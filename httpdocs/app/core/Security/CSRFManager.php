<?php
namespace App\Core\Security;

use App\Core\SessionManager;

/**
 * CSRF Token Manager
 * Centralized CSRF token generation, validation, and rotation
 * Uses Redis for token storage when available, falls back to session
 */
class CSRFManager {
    private const TOKEN_NAME = 'csrf_token';
    private const ROTATION_INTERVAL = 600; // 10 minutes
    private const TOKEN_LENGTH = 32; // bytes
    private static $redisCache = null;
    
    /**
     * Get Redis cache instance
     * @return \App\Services\CacheService|null
     */
    private static function getRedisCache() {
        if (self::$redisCache === null) {
            try {
                $cacheService = \App\Core\DependencyFactory::getCacheService();
                $cacheConfig = require __DIR__ . '/../../config/cache.php';
                
                // Only use Redis if configured
                if ($cacheConfig['driver'] === 'redis' && extension_loaded('redis')) {
                    self::$redisCache = $cacheService;
                }
            } catch (\Exception $e) {
                // Redis not available, will use session
                self::$redisCache = false;
            }
        }
        
        return self::$redisCache ?: null;
    }
    
    /**
     * Get Redis key for CSRF token
     * @param string $sessionId
     * @return string
     */
    private static function getRedisKey(string $sessionId): string {
        return 'csrf:token:' . $sessionId;
    }
    
    /**
     * Get Redis key for previous CSRF token
     * @param string $sessionId
     * @return string
     */
    private static function getPreviousRedisKey(string $sessionId): string {
        return 'csrf:token:previous:' . $sessionId;
    }
    
    /**
     * Get Redis key for token timestamp
     * @param string $sessionId
     * @return string
     */
    private static function getTimeRedisKey(string $sessionId): string {
        return 'csrf:token:time:' . $sessionId;
    }
    
    /**
     * Generate CSRF token with rotation support
     * Tokens are rotated periodically for enhanced security
     * Uses Redis if available, otherwise falls back to session
     * 
     * @return string CSRF token
     */
    public static function generateToken(): string {
        SessionManager::ensureSession();
        $sessionId = session_id();
        
        $redisCache = self::getRedisCache();
        
        if ($redisCache !== null) {
            // Use Redis for token storage
            $tokenKey = self::getRedisKey($sessionId);
            $timeKey = self::getTimeRedisKey($sessionId);
            $previousKey = self::getPreviousRedisKey($sessionId);
            
            $lastRotation = $redisCache->get($timeKey, 0);
            $currentTime = time();
            $currentToken = $redisCache->get($tokenKey);
            
            if ($currentToken === null || ($currentTime - $lastRotation) > self::ROTATION_INTERVAL) {
                // Store previous token for graceful rotation
                if ($currentToken !== null) {
                    $redisCache->set($previousKey, $currentToken, self::ROTATION_INTERVAL * 2);
                }
                
                // Generate new token
                $token = bin2hex(random_bytes(self::TOKEN_LENGTH));
                $redisCache->set($tokenKey, $token, self::ROTATION_INTERVAL * 2);
                $redisCache->set($timeKey, $currentTime, self::ROTATION_INTERVAL * 2);
                
                return $token;
            }
            
            return $currentToken;
        } else {
            // Fallback to session storage
            $lastRotation = SessionManager::get('csrf_token_time', 0);
            $currentTime = time();
            
            if (!SessionManager::has(self::TOKEN_NAME) || 
                ($currentTime - $lastRotation) > self::ROTATION_INTERVAL) {
                
                // Store previous token for graceful rotation
                if (SessionManager::has(self::TOKEN_NAME)) {
                    SessionManager::set('csrf_token_previous', SessionManager::get(self::TOKEN_NAME));
                }
                
                // Generate new token
                $token = bin2hex(random_bytes(self::TOKEN_LENGTH));
                SessionManager::set(self::TOKEN_NAME, $token);
                SessionManager::set('csrf_token_time', $currentTime);
                
                return $token;
            }
            
            return SessionManager::get(self::TOKEN_NAME);
        }
    }
    
    /**
     * Validate CSRF token
     * Supports token rotation - accepts current or previous token
     * Uses Redis if available, otherwise falls back to session
     *
     * @param string|null $token Token to validate
     * @return bool True if token is valid
     */
    public static function validateToken(?string $token): bool {
        SessionManager::ensureSession();

        if (empty($token) || !is_string($token)) {
            return false;
        }

        // Additional validation: check token length to prevent timing attacks
        if (strlen($token) !== (self::TOKEN_LENGTH * 2)) { // hex representation doubles the length
            return false;
        }

        $sessionId = session_id();
        $redisCache = self::getRedisCache();

        if ($redisCache !== null) {
            // Use Redis for token validation
            $tokenKey = self::getRedisKey($sessionId);
            $previousKey = self::getPreviousRedisKey($sessionId);

            $currentToken = $redisCache->get($tokenKey);

            // If no token exists, this is likely a new session or token was cleared
            // Don't auto-generate here - token should exist if page was loaded properly
            if ($currentToken === null) {
                return false;
            }

            // Check current token
            if (hash_equals($currentToken, $token)) {
                return true;
            }

            // Check previous token (for graceful rotation)
            $previousToken = $redisCache->get($previousKey);
            if ($previousToken !== null && hash_equals($previousToken, $token)) {
                // Rotate token after using previous one
                self::generateToken();
                return true;
            }

            return false;
        } else {
            // Fallback to session validation
            if (!SessionManager::has(self::TOKEN_NAME)) {
                // Token should exist if page was loaded properly
                // Don't auto-generate here - return false to force page refresh
                return false;
            }

            $currentToken = SessionManager::get(self::TOKEN_NAME);

            // Check current token
            if (hash_equals($currentToken, $token)) {
                return true;
            }

            // Check previous token (for graceful rotation)
            if (SessionManager::has('csrf_token_previous')) {
                $previousToken = SessionManager::get('csrf_token_previous');
                if (hash_equals($previousToken, $token)) {
                    // Rotate token after using previous one
                    self::generateToken();
                    return true;
                }
            }

            return false;
        }
    }
    
    /**
     * Get current CSRF token (without generating new one)
     * 
     * @return string|null Current token or null if not exists
     */
    public static function getToken(): ?string {
        SessionManager::ensureSession();
        $sessionId = session_id();
        
        $redisCache = self::getRedisCache();
        
        if ($redisCache !== null) {
            $tokenKey = self::getRedisKey($sessionId);
            return $redisCache->get($tokenKey);
        } else {
            return SessionManager::get(self::TOKEN_NAME);
        }
    }
    
    /**
     * Check if CSRF protection should be applied to this request
     * 
     * @param string $method HTTP method
     * @param string $path Request path
     * @param array $bypassRoutes Routes that bypass CSRF check
     * @return bool True if CSRF check should be applied
     */
    public static function shouldCheck(string $method, string $path, array $bypassRoutes = []): bool {
        // Only check POST, PUT, DELETE, PATCH methods
        if (!in_array(strtoupper($method), ['POST', 'PUT', 'DELETE', 'PATCH'])) {
            return false;
        }
        
        // Check bypass routes
        foreach ($bypassRoutes as $bypassRoute) {
            if (strpos($path, $bypassRoute) === 0) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Extract token from request
     * Checks POST data, headers, and query string
     * 
     * @return string|null Extracted token or null
     */
    public static function extractTokenFromRequest(): ?string {
        // Check POST data first
        if (isset($_POST['csrf_token']) && !empty($_POST['csrf_token'])) {
            return $_POST['csrf_token'];
        }
        
        // Check headers (for AJAX requests)
        // Try getallheaders() first (works in Apache/mod_php)
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            if ($headers !== false) {
                if (isset($headers['X-CSRF-Token'])) {
                    return $headers['X-CSRF-Token'];
                }
                if (isset($headers['x-csrf-token'])) {
                    return $headers['x-csrf-token'];
                }
                // Case-insensitive check
                foreach ($headers as $key => $value) {
                    if (strtolower($key) === 'x-csrf-token') {
                        return $value;
                    }
                }
            }
        }
        
        // Fallback: Check $_SERVER superglobal (works in FastCGI/Nginx)
        // HTTP headers are prefixed with HTTP_ and converted to uppercase
        $serverHeaders = [
            'HTTP_X_CSRF_TOKEN',
            'HTTP_X_CSRF_TOKEN',
            'HTTP_X-CSRF-TOKEN',
            'HTTP_X-CSRF-TOKEN'
        ];
        
        foreach ($serverHeaders as $headerKey) {
            if (isset($_SERVER[$headerKey]) && !empty($_SERVER[$headerKey])) {
                return $_SERVER[$headerKey];
            }
        }
        
        // Also check all HTTP_ prefixed headers (case-insensitive)
        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $normalizedKey = str_replace('_', '-', substr($key, 5));
                if (strtolower($normalizedKey) === 'x-csrf-token') {
                    return $value;
                }
            }
        }
        
        // Check query string (for GET requests with CSRF - rare but possible)
        if (isset($_GET['csrf_token']) && !empty($_GET['csrf_token'])) {
            return $_GET['csrf_token'];
        }
        
        return null;
    }
}

