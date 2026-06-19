import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';

import '../../../config/theme.dart';
import '../cubit/auth_cubit.dart';
import '../cubit/auth_state.dart';
import '../widgets/auth_layout.dart';

class ManagerEmailScreen extends StatefulWidget {
  const ManagerEmailScreen({super.key});

  @override
  State<ManagerEmailScreen> createState() => _ManagerEmailScreenState();
}

class _ManagerEmailScreenState extends State<ManagerEmailScreen> {
  final _controller = TextEditingController();
  final _formKey = GlobalKey<FormState>();
  String? _errorText;

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  void _onSubmit() {
    setState(() => _errorText = null);
    if (!_formKey.currentState!.validate()) return;
    final email = _controller.text.trim();
    context.read<AuthCubit>().validateManagerEmail(email);
  }

  @override
  Widget build(BuildContext context) {
    return BlocListener<AuthCubit, AuthState>(
      listener: (context, state) {
        if (state is EmailValidated) {
          // Route through GoRouter so the matched location becomes
          // /manager-password — keeps redirect + back-stack consistent.
          context.push('/manager-password', extra: {'email': state.email});
        } else if (state is AuthError) {
          HapticFeedback.heavyImpact();
          setState(() => _errorText = state.message);
        }
      },
      child: BlocBuilder<AuthCubit, AuthState>(
        builder: (context, state) {
          final isLoading = state is AuthLoading;
          return AuthLayout(
            title: 'Yönetici Girişi',
            subtitle: 'İşletmenize kayıtlı e-posta adresinizi girin.',
            bottom: PrimaryActionButton(
              label: 'Devam Et',
              onPressed: isLoading ? null : _onSubmit,
              loading: isLoading,
              icon: Icons.arrow_forward_rounded,
            ),
            child: Form(
              key: _formKey,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  BrandInputGroup(
                    errorText: _errorText,
                    leading: Icon(
                      Icons.alternate_email_rounded,
                      size: 18,
                      color: context.brandTextHint,
                    ),
                    child: TextFormField(
                      controller: _controller,
                      enabled: !isLoading,
                      keyboardType: TextInputType.emailAddress,
                      autocorrect: false,
                      enableSuggestions: false,
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
                        hintText: 'ornek@email.com',
                        hintStyle: TextStyle(
                          color: context.brandTextHint,
                          fontWeight: FontWeight.w400,
                        ),
                      ),
                      validator: (value) {
                        if (value == null || value.trim().isEmpty) {
                          return 'E-posta adresi gerekli';
                        }
                        final ok = RegExp(
                                r'^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$')
                            .hasMatch(value.trim());
                        if (!ok) return 'Geçerli bir e-posta adresi girin';
                        return null;
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
