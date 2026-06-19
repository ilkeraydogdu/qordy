<?php
namespace App\Middleware;

require_once __DIR__ . '/../core/DependencyFactory.php';
require_once __DIR__ . '/../core/SessionManager.php';

/**
 * QR Code Authentication Middleware
 * Validates QR code access for customer routes
 * 
 * @package App\Middleware
 */
class QRCodeAuthMiddleware {
    private static $qrSecurityService = null;

    /**
     * Initialize middleware
     */
    private static function init() {
        if (self::$qrSecurityService === null) {
            self::$qrSecurityService = \App\Core\DependencyFactory::getQRCodeSecurityService();
        }
    }

    /**
     * Handle middleware
     * @param string $tableId Table ID from route
     * @return bool|array False on failure, session data on success
     */
    public static function handle(?string $tableId = null): bool|array {
        self::init();
        
        // Ensure session is started
        \App\Core\SessionManager::ensureSession();

        // Get table ID from route parameter or GET parameter
        if (empty($tableId)) {
            $queryParams = \App\Core\RequestParser::getQueryParams();
            $tableId = $queryParams['table_id'] ?? $queryParams['table'] ?? '';
        }

        if (empty($tableId)) {
            // No table ID provided - show table selection
            return false;
        }

        // Check working hours before allowing QR access
        try {
            $settingsService = \App\Core\DependencyFactory::getSystemSettingsService();
            $hoursCheck = $settingsService->checkWorkingHours();
            
            if (!$hoursCheck['open']) {
                $_SESSION['qr_session_error'] = 'OUTSIDE_WORKING_HOURS';
                $_SESSION['qr_session_error_meta'] = [
                    'start_time' => $hoursCheck['start'] ?? '',
                    'end_time' => $hoursCheck['end'] ?? '',
                    'message' => $hoursCheck['message'] ?: 'Mesai saati disindayiz'
                ];
                return false;
            }
        } catch (\Exception $e) {
            // Don't block access if settings check fails
        }

        // Get QR token from session or cookie
        $qrToken = $_SESSION['customer_qr_token'] ?? $_COOKIE['customer_qr_token'] ?? null;

        // Validate QR access
        $validation = self::$qrSecurityService->validateQRAccess($tableId, $qrToken);

        if (!$validation || !$validation['valid']) {
            // Store error details for API handlers (optional)
            if (is_array($validation) && isset($validation['error'])) {
                $_SESSION['qr_session_error'] = $validation['error'];
                $_SESSION['qr_session_error_meta'] = [
                    'max_sessions' => $validation['max_sessions'] ?? null,
                    'active_sessions' => $validation['active_sessions'] ?? null
                ];
            } else {
                $_SESSION['qr_session_error'] = 'QR_SESSION_INVALID';
                $_SESSION['qr_session_error_meta'] = [];
            }

            // Invalid or expired QR code
            // Clear any existing session (including customer_session_id to prevent showing old customers' orders)
            unset($_SESSION['customer_qr_token']);
            unset($_SESSION['customer_session_token']);
            unset($_SESSION['customer_table_id']);
            unset($_SESSION['customer_session_id']);
            
            return false;
        }
        
        // CRITICAL: Tenant isolation check - ensure table belongs to current tenant
        $tenantId = \App\Core\TenantContext::getId();
        if ($tenantId && !empty($tableId)) {
            try {
                $tableService = \App\Core\DependencyFactory::getTableService();
                $table = $tableService->getTableById($tableId);
                
                if ($table) {
                    $tableBusinessId = $table['tenant_id'] ?? null;
                    if (empty($tableBusinessId) || $tableBusinessId !== $tenantId) {
                        // Table doesn't belong to this tenant - deny access
                        if (class_exists('\App\Core\Logger')) {
                            \App\Core\Logger::warning('QR access denied - tenant mismatch', [
                                'table_id' => $tableId,
                                'table_business_id' => $tableBusinessId,
                                'tenant_id' => $tenantId,
                                'subdomain' => $_SESSION['tenant_subdomain'] ?? 'unknown'
                            ]);
                        }
                        // Clear session
                        unset($_SESSION['customer_qr_token']);
                        unset($_SESSION['customer_session_token']);
                        unset($_SESSION['customer_table_id']);
                        unset($_SESSION['customer_session_id']);
                        
                        return false;
                    }
                }
            } catch (\Exception $e) {
                // Log but don't fail - let controller handle it
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::warning('QR middleware: Error checking tenant isolation', [
                        'error' => $e->getMessage(),
                        'table_id' => $tableId
                    ]);
                }
            }
        }

        // Store session data
        $session = $validation['session'];
        $_SESSION['customer_qr_token'] = $session['qr_token'];
        $_SESSION['customer_session_token'] = $session['session_token'];
        $_SESSION['customer_table_id'] = $session['table_id'];
        $_SESSION['customer_session_id'] = $session['customer_session_id'] ?? $session['session_id'];
        unset($_SESSION['qr_session_error'], $_SESSION['qr_session_error_meta']);

        // Set cookie for persistence (7 days)
        setcookie('customer_qr_token', $session['qr_token'], time() + (7 * 24 * 60 * 60), '/', '', false, true);

        return $session;
    }

    /**
     * Check if current request has valid QR session
     * @return bool Has valid session
     */
    public static function hasValidSession(): bool {
        self::init();
        
        \App\Core\SessionManager::ensureSession();

        $sessionToken = $_SESSION['customer_session_token'] ?? null;
        $tableId = $_SESSION['customer_table_id'] ?? null;

        if (empty($sessionToken) || empty($tableId)) {
            return false;
        }

        $session = self::$qrSecurityService->validateCustomerSession($sessionToken);
        return $session !== false && $session['table_id'] === $tableId;
    }

    /**
     * Require valid QR session (redirect if invalid)
     * @param string|null $tableId Table ID
     * @return bool|array False on failure, session data on success
     */
    public static function require(?string $tableId = null): bool|array {
        $result = self::handle($tableId);
        
        if ($result === false) {
            // Redirect to table selection or show error
            if (!defined('BASE_URL')) {
                require_once __DIR__ . '/../config/config.php';
            }
            
            // Check if this is an API request
            $isApiRequest = strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false;
            
            if ($isApiRequest) {
                \App\Core\ApiResponseHelper::error('Invalid or expired QR code session', 401, 'QR_SESSION_EXPIRED', [
                    'success' => false,
                    'code' => 'QR_SESSION_INVALID'
                ]);
                exit;
            }
            
            // For regular requests with tableId, don't redirect - let controller handle it
            // The controller will show menu even if QR validation fails
            // Only redirect if this is a direct /t call without tableId
            if (empty($tableId)) {
                header('Location: ' . BASE_URL . '/menu');
            exit;
            }
            // If tableId exists, return false but don't redirect - let controller show menu
            return false;
        }

        return $result;
    }
}

