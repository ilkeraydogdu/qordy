import '../../domain/entities/order_entity.dart';

class OrderItemModel {
 OrderItemModel({
 required this.id,
 required this.name,
 required this.quantity,
 required this.unitPrice,
 this.notes,
 this.options = const <String>[],
 });
 final String id;
 final String name;
 final int quantity;
 final double unitPrice;
 final String? notes;
 final List<String> options;

 factory OrderItemModel.fromJson(Map<String, dynamic> json) {
 return OrderItemModel(
 id: json['id']?.toString() ?? '',
 name: json['name']?.toString() ?? '',
 quantity: (json['quantity'] as num?)?.toInt() ?? 0,
 unitPrice: (json['unit_price'] as num?)?.toDouble()
 ?? (json['price'] as num?)?.toDouble() ?? 0.0,
 notes: json['notes'] as String?,
 options: (json['options'] as List?)
 ?.map((e) => e.toString())
 .toList() ??
 const <String>[],
 );
 }

 OrderItem toEntity() => OrderItem(
 id: id,
 name: name,
 quantity: quantity,
 unitPrice: unitPrice,
 notes: notes,
 options: options,
 );
}

class OrderModel {
 OrderModel({
 required this.id,
 required this.orderNumber,
 required this.status,
 required this.items,
 required this.total,
 required this.tableNumber,
 required this.customerName,
 required this.createdAt,
 required this.tenantId,
 this.notes,
 this.paymentMethod,
 });
 final String id;
 final String orderNumber;
 final String status;
 final List<OrderItemModel> items;
 final double total;
 final String? tableNumber;
 final String? customerName;
 final String? notes;
 final String? paymentMethod;
 final DateTime createdAt;
 final String tenantId;

 factory OrderModel.fromJson(Map<String, dynamic> json) {
 return OrderModel(
 id: json['id']?.toString() ?? '',
 orderNumber: json['order_number']?.toString()
 ?? json['number']?.toString()
 ?? json['id']?.toString() ?? '',
 status: json['status']?.toString() ?? 'pending',
 items: (json['items'] as List?)
 ?.map((e) => OrderItemModel.fromJson(
 (e as Map).cast<String, dynamic>()))
 .toList() ??
 const <OrderItemModel>[],
 total: (json['total'] as num?)?.toDouble()
 ?? (json['total_amount'] as num?)?.toDouble() ?? 0.0,
 tableNumber: json['table_number']?.toString() ?? json['table']?.toString(),
 customerName: json['customer_name']?.toString() ?? json['customer']?.toString(),
 notes: json['notes'] as String?,
 paymentMethod: json['payment_method']?.toString(),
 createdAt: DateTime.tryParse(json['created_at']?.toString() ?? '')
 ?? DateTime.now(),
 tenantId: json['tenant_id']?.toString() ?? json['business_id']?.toString() ?? '',
 );
 }

 OrderEntity toEntity() => OrderEntity(
 id: id,
 orderNumber: orderNumber,
 status: parseOrderStatus(status),
 items: items.map((e) => e.toEntity()).toList(),
 total: total,
 tableNumber: tableNumber,
 customerName: customerName,
 notes: notes,
 paymentMethod: paymentMethod,
 createdAt: createdAt,
 tenantId: tenantId,
 );
}
