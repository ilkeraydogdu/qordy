/// Veri kaynağı katmanında fırlatılan düşük seviye exception'lar.
///
/// Repository'ler bunları yakalayıp [Failure] üretir.
class ServerException implements Exception {
 ServerException({this.statusCode, this.message = 'Sunucu hatası', this.body});

 final int? statusCode;
 final String message;
 final Object? body;

 @override
 String toString() => 'ServerException($statusCode, $message)';
}

class NetworkException implements Exception {
 NetworkException({this.message = 'Ağ bağlantısı başarısız', this.cause});

 final String message;
 final Object? cause;

 @override
 String toString() => 'NetworkException($message)';
}

class TokenExpiredException implements Exception {
 TokenExpiredException({this.message = 'Token süresi doldu'});

 final String message;

 @override
 String toString() => 'TokenExpiredException($message)';
}

class TwoFactorRequiredException implements Exception {
 TwoFactorRequiredException({required this.method, this.message = '2FA gerekli'});

 final String method;
 final String message;

 @override
 String toString() => 'TwoFactorRequiredException($method)';
}

class CertificateException implements Exception {
 CertificateException({this.message = 'Sertifika doğrulanamadı', this.cause});

 final String message;
 final Object? cause;

 @override
 String toString() => 'CertificateException($message)';
}

class RateLimitException implements Exception {
 RateLimitException({this.retryAfter, this.message = 'Rate limit aşıldı'});

 final Duration? retryAfter;
 final String message;

 @override
 String toString() => 'RateLimitException($message)';
}

class CacheException implements Exception {
 CacheException({this.message = 'Önbellek hatası', this.cause});

 final String message;
 final Object? cause;

 @override
 String toString() => 'CacheException($message)';
}
