import 'dart:async';

import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:qordy_app/models/approval.dart';

import '../data/manager_repository.dart';
import 'approvals_state.dart';

class ApprovalsCubit extends Cubit<ApprovalsState> {
  final ManagerRepository _repository;
  Timer? _refreshTimer;
  List<OrderApproval> _approvals = [];

  ApprovalsCubit({required ManagerRepository repository})
      : _repository = repository,
        super(const ApprovalsInitial());

  Future<void> loadApprovals() async {
    emit(const ApprovalsLoading());
    try {
      final response = await _repository.getOrderApprovals();
      if (response.isSuccess) {
        _approvals = response.data ?? [];
        emit(ApprovalsLoaded(approvals: _approvals));
      } else {
        emit(ApprovalsError(response.error ?? 'Onaylar yüklenemedi'));
      }
    } catch (e) {
      emit(ApprovalsError(e.toString()));
    }
  }

  void startAutoRefresh() {
    _refreshTimer?.cancel();
    _refreshTimer = Timer.periodic(const Duration(seconds: 10), (_) {
      _silentRefresh();
    });
  }

  void stopAutoRefresh() {
    _refreshTimer?.cancel();
    _refreshTimer = null;
  }

  Future<void> _silentRefresh() async {
    try {
      final response = await _repository.getOrderApprovals();
      if (response.isSuccess && !isClosed) {
        _approvals = response.data ?? [];
        emit(ApprovalsLoaded(approvals: _approvals));
      }
    } catch (_) {}
  }

  Future<void> approve(String approvalId) async {
    try {
      final response = await _repository.approveOrder(approvalId);
      if (response.isSuccess) {
        _approvals.removeWhere((a) => a.approvalId == approvalId);
        emit(const ApprovalActionSuccess('Onaylandı'));
        emit(ApprovalsLoaded(approvals: List.from(_approvals)));
      } else {
        emit(ApprovalsError(response.error ?? 'Onaylama başarısız'));
        emit(ApprovalsLoaded(approvals: _approvals));
      }
    } catch (e) {
      emit(ApprovalsError(e.toString()));
      emit(ApprovalsLoaded(approvals: _approvals));
    }
  }

  Future<void> reject(String approvalId) async {
    try {
      final response = await _repository.rejectOrder(approvalId);
      if (response.isSuccess) {
        _approvals.removeWhere((a) => a.approvalId == approvalId);
        emit(const ApprovalActionSuccess('Reddedildi'));
        emit(ApprovalsLoaded(approvals: List.from(_approvals)));
      } else {
        emit(ApprovalsError(response.error ?? 'Reddetme başarısız'));
        emit(ApprovalsLoaded(approvals: _approvals));
      }
    } catch (e) {
      emit(ApprovalsError(e.toString()));
      emit(ApprovalsLoaded(approvals: _approvals));
    }
  }

  @override
  Future<void> close() {
    _refreshTimer?.cancel();
    return super.close();
  }
}
