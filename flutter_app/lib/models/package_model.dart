class SubscriptionPackage {
  final String? packageId;
  final String? name;
  final String? description;
  final double? priceMonthly;
  final double? priceYearly;
  final double? priceOneTime;
  final Map<String, dynamic>? features;
  final bool? isPopular;

  const SubscriptionPackage({
    this.packageId,
    this.name,
    this.description,
    this.priceMonthly,
    this.priceYearly,
    this.priceOneTime,
    this.features,
    this.isPopular,
  });

  factory SubscriptionPackage.fromJson(Map<String, dynamic> json) {
    // Defensive number parsing — PHP bazen `"9.99"` (string) döndürüyor,
    // `num` cast'i o durumda patlıyor ve tüm Paketler ekranı çöküyordu.
    double? toDouble(dynamic v) {
      if (v == null) return null;
      if (v is num) return v.toDouble();
      return double.tryParse(v.toString());
    }

    // `features` alanı bazen `Map<String, dynamic>`, bazen
    // `Map<dynamic, dynamic>`, bazen `null` dönüyor; bazen de backend
    // boş dizi (`[]`) olarak döndürüyor. Körü körüne `as` yerine
    // normalize edip tek bir `Map<String, dynamic>`'e çeviriyoruz.
    Map<String, dynamic>? normalizeFeatures(dynamic raw) {
      if (raw == null) return null;
      if (raw is Map<String, dynamic>) return raw;
      if (raw is Map) {
        return raw.map((k, v) => MapEntry(k.toString(), v));
      }
      // `[]` veya beklenmeyen bir tip geldiğinde sessizce atla.
      return null;
    }

    bool? parseBool(dynamic v) {
      if (v == null) return null;
      if (v is bool) return v;
      if (v is num) return v != 0;
      final s = v.toString().toLowerCase();
      if (s == 'true' || s == '1' || s == 'yes') return true;
      if (s == 'false' || s == '0' || s == 'no') return false;
      return null;
    }

    return SubscriptionPackage(
      packageId: (json['package_id'] ?? json['packageId'])?.toString(),
      name: json['name']?.toString(),
      description: json['description']?.toString(),
      priceMonthly: toDouble(json['price_monthly'] ?? json['priceMonthly']),
      priceYearly: toDouble(json['price_yearly'] ?? json['priceYearly']),
      priceOneTime: toDouble(json['price_one_time'] ?? json['priceOneTime']),
      features: normalizeFeatures(json['features']),
      isPopular: parseBool(json['is_popular'] ?? json['isPopular']),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'packageId': packageId,
      'name': name,
      'description': description,
      'priceMonthly': priceMonthly,
      'priceYearly': priceYearly,
      'priceOneTime': priceOneTime,
      'features': features,
      'isPopular': isPopular,
    };
  }

  @override
  String toString() => 'SubscriptionPackage(packageId: $packageId, name: $name)';

  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      other is SubscriptionPackage &&
          runtimeType == other.runtimeType &&
          packageId == other.packageId;

  @override
  int get hashCode => packageId.hashCode;
}

/// Superadmin tarafından bu müşteri için özelleştirilmiş ödeme teklifi.
/// Paywall ekranında öne çıkan banner olarak gösterilir ve kullanıcıyı
/// hazır checkout linkine yönlendirir.
class AssignedOffer {
  final String? linkId;
  final String? token;
  final String? publicUrl;
  final String? packageId;
  final String? packageName;
  final double? customPrice;
  final int? durationMonths;
  final String? note;
  final String? expiresAt;
  final bool shouldShowPopup;
  final int dismissCount;
  final int cooldownMinutes;

  const AssignedOffer({
    this.linkId,
    this.token,
    this.publicUrl,
    this.packageId,
    this.packageName,
    this.customPrice,
    this.durationMonths,
    this.note,
    this.expiresAt,
    this.shouldShowPopup = true,
    this.dismissCount = 0,
    this.cooldownMinutes = 45,
  });

  factory AssignedOffer.fromJson(Map<String, dynamic> json) {
    double? toDouble(dynamic v) {
      if (v == null) return null;
      if (v is num) return v.toDouble();
      return double.tryParse(v.toString());
    }

    int? toInt(dynamic v) {
      if (v == null) return null;
      if (v is num) return v.toInt();
      return int.tryParse(v.toString());
    }

    return AssignedOffer(
      linkId: json['link_id']?.toString(),
      token: json['token']?.toString(),
      publicUrl: json['public_url']?.toString(),
      packageId: json['package_id']?.toString(),
      packageName: json['package_name']?.toString(),
      customPrice: toDouble(json['custom_price']),
      durationMonths: toInt(json['duration_months']),
      note: json['note']?.toString(),
      expiresAt: json['expires_at']?.toString(),
      shouldShowPopup: (json['should_show_popup'] ?? true) == true,
      dismissCount: toInt(json['dismiss_count']) ?? 0,
      cooldownMinutes: toInt(json['cooldown_minutes']) ?? 45,
    );
  }
}

/// Subscription phase returned by the backend `TrialService::getSubscriptionPhase`.
///
/// - [trial]: Kullanıcı 7 günlük ücretsiz denemede.
/// - [active]: Ücretli abonelik aktif.
/// - [grace]: Deneme süresi doldu, 7 günlük salt-okunur grace periyodunda.
/// - [suspended]: Grace da doldu — işletme askıya alındı.
/// - [expired]: Abonelik süresi doldu (çoğunlukla suspended ile eş).
/// - [none]: Abonelik yok.
enum SubscriptionPhase { trial, active, grace, suspended, expired, none }

SubscriptionPhase _parsePhase(String? raw) {
  switch ((raw ?? '').toLowerCase()) {
    case 'trial':
      return SubscriptionPhase.trial;
    case 'active':
      return SubscriptionPhase.active;
    case 'grace':
    case 'grace_period':
      return SubscriptionPhase.grace;
    case 'suspended':
      return SubscriptionPhase.suspended;
    case 'expired':
      return SubscriptionPhase.expired;
    default:
      return SubscriptionPhase.none;
  }
}

class Subscription {
  final String? subscriptionId;
  final String? packageId;
  final String? packageName;
  final String? status;
  final String? startDate;
  final String? endDate;
  final String? currentPeriodEnd;
  final double? amount;

  /// Yeni 7-gün trial + 7-gün grace mimarisi alanları
  final SubscriptionPhase phase;
  final int daysLeft;
  final int graceDaysLeft;
  final bool readOnly;
  final bool isTrial;
  final String? trialEndsAt;
  final String? graceEndsAt;

  const Subscription({
    this.subscriptionId,
    this.packageId,
    this.packageName,
    this.status,
    this.startDate,
    this.endDate,
    this.currentPeriodEnd,
    this.amount,
    this.phase = SubscriptionPhase.none,
    this.daysLeft = 0,
    this.graceDaysLeft = 0,
    this.readOnly = false,
    this.isTrial = false,
    this.trialEndsAt,
    this.graceEndsAt,
  });

  bool get canMutate => !readOnly &&
      phase != SubscriptionPhase.grace &&
      phase != SubscriptionPhase.suspended &&
      phase != SubscriptionPhase.expired;

  bool get isSuspended =>
      phase == SubscriptionPhase.suspended || phase == SubscriptionPhase.expired;

  bool get isGrace => phase == SubscriptionPhase.grace;

  bool get isTrialPhase => phase == SubscriptionPhase.trial || isTrial;

  bool get hasActiveAccess =>
      phase == SubscriptionPhase.trial || phase == SubscriptionPhase.active;

  factory Subscription.fromJson(Map<String, dynamic> json) {
    // /subscription/status phase bilgisini kök seviyede, paket/abonelik
    // kimliklerini ise `subscription` alt objesinde döndürüyor. İki
    // kaynaktan da güvenli okumak için merge ediyoruz.
    Map<String, dynamic> nested = const <String, dynamic>{};
    final nestedRaw = json['subscription'];
    if (nestedRaw is Map) {
      nested = nestedRaw.map((k, v) => MapEntry(k.toString(), v));
    }

    dynamic pick(String snake, String camel) =>
        json[snake] ?? json[camel] ?? nested[snake] ?? nested[camel];

    String? asString(dynamic v) => v?.toString();

    double? asDouble(dynamic v) {
      if (v == null) return null;
      if (v is num) return v.toDouble();
      return double.tryParse(v.toString());
    }

    int asInt(dynamic v, {int fallback = 0}) {
      if (v == null) return fallback;
      if (v is int) return v;
      if (v is num) return v.toInt();
      return int.tryParse(v.toString()) ?? fallback;
    }

    bool asBool(dynamic v) {
      if (v is bool) return v;
      if (v is num) return v != 0;
      if (v == null) return false;
      final s = v.toString().toLowerCase();
      return s == 'true' || s == '1' || s == 'yes';
    }

    return Subscription(
      subscriptionId: asString(pick('subscription_id', 'subscriptionId')),
      packageId: asString(pick('package_id', 'packageId')),
      packageName: asString(pick('package_name', 'packageName')),
      status: asString(pick('status', 'status')),
      startDate: asString(pick('start_date', 'startDate')),
      endDate: asString(pick('end_date', 'endDate')),
      currentPeriodEnd:
          asString(pick('current_period_end', 'currentPeriodEnd')),
      amount: asDouble(pick('amount', 'amount')),
      phase: _parsePhase(asString(json['phase'])),
      daysLeft: asInt(json['daysLeft'] ?? json['days_left']),
      graceDaysLeft:
          asInt(json['graceDaysLeft'] ?? json['grace_days_left']),
      readOnly: asBool(json['readOnly'] ?? json['read_only']),
      isTrial: asBool(json['isTrial'] ?? json['is_trial']),
      trialEndsAt: asString(json['trialEndsAt'] ?? json['trial_ends_at']),
      graceEndsAt: asString(json['graceEndsAt'] ?? json['grace_ends_at']),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'subscriptionId': subscriptionId,
      'packageId': packageId,
      'packageName': packageName,
      'status': status,
      'startDate': startDate,
      'endDate': endDate,
      'currentPeriodEnd': currentPeriodEnd,
      'amount': amount,
      'phase': phase.name,
      'daysLeft': daysLeft,
      'graceDaysLeft': graceDaysLeft,
      'readOnly': readOnly,
      'isTrial': isTrial,
      'trialEndsAt': trialEndsAt,
      'graceEndsAt': graceEndsAt,
    };
  }

  @override
  String toString() => 'Subscription(subscriptionId: $subscriptionId, phase: ${phase.name}, daysLeft: $daysLeft)';

  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      other is Subscription &&
          runtimeType == other.runtimeType &&
          subscriptionId == other.subscriptionId;

  @override
  int get hashCode => subscriptionId.hashCode;
}
