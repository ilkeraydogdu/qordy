<?php
namespace App\Repositories;

use App\Core\BaseRepository;

class ReceiptRepository extends BaseRepository {
    protected $table = 'receipts';
    protected $primaryKey = 'receipt_id';
    
    public function __construct($database) {
        parent::__construct($database);
    }
    
    /** Receipts table has no tenant_id; filter via orders when needed */
    private function getReceiptTenantCondition(): string {
        $tenantId = \App\Core\TenantContext::getId();
        if ($tenantId) {
            return " AND o.tenant_id = " . $this->db->quote($tenantId);
        }
        return '';
    }
    
    public function getByOrder(string $orderId): array {
        $sql = "SELECT r.* FROM {$this->table} r WHERE r.order_id = :oid ORDER BY r.created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['oid' => $orderId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function getByTable(string $tableId): array {
        $sql = "SELECT r.*, o.table_name FROM {$this->table} r LEFT JOIN orders o ON r.order_id = o.order_id WHERE r.table_id = :tid" . $this->getReceiptTenantCondition() . " ORDER BY r.created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['tid' => $tableId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function getDailyReceipts(string $date): array {
        $filter = $this->getReceiptTenantCondition();
        $sql = "SELECT r.*, o.table_name, o.is_paid, o.status AS order_status,
                COALESCE(NULLIF(TRIM(o.staff_name), ''), u.name, '') AS waiter_name,
                COALESCE(creator.name, '') AS created_by_name,
                COALESCE(NULLIF(r.total_amount, 0), o.total_amount) AS total_amount,
                o.total_amount AS order_total
                FROM {$this->table} r
                LEFT JOIN orders o ON r.order_id = o.order_id
                LEFT JOIN users u ON o.created_by = u.user_id
                LEFT JOIN users creator ON r.created_by = creator.user_id
                WHERE DATE(r.created_at) = :dt {$filter} ORDER BY r.created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['dt' => $date]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function getByDateRange(string $startDate, string $endDate): array {
        $filter = $this->getReceiptTenantCondition();
        $sql = "SELECT r.*, o.table_name, o.is_paid, o.status AS order_status,
                COALESCE(NULLIF(TRIM(o.staff_name), ''), u.name, '') AS waiter_name,
                COALESCE(creator.name, '') AS created_by_name,
                COALESCE(NULLIF(r.total_amount, 0), o.total_amount) AS total_amount,
                o.total_amount AS order_total
                FROM {$this->table} r
                LEFT JOIN orders o ON r.order_id = o.order_id
                LEFT JOIN users u ON o.created_by = u.user_id
                LEFT JOIN users creator ON r.created_by = creator.user_id
                WHERE DATE(r.created_at) BETWEEN :sd AND :ed {$filter} ORDER BY r.created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['sd' => $startDate, 'ed' => $endDate]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function getByDatetimeRange(string $startDatetime, string $endDatetime): array {
        $filter = $this->getReceiptTenantCondition();
        $sql = "SELECT r.*, o.table_name FROM {$this->table} r LEFT JOIN orders o ON r.order_id = o.order_id WHERE r.created_at BETWEEN :sd AND :ed {$filter} ORDER BY r.created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['sd' => $startDatetime, 'ed' => $endDatetime]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Find receipt by receipt_number (e.g. 20260205-00005).
     * Used when client sends receipt_number and receipt_id might differ.
     * Tenant filter: include receipts when order.tenant_id matches OR is null (legacy data).
     */
    public function findByReceiptNumber(string $receiptNumber): ?array {
        $tenantId = \App\Core\TenantContext::getId();
        if ($tenantId) {
            $sql = "SELECT r.*, o.table_name FROM {$this->table} r LEFT JOIN orders o ON r.order_id = o.order_id WHERE r.receipt_number = :rn AND (o.tenant_id IS NULL OR o.tenant_id = " . $this->db->quote($tenantId) . ") LIMIT 1";
        } else {
            $sql = "SELECT r.*, o.table_name FROM {$this->table} r LEFT JOIN orders o ON r.order_id = o.order_id WHERE r.receipt_number = :rn LIMIT 1";
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['rn' => $receiptNumber]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
