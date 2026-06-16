import 'package:dartz/dartz.dart';

import '../../../../core/error/failures.dart';
import '../../../../core/usecase/usecase.dart';
import '../entities/dashboard_summary.dart';
import '../repositories/dashboard_repository.dart';

class GetDashboardSummaryUseCase implements UseCase<DashboardSummary, NoParams> {
 const GetDashboardSummaryUseCase(this.repository);
 final DashboardRepository repository;

 @override
 Future<Either<Failure, DashboardSummary>> call(NoParams params) {
 return repository.getSummary();
 }
}
