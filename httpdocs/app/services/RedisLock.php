<?php
namespace App\Services;

/**
 * Redis Distributed Lock Service
 * Prevents race conditions in multi-user scenarios
 * Uses Redis SETNX with expiration for distributed locking
 */
class RedisLock {
    private $redis;
    private $defaultTimeout;
    private $defaultTtl;
    
    public function __construct($redis = null) {
        if ($redis === null) {
            // Get Redis connection from cache service
            $cacheService = \App\Core\DependencyFactory::getCacheService();
            if ($cacheService instanceof RedisCache) {
                $this->redis = $cacheService->getRedis();
            } else {
                throw new \App\Exceptions\ExternalServiceException('Redis cache gerekli', ['service' => 'Redis'], 503);
            }
        } else {
            $this->redis = $redis;
        }
        
        $this->defaultTimeout = 5; // 5 seconds default timeout
        $this->defaultTtl = 30; // 30 seconds default lock TTL
    }
    
    /**
     * Acquire a lock
     * @param string $key Lock key
     * @param int $ttl Time to live in seconds (lock expiration)
     * @param int $timeout Maximum time to wait for lock acquisition
     * @return bool True if lock acquired, false otherwise
     */
    public function acquire(string $key, int $ttl = null, int $timeout = null): bool {
        $ttl = $ttl ?? $this->defaultTtl;
        $timeout = $timeout ?? $this->defaultTimeout;
        $lockKey = $this->getLockKey($key);
        $startTime = microtime(true);
        
        while (true) {
            // Try to acquire lock using SETNX with expiration
            $acquired = $this->redis->set($lockKey, microtime(true), ['nx', 'ex' => $ttl]);
            
            if ($acquired) {
                return true;
            }
            
            // Check if timeout exceeded
            if ((microtime(true) - $startTime) >= $timeout) {
                return false;
            }
            
            // Wait a bit before retrying (exponential backoff)
            usleep(10000); // 10ms
        }
    }
    
    /**
     * Release a lock
     * @param string $key Lock key
     * @return bool True if released, false otherwise
     */
    public function release(string $key): bool {
        $lockKey = $this->getLockKey($key);
        return $this->redis->del($lockKey) > 0;
    }
    
    /**
     * Execute a callback with lock protection
     * Automatically acquires lock, executes callback, and releases lock
     * @param string $key Lock key
     * @param callable $callback Callback to execute
     * @param int $ttl Lock TTL
     * @param int $timeout Lock acquisition timeout
     * @return mixed Callback return value
     * @throws \Exception If lock cannot be acquired or callback throws exception
     */
    public function execute(string $key, callable $callback, int $ttl = null, int $timeout = null) {
        if (!$this->acquire($key, $ttl, $timeout)) {
            throw new \App\Exceptions\ConflictException('Kilit alınamadı', ['key' => $key]);
        }
        
        try {
            $result = $callback();
            return $result;
        } finally {
            $this->release($key);
        }
    }
    
    /**
     * Check if a lock exists
     * @param string $key Lock key
     * @return bool True if locked
     */
    public function isLocked(string $key): bool {
        $lockKey = $this->getLockKey($key);
        return $this->redis->exists($lockKey) > 0;
    }
    
    /**
     * Extend lock expiration
     * @param string $key Lock key
     * @param int $ttl New TTL in seconds
     * @return bool True if extended
     */
    public function extend(string $key, int $ttl): bool {
        $lockKey = $this->getLockKey($key);
        return $this->redis->expire($lockKey, $ttl) > 0;
    }
    
    /**
     * Get lock key with prefix
     * @param string $key Original key
     * @return string Prefixed lock key
     */
    private function getLockKey(string $key): string {
        return "lock:{$key}";
    }
}
