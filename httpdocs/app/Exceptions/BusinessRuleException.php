<?php
namespace App\Exceptions;

use Throwable;

/**
 * Business rule violation
 * HTTP 422 Unprocessable Entity
 */
class BusinessRuleException extends AppException {
 protected string $errorCode = 'BUSINESS_RULE_VIOLATION';
 protected int $httpStatus = 422;

 public function __construct(string $message = 'İş kuralı ihlali', array $context = [], ?Throwable $previous = null) {
 parent::__construct($message, $context, 422, 'BUSINESS_RULE_VIOLATION', $previous);
 }

 public static function stockInsufficient(string $item = ''): self {
 return new self(
 $item ? "'{$item}' için yeterli stok yok" : 'Yeterli stok yok',
 ['item' => $item]
 );
 }

 public static function orderNotEditable(string $status = ''): self {
 return new self(
 $status ? "'{$status}' durumundaki sipariş düzenlenemez" : 'Sipariş düzenlenemez',
 ['status' => $status]
 );
 }

 public static function subscriptionExpired(): self {
 return new self('Abonelik süresi dolmuş');
 }

 public static function trialExpired(): self {
 return new self('Deneme süresi dolmuş');
 }
}
