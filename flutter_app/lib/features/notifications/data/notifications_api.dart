import 'package:dio/dio.dart';
import 'package:get_it/get_it.dart';
import 'package:qordy_app/config/api_config.dart';
import 'package:qordy_app/core/network/safe_json.dart';

class NotificationsApi {
  final Dio _dio = GetIt.instance<Dio>();

  Future<Map<String, dynamic>> getNotifications() async {
    final response = await _dio.get(ApiConfig.notifications);
    return asJsonMap(response.data);
  }

  Future<Map<String, dynamic>> markAsRead(String notificationId) async {
    // Backend expects snake_case `notification_id`; previous build
    // sent camelCase and the request silently returned 400 so the
    // badge never decreased. Send both for resilience.
    final response = await _dio.post(
      ApiConfig.notificationsRead,
      data: {
        'notification_id': notificationId,
        'notificationId': notificationId,
      },
    );
    return asJsonMap(response.data);
  }

  Future<Map<String, dynamic>> markAllAsRead() async {
    final response = await _dio.post(ApiConfig.notificationsReadAll);
    return asJsonMap(response.data);
  }

  /// Register (or refresh) the device's FCM token with the backend so the
  /// server can target push notifications. Safe to call repeatedly; the
  /// server upserts by `device_id`.
  Future<Map<String, dynamic>> registerPushToken({
    required String token,
    String? deviceId,
    String platform = 'android',
  }) async {
    final response = await _dio.post(
      ApiConfig.notificationsRegisterToken,
      data: {
        'token': token,
        if (deviceId != null && deviceId.isNotEmpty) 'device_id': deviceId,
        'platform': platform,
      },
    );
    return asJsonMap(response.data);
  }
}
