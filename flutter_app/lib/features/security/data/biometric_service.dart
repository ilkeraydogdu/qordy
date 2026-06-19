import 'package:flutter/services.dart';
import 'package:local_auth/local_auth.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// Ince biyometrik giriş katmanı.
///
/// Sadece "bu cihazda biyometrik açık mı?" gibi kullanıcı tercihini ve
/// `local_auth` üzerinden parmak izi / Face ID doğrulamasını yönetir.
/// Parola/PIN'i saklamaz — biyometri sadece mevcut PIN'e hızlı geçiş
/// için kullanılır. Cihaz sahibi biyometrisi cihazda ayarlı değilse
/// `canUseBiometrics()` `false` döner ve UI toggle gizlenir.
class BiometricService {
  static const _prefsKeyPrefix = 'biometric_enabled_';

  final LocalAuthentication _auth = LocalAuthentication();

  /// Cihaz destekliyor mu ve en az bir biyometri kayıtlı mı?
  Future<bool> canUseBiometrics() async {
    try {
      final supported = await _auth.isDeviceSupported();
      if (!supported) return false;
      final canCheck = await _auth.canCheckBiometrics;
      if (!canCheck) return false;
      final types = await _auth.getAvailableBiometrics();
      return types.isNotEmpty;
    } on PlatformException {
      return false;
    } on LocalAuthException {
      return false;
    }
  }

  /// Cihazda kayıtlı biyometri tipleri ("fingerprint", "face", ...).
  Future<List<BiometricType>> availableTypes() async {
    try {
      return await _auth.getAvailableBiometrics();
    } on PlatformException {
      return const [];
    } on LocalAuthException {
      return const [];
    }
  }

  /// Kullanıcı bazlı "biyometrik hızlı giriş" açık mı?
  Future<bool> isEnabledForUser(String userId) async {
    if (userId.isEmpty) return false;
    final prefs = await SharedPreferences.getInstance();
    return prefs.getBool('$_prefsKeyPrefix$userId') ?? false;
  }

  /// Aç / kapat. Gerçek biyometri doğrulaması [authenticate] üzerinden
  /// yapılır; burada sadece kullanıcı tercihi saklanır.
  Future<void> setEnabledForUser(String userId, bool enabled) async {
    if (userId.isEmpty) return;
    final prefs = await SharedPreferences.getInstance();
    await prefs.setBool('$_prefsKeyPrefix$userId', enabled);
  }

  /// Biyometriyi tetikle. Başarılıysa true döner. Reason metni
  /// sistem diyalogunda üstte gösterilir.
  Future<bool> authenticate({
    String reason = 'Kimliğinizi doğrulayın',
  }) async {
    try {
      return await _auth.authenticate(
        localizedReason: reason,
        biometricOnly: true,
        persistAcrossBackgrounding: true,
      );
    } on PlatformException {
      return false;
    } on LocalAuthException {
      return false;
    }
  }
}
