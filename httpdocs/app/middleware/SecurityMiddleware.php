<?php
namespace App\Middleware;

require_once __DIR__ . '/../core/SecurityFirewall.php';
require_once __DIR__ . '/../core/SessionManager.php';
require_once __DIR__ . '/../core/RequestTypeDetector.php';
require_once __DIR__ . '/../config/security.php';

class SecurityMiddleware {
    private static $firewall = null;
    private static $config = null;
    
    public static function init(): void {
        if (self::$config === null) {
            self::$config = include __DIR__ . '/../config/security.php';
        }
        
        if (self::$firewall === null) {
            self::$firewall = new \App\Core\SecurityFirewall(self::$config);
        }
    }
    
    public static function handle(): bool {
        $path = $_SERVER['REQUEST_URI'] ?? '';
        $pathClean = parse_url($path, PHP_URL_PATH) ?: $path;

        // GÜVENLIK: /login, /register, /qodmin/login ARTIK bypass edilmez.
        // Bu endpoint'ler brute-force'a en açık noktalardır; rate limit + IP
        // block + SQL/XSS heuristics çalışmak ZORUNDA. Yalnızca gerçek
        // "anasayfa" tarzı statik landing'ler için hafif bypass uygula.
        $isLandingPage = $pathClean === '/' ||
                        strpos($pathClean, '/pricing') === 0 ||
                        strpos($pathClean, '/features') === 0 ||
                        strpos($pathClean, '/blog') === 0 ||
                        strpos($pathClean, '/sayfa/') === 0;

        // Sadece GET istekleri için landing bypass uygulanır — POST/PUT/DELETE
        // asla bypass edilmez (form spam / token hırsızlığı önlemi).
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($isLandingPage && $method === 'GET') {
            return true;
        }

        // Mobile API (Bearer token) için oturum kontrollerini atla AMA
        // rate limit + SQLi/XSS kontrollerini uygula (init'ten sonra).
        $isMobileApi = strpos($pathClean, '/api/mobile/') === 0 || strpos($pathClean, '/api/mobile') === 0;

        self::init();

        // Mobile API dışındaki istekler için oturum başlat.
        // Mobile API Bearer token kullanır, session gerektirmez.
        if (!$isMobileApi) {
            \App\Core\SessionManager::ensureSession();
        }

        $ip = self::$firewall->getClientIP();
        
        if (self::$config['ip_blocking']['enabled'] && self::$firewall->isIPBlocked($ip)) {
            \App\Core\ApiResponseHelper::error('Access denied. Your IP has been blocked.', 403, 'IP_BLOCKED');
        }
        
        // Bypass rate limiting for internal dashboard endpoints (authenticated users only)
        // These endpoints are used for real-time updates and should not be rate limited
        $bypassRateLimitPaths = [
            '/api/admin/dashboard-data',
            '/api/business/dashboard-data',
            '/api/qodmin/dashboard-data',
            '/api/admin/getDashboardData',
            '/api/notifications',
            '/api/tables',
            '/api/orders',
            '/kitchen/getOrders',
            '/kitchen/dashboard',
            '/pos/',
            '/waiter/'
        ];
        
        $shouldBypassRateLimit = false;
        foreach ($bypassRateLimitPaths as $bypassPath) {
            if (strpos($path, $bypassPath) !== false) {
                // Only bypass if user is authenticated (internal use only)
                $userId = self::getCurrentUserId();
                if ($userId) {
                    $shouldBypassRateLimit = true;
                    break;
                }
            }
        }
        
        // Apply rate limiting only if not bypassed
        if (!$shouldBypassRateLimit) {
            $rateLimitType = self::getRateLimitType();
            $rateLimitConfig = self::$config['rate_limits'][$rateLimitType] ?? self::$config['rate_limits']['default'];
            
            // For login endpoint, use IP-based identifier only
            // Each IP can only have one active session at a time
            $identifier = $ip;
            if (isset($rateLimitConfig['per_user']) && $rateLimitConfig['per_user'] && $rateLimitType !== 'login') {
                $userId = self::getCurrentUserId();
                if ($userId) {
                    $identifier = 'user_' . $userId;
                }
            }
            
            if (!self::$firewall->checkRateLimit($identifier, $rateLimitType)) {
                $remaining = self::$firewall->getRemainingRequests($identifier, $rateLimitType);
                $resetTime = self::$firewall->getRateLimiter()->getResetTime($identifier, $rateLimitType);
                
                $limit = isset($rateLimitConfig['per_user']) && $rateLimitConfig['per_user'] && $userId 
                    ? ($rateLimitConfig['user_requests'] ?? $rateLimitConfig['requests'])
                    : ($rateLimitConfig['requests'] ?? 60);
                
                $retryAfter = $resetTime - time();

                // Tek bir kriter ile "bu bir API isteği mi?" kararı verelim:
                // 1) Sorgu stringsiz PATH'te /api/ prefix var mı?
                // 2) Accept header'ı application/json mu? (SPA/AJAX çoğu zaman)
                // 3) RequestTypeDetector API olarak işaretliyor mu?
                $acceptHeader = $_SERVER['HTTP_ACCEPT'] ?? '';
                $isApiRequest = strpos($pathClean, '/api/') === 0
                    || (stripos($acceptHeader, 'application/json') !== false);
                if (!$isApiRequest && class_exists('\\App\\Core\\RequestTypeDetector')) {
                    try { $isApiRequest = \App\Core\RequestTypeDetector::isAPIRequest(); } catch (\Throwable $e) {
                        \App\Core\Logger::warning('SecurityMiddleware: RequestTypeDetector failed', [
                            'path' => $pathClean,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                // login kategorisinde OLSA BİLE, istek API ise HTML redirect yerine
                // JSON 429 dön. 2FA/verify gibi API endpoint'lerinin HTML login'e
                // atılmasını engeller (mobil + SPA uyumluluğu).
                if ($rateLimitType === 'login' && !$isApiRequest) {
                    \App\Core\SessionManager::ensureSession();
                    require_once __DIR__ . '/../helpers/functions.php';
                    $toastService = getToastNotificationService();
                    $toastService->setFlash('warning', 'auth.error.too_many_attempts', [
                        'seconds' => $retryAfter
                    ]);
                    // CRITICAL: Use current host (with subdomain) for redirect
                    $currentHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
                    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                    header('Location: ' . $protocol . '://' . $currentHost . '/login');
                    exit;
                }
                
                // For API requests, return user-friendly error message
                if ($isApiRequest) {
                    http_response_code(429);
                    header('X-RateLimit-Limit: ' . $limit);
                    header('X-RateLimit-Remaining: ' . $remaining);
                    header('X-RateLimit-Reset: ' . $resetTime);
                    header('Retry-After: ' . $retryAfter);
                    
                    // Use translation service for user-friendly message
                    require_once __DIR__ . '/../helpers/functions.php';
                    $translationService = getTranslationService();
                    $message = $translationService->translate('auth.error.too_many_attempts', null, ['seconds' => $retryAfter]);
                    if (!$message) {
                        $message = "Çok fazla deneme yaptınız. Lütfen {$retryAfter} saniye sonra tekrar deneyin.";
                    }
                    
                    \App\Core\ApiResponseHelper::error($message, 429, 'RATE_LIMIT_EXCEEDED', [
                        'retry_after' => $retryAfter
                    ]);
                } else {
                    // For other requests, redirect with message
                    \App\Core\SessionManager::ensureSession();
                    require_once __DIR__ . '/../helpers/functions.php';
                    $toastService = getToastNotificationService();
                    $toastService->setFlash('warning', 'auth.error.too_many_attempts', [
                        'seconds' => $retryAfter
                    ]);
                    // CRITICAL: Use current host (with subdomain) for redirect
                    $currentHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
                    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                    header('Location: ' . $protocol . '://' . $currentHost . '/login');
                    exit;
                }
            }
        }
        
        // CSRF Protection is now handled by CSRFMiddleware
        // This middleware focuses on other security aspects (rate limiting, IP blocking, etc.)
        
        if (self::$config['sql_injection_protection']['enabled']) {
            $inputs = array_merge($_GET, $_POST);
            foreach ($inputs as $key => $value) {
                if (is_string($value) && self::$firewall->detectSQLInjection($value)) {
                    self::$firewall->logSuspiciousActivity('SQL_INJECTION_ATTEMPT', "Field: {$key}", $ip);
                    
                    if (self::$config['sql_injection_protection']['strict_mode']) {
                        \App\Core\ApiResponseHelper::error('Invalid input detected.', 403, 'SQL_INJECTION_DETECTED');
                    }
                }
            }
        }
        
        if (self::$config['xss_protection']['enabled']) {
            $inputs = array_merge($_GET, $_POST);
            foreach ($inputs as $key => $value) {
                if (is_string($value) && self::$firewall->detectXSS($value)) {
                    self::$firewall->logSuspiciousActivity('XSS_ATTEMPT', "Field: {$key}", $ip);
                    
                    if (self::$config['xss_protection']['strict_mode'] ?? false) {
                        http_response_code(403);
                        \App\Core\ApiResponseHelper::error('Invalid input detected.', 403, 'SQL_INJECTION_DETECTED');
                        exit;
                    }
                }
            }
        }
        
        return true;
    }
    
    private static function getRateLimitType(): string {
        // ÖNEMLİ: Önceki sürüm `REQUEST_URI` üzerinde `strpos('/auth')` kullanıyordu.
        // Bu, `/api/mobile/auth/2fa/verify` gibi meşru JSON endpoint'lerini de
        // "login" bucket'ına düşürüyor ve limit aşıldığında HTML redirect'e yol
        // açıyordu. Artık:
        //   - REQUEST_URI yerine sadece PATH (query string hariç) kullanıyoruz,
        //   - Tam segment eşleşmesi (/login, /qodmin/login, /staff/login) yapıyoruz,
        //   - /api/* her zaman "api" bucket'ına gidiyor.
        $rawPath = $_SERVER['REQUEST_URI'] ?? '';
        $path = parse_url($rawPath, PHP_URL_PATH) ?: $rawPath;
        $path = '/' . ltrim($path, '/');

        if (strpos($path, '/api/') === 0) {
            return 'api';
        }

        // Yalnızca gerçek login form path'leri
        static $loginPaths = [
            '/login',
            '/register',
            '/qodmin/login',
            '/staff/login',
        ];
        if (in_array($path, $loginPaths, true)) {
            return 'login';
        }

        if (strpos($path, '/upload/') === 0 || $path === '/upload') {
            return 'upload';
        }

        return 'default';
    }
    
    /**
     * Check if route should bypass rate limiting
     * @param string $path
     * @return bool
     */
    private static function shouldBypassRateLimit(string $path): bool {
        // Login endpoint bypasses strict rate limiting (uses relaxed login rate limit)
        // This prevents legitimate users from being blocked
        return false; // We use relaxed login rate limit instead
    }
    
    private static function getCurrentUserId() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return $_SESSION['user_id'] ?? null;
    }
    
    public static function getFirewall(): \App\Core\SecurityFirewall {
        self::init();
        return self::$firewall;
    }
}

