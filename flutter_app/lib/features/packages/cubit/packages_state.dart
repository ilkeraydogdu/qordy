import 'package:equatable/equatable.dart';
import 'package:qordy_app/models/package_model.dart';

abstract class PackagesState extends Equatable {
  const PackagesState();

  @override
  List<Object?> get props => [];
}

class PackagesInitial extends PackagesState {
  const PackagesInitial();
}

class PackagesLoading extends PackagesState {
  const PackagesLoading();
}

/// Ana paketler ekranının hazır olduğu durum. Subscription durumu,
/// superadmin'in önerdiği özel teklif ve mevcut paket listesi birlikte
/// tutulur; böylece billing-toggle veya paywall banner değişimleri tek
/// state emission ile ekrana gider.
class PackagesLoaded extends PackagesState {
  final List<SubscriptionPackage> packages;
  final Subscription? currentSubscription;
  final AssignedOffer? assignedOffer;
  final bool isYearly;

  const PackagesLoaded({
    required this.packages,
    this.currentSubscription,
    this.assignedOffer,
    this.isYearly = false,
  });

  PackagesLoaded copyWith({
    List<SubscriptionPackage>? packages,
    Subscription? currentSubscription,
    AssignedOffer? assignedOffer,
    bool clearAssignedOffer = false,
    bool? isYearly,
  }) {
    return PackagesLoaded(
      packages: packages ?? this.packages,
      currentSubscription: currentSubscription ?? this.currentSubscription,
      assignedOffer:
          clearAssignedOffer ? null : (assignedOffer ?? this.assignedOffer),
      isYearly: isYearly ?? this.isYearly,
    );
  }

  @override
  List<Object?> get props =>
      [packages, currentSubscription, assignedOffer, isYearly];
}

/// iyzico checkout içeriği hazır — ekran bunu alıp WebView'e yüklüyor.
class PackageCheckoutReady extends PackagesState {
  final String checkoutHtml;
  final String? paymentPageUrl;
  final String token;
  final String conversationId;
  final String returnUrl;
  final double amount;
  final String packageName;
  final String billingCycle;
  final String subscriptionId;

  const PackageCheckoutReady({
    required this.checkoutHtml,
    this.paymentPageUrl,
    required this.token,
    required this.conversationId,
    required this.returnUrl,
    required this.amount,
    required this.packageName,
    required this.billingCycle,
    required this.subscriptionId,
  });

  @override
  List<Object?> get props => [
        checkoutHtml,
        paymentPageUrl,
        token,
        conversationId,
        returnUrl,
        amount,
        packageName,
        billingCycle,
        subscriptionId,
      ];
}

/// Checkout initiate sırasında (iyzico'ya istek giderken) gösterilen
/// geçici loading durumu. PackageCheckoutReady veya PackagesError'e
/// düşer.
class PackageCheckoutInitiating extends PackagesState {
  final String packageName;
  final String billingCycle;

  const PackageCheckoutInitiating({
    required this.packageName,
    required this.billingCycle,
  });

  @override
  List<Object?> get props => [packageName, billingCycle];
}

/// Ödeme başarıyla tamamlandıktan sonra emit edilir. Ekran bu state'te
/// kutlama animasyonu gösterir, sonra loadPackages() ile normal yüklü
/// duruma geri döner.
class PackagePurchaseSucceeded extends PackagesState {
  final String packageName;
  final String billingCycle;

  const PackagePurchaseSucceeded({
    required this.packageName,
    required this.billingCycle,
  });

  @override
  List<Object?> get props => [packageName, billingCycle];
}

class PackagesError extends PackagesState {
  final String message;

  const PackagesError(this.message);

  @override
  List<Object?> get props => [message];
}
