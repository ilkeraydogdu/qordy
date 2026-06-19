import 'package:equatable/equatable.dart';
import 'package:qordy_app/models/notification_model.dart';

abstract class NotificationsState extends Equatable {
  const NotificationsState();

  @override
  List<Object?> get props => [];
}

class NotificationsInitial extends NotificationsState {
  const NotificationsInitial();
}

class NotificationsLoading extends NotificationsState {
  const NotificationsLoading();
}

class NotificationsLoaded extends NotificationsState {
  final List<AppNotification> notifications;

  const NotificationsLoaded({required this.notifications});

  int get unreadCount =>
      notifications.where((n) => n.isRead != true).length;

  List<AppNotification> get todayNotifications {
    final now = DateTime.now();
    return notifications.where((n) {
      if (n.createdAt == null) return false;
      try {
        final dt = DateTime.parse(n.createdAt!);
        return dt.year == now.year &&
            dt.month == now.month &&
            dt.day == now.day;
      } catch (_) {
        return false;
      }
    }).toList();
  }

  List<AppNotification> get yesterdayNotifications {
    final yesterday = DateTime.now().subtract(const Duration(days: 1));
    return notifications.where((n) {
      if (n.createdAt == null) return false;
      try {
        final dt = DateTime.parse(n.createdAt!);
        return dt.year == yesterday.year &&
            dt.month == yesterday.month &&
            dt.day == yesterday.day;
      } catch (_) {
        return false;
      }
    }).toList();
  }

  List<AppNotification> get olderNotifications {
    final twoDaysAgo = DateTime.now().subtract(const Duration(days: 2));
    return notifications.where((n) {
      if (n.createdAt == null) return true;
      try {
        final dt = DateTime.parse(n.createdAt!);
        return dt.isBefore(twoDaysAgo.add(const Duration(days: 1)));
      } catch (_) {
        return true;
      }
    }).toList();
  }

  @override
  List<Object?> get props => [notifications];
}

class NotificationsError extends NotificationsState {
  final String message;

  const NotificationsError(this.message);

  @override
  List<Object?> get props => [message];
}
