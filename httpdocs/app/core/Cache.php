<?php
namespace App\Core;

/**
 * Simple File-based Cache System
 * Provides caching functionality for frequently accessed data
 */
class Cache {
    private static $cacheDir = __DIR__ . '/../../storage/cache';
    private static $defaultTTL = 3600; // 1 hour
    
    /**
     * Initialize cache directory
     */
    private static function init(): void {
        if (!is_dir(self::$cacheDir)) {
            mkdir(self::$cacheDir, 0755, true);
        }
    }
    
    /**
     * Get cache file path
     * @param string $key
     * @return string
     */
    private static function getCachePath(string $key): string {
        self::init();
        $safeKey = preg_replace('/[^a-zA-Z0-9_-]/', '_', $key);
        return self::$cacheDir . '/' . $safeKey . '.cache';
    }
    
    /**
     * Get cached value
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function get(string $key, $default = null) {
        $cachePath = self::getCachePath($key);
        
        if (!file_exists($cachePath)) {
            return $default;
        }
        
        $data = json_decode(file_get_contents($cachePath), true);
        if (!$data) {
            return $default;
        }
        
        // Check if cache is expired
        if (isset($data['expires_at']) && time() > $data['expires_at']) {
            unlink($cachePath);
            return $default;
        }
        
        return $data['value'] ?? $default;
    }
    
    /**
     * Set cache value
     * @param string $key
     * @param mixed $value
     * @param int $ttl Time to live in seconds
     * @return bool
     */
    public static function set(string $key, $value, int $ttl = null): bool {
        self::init();
        
        $ttl = $ttl ?? self::$defaultTTL;
        $cachePath = self::getCachePath($key);
        
        $data = [
            'value' => $value,
            'created_at' => time(),
            'expires_at' => time() + $ttl
        ];
        
        return file_put_contents($cachePath, json_encode($data)) !== false;
    }
    
    /**
     * Check if cache key exists and is valid
     * @param string $key
     * @return bool
     */
    public static function has(string $key): bool {
        $cachePath = self::getCachePath($key);
        
        if (!file_exists($cachePath)) {
            return false;
        }
        
        $data = json_decode(file_get_contents($cachePath), true);
        if (!$data) {
            return false;
        }
        
        // Check if expired
        if (isset($data['expires_at']) && time() > $data['expires_at']) {
            unlink($cachePath);
            return false;
        }
        
        return true;
    }
    
    /**
     * Delete cache entry
     * @param string $key
     * @return bool
     */
    public static function delete(string $key): bool {
        $cachePath = self::getCachePath($key);
        
        if (file_exists($cachePath)) {
            return unlink($cachePath);
        }
        
        return true;
    }
    
    /**
     * Clear all cache
     * @param string $pattern Optional pattern to match keys
     * @return int Number of files deleted
     */
 /**
 * Delete all cache entries matching a pattern.
 * Alias for clear() with clearer naming.
 *
 * @param string $pattern Glob pattern (e.g., 'order:*', 'menu:*', 'analytics:*')
 * @return int Number of entries deleted
 */
 public static function deleteByPattern(string $pattern): int {
 return self::clear($pattern);
 }

    public static function clear(string $pattern = '*'): int {
        self::init();
        
        $deleted = 0;
        $files = glob(self::$cacheDir . '/' . $pattern . '.cache');
        
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
                $deleted++;
            }
        }
        
        return $deleted;
    }
    
    /**
     * Remember - Get from cache or execute callback and cache result
     * @param string $key
     * @param callable $callback
     * @param int $ttl
     * @return mixed
     */
    public static function remember(string $key, callable $callback, int $ttl = null) {
        if (self::has($key)) {
            return self::get($key);
        }
        
        $value = $callback();
        self::set($key, $value, $ttl);
        
        return $value;
    }
    
    /**
     * Invalidate cache by pattern
     * @param string $pattern
     * @return int
     */
    public static function invalidate(string $pattern): int {
        return self::clear($pattern);
    }
    
    /**
     * Get cache statistics
     * @return array
     */
    public static function getStats(): array {
        self::init();
        
        $files = glob(self::$cacheDir . '/*.cache');
        $totalSize = 0;
        $expired = 0;
        $valid = 0;
        
        foreach ($files as $file) {
            $totalSize += filesize($file);
            $data = json_decode(file_get_contents($file), true);
            if ($data && isset($data['expires_at'])) {
                if (time() > $data['expires_at']) {
                    $expired++;
                } else {
                    $valid++;
                }
            }
        }
        
        return [
            'total_files' => count($files),
            'valid_entries' => $valid,
            'expired_entries' => $expired,
            'total_size' => $totalSize,
            'total_size_formatted' => self::formatBytes($totalSize)
        ];
    }
    
    /**
     * Format bytes to human readable format
     * @param int $bytes
     * @return string
     */
    private static function formatBytes(int $bytes): string {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, 2) . ' ' . $units[$pow];
    }
}

