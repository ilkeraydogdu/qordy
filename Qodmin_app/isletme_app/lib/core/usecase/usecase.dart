import 'package:dartz/dartz.dart';

import '../error/failures.dart';

/// Tüm use case'lerin türeyeceği base.
///
/// `In` = input, `Out` = output (domain entity).
abstract class UseCase<Out, In> {
 Future<Either<Failure, Out>> call(In params);
}

abstract class StreamUseCase<Out, In> {
 Stream<Either<Failure, Out>> call(In params);
}

/// Parametresiz use case'ler için.
class NoParams {
 const NoParams();
}
