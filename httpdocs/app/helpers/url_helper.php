<?php
/**
 * URL Helper Functions
 * Provides helper functions for generating URLs based on user role
 */

if (!function_exists('getAdminUrl')) {
    /**
     * Get admin URL based on current user role
     * Super admin uses /qodmin/*, business manager uses /business/*
     * 
     * @param string $path Path after /qodmin/ or /business/
     * @return string Full URL with appropriate prefix
     */
    function getAdminUrl(string $path = ''): string {
        if (!defined('BASE_URL')) {
            require_once __DIR__ . '/../config/config.php';
        }
        
        require_once __DIR__ . '/../core/Authorization.php';
        require_once __DIR__ . '/../core/SessionManager.php';
        
        $auth = \App\Core\Authorization::getInstance();
        $isSuperAdmin = $auth->isSuperAdmin();
        
        // Remove leading slash if present
        $path = ltrim($path, '/');
        
        // Determine prefix
        $prefix = $isSuperAdmin ? '/qodmin' : '/business';
        
        return BASE_URL . $prefix . ($path ? '/' . $path : '');
    }
}

if (!function_exists('adminUrl')) {
    /**
     * Alias for getAdminUrl
     */
    function adminUrl(string $path = ''): string {
        return getAdminUrl($path);
    }
}
