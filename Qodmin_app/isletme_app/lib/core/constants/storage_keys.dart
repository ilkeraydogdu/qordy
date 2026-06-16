/// Secure storage anahtarları.
///
/// ASLA plain shared_preferences'a token/PII yazma — yalnızca secure storage.
class StorageKeys {
 StorageKeys._();

 /// Bearer token.
 static const String accessToken = 'auth.access_token';

 /// Refresh token.
 static const String refreshToken = 'auth.refresh_token';

 /// Token alınma zamanı (epoch ms) — refresh stratejisi için.
 static const String tokenIssuedAt = 'auth.token_issued_at';

 /// Token süresi (saniye).
 static const String tokenExpiresIn = 'auth.token_expires_in';

 /// Aktif tenant subdomain.
 static const String tenantSubdomain = 'tenant.subdomain';

 /// User ID.
 static const String userId = 'auth.user_id';

 /// User email.
 static const String userEmail = 'auth.user_email';

 /// 2FA zorunlu mu?
 static const String requires2fa = 'auth.requires_2fa';

 /// Kullanıcının seçtiği 2FA yöntemi.
 static const String twoFactorMethod = 'auth.2fa_method';

 /// FCM token (push bildirimler için).
 static const String fcmToken = 'push.fcm_token';

 /// Son senkronizasyon zamanı.
 static const String lastSyncAt = 'sync.last_at';
}
