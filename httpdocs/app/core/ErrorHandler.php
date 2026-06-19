<?php
namespace App\Core;

require_once __DIR__ . '/Logger.php';

/**
 * Centralized Error Handler
 * Handles all PHP errors and exceptions
 */
class ErrorHandler {
    private static $initialized = false;
    
    /**
     * Initialize error handler
     */
    public static function init() {
        if (self::$initialized) {
            return;
        }
        
        // Set error reporting based on environment
        // Get APP_ENV and APP_DEBUG from database instead of .env
        // But only if DependencyFactory is available (autoloader loaded)
        $isProduction = false;
        $isDebug = false;
        
        try {
            // Check if DependencyFactory class exists (autoloader loaded)
            if (class_exists('\App\Core\DependencyFactory', false)) {
                $settingsService = \App\Core\DependencyFactory::getSystemSettingsService();
                $isProduction = ($settingsService->getAppEnv() === 'production');
                $isDebug = $settingsService->getAppDebug();
            } else {
                // Fallback to .env if DependencyFactory not available yet
                $isProduction = (isset($_ENV['APP_ENV']) && $_ENV['APP_ENV'] === 'production');
                $isDebug = (isset($_ENV['APP_DEBUG']) && ($_ENV['APP_DEBUG'] === 'true' || $_ENV['APP_DEBUG'] === '1'));
            }
        } catch (\Exception $e) {
            // Fallback to .env if database is not available
            $isProduction = (isset($_ENV['APP_ENV']) && $_ENV['APP_ENV'] === 'production');
            $isDebug = (isset($_ENV['APP_DEBUG']) && ($_ENV['APP_DEBUG'] === 'true' || $_ENV['APP_DEBUG'] === '1'));
        }
        
        if ($isProduction) {
            error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
            ini_set('display_errors', '0');
        } else {
            error_reporting(E_ALL);
            ini_set('display_errors', $isDebug ? '1' : '0');
        }
        
        ini_set('log_errors', '1');
        ini_set('error_log', __DIR__ . '/../../logs/php_errors.log');
        
        // Set custom error handler
        set_error_handler([self::class, 'handleError']);
        
        // Set custom exception handler
        set_exception_handler([self::class, 'handleException']);
        
        // Set shutdown handler for fatal errors
        register_shutdown_function([self::class, 'handleShutdown']);
        
        self::$initialized = true;
    }
    
    /**
     * Handle PHP errors
     */
    public static function handleError($severity, $message, $file, $line) {
        if (!(error_reporting() & $severity)) {
            return false;
        }
        
        $errorLevel = self::getErrorLevel($severity);
        $context = [
            'file' => $file,
            'line' => $line,
            'severity' => $severity
        ];
        
        Logger::error("PHP Error: {$message}", $context);
        
        // For fatal errors, also show error page
        if ($severity === E_ERROR || $severity === E_CORE_ERROR || $severity === E_COMPILE_ERROR) {
            self::showErrorPage(500, 'Fatal Error', $message);
        }
        
        return true;
    }
    
    /**
     * Handle uncaught exceptions
     */
    public static function handleException(\Throwable $exception) {
        try {
            Logger::exception($exception);
        } catch (\Throwable $e) {
            // Fallback if logger fails
            error_log("Exception: " . $exception->getMessage() . " in " . $exception->getFile() . ":" . $exception->getLine());
        }

        // Check if this is production environment
        $isProduction = false;
        try {
            if (class_exists('\App\Core\DependencyFactory', false)) {
                $settingsService = \App\Core\DependencyFactory::getSystemSettingsService();
                $isProduction = ($settingsService->getAppEnv() === 'production');
            } else {
                $isProduction = (isset($_ENV['APP_ENV']) && $_ENV['APP_ENV'] === 'production');
            }
        } catch (\Exception $e) {
            $isProduction = (isset($_ENV['APP_ENV']) && $_ENV['APP_ENV'] === 'production');
        }

        // Build detailed error message
        $errorMessage = $exception->getMessage();
        $errorFile = $exception->getFile();
        $errorLine = $exception->getLine();

        // In production, don't expose file path and line number in error message
        $fullMessage = $isProduction ?
            "An unexpected error occurred" :
            "{$errorMessage}\n\nFile: {$errorFile}\nLine: {$errorLine}\n\nStack Trace:\n{$exception->getTraceAsString()}";

        // Log the full exception details regardless of environment
        $logContext = [
            'message' => $errorMessage,
            'file' => $errorFile,
            'line' => $errorLine,
            'trace' => $exception->getTraceAsString(),
            'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
            'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? ''
        ];

        Logger::error("Uncaught Exception: " . $errorMessage, $logContext);

        // Check if this is an API request before showing error page
        $isApiRequest = false;
        try {
            if (class_exists('\App\Core\RequestTypeDetector')) {
                $isApiRequest = \App\Core\RequestTypeDetector::isAPIRequest();
            }
        } catch (\Throwable $e) {
            // Fallback detection if RequestTypeDetector fails
            $uri = $_SERVER['REQUEST_URI'] ?? '';
            $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
            $acceptHeader = $_SERVER['HTTP_ACCEPT'] ?? '';
            $requestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';

            $isApiRequest = (
                strpos($uri, '/api/') !== false ||
                ((strpos($uri, '/qodmin/shifts/') !== false || strpos($uri, '/business/shifts/') !== false) && $_SERVER['REQUEST_METHOD'] === 'POST') ||
                strpos($contentType, 'application/json') !== false ||
                strpos($acceptHeader, 'application/json') !== false ||
                strtolower($requestedWith) === 'xmlhttprequest'
            );
        }

        if ($isApiRequest) {
            // Return JSON error response for API requests
            try {
                \App\Core\ApiResponseHelper::send([
                    'success' => false,
                    'error' => 'Internal Server Error',
                    'message' => $isProduction ? 'An unexpected error occurred' : $errorMessage,
                    'code' => 500
                ], 500);
            } catch (\Throwable $e) {
                // If ApiResponseHelper fails, send basic JSON response
                http_response_code(500);
                if (!headers_sent()) {
                    header('Content-Type: application/json; charset=utf-8');
                }
                echo json_encode([
                    'success' => false,
                    'error' => 'Internal Server Error',
                    'message' => $isProduction ? 'An unexpected error occurred' : $errorMessage
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
        } else {
            self::showErrorPage(500, 'Uncaught Exception', $fullMessage);
        }
    }
    
    /**
     * Handle fatal errors
     */
    public static function handleShutdown() {
        $error = error_get_last();
        
        if ($error !== null && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
            self::handleError(
                $error['type'],
                $error['message'],
                $error['file'],
                $error['line']
            );
        }
    }
    
    /**
     * Convert PHP error level to string
     */
    private static function getErrorLevel($severity) {
        switch ($severity) {
            case E_ERROR:
            case E_CORE_ERROR:
            case E_COMPILE_ERROR:
            case E_PARSE:
                return 'ERROR';
            case E_WARNING:
            case E_CORE_WARNING:
            case E_COMPILE_WARNING:
                return 'WARNING';
            case E_NOTICE:
            case E_USER_NOTICE:
                return 'NOTICE';
            case E_USER_ERROR:
                return 'ERROR';
            case E_USER_WARNING:
                return 'WARNING';
            default:
                return 'INFO';
        }
    }
    
    /**
     * Show error page
     */
    private static function showErrorPage($code, $title, $message) {
        // Ensure BASE_URL is defined first (must be set in .env file)
        if (!defined('BASE_URL')) {
            if (!isset($_ENV['APP_URL']) || empty($_ENV['APP_URL'])) {
                // Fallback for error page rendering - use a minimal URL
                $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
                $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                define('BASE_URL', $protocol . '://' . $host);
            } else {
                define('BASE_URL', $_ENV['APP_URL']);
            }
        }
        
        http_response_code($code);
        
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=UTF-8');
        }
        
        // Don't show error page if we're in an API request
        $isApiRequest = false;
        try {
            if (class_exists('\App\Core\RequestTypeDetector')) {
                $isApiRequest = \App\Core\RequestTypeDetector::isAPIRequest();
            }
        } catch (\Throwable $e) {
            // Fallback detection if RequestTypeDetector fails
            $uri = $_SERVER['REQUEST_URI'] ?? '';
            $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
            $acceptHeader = $_SERVER['HTTP_ACCEPT'] ?? '';
            $requestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
            
            $isApiRequest = (
                strpos($uri, '/api/') !== false ||
                ((strpos($uri, '/qodmin/shifts/') !== false || strpos($uri, '/business/shifts/') !== false) && $_SERVER['REQUEST_METHOD'] === 'POST') ||
                strpos($contentType, 'application/json') !== false ||
                strpos($acceptHeader, 'application/json') !== false ||
                strtolower($requestedWith) === 'xmlhttprequest'
            );
        }
        
        if ($isApiRequest) {
            try {
                \App\Core\ApiResponseHelper::send([
                    'success' => false,
                    'error' => $title,
                    'message' => $message,
                    'code' => $code
                ], $code);
            } catch (\Throwable $e) {
                // If ApiResponseHelper fails, send basic JSON response
                http_response_code($code);
                if (!headers_sent()) {
                    header('Content-Type: application/json; charset=utf-8');
                }
                echo json_encode([
                    'success' => false,
                    'error' => $title,
                    'message' => $message,
                    'code' => $code
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
        }
        
        // Check if we're in development mode for detailed error messages
        // Get APP_ENV and APP_DEBUG from database instead of .env
        try {
            $settingsService = \App\Core\DependencyFactory::getSystemSettingsService();
            $isProduction = ($settingsService->getAppEnv() === 'production');
            $isDebug = $settingsService->getAppDebug();
        } catch (\Exception $e) {
            // Fallback to .env if database is not available
            $isProduction = (isset($_ENV['APP_ENV']) && $_ENV['APP_ENV'] === 'production');
            $isDebug = (isset($_ENV['APP_DEBUG']) && ($_ENV['APP_DEBUG'] === 'true' || $_ENV['APP_DEBUG'] === '1'));
        }
        $showDetails = !$isProduction || $isDebug;
        
        // Get home URL based on user's authentication status and role
        $homeUrl = BASE_URL . '/';
        $isAuthenticated = false;
        
        try {
            // Try to use SessionService to get proper home URL
            // Only if SessionService is available (may not be available in early error scenarios)
            if (class_exists('\App\Services\SessionService')) {
                $sessionService = \App\Services\SessionService::getInstance();
                if ($sessionService !== null) {
                    $homeUrl = $sessionService->getHomeUrl();
                    $isAuthenticated = $sessionService->isAuthenticated();
                } else {
                    // SessionService::getInstance() returned null, use fallback
                    $homeUrl = self::getRedirectUrlByRole();
                    \App\Core\SessionManager::ensureSession();
                    $isAuthenticated = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
                }
            } else {
                // Fallback: Check session directly if SessionService not available
                $homeUrl = self::getRedirectUrlByRole();
                \App\Core\SessionManager::ensureSession();
                $isAuthenticated = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
            }
        } catch (\Throwable $e) {
            // If SessionService fails, use fallback
            error_log("ErrorHandler: Failed to get home URL from SessionService: " . $e->getMessage());
            try {
                $homeUrl = self::getRedirectUrlByRole();
                \App\Core\SessionManager::ensureSession();
                $isAuthenticated = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
            } catch (\Throwable $e2) {
                // Final fallback - CRITICAL: Use current host (with subdomain) for redirect
                $currentHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $homeUrl = $protocol . '://' . $currentHost . '/login';
            }
        }
        
        // Show user-friendly error page
        $errorFile = __DIR__ . '/../views/errors/' . $code . '.php';
        if (file_exists($errorFile)) {
            // Extract file and line from message if available
            $errorFileInfo = '';
            $errorLineInfo = '';
            if (preg_match('/File:\s*(.+?)(?:\n|$)/', $message, $fileMatch)) {
                $errorFileInfo = trim($fileMatch[1]);
            }
            if (preg_match('/Line:\s*(\d+)/', $message, $lineMatch)) {
                $errorLineInfo = trim($lineMatch[1]);
            }
            
            // Check if user is super admin
            $isSuperAdmin = false;
            try {
                \App\Core\SessionManager::ensureSession();
                $sessionRole = \App\Core\SessionManager::get('role');
                $isSuperAdminSession = \App\Core\SessionManager::get('is_super_admin') === true;
                
                if ($isSuperAdminSession) {
                    $isSuperAdmin = true;
                } elseif ($sessionRole) {
                    $normalizedRole = strtoupper(trim($sessionRole));
                    $isSuperAdmin = ($normalizedRole === 'SUPER_ADMIN' || $normalizedRole === 'ROLE_SUPER_ADMIN' ||
                                   $normalizedRole === 'QODMIN' || $normalizedRole === 'ROLE_QODMIN');
                }
            } catch (\Exception $e) {
                // Ignore errors when checking super admin status
            }
            
            // Always pass error details to error page (errorMessage to avoid conflict with t() function)
            // Super admins should always see details, even in production
            $errorDetails = [
                'title' => $title,
                'errorMessage' => $message, // Use errorMessage to avoid conflict with t() function
                'errorFile' => $errorFileInfo,
                'errorLine' => $errorLineInfo,
                'show_details' => ($showDetails && $code === 500) || $isSuperAdmin, // Show details for 500 errors in debug mode OR for super admins
                'homeUrl' => $homeUrl, // Pass home URL to error page
                'isAuthenticated' => $isAuthenticated, // Pass authentication status
                'isSuperAdmin' => $isSuperAdmin // Pass super admin status
            ];
            extract($errorDetails);
            
            // Prevent class redeclaration by checking if classes are already loaded
            // Use a flag to prevent config.php from being loaded again in error pages
            // Define this BEFORE disabling autoloaders so config.php can check it
            if (!defined('ERROR_PAGE_LOADING')) {
                define('ERROR_PAGE_LOADING', true);
            }
            
            // Check if core classes are already loaded - if so, use simple error page
            $coreClassesLoaded = class_exists('App\Core\BaseRepository', false) || 
                                 class_exists('App\Core\BaseService', false) ||
                                 class_exists('App\Core\Model', false);
            
            // Get base URL for error page links
            $baseUrlForError = defined('BASE_URL') ? BASE_URL : '/';
            if (empty($baseUrlForError) || $baseUrlForError === '/') {
                $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
                $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                $scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
                if ($scriptDir === '/') {
                    $scriptDir = '';
                }
                $baseUrlForError = $protocol . '://' . $host . $scriptDir;
            }
            
            // If core classes are loaded, use simple error page to avoid redeclaration
            if ($coreClassesLoaded) {
                // Use simple error page without including complex error page file
                $errorHtml = "<!DOCTYPE html><html><head><title>{$code} - {$title}</title>";
                $errorHtml .= "<meta charset='UTF-8'><meta name='viewport' content='width=device-width, initial-scale=1'>";
                $errorHtml .= "<style>body{font-family:system-ui,-apple-system,sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;background:#f8fafc;}";
                $errorHtml .= ".container{text-align:center;padding:2rem;max-width:600px;}";
                $errorHtml .= "h1{font-size:4rem;font-weight:900;color:#1e293b;margin:0 0 1rem;}";
                $errorHtml .= "h2{font-size:1.5rem;font-weight:700;color:#475569;margin:0 0 1rem;}";
                $errorHtml .= "p{color:#64748b;margin:0 0 2rem;}";
                $errorHtml .= ".error-details{background:#fee2e2;border:1px solid #fecaca;border-radius:0.5rem;padding:1rem;margin:1rem 0;text-align:left;}";
                $errorHtml .= ".error-details pre{white-space:pre-wrap;word-wrap:break-word;font-size:0.875rem;color:#991b1b;}";
                $errorHtml .= "a{display:inline-block;background:#1e293b;color:white;padding:0.75rem 1.5rem;border-radius:0.5rem;text-decoration:none;font-weight:700;margin:0.5rem;}</style>";
                $errorHtml .= "</head><body><div class='container'>";
                $errorHtml .= "<h1>{$code}</h1><h2>" . htmlspecialchars($title) . "</h2>";
                
                if ($showDetails && $code === 500) {
                    $errorHtml .= "<div class='error-details'><pre>" . htmlspecialchars($message) . "</pre></div>";
                } else {
                    $errorHtml .= "<p>" . htmlspecialchars($message) . "</p>";
                }
                
                $homeLabel = $isAuthenticated ? 'Panele Dön' : 'Ana Sayfaya Dön';
                $errorHtml .= "<a href='" . htmlspecialchars($homeUrl) . "'>" . $homeLabel . "</a>";
                $errorHtml .= "<a href='javascript:location.reload()'>Yenile</a>";
                $errorHtml .= "</div></body></html>";
                echo $errorHtml;
                exit;
            }
            
            // Temporarily disable autoloader to prevent class redeclaration
            // This prevents any class from being loaded again during error page inclusion
            $autoloaders = spl_autoload_functions();
            $autoloadersBackup = [];
            if ($autoloaders !== false) {
                foreach ($autoloaders as $autoloader) {
                    $autoloadersBackup[] = $autoloader;
                    spl_autoload_unregister($autoloader);
                }
            }
            
            // Use output buffering to prevent class redeclaration issues
            ob_start();
            try {
                // Check if file path is valid before including
                if (empty($errorFile) || !is_string($errorFile) || !file_exists($errorFile)) {
                    // If error file doesn't exist, use fallback HTML instead of throwing error
                    ob_end_clean();
                    $errorHtml = "<!DOCTYPE html><html><head><title>{$code} - {$title}</title></head><body><h1>{$code} - {$title}</h1><p>" . htmlspecialchars($message) . "</p><a href='" . htmlspecialchars($homeUrl) . "'>Ana Sayfaya Dön</a></body></html>";
                    echo $errorHtml;
                    exit;
                }
                include $errorFile;
            } catch (\Throwable $e) {
                ob_end_clean();
                // Fallback to simple error display if error page itself fails
                // Check if the error is about class redeclaration
                if (strpos($e->getMessage(), 'Cannot declare class') !== false || 
                    strpos($e->getMessage(), 'already in use') !== false) {
                    // Class redeclaration error - show simple error without including file
                    echo "<!DOCTYPE html><html><head><title>{$code} - {$title}</title></head><body><h1>{$code} - {$title}</h1><p>" . htmlspecialchars($message) . "</p><p><small>Note: Error page could not be displayed due to class redeclaration.</small></p></body></html>";
                } else {
                    // Other error - show simple error display
                    echo "<!DOCTYPE html><html><head><title>{$code} - {$title}</title></head><body><h1>{$code} - {$title}</h1><p>" . htmlspecialchars($message) . "</p></body></html>";
                }
            } finally {
                // Restore autoloaders - ensure they are restored even if error occurs
                if (!empty($autoloadersBackup)) {
                    foreach ($autoloadersBackup as $autoloader) {
                        if ($autoloader !== false && $autoloader !== null) {
                            try {
                                spl_autoload_register($autoloader, true, false);
                            } catch (\Throwable $e) {
                                // Silently ignore autoloader registration errors
                            }
                        }
                    }
                }
            }
            ob_end_flush();
        } else {
            $errorHtml = "<!DOCTYPE html><html><head><title>{$code} - {$title}</title></head><body><h1>{$code} - {$title}</h1><p>" . htmlspecialchars($message);
            if ($showDetails && $code === 500) {
                $errorHtml .= "</p><pre style='background:#f5f5f5;padding:10px;border:1px solid #ddd;margin:10px 0;'>" . htmlspecialchars($message) . "</pre>";
            }
            $errorHtml .= "</p></body></html>";
            echo $errorHtml;
        }
        
        exit;
    }
    
    /**
     * Get redirect URL based on user's role
     * Uses the same logic as AuthController::getRedirectUrlByRoleFallback
     * @return string Full URL with BASE_URL prefix
     */
    private static function getRedirectUrlByRole(): string {
        // Ensure BASE_URL is defined
        if (!defined('BASE_URL')) {
            if (!isset($_ENV['APP_URL']) || empty($_ENV['APP_URL'])) {
                $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
                $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                define('BASE_URL', $protocol . '://' . $host);
            } else {
                define('BASE_URL', $_ENV['APP_URL']);
            }
        }
        
        try {
            \App\Core\SessionManager::ensureSession();
            
            // Check if user is authenticated
            if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
                // CRITICAL: Use current host (with subdomain) for redirect to preserve subdomain
                $currentHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                return $protocol . '://' . $currentHost . '/login';
            }
            
            // Get role from session
            $role = $_SESSION['role'] ?? '';
            if (empty($role)) {
                // CRITICAL: Use current host (with subdomain) for redirect to preserve subdomain
                $currentHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                return $protocol . '://' . $currentHost . '/login';
            }
            
            // Normalize role code (remove ROLE_ prefix if exists)
            $normalizedRole = strtoupper(str_replace('ROLE_', '', trim($role)));
            
            // Get table_id for CUSTOMER role
            $tableId = $_SESSION['table_id'] ?? null;
            
            // Role-based redirect mapping (same as AuthController::getRedirectUrlByRoleFallback)
            // SUPER_ADMIN and QODMIN redirect to /qodmin/dashboard
            // MANAGER, BUSINESS_MANAGER, ADMIN, ADMINISTRATOR redirect to /business/dashboard
            // WAITER, KITCHEN, CASHIER redirect to /business/{role}/dashboard
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
                'CUSTOMER' => $tableId ? '/t/' . $tableId : '/menu'
            ];
            
            // Return role-specific URL or default to login
            $redirectPath = $roleRedirects[$normalizedRole] ?? '/login';
            // CRITICAL: Use current host (with subdomain) for redirect to preserve subdomain
            $currentHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            return $protocol . '://' . $currentHost . $redirectPath;
            
        } catch (\Throwable $e) {
            error_log("ErrorHandler::getRedirectUrlByRole error: " . $e->getMessage());
            // CRITICAL: Use current host (with subdomain) for redirect to preserve subdomain
            $currentHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            return $protocol . '://' . $currentHost . '/login';
        }
    }
    
    /**
     * Handle 404 errors
     * For API requests, returns JSON. For web requests, shows HTML error page.
     * @param string $message Error message
     */
    public static function handle404($message = 'Page not found') {
        // Check if this is an API request
        $isApiRequest = (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/api/') === 0) ||
                       (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) ||
                       (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false);
        
        if ($isApiRequest) {
            // API cevaplarında proje geneli ile tutarlı shape:
            //   { success: false, error: <msg>, code: 404 }
            // Eski sürüm `error: true, message: ...` döndürüyordu; bu shape
            // diğer API hataları (`ApiResponseHelper::error` + global exception
            // handler) ile uyuşmuyordu ve istemciler iki farklı format'a
            // bakmak zorunda kalıyordu.
            \App\Core\ApiResponseHelper::send([
                'success' => false,
                'error' => $message,
                'message' => $message,
                'code' => 404,
            ], 404);
        }
        self::showErrorPage(404, 'Not Found', $message);
    }
}

