import 'package:qordy_app/core/network/safe_json.dart';

/// Dashboard summary returned by `/api/mobile/staff/dashboard`.
///
/// The backend response is a grab-bag of both scoped stats (under
/// `today_stats`) and convenience scalars at the root (`pending_orders`,
/// `active_orders`, etc.). We parse both locations so the UI has a single
/// source of truth.
class DashboardStats {
  final double? totalRevenue;
  final int? totalOrders;
  final int? activeOrders;
  final int? completedOrders;
  final int? cancelledOrders;
  final double? averageOrderValue;
  final int? totalTables;
  final int? occupiedTables;
  final int? freeTables;
  final int? pendingApprovals;
  final Map<String, dynamic>? todayStats;
  final int? pendingOrders;
  final int? unreadNotifications;
  final int? activeTables;
  final String? businessDate;

  const DashboardStats({
    this.totalRevenue,
    this.totalOrders,
    this.activeOrders,
    this.completedOrders,
    this.cancelledOrders,
    this.averageOrderValue,
    this.totalTables,
    this.occupiedTables,
    this.freeTables,
    this.pendingApprovals,
    this.todayStats,
    this.pendingOrders,
    this.unreadNotifications,
    this.activeTables,
    this.businessDate,
  });

  factory DashboardStats.fromJson(Map<String, dynamic> json) {
    // Some of the most-used metrics live nested under `today_stats` —
    // pull that first so we can fall back from root → nested.
    final today = asJsonMap(json['today_stats'] ?? json['todayStats']);

    double? firstDouble(List<String> rootKeys, List<String> nestedKeys) {
      final root = json.pickDouble(rootKeys);
      if (root != null) return root;
      return today.pickDouble(nestedKeys);
    }

    int? firstInt(List<String> rootKeys, List<String> nestedKeys) {
      final root = json.pickInt(rootKeys);
      if (root != null) return root;
      return today.pickInt(nestedKeys);
    }

    return DashboardStats(
      totalRevenue: firstDouble(
        const ['total_revenue', 'totalRevenue'],
        const ['total_revenue', 'totalRevenue'],
      ),
      totalOrders: firstInt(
        const ['total_orders', 'totalOrders'],
        const ['total_orders', 'totalOrders'],
      ),
      activeOrders: json.pickInt(const ['active_orders', 'activeOrders']),
      completedOrders:
          json.pickInt(const ['completed_orders', 'completedOrders']),
      cancelledOrders:
          json.pickInt(const ['cancelled_orders', 'cancelledOrders']),
      averageOrderValue: json.pickDouble(
          const ['average_order_value', 'averageOrderValue']),
      totalTables: json.pickInt(const ['total_tables', 'totalTables']),
      occupiedTables:
          json.pickInt(const ['occupied_tables', 'occupiedTables']),
      freeTables: json.pickInt(const ['free_tables', 'freeTables']),
      pendingApprovals:
          json.pickInt(const ['pending_approvals', 'pendingApprovals']),
      todayStats: today.isEmpty ? null : today,
      pendingOrders: json.pickInt(const ['pending_orders', 'pendingOrders']),
      unreadNotifications: json
          .pickInt(const ['unread_notifications', 'unreadNotifications']),
      activeTables: json.pickInt(const ['active_tables', 'activeTables']),
      businessDate: json.pickString(const ['business_date', 'businessDate']),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'totalRevenue': totalRevenue,
      'totalOrders': totalOrders,
      'activeOrders': activeOrders,
      'completedOrders': completedOrders,
      'cancelledOrders': cancelledOrders,
      'averageOrderValue': averageOrderValue,
      'totalTables': totalTables,
      'occupiedTables': occupiedTables,
      'freeTables': freeTables,
      'pendingApprovals': pendingApprovals,
      'todayStats': todayStats,
    };
  }

  @override
  String toString() =>
      'DashboardStats(totalRevenue: $totalRevenue, totalOrders: $totalOrders, activeOrders: $activeOrders)';
}
