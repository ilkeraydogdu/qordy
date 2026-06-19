<?php
namespace App\Core;

/**
 * TenantContext - Global tenant context manager
 * Stores and provides access to current tenant information
 */
class TenantContext {
    private static $tenant = null;
    private static $tenantId = null;
    
    /**
     * Set the current tenant
     * @param array $tenant Tenant data
     */
    public static function set($tenant) {
        self::$tenant = $tenant;
        // Canonical tenant identifier. Accept any of the legacy field names for
        // backward compatibility but always expose a single canonical id.
        self::$tenantId = $tenant['tenant_id']
            ?? $tenant['business_id']
            ?? $tenant['customer_id']
            ?? $tenant['id']
            ?? null;
    }
    
    /**
     * Get the current tenant
     * @return array|null
     */
    public static function get() {
        return self::$tenant;
    }
    
    /**
     * Get the current tenant ID
     * @return string|int|null
     */
    public static function getId() {
        return self::$tenantId;
    }
    
    /**
     * Set tenant ID directly
     * @param string|int $tenantId
     */
    public static function setId($tenantId) {
        self::$tenantId = $tenantId;
    }
    
    /**
     * Check if a tenant is set
     * @return bool
     */
    public static function isSet() {
        return self::$tenantId !== null;
    }
    
    /**
     * Clear the current tenant context
     */
    public static function clear() {
        self::$tenant = null;
        self::$tenantId = null;
    }
    
    /**
     * Get tenant from subdomain
     * @param string $host The full host (e.g., restaurant1.qordy.com)
     * @return string|null The subdomain (e.g., restaurant1) or null
     */
    public static function getSubdomainFromHost($host) {
        // Remove port if present
        $host = explode(':', $host)[0];
        
        // Split by dots
        $parts = explode('.', $host);
        
        // If we have at least 3 parts (subdomain.domain.tld)
        if (count($parts) >= 3) {
            $subdomain = $parts[0];
            
            // Ignore www and common subdomains
            if (!in_array($subdomain, ['www', 'admin', 'api', 'qodmin', 'business'])) {
                return $subdomain;
            }
        }
        
        return null;
    }
}
