<?php
namespace App\Repositories;

use App\Core\BaseRepository;

class BankTransferPaymentRepository extends BaseRepository {
    protected $table = 'bank_transfer_payments';
    protected $primaryKey = 'transfer_id';

    public function getBySubscription(string $subscriptionId): ?array {
        try {
            $sql = "SELECT * FROM {$this->table} WHERE subscription_id = :sid ORDER BY created_at DESC LIMIT 1";
            $results = $this->fetchAll($sql, ['sid' => $subscriptionId]);
            return !empty($results) ? $results[0] : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get all transfers for a customer (for mobile - pending/approved/rejected)
     */
    public function getByCustomerId(string $customerId, ?string $status = null): array {
        try {
            $sql = "SELECT btp.*, p.name AS package_name, s.billing_cycle
                    FROM {$this->table} btp
                    LEFT JOIN subscriptions s ON btp.subscription_id = s.subscription_id
                    LEFT JOIN packages p ON s.package_id = p.package_id
                    WHERE btp.customer_id = :cid";
            $params = ['cid' => $customerId];
            if ($status) {
                $sql .= " AND btp.status = :status";
                $params['status'] = $status;
            }
            $sql .= " ORDER BY btp.created_at DESC";
            return $this->fetchAll($sql, $params) ?: [];
        } catch (\Exception $e) {
            return [];
        }
    }

    public function getByUniqueCode(string $code): ?array {
        try {
            $sql = "SELECT * FROM {$this->table} WHERE unique_code = :code LIMIT 1";
            $results = $this->fetchAll($sql, ['code' => $code]);
            return !empty($results) ? $results[0] : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function getPendingTransfers(): array {
        try {
            $sql = "SELECT btp.*, 
                           c.email AS customer_email, c.first_name, c.last_name,
                           p.name AS package_name,
                           s.billing_cycle
                    FROM {$this->table} btp
                    LEFT JOIN customers c ON btp.customer_id = c.customer_id
                    LEFT JOIN subscriptions s ON btp.subscription_id = s.subscription_id
                    LEFT JOIN packages p ON s.package_id = p.package_id
                    WHERE btp.status = 'pending'
                    ORDER BY btp.created_at ASC";
            return $this->fetchAll($sql) ?: [];
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('BankTransferPaymentRepository::getPendingTransfers error', ['error' => $e->getMessage()]);
            }
            return [];
        }
    }

    public function getAllTransfers(int $limit = 50, int $offset = 0): array {
        try {
            $sql = "SELECT btp.*, 
                           c.email AS customer_email, c.first_name, c.last_name,
                           p.name AS package_name,
                           s.billing_cycle
                    FROM {$this->table} btp
                    LEFT JOIN customers c ON btp.customer_id = c.customer_id
                    LEFT JOIN subscriptions s ON btp.subscription_id = s.subscription_id
                    LEFT JOIN packages p ON s.package_id = p.package_id
                    ORDER BY btp.created_at DESC
                    LIMIT :lim OFFSET :off";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
            $stmt->bindValue(':off', $offset, \PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {
            return [];
        }
    }

    public function updateStatus(string $transferId, string $status, ?string $adminNote, ?string $reviewedBy): bool {
        try {
            $sql = "UPDATE {$this->table} 
                    SET status = :status, admin_note = :note, reviewed_by = :reviewer, reviewed_at = NOW(), updated_at = NOW()
                    WHERE transfer_id = :tid";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                'status' => $status,
                'note' => $adminNote,
                'reviewer' => $reviewedBy,
                'tid' => $transferId
            ]);
        } catch (\Exception $e) {
            return false;
        }
    }
}
