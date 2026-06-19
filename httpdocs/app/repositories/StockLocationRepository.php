<?php
namespace App\Repositories;

use App\Core\BaseRepository;
use PDO;

/**
 * Stock Location Repository
 * Handles database operations for stock locations
 * 
 * @package App\Repositories
 */
class StockLocationRepository extends BaseRepository {
    protected $table = 'stock_locations';
    protected $primaryKey = 'location_id';

    /**
     * Constructor
     * @param \PDO $database Database connection
     */
    public function __construct($database) {
        parent::__construct($database);
    }

    /**
     * Get all active locations
     * @return array Locations
     */
    public function getAll(): array {
        $sql = "SELECT * FROM {$this->table} WHERE is_active = 1";
        $params = [];
        
        // Add tenant filter
        $sql = $this->addTenantToWhere($sql, $params);
        $sql .= " ORDER BY name ASC";
        
        return $this->fetchAll($sql, $params);
    }

    /**
     * Get all locations including inactive
     * @return array Locations
     */
    public function getAllIncludingInactive(): array {
        $sql = "SELECT * FROM {$this->table}";
        $params = [];
        
        // Add tenant filter
        $sql = $this->addTenantToWhere($sql, $params);
        $sql .= " ORDER BY name ASC";
        
        return $this->fetchAll($sql, $params);
    }

    /**
     * Get location by code
     * @param string $code Location code
     * @return array|null Location data or null
     */
    public function getByCode(string $code): ?array {
        $sql = "SELECT * FROM {$this->table} WHERE code = :code";
        $params = ['code' => $code];
        
        // Add tenant filter
        $sql = $this->addTenantToWhere($sql, $params);
        $sql .= " LIMIT 1";
        
        return $this->fetchOne($sql, $params);
    }

    /**
     * Get active locations
     * @return array Active locations
     */
    public function getActive(): array {
        return $this->getAll();
    }
}

