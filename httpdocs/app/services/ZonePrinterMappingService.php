<?php
namespace App\Services;

use App\Core\BaseService;
use App\Repositories\ZonePrinterMappingRepository;

class ZonePrinterMappingService extends BaseService {
    
    public function __construct(ZonePrinterMappingRepository $repository) {
        parent::__construct($repository);
    }
    
    /**
     * Assign printer to zone
     * @param string $printerId
     * @param string $zoneId
     * @param string $businessId
     * @param int $priority
     * @return bool
     */
    public function assign(string $printerId, string $zoneId, string $businessId, int $priority = 1): bool {
        // Check if mapping already exists
        $existing = $this->repository->getByZoneAndPrinter($zoneId, $printerId);
        if ($existing) {
            // Update existing mapping
            return $this->repository->update($existing['mapping_id'], [
                'is_active' => 1,
                'priority' => $priority,
                'business_id' => $businessId
            ]);
        }
        
        // Create new mapping
        $data = [
            'zone_id' => $zoneId,
            'printer_id' => $printerId,
            'business_id' => $businessId,
            'priority' => $priority,
            'is_active' => 1
        ];
        
        return $this->repository->create($data);
    }
    
    /**
     * Remove printer from zone
     * @param string $printerId
     * @param string $zoneId
     * @return bool
     */
    public function remove(string $printerId, string $zoneId): bool {
        return $this->repository->deleteByZoneAndPrinter($zoneId, $printerId);
    }
    
    /**
     * Get zones by printer
     * @param string $printerId
     * @return array
     */
    public function getZonesByPrinter(string $printerId): array {
        return $this->repository->getByPrinter($printerId);
    }
    
    /**
     * Get printers by zone
     * @param string $zoneId
     * @return array
     */
    public function getPrintersByZone(string $zoneId): array {
        return $this->repository->getByZone($zoneId);
    }
    
    /**
     * Get all mappings by business
     * @param string $businessId
     * @return array
     */
    public function getByBusiness(string $businessId): array {
        return $this->repository->getByBusiness($businessId);
    }
    
    /**
     * Assign multiple zones to printer
     * @param string $printerId
     * @param array $zoneIds
     * @param string $businessId
     * @return array Statistics
     */
    public function assignMultipleZones(string $printerId, array $zoneIds, string $businessId): array {
        $assigned = 0;
        $errors = 0;
        
        foreach ($zoneIds as $zoneId) {
            if ($this->assign($printerId, $zoneId, $businessId)) {
                $assigned++;
            } else {
                $errors++;
            }
        }
        
        return [
            'assigned' => $assigned,
            'errors' => $errors,
            'total' => count($zoneIds)
        ];
    }
    
    /**
     * Remove printer from all zones
     * @param string $printerId
     * @return int Number of mappings removed
     */
    public function removeFromAllZones(string $printerId): int {
        $mappings = $this->repository->getByPrinter($printerId);
        $count = 0;
        
        foreach ($mappings as $mapping) {
            if ($this->repository->delete($mapping['mapping_id'])) {
                $count++;
            }
        }
        
        return $count;
    }
}
