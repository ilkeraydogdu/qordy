import 'package:equatable/equatable.dart';
import 'package:qordy_app/models/approval.dart';

abstract class ApprovalsState extends Equatable {
  const ApprovalsState();

  @override
  List<Object?> get props => [];
}

class ApprovalsInitial extends ApprovalsState {
  const ApprovalsInitial();
}

class ApprovalsLoading extends ApprovalsState {
  const ApprovalsLoading();
}

class ApprovalsLoaded extends ApprovalsState {
  final List<OrderApproval> approvals;

  const ApprovalsLoaded({required this.approvals});

  @override
  List<Object?> get props => [approvals];
}

class ApprovalActionSuccess extends ApprovalsState {
  final String message;

  const ApprovalActionSuccess(this.message);

  @override
  List<Object?> get props => [message];
}

class ApprovalsError extends ApprovalsState {
  final String message;

  const ApprovalsError(this.message);

  @override
  List<Object?> get props => [message];
}
