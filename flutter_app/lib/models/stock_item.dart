class StockItem {
  final String? stockId;
  final String? name;
  final double? quantity;
  final String? unit;
  final double? minQuantity;
  final double? cost;
  final String? supplierId;
  final String? supplierName;
  final bool? isLowStock;

  const StockItem({
    this.stockId,
    this.name,
    this.quantity,
    this.unit,
    this.minQuantity,
    this.cost,
    this.supplierId,
    this.supplierName,
    this.isLowStock,
  });

  factory StockItem.fromJson(Map<String, dynamic> json) {
    return StockItem(
      stockId: (json['stock_id'] ?? json['ingredient_id'] ?? json['stockId'])?.toString(),
      name: json['name'] as String?,
      quantity: (json['quantity'] as num?)?.toDouble(),
      unit: json['unit'] as String?,
      minQuantity: ((json['min_quantity'] ?? json['minQuantity']) as num?)?.toDouble(),
      cost: (json['cost'] as num?)?.toDouble(),
      supplierId: (json['supplier_id'] ?? json['supplierId'])?.toString(),
      supplierName: (json['supplier_name'] ?? json['supplierName']) as String?,
      isLowStock: (json['is_low_stock'] ?? json['isLowStock']) as bool?,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'stockId': stockId,
      'name': name,
      'quantity': quantity,
      'unit': unit,
      'minQuantity': minQuantity,
      'cost': cost,
      'supplierId': supplierId,
      'supplierName': supplierName,
      'isLowStock': isLowStock,
    };
  }

  StockItem copyWith({
    String? stockId,
    String? name,
    double? quantity,
    String? unit,
    double? minQuantity,
    double? cost,
    String? supplierId,
    String? supplierName,
    bool? isLowStock,
  }) {
    return StockItem(
      stockId: stockId ?? this.stockId,
      name: name ?? this.name,
      quantity: quantity ?? this.quantity,
      unit: unit ?? this.unit,
      minQuantity: minQuantity ?? this.minQuantity,
      cost: cost ?? this.cost,
      supplierId: supplierId ?? this.supplierId,
      supplierName: supplierName ?? this.supplierName,
      isLowStock: isLowStock ?? this.isLowStock,
    );
  }

  @override
  String toString() => 'StockItem(stockId: $stockId, name: $name, quantity: $quantity $unit)';

  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      other is StockItem &&
          runtimeType == other.runtimeType &&
          stockId == other.stockId;

  @override
  int get hashCode => stockId.hashCode;
}
