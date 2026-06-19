class Staff {
  final String? userId;
  final String? name;
  final String? email;
  final String? phone;
  final String? role;
  final String? roleId;
  final String? roleName;
  final String? pin;
  final bool? isActive;
  final String? createdAt;

  const Staff({
    this.userId,
    this.name,
    this.email,
    this.phone,
    this.role,
    this.roleId,
    this.roleName,
    this.pin,
    this.isActive,
    this.createdAt,
  });

  factory Staff.fromJson(Map<String, dynamic> json) {
    return Staff(
      userId: (json['user_id'] ?? json['userId'])?.toString(),
      name: json['name'] as String?,
      email: json['email'] as String?,
      phone: json['phone'] as String?,
      role: json['role'] as String?,
      roleId: (json['role_id'] ?? json['roleId'])?.toString(),
      roleName: (json['role_name'] ?? json['roleName']) as String?,
      pin: json['pin']?.toString(),
      isActive: (json['is_active'] ?? json['isActive']) as bool?,
      createdAt: (json['created_at'] ?? json['createdAt']) as String?,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'userId': userId,
      'name': name,
      'email': email,
      'phone': phone,
      'role': role,
      'roleId': roleId,
      'roleName': roleName,
      'pin': pin,
      'isActive': isActive,
      'createdAt': createdAt,
    };
  }

  Staff copyWith({
    String? userId,
    String? name,
    String? email,
    String? phone,
    String? role,
    String? roleId,
    String? roleName,
    String? pin,
    bool? isActive,
    String? createdAt,
  }) {
    return Staff(
      userId: userId ?? this.userId,
      name: name ?? this.name,
      email: email ?? this.email,
      phone: phone ?? this.phone,
      role: role ?? this.role,
      roleId: roleId ?? this.roleId,
      roleName: roleName ?? this.roleName,
      pin: pin ?? this.pin,
      isActive: isActive ?? this.isActive,
      createdAt: createdAt ?? this.createdAt,
    );
  }

  @override
  String toString() => 'Staff(userId: $userId, name: $name, role: $role)';

  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      other is Staff &&
          runtimeType == other.runtimeType &&
          userId == other.userId;

  @override
  int get hashCode => userId.hashCode;
}
