import 'dart:async';

import 'package:dio/dio.dart';

import '../constants/api_constants.dart';
import '../storage/secure_storage.dart';

/// Bearer token ekler + 401'de otomatik refresh yapar.
///
/// Refresh sırasında sonsuz döngüyü önlemek için `_isRefreshing` flag'i
/// ve aynı anda gelen 401'leri tek bir refresh çağrısında toplar.
class AuthInterceptor extends Interceptor {
 AuthInterceptor({
 required this.storage,
 required this.refreshDio,
 this.onUnauthenticated,
 });

 final SecureStorageService storage;
 final Dio refreshDio;
 final Future<void> Function()? onUnauthenticated;

 bool _isRefreshing = false;
 final List<Completer<String>> _waiters = <Completer<String>>[];

 @override
 Future<void> onRequest(
 RequestOptions options,
 RequestInterceptorHandler handler,
 ) async {
 // Auth endpoint'lerine token ekleme.
 if (_isAuthEndpoint(options.path)) {
 return handler.next(options);
 }

 final token = await storage.readAccessToken();
 if (token != null && token.isNotEmpty) {
 options.headers['Authorization'] = 'Bearer $token';
 }
 handler.next(options);
 }

 @override
 Future<void> onResponse(
 Response<dynamic> response,
 ResponseInterceptorHandler handler,
 ) async {
 if (response.statusCode == 401 &&
 !_isAuthEndpoint(response.requestOptions.path)) {
 final newToken = await _refreshToken();
 if (newToken != null) {
 // Eski isteği yeni token ile tekrar dene.
 final retryOptions = response.requestOptions;
 retryOptions.headers['Authorization'] = 'Bearer $newToken';
 try {
 final retried = await refreshDio.fetch<dynamic>(retryOptions);
 return handler.resolve(retried);
 } catch (e) {
 // Retry başarısız — fall through.
 }
 }
 // Refresh başarısız veya retry başarısız → oturumu kapat.
 if (onUnauthenticated != null) {
 await onUnauthenticated!();
 }
 return handler.next(response);
 }
 handler.next(response);
 }

 @override
 Future<void> onError(
 DioException err,
 ErrorInterceptorHandler handler,
 ) async {
 if (err.response?.statusCode == 401 &&
 !_isAuthEndpoint(err.requestOptions.path)) {
 final newToken = await _refreshToken();
 if (newToken != null) {
 final retryOptions = err.requestOptions;
 retryOptions.headers['Authorization'] = 'Bearer $newToken';
 try {
 final retried = await refreshDio.fetch<dynamic>(retryOptions);
 return handler.resolve(retried);
 } catch (_) {
 // ignore
 }
 }
 if (onUnauthenticated != null) {
 await onUnauthenticated!();
 }
 }
 handler.next(err);
 }

 bool _isAuthEndpoint(String path) {
 return path.contains('/manager/login') ||
 path.contains('/refresh-token') ||
 path.contains('/auth/2fa/') ||
 path.contains('/verify-token') ||
 path.contains('/validate-subdomain');
 }

 Future<String?> _refreshToken() async {
 if (_isRefreshing) {
 // Zaten refresh sürüyor — sıraya gir.
 final completer = Completer<String>();
 _waiters.add(completer);
 return completer.future;
 }

 _isRefreshing = true;
 try {
 final refresh = await storage.readRefreshToken();
 if (refresh == null || refresh.isEmpty) {
 return null;
 }

 final response = await refreshDio.post<dynamic>(
 ApiConstants.refreshToken,
 data: {'refresh_token': refresh},
 );

 if (response.statusCode == 200 && response.data is Map) {
 final data = response.data as Map<String, dynamic>;
 final newAccess = data['access_token'] as String?;
 final newRefresh = data['refresh_token'] as String?;
 if (newAccess != null) {
 await storage.writeAccessToken(newAccess);
 if (newRefresh != null) {
 await storage.writeRefreshToken(newRefresh);
 }
 // Tüm bekleyen isteklere yeni token'ı dağıt.
 for (final w in _waiters) {
 w.complete(newAccess);
 }
 _waiters.clear();
 return newAccess;
 }
 }

 // Refresh başarısız — tüm bekleyenleri hata ile bitir.
 for (final w in _waiters) {
 w.complete('');
 }
 _waiters.clear();
 return null;
 } finally {
 _isRefreshing = false;
 }
 }
}
