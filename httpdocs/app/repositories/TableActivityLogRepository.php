<?php
namespace App\Repositories;

use App\Core\BaseRepository;
use PDO;

/**
 * Table Activity Log Repository
 * Masa hareket kayıtları (silme, eksiltme, iptal vb.)
 */
class TableActivityLogRepository extends BaseRepository {
    protected $table = 'table_activity_logs';
    protected $primaryKey = 'log_id';

    public function __construct($database) {
        parent::__construct($database);
        $this->ensureTableExists();
    }

    /**
     * Ensure the table exists (auto-create if needed)
     */
    private function ensureTableExists(): void {
        static $checked = false;
        if ($checked) return;
        $checked = true;
        
        try {
            $stmt = $this->db->query("SHOW TABLES LIKE 'table_activity_logs'");
            if ($stmt->rowCount() === 0) {
                // Run migration
                $migrationFile = __DIR__ . '/../migrations/20260206_create_table_activity_logs.php';
                if (file_exists($migrationFile)) {
                    require_once $migrationFile;
                    if (function_exists('run')) {
                        run();
                    }
                }
            }
        } catch (\Exception $e) {
            // Silent fail
        }
    }

    /**
     * Create a new activity log entry
     */
    public function createLog(array $data): bool {
        try {
            if (empty($data['log_id'])) {
                $data['log_id'] = $this->generateId('tal');
            }
            
            $sql = "INSERT INTO {$this->table} 
                    (log_id, tenant_id, table_id, table_name, order_id, order_item_id, 
                     action_type, action_details, item_name, old_quantity, new_quantity, 
                     item_price, total_affected, performed_by, performed_by_name, performed_by_role)
                    VALUES 
                    (:log_id, :business_id, :table_id, :table_name, :order_id, :order_item_id,
                     :action_type, :action_details, :item_name, :old_quantity, :new_quantity,
                     :item_price, :total_affected, :performed_by, :performed_by_name, :performed_by_role)";
            
            $params = [
                'log_id' => $data['log_id'],
                'business_id' => $data['business_id'] ?? '',
                'table_id' => $data['table_id'] ?? '',
                'table_name' => $data['table_name'] ?? '',
                'order_id' => $data['order_id'] ?? null,
                'order_item_id' => $data['order_item_id'] ?? null,
                'action_type' => $data['action_type'] ?? 'ITEM_DELETED',
                'action_details' => isset($data['action_details']) ? json_encode($data['action_details'], JSON_UNESCAPED_UNICODE) : null,
                'item_name' => $data['item_name'] ?? null,
                'old_quantity' => $data['old_quantity'] ?? null,
                'new_quantity' => $data['new_quantity'] ?? null,
                'item_price' => $data['item_price'] ?? null,
                'total_affected' => $data['total_affected'] ?? null,
                'performed_by' => $data['performed_by'] ?? null,
                'performed_by_name' => $data['performed_by_name'] ?? null,
                'performed_by_role' => $data['performed_by_role'] ?? null,
            ];
            
            return $this->execute($sql, $params);
        } catch (\Exception $e) {
            error_log("TableActivityLogRepository::createLog error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get activity logs for a specific table
     */
    public function getByTable(string $tableId, int $limit = 50, int $offset = 0, ?string $dateFilter = null): array {
        try {
            $tenantFilter = $this->getTenantFilter();
            $sql = "SELECT * FROM {$this->table} WHERE table_id = :table_id";
            $params = ['table_id' => $tableId];
            
            if (!empty($tenantFilter['where'])) {
                $sql .= " AND " . $tenantFilter['where'];
                $params = array_merge($params, $tenantFilter['params']);
            }

            if ($dateFilter === 'today') {
                $sql .= " AND DATE(created_at) = CURDATE()";
            }
            
            $sql .= " ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
            
            $stmt = $this->db->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue(":$key", $value);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {
            error_log("TableActivityLogRepository::getByTable error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get activity logs for a specific order
     */
    public function getByOrder(string $orderId): array {
        try {
            $sql = "SELECT * FROM {$this->table} WHERE order_id = :order_id ORDER BY created_at DESC";
            return $this->fetchAll($sql, ['order_id' => $orderId]) ?: [];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get today's activity logs for a business
     */
    public function getTodayLogs(): array {
        try {
            $tenantFilter = $this->getTenantFilter();
            $sql = "SELECT * FROM {$this->table} WHERE DATE(created_at) = CURDATE()";
            $params = [];
            
            if (!empty($tenantFilter['where'])) {
                $sql .= " AND " . $tenantFilter['where'];
                $params = array_merge($params, $tenantFilter['params']);
            }
            
            $sql .= " ORDER BY created_at DESC LIMIT 200";
            
            return $this->fetchAll($sql, $params) ?: [];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Generate unique ID
     */
    private function generateId(string $prefix = 'tal'): string {
        return $prefix . '_' . bin2hex(random_bytes(12));
    }
}
