<?php
namespace App\Middleware;

use App\Core\SessionManager;

/**
 * Read-only middleware.
 *
 * Grace period'da kullanıcılar sistemi görebilir ama değiştiremez.
 * Bu middleware yazma (mutation) isteklerini 403 ile reddeder.
 *
 * Çalışma mantığı:
 *  - $_SESSION['read_only'] === true ise:
 *       POST / PUT / PATCH / DELETE istekleri 403 döner
 *       GET / HEAD / OPTIONS istekleri geçer
 *  - İzin verilen yollar (ödeme akışı vs) istisnadır.
 *
 * TrialMiddleware'den SONRA çağrılmalıdır.
 */
class ReadonlyMiddleware {

    /** HTTP yöntemleri mutasyon sayılanlar */
    private const WRITE_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    /** Salt okunur modda bile yazmaya izin verilen path parçaları */
    private const ALLOWED_WRITE_PATHS = [
        '/customer/packages',       // paket satın alma
        '/customer/payment',        // ödeme akışı
        '/api/mobile/packages/purchase',
        '/api/mobile/payment',
        '/logout',
        '/api/mobile/logout',
        '/api/mobile/subscription/status',
    ];

    /**
     * @return bool true = request geçer, false = 403 döner ve exit eder
     */
    public static function handle(): bool {
        SessionManager::ensureSession();

        $readOnly = !empty($_SESSION['read_only']);
        if (!$readOnly) return true;

        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if (!in_array($method, self::WRITE_METHODS, true)) return true;

        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $path = parse_url($uri, PHP_URL_PATH) ?? $uri;
        // Güvenlik: strpos !== false URL'nin ORTASINDA geçen eşleşmeyi
        // de true yapar (ör. /attacker?next=/customer/packages). Sadece
        // path prefix eşleşmesi kabul edilir.
        foreach (self::ALLOWED_WRITE_PATHS as $allow) {
            if (strncmp($path, $allow, strlen($allow)) === 0) {
                // Eşleşme ya tam path ya da path segment (sonrası '/' veya '?')
                $next = $path[strlen($allow)] ?? '';
                if ($next === '' || $next === '/' || $next === '?') {
                    return true;
                }
            }
        }

        self::denyResponse();
        return false;
    }

    private static function denyResponse(): void {
        $phase = $_SESSION['subscription_phase'] ?? ($_SESSION['subscription_status'] ?? 'grace');
        $message = ($phase === 'suspended')
            ? 'İşletmeniz ödeme sebebiyle askıya alınmıştır. Değişiklik yapabilmek için paket yenileyin.'
            : 'Aboneliğiniz askıya alındı. Sadece okuma modundasınız — değişiklik yapabilmek için paket satın alın.';

        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $isJson = (strpos($accept, 'application/json') !== false)
               || (strpos($uri, '/api/') !== false);

        http_response_code(403);
        if ($isJson) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'error' => 'read_only_mode',
                'phase' => $phase,
                'message' => $message,
            ]);
        } else {
            header('Content-Type: text/html; charset=utf-8');
            $safe = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
            echo "<!doctype html><meta charset='utf-8'><title>Salt okunur mod</title>"
               . "<div style='font-family:sans-serif;padding:32px;max-width:640px;margin:40px auto;"
               . "border:1px solid #fde68a;background:#fffbeb;border-radius:12px;color:#92400e;'>"
               . "<h2 style='margin-top:0'>Salt okunur mod</h2><p>{$safe}</p>"
               . "<p><a href='/customer/packages' style='color:#b45309;font-weight:600'>Paketleri Gör →</a></p>"
               . "</div>";
        }
        exit;
    }
}
