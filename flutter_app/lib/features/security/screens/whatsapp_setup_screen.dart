import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../../config/theme.dart';
import '../data/whatsapp_2fa_api.dart';

/// Three-step enrolment for WhatsApp 2FA:
///   1. Enter phone number (country code dropdown pre-filled with +90).
///   2. Receive 6-digit code via Meta WhatsApp template.
///   3. Enter the code, server flips is_enabled=1.
///
/// Returns `true` to the caller when WhatsApp 2FA is fully active.
class WhatsappSetupScreen extends StatefulWidget {
  const WhatsappSetupScreen({super.key});

  @override
  State<WhatsappSetupScreen> createState() => _WhatsappSetupScreenState();
}

class _WhatsappSetupScreenState extends State<WhatsappSetupScreen> {
  final _api = Whatsapp2faApi();
  final _phoneCtrl = TextEditingController();
  final _codeCtrl = TextEditingController();
  String _countryCode = '+90';
  int _step = 1;
  bool _busy = false;
  String? _maskedPhone;

  @override
  void dispose() {
    _phoneCtrl.dispose();
    _codeCtrl.dispose();
    super.dispose();
  }

  Future<void> _submitPhone() async {
    final raw = _phoneCtrl.text.replaceAll(RegExp(r'\D'), '');
    if (raw.length < 9) {
      _toast('Geçerli bir telefon numarası girin', error: true);
      return;
    }
    // Combine E.164 (+90 + 5XX...) → "905XXXXXXXXX"
    final phone = _countryCode.replaceAll('+', '') + raw;
    setState(() => _busy = true);
    try {
      final res = await _api.setup(phone);
      if (!mounted) return;
      if (res['success'] == true) {
        final data = res['data'] is Map
            ? (res['data'] as Map).map((k, v) => MapEntry(k.toString(), v))
            : const <String, dynamic>{};
        setState(() {
          _step = 2;
          _maskedPhone = data['masked_phone']?.toString();
          _busy = false;
        });
        _toast('WhatsApp üzerinden 6 haneli kod gönderildi');
      } else {
        setState(() => _busy = false);
        _toast(res['error']?.toString() ?? 'Kod gönderilemedi', error: true);
      }
    } catch (e) {
      if (!mounted) return;
      setState(() => _busy = false);
      _toast('Ağ hatası: $e', error: true);
    }
  }

  Future<void> _submitCode() async {
    final code = _codeCtrl.text.replaceAll(RegExp(r'\D'), '');
    if (code.length != 6) {
      _toast('6 haneli kodu girin', error: true);
      return;
    }
    setState(() => _busy = true);
    try {
      final res = await _api.confirm(code);
      if (!mounted) return;
      if (res['success'] == true) {
        _toast('WhatsApp doğrulaması aktifleştirildi');
        Navigator.of(context).pop(true);
      } else {
        setState(() => _busy = false);
        _toast(res['error']?.toString() ?? 'Kod hatalı', error: true);
      }
    } catch (e) {
      if (!mounted) return;
      setState(() => _busy = false);
      _toast('Ağ hatası: $e', error: true);
    }
  }

  void _toast(String msg, {bool error = false}) {
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(
        content: Text(msg),
        backgroundColor: error ? AppColors.error : AppColors.success,
        behavior: SnackBarBehavior.floating,
      ));
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final fg = isDark ? AppColors.darkTextPrimary : AppColors.textPrimary;
    final sub = isDark ? AppColors.darkTextSecondary : AppColors.textSecondary;
    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      appBar: AppBar(
        title: const Text('WhatsApp Doğrulama Kur'),
        backgroundColor: Theme.of(context).scaffoldBackgroundColor,
        elevation: 0,
      ),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(20, 8, 20, 20),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: const Color(0xFF25D366).withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(14),
                ),
                child: Row(
                  children: [
                    const Icon(Icons.chat_rounded,
                        color: Color(0xFF25D366), size: 26),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Text(
                        _step == 1
                            ? 'Giriş doğrulama kodlarını alacağın WhatsApp numaranı gir. '
                                'Kod, Meta WhatsApp Business altyapısı ile tek kullanımlık olarak gönderilir.'
                            : 'WhatsApp\'ta gelen 6 haneli kodu gir. Kod 5 dakika içinde geçerliliğini yitirir.',
                        style: TextStyle(fontSize: 12.5, height: 1.45, color: fg),
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 24),
              if (_step == 1) ..._phoneStep(fg, sub) else ..._codeStep(fg, sub),
              const Spacer(),
              SizedBox(
                height: 52,
                child: ElevatedButton(
                  onPressed: _busy
                      ? null
                      : () => _step == 1 ? _submitPhone() : _submitCode(),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: const Color(0xFF25D366),
                    foregroundColor: Colors.white,
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(14),
                    ),
                    disabledBackgroundColor:
                        const Color(0xFF25D366).withValues(alpha: 0.4),
                  ),
                  child: _busy
                      ? const SizedBox(
                          width: 22,
                          height: 22,
                          child: CircularProgressIndicator(
                            color: Colors.white,
                            strokeWidth: 2.4,
                          ),
                        )
                      : Text(
                          _step == 1
                              ? 'WhatsApp ile kod gönder'
                              : 'Kodu doğrula ve aktif et',
                          style: const TextStyle(
                            fontSize: 15,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                ),
              ),
              if (_step == 2) ...[
                const SizedBox(height: 12),
                TextButton(
                  onPressed: _busy
                      ? null
                      : () {
                          setState(() {
                            _step = 1;
                            _codeCtrl.clear();
                          });
                        },
                  child: const Text('Numarayı değiştir'),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }

  List<Widget> _phoneStep(Color fg, Color sub) {
    return [
      Text(
        'Telefon numarası',
        style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: sub),
      ),
      const SizedBox(height: 8),
      Row(
        children: [
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
            decoration: BoxDecoration(
              border: Border.all(color: AppColors.border),
              borderRadius: BorderRadius.circular(12),
            ),
            child: DropdownButtonHideUnderline(
              child: DropdownButton<String>(
                value: _countryCode,
                items: const [
                  DropdownMenuItem(value: '+90', child: Text('TR +90')),
                  DropdownMenuItem(value: '+49', child: Text('DE +49')),
                  DropdownMenuItem(value: '+44', child: Text('UK +44')),
                  DropdownMenuItem(value: '+1', child: Text('US +1')),
                  DropdownMenuItem(value: '+31', child: Text('NL +31')),
                ],
                onChanged: (v) {
                  if (v != null) setState(() => _countryCode = v);
                },
              ),
            ),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: TextField(
              controller: _phoneCtrl,
              keyboardType: TextInputType.phone,
              inputFormatters: [
                FilteringTextInputFormatter.digitsOnly,
                LengthLimitingTextInputFormatter(11),
              ],
              style: TextStyle(
                fontSize: 17,
                fontWeight: FontWeight.w600,
                color: fg,
              ),
              decoration: InputDecoration(
                hintText: '5XX XXX XX XX',
                hintStyle: TextStyle(color: sub.withValues(alpha: 0.7)),
                filled: true,
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(12),
                  borderSide: BorderSide(color: AppColors.border),
                ),
                enabledBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(12),
                  borderSide: BorderSide(color: AppColors.border),
                ),
                focusedBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(12),
                  borderSide: const BorderSide(
                      color: Color(0xFF25D366), width: 1.6),
                ),
              ),
            ),
          ),
        ],
      ),
    ];
  }

  List<Widget> _codeStep(Color fg, Color sub) {
    return [
      if (_maskedPhone != null)
        Text(
          'Gönderildiği numara: $_countryCode ${_maskedPhone!}',
          style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: sub),
        ),
      const SizedBox(height: 12),
      TextField(
        controller: _codeCtrl,
        autofocus: true,
        keyboardType: TextInputType.number,
        textAlign: TextAlign.center,
        inputFormatters: [
          FilteringTextInputFormatter.digitsOnly,
          LengthLimitingTextInputFormatter(6),
        ],
        style: TextStyle(
          fontSize: 26,
          letterSpacing: 8,
          fontWeight: FontWeight.w700,
          color: fg,
        ),
        decoration: InputDecoration(
          hintText: '••••••',
          hintStyle: TextStyle(
            letterSpacing: 8,
            color: sub.withValues(alpha: 0.5),
          ),
          counterText: '',
          filled: true,
          border: OutlineInputBorder(
            borderRadius: BorderRadius.circular(12),
            borderSide: BorderSide(color: AppColors.border),
          ),
          enabledBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(12),
            borderSide: BorderSide(color: AppColors.border),
          ),
          focusedBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(12),
            borderSide:
                const BorderSide(color: Color(0xFF25D366), width: 1.6),
          ),
        ),
      ),
    ];
  }
}
