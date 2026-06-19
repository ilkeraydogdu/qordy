import 'package:equatable/equatable.dart';
import 'package:qordy_app/models/staff.dart';

abstract class StaffState extends Equatable {
  const StaffState();

  @override
  List<Object?> get props => [];
}

class StaffInitial extends StaffState {
  const StaffInitial();
}

class StaffLoading extends StaffState {
  const StaffLoading();
}

class StaffLoaded extends StaffState {
  final List<Staff> staffList;
  final List<Map<String, dynamic>> roles;
  final String? filterRole;
  final String searchQuery;

  const StaffLoaded({
    required this.staffList,
    this.roles = const [],
    this.filterRole,
    this.searchQuery = '',
  });

  List<Staff> get filteredStaff {
    var result = staffList;
    if (filterRole != null && filterRole!.isNotEmpty) {
      result = result
          .where((s) =>
              s.role?.toUpperCase() == filterRole!.toUpperCase() ||
              s.roleName?.toUpperCase() == filterRole!.toUpperCase())
          .toList();
    }
    if (searchQuery.isNotEmpty) {
      final query = searchQuery.toLowerCase();
      result = result
          .where((s) =>
              (s.name?.toLowerCase().contains(query) ?? false) ||
              (s.email?.toLowerCase().contains(query) ?? false) ||
              (s.phone?.contains(query) ?? false))
          .toList();
    }
    return result;
  }

  @override
  List<Object?> get props => [staffList, roles, filterRole, searchQuery];
}

class StaffActionLoading extends StaffState {
  const StaffActionLoading();
}

class StaffError extends StaffState {
  final String message;

  const StaffError(this.message);

  @override
  List<Object?> get props => [message];
}
