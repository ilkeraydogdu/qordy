<?php
namespace App\Repositories;

use App\Core\BaseRepository;

class PaymentTransactionRepository extends BaseRepository {
    protected $table = 'payment_transactions';
    protected $primaryKey = 'id';

    public function __construct($database) {
        parent::__construct($database);
    }

    public function getByDateRange(string $startDate, string $endDate): array {
        $dateColumn = 'created_at';
        try {
            if (!$this->hasColumn('created_at')) {
                $dateColumn = 'timestamp';
            }
        } catch (\Throwable $e) {
            $dateColumn = 'timestamp';
        }
        $sql = "SELECT pt.*, u.name as processed_by_name, 
                COALESCE(r.role_code, r.role_id, u.role) as processed_by_role,
                t.name as table_name 
                FROM {$this->table} pt 
                LEFT JOIN users u ON pt.processed_by = u.user_id 
                LEFT JOIN roles r ON u.role_id = r.role_id
                LEFT JOIN tables t ON pt.table_id = t.table_id 
                WHERE pt.{$dateColumn} BETWEEN :start AND :end";
        $params = [
            'start' => $startDate . ' 00:00:00',
            'end' => $endDate . ' 23:59:59',
        ];
        $tenantId = \App\Core\TenantContext::getId();
        if ($tenantId && $this->hasColumn('tenant_id')) {
            $sql .= " AND pt.tenant_id = :tenant_id";
            $params['tenant_id'] = $tenantId;
        }
        $sql .= " ORDER BY pt.{$dateColumn} DESC";
        return $this->fetchAll($sql, $params);
    }

    public function getByOrderId(string $orderId): ?array {
        // SECURITY: always scope by tenant_id so order_id guessing cannot
        // leak another business's payment data.
        $tenantId = $this->resolveTenantId();
        if (empty($tenantId)) {
            return null;
        }
        $sql = "SELECT * FROM {$this->table} WHERE order_id = :order_id AND tenant_id = :tenant_id ORDER BY created_at DESC LIMIT 1";
        return $this->fetchOne($sql, ['order_id' => $orderId, 'tenant_id' => $tenantId]);
    }

    public function getByShiftId(string $shiftId): array {
        $tenantId = $this->resolveTenantId();
        if (empty($tenantId)) {
            return [];
        }
        $sql = "SELECT * FROM {$this->table} WHERE shift_id = :shift_id AND tenant_id = :tenant_id ORDER BY created_at DESC";
        return $this->fetchAll($sql, ['shift_id' => $shiftId, 'tenant_id' => $tenantId]);
    }

    /**
     * Resolve the current tenant id. Fails closed (returns null) if no
     * tenant can be determined.
     */
    private function resolveTenantId(): ?string {
        if (class_exists('\App\Core\TenantResolver')) {
            $resolved = \App\Core\TenantResolver::resolve();
            if (!empty($resolved)) {
                return $resolved;
            }
        }
        $sessionId = $_SESSION['business_id'] ?? ($_SESSION['customer_id'] ?? null);
        return !empty($sessionId) ? $sessionId : null;
    }
}
