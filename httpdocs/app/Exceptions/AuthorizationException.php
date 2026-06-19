<?php
namespace App\Exceptions;

use Throwable;

/**
 * Authorization failure (permission, role, ownership)
 * HTTP 403 Forbidden
 */
class AuthorizationException extends AppException {
 protected string $errorCode = 'AUTHORIZATION_ERROR';
 protected int $httpStatus = 403;

 public function __construct(string $message = 'Bu işlem için yetkiniz yok', array $context = [], ?Throwable $previous = null) {
 parent::__construct($message, $context, 403, 'AUTHORIZATION_ERROR', $previous);
 }

 public static function resourceNotOwned(string $resourceType = 'resource'): self {
 return new self("Bu {$resourceType} üzerinde işlem yetkiniz yok");
 }

 public static function insufficientRole(string $required = ''): self {
 $msg = $required ? "Bu işlem için '{$required}' rolü gerekli" : 'Yetersiz yetki';
 return new self($msg);
 }
}
