<?php
namespace App\Services;

use App\Core\BaseService;
use App\Repositories\PrinterBridgeRepository;

class PrinterBridgeService extends BaseService {
    
    public function __construct(PrinterBridgeRepository $repository) {
        parent::__construct($repository);
    }
    
    public function registerBridge(array $bridgeData, string $businessId): ?array {
        // Validate required fields
        if (empty($bridgeData['bridge_name'])) {
            error_log("PrinterBridgeService::registerBridge - bridge_name is required");
            return null;
        }
        
        // Generate API key if not provided
        if (empty($bridgeData['api_key'])) {
            $bridgeData['api_key'] = $this->generateApiKey();
        }
        
        // Check if API key already exists and regenerate if needed
        $maxAttempts = 5;
        $attempts = 0;
        while ($attempts < $maxAttempts) {
            $existing = $this->repository->getByApiKey($bridgeData['api_key']);
            if (!$existing) {
                break; // API key is unique, proceed
            }
            // Generate new API key if exists
            $bridgeData['api_key'] = $this->generateApiKey();
            $attempts++;
        }
        
        if ($attempts >= $maxAttempts) {
            error_log("PrinterBridgeService::registerBridge - Failed to generate unique API key after {$maxAttempts} attempts");
            return null;
        }
        
        require_once __DIR__ . '/../helpers/functions.php';
        
        // Generate bridge_id if not provided
        if (empty($bridgeData['bridge_id'])) {
            $bridgeData['bridge_id'] = generateId('pb');
        }
        
        // Ensure business_id is set (don't rely on tenant isolation for this table)
        $bridgeData['tenant_id'] = $businessId;
        $bridgeData['status'] = 'ONLINE';
        // Don't set created_at/updated_at/last_heartbeat here - let MySQL handle it with DEFAULT CURRENT_TIMESTAMP
        // These will be set automatically by MySQL when the record is inserted
        
        try {
            // Ensure table exists before creating
            $this->ensureTableExists();
            
            $result = $this->repository->create($bridgeData);
            // BaseRepository::create returns ID (string or int) on success, false on failure
            if ($result !== false && !empty($result)) {
                // Return bridge using getByApiKey to avoid tenant isolation issues with findById
                // Since we just created it, we know the api_key is unique
                $bridge = $this->repository->getByApiKey($bridgeData['api_key']);
                if ($bridge && is_array($bridge)) {
                    return $bridge;
                } else {
                    // Fallback: try findById if getByApiKey fails (but tenant filter might prevent finding it)
                    // Use direct SQL query to bypass tenant filter for newly created bridge
                    try {
                        $db = $this->repository->db ?? \App\Core\DependencyFactory::getDatabase();
                        $stmt = $db->prepare("SELECT * FROM printer_bridges WHERE bridge_id = :bridge_id LIMIT 1");
                        $stmt->execute(['bridge_id' => $bridgeData['bridge_id']]);
                        $bridge = $stmt->fetch(\PDO::FETCH_ASSOC);
                        if ($bridge && is_array($bridge)) {
                            return $bridge;
                        }
                    } catch (\Exception $e) {
                        error_log("PrinterBridgeService::registerBridge - Direct SQL query failed: " . $e->getMessage());
                    }
                    
                    error_log("PrinterBridgeService::registerBridge - Bridge created but not found. bridge_id: " . $bridgeData['bridge_id'] . ", api_key: " . substr($bridgeData['api_key'], 0, 10) . "...");
                    // Return the data we tried to insert as fallback (with created_at/updated_at)
                    $bridgeData['created_at'] = date('Y-m-d H:i:s');
                    $bridgeData['updated_at'] = date('Y-m-d H:i:s');
                    return $bridgeData;
                }
            } else {
                error_log("PrinterBridgeService::registerBridge - Repository create returned false or empty. Result: " . var_export($result, true) . " | Data: " . json_encode($bridgeData));
                return null;
            }
        } catch (\Exception $e) {
            error_log("PrinterBridgeService::registerBridge - Exception: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
            return null;
        }
    }
    
    public function updateHeartbeat(string $bridgeId): bool {
        return $this->repository->updateHeartbeat($bridgeId);
    }
    
    public function getQueueByBusiness(string $businessId, int $limit = 10): array {
        return $this->repository->getPendingQueueByBusiness($businessId, $limit);
    }

    /**
     * Get queue items for a specific preparation screen within a business
     * @param string $businessId Business ID
     * @param string $screenId Preparation screen ID
     * @param int $limit Maximum number of items to return
     * @return array Queue items for the specific screen
     */
    public function getQueueByScreen(string $businessId, string $screenId, int $limit = 10): array {
        return $this->repository->getPendingQueueByScreen($businessId, $screenId, $limit);
    }

    /**
     * Get queue items for a specific printer within a business
     * @param string $businessId Business ID
     * @param string $printerId Printer ID
     * @param int $limit Maximum number of items to return
     * @return array Queue items for the specific printer
     */
    public function getQueueByPrinter(string $businessId, string $printerId, int $limit = 10): array {
        return $this->repository->getPendingQueueByPrinter($businessId, $printerId, $limit);
    }
    
    public function validateApiKey(string $apiKey, string $businessId): bool {
        $bridge = $this->repository->getByApiKey($apiKey);
        if (!$bridge) {
            return false;
        }
        
        return ($bridge['tenant_id'] ?? null) === $businessId;
    }
    
    public function getBridgeByApiKey(string $apiKey): ?array {
        return $this->repository->getByApiKey($apiKey);
    }
    
    /**
     * Get all bridges by business ID
     * @param string $businessId Business ID
     * @return array List of bridges
     */
    public function getBridgesByBusiness(string $businessId): array {
        return $this->repository->getByBusiness($businessId);
    }
    
    /**
     * Get business ID by token (checks businesses table for backward compatibility)
     * @param string $token Token or business_id
     * @return string|null Business ID or null
     */
    public function getBusinessIdByToken(string $token): ?string {
        try {
            $db = $this->repository->db ?? \App\Core\DependencyFactory::getDatabase();
            // Business bilgileri artık customers tablosunda tutuluyor
            $stmt = $db->prepare("SELECT customer_id FROM customers WHERE customer_id = :token LIMIT 1");
            $stmt->execute(['token' => $token]);
            $customer = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($customer && !empty($customer['customer_id'])) {
                return $customer['customer_id'];
            }
        } catch (\Exception $e) {
            // Table might not exist - this is acceptable for backward compatibility
            error_log("PrinterBridgeService::getBusinessIdByToken - Error: " . $e->getMessage());
        }
        
        return null;
    }
    
    /**
     * Ensure printer_bridges table exists (creates if not exists)
     * Also ensures required columns exist (migrates existing tables)
     * @return bool True if table exists or was created successfully
     */
    public function ensureTableExists(): bool {
        try {
            $db = $this->repository->db ?? \App\Core\DependencyFactory::getDatabase();
            
            // Check if table exists
            $checkSql = "SHOW TABLES LIKE 'printer_bridges'";
            $checkStmt = $db->query($checkSql);
            $tableExists = $checkStmt->rowCount() > 0;
            
            if (!$tableExists) {
                // Create table if it doesn't exist
                $createTableSql = "CREATE TABLE IF NOT EXISTS `printer_bridges` (
                    `bridge_id` VARCHAR(50) PRIMARY KEY,
                    `tenant_id` VARCHAR(50) NOT NULL,
                    `bridge_name` VARCHAR(200) NOT NULL,
                    `api_key` VARCHAR(100) NOT NULL UNIQUE,
                    `status` VARCHAR(20) DEFAULT 'OFFLINE',
                    `last_heartbeat` TIMESTAMP NULL DEFAULT NULL,
                    `ip_address` VARCHAR(45) NULL,
                    `version` VARCHAR(20) NULL,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX `idx_tenant_id` (`tenant_id`),
                    INDEX `idx_api_key` (`api_key`),
                    INDEX `idx_status` (`status`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Windows printer bridge connections'";
                
                $db->exec($createTableSql);
                error_log("Created printer_bridges table automatically");
                return true;
            }
            
            // Table exists - check and add missing columns
            $columnsToAdd = [];

            if (!\App\Core\DbSchema::hasColumn('printer_bridges', 'bridge_name')) {
                if (\App\Core\DbSchema::hasColumn('printer_bridges', 'device_name')) {
                    $db->exec("ALTER TABLE printer_bridges CHANGE COLUMN device_name bridge_name VARCHAR(200) NOT NULL");
                    \App\Core\DbSchema::forget('printer_bridges');
                    error_log("Renamed device_name to bridge_name in printer_bridges table");
                } else {
                    $columnsToAdd[] = "ADD COLUMN bridge_name VARCHAR(200) NOT NULL AFTER tenant_id";
                }
            }

            if (!\App\Core\DbSchema::hasColumn('printer_bridges', 'api_key')) {
                // Generate api_key for existing records first
                $existingBridges = $db->query("SELECT bridge_id FROM printer_bridges WHERE api_key IS NULL OR api_key = ''");
                $bridges = $existingBridges->fetchAll(\PDO::FETCH_ASSOC);
                foreach ($bridges as $bridge) {
                    $newApiKey = bin2hex(random_bytes(32));
                    $updateStmt = $db->prepare("UPDATE printer_bridges SET api_key = :api_key WHERE bridge_id = :bridge_id");
                    $updateStmt->execute(['api_key' => $newApiKey, 'bridge_id' => $bridge['bridge_id']]);
                }
                
                // Add api_key column
                $columnsToAdd[] = "ADD COLUMN api_key VARCHAR(100) NOT NULL UNIQUE AFTER bridge_name";
                error_log("Added api_key column to printer_bridges table");
            }
            
            // tenant_id type must be VARCHAR (string customer IDs).
            $tenantMeta = \App\Core\DbSchema::columnMeta('printer_bridges', 'tenant_id');
            if ($tenantMeta) {
                $type = strtoupper((string)($tenantMeta['Type'] ?? ''));
                if (strpos($type, 'INT') !== false) {
                    try {
                        $db->exec("ALTER TABLE printer_bridges MODIFY COLUMN tenant_id VARCHAR(50) NOT NULL");
                        \App\Core\DbSchema::forget('printer_bridges');
                    } catch (\Exception $e) {
                        error_log("PrinterBridgeService::ensureTableExists - Failed to change tenant_id type: " . $e->getMessage());
                    }
                }
            }

            // created_at / updated_at must be proper TIMESTAMPs with defaults.
            $createdMeta = \App\Core\DbSchema::columnMeta('printer_bridges', 'created_at');
            if ($createdMeta) {
                $type = strtoupper((string)($createdMeta['Type'] ?? ''));
                $default = $createdMeta['Default'] ?? null;
                if (strpos($type, 'DATETIME') !== false || ($default === null && strpos($type, 'TIMESTAMP') === false)) {
                    try {
                        $db->exec("ALTER TABLE printer_bridges MODIFY COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
                        \App\Core\DbSchema::forget('printer_bridges');
                    } catch (\Exception $e) {
                        error_log("PrinterBridgeService::ensureTableExists - Failed to fix created_at: " . $e->getMessage());
                    }
                }
            }

            $updatedMeta = \App\Core\DbSchema::columnMeta('printer_bridges', 'updated_at');
            if ($updatedMeta) {
                $type  = strtoupper((string)($updatedMeta['Type'] ?? ''));
                $extra = (string)($updatedMeta['Extra'] ?? '');
                if (strpos($type, 'DATETIME') !== false || strpos($extra, 'on update') === false) {
                    try {
                        $db->exec("ALTER TABLE printer_bridges MODIFY COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
                        \App\Core\DbSchema::forget('printer_bridges');
                    } catch (\Exception $e) {
                        error_log("PrinterBridgeService::ensureTableExists - Failed to fix updated_at: " . $e->getMessage());
                    }
                }
            }
            
            // Add missing columns
            if (!empty($columnsToAdd)) {
                $alterSql = "ALTER TABLE printer_bridges " . implode(", ", $columnsToAdd);
                $db->exec($alterSql);
                error_log("Updated printer_bridges table structure: " . implode(", ", $columnsToAdd));
            }
            
            // Ensure indexes exist
            try {
                $db->exec("CREATE INDEX IF NOT EXISTS idx_tenant_id ON printer_bridges(tenant_id)");
            } catch (\Exception $e) {
                // Index might already exist, ignore
            }
            
            try {
                $db->exec("CREATE INDEX IF NOT EXISTS idx_api_key ON printer_bridges(api_key)");
            } catch (\Exception $e) {
                // Index might already exist, ignore
            }
            
            try {
                $db->exec("CREATE INDEX IF NOT EXISTS idx_status ON printer_bridges(status)");
            } catch (\Exception $e) {
                // Index might already exist, ignore
            }
            
            return true;
        } catch (\Exception $e) {
            error_log("PrinterBridgeService::ensureTableExists - Error: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
            return false;
        }
    }
    
    private function generateApiKey(): string {
        return bin2hex(random_bytes(32));
    }
    
    /**
     * Recover timed-out print queue items
     * Resets PRINTING items older than 5 minutes back to PENDING
     * Also resets FAILED items with retry_count < 3 back to PENDING
     * @return array Statistics about recovery operation
     */
    public function recoverTimedOutItems(): array {
        $receiptPrintQueueRepository = \App\Core\DependencyFactory::getReceiptPrintQueueRepository();
        
        $staleLocksReset = $receiptPrintQueueRepository->resetStaleLocks();
        $failedItemsReset = $receiptPrintQueueRepository->resetFailedItemsForRetry();
        $expiredCount = $receiptPrintQueueRepository->expireOldPendingItems();
        
        return [
            'stale_locks_reset' => $staleLocksReset,
            'failed_items_reset' => $failedItemsReset,
            'expired_old_items' => $expiredCount,
            'total_recovered' => $staleLocksReset + $failedItemsReset
        ];
    }
}

