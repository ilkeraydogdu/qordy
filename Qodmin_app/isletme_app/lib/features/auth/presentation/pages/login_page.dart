import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import '../../../../app/router/app_router.dart';
import '../../../../app/theme/design_tokens.dart';
import '../bloc/auth_bloc.dart';
import '../bloc/auth_event.dart';
import '../bloc/auth_state.dart';

class LoginPage extends StatefulWidget {
 const LoginPage({super.key});
 @override
 State<LoginPage> createState() => _LoginPageState();
}

class _LoginPageState extends State<LoginPage> {
 final _formKey = GlobalKey<FormState>();
 final _emailCtrl = TextEditingController();
 final _passCtrl = TextEditingController();
 final _subdomainCtrl = TextEditingController();
 bool _obscure = true;

 @override
 void dispose() {
 _emailCtrl.dispose();
 _passCtrl.dispose();
 _subdomainCtrl.dispose();
 super.dispose();
 }

 void _submit() {
 if (!_formKey.currentState!.validate()) return;
 context.read<AuthBloc>().add(AuthLoginRequested(
 email: _emailCtrl.text.trim(),
 password: _passCtrl.text,
 subdomain: _subdomainCtrl.text.trim(),
 ));
 }

 @override
 Widget build(BuildContext context) {
 return Scaffold(body: _buildBody());
 }

 Widget _buildBody() {
 final theme = Theme.of(context);
 return SafeArea(
 child: BlocConsumer<AuthBloc, AuthState>(
 listener: (context, state) {
 if (state is AuthAuthenticated) {
 context.go(AppRoutes.dashboard);
 } else if (state is AuthTwoFactorRequired) {
 context.go(AppRoutes.twoFactor);
 } else if (state is AuthError) {
 ScaffoldMessenger.of(context).showSnackBar(
 SnackBar(
 content: Text(state.message),
 backgroundColor: QordyColors.error,
 ),
 );
 }
 },
 builder: (context, state) {
 final isLoading = state is AuthLoading;
 return SingleChildScrollView(
 padding: const EdgeInsets.all(QordySpacing.xl),
 child: Form(
 key: _formKey,
 child: Column(
 crossAxisAlignment: CrossAxisAlignment.stretch,
 children: [
 const SizedBox(height: QordySpacing.xxxl),
 Center(
 child: Container(
 width: 80, height: 80,
 decoration: BoxDecoration(
 color: theme.colorScheme.primaryContainer,
 borderRadius: QordyRadius.brXl,
 ),
 child: Icon(Icons.restaurant_menu,
 size: 40, color: theme.colorScheme.primary),
 ),
 ),
 const SizedBox(height: QordySpacing.xl),
 Text('Qordy İşletme',
 textAlign: TextAlign.center,
 style: QordyTypography.headlineMedium),
 const SizedBox(height: QordySpacing.xs),
 Text('Hesabınıza giriş yapın',
 textAlign: TextAlign.center,
 style: QordyTypography.bodyMedium.copyWith(
 color: QordyColors.onSurfaceVariant,
 ),
 ),
 const SizedBox(height: QordySpacing.xxl),
 _subdomainField(),
 const SizedBox(height: QordySpacing.lg),
 _emailField(),
 const SizedBox(height: QordySpacing.lg),
 _passwordField(isLoading),
 const SizedBox(height: QordySpacing.xl),
 _submitButton(isLoading),
 const SizedBox(height: QordySpacing.lg),
 TextButton(
 onPressed: isLoading ? null : () {},
 child: const Text('Şifremi unuttum'),
 ),
 ],
 ),
 ),
 );
 },
 ),
 );
 }

 Widget _subdomainField() {
 return TextFormField(
 controller: _subdomainCtrl,
 autocorrect: false,
 textCapitalization: TextCapitalization.none,
 inputFormatters: [
 FilteringTextInputFormatter.allow(RegExp(r'[a-z0-9-]')),
 LengthLimitingTextInputFormatter(32),
 ],
 decoration: const InputDecoration(
 labelText: 'İşletme subdomain',
 hintText: 'ornek-cafe',
 prefixText: 'qordy.com/',
 prefixStyle: TextStyle(color: QordyColors.onSurfaceVariant),
 ),
 validator: (v) {
 if (v == null || v.trim().isEmpty) return 'Subdomain gerekli';
 if (v.trim().length < 3) return 'En az 3 karakter';
 return null;
 },
 );
 }

 Widget _emailField() {
 return TextFormField(
 controller: _emailCtrl,
 keyboardType: TextInputType.emailAddress,
 autocorrect: false,
 textCapitalization: TextCapitalization.none,
 inputFormatters: [LengthLimitingTextInputFormatter(120)],
 decoration: const InputDecoration(
 labelText: 'E-posta',
 prefixIcon: Icon(Icons.alternate_email),
 ),
 validator: (v) {
 if (v == null || v.trim().isEmpty) return 'E-posta gerekli';
 if (!RegExp(r'^[\w.\-+]+@[\w\-]+\.[a-zA-Z]{2,}$').hasMatch(v.trim())) {
 return 'Geçerli bir e-posta girin';
 }
 return null;
 },
 );
 }

 Widget _passwordField(bool isLoading) {
 return TextFormField(
 controller: _passCtrl,
 obscureText: _obscure,
 inputFormatters: [LengthLimitingTextInputFormatter(128)],
 decoration: InputDecoration(
 labelText: 'Şifre',
 prefixIcon: const Icon(Icons.lock_outline),
 suffixIcon: IconButton(
 icon: Icon(_obscure ? Icons.visibility : Icons.visibility_off),
 onPressed: isLoading ? null : () => setState(() => _obscure = !_obscure),
 ),
 ),
 validator: (v) {
 if (v == null || v.isEmpty) return 'Şifre gerekli';
 if (v.length < 6) return 'En az 6 karakter';
 return null;
 },
 );
 }

 Widget _submitButton(bool isLoading) {
 return FilledButton(
 onPressed: isLoading ? null : _submit,
 child: isLoading
 ? const SizedBox(
 height: 20, width: 20,
 child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
 )
 : const Text('Giriş Yap'),
 );
 }
}
