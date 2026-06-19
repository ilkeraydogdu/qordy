import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:qr_flutter/qr_flutter.dart';

import '../../../config/theme.dart';
import '../data/totp_api.dart';
import '../widgets/otp_code_input.dart';

/// Three-step TOTP enrolment flow:
///   1. Server generates a fresh secret + otpauth URI.
///   2. We render the QR code and the raw secret so the user can scan
///      with (or paste into) Google Authenticator / Authy / 1Password.
///   3. The user types the current 6-digit code from their authenticator
///      app; we post it back to confirm enrolment and flip is_enabled=1.
///
/// Used from [SecurityScreen]. On success Navigator.pop(context, true)
/// is called so the caller can refresh its local state.
class TotpSetupScreen extends StatefulWidget {
  const TotpSetupScreen({super.key});

  @override
  State<TotpSetupScreen> createState() => _TotpSetupScreenState();
}

class _TotpSetupScreenState extends State<TotpSetupScreen> {
  final _api = TotpApi();
  final TextEditingController _codeController = TextEditingController();
  bool _loading = true;
  String? _secret;
  String? _otpauthUri;
  bool _submitting = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _codeController.addListener(() {
      if (_error != null && _codeController.text.isNotEmpty) {
        setState(() => _error = null);
      } else if (mounted) {
        setState(() {});
      }
    });
    _start();
  }

  @override
  void dispose() {
    _codeController.dispose();
    super.dispose();
  }

  Future<void> _start() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final res = await _api.setup();
      if (res['success'] != true) {
        setState(() {
          _error = res['error']?.toString() ?? 'TOTP hazırlanamadı';
          _loading = false;
        });
        return;
      }
      final data = res['data'] is Map
          ? (res['data'] as Map).map((k, v) => MapEntry(k.toString(), v))
          : <String, dynamic>{};
      setState(() {
        _secret = data['secret']?.toString();
        _otpauthUri = data['otpauth_uri']?.toString();
        _loading = false;
      });
    } on DioException catch (e) {
      setState(() {
        _error = e.response?.data is Map
            ? (e.response!.data['error']?.toString() ?? 'Bağlantı hatası')
            : 'Bağlantı hatası';
        _loading = false;
      });
    } catch (e) {
      setState(() {
        _error = 'Beklenmeyen hata: $e';
        _loading = false;
      });
    }
  }

  Future<void> _confirm([String? code]) async {
    final value = (code ?? _codeController.text).trim();
    if (value.length != 6 || _submitting) return;
    setState(() => _submitting = true);
    try {
      final res = await _api.confirm(value);
      if (res['success'] == true) {
        HapticFeedback.mediumImpact();
        if (!mounted) return;
        Navigator.of(context).pop(true);
        return;
      }
      HapticFeedback.heavyImpact();
      setState(() {
        _error = res['error']?.toString() ?? 'Kod doğrulanamadı';
        _submitting = false;
      });
      _codeController.clear();
    } on DioException catch (e) {
      HapticFeedback.heavyImpact();
      setState(() {
        _error = e.response?.data is Map
            ? (e.response!.data['error']?.toString() ?? 'Doğrulama hatası')
            : 'Doğrulama hatası';
        _submitting = false;
      });
      _codeController.clear();
    } catch (_) {
      setState(() {
        _error = 'Doğrulama hatası';
        _submitting = false;
      });
      _codeController.clear();
    }
  }

  Future<void> _copySecret() async {
    if (_secret == null) return;
    await Clipboard.setData(ClipboardData(text: _secret!));
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(
        content: Text('Anahtar panoya kopyalandı'),
        behavior: SnackBarBehavior.floating,
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      appBar: AppBar(
        title: const Text('2 Adımlı Doğrulama'),
        backgroundColor: Theme.of(context).scaffoldBackgroundColor,
        elevation: 0,
      ),
      body: SafeArea(
        child: _loading
            ? const Center(child: CircularProgressIndicator())
            : _otpauthUri == null
                ? _ErrorView(message: _error ?? 'Bilinmeyen hata', onRetry: _start)
                : SingleChildScrollView(
                    padding: const EdgeInsets.fromLTRB(20, 8, 20, 24),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        _StepHeader(
                          number: '1',
                          title: 'Authenticator uygulamanızı açın',
                          subtitle:
                              'Google Authenticator, Authy, Microsoft Authenticator veya 1Password destekleniyor. "Hesap ekle" / "QR kodu tara"yı seçin.',
                          isDark: isDark,
                        ),
                        const SizedBox(height: 18),
                        _StepHeader(
                          number: '2',
                          title: 'QR kodunu taratın',
                          subtitle:
                              'QR tarayamıyorsanız aşağıdaki anahtarı uygulamaya manuel olarak yapıştırın.',
                          isDark: isDark,
                        ),
                        const SizedBox(height: 14),
                        Center(
                          child: Container(
                            padding: const EdgeInsets.all(12),
                            decoration: BoxDecoration(
                              color: Colors.white,
                              borderRadius: BorderRadius.circular(16),
                              border: Border.all(
                                color: isDark
                                    ? AppColors.darkBorder
                                    : AppColors.border,
                              ),
                              boxShadow: [
                                BoxShadow(
                                  color: AppColors.primary
                                      .withValues(alpha: 0.08),
                                  blurRadius: 20,
                                  offset: const Offset(0, 4),
                                ),
                              ],
                            ),
                            child: QrImageView(
                              data: _otpauthUri!,
                              size: 220,
                              backgroundColor: Colors.white,
                              eyeStyle: const QrEyeStyle(
                                eyeShape: QrEyeShape.square,
                                color: Colors.black,
                              ),
                              dataModuleStyle: const QrDataModuleStyle(
                                dataModuleShape: QrDataModuleShape.square,
                                color: Colors.black,
                              ),
                            ),
                          ),
                        ),
                        const SizedBox(height: 14),
                        _SecretBox(
                          secret: _secret ?? '',
                          onCopy: _copySecret,
                          isDark: isDark,
                        ),
                        const SizedBox(height: 22),
                        _StepHeader(
                          number: '3',
                          title: 'Uygulamadaki 6 haneli kodu girin',
                          subtitle:
                              'Kod her 30 saniyede yenilenir. Eşleştiğinde 2FA aktifleştirilir.',
                          isDark: isDark,
                        ),
                        const SizedBox(height: 14),
                        OtpCodeInput(
                          controller: _codeController,
                          enabled: !_submitting,
                          onCompleted: _confirm,
                        ),
                        if (_error != null) ...[
                          const SizedBox(height: 10),
                          Center(
                            child: Text(
                              _error!,
                              style: const TextStyle(
                                color: AppColors.error,
                                fontSize: 13,
                                fontWeight: FontWeight.w500,
                              ),
                            ),
                          ),
                        ],
                        const SizedBox(height: 20),
                        SizedBox(
                          width: double.infinity,
                          height: 50,
                          child: ElevatedButton(
                            onPressed: (_submitting ||
                                    _codeController.text.length != 6)
                                ? null
                                : () => _confirm(),
                            style: ElevatedButton.styleFrom(
                              backgroundColor: AppColors.primary,
                              foregroundColor: Colors.white,
                              shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(14),
                              ),
                            ),
                            child: _submitting
                                ? const SizedBox(
                                    width: 20,
                                    height: 20,
                                    child: CircularProgressIndicator(
                                      strokeWidth: 2.2,
                                      color: Colors.white,
                                    ),
                                  )
                                : const Text(
                                    'Aktifleştir',
                                    style: TextStyle(
                                      fontSize: 15,
                                      fontWeight: FontWeight.w700,
                                    ),
                                  ),
                          ),
                        ),
                      ],
                    ),
                  ),
      ),
      resizeToAvoidBottomInset: true,
    );
  }
}

class _StepHeader extends StatelessWidget {
  final String number;
  final String title;
  final String subtitle;
  final bool isDark;
  const _StepHeader({
    required this.number,
    required this.title,
    required this.subtitle,
    required this.isDark,
  });

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          width: 28,
          height: 28,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            color: AppColors.primary.withValues(alpha: 0.12),
            borderRadius: BorderRadius.circular(999),
          ),
          child: Text(
            number,
            style: const TextStyle(
              color: AppColors.primary,
              fontWeight: FontWeight.w700,
            ),
          ),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                title,
                style: TextStyle(
                  fontSize: 15,
                  fontWeight: FontWeight.w700,
                  color: isDark
                      ? AppColors.darkTextPrimary
                      : AppColors.textPrimary,
                ),
              ),
              const SizedBox(height: 2),
              Text(
                subtitle,
                style: TextStyle(
                  fontSize: 12.5,
                  height: 1.45,
                  color: isDark
                      ? AppColors.darkTextSecondary
                      : AppColors.textSecondary,
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

class _SecretBox extends StatelessWidget {
  final String secret;
  final VoidCallback onCopy;
  final bool isDark;
  const _SecretBox({
    required this.secret,
    required this.onCopy,
    required this.isDark,
  });

  @override
  Widget build(BuildContext context) {
    // Group into 4-char blocks for readability.
    final pretty = secret
        .replaceAllMapped(RegExp(r'.{4}'), (m) => '${m[0]} ')
        .trim();
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      decoration: BoxDecoration(
        color: isDark ? AppColors.darkSurface : const Color(0xFFF3F4F6),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(
          color: isDark ? AppColors.darkBorder : AppColors.border,
        ),
      ),
      child: Row(
        children: [
          Expanded(
            child: SelectableText(
              pretty,
              style: TextStyle(
                fontFamily: 'monospace',
                fontSize: 14,
                letterSpacing: 1.1,
                fontWeight: FontWeight.w600,
                color: isDark
                    ? AppColors.darkTextPrimary
                    : AppColors.textPrimary,
              ),
            ),
          ),
          TextButton.icon(
            onPressed: onCopy,
            icon: const Icon(Icons.copy_rounded, size: 18),
            label: const Text('Kopyala'),
          ),
        ],
      ),
    );
  }
}

class _ErrorView extends StatelessWidget {
  final String message;
  final VoidCallback onRetry;
  const _ErrorView({required this.message, required this.onRetry});

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.error_outline_rounded,
                color: AppColors.error, size: 42),
            const SizedBox(height: 12),
            Text(
              message,
              textAlign: TextAlign.center,
              style: const TextStyle(fontSize: 14),
            ),
            const SizedBox(height: 16),
            ElevatedButton(
              onPressed: onRetry,
              child: const Text('Tekrar dene'),
            ),
          ],
        ),
      ),
    );
  }
}
