<?php
namespace App\Services;

use App\Core\DependencyFactory;
use App\Core\Logger;

/**
 * Cross-subdomain admin impersonation ("Login As User").
 *
 * Super admin is normally authenticated on qordy.com. Each customer
 * business runs on its own subdomain (e.g. pofudukcafe.qordy.com) with
 * an independent session cookie (per-host, NOT a wildcard cookie), so
 * simply switching $_SESSION on qordy.com does not grant access to the
 * customer's real panel on their subdomain.
 *
 * Instead we mint a one-time, short-lived signed token, persist it in
 * `admin_impersonation_tokens`, and redirect the super admin to
 * `https://<subdomain>.qordy.com/admin-handoff?token=...`. That handler
 * (see `BusinessesController::adminHandoff`) validates the token and
 * creates the customer session on the subdomain.
 *
 * The token is:
 *   - 64 hex chars (32 random bytes)
 *   - valid for 2 minutes
 *   - single-use (marked `used_at` on consumption)
 *   - bound to a specific target_customer_id
 */
class ImpersonationService {

    /** Token geçerlilik süresi (saniye). */
    public const TOKEN_TTL_SECONDS = 120;

    private \PDO $db;

    public function __construct() {
        $this->db = DependencyFactory::getDatabase();
    }

    /**
     * Super admin tarafından, bir müşteri hesabına geçmek için token üret.
     *
     * @param string      $targetCustomerId    Hedef işletme customer_id.
     * @param string|null $targetUserId        Varsa users tablosundaki user_id.
     * @param int|null    $targetRoleId        Hedef kullanıcının rol_id'si.
     * @param string      $createdByUserId     İşlemi yapan super admin user_id.
     * @param string|null $createdByEmail      Süper admin e-posta (log için).
     * @param string|null $returnUrl           "Qodmin'e dön" için dönüş URL'i.
     * @return string|null 64 karakter hex token; başarısızsa null.
     */
    public function mintToken(
        string $targetCustomerId,
        ?string $targetUserId,
        ?int $targetRoleId,
        string $createdByUserId,
        ?string $createdByEmail = null,
        ?string $returnUrl = null
    ): ?string {
        try {
            $this->ensureTable();

            $token     = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', time() + self::TOKEN_TTL_SECONDS);
            $ip        = $_SERVER['REMOTE_ADDR']     ?? null;
            $ua        = $_SERVER['HTTP_USER_AGENT'] ?? null;
            if (is_string($ua) && strlen($ua) > 500) {
                $ua = substr($ua, 0, 500);
            }

            $stmt = $this->db->prepare("
                INSERT INTO admin_impersonation_tokens (
                    token, target_customer_id, target_user_id, target_role_id,
                    created_by_user_id, created_by_email, created_ip, user_agent,
                    return_url, expires_at, created_at
                ) VALUES (
                    :token, :target_customer_id, :target_user_id, :target_role_id,
                    :created_by_user_id, :created_by_email, :created_ip, :user_agent,
                    :return_url, :expires_at, NOW()
                )
            ");
            $stmt->execute([
                ':token'              => $token,
                ':target_customer_id' => $targetCustomerId,
                ':target_user_id'     => $targetUserId,
                ':target_role_id'     => $targetRoleId,
                ':created_by_user_id' => $createdByUserId,
                ':created_by_email'   => $createdByEmail,
                ':created_ip'         => $ip,
                ':user_agent'         => $ua,
                ':return_url'         => $returnUrl,
                ':expires_at'         => $expiresAt,
            ]);

            return $token;
        } catch (\Throwable $e) {
            Logger::error('ImpersonationService::mintToken failed', [
                'error'              => $e->getMessage(),
                'target_customer_id' => $targetCustomerId,
            ]);
            return null;
        }
    }

    /**
     * Subdomain tarafı: token'ı doğrula ve "kullanıldı" olarak işaretle.
     * Return edilen array'deki bilgiyle caller customer session'ı kurar.
     *
     * @return array|null ['target_customer_id'=>..., 'target_user_id'=>..., 'return_url'=>..., 'created_by_user_id'=>..., 'created_by_email'=>...]
     */
    public function consumeToken(string $token): ?array {
        if ($token === '' || !ctype_xdigit($token) || strlen($token) !== 64) {
            return null;
        }
        try {
            $this->ensureTable();

            // Tek kullanımlık tüketim için lock-and-update: used_at NULL ve
            // expires_at > NOW() şartını atomik güncellemeye yerleştiriyoruz.
            $stmt = $this->db->prepare("
                UPDATE admin_impersonation_tokens
                SET used_at = NOW()
                WHERE token = :token
                  AND used_at IS NULL
                  AND expires_at > NOW()
            ");
            $stmt->execute([':token' => $token]);
            if ($stmt->rowCount() !== 1) {
                return null;
            }

            $fetch = $this->db->prepare("
                SELECT target_customer_id, target_user_id, target_role_id,
                       return_url, created_by_user_id, created_by_email
                FROM admin_impersonation_tokens
                WHERE token = :token
                LIMIT 1
            ");
            $fetch->execute([':token' => $token]);
            $row = $fetch->fetch(\PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (\Throwable $e) {
            Logger::error('ImpersonationService::consumeToken failed', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Süresi geçmiş token'ları temizle (garbage collection).
     * İsteğe bağlı olarak cron'dan çağırılabilir.
     */
    public function gc(): int {
        try {
            $stmt = $this->db->prepare("
                DELETE FROM admin_impersonation_tokens
                WHERE (used_at IS NOT NULL AND used_at < DATE_SUB(NOW(), INTERVAL 1 DAY))
                   OR (used_at IS NULL     AND expires_at < DATE_SUB(NOW(), INTERVAL 1 HOUR))
            ");
            $stmt->execute();
            return $stmt->rowCount();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Tablo şema drift'ine karşı idempotent garanti.
     */
    private function ensureTable(): void {
        try {
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS admin_impersonation_tokens (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    token VARCHAR(128) NOT NULL,
                    target_customer_id VARCHAR(50) NOT NULL,
                    target_user_id VARCHAR(50) DEFAULT NULL,
                    target_role_id INT UNSIGNED DEFAULT NULL,
                    created_by_user_id VARCHAR(50) NOT NULL,
                    created_by_email VARCHAR(191) DEFAULT NULL,
                    created_ip VARCHAR(45) DEFAULT NULL,
                    user_agent VARCHAR(512) DEFAULT NULL,
                    return_url VARCHAR(512) DEFAULT NULL,
                    expires_at DATETIME NOT NULL,
                    used_at DATETIME DEFAULT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    UNIQUE KEY uq_token (token),
                    INDEX idx_target_customer (target_customer_id),
                    INDEX idx_expires (expires_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } catch (\Throwable $e) {
            // ignore; table may exist
        }
    }
}
