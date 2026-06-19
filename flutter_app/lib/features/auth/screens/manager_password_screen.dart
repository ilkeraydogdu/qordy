import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../config/theme.dart';
import '../cubit/auth_cubit.dart';
import '../cubit/auth_state.dart';
import '../widgets/auth_layout.dart';

class ManagerPasswordScreen extends StatefulWidget {
  final String email;

  const ManagerPasswordScreen({
    super.key,
    required this.email,
  });

  @override
  State<ManagerPasswordScreen> createState() => _ManagerPasswordScreenState();
}

class _ManagerPasswordScreenState extends State<ManagerPasswordScreen> {
  final _controller = TextEditingController();
  final _formKey = GlobalKey<FormState>();
  bool _obscure = true;
  String? _errorText;

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  void _onSubmit() {
    setState(() => _errorText = null);
    if (!_formKey.currentState!.validate()) return;
    final password = _controller.text;
    context.read<AuthCubit>().managerLogin(widget.email, password);
  }

  void _showForgotPasswordInfo(BuildContext context) {
    showDialog<void>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Şifremi Unuttum'),
        content: const Text(
          'Şifre sıfırlama talebi için destek@qordy.com adresine e-posta gönderin '
          'veya QORDY web panelindeki "Şifremi Unuttum" bağlantısını kullanın.',
          style: TextStyle(height: 1.5),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(ctx).pop(),
            child: const Text('Tamam'),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return BlocListener<AuthCubit, AuthState>(
      listener: (context, state) {
        // Navigation on success is handled by GoRouter's redirect rule
        // (see `AppRouter.router` in config/router.dart). Doing a manual
        // `popUntil(isFirst)` here races with that redirect and can pop
        // the dashboard the router just pushed, dropping the user back
        // on /role-select.
        if (state is AuthError) {
          HapticFeedback.heavyImpact();
          setState(() => _errorText = state.message);
        }
      },
      child: BlocBuilder<AuthCubit, AuthState>(
        builder: (context, state) {
          final isLoading = state is AuthLoading;
          return AuthLayout(
            title: 'Şifrenizi Girin',
            subtitle: 'Devam etmek için hesap şifrenizi girin.',
            bottom: PrimaryActionButton(
              label: 'Giriş Yap',
              onPressed: isLoading ? null : _onSubmit,
              loading: isLoading,
              icon: Icons.arrow_forward_rounded,
            ),
            child: Form(
              key: _formKey,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  // E-posta "onay chip'i" — kullanıcı bir adım önce
                  // girdiği e-postayı net görür; tek dokunuşta değiştirebilir.
                  // Dark mode'da düzgün render olsun diye context-aware.
                  Container(
                    padding: const EdgeInsets.fromLTRB(12, 10, 6, 10),
                    decoration: BoxDecoration(
                      color: context.brandSurfaceMuted,
                      borderRadius: BorderRadius.circular(AppRadius.md),
                      border: Border.all(
                          color: context.brandBorder, width: 0.8),
                    ),
                    child: Row(
                      children: [
                        Container(
                          width: 32,
                          height: 32,
                          decoration: BoxDecoration(
                            gradient: LinearGradient(
                              begin: Alignment.topLeft,
                              end: Alignment.bottomRight,
                              colors: [
                                AppColors.primary.withValues(
                                    alpha: context.isDark ? 0.38 : 0.18),
                                AppColors.primary.withValues(
                                    alpha: context.isDark ? 0.22 : 0.08),
                              ],
                            ),
                            borderRadius: BorderRadius.circular(AppRadius.sm),
                            border: Border.all(
                              color: AppColors.primary.withValues(
                                  alpha: context.isDark ? 0.42 : 0.22),
                              width: 0.6,
                            ),
                          ),
                          alignment: Alignment.center,
                          child: const Icon(
                            Icons.alternate_email_rounded,
                            size: 16,
                            color: AppColors.primary,
                          ),
                        ),
                        const SizedBox(width: 10),
                        Expanded(
                          child: Text(
                            widget.email,
                            style: TextStyle(
                              fontSize: 13.5,
                              color: context.brandTextPrimary,
                              fontWeight: FontWeight.w700,
                              letterSpacing: -0.1,
                            ),
                            overflow: TextOverflow.ellipsis,
                          ),
                        ),
                        TextButton.icon(
                          onPressed: () {
                            HapticFeedback.selectionClick();
                            Navigator.of(context).pop();
                          },
                          icon: const Icon(Icons.edit_rounded, size: 14),
                          label: const Text('Değiştir'),
                          style: TextButton.styleFrom(
                            padding: const EdgeInsets.symmetric(
                                horizontal: 10, vertical: 6),
                            minimumSize: const Size(0, 32),
                            foregroundColor: AppColors.primary,
                            textStyle: const TextStyle(
                                fontSize: 12.5,
                                fontWeight: FontWeight.w700),
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 18),
                  BrandInputGroup(
                    errorText: _errorText,
                    leading: Icon(
                      Icons.lock_outline_rounded,
                      size: 18,
                      color: context.brandTextHint,
                    ),
                    trailing: IconButton(
                      icon: Icon(
                        _obscure
                            ? Icons.visibility_off_outlined
                            : Icons.visibility_outlined,
                        size: 20,
                        color: context.brandTextHint,
                      ),
                      onPressed: () {
                        HapticFeedback.selectionClick();
                        setState(() => _obscure = !_obscure);
                      },
                    ),
                    child: TextFormField(
                      controller: _controller,
                      enabled: !isLoading,
                      obscureText: _obscure,
                      textInputAction: TextInputAction.done,
                      onFieldSubmitted: (_) => _onSubmit(),
                      style: TextStyle(
                        fontSize: 15,
                        fontWeight: FontWeight.w500,
                        color: context.brandTextPrimary,
                      ),
                      decoration: InputDecoration(
                        border: InputBorder.none,
                        enabledBorder: InputBorder.none,
                        focusedBorder: InputBorder.none,
                        filled: false,
                        contentPadding:
                            const EdgeInsets.symmetric(vertical: 14),
                        hintText: '••••••••',
                        hintStyle:
                            TextStyle(color: context.brandTextHint),
                      ),
                      validator: (value) {
                        if (value == null || value.isEmpty) {
                          return 'Şifre gerekli';
                        }
                        if (value.length < 6) {
                          return 'Şifre en az 6 karakter olmalı';
                        }
                        return null;
                      },
                    ),
                  ),
                  const SizedBox(height: 8),
                  Align(
                    alignment: Alignment.centerRight,
                    child: TextButton(
                      onPressed: () => _showForgotPasswordInfo(context),
                      child: const Text('Şifremi unuttum'),
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
