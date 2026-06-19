import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:get_it/get_it.dart';
import 'package:go_router/go_router.dart';

import '../../../config/theme.dart';
import '../../auth/cubit/auth_cubit.dart';
import '../../auth/cubit/auth_state.dart';
import '../data/biometric_service.dart';
import '../pattern_unlock_service.dart';
import '../quick_unlock_service.dart';
import '../widgets/pattern_lock.dart';
import '../widgets/pin_keypad.dart';

/// Shown on cold-start when the persisted session is gated behind a
/// user-configured PIN. Success emits [Authenticated]; too many wrong
/// entries clears the saved PIN and forces a full re-login.
class QuickUnlockScreen extends StatefulWidget {
  const QuickUnlockScreen({super.key});

  @override
  State<QuickUnlockScreen> createState() => _QuickUnlockScreenState();
}

enum _UnlockMode { pin, pattern, bio }

class _QuickUnlockScreenState extends State<QuickUnlockScreen> {
  String _pin = '';
  String? _error;
  String? _patternError;
  bool _busy = false;
  int _expectedLength = 6;
  bool _biometricEnabled = false;
  late _UnlockMode _mode;

  final _biometrics = GetIt.instance<BiometricService>();
  final _pattern = GetIt.instance<PatternUnlockService>();

  @override
  void initState() {
    super.initState();
    _mode = _pickInitialMode();
    _loadLength();
    // Kısa bir delay ile ekran yerleşsin, sonra otomatik biyometri dene.
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _maybeAutoPromptBiometric();
    });
  }

  _UnlockMode _pickInitialMode() {
    final state = context.read<AuthCubit>().state;
    if (state is! PendingUnlock) return _UnlockMode.pin;
    if (state.bioOnly) return _UnlockMode.bio;
    // PIN wins over pattern when both are enabled — PIN is the default
    // method we ship with; pattern is an opt-in. User can tap the
    // switcher to flip.
    if (state.pinEnabled) return _UnlockMode.pin;
    if (state.patternEnabled) return _UnlockMode.pattern;
    return _UnlockMode.pin;
  }

  Future<void> _loadLength() async {
    final cubit = context.read<AuthCubit>();
    final state = cubit.state;
    if (state is! PendingUnlock) return;
    final l = await cubit.quickUnlockService.getPinLength(state.userId);
    final canBio = await _biometrics.canUseBiometrics();
    final bioEnabled =
        canBio && await _biometrics.isEnabledForUser(state.userId);
    if (mounted) {
      setState(() {
        _expectedLength = l ?? 6;
        _biometricEnabled = bioEnabled;
      });
    }
  }

  Future<void> _maybeAutoPromptBiometric() async {
    if (!mounted) return;
    // loadLength async olduğu için tekrar kontrol et.
    final cubit = context.read<AuthCubit>();
    final state = cubit.state;
    if (state is! PendingUnlock) return;
    final canBio = await _biometrics.canUseBiometrics();
    if (!canBio) return;
    final bioEnabled = await _biometrics.isEnabledForUser(state.userId);
    if (!bioEnabled) return;
    if (!mounted) return;
    await _useBiometric();
  }

  Future<void> _useBiometric() async {
    if (_busy) return;
    final cubit = context.read<AuthCubit>();
    final state = cubit.state;
    if (state is! PendingUnlock) return;
    setState(() => _busy = true);
    final ok = await _biometrics.authenticate(
      reason: 'Uygulamaya giriş için kimliğinizi doğrulayın',
    );
    if (!mounted) return;
    if (ok) {
      HapticFeedback.mediumImpact();
      await cubit.completeUnlock();
    } else {
      setState(() => _busy = false);
    }
  }

  void _onKey(String digit) {
    if (_busy) return;
    if (_pin.length >= _expectedLength) return;
    HapticFeedback.selectionClick();
    setState(() {
      _pin = _pin + digit;
      _error = null;
    });
    if (_pin.length == _expectedLength) {
      _submit();
    }
  }

  void _onBackspace() {
    if (_busy || _pin.isEmpty) return;
    HapticFeedback.selectionClick();
    setState(() {
      _pin = _pin.substring(0, _pin.length - 1);
      _error = null;
    });
  }

  Future<void> _submit() async {
    final cubit = context.read<AuthCubit>();
    final state = cubit.state;
    if (state is! PendingUnlock) return;
    setState(() => _busy = true);
    final result = await cubit.quickUnlockService.verify(
      userId: state.userId,
      pin: _pin,
    );
    if (!mounted) return;
    switch (result) {
      case QuickUnlockResult.ok:
        HapticFeedback.mediumImpact();
        await cubit.completeUnlock();
        // router redirects on Authenticated state
        break;
      case QuickUnlockResult.wrong:
        HapticFeedback.heavyImpact();
        final remaining =
            await cubit.quickUnlockService.getRemainingAttempts(state.userId);
        if (mounted) {
          setState(() {
            _pin = '';
            _error = 'Hatalı PIN. Kalan deneme: $remaining';
            _busy = false;
          });
        }
        break;
      case QuickUnlockResult.lockedOut:
        HapticFeedback.heavyImpact();
        // Service already cleared the PIN — kick the user to full login.
        await cubit.logout();
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
              content: Text(
                  'Çok fazla hatalı deneme. Lütfen e-posta ve şifrenizle tekrar giriş yapın.'),
            ),
          );
        }
        break;
      case QuickUnlockResult.notSet:
        // Edge case: PIN was wiped externally. Fall through to Authenticated.
        await cubit.completeUnlock();
        break;
    }
  }

  Future<void> _useDifferentAccount() async {
    await context.read<AuthCubit>().logout();
    if (mounted) context.go('/login');
  }

  Future<void> _submitPattern(List<int> dots) async {
    final cubit = context.read<AuthCubit>();
    final state = cubit.state;
    if (state is! PendingUnlock) return;
    if (dots.length < 4) {
      setState(() => _patternError = 'En az 4 nokta seçilmeli');
      return;
    }
    setState(() => _busy = true);
    final result = await _pattern.verify(userId: state.userId, dots: dots);
    if (!mounted) return;
    switch (result) {
      case PatternUnlockResult.ok:
        HapticFeedback.mediumImpact();
        await cubit.completeUnlock();
        break;
      case PatternUnlockResult.wrong:
        HapticFeedback.heavyImpact();
        final remaining = await _pattern.getRemainingAttempts(state.userId);
        setState(() {
          _patternError = 'Desen hatalı. Kalan deneme: $remaining';
          _busy = false;
        });
        break;
      case PatternUnlockResult.lockedOut:
        HapticFeedback.heavyImpact();
        await cubit.logout();
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
              content: Text(
                  'Çok fazla hatalı desen. Lütfen e-posta ve şifrenizle tekrar giriş yapın.'),
            ),
          );
        }
        break;
      case PatternUnlockResult.notSet:
        await cubit.completeUnlock();
        break;
    }
  }

  void _switchMode(_UnlockMode mode) {
    if (_busy) return;
    HapticFeedback.selectionClick();
    setState(() {
      _mode = mode;
      _pin = '';
      _error = null;
      _patternError = null;
    });
  }

  Widget _buildUnlockPanel(PendingUnlock state) {
    if (state.bioOnly) {
      return _BioOnlyUnlockPanel(
        busy: _busy,
        onBiometric: _useBiometric,
      );
    }
    switch (_mode) {
      case _UnlockMode.pattern:
        return Center(
          child: PatternLock(
            onCompleted: _submitPattern,
            errorText: _patternError,
            maxSize: 300,
          ),
        );
      case _UnlockMode.bio:
        return _BioOnlyUnlockPanel(
          busy: _busy,
          onBiometric: _useBiometric,
        );
      case _UnlockMode.pin:
        return PinKeypad(
          onDigit: _onKey,
          onBackspace: _onBackspace,
          onBiometric: _biometricEnabled ? _useBiometric : null,
          busy: _busy,
        );
    }
  }

  @override
  Widget build(BuildContext context) {
    final state = context.watch<AuthCubit>().state;
    if (state is! PendingUnlock) {
      return const SizedBox.shrink();
    }
    final isDark = Theme.of(context).brightness == Brightness.dark;
    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 24),
          child: Column(
            children: [
              const SizedBox(height: 48),
              _Avatar(
                avatarUrl: state.avatarUrl,
                businessLogoUrl: state.businessLogo,
                displayName: state.displayName,
              ),
              const SizedBox(height: 20),
              Text(
                'Merhaba ${state.displayName}',
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
              if ((state.businessName ?? '').isNotEmpty) ...[
                const SizedBox(height: 4),
                Text(
                  state.businessName!,
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w500,
                    color: isDark
                        ? AppColors.darkTextSecondary
                        : AppColors.textSecondary,
                  ),
                ),
              ],
              if ((state.roleLabel ?? '').isNotEmpty) ...[
                const SizedBox(height: 6),
                Container(
                  padding: const EdgeInsets.symmetric(
                      horizontal: 10, vertical: 4),
                  decoration: BoxDecoration(
                    color: AppColors.primary.withValues(alpha: 0.1),
                    borderRadius: BorderRadius.circular(20),
                  ),
                  child: Text(
                    state.roleLabel!,
                    style: const TextStyle(
                      fontSize: 11,
                      fontWeight: FontWeight.w600,
                      color: AppColors.primary,
                    ),
                  ),
                ),
              ],
              const SizedBox(height: 28),
              // Header row: PIN dots for pin mode, "N nokta" counter for
              // pattern, blank for bio-only.
              if (_mode == _UnlockMode.pin) ...[
                _PinDots(
                  length: _expectedLength,
                  filled: _pin.length,
                  error: _error != null,
                ),
                const SizedBox(height: 10),
                SizedBox(
                  height: 18,
                  child: Text(
                    _error ?? 'PIN kodunu gir',
                    style: TextStyle(
                      fontSize: 13,
                      color: _error != null
                          ? AppColors.error
                          : (isDark
                              ? AppColors.darkTextSecondary
                              : AppColors.textSecondary),
                      fontWeight:
                          _error != null ? FontWeight.w500 : FontWeight.w400,
                    ),
                  ),
                ),
              ] else if (_mode == _UnlockMode.pattern) ...[
                SizedBox(
                  height: 18,
                  child: Text(
                    _patternError ?? 'Deseni çiz',
                    style: TextStyle(
                      fontSize: 13,
                      color: _patternError != null
                          ? AppColors.error
                          : (isDark
                              ? AppColors.darkTextSecondary
                              : AppColors.textSecondary),
                      fontWeight: _patternError != null
                          ? FontWeight.w500
                          : FontWeight.w400,
                    ),
                  ),
                ),
              ] else ...[
                const SizedBox(height: 18),
              ],
              const SizedBox(height: 20),
              Expanded(
                child: _buildUnlockPanel(state),
              ),
              // Aşağıda PIN / desen / biyometri arası geçiş için küçük bir
              // switcher; kullanıcı birden fazla yöntem kurduysa görünür.
              if (!state.bioOnly &&
                  (state.pinEnabled && state.patternEnabled))
                _MethodSwitcher(
                  current: _mode,
                  onPin: () => _switchMode(_UnlockMode.pin),
                  onPattern: () => _switchMode(_UnlockMode.pattern),
                ),
              if (!state.bioOnly &&
                  (state.pinEnabled && state.patternEnabled))
                const SizedBox(height: 6),
              TextButton(
                onPressed: _useDifferentAccount,
                child: const Text(
                  'Farklı hesapla giriş yap',
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

/// Segmented pill that lets the user flip between PIN and pattern
/// entry while on the unlock screen. Only rendered when both factors
/// are enabled for the current user on this device.
class _MethodSwitcher extends StatelessWidget {
  final _UnlockMode current;
  final VoidCallback onPin;
  final VoidCallback onPattern;
  const _MethodSwitcher({
    required this.current,
    required this.onPin,
    required this.onPattern,
  });

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final bg = isDark
        ? AppColors.darkSurface.withValues(alpha: 0.7)
        : AppColors.surfaceMuted;
    return Container(
      padding: const EdgeInsets.all(4),
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(999),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          _segment(
            context,
            label: 'PIN',
            icon: Icons.pin_rounded,
            selected: current == _UnlockMode.pin,
            onTap: onPin,
          ),
          _segment(
            context,
            label: 'Desen',
            icon: Icons.pattern_rounded,
            selected: current == _UnlockMode.pattern,
            onTap: onPattern,
          ),
        ],
      ),
    );
  }

  Widget _segment(
    BuildContext context, {
    required String label,
    required IconData icon,
    required bool selected,
    required VoidCallback onTap,
  }) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    return GestureDetector(
      onTap: onTap,
      behavior: HitTestBehavior.opaque,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 180),
        curve: Curves.easeOut,
        padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 9),
        decoration: BoxDecoration(
          color: selected
              ? (isDark
                  ? AppColors.darkCard
                  : Colors.white)
              : Colors.transparent,
          borderRadius: BorderRadius.circular(999),
          boxShadow: selected
              ? [
                  BoxShadow(
                    color: Colors.black.withValues(alpha: 0.08),
                    blurRadius: 8,
                    offset: const Offset(0, 2),
                  ),
                ]
              : null,
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(
              icon,
              size: 16,
              color: selected
                  ? AppColors.primary
                  : (isDark
                      ? AppColors.darkTextSecondary
                      : AppColors.textSecondary),
            ),
            const SizedBox(width: 6),
            Text(
              label,
              style: TextStyle(
                fontSize: 13,
                fontWeight: FontWeight.w600,
                color: selected
                    ? AppColors.primary
                    : (isDark
                        ? AppColors.darkTextSecondary
                        : AppColors.textSecondary),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// Personel rolleri için biyometri-only yeniden doğrulama paneli.
/// PIN keypad yerine ortada büyük bir Face ID / parmak izi butonu
/// gösterir ve altında açıklama + fallback metni yer alır.
class _BioOnlyUnlockPanel extends StatelessWidget {
  final bool busy;
  final VoidCallback onBiometric;

  const _BioOnlyUnlockPanel({required this.busy, required this.onBiometric});

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    return Column(
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        GestureDetector(
          onTap: busy ? null : onBiometric,
          child: Container(
            width: 108,
            height: 108,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              gradient: AppColors.brandGradient,
              boxShadow: [
                BoxShadow(
                  color: AppColors.primary.withValues(alpha: 0.30),
                  blurRadius: 26,
                  offset: const Offset(0, 10),
                ),
              ],
            ),
            alignment: Alignment.center,
            child: busy
                ? const SizedBox(
                    width: 28,
                    height: 28,
                    child: CircularProgressIndicator(
                      strokeWidth: 2.5,
                      color: Colors.white,
                    ),
                  )
                : const Icon(
                    Icons.fingerprint_rounded,
                    color: Colors.white,
                    size: 56,
                  ),
          ),
        ),
        const SizedBox(height: 20),
        Text(
          'Biyometri ile aç',
          style: TextStyle(
            fontSize: 16,
            fontWeight: FontWeight.w700,
            color: isDark
                ? AppColors.darkTextPrimary
                : AppColors.textPrimary,
            letterSpacing: -0.2,
          ),
        ),
        const SizedBox(height: 6),
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 32),
          child: Text(
            'Hızlı giriş için cihazınızın Face ID veya parmak izi özelliğini kullanın. PIN zaten giriş sırasında kullanıldığı için burada tekrar sorulmuyor.',
            textAlign: TextAlign.center,
            style: TextStyle(
              fontSize: 12.5,
              height: 1.45,
              color: isDark
                  ? AppColors.darkTextSecondary
                  : AppColors.textSecondary,
            ),
          ),
        ),
      ],
    );
  }
}

class _Avatar extends StatelessWidget {
  final String? avatarUrl;
  final String? businessLogoUrl;
  final String displayName;

  const _Avatar({
    required this.displayName,
    this.avatarUrl,
    this.businessLogoUrl,
  });

  @override
  Widget build(BuildContext context) {
    final initials = displayName.isEmpty
        ? '?'
        : displayName.trim().split(RegExp(r'\s+')).take(2)
            .map((w) => w.isNotEmpty ? w[0].toUpperCase() : '')
            .join();
    final hasAvatar = avatarUrl != null && avatarUrl!.isNotEmpty;
    return SizedBox(
      width: 96,
      height: 96,
      child: Stack(
        clipBehavior: Clip.none,
        children: [
          Container(
            width: 96,
            height: 96,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              gradient: AppColors.brandGradient,
              boxShadow: [
                BoxShadow(
                  color: AppColors.primary.withValues(alpha: 0.25),
                  blurRadius: 20,
                  offset: const Offset(0, 6),
                ),
              ],
            ),
            alignment: Alignment.center,
            child: hasAvatar
                ? ClipOval(
                    child: CachedNetworkImage(
                      imageUrl: avatarUrl!,
                      width: 96,
                      height: 96,
                      fit: BoxFit.cover,
                      errorWidget: (_, __, ___) => Center(
                        child: Text(
                          initials,
                          style: const TextStyle(
                            fontSize: 30,
                            fontWeight: FontWeight.w700,
                            color: Colors.white,
                          ),
                        ),
                      ),
                    ),
                  )
                : Text(
                    initials,
                    style: const TextStyle(
                      fontSize: 30,
                      fontWeight: FontWeight.w700,
                      color: Colors.white,
                    ),
                  ),
          ),
          if (businessLogoUrl != null && businessLogoUrl!.isNotEmpty)
            Positioned(
              right: -4,
              bottom: -4,
              child: Container(
                width: 36,
                height: 36,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  color: Theme.of(context).cardColor,
                  border: Border.all(
                    color: Theme.of(context).scaffoldBackgroundColor,
                    width: 3,
                  ),
                ),
                child: ClipOval(
                  child: CachedNetworkImage(
                    imageUrl: businessLogoUrl!,
                    fit: BoxFit.cover,
                    errorWidget: (_, __, ___) => const SizedBox.shrink(),
                  ),
                ),
              ),
            ),
        ],
      ),
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
