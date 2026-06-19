<?php
namespace App\Services;

use App\Core\DependencyFactory;

/**
 * Auth Helper Service
 * Centralized service for authentication and authorization checks
 * Replaces global helper functions with OOP approach
 */
class AuthHelperService {
    /**
     * Check if user is logged in
     * @return bool
     */
    public function isLoggedIn(): bool {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }
    
    /**
     * Check if user has a specific role
     * @param string $role Required role
     * @return bool
     */
    public function hasRole(string $role): bool {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['role'])) {
            return false;
        }
        
        $currentRole = strtoupper(trim(str_replace('ROLE_', '', $_SESSION['role'])));
        $requiredRole = strtoupper(trim(str_replace('ROLE_', '', $role)));
        
        if ($currentRole === $requiredRole) {
            return true;
        }
        
        // SUPER_ADMIN has all roles
        if ($currentRole === 'SUPER_ADMIN') {
            return true;
        }
        
        // Manager-equivalent roles: MANAGER, BUSINESS_MANAGER, ADMIN, ADMINISTRATOR
        $managerRoles = ['MANAGER', 'BUSINESS_MANAGER', 'ADMIN', 'ADMINISTRATOR'];
        if (in_array($currentRole, $managerRoles) && in_array($requiredRole, $managerRoles)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Check if user has any of the specified roles
     * @param array|string $roles Required roles
     * @return bool
     */
    public function hasAnyRole($roles): bool {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['role'])) {
            return false;
        }
        
        if (!is_array($roles)) {
            $roles = [$roles];
        }
        
        foreach ($roles as $role) {
            if ($this->hasRole($role)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Require user to be logged in (redirects if not)
     */
    public function requireLogin(): void {
        if (!$this->isLoggedIn()) {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['redirect_to'] = $_SERVER['REQUEST_URI'] ?? '/';
            // CRITICAL: Use current host (with subdomain) for redirect
            $currentHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            header('Location: ' . $protocol . '://' . $currentHost . '/login');
            exit;
        }
    }
    
    /**
     * Require user to have a specific role (redirects if not)
     * @param string $role Required role
     */
    public function requireRole(string $role): void {
        if (!$this->hasRole($role)) {
            // CRITICAL: Use current host (with subdomain) for redirect
            $currentHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            header('Location: ' . $protocol . '://' . $currentHost . '/unauthorized');
            exit;
        }
    }
    
    /**
     * Require user to have any of the specified roles (redirects if not)
     * @param array|string $roles Required roles
     */
    public function requireAnyRole($roles): void {
        if (!$this->hasAnyRole($roles)) {
            // CRITICAL: Use current host (with subdomain) for redirect
            $currentHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            header('Location: ' . $protocol . '://' . $currentHost . '/unauthorized');
            exit;
        }
    }
    
    /**
     * Authenticate user with PIN
     * @param string $pin User PIN
     * @return bool Success
     */
    public function authenticateUser(string $pin): bool {
        $authService = DependencyFactory::getAuthenticationService();
        return $authService->authenticateWithPin($pin);
    }
    
    /**
     * Logout user
     */
    public function logoutUser(): void {
        $authService = DependencyFactory::getAuthenticationService();
        $authService->logout();
    }
    
    /**
     * Check if session is still valid (not expired)
     * @return bool
     */
    public function isSessionValid(): bool {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['login_time'])) {
            return false;
        }
        
        $sessionTimeout = 24 * 60 * 60; // 24 hours (restaurant/cafe should stay open)
        return (time() - $_SESSION['login_time']) < $sessionTimeout;
    }
    
    /**
     * Refresh session timestamp
     */
    public function refreshSession(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (isset($_SESSION['logged_in']) && $_SESSION['logged_in']) {
            $_SESSION['login_time'] = time();
        }
    }
    
    /**
     * Get current user info
     * @return array|null User data or null
     */
    public function getCurrentUser(): ?array {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $rawRole = $_SESSION['role'] ?? '';
        $normalizedRole = $rawRole;
        
        if (!empty($rawRole)) {
            require_once __DIR__ . '/RoleMapper.php';
            $roleMapper = \App\Services\RoleMapper::getInstance();
            $normalizedRole = $roleMapper->normalizeRole($rawRole);
            
            if ($normalizedRole !== $rawRole) {
                $_SESSION['role'] = $normalizedRole;
            }
        }
        
        return [
            'user_id' => $_SESSION['user_id'] ?? null,
            'username' => $_SESSION['username'] ?? null,
            'role' => $normalizedRole,
            'customer_id' => $_SESSION['customer_id'] ?? null,
            'first_name' => $_SESSION['first_name'] ?? null,
            'last_name' => $_SESSION['last_name'] ?? null,
            'name' => $_SESSION['name'] ?? null,
            'surname' => $_SESSION['surname'] ?? null,
        ];
    }
    
    /**
     * Generate CSRF token
     * @return string CSRF token
     */
    public function generateCSRFToken(): string {
        require_once __DIR__ . '/../core/Security/CSRFManager.php';
        return \App\Core\Security\CSRFManager::generateToken();
    }
    
    /**
     * Validate CSRF token
     * @param string $token Token to validate
     * @return bool Valid
     */
    public function validateCSRFToken(string $token): bool {
        require_once __DIR__ . '/../core/Security/CSRFManager.php';
        return \App\Core\Security\CSRFManager::validateToken($token);
    }
}

