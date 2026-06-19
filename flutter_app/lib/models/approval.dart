class OrderApproval {
  final String? approvalId;
  final String? orderId;
  final String? orderItemId;
  final String? tableId;
  final String? tableName;
  final String? actionType;
  final String? itemName;
  final double? itemPrice;
  final int? oldQuantity;
  final int? newQuantity;
  final String? requestedBy;
  final String? requestedByName;
  final String? requestedByRole;
  final String? status;
  final String? requestedAt;

  const OrderApproval({
    this.approvalId,
    this.orderId,
    this.orderItemId,
    this.tableId,
    this.tableName,
    this.actionType,
    this.itemName,
    this.itemPrice,
    this.oldQuantity,
    this.newQuantity,
    this.requestedBy,
    this.requestedByName,
    this.requestedByRole,
    this.status,
    this.requestedAt,
  });

  factory OrderApproval.fromJson(Map<String, dynamic> json) {
    return OrderApproval(
      approvalId: (json['approval_id'] ?? json['approvalId'] ?? json['id'])?.toString(),
      orderId: (json['order_id'] ?? json['orderId'])?.toString(),
      orderItemId: (json['order_item_id'] ?? json['orderItemId'])?.toString(),
      tableId: (json['table_id'] ?? json['tableId'])?.toString(),
      tableName: (json['table_name'] ?? json['tableName']) as String?,
      actionType: (json['action_type'] ?? json['actionType']) as String?,
      itemName: (json['item_name'] ?? json['itemName']) as String?,
      itemPrice: ((json['item_price'] ?? json['itemPrice']) as num?)?.toDouble(),
      oldQuantity: (json['old_quantity'] ?? json['oldQuantity']) as int?,
      newQuantity: (json['new_quantity'] ?? json['newQuantity']) as int?,
      requestedBy: (json['requested_by'] ?? json['requestedBy'])?.toString(),
      requestedByName: (json['requested_by_name'] ?? json['requestedByName']) as String?,
      requestedByRole: (json['requested_by_role'] ?? json['requestedByRole']) as String?,
      status: json['status'] as String?,
      requestedAt: (json['requested_at'] ?? json['requestedAt']) as String?,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'approvalId': approvalId,
      'orderId': orderId,
      'orderItemId': orderItemId,
      'tableId': tableId,
      'tableName': tableName,
      'actionType': actionType,
      'itemName': itemName,
      'itemPrice': itemPrice,
      'oldQuantity': oldQuantity,
      'newQuantity': newQuantity,
      'requestedBy': requestedBy,
      'requestedByName': requestedByName,
      'requestedByRole': requestedByRole,
      'status': status,
      'requestedAt': requestedAt,
    };
  }

  OrderApproval copyWith({
    String? approvalId,
    String? orderId,
    String? orderItemId,
    String? tableId,
    String? tableName,
    String? actionType,
    String? itemName,
    double? itemPrice,
    int? oldQuantity,
    int? newQuantity,
    String? requestedBy,
    String? requestedByName,
    String? requestedByRole,
    String? status,
    String? requestedAt,
  }) {
    return OrderApproval(
      approvalId: approvalId ?? this.approvalId,
      orderId: orderId ?? this.orderId,
      orderItemId: orderItemId ?? this.orderItemId,
      tableId: tableId ?? this.tableId,
      tableName: tableName ?? this.tableName,
      actionType: actionType ?? this.actionType,
      itemName: itemName ?? this.itemName,
      itemPrice: itemPrice ?? this.itemPrice,
      oldQuantity: oldQuantity ?? this.oldQuantity,
      newQuantity: newQuantity ?? this.newQuantity,
      requestedBy: requestedBy ?? this.requestedBy,
      requestedByName: requestedByName ?? this.requestedByName,
      requestedByRole: requestedByRole ?? this.requestedByRole,
      status: status ?? this.status,
      requestedAt: requestedAt ?? this.requestedAt,
    );
  }

  @override
  String toString() => 'OrderApproval(approvalId: $approvalId, actionType: $actionType, status: $status)';

  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      other is OrderApproval &&
          runtimeType == other.runtimeType &&
          approvalId == other.approvalId;

  @override
  int get hashCode => approvalId.hashCode;
}
