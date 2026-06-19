import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:qordy_app/core/security/secure_storage.dart';
import 'package:qordy_app/features/auth/data/auth_api.dart';
import 'package:qordy_app/models/business.dart';
import 'package:qordy_app/models/user.dart';

class AuthRepository {
  final AuthApi _api;
  final FlutterSecureStorage _storage;

  static const _tokenKey = 'auth_token';
  static const _refreshTokenKey = 'refresh_token';
  static const _userKey = 'auth_user';
  static const _businessKey = 'auth_business';

  String? _token;
  User? _user;
  Business? _business;

  AuthRepository({
    AuthApi? api,
    FlutterSecureStorage? storage,
  })  : _api = api ?? AuthApi(),
        _storage = storage ?? secureStorage;

  bool get isAuthenticated => _token != null && _user != null;
  User? get currentUser => _user;
  Business? get currentBusiness => _business;
  String? get token => _token;

  Future<void> initialize() async {
    _token = await _storage.read(key: _tokenKey);
    final userJson = await _storage.read(key: _userKey);
    final businessJson = await _storage.read(key: _businessKey);

    if (userJson != null) {
      final decoded = _coerceMap(jsonDecode(userJson));
      if (decoded != null) _user = User.fromJson(decoded);
    }
    if (businessJson != null) {
      final decoded = _coerceMap(jsonDecode(businessJson));
      if (decoded != null) _business = Business.fromJson(decoded);
    }

    if (_token != null) {
      try {
        final response = await _api.verifyToken(_token!);
        if (response['success'] == true && response['data'] != null) {
          final data = _coerceMap(response['data']);
          if (data == null) {
            await _clearStorage();
            return;
          }
          final userMap = _coerceMap(data['user']);
          if (userMap != null) {
            _user = User.fromJson(userMap);
            await _storage.write(key: _userKey, value: jsonEncode(_user!.toJson()));
          }
          final businessMap = _coerceMap(data['business']);
          if (businessMap != null) {
            _business = Business.fromJson(businessMap);
            await _storage.write(
                key: _businessKey, value: jsonEncode(_business!.toJson()));
          }
        } else {
          await _clearStorage();
        }
      } catch (_) {
        await _clearStorage();
      }
    }
  }

  Future<Map<String, dynamic>> validateSubdomain(String subdomain) async {
    return _api.validateSubdomain(subdomain);
  }

  Future<Map<String, dynamic>> staffLogin(
      String pin, String subdomain) async {
    final response = await _api.staffLogin(pin, subdomain);
    if (response['success'] == true && !_isTwoFactorChallenge(response)) {
      await _handleLoginResponse(response);
    }
    return response;
  }

  Future<Map<String, dynamic>> validateManagerEmail(String email) async {
    return _api.validateManagerEmail(email);
  }

  Future<Map<String, dynamic>> managerLogin(
      String email, String password) async {
    final response = await _api.managerLogin(email, password);
    if (response['success'] == true && !_isTwoFactorChallenge(response)) {
      await _handleLoginResponse(response);
    }
    return response;
  }

  /// A `requires_2fa` response means the password/PIN checked out but
  /// the server is holding back the bearer until the TOTP code is
  /// verified. We must NOT persist the half-authenticated state, the
  /// client needs a clean token-less slate so the challenge screen can
  /// recover from a mid-flow crash without leaving stale session data.
  bool _isTwoFactorChallenge(Map<String, dynamic> response) {
    final data = _coerceMap(response['data']);
    return data != null && data['requires_2fa'] == true;
  }

  Future<Map<String, dynamic>> registerBusiness(
      Map<String, dynamic> data) async {
    final response = await _api.registerBusiness(data);
    // 7 günlük trial + auto-login: backend token + user + business + subscription döndürüyor.
    if (response['success'] == true) {
      try {
        await _handleLoginResponse(response);
      } catch (e, st) {
        // Do NOT swallow silently: a successful register whose session
        // persistence fails leaves the user staring at "hoş geldin"
        // while the app still thinks they are logged out. Propagate so
        // the caller can show a "lütfen tekrar giriş yapın" message.
        debugPrint('AuthRepository.registerBusiness: '
            '_handleLoginResponse failed: $e\n$st');
        rethrow;
      }
    }
    return response;
  }

  Future<void> logout() async {
    try {
      await _api.logout(_token);
    } catch (_) {}
    await _clearStorage();
  }

  /// Proxy to `/api/mobile/subscription/status`. Used by AuthCubit to
  /// hydrate the paywall gate right after login and after a successful
  /// in-app purchase. Returns the raw envelope so the caller can check
  /// `response['success']` before parsing `response['data']`.
  Future<Map<String, dynamic>> getSubscriptionStatus() =>
      _api.getSubscriptionStatus();

  /// Exchanges a TOTP challenge token (issued during login) for a real
  /// auth bundle. Mirrors [staffLogin]/[managerLogin] on success — the
  /// access token, user and business are all persisted transparently so
  /// the AuthCubit only has to branch on the returned [success] flag.
  Future<Map<String, dynamic>> verify2FAChallenge({
    required String challengeToken,
    required String code,
    String method = 'totp',
  }) async {
    final response = await _api.verify2FAChallenge(
      challengeToken: challengeToken,
      code: code,
      method: method,
    );
    if (response['success'] == true) {
      await _handleLoginResponse(response);
    }
    return response;
  }

  /// Ask the server to deliver a one-time code via the chosen channel
  /// (WhatsApp / Email / SMS). TOTP does not need this call.
  Future<Map<String, dynamic>> send2FAChallengeCode({
    required String challengeToken,
    required String method,
  }) {
    return _api.send2FAChallengeCode(
      challengeToken: challengeToken,
      method: method,
    );
  }

  Future<void> _handleLoginResponse(Map<String, dynamic> response) async {
    final data = _coerceMap(response['data']);
    if (data == null) return;

    final token = data['token']?.toString();
    if (token != null && token.isNotEmpty) {
      _token = token;
      await _storage.write(key: _tokenKey, value: _token);
    }

    // If the backend eventually starts issuing a refresh token alongside
    // the access token, persist it transparently so the interceptor layer
    // can pick it up without another repo change.
    final refreshToken = data['refresh_token']?.toString();
    if (refreshToken != null && refreshToken.isNotEmpty) {
      await _storage.write(key: _refreshTokenKey, value: refreshToken);
    }

    final userMap = _coerceMap(data['user']);
    if (userMap != null) {
      _user = User.fromJson(userMap);
      await _storage.write(key: _userKey, value: jsonEncode(_user!.toJson()));
    }

    final businessMap = _coerceMap(data['business']);
    if (businessMap != null) {
      _business = Business.fromJson(businessMap);
      await _storage.write(
          key: _businessKey, value: jsonEncode(_business!.toJson()));
    }
  }

  /// Converts any `Map` shape coming off the wire to a `Map<String, dynamic>`
  /// without throwing when the backend sends an unexpected type (e.g. a
  /// `List` for `permissions`, `null`, or a nested Map whose keys happen
  /// to be `Object`). Returns `null` if the value isn't map-shaped.
  Map<String, dynamic>? _coerceMap(dynamic v) {
    if (v is Map<String, dynamic>) return v;
    if (v is Map) return v.map((k, val) => MapEntry(k.toString(), val));
    return null;
  }

  Future<void> _clearStorage() async {
    _token = null;
    _user = null;
    _business = null;
    await _storage.delete(key: _tokenKey);
    await _storage.delete(key: _refreshTokenKey);
    await _storage.delete(key: _userKey);
    await _storage.delete(key: _businessKey);
  }
}
