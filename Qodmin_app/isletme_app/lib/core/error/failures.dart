import 'package:equatable/equatable.dart';

/// Uygulama katmanları arasında hata durumunu temsil eden sealed sınıf.
///
/// Network/parsing/storage gibi düşük seviye hatalar repository'de
/// [Failure]'a dönüştürülür, UI katmanı yalnızca bu tipleri görür.
sealed class Failure extends Equatable {
 const Failure({this.message = '', this.cause});

 final String message;
 final Object? cause;

 @override
 List<Object?> get props => [message, cause.runtimeType, cause?.toString()];
}

class NetworkFailure extends Failure {
 const NetworkFailure({super.message = 'Ağ hatası', super.cause});
}

class ServerFailure extends Failure {
 const ServerFailure({this.statusCode, super.message = 'Sunucu hatası', super.cause});

 final int? statusCode;

 @override
 List<Object?> get props => [...super.props, statusCode];
}

class AuthFailure extends Failure {
 const AuthFailure({this.code, super.message = 'Yetkilendirme hatası', super.cause});

 final String? code;

 @override
 List<Object?> get props => [...super.props, code];
}

class TokenExpiredFailure extends AuthFailure {
 const TokenExpiredFailure({super.message = 'Oturum süresi doldu'}) : super(code: 'token_expired');
}

class TwoFactorRequiredFailure extends AuthFailure {
 const TwoFactorRequiredFailure({required this.method, super.message = '2FA gerekli'})
 : super(code: '2fa_required');

 final String method;

 @override
 List<Object?> get props => [...super.props, method];
}

class ValidationFailure extends Failure {
 const ValidationFailure({required this.errors, super.message = 'Doğrulama hatası', super.cause});

 final Map<String, List<String>> errors;

 @override
 List<Object?> get props => [...super.props, errors];
}

class RateLimitFailure extends Failure {
 const RateLimitFailure({this.retryAfter, super.message = 'Çok fazla istek', super.cause});

 final Duration? retryAfter;

 @override
 List<Object?> get props => [...super.props, retryAfter];
}

class CacheFailure extends Failure {
 const CacheFailure({super.message = 'Önbellek hatası', super.cause});
}

class SecurityFailure extends Failure {
 const SecurityFailure({super.message = 'Güvenlik ihlali', super.cause});
}

class CertificateFailure extends SecurityFailure {
 const CertificateFailure({super.message = 'Sunucu sertifikası doğrulanamadı', super.cause});
}

class JailbreakFailure extends SecurityFailure {
 const JailbreakFailure({super.message = 'Cihaz güvenli değil', super.cause});
}

class UnknownFailure extends Failure {
 const UnknownFailure({super.message = 'Bilinmeyen hata', super.cause});
}
