import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../config/theme.dart';
import '../../auth/cubit/auth_cubit.dart';
import '../../auth/cubit/auth_state.dart';
import '../widgets/pin_keypad.dart';

enum _SetupStep { choose, confirm }

/// One-time prompt shown after the very first successful login: asks the
/// user whether they want to set up a 4–8 digit PIN for subsequent
/// launches. Choosing "Şimdi değil" just transitions to [Authenticated]
/// and skips the setup for this session (we don't pin the user to a
/// decision — they can always enable it later from settings).
class QuickUnlockSetupScreen extends StatefulWidget {
  const QuickUnlockSetupScreen({super.key});

  @override
  State<QuickUnlockSetupScreen> createState() => _QuickUnlockSetupScreenState();
}

class _QuickUnlockSetupScreenState extends State<QuickUnlockSetupScreen> {
  _SetupStep _step = _SetupStep.choose;
  String _first = '';
  String _confirm = '';
  String? _error;
  int _length = 6;
  bool _busy = false;

  void _onDigit(String d) {
    if (_busy) return;
    HapticFeedback.selectionClick();
    setState(() {
      if (_step == _SetupStep.choose) {
        if (_first.length < _length) _first = _first + d;
        _error = null;
      } else {
        if (_confirm.length < _length) _confirm = _confirm + d;
        _error = null;
      }
    });
    _maybeAdvance();
  }

  void _onBackspace() {
    if (_busy) return;
    HapticFeedback.selectionClick();
    setState(() {
      if (_step == _SetupStep.choose && _first.isNotEmpty) {
        _first = _first.substring(0, _first.length - 1);
      } else if (_step == _SetupStep.confirm && _confirm.isNotEmpty) {
        _confirm = _confirm.substring(0, _confirm.length - 1);
      }
      _error = null;
    });
  }

  void _maybeAdvance() {
    if (_step == _SetupStep.choose && _first.length >= 4) {
      // Let the user finish typing all digits they want (up to _length).
      // Advance as soon as they type the minimum 4.
      // Actually only auto-advance once they hit full length to avoid
      // jumping before they're done.
    }
    if (_step == _SetupStep.choose && _first.length == _length) {
      setState(() => _step = _SetupStep.confirm);
    } else if (_step == _SetupStep.confirm &&
        _confirm.length == _first.length) {
      _attemptFinalize();
    }
  }

  void _changeLength(int newLen) {
    if (_busy) return;
    setState(() {
      _length = newLen;
      _first = '';
      _confirm = '';
      _step = _SetupStep.choose;
      _error = null;
    });
  }

  Future<void> _attemptFinalize() async {
    if (_first != _confirm) {
      setState(() {
        _error = 'PIN kodları eşleşmiyor. Tekrar deneyin.';
        _confirm = '';
      });
      return;
    }
    if (_first.length < 4) {
      setState(() => _error = 'PIN en az 4 haneli olmalı');
      return;
    }
    setState(() => _busy = true);
    final cubit = context.read<AuthCubit>();
    final state = cubit.state;
    if (state is! QuickUnlockSetupRequired) return;
    final uid = state.user.userId ?? '';
    if (uid.isEmpty) {
      await cubit.completeQuickUnlockSetup();
      return;
    }
    try {
      await cubit.quickUnlockService.setupPin(
        userId: uid,
        pin: _first,
        userMeta: {
          if (state.user.name != null) 'name': state.user.name!,
          if (state.user.email != null) 'email': state.user.email!,
        },
      );
      HapticFeedback.mediumImpact();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text(
                'Hızlı giriş aktif. Sonraki açılışta sadece PIN isteyeceğiz.'),
            backgroundColor: AppColors.successBright,
          ),
        );
      }
      await cubit.completeQuickUnlockSetup();
    } catch (e) {
      if (mounted) {
        setState(() {
          _error = 'PIN kaydedilemedi: $e';
          _busy = false;
          _first = '';
          _confirm = '';
          _step = _SetupStep.choose;
        });
      }
    }
  }

  Future<void> _skip() async {
    final cubit = context.read<AuthCubit>();
    await cubit.completeQuickUnlockSetup();
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final state = context.watch<AuthCubit>().state;
    if (state is! QuickUnlockSetupRequired) {
      return const SizedBox.shrink();
    }
    final filled = _step == _SetupStep.choose ? _first.length : _confirm.length;
    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 24),
          child: Column(
            children: [
              const SizedBox(height: 48),
              Container(
                width: 72,
                height: 72,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  color: AppColors.primary.withValues(alpha: 0.12),
                ),
                child: const Icon(
                  Icons.lock_rounded,
                  size: 32,
                  color: AppColors.primary,
                ),
              ),
              const SizedBox(height: 24),
              Text(
                _step == _SetupStep.choose
                    ? 'Hızlı giriş için PIN belirle'
                    : 'PIN kodunu tekrar gir',
                textAlign: TextAlign.center,
                style: TextStyle(
                  fontSize: 22,
                  fontWeight: FontWeight.w700,
                  color: isDark
                      ? AppColors.darkTextPrimary
                      : AppColors.textPrimary,
                  letterSpacing: -0.3,
                ),
              ),
              const SizedBox(height: 8),
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 16),
                child: Text(
                  _step == _SetupStep.choose
                      ? 'Sonraki açılışlarda sadece bu PIN ile girersin — '
                          'e-posta ve şifreni tekrar yazmana gerek kalmaz.'
                      : 'Emin olmak için aynı PIN\'i bir kez daha yaz.',
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    fontSize: 13,
                    color: isDark
                        ? AppColors.darkTextSecondary
                        : AppColors.textSecondary,
                    height: 1.45,
                  ),
                ),
              ),
              const SizedBox(height: 28),
              _LengthSelector(
                value: _length,
                onChanged: _changeLength,
                enabled: _step == _SetupStep.choose && _first.isEmpty,
              ),
              const SizedBox(height: 28),
              _PinDots(
                length: _length,
                filled: filled,
                error: _error != null,
              ),
              const SizedBox(height: 12),
              SizedBox(
                height: 18,
                child: _error == null
                    ? const SizedBox.shrink()
                    : Text(
                        _error!,
                        textAlign: TextAlign.center,
                        style: const TextStyle(
                          fontSize: 13,
                          color: AppColors.error,
                          fontWeight: FontWeight.w500,
                        ),
                      ),
              ),
              const SizedBox(height: 12),
              Expanded(
                child: PinKeypad(
                  onDigit: _onDigit,
                  onBackspace: _onBackspace,
                  busy: _busy,
                ),
              ),
              TextButton(
                onPressed: _busy ? null : _skip,
                child: const Text(
                  'Şimdi değil',
                  style: TextStyle(
                    fontSize: 13,
                    fontWeight: FontWeight.w600,
                    color: AppColors.primary,
                  ),
                ),
              ),
              const SizedBox(height: 8),
            ],
          ),
        ),
      ),
    );
  }
}

class _LengthSelector extends StatelessWidget {
  final int value;
  final ValueChanged<int> onChanged;
  final bool enabled;

  const _LengthSelector({
    required this.value,
    required this.onChanged,
    this.enabled = true,
  });

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    const options = [4, 6, 8];
    return Row(
      mainAxisAlignment: MainAxisAlignment.center,
      children: options.map((n) {
        final selected = n == value;
        return Padding(
          padding: const EdgeInsets.symmetric(horizontal: 4),
          child: InkWell(
            onTap: enabled ? () => onChanged(n) : null,
            borderRadius: BorderRadius.circular(20),
            child: AnimatedContainer(
              duration: const Duration(milliseconds: 160),
              padding:
                  const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
              decoration: BoxDecoration(
                color: selected
                    ? AppColors.primary
                    : (isDark
                        ? AppColors.darkSurface
                        : AppColors.surface),
                borderRadius: BorderRadius.circular(20),
                border: Border.all(
                  color: selected
                      ? AppColors.primary
                      : (isDark
                          ? AppColors.darkBorder
                          : AppColors.border),
                ),
              ),
              child: Text(
                '$n hane',
                style: TextStyle(
                  fontSize: 13,
                  fontWeight: FontWeight.w600,
                  color: selected
                      ? Colors.white
                      : (isDark
                          ? AppColors.darkTextSecondary
                          : AppColors.textSecondary),
                ),
              ),
            ),
          ),
        );
      }).toList(),
    );
  }
}

class _PinDots extends StatelessWidget {
  final int length;
  final int filled;
  final bool error;
  const _PinDots({
    required this.length,
    required this.filled,
    this.error = false,
  });

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.center,
      children: List.generate(length, (i) {
        final isFilled = i < filled;
        return AnimatedContainer(
          duration: const Duration(milliseconds: 140),
          margin: const EdgeInsets.symmetric(horizontal: 6),
          width: 14,
          height: 14,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            color: error
                ? AppColors.error
                : (isFilled ? AppColors.primary : Colors.transparent),
            border: Border.all(
              color: error
                  ? AppColors.error
                  : (isFilled ? AppColors.primary : AppColors.border),
              width: 1.5,
            ),
          ),
        );
      }),
    );
  }
}
