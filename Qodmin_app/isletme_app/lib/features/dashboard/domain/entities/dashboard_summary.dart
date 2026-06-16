import 'package:equatable/equatable.dart';

/// Dashboard KPI özet verisi.
class DashboardSummary extends Equatable {
 const DashboardSummary({
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

 double get tableOccupancyRate =>
 totalTables == 0 ? 0 : (occupiedTables / totalTables) * 100;

 @override
 List<Object?> get props => [
 todayOrders,
 todayRevenue,
 activeOrders,
 occupiedTables,
 totalTables,
 pendingKitchen,
 avgOrderValue,
 topSellingItems,
 revenueTrend,
 ];
}

class TopItem extends Equatable {
 const TopItem({
 required this.id,
 required this.name,
 required this.soldCount,
 required this.revenue,
 });
 final String id;
 final String name;
 final int soldCount;
 final double revenue;

 @override
 List<Object?> get props => [id, name, soldCount, revenue];
}

class RevenuePoint extends Equatable {
 const RevenuePoint({required this.hour, required this.amount});
 final int hour;
 final double amount;

 @override
 List<Object?> get props => [hour, amount];
}
