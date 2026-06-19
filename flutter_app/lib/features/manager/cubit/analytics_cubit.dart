import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:qordy_app/models/analytics.dart';

import '../data/manager_repository.dart';
import 'analytics_state.dart';

class AnalyticsCubit extends Cubit<AnalyticsState> {
  final ManagerRepository _repository;
  String _currentPeriod = 'today';

  AnalyticsCubit({required ManagerRepository repository})
      : _repository = repository,
        super(const AnalyticsInitial());

  Future<void> loadAnalytics({String? period}) async {
    if (period != null) _currentPeriod = period;

    final isInitial = state is AnalyticsInitial;
    if (isInitial) {
      emit(const AnalyticsLoading());
    }

    try {
      final responses = await Future.wait([
        _repository.getAnalytics(period: _currentPeriod),
        _repository.getAnalyticsByCategory(),
      ]);

      final analyticsResponse = responses[0];
      final categoryResponse = responses[1];

      if (analyticsResponse.isSuccess) {
        final analyticsData =
            analyticsResponse.data as AnalyticsData? ?? const AnalyticsData();
        final categories = categoryResponse.isSuccess
            ? (categoryResponse.data as List<CategorySales>?) ?? []
            : <CategorySales>[];

        emit(AnalyticsLoaded(
          analytics: analyticsData,
          categorySales: categories,
          period: _currentPeriod,
        ));
      } else {
        emit(AnalyticsError(
            analyticsResponse.error ?? 'Analiz verileri yüklenemedi'));
      }
    } catch (e) {
      emit(AnalyticsError(e.toString()));
    }
  }

  Future<void> loadByCategory() async {
    try {
      final response = await _repository.getAnalyticsByCategory();
      if (response.isSuccess && state is AnalyticsLoaded) {
        final loaded = state as AnalyticsLoaded;
        emit(AnalyticsLoaded(
          analytics: loaded.analytics,
          categorySales: response.data ?? [],
          period: loaded.period,
        ));
      }
    } catch (_) {}
  }

  Future<void> refresh() => loadAnalytics(period: _currentPeriod);
}
