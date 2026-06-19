<?php
namespace App\Repositories;

use App\Core\BaseRepository;

class PrinterRepository extends BaseRepository {
    protected $table = 'printers';
    protected $primaryKey = 'printer_id';
    
    public function __construct($database) {
        parent::__construct($database);
    }
    
    /**
     * Get all printers
     * @return array
     */
    public function getAll(): array {
        try {
            // Get tenant filter if applicable
            $tenantFilter = $this->getTenantFilter();
            $sql = "SELECT * FROM {$this->table}";
            $params = [];
            
            // Add tenant filter if exists
            if (!empty($tenantFilter['where'])) {
                $sql .= " WHERE " . $tenantFilter['where'];
                $params = array_merge($params, $tenantFilter['params']);
            }
            
            $sql .= " ORDER BY printer_name";
            return $this->fetchAll($sql, $params);
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error("PrinterRepository::getAll - Error", [
                    'error' => $e->getMessage(),
                    'table' => $this->table
                ]);
            }
            return [];
        }
    }
    
    public function getByLocation($location) {
        $sql = "SELECT * FROM {$this->table} WHERE printer_location = :location AND is_active = 1 ORDER BY printer_name";
        return $this->fetchAll($sql, ['location' => $location]);
    }
    
    public function getBySerial($serial) {
        $sql = "SELECT * FROM {$this->table} WHERE printer_serial = :serial LIMIT 1";
        return $this->fetchOne($sql, ['serial' => $serial]);
    }
    
    public function getActive() {
        $sql = "SELECT * FROM {$this->table} WHERE is_active = 1 AND status = 'ACTIVE' ORDER BY printer_name";
        return $this->fetchAll($sql);
    }
    
    public function updateStatus($printerId, $status) {
        $sql = "UPDATE {$this->table} SET status = :status, updated_at = NOW() WHERE printer_id = :printer_id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['status' => $status, 'printer_id' => $printerId]);
    }
    
    /**
     * Find printers by location pattern (fuzzy matching)
     * @param string $pattern Location pattern to search for
     * @return array Matching printers
     */
    public function findByLocationPattern($pattern) {
        $exact = $pattern;
        $startsWith = $pattern . '%';
        $contains = '%' . $pattern . '%';
        $endsWith = '%' . $pattern;
        
        $sql = "SELECT * FROM {$this->table} 
                WHERE is_active = 1 AND status = 'ACTIVE' 
                AND (printer_location = :exact 
                     OR printer_location LIKE :starts_with 
                     OR printer_location LIKE :contains 
                     OR printer_location LIKE :ends_with)
                ORDER BY 
                    CASE 
                        WHEN printer_location = :exact2 THEN 1
                        WHEN printer_location LIKE :starts_with2 THEN 2
                        WHEN printer_location LIKE :contains2 THEN 3
                        WHEN printer_location LIKE :ends_with2 THEN 4
                        ELSE 5
                    END,
                    printer_name";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'exact' => $exact,
            'starts_with' => $startsWith,
            'contains' => $contains,
            'ends_with' => $endsWith,
            'exact2' => $exact,
            'starts_with2' => $startsWith,
            'contains2' => $contains,
            'ends_with2' => $endsWith
        ]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Get first active printer (default fallback)
     * @return array|null First active printer or null
     */
    public function getFirstActive() {
        $sql = "SELECT * FROM {$this->table} 
                WHERE is_active = 1 AND status = 'ACTIVE' 
                ORDER BY printer_name 
                LIMIT 1";
        return $this->fetchOne($sql);
    }
    
    /**
     * Get all printers with business info
     * @return array Printers with company_name
     */
    public function getAllWithBusinessInfo(): array {
        try {
            $sql = "SELECT p.*, c.company_name, c.email as business_email 
                    FROM {$this->table} p 
                    LEFT JOIN customers c ON p.tenant_id = c.customer_id 
                    ORDER BY c.company_name, p.printer_name";
            return $this->fetchAll($sql);
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error("PrinterRepository::getAllWithBusinessInfo - Error", [
                    'error' => $e->getMessage(),
                    'table' => $this->table
                ]);
            }
            return [];
        }
    }
    
    /**
     * Get printers by business ID
     * @param string $businessId
     * @return array
     */
    public function getByBusiness(string $businessId): array {
        try {
            $sql = "SELECT * FROM {$this->table} 
                    WHERE tenant_id = :business_id 
                    ORDER BY printer_name";
            return $this->fetchAll($sql, ['business_id' => $businessId]);
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error("PrinterRepository::getByBusiness - Error", [
                    'error' => $e->getMessage(),
                    'table' => $this->table
                ]);
            }
            return [];
        }
    }
    
    /**
     * Get printers by bridge ID
     * @param string $bridgeId
     * @return array
     */
    public function getByBridge(string $bridgeId): array {
        try {
            $sql = "SELECT * FROM {$this->table} 
                    WHERE bridge_id = :bridge_id 
                    ORDER BY printer_name";
            return $this->fetchAll($sql, ['bridge_id' => $bridgeId]);
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error("PrinterRepository::getByBridge - Error", [
                    'error' => $e->getMessage(),
                    'bridge_id' => $bridgeId,
                    'table' => $this->table
                ]);
            }
            return [];
        }
    }
}

