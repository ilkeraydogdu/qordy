import 'package:dartz/dartz.dart';

import '../../../../core/error/exceptions.dart';
import '../../../../core/error/failures.dart';
import '../../domain/entities/order_entity.dart';
import '../../domain/repositories/orders_repository.dart';
import '../datasources/orders_remote_datasource.dart';

class OrdersRepositoryImpl implements OrdersRepository {
 OrdersRepositoryImpl({required this.remote});
 final OrdersRemoteDataSource remote;

 @override
 Future<Either<Failure, List<OrderEntity>>> getOrders({String? status}) async {
 try {
 final list = await remote.getOrders(status: status);
 return Right(list.map((m) => m.toEntity()).toList());
 } on NetworkException catch (e) {
 return Left(NetworkFailure(cause: e));
 } on ServerException catch (e) {
 return Left(ServerFailure(statusCode: e.statusCode, message: e.message, cause: e));
 } catch (e) {
 return Left(UnknownFailure(cause: e));
 }
 }

 @override
 Future<Either<Failure, OrderEntity>> updateStatus({
 required String orderId,
 required OrderStatus status,
 }) async {
 try {
 final model = await remote.updateStatus(
 orderId: orderId,
 status: _statusToApi(status),
 );
 return Right(model.toEntity());
 } on NetworkException catch (e) {
 return Left(NetworkFailure(cause: e));
 } on ServerException catch (e) {
 return Left(ServerFailure(statusCode: e.statusCode, message: e.message, cause: e));
 }
 }

 String _statusToApi(OrderStatus s) {
 switch (s) {
 case OrderStatus.pending:
 return 'pending';
 case OrderStatus.confirmed:
 return 'confirmed';
 case OrderStatus.preparing:
 return 'preparing';
 case OrderStatus.ready:
 return 'ready';
 case OrderStatus.served:
 return 'served';
 case OrderStatus.cancelled:
 return 'cancelled';
 case OrderStatus.completed:
 return 'completed';
 }
 }
}
