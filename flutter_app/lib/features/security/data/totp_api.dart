import 'package:dio/dio.dart';
import 'package:get_it/get_it.dart';

import '../../../config/api_config.dart';

/// Thin wrapper around the server's TOTP endpoints. Wired to the shared
/// authenticated [Dio] instance so the bearer token is applied by the
/// global [AuthInterceptor]; no extra plumbing required.
class TotpApi {
  final Dio _dio;
  TotpApi({Dio? dio}) : _dio = dio ?? GetIt.instance<Dio>();

  /// Fetches the current enrolment state. Returns a map with
  /// `{enrolled: bool, enabled: bool}`.
  Future<Map<String, dynamic>> status() async {
    final res = await _dio.get(ApiConfig.totpStatus);
    return _asMap(res.data);
  }

  /// Generates a new secret + otpauth:// URI. The returned shape is
  /// `{secret, otpauth_uri, issuer, account, digits, period}`.
  Future<Map<String, dynamic>> setup() async {
    final res = await _dio.post(ApiConfig.totpSetup);
    return _asMap(res.data);
  }

  /// Confirms the generated secret by verifying the first authenticator
  /// code. On success the server flips is_enabled=1.
  Future<Map<String, dynamic>> confirm(String code) async {
    final res = await _dio.post(ApiConfig.totpConfirm, data: {'code': code});
    return _asMap(res.data);
  }

  /// Disables TOTP. Requires the current authenticator code so a
  /// session hijack can't silently drop the second factor.
  Future<Map<String, dynamic>> disable(String code) async {
    final res = await _dio.post(ApiConfig.totpDisable, data: {'code': code});
    return _asMap(res.data);
  }

  Map<String, dynamic> _asMap(dynamic raw) {
    if (raw is Map<String, dynamic>) return raw;
    if (raw is Map) return raw.map((k, v) => MapEntry(k.toString(), v));
    return const {'success': false, 'error': 'Geçersiz sunucu yanıtı'};
  }
}
