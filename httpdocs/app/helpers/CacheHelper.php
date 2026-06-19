<?php
namespace App\Helpers;

/**
 * Cache Helper - Centralized Redis & APCu Cache Management
 * Handles tenant-aware caching with automatic invalidation
 */
class CacheHelper {
    private static $redis = null;
    private static $redisAvailable = null;
    
    /**
     * Get Redis connection
     * @return \Redis|null
     */
    public static function getRedis() {
        if (self::$redis === null && self::isRedisAvailable()) {
            try {
                self::$redis = new \Redis();
                self::$redis->connect(
                    getenv('REDIS_HOST') ?: '127.0.0.1',
                    (int)(getenv('REDIS_PORT') ?: 6379),
                    (float)(getenv('REDIS_TIMEOUT') ?: 2.5)
                );
                
                if (getenv('REDIS_PASSWORD')) {
                    self::$redis->auth(getenv('REDIS_PASSWORD'));
                }
                
                self::$redis->select((int)(getenv('REDIS_DATABASE') ?: 0));
                
                // Test connection
                self::$redis->ping();
            } catch (\Exception $e) {
                error_log("Redis connection failed: " . $e->getMessage());
                self::$redis = null;
                self::$redisAvailable = false;
            }
        }
        return self::$redis;
    }
    
    /**
     * Check if Redis is available
     * @return bool
     */
    public static function isRedisAvailable() {
        if (self::$redisAvailable === null) {
            self::$redisAvailable = class_exists('\Redis') && 
                                    getenv('CACHE_DRIVER') === 'redis';
        }
        return self::$redisAvailable;
    }
    
    /**
     * Set cache with tenant-aware key
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @param int $ttl Time to live in seconds (default: 300 = 5 minutes)
     * @return bool
     */
    public static function set($key, $value, $ttl = 300) {
        $tenantId = \App\Core\TenantContext::getId();
        $fullKey = "tenant_{$tenantId}_{$key}";
        
        $success = false;
        
        // Try Redis first
        if (self::isRedisAvailable()) {
            $redis = self::getRedis();
            if ($redis) {
                try {
                    $success = $redis->setex($fullKey, $ttl, json_encode($value));
                } catch (\Exception $e) {
                    error_log("Redis set failed: " . $e->getMessage());
                }
            }
        }
        
        // Fallback to APCu
        if (!$success && function_exists('apcu_store') && apcu_enabled()) {
            $success = apcu_store($fullKey, $value, $ttl);
        }
        
        return $success;
    }
    
    /**
     * Get cache with tenant-aware key
     * @param string $key Cache key
     * @return mixed|null
     */
    public static function get($key) {
        $tenantId = \App\Core\TenantContext::getId();
        $fullKey = "tenant_{$tenantId}_{$key}";
        
        // Try Redis first
        if (self::isRedisAvailable()) {
            $redis = self::getRedis();
            if ($redis) {
                try {
                    $value = $redis->get($fullKey);
                    if ($value !== false) {
                        return json_decode($value, true);
                    }
                } catch (\Exception $e) {
                    error_log("Redis get failed: " . $e->getMessage());
                }
            }
        }
        
        // Fallback to APCu
        if (function_exists('apcu_fetch') && apcu_enabled()) {
            $value = apcu_fetch($fullKey);
            if ($value !== false) {
                return $value;
            }
        }
        
        return null;
    }
    
    /**
     * Clear cache for specific tenant
     * @param string $tenantId
     * @return int Number of keys deleted
     */
    public static function clearTenantCache($tenantId) {
        $deletedCount = 0;
        $pattern = "tenant_{$tenantId}_*";
        
        // Clear Redis
        if (self::isRedisAvailable()) {
            $redis = self::getRedis();
            if ($redis) {
                try {
                    $keys = $redis->keys($pattern);
                    if (!empty($keys)) {
                        $deletedCount = $redis->del($keys);
                    }
                } catch (\Exception $e) {
                    error_log("Redis clear tenant cache failed: " . $e->getMessage());
                }
            }
        }
        
        // Clear APCu (harder - no pattern matching, clear all)
        if (function_exists('apcu_clear_cache')) {
            apcu_clear_cache();
        }
        
        // CRITICAL: Clear OPcache
        if (function_exists('opcache_reset')) {
            opcache_reset();
        }
        
        // CRITICAL: Clear file stat cache
        clearstatcache(true);
        
        if (class_exists('\App\Core\Logger')) {
            \App\Core\Logger::info('Tenant cache cleared', [
                'tenant_id' => $tenantId,
                'keys_deleted' => $deletedCount,
                'opcache_cleared' => function_exists('opcache_reset')
            ]);
        }
        
        return $deletedCount;
    }
    
    /**
     * Clear menu cache when menu items or categories change
     * @param string $tenantId
     * @return int
     */
    public static function clearMenuCache($tenantId) {
        $deletedCount = 0;
        
        $patterns = [
            "tenant_{$tenantId}_customer_menu_data",
            "tenant_{$tenantId}_waiter_menu_data",
            "tenant_{$tenantId}_categories_tree",
            "tenant_{$tenantId}_menu_items",
            "customer_menu_data_{$tenantId}", // Legacy format
            "menu:items:business:{$tenantId}",
            "menu:items:available:{$tenantId}:tr",
            "menu:items:available:{$tenantId}:en",
            "menu:categories:{$tenantId}:tr",
            "menu:categories:{$tenantId}:en",
            "menu:categories:{$tenantId}:tr:tree",
            "menu:categories:{$tenantId}:en:tree",
            "menu:categories:with_count:{$tenantId}",
            "menu:categories:with_products:{$tenantId}:tr",
            "menu:categories:with_products:{$tenantId}:en",
            "menu:categories:business:{$tenantId}:tr",
            "menu:categories:business:{$tenantId}:en",
        ];
        
        // Clear Redis
        if (self::isRedisAvailable()) {
            $redis = self::getRedis();
            if ($redis) {
                try {
                    foreach ($patterns as $pattern) {
                        if ($redis->del($pattern)) {
                            $deletedCount++;
                        }
                    }
                } catch (\Exception $e) {
                    error_log("Redis clear menu cache failed: " . $e->getMessage());
                }
            }
        }
        
        // Clear APCu
        if (function_exists('apcu_delete')) {
            foreach ($patterns as $pattern) {
                if (apcu_delete($pattern)) {
                    $deletedCount++;
                }
            }
        }
        
        // CRITICAL: Clear OPcache to ensure fresh PHP files
        if (function_exists('opcache_reset')) {
            opcache_reset();
        }
        
        // CRITICAL: Clear file stat cache
        clearstatcache(true);
        
        if (class_exists('\App\Core\Logger')) {
            \App\Core\Logger::info('Menu cache cleared', [
                'tenant_id' => $tenantId,
                'keys_deleted' => $deletedCount,
                'opcache_cleared' => function_exists('opcache_reset'),
                'statcache_cleared' => true
            ]);
        }
        
        return $deletedCount;
    }
    
    /**
     * Clear all cache (use with caution!)
     * @return bool
     */
    public static function clearAll() {
        $success = false;
        
        // Clear Redis
        if (self::isRedisAvailable()) {
            $redis = self::getRedis();
            if ($redis) {
                try {
                    $redis->flushDB();
                    $success = true;
                } catch (\Exception $e) {
                    error_log("Redis flush failed: " . $e->getMessage());
                }
            }
        }
        
        // Clear APCu
        if (function_exists('apcu_clear_cache')) {
            apcu_clear_cache();
            $success = true;
        }
        
        // CRITICAL: Clear OPcache to ensure fresh PHP files
        if (function_exists('opcache_reset')) {
            opcache_reset();
            $success = true;
        }
        
        // CRITICAL: Clear file stat cache
        clearstatcache(true);
        
        if (class_exists('\App\Core\Logger')) {
            \App\Core\Logger::info('All cache cleared', [
                'redis_cleared' => self::isRedisAvailable(),
                'apcu_cleared' => function_exists('apcu_clear_cache'),
                'opcache_cleared' => function_exists('opcache_reset'),
                'statcache_cleared' => true
            ]);
        }
        
        return $success;
    }
    
    /**
     * Get cache statistics
     * @return array
     */
    public static function getStats() {
        $stats = [
            'redis' => [],
            'apcu' => []
        ];
        
        // Redis stats
        if (self::isRedisAvailable()) {
            $redis = self::getRedis();
            if ($redis) {
                try {
                    $info = $redis->info('stats');
                    $stats['redis'] = [
                        'hits' => $info['keyspace_hits'] ?? 0,
                        'misses' => $info['keyspace_misses'] ?? 0,
                        'hit_rate' => isset($info['keyspace_hits'], $info['keyspace_misses']) 
                            ? round($info['keyspace_hits'] / ($info['keyspace_hits'] + $info['keyspace_misses']) * 100, 2)
                            : 0
                    ];
                } catch (\Exception $e) {
                    error_log("Redis stats failed: " . $e->getMessage());
                }
            }
        }
        
        // APCu stats
        if (function_exists('apcu_cache_info')) {
            $info = apcu_cache_info();
            $stats['apcu'] = [
                'hits' => $info['num_hits'] ?? 0,
                'misses' => $info['num_misses'] ?? 0,
                'hit_rate' => isset($info['num_hits'], $info['num_misses']) && ($info['num_hits'] + $info['num_misses']) > 0
                    ? round($info['num_hits'] / ($info['num_hits'] + $info['num_misses']) * 100, 2)
                    : 0
            ];
        }
        
        return $stats;
    }
}
