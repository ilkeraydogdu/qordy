class Expense {
  final String? expenseId;
  final String? description;
  final double? amount;
  final String? category;
  final String? date;
  final String? createdAt;

  const Expense({
    this.expenseId,
    this.description,
    this.amount,
    this.category,
    this.date,
    this.createdAt,
  });

  factory Expense.fromJson(Map<String, dynamic> json) {
    return Expense(
      expenseId: (json['expense_id'] ?? json['expenseId'])?.toString(),
      description: json['description'] as String?,
      amount: (json['amount'] as num?)?.toDouble(),
      category: json['category'] as String?,
      date: json['date'] as String?,
      createdAt: (json['created_at'] ?? json['createdAt']) as String?,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'expenseId': expenseId,
      'description': description,
      'amount': amount,
      'category': category,
      'date': date,
      'createdAt': createdAt,
    };
  }

  Expense copyWith({
    String? expenseId,
    String? description,
    double? amount,
    String? category,
    String? date,
    String? createdAt,
  }) {
    return Expense(
      expenseId: expenseId ?? this.expenseId,
      description: description ?? this.description,
      amount: amount ?? this.amount,
      category: category ?? this.category,
      date: date ?? this.date,
      createdAt: createdAt ?? this.createdAt,
    );
  }

  @override
  String toString() => 'Expense(expenseId: $expenseId, description: $description, amount: $amount)';

  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      other is Expense &&
          runtimeType == other.runtimeType &&
          expenseId == other.expenseId;

  @override
  int get hashCode => expenseId.hashCode;
}
