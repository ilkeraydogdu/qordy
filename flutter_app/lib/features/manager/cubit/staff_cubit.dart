import 'package:flutter_bloc/flutter_bloc.dart';

import '../data/manager_repository.dart';
import 'staff_state.dart';

class StaffCubit extends Cubit<StaffState> {
  final ManagerRepository _repository;
  List<Map<String, dynamic>> _roles = [];
  String? _filterRole;
  String _searchQuery = '';

  StaffCubit({required ManagerRepository repository})
      : _repository = repository,
        super(const StaffInitial());

  Future<void> loadStaff() async {
    final isInitial = state is StaffInitial;
    if (isInitial) {
      emit(const StaffLoading());
    }

    try {
      final staffResponse = await _repository.getStaffList();
      final rolesResponse = await _repository.getRoles();

      if (staffResponse.isSuccess) {
        if (rolesResponse.isSuccess && rolesResponse.data != null) {
          _roles = rolesResponse.data!
              .map((e) => Map<String, dynamic>.from(e))
              .toList();
        }

        emit(StaffLoaded(
          staffList: staffResponse.data ?? [],
          roles: _roles,
          filterRole: _filterRole,
          searchQuery: _searchQuery,
        ));
      } else {
        emit(StaffError(staffResponse.error ?? 'Personel listesi yüklenemedi'));
      }
    } catch (e) {
      emit(StaffError(e.toString()));
    }
  }

  Future<void> createStaff(Map<String, dynamic> data) async {
    try {
      emit(const StaffActionLoading());
      final response = await _repository.createStaff(data);
      if (response.isSuccess) {
        await loadStaff();
      } else {
        final prev = state;
        emit(StaffError(response.error ?? 'Personel eklenemedi'));
        if (prev is StaffLoaded) emit(prev);
      }
    } catch (e) {
      final prev = state;
      emit(StaffError(e.toString()));
      if (prev is StaffLoaded) emit(prev);
    }
  }

  Future<void> updateStaff(Map<String, dynamic> data) async {
    try {
      emit(const StaffActionLoading());
      final response = await _repository.updateStaff(data);
      if (response.isSuccess) {
        await loadStaff();
      } else {
        final prev = state;
        emit(StaffError(response.error ?? 'Personel güncellenemedi'));
        if (prev is StaffLoaded) emit(prev);
      }
    } catch (e) {
      final prev = state;
      emit(StaffError(e.toString()));
      if (prev is StaffLoaded) emit(prev);
    }
  }

  Future<void> deleteStaff(String userId) async {
    try {
      final response = await _repository.deleteStaff(userId);
      if (response.isSuccess) {
        await loadStaff();
      } else {
        if (state is StaffLoaded) {
          emit(StaffError(response.error ?? 'Personel silinemedi'));
          await loadStaff();
        }
      }
    } catch (e) {
      if (state is StaffLoaded) {
        emit(StaffError(e.toString()));
        await loadStaff();
      }
    }
  }

  void filterByRole(String? role) {
    _filterRole = role;
    if (state is StaffLoaded) {
      final loaded = state as StaffLoaded;
      emit(StaffLoaded(
        staffList: loaded.staffList,
        roles: loaded.roles,
        filterRole: _filterRole,
        searchQuery: _searchQuery,
      ));
    }
  }

  void search(String query) {
    _searchQuery = query;
    if (state is StaffLoaded) {
      final loaded = state as StaffLoaded;
      emit(StaffLoaded(
        staffList: loaded.staffList,
        roles: loaded.roles,
        filterRole: _filterRole,
        searchQuery: _searchQuery,
      ));
    }
  }

  Future<void> refresh() => loadStaff();
}
