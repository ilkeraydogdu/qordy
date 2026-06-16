import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import '../../../../app/router/app_router.dart';
import '../../../../app/theme/design_tokens.dart';
import '../bloc/auth_bloc.dart';
import '../bloc/auth_event.dart';
import '../bloc/auth_state.dart';

class TwoFactorPage extends StatefulWidget {
 const TwoFactorPage({super.key});

 @override
 State<TwoFactorPage> createState() => _TwoFactorPageState();
}

class _TwoFactorPageState extends State<TwoFactorPage> {
 final _codeCtrl = TextEditingController();

 @override
 void dispose() {
 _codeCtrl.dispose();
 super.dispose();
 }

 void _submit() {
 if (_codeCtrl.text.length != 6) return;
 context.read<AuthBloc>().add(Auth2FARequested(
 code: _codeCtrl.text.trim(),
 method: 'email',
 ));
 }

 @override
 Widget build(BuildContext context) {
 return Scaffold(
 appBar: AppBar(title: const Text('İki faktörlü doğrulama')),
 body: SafeArea(
 child: BlocConsumer<AuthBloc, AuthState>(
 listener: (context, state) {
 if (state is AuthAuthenticated) {
 context.go(AppRoutes.dashboard);
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
 child: Column(
 crossAxisAlignment: CrossAxisAlignment.stretch,
 children: [
 const SizedBox(height: QordySpacing.xxxl),
 const Icon(Icons.shield_outlined,
 size: 64, color: QordyColors.primary),
 const SizedBox(height: QordySpacing.lg),
 Text('Doğrulama kodu',
 textAlign: TextAlign.center,
 style: QordyTypography.headlineMedium),
 const SizedBox(height: QordySpacing.sm),
 Text('E-postanıza gönderilen 6 haneli kodu girin',
 textAlign: TextAlign.center,
 style: QordyTypography.bodyMedium.copyWith(
 color: QordyColors.onSurfaceVariant,
 ),
 ),
 const SizedBox(height: QordySpacing.xxl),
 TextField(
 controller: _codeCtrl,
 keyboardType: TextInputType.number,
 maxLength: 6,
 textAlign: TextAlign.center,
 style: QordyTypography.headlineMedium.copyWith(letterSpacing: 8),
 inputFormatters: [
 FilteringTextInputFormatter.digitsOnly,
 LengthLimitingTextInputFormatter(6),
 ],
 decoration: const InputDecoration(
 counterText: '',
 hintText: '000000',
 ),
 onSubmitted: (_) => _submit(),
 ),
 const SizedBox(height: QordySpacing.xl),
 FilledButton(
 onPressed: isLoading ? null : _submit,
 child: isLoading
 ? const SizedBox(
 height: 20, width: 20,
 child: CircularProgressIndicator(
 strokeWidth: 2, color: Colors.white),
 )
 : const Text('Doğrula'),
 ),
 ],
 ),
 );
 },
 ),
 ),
 );
 }
}
