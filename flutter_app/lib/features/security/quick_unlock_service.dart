import 'dart:convert';
import 'dart:math';

import 'package:crypto/crypto.dart';

import '../../core/security/secure_storage.dart';

/// "Quick unlock" = a 4–8 digit PIN the user sets up after the first
/// successful login. On subsequent app launches we gate access to the
/// already-persisted session behind this PIN instead of asking the user
/// to re-enter their email + password every time.
///
/// Security notes:
///
/// * The actual bearer token never leaves [flutter_secure_storage], which
///   is already KeyStore/Keychain-backed (see [secureStorage]). The PIN
///   does NOT encrypt the token itself — it just gates *access* at the
///   UI layer. Losing the PIN means the user must re-login.
/// * PIN verification uses `SHA-256(pin || salt)` with a 16-byte random
///   per-user salt, so rainbow tables can't brute-force a leaked secure
///   storage snapshot.
/// * We also track consecutive failed attempts and lock out after 5
///   fails until the user signs out fully, preventing offline brute
///   force. [getFailedAttempts]/[incrementFailedAttempts]/[resetFailedAttempts].
///
/// Keys are namespaced per `userId` so staff and owner on the same
/// physical device don't stomp each other's PIN.
class QuickUnlockService {
  QuickUnlockService();

  static const _keyMethod = 'qu_method';
  static const _keySalt = 'qu_salt';
  static const _keyHash = 'qu_hash';
  static const _keyLength = 'qu_length';
  static const _keyFailedAttempts = 'qu_failed';
  static const _keyLastUser = 'qu_last_user';
  static const _keyUserMeta = 'qu_user_meta';

  static const int _maxFailedAttempts = 5;

  String _k(String base, String userId) => '${base}_$userId';

  Future<bool> isEnabledForUser(String userId) async {
    if (userId.isEmpty) return false;
    final v = await secureStorage.read(key: _k(_keyHash, userId));
    return v != null && v.isNotEmpty;
  }

  /// Returns the last userId that successfully enabled quick unlock on
  /// this device. The UI uses this to decide whether cold-start should
  /// jump to `/unlock` or straight to `/role-select`.
  Future<String?> getLastUserId() async {
    return secureStorage.read(key: _keyLastUser);
  }

  Future<Map<String, String>?> getUserMeta(String userId) async {
    if (userId.isEmpty) return null;
    final raw = await secureStorage.read(key: _k(_keyUserMeta, userId));
    if (raw == null || raw.isEmpty) return null;
    try {
      final decoded = jsonDecode(raw);
      if (decoded is Map) {
        return decoded.map((k, v) => MapEntry(k.toString(), v?.toString() ?? ''));
      }
    } catch (_) {}
    return null;
  }

  /// Registers a new PIN for [userId]. `method` is informational for
  /// the UI ('pin' / 'pattern'). Resets any stale failed-attempt counter.
  Future<void> setupPin({
    required String userId,
    required String pin,
    String method = 'pin',
    Map<String, String>? userMeta,
  }) async {
    if (userId.isEmpty) {
      throw ArgumentError('userId required');
    }
    if (pin.length < 4 || pin.length > 8) {
      throw ArgumentError('PIN must be 4–8 characters long');
    }
    final salt = _randomSaltHex();
    final hash = _hashPin(pin, salt);
    await secureStorage.write(key: _k(_keySalt, userId), value: salt);
    await secureStorage.write(key: _k(_keyHash, userId), value: hash);
    await secureStorage.write(
        key: _k(_keyLength, userId), value: pin.length.toString());
    await secureStorage.write(key: _k(_keyMethod, userId), value: method);
    await secureStorage.write(
        key: _k(_keyFailedAttempts, userId), value: '0');
    await secureStorage.write(key: _keyLastUser, value: userId);
    if (userMeta != null && userMeta.isNotEmpty) {
      await secureStorage.write(
          key: _k(_keyUserMeta, userId), value: jsonEncode(userMeta));
    }
  }

  /// Verifies [pin] against the stored hash. Returns `true` on success
  /// and resets the failed counter; on failure, increments it and
  /// returns `false`. When the counter hits [_maxFailedAttempts] we
  /// wipe the PIN (caller should then force a full re-login).
  Future<QuickUnlockResult> verify({
    required String userId,
    required String pin,
  }) async {
    if (userId.isEmpty) return QuickUnlockResult.notSet;
    final storedHash = await secureStorage.read(key: _k(_keyHash, userId));
    final salt = await secureStorage.read(key: _k(_keySalt, userId));
    if (storedHash == null ||
        storedHash.isEmpty ||
        salt == null ||
        salt.isEmpty) {
      return QuickUnlockResult.notSet;
    }
    final hash = _hashPin(pin, salt);
    if (hash == storedHash) {
      await secureStorage.write(
          key: _k(_keyFailedAttempts, userId), value: '0');
      return QuickUnlockResult.ok;
    }
    final next = (await getFailedAttempts(userId)) + 1;
    if (next >= _maxFailedAttempts) {
      await clear(userId);
      return QuickUnlockResult.lockedOut;
    }
    await secureStorage.write(
        key: _k(_keyFailedAttempts, userId), value: next.toString());
    return QuickUnlockResult.wrong;
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

  Future<String?> getMethod(String userId) async {
    return secureStorage.read(key: _k(_keyMethod, userId));
  }

  Future<int?> getPinLength(String userId) async {
    final s = await secureStorage.read(key: _k(_keyLength, userId));
    return s == null ? null : int.tryParse(s);
  }

  /// Erases all quick-unlock state for [userId]. Call this on signout or
  /// when the user explicitly turns off quick unlock.
  Future<void> clear(String userId) async {
    if (userId.isEmpty) return;
    await secureStorage.delete(key: _k(_keySalt, userId));
    await secureStorage.delete(key: _k(_keyHash, userId));
    await secureStorage.delete(key: _k(_keyLength, userId));
    await secureStorage.delete(key: _k(_keyMethod, userId));
    await secureStorage.delete(key: _k(_keyFailedAttempts, userId));
    await secureStorage.delete(key: _k(_keyUserMeta, userId));
    final last = await getLastUserId();
    if (last == userId) {
      await secureStorage.delete(key: _keyLastUser);
    }
  }

  /// Wipes every quick-unlock blob on device. Used by the global
  /// "forget everything" sign-out path.
  Future<void> clearAll() async {
    final last = await getLastUserId();
    if (last != null) await clear(last);
    await secureStorage.delete(key: _keyLastUser);
  }

  static String _hashPin(String pin, String saltHex) {
    final bytes = utf8.encode(pin) + _hexDecode(saltHex);
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

enum QuickUnlockResult { ok, wrong, lockedOut, notSet }
