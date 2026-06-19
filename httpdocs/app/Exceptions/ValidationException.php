<?php
namespace App\Exceptions;

use Throwable;

/**
 * Validation failure (form input, business rule, schema)
 * HTTP 422 Unprocessable Entity
 */
class ValidationException extends AppException {
 protected string $errorCode = 'VALIDATION_ERROR';
 protected int $httpStatus = 422;
 protected array $errors = [];

 public function __construct(string $message = 'Doğrulama hatası', array $errors = [], array $context = [], ?Throwable $previous = null) {
 parent::__construct($message, $context, 422, 'VALIDATION_ERROR', $previous);
 $this->errors = $errors;
 }

 public function getErrors(): array {
 return $this->errors;
 }

 public function toArray(): array {
 $arr = parent::toArray();
 $arr['errors'] = $this->errors;
 return $arr;
 }
}
