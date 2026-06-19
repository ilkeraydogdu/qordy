<?php
namespace App\Services;

/**
 * İşletme bazlı kullanıcı aktivite günlüğü (giriş, çıkış ve genişletilebilir aksiyonlar)
 */
class ActivityLogService {
    private $db;

    public function __construct(\PDO $db) {
        $this->db = $db;
    }

    public static function ensureTable(\PDO $db): void {
        $db->exec("CREATE TABLE IF NOT EXISTS user_activity_logs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            tenant_id VARCHAR(50) NULL,
            user_id VARCHAR(50) NULL,
            actor_type VARCHAR(32) NOT NULL DEFAULT 'user',
            action VARCHAR(64) NOT NULL,
            entity_type VARCHAR(64) NULL,
            entity_id VARCHAR(128) NULL,
            metadata JSON NULL,
            ip_address VARCHAR(45) NULL,
            user_agent VARCHAR(512) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_tenant_created (tenant_id, created_at),
            KEY idx_user_created (user_id, created_at),
            KEY idx_action_created (action, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function log(
        string $action,
        ?string $businessId = null,
        ?string $userId = null,
        ?string $entityType = null,
        ?string $entityId = null,
        ?array $metadata = null,
        string $actorType = 'user'
    ): void {
        try {
            self::ensureTable($this->db);
            $ip = $_SERVER['REMOTE_ADDR'] ?? null;
            $ua = isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 512) : null;
            $metaJson = $metadata !== null ? json_encode($metadata, JSON_UNESCAPED_UNICODE) : null;
            $stmt = $this->db->prepare(
                'INSERT INTO user_activity_logs (tenant_id, user_id, actor_type, action, entity_type, entity_id, metadata, ip_address, user_agent) VALUES (?,?,?,?,?,?,?,?,?)'
            );
            $stmt->execute([
                $businessId,
                $userId,
                $actorType,
                $action,
                $entityType,
                $entityId,
                $metaJson,
                $ip,
                $ua
            ]);
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::warning('ActivityLogService::log failed', ['error' => $e->getMessage()]);
            }
        }
    }

    public function query(array $filters, int $limit = 200, int $offset = 0): array {
        self::ensureTable($this->db);
        $where = ['1=1'];
        $params = [];
        if (!empty($filters['business_id'])) {
            $where[] = 'tenant_id = :bid';
            $params['bid'] = $filters['business_id'];
        }
        if (!empty($filters['user_id'])) {
            $where[] = 'user_id = :uid';
            $params['uid'] = $filters['user_id'];
        }
        if (!empty($filters['action'])) {
            $where[] = 'action = :act';
            $params['act'] = $filters['action'];
        }
        if (!empty($filters['date_from'])) {
            $where[] = 'created_at >= :df';
            $params['df'] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'created_at <= :dt';
            $params['dt'] = $filters['date_to'] . ' 23:59:59';
        }
        if (!empty($filters['entity_type'])) {
            $where[] = 'entity_type = :et';
            $params['et'] = $filters['entity_type'];
        }
        $sql = 'SELECT * FROM user_activity_logs WHERE ' . implode(' AND ', $where) . ' ORDER BY created_at DESC LIMIT ' . (int)$limit . ' OFFSET ' . (int)$offset;
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }
}
