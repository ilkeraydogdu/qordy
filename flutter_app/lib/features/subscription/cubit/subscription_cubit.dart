import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:qordy_app/features/packages/data/packages_repository.dart';

import 'subscription_state.dart';

/// Abonelik durumunu merkezi yöneten cubit.
/// - Uygulama açılışında bir kez [refresh] çağrılır.
/// - Arkaplan timer'ı ile her X dakikada bir durum tazelenir (varsayılan 5dk).
/// - [markUpsellReminderShown] ile günde bir Satın Al hatırlatıcısı yönetilir.
class SubscriptionCubit extends Cubit<SubscriptionState> {
  final PackagesRepository? _repository;
  final Duration pollInterval;
  Timer? _poller;

  SubscriptionCubit({
    required PackagesRepository repository,
    this.pollInterval = const Duration(minutes: 5),
  })  : _repository = repository,
        super(const SubscriptionState());

  /// Test-only: repository'siz cubit, sadece state yayınlayıp tüketen testler için.
  /// Prod kodunda kullanılmaz.
  @visibleForTesting
  SubscriptionCubit.forTesting([SubscriptionState? initial])
      : _repository = null,
        pollInterval = const Duration(days: 30),
        super(initial ?? const SubscriptionState());

  Future<void> refresh({bool silent = false}) async {
    final repo = _repository;
    if (repo == null) return; // test modunda
    if (!silent) {
      emit(state.copyWith(isLoading: true, clearError: true));
    }
    try {
      final res = await repo.getSubscriptionStatus();
      if (res.isSuccess && res.data != null) {
        emit(state.copyWith(
          isLoading: false,
          subscription: res.data,
          clearError: true,
        ));
      } else {
        emit(state.copyWith(
          isLoading: false,
          error: res.error,
        ));
      }
    } catch (e) {
      emit(state.copyWith(isLoading: false, error: e.toString()));
    }
  }

  void startPolling() {
    _poller?.cancel();
    _poller = Timer.periodic(pollInterval, (_) => refresh(silent: true));
  }

  void stopPolling() {
    _poller?.cancel();
    _poller = null;
  }

  void markUpsellReminderShown() {
    emit(state.copyWith(lastUpsellReminderAt: DateTime.now()));
  }

  /// Günde 1 kez olacak şekilde upsell reminder gerekip gerekmediğini söyler.
  bool shouldShowDailyUpsell({Duration cooldown = const Duration(hours: 20)}) {
    if (!state.isTrial && !state.isGrace) return false;
    final last = state.lastUpsellReminderAt;
    if (last == null) return true;
    return DateTime.now().difference(last) > cooldown;
  }

  @override
  Future<void> close() {
    stopPolling();
    return super.close();
  }
}
