<?php
namespace App\Exceptions;

use Throwable;

/**
 * Resource not found
 * HTTP 404 Not Found
 */
class NotFoundException extends AppException {
 protected string $errorCode = 'NOT_FOUND';
 protected int $httpStatus = 404;

 public function __construct(string $message = 'Kayıt bulunamadı', array $context = [], ?Throwable $previous = null) {
 parent::__construct($message, $context, 404, 'NOT_FOUND', $previous);
 }

 public static function forResource(string $resource, $id = null): self {
 $msg = $id !== null
 ? "{$resource} bulunamadı (id: {$id})"
 : "{$resource} bulunamadı";
 return new self($msg, ['resource' => $resource, 'id' => $id]);
 }
}
