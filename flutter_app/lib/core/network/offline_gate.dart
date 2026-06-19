import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../config/theme.dart';
import 'connectivity_service.dart';

/// App-wide offline gate. When the device drops connectivity we cover
/// the whole tree with an opaque "İnternet bağlantısı yok" screen so:
///
///   * no stale data is visible (prices, stock, order state),
///   * nothing mutable can be tapped (offline guesses would desync the
///     POS / kitchen),
///   * a cashier can't keep ringing up tickets that the backend never
///     sees.
///
/// Having a dedicated gate also makes the security contract crisp —
/// because every interaction requires an authenticated round-trip to
/// the backend, the app can't be fooled into "working" by jamming the
/// radio and then tapping around.
class OfflineGate extends StatefulWidget {
  final Widget child;
  const OfflineGate({super.key, required this.child});

  @override
  State<OfflineGate> createState() => _OfflineGateState();
}

class _OfflineGateState extends State<OfflineGate> {
  late bool _online;
  StreamSubscription<bool>? _sub;

  @override
  void initState() {
    super.initState();
    _online = ConnectivityService.instance.isOnline;
    _sub = ConnectivityService.instance.stream.listen((online) {
      if (!mounted) return;
      if (online != _online) {
        setState(() => _online = online);
      }
    });
  }

  @override
  void dispose() {
    _sub?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Stack(
      children: [
        // Underlying app keeps its state — when we come back online the
        // screens resume exactly where they were.
        widget.child,
        if (!_online)
          const _OfflineBlocker(),
      ],
    );
  }
}

class _OfflineBlocker extends StatefulWidget {
  const _OfflineBlocker();

  @override
  State<_OfflineBlocker> createState() => _OfflineBlockerState();
}

class _OfflineBlockerState extends State<_OfflineBlocker>
    with SingleTickerProviderStateMixin {
  late final AnimationController _pulse;
  bool _checking = false;

  @override
  void initState() {
    super.initState();
    _pulse = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 1400),
    )..repeat(reverse: true);
    HapticFeedback.heavyImpact();
  }

  @override
  void dispose() {
    _pulse.dispose();
    super.dispose();
  }

  Future<void> _retry() async {
    if (_checking) return;
    setState(() => _checking = true);
    HapticFeedback.selectionClick();
    await ConnectivityService.instance.recheck();
    // Small delay to let the button animation feel intentional.
    await Future.delayed(const Duration(milliseconds: 220));
    if (mounted) setState(() => _checking = false);
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    return Positioned.fill(
      // Absorb all pointer events behind the blocker — can't tap the
      // app through the overlay even if transparency were added later.
      child: AbsorbPointer(
        absorbing: true,
        child: Material(
          color: isDark
              ? AppColors.darkScaffoldBackground
              : AppColors.scaffoldBackground,
          child: SafeArea(
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 32),
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  const Spacer(),
                  ScaleTransition(
                    scale: Tween(begin: 0.92, end: 1.06).animate(
                      CurvedAnimation(
                        parent: _pulse,
                        curve: Curves.easeInOutCubic,
                      ),
                    ),
                    child: Container(
                      width: 104,
                      height: 104,
                      decoration: BoxDecoration(
                        shape: BoxShape.circle,
                        gradient: LinearGradient(
                          begin: Alignment.topLeft,
                          end: Alignment.bottomRight,
                          colors: [
                            AppColors.error.withValues(alpha: 0.15),
                            AppColors.error.withValues(alpha: 0.05),
                          ],
                        ),
                        border: Border.all(
                          color: AppColors.error.withValues(alpha: 0.25),
                          width: 1.5,
                        ),
                      ),
                      child: const Icon(
                        Icons.wifi_off_rounded,
                        size: 48,
                        color: AppColors.error,
                      ),
                    ),
                  ),
                  const SizedBox(height: 28),
                  Text(
                    'İnternet bağlantısı yok',
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      fontSize: 22,
                      fontWeight: FontWeight.w800,
                      color: isDark
                          ? AppColors.darkTextPrimary
                          : AppColors.textPrimary,
                      letterSpacing: -0.2,
                    ),
                  ),
                  const SizedBox(height: 10),
                  Text(
                    'QORDY güvenlik nedeniyle yalnızca çevrimiçiyken çalışır. '
                    'Lütfen Wi-Fi veya mobil veri bağlantınızı kontrol edin ve '
                    'tekrar deneyin.',
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      fontSize: 14.5,
                      height: 1.5,
                      color: isDark
                          ? AppColors.darkTextSecondary
                          : AppColors.textSecondary,
                    ),
                  ),
                  const SizedBox(height: 32),
                  AbsorbPointer(
                    // Re-enable taps for just the retry button.
                    absorbing: false,
                    child: SizedBox(
                      width: double.infinity,
                      height: 52,
                      child: ElevatedButton.icon(
                        style: ElevatedButton.styleFrom(
                          backgroundColor: AppColors.primary,
                          foregroundColor: Colors.white,
                          elevation: 0,
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(14),
                          ),
                        ),
                        onPressed: _checking ? null : _retry,
                        icon: _checking
                            ? const SizedBox(
                                width: 18,
                                height: 18,
                                child: CircularProgressIndicator(
                                  strokeWidth: 2.2,
                                  valueColor: AlwaysStoppedAnimation<Color>(
                                      Colors.white),
                                ),
                              )
                            : const Icon(Icons.refresh_rounded, size: 20),
                        label: Text(
                          _checking ? 'Kontrol ediliyor...' : 'Tekrar Dene',
                          style: const TextStyle(
                            fontSize: 15,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ),
                    ),
                  ),
                  const Spacer(),
                  Text(
                    'QORDY · Güvenli Restoran Yönetimi',
                    style: TextStyle(
                      fontSize: 11.5,
                      color: (isDark
                              ? AppColors.darkTextHint
                              : AppColors.textHint)
                          .withValues(alpha: 0.8),
                      letterSpacing: 0.4,
                    ),
                  ),
                  const SizedBox(height: 12),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
