import 'dart:async';

import 'package:dio/dio.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:qordy_app/core/navigation/role_home.dart';
import 'package:qordy_app/core/network/auth_interceptor.dart';
import 'package:qordy_app/features/auth/cubit/auth_state.dart';
import 'package:qordy_app/features/auth/data/auth_repository.dart';
import 'package:qordy_app/features/security/data/biometric_service.dart';
import 'package:qordy_app/features/security/pattern_unlock_service.dart';
import 'package:qordy_app/features/security/quick_unlock_service.dart';
import 'package:qordy_app/models/business.dart';
import 'package:qordy_app/models/user.dart' show User;

class AuthCubit extends Cubit<AuthState> {
 final AuthRepository _repository;
 final QuickUnlockService _quickUnlock;
 final PatternUnlockService? _pattern;
 final BiometricService? _biometrics;
 StreamSubscription<UnauthorizedReason>? _unauthorizedSubscription;

 /// Timestamp of the last successful [Authenticated] emission. Used to
 /// suppress transient `sessionExpired` events that occasionally race
 /// with the very first API call after login (the new bearer is saved
 /// in secure storage asynchronously, and on slower devices the first
 /// dashboard `GET` can fire before the token write has actually
 /// reached the store — producing a ghost 401). Explicit terminal
 /// reasons (`tenantSuspended`, `userInactive`, `sessionRevoked`) are
 /// never ignored because they represent admin-side decisions.
 DateTime? _lastAuthenticatedAt;
 static const _postLoginGracePeriod = Duration(seconds: 4);

 AuthCubit({
 required AuthRepository repository,
 required QuickUnlockService quickUnlock,
 PatternUnlockService? pattern,
 BiometricService? biometrics,
 }) : _repository = repository,
 _quickUnlock = quickUnlock,
 _pattern = pattern,
 _biometrics = biometrics,
 super(const AuthInitial()) {
 // React to backend-issued 401/403s globally: the interceptor has
 // already purged persisted credentials, so we flip the Cubit state
 // back to [AuthInitial] which — via GoRouter's redirect — bounces
 // the user to /login. We additionally emit an [AuthError]
 // with a reason-specific message so the UI can explain *why* the
 // session ended (generic expiry vs admin revocation vs tenant
 // suspension).
 _unauthorizedSubscription =
 AuthInterceptor.unauthorizedStream.listen((reason) {
 if (state is! Authenticated) return;

 // Swallow transient expiry noise right after the manager login
 // emission — see [_lastAuthenticatedAt] docstring.
 if (reason == UnauthorizedReason.sessionExpired &&
 _lastAuthenticatedAt != null &&
 DateTime.now().difference(_lastAuthenticatedAt!) <
 _postLoginGracePeriod) {
 return;
 }

 final message = _messageForReason(reason);
 if (message != null) {
 emit(AuthError(message));
 }
 emit(const AuthInitial());
 });
 }

 static String? _messageForReason(UnauthorizedReason reason) {
 switch (reason) {
 case UnauthorizedReason.sessionRevoked:
 return 'Oturumunuz iptal edildi. Lütfen tekrar giriş yapın.';
 case UnauthorizedReason.userInactive:
 return 'Kullanıcı hesabınız pasif durumda. Yöneticinizle iletişime geçin.';
 case UnauthorizedReason.tenantSuspended:
 return 'İşletmeniz askıya alındı. Lütfen işletme sahibiyle iletişime geçin.';
 case UnauthorizedReason.sessionExpired:
 return null; // Sessiz düşüş — silent re-login tecrübesi
 }
 }

 @override
 Future<void> close() async {
 await _unauthorizedSubscription?.cancel();
 return super.close();
 }

 Future<void> initialize() async {
 emit(const AuthLoading());
 try {
 await _repository.initialize();
 final user = _repository.currentUser;
 final business = _repository.currentBusiness;
 final token = _repository.token;
 if (_repository.isAuthenticated &&
 user != null &&
 business != null &&
 token != null &&
 token.isNotEmpty) {
 // Cold start of an already-logged-in device:
 // * Manager / owner / admin: a user-configured PIN gates the
 // session; optional biometric accelerates it. Bypass only if
 // the user has not set up quick unlock.
 final uid = user.userId ?? '';
 final role = AppRole.fromUser(user);
 final hasPin = await _quickUnlock.isEnabledForUser(uid);

 bool gated = false;
 bool bioOnly = false;
 bool hasPattern = false;
 if (uid.isNotEmpty) {
 // Pattern is PIN-equivalent strength and can be enabled by
 // managers. Treat its presence as a valid gating factor.
 try {
 hasPattern =
 await (_pattern?.isEnabledForUser(uid) ?? Future.value(false));
 } catch (_) {}

 hasPin = await _quickUnlock.isEnabledForUser(uid);
 if (hasPin || hasPattern) {
 gated = true;
 bioOnly = false;
 }
 }

 if (gated) {
 emit(PendingUnlock(
 userId: uid,
 displayName: user.displayName,
 avatarUrl: user.avatar,
 businessName: business.companyName,
 businessLogo: business.logoUrl,
 roleLabel: role.label,
 user: user,
 business: business,
 token: token,
 bioOnly: bioOnly,
 patternEnabled: hasPattern,
 pinEnabled: hasPin,
 ));
 } else {
 _lastAuthenticatedAt = DateTime.now();
 emit(Authenticated(
 user: user,
 business: business,
 token: token,
 ));
 }
 } else {
 // Repository reports authenticated but the persisted payload is
 // incomplete (app was force-killed mid-login, secure storage was
 // cleared, etc.) — safest path is the login flow.
 emit(const AuthInitial());
 }
 } catch (_) {
 emit(const AuthInitial());
 }
 }

 /// Called by `/quick-unlock` when the user has entered a valid PIN.
 /// Re-hydrates [Authenticated] from the already-persisted credentials
 /// without hitting the network.
 Future<void> completeUnlock() async {
 final current = state;
 if (current is! PendingUnlock) return;
 _lastAuthenticatedAt = DateTime.now();
 emit(Authenticated(
 user: current.user,
 business: current.business,
 token: current.token,
 permissions: current.permissions,
 stats: current.stats,
 ));
 }

 /// Called after the user either enables quick unlock or skips it on
 /// the `/quick-unlock/setup` screen. Promotes the transient
 /// [QuickUnlockSetupRequired] state into [Authenticated].
 Future<void> completeQuickUnlockSetup() async {
 final current = state;
 if (current is! QuickUnlockSetupRequired) return;
 _lastAuthenticatedAt = DateTime.now();
 emit(Authenticated(
 user: current.user,
 business: current.business,
 token: current.token,
 permissions: current.permissions,
 stats: current.stats,
 ));
 }

 QuickUnlockService get quickUnlockService => _quickUnlock;
 PatternUnlockService? get patternUnlockService => _pattern;

 /// Unified login entry point used by the redesigned `/login` screen.
 ///
 /// The mobile app now only supports manager/owner login. The input
 /// field asks the user to type their email address. This method inspects
 /// the raw input and fans out to the validator.
 Future<void> resolveIdentity(String input) async {
 final trimmed = input.trim();
 if (trimmed.isEmpty) {
 emit(const AuthError('Lütfen e-posta adresinizi girin.'));
 return;
 }
 if (_looksLikeEmail(trimmed)) {
 await validateManagerEmail(trimmed);
 } else {
 emit(const AuthError('Geçersiz e-posta adresi.'));
 }
 }

 /// Heuristic used by [resolveIdentity] to decide whether to treat the
 /// input as an owner/manager e-mail address. Intentionally strict
 /// enough that casual strings like "cadde cafe" never get dispatched
 /// to the email validator: requires an `@`, at least one character
 /// before it, and a domain with a TLD-looking suffix.
 bool _looksLikeEmail(String value) {
 final at = value.indexOf('@');
 if (at <= 0) return false;
 final domain = value.substring(at + 1);
 if (domain.isEmpty) return false;
 if (!domain.contains('.')) return false;
 // Reject trailing dots or "@." style inputs.
 if (domain.startsWith('.') || domain.endsWith('.')) return false;
 return RegExp(r'^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$')
 .hasMatch(value);
 }

 Future<void> validateManagerEmail(String email) async {
 emit(const AuthLoading());
 try {
 final response = await _repository.validateManagerEmail(email);
 if (response['success'] == true) {
 emit(EmailValidated(
 email: email,
 data: _asMapOrNull(response['data']),
 ));
 } else {
 emit(AuthError(
 response['error']?.toString() ?? 'E-posta bulunamadı',
 ));
 }
 } on DioException catch (e) {
 emit(AuthError(_extractError(e)));
 } catch (e) {
 emit(AuthError(_friendly(e)));
 }
 }

 Future<void> managerLogin(String email, String password) async {
 emit(const AuthLoading());
 try {
 final response = await _repository.managerLogin(email, password);
 if (response['success'] == true) {
 final data = _asMap(response['data']);
 if (data['requires_2fa'] == true) {
 emit(_twoFactorChallengeFromPayload(data, flow: 'manager'));
 return;
 }
 final authed = _authenticatedFromPayload(data);
 emit(await _postLoginState(authed));
 } else {
 emit(AuthError(
 response['error']?.toString() ?? 'Giriş başarısız',
 ));
 }
 } on DioException catch (e) {
 emit(AuthError(_extractError(e)));
 } catch (e) {
 emit(AuthError(_friendly(e)));
 }
 }

 /// Submits the 6-digit TOTP code against the pending challenge. On
 /// success the repo persists the real bearer and we emit
 /// [Authenticated] (or [QuickUnlockSetupRequired]). On failure we
 /// stay on [TwoFactorChallengeRequired] but surface a one-off
 /// [AuthError] for the UI to show — preserving the challenge token so
 /// the user can retry without re-entering their password.
 Future<void> verifyTwoFactor(String code, {String method = 'totp'}) async {
 final current = state;
 if (current is! TwoFactorChallengeRequired) return;
 final challenge = current.challengeToken;
 TwoFactorChallengeRequired restore() => TwoFactorChallengeRequired(
 challengeToken: challenge,
 flow: current.flow,
 expiresInSeconds: current.expiresInSeconds,
 displayName: current.displayName,
 businessName: current.businessName,
 businessLogo: current.businessLogo,
 roleLabel: current.roleLabel,
 methods: current.methods,
 defaultMethod: current.defaultMethod,
 );
 try {
 final response = await _repository.verify2FAChallenge(
 challengeToken: challenge,
 code: code,
 method: method,
 );
 if (response['success'] == true) {
 final authed = _authenticatedFromPayload(response['data']);
 emit(await _postLoginState(authed));
 } else {
 final message =
 response['error']?.toString() ?? 'Doğrulama kodu hatalı';
 emit(AuthError(message));
 emit(restore());
 }
 } on DioException catch (e) {
 emit(AuthError(_extractError(e)));
 final status = e.response?.statusCode ?? 0;
 if (status == 429 || status == 410) {
 emit(const AuthInitial());
 return;
 }
 emit(restore());
 } catch (e) {
 emit(AuthError(_friendly(e)));
 emit(restore());
 }
 }

 /// Asks the server to deliver a one-time code via the chosen channel
 /// (WhatsApp / Email / SMS). The state is not changed; callers should
 /// await the returned future and show a SnackBar with the result.
 Future<Map<String, dynamic>> send2FACode(String method) async {
 final current = state;
 if (current is! TwoFactorChallengeRequired) {
 return {'success': false, 'error': 'Aktif 2FA oturumu yok'};
 }
 try {
 return await _repository.send2FAChallengeCode(
 challengeToken: current.challengeToken,
 method: method,
 );
 } on DioException catch (e) {
 return {'success': false, 'error': _extractError(e)};
 } catch (e) {
 return {'success': false, 'error': _friendly(e)};
 }
 }

 /// Abandon the pending 2FA challenge and return the user to the
 /// login screen. The backend challenge will expire on its own after
 /// its TTL; there's no explicit cancel endpoint to call.
 void cancelTwoFactorChallenge() {
 if (state is TwoFactorChallengeRequired) {
 emit(const AuthInitial());
 }
 }

 /// Mobil kayıt — backend trial + otomatik oturum açma ile token döndürür.
 /// Başarılı olursa [Authenticated] yayınlar ve router
 /// `RegisterScreen`'ı kapatarak dashboard'a yönlendirir.
 Future<void> registerBusiness(Map<String, dynamic> data) async {
 emit(const AuthLoading());
 try {
 final response = await _repository.registerBusiness(data);
 if (response['success'] == true) {
 final authed = _authenticatedFromPayload(response['data']);
 emit(await _postLoginState(authed));
 } else {
 emit(AuthError(
 response['error']?.toString() ?? 'Kayıt başarısız',
 ));
 }
 } on DioException catch (e) {
 emit(AuthError(_extractError(e)));
 } catch (e) {
 emit(AuthError(_friendly(e)));
 }
 }

 /// Converts the `data` field of an auth API response into an
 /// [Authenticated] state. Tolerant of shape drift — backends have been
 /// known to return a `List&lt;String&gt;` for `permissions` and a missing
 /// `stats` field; neither should crash the client.
 Authenticated _authenticatedFromPayload(dynamic payload) {
 final data = _asMap(payload);
 final userJson = _asMap(data['user']);
 final businessJson = _asMap(data['business']);
 final token = data['token']?.toString() ?? '';
 _lastAuthenticatedAt = DateTime.now();
 return Authenticated(
 user: User.fromJson(userJson),
 business: Business.fromJson(businessJson),
 token: token,
 permissions: _asStringList(data['permissions']),
 stats: _asMapOrNull(data['stats']),
 );
 }

 /// Hoists the `requires_2fa` login response into its Cubit state.
 /// Extracts cosmetic fields defensively — any missing key just means
 /// a blander challenge screen, never a crash.
 TwoFactorChallengeRequired _twoFactorChallengeFromPayload(
 Map<String, dynamic> data, {
 required String flow,
 }) {
 final user = _asMapOrNull(data['user']);
 final business = _asMapOrNull(data['business']);
 final rawMethods = data['methods'];
 final methods = <String>[];
 if (rawMethods is List) {
 for (final m in rawMethods) {
 final v = m?.toString();
 if (v != null && v.isNotEmpty) methods.add(v);
 }
 }
 if (methods.isEmpty) {
 final single = data['method']?.toString();
 methods.add((single == null || single.isEmpty) ? 'totp' : single);
 }
 return TwoFactorChallengeRequired(
 challengeToken: data['challenge_token']?.toString() ?? '',
 expiresInSeconds: _asInt(data['expires_in']) ?? 300,
 flow: flow,
 displayName: user?['name']?.toString(),
 roleLabel: AppRole.fromRoleName(user?['role']?.toString() ?? '').label,
 businessName: business?['name']?.toString(),
 businessLogo: business?['logo']?.toString(),
 methods: methods,
 defaultMethod: data['method']?.toString() ?? methods.first,
 );
 }

 /// Wraps a freshly-authenticated payload in either [Authenticated]
 /// directly (existing user who already has quick-unlock configured or
 /// already declined) or [QuickUnlockSetupRequired] so the router
 /// bounces them through `/quick-unlock/setup` on the very first
 /// login. Used by manager-password and register flows.
 Future<AuthState> _postLoginState(
 Authenticated base, {
 bool fromStaffPin = false,
 }) async {
 // Ödeme paywall'u için abonelik durumunu iliştir. Bu non-blocking —
 // hata alırsak base ile devam ediyoruz (router phase null gördüğünde
 // gating uygulamıyor).
 final hydrated = await _hydrateSubscription(base);

 final uid = hydrated.user.userId ?? '';
 if (uid.isEmpty) return hydrated;

 final role = AppRole.fromUser(hydrated.user);
 // Only managers get the quick-unlock setup ceremony
 final isManager = role == AppRole.admin || role == AppRole.manager;
 if (!isManager) return hydrated;

 final already = await _quickUnlock.isEnabledForUser(uid);
 if (already) return hydrated;
 return QuickUnlockSetupRequired(
 user: hydrated.user,
 business: hydrated.business,
 token: hydrated.token,
 permissions: hydrated.permissions,
 stats: hydrated.stats,
 );
 }

 /// Fetches `/subscription/status` using the freshly-issued bearer
 /// token and attaches phase / readOnly / daysLeft to the
 /// [Authenticated] payload. Never throws — a failed fetch simply
 /// returns the unchanged state.
 Future<Authenticated> _hydrateSubscription(Authenticated base) async {
 try {
 final resp = await _repository.getSubscriptionStatus();
 if (resp['success'] != true) return base;
 final data = _asMap(resp['data']);
 return base.copyWith(
 subscriptionPhase: data['phase']?.toString(),
 subscriptionReadOnly: data['readOnly'] == true || data['read_only'] == true,
 subscriptionDaysLeft: _asInt(data['daysLeft'] ?? data['days_left']) ?? 0,
 subscriptionGraceDaysLeft:
 _asInt(data['graceDaysLeft'] ?? data['grace_days_left']) ?? 0,
 );
 } catch (_) {
 return base;
 }
 }

 /// Refreshes the subscription phase on the current [Authenticated]
 /// state without forcing a full re-login. Called after a successful
 /// iyzico purchase so the router immediately releases the paywall.
 Future<void> refreshSubscriptionStatus() async {
 final current = state;
 if (current is! Authenticated) return;
 final hydrated = await _hydrateSubscription(current);
 if (!isClosed) emit(hydrated);
 }

 Map<String, dynamic> _asMap(dynamic v) {
 if (v is Map) {
 return v.map((k, val) => MapEntry(k.toString(), val));
 }
 return <String, dynamic>{};
 }

 Map<String, dynamic>? _asMapOrNull(dynamic v) {
 if (v is Map) {
 return v.map((k, val) => MapEntry(k.toString(), val));
 }
 return null;
 }

 int? _asInt(dynamic v) {
 if (v is int) return v;
 if (v is num) return v.toInt();
 if (v is String) return int.tryParse(v);
 return null;
 }

 List<String> _asStringList(dynamic v) {
 if (v is List) {
 return v.map((e) => e?.toString() ?? '').where((s) => s.isNotEmpty).toList(growable: false);
 }
 // Accept a Map<String, bool/dynamic> shape too ({"*": true, ...}) —
 // older PHP builds may have returned that form.
 if (v is Map) {
 return v.entries
 .where((e) => e.value == true || e.value == 1 || e.value == '1')
 .map((e) => e.key.toString())
 .toList(growable: false);
 }
 return const [];
 }

 String _friendly(Object e) {
 final raw = e.toString();
 if (raw.contains("is not a subtype of type") || raw.contains('type cast')) {
 return 'Sunucu beklenmeyen bir yanıt döndürdü. Lütfen tekrar deneyin.';
 }
 return raw;
 }

 Future<void> logout({bool keepQuickUnlock = false}) async {
 final current = state;
 // Preserve the user id before we tear down the session so we can
 // optionally scrub the quick-unlock PIN for the correct account.
 String? uid;
 if (current is Authenticated) uid = current.user.userId;
 if (current is PendingUnlock) uid = current.userId;
 if (current is QuickUnlockSetupRequired) uid = current.user.userId;
 emit(const AuthLoading());
 try {
 await _repository.logout();
 } catch (_) {}
 if (!keepQuickUnlock && uid != null && uid.isNotEmpty) {
 try {
 await _quickUnlock.clear(uid);
 } catch (_) {}
 try {
 await _pattern?.clear(uid);
 } catch (_) {}
 }
 emit(const AuthInitial());
 }

 String _extractError(DioException e) {
 final data = e.response?.data;
 if (data is Map<String, dynamic>) {
 return data['error'] as String? ??
 data['message'] as String? ??
 'Bir hata oluştu';
 }
 if (e.type == DioExceptionType.connectionTimeout ||
 e.type == DioExceptionType.receiveTimeout) {
 return 'Bağlantı zaman aşımına uğradı';
 }
 if (e.type == DioExceptionType.connectionError) {
 return 'İnternet bağlantınızı kontrol edin';
 }
 return 'Bir hata oluştu';
 }
}
