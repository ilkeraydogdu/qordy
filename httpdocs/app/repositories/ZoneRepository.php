<?php
namespace App\Repositories;

use App\Core\BaseRepository;

class ZoneRepository extends BaseRepository {
    protected $table = 'zones';
    protected $primaryKey = 'zone_id';

    public function __construct($database) {
        parent::__construct($database);
    }

    public function getAll(): array {
        $sql = "SELECT * FROM {$this->table}";
        $params = [];
        
        // Add tenant filter
        $sql = $this->addTenantToWhere($sql, $params);
        $sql .= " ORDER BY name ASC";
        
        return $this->fetchAll($sql, $params);
    }

    public function getById(string $zoneId): ?array {
        $sql = "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = :zone_id";
        $params = ['zone_id' => $zoneId];
        
        // Add tenant filter
        $sql = $this->addTenantToWhere($sql, $params);
        $sql .= " LIMIT 1";
        
        return $this->fetchOne($sql, $params);
    }

    public function getByName(string $name): ?array {
        $sql = "SELECT * FROM {$this->table} WHERE name = :name";
        $params = ['name' => $name];
        
        // Add tenant filter
        $sql = $this->addTenantToWhere($sql, $params);
        $sql .= " LIMIT 1";
        
        return $this->fetchOne($sql, $params);
    }

    public function getWithTableCount(): array {
        // Get tenant filter - zones table now has tenant_id column
        $zoneFilter = $this->getTenantFilter();
        $params = [];
        $whereConditions = [];
        
        // Filter zones by tenant_id (zones table now has tenant_id column)
        if (!empty($zoneFilter['where'])) {
            $whereConditions[] = "z." . $zoneFilter['where'];
            $params = array_merge($params, $zoneFilter['params']);
        }
        
        // Get tenant ID for counting tables
        $tenantId = \App\Core\TenantResolver::resolve();
        if (!$tenantId && class_exists('\App\Core\TenantContext')) {
            $tenantId = \App\Core\TenantContext::getId();
        }
        
        $whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";
        
        // Use LEFT JOIN so zones without tables are still shown (with table_count = 0)
        // Count only tables that belong to current tenant using CASE in COUNT
        if ($tenantId) {
            $sql = "SELECT z.*, 
                    COUNT(CASE WHEN t.tenant_id = :table_tenant_id THEN 1 END) as table_count 
                    FROM {$this->table} z 
                    LEFT JOIN tables t ON t.zone_id = z.zone_id 
                    {$whereClause}
                    GROUP BY z.zone_id 
                    ORDER BY z.floor, z.name";
            $params['table_tenant_id'] = $tenantId;
        } else {
            // No tenant ID - count all tables (for super admin or when tenant context is not set)
            $sql = "SELECT z.*, 
                    COUNT(t.table_id) as table_count 
                    FROM {$this->table} z 
                    LEFT JOIN tables t ON t.zone_id = z.zone_id 
                    {$whereClause}
                    GROUP BY z.zone_id 
                    ORDER BY z.floor, z.name";
        }
        
        return $this->fetchAll($sql, $params);
    }
    
    public function getByFloor(string $floor): array {
        $sql = "SELECT * FROM {$this->table} WHERE floor = :floor";
        $params = ['floor' => $floor];
        
        // Add tenant filter
        $sql = $this->addTenantToWhere($sql, $params);
        $sql .= " ORDER BY name ASC";
        
        return $this->fetchAll($sql, $params);
    }
    
    public function getAllFloors(): array {
        $sql = "SELECT DISTINCT floor FROM {$this->table} WHERE floor IS NOT NULL AND floor != ''";
        $params = [];
        
        // Add tenant filter
        $sql = $this->addTenantToWhere($sql, $params);
        $sql .= " ORDER BY floor ASC";
        
        $results = $this->fetchAll($sql, $params);
        return array_column($results, 'floor');
    }
}

