<?php
namespace App\Core\Helpers;

/**
 * Centralized IP Address Helper
 * Provides consistent IP detection across the entire application
 */
class IPHelper {
    /**
     * Get client IP address with consistent logic
     * Handles proxies, load balancers, and Cloudflare
     * 
     * @return string Client IP address
     */
    public static function getClientIP(): string {
        // Priority order for IP detection
        // Cloudflare and reverse proxies should be checked first
        $ipKeys = [
            'HTTP_CF_CONNECTING_IP',      // Cloudflare
            'HTTP_X_REAL_IP',             // Nginx reverse proxy
            'HTTP_X_FORWARDED_FOR',        // Standard proxy header
            'HTTP_CLIENT_IP',             // Some proxies
            'HTTP_X_FORWARDED',           // Standard proxy header
            'HTTP_X_CLUSTER_CLIENT_IP',   // Cluster/load balancer
            'HTTP_FORWARDED_FOR',         // Alternative proxy header
            'HTTP_FORWARDED',             // Alternative proxy header
            'REMOTE_ADDR'                 // Direct connection (fallback)
        ];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                
                // Handle comma-separated IPs (take first valid one)
                if (strpos($ip, ',') !== false) {
                    $ips = explode(',', $ip);
                    foreach ($ips as $singleIP) {
                        $singleIP = trim($singleIP);
                        if (self::isValidIP($singleIP)) {
                            return $singleIP;
                        }
                    }
                } else {
                    $ip = trim($ip);
                    if (self::isValidIP($ip)) {
                        return $ip;
                    }
                }
            }
        }
        
        // Fallback to REMOTE_ADDR
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Validate IP address
     * Accepts both IPv4 and IPv6, including private IPs
     * 
     * @param string $ip IP address to validate
     * @return bool True if valid IP
     */
    private static function isValidIP(string $ip): bool {
        // Accept all valid IPs including private ranges
        // Private IPs are valid for NAT/proxy scenarios
        return filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }
    
    /**
     * Check if two IPs are from the same network
     * Useful for NAT/proxy scenarios where IP might change slightly
     * 
     * @param string $ip1 First IP address
     * @param string $ip2 Second IP address
     * @return bool True if IPs are similar (same network)
     */
    public static function areIPsSimilar(string $ip1, string $ip2): bool {
        if ($ip1 === $ip2) {
            return true;
        }
        
        // If both are private IPs, they might be from same NAT
        // In this case, we'll be more lenient
        $isPrivate1 = filter_var($ip1, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
        $isPrivate2 = filter_var($ip2, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
        
        // If both are private IPs, consider them similar (same NAT network)
        if ($isPrivate1 && $isPrivate2) {
            return true;
        }
        
        // For public IPs, they must match exactly
        return false;
    }
}

