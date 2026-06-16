import 'package:dio/dio.dart';

import '../../../../core/constants/api_constants.dart';
import '../../../../core/error/exceptions.dart';
import '../models/login_response_model.dart';

/// Login response ve 2FA response için ortak interface.
abstract class AuthRemoteDataSource {
 Future<LoginResponseModel> login({
 required String email,
 required String password,
 required String subdomain,
 });

 Future<LoginResponseModel> verify2FA({
 required String code,
 required String method,
 });

 Future<void> logout();

 Future<LoginResponseModel?> currentSession();
}

class AuthRemoteDataSourceImpl implements AuthRemoteDataSource {
 AuthRemoteDataSourceImpl({required this.dio, required this.baseUrl});

 final Dio dio;
 final String baseUrl;

 @override
 Future<LoginResponseModel> login({
 required String email,
 required String password,
 required String subdomain,
 }) async {
 try {
 final response = await dio.post<dynamic>(
 ApiConstants.login,
 data: {
 'email': email,
 'password': password,
 'subdomain': subdomain,
 },
 );

 if (response.statusCode == 200 && response.data is Map) {
 return LoginResponseModel.fromJson(response.data as Map<String, dynamic>);
 }

 if (response.statusCode == 401 || response.statusCode == 403) {
 throw ServerException(
 statusCode: response.statusCode,
 message: 'Geçersiz e-posta veya şifre',
 body: response.data,
 );
 }

 if (response.statusCode == 422) {
 throw ServerException(
 statusCode: 422,
 message: 'Doğrulama hatası',
 body: response.data,
 );
 }

 throw ServerException(
 statusCode: response.statusCode,
 message: 'Giriş başarısız',
 body: response.data,
 );
 } on DioException catch (e) {
 if (e.type == DioExceptionType.connectionError ||
 e.type == DioExceptionType.connectionTimeout ||
 e.type == DioExceptionType.receiveTimeout) {
 throw NetworkException(cause: e);
 }
 throw ServerException(
 statusCode: e.response?.statusCode,
 message: e.message ?? 'Ağ hatası',
 body: e.response?.data,
 );
 }
 }

 @override
 Future<LoginResponseModel> verify2FA({
 required String code,
 required String method,
 }) async {
 try {
 final response = await dio.post<dynamic>(
 ApiConstants.twoFactorVerify,
 data: {'code': code, 'method': method},
 );

 if (response.statusCode == 200 && response.data is Map) {
 return LoginResponseModel.fromJson(response.data as Map<String, dynamic>);
 }

 throw ServerException(
 statusCode: response.statusCode,
 message: '2FA doğrulama başarısız',
 body: response.data,
 );
 } on DioException catch (e) {
 throw NetworkException(cause: e);
 }
 }

 @override
 Future<void> logout() async {
 try {
 await dio.post<dynamic>(ApiConstants.logout);
 } on DioException catch (_) {
 // Logout network hatası kritik değil.
 }
 }

 @override
 Future<LoginResponseModel?> currentSession() async {
 // verify-token endpoint'i mevcut token'ı kontrol eder.
 try {
 final response = await dio.post<dynamic>(ApiConstants.verifyToken);
 if (response.statusCode == 200 && response.data is Map) {
 return LoginResponseModel.fromJson(response.data as Map<String, dynamic>);
 }
 return null;
 } on DioException catch (_) {
 return null;
 }
 }
}
