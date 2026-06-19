import 'package:qordy_app/core/network/api_response.dart';
import 'package:qordy_app/models/package_model.dart';

import 'packages_api.dart';

class PackagesRepository {
  final PackagesApi _api;

  PackagesRepository({required PackagesApi api}) : _api = api;

  Future<ApiResponse<List<SubscriptionPackage>>> getPackages() =>
      _api.getPackages();

  Future<ApiResponse<Map<String, dynamic>>> purchasePackage(
    String packageId,
    String billingCycle,
  ) =>
      _api.purchasePackage(packageId, billingCycle);

  Future<ApiResponse<Map<String, dynamic>>> uploadReceipt(
    String subscriptionId,
    String filePath,
  ) =>
      _api.uploadReceipt(subscriptionId, filePath);

  Future<ApiResponse<List<Map<String, dynamic>>>> getPendingPayments() =>
      _api.getPendingPayments();

  Future<ApiResponse<Subscription>> getSubscriptionStatus() =>
      _api.getSubscriptionStatus();

  Future<ApiResponse<Map<String, dynamic>>> initiateIyzicoPayment({
    String? subscriptionId,
    String? packageId,
    String billingCycle = 'monthly',
    String returnUrl = 'qordy://payment/return',
  }) =>
      _api.initiateIyzicoPayment(
        subscriptionId: subscriptionId,
        packageId: packageId,
        billingCycle: billingCycle,
        returnUrl: returnUrl,
      );

  Future<ApiResponse<Map<String, dynamic>>> iyzicoStatus({
    String? token,
    String? conversationId,
    String? subscriptionId,
  }) =>
      _api.iyzicoStatus(
        token: token,
        conversationId: conversationId,
        subscriptionId: subscriptionId,
      );

  Future<ApiResponse<Map<String, dynamic>>> getAssignedOffer() =>
      _api.getAssignedOffer();

  Future<ApiResponse<List<AssignedOffer>>> getCustomOffers() =>
      _api.getCustomOffers();

  Future<ApiResponse<Map<String, dynamic>>> dismissCustomOffer(String linkId) =>
      _api.dismissCustomOffer(linkId);

  Future<ApiResponse<List<Map<String, dynamic>>>> getSubscriptionHistory() =>
      _api.getSubscriptionHistory();
}
