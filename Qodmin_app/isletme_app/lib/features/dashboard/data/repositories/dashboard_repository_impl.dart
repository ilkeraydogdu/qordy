import 'package:dartz/dartz.dart';

import '../../../../core/error/exceptions.dart';
import '../../../../core/error/failures.dart';
import '../../domain/entities/dashboard_summary.dart';
import '../../domain/repositories/dashboard_repository.dart';
import '../datasources/dashboard_remote_datasource.dart';

class DashboardRepositoryImpl implements DashboardRepository {
 DashboardRepositoryImpl({required this.remote});
 final DashboardRemoteDataSource remote;

 @override
 Future<Either<Failure, DashboardSummary>> getSummary() async {
 try {
 final model = await remote.getSummary();
 return Right(model.toEntity());
 } on NetworkException catch (e) {
 return Left(NetworkFailure(cause: e));
 } on ServerException catch (e) {
 return Left(ServerFailure(statusCode: e.statusCode, message: e.message, cause: e));
 } catch (e) {
 return Left(UnknownFailure(cause: e));
 }
 }
}
