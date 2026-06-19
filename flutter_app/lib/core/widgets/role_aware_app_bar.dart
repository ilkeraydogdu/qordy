import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';

import '../../config/theme.dart';
import '../../features/auth/cubit/auth_cubit.dart';
import 'confirm_dialog.dart';
import 'qordy_logo.dart';

/// AppBar used on every "role home" screen (POS, Kitchen, Preparation,
/// Waiter, full manager shell). Puts the Qordy logo on the left and an
/// always-visible "Çıkış" (logout) action on the right so operational
/// users never have to hunt through menus to sign out of a shared
/// terminal — a common complaint from cafés where one tablet rotates
/// between staff members during a shift.
class RoleAwareAppBar extends StatelessWidget implements PreferredSizeWidget {
  const RoleAwareAppBar({
    super.key,
    this.title,
    this.subtitle,
    this.showLogo = true,
    this.showLogout = true,
    this.actions = const [],
    this.leading,
    this.bottom,
    this.backgroundColor,
  });

  /// If null and [showLogo] is true, the brand wordmark is rendered.
  final String? title;
  final String? subtitle;
  final bool showLogo;
  final bool showLogout;
  final List<Widget> actions;
  final Widget? leading;
  final PreferredSizeWidget? bottom;
  final Color? backgroundColor;

  static const double _toolbarHeight = kToolbarHeight + 4;

  @override
  Size get preferredSize => Size.fromHeight(
        _toolbarHeight + (bottom?.preferredSize.height ?? 0),
      );

  @override
  Widget build(BuildContext context) {
    final colorScheme = Theme.of(context).colorScheme;
    final isDark = Theme.of(context).brightness == Brightness.dark;

    Widget? titleWidget;
    if (title != null) {
      titleWidget = Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(
            title!,
            style: Theme.of(context).textTheme.titleLarge?.copyWith(
                  fontWeight: FontWeight.w700,
                  letterSpacing: -0.2,
                ),
          ),
          if (subtitle != null && subtitle!.isNotEmpty) ...[
            const SizedBox(height: 2),
            Text(
              subtitle!,
              style: Theme.of(context).textTheme.bodySmall?.copyWith(
                    color: isDark
                        ? AppColors.darkTextSecondary
                        : AppColors.textSecondary,
                  ),
            ),
          ],
        ],
      );
    } else if (showLogo) {
      titleWidget = Padding(
        padding: const EdgeInsets.symmetric(vertical: 6),
        child: QordyLogo(height: 22, onDarkBackground: isDark),
      );
    }

    return AppBar(
      backgroundColor: backgroundColor ?? colorScheme.surface,
      surfaceTintColor: Colors.transparent,
      elevation: 0,
      scrolledUnderElevation: 0.5,
      shadowColor: Colors.black.withValues(alpha: 0.06),
      toolbarHeight: _toolbarHeight,
      centerTitle: false,
      titleSpacing: leading == null ? 16 : 0,
      leading: leading,
      automaticallyImplyLeading: leading == null ? false : true,
      title: titleWidget,
      actions: [
        ...actions,
        _StaffMenuButton(),
        if (showLogout)
          _LogoutButton()
        else
          const SizedBox.shrink(),
      ],
      bottom: bottom,
    );
  }
}

/// Pop-up menu that every staff role sees on their home screen to
/// reach the shared self-service routes (profile, notifications,
/// notification preferences, security). Managers get the same shortcut
/// since they don't use a bottom shell on their role-home screens.
class _StaffMenuButton extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    return PopupMenuButton<String>(
      tooltip: 'Menü',
      icon: Icon(
        Icons.more_vert_rounded,
        color: isDark ? AppColors.darkTextPrimary : AppColors.textPrimary,
      ),
      position: PopupMenuPosition.under,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(12),
      ),
      onSelected: (value) {
        switch (value) {
          case 'notifications':
            context.push('/notifications');
            break;
          case 'notification-settings':
            context.push('/notification-settings');
            break;
          case 'profile':
            context.push('/profile');
            break;
          case 'security':
            context.push('/security');
            break;
          
            
            break;
        }
      },
      // The old menu duplicated entries that the user can already
      // reach from the notifications screen's gear icon or the profile
      // page's sub-sections. We keep only the ones that are not
      // otherwise discoverable on the current page.
      itemBuilder: (context) => const [
        PopupMenuItem(
          value: 'notifications',
          child: Row(children: [
            Icon(Icons.notifications_outlined, size: 18),
            SizedBox(width: 10),
            Text('Bildirimler'),
          ]),
        ),
        PopupMenuItem(
          value: 'profile',
          child: Row(children: [
            Icon(Icons.person_outline_rounded, size: 18),
            SizedBox(width: 10),
            Text('Profilim'),
          ]),
        ),
      ],
    );
  }
}

class _LogoutButton extends StatelessWidget {
  Future<void> _confirmAndLogout(BuildContext context) async {
    final confirmed = await ConfirmDialog.show(
      context,
      title: 'Çıkış Yap',
      message: 'Hesabınızdan çıkış yapmak istediğinizden emin misiniz?',
      confirmLabel: 'Çıkış Yap',
      cancelLabel: 'Vazgeç',
      isDestructive: true,
    );
    if (confirmed && context.mounted) {
      await context.read<AuthCubit>().logout();
    }
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    return Padding(
      padding: const EdgeInsets.only(right: 8),
      child: TextButton.icon(
        onPressed: () => _confirmAndLogout(context),
        style: TextButton.styleFrom(
          foregroundColor: isDark ? AppColors.darkTextPrimary : AppColors.error,
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(10),
          ),
        ),
        icon: const Icon(Icons.logout_rounded, size: 18),
        label: const Text(
          'Çıkış',
          style: TextStyle(fontSize: 13.5, fontWeight: FontWeight.w600),
        ),
      ),
    );
  }
}
