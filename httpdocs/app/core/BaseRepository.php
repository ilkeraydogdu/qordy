<?php
namespace App\Core;

use App\Interfaces\RepositoryInterface;

abstract class BaseRepository implements RepositoryInterface {
    protected $db;
    protected $table;
    protected $primaryKey = 'id';
    
    /**
     * Static cache for table column information to avoid SHOW COLUMNS on every insert
     * Key: table_name, Value: ['columns' => [...], 'columnInfoMap' => [...], 'timestamp' => ...]
     */
    private static $columnCache = [];
    private static $columnCacheTimeout = 3600; // 1 hour cache

    public function __construct($database) {
        $this->db = $database;
        $this->withRelations = [];
    }

    public function create(array $data) {
        if (empty($data)) {
            return false;
        }

        // Canonical tenant stamping.
        // Historically callers passed 'business_id', 'customer_id' or
        // 'tenant_id' interchangeably. After the tenant-id canonicalization
        // refactor every operational table uses `tenant_id`. Normalize here
        // so no caller can accidentally lose the tenant scope by picking the
        // wrong key name.
        if (!in_array($this->table, \App\Core\TenantResolver::getExcludedTables(), true)) {
            $existingTenantId = \App\Core\TenantResolver::fromArray($data);
            if ($existingTenantId !== null) {
                $data['tenant_id'] = $existingTenantId;
            } elseif (!isset($data['tenant_id']) && !isset($data['business_id'])) {
                $data = $this->applyTenantScope($data);
            }
        }

        if ($this->table === 'packages' && isset($data['tenant_id'])) {
            unset($data['tenant_id']);
        }
        
        if ($this->table === 'payment_transactions') {
            $excludedFields = ['business_id', 'tenant_id', 'status', 'session_id', 'businessId', 'sessionId', 'order_id', 'payment_method', 'external_reference'];
            foreach ($excludedFields as $field) {
                if (isset($data[$field])) {
                    unset($data[$field]);
                }
            }
            
            // Map payment_method to method if exists
            if (isset($data['payment_method']) && !isset($data['method'])) {
                $data['method'] = $data['payment_method'];
                unset($data['payment_method']);
            }
            
            // Map external_reference to external_transaction_id if exists
            if (isset($data['external_reference']) && !isset($data['external_transaction_id'])) {
                $data['external_transaction_id'] = $data['external_reference'];
                unset($data['external_reference']);
            }
        }
        
        // Special handling for receipts table - remove invalid fields
        if ($this->table === 'receipts') {
            $excludedFields = ['business_id', 'printer_id', 'print_status', 'print_attempts', 'businessId', 'printerId', 'printStatus', 'printAttempts'];
            foreach ($excludedFields as $field) {
                if (isset($data[$field])) {
                    unset($data[$field]);
                }
            }
        }
        
        // Special handling for order_items table - ensure variant_id is null if empty
        if ($this->table === 'order_items') {
            if (isset($data['variant_id']) && empty($data['variant_id'])) {
                $data['variant_id'] = null;
            }
            // Ensure note is string, not null
            if (isset($data['note']) && $data['note'] === null) {
                $data['note'] = '';
            }
        }
        
        // Note: Reservations table now has customer_email, status, and special_requests columns
        // No need to remove them anymore
        
        // Sanitize column names to prevent SQL injection
        $columns = [];
        $placeholders = [];
        $values = [];
        
        foreach ($data as $key => $value) {
            // Only allow alphanumeric characters and underscores in column names
            if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $key)) {
                $columns[] = $key;
                $placeholders[] = ":{$key}";
                // Handle NULL values properly for PDO
                if ($value === null) {
                    $values[$key] = null;
                } else {
                    $values[$key] = $value;
                }
            }
        }
        
        if (empty($columns)) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::warning("BaseRepository::create - No valid columns found", [
                    'table' => $this->table,
                    'data_keys' => array_keys($data)
                ]);
            }
            return false;
        }
        
        // Validate that columns exist in table and filter out non-existent columns
        // This prevents SQL errors when inserting data with columns that don't exist
        try {
            // CRITICAL PERFORMANCE FIX: Use cached column information instead of SHOW COLUMNS on every insert
            $columnCacheData = $this->getCachedTableColumns();
            $existingColumns = $columnCacheData['columns'];
            $columnInfoMap = $columnCacheData['columnInfoMap'];
            
            // Filter out columns that don't exist in table
            $validColumns = [];
            $validPlaceholders = [];
            $validValues = [];
            $removedColumns = [];
            $missingRequiredColumns = [];
            
            // Filter out columns that don't exist in table
            $validColumns = [];
            $validPlaceholders = [];
            $validValues = [];
            $removedColumns = [];
            $missingRequiredColumns = [];
            
            foreach ($columns as $index => $column) {
                if (in_array($column, $existingColumns)) {
                    $validColumns[] = $column;
                    $validPlaceholders[] = $placeholders[$index];
                    $validValues[$column] = $values[$column];
                } else {
                    $removedColumns[] = $column;
                    // Log removed columns for debugging (especially preparation_screen_id)
                    if ($column === 'preparation_screen_id' && class_exists('\App\Core\Logger')) {
                        \App\Core\Logger::error("BaseRepository::create - preparation_screen_id column removed (doesn't exist in table)", [
                            'table' => $this->table,
                            'existing_columns' => $existingColumns,
                            'data_keys' => array_keys($data)
                        ]);
                    }
                }
            }
            
            // Log if preparation_screen_id is being inserted
            if (isset($validValues['preparation_screen_id']) && class_exists('\App\Core\Logger')) {
                \App\Core\Logger::info("BaseRepository::create - preparation_screen_id will be inserted", [
                    'table' => $this->table,
                    'preparation_screen_id' => $validValues['preparation_screen_id'],
                    'valid_columns' => $validColumns
                ]);
            }
            
            // Check for required columns (NOT NULL without default) that are missing
            foreach ($columnInfoMap as $colName => $colInfo) {
                $isNull = ($colInfo['Null'] ?? 'YES') === 'YES';
                $hasDefault = isset($colInfo['Default']) && $colInfo['Default'] !== null;
                $isAutoIncrement = strpos($colInfo['Extra'] ?? '', 'auto_increment') !== false;
                
                // Skip auto-increment, created_at, updated_at (handled by DB)
                if ($isAutoIncrement || $colName === 'created_at' || $colName === 'updated_at') {
                    continue;
                }
                
                // If column is NOT NULL and has no default, it must be provided
                if (!$isNull && !$hasDefault && !in_array($colName, $validColumns)) {
                    $missingRequiredColumns[] = $colName;
                }
            }
            
            // Update arrays with filtered data
            $columns = $validColumns;
            $placeholders = $validPlaceholders;
            $values = $validValues;
            
            // Log removed columns for debugging
            if (!empty($removedColumns) && class_exists('\App\Core\Logger')) {
                \App\Core\Logger::warning("BaseRepository::create - Removed non-existent columns", [
                    'table' => $this->table,
                    'removed_columns' => $removedColumns,
                    'existing_columns' => $existingColumns,
                    'incoming_data' => $data // Enhanced logging: logs full dataset before filtering
                ]);
            }
            
            // Log missing required columns AND THROW ERROR
            if (!empty($missingRequiredColumns)) {
                $errorMsg = "Missing required columns: " . implode(', ', $missingRequiredColumns);
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::error("BaseRepository::create - Missing required columns", [
                        'table' => $this->table,
                        'missing_required_columns' => $missingRequiredColumns,
                        'provided_columns' => $validColumns,
                        'all_data_keys' => array_keys($data ?? [])
                    ]);
                }
                // Throw PDOException with specific error message
                throw new \PDOException("Field '" . $missingRequiredColumns[0] . "' doesn't have a default value");
            }
            
            // Check if we have any valid columns left
            if (empty($columns)) {
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::error("BaseRepository::create - No valid columns after filtering", [
                        'table' => $this->table,
                        'original_columns' => array_keys($data),
                        'existing_columns' => $existingColumns,
                        'removed_columns' => $removedColumns
                    ]);
                }
                return false;
            }
        } catch (\Exception $e) {
            // Schema check failed - log but continue (might be table doesn't exist yet)
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::warning("BaseRepository::create - Schema check failed", [
                    'table' => $this->table,
                    'error' => $e->getMessage()
                ]);
            }
            // Continue with original columns if schema check fails
        }
        
        // Final check - ensure we have columns
        if (empty($columns)) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error("BaseRepository::create - No columns to insert", [
                    'table' => $this->table,
                    'data_keys' => array_keys($data)
                ]);
            }
            return false;
        }
        
        $columnsStr = implode(',', $columns);
        $placeholdersStr = implode(', ', $placeholders);
        
        $sql = "INSERT INTO {$this->table} ({$columnsStr}) VALUES ({$placeholdersStr})";
        
        // Log SQL for debugging (only for packages table)
        if ($this->table === 'packages' && class_exists('\App\Core\Logger')) {
            \App\Core\Logger::debug("BaseRepository::create - SQL for packages", [
                'sql' => preg_replace('/:(\w+)/', '?', $sql),
                'columns' => $columns,
                'values' => $values
            ]);
        }
        
        // Performance monitoring
        $startTime = microtime(true);
        
        try {
            $stmt = $this->db->prepare($sql);
            
            // Bind values properly for PDO (handle NULL values and boolean/integer conversions)
            foreach ($values as $key => $value) {
                if ($value === null) {
                    $stmt->bindValue(":{$key}", null, \PDO::PARAM_NULL);
                } elseif (is_bool($value)) {
                    // Convert boolean to integer for database compatibility
                    $stmt->bindValue(":{$key}", $value ? 1 : 0, \PDO::PARAM_INT);
                } elseif ($key === 'requires_password_change' && ($value === '' || $value === '0' || $value === false)) {
                    // Special handling for requires_password_change: empty string or '0' should be 0
                    $stmt->bindValue(":{$key}", 0, \PDO::PARAM_INT);
                } elseif ($key === 'requires_password_change' && ($value === '1' || $value === true || $value === 1)) {
                    // Special handling for requires_password_change: '1' or true should be 1
                    $stmt->bindValue(":{$key}", 1, \PDO::PARAM_INT);
                } else {
                    $stmt->bindValue(":{$key}", $value);
                }
            }
            
            $result = $stmt->execute();
            $executionTime = microtime(true) - $startTime;
            
            if (class_exists('\App\Services\PerformanceMonitor')) {
                \App\Services\PerformanceMonitor::logQuery($sql, $executionTime, $values);
            }
            
            if ($result) {
                // For tables with string primary keys (like package_id, user_id), use the provided ID
                // For tables with auto-increment IDs, use lastInsertId()
                // IMPORTANT: For auto-increment tables, never use provided ID - always use lastInsertId()
                
                $insertId = null;
                
                // Check if primary key was provided in data
                if (isset($data[$this->primaryKey]) && !empty($data[$this->primaryKey])) {
                    // String primary key (like package_id, user_id) - use provided value
                    $insertId = $data[$this->primaryKey];
                } else {
                    // Auto-increment primary key - always use lastInsertId()
                    $insertId = $this->db->lastInsertId();
                }
                
                // Invalidate cache with tag-based invalidation
                try {
                    $cache = $this->getCache();
                    $cache->delete($this->getCacheKey('all'));
                    
                    // Tag-based invalidation for better performance
                    $tags = ["table:{$this->table}"];
                    $tenantFilter = $this->getTenantFilter();
                    if (!empty($tenantFilter['params']['tenant_business_id'])) {
                        $tags[] = "business:{$tenantFilter['params']['tenant_business_id']}";
                    }
                    
                    if (method_exists($cache, 'invalidateByTags')) {
                        $cache->invalidateByTags($tags);
                    } else {
                        // Fallback: delete by pattern
                        $cache->deleteByPattern("{$this->table}:*");
                    }
                    
                    // CRITICAL: Invalidate service-level caches for specific tables
                    $this->invalidateServiceCaches($cache, 'create');
                } catch (\Exception $e) {
                    // Cache error is not critical, log and continue
                    if (class_exists('\App\Core\Logger')) {
                        \App\Core\Logger::warning("BaseRepository::create - Cache error", ['error' => $e->getMessage()]);
                    }
                }
                
                // CRITICAL: Always return the ID, never return true
                // For string primary keys, we must return the ID
                // For auto-increment, return the lastInsertId (even if 0, it means insert succeeded)
                if (!empty($insertId)) {
                    return $insertId;
                }
                
                // If insertId is empty/0 but insert succeeded, try to get it from lastInsertId again
                // Some databases might return 0 for first insert, but we should still verify
                $lastId = $this->db->lastInsertId();
                if (!empty($lastId)) {
                    return $lastId;
                }
                
                // Last resort: if we have primary key in data, return it even if empty check failed
                if (isset($data[$this->primaryKey])) {
                    return $data[$this->primaryKey];
                }
                
                // If all else fails, log warning and return false to indicate failure
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::warning("BaseRepository::create - Could not determine insert ID", [
                        'table' => $this->table,
                        'primary_key' => $this->primaryKey,
                        'data_keys' => array_keys($data)
                    ]);
                }
                return false;
            }
            
            // If execute returned false, check for errors
            $errorInfo = $stmt->errorInfo();
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error("BaseRepository::create - Execute returned false", [
                    'table' => $this->table,
                    'sql_state' => $errorInfo[0] ?? 'unknown',
                    'error_code' => $errorInfo[1] ?? 'unknown',
                    'error_message' => $errorInfo[2] ?? 'unknown',
                    'columns' => $columns,
                    'primary_key' => $this->primaryKey
                ]);
            }
            
            return false;
        } catch (\PDOException $e) {
            $executionTime = microtime(true) - $startTime;
            
            $errorInfo = $e->errorInfo ?? [];
            $sqlState = $errorInfo[0] ?? 'unknown';
            $errorCode = $errorInfo[1] ?? 'unknown';
            $errorMessage = $errorInfo[2] ?? $e->getMessage();
            
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error("BaseRepository::create - PDOException", [
                    'message' => $e->getMessage(),
                    'code' => $e->getCode(),
                    'sql_state' => $sqlState,
                    'error_code' => $errorCode,
                    'error_message' => $errorMessage,
                    'table' => $this->table,
                    'primary_key' => $this->primaryKey,
                    'columns' => $columns,
                    'values' => $values,
                    'sql' => preg_replace('/:(\w+)/', '?', $sql),
                    'data_keys' => array_keys($data ?? [])
                ]);
            } else {
                error_log("BaseRepository::create - PDOException: " . $e->getMessage() . " | SQLState: {$sqlState} | ErrorCode: {$errorCode} | ErrorMessage: {$errorMessage} | Table: {$this->table}, Columns: " . implode(', ', $columns));
            }
            
            // Re-throw to allow upper layers to handle
            throw $e;
        } catch (\Exception $e) {
            $executionTime = microtime(true) - $startTime;
            
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error("BaseRepository::create - Exception", [
                    'message' => $e->getMessage(),
                    'table' => $this->table,
                    'primary_key' => $this->primaryKey,
                    'columns' => $columns,
                    'trace' => $e->getTraceAsString()
                ]);
            } else {
                error_log("BaseRepository::create - Exception: " . $e->getMessage() . " | Table: {$this->table}, Columns: " . implode(', ', $columns));
            }
            
            // Re-throw to allow upper layers to handle
            throw $e;
        }
    }

    public function update(string $id, array $data) {
        if (empty($data)) {
            return false;
        }
        
        // Special handling for payment_transactions table - use centralized mapper
        if ($this->table === 'payment_transactions') {
            require_once __DIR__ . '/DataMapper/PaymentTransactionMapper.php';
            $data = \App\Core\DataMapper\PaymentTransactionMapper::filterAndMap($data);
        }
        
        // Special handling for receipts table - remove invalid fields
        if ($this->table === 'receipts') {
            $excludedFields = ['business_id', 'printer_id', 'print_status', 'print_attempts', 'businessId', 'printerId', 'printStatus', 'printAttempts'];
            foreach ($excludedFields as $field) {
                if (isset($data[$field])) {
                    unset($data[$field]);
                }
            }
        }
        
        // Note: Reservations table now has customer_email, status, and special_requests columns
        // No need to remove them anymore
        
        // Sanitize column names to prevent SQL injection
        $set = [];
        $values = [];
        
        foreach ($data as $key => $value) {
            // Only allow alphanumeric characters and underscores in column names
            if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $key)) {
                // CRITICAL: Include null values in UPDATE (e.g., category_id = NULL)
                // This ensures that fields can be explicitly set to NULL
                $set[] = "{$key} = :{$key}";
                $values[$key] = $value; // Keep null values as-is
            }
        }
        
        if (empty($set)) {
            return false;
        }
        
        $setClause = implode(', ', $set);
        $values[$this->primaryKey] = $id;
        
        // CRITICAL: Add business_id filter for tenant isolation in updates
        $sql = "UPDATE {$this->table} SET {$setClause} WHERE {$this->primaryKey} = :{$this->primaryKey}";
        
        // Add tenant filter using new helper method
        $tenantFilter = $this->getTenantFilter();
        if (!empty($tenantFilter['where'])) {
            $sql .= " AND " . $tenantFilter['where'];
            $values = array_merge($values, $tenantFilter['params']);
        }
        
        // Log SQL query for debugging (without sensitive data)
        $logMessage = "BaseRepository::update - Table: {$this->table}, PrimaryKey: {$this->primaryKey}, ID: {$id}";
        \App\Core\Logger::debug($logMessage, [
            'sql' => preg_replace('/:(\w+)/', '?', $sql),
            'set_clause' => $setClause,
            'has_category_id' => isset($values['category_id']),
            'category_id_value' => $values['category_id'] ?? 'not_in_update',
            'category_id_is_null' => isset($values['category_id']) && $values['category_id'] === null,
            'tenant_filter' => $tenantFilter['where'] ?? 'none',
            'all_keys' => array_keys($values)
        ]);
        
        try {
            $stmt = $this->db->prepare($sql);
            
            // Log parameter values
            \App\Core\Logger::debug("BaseRepository::update - Parameters", [
                'params' => array_keys($values),
                'values' => array_map(function($v) {
                    return $v === null ? 'NULL' : (is_string($v) && strlen($v) > 100 ? substr($v, 0, 100) . '...' : $v);
                }, $values)
            ]);
            
            // Bind values properly for PDO (handle NULL values)
            foreach ($values as $key => $value) {
                if ($value === null) {
                    $stmt->bindValue(":{$key}", null, \PDO::PARAM_NULL);
                } else {
                    $stmt->bindValue(":{$key}", $value);
                }
            }
            
            $result = $stmt->execute();
            
            // Check if any rows were actually updated
            $rowsAffected = $stmt->rowCount();
            
            // Log result with detailed info
            \App\Core\Logger::debug("BaseRepository::update - Result", [
                'success' => $result,
                'rows_affected' => $rowsAffected,
                'table' => $this->table,
                'primary_key' => $this->primaryKey,
                'id' => $id,
                'updated_fields' => array_keys($values),
                'category_id_in_update' => isset($values['category_id']) ? $values['category_id'] : 'not_in_update'
            ]);
            
            if ($result && $rowsAffected > 0) {
                // Invalidate cache with tag-based invalidation
                try {
                    $cache = $this->getCache();
                    $cache->delete($this->getCacheKey("id:{$id}"));
                    $cache->delete($this->getCacheKey('all'));
                    
                    // Tag-based invalidation for better performance
                    $tags = ["table:{$this->table}"];
                    $tenantFilter = $this->getTenantFilter();
                    if (!empty($tenantFilter['params']['tenant_business_id'])) {
                        $tags[] = "business:{$tenantFilter['params']['tenant_business_id']}";
                    }
                    
                    if (method_exists($cache, 'invalidateByTags')) {
                        $cache->invalidateByTags($tags);
                    } else {
                        // Fallback: delete by pattern
                        $cache->deleteByPattern("{$this->table}:*");
                    }
                    
                    // CRITICAL: Invalidate service-level caches for specific tables
                    $this->invalidateServiceCaches($cache, 'update');
                } catch (\Exception $e) {
                    // Cache error is not critical, log and continue
                    \App\Core\Logger::warning("BaseRepository::update - Cache error", ['error' => $e->getMessage()]);
                }
                return true;
            }
            
            // If no rows were affected, check if record exists
            if ($rowsAffected === 0) {
                $logMsg = "BaseRepository::update - No rows affected for id: {$id} in table: {$this->table}";
                \App\Core\Logger::debug($logMsg, [
                    'id' => $id,
                    'table' => $this->table,
                    'updated_fields' => array_keys($values),
                    'category_id_in_update' => isset($values['category_id']) ? $values['category_id'] : 'not_in_update',
                    'sql' => preg_replace('/:(\w+)/', '?', $sql)
                ]);
                
                // Check if record exists
                $existing = $this->findById($id);
                if (!$existing) {
                    \App\Core\Logger::warning("BaseRepository::update - Record not found", ['id' => $id, 'table' => $this->table]);
                    return false;
                } else {
                    // Record exists but no changes detected - compare values to see what changed
                    $changes = [];
                    foreach ($values as $key => $newValue) {
                        if ($key === $this->primaryKey) continue; // Skip primary key
                        $oldValue = $existing[$key] ?? null;
                        if ($oldValue != $newValue) {
                            $changes[$key] = [
                                'old' => $oldValue,
                                'new' => $newValue
                            ];
                        }
                    }
                    
                    if (!empty($changes)) {
                        // There ARE changes but MySQL didn't update (possibly same value or type issue)
                        \App\Core\Logger::warning("BaseRepository::update - Changes detected but no rows affected", [
                            'id' => $id,
                            'table' => $this->table,
                            'changes' => $changes,
                            'category_id_change' => $changes['category_id'] ?? 'no_change'
                        ]);
                } else {
                    // Record exists but no changes detected (data was identical) - this is still a success
                    \App\Core\Logger::debug("BaseRepository::update - No changes detected (data identical)");
                    }
                    
                    // Invalidate cache anyway to ensure fresh data
                    try {
                        $cache = $this->getCache();
                        $cache->delete($this->getCacheKey("id:{$id}"));
                        $cache->delete($this->getCacheKey('all'));
                    } catch (\Exception $e) {
                        \App\Core\Logger::warning("BaseRepository::update - Cache error", ['error' => $e->getMessage()]);
                    }
                    return true; // Data was already up to date, consider it success
                }
            }
            
            return false;
        } catch (\PDOException $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error("BaseRepository::update - PDOException", [
                    'message' => $e->getMessage(),
                    'code' => $e->getCode(),
                    'table' => $this->table,
                    'primary_key' => $this->primaryKey,
                    'id' => $id
                ]);
            } else {
                error_log("BaseRepository::update - PDOException: " . $e->getMessage() . " | SQLState: " . $e->getCode() . " | Table: {$this->table}, PrimaryKey: {$this->primaryKey}, ID: {$id}");
            }
            return false;
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error("BaseRepository::update - Exception", [
                    'message' => $e->getMessage(),
                    'table' => $this->table,
                    'primary_key' => $this->primaryKey,
                    'id' => $id
                ]);
            } else {
                error_log("BaseRepository::update - Exception: " . $e->getMessage() . " | Table: {$this->table}, PrimaryKey: {$this->primaryKey}, ID: {$id}");
            }
            return false;
        }
    }

    public function delete(string $id): bool {
        // Trim and validate ID
        $id = trim($id);
        if (empty($id)) {
            \App\Core\Logger::warning("BaseRepository::delete - Empty ID provided", ['table' => $this->table]);
            return false;
        }
        
        try {
            // Use primaryKey in the WHERE clause and parameter name
            $paramName = $this->primaryKey;
            $sql = "DELETE FROM {$this->table} WHERE {$this->primaryKey} = :{$paramName}";
            $params = [$paramName => $id];
            
            $tenantCriteria = $this->applyTenantScope([]);
            $tenantCol = $this->getTenantColumnName();
            $hasTenantFilter = false;
            if (isset($tenantCriteria[$tenantCol])) {
                $sql .= " AND {$tenantCol} = :tenant_scope_id";
                $params['tenant_scope_id'] = $tenantCriteria[$tenantCol];
                $hasTenantFilter = true;
            }

            \App\Core\Logger::debug("BaseRepository::delete", [
                'table' => $this->table,
                'primary_key' => $this->primaryKey,
                'id' => $id,
                'has_tenant_filter' => $hasTenantFilter,
            ]);
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute($params);
            
            // Check if any rows were actually deleted
            $rowsAffected = $stmt->rowCount();
            
            // Log for debugging
            \App\Core\Logger::debug("BaseRepository::delete - Result", [
                'success' => $result,
                'rows_affected' => $rowsAffected
            ]);
            
            if ($result && $rowsAffected > 0) {
                // Invalidate cache with tag-based invalidation
                try {
                    $cache = $this->getCache();
                    $cache->delete($this->getCacheKey("id:{$id}"));
                    $cache->delete($this->getCacheKey('all'));
                    
                    // Tag-based invalidation for better performance
                    $tags = ["table:{$this->table}"];
                    $tenantFilter = $this->getTenantFilter();
                    if (!empty($tenantFilter['params']['tenant_business_id'])) {
                        $tags[] = "business:{$tenantFilter['params']['tenant_business_id']}";
                    }
                    
                    if (method_exists($cache, 'invalidateByTags')) {
                        $cache->invalidateByTags($tags);
                    } else {
                        // Fallback: delete by pattern
                        $cache->deleteByPattern("{$this->table}:*");
                    }
                    
                    // CRITICAL: Invalidate service-level caches for specific tables
                    $this->invalidateServiceCaches($cache, 'delete');
                } catch (\Exception $e) {
                    // Cache error is not critical, log and continue
                    \App\Core\Logger::warning("BaseRepository::delete - Cache error", ['error' => $e->getMessage()]);
                }
                return true;
            }
            
            // If no rows were affected, the record might not exist
            if ($rowsAffected === 0) {
                \App\Core\Logger::debug("BaseRepository::delete - No rows affected", [
                    'id' => $id,
                    'table' => $this->table
                ]);
                
                // Check if record exists
                $existing = $this->findById($id);
                if (!$existing) {
                    \App\Core\Logger::warning("BaseRepository::delete - Record not found", ['id' => $id, 'table' => $this->table]);
                } else {
                    \App\Core\Logger::warning("BaseRepository::delete - Delete failed (possible constraint)", ['id' => $id, 'table' => $this->table]);
                }
            }
            
            return false;
        } catch (\PDOException $e) {
            \App\Core\Logger::error("BaseRepository::delete - PDOException", [
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'table' => $this->table,
                'primary_key' => $this->primaryKey,
                'id' => $id
            ]);
            return false;
        } catch (\Exception $e) {
            \App\Core\Logger::error("BaseRepository::delete - Exception", [
                'message' => $e->getMessage(),
                'table' => $this->table,
                'primary_key' => $this->primaryKey,
                'id' => $id
            ]);
            return false;
        }
    }

    /**
     * Find multiple records by IDs (batch operation)
     * @param string[] $ids
     * @return array
     */
    public function findByIds(array $ids): array {
        if (empty($ids)) {
            return [];
        }

        // CRITICAL: For real-time tables, bypass cache entirely for fresh data
        $realtimeTables = ['orders', 'tables', 'notifications', 'order_items', 'table_sessions'];
        $isRealtimeTable = in_array($this->table, $realtimeTables);

        $cache = $this->getCache();
        $cachedResults = [];
        $uncachedIds = [];

        // Try to load from cache for each ID (if not real-time table)
        if (!$isRealtimeTable) {
            foreach ($ids as $id) {
                $cacheKey = $this->getCacheKey("id:{$id}");
                if ($cache->has($cacheKey)) {
                    $cachedResult = $cache->get($cacheKey);
                    // Ensure cache returns array or null, never false
                    if ($cachedResult !== false && (!is_array($cachedResult) && $cachedResult !== null)) {
                        $cachedResult = null;
                    }
                    if ($cachedResult !== null) {
                        $cachedResults[$id] = $cachedResult;
                    }
                } else {
                    $uncachedIds[] = $id;
                }
            }
        } else {
            $uncachedIds = $ids;
        }

        // If uncached IDs remain, fetch from database
        $dbResults = [];
        if (!empty($uncachedIds)) {
            $placeholders = implode(',', array_fill(0, count($uncachedIds), '?'));
            $sql = "SELECT * FROM {$this->table} WHERE {$this->primaryKey} IN ({$placeholders})";
            $params = array_map('strval', $uncachedIds);

            // Add tenant filter if applicable
            $tenantFilter = $this->getTenantFilter();
            if (!empty($tenantFilter['where'])) {
                $sql .= " AND " . $tenantFilter['where'];
                // Tenant params use named placeholders (:tenant_filter_id),
                // but $params uses positional (?). We need to switch to named
                // placeholders for the IN clause as well, or handle binding manually.
                // Simplest fix: rebuild with named placeholders for the IN clause.
                $namedParams = [];
                foreach ($uncachedIds as $idx => $uid) {
                    $key = "id_{$idx}";
                    $namedParams[$key] = (string) $uid;
                }
                // Rebuild SQL with named placeholders
                $namedPlaceholders = implode(',', array_map(fn($k) => ":{$k}", array_keys($namedParams)));
                $sql = "SELECT * FROM {$this->table} WHERE {$this->primaryKey} IN ({$namedPlaceholders})";
                $sql .= " AND " . $tenantFilter['where'];
                $params = array_merge($namedParams, $tenantFilter['params']);
            }

            $stmt = $this->db->prepare($sql);
            if ($stmt) {
                $stmt->execute($params);
                $dbResults = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Cache results for non-real-time tables
                if (!$isRealtimeTable) {
                    foreach ($dbResults as $row) {
                        $id = $row[$this->primaryKey];
                        $cacheKey = $this->getCacheKey("id:{$id}");
                        $cache->set($cacheKey, $row, 3600); // Cache for 1 hour
                    }
                }
            }
        }

        // Combine cached and fresh results
        $allResults = [];
        foreach ($ids as $id) {
            if (isset($cachedResults[$id])) {
                $allResults[$id] = $cachedResults[$id];
            } else {
                // Find in dbResults
                $result = array_filter($dbResults, fn($row) => $row[$this->primaryKey] === $id);
                if (!empty($result)) {
                    $allResults[$id] = current($result);
                } else {
                    $allResults[$id] = null;
                }
            }
        }

        return array_values($allResults);
    }

    public function findById(string $id) {
        // CRITICAL: For real-time tables, bypass cache entirely for fresh data
        $realtimeTables = ['orders', 'tables', 'notifications', 'order_items', 'table_sessions'];
        $isRealtimeTable = in_array($this->table, $realtimeTables);
        
        $cacheKey = $this->getCacheKey("id:{$id}");
        $cache = $this->getCache();
        $result = null;
        
        // Only use cache for non-real-time tables
        if (!$isRealtimeTable && $cache->has($cacheKey)) {
            $result = $cache->get($cacheKey);
            // Ensure cache returns array or null, never false
            if ($result === false || (!is_array($result) && $result !== null)) {
                $result = null;
            }
        }
        
        // If not from cache (or real-time table), fetch from database
        if ($result === null) {
            // CRITICAL: Add tenant filter for tenant isolation
            // Use getTenantFilter() which supports both business_id and tenant_id columns
            $sql = "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = :id";
            $params = ['id' => $id];
            
            // Add tenant filter if applicable (skip for excluded tables)
            $tenantFilter = $this->getTenantFilter();
            if (!empty($tenantFilter['where'])) {
                $sql .= " AND " . $tenantFilter['where'];
                $params = array_merge($params, $tenantFilter['params']);
            }
            
            $sql .= " LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            // PDO::fetch() returns false if no row found, convert to null
            if ($result === false) {
                $result = null;
            }
            
            // Ensure we have array or null
            if ($result !== null && !is_array($result)) {
                \App\Core\Logger::warning("BaseRepository::findById - Unexpected result type", [
                    'type' => gettype($result),
                    'id' => $id,
                    'table' => $this->table
                ]);
                $result = null;
            }
            
            // Cache the result (only if found and not a real-time table)
            if ($result !== null && !$isRealtimeTable) {
                $cache->set($cacheKey, $result, 3600); // Cache for 1 hour
            }
        }
        
        // Load relationships if specified
        if ($result !== null && !empty($this->withRelations)) {
            $result = $this->loadRelations($result);
        }
        
        return $result;
    }

    /**
     * Find one record by criteria
     * @param array $criteria Associative array of field => value pairs
     * @return array|null Returns single record or null if not found
     */
    public function findOneBy(array $criteria) {
        if (empty($criteria)) {
            return null;
        }
        
        // CRITICAL: Apply tenant scope for multi-tenant isolation
        $criteria = $this->applyTenantScope($criteria);
        
        $conditions = [];
        $params = [];
        
        foreach ($criteria as $field => $value) {
            // Sanitize field names to prevent SQL injection
            if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $field)) {
                $conditions[] = "{$field} = :{$field}";
                $params[$field] = $value;
            }
        }
        
        if (empty($conditions)) {
            return null;
        }
        
        $where = implode(' AND ', $conditions);
        $sql = "SELECT * FROM {$this->table} WHERE {$where} LIMIT 1";
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            // PDO::fetch() returns false if no row found, convert to null
            return $result === false ? null : $result;
        } catch (\PDOException $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('BaseRepository::findOneBy - Query failed', [
                    'table' => $this->table,
                    'criteria' => $criteria,
                    'error' => $e->getMessage()
                ]);
            }
            return null;
        }
    }

    /**
     * Apply tenant scope to criteria for multi-tenant data isolation.
     * Uses TenantResolver as single source of truth.
     */
    protected function applyTenantScope(array $criteria = []): array {
        if (in_array($this->table, \App\Core\TenantResolver::getExcludedTables(), true)) {
            return $criteria;
        }

        $tenantId = \App\Core\TenantResolver::resolve();

        if (!$tenantId) {
            return $criteria;
        }

        $col = $this->detectTenantColumn();
        if (!$col) {
            return $criteria;
        }

        if (!isset($criteria[$col])) {
            $criteria[$col] = $tenantId;
        }

        return $criteria;
    }

    protected function hasBusinessIdColumn(): bool {
        return $this->hasColumn('business_id');
    }

    /**
     * SQL WHERE clause + params for tenant filtering in raw queries.
     * Returns '1=0' (match nothing) when tenant is required but unknown.
     */
    protected function getTenantFilter(): array {
        if (in_array($this->table, \App\Core\TenantResolver::getExcludedTables(), true)) {
            return ['where' => '', 'params' => []];
        }

        $tenantId = \App\Core\TenantResolver::resolve();

        if (!$tenantId) {
            return ['where' => '1=0', 'params' => []];
        }

        $col = $this->detectTenantColumn();
        if (!$col) {
            return ['where' => '', 'params' => []];
        }

        return [
            'where' => "{$col} = :tenant_filter_id",
            'params' => ['tenant_filter_id' => $tenantId]
        ];
    }

    /**
     * Prefix getTenantFilter() WHERE clause for a SQL table alias (e.g. o, i).
     * When tenant is unknown the filter is `1=0`; prepending `o.` would yield invalid `o.1=0`.
     */
    protected function tenantWhereForAlias(string $alias, string $whereClause): string {
        $whereClause = trim($whereClause);
        if ($whereClause === '1=0') {
            return '(1=0)';
        }
        return $alias . '.' . $whereClause;
    }

    /**
     * Detect which tenant column this table uses (cached via hasColumn).
     */
    protected function detectTenantColumn(): ?string {
        if ($this->hasColumn('tenant_id')) {
            return 'tenant_id';
        }
        if ($this->hasColumn('business_id')) {
            return 'business_id';
        }
        return null;
    }

    public function getTenantColumnName(): string {
        return $this->detectTenantColumn() ?? 'tenant_id';
    }
    
    /**
     * Get cached table columns information
     * Uses static cache to avoid SHOW COLUMNS queries on every insert
     * @return array ['columns' => [...], 'columnInfoMap' => [...]]
     */
    /**
     * Return schema info for the current table via the application-wide
     * DbSchema cache. Everyone (repositories, services, controllers) shares
     * the same in-memory cache — no per-class duplication.
     *
     * @return array{columns: array<int,string>, columnInfoMap: array<string,array<string,mixed>>}
     */
    private function getCachedTableColumns(): array {
        $columns = \App\Core\DbSchema::columns($this->table);
        $columnInfoMap = [];
        foreach ($columns as $col) {
            $meta = \App\Core\DbSchema::columnMeta($this->table, $col);
            if ($meta !== null) {
                $columnInfoMap[$col] = $meta;
            }
        }
        return [
            'columns' => $columns,
            'columnInfoMap' => $columnInfoMap,
        ];
    }

    /**
     * Invalidate column cache for this table.
     * Call this when table structure changes (ALTER TABLE, etc.)
     */
    private function invalidateColumnCache(): void {
        \App\Core\DbSchema::forget($this->table);
        unset(self::$columnCache[$this->table]);
    }
    
    /**
     * Check if table has a specific column (cached for performance)
     * @param string $columnName Column name to check
     * @return bool True if column exists
     */
    /**
     * True when the current repository's table has the given column.
     * Delegates to the application-wide DbSchema cache so every consumer
     * (repositories, services, controllers) shares one introspection cache.
     */
    public function hasColumn(string $columnName): bool {
        return \App\Core\DbSchema::hasColumn($this->table, $columnName);
    }
    
    /**
     * Add tenant filter to existing SQL WHERE clause
     * Automatically handles WHERE keyword and AND logic
     * 
     * Usage in child repositories:
     *   $sql = "SELECT * FROM table WHERE column = :value";
     *   $params = ['value' => 123];
     *   $sql = $this->addTenantToWhere($sql, $params);
     * 
     * @param string $sql SQL query (may or may not have WHERE clause)
     * @param array $params Parameters array (will be updated with tenant params)
     * @return string Modified SQL with tenant filter added
     */
    protected function addTenantToWhere(string $sql, array &$params): string {
        $filter = $this->getTenantFilter();
        
        // If no tenant filter needed, return original SQL
        if (empty($filter['where'])) {
            return $sql;
        }
        
        // Merge tenant params into existing params
        $params = array_merge($params, $filter['params']);
        
        // Check if SQL already has WHERE clause (case-insensitive)
        $hasWhere = stripos($sql, 'WHERE') !== false;
        
        if ($hasWhere) {
            // Add with AND
            // Find position after WHERE clause to insert
            $wherePos = stripos($sql, 'WHERE');
            $afterWhere = substr($sql, $wherePos + 5); // Skip "WHERE"
            
            // Check if there's already a condition after WHERE
            $afterWhere = trim($afterWhere);
            if (!empty($afterWhere) && !preg_match('/^(GROUP|ORDER|LIMIT|HAVING)/i', $afterWhere)) {
                // Add AND before tenant filter
                $sql .= ' AND ' . $filter['where'];
            } else {
                // WHERE exists but no condition yet (edge case)
                $sql .= ' ' . $filter['where'];
            }
        } else {
            // No WHERE clause, add it
            // Find position before GROUP BY, ORDER BY, LIMIT, etc.
            $keywords = ['GROUP BY', 'ORDER BY', 'LIMIT', 'HAVING', 'UNION'];
            $insertPos = strlen($sql);
            
            foreach ($keywords as $keyword) {
                $pos = stripos($sql, $keyword);
                if ($pos !== false && $pos < $insertPos) {
                    $insertPos = $pos;
                }
            }
            
            if ($insertPos < strlen($sql)) {
                // Insert WHERE before the keyword
                $before = substr($sql, 0, $insertPos);
                $after = substr($sql, $insertPos);
                $sql = trim($before) . ' WHERE ' . $filter['where'] . ' ' . $after;
            } else {
                // Append WHERE at the end
                $sql .= ' WHERE ' . $filter['where'];
            }
        }
        
        return $sql;
    }
    
    public function findAll(array $criteria = []): array {
        // Validate table name
        require_once __DIR__ . '/Security/SQLInjectionProtection.php';
        $sanitizedTable = \App\Core\Security\SQLInjectionProtection::sanitizeTableName($this->table);
        if ($sanitizedTable !== $this->table) {
            throw new \InvalidArgumentException("Invalid table name: {$this->table}");
        }
        
        // CRITICAL: Apply tenant scope for multi-tenant data isolation
        $criteria = $this->applyTenantScope($criteria);
        
        // Generate cache key based on criteria
        $cacheKey = $this->getCacheKey('all');
        if (!empty($criteria)) {
            $criteriaHash = md5(json_encode($criteria));
            $cacheKey = $this->getCacheKey("all:{$criteriaHash}");
        }
        
        // Use rememberWithLock to prevent cache stampede and ensure thread safety
        $cache = $this->getCache();
        $tags = ["table:{$this->table}"];
        
        // Add tenant tag if applicable
        $tenantFilter = $this->getTenantFilter();
        if (!empty($tenantFilter['params']['tenant_business_id'])) {
            $tags[] = "business:{$tenantFilter['params']['tenant_business_id']}";
        }
        
        // CRITICAL: For real-time tables, use shorter cache for faster updates
        // Real-time tables: orders, tables, notifications, order_items, table_sessions, users
        // Other tables can use longer cache (30 minutes)
        // PERFORMANCE: Added 'users' to real-time tables to prevent stale data showing deleted users
        $realtimeTables = ['orders', 'tables', 'notifications', 'order_items', 'table_sessions', 'users'];
        $cacheTime = in_array($this->table, $realtimeTables) ? 300 : 1800; // 5 min for real-time, 30 min for others
        
        // PERFORMANCE: For users table, use even shorter cache (2 minutes) to prevent stale data
        if ($this->table === 'users') {
            $cacheTime = 120; // 2 minutes for users (prevents showing deleted users)
        }
        
        // Use rememberWithLock if available (Redis), otherwise fallback to remember
        if (method_exists($cache, 'rememberWithLock')) {
            return $cache->rememberWithLock($cacheKey, function() use ($criteria) {
                return $this->executeFindAllQuery($criteria);
            }, $cacheTime, $tags);
        } else {
            return $cache->remember($cacheKey, function() use ($criteria) {
                return $this->executeFindAllQuery($criteria);
            }, $cacheTime);
        }
    }
    
    /**
     * Execute the actual findAll query (extracted for reuse)
     * @param array $criteria
     * @return array
     */
    private function executeFindAllQuery(array $criteria): array {
        $sql = "SELECT * FROM {$this->table}";
        $params = [];
        
        if (!empty($criteria)) {
            $conditions = [];
            foreach ($criteria as $field => $value) {
                // Sanitize field names to prevent SQL injection
                if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $field)) {
                    $conditions[] = "{$field} = :{$field}";
                    $params[$field] = $value;
                }
            }
            if (!empty($conditions)) {
                $sql .= " WHERE " . implode(' AND ', $conditions);
            }
        }
        
        // Only add ORDER BY if primaryKey is set and is a valid column name
        // Some repositories may override this method or use different ordering
        if (!empty($this->primaryKey) && $this->primaryKey !== 'id') {
            // Check if primaryKey is a valid identifier (alphanumeric and underscore only)
            if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $this->primaryKey)) {
                $sql .= " ORDER BY {$this->primaryKey} DESC";
            }
        } elseif (!empty($this->primaryKey)) {
            $sql .= " ORDER BY {$this->primaryKey} DESC";
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // Load relationships if specified
        if (!empty($this->withRelations)) {
            $results = $this->loadRelationsForMany($results);
        }
        
        return $results;
    }
    
    /**
     * Execute a query and return all results
     */
    protected function fetchAll(string $sql, array $params = []): array {
        try {
            $startTime = microtime(true);
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $executionTime = microtime(true) - $startTime;
            
            // Log query for profiling
            if (class_exists('\App\Services\QueryProfiler')) {
                \App\Services\QueryProfiler::logQuery($sql, $executionTime, $params);
            }
            
            if (class_exists('\App\Services\PerformanceMonitor')) {
                \App\Services\PerformanceMonitor::logQuery($sql, $executionTime, $params);
            }
            
            return $result;
        } catch (\PDOException $e) {
            error_log("BaseRepository::fetchAll PDO Error: " . $e->getMessage() . " | SQLState: " . ($e->errorInfo[0] ?? 'unknown') . " | SQL: " . substr($sql, 0, 500));
            throw $e; // Re-throw to let calling code handle it
        } catch (\Exception $e) {
            error_log("BaseRepository::fetchAll Error: " . $e->getMessage() . " | File: " . $e->getFile() . " | Line: " . $e->getLine());
            throw $e; // Re-throw to let calling code handle it
        }
    }
    
    /**
     * Execute a query and return single result
     */
    protected function fetchOne(string $sql, array $params = []): ?array {
        try {
            $startTime = microtime(true);
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            $executionTime = microtime(true) - $startTime;
            
            // Log query for profiling
            if (class_exists('\App\Services\QueryProfiler')) {
                \App\Services\QueryProfiler::logQuery($sql, $executionTime, $params);
            }
            
            if (class_exists('\App\Services\PerformanceMonitor')) {
                \App\Services\PerformanceMonitor::logQuery($sql, $executionTime, $params);
            }
            
            return $result ?: null;
        } catch (\PDOException $e) {
            error_log("BaseRepository::fetchOne PDO Error: " . $e->getMessage() . " | SQLState: " . ($e->errorInfo[0] ?? 'unknown') . " | SQL: " . substr($sql, 0, 500));
            throw $e; // Re-throw to let calling code handle it
        } catch (\Exception $e) {
            error_log("BaseRepository::fetchOne Error: " . $e->getMessage() . " | File: " . $e->getFile() . " | Line: " . $e->getLine());
            throw $e; // Re-throw to let calling code handle it
        }
    }
    
    /**
     * Execute a query (INSERT, UPDATE, DELETE)
     */
    protected function execute(string $sql, array $params = []): bool {
        try {
            $startTime = microtime(true);
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute($params);
            $executionTime = microtime(true) - $startTime;
            
            if (class_exists('\App\Services\PerformanceMonitor')) {
                \App\Services\PerformanceMonitor::logQuery($sql, $executionTime, $params);
            }
            
            return $result;
        } catch (\PDOException $e) {
            error_log("BaseRepository::execute PDO Error: " . $e->getMessage() . " | SQLState: " . ($e->errorInfo[0] ?? 'unknown') . " | SQL: " . substr($sql, 0, 500));
            throw $e; // Re-throw to let calling code handle it
        } catch (\Exception $e) {
            error_log("BaseRepository::execute Error: " . $e->getMessage() . " | File: " . $e->getFile() . " | Line: " . $e->getLine());
            throw $e; // Re-throw to let calling code handle it
        }
    }

    public function count(array $criteria = []): int {
        $sql = "SELECT COUNT(*) FROM {$this->table}";
        $params = [];
        
        if (!empty($criteria)) {
            $conditions = [];
            foreach ($criteria as $field => $value) {
                // Sanitize field names to prevent SQL injection
                if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $field)) {
                    $conditions[] = "{$field} = :{$field}";
                    $params[$field] = $value;
                }
            }
            if (!empty($conditions)) {
                $sql .= " WHERE " . implode(' AND ', $conditions);
            }
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetchColumn();
        return $result ? (int)$result : 0;
    }

    public function getDbConnection() {
        return $this->db;
    }

    /**
     * Get table name
     * @return string Table name
     */
    public function getTableName(): string {
        return $this->table;
    }
    
    /**
     * Get QueryBuilder instance for this repository
     * @return \App\Core\QueryBuilder QueryBuilder instance
     */
    protected function query(): \App\Core\QueryBuilder {
        require_once __DIR__ . '/QueryBuilder.php';
        $queryBuilder = new \App\Core\QueryBuilder($this->db);
        $queryBuilder->from($this->table);
        return $queryBuilder;
    }
    
    /**
     * Eager loading relationships
     * @var array Relationships to eager load
     */
    protected $withRelations = [];
    
    /**
     * Specify relationships to eager load
     * @param array|string $relations Relationship names
     * @return self
     */
    public function with($relations): self {
        if (is_string($relations)) {
            $relations = [$relations];
        }
        $this->withRelations = array_merge($this->withRelations, $relations);
        return $this;
    }
    
    /**
     * Load relationships for a single record
     * @param array $record Record data
     * @return array Record with loaded relationships
     */
    protected function loadRelations(array $record): array {
        if (empty($this->withRelations) || empty($record)) {
            return $record;
        }
        
        foreach ($this->withRelations as $relation) {
            $record = $this->loadRelation($record, $relation);
        }
        
        return $record;
    }
    
    /**
     * Load relationships for multiple records
     * @param array $records Records data
     * @return array Records with loaded relationships
     */
    protected function loadRelationsForMany(array $records): array {
        if (empty($this->withRelations) || empty($records)) {
            return $records;
        }
        
        // Use batch loading to avoid N+1 queries
        foreach ($this->withRelations as $relation) {
            $records = $this->loadRelationForMany($records, $relation);
        }
        
        return $records;
    }
    
    /**
     * Load a single relationship for a record
     * @param array $record Record data
     * @param string $relation Relationship name
     * @return array Record with loaded relationship
     */
    protected function loadRelation(array $record, string $relation): array {
        // This method should be overridden in child repositories
        // Default implementation does nothing
        return $record;
    }
    
    /**
     * Load a relationship for multiple records (batch loading)
     * @param array $records Records data
     * @param string $relation Relationship name
     * @return array Records with loaded relationship
     */
    protected function loadRelationForMany(array $records, string $relation): array {
        // This method should be overridden in child repositories
        // Default implementation does nothing
        return $records;
    }
    
    /**
     * Reset eager loading relationships
     * @return self
     */
    protected function resetWith(): self {
        $this->withRelations = [];
        return $this;
    }
    
    /**
     * Generate cache key for repository operations
     * @param string $suffix Additional suffix for the cache key
     * @return string Cache key
     */
    protected function getCacheKey(string $suffix = ''): string {
        $key = 'repo:' . strtolower($this->table);
        if (!empty($suffix)) {
            $key .= ':' . $suffix;
        }
        return $key;
    }
    
    /**
     * Get cache service instance
     * @return \App\Services\CacheService Cache service
     */
    protected function getCache(): \App\Interfaces\CacheInterface {
        return \App\Core\DependencyFactory::getCacheService();
    }
    
    /**
     * Public method to clear cache (can be called from services)
     */
    public function clearCache(): void {
        try {
            $cache = $this->getCache();
            if ($cache && method_exists($cache, 'delete')) {
                $cacheKey = $this->getCacheKey('all');
                $cache->delete($cacheKey);
            }
        } catch (\Exception $e) {
            // Silently fail - cache clearing is not critical
        }
    }
    
    /**
     * Multi-Tenant Support Methods
     * ==============================
     */
    
    // Multi-tenant support properties
    protected $isTenantScoped = false;
    protected $tenantColumn = 'tenant_id';
    
    /**
     * Add tenant scope to SQL query
     * Automatically adds WHERE tenant_id = ? condition if tenant is set
     * 
     * @param string $sql The original SQL query
     * @param array &$params Parameters array (will be modified to include tenant_id)
     * @return string Modified SQL with tenant scope
     */
    protected function addTenantScope($sql, &$params = []) {
        // Skip if tenant scoping is disabled for this repository
        if (!$this->isTenantScoped) {
            return $sql;
        }
        
        // Get current tenant ID
        require_once __DIR__ . '/TenantContext.php';
        $tenantId = \App\Core\TenantContext::getId();
        
        // If no tenant is set, return original SQL (allows super admin access)
        if ($tenantId === null) {
            return $sql;
        }
        
        // Add tenant_id condition
        $sql = preg_replace_callback(
            '/\bWHERE\b/i',
            function($matches) use ($tenantId, &$params) {
                $params[] = $tenantId;
                return $matches[0] . " {$this->tenantColumn} = ? AND";
            },
            $sql,
            1 // Only replace first WHERE
        );
        
        // If no WHERE clause exists, add one
        if (stripos($sql, 'WHERE') === false) {
            // Find position to insert WHERE clause (before ORDER BY, LIMIT, etc.)
            $insertBefore = ['ORDER BY', 'LIMIT', 'GROUP BY', 'HAVING'];
            $insertPos = strlen($sql);
            
            foreach ($insertBefore as $keyword) {
                $pos = stripos($sql, $keyword);
                if ($pos !== false && $pos < $insertPos) {
                    $insertPos = $pos;
                }
            }
            
            $params[] = $tenantId;
            $sql = substr($sql, 0, $insertPos) . " WHERE {$this->tenantColumn} = ? " . substr($sql, $insertPos);
        }
        
        return $sql;
    }
    
    /**
     * Enable tenant scoping for this repository
     * Call this in child class constructor to enable automatic tenant filtering
     */
    protected function enableTenantScoping($tenantColumn = 'tenant_id') {
        $this->isTenantScoped = true;
        $this->tenantColumn = $tenantColumn;
    }
    
    /**
     * Invalidate service-level caches for specific tables
     * This ensures that service caches (like MenuItemService, CategoryService) are cleared
     * when repository-level data changes
     * 
     * @param \App\Interfaces\CacheInterface $cache Cache service instance
     * @param string $operation Operation type: 'create', 'update', or 'delete'
     */
    protected function invalidateServiceCaches($cache, string $operation): void {
        try {
            $tenantId = $this->getTenantIdForCache();
            
            // Invalidate menu-related caches when menu_items table changes
            if ($this->table === 'menu_items') {
                // Clear all menu item caches
                if (method_exists($cache, 'deleteByPattern')) {
                    $cache->deleteByPattern('menu:items:*');
                    $cache->deleteByPattern('menu:item:*');
                } else {
                    // Fallback: delete specific keys
                    $cache->delete('menu:items:all:' . $tenantId);
                    $cache->delete('menu:items:available:' . $tenantId);
                    if ($tenantId && $tenantId !== 'global') {
                        $cache->delete('menu:items:business:' . $tenantId);
                    }
                }
                
                // Also invalidate category caches (categories with products)
                if (method_exists($cache, 'deleteByPattern')) {
                    $cache->deleteByPattern('menu:categories:with_products:*');
                } else {
                    $cache->delete('menu:categories:with_products:' . $tenantId . ':tr');
                    $cache->delete('menu:categories:with_products:' . $tenantId . ':en');
                }
                
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::debug("BaseRepository::invalidateServiceCaches - Menu items cache invalidated", [
                        'table' => $this->table,
                        'operation' => $operation,
                        'tenant_id' => $tenantId
                    ]);
                }
            }
            
            // Invalidate category-related caches when categories table changes
            if ($this->table === 'categories') {
                // Clear all category caches
                if (method_exists($cache, 'deleteByPattern')) {
                    $cache->deleteByPattern('menu:categories:*');
                } else {
                    // Fallback: delete specific keys
                    $cache->delete('menu:categories:' . $tenantId . ':tr');
                    $cache->delete('menu:categories:' . $tenantId . ':en');
                    $cache->delete('menu:categories:' . $tenantId . ':tr:tree');
                    $cache->delete('menu:categories:' . $tenantId . ':en:tree');
                    $cache->delete('menu:categories:with_count:' . $tenantId);
                    $cache->delete('menu:categories:with_products:' . $tenantId . ':tr');
                    $cache->delete('menu:categories:with_products:' . $tenantId . ':en');
                    $cache->delete('menu:categories:business:' . $tenantId . ':tr');
                    $cache->delete('menu:categories:business:' . $tenantId . ':en');
                }
                
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::debug("BaseRepository::invalidateServiceCaches - Categories cache invalidated", [
                        'table' => $this->table,
                        'operation' => $operation,
                        'tenant_id' => $tenantId
                    ]);
                }
            }
            
            // CRITICAL: Invalidate user-related caches when users table changes
            if ($this->table === 'users') {
                // Clear all user caches aggressively
                if (method_exists($cache, 'deleteByPattern')) {
                    $cache->deleteByPattern('users:*');
                    $cache->deleteByPattern('user:*');
                } else {
                    // Fallback: delete specific keys
                    $cache->delete('users:all');
                    $cache->delete('users:all:' . $tenantId);
                    $cache->delete('users:business:' . $tenantId);
                }
                
                // Also clear service-level caches
                if (method_exists($cache, 'forget')) {
                    $cache->forget('users:all');
                }
                
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::debug("BaseRepository::invalidateServiceCaches - Users cache invalidated", [
                        'table' => $this->table,
                        'operation' => $operation,
                        'tenant_id' => $tenantId
                    ]);
                }
            }
        } catch (\Exception $e) {
            // Cache invalidation error is not critical, log and continue
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::warning("BaseRepository::invalidateServiceCaches - Error", [
                    'table' => $this->table,
                    'operation' => $operation,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }
    
    /**
     * Get tenant ID for cache operations
     * @return string Tenant ID or 'global' if not set
     */
    private function getTenantIdForCache(): string {
        $tenantId = null;
        if (class_exists('\App\Core\TenantResolver')) {
            try { $tenantId = \App\Core\TenantResolver::resolve(); } catch (\Throwable $e) {}
        }
        return $tenantId ?: 'global';
    }
}