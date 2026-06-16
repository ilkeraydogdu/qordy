import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../app/router/route_guards.dart';
import '../../../../core/usecase/usecase.dart';
import '../../domain/usecases/login_usecase.dart';
import '../../domain/usecases/logout_usecase.dart';
import '../../domain/usecases/verify_2fa_usecase.dart';
import 'auth_event.dart';
import 'auth_state.dart';

class AuthBloc extends Bloc<AuthEvent, AuthState> {
 AuthBloc({
 required this.loginUseCase,
 required this.verify2FAUseCase,
 required this.logoutUseCase,
 }) : super(const AuthInitial()) {
 on<AuthCheckRequested>(_onCheckRequested);
 on<AuthLoginRequested>(_onLoginRequested);
 on<Auth2FARequested>(_on2FARequested);
 on<AuthLogoutRequested>(_onLogoutRequested);
 on<AuthReset>((_, emit) => emit(const AuthInitial()));
 }

 final LoginUseCase loginUseCase;
 final Verify2FAUseCase verify2FAUseCase;
 final LogoutUseCase logoutUseCase;

 Future<void> _onCheckRequested(
 AuthCheckRequested event,
 Emitter<AuthState> emit,
 ) async {
 // İlk açılışta current session kontrolü.
 // Şimdilik: AuthInitial → AuthUnauthenticated. Detay AŞAMA 11'de.
 emit(const AuthUnauthenticated());
 }

 Future<void> _onLoginRequested(
 AuthLoginRequested event,
 Emitter<AuthState> emit,
 ) async {
 emit(const AuthLoading());

 final result = await loginUseCase(LoginParams(
 email: event.email,
 password: event.password,
 subdomain: event.subdomain,
 ));

 result.fold(
 (failure) {
 if (failure.message.contains('2FA') ||
 failure.message.toLowerCase().contains('two factor')) {
 emit(AuthTwoFactorRequired(method: 'email'));
 } else {
 emit(AuthError(failure.message));
 }
 },
 (session) {
 setAuthState(true);
 emit(AuthAuthenticated(session));
 },
 );
 }

 Future<void> _on2FARequested(
 Auth2FARequested event,
 Emitter<AuthState> emit,
 ) async {
 emit(const AuthLoading());

 final result = await verify2FAUseCase(Verify2FAParams(
 code: event.code,
 method: event.method,
 ));

 result.fold(
 (failure) => emit(AuthError(failure.message)),
 (session) {
 setAuthState(true);
 emit(AuthAuthenticated(session));
 },
 );
 }

 Future<void> _onLogoutRequested(
 AuthLogoutRequested event,
 Emitter<AuthState> emit,
 ) async {
 emit(const AuthLoading());
 await logoutUseCase(const NoParams());
 setAuthState(false);
 emit(const AuthUnauthenticated());
 }
}
