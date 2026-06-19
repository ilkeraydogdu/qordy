import 'dart:async';
import 'dart:io';

import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:flutter/foundation.dart';

/// Reactive connectivity wrapper — combines
/// [Connectivity.onConnectivityChanged] (radio/Wi-Fi toggling) with an
/// actual reachability probe so we don't pretend we're online just
/// because the device is attached to a captive portal / hotspot with
/// no real internet.
///
/// QORDY mobile is deliberately **online-only**: every meaningful
/// screen hits the API, and the backend is the source of truth for
/// orders, payments and subscription state. Rendering stale local
/// data would let a waiter take an order that a suspended tenant
/// shouldn't be accepting, or show prices that have since been
/// edited. Easier and safer to just block the UI behind an
/// "İnternet bağlantısı yok" gate whenever we lose the network.
class ConnectivityService {
  ConnectivityService._();
  static final ConnectivityService instance = ConnectivityService._();

  final Connectivity _connectivity = Connectivity();

  // Seeded to `true` (online) so the app doesn't flash the offline
  // screen on the very first frame while we're still probing; the
  // first probe flips it to the real value within ~200 ms.
  bool _isOnline = true;

  /// Current best-effort online flag. Combines `connectivity_plus`'s
  /// radio-layer signal with a lightweight DNS probe.
  bool get isOnline => _isOnline;

  final _controller = StreamController<bool>.broadcast();

  /// Broadcast stream of connectivity transitions — `true` = online,
  /// `false` = offline.
  Stream<bool> get stream => _controller.stream;

  StreamSubscription<List<ConnectivityResult>>? _sub;
  Timer? _probeTimer;

  /// Wire everything up. Call once during app startup.
  Future<void> initialize() async {
    try {
      await _probeAndEmit();
    } catch (_) {}

    _sub = _connectivity.onConnectivityChanged.listen((_) {
      // Radio layer toggled — re-probe right away. We don't trust the
      // raw ConnectivityResult on its own because "wifi" doesn't mean
      // "actually reaches the internet" (captive portals, misconfigured
      // routers, SIMs with no data plan, etc.).
      _probeAndEmit();
    });

    // Belt-and-braces: every 20s re-probe even if the radio hasn't
    // changed. Catches the "SSID is up but upstream DSL died" case.
    _probeTimer?.cancel();
    _probeTimer = Timer.periodic(
      const Duration(seconds: 20),
      (_) => _probeAndEmit(),
    );
  }

  Future<void> dispose() async {
    await _sub?.cancel();
    _probeTimer?.cancel();
    await _controller.close();
  }

  Future<void> _probeAndEmit() async {
    final online = await _hasReachableInternet();
    if (online != _isOnline) {
      _isOnline = online;
      if (!_controller.isClosed) _controller.add(online);
    }
  }

  /// DNS-based reachability check. Cheap (a single UDP packet) and
  /// works even when the backend is temporarily down — we only care
  /// about "is there real internet?", not "is our API up?".
  Future<bool> _hasReachableInternet() async {
    try {
      final result = await InternetAddress.lookup(
        'one.one.one.one',
      ).timeout(const Duration(seconds: 3));
      return result.isNotEmpty && result.first.rawAddress.isNotEmpty;
    } catch (e) {
      if (kDebugMode) {
        debugPrint('ConnectivityService: probe failed — $e');
      }
      return false;
    }
  }

  /// Force a fresh probe — useful when the user taps "Tekrar dene" on
  /// the offline screen so we don't make them wait for the 20s tick.
  Future<bool> recheck() async {
    await _probeAndEmit();
    return _isOnline;
  }
}
