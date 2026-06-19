<?php
namespace App\Core;

class SessionManager {
    private static $initialized = false;
    private static $redisHandler = null;
    
    /**
     * Reset initialized flag (used when session is manually cleared)
     */
    public static function resetInitialized(): void {
        self::$initialized = false;
    }

    public static function ensureSession(bool $skipValidation = false): void {
        // Ensure helper functions are loaded
        require_once __DIR__ . '/HelperLoader.php';
        HelperLoader::ensureLoaded();

        // Check if session needs to be started
        if (session_status() === PHP_SESSION_NONE) {
            // Configure secure session settings
            self::configureSession();
            
            // Try to use Redis session handler if available
            self::initializeRedisHandler();
            
            session_start();
            self::$initialized = true;
            
            // Regenerate session ID to prevent session fixation
            if (!isset($_SESSION['created'])) {
                session_regenerate_id(true);
                $_SESSION['created'] = time();
            }
        }
        
        // CRITICAL: ALWAYS check for invalid roles IMMEDIATELY after session start
        // This MUST happen before ANY other logic to prevent redirect loops
        // Invalid roles like "SUBDOMAIN" cause infinite redirect loops
        if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
            $role = $_SESSION['role'] ?? null;
            $roleId = $_SESSION['role_id'] ?? null;
            require_once __DIR__ . '/Validators/RoleValidator.php';
            $isValidRole = empty($role) || \App\Core\Validators\RoleValidator::isValid($role);
            
            // If role is invalid and no valid roleId, clear session IMMEDIATELY
            // This MUST happen before any redirect logic to prevent loops
            if (!empty($role) && !$isValidRole && empty($roleId)) {
                // Log before clearing
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::warning("ensureSession: CRITICAL - Clearing session with invalid role to prevent redirect loop", [
                        'invalid_role' => $role,
                        'normalized_role' => \App\Core\Validators\RoleValidator::normalize($role ?? ''),
                        'skip_validation' => $skipValidation,
                        'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
                        'logged_in' => $_SESSION['logged_in'] ?? false
                    ]);
                }
                
                // CRITICAL: Clear session completely
                session_destroy();
                session_start();
                $_SESSION = [];
                $_SESSION['logged_in'] = false;
                self::$initialized = false;
                
                // IMPORTANT: Don't redirect here - just clear session and return
                // Any redirect would cause a loop
                return;
            }
        }
        
        // Always validate session after ensuring it's started
        // This ensures validation happens even if session was already started
        // BUT skip validation if requested (e.g., during Authorization initialization to prevent redirect loops)
        if (self::$initialized && !$skipValidation) {
            self::validateSession();
        }
    }
    
    /**
     * Initialize Redis session handler if available
     */
    private static function initializeRedisHandler(): void {
        // Check if Redis extension is available
        if (!extension_loaded('redis')) {
            return; // Fallback to default PHP session handler
        }

        // Resolve SESSION_DRIVER from both $_ENV (loaded via phpdotenv) and
        // getenv() (populated on some PHP-FPM setups). The codebase used to
        // only look at $_ENV which could be empty in production causing all
        // sessions to fall back to local file storage.
        $useRedisSession = $_ENV['SESSION_DRIVER'] ?? getenv('SESSION_DRIVER') ?: 'php';
        if ($useRedisSession !== 'redis') {
            return; // Use PHP default session handler
        }
        
        try {
            // Load cache config to get Redis settings
            $cacheConfig = require __DIR__ . '/../config/cache.php';
            
            if ($cacheConfig['driver'] !== 'redis') {
                return; // Redis not configured for cache, skip session handler
            }
            
            // Create Redis session handler with session-specific config
            require_once __DIR__ . '/Session/RedisSessionHandler.php';
            
            $sessionConfig = [
                'host' => $cacheConfig['redis']['host'],
                'port' => $cacheConfig['redis']['port'],
                'password' => $cacheConfig['redis']['password'],
                'database' => $_ENV['REDIS_SESSION_DATABASE'] ?? getenv('REDIS_SESSION_DATABASE') ?? 1,
                'timeout' => $cacheConfig['redis']['timeout'],
                'prefix' => $_ENV['SESSION_PREFIX'] ?? getenv('SESSION_PREFIX') ?? 'session:',
                'ttl' => self::getSessionLifetime(),
            ];
            
            self::$redisHandler = new \App\Core\Session\RedisSessionHandler($sessionConfig);
            
            // Set the custom session handler
            session_set_save_handler(self::$redisHandler, true);
        } catch (\Exception $e) {
            // Log error but continue with default handler
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::warning("Failed to initialize Redis session handler: " . $e->getMessage());
            }
            // Fallback to default PHP session handler
        }
    }
    
    /**
     * Get session lifetime from settings
     */
    private static function getSessionLifetime(): int {
        try {
            $settingsService = \App\Core\DependencyFactory::getSystemSettingsService();
            return $settingsService->getSessionLifetime();
        } catch (\Exception $e) {
            return 8 * 60 * 60; // Default: 8 hours
        }
    }
    
    /**
     * Configure secure session settings
     * Loads settings from database instead of hardcoded values
     */
    private static function configureSession(): void {
        // Defaults are Lax (not Strict) so same-site 302 redirects after a
        // POST login reliably carry the session cookie. `Strict` is known to
        // drop the cookie on cross-page navigations in some browsers which
        // caused the "PIN correct but bounced to /login" bug we've seen
        // repeatedly in production.
        $envLifetime   = isset($_ENV['SESSION_LIFETIME']) ? (int)$_ENV['SESSION_LIFETIME'] : 0;
        $envSecure     = isset($_ENV['SESSION_SECURE_COOKIE'])
            ? in_array(strtolower((string)$_ENV['SESSION_SECURE_COOKIE']), ['1', 'true', 'yes'], true)
            : isHttps();
        $envHttpOnly   = isset($_ENV['SESSION_HTTP_ONLY'])
            ? in_array(strtolower((string)$_ENV['SESSION_HTTP_ONLY']), ['1', 'true', 'yes'], true)
            : true;
        $envSameSite   = isset($_ENV['SESSION_SAME_SITE']) && !empty($_ENV['SESSION_SAME_SITE'])
            ? (string)$_ENV['SESSION_SAME_SITE']
            : 'Lax';

        $sessionLifetime = $envLifetime;
        $sessionSecure   = $envSecure;
        $sessionHttpOnly = $envHttpOnly;
        $sessionSameSite = $envSameSite;

        try {
            $settingsService = \App\Core\DependencyFactory::getSystemSettingsService();
            $sessionLifetime = $settingsService->getSessionLifetime();
            $sessionSecure = $settingsService->getSessionSecureCookie();
            $sessionHttpOnly = $settingsService->getSessionHttpOnly();
            $dbSameSite = $settingsService->getSessionSameSite();
            if (!empty($dbSameSite)) {
                $sessionSameSite = $dbSameSite;
            }
        } catch (\Exception $e) {
            // DB not reachable: keep the env-driven defaults above.
        }

        // Normalise SameSite and coerce legacy "Strict" configs back to
        // "Lax" for the login flow — Strict is aggressive enough to break
        // subdomain login on several Android/Chrome combos.
        $sameSiteNorm = ucfirst(strtolower((string)$sessionSameSite));
        if (!in_array($sameSiteNorm, ['Lax', 'Strict', 'None'], true)) {
            $sameSiteNorm = 'Lax';
        }
        if ($sameSiteNorm === 'Strict') {
            $sameSiteNorm = 'Lax';
        }
        $sessionSameSite = $sameSiteNorm;
        
        // Override secure setting if HTTPS is detected (security first)
        if (isHttps()) {
            $sessionSecure = true;
        }
        
        // CRITICAL: Each subdomain should have its OWN session for proper isolation
        // Do NOT use .qordy.com as it shares sessions across all subdomains
        // This prevents login confusion between qordy.com and subdomain.qordy.com
        $cookieDomain = '';
        $host = $_SERVER['HTTP_HOST'] ?? '';
        // Use specific hostname, not wildcard domain
        // This ensures qordy.com and caddecafe.qordy.com have separate sessions
        if (strpos($host, 'qordy.com') !== false) {
            $cookieDomain = $host; // Use specific hostname, not .qordy.com
        }
        
        $cookieParams = [
            'lifetime' => $sessionLifetime,
            'path' => '/',
            'domain' => $cookieDomain,
            'secure' => $sessionSecure,
            'httponly' => $sessionHttpOnly,
            'samesite' => $sessionSameSite
        ];
        
        // CRITICAL: Must set cookie params BEFORE session_start()
        // Set session cookie domain for cross-subdomain access
        if (!empty($cookieDomain)) {
            ini_set('session.cookie_domain', $cookieDomain);
        }
        ini_set('session.cookie_path', $cookieParams['path']);
        ini_set('session.cookie_lifetime', $cookieParams['lifetime']);
        ini_set('session.cookie_secure', $cookieParams['secure'] ? '1' : '0');
        ini_set('session.cookie_httponly', $cookieParams['httponly'] ? '1' : '0');
        
        // Also use session_set_cookie_params for PHP 7.3+
        if (PHP_VERSION_ID >= 70300) {
            session_set_cookie_params($cookieParams);
        } else {
            session_set_cookie_params(
                $cookieParams['lifetime'],
                $cookieParams['path'],
                $cookieParams['domain'],
                $cookieParams['secure'],
                $cookieParams['httponly']
            );
        }
        
        // Set additional session security options
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.cookie_samesite', $sessionSameSite);
    }
    
    /**
     * Validate session to prevent hijacking
     */
    private static function validateSession(): void {
        // Skip ALL validation on login/auth pages and root URL to prevent redirect loops
        // This must be checked FIRST before any session validation
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        $parsedUri = parse_url($requestUri, PHP_URL_PATH);
        $path = $parsedUri ?: $requestUri;
        
        // Check if this is login page, auth page, or root URL (which redirects to login)
        // Also check waiter/pos and other waiter routes to prevent redirect loops
        $isLoginPage = $path === '/' || 
                      $path === '' || 
                      strpos($path, '/login') !== false || 
                      strpos($path, '/auth/') !== false ||
                      strpos($path, '/waiter/pos') !== false ||
                      strpos($path, '/waiter/') !== false;
        
        // Check if session has invalid role (like "SUBDOMAIN") - clear it even on login page
        // This prevents redirect loops when invalid role is in session
        if ($isLoginPage) {
            $isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
            if ($isLoggedIn) {
                $role = $_SESSION['role'] ?? null;
                $roleId = $_SESSION['role_id'] ?? null;
                require_once __DIR__ . '/Validators/RoleValidator.php';
                $isValidRole = empty($role) || \App\Core\Validators\RoleValidator::isValid($role);
                
                // If role is invalid and no valid roleId, clear session immediately
                if (!empty($role) && !$isValidRole && empty($roleId)) {
                    if (class_exists('\App\Core\Logger')) {
                        // Düzeltme: $normalizedRole tanımsızdı — Logger'a
                        // undefined variable gidiyordu. RoleValidator ile
                        // normalize et.
                        $normalizedRole = \App\Core\Validators\RoleValidator::normalize(is_string($role) ? $role : '');
                        \App\Core\Logger::warning("Login page: Clearing session with invalid role", [
                            'invalid_role' => $role,
                            'normalized_role' => $normalizedRole
                        ]);
                    }
                    session_destroy();
                    session_start();
                    $_SESSION = [];
                    $_SESSION['logged_in'] = false;
                    self::$initialized = false;
                    // Don't redirect - just clear session and continue to show login page
                    return;
                }
            }
            
            // On login page or root URL, skip all other validation - allow new device login
            return;
        }
        
        // Check if session is new or needs validation
        if (!isset($_SESSION['ip_address']) || !isset($_SESSION['user_agent'])) {
            // First time - store IP and User-Agent
            $_SESSION['ip_address'] = self::getClientIP();
            $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $_SESSION['last_activity'] = time();
            return;
        }
        
        // Only validate IP and session invalidation for authenticated users
        $isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
        
        if (!$isLoggedIn) {
            // Not logged in - no need to validate IP or session invalidation
            return;
        }
        
        // Check if role or role_id is missing - if so, try to reload from database first
        $role = $_SESSION['role'] ?? null;
        $roleId = $_SESSION['role_id'] ?? null;
        $userId = $_SESSION['user_id'] ?? null;
        
        // Use centralized role validator to prevent redirect loops with invalid roles like "SUBDOMAIN"
        require_once __DIR__ . '/Validators/RoleValidator.php';
        
        // If role exists but is invalid (like "SUBDOMAIN"), clear session immediately to prevent redirect loop
        if ($isLoggedIn && \App\Core\Validators\RoleValidator::shouldClear($role, $roleId)) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::warning("Session invalidated: Invalid role detected", [
                    'user_id' => $userId ?? 'unknown',
                    'invalid_role' => $role,
                    'normalized_role' => \App\Core\Validators\RoleValidator::normalize($role ?? '')
                ]);
            }
            
            // Destroy session completely and start fresh
            session_destroy();
            session_start();
            $_SESSION = [];
            $_SESSION['logged_in'] = false;
            self::$initialized = false;
            
            // Check if this is an API request
            $isApiRequest = strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false;
            
            if (!$isApiRequest) {
                // CRITICAL: Use current host (with subdomain) for redirect
                $currentHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                header('Location: ' . $protocol . '://' . $currentHost . '/login?session_invalid=1');
                exit;
            }
            return;
        }
        
        if (empty($role) && empty($roleId) && $isLoggedIn && $userId) {
            // Try to reload role from database before invalidating session
            // This prevents redirect loops when Authorization is initializing
            try {
                require_once __DIR__ . '/DependencyFactory.php';
                $roleService = \App\Core\DependencyFactory::getRoleService();
                
                // Try to get user's role from database
                require_once __DIR__ . '/../models/User.php';
                $userModel = new \App\Models\User();
                $userData = $userModel->findById($userId);
                
                if ($userData && !empty($userData['role'])) {
                    $rawRole = $userData['role'];
                    $roleData = $roleService->getByRoleCode($rawRole);
                    
                    if ($roleData) {
                        // Reload role successfully - update session
                        $_SESSION['role'] = $rawRole;
                        if (isset($roleData['role_id'])) {
                            $_SESSION['role_id'] = $roleData['role_id'];
                        }
                        
                        if (class_exists('\App\Core\Logger')) {
                            \App\Core\Logger::info("Session role reloaded from database", [
                                'user_id' => $userId,
                                'role' => $rawRole,
                                'role_id' => $roleData['role_id'] ?? null
                            ]);
                        }
                        
                        // Role reloaded successfully, continue validation
                        $role = $_SESSION['role'];
                        $roleId = $_SESSION['role_id'] ?? null;
                    }
                }
            } catch (\Exception $e) {
                // Failed to reload role - log and continue with invalidation
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::warning("Failed to reload role from database: " . $e->getMessage(), [
                        'user_id' => $userId ?? 'unknown'
                    ]);
                }
            }
        }
        
        // If still no role/role_id after reload attempt, invalidate session
        if (empty($role) && empty($roleId)) {
            // Session has logged_in but no role/role_id - invalid session
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::warning("Session invalidated: Missing role/role_id", [
                    'user_id' => $userId ?? 'unknown',
                    'logged_in' => $isLoggedIn,
                    'has_role' => !empty($role),
                    'has_role_id' => !empty($roleId)
                ]);
            }
            
            // Destroy session completely and start fresh
            session_destroy();
            session_start();
            // Ensure logged_in is false in new session to prevent redirect loops
            $_SESSION = [];
            $_SESSION['logged_in'] = false;
            self::$initialized = false;
            
            // Check if this is an API request
            $isApiRequest = strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false;
            
            if (!$isApiRequest) {
                // CRITICAL: Use current host (with subdomain) for redirect
                $currentHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                header('Location: ' . $protocol . '://' . $currentHost . '/login?session_invalid=1');
                exit;
            }
            return;
        }
        
        // Validate IP address - strict check: user must use same IP
        $currentIP = self::getClientIP();
        $storedIP = $_SESSION['ip_address'] ?? null;
        
        // Check if session was invalidated by login from another device (only if Redis available)
        if ($userId && $storedIP) {
            try {
                // Check if Redis extension is available before checking session invalidation
                if (extension_loaded('redis')) {
                    require_once __DIR__ . '/../services/AuthenticationService.php';
                    $authService = \App\Core\DependencyFactory::getAuthenticationService();
                    if ($authService->isSessionInvalidated($userId, $storedIP)) {
                        // Session was invalidated by login from another device
                        if (class_exists('\App\Core\Logger')) {
                            \App\Core\Logger::info("Session invalidated: User logged in from another device", [
                                'user_id' => $userId,
                                'ip' => $storedIP
                            ]);
                        }
                        
                    // Destroy session completely and start fresh
                    session_destroy();
                    session_start();
                    // Ensure logged_in is false in new session to prevent redirect loops
                    $_SESSION = [];
                    $_SESSION['logged_in'] = false;
                    self::$initialized = false;
                    
                    // Set flash message
                    require_once __DIR__ . '/../helpers/functions.php';
                    $toastService = getToastNotificationService();
                    $toastService->setFlash('info', 'auth.info.session_invalidated');
                    
                    // Check if this is an API request
                    $isApiRequest = strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false;
                    
                    if (!$isApiRequest) {
                        if (!defined('BASE_URL')) {
                            throw new \Exception('BASE_URL constant is not defined. Please check your configuration.');
                        }
                        // CRITICAL: Use current host (with subdomain) for redirect
                        $currentHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
                        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                        header('Location: ' . $protocol . '://' . $currentHost . '/login');
                        exit;
                    }
                    return;
                    }
                }
            } catch (\Exception $e) {
                // Redis not available or error - skip session invalidation check
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::debug("Session invalidation check skipped: " . $e->getMessage());
                }
            }
        }
        
        // IP validation is configurable and optional (disabled by default)
        // Only validate IP for authenticated users with valid roles if enabled
        // Skip IP validation if role is missing (session is invalid anyway)
        if ($isLoggedIn && (!empty($role) || !empty($roleId))) {
            // Load IP validation configuration
            $securityConfig = require __DIR__ . '/../config/security.php';
            $ipValidationConfig = $securityConfig['session_ip_validation'] ?? [
                'enabled' => false,
                'strict_mode' => false,
                'similarity_threshold' => 3,
                'bypass_new_login_seconds' => 10,
            ];
            
            // Skip IP validation if disabled
            if (!($ipValidationConfig['enabled'] ?? false)) {
                // IP validation disabled - store IP for logging but don't validate
                if (!$storedIP) {
                    $_SESSION['ip_address'] = self::getClientIP();
                }
                // Continue without IP validation
            } else {
                // IP validation enabled - perform validation
                // Use centralized IP helper for consistent IP comparison
                require_once __DIR__ . '/Helpers/IPHelper.php';
                
                // Bypass IP validation for newly logged in users (configurable)
                $bypassSeconds = $ipValidationConfig['bypass_new_login_seconds'] ?? 10;
                $loginTime = $_SESSION['login_time'] ?? null;
                $isNewLogin = $loginTime && (time() - $loginTime) <= $bypassSeconds;
                
                // If IP changed significantly, invalidate session
                // BUT skip this check for newly logged in users to prevent redirect loops
                // Also use IP similarity check to handle NAT/proxy scenarios
                if ($storedIP && !$isNewLogin) {
                    $strictMode = $ipValidationConfig['strict_mode'] ?? false;
                    $similarityThreshold = $ipValidationConfig['similarity_threshold'] ?? 3;
                    
                    // Check if IPs are similar (same network) or exactly the same
                    $ipsAreSimilar = \App\Core\Helpers\IPHelper::areIPsSimilar($storedIP, $currentIP, $similarityThreshold);
                    
                    // In strict mode, require exact match; otherwise allow similar IPs
                    $ipChanged = $strictMode 
                        ? ($currentIP !== $storedIP)
                        : (!$ipsAreSimilar && $currentIP !== $storedIP);
                    
                    if ($ipChanged) {
                        if (class_exists('\App\Core\Logger')) {
                            \App\Core\Logger::warning("Session invalidated: IP address changed", [
                                'stored_ip' => $storedIP,
                                'current_ip' => $currentIP,
                                'user_id' => $userId ?? 'unknown',
                                'login_time' => $loginTime,
                                'is_new_login' => $isNewLogin,
                                'ips_are_similar' => $ipsAreSimilar,
                                'strict_mode' => $strictMode
                            ]);
                        }
                        
                        // Destroy session completely and start fresh
                        session_destroy();
                        session_start();
                        // Ensure logged_in is false in new session to prevent redirect loops
                        $_SESSION = [];
                        $_SESSION['logged_in'] = false;
                        self::$initialized = false;
                        
                        // Check if this is an API request
                        $isApiRequest = strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false;
                        
                        if (!$isApiRequest) {
                            // Build dynamic URL to preserve subdomain
                            $currentHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
                            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                            $redirectUrl = $protocol . '://' . $currentHost . '/login';
                            
                            // Set flash message instead of showing redirect error
                            require_once __DIR__ . '/../helpers/functions.php';
                            $toastService = getToastNotificationService();
                            $toastService->setFlash('info', 'auth.info.session_invalidated');
                            header('Location: ' . $redirectUrl);
                            exit;
                        }
                        return;
                    }
                }
            }
            
            // If this is a new login, ensure IP is stored (in case it wasn't set during login)
            if ($isNewLogin && !$storedIP) {
                $_SESSION['ip_address'] = $currentIP;
                $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
            }
        }
        
        // Store IP if not set
        if (!$storedIP) {
            $_SESSION['ip_address'] = $currentIP;
        }
        
        // Validate User-Agent (more strict)
        $currentUserAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $storedUserAgent = $_SESSION['user_agent'];
        
        if ($currentUserAgent !== $storedUserAgent) {
            // User-Agent mismatch - potential hijacking
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::warning("Session User-Agent mismatch - potential hijacking", [
                    'stored_ua' => $storedUserAgent,
                    'current_ua' => $currentUserAgent,
                    'user_id' => $_SESSION['user_id'] ?? 'unknown'
                ]);
            }
            // Regenerate session ID and clear session data
            session_regenerate_id(true);
            $_SESSION = [];
            $_SESSION['ip_address'] = $currentIP;
            $_SESSION['user_agent'] = $currentUserAgent;
            $_SESSION['last_activity'] = time();
        }
        
        // Get session timeout from database
        $sessionTimeout = 24 * 60 * 60; // Default: 24 hours (restaurant/cafe should stay open)
        try {
            $settingsService = \App\Core\DependencyFactory::getSystemSettingsService();
            $sessionTimeout = $settingsService->getSessionLifetime();
        } catch (\Exception $e) {
            // Use default if database is not available
        }
        
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $sessionTimeout) {
            // Session expired - redirect to login instead of showing unauthorized page
            session_destroy();
            session_start();
            // Ensure logged_in is false in new session to prevent redirect loops
            $_SESSION = [];
            $_SESSION['logged_in'] = false;
            self::$initialized = false;
            
            // Check if this is an API request
            $isApiRequest = strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false;
            
            if (!$isApiRequest) {
                // Redirect to login page - preserve subdomain
                $currentHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $redirectUrl = $protocol . '://' . $currentHost . '/login?expired=1';
                header('Location: ' . $redirectUrl);
                exit;
            }
            return;
        }
        
        // Update last activity time
        $_SESSION['last_activity'] = time();
    }
    
    /**
     * Get client IP address (handles proxies)
     */
    private static function getClientIP(): string {
        // Use centralized IP helper for consistency
        require_once __DIR__ . '/Helpers/IPHelper.php';
        return \App\Core\Helpers\IPHelper::getClientIP();
    }

    public static function get(string $key, $default = null) {
        self::ensureSession();
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, $value): void {
        self::ensureSession();
        
        // CRITICAL: Validate role before setting it in session
        // This prevents invalid roles like "SUBDOMAIN" from being set
        if ($key === 'role' && !empty($value)) {
            require_once __DIR__ . '/Validators/RoleValidator.php';
            
            // Use centralized role validator (with RoleMapper fallback)
            if (!\App\Core\Validators\RoleValidator::validateWithMapper($value)) {
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::warning("SessionManager: Attempted to set invalid role", [
                        'invalid_role' => $value,
                        'normalized_role' => \App\Core\Validators\RoleValidator::normalize($value)
                    ]);
                }
                // Don't set invalid role - return early
                return;
            }
        }
        
        $_SESSION[$key] = $value;
    }

    public static function has(string $key): bool {
        self::ensureSession();
        return isset($_SESSION[$key]);
    }

    public static function remove(string $key): void {
        self::ensureSession();
        unset($_SESSION[$key]);
    }

    /**
     * Remove superadmin / panel keys that must not survive a new business login
     * (prevents viewing the previously selected tenant after registering or switching users).
     */
    public static function clearTenantSelectionOverflow(): void {
        self::ensureSession();
        unset(
            $_SESSION['selected_business_id'],
            $_SESSION['tenant_subdomain']
        );
    }

    /**
     * Canonical way to bind a tenant to the current session.
     * Sets all three legacy keys so every consumer sees the same value.
     */
    public static function setTenantSession(string $tenantId): void
    {
        self::ensureSession();
        self::clearTenantSelectionOverflow();
        $_SESSION['business_id']  = $tenantId;
        $_SESSION['customer_id']  = $tenantId;
        $_SESSION['tenant_id']    = $tenantId;
    }

    public static function destroy(): void {
        if (self::$initialized) {
            // Clear all session data
            $_SESSION = [];
            
            // Destroy session cookie
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(
                    session_name(),
                    '',
                    time() - 42000,
                    $params["path"],
                    $params["domain"],
                    $params["secure"],
                    $params["httponly"]
                );
            }
            
            session_destroy();
            self::$initialized = false;
        }
    }
    
    /**
     * Regenerate session ID (for security after login)
     */
    public static function regenerateId(): void {
        self::ensureSession();
        session_regenerate_id(true);
        $_SESSION['created'] = time();
    }
    
    /**
     * Check if session is expired
     */
    public static function isExpired(): bool {
        self::ensureSession();
        
        // Get session timeout from database
        $sessionTimeout = 24 * 60 * 60; // Default: 24 hours (restaurant/cafe should stay open)
        try {
            $settingsService = \App\Core\DependencyFactory::getSystemSettingsService();
            $sessionTimeout = $settingsService->getSessionLifetime();
        } catch (\Exception $e) {
            // Use default if database is not available
        }
        
        if (isset($_SESSION['last_activity'])) {
            return (time() - $_SESSION['last_activity']) > $sessionTimeout;
        }
        return false;
    }
}

