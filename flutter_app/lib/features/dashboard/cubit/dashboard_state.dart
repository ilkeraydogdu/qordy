import 'package:equatable/equatable.dart';
import 'package:qordy_app/models/dashboard_stats.dart';
import 'package:qordy_app/models/order.dart';

abstract class DashboardState extends Equatable {
  const DashboardState();

  @override
  List<Object?> get props => [];
}

class DashboardInitial extends DashboardState {
  const DashboardInitial();
}

class DashboardLoading extends DashboardState {
  const DashboardLoading();
}

class DashboardLoaded extends DashboardState {
  final DashboardStats stats;
  final List<Order> recentOrders;
  final String period;

  const DashboardLoaded({
    required this.stats,
    required this.recentOrders,
    this.period = 'today',
  });

  @override
  List<Object?> get props => [stats, recentOrders, period];
}

class DashboardError extends DashboardState {
  final String message;

  const DashboardError(this.message);

  @override
  List<Object?> get props => [message];
}
