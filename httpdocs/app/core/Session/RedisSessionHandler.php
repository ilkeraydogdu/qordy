<?php
namespace App\Core\Session;

/**
 * Redis Session Handler
 * Implements PHP SessionHandlerInterface for Redis storage
 */
class RedisSessionHandler implements \SessionHandlerInterface {
    private $redis;
    private $prefix;
    private $ttl;
    private $config;
    
    /**
     * Constructor
     * @param array $config Redis configuration
     */
    public function __construct(array $config = []) {
        $this->config = $config;
        $this->prefix = $config['prefix'] ?? 'session:';
        $this->ttl = $config['ttl'] ?? 3600; // Default 1 hour
        
        // Initialize Redis connection
        $this->redis = new \Redis();
        $host = $config['host'] ?? '127.0.0.1';
        $port = $config['port'] ?? 6379;
        $password = $config['password'] ?? null;
        $database = $config['database'] ?? 1; // Use database 1 for sessions (0 for cache)
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
    }
    
    /**
     * Initialize session
     * @param string $savePath
     * @param string $sessionName
     * @return bool
     */
    public function open($savePath, $sessionName): bool {
        return true; // Connection already established in constructor
    }
    
    /**
     * Close session
     * @return bool
     */
    public function close(): bool {
        return true; // Keep connection open for performance
    }
    
    /**
     * Read session data
     * @param string $sessionId
     * @return string
     */
    public function read($sessionId): string {
        $key = $this->prefix . $sessionId;
        $data = $this->redis->get($key);
        
        if ($data === false) {
            return '';
        }
        
        return $data;
    }
    
    /**
     * Write session data
     * @param string $sessionId
     * @param string $sessionData
     * @return bool
     */
    public function write($sessionId, $sessionData): bool {
        $key = $this->prefix . $sessionId;
        
        // Use SETEX to set with expiration
        return $this->redis->setex($key, $this->ttl, $sessionData);
    }
    
    /**
     * Destroy session
     * @param string $sessionId
     * @return bool
     */
    public function destroy($sessionId): bool {
        $key = $this->prefix . $sessionId;
        return $this->redis->del($key) > 0;
    }
    
    /**
     * Garbage collection (cleanup old sessions)
     * @param int $maxLifetime
     * @return int|false Number of deleted sessions
     */
    public function gc($maxLifetime): int|false {
        // Redis handles TTL automatically, but we can clean up manually if needed
        // This is called periodically by PHP
        return 0; // Redis handles expiration automatically
    }
    
    /**
     * Get Redis connection (for advanced usage)
     * @return \Redis
     */
    public function getRedis(): \Redis {
        return $this->redis;
    }
    
    /**
     * Check if Redis is available
     * @return bool
     */
    public static function isAvailable(): bool {
        return extension_loaded('redis');
    }
}

