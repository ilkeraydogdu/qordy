<?php
namespace App\Services;

use App\Core\BaseService;
use App\Repositories\PrinterRepository;
use App\Core\DependencyFactory;

class PrinterService extends BaseService {
    public function __construct(PrinterRepository $repository) {
        parent::__construct($repository);
    }
    
    public function getAllPrinters() {
        try {
            return $this->repository->getAll();
        } catch (\Exception $e) {
            // Printers table might not exist
            error_log("PrinterService::getAllPrinters - Error (table might not exist): " . $e->getMessage());
            return [];
        }
    }
    
    public function getActivePrinters() {
        try {
            return $this->repository->getActive();
        } catch (\Exception $e) {
            // Printers table might not exist
            error_log("PrinterService::getActivePrinters - Error (table might not exist): " . $e->getMessage());
            return [];
        }
    }
    
    public function getPrinterById($printerId) {
        if (empty($printerId)) {
            return null;
        }
        try {
            return $this->repository->findById($printerId);
        } catch (\Exception $e) {
            // Printers table might not exist or printer not found
            error_log("PrinterService::getPrinterById - Error (table might not exist or printer not found): " . $e->getMessage());
            return null;
        }
    }
    
    public function getPrintersByLocation($location) {
        try {
            return $this->repository->getByLocation($location);
        } catch (\Exception $e) {
            // Printers table might not exist
            error_log("PrinterService::getPrintersByLocation - Error (table might not exist): " . $e->getMessage());
            return [];
        }
    }
    
    public function getPrinterBySerial($serial) {
        if (empty($serial)) {
            return null;
        }
        try {
            return $this->repository->getBySerial($serial);
        } catch (\Exception $e) {
            // Printers table might not exist or printer not found
            error_log("PrinterService::getPrinterBySerial - Error (table might not exist or printer not found): " . $e->getMessage());
            return null;
        }
    }
    
    public function registerPrinter($printerData) {
        // Set default model if not provided
        if (!isset($printerData['printer_model'])) {
            $printerData['printer_model'] = 'XP-Q805K';
        }
        
        // Set default status
        if (!isset($printerData['status'])) {
            $printerData['status'] = 'ACTIVE';
        }
        
        if (!isset($printerData['is_active'])) {
            $printerData['is_active'] = 1;
        }
        
        if (!isset($printerData['connection_type'])) {
            $printerData['connection_type'] = 'USB';
        }
        
        return $this->repository->create($printerData);
    }
    
    public function updatePrinter($printerId, $printerData) {
        return $this->repository->update($printerId, $printerData);
    }
    
    public function deletePrinter($printerId) {
        return $this->repository->delete($printerId);
    }
    
    public function updatePrinterStatus($printerId, $status) {
        return $this->repository->updateStatus($printerId, $status);
    }
    
    public function testPrinterConnection($printerSerial) {
        // Bağlantı durumu, yazıcının ait olduğu tenant için ONLINE bir
        // PrinterBridge olup olmadığına göre belirlenir. Eski sürümde
        // sunucudan localhost:8080'e POST atılıyordu; üretim sunucusunda
        // böyle bir servis olmadığı için her çağrı başarısız dönüyordu
        // (dead code). Yeni davranış merkezi bridge mimarisini kullanır.
        try {
            if (empty($printerSerial)) {
                return false;
            }
            $printer = $this->getPrinterBySerial($printerSerial);
            if (!$printer) {
                return false;
            }
            $tenantId = $printer['tenant_id'] ?? $printer['business_id'] ?? null;
            if (!$tenantId) {
                return false;
            }
            $bridgeRepo = DependencyFactory::getPrinterBridgeRepository();
            $bridges = $bridgeRepo->getByBusiness((string)$tenantId);
            foreach ($bridges as $b) {
                if (strtoupper((string)($b['status'] ?? '')) !== 'ONLINE') {
                    continue;
                }
                $hb = $b['last_heartbeat'] ?? null;
                if (!$hb) {
                    continue;
                }
                $hbTs = strtotime((string)$hb);
                if ($hbTs === false) {
                    continue;
                }
                if ((time() - $hbTs) <= 120) {
                    return true;
                }
            }
            return false;
        } catch (\Throwable $e) {
            \App\Core\Logger::warning('PrinterService::testPrinterConnection - bridge lookup failed', [
                'printer_serial' => $printerSerial,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
    
    /**
     * Find printer for zone and floor
     * Searches printers by location matching zone name and floor
     * @param string|null $zoneName Zone name
     * @param string|null $zoneFloor Zone floor
     * @param string|null $tableFloor Table floor (fallback if zone floor not available)
     * @return array|null Matching printer or null
     */
    public function findPrinterForZoneAndFloor(?string $zoneName, ?string $zoneFloor = null, ?string $tableFloor = null): ?array {
        try {
            $printers = [];
            
            // Priority 1: Zone name + Zone floor combination (e.g., "Bahçe - Zemin Kat")
            if (!empty($zoneName) && !empty($zoneFloor)) {
                $locationPattern = trim($zoneName) . ' - ' . trim($zoneFloor);
                try {
                    $printers = $this->repository->findByLocationPattern($locationPattern);
                    if (!empty($printers)) {
                        return $printers[0]; // Return first match (best match due to ordering)
                    }
                } catch (\Exception $e) {
                    // Table might not exist, continue to next priority
                }
                
                // Try alternative format: "Zone Floor" (without dash)
                $locationPattern = trim($zoneName) . ' ' . trim($zoneFloor);
                try {
                    $printers = $this->repository->findByLocationPattern($locationPattern);
                    if (!empty($printers)) {
                        return $printers[0];
                    }
                } catch (\Exception $e) {
                    // Table might not exist, continue to next priority
                }
            }
            
            // Priority 2: Zone name only (e.g., "Bahçe")
            if (!empty($zoneName)) {
                try {
                    $printers = $this->repository->findByLocationPattern($zoneName);
                    if (!empty($printers)) {
                        return $printers[0];
                    }
                } catch (\Exception $e) {
                    // Table might not exist, continue to next priority
                }
            }
            
            // Priority 3: Floor only (use zone floor first, then table floor)
            $floorToSearch = $zoneFloor ?? $tableFloor;
            if (!empty($floorToSearch)) {
                try {
                    $printers = $this->repository->findByLocationPattern($floorToSearch);
                    if (!empty($printers)) {
                        return $printers[0];
                    }
                } catch (\Exception $e) {
                    // Table might not exist, continue to next priority
                }
            }
            
            // Priority 4: Default printer (fallback)
            return $this->getDefaultPrinter();
        } catch (\Exception $e) {
            // Printers table might not exist
            error_log("PrinterService::findPrinterForZoneAndFloor - Error (table might not exist): " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Find printer by location pattern (fuzzy matching)
     * @param string $pattern Location pattern to search for
     * @return array|null Matching printer or null
     */
    public function findPrinterByLocationPattern(string $pattern): ?array {
        try {
            $printers = $this->repository->findByLocationPattern($pattern);
            return !empty($printers) ? $printers[0] : null;
        } catch (\Exception $e) {
            // Printers table might not exist
            error_log("PrinterService::findPrinterByLocationPattern - Error (table might not exist): " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get default printer (first active printer)
     * @return array|null First active printer or null
     */
    public function getDefaultPrinter(): ?array {
        try {
            return $this->repository->getFirstActive();
        } catch (\Exception $e) {
            // Printers table might not exist
            error_log("PrinterService::getDefaultPrinter - Error (table might not exist): " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get all printers with business info
     * @return array Printers with company_name
     */
    public function getAllPrintersWithBusiness(): array {
        try {
            return $this->repository->getAllWithBusinessInfo();
        } catch (\Exception $e) {
            error_log("PrinterService::getAllPrintersWithBusiness - Error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get printers by business ID
     * @param string $businessId
     * @return array
     */
    public function getPrintersByBusiness(string $businessId): array {
        try {
            return $this->repository->getByBusiness($businessId);
        } catch (\Exception $e) {
            error_log("PrinterService::getPrintersByBusiness - Error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get printers by bridge ID
     * @param string $bridgeId
     * @return array
     */
    public function getPrintersByBridge(string $bridgeId): array {
        try {
            return $this->repository->getByBridge($bridgeId);
        } catch (\Exception $e) {
            error_log("PrinterService::getPrintersByBridge - Error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get all printers with zones
     * @param string|null $businessId Optional business filter
     * @return array Printers with zone information
     */
    public function getAllPrintersWithZones(?string $businessId = null): array {
        try {
            $printers = $businessId ? $this->repository->getByBusiness($businessId) : $this->repository->getAll();
            
            // Get zone mappings for each printer
            $zoneMappingService = DependencyFactory::getZonePrinterMappingService();
            
            foreach ($printers as &$printer) {
                $zones = $zoneMappingService->getZonesByPrinter($printer['printer_id']);
                $printer['zones'] = array_map(function($zone) {
                    return [
                        'zone_id' => $zone['zone_id'],
                        'zone_name' => $zone['zone_name'] ?? '',
                        'zone_floor' => $zone['zone_floor'] ?? ''
                    ];
                }, $zones);
            }
            
            return $printers;
        } catch (\Exception $e) {
            error_log("PrinterService::getAllPrintersWithZones - Error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Assign printer to location (legacy method for backward compatibility)
     * @param string $printerId
     * @param string $location
     * @return bool
     */
    public function assignPrinterToLocation(string $printerId, string $location): bool {
        try {
            return $this->repository->update($printerId, ['printer_location' => $location]);
        } catch (\Exception $e) {
            error_log("PrinterService::assignPrinterToLocation - Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Assign printer to preparation screen
     * @param string $printerId Printer ID
     * @param string $screenId Preparation screen ID
     * @return bool Success
     */
    public function assignPrinterToScreen(string $printerId, string $screenId): bool {
        $printer = $this->getById($printerId);
        if (!$printer) {
            return false;
        }

        // Update printer with preparation screen ID
        return $this->updatePrinter($printerId, [
            'preparation_screen_id' => $screenId
        ]);
    }

    /**
     * Remove printer assignment from preparation screen
     * @param string $printerId Printer ID
     * @return bool Success
     */
    public function removePrinterAssignment(string $printerId): bool {
        $printer = $this->getById($printerId);
        if (!$printer) {
            return false;
        }

        // Update printer to remove preparation screen assignment
        return $this->updatePrinter($printerId, [
            'preparation_screen_id' => null
        ]);
    }
}

