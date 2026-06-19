import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:get_it/get_it.dart';
import 'package:go_router/go_router.dart';
import 'package:local_auth/local_auth.dart';

import '../../../config/theme.dart';
import '../../auth/cubit/auth_cubit.dart';
import '../../auth/cubit/auth_state.dart';
import '../data/biometric_service.dart';
import '../data/totp_api.dart';
import '../data/whatsapp_2fa_api.dart';
import '../pattern_unlock_service.dart';
import 'pattern_setup_screen.dart';
import 'totp_setup_screen.dart';
import 'whatsapp_setup_screen.dart';

/// Centralised security dashboard: quick-unlock (PIN) toggle + future
/// 2FA TOTP management. Mounted at `/security` and reachable from the
/// profile menu.
class SecurityScreen extends StatefulWidget {
  const SecurityScreen({super.key});

  @override
  State<SecurityScreen> createState() => _SecurityScreenState();
}

class _SecurityScreenState extends State<SecurityScreen> {
  bool _loading = true;
  bool _pinEnabled = false;
  int? _pinLength;

  final _totpApi = TotpApi();
  final _waApi = Whatsapp2faApi();
  final _biometrics = GetIt.instance<BiometricService>();
  final _patternService = GetIt.instance<PatternUnlockService>();
  bool _patternEnabled = false;
  bool _totpEnabled = false;
  bool _totpEnrolled = false;
  bool _totpGloballyEnabled = true;
  bool _waEnabled = false;
  bool _waEnrolled = false;
  bool _waGloballyEnabled = false;
  String? _waMaskedPhone;
  bool _biometricAvailable = false;
  bool _biometricEnabled = false;
  List<BiometricType> _biometricTypes = const [];

  @override
  void initState() {
    super.initState();
    _refresh();
  }

  Future<void> _refresh() async {
    final cubit = context.read<AuthCubit>();
    final state = cubit.state;
    final uid =
        state is Authenticated ? (state.user.userId ?? '') : '';
    if (uid.isEmpty) {
      if (mounted) setState(() => _loading = false);
      return;
    }
    final enabled = await cubit.quickUnlockService.isEnabledForUser(uid);
    final length = await cubit.quickUnlockService.getPinLength(uid);
    bool patternEnabled = false;
    try {
      patternEnabled = await _patternService.isEnabledForUser(uid);
    } catch (_) {}

    // TOTP status — silently degrade if the server is old or offline.
    bool totpEnabled = false;
    bool totpEnrolled = false;
    try {
      final status = await _totpApi.status();
      final data = status['data'] is Map
          ? (status['data'] as Map).map((k, v) => MapEntry(k.toString(), v))
          : const <String, dynamic>{};
      totpEnabled = data['enabled'] == true;
      totpEnrolled = data['enrolled'] == true;
    } catch (_) {}

    // Whatsapp + global method availability (superadmin toggles).
    bool waEnabled = false;
    bool waEnrolled = false;
    bool waGlobal = false;
    bool totpGlobal = true;
    String? waMasked;
    try {
      final methodsRes = await _waApi.authMethodsStatus();
      if (methodsRes['success'] == true && methodsRes['data'] is Map) {
        final md = (methodsRes['data'] as Map)
            .map((k, v) => MapEntry(k.toString(), v));
        final status = md['status'] is Map
            ? (md['status'] as Map).map((k, v) => MapEntry(k.toString(), v))
            : const <String, dynamic>{};
        final wa = status['whatsapp'] is Map
            ? (status['whatsapp'] as Map)
                .map((k, v) => MapEntry(k.toString(), v))
            : const <String, dynamic>{};
        final totp = status['totp'] is Map
            ? (status['totp'] as Map)
                .map((k, v) => MapEntry(k.toString(), v))
            : const <String, dynamic>{};
        waEnrolled = wa['enrolled'] == true;
        waGlobal = wa['globally_enabled'] == true;
        totpGlobal = totp['globally_enabled'] != false;
        waMasked = wa['masked']?.toString();
      }
      final ws = await _waApi.status();
      if (ws['success'] == true && ws['data'] is Map) {
        final d = (ws['data'] as Map).map((k, v) => MapEntry(k.toString(), v));
        waEnabled = d['enabled'] == true;
      }
    } catch (_) {}

    // Biyometri durumu (cihaz + kullanıcı tercihi)
    bool bioAvailable = false;
    bool bioEnabled = false;
    List<BiometricType> bioTypes = const [];
    try {
      bioAvailable = await _biometrics.canUseBiometrics();
      if (bioAvailable) {
        bioEnabled = await _biometrics.isEnabledForUser(uid);
        bioTypes = await _biometrics.availableTypes();
      }
    } catch (_) {}

    if (!mounted) return;
    setState(() {
      _pinEnabled = enabled;
      _pinLength = length;
      _patternEnabled = patternEnabled;
      _totpEnabled = totpEnabled;
      _totpEnrolled = totpEnrolled;
      _totpGloballyEnabled = totpGlobal;
      _waEnabled = waEnabled;
      _waEnrolled = waEnrolled;
      _waGloballyEnabled = waGlobal;
      _waMaskedPhone = waMasked;
      _biometricAvailable = bioAvailable;
      _biometricEnabled = bioEnabled;
      _biometricTypes = bioTypes;
      _loading = false;
    });
  }

  Future<void> _toggleBiometric(bool enabled) async {
    final cubit = context.read<AuthCubit>();
    final state = cubit.state;
    final uid = state is Authenticated ? (state.user.userId ?? '') : '';
    if (uid.isEmpty) return;
    if (enabled) {
      // Önce PIN veya desen kurulu mu kontrol et — biyometri bu
      // faktörlerden birine hızlı geçiş için.
      if (!_pinEnabled && !_patternEnabled) {
        if (!mounted) return;
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content:
                Text('Biyometri için önce PIN veya desen kurmanız gerekiyor.'),
            behavior: SnackBarBehavior.floating,
          ),
        );
        return;
      }
      final ok = await _biometrics.authenticate(
        reason: 'Biyometri ile hızlı girişi aktifleştirin',
      );
      if (!ok) return;
    }
    await _biometrics.setEnabledForUser(uid, enabled);
    if (!mounted) return;
    setState(() => _biometricEnabled = enabled);
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(enabled
            ? 'Biyometri ile hızlı giriş aktif'
            : 'Biyometri ile hızlı giriş kapatıldı'),
        behavior: SnackBarBehavior.floating,
      ),
    );
  }

  String _biometricLabel() {
    if (_biometricTypes.contains(BiometricType.face)) {
      return 'Face ID / Yüz ile giriş';
    }
    if (_biometricTypes.contains(BiometricType.fingerprint) ||
        _biometricTypes.contains(BiometricType.strong) ||
        _biometricTypes.contains(BiometricType.weak)) {
      return 'Parmak izi ile giriş';
    }
    return 'Biyometrik hızlı giriş';
  }

  Future<void> _enableWhatsApp() async {
    final ok = await Navigator.of(context).push<bool>(
      MaterialPageRoute(builder: (_) => const WhatsappSetupScreen()),
    );
    if (ok == true && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('WhatsApp doğrulama aktifleştirildi.'),
          backgroundColor: AppColors.success,
          behavior: SnackBarBehavior.floating,
        ),
      );
    }
    await _refresh();
  }

  Future<void> _disableWhatsApp() async {
    final confirm = await showDialog<bool>(
      context: context,
      builder: (_) => AlertDialog(
        title: const Text('WhatsApp doğrulamasını kapat'),
        content: const Text(
            'WhatsApp ile giriş doğrulamasını kapatmak istiyor musun? Kayıtlı numaran silinir, bir daha aç dersen yeniden kurulum gerekir.'),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(false),
            child: const Text('İptal'),
          ),
          TextButton(
            onPressed: () => Navigator.of(context).pop(true),
            child: const Text('Kapat',
                style: TextStyle(color: AppColors.error)),
          ),
        ],
      ),
    );
    if (confirm != true) return;
    try {
      final res = await _waApi.disable();
      if (!mounted) return;
      if (res['success'] == true) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('WhatsApp doğrulama kapatıldı.'),
            behavior: SnackBarBehavior.floating,
          ),
        );
      } else {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(res['error']?.toString() ?? 'Kapatılamadı'),
            backgroundColor: AppColors.error,
            behavior: SnackBarBehavior.floating,
          ),
        );
      }
    } catch (_) {}
    await _refresh();
  }

  Future<void> _enableTotp() async {
    final ok = await Navigator.of(context).push<bool>(
      MaterialPageRoute(builder: (_) => const TotpSetupScreen()),
    );
    if (ok == true && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('2 Adımlı Doğrulama aktifleştirildi.'),
          backgroundColor: AppColors.success,
          behavior: SnackBarBehavior.floating,
        ),
      );
    }
    await _refresh();
  }

  Future<void> _disableTotp() async {
    final code = await _askForTotpCode(
      title: '2FA\'yı kapat',
      message:
          'Güvenlik için 2 Adımlı Doğrulamayı kapatmadan önce authenticator uygulamanızdaki 6 haneli kodu girin.',
    );
    if (code == null) return;
    try {
      final res = await _totpApi.disable(code);
      if (!mounted) return;
      if (res['success'] == true) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('2 Adımlı Doğrulama kapatıldı.'),
            behavior: SnackBarBehavior.floating,
          ),
        );
      } else {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(res['error']?.toString() ?? 'Kod doğrulanamadı'),
            backgroundColor: AppColors.error,
            behavior: SnackBarBehavior.floating,
          ),
        );
      }
    } on DioException catch (e) {
      if (!mounted) return;
      final msg = e.response?.data is Map
          ? (e.response!.data['error']?.toString() ?? 'Bağlantı hatası')
          : 'Bağlantı hatası';
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(msg),
          backgroundColor: AppColors.error,
          behavior: SnackBarBehavior.floating,
        ),
      );
    }
    await _refresh();
  }

  /// Small modal with a 6-digit input. Returns the typed code on
  /// submit, null on cancel. Used both for `disable` and for anywhere
  /// we need to confirm the user is actually in control of the factor.
  Future<String?> _askForTotpCode({
    required String title,
    required String message,
  }) async {
    final controller = TextEditingController();
    try {
      return await showDialog<String>(
        context: context,
        builder: (ctx) => AlertDialog(
        title: Text(title),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(message, style: const TextStyle(fontSize: 13, height: 1.4)),
            const SizedBox(height: 12),
            TextField(
              controller: controller,
              autofocus: true,
              keyboardType: TextInputType.number,
              inputFormatters: [
                FilteringTextInputFormatter.digitsOnly,
                LengthLimitingTextInputFormatter(6),
              ],
              decoration: const InputDecoration(
                labelText: '6 haneli kod',
                counterText: '',
              ),
              style: const TextStyle(
                fontSize: 18,
                letterSpacing: 4,
                fontWeight: FontWeight.w600,
              ),
            ),
          ],
        ),
          actions: [
            TextButton(
              onPressed: () => Navigator.of(ctx).pop(),
              child: const Text('İptal'),
            ),
            TextButton(
              onPressed: () {
                final c = controller.text.trim();
                if (c.length == 6) Navigator.of(ctx).pop(c);
              },
              child: const Text('Onayla'),
            ),
          ],
        ),
      );
    } finally {
      controller.dispose();
    }
  }

  Future<void> _disablePin() async {
    final confirm = await showDialog<bool>(
      context: context,
      builder: (_) => AlertDialog(
        title: const Text('PIN\'i kaldır'),
        content: const Text(
            'Hızlı giriş PIN\'ini kaldırmak istiyor musun? Sonraki girişte e-posta ve şifren tekrar istenecek.'),
        actions: [
          TextButton(
              onPressed: () => Navigator.of(context).pop(false),
              child: const Text('İptal')),
          TextButton(
              onPressed: () => Navigator.of(context).pop(true),
              child: const Text('Kaldır',
                  style: TextStyle(color: AppColors.error))),
        ],
      ),
    );
    if (confirm != true) return;
    if (!mounted) return;
    final cubit = context.read<AuthCubit>();
    final state = cubit.state;
    final uid = state is Authenticated ? (state.user.userId ?? '') : '';
    if (uid.isEmpty) return;
    await cubit.quickUnlockService.clear(uid);
    await _refresh();
    if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Hızlı giriş devre dışı bırakıldı.')),
      );
    }
  }

  Future<void> _enablePattern() async {
    final ok = await Navigator.of(context).push<bool>(
      MaterialPageRoute(builder: (_) => const PatternSetupScreen()),
    );
    if (ok == true && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Desen kilidi aktifleştirildi.'),
          backgroundColor: AppColors.success,
          behavior: SnackBarBehavior.floating,
        ),
      );
    }
    await _refresh();
  }

  Future<void> _disablePattern() async {
    final confirm = await showDialog<bool>(
      context: context,
      builder: (_) => AlertDialog(
        title: const Text('Desen kilidini kaldır'),
        content: const Text(
            'Desen kilidini kaldırmak istiyor musun? Sonraki girişte varsa PIN, yoksa e-posta ve şifren istenecek.'),
        actions: [
          TextButton(
              onPressed: () => Navigator.of(context).pop(false),
              child: const Text('İptal')),
          TextButton(
              onPressed: () => Navigator.of(context).pop(true),
              child: const Text('Kaldır',
                  style: TextStyle(color: AppColors.error))),
        ],
      ),
    );
    if (confirm != true) return;
    if (!mounted) return;
    final cubit = context.read<AuthCubit>();
    final state = cubit.state;
    final uid = state is Authenticated ? (state.user.userId ?? '') : '';
    if (uid.isEmpty) return;
    await _patternService.clear(uid);
    await _refresh();
    if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Desen kilidi kapatıldı.')),
      );
    }
  }

  Future<void> _enablePin() async {
    // Reuse the same setup flow: push the existing setup screen in a
    // transient wrapper by temporarily emitting QuickUnlockSetupRequired
    // is overkill — simpler to ship a dedicated edit screen. For now
    // logout-and-relogin is the cleanest path, so we show a bottom
    // sheet explaining the flow.
    await Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => const _InlinePinSetupScreen(),
      ),
    );
    await _refresh();
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      appBar: AppBar(
        title: const Text('Güvenlik'),
        backgroundColor: Theme.of(context).scaffoldBackgroundColor,
        elevation: 0,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_ios_new_rounded, size: 18),
          onPressed: () => context.pop(),
        ),
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : ListView(
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
              children: [
                _SectionCard(
                  icon: Icons.lock_rounded,
                  title: 'Hızlı giriş (PIN)',
                  subtitle: _pinEnabled
                      ? 'Aktif — ${_pinLength ?? 6} haneli PIN ile açılıyor'
                      : 'Uygulamayı kapatıp açınca yine PIN istemesi için kur',
                  accent: AppColors.primary,
                  trailing: Switch(
                    value: _pinEnabled,
                    activeThumbColor: AppColors.primary,
                    onChanged: (v) {
                      if (v) {
                        _enablePin();
                      } else {
                        _disablePin();
                      }
                    },
                  ),
                ),
                const SizedBox(height: 12),
                _SectionCard(
                  icon: Icons.pattern_rounded,
                  title: 'Desen kilidi',
                  subtitle: _patternEnabled
                      ? 'Aktif — 3x3 desenle açılıyor'
                      : 'PIN yerine / yanında desenle kilit açma',
                  accent: AppColors.accentOrange,
                  trailing: Switch(
                    value: _patternEnabled,
                    activeThumbColor: AppColors.accentOrange,
                    onChanged: (v) {
                      if (v) {
                        _enablePattern();
                      } else {
                        _disablePattern();
                      }
                    },
                  ),
                ),
                if (_biometricAvailable) ...[
                  const SizedBox(height: 12),
                  _SectionCard(
                    icon: _biometricTypes.contains(BiometricType.face)
                        ? Icons.face_rounded
                        : Icons.fingerprint_rounded,
                    title: _biometricLabel(),
                    subtitle: _biometricEnabled
                        ? 'Aktif — PIN/desen yerine biyometri ile açılıyor'
                        : (_pinEnabled || _patternEnabled)
                            ? 'PIN veya deseni biyometri ile atla'
                            : 'Önce PIN veya desen kurarsanız biyometri ile açabilirsiniz',
                    accent: AppColors.accentPurple,
                    trailing: Switch(
                      value: _biometricEnabled,
                      activeThumbColor: AppColors.accentPurple,
                      onChanged: (_pinEnabled || _patternEnabled)
                          ? _toggleBiometric
                          : null,
                    ),
                  ),
                ],
                const SizedBox(height: 12),
                if (_totpGloballyEnabled)
                  _SectionCard(
                    icon: Icons.phonelink_lock_rounded,
                    title: '2 Adımlı Doğrulama (TOTP)',
                    subtitle: _totpEnabled
                        ? 'Aktif — her girişte authenticator kodu istenecek'
                        : _totpEnrolled
                            ? 'Kurulum başlatılmış ama onaylanmamış — tekrar dene'
                            : 'Google Authenticator, Authy veya Microsoft Authenticator ile koru',
                    accent: AppColors.info,
                    trailing: Switch(
                      value: _totpEnabled,
                      activeThumbColor: AppColors.info,
                      onChanged: (v) {
                        if (v) {
                          _enableTotp();
                        } else {
                          _disableTotp();
                        }
                      },
                    ),
                  ),
                if (_totpGloballyEnabled) const SizedBox(height: 12),
                if (_waGloballyEnabled)
                  _SectionCard(
                    icon: Icons.chat_rounded,
                    title: 'WhatsApp ile Doğrulama',
                    subtitle: _waEnabled
                        ? 'Aktif — her girişte ${_waMaskedPhone ?? 'WhatsApp numaran'} üzerine kod gelir'
                        : _waEnrolled
                            ? 'Kurulum başlatılmış ama onaylanmamış — tekrar dene'
                            : 'Meta WhatsApp Business altyapısı ile tek kullanımlık kod gönderelim',
                    accent: const Color(0xFF25D366),
                    trailing: Switch(
                      value: _waEnabled,
                      activeThumbColor: const Color(0xFF25D366),
                      onChanged: (v) {
                        if (v) {
                          _enableWhatsApp();
                        } else {
                          _disableWhatsApp();
                        }
                      },
                    ),
                  ),
                if (_waGloballyEnabled) const SizedBox(height: 12),
                const SizedBox(height: 12),
                Text(
                  'Güvenlik ipucu',
                  style: TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.w600,
                    color: isDark
                        ? AppColors.darkTextSecondary
                        : AppColors.textSecondary,
                    letterSpacing: 0.4,
                  ),
                ),
                const SizedBox(height: 8),
                Text(
                  'PIN kodunu doğum tarihin veya telefon numaranın son hanelerinden seçme. '
                  'Hatalı 5 denemeden sonra PIN sıfırlanır ve e-posta + şifre ile yeniden '
                  'giriş istenir. 2 Adımlı Doğrulamayı açtığında her oturum açılışında '
                  'authenticator uygulamandaki 6 haneli kodu gireceksin — bir cihazın '
                  'çalınsa bile seninle beraber olan authenticator olmadan hesabına '
                  'girilemez.',
                  style: TextStyle(
                    fontSize: 13,
                    height: 1.5,
                    color: isDark
                        ? AppColors.darkTextSecondary
                        : AppColors.textSecondary,
                  ),
                ),
              ],
            ),
    );
  }
}

class _SectionCard extends StatelessWidget {
  final IconData icon;
  final String title;
  final String subtitle;
  final Color accent;
  final Widget trailing;

  const _SectionCard({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.accent,
    required this.trailing,
  });

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final border =
        isDark ? AppColors.darkBorder : AppColors.border;
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Theme.of(context).cardColor,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: border.withValues(alpha: 0.6)),
      ),
      child: Row(
        children: [
          Container(
            width: 42,
            height: 42,
            decoration: BoxDecoration(
              color: accent.withValues(alpha: 0.12),
              borderRadius: BorderRadius.circular(12),
            ),
            child: Icon(icon, color: accent, size: 22),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: TextStyle(
                    fontSize: 15,
                    fontWeight: FontWeight.w600,
                    color: isDark
                        ? AppColors.darkTextPrimary
                        : AppColors.textPrimary,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  subtitle,
                  style: TextStyle(
                    fontSize: 12,
                    color: isDark
                        ? AppColors.darkTextSecondary
                        : AppColors.textSecondary,
                    height: 1.4,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(width: 12),
          trailing,
        ],
      ),
    );
  }
}

/// Self-contained PIN setup flow used from the Security screen. Unlike
/// the cold-start [QuickUnlockSetupScreen] this does not transition the
/// auth state — it just records a PIN for the current authenticated
/// user and pops back.
class _InlinePinSetupScreen extends StatefulWidget {
  const _InlinePinSetupScreen();

  @override
  State<_InlinePinSetupScreen> createState() => _InlinePinSetupScreenState();
}

class _InlinePinSetupScreenState extends State<_InlinePinSetupScreen> {
  String _first = '';
  String _confirm = '';
  final int _length = 6;
  bool _confirmStep = false;
  String? _error;

  void _onDigit(String d) {
    setState(() {
      if (!_confirmStep) {
        if (_first.length < _length) _first = _first + d;
      } else {
        if (_confirm.length < _length) _confirm = _confirm + d;
      }
      _error = null;
    });
    if (!_confirmStep && _first.length == _length) {
      setState(() => _confirmStep = true);
    } else if (_confirmStep && _confirm.length == _first.length) {
      _finalize();
    }
  }

  void _onBackspace() {
    setState(() {
      if (_confirmStep && _confirm.isNotEmpty) {
        _confirm = _confirm.substring(0, _confirm.length - 1);
      } else if (!_confirmStep && _first.isNotEmpty) {
        _first = _first.substring(0, _first.length - 1);
      }
      _error = null;
    });
  }

  Future<void> _finalize() async {
    if (_first != _confirm) {
      setState(() {
        _error = 'PIN\'ler eşleşmiyor';
        _confirm = '';
      });
      return;
    }
    final cubit = context.read<AuthCubit>();
    final state = cubit.state;
    final uid = state is Authenticated ? (state.user.userId ?? '') : '';
    if (uid.isEmpty) return;
    await cubit.quickUnlockService.setupPin(userId: uid, pin: _first);
    if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('PIN kaydedildi.')),
      );
      Navigator.of(context).pop();
    }
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final filled = _confirmStep ? _confirm.length : _first.length;
    return Scaffold(
      appBar: AppBar(
        title: const Text('Hızlı giriş PIN\'i'),
      ),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 24),
          child: Column(
            children: [
              const SizedBox(height: 16),
              Text(
                _confirmStep
                    ? 'Onaylamak için PIN\'i tekrar gir'
                    : 'Yeni PIN\'i gir',
                textAlign: TextAlign.center,
                style: TextStyle(
                  fontSize: 18,
                  fontWeight: FontWeight.w700,
                  color: isDark
                      ? AppColors.darkTextPrimary
                      : AppColors.textPrimary,
                ),
              ),
              const SizedBox(height: 16),
              Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: List.generate(_length, (i) {
                  final isFilled = i < filled;
                  return AnimatedContainer(
                    duration: const Duration(milliseconds: 140),
                    margin: const EdgeInsets.symmetric(horizontal: 6),
                    width: 14,
                    height: 14,
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      color: isFilled ? AppColors.primary : Colors.transparent,
                      border: Border.all(
                        color: isFilled ? AppColors.primary : AppColors.border,
                        width: 1.5,
                      ),
                    ),
                  );
                }),
              ),
              const SizedBox(height: 12),
              if (_error != null)
                Text(
                  _error!,
                  style: const TextStyle(
                      color: AppColors.error, fontWeight: FontWeight.w500),
                ),
              const Spacer(),
              _SimpleKeypad(
                onDigit: _onDigit,
                onBackspace: _onBackspace,
              ),
              const SizedBox(height: 12),
            ],
          ),
        ),
      ),
    );
  }
}

class _SimpleKeypad extends StatelessWidget {
  final ValueChanged<String> onDigit;
  final VoidCallback onBackspace;
  const _SimpleKeypad({required this.onDigit, required this.onBackspace});

  @override
  Widget build(BuildContext context) {
    final keys = [
      ['1', '2', '3'],
      ['4', '5', '6'],
      ['7', '8', '9'],
      ['', '0', '⌫'],
    ];
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final fg =
        isDark ? AppColors.darkTextPrimary : AppColors.textPrimary;
    return Column(
      children: keys.map((row) {
        return Row(
          children: row.map((k) {
            if (k.isEmpty) return const Expanded(child: SizedBox());
            final isBack = k == '⌫';
            return Expanded(
              child: SizedBox(
                height: 66,
                child: InkWell(
                  onTap: () => isBack ? onBackspace() : onDigit(k),
                  borderRadius: BorderRadius.circular(999),
                  child: Center(
                    child: isBack
                        ? Icon(Icons.backspace_outlined, color: fg)
                        : Text(
                            k,
                            style: TextStyle(
                              fontSize: 26,
                              fontWeight: FontWeight.w600,
                              color: fg,
                            ),
                          ),
                  ),
                ),
              ),
            );
          }).toList(),
        );
      }).toList(),
    );
  }
}
