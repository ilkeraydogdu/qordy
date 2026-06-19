import 'package:dio/dio.dart';
import 'package:qordy_app/config/api_config.dart';
import 'package:qordy_app/core/network/api_client.dart';
import 'package:qordy_app/core/network/api_response.dart';
import 'package:qordy_app/core/network/safe_json.dart';
import 'package:qordy_app/models/package_model.dart';

class PackagesApi {
  final ApiClient _apiClient;

  PackagesApi({required ApiClient apiClient}) : _apiClient = apiClient;

  Future<ApiResponse<List<SubscriptionPackage>>> getPackages() {
    return _apiClient.get<List<SubscriptionPackage>>(
      ApiConfig.packages,
      fromJson: (json) => pickListOf<SubscriptionPackage>(
        json,
        SubscriptionPackage.fromJson,
        preferKeys: const ['packages'],
      ),
    );
  }

  Future<ApiResponse<Map<String, dynamic>>> purchasePackage(
    String packageId,
    String billingCycle,
  ) {
    // Backend snake_case alan isimleri bekliyor.
    final pricingType = billingCycle == 'yearly'
        ? 'yearly'
        : (billingCycle == 'one_time' ? 'one_time' : 'monthly');
    return _apiClient.post<Map<String, dynamic>>(
      ApiConfig.packageSubscribe,
      data: {
        'package_id': packageId,
        'pricing_type': pricingType,
      },
      fromJson: (json) => json as Map<String, dynamic>,
    );
  }

  Future<ApiResponse<Map<String, dynamic>>> uploadReceipt(
    String subscriptionId,
    String filePath,
  ) async {
    final formData = FormData.fromMap({
      'subscription_id': subscriptionId,
      'receipt': await MultipartFile.fromFile(filePath),
    });
    return _apiClient.post<Map<String, dynamic>>(
      ApiConfig.packageUploadReceipt,
      data: formData,
      fromJson: (json) => json as Map<String, dynamic>,
    );
  }

  Future<ApiResponse<List<Map<String, dynamic>>>> getPendingPayments() {
    return _apiClient.get<List<Map<String, dynamic>>>(
      ApiConfig.packagePendingPayments,
      fromJson: (json) => pickListOf<Map<String, dynamic>>(
        json,
        (m) => m,
        preferKeys: const ['transfers', 'payments'],
      ),
    );
  }

  Future<ApiResponse<Subscription>> getSubscriptionStatus() {
    return _apiClient.get<Subscription>(
      ApiConfig.subscriptionStatus,
      fromJson: (json) => Subscription.fromJson(asJsonMap(json)),
    );
  }

  Future<ApiResponse<Map<String, dynamic>>> initiateIyzicoPayment({
    String? subscriptionId,
    String? packageId,
    String billingCycle = 'monthly',
    String returnUrl = 'qordy://payment/return',
  }) {
    return _apiClient.post<Map<String, dynamic>>(
      ApiConfig.paymentIyzicoInitiate,
      data: {
        if (subscriptionId != null) 'subscription_id': subscriptionId,
        if (packageId != null) 'package_id': packageId,
        'billing_cycle': billingCycle,
        'return_url': returnUrl,
      },
      fromJson: (json) => json as Map<String, dynamic>,
    );
  }

  /// Poll iyzico ödeme durumunu. Bu endpoint, WebView deeplink callback
  /// kaçırıldığında (uygulama kapatıldı, scheme kayıtlı değil, vb.)
  /// asenkron olarak başarı/başarısızlığı öğrenmemizi sağlar.
  Future<ApiResponse<Map<String, dynamic>>> iyzicoStatus({
    String? token,
    String? conversationId,
    String? subscriptionId,
  }) {
    final qp = <String, dynamic>{};
    if (token != null && token.isNotEmpty) qp['token'] = token;
    if (conversationId != null && conversationId.isNotEmpty) {
      qp['conversation_id'] = conversationId;
    }
    if (subscriptionId != null && subscriptionId.isNotEmpty) {
      qp['subscription_id'] = subscriptionId;
    }
    return _apiClient.get<Map<String, dynamic>>(
      ApiConfig.paymentIyzicoStatus,
      queryParameters: qp,
      fromJson: (json) => json as Map<String, dynamic>,
    );
  }

  /// Superadmin bu tenant için ödeme linki hazırladıysa getirir.
  Future<ApiResponse<Map<String, dynamic>>> getAssignedOffer() {
    return _apiClient.get<Map<String, dynamic>>(
      ApiConfig.packagesAssignedOffer,
      fromJson: (json) => json as Map<String, dynamic>,
    );
  }

  /// Bu müşteri için hazırlanmış tüm aktif özel teklifleri (cooldown/dismissal
  /// bilgisiyle birlikte) döner. Popup kapandıktan sonra badge'de kalır.
  Future<ApiResponse<List<AssignedOffer>>> getCustomOffers() {
    return _apiClient.get<List<AssignedOffer>>(
      ApiConfig.packagesCustomOffers,
      fromJson: (json) => pickListOf<AssignedOffer>(
        json,
        AssignedOffer.fromJson,
        preferKeys: const ['offers'],
      ),
    );
  }

  Future<ApiResponse<Map<String, dynamic>>> dismissCustomOffer(String linkId) {
    return _apiClient.post<Map<String, dynamic>>(
      ApiConfig.packagesCustomOfferDismiss(linkId),
      data: const {},
      fromJson: (json) => json as Map<String, dynamic>,
    );
  }

  /// Müşterinin abonelik + ödeme geçmişi.
  Future<ApiResponse<List<Map<String, dynamic>>>> getSubscriptionHistory() {
    return _apiClient.get<List<Map<String, dynamic>>>(
      ApiConfig.subscriptionHistory,
      fromJson: (json) => pickListOf<Map<String, dynamic>>(
        json,
        (m) => m,
        preferKeys: const ['history'],
      ),
    );
  }
}
