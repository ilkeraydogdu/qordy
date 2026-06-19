import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// Local-only user preferences for how the device handles push
/// notifications. Backed by SharedPreferences so the toggles persist
/// across sessions. The values are consulted by [PushService] before
/// showing a local/system banner and by [NotificationsCubit] before
/// firing a buzzer for a newly arrived item.
///
/// We intentionally keep this client-side. The server sends every
/// notification it should; the device decides whether to surface it.
class NotificationPrefs extends ChangeNotifier {
  NotificationPrefs._();
  static final NotificationPrefs instance = NotificationPrefs._();

  static const _kPushEnabled = 'notif.push_enabled';
  static const _kSoundEnabled = 'notif.sound_enabled';
  static const _kVibrationEnabled = 'notif.vibration_enabled';
  static const _kInAppBanner = 'notif.inapp_banner';
  static const _kCategoryPrefix = 'notif.cat.';

  /// Known notification categories (match backend `type` enum).
  /// Order matters: drives the UI list.
  static const categories = <NotificationCategory>[
    NotificationCategory('NEW_ORDER', 'Yeni Sipariş', 'Masa sipariş verdiğinde'),
    NotificationCategory('CALL_WAITER', 'Garson Çağrısı', 'Masa garson çağırdığında'),
    NotificationCategory('REQUEST_BILL', 'Hesap Talebi', 'Masa hesap istediğinde'),
    NotificationCategory('ORDER_READY', 'Sipariş Hazır', 'Mutfak hazır bildirdiğinde'),
    NotificationCategory('KITCHEN_ISSUE', 'Mutfak Uyarısı', 'Mutfak sorun bildirdiğinde'),
    NotificationCategory('CANCEL_ORDER', 'İptal Talebi', 'Sipariş iptali istendiğinde'),
    NotificationCategory('PAYMENT_RECEIVED', 'Ödeme Alındı', 'Ödeme tamamlandığında'),
    NotificationCategory('EDIT_APPROVAL', 'Onay Bekleniyor', 'Değişiklik onayı gerektiğinde'),
    NotificationCategory('SYSTEM', 'Sistem Bildirimleri', 'Güncelleme / duyurular'),
  ];

  SharedPreferences? _prefs;
  bool _loaded = false;

  bool _pushEnabled = true;
  bool _soundEnabled = true;
  bool _vibrationEnabled = true;
  bool _inAppBanner = true;
  final Map<String, bool> _categoryEnabled = <String, bool>{};

  bool get pushEnabled => _pushEnabled;
  bool get soundEnabled => _soundEnabled;
  bool get vibrationEnabled => _vibrationEnabled;
  bool get inAppBanner => _inAppBanner;

  bool isCategoryEnabled(String key) => _categoryEnabled[key] ?? true;

  /// True if the device should surface a local banner for [type] (the
  /// uppercase backend notification type). Respects the master
  /// `pushEnabled` switch as well as the per-category toggle.
  bool shouldShow(String? type) {
    if (!_pushEnabled) return false;
    if (type == null || type.isEmpty) return true;
    return isCategoryEnabled(type.toUpperCase());
  }

  Future<void> ensureLoaded() async {
    if (_loaded) return;
    _prefs = await SharedPreferences.getInstance();
    _pushEnabled = _prefs!.getBool(_kPushEnabled) ?? true;
    _soundEnabled = _prefs!.getBool(_kSoundEnabled) ?? true;
    _vibrationEnabled = _prefs!.getBool(_kVibrationEnabled) ?? true;
    _inAppBanner = _prefs!.getBool(_kInAppBanner) ?? true;
    for (final c in categories) {
      _categoryEnabled[c.key] = _prefs!.getBool('$_kCategoryPrefix${c.key}') ?? true;
    }
    _loaded = true;
  }

  Future<void> setPushEnabled(bool v) async {
    await ensureLoaded();
    _pushEnabled = v;
    await _prefs!.setBool(_kPushEnabled, v);
    notifyListeners();
  }

  Future<void> setSoundEnabled(bool v) async {
    await ensureLoaded();
    _soundEnabled = v;
    await _prefs!.setBool(_kSoundEnabled, v);
    notifyListeners();
  }

  Future<void> setVibrationEnabled(bool v) async {
    await ensureLoaded();
    _vibrationEnabled = v;
    await _prefs!.setBool(_kVibrationEnabled, v);
    notifyListeners();
  }

  Future<void> setInAppBanner(bool v) async {
    await ensureLoaded();
    _inAppBanner = v;
    await _prefs!.setBool(_kInAppBanner, v);
    notifyListeners();
  }

  Future<void> setCategoryEnabled(String key, bool v) async {
    await ensureLoaded();
    _categoryEnabled[key] = v;
    await _prefs!.setBool('$_kCategoryPrefix$key', v);
    notifyListeners();
  }
}

class NotificationCategory {
  const NotificationCategory(this.key, this.label, this.description);
  final String key;
  final String label;
  final String description;
}
