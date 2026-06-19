<?php
namespace App\Exceptions;

use Throwable;

/**
 * Conflict with current state (duplicate, FK, version)
 * HTTP 409 Conflict
 */
class ConflictException extends AppException {
 protected string $errorCode = 'CONFLICT';
 protected int $httpStatus = 409;

  public function __construct(string $message = 'İşlem mevcut durumla çakışıyor', array $context = [], ?Throwable $previous = null) {
 parent::__construct($message, $context, 409, 'CONFLICT', $previous);
 }

 public static function duplicate(string $field = 'record'): self {
 return new self("Bu {$field} zaten mevcut");
 }

 public static function alreadyDeleted(): self {
 return new self('Kayıt zaten silinmiş');
 }
}
