import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:qordy_app/features/subscription/cubit/subscription_cubit.dart';
import 'package:qordy_app/features/subscription/cubit/subscription_state.dart';
import 'package:qordy_app/features/subscription/widgets/subscription_upsell_sheet.dart';

/// Shell üst çubuğu altındaki sabit salt-okunur uyarı bandı.
/// - Grace: "Deneme bitti — X gün salt okunur. Şimdi satın al"
/// - Suspended: "İşletmeniz askıya alındı. Paket satın alın."
class ReadonlyBanner extends StatelessWidget {
  const ReadonlyBanner({super.key});

  @override
  Widget build(BuildContext context) {
    return BlocBuilder<SubscriptionCubit, SubscriptionState>(
      buildWhen: (prev, next) =>
          prev.phase != next.phase ||
          prev.graceDaysLeft != next.graceDaysLeft ||
          prev.readOnly != next.readOnly,
      builder: (context, state) {
        final bool showGrace = state.isGrace;
        final bool showSuspended = state.isSuspended;
        if (!showGrace && !showSuspended) return const SizedBox.shrink();

        final Color bg = showSuspended
            ? const Color(0xFFFEE2E2)
            : const Color(0xFFFFF7ED);
        final Color fg = showSuspended
            ? const Color(0xFF991B1B)
            : const Color(0xFF9A3412);
        final String msg = showSuspended
            ? 'İşletmeniz ödeme sebebiyle askıya alındı. Devam etmek için paket satın alın.'
            : 'Deneme süresi doldu — ${state.graceDaysLeft} gün salt-okunur modda. Satın almak için dokunun.';

        return Material(
          color: bg,
          child: InkWell(
            onTap: () => SubscriptionUpsellSheet.show(context),
            child: Container(
              width: double.infinity,
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
              child: Row(
                children: [
                  Icon(
                    showSuspended ? Icons.block : Icons.lock_outline,
                    color: fg,
                    size: 18,
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Text(
                      msg,
                      style: TextStyle(
                        color: fg,
                        fontSize: 12.5,
                        fontWeight: FontWeight.w600,
                        height: 1.3,
                      ),
                    ),
                  ),
                  Icon(Icons.chevron_right, color: fg),
                ],
              ),
            ),
          ),
        );
      },
    );
  }
}
