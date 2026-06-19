import 'dart:async';
import 'dart:developer' as developer;

import 'package:dio/dio.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:qordy_app/core/push/push_service.dart';
import 'package:qordy_app/features/notifications/cubit/notifications_state.dart';
import 'package:qordy_app/features/notifications/data/notifications_repository.dart';

class NotificationsCubit extends Cubit<NotificationsState> {
  final NotificationsRepository _repository;
  Timer? _pollTimer;
  final Set<String> _seenIds = <String>{};
  bool _primed = false;

  NotificationsCubit({required NotificationsRepository repository})
      : _repository = repository,
        super(const NotificationsInitial());

  @override
  Future<void> close() async {
    _pollTimer?.cancel();
    return super.close();
  }

  /// Start a lightweight polling loop that detects newly-arrived
  /// notifications while the app is foregrounded. Each new item fires a
  /// local system notification via [PushService] so the waiter/kitchen
  /// staff gets an audible cue even when FCM isn't (yet) configured.
  void startPolling({Duration interval = const Duration(seconds: 20)}) {
    _pollTimer?.cancel();
    _pollTimer = Timer.periodic(interval, (_) => _poll());
    _poll();
  }

  void stopPolling() {
    _pollTimer?.cancel();
    _pollTimer = null;
  }

  Future<void> _poll() async {
    try {
      final notifications = await _repository.getNotifications();
      // On the very first poll we only snapshot the ids so that existing
      // unread items don't retroactively buzz the device.
      if (!_primed) {
        _seenIds
          ..clear()
          ..addAll(notifications
              .map((n) => n.notificationId)
              .whereType<String>());
        _primed = true;
      } else {
        for (final n in notifications) {
          final id = n.notificationId;
          if (id == null) continue;
          if (_seenIds.add(id) && n.isRead != true) {
            PushService.instance.showLocal(
              title: (n.title ?? '').isNotEmpty ? n.title! : 'QORDY',
              body: n.message ?? '',
              type: n.type,
              id: id.hashCode.abs(),
            );
          }
        }
      }
      if (!isClosed) {
        emit(NotificationsLoaded(notifications: notifications));
      }
    } catch (e, st) {
      // Don't clobber a good `NotificationsLoaded` state — a flaky
      // network shouldn't replace the UI with an error screen. But we
      // do want the failure in the log so support can diagnose "badge
      // stuck at 0" reports instead of staring at nothing.
      developer.log(
        'Notifications poll failed',
        name: 'NotificationsCubit',
        error: e,
        stackTrace: st,
      );
      // If we never got a successful poll, surface the error to the UI
      // so the user sees "Bildirimler yüklenemedi" instead of an
      // eternal spinner.
      if (!_primed && !isClosed) {
        emit(NotificationsError(
          e is DioException ? _extractError(e) : 'Bildirimler yüklenemedi',
        ));
      }
    }
  }

  int get unreadCount {
    if (state is NotificationsLoaded) {
      return (state as NotificationsLoaded).unreadCount;
    }
    return 0;
  }

  Future<void> loadNotifications() async {
    emit(const NotificationsLoading());
    try {
      final notifications = await _repository.getNotifications();
      emit(NotificationsLoaded(notifications: notifications));
    } on DioException catch (e) {
      emit(NotificationsError(_extractError(e)));
    } catch (e) {
      emit(NotificationsError(e.toString()));
    }
  }

  Future<void> markRead(String notificationId) async {
    if (state is! NotificationsLoaded) return;

    final current = state as NotificationsLoaded;
    final updated = current.notifications.map((n) {
      if (n.notificationId == notificationId) {
        return n.copyWith(isRead: true);
      }
      return n;
    }).toList();

    emit(NotificationsLoaded(notifications: updated));

    try {
      await _repository.markAsRead(notificationId);
    } catch (_) {
      emit(NotificationsLoaded(notifications: current.notifications));
    }
  }

  Future<void> markAllRead() async {
    if (state is! NotificationsLoaded) return;

    final current = state as NotificationsLoaded;
    final updated = current.notifications
        .map((n) => n.copyWith(isRead: true))
        .toList();

    emit(NotificationsLoaded(notifications: updated));

    try {
      await _repository.markAllAsRead();
    } catch (_) {
      emit(NotificationsLoaded(notifications: current.notifications));
    }
  }

  String _extractError(DioException e) {
    final data = e.response?.data;
    if (data is Map<String, dynamic>) {
      return data['error'] as String? ??
          data['message'] as String? ??
          'Bir hata oluştu';
    }
    if (e.type == DioExceptionType.connectionTimeout ||
        e.type == DioExceptionType.receiveTimeout) {
      return 'Bağlantı zaman aşımına uğradı';
    }
    if (e.type == DioExceptionType.connectionError) {
      return 'İnternet bağlantınızı kontrol edin';
    }
    return 'Bir hata oluştu';
  }
}
