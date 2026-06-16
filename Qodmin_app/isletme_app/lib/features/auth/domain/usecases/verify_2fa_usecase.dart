import 'package:dartz/dartz.dart';
import 'package:equatable/equatable.dart';

import '../../../../core/error/failures.dart';
import '../../../../core/usecase/usecase.dart';
import '../entities/auth_session.dart';
import '../repositories/auth_repository.dart';

class Verify2FAParams extends Equatable {
 const Verify2FAParams({required this.code, required this.method});
 final String code;
 final String method;

 @override
 List<Object?> get props => [code, method];
}

class Verify2FAUseCase implements UseCase<AuthSession, Verify2FAParams> {
 const Verify2FAUseCase(this.repository);
 final AuthRepository repository;

 @override
 Future<Either<Failure, AuthSession>> call(Verify2FAParams params) {
 return repository.verify2FA(code: params.code, method: params.method);
 }
}
