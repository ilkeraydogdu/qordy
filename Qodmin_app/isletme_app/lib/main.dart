import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'app/app.dart';
import 'app/di/injector.dart';
import 'core/utils/logger.dart';
import 'features/auth/presentation/bloc/auth_bloc.dart';
import 'features/auth/presentation/bloc/auth_event.dart';

void main() async {
 WidgetsFlutterBinding.ensureInitialized();
 AppLogger.i('main', 'Qordy Isletme Yonetici başlatılıyor');
 await setupInjector();
 runApp(
 BlocProvider<AuthBloc>(
 create: (_) => sl<AuthBloc>()..add(const AuthCheckRequested()),
 child: const QordyApp(),
 ),
 );
}
