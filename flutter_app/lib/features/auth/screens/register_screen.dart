import 'dart:async';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:get_it/get_it.dart';
import 'package:go_router/go_router.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../../../config/api_config.dart';
import '../../../config/theme.dart';
import '../../../core/widgets/qordy_logo.dart';
import '../cubit/auth_cubit.dart';
import '../cubit/auth_state.dart';
import '../widgets/auth_layout.dart';

/// 4-step business sign-up wizard.
///
/// Design principles (updated pass):
///   * **Short, scannable copy.** Each step has a single-line title and
///     an inline 1-sentence hint — no paragraph-long descriptions.
///   * **Sticky action bar.** Previous/Next live in the Scaffold's
///     `bottomNavigationBar` so they stay above the keyboard and never
///     get hidden under the keypad or visually overlap inputs.
///   * **Live password strength.** As the user types in step 4 we
///     compute a 0-4 score (length / letter / digit / symbol / variety)
///     and animate a 4-segment meter + tier label.
///   * **Navigation is driven by the cubit.** On submit, a
///     `BlocListener` watches AuthCubit: on [Authenticated] we queue the
///     onboarding flag and `go('/dashboard')`; on [AuthError] we show
///     the message inline and clear the submitting flag. No fragile
///     `runtimeType.toString()` checks.
class RegisterScreen extends StatefulWidget {
  const RegisterScreen({super.key});

  @override
  State<RegisterScreen> createState() => _RegisterScreenState();
}

class _RegisterScreenState extends State<RegisterScreen> {
  final _data = _WizardData();
  int _step = 0;
  static const _totalSteps = 4;

  bool _submitting = false;
  bool _finished = false;
  String? _error;

  @override
  void dispose() {
    _data.dispose();
    super.dispose();
  }

  void _goNext() {
    setState(() => _error = null);
    switch (_step) {
      case 0:
        if (!_data.validateOwner()) return;
        break;
      case 1:
        if (!_data.validateAddress()) return;
        break;
      case 2:
        if (!_data.validateContact()) {
          setState(() => _error = _contactError());
          HapticFeedback.heavyImpact();
          return;
        }
        break;
    }
    if (_step < _totalSteps - 1) {
      FocusScope.of(context).unfocus();
      setState(() => _step++);
    }
  }

  String _contactError() {
    if (!_data.phoneVerified) {
      return 'Telefonunuzu WhatsApp ile doğrulayın.';
    }
    if (!_data.emailVerified) {
      return 'E-posta adresinizi doğrulayın.';
    }
    return 'İletişim bilgilerinizi doğrulayın.';
  }

  void _goBack() {
    setState(() => _error = null);
    if (_step > 0) {
      FocusScope.of(context).unfocus();
      setState(() => _step--);
    } else {
      Navigator.of(context).maybePop();
    }
  }

  Future<void> _submit() async {
    if (!_data.validateCredentials()) return;
    if (!_data.termsAccepted) {
      setState(() => _error = 'Kullanım koşullarını kabul edin.');
      HapticFeedback.heavyImpact();
      return;
    }
    FocusScope.of(context).unfocus();
    setState(() {
      _submitting = true;
      _error = null;
    });
    try {
      await context
          .read<AuthCubit>()
          .registerBusiness(_data.asRegisterPayload());
      // The rest is handled by the BlocListener in build() — it watches
      // the cubit and navigates on Authenticated or surfaces the error
      // on AuthError. This avoids fragile state-type string comparisons.
    } on DioException catch (e) {
      if (!mounted) return;
      setState(() {
        _error = _extractError(e);
        _submitting = false;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _error = 'Beklenmeyen bir hata oluştu.';
        _submitting = false;
      });
    }
  }

  String _extractError(DioException e) {
    final data = e.response?.data;
    if (data is Map) {
      return data['error']?.toString() ??
          data['message']?.toString() ??
          'Bir hata oluştu.';
    }
    if (e.type == DioExceptionType.connectionTimeout ||
        e.type == DioExceptionType.receiveTimeout) {
      return 'Bağlantı zaman aşımına uğradı.';
    }
    if (e.type == DioExceptionType.connectionError) {
      return 'İnternet bağlantınızı kontrol edin.';
    }
    return 'Beklenmeyen bir hata oluştu.';
  }

  @override
  Widget build(BuildContext context) {
    if (_finished) {
      return _SuccessScreen(
        businessName: _data.businessName.text.trim(),
      );
    }

    return BlocListener<AuthCubit, AuthState>(
      listenWhen: (p, c) => _submitting && c is! AuthLoading,
      listener: (context, state) async {
        if (state is Authenticated) {
          // Capture the router BEFORE any async gap so we don't
          // reach across `await` with a stale context.
          final router = GoRouter.of(context);
          try {
            final prefs = await SharedPreferences.getInstance();
            await prefs.setBool('qordy_pending_onboarding', true);
            await prefs.setString('qordy_pending_onboarding_name',
                _data.businessName.text.trim());
            await prefs.setString('qordy_pending_onboarding_sub',
                _data.subdomain.text.trim());
          } catch (_) {/* non-fatal */}
          if (!mounted) return;
          setState(() {
            _submitting = false;
            _finished = true;
          });
          // The router's global redirect rule already sends authenticated
          // users at `/register` to `RoleHome.initialRouteFor(role)` on
          // its own — but the success screen is our chance to show a
          // branded "welcome" animation before the dashboard takes over.
          Future.delayed(const Duration(milliseconds: 1400), () {
            if (!mounted) return;
            router.go('/dashboard');
          });
        } else if (state is AuthError) {
          if (!mounted) return;
          HapticFeedback.heavyImpact();
          setState(() {
            _submitting = false;
            _error = state.message;
          });
        }
      },
      child: AuthLayout(
        title: 'Yeni İşletme',
        subtitle: '4 adımda hesabını oluştur · 7 gün ücretsiz.',
        bottom: _BottomActionBar(
          step: _step,
          total: _totalSteps,
          submitting: _submitting,
          onBack: _submitting ? null : _goBack,
          onNext: _submitting
              ? null
              : (_step == _totalSteps - 1 ? _submit : _goNext),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            _StepIndicator(step: _step, total: _totalSteps),
            const SizedBox(height: 22),
            if (_error != null) ...[
              _ErrorBanner(message: _error!),
              const SizedBox(height: 16),
            ],
            AnimatedSwitcher(
              duration: const Duration(milliseconds: 240),
              switchInCurve: Curves.easeOutCubic,
              switchOutCurve: Curves.easeInCubic,
              transitionBuilder: (child, anim) {
                final offset = Tween<Offset>(
                  begin: const Offset(0.04, 0),
                  end: Offset.zero,
                ).animate(anim);
                return FadeTransition(
                  opacity: anim,
                  child: SlideTransition(position: offset, child: child),
                );
              },
              child: KeyedSubtree(
                key: ValueKey(_step),
                child: _buildCurrentStep(),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildCurrentStep() {
    switch (_step) {
      case 0:
        return _OwnerStep(data: _data);
      case 1:
        return _AddressStep(
          data: _data,
          onDataChanged: () => setState(() {}),
        );
      case 2:
        return _ContactStep(
          data: _data,
          onChanged: () => setState(() {}),
        );
      case 3:
        return _SecurityStep(
          data: _data,
          onTermsChanged: () => setState(() {}),
        );
      default:
        return const SizedBox.shrink();
    }
  }
}

/// Sticky bottom action bar: Back + Primary CTA sitting above the
/// keyboard. Passed to [AuthLayout] via its `bottom` slot.
class _BottomActionBar extends StatelessWidget {
  const _BottomActionBar({
    required this.step,
    required this.total,
    required this.submitting,
    required this.onBack,
    required this.onNext,
  });

  final int step;
  final int total;
  final bool submitting;
  final VoidCallback? onBack;
  final VoidCallback? onNext;

  @override
  Widget build(BuildContext context) {
    final isLast = step == total - 1;
    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Row(
          children: [
            Expanded(
              flex: 2,
              child: OutlinedButton.icon(
                onPressed: onBack,
                icon: const Icon(Icons.arrow_back_rounded, size: 18),
                label: Text(step == 0 ? 'İptal' : 'Geri'),
                style: OutlinedButton.styleFrom(
                  padding: const EdgeInsets.symmetric(vertical: 15),
                  side: const BorderSide(color: AppColors.border),
                  foregroundColor: AppColors.textSecondary,
                ),
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              flex: 3,
              child: PrimaryActionButton(
                label: isLast ? 'Kaydı Tamamla' : 'Devam Et',
                onPressed: onNext,
                loading: submitting,
                icon: isLast
                    ? Icons.check_circle_outline_rounded
                    : Icons.arrow_forward_rounded,
              ),
            ),
          ],
        ),
        const SizedBox(height: 8),
        Center(
          child: TextButton(
            onPressed: submitting
                ? null
                : () => Navigator.of(context).maybePop(),
            child: const Text('Zaten hesabım var · Giriş yap'),
          ),
        ),
      ],
    );
  }
}

// ---------------------------------------------------------------------------
// Shared wizard state
// ---------------------------------------------------------------------------
class _WizardData {
  final fullName = TextEditingController();
  final businessName = TextEditingController();
  final subdomain = TextEditingController();
  final phone = TextEditingController();
  final email = TextEditingController();
  final password = TextEditingController();
  final passwordConfirm = TextEditingController();

  bool emailVerified = false;
  bool phoneVerified = false;
  bool termsAccepted = false;
  bool subdomainAvailable = true;
  String? subdomainError;

  final ownerKey = GlobalKey<FormState>();
  final addressKey = GlobalKey<FormState>();
  final contactKey = GlobalKey<FormState>();
  final securityKey = GlobalKey<FormState>();

  bool validateOwner() => ownerKey.currentState?.validate() ?? false;

  bool validateAddress() {
    final ok = addressKey.currentState?.validate() ?? false;
    return ok && subdomainError == null && subdomainAvailable;
  }

  bool validateContact() {
    final ok = contactKey.currentState?.validate() ?? false;
    return ok && phoneVerified && emailVerified;
  }

  bool validateCredentials() =>
      securityKey.currentState?.validate() ?? false;

  Map<String, dynamic> asRegisterPayload() {
    final parts = fullName.text.trim().split(RegExp(r'\s+'));
    final first = parts.isNotEmpty ? parts.first : '';
    final last = parts.length > 1 ? parts.sublist(1).join(' ') : '';
    return {
      'company_name': businessName.text.trim(),
      'subdomain': subdomain.text.trim().toLowerCase(),
      'first_name': first,
      'last_name': last,
      'name': fullName.text.trim(),
      'email': email.text.trim(),
      'phone': '+90${phone.text.trim()}',
      'password': password.text,
      'password_confirmation': passwordConfirm.text,
      'email_verified': emailVerified,
      'phone_verified': phoneVerified,
    };
  }

  void dispose() {
    fullName.dispose();
    businessName.dispose();
    subdomain.dispose();
    phone.dispose();
    email.dispose();
    password.dispose();
    passwordConfirm.dispose();
  }
}

// ---------------------------------------------------------------------------
// Steps
// ---------------------------------------------------------------------------

class _OwnerStep extends StatelessWidget {
  final _WizardData data;
  const _OwnerStep({required this.data});

  @override
  Widget build(BuildContext context) {
    return Form(
      key: data.ownerKey,
      autovalidateMode: AutovalidateMode.onUserInteraction,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const _StepHead(
            icon: Icons.person_rounded,
            title: 'Hakkınızda',
            hint: 'Adınız ve işletmenizin resmi tam ünvanı.',
          ),
          const SizedBox(height: 22),
          const _Label('Ad Soyad'),
          const SizedBox(height: 6),
          BrandInputGroup(
            leading: const Icon(Icons.badge_outlined,
                size: 18, color: AppColors.textHint),
            child: _plainField(
              controller: data.fullName,
              hint: 'Adınız Soyadınız',
              keyboardType: TextInputType.name,
              textInputAction: TextInputAction.next,
              textCapitalization: TextCapitalization.words,
              validator: (v) {
                final trimmed = (v ?? '').trim();
                if (trimmed.isEmpty) return 'Ad soyad gerekli';
                if (trimmed.split(RegExp(r'\s+')).length < 2) {
                  return 'Ad ve soyadınızı girin';
                }
                if (trimmed.length < 4) return 'En az 4 karakter';
                return null;
              },
            ),
          ),
          const SizedBox(height: 16),
          const _Label('İşletme Ünvanı'),
          const SizedBox(height: 6),
          BrandInputGroup(
            leading: const Icon(Icons.storefront_outlined,
                size: 18, color: AppColors.textHint),
            child: _plainField(
              controller: data.businessName,
              hint: 'Cadde Cafe Rest. Gıda Ltd. Şti.',
              textInputAction: TextInputAction.done,
              textCapitalization: TextCapitalization.words,
              validator: (v) {
                final trimmed = (v ?? '').trim();
                if (trimmed.isEmpty) return 'İşletme ünvanı gerekli';
                if (trimmed.length < 3) return 'En az 3 karakter';
                return null;
              },
            ),
          ),
          const SizedBox(height: 6),
          const _InlineHint(
            text: 'Ticaret sicilinde görünen uzun/tam ünvanı girin.',
          ),
        ],
      ),
    );
  }
}

class _AddressStep extends StatefulWidget {
  final _WizardData data;
  final VoidCallback onDataChanged;
  const _AddressStep({required this.data, required this.onDataChanged});

  @override
  State<_AddressStep> createState() => _AddressStepState();
}

class _AddressStepState extends State<_AddressStep> {
  Timer? _debounce;
  bool _checking = false;
  bool _userTouchedSubdomain = false;

  @override
  void initState() {
    super.initState();
    widget.data.businessName.addListener(_autoFillSubdomain);
    widget.data.subdomain.addListener(_onSubdomainChanged);
    if (widget.data.subdomain.text.isEmpty) {
      _autoFillSubdomain(initial: true);
    }
  }

  @override
  void dispose() {
    _debounce?.cancel();
    widget.data.businessName.removeListener(_autoFillSubdomain);
    widget.data.subdomain.removeListener(_onSubdomainChanged);
    super.dispose();
  }

  String _slugify(String s) => s
      .toLowerCase()
      .replaceAll(RegExp(r'[çÇ]'), 'c')
      .replaceAll(RegExp(r'[ğĞ]'), 'g')
      .replaceAll(RegExp(r'[ıİ]'), 'i')
      .replaceAll(RegExp(r'[öÖ]'), 'o')
      .replaceAll(RegExp(r'[şŞ]'), 's')
      .replaceAll(RegExp(r'[üÜ]'), 'u')
      .replaceAll(RegExp(r'[^a-z0-9]'), '');

  void _autoFillSubdomain({bool initial = false}) {
    if (!initial && _userTouchedSubdomain) return;
    final slug = _slugify(widget.data.businessName.text);
    if (slug.isEmpty) return;
    final capped = slug.length > 24 ? slug.substring(0, 24) : slug;
    if (capped != widget.data.subdomain.text) {
      widget.data.subdomain.value = TextEditingValue(
        text: capped,
        selection: TextSelection.collapsed(offset: capped.length),
      );
    }
  }

  void _onSubdomainChanged() {
    _userTouchedSubdomain = true;
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 500), _checkAvailability);
  }

  Future<void> _checkAvailability() async {
    final slug = widget.data.subdomain.text.trim();
    if (slug.length < 3) {
      setState(() {
        widget.data.subdomainError =
            slug.isEmpty ? null : 'En az 3 karakter';
        widget.data.subdomainAvailable = false;
      });
      widget.onDataChanged();
      return;
    }
    setState(() {
      _checking = true;
      widget.data.subdomainError = null;
    });
    try {
      final dio = GetIt.instance<Dio>();
      final resp = await dio.post(
        ApiConfig.validateSubdomain,
        data: {'subdomain': slug},
      );
      final body = resp.data;
      final exists = body is Map && body['success'] == true;
      setState(() {
        widget.data.subdomainAvailable = !exists;
        widget.data.subdomainError = exists ? 'Bu ad zaten alınmış' : null;
      });
    } on DioException catch (e) {
      final status = e.response?.statusCode ?? 0;
      setState(() {
        widget.data.subdomainAvailable = status == 404;
        widget.data.subdomainError =
            status == 404 ? null : 'Kontrol edilemedi';
      });
    } catch (_) {
      setState(() {
        widget.data.subdomainAvailable = true;
        widget.data.subdomainError = null;
      });
    } finally {
      if (mounted) setState(() => _checking = false);
      widget.onDataChanged();
    }
  }

  @override
  Widget build(BuildContext context) {
    final d = widget.data;
    final slug = d.subdomain.text.trim();
    return Form(
      key: d.addressKey,
      autovalidateMode: AutovalidateMode.onUserInteraction,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const _StepHead(
            icon: Icons.storefront_rounded,
            title: 'İşletme Kısa Adı',
            hint: 'Personeliniz bu kısa adla uygulamadan giriş yapar.',
          ),
          const SizedBox(height: 22),
          const _Label('Giriş Adı'),
          const SizedBox(height: 6),
          BrandInputGroup(
            leading: const Icon(Icons.link_rounded,
                size: 18, color: AppColors.textHint),
            trailing: _checking
                ? const Padding(
                    padding: EdgeInsets.symmetric(horizontal: 12),
                    child: SizedBox(
                      width: 16,
                      height: 16,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    ),
                  )
                : (slug.isNotEmpty
                    ? Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 10),
                        child: Icon(
                          d.subdomainAvailable && d.subdomainError == null
                              ? Icons.check_circle_rounded
                              : Icons.error_outline_rounded,
                          size: 20,
                          color:
                              d.subdomainAvailable && d.subdomainError == null
                                  ? AppColors.success
                                  : AppColors.error,
                        ),
                      )
                    : null),
            child: _plainField(
              controller: d.subdomain,
              hint: 'kafeistanbul',
              keyboardType: TextInputType.url,
              textInputAction: TextInputAction.done,
              inputFormatters: [
                FilteringTextInputFormatter.allow(RegExp(r'[a-z0-9]')),
                LengthLimitingTextInputFormatter(24),
              ],
              validator: (v) {
                final trimmed = (v ?? '').trim();
                if (trimmed.isEmpty) return 'Giriş adı gerekli';
                if (trimmed.length < 3) return 'En az 3 karakter';
                if (d.subdomainError != null) return d.subdomainError;
                return null;
              },
            ),
          ),
          const SizedBox(height: 10),
          _SubdomainPreview(slug: slug),
          const SizedBox(height: 6),
          const _InlineHint(
            text:
                'Sadece küçük harf ve rakam · Türkçe karakterler otomatik düzeltilir.',
          ),
        ],
      ),
    );
  }
}

class _SubdomainPreview extends StatelessWidget {
  final String slug;
  const _SubdomainPreview({required this.slug});

  @override
  Widget build(BuildContext context) {
    final display = slug.isEmpty ? 'isletme' : slug;
    final isDark = Theme.of(context).brightness == Brightness.dark;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 11),
      decoration: BoxDecoration(
        color: isDark
            ? AppColors.primary.withValues(alpha: 0.12)
            : AppColors.primarySoft,
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: AppColors.primary.withValues(alpha: 0.18)),
      ),
      child: Row(
        children: [
          Icon(Icons.verified_user_rounded,
              size: 15,
              color:
                  isDark ? AppColors.primaryDarkMode : AppColors.primaryDark),
          const SizedBox(width: 10),
          Expanded(
            child: Text.rich(
              TextSpan(
                children: [
                  TextSpan(
                    text: 'İşletme kısa adın: ',
                    style: TextStyle(
                      fontSize: 12,
                      color: isDark
                          ? AppColors.primaryDarkMode
                          : AppColors.primaryDark,
                    ),
                  ),
                  TextSpan(
                    text: display,
                    style: TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.w700,
                      color: isDark
                          ? AppColors.primaryDarkMode
                          : AppColors.primaryDark,
                    ),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _ContactStep extends StatefulWidget {
  final _WizardData data;
  final VoidCallback onChanged;
  const _ContactStep({required this.data, required this.onChanged});

  @override
  State<_ContactStep> createState() => _ContactStepState();
}

class _ContactStepState extends State<_ContactStep> {
  @override
  Widget build(BuildContext context) {
    final d = widget.data;
    return Form(
      key: d.contactKey,
      autovalidateMode: AutovalidateMode.onUserInteraction,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const _StepHead(
            icon: Icons.verified_user_rounded,
            title: 'İletişim Doğrulaması',
            hint: 'Önce telefon, sonra e-posta. Her ikisi zorunludur.',
          ),
          const SizedBox(height: 22),

          // ── Telefon ────────────────────────────────────────────────
          const _Label('Telefon · WhatsApp'),
          const SizedBox(height: 6),
          BrandInputGroup(
            leading: Text(
              '🇹🇷  +90',
              style: TextStyle(
                fontSize: 14,
                fontWeight: FontWeight.w600,
                color: context.brandTextPrimary,
              ),
            ),
            trailing: d.phoneVerified
                ? const Padding(
                    padding: EdgeInsets.symmetric(horizontal: 10),
                    child: Icon(Icons.verified_rounded,
                        size: 20, color: AppColors.success),
                  )
                : null,
            child: _plainField(
              controller: d.phone,
              hint: '5XX XXX XX XX',
              keyboardType: TextInputType.phone,
              enabled: !d.phoneVerified,
              inputFormatters: [
                FilteringTextInputFormatter.digitsOnly,
                LengthLimitingTextInputFormatter(10),
              ],
              validator: (v) {
                final t = (v ?? '').trim();
                if (t.length != 10) return '10 haneli numara girin';
                if (!t.startsWith('5')) return '5 ile başlamalı';
                return null;
              },
            ),
          ),
          const SizedBox(height: 10),
          _VerifyCta(
            verified: d.phoneVerified,
            icon: Icons.phone_iphone_rounded,
            label:
                d.phoneVerified ? 'Telefon doğrulandı' : 'WhatsApp ile Doğrula',
            onTap: d.phoneVerified
                ? null
                : () async {
                    final phoneOk = d.phone.text.trim().length == 10 &&
                        d.phone.text.trim().startsWith('5');
                    if (!phoneOk) {
                      HapticFeedback.heavyImpact();
                      ScaffoldMessenger.of(context).showSnackBar(
                        const SnackBar(
                          content:
                              Text('Önce geçerli bir telefon numarası girin.'),
                        ),
                      );
                      return;
                    }
                    final verified = await _openCodeSheet(
                      context: context,
                      title: 'WhatsApp Doğrulama',
                      description:
                          '+90${d.phone.text.trim()} numarasına 6 haneli kod gönderildi.',
                      sendEndpoint: ApiConfig.registerSendPhoneCode,
                      verifyEndpoint: ApiConfig.registerVerifyPhone,
                      sendPayload: {
                        'phone': d.phone.text.trim(),
                        'country_code': '+90',
                      },
                      verifyPayload: (code) => {
                        'phone': d.phone.text.trim(),
                        'country_code': '+90',
                        'code': code,
                      },
                    );
                    if (verified == true && mounted) {
                      HapticFeedback.mediumImpact();
                      setState(() => d.phoneVerified = true);
                      widget.onChanged();
                    }
                  },
          ),

          const SizedBox(height: 22),
          _EmailRevealPanel(data: d, onChanged: widget.onChanged),
        ],
      ),
    );
  }
}

class _EmailRevealPanel extends StatefulWidget {
  final _WizardData data;
  final VoidCallback onChanged;
  const _EmailRevealPanel({required this.data, required this.onChanged});

  @override
  State<_EmailRevealPanel> createState() => _EmailRevealPanelState();
}

class _EmailRevealPanelState extends State<_EmailRevealPanel> {
  @override
  Widget build(BuildContext context) {
    final d = widget.data;
    final isDark = Theme.of(context).brightness == Brightness.dark;

    if (!d.phoneVerified) {
      return Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: isDark ? AppColors.darkSurfaceMuted : AppColors.surfaceMuted,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(
              color: isDark ? AppColors.darkBorder : AppColors.border),
        ),
        child: Row(
          children: [
            Container(
              width: 34,
              height: 34,
              decoration: const BoxDecoration(
                color: AppColors.primarySoft,
                shape: BoxShape.circle,
              ),
              child: const Icon(Icons.lock_outline_rounded,
                  size: 17, color: AppColors.primary),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Text(
                'E-posta alanı, telefon onayından sonra açılır.',
                style: TextStyle(
                  fontSize: 12.5,
                  color: isDark
                      ? AppColors.darkTextSecondary
                      : AppColors.textSecondary,
                  height: 1.4,
                ),
              ),
            ),
          ],
        ),
      );
    }

    return AnimatedSize(
      duration: const Duration(milliseconds: 260),
      curve: Curves.easeOutCubic,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const _Label('E-posta'),
          const SizedBox(height: 6),
          BrandInputGroup(
            leading: const Icon(Icons.alternate_email_rounded,
                size: 18, color: AppColors.textHint),
            trailing: d.emailVerified
                ? const Padding(
                    padding: EdgeInsets.symmetric(horizontal: 10),
                    child: Icon(Icons.verified_rounded,
                        size: 20, color: AppColors.success),
                  )
                : null,
            child: _plainField(
              controller: d.email,
              hint: 'ornek@email.com',
              keyboardType: TextInputType.emailAddress,
              textInputAction: TextInputAction.done,
              enabled: !d.emailVerified,
              validator: (v) {
                final t = (v ?? '').trim();
                if (t.isEmpty) return 'E-posta gerekli';
                final r = RegExp(
                    r'^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$');
                if (!r.hasMatch(t)) return 'Geçerli bir e-posta girin';
                return null;
              },
            ),
          ),
          const SizedBox(height: 10),
          _VerifyCta(
            verified: d.emailVerified,
            icon: Icons.mark_email_read_outlined,
            label: d.emailVerified
                ? 'E-posta doğrulandı'
                : 'E-posta ile Doğrula',
            onTap: d.emailVerified
                ? null
                : () async {
                    final form = d.contactKey.currentState;
                    if (form == null || !form.validate()) return;
                    final verified = await _openCodeSheet(
                      context: context,
                      title: 'E-posta Doğrulama',
                      description:
                          '${d.email.text.trim()} adresine 6 haneli kod gönderildi.',
                      sendEndpoint: ApiConfig.registerSendEmailCode,
                      verifyEndpoint: ApiConfig.registerVerifyEmail,
                      sendPayload: {'email': d.email.text.trim()},
                      verifyPayload: (code) => {
                        'email': d.email.text.trim(),
                        'code': code,
                      },
                    );
                    if (verified == true && mounted) {
                      HapticFeedback.mediumImpact();
                      setState(() => d.emailVerified = true);
                      widget.onChanged();
                    }
                  },
          ),
        ],
      ),
    );
  }
}

class _VerifyCta extends StatelessWidget {
  final IconData icon;
  final String label;
  final bool verified;
  final VoidCallback? onTap;
  const _VerifyCta({
    required this.icon,
    required this.label,
    required this.verified,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    final disabled = onTap == null;
    return SizedBox(
      width: double.infinity,
      height: 46,
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(12),
          child: AnimatedContainer(
            duration: const Duration(milliseconds: 200),
            decoration: BoxDecoration(
              color: verified
                  ? AppColors.success.withValues(alpha: 0.10)
                  : AppColors.primarySoft,
              borderRadius: BorderRadius.circular(12),
              border: Border.all(
                color: verified
                    ? AppColors.success.withValues(alpha: 0.35)
                    : AppColors.primary.withValues(alpha: 0.22),
                width: 1.2,
              ),
            ),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Icon(
                  verified ? Icons.check_circle_rounded : icon,
                  size: 18,
                  color: verified ? AppColors.success : AppColors.primary,
                ),
                const SizedBox(width: 8),
                Text(
                  label,
                  style: TextStyle(
                    fontSize: 13.5,
                    fontWeight: FontWeight.w700,
                    letterSpacing: 0.1,
                    color: disabled
                        ? AppColors.success
                        : (verified
                            ? AppColors.success
                            : AppColors.primary),
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

class _SecurityStep extends StatefulWidget {
  final _WizardData data;
  final VoidCallback onTermsChanged;
  const _SecurityStep({
    required this.data,
    required this.onTermsChanged,
  });

  @override
  State<_SecurityStep> createState() => _SecurityStepState();
}

class _SecurityStepState extends State<_SecurityStep> {
  bool _obscurePw = true;
  bool _obscureConfirm = true;

  _PwScore _pwScore = _PwScore.empty;

  @override
  void initState() {
    super.initState();
    widget.data.password.addListener(_recalc);
    _recalc();
  }

  @override
  void dispose() {
    widget.data.password.removeListener(_recalc);
    super.dispose();
  }

  void _recalc() {
    final next = _PwScore.evaluate(widget.data.password.text);
    if (next != _pwScore) setState(() => _pwScore = next);
  }

  @override
  Widget build(BuildContext context) {
    final d = widget.data;
    return Form(
      key: d.securityKey,
      autovalidateMode: AutovalidateMode.onUserInteraction,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const _StepHead(
            icon: Icons.lock_rounded,
            title: 'Şifre Belirle',
            hint: 'Hesabınıza bu şifre ile giriş yapacaksınız.',
          ),
          const SizedBox(height: 22),
          const _Label('Şifre'),
          const SizedBox(height: 6),
          BrandInputGroup(
            leading: const Icon(Icons.lock_outline_rounded,
                size: 18, color: AppColors.textHint),
            trailing: _EyeToggle(
              obscured: _obscurePw,
              onToggle: () => setState(() => _obscurePw = !_obscurePw),
            ),
            child: _plainField(
              controller: d.password,
              hint: 'En az 8 karakter',
              obscureText: _obscurePw,
              textInputAction: TextInputAction.next,
              validator: (v) {
                if (v == null || v.isEmpty) return 'Şifre gerekli';
                if (v.length < 8) return 'En az 8 karakter';
                if (!RegExp(r'[A-Za-z]').hasMatch(v) ||
                    !RegExp(r'[0-9]').hasMatch(v)) {
                  return 'Harf ve rakam içermeli';
                }
                return null;
              },
            ),
          ),
          const SizedBox(height: 8),
          _PasswordStrengthMeter(score: _pwScore),
          const SizedBox(height: 16),
          const _Label('Şifre (tekrar)'),
          const SizedBox(height: 6),
          BrandInputGroup(
            leading: const Icon(Icons.lock_outline_rounded,
                size: 18, color: AppColors.textHint),
            trailing: _EyeToggle(
              obscured: _obscureConfirm,
              onToggle: () =>
                  setState(() => _obscureConfirm = !_obscureConfirm),
            ),
            child: _plainField(
              controller: d.passwordConfirm,
              hint: 'Tekrar girin',
              obscureText: _obscureConfirm,
              textInputAction: TextInputAction.done,
              validator: (v) {
                if (v == null || v.isEmpty) return 'Şifre tekrarı gerekli';
                if (v != d.password.text) return 'Şifreler eşleşmiyor';
                return null;
              },
            ),
          ),
          const SizedBox(height: 18),
          _SummaryCard(data: d),
          const SizedBox(height: 14),
          InkWell(
            onTap: () {
              HapticFeedback.selectionClick();
              d.termsAccepted = !d.termsAccepted;
              widget.onTermsChanged();
            },
            borderRadius: BorderRadius.circular(10),
            child: Padding(
              padding: const EdgeInsets.symmetric(vertical: 4),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  SizedBox(
                    width: 22,
                    height: 22,
                    child: Checkbox(
                      value: d.termsAccepted,
                      onChanged: (v) {
                        d.termsAccepted = v ?? false;
                        widget.onTermsChanged();
                      },
                      activeColor: AppColors.primary,
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(4),
                      ),
                      side: const BorderSide(color: AppColors.border),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Text(
                      'Kullanım koşullarını ve gizlilik politikasını kabul ediyorum.',
                      style: TextStyle(
                        fontSize: 12.5,
                        color: context.brandTextSecondary,
                        height: 1.45,
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

/// Live password strength meter: 4 segments, tier label, colour ramp.
class _PasswordStrengthMeter extends StatelessWidget {
  final _PwScore score;
  const _PasswordStrengthMeter({required this.score});

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final baseBar = isDark ? AppColors.darkBorder : AppColors.border;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            for (int i = 0; i < 4; i++) ...[
              Expanded(
                child: AnimatedContainer(
                  duration: const Duration(milliseconds: 200),
                  height: 5,
                  decoration: BoxDecoration(
                    color: score.filledSegments > i
                        ? score.color
                        : baseBar.withValues(alpha: 0.7),
                    borderRadius: BorderRadius.circular(3),
                  ),
                ),
              ),
              if (i < 3) const SizedBox(width: 6),
            ],
          ],
        ),
        const SizedBox(height: 6),
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Text(
              score.label,
              style: TextStyle(
                fontSize: 11.5,
                fontWeight: FontWeight.w700,
                color: score.color,
                letterSpacing: 0.2,
              ),
            ),
            if (score.hint != null)
              Flexible(
                child: Text(
                  score.hint!,
                  textAlign: TextAlign.right,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    fontSize: 11,
                    color: isDark
                        ? AppColors.darkTextHint
                        : AppColors.textHint,
                  ),
                ),
              ),
          ],
        ),
      ],
    );
  }
}

/// Password classification — deliberately simple, deterministic, and
/// tied only to rules the server also enforces. No zxcvbn dependency.
class _PwScore {
  const _PwScore._(this.filledSegments, this.label, this.color, this.hint);

  final int filledSegments;
  final String label;
  final Color color;
  final String? hint;

  static const empty =
      _PwScore._(0, 'Şifre girin', AppColors.textHint, 'en az 8 karakter');

  static _PwScore evaluate(String pw) {
    if (pw.isEmpty) return empty;

    int points = 0;
    if (pw.length >= 8) points++;
    if (pw.length >= 12) points++;
    if (RegExp(r'[A-Z]').hasMatch(pw)) points++;
    if (RegExp(r'[0-9]').hasMatch(pw)) points++;
    if (RegExp(r'[^A-Za-z0-9]').hasMatch(pw)) points++;

    // Cap at 4 visible segments. Grade:
    //   1 → Çok zayıf, 2 → Zayıf, 3 → İyi, 4+ → Güçlü.
    final segs = points.clamp(1, 4);
    switch (segs) {
      case 1:
        return _PwScore._(
          1,
          'Çok zayıf',
          AppColors.error,
          'büyük harf + rakam ekle',
        );
      case 2:
        return _PwScore._(
          2,
          'Zayıf',
          const Color(0xFFE67E22),
          '12+ karakter ve semboller',
        );
      case 3:
        return _PwScore._(
          3,
          'İyi',
          AppColors.warning,
          'sembol eklersen güçlü olur',
        );
      default:
        return _PwScore._(
          4,
          'Güçlü',
          AppColors.success,
          null,
        );
    }
  }

  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is _PwScore &&
          other.filledSegments == filledSegments &&
          other.label == label);

  @override
  int get hashCode => Object.hash(filledSegments, label);
}

class _EyeToggle extends StatelessWidget {
  final bool obscured;
  final VoidCallback onToggle;
  const _EyeToggle({required this.obscured, required this.onToggle});

  @override
  Widget build(BuildContext context) {
    return IconButton(
      splashRadius: 18,
      icon: Icon(
        obscured ? Icons.visibility_off_outlined : Icons.visibility_outlined,
        size: 20,
        color: AppColors.textHint,
      ),
      onPressed: () {
        HapticFeedback.selectionClick();
        onToggle();
      },
    );
  }
}

class _SummaryCard extends StatelessWidget {
  final _WizardData data;
  const _SummaryCard({required this.data});

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final rows = [
      _SummaryRow(
        icon: Icons.person_outline_rounded,
        label: 'Kullanıcı',
        value: data.fullName.text.trim(),
      ),
      _SummaryRow(
        icon: Icons.storefront_outlined,
        label: 'İşletme',
        value: data.businessName.text.trim(),
      ),
      _SummaryRow(
        icon: Icons.verified_user_rounded,
        label: 'İşletme Kısa Adı',
        value: data.subdomain.text.trim(),
      ),
      _SummaryRow(
        icon: Icons.phone_iphone_rounded,
        label: 'Telefon',
        value: '+90 ${data.phone.text.trim()}',
        verified: data.phoneVerified,
      ),
      _SummaryRow(
        icon: Icons.alternate_email_rounded,
        label: 'E-posta',
        value: data.email.text.trim(),
        verified: data.emailVerified,
      ),
    ];

    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: isDark ? AppColors.darkCard : AppColors.card,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(
          color: isDark ? AppColors.darkBorder : AppColors.border,
        ),
      ),
      child: Column(
        children: [
          for (int i = 0; i < rows.length; i++) ...[
            rows[i],
            if (i < rows.length - 1)
              Padding(
                padding: const EdgeInsets.symmetric(vertical: 9),
                child: Divider(
                  height: 1,
                  color: isDark ? AppColors.darkBorder : AppColors.divider,
                ),
              ),
          ],
        ],
      ),
    );
  }
}

class _SummaryRow extends StatelessWidget {
  final IconData icon;
  final String label;
  final String value;
  final bool verified;
  const _SummaryRow({
    required this.icon,
    required this.label,
    required this.value,
    this.verified = false,
  });

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    return Row(
      children: [
        Icon(
          icon,
          size: 15,
          color: isDark ? AppColors.darkTextSecondary : AppColors.textSecondary,
        ),
        const SizedBox(width: 10),
        Text(
          label,
          style: TextStyle(
            fontSize: 12,
            color: isDark
                ? AppColors.darkTextSecondary
                : AppColors.textSecondary,
          ),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Text(
            value.isEmpty ? '—' : value,
            textAlign: TextAlign.right,
            style: TextStyle(
              fontSize: 12.5,
              fontWeight: FontWeight.w600,
              color: isDark
                  ? AppColors.darkTextPrimary
                  : AppColors.textPrimary,
            ),
            overflow: TextOverflow.ellipsis,
          ),
        ),
        if (verified) ...[
          const SizedBox(width: 7),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
            decoration: BoxDecoration(
              color: AppColors.success.withValues(alpha: 0.12),
              borderRadius: BorderRadius.circular(5),
            ),
            child: const Icon(
              Icons.check_rounded,
              size: 12,
              color: AppColors.success,
            ),
          ),
        ],
      ],
    );
  }
}

// ---------------------------------------------------------------------------
// Atoms: step indicator, head block, labels, hint, error
// ---------------------------------------------------------------------------

class _StepIndicator extends StatelessWidget {
  final int step;
  final int total;
  const _StepIndicator({required this.step, required this.total});

  static const _labels = [
    'Hakkınızda',
    'İşletme Adı',
    'İletişim',
    'Şifre',
  ];

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            for (int i = 0; i < total; i++) ...[
              Expanded(
                child: AnimatedContainer(
                  duration: const Duration(milliseconds: 220),
                  height: 4,
                  decoration: BoxDecoration(
                    color: i <= step
                        ? AppColors.primary
                        : AppColors.primary.withValues(alpha: 0.14),
                    borderRadius: BorderRadius.circular(3),
                  ),
                ),
              ),
              if (i < total - 1) const SizedBox(width: 6),
            ],
          ],
        ),
        const SizedBox(height: 10),
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Text(
              '${step + 1} / $total · ${_labels[step]}',
              style: TextStyle(
                fontSize: 11.5,
                fontWeight: FontWeight.w700,
                color: isDark
                    ? AppColors.darkTextSecondary
                    : AppColors.textSecondary,
                letterSpacing: 0.5,
              ),
            ),
            Text(
              '${(((step + 1) / total) * 100).round()}%',
              style: const TextStyle(
                fontSize: 11.5,
                fontWeight: FontWeight.w700,
                color: AppColors.primary,
                letterSpacing: 0.3,
              ),
            ),
          ],
        ),
      ],
    );
  }
}

/// Compact step head — a single row: icon pill + title + one-line hint.
/// Replaces the verbose paragraph description the previous pass had.
class _StepHead extends StatelessWidget {
  final IconData icon;
  final String title;
  final String hint;
  const _StepHead({
    required this.icon,
    required this.title,
    required this.hint,
  });

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    return Row(
      crossAxisAlignment: CrossAxisAlignment.center,
      children: [
        Container(
          width: 38,
          height: 38,
          decoration: BoxDecoration(
            color: AppColors.primary.withValues(alpha: 0.12),
            borderRadius: BorderRadius.circular(11),
          ),
          alignment: Alignment.center,
          child: Icon(icon, size: 20, color: AppColors.primary),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                title,
                style: TextStyle(
                  fontSize: 16,
                  fontWeight: FontWeight.w700,
                  letterSpacing: -0.1,
                  color: isDark
                      ? AppColors.darkTextPrimary
                      : AppColors.textPrimary,
                ),
              ),
              const SizedBox(height: 2),
              Text(
                hint,
                style: TextStyle(
                  fontSize: 12,
                  height: 1.35,
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

class _InlineHint extends StatelessWidget {
  final String text;
  const _InlineHint({required this.text});

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    return Padding(
      padding: const EdgeInsets.only(top: 6),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(
            Icons.info_outline_rounded,
            size: 12,
            color: isDark ? AppColors.darkTextHint : AppColors.textHint,
          ),
          const SizedBox(width: 6),
          Expanded(
            child: Text(
              text,
              style: TextStyle(
                fontSize: 11.5,
                height: 1.4,
                color:
                    isDark ? AppColors.darkTextHint : AppColors.textHint,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _Label extends StatelessWidget {
  const _Label(this.text);
  final String text;

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    return Text(
      text,
      style: TextStyle(
        fontSize: 11.5,
        fontWeight: FontWeight.w700,
        color: isDark ? AppColors.darkTextSecondary : AppColors.textSecondary,
        letterSpacing: 0.4,
      ),
    );
  }
}

class _ErrorBanner extends StatelessWidget {
  const _ErrorBanner({required this.message});
  final String message;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(13),
      decoration: BoxDecoration(
        color: AppColors.error.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppColors.error.withValues(alpha: 0.3)),
      ),
      child: Row(
        children: [
          const Icon(Icons.error_outline_rounded,
              size: 17, color: AppColors.error),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              message,
              style: const TextStyle(color: AppColors.error, fontSize: 12.5),
            ),
          ),
        ],
      ),
    );
  }
}

Widget _plainField({
  required TextEditingController controller,
  required String hint,
  TextInputType? keyboardType,
  TextInputAction? textInputAction,
  String? Function(String?)? validator,
  bool obscureText = false,
  bool enabled = true,
  TextCapitalization textCapitalization = TextCapitalization.none,
  List<TextInputFormatter>? inputFormatters,
}) {
  final hintText = hint;
  return Builder(builder: (ctx) {
    final isDark = Theme.of(ctx).brightness == Brightness.dark;
    final primary =
        isDark ? AppColors.darkTextPrimary : AppColors.textPrimary;
    final secondary =
        isDark ? AppColors.darkTextSecondary : AppColors.textSecondary;
    final hintColor = isDark ? AppColors.darkTextHint : AppColors.textHint;
    return TextFormField(
      controller: controller,
      keyboardType: keyboardType,
      textInputAction: textInputAction,
      obscureText: obscureText,
      enabled: enabled,
      textCapitalization: textCapitalization,
      inputFormatters: inputFormatters,
      cursorColor: AppColors.primary,
      style: TextStyle(
        fontSize: 15,
        fontWeight: FontWeight.w500,
        color: enabled ? primary : secondary,
      ),
      decoration: InputDecoration(
        border: InputBorder.none,
        enabledBorder: InputBorder.none,
        focusedBorder: InputBorder.none,
        disabledBorder: InputBorder.none,
        errorBorder: InputBorder.none,
        focusedErrorBorder: InputBorder.none,
        isCollapsed: true,
        filled: false,
        contentPadding: const EdgeInsets.symmetric(vertical: 16),
        hintText: hintText,
        hintStyle: TextStyle(
          color: hintColor,
          fontWeight: FontWeight.w400,
        ),
        errorStyle: const TextStyle(height: 0, fontSize: 0),
      ),
      validator: validator,
    );
  });
}

// ---------------------------------------------------------------------------
// OTP (email / WhatsApp) verification bottom sheet
// ---------------------------------------------------------------------------
Future<bool?> _openCodeSheet({
  required BuildContext context,
  required String title,
  required String description,
  required String sendEndpoint,
  required String verifyEndpoint,
  required Map<String, dynamic> sendPayload,
  required Map<String, dynamic> Function(String code) verifyPayload,
}) {
  return showModalBottomSheet<bool>(
    context: context,
    isScrollControlled: true,
    backgroundColor: Colors.transparent,
    builder: (ctx) => _CodeSheet(
      title: title,
      description: description,
      sendEndpoint: sendEndpoint,
      verifyEndpoint: verifyEndpoint,
      sendPayload: sendPayload,
      verifyPayload: verifyPayload,
    ),
  );
}

class _CodeSheet extends StatefulWidget {
  final String title;
  final String description;
  final String sendEndpoint;
  final String verifyEndpoint;
  final Map<String, dynamic> sendPayload;
  final Map<String, dynamic> Function(String code) verifyPayload;

  const _CodeSheet({
    required this.title,
    required this.description,
    required this.sendEndpoint,
    required this.verifyEndpoint,
    required this.sendPayload,
    required this.verifyPayload,
  });

  @override
  State<_CodeSheet> createState() => _CodeSheetState();
}

class _CodeSheetState extends State<_CodeSheet> {
  final _code = TextEditingController();
  bool _sending = false;
  bool _verifying = false;
  String? _error;
  String? _info;
  int _resendIn = 0;
  Timer? _timer;

  @override
  void initState() {
    super.initState();
    _send();
  }

  @override
  void dispose() {
    _timer?.cancel();
    _code.dispose();
    super.dispose();
  }

  Future<void> _send() async {
    setState(() {
      _sending = true;
      _error = null;
      _info = null;
    });
    try {
      final dio = GetIt.instance<Dio>();
      final resp = await dio.post(widget.sendEndpoint, data: widget.sendPayload);
      final body = resp.data;
      if (body is Map && body['success'] == true) {
        setState(() {
          _info = 'Doğrulama kodu gönderildi.';
          _resendIn = 60;
        });
        _tickResend();
      } else {
        setState(() => _error = body is Map
            ? (body['error']?.toString() ?? 'Kod gönderilemedi')
            : 'Kod gönderilemedi');
      }
    } on DioException catch (e) {
      final data = e.response?.data;
      setState(() => _error = data is Map
          ? (data['error']?.toString() ?? 'Kod gönderilemedi')
          : 'Kod gönderilemedi');
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  void _tickResend() {
    _timer?.cancel();
    _timer = Timer.periodic(const Duration(seconds: 1), (t) {
      if (!mounted) return t.cancel();
      if (_resendIn <= 0) return t.cancel();
      setState(() => _resendIn--);
    });
  }

  Future<void> _verify() async {
    if (_code.text.trim().length < 4) {
      setState(() => _error = 'Geçerli bir kod girin.');
      return;
    }
    setState(() {
      _verifying = true;
      _error = null;
    });
    try {
      final dio = GetIt.instance<Dio>();
      final resp = await dio.post(
        widget.verifyEndpoint,
        data: widget.verifyPayload(_code.text.trim()),
      );
      final body = resp.data;
      if (body is Map && body['success'] == true) {
        if (mounted) Navigator.of(context).pop(true);
      } else {
        setState(() => _error = body is Map
            ? (body['error']?.toString() ?? 'Kod doğrulanamadı')
            : 'Kod doğrulanamadı');
      }
    } on DioException catch (e) {
      final data = e.response?.data;
      setState(() => _error = data is Map
          ? (data['error']?.toString() ?? 'Kod doğrulanamadı')
          : 'Kod doğrulanamadı');
    } finally {
      if (mounted) setState(() => _verifying = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final inset = MediaQuery.of(context).viewInsets.bottom;
    final isDark = Theme.of(context).brightness == Brightness.dark;
    return Padding(
      padding: EdgeInsets.only(bottom: inset),
      child: Container(
        padding: const EdgeInsets.fromLTRB(22, 14, 22, 24),
        decoration: BoxDecoration(
          color: isDark ? AppColors.darkCard : AppColors.card,
          borderRadius: const BorderRadius.vertical(top: Radius.circular(22)),
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Center(
              child: Container(
                width: 42,
                height: 4,
                decoration: BoxDecoration(
                  color: AppColors.border,
                  borderRadius: BorderRadius.circular(2),
                ),
              ),
            ),
            const SizedBox(height: 18),
            Text(
              widget.title,
              style: TextStyle(
                fontSize: 18,
                fontWeight: FontWeight.w700,
                color: isDark
                    ? AppColors.darkTextPrimary
                    : AppColors.textPrimary,
              ),
            ),
            const SizedBox(height: 4),
            Text(
              widget.description,
              style: TextStyle(
                fontSize: 13,
                color: isDark
                    ? AppColors.darkTextSecondary
                    : AppColors.textSecondary,
                height: 1.4,
              ),
            ),
            const SizedBox(height: 18),
            TextField(
              controller: _code,
              keyboardType: TextInputType.number,
              textAlign: TextAlign.center,
              autofocus: true,
              inputFormatters: [
                FilteringTextInputFormatter.digitsOnly,
                LengthLimitingTextInputFormatter(6),
              ],
              style: TextStyle(
                fontSize: 22,
                fontWeight: FontWeight.w700,
                letterSpacing: 8,
                color: isDark
                    ? AppColors.darkTextPrimary
                    : AppColors.textPrimary,
              ),
              decoration: InputDecoration(
                filled: true,
                fillColor: isDark
                    ? AppColors.darkSurfaceMuted
                    : AppColors.surfaceMuted,
                hintText: '______',
                hintStyle: const TextStyle(
                  color: AppColors.textHint,
                  letterSpacing: 8,
                ),
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(12),
                  borderSide: BorderSide.none,
                ),
                focusedBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(12),
                  borderSide:
                      const BorderSide(color: AppColors.primary, width: 1.5),
                ),
              ),
            ),
            if (_error != null) ...[
              const SizedBox(height: 12),
              _ErrorBanner(message: _error!),
            ] else if (_info != null) ...[
              const SizedBox(height: 12),
              Text(
                _info!,
                textAlign: TextAlign.center,
                style: const TextStyle(
                  fontSize: 12.5,
                  color: AppColors.success,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ],
            const SizedBox(height: 18),
            PrimaryActionButton(
              label: 'Doğrula',
              onPressed: _verifying ? null : _verify,
              loading: _verifying,
              icon: Icons.verified_outlined,
            ),
            const SizedBox(height: 8),
            Center(
              child: TextButton(
                onPressed: (_resendIn > 0 || _sending) ? null : _send,
                child: Text(
                  _resendIn > 0
                      ? 'Yeniden gönder ($_resendIn)'
                      : 'Kodu yeniden gönder',
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

// ---------------------------------------------------------------------------
// Success screen
// ---------------------------------------------------------------------------
class _SuccessScreen extends StatelessWidget {
  final String businessName;
  const _SuccessScreen({required this.businessName});

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 28, vertical: 28),
          child: Column(
            children: [
              const Spacer(),
              const QordyMark(size: 64),
              const SizedBox(height: 24),
              Container(
                width: 68,
                height: 68,
                decoration: BoxDecoration(
                  color: AppColors.success.withValues(alpha: 0.14),
                  shape: BoxShape.circle,
                ),
                child: const Icon(
                  Icons.check_circle_rounded,
                  color: AppColors.success,
                  size: 40,
                ),
              ),
              const SizedBox(height: 20),
              Text(
                'Hoş geldin, $businessName!',
                textAlign: TextAlign.center,
                style: TextStyle(
                  fontSize: 20,
                  fontWeight: FontWeight.w700,
                  color: isDark
                      ? AppColors.darkTextPrimary
                      : AppColors.textPrimary,
                ),
              ),
              const SizedBox(height: 10),
              Text(
                'Hesabın hazır. Panele yönlendiriliyorsun — 7 gün ücretsiz '
                'deneme süresin başladı.',
                textAlign: TextAlign.center,
                style: TextStyle(
                  fontSize: 13,
                  height: 1.5,
                  color: isDark
                      ? AppColors.darkTextSecondary
                      : AppColors.textSecondary,
                ),
              ),
              const SizedBox(height: 20),
              const SizedBox(
                width: 22,
                height: 22,
                child: CircularProgressIndicator(
                  strokeWidth: 2.2,
                  color: AppColors.primary,
                ),
              ),
              const Spacer(),
            ],
          ),
        ),
      ),
    );
  }
}
