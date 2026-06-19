import 'package:qordy_app/features/notifications/data/notifications_api.dart';
import 'package:qordy_app/models/notification_model.dart';

class NotificationsRepository {
  final NotificationsApi _api;

  NotificationsRepository({NotificationsApi? api})
      : _api = api ?? NotificationsApi();

  Future<List<AppNotification>> getNotifications() async {
    final response = await _api.getNotifications();
    final data = response['data'];

    List<dynamic>? list;
    if (data is List) {
      list = data;
    } else if (data is Map) {
      final nested = data['notifications'] ?? data['items'] ?? data['list'];
      if (nested is List) list = nested;
    } else if (response['notifications'] is List) {
      list = response['notifications'] as List;
    }

    if (list == null) return const [];

    return list
        .whereType<Object>()
        .map((e) {
          if (e is Map<String, dynamic>) return e;
          if (e is Map) return e.map((k, v) => MapEntry(k.toString(), v));
          return null;
        })
        .whereType<Map<String, dynamic>>()
        .map(AppNotification.fromJson)
        .toList(growable: false);
  }

  Future<bool> markAsRead(String notificationId) async {
    final response = await _api.markAsRead(notificationId);
    return response['success'] == true;
  }

  Future<bool> markAllAsRead() async {
    final response = await _api.markAllAsRead();
    return response['success'] == true;
  }
}
