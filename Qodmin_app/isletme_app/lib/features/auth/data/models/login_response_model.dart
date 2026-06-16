import '../../domain/entities/auth_session.dart';

/// Backend'den gelen login response model'i.
///
/// Backend formatı (MobileAPIController@managerLogin):
/// ```json
/// {
/// "access_token": "...",
/// "refresh_token": "...",
/// "expires_in": 3600,
/// "user": { "id": "...", "email": "...", "name": "..." },
/// "business": { "name": "...", "subdomain": "..." },
/// "permissions": ["..."]
/// }
///```
class LoginResponseModel {
 const LoginResponseModel({
 required this.accessToken,
 required this.refreshToken,
 required this.expiresIn,
 required this.userId,
 required this.email,
 required this.businessName,
 required this.subdomain,
 required this.permissions,
 required this.requires2fa,
 required this.twoFactorMethod,
 });

 final String accessToken;
 final String refreshToken;
 final int expiresIn;
 final String userId;
 final String email;
 final String businessName;
 final String subdomain;
 final List<String> permissions;
 final bool requires2fa;
 final String twoFactorMethod;

 factory LoginResponseModel.fromJson(Map<String, dynamic> json) {
 final user = (json['user'] as Map?)?.cast<String, dynamic>() ?? const <String, dynamic>{};
 final business = (json['business'] as Map?)?.cast<String, dynamic>() ?? const <String, dynamic>{};

 return LoginResponseModel(
 accessToken: json['access_token'] as String? ?? '',
 refreshToken: json['refresh_token'] as String? ?? '',
 expiresIn: (json['expires_in'] as num?)?.toInt() ?? 3600,
 userId: user['id']?.toString() ?? '',
 email: user['email'] as String? ?? '',
 businessName: business['name'] as String? ?? '',
 subdomain: business['subdomain'] as String? ?? '',
 permissions: (json['permissions'] as List?)
 ?.map((e) => e.toString())
 .toList() ??
 const <String>[],
 requires2fa: json['requires_2fa'] as bool? ?? json['requires2fa'] as bool? ?? false,
 twoFactorMethod: json['two_factor_method'] as String?
 ?? json['twoFactorMethod'] as String?
 ?? 'email',
 );
 }

 AuthSession toEntity() {
 return AuthSession(
 accessToken: accessToken,
 refreshToken: refreshToken,
 userId: userId,
 email: email,
 businessName: businessName,
 subdomain: subdomain,
 expiresAt: DateTime.now().add(Duration(seconds: expiresIn)),
 permissions: permissions,
 requires2fa: requires2fa,
 twoFactorMethod: twoFactorMethod,
 );
 }
}
