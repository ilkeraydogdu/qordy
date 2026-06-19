<?php
namespace App\Core;

class RateLimiter {
    private $storagePath;
    private $limits;
    private $redis = null;
    private $useRedis = false;
    private $redisConfig = null;
    
    public function __construct(array $limits = []) {
        $this->storagePath = __DIR__ . '/../storage/rate_limits/';
        if (!is_dir($this->storagePath)) {
            mkdir($this->storagePath, 0755, true);
        }
        
        // Default limits (will be overridden by config)
        $defaultLimits = [
            'default' => ['requests' => 60, 'period' => 60],
            'api' => ['requests' => 100, 'period' => 60],
            'login' => ['requests' => 50, 'period' => 60],  // Increased for multiple users
            'upload' => ['requests' => 10, 'period' => 60]
        ];
        
        // Merge with provided limits (from config)
        $this->limits = array_merge($defaultLimits, $limits);
        
        // Normalize limits structure
        foreach ($this->limits as $type => &$limit) {
            if (!isset($limit['requests'])) {
                $limit['requests'] = $defaultLimits['default']['requests'];
            }
            if (!isset($limit['period'])) {
                $limit['period'] = $defaultLimits['default']['period'];
            }
        }
        
        // Try to initialize Redis connection
        $this->initializeRedis();
    }
    
    /**
     * Initialize Redis connection for rate limiting
     * CRITICAL: Redis is required in production for proper rate limiting
     */
    private function initializeRedis(): void {
        $appEnv = isset($_ENV['APP_ENV']) ? $_ENV['APP_ENV'] : 'development';
        
        // Check if Redis extension is available
        if (!extension_loaded('redis')) {
            // Log warning but don't throw exception - allow site to work without Redis
            error_log('WARNING: Redis extension is not loaded. Rate limiting disabled, using file-based fallback.');
            return;
        }
        
        try {
            // Load cache config to get Redis settings
            $cacheConfig = require __DIR__ . '/../config/cache.php';
            
            // Only use Redis if cache driver is Redis
            if ($cacheConfig['driver'] !== 'redis') {
                // In production, log warning but don't throw exception for landing pages
                // Rate limiting will be disabled but site will still work
                if ($appEnv === 'production') {
                    error_log('WARNING: Redis cache driver is not configured. Rate limiting is disabled. Configure cache.php to use Redis for production rate limiting.');
                }
                return;
            }
            
            // Use Redis database 2 for rate limiting (separate from cache and sessions)
            $this->redisConfig = [
                'host' => $cacheConfig['redis']['host'],
                'port' => $cacheConfig['redis']['port'],
                'password' => $cacheConfig['redis']['password'],
                'database' => isset($_ENV['REDIS_RATELIMIT_DATABASE']) ? (int)$_ENV['REDIS_RATELIMIT_DATABASE'] : 2,
                'timeout' => $cacheConfig['redis']['timeout'],
            ];
            
            $this->redis = new \Redis();
            if (!$this->redis->connect($this->redisConfig['host'], $this->redisConfig['port'], $this->redisConfig['timeout'])) {
                // Log warning but don't throw exception - allow site to work without Redis
                error_log('WARNING: Failed to connect to Redis for rate limiting. Rate limiting disabled, using file-based fallback.');
                $this->redis = null;
                return;
            }
            
            if ($this->redisConfig['password'] !== null) {
                if (!$this->redis->auth($this->redisConfig['password'])) {
                    // Log warning but don't throw exception - allow site to work without Redis
                    error_log('WARNING: Redis authentication failed. Rate limiting disabled, using file-based fallback.');
                    $this->redis = null;
                    return;
                }
            }
            
            $this->redis->select($this->redisConfig['database']);
            $this->useRedis = true;
        } catch (\Exception $e) {
            // Redis connection failed
            // Log warning but don't throw exception - allow site to work without Redis
            // Rate limiting will be disabled but site will still function
            error_log('WARNING: Failed to initialize Redis for rate limiting: ' . $e->getMessage() . ' - Rate limiting disabled, using file-based fallback.');
            $this->redis = null;
            $this->useRedis = false;
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::warning("Failed to initialize Redis for rate limiting: " . $e->getMessage());
            }
        }
    }
    
    public function check(string $identifier, string $type = 'default'): bool {
        if ($this->useRedis && $this->redis !== null) {
            return $this->checkRedis($identifier, $type);
        }
        
        return $this->checkFile($identifier, $type);
    }
    
    /**
     * Check rate limit using Redis (atomic operations)
     */
    private function checkRedis(string $identifier, string $type = 'default'): bool {
        try {
            $limit = $this->limits[$type] ?? $this->limits['default'];
            $key = $this->getRedisKey($identifier, $type);
            
            // Use atomic INCR to increment counter
            $current = $this->redis->incr($key);
            
            // Check TTL - if key doesn't exist or expired, reset it
            $ttl = $this->redis->ttl($key);
            if ($ttl === -1 || $ttl === -2) {
                // Key has no expiration or doesn't exist, set expiration
                $this->redis->expire($key, $limit['period']);
            }
            
            // If this is the first request (current == 1), ensure expiration is set
            if ($current === 1) {
                $this->redis->expire($key, $limit['period']);
            }
            
            // Check if limit exceeded
            if ($current > $limit['requests']) {
                return false;
            }
            
            return true;
        } catch (\Exception $e) {
            // Redis operation failed, fallback to file-based
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::warning("Redis rate limit check failed, falling back to file-based: " . $e->getMessage());
            }
            $this->useRedis = false;
            return $this->checkFile($identifier, $type);
        }
    }
    
    /**
     * Check rate limit using file-based storage (fallback)
     */
    private function checkFile(string $identifier, string $type = 'default'): bool {
        $limit = $this->limits[$type] ?? $this->limits['default'];
        $key = $this->getKey($identifier, $type);
        $file = $this->storagePath . md5($key) . '.json';
        
        $data = $this->readData($file);
        $now = time();
        
        if (!isset($data['requests']) || !isset($data['reset_time'])) {
            $data = [
                'requests' => 1,
                'reset_time' => $now + $limit['period']
            ];
            $this->writeData($file, $data);
            return true;
        }
        
        if ($now > $data['reset_time']) {
            $data = [
                'requests' => 1,
                'reset_time' => $now + $limit['period']
            ];
            $this->writeData($file, $data);
            return true;
        }
        
        if ($data['requests'] >= $limit['requests']) {
            return false;
        }
        
        $data['requests']++;
        $this->writeData($file, $data);
        
        return true;
    }
    
    public function getRemaining(string $identifier, string $type = 'default'): int {
        if ($this->useRedis && $this->redis !== null) {
            return $this->getRemainingRedis($identifier, $type);
        }
        
        return $this->getRemainingFile($identifier, $type);
    }
    
    /**
     * Get remaining requests using Redis
     */
    private function getRemainingRedis(string $identifier, string $type = 'default'): int {
        try {
            $limit = $this->limits[$type] ?? $this->limits['default'];
            $key = $this->getRedisKey($identifier, $type);
            
            $current = $this->redis->get($key);
            if ($current === false) {
                return $limit['requests'];
            }
            
            $current = (int)$current;
            return max(0, $limit['requests'] - $current);
        } catch (\Exception $e) {
            // Fallback to file-based
            $this->useRedis = false;
            return $this->getRemainingFile($identifier, $type);
        }
    }
    
    /**
     * Get remaining requests using file-based storage (fallback)
     */
    private function getRemainingFile(string $identifier, string $type = 'default'): int {
        $limit = $this->limits[$type] ?? $this->limits['default'];
        $key = $this->getKey($identifier, $type);
        $file = $this->storagePath . md5($key) . '.json';
        
        $data = $this->readData($file);
        $now = time();
        
        if (!isset($data['requests']) || $now > ($data['reset_time'] ?? 0)) {
            return $limit['requests'];
        }
        
        return max(0, $limit['requests'] - $data['requests']);
    }
    
    public function getResetTime(string $identifier, string $type = 'default'): int {
        if ($this->useRedis && $this->redis !== null) {
            return $this->getResetTimeRedis($identifier, $type);
        }
        
        return $this->getResetTimeFile($identifier, $type);
    }
    
    /**
     * Get reset time using Redis
     */
    private function getResetTimeRedis(string $identifier, string $type = 'default'): int {
        try {
            $key = $this->getRedisKey($identifier, $type);
            $ttl = $this->redis->ttl($key);
            
            if ($ttl === -2) {
                // Key doesn't exist
                return time();
            }
            
            if ($ttl === -1) {
                // Key exists but has no expiration (shouldn't happen, but handle it)
                $limit = $this->limits[$type] ?? $this->limits['default'];
                return time() + $limit['period'];
            }
            
            return time() + $ttl;
        } catch (\Exception $e) {
            // Fallback to file-based
            $this->useRedis = false;
            return $this->getResetTimeFile($identifier, $type);
        }
    }
    
    /**
     * Get reset time using file-based storage (fallback)
     */
    private function getResetTimeFile(string $identifier, string $type = 'default'): int {
        $key = $this->getKey($identifier, $type);
        $file = $this->storagePath . md5($key) . '.json';
        
        $data = $this->readData($file);
        
        return $data['reset_time'] ?? time();
    }
    
    private function getKey(string $identifier, string $type): string {
        return $type . ':' . $identifier;
    }
    
    /**
     * Get Redis key for rate limiting
     */
    private function getRedisKey(string $identifier, string $type): string {
        return 'ratelimit:' . $type . ':' . $identifier;
    }
    
    private function readData(string $file): array {
        if (!file_exists($file)) {
            return [];
        }
        
        $content = file_get_contents($file);
        $data = json_decode($content, true);
        
        return is_array($data) ? $data : [];
    }
    
    private function writeData(string $file, array $data): void {
        file_put_contents($file, json_encode($data), LOCK_EX);
    }
    
    public function reset(string $identifier, string $type = 'default'): void {
        if ($this->useRedis && $this->redis !== null) {
            try {
                $key = $this->getRedisKey($identifier, $type);
                $this->redis->del($key);
            } catch (\Exception $e) {
                // Fallback to file-based
                $this->useRedis = false;
            }
        }
        
        // Also reset file-based (for fallback or cleanup)
        $key = $this->getKey($identifier, $type);
        $file = $this->storagePath . md5($key) . '.json';
        
        if (file_exists($file)) {
            unlink($file);
        }
    }
    
    public function cleanup(): void {
        // Redis automatically expires keys, so no cleanup needed
        if ($this->useRedis && $this->redis !== null) {
            // Optionally, we could scan for expired keys, but Redis handles TTL automatically
            return;
        }
        
        // Cleanup file-based storage
        $files = glob($this->storagePath . '*.json');
        $now = time();
        
        foreach ($files as $file) {
            $data = $this->readData($file);
            if (isset($data['reset_time']) && $now > $data['reset_time'] + 3600) {
                unlink($file);
            }
        }
    }
}

