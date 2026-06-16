import 'package:flutter_bloc/flutter_bloc.dart';
import '../../../../core/usecase/usecase.dart';
import '../../domain/usecases/get_dashboard_summary_usecase.dart';
import 'dashboard_event.dart';
import 'dashboard_state.dart';

class DashboardBloc extends Bloc<DashboardEvent, DashboardState> {
 DashboardBloc({required this.getSummaryUseCase})
 : super(const DashboardInitial()) {
 on<DashboardLoadRequested>(_onLoad);
 on<DashboardRefreshRequested>(_onLoad);
 }

 final GetDashboardSummaryUseCase getSummaryUseCase;

 Future<void> _onLoad(
 DashboardEvent event,
 Emitter<DashboardState> emit,
 ) async {
 emit(const DashboardLoading());
 final result = await getSummaryUseCase(const NoParams());
 result.fold(
 (failure) => emit(DashboardError(failure.message)),
 (summary) => emit(DashboardLoaded(summary)),
 );
 }
}
