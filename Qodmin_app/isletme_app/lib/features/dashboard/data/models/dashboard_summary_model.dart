import '../../domain/entities/dashboard_summary.dart';

class DashboardSummaryModel {
 DashboardSummaryModel({
 required this.todayOrders,
 required this.todayRevenue,
 required this.activeOrders,
 required this.occupiedTables,
 required this.totalTables,
 required this.pendingKitchen,
 required this.avgOrderValue,
 required this.topSellingItems,
 required this.revenueTrend,
 });

 final int todayOrders;
 final double todayRevenue;
 final int activeOrders;
 final int occupiedTables;
 final int totalTables;
 final int pendingKitchen;
 final double avgOrderValue;
 final List<TopItem> topSellingItems;
 final List<RevenuePoint> revenueTrend;

 factory DashboardSummaryModel.fromJson(Map<String, dynamic> json) {
 return DashboardSummaryModel(
 todayOrders: (json['today_orders'] as num?)?.toInt() ?? 0,
 todayRevenue: (json['today_revenue'] as num?)?.toDouble() ?? 0.0,
 activeOrders: (json['active_orders'] as num?)?.toInt() ?? 0,
 occupiedTables: (json['occupied_tables'] as num?)?.toInt() ?? 0,
 totalTables: (json['total_tables'] as num?)?.toInt() ?? 0,
 pendingKitchen: (json['pending_kitchen'] as num?)?.toInt() ?? 0,
 avgOrderValue: (json['avg_order_value'] as num?)?.toDouble() ?? 0.0,
 topSellingItems: (json['top_items'] as List?)
 ?.map((e) => TopItem(
 id: e['id']?.toString() ?? '',
 name: e['name']?.toString() ?? '',
 soldCount: (e['sold_count'] as num?)?.toInt() ?? 0,
 revenue: (e['revenue'] as num?)?.toDouble() ?? 0.0,
 ))
 .toList() ??
 const <TopItem>[],
 revenueTrend: (json['revenue_trend'] as List?)
 ?.map((e) => RevenuePoint(
 hour: (e['hour'] as num?)?.toInt() ?? 0,
 amount: (e['amount'] as num?)?.toDouble() ?? 0.0,
 ))
 .toList() ??
 const <RevenuePoint>[],
 );
 }

 DashboardSummary toEntity() {
 return DashboardSummary(
 todayOrders: todayOrders,
 todayRevenue: todayRevenue,
 activeOrders: activeOrders,
 occupiedTables: occupiedTables,
 totalTables: totalTables,
 pendingKitchen: pendingKitchen,
 avgOrderValue: avgOrderValue,
 topSellingItems: topSellingItems,
 revenueTrend: revenueTrend,
 );
 }
}
