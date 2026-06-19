import 'dart:convert';

class AppNotification {
  final String? notificationId;
  final String? title;
  final String? message;
  final String? type;
  final String? tableId;
  final String? tableName;
  final String? zoneName;
  final bool? isRead;
  final String? createdAt;
  final Map<String, dynamic>? data;

  const AppNotification({
    this.notificationId,
    this.title,
    this.message,
    this.type,
    this.tableId,
    this.tableName,
    this.zoneName,
    this.isRead,
    this.createdAt,
    this.data,
  });

  factory AppNotification.fromJson(Map<String, dynamic> json) {
    final rawData = json['data'];
    Map<String, dynamic>? data;
    if (rawData is Map<String, dynamic>) {
      data = rawData;
    } else if (rawData is Map) {
      data = Map<String, dynamic>.from(rawData);
    } else if (rawData is String && rawData.isNotEmpty) {
      try {
        final decoded = jsonDecode(rawData);
        if (decoded is Map) data = Map<String, dynamic>.from(decoded);
      } catch (_) {
        data = null;
      }
    }

    final type = json['type']?.toString();
    final tableName =
        (json['table_name'] ?? json['tableName'])?.toString();
    final zoneName =
        (json['zone_name'] ?? json['zoneName'])?.toString();

    final rawIsRead = json['is_read'] ?? json['isRead'];
    bool? isRead;
    if (rawIsRead is bool) {
      isRead = rawIsRead;
    } else if (rawIsRead is num) {
      isRead = rawIsRead != 0;
    } else if (rawIsRead is String) {
      isRead = rawIsRead == '1' || rawIsRead.toLowerCase() == 'true';
    }

    final createdAt = (json['created_at'] ??
            json['createdAt'] ??
            json['timestamp'])
        ?.toString();

    final title = json['title']?.toString() ?? _deriveTitle(type, tableName);
    final message =
        json['message']?.toString() ?? _deriveMessage(type, tableName, data);

    return AppNotification(
      notificationId:
          (json['notification_id'] ?? json['notificationId'] ?? json['id'])?.toString(),
      title: title,
      message: message,
      type: type,
      tableId: (json['table_id'] ?? json['tableId'])?.toString(),
      tableName: tableName,
      zoneName: zoneName,
      isRead: isRead,
      createdAt: createdAt,
      data: data,
    );
  }

  static String _deriveTitle(String? type, String? tableName) {
    final t = (tableName == null || tableName.isEmpty) ? 'Masa' : tableName;
    switch (type) {
      case 'CALL_WAITER':
        return '$t - Garson Çağrısı';
      case 'REQUEST_BILL':
        return '$t - Hesap Talebi';
      case 'NEW_ORDER':
        return '$t - Yeni Sipariş';
      case 'ORDER_READY':
        return '$t - Sipariş Hazır';
      case 'KITCHEN_ISSUE':
        return '$t - Mutfak Uyarısı';
      case 'CANCEL_ORDER':
        return '$t - İptal Talebi';
      case 'EDIT_APPROVAL':
      case 'ORDER_EDIT_APPROVAL':
        return '$t - Onay Bekliyor';
      case 'PAYMENT_RECEIVED':
        return '$t - Ödeme Alındı';
      case 'ORDER_SERVED':
        return '$t - Servis Edildi';
      case 'TABLE_TRANSFER':
        return '$t - Masa Transferi';
      case 'SYSTEM':
        return 'Sistem Bildirimi';
    }
    return 'Qordy Bildirimi';
  }

  static String _deriveMessage(
      String? type, String? tableName, Map<String, dynamic>? data) {
    final t = (tableName == null || tableName.isEmpty) ? 'Masa' : tableName;
    switch (type) {
      case 'CALL_WAITER':
        return '$t sizi çağırıyor.';
      case 'REQUEST_BILL':
        return '$t hesap talep etti.';
      case 'NEW_ORDER':
        return '$t yeni sipariş verdi.';
      case 'ORDER_READY':
        return '$t için sipariş hazır - teslim edebilirsiniz.';
      case 'KITCHEN_ISSUE':
        final issue = data?['issue']?.toString();
        return (issue != null && issue.isNotEmpty)
            ? issue
            : '$t için mutfaktan bildirim geldi.';
      case 'CANCEL_ORDER':
        return '$t sipariş iptali talep etti.';
      case 'EDIT_APPROVAL':
      case 'ORDER_EDIT_APPROVAL':
        return '$t için sipariş değişiklik onayı bekliyor.';
      case 'PAYMENT_RECEIVED':
        final amt = data?['amount']?.toString();
        return amt != null
            ? '$t ödemesi alındı ($amt).'
            : '$t ödemesi alındı.';
      case 'ORDER_SERVED':
        return '$t siparişi servis edildi.';
      case 'TABLE_TRANSFER':
        return '$t için masa transferi yapıldı.';
    }
    return '$t için yeni bildirim.';
  }

  Map<String, dynamic> toJson() {
    return {
      'notificationId': notificationId,
      'title': title,
      'message': message,
      'type': type,
      'tableId': tableId,
      'tableName': tableName,
      'zoneName': zoneName,
      'isRead': isRead,
      'createdAt': createdAt,
      'data': data,
    };
  }

  AppNotification copyWith({
    String? notificationId,
    String? title,
    String? message,
    String? type,
    String? tableId,
    String? tableName,
    String? zoneName,
    bool? isRead,
    String? createdAt,
    Map<String, dynamic>? data,
  }) {
    return AppNotification(
      notificationId: notificationId ?? this.notificationId,
      title: title ?? this.title,
      message: message ?? this.message,
      type: type ?? this.type,
      tableId: tableId ?? this.tableId,
      tableName: tableName ?? this.tableName,
      zoneName: zoneName ?? this.zoneName,
      isRead: isRead ?? this.isRead,
      createdAt: createdAt ?? this.createdAt,
      data: data ?? this.data,
    );
  }

  /// True when this notification represents something a waiter should
  /// act on right now (call from a table, bill request, new order).
  bool get isWaiterActionable {
    switch (type) {
      case 'CALL_WAITER':
      case 'REQUEST_BILL':
      case 'NEW_ORDER':
      case 'KITCHEN_ISSUE':
      case 'CANCEL_ORDER':
        return true;
    }
    return false;
  }

  @override
  String toString() =>
      'AppNotification(notificationId: $notificationId, type: $type, title: $title)';

  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      other is AppNotification &&
          runtimeType == other.runtimeType &&
          notificationId == other.notificationId;

  @override
  int get hashCode => notificationId.hashCode;
}
