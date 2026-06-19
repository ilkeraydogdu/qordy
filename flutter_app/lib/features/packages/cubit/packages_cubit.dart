import 'dart:async';

import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:qordy_app/models/package_model.dart';

import '../data/packages_repository.dart';
import 'packages_state.dart';

/// Cubit that powers both the packages listing screen and the iyzico
/// in-app checkout. Three distinct flows live here:
///
/// 1. Listing: fetch available packages, current subscription phase and
///    any superadmin-assigned offer in parallel.
/// 2. Monthly/Yearly toggle: purely local, re-emits the loaded state.
/// 3. Checkout: initiate iyzico hosted form on the backend and emit a
///    [PackageCheckoutReady] state which the WebView screen consumes.
///    When the WebView deeplinks back with a result we either emit
///    [PackagePurchaseSucceeded] (callback saw `status=success`) or
///    fall back to polling `/payment/iyzico/status` for resilience.
class PackagesCubit extends Cubit<PackagesState> {
  final PackagesRepository _repository;

  List<SubscriptionPackage> _packages = [];
  Subscription? _subscription;
  AssignedOffer? _assignedOffer;
  bool _isYearly = false;

  Timer? _statusPollTimer;
  PackageCheckoutReady? _checkoutContext;

  PackagesCubit({required PackagesRepository repository})
      : _repository = repository,
        super(const PackagesInitial());

  List<SubscriptionPackage> get packages => _packages;
  Subscription? get currentSubscription => _subscription;

  Future<void> loadPackages() async {
    if (state is! PackagesLoaded) {
      emit(const PackagesLoading());
    }
    // Three endpoints are loaded in parallel but none of them should
    // take down the whole Paketler screen if they fail in isolation
    // (the previous implementation relied on a blanket try/catch that
    // silently swallowed the exception AFTER `_packages` had values,
    // leaving the UI stuck on the loading spinner forever — which is
    // exactly the "Paketler açılırken hata" the customer reported).
    //
    // Packages is required (hard error). Subscription + assigned offer
    // are optional — we log and carry on if they misbehave.
    try {
      final packagesResp = await _repository.getPackages();
      if (packagesResp.isSuccess) {
        final data = packagesResp.data;
        _packages = data is List<SubscriptionPackage>
            ? data
            : const <SubscriptionPackage>[];
      } else if (_packages.isEmpty) {
        emit(PackagesError(packagesResp.error ?? 'Paketler yüklenemedi'));
        return;
      }
    } catch (e) {
      if (_packages.isEmpty) {
        emit(PackagesError('Paketler yüklenemedi: $e'));
        return;
      }
    }

    try {
      final subResp = await _repository.getSubscriptionStatus();
      if (subResp.isSuccess && subResp.data != null) {
        final data = subResp.data;
        if (data is Subscription) _subscription = data;
      }
    } catch (_) {
      // Non-fatal: kullanıcı henüz abone değil olabilir.
    }

    try {
      final offerResp = await _repository.getAssignedOffer();
      if (offerResp.isSuccess && offerResp.data is Map) {
        final data = offerResp.data as Map;
        final offerMap = data['offer'];
        if (offerMap is Map) {
          _assignedOffer = AssignedOffer.fromJson(
            offerMap.map((k, v) => MapEntry(k.toString(), v)),
          );
        } else {
          _assignedOffer = null;
        }
      } else {
        _assignedOffer = null;
      }
    } catch (_) {
      _assignedOffer = null;
    }

    _emitLoaded();
  }

  void toggleBillingCycle() {
    _isYearly = !_isYearly;
    _emitLoaded();
  }

  void setYearly(bool yearly) {
    if (_isYearly == yearly) return;
    _isYearly = yearly;
    _emitLoaded();
  }

  /// Step 1 of the in-app purchase: ask the backend to create an iyzico
  /// checkout session and return the HTML form content. The screen then
  /// pushes the WebView; upon deeplink callback it will call
  /// [finalizePurchase].
  Future<void> beginCheckout(
    SubscriptionPackage package, {
    String? billingCycleOverride,
  }) async {
    final billingCycle = billingCycleOverride ?? (_isYearly ? 'yearly' : 'monthly');
    emit(PackageCheckoutInitiating(
      packageName: package.name ?? 'Paket',
      billingCycle: billingCycle,
    ));

    try {
      final resp = await _repository.initiateIyzicoPayment(
        packageId: package.packageId,
        billingCycle: billingCycle,
      );
      if (!resp.isSuccess || resp.data == null) {
        emit(PackagesError(resp.error ?? 'Ödeme başlatılamadı'));
        _emitLoaded();
        return;
      }
      final data = resp.data!;
      final html = (data['checkout_form_content'] ?? '').toString();
      final token = (data['token'] ?? '').toString();
      if (html.isEmpty || token.isEmpty) {
        emit(const PackagesError('Ödeme sağlayıcısından geçerli yanıt alınamadı'));
        _emitLoaded();
        return;
      }
      final ready = PackageCheckoutReady(
        checkoutHtml: html,
        paymentPageUrl: data['payment_page_url']?.toString(),
        token: token,
        conversationId: (data['conversation_id'] ?? '').toString(),
        returnUrl: (data['return_url'] ?? 'qordy://payment/return').toString(),
        amount: (data['amount'] as num?)?.toDouble() ?? 0.0,
        packageName: (data['package_name'] ?? package.name ?? 'Paket').toString(),
        billingCycle: (data['billing_cycle'] ?? billingCycle).toString(),
        subscriptionId: (data['subscription_id'] ?? '').toString(),
      );
      _checkoutContext = ready;
      emit(ready);
    } catch (e) {
      emit(PackagesError(e.toString()));
      _emitLoaded();
    }
  }

  /// Step 2 of the in-app purchase: WebView caught the deeplink callback
  /// (`qordy://payment/return?status=success|fail&token=…`). We confirm
  /// with the backend (poll status once) and emit the terminal state.
  Future<void> finalizePurchase({
    required String status,
    String? token,
    String? conversationId,
    String? subscriptionId,
    String? errorMessage,
  }) async {
    _stopPolling();
    final ready = _checkoutContext;
    final packageName = ready?.packageName ?? 'Paket';
    final billingCycle = ready?.billingCycle ?? (_isYearly ? 'yearly' : 'monthly');

    if (status != 'success') {
      emit(PackagesError(
        errorMessage ?? 'Ödeme tamamlanamadı. Lütfen tekrar deneyin.',
      ));
      // Re-emit loaded so the user immediately sees the list again.
      await loadPackages();
      return;
    }

    // Confirm with the backend — protects against spoofed deeplinks
    // and gives us authoritative payment/subscription info.
    try {
      final resp = await _repository.iyzicoStatus(
        token: token ?? ready?.token,
        conversationId: conversationId ?? ready?.conversationId,
        subscriptionId: subscriptionId ?? ready?.subscriptionId,
      );
      if (resp.isSuccess && resp.data is Map) {
        final serverStatus = (resp.data!['status'] ?? '').toString();
        if (serverStatus == 'completed') {
          emit(PackagePurchaseSucceeded(
            packageName: packageName,
            billingCycle: billingCycle,
          ));
          await loadPackages();
          return;
        }
        if (serverStatus == 'pending') {
          _startPolling(
            token: token ?? ready?.token,
            conversationId: conversationId ?? ready?.conversationId,
            subscriptionId: subscriptionId ?? ready?.subscriptionId,
            packageName: packageName,
            billingCycle: billingCycle,
          );
          return;
        }
      }
    } catch (_) {
      // fall through to error
    }

    emit(const PackagesError(
      'Ödeme doğrulanamadı. Lütfen birkaç saniye sonra paketlerinizi kontrol edin.',
    ));
    await loadPackages();
  }

  /// Polls `/payment/iyzico/status` every 3s for up to 30s. Used when
  /// iyzico confirmed success on the webview but our payment row is
  /// still `pending` (webhook ordering delay). Emits success as soon as
  /// the server catches up.
  void _startPolling({
    String? token,
    String? conversationId,
    String? subscriptionId,
    required String packageName,
    required String billingCycle,
  }) {
    _statusPollTimer?.cancel();
    var attempts = 0;
    _statusPollTimer = Timer.periodic(const Duration(seconds: 3), (t) async {
      attempts++;
      if (attempts > 10) {
        t.cancel();
        emit(const PackagesError(
          'Ödeme onayı bekleniyor. Aktivasyon biraz gecikebilir.',
        ));
        await loadPackages();
        return;
      }
      try {
        final resp = await _repository.iyzicoStatus(
          token: token,
          conversationId: conversationId,
          subscriptionId: subscriptionId,
        );
        if (!resp.isSuccess || resp.data is! Map) return;
        final st = (resp.data!['status'] ?? '').toString();
        if (st == 'completed') {
          t.cancel();
          emit(PackagePurchaseSucceeded(
            packageName: packageName,
            billingCycle: billingCycle,
          ));
          await loadPackages();
        } else if (st == 'failed') {
          t.cancel();
          emit(const PackagesError('Ödeme reddedildi. Lütfen tekrar deneyin.'));
          await loadPackages();
        }
      } catch (_) {}
    });
  }

  void _stopPolling() {
    _statusPollTimer?.cancel();
    _statusPollTimer = null;
  }

  /// Kullanıcı WebView'i manuel kapattıysa / geri çıktıysa loaded
  /// duruma geri dönüyoruz — arka planda hala bir checkout açık olabilir
  /// ama kullanıcı vazgeçti.
  void cancelCheckout() {
    _stopPolling();
    _emitLoaded();
  }

  void _emitLoaded() {
    emit(PackagesLoaded(
      packages: _packages,
      currentSubscription: _subscription,
      assignedOffer: _assignedOffer,
      isYearly: _isYearly,
    ));
  }

  @override
  Future<void> close() {
    _stopPolling();
    return super.close();
  }
}
