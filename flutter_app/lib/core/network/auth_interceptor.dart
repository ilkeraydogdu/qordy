import 'dart:async';

import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import '../../config/api_config.dart';
import '../security/secure_storage.dart' as ss;

/// Attaches the bearer token to every outgoing request and handles 401
/// responses by transparently attempting to refresh the access token
/// exactly once per burst of concurrent failures (singleflight).
///
/// Behaviour on 401:
///   1. The first caller that observes a 401 tries to refresh the token
///      by calling [ApiConfig.refreshToken] with the stored refresh
///      token.
///   2. Any other requests that 401 while the refresh is in flight await
///      the same [Completer] instead of firing N parallel refreshes.
///   3. On success, the failed request is retried once with the new
///      access token.
///   4. On failure, all pending tokens are cleared and subscribers on
///      [unauthorizedStream] are notified so the UI can redirect to the
///      login flow.
class AuthInterceptor extends Interceptor {
  final FlutterSecureStorage _storage;

  static const String _tokenKey = 'auth_token';
  static const String _refreshTokenKey = 'refresh_token';
  static const String _userKey = 'auth_user';
  static const String _businessKey = 'auth_business';

  /// Marker attached to [RequestOptions.extra] after a retry so we don't
  /// accidentally loop forever when the server keeps returning 401.
  static const String _retriedKey = '_qordy_auth_retried';

  /// Broadcast stream notifying listeners (e.g. `AuthCubit`) when the
  /// backend responds with 401 on a Bearer-authenticated call AND the
  /// interceptor could not transparently refresh the session.
  static final _unauthorizedController =
      StreamController<UnauthorizedReason>.broadcast();
  static Stream<UnauthorizedReason> get unauthorizedStream =>
      _unauthorizedController.stream;

  /// Singleflight lock: whenever a refresh is in flight, every other
  /// 401-observer awaits this Completer instead of launching their own
  /// refresh.
  Completer<String?>? _refreshInFlight;

  /// Dio used strictly for the refresh call. Kept separate so it bypasses
  /// the interceptor chain and cannot trigger another refresh attempt.
  late final Dio _refreshClient = Dio(
    BaseOptions(
      baseUrl: ApiConfig.baseUrl,
      connectTimeout: ApiConfig.timeout,
      receiveTimeout: ApiConfig.timeout,
      sendTimeout: ApiConfig.timeout,
      headers: const {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
    ),
  );

  AuthInterceptor({FlutterSecureStorage? storage})
      : _storage = storage ?? ss.secureStorage;

  @override
  void onRequest(
    RequestOptions options,
    RequestInterceptorHandler handler,
  ) async {
    final token = await _storage.read(key: _tokenKey);
    if (token != null && token.isNotEmpty) {
      options.headers['Authorization'] = 'Bearer $token';
    }
    options.headers['Accept'] = 'application/json';
    options.headers.putIfAbsent('Content-Type', () => 'application/json');
    handler.next(options);
  }

  @override
  void onError(DioException err, ErrorInterceptorHandler handler) async {
    final response = err.response;
    final status = response?.statusCode;

    // 403 with a known terminal code (pasif kullanıcı / askıya alınmış
    // işletme) should immediately log the user out — no refresh will
    // rescue these; the backend explicitly rejected us.
    if (status == 403) {
      final code = _extractCode(response?.data);
      if (code == 'USER_INACTIVE' || code == 'TENANT_SUSPENDED') {
        await _failOut(
          code == 'TENANT_SUSPENDED'
              ? UnauthorizedReason.tenantSuspended
              : UnauthorizedReason.userInactive,
        );
      }
      handler.next(err);
      return;
    }

    if (status != 401) {
      handler.next(err);
      return;
    }

    final options = err.requestOptions;

    // Never try to refresh the refresh endpoint itself or already-retried
    // requests — otherwise a broken refresh endpoint would loop.
    final isRefreshCall = options.path.contains(ApiConfig.refreshToken);
    final alreadyRetried = options.extra[_retriedKey] == true;
    if (isRefreshCall || alreadyRetried) {
      await _failOut(UnauthorizedReason.sessionExpired);
      handler.next(err);
      return;
    }

    // Backend can explicitly mark a token as revoked; in that case there's
    // no point attempting a refresh — just fail fast.
    final authCode = _extractCode(response?.data);
    if (authCode == 'TOKEN_REVOKED') {
      await _failOut(UnauthorizedReason.sessionRevoked);
      handler.next(err);
      return;
    }

    final refreshToken = await _storage.read(key: _refreshTokenKey);
    if (refreshToken == null || refreshToken.isEmpty) {
      await _failOut(UnauthorizedReason.sessionExpired);
      handler.next(err);
      return;
    }

    try {
      final newAccessToken = await _refreshAccessToken(refreshToken);
      if (newAccessToken == null || newAccessToken.isEmpty) {
        await _failOut(UnauthorizedReason.sessionExpired);
        handler.next(err);
        return;
      }

      // Retry the original request with the new bearer, on a plain Dio
      // so we don't re-enter the interceptor and re-read the old token.
      final retryOptions = Options(
        method: options.method,
        headers: {
          ...options.headers,
          'Authorization': 'Bearer $newAccessToken',
        },
        contentType: options.contentType,
        responseType: options.responseType,
        followRedirects: options.followRedirects,
        receiveDataWhenStatusError: options.receiveDataWhenStatusError,
        extra: {...options.extra, _retriedKey: true},
      );

      final retryResponse = await _refreshClient.request<dynamic>(
        options.path.startsWith('http')
            ? options.path
            : '${ApiConfig.baseUrl}${options.path}',
        data: options.data,
        queryParameters: options.queryParameters,
        options: retryOptions,
        cancelToken: options.cancelToken,
      );
      handler.resolve(retryResponse);
    } on DioException catch (_) {
      await _failOut(UnauthorizedReason.sessionExpired);
      handler.next(err);
    } catch (e, st) {
      if (kDebugMode) {
        debugPrint('AuthInterceptor refresh failed: $e\n$st');
      }
      await _failOut(UnauthorizedReason.sessionExpired);
      handler.next(err);
    }
  }

  /// Pulls the structured `code` field out of a server error payload
  /// regardless of whether the envelope is nested under `error` / `meta`.
  static String? _extractCode(dynamic data) {
    if (data is Map) {
      final code = data['code'];
      if (code is String && code.isNotEmpty) return code;
      final inner = data['error'];
      if (inner is Map) {
        final ec = inner['code'];
        if (ec is String && ec.isNotEmpty) return ec;
      }
    }
    return null;
  }

  /// Calls the refresh endpoint — at most one concurrent call — and
  /// persists the fresh access token.
  Future<String?> _refreshAccessToken(String refreshToken) {
    final inFlight = _refreshInFlight;
    if (inFlight != null && !inFlight.isCompleted) {
      return inFlight.future;
    }

    final completer = Completer<String?>();
    _refreshInFlight = completer;

    Future(() async {
      try {
        final response = await _refreshClient.post<dynamic>(
          ApiConfig.refreshToken,
          data: {'refresh_token': refreshToken},
        );
        final data = response.data;
        if (data is Map<String, dynamic>) {
          final token = data['token'] as String? ??
              data['access_token'] as String?;
          final newRefresh = data['refresh_token'] as String?;
          if (token != null && token.isNotEmpty) {
            await saveTokens(
              token: token,
              refreshToken: newRefresh ?? refreshToken,
            );
            completer.complete(token);
            return;
          }
        }
        completer.complete(null);
      } catch (e) {
        completer.complete(null);
      } finally {
        if (identical(_refreshInFlight, completer)) {
          _refreshInFlight = null;
        }
      }
    });

    return completer.future;
  }

  Future<void> _failOut(UnauthorizedReason reason) async {
    await clearTokens();
    if (!_unauthorizedController.isClosed) {
      _unauthorizedController.add(reason);
    }
  }

  Future<String?> getToken() => _storage.read(key: _tokenKey);

  Future<String?> getRefreshToken() => _storage.read(key: _refreshTokenKey);

  Future<void> saveTokens({
    required String token,
    String? refreshToken,
  }) async {
    await _storage.write(key: _tokenKey, value: token);
    if (refreshToken != null) {
      await _storage.write(key: _refreshTokenKey, value: refreshToken);
    }
  }

  Future<void> clearTokens() async {
    await _storage.delete(key: _tokenKey);
    await _storage.delete(key: _refreshTokenKey);
    await _storage.delete(key: _userKey);
    await _storage.delete(key: _businessKey);
  }

  Future<bool> hasToken() async {
    final token = await _storage.read(key: _tokenKey);
    return token != null && token.isNotEmpty;
  }
}

/// Why the interceptor is forcing the user back to the login flow.
/// Surfaced via [AuthInterceptor.unauthorizedStream] so the UI layer
/// can pick an appropriate message (generic expiry vs admin-initiated
/// revocation vs tenant suspension).
enum UnauthorizedReason {
  sessionExpired,
  sessionRevoked,
  userInactive,
  tenantSuspended,
}
