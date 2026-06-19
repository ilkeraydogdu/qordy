<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Order status constants - prevents magic strings across codebase.
 * Use these instead of hardcoded status strings.
 */
final class OrderStatus
{
 // Active statuses (order is being processed)
 public const PENDING = 'PENDING';
 public const PREPARING = 'PREPARING';
 public const READY = 'READY';
 public const SERVED = 'SERVED';
 public const IN_PROGRESS = 'IN_PROGRESS';

 // Completion statuses
 public const COMPLETED = 'COMPLETED';
 public const DELIVERED = 'DELIVERED';
 public const PAID = 'PAID';

 // Cancellation statuses
 public const CANCELLED = 'CANCELLED';
 public const REJECTED = 'REJECTED';
 public const REFUNDED = 'REFUNDED';

 // Payment statuses
 public const PENDING_PAYMENT = 'PENDING_PAYMENT';
 public const PAYMENT_FAILED = 'PAYMENT_FAILED';
 public const PAYMENT_PROCESSING = 'PAYMENT_PROCESSING';

 // Delivery statuses
 public const OUT_FOR_DELIVERY = 'OUT_FOR_DELIVERY';
 public const DELIVERY_PENDING = 'DELIVERY_PENDING';

 /**
 * All valid statuses
 */
 public const ALL = [
 self::PENDING,
 self::PREPARING,
 self::READY,
 self::SERVED,
 self::IN_PROGRESS,
 self::COMPLETED,
 self::DELIVERED,
 self::PAID,
 self::CANCELLED,
 self::REJECTED,
 self::REFUNDED,
 self::PENDING_PAYMENT,
 self::PAYMENT_FAILED,
 self::PAYMENT_PROCESSING,
 self::OUT_FOR_DELIVERY,
 self::DELIVERY_PENDING,
 ];

 /**
 * Active statuses (order is being processed)
 */
 public const ACTIVE = [
 self::PENDING,
 self::PREPARING,
 self::READY,
 self::SERVED,
 self::IN_PROGRESS,
 ];

 /**
 * Inactive/final statuses
 */
 public const COMPLETED_STATUSES = [
 self::COMPLETED,
 self::DELIVERED,
 self::PAID,
 self::CANCELLED,
 self::REJECTED,
 self::REFUNDED,
 self::PAYMENT_FAILED,
 ];

 /**
 * Payment-related statuses
 */
 public const PAYMENT_STATUSES = [
 self::PENDING_PAYMENT,
 self::PAYMENT_PROCESSING,
 self::PAYMENT_FAILED,
 self::PAID,
 ];

 /**
 * Validate status
 */
 public static function isValid(string $status): bool
 {
 return in_array($status, self::ALL, true);
 }

 /**
 * Check if status is active (order being processed)
 */
 public static function isActive(string $status): bool
 {
 return in_array($status, self::ACTIVE, true);
 }

 /**
 * Check if status is completed/final
 */
 public static function isCompleted(string $status): bool
 {
 return in_array($status, self::COMPLETED_STATUSES, true);
 }

 /**
 * Check if status requires payment
 */
 public static function requiresPayment(string $status): bool
 {
 return $status === self::PENDING_PAYMENT;
 }

 /**
 * Get display label for status
 */
 public static function label(string $status): string
 {
 return match ($status) {
 self::PENDING => 'Bekliyor',
 self::PREPARING => 'Hazırlanıyor',
 self::READY => 'Hazır',
 self::SERVED => 'Servis Edildi',
 self::IN_PROGRESS => 'İşleniyor',
 self::COMPLETED => 'Tamamlandı',
 self::DELIVERED => 'Teslim Edildi',
 self::PAID => 'Ödendi',
 self::CANCELLED => 'İptal Edildi',
 self::REJECTED => 'Reddedildi',
 self::REFUNDED => 'İade Edildi',
 self::PENDING_PAYMENT => 'Ödeme Bekliyor',
 self::PAYMENT_FAILED => 'Ödeme Başarısız',
 self::PAYMENT_PROCESSING => 'Ödeme İşleniyor',
 self::OUT_FOR_DELIVERY => 'Yolda',
 self::DELIVERY_PENDING => 'Teslimat Bekliyor',
 default => $status,
 };
 }

 /**
 * Get color for status (for UI)
 */
 public static function color(string $status): string
 {
 return match ($status) {
 self::PENDING => 'yellow',
 self::PREPARING => 'blue',
 self::READY => 'green',
 self::SERVED => 'gray',
 self::IN_PROGRESS => 'blue',
 self::COMPLETED, self::DELIVERED, self::PAID => 'green',
 self::CANCELLED, self::REJECTED, self::REFUNDED => 'red',
 self::PENDING_PAYMENT, self::PAYMENT_PROCESSING => 'yellow',
 self::PAYMENT_FAILED => 'red',
 self::OUT_FOR_DELIVERY => 'purple',
 self::DELIVERY_PENDING => 'orange',
 default => 'gray',
 };
 }
}