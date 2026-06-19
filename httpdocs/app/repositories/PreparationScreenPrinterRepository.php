<?php
namespace App\Repositories;

use App\Core\BaseRepository;

class PreparationScreenPrinterRepository extends BaseRepository {
    protected $table = 'preparation_screen_printers';
    protected $primaryKey = 'id';
    
    public function __construct($database) {
        parent::__construct($database);
    }
    
    /**
     * Get all mappings
     * @return array
     */
    public function getAll(): array {
        try {
            $sql = "SELECT * FROM {$this->table} ORDER BY created_at DESC";
            return $this->fetchAll($sql);
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error("PreparationScreenPrinterRepository::getAll - Error", [
                    'error' => $e->getMessage(),
                    'table' => $this->table
                ]);
            }
            return [];
        }
    }
    
    /**
     * Get printers by screen ID
     * @param string $screenId
     * @param string $businessId
     * @return array
     */
    public function getPrintersByScreen(string $screenId, string $businessId): array {
        $sql = "SELECT psp.*, p.printer_name, p.printer_location, p.printer_serial, p.status as printer_status 
                FROM {$this->table} psp 
                LEFT JOIN printers p ON psp.printer_id = p.printer_id 
                WHERE psp.screen_id = :screen_id 
                AND psp.tenant_id = :business_id 
                AND psp.is_active = 1 
                ORDER BY psp.priority ASC, p.printer_name";
        return $this->fetchAll($sql, [
            'screen_id' => $screenId,
            'business_id' => $businessId
        ]);
    }
    
    /**
     * Get screens by printer ID
     * @param string $printerId
     * @return array Screens assigned to printer (including KITCHEN if assigned)
     */
    public function getScreensByPrinter(string $printerId): array {
        // Handle KITCHEN screen (special screen_id = 'KITCHEN')
        // Use LEFT JOIN with CASE to handle KITCHEN screen that may not exist in preparation_screens table
        $sql = "SELECT psp.*, 
                       CASE 
                           WHEN psp.screen_id = 'KITCHEN' THEN 'Mutfak'
                           ELSE ps.name 
                       END as screen_name,
                       CASE 
                           WHEN psp.screen_id = 'KITCHEN' THEN 'kitchen'
                           ELSE ps.slug 
                       END as screen_slug,
                       CASE 
                           WHEN psp.screen_id = 'KITCHEN' THEN 'KITCHEN'
                           ELSE ps.production_point 
                       END as production_point
                FROM {$this->table} psp 
                LEFT JOIN preparation_screens ps ON psp.screen_id = ps.screen_id 
                WHERE psp.printer_id = :printer_id 
                AND psp.is_active = 1 
                ORDER BY CASE WHEN psp.screen_id = 'KITCHEN' THEN 0 ELSE 1 END, screen_name";
        return $this->fetchAll($sql, ['printer_id' => $printerId]);
    }
    
    /**
     * Get mappings by business ID
     * @param string $businessId
     * @return array
     */
    public function getByBusiness(string $businessId): array {
        $sql = "SELECT psp.*, ps.name as screen_name, ps.slug as screen_slug, 
                       p.printer_name, p.printer_location 
                FROM {$this->table} psp 
                LEFT JOIN preparation_screens ps ON psp.screen_id = ps.screen_id 
                LEFT JOIN printers p ON psp.printer_id = p.printer_id 
                WHERE psp.tenant_id = :business_id 
                AND psp.is_active = 1 
                ORDER BY ps.name, p.printer_name";
        return $this->fetchAll($sql, ['business_id' => $businessId]);
    }
    
    /**
     * Get mapping by screen and printer
     * @param string $screenId
     * @param string $printerId
     * @param string $businessId
     * @return array|null
     */
    public function getByScreenAndPrinter(string $screenId, string $printerId, string $businessId): ?array {
        $sql = "SELECT * FROM {$this->table} 
                WHERE screen_id = :screen_id 
                AND printer_id = :printer_id 
                AND tenant_id = :business_id 
                LIMIT 1";
        return $this->fetchOne($sql, [
            'screen_id' => $screenId,
            'printer_id' => $printerId,
            'business_id' => $businessId
        ]);
    }
    
    /**
     * Create new mapping
     * @param array $data
     * @return bool
     */
    public function create(array $data): bool {
        if (!isset($data['created_at'])) {
            $data['created_at'] = date('Y-m-d H:i:s');
        }
        if (!isset($data['updated_at'])) {
            $data['updated_at'] = date('Y-m-d H:i:s');
        }
        try {
            $columns = array_keys($data);
            $placeholders = array_map(function($col) { return ":{$col}"; }, $columns);
            $sql = "INSERT INTO {$this->table} (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute($data);
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error("PreparationScreenPrinterRepository::create - Error", [
                    'error' => $e->getMessage(),
                    'table' => $this->table
                ]);
            }
            return false;
        }
    }
    
    /**
     * Update priority
     * @param string $screenId
     * @param string $printerId
     * @param string $businessId
     * @param int $priority
     * @return bool
     */
    public function updatePriority(string $screenId, string $printerId, string $businessId, int $priority): bool {
        $sql = "UPDATE {$this->table} 
                SET priority = :priority, updated_at = NOW() 
                WHERE screen_id = :screen_id 
                AND printer_id = :printer_id 
                AND tenant_id = :business_id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'priority' => $priority,
            'screen_id' => $screenId,
            'printer_id' => $printerId,
            'business_id' => $businessId
        ]);
    }
    
    /**
     * Update record by ID (for BaseRepository compatibility)
     * @param string $id
     * @param array $data
     * @return bool
     */
    public function update(string $id, array $data): bool {
        // Use parent method for consistency with BaseRepository signature
        return parent::update($id, $data);
    }
    
    /**
     * Delete mapping by screen and printer
     * @param string $screenId
     * @param string $printerId
     * @param string $businessId
     * @return bool
     */
    public function deleteByScreenAndPrinter(string $screenId, string $printerId, string $businessId): bool {
        $sql = "DELETE FROM {$this->table} 
                WHERE screen_id = :screen_id 
                AND printer_id = :printer_id 
                AND tenant_id = :business_id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'screen_id' => $screenId,
            'printer_id' => $printerId,
            'business_id' => $businessId
        ]);
    }
    
    /**
     * Deactivate mapping (soft delete)
     * @param int $id
     * @return bool
     */
    public function deactivate(int $id): bool {
        $sql = "UPDATE {$this->table} 
                SET is_active = 0, updated_at = NOW() 
                WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['id' => $id]);
    }
    
    /**
     * Assign printer to screen
     * @param string $screenId
     * @param string $printerId
     * @param string $businessId
     * @param int $priority
     * @return bool
     */
    public function assignPrinter(string $screenId, string $printerId, string $businessId, int $priority = 1): bool {
        // Check if mapping already exists
        $existing = $this->getByScreenAndPrinter($screenId, $printerId, $businessId);
        if ($existing) {
            // Update existing mapping to active
            return $this->update($existing['id'], [
                'is_active' => 1,
                'priority' => $priority,
                'updated_at' => date('Y-m-d H:i:s')
            ]);
        }
        
        // Create new mapping
        $data = [
            'screen_id' => $screenId,
            'printer_id' => $printerId,
            'tenant_id' => $businessId,
            'priority' => $priority,
            'is_active' => 1
        ];
        
        return $this->create($data);
    }
    
    /**
     * Remove printer from screen
     * @param string $screenId
     * @param string $printerId
     * @param string $businessId
     * @return bool
     */
    public function removePrinter(string $screenId, string $printerId, string $businessId): bool {
        return $this->deleteByScreenAndPrinter($screenId, $printerId, $businessId);
    }
    
    /**
     * Remove all assignments for a printer (for re-assignment)
     * @param string $printerId
     * @return bool
     */
    public function removeAllPrinterAssignments(string $printerId): bool {
        if (empty($printerId)) {
            return false;
        }
        
        try {
            $sql = "DELETE FROM {$this->table} WHERE printer_id = :printer_id";
            return $this->execute($sql, ['printer_id' => $printerId]);
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('PreparationScreenPrinterRepository::removeAllPrinterAssignments - Error', [
                    'printer_id' => $printerId,
                    'error' => $e->getMessage()
                ]);
            }
            return false;
        }
    }
}
