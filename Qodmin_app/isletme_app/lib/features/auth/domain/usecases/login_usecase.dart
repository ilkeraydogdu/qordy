import 'package:dartz/dartz.dart';
import 'package:equatable/equatable.dart';

import '../../../../core/error/failures.dart';
import '../../../../core/usecase/usecase.dart';
import '../entities/auth_session.dart';
import '../repositories/auth_repository.dart';

class LoginParams extends Equatable {
 const LoginParams({
 required this.email,
 required this.password,
 required this.subdomain,
 });

 final String email;
 final String password;
 final String subdomain;

 @override
 List<Object?> get props => [email, password, subdomain];
}

class LoginUseCase implements UseCase<AuthSession, LoginParams> {
 const LoginUseCase(this.repository);
 final AuthRepository repository;

 @override
 Future<Either<Failure, AuthSession>> call(LoginParams params) {
 return repository.login(
 email: params.email,
 password: params.password,
 subdomain: params.subdomain,
 );
 }
}
