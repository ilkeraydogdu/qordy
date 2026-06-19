import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:get_it/get_it.dart';

import '../../../config/theme.dart';
import '../../../core/theme/theme_cubit.dart';
import '../../../core/ui/primitives.dart';
import '../cubit/settings_cubit.dart';
import '../cubit/settings_state.dart';

class SettingsScreen extends StatelessWidget {
  const SettingsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return BlocProvider(
      create: (_) => GetIt.instance<SettingsCubit>()..loadSettings(),
      child: const _SettingsView(),
    );
  }
}

class _SettingsView extends StatelessWidget {
  const _SettingsView();

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      appBar: AppBar(
        title: Text(
          'Ayarlar',
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
      ),
      body: BlocConsumer<SettingsCubit, SettingsState>(
        listener: (context, state) {
          if (state is SettingsSaved) {
            ScaffoldMessenger.of(context).showSnackBar(
              SnackBar(
                content: const Text('Ayarlar kaydedildi'),
                backgroundColor: Colors.green,
                behavior: SnackBarBehavior.floating,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
              ),
            );
          } else if (state is SettingsError) {
            ScaffoldMessenger.of(context).showSnackBar(
              SnackBar(
                content: Text(state.message),
                backgroundColor: Colors.red,
                behavior: SnackBarBehavior.floating,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
              ),
            );
          }
        },
        buildWhen: (prev, curr) =>
            curr is SettingsLoading ||
            curr is SettingsLoaded ||
            curr is SettingsError,
        builder: (context, state) {
          if (state is SettingsLoading) {
            return ListView(
              padding: const EdgeInsets.all(AppSpacing.lg),
              children: [
                for (var i = 0; i < 5; i++) ...[
                  const QSkeleton(height: 18, radius: AppRadius.sm),
                  const SizedBox(height: AppSpacing.sm),
                  const QSkeleton(height: 120, radius: AppRadius.lg),
                  const SizedBox(height: AppSpacing.lg),
                ],
              ],
            );
          }
          if (state is SettingsError && state is! SettingsLoaded) {
            return QEmptyState(
              icon: Icons.error_outline_rounded,
              title: 'Ayarlar yüklenemedi',
              message: state.message,
              action: FilledButton.icon(
                onPressed: () =>
                    context.read<SettingsCubit>().loadSettings(),
                icon: const Icon(Icons.refresh_rounded, size: 18),
                label: const Text('Tekrar Dene'),
                style: FilledButton.styleFrom(
                  backgroundColor: AppColors.primary,
                  padding: const EdgeInsets.symmetric(
                      horizontal: 20, vertical: 12),
                ),
              ),
            );
          }
          if (state is! SettingsLoaded) return const SizedBox.shrink();

          return Stack(
            children: [
              SingleChildScrollView(
                padding: const EdgeInsets.fromLTRB(
                    AppSpacing.lg, AppSpacing.md, AppSpacing.lg, 120),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    _SectionHeader(
                      title: 'İşletme Bilgileri',
                      icon: Icons.storefront_rounded,
                      color: AppColors.primary,
                    ),
                    _SettingsCard(
                      children: [
                        _ReadOnlyField(
                          label: 'İşletme Adı',
                          value: state.businessName,
                          icon: Icons.store,
                          color: AppColors.primary,
                        ),
                        _SettingsDivider(),
                        _ReadOnlyField(
                          label: 'Adres',
                          value: state.address,
                          icon: Icons.location_on,
                          color: AppColors.accentPurple,
                        ),
                      ],
                    ),
                    const SizedBox(height: AppSpacing.lg),
                    _SectionHeader(
                      title: 'Çalışma Saatleri',
                      icon: Icons.schedule_rounded,
                      color: AppColors.accentOrange,
                    ),
                    _SettingsCard(
                      children: [
                        _ReadOnlyField(
                          label: 'Açılış',
                          value: state.workingHoursStart,
                          icon: Icons.access_time,
                          color: AppColors.successAlt,
                        ),
                        _SettingsDivider(),
                        _ReadOnlyField(
                          label: 'Kapanış',
                          value: state.workingHoursEnd,
                          icon: Icons.access_time_filled,
                          color: AppColors.errorBright,
                        ),
                      ],
                    ),
                    const SizedBox(height: AppSpacing.lg),
                    _SectionHeader(
                      title: 'Sipariş Ayarları',
                      icon: Icons.receipt_long_rounded,
                      color: AppColors.accentIndigo,
                    ),
                    _SettingsCard(
                      children: [
                        _ToggleRow(
                          label: 'Onay Gerekli',
                          description:
                              'Düzenleme ve iptal talepleri önce onaydan geçer.',
                          icon: Icons.approval_rounded,
                          color: AppColors.accentIndigo,
                          value: state.approvalRequired,
                          onChanged: (v) {
                            context.read<SettingsCubit>().updateField(
                                  (s) => s.copyWith(approvalRequired: v),
                                );
                          },
                        ),
                        _SettingsDivider(),
                        _DropdownRow(
                          label: 'Onay Yetkisi',
                          icon: Icons.shield_rounded,
                          color: AppColors.accentPurple,
                          value: state.approvalRole,
                          items: const ['manager', 'admin'],
                          labels: const ['Yönetici', 'Admin'],
                          onChanged: (v) {
                            if (v != null) {
                              context.read<SettingsCubit>().updateField(
                                    (s) => s.copyWith(approvalRole: v),
                                  );
                            }
                          },
                        ),
                      ],
                    ),
                    const SizedBox(height: AppSpacing.lg),
                    _SectionHeader(
                      title: 'WiFi',
                      icon: Icons.wifi_rounded,
                      color: AppColors.accentCyan,
                    ),
                    _SettingsCard(
                      children: [
                        _EditableRow(
                          label: 'Ağ Adı',
                          value: state.wifiName,
                          icon: Icons.wifi,
                          color: AppColors.accentCyan,
                          onChanged: (v) {
                            context.read<SettingsCubit>().updateField(
                                  (s) => s.copyWith(wifiName: v),
                                );
                          },
                        ),
                        _SettingsDivider(),
                        _EditableRow(
                          label: 'Şifre',
                          value: state.wifiPassword,
                          icon: Icons.lock_outline,
                          color: AppColors.warningBright,
                          obscure: true,
                          onChanged: (v) {
                            context.read<SettingsCubit>().updateField(
                                  (s) => s.copyWith(wifiPassword: v),
                                );
                          },
                        ),
                        _SettingsDivider(),
                        _ToggleRow(
                          label: 'Müşteriye Göster',
                          description:
                              'QR menüde misafirlere WiFi bilgisini gösterir.',
                          icon: Icons.qr_code_rounded,
                          color: AppColors.successAlt,
                          value: state.showWifiToCustomer,
                          onChanged: (v) {
                            context.read<SettingsCubit>().updateField(
                                  (s) => s.copyWith(showWifiToCustomer: v),
                                );
                          },
                        ),
                      ],
                    ),
                    const SizedBox(height: AppSpacing.lg),
                    _SectionHeader(
                      title: 'Görünüm',
                      icon: Icons.palette_rounded,
                      color: AppColors.accentRose,
                    ),
                    _SettingsCard(
                      children: const [
                        _ThemePicker(),
                      ],
                    ),
                    const SizedBox(height: AppSpacing.lg),
                    _SectionHeader(
                      title: 'Para Birimi',
                      icon: Icons.currency_lira,
                      color: AppColors.successAlt,
                    ),
                    _SettingsCard(
                      children: [
                        _ReadOnlyField(
                          label: 'Para Birimi',
                          value: state.currency,
                          icon: Icons.currency_lira,
                          color: AppColors.successAlt,
                        ),
                      ],
                    ),
                    const SizedBox(height: AppSpacing.md),
                  ],
                ),
              ),
              // Sticky bottom save bar — kullanıcı listede ne kadar
              // aşağı inerse insin "Kaydet" her zaman erişilebilir.
              Positioned(
                left: 0,
                right: 0,
                bottom: 0,
                child: _SaveBar(),
              ),
            ],
          );
        },
      ),
    );
  }
}

class _SectionHeader extends StatelessWidget {
  final String title;
  final IconData icon;
  final Color color;

  const _SectionHeader({
    required this.title,
    required this.icon,
    required this.color,
  });

  @override
  Widget build(BuildContext context) {
    final dark = context.isDark;
    return Padding(
      padding: const EdgeInsets.only(left: 2, bottom: 10, top: 6),
      child: Row(
        children: [
          Container(
            width: 22,
            height: 22,
            decoration: BoxDecoration(
              color: color.withValues(alpha: dark ? 0.22 : 0.12),
              borderRadius: BorderRadius.circular(6),
              border: Border.all(
                color: color.withValues(alpha: dark ? 0.38 : 0.22),
                width: 0.6,
              ),
            ),
            alignment: Alignment.center,
            child: Icon(icon, size: 13, color: color),
          ),
          const SizedBox(width: 8),
          Text(
            title.toUpperCase(),
            style: TextStyle(
              fontSize: 11.5,
              fontWeight: FontWeight.w800,
              color: context.brandTextSecondary,
              letterSpacing: 1.3,
            ),
          ),
        ],
      ),
    );
  }
}

class _SettingsCard extends StatelessWidget {
  final List<Widget> children;

  const _SettingsCard({required this.children});

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: Theme.of(context).cardColor,
        borderRadius: BorderRadius.circular(AppRadius.lg),
        border: Border.all(color: context.brandBorder, width: 0.6),
        boxShadow: AppShadows.card(context.isDark),
      ),
      clipBehavior: Clip.antiAlias,
      child: Column(children: children),
    );
  }
}

class _SettingsDivider extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Divider(
      height: 1,
      indent: 58,
      color: context.brandBorder.withValues(alpha: 0.6),
    );
  }
}

/// Küçük gradient ikon rozeti; ayarlar satırlarının solunda renk kodu
/// ipucu olarak duruyor (örn. WiFi için cyan, onay için indigo).
class _RowIconBadge extends StatelessWidget {
  final IconData icon;
  final Color color;

  const _RowIconBadge({required this.icon, required this.color});

  @override
  Widget build(BuildContext context) {
    final dark = context.isDark;
    return Container(
      width: 36,
      height: 36,
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [
            color.withValues(alpha: dark ? 0.38 : 0.20),
            color.withValues(alpha: dark ? 0.20 : 0.08),
          ],
        ),
        borderRadius: BorderRadius.circular(AppRadius.sm),
        border: Border.all(
          color: color.withValues(alpha: dark ? 0.42 : 0.24),
          width: 0.6,
        ),
      ),
      alignment: Alignment.center,
      child: Icon(icon, size: 18, color: color),
    );
  }
}

class _ReadOnlyField extends StatelessWidget {
  final String label;
  final String value;
  final IconData icon;
  final Color color;

  const _ReadOnlyField({
    required this.label,
    required this.value,
    required this.icon,
    this.color = AppColors.primary,
  });

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(
          horizontal: AppSpacing.md, vertical: AppSpacing.md),
      child: Row(
        children: [
          _RowIconBadge(icon: icon, color: color),
          const SizedBox(width: AppSpacing.md),
          Expanded(
            child: Text(
              label,
              style: TextStyle(
                fontSize: 13.5,
                fontWeight: FontWeight.w500,
                color: context.brandTextSecondary,
              ),
            ),
          ),
          const SizedBox(width: 8),
          Flexible(
            child: Text(
              value.isEmpty ? '-' : value,
              textAlign: TextAlign.end,
              style: TextStyle(
                fontSize: 13.5,
                fontWeight: FontWeight.w700,
                color: context.brandTextPrimary,
                letterSpacing: -0.1,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _ToggleRow extends StatelessWidget {
  final String label;
  final String? description;
  final IconData? icon;
  final Color color;
  final bool value;
  final ValueChanged<bool> onChanged;

  const _ToggleRow({
    required this.label,
    required this.value,
    required this.onChanged,
    this.description,
    this.icon,
    this.color = AppColors.primary,
  });

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: () => onChanged(!value),
      child: Padding(
        padding: const EdgeInsets.symmetric(
            horizontal: AppSpacing.md, vertical: 10),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.center,
          children: [
            if (icon != null) ...[
              _RowIconBadge(icon: icon!, color: color),
              const SizedBox(width: AppSpacing.md),
            ],
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    label,
                    style: TextStyle(
                      fontSize: 13.5,
                      fontWeight: FontWeight.w600,
                      color: context.brandTextPrimary,
                      letterSpacing: -0.1,
                    ),
                  ),
                  if (description != null) ...[
                    const SizedBox(height: 2),
                    Text(
                      description!,
                      style: TextStyle(
                        fontSize: 11.5,
                        color: context.brandTextHint,
                        height: 1.35,
                      ),
                    ),
                  ],
                ],
              ),
            ),
            const SizedBox(width: 8),
            Switch.adaptive(
              value: value,
              onChanged: onChanged,
              activeThumbColor: color,
            ),
          ],
        ),
      ),
    );
  }
}

class _DropdownRow extends StatelessWidget {
  final String label;
  final IconData? icon;
  final Color color;
  final String value;
  final List<String> items;
  final List<String> labels;
  final ValueChanged<String?> onChanged;

  const _DropdownRow({
    required this.label,
    required this.value,
    required this.items,
    required this.labels,
    required this.onChanged,
    this.icon,
    this.color = AppColors.primary,
  });

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(
          horizontal: AppSpacing.md, vertical: 8),
      child: Row(
        children: [
          if (icon != null) ...[
            _RowIconBadge(icon: icon!, color: color),
            const SizedBox(width: AppSpacing.md),
          ],
          Expanded(
            child: Text(
              label,
              style: TextStyle(
                fontSize: 13.5,
                fontWeight: FontWeight.w600,
                color: context.brandTextPrimary,
                letterSpacing: -0.1,
              ),
            ),
          ),
          DropdownButtonHideUnderline(
            child: DropdownButton<String>(
              value: items.contains(value) ? value : items.first,
              borderRadius: BorderRadius.circular(AppRadius.md),
              items: List.generate(
                items.length,
                (i) => DropdownMenuItem(
                  value: items[i],
                  child: Text(
                    labels[i],
                    style: TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.w600,
                      color: context.brandTextPrimary,
                    ),
                  ),
                ),
              ),
              onChanged: onChanged,
            ),
          ),
        ],
      ),
    );
  }
}

class _EditableRow extends StatefulWidget {
  final String label;
  final String value;
  final IconData icon;
  final Color color;
  final bool obscure;
  final ValueChanged<String> onChanged;

  const _EditableRow({
    required this.label,
    required this.value,
    required this.icon,
    this.obscure = false,
    this.color = AppColors.primary,
    required this.onChanged,
  });

  @override
  State<_EditableRow> createState() => _EditableRowState();
}

class _EditableRowState extends State<_EditableRow> {
  late final TextEditingController _controller;

  @override
  void initState() {
    super.initState();
    _controller = TextEditingController(text: widget.value);
  }

  @override
  void didUpdateWidget(covariant _EditableRow oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (widget.value != oldWidget.value &&
        widget.value != _controller.text) {
      _controller.text = widget.value;
    }
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(
          horizontal: AppSpacing.md, vertical: 6),
      child: Row(
        children: [
          _RowIconBadge(icon: widget.icon, color: widget.color),
          const SizedBox(width: AppSpacing.md),
          Expanded(
            child: TextField(
              controller: _controller,
              obscureText: widget.obscure,
              style: TextStyle(
                fontSize: 14,
                fontWeight: FontWeight.w600,
                color: context.brandTextPrimary,
              ),
              decoration: InputDecoration(
                labelText: widget.label,
                labelStyle: TextStyle(
                  fontSize: 12.5,
                  color: context.brandTextSecondary,
                  fontWeight: FontWeight.w500,
                ),
                border: InputBorder.none,
                isDense: true,
                contentPadding: EdgeInsets.zero,
              ),
              onChanged: widget.onChanged,
            ),
          ),
        ],
      ),
    );
  }
}

/// Sticky alt bar — sayfanın her noktasından "Kaydet" butonuna
/// erişilebilsin diye. MainShell navigation bar'ın üstünde yüzer.
class _SaveBar extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    final dark = context.isDark;
    return BlocBuilder<SettingsCubit, SettingsState>(
      buildWhen: (prev, curr) =>
          curr is SettingsSaving || curr is SettingsLoaded,
      builder: (context, state) {
        final isSaving = state is SettingsSaving;
        return Container(
          padding: EdgeInsets.fromLTRB(
            AppSpacing.lg,
            AppSpacing.sm,
            AppSpacing.lg,
            MediaQuery.of(context).padding.bottom + AppSpacing.sm,
          ),
          decoration: BoxDecoration(
            // Alttan yukarı doğru scrim gradient — ayarlar kartlarının
            // bar'ın altından "kaybolurken" yumuşak bir solma efekti almasını
            // sağlar, sert bir kesme hissi vermez.
            gradient: LinearGradient(
              begin: Alignment.topCenter,
              end: Alignment.bottomCenter,
              colors: [
                Theme.of(context).scaffoldBackgroundColor.withValues(alpha: 0),
                Theme.of(context)
                    .scaffoldBackgroundColor
                    .withValues(alpha: dark ? 0.9 : 0.92),
                Theme.of(context).scaffoldBackgroundColor,
              ],
              stops: const [0.0, 0.35, 1.0],
            ),
          ),
          child: SizedBox(
            width: double.infinity,
            height: 52,
            child: FilledButton.icon(
              onPressed: isSaving
                  ? null
                  : () => context.read<SettingsCubit>().saveSettings(),
              style: FilledButton.styleFrom(
                backgroundColor: AppColors.primary,
                foregroundColor: Colors.white,
                disabledBackgroundColor:
                    AppColors.primary.withValues(alpha: 0.5),
                disabledForegroundColor: Colors.white70,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(AppRadius.lg),
                ),
                elevation: 0,
                shadowColor: AppColors.primary.withValues(alpha: 0.35),
              ),
              icon: isSaving
                  ? const SizedBox(
                      width: 18,
                      height: 18,
                      child: CircularProgressIndicator(
                        strokeWidth: 2,
                        color: Colors.white,
                      ),
                    )
                  : const Icon(Icons.check_rounded, size: 20),
              label: Text(
                isSaving ? 'Kaydediliyor...' : 'Ayarları Kaydet',
                style: const TextStyle(
                  fontSize: 15,
                  fontWeight: FontWeight.w800,
                  letterSpacing: 0.2,
                ),
              ),
            ),
          ),
        );
      },
    );
  }
}

/// Three-way segmented control that lets the user pin the app to
/// Light, Dark or "Follow System" theme. Choice is persisted in
/// SharedPreferences by [ThemeCubit] so the selection survives relaunches.
class _ThemePicker extends StatelessWidget {
  const _ThemePicker();

  @override
  Widget build(BuildContext context) {
    return BlocBuilder<ThemeCubit, AppThemeMode>(
      builder: (context, mode) {
        final isDark = Theme.of(context).brightness == Brightness.dark;
        final rowColor =
            isDark ? AppColors.darkTextPrimary : AppColors.textPrimary;
        final subColor =
            isDark ? AppColors.darkTextSecondary : AppColors.textSecondary;

        return Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Icon(Icons.palette_outlined,
                      size: 18, color: AppColors.primary),
                  const SizedBox(width: 10),
                  Text(
                    'Tema',
                    style: TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.w600,
                      color: rowColor,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 6),
              Text(
                'Uygulamayı aydınlık, karanlık ya da sistem temasına sabitleyin.',
                style: TextStyle(
                  fontSize: 12,
                  height: 1.4,
                  color: subColor,
                ),
              ),
              const SizedBox(height: 14),
              Container(
                padding: const EdgeInsets.all(4),
                decoration: BoxDecoration(
                  color: isDark
                      ? AppColors.darkSurfaceMuted
                      : AppColors.surfaceMuted,
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Row(
                  children: [
                    _ThemeSegment(
                      icon: Icons.brightness_auto_outlined,
                      label: 'Sistem',
                      selected: mode == AppThemeMode.system,
                      onTap: () => context
                          .read<ThemeCubit>()
                          .setMode(AppThemeMode.system),
                    ),
                    _ThemeSegment(
                      icon: Icons.light_mode_outlined,
                      label: 'Aydınlık',
                      selected: mode == AppThemeMode.light,
                      onTap: () => context
                          .read<ThemeCubit>()
                          .setMode(AppThemeMode.light),
                    ),
                    _ThemeSegment(
                      icon: Icons.dark_mode_outlined,
                      label: 'Karanlık',
                      selected: mode == AppThemeMode.dark,
                      onTap: () => context
                          .read<ThemeCubit>()
                          .setMode(AppThemeMode.dark),
                    ),
                  ],
                ),
              ),
            ],
          ),
        );
      },
    );
  }
}

class _ThemeSegment extends StatelessWidget {
  final IconData icon;
  final String label;
  final bool selected;
  final VoidCallback onTap;

  const _ThemeSegment({
    required this.icon,
    required this.label,
    required this.selected,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final bg = selected
        ? (isDark ? AppColors.darkCard : Colors.white)
        : Colors.transparent;
    final fg = selected
        ? (isDark ? AppColors.primaryDarkMode : AppColors.primary)
        : (isDark ? AppColors.darkTextSecondary : AppColors.textSecondary);
    return Expanded(
      child: InkWell(
        borderRadius: BorderRadius.circular(9),
        onTap: onTap,
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 160),
          padding: const EdgeInsets.symmetric(vertical: 9),
          decoration: BoxDecoration(
            color: bg,
            borderRadius: BorderRadius.circular(9),
            boxShadow: selected
                ? [
                    BoxShadow(
                      color: Colors.black.withValues(alpha: 0.06),
                      blurRadius: 6,
                      offset: const Offset(0, 2),
                    ),
                  ]
                : null,
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(icon, size: 18, color: fg),
              const SizedBox(height: 3),
              Text(
                label,
                style: TextStyle(
                  fontSize: 11.5,
                  fontWeight: selected ? FontWeight.w700 : FontWeight.w500,
                  color: fg,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
