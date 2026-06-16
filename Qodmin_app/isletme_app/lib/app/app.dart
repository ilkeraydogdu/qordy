import 'package:flutter/material.dart';

import 'router/app_router.dart';
import 'theme/app_theme.dart';

class QordyApp extends StatelessWidget {
 const QordyApp({super.key});

 @override
 Widget build(BuildContext context) {
 return MaterialApp.router(
 title: 'Qordy İşletme',
 debugShowCheckedModeBanner: false,
 theme: AppTheme.light(),
 darkTheme: AppTheme.dark(),
 themeMode: ThemeMode.system,
 routerConfig: appRouter,
 );
 }
}
