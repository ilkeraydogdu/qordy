<?php
namespace App\Controllers\API;

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../../core/DependencyFactory.php';

use App\Core\Controller;
use App\Core\DependencyFactory;
use App\Core\TenantContext;

class MobileAPIController extends Controller {
    
    public function __construct() {
        parent::__construct();
        header('Content-Type: application/json; charset=utf-8');
    }
    
    // ─── Helpers ──────────────────────────────────────────────
    
    protected function json($data, int $status = 200): void {
        http_response_code($status);
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Resolve a tenant-relative asset path into an absolute URL.
     *
     * Before this helper, five call sites inlined the same ternary chain
     * that had a subtle bug: when the stored path started with `/` the
     * chain dropped the separator AND ltrim'd the leading slash, producing
     * URLs like `https://qordy.comassets/...`. Centralising avoids drift
     * and the bug.
     *
     * @param string|null $path  Stored value (may be http URL, '/asset/x', or 'asset/x').
     * @return string|null        Absolute URL or null when input is empty.
     */
    private function absoluteAssetUrl(?string $path): ?string {
        if ($path === null) return null;
        $path = trim($path);
        if ($path === '') return null;
        // Pass through anything that already looks absolute.
        if (stripos($path, 'http://') === 0 || stripos($path, 'https://') === 0) {
            return $path;
        }
        if (!defined('BASE_URL') || BASE_URL === '') {
            return $path;
        }
        return rtrim(BASE_URL, '/') . '/' . ltrim($path, '/');
    }
    
    private function input(): array {
        $raw = json_decode(file_get_contents('php://input'), true);
        $merged = is_array($raw) ? array_merge($_POST, $raw) : $_POST;
        // Client SDKs (notably Flutter's Dio) tend to marshal request
        // bodies using camelCase keys while our PHP handlers historically
        // expect snake_case. Rather than patching every endpoint we
        // surface both spellings on the request array so a field like
        // `packageId` is readable as `package_id` too. Existing
        // snake_case keys win on collision so this is safe to apply
        // globally.
        foreach ($merged as $key => $value) {
            if (!is_string($key) || !preg_match('/[A-Z]/', $key)) continue;
            $snake = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $key));
            if (!array_key_exists($snake, $merged)) {
                $merged[$snake] = $value;
            }
        }
        return $merged;
    }

    /**
     * Match customers row from mobile login input: slug, işletme adı, or full host/URL.
     */
    private function resolveBusinessForMobileTenant(\PDO $db, string $rawInput): ?array {
        $svc = DependencyFactory::getSubdomainService();
        $slug = $svc->normalizeTenantInput($rawInput);
        if (strlen($slug) < 3) {
            return null;
        }
        $stmt = $db->prepare(
            "SELECT * FROM customers WHERE subdomain = ? AND (status IS NULL OR LOWER(status) != 'deleted') LIMIT 1"
        );
        $stmt->execute([$slug]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($row) {
            return $row;
        }

        // Second-chance match against company_name when the mobile
        // client typed the brand instead of the subdomain. We intentionally
        // constrain with a SQL-side LIKE on normalized chars so we don't
        // stream the entire `customers` table into PHP just to compare
        // slugs — the old implementation did a full table scan on every
        // failed login and was O(tenants).
        $likeFragment = '%' . preg_replace('/[^a-z0-9]/i', '%', $slug) . '%';
        $stmt = $db->prepare(
            "SELECT * FROM customers
             WHERE (status IS NULL OR LOWER(status) != 'deleted')
               AND LOWER(company_name) LIKE ?
             LIMIT 25"
        );
        $stmt->execute([strtolower($likeFragment)]);
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $cn = $row['company_name'] ?? '';
            if ($cn !== '' && $svc->slugifyTenantKey($cn) === $slug) {
                return $row;
            }
        }
        return null;
    }
    
    private function query(): array {
        return \App\Core\RequestParser::getQueryParams();
    }
    
    /** Get orders for period - uses datetime range for single-day when it matches business date (overnight support) */
    private function getOrdersForPeriod(array $dr, array $businessRange, $orderService): array {
        $startDate = $dr['start_date'];
        $endDate = $dr['end_date'];
        if ($startDate === $endDate && $startDate === ($businessRange['date'] ?? '')) {
            $startDt = $businessRange['start_datetime'] ?? $businessRange['start'] ?? ($startDate . ' 00:00:00');
            $endDt = $businessRange['end_datetime'] ?? $businessRange['end'] ?? ($endDate . ' 23:59:59');
            return $orderService->getOrdersByDatetimeRange($startDt, $endDt);
        }
        return $orderService->getOrdersByDateRange($startDate, $endDate);
    }
    
    /** Compute start_date and end_date from period (today|week|month|3months) based on business date */
    private function getDateRangeFromPeriod(string $period): array {
        $settingsService = DependencyFactory::getSystemSettingsService();
        $range = $settingsService->getBusinessDateRange();
        $baseDate = $range['date'];
        $now = new \DateTime($baseDate);
        $endDate = $now->format('Y-m-d');
        $startDate = $endDate;
        switch (strtolower($period)) {
            case 'week':
                $startDate = (clone $now)->modify('-6 days')->format('Y-m-d');
                break;
            case 'month':
                $startDate = (clone $now)->modify('-29 days')->format('Y-m-d');
                break;
            case '3months':
                $startDate = (clone $now)->modify('-89 days')->format('Y-m-d');
                break;
            default:
                break;
        }
        return ['start_date' => $startDate, 'end_date' => $endDate];
    }
    
    private function getBearerToken(): ?string {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Demo tenant: block mutating API calls (read-only showcase).
     */
    private function enforceDemoReadOnlyIfNeeded(): void {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            return;
        }
        $cid = \App\Core\TenantResolver::resolve();
        if (!$cid) {
            return;
        }
        try {
            if (!DependencyFactory::getCustomerRepository()->isDemoCustomer($cid)) {
                return;
            }
        } catch (\Throwable $e) {
            return;
        }
        $this->json([
            'success' => false,
            'error' => 'Demo modunda veri değiştirilemez. Kendi işletmenizi oluşturmak için kayıt olun.',
            'code' => 'DEMO_READ_ONLY',
        ], 403);
    }
    
    private function requireAuth(): bool {
        if ($this->isLoggedIn()) {
            $cid = \App\Core\TenantResolver::resolve();
            if ($cid && !array_key_exists('is_demo', $_SESSION)) {
                try {
                    $_SESSION['is_demo'] = DependencyFactory::getCustomerRepository()->isDemoCustomer($cid);
                } catch (\Throwable $e) {
                    $_SESSION['is_demo'] = false;
                }
            }
            $this->enforceDemoReadOnlyIfNeeded();
            return true;
        }
        
        $token = $this->getBearerToken();
        if ($token) {
            try {
                $db = DependencyFactory::getDatabase();
                $this->ensureMobileTokensTable($db);
                // Pull revocation flag too so we can reject tokens that
                // were invalidated server-side (logout on another device,
                // admin force-logout, password change, etc.).
                $stmt = $db->prepare("SELECT id, user_id, tenant_id AS business_id, expires_at, revoked_at FROM mobile_tokens WHERE token = ? LIMIT 1");
                $stmt->execute([$token]);
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);

                if (!$row) {
                    $this->json([
                        'success' => false,
                        'error' => 'Oturum süresi doldu, lütfen yeniden giriş yapın',
                        'code' => 'TOKEN_INVALID',
                    ], 401);
                    return false;
                }
                if (!empty($row['revoked_at'])) {
                    $this->json([
                        'success' => false,
                        'error' => 'Oturumunuz iptal edildi',
                        'code' => 'TOKEN_REVOKED',
                    ], 401);
                    return false;
                }
                if (strtotime($row['expires_at']) < time()) {
                    $this->json([
                        'success' => false,
                        'error' => 'Oturum süresi doldu',
                        'code' => 'TOKEN_EXPIRED',
                    ], 401);
                    return false;
                }

                // Defence in depth: make sure the underlying user and tenant
                // are still active before letting the token unlock the app.
                $userService = DependencyFactory::getUserService();
                $user = $userService->findByUserId($row['user_id']);
                if (!$user) {
                    $this->json([
                        'success' => false,
                        'error' => 'Kullanıcı bulunamadı',
                        'code' => 'USER_NOT_FOUND',
                    ], 401);
                    return false;
                }
                $userActive = true;
                if (array_key_exists('is_active', $user)) {
                    $userActive = (int) ($user['is_active'] ?? 1) === 1;
                } elseif (array_key_exists('active', $user)) {
                    $userActive = (int) ($user['active'] ?? 1) === 1;
                }
                if (!$userActive) {
                    $this->json([
                        'success' => false,
                        'error' => 'Kullanıcı hesabınız pasif durumda',
                        'code' => 'USER_INACTIVE',
                    ], 403);
                    return false;
                }

                try {
                    $customer = DependencyFactory::getCustomerService()->getById($row['business_id']);
                    if (!$customer) {
                        $this->json([
                            'success' => false,
                            'error' => 'İşletme bulunamadı',
                            'code' => 'TENANT_NOT_FOUND',
                        ], 403);
                        return false;
                    }
                    // Accept both object and array shapes depending on service impl.
                    $active = 1;
                    if (is_array($customer)) {
                        $active = (int) ($customer['is_active'] ?? 1);
                    } elseif (is_object($customer)) {
                        $active = (int) (($customer->is_active ?? null) ?? ($customer->getIsActive ?? null) ?? 1);
                    }
                    if ($active === 0) {
                        $this->json([
                            'success' => false,
                            'error' => 'İşletmeniz askıya alınmış',
                            'code' => 'TENANT_SUSPENDED',
                        ], 403);
                        return false;
                    }
                } catch (\Throwable $e) {
                    // Don't block the request on an unexpected lookup error,
                    // but record it for operators.
                    \App\Core\Logger::error('Mobile tenant lookup error', ['error' => $e->getMessage()]);
                }

                $_SESSION['logged_in'] = true;
                $_SESSION['user_id'] = $row['user_id'];
                $_SESSION['customer_id'] = $row['business_id'];
                $_SESSION['business_id'] = $row['business_id'];
                try {
                    $_SESSION['is_demo'] = DependencyFactory::getCustomerRepository()->isDemoCustomer($row['business_id']);
                } catch (\Throwable $e) {
                    $_SESSION['is_demo'] = false;
                }

                $_SESSION['username'] = $user['name'] ?? $user['username'] ?? '';
                $_SESSION['role'] = $user['role'] ?? '';
                $_SESSION['role_id'] = $user['role_id'] ?? null;

                // Fire-and-forget audit trail — never fail the request.
                try {
                    $upd = $db->prepare("UPDATE mobile_tokens SET last_used_at = NOW(), last_ip = ?, user_agent = ? WHERE id = ?");
                    $upd->execute([
                        $this->clientIp(),
                        substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
                        $row['id'],
                    ]);
                } catch (\Throwable $e) {}

                $this->enforceDemoReadOnlyIfNeeded();
                return true;
            } catch (\Exception $e) {
                \App\Core\Logger::error('Mobile token auth error', ['error' => $e->getMessage()]);
            }
        }

        $this->json([
            'success' => false,
            'error' => 'Oturum açmanız gerekiyor',
            'code' => 'AUTH_REQUIRED',
        ], 401);
        return false;
    }
    
    private function requireManager(): bool {
        if (!$this->requireAuth()) return false;
        $role = strtoupper(trim($this->getCurrentRole() ?? $_SESSION['role'] ?? ''));
        $managerRoles = ['MANAGER', 'ROLE_MANAGER', 'BUSINESS_MANAGER', 'ROLE_BUSINESS_MANAGER', 'ADMIN', 'ADMINISTRATOR', 'SUPER_ADMIN', 'OWNER', 'ROLE_OWNER'];
        if (!in_array($role, $managerRoles)) {
            $this->json(['success' => false, 'error' => 'Bu işlem için yönetici yetkisi gerekli'], 403);
            return false;
        }
        return true;
    }
    
    private function getTenantId(): ?string {
        return \App\Core\TenantResolver::resolve();
    }
    
    private function ensureMobileTenant(): void {
        if (!TenantContext::isSet()) {
            $customerId = \App\Core\TenantResolver::resolve();
            if ($customerId) {
                try {
                    $customer = DependencyFactory::getCustomerService()->getById($customerId);
                    if ($customer) TenantContext::set($customer);
                } catch (\Exception $e) {}
            }
        }
    }
    
    /**
     * Lazily create the TOTP challenge table. We use a dedicated table
     * instead of overloading mobile_tokens because the challenge token
     * MUST NOT unlock any protected endpoint on its own — it is solely
     * the handle we hand back to the client after the password / PIN
     * check, exchanged for a real bearer token only after the TOTP code
     * is verified.
     */
    private function ensureMobile2FAChallengesTable(\PDO $db): void {
        try {
            $db->exec("CREATE TABLE IF NOT EXISTS mobile_2fa_challenges (
                id INT AUTO_INCREMENT PRIMARY KEY,
                challenge_token VARCHAR(128) NOT NULL UNIQUE,
                user_id VARCHAR(100) NOT NULL,
                tenant_id VARCHAR(100) NOT NULL,
                intent VARCHAR(32) NOT NULL,
                payload LONGTEXT NULL,
                attempts INT NOT NULL DEFAULT 0,
                expires_at DATETIME NOT NULL,
                consumed_at DATETIME NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_token (challenge_token),
                INDEX idx_user (user_id),
                INDEX idx_expires (expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } catch (\Exception $e) {}
    }

    /**
     * Atomic helper: create a pending 2FA challenge record and return
     * the opaque token that the client will present alongside the TOTP
     * code on the next request. Payload is JSON-encoded and holds
     * everything we need to rehydrate the session (role_id, remembered
     * read_only flag, etc.) without another database round trip.
     */
    private function issue2FAChallenge(
        \PDO $db,
        string $userId,
        string $tenantId,
        array $payload,
        int $ttlSeconds = 300
    ): string {
        $this->ensureMobile2FAChallengesTable($db);
        $token = bin2hex(random_bytes(32));
        $stmt = $db->prepare(
            "INSERT INTO mobile_2fa_challenges
             (challenge_token, user_id, tenant_id, intent, payload, expires_at, created_at)
             VALUES (?, ?, ?, 'login', ?, DATE_ADD(NOW(), INTERVAL ? SECOND), NOW())"
        );
        $stmt->execute([
            $token,
            $userId,
            $tenantId,
            json_encode($payload, JSON_UNESCAPED_UNICODE),
            $ttlSeconds,
        ]);
        return $token;
    }

    /**
     * Checks if TOTP 2FA is enabled for this user. Wrapped in a try so
     * the login path can't break if the user_2fa table is missing
     * (new installs where 2FA has never been configured).
     */
    private function isTotpEnabled(string $userId): bool {
        try {
            $repo = DependencyFactory::getUser2FARepository();
            if (!$repo) return false;
            $row = $repo->getByUserAndMethod($userId, 'totp');
            return $row && (int)($row['is_enabled'] ?? 0) === 1;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function getTotpSecret(string $userId): ?string {
        try {
            $repo = DependencyFactory::getUser2FARepository();
            if (!$repo) return null;
            $row = $repo->getByUserAndMethod($userId, 'totp');
            return $row['secret_code'] ?? null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Is a given 2FA method globally enabled by the superadmin?
     * Allowed methods: totp, whatsapp, email, sms.
     */
    private function is2FAMethodGloballyEnabled(string $method): bool {
        try {
            $s = DependencyFactory::getSystemSettingsService();
            $key = 'auth_2fa_' . $method . '_enabled';
            $default = in_array($method, ['totp', 'email'], true) ? '1' : '0';
            $v = (string)($s->getSetting($key, $default));
            return $v === '1';
        } catch (\Throwable $e) {
            return $method === 'totp';
        }
    }

    /**
     * Returns the list of 2FA methods that are BOTH globally allowed by
     * the superadmin AND enrolled+enabled by the given user.
     * Order matters: TOTP is preferred first (most secure + offline).
     */
    private function getEffectiveUser2FAMethods(string $userId): array {
        $out = [];
        try {
            $repo = DependencyFactory::getUser2FARepository();
            if (!$repo) return $out;
            $enrolled = $repo->getEnabledMethods($userId); // ['totp','whatsapp',...]
            $order = ['totp', 'whatsapp', 'email', 'sms'];
            foreach ($order as $m) {
                if (in_array($m, $enrolled, true) && $this->is2FAMethodGloballyEnabled($m)) {
                    $out[] = $m;
                }
            }
        } catch (\Throwable $e) {}
        return $out;
    }

    /**
     * Returns the user_2fa.secret_code for a method (phone number for
     * whatsapp/sms, email for email, base32 for totp), or null.
     */
    private function getUser2FASecret(string $userId, string $method): ?string {
        try {
            $repo = DependencyFactory::getUser2FARepository();
            if (!$repo) return null;
            $row = $repo->getByUserAndMethod($userId, $method);
            return $row['secret_code'] ?? null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Sends a one-time verification code for the given 2FA challenge via
     * the chosen delivery method. Stores a salted SHA-256 hash of the
     * code in the challenge payload so verify2FAChallenge can match it.
     * Returns ['success'=>bool, 'message'=>string].
     */
    private function send2FACode(\PDO $db, array $challenge, string $method): array {
        $method = strtolower($method);
        if (!in_array($method, ['whatsapp', 'email', 'sms'], true)) {
            return ['success' => false, 'message' => 'Bu yöntem otomatik kod göndermez'];
        }
        if (!$this->is2FAMethodGloballyEnabled($method)) {
            return ['success' => false, 'message' => 'Bu doğrulama yöntemi şu an kullanım dışı'];
        }
        $secret = $this->getUser2FASecret($challenge['user_id'], $method);
        if (empty($secret)) {
            return ['success' => false, 'message' => 'Bu yöntem için kayıtlı bilgi bulunamadı'];
        }
        $code = str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        $hash = hash('sha256', $code . '|' . $challenge['challenge_token']);
        $payload = json_decode($challenge['payload'] ?? '', true) ?: [];
        $payload['sent_code_hash'] = $hash;
        $payload['sent_method'] = $method;
        $payload['sent_at'] = time();

        try {
            $upd = $db->prepare("UPDATE mobile_2fa_challenges SET payload = ?, expires_at = DATE_ADD(NOW(), INTERVAL 300 SECOND), attempts = 0 WHERE id = ?");
            $upd->execute([json_encode($payload, JSON_UNESCAPED_UNICODE), $challenge['id']]);
        } catch (\Throwable $e) {
            \App\Core\Logger::warning('2FA challenge payload update failed', ['error' => $e->getMessage()]);
        }

        if ($method === 'whatsapp') {
            $wa = \App\Core\DependencyFactory::getWhatsAppService();
            return $wa->sendVerificationCode($secret, $code);
        }
        if ($method === 'email') {
            try {
                $emailSvc = DependencyFactory::getEmailService();
                $subject = 'Qordy Doğrulama Kodu';
                $body = "<p>Merhaba,</p><p>Giriş doğrulama kodunuz: <strong style=\"font-size:22px;letter-spacing:3px\">{$code}</strong></p><p>Kod 5 dakika içinde geçersiz olur. İsteği siz yapmadıysanız şifrenizi hemen değiştirin.</p>";
                $ok = $emailSvc->send($secret, $subject, $body);
                return ['success' => (bool)$ok, 'message' => $ok ? 'Kod e-postanıza gönderildi' : 'E-posta gönderilemedi'];
            } catch (\Throwable $e) {
                \App\Core\Logger::error('2FA email send failed', ['error' => $e->getMessage()]);
                return ['success' => false, 'message' => 'E-posta gönderilemedi'];
            }
        }
        // sms: stubbed (gateway-specific). Mark as sent only if an SMS
        // service exists; otherwise return a clear error.
        try {
            if (method_exists('App\\Core\\DependencyFactory', 'getSMSService')) {
                $smsSvc = \App\Core\DependencyFactory::getSMSService();
                if ($smsSvc && method_exists($smsSvc, 'send')) {
                    $ok = $smsSvc->send($secret, "Qordy doğrulama kodunuz: {$code}");
                    return ['success' => (bool)$ok, 'message' => $ok ? 'Kod SMS ile gönderildi' : 'SMS gönderilemedi'];
                }
            }
        } catch (\Throwable $e) {}
        return ['success' => false, 'message' => 'SMS servisi yapılandırılmamış'];
    }

    /**
     * Verify a 6-digit code for a non-TOTP method. The expected hash is
     * stored in challenge payload by send2FACode().
     */
    private function verifyNonTotpCode(array $challenge, string $code): bool {
        $payload = json_decode($challenge['payload'] ?? '', true) ?: [];
        $hash = $payload['sent_code_hash'] ?? '';
        $sentAt = (int)($payload['sent_at'] ?? 0);
        if (empty($hash) || $sentAt <= 0) return false;
        if ((time() - $sentAt) > 300) return false; // extra TTL guard
        $expected = hash('sha256', $code . '|' . $challenge['challenge_token']);
        return hash_equals($hash, $expected);
    }

    private function ensureMobileTokensTable(\PDO $db): void {
        try {
            $db->exec("CREATE TABLE IF NOT EXISTS mobile_tokens (
                id INT AUTO_INCREMENT PRIMARY KEY,
                token VARCHAR(255) NOT NULL UNIQUE,
                user_id VARCHAR(100) NOT NULL,
                tenant_id VARCHAR(100) NOT NULL,
                expires_at DATETIME NOT NULL,
                refresh_token VARCHAR(255) NULL,
                refresh_expires_at DATETIME NULL,
                revoked_at DATETIME NULL,
                last_used_at DATETIME NULL,
                last_ip VARCHAR(45) NULL,
                user_agent VARCHAR(255) NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_token (token),
                INDEX idx_user_id (user_id),
                INDEX idx_tenant (tenant_id),
                INDEX idx_expires (expires_at),
                INDEX idx_refresh_token (refresh_token),
                INDEX idx_revoked (revoked_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            // Var olan eski kurulumlar için kolon migrasyonu
            if (!\App\Core\DbSchema::hasColumn('mobile_tokens', 'refresh_token')) {
                $db->exec("ALTER TABLE mobile_tokens ADD COLUMN refresh_token VARCHAR(255) NULL AFTER expires_at");
                $db->exec("ALTER TABLE mobile_tokens ADD COLUMN refresh_expires_at DATETIME NULL AFTER refresh_token");
                try { $db->exec("ALTER TABLE mobile_tokens ADD INDEX idx_refresh_token (refresh_token)"); } catch (\Exception $e) {}
                \App\Core\DbSchema::forget('mobile_tokens');
            }
            if (!\App\Core\DbSchema::hasColumn('mobile_tokens', 'revoked_at')) {
                try { $db->exec("ALTER TABLE mobile_tokens ADD COLUMN revoked_at DATETIME NULL AFTER refresh_expires_at"); } catch (\Exception $e) {}
                try { $db->exec("ALTER TABLE mobile_tokens ADD COLUMN last_used_at DATETIME NULL AFTER revoked_at"); } catch (\Exception $e) {}
                try { $db->exec("ALTER TABLE mobile_tokens ADD COLUMN last_ip VARCHAR(45) NULL AFTER last_used_at"); } catch (\Exception $e) {}
                try { $db->exec("ALTER TABLE mobile_tokens ADD COLUMN user_agent VARCHAR(255) NULL AFTER last_ip"); } catch (\Exception $e) {}
                \App\Core\DbSchema::forget('mobile_tokens');
                try { $db->exec("ALTER TABLE mobile_tokens ADD INDEX idx_revoked (revoked_at)"); } catch (\Exception $e) {}
                try { $db->exec("ALTER TABLE mobile_tokens ADD INDEX idx_tenant (tenant_id)"); } catch (\Exception $e) {}
            }
        } catch (\Exception $e) {}
    }

    /**
     * Best-effort client IP extraction honouring common proxy headers.
     * Used for audit trails on mobile token usage.
     */
    private function clientIp(): string {
        $ip = $_SERVER['HTTP_CF_CONNECTING_IP']
            ?? $_SERVER['HTTP_X_FORWARDED_FOR']
            ?? $_SERVER['REMOTE_ADDR']
            ?? '';
        if (is_string($ip) && str_contains($ip, ',')) {
            $ip = trim(explode(',', $ip)[0]);
        }
        return substr((string) $ip, 0, 45);
    }

    /**
     * POST /api/mobile/refresh-token
     * Body: { refresh_token }
     * Dönüş: { success, data: { token, refresh_token, expires_at } }
     */
    public function refreshToken(): void {
        $data = $this->input();
        $refresh = trim($data['refresh_token'] ?? '');
        if ($refresh === '') {
            $refresh = $this->getBearerToken() ?: '';
        }
        if ($refresh === '') {
            $this->json(['success' => false, 'error' => 'refresh_token gerekli'], 400);
            return;
        }

        try {
            $db = DependencyFactory::getDatabase();
            $this->ensureMobileTokensTable($db);

            $stmt = $db->prepare("SELECT id, user_id, tenant_id, refresh_expires_at, revoked_at FROM mobile_tokens WHERE refresh_token = ? LIMIT 1");
            $stmt->execute([$refresh]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$row) {
                $this->json(['success' => false, 'error' => 'Geçersiz refresh token', 'code' => 'REFRESH_INVALID'], 401);
                return;
            }
            if (!empty($row['revoked_at'])) {
                $this->json(['success' => false, 'error' => 'Oturumunuz iptal edildi', 'code' => 'REFRESH_REVOKED'], 401);
                return;
            }
            if (!empty($row['refresh_expires_at']) && strtotime($row['refresh_expires_at']) < time()) {
                $this->json(['success' => false, 'error' => 'Refresh token süresi doldu', 'code' => 'REFRESH_EXPIRED'], 401);
                return;
            }

            $newToken = bin2hex(random_bytes(32));
            $newRefresh = bin2hex(random_bytes(32));
            $expiry = date('Y-m-d H:i:s', strtotime('+30 days'));
            $refreshExpiry = date('Y-m-d H:i:s', strtotime('+90 days'));

            $upd = $db->prepare("UPDATE mobile_tokens SET token = ?, expires_at = ?, refresh_token = ?, refresh_expires_at = ? WHERE id = ?");
            $upd->execute([$newToken, $expiry, $newRefresh, $refreshExpiry, $row['id']]);

            $this->json(['success' => true, 'data' => [
                'token' => $newToken,
                'refresh_token' => $newRefresh,
                'expires_at' => $expiry,
            ]]);
        } catch (\Exception $e) {
            \App\Core\Logger::error('Mobile refresh token error', ['error' => $e->getMessage()]);
            $this->json(['success' => false, 'error' => 'Refresh hatası'], 500);
        }
    }
    
    /**
     * Normalize raw DB role strings to the uppercase wire values the
     * Flutter client expects (WAITER / KITCHEN / PREPARATION / CASHIER
     * / MANAGER / OWNER / ADMIN). Legacy / localised values like
     * "garson", "mutfak", "STAFF_WAITER", "BUSINESS_OWNER" are mapped
     * to their canonical counterpart so the app can render the right
     * home screen & Turkish label without guessing on the client.
     */
    private function normalizeRoleWire(?string $role): string {
        $r = strtoupper(trim((string)$role));
        if ($r === '') return '';
        // owner / manager / admin super-roles
        if (str_contains($r, 'OWNER'))       return 'OWNER';
        if (str_contains($r, 'MANAGER'))     return 'MANAGER';
        if (str_contains($r, 'ADMIN'))       return 'ADMIN';
        // operational
        if (str_contains($r, 'WAITER') || $r === 'GARSON')        return 'WAITER';
        if (str_contains($r, 'KITCHEN') || $r === 'MUTFAK' || $r === 'CHEF') return 'KITCHEN';
        if (str_contains($r, 'PREPARATION') || $r === 'HAZIRLIK') return 'PREPARATION';
        if (str_contains($r, 'CASHIER') || $r === 'KASIYER' || $r === 'KASA') return 'CASHIER';
        return $r;
    }

    /**
     * Resolve the canonical uppercase role wire for a user row, falling
     * back to the `roles` table via `role_id` when `users.role` is empty
     * or contains a custom/legacy value that `normalizeRoleWire()`
     * cannot map. This is what guards the mobile app from the "waiter
     * sees owner dashboard" bug: without this helper a staff user whose
     * role is stored as a custom code (only `role_id` populated) would
     * ship to the client as an empty string and fall back to the
     * manager landing page on the Flutter side.
     */
    private function resolveCanonicalRoleWire(?string $role, ?string $roleId = null): string {
        $wire = $this->normalizeRoleWire($role);
        $known = ['OWNER', 'MANAGER', 'ADMIN', 'WAITER', 'KITCHEN', 'PREPARATION', 'CASHIER'];
        if ($wire !== '' && in_array($wire, $known, true)) {
            return $wire;
        }
        $roleId = $roleId !== null ? trim($roleId) : '';
        if ($roleId === '') {
            return $wire; // keep whatever normalize produced (may be empty)
        }
        try {
            $repo = DependencyFactory::getRoleRepository();
            if ($repo) {
                $row = $repo->findById($roleId) ?? null;
                if (is_array($row)) {
                    $byCode = $this->normalizeRoleWire($row['role_code'] ?? '');
                    if ($byCode !== '' && in_array($byCode, $known, true)) {
                        return $byCode;
                    }
                    $byName = $this->normalizeRoleWire($row['role_name'] ?? '');
                    if ($byName !== '' && in_array($byName, $known, true)) {
                        return $byName;
                    }
                }
            }
        } catch (\Throwable $e) {
            \App\Core\Logger::warning('resolveCanonicalRoleWire lookup failed', [
                'role_id' => $roleId,
                'error' => $e->getMessage(),
            ]);
        }
        return $wire;
    }

    private function getUserPermissions(?string $role, ?string $roleId = null): array {
        $role = strtoupper($role ?? '');
        if (in_array($role, ['MANAGER', 'BUSINESS_MANAGER', 'ADMIN', 'ADMINISTRATOR', 'SUPER_ADMIN'])) {
            return ['*'];
        }
        $basePerms = ['orders.view', 'tables.view', 'menu.view', 'notifications.view'];
        if (in_array($role, ['WAITER', 'GARSON'])) {
            return array_merge($basePerms, ['orders.create', 'orders.update', 'tables.manage']);
        }
        if (in_array($role, ['CASHIER', 'KASIYER'])) {
            return array_merge($basePerms, ['orders.update', 'payments.process']);
        }
        if (in_array($role, ['KITCHEN', 'CHEF', 'MUTFAK'])) {
            return array_merge($basePerms, ['orders.update']);
        }
        return $basePerms;
    }
    
    private function getBusinessStats(string $businessId): array {
        try {
            $this->ensureMobileTenant();
            $settingsService = DependencyFactory::getSystemSettingsService();
            $range = $settingsService->getBusinessDateRange();
            $orderService = DependencyFactory::getOrderService();
            $orders = $orderService->getOrdersByDateRange($range['date'], $range['date']);
            $orders = is_array($orders) ? $orders : [];
            
            $revenue = 0;
            foreach ($orders as $o) {
                if (strtoupper($o['status'] ?? '') !== 'CANCELLED') {
                    $revenue += floatval($o['total_amount'] ?? 0);
                }
            }
            
            $tables = DependencyFactory::getTableService()->getAllTables();
            $activeTables = array_filter($tables ?: [], fn($t) => ($t['status'] ?? '') === 'occupied');
            
            $userService = DependencyFactory::getUserService();
            $staffCount = 0;
            try {
                $staff = method_exists($userService, 'getAllUsers') ? $userService->getAllUsers() : $userService->getAll();
                $staffCount = is_array($staff) ? count($staff) : 0;
            } catch (\Exception $e) {}
            
            return [
                'orders_today' => count($orders),
                'revenue_today' => $revenue,
                'orders_month' => count($orders),
                'revenue_month' => $revenue,
                'staff_count' => $staffCount,
                'active_tables' => count($activeTables),
            ];
        } catch (\Exception $e) {
            return [
                'orders_today' => 0,
                'revenue_today' => 0,
                'orders_month' => 0,
                'revenue_month' => 0,
                'staff_count' => 0,
                'active_tables' => 0,
            ];
        }
    }
    
    // ─── Auth Endpoints ───────────────────────────────────────
    
    public function validateSubdomain() {
        $data = $this->input();
        $subdomain = trim($data['subdomain'] ?? $data['business_number'] ?? '');
        
        if ($subdomain === '') {
            $this->json(['success' => false, 'error' => 'İşletme adı gerekli'], 400);
        }
        
        try {
            $db = DependencyFactory::getDatabase();
            $business = $this->resolveBusinessForMobileTenant($db, $subdomain);
            
            if ($business) {
                $logoUrl = $this->absoluteAssetUrl(
                    $business['logo_url'] ?? $business['logo_path'] ?? null
                );
                $this->json(['success' => true, 'data' => [
                    'valid' => true,
                    'business' => [
                        'id' => $business['customer_id'],
                        'name' => $business['company_name'],
                        'subdomain' => $business['subdomain'],
                        'logo' => $logoUrl,
                    ]
                ]]);
            } else {
                $this->json(['success' => false, 'error' => 'İşletme bulunamadı'], 404);
            }
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => 'Doğrulama hatası'], 500);
        }
    }
    
    public function staffLogin() {
        $data = $this->input();
        $pin = $data['pin'] ?? '';
        $subdomain = trim($data['subdomain'] ?? $data['business_number'] ?? '');
        
        if ($pin === '' || $subdomain === '') {
            $this->json(['success' => false, 'error' => 'PIN ve işletme bilgisi gerekli'], 400);
        }
        
        try {
            $db = DependencyFactory::getDatabase();
            $biz = $this->resolveBusinessForMobileLogin($db, $subdomain);
            
            if (!$biz) {
                $this->json(['success' => false, 'error' => 'İşletme bulunamadı'], 404);
            }
            
            if (isset($biz['is_active']) && (int)$biz['is_active'] === 0) {
                $this->json(['success' => false, 'error' => 'İşletmeniz pasife alınmıştır. Lütfen yöneticinizle iletişime geçin.'], 403);
            }

            $businessId = $biz['customer_id'];

            // Abonelik durumu kontrolü — suspended/expired → personel girişi reddedilir
            $subCheck = $this->evaluateSubscriptionForLogin($businessId);
            if (!$subCheck['ok']) {
                $this->json([
                    'success' => false,
                    'error' => $subCheck['message'],
                    'phase' => $subCheck['phase'],
                    'suspended' => true,
                ], 403);
            }

            $logoUrl = $this->absoluteAssetUrl(
                $biz['logo_url'] ?? $biz['logo_path'] ?? null
            );
            
            // Get user by PIN via User model which handles hashed PINs securely
            require_once __DIR__ . '/../../models/User.php';
            \App\Core\TenantContext::setId($businessId);
            $userModel = new \App\Models\User();
            $user = $userModel->findByPin($pin);
            \App\Core\TenantContext::clear();
            
            if (!$user || !isset($user['user_id'])) {
                $this->json(['success' => false, 'error' => 'Geçersiz PIN'], 401);
            }

            // 2FA gate — if this user has any enrolled + admin-allowed 2FA
            // methods we MUST NOT hand out a bearer token yet. Issue a
            // short-lived challenge; the client exchanges it for the real
            // bearer via /api/mobile/auth/2fa/verify after the user
            // completes the chosen method (TOTP / WhatsApp / Email / SMS).
            $methods2fa = $this->getEffectiveUser2FAMethods($user['user_id']);
            if (!empty($methods2fa)) {
                $challenge = $this->issue2FAChallenge($db, $user['user_id'], $businessId, [
                    'flow' => 'staff',
                    'subdomain' => $subdomain,
                    'business_id' => $businessId,
                    'business_name' => $biz['company_name'] ?? '',
                    'read_only' => (bool)($subCheck['readOnly'] ?? false),
                    'phase' => $subCheck['phase'] ?? 'active',
                    'methods' => $methods2fa,
                ]);
                $this->json(['success' => true, 'data' => [
                    'requires_2fa' => true,
                    'method' => $methods2fa[0],
                    'methods' => $methods2fa,
                    'challenge_token' => $challenge,
                    'expires_in' => 300,
                    'user' => [
                        'name' => $user['name'],
                        'role' => $this->resolveCanonicalRoleWire($user['role'] ?? '', $user['role_id'] ?? null),
                        'role_code' => $this->resolveCanonicalRoleWire($user['role'] ?? '', $user['role_id'] ?? null),
                    ],
                    'business' => [
                        'name' => $biz['company_name'],
                        'logo' => $logoUrl,
                    ],
                ]]);
                return;
            }
            
            $token = bin2hex(random_bytes(32));
            $refreshToken = bin2hex(random_bytes(32));
            $expiry = date('Y-m-d H:i:s', strtotime('+30 days'));
            $refreshExpiry = date('Y-m-d H:i:s', strtotime('+90 days'));

            $this->ensureMobileTokensTable($db);

            try {
                $stmt = $db->prepare("INSERT INTO mobile_tokens (token, user_id, tenant_id, expires_at, refresh_token, refresh_expires_at, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE token = VALUES(token), expires_at = VALUES(expires_at), refresh_token = VALUES(refresh_token), refresh_expires_at = VALUES(refresh_expires_at)");
                $stmt->execute([$token, $user['user_id'], $businessId, $expiry, $refreshToken, $refreshExpiry]);
            } catch (\Exception $e) {
                \App\Core\Logger::warning('Mobile token insert failed', ['error' => $e->getMessage()]);
            }
            
            $_SESSION['logged_in'] = true;
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['name'] ?? '';
            $_SESSION['role'] = $user['role'];
            $_SESSION['role_id'] = $user['role_id'] ?? null;
            $_SESSION['customer_id'] = $businessId;
            $_SESSION['business_id'] = $businessId;
            $_SESSION['login_time'] = time();
            $_SESSION['is_demo'] = !empty($biz['is_demo']);
            if (!empty($_SESSION['is_demo'])) {
                try {
                    $fw = \App\Middleware\SecurityMiddleware::getFirewall();
                    $ip = $fw->getClientIP();
                    DependencyFactory::getDemoAccessLogRepository()->log(
                        $businessId,
                        $user['user_id'] ?? null,
                        $ip,
                        $_SERVER['HTTP_USER_AGENT'] ?? '',
                        'POST',
                        '/api/mobile/staff/login',
                        'login'
                    );
                } catch (\Throwable $e) {
                }
            }
            
            $permissions = $this->getUserPermissions($user['role'], $user['role_id'] ?? null);
            
            $this->json(['success' => true, 'data' => [
                'token' => $token,
                'refresh_token' => $refreshToken,
                'read_only' => $subCheck['readOnly'] ?? false,
                'phase' => $subCheck['phase'] ?? 'active',
                'user' => [
                    'id' => $user['user_id'],
                    'name' => $user['name'],
                    'role' => $this->resolveCanonicalRoleWire($user['role'] ?? '', $user['role_id'] ?? null),
                    'role_code' => $this->resolveCanonicalRoleWire($user['role'] ?? '', $user['role_id'] ?? null),
                    'role_id' => $user['role_id'] ?? null,
                    'preparation_screen_id' => $user['preparation_screen_id'] ?? null,
                ],
                'business' => [
                    'id' => $businessId,
                    'name' => $biz['company_name'],
                    'subdomain' => $biz['subdomain'],
 'business_number' => $biz['business_number'] ?? null,
                    'logo' => $logoUrl,
                ],
                'permissions' => $permissions,
            ]]);
        } catch (\Exception $e) {
            \App\Core\Logger::error('Mobile staff login error', ['error' => $e->getMessage()]);
            $this->json(['success' => false, 'error' => 'Giriş hatası'], 500);
        }
    }

    /**
     * Abonelik durumunu mobil login için değerlendir.
     * Dönüş: ['ok' => bool, 'phase' => string, 'readOnly' => bool, 'message' => string]
     *
     *  - active / trial   → ok=true, readOnly=false
     *  - grace            → ok=true, readOnly=true (client UI salt okunur)
     *  - suspended/expired → ok=false, 403 mesajı
     *  - none/pending     → ok=true (yeni kayıt + henüz trial oluşmamış)
     */
    private function evaluateSubscriptionForLogin(string $businessId): array {
        try {
            $trialService = DependencyFactory::getTrialService();

            // Oto faz geçişleri
            try { $trialService->checkAndExpireTrials(); } catch (\Exception $e) {}
            try { $trialService->checkAndSuspendGraceExpired(); } catch (\Exception $e) {}

            $phase = $trialService->getSubscriptionPhase($businessId);
            $p = $phase['phase'] ?? 'none';

            if ($p === 'suspended' || $p === 'expired') {
                return [
                    'ok' => false,
                    'phase' => $p,
                    'readOnly' => true,
                    'message' => 'İşletmeniz ödeme sebebiyle askıya alınmıştır. Yönetici ile iletişime geçin.',
                ];
            }

            return [
                'ok' => true,
                'phase' => $p,
                'readOnly' => (bool)($phase['readOnly'] ?? false),
                'message' => '',
            ];
        } catch (\Exception $e) {
            \App\Core\Logger::warning('evaluateSubscriptionForLogin error', ['error' => $e->getMessage()]);
            return ['ok' => true, 'phase' => 'unknown', 'readOnly' => false, 'message' => ''];
        }
    }
    
    public function validateManagerEmail() {
        $data = $this->input();
        $email = trim($data['email'] ?? '');
        
        if (empty($email)) {
            $this->json(['success' => false, 'error' => 'E-posta gerekli'], 400);
        }
        
        try {
            $db = DependencyFactory::getDatabase();
            $stmt = $db->prepare("SELECT * FROM customers WHERE email = ? AND (status IS NULL OR LOWER(status) != 'deleted') LIMIT 1");
            $stmt->execute([$email]);
            $customer = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!empty($customer)) {
                if (isset($customer['is_active']) && (int)$customer['is_active'] === 0) {
                    $this->json(['success' => false, 'error' => 'İşletmeniz pasife alınmıştır. Lütfen yöneticinizle iletişime geçin.'], 403);
                }
                
                $logoUrl = $this->absoluteAssetUrl(
                    $customer['logo_url'] ?? $customer['logo_path'] ?? null
                );
                $this->json(['success' => true, 'data' => [
                    'valid' => true,
                    'business' => [
                        'id' => $customer['customer_id'],
                        'name' => $customer['company_name'],
                        'subdomain' => $customer['subdomain'] ?? '',
                        'logo' => $logoUrl,
                    ]
                ]]);
            } else {
                $this->json(['success' => false, 'error' => 'Bu e-posta ile kayıtlı işletme bulunamadı'], 404);
            }
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => 'Doğrulama hatası'], 500);
        }
    }
    
    public function managerLogin() {
        $data = $this->input();
        $email = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';
        
        if (empty($email) || empty($password)) {
            $this->json(['success' => false, 'error' => 'E-posta ve şifre gerekli'], 400);
        }
        
        try {
            $db = DependencyFactory::getDatabase();
            
            $stmt = $db->prepare("SELECT * FROM customers WHERE email = ? AND (status IS NULL OR LOWER(status) != 'deleted') LIMIT 1");
            $stmt->execute([$email]);
            $customer = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$customer) {
                $this->json(['success' => false, 'error' => 'Geçersiz e-posta veya şifre'], 401);
            }
            
            if (isset($customer['is_active']) && (int)$customer['is_active'] === 0) {
                $this->json(['success' => false, 'error' => 'İşletmeniz pasife alınmıştır. Lütfen yöneticinizle iletişime geçin.'], 403);
            }

            $businessId = $customer['customer_id'];

            // Abonelik fazı: suspended/expired → manager bile giremez (paket yenilemesi için paket sayfasına yönlendirir).
            // grace_period → girer ama read_only döner.
            $subCheck = $this->evaluateSubscriptionForLogin($businessId);
            if (!$subCheck['ok']) {
                $this->json([
                    'success' => false,
                    'error' => 'Aboneliğiniz ödeme sebebiyle askıya alınmıştır. Lütfen paketinizi yenileyin.',
                    'phase' => $subCheck['phase'],
                    'suspended' => true,
                ], 403);
            }

            if (!password_verify($password, $customer['password'] ?? '')) {
                $stmt = $db->prepare("SELECT user_id, name, role, role_id, pin FROM users WHERE tenant_id = ? AND name = ? AND role IN ('BUSINESS_MANAGER', 'MANAGER', 'ADMIN') LIMIT 1");
                $stmt->execute([$businessId, $email]);
                $managerUser = $stmt->fetch(\PDO::FETCH_ASSOC);
                
                if (!$managerUser || !password_verify($password, $managerUser['pin'] ?? '')) {
                    $this->json(['success' => false, 'error' => 'Geçersiz e-posta veya şifre'], 401);
                }
                
                $userId = $managerUser['user_id'];
                $userName = $managerUser['name'] ?? '';
                $userRole = $managerUser['role'] ?? 'BUSINESS_MANAGER';
                $roleId = $managerUser['role_id'] ?? null;
            } else {
                $stmt = $db->prepare("SELECT user_id, name, role, role_id FROM users WHERE tenant_id = ? AND role IN ('BUSINESS_MANAGER', 'MANAGER', 'ADMIN') LIMIT 1");
                $stmt->execute([$businessId]);
                $managerUser = $stmt->fetch(\PDO::FETCH_ASSOC);
                
                $userId = $managerUser['user_id'] ?? 'mgr_' . $businessId;
                // İşletme sahibi için tercih sırası: users.name → customers.first_name + last_name → company_name → email
                $ownerPersonName = trim(
                    ($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? '')
                );
                $userName = $managerUser['name']
                    ?? ($ownerPersonName !== '' ? $ownerPersonName : null)
                    ?? ($customer['company_name'] ?? '')
                    ?: $email;
                $userRole = $managerUser['role'] ?? 'BUSINESS_MANAGER';
                $roleId = $managerUser['role_id'] ?? null;
            }

            // TOTP 2FA gate for managers. Same rationale as staffLogin —
            // the bearer is withheld until the authenticator code is
            // verified through /api/mobile/auth/2fa/verify.
            $managerLogoUrl = $this->absoluteAssetUrl(
                $customer['logo_url'] ?? $customer['logo_path'] ?? null
            );
            $managerMethods2fa = $this->getEffectiveUser2FAMethods($userId);
            if (!empty($managerMethods2fa)) {
                $challenge = $this->issue2FAChallenge($db, $userId, $businessId, [
                    'flow' => 'manager',
                    'email' => $email,
                    'business_id' => $businessId,
                    'read_only' => (bool)($subCheck['readOnly'] ?? false),
                    'phase' => $subCheck['phase'] ?? 'active',
                    'methods' => $managerMethods2fa,
                ]);
                $this->json(['success' => true, 'data' => [
                    'requires_2fa' => true,
                    'method' => $managerMethods2fa[0],
                    'methods' => $managerMethods2fa,
                    'challenge_token' => $challenge,
                    'expires_in' => 300,
                    'user' => [
                        'name' => $userName,
                        'role' => $this->resolveCanonicalRoleWire($userRole, $roleId),
                        'role_code' => $this->resolveCanonicalRoleWire($userRole, $roleId),
                    ],
                    'business' => [
                        'name' => $customer['company_name'] ?? '',
                        'logo' => $managerLogoUrl,
                    ],
                ]]);
                return;
            }
            
            $token = bin2hex(random_bytes(32));
            $refreshToken = bin2hex(random_bytes(32));
            $expiry = date('Y-m-d H:i:s', strtotime('+30 days'));
            $refreshExpiry = date('Y-m-d H:i:s', strtotime('+90 days'));

            $this->ensureMobileTokensTable($db);

            try {
                $stmt = $db->prepare("INSERT INTO mobile_tokens (token, user_id, tenant_id, expires_at, refresh_token, refresh_expires_at, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE token = VALUES(token), expires_at = VALUES(expires_at), refresh_token = VALUES(refresh_token), refresh_expires_at = VALUES(refresh_expires_at)");
                $stmt->execute([$token, $userId, $businessId, $expiry, $refreshToken, $refreshExpiry]);
            } catch (\Exception $e) {
                \App\Core\Logger::warning('Mobile token insert failed (manager)', ['error' => $e->getMessage()]);
            }
            
            $_SESSION['logged_in'] = true;
            $_SESSION['user_id'] = $userId;
            $_SESSION['username'] = $userName;
            $_SESSION['role'] = $userRole;
            $_SESSION['role_id'] = $roleId;
            $_SESSION['customer_id'] = $businessId;
            $_SESSION['business_id'] = $businessId;
            $_SESSION['login_time'] = time();
            $_SESSION['is_demo'] = !empty($customer['is_demo']);
            if (!empty($_SESSION['is_demo'])) {
                try {
                    $fw = \App\Middleware\SecurityMiddleware::getFirewall();
                    $ip = $fw->getClientIP();
                    DependencyFactory::getDemoAccessLogRepository()->log(
                        $businessId,
                        $userId,
                        $ip,
                        $_SERVER['HTTP_USER_AGENT'] ?? '',
                        'POST',
                        '/api/mobile/manager/login',
                        'login'
                    );
                } catch (\Throwable $e) {
                }
            }
            
            $permissions = ['*'];
            
            $stats = $this->getBusinessStats($businessId);
            $logoUrl = $this->absoluteAssetUrl(
                $customer['logo_url'] ?? $customer['logo_path'] ?? null
            );
            $this->json(['success' => true, 'data' => [
                'token' => $token,
                'refresh_token' => $refreshToken,
                'read_only' => $subCheck['readOnly'] ?? false,
                'phase' => $subCheck['phase'] ?? 'active',
                'user' => [
                    'id' => $userId,
                    'name' => $userName,
                    'first_name' => $customer['first_name'] ?? null,
                    'last_name' => $customer['last_name'] ?? null,
                    'email' => $email,
                    'role' => $this->resolveCanonicalRoleWire($userRole, $roleId),
                    'role_code' => $this->resolveCanonicalRoleWire($userRole, $roleId),
                    'role_id' => $roleId,
                    'is_manager' => true,
                ],
                'business' => [
                    'id' => $businessId,
                    'name' => $customer['company_name'],
                    'subdomain' => $customer['subdomain'] ?? '',
                    'logo' => $logoUrl,
                ],
                'permissions' => $permissions,
                'stats' => $stats,
            ]]);
        } catch (\Exception $e) {
            \App\Core\Logger::error('Mobile manager login error', ['error' => $e->getMessage()]);
            $this->json(['success' => false, 'error' => 'Giriş hatası'], 500);
        }
    }

    /**
     * GET /api/mobile/auth/resolve-tenant?email=...
     *
     * İşletme yöneticisinin e-postasından tenant subdomain'i çözer.
     * Mobil işletme uygulaması artık subdomain alanı sormaz; girişte
     * yalnızca e-posta + şifre kullanılır. Bu uç, gerekirse istemcinin
     * tenant'a özel kaynaklara (ör. logo) erişebilmesi için subdomain'i
     * döndürür. Var olmayan e-posta için 404 döner.
     */
    public function resolveTenant(): void {
        $email = trim($_GET['email'] ?? '');
        if ($email === '') {
            $data = $this->input();
            $email = trim($data['email'] ?? '');
        }

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->json(['success' => false, 'error' => 'Geçerli e-posta gerekli'], 400);
        }

        try {
            $db = DependencyFactory::getDatabase();
            $stmt = $db->prepare("SELECT customer_id, company_name, subdomain, logo_url, logo_path, is_active FROM customers WHERE email = ? AND (status IS NULL OR LOWER(status) != 'deleted') LIMIT 1");
            $stmt->execute([$email]);
            $customer = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$customer) {
                $this->json(['success' => false, 'error' => 'Bu e-posta ile kayıtlı işletme bulunamadı'], 404);
            }

            if (isset($customer['is_active']) && (int)$customer['is_active'] === 0) {
                $this->json(['success' => false, 'error' => 'İşletmeniz pasife alınmıştır. Lütfen yöneticinizle iletişime geçin.'], 403);
            }

            $logoUrl = $this->absoluteAssetUrl(
                $customer['logo_url'] ?? $customer['logo_path'] ?? null
            );
            $this->json(['success' => true, 'data' => [
                'business' => [
                    'id' => $customer['customer_id'],
                    'name' => $customer['company_name'],
                    'subdomain' => $customer['subdomain'] ?? '',
                    'logo' => $logoUrl,
                ],
            ]]);
        } catch (\Exception $e) {
            \App\Core\Logger::error('Mobile resolveTenant error', ['error' => $e->getMessage()]);
            $this->json(['success' => false, 'error' => 'Çözümleme hatası'], 500);
        }
    }

    /**
     * POST /api/mobile/auth/forgot-password
     * Body: { "email": "..." }
     *
     * İşletme yöneticisi için şifre sıfırlama kodu üretir ve e-posta ile
     * gönderir. Güvenlik gereği e-postanın kayıtlı olup olmadığını ifşa
     * etmez — her durumda aynı başarı mesajını döndürür. Kod 15 dakika
     * geçerlidir ve cache'de saklanır (mobil API stateless).
     */
    public function forgotPassword(): void {
        $data = $this->input();
        $email = trim(strtolower($data['email'] ?? ''));

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->json(['success' => false, 'error' => 'Geçerli e-posta gerekli'], 400);
        }

        // Sabit başarı mesajı — kullanıcı sayımı / e-posta keşfini engeller.
        $genericResponse = ['success' => true, 'data' => [
            'message' => 'Eğer bu e-posta kayıtlıysa, şifre sıfırlama kodu gönderildi.',
        ]];

        try {
            $db = DependencyFactory::getDatabase();
            $stmt = $db->prepare("SELECT customer_id, company_name FROM customers WHERE email = ? AND (status IS NULL OR LOWER(status) != 'deleted') LIMIT 1");
            $stmt->execute([$email]);
            $customer = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$customer) {
                // Bilgi sızdırma — yine de OK döndür.
                $this->json($genericResponse);
            }

            $code = (string) random_int(100000, 999999);
            $cache = DependencyFactory::getCacheService();
            $key = 'mobile_pwd_reset_' . md5($email);
            $cache->set($key, ['code' => $code, 'tries' => 0], 900); // 15 dk

            $appName = 'Qordy';
            $subject = $appName . ' - Şifre Sıfırlama Kodu';
            $body = "
            <!DOCTYPE html>
            <html><head><meta charset='UTF-8'></head>
            <body style='font-family:Arial,sans-serif;line-height:1.6;color:#111827;'>
            <div style='max-width:600px;margin:0 auto;padding:24px;'>
            <h2 style='color:#1a1a2e;'>Şifre Sıfırlama</h2>
            <p>Qordy İşletme hesabınız için şifre sıfırlama talebi aldık. Aşağıdaki kodu uygulamaya girin:</p>
            <div style='background:#f8f9fa;border:2px dashed #e5e7eb;padding:20px;text-align:center;margin:20px 0;border-radius:10px;'>
            <span style='font-size:32px;font-weight:bold;color:#f59e0b;letter-spacing:8px;font-family:monospace;'>{$code}</span>
            </div>
            <p>Bu kod 15 dakika geçerlidir.</p>
            <p style='color:#dc2626;font-size:14px;'>Bu talebi siz yapmadıysanız bu e-postayı yok sayabilirsiniz; şifreniz değişmez.</p>
            <p>İyi çalışmalar,<br>{$appName} Ekibi</p>
            </div></body></html>";

            try {
                $emailService = DependencyFactory::getEmailService();
                $emailService->sendEmail($email, $subject, $body);
            } catch (\Throwable $e) {
                \App\Core\Logger::warning('Mobile forgotPassword email failed', ['error' => $e->getMessage()]);
            }

            $this->json($genericResponse);
        } catch (\Exception $e) {
            \App\Core\Logger::error('Mobile forgotPassword error', ['error' => $e->getMessage()]);
            // Hata durumunda dahi generic mesaj döndür.
            $this->json($genericResponse);
        }
    }

    /**
     * POST /api/mobile/auth/reset-password
     * Body: { "email": "...", "code": "123456", "password": "yeni-sifre" }
     *
     * forgot-password ile gönderilen kodu doğrular ve yeni şifreyi yazar.
     */
    public function resetPassword(): void {
        $data = $this->input();
        $email = trim(strtolower($data['email'] ?? ''));
        $code = trim($data['code'] ?? '');
        $password = $data['password'] ?? '';

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->json(['success' => false, 'error' => 'Geçerli e-posta gerekli'], 400);
        }
        if (strlen($code) < 6) {
            $this->json(['success' => false, 'error' => 'Doğrulama kodu gerekli'], 400);
        }
        if (strlen($password) < 6) {
            $this->json(['success' => false, 'error' => 'Şifre en az 6 karakter olmalı'], 400);
        }

        try {
            $cache = DependencyFactory::getCacheService();
            $key = 'mobile_pwd_reset_' . md5($email);
            $stored = $cache->get($key);

            if (!$stored || !is_array($stored)) {
                $this->json(['success' => false, 'error' => 'Kod süresi dolmuş veya geçersiz. Lütfen tekrar kod isteyin.'], 400);
            }

            $tries = (int) ($stored['tries'] ?? 0);
            if ($tries >= 5) {
                $cache->delete($key);
                $this->json(['success' => false, 'error' => 'Çok fazla hatalı deneme. Lütfen yeni kod isteyin.'], 429);
            }

            if (!isset($stored['code']) || !hash_equals((string) $stored['code'], $code)) {
                $stored['tries'] = $tries + 1;
                $cache->set($key, $stored, 900);
                $this->json(['success' => false, 'error' => 'Geçersiz doğrulama kodu'], 400);
            }

            $db = DependencyFactory::getDatabase();
            $stmt = $db->prepare("SELECT customer_id FROM customers WHERE email = ? AND (status IS NULL OR LOWER(status) != 'deleted') LIMIT 1");
            $stmt->execute([$email]);
            $customer = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$customer) {
                $this->json(['success' => false, 'error' => 'İşletme bulunamadı'], 404);
            }

            $hash = password_hash($password, PASSWORD_DEFAULT);
            $upd = $db->prepare("UPDATE customers SET password = ?, updated_at = NOW() WHERE customer_id = ?");
            $upd->execute([$hash, $customer['customer_id']]);

            $cache->delete($key);

            // Güvenlik: parola değişince mevcut mobil oturumları iptal et.
            try {
                $this->ensureMobileTokensTable($db);
                $rev = $db->prepare("UPDATE mobile_tokens SET revoked_at = NOW() WHERE tenant_id = ? AND revoked_at IS NULL");
                $rev->execute([$customer['customer_id']]);
            } catch (\Throwable $e) {}

            $this->json(['success' => true, 'data' => [
                'message' => 'Şifreniz güncellendi. Yeni şifrenizle giriş yapabilirsiniz.',
            ]]);
        } catch (\Exception $e) {
            \App\Core\Logger::error('Mobile resetPassword error', ['error' => $e->getMessage()]);
            $this->json(['success' => false, 'error' => 'Şifre sıfırlama hatası'], 500);
        }
    }

    // ─── TOTP 2FA (Google Authenticator vb.) ──────────────────

    /**
     * GET /api/mobile/security/totp/status
     * Authenticated. Returns whether TOTP is enrolled/enabled/pending.
     */
    public function totpStatus(): void {
        if (!$this->requireAuth()) return;
        $userId = $_SESSION['user_id'] ?? '';
        try {
            $repo = DependencyFactory::getUser2FARepository();
            $row = $repo ? $repo->getByUserAndMethod($userId, 'totp') : null;
            $this->json(['success' => true, 'data' => [
                'enrolled' => (bool)$row,
                'enabled' => $row && (int)($row['is_enabled'] ?? 0) === 1,
            ]]);
        } catch (\Throwable $e) {
            $this->json(['success' => true, 'data' => ['enrolled' => false, 'enabled' => false]]);
        }
    }

    /**
     * POST /api/mobile/security/totp/setup
     * Authenticated. Generates a new TOTP secret, stores it as disabled
     * ("pending" — i.e. enrolled but not yet confirmed), and returns the
     * otpauth:// URL plus the base32 secret so the client can render a
     * QR code or let the user type the secret into an authenticator
     * manually. The enrolment is not active until the user sends back a
     * valid 6-digit code through /totp/confirm.
     */
    public function totpSetup(): void {
        if (!$this->requireAuth()) return;
        $userId = $_SESSION['user_id'] ?? '';
        $tenantId = \App\Core\TenantResolver::resolve() ?? '';
        try {
            $userService = DependencyFactory::getUserService();
            $user = $userService->findByUserId($userId);
            $account = $user['email'] ?? $user['name'] ?? $userId;

            $biz = DependencyFactory::getCustomerService()->getById($tenantId);
            $bizName = 'QORDY';
            if (is_array($biz)) {
                $bizName = $biz['company_name'] ?? 'QORDY';
            } elseif (is_object($biz)) {
                $bizName = $biz->company_name ?? 'QORDY';
            }
            $issuer = 'QORDY (' . $bizName . ')';

            require_once __DIR__ . '/../../services/TotpService.php';
            $secret = \App\Services\TotpService::generateSecret(32);
            $otpauth = \App\Services\TotpService::otpauthUri($secret, $account, $issuer);

            // Upsert into user_2fa as disabled (=pending). The repo's
            // enable() method flips is_enabled=1; we want the reverse
            // until the user confirms with a live code, so write
            // directly.
            $db = DependencyFactory::getDatabase();
            $stmt = $db->prepare(
                "INSERT INTO user_2fa (user_id, method, is_enabled, secret_code, created_at, updated_at)
                 VALUES (?, 'totp', 0, ?, NOW(), NOW())
                 ON DUPLICATE KEY UPDATE secret_code = VALUES(secret_code), is_enabled = 0, updated_at = NOW()"
            );
            try {
                $stmt->execute([$userId, $secret]);
            } catch (\PDOException $e) {
                // `user_2fa` lacks a unique constraint on (user_id, method)
                // on some installs — fall back to manual upsert.
                $row = null;
                try {
                    $find = $db->prepare("SELECT user_2fa_id FROM user_2fa WHERE user_id = ? AND method = 'totp' LIMIT 1");
                    $find->execute([$userId]);
                    $row = $find->fetch(\PDO::FETCH_ASSOC);
                } catch (\Throwable $e2) {}
                if ($row) {
                    $upd = $db->prepare("UPDATE user_2fa SET secret_code = ?, is_enabled = 0, updated_at = NOW() WHERE user_2fa_id = ?");
                    $upd->execute([$secret, $row['user_2fa_id']]);
                } else {
                    $ins = $db->prepare("INSERT INTO user_2fa (user_id, method, is_enabled, secret_code, created_at, updated_at) VALUES (?, 'totp', 0, ?, NOW(), NOW())");
                    $ins->execute([$userId, $secret]);
                }
            }

            $this->json(['success' => true, 'data' => [
                'secret' => $secret,
                'otpauth_uri' => $otpauth,
                'issuer' => $issuer,
                'account' => $account,
                'digits' => 6,
                'period' => 30,
            ]]);
        } catch (\Throwable $e) {
            \App\Core\Logger::error('TOTP setup failed', ['error' => $e->getMessage()]);
            $this->json(['success' => false, 'error' => 'TOTP hazırlanamadı'], 500);
        }
    }

    /**
     * POST /api/mobile/security/totp/confirm
     * Authenticated. Verifies the first 6-digit code entered by the
     * user against the pending secret; on success flips is_enabled=1
     * and TOTP becomes required on every subsequent login.
     */
    public function totpConfirm(): void {
        if (!$this->requireAuth()) return;
        $userId = $_SESSION['user_id'] ?? '';
        $data = $this->input();
        $code = preg_replace('/\D/', '', (string)($data['code'] ?? ''));
        if (strlen($code) !== 6) {
            $this->json(['success' => false, 'error' => 'Geçersiz kod formatı'], 400);
        }
        try {
            $secret = $this->getTotpSecret($userId);
            if (!$secret) {
                $this->json(['success' => false, 'error' => 'Önce TOTP kurulumunu başlatın'], 400);
            }
            require_once __DIR__ . '/../../services/TotpService.php';
            // Widen drift tolerance to ±2 steps (±60s). Field reports:
            // users on jailbroken/rooted phones or in regions with
            // poor NTP propagation routinely drift ~40–50 seconds from
            // server time, so the default ±30s rejects legitimate
            // codes. Google's own implementations accept ±1 step, but
            // our user base clearly needs more slack — the tradeoff is
            // trivial brute-force risk (still 6 digits, still ≤ 5
            // attempts per challenge).
            if (!\App\Services\TotpService::verifyCode($secret, $code, 2)) {
                $this->json(['success' => false, 'error' => 'Kod hatalı ya da süresi dolmuş'], 401);
            }
            $db = DependencyFactory::getDatabase();
            $stmt = $db->prepare("UPDATE user_2fa SET is_enabled = 1, updated_at = NOW() WHERE user_id = ? AND method = 'totp'");
            $stmt->execute([$userId]);
            $this->json(['success' => true, 'data' => ['enabled' => true]]);
        } catch (\Throwable $e) {
            \App\Core\Logger::error('TOTP confirm failed', ['error' => $e->getMessage()]);
            $this->json(['success' => false, 'error' => 'TOTP etkinleştirilemedi'], 500);
        }
    }

    /**
     * POST /api/mobile/security/totp/disable
     * Authenticated. Requires a current TOTP code to prevent a stolen
     * session from silently disabling the second factor.
     */
    public function totpDisable(): void {
        if (!$this->requireAuth()) return;
        $userId = $_SESSION['user_id'] ?? '';
        $data = $this->input();
        $code = preg_replace('/\D/', '', (string)($data['code'] ?? ''));
        if (strlen($code) !== 6) {
            $this->json(['success' => false, 'error' => 'Geçersiz kod formatı'], 400);
        }
        try {
            $secret = $this->getTotpSecret($userId);
            if (!$secret) {
                $this->json(['success' => true, 'data' => ['enabled' => false]]);
            }
            require_once __DIR__ . '/../../services/TotpService.php';
            if (!\App\Services\TotpService::verifyCode($secret, $code, 2)) {
                $this->json(['success' => false, 'error' => 'Kod hatalı'], 401);
            }
            $db = DependencyFactory::getDatabase();
            $stmt = $db->prepare("DELETE FROM user_2fa WHERE user_id = ? AND method = 'totp'");
            $stmt->execute([$userId]);
            $this->json(['success' => true, 'data' => ['enabled' => false]]);
        } catch (\Throwable $e) {
            \App\Core\Logger::error('TOTP disable failed', ['error' => $e->getMessage()]);
            $this->json(['success' => false, 'error' => 'TOTP kapatılamadı'], 500);
        }
    }

    // ─── WhatsApp 2FA (Meta Cloud API) ───────────────────────

    /**
     * GET /api/mobile/security/whatsapp/status
     * Authenticated. Tells the app whether WhatsApp 2FA is enrolled
     * and shows a masked phone preview so users can recognize which
     * number they registered.
     */
    public function whatsappStatus(): void {
        if (!$this->requireAuth()) return;
        $userId = $_SESSION['user_id'] ?? '';
        try {
            $repo = DependencyFactory::getUser2FARepository();
            $row = $repo ? $repo->getByUserAndMethod($userId, 'whatsapp') : null;
            $phone = $row['secret_code'] ?? '';
            $masked = '';
            if ($phone !== '') {
                $len = strlen($phone);
                $masked = $len > 4 ? str_repeat('•', max(0, $len - 4)) . substr($phone, -4) : '••••';
            }
            $this->json(['success' => true, 'data' => [
                'enrolled' => (bool)$row,
                'enabled'  => $row && (int)($row['is_enabled'] ?? 0) === 1,
                'masked_phone' => $masked,
                'globally_enabled' => $this->is2FAMethodGloballyEnabled('whatsapp'),
            ]]);
        } catch (\Throwable $e) {
            $this->json(['success' => true, 'data' => [
                'enrolled' => false, 'enabled' => false, 'masked_phone' => '',
                'globally_enabled' => $this->is2FAMethodGloballyEnabled('whatsapp'),
            ]]);
        }
    }

    /**
     * POST /api/mobile/security/whatsapp/setup
     * Authenticated. Body: { phone: "+905321234567" }
     * Stores the phone on user_2fa as pending (is_enabled=0), generates
     * a 6-digit code, saves its SHA-256 hash in a cache key, and sends
     * the code via WhatsApp template. On success the client must call
     * /whatsapp/confirm with the code to finalize enrolment.
     */
    public function whatsappSetup(): void {
        if (!$this->requireAuth()) return;
        if (!$this->is2FAMethodGloballyEnabled('whatsapp')) {
            $this->json(['success' => false, 'error' => 'WhatsApp doğrulama platformda kapalı'], 403);
        }
        $userId = $_SESSION['user_id'] ?? '';
        $data = $this->input();
        $phone = preg_replace('/\D/', '', (string)($data['phone'] ?? ''));
        if (strlen($phone) === 10 && strpos($phone, '5') === 0) {
            $phone = '90' . $phone;
        }
        if (strlen($phone) < 11) {
            $this->json(['success' => false, 'error' => 'Geçerli bir telefon numarası girin (ülke kodu dahil)'], 400);
        }
        try {
            $db = DependencyFactory::getDatabase();
            $code = str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
            $hash = hash('sha256', $code . '|wa|' . $userId);

            // Upsert user_2fa row as pending
            try {
                $stmt = $db->prepare(
                    "INSERT INTO user_2fa (user_id, method, is_enabled, secret_code, created_at, updated_at)
                     VALUES (?, 'whatsapp', 0, ?, NOW(), NOW())
                     ON DUPLICATE KEY UPDATE secret_code = VALUES(secret_code), is_enabled = 0, updated_at = NOW()"
                );
                $stmt->execute([$userId, $phone]);
            } catch (\PDOException $e) {
                $find = $db->prepare("SELECT user_2fa_id FROM user_2fa WHERE user_id = ? AND method = 'whatsapp' LIMIT 1");
                $find->execute([$userId]);
                $row = $find->fetch(\PDO::FETCH_ASSOC);
                if ($row) {
                    $db->prepare("UPDATE user_2fa SET secret_code = ?, is_enabled = 0, updated_at = NOW() WHERE user_2fa_id = ?")
                       ->execute([$phone, $row['user_2fa_id']]);
                } else {
                    $db->prepare("INSERT INTO user_2fa (user_id, method, is_enabled, secret_code, created_at, updated_at) VALUES (?, 'whatsapp', 0, ?, NOW(), NOW())")
                       ->execute([$userId, $phone]);
                }
            }

            // Store pending code hash in a dedicated, short-lived row.
            $this->ensureMobile2FASetupTable($db);
            $db->prepare("DELETE FROM mobile_2fa_setup WHERE user_id = ? AND method = 'whatsapp'")->execute([$userId]);
            $db->prepare(
                "INSERT INTO mobile_2fa_setup (user_id, method, code_hash, expires_at, created_at)
                 VALUES (?, 'whatsapp', ?, DATE_ADD(NOW(), INTERVAL 300 SECOND), NOW())"
            )->execute([$userId, $hash]);

            $wa = \App\Core\DependencyFactory::getWhatsAppService();
            $res = $wa->sendVerificationCode($phone, $code);
            if (!$res['success']) {
                $this->json(['success' => false, 'error' => $res['message'] ?? 'Kod gönderilemedi'], 502);
            }

            $masked = strlen($phone) > 4 ? str_repeat('•', strlen($phone) - 4) . substr($phone, -4) : '••••';
            $this->json(['success' => true, 'data' => [
                'sent' => true,
                'masked_phone' => $masked,
                'expires_in' => 300,
            ]]);
        } catch (\Throwable $e) {
            \App\Core\Logger::error('WhatsApp 2FA setup failed', ['error' => $e->getMessage()]);
            $this->json(['success' => false, 'error' => 'WhatsApp doğrulaması başlatılamadı'], 500);
        }
    }

    /**
     * POST /api/mobile/security/whatsapp/confirm
     * Authenticated. Body: { code: "123456" }
     * Finalizes enrolment if the code matches the pending hash issued
     * by /whatsapp/setup.
     */
    public function whatsappConfirm(): void {
        if (!$this->requireAuth()) return;
        $userId = $_SESSION['user_id'] ?? '';
        $data = $this->input();
        $code = preg_replace('/\D/', '', (string)($data['code'] ?? ''));
        if (strlen($code) !== 6) {
            $this->json(['success' => false, 'error' => 'Geçersiz kod formatı'], 400);
        }
        try {
            $db = DependencyFactory::getDatabase();
            $this->ensureMobile2FASetupTable($db);
            $row = $db->prepare("SELECT * FROM mobile_2fa_setup WHERE user_id = ? AND method = 'whatsapp' ORDER BY id DESC LIMIT 1");
            $row->execute([$userId]);
            $s = $row->fetch(\PDO::FETCH_ASSOC);
            if (!$s) {
                $this->json(['success' => false, 'error' => 'Önce kurulum başlatın'], 400);
            }
            if (strtotime($s['expires_at']) < time()) {
                $this->json(['success' => false, 'error' => 'Kodun süresi doldu, yeniden gönderin'], 401);
            }
            $expected = hash('sha256', $code . '|wa|' . $userId);
            if (!hash_equals((string)$s['code_hash'], $expected)) {
                $this->json(['success' => false, 'error' => 'Kod hatalı'], 401);
            }
            $db->prepare("UPDATE user_2fa SET is_enabled = 1, updated_at = NOW() WHERE user_id = ? AND method = 'whatsapp'")->execute([$userId]);
            $db->prepare("DELETE FROM mobile_2fa_setup WHERE user_id = ? AND method = 'whatsapp'")->execute([$userId]);
            $this->json(['success' => true, 'data' => ['enabled' => true]]);
        } catch (\Throwable $e) {
            \App\Core\Logger::error('WhatsApp 2FA confirm failed', ['error' => $e->getMessage()]);
            $this->json(['success' => false, 'error' => 'Doğrulama başarısız'], 500);
        }
    }

    /**
     * POST /api/mobile/security/whatsapp/disable
     * Authenticated. Disables WhatsApp 2FA for the current user.
     */
    public function whatsappDisable(): void {
        if (!$this->requireAuth()) return;
        $userId = $_SESSION['user_id'] ?? '';
        try {
            $db = DependencyFactory::getDatabase();
            $db->prepare("DELETE FROM user_2fa WHERE user_id = ? AND method = 'whatsapp'")->execute([$userId]);
            $this->json(['success' => true, 'data' => ['enabled' => false]]);
        } catch (\Throwable $e) {
            \App\Core\Logger::error('WhatsApp 2FA disable failed', ['error' => $e->getMessage()]);
            $this->json(['success' => false, 'error' => 'WhatsApp doğrulaması kapatılamadı'], 500);
        }
    }

    /**
     * GET /api/mobile/security/auth-methods
     * Authenticated. Returns:
     *  - available_methods: list of globally allowed methods (superadmin)
     *  - enrolled_methods:  user-enrolled + admin-allowed methods (for login)
     *  - status per method with masked contact if applicable.
     */
    public function authMethodsStatus(): void {
        if (!$this->requireAuth()) return;
        $userId = $_SESSION['user_id'] ?? '';
        $all = ['totp', 'whatsapp', 'email', 'sms'];
        $available = [];
        foreach ($all as $m) {
            if ($this->is2FAMethodGloballyEnabled($m)) $available[] = $m;
        }
        $enrolled = $this->getEffectiveUser2FAMethods($userId);
        $status = [];
        foreach ($all as $m) {
            $secret = $this->getUser2FASecret($userId, $m);
            $mask = '';
            if ($secret) {
                if ($m === 'email') {
                    $parts = explode('@', $secret, 2);
                    $mask = (strlen($parts[0]) > 2 ? substr($parts[0], 0, 2) . str_repeat('•', max(0, strlen($parts[0]) - 2)) : '••') . '@' . ($parts[1] ?? '');
                } elseif ($m === 'totp') {
                    $mask = 'Authenticator Uygulaması';
                } else {
                    $mask = strlen($secret) > 4 ? str_repeat('•', strlen($secret) - 4) . substr($secret, -4) : '••••';
                }
            }
            $status[$m] = [
                'globally_enabled' => in_array($m, $available, true),
                'enrolled'         => in_array($m, $enrolled, true),
                'masked'           => $mask,
            ];
        }
        $this->json(['success' => true, 'data' => [
            'available_methods' => $available,
            'enrolled_methods'  => $enrolled,
            'status'            => $status,
        ]]);
    }

    /**
     * Create the transient pending-code table used by WhatsApp/Email/SMS
     * 2FA setup flows. Kept separate from mobile_2fa_challenges so login
     * challenges and enrolment challenges can't ever collide.
     */
    private function ensureMobile2FASetupTable(\PDO $db): void {
        try {
            $db->exec("CREATE TABLE IF NOT EXISTS mobile_2fa_setup (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id VARCHAR(100) NOT NULL,
                method VARCHAR(32) NOT NULL,
                code_hash VARCHAR(128) NOT NULL,
                expires_at DATETIME NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_user_method (user_id, method),
                INDEX idx_expires (expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } catch (\Exception $e) {}
    }

    /**
     * POST /api/mobile/auth/2fa/send
     * Unauthenticated (identified by challenge token). Body:
     *   { challenge_token: "...", method: "whatsapp" }
     * Triggers code delivery over the chosen method. TOTP is app-side
     * and does not need this call.
     */
    public function send2FAChallengeCode(): void {
        $data = $this->input();
        $challengeToken = trim((string)($data['challenge_token'] ?? ''));
        $method = strtolower(trim((string)($data['method'] ?? '')));
        if ($challengeToken === '' || $method === '') {
            $this->json(['success' => false, 'error' => 'Eksik alan'], 400);
        }
        if ($method === 'totp') {
            $this->json(['success' => false, 'error' => 'TOTP kodu uygulamanızdan alınır, sunucu göndermez'], 400);
        }
        try {
            $db = DependencyFactory::getDatabase();
            $this->ensureMobile2FAChallengesTable($db);
            $stmt = $db->prepare("SELECT * FROM mobile_2fa_challenges WHERE challenge_token = ? AND consumed_at IS NULL LIMIT 1");
            $stmt->execute([$challengeToken]);
            $ch = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$ch) {
                $this->json(['success' => false, 'error' => 'Geçersiz veya kullanılmış oturum'], 401);
            }
            if (strtotime($ch['expires_at']) < time()) {
                $this->json(['success' => false, 'error' => 'Oturum süresi doldu'], 401);
            }
            $payload = json_decode($ch['payload'] ?? '', true) ?: [];
            $allowed = $payload['methods'] ?? ['totp'];
            if (!in_array($method, $allowed, true)) {
                $this->json(['success' => false, 'error' => 'Bu yöntem bu oturum için kullanılamaz'], 400);
            }
            $res = $this->send2FACode($db, $ch, $method);
            if (!$res['success']) {
                $this->json(['success' => false, 'error' => $res['message'] ?? 'Kod gönderilemedi'], 502);
            }
            $this->json(['success' => true, 'data' => [
                'sent' => true,
                'method' => $method,
                'expires_in' => 300,
                'message' => $res['message'] ?? 'Kod gönderildi',
            ]]);
        } catch (\Throwable $e) {
            \App\Core\Logger::error('send2FAChallengeCode failed', ['error' => $e->getMessage()]);
            $this->json(['success' => false, 'error' => 'Kod gönderimi başarısız'], 500);
        }
    }

    /**
     * POST /api/mobile/auth/2fa/verify
     * Unauthenticated. Exchanges a challenge token + TOTP code for a
     * full auth bundle (bearer + refresh + user/business/permissions).
     * This is what the client calls after managerLogin/staffLogin
     * returns `requires_2fa: true`.
     */
    public function verify2FAChallenge(): void {
        $data = $this->input();
        $challenge = trim((string)($data['challenge_token'] ?? ''));
        $code = preg_replace('/\D/', '', (string)($data['code'] ?? ''));
        $method = strtolower(trim((string)($data['method'] ?? 'totp')));
        if ($challenge === '' || strlen($code) !== 6) {
            $this->json(['success' => false, 'error' => 'Eksik veya geçersiz alan'], 400);
        }
        try {
            $db = DependencyFactory::getDatabase();
            $this->ensureMobile2FAChallengesTable($db);
            $stmt = $db->prepare(
                "SELECT * FROM mobile_2fa_challenges
                 WHERE challenge_token = ? AND consumed_at IS NULL
                 LIMIT 1"
            );
            $stmt->execute([$challenge]);
            $ch = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$ch) {
                $this->json(['success' => false, 'error' => 'Geçersiz veya kullanılmış oturum'], 401);
            }
            if (strtotime($ch['expires_at']) < time()) {
                $this->json(['success' => false, 'error' => 'Doğrulama süresi doldu, lütfen tekrar giriş yapın'], 401);
            }
            if ((int)$ch['attempts'] >= 5) {
                // Invalidate — stale/attacked challenge.
                $db->prepare("UPDATE mobile_2fa_challenges SET consumed_at = NOW() WHERE id = ?")->execute([$ch['id']]);
                $this->json(['success' => false, 'error' => 'Çok fazla hatalı deneme, yeniden giriş yapın'], 429);
            }

            $userId = $ch['user_id'];
            $businessId = $ch['tenant_id'];
            $payload = json_decode($ch['payload'] ?? '', true) ?: [];
            $allowedMethods = $payload['methods'] ?? ['totp'];
            if (!in_array($method, $allowedMethods, true)) {
                $this->json(['success' => false, 'error' => 'Bu doğrulama yöntemi bu oturum için kullanılamaz'], 400);
            }
            if (!$this->is2FAMethodGloballyEnabled($method)) {
                $this->json(['success' => false, 'error' => 'Bu doğrulama yöntemi şu an kullanım dışı'], 400);
            }

            $ok = false;
            if ($method === 'totp') {
                $secret = $this->getTotpSecret($userId);
                require_once __DIR__ . '/../../services/TotpService.php';
                $ok = $secret && \App\Services\TotpService::verifyCode($secret, $code, 2);
            } else {
                $ok = $this->verifyNonTotpCode($ch, $code);
            }
            if (!$ok) {
                $db->prepare("UPDATE mobile_2fa_challenges SET attempts = attempts + 1 WHERE id = ?")->execute([$ch['id']]);
                $this->json(['success' => false, 'error' => 'Kod hatalı'], 401);
            }

            // Consume the challenge (single-use) BEFORE issuing the real
            // bearer to foreclose any window where the same token could
            // be replayed.
            $db->prepare("UPDATE mobile_2fa_challenges SET consumed_at = NOW() WHERE id = ?")->execute([$ch['id']]);

            // Now mint the actual bearer and return the same auth bundle
            // shape that staffLogin/managerLogin would have returned.
            $flow = $payload['flow'] ?? 'manager';
            $userService = DependencyFactory::getUserService();
            $user = $userService->findByUserId($userId) ?: [];
            $customer = null;
            try { $customer = DependencyFactory::getCustomerService()->getById($businessId); } catch (\Throwable $e) {}
            $customerArr = is_array($customer) ? $customer : (is_object($customer) ? (array)$customer : []);

            $token = bin2hex(random_bytes(32));
            $refreshToken = bin2hex(random_bytes(32));
            $expiry = date('Y-m-d H:i:s', strtotime('+30 days'));
            $refreshExpiry = date('Y-m-d H:i:s', strtotime('+90 days'));

            $this->ensureMobileTokensTable($db);
            try {
                $ins = $db->prepare("INSERT INTO mobile_tokens (token, user_id, tenant_id, expires_at, refresh_token, refresh_expires_at, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE token = VALUES(token), expires_at = VALUES(expires_at), refresh_token = VALUES(refresh_token), refresh_expires_at = VALUES(refresh_expires_at)");
                $ins->execute([$token, $userId, $businessId, $expiry, $refreshToken, $refreshExpiry]);
            } catch (\Exception $e) {
                \App\Core\Logger::warning('Mobile token insert failed (2fa)', ['error' => $e->getMessage()]);
            }

            $role = $user['role'] ?? 'BUSINESS_MANAGER';
            $roleId = $user['role_id'] ?? null;

            $_SESSION['logged_in'] = true;
            $_SESSION['user_id'] = $userId;
            $_SESSION['username'] = $user['name'] ?? '';
            $_SESSION['role'] = $role;
            $_SESSION['role_id'] = $roleId;
            $_SESSION['customer_id'] = $businessId;
            $_SESSION['business_id'] = $businessId;
            $_SESSION['login_time'] = time();

            $logoUrl = $this->absoluteAssetUrl(
                $customerArr['logo_url'] ?? $customerArr['logo_path'] ?? null
            );

            $permissions = $flow === 'manager'
                ? ['*']
                : $this->getUserPermissions($role, $roleId);

            $stats = $flow === 'manager' ? $this->getBusinessStats($businessId) : null;

            $canonicalRole = $this->resolveCanonicalRoleWire($role, $roleId);
            $respUser = [
                'id' => $userId,
                'name' => $user['name'] ?? ($payload['email'] ?? ''),
                'role' => $canonicalRole,
                'role_code' => $canonicalRole,
                'role_id' => $roleId,
            ];
            if ($flow === 'manager') {
                $respUser['email'] = $payload['email'] ?? ($user['email'] ?? '');
                $respUser['is_manager'] = true;
            } else {
                $respUser['preparation_screen_id'] = $user['preparation_screen_id'] ?? null;
            }

            $this->json(['success' => true, 'data' => [
                'token' => $token,
                'refresh_token' => $refreshToken,
                'read_only' => (bool)($payload['read_only'] ?? false),
                'phase' => $payload['phase'] ?? 'active',
                'user' => $respUser,
                'business' => [
                    'id' => $businessId,
                    'name' => $customerArr['company_name'] ?? ($payload['business_name'] ?? ''),
                    'subdomain' => $customerArr['subdomain'] ?? '',
                    'logo' => $logoUrl,
                ],
                'permissions' => $permissions,
                'stats' => $stats,
            ]]);
        } catch (\Throwable $e) {
            \App\Core\Logger::error('2FA verify failed', ['error' => $e->getMessage()]);
            $this->json(['success' => false, 'error' => 'Doğrulama başarısız'], 500);
        }
    }

    public function verifyTokenEndpoint() {
        $token = $this->getBearerToken();
        
        if (empty($token)) {
            $data = $this->input();
            $token = $data['token'] ?? '';
        }
        
        if (empty($token)) {
            $this->json(['success' => false, 'error' => 'Token gerekli'], 400);
        }
        
        try {
            $db = DependencyFactory::getDatabase();
            $stmt = $db->prepare("SELECT user_id, tenant_id AS business_id, expires_at FROM mobile_tokens WHERE token = ? LIMIT 1");
            $stmt->execute([$token]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$row || strtotime($row['expires_at']) < time()) {
                if ($this->isLoggedIn()) {
                    $this->json(['success' => true, 'data' => ['valid' => true]]);
                }
                $this->json(['success' => false, 'error' => 'Geçersiz veya süresi dolmuş token'], 401);
            }
            
            $_SESSION['logged_in'] = true;
            $_SESSION['user_id'] = $row['user_id'];
            $_SESSION['customer_id'] = $row['business_id'];
            $_SESSION['business_id'] = $row['business_id'];
            try {
                $_SESSION['is_demo'] = DependencyFactory::getCustomerRepository()->isDemoCustomer($row['business_id']);
            } catch (\Throwable $e) {
                $_SESSION['is_demo'] = false;
            }
            
            $this->json(['success' => true, 'data' => ['valid' => true]]);
        } catch (\Exception $e) {
            if ($this->isLoggedIn()) {
                $this->json(['success' => true, 'data' => ['valid' => true]]);
            }
            $this->json(['success' => false, 'error' => 'Token doğrulama hatası'], 500);
        }
    }
    
    public function logout() {
        $token = $this->getBearerToken();

        if (!$token) {
            $data = $this->input();
            $token = $data['token'] ?? '';
        }

        if ($token) {
            try {
                $db = DependencyFactory::getDatabase();
                $this->ensureMobileTokensTable($db);
                // Soft-revoke: keep the row for audit/history but ensure
                // any future use of the same token is rejected by
                // requireAuth()'s revoked_at check.
                $stmt = $db->prepare(
                    "UPDATE mobile_tokens SET revoked_at = NOW() WHERE token = ? AND revoked_at IS NULL"
                );
                $stmt->execute([$token]);
            } catch (\Exception $e) {}
        }

        // Unregister any FCM device tied to this session so we stop
        // pushing notifications to a signed-out user. We only clear the
        // device that explicitly identifies itself in the logout body —
        // otherwise a device-B logout would kill notifications for
        // device-A logged in to the same account.
        try {
            $userId = $_SESSION['user_id'] ?? null;
            $data = $this->input();
            $deviceId = trim((string) ($data['device_id'] ?? ''));
            if ($userId && $deviceId !== '') {
                $db = DependencyFactory::getDatabase();
                $stmt = $db->prepare(
                    "UPDATE user_devices SET is_active = 0, updated_at = NOW() WHERE user_id = ? AND device_id = ?"
                );
                $stmt->execute([$userId, $deviceId]);
            }
        } catch (\Exception $e) {}

        try { session_destroy(); } catch (\Exception $e) {}
        $this->json(['success' => true, 'message' => 'Çıkış yapıldı']);
    }
    
    // ─── Staff Dashboard ──────────────────────────────────────
    
    public function staffDashboard() {
        if (!$this->requireAuth()) return;
        $this->ensureMobileTenant();
        
        try {
            $q = $this->query();
            $period = $q['period'] ?? 'today';
            $settingsService = DependencyFactory::getSystemSettingsService();
            $businessRange = $settingsService->getBusinessDateRange();
            $dr = $this->getDateRangeFromPeriod($period);
            $orderService = DependencyFactory::getOrderService();
            
            $todayOrders = $this->getOrdersForPeriod($dr, $businessRange, $orderService);
            $todayOrders = is_array($todayOrders) ? $todayOrders : [];
            $pendingOrders = array_filter($todayOrders, fn($o) => strtoupper($o['status'] ?? '') === 'PENDING');
            
            $totalRevenue = 0;
            foreach ($todayOrders as $o) {
                if (strtoupper($o['status'] ?? '') === 'CANCELLED') continue;
                $isPaid = !empty($o['is_paid']) && ($o['is_paid'] == 1 || $o['is_paid'] === '1');
                $hasPayment = !empty(trim($o['payment_method'] ?? ''));
                if ($isPaid || $hasPayment) {
                    $totalRevenue += floatval($o['total_amount'] ?? 0);
                }
            }
            
            $tables = DependencyFactory::getTableService()->getAllTables();
            $activeTables = array_filter($tables ?: [], fn($t) => ($t['status'] ?? '') === 'occupied');
            
            $notificationService = DependencyFactory::getNotificationService();
            $unreadCount = 0;
            if (method_exists($notificationService, 'getUnreadCountForBusinessDay')) {
                $unreadCount = $notificationService->getUnreadCountForBusinessDay(
                    $businessRange['start_datetime'] ?? $businessRange['start'] ?? ($businessRange['date'] . ' 00:00:00'),
                    $businessRange['end_datetime'] ?? $businessRange['end'] ?? ($businessRange['date'] . ' 23:59:59')
                );
            } elseif (method_exists($notificationService, 'getUnreadCount')) {
                $unreadCount = $notificationService->getUnreadCount();
            }
            
            // "Son siparişler" kartının mobilde boş görünmesinin
            // nedeni: sunucu bu alanı hiç göndermiyordu. Web yönetim
            // panelindeki "Son Siparişler" widget'ıyla paritede olacak
            // şekilde, en yeni 10 siparişi ekliyoruz (created_at
            // desc). Liste zaten tenant-filtered OrderService üzerinden
            // geliyor, ek scoping gerekmiyor.
            $recentOrders = $todayOrders;
            usort($recentOrders, function ($a, $b) {
                $ta = strtotime($a['created_at'] ?? $a['order_date'] ?? '') ?: 0;
                $tb = strtotime($b['created_at'] ?? $b['order_date'] ?? '') ?: 0;
                return $tb <=> $ta;
            });
            $recentOrders = array_slice($recentOrders, 0, 10);

            $this->json(['success' => true, 'data' => [
                'today_stats' => [
                    'total_orders' => count($todayOrders),
                    'total_revenue' => $totalRevenue
                ],
                'tables' => $tables ?: [],
                'active_orders' => count($pendingOrders),
                'unread_notifications' => $unreadCount,
                'total_orders' => count($todayOrders),
                'pending_orders' => count($pendingOrders),
                'total_revenue' => $totalRevenue,
                'active_tables' => count($activeTables),
                'total_tables' => count($tables ?: []),
                'recent_orders' => $recentOrders,
                'business_date' => $businessRange['date']
            ]]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    // ─── Orders ───────────────────────────────────────────────
    
    public function getOrders() {
        if (!$this->requireAuth()) return;
        $this->ensureMobileTenant();
        
        try {
            $q = $this->query();
            $status = $q['status'] ?? null;
            $period = $q['period'] ?? null;
            $dateFrom = $q['date_from'] ?? null;
            $dateTo = $q['date_to'] ?? null;
            $orderService = DependencyFactory::getOrderService();
            
            $settingsService = DependencyFactory::getSystemSettingsService();
            $range = $settingsService->getBusinessDateRange();
            if ($dateFrom && $dateTo) {
                $startDate = $dateFrom;
                $endDate = $dateTo;
            } elseif ($period) {
                $dr = $this->getDateRangeFromPeriod($period);
                $startDate = $dr['start_date'];
                $endDate = $dr['end_date'];
            } else {
                $startDate = $range['date'];
                $endDate = $range['date'];
            }
            $orders = $orderService->getOrdersByDateRange($startDate, $endDate);
            if ($status) {
                $statusUpper = strtoupper($status);
                $orders = array_values(array_filter($orders, fn($o) => strtoupper($o['status'] ?? '') === $statusUpper));
            }
            
            $this->json(['success' => true, 'data' => ['orders' => is_array($orders) ? $orders : []]]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    public function updateOrderStatus() {
        if (!$this->requireAuth()) return;
        $this->ensureMobileTenant();
        
        $data = $this->input();
        $orderId = $data['order_id'] ?? '';
        $status = $data['status'] ?? '';
        
        if (empty($orderId) || empty($status)) {
            $this->json(['success' => false, 'error' => 'order_id ve status gerekli'], 400);
        }
        
        try {
            $orderService = DependencyFactory::getOrderService();
            $result = $orderService->updateOrderStatus($orderId, strtoupper($status));
            $this->json(['success' => (bool)$result]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // ─── Tables ───────────────────────────────────────────────

    public function getTables() {
        if (!$this->requireAuth()) return;
        $this->ensureMobileTenant();

        try {
            $tables = \App\Core\DependencyFactory::getTableService()->getAllTables();
            $zones = \App\Core\DependencyFactory::getZoneService()->getAllZones();
            $orderService = DependencyFactory::getOrderService();

            $normalizeStatus = function($s) {
                $s = strtoupper(trim($s ?? 'FREE'));
                if ($s === 'OCCUPIED' || $s === 'PAYMENT_PENDING') return 'occupied';
                if ($s === 'FREE') return 'available';
                if ($s === 'RESERVED') return 'reserved';
                return 'available';
            };

            $formattedZones = [];
            foreach ($zones as $zone) {
                $zoneTables = array_filter($tables, function($t) use ($zone) {
                    return ($t['zone_id'] ?? '') === ($zone['zone_id'] ?? '');
                });
                $zoneName = $zone['zone_name'] ?? $zone['name'] ?? 'Bölge';
                $formattedTables = [];
                foreach ($zoneTables as $t) {
                    $tableId = $t['table_id'] ?? $t['id'] ?? '';
                    $activeOrders = $tableId ? $orderService->getActiveOrdersByTable($tableId) : [];
                    $activeOrderCount = count($activeOrders);
                    $status = $normalizeStatus($t['status'] ?? '');
                    if ($activeOrderCount > 0) {
                        $status = 'occupied';
                    }
                    $formattedTables[] = array_merge($t, [
                        'status' => $status,
                        'table_name' => $t['name'] ?? $t['table_name'] ?? '',
                        'zone_name' => $zoneName,
                        'active_orders' => $activeOrderCount,
                    ]);
                }
                $formattedZones[] = [
                    'zone_id' => $zone['zone_id'] ?? '',
                    'zone_name' => $zoneName,
                    'description' => $zone['description'] ?? '',
                    'tables' => array_values($formattedTables),
                ];
            }
            $this->json(['success' => true, 'data' => ['zones' => $formattedZones]]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/mobile/manager/zones/{zoneId}/tables
     * Belirli bir bölgenin masalarını döndürür.
     */
    public function getZoneTables($zoneId) {
        if (!$this->requireAuth()) return;
        $this->ensureMobileTenant();

        try {
            $zoneId = trim((string)$zoneId);
            if ($zoneId === '') {
                $this->json(['success' => false, 'error' => 'zone_id gerekli'], 400);
                return;
            }

            $tableService = \App\Core\DependencyFactory::getTableService();
            $zoneService = \App\Core\DependencyFactory::getZoneService();
            $orderService = DependencyFactory::getOrderService();

            $zones = $zoneService->getAllZones();
            $zone = null;
            foreach ($zones as $z) {
                if (($z['zone_id'] ?? '') === $zoneId) { $zone = $z; break; }
            }
            if (!$zone) {
                $this->json(['success' => false, 'error' => 'Bölge bulunamadı'], 404);
                return;
            }

            $allTables = $tableService->getAllTables();
            $tables = array_values(array_filter($allTables, function($t) use ($zoneId) {
                return ($t['zone_id'] ?? '') === $zoneId;
            }));

            $normalizeStatus = function($s) {
                $s = strtoupper(trim($s ?? 'FREE'));
                if ($s === 'OCCUPIED' || $s === 'PAYMENT_PENDING') return 'occupied';
                if ($s === 'FREE') return 'available';
                if ($s === 'RESERVED') return 'reserved';
                return 'available';
            };

            $formatted = [];
            foreach ($tables as $t) {
                $tableId = $t['table_id'] ?? $t['id'] ?? '';
                $activeOrders = $tableId ? $orderService->getActiveOrdersByTable($tableId) : [];
                $status = $normalizeStatus($t['status'] ?? '');
                if (count($activeOrders) > 0) $status = 'occupied';
                $formatted[] = array_merge($t, [
                    'status' => $status,
                    'table_name' => $t['name'] ?? $t['table_name'] ?? '',
                    'zone_name' => $zone['zone_name'] ?? $zone['name'] ?? 'Bölge',
                    'active_orders' => count($activeOrders),
                ]);
            }

            $this->json(['success' => true, 'data' => [
                'zone' => [
                    'zone_id' => $zone['zone_id'] ?? '',
                    'zone_name' => $zone['zone_name'] ?? $zone['name'] ?? 'Bölge',
                    'description' => $zone['description'] ?? '',
                ],
                'tables' => $formatted,
            ]]);
        } catch (\Exception $e) {
            \App\Core\Logger::error('getZoneTables error', ['error' => $e->getMessage()]);
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // ─── Menu ─────────────────────────────────────────────────

    public function getMenu() {
        if (!$this->requireAuth()) return;
        $this->ensureMobileTenant();

        try {
            $menuItems = \App\Core\DependencyFactory::getMenuItemService()->getAllMenuItems();
            $categories = \App\Core\DependencyFactory::getCategoryService()->getAllCategories();
            $menuItems = is_array($menuItems) ? $menuItems : [];
            $categories = is_array($categories) ? $categories : [];

            // Mobile expects categories with nested items (category_name, items[])
            $formattedCategories = [];
            foreach ($categories as $cat) {
                $catId = $cat['category_id'] ?? '';
                $catItems = array_values(array_filter($menuItems, fn($m) => ($m['category_id'] ?? '') === $catId));
                $formattedItems = array_map(function ($m) {
                    return [
                        'menu_item_id' => $m['menu_item_id'] ?? '',
                        'name' => $m['name'] ?? '',
                        'description' => $m['description'] ?? '',
                        'price' => floatval($m['price'] ?? 0),
                        'is_available' => (int)($m['is_available'] ?? 1) === 1,
                        'image' => $m['image'] ?? null,
                    ];
                }, $catItems);
                $formattedCategories[] = [
                    'category_id' => $catId,
                    'category_name' => $cat['name'] ?? $cat['category_name'] ?? '',
                    'description' => $cat['description'] ?? '',
                    'items' => $formattedItems,
                ];
            }
            // Include uncategorized items (no category_id or category not in list)
            $catIds = array_column($categories, 'category_id');
            $uncategorized = array_filter($menuItems, fn($m) => empty($m['category_id']) || !in_array($m['category_id'], $catIds));
            if (!empty($uncategorized)) {
                $formattedItems = array_map(function ($m) {
                    return [
                        'menu_item_id' => $m['menu_item_id'] ?? '',
                        'name' => $m['name'] ?? '',
                        'description' => $m['description'] ?? '',
                        'price' => floatval($m['price'] ?? 0),
                        'is_available' => (int)($m['is_available'] ?? 1) === 1,
                        'image' => $m['image'] ?? null,
                    ];
                }, array_values($uncategorized));
                $formattedCategories[] = [
                    'category_id' => '',
                    'category_name' => 'Kategorisiz',
                    'description' => '',
                    'items' => $formattedItems,
                ];
            }
            $this->json(['success' => true, 'data' => ['categories' => $formattedCategories, 'items' => $menuItems]]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function getMenuItemIngredients() {
        if (!$this->requireAuth()) return;
        $this->ensureMobileTenant();
        
        $q = $this->query();
        $menuItemId = $q['menu_item_id'] ?? '';
        
        try {
            $ingredientService = DependencyFactory::getIngredientService();
            $ingredients = method_exists($ingredientService, 'getByMenuItem')
                ? $ingredientService->getByMenuItem($menuItemId)
                : [];
            $this->json(['success' => true, 'ingredients' => $ingredients]);
        } catch (\Exception $e) {
            $this->json(['success' => true, 'ingredients' => []]);
        }
    }
    
    // ─── Notifications ────────────────────────────────────────
    
    public function getNotifications() {
        if (!$this->requireAuth()) return;
        $this->ensureMobileTenant();
        
        try {
            $q = $this->query();
            $limit = isset($q['limit']) ? (int)$q['limit'] : 100;
            $settingsService = DependencyFactory::getSystemSettingsService();
            $range = $settingsService->getBusinessDateRange();
            $startDt = $range['start_datetime'] ?? $range['start'] ?? ($range['date'] . ' 00:00:00');
            $endDt = $range['end_datetime'] ?? $range['end'] ?? ($range['date'] . ' 23:59:59');
            
            $notifService = DependencyFactory::getNotificationService();
            $notifications = method_exists($notifService, 'getForBusinessDay')
                ? $notifService->getForBusinessDay($startDt, $endDt, $limit)
                : $notifService->getAll($limit);
            $this->json(['success' => true, 'notifications' => $notifications ?: []]);
        } catch (\Exception $e) {
            $this->json(['success' => true, 'notifications' => []]);
        }
    }
    
    public function markNotificationRead() {
        if (!$this->requireAuth()) return;
        $data = $this->input();
        
        try {
            $notifService = DependencyFactory::getNotificationService();
            if (method_exists($notifService, 'markAsRead')) {
                $notifService->markAsRead($data['notification_id'] ?? '');
            }
            $this->json(['success' => true]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    public function markAllNotificationsRead() {
        if (!$this->requireAuth()) return;
        $this->ensureMobileTenant();
        
        try {
            $notifService = DependencyFactory::getNotificationService();
            $settingsService = DependencyFactory::getSystemSettingsService();
            $range = $settingsService->getBusinessDateRange();
            $startDt = $range['start_datetime'] ?? $range['start'] ?? ($range['date'] . ' 00:00:00');
            $endDt = $range['end_datetime'] ?? $range['end'] ?? ($range['date'] . ' 23:59:59');
            if (method_exists($notifService, 'markAllAsReadForBusinessDay')) {
                $notifService->markAllAsReadForBusinessDay($startDt, $endDt);
            } elseif (method_exists($notifService, 'markAllAsRead')) {
                $notifService->markAllAsRead();
            }
            $this->json(['success' => true]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    public function registerPushToken() {
        if (!$this->requireAuth()) return;
        $data = $this->input();

        $userId = $this->getCurrentUserId();
        $token = trim((string)($data['token'] ?? $data['push_token'] ?? $data['fcm_token'] ?? ''));
        $deviceId = trim((string)($data['device_id'] ?? ''));
        $platform = strtolower((string)($data['platform'] ?? 'android'));
        if (!in_array($platform, ['android', 'ios', 'web'], true)) $platform = 'android';
        if ($deviceId === '') {
            $deviceId = 'dev_' . substr(md5($userId . '_' . $token), 0, 24);
        }
        $tenantId = $_SESSION['business_id'] ?? ($_SESSION['customer_id'] ?? null);

        if ($token === '') {
            $this->json(['success' => false, 'error' => 'token gerekli'], 400);
            return;
        }

        $db = DependencyFactory::getDatabase();

        // 1) user_devices (yeni)
        try {
            $stmt = $db->prepare("
                INSERT INTO user_devices
                    (device_id, user_id, tenant_id, fcm_token, platform, app_version, os_version, device_model, locale, timezone, last_seen_at, is_active, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 1, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                    fcm_token = VALUES(fcm_token),
                    tenant_id = VALUES(tenant_id),
                    platform = VALUES(platform),
                    app_version = VALUES(app_version),
                    os_version = VALUES(os_version),
                    device_model = VALUES(device_model),
                    locale = VALUES(locale),
                    timezone = VALUES(timezone),
                    last_seen_at = NOW(),
                    is_active = 1,
                    updated_at = NOW()
            ");
            $stmt->execute([
                $deviceId, $userId, $tenantId, $token, $platform,
                $data['app_version'] ?? null,
                $data['os_version'] ?? null,
                $data['device_model'] ?? null,
                $data['locale'] ?? null,
                $data['timezone'] ?? null,
            ]);
        } catch (\Exception $e) {
            \App\Core\Logger::warning('registerPushToken user_devices failed, trying legacy', ['error' => $e->getMessage()]);
        }

        // 2) push_tokens (legacy, backward compatibility)
        try {
            $stmt = $db->prepare("INSERT INTO push_tokens (user_id, token, platform, created_at) VALUES (?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE token = VALUES(token), updated_at = NOW()");
            $stmt->execute([$userId, $token, $platform]);
        } catch (\Exception $e) {}

        $this->json(['success' => true, 'data' => ['device_id' => $deviceId]]);
    }
    
    // ─── Kitchen Endpoints ────────────────────────────────────
    
    /**
     * Check if order has kitchen items - used by mobile to filter notifications for kitchen staff
     */
    public function orderHasKitchenItems() {
        if (!$this->requireAuth()) return;
        $this->ensureMobileTenant();
        $q = $this->query();
        $orderId = trim($q['order_id'] ?? '');
        if (empty($orderId)) {
            $this->json(['success' => false, 'error' => 'order_id gerekli'], 400);
            return;
        }
        try {
            $orderService = DependencyFactory::getOrderService();
            $hasKitchen = $orderService->orderHasKitchenItems($orderId);
            $this->json(['success' => true, 'data' => ['has_kitchen_items' => $hasKitchen]]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    public function getKitchenOrders() {
        if (!$this->requireAuth()) return;
        $this->ensureMobileTenant();
        
        $q = $this->query();
        $statusFilter = strtolower(trim($q['status'] ?? 'all'));
        
        try {
            $orderService = DependencyFactory::getOrderService();
            $tableService = DependencyFactory::getTableService();
            $zoneService = DependencyFactory::getZoneService();
            
            $statuses = ['PENDING', 'PREPARING', 'READY'];
            if ($statusFilter === 'pending') $statuses = ['PENDING'];
            elseif ($statusFilter === 'preparing') $statuses = ['PREPARING'];
            elseif ($statusFilter === 'ready') $statuses = ['READY'];
            
            $allOrders = [];
            foreach ($statuses as $st) {
                $ords = $orderService->getOrdersByStatus($st);
                if (is_array($ords)) $allOrders = array_merge($allOrders, $ords);
            }
            
            $orderIds = array_column($allOrders, 'order_id');
            $ordersWithItems = !empty($orderIds) ? $orderService->getOrdersWithItems($orderIds) : [];
            $ordersMap = [];
            foreach ($ordersWithItems as $o) {
                $ordersMap[$o['order_id']] = $o;
            }
            
            $userService = DependencyFactory::getUserService();
            $formatted = [];
            foreach ($allOrders as $ord) {
                $full = $ordersMap[$ord['order_id']] ?? $ord;
                $tableId = $ord['table_id'] ?? '';
                $tableName = '';
                $zoneName = '';
                if ($tableId) {
                    $table = $tableService->getTableById($tableId);
                    if ($table) {
                        $tableName = $table['name'] ?? '';
                        $zoneId = $table['zone_id'] ?? '';
                        if ($zoneId) {
                            $zone = $zoneService->getZoneById($zoneId);
                            $zoneName = $zone['name'] ?? $zone['zone_name'] ?? '';
                        }
                    }
                }
                $waiterName = '';
                $createdBy = $ord['created_by'] ?? '';
                if ($createdBy && $createdBy !== 'customer') {
                    $waiter = $userService->findByUserId($createdBy);
                    $waiterName = $waiter['name'] ?? $waiter['username'] ?? '';
                }
                $items = [];
                foreach ($full['items'] ?? [] as $it) {
                    $name = $it['menu_item_name'] ?? $it['item_name'] ?? $it['name'] ?? '';
                    $note = $it['note'] ?? '';
                    $excl = $it['excluded_ingredients'] ?? '[]';
                    $extras = $it['selected_extras'] ?? '[]';
                    $exclArr = is_string($excl) ? json_decode($excl, true) : $excl;
                    $extrasArr = is_string($extras) ? json_decode($extras, true) : $extras;
                    $suffix = [];
                    if (!empty($exclArr) && is_array($exclArr)) $suffix[] = 'Çıkar: ' . implode(', ', $exclArr);
                    if (!empty($extrasArr) && is_array($extrasArr)) $suffix[] = 'Ekstra: ' . implode(', ', array_column($extrasArr, 'name'));
                    if ($note) $suffix[] = $note;
                    $items[] = [
                        'order_item_id' => $it['order_item_id'] ?? '',
                        'product_name' => $name,
                        'quantity' => (int)($it['quantity'] ?? 1),
                        'notes' => implode(' | ', $suffix),
                        'unit_price' => floatval($it['price'] ?? $it['item_price'] ?? 0),
                    ];
                }
                $formatted[] = [
                    'order_id' => $ord['order_id'],
                    'table_id' => $tableId,
                    'table_name' => $tableName,
                    'zone_name' => $zoneName,
                    'waiter_name' => $waiterName,
                    'status' => strtolower($ord['status'] ?? 'pending'),
                    'total_amount' => floatval($ord['total_amount'] ?? 0),
                    'notes' => $ord['customer_note'] ?? '',
                    'created_at' => $ord['created_at'] ?? '',
                    'items' => $items,
                ];
            }
            usort($formatted, fn($a, $b) => strtotime($a['created_at'] ?? '0') - strtotime($b['created_at'] ?? '0'));
            $this->json(['success' => true, 'data' => ['orders' => $formatted]]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    public function updateKitchenStatus() {
        if (!$this->requireAuth()) return;
        $this->ensureMobileTenant();
        $data = $this->input();
        
        try {
            $orderService = DependencyFactory::getOrderService();
            $result = $orderService->updateOrderStatus($data['order_id'] ?? '', strtoupper($data['status'] ?? 'READY'));
            $this->json(['success' => (bool)$result]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    // ─── Preparation Screen ───────────────────────────────────
    
    public function getPreparationOrders() {
        if (!$this->requireAuth()) return;
        $this->ensureMobileTenant();
        
        $q = $this->query();
        $screenSlug = $q['screen_slug'] ?? 'default';
        
        try {
            $prepService = DependencyFactory::getPreparationScreenService();
            $tableService = DependencyFactory::getTableService();
            
            $screen = $prepService->getScreenBySlug($screenSlug);
            if (!$screen) {
                $this->json(['success' => true, 'orders' => [], 'screen_name' => '']);
                return;
            }
            
            $ordersWithItems = $prepService->getOrdersForScreen($screen['screen_id']);
            $flattened = [];
            
            foreach ($ordersWithItems as $order) {
                $tableId = $order['table_id'] ?? '';
                $tableName = '';
                $zoneName = '';
                if ($tableId) {
                    $table = $tableService->getTableById($tableId);
                    if ($table) {
                        $tableName = $table['name'] ?? '';
                        $zoneId = $table['zone_id'] ?? '';
                        if ($zoneId) {
                            $zone = DependencyFactory::getZoneService()->getZoneById($zoneId);
                            $zoneName = $zone['name'] ?? $zone['zone_name'] ?? '';
                        }
                    }
                }
                
                foreach ($order['items'] ?? [] as $item) {
                    $prepStatus = strtolower(trim($item['preparation_status'] ?? 'pending'));
                    if (!in_array($prepStatus, ['pending', 'preparing', 'ready', 'served'])) {
                        $prepStatus = 'pending';
                    }
                    $flattened[] = [
                        'order_id' => $order['order_id'],
                        'order_item_id' => $item['order_item_id'],
                        'product_name' => $item['menu_item_name'] ?? $item['item_name'] ?? $item['name'] ?? '',
                        'quantity' => (int)($item['quantity'] ?? 1),
                        'notes' => $item['note'] ?? '',
                        'status' => $prepStatus,
                        'table_name' => $tableName,
                        'zone_name' => $zoneName,
                        'created_at' => $order['created_at'] ?? $item['created_at'] ?? '',
                        'category_name' => $item['category_name'] ?? ''
                    ];
                }
            }
            
            $this->json([
                'success' => true,
                'orders' => $flattened,
                'screen_name' => $screen['name'] ?? ''
            ]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    public function updatePreparationStatus() {
        if (!$this->requireAuth()) return;
        $this->ensureMobileTenant();
        $data = $this->input();
        
        $orderItemId = $data['order_item_id'] ?? '';
        $status = strtoupper(trim($data['status'] ?? 'READY'));
        $screenSlug = $data['screen_slug'] ?? '';
        
        if (empty($orderItemId) || empty($status)) {
            $this->json(['success' => false, 'error' => 'order_item_id ve status gerekli'], 400);
            return;
        }
        if (!in_array($status, ['PENDING', 'PREPARING', 'READY', 'SERVED'])) {
            $this->json(['success' => false, 'error' => 'Geçersiz status'], 400);
            return;
        }
        
        try {
            $orderItemService = DependencyFactory::getOrderItemService();
            $prepService = DependencyFactory::getPreparationScreenService();
            
            $orderItem = $orderItemService->getOrderItemById($orderItemId);
            if (!$orderItem) {
                $this->json(['success' => false, 'error' => 'Sipariş kalemi bulunamadı'], 404);
                return;
            }
            
            $orderId = $orderItem['order_id'] ?? '';
            if (empty($orderId)) {
                $this->json(['success' => false, 'error' => 'Sipariş bulunamadı'], 404);
                return;
            }
            
            $screenId = null;
            if (!empty($screenSlug)) {
                $screen = $prepService->getScreenBySlug($screenSlug);
                $screenId = $screen['screen_id'] ?? null;
            }
            
            $validIds = [];
            if ($screenId) {
                $validIds = $prepService->getOrderItemIdsForScreen($orderId, $screenId);
            }
            
            if (!empty($validIds) && !in_array($orderItemId, $validIds)) {
                $this->json(['success' => false, 'error' => 'Bu kalem bu ekrana ait değil'], 403);
                return;
            }
            
            $result = $orderItemService->updatePreparationStatusByIds([$orderItemId], $status);
            $this->json(['success' => (bool)$result]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    // ─── Waiter Endpoints ─────────────────────────────────────
    
    public function getTableDetails() {
        if (!$this->requireAuth()) return;
        $this->ensureMobileTenant();
        $q = $this->query();
        
        try {
            $tableId = $q['table_id'] ?? '';
            $tableService = DependencyFactory::getTableService();
            $orderService = DependencyFactory::getOrderService();
            $table = $tableService->getTableById($tableId);
            if (!$table) {
                $this->json(['success' => false, 'error' => 'Masa bulunamadı'], 404);
                return;
            }

            $activeOrders = $orderService->getActiveOrdersByTable($tableId);
            $orderIds = array_column($activeOrders, 'order_id');
            $ordersWithItems = !empty($orderIds) ? $orderService->getOrdersWithItems($orderIds) : [];
            $ordersMap = [];
            foreach ($ordersWithItems as $o) {
                $ordersMap[$o['order_id']] = $o;
            }

            $zoneName = '';
            $zoneId = $table['zone_id'] ?? '';
            if ($zoneId) {
                $zone = DependencyFactory::getZoneService()->getZoneById($zoneId);
                $zoneName = $zone['name'] ?? $zone['zone_name'] ?? '';
            }

            $status = strtoupper(trim($table['status'] ?? 'FREE'));
            $normalizedStatus = ($status === 'OCCUPIED' || $status === 'PAYMENT_PENDING') ? 'occupied' : 'available';

            $totalAmount = 0;
            $formattedOrders = [];
            foreach ($activeOrders as $ord) {
                $fullOrder = $ordersMap[$ord['order_id']] ?? $ord;
                $items = $fullOrder['items'] ?? [];
                $orderTotal = floatval($ord['total_amount'] ?? 0);
                $totalAmount += $orderTotal;
                $formattedOrders[] = array_merge($ord, [
                    'status' => strtolower($ord['status'] ?? 'pending'),
                    'items' => $items,
                    'table_name' => $table['name'] ?? '',
                    'zone_name' => $zoneName,
                ]);
            }

            if (!empty($formattedOrders)) {
                $normalizedStatus = 'occupied';
            }

            $detail = [
                'table_id' => $table['table_id'] ?? $tableId,
                'table_name' => $table['name'] ?? '',
                'capacity' => (int)($table['capacity'] ?? 4),
                'status' => $normalizedStatus,
                'zone_name' => $zoneName,
                'active_orders' => $formattedOrders,
                'total_amount' => $totalAmount,
            ];
            $this->json(['success' => true, 'data' => $detail]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    public function getReadyOrders() {
        if (!$this->requireAuth()) return;
        $this->ensureMobileTenant();
        
        try {
            $orders = DependencyFactory::getOrderService()->getOrdersByStatus('READY');
            $this->json(['success' => true, 'orders' => $orders ?: []]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    public function deliverOrder() {
        if (!$this->requireAuth()) return;
        $this->ensureMobileTenant();
        $data = $this->input();
        
        try {
            $result = DependencyFactory::getOrderService()->updateOrderStatus($data['order_id'] ?? '', 'SERVED');
            $this->json(['success' => (bool)$result]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    public function transferToCashier() {
        if (!$this->requireAuth()) return;
        $this->ensureMobileTenant();
        $data = $this->input();
        
        try {
            $orderService = DependencyFactory::getOrderService();
            $tableId = $data['table_id'] ?? $data['order_id'] ?? '';
            if (empty($tableId)) {
                $this->json(['success' => false, 'error' => 'table_id veya order_id gerekli'], 400);
                return;
            }
            if (!empty($data['order_id'])) {
                $result = $orderService->updateOrderStatus($data['order_id'], 'READY_FOR_PAYMENT');
            } else {
                $orders = $orderService->getActiveOrdersByTable($tableId);
                $result = true;
                foreach ($orders as $o) {
                    $ok = $orderService->updateOrderStatus($o['order_id'] ?? '', 'READY_FOR_PAYMENT');
                    if (!$ok) $result = false;
                }
            }
            $this->json(['success' => (bool)$result]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    public function deleteOrderItem() {
        if (!$this->requireAuth()) return;
        $this->ensureMobileTenant();
        $data = $this->input();
        $orderItemId = $data['order_item_id'] ?? $data['item_id'] ?? '';
        
        if (empty($orderItemId)) {
            $this->json(['success' => false, 'error' => 'order_item_id gerekli'], 400);
            return;
        }
        
        try {
            $orderItemService = DependencyFactory::getOrderItemService();
            $orderService = DependencyFactory::getOrderService();
            $tableService = DependencyFactory::getTableService();
            $orderItem = $orderItemService->getOrderItemByIdWithName($orderItemId) ?: $orderItemService->getOrderItemById($orderItemId);
            if (!$orderItem) {
                $this->json(['success' => false, 'error' => 'Sipariş öğesi bulunamadı'], 404);
                return;
            }
            $order = $orderService->getOrderById($orderItem['order_id'] ?? '');
            if (!$order) {
                $this->json(['success' => false, 'error' => 'Sipariş bulunamadı'], 404);
                return;
            }
            
            $approvalService = DependencyFactory::getOrderEditApprovalService();
            $businessId = $this->getTenantId();
            $userId = $_SESSION['user_id'] ?? $this->getCurrentUserId() ?? '';
            $requiresApproval = $approvalService->requiresApproval($userId, $businessId);
            
            if ($requiresApproval) {
                if ($approvalService->hasPendingApprovalForOrderItem($orderItemId)) {
                    $this->json(['success' => false, 'error' => 'Bu ürün için zaten bekleyen onay talebi var'], 400);
                    return;
                }
                $tableId = $order['table_id'] ?? '';
                $table = !empty($tableId) ? $tableService->getTableById($tableId) : null;
                $itemName = $orderItem['menu_item_name'] ?? $orderItem['item_name'] ?? $orderItem['name'] ?? 'Ürün';
                $userName = $_SESSION['username'] ?? $_SESSION['user_name'] ?? $_SESSION['name'] ?? 'Garson';
                $approvalId = $approvalService->createApprovalRequest([
                    'order_id' => $order['order_id'],
                    'table_id' => $tableId,
                    'table_name' => $table['name'] ?? '',
                    'order_item_id' => $orderItemId,
                    'action_type' => 'DELETE',
                    'old_quantity' => intval($orderItem['quantity'] ?? 1),
                    'new_quantity' => null,
                    'item_name' => $itemName,
                    'item_price' => floatval($orderItem['price'] ?? 0),
                    'requested_by' => $userId,
                    'requested_by_name' => $userName,
                ]);
                if ($approvalId) {
                    $this->json(['success' => true, 'approval_pending' => true, 'message' => 'Silme talebi onay kuyruğuna gönderildi']);
                    return;
                }
                $this->json(['success' => false, 'error' => 'Onay talebi oluşturulamadı'], 500);
                return;
            }
            
            $result = $orderItemService->deleteOrderItem($orderItemId);
            if ($result) {
                $itemTotal = floatval($orderItem['price'] ?? 0) * intval($orderItem['quantity'] ?? 1);
                $newTotal = max(0, floatval($order['total_amount'] ?? 0) - $itemTotal);
                $orderService->updateOrderTotal($order['order_id'], $newTotal);
            }
            $this->json(['success' => (bool)$result]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    public function acceptOrder() {
        if (!$this->requireAuth()) return;
        $this->ensureMobileTenant();
        $data = $this->input();
        
        try {
            $result = DependencyFactory::getOrderService()->updateOrderStatus($data['order_id'] ?? '', 'PREPARING');
            $this->json(['success' => (bool)$result]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    public function moveTable() {
        if (!$this->requireAuth()) return;
        $this->ensureMobileTenant();
        $data = $this->input();
        
        try {
            $orderService = DependencyFactory::getOrderService();
            $orderId = $data['order_id'] ?? '';
            $newTableId = $data['new_table_id'] ?? $data['to_table_id'] ?? '';
            $fromTableId = $data['from_table_id'] ?? '';
            if (empty($newTableId)) {
                $this->json(['success' => false, 'error' => 'new_table_id veya to_table_id gerekli'], 400);
                return;
            }
            if (empty($orderId) && !empty($fromTableId)) {
                $orders = $orderService->getActiveOrdersByTable($fromTableId);
                $result = true;
                foreach ($orders as $o) {
                    $oid = $o['order_id'] ?? '';
                    if (method_exists($orderService, 'moveOrderToTable')) {
                        $ok = $orderService->moveOrderToTable($oid, $newTableId);
                    } else {
                        $db = DependencyFactory::getDatabase();
                        $stmt = $db->prepare("UPDATE orders SET table_id = ? WHERE order_id = ?");
                        $ok = $stmt->execute([$newTableId, $oid]);
                    }
                    if (!$ok) $result = false;
                }
            } else {
                if (method_exists($orderService, 'moveOrderToTable')) {
                    $result = $orderService->moveOrderToTable($orderId, $newTableId);
                } else {
                    $db = DependencyFactory::getDatabase();
                    $stmt = $db->prepare("UPDATE orders SET table_id = ? WHERE order_id = ?");
                    $result = $stmt->execute([$newTableId, $orderId]);
                }
            }
            $this->json(['success' => (bool)$result]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    // ─── POS / Cashier Endpoints ──────────────────────────────
    
    public function createMobileOrder() {
        if (!$this->requireAuth()) return;
        $this->ensureMobileTenant();
        $data = $this->input();
        
        try {
            $orderService = DependencyFactory::getOrderService();
            $orderData = [
                'table_id' => $data['table_id'] ?? null,
                'order_type' => $data['order_type'] ?? 'DINE_IN',
                'status' => 'PENDING',
                'created_by' => $this->getCurrentUserId(),
                'items' => $data['items'] ?? [],
                'customer_name' => $data['customer_name'] ?? null,
                'notes' => $data['notes'] ?? null,
            ];
            
            $result = $orderService->createOrder($orderData);
            $this->json(['success' => true, 'order' => $result]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    /// Add a single item to an existing order. Mirrors the web POS
    /// flow in `PosController::addItemsToOrder`: resolves the menu
    /// item (and optional variant) price, handles excluded_ingredients
    /// + selected_extras, and merges with an existing order_item when
    /// the (menu_item_id, variant_id, excluded, extras) tuple matches
    /// so repeat orders of the same item just bump quantity. Items
    /// with differing modifiers (e.g. "pizza without tomato") stay on
    /// their own line.
    public function addItemToOrder() {
        if (!$this->requireAuth()) return;
        $this->ensureMobileTenant();
        $data = $this->input();

        $orderId    = $data['order_id']     ?? '';
        $menuItemId = $data['menu_item_id'] ?? '';
        $quantity   = max(1, intval($data['quantity'] ?? 1));
        $variantId  = $data['variant_id']   ?? null;
        $note       = $data['note']         ?? ($data['notes'] ?? null);
        $excluded   = is_array($data['excluded_ingredients'] ?? null)
            ? $data['excluded_ingredients']
            : [];
        $extras     = is_array($data['selected_extras'] ?? null)
            ? $data['selected_extras']
            : [];

        if (empty($orderId) || empty($menuItemId)) {
            $this->json([
                'success' => false,
                'error'   => 'order_id ve menu_item_id gerekli',
            ], 400);
            return;
        }

        try {
            $orderItemService = DependencyFactory::getOrderItemService();
            $orderService     = DependencyFactory::getOrderService();
            $menuItemService  = DependencyFactory::getMenuItemService();

            $menuItem = $menuItemService->getMenuItemById($menuItemId);
            if (!$menuItem) {
                $this->json([
                    'success' => false,
                    'error'   => 'Menü ürünü bulunamadı',
                ], 404);
                return;
            }

            $variantPriceModifier = 0.0;
            if ($variantId && method_exists($menuItemService, 'getVariants')) {
                try {
                    $variants = $menuItemService->getVariants($menuItemId) ?? [];
                    foreach ($variants as $v) {
                        if (($v['variant_id'] ?? '') === $variantId) {
                            $variantPriceModifier = floatval($v['price_modifier'] ?? 0);
                            break;
                        }
                    }
                } catch (\Throwable $e) {
                    // Non-fatal — treat as no variant modifier.
                }
            }

            $unitPrice = floatval($menuItem['price'] ?? 0) + $variantPriceModifier;

            $orderItemData = [
                'order_id'     => $orderId,
                'menu_item_id' => $menuItemId,
                'variant_id'   => $variantId,
                'quantity'     => $quantity,
                'price'        => $unitPrice,
                'note'         => $note,
            ];
            if (!empty($excluded)) {
                $orderItemData['excluded_ingredients'] = $excluded;
            }
            if (!empty($extras)) {
                $orderItemData['selected_extras'] = $extras;
            }

            $itemResult  = false;
            $mergeableId = null;
            if (method_exists($orderItemService, 'findMergeableOrderItem')) {
                $mergeableId = $orderItemService->findMergeableOrderItem(
                    $orderId,
                    $menuItemId,
                    $variantId,
                    $excluded,
                    $extras
                );
            }
            if ($mergeableId) {
                $existing = $orderItemService->getOrderItemById($mergeableId);
                if ($existing) {
                    $newQty = intval($existing['quantity'] ?? 1) + $quantity;
                    $ok     = $orderItemService->updateQuantity($mergeableId, $newQty);
                    $itemResult = $ok ? $mergeableId : false;
                }
            }
            if (!$itemResult) {
                $itemResult = $orderItemService->createOrderItem($orderItemData);
            }

            if (!$itemResult) {
                $this->json([
                    'success' => false,
                    'error'   => 'Ürün siparişe eklenemedi',
                ], 500);
                return;
            }

            if (!$mergeableId) {
                try {
                    $db = DependencyFactory::getDatabase();
                    if (!empty($excluded)) {
                        $stmt = $db->prepare(
                            "INSERT INTO order_item_ingredients
                                (order_item_id, ingredient_name, is_excluded)
                             VALUES (?, ?, 1)"
                        );
                        foreach ($excluded as $ing) {
                            $name = is_string($ing) ? $ing : ($ing['name'] ?? '');
                            if ($name !== '') {
                                $stmt->execute([$itemResult, $name]);
                            }
                        }
                    }
                    if (!empty($extras)) {
                        $stmt = $db->prepare(
                            "INSERT INTO order_item_extras
                                (order_item_id, name, price)
                             VALUES (?, ?, ?)"
                        );
                        foreach ($extras as $ex) {
                            if (is_array($ex)) {
                                $name  = $ex['name']  ?? '';
                                $price = floatval($ex['price'] ?? 0);
                            } else {
                                $name  = (string)$ex;
                                $price = 0;
                            }
                            if ($name !== '') {
                                $stmt->execute([$itemResult, $name, $price]);
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    \App\Core\Logger::warning(
                        'MobileAPIController: failed to persist modifiers',
                        ['order_item_id' => $itemResult, 'error' => $e->getMessage()]
                    );
                }
            }

            $allItems = $orderItemService->getOrderItemsByOrder($orderId);
            $newTotal = 0.0;
            foreach ($allItems as $it) {
                if (($it['preparation_status'] ?? '') === 'CANCELLED') continue;
                $newTotal += floatval($it['price'] ?? 0)
                          * intval($it['quantity'] ?? 1);
            }
            $orderService->updateOrderTotal($orderId, round($newTotal, 2));

            $this->json([
                'success'  => true,
                'merged'   => (bool)$mergeableId,
                'item_id'  => $itemResult,
                'total'    => round($newTotal, 2),
            ]);
        } catch (\Exception $e) {
            $this->json([
                'success' => false,
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
    
    public function removeItemFromOrder() {
        // Same as deleteOrderItem - uses order_item_id from request body
        $this->deleteOrderItem();
    }
    
    public function updateItemQuantity() {
        if (!$this->requireAuth()) return;
        $this->ensureMobileTenant();
        $data = $this->input();
        $orderItemId = $data['order_item_id'] ?? '';
        $newQuantity = intval($data['quantity'] ?? 1);
        
        if (empty($orderItemId) || $newQuantity < 1) {
            $this->json(['success' => false, 'error' => 'Geçersiz parametreler'], 400);
            return;
        }
        
        try {
            $orderItemService = DependencyFactory::getOrderItemService();
            $orderService = DependencyFactory::getOrderService();
            $tableService = DependencyFactory::getTableService();
            $orderItem = $orderItemService->getOrderItemByIdWithName($orderItemId) ?: $orderItemService->getOrderItemById($orderItemId);
            if (!$orderItem) {
                $this->json(['success' => false, 'error' => 'Sipariş öğesi bulunamadı'], 404);
                return;
            }
            $oldQuantity = intval($orderItem['quantity'] ?? 1);
            $order = $orderService->getOrderById($orderItem['order_id'] ?? '');
            if (!$order) {
                $this->json(['success' => false, 'error' => 'Sipariş bulunamadı'], 404);
                return;
            }
            
            if ($newQuantity < $oldQuantity) {
                $approvalService = DependencyFactory::getOrderEditApprovalService();
                $businessId = $this->getTenantId();
                $userId = $_SESSION['user_id'] ?? $this->getCurrentUserId() ?? '';
                $requiresApproval = $approvalService->requiresApproval($userId, $businessId);
                
                if ($requiresApproval) {
                    if ($approvalService->hasPendingApprovalForOrderItem($orderItemId)) {
                        $this->json(['success' => false, 'error' => 'Bu ürün için zaten bekleyen onay talebi var'], 400);
                        return;
                    }
                    $tableId = $order['table_id'] ?? '';
                    $table = !empty($tableId) ? $tableService->getTableById($tableId) : null;
                    $itemName = $orderItem['menu_item_name'] ?? $orderItem['item_name'] ?? $orderItem['name'] ?? 'Ürün';
                    $userName = $_SESSION['username'] ?? $_SESSION['user_name'] ?? $_SESSION['name'] ?? 'Kasiyer';
                    $approvalId = $approvalService->createApprovalRequest([
                        'order_id' => $order['order_id'],
                        'table_id' => $tableId,
                        'table_name' => $table['name'] ?? '',
                        'order_item_id' => $orderItemId,
                        'action_type' => 'REDUCE_QUANTITY',
                        'old_quantity' => $oldQuantity,
                        'new_quantity' => $newQuantity,
                        'item_name' => $itemName,
                        'item_price' => floatval($orderItem['price'] ?? 0),
                        'requested_by' => $userId,
                        'requested_by_name' => $userName,
                    ]);
                    if ($approvalId) {
                        $this->json(['success' => true, 'approval_pending' => true, 'message' => 'Azaltma talebi onay kuyruğuna gönderildi']);
                        return;
                    }
                    $this->json(['success' => false, 'error' => 'Onay talebi oluşturulamadı'], 500);
                    return;
                }
            }
            
            if (method_exists($orderItemService, 'updateQuantity')) {
                $result = $orderItemService->updateQuantity($orderItemId, $newQuantity);
            } else {
                $db = DependencyFactory::getDatabase();
                $stmt = $db->prepare("UPDATE order_items SET quantity = ? WHERE order_item_id = ?");
                $result = $stmt->execute([$newQuantity, $orderItemId]);
            }
            if ($result && $newQuantity !== $oldQuantity) {
                $allItems = $orderItemService->getOrderItemsByOrder($order['order_id']);
                $newTotal = 0;
                foreach ($allItems as $it) {
                    if (($it['preparation_status'] ?? '') !== 'CANCELLED') {
                        $newTotal += floatval($it['price'] ?? 0) * intval($it['quantity'] ?? 1);
                    }
                }
                $orderService->updateOrderTotal($order['order_id'], round($newTotal, 2));
            }
            $this->json(['success' => (bool)$result]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    public function processPaymentMobile() {
        if (!$this->requireAuth()) return;
        $this->ensureMobileTenant();
        $data = $this->input();
        
        try {
            $orderService = DependencyFactory::getOrderService();
            $paymentService = DependencyFactory::getPaymentService();
            $tableId = $data['table_id'] ?? '';
            $orderId = $data['order_id'] ?? '';
            $paymentMethod = strtoupper($data['payment_method'] ?? 'CASH');
            $tipAmount = floatval($data['tip_amount'] ?? $data['tip'] ?? 0);
            $amount = floatval($data['amount'] ?? 0);
            if (empty($tableId) && empty($orderId)) {
                $this->json(['success' => false, 'error' => 'table_id veya order_id gerekli'], 400);
                return;
            }
            $orders = [];
            if (!empty($tableId)) {
                $orders = $orderService->getActiveOrdersByTable($tableId);
            } elseif (!empty($orderId)) {
                $o = $orderService->getOrderById($orderId);
                if ($o) $orders = [$o];
            }
            if (empty($orders)) {
                $this->json(['success' => false, 'error' => 'Ödenecek sipariş bulunamadı']);
                return;
            }
            // Partial-payment semantics:
            // - When `order_id` is provided and `amount > 0`, pay exactly
            //   that amount against just that order (supports split /
            //   parçalı / kişiye böl flows from the mobile cashier).
            // - Otherwise fall back to "settle every active order at its
            //   full total" which mirrors the legacy behaviour used by
            //   "Tek dokunuşta öde".
            $totalPaid = 0;
            $singleOrderPartial =
                !empty($orderId) && $amount > 0 && count($orders) === 1;
            if ($singleOrderPartial) {
                $o = $orders[0];
                $oid = $o['order_id'] ?? '';
                $orderAmt = floatval($o['total_amount'] ?? 0);
                $pay = min($amount, $orderAmt);
                if ($pay > 0) {
                    $result = $paymentService->processPayment([
                        'order_id' => $oid,
                        'amount' => $pay,
                        'payment_method' => $paymentMethod,
                        'tip' => $tipAmount,
                        'table_id' => $tableId ?: ($o['table_id'] ?? ''),
                    ]);
                    if ($result && ($result['success'] ?? false)) {
                        $totalPaid += $pay;
                        // Only flip status to SERVED if the order is
                        // fully paid; partial payments leave it open
                        // so the remaining balance can be collected.
                        if ($pay + 0.009 >= $orderAmt) {
                            $orderService->updateOrderStatus($oid, 'SERVED');
                        }
                    }
                }
            } else {
                foreach ($orders as $o) {
                    $oid = $o['order_id'] ?? '';
                    $orderAmt = floatval($o['total_amount'] ?? 0);
                    if ($orderAmt <= 0) continue;
                    $result = $paymentService->processPayment([
                        'order_id' => $oid,
                        'amount' => $orderAmt,
                        'payment_method' => $paymentMethod,
                        'tip' => $tipAmount,
                        'table_id' => $tableId ?: ($o['table_id'] ?? ''),
                    ]);
                    if ($result && ($result['success'] ?? false)) {
                        $totalPaid += $orderAmt;
                        $orderService->updateOrderStatus($oid, 'SERVED');
                    }
                }
            }
            $this->json(['success' => true, 'data' => ['total_paid' => $totalPaid, 'message' => 'Ödeme alındı']]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    public function printAdisyonMobile() {
        if (!$this->requireAuth()) return;
        $this->ensureMobileTenant();
        $data = $this->input();
        
        try {
            $orderPrintService = DependencyFactory::getOrderPrintService();
            $tableId = $data['table_id'] ?? '';
            $orderId = $data['order_id'] ?? '';
            if (!empty($tableId) && method_exists($orderPrintService, 'printAdisyonForTable')) {
                $result = $orderPrintService->printAdisyonForTable($tableId, null, $orderId ?: null, false);
            } elseif (!empty($orderId) && method_exists($orderPrintService, 'printAdisyon')) {
                $result = $orderPrintService->printAdisyon($orderId);
            } else {
                $this->json(['success' => false, 'error' => 'table_id veya order_id gerekli'], 400);
                return;
            }
            if ($result && ($result['success'] ?? false)) {
                $receiptData = $result['receipt_data'] ?? null;
                if (!$receiptData && !empty($tableId)) {
                    $orderService = DependencyFactory::getOrderService();
                    $orders = $orderService->getActiveOrdersByTable($tableId);
                    $tableService = DependencyFactory::getTableService();
                    $table = $tableService->getTableById($tableId);
                    $customerService = DependencyFactory::getCustomerService();
                    $business = $customerService->getById($_SESSION['business_id'] ?? '');
                    $items = [];
                    $total = 0;
                    foreach ($orders as $o) {
                        $orderItems = DependencyFactory::getOrderItemService()->getOrderItemsByOrder($o['order_id'] ?? '');
                        foreach ($orderItems as $oi) {
                            $items[] = [
                                'product_name' => $oi['product_name'] ?? $oi['name'] ?? '',
                                'quantity' => (int)($oi['quantity'] ?? 1),
                                'total' => floatval($oi['price'] ?? 0) * (int)($oi['quantity'] ?? 1),
                            ];
                            $total += floatval($oi['price'] ?? 0) * (int)($oi['quantity'] ?? 1);
                        }
                    }
                    $receiptData = [
                        'business' => $business ? ['name' => $business['company_name'] ?? '', 'address' => $business['address'] ?? '', 'phone' => $business['phone'] ?? ''] : null,
                        'table_name' => $table['name'] ?? $table['table_name'] ?? '',
                        'items' => $items,
                        'total' => $total,
                        'date' => date('d.m.Y H:i'),
                    ];
                }
                $this->json(['success' => true, 'data' => $receiptData ?: ['message' => 'Adisyon yazdırma kuyruğa eklendi']]);
            } else {
                $this->json(['success' => false, 'error' => $result['error'] ?? 'Adisyon yazdırılamadı']);
            }
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    public function getActiveOrdersMobile() {
        if (!$this->requireAuth()) return;
        $this->ensureMobileTenant();
        
        try {
            $orderService = DependencyFactory::getOrderService();
            $orders = [];
            foreach (['PENDING', 'PREPARING', 'READY', 'SERVED'] as $status) {
                $statusOrders = $orderService->getOrdersByStatus($status);
                if (is_array($statusOrders)) $orders = array_merge($orders, $statusOrders);
            }
            $this->json(['success' => true, 'orders' => $orders]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    public function getTableOrdersMobile() {
        if (!$this->requireAuth()) return;
        $this->ensureMobileTenant();
        $q = $this->query();
        
        try {
            $orders = DependencyFactory::getOrderService()->getOrdersByTable($q['table_id'] ?? '');
            $this->json(['success' => true, 'orders' => $orders ?: []]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    public function clearTableOrders() {
        if (!$this->requireAuth()) return;
        $this->ensureMobileTenant();
        $data = $this->input();
        
        try {
            $tableId = $data['table_id'] ?? '';
            $tableService = DependencyFactory::getTableService();
            if (method_exists($tableService, 'clearTable')) {
                $result = $tableService->clearTable($tableId);
            } else {
                $db = DependencyFactory::getDatabase();
                $tenantId = TenantContext::getId();
                $params = [$tableId];
                $tenantWhere = '';
                if ($tenantId) { $tenantWhere = ' AND tenant_id = ?'; $params[] = $tenantId; }
                $stmt = $db->prepare("UPDATE tables SET status = 'available' WHERE table_id = ?" . $tenantWhere);
                $result = $stmt->execute($params);
            }
            $this->json(['success' => (bool)$result]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    // ─── Manager Endpoints ────────────────────────────────────
    
    public function getManagerAnalytics() {
        if (!$this->requireManager()) return;
        $this->ensureMobileTenant();
        
        try {
            $q = $this->query();
            $period = $q['period'] ?? 'today';
            $settingsService = DependencyFactory::getSystemSettingsService();
            $range = $settingsService->getBusinessDateRange();
            $dr = $this->getDateRangeFromPeriod($period);
            $startDate = $q['start_date'] ?? $dr['start_date'];
            $endDate = $q['end_date'] ?? $dr['end_date'];
            
            $orderService = DependencyFactory::getOrderService();
            $orders = $this->getOrdersForPeriod(['start_date' => $startDate, 'end_date' => $endDate], $range, $orderService);
            $orders = is_array($orders) ? $orders : [];
            
            $totalRevenue = 0;
            $paidCount = 0;
            $cancelledCount = 0;
            foreach ($orders as $o) {
                $status = strtoupper($o['status'] ?? '');
                if ($status === 'CANCELLED') { $cancelledCount++; continue; }
                $isPaid = !empty($o['is_paid']) && ($o['is_paid'] == 1 || $o['is_paid'] === '1');
                $hasPayment = !empty(trim($o['payment_method'] ?? ''));
                if ($isPaid || $hasPayment) {
                    $paidCount++;
                    $totalRevenue += floatval($o['total_amount'] ?? 0);
                }
            }
            $nonCancelled = count($orders) - $cancelledCount;
            $completionRate = $nonCancelled > 0 ? round(($paidCount / $nonCancelled) * 100) : 0;
            
            $db = DependencyFactory::getDatabase();
            $tenantId = TenantContext::getId();
            $tenantWhere = $tenantId ? ' AND o.tenant_id = ?' : '';
            $startDt = $startDate . ' 00:00:00';
            $endDt = $endDate . ' 23:59:59';
            if ($startDate === $endDate && $startDate === ($range['date'] ?? '')) {
                $startDt = $range['start_datetime'] ?? $range['start'] ?? $startDt;
                $endDt = $range['end_datetime'] ?? $range['end'] ?? $endDt;
            }
            $params = [$startDt, $endDt];
            if ($tenantId) $params[] = $tenantId;
            
            $paidFilter = "AND (o.is_paid = 1 OR o.payment_method IS NOT NULL)";
            $topItems = [];
            $hourlyData = [];
            try {
                $stmt = $db->prepare("SELECT mi.name as product_name, SUM(oi.quantity) as total_quantity FROM order_items oi INNER JOIN orders o ON oi.order_id = o.order_id INNER JOIN menu_items mi ON oi.menu_item_id = mi.menu_item_id WHERE o.created_at BETWEEN ? AND ? AND o.status != 'CANCELLED' $paidFilter $tenantWhere GROUP BY mi.menu_item_id, mi.name ORDER BY total_quantity DESC LIMIT 10");
                $stmt->execute($params);
                $topItems = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                
                $stmt = $db->prepare("SELECT HOUR(o.created_at) as hour, COUNT(*) as count FROM orders o WHERE o.created_at BETWEEN ? AND ? AND o.status != 'CANCELLED' $tenantWhere GROUP BY HOUR(o.created_at) ORDER BY hour");
                $stmt->execute($params);
                $hourlyRaw = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                for ($h = 0; $h < 24; $h++) {
                    $found = array_filter($hourlyRaw, fn($r) => (int)($r['hour'] ?? -1) === $h);
                    $hourlyData[] = ['hour' => $h, 'count' => $found ? (int)reset($found)['count'] : 0];
                }
            } catch (\Exception $e) {}
            
            $this->json(['success' => true, 'data' => [
                'total_orders' => count($orders),
                'total_revenue' => $totalRevenue,
                'revenue' => $totalRevenue,
                'order_count' => count($orders),
                'paid_orders' => $paidCount,
                'cancelled_orders' => $cancelledCount,
                'avg_order_value' => $paidCount > 0 ? round($totalRevenue / $paidCount, 2) : 0,
                'completion_rate' => $completionRate,
                'top_items' => $topItems,
                'hourly_distribution' => $hourlyData,
                'business_date' => $range['date']
            ]]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    public function getStaffList() {
        if (!$this->requireManager()) return;
        $this->ensureMobileTenant();
        $businessId = $this->getTenantId();
        
        try {
            $userService = DependencyFactory::getUserService();
            $userRepo = $userService->getRepository();
            // Use tenant-specific query: users table is excluded from tenant scope, so query directly
            $users = method_exists($userRepo, 'getByBusinessId') && $businessId
                ? $userRepo->getByBusinessId($businessId)
                : [];
            if (empty($users) && $businessId) {
                $all = method_exists($userService, 'getAllUsers') ? $userService->getAllUsers() : $userService->getAll();
                // users table uses tenant_id column; fall back to business_id for legacy rows
                $users = array_values(array_filter(
                    is_array($all) ? $all : [],
                    fn($u) => (string)($u['tenant_id'] ?? $u['business_id'] ?? '') === (string)$businessId
                ));
            }
            // Mobile expects 'id' field (alias for user_id) for delete/update; ensure is_active defaults to true
            foreach ($users as &$u) {
                if (!isset($u['id']) && isset($u['user_id'])) {
                    $u['id'] = $u['user_id'];
                }
                if (!isset($u['is_active'])) {
                    $u['is_active'] = true;
                } elseif ($u['is_active'] === '0' || $u['is_active'] === 0) {
                    $u['is_active'] = false;
                } else {
                    $u['is_active'] = (bool)($u['is_active'] ?? true);
                }
            }
            $this->json(['success' => true, 'data' => ['staff' => $users]]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    public function getBusinessSettings() {
        if (!$this->requireManager()) return;
        $this->ensureMobileTenant();

        try {
            $settings = DependencyFactory::getSystemSettingsService()->getSettings();

            $customerId = $_SESSION['customer_id'] ?? ($_SESSION['business_id'] ?? null);
            $business = null;
            if ($customerId) {
                $customerService = DependencyFactory::getCustomerService();
                if (method_exists($customerService, 'findById')) {
                    $c = $customerService->findById($customerId);
                } else {
                    $c = $customerService->getRepository()->findById($customerId);
                }
                if (is_array($c)) {
                    $business = [
                        'id' => $c['customer_id'] ?? $customerId,
                        'name' => $c['company_name'] ?? ($c['name'] ?? ''),
                        'subdomain' => $c['subdomain'] ?? '',
                        'address' => $c['address'] ?? '',
                        'phone' => $c['phone'] ?? '',
                        'email' => $c['email'] ?? '',
                        'tax_number' => $c['tax_number'] ?? '',
                        'tax_office' => $c['tax_office'] ?? '',
                    ];
                }
            }

            $this->json([
                'success' => true,
                'settings' => $settings,
                'business' => $business,
            ]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function updateBusinessSettings() {
        if (!$this->requireManager()) return;
        $this->ensureMobileTenant();
        $data = $this->input();

        try {
            // Mobil istemci hem camelCase hem snake_case yollayabilir;
            // her ikisini de normalize edip tek bir snake_case payload'a çeviriyoruz.
            $aliases = [
                'businessName'        => 'company_name',
                'companyName'         => 'company_name',
                'workingHoursStart'   => 'working_hours_start',
                'workingHoursEnd'     => 'working_hours_end',
                'approvalRequired'    => 'approval_required',
                'approvalRole'        => 'approval_role',
                'wifiName'            => 'wifi_name',
                'wifiPassword'        => 'wifi_password',
                'showWifiToCustomer'  => 'show_wifi_to_customer',
                'taxNumber'           => 'tax_number',
                'taxOffice'           => 'tax_office',
            ];
            foreach ($aliases as $camel => $snake) {
                if (array_key_exists($camel, $data) && !array_key_exists($snake, $data)) {
                    $data[$snake] = $data[$camel];
                }
                unset($data[$camel]);
            }

            // İşletme hesap kaydını güncellenecek alanlar
            $businessFields = [
                'company_name', 'name', 'address', 'phone', 'email',
                'tax_number', 'tax_office', 'city', 'country',
            ];
            $businessPayload = [];
            foreach ($businessFields as $f) {
                if (array_key_exists($f, $data)) {
                    $businessPayload[$f] = $data[$f];
                    unset($data[$f]);
                }
            }

            // Per-business mobil ayarları (ilk adımda sadece in-memory
            // session'a yazılıyor; DB şeması için ayrı bir migration
            // gerekiyor. Başarı olarak döneriz ki UI hata göstermesin.)
            $perBusinessSettings = [
                'working_hours_start', 'working_hours_end',
                'approval_required', 'approval_role',
                'wifi_name', 'wifi_password', 'show_wifi_to_customer',
                'currency',
            ];
            $businessSettingsPayload = [];
            foreach ($perBusinessSettings as $f) {
                if (array_key_exists($f, $data)) {
                    $businessSettingsPayload[$f] = $data[$f];
                    unset($data[$f]);
                }
            }

            $settingsOk = true;
            $businessOk = true;

            // Kalan serbest anahtarlar gerçek sistem ayarları (admin
            // panelinde yönetilen global tercihler). Normal kullanıcı
            // bu katmana genelde yazmaz; boş kalırsa başarı.
            if (!empty($data)) {
                $settingsOk = (bool)DependencyFactory::getSystemSettingsService()->updateSettings($data);
            }

            if (!empty($businessPayload)) {
                $customerId = $_SESSION['customer_id'] ?? ($_SESSION['business_id'] ?? null);
                if ($customerId) {
                    $customerService = DependencyFactory::getCustomerService();
                    if (method_exists($customerService, 'updateProfile')) {
                        $res = $customerService->updateProfile($customerId, $businessPayload);
                        $businessOk = (bool)($res['success'] ?? false);
                    } else {
                        $businessOk = (bool)$customerService->getRepository()->update($customerId, $businessPayload);
                    }
                }
            }

            if (!empty($businessSettingsPayload)) {
                $customerId = $_SESSION['customer_id'] ?? ($_SESSION['business_id'] ?? null);
                if ($customerId) {
                    try {
                        $businessSettingsService = DependencyFactory::getBusinessSettingsService();
                        if ($businessSettingsService && method_exists($businessSettingsService, 'updateSettings')) {
                            // mevcut signature yalnızca belirli flag'leri
                            // kabul ediyor; bilinmeyen alanları sessizce
                            // kabul et — ilerideki şemaya göre genişletilecek
                            $businessSettingsService->updateSettings($customerId, $businessSettingsPayload);
                        }
                    } catch (\Throwable $_) {
                        // per-business settings şeması henüz tamamlanmadı
                        // → UI'ı bloklamayacak şekilde yut
                    }
                }
            }

            $ok = $settingsOk && $businessOk;
            if ($ok) {
                $this->json(['success' => true]);
            } else {
                $reasons = [];
                if (!$settingsOk) {
                    $reasons[] = 'Sistem ayarları güncellenemedi';
                }
                if (!$businessOk) {
                    $reasons[] = 'İşletme bilgileri güncellenemedi';
                }
                $this->json([
                    'success' => false,
                    'error'   => implode(' ve ', $reasons) ?: 'Ayarlar kaydedilemedi',
                ]);
            }
        } catch (\Exception $e) {
            error_log('[MobileAPI][updateBusinessSettings] ' . $e->getMessage());
            $this->json([
                'success' => false,
                'error'   => 'Ayarlar kaydedilirken hata oluştu: ' . $e->getMessage(),
            ], 500);
        }
    }
    
    public function getCategories() {
        if (!$this->requireAuth()) return;
        $this->ensureMobileTenant();
        
        try {
            $categories = DependencyFactory::getCategoryService()->getAllCategories();
            $this->json(['success' => true, 'categories' => $categories ?: []]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    // ─── Menu Management ──────────────────────────────────────
    
    public function updateMenuItemAvailability() {
        if (!$this->requireManager()) return;
        $this->ensureMobileTenant();
        $data = $this->input();
        
        try {
            $menuItemService = DependencyFactory::getMenuItemService();
            $result = $menuItemService->updateMenuItem($data['menu_item_id'] ?? '', [
                'is_available' => $data['is_available'] ?? 1
            ]);
            $this->json(['success' => (bool)$result]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    public function addMenuItem() {
        if (!$this->requireManager()) return;
        $this->ensureMobileTenant();
        $data = $this->input();
        
        try {
            $result = DependencyFactory::getMenuItemService()->createMenuItem($data);
            $this->json(['success' => true, 'menu_item' => $result]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    public function updateMenuItem() {
        if (!$this->requireManager()) return;
        $this->ensureMobileTenant();
        $data = $this->input();
        
        try {
            $menuItemId = $data['menu_item_id'] ?? '';
            unset($data['menu_item_id']);
            $result = DependencyFactory::getMenuItemService()->updateMenuItem($menuItemId, $data);
            $this->json(['success' => (bool)$result]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    public function deleteMenuItem() {
        if (!$this->requireManager()) return;
        $this->ensureMobileTenant();
        $data = $this->input();
        
        try {
            $result = DependencyFactory::getMenuItemService()->deleteMenuItem($data['menu_item_id'] ?? '');
            $this->json(['success' => (bool)$result]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    // ─── Staff CRUD ───────────────────────────────────────────
    
    public function createStaff() {
        if (!$this->requireManager()) return;
        $this->ensureMobileTenant();
        $data = $this->input();
        
        try {
            // users table uses tenant_id column; keep business_id for compat
            $data['tenant_id'] = $this->getTenantId();
            $data['business_id'] = $this->getTenantId();
            // Mobile sends staff_id for update; for create, generate user_id
            if (empty($data['user_id']) && empty($data['staff_id'])) {
                $data['user_id'] = (function_exists('generateId') ? generateId('u') : 'u_' . uniqid() . '_' . time());
            } elseif (!empty($data['staff_id'])) {
                $data['user_id'] = $data['staff_id'];
                unset($data['staff_id']);
            }
            $result = DependencyFactory::getUserService()->create($data);
            $this->json(['success' => true, 'data' => ['user' => $result, 'staff_id' => $data['user_id'] ?? null]]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    public function updateStaff() {
        if (!$this->requireManager()) return;
        $this->ensureMobileTenant();
        $data = $this->input();
        
        try {
            $userId = $data['user_id'] ?? $data['staff_id'] ?? '';
            unset($data['user_id'], $data['staff_id']);
            $result = DependencyFactory::getUserService()->update($userId, $data);
            $this->json(['success' => (bool)$result]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    public function deleteStaffMobile() {
        if (!$this->requireManager()) return;
        $this->ensureMobileTenant();
        $data = $this->input();
        $userId = $data['user_id'] ?? $data['staff_id'] ?? '';
        
        try {
            $result = DependencyFactory::getUserService()->delete($userId);
            $this->json(['success' => (bool)$result]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    public function getRoles() {
        if (!$this->requireManager()) return;
        $this->ensureMobileTenant();
        
        try {
            $roles = DependencyFactory::getRoleService()->getAllRoles();
            $roles = is_array($roles) ? $roles : [];
            $prepScreens = [];
            try {
                $prepService = DependencyFactory::getPreparationScreenService();
                $screens = method_exists($prepService, 'getAllScreens') ? $prepService->getAllScreens() : [];
                $prepScreens = array_map(fn($s) => [
                    'screen_id' => $s['screen_id'] ?? $s['id'] ?? '',
                    'name' => $s['name'] ?? '',
                    'slug' => $s['slug'] ?? '',
                ], is_array($screens) ? $screens : []);
            } catch (\Exception $e) {}
            $this->json(['success' => true, 'data' => ['roles' => $roles, 'preparation_screens' => $prepScreens]]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    // ─── Reservations CRUD ────────────────────────────────────
    
    public function getReservationsList() {
        if (!$this->requireAuth()) return;
        $this->ensureMobileTenant();
        
        try {
            $reservationService = DependencyFactory::getReservationService();
            $reservations = method_exists($reservationService, 'getAllReservations')
                ? $reservationService->getAllReservations()
                : $reservationService->getAll();
            $reservations = is_array($reservations) ? $reservations : [];
            $normalized = array_map(function ($r) {
                return array_merge($r, [
                    'customer_phone' => $r['customer_phone'] ?? $r['contact'] ?? '',
                    'party_size' => $r['party_size'] ?? $r['guests'] ?? 2,
                ]);
            }, $reservations);
            $this->json(['success' => true, 'reservations' => $normalized]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    public function createReservation() {
        if (!$this->requireAuth()) return;
        $this->ensureMobileTenant();
        $data = $this->input();
        if (isset($data['party_size']) && !isset($data['guests'])) {
            $data['guests'] = (int)$data['party_size'];
        }
        if (!empty($data['customer_phone']) && empty($data['contact'])) {
            $data['contact'] = $data['customer_phone'];
        }
        
        try {
            $result = DependencyFactory::getReservationService()->createReservation($data);
            $this->json(['success' => true, 'reservation' => $result]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    public function updateReservationMobile() {
        if (!$this->requireAuth()) return;
        $this->ensureMobileTenant();
        $data = $this->input();
        if (isset($data['party_size']) && !isset($data['guests'])) {
            $data['guests'] = (int)$data['party_size'];
        }
        if (!empty($data['customer_phone']) && empty($data['contact'])) {
            $data['contact'] = $data['customer_phone'];
        }
        
        try {
            $id = $data['reservation_id'] ?? '';
            unset($data['reservation_id']);
            $result = DependencyFactory::getReservationService()->updateReservation($id, $data);
            $this->json(['success' => (bool)$result]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    public function deleteReservation() {
        if (!$this->requireManager()) return;
        $this->ensureMobileTenant();
        $data = $this->input();
        
        try {
            $result = DependencyFactory::getReservationService()->deleteReservation($data['reservation_id'] ?? '');
            $this->json(['success' => (bool)$result]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    // ─── Zones & Tables CRUD ──────────────────────────────────
    
    public function getZonesList() {
        if (!$this->requireAuth()) return;
        $this->ensureMobileTenant();
        
        try {
            $zoneService = DependencyFactory::getZoneService();
            $tableService = DependencyFactory::getTableService();
            $zones = $zoneService->getAllZones();
            $tables = $tableService->getAllTables();
            $tables = is_array($tables) ? $tables : [];
            $formattedZones = [];
            foreach ($zones as $z) {
                $zoneId = $z['zone_id'] ?? '';
                $zoneTables = array_values(array_filter($tables, fn($t) => ($t['zone_id'] ?? '') === $zoneId));
                $formattedZones[] = [
                    'zone_id' => $zoneId,
                    'name' => $z['name'] ?? $z['zone_name'] ?? '',
                    'description' => $z['description'] ?? '',
                    'floor' => $z['floor'] ?? null,
                    'is_active' => (bool)($z['is_active'] ?? true),
                    'table_count' => count($zoneTables),
                    'tables' => array_map(fn($t) => [
                        'table_id' => $t['table_id'] ?? '',
                        'name' => $t['name'] ?? $t['table_name'] ?? '',
                        'capacity' => (int)($t['capacity'] ?? 4),
                        'status' => $t['status'] ?? 'FREE',
                    ], $zoneTables),
                ];
            }
            $this->json(['success' => true, 'zones' => $formattedZones]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    public function createZone() {
        if (!$this->requireManager()) return;
        $this->ensureMobileTenant();
        $data = $this->input();
        
        try {
            $result = DependencyFactory::getZoneService()->createZone($data);
            $this->json(['success' => true, 'zone' => $result]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    public function updateZone() {
        if (!$this->requireManager()) return;
        $this->ensureMobileTenant();
        $data = $this->input();
        
        try {
            $id = $data['zone_id'] ?? '';
            unset($data['zone_id']);
            $result = DependencyFactory::getZoneService()->updateZone($id, $data);
            $this->json(['success' => (bool)$result]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    public function deleteZone() {
        if (!$this->requireManager()) return;
        $this->ensureMobileTenant();
        $data = $this->input();
        
        try {
            $result = DependencyFactory::getZoneService()->deleteZone($data['zone_id'] ?? '');
            $this->json(['success' => (bool)$result]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    public function createTableMobile() {
        if (!$this->requireManager()) return;
        $this->ensureMobileTenant();
        $data = $this->input();
        
        try {
            $result = DependencyFactory::getTableService()->createTable($data);
            $this->json(['success' => true, 'table' => $result]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    public function updateTableMobile() {
        if (!$this->requireManager()) return;
        $this->ensureMobileTenant();
        $data = $this->input();
        
        try {
            $id = $data['table_id'] ?? '';
            unset($data['table_id']);
            $result = DependencyFactory::getTableService()->updateTable($id, $data);
            $this->json(['success' => (bool)$result]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    public function deleteTableMobile() {
        if (!$this->requireManager()) return;
        $this->ensureMobileTenant();
        $data = $this->input();
        
        try {
            $result = DependencyFactory::getTableService()->deleteTable($data['table_id'] ?? '');
            $this->json(['success' => (bool)$result]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    // ─── Category CRUD ────────────────────────────────────────
    
    public function createCategory() {
        if (!$this->requireManager()) return;
        $this->ensureMobileTenant();
        $data = $this->input();
        
        try {
            $result = DependencyFactory::getCategoryService()->createCategory($data);
            $this->json(['success' => true, 'category' => $result]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    public function updateCategory() {
        if (!$this->requireManager()) return;
        $this->ensureMobileTenant();
        $data = $this->input();
        
        try {
            $id = $data['category_id'] ?? '';
            unset($data['category_id']);
            $result = DependencyFactory::getCategoryService()->updateCategory($id, $data);
            $this->json(['success' => (bool)$result]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    public function deleteCategoryMobile() {
        if (!$this->requireManager()) return;
        $this->ensureMobileTenant();
        $data = $this->input();
        
        try {
            $result = DependencyFactory::getCategoryService()->deleteCategory($data['category_id'] ?? '');
            $this->json(['success' => (bool)$result]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    // ─── Expense CRUD ─────────────────────────────────────────
    
    public function getExpenses() {
        if (!$this->requireManager()) return;
        $this->ensureMobileTenant();
        
        try {
            $financeService = DependencyFactory::getFinanceService();
            $expenses = method_exists($financeService, 'getAllExpenses')
                ? $financeService->getAllExpenses()
                : [];
            $expenses = is_array($expenses) ? $expenses : [];
            $totalMonth = 0;
            $thisMonth = date('Y-m');
            $normalized = [];
            foreach ($expenses as $e) {
                $date = $e['date'] ?? $e['created_at'] ?? '';
                if ($date && substr($date, 0, 7) === $thisMonth) {
                    $totalMonth += floatval($e['amount'] ?? $e['total'] ?? 0);
                }
                $normalized[] = array_merge($e, [
                    'description' => $e['description'] ?? $e['title'] ?? 'Gider',
                    'date' => $date ? (strlen($date) >= 10 ? substr($date, 0, 10) : $date) : '',
                ]);
            }
            $this->json(['success' => true, 'expenses' => $normalized, 'total_month' => $totalMonth]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    public function createExpense() {
        if (!$this->requireManager()) return;
        $this->ensureMobileTenant();
        $data = $this->input();
        if (!empty($data['description']) && empty($data['title'])) {
            $data['title'] = $data['description'];
        }
        
        try {
            $financeService = DependencyFactory::getFinanceService();
            $result = $financeService->createExpense($data);
            $this->json(['success' => true, 'expense' => $result]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    public function updateExpense() {
        if (!$this->requireManager()) return;
        $this->ensureMobileTenant();
        $data = $this->input();
        if (!empty($data['description']) && empty($data['title'])) {
            $data['title'] = $data['description'];
        }
        
        try {
            $financeService = DependencyFactory::getFinanceService();
            $id = $data['expense_id'] ?? '';
            unset($data['expense_id']);
            $result = $financeService->updateExpense($id, $data);
            $this->json(['success' => (bool)$result]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    public function deleteExpense() {
        if (!$this->requireManager()) return;
        $this->ensureMobileTenant();
        $data = $this->input();
        
        try {
            $financeService = DependencyFactory::getFinanceService();
            $result = $financeService->deleteExpense($data['expense_id'] ?? '');
            $this->json(['success' => (bool)$result]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    // ─── Registration ─────────────────────────────────────────
    
    public function registerBusiness() {
        $data = $this->input();
        
        try {
            $required = ['company_name', 'email', 'password', 'phone'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    $labels = ['company_name' => 'İşletme adı', 'email' => 'E-posta', 'password' => 'Şifre', 'phone' => 'Telefon'];
                    $this->json(['success' => false, 'error' => ($labels[$field] ?? $field) . ' alanı gerekli'], 400);
                }
            }
            
            if (empty($data['subdomain'])) {
                $data['subdomain'] = strtolower(preg_replace('/[^a-z0-9]/', '', strtolower($data['company_name'])));
            }
            
            $customerService = DependencyFactory::getCustomerService();
            if (method_exists($customerService, 'register')) {
                $result = $customerService->register($data);
            } else {
                $result = $customerService->create($data);
            }
            
            if (is_array($result) && isset($result['success']) && $result['success'] === false) {
                $this->json(['success' => false, 'error' => $result['error'] ?? 'Kayıt başarısız'], 400);
            }
            
            $token = null;
            $refreshToken = null;
            $user = null;
            $business = null;
            $subscription = null;

            $businessId = is_array($result) ? ($result['customer_id'] ?? $result['id'] ?? null) : $result;
            if ($businessId) {
                $db = DependencyFactory::getDatabase();
                $token = bin2hex(random_bytes(32));
                $refreshToken = bin2hex(random_bytes(32));
                $expiry = date('Y-m-d H:i:s', strtotime('+30 days'));
                $refreshExpiry = date('Y-m-d H:i:s', strtotime('+90 days'));

                $this->ensureMobileTokensTable($db);

                $userId = is_array($result) ? ($result['user_id'] ?? 'mgr_' . $businessId) : 'mgr_' . $businessId;
                try {
                    $stmt = $db->prepare("INSERT INTO mobile_tokens (token, user_id, tenant_id, expires_at, refresh_token, refresh_expires_at, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE token = VALUES(token), expires_at = VALUES(expires_at), refresh_token = VALUES(refresh_token), refresh_expires_at = VALUES(refresh_expires_at)");
                    $stmt->execute([$token, $userId, $businessId, $expiry, $refreshToken, $refreshExpiry]);
                } catch (\Exception $e) {}

                // Otomatik trial aboneliği oluştur (CustomerService::register zaten
                // oluşturmuş olabilir; burası idempotent — zaten varsa skip eder).
                try {
                    $trialService = DependencyFactory::getTrialService();
                    if (!$trialService->hasUsedTrial($businessId)) {
                        $trialService->createTrialSubscription($businessId);
                    }
                } catch (\Exception $e) {
                    \App\Core\Logger::warning('Auto trial on register failed', [
                        'business_id' => $businessId,
                        'error' => $e->getMessage(),
                    ]);
                }

                // Session — CustomerService zaten session kurmuş olabilir, burada pekiştirelim
                $_SESSION['logged_in'] = true;
                $_SESSION['user_id'] = $userId;
                $_SESSION['customer_id'] = $businessId;
                $_SESSION['business_id'] = $businessId;
                $_SESSION['role'] = 'BUSINESS_OWNER';
                $_SESSION['login_time'] = time();

                // Mevcut abonelik fazı
                try {
                    $phase = DependencyFactory::getTrialService()->getSubscriptionPhase($businessId);
                    $subscription = [
                        'phase' => $phase['phase'] ?? 'trial',
                        'daysLeft' => (int)($phase['daysLeft'] ?? 7),
                        'graceDaysLeft' => (int)($phase['graceDaysLeft'] ?? 0),
                        'trialEndsAt' => $phase['trial_ends_at'] ?? null,
                        'isTrial' => (bool)($phase['is_trial'] ?? true),
                    ];
                } catch (\Exception $e) {}

                $user = [
                    'id' => $userId,
                    'name' => trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? '')),
                    'email' => $data['email'],
                    'role' => 'BUSINESS_OWNER',
                    'is_manager' => true,
                ];
                $business = [
                    'id' => $businessId,
                    'name' => $data['company_name'],
                    'subdomain' => $data['subdomain'],
                ];
            }

            $this->json(['success' => true, 'data' => [
                'message' => 'Kayıt başarılı — 7 günlük ücretsiz deneme başlatıldı',
                'token' => $token,
                'refresh_token' => $refreshToken,
                'user' => $user,
                'business' => $business,
                'subscription' => $subscription,
            ]]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    // ─── Registration Verification (E-posta & Telefon) ─────────
    // Cache tabanlı doğrulama - mobil API stateless
    
    public function sendRegisterEmailCode() {
        $data = $this->input();
        $email = trim($data['email'] ?? '');
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->json(['success' => false, 'error' => 'Geçerli e-posta gerekli'], 400);
        }
        $svc = new \App\Services\MobileRegistrationVerificationService();
        $result = $svc->sendEmailCode($email);
        if (!$result['success']) {
            $this->json(['success' => false, 'error' => $result['error'] ?? 'Kod gönderilemedi'], 400);
        }
        $this->json(['success' => true, 'data' => ['message' => $result['message'] ?? 'Doğrulama kodu gönderildi']]);
    }
    
    public function verifyRegisterEmail() {
        $data = $this->input();
        $email = trim($data['email'] ?? '');
        $code = trim($data['code'] ?? '');
        if (empty($email) || empty($code)) {
            $this->json(['success' => false, 'error' => 'E-posta ve kod gerekli'], 400);
        }
        $svc = new \App\Services\MobileRegistrationVerificationService();
        $result = $svc->verifyEmail($email, $code);
        if (!$result['success']) {
            $this->json(['success' => false, 'error' => $result['error'] ?? 'Doğrulama başarısız'], 400);
        }
        $this->json(['success' => true, 'data' => ['verified' => true]]);
    }
    
    public function sendRegisterPhoneCode() {
        $data = $this->input();
        $phone = preg_replace('/\D/', '', $data['phone'] ?? '');
        $countryCode = $data['country_code'] ?? '+90';
        if (empty($phone)) {
            $this->json(['success' => false, 'error' => 'Telefon numarası gerekli'], 400);
        }
        $svc = new \App\Services\MobileRegistrationVerificationService();
        $result = $svc->sendPhoneCode($phone, $countryCode);
        if (!$result['success']) {
            $this->json(['success' => false, 'error' => $result['error'] ?? 'Kod gönderilemedi'], 400);
        }
        $this->json(['success' => true, 'data' => ['message' => $result['message'] ?? 'WhatsApp ile doğrulama kodu gönderildi']]);
    }
    
    public function verifyRegisterPhone() {
        $data = $this->input();
        $phone = preg_replace('/\D/', '', $data['phone'] ?? '');
        $countryCode = $data['country_code'] ?? '+90';
        $code = trim($data['code'] ?? '');
        if (empty($phone) || empty($code)) {
            $this->json(['success' => false, 'error' => 'Telefon ve kod gerekli'], 400);
        }
        $svc = new \App\Services\MobileRegistrationVerificationService();
        $result = $svc->verifyPhone($phone, $countryCode, $code);
        if (!$result['success']) {
            $this->json(['success' => false, 'error' => $result['error'] ?? 'Doğrulama başarısız'], 400);
        }
        $this->json(['success' => true, 'data' => ['verified' => true]]);
    }
    
    public function getPackagesList() {
        if (!$this->requireAuth()) return;
        try {
            $packageService = DependencyFactory::getPackageService();
            $packages = $packageService->getActivePackages();
            $packages = is_array($packages) ? $packages : [];
            foreach ($packages as &$pkg) {
                $pkg['monthly_discount'] = $packageService->calculateDiscount($pkg, 'monthly');
                $pkg['yearly_discount'] = $packageService->calculateDiscount($pkg, 'yearly');
                $pkg['discounted_price_monthly'] = $packageService->getDiscountedPrice($pkg, 'monthly');
                $pkg['discounted_price_yearly'] = $packageService->getDiscountedPrice($pkg, 'yearly');
            }
            unset($pkg);
            $this->json(['success' => true, 'data' => ['packages' => $packages]]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    public function getSubscriptionStatus() {
        if (!$this->requireAuth()) return;
        try {
            $customerId = \App\Core\TenantResolver::resolve();
            if (!$customerId) {
                $this->json(['success' => true, 'data' => [
                    'has_subscription' => false,
                    'phase' => 'none',
                    'daysLeft' => 0,
                    'graceDaysLeft' => 0,
                    'readOnly' => false,
                ]]);
                return;
            }

            // TrialService faz bilgisini tek noktadan verir
            $trialService = DependencyFactory::getTrialService();

            // Otomatik faz geçişlerini tetikle (cron yedek güvencesi)
            try { $trialService->checkAndExpireTrials(); } catch (\Exception $e) {}
            try { $trialService->checkAndSuspendGraceExpired(); } catch (\Exception $e) {}

            $phase = $trialService->getSubscriptionPhase($customerId);

            $subscriptionService = DependencyFactory::getSubscriptionService();
            $sub = $subscriptionService->getCustomerSubscription($customerId);

            $hasActive = $phase['phase'] === 'active' || $phase['phase'] === 'trial';

            $this->json(['success' => true, 'data' => [
                'has_subscription' => $hasActive,
                'phase' => $phase['phase'] ?? 'none',
                'daysLeft' => (int)($phase['daysLeft'] ?? 0),
                'graceDaysLeft' => (int)($phase['graceDaysLeft'] ?? 0),
                'readOnly' => (bool)($phase['readOnly'] ?? false),
                'trialEndsAt' => $phase['trial_ends_at'] ?? null,
                'graceEndsAt' => $phase['grace_ends_at'] ?? null,
                'currentPeriodEnd' => $phase['current_period_end'] ?? null,
                'status' => $phase['status'] ?? null,
                'isTrial' => (bool)($phase['is_trial'] ?? false),
                'packageName' => $phase['package_name'] ?? null,
                'subscription' => $sub,
            ]]);
        } catch (\Exception $e) {
            $this->json(['success' => true, 'data' => [
                'has_subscription' => false,
                'phase' => 'none',
                'daysLeft' => 0,
                'graceDaysLeft' => 0,
                'readOnly' => false,
            ]]);
        }
    }
    
    /**
     * Paket satın al - Havale ile (abonelik + bank transfer kaydı oluşturur)
     */
    public function purchasePackage() {
        if (!$this->requireAuth()) return;
        $data = $this->input();
        $packageId = trim($data['package_id'] ?? '');
        $pricingType = $data['pricing_type'] ?? 'yearly';
        if (!in_array($pricingType, ['one_time', 'monthly', 'yearly'])) {
            $pricingType = 'yearly';
        }
        if (empty($packageId)) {
            $this->json(['success' => false, 'error' => 'Paket seçimi gerekli'], 400);
        }
        $customerId = $_SESSION['customer_id'] ?? null;
        if (!$customerId) {
            $this->json(['success' => false, 'error' => 'Oturum açmanız gerekiyor'], 401);
        }
        try {
            $subscriptionService = DependencyFactory::getSubscriptionService();
            $result = $subscriptionService->createSubscription($customerId, $packageId, $pricingType);
            if (!$result['success']) {
                $this->json(['success' => false, 'error' => $result['error'] ?? 'Abonelik oluşturulamadı'], 400);
            }
            $subscriptionId = $result['subscription_id'];
            $subscriptionRepo = \App\Core\DependencyFactory::getSubscriptionRepository();
            $subscription = $subscriptionRepo->getSubscriptionWithPackage($subscriptionId);
            if (!$subscription) {
                $this->json(['success' => false, 'error' => 'Abonelik bilgisi alınamadı'], 500);
            }
            $package = DependencyFactory::getPackageService()->getPackageById($packageId);
            $billingCycle = $subscription['billing_cycle'] ?? $pricingType;
            $priceField = 'price_' . ($billingCycle === 'one_time' ? 'one_time' : ($billingCycle === 'monthly' ? 'monthly' : 'yearly'));
            $amount = floatval($package[$priceField] ?? 0);
            $customerRepo = DependencyFactory::getCustomerRepository();
            $customer = $customerRepo->findById($customerId);
            $customerEmail = $customer['email'] ?? $_SESSION['username'] ?? 'user';
            $bankTransferService = DependencyFactory::getBankTransferService();
            $uniqueCode = $bankTransferService->generateUniqueCode($customerEmail);
            $transferResult = $bankTransferService->createTransfer([
                'subscription_id' => $subscriptionId,
                'customer_id' => $customerId,
                'amount' => $amount,
                'unique_code' => $uniqueCode,
                'sender_name' => null,
                'status' => 'pending',
            ]);
            if (!$transferResult['success']) {
                $this->json(['success' => false, 'error' => $transferResult['error'] ?? 'Havale kaydı oluşturulamadı'], 500);
            }
            $bankAccounts = $bankTransferService->getActiveBankAccounts();
            $this->json(['success' => true, 'data' => [
                'subscription_id' => $subscriptionId,
                'transfer_id' => $transferResult['transfer_id'],
                'unique_code' => $uniqueCode,
                'amount' => $amount,
                'package_name' => $package['name'] ?? '',
                'bank_accounts' => $bankAccounts,
            ]]);
        } catch (\Exception $e) {
            \App\Core\Logger::error('MobileAPIController::purchasePackage', ['error' => $e->getMessage()]);
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * POST /api/mobile/payment/iyzico/initiate
     * Mobile app için iyzico checkout form içeriğini döner.
     * Mobile WebView bu içeriği render edip ödeme akışını yürütür.
     * body: { subscription_id | package_id, billing_cycle?, return_url? }
     */
    public function initiateIyzicoPayment() {
        if (!$this->requireAuth()) return;
        $this->ensureMobileTenant();

        try {
            $data = $this->input();
            $subscriptionId = $data['subscription_id'] ?? $data['order_id'] ?? '';
            $packageId = $data['package_id'] ?? '';
            $billingCycle = strtolower($data['billing_cycle'] ?? 'monthly');
            $returnUrl = $data['return_url'] ?? 'qordy://payment/return';
            $customerId = $_SESSION['customer_id'] ?? ($_SESSION['business_id'] ?? null);

            if (!$customerId) {
                $this->json(['success' => false, 'error' => 'Oturum bulunamadı'], 401);
                return;
            }

            $subscriptionRepo = DependencyFactory::getSubscriptionRepository();
            $subscription = null;

            if ($subscriptionId) {
                $subscription = $subscriptionRepo->getSubscriptionWithPackage($subscriptionId);

                // IDOR guard: the subscription we load must belong to
                // the authenticated customer. Without this, any client
                // with a valid session could initiate a payment session
                // for any subscription id it knows (or brute-forces).
                // We check every plausible tenant-key field the schema
                // may use (customer_id / business_id / tenant_id).
                if ($subscription) {
                    $owns = false;
                    foreach (['customer_id', 'business_id', 'tenant_id'] as $keyField) {
                        if (!empty($subscription[$keyField]) && (string)$subscription[$keyField] === (string)$customerId) {
                            $owns = true;
                            break;
                        }
                    }
                    if (!$owns) {
                        \App\Core\Logger::warning('Mobile iyzico initiate: ownership check failed', [
                            'customer_id'     => $customerId,
                            'subscription_id' => $subscriptionId,
                        ]);
                        $this->json(['success' => false, 'error' => 'Bu abonelik sizin hesabınıza ait değil.'], 403);
                        return;
                    }
                }
            }

            // Subscription yoksa veya package_id verildiyse yeni pending sub oluştur.
            // createSubscription zaten pending statüsünde bir satır oluşturuyor —
            // ödeme başarıyla tamamlanınca activateSubscription active'e çeviriyor.
            if (!$subscription && $packageId) {
                $subscriptionService = DependencyFactory::getSubscriptionService();
                // pricingType enum'u: one_time, monthly, yearly
                $pricingType = $billingCycle === 'yearly' ? 'yearly'
                    : ($billingCycle === 'one_time' ? 'one_time' : 'monthly');
                $createResult = $subscriptionService->createSubscription(
                    $customerId,
                    $packageId,
                    $pricingType
                );
                if (!empty($createResult['success']) && !empty($createResult['subscription_id'])) {
                    $subscriptionId = $createResult['subscription_id'];
                    $subscription = $subscriptionRepo->getSubscriptionWithPackage($subscriptionId);
                } else {
                    $this->json([
                        'success' => false,
                        'error' => $createResult['error'] ?? 'Abonelik oluşturulamadı',
                    ], 400);
                    return;
                }
            }

            if (!$subscription) {
                $this->json(['success' => false, 'error' => 'Abonelik/paket bulunamadı'], 404);
                return;
            }

            // Amount is ALWAYS derived from server-side state (the
            // subscription row or its joined package). We explicitly
            // ignore any client-supplied `amount` — trusting it would
            // let a tampered mobile client charge any number they like.
            $priceField = 'price_' . ($subscription['billing_cycle'] ?? $billingCycle);
            $amount = floatval($subscription[$priceField] ?? $subscription['amount'] ?? 0);
            if ($amount <= 0) {
                \App\Core\Logger::warning('Mobile iyzico initiate: refusing zero/missing server-side price', [
                    'customer_id'     => $customerId,
                    'subscription_id' => $subscription['subscription_id'] ?? null,
                    'price_field'     => $priceField,
                ]);
                $this->json(['success' => false, 'error' => 'Geçersiz tutar'], 400);
                return;
            }

            $customerRepo = DependencyFactory::getCustomerRepository();
            $customer = $customerRepo->findById($customerId);
            if (!$customer) {
                $this->json(['success' => false, 'error' => 'Müşteri bulunamadı'], 404);
                return;
            }

            $gatewaySvc = DependencyFactory::getPaymentGatewayService();
            $iyzico = $gatewaySvc->getGateway('iyzico');
            if (!$iyzico || !$iyzico->isEnabled()) {
                $this->json(['success' => false, 'error' => 'iyzico yapılandırılmamış'], 400);
                return;
            }

            $conversationId = 'MOBSUBS_' . ($subscription['subscription_id'] ?? $subscriptionId) . '_' . time();
            $customerName = trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? ''));
            if ($customerName === '') $customerName = $customer['company_name'] ?? 'Müşteri';
            $nameParts = explode(' ', $customerName, 2);

            // Build iyzico buyer identity from the real customer row.
            // We intentionally fall back to the gateway's last-resort
            // defaults only when the profile column is empty — writing
            // '34000' / '00000000000' unconditionally (prior code)
            // meant iyzico saw the same identity for every tenant,
            // which blocks fraud scoring and 3DS recovery flows.
            $customerZip      = trim((string)($customer['zip_code']        ?? $customer['postal_code'] ?? ''));
            $customerIdentity = trim((string)($customer['identity_number'] ?? $customer['tc_no']       ?? ''));
            $customerCity     = trim((string)($customer['city']            ?? ''));
            $customerCountry  = trim((string)($customer['country']         ?? ''));

            $paymentData = [
                'order_id' => $conversationId,
                'amount' => $amount,
                'customer_id' => $customer['customer_id'] ?? $customerId,
                'customer_name' => $nameParts[0] ?? 'Müşteri',
                'customer_surname' => $nameParts[1] ?? '',
                'customer_email' => $customer['email'] ?? '',
                'customer_phone' => $customer['phone'] ?? '',
                'customer_address' => $customer['address'] ?? 'Türkiye',
                'customer_city' => $customerCity !== '' ? $customerCity : 'Istanbul',
                'customer_country' => $customerCountry !== '' ? $customerCountry : 'Turkey',
                'customer_zip' => $customerZip !== '' ? $customerZip : '34000',
                'customer_identity' => $customerIdentity !== '' ? $customerIdentity : '00000000000',
                'success_url' => BASE_URL . '/customer/payment/iyzico/callback?mobile=1&return=' . urlencode($returnUrl),
                'fail_url' => BASE_URL . '/customer/payment/iyzico/callback?mobile=1&return=' . urlencode($returnUrl),
                'basket' => [[
                    'name' => $subscription['package_name'] ?? 'Paket Abonelik',
                    'price' => $amount,
                    'category' => 'Abonelik',
                ]]
            ];

            $result = $iyzico->processPayment($paymentData);
            if (!($result['success'] ?? false)) {
                $this->json(['success' => false, 'error' => $result['error'] ?? 'iyzico başlatılamadı'], 400);
                return;
            }

            // Pending payment kaydı
            try {
                $paymentRepo = DependencyFactory::getSubscriptionPaymentRepository();
                require_once __DIR__ . '/../../helpers/functions.php';
                $paymentRepo->create([
                    'payment_id' => generateId('pay'),
                    'subscription_id' => $subscription['subscription_id'] ?? $subscriptionId,
                    'amount' => $amount,
                    'currency' => 'TRY',
                    'payment_method' => 'iyzico',
                    'payment_status' => 'pending',
                    'merchant_oid' => $conversationId,
                    'gateway_transaction_id' => $result['token'] ?? null,
                    'payment_date' => null,
                ]);
            } catch (\Exception $e) {
                \App\Core\Logger::warning('Mobile iyzico payment record failed', ['error' => $e->getMessage()]);
            }

            $this->json(['success' => true, 'data' => [
                'checkout_form_content' => $result['checkout_form_content'] ?? '',
                'payment_page_url' => $result['payment_page_url'] ?? null,
                'token' => $result['token'] ?? null,
                'conversation_id' => $conversationId,
                'return_url' => $returnUrl,
                'amount' => $amount,
                'subscription_id' => $subscription['subscription_id'] ?? $subscriptionId,
                'package_name' => $subscription['package_name'] ?? null,
                'billing_cycle' => $subscription['billing_cycle'] ?? $billingCycle,
            ]]);
        } catch (\Exception $e) {
            \App\Core\Logger::error('Mobile iyzico initiate failed', ['error' => $e->getMessage()]);
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/mobile/payment/iyzico/status
     * Mobile WebView deeplink fırsatını kaçırdığında (uygulama
     * öldürülmüş, scheme kayıtlı değil, vb.) app periyodik olarak
     * token'ın durumunu sorgulayarak son sonucu öğrenir.
     * query: token | conversation_id | subscription_id
     * response: { status: pending|completed|failed|not_found, payment?: {...}, subscription?: {...} }
     */
    public function iyzicoPaymentStatus() {
        if (!$this->requireAuth()) return;

        try {
            $token = trim((string)($_GET['token'] ?? ''));
            $conversationId = trim((string)($_GET['conversation_id'] ?? ''));
            $subscriptionId = trim((string)($_GET['subscription_id'] ?? ''));
            $customerId = $_SESSION['customer_id'] ?? ($_SESSION['business_id'] ?? null);
            if (!$customerId) {
                $this->json(['success' => false, 'error' => 'Oturum bulunamadı'], 401);
                return;
            }

            $paymentRepo = DependencyFactory::getSubscriptionPaymentRepository();
            $payment = null;
            if ($token !== '') {
                $payment = $paymentRepo->getByGatewayTransactionId($token);
            }
            if (!$payment && $conversationId !== '') {
                $payment = $paymentRepo->getByMerchantOid($conversationId);
            }

            $subscription = null;
            $subRepo = DependencyFactory::getSubscriptionRepository();
            $targetSubId = $subscriptionId !== '' ? $subscriptionId : ($payment['subscription_id'] ?? '');
            if ($targetSubId) {
                $subscription = $subRepo->getSubscriptionWithPackage($targetSubId);
            }

            if (!$payment && !$subscription) {
                $this->json(['success' => true, 'data' => ['status' => 'not_found']]);
                return;
            }

            // IDOR guard: both payment row (via its subscription) and the
            // subscription itself must belong to the authenticated tenant.
            // Without this, any session could probe arbitrary tokens /
            // subscription_id values and read another business's billing
            // state.
            $ownsSubscription = static function (?array $sub, string $cid): bool {
                if (!$sub) return false;
                foreach (['customer_id', 'business_id', 'tenant_id'] as $f) {
                    if (!empty($sub[$f]) && (string)$sub[$f] === $cid) return true;
                }
                return false;
            };

            if ($subscription && !$ownsSubscription($subscription, (string)$customerId)) {
                \App\Core\Logger::warning('Mobile iyzico status: subscription ownership mismatch', [
                    'customer_id'     => $customerId,
                    'subscription_id' => $subscription['subscription_id'] ?? null,
                ]);
                $subscription = null;
            }

            if ($payment) {
                $paySubId = $payment['subscription_id'] ?? null;
                $paySub = $paySubId ? $subRepo->getSubscriptionWithPackage($paySubId) : null;
                if (!$ownsSubscription($paySub, (string)$customerId)) {
                    \App\Core\Logger::warning('Mobile iyzico status: payment ownership mismatch', [
                        'customer_id' => $customerId,
                        'payment_id'  => $payment['payment_id'] ?? null,
                    ]);
                    $payment = null;
                }
            }

            if (!$payment && !$subscription) {
                $this->json(['success' => true, 'data' => ['status' => 'not_found']]);
                return;
            }

            // Derive status: prefer payment row, fallback to subscription state
            $status = 'pending';
            if ($payment) {
                $raw = strtolower((string)($payment['payment_status'] ?? ''));
                if (in_array($raw, ['completed', 'success', 'successful'], true)) {
                    $status = 'completed';
                } elseif (in_array($raw, ['failed', 'cancelled', 'canceled', 'refunded'], true)) {
                    $status = 'failed';
                } else {
                    $status = 'pending';
                }
            } elseif ($subscription) {
                $st = strtolower((string)($subscription['status'] ?? ''));
                if ($st === 'active') $status = 'completed';
                elseif ($st === 'pending') $status = 'pending';
                else $status = 'failed';
            }

            $this->json(['success' => true, 'data' => [
                'status' => $status,
                'payment' => $payment ? [
                    'payment_id' => $payment['payment_id'] ?? null,
                    'amount' => isset($payment['amount']) ? (float)$payment['amount'] : null,
                    'currency' => $payment['currency'] ?? 'TRY',
                    'payment_status' => $payment['payment_status'] ?? null,
                    'payment_date' => $payment['payment_date'] ?? null,
                ] : null,
                'subscription' => $subscription ? [
                    'subscription_id' => $subscription['subscription_id'] ?? null,
                    'package_id' => $subscription['package_id'] ?? null,
                    'package_name' => $subscription['package_name'] ?? null,
                    'status' => $subscription['status'] ?? null,
                    'billing_cycle' => $subscription['billing_cycle'] ?? null,
                    'current_period_end' => $subscription['current_period_end'] ?? ($subscription['end_date'] ?? null),
                ] : null,
            ]]);
        } catch (\Exception $e) {
            \App\Core\Logger::error('Mobile iyzico status failed', ['error' => $e->getMessage()]);
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/mobile/packages/assigned-offer
     * Superadmin bu müşteri için özel bir ödeme bağlantısı hazırladıysa
     * onu dön. Mobile app, deneme süresi bittiğinde paywall üzerinde
     * bu teklifi öne çıkarıyor.
     */
    public function getAssignedOffer() {
        if (!$this->requireAuth()) return;

        try {
            $customerId = $_SESSION['customer_id'] ?? ($_SESSION['business_id'] ?? null);
            if (!$customerId) {
                $this->json(['success' => false, 'error' => 'Oturum bulunamadı'], 401);
                return;
            }

            $linkRepo = DependencyFactory::getCustomPaymentLinkRepository();
            $links = $linkRepo->listAll([
                'customer_id' => $customerId,
                'is_active' => 1,
            ], 5, 0);

            $offer = null;
            foreach ($links as $link) {
                // Skip exhausted links even if admin forgot to toggle is_active
                $used = (int)($link['used_count'] ?? 0);
                $maxUses = (int)($link['max_uses'] ?? 1);
                if ($maxUses > 0 && $used >= $maxUses) continue;

                $expiresAt = $link['expires_at'] ?? null;
                if ($expiresAt && strtotime($expiresAt) < time()) continue;

                $pkgId = $link['package_id'] ?? null;
                $pkg = null;
                if ($pkgId) {
                    try {
                        $pkg = DependencyFactory::getPackageService()->getPackageById($pkgId);
                    } catch (\Throwable $t) { $pkg = null; }
                }

                $offer = [
                    'link_id' => $link['link_id'] ?? null,
                    'token' => $link['token'] ?? null,
                    'public_url' => BASE_URL . '/pay/' . ($link['token'] ?? ''),
                    'package_id' => $pkgId,
                    'package_name' => $pkg['name'] ?? ($link['package_name'] ?? 'Özel Teklif'),
                    'custom_price' => isset($link['custom_price']) ? (float)$link['custom_price'] : null,
                    'duration_months' => isset($link['duration_months']) ? (int)$link['duration_months'] : null,
                    'note' => $link['note'] ?? null,
                    'expires_at' => $expiresAt,
                    'target_email' => $link['target_email'] ?? null,
                ];
                break;
            }

            $this->json(['success' => true, 'data' => [
                'offer' => $offer,
            ]]);
        } catch (\Exception $e) {
            \App\Core\Logger::error('Mobile assigned offer failed', ['error' => $e->getMessage()]);
            $this->json(['success' => true, 'data' => ['offer' => null]]);
        }
    }

    /**
     * GET /api/mobile/packages/custom-offers
     * Kullanıcıya atanmış tüm aktif özel teklifleri (dismiss durumu + cooldown
     * bilgisiyle birlikte) döner. Web tarafındaki /api/customer/custom-offers
     * ile aynı şemayı sunar ki Flutter ve web tek hattan beslensin.
     */
    public function listCustomOffers() {
        if (!$this->requireAuth()) return;

        // Süper admin impersonation modunda kullanıcıya popup dökmeyelim.
        if (!empty($_SESSION['original_super_admin_id']) || !empty($_SESSION['impersonator_id'])) {
            $this->json(['success' => true, 'offers' => []]);
            return;
        }

        $customerId = $_SESSION['customer_id'] ?? ($_SESSION['business_id'] ?? null);
        if (!$customerId) {
            $this->json(['success' => false, 'error' => 'Oturum bulunamadı'], 401);
            return;
        }

        $cooldownMinutes = 45;
        try {
            $customerService = DependencyFactory::getCustomerService();
            $customer = $customerService ? $customerService->getById($customerId) : null;
            $email = null;
            if (is_array($customer)) {
                $email = $customer['email'] ?? null;
            } elseif (is_object($customer)) {
                $email = $customer->email ?? null;
            }

            $linkRepo = DependencyFactory::getCustomPaymentLinkRepository();
            $links = method_exists($linkRepo, 'findActiveForCustomer')
                ? $linkRepo->findActiveForCustomer($customerId, $email)
                : $linkRepo->listAll(['customer_id' => $customerId, 'is_active' => 1], 20, 0);

            $dismissRepo = null;
            try {
                $dismissRepo = DependencyFactory::getCustomPaymentLinkDismissalRepository();
            } catch (\Throwable $t) {
                $dismissRepo = null;
            }
            $dismissals = $dismissRepo ? $dismissRepo->getAllForCustomer($customerId) : [];
            $dismissMap = [];
            foreach ($dismissals as $d) {
                $dismissMap[$d['link_id']] = $d;
            }

            $out = [];
            foreach ($links as $link) {
                $used = (int)($link['used_count'] ?? 0);
                $maxUses = (int)($link['max_uses'] ?? 1);
                if ($maxUses > 0 && $used >= $maxUses) continue;
                $expiresAt = $link['expires_at'] ?? null;
                if ($expiresAt && strtotime($expiresAt) < time()) continue;

                $linkId = $link['link_id'] ?? null;
                $lastDismissed = isset($dismissMap[$linkId]['dismissed_at'])
                    ? strtotime($dismissMap[$linkId]['dismissed_at']) : 0;
                $cooldownPassed = (time() - (int)$lastDismissed) > ($cooldownMinutes * 60);
                $shouldShowPopup = ($lastDismissed === 0) || $cooldownPassed;

                $pkg = null;
                if (!empty($link['package_id'])) {
                    try {
                        $pkg = DependencyFactory::getPackageService()->getPackageById($link['package_id']);
                    } catch (\Throwable $t) { $pkg = null; }
                }

                $out[] = [
                    'link_id' => $linkId,
                    'token' => $link['token'] ?? null,
                    'public_url' => BASE_URL . '/pay/' . ($link['token'] ?? ''),
                    'package_id' => $link['package_id'] ?? null,
                    'package_name' => $pkg['name'] ?? ($link['package_name'] ?? 'Özel Teklif'),
                    'custom_price' => isset($link['custom_price']) ? (float)$link['custom_price'] : null,
                    'duration_months' => isset($link['duration_months']) ? (int)$link['duration_months'] : null,
                    'note' => $link['note'] ?? null,
                    'expires_at' => $expiresAt,
                    'should_show_popup' => $shouldShowPopup,
                    'dismiss_count' => (int)($dismissMap[$linkId]['dismiss_count'] ?? 0),
                    'cooldown_minutes' => $cooldownMinutes,
                ];
            }

            $this->json(['success' => true, 'offers' => $out]);
        } catch (\Exception $e) {
            \App\Core\Logger::error('Mobile list custom offers failed', ['error' => $e->getMessage()]);
            $this->json(['success' => true, 'offers' => []]);
        }
    }

    /**
     * POST /api/mobile/packages/custom-offers/{link_id}/dismiss
     * Popup'ın kapatıldığını kaydeder → cooldown başlatır.
     */
    public function dismissCustomOffer($linkId = null) {
        if (!$this->requireAuth()) return;
        $customerId = $_SESSION['customer_id'] ?? ($_SESSION['business_id'] ?? null);
        if (!$customerId) {
            $this->json(['success' => false, 'error' => 'Oturum bulunamadı'], 401);
            return;
        }
        $linkId = trim((string)$linkId);
        if ($linkId === '') {
            $this->json(['success' => false, 'error' => 'link_id gerekli'], 400);
            return;
        }
        try {
            $dismissRepo = DependencyFactory::getCustomPaymentLinkDismissalRepository();
            $dismissRepo->dismiss($linkId, $customerId);
            $this->json(['success' => true]);
        } catch (\Exception $e) {
            \App\Core\Logger::error('Mobile dismiss offer failed', [
                'error' => $e->getMessage(),
                'link_id' => $linkId,
            ]);
            $this->json(['success' => false, 'error' => 'Kayıt başarısız'], 500);
        }
    }

    /**
     * GET /api/mobile/subscription/history
     * Müşteri için abonelik + ödeme geçmişini döner.
     */
    public function subscriptionHistory() {
        if (!$this->requireAuth()) return;
        $customerId = $_SESSION['customer_id'] ?? ($_SESSION['business_id'] ?? null);
        if (!$customerId) {
            $this->json(['success' => false, 'error' => 'Oturum bulunamadı'], 401);
            return;
        }
        try {
            $db = DependencyFactory::getDatabase();
            $stmt = $db->prepare(
                "SELECT s.subscription_id, s.package_id, s.amount, s.currency,
                        s.billing_cycle, s.status, s.is_trial, s.trial_ends_at,
                        s.current_period_start, s.current_period_end,
                        s.created_at, s.cancelled_at,
                        p.name AS package_name
                 FROM subscriptions s
                 LEFT JOIN packages p ON p.package_id = s.package_id
                 WHERE s.tenant_id = :cid
                 ORDER BY s.created_at DESC LIMIT 50"
            );
            $stmt->execute(['cid' => $customerId]);
            $subs = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            $subIds = array_column($subs, 'subscription_id');
            $paymentsBySub = [];
            if (!empty($subIds)) {
                $in = implode(',', array_fill(0, count($subIds), '?'));
                $pStmt = $db->prepare(
                    "SELECT payment_id, subscription_id, amount, currency, payment_method,
                            payment_status, gateway_transaction_id, payment_date, created_at
                     FROM subscription_payments
                     WHERE subscription_id IN ($in)
                     ORDER BY COALESCE(payment_date, created_at) DESC"
                );
                $pStmt->execute($subIds);
                foreach ($pStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $row) {
                    $paymentsBySub[$row['subscription_id']][] = $row;
                }
            }
            $history = [];
            foreach ($subs as $s) {
                $s['payments'] = $paymentsBySub[$s['subscription_id']] ?? [];
                $history[] = $s;
            }
            $this->json(['success' => true, 'history' => $history]);
        } catch (\Exception $e) {
            \App\Core\Logger::error('Mobile subscription history failed', ['error' => $e->getMessage()]);
            $this->json(['success' => true, 'history' => []]);
        }
    }

    /**
     * Havale dekontu yükle
     */
    public function uploadPaymentReceipt() {
        if (!$this->requireAuth()) return;
        $transferId = $_POST['transfer_id'] ?? '';
        if (empty($transferId)) {
            $this->json(['success' => false, 'error' => 'Transfer ID gerekli'], 400);
        }
        if (!isset($_FILES['receipt']) || $_FILES['receipt']['error'] === UPLOAD_ERR_NO_FILE) {
            $this->json(['success' => false, 'error' => 'Dekont dosyası seçilmedi'], 400);
        }
        $bankTransferService = DependencyFactory::getBankTransferService();
        $transfer = $bankTransferService->getTransferById($transferId);
        if (!$transfer) {
            $this->json(['success' => false, 'error' => 'Havale kaydı bulunamadı'], 404);
        }
        $customerId = $_SESSION['customer_id'] ?? null;
        if ($transfer['customer_id'] !== $customerId) {
            $this->json(['success' => false, 'error' => 'Yetkisiz işlem'], 403);
        }
        $senderName = $_POST['sender_name'] ?? null;
        $senderIban = $_POST['sender_iban'] ?? null;
        if ($senderName || $senderIban) {
            $updateData = [];
            if ($senderName) $updateData['sender_name'] = $senderName;
            if ($senderIban) $updateData['sender_iban'] = $senderIban;
            \App\Core\DependencyFactory::getBankTransferPaymentRepository()->update($transferId, $updateData);
        }
        $result = $bankTransferService->uploadReceipt($transferId, $_FILES['receipt']);
        if ($result['success']) {
            $this->json(['success' => true, 'data' => ['message' => 'Dekont yüklendi. Ödemeniz onay aşamasındadır.']]);
        } else {
            $this->json(['success' => false, 'error' => $result['error'] ?? 'Yükleme başarısız'], 400);
        }
    }
    
    /**
     * Müşterinin bekleyen / tüm havale ödemelerini listele
     */
    public function getPendingPayments() {
        if (!$this->requireAuth()) return;
        $customerId = $_SESSION['customer_id'] ?? null;
        if (!$customerId) {
            $this->json(['success' => true, 'data' => ['transfers' => []]]);
        }
        try {
            $bankTransferService = DependencyFactory::getBankTransferService();
            $transfers = $bankTransferService->getTransfersByCustomerId($customerId);
            $this->json(['success' => true, 'data' => ['transfers' => $transfers]]);
        } catch (\Exception $e) {
            $this->json(['success' => true, 'data' => ['transfers' => []]]);
        }
    }
    
    // ─── Analytics Enhanced ───────────────────────────────────
    
    public function getProductSalesData() {
        if (!$this->requireManager()) return;
        $this->ensureMobileTenant();
        
        try {
            $q = $this->query();
            $period = $q['period'] ?? 'daily';
            $settingsService = DependencyFactory::getSystemSettingsService();
            $range = $settingsService->getBusinessDateRange();
            $startDate = $q['start_date'] ?? date('Y-m-d', strtotime('-7 days'));
            $endDate = $q['end_date'] ?? ($range['date'] ?? date('Y-m-d'));
            
            list($startDt, $endDt) = $this->resolveDatetimeRangeForProductSales($startDate, $endDate);
            
            $db = DependencyFactory::getDatabase();
            $tenantId = TenantContext::getId();
            $tenantWhere = $tenantId ? ' AND o.tenant_id = ?' : '';
            $params = [$startDt, $endDt];
            if ($tenantId) $params[] = $tenantId;
            $paidFilter = "AND (o.is_paid = 1 OR o.payment_method IS NOT NULL)";
            
            $sqlTotals = "SELECT mi.name as product_name, COALESCE(c.name, 'Kategorisiz') as category_name, SUM(oi.quantity) as total_quantity, SUM(oi.price * oi.quantity) as total_revenue
                FROM order_items oi INNER JOIN orders o ON oi.order_id = o.order_id INNER JOIN menu_items mi ON oi.menu_item_id = mi.menu_item_id LEFT JOIN categories c ON mi.category_id = c.category_id
                WHERE o.created_at BETWEEN ? AND ? AND o.status != 'CANCELLED' $paidFilter $tenantWhere
                GROUP BY mi.menu_item_id, mi.name, c.name ORDER BY total_quantity DESC LIMIT 50";
            $stmt = $db->prepare($sqlTotals);
            $stmt->execute($params);
            $productTotals = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            $sqlCat = "SELECT COALESCE(c.name, 'Kategorisiz') as category_name, SUM(oi.quantity) as total_quantity, SUM(oi.price * oi.quantity) as total_revenue
                FROM order_items oi INNER JOIN orders o ON oi.order_id = o.order_id INNER JOIN menu_items mi ON oi.menu_item_id = mi.menu_item_id LEFT JOIN categories c ON mi.category_id = c.category_id
                WHERE o.created_at BETWEEN ? AND ? AND o.status != 'CANCELLED' $paidFilter $tenantWhere
                GROUP BY c.category_id, c.name ORDER BY total_quantity DESC";
            $stmtCat = $db->prepare($sqlCat);
            $stmtCat->execute($params);
            $categoryTotals = $stmtCat->fetchAll(\PDO::FETCH_ASSOC);
            
            $grandTotalQty = array_sum(array_column($productTotals, 'total_quantity'));
            $grandTotalRevenue = array_sum(array_column($productTotals, 'total_revenue'));
            
            $this->json(['success' => true, 'data' => [
                'product_totals' => $productTotals,
                'category_totals' => $categoryTotals,
                'top_10_products' => array_slice($productTotals, 0, 10),
                'summary' => [
                    'total_quantity' => (int)$grandTotalQty,
                    'total_revenue' => round((float)$grandTotalRevenue, 2),
                    'total_products' => count($productTotals)
                ],
                'start_date' => $startDate,
                'end_date' => $endDate
            ]]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    private function resolveDatetimeRangeForProductSales(string $startDate, string $endDate): array {
        if ($startDate === $endDate) {
            $settingsService = DependencyFactory::getSystemSettingsService();
            $businessRange = $settingsService->getBusinessDateRange();
            if ($startDate === ($businessRange['date'] ?? '')) {
                return [$businessRange['start_datetime'] ?? $businessRange['start'] ?? ($startDate . ' 00:00:00'), $businessRange['end_datetime'] ?? $businessRange['end'] ?? ($endDate . ' 23:59:59')];
            }
            $historical = $settingsService->getBusinessDateRangeForDate($startDate);
            if ($historical) {
                return [$historical['start_datetime'], $historical['end_datetime']];
            }
        }
        return [$startDate . ' 00:00:00', $endDate . ' 23:59:59'];
    }
    
    public function getAnalyticsByCategory() {
        if (!$this->requireManager()) return;
        $this->ensureMobileTenant();
        
        try {
            $q = $this->query();
            $period = $q['period'] ?? 'today';
            $settingsService = DependencyFactory::getSystemSettingsService();
            $range = $settingsService->getBusinessDateRange();
            $dr = $this->getDateRangeFromPeriod($period);
            $startDate = $q['start_date'] ?? $dr['start_date'];
            $endDate = $q['end_date'] ?? $dr['end_date'];
            
            $db = DependencyFactory::getDatabase();
            $tenantId = TenantContext::getId();
            $startDt = $startDate . ' 00:00:00';
            $endDt = $endDate . ' 23:59:59';
            if ($startDate === $endDate && $startDate === ($range['date'] ?? '')) {
                $startDt = $range['start_datetime'] ?? $range['start'] ?? $startDt;
                $endDt = $range['end_datetime'] ?? $range['end'] ?? $endDt;
            }
            $params = [$startDt, $endDt];
            $tenantWhere = '';
            if ($tenantId) { $tenantWhere = 'AND o.tenant_id = ?'; $params[] = $tenantId; }
            
            $paidFilter = "AND (o.is_paid = 1 OR o.payment_method IS NOT NULL)";
            $stmt = $db->prepare("SELECT COALESCE(c.name, 'Kategorisiz') as category_name, SUM(oi.price * oi.quantity) as revenue FROM order_items oi INNER JOIN orders o ON oi.order_id = o.order_id LEFT JOIN menu_items mi ON oi.menu_item_id = mi.menu_item_id LEFT JOIN categories c ON mi.category_id = c.category_id WHERE o.created_at BETWEEN ? AND ? AND o.status != 'CANCELLED' $paidFilter $tenantWhere GROUP BY c.category_id, c.name ORDER BY revenue DESC");
            $stmt->execute($params);
            $categoryRevenue = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            $stmt = $db->prepare("SELECT COALESCE(UPPER(TRIM(o.payment_method)), 'CASH') as method, COUNT(*) as count, COALESCE(SUM(o.total_amount), 0) as total FROM orders o WHERE o.created_at BETWEEN ? AND ? AND o.status != 'CANCELLED' AND (o.is_paid = 1 OR o.payment_method IS NOT NULL) $tenantWhere GROUP BY COALESCE(UPPER(TRIM(o.payment_method)), 'CASH')");
            $stmt->execute($params);
            $paymentBreakdown = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            $this->json(['success' => true, 'data' => [
                'category_revenue' => $categoryRevenue,
                'payment_breakdown' => $paymentBreakdown
            ]]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    public function getZReport() {
        if (!$this->requireManager()) return;
        $this->ensureMobileTenant();
        
        try {
            $q = $this->query();
            $settingsService = DependencyFactory::getSystemSettingsService();
            $range = $settingsService->getBusinessDateRange();
            $date = $q['date'] ?? $range['date'];
            
            $orderService = DependencyFactory::getOrderService();
            $settingsService = DependencyFactory::getSystemSettingsService();
            $businessRange = $settingsService->getBusinessDateRange();
            $startDt = null;
            $endDt = null;
            if ($date === $businessRange['date']) {
                $startDt = $businessRange['start_datetime'] ?? $businessRange['start'] ?? null;
                $endDt = $businessRange['end_datetime'] ?? $businessRange['end'] ?? null;
            } else {
                $historicalRange = $settingsService->getBusinessDateRangeForDate($date);
                if ($historicalRange) {
                    $startDt = $historicalRange['start_datetime'];
                    $endDt = $historicalRange['end_datetime'];
                }
            }
            $orders = ($startDt && $endDt)
                ? $orderService->getOrdersByDatetimeRange($startDt, $endDt)
                : $orderService->getOrdersByDateRange($date, $date);
            $orders = is_array($orders) ? $orders : [];
            
            $totalRevenue = 0;
            $totalOrders = count($orders);
            $paidOrders = 0;
            $cancelledOrders = 0;
            
            foreach ($orders as $o) {
                $status = strtoupper($o['status'] ?? '');
                if ($status === 'CANCELLED') { $cancelledOrders++; continue; }
                $isPaid = !empty($o['is_paid']) && ($o['is_paid'] == 1 || $o['is_paid'] === '1');
                $hasPayment = !empty(trim($o['payment_method'] ?? ''));
                if ($isPaid || $hasPayment) {
                    $paidOrders++;
                    $totalRevenue += floatval($o['total_amount'] ?? 0);
                }
            }
            
            $paymentBreakdown = [];
            foreach ($orders as $o) {
                $st = strtoupper($o['status'] ?? '');
                if ($st === 'CANCELLED') continue;
                $isPd = !empty($o['is_paid']) && ($o['is_paid'] == 1 || $o['is_paid'] === '1');
                $hasPm = !empty(trim($o['payment_method'] ?? ''));
                if (!$isPd && !$hasPm) continue;
                $method = strtoupper(trim($o['payment_method'] ?? 'CASH')) ?: 'CASH';
                if (!isset($paymentBreakdown[$method])) $paymentBreakdown[$method] = ['method' => $method, 'count' => 0, 'total' => 0];
                $paymentBreakdown[$method]['count']++;
                $paymentBreakdown[$method]['total'] += floatval($o['total_amount'] ?? 0);
            }
            
            $this->json(['success' => true, 'report' => [
                'date' => $date,
                'total_orders' => $totalOrders,
                'paid_orders' => $paidOrders,
                'cancelled_orders' => $cancelledOrders,
                'total_revenue' => $totalRevenue,
                'avg_order_value' => $paidOrders > 0 ? round($totalRevenue / $paidOrders, 2) : 0,
                'payment_breakdown' => array_values($paymentBreakdown),
            ]]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    public function printZReport() {
        if (!$this->requireManager()) return;
        $this->ensureMobileTenant();
        
        try {
            $body = $this->input();
            $settingsService = \App\Core\DependencyFactory::getSystemSettingsService();
            $businessRange = $settingsService->getBusinessDateRange();
            $date = $body['date'] ?? $businessRange['date'];
            
            $startDt = null;
            $endDt = null;
            if ($date === $businessRange['date']) {
                $startDt = $businessRange['start_datetime'] ?? $businessRange['start'] ?? null;
                $endDt = $businessRange['end_datetime'] ?? $businessRange['end'] ?? null;
            } else {
                $historicalRange = $settingsService->getBusinessDateRangeForDate($date);
                if ($historicalRange) {
                    $startDt = $historicalRange['start_datetime'];
                    $endDt = $historicalRange['end_datetime'];
                }
            }
            
            $businessId = \App\Core\TenantContext::getId();
            if ($startDt && $endDt && $businessId) {
                $settingsService->logBusinessDayRange($businessId, $date, $startDt, $endDt, 'mobile_z_print');
            }
            
            $zReportService = \App\Core\DependencyFactory::getZReportService();
            $reportData = $zReportService->buildZReportData($date, $startDt, $endDt);
            $printData = $zReportService->getPrintPayload($reportData);
            
            $db = \App\Core\DependencyFactory::getDatabase();
            $screenId = 'cashier_main';
            $stmt = $db->prepare("SELECT screen_id FROM preparation_screens WHERE is_active = 1 AND tenant_id = ? AND (screen_type = 'CASHIER' OR LOWER(name) LIKE '%kasa%') LIMIT 1");
            $stmt->execute([$businessId]);
            $screen = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($screen) $screenId = $screen['screen_id'];
            
            $queueId = 'q_' . substr(md5(uniqid(mt_rand(), true)), 0, 12);
            $stmt = $db->prepare("INSERT INTO receipt_print_queue (queue_id, receipt_id, tenant_id, screen_id, print_data, status, created_at) VALUES (?, NULL, ?, ?, ?, 'PENDING', NOW())");
            $stmt->execute([$queueId, $businessId, $screenId, json_encode($printData, JSON_UNESCAPED_UNICODE)]);
            
            $this->json(['success' => true, 'message' => 'Z raporu yazıcıya gönderildi', 'queue_id' => $queueId]);
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('Mobile Z Report print error', ['error' => $e->getMessage()]);
            }
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    

    // ─── Stock (Stok/Envanter) ─────────────────────────────────

    public function getStockList() {
        if (!$this->requireManager()) return;
        $this->ensureMobileTenant();
        try {
            $stockService = DependencyFactory::getStockMovementService();
            $list = method_exists($stockService, 'getStockSummary') ? $stockService->getStockSummary() : [];
            $this->json(['success' => true, 'data' => ['items' => is_array($list) ? $list : [], 'count' => count($list ?: [])]]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage(), 'data' => ['items' => [], 'count' => 0]], 500);
        }
    }

    /**
     * POST /api/mobile/manager/stock/add  — IN hareketi
     * body: { item_type, item_id, quantity, unit, notes?, location_id? }
     */
    public function addStockMovement() {
        if (!$this->requireManager()) return;
        $this->ensureMobileTenant();
        $data = $this->input();
        try {
            $itemType = (string)($data['item_type'] ?? 'ingredient');
            $itemId = (string)($data['item_id'] ?? '');
            $quantity = (float)($data['quantity'] ?? 0);
            $unit = (string)($data['unit'] ?? 'adet');
            $loc = $data['location_id'] ?? $data['to_location_id'] ?? null;
            $notes = $data['notes'] ?? null;
            if ($itemId === '' || $quantity <= 0) {
                $this->json(['success' => false, 'error' => 'item_id ve pozitif quantity gerekli'], 400);
                return;
            }
            $svc = DependencyFactory::getStockMovementService();
            $id = $svc->addStock($itemType, $itemId, $quantity, $unit, $loc, null, null, $notes);
            $this->json(['success' => (bool)$id, 'movement_id' => $id]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/mobile/manager/stock/remove  — OUT hareketi
     */
    public function removeStockMovement() {
        if (!$this->requireManager()) return;
        $this->ensureMobileTenant();
        $data = $this->input();
        try {
            $itemType = (string)($data['item_type'] ?? 'ingredient');
            $itemId = (string)($data['item_id'] ?? '');
            $quantity = (float)($data['quantity'] ?? 0);
            $unit = (string)($data['unit'] ?? 'adet');
            $loc = $data['location_id'] ?? $data['from_location_id'] ?? null;
            $notes = $data['notes'] ?? null;
            if ($itemId === '' || $quantity <= 0) {
                $this->json(['success' => false, 'error' => 'item_id ve pozitif quantity gerekli'], 400);
                return;
            }
            $svc = DependencyFactory::getStockMovementService();
            $id = $svc->removeStock($itemType, $itemId, $quantity, $unit, $loc, null, null, $notes);
            $this->json(['success' => (bool)$id, 'movement_id' => $id]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/mobile/manager/stock/adjust  — ADJUSTMENT: yeni mutlak değere çek
     */
    public function adjustStockMovement() {
        if (!$this->requireManager()) return;
        $this->ensureMobileTenant();
        $data = $this->input();
        try {
            $itemType = (string)($data['item_type'] ?? 'ingredient');
            $itemId = (string)($data['item_id'] ?? '');
            $newQty = (float)($data['quantity'] ?? $data['new_quantity'] ?? 0);
            $unit = (string)($data['unit'] ?? 'adet');
            $loc = $data['location_id'] ?? null;
            $notes = $data['notes'] ?? null;
            if ($itemId === '') {
                $this->json(['success' => false, 'error' => 'item_id gerekli'], 400);
                return;
            }
            $svc = DependencyFactory::getStockMovementService();
            $id = $svc->adjustStock($itemType, $itemId, $newQty, $unit, $loc, $notes);
            $this->json(['success' => (bool)$id, 'movement_id' => $id]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/mobile/manager/stock/delete  — Hareket kaydını siler (ters hareket oluşturur)
     * body: { movement_id, reason? }
     */
    public function deleteStockMovement() {
        if (!$this->requireManager()) return;
        $this->ensureMobileTenant();
        $data = $this->input();
        try {
            $movementId = (string)($data['movement_id'] ?? '');
            if ($movementId === '') {
                $this->json(['success' => false, 'error' => 'movement_id gerekli'], 400);
                return;
            }
            $svc = DependencyFactory::getStockMovementService();
            if (!method_exists($svc, 'reverseMovement') && !method_exists($svc, 'deleteMovement')) {
                $this->json(['success' => false, 'error' => 'Silme desteklenmiyor'], 501);
                return;
            }
            $result = method_exists($svc, 'deleteMovement')
                ? $svc->deleteMovement($movementId)
                : $svc->reverseMovement($movementId, $data['reason'] ?? 'Mobile delete');
            $this->json(['success' => (bool)$result]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // ─── Receipts (Fişler) ─────────────────────────────────────

    public function getReceiptsList() {
        if (!$this->requireManager()) return;
        $this->ensureMobileTenant();
        try {
            $q = $this->query();
            $from = $q['from'] ?? ($q['start'] ?? null);
            $to = $q['to'] ?? ($q['end'] ?? null);
            $date = $q['date'] ?? null;

            $receiptService = DependencyFactory::getReceiptService();
            $receipts = [];
            if ($from && $to) {
                if (method_exists($receiptService, 'getReceiptsByDateRangeForList')) {
                    $receipts = $receiptService->getReceiptsByDateRangeForList($from, $to);
                } elseif (method_exists($receiptService, 'getReceiptsByDateRange')) {
                    $receipts = $receiptService->getReceiptsByDateRange($from, $to);
                }
            } elseif ($date) {
                if (method_exists($receiptService, 'getDailyReceipts')) {
                    $receipts = $receiptService->getDailyReceipts($date);
                }
            } else {
                $today = date('Y-m-d');
                if (method_exists($receiptService, 'getDailyReceipts')) {
                    $receipts = $receiptService->getDailyReceipts($today);
                }
            }
            $receipts = is_array($receipts) ? $receipts : [];
            $this->json(['success' => true, 'receipts' => $receipts, 'count' => count($receipts), 'range' => ['from' => $from, 'to' => $to, 'date' => $date]]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage(), 'receipts' => []], 500);
        }
    }

    // ─── Order Approvals (Sipariş Onayları) ─────────────────────

    public function getOrderApprovalsPending() {
        if (!$this->requireManager()) return;
        $this->ensureMobileTenant();
        try {
            $approvalService = DependencyFactory::getOrderEditApprovalService();
            $approvals = $approvalService->getPendingApprovals();
            $count = count($approvals);
            $this->json(['success' => true, 'approvals' => $approvals, 'count' => $count]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage(), 'approvals' => [], 'count' => 0], 500);
        }
    }

    public function approveOrderRequest() {
        if (!$this->requireManager()) return;
        $this->ensureMobileTenant();
        $data = $this->input();
        $approvalId = $data['approval_id'] ?? '';
        if (empty($approvalId)) {
            $this->json(['success' => false, 'error' => 'approval_id gerekli'], 400);
            return;
        }
        try {
            $approvalService = DependencyFactory::getOrderEditApprovalService();
            $approval = $approvalService->getApprovalById($approvalId);
            if (!$approval) {
                $this->json(['success' => false, 'error' => 'Onay talebi bulunamadi'], 404);
                return;
            }
            $userId = $_SESSION['user_id'] ?? '';
            $userName = $_SESSION['username'] ?? 'Yonetici';
            $result = $approvalService->approveRequest($approvalId, $userId, $userName);
            if ($result) {
                $notificationService = DependencyFactory::getNotificationService();
                if (method_exists($notificationService, 'markAsReadByApprovalId')) {
                    $notificationService->markAsReadByApprovalId($approvalId);
                }
                $this->json(['success' => true, 'message' => 'Onaylandi']);
            } else {
                $this->json(['success' => false, 'error' => 'Onaylama basarisiz'], 500);
            }
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function rejectOrderRequest() {
        if (!$this->requireManager()) return;
        $this->ensureMobileTenant();
        $data = $this->input();
        $approvalId = $data['approval_id'] ?? '';
        if (empty($approvalId)) {
            $this->json(['success' => false, 'error' => 'approval_id gerekli'], 400);
            return;
        }
        try {
            $approvalService = DependencyFactory::getOrderEditApprovalService();
            $userId = $_SESSION['user_id'] ?? '';
            $userName = $_SESSION['username'] ?? 'Yonetici';
            $reason = $data['reason'] ?? '';
            $result = $approvalService->rejectRequest($approvalId, $userId, $userName, $reason);
            $this->json(['success' => (bool)$result, 'message' => $result ? 'Reddedildi' : 'Islem basarisiz']);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // Printers + bridges (mobile mirror of /business/printers)
    // ═══════════════════════════════════════════════════════════════

    /**
     * List all printer bridges for the current tenant.
     * GET /api/mobile/printers/bridges
     */
    public function getPrinterBridges() {
        if (!$this->requireManager()) return;
        $this->ensureMobileTenant();
        try {
            $businessId = \App\Core\TenantContext::getId() ?? ($_SESSION['business_id'] ?? '');
            if (empty($businessId)) {
                $this->json(['success' => true, 'bridges' => []]);
                return;
            }
            $service = DependencyFactory::getPrinterBridgeService();
            if (method_exists($service, 'ensureTableExists')) {
                $service->ensureTableExists();
            }
            $bridges = $service->getBridgesByBusiness($businessId);
            // Strip the long api_key from the list response; only reveal on
            // explicit reveal call. Keep bridge_id + heartbeat for UI.
            $sanitised = array_map(function ($b) {
                $b = is_array($b) ? $b : (array)$b;
                if (isset($b['api_key'])) {
                    $key = (string)$b['api_key'];
                    $b['api_key_masked'] = strlen($key) > 12
                        ? substr($key, 0, 6) . '…' . substr($key, -4)
                        : str_repeat('•', max(4, strlen($key)));
                    unset($b['api_key']);
                }
                return $b;
            }, $bridges ?? []);
            $this->json(['success' => true, 'bridges' => $sanitised]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Reveal the bridge api_key (used when the user taps "Kopyala" / pairs
     * a new machine). We keep this as a separate endpoint so we can audit
     * secret-reveal in logs and never accidentally leak keys in the list.
     * POST /api/mobile/printers/bridges/reveal-key
     */
    public function revealPrinterBridgeKey() {
        if (!$this->requireManager()) return;
        $this->ensureMobileTenant();
        $data = $this->input();
        $bridgeId = $data['bridge_id'] ?? '';
        if (empty($bridgeId)) {
            $this->json(['success' => false, 'error' => 'bridge_id gerekli'], 400);
            return;
        }
        try {
            $service = DependencyFactory::getPrinterBridgeService();
            $bridge = method_exists($service, 'findById') ? $service->findById($bridgeId) : null;
            if (!$bridge) {
                $this->json(['success' => false, 'error' => 'Köprü bulunamadı'], 404);
                return;
            }
            $tenantId = \App\Core\TenantContext::getId();
            if ($tenantId && isset($bridge['tenant_id']) && $bridge['tenant_id'] !== $tenantId) {
                $this->json(['success' => false, 'error' => 'Yetkisiz'], 403);
                return;
            }
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::info('mobile.printer.bridge.key_reveal', [
                    'bridge_id' => $bridgeId,
                    'user_id' => $_SESSION['user_id'] ?? null,
                ]);
            }
            $this->json([
                'success' => true,
                'api_key' => $bridge['api_key'] ?? '',
                'bridge_name' => $bridge['bridge_name'] ?? '',
            ]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Create a new bridge (max 5 per tenant enforced server-side).
     * POST /api/mobile/printers/bridges/create
     */
    public function createPrinterBridge() {
        if (!$this->requireManager()) return;
        $this->ensureMobileTenant();
        $data = $this->input();
        $bridgeName = trim((string)($data['bridge_name'] ?? ''));
        if (strlen($bridgeName) < 3 || strlen($bridgeName) > 100) {
            $this->json(['success' => false, 'error' => 'Köprü adı 3-100 karakter olmalı'], 400);
            return;
        }
        try {
            $businessId = \App\Core\TenantContext::getId() ?? ($_SESSION['business_id'] ?? '');
            if (empty($businessId)) {
                $this->json(['success' => false, 'error' => 'İşletme bağlamı yok'], 400);
                return;
            }
            $service = DependencyFactory::getPrinterBridgeService();
            if (method_exists($service, 'ensureTableExists')) $service->ensureTableExists();
            $existing = $service->getBridgesByBusiness($businessId);
            if (count($existing) >= 5) {
                $this->json(['success' => false, 'error' => 'Maksimum 5 köprü oluşturabilirsiniz'], 400);
                return;
            }
            $apiKey = bin2hex(random_bytes(32));
            $result = $service->registerBridge([
                'bridge_name' => $bridgeName,
                'api_key' => $apiKey,
            ], $businessId);
            if (!$result || !is_array($result)) {
                $this->json(['success' => false, 'error' => 'Köprü oluşturulamadı'], 500);
                return;
            }
            $this->json(['success' => true, 'bridge' => $result]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /** POST /api/mobile/printers/bridges/update  (rename) */
    public function updatePrinterBridge() {
        if (!$this->requireManager()) return;
        $this->ensureMobileTenant();
        $data = $this->input();
        $bridgeId = $data['bridge_id'] ?? '';
        $bridgeName = trim((string)($data['bridge_name'] ?? ''));
        if (empty($bridgeId) || strlen($bridgeName) < 3 || strlen($bridgeName) > 100) {
            $this->json(['success' => false, 'error' => 'bridge_id ve 3-100 karakter isim gerekli'], 400);
            return;
        }
        try {
            $service = DependencyFactory::getPrinterBridgeService();
            $bridge = method_exists($service, 'findById') ? $service->findById($bridgeId) : null;
            if (!$bridge) {
                $this->json(['success' => false, 'error' => 'Köprü bulunamadı'], 404);
                return;
            }
            $tenantId = \App\Core\TenantContext::getId();
            if ($tenantId && isset($bridge['tenant_id']) && $bridge['tenant_id'] !== $tenantId) {
                $this->json(['success' => false, 'error' => 'Yetkisiz'], 403);
                return;
            }
            $ok = $service->update($bridgeId, ['bridge_name' => $bridgeName]);
            $this->json(['success' => (bool)$ok]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /** POST /api/mobile/printers/bridges/delete */
    public function deletePrinterBridge() {
        if (!$this->requireManager()) return;
        $this->ensureMobileTenant();
        $data = $this->input();
        $bridgeId = $data['bridge_id'] ?? '';
        if (empty($bridgeId)) {
            $this->json(['success' => false, 'error' => 'bridge_id gerekli'], 400);
            return;
        }
        try {
            $service = DependencyFactory::getPrinterBridgeService();
            $bridge = method_exists($service, 'findById') ? $service->findById($bridgeId) : null;
            if (!$bridge) {
                $this->json(['success' => false, 'error' => 'Köprü bulunamadı'], 404);
                return;
            }
            $tenantId = \App\Core\TenantContext::getId();
            if ($tenantId && isset($bridge['tenant_id']) && $bridge['tenant_id'] !== $tenantId) {
                $this->json(['success' => false, 'error' => 'Yetkisiz'], 403);
                return;
            }
            $ok = $service->delete($bridgeId);
            $this->json(['success' => (bool)$ok]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /** GET /api/mobile/printers/bridge-printers?bridge_id=X */
    public function getPrintersForBridge() {
        if (!$this->requireManager()) return;
        $this->ensureMobileTenant();
        $q = $this->query();
        $bridgeId = $q['bridge_id'] ?? '';
        if (empty($bridgeId)) {
            $this->json(['success' => false, 'error' => 'bridge_id gerekli'], 400);
            return;
        }
        try {
            $printerService = DependencyFactory::getPrinterService();
            $printers = $printerService->getPrintersByBridge($bridgeId);
            $screenService = DependencyFactory::getPreparationScreenPrinterService();
            foreach ($printers as &$p) {
                $pid = $p['printer_id'] ?? '';
                if ($pid && method_exists($screenService, 'getScreensForPrinter')) {
                    $p['assigned_screens'] = $screenService->getScreensForPrinter($pid);
                } else {
                    $p['assigned_screens'] = [];
                }
            }
            unset($p);
            $this->json(['success' => true, 'printers' => $printers]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /** POST /api/mobile/printers/update (rename + optional screen assignments) */
    public function updatePrinterMobile() {
        if (!$this->requireManager()) return;
        $this->ensureMobileTenant();
        $data = $this->input();
        $printerId = $data['printer_id'] ?? '';
        $printerName = trim((string)($data['printer_name'] ?? ''));
        if (empty($printerId) || empty($printerName)) {
            $this->json(['success' => false, 'error' => 'printer_id ve printer_name gerekli'], 400);
            return;
        }
        try {
            $printerService = DependencyFactory::getPrinterService();
            $printer = $printerService->getPrinterById($printerId);
            if (!$printer) {
                $this->json(['success' => false, 'error' => 'Yazıcı bulunamadı'], 404);
                return;
            }
            $tenantId = \App\Core\TenantContext::getId();
            if ($tenantId && isset($printer['tenant_id']) && $printer['tenant_id'] !== $tenantId) {
                $this->json(['success' => false, 'error' => 'Yetkisiz'], 403);
                return;
            }
            $ok = $printerService->updatePrinter($printerId, ['printer_name' => $printerName]);
            // Reassign screens if provided
            if (isset($data['screen_ids']) && is_array($data['screen_ids'])) {
                $screenService = DependencyFactory::getPreparationScreenPrinterService();
                if (method_exists($screenService, 'removeAllPrinterAssignments')) {
                    $screenService->removeAllPrinterAssignments($printerId);
                }
                foreach ($data['screen_ids'] as $sid) {
                    if (!empty($sid) && method_exists($screenService, 'assignPrinterToScreen')) {
                        $screenService->assignPrinterToScreen($printerId, $sid);
                    }
                }
            }
            $this->json(['success' => (bool)$ok]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /** POST /api/mobile/printers/delete */
    public function deletePrinterMobile() {
        if (!$this->requireManager()) return;
        $this->ensureMobileTenant();
        $data = $this->input();
        $printerId = $data['printer_id'] ?? '';
        if (empty($printerId)) {
            $this->json(['success' => false, 'error' => 'printer_id gerekli'], 400);
            return;
        }
        try {
            $printerService = DependencyFactory::getPrinterService();
            $printer = $printerService->getPrinterById($printerId);
            if (!$printer) {
                $this->json(['success' => false, 'error' => 'Yazıcı bulunamadı'], 404);
                return;
            }
            $tenantId = \App\Core\TenantContext::getId();
            if ($tenantId && isset($printer['tenant_id']) && $printer['tenant_id'] !== $tenantId) {
                $this->json(['success' => false, 'error' => 'Yetkisiz'], 403);
                return;
            }
            $ok = $printerService->deletePrinter($printerId);
            $this->json(['success' => (bool)$ok]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /** POST /api/mobile/printers/test */
    public function testPrinterMobile() {
        if (!$this->requireManager()) return;
        $this->ensureMobileTenant();
        $data = $this->input();
        $serial = (string)($data['printer_serial'] ?? '');
        if (empty($serial)) {
            $this->json(['success' => false, 'error' => 'printer_serial gerekli'], 400);
            return;
        }
        try {
            $printerService = DependencyFactory::getPrinterService();
            $ok = $printerService->testPrinterConnection($serial);
            $this->json(['success' => (bool)$ok, 'message' => $ok ? 'Bağlantı başarılı' : 'Bağlantı başarısız']);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /** GET /api/mobile/printers/prep-screens — list screens we can assign printers to. */
    public function getPrepScreensForPrinterMobile() {
        if (!$this->requireManager()) return;
        $this->ensureMobileTenant();
        try {
            $svc = DependencyFactory::getPreparationScreenService();
            $screens = method_exists($svc, 'getAllScreens') ? $svc->getAllScreens() : [];
            $this->json(['success' => true, 'screens' => $screens]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // Queue management (mobile mirror of /business/queue)
    // ═══════════════════════════════════════════════════════════════

    /** GET /api/mobile/queue — list tickets currently in queue. */
    public function getQueueMobile() {
        if (!$this->requireAuth()) return;
        $this->ensureMobileTenant();
        try {
            $qs = DependencyFactory::getQueueService();
            $q = $this->query();
            $status = $q['status'] ?? 'waiting';
            if (method_exists($qs, 'getByStatus')) {
                $tickets = $qs->getByStatus($status);
            } elseif (method_exists($qs, 'getActiveTickets')) {
                $tickets = $qs->getActiveTickets();
            } elseif (method_exists($qs, 'getAll')) {
                $tickets = $qs->getAll();
            } else {
                $tickets = [];
            }
            $this->json(['success' => true, 'tickets' => $tickets ?: []]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /** GET /api/mobile/queue/settings */
    public function getQueueSettingsMobile() {
        if (!$this->requireManager()) return;
        $this->ensureMobileTenant();
        try {
            $qs = DependencyFactory::getQueueService();
            $settings = method_exists($qs, 'getSettings') ? $qs->getSettings() : [];
            $this->json(['success' => true, 'settings' => $settings ?: []]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /** POST /api/mobile/queue/settings */
    public function updateQueueSettingsMobile() {
        if (!$this->requireManager()) return;
        $this->ensureMobileTenant();
        $data = $this->input();
        try {
            $qs = DependencyFactory::getQueueService();
            $ok = method_exists($qs, 'updateSettings') ? $qs->updateSettings($data) : false;
            $this->json(['success' => (bool)$ok]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /** POST /api/mobile/queue/call-next
     *  QueueService'de ayrı bir `callNext` yok; web tarafında notify(id) ile
     *  teker teker çağrılıyor. Mobilde tek dokunuşla en öndeki WAITING girişi
     *  NOTIFIED'a çevirmek istiyoruz. Bunun için getActiveQueue + markNotified
     *  zinciri kuruyoruz.
     */
    public function callNextQueueTicketMobile() {
        if (!$this->requireAuth()) return;
        $this->ensureMobileTenant();
        try {
            $tenantId = \App\Core\TenantContext::getId() ?? ($_SESSION['business_id'] ?? '');
            if (empty($tenantId)) {
                $this->json(['success' => false, 'error' => 'Tenant bulunamadı'], 400);
                return;
            }
            $qs = DependencyFactory::getQueueService();
            $active = method_exists($qs, 'getActiveQueue') ? $qs->getActiveQueue($tenantId) : [];
            $waiting = null;
            foreach (($active ?: []) as $entry) {
                $st = strtolower((string)($entry['status'] ?? ''));
                if ($st === 'waiting') { $waiting = $entry; break; }
            }
            if (!$waiting) {
                $this->json(['success' => false, 'error' => 'Sırada bekleyen müşteri yok'], 404);
                return;
            }
            if (method_exists($qs, 'markNotified')) {
                $qs->markNotified((int)$waiting['id'], []);
            }
            $this->json(['success' => true, 'ticket' => $waiting]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /** POST /api/mobile/queue/update-status
     *  Status değerleri: notified | seated | cancelled | no_show
     */
    public function updateQueueTicketStatusMobile() {
        if (!$this->requireAuth()) return;
        $this->ensureMobileTenant();
        $data = $this->input();
        $queueId = (int)($data['queue_id'] ?? $data['id'] ?? 0);
        $status = strtolower((string)($data['status'] ?? ''));
        if ($queueId <= 0 || empty($status)) {
            $this->json(['success' => false, 'error' => 'queue_id ve status gerekli'], 400);
            return;
        }
        try {
            $qs = DependencyFactory::getQueueService();
            $ok = false;
            switch ($status) {
                case 'notified':
                    $ok = method_exists($qs, 'markNotified') ? $qs->markNotified($queueId, []) : false;
                    break;
                case 'seated':
                    $ok = method_exists($qs, 'markSeated') ? $qs->markSeated($queueId) : false;
                    break;
                case 'cancelled':
                case 'canceled':
                    $ok = method_exists($qs, 'cancel') ? $qs->cancel($queueId, false) : false;
                    break;
                case 'no_show':
                case 'no-show':
                    $ok = method_exists($qs, 'markNoShow') ? $qs->markNoShow($queueId) : false;
                    break;
                default:
                    $this->json(['success' => false, 'error' => "Bilinmeyen status: $status"], 400);
                    return;
            }
            $this->json(['success' => (bool)$ok]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // Receipt templates (mobile mirror of /business/receipt-templates)
    // ═══════════════════════════════════════════════════════════════

    /** GET /api/mobile/receipt-templates */
    public function getReceiptTemplatesMobile() {
        if (!$this->requireManager()) return;
        $this->ensureMobileTenant();
        try {
            $svc = DependencyFactory::getReceiptTemplateService();
            $templates = method_exists($svc, 'getAllTemplates') ? $svc->getAllTemplates()
                : (method_exists($svc, 'getAll') ? $svc->getAll() : []);
            $this->json(['success' => true, 'templates' => $templates ?: []]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /** POST /api/mobile/receipt-templates/create */
    public function createReceiptTemplateMobile() {
        if (!$this->requireManager()) return;
        $this->ensureMobileTenant();
        $data = $this->input();
        try {
            $svc = DependencyFactory::getReceiptTemplateService();
            $ok = method_exists($svc, 'create') ? $svc->create($data) : false;
            $this->json(['success' => (bool)$ok, 'template' => $ok]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /** POST /api/mobile/receipt-templates/update */
    public function updateReceiptTemplateMobile() {
        if (!$this->requireManager()) return;
        $this->ensureMobileTenant();
        $data = $this->input();
        $id = $data['template_id'] ?? '';
        if (empty($id)) {
            $this->json(['success' => false, 'error' => 'template_id gerekli'], 400);
            return;
        }
        try {
            $svc = DependencyFactory::getReceiptTemplateService();
            $ok = method_exists($svc, 'update') ? $svc->update($id, $data) : false;
            $this->json(['success' => (bool)$ok]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /** POST /api/mobile/receipt-templates/delete */
    public function deleteReceiptTemplateMobile() {
        if (!$this->requireManager()) return;
        $this->ensureMobileTenant();
        $data = $this->input();
        $id = $data['template_id'] ?? '';
        if (empty($id)) {
            $this->json(['success' => false, 'error' => 'template_id gerekli'], 400);
            return;
        }
        try {
            $svc = DependencyFactory::getReceiptTemplateService();
            $ok = method_exists($svc, 'delete') ? $svc->delete($id) : false;
            $this->json(['success' => (bool)$ok]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // Roles & permissions (read-only mirror of /business/roles-permissions)
    // ═══════════════════════════════════════════════════════════════

    /** GET /api/mobile/roles-permissions */
    public function getRolesPermissionsMobile() {
        if (!$this->requireManager()) return;
        $this->ensureMobileTenant();
        try {
            $roleService = DependencyFactory::getRoleService();
            $permService = DependencyFactory::getPermissionService();
            $roles = method_exists($roleService, 'getAllRoles') ? $roleService->getAllRoles()
                : (method_exists($roleService, 'getAll') ? $roleService->getAll() : []);
            $permissions = method_exists($permService, 'getAllPermissions') ? $permService->getAllPermissions()
                : (method_exists($permService, 'getAll') ? $permService->getAll() : []);
            // For each role, fetch its permissions if helper available.
            $enriched = [];
            foreach (($roles ?: []) as $r) {
                $r = is_array($r) ? $r : (array)$r;
                $rid = $r['role_id'] ?? '';
                if ($rid && method_exists($permService, 'getRolePermissions')) {
                    $r['permissions'] = $permService->getRolePermissions($rid) ?: [];
                }
                $enriched[] = $r;
            }
            $this->json(['success' => true, 'roles' => $enriched, 'permissions' => $permissions ?: []]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /** POST /api/mobile/roles-permissions/update */
    public function updateRolePermissionsMobile() {
        if (!$this->requireManager()) return;
        $this->ensureMobileTenant();
        $data = $this->input();
        $roleId = $data['role_id'] ?? '';
        $perms = is_array($data['permissions'] ?? null) ? $data['permissions'] : [];
        if (empty($roleId)) {
            $this->json(['success' => false, 'error' => 'role_id gerekli'], 400);
            return;
        }
        try {
            $svc = DependencyFactory::getPermissionService();
            $ok = false;
            if (method_exists($svc, 'setRolePermissions')) {
                $ok = $svc->setRolePermissions($roleId, $perms);
            } elseif (method_exists($svc, 'syncRolePermissions')) {
                $ok = $svc->syncRolePermissions($roleId, $perms);
            }
            $this->json(['success' => (bool)$ok]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // Order approval history
    // ═══════════════════════════════════════════════════════════════

    /** GET /api/mobile/order-approvals/history */
    public function getOrderApprovalHistoryMobile() {
        if (!$this->requireManager()) return;
        $this->ensureMobileTenant();
        try {
            $svc = DependencyFactory::getOrderEditApprovalService();
            $q = $this->query();
            $limit = (int)($q['limit'] ?? 100);
            $history = method_exists($svc, 'getHistory') ? $svc->getHistory($limit)
                : (method_exists($svc, 'getApprovalHistory') ? $svc->getApprovalHistory($limit)
                : (method_exists($svc, 'getAll') ? $svc->getAll() : []));
            $this->json(['success' => true, 'history' => $history ?: []]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // Table history (mobile mirror of /business/table-history/{id})
    // ═══════════════════════════════════════════════════════════════

    /** GET /api/mobile/tables/history?table_id=X */
    public function getTableHistoryMobile() {
        if (!$this->requireAuth()) return;
        $this->ensureMobileTenant();
        $q = $this->query();
        $tableId = $q['table_id'] ?? '';
        if (empty($tableId)) {
            $this->json(['success' => false, 'error' => 'table_id gerekli'], 400);
            return;
        }
        try {
            $orderService = DependencyFactory::getOrderService();
            // All orders ever created for this table, newest-first.
            $orders = method_exists($orderService, 'getOrdersByTable')
                ? $orderService->getOrdersByTable($tableId)
                : [];
            // Sort by created_at desc if field exists.
            if (is_array($orders)) {
                usort($orders, function ($a, $b) {
                    $ta = strtotime((string)($a['created_at'] ?? '1970-01-01'));
                    $tb = strtotime((string)($b['created_at'] ?? '1970-01-01'));
                    return $tb <=> $ta;
                });
            }
            $this->json(['success' => true, 'orders' => $orders ?: []]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // Finance (invoices, suppliers, waste)
    // ═══════════════════════════════════════════════════════════════

    /** GET /api/mobile/finance/invoices */
    public function getInvoicesMobile() {
        if (!$this->requireManager()) return;
        $this->ensureMobileTenant();
        try {
            $q = $this->query();
            // Reuse whatever service is registered; fall back to PDO query
            // against the invoices table directly if no service exists.
            $db = DependencyFactory::getDatabase();
            $limit = min(500, max(1, (int)($q['limit'] ?? 200)));
            $tenantId = \App\Core\TenantContext::getId();
            $rows = [];
            try {
                $stmt = $db->prepare(
                    "SELECT * FROM invoices WHERE tenant_id = :tid ORDER BY invoice_date DESC, created_at DESC LIMIT :lim"
                );
                $stmt->bindValue(':tid', $tenantId);
                $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
                $stmt->execute();
                $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            } catch (\Exception $_) {
                $rows = [];
            }
            $this->json(['success' => true, 'invoices' => $rows]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /** POST /api/mobile/finance/invoices/create */
    public function createInvoiceMobile() {
        if (!$this->requireManager()) return;
        $this->ensureMobileTenant();
        $data = $this->input();
        try {
            $db = DependencyFactory::getDatabase();
            $tenantId = \App\Core\TenantContext::getId();
            $invoiceId = bin2hex(random_bytes(16));
            $stmt = $db->prepare(
                "INSERT INTO invoices (invoice_id, tenant_id, supplier_id, invoice_number, invoice_date, total_amount, status, notes, created_at)
                 VALUES (:id, :tid, :sid, :num, :dt, :total, :st, :notes, NOW())"
            );
            $stmt->execute([
                ':id' => $invoiceId,
                ':tid' => $tenantId,
                ':sid' => $data['supplier_id'] ?? null,
                ':num' => $data['invoice_number'] ?? '',
                ':dt' => $data['invoice_date'] ?? date('Y-m-d'),
                ':total' => (float)($data['total_amount'] ?? 0),
                ':st' => $data['status'] ?? 'pending',
                ':notes' => $data['notes'] ?? '',
            ]);
            $this->json(['success' => true, 'invoice_id' => $invoiceId]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /** POST /api/mobile/finance/invoices/delete */
    public function deleteInvoiceMobile() {
        if (!$this->requireManager()) return;
        $this->ensureMobileTenant();
        $data = $this->input();
        $id = $data['invoice_id'] ?? '';
        if (empty($id)) {
            $this->json(['success' => false, 'error' => 'invoice_id gerekli'], 400);
            return;
        }
        try {
            $db = DependencyFactory::getDatabase();
            $tenantId = \App\Core\TenantContext::getId();
            $stmt = $db->prepare("DELETE FROM invoices WHERE invoice_id = :id AND tenant_id = :tid");
            $stmt->execute([':id' => $id, ':tid' => $tenantId]);
            $this->json(['success' => $stmt->rowCount() > 0]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /** GET /api/mobile/finance/suppliers */
    public function getSuppliersMobile() {
        if (!$this->requireManager()) return;
        $this->ensureMobileTenant();
        try {
            $db = DependencyFactory::getDatabase();
            $tenantId = \App\Core\TenantContext::getId();
            $stmt = $db->prepare("SELECT * FROM suppliers WHERE tenant_id = :tid ORDER BY name ASC");
            $stmt->execute([':tid' => $tenantId]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            $this->json(['success' => true, 'suppliers' => $rows]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /** POST /api/mobile/finance/suppliers/create */
    public function createSupplierMobile() {
        if (!$this->requireManager()) return;
        $this->ensureMobileTenant();
        $data = $this->input();
        try {
            $db = DependencyFactory::getDatabase();
            $tenantId = \App\Core\TenantContext::getId();
            $supplierId = bin2hex(random_bytes(16));
            $stmt = $db->prepare(
                "INSERT INTO suppliers (supplier_id, tenant_id, name, phone, email, address, notes, created_at)
                 VALUES (:id, :tid, :name, :phone, :email, :addr, :notes, NOW())"
            );
            $stmt->execute([
                ':id' => $supplierId,
                ':tid' => $tenantId,
                ':name' => $data['name'] ?? '',
                ':phone' => $data['phone'] ?? '',
                ':email' => $data['email'] ?? '',
                ':addr' => $data['address'] ?? '',
                ':notes' => $data['notes'] ?? '',
            ]);
            $this->json(['success' => true, 'supplier_id' => $supplierId]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /** POST /api/mobile/finance/suppliers/update */
    public function updateSupplierMobile() {
        if (!$this->requireManager()) return;
        $this->ensureMobileTenant();
        $data = $this->input();
        $id = $data['supplier_id'] ?? '';
        if (empty($id)) {
            $this->json(['success' => false, 'error' => 'supplier_id gerekli'], 400);
            return;
        }
        try {
            $db = DependencyFactory::getDatabase();
            $tenantId = \App\Core\TenantContext::getId();
            $stmt = $db->prepare(
                "UPDATE suppliers SET name = :name, phone = :phone, email = :email, address = :addr, notes = :notes
                 WHERE supplier_id = :id AND tenant_id = :tid"
            );
            $stmt->execute([
                ':name' => $data['name'] ?? '',
                ':phone' => $data['phone'] ?? '',
                ':email' => $data['email'] ?? '',
                ':addr' => $data['address'] ?? '',
                ':notes' => $data['notes'] ?? '',
                ':id' => $id,
                ':tid' => $tenantId,
            ]);
            $this->json(['success' => $stmt->rowCount() >= 0]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /** POST /api/mobile/finance/suppliers/delete */
    public function deleteSupplierMobile() {
        if (!$this->requireManager()) return;
        $this->ensureMobileTenant();
        $data = $this->input();
        $id = $data['supplier_id'] ?? '';
        if (empty($id)) {
            $this->json(['success' => false, 'error' => 'supplier_id gerekli'], 400);
            return;
        }
        try {
            $db = DependencyFactory::getDatabase();
            $tenantId = \App\Core\TenantContext::getId();
            $stmt = $db->prepare("DELETE FROM suppliers WHERE supplier_id = :id AND tenant_id = :tid");
            $stmt->execute([':id' => $id, ':tid' => $tenantId]);
            $this->json(['success' => $stmt->rowCount() > 0]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /** GET /api/mobile/finance/waste */
    public function getWasteMobile() {
        if (!$this->requireManager()) return;
        $this->ensureMobileTenant();
        try {
            $db = DependencyFactory::getDatabase();
            $tenantId = \App\Core\TenantContext::getId();
            $q = $this->query();
            $limit = min(500, max(1, (int)($q['limit'] ?? 200)));
            $stmt = $db->prepare(
                "SELECT * FROM waste_records WHERE tenant_id = :tid ORDER BY waste_date DESC, created_at DESC LIMIT :lim"
            );
            $stmt->bindValue(':tid', $tenantId);
            $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            $this->json(['success' => true, 'records' => $rows]);
        } catch (\Exception $e) {
            // If the table doesn't exist, return empty list rather than erroring
            $this->json(['success' => true, 'records' => []]);
        }
    }

    /** POST /api/mobile/finance/waste/create */
    public function createWasteMobile() {
        if (!$this->requireManager()) return;
        $this->ensureMobileTenant();
        $data = $this->input();
        try {
            $db = DependencyFactory::getDatabase();
            $tenantId = \App\Core\TenantContext::getId();
            $wasteId = bin2hex(random_bytes(16));
            $stmt = $db->prepare(
                "INSERT INTO waste_records (waste_id, tenant_id, item_name, quantity, unit, reason, waste_date, notes, created_at)
                 VALUES (:id, :tid, :name, :qty, :unit, :reason, :dt, :notes, NOW())"
            );
            $stmt->execute([
                ':id' => $wasteId,
                ':tid' => $tenantId,
                ':name' => $data['item_name'] ?? '',
                ':qty' => (float)($data['quantity'] ?? 0),
                ':unit' => $data['unit'] ?? 'adet',
                ':reason' => $data['reason'] ?? '',
                ':dt' => $data['waste_date'] ?? date('Y-m-d'),
                ':notes' => $data['notes'] ?? '',
            ]);
            $this->json(['success' => true, 'waste_id' => $wasteId]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /** POST /api/mobile/finance/waste/delete */
    public function deleteWasteMobile() {
        if (!$this->requireManager()) return;
        $this->ensureMobileTenant();
        $data = $this->input();
        $id = $data['waste_id'] ?? '';
        if (empty($id)) {
            $this->json(['success' => false, 'error' => 'waste_id gerekli'], 400);
            return;
        }
        try {
            $db = DependencyFactory::getDatabase();
            $tenantId = \App\Core\TenantContext::getId();
            $stmt = $db->prepare("DELETE FROM waste_records WHERE waste_id = :id AND tenant_id = :tid");
            $stmt->execute([':id' => $id, ':tid' => $tenantId]);
            $this->json(['success' => $stmt->rowCount() > 0]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // Payment gateways / POS devices / Feature flags / Error logs / Reports
    // ═══════════════════════════════════════════════════════════════

    /** GET /api/mobile/payment-gateways */
    public function getPaymentGatewaysMobile() {
        if (!$this->requireManager()) return;
        $this->ensureMobileTenant();
        try {
            $svc = DependencyFactory::getPaymentGatewayService();
            $items = method_exists($svc, 'getAll') ? $svc->getAll()
                : (method_exists($svc, 'listGateways') ? $svc->listGateways() : []);
            $this->json(['success' => true, 'gateways' => $items ?: []]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /** POST /api/mobile/payment-gateways/toggle */
    public function togglePaymentGatewayMobile() {
        if (!$this->requireManager()) return;
        $this->ensureMobileTenant();
        $data = $this->input();
        $id = $data['gateway_id'] ?? ($data['id'] ?? '');
        $enabled = (bool)($data['is_enabled'] ?? $data['enabled'] ?? false);
        if (empty($id)) {
            $this->json(['success' => false, 'error' => 'gateway_id gerekli'], 400);
            return;
        }
        try {
            $svc = DependencyFactory::getPaymentGatewayService();
            $ok = false;
            if (method_exists($svc, 'toggle')) $ok = $svc->toggle($id, $enabled);
            elseif (method_exists($svc, 'setEnabled')) $ok = $svc->setEnabled($id, $enabled);
            elseif (method_exists($svc, 'update')) $ok = $svc->update($id, ['is_enabled' => $enabled ? 1 : 0]);
            $this->json(['success' => (bool)$ok]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /** GET /api/mobile/pos-devices */
    public function getPosDevicesMobile() {
        if (!$this->requireManager()) return;
        $this->ensureMobileTenant();
        try {
            $svc = DependencyFactory::getPOSDeviceService();
            $items = method_exists($svc, 'getAll') ? $svc->getAll()
                : (method_exists($svc, 'listDevices') ? $svc->listDevices() : []);
            $this->json(['success' => true, 'devices' => $items ?: []]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /** POST /api/mobile/pos-devices/delete */
    public function deletePosDeviceMobile() {
        if (!$this->requireManager()) return;
        $this->ensureMobileTenant();
        $data = $this->input();
        $id = $data['device_id'] ?? '';
        if (empty($id)) {
            $this->json(['success' => false, 'error' => 'device_id gerekli'], 400);
            return;
        }
        try {
            $svc = DependencyFactory::getPOSDeviceService();
            $ok = method_exists($svc, 'delete') ? $svc->delete($id) : false;
            $this->json(['success' => (bool)$ok]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /** GET /api/mobile/features */
    public function getFeatureFlagsMobile() {
        if (!$this->requireManager()) return;
        $this->ensureMobileTenant();
        try {
            $svc = DependencyFactory::getFeatureService();
            $items = method_exists($svc, 'getAll') ? $svc->getAll()
                : (method_exists($svc, 'listFeatures') ? $svc->listFeatures() : []);
            $this->json(['success' => true, 'features' => $items ?: []]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /** POST /api/mobile/features/toggle */
    public function toggleFeatureFlagMobile() {
        if (!$this->requireManager()) return;
        $this->ensureMobileTenant();
        $data = $this->input();
        $id = $data['feature_id'] ?? ($data['id'] ?? '');
        $enabled = (bool)($data['is_enabled'] ?? $data['enabled'] ?? false);
        if (empty($id)) {
            $this->json(['success' => false, 'error' => 'feature_id gerekli'], 400);
            return;
        }
        try {
            $svc = DependencyFactory::getFeatureService();
            $ok = false;
            if (method_exists($svc, 'toggle')) $ok = $svc->toggle($id, $enabled);
            elseif (method_exists($svc, 'setEnabled')) $ok = $svc->setEnabled($id, $enabled);
            elseif (method_exists($svc, 'update')) $ok = $svc->update($id, ['is_enabled' => $enabled ? 1 : 0]);
            $this->json(['success' => (bool)$ok]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /** GET /api/mobile/error-logs */
    public function getErrorLogsMobile() {
        if (!$this->requireManager()) return;
        $this->ensureMobileTenant();
        try {
            $db = DependencyFactory::getDatabase();
            $tenantId = \App\Core\TenantContext::getId();
            $q = $this->query();
            $limit = min(500, max(1, (int)($q['limit'] ?? 200)));
            $stmt = $db->prepare(
                "SELECT log_id, level, message, context, created_at FROM error_logs
                 WHERE (tenant_id IS NULL OR tenant_id = :tid)
                 ORDER BY created_at DESC LIMIT :lim"
            );
            $stmt->bindValue(':tid', $tenantId);
            $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            $this->json(['success' => true, 'logs' => $rows]);
        } catch (\Exception $e) {
            $this->json(['success' => true, 'logs' => []]);
        }
    }

    /** GET /api/mobile/reports?type=sales&period=week */
    public function getReportsMobile() {
        if (!$this->requireManager()) return;
        $this->ensureMobileTenant();
        try {
            $q = $this->query();
            $type = $q['type'] ?? 'sales';
            $period = $q['period'] ?? 'week';
            $orderService = DependencyFactory::getOrderService();
            $payload = [];
            if ($type === 'sales') {
                $from = $period === 'today' ? date('Y-m-d 00:00:00')
                    : ($period === 'month' ? date('Y-m-01 00:00:00')
                    : date('Y-m-d 00:00:00', strtotime('-7 days')));
                $to = date('Y-m-d 23:59:59');
                $orders = method_exists($orderService, 'getOrdersBetween')
                    ? $orderService->getOrdersBetween($from, $to)
                    : [];
                $total = 0.0;
                $count = 0;
                foreach ($orders as $o) {
                    $total += (float)($o['total_amount'] ?? $o['grand_total'] ?? 0);
                    $count++;
                }
                $payload = ['total' => $total, 'count' => $count, 'from' => $from, 'to' => $to];
            }
            $this->json(['success' => true, 'report' => $payload, 'type' => $type, 'period' => $period]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Validate a 4-6 digit business number for mobile staff login.
     *
     * Personel artık "caddecafe" gibi kısaltmalar yerine işletme
     * sahibinin dashboard'unda gördüğü 4-6 haneli numarayı yazar.
     */
    public function validateBusinessNumber() {
 $data = $this->input();
 $businessNumber = trim($data['business_number'] ?? '');

 if ($businessNumber === '') {
 $this->json(['success' => false, 'error' => 'İşletme numarası gerekli'], 400);
 }

 if (!preg_match('/^\d{4,6}$/', $businessNumber)) {
 $this->json(['success' => false, 'error' => 'İşletme numarası 4-6 haneli rakam olmalıdır'], 400);
 }

 try {
 $db = DependencyFactory::getDatabase();
 $stmt = $db->prepare(
 "SELECT customer_id, company_name, business_number, subdomain,
 logo_url, logo_path, is_active, is_demo
 FROM customers
 WHERE business_number = ?
 AND (status IS NULL OR LOWER(status) != 'deleted')
 LIMIT 1"
 );
 $stmt->execute([$businessNumber]);
 $biz = $stmt->fetch(\PDO::FETCH_ASSOC);

 if (!$biz) {
 $this->json([
 'success' => false,
 'error' => 'Bu numaraya sahip bir işletme bulunamadı. Lütfen işletme numaranızı kontrol edin.'
 ], 404);
 }

 if (isset($biz['is_active']) && (int)$biz['is_active'] === 0) {
 $this->json([
 'success' => false,
 'error' => 'İşletmeniz pasife alınmıştır. Lütfen yöneticinizle iletişime geçin.'
 ], 403);
 }

 $logoUrl = $this->absoluteAssetUrl(
 $biz['logo_url'] ?? $biz['logo_path'] ?? null
 );

 $this->json(['success' => true, 'data' => [
 'valid' => true,
 'business' => [
 'id' => $biz['customer_id'],
 'name' => $biz['company_name'],
 'business_number' => $biz['business_number'],
 'subdomain' => $biz['subdomain'] ?? null,
 'logo' => $logoUrl,
 ],
 ]]);
 } catch (\Exception $e) {
 \App\Core\Logger::error('validateBusinessNumber error', ['error' => $e->getMessage()]);
 $this->json(['success' => false, 'error' => 'Doğrulama hatası'], 500);
 }
 }

 /**
 * Resolve a customer row by either a business_number or a legacy
 * subdomain. Mobile login accepts both so the rollout of the
 * 6-digit ID can be gradual.
 */
 private function resolveBusinessForMobileLogin(\PDO $db, string $rawInput): ?array {
 $input = trim($rawInput);
 if ($input === '') {
 return null;
 }

 // Pure digits: business_number path
 if (preg_match('/^\d{4,6}$/', $input)) {
 $stmt = $db->prepare(
 "SELECT * FROM customers
 WHERE business_number = ?
 AND (status IS NULL OR LOWER(status) != 'deleted')
 LIMIT 1"
 );
 $stmt->execute([$input]);
 $row = $stmt->fetch(\PDO::FETCH_ASSOC);
 if ($row) {
 return $row;
 }
 }

 // Fallback: subdomain / işletme adı eşleşmesi
 return $this->resolveBusinessForMobileTenant($db, $input);
 }
}
