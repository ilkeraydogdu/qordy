<?php
namespace App\Services;

use App\Core\BaseService;
use App\Repositories\PreparationScreenPrinterRepository;

class PreparationScreenPrinterService extends BaseService {
    
    public function __construct(PreparationScreenPrinterRepository $repository) {
        parent::__construct($repository);
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
        // Validate inputs
        if (empty($screenId) || empty($printerId) || empty($businessId)) {
            return false;
        }
        
        // Check if screen exists (or is KITCHEN special screen)
        if ($screenId === 'KITCHEN') {
            // KITCHEN is a special screen - always valid
            // No need to check repository
        } else {
            try {
                $screenRepo = \App\Core\DependencyFactory::getPreparationScreenRepository();
                $screen = $screenRepo->getById($screenId);
                if (!$screen) {
                    return false;
                }
            } catch (\Exception $e) {
                error_log("PreparationScreenPrinterService::assignPrinter - Screen not found: " . $e->getMessage());
                return false;
            }
        }
        
        // Check if printer exists
        try {
            $printerService = \App\Core\DependencyFactory::getPrinterService();
            $printer = $printerService->getPrinterById($printerId);
            if (!$printer) {
                return false;
            }
            
            // Verify printer belongs to same tenant
            // printers table uses tenant_id column; keep business_id fallback for legacy rows.
            $printerTenantId = $printer['tenant_id'] ?? $printer['business_id'] ?? null;
            if ($printerTenantId !== null && (string)$printerTenantId !== (string)$businessId) {
                return false;
            }
        } catch (\Exception $e) {
            error_log("PreparationScreenPrinterService::assignPrinter - Printer not found: " . $e->getMessage());
            return false;
        }
        
        // Assign printer
        return $this->repository->assignPrinter($screenId, $printerId, $businessId, $priority);
    }
    
    /**
     * Remove printer from screen
     * @param string $screenId
     * @param string $printerId
     * @param string $businessId
     * @return bool
     */
    public function removePrinter(string $screenId, string $printerId, string $businessId): bool {
        // Validate inputs
        if (empty($screenId) || empty($printerId) || empty($businessId)) {
            return false;
        }
        
        return $this->repository->removePrinter($screenId, $printerId, $businessId);
    }
    
    /**
     * Assign printer to screen (simplified - auto-detects business_id)
     * @param string $printerId
     * @param string $screenId
     * @param int $priority
     * @return bool
     */
    public function assignPrinterToScreen(string $printerId, string $screenId, int $priority = 1): bool {
        if (empty($printerId) || empty($screenId)) {
            return false;
        }
        
        // Get business_id from tenant context
        $businessId = \App\Core\TenantContext::getId();
        if (!$businessId) {
            return false;
        }
        
        return $this->assignPrinter($screenId, $printerId, $businessId, $priority);
    }
    
    /**
     * Remove all printer assignments (for re-assignment)
     * @param string $printerId
     * @return bool
     */
    public function removeAllPrinterAssignments(string $printerId): bool {
        if (empty($printerId)) {
            return false;
        }
        
        return $this->repository->removeAllPrinterAssignments($printerId);
    }
    
    /**
     * Get screens for a printer (with details)
     * @param string $printerId
     * @return array
     */
    public function getScreensForPrinter(string $printerId): array {
        if (empty($printerId)) {
            return [];
        }
        
        return $this->getScreensByPrinter($printerId);
    }
    
    /**
     * Get printers by screen
     * @param string $screenId Screen ID (can be 'KITCHEN' for kitchen screen)
     * @param string $businessId
     * @return array
     */
    public function getPrintersByScreen(string $screenId, string $businessId): array {
        if (empty($screenId) || empty($businessId)) {
            return [];
        }
        
        // KITCHEN is a special screen - use repository directly
        // Repository will handle it via SQL query
        return $this->repository->getPrintersByScreen($screenId, $businessId);
    }
    
    /**
     * Get screens by printer
     * @param string $printerId
     * @return array Screens assigned to printer (including KITCHEN if assigned)
     */
    public function getScreensByPrinter(string $printerId): array {
        if (empty($printerId)) {
            return [];
        }
        
        $screens = $this->repository->getScreensByPrinter($printerId);
        
        // Check if KITCHEN screen is assigned (special handling)
        // KITCHEN screen_id = 'KITCHEN' in preparation_screen_printers table
        $kitchenScreens = array_filter($screens, function($screen) {
            return ($screen['screen_id'] ?? '') === 'KITCHEN';
        });
        
        // If KITCHEN screen found, add it with proper name
        if (!empty($kitchenScreens)) {
            foreach ($screens as &$screen) {
                if (($screen['screen_id'] ?? '') === 'KITCHEN') {
                    $screen['screen_name'] = 'Mutfak';
                    $screen['production_point'] = 'KITCHEN';
                }
            }
            unset($screen);
        }
        
        return $screens;
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
        if (empty($screenId) || empty($printerId) || empty($businessId)) {
            return false;
        }
        
        return $this->repository->updatePriority($screenId, $printerId, $businessId, $priority);
    }
    
    /**
     * Get all mappings by business
     * @param string $businessId
     * @return array
     */
    public function getByBusiness(string $businessId): array {
        if (empty($businessId)) {
            return [];
        }
        
        return $this->repository->getByBusiness($businessId);
    }
    
    /**
     * Assign multiple printers to screen
     * @param string $screenId
     * @param array $printerIds
     * @param string $businessId
     * @param int $priority
     * @return array Statistics
     */
    public function assignMultiplePrinters(string $screenId, array $printerIds, string $businessId, int $priority = 1): array {
        $assigned = 0;
        $errors = 0;
        
        foreach ($printerIds as $printerId) {
            if ($this->assignPrinter($screenId, $printerId, $businessId, $priority)) {
                $assigned++;
            } else {
                $errors++;
            }
        }
        
        return [
            'assigned' => $assigned,
            'errors' => $errors,
            'total' => count($printerIds)
        ];
    }
    
    /**
     * Remove printer from all screens
     * @param string $printerId
     * @param string $businessId
     * @return int Number of mappings removed
     */
    public function removeFromAllScreens(string $printerId, string $businessId): int {
        $mappings = $this->repository->getScreensByPrinter($printerId);
        $count = 0;
        
        foreach ($mappings as $mapping) {
            if (($mapping['tenant_id'] ?? null) === $businessId) {
                if ($this->repository->deleteByScreenAndPrinter(
                    $mapping['screen_id'], 
                    $printerId, 
                    $businessId
                )) {
                    $count++;
                }
            }
        }
        
        return $count;
    }
    
    /**
     * Bulk assign printer to multiple screens
     * @param string $printerId
     * @param array $screenIds Array of screen IDs
     * @param string $businessId
     * @param int $priority Default priority
     * @return array Statistics
     */
    public function bulkAssignPrinterToScreens(string $printerId, array $screenIds, string $businessId, int $priority = 1): array {
        if (empty($printerId) || empty($businessId)) {
            return ['success' => false, 'error' => 'Invalid printer or business ID'];
        }
        
        // Verify printer exists and belongs to business
        try {
            $printerService = \App\Core\DependencyFactory::getPrinterService();
            $printer = $printerService->getPrinterById($printerId);
            if (!$printer || (($printer['tenant_id'] ?? null) !== $businessId)) {
                return ['success' => false, 'error' => 'Printer not found or unauthorized'];
            }
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Printer verification failed'];
        }
        
        $assigned = 0;
        $skipped = 0;
        $errors = [];
        
        foreach ($screenIds as $screenId) {
            try {
                if ($this->assignPrinter($screenId, $printerId, $businessId, $priority)) {
                    $assigned++;
                } else {
                    $skipped++;
                    $errors[] = "Screen $screenId: Assignment failed";
                }
            } catch (\Exception $e) {
                $errors[] = "Screen $screenId: " . $e->getMessage();
            }
        }
        
        return [
            'success' => true,
            'assigned' => $assigned,
            'skipped' => $skipped,
            'total' => count($screenIds),
            'errors' => $errors
        ];
    }
    
    /**
     * Update printer screen assignments (remove old, add new)
     * @param string $printerId
     * @param array $screenIds New screen IDs
     * @param string $businessId
     * @return array Result
     */
    public function updatePrinterScreenAssignments(string $printerId, array $screenIds, string $businessId): array {
        // 1. Remove all existing assignments
        $removed = $this->removeFromAllScreens($printerId, $businessId);
        
        // 2. Add new assignments
        $result = $this->bulkAssignPrinterToScreens($printerId, $screenIds, $businessId);
        $result['removed'] = $removed;
        
        return $result;
    }
}
