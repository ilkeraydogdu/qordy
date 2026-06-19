<?php
namespace App\Controllers;

require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../core/DependencyFactory.php';
require_once __DIR__ . '/../core/TenantContext.php';
require_once __DIR__ . '/../services/QueueService.php';
require_once __DIR__ . '/../services/QueueNotificationService.php';

use App\Core\DependencyFactory;
use App\Core\TenantContext;
use App\Core\Logger;
use App\Core\RequestParser;

/**
 * PublicQueueController
 *
 * Handles the customer-facing side of the queue (sıra) feature:
 *  - /q            : door-screen signage (big QR + live waiting stats)
 *  - /q/form       : customer form page (requires a valid QR token)
 *  - POST /q/submit: enqueue the visitor
 *  - /q/status/{id}: live status page the visitor watches after enqueuing
 *  - /q/cancel/{id}: customer cancels their own ticket
 *  - /api/q/token  : door display polls this to refresh its QR
 *  - /api/q/status/{id}: customer page polls this
 *
 * All endpoints rely on subdomain-based tenant resolution performed by
 * TenantMiddleware (see App bootstrap). We do NOT call ensureTenantContext()
 * here because this is a fully public surface.
 */
class PublicQueueController extends \App\Core\Controller
{
    private $queueService;
    private $queueNotificationService;
    private $customerService;

    public function __construct()
    {
        parent::__construct();
        $this->queueService = DependencyFactory::getQueueService();
        $this->queueNotificationService = DependencyFactory::getQueueNotificationService();
        $this->customerService = DependencyFactory::getCustomerService();
    }

    // ---------------------------------------------------------------------
    // Door display screen
    // ---------------------------------------------------------------------
    public function display(): void
    {
        $tenant = $this->requireTenantOrFail();
        if ($tenant === null) {
            return;
        }
        $tenantId = (string) ($tenant['customer_id'] ?? $tenant['id']);

        $settings = $this->queueService->getSettings($tenantId);
        $effAccepting = $this->queueService->getEffectiveIsAcceptingQueue($tenantId, $settings);
        $viewSettings = $this->withEffectiveIsAcceptingQueue($settings, $effAccepting);
        $defLang = (string) ($settings['default_language'] ?? 'tr');
        $doorMenuItems = $effAccepting ? [] : $this->loadDoorMenuSlideshowItems($defLang);
        $token    = $this->queueService->ensureActiveToken($tenantId);
        $active   = $this->queueService->getActiveQueue($tenantId);

        $this->view('queue/display', [
            'business'  => $tenant,
            'settings'  => $viewSettings,
            'token'     => $token,
            'active'    => $active,
            'waitingCount' => count($active),
            'formUrl'   => $this->buildFormUrl($token['token'] ?? ''),
            'doorMenuItems' => $doorMenuItems,
        ]);
    }

    public function apiToken(): void
    {
        $tenant = $this->requireTenantOrFail(true);
        if ($tenant === null) {
            return;
        }
        $tenantId = (string) ($tenant['customer_id'] ?? $tenant['id']);

        $settings = $this->queueService->getSettings($tenantId);
        $effAccepting = $this->queueService->getEffectiveIsAcceptingQueue($tenantId, $settings);
        $current  = $this->queueService->ensureActiveToken($tenantId);

        $expiresAtTs = strtotime($current['expires_at'] ?? 'now');
        $secondsLeft = max(0, $expiresAtTs - time());

        // Rotate proactively when less than 10% of TTL left
        $ttl = (int) ($settings['qr_token_ttl_seconds'] ?? 90);
        if ($secondsLeft < max(5, (int) floor($ttl * 0.15))) {
            $current = $this->queueService->rotateToken($tenantId);
            $expiresAtTs = strtotime($current['expires_at'] ?? 'now');
            $secondsLeft = max(0, $expiresAtTs - time());
        }

        $active = $this->queueService->getActiveQueue($tenantId);
        // Slim the active list for JSON payload (only what the display needs)
        $activeSlim = array_map(static function ($e) {
            return [
                'queue_number' => (int) ($e['queue_number'] ?? 0),
                'status'       => (string) ($e['status'] ?? ''),
            ];
        }, array_slice($active, 0, 12));

        $this->jsonResponse([
            'success' => true,
            'token'        => $current['token'] ?? null,
            'expires_at'   => $current['expires_at'] ?? null,
            'seconds_left' => $secondsLeft,
            'form_url'     => $this->buildFormUrl($current['token'] ?? ''),
            'waiting_count' => count($active),
            'estimated_wait' => count($active) * max(1, (int) ($settings['average_wait_minutes'] ?? 15)),
            'active'       => $activeSlim,
            'is_accepting_queue' => $effAccepting,
        ]);
    }

    // ---------------------------------------------------------------------
    // Customer form
    // ---------------------------------------------------------------------
    public function form(): void
    {
        $tenant = $this->requireTenantOrFail();
        if ($tenant === null) {
            return;
        }
        $tenantId = (string) ($tenant['customer_id'] ?? $tenant['id']);

        $qp = RequestParser::getQueryParams();
        $tokenValue = trim((string) ($qp['token'] ?? $qp['t'] ?? ''));

        $settings   = $this->queueService->getSettings($tenantId);
        $effAccepting = $this->queueService->getEffectiveIsAcceptingQueue($tenantId, $settings);
        $settings = $this->withEffectiveIsAcceptingQueue($settings, $effAccepting);
        $sessionKey = $this->ensureSessionCookie();

        // Already in the queue? -> status page.
        $existing = $this->findExistingActiveEntry($tenantId, $sessionKey);
        if ($existing) {
            $this->redirectTo('/sira/bilet/' . rawurlencode($existing['queue_id']));
            return;
        }

        // Gate logic — user may arrive via:
        //   (a) fresh QR scan with a valid token in the URL (token is still live)
        //   (b) existing form pass cookie (they already validated earlier in last 15 min)
        //   (c) token present but already expired/rotated -> treat as (b) if cookie is valid,
        //       otherwise they need to rescan.
        $tokenRow   = $tokenValue !== '' ? $this->queueService->getTokenByValue($tokenValue) : null;
        $tokenValid = $this->isTokenUsable($tokenRow, $tenantId);
        $hasPass    = $this->verifyFormPass($tenantId, $sessionKey);

        if ($tokenValid) {
            // Issue / refresh a 15-min form pass so the QR token can rotate freely.
            $this->issueFormPass($tenantId, $sessionKey, 900);
            $hasPass = true;
        }

        $this->view('queue/form', [
            'business'   => $tenant,
            'settings'   => $settings,
            'tokenValue' => $tokenValid ? $tokenValue : '',
            'tokenValid' => $hasPass, // legacy flag — now means "form is usable"
            'hasPass'    => $hasPass,
            'tokenRow'   => $tokenRow,
            'sessionKey' => $sessionKey,
            'csrf_token' => $this->getCsrfToken(),
        ]);
    }

    public function submit(): void
    {
        $tenant = $this->requireTenantOrFail(true);
        if ($tenant === null) {
            return;
        }
        $tenantId = (string) ($tenant['customer_id'] ?? $tenant['id']);
        $sessionKey = $this->ensureSessionCookie();

        $data = RequestParser::getRequestData();

        // CSRF check (best-effort; the endpoint also relies on session cookie)
        if (!$this->verifyCsrf($data['csrf_token'] ?? '')) {
            $this->jsonResponse(['success' => false, 'error' => 'invalid_csrf'], 400);
            return;
        }

        // Primary authorisation: the 15-minute signed form pass issued when the
        // visitor first scanned the QR. The QR token itself is only a "door key"
        // and is free to rotate while the guest is filling the form.
        $hasPass    = $this->verifyFormPass($tenantId, $sessionKey);
        $tokenValue = (string) ($data['token'] ?? '');
        $tokenRow   = $tokenValue !== '' ? $this->queueService->getTokenByValue($tokenValue) : null;
        $tokenValid = $this->isTokenUsable($tokenRow, $tenantId);

        if (!$hasPass && !$tokenValid) {
            $this->jsonResponse(['success' => false, 'error' => 'invalid_or_expired_token', 'code' => 'TOKEN_EXPIRED'], 410);
            return;
        }

        // Prevent resubmission from same browser
        $existing = $this->findExistingActiveEntry($tenantId, $sessionKey);
        if ($existing) {
            $this->jsonResponse([
                'success' => true,
                'reused'  => true,
                'queue_id' => $existing['queue_id'],
                'redirect' => '/sira/bilet/' . rawurlencode($existing['queue_id']),
            ]);
            return;
        }

        $payload = [
            'name'        => (string) ($data['name'] ?? ''),
            'surname'     => (string) ($data['surname'] ?? ''),
            'phone'       => (string) ($data['phone'] ?? ''),
            'phone_country' => (string) ($data['phone_country'] ?? ''),
            'email'       => (string) ($data['email'] ?? ''),
            'party_size'  => (int)    ($data['party_size'] ?? 1),
            'has_baby'    => !empty($data['has_baby']),
            'has_accessibility' => !empty($data['has_accessibility']),
            'note'        => (string) ($data['note'] ?? ''),
            'language'    => (string) ($data['language'] ?? ''),
            'marketing_opt_in' => !empty($data['marketing_opt_in']),
            'token_id'    => (int) ($tokenRow['id'] ?? 0),
            'session_key' => $sessionKey,
            'device_fingerprint' => $this->hashFingerprint(),
            'ip_address'  => $this->clientIp(),
            'user_agent'  => (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''),
        ];

        $result = $this->queueService->enqueue($tenantId, $payload);
        if (!$result['success']) {
            $this->jsonResponse($result, 422);
            return;
        }

        $entry = $result['entry'] ?? null;

        if ($entry && empty($result['reused'])) {
            try {
                $settings = $this->queueService->getSettings($tenantId);
                $status   = $this->queueService->buildPublicStatus($entry);
                $this->queueNotificationService->notifyJoined(
                    $entry,
                    $settings,
                    $tenant,
                    (int) ($status['position'] ?? 1),
                    (int) ($status['eta_minutes'] ?? 0)
                );
            } catch (\Throwable $e) {
                if (class_exists('\App\Core\Logger')) {
                    Logger::warning('PublicQueueController@submit notifyJoined failed', [
                        'error' => $e->getMessage(),
                        'entry' => $entry['id'] ?? null,
                    ]);
                }
            }
        }

        $this->jsonResponse([
            'success'  => true,
            'reused'   => !empty($result['reused']),
            'queue_id' => $entry['queue_id'] ?? null,
            'redirect' => '/sira/bilet/' . rawurlencode($entry['queue_id'] ?? ''),
        ]);
    }

    // ---------------------------------------------------------------------
    // Status page
    // ---------------------------------------------------------------------
    public function status($queueId = null): void
    {
        $tenant = $this->requireTenantOrFail();
        if ($tenant === null) {
            return;
        }
        $tenantId = (string) ($tenant['customer_id'] ?? $tenant['id']);

        $queueId = (string) $queueId;
        $entry = $queueId !== '' ? $this->queueService->getEntryForPublic($queueId) : null;
        if (!$entry || (string) $entry['tenant_id'] !== $tenantId) {
            http_response_code(404);
            $this->view('queue/not_found', [
                'business' => $tenant,
            ]);
            return;
        }

        $settings = $this->queueService->getSettings($tenantId);
        $status = $this->queueService->buildPublicStatus($entry);

        $this->view('queue/status', [
            'business' => $tenant,
            'settings' => $settings,
            'entry'    => $entry,
            'status'   => $status,
            'csrf_token' => $this->getCsrfToken(),
        ]);
    }

    public function apiStatus($queueId = null): void
    {
        $tenant = $this->requireTenantOrFail(true);
        if ($tenant === null) {
            return;
        }
        $tenantId = (string) ($tenant['customer_id'] ?? $tenant['id']);

        $queueId = (string) $queueId;
        $entry = $queueId !== '' ? $this->queueService->getEntryForPublic($queueId) : null;
        if (!$entry || (string) $entry['tenant_id'] !== $tenantId) {
            $this->jsonResponse(['success' => false, 'error' => 'not_found'], 404);
            return;
        }

        $this->jsonResponse([
            'success' => true,
            'status'  => $this->queueService->buildPublicStatus($entry),
        ]);
    }

    public function cancel($queueId = null): void
    {
        $tenant = $this->requireTenantOrFail(true);
        if ($tenant === null) {
            return;
        }
        $tenantId = (string) ($tenant['customer_id'] ?? $tenant['id']);

        $sessionKey = $this->ensureSessionCookie();

        $entry = $queueId ? $this->queueService->getEntryForPublic((string) $queueId) : null;
        if (!$entry || (string) $entry['tenant_id'] !== $tenantId) {
            $this->jsonResponse(['success' => false, 'error' => 'not_found'], 404);
            return;
        }

        if (($entry['session_key'] ?? '') !== $sessionKey) {
            $this->jsonResponse(['success' => false, 'error' => 'not_owner'], 403);
            return;
        }

        if (!in_array($entry['status'], ['WAITING', 'NOTIFIED'], true)) {
            $this->jsonResponse(['success' => false, 'error' => 'already_closed'], 409);
            return;
        }

        $this->queueService->cancel((int) $entry['id'], true);
        $this->jsonResponse(['success' => true]);
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------
    private function withEffectiveIsAcceptingQueue(array $settings, bool $effective): array
    {
        $s = $settings;
        $s['is_accepting_queue'] = $effective ? 1 : 0;
        return $s;
    }

    /**
     * Hoşgeldin / marka ekranında dönen ürün slaytı (menüde ürün varsa).
     */
    private function loadDoorMenuSlideshowItems(string $languageCode): array
    {
        try {
            $menu = DependencyFactory::getMenuItemService();
            $items = $menu->getAvailableMenuItems($languageCode);
        } catch (\Throwable $e) {
            return [];
        }
        if (empty($items)) {
            return [];
        }
        shuffle($items);
        $out = [];
        foreach (array_slice($items, 0, 10) as $it) {
            $name = trim((string) ($it['name'] ?? $it['title'] ?? ''));
            if ($name === '') {
                continue;
            }
            $rawPrice = $it['price'] ?? $it['unit_price'] ?? null;
            $price = is_numeric($rawPrice) ? (float) $rawPrice : null;
            $out[] = [
                'name'      => $name,
                'image_url' => trim((string) ($it['image_url'] ?? '')),
                'price'     => $price,
            ];
        }
        return $out;
    }

    private function requireTenantOrFail(bool $isJson = false): ?array
    {
        $tenant = TenantContext::get();
        if (!$tenant) {
            $host = $_SERVER['HTTP_HOST'] ?? '';
            $subdomain = TenantContext::getSubdomainFromHost($host);
            if ($subdomain) {
                try {
                    $tenant = $this->customerService->getBySubdomain($subdomain);
                    if ($tenant) {
                        TenantContext::set($tenant);
                    }
                } catch (\Throwable $e) {
                    if (class_exists('\App\Core\Logger')) {
                        Logger::warning('PublicQueueController tenant lookup failed', [
                            'subdomain' => $subdomain,
                            'error'     => $e->getMessage(),
                        ]);
                    }
                }
            }
        }

        if (!$tenant) {
            if ($isJson) {
                $this->jsonResponse(['success' => false, 'error' => 'tenant_not_found'], 404);
            } else {
                http_response_code(404);
                echo '<!doctype html><meta charset="utf-8"><title>Not found</title>'
                   . '<div style="font-family:sans-serif;padding:40px;text-align:center">'
                   . '<h1>İşletme bulunamadı</h1>'
                   . '<p>Lütfen QR kodu tekrar okutun.</p></div>';
            }
            return null;
        }
        return $tenant;
    }

    private function isTokenUsable(?array $tokenRow, string $tenantId): bool
    {
        if (!$tokenRow) {
            return false;
        }
        if ((string) $tokenRow['tenant_id'] !== $tenantId) {
            return false;
        }
        if (!empty($tokenRow['is_revoked'])) {
            return false;
        }
        $expiresAt = strtotime($tokenRow['expires_at'] ?? 'now');
        if ($expiresAt < time()) {
            return false;
        }
        $max = (int) ($tokenRow['max_consumptions'] ?? 0);
        if ($max > 0 && (int) ($tokenRow['consumed_count'] ?? 0) >= $max) {
            return false;
        }
        return true;
    }

    private function findExistingActiveEntry(string $tenantId, string $sessionKey): ?array
    {
        try {
            $model = new \App\Models\QueueEntry();
            return $model->findActiveBySessionKey($tenantId, $sessionKey);
        } catch (\Throwable $e) {
            return null;
        }
    }

    // ------------------------------------------------------------------
    // Form pass — HMAC signed cookie that lets the guest finish the form
    // even after the door-display QR token has rotated / expired.
    //
    // Payload = tenantId|sessionKey|expiresAt
    // Cookie  = base64(payload) . '.' . hmac_sha256(payload, secret)
    // ------------------------------------------------------------------
    private function formPassSecret(): string
    {
        $seed = $_ENV['APP_KEY'] ?? $_ENV['APP_SECRET'] ?? getenv('APP_KEY') ?: 'qordy-queue-pass-v1';
        return hash('sha256', (string) $seed . '|queue-form-pass');
    }

    private function issueFormPass(string $tenantId, string $sessionKey, int $ttlSeconds = 900): void
    {
        $exp = time() + max(60, $ttlSeconds);
        $payload = $tenantId . '|' . $sessionKey . '|' . $exp;
        $sig = hash_hmac('sha256', $payload, $this->formPassSecret());
        $cookie = rtrim(strtr(base64_encode($payload), '+/', '-_'), '=') . '.' . $sig;

        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        setcookie('qd_queue_pass', $cookie, [
            'expires'  => $exp,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        $_COOKIE['qd_queue_pass'] = $cookie;
    }

    private function verifyFormPass(string $tenantId, string $sessionKey): bool
    {
        $cookie = (string) ($_COOKIE['qd_queue_pass'] ?? '');
        if ($cookie === '' || strpos($cookie, '.') === false) {
            return false;
        }
        [$b64, $sig] = explode('.', $cookie, 2);
        $payload = base64_decode(strtr($b64, '-_', '+/'), true);
        if ($payload === false) {
            return false;
        }
        $parts = explode('|', $payload);
        if (count($parts) !== 3) {
            return false;
        }
        [$tid, $sk, $exp] = $parts;
        if ($tid !== $tenantId || $sk !== $sessionKey) {
            return false;
        }
        if ((int) $exp < time()) {
            return false;
        }
        $expected = hash_hmac('sha256', $payload, $this->formPassSecret());
        return hash_equals($expected, $sig);
    }

    private function ensureSessionCookie(): string
    {
        $name = 'qd_queue_sk';
        if (!empty($_COOKIE[$name]) && preg_match('/^[a-f0-9]{32,64}$/', $_COOKIE[$name])) {
            return $_COOKIE[$name];
        }
        $value = bin2hex(random_bytes(24));
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        setcookie($name, $value, [
            'expires'  => time() + 60 * 60 * 24 * 30,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        $_COOKIE[$name] = $value;
        return $value;
    }

    private function buildFormUrl(string $token): string
    {
        $path = '/sira/katil?token=' . rawurlencode($token);

        $tenant = TenantContext::get();
        $tenantId = TenantContext::getId();
        if ($tenant && $tenantId) {
            try {
                $urlService = DependencyFactory::getUrlService();
                return $urlService->buildTenantUrl((string) $tenantId, $path);
            } catch (\Throwable $e) {
                // fall through to HTTP_HOST-based build
            }
        }

        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? ($_ENV['BASE_DOMAIN'] ?? 'qordy.com');
        return $protocol . '://' . $host . $path;
    }

    private function hashFingerprint(): string
    {
        $parts = [
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '',
            $_SERVER['HTTP_SEC_CH_UA'] ?? '',
            $_SERVER['HTTP_SEC_CH_UA_PLATFORM'] ?? '',
            $_SERVER['HTTP_SEC_CH_UA_MOBILE'] ?? '',
        ];
        return substr(hash('sha256', implode('|', $parts)), 0, 64);
    }

    private function clientIp(): string
    {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'] as $k) {
            if (!empty($_SERVER[$k])) {
                $ip = trim(explode(',', $_SERVER[$k])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return '';
    }

    private function getCsrfToken(): string
    {
        require_once __DIR__ . '/../core/Security/CSRFManager.php';
        return \App\Core\Security\CSRFManager::generateToken();
    }

    private function verifyCsrf(string $token): bool
    {
        if ($token === '') {
            return false;
        }
        require_once __DIR__ . '/../core/Security/CSRFManager.php';
        if (method_exists('\App\Core\Security\CSRFManager', 'validateToken')) {
            return (bool) \App\Core\Security\CSRFManager::validateToken($token);
        }
        if (method_exists('\App\Core\Security\CSRFManager', 'verifyToken')) {
            return (bool) \App\Core\Security\CSRFManager::verifyToken($token);
        }
        return true;
    }

    protected function redirectTo(string $path): void
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'qordy.com';
        header('Location: ' . $protocol . '://' . $host . $path);
        exit;
    }
}
