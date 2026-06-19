import 'package:qordy_app/core/network/safe_json.dart';
import 'package:qordy_app/features/dashboard/data/dashboard_api.dart';
import 'package:qordy_app/models/dashboard_stats.dart';
import 'package:qordy_app/models/order.dart';

class DashboardRepository {
  final DashboardApi _api;

  DashboardRepository({DashboardApi? api}) : _api = api ?? DashboardApi();

  Future<DashboardStats> getStats({String? period}) async {
    final response = await _api.getDashboardStats(period: period);
    final data = response.mapOf('data');
    if (data.isEmpty) return const DashboardStats();
    return DashboardStats.fromJson(data);
  }

  Future<List<Order>> getRecentOrders({String? period}) async {
    final response = await _api.getDashboardStats(period: period);
    final data = response.mapOf('data');
    final raw = data['recent_orders'] ?? data['recentOrders'];
    final list = asJsonList(raw);
    return list
        .map((e) => Order.fromJson(asJsonMap(e)))
        .toList(growable: false);
  }
}
