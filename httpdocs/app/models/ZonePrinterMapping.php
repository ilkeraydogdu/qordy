<?php
namespace App\Models;

require_once __DIR__ . '/../core/Model.php';

class ZonePrinterMapping extends \App\Core\Model {
    protected $table = 'zone_printer_mappings';
    protected $primaryKey = 'mapping_id';
    
    /**
     * Get all mappings
     * @return array
     */
    public function getAll(): array {
        return $this->query()
            ->orderBy('created_at', 'DESC')
            ->get();
    }
    
    /**
     * Get mapping by ID
     * @param string $mappingId
     * @return array|null
     */
    public function getById(string $mappingId): ?array {
        return $this->query()
            ->where('mapping_id', $mappingId)
            ->first();
    }
    
    /**
     * Get mappings by zone ID
     * @param string $zoneId
     * @return array
     */
    public function getByZone(string $zoneId): array {
        return $this->query()
            ->where('zone_id', $zoneId)
            ->where('is_active', 1)
            ->orderBy('priority', 'ASC')
            ->get();
    }
    
    /**
     * Get mappings by printer ID
     * @param string $printerId
     * @return array
     */
    public function getByPrinter(string $printerId): array {
        return $this->query()
            ->where('printer_id', $printerId)
            ->where('is_active', 1)
            ->get();
    }
    
    /**
     * Get mappings by business ID
     * @param string $businessId
     * @return array
     */
    public function getByBusiness(string $businessId): array {
        return $this->query()
            ->where('tenant_id', $businessId)
            ->where('is_active', 1)
            ->get();
    }
    
    /**
     * Get mapping by zone and printer
     * @param string $zoneId
     * @param string $printerId
     * @return array|null
     */
    public function getByZoneAndPrinter(string $zoneId, string $printerId): ?array {
        return $this->query()
            ->where('zone_id', $zoneId)
            ->where('printer_id', $printerId)
            ->first();
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
        return $this->query()->insert($data);
    }
    
    /**
     * Update mapping
     * @param string $mappingId
     * @param array $data
     * @return bool
     */
    public function update(string $mappingId, array $data): bool {
        $data['updated_at'] = date('Y-m-d H:i:s');
        return $this->query()
            ->where('mapping_id', $mappingId)
            ->update($data);
    }
    
    /**
     * Delete mapping
     * @param string $mappingId
     * @return bool
     */
    public function delete(string $mappingId): bool {
        return $this->query()
            ->where('mapping_id', $mappingId)
            ->delete();
    }
    
    /**
     * Delete mapping by zone and printer
     * @param string $zoneId
     * @param string $printerId
     * @return bool
     */
    public function deleteByZoneAndPrinter(string $zoneId, string $printerId): bool {
        return $this->query()
            ->where('zone_id', $zoneId)
            ->where('printer_id', $printerId)
            ->delete();
    }
    
    /**
     * Deactivate mapping (soft delete)
     * @param string $mappingId
     * @return bool
     */
    public function deactivate(string $mappingId): bool {
        return $this->query()
            ->where('mapping_id', $mappingId)
            ->update(['is_active' => 0, 'updated_at' => date('Y-m-d H:i:s')]);
    }
}
