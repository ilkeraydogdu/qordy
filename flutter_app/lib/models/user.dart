import 'package:qordy_app/core/network/safe_json.dart';

/// Immutable representation of an authenticated user.
///
/// The backend (loosely-typed PHP) frequently sends numeric primary keys
/// as raw `int` values while other endpoints echo them back as strings.
/// We coerce everything to [String] on the client so downstream code
/// (router redirects, cubit guards, storage round-trips) can stay simple.
class User {
  final String? userId;
  final String? name;
  final String? firstName;
  final String? lastName;
  final String? email;
  final String? role;
  final String? tenantId;
  final String? pin;
  final String? phone;
  final String? avatar;
  final bool? isActive;
  final bool? isManager;
  final String? roleId;
  final String? createdAt;

  const User({
    this.userId,
    this.name,
    this.firstName,
    this.lastName,
    this.email,
    this.role,
    this.tenantId,
    this.pin,
    this.phone,
    this.avatar,
    this.isActive,
    this.isManager,
    this.roleId,
    this.createdAt,
  });

  /// İnsan-okur ad. Öncelik sırası:
  ///   1. [name] (dolu ve email'e eşit değilse),
  ///   2. [firstName] + [lastName] (varsa),
  ///   3. [email] (son çare).
  String get displayName {
    final n = (name ?? '').trim();
    if (n.isNotEmpty && n.toLowerCase() != (email ?? '').toLowerCase()) {
      return n;
    }
    final composed = [firstName ?? '', lastName ?? '']
        .map((p) => p.trim())
        .where((p) => p.isNotEmpty)
        .join(' ');
    if (composed.isNotEmpty) return composed;
    if (n.isNotEmpty) return n;
    return (email ?? '').trim();
  }

  factory User.fromJson(Map<String, dynamic> json) {
    return User(
      userId: json.pickString(const ['id', 'user_id', 'userId']),
      name: json.pickString(const ['name', 'username', 'full_name']),
      firstName: json.pickString(const ['first_name', 'firstName', 'given_name']),
      lastName: json.pickString(const ['last_name', 'lastName', 'family_name']),
      email: json.pickString(const ['email']),
      // Prefer canonical `role_code` coming off the `roles` table joined
      // server-side (guaranteed to be an uppercase AppRole wire value).
      // Fall back to the free-text `role` column for older backends.
      role: json.pickString(const ['role_code', 'role']),
      tenantId: json.pickString(const ['tenant_id', 'tenantId', 'business_id']),
      pin: json.pickString(const ['pin']),
      phone: json.pickString(const ['phone']),
      avatar: json.pickString(const ['avatar']),
      isActive: json.pickBool(const ['is_active', 'isActive', 'active']),
      isManager: json.pickBool(const ['is_manager', 'isManager']),
      roleId: json.pickString(const ['role_id', 'roleId']),
      createdAt: json.pickString(const ['created_at', 'createdAt']),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'userId': userId,
      'name': name,
      'firstName': firstName,
      'lastName': lastName,
      'email': email,
      'role': role,
      'tenantId': tenantId,
      'pin': pin,
      'phone': phone,
      'avatar': avatar,
      'isActive': isActive,
      'isManager': isManager,
      'roleId': roleId,
      'createdAt': createdAt,
    };
  }

  User copyWith({
    String? userId,
    String? name,
    String? firstName,
    String? lastName,
    String? email,
    String? role,
    String? tenantId,
    String? pin,
    String? phone,
    String? avatar,
    bool? isActive,
    bool? isManager,
    String? roleId,
    String? createdAt,
  }) {
    return User(
      userId: userId ?? this.userId,
      name: name ?? this.name,
      firstName: firstName ?? this.firstName,
      lastName: lastName ?? this.lastName,
      email: email ?? this.email,
      role: role ?? this.role,
      tenantId: tenantId ?? this.tenantId,
      pin: pin ?? this.pin,
      phone: phone ?? this.phone,
      avatar: avatar ?? this.avatar,
      isActive: isActive ?? this.isActive,
      isManager: isManager ?? this.isManager,
      roleId: roleId ?? this.roleId,
      createdAt: createdAt ?? this.createdAt,
    );
  }

  @override
  String toString() => 'User(userId: $userId, name: $name, role: $role)';

  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      other is User &&
          runtimeType == other.runtimeType &&
          userId == other.userId;

  @override
  int get hashCode => userId.hashCode;
}
