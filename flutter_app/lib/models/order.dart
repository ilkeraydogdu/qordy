class Order {
  final String? orderId;
  final String? tableId;
  final String? tableName;
  final String? status;
  final double? totalAmount;
  final String? customerName;
  final String? notes;
  final String? tenantId;
  final String? createdAt;
  final String? updatedAt;
  final List<OrderItem>? items;

  const Order({
    this.orderId,
    this.tableId,
    this.tableName,
    this.status,
    this.totalAmount,
    this.customerName,
    this.notes,
    this.tenantId,
    this.createdAt,
    this.updatedAt,
    this.items,
  });

  factory Order.fromJson(Map<String, dynamic> json) {
    // Backend can hand us numeric IDs, legacy snake_case and/or camelCase
    // keys. Coerce everything defensively instead of blind `as String` casts
    // so one schema drift doesn't crash the whole Kitchen/POS/Waiter screen.
    String? str(List<String> keys) {
      for (final k in keys) {
        final v = json[k];
        if (v == null) continue;
        if (v is String) return v.isEmpty ? null : v;
        return v.toString();
      }
      return null;
    }

    double? numField(List<String> keys) {
      for (final k in keys) {
        final v = json[k];
        if (v == null) continue;
        if (v is num) return v.toDouble();
        if (v is String) {
          final p = double.tryParse(v);
          if (p != null) return p;
        }
      }
      return null;
    }

    final rawItems = json['items'];
    List<OrderItem>? parsedItems;
    if (rawItems is List) {
      parsedItems = rawItems
          .whereType<Object>()
          .map((e) {
            if (e is Map<String, dynamic>) return OrderItem.fromJson(e);
            if (e is Map) {
              return OrderItem.fromJson(
                  e.map((k, v) => MapEntry(k.toString(), v)));
            }
            return null;
          })
          .whereType<OrderItem>()
          .toList();
    }

    return Order(
      orderId: str(const ['order_id', 'orderId', 'id']),
      tableId: str(const ['table_id', 'tableId']),
      tableName: str(const ['table_name', 'tableName']),
      status: str(const ['status']),
      totalAmount: numField(const ['total_amount', 'totalAmount', 'total']),
      customerName: str(const ['customer_name', 'customerName']),
      notes: str(const ['notes']),
      tenantId: str(const ['tenant_id', 'tenantId']),
      createdAt: str(const ['created_at', 'createdAt']),
      updatedAt: str(const ['updated_at', 'updatedAt']),
      items: parsedItems,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'orderId': orderId,
      'tableId': tableId,
      'tableName': tableName,
      'status': status,
      'totalAmount': totalAmount,
      'customerName': customerName,
      'notes': notes,
      'tenantId': tenantId,
      'createdAt': createdAt,
      'updatedAt': updatedAt,
      'items': items?.map((e) => e.toJson()).toList(),
    };
  }

  Order copyWith({
    String? orderId,
    String? tableId,
    String? tableName,
    String? status,
    double? totalAmount,
    String? customerName,
    String? notes,
    String? tenantId,
    String? createdAt,
    String? updatedAt,
    List<OrderItem>? items,
  }) {
    return Order(
      orderId: orderId ?? this.orderId,
      tableId: tableId ?? this.tableId,
      tableName: tableName ?? this.tableName,
      status: status ?? this.status,
      totalAmount: totalAmount ?? this.totalAmount,
      customerName: customerName ?? this.customerName,
      notes: notes ?? this.notes,
      tenantId: tenantId ?? this.tenantId,
      createdAt: createdAt ?? this.createdAt,
      updatedAt: updatedAt ?? this.updatedAt,
      items: items ?? this.items,
    );
  }

  @override
  String toString() => 'Order(orderId: $orderId, status: $status, totalAmount: $totalAmount)';

  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      other is Order &&
          runtimeType == other.runtimeType &&
          orderId == other.orderId;

  @override
  int get hashCode => orderId.hashCode;
}

class OrderItem {
  final String? orderItemId;
  final String? orderId;
  final String? menuItemId;
  final String? name;
  final int? quantity;
  final double? price;
  final double? totalPrice;
  final String? status;
  final String? notes;
  final String? productionPoint;
  final List<OrderItemCustomization>? customizations;

  const OrderItem({
    this.orderItemId,
    this.orderId,
    this.menuItemId,
    this.name,
    this.quantity,
    this.price,
    this.totalPrice,
    this.status,
    this.notes,
    this.productionPoint,
    this.customizations,
  });

  factory OrderItem.fromJson(Map<String, dynamic> json) {
    String? str(List<String> keys) {
      for (final k in keys) {
        final v = json[k];
        if (v == null) continue;
        if (v is String) return v.isEmpty ? null : v;
        return v.toString();
      }
      return null;
    }

    final quantityRaw = json['quantity'];
    final int? quantityParsed = quantityRaw == null
        ? null
        : quantityRaw is int
            ? quantityRaw
            : quantityRaw is num
                ? quantityRaw.toInt()
                : int.tryParse(quantityRaw.toString());

    final priceRaw = json['price'];
    final double? priceParsed = priceRaw == null
        ? null
        : priceRaw is num
            ? priceRaw.toDouble()
            : double.tryParse(priceRaw.toString());

    final totalPriceRaw =
        json['total_price'] ?? json['totalPrice'];
    final double? totalPriceParsed = totalPriceRaw == null
        ? null
        : totalPriceRaw is num
            ? totalPriceRaw.toDouble()
            : double.tryParse(totalPriceRaw.toString());

    final rawCustom = json['customizations'];
    List<OrderItemCustomization>? parsedCustom;
    if (rawCustom is List) {
      parsedCustom = rawCustom
          .whereType<Object>()
          .map((e) {
            if (e is Map<String, dynamic>) {
              return OrderItemCustomization.fromJson(e);
            }
            if (e is Map) {
              return OrderItemCustomization.fromJson(
                  e.map((k, v) => MapEntry(k.toString(), v)));
            }
            return null;
          })
          .whereType<OrderItemCustomization>()
          .toList();
    }

    return OrderItem(
      orderItemId: str(const ['order_item_id', 'orderItemId', 'id']),
      orderId: str(const ['order_id', 'orderId']),
      menuItemId: str(const ['menu_item_id', 'menuItemId']),
      name: str(const ['name']),
      quantity: quantityParsed,
      price: priceParsed,
      totalPrice: totalPriceParsed,
      status: str(const ['status']),
      notes: str(const ['notes']),
      productionPoint: str(const ['production_point', 'productionPoint']),
      customizations: parsedCustom,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'orderItemId': orderItemId,
      'orderId': orderId,
      'menuItemId': menuItemId,
      'name': name,
      'quantity': quantity,
      'price': price,
      'totalPrice': totalPrice,
      'status': status,
      'notes': notes,
      'productionPoint': productionPoint,
      'customizations': customizations?.map((e) => e.toJson()).toList(),
    };
  }

  OrderItem copyWith({
    String? orderItemId,
    String? orderId,
    String? menuItemId,
    String? name,
    int? quantity,
    double? price,
    double? totalPrice,
    String? status,
    String? notes,
    String? productionPoint,
    List<OrderItemCustomization>? customizations,
  }) {
    return OrderItem(
      orderItemId: orderItemId ?? this.orderItemId,
      orderId: orderId ?? this.orderId,
      menuItemId: menuItemId ?? this.menuItemId,
      name: name ?? this.name,
      quantity: quantity ?? this.quantity,
      price: price ?? this.price,
      totalPrice: totalPrice ?? this.totalPrice,
      status: status ?? this.status,
      notes: notes ?? this.notes,
      productionPoint: productionPoint ?? this.productionPoint,
      customizations: customizations ?? this.customizations,
    );
  }

  @override
  String toString() => 'OrderItem(name: $name, quantity: $quantity, price: $price)';
}

class OrderItemCustomization {
  final String? id;
  final String? name;
  final String? type;
  final double? price;

  const OrderItemCustomization({
    this.id,
    this.name,
    this.type,
    this.price,
  });

  factory OrderItemCustomization.fromJson(Map<String, dynamic> json) {
    String? str(List<String> keys) {
      for (final k in keys) {
        final v = json[k];
        if (v == null) continue;
        if (v is String) return v.isEmpty ? null : v;
        return v.toString();
      }
      return null;
    }

    final priceRaw = json['price'];
    final double? priceParsed = priceRaw == null
        ? null
        : priceRaw is num
            ? priceRaw.toDouble()
            : double.tryParse(priceRaw.toString());

    return OrderItemCustomization(
      id: str(const ['customization_id', 'customizationId', 'id']),
      name: str(const ['name']),
      type: str(const ['type']),
      price: priceParsed,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'name': name,
      'type': type,
      'price': price,
    };
  }

  @override
  String toString() => 'OrderItemCustomization(name: $name, type: $type, price: $price)';
}
