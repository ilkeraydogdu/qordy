<?php
namespace App\Services;

use App\Interfaces\CacheInterface;

/**
 * File Cache Implementation
 * Stores cache data in files
 */
class FileCache implements CacheInterface {
    private $path;
    private $defaultTtl;
    
    public function __construct(array $config = []) {
        $this->path = $config['path'] ?? __DIR__ . '/../../cache';
        $this->defaultTtl = $config['ttl'] ?? 3600;
        
        // Create cache directory if it doesn't exist
        if (!is_dir($this->path)) {
            mkdir($this->path, 0755, true);
        }
    }
    
    /**
     * Get cache file path
     * @param string $key Cache key
     * @return string File path
     */
    private function getFilePath(string $key): string {
        // Sanitize key to prevent directory traversal
        $safeKey = preg_replace('/[^a-zA-Z0-9_-]/', '_', $key);
        $hash = md5($key);
        $subdir = substr($hash, 0, 2);
        $subdirPath = $this->path . '/' . $subdir;
        
        if (!is_dir($subdirPath)) {
            mkdir($subdirPath, 0755, true);
        }
        
        return $subdirPath . '/' . $hash . '.cache';
    }
    
    /**
     * Get value from cache
     * @param string $key Cache key
     * @param mixed $default Default value if key not found
     * @return mixed Cached value or default
     */
    public function get(string $key, $default = null) {
        $filePath = $this->getFilePath($key);
        
        if (!file_exists($filePath)) {
            return $default;
        }
        
        $data = @json_decode(file_get_contents($filePath), true);

        if ($data === null) {
            @unlink($filePath);
            return $default;
        }
        
        // Check expiration
        if (isset($data['expires_at']) && $data['expires_at'] < time()) {
            @unlink($filePath);
            return $default;
        }
        
        return $data['value'] ?? $default;
    }
    
    /**
     * Set value in cache
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @param int|null $ttl Time to live in seconds (null for default)
     * @return bool Success
     */
    public function set(string $key, $value, ?int $ttl = null): bool {
        $filePath = $this->getFilePath($key);
        $ttl = $ttl ?? $this->defaultTtl;
        
        $data = [
            'value' => $value,
            'expires_at' => time() + $ttl,
            'created_at' => time()
        ];
        
        return file_put_contents($filePath, json_encode($data), LOCK_EX) !== false;
    }
    
    /**
     * Delete value from cache
     * @param string $key Cache key
     * @return bool Success
     */
    public function delete(string $key): bool {
        $filePath = $this->getFilePath($key);
        
        if (file_exists($filePath)) {
            return @unlink($filePath);
        }
        
        return true;
    }
    
    /**
     * Clear all cache
     * @return bool Success
     */
    public function clear(): bool {
        return $this->deleteDirectory($this->path);
    }
    
    /**
     * Check if key exists in cache
     * @param string $key Cache key
     * @return bool True if exists
     */
    public function has(string $key): bool {
        $filePath = $this->getFilePath($key);
        
        if (!file_exists($filePath)) {
            return false;
        }
        
        $data = @json_decode(file_get_contents($filePath), true);

        if ($data === null) {
            return false;
        }
        
        // Check expiration
        if (isset($data['expires_at']) && $data['expires_at'] < time()) {
            @unlink($filePath);
            return false;
        }
        
        return true;
    }
    
    /**
     * Get multiple values from cache
     * @param array $keys Array of cache keys
     * @return array Associative array of key => value
     */
    public function getMultiple(array $keys): array {
        $results = [];
        foreach ($keys as $key) {
            $results[$key] = $this->get($key);
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
        $success = true;
        foreach ($keys as $key) {
            if (!$this->delete($key)) {
                $success = false;
            }
        }
        return $success;
    }
    
    /**
     * Delete directory recursively
     * @param string $dir Directory path
     * @return bool Success
     */
    private function deleteDirectory(string $dir): bool {
        if (!is_dir($dir)) {
            return true;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $filePath = $dir . '/' . $file;
            if (is_dir($filePath)) {
                $this->deleteDirectory($filePath);
            } else {
                @unlink($filePath);
            }
        }
        
        return @rmdir($dir);
    }
}

