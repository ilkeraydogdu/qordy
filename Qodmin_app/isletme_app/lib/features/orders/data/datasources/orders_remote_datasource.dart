import 'package:dio/dio.dart';

import '../../../../core/constants/api_constants.dart';
import '../../../../core/error/exceptions.dart';
import '../models/order_model.dart';

abstract class OrdersRemoteDataSource {
 Future<List<OrderModel>> getOrders({String? status});
 Future<OrderModel> updateStatus({required String orderId, required String status});
}

class OrdersRemoteDataSourceImpl implements OrdersRemoteDataSource {
 OrdersRemoteDataSourceImpl({required this.dio});
 final Dio dio;

 @override
 Future<List<OrderModel>> getOrders({String? status}) async {
 try {
 final response = await dio.get<dynamic>(
 ApiConstants.orders,
 queryParameters: status != null ? {'status': status} : null,
 );
 if (response.statusCode == 200 && response.data is Map) {
 final list = (response.data['orders'] as List?)
 ?? (response.data['data'] as List?)
 ?? const <dynamic>[];
 return list
 .map((e) => OrderModel.fromJson((e as Map).cast<String, dynamic>()))
 .toList();
 }
 if (response.statusCode == 200 && response.data is List) {
 return (response.data as List)
 .map((e) => OrderModel.fromJson((e as Map).cast<String, dynamic>()))
 .toList();
 }
 throw ServerException(
 statusCode: response.statusCode,
 message: 'Siparişler alınamadı',
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

 @override
 Future<OrderModel> updateStatus({
 required String orderId,
 required String status,
 }) async {
 try {
 final response = await dio.post<dynamic>(
 ApiConstants.orderStatus,
 data: {'order_id': orderId, 'status': status},
 );
 if (response.statusCode == 200 && response.data is Map) {
 return OrderModel.fromJson(
 (response.data['order'] as Map?)?.cast<String, dynamic>()
 ?? (response.data as Map).cast<String, dynamic>(),
 );
 }
 throw ServerException(
 statusCode: response.statusCode,
 message: 'Sipariş durumu güncellenemedi',
 body: response.data,
 );
 } on DioException catch (e) {
 throw NetworkException(cause: e);
 }
 }
}
