<?php
/**
 * CSRF Helper Functions
 */

if (!function_exists('csrf_field')) {
    /**
     * Generate CSRF token input field
     * @return string HTML input field
     */
    function csrf_field(): string {
        require_once __DIR__ . '/../core/Security/CSRFManager.php';
        $token = \App\Core\Security\CSRFManager::generateToken();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
    }
}

if (!function_exists('csrf_token')) {
    /**
     * Get CSRF token value
     * @return string Token value
     */
    function csrf_token(): string {
        require_once __DIR__ . '/../core/Security/CSRFManager.php';
        return \App\Core\Security\CSRFManager::generateToken();
    }
}
