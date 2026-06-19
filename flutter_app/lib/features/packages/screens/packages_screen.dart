import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:get_it/get_it.dart';
import 'package:qordy_app/config/theme.dart';
import 'package:qordy_app/features/auth/cubit/auth_cubit.dart';
import 'package:qordy_app/features/auth/cubit/auth_state.dart';
import 'package:qordy_app/features/subscription/screens/payment_checkout_screen.dart';
import 'package:qordy_app/models/package_model.dart';
import 'package:url_launcher/url_launcher.dart';

import '../cubit/packages_cubit.dart';
import '../cubit/packages_state.dart';

/// Abonelik / paketler ekranı.
///
/// Üç farklı moddan birinde açılır:
/// 1. `active/trial` fazındaki kullanıcı → paketler listesi + "mevcut
///    aboneliğiniz" kartı.
/// 2. `expired/suspended` (paywall) → üstte uyarı banner'ı + paketler
///    listesi, AppBar'da geri butonu yok, alt nav gizli.
/// 3. Superadmin "özel teklif" hazırladıysa → üstte öne çıkan gradient
///    banner, doğrudan hosted ödeme linkine götürür.
class PackagesScreen extends StatelessWidget {
  const PackagesScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return BlocProvider(
      create: (_) => GetIt.instance<PackagesCubit>()..loadPackages(),
      child: const _PackagesView(),
    );
  }
}

class _PackagesView extends StatelessWidget {
  const _PackagesView();

  @override
  Widget build(BuildContext context) {
    final authState = context.watch<AuthCubit>().state;
    final isPaywalled =
        authState is Authenticated && authState.isPaywalled;

    return BlocListener<PackagesCubit, PackagesState>(
      listenWhen: (a, b) => a.runtimeType != b.runtimeType || b is PackageCheckoutReady,
      listener: (context, state) async {
        if (state is PackageCheckoutReady) {
          final cubit = context.read<PackagesCubit>();
          await Navigator.of(context).push(
            MaterialPageRoute(
              builder: (_) => BlocProvider.value(
                value: cubit,
                child: PaymentCheckoutScreen(checkout: state),
              ),
              fullscreenDialog: true,
            ),
          );
        } else if (state is PackagePurchaseSucceeded) {
          HapticFeedback.mediumImpact();
          // Auth cubit'teki subscription phase'i güncelle ki
          // paywall hemen kalksın ve kullanıcı dashboard'a dönebilsin.
          await context.read<AuthCubit>().refreshSubscriptionStatus();
          if (!context.mounted) return;
          _showSuccessSheet(context, state);
        } else if (state is PackagesError) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text(state.message),
              backgroundColor: AppColors.error,
              behavior: SnackBarBehavior.floating,
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(12),
              ),
            ),
          );
        }
      },
      child: Scaffold(
        backgroundColor: context.brandScaffoldBg,
        appBar: AppBar(
          title: Text(isPaywalled ? 'Aboneliğinizi Yenileyin' : 'Paketler'),
          automaticallyImplyLeading: !isPaywalled,
          backgroundColor: context.brandCard,
          surfaceTintColor: context.brandCard,
          elevation: 0,
        ),
        body: BlocBuilder<PackagesCubit, PackagesState>(
          buildWhen: (a, b) =>
              b is PackagesLoading ||
              b is PackagesLoaded ||
              b is PackageCheckoutInitiating ||
              b is PackagesError,
          builder: (context, state) {
            if (state is PackagesLoading || state is PackagesInitial) {
              return const _CenteredSpinner(label: 'Paketler yükleniyor…');
            }
            if (state is PackageCheckoutInitiating) {
              return _CenteredSpinner(
                label: '${state.packageName} için güvenli ödeme açılıyor…',
              );
            }

            if (state is! PackagesLoaded) {
              return const _CenteredSpinner(label: 'Paketler yükleniyor…');
            }
            final PackagesLoaded loaded = state;

            return RefreshIndicator(
              color: AppColors.primary,
              onRefresh: () => context.read<PackagesCubit>().loadPackages(),
              child: ListView(
                padding: const EdgeInsets.fromLTRB(16, 8, 16, 32),
                children: [
                  if (isPaywalled)
                    _PaywallBanner(subscription: loaded.currentSubscription),
                  if (!isPaywalled && loaded.currentSubscription != null)
                    _CurrentSubscriptionCard(
                      subscription: loaded.currentSubscription!,
                    ),
                  if (loaded.assignedOffer != null)
                    Padding(
                      padding: const EdgeInsets.only(top: 16),
                      child: _AssignedOfferCard(offer: loaded.assignedOffer!),
                    ),
                  const SizedBox(height: 20),
                  _BillingToggle(isYearly: loaded.isYearly),
                  const SizedBox(height: 16),
                  ...loaded.packages.map(
                    (pkg) {
                      // Trial fazındaki kullanıcı için "currentSubscription"
                      // dolu gelse bile Satın Al CTA görünmeli — trial
                      // paketi otomatik atanıyor ama kullanıcı henüz
                      // ödemedi. Sadece gerçekten AKTİF abonelik varsa
                      // paketi disabled "Aktif Paketiniz" olarak göster.
                      final sub = loaded.currentSubscription;
                      final isActive =
                          sub?.phase == SubscriptionPhase.active;
                      final isCurrent = !isPaywalled &&
                          isActive &&
                          sub?.packageId == pkg.packageId;
                      return Padding(
                        padding: const EdgeInsets.only(bottom: 14),
                        child: _PackageCard(
                          package: pkg,
                          isYearly: loaded.isYearly,
                          isCurrentPackage: isCurrent,
                        ),
                      );
                    },
                  ),
                  const SizedBox(height: 24),
                  const _SecurityFooter(),
                ],
              ),
            );
          },
        ),
      ),
    );
  }

  void _showSuccessSheet(BuildContext context, PackagePurchaseSucceeded state) {
    showModalBottomSheet<void>(
      context: context,
      backgroundColor: context.brandCard,
      isDismissible: false,
      enableDrag: false,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      builder: (ctx) => Padding(
        padding: const EdgeInsets.fromLTRB(24, 18, 24, 32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 56,
              height: 4,
              margin: const EdgeInsets.only(bottom: 22),
              decoration: BoxDecoration(
                color: AppColors.border,
                borderRadius: BorderRadius.circular(99),
              ),
            ),
            Container(
              width: 72,
              height: 72,
              decoration: const BoxDecoration(
                color: AppColors.success,
                shape: BoxShape.circle,
              ),
              child: const Icon(Icons.check, color: Colors.white, size: 40),
            ),
            const SizedBox(height: 18),
            Text(
              'Ödeme başarılı',
              style: TextStyle(
                fontSize: 20,
                fontWeight: FontWeight.w800,
                color: ctx.brandTextPrimary,
              ),
            ),
            const SizedBox(height: 6),
            Text(
              '${state.packageName} paketi (${state.billingCycle == 'yearly' ? 'yıllık' : 'aylık'}) aktifleştirildi.',
              textAlign: TextAlign.center,
              style: TextStyle(
                fontSize: 14,
                color: ctx.brandTextSecondary,
                height: 1.4,
              ),
            ),
            const SizedBox(height: 22),
            SizedBox(
              width: double.infinity,
              child: FilledButton(
                style: FilledButton.styleFrom(
                  backgroundColor: AppColors.primary,
                  padding: const EdgeInsets.symmetric(vertical: 14),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(14),
                  ),
                ),
                onPressed: () => Navigator.of(ctx).pop(),
                child: const Text(
                  'Harika, devam edelim',
                  style: TextStyle(fontSize: 15, fontWeight: FontWeight.w600),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

// ──────────────────────────────────────────────────────────────────
//  Helpers & widgets
// ──────────────────────────────────────────────────────────────────

class _CenteredSpinner extends StatelessWidget {
  final String label;
  const _CenteredSpinner({required this.label});

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const CircularProgressIndicator(color: AppColors.primary),
            const SizedBox(height: 16),
            Text(
              label,
              textAlign: TextAlign.center,
              style: TextStyle(
                color: context.brandTextSecondary,
                fontSize: 14,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _PaywallBanner extends StatelessWidget {
  final Subscription? subscription;
  const _PaywallBanner({this.subscription});

  @override
  Widget build(BuildContext context) {
    final phase = subscription?.phase;
    final isSuspended = phase == SubscriptionPhase.suspended ||
        phase == SubscriptionPhase.expired;
    final title = isSuspended
        ? 'Aboneliğiniz askıya alındı'
        : 'Deneme süreniz sona erdi';
    final subtitle = isSuspended
        ? 'Uygulamayı kullanmaya devam etmek için lütfen bir paket seçin.'
        : 'İşletmenizin kaldığı yerden devam edebilmesi için hemen bir paket seçin — verileriniz güvenle sizi bekliyor.';

    return Container(
      margin: const EdgeInsets.only(top: 8),
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [Color(0xFFEF4444), Color(0xFFDC2626)],
        ),
        borderRadius: BorderRadius.circular(18),
        boxShadow: [
          BoxShadow(
            color: const Color(0xFFEF4444).withValues(alpha: 0.25),
            blurRadius: 14,
            offset: const Offset(0, 6),
          ),
        ],
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 44,
            height: 44,
            decoration: BoxDecoration(
              color: Colors.white.withValues(alpha: 0.2),
              borderRadius: BorderRadius.circular(12),
            ),
            alignment: Alignment.center,
            child: const Icon(Icons.lock_clock, color: Colors.white, size: 24),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: const TextStyle(
                    color: Colors.white,
                    fontWeight: FontWeight.w800,
                    fontSize: 16,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  subtitle,
                  style: TextStyle(
                    color: Colors.white.withValues(alpha: 0.92),
                    fontSize: 13.5,
                    height: 1.4,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _CurrentSubscriptionCard extends StatelessWidget {
  final Subscription subscription;
  const _CurrentSubscriptionCard({required this.subscription});

  @override
  Widget build(BuildContext context) {
    final phase = subscription.phase;
    final isActive = phase == SubscriptionPhase.active;
    final isTrial = phase == SubscriptionPhase.trial;
    final isGrace = phase == SubscriptionPhase.grace;

    final (colors, badge, badgeColor) = switch (phase) {
      SubscriptionPhase.active => (
          [AppColors.primary, AppColors.primaryDark],
          'Aktif',
          AppColors.success,
        ),
      SubscriptionPhase.trial => (
          [const Color(0xFF10B981), const Color(0xFF059669)],
          'Ücretsiz Deneme',
          Colors.white,
        ),
      SubscriptionPhase.grace => (
          [const Color(0xFFF59E0B), const Color(0xFFD97706)],
          'Ödeme Bekleniyor',
          Colors.white,
        ),
      _ => (
          [AppColors.textSecondary, AppColors.textPrimary],
          subscription.status ?? 'Bilgi Yok',
          Colors.white,
        ),
    };

    final daysInfo = isTrial && subscription.daysLeft > 0
        ? '${subscription.daysLeft} gün kaldı'
        : (isGrace && subscription.graceDaysLeft > 0
            ? '${subscription.graceDaysLeft} gün içinde askıya alınacak'
            : (subscription.currentPeriodEnd != null
                ? 'Yenileme: ${_formatDate(subscription.currentPeriodEnd!)}'
                : ''));

    return Container(
      margin: const EdgeInsets.only(top: 4),
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: colors,
        ),
        borderRadius: BorderRadius.circular(20),
        boxShadow: [
          BoxShadow(
            color: colors.first.withValues(alpha: 0.25),
            blurRadius: 16,
            offset: const Offset(0, 6),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Icon(Icons.workspace_premium_outlined,
                  color: Colors.white, size: 20),
              const SizedBox(width: 8),
              Text(
                'Mevcut Paketiniz',
                style: TextStyle(
                  color: Colors.white.withValues(alpha: 0.9),
                  fontSize: 13,
                  fontWeight: FontWeight.w500,
                ),
              ),
              const Spacer(),
              Container(
                padding:
                    const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                decoration: BoxDecoration(
                  color: isActive
                      ? Colors.white.withValues(alpha: 0.25)
                      : Colors.white.withValues(alpha: 0.15),
                  borderRadius: BorderRadius.circular(99),
                ),
                child: Text(
                  badge,
                  style: TextStyle(
                    color: badgeColor,
                    fontSize: 11,
                    fontWeight: FontWeight.w700,
                    letterSpacing: 0.2,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          Text(
            subscription.packageName ?? 'Paket',
            style: const TextStyle(
              color: Colors.white,
              fontSize: 24,
              fontWeight: FontWeight.w800,
              letterSpacing: -0.3,
            ),
          ),
          if (daysInfo.isNotEmpty) ...[
            const SizedBox(height: 8),
            Row(
              children: [
                Icon(
                  isTrial ? Icons.schedule : Icons.event,
                  size: 14,
                  color: Colors.white.withValues(alpha: 0.85),
                ),
                const SizedBox(width: 6),
                Text(
                  daysInfo,
                  style: TextStyle(
                    color: Colors.white.withValues(alpha: 0.85),
                    fontSize: 13,
                  ),
                ),
              ],
            ),
          ],
        ],
      ),
    );
  }

  String _formatDate(String raw) {
    try {
      final dt = DateTime.parse(raw).toLocal();
      final months = [
        'Oca', 'Şub', 'Mar', 'Nis', 'May', 'Haz',
        'Tem', 'Ağu', 'Eyl', 'Eki', 'Kas', 'Ara',
      ];
      return '${dt.day} ${months[dt.month - 1]} ${dt.year}';
    } catch (_) {
      return raw.split('T').first;
    }
  }
}

class _AssignedOfferCard extends StatelessWidget {
  final AssignedOffer offer;
  const _AssignedOfferCard({required this.offer});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [Color(0xFF7C3AED), Color(0xFF4338CA)],
        ),
        borderRadius: BorderRadius.circular(18),
        boxShadow: [
          BoxShadow(
            color: const Color(0xFF7C3AED).withValues(alpha: 0.3),
            blurRadius: 16,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Icon(Icons.card_giftcard, color: Colors.white, size: 22),
              const SizedBox(width: 10),
              const Text(
                'Size Özel Teklif',
                style: TextStyle(
                  color: Colors.white,
                  fontWeight: FontWeight.w800,
                  fontSize: 15,
                  letterSpacing: 0.3,
                ),
              ),
              const Spacer(),
              Container(
                padding:
                    const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: 0.2),
                  borderRadius: BorderRadius.circular(6),
                ),
                child: const Text(
                  'YENİ',
                  style: TextStyle(
                    color: Colors.white,
                    fontWeight: FontWeight.w800,
                    fontSize: 10,
                    letterSpacing: 0.5,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Text(
            offer.packageName ?? 'Özel Paket',
            style: const TextStyle(
              color: Colors.white,
              fontSize: 20,
              fontWeight: FontWeight.w800,
            ),
          ),
          if (offer.note != null && offer.note!.trim().isNotEmpty) ...[
            const SizedBox(height: 4),
            Text(
              offer.note!,
              style: TextStyle(
                color: Colors.white.withValues(alpha: 0.88),
                fontSize: 13,
                height: 1.4,
              ),
            ),
          ],
          if (offer.customPrice != null) ...[
            const SizedBox(height: 12),
            Row(
              crossAxisAlignment: CrossAxisAlignment.end,
              children: [
                Text(
                  '₺${offer.customPrice!.toStringAsFixed(0)}',
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 30,
                    fontWeight: FontWeight.w800,
                    letterSpacing: -0.5,
                  ),
                ),
                const SizedBox(width: 6),
                Padding(
                  padding: const EdgeInsets.only(bottom: 6),
                  child: Text(
                    offer.durationMonths != null
                        ? '/ ${offer.durationMonths} ay'
                        : '/ tek seferlik',
                    style: TextStyle(
                      color: Colors.white.withValues(alpha: 0.8),
                      fontSize: 14,
                    ),
                  ),
                ),
              ],
            ),
          ],
          const SizedBox(height: 16),
          SizedBox(
            width: double.infinity,
            child: FilledButton(
              style: FilledButton.styleFrom(
                backgroundColor: Colors.white,
                foregroundColor: const Color(0xFF4338CA),
                padding: const EdgeInsets.symmetric(vertical: 13),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(12),
                ),
              ),
              onPressed: () => _openOffer(context, offer),
              child: const Text(
                'Teklifi İncele ve Öde',
                style: TextStyle(fontWeight: FontWeight.w700, fontSize: 14),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Future<void> _openOffer(BuildContext context, AssignedOffer offer) async {
    final url = offer.publicUrl;
    if (url == null || url.isEmpty) return;
    final uri = Uri.tryParse(url);
    if (uri == null) return;
    await launchUrl(uri, mode: LaunchMode.externalApplication);
  }
}

class _BillingToggle extends StatelessWidget {
  final bool isYearly;
  const _BillingToggle({required this.isYearly});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(4),
      decoration: BoxDecoration(
        color: context.brandBorder,
        borderRadius: BorderRadius.circular(14),
      ),
      child: Row(
        children: [
          _buildTab(context, label: 'Aylık', selected: !isYearly, yearly: false),
          _buildTab(
            context,
            label: 'Yıllık',
            selected: isYearly,
            yearly: true,
            badge: 'İndirim',
          ),
        ],
      ),
    );
  }

  Widget _buildTab(
    BuildContext context, {
    required String label,
    required bool selected,
    required bool yearly,
    String? badge,
  }) {
    return Expanded(
      child: Material(
        color: Colors.transparent,
        borderRadius: BorderRadius.circular(10),
        child: InkWell(
          borderRadius: BorderRadius.circular(10),
          onTap: () => context.read<PackagesCubit>().setYearly(yearly),
          child: AnimatedContainer(
            duration: const Duration(milliseconds: 180),
            padding: const EdgeInsets.symmetric(vertical: 12),
            decoration: BoxDecoration(
              color: selected ? context.brandCard : Colors.transparent,
              borderRadius: BorderRadius.circular(10),
              boxShadow: selected
                  ? [
                      BoxShadow(
                        color: Colors.black.withValues(alpha: 0.05),
                        blurRadius: 6,
                        offset: const Offset(0, 1),
                      ),
                    ]
                  : null,
            ),
            alignment: Alignment.center,
            child: Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Text(
                  label,
                  style: TextStyle(
                    fontWeight: FontWeight.w700,
                    fontSize: 14,
                    color: selected
                        ? AppColors.primary
                        : context.brandTextSecondary,
                  ),
                ),
                if (badge != null) ...[
                  const SizedBox(width: 6),
                  Container(
                    padding:
                        const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                    decoration: BoxDecoration(
                      color: AppColors.success.withValues(alpha: 0.14),
                      borderRadius: BorderRadius.circular(6),
                    ),
                    child: Text(
                      badge,
                      style: TextStyle(
                        color: AppColors.success,
                        fontSize: 10,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                ],
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _PackageCard extends StatelessWidget {
  final SubscriptionPackage package;
  final bool isYearly;
  final bool isCurrentPackage;

  const _PackageCard({
    required this.package,
    required this.isYearly,
    this.isCurrentPackage = false,
  });

  @override
  Widget build(BuildContext context) {
    final isPopular = package.isPopular == true;
    final monthly = package.priceMonthly ?? 0;
    final yearly = package.priceYearly ?? 0;
    final price = isYearly ? yearly : monthly;
    final period = isYearly ? '/yıl' : '/ay';

    // Yıllık fiyatı aylığa bölüp "aylık X" göster — tasarruf mesajı
    double? yearlyDiscountPct;
    if (isYearly && monthly > 0 && yearly > 0) {
      final twelveMonthly = monthly * 12;
      if (twelveMonthly > yearly) {
        yearlyDiscountPct = ((twelveMonthly - yearly) / twelveMonthly) * 100;
      }
    }

    final featureEntries = package.features?.entries.toList() ?? [];

    return Container(
      decoration: BoxDecoration(
        color: context.brandCard,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(
          color: isPopular ? AppColors.primary : context.brandBorder,
          width: isPopular ? 2 : 1,
        ),
        boxShadow: isPopular
            ? [
                BoxShadow(
                  color: AppColors.primary.withValues(alpha: 0.1),
                  blurRadius: 14,
                  offset: const Offset(0, 6),
                ),
              ]
            : [
                BoxShadow(
                  color: Colors.black.withValues(alpha: 0.03),
                  blurRadius: 8,
                  offset: const Offset(0, 2),
                ),
              ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          if (isPopular)
            Container(
              padding: const EdgeInsets.symmetric(vertical: 8),
              decoration: const BoxDecoration(
                color: AppColors.primary,
                borderRadius:
                    BorderRadius.vertical(top: Radius.circular(18.5)),
              ),
              alignment: Alignment.center,
              child: const Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Icon(Icons.star, color: Colors.white, size: 14),
                  SizedBox(width: 6),
                  Text(
                    'EN POPÜLER',
                    style: TextStyle(
                      color: Colors.white,
                      fontWeight: FontWeight.w800,
                      fontSize: 11,
                      letterSpacing: 0.6,
                    ),
                  ),
                ],
              ),
            ),
          Padding(
            padding: const EdgeInsets.all(20),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  package.name ?? 'Paket',
                  style: TextStyle(
                    fontSize: 20,
                    fontWeight: FontWeight.w800,
                    color: context.brandTextPrimary,
                    letterSpacing: -0.3,
                  ),
                ),
                if (package.description != null &&
                    package.description!.trim().isNotEmpty) ...[
                  const SizedBox(height: 6),
                  Text(
                    package.description!,
                    style: TextStyle(
                      fontSize: 13.5,
                      color: context.brandTextSecondary,
                      height: 1.45,
                    ),
                  ),
                ],
                const SizedBox(height: 16),
                Row(
                  crossAxisAlignment: CrossAxisAlignment.end,
                  children: [
                    Text(
                      '₺${price.toStringAsFixed(0)}',
                      style: TextStyle(
                        fontSize: 36,
                        fontWeight: FontWeight.w900,
                        color: context.brandTextPrimary,
                        letterSpacing: -1,
                        height: 1,
                      ),
                    ),
                    const SizedBox(width: 4),
                    Padding(
                      padding: const EdgeInsets.only(bottom: 6),
                      child: Text(
                        period,
                        style: TextStyle(
                          fontSize: 15,
                          color: context.brandTextSecondary,
                          fontWeight: FontWeight.w500,
                        ),
                      ),
                    ),
                  ],
                ),
                if (isYearly && yearlyDiscountPct != null) ...[
                  const SizedBox(height: 6),
                  Container(
                    padding: const EdgeInsets.symmetric(
                        horizontal: 8, vertical: 4),
                    decoration: BoxDecoration(
                      color: AppColors.success.withValues(alpha: 0.12),
                      borderRadius: BorderRadius.circular(6),
                    ),
                    child: Text(
                      '%${yearlyDiscountPct.toStringAsFixed(0)} tasarruf',
                      style: TextStyle(
                        color: AppColors.success,
                        fontSize: 12,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                ],
                const SizedBox(height: 18),
                ...featureEntries.map(
                  (entry) => Padding(
                    padding: const EdgeInsets.only(bottom: 9),
                    child: Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Container(
                          width: 20,
                          height: 20,
                          decoration: BoxDecoration(
                            color: _featureEnabled(entry.value)
                                ? AppColors.primarySoft
                                : Colors.transparent,
                            shape: BoxShape.circle,
                          ),
                          alignment: Alignment.center,
                          child: Icon(
                            _featureEnabled(entry.value)
                                ? Icons.check
                                : Icons.close,
                            size: 13,
                            color: _featureEnabled(entry.value)
                                ? AppColors.primary
                                : context.brandTextHint,
                          ),
                        ),
                        const SizedBox(width: 10),
                        Expanded(
                          child: Text(
                            _featureLabel(entry.key),
                            style: TextStyle(
                              fontSize: 14,
                              color: context.brandTextPrimary,
                              height: 1.4,
                            ),
                          ),
                        ),
                        if (entry.value is! bool)
                          Text(
                            entry.value.toString(),
                            style: TextStyle(
                              fontWeight: FontWeight.w700,
                              fontSize: 14,
                              color: context.brandTextPrimary,
                            ),
                          ),
                      ],
                    ),
                  ),
                ),
                const SizedBox(height: 18),
                SizedBox(
                  width: double.infinity,
                  height: 50,
                  child: isCurrentPackage
                      ? OutlinedButton(
                          onPressed: null,
                          style: OutlinedButton.styleFrom(
                            side:
                                BorderSide(color: context.brandBorder),
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(14),
                            ),
                          ),
                          child: Text(
                            'Aktif Paketiniz',
                            style: TextStyle(
                              color: context.brandTextSecondary,
                              fontWeight: FontWeight.w600,
                              fontSize: 15,
                            ),
                          ),
                        )
                      : FilledButton(
                          onPressed: () => _confirmPurchase(context),
                          style: FilledButton.styleFrom(
                            backgroundColor: isPopular
                                ? AppColors.primary
                                : context.brandTextPrimary,
                            foregroundColor: Colors.white,
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(14),
                            ),
                          ),
                          child: Row(
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: const [
                              Text(
                                'Kredi Kartı ile Öde',
                                style: TextStyle(
                                  fontSize: 15,
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                              SizedBox(width: 6),
                              Icon(Icons.arrow_forward, size: 18),
                            ],
                          ),
                        ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  bool _featureEnabled(dynamic value) {
    if (value is bool) return value;
    if (value == null) return false;
    final s = value.toString().toLowerCase();
    if (s == 'false' || s == '0' || s == 'yok' || s == 'hayır') return false;
    return true;
  }

  String _featureLabel(String key) {
    // Backend snake_case anahtarlar döndürebiliyor — insan okunaklı
    // hâle getirip görsel tutarlılık sağlıyoruz.
    if (key.isEmpty) return key;
    final spaced = key.replaceAll('_', ' ').replaceAll('-', ' ');
    return spaced[0].toUpperCase() + spaced.substring(1);
  }

  void _confirmPurchase(BuildContext context) {
    final price = isYearly
        ? (package.priceYearly ?? 0)
        : (package.priceMonthly ?? 0);
    final period = isYearly ? 'yıllık' : 'aylık';

    showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      backgroundColor: context.brandCard,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(22)),
      ),
      builder: (ctx) => Padding(
        padding: EdgeInsets.fromLTRB(
          20,
          14,
          20,
          20 + MediaQuery.of(ctx).viewInsets.bottom,
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              width: 48,
              height: 4,
              margin: const EdgeInsets.only(bottom: 18),
              decoration: BoxDecoration(
                color: AppColors.border,
                borderRadius: BorderRadius.circular(99),
              ),
            ),
            Text(
              'Satın Alma Onayı',
              style: TextStyle(
                fontSize: 18,
                fontWeight: FontWeight.w800,
                color: ctx.brandTextPrimary,
              ),
            ),
            const SizedBox(height: 14),
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: ctx.brandSurface,
                borderRadius: BorderRadius.circular(14),
                border: Border.all(color: ctx.brandBorder),
              ),
              child: Row(
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          package.name ?? 'Paket',
                          style: TextStyle(
                            fontSize: 15,
                            fontWeight: FontWeight.w700,
                            color: ctx.brandTextPrimary,
                          ),
                        ),
                        const SizedBox(height: 3),
                        Text(
                          '${period.substring(0, 1).toUpperCase()}${period.substring(1)} abonelik',
                          style: TextStyle(
                            fontSize: 12,
                            color: ctx.brandTextSecondary,
                          ),
                        ),
                      ],
                    ),
                  ),
                  Text(
                    '₺${price.toStringAsFixed(2)}',
                    style: TextStyle(
                      fontSize: 18,
                      fontWeight: FontWeight.w800,
                      color: AppColors.primary,
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                const Icon(Icons.verified_user_outlined,
                    size: 16, color: AppColors.success),
                const SizedBox(width: 6),
                Expanded(
                  child: Text(
                    '256-bit SSL · 3D Secure · iyzico ile güvenli ödeme',
                    style: TextStyle(
                      fontSize: 12,
                      color: ctx.brandTextSecondary,
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 18),
            Row(
              children: [
                Expanded(
                  child: OutlinedButton(
                    onPressed: () => Navigator.pop(ctx),
                    style: OutlinedButton.styleFrom(
                      padding: const EdgeInsets.symmetric(vertical: 13),
                      side: BorderSide(color: ctx.brandBorder),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(12),
                      ),
                    ),
                    child: Text(
                      'İptal',
                      style: TextStyle(color: ctx.brandTextSecondary),
                    ),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  flex: 2,
                  child: FilledButton(
                    onPressed: () {
                      Navigator.pop(ctx);
                      if (package.packageId != null) {
                        context.read<PackagesCubit>().beginCheckout(package);
                      }
                    },
                    style: FilledButton.styleFrom(
                      backgroundColor: AppColors.primary,
                      padding: const EdgeInsets.symmetric(vertical: 13),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(12),
                      ),
                    ),
                    child: const Text(
                      'Öde ve Aktifleştir',
                      style: TextStyle(fontWeight: FontWeight.w700),
                    ),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _SecurityFooter extends StatelessWidget {
  const _SecurityFooter();

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: context.brandSurface,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: context.brandBorder),
      ),
      child: Row(
        children: [
          Icon(Icons.shield_outlined,
              size: 18, color: context.brandTextSecondary),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              'Ödemeler iyzico altyapısı ile alınır. Kart bilgileriniz '
              'hiçbir şekilde sunucularımızda saklanmaz.',
              style: TextStyle(
                fontSize: 12,
                color: context.brandTextSecondary,
                height: 1.45,
              ),
            ),
          ),
        ],
      ),
    );
  }
}
