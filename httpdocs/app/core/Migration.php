<?php
namespace App\Core;

/**
 * Base Migration Class
 * 
 * All database migrations should extend this class
 */
abstract class Migration {
    protected $db;
    
    public function __construct() {
        $this->db = DependencyFactory::getDatabase();
    }
    
    /**
     * Run the migration
     * 
     * @return bool
     */
    abstract public function up(): bool;
    
    /**
     * Reverse the migration
     * 
     * @return bool
     */
    public function down(): bool {
        // Optional - override in child classes if needed
        return true;
    }
    
    /**
     * Execute a SQL query
     * 
     * @param string $sql
     * @param array $params
     * @return bool
     */
    protected function execute(string $sql, array $params = []): bool {
        try {
            if (empty($params)) {
                return $this->db->exec($sql) !== false;
            } else {
                $stmt = $this->db->prepare($sql);
                return $stmt->execute($params);
            }
        } catch (\PDOException $e) {
            $errorCode = $e->getCode();
            $errorMessage = $e->getMessage();
            
            // Handle duplicate column/index errors gracefully
            if (strpos($errorMessage, 'Duplicate column') !== false || 
                strpos($errorMessage, 'Duplicate key') !== false ||
                $errorCode === '42S21' || // Duplicate column name
                $errorCode === 1061) {     // Duplicate key name
                // Log as warning instead of error since this is often expected
                error_log("Migration SQL Warning (ignored): " . $errorMessage);
                error_log("SQL: " . $sql);
                return true; // Return true since column/index already exists
            }
            
            // Handle other errors normally
            error_log("Migration SQL Error: " . $errorMessage);
            error_log("SQL: " . $sql);
            return false;
        } catch (\Exception $e) {
            error_log("Migration SQL Error: " . $e->getMessage());
            error_log("SQL: " . $sql);
            return false;
        }
    }
    
    /**
     * Check if a table exists
     * 
     * @param string $tableName
     * @return bool
     */
    protected function tableExists(string $tableName): bool {
        try {
            $stmt = $this->db->query("SHOW TABLES LIKE '{$tableName}'");
            return $stmt->rowCount() > 0;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Check if a column exists in a table
     * 
     * @param string $tableName
     * @param string $columnName
     * @return bool
     */
    protected function columnExists(string $tableName, string $columnName): bool {
        // Force-refresh the cache (migrations alter schema, so we must not
        // trust a stale snapshot captured earlier in the request).
        \App\Core\DbSchema::forget($tableName);
        return \App\Core\DbSchema::hasColumn($tableName, $columnName);
    }
    
    /**
     * Check if an index exists in a table
     * 
     * @param string $tableName
     * @param string $indexName
     * @return bool
     */
    protected function indexExists(string $tableName, string $indexName): bool {
        try {
            $stmt = $this->db->query("SHOW INDEX FROM `{$tableName}` WHERE Key_name = '{$indexName}'");
            return $stmt->rowCount() > 0;
        } catch (\Exception $e) {
            return false;
        }
    }
}
