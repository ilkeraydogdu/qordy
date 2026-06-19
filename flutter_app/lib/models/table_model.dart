import 'package:qordy_app/core/network/safe_json.dart';

class RestaurantTable {
  final String? tableId;
  final String? name;
  final String? zoneId;
  final String? zoneName;
  final String? status;
  final int? capacity;
  final double? totalAmount;
  final int? activeOrderCount;
  final String? qrCode;

  const RestaurantTable({
    this.tableId,
    this.name,
    this.zoneId,
    this.zoneName,
    this.status,
    this.capacity,
    this.totalAmount,
    this.activeOrderCount,
    this.qrCode,
  });

  factory RestaurantTable.fromJson(Map<String, dynamic> json) {
    return RestaurantTable(
      tableId: json.pickString(const ['table_id', 'tableId', 'id']),
      name: json.pickString(const ['name', 'table_name', 'tableName']),
      zoneId: json.pickString(const ['zone_id', 'zoneId']),
      zoneName: json.pickString(const ['zone_name', 'zoneName']),
      status: json.pickString(const ['status']),
      capacity: json.pickInt(const ['capacity']),
      totalAmount:
          json.pickDouble(const ['total_amount', 'totalAmount']),
      activeOrderCount:
          json.pickInt(const ['active_orders', 'activeOrderCount']),
      qrCode: json.pickString(const ['qr_code', 'qrCode']),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'tableId': tableId,
      'name': name,
      'zoneId': zoneId,
      'zoneName': zoneName,
      'status': status,
      'capacity': capacity,
      'totalAmount': totalAmount,
      'activeOrderCount': activeOrderCount,
      'qrCode': qrCode,
    };
  }

  RestaurantTable copyWith({
    String? tableId,
    String? name,
    String? zoneId,
    String? zoneName,
    String? status,
    int? capacity,
    double? totalAmount,
    int? activeOrderCount,
    String? qrCode,
  }) {
    return RestaurantTable(
      tableId: tableId ?? this.tableId,
      name: name ?? this.name,
      zoneId: zoneId ?? this.zoneId,
      zoneName: zoneName ?? this.zoneName,
      status: status ?? this.status,
      capacity: capacity ?? this.capacity,
      totalAmount: totalAmount ?? this.totalAmount,
      activeOrderCount: activeOrderCount ?? this.activeOrderCount,
      qrCode: qrCode ?? this.qrCode,
    );
  }

  @override
  String toString() => 'RestaurantTable(tableId: $tableId, name: $name, status: $status)';

  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      other is RestaurantTable &&
          runtimeType == other.runtimeType &&
          tableId == other.tableId;

  @override
  int get hashCode => tableId.hashCode;
}
