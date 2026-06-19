import 'dart:convert';
import 'dart:math';

import 'package:crypto/crypto.dart';

import '../../core/security/secure_storage.dart';

/// User-drawn swipe pattern stored on device as `SHA-256(pattern || salt)`.
///
/// Rationale: the customer asked for the full "PIN / desen / Face ID /
/// 2FA" matrix of unlock methods. Biometric and PIN already exist; this
/// service adds the "desen" (pattern) flow so waiter/kitchen/cashier
/// staff — who already log in with an operational PIN — can pick a
/// second-factor *other than* PIN on cold start, and managers get an
/// alternative to the numeric keypad.
///
/// Design mirrors [QuickUnlockService] intentionally: namespaced per
/// user, hashed with a random per-user salt, failed-attempt counter
/// that wipes the secret after 5 bad tries and forces a full re-login.
/// The pattern itself is encoded as the sequence of dot indices
/// (0-based) joined by `-`, e.g. `"0-1-2-5-8"` for an "L" shape on a
/// 3×3 grid. Short patterns (<4 dots) are rejected at setup.
class PatternUnlockService {
  PatternUnlockService();

  static const _keySalt = 'pat_salt';
  static const _keyHash = 'pat_hash';
  static const _keyFailedAttempts = 'pat_failed';
  static const _keyLastUser = 'pat_last_user';

  static const int _maxFailedAttempts = 5;

  String _k(String base, String userId) => '${base}_$userId';

  /// True when the user has enrolled a pattern on this device.
  Future<bool> isEnabledForUser(String userId) async {
    if (userId.isEmpty) return false;
    final v = await secureStorage.read(key: _k(_keyHash, userId));
    return v != null && v.isNotEmpty;
  }

  Future<String?> getLastUserId() {
    return secureStorage.read(key: _keyLastUser);
  }

  Future<void> setupPattern({
    required String userId,
    required List<int> dots,
  }) async {
    if (userId.isEmpty) {
      throw ArgumentError('userId required');
    }
    if (dots.length < 4) {
      throw ArgumentError('Pattern must connect at least 4 dots');
    }
    final encoded = dots.join('-');
    final salt = _randomSaltHex();
    final hash = _hashPattern(encoded, salt);
    await secureStorage.write(key: _k(_keySalt, userId), value: salt);
    await secureStorage.write(key: _k(_keyHash, userId), value: hash);
    await secureStorage.write(
        key: _k(_keyFailedAttempts, userId), value: '0');
    await secureStorage.write(key: _keyLastUser, value: userId);
  }

  Future<PatternUnlockResult> verify({
    required String userId,
    required List<int> dots,
  }) async {
    if (userId.isEmpty) return PatternUnlockResult.notSet;
    if (dots.length < 4) return PatternUnlockResult.wrong;
    final storedHash = await secureStorage.read(key: _k(_keyHash, userId));
    final salt = await secureStorage.read(key: _k(_keySalt, userId));
    if (storedHash == null ||
        storedHash.isEmpty ||
        salt == null ||
        salt.isEmpty) {
      return PatternUnlockResult.notSet;
    }
    final encoded = dots.join('-');
    final hash = _hashPattern(encoded, salt);
    if (hash == storedHash) {
      await secureStorage.write(
          key: _k(_keyFailedAttempts, userId), value: '0');
      return PatternUnlockResult.ok;
    }
    final next = (await getFailedAttempts(userId)) + 1;
    if (next >= _maxFailedAttempts) {
      await clear(userId);
      return PatternUnlockResult.lockedOut;
    }
    await secureStorage.write(
        key: _k(_keyFailedAttempts, userId), value: next.toString());
    return PatternUnlockResult.wrong;
  }

  Future<int> getFailedAttempts(String userId) async {
    if (userId.isEmpty) return 0;
    final s = await secureStorage.read(key: _k(_keyFailedAttempts, userId));
    return int.tryParse(s ?? '0') ?? 0;
  }

  Future<int> getRemainingAttempts(String userId) async {
    final used = await getFailedAttempts(userId);
    return (_maxFailedAttempts - used).clamp(0, _maxFailedAttempts);
  }

  Future<void> clear(String userId) async {
    if (userId.isEmpty) return;
    await secureStorage.delete(key: _k(_keySalt, userId));
    await secureStorage.delete(key: _k(_keyHash, userId));
    await secureStorage.delete(key: _k(_keyFailedAttempts, userId));
    final last = await getLastUserId();
    if (last == userId) {
      await secureStorage.delete(key: _keyLastUser);
    }
  }

  static String _hashPattern(String encoded, String saltHex) {
    final bytes = utf8.encode(encoded) + _hexDecode(saltHex);
    return sha256.convert(bytes).toString();
  }

  static String _randomSaltHex() {
    final rng = Random.secure();
    final bytes = List<int>.generate(16, (_) => rng.nextInt(256));
    return bytes.map((b) => b.toRadixString(16).padLeft(2, '0')).join();
  }

  static List<int> _hexDecode(String hex) {
    final out = <int>[];
    for (var i = 0; i < hex.length; i += 2) {
      out.add(int.parse(hex.substring(i, i + 2), radix: 16));
    }
    return out;
  }
}

enum PatternUnlockResult { ok, wrong, lockedOut, notSet }
