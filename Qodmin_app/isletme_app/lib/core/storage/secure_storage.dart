import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import '../constants/storage_keys.dart';

/// Secure storage sarmalayıcı.
///
/// Android'de EncryptedSharedPreferences + Keystore kullanır.
/// ASLA plain shared_preferences'a token/PII yazma.
class SecureStorageService {
 SecureStorageService({FlutterSecureStorage? storage})
 : _storage = storage ??
 const FlutterSecureStorage(
 aOptions: AndroidOptions(
 encryptedSharedPreferences: true,
 resetOnError: true,
 ),
 );

 final FlutterSecureStorage _storage;

 Future<String?> readString(String key) => _storage.read(key: key);

 Future<void> writeString(String key, String value) =>
 _storage.write(key: key, value: value);

 Future<void> delete(String key) => _storage.delete(key: key);

 Future<void> deleteAll() => _storage.deleteAll();

 /// Access token oku.
 Future<String?> readAccessToken() => readString(StorageKeys.accessToken);

 /// Access token yaz.
 Future<void> writeAccessToken(String token) =>
 writeString(StorageKeys.accessToken, token);

 /// Refresh token oku.
 Future<String?> readRefreshToken() => readString(StorageKeys.refreshToken);

 /// Refresh token yaz.
 Future<void> writeRefreshToken(String token) =>
 writeString(StorageKeys.refreshToken, token);

 /// Auth ile ilgili tüm verileri temizle (logout).
 Future<void> clearAuth() async {
 await delete(StorageKeys.accessToken);
 await delete(StorageKeys.refreshToken);
 await delete(StorageKeys.tokenIssuedAt);
 await delete(StorageKeys.tokenExpiresIn);
 await delete(StorageKeys.userId);
 await delete(StorageKeys.userEmail);
 await delete(StorageKeys.requires2fa);
 await delete(StorageKeys.twoFactorMethod);
 }

 /// Tüm hassas verileri temizle (account deletion).
 Future<void> wipe() => deleteAll();
}
