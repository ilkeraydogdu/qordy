import 'package:dartz/dartz.dart';

import '../../../../core/error/failures.dart';
import '../../../../core/usecase/usecase.dart';
import '../entities/order_entity.dart';
import '../repositories/orders_repository.dart';

class GetOrdersUseCase implements UseCase<List<OrderEntity>, String?> {
 const GetOrdersUseCase(this.repository);
 final OrdersRepository repository;

 @override
 Future<Either<Failure, List<OrderEntity>>> call(String? params) {
 return repository.getOrders(status: params);
 }
}

class UpdateOrderStatusUseCase implements UseCase<OrderEntity, UpdateOrderStatusParams> {
 const UpdateOrderStatusUseCase(this.repository);
 final OrdersRepository repository;

 @override
 Future<Either<Failure, OrderEntity>> call(UpdateOrderStatusParams params) {
 return repository.updateStatus(orderId: params.orderId, status: params.status);
 }
}

class UpdateOrderStatusParams {
 const UpdateOrderStatusParams({required this.orderId, required this.status});
 final String orderId;
 final OrderStatus status;
}
