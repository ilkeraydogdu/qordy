import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';

import '../../config/api_config.dart';
import '../error/failures.dart';
import 'api_response.dart';
import 'auth_interceptor.dart';

class ApiClient {
  late final Dio _dio;
  final AuthInterceptor _authInterceptor;

  ApiClient({required AuthInterceptor authInterceptor})
      : _authInterceptor = authInterceptor {
    _dio = Dio(
      BaseOptions(
        baseUrl: ApiConfig.baseUrl,
        connectTimeout: ApiConfig.timeout,
        receiveTimeout: ApiConfig.timeout,
        sendTimeout: ApiConfig.timeout,
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
        },
      ),
    );

    _dio.interceptors.add(_authInterceptor);

    if (kDebugMode) {
      _dio.interceptors.add(
        LogInterceptor(
          requestBody: true,
          responseBody: true,
          requestHeader: false,
          responseHeader: false,
          error: true,
          logPrint: (obj) => debugPrint(obj.toString()),
        ),
      );
    }
  }

  AuthInterceptor get authInterceptor => _authInterceptor;

  /// Direct access to the underlying [Dio] instance.
  ///
  /// Kept available for legacy API classes that still take a `Dio`
  /// in their constructor. New APIs should use the typed
  /// [get]/[post]/[put]/[delete] helpers on [ApiClient] instead.
  Dio get dio => _dio;

  Future<ApiResponse<T>> get<T>(
    String path, {
    Map<String, dynamic>? queryParameters,
    T Function(dynamic json)? fromJson,
    CancelToken? cancelToken,
  }) async {
    return _request(
      () => _dio.get(
        path,
        queryParameters: queryParameters,
        cancelToken: cancelToken,
      ),
      fromJson: fromJson,
    );
  }

  Future<ApiResponse<T>> post<T>(
    String path, {
    dynamic data,
    Map<String, dynamic>? queryParameters,
    T Function(dynamic json)? fromJson,
    CancelToken? cancelToken,
  }) async {
    return _request(
      () => _dio.post(
        path,
        data: data,
        queryParameters: queryParameters,
        cancelToken: cancelToken,
      ),
      fromJson: fromJson,
    );
  }

  Future<ApiResponse<T>> put<T>(
    String path, {
    dynamic data,
    Map<String, dynamic>? queryParameters,
    T Function(dynamic json)? fromJson,
    CancelToken? cancelToken,
  }) async {
    return _request(
      () => _dio.put(
        path,
        data: data,
        queryParameters: queryParameters,
        cancelToken: cancelToken,
      ),
      fromJson: fromJson,
    );
  }

  Future<ApiResponse<T>> delete<T>(
    String path, {
    dynamic data,
    Map<String, dynamic>? queryParameters,
    T Function(dynamic json)? fromJson,
    CancelToken? cancelToken,
  }) async {
    return _request(
      () => _dio.delete(
        path,
        data: data,
        queryParameters: queryParameters,
        cancelToken: cancelToken,
      ),
      fromJson: fromJson,
    );
  }

  Future<ApiResponse<T>> _request<T>(
    Future<Response> Function() request, {
    T Function(dynamic json)? fromJson,
  }) async {
    try {
      final response = await request();
      final responseData = response.data;

      if (responseData is Map<String, dynamic>) {
        return ApiResponse.fromJson(responseData, fromJson);
      }

      return ApiResponse.success(responseData as T);
    } on DioException catch (e) {
      return ApiResponse.failure(
        _handleDioError(e),
        statusCode: e.response?.statusCode,
      );
    } catch (e) {
      return ApiResponse.failure(e.toString());
    }
  }

  String _handleDioError(DioException error) {
    switch (error.type) {
      case DioExceptionType.connectionTimeout:
      case DioExceptionType.sendTimeout:
      case DioExceptionType.receiveTimeout:
        return const TimeoutFailure().message;

      case DioExceptionType.connectionError:
        return const NetworkFailure().message;

      case DioExceptionType.badResponse:
        return _handleStatusCode(error.response);

      case DioExceptionType.cancel:
        return 'Request was cancelled.';

      case DioExceptionType.badCertificate:
        return 'Security certificate error.';

      case DioExceptionType.unknown:
        if (error.error.toString().contains('SocketException')) {
          return const NetworkFailure().message;
        }
        return 'An unexpected error occurred.';
    }
  }

  String _handleStatusCode(Response? response) {
    final data = response?.data;
    if (data is Map<String, dynamic> && data.containsKey('error')) {
      return data['error'] as String;
    }
    if (data is Map<String, dynamic> && data.containsKey('message')) {
      return data['message'] as String;
    }

    switch (response?.statusCode) {
      case 400:
        return 'Bad request. Please check your input.';
      case 401:
        return const AuthFailure().message;
      case 403:
        return 'You do not have permission to perform this action.';
      case 404:
        return 'The requested resource was not found.';
      case 422:
        return 'Validation error. Please check your input.';
      case 429:
        return 'Too many requests. Please try again later.';
      case 500:
        return 'Server error. Please try again later.';
      case 502:
        return 'Service temporarily unavailable.';
      case 503:
        return 'Service is under maintenance.';
      default:
        return 'Something went wrong (${response?.statusCode}).';
    }
  }
}
