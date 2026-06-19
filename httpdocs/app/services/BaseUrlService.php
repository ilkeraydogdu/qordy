<?php
namespace App\Services;

/**
 * Base URL Service
 * Centralized service for managing base URL with automatic domain detection
 * Replaces hardcoded domains and provides dynamic URL generation
 */
class BaseUrlService {
    private static ?string $baseUrl = null;
    private static ?string $domain = null;
    private static ?string $protocol = null;
    private static ?string $cachedHost = null; // Cache the host to detect changes
    
    /**
     * Get base URL (with protocol and domain)
     * Always dynamically detects domain from HTTP_HOST for web requests
     * Falls back to .env for CLI scripts
     * 
     * @return string Base URL (e.g., https://caddecafe.qordy.com)
     */
    public static function getBaseUrl(): string {
        // For web requests: Always check HTTP_HOST dynamically (no cache)
        // This ensures domain is always detected from current request
        if (isset($_SERVER['HTTP_HOST']) && !empty($_SERVER['HTTP_HOST'])) {
            $currentHost = $_SERVER['HTTP_HOST'];
            
            // If host changed or not cached, re-detect
            if (self::$cachedHost !== $currentHost || self::$baseUrl === null) {
                self::$baseUrl = self::autoDetectBaseUrl();
                self::$cachedHost = $currentHost;
                return self::$baseUrl;
            }
            
            // Return cached value for same request
            return self::$baseUrl;
        }
        
        // For CLI scripts: Use .env or fallback
        if (self::$baseUrl === null) {
            // Priority 1: Read from .env file (for CLI scripts only)
            // NOTE: Web requests NEVER use .env APP_URL - they always use HTTP_HOST
            $envUrl = $_ENV['APP_URL'] ?? null;
            if (!empty($envUrl)) {
                $envUrl = trim($envUrl);
                // Validate URL format before using it
                if (filter_var($envUrl, FILTER_VALIDATE_URL)) {
                    self::$baseUrl = rtrim($envUrl, '/');
                    self::parseUrl(self::$baseUrl);
                    return self::$baseUrl;
                } else {
                    // Invalid URL in .env - log warning and fallback
                    error_log("WARNING: Invalid APP_URL in .env: " . $envUrl . " - Using fallback");
                }
            }
            
            // Priority 2: Check if BASE_URL constant is already defined (backward compatibility)
            if (defined('BASE_URL') && !empty(BASE_URL)) {
                self::$baseUrl = BASE_URL;
                self::parseUrl(self::$baseUrl);
                return self::$baseUrl;
            }
            
            // Priority 3: Final fallback
            self::$baseUrl = 'http://localhost';
        }
        
        return self::$baseUrl;
    }
    
    /**
     * Get domain only (without protocol)
     * Always dynamically detects from HTTP_HOST for web requests
     * 
     * @return string Domain (e.g., caddecafe.qordy.com)
     */
    public static function getDomain(): string {
        // Always get fresh base URL to ensure domain is current
        self::getBaseUrl();
        return self::$domain ?? 'localhost';
    }
    
    /**
     * Get protocol only (http or https)
     * Always dynamically detects from server variables for web requests
     * 
     * @return string Protocol (http or https)
     */
    public static function getProtocol(): string {
        // Always get fresh base URL to ensure protocol is current
        self::getBaseUrl();
        return self::$protocol ?? 'http';
    }
    
    /**
     * Auto-detect base URL from server variables
     * 
     * @return string Base URL
     */
    private static function autoDetectBaseUrl(): string {
        // Detect protocol
        $protocol = 'http';
        if (
            (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
            (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
            (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on') ||
            (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
        ) {
            $protocol = 'https';
        }
        
        // Detect host
        $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
        
        // Remove port if present (for cleaner URLs)
        if (strpos($host, ':') !== false) {
            $hostParts = explode(':', $host);
            $host = $hostParts[0];
        }
        
        $baseUrl = $protocol . '://' . $host;
        self::parseUrl($baseUrl);
        
        return $baseUrl;
    }
    
    /**
     * Parse URL to extract domain and protocol
     * 
     * @param string $url Full URL
     */
    private static function parseUrl(string $url): void {
        $parsed = parse_url($url);
        if ($parsed !== false) {
            self::$protocol = $parsed['scheme'] ?? 'http';
            self::$domain = $parsed['host'] ?? 'localhost';
            if (isset($parsed['port'])) {
                self::$domain .= ':' . $parsed['port'];
            }
        }
    }
    
    /**
     * Reset cached values (useful for testing or domain changes)
     */
    public static function reset(): void {
        self::$baseUrl = null;
        self::$domain = null;
        self::$protocol = null;
        self::$cachedHost = null;
    }
    
    /**
     * Check if URL contains old domain and needs update
     * 
     * @param string $url URL to check
     * @param string $oldDomain Old domain to check for
     * @return bool True if URL contains old domain
     */
    public static function containsOldDomain(string $url, string $oldDomain): bool {
        return strpos($url, $oldDomain) !== false;
    }
    
    /**
     * Replace old domain with current domain in URL
     * 
     * @param string $url URL to update
     * @param string $oldDomain Old domain to replace
     * @return string Updated URL
     */
    public static function replaceDomain(string $url, string $oldDomain): string {
        $currentDomain = self::getDomain();
        return str_replace($oldDomain, $currentDomain, $url);
    }
}
