import 'dart:async';
import 'dart:convert';

import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';

import 'notification_prefs.dart';

/// Top-level handler required by `firebase_messaging` for background
/// isolate message delivery. Must be annotated with `@pragma('vm:entry-point')`
/// and NOT be a method of any class. We keep the logic minimal on purpose —
/// the system tray UI is already raised by FCM itself when the app is
/// backgrounded, so here we just log for observability.
@pragma('vm:entry-point')
Future<void> qordyFirebaseBackgroundHandler(RemoteMessage message) async {
  if (kDebugMode) {
    debugPrint(
      'PushService[bg] ${message.messageId} '
      'title=${message.notification?.title} data=${message.data}',
    );
  }
}

/// Central wrapper around Firebase Cloud Messaging + the local-notification
/// fallback used to surface foreground pushes on Android.
///
/// The class is deliberately tolerant of a missing Firebase configuration
/// (no `google-services.json`, no `firebase_options.dart`) — in that case
/// we log a warning and degrade silently so the app still launches. Once
/// the operator drops the proper config in, the same code path picks it up
/// on the next cold-start.
///
/// Responsibilities:
///   * Request the Android 13+ `POST_NOTIFICATIONS` runtime permission.
///   * Create the default Android notification channel used by
///     `flutter_local_notifications` (sound + high importance).
///   * Retrieve the FCM device token and hand it to [onTokenReady] so the
///     caller can ship it to the backend.
///   * Surface foreground messages as local notifications (FCM only shows
///     the system tray notification when the app is backgrounded).
class PushService {
  PushService._();

  static final PushService instance = PushService._();

  final FlutterLocalNotificationsPlugin _local =
      FlutterLocalNotificationsPlugin();

  /// Default Android channel. Must match the `channelId` used when
  /// creating notifications below, and also whatever `android/channel_id`
  /// the FCM payload sets for background/system-tray delivery.
  static const AndroidNotificationChannel _defaultChannel =
      AndroidNotificationChannel(
    'qordy_default',
    'Qordy Bildirimleri',
    description: 'Siparişler, mutfak uyarıları ve sistem bildirimleri.',
    importance: Importance.high,
    playSound: true,
    enableVibration: true,
  );

  bool _initialized = false;
  String? _currentToken;

  /// Optional callback fired whenever a new FCM token is available.
  /// Register this before calling [initialize] so the caller can forward
  /// the token to the backend `/api/mobile/notifications/register-token`.
  void Function(String token)? onTokenReady;

  /// Optional callback for foreground messages. Useful if the UI wants to
  /// refresh an in-app list (e.g. order tickets) in addition to the local
  /// notification popup we raise.
  void Function(RemoteMessage message)? onForegroundMessage;

  /// Optional callback fired when the user taps a notification. Receives
  /// the payload map (either from FCM `data` or the local notification
  /// payload) so the navigator can deep-link (e.g. `/orders/123`).
  void Function(Map<String, dynamic> payload)? onNotificationTap;

  /// Kick everything off. Safe to call multiple times.
  Future<void> initialize() async {
    if (_initialized) return;
    _initialized = true;

    try {
      await _initLocalNotifications();
      await _initFirebase();
    } catch (e, st) {
      debugPrint('PushService.initialize failed: $e');
      debugPrintStack(stackTrace: st);
    }
  }

  Future<void> _initLocalNotifications() async {
    const androidInit = AndroidInitializationSettings('@mipmap/ic_launcher');
    const settings = InitializationSettings(android: androidInit);
    await _local.initialize(
      settings,
      onDidReceiveNotificationResponse: (response) {
        _handleTapPayload(response.payload);
      },
    );

    final androidImpl = _local.resolvePlatformSpecificImplementation<
        AndroidFlutterLocalNotificationsPlugin>();
    await androidImpl?.createNotificationChannel(_defaultChannel);
    // Android 13+ needs an explicit runtime prompt. On earlier versions
    // the permission is granted at install time so this is a no-op.
    await androidImpl?.requestNotificationsPermission();
  }

  void _handleTapPayload(String? payload) {
    if (payload == null || payload.isEmpty) return;
    try {
      final decoded = jsonDecode(payload);
      if (decoded is Map) {
        onNotificationTap?.call(Map<String, dynamic>.from(decoded));
      }
    } catch (e) {
      if (kDebugMode) {
        debugPrint('PushService: payload decode failed — $e');
      }
    }
  }

  Future<void> _initFirebase() async {
    try {
      await Firebase.initializeApp();
    } catch (e) {
      debugPrint(
        'PushService: Firebase.initializeApp failed — '
        'push disabled (add google-services.json to enable). $e',
      );
      return;
    }

    final messaging = FirebaseMessaging.instance;

    // iOS/macOS also need an explicit permission prompt; the call is a
    // no-op on Android but we keep it for symmetry when we ship iOS later.
    await messaging.requestPermission(
      alert: true,
      badge: true,
      sound: true,
      provisional: false,
    );

    // Android 8+ foreground presentation: let the system show the heads-up
    // banner even when the app has focus (mirrors iOS behaviour).
    await messaging.setForegroundNotificationPresentationOptions(
      alert: true,
      badge: true,
      sound: true,
    );

    // Register the background isolate handler. Must be done before any
    // awaits that could yield to the platform channel.
    FirebaseMessaging.onBackgroundMessage(qordyFirebaseBackgroundHandler);

    // Foreground message handler: raise a local notification so the user
    // actually sees something (stock Firebase behaviour is to swallow
    // foreground messages on Android).
    FirebaseMessaging.onMessage.listen((message) {
      onForegroundMessage?.call(message);
      final type = message.data['type']?.toString();
      if (!NotificationPrefs.instance.shouldShow(type)) {
        return;
      }
      _showLocalNotification(message);
    });

    // User tapped a system-tray notification while the app was
    // backgrounded or terminated → resume + deep-link via payload.
    FirebaseMessaging.onMessageOpenedApp.listen((message) {
      onNotificationTap?.call(
        Map<String, dynamic>.from(message.data),
      );
    });
    try {
      final initial = await messaging.getInitialMessage();
      if (initial != null) {
        // Delay slightly so the router has time to mount before we
        // deep-link; calling back synchronously from a cold start can
        // race the first frame.
        Future.delayed(const Duration(milliseconds: 400), () {
          onNotificationTap?.call(
            Map<String, dynamic>.from(initial.data),
          );
        });
      }
    } catch (e) {
      debugPrint('PushService: getInitialMessage failed — $e');
    }

    try {
      _currentToken = await messaging.getToken();
      final token = _currentToken;
      if (token != null && token.isNotEmpty) {
        onTokenReady?.call(token);
      }
    } catch (e) {
      debugPrint('PushService: getToken failed — $e');
    }

    FirebaseMessaging.instance.onTokenRefresh.listen((token) {
      _currentToken = token;
      onTokenReady?.call(token);
    });
  }

  void _showLocalNotification(RemoteMessage message) {
    final notif = message.notification;
    final title = notif?.title ?? message.data['title']?.toString() ?? 'QORDY';
    final body = notif?.body ?? message.data['body']?.toString() ?? '';
    if (title.isEmpty && body.isEmpty) return;

    final androidDetails = AndroidNotificationDetails(
      _defaultChannel.id,
      _defaultChannel.name,
      channelDescription: _defaultChannel.description,
      importance: Importance.high,
      priority: Priority.high,
      playSound: true,
      enableVibration: true,
      icon: '@mipmap/ic_launcher',
      styleInformation: BigTextStyleInformation(body),
      category: AndroidNotificationCategory.message,
    );
    final details = NotificationDetails(android: androidDetails);

    final id = message.messageId.hashCode == 0
        ? DateTime.now().millisecondsSinceEpoch.remainder(1 << 31)
        : message.messageId.hashCode.abs();

    _local.show(
      id,
      title,
      body,
      details,
      payload: jsonEncode(message.data),
    );
  }

  /// Fire a local notification directly — used by the in-app polling
  /// fallback when new notifications arrive while the app is foregrounded
  /// and we don't have an FCM message to piggy-back on.
  Future<void> showLocal({
    required String title,
    required String body,
    int? id,
    String? type,
    Map<String, dynamic>? payload,
  }) async {
    final prefs = NotificationPrefs.instance;
    if (!prefs.shouldShow(type)) return;
    final androidDetails = AndroidNotificationDetails(
      _defaultChannel.id,
      _defaultChannel.name,
      channelDescription: _defaultChannel.description,
      importance: Importance.high,
      priority: Priority.high,
      playSound: prefs.soundEnabled,
      enableVibration: prefs.vibrationEnabled,
      icon: '@mipmap/ic_launcher',
      styleInformation: BigTextStyleInformation(body),
      category: AndroidNotificationCategory.message,
    );
    final details = NotificationDetails(android: androidDetails);
    await _local.show(
      id ?? DateTime.now().millisecondsSinceEpoch.remainder(1 << 31),
      title,
      body,
      details,
      payload: payload == null ? null : jsonEncode(payload),
    );
  }

  /// Clear all currently-displayed notifications. Useful when the user
  /// opens the notifications tab so the system tray doesn't show stale
  /// badges after they've read everything in-app.
  Future<void> clearAll() async {
    try {
      await _local.cancelAll();
    } catch (_) {}
  }

  String? get currentToken => _currentToken;
}
