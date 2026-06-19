<?php
namespace App\Interfaces;

/**
 * Cache Interface
 * Defines contract for cache implementations
 */
interface CacheInterface {
    /**
     * Get value from cache
     * @param string $key Cache key
     * @param mixed $default Default value if key not found
     * @return mixed Cached value or default
     */
    public function get(string $key, $default = null);
    
    /**
     * Set value in cache
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @param int|null $ttl Time to live in seconds (null for default)
     * @return bool Success
     */
    public function set(string $key, $value, ?int $ttl = null): bool;
    
    /**
     * Delete value from cache
     * @param string $key Cache key
     * @return bool Success
     */
    public function delete(string $key): bool;
    
    /**
     * Clear all cache
     * @return bool Success
     */
    public function clear(): bool;
    
    /**
     * Check if key exists in cache
     * @param string $key Cache key
     * @return bool True if exists
     */
    public function has(string $key): bool;
    
    /**
     * Get multiple values from cache
     * @param array $keys Array of cache keys
     * @return array Associative array of key => value
     */
    public function getMultiple(array $keys): array;
    
    /**
     * Set multiple values in cache
     * @param array $values Associative array of key => value
     * @param int|null $ttl Time to live in seconds (null for default)
     * @return bool Success
     */
    public function setMultiple(array $values, ?int $ttl = null): bool;
    
    /**
     * Delete multiple values from cache
     * @param array $keys Array of cache keys
     * @return bool Success
     */
    public function deleteMultiple(array $keys): bool;
}

