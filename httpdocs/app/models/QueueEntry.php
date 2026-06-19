<?php
namespace App\Models;

require_once __DIR__ . '/../core/Model.php';

/**
 * QueueEntry - actual person waiting in the queue (bilet kaydı)
 */
class QueueEntry extends \App\Core\Model
{
    protected $table = 'queue_entries';

    public const STATUS_WAITING   = 'WAITING';
    public const STATUS_NOTIFIED  = 'NOTIFIED';
    public const STATUS_SEATED    = 'SEATED';
    public const STATUS_CANCELLED = 'CANCELLED';
    public const STATUS_NO_SHOW   = 'NO_SHOW';
    public const STATUS_EXPIRED   = 'EXPIRED';

    public const ACTIVE_STATUSES = [self::STATUS_WAITING, self::STATUS_NOTIFIED];

    public function createEntry(array $data): ?int
    {
        $required = ['tenant_id', 'queue_id', 'queue_number', 'session_key', 'name', 'phone'];
        foreach ($required as $k) {
            if (!isset($data[$k]) || $data[$k] === '') {
                return null;
            }
        }
        if (isset($data['notifications']) && is_array($data['notifications'])) {
            $data['notifications'] = json_encode($data['notifications'], JSON_UNESCAPED_UNICODE);
        }
        $id = $this->insert('queue_entries', $data);
        return $id ? (int) $id : null;
    }

    public function findByQueueId(string $queueId): ?array
    {
        $row = $this->fetch(
            "SELECT * FROM queue_entries WHERE queue_id = :qid LIMIT 1",
            ['qid' => $queueId]
        );
        return $row ? $this->decode($row) : null;
    }

    public function findActiveBySessionKey(string $tenantId, string $sessionKey): ?array
    {
        $placeholders = $this->statusPlaceholders(self::ACTIVE_STATUSES);
        $params = ['tid' => $tenantId, 'skey' => $sessionKey];
        foreach (self::ACTIVE_STATUSES as $i => $s) {
            $params["st{$i}"] = $s;
        }

        $row = $this->fetch(
            "SELECT * FROM queue_entries
             WHERE tenant_id = :tid AND session_key = :skey AND status IN ({$placeholders})
             ORDER BY id DESC LIMIT 1",
            $params
        );
        return $row ? $this->decode($row) : null;
    }

    public function findRecentByPhone(string $tenantId, string $phone, int $cooldownMinutes): ?array
    {
        $row = $this->fetch(
            "SELECT * FROM queue_entries
             WHERE tenant_id = :tid AND phone = :p
               AND created_at >= (NOW() - INTERVAL :m MINUTE)
             ORDER BY id DESC LIMIT 1",
            ['tid' => $tenantId, 'p' => $phone, 'm' => max(1, $cooldownMinutes)]
        );
        return $row ? $this->decode($row) : null;
    }

    /**
     * Ordered active queue list (WAITING + NOTIFIED) for a tenant.
     */
    public function getActiveForTenant(string $tenantId): array
    {
        $placeholders = $this->statusPlaceholders(self::ACTIVE_STATUSES);
        $params = ['tid' => $tenantId];
        foreach (self::ACTIVE_STATUSES as $i => $s) {
            $params["st{$i}"] = $s;
        }

        $rows = $this->fetchAll(
            "SELECT * FROM queue_entries
             WHERE tenant_id = :tid AND status IN ({$placeholders})
             ORDER BY created_at ASC, id ASC",
            $params
        );
        return array_map(fn($r) => $this->decode($r), $rows ?: []);
    }

    public function getRecentForTenant(string $tenantId, int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $rows = $this->fetchAll(
            "SELECT * FROM queue_entries
             WHERE tenant_id = :tid
             ORDER BY id DESC LIMIT {$limit}",
            ['tid' => $tenantId]
        );
        return array_map(fn($r) => $this->decode($r), $rows ?: []);
    }

    /**
     * Filtered list for business CRM (date range, status, cap 500).
     */
    public function getFilteredForTenant(string $tenantId, array $f): array
    {
        $where  = ['tenant_id = :tid'];
        $params = ['tid' => $tenantId];
        if (!empty($f['date_from']) && is_string($f['date_from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $f['date_from'])) {
            $where[] = 'DATE(created_at) >= :df';
            $params['df'] = $f['date_from'];
        }
        if (!empty($f['date_to']) && is_string($f['date_to']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $f['date_to'])) {
            $where[] = 'DATE(created_at) <= :dt';
            $params['dt'] = $f['date_to'];
        }
        if (!empty($f['status'])) {
            $st = strtoupper((string) $f['status']);
            if (in_array($st, [
                self::STATUS_WAITING, self::STATUS_NOTIFIED, self::STATUS_SEATED,
                self::STATUS_CANCELLED, self::STATUS_NO_SHOW, self::STATUS_EXPIRED,
            ], true)) {
                $where[] = 'status = :st';
                $params['st'] = $st;
            }
        }
        $limit = (int) ($f['limit'] ?? 200);
        $limit = max(1, min(500, $limit));
        $w   = implode(' AND ', $where);
        $sql = "SELECT * FROM `{$this->table}` WHERE {$w} ORDER BY id DESC LIMIT " . (int) $limit;
        $rows = $this->fetchAll($sql, $params) ?: [];
        return array_map(fn($r) => $this->decode($r), $rows);
    }

    /**
     * CRM cleanup. When $includeActive is false, WAITING/NOTIFIED rows are not deleted.
     *
     * @return int rows deleted
     */
    public function deleteByIdsForTenant(string $tenantId, array $ids, bool $includeActive = false): int
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn($i) => $i > 0)));
        if (empty($ids)) {
            return 0;
        }
        if (count($ids) > 500) {
            $ids = array_slice($ids, 0, 500);
        }
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $activeGuard = $includeActive ? '' : " AND status NOT IN ('WAITING','NOTIFIED')";
        $sql = "DELETE FROM `{$this->table}` WHERE tenant_id = ? AND id IN ({$ph})" . $activeGuard;
        $stmt = $this->rawQuery($sql, array_merge([$tenantId], $ids));
        return $stmt ? (int) $stmt->rowCount() : 0;
    }

    public function nextQueueNumberForToday(string $tenantId): int
    {
        $row = $this->fetch(
            "SELECT COALESCE(MAX(queue_number), 0) + 1 AS next_num
             FROM queue_entries
             WHERE tenant_id = :tid AND DATE(created_at) = CURDATE()",
            ['tid' => $tenantId]
        );
        return (int) ($row['next_num'] ?? 1);
    }

    public function countActive(string $tenantId): int
    {
        $placeholders = $this->statusPlaceholders(self::ACTIVE_STATUSES);
        $params = ['tid' => $tenantId];
        foreach (self::ACTIVE_STATUSES as $i => $s) {
            $params["st{$i}"] = $s;
        }
        $row = $this->fetch(
            "SELECT COUNT(*) AS c FROM queue_entries
             WHERE tenant_id = :tid AND status IN ({$placeholders})",
            $params
        );
        return (int) ($row['c'] ?? 0);
    }

    public function countWaitingAhead(string $tenantId, int $entryId): int
    {
        $row = $this->fetch(
            "SELECT COUNT(*) AS c FROM queue_entries q
             WHERE q.tenant_id = :tid
               AND q.status IN ('WAITING','NOTIFIED')
               AND q.id < :eid",
            ['tid' => $tenantId, 'eid' => $entryId]
        );
        return (int) ($row['c'] ?? 0);
    }

    public function updateStatus(int $id, string $status, array $extra = []): bool
    {
        $data = array_merge(['status' => $status], $extra);
        $data['updated_at'] = date('Y-m-d H:i:s');
        if (isset($data['notifications']) && is_array($data['notifications'])) {
            $data['notifications'] = json_encode($data['notifications'], JSON_UNESCAPED_UNICODE);
        }
        $result = $this->update('queue_entries', $data, 'id = :__id', ['__id' => $id]);
        return $result !== false;
    }

    public function fetchByIdForTenant(int $id, string $tenantId): ?array
    {
        $row = $this->fetch(
            "SELECT * FROM queue_entries WHERE id = :id AND tenant_id = :tid LIMIT 1",
            ['id' => $id, 'tid' => $tenantId]
        );
        return $row ? $this->decode($row) : null;
    }

    public function findStaleNotified(string $tenantId, int $minutes): array
    {
        $rows = $this->fetchAll(
            "SELECT * FROM queue_entries
             WHERE tenant_id = :tid AND status = 'NOTIFIED'
               AND notified_at IS NOT NULL
               AND notified_at <= (NOW() - INTERVAL :m MINUTE)",
            ['tid' => $tenantId, 'm' => max(1, $minutes)]
        );
        return array_map(fn($r) => $this->decode($r), $rows ?: []);
    }

    public function decode(array $row): array
    {
        if (isset($row['notifications']) && is_string($row['notifications'])) {
            $decoded = json_decode($row['notifications'], true);
            $row['notifications'] = is_array($decoded) ? $decoded : [];
        }
        foreach ([
            'has_baby', 'has_accessibility', 'marketing_opt_in',
        ] as $k) {
            if (isset($row[$k])) {
                $row[$k] = (int) $row[$k];
            }
        }
        return $row;
    }

    private function statusPlaceholders(array $statuses): string
    {
        $parts = [];
        foreach ($statuses as $i => $_) {
            $parts[] = ":st{$i}";
        }
        return implode(',', $parts);
    }
}
