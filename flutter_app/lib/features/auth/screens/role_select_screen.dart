import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:go_router/go_router.dart';

import '../../../config/theme.dart';
import '../../../core/widgets/qordy_logo.dart';

/// Legacy entry point — kept for backwards-compatible deeplinks but
/// the app now routes through [/login] directly for all logins.
/// This screen is deprecated and just redirects to the unified login.
class RoleSelectScreen extends StatefulWidget {
 const RoleSelectScreen({super.key});

 @override
 State<RoleSelectScreen> createState() => _RoleSelectScreenState();
}

class _RoleSelectScreenState extends State<RoleSelectScreen> {
 @override
 void initState() {
 super.initState();
 // Redirect directly to the unified login
 WidgetsBinding.instance.addPostFrameCallback((_) {
 if (mounted) context.go('/login');
 });
 }

 @override
 Widget build(BuildContext context) {
 return const Scaffold(
 body: Center(
 child: CircularProgressIndicator(),
 ),
 );
 }
}