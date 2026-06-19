<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Cache;

/**
 * Rate Limiter Service
 * Implements sliding window rate limiting using cache
 */
final class RateLimiter
{
 private const DEFAULT_LIMIT = 60; // requests
 private const DEFAULT_WINDOW = 60; // seconds

 /**
 * Check if request is allowed
 *
 * @param string $key Identifier (e.g., IP, user_id)
 * @param int $limit Max requests
 * @param int $window Window in seconds
 * @return array{allowed: bool, remaining: int, reset_at: int, retry_after: int}
 */
 public static function check(string $key, int $limit = self::DEFAULT_LIMIT, int $window = self::DEFAULT_WINDOW): array
 {
 $cacheKey = "ratelimit:{$key}";
 $now = time();

 $data = Cache::get($cacheKey);
 if ($data === null) {
 $data = ['count' => 0, 'reset_at' => $now + $window];
 }

 // Reset if window expired
 if ($now >= $data['reset_at']) {
 $data = ['count' => 0, 'reset_at' => $now + $window];
 }

 $data['count']++;
 $allowed = $data['count'] <= $limit;

 if ($allowed) {
 // Extend TTL to window
 Cache::set($cacheKey, $data, $window);
 }

 return [
 'allowed' => $allowed,
 'remaining' => max(0, $limit - $data['count']),
 'reset_at' => $data['reset_at'],
 'retry_after' => $allowed ? 0 : ($data['reset_at'] - $now)
 ];
 }

 /**
 * IP-based rate limit
 */
 public static function checkIp(string $ip, int $limit = 100, int $window = 60): array
 {
 return self::check("ip:{$ip}", $limit, $window);
 }

 /**
 * User-based rate limit
 */
 public static function checkUser(string $userId, int $limit = 200, int $window = 60): array
 {
 return self::check("user:{$userId}", $limit, $window);
 }

 /**
 * Endpoint-specific rate limit (e.g., login)
 */
 public static function checkEndpoint(string $endpoint, string $identifier, int $limit, int $window): array
 {
 return self::check("endpoint:{$endpoint}:{$identifier}", $limit, $window);
 }

 /**
 * Reset rate limit for a key
 */
 public static function reset(string $key): bool
 {
 return Cache::delete("ratelimit:{$key}");
 }

 /**
 * Get current usage
 */
 public static function getUsage(string $key): array
 {
 $cacheKey = "ratelimit:{$key}";
 $data = Cache::get($cacheKey);
 if ($data === null) {
 return ['count' => 0, 'reset_at' => 0];
 }
 return $data;
 }
}