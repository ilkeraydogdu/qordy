<?php
namespace App\Repositories;

use PDO;

/**
 * Persists the short-lived state needed to bridge iyzico's cross-origin
 * POST callback to our custom payment link flow.
 *
 * We cannot rely on $_SESSION there because browsers running with
 * SameSite=Lax session cookies (our default) strip the cookie on
 * cross-site POSTs initiated by iyzico.
 */
class CustomPaymentLinkIntentRepository {
    /** @var PDO */
    protected $db;
    protected $table = 'custom_payment_link_intents';

    public function __construct(PDO $database) {
        $this->db = $database;
    }

    public function insert(array $row): bool {
        $fields = array_keys($row);
        $placeholders = array_map(static fn($f) => ':' . $f, $fields);
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->table,
            implode(',', $fields),
            implode(',', $placeholders)
        );
        $stmt = $this->db->prepare($sql);
        foreach ($row as $k => $v) {
            $stmt->bindValue(':' . $k, $v);
        }
        return $stmt->execute();
    }

    public function findByGatewayToken(string $gatewayToken): ?array {
        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->table} WHERE gateway_token = :t LIMIT 1"
        );
        $stmt->execute(['t' => $gatewayToken]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findByConversationId(string $conversationId): ?array {
        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->table} WHERE conversation_id = :c LIMIT 1"
        );
        $stmt->execute(['c' => $conversationId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Most recent intent for a given link (any status). Used by the
     * post-payment activation flow to confirm the user actually paid
     * before we let them set an initial password.
     */
    public function findLatestByLinkId(string $linkId): ?array {
        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->table}
             WHERE link_id = :l
             ORDER BY created_at DESC
             LIMIT 1"
        );
        $stmt->execute(['l' => $linkId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Most recent *completed* intent for a link. For multi-use links a
     * newer pending/failed row must not shadow an earlier successful
     * payment when we decide whether the user is allowed to set a
     * password or whether /pay/{token}/success should render as paid.
     */
    public function findLatestCompletedByLinkId(string $linkId): ?array {
        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->table}
             WHERE link_id = :l AND status = 'completed'
             ORDER BY consumed_at DESC, created_at DESC
             LIMIT 1"
        );
        $stmt->execute(['l' => $linkId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function markCompleted(string $intentId): bool {
        $stmt = $this->db->prepare(
            "UPDATE {$this->table}
             SET status = 'completed', consumed_at = NOW()
             WHERE intent_id = :id AND status = 'pending'"
        );
        $stmt->execute(['id' => $intentId]);
        return $stmt->rowCount() > 0;
    }

    public function markFailed(string $intentId): bool {
        $stmt = $this->db->prepare(
            "UPDATE {$this->table}
             SET status = 'failed', consumed_at = NOW()
             WHERE intent_id = :id AND status = 'pending'"
        );
        return $stmt->execute(['id' => $intentId]);
    }
}
