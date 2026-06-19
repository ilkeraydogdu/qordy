<?php
namespace App\Services;

use App\Core\BaseService;
use App\Repositories\ZoneRepository;

class ZoneService extends BaseService {
    
    public function __construct(ZoneRepository $repository) {
        parent::__construct($repository);
    }

    /**
     * Get all zones (with Redis cache for performance)
     * @return array All zones
     */
    public function getAllZones(): array {
        $tenantId = \App\Core\TenantContext::getId() ?? 'guest';
        $cacheKey = "zones:all:{$tenantId}";
        
        try {
            $cache = \App\Core\DependencyFactory::getCacheService();
            
            // Try to get from cache (300 seconds TTL - zones change rarely)
            $zones = $cache->get($cacheKey);
            if ($zones !== null) {
                return $zones;
            }
            
            // Cache miss - get from database
            $zones = $this->repository->getAll();
            
            // Store in cache for 3 minutes (optimized for fresh data)
            $cache->set($cacheKey, $zones, 180);
            
            return $zones;
        } catch (\PDOException $e) {
            // If zones table doesn't exist, return empty array
            if (strpos($e->getMessage(), "doesn't exist") !== false) {
                error_log("Zones table not found, returning empty array");
                return [];
            }
            throw $e;
        } catch (\Exception $e) {
            // If cache fails, return from database directly
            return $this->repository->getAll();
        }
    }

    /**
     * Get zone by ID
     * @param string $zoneId Zone ID
     * @return array|null Zone data or null
     */
    public function getZoneById(string $zoneId): ?array {
        return $this->repository->getById($zoneId);
    }

    /**
     * Get zone by name
     * @param string $name Zone name
     * @return array|null Zone data or null
     */
    public function getZoneByName(string $name): ?array {
        return $this->repository->getByName($name);
    }

    /**
     * Get zones with table count
     * @return array Zones with table count
     */
    public function getZonesWithTableCount(): array {
        return $this->repository->getWithTableCount();
    }

    /**
     * Get zones by floor
     * @param string $floor Floor name
     * @return array Zones
     */
    public function getZonesByFloor(string $floor): array {
        return $this->repository->getByFloor($floor);
    }

    /**
     * Get all unique floors
     * @return array Unique floor names
     */
    public function getAllFloors(): array {
        return $this->repository->getAllFloors();
    }

    /**
     * Get zones grouped by floor
     * @return array Zones grouped by floor
     */
    public function getZonesGroupedByFloor(): array {
        $zones = $this->getAllZones();
        $grouped = [];
        
        foreach ($zones as $zone) {
            $floor = $zone['floor'] ?? 'Diğer';
            if (!isset($grouped[$floor])) {
                $grouped[$floor] = [];
            }
            $grouped[$floor][] = $zone;
        }
        
        return $grouped;
    }

    /**
     * Invalidate zones cache
     * @return void
     */
    private function invalidateZonesCache(): void {
        try {
            $tenantId = \App\Core\TenantContext::getId() ?? 'guest';
            $cacheKey = "zones:all:{$tenantId}";
            $cache = \App\Core\DependencyFactory::getCacheService();
            $cache->delete($cacheKey);
        } catch (\Exception $e) {
            // Ignore cache errors
        }
    }
    
    /**
     * Create a new zone
     * @param array $data Zone data
     * @return bool|string Zone ID on success, false on failure
     */
    public function createZone(array $data): bool|string {
        try {
            require_once __DIR__ . '/../helpers/functions.php';
            
            // Normalize zone name (trim and normalize whitespace)
            if (isset($data['name'])) {
                $data['name'] = trim($data['name']);
                // Normalize multiple spaces to single space
                $data['name'] = preg_replace('/\s+/', ' ', $data['name']);
                
                if (empty($data['name'])) {
                    error_log('ZoneService::createZone: Zone name is empty after normalization');
                    return false;
                }
                
                // Check if zone with same name already exists for current tenant (case-insensitive)
                // Get tenant_id for checking
                $tenantId = \App\Core\TenantResolver::resolve();
                if (!$tenantId && class_exists('\App\Core\TenantContext')) {
                    $tenantId = \App\Core\TenantContext::getId();
                }
                
                // Only check zones for current tenant (getAllZones already filters by tenant)
                $allZones = $this->getAllZones();
                foreach ($allZones as $zone) {
                    $existingName = trim($zone['name'] ?? '');
                    $existingTenantId = $zone['tenant_id'] ?? null;
                    
                    // Check if same name exists for same tenant (case-insensitive)
                    if (!empty($existingName) && 
                        strcasecmp($existingName, $data['name']) === 0 &&
                        $existingTenantId === $tenantId) {
                        error_log('ZoneService::createZone: Zone with name "' . $data['name'] . '" already exists for tenant ' . $tenantId . ' (case-insensitive match with "' . $existingName . '")');
                        return false; // Zone already exists for this tenant
                    }
                }
            }
            
            if (!isset($data['zone_id'])) {
                $data['zone_id'] = generateId('z');
            }
            if (!isset($data['created_at'])) {
                $data['created_at'] = date('Y-m-d H:i:s');
            }
            
            // Ensure empty strings are set to null for optional fields (floor, description)
            if (isset($data['floor']) && $data['floor'] === '') {
                $data['floor'] = null;
            }
            if (isset($data['description']) && $data['description'] === '') {
                $data['description'] = null;
            }
            
            // CRITICAL: Add tenant_id if not set (for multi-tenant isolation)
            if (!isset($data['tenant_id'])) {
                $tenantId = \App\Core\TenantResolver::resolve();
                if (!$tenantId && class_exists('\App\Core\TenantContext')) {
                    $tenantId = \App\Core\TenantContext::getId();
                }
                if ($tenantId) {
                    $data['tenant_id'] = $tenantId;
                }
            }
            
            error_log('ZoneService::createZone: Attempting to create zone with data: ' . json_encode($data));
            
            $result = $this->repository->create($data);
            
            error_log('ZoneService::createZone: Repository create result: ' . var_export($result, true));
            
            if ($result !== false) {
                // Invalidate cache after creation
                $this->invalidateZonesCache();
                // Return the zone_id we set (for custom IDs, result might be the insert ID or true)
                return $data['zone_id'];
            }
            
            error_log('ZoneService::createZone: Repository create returned false');
            return false;
        } catch (\PDOException $e) {
            error_log('Error in ZoneService::createZone (PDO): ' . $e->getMessage());
            error_log('PDO Error Code: ' . $e->getCode());
            error_log('SQL State: ' . $e->errorInfo[0] ?? 'N/A');
            error_log('Stack trace: ' . $e->getTraceAsString());
            throw $e; // Re-throw to let controller handle it
        } catch (\Exception $e) {
            error_log('Error in ZoneService::createZone: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
            throw $e; // Re-throw to let controller handle it
        }
    }

    /**
     * Update zone
     * @param string $zoneId Zone ID
     * @param array $data Zone data to update
     * @return bool Success
     */
    public function updateZone(string $zoneId, array $data): bool {
        try {
            // If setting this zone as active, deactivate all other zones first
            if (isset($data['is_active']) && ($data['is_active'] == 1 || $data['is_active'] === true || $data['is_active'] === '1')) {
                $this->deactivateAllZones($zoneId);
            }
            
            $data['updated_at'] = date('Y-m-d H:i:s');
            $result = (bool)$this->repository->update($zoneId, $data);
            
            // Invalidate cache after update
            if ($result) {
                $this->invalidateZonesCache();
            }
            
            return $result;
        } catch (\Exception $e) {
            error_log('Error in ZoneService::updateZone: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
            return false;
        }
    }

    /**
     * Deactivate all zones except the specified one
     * @param string $exceptZoneId Zone ID to keep active
     * @return bool Success
     */
    private function deactivateAllZones(string $exceptZoneId): bool {
        try {
            $db = \App\Core\Database::getInstance();
            $table = $this->repository->getTableName();
            $primaryKey = 'zone_id'; // ZoneRepository uses 'zone_id' as primary key
            $sql = "UPDATE {$table} SET is_active = 0 WHERE {$primaryKey} != :zone_id";
            $stmt = $db->prepare($sql);
            return $stmt->execute(['zone_id' => $exceptZoneId]);
        } catch (\Exception $e) {
            error_log('Error in ZoneService::deactivateAllZones: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete zone
     * @param string $zoneId Zone ID
     * @return bool Success
     */
    public function deleteZone(string $zoneId): bool {
        // Check if zone has tables
        $tableService = \App\Core\DependencyFactory::getTableService();
        $tables = $tableService->getTablesByZoneId($zoneId);
        
        if (count($tables) > 0) {
            return false; // Cannot delete zone with tables
        }
        
        $result = $this->repository->delete($zoneId);
        
        // Invalidate cache after deletion
        if ($result) {
            $this->invalidateZonesCache();
        }
        
        return $result;
        
        return (bool)$this->repository->delete($zoneId);
    }
}
