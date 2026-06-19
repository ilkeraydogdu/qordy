<?php
namespace App\Services;

require_once __DIR__ . '/../core/SessionManager.php';
require_once __DIR__ . '/../core/DependencyFactory.php';

/**
 * Centralized Session Service
 * Handles session state management and user navigation
 * 
 * Features:
 * - Session state checking
 * - Role-based dashboard URL resolution
 * - Cookie and session management
 * - Integration with SessionManager and AuthenticationService
 * 
 * @package App\Services
 */
class SessionService {
    private static $instance = null;
    private $roleService = null;
    private $authService = null;
    private $constantsService = null;
    private $sessionTimeout = null;
    private $loginUrl = null;
    
    /**
     * Private constructor for singleton pattern
     */
    private function __construct() {
        \App\Core\SessionManager::ensureSession();
        
        try {
            $this->roleService = \App\Core\DependencyFactory::getRoleService();
        } catch (\Exception $e) {
            error_log("SessionService: Could not initialize RoleService: " . $e->getMessage());
        }
        
        try {
            $this->authService = \App\Core\DependencyFactory::getAuthenticationService();
        } catch (\Exception $e) {
            error_log("SessionService: Could not initialize AuthenticationService: " . $e->getMessage());
        }
        
        try {
            $this->constantsService = \App\Core\DependencyFactory::getConstantsService();
        } catch (\Exception $e) {
            error_log("SessionService: Could not initialize ConstantsService: " . $e->getMessage());
        }
    }
    
    /**
     * Get singleton instance
     * @return SessionService
     */
    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Check if user is authenticated
     * @return bool True if user is logged in, false otherwise
     */
    public function isAuthenticated(): bool {
        \App\Core\SessionManager::ensureSession();
        
        // Check if logged_in flag is set
        $loggedIn = \App\Core\SessionManager::get('logged_in', false);
        if (!$loggedIn) {
            return false;
        }
        
        // Check if session is still valid (not expired)
        $loginTime = \App\Core\SessionManager::get('login_time');
        if ($loginTime) {
            $sessionTimeout = $this->getSessionTimeout();
            if ((time() - $loginTime) > $sessionTimeout) {
                return false;
            }
        }
        
        // Check if user_id exists
        $userId = \App\Core\SessionManager::get('user_id');
        return !empty($userId);
    }
    
    /**
     * Get current user's role
     * @return string|null User role or null if not authenticated
     */
    public function getUserRole(): ?string {
        if (!$this->isAuthenticated()) {
            return null;
        }
        
        return \App\Core\SessionManager::get('role');
    }
    
    /**
     * Get current user's role ID
     * @return string|null User role ID or null if not authenticated
     */
    public function getUserRoleId(): ?string {
        if (!$this->isAuthenticated()) {
            return null;
        }
        
        return \App\Core\SessionManager::get('role_id');
    }
    
    /**
     * Get home URL based on user's authentication status and role
     * Returns dashboard URL if authenticated, login URL otherwise
     * 
     * @return string Home URL (dashboard or login)
     */
    public function getHomeUrl(): string {
        // Ensure BASE_URL is defined (must be set in .env file)
        if (!defined('BASE_URL')) {
            if (!isset($_ENV['APP_URL']) || empty($_ENV['APP_URL'])) {
                throw new \Exception('APP_URL environment variable is required. Please set it in .env file.');
            }
            define('BASE_URL', $_ENV['APP_URL']);
        }
        
        // Get login URL (centralized)
        $loginUrl = $this->getLoginUrl();
        
        // If not authenticated, return login URL
        if (!$this->isAuthenticated()) {
            return BASE_URL . $loginUrl;
        }
        
        // Get user role
        $role = $this->getUserRole();
        if (empty($role)) {
            // No role found, redirect to login
            return BASE_URL . $loginUrl;
        }
        
        // Try to get redirect URL from database (RoleService) - PRIMARY SOURCE
        $redirectUrl = null;
        
        try {
            if ($this->roleService) {
                $roleData = $this->roleService->getByRoleCode($role);
                if ($roleData && isset($roleData['default_redirect_url']) && !empty($roleData['default_redirect_url'])) {
                    $redirectUrl = $roleData['default_redirect_url'];
                }
            }
        } catch (\Exception $e) {
            error_log("SessionService: Failed to get redirect URL from database: " . $e->getMessage());
        }
        
        // Fallback: Try to get from all active roles if specific role not found
        if (!$redirectUrl && $this->roleService) {
            try {
                $allRoles = $this->roleService->getActiveRoles();
                foreach ($allRoles as $roleData) {
                    if (isset($roleData['role_code']) && strtoupper($roleData['role_code']) === strtoupper($role)) {
                        if (isset($roleData['default_redirect_url']) && !empty($roleData['default_redirect_url'])) {
                            $redirectUrl = $roleData['default_redirect_url'];
                            break;
                        }
                    }
                }
            } catch (\Exception $e) {
                error_log("SessionService: Failed to get redirect URL from all roles: " . $e->getMessage());
            }
        }
        
        // Final fallback: Special handling for CUSTOMER role or use login URL
        if (!$redirectUrl) {
            // Special handling for CUSTOMER role
            if ($role === 'CUSTOMER') {
                // Check if table_id is in session (from login)
                $tableId = \App\Core\SessionManager::get('table_id');
                if (!empty($tableId)) {
                    // Use UrlService to generate SEO-friendly URL
                    try {
                        $urlService = \App\Core\DependencyFactory::getUrlService();
                        $seoUrl = $urlService->generateTableUrl($tableId, true);
                        // Extract path from full URL
                        $redirectUrl = parse_url($seoUrl, PHP_URL_PATH);
                    } catch (\Exception $e) {
                        error_log("SessionService: Error generating SEO URL for tableId '{$tableId}': " . $e->getMessage());
                        // Fallback to old format if service fails
                        $redirectUrl = '/t/' . $tableId;
                    }
                } else {
                    // Try to get from constants or use default
                    $customerMenuUrl = $this->getConstantValue('ROUTE_URL', 'CUSTOMER_MENU_URL');
                    $redirectUrl = $customerMenuUrl ?: '/menu';
                }
            } else {
                $normalizedRole = strtoupper(str_replace('ROLE_', '', trim($role)));
                $roleRedirects = [
                    'SUPER_ADMIN' => '/qodmin/dashboard',
                    'QODMIN' => '/qodmin/dashboard',
                    'MANAGER' => '/business/dashboard',
                    'BUSINESS_MANAGER' => '/business/dashboard',
                    'ADMIN' => '/business/dashboard',
                    'ADMINISTRATOR' => '/business/dashboard',
                    'WAITER' => '/business/waiter/dashboard',
                    'KITCHEN' => '/business/kitchen/dashboard',
                    'CASHIER' => '/business/pos',
                ];
                $redirectUrl = $roleRedirects[$normalizedRole] ?? $loginUrl;
            }
        }
        
        // Ensure redirect URL starts with /
        if (!empty($redirectUrl) && $redirectUrl[0] !== '/') {
            $redirectUrl = '/' . $redirectUrl;
        }
        
        // Return full URL
        return BASE_URL . $redirectUrl;
    }
    
    /**
     * Get current user ID
     * @return string|null User ID or null if not authenticated
     */
    public function getCurrentUserId(): ?string {
        if (!$this->isAuthenticated()) {
            return null;
        }
        
        return \App\Core\SessionManager::get('user_id');
    }
    
    /**
     * Get current username
     * @return string|null Username or null if not authenticated
     */
    public function getCurrentUsername(): ?string {
        if (!$this->isAuthenticated()) {
            return null;
        }
        
        return \App\Core\SessionManager::get('username');
    }
    
    /**
     * Check if session is valid (not expired)
     * @return bool True if session is valid, false otherwise
     */
    public function isSessionValid(): bool {
        if (!$this->isAuthenticated()) {
            return false;
        }
        
        $loginTime = \App\Core\SessionManager::get('login_time');
        if (!$loginTime) {
            return false;
        }
        
        $sessionTimeout = $this->getSessionTimeout();
        return (time() - $loginTime) < $sessionTimeout;
    }
    
    /**
     * Refresh session timestamp
     * Updates last activity time to prevent session expiration
     */
    public function refreshSession(): void {
        if ($this->isAuthenticated()) {
            \App\Core\SessionManager::set('login_time', time());
            \App\Core\SessionManager::set('last_activity', time());
        }
    }
    
    /**
     * Set a cookie with secure defaults
     * @param string $name Cookie name
     * @param string $value Cookie value
     * @param int $expire Expiration time (Unix timestamp), 0 for session cookie
     * @param string $path Cookie path (default: '/')
     * @param string $domain Cookie domain (default: empty for current domain)
     * @param bool $secure Use HTTPS only (default: true if HTTPS is available)
     * @param bool $httpOnly Prevent JavaScript access (default: true)
     * @param string $sameSite SameSite attribute (default: 'Strict')
     * @return bool True on success, false on failure
     */
    public function setCookie(
        string $name,
        string $value,
        int $expire = 0,
        string $path = '/',
        string $domain = '',
        ?bool $secure = null,
        bool $httpOnly = true,
        string $sameSite = 'Strict'
    ): bool {
        // Determine secure flag if not explicitly set
        if ($secure === null) {
            $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
        }
        
        // Set cookie with secure defaults
        // Use array format for PHP 7.3+ (more secure and flexible)
        if (PHP_VERSION_ID >= 70300) {
            $options = [
                'expires' => $expire,
                'path' => $path,
                'domain' => $domain,
                'secure' => $secure,
                'httponly' => $httpOnly,
                'samesite' => $sameSite
            ];
            return setcookie($name, $value, $options);
        } else {
            // Fallback for older PHP versions (PHP < 7.3)
            // Note: SameSite attribute is not supported in older versions
            return setcookie($name, $value, $expire, $path, $domain, $secure, $httpOnly);
        }
    }
    
    /**
     * Get a cookie value
     * @param string $name Cookie name
     * @param mixed $default Default value if cookie doesn't exist
     * @return mixed Cookie value or default
     */
    public function getCookie(string $name, $default = null) {
        return $_COOKIE[$name] ?? $default;
    }
    
    /**
     * Delete a cookie
     * @param string $name Cookie name
     * @param string $path Cookie path (default: '/')
     * @param string $domain Cookie domain (default: empty)
     * @return bool True on success, false on failure
     */
    public function deleteCookie(string $name, string $path = '/', string $domain = ''): bool {
        if (!isset($_COOKIE[$name])) {
            return true; // Cookie doesn't exist, consider it deleted
        }
        
        $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
        
        // Set cookie with expiration in the past to delete it
        if (PHP_VERSION_ID >= 70300) {
            return setcookie($name, '', [
                'expires' => time() - 3600,
                'path' => $path,
                'domain' => $domain,
                'secure' => $secure,
                'httponly' => true,
                'samesite' => 'Strict'
            ]);
        } else {
            // Fallback for older PHP versions
            return setcookie($name, '', time() - 3600, $path, $domain, $secure, true);
        }
    }
    
    /**
     * Get all session data as array (for debugging/logging purposes)
     * @return array Session data
     */
    public function getSessionData(): array {
        \App\Core\SessionManager::ensureSession();
        return $_SESSION ?? [];
    }
    
    /**
     * Clear all session data (but keep session alive)
     * Useful for logout without destroying session completely
     */
    public function clearSessionData(): void {
        \App\Core\SessionManager::ensureSession();
        $_SESSION = [];
    }
    
    /**
     * Get session timeout from system constants (in seconds)
     * Falls back to 8 hours (28800 seconds) if not configured
     * @return int Session timeout in seconds
     */
    private function getSessionTimeout(): int {
        if ($this->sessionTimeout !== null) {
            return $this->sessionTimeout;
        }
        
        // Try to get from system constants
        try {
            if ($this->constantsService) {
                $timeoutValue = $this->constantsService->getValue('SESSION_CONFIG', 'SESSION_TIMEOUT');
                if ($timeoutValue !== null && is_numeric($timeoutValue)) {
                    $this->sessionTimeout = (int)$timeoutValue;
                    return $this->sessionTimeout;
                }
            }
        } catch (\Exception $e) {
            error_log("SessionService: Failed to get session timeout from constants: " . $e->getMessage());
        }
        
        // Fallback: 24 hours (86400 seconds) - restaurant/cafe should stay open
        $this->sessionTimeout = 24 * 60 * 60;
        return $this->sessionTimeout;
    }
    
    /**
     * Get login URL from system constants
     * Falls back to '/login' if not configured
     * @return string Login URL path
     */
    private function getLoginUrl(): string {
        if ($this->loginUrl !== null) {
            return $this->loginUrl;
        }
        
        // Try to get from system constants
        try {
            if ($this->constantsService) {
                $loginUrlValue = $this->constantsService->getValue('ROUTE_URL', 'LOGIN_URL');
                if ($loginUrlValue !== null && !empty($loginUrlValue)) {
                    $this->loginUrl = $loginUrlValue;
                    // Ensure it starts with /
                    if ($this->loginUrl[0] !== '/') {
                        $this->loginUrl = '/' . $this->loginUrl;
                    }
                    return $this->loginUrl;
                }
            }
        } catch (\Exception $e) {
            error_log("SessionService: Failed to get login URL from constants: " . $e->getMessage());
        }
        
        // Fallback: '/login'
        $this->loginUrl = '/login';
        return $this->loginUrl;
    }
    
    /**
     * Get constant value by type and key
     * Helper method for accessing system constants
     * @param string $type Constant type
     * @param string $key Constant key
     * @return string|null Constant value or null
     */
    private function getConstantValue(string $type, string $key): ?string {
        try {
            if ($this->constantsService) {
                return $this->constantsService->getValue($type, $key);
            }
        } catch (\Exception $e) {
            error_log("SessionService: Failed to get constant value: " . $e->getMessage());
        }
        return null;
    }
}

