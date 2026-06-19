import 'package:equatable/equatable.dart';
import 'package:qordy_app/models/analytics.dart';

abstract class AnalyticsState extends Equatable {
  const AnalyticsState();

  @override
  List<Object?> get props => [];
}

class AnalyticsInitial extends AnalyticsState {
  const AnalyticsInitial();
}

class AnalyticsLoading extends AnalyticsState {
  const AnalyticsLoading();
}

class AnalyticsLoaded extends AnalyticsState {
  final AnalyticsData analytics;
  final List<CategorySales> categorySales;
  final String period;

  const AnalyticsLoaded({
    required this.analytics,
    this.categorySales = const [],
    this.period = 'today',
  });

  @override
  List<Object?> get props => [analytics, categorySales, period];
}

class AnalyticsError extends AnalyticsState {
  final String message;

  const AnalyticsError(this.message);

  @override
  List<Object?> get props => [message];
}
