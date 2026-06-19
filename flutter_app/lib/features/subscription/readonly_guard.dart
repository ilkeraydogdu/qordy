import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:qordy_app/config/theme.dart';
import 'package:qordy_app/core/di/injection.dart';
import 'package:qordy_app/features/subscription/cubit/subscription_cubit.dart';
import 'package:qordy_app/features/subscription/cubit/subscription_state.dart';
import 'package:qordy_app/features/subscription/widgets/subscription_upsell_sheet.dart';

/// Grace / suspended periyotlarında mutasyonları engelleyen yardımcı.
///
/// Kullanım:
/// ```dart
/// if (!ReadonlyGuard.ensureCanMutate(context)) return;
/// await cubit.createSomething();
/// ```
class ReadonlyGuard {
  ReadonlyGuard._();

  /// Mutasyon için uygunsa true; değilse SnackBar'ı gösterip false döner.
  static bool ensureCanMutate(BuildContext context) {
    final state = getIt<SubscriptionCubit>().state;
    if (state.canMutate) return true;

    final messenger = ScaffoldMessenger.maybeOf(context);
    messenger?.showSnackBar(
      SnackBar(
        behavior: SnackBarBehavior.floating,
        backgroundColor: state.isSuspended
            ? AppColors.error
            : AppColors.warning,
        content: Text(
          state.isSuspended
              ? 'İşletmeniz askıya alındı. Devam etmek için paket satın alın.'
              : 'Deneme süresi doldu — salt-okunur modda. Yeni değişiklikler için paket alın.',
        ),
        action: SnackBarAction(
          label: 'Satın Al',
          textColor: Colors.white,
          onPressed: () => SubscriptionUpsellSheet.show(context),
        ),
      ),
    );
    return false;
  }

  /// Sadece read-only durumu döner; UI tarafında butonları disable etmekte
  /// `!ReadonlyGuard.canMutate(context)` ile kontrol edilir.
  static bool canMutate(BuildContext context) {
    final cubit = context.read<SubscriptionCubit>();
    return cubit.state.canMutate;
  }
}

/// Disabled görünümü için yardımcı: cubit state'ine göre butonları disable eder.
/// Sadece `BlocBuilder` içinde kullanılmalı.
class ReadonlyAwareButton extends StatelessWidget {
  final Widget child;
  final VoidCallback onPressed;
  const ReadonlyAwareButton({
    super.key,
    required this.child,
    required this.onPressed,
  });

  @override
  Widget build(BuildContext context) {
    return BlocSelector<SubscriptionCubit, SubscriptionState, bool>(
      selector: (state) => state.canMutate,
      builder: (context, canMutate) {
        return AbsorbPointer(
          absorbing: !canMutate,
          child: Opacity(
            opacity: canMutate ? 1 : 0.5,
            child: GestureDetector(
              onTap: () {
                if (!ReadonlyGuard.ensureCanMutate(context)) return;
                onPressed();
              },
              child: child,
            ),
          ),
        );
      },
    );
  }
}
