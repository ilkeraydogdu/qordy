<?php
namespace App\Repositories;

use PDO;

/**
 * Repository for personalized payment links (custom_payment_links).
 *
 * Kept intentionally minimal — this is a super-admin-only, global
 * (non tenant-scoped) resource, so it does not extend BaseRepository
 * which would otherwise apply tenant filters.
 */
class CustomPaymentLinkRepository {
    /** @var PDO */
    protected $db;

    protected $table = 'custom_payment_links';

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

    public function findByToken(string $token): ?array {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE token = :token LIMIT 1");
        $stmt->execute(['token' => $token]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Mevcut müşteri için ödeme bekleyen aktif özel linkleri döndürür.
     * Müşteri dashboard popup'ı ve mobil bottom sheet için kullanılır.
     *
     * @param string      $customerId mevcut oturum açan müşteri
     * @param string|null $email      müşterinin e-postası (yeni-müşteri modundaki linkler için)
     * @return array<int,array>
     */
    public function findActiveForCustomer(string $customerId, ?string $email = null): array {
        $sql = "SELECT l.*, p.name AS package_name, p.description AS package_description,
                       p.price_monthly, p.price_yearly
                FROM {$this->table} l
                LEFT JOIN packages p ON p.package_id = l.package_id
                WHERE l.is_active = 1
                  AND (l.max_uses = 0 OR l.used_count < l.max_uses)
                  AND (l.expires_at IS NULL OR l.expires_at > NOW())
                  AND (
                        l.customer_id = :cid
                     " . ($email ? "OR (l.mode = 'new_customer' AND LOWER(l.target_email) = LOWER(:email))" : "") . "
                  )
                ORDER BY l.created_at DESC";
        $stmt = $this->db->prepare($sql);
        $params = ['cid' => $customerId];
        if ($email) {
            $params['email'] = $email;
        }
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findById(string $linkId): ?array {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE link_id = :id LIMIT 1");
        $stmt->execute(['id' => $linkId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Atomically increment the used_count and deactivate the link if
     * it was single-use (or we've reached max_uses).
     */
    public function markConsumed(string $linkId): bool {
        $link = $this->findById($linkId);
        if (!$link) {
            return false;
        }

        $newCount = ((int)$link['used_count']) + 1;
        $shouldDeactivate = (int)$link['is_single_use'] === 1
            || $newCount >= (int)$link['max_uses'];

        $sql = "UPDATE {$this->table}
                SET used_count = :cnt,
                    last_used_at = NOW()"
            . ($shouldDeactivate ? ", is_active = 0" : "")
            . " WHERE link_id = :id";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'cnt' => $newCount,
            'id'  => $linkId,
        ]);
    }

    public function setActive(string $linkId, bool $active): bool {
        $stmt = $this->db->prepare(
            "UPDATE {$this->table} SET is_active = :v WHERE link_id = :id"
        );
        return $stmt->execute([
            'v'  => $active ? 1 : 0,
            'id' => $linkId,
        ]);
    }

    public function setReusable(string $linkId, bool $reusable, int $maxUses = 1): bool {
        $stmt = $this->db->prepare(
            "UPDATE {$this->table}
             SET is_single_use = :single, max_uses = :max
             WHERE link_id = :id"
        );
        return $stmt->execute([
            'single' => $reusable ? 0 : 1,
            'max'    => max(1, $maxUses),
            'id'     => $linkId,
        ]);
    }

    public function delete(string $linkId): bool {
        $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE link_id = :id");
        return $stmt->execute(['id' => $linkId]);
    }

    /**
     * List payment links with optional filters:
     *   - mode:          existing_customer|new_customer
     *   - is_active:     1|0
     *   - customer_id:   exact match
     *   - q:             partial match on email / name
     */
    public function listAll(array $filters = [], int $limit = 200, int $offset = 0): array {
        $where = [];
        $params = [];

        if (!empty($filters['mode'])) {
            $where[] = 'mode = :mode';
            $params['mode'] = $filters['mode'];
        }
        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $where[] = 'is_active = :is_active';
            $params['is_active'] = (int)$filters['is_active'];
        }
        if (!empty($filters['customer_id'])) {
            $where[] = 'customer_id = :cid';
            $params['cid'] = $filters['customer_id'];
        }
        if (!empty($filters['q'])) {
            $where[] = '(target_email LIKE :q OR target_name LIKE :q OR note LIKE :q)';
            $params['q'] = '%' . $filters['q'] . '%';
        }

        $sql = "SELECT * FROM {$this->table}";
        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY created_at DESC LIMIT ' . (int)$limit . ' OFFSET ' . (int)$offset;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
