<?php
namespace App\Services;

use App\Interfaces\CacheInterface;

/**
 * Redis Cache Implementation
 * Stores cache data in Redis
 */
class RedisCache implements CacheInterface {
    private $redis;
    private $defaultTtl;
    
    public function __construct(array $config = []) {
        $this->redis = new \Redis();
        $host = $config['host'] ?? '127.0.0.1';
        $port = $config['port'] ?? 6379;
        $password = $config['password'] ?? null;
        $database = $config['database'] ?? 0;
        $timeout = $config['timeout'] ?? 2.5;
        
        if (!$this->redis->connect($host, $port, $timeout)) {
            throw new \Exception("Failed to connect to Redis at {$host}:{$port}");
        }
        
        if ($password !== null) {
            if (!$this->redis->auth($password)) {
                throw new \Exception("Failed to authenticate with Redis");
            }
        }
        
        $this->redis->select($database);
        $this->defaultTtl = $config['ttl'] ?? 3600;
    }
    
    /**
     * Get value from cache
     * @param string $key Cache key
     * @param mixed $default Default value if key not found
     * @return mixed Cached value or default
     */
    public function get(string $key, $default = null) {
        $value = $this->redis->get($key);
        
        if ($value === false) {
            return $default;
        }
        
        return json_decode($value, true);
    }
    
    /**
     * Set value in cache
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @param int|null $ttl Time to live in seconds (null for default)
     * @return bool Success
     */
    public function set(string $key, $value, ?int $ttl = null): bool {
        $ttl = $ttl ?? $this->defaultTtl;
        $serialized = json_encode($value);
        
        if ($ttl > 0) {
            return $this->redis->setex($key, $ttl, $serialized);
        } else {
            return $this->redis->set($key, $serialized);
        }
    }
    
    /**
     * Delete value from cache
     * @param string $key Cache key
     * @return bool Success
     */
    public function delete(string $key): bool {
        return $this->redis->del($key) > 0;
    }
    
    /**
     * Forget/delete value from cache (alias for delete)
     * @param string $key Cache key
     * @return bool Success
     */
    public function forget(string $key): bool {
        return $this->delete($key);
    }
    
    /**
     * Clear all cache
     * @return bool Success
     */
    public function clear(): bool {
        return $this->redis->flushDB();
    }
    
    /**
     * Check if key exists in cache
     * @param string $key Cache key
     * @return bool True if exists
     */
    public function has(string $key): bool {
        return $this->redis->exists($key) > 0;
    }
    
    /**
     * Get multiple values from cache
     * @param array $keys Array of cache keys
     * @return array Associative array of key => value
     */
    public function getMultiple(array $keys): array {
        if (empty($keys)) {
            return [];
        }
        
        $values = $this->redis->mget($keys);
        $results = [];
        
        foreach ($keys as $index => $key) {
            $value = $values[$index] ?? false;
            if ($value !== false) {
                $results[$key] = json_decode($value, true);
            }
        }
        
        return $results;
    }
    
    /**
     * Set multiple values in cache
     * @param array $values Associative array of key => value
     * @param int|null $ttl Time to live in seconds (null for default)
     * @return bool Success
     */
    public function setMultiple(array $values, ?int $ttl = null): bool {
        $ttl = $ttl ?? $this->defaultTtl;
        $success = true;
        
        foreach ($values as $key => $value) {
            if (!$this->set($key, $value, $ttl)) {
                $success = false;
            }
        }
        
        return $success;
    }
    
    /**
     * Delete multiple values from cache
     * @param array $keys Array of cache keys
     * @return bool Success
     */
    public function deleteMultiple(array $keys): bool {
        if (empty($keys)) {
            return true;
        }
        
        return $this->redis->del($keys) > 0;
    }
    
    /**
     * Remember - Get from cache or execute callback and cache result
     * @param string $key Cache key
     * @param callable $callback Callback to execute if cache miss
     * @param int|null $ttl Time to live in seconds
     * @return mixed Cached value or callback result
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
     * Remember with lock protection (prevents cache stampede)
     * @param string $key Cache key
     * @param callable $callback Callback to execute if cache miss
     * @param int|null $ttl Time to live in seconds
     * @param array $tags Tags for cache invalidation
     * @return mixed Cached value or callback result
     */
    public function rememberWithLock(string $key, callable $callback, ?int $ttl = null, array $tags = []) {
        $value = $this->get($key);
        
        if ($value !== null) {
            return $value;
        }
        
        // Use lock to prevent cache stampede (multiple requests hitting DB simultaneously)
        try {
            require_once __DIR__ . '/RedisLock.php';
            $lock = new RedisLock($this->redis);
            
            return $lock->execute("cache:{$key}", function() use ($key, $callback, $ttl, $tags) {
                // Double-check cache after acquiring lock
                $value = $this->get($key);
                if ($value !== null) {
                    return $value;
                }
                
                // Execute callback
                $value = $callback();
                
                // Cache the result
                $this->set($key, $value, $ttl);
                
                // Store tags for invalidation
                if (!empty($tags)) {
                    $this->addTags($key, $tags);
                }
                
                return $value;
            }, 30, 5); // 30s lock TTL, 5s timeout
        } catch (\Exception $e) {
            // Lock failed - fallback to direct execution (better than nothing)
            \App\Core\Logger::warning("RedisLock failed, falling back to direct cache: " . $e->getMessage());
            $value = $callback();
            $this->set($key, $value, $ttl);
            if (!empty($tags)) {
                $this->addTags($key, $tags);
            }
            return $value;
        }
    }
    
    /**
     * Add tags to a cache key for tag-based invalidation
     * @param string $key Cache key
     * @param array $tags Tags
     */
    private function addTags(string $key, array $tags): void {
        foreach ($tags as $tag) {
            $tagKey = "tag:{$tag}";
            $this->redis->sAdd($tagKey, $key);
            // Set expiration on tag set (longer than cache TTL)
            $this->redis->expire($tagKey, 86400); // 24 hours
        }
    }
    
    /**
     * Invalidate cache by tags
     * @param array $tags Tags to invalidate
     * @return int Number of keys deleted
     */
    public function invalidateByTags(array $tags): int {
        $deleted = 0;
        
        foreach ($tags as $tag) {
            $tagKey = "tag:{$tag}";
            $keys = $this->redis->sMembers($tagKey);
            
            if (!empty($keys)) {
                $deleted += $this->redis->del($keys);
                // Delete tag set
                $this->redis->del($tagKey);
            }
        }
        
        return $deleted;
    }
    
    /**
     * Delete cache by pattern (e.g., 'menu:*')
     * @param string $pattern Pattern to match
     * @return int Number of deleted keys
     */
    public function deleteByPattern(string $pattern): int {
        $keys = $this->redis->keys($pattern);
        
        if (empty($keys)) {
            return 0;
        }
        
        return $this->redis->del($keys);
    }
    
    /**
     * Invalidate cache by pattern (alias for deleteByPattern)
     * @param string $pattern Pattern to match
     * @return int Number of keys deleted
     */
    public function invalidate(string $pattern): int {
        return $this->deleteByPattern($pattern);
    }
    
    /**
     * Get cache statistics
     * @return array Cache stats
     */
    public function getStats(): array {
        $info = $this->redis->info();
        
        return [
            'driver' => 'redis',
            'keys' => $this->redis->dbSize(),
            'used_memory' => $info['used_memory'] ?? 0,
            'used_memory_human' => $info['used_memory_human'] ?? '0B',
            'used_memory_peak' => $info['used_memory_peak'] ?? 0,
            'used_memory_peak_human' => $info['used_memory_peak_human'] ?? '0B',
            'keyspace_hits' => $info['keyspace_hits'] ?? 0,
            'keyspace_misses' => $info['keyspace_misses'] ?? 0,
            'total_commands_processed' => $info['total_commands_processed'] ?? 0,
            'connected_clients' => $info['connected_clients'] ?? 0,
        ];
    }
    
    /**
     * Get Redis connection
     * @return \Redis Redis connection
     */
    public function getRedis(): \Redis {
        return $this->redis;
    }
    
    /**
     * Destructor - close Redis connection
     */
    public function __destruct() {
        if ($this->redis) {
            try {
                $this->redis->close();
            } catch (\Exception $e) {
                // Ignore errors on close
            }
        }
    }
}

