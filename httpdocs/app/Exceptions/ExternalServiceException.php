<?php
namespace App\Exceptions;

use Throwable;

/**
 * External service failure (API, webhook, third-party)
 * HTTP 502 Bad Gateway / 503 Service Unavailable
 */
class ExternalServiceException extends AppException {
 protected string $errorCode = 'EXTERNAL_SERVICE_ERROR';
 protected int $httpStatus = 502;

 public function __construct(string $message = 'Dış servis hatası', array $context = [], int $httpStatus = 502, ?Throwable $previous = null) {
  parent::__construct($message, $context, $httpStatus, 'EXTERNAL_SERVICE_ERROR', $previous);
 }

 public static function apiUnreachable(string $service = ''): self {
 return new self(
 $service ? "[{$service}] servisine ulaşılamıyor" : 'API erişilemez durumda',
 ['service' => $service],
 503
 );
 }

 public static function invalidResponse(string $service = '', string $detail = ''): self {
 $msg = $service ? "[{$service}] geçersiz yanıt" : 'Geçersiz servis yanıtı';
 if ($detail) {
 $msg .= ': ' . $detail;
 }
 return new self($msg, ['service' => $service, 'detail' => $detail]);
 }
}
