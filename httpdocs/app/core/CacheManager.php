<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Centralized cache key manager - prevents magic strings.
 * All cache key patterns defined here.
 */
final class CacheManager
{
 public const TTL_SHORT = 60; // 1 min
 public const TTL_MEDIUM = 300; // 5 min
 public const TTL_LONG = 3600; // 1 hour
 public const TTL_DAY = 86400; // 24 hours

 public const PREFIX_ORDER = 'order:';
 public const PREFIX_MENU = 'menu:';
 public const PREFIX_CATEGORY = 'category:';
 public const PREFIX_USER = 'user:';
 public const PREFIX_BUSINESS = 'business:';
 public const PREFIX_ANALYTICS = 'analytics:';
 public const PREFIX_REPORT = 'report:';
 public const PREFIX_STOCK = 'stock:';

 public static function get(string $key, $default = null)
 {
 return Cache::get($key, $default);
 }

 public static function set(string $key, $value, int $ttl = self::TTL_MEDIUM): bool
 {
 return Cache::set($key, $value, $ttl);
 }

 public static function forget(string $key): bool
 {
 return Cache::delete($key);
 }

 public static function forgetPattern(string $pattern): int
 {
 return Cache::deleteByPattern($pattern);
 }

 public static function has(string $key): bool
 {
 return Cache::has($key);
 }

 public static function cacheOrder(string $orderId, array $data, int $ttl = self::TTL_SHORT): bool
 {
 return self::set(self::PREFIX_ORDER . $orderId, $data, $ttl);
 }

 public static function getOrder(string $orderId): ?array
 {
 return self::get(self::PREFIX_ORDER . $orderId);
 }

 public static function invalidateOrders(): int
 {
 return self::forgetPattern(self::PREFIX_ORDER . '*');
 }

 public static function invalidateMenu(): int
 {
 return self::forgetPattern(self::PREFIX_MENU . '*');
 }

 public static function invalidateCategories(): int
 {
 return self::forgetPattern(self::PREFIX_CATEGORY . '*');
 }

 public static function invalidateAnalytics(): int
 {
 return self::forgetPattern(self::PREFIX_ANALYTICS . '*');
 }

 public static function invalidateStock(): int
 {
 return self::forgetPattern(self::PREFIX_STOCK . '*');
 }
}