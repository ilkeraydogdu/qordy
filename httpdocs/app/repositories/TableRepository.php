<?php
namespace App\Repositories;

use App\Core\BaseRepository;

/**
 * Table Repository
 * Handles database operations for tables
 * 
 * @package App\Repositories
 */
class TableRepository extends BaseRepository {
    protected $table = 'tables';
    protected $primaryKey = 'table_id';

    /**
     * Constructor
     * @param \PDO $database Database connection
     */
    public function __construct($database) {
        parent::__construct($database);
    }

    /**
     * Get tables by status
     * @param string $status Table status (FREE, OCCUPIED, RESERVED)
     * @return array Tables
     */
    public function getByStatus(string $status): array {
        $sql = "SELECT * FROM {$this->table} WHERE status = :status";
        $params = ['status' => $status];
        
        // CRITICAL: Add tenant filter using BaseRepository's smart column detection
        $sql = $this->addTenantToWhere($sql, $params);
        $sql .= " ORDER BY name + 0, name";
        
        return $this->fetchAll($sql, $params);
    }

    /**
     * Get tables by zone (backward compatibility)
     * @param string $zone Zone name
     * @return array Tables
     */
    public function getByZone(string $zone): array {
        $sql = "SELECT * FROM {$this->table} WHERE zone = :zone";
        $params = ['zone' => $zone];
        
        // CRITICAL: Add tenant filter
        $sql = $this->addTenantToWhere($sql, $params);
        $sql .= " ORDER BY name + 0, name";
        
        return $this->fetchAll($sql, $params);
    }

    /**
     * Get tables by zone_id
     * @param string $zoneId Zone ID
     * @return array Tables
     */
    public function getByZoneId(string $zoneId): array {
        $sql = "SELECT * FROM {$this->table} WHERE zone_id = :zone_id";
        $params = ['zone_id' => $zoneId];
        
        // CRITICAL: Add tenant filter using BaseRepository's smart column detection
        $sql = $this->addTenantToWhere($sql, $params);
        $sql .= " ORDER BY floor, section, name + 0, name";
        
        return $this->fetchAll($sql, $params);
    }

    /**
     * Get tables by floor
     * @param string $floor Floor name
     * @return array Tables
     */
    public function getByFloor(string $floor): array {
        $sql = "SELECT * FROM {$this->table} WHERE floor = :floor";
        $params = ['floor' => $floor];
        
        // CRITICAL: Add tenant filter
        $sql = $this->addTenantToWhere($sql, $params);
        $sql .= " ORDER BY section, name + 0, name";
        
        return $this->fetchAll($sql, $params);
    }

    /**
     * Get tables by section
     * @param string $section Section name
     * @return array Tables
     */
    public function getBySection(string $section): array {
        $sql = "SELECT * FROM {$this->table} WHERE section = :section";
        $params = ['section' => $section];
        
        // CRITICAL: Add tenant filter
        $sql = $this->addTenantToWhere($sql, $params);
        $sql .= " ORDER BY name + 0, name";
        
        return $this->fetchAll($sql, $params);
    }
    
    /**
     * Get all tables with natural sorting (bahçe1, bahçe2, bahçe3)
     * Override base findAll to add natural sort by name
     * CRITICAL: Always applies tenant filter to ensure data isolation
     * @param array $criteria Optional criteria
     * @return array Tables
     */
    public function findAll(array $criteria = []): array {
        // CRITICAL: Apply tenant scope using BaseRepository's smart column detection
        // This automatically handles business_id OR tenant_id based on table structure
        $criteria = $this->applyTenantScope($criteria);
        
        $sql = "SELECT * FROM {$this->table}";
        $params = [];
        
        $conditions = [];
        
        // Add criteria conditions
        if (!empty($criteria)) {
            foreach ($criteria as $field => $value) {
                if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $field)) {
                    $conditions[] = "{$field} = :{$field}";
                    $params[$field] = $value;
                }
            }
        }
        
        // CRITICAL: Always add tenant filter to SQL (not just to criteria)
        // This ensures tenant isolation even when criteria is empty
        $tenantFilter = $this->getTenantFilter();
        if (!empty($tenantFilter['where'])) {
            $conditions[] = $tenantFilter['where'];
            $params = array_merge($params, $tenantFilter['params']);
        }
        
        // Add WHERE clause if we have any conditions
        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }
        
        // Natural sort: numbers in names are sorted numerically (bahçe1, bahçe2, bahçe10)
        // ORDER BY name + 0 sorts numerically, then alphabetically for non-numeric parts
        $sql .= " ORDER BY zone_id, name + 0, name";
        
        return $this->fetchAll($sql, $params);
    }
    
    /**
     * Get tenant ID from session or TenantContext
     * @return string|null
     */
    private function getTenantId(): ?string {
        // Try session first
        $tenantId = \App\Core\TenantResolver::resolve();
        
        // If not in session, try TenantContext
        if (!$tenantId && class_exists('\App\Core\TenantContext')) {
            try {
                $tenantId = \App\Core\TenantContext::getId();
            } catch (\Exception $e) {
                // TenantContext not set, continue
            }
        }
        
        return $tenantId;
    }

    /**
     * Find table by ID without tenant filter (for bootstrap when setting tenant from table)
     * Use only when TenantContext is not set - security: caller must verify user has access
     * @param string $tableId Table ID
     * @return array|null Table data or null
     */
    public function findByIdUnscoped(string $tableId): ?array {
        $sql = "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = :id LIMIT 1";
        return $this->fetchOne($sql, ['id' => $tableId]);
    }

    /**
     * Update table status
     * @param string $tableId Table ID
     * @param string $status New status
     * @return bool Success
     */
    public function updateStatus(string $tableId, string $status): bool {
        $sql = "UPDATE {$this->table} SET status = :status WHERE {$this->primaryKey} = :table_id";
        return $this->execute($sql, [
            'table_id' => $tableId,
            'status' => $status
        ]);
    }

    /**
     * Get available tables
     * @return array Available tables
     */
    public function getAvailableTables(): array {
        return $this->getByStatus('FREE');
    }

    /**
     * Get occupied tables
     * @return array Occupied tables
     */
    public function getOccupiedTables(): array {
        return $this->getByStatus('OCCUPIED');
    }
    
    /**
     * Get table by unique slug
     * @param string $uniqueSlug Unique slug
     * @return array|null Table data or null
     */
    public function getByUniqueSlug(string $uniqueSlug): ?array {
        $sql = "SELECT * FROM {$this->table} WHERE unique_slug = :unique_slug";
        $params = ['unique_slug' => $uniqueSlug];
        
        // CRITICAL: Add tenant filter for security
        $sql = $this->addTenantToWhere($sql, $params);
        $sql .= " LIMIT 1";
        
        return $this->fetchOne($sql, $params);
    }
    
    /**
     * Load zone relationship for tables (batch loading)
     * @param array $tables Tables data
     * @return array Tables with zones
     */
    protected function loadRelationForMany(array $tables, string $relation): array {
        if ($relation === 'zone') {
            return $this->loadZonesForTables($tables);
        }
        return parent::loadRelationForMany($tables, $relation);
    }
    
    /**
     * Load zones for multiple tables (batch loading)
     * @param array $tables Tables data
     * @return array Tables with zones
     */
    private function loadZonesForTables(array $tables): array {
        if (empty($tables)) {
            return $tables;
        }
        
        // Get unique zone IDs
        $zoneIds = array_unique(array_filter(array_column($tables, 'zone_id')));
        
        if (empty($zoneIds)) {
            return $tables;
        }
        
        // Load all zones in one query
        $placeholders = implode(',', array_fill(0, count($zoneIds), '?'));
        $sql = "SELECT * FROM zones WHERE zone_id IN ({$placeholders})";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($zoneIds);
        $zones = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // Index zones by ID
        $zonesById = [];
        foreach ($zones as $zone) {
            $zonesById[$zone['zone_id']] = $zone;
        }
        
        // Attach zones to tables
        foreach ($tables as &$table) {
            $zoneId = $table['zone_id'] ?? null;
            if ($zoneId && isset($zonesById[$zoneId])) {
                $table['zone'] = $zonesById[$zoneId];
            }
        }
        
        return $tables;
    }
}

