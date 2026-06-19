import 'package:dio/dio.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:qordy_app/features/dashboard/cubit/dashboard_state.dart';
import 'package:qordy_app/features/dashboard/data/dashboard_repository.dart';

class DashboardCubit extends Cubit<DashboardState> {
  final DashboardRepository _repository;
  String _currentPeriod = 'today';

  DashboardCubit({required DashboardRepository repository})
      : _repository = repository,
        super(const DashboardInitial());

  String get currentPeriod => _currentPeriod;

  Future<void> loadDashboard({String? period}) async {
    if (period != null) _currentPeriod = period;
    emit(const DashboardLoading());
    try {
      final stats = await _repository.getStats(period: _currentPeriod);
      final recentOrders =
          await _repository.getRecentOrders(period: _currentPeriod);
      emit(DashboardLoaded(
        stats: stats,
        recentOrders: recentOrders,
        period: _currentPeriod,
      ));
    } on DioException catch (e) {
      emit(DashboardError(_extractError(e)));
    } catch (e) {
      emit(DashboardError(e.toString()));
    }
  }

  Future<void> refresh() async {
    await loadDashboard(period: _currentPeriod);
  }

  String _extractError(DioException e) {
    final data = e.response?.data;
    if (data is Map<String, dynamic>) {
      return data['error'] as String? ??
          data['message'] as String? ??
          'Bir hata oluştu';
    }
    if (e.type == DioExceptionType.connectionTimeout ||
        e.type == DioExceptionType.receiveTimeout) {
      return 'Bağlantı zaman aşımına uğradı';
    }
    if (e.type == DioExceptionType.connectionError) {
      return 'İnternet bağlantınızı kontrol edin';
    }
    return 'Bir hata oluştu';
  }
}
