import 'package:dio/dio.dart';
import 'package:get_it/get_it.dart';
import 'package:qordy_app/config/api_config.dart';
import 'package:qordy_app/core/network/safe_json.dart';

class DashboardApi {
  final Dio _dio = GetIt.instance<Dio>();

  /// Returns the dashboard payload as a normalized map. Even when the
  /// backend returns a null body or a non-JSON response the caller
  /// receives an empty map so we never crash the Dashboard screen.
  Future<Map<String, dynamic>> getDashboardStats({String? period}) async {
    final queryParams = <String, dynamic>{};
    if (period != null) queryParams['period'] = period;

    final response = await _dio.get(
      ApiConfig.dashboard,
      queryParameters: queryParams.isNotEmpty ? queryParams : null,
    );
    return asJsonMap(response.data);
  }
}
