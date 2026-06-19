import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';

import '../../../config/theme.dart';
import '../../../core/widgets/qordy_logo.dart';
import '../cubit/auth_cubit.dart';
import '../cubit/auth_state.dart';

/// Redesigned, unified entry point to the app.
///
/// Design brief (verbatim from the customer, condensed):
///   * "hem logo hem yazı olmasın, sade güzel br giriş ekranı olsun" —
///     only the Q mark, no wordmark, no pager, no two-tab layout.
///   * "tek input olsun" — a single dynamic field decides the flow:
///       - `ornek@domain.com` → owner/manager flow → `/manager-password`
///       - `Pofuduk Cafe`      → staff flow        → `/staff-pin`
///   * Registration link stays visible on the page.
///   * "bazı efektler gğzellikler kat" — soft brand glow, floating logo
///     halo, animated leading icon that reflects detected intent, subtle
///     focus glow on the input, haptic-enabled gradient CTA.
///
/// Session re-entry (already-logged-in users) is handled separately by
/// [QuickUnlockScreen] via the `PendingUnlock` state; this screen is
/// reached only when the auth state is [AuthInitial].
class UnifiedLoginScreen extends StatefulWidget {
  const UnifiedLoginScreen({super.key});

  @override
  State<UnifiedLoginScreen> createState() => _UnifiedLoginScreenState();
}

enum _IdentityKind { unknown, email }

class _UnifiedLoginScreenState extends State<UnifiedLoginScreen> {
  final TextEditingController _controller = TextEditingController();
  final FocusNode _focusNode = FocusNode();
  String? _errorText;
  _IdentityKind _kind = _IdentityKind.unknown;

  @override
  void initState() {
    super.initState();
    _controller.addListener(_onChanged);
  }

  @override
  void dispose() {
    _controller.removeListener(_onChanged);
    _controller.dispose();
    _focusNode.dispose();
    super.dispose();
  }

  void _onChanged() {
    final value = _controller.text.trim();
    final kind = _detectKind(value);
    if (_kind != kind || _errorText != null) {
      setState(() {
        _kind = kind;
        _errorText = null;
      });
    }
  }

  _IdentityKind _detectKind(String value) {
    if (value.isEmpty) return _IdentityKind.unknown;
    final atIndex = value.indexOf('@');
    if (atIndex > 0) {
      // Treat anything with an `@` sign as an email candidate so the UI
      // reflects the user's intent even before they finish typing the
      // TLD. The backend call happens only on submit.
      return _IdentityKind.email;
    }
    return _IdentityKind.unknown;
  }

  void _submit() {
    final value = _controller.text.trim();
    if (value.isEmpty) {
      HapticFeedback.heavyImpact();
      setState(() => _errorText =
          'E-posta adresinizi veya işletme adınızı girin.');
      return;
    }
    FocusScope.of(context).unfocus();
    context.read<AuthCubit>().resolveIdentity(value);
  }

  @override
  Widget build(BuildContext context) {
    return BlocListener<AuthCubit, AuthState>(
      listener: (context, state) {
        if (state is EmailValidated) {
          context.push('/manager-password', extra: {'email': state.email});
        } else if (false) && state is SubdomainValidated {
          context.push('/staff-pin', extra: {
            'businessName': state.businessName,
            'subdomain': state.subdomain,
          });
        } else if (state is AuthError) {
          HapticFeedback.heavyImpact();
          setState(() => _errorText = state.message);
        }
      },
      child: BlocBuilder<AuthCubit, AuthState>(
        builder: (context, state) {
          final isLoading = state is AuthLoading;
          return Scaffold(
            backgroundColor: Colors.transparent,
            resizeToAvoidBottomInset: true,
            body: Container(
              decoration: context.brandScaffoldDecoration,
              child: Stack(
                children: [
                  const _BackgroundGlow(),
                  SafeArea(
                    child: LayoutBuilder(
                      builder: (context, c) {
                        return SingleChildScrollView(
                          padding: EdgeInsets.only(
                            left: 24,
                            right: 24,
                            top: 16,
                            bottom:
                                MediaQuery.of(context).viewInsets.bottom + 32,
                          ),
                          child: ConstrainedBox(
                            constraints: BoxConstraints(
                              minHeight: c.maxHeight - 48,
                            ),
                            child: IntrinsicHeight(
                              child: Column(
                                crossAxisAlignment:
                                    CrossAxisAlignment.stretch,
                                children: [
                                  const Spacer(),
                                  const _BrandBadge(),
                                  const SizedBox(height: 28),
                                  _Heading(kind: _kind),
                                  const SizedBox(height: 22),
                                  _DynamicIdentityField(
                                    controller: _controller,
                                    focusNode: _focusNode,
                                    kind: _kind,
                                    errorText: _errorText,
                                    enabled: !isLoading,
                                    onSubmitted: _submit,
                                  ),
                                  const SizedBox(height: 10),
                                  _HelperText(kind: _kind),
                                  const SizedBox(height: 22),
                                  _PrimaryCTA(
                                    kind: _kind,
                                    loading: isLoading,
                                    onTap: _submit,
                                  ),
                                  const SizedBox(height: 16),
                                  const _RegisterFooter(),
                                  const Spacer(),
                                  const SizedBox(height: 12),
                                ],
                              ),
                            ),
                          ),
                        );
                      },
                    ),
                  ),
                ],
              ),
            ),
          );
        },
      ),
    );
  }
}

// ---------------------------------------------------------------------------
// Atmospheric glow layer reused for the whole auth flow.
// ---------------------------------------------------------------------------
class _BackgroundGlow extends StatelessWidget {
  const _BackgroundGlow();

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    return IgnorePointer(
      child: Stack(
        children: [
          Positioned(
            top: -160,
            right: -140,
            child: Container(
              width: 380,
              height: 380,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                gradient: RadialGradient(
                  colors: [
                    AppColors.primary
                        .withValues(alpha: isDark ? 0.22 : 0.16),
                    AppColors.primary.withValues(alpha: 0.0),
                  ],
                ),
              ),
            ),
          ),
          Positioned(
            bottom: -200,
            left: -160,
            child: Container(
              width: 420,
              height: 420,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                gradient: RadialGradient(
                  colors: [
                    AppColors.primaryLight
                        .withValues(alpha: isDark ? 0.14 : 0.10),
                    AppColors.primaryLight.withValues(alpha: 0.0),
                  ],
                ),
              ),
            ),
          ),
          Positioned(
            top: 260,
            left: -80,
            child: Container(
              width: 220,
              height: 220,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                gradient: RadialGradient(
                  colors: [
                    AppColors.accentIndigo
                        .withValues(alpha: isDark ? 0.10 : 0.06),
                    AppColors.accentIndigo.withValues(alpha: 0.0),
                  ],
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

// ---------------------------------------------------------------------------
// Brand badge — Q mark only. Sits above the headline, not twinned with a
// wordmark (per customer brief "hem logo hem yazı olmasın").
// ---------------------------------------------------------------------------
class _BrandBadge extends StatelessWidget {
  const _BrandBadge();

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Stack(
        alignment: Alignment.center,
        children: [
          Container(
            width: 112,
            height: 112,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              gradient: RadialGradient(
                colors: [
                  AppColors.primary.withValues(alpha: 0.22),
                  AppColors.primary.withValues(alpha: 0.0),
                ],
              ),
            ),
          ),
          const QordyMark(size: 68, borderRadius: 20),
        ],
      ),
    );
  }
}

// ---------------------------------------------------------------------------
// Animated headline — the subtitle morphs based on detected intent so the
// user gets instant feedback on what the app will do.
// ---------------------------------------------------------------------------
class _Heading extends StatelessWidget {
  const _Heading({required this.kind});
  final _IdentityKind kind;

  @override
  Widget build(BuildContext context) {
    final subtitle = switch (kind) {
      _IdentityKind.email =>
        'İşletme sahibi olarak devam etmek için e-postanızı girin.',
