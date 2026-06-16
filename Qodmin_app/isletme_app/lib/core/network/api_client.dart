import 'dart:async';

import 'package:dio/dio.dart';
import 'package:http_certificate_pinning/http_certificate_pinning.dart';

import '../constants/api_constants.dart';

/// Merkezi Dio HTTP istemcisi.
///
/// Tüm feature'lar bu client'ı kullanır. Interceptor'lar burada bağlanır.
class ApiClient {
 ApiClient({
 Dio? dio,
 }) : _dio = dio ?? _buildDio();

 final Dio _dio;

 Dio get raw => _dio;

 static Dio _buildDio() {
 final dio = Dio(
 BaseOptions(
 connectTimeout: const Duration(seconds: ApiConstants.connectTimeoutSec),
 receiveTimeout: const Duration(seconds: ApiConstants.receiveTimeoutSec),
 sendTimeout: const Duration(seconds: ApiConstants.sendTimeoutSec),
 headers: {
 'Accept': 'application/json',
 'Content-Type': 'application/json',
 'X-Client': 'qordy-isletme-android',
 'X-Client-Version': '1.0.0',
 },
 responseType: ResponseType.json,
 validateStatus: (status) => status != null && status < 500,
 ),
 );
 return dio;
 }

 /// Base URL'i tenant subdomain'e göre değiştir.
 void setBaseUrl(String baseUrl) {
 _dio.options.baseUrl = baseUrl;
 }

 /// Tenant subdomain ayarla — tüm istekler artık bu tenant'a gider.
 void setTenant(String subdomain) {
 setBaseUrl(ApiConstants.tenantBaseUrl(subdomain));
 }

 /// Mevcut base URL.
 String get baseUrl => _dio.options.baseUrl;

 /// GET isteği.
 Future<Response<dynamic>> get(
 String path, {
 Map<String, dynamic>? queryParameters,
 Options? options,
 CancelToken? cancelToken,
 }) {
 return _dio.get<dynamic>(
 path,
 queryParameters: queryParameters,
 options: options,
 cancelToken: cancelToken,
 );
 }

 /// POST isteği.
 Future<Response<dynamic>> post(
 String path, {
 dynamic data,
 Map<String, dynamic>? queryParameters,
 Options? options,
 CancelToken? cancelToken,
 }) {
 return _dio.post<dynamic>(
 path,
 data: data,
 queryParameters: queryParameters,
 options: options,
 cancelToken: cancelToken,
 );
 }

 /// PUT isteği.
 Future<Response<dynamic>> put(
 String path, {
 dynamic data,
 Map<String, dynamic>? queryParameters,
 Options? options,
 CancelToken? cancelToken,
 }) {
 return _dio.put<dynamic>(
 path,
 data: data,
 queryParameters: queryParameters,
 options: options,
 cancelToken: cancelToken,
 );
 }

 /// DELETE isteği.
 Future<Response<dynamic>> delete(
 String path, {
 dynamic data,
 Map<String, dynamic>? queryParameters,
 Options? options,
 CancelToken? cancelToken,
 }) {
 return _dio.delete<dynamic>(
 path,
 data: data,
 queryParameters: queryParameters,
 options: options,
 cancelToken: cancelToken,
 );
 }

 /// PATCH isteği.
 Future<Response<dynamic>> patch(
 String path, {
 dynamic data,
 Map<String, dynamic>? queryParameters,
 Options? options,
 CancelToken? cancelToken,
 }) {
 return _dio.patch<dynamic>(
 path,
 data: data,
 queryParameters: queryParameters,
 options: options,
 cancelToken: cancelToken,
 );
 }

 /// Tüm interceptor'ları sıfırla.
 void resetInterceptors() {
 _dio.interceptors.clear();
 }

 /// Tüm interceptor'ları ekle.
 void addInterceptors(List<Interceptor> interceptors) {
 _dio.interceptors.addAll(interceptors);
 }

 /// Certificate pinning aktif — production ortamında çağrılır.
 ///
 /// SHA-256 fingerprint backend sertifikasınınki ile eşleşmeli.
 /// Geliştirme sırasında boş bırakılabilir.
 Future<void> enableCertificatePinning(List<String> allowedSha256) async {
 if (allowedSha256.isEmpty) return;
 final url = baseUrl.isEmpty ? ApiConstants.productionBaseUrl : baseUrl;
 await HttpCertificatePinning.check(
 serverURL: url,
 sha: SHA.SHA256,
 allowedSHAFingerprints: allowedSha256,
 timeout: 10,
 );
 }

 void dispose() {
 _dio.close(force: true);
 }
}
