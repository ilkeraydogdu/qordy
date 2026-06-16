import 'package:dartz/dartz.dart';

import '../../../../core/error/exceptions.dart';
import '../../../../core/error/failures.dart';
import '../../../../core/storage/secure_storage.dart';
import '../../domain/entities/auth_session.dart';
import '../../domain/repositories/auth_repository.dart';
import '../datasources/auth_remote_datasource.dart';

class AuthRepositoryImpl implements AuthRepository {
 AuthRepositoryImpl({
 required this.remote,
 required this.storage,
 required this.onJailbroken,
 });

 final AuthRemoteDataSource remote;
 final SecureStorageService storage;
 final Future<Failure?> Function() onJailbroken;

 @override
 Future<Either<Failure, AuthSession>> login({
 required String email,
 required String password,
 required String subdomain,
 }) async {
 final jbResult = await onJailbroken();
 if (jbResult != null) return Left(jbResult);

 try {
 final model = await remote.login(
 email: email,
 password: password,
 subdomain: subdomain,
 );
 final session = model.toEntity();

 await storage.writeAccessToken(session.accessToken);
 await storage.writeRefreshToken(session.refreshToken);
 await storage.writeString('auth.user_id', session.userId);
 await storage.writeString('auth.user_email', session.email);
 await storage.writeString('tenant.subdomain', session.subdomain);

 return Right(session);
 } on NetworkException catch (e) {
 return Left(NetworkFailure(message: e.message, cause: e));
 } on ServerException catch (e) {
 if (e.statusCode == 401 || e.statusCode == 403) {
 return Left(AuthFailure(message: 'Geçersiz kimlik bilgileri', cause: e));
 }
 if (e.statusCode == 422) {
 return Left(ValidationFailure(
 message: 'Doğrulama hatası',
 errors: const {},
 cause: e,
 ));
 }
 return Left(ServerFailure(statusCode: e.statusCode, message: e.message, cause: e));
 } catch (e) {
 return Left(UnknownFailure(message: 'Beklenmeyen hata', cause: e));
 }
 }

 @override
 Future<Either<Failure, AuthSession>> verify2FA({
 required String code,
 required String method,
 }) async {
 try {
 final model = await remote.verify2FA(code: code, method: method);
 final session = model.toEntity();

 await storage.writeAccessToken(session.accessToken);
 await storage.writeRefreshToken(session.refreshToken);
 return Right(session);
 } on NetworkException catch (e) {
 return Left(NetworkFailure(cause: e));
 } on ServerException catch (e) {
 return Left(AuthFailure(code: e.statusCode?.toString(), message: '2FA başarısız', cause: e));
 }
 }

 @override
 Future<Either<Failure, Unit>> logout() async {
 try {
 await remote.logout();
 } catch (_) {}
 await storage.clearAuth();
 return const Right(unit);
 }

 @override
 Future<Either<Failure, AuthSession?>> currentSession() async {
 try {
 final model = await remote.currentSession();
 if (model == null) return const Right(null);
 return Right(model.toEntity());
 } on NetworkException catch (e) {
 return Left(NetworkFailure(cause: e));
 }
 }

 @override
 Future<Either<Failure, bool>> isJailbroken() async {
 final result = await onJailbroken();
 if (result != null) return Left(result);
 return const Right(false);
 }
}
