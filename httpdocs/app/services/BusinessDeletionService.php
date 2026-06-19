<?php
namespace App\Services;

/**
 * Business Deletion Service
 * 
 * CRITICAL: This service permanently deletes ALL data for a business
 * Safety measures:
 * - Multiple business_id validations
 * - Transaction-based (rollback on error)
 * - Detailed logging
 * - Plesk subdomain deletion
 * - Folder deletion
 * 
 * @package App\Services
 */
class BusinessDeletionService {
    private $db;
    private $deletedRecords = [];
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    /**
     * Delete business completely
     * 
     * @param string $businessId Customer/Business ID
     * @param string $subdomain Subdomain name
     * @return array Result with success status
     */
    public function deleteBusinessCompletely(string $businessId, string $subdomain = ''): array {
        if (!$this->validateBusinessId($businessId)) {
            return ['success' => false, 'message' => 'Invalid business ID format'];
        }
        
        if (strpos($businessId, 'CUST_') !== 0) {
            return ['success' => false, 'message' => 'Security: Only business customers can be deleted'];
        }
        
        // Check business exists
        $business = $this->getBusinessWithSubdomain($businessId);
        if (!$business) {
            return ['success' => false, 'message' => 'Business not found'];
        }
        
        // Use subdomain from DB if not provided
        $actualSubdomain = !empty($subdomain) ? $subdomain : ($business['subdomain'] ?? '');
        
        // If subdomain was provided, verify it matches (prevent accidental cross-deletion)
        if (!empty($subdomain) && !empty($business['subdomain']) && $business['subdomain'] !== $subdomain) {
            return ['success' => false, 'message' => 'Security: Subdomain mismatch - cannot delete'];
        }
        
        if (class_exists('\App\Core\Logger')) {
            \App\Core\Logger::info('Business deletion initiated', [
                'business_id' => $businessId,
                'subdomain' => $actualSubdomain ?: '(none)',
                'initiated_by' => $_SESSION['user_id'] ?? 'unknown',
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        }
        
        $this->db->beginTransaction();
        
        try {
            // Order matters: we must delete child rows before parent rows.
            //   subdomains.tenant_id -> businesses.tenant_id  (FK with ON DELETE CASCADE)
            //   businesses + customers share the same tenant_id value.
            $this->deleteFromMultiTenantTables($businessId);
            $this->deleteBusinessUsers($businessId);
            $this->deleteSubscriptions($businessId);
            $this->deleteFromSubdomainsTable($businessId);
            $this->deleteFromBusinessesTable($businessId);
            $this->deleteFromCustomersTable($businessId);
            
            $this->db->commit();
            
            // Subdomain cleanup (only if subdomain exists)
            $pleskResult = false;
            $folderResult = false;
            if (!empty($actualSubdomain)) {
                $pleskResult = $this->deletePleskSubdomain($actualSubdomain);
                $folderResult = $this->deleteSubdomainFolder($actualSubdomain);
            }
            
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::info('Business deleted completely', [
                    'business_id' => $businessId,
                    'subdomain' => $actualSubdomain ?: '(none)',
                    'deleted_records' => $this->deletedRecords,
                    'plesk_deleted' => $pleskResult,
                    'folder_deleted' => $folderResult
                ]);
            }
            
            return [
                'success' => true,
                'deleted_records' => $this->deletedRecords,
                'plesk_deleted' => $pleskResult,
                'folder_deleted' => $folderResult
            ];
            
        } catch (\Exception $e) {
            $this->db->rollBack();
            
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('Business deletion failed - rolled back', [
                    'business_id' => $businessId,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
            
            return [
                'success' => false,
                'message' => 'Deletion failed and rolled back',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Validate business ID format
     */
    private function validateBusinessId(string $businessId): bool {
        return !empty($businessId);
    }
    
    /**
     * Check if business exists
     */
    private function businessExists(string $businessId): bool {
        $stmt = $this->db->prepare("SELECT customer_id FROM customers WHERE customer_id = ? LIMIT 1");
        $stmt->execute([$businessId]);
        return $stmt->rowCount() > 0;
    }
    
    /**
     * Get business with subdomain for verification
     */
    private function getBusinessWithSubdomain(string $businessId): ?array {
        $stmt = $this->db->prepare("SELECT customer_id, subdomain FROM customers WHERE customer_id = ? LIMIT 1");
        $stmt->execute([$businessId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ?: null;
    }
    
    /**
     * Delete from all multi-tenant tables
     */
    private function deleteFromMultiTenantTables(string $businessId): void {
        // All tables with business_id column (dynamically discovered + known tables)
        $tables = [
            'notifications', 'order_item_ingredients', 'order_item_extras',
            'order_items', 'orders', 'menu_item_screens',
            'menu_items', 'categories',
            'table_sessions', 'table_activity_logs', 'tables',
            'reservations', 'receipts',
            'receipt_print_queue', 'receipt_templates', 'receipt_template_layouts',
            'shifts', 'expenses', 'ingredients', 'recipes', 'inventory',
            'suppliers', 'purchases', 'waste_records', 'leaves',
            'medical_reports', 'staff_schedules', 'shift_schedules', 'guest_staff',
            'preparation_screen_printers', 'preparation_screen_categories', 'preparation_screens',
            'bridge_detected_printers', 'printer_bridge_tokens', 'printer_bridges', 'printers',
            'print_queue', 'zones', 'zone_printer_mappings',
            'payment_transactions', 'payments', 'saved_payment_methods', 'media_files', 'qr_codes',
            'mobile_tokens', 'user_activity_logs', 'whatsapp_message_logs',
            'system_settings', 'business_day_log',
            'customer_sessions', 'bank_transfer_payments',
            'businesses',
        ];
        
        foreach ($tables as $table) {
            try {
                $checkTable = $this->db->query("SHOW TABLES LIKE '$table'");
                if ($checkTable->rowCount() === 0) continue;
                
                $col = null;
                if ($this->hasColumn($table, 'tenant_id')) {
                    $col = 'tenant_id';
                } elseif ($this->hasColumn($table, 'business_id')) {
                    $col = 'business_id';
                } elseif ($this->hasColumn($table, 'customer_id') && $table !== 'customers') {
                    $col = 'customer_id';
                }
                if (!$col) continue;
                
                $stmt = $this->db->prepare("DELETE FROM `$table` WHERE `{$col}` = ?");
                $stmt->execute([$businessId]);
                $deleted = $stmt->rowCount();
                
                // Log deletion for audit
                if (class_exists('\App\Core\Logger') && $deleted > 0) {
                    \App\Core\Logger::info("Deleted from table: $table", [
                        'table' => $table,
                        'business_id' => $businessId,
                        'deleted_count' => $deleted
                    ]);
                }
                
                if ($deleted > 0) {
                    $this->deletedRecords[$table] = $deleted;
                }
                
            } catch (\Exception $e) {
                // Log but continue with other tables
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::warning("Error deleting from table $table", [
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }
    }
    
    /**
     * Delete users belonging to this business
     * SECURITY: Only deletes users with matching business_id
     */
    private function deleteBusinessUsers(string $businessId): void {
        // CRITICAL: Only delete users that belong to THIS business
        $stmt = $this->db->prepare("DELETE FROM users WHERE tenant_id = ?");
        $stmt->execute([$businessId]);
        $this->deletedRecords['users'] = $stmt->rowCount();
        
        if (class_exists('\App\Core\Logger')) {
            \App\Core\Logger::info("Deleted business users", [
                'business_id' => $businessId,
                'deleted_count' => $this->deletedRecords['users']
            ]);
        }
    }
    
    /**
     * Delete subscriptions
     * SECURITY: Only deletes subscriptions for this specific customer_id/business_id
     */
    private function deleteSubscriptions(string $businessId): void {
        // Canonical tenant column is `tenant_id` post-refactor; tolerate legacy
        // column names for extra safety during migration rollouts.
        if ($this->hasColumn('subscriptions', 'tenant_id')) {
            $idColumn = 'tenant_id';
        } elseif ($this->hasColumn('subscriptions', 'business_id')) {
            $idColumn = 'business_id';
        } elseif ($this->hasColumn('subscriptions', 'customer_id')) {
            $idColumn = 'customer_id';
        } else {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::warning('Subscriptions table has no tenant column', [
                    'business_id' => $businessId
                ]);
            }
            return;
        }
        
        // Delete from subscription_payments first (foreign key)
        // SECURITY: Uses subquery with the correct id column filter
        try {
            $checkTable = $this->db->query("SHOW TABLES LIKE 'subscription_payments'");
            if ($checkTable->rowCount() > 0) {
                $stmt = $this->db->prepare("DELETE FROM subscription_payments WHERE subscription_id IN (SELECT subscription_id FROM subscriptions WHERE {$idColumn} = ?)");
                $stmt->execute([$businessId]);
                $this->deletedRecords['subscription_payments'] = $stmt->rowCount();
            }
        } catch (\Exception $e) {
            // Table might not exist, continue
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::warning('Error deleting subscription_payments', [
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        // Delete subscriptions
        // SECURITY: Only deletes subscriptions with matching id column
        try {
            $checkTable = $this->db->query("SHOW TABLES LIKE 'subscriptions'");
            if ($checkTable->rowCount() > 0) {
                $stmt = $this->db->prepare("DELETE FROM subscriptions WHERE {$idColumn} = ?");
                $stmt->execute([$businessId]);
                $this->deletedRecords['subscriptions'] = $stmt->rowCount();
            }
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('Error deleting subscriptions', [
                    'error' => $e->getMessage(),
                    'id_column' => $idColumn,
                    'business_id' => $businessId
                ]);
            }
            throw $e; // Re-throw to trigger rollback
        }
        
        if (class_exists('\App\Core\Logger')) {
            \App\Core\Logger::info("Deleted subscriptions", [
                'business_id' => $businessId,
                'id_column_used' => $idColumn,
                'subscriptions_deleted' => $this->deletedRecords['subscriptions'] ?? 0,
                'payments_deleted' => $this->deletedRecords['subscription_payments'] ?? 0
            ]);
        }
    }
    
    /**
     * Check if a column exists in a table (delegates to central DbSchema cache).
     */
    private function hasColumn(string $table, string $column): bool {
        return \App\Core\DbSchema::hasColumn($table, $column);
    }
    
    /**
     * Delete the tenant's row from `businesses` (the extended tenant profile).
     * The `customers` table is the canonical tenant registry; `businesses`
     * holds the Plesk/infrastructure profile and shares the same tenant_id.
     */
    private function deleteFromBusinessesTable(string $businessId): void {
        try {
            $checkTable = $this->db->query("SHOW TABLES LIKE 'businesses'");
            if ($checkTable->rowCount() === 0) {
                return;
            }
            $col = $this->hasColumn('businesses', 'tenant_id') ? 'tenant_id' : 'business_id';
            $stmt = $this->db->prepare("DELETE FROM businesses WHERE {$col} = ?");
            $stmt->execute([$businessId]);
            $this->deletedRecords['businesses'] = $stmt->rowCount();
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::info("Deleted business profile", [
                    'tenant_id' => $businessId,
                    'deleted_count' => $this->deletedRecords['businesses']
                ]);
            }
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::warning('Could not delete from businesses table', [
                    'tenant_id' => $businessId,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }
    
    /**
     * Delete from customers table
     * SECURITY: Only deletes the exact customer_id record
     */
    private function deleteFromCustomersTable(string $businessId): void {
        // CRITICAL: Only delete the exact customer_id (no wildcards, no ranges)
        $stmt = $this->db->prepare("DELETE FROM customers WHERE customer_id = ?");
        $stmt->execute([$businessId]);
        $this->deletedRecords['customers'] = $stmt->rowCount();
        
        // SECURITY: Verify only one record was deleted
        if ($this->deletedRecords['customers'] > 1) {
            throw new \Exception("Security violation: More than one customer record deleted!");
        }
        
        if (class_exists('\App\Core\Logger')) {
            \App\Core\Logger::info("Deleted customer record", [
                'business_id' => $businessId,
                'deleted_count' => $this->deletedRecords['customers']
            ]);
        }
    }
    
    /**
     * Delete from subdomains table
     * SECURITY: Only deletes subdomains for this specific business_id
     */
    private function deleteFromSubdomainsTable(string $businessId): void {
        // subdomains was migrated to tenant_id; legacy fallback kept for safety.
        $col = $this->hasColumn('subdomains', 'tenant_id') ? 'tenant_id' : 'business_id';
        $stmt = $this->db->prepare("DELETE FROM subdomains WHERE {$col} = ?");
        $stmt->execute([$businessId]);
        $this->deletedRecords['subdomains'] = $stmt->rowCount();
        
        if (class_exists('\App\Core\Logger')) {
            \App\Core\Logger::info("Deleted subdomain records", [
                'business_id' => $businessId,
                'deleted_count' => $this->deletedRecords['subdomains']
            ]);
        }
    }
    
    /**
     * Delete Plesk subdomain
     * SECURITY: Only deletes the specific subdomain provided
     */
    private function deletePleskSubdomain(string $subdomain): bool {
        try {
            // SECURITY: Validate subdomain format before deletion
            if (empty($subdomain) || !preg_match('/^[a-z0-9-]+$/', $subdomain)) {
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::error('Invalid subdomain format for Plesk deletion', [
                        'subdomain' => $subdomain
                    ]);
                }
                return false;
            }
            
            require_once __DIR__ . '/PleskService.php';
            $pleskService = new \App\Services\PleskService();
            $result = $pleskService->deleteSubdomain($subdomain);
            
            if (class_exists('\App\Core\Logger')) {
                if ($result['success'] ?? false) {
                    \App\Core\Logger::info('Plesk subdomain deleted successfully', [
                        'subdomain' => $subdomain
                    ]);
                } else {
                    \App\Core\Logger::warning('Plesk subdomain deletion failed', [
                        'subdomain' => $subdomain,
                        'result' => $result
                    ]);
                }
            }
            
            return $result['success'] ?? false;
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('Plesk subdomain deletion exception', [
                    'subdomain' => $subdomain,
                    'error' => $e->getMessage()
                ]);
            }
            return false;
        }
    }
    
    /**
     * Delete subdomain folder
     * SECURITY: Only deletes the specific subdomain folder
     */
    private function deleteSubdomainFolder(string $subdomain): bool {
        try {
            // SECURITY: Validate subdomain format to prevent path traversal
            if (empty($subdomain) || !preg_match('/^[a-z0-9-]+$/', $subdomain)) {
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::error('Invalid subdomain format for folder deletion', [
                        'subdomain' => $subdomain
                    ]);
                }
                return false;
            }
            
            $baseDir = '/var/www/vhosts/qordy.com';
            $folderPath = $baseDir . '/' . $subdomain . '.qordy.com';
            
            // SECURITY: Verify path is within base directory (prevent directory traversal)
            $realBaseDir = realpath($baseDir);
            $realFolderPath = realpath($folderPath);
            
            if ($realFolderPath && $realBaseDir && strpos($realFolderPath, $realBaseDir) !== 0) {
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::error('Security: Folder path outside base directory', [
                        'subdomain' => $subdomain,
                        'folder_path' => $folderPath,
                        'real_path' => $realFolderPath,
                        'base_dir' => $realBaseDir
                    ]);
                }
                return false;
            }
            
            if (is_dir($folderPath)) {
                // Recursive delete
                $this->deleteDirectory($folderPath);
                $deleted = !is_dir($folderPath);
                
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::info('Subdomain folder deletion', [
                        'subdomain' => $subdomain,
                        'folder_path' => $folderPath,
                        'success' => $deleted
                    ]);
                }
                
                return $deleted;
            }
            
            return true; // Already doesn't exist
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('Folder deletion failed', [
                    'subdomain' => $subdomain,
                    'error' => $e->getMessage()
                ]);
            }
            return false;
        }
    }
    
    /**
     * Recursively delete directory
     */
    private function deleteDirectory(string $dir): void {
        if (!is_dir($dir)) return;
        
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
