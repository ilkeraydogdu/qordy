<?php
namespace App\Repositories;

use App\Core\BaseRepository;

class PrinterBridgeRepository extends BaseRepository {
    protected $table = 'printer_bridges';
    protected $primaryKey = 'bridge_id';
    
    public function __construct($database) {
        parent::__construct($database);
    }
    
    public function getByBusiness(string $businessId): array {
        $sql = "SELECT * FROM {$this->table} WHERE tenant_id = :business_id ORDER BY created_at DESC";
        return $this->fetchAll($sql, ['business_id' => $businessId]);
    }
    
    public function getByApiKey(string $apiKey): ?array {
        $sql = "SELECT * FROM {$this->table} WHERE api_key = :api_key LIMIT 1";
        return $this->fetchOne($sql, ['api_key' => $apiKey]);
    }
    
    public function getOnline(): array {
        // Tenant filtresi: TenantContext çözüldüyse yalnızca kendi bridge'lerini gösterir.
        // Çözülmemişse (super-admin) tüm tenant'ları listeler.
        $filter = $this->getTenantFilter();
        $where = "status = 'ONLINE' AND last_heartbeat >= DATE_SUB(NOW(), INTERVAL 2 MINUTE)";
        $params = [];
        if (!empty($filter['where'])) {
            $where .= ' AND ' . $filter['where'];
            $params = array_merge($params, $filter['params']);
        }
        $sql = "SELECT * FROM {$this->table} WHERE {$where} ORDER BY last_heartbeat DESC";
        return $this->fetchAll($sql, $params);
    }
    
    public function updateHeartbeat(string $bridgeId): bool {
        $sql = "UPDATE {$this->table} 
                SET last_heartbeat = NOW(), 
                    status = 'ONLINE', 
                    updated_at = NOW() 
                WHERE bridge_id = :bridge_id";
        return $this->execute($sql, ['bridge_id' => $bridgeId]);
    }
    
    public function updateStatus(string $bridgeId, string $status): bool {
        $sql = "UPDATE {$this->table} 
                SET status = :status, 
                    updated_at = NOW() 
                WHERE bridge_id = :bridge_id";
        return $this->execute($sql, [
            'bridge_id' => $bridgeId,
            'status' => $status
        ]);
    }
    
    public function getPendingQueueByBusiness(string $businessId, int $limit = 10): array {
        $sql = "SELECT q.*, r.receipt_number, r.total_amount, o.table_id
                FROM receipt_print_queue q
                INNER JOIN receipts r ON q.receipt_id = r.receipt_id
                LEFT JOIN orders o ON r.order_id = o.order_id
                WHERE q.tenant_id = :business_id
                AND q.status = 'PENDING'
                ORDER BY q.created_at ASC
                LIMIT " . intval($limit);
        return $this->fetchAll($sql, [
            'business_id' => $businessId
        ]);
    }

    /**
     * Get pending queue items for a specific preparation screen within a business
     * @param string $businessId Business ID
     * @param string $screenId Preparation screen ID
     * @param int $limit Maximum number of items to return
     * @return array Queue items for the specific screen
     */
    public function getPendingQueueByScreen(string $businessId, string $screenId, int $limit = 10): array {
        $sql = "SELECT q.*, r.receipt_number, r.total_amount, o.table_id
                FROM receipt_print_queue q
                INNER JOIN receipts r ON q.receipt_id = r.receipt_id
                LEFT JOIN orders o ON r.order_id = o.order_id
                LEFT JOIN printers p ON q.printer_id = p.printer_id
                WHERE q.tenant_id = :business_id
                AND p.preparation_screen_id = :screen_id
                AND q.status = 'PENDING'
                ORDER BY q.created_at ASC
                LIMIT " . intval($limit);
        return $this->fetchAll($sql, [
            'business_id' => $businessId,
            'screen_id' => $screenId
        ]);
    }

    /**
     * Get pending queue items for a specific printer within a business
     * @param string $businessId Business ID
     * @param string $printerId Printer ID
     * @param int $limit Maximum number of items to return
     * @return array Queue items for the specific printer
     */
    public function getPendingQueueByPrinter(string $businessId, string $printerId, int $limit = 10): array {
        $sql = "SELECT q.*, r.receipt_number, r.total_amount, o.table_id
                FROM receipt_print_queue q
                INNER JOIN receipts r ON q.receipt_id = r.receipt_id
                LEFT JOIN orders o ON r.order_id = o.order_id
                WHERE q.tenant_id = :business_id
                AND q.printer_id = :printer_id
                AND q.status = 'PENDING'
                ORDER BY q.created_at ASC
                LIMIT " . intval($limit);
        return $this->fetchAll($sql, [
            'business_id' => $businessId,
            'printer_id' => $printerId
        ]);
    }
}

