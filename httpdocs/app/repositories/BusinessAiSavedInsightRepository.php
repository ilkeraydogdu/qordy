<?php
namespace App\Repositories;

use PDO;

/**
 * Persisted AI / rule-based insight bookmarks per business user.
 */
class BusinessAiSavedInsightRepository {
    /** @var PDO */
    protected $db;

    protected $table = 'business_ai_saved_insights';

    public function __construct(PDO $database) {
        $this->db = $database;
    }

    public function tableExists(): bool {
        try {
            $this->db->query("SELECT 1 FROM {$this->table} LIMIT 1");
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listForUser(string $businessId, string $userId): array {
        if (!$this->tableExists()) {
            return [];
        }
        $stmt = $this->db->prepare(
            "SELECT id, insight_id, category_key, category_label, title, metric, body_text, action_hint,
                    impact, tone, icon, source, saved_at
             FROM {$this->table}
             WHERE business_id = :bid AND user_id = :uid
             ORDER BY saved_at DESC"
        );
        $stmt->execute(['bid' => $businessId, 'uid' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return string[]
     */
    public function listInsightIdsForUser(string $businessId, string $userId): array {
        if (!$this->tableExists()) {
            return [];
        }
        $stmt = $this->db->prepare(
            "SELECT insight_id FROM {$this->table} WHERE business_id = :bid AND user_id = :uid"
        );
        $stmt->execute(['bid' => $businessId, 'uid' => $userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        return array_map('strval', $rows);
    }

    public function save(string $businessId, string $userId, array $row): bool {
        if (!$this->tableExists()) {
            return false;
        }
        $sql = "INSERT INTO {$this->table}
            (business_id, user_id, insight_id, category_key, category_label, title, metric, body_text,
             action_hint, impact, tone, icon, source, payload_json, saved_at)
            VALUES
            (:business_id, :user_id, :insight_id, :category_key, :category_label, :title, :metric, :body_text,
             :action_hint, :impact, :tone, :icon, :source, :payload_json, NOW())
            ON DUPLICATE KEY UPDATE
                category_key = VALUES(category_key),
                category_label = VALUES(category_label),
                title = VALUES(title),
                metric = VALUES(metric),
                body_text = VALUES(body_text),
                action_hint = VALUES(action_hint),
                impact = VALUES(impact),
                tone = VALUES(tone),
                icon = VALUES(icon),
                source = VALUES(source),
                payload_json = VALUES(payload_json),
                saved_at = NOW()";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'business_id' => $businessId,
            'user_id' => $userId,
            'insight_id' => $row['insight_id'],
            'category_key' => $row['category_key'] ?? '',
            'category_label' => $row['category_label'] ?? '',
            'title' => $row['title'] ?? '',
            'metric' => $row['metric'] ?? '',
            'body_text' => $row['body_text'] ?? '',
            'action_hint' => $row['action_hint'] ?? '',
            'impact' => $row['impact'] ?? 'orta',
            'tone' => $row['tone'] ?? 'info',
            'icon' => $row['icon'] ?? 'info',
            'source' => $row['source'] ?? 'rule',
            'payload_json' => $row['payload_json'] ?? '{}',
        ]);
    }

    public function unsave(string $businessId, string $userId, string $insightId): bool {
        if (!$this->tableExists()) {
            return false;
        }
        $stmt = $this->db->prepare(
            "DELETE FROM {$this->table} WHERE business_id = :bid AND user_id = :uid AND insight_id = :iid"
        );
        return $stmt->execute(['bid' => $businessId, 'uid' => $userId, 'iid' => $insightId]);
    }
}
