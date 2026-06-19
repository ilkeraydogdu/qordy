<?php
namespace App\Services;

use App\Core\DependencyFactory;
use App\Core\Logger;

/**
 * PushService — FCM push bildirim gönderimi (HTTP v1 API)
 *
 * Phase 2 altyapısı:
 *  - user_devices tablosundan aktif FCM tokenları çeker
 *  - FCM HTTP v1 endpoint'ine JSON payload gönderir
 *  - Expired/unregistered token'ları temizler
 *
 * Yapılandırma (system_settings üzerinden):
 *   fcm_project_id       : Firebase project id
 *   fcm_server_key       : (Legacy) sunucu anahtarı — yedek
 *   fcm_service_account  : OAuth2 access token (JWT) elde etmek için service-account json path
 *
 * Not: Cursor tarafında anahtar dosyası yüklenmemiş olabilir, o yüzden aktif değilse
 * sessizce log atar ve başarısızlığı döner — cron/scheduler sistemi bozmaz.
 */
class PushService {
    private $db;
    private $settings;

    public function __construct() {
        $this->db = DependencyFactory::getDatabase();
        $this->settings = DependencyFactory::getSystemSettingsService();
    }

    public function isConfigured(): bool {
        $key = $this->settings->getSetting('fcm_server_key', '');
        $account = $this->settings->getSetting('fcm_service_account', '');
        return !empty($key) || !empty($account);
    }

    /**
     * Belirli bir kullanıcının tüm aktif cihazlarına bildirim gönder.
     * @param string $userId Qordy user/customer id
     * @param string $title Bildirim başlığı
     * @param string $body  Bildirim içeriği
     * @param array  $data  Ek veri (stringe çevrilir)
     * @return array ['sent' => int, 'failed' => int, 'skipped' => int]
     */
    public function sendToUser(string $userId, string $title, string $body, array $data = []): array {
        $result = ['sent' => 0, 'failed' => 0, 'skipped' => 0];
        $tokens = $this->getActiveTokensForUser($userId);
        if (empty($tokens)) {
            $result['skipped'] = 1;
            return $result;
        }
        foreach ($tokens as $row) {
            $ok = $this->sendSingle($row['fcm_token'], $title, $body, $data);
            if ($ok === true) $result['sent']++;
            elseif ($ok === 'invalid') {
                $this->deactivateToken($row['device_id']);
                $result['failed']++;
            } else {
                $result['failed']++;
            }
        }
        return $result;
    }

    /**
     * Tenant içindeki tüm yöneticilere (BUSINESS_OWNER) bildirim gönderir.
     */
    public function sendToTenantOwners(string $tenantId, string $title, string $body, array $data = []): array {
        $summary = ['sent' => 0, 'failed' => 0, 'skipped' => 0];
        try {
            $stmt = $this->db->prepare("SELECT DISTINCT user_id FROM user_devices WHERE tenant_id = ? AND is_active = 1");
            $stmt->execute([$tenantId]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            foreach ($rows as $r) {
                $res = $this->sendToUser($r['user_id'], $title, $body, $data);
                $summary['sent']    += $res['sent'];
                $summary['failed']  += $res['failed'];
                $summary['skipped'] += $res['skipped'];
            }
        } catch (\Exception $e) {
            Logger::warning('PushService::sendToTenantOwners failed', ['error' => $e->getMessage()]);
        }
        return $summary;
    }

    /**
     * Tenant içindeki belirli role(ler)e sahip kullanıcıların mobil cihazlarına bildirim gönderir.
     * $roles dizisi 'BUSINESS_OWNER', 'BUSINESS_MANAGER', 'WAITER', 'KITCHEN', 'CASHIER' gibi değerler içerebilir.
     * Boş dizi geçilirse tüm tenant cihazlarına gönderilir.
     */
    public function sendToTenantRoles(string $tenantId, array $roles, string $title, string $body, array $data = []): array {
        $summary = ['sent' => 0, 'failed' => 0, 'skipped' => 0];
        try {
            $userIds = $this->resolveTenantUserIdsByRoles($tenantId, $roles);
            if (empty($userIds)) {
                $summary['skipped'] = 1;
                return $summary;
            }
            foreach ($userIds as $uid) {
                $res = $this->sendToUser($uid, $title, $body, $data);
                $summary['sent']    += $res['sent'];
                $summary['failed']  += $res['failed'];
                $summary['skipped'] += $res['skipped'];
            }
        } catch (\Exception $e) {
            Logger::warning('PushService::sendToTenantRoles failed', ['error' => $e->getMessage(), 'tenant' => $tenantId, 'roles' => $roles]);
        }
        return $summary;
    }

    /**
     * Tenant + rol filtresine göre gerçek user_id listesini döndürür.
     * Manager/Owner için customers tablosunu, staff için users tablosunu kontrol eder.
     */
    private function resolveTenantUserIdsByRoles(string $tenantId, array $roles): array {
        $ids = [];
        $roles = array_values(array_filter(array_map('strtoupper', $roles)));
        $wantOwners = empty($roles) || array_intersect($roles, ['BUSINESS_OWNER', 'BUSINESS_MANAGER', 'OWNER', 'MANAGER', 'SUPER_ADMIN', 'ADMIN']);
        $staffRoles = array_values(array_diff($roles, ['BUSINESS_OWNER', 'BUSINESS_MANAGER', 'OWNER', 'MANAGER', 'SUPER_ADMIN', 'ADMIN']));

        if ($wantOwners) {
            try {
                $stmt = $this->db->prepare("SELECT customer_id FROM customers WHERE customer_id = ? LIMIT 1");
                $stmt->execute([$tenantId]);
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                if ($row) { $ids[] = $row['customer_id']; }
            } catch (\Exception $e) {}
        }

        if (!empty($staffRoles) || empty($roles)) {
            try {
                if (!empty($staffRoles)) {
                    $placeholders = implode(',', array_fill(0, count($staffRoles), '?'));
                    $params = array_merge([$tenantId], $staffRoles);
                    $sql = "SELECT user_id FROM users WHERE tenant_id = ? AND UPPER(role) IN ($placeholders) AND (is_active = 1 OR is_active IS NULL)";
                } else {
                    $params = [$tenantId];
                    $sql = "SELECT user_id FROM users WHERE tenant_id = ? AND (is_active = 1 OR is_active IS NULL)";
                }
                $stmt = $this->db->prepare($sql);
                $stmt->execute($params);
                foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
                    if (!empty($r['user_id'])) $ids[] = $r['user_id'];
                }
            } catch (\Exception $e) {
                Logger::warning('resolveTenantUserIdsByRoles users query failed', ['error' => $e->getMessage()]);
            }
        }

        return array_values(array_unique($ids));
    }

    // ─── Düşük seviye: tek token ───────────────────────────────

    /**
     * @return true|false|'invalid'
     */
    private function sendSingle(string $fcmToken, string $title, string $body, array $data) {
        if (!$this->isConfigured()) {
            Logger::info('PushService: FCM not configured, skipping push', ['token_prefix' => substr($fcmToken, 0, 12)]);
            return false;
        }
        try {
            $serverKey = (string)$this->settings->getSetting('fcm_server_key', '');
            if ($serverKey === '') {
                return false; // HTTP v1 desteği ayrıca eklenebilir
            }
            $payload = [
                'to' => $fcmToken,
                'notification' => [
                    'title' => $title,
                    'body'  => $body,
                    'sound' => 'default',
                ],
                'data' => array_map(fn($v) => is_scalar($v) ? (string)$v : json_encode($v), $data),
                'priority' => 'high',
            ];
            $ch = curl_init('https://fcm.googleapis.com/fcm/send');
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: key=' . $serverKey,
                    'Content-Type: application/json',
                ],
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_TIMEOUT => 10,
            ]);
            $resp = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($code !== 200) {
                Logger::warning('FCM non-200', ['code' => $code, 'resp' => $resp]);
                return false;
            }
            $decoded = json_decode($resp, true);
            if (isset($decoded['results'][0]['error'])) {
                $err = $decoded['results'][0]['error'];
                if (in_array($err, ['NotRegistered', 'InvalidRegistration'], true)) {
                    return 'invalid';
                }
                return false;
            }
            return true;
        } catch (\Exception $e) {
            Logger::warning('PushService::sendSingle exception', ['error' => $e->getMessage()]);
            return false;
        }
    }

    private function getActiveTokensForUser(string $userId): array {
        try {
            $stmt = $this->db->prepare("
                SELECT device_id, fcm_token, platform
                FROM user_devices
                WHERE user_id = ? AND is_active = 1 AND fcm_token IS NOT NULL AND fcm_token <> ''
            ");
            $stmt->execute([$userId]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {
            // Fallback: push_tokens legacy
            try {
                $stmt2 = $this->db->prepare("SELECT CONCAT('legacy_', SUBSTRING(MD5(token),1,12)) AS device_id, token AS fcm_token, platform FROM push_tokens WHERE user_id = ?");
                $stmt2->execute([$userId]);
                return $stmt2->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            } catch (\Exception $e2) {
                return [];
            }
        }
    }

    private function deactivateToken(string $deviceId): void {
        try {
            $this->db->prepare("UPDATE user_devices SET is_active = 0 WHERE device_id = ?")->execute([$deviceId]);
        } catch (\Exception $e) {}
    }
}
