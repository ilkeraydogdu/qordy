import 'package:equatable/equatable.dart';

/// Domain entity — auth session.
///
/// Veri katmanından (LoginResponseModel) bu entity'ye map'lenir.
class AuthSession extends Equatable {
 const AuthSession({
 required this.accessToken,
 required this.refreshToken,
 required this.userId,
 required this.email,
 required this.businessName,
 required this.subdomain,
 required this.expiresAt,
 this.permissions = const <String>[],
 this.requires2fa = false,
 this.twoFactorMethod = 'email',
 });

 final String accessToken;
 final String refreshToken;
 final String userId;
 final String email;
 final String businessName;
 final String subdomain;
 final DateTime expiresAt;
 final List<String> permissions;
 final bool requires2fa;
 final String twoFactorMethod;

 bool get isExpired => DateTime.now().isAfter(expiresAt);

 bool get isExpiringSoon => DateTime.now().add(const Duration(minutes: 1)).isAfter(expiresAt);

 bool hasPermission(String permission) => permissions.contains(permission);

 @override
 List<Object?> get props => [
 accessToken,
 refreshToken,
 userId,
 email,
 businessName,
 subdomain,
 expiresAt,
 permissions,
 requires2fa,
 twoFactorMethod,
 ];
}
