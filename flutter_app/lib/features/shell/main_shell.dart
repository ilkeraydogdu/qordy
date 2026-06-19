import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../../config/theme.dart';
import '../../core/di/injection.dart';
import '../subscription/cubit/subscription_cubit.dart';
import '../subscription/cubit/subscription_state.dart';
import '../subscription/widgets/custom_offer_gate.dart';
import '../subscription/widgets/readonly_banner.dart';
import '../subscription/widgets/subscription_upsell_sheet.dart';
import '../subscription/widgets/trial_countdown_card.dart';

/// Bottom navigation shell used for manager / owner / admin roles.
///
/// Operational roles (cashier/kitchen/preparation/waiter) are redirected
/// by the router directly to their role home and therefore never wrap
/// this shell — see `RoleHome` + `AppRouter.redirect`.
class MainShell extends StatefulWidget {
  final StatefulNavigationShell navigationShell;

  const MainShell({super.key, required this.navigationShell});

  @override
  State<MainShell> createState() => _MainShellState();
}

class _MainShellState extends State<MainShell> {
  static const _lastReminderKey = 'qordy_last_upsell_reminder';
  Timer? _reminderTimer;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final cubit = getIt<SubscriptionCubit>();
      cubit.refresh(silent: true);
      cubit.startPolling();
      _reminderTimer = Timer.periodic(
        const Duration(minutes: 30),
        (_) => _maybeShowDailyReminder(),
      );
      // İlk açılışta da değerlendir (biraz gecikme ile)
      Timer(const Duration(seconds: 5), _maybeShowDailyReminder);
    });
  }

  @override
  void dispose() {
    _reminderTimer?.cancel();
    super.dispose();
  }

  Future<void> _maybeShowDailyReminder() async {
    if (!mounted) return;
    final cubit = getIt<SubscriptionCubit>();
    if (!cubit.shouldShowDailyUpsell()) return;

    final prefs = await SharedPreferences.getInstance();
    final lastMs = prefs.getInt(_lastReminderKey) ?? 0;
    final last = DateTime.fromMillisecondsSinceEpoch(lastMs);
    if (DateTime.now().difference(last) < const Duration(hours: 20)) return;

    await prefs.setInt(
      _lastReminderKey,
      DateTime.now().millisecondsSinceEpoch,
    );
    cubit.markUpsellReminderShown();

    if (!mounted) return;
    final state = cubit.state;
    if (!state.isTrial && !state.isGrace) return;

    final messenger = ScaffoldMessenger.of(context);
    messenger.showSnackBar(
      SnackBar(
        behavior: SnackBarBehavior.floating,
        backgroundColor: AppColors.primaryDark,
        duration: const Duration(seconds: 6),
        content: Text(
          state.isGrace
              ? 'Deneme bitti — ${state.graceDaysLeft} gün salt-okunur'
              : 'Denemenize ${state.daysLeft} gün kaldı',
        ),
        action: SnackBarAction(
          label: 'Satın Al',
          textColor: Colors.white,
          onPressed: () => SubscriptionUpsellSheet.show(context),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final colorScheme = Theme.of(context).colorScheme;
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final borderColor = isDark ? AppColors.darkBorder : AppColors.border;
    final iconColor = isDark ? AppColors.darkTextHint : AppColors.textHint;
    final primary = colorScheme.primary;
    final navigationShell = widget.navigationShell;

    return BlocProvider.value(
      value: getIt<SubscriptionCubit>(),
      child: BlocListener<SubscriptionCubit, SubscriptionState>(
        listenWhen: (prev, next) => prev.phase != next.phase,
        listener: (_, __) {},
        child: Scaffold(
          // Scaffold arka planını `brandScaffoldDecoration` ile kaplıyoruz:
          // dark mode'da marka mavisi üstten sızan yumuşak radial glow
          // kartların "yüzen" hissini artırıyor. Light mode'da ince bir
          // yukarıdan-aşağı gradient var.
          backgroundColor: Colors.transparent,
          body: Container(
            decoration: context.brandScaffoldDecoration,
            child: CustomOfferGate(
              child: Column(
                children: [
                  // Web pariteli: trial ve grace periyodlarında her sayfanın
                  // üstünde küçük bir sayaç banner'ı gösterir. Dashboard'da
                  // zaten var olan TrialCountdownCard ile aynı cubit'ten
                  // beslendiği için çift ekran yazılmıyor — aktif/none
                  // fazlarında widget kendini SizedBox.shrink()'e indiriyor.
                  const TrialCountdownCard(),
                  const ReadonlyBanner(),
                  Expanded(child: navigationShell),
                ],
              ),
            ),
          ),
          bottomNavigationBar: _PremiumBottomNav(
            currentIndex: navigationShell.currentIndex,
            onSelected: (index) {
              navigationShell.goBranch(
                index,
                initialLocation: index == navigationShell.currentIndex,
              );
            },
            isDark: isDark,
            borderColor: borderColor,
            iconColor: iconColor,
            primary: primary,
            surface: colorScheme.surface,
          ),
        ),
      ),
    );
  }
}

class _PremiumBottomNav extends StatelessWidget {
  const _PremiumBottomNav({
    required this.currentIndex,
    required this.onSelected,
    required this.isDark,
    required this.borderColor,
    required this.iconColor,
    required this.primary,
    required this.surface,
  });

  final int currentIndex;
  final ValueChanged<int> onSelected;
  final bool isDark;
  final Color borderColor;
  final Color iconColor;
  final Color primary;
  final Color surface;

  static const _items = <_NavItem>[
    _NavItem(Icons.space_dashboard_outlined, Icons.space_dashboard_rounded,
        'Ana Sayfa'),
    _NavItem(Icons.receipt_long_outlined, Icons.receipt_long_rounded,
        'Siparişler'),
    _NavItem(Icons.table_restaurant_outlined,
        Icons.table_restaurant_rounded, 'Masalar'),
    _NavItem(Icons.notifications_none_rounded,
        Icons.notifications_rounded, 'Bildirim'),
    _NavItem(Icons.person_outline_rounded, Icons.person_rounded, 'Profil'),
  ];

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: surface,
        border: Border(top: BorderSide(color: borderColor, width: 0.4)),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: isDark ? 0.28 : 0.06),
            blurRadius: 18,
            offset: const Offset(0, -4),
          ),
        ],
      ),
      child: SafeArea(
        top: false,
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 8),
          child: Row(
            children: [
              for (var i = 0; i < _items.length; i++)
                Expanded(
                  child: _NavButton(
                    item: _items[i],
                    selected: currentIndex == i,
                    primary: primary,
                    iconColor: iconColor,
                    onTap: () => onSelected(i),
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }
}

class _NavItem {
  final IconData outlined;
  final IconData filled;
  final String label;
  const _NavItem(this.outlined, this.filled, this.label);
}

class _NavButton extends StatelessWidget {
  const _NavButton({
    required this.item,
    required this.selected,
    required this.primary,
    required this.iconColor,
    required this.onTap,
  });

  final _NavItem item;
  final bool selected;
  final Color primary;
  final Color iconColor;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final color = selected ? primary : iconColor;
    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(AppRadius.md),
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 220),
          curve: Curves.easeOutCubic,
          padding: const EdgeInsets.symmetric(vertical: 8, horizontal: 4),
          decoration: BoxDecoration(
            color: selected
                ? primary.withValues(alpha: 0.1)
                : Colors.transparent,
            borderRadius: BorderRadius.circular(AppRadius.md),
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              AnimatedSwitcher(
                duration: const Duration(milliseconds: 200),
                transitionBuilder: (child, anim) =>
                    ScaleTransition(scale: anim, child: child),
                child: Icon(
                  selected ? item.filled : item.outlined,
                  key: ValueKey(selected),
                  color: color,
                  size: 23,
                ),
              ),
              const SizedBox(height: 4),
              AnimatedDefaultTextStyle(
                duration: const Duration(milliseconds: 200),
                style: TextStyle(
                  fontSize: 11,
                  fontWeight: selected ? FontWeight.w700 : FontWeight.w500,
                  color: color,
                  letterSpacing: 0.1,
                ),
                child: Text(item.label),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
