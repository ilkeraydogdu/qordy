<?php
namespace App\Repositories;

use PDO;

/**
 * Stores the last time a customer dismissed an in-app popup for a
 * personalized payment link. Used to implement the "re-prompt every X
 * minutes until purchased" UX.
 */
class CustomPaymentLinkDismissalRepository {
    /** @var PDO */
    protected $db;

    protected $table = 'custom_payment_link_dismissals';

    public function __construct(PDO $database) {
        $this->db = $database;
    }

    /**
     * @return array<string,string> link_id => dismissed_at (Y-m-d H:i:s)
     */
    public function getAllForCustomer(string $customerId): array {
        $stmt = $this->db->prepare(
            "SELECT link_id, dismissed_at FROM {$this->table} WHERE customer_id = :cid"
        );
        $stmt->execute(['cid' => $customerId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $out = [];
        foreach ($rows as $row) {
            $out[$row['link_id']] = $row['dismissed_at'];
        }
        return $out;
    }

    public function dismiss(string $linkId, string $customerId): bool {
        $sql = "INSERT INTO {$this->table} (link_id, customer_id, dismissed_at, dismiss_count)
                VALUES (:lid, :cid, NOW(), 1)
                ON DUPLICATE KEY UPDATE
                    dismissed_at = NOW(),
                    dismiss_count = dismiss_count + 1";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['lid' => $linkId, 'cid' => $customerId]);
    }

    public function clear(string $linkId, string $customerId): bool {
        $stmt = $this->db->prepare(
            "DELETE FROM {$this->table} WHERE link_id = :lid AND customer_id = :cid"
        );
        return $stmt->execute(['lid' => $linkId, 'cid' => $customerId]);
    }
}
