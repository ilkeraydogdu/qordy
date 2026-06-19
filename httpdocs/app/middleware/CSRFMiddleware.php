<?php
namespace App\Middleware;

require_once __DIR__ . '/../helpers/security.php';

class CSRFMiddleware {
    public static function handle() {
        // Ensure session is started for CSRF validation
        \App\Core\SessionManager::ensureSession();
        
        // Only check for POST, PUT, DELETE, PATCH requests
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (!in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'])) {
            return true; // Allow GET, HEAD, OPTIONS requests
        }

        // Get URI for checking exceptions
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $path = parse_url($uri, PHP_URL_PATH) ?? $uri;
        
        // Load CSRF bypass routes from configuration (instead of hardcoded)
        $securityConfig = require __DIR__ . '/../config/security.php';
        $bypassRoutes = $securityConfig['csrf']['bypass_routes'] ?? [];
        
        // Additional dynamic bypass routes based on authentication method
        // These are determined at runtime based on request characteristics
        $dynamicBypassRoutes = [];
        
        // Printer Bridge API endpoints use their own authentication (API key / config_code), skip CSRF entirely
        // These endpoints are designed for external desktop apps that cannot have CSRF tokens
        $printerBridgeEndpoints = [
            '/api/printer-bridge/register',
            '/api/printer-bridge/heartbeat',
            '/api/printer-bridge/queue',
            '/api/printer-bridge/update-status',
            '/api/printer-bridge/config',
            '/api/printer-bridge/sync-printers',
            '/api/printer-bridge/assign-printer',
            '/api/printer-bridge/printers',
            '/pb/register',
            '/pb/heartbeat',
            '/pb/queue',
            '/pb/update-status',
            '/pb/sync-printers',
            '/pb/printer-roles',
            '/pb/screens',
            '/api/printer-bridge/detected-printers',
            '/api/printer-bridge/preparation-screens',
        ];
        
        foreach ($printerBridgeEndpoints as $endpoint) {
            if (strpos($path, $endpoint) === 0) {
                // Skip CSRF for all printer-bridge endpoints - they use API key authentication
                $dynamicBypassRoutes[] = $endpoint;
            }
        }
        
        // Customer panel API endpoints - customers may not have CSRF tokens
        // These endpoints use table_id validation instead
        $customerPanelEndpoints = [
            '/api/call-waiter',
            '/api/request-bill',
            '/api/place-order',
            '/api/session/update-location',
            '/api/session/check',
            '/api/session/continue',
            '/api/update-order-status',
            '/api/cart/sync',
        ];
        
        foreach ($customerPanelEndpoints as $endpoint) {
            if (strpos($path, $endpoint) === 0) {
                // Customer panel endpoints use table_id validation, skip CSRF
                $dynamicBypassRoutes[] = $endpoint;
            }
        }
        
        // Mobile app API endpoints - use Bearer token authentication, not session/CSRF
        if (strpos($path, '/api/mobile/') === 0 || strpos($path, '/api/mobile') === 0) {
            $dynamicBypassRoutes[] = $path;
        }
        
        // Merge config-based and dynamic bypass routes
        $allBypassRoutes = array_merge($bypassRoutes, $dynamicBypassRoutes);
        
        // Use CSRFManager to check if CSRF validation should be applied
        require_once __DIR__ . '/../core/Security/CSRFManager.php';
        if (!\App\Core\Security\CSRFManager::shouldCheck($method, $path, $allBypassRoutes)) {
            return true; // CSRF check bypassed for this route
        }
        
        // Use CSRFManager to extract token from request (checks POST, headers, query string)
        $token = \App\Core\Security\CSRFManager::extractTokenFromRequest();
        
        // Debug: Log token extraction attempt
        if (class_exists('\App\Core\Logger')) {
            $debugHeaders = [];
            if (function_exists('getallheaders')) {
                $allHeaders = getallheaders();
                if ($allHeaders !== false) {
                    $debugHeaders = array_keys($allHeaders);
                }
            }
            // Also check $_SERVER for HTTP_ headers
            foreach ($_SERVER as $key => $value) {
                if (strpos($key, 'HTTP_') === 0) {
                    $debugHeaders[] = $key;
                }
            }
            \App\Core\Logger::debug('CSRF token extraction', [
                'path' => $path,
                'method' => $method,
                'token_found' => $token ? 'yes' : 'no',
                'token_length' => $token ? strlen($token) : 0,
                'available_headers' => $debugHeaders,
                'post_csrf_token' => \App\Core\RequestParser::has('csrf_token') ? 'yes' : 'no'
            ]);
        }
        
        // If no token provided, return error
        if (!$token || empty(trim($token))) {
            \App\Core\ApiResponseHelper::error('CSRF token validation failed', 403, 'CSRF_VALIDATION_FAILED');
            exit;
        }
        
        // Validate token
        $isValid = \App\Core\Security\CSRFManager::validateToken($token);
        
        if (!$isValid) {
            // Try to get current token for debugging
            $currentToken = \App\Core\Security\CSRFManager::getToken();
            $sessionId = session_id();
            
            // Log validation failure for debugging
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::warning('CSRF token validation failed', [
                    'path' => $path,
                    'method' => $method,
                    'token_received_length' => strlen($token),
                    'token_current_length' => $currentToken ? strlen($currentToken) : 0,
                    'token_received_preview' => substr($token, 0, 10) . '...',
                    'token_current_preview' => $currentToken ? substr($currentToken, 0, 10) . '...' : 'null',
                    'session_id' => $sessionId ? substr($sessionId, 0, 10) . '...' : 'null',
                    'session_exists' => $sessionId ? 'yes' : 'no'
                ]);
            }
            
            \App\Core\ApiResponseHelper::error('CSRF token validation failed', 403, 'CSRF_VALIDATION_FAILED');
            exit;
        }

        return true;
    }
}