import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';

import 'config/router.dart';
import 'config/theme.dart';
import 'core/di/injection.dart';
import 'core/network/offline_gate.dart';
import 'core/push/push_service.dart';
import 'core/security/session_guard.dart';
import 'core/theme/theme_cubit.dart';
import 'core/widgets/qordy_logo.dart';
import 'features/auth/cubit/auth_cubit.dart';
import 'features/auth/cubit/auth_state.dart';

class QordyApp extends StatefulWidget {
  const QordyApp({super.key});

  @override
  State<QordyApp> createState() => _QordyAppState();
}

class _QordyAppState extends State<QordyApp> {
  late final AuthCubit _authCubit;
  late final ThemeCubit _themeCubit;
  late final GoRouter _router;

  @override
  void initState() {
    super.initState();
    _authCubit = getIt<AuthCubit>();
    _themeCubit = getIt<ThemeCubit>();
    _router = AppRouter.router(_authCubit);
    _authCubit.initialize();
    // Load persisted theme preference (system / light / dark). This is
    // async but emits the current value synchronously first, so the
    // first paint always happens on a known mode.
    _themeCubit.load();

    // Deep-link notification taps into the correct screen. Supports two
    // conventions on the payload map:
    //   1. `route`: a pre-built path such as `/orders/ABC123`
    //   2. `type` + id fields (order_id / table_id / notification_id)
    // Either way we bounce through the already-configured GoRouter so
    // redirects (auth guard, subscription guard, etc.) still apply.
    PushService.instance.onNotificationTap = (payload) {
      final route = _resolveRouteFromPayload(payload);
      if (route == null) return;
      // Guard for terminated-state cold starts — router may not be ready
      // on the very first frame.
      WidgetsBinding.instance.addPostFrameCallback((_) {
        try {
          _router.go(route);
        } catch (_) {}
      });
    };
  }

  String? _resolveRouteFromPayload(Map<String, dynamic> payload) {
    final explicit = payload['route']?.toString();
    if (explicit != null && explicit.startsWith('/')) return explicit;

    final type = payload['type']?.toString().toLowerCase() ?? '';
    switch (type) {
      case 'order':
      case 'new_order':
      case 'order_ready':
      case 'order_status':
        // The mobile no longer ships a per-order detail screen —
        // the personnel feature set was retired. Land the tap on
        // the manager dashboard.
        return '/dashboard';
      case 'kitchen':
        return '/dashboard';
      case 'waiter':
        return '/dashboard';
      case 'preparation':
        return '/dashboard';
      case 'notification':
      case 'system':
        return '/notifications';
    }
    return '/notifications';
  }

  @override
  void dispose() {
    _authCubit.close();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return MultiBlocProvider(
      providers: [
        BlocProvider<AuthCubit>.value(value: _authCubit),
        BlocProvider<ThemeCubit>.value(value: _themeCubit),
      ],
      child: BlocBuilder<ThemeCubit, AppThemeMode>(
        builder: (context, themeMode) {
          return MaterialApp.router(
            title: 'QORDY',
            debugShowCheckedModeBanner: false,
            theme: AppTheme.light,
            darkTheme: AppTheme.dark,
            themeMode: themeMode.materialMode,
            routerConfig: _router,
            builder: (context, child) {
              final isDark = Theme.of(context).brightness == Brightness.dark;
              return AnnotatedRegion<SystemUiOverlayStyle>(
                value: SystemUiOverlayStyle(
                  statusBarColor: Colors.transparent,
                  statusBarIconBrightness:
                      isDark ? Brightness.light : Brightness.dark,
                  statusBarBrightness:
                      isDark ? Brightness.dark : Brightness.light,
                  systemNavigationBarColor: isDark
                      ? const Color(0xFF0F172A)
                      : Colors.white,
                  systemNavigationBarIconBrightness:
                      isDark ? Brightness.light : Brightness.dark,
                ),
                child: OfflineGate(
                  child: SessionGuard(
                    child: _BootGate(
                      child: child ?? const SizedBox.shrink(),
                    ),
                  ),
                ),
              );
            },
          );
        },
      ),
    );
  }
}

/// Full-screen branded splash shown *only during the initial cold-start
/// hydration* of the persisted session. Without this gate the router
/// briefly paints `/role-select` before the redirect fires, which looks
/// like a flash of the login screen on every cold start.
///
/// IMPORTANT: this gate must NOT replace the tree on every subsequent
/// [AuthLoading] emission. The auth cubit emits loading during every
/// form submission (staff/manager login, registerBusiness, logout…) and
/// if we rebuild the whole app with a splash each time, the user sees
/// their form disappear mid-submit. We track `_bootFinished` so the
/// splash is only ever shown before the first non-loading state lands.
class _BootGate extends StatefulWidget {
  const _BootGate({required this.child});
  final Widget child;

  @override
  State<_BootGate> createState() => _BootGateState();
}

class _BootGateState extends State<_BootGate> {
  bool _bootFinished = false;

  @override
  Widget build(BuildContext context) {
    if (_bootFinished) return widget.child;
    return BlocListener<AuthCubit, AuthState>(
      listenWhen: (prev, curr) => !_bootFinished && curr is! AuthLoading,
      listener: (_, __) {
        if (!_bootFinished) setState(() => _bootFinished = true);
      },
      child: BlocBuilder<AuthCubit, AuthState>(
        buildWhen: (prev, curr) =>
            !_bootFinished && (prev is AuthLoading) != (curr is AuthLoading),
        builder: (context, state) {
          if (state is! AuthLoading) return widget.child;
          return const _SplashScreen();
        },
      ),
    );
  }
}

class _SplashScreen extends StatefulWidget {
  const _SplashScreen();

  @override
  State<_SplashScreen> createState() => _SplashScreenState();
}

class _SplashScreenState extends State<_SplashScreen>
    with SingleTickerProviderStateMixin {
  late final AnimationController _fade;

  @override
  void initState() {
    super.initState();
    _fade = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 420),
    )..forward();
  }

  @override
  void dispose() {
    _fade.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    return Scaffold(
      backgroundColor: isDark
          ? AppColors.darkScaffoldBackground
          : AppColors.scaffoldBackground,
      body: DecoratedBox(
        decoration: BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: isDark
                ? const [
                    AppColors.darkScaffoldBackground,
                    Color(0xFF101826),
                  ]
                : [
                    AppColors.scaffoldBackground,
                    AppColors.primary.withValues(alpha: 0.05),
                  ],
          ),
        ),
        child: FadeTransition(
          opacity:
              CurvedAnimation(parent: _fade, curve: Curves.easeOutCubic),
          child: Center(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                ScaleTransition(
                  scale: Tween(begin: 0.92, end: 1.0).animate(
                    CurvedAnimation(
                      parent: _fade,
                      curve: Curves.easeOutBack,
                    ),
                  ),
                  child: const QordyLogo(height: 48),
                ),
                const SizedBox(height: 28),
                const SizedBox(
                  width: 22,
                  height: 22,
                  child: CircularProgressIndicator(
                    strokeWidth: 2.2,
                    valueColor:
                        AlwaysStoppedAnimation<Color>(AppColors.primary),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
