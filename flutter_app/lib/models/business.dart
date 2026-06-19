import 'package:qordy_app/core/network/safe_json.dart';

/// Tenant / business profile carried with every authenticated session.
///
/// Same rationale as [User]: the backend may return numeric IDs as ints
/// (JSON numbers) or strings depending on the endpoint, so we coerce
/// everything through [SafeJsonMap] helpers.
class Business {
  final String? customerId;
  final String? companyName;
  final String? subdomain;
  final String? email;
  final String? phone;
  final String? address;
  final String? logoUrl;
  final bool? isActive;
  final String? createdAt;

  const Business({
    this.customerId,
    this.companyName,
    this.subdomain,
    this.email,
    this.phone,
    this.address,
    this.logoUrl,
    this.isActive,
    this.createdAt,
  });

  factory Business.fromJson(Map<String, dynamic> json) {
    return Business(
      customerId: json.pickString(const ['id', 'customer_id', 'customerId']),
      companyName:
          json.pickString(const ['name', 'company_name', 'companyName']),
      subdomain: json.pickString(const ['subdomain']),
      email: json.pickString(const ['email']),
      phone: json.pickString(const ['phone']),
      address: json.pickString(const ['address']),
      logoUrl: json.pickString(const ['logo', 'logo_url', 'logoUrl']),
      isActive: json.pickBool(const ['is_active', 'isActive']),
      createdAt: json.pickString(const ['created_at', 'createdAt']),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'customerId': customerId,
      'companyName': companyName,
      'subdomain': subdomain,
      'email': email,
      'phone': phone,
      'address': address,
      'logoUrl': logoUrl,
      'isActive': isActive,
      'createdAt': createdAt,
    };
  }

  @override
  String toString() =>
      'Business(customerId: $customerId, companyName: $companyName)';

  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      other is Business &&
          runtimeType == other.runtimeType &&
          customerId == other.customerId;

  @override
  int get hashCode => customerId.hashCode;
}
