import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:qordy_app/models/category.dart';
import 'package:qordy_app/models/menu_item.dart';

import '../data/manager_repository.dart';
import 'menu_state.dart';

class MenuManagementCubit extends Cubit<MenuManagementState> {
  final ManagerRepository _repository;
  List<MenuItem> _items = [];
  List<Category> _categories = [];
  String? _selectedCategoryId;
  String _searchQuery = '';

  MenuManagementCubit({required ManagerRepository repository})
      : _repository = repository,
        super(const MenuManagementInitial());

  Future<void> loadMenu() async {
    final isInitial = state is MenuManagementInitial;
    if (isInitial) {
      emit(const MenuManagementLoading());
    }

    try {
      final responses = await Future.wait([
        _repository.getMenu(),
        _repository.getCategories(),
      ]);

      final menuResponse = responses[0];
      final catResponse = responses[1];

      if (menuResponse.isSuccess) {
        _items = (menuResponse.data as List<MenuItem>?) ?? [];
        _categories = catResponse.isSuccess
            ? (catResponse.data as List<Category>?) ?? []
            : [];

        emit(MenuManagementLoaded(
          items: _items,
          categories: _categories,
          selectedCategoryId: _selectedCategoryId,
          searchQuery: _searchQuery,
        ));
      } else {
        emit(MenuManagementError(
            menuResponse.error ?? 'Menü yüklenemedi'));
      }
    } catch (e) {
      emit(MenuManagementError(e.toString()));
    }
  }

  Future<void> addItem(Map<String, dynamic> data) async {
    try {
      emit(const MenuManagementActionLoading());
      final response = await _repository.addMenuItem(data);
      if (response.isSuccess) {
        await loadMenu();
      } else {
        final prev = state;
        emit(MenuManagementError(response.error ?? 'Ürün eklenemedi'));
        if (prev is MenuManagementLoaded) emit(prev);
      }
    } catch (e) {
      final prev = state;
      emit(MenuManagementError(e.toString()));
      if (prev is MenuManagementLoaded) emit(prev);
    }
  }

  Future<void> updateItem(Map<String, dynamic> data) async {
    try {
      emit(const MenuManagementActionLoading());
      final response = await _repository.updateMenuItem(data);
      if (response.isSuccess) {
        await loadMenu();
      } else {
        final prev = state;
        emit(MenuManagementError(
            response.error ?? 'Ürün güncellenemedi'));
        if (prev is MenuManagementLoaded) emit(prev);
      }
    } catch (e) {
      final prev = state;
      emit(MenuManagementError(e.toString()));
      if (prev is MenuManagementLoaded) emit(prev);
    }
  }

  Future<void> deleteItem(String menuItemId) async {
    try {
      final response = await _repository.deleteMenuItem(menuItemId);
      if (response.isSuccess) {
        await loadMenu();
      } else {
        if (state is MenuManagementLoaded) {
          emit(MenuManagementError(
              response.error ?? 'Ürün silinemedi'));
          await loadMenu();
        }
      }
    } catch (e) {
      if (state is MenuManagementLoaded) {
        emit(MenuManagementError(e.toString()));
        await loadMenu();
      }
    }
  }

  Future<void> toggleAvailability(String menuItemId, bool isAvailable) async {
    try {
      final response =
          await _repository.updateMenuItemAvailability(menuItemId, isAvailable);
      if (response.isSuccess) {
        _items = _items.map((item) {
          if (item.menuItemId == menuItemId) {
            return item.copyWith(isAvailable: isAvailable);
          }
          return item;
        }).toList();

        emit(MenuManagementLoaded(
          items: _items,
          categories: _categories,
          selectedCategoryId: _selectedCategoryId,
          searchQuery: _searchQuery,
        ));
      }
    } catch (_) {}
  }

  void selectCategory(String? categoryId) {
    _selectedCategoryId = categoryId;
    if (state is MenuManagementLoaded) {
      emit(MenuManagementLoaded(
        items: _items,
        categories: _categories,
        selectedCategoryId: _selectedCategoryId,
        searchQuery: _searchQuery,
      ));
    }
  }

  void search(String query) {
    _searchQuery = query;
    if (state is MenuManagementLoaded) {
      emit(MenuManagementLoaded(
        items: _items,
        categories: _categories,
        selectedCategoryId: _selectedCategoryId,
        searchQuery: _searchQuery,
      ));
    }
  }

  Future<void> refresh() => loadMenu();
}
