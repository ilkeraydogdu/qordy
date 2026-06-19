<?php
namespace App\Middleware;

class SecurityHeadersMiddleware {
    private static $config = null;
    private static $corsConfig = null;
    
    public static function init(): void {
        if (self::$config === null) {
            // Load security config
            $securityConfig = include __DIR__ . '/../config/security.php';
            self::$config = $securityConfig['security_headers'] ?? [
                'enabled' => true,
                'x_frame_options' => 'DENY',
                'x_content_type_options' => 'nosniff',
                'x_xss_protection' => '1; mode=block',
                'content_security_policy' => "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.tailwindcss.com https://cdnjs.cloudflare.com https://cdn.jsdelivr.net https://www.googletagmanager.com https://cdn.iyzipay.com https://*.iyzipay.com https://*.iyzico.com https://*.hotjar.com https://static.hotjar.com https://script.hotjar.com https://*.sentry.io; script-src-elem 'self' 'unsafe-inline' https://cdn.tailwindcss.com https://cdnjs.cloudflare.com https://cdn.jsdelivr.net https://www.googletagmanager.com https://cdn.iyzipay.com https://*.iyzipay.com https://*.iyzico.com https://*.hotjar.com https://static.hotjar.com https://script.hotjar.com https://*.sentry.io; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.tailwindcss.com https://*.iyzipay.com https://*.hotjar.com; font-src 'self' data: https://fonts.gstatic.com https://cdnjs.cloudflare.com https://*.iyzipay.com https://*.hotjar.com; img-src 'self' data: blob: https:; connect-src 'self' https: https://www.google-analytics.com https://www.googletagmanager.com https://analytics.google.com https://*.iyzipay.com https://*.iyzico.com https://*.hotjar.com wss://*.hotjar.com https://*.sentry.io wss://qordy.com wss://*.qordy.com; media-src 'self' data: blob: https://assets.mixkit.co; object-src 'none'; frame-src 'self' https:; child-src 'self' https:; frame-ancestors 'self' https://qordy.com https://*.qordy.com; base-uri 'self'; form-action 'self' https: data:; worker-src 'self' blob:;",
                'strict_transport_security' => 'max-age=31536000; includeSubDomains',
                'referrer_policy' => 'strict-origin-when-cross-origin',
                'permissions_policy' => 'geolocation=(), microphone=(), camera=()'
            ];
        }
        
        if (self::$corsConfig === null) {
            self::$corsConfig = include __DIR__ . '/../config/cors.php';
        }
    }
    
    /**
     * Handle CORS preflight requests
     */
    private static function handleCorsPreflight(): void {
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
            self::setCorsHeaders();
            http_response_code(200);
            exit;
        }
    }
    
    /**
     * Set CORS headers
     */
    private static function setCorsHeaders(): void {
        if (!self::$corsConfig['enabled']) {
            return;
        }
        
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        $allowedOrigins = self::$corsConfig['allowed_origins'] ?? [];
        $allowAllOrigins = self::$corsConfig['allow_all_origins'] ?? false;
        
        // Determine if origin is allowed
        $isAllowed = $allowAllOrigins || in_array($origin, $allowedOrigins);
        
        if ($isAllowed && $origin) {
            header('Access-Control-Allow-Origin: ' . $origin);
        } elseif ($allowAllOrigins) {
            header('Access-Control-Allow-Origin: *');
        }
        
        if (self::$corsConfig['allow_credentials'] ?? false) {
            header('Access-Control-Allow-Credentials: true');
        }
        
        $allowedMethods = implode(', ', self::$corsConfig['allowed_methods'] ?? ['GET', 'POST']);
        header('Access-Control-Allow-Methods: ' . $allowedMethods);
        
        $allowedHeaders = implode(', ', self::$corsConfig['allowed_headers'] ?? []);
        header('Access-Control-Allow-Headers: ' . $allowedHeaders);
        
        if (!empty(self::$corsConfig['exposed_headers'])) {
            $exposedHeaders = implode(', ', self::$corsConfig['exposed_headers']);
            header('Access-Control-Expose-Headers: ' . $exposedHeaders);
        }
        
        $maxAge = self::$corsConfig['max_age'] ?? 86400;
        header('Access-Control-Max-Age: ' . $maxAge);
    }
    
    public static function handle(): bool {
        self::init();
        
        // Handle CORS preflight
        self::handleCorsPreflight();
        
        // Set CORS headers for all requests
        self::setCorsHeaders();
        
        if (!self::$config['enabled']) {
            return true;
        }
        
        // Public queue pages (/sira, /api/sira) are intentionally iframed by the
        // admin panel across qordy.com subdomains for the live preview card.
        // Relax framing headers for those URIs.
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
        $isFramableQueuePath = (bool) preg_match('#^/(sira|api/sira)(/|\?|$)#', $uri);

        // Set security headers
        if (isset(self::$config['x_frame_options']) && !$isFramableQueuePath) {
            header('X-Frame-Options: ' . self::$config['x_frame_options']);
        }
        
        if (isset(self::$config['x_content_type_options'])) {
            header('X-Content-Type-Options: ' . self::$config['x_content_type_options']);
        }
        
        if (isset(self::$config['x_xss_protection'])) {
            header('X-XSS-Protection: ' . self::$config['x_xss_protection']);
        }
        
        if (isset(self::$config['content_security_policy'])) {
            $csp = self::$config['content_security_policy'];
            if ($isFramableQueuePath) {
                $csp = preg_replace(
                    "/frame-ancestors[^;]*;?/",
                    "frame-ancestors 'self' https://qordy.com https://*.qordy.com;",
                    $csp
                );
            }
            header('Content-Security-Policy: ' . $csp);
        }
        
        // Only set HSTS if HTTPS
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' && isset(self::$config['strict_transport_security'])) {
            header('Strict-Transport-Security: ' . self::$config['strict_transport_security']);
        }
        
        if (isset(self::$config['referrer_policy'])) {
            header('Referrer-Policy: ' . self::$config['referrer_policy']);
        }
        
        if (isset(self::$config['permissions_policy'])) {
            header('Permissions-Policy: ' . self::$config['permissions_policy']);
        }
        
        return true;
    }
}

