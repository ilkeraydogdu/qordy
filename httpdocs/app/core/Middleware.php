<?php
namespace App\Core;

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/Authorization.php';

class Middleware {
    private static $auth;
    
    public static function init() {
        if (self::$auth === null) {
            self::$auth = Authorization::getInstance();
        }
    }
    /**
     * Check if user is authenticated
     */
    public static function auth($redirect = true) {
        self::init();
        return self::$auth->requireLogin($redirect);
    }
    
    /**
     * Check if user has specific role
     */
    public static function role($role, $redirect = true) {
        self::init();
        return self::$auth->requireRole($role, $redirect);
    }
    
    /**
     * Check if user has any of the specified roles
     */
    public static function anyRole($roles, $redirect = true) {
        self::init();
        return self::$auth->requireAnyRole($roles, $redirect);
    }
    
    /**
     * Check if user has specific permission
     */
    public static function permission($permission, $redirect = true) {
        self::init();
        return self::$auth->requirePermission($permission, $redirect);
    }
    
    /**
     * Check if user has any of the specified permissions
     */
    public static function anyPermission($permissions, $redirect = true) {
        self::init();
        return self::$auth->requireAnyPermission($permissions, $redirect);
    }
    
    /**
     * Check if session is valid
     */
    public static function sessionValid($redirect = true) {
        if (!isSessionValid()) {
            if ($redirect) {
                session_destroy();
                // CRITICAL: Use current host (with subdomain) for redirect to preserve subdomain
                $currentHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $loginUrl = $protocol . '://' . $currentHost . '/login?expired=1';
                header('Location: ' . $loginUrl);
                exit;
            } else {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * CSRF protection (delegated to CSRFManager)
     */
    public static function csrf($redirect = true) {
        require_once __DIR__ . '/Security/CSRFManager.php';
        
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (!in_array(strtoupper($method), ['POST', 'PUT', 'DELETE', 'PATCH'])) {
            return true;
        }
        
        $token = \App\Core\Security\CSRFManager::extractTokenFromRequest();
        
        if (!\App\Core\Security\CSRFManager::validateToken($token)) {
            if ($redirect) {
                $_SESSION['error'] = 'Güvenlik doğrulaması başarısız oldu. Lütfen tekrar deneyin.';
                // CRITICAL: Use current host (with subdomain) for redirect
                $currentHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                header('Location: ' . $protocol . '://' . $currentHost . '/login');
                exit;
            } else {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Combined authentication check
     */
    public static function check($options = []) {
        self::init();
        $options = array_merge([
            'auth' => true,
            'role' => null,
            'anyRole' => null,
            'permission' => null,
            'anyPermission' => null,
            'sessionValid' => true,
            'csrf' => false,
            'redirect' => true
        ], $options);
        
        if ($options['auth']) {
            if (!self::auth($options['redirect'])) {
                return false;
            }
        }
        
        if ($options['sessionValid']) {
            if (!self::sessionValid($options['redirect'])) {
                return false;
            }
        }
        
        if ($options['role']) {
            if (!self::role($options['role'], $options['redirect'])) {
                return false;
            }
        }
        
        if ($options['anyRole']) {
            if (!self::anyRole($options['anyRole'], $options['redirect'])) {
                return false;
            }
        }
        
        if ($options['permission']) {
            if (!self::permission($options['permission'], $options['redirect'])) {
                return false;
            }
        }
        
        if ($options['anyPermission']) {
            if (!self::anyPermission($options['anyPermission'], $options['redirect'])) {
                return false;
            }
        }
        
        if ($options['csrf']) {
            if (!self::csrf($options['redirect'])) {
                return false;
            }
        }
        
        return true;
    }
}