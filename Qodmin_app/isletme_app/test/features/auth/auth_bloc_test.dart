import 'package:bloc_test/bloc_test.dart';
import 'package:dartz/dartz.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:isletme_yonetici/core/error/failures.dart';
import 'package:isletme_yonetici/core/usecase/usecase.dart';
import 'package:isletme_yonetici/features/auth/domain/entities/auth_session.dart';
import 'package:isletme_yonetici/features/auth/domain/usecases/login_usecase.dart';
import 'package:isletme_yonetici/features/auth/domain/usecases/logout_usecase.dart';
import 'package:isletme_yonetici/features/auth/domain/usecases/verify_2fa_usecase.dart';
import 'package:isletme_yonetici/features/auth/presentation/bloc/auth_bloc.dart';
import 'package:isletme_yonetici/features/auth/presentation/bloc/auth_event.dart';
import 'package:isletme_yonetici/features/auth/presentation/bloc/auth_state.dart';
import 'package:mocktail/mocktail.dart';

class _MockLogin extends Mock implements LoginUseCase {}

class _MockVerify2FA extends Mock implements Verify2FAUseCase {}

class _MockLogout extends Mock implements LogoutUseCase {}

class _FakeParams extends Fake implements LoginParams {}

class _FakeVerifyParams extends Fake implements Verify2FAParams {}

class _FakeNoParams extends Fake implements NoParams {}

void main() {
 late _MockLogin loginUC;
 late _MockVerify2FA verifyUC;
 late _MockLogout logoutUC;
 late AuthBloc bloc;

 final tSession = AuthSession(
 accessToken: 'a',
 refreshToken: 'r',
 userId: 'u1',
 email: 'o@c.com',
 businessName: 'C',
 subdomain: 'c',
 expiresAt: DateTime.now().add(const Duration(hours: 1)),
 );

 setUpAll(() {
 registerFallbackValue(_FakeParams());
 registerFallbackValue(_FakeVerifyParams());
 registerFallbackValue(_FakeNoParams());
 });

 setUp(() {
 loginUC = _MockLogin();
 verifyUC = _MockVerify2FA();
 logoutUC = _MockLogout();
 bloc = AuthBloc(
 loginUseCase: loginUC,
 verify2FAUseCase: verifyUC,
 logoutUseCase: logoutUC,
 );
 });

 group('AuthBloc', () {
 blocTest<AuthBloc, AuthState>(
 'AuthCheckRequested → AuthUnauthenticated',
 build: () => bloc,
 act: (b) => b.add(const AuthCheckRequested()),
 expect: () => [const AuthUnauthenticated()],
 );

 blocTest<AuthBloc, AuthState>(
 'başarılı login → AuthLoading → AuthAuthenticated',
 build: () {
 when(() => loginUC(any())).thenAnswer((_) async => Right(tSession));
 return bloc;
 },
 act: (b) => b.add(const AuthLoginRequested(
 email: 'o@c.com', password: 'p', subdomain: 'c',
 )),
 expect: () => [const AuthLoading(), AuthAuthenticated(tSession)],
 verify: (_) {
 verify(() => loginUC(any())).called(1);
 },
 );

 blocTest<AuthBloc, AuthState>(
 'başarısız login → AuthLoading → AuthError',
 build: () {
 when(() => loginUC(any())).thenAnswer(
 (_) async => const Left(AuthFailure(message: 'Geçersiz')),
 );
 return bloc;
 },
 act: (b) => b.add(const AuthLoginRequested(
 email: 'o@c.com', password: 'wrong', subdomain: 'c',
 )),
 expect: () => [
 const AuthLoading(),
 const AuthError('Geçersiz'),
 ],
 );

 blocTest<AuthBloc, AuthState>(
 '2FA doğrulama başarılı → AuthAuthenticated',
 build: () {
 when(() => verifyUC(any())).thenAnswer((_) async => Right(tSession));
 return bloc;
 },
 act: (b) => b.add(const Auth2FARequested(code: '123456', method: 'email')),
 expect: () => [const AuthLoading(), AuthAuthenticated(tSession)],
 );

 blocTest<AuthBloc, AuthState>(
 'logout → AuthUnauthenticated',
 build: () {
 when(() => logoutUC(any())).thenAnswer((_) async => const Right<Failure, void>(null));
 return bloc;
 },
 act: (b) => b.add(const AuthLogoutRequested()),
 expect: () => [const AuthLoading(), const AuthUnauthenticated()],
 );
 });
}
