import 'package:equatable/equatable.dart';
import 'package:qordy_app/models/expense.dart';

abstract class ExpensesState extends Equatable {
  const ExpensesState();

  @override
  List<Object?> get props => [];
}

class ExpensesInitial extends ExpensesState {
  const ExpensesInitial();
}

class ExpensesLoading extends ExpensesState {
  const ExpensesLoading();
}

class ExpensesLoaded extends ExpensesState {
  final List<Expense> expenses;
  final List<Expense> filteredExpenses;
  final String period;
  final String? selectedCategory;
  final double totalAmount;

  const ExpensesLoaded({
    required this.expenses,
    required this.filteredExpenses,
    this.period = 'week',
    this.selectedCategory,
    required this.totalAmount,
  });

  @override
  List<Object?> get props => [
        expenses,
        filteredExpenses,
        period,
        selectedCategory,
        totalAmount,
      ];
}

class ExpenseActionSuccess extends ExpensesState {
  final String message;

  const ExpenseActionSuccess(this.message);

  @override
  List<Object?> get props => [message];
}

class ExpensesError extends ExpensesState {
  final String message;

  const ExpensesError(this.message);

  @override
  List<Object?> get props => [message];
}
