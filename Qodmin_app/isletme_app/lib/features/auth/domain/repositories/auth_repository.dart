import 'package:dartz/dartz.dart';

import '../../../../core/error/failures.dart';
import '../entities/auth_session.dart';

abstract class AuthRepository {
 Future<Either<Failure, AuthSession>> login({
 required String email,
 required String password,
 required String subdomain,
 });

 Future<Either<Failure, AuthSession>> verify2FA({
 required String code,
 required String method,
 });

 Future<Either<Failure, Unit>> logout();

 Future<Either<Failure, AuthSession?>> currentSession();

 Future<Either<Failure, bool>> isJailbroken();
}
