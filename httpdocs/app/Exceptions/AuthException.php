<?php
namespace App\Exceptions;

use Throwable;

/**
 * Authentication failure (login, token, 2FA)
 * HTTP 401 Unauthorized
 */
class AuthException extends AppException {
 protected string $errorCode = 'AUTH_ERROR';
 protected int $httpStatus = 401;

 public function __construct(string $message = 'Kimlik doğrulama başarısız', array $context = [], ?Throwable $previous = null) {
 parent::__construct($message, $context, 401, 'AUTH_ERROR', $previous);
 }

 public static function invalidCredentials(): self {
 return new self('Geçersiz kullanıcı adı veya şifre');
 }

 public static function sessionExpired(): self {
 return new self('Oturum süresi dolmuş. Lütfen tekrar giriş yapın.');
 }

 public static function tokenInvalid(): self {
 return new self('Geçersiz veya süresi dolmuş token');
 }

 public static function twoFactorRequired(): self {
 return new self('İki faktörlü kimlik doğrulama gerekli');
 }
}
