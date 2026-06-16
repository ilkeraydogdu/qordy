import 'package:dartz/dartz.dart';

import '../../../../core/error/failures.dart';
import '../../../../core/usecase/usecase.dart';
import '../repositories/auth_repository.dart';

class LogoutUseCase implements UseCase<void, NoParams> {
 const LogoutUseCase(this.repository);
 final AuthRepository repository;

 @override
 Future<Either<Failure, void>> call(NoParams params) async {
 final result = await repository.logout();
 return result.fold(
 (failure) => Left(failure),
 (_) => const Right(null),
 );
 }
}
