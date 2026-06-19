<?php
namespace App\Exceptions;

use Throwable;

/**
 * Payment gateway / financial operation failure
 * HTTP 402 Payment Required
 */
class PaymentException extends AppException {
 protected string $errorCode = 'PAYMENT_ERROR';
 protected int $httpStatus = 402;

 public function __construct(string $message = 'Ödeme işlemi başarısız', array $context = [], ?Throwable $previous = null) {
 parent::__construct($message, $context, 402, 'PAYMENT_ERROR', $previous);
 }

 public static function gatewayError(string $gateway = '', string $detail = ''): self {
 $msg = $gateway ? "[{$gateway}] ödeme hatası" : 'Ödeme gateway hatası';
 if ($detail) {
 $msg .= ': ' . $detail;
 }
 return new self($msg, ['gateway' => $gateway, 'detail' => $detail]);
 }

 public static function cardDeclined(): self {
 return new self('Kart reddedildi');
 }

 public static function insufficientFunds(): self {
 return new self('Yetersiz bakiye');
 }
}
