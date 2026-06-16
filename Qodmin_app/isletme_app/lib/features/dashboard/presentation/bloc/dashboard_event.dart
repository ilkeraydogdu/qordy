import 'package:equatable/equatable.dart';

sealed class DashboardEvent extends Equatable {
 const DashboardEvent();
 @override
 List<Object?> get props => [];
}

class DashboardLoadRequested extends DashboardEvent {
 const DashboardLoadRequested();
}

class DashboardRefreshRequested extends DashboardEvent {
 const DashboardRefreshRequested();
}
