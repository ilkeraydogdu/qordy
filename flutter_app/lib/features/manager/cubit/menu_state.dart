import 'package:equatable/equatable.dart';
import 'package:qordy_app/models/category.dart';
import 'package:qordy_app/models/menu_item.dart';

abstract class MenuManagementState extends Equatable {
  const MenuManagementState();

  @override
  List<Object?> get props => [];
}

class MenuManagementInitial extends MenuManagementState {
  const MenuManagementInitial();
}

class MenuManagementLoading extends MenuManagementState {
  const MenuManagementLoading();
}

class MenuManagementLoaded extends MenuManagementState {
  final List<MenuItem> items;
  final List<Category> categories;
  final String? selectedCategoryId;
  final String searchQuery;

  const MenuManagementLoaded({
    required this.items,
    required this.categories,
    this.selectedCategoryId,
    this.searchQuery = '',
  });

  List<MenuItem> get filteredItems {
    var result = items;
    if (selectedCategoryId != null && selectedCategoryId!.isNotEmpty) {
      result =
          result.where((i) => i.categoryId == selectedCategoryId).toList();
    }
    if (searchQuery.isNotEmpty) {
      final query = searchQuery.toLowerCase();
      result = result
          .where((i) =>
              (i.name?.toLowerCase().contains(query) ?? false) ||
              (i.categoryName?.toLowerCase().contains(query) ?? false))
          .toList();
    }
    return result;
  }

  @override
  List<Object?> get props =>
      [items, categories, selectedCategoryId, searchQuery];
}

class MenuManagementActionLoading extends MenuManagementState {
  const MenuManagementActionLoading();
}

class MenuManagementError extends MenuManagementState {
  final String message;

  const MenuManagementError(this.message);

  @override
  List<Object?> get props => [message];
}
