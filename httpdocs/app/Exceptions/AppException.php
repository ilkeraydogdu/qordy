<?php
namespace App\Exceptions;

use Exception;
use Throwable;

/**
 * Base application exception
 * All custom exceptions in Qordy extend this class.
 */
class AppException extends Exception {
 protected string $errorCode = 'APP_ERROR';
 protected int $httpStatus = 500;
 protected array $context = [];

 public function __construct(string $message = '', array $context = [], int $httpStatus = 0, string $errorCode = '', ?Throwable $previous = null) {
 parent::__construct($message, 0, $previous);
 if (!empty($context)) {
 $this->context = $context;
 }
 if ($httpStatus > 0) {
 $this->httpStatus = $httpStatus;
 }
 if (!empty($errorCode)) {
 $this->errorCode = $errorCode;
 }
 }

 public function getErrorCode(): string {
 return $this->errorCode;
 }

 public function getHttpStatus(): int {
 return $this->httpStatus;
 }

 public function getContext(): array {
 return $this->context;
 }

 public function toArray(): array {
 return [
 'success' => false,
 'error_code' => $this->errorCode,
 'message' => $this->getMessage(),
 ];
 }
}
