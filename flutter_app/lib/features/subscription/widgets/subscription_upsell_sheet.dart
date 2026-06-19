import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:qordy_app/config/theme.dart';
import 'package:qordy_app/core/di/injection.dart';
import 'package:qordy_app/core/ui/primitives.dart';
import 'package:qordy_app/features/packages/cubit/packages_cubit.dart';
import 'package:qordy_app/features/packages/cubit/packages_state.dart';
import 'package:qordy_app/features/subscription/cubit/subscription_cubit.dart';
import 'package:qordy_app/models/package_model.dart';

/// Trial & grace periyotlarında gösterilen paket satın alma bottom sheet'i.
/// Paket kartlarını listeler, seçilen paket için iyzico checkout başlatır.
class SubscriptionUpsellSheet extends StatefulWidget {
  const SubscriptionUpsellSheet({super.key});

  static Future<void> show(BuildContext context) {
    final cubit = context.read<SubscriptionCubit>();
    cubit.markUpsellReminderShown();
    return showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => BlocProvider(
        create: (_) => getIt<PackagesCubit>()..loadPackages(),
        child: const SubscriptionUpsellSheet(),
      ),
    );
  }

  @override
  State<SubscriptionUpsellSheet> createState() =>
      _SubscriptionUpsellSheetState();
}

class _SubscriptionUpsellSheetState extends State<SubscriptionUpsellSheet> {
  bool _yearly = false;

  @override
  Widget build(BuildContext context) {
    final mq = MediaQuery.of(context);
    return Padding(
      padding: EdgeInsets.only(bottom: mq.viewInsets.bottom),
      child: DraggableScrollableSheet(
        initialChildSize: 0.85,
        minChildSize: 0.5,
        maxChildSize: 0.95,
        expand: false,
        builder: (context, scrollController) => Container(
          decoration: BoxDecoration(
            color: Theme.of(context).cardColor,
            borderRadius:
                const BorderRadius.vertical(top: Radius.circular(AppRadius.xl)),
            boxShadow: AppShadows.card(context.isDark),
          ),
          child: Column(
            children: [
              _handleBar(),
              _header(),
              _cycleToggle(),
              const SizedBox(height: AppSpacing.md),
              Expanded(
                child: BlocBuilder<PackagesCubit, PackagesState>(
                  builder: (context, state) {
                    if (state is PackagesLoading) {
                      return const Center(
                          child: CircularProgressIndicator(
                              color: AppColors.primary));
                    }
                    if (state is PackagesError) {
                      return QEmptyState(
                        icon: Icons.error_outline_rounded,
                        title: 'Paketler yüklenemedi',
                        message: state.message,
                      );
                    }
                    if (state is PackagesLoaded) {
                      if (state.packages.isEmpty) {
                        return const QEmptyState(
                          icon: Icons.inventory_2_outlined,
                          title: 'Paket bulunamadı',
                          message:
                              'Şu an satın alınabilir bir paket bulunmuyor.',
                        );
                      }
                      return ListView.separated(
                        controller: scrollController,
                        padding: const EdgeInsets.fromLTRB(
                            AppSpacing.lg,
                            AppSpacing.sm,
                            AppSpacing.lg,
                            AppSpacing.xl),
                        itemCount: state.packages.length,
                        separatorBuilder: (_, __) =>
                            const SizedBox(height: AppSpacing.md),
                        itemBuilder: (context, i) =>
                            _packageCard(state.packages[i]),
                      );
                    }
                    return const SizedBox.shrink();
                  },
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _handleBar() => Center(
        child: Container(
          margin: const EdgeInsets.only(top: 10, bottom: 8),
          width: 44,
          height: 4,
          decoration: BoxDecoration(
            color: context.brandBorder,
            borderRadius: BorderRadius.circular(4),
          ),
        ),
      );

  Widget _header() => Padding(
        padding: const EdgeInsets.symmetric(
            horizontal: AppSpacing.lg, vertical: AppSpacing.sm),
        child: Row(
          children: [
            Container(
              width: 44,
              height: 44,
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  colors: [
                    AppColors.primary,
                    AppColors.primary.withValues(alpha: 0.7),
                  ],
                ),
                borderRadius: BorderRadius.circular(AppRadius.md),
                boxShadow: [
                  BoxShadow(
                    color: AppColors.primary.withValues(alpha: 0.3),
                    blurRadius: 10,
                    offset: const Offset(0, 3),
                  ),
                ],
              ),
              child: const Icon(Icons.workspace_premium_rounded,
                  color: Colors.white, size: 22),
            ),
            const SizedBox(width: AppSpacing.md),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Qordy\'ye devam edin',
                    style: TextStyle(
                      fontSize: 17,
                      fontWeight: FontWeight.w800,
                      color: context.brandTextPrimary,
                      letterSpacing: -0.2,
                    ),
                  ),
                  const SizedBox(height: 3),
                  Text(
                    'En uygun paketi seçin — istediğiniz zaman iptal edin.',
                    style: TextStyle(
                      fontSize: 12.5,
                      color: context.brandTextSecondary,
                      height: 1.35,
                    ),
                  ),
                ],
              ),
            ),
            IconButton(
              icon: Icon(Icons.close_rounded,
                  color: context.brandTextSecondary),
              onPressed: () => Navigator.pop(context),
              visualDensity: VisualDensity.compact,
            ),
          ],
        ),
      );

  Widget _cycleToggle() => Padding(
        padding: const EdgeInsets.fromLTRB(
            AppSpacing.lg, AppSpacing.md, AppSpacing.lg, 0),
        child: Container(
          padding: const EdgeInsets.all(4),
          decoration: BoxDecoration(
            color: context.isDark
                ? AppColors.darkSurfaceMuted
                : AppColors.surfaceMuted,
            borderRadius: BorderRadius.circular(AppRadius.md),
          ),
          child: Row(
            children: [
              Expanded(child: _cycleSegment('Aylık', !_yearly, false)),
              Expanded(child: _cycleSegment('Yıllık · %20', _yearly, true)),
            ],
          ),
        ),
      );

  Widget _cycleSegment(String label, bool selected, bool targetYearly) {
    return GestureDetector(
      onTap: () => setState(() => _yearly = targetYearly),
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 220),
        curve: Curves.easeOut,
        padding: const EdgeInsets.symmetric(vertical: 10),
        decoration: BoxDecoration(
          color: selected
              ? Theme.of(context).cardColor
              : Colors.transparent,
          borderRadius: BorderRadius.circular(AppRadius.sm),
          boxShadow: selected ? AppShadows.card(context.isDark) : null,
        ),
        alignment: Alignment.center,
        child: Text(
          label,
          style: TextStyle(
            fontSize: 13,
            fontWeight: FontWeight.w700,
            color: selected
                ? AppColors.primary
                : context.brandTextSecondary,
          ),
        ),
      ),
    );
  }

  Widget _packageCard(SubscriptionPackage p) {
    final double price = _yearly
        ? (p.priceYearly ?? 0)
        : (p.priceMonthly ?? 0);
    final bool isPopular = p.isPopular == true;
    return Container(
      decoration: BoxDecoration(
        color: Theme.of(context).cardColor,
        borderRadius: BorderRadius.circular(AppRadius.lg),
        border: Border.all(
          color: isPopular
              ? AppColors.primary.withValues(alpha: 0.5)
              : context.brandBorder,
          width: isPopular ? 1.5 : 0.6,
        ),
        boxShadow: AppShadows.card(context.isDark),
      ),
      padding: const EdgeInsets.all(AppSpacing.md),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  p.name ?? 'Paket',
                  style: TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.w800,
                    color: context.brandTextPrimary,
                    letterSpacing: -0.2,
                  ),
                ),
              ),
              if (isPopular)
                Container(
                  padding: const EdgeInsets.symmetric(
                      horizontal: 10, vertical: 4),
                  decoration: BoxDecoration(
                    gradient: LinearGradient(
                      colors: [
                        AppColors.primary,
                        AppColors.primary.withValues(alpha: 0.75),
                      ],
                    ),
                    borderRadius:
                        BorderRadius.circular(AppRadius.pill),
                  ),
                  child: const Text(
                    'POPÜLER',
                    style: TextStyle(
                      color: Colors.white,
                      fontSize: 10.5,
                      fontWeight: FontWeight.w800,
                      letterSpacing: 0.6,
                    ),
                  ),
                ),
            ],
          ),
          if ((p.description ?? '').isNotEmpty) ...[
            const SizedBox(height: 6),
            Text(
              p.description!,
              style: TextStyle(
                fontSize: 12.5,
                color: context.brandTextSecondary,
                height: 1.4,
              ),
            ),
          ],
          const SizedBox(height: AppSpacing.md),
          Row(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              Text(
                '₺${price.toStringAsFixed(0)}',
                style: TextStyle(
                  fontSize: 28,
                  fontWeight: FontWeight.w800,
                  color: context.brandTextPrimary,
                  letterSpacing: -0.5,
                ),
              ),
              const SizedBox(width: 4),
              Padding(
                padding: const EdgeInsets.only(bottom: 5),
                child: Text(
                  _yearly ? '/yıl' : '/ay',
                  style: TextStyle(
                    color: context.brandTextHint,
                    fontSize: 13,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: AppSpacing.md),
          SizedBox(
            width: double.infinity,
            child: ElevatedButton.icon(
              onPressed: () => _buy(p),
              icon: const Icon(Icons.credit_card_rounded, size: 18),
              label: const Text(
                'Şimdi Satın Al',
                style: TextStyle(
                    fontWeight: FontWeight.w700, fontSize: 14),
              ),
              style: ElevatedButton.styleFrom(
                backgroundColor: AppColors.primary,
                foregroundColor: Colors.white,
                elevation: 0,
                padding: const EdgeInsets.symmetric(vertical: 13),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(AppRadius.md),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Future<void> _buy(SubscriptionPackage p) async {
    // Yeni mimaride iyzico checkout /packages ekranı üzerinden
    // PackagesCubit.beginCheckout ile başlatılıyor — böylece hem
    // başarı/başarısız sonucunu tek yerden yönetiyoruz hem de
    // AuthCubit.refreshSubscriptionStatus paywall'u otomatik kaldırıyor.
    // Bu sheet yalnızca hızlı bir upsell olduğu için kullanıcıyı
    // paketler ekranına götürüp orada satın alma akışına bırakıyoruz.
    if (p.packageId == null) return;
    Navigator.pop(context);
    if (!context.mounted) return;
    context.push('/packages');
  }
}
