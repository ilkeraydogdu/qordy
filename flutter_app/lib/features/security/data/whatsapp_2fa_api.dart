import 'package:dio/dio.dart';
import 'package:get_it/get_it.dart';

import '../../../config/api_config.dart';

/// Thin wrapper around the server's WhatsApp 2FA endpoints. Shares the
/// authenticated [Dio] instance so the bearer token is attached by the
/// global interceptor.
class Whatsapp2faApi {
  final Dio _dio;
  Whatsapp2faApi({Dio? dio}) : _dio = dio ?? GetIt.instance<Dio>();

  /// `{enrolled, enabled, masked_phone, globally_enabled}`
  Future<Map<String, dynamic>> status() async {
    final res = await _dio.get(ApiConfig.whatsapp2faStatus);
    return _asMap(res.data);
  }

  /// Starts enrolment: saves the phone on the server and asks Meta to
  /// deliver a 6-digit OTP via WhatsApp. Returns `{sent, masked_phone,
  /// expires_in}` on success.
  Future<Map<String, dynamic>> setup(String phone) async {
    final res = await _dio.post(
      ApiConfig.whatsapp2faSetup,
      data: {'phone': phone},
    );
    return _asMap(res.data);
  }

  /// Finalises enrolment with the code that was delivered.
  Future<Map<String, dynamic>> confirm(String code) async {
    final res =
        await _dio.post(ApiConfig.whatsapp2faConfirm, data: {'code': code});
    return _asMap(res.data);
  }

  Future<Map<String, dynamic>> disable() async {
    final res = await _dio.post(ApiConfig.whatsapp2faDisable);
    return _asMap(res.data);
  }

  /// Cross-method status + enrolment data for the Security screen. Used
  /// to hide WhatsApp/Email/SMS rows when the superadmin has them off.
  Future<Map<String, dynamic>> authMethodsStatus() async {
    final res = await _dio.get(ApiConfig.authMethodsStatus);
    return _asMap(res.data);
  }

  Map<String, dynamic> _asMap(dynamic raw) {
    if (raw is Map<String, dynamic>) return raw;
    if (raw is Map) return raw.map((k, v) => MapEntry(k.toString(), v));
    return const {'success': false, 'error': 'Geçersiz sunucu yanıtı'};
  }
}
