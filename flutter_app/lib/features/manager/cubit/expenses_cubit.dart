import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:qordy_app/models/expense.dart';

import '../data/manager_repository.dart';
import 'expenses_state.dart';

class ExpensesCubit extends Cubit<ExpensesState> {
  final ManagerRepository _repository;

  List<Expense> _expenses = [];
  String _period = 'week';
  String? _selectedCategory;

  ExpensesCubit({required ManagerRepository repository})
      : _repository = repository,
        super(const ExpensesInitial());

  Future<void> loadExpenses() async {
    emit(const ExpensesLoading());
    try {
      final response = await _repository.getExpenses(period: _period);
      if (response.isSuccess) {
        _expenses = response.data ?? [];
        _emitLoaded();
      } else {
        emit(ExpensesError(response.error ?? 'Giderler yüklenemedi'));
      }
    } catch (e) {
      emit(ExpensesError(e.toString()));
    }
  }

  void setPeriod(String period) {
    _period = period;
    loadExpenses();
  }

  void setCategory(String? category) {
    _selectedCategory = _selectedCategory == category ? null : category;
    _emitLoaded();
  }

  Future<void> createExpense({
    required String description,
    required double amount,
    required String category,
    required String date,
  }) async {
    emit(const ExpensesLoading());
    try {
      final response = await _repository.createExpense({
        'description': description,
        'amount': amount,
        'category': category,
        'date': date,
      });
      if (response.isSuccess) {
        emit(const ExpenseActionSuccess('Gider eklendi'));
        await loadExpenses();
      } else {
        emit(ExpensesError(response.error ?? 'Gider eklenemedi'));
      }
    } catch (e) {
      emit(ExpensesError(e.toString()));
    }
  }

  Future<void> deleteExpense(String id) async {
    try {
      final response = await _repository.deleteExpense(id);
      if (response.isSuccess) {
        _expenses.removeWhere((e) => e.expenseId == id);
        emit(const ExpenseActionSuccess('Gider silindi'));
        _emitLoaded();
      } else {
        emit(ExpensesError(response.error ?? 'Gider silinemedi'));
        _emitLoaded();
      }
    } catch (e) {
      emit(ExpensesError(e.toString()));
      _emitLoaded();
    }
  }

  void _emitLoaded() {
    var filtered = List<Expense>.from(_expenses);
    if (_selectedCategory != null) {
      filtered = filtered.where((e) => e.category == _selectedCategory).toList();
    }
    final total = filtered.fold<double>(0, (sum, e) => sum + (e.amount ?? 0));
    emit(ExpensesLoaded(
      expenses: _expenses,
      filteredExpenses: filtered,
      period: _period,
      selectedCategory: _selectedCategory,
      totalAmount: total,
    ));
  }
}
