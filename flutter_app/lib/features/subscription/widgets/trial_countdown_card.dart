import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:qordy_app/config/theme.dart';
import 'package:qordy_app/features/subscription/cubit/subscription_cubit.dart';
import 'package:qordy_app/features/subscription/cubit/subscription_state.dart';
import 'package:qordy_app/features/subscription/widgets/subscription_upsell_sheet.dart';
import 'package:qordy_app/models/package_model.dart';

/// Dashboard'ın üst kısmında trial ve grace periyotlarında görünür.
/// - Trial: "7 gün deneme — X gün kaldı" + Satın Al CTA
/// - Grace: "Deneme bitti, salt-okunur — X gün içinde satın al"
/// - Aktif: gizli.
class TrialCountdownCard extends StatelessWidget {
  const TrialCountdownCard({super.key});

  @override
  Widget build(BuildContext context) {
    return BlocBuilder<SubscriptionCubit, SubscriptionState>(
      buildWhen: (prev, next) =>
          prev.phase != next.phase ||
          prev.daysLeft != next.daysLeft ||
          prev.graceDaysLeft != next.graceDaysLeft ||
          prev.subscription != next.subscription,
      builder: (context, state) {
        if (state.subscription == null) {
          return const SizedBox.shrink();
        }
        final phase = state.phase;
        if (phase != SubscriptionPhase.trial &&
            phase != SubscriptionPhase.grace) {
          return const SizedBox.shrink();
        }

        final bool grace = phase == SubscriptionPhase.grace;
        final int days =
            grace ? state.graceDaysLeft : state.daysLeft;

        final Color bg = grace
            ? const Color(0xFFFFF7ED)
            : AppColors.primarySoft;
        final Color fg = grace
            ? const Color(0xFF9A3412)
            : AppColors.primaryDark;
        final Color borderColor = grace
            ? const Color(0xFFFED7AA)
            : AppColors.primary.withValues(alpha: 0.2);
        final IconData icon = grace
            ? Icons.warning_amber_rounded
            : Icons.timer_outlined;

        final String title = grace
            ? 'Deneme süreniz doldu'
            : days <= 0
                ? 'Deneme süreniz bugün sona eriyor'
                : 'Qordy denemenize $days gün kaldı';
        final String subtitle = grace
            ? 'Kesintisiz kullanım için $days gün içinde paket satın alın. Bu süreçte sistem salt-okunur moddadır.'
            : 'Paketinizi şimdi alın, veri kaybı olmadan tüm özelliklere devam edin.';

        return Padding(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
          child: Material(
            color: bg,
            borderRadius: BorderRadius.circular(16),
            child: InkWell(
              borderRadius: BorderRadius.circular(16),
              onTap: () => _openUpsell(context),
              child: Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  borderRadius: BorderRadius.circular(16),
                  border: Border.all(color: borderColor),
                ),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Container(
                      width: 44,
                      height: 44,
                      decoration: BoxDecoration(
                        color: fg.withValues(alpha: 0.12),
                        borderRadius: BorderRadius.circular(12),
                      ),
                      child: Icon(icon, color: fg, size: 22),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            title,
                            style: TextStyle(
                              fontSize: 15,
                              fontWeight: FontWeight.w700,
                              color: fg,
                            ),
                          ),
                          const SizedBox(height: 4),
                          Text(
                            subtitle,
                            style: TextStyle(
                              fontSize: 12.5,
                              color: fg.withValues(alpha: 0.85),
                              height: 1.35,
                            ),
                          ),
                          const SizedBox(height: 10),
                          SizedBox(
                            height: 34,
                            child: ElevatedButton.icon(
                              onPressed: () => _openUpsell(context),
                              icon: const Icon(Icons.shopping_bag_outlined, size: 16),
                              label: const Text('Şimdi Satın Al'),
                              style: ElevatedButton.styleFrom(
                                backgroundColor: AppColors.primary,
                                foregroundColor: Colors.white,
                                padding: const EdgeInsets.symmetric(horizontal: 16),
                                shape: RoundedRectangleBorder(
                                  borderRadius: BorderRadius.circular(10),
                                ),
                                textStyle: const TextStyle(
                                  fontSize: 13,
                                  fontWeight: FontWeight.w600,
                                ),
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        );
      },
    );
  }

  void _openUpsell(BuildContext context) {
    SubscriptionUpsellSheet.show(context);
  }
}
