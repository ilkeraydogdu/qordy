import 'package:dartz/dartz.dart';
import '../../../../core/error/failures.dart';
import '../entities/order_entity.dart';

abstract class OrdersRepository {
 Future<Either<Failure, List<OrderEntity>>> getOrders({String? status});
 Future<Either<Failure, OrderEntity>> updateStatus({
 required String orderId,
 required OrderStatus status,
 });
}
