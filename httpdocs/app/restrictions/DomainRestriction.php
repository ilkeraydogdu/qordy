<?php
/**
 * Qordy Domain Restriction
 * - Sadece ana domain (qordy.com, www.qordy.com) erişilebilir
 * - Subdomain'ler için erişim kontrolü artık App/Core/Controller.php -> ensureTenantContext tarafından yapılıyor
 * - Oturum açmamış kullanıcılar için ana domainde admin/operasyonel rotalara erişim engellenir
 * - Oturum açmış kullanıcılar /business ve diğer rotalara erişebilir
 */
class DomainRestriction
{
    private const MAIN_DOMAIN = 'qordy.com';
    private const ALLOWED_HOSTS = ['qordy.com', 'www.qordy.com'];

    /** Ana domain'de engellenecek admin/operasyonel rotalar (oturum açmamışlar için) */
    private const BLOCKED_PATH_PREFIXES = [
        '/business',
        '/staff',
        '/qodmin',
        '/dashboard',
        '/waiter',
        '/kitchen',
        '/pos',
        '/admin',
        '/preparation',
        '/receipt',
    ];

    /** BLOCKED_PATH_PREFIXES içinde olup giriş için erişilebilir olması gereken rotalar */
    private const ALLOWED_AUTH_PATHS = [
        '/qodmin/login',  // /qodmin engelli ama Super Admin girişi buradan yapılır
    ];

    /** Oturum gerektirmeyen, harici servislerden gelen webhook rotaları */
    private const ALLOWED_WEBHOOK_PATHS = [
        '/api/webhook/meta',  // Meta (WhatsApp/Facebook) webhook - Meta sunucularından çağrılır
    ];

    public static function apply(): void
    {
        if (php_sapi_name() === 'cli') {
            return;
        }

        $host = strtolower($_SERVER['HTTP_HOST'] ?? '');
        $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
        $requestPath = parse_url($requestUri, PHP_URL_PATH) ?: '/';
        $requestPath = rtrim($requestPath, '/') ?: '/';

        // Engelli prefix'ler içindeki giriş sayfalarına izin ver
        if (self::isAllowedAuthPath($requestPath)) {
            return;
        }

        // Webhook rotaları - Meta gibi harici servisler oturum olmadan erişebilmeli
        if (self::isAllowedWebhookPath($requestPath)) {
            return;
        }

        // 1. Subdomain engelleme iptal edildi.
        // Subdomain'ler (örn: caddecafe.qordy.com) personel PIN girişi ve QR menü için gereklidir.
        // Güvenlik ve yetkilendirme kontrolleri Controller.php'deki ensureTenantContext metodunda yapılmaktadır.

        // 2. Ana domain'de admin panel engelleme (SADECE oturum açmamış kullanıcılar için)
        if (self::isMainDomain($host) && self::isBlockedPath($requestPath)) {
            // Oturum açmış kullanıcılara /business ve diğer rotalara izin ver
            if (self::isAuthenticatedUser()) {
                return; // Oturum açmışsa engelleme - devam et
            }
            // Oturum açmamış kullanıcıları ana sayfaya yönlendir
            header('Location: https://' . self::MAIN_DOMAIN . '/', true, 302);
            exit;
        }
    }

    private static function isMainDomain(string $host): bool
    {
        return in_array($host, self::ALLOWED_HOSTS, true);
    }

    private static function isAllowedAuthPath(string $path): bool
    {
        foreach (self::ALLOWED_AUTH_PATHS as $allowed) {
            if ($path === $allowed || strpos($path, $allowed . '?') === 0) {
                return true;
            }
        }
        return false;
    }

    private static function isAllowedWebhookPath(string $path): bool
    {
        foreach (self::ALLOWED_WEBHOOK_PATHS as $allowed) {
            if ($path === $allowed || strpos($path, $allowed . '?') === 0) {
                return true;
            }
        }
        return false;
    }

    private static function isBlockedPath(string $path): bool
    {
        foreach (self::BLOCKED_PATH_PREFIXES as $prefix) {
            if ($path === $prefix || strpos($path, $prefix . '/') === 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * Kullanıcının oturum açıp açmadığını kontrol et
     * Session'ı minimal şekilde başlatıp kontrol eder
     */
    private static function isAuthenticatedUser(): bool
    {
        try {
            if (session_status() === PHP_SESSION_NONE) {
                // Minimal session başlatma (güvenli, header'ı bozmadan)
                if (class_exists('\App\Core\SessionManager')) {
                    \App\Core\SessionManager::ensureSession(true);
                } else {
                    @session_start();
                }
            }
            return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
