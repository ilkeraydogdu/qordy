<?php
namespace App\Repositories;

use App\Core\BaseRepository;

class ReceiptPrintQueueRepository extends BaseRepository {
    protected $table = 'receipt_print_queue';
    protected $primaryKey = 'queue_id';
    
    public function __construct($database) {
        parent::__construct($database);
    }
    
    public function getByReceipt($receiptId) {
        $sql = "SELECT * FROM {$this->table} WHERE receipt_id = :receipt_id ORDER BY created_at DESC";
        return $this->fetchAll($sql, ['receipt_id' => $receiptId]);
    }
    
    public function getByPrinter($printerId) {
        $sql = "SELECT * FROM {$this->table} WHERE printer_id = :printer_id AND status = 'PENDING' ORDER BY created_at ASC";
        return $this->fetchAll($sql, ['printer_id' => $printerId]);
    }
    
    public function getPending() {
        $sql = "SELECT * FROM {$this->table} WHERE status = 'PENDING' ORDER BY created_at ASC";
        return $this->fetchAll($sql);
    }
    
    public function getPendingByBusiness(string $businessId, int $limit = 10): array {
        $sql = "SELECT * FROM {$this->table} 
                WHERE tenant_id = :business_id 
                AND status = 'PENDING' 
                ORDER BY created_at ASC 
                LIMIT :limit";
        return $this->fetchAll($sql, [
            'business_id' => $businessId,
            'limit' => $limit
        ]);
    }
    
    /**
     * Atomically lock and fetch a queue item for processing
     * Prevents race condition where multiple bridges could process the same item
     * @param string $bridgeId Bridge ID that will process this item
     * @param string $businessId Business ID
     * @return array|null Locked queue item or null if none available
     */
    public function lockAndFetchNext(string $bridgeId, string $businessId): ?array {
        // First, try to reset any stale locks (PRINTING items older than 5 minutes)
        $this->resetStaleLocks();
        
        // Also reset failed items with retry_count < 3 back to PENDING
        $this->resetFailedItemsForRetry();
        
        // Start transaction for atomic operation
        $this->db->beginTransaction();
        
        try {
            // Find the next pending item - SKIP LOCKED prevents blocking on locked rows
            $sql = "SELECT queue_id FROM {$this->table}
                    WHERE tenant_id = :business_id 
                    AND status = 'PENDING'
                    AND (retry_count IS NULL OR retry_count < 3)
                    AND created_at > DATE_SUB(NOW(), INTERVAL 2 HOUR)
                    ORDER BY created_at ASC
                    LIMIT 1
                    FOR UPDATE SKIP LOCKED";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['business_id' => $businessId]);
            $item = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$item || empty($item['queue_id'])) {
                $this->db->rollBack();
                return null;
            }
            
            $queueId = $item['queue_id'];
            
            // Atomically lock the item
            $updateSql = "UPDATE {$this->table} 
                          SET status = 'PRINTING',
                              processing_bridge_id = :bridge_id,
                              processing_started_at = NOW()
                          WHERE queue_id = :queue_id
                          AND status = 'PENDING'";
            
            $updateStmt = $this->db->prepare($updateSql);
            $updateStmt->execute([
                'bridge_id' => $bridgeId,
                'queue_id' => $queueId
            ]);
            
            if ($updateStmt->rowCount() === 0) {
                // Item was already locked by another bridge
                $this->db->rollBack();
                return null;
            }
            
            // Commit transaction
            $this->db->commit();
            
            // Fetch the locked item with related data (table, receipt, order info)
            return $this->findByIdWithDetails($queueId);
            
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log("Error in lockAndFetchNext: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Find queue item by ID with related table, receipt, and order details
     * @param string $queueId Queue ID
     * @return array|null Queue item with details or null
     */
    public function findByIdWithDetails(string $queueId): ?array {
        $sql = "SELECT 
                    q.*,
                    r.receipt_number,
                    r.order_id,
                    o.table_id,
                    o.table_name,
                    t.zone_name,
                    t.zone_floor,
                    t.floor as table_floor
                FROM {$this->table} q
                LEFT JOIN receipts r ON q.receipt_id = r.receipt_id
                LEFT JOIN orders o ON r.order_id = o.order_id
                LEFT JOIN tables t ON o.table_id = t.table_id
                WHERE q.queue_id = :queue_id
                LIMIT 1";
        
        $result = $this->fetchOne($sql, ['queue_id' => $queueId]);
        
        if ($result) {
            // Add formatted display fields
            $result['table_display'] = $result['table_name'] ?? 'Bilinmeyen Masa';
            if ($result['zone_name']) {
                $result['table_display'] .= ' (' . $result['zone_name'];
                if ($result['zone_floor'] || $result['table_floor']) {
                    $floor = $result['zone_floor'] ?? $result['table_floor'];
                    $result['table_display'] .= ' - ' . $floor . ' Kat';
                }
                $result['table_display'] .= ')';
            }
            
            $result['receipt_display'] = $result['receipt_number'] ?? 'N/A';
            $result['order_display'] = $result['order_id'] ?? 'N/A';
        }
        
        return $result;
    }
    
    /**
     * Reset stale locks (PRINTING items older than 5 minutes)
     * Includes retry_count < 3 guard to prevent infinite reprint loops
     * Scoped to business_id for multi-tenant safety
     * @param string|null $businessId Optional business ID filter
     * @return int Number of items reset
     */
    public function resetStaleLocks(?string $businessId = null): int {
        if ($businessId) {
            $sql = "UPDATE {$this->table} 
                    SET status = 'PENDING',
                        processing_bridge_id = NULL,
                        processing_started_at = NULL,
                        retry_count = COALESCE(retry_count, 0) + 1
                    WHERE status = 'PRINTING'
                    AND tenant_id = :business_id
                    AND COALESCE(retry_count, 0) < 3
                    AND processing_started_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE)
                    AND created_at > DATE_SUB(NOW(), INTERVAL 2 HOUR)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['business_id' => $businessId]);
        } else {
            $sql = "UPDATE {$this->table} 
                    SET status = 'PENDING',
                        processing_bridge_id = NULL,
                        processing_started_at = NULL,
                        retry_count = COALESCE(retry_count, 0) + 1
                    WHERE status = 'PRINTING'
                    AND COALESCE(retry_count, 0) < 3
                    AND processing_started_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE)
                    AND created_at > DATE_SUB(NOW(), INTERVAL 2 HOUR)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
        }
        return $stmt->rowCount();
    }
    
    /**
     * Reset failed items for retry (retry_count < 3, created within last hour)
     * @param string|null $businessId Optional business ID filter
     * @return int Number of items reset
     */
    public function resetFailedItemsForRetry(?string $businessId = null): int {
        // Only retry FAILED jobs that have never been retried (retry_count=0)
        // and are recent (15 min). Broader windows risk reprinting jobs where
        // the print succeeded but the status update failed.
        $whereExtra = $businessId ? "AND tenant_id = :business_id" : "";
        $sql = "UPDATE {$this->table} 
                SET status = 'PENDING',
                    processing_bridge_id = NULL,
                    processing_started_at = NULL,
                    retry_count = COALESCE(retry_count, 0) + 1
                WHERE status = 'FAILED'
                {$whereExtra}
                AND (retry_count IS NULL OR retry_count = 0)
                AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)";
        $stmt = $this->db->prepare($sql);
        if ($businessId) {
            $stmt->execute(['business_id' => $businessId]);
        } else {
            $stmt->execute();
        }
        return $stmt->rowCount();
    }
    
    /**
     * Expire old PENDING, FAILED, and stuck PRINTING items (older than 2 hours)
     * Prevents stale jobs from being picked up after long outages
     * @return int Number of items expired
     */
    public function expireOldPendingItems(): int {
        $sql = "UPDATE {$this->table} 
                SET status = 'EXPIRED',
                    processing_bridge_id = NULL,
                    processing_started_at = NULL
                WHERE status IN ('PENDING', 'FAILED', 'PRINTING')
                AND created_at < DATE_SUB(NOW(), INTERVAL 2 HOUR)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->rowCount();
    }
    
    public function updateStatus($queueId, $status, $errorMessage = null) {
        $updateData = [
            'status' => $status,
            'printed_at' => $status === 'PRINTED' ? date('Y-m-d H:i:s') : null
        ];
        
        // Clear processing fields when status changes from PRINTING
        if ($status !== 'PRINTING') {
            $updateData['processing_bridge_id'] = null;
            $updateData['processing_started_at'] = null;
        }
        
        if ($errorMessage) {
            $updateData['error_message'] = $errorMessage;
        }
        
        if ($status === 'FAILED') {
            // Get current retry count
            $queue = $this->findById($queueId);
            if ($queue) {
                $updateData['retry_count'] = intval($queue['retry_count'] ?? 0) + 1;
            }
        }
        
        return $this->update($queueId, $updateData);
    }
}

