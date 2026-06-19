<?php
namespace App\Services;

/**
 * Efficient Single-File Cache Service
 * Stores all cache entries in a single JSON file to avoid unnecessary directory structure
 * No subdirectories, no multiple files - just one cache.json file
 */
class CacheService {
    private $cacheFile;
    private $defaultTtl;
    private $lockFile;
    private $cleanupProbability = 10; // 1 in 10 chance to run cleanup (10% - optimized for better performance)
    private $maxCacheFileSize = 10 * 1024 * 1024; // 10MB - auto cleanup if exceeded
    private $statsFile; // File to store cache statistics
    private $hitCount = 0;
    private $missCount = 0;

    public function __construct($cacheDir = null, $ttl = 3600) {
        $cacheDir = rtrim($cacheDir ?: __DIR__ . '/../../cache/', '/') . '/';
        $this->cacheFile = $cacheDir . 'cache.json';
        $this->lockFile = $cacheDir . 'cache.lock';
        $this->statsFile = $cacheDir . 'cache_stats.json';
        $this->defaultTtl = $ttl;
        
        // Create cache directory if it doesn't exist (only the root directory)
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }
        
        // Create .gitkeep to preserve directory structure
        $gitkeep = $cacheDir . '.gitkeep';
        if (!file_exists($gitkeep)) {
            file_put_contents($gitkeep, '');
        }
        
        // Load statistics
        $this->loadStats();
    }

    /**
     * Get all cache data from file
     * @return array Cache data array
     */
    private function loadCache(): array {
        if (!file_exists($this->cacheFile)) {
            return [];
        }
        
        // Check file size - if too large, trigger cleanup (only check occasionally to reduce I/O)
        // Only check every 100th call to reduce filesize() overhead
        static $callCount = 0;
        $callCount++;
        if ($callCount % 100 === 0) {
            $fileSize = @filesize($this->cacheFile);
            if ($fileSize !== false && $fileSize > $this->maxCacheFileSize) {
                // File too large - force cleanup
                $this->cleanup(true);
            }
        }
        
        $content = @file_get_contents($this->cacheFile);
        if ($content === false || empty($content)) {
            return [];
        }
        
        $data = @json_decode($content, true);
        return is_array($data) ? $data : [];
    }

    /**
     * Save cache data to file
     * @param array $data Cache data array
     * @return bool Success
     */
    private function saveCache(array $data): bool {
        // Clean expired entries before saving (always clean on save)
        $now = time();
        foreach ($data as $key => $entry) {
            $expiresAt = $entry['expires_at'] ?? ($entry['timestamp'] + ($entry['ttl'] ?? $this->defaultTtl));
            if ($now > $expiresAt) {
                unset($data[$key]);
            }
        }
        
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return @file_put_contents($this->cacheFile, $json, LOCK_EX) !== false;
    }

    /**
     * Acquire file lock for safe concurrent access
     * OPTIMIZED: Uses exponential backoff to reduce contention
     * @param int $timeout Timeout in seconds (reduced from 5 to 2 for faster failure)
     * @return resource|false Lock handle or false
     */
    private function acquireLock(int $timeout = 2) {
        $lock = @fopen($this->lockFile, 'c+');
        if (!$lock) {
            return false;
        }
        
        $startTime = microtime(true);
        $retryCount = 0;
        $maxRetries = 10; // Maximum number of retries
        
        while (!flock($lock, LOCK_EX | LOCK_NB)) {
            // Check timeout (use microtime for more precise timing)
            if ((microtime(true) - $startTime) > $timeout) {
                fclose($lock);
                return false;
            }
            
            // Exponential backoff: 10ms, 20ms, 40ms, 80ms, 160ms, max 200ms
            $backoffMs = min(10 * pow(2, $retryCount), 200);
            usleep($backoffMs * 1000);
            
            $retryCount++;
            if ($retryCount >= $maxRetries) {
                fclose($lock);
                return false;
            }
        }
        
        return $lock;
    }

    /**
     * Release file lock
     * @param resource $lock Lock handle
     */
    private function releaseLock($lock): void {
        if ($lock) {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /**
     * Get cached value
     * OPTIMIZED: Faster lock acquisition with exponential backoff
     * @param string $key Cache key
     * @return mixed Cached value or null if not found/expired
     */
    public function get(string $key) {
        $lock = $this->acquireLock();
        if (!$lock) {
            // Lock acquisition failed - try non-blocking read as fallback
            // This prevents complete cache miss under high contention
            return $this->getWithoutLock($key);
        }
        
        try {
            $cache = $this->loadCache();
            
            if (!isset($cache[$key])) {
                // Clean expired entries before returning null
                $this->cleanupExpiredEntries($cache);
                $this->recordMiss();
                return null;
            }
            
            $entry = $cache[$key];
            $expiresAt = $entry['expires_at'] ?? ($entry['timestamp'] + ($entry['ttl'] ?? $this->defaultTtl));
            
            if (time() > $expiresAt) {
                // Expired - remove it and clean other expired entries
                unset($cache[$key]);
                $this->cleanupExpiredEntries($cache);
                $this->recordMiss();
                return null;
            }
            
            // Check if we should run cleanup (based on probability)
            $this->maybeCleanup($cache);
            
            // Record cache hit
            $this->recordHit();
            
            return $entry['data'] ?? null;
        } finally {
            $this->releaseLock($lock);
        }
    }
    
    /**
     * Get cached value without lock (read-only, for fallback when lock fails)
     * @param string $key Cache key
     * @return mixed Cached value or null
     */
    private function getWithoutLock(string $key) {
        // Try to read cache file without lock (read-only access)
        // This is safe for reads but may return stale data if write is in progress
        if (!file_exists($this->cacheFile)) {
            return null;
        }
        
        try {
            $content = @file_get_contents($this->cacheFile);
            if ($content === false || empty($content)) {
                return null;
            }
            
            $cache = @json_decode($content, true);
            if (!is_array($cache) || !isset($cache[$key])) {
                return null;
            }
            
            $entry = $cache[$key];
            $expiresAt = $entry['expires_at'] ?? ($entry['timestamp'] + ($entry['ttl'] ?? $this->defaultTtl));
            
            if (time() > $expiresAt) {
                return null;
            }
            
            $this->recordHit();
            return $entry['data'] ?? null;
        } catch (\Exception $e) {
            // Silently fail - return null on any error
            return null;
        }
    }

    /**
     * Set cached value
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @param int|null $ttl Time to live in seconds
     * @return bool Success
     */
    public function set(string $key, $value, ?int $ttl = null): bool {
        $lock = $this->acquireLock();
        if (!$lock) {
            return false;
        }
        
        try {
            $cache = $this->loadCache();
            
            // Clean expired entries before adding new one
            $this->cleanupExpiredEntries($cache);
            
            $ttl = $ttl ?? $this->defaultTtl;
            $expiresAt = time() + $ttl;
            
            $cache[$key] = [
                'data' => $value,
                'timestamp' => time(),
                'expires_at' => $expiresAt,
                'ttl' => $ttl
            ];
            
            $result = $this->saveCache($cache);
            
            // Check if we should run full cleanup (based on probability)
            $this->maybeCleanup($cache);
            
            return $result;
        } finally {
            $this->releaseLock($lock);
        }
    }

    /**
     * Delete cached value
     * @param string $key Cache key
     * @return bool Success
     */
    public function delete(string $key): bool {
        $lock = $this->acquireLock();
        if (!$lock) {
            return false;
        }
        
        try {
            $cache = $this->loadCache();
            if (isset($cache[$key])) {
                unset($cache[$key]);
                return $this->saveCache($cache);
            }
            return true;
        } finally {
            $this->releaseLock($lock);
        }
    }
    
    /**
     * Forget/delete cached value (alias for delete)
     * @param string $key Cache key
     * @return bool Success
     */
    public function forget(string $key): bool {
        return $this->delete($key);
    }

    /**
     * Delete multiple cache keys by pattern
     * @param string $pattern Pattern to match (e.g., 'menu:*', 'settings:*')
     * @return int Number of deleted entries
     */
    public function deleteByPattern(string $pattern): int {
        $lock = $this->acquireLock();
        if (!$lock) {
            return 0;
        }
        
        try {
            $cache = $this->loadCache();
            $pattern = str_replace('*', '.*', preg_quote($pattern, '/'));
            $deleted = 0;
            
            foreach ($cache as $key => $entry) {
                if (preg_match('/^' . $pattern . '$/', $key)) {
                    unset($cache[$key]);
                    $deleted++;
                }
            }
            
            if ($deleted > 0) {
                $this->saveCache($cache);
            }
            
            return $deleted;
        } finally {
            $this->releaseLock($lock);
        }
    }

    /**
     * Clear all cache
     * @return bool Success
     */
    public function clear(): bool {
        $lock = $this->acquireLock();
        if (!$lock) {
            return false;
        }
        
        try {
            if (file_exists($this->cacheFile)) {
                $result = @unlink($this->cacheFile);
            } else {
                $result = true;
            }
            
            // CRITICAL: Clear OPcache
            if (function_exists('opcache_reset')) {
                opcache_reset();
            }
            
            // CRITICAL: Clear file stat cache
            clearstatcache(true);
            
            return $result;
        } finally {
            $this->releaseLock($lock);
        }
    }
    
    /**
     * Alias for clear() - flush all cache
     * @return bool Success
     */
    public function flush(): bool {
        return $this->clear();
    }
    
    /**
     * Invalidate cache by clearing OPcache and stat cache
     * @return bool Success
     */
    public function invalidate(): bool {
        $success = false;
        
        // Clear OPcache
        if (function_exists('opcache_reset')) {
            opcache_reset();
            $success = true;
        }
        
        // Clear file stat cache
        clearstatcache(true);
        
        return $success;
    }

    /**
     * Check if key exists and is not expired
     * @param string $key Cache key
     * @return bool
     */
    public function has(string $key): bool {
        return $this->get($key) !== null;
    }

    /**
     * Remember a value in cache, or retrieve it if it exists
     * @param string $key Cache key
     * @param callable $callback Callback function to execute if cache miss
     * @param int|null $ttl Time to live in seconds
     * @return mixed Cached value or result from callback
     */
    public function remember(string $key, callable $callback, ?int $ttl = null) {
        $value = $this->get($key);
        if ($value !== null) {
            return $value;
        }
        
        // Cache miss - execute callback
        $value = $callback();
        
        // Cache the result
        $this->set($key, $value, $ttl);
        
        return $value;
    }

    /**
     * Clean up expired cache entries
     * @param bool $force Force cleanup regardless of probability
     * @param array|null $cache Optional cache array to clean (for internal use)
     * @return array Cleanup statistics
     */
    public function cleanup(bool $force = false, ?array $cache = null): array {
        if (!$force && rand(1, $this->cleanupProbability) !== 1) {
            return ['deleted' => 0, 'errors' => 0, 'total_size_freed' => 0];
        }
        
        $lock = $this->acquireLock();
        if (!$lock) {
            return ['deleted' => 0, 'errors' => 0, 'total_size_freed' => 0];
        }
        
        try {
            $data = $cache ?? $this->loadCache();
            $now = time();
            $deleted = 0;
            $sizeFreed = 0;
            
            foreach ($data as $key => $entry) {
                $expiresAt = $entry['expires_at'] ?? ($entry['timestamp'] + ($entry['ttl'] ?? $this->defaultTtl));
                if ($now > $expiresAt) {
                    $sizeFreed += strlen(json_encode($entry));
                    unset($data[$key]);
                    $deleted++;
                }
            }
            
            if ($deleted > 0) {
                $this->saveCache($data);
            }
            
            return [
                'deleted' => $deleted,
                'errors' => 0,
                'total_size_freed' => $sizeFreed
            ];
        } finally {
            $this->releaseLock($lock);
        }
    }

    /**
     * Clean expired entries from cache array (lightweight cleanup)
     * @param array $cache Cache array (passed by reference)
     */
    private function cleanupExpiredEntries(array &$cache): void {
        $now = time();
        $cleaned = false;
        
        foreach ($cache as $key => $entry) {
            $expiresAt = $entry['expires_at'] ?? ($entry['timestamp'] + ($entry['ttl'] ?? $this->defaultTtl));
            if ($now > $expiresAt) {
                unset($cache[$key]);
                $cleaned = true;
            }
        }
        
        // Save if any entries were cleaned
        if ($cleaned) {
            $this->saveCache($cache);
        }
    }
    
    /**
     * Maybe run cleanup based on probability
     * @param array $cache Cache array (for internal use)
     */
    private function maybeCleanup(?array $cache = null): void {
        if (rand(1, $this->cleanupProbability) === 1) {
            $this->cleanup(true, $cache);
        }
    }

    /**
     * Get cache statistics
     * @return array Cache stats
     */
    public function getStats(): array {
        $lock = $this->acquireLock();
        if (!$lock) {
            return [
                'count' => 0,
                'expired_count' => 0,
                'total_size' => 0,
                'expired_size' => 0,
                'size_formatted' => '0 B',
                'expired_size_formatted' => '0 B'
            ];
        }
        
        try {
            $cache = $this->loadCache();
            $now = time();
            $stats = [
                'count' => 0,
                'expired_count' => 0,
                'total_size' => 0,
                'expired_size' => 0
            ];
            
            foreach ($cache as $key => $entry) {
                $entrySize = strlen(json_encode($entry));
                $stats['total_size'] += $entrySize;
                $stats['count']++;
                
                $expiresAt = $entry['expires_at'] ?? ($entry['timestamp'] + ($entry['ttl'] ?? $this->defaultTtl));
                if ($now > $expiresAt) {
                    $stats['expired_count']++;
                    $stats['expired_size'] += $entrySize;
                }
            }
            
            $stats['size_formatted'] = $this->formatBytes($stats['total_size']);
            $stats['expired_size_formatted'] = $this->formatBytes($stats['expired_size']);
            
            // Add hit/miss statistics (include in-memory counters)
            $allStats = $this->loadStats();
            $stats['hits'] = ($allStats['hits'] ?? 0) + $this->hitCount;
            $stats['misses'] = ($allStats['misses'] ?? 0) + $this->missCount;
            $totalRequests = $stats['hits'] + $stats['misses'];
            $stats['hit_rate'] = $totalRequests > 0 ? round(($stats['hits'] / $totalRequests) * 100, 2) : 0;
            $stats['miss_rate'] = $totalRequests > 0 ? round(($stats['misses'] / $totalRequests) * 100, 2) : 0;
            
            // Add file size information
            if (file_exists($this->cacheFile)) {
                $stats['file_size'] = filesize($this->cacheFile);
                $stats['file_size_formatted'] = $this->formatBytes($stats['file_size']);
            } else {
                $stats['file_size'] = 0;
                $stats['file_size_formatted'] = '0 B';
            }
            
            return $stats;
        } finally {
            $this->releaseLock($lock);
        }
    }

    /**
     * Format bytes to human readable format
     * @param int $bytes Size in bytes
     * @param int $precision Decimal precision
     * @return string Formatted size
     */
    private function formatBytes(int $bytes, int $precision = 2): string {
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }

    /**
     * Set cleanup probability
     * @param int $probability 1 in N chance to run cleanup (default: 10)
     */
    public function setCleanupProbability(int $probability): void {
        $this->cleanupProbability = max(1, $probability);
    }
    
    /**
     * Load cache statistics from file
     * @return array Statistics array
     */
    private function loadStats(): array {
        if (!file_exists($this->statsFile)) {
            return ['hits' => 0, 'misses' => 0, 'last_reset' => time()];
        }
        
        $content = @file_get_contents($this->statsFile);
        if ($content === false) {
            return ['hits' => 0, 'misses' => 0, 'last_reset' => time()];
        }
        
        $data = @json_decode($content, true);
        return is_array($data) ? $data : ['hits' => 0, 'misses' => 0, 'last_reset' => time()];
    }
    
    /**
     * Save cache statistics to file
     * @param array $stats Statistics array
     */
    private function saveStats(array $stats): void {
        @file_put_contents($this->statsFile, json_encode($stats, JSON_PRETTY_PRINT), LOCK_EX);
    }
    
    /**
     * Record a cache hit
     */
    private function recordHit(): void {
        // Increment in-memory counter
        $this->hitCount++;
        
        // Save stats every 50 hits to reduce I/O
        if ($this->hitCount >= 50) {
            $stats = $this->loadStats();
            $stats['hits'] = ($stats['hits'] ?? 0) + $this->hitCount;
            $this->saveStats($stats);
            $this->hitCount = 0;
        }
    }
    
    /**
     * Record a cache miss
     */
    private function recordMiss(): void {
        // Increment in-memory counter
        $this->missCount++;
        
        // Save stats every 50 misses to reduce I/O
        if ($this->missCount >= 50) {
            $stats = $this->loadStats();
            $stats['misses'] = ($stats['misses'] ?? 0) + $this->missCount;
            $this->saveStats($stats);
            $this->missCount = 0;
        }
    }
    
    /**
     * Reset cache statistics
     */
    public function resetStats(): void {
        $this->saveStats(['hits' => 0, 'misses' => 0, 'last_reset' => time()]);
        $this->hitCount = 0;
        $this->missCount = 0;
    }

    /**
     * Migrate old cache files from subdirectory structure to single file
     * This method cleans up old cache files that were stored in subdirectories
     * @return array Migration statistics
     */
    public function migrateOldCacheFiles(): array {
        $stats = [
            'migrated' => 0,
            'deleted' => 0,
            'errors' => 0,
            'total_size_freed' => 0
        ];
        
        $cacheDir = dirname($this->cacheFile);
        
        // Find all .cache files in subdirectories
        $files = glob($cacheDir . '/*/*.cache');
        if (!$files) {
            return $stats;
        }
        
        $lock = $this->acquireLock();
        if (!$lock) {
            return $stats;
        }
        
        try {
            $cache = $this->loadCache();
            $now = time();
            
            foreach ($files as $file) {
                if (!is_file($file)) {
                    continue;
                }
                
                $fileSize = filesize($file);
                $content = @file_get_contents($file);
                if ($content === false) {
                    continue;
                }
                
                $cacheData = @json_decode($content, true);
                if ($cacheData === null) {
                    // Corrupted file - delete it
                    @unlink($file);
                    $stats['deleted']++;
                    $stats['total_size_freed'] += $fileSize;
                    continue;
                }
                
                // Extract key from cache data
                $key = $cacheData['key'] ?? basename($file, '.cache');
                
                // Check if expired
                $expiresAt = $cacheData['expires_at'] ?? ($cacheData['timestamp'] + ($cacheData['ttl'] ?? $this->defaultTtl));
                
                if ($now > $expiresAt) {
                    // Expired - delete it
                    @unlink($file);
                    $stats['deleted']++;
                    $stats['total_size_freed'] += $fileSize;
                } else {
                    // Still valid - migrate to new structure
                    $data = $cacheData['data'] ?? null;
                    if ($data !== null) {
                        $cache[$key] = [
                            'data' => $data,
                            'timestamp' => $cacheData['timestamp'] ?? time(),
                            'expires_at' => $expiresAt,
                            'ttl' => $cacheData['ttl'] ?? $this->defaultTtl
                        ];
                        @unlink($file);
                        $stats['migrated']++;
                    } else {
                        @unlink($file);
                        $stats['deleted']++;
                        $stats['total_size_freed'] += $fileSize;
                    }
                }
            }
            
            // Save migrated cache
            if ($stats['migrated'] > 0) {
                $this->saveCache($cache);
            }
            
            // Clean up empty subdirectories
            $subdirs = glob($cacheDir . '/*', GLOB_ONLYDIR);
            foreach ($subdirs as $subdir) {
                if (is_dir($subdir) && count(glob($subdir . '/*')) === 0) {
                    @rmdir($subdir);
                }
            }
            
            return $stats;
        } finally {
            $this->releaseLock($lock);
        }
    }
}
