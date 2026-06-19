<?php
namespace App\Repositories;

use App\Core\BaseRepository;

class ZonePrinterMappingRepository extends BaseRepository {
    protected $table = 'zone_printer_mappings';
    protected $primaryKey = 'mapping_id';
    
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
                \App\Core\Logger::error("ZonePrinterMappingRepository::getAll - Error", [
                    'error' => $e->getMessage(),
                    'table' => $this->table
                ]);
            }
            return [];
        }
    }
    
    /**
     * Get mappings by zone ID
     * @param string $zoneId
     * @return array
     */
    public function getByZone(string $zoneId): array {
        $sql = "SELECT zpm.*, p.printer_name, p.printer_location, p.printer_serial 
                FROM {$this->table} zpm 
                LEFT JOIN printers p ON zpm.printer_id = p.printer_id 
                WHERE zpm.zone_id = :zone_id AND zpm.is_active = 1 
                ORDER BY zpm.priority ASC, p.printer_name";
        return $this->fetchAll($sql, ['zone_id' => $zoneId]);
    }
    
    /**
     * Get mappings by printer ID
     * @param string $printerId
     * @return array
     */
    public function getByPrinter(string $printerId): array {
        $sql = "SELECT zpm.*, z.name as zone_name, z.floor as zone_floor 
                FROM {$this->table} zpm 
                LEFT JOIN zones z ON zpm.zone_id = z.zone_id 
                WHERE zpm.printer_id = :printer_id AND zpm.is_active = 1 
                ORDER BY z.floor, z.name";
        return $this->fetchAll($sql, ['printer_id' => $printerId]);
    }
    
    /**
     * Get mappings by business ID
     * @param string $businessId
     * @return array
     */
    public function getByBusiness(string $businessId): array {
        $sql = "SELECT zpm.*, z.name as zone_name, z.floor as zone_floor, 
                       p.printer_name, p.printer_location 
                FROM {$this->table} zpm 
                LEFT JOIN zones z ON zpm.zone_id = z.zone_id 
                LEFT JOIN printers p ON zpm.printer_id = p.printer_id 
                WHERE zpm.tenant_id = :business_id AND zpm.is_active = 1 
                ORDER BY z.floor, z.name, p.printer_name";
        return $this->fetchAll($sql, ['business_id' => $businessId]);
    }
    
    /**
     * Get mapping by zone and printer
     * @param string $zoneId
     * @param string $printerId
     * @return array|null
     */
    public function getByZoneAndPrinter(string $zoneId, string $printerId): ?array {
        $sql = "SELECT * FROM {$this->table} 
                WHERE zone_id = :zone_id AND printer_id = :printer_id 
                LIMIT 1";
        return $this->fetchOne($sql, [
            'zone_id' => $zoneId,
            'printer_id' => $printerId
        ]);
    }
    
    /**
     * Create new mapping
     * @param array $data
     * @return bool
     */
    public function create(array $data): bool {
        require_once __DIR__ . '/../helpers/functions.php';
        if (!isset($data['mapping_id'])) {
            $data['mapping_id'] = generateId('zpm');
        }
        if (!isset($data['created_at'])) {
            $data['created_at'] = date('Y-m-d H:i:s');
        }
        if (!isset($data['updated_at'])) {
            $data['updated_at'] = date('Y-m-d H:i:s');
        }
        try {
            $sql = "INSERT INTO {$this->table} (" . implode(', ', array_keys($data)) . ") VALUES (:" . implode(', :', array_keys($data)) . ")";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute($data);
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error("ZonePrinterMappingRepository::create - Error", [
                    'error' => $e->getMessage(),
                    'table' => $this->table
                ]);
            }
            return false;
        }
    }
    
    /**
     * Delete mapping by zone and printer
     * @param string $zoneId
     * @param string $printerId
     * @return bool
     */
    public function deleteByZoneAndPrinter(string $zoneId, string $printerId): bool {
        $sql = "DELETE FROM {$this->table} 
                WHERE zone_id = :zone_id AND printer_id = :printer_id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'zone_id' => $zoneId,
            'printer_id' => $printerId
        ]);
    }
    
    /**
     * Deactivate mapping (soft delete)
     * @param string $mappingId
     * @return bool
     */
    public function deactivate(string $mappingId): bool {
        $sql = "UPDATE {$this->table} 
                SET is_active = 0, updated_at = NOW() 
                WHERE mapping_id = :mapping_id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['mapping_id' => $mappingId]);
    }
}
