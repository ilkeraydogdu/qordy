import 'package:dio/dio.dart';

import '../../../../core/constants/api_constants.dart';
import '../../../../core/error/exceptions.dart';
import '../models/dashboard_summary_model.dart';

abstract class DashboardRemoteDataSource {
 Future<DashboardSummaryModel> getSummary();
}

class DashboardRemoteDataSourceImpl implements DashboardRemoteDataSource {
 DashboardRemoteDataSourceImpl({required this.dio});
 final Dio dio;

 @override
 Future<DashboardSummaryModel> getSummary() async {
 try {
 final response = await dio.get<dynamic>(ApiConstants.staffDashboard);
 if (response.statusCode == 200 && response.data is Map) {
 return DashboardSummaryModel.fromJson(response.data as Map<String, dynamic>);
 }
 throw ServerException(
 statusCode: response.statusCode,
 message: 'Dashboard verisi alınamadı',
 body: response.data,
 );
 } on DioException catch (e) {
 if (e.type == DioExceptionType.connectionError ||
 e.type == DioExceptionType.connectionTimeout ||
 e.type == DioExceptionType.receiveTimeout) {
 throw NetworkException(cause: e);
 }
 throw ServerException(
 statusCode: e.response?.statusCode,
 message: e.message ?? 'Ağ hatası',
 body: e.response?.data,
 );
 }
 }
}
