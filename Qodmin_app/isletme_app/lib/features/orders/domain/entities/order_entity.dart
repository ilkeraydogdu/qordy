import 'package:equatable/equatable.dart';

enum OrderStatus { pending, confirmed, preparing, ready, served, cancelled, completed }

OrderStatus parseOrderStatus(String? raw) {
 switch (raw?.toLowerCase()) {
 case 'pending':
 case 'new':
 return OrderStatus.pending;
 case 'confirmed':
 case 'accepted':
 return OrderStatus.confirmed;
 case 'preparing':
 case 'in_kitchen':
 return OrderStatus.preparing;
 case 'ready':
 return OrderStatus.ready;
 case 'served':
 case 'delivered':
 return OrderStatus.served;
 case 'cancelled':
 case 'canceled':
 return OrderStatus.cancelled;
 case 'completed':
 case 'closed':
 return OrderStatus.completed;
 default:
 return OrderStatus.pending;
 }
}

String orderStatusLabel(OrderStatus s) {
 switch (s) {
 case OrderStatus.pending:
 return 'Bekliyor';
 case OrderStatus.confirmed:
 return 'Onaylandı';
 case OrderStatus.preparing:
 return 'Hazırlanıyor';
 case OrderStatus.ready:
 return 'Hazır';
 case OrderStatus.served:
 return 'Servis edildi';
 case OrderStatus.cancelled:
 return 'İptal';
 case OrderStatus.completed:
 return 'Tamamlandı';
 }
}

class OrderItem extends Equatable {
 const OrderItem({
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

 double get total => unitPrice * quantity;

 @override
 List<Object?> get props => [id, name, quantity, unitPrice, notes, options];
}

class OrderEntity extends Equatable {
 const OrderEntity({
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
 final OrderStatus status;
 final List<OrderItem> items;
 final double total;
 final String? tableNumber;
 final String? customerName;
 final String? notes;
 final String? paymentMethod;
 final DateTime createdAt;
 final String tenantId;

 OrderEntity copyWith({OrderStatus? status}) {
 return OrderEntity(
 id: id,
 orderNumber: orderNumber,
 status: status ?? this.status,
 items: items,
 total: total,
 tableNumber: tableNumber,
 customerName: customerName,
 notes: notes,
 paymentMethod: paymentMethod,
 createdAt: createdAt,
 tenantId: tenantId,
 );
 }

 @override
 List<Object?> get props => [
 id, orderNumber, status, items, total, tableNumber,
 customerName, notes, paymentMethod, createdAt, tenantId,
 ];
}
