import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../features/auth/cubit/auth_cubit.dart';
import '../../features/auth/cubit/auth_state.dart';

/// Wraps the app tree and forces the user back to the login screen after
/// a period of inactivity OR when the app has spent more than
/// [_lockOnBackgroundAfter] in the background (e.g., device was stolen).
///
/// Rationale — restaurants are multi-user environments where a terminal
/// tablet often stays logged in on the counter. An idle timeout is a
/// cheap way to prevent an unattended device from being used by a
/// stranger without re-entering the PIN / password.
///
/// Two independent rules:
///   * **Idle timeout**: any pointer/key/raw-key event resets a 20-minute
///     timer. When the timer elapses we flip `AuthCubit` back to
///     `AuthInitial`, which sends the user to `/role-select`.
///   * **Background lock**: when the app goes to the background we
///     stamp `DateTime.now()`. If, on resume, more than 10 minutes have
///     passed we log out immediately.
class SessionGuard extends StatefulWidget {
  final Widget child;
  const SessionGuard({super.key, required this.child});

  @override
  State<SessionGuard> createState() => _SessionGuardState();
}

class _SessionGuardState extends State<SessionGuard>
    with WidgetsBindingObserver {
  static const _idleTimeout = Duration(minutes: 20);
  static const _lockOnBackgroundAfter = Duration(minutes: 10);

  Timer? _idleTimer;
  DateTime? _backgroundedAt;
  late final FocusNode _keyboardFocusNode;

  @override
  void initState() {
    super.initState();
    _keyboardFocusNode = FocusNode(
      debugLabel: 'SessionGuardKeyboardListener',
      canRequestFocus: false,
      skipTraversal: true,
    );
    WidgetsBinding.instance.addObserver(this);
    _resetIdleTimer();
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _idleTimer?.cancel();
    _keyboardFocusNode.dispose();
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.paused ||
        state == AppLifecycleState.inactive) {
      _backgroundedAt = DateTime.now();
    } else if (state == AppLifecycleState.resumed) {
      final since = _backgroundedAt;
      _backgroundedAt = null;
      if (since != null &&
          DateTime.now().difference(since) > _lockOnBackgroundAfter) {
        _forceLogout(reason: 'background');
      } else {
        _resetIdleTimer();
      }
    }
  }

  void _resetIdleTimer() {
    _idleTimer?.cancel();
    _idleTimer = Timer(_idleTimeout, () => _forceLogout(reason: 'idle'));
  }

  Future<void> _forceLogout({required String reason}) async {
    if (!mounted) return;
    final authCubit = context.read<AuthCubit>();
    if (authCubit.state is! Authenticated) return;
    if (kDebugMode) {
      debugPrint('[SessionGuard] forcing logout · reason=$reason');
    }
    await authCubit.logout();
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(
        content: Text(
            'Güvenlik için oturumunuz kapatıldı. Lütfen tekrar giriş yapın.'),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Listener(
      behavior: HitTestBehavior.translucent,
      onPointerDown: (_) => _resetIdleTimer(),
      onPointerMove: (_) => _resetIdleTimer(),
      onPointerSignal: (_) => _resetIdleTimer(),
      child: KeyboardListener(
        focusNode: _keyboardFocusNode,
        onKeyEvent: (event) {
          if (event is KeyDownEvent) _resetIdleTimer();
        },
        child: widget.child,
      ),
    );
  }
}
