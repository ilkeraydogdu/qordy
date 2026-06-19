<?php
namespace App\Core;

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/QueryBuilder.php';

class Model {
    protected $db;
    protected $table;
    
    /**
     * Constructor with optional database injection
     * If no database provided, uses DependencyFactory (DI)
     * @param \PDO|null $db Optional database connection
     */
    public function __construct(?\PDO $db = null) {
        if ($db !== null) {
            $this->db = $db;
        } else {
            // Use DependencyFactory for DI
            try {
                require_once __DIR__ . '/DependencyFactory.php';
                $this->db = \App\Core\DependencyFactory::getDatabase();
            } catch (\Exception $e) {
                // Fallback to direct instantiation if DependencyFactory fails
                $database = new \App\Config\Database();
                $this->db = $database->connect();
            }
        }
    }
    
    protected function query(): QueryBuilder {
        $queryBuilder = new QueryBuilder($this->db);
        if ($this->table) {
            $queryBuilder->from($this->table);
        }
        return $queryBuilder;
    }
    
    protected function rawQuery($sql, $params = []) {
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (\PDOException $e) {
            // Use Logger instead of error_log
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error("Database error: " . $e->getMessage(), [
                    'sql' => $sql,
                    'params' => $params,
                    'file' => __FILE__,
                    'line' => __LINE__
                ]);
            } else {
                // Fallback to error_log if Logger is not available
                error_log("Database error: " . $e->getMessage());
            }
            return false;
        }
    }
    
    protected function fetchAll($sql, $params = []) {
        $stmt = $this->rawQuery($sql, $params);
        return $stmt ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : [];
    }
    
    protected function fetch($sql, $params = []) {
        $stmt = $this->rawQuery($sql, $params);
        return $stmt ? $stmt->fetch(\PDO::FETCH_ASSOC) : false;
    }
    
    protected function insert($table, $data) {
        $keys = array_keys($data);
        $fields = implode(',', $keys);
        $placeholders = ':' . implode(', :', $keys);
        
        $sql = "INSERT INTO {$table} ({$fields}) VALUES ({$placeholders})";
        $stmt = $this->rawQuery($sql, $data);
        if ($stmt) {
            return $this->db->lastInsertId();
        }
        return false;
    }
    
    protected function update($table, $data, $where, $whereParams = []) {
        $set = [];
        foreach ($data as $key => $value) {
            $set[] = "{$key} = :{$key}";
        }
        $setClause = implode(', ', $set);
        
        $sql = "UPDATE {$table} SET {$setClause} WHERE {$where}";
        $params = array_merge($data, $whereParams);
        
        $stmt = $this->rawQuery($sql, $params);
        return $stmt ? $stmt->rowCount() : false;
    }
    
    protected function delete($table, $where, $params = []) {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        $stmt = $this->rawQuery($sql, $params);
        return $stmt ? $stmt->rowCount() : false;
    }
}