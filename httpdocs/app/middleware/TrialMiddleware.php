<?php
namespace App\Middleware;

use App\Core\DependencyFactory;
use App\Core\SessionManager;

class TrialMiddleware {

    /**
     * Trial / Grace / Suspend durumunu kontrol et ve session'a işle.
     * business/* route'larında çağrılır.
     *
     * Fazlar:
     *  - active / trial       → geçişe izin ver
     *  - grace_period         → geçişe izin ver, session.read_only = true
     *                           (ReadonlyMiddleware yazma isteklerini 403 reddeder)
     *  - suspended / expired  → /trial/expired'a redirect (API path'leri hariç)
     *
     * @return array|null Phase bilgisi
     */
    public static function handle(): ?array {
        SessionManager::ensureSession();

        // Default: read-only kapalı
        $_SESSION['read_only'] = false;

        if (!empty($_SESSION['is_demo'])) {
            $_SESSION['is_trial'] = false;
            $_SESSION['trial_remaining_days'] = null;
            return null;
        }

        $customerId = \App\Core\TenantResolver::resolve();
        if (!$customerId) return null;

        $role = $_SESSION['role'] ?? '';
        if (in_array($role, ['SUPER_ADMIN', 'ADMIN'])) return null;

        try {
            $trialService = DependencyFactory::getTrialService();

            // Otomatik faz geçişleri (cron yedek: kullanıcı her request'te de tetikler)
            $trialService->checkAndExpireTrials();
            $trialService->checkAndSuspendGraceExpired();

            $phase = $trialService->getSubscriptionPhase($customerId);
            $phaseName = $phase['phase'] ?? 'none';

            // Session güncelle
            $_SESSION['subscription_phase'] = $phaseName;
            $_SESSION['trial_remaining_days'] = $phase['daysLeft'] ?? 0;
            $_SESSION['grace_remaining_days'] = $phase['graceDaysLeft'] ?? 0;
            $_SESSION['trial_ends_at'] = $phase['trial_ends_at'] ?? null;
            $_SESSION['grace_ends_at'] = $phase['grace_ends_at'] ?? null;
            $_SESSION['is_trial'] = ($phaseName === 'trial');
            $_SESSION['subscription_status'] = $phaseName;

            // Read-only: grace period'da yazma engellenir
            if (!empty($phase['readOnly']) && $phaseName === 'grace') {
                $_SESSION['read_only'] = true;
            }

            // Suspended / expired → paywall redirect
            if (in_array($phaseName, ['suspended', 'expired'], true)) {
                $_SESSION['read_only'] = true;

                $uri = $_SERVER['REQUEST_URI'] ?? '';
                $allowedPaths = [
                    '/trial/expired',
                    '/customer/packages',
                    '/customer/payment',
                    '/logout',
                    '/api/',
                    '/assets/',
                ];

                $isAllowed = false;
                foreach ($allowedPaths as $path) {
                    if (strpos($uri, $path) !== false) {
                        $isAllowed = true;
                        break;
                    }
                }

                if (!$isAllowed) {
                    $currentHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
                    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                    header('Location: ' . $protocol . '://' . $currentHost . '/trial/expired');
                    exit;
                }
            }

            return $phase;

        } catch (\Exception $e) {
            if (class_exists('\\App\\Core\\Logger')) {
                \App\Core\Logger::error('TrialMiddleware error', ['error' => $e->getMessage()]);
            }
            return null;
        }
    }

    /**
     * Trial / grace banner verisi (blade/layout için).
     */
    public static function getTrialBannerData(): ?array {
        $phase = $_SESSION['subscription_phase'] ?? ($_SESSION['subscription_status'] ?? null);
        if (!$phase || $phase === 'active' || $phase === 'none') return null;

        $remainingDays = (int)($_SESSION['trial_remaining_days'] ?? 0);
        $graceDaysLeft = (int)($_SESSION['grace_remaining_days'] ?? 0);

        if ($phase === 'suspended') {
            return [
                'type' => 'suspended',
                'message' => 'İşletmeniz ödeme sebebiyle askıya alındı. Erişimi yeniden etkinleştirmek için paketinizi yenileyin.',
                'cta_text' => 'Paketleri Gör',
                'cta_url' => '/customer/packages/list',
                'color' => 'red',
            ];
        }

        if ($phase === 'expired') {
            return [
                'type' => 'expired',
                'message' => 'Deneme süreniz sona erdi.',
                'cta_text' => 'Hemen Satın Al',
                'cta_url' => '/customer/packages/list',
                'color' => 'red',
            ];
        }

        if ($phase === 'grace') {
            return [
                'type' => 'grace',
                'message' => "Deneme süreniz doldu. {$graceDaysLeft} gün boyunca salt okunur olarak erişebilirsiniz. Değişiklik yapabilmek için paket satın alın.",
                'cta_text' => 'Hemen Satın Al',
                'cta_url' => '/customer/packages/list',
                'color' => 'orange',
                'remaining_days' => $graceDaysLeft,
            ];
        }

        if ($phase === 'trial') {
            if ($remainingDays <= 1) {
                return [
                    'type' => 'urgent',
                    'message' => "Deneme sürenizin bitmesine {$remainingDays} gün kaldı!",
                    'cta_text' => 'Plan Seç',
                    'cta_url' => '/customer/packages/list',
                    'color' => 'red',
                    'remaining_days' => $remainingDays,
                ];
            }
            if ($remainingDays <= 3) {
                return [
                    'type' => 'warning',
                    'message' => "Ücretsiz denemenizin bitmesine {$remainingDays} gün kaldı.",
                    'cta_text' => 'Plan Seç',
                    'cta_url' => '/customer/packages/list',
                    'color' => 'orange',
                    'remaining_days' => $remainingDays,
                ];
            }
            return [
                'type' => 'info',
                'message' => "Ücretsiz deneme: {$remainingDays} gün kaldı",
                'cta_text' => 'Planları Gör',
                'cta_url' => '/customer/packages/list',
                'color' => 'blue',
                'remaining_days' => $remainingDays,
            ];
        }

        return null;
    }
}
