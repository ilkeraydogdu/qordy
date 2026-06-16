import 'package:dartz/dartz.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:isletme_yonetici/core/error/failures.dart';
import 'package:isletme_yonetici/features/auth/domain/entities/auth_session.dart';
import 'package:isletme_yonetici/features/auth/domain/repositories/auth_repository.dart';
import 'package:isletme_yonetici/features/auth/domain/usecases/login_usecase.dart';
import 'package:mocktail/mocktail.dart';

class _MockAuthRepository extends Mock implements AuthRepository {}

void main() {
 late _MockAuthRepository repository;
 late LoginUseCase useCase;

 setUp(() {
 repository = _MockAuthRepository();
 useCase = LoginUseCase(repository);
 });

 final tSession = AuthSession(
 accessToken: 'access-123',
 refreshToken: 'refresh-456',
 userId: 'user-1',
 email: 'owner@cafe.com',
 businessName: 'Cafe Test',
 subdomain: 'cafe-test',
 expiresAt: DateTime.now().add(const Duration(hours: 1)),
 permissions: const ['orders.view', 'menu.edit'],
 );

 group('LoginUseCase', () {
 test('başarılı login → AuthSession döner', () async {
 when(() => repository.login(
 email: any(named: 'email'),
 password: any(named: 'password'),
 subdomain: any(named: 'subdomain'),
 )).thenAnswer((_) async => Right(tSession));

 final result = await useCase(const LoginParams(
 email: 'owner@cafe.com',
 password: 'secret123',
 subdomain: 'cafe-test',
 ));

 expect(result.isRight(), isTrue);
 result.fold(
 (_) => fail('Right bekleniyordu'),
 (session) {
 expect(session.userId, equals('user-1'));
 expect(session.email, equals('owner@cafe.com'));
 expect(session.permissions, contains('orders.view'));
 },
 );
 });

 test('yanlış şifre → AuthFailure döner', () async {
 when(() => repository.login(
 email: any(named: 'email'),
 password: any(named: 'password'),
 subdomain: any(named: 'subdomain'),
 )).thenAnswer((_) async => const Left(AuthFailure(message: 'Geçersiz kimlik')));

 final result = await useCase(const LoginParams(
 email: 'owner@cafe.com',
 password: 'wrong',
 subdomain: 'cafe-test',
 ));

 expect(result.isLeft(), isTrue);
 result.fold(
 (failure) {
 expect(failure, isA<AuthFailure>());
 expect(failure.message, contains('Geçersiz'));
 },
 (_) => fail('Left bekleniyordu'),
 );
 });

 test('subdomain yanlış → NetworkFailure döner', () async {
 when(() => repository.login(
 email: any(named: 'email'),
 password: any(named: 'password'),
 subdomain: any(named: 'subdomain'),
 )).thenAnswer((_) async => const Left(NetworkFailure(message: 'Bağlantı yok')));

 final result = await useCase(const LoginParams(
 email: 'owner@cafe.com',
 password: 'secret',
 subdomain: 'invalid-tenant',
 ));

 expect(result.isLeft(), isTrue);
 result.fold(
 (failure) => expect(failure, isA<NetworkFailure>()),
 (_) => fail('Left bekleniyordu'),
 );
 });
 });
}
