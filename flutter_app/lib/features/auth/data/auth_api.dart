import 'package:dio/dio.dart';
import 'package:get_it/get_it.dart';
import '../../../config/api_config.dart';

class AuthApi {
 final Dio _dio;

 AuthApi({Dio? dio}) : _dio = dio ?? GetIt.instance<Dio>();

 Future<Map<String, dynamic>> validateSubdomain(String subdomain) async {
 final response = await _dio.post(
 ApiConfig.validateSubdomain,
 data: {'subdomain': subdomain},
 );
 return _asPayload(response.data);
 }

 /// 4-6 haneli benzersiz işletme numarasını doğrula. Personel bu
 /// numarayı yazıp PIN ekranına geçer. Aktif olmayan işletmeler
 /// 404 ile reddedilir; backend hata kodu istemci tarafında metne
 /// çevrilir.
 Future<Map<String, dynamic>> validateBusinessNumber(
 String businessNumber,
 ) async {
 final response = await _dio.post(
 ApiConfig.validateBusinessNumber,
 data: {'business_number': businessNumber},
 );
 return _asPayload(response.data);
 }

 Future<Map<String, dynamic>> staffLogin(
 String pin, {
 String? subdomain,
 String? businessNumber,
 }) async {
 final payload = <String, dynamic>{'pin': pin};
 if (businessNumber != null && businessNumber.isNotEmpty) {
 payload['business_number'] = businessNumber;
 } else if (subdomain != null && subdomain.isNotEmpty) {
 payload['subdomain'] = subdomain;
 }
 final response = await _dio.post(
 ApiConfig.staffLogin,
 data: payload,
 );
 return _asPayload(response.data);
 }

 Future<Map<String, dynamic>> validateManagerEmail(String email) async {
 final response = await _dio.post(
 ApiConfig.managerValidateEmail,
 data: {'email': email},
 );
 return _asPayload(response.data);
 }

 Future<Map<String, dynamic>> managerLogin(
 String email,
 String password,
 ) async {
 final response = await _dio.post(
 ApiConfig.managerLogin,
 data: {'email': email, 'password': password},
 );
 return _asPayload(response.data);
 }

 Future<Map<String, dynamic>> verifyToken(String token) async {
 final response = await _dio.post(
 ApiConfig.verifyToken,
 options: Options(headers: {'Authorization': 'Bearer $token'}),
 );
 return _asPayload(response.data);
 }

 Future<void> logout(String? token) async {
 await _dio.post(
 ApiConfig.logout,
 options: token != null
 ? Options(headers: {'Authorization': 'Bearer $token'})
 : null,
 );
 }

 Future<Map<String, dynamic>> registerBusiness(
 Map<String, dynamic> data,
 ) async {
 final response = await _dio.post(ApiConfig.register, data: data);
 return _asPayload(response.data);
 }

 /// Exchange a login challenge token + code for a full auth bundle.
 /// Works for all methods (totp/whatsapp/email/sms). Unauthenticated.
 Future<Map<String, dynamic>> verify2FAChallenge({
 required String challengeToken,
 required String code,
 String method = 'totp',
 }) async {
 final response = await _dio.post(
 ApiConfig.twoFactorVerify,
 data: {
 'challenge_token': challengeToken,
 'code': code,
 'method': method,
 },
 );
 return _asPayload(response.data);
 }

 /// Trigger server-side code delivery for non-TOTP methods (WhatsApp,
 /// Email, SMS). Unauthenticated — identified by the challenge token.
 Future<Map<String, dynamic>> send2FAChallengeCode({
 required String challengeToken,
 required String method,
 }) async {
 final response = await _dio.post(
 ApiConfig.twoFactorSend,
 data: {'challenge_token': challengeToken, 'method': method},
 );
 return _asPayload(response.data);
 }

 /// Polls the current subscription phase (trial/active/grace/expired/…)
 /// right after login so the router knows whether to gate the user
 /// onto the paywall. Authenticated call.
 Future<Map<String, dynamic>> getSubscriptionStatus() async {
 final response = await _dio.get(ApiConfig.subscriptionStatus);
 return _asPayload(response.data);
 }

 /// Normalises any Dio response body into a `Map<String, dynamic>` without
 /// throwing on unexpected shapes. Real-world sources of trouble:
 /// * PHP occasionally emits an HTML 500 page → `data` is a String.
 /// * Some endpoints return a bare List (e.g. legacy error handlers).
 /// * Keys can be `Object` when the response is proxied through a
 /// middleware that loses generic types.
 /// Anything that isn't map-shaped becomes `{success:false, error:...}`
 /// so the caller's `response['success'] == true` branch stays honest.
 Map<String, dynamic> _asPayload(dynamic raw) {
 if (raw is Map<String, dynamic>) return raw;
 if (raw is Map) {
 return raw.map((k, v) => MapEntry(k.toString(), v));
 }
 if (raw is List) {
 return <String, dynamic>{
 'success': false,
 'error': 'Sunucu beklenmedik bir liste yanıtı döndürdü.',
 'data': raw,
 };
 }
 if (raw is String) {
 final trimmed = raw.trim();
 // A plain "OK" / empty body is fine; everything else we treat as an
 // opaque error so the UI shows a friendly message.
 if (trimmed.isEmpty) return const {'success': true};
 return <String, dynamic>{
 'success': false,
 'error': 'Sunucu yanıtı okunamadı.',
 'raw': trimmed,
 };
 }
 return const {'success': false, 'error': 'Boş yanıt'};
 }
}
