import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import 'app.dart';
import 'core/di/injection.dart';
import 'core/network/connectivity_service.dart';
import 'core/push/notification_prefs.dart';
import 'core/push/push_service.dart';
import 'core/security/app_integrity.dart';
import 'features/auth/cubit/auth_cubit.dart';
import 'features/auth/cubit/auth_state.dart';
import 'features/notifications/data/notifications_api.dart';

void main() async {
  // Keep `main()` intentionally small — the sooner we hit `runApp` the
  // sooner the first frame lands. Everything that's not *strictly*
  // required to render the first splash/loader is pushed to
  // `addPostFrameCallback` or into the Cubit layer.
  WidgetsFlutterBinding.ensureInitialized();

  // Make the status bar transparent. Everything else (nav bar color,
  // icon brightness) is driven from `AnnotatedRegion` inside
  // `QordyApp` so it follows light/dark mode correctly, rather than
  // freezing a light palette at cold-start.
  SystemChrome.setSystemUIOverlayStyle(
    const SystemUiOverlayStyle(
      statusBarColor: Colors.transparent,
    ),
  );

  // Dependencies MUST be ready before runApp, because the widget tree
  // resolves Cubits out of GetIt synchronously on mount.
  await setupDependencies();

  // App integrity self-check. Runs in release mode only and will set a
  // trip-flag on the cubit layer; UI falls back to a hardened block
  // screen via SessionGuard if the check fails (rooted device, debug
  // build loaded at runtime, etc.). Fire-and-forget — the probe itself
  // is cheap but we never want it to gate the first frame.
  unawaited(AppIntegrity.instance.runChecks());

  // Kick off connectivity probes immediately so the offline gate has a
  // real value on the first frame instead of flashing the "online"
  // optimistic default.
  unawaited(ConnectivityService.instance.initialize());

  unawaited(NotificationPrefs.instance.ensureLoaded());

  runApp(const QordyApp());

  // Orientation + push init are intentionally deferred: they don't affect
  // the first frame, and on Android they each involve a platform channel
  // round-trip that was adding ~150-250ms to cold start.
  WidgetsBinding.instance.addPostFrameCallback((_) async {
    // Telefon + tablet: dikey ve yatay (KDS / POS tablet kullanımı).
    await SystemChrome.setPreferredOrientations([
      DeviceOrientation.portraitUp,
      DeviceOrientation.portraitDown,
      DeviceOrientation.landscapeLeft,
      DeviceOrientation.landscapeRight,
    ]);
    _initPush();
  });
}

void _initPush() {
  final auth = getIt<AuthCubit>();
  final notifApi = NotificationsApi();

  Future<void> register(String token) async {
    if (auth.state is! Authenticated) return;
    try {
      await notifApi.registerPushToken(token: token);
    } catch (_) {
      // Token registration is best-effort; a failure here shouldn't
      // block the rest of the app from using its other channels.
    }
  }

  PushService.instance.onTokenReady = register;
  PushService.instance.initialize();

  // Once the user logs in (or re-hydrates a stored session), push the
  // cached token. The service itself caches the most recent token in
  // memory so this is cheap.
  auth.stream.listen((state) {
    final token = PushService.instance.currentToken;
    if (state is Authenticated && token != null && token.isNotEmpty) {
      register(token);
    }
  });
}
