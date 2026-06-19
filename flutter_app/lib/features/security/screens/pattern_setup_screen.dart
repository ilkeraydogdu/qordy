import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:get_it/get_it.dart';
import 'package:go_router/go_router.dart';

import '../../../config/theme.dart';
import '../../auth/cubit/auth_cubit.dart';
import '../../auth/cubit/auth_state.dart';
import '../pattern_unlock_service.dart';
import '../widgets/pattern_lock.dart';

/// Standalone flow for enrolling (or replacing) a swipe pattern. The
/// user draws the pattern twice — first as the "new" pattern, then as
/// the confirmation. On match we hash-store it via
/// [PatternUnlockService.setupPattern] and pop with `true`.
///
/// Unlike the cold-start `QuickUnlockSetupScreen` this doesn't flip the
/// [AuthState]; we assume the user is already in [Authenticated] and
/// we're just attaching a local-device unlock method for their id.
class PatternSetupScreen extends StatefulWidget {
  const PatternSetupScreen({super.key});

  @override
  State<PatternSetupScreen> createState() => _PatternSetupScreenState();
}

class _PatternSetupScreenState extends State<PatternSetupScreen> {
  final _pattern = GetIt.instance<PatternUnlockService>();
  List<int>? _first;
  bool _confirmStep = false;
  bool _busy = false;
  String? _error;
  int _latestLen = 0;

  Future<void> _onCompleted(List<int> dots) async {
    if (_busy) return;
    if (dots.length < 4) {
      setState(() {
        _error = 'En az 4 noktayı birleştir.';
      });
      return;
    }
    if (!_confirmStep) {
      setState(() {
        _first = List.of(dots);
        _confirmStep = true;
        _error = null;
      });
      HapticFeedback.mediumImpact();
      return;
    }
    // confirm step
    final first = _first;
    if (first == null) return;
    if (first.length != dots.length || !_listEquals(first, dots)) {
      setState(() {
        _error = 'Desenler uyuşmadı — baştan başla.';
        _first = null;
        _confirmStep = false;
      });
      return;
    }

    final cubit = context.read<AuthCubit>();
    final state = cubit.state;
    final uid = state is Authenticated ? (state.user.userId ?? '') : '';
    if (uid.isEmpty) return;
    setState(() => _busy = true);
    try {
      await _pattern.setupPattern(userId: uid, dots: dots);
      if (!mounted) return;
      HapticFeedback.mediumImpact();
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Desen kaydedildi.'),
          backgroundColor: AppColors.success,
          behavior: SnackBarBehavior.floating,
        ),
      );
      if (mounted) context.pop(true);
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _error = 'Kayıt edilemedi: $e';
        _busy = false;
      });
    }
  }

  bool _listEquals(List<int> a, List<int> b) {
    if (a.length != b.length) return false;
    for (var i = 0; i < a.length; i++) {
      if (a[i] != b[i]) return false;
    }
    return true;
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      appBar: AppBar(
        title: const Text('Desen kilidi'),
        backgroundColor: Theme.of(context).scaffoldBackgroundColor,
        elevation: 0,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_ios_new_rounded, size: 18),
          onPressed: () => context.pop(false),
        ),
      ),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(24, 8, 24, 24),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const SizedBox(height: 8),
              Text(
                _confirmStep
                    ? 'Onaylamak için deseni tekrar çiz'
                    : 'Yeni deseni çiz',
                textAlign: TextAlign.center,
                style: TextStyle(
                  fontSize: 20,
                  fontWeight: FontWeight.w700,
                  color: isDark
                      ? AppColors.darkTextPrimary
                      : AppColors.textPrimary,
                  letterSpacing: -0.2,
                ),
              ),
              const SizedBox(height: 6),
              Text(
                _confirmStep
                    ? 'Az önce çizdiğin desenle birebir aynı olmalı.'
                    : 'En az 4 noktayı parmağını kaldırmadan birleştir. '
                        'Sonraki adımda aynı deseni bir daha çizeceksin.',
                textAlign: TextAlign.center,
                style: TextStyle(
                  fontSize: 13,
                  height: 1.45,
                  color: isDark
                      ? AppColors.darkTextSecondary
                      : AppColors.textSecondary,
                ),
              ),
              const SizedBox(height: 20),
              Expanded(
                child: Center(
                  child: PatternLock(
                    onCompleted: _onCompleted,
                    onProgress: (picked) {
                      if (picked.length != _latestLen) {
                        setState(() {
                          _latestLen = picked.length;
                        });
                      }
                    },
                    errorText: _error,
                    maxSize: 320,
                  ),
                ),
              ),
              const SizedBox(height: 12),
              SizedBox(
                height: 22,
                child: _error != null
                    ? Text(
                        _error!,
                        textAlign: TextAlign.center,
                        style: const TextStyle(
                          fontSize: 13,
                          color: AppColors.error,
                          fontWeight: FontWeight.w600,
                        ),
                      )
                    : Text(
                        _latestLen > 0
                            ? 'Seçildi: $_latestLen nokta'
                            : 'Parmağını dokunduğun yerden kaldırmadan çiz',
                        textAlign: TextAlign.center,
                        style: TextStyle(
                          fontSize: 12,
                          color: isDark
                              ? AppColors.darkTextSecondary
                              : AppColors.textSecondary,
                        ),
                      ),
              ),
              const SizedBox(height: 8),
              if (_confirmStep)
                TextButton(
                  onPressed: _busy
                      ? null
                      : () => setState(() {
                            _first = null;
                            _confirmStep = false;
                            _error = null;
                            _latestLen = 0;
                          }),
                  child: const Text(
                    'Baştan çiz',
                    style: TextStyle(fontWeight: FontWeight.w600),
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }
}
