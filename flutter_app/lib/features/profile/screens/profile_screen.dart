import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:package_info_plus/package_info_plus.dart';
import 'package:qordy_app/config/theme.dart';
import 'package:qordy_app/core/ui/primitives.dart';
import 'package:qordy_app/core/widgets/confirm_dialog.dart';
import 'package:qordy_app/core/navigation/role_home.dart';
import 'package:qordy_app/features/auth/cubit/auth_cubit.dart';
import 'package:qordy_app/features/auth/cubit/auth_state.dart';

class ProfileScreen extends StatefulWidget {
  const ProfileScreen({super.key});

  @override
  State<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends State<ProfileScreen> {
  String _appVersion = '';

  @override
  void initState() {
    super.initState();
    _loadVersion();
  }

  Future<void> _loadVersion() async {
    try {
      final info = await PackageInfo.fromPlatform();
      if (mounted) {
        setState(() {
          _appVersion = 'v${info.version} (${info.buildNumber})';
        });
      }
    } catch (_) {
      if (mounted) {
        setState(() => _appVersion = 'v1.0.0');
      }
    }
  }

  Future<void> _logout() async {
    final confirmed = await ConfirmDialog.show(
      context,
      title: 'Çıkış Yap',
      message: 'Hesabınızdan çıkış yapmak istediğinizden emin misiniz?',
      confirmLabel: 'Çıkış Yap',
      cancelLabel: 'Vazgeç',
      isDestructive: true,
    );

    if (confirmed && mounted) {
      context.read<AuthCubit>().logout();
    }
  }

  Color _roleAccent(AppRole role) {
    switch (role) {
      case AppRole.kitchen:
        return AppColors.warning;
      case AppRole.waiter:
        return AppColors.info;
      case AppRole.cashier:
        return AppColors.success;
      case AppRole.preparation:
        return AppColors.warning;
      case AppRole.admin:
      case AppRole.manager:
      case AppRole.owner:
      case AppRole.unknown:
        return AppColors.primary;
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      appBar: AppBar(
        title: Text(
          'Profil',
          style: TextStyle(
            color: context.brandTextPrimary,
            fontWeight: FontWeight.w700,
            fontSize: 18,
          ),
        ),
        backgroundColor: Theme.of(context).scaffoldBackgroundColor,
        surfaceTintColor: Colors.transparent,
        elevation: 0,
        scrolledUnderElevation: 0.5,
        centerTitle: false,
      ),
      body: BlocBuilder<AuthCubit, AuthState>(
        builder: (context, state) {
          if (state is! Authenticated) {
            return const SizedBox.shrink();
          }

          final user = state.user;
          final business = state.business;

          final role = AppRole.fromUser(user);
          final isManager = RoleHome.showsFullShell(role);

          return ListView(
            padding: const EdgeInsets.fromLTRB(
              AppSpacing.lg,
              AppSpacing.sm,
              AppSpacing.lg,
              AppSpacing.xl,
            ),
            children: [
              _buildHeroCard(
                name: user.displayName,
                role: role,
                email: user.email,
                avatar: user.avatar,
                businessLogo: business.logoUrl,
                businessName: business.companyName,
              ),
              const SizedBox(height: AppSpacing.lg),
              _buildBusinessInfo(business.companyName, business.logoUrl),
              const SizedBox(height: AppSpacing.lg),
              const QSectionHeader(title: 'Hesap'),
              const SizedBox(height: AppSpacing.md),
              _buildMenuGroup(isManager: isManager),
              const SizedBox(height: AppSpacing.xl),
              _buildLogoutButton(),
              const SizedBox(height: AppSpacing.lg),
              _buildVersionInfo(),
            ],
          );
        },
      ),
    );
  }

  Widget _buildHeroCard({
    required String? name,
    required AppRole role,
    required String? email,
    required String? avatar,
    required String? businessLogo,
    required String? businessName,
  }) {
    final initials = _getInitials(name ?? '');
    final accent = _roleAccent(role);

    return Container(
      padding: const EdgeInsets.all(AppSpacing.lg),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(AppRadius.xl),
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [
            accent,
            Color.lerp(accent, Colors.black, 0.22)!,
          ],
        ),
        boxShadow: [
          BoxShadow(
            color: accent.withValues(alpha: 0.32),
            blurRadius: 24,
            offset: const Offset(0, 12),
          ),
        ],
      ),
      child: Stack(
        children: [
          Positioned(
            right: -20,
            top: -20,
            child: _glow(120, 0.14),
          ),
          Positioned(
            right: 60,
            bottom: -30,
            child: _glow(80, 0.08),
          ),
          Row(
            children: [
              _buildAvatarStack(
                initials: initials,
                avatar: avatar,
                businessLogo: businessLogo,
              ),
              const SizedBox(width: AppSpacing.md),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      name ?? '-',
                      style: const TextStyle(
                        fontSize: 19,
                        fontWeight: FontWeight.w700,
                        color: Colors.white,
                      ),
                    ),
                    if (businessName != null && businessName.isNotEmpty) ...[
                      const SizedBox(height: 2),
                      Text(
                        businessName,
                        style: TextStyle(
                          fontSize: 12.5,
                          color: Colors.white.withValues(alpha: 0.85),
                        ),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ],
                    const SizedBox(height: 6),
                    Container(
                      padding: const EdgeInsets.symmetric(
                          horizontal: 10, vertical: 4),
                      decoration: BoxDecoration(
                        color: Colors.white.withValues(alpha: 0.22),
                        borderRadius: BorderRadius.circular(AppRadius.pill),
                        border: Border.all(
                          color: Colors.white.withValues(alpha: 0.35),
                          width: 0.6,
                        ),
                      ),
                      child: Text(
                        role.label,
                        style: const TextStyle(
                          fontSize: 11.5,
                          fontWeight: FontWeight.w700,
                          color: Colors.white,
                          letterSpacing: 0.3,
                        ),
                      ),
                    ),
                    if (email != null && email.isNotEmpty) ...[
                      const SizedBox(height: 8),
                      Text(
                        email,
                        style: TextStyle(
                          fontSize: 12.5,
                          color: Colors.white.withValues(alpha: 0.88),
                        ),
                      ),
                    ],
                  ],
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildAvatarStack({
    required String initials,
    required String? avatar,
    required String? businessLogo,
  }) {
    Widget inner;
    if (avatar != null && avatar.isNotEmpty) {
      inner = ClipOval(
        child: CachedNetworkImage(
          imageUrl: avatar,
          fit: BoxFit.cover,
          width: 68,
          height: 68,
          fadeInDuration: const Duration(milliseconds: 180),
          errorWidget: (_, __, ___) => _initialsOnBrand(initials),
        ),
      );
    } else {
      inner = _initialsOnBrand(initials);
    }

    final avatarCircle = Container(
      width: 68,
      height: 68,
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.2),
        shape: BoxShape.circle,
        border: Border.all(
          color: Colors.white.withValues(alpha: 0.35),
          width: 1,
        ),
      ),
      child: inner,
    );

    if (businessLogo == null || businessLogo.isEmpty) return avatarCircle;

    return SizedBox(
      width: 76,
      height: 76,
      child: Stack(
        clipBehavior: Clip.none,
        children: [
          Positioned(left: 0, top: 0, child: avatarCircle),
          Positioned(
            right: -2,
            bottom: -2,
            child: Container(
              width: 28,
              height: 28,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: Colors.white,
                border: Border.all(color: Colors.white, width: 2),
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withValues(alpha: 0.18),
                    blurRadius: 4,
                    offset: const Offset(0, 2),
                  ),
                ],
              ),
              child: ClipOval(
                child: CachedNetworkImage(
                  imageUrl: businessLogo,
                  fit: BoxFit.cover,
                  errorWidget: (_, __, ___) => const Icon(
                    Icons.storefront_rounded,
                    size: 14,
                    color: AppColors.primary,
                  ),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _initialsOnBrand(String initials) => Center(
        child: Text(
          initials,
          style: const TextStyle(
            fontSize: 24,
            fontWeight: FontWeight.w800,
            color: Colors.white,
          ),
        ),
      );

  Widget _glow(double size, double opacity) {
    return IgnorePointer(
      child: Container(
        width: size,
        height: size,
        decoration: BoxDecoration(
          shape: BoxShape.circle,
          gradient: RadialGradient(
            colors: [
              Colors.white.withValues(alpha: opacity),
              Colors.white.withValues(alpha: 0.0),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildBusinessInfo(String? companyName, String? logoUrl) {
    return QCard(
      padding: const EdgeInsets.all(AppSpacing.md),
      child: Row(
        children: [
          Container(
            width: 44,
            height: 44,
            decoration: BoxDecoration(
              color: AppColors.successAlt.withValues(alpha: 0.1),
              borderRadius: BorderRadius.circular(AppRadius.md),
            ),
            clipBehavior: Clip.antiAlias,
            child: logoUrl != null && logoUrl.isNotEmpty
                ? CachedNetworkImage(
                    imageUrl: logoUrl,
                    fit: BoxFit.cover,
                    errorWidget: (_, __, ___) => const Icon(
                      Icons.store_rounded,
                      color: AppColors.successAlt,
                      size: 22,
                    ),
                  )
                : const Icon(
                    Icons.store_rounded,
                    color: AppColors.successAlt,
                    size: 22,
                  ),
          ),
          const SizedBox(width: AppSpacing.md),
          Expanded(
            child: Text(
              companyName ?? '-',
              style: TextStyle(
                fontSize: 15,
                fontWeight: FontWeight.w700,
                color: context.brandTextPrimary,
              ),
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
            ),
          ),
          const Icon(
            Icons.verified_rounded,
            color: AppColors.successAlt,
            size: 20,
          ),
        ],
      ),
    );
  }

  Widget _buildMenuGroup({required bool isManager}) {
    final items = <_MenuItemData>[
      if (isManager)
        _MenuItemData(
          icon: Icons.card_membership_rounded,
          label: 'Abonelik & Paketler',
          subtitle: 'Plan ve fatura yönetimi',
          accent: AppColors.primary,
          onTap: () => context.push('/packages'),
        ),
      if (isManager)
        _MenuItemData(
          icon: Icons.receipt_long_rounded,
          label: 'Satın Alma Geçmişi',
          subtitle: 'Abonelik ve ödeme kayıtları',
          accent: AppColors.success,
          onTap: () => context.push('/purchase-history'),
        ),
      if (isManager)
        _MenuItemData(
          icon: Icons.settings_rounded,
          label: 'İşletme Ayarları',
          subtitle: 'Vergi, ödeme, masalar',
          accent: AppColors.info,
          onTap: () => context.push('/settings'),
        ),
      if (isManager)
        _MenuItemData(
          icon: Icons.tune_rounded,
          label: 'Gelişmiş Yönetim',
          subtitle:
              'Yazıcılar, sıra, fiş şablonları, finans, roller ve fazlası',
          accent: AppColors.accentPurple,
          onTap: () => context.push('/admin'),
        ),
      if (isManager)
      _MenuItemData(
        icon: Icons.notifications_rounded,
        label: 'Bildirimler',
        subtitle: 'Tüm bildirim geçmişini gör',
        accent: AppColors.warning,
        onTap: () => context.push('/notifications'),
      ),
      _MenuItemData(
        icon: Icons.tune_rounded,
        label: 'Bildirim Ayarları',
        subtitle: 'Ses, titreşim ve kategoriler',
        accent: AppColors.accentPurple,
        onTap: () => context.push('/notification-settings'),
      ),
      _MenuItemData(
        icon: Icons.lock_rounded,
        label: 'Güvenlik',
        subtitle: 'PIN ve 2 adımlı doğrulama',
        accent: AppColors.primary,
        onTap: () => context.push('/security'),
      ),
      _MenuItemData(
        icon: Icons.info_rounded,
        label: 'Hakkında',
        subtitle: 'QORDY uygulama bilgileri',
        accent: AppColors.textSecondary,
        onTap: () => _showAboutDialog(context),
      ),
    ];

    return Container(
      decoration: BoxDecoration(
        color: Theme.of(context).cardColor,
        borderRadius: BorderRadius.circular(AppRadius.lg),
        border: Border.all(color: context.brandBorder, width: 0.6),
        boxShadow: AppShadows.card(context.isDark),
      ),
      clipBehavior: Clip.antiAlias,
      child: Column(
        children: [
          for (var i = 0; i < items.length; i++) ...[
            _buildMenuItem(items[i]),
            if (i < items.length - 1)
              Padding(
                padding: const EdgeInsets.only(left: 70),
                child: Divider(
                  height: 1,
                  color: context.brandBorder.withValues(alpha: 0.6),
                ),
              ),
          ],
        ],
      ),
    );
  }

  Widget _buildMenuItem(_MenuItemData item) {
    // Material+InkWell çifti Container üstünde opaque kalmayınca
    // ripple ekranda görünür oluyor; önceki sürüm karttaki
    // clipBehavior nedeniyle düz tıklama hissi veriyordu.
    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: item.onTap,
        splashColor: item.accent.withValues(alpha: 0.10),
        highlightColor: item.accent.withValues(alpha: 0.06),
        child: Padding(
          padding: const EdgeInsets.symmetric(
            horizontal: AppSpacing.md,
            vertical: AppSpacing.md,
          ),
          child: Row(
            children: [
              Container(
                width: 40,
                height: 40,
                decoration: BoxDecoration(
                  gradient: LinearGradient(
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                    colors: [
                      item.accent.withValues(alpha: 0.18),
                      item.accent.withValues(alpha: 0.08),
                    ],
                  ),
                  borderRadius: BorderRadius.circular(AppRadius.md),
                  border: Border.all(
                    color: item.accent.withValues(alpha: 0.18),
                    width: 0.6,
                  ),
                ),
                child: Icon(item.icon, color: item.accent, size: 20),
              ),
              const SizedBox(width: AppSpacing.md),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      item.label,
                      style: TextStyle(
                        fontSize: 14.5,
                        fontWeight: FontWeight.w600,
                        color: context.brandTextPrimary,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      item.subtitle,
                      style: TextStyle(
                        fontSize: 12,
                        color: context.brandTextSecondary,
                      ),
                    ),
                  ],
                ),
              ),
              Icon(
                Icons.chevron_right_rounded,
                color: context.brandTextHint,
                size: 22,
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildLogoutButton() {
    return SizedBox(
      width: double.infinity,
      child: OutlinedButton.icon(
        onPressed: _logout,
        icon: const Icon(Icons.logout_rounded, size: 20),
        label: const Text(
          'Çıkış Yap',
          style: TextStyle(fontWeight: FontWeight.w700, fontSize: 15),
        ),
        style: OutlinedButton.styleFrom(
          foregroundColor: AppColors.errorBright,
          side: const BorderSide(color: AppColors.errorBright, width: 1.2),
          padding: const EdgeInsets.symmetric(vertical: 14),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(AppRadius.md),
          ),
        ),
      ),
    );
  }

  Widget _buildVersionInfo() {
    return Center(
      child: Text(
        _appVersion.isNotEmpty ? 'QORDY $_appVersion' : '',
        style: TextStyle(
          fontSize: 11.5,
          color: context.brandTextHint,
          letterSpacing: 0.2,
        ),
      ),
    );
  }

  String _getInitials(String name) {
    if (name.isEmpty) return '?';
    final parts = name.trim().split(' ');
    if (parts.length >= 2) {
      return '${parts.first[0]}${parts.last[0]}'.toUpperCase();
    }
    return parts.first[0].toUpperCase();
  }

  void _showAboutDialog(BuildContext context) {
    final year = DateTime.now().year;
    final version = _appVersion.isNotEmpty ? _appVersion : '1.0.0';
    showDialog<void>(
      context: context,
      builder: (ctx) {
        final isDark = Theme.of(ctx).brightness == Brightness.dark;
        return Dialog(
          backgroundColor: Theme.of(ctx).cardColor,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(AppRadius.lg),
          ),
          child: Padding(
            padding: const EdgeInsets.fromLTRB(20, 22, 20, 14),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Container(
                      width: 46,
                      height: 46,
                      decoration: BoxDecoration(
                        color: AppColors.primary.withValues(alpha: 0.12),
                        borderRadius: BorderRadius.circular(AppRadius.md),
                      ),
                      child: const Icon(
                        Icons.auto_awesome_rounded,
                        color: AppColors.primary,
                        size: 24,
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            'QORDY',
                            style: TextStyle(
                              fontSize: 18,
                              fontWeight: FontWeight.w800,
                              color: isDark
                                  ? AppColors.darkTextPrimary
                                  : AppColors.textPrimary,
                              letterSpacing: 0.5,
                            ),
                          ),
                          const SizedBox(height: 2),
                          Text(
                            version,
                            style: TextStyle(
                              fontSize: 12,
                              color: isDark
                                  ? AppColors.darkTextSecondary
                                  : AppColors.textSecondary,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 18),
                Text(
                  'İşletmeniz için dijital sipariş, menü ve yönetim platformu.',
                  style: TextStyle(
                    fontSize: 13,
                    height: 1.45,
                    color: isDark
                        ? AppColors.darkTextPrimary
                        : AppColors.textPrimary,
                  ),
                ),
                const SizedBox(height: 14),
                Text(
                  '© $year QORDY — Tüm hakları saklıdır.',
                  style: TextStyle(
                    fontSize: 12.5,
                    fontWeight: FontWeight.w600,
                    color: isDark
                        ? AppColors.darkTextPrimary
                        : AppColors.textPrimary,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  'QORDY, Pofuduk Dijital Medya ve Yazılım Limited Şirketi '
                  'tarafından geliştirilmiş ve lisanslanmıştır.',
                  style: TextStyle(
                    fontSize: 12,
                    height: 1.45,
                    color: isDark
                        ? AppColors.darkTextSecondary
                        : AppColors.textSecondary,
                  ),
                ),
                const SizedBox(height: 10),
                Text(
                  'Destek: destek@qordy.com',
                  style: TextStyle(
                    fontSize: 12.5,
                    color: isDark
                        ? AppColors.darkTextSecondary
                        : AppColors.textSecondary,
                  ),
                ),
                const SizedBox(height: 12),
                Align(
                  alignment: Alignment.centerRight,
                  child: TextButton(
                    onPressed: () => Navigator.of(ctx).pop(),
                    child: const Text(
                      'Kapat',
                      style: TextStyle(fontWeight: FontWeight.w700),
                    ),
                  ),
                ),
              ],
            ),
          ),
        );
      },
    );
  }
}

class _MenuItemData {
  final IconData icon;
  final String label;
  final String subtitle;
  final Color accent;
  final VoidCallback onTap;

  const _MenuItemData({
    required this.icon,
    required this.label,
    required this.subtitle,
    required this.accent,
    required this.onTap,
  });
}
