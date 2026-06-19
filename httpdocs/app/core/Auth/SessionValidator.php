<?php
namespace App\Core\Auth;

/**
 * Session Validator
 * Handles session validation and user authentication state
 */
class SessionValidator {
    /**
     * Check if user is logged in
     * @return bool True if logged in
     */
    public function isLoggedIn(): bool {
        require_once __DIR__ . '/../SessionManager.php';
        \App\Core\SessionManager::ensureSession();
        return \App\Core\SessionManager::get('logged_in') === true;
    }
    
    /**
     * Get current user ID from session
     * @return string|null User ID
     */
    public function getCurrentUserId(): ?string {
        require_once __DIR__ . '/../SessionManager.php';
        \App\Core\SessionManager::ensureSession();
        return \App\Core\SessionManager::get('user_id');
    }
    
    /**
     * Validate session is active and user is authenticated
     * @return bool True if session is valid
     */
    public function validateSession(): bool {
        require_once __DIR__ . '/../SessionManager.php';
        \App\Core\SessionManager::ensureSession();
        
        if (!\App\Core\SessionManager::get('logged_in')) {
            return false;
        }
        
        $userId = \App\Core\SessionManager::get('user_id');
        return !empty($userId);
    }
    
    /**
     * Require login or redirect/fail
     * @param bool $redirect Whether to redirect on failure
     * @return bool True if logged in
     */
    public function requireLogin(bool $redirect = true): bool {
        if ($this->isLoggedIn()) {
            return true;
        }
        
        if ($redirect) {
            // CRITICAL: Use current host (with subdomain) for redirect to preserve subdomain
            $currentHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
            $protocol = getProtocol();
            $loginUrl = $protocol . '://' . $currentHost . '/login';
            header('Location: ' . $loginUrl);
            exit;
        }
        
        return false;
    }
}

