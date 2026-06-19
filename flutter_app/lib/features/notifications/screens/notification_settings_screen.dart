import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../../config/theme.dart';
import '../../../core/push/notification_prefs.dart';

/// User-facing settings for the local notification behaviour. Every
/// change is immediate and persisted through [NotificationPrefs]; the
/// effect is visible on the very next notification the device receives.
class NotificationSettingsScreen extends StatefulWidget {
  const NotificationSettingsScreen({super.key});

  @override
  State<NotificationSettingsScreen> createState() =>
      _NotificationSettingsScreenState();
}

class _NotificationSettingsScreenState
    extends State<NotificationSettingsScreen> {
  final _prefs = NotificationPrefs.instance;
  bool _ready = false;

  @override
  void initState() {
    super.initState();
    _prefs.ensureLoaded().then((_) {
      if (mounted) setState(() => _ready = true);
    });
    _prefs.addListener(_onPrefsChanged);
  }

  @override
  void dispose() {
    _prefs.removeListener(_onPrefsChanged);
    super.dispose();
  }

  void _onPrefsChanged() {
    if (mounted) setState(() {});
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      appBar: AppBar(
        title: Text(
          'Bildirim Ayarları',
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
        iconTheme: IconThemeData(color: context.brandTextPrimary),
      ),
      body: !_ready
          ? const Center(child: CircularProgressIndicator())
          : ListView(
              padding: const EdgeInsets.symmetric(
                  vertical: AppSpacing.md, horizontal: AppSpacing.lg),
              children: [
                _buildHeader(
                  'Genel',
                  'Cihazda nasıl bildirim alınacağını belirle.',
                ),
                _buildCard([
                  _buildToggle(
                    icon: Icons.notifications_active_outlined,
                    title: 'Bildirimler Açık',
                    subtitle:
                        'Kapattığında sistem bildirim çubuğuna bir şey düşmez.',
                    value: _prefs.pushEnabled,
                    onChanged: (v) {
                      HapticFeedback.lightImpact();
                      _prefs.setPushEnabled(v);
                    },
                  ),
                  _divider(),
                  _buildToggle(
                    icon: Icons.volume_up_outlined,
                    title: 'Ses',
                    subtitle: 'Yeni bildirimde ses çalsın.',
                    value: _prefs.soundEnabled,
                    enabled: _prefs.pushEnabled,
                    onChanged: (v) => _prefs.setSoundEnabled(v),
                  ),
                  _divider(),
                  _buildToggle(
                    icon: Icons.vibration_outlined,
                    title: 'Titreşim',
                    subtitle: 'Cihaz titreşerek haber versin.',
                    value: _prefs.vibrationEnabled,
                    enabled: _prefs.pushEnabled,
                    onChanged: (v) => _prefs.setVibrationEnabled(v),
                  ),
                  _divider(),
                  _buildToggle(
                    icon: Icons.campaign_outlined,
                    title: 'Uygulama İçi Banner',
                    subtitle: 'Uygulama açıkken üstten akan mini bildirim.',
                    value: _prefs.inAppBanner,
                    enabled: _prefs.pushEnabled,
                    onChanged: (v) => _prefs.setInAppBanner(v),
                  ),
                ]),
                const SizedBox(height: 20),
                _buildHeader(
                  'Kategoriler',
                  'Hangi tür olaylar için bildirim almak istediğini seç.',
                ),
                _buildCard([
                  for (int i = 0; i < NotificationPrefs.categories.length; i++)
                    ...[
                      _buildCategoryToggle(NotificationPrefs.categories[i]),
                      if (i < NotificationPrefs.categories.length - 1) _divider(),
                    ],
                ]),
                const SizedBox(height: 24),
                Text(
                  'Bu ayarlar cihaz üzerinde saklanır. Kapattığın bir kategori yalnızca bu cihazdaki bildirimleri susturur; sistem kayıtlarını etkilemez.',
                  style: TextStyle(
                    fontSize: 12,
                    color: context.brandTextHint,
                    height: 1.5,
                  ),
                ),
                const SizedBox(height: 24),
              ],
            ),
    );
  }

  Widget _buildHeader(String title, String subtitle) {
    return Padding(
      padding: const EdgeInsets.only(bottom: AppSpacing.sm),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            title.toUpperCase(),
            style: TextStyle(
              fontSize: 12,
              fontWeight: FontWeight.w800,
              color: context.brandTextHint,
              letterSpacing: 1.4,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            subtitle,
            style: TextStyle(
              fontSize: 12.5,
              color: context.brandTextSecondary,
              height: 1.4,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildCard(List<Widget> children) {
    return Container(
      decoration: BoxDecoration(
        color: Theme.of(context).cardColor,
        borderRadius: BorderRadius.circular(AppRadius.lg),
        border: Border.all(color: context.brandBorder, width: 0.6),
        boxShadow: AppShadows.card(context.isDark),
      ),
      child: Column(children: children),
    );
  }

  Widget _divider() =>
      Divider(height: 1, thickness: 0.5, color: context.brandBorder);

  Widget _buildToggle({
    required IconData icon,
    required String title,
    required String subtitle,
    required bool value,
    required ValueChanged<bool> onChanged,
    bool enabled = true,
  }) {
    return Opacity(
      opacity: enabled ? 1.0 : 0.5,
      child: Padding(
        padding: const EdgeInsets.symmetric(
            horizontal: AppSpacing.md, vertical: 10),
        child: Row(
          children: [
            Container(
              width: 36,
              height: 36,
              decoration: BoxDecoration(
                color: AppColors.primary.withValues(alpha: 0.1),
                borderRadius: BorderRadius.circular(10),
              ),
              child: Icon(icon, size: 18, color: AppColors.primary),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    style: TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.w600,
                      color: context.brandTextPrimary,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    subtitle,
                    style: TextStyle(
                      fontSize: 12,
                      color: context.brandTextSecondary,
                      height: 1.3,
                    ),
                  ),
                ],
              ),
            ),
            Switch.adaptive(
              value: value,
              onChanged: enabled ? onChanged : null,
              activeThumbColor: AppColors.primary,
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildCategoryToggle(NotificationCategory c) {
    final enabled = _prefs.isCategoryEnabled(c.key);
    return _buildToggle(
      icon: _iconFor(c.key),
      title: c.label,
      subtitle: c.description,
      value: enabled,
      enabled: _prefs.pushEnabled,
      onChanged: (v) {
        HapticFeedback.selectionClick();
        _prefs.setCategoryEnabled(c.key, v);
      },
    );
  }

  IconData _iconFor(String key) {
    switch (key) {
      case 'NEW_ORDER':
        return Icons.receipt_long_outlined;
      case 'CALL_WAITER':
        return Icons.room_service_outlined;
      case 'REQUEST_BILL':
        return Icons.request_quote_outlined;
      case 'ORDER_READY':
        return Icons.check_circle_outline;
      case 'KITCHEN_ISSUE':
        return Icons.soup_kitchen_outlined;
      case 'CANCEL_ORDER':
        return Icons.cancel_outlined;
      case 'PAYMENT_RECEIVED':
        return Icons.payments_outlined;
      case 'EDIT_APPROVAL':
        return Icons.rule_outlined;
      case 'SYSTEM':
        return Icons.info_outline;
      default:
        return Icons.notifications_none_rounded;
    }
  }
}
