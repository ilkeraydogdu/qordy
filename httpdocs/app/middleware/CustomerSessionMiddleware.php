<?php
namespace App\Middleware;

require_once __DIR__ . '/../core/DependencyFactory.php';
require_once __DIR__ . '/../core/SessionManager.php';

/**
 * Customer Session Middleware
 * Validates and manages customer sessions
 * 
 * @package App\Middleware
 */
class CustomerSessionMiddleware {
    private static $customerSessionService = null;

    /**
     * Initialize middleware
     */
    private static function init() {
        if (self::$customerSessionService === null) {
            self::$customerSessionService = \App\Core\DependencyFactory::getCustomerSessionService();
        }
    }

    /**
     * Handle middleware - validate customer session
     * @return bool|array False on failure, session data on success
     */
    public static function handle(): bool|array {
        self::init();
        
        // Ensure session is started
        \App\Core\SessionManager::ensureSession();

        $sessionToken = $_SESSION['customer_session_token'] ?? null;
        $tableId = $_SESSION['customer_table_id'] ?? null;

        if (empty($sessionToken) || empty($tableId)) {
            return false;
        }

        // Validate session
        $session = self::$customerSessionService->validateSession($sessionToken);

        if (!$session || $session['table_id'] !== $tableId) {
            // Invalid session - clear
            self::clearSession();
            return false;
        }

        // Update last activity
        $_SESSION['customer_last_activity'] = time();

        return $session;
    }

    /**
     * Require valid customer session
     * @return bool|array False on failure, session data on success
     */
    public static function require(): bool|array {
        $result = self::handle();
        
        if ($result === false) {
            // Check if this is an API request
            $isApiRequest = strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false;
            
            if ($isApiRequest) {
                \App\Core\ApiResponseHelper::error('Customer session expired', 401, 'CUSTOMER_SESSION_EXPIRED', [
                    'success' => false,
                    'code' => 'SESSION_EXPIRED'
                ]);
                exit;
            }
            
            // Redirect to login/table selection
            if (!defined('BASE_URL')) {
                require_once __DIR__ . '/../config/config.php';
            }
            
            header('Location: ' . BASE_URL . '/menu?error=session_expired');
            exit;
        }

        return $result;
    }

    /**
     * Clear customer session
     */
    public static function clearSession(): void {
        \App\Core\SessionManager::ensureSession();
        
        unset($_SESSION['customer_qr_token']);
        unset($_SESSION['customer_session_token']);
        unset($_SESSION['customer_table_id']);
        unset($_SESSION['customer_session_id']);
        unset($_SESSION['customer_last_activity']);
        
        // Clear cookie
        setcookie('customer_qr_token', '', time() - 3600, '/', '', false, true);
    }

    /**
     * Get current customer session
     * @return array|null Session data or null
     */
    public static function getCurrentSession(): ?array {
        $result = self::handle();
        return $result !== false ? $result : null;
    }

    /**
     * Check if customer session exists
     * @return bool Has session
     */
    public static function hasSession(): bool {
        \App\Core\SessionManager::ensureSession();
        return !empty($_SESSION['customer_session_token']) && !empty($_SESSION['customer_table_id']);
    }
}

