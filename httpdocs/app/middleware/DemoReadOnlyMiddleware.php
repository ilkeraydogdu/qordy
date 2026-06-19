<?php
namespace App\Middleware;

use App\Core\ApiResponseHelper;
use App\Core\DependencyFactory;
use App\Core\SessionManager;

/**
 * Blocks mutating HTTP methods when session is a demo tenant (read-only showcase).
 */
class DemoReadOnlyMiddleware {

    /**
     * @param string $uri Request path (e.g. from Router dispatch)
     * @return bool true = allowed to continue, false = already exited with response
     */
    public static function handle(string $uri): bool {
        SessionManager::ensureSession();

        if (empty($_SESSION['is_demo'])) {
            return true;
        }

        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            return true;
        }

        $path = self::normalizePath($uri);

        if (self::isWhitelistedWrite($method, $path)) {
            return true;
        }

        self::logBlocked($path, $method);

        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $isApi = strpos($path, '/api/') === 0
            || strpos($accept, 'application/json') !== false
            || (isset($_SERVER['CONTENT_TYPE']) && stripos((string)$_SERVER['CONTENT_TYPE'], 'application/json') !== false);

        if ($isApi) {
            ApiResponseHelper::error(
                'Demo modunda veri değiştirilemez. Kendi işletmenizi oluşturmak için kayıt olun.',
                403,
                'DEMO_READ_ONLY'
            );
        }

        http_response_code(403);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Demo</title></head><body>';
        echo '<p>Demo modunda değişiklik yapılamaz. Gerçek hesap için kayıt olun.</p>';
        echo '</body></html>';
        exit;
    }

    public static function normalizePath(string $uri): string {
        $path = parse_url($uri, PHP_URL_PATH);
        if ($path === null || $path === false) {
            $path = $uri;
        }
        if (preg_match('#^/(tr|en)(/|$)#', $path)) {
            $path = preg_replace('#^/(tr|en)#', '', $path);
            if ($path === '' || $path === null) {
                $path = '/';
            }
        }
        return $path;
    }

    /**
     * @param string $method POST|PUT|PATCH|DELETE
     */
    private static function isWhitelistedWrite(string $method, string $path): bool {
        $needPost = ['POST'];
        $anyWrite = ['POST', 'PUT', 'PATCH', 'DELETE'];

        $rules = [
            ['/logout', $anyWrite],
            ['/staff/login', $needPost],
            ['/login', $needPost],
            ['/qodmin/login', $needPost],
            ['/register', $needPost],
            ['/auth/2fa/verify', $needPost],
            ['/api/mobile/staff/login', $needPost],
            ['/api/mobile/manager/login', $needPost],
            ['/api/mobile/logout', $anyWrite],
            ['/api/mobile/validate-subdomain', $needPost],
            ['/api/mobile/validate-manager-email', $needPost],
            ['/api/mobile/verify-token', $needPost],
            ['/api/mobile/register', $needPost],
            ['/api/mobile/register/send-email-code', $needPost],
            ['/api/mobile/register/verify-email', $needPost],
            ['/api/mobile/register/send-phone-code', $needPost],
            ['/api/mobile/register/verify-phone', $needPost],
            ['/api/register/send-email-code', $needPost],
            ['/api/register/verify-email', $needPost],
            ['/api/register/send-phone-code', $needPost],
            ['/api/register/verify-phone', $needPost],
        ];

        foreach ($rules as $rule) {
            list($prefix, $methods) = $rule;
            if (!in_array($method, $methods, true)) {
                continue;
            }
            if ($path === $prefix || strpos($path, $prefix . '/') === 0) {
                return true;
            }
        }

        return false;
    }

    private static function logBlocked(string $path, string $method): void {
        try {
            $cid = \App\Core\TenantResolver::resolve();
            if (!$cid) {
                return;
            }
            $fw = \App\Middleware\SecurityMiddleware::getFirewall();
            $ip = $fw ? $fw->getClientIP() : ($_SERVER['REMOTE_ADDR'] ?? '');
            $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
            DependencyFactory::getDemoAccessLogRepository()->log(
                $cid,
                $_SESSION['user_id'] ?? null,
                $ip,
                $ua,
                $method,
                $path,
                'blocked_write'
            );
        } catch (\Throwable $e) {
            // ignore
        }
    }
}
