<?php
namespace App\Services;

use App\Core\DependencyFactory;
use App\Core\Logger;

/**
 * Label Service
 * Centralized service for retrieving system labels (roles, statuses, categories, etc.)
 * Replaces global helper functions with OOP approach
 */
class LabelService {
    private static $roleLabelCache = null;
    private static $statusLabelCache = null;
    private static $statusColorCache = null;
    private static $expenseCategoryCache = null;
    private static $wasteReasonCache = null;
    private static $vehicleTypeCache = null;
    private static $integrationPlatformCache = null;
    
    /**
     * Get role label
     * @param string $role Role code
     * @return string Role label
     */
    public function getRoleLabel(string $role): string {
        if (self::$roleLabelCache === null) {
            try {
                $constantsService = DependencyFactory::getConstantsService();
                $currentLang = $this->getCurrentLanguage();
                $roleLabels = $constantsService->getAsKeyLabel('ROLE', $currentLang);
                self::$roleLabelCache = $roleLabels ?: [];
                
                if (empty(self::$roleLabelCache)) {
                    Logger::warning('No role labels found in database for language: ' . $currentLang);
                }
            } catch (\Exception $e) {
                Logger::error('Failed to load role labels from ConstantsService: ' . $e->getMessage());
                self::$roleLabelCache = [];
            }
        }
        
        return self::$roleLabelCache[$role] ?? $role;
    }
    
    /**
     * Get status label
     * @param string $status Status code
     * @return string Status label
     */
    public function getStatusLabel(string $status): string {
        if (self::$statusLabelCache === null) {
            try {
                require_once __DIR__ . '/../models/SystemLabel.php';
                $labelModel = new \App\Models\SystemLabel();
                $labels = $labelModel->getByType('status');
                self::$statusLabelCache = [];
                foreach ($labels as $label) {
                    self::$statusLabelCache[$label['label_key']] = $label['label_value_tr'];
                }
                
                if (empty(self::$statusLabelCache)) {
                    Logger::warning('No status labels found in database');
                }
            } catch (\Exception $e) {
                Logger::error('Failed to load status labels from SystemLabel: ' . $e->getMessage());
                self::$statusLabelCache = [];
            }
        }
        
        return self::$statusLabelCache[$status] ?? $status;
    }
    
    /**
     * Get status color
     * @param string $status Status code
     * @return string Color class
     */
    public function getStatusColor(string $status): string {
        if (self::$statusColorCache === null) {
            try {
                require_once __DIR__ . '/../models/SystemLabel.php';
                $labelModel = new \App\Models\SystemLabel();
                $labels = $labelModel->getByType('status');
                self::$statusColorCache = [];
                foreach ($labels as $label) {
                    self::$statusColorCache[$label['label_key']] = $label['color'] ?? 'secondary';
                }
            } catch (\Exception $e) {
                Logger::error('Failed to load status colors from SystemLabel: ' . $e->getMessage());
                self::$statusColorCache = [];
            }
        }
        
        return self::$statusColorCache[$status] ?? 'secondary';
    }
    
    /**
     * Get expense category label
     * @param string $category Category code
     * @return string Category label
     */
    public function getExpenseCategoryLabel(string $category): string {
        if (self::$expenseCategoryCache === null) {
            try {
                require_once __DIR__ . '/../models/SystemLabel.php';
                $labelModel = new \App\Models\SystemLabel();
                $labels = $labelModel->getByType('expense_category');
                self::$expenseCategoryCache = [];
                foreach ($labels as $label) {
                    self::$expenseCategoryCache[$label['label_key']] = $label['label_value_tr'];
                }
            } catch (\Exception $e) {
                Logger::error('Failed to load expense category labels: ' . $e->getMessage());
                self::$expenseCategoryCache = [];
            }
        }
        
        return self::$expenseCategoryCache[$category] ?? $category;
    }
    
    /**
     * Get waste reason label
     * @param string $reason Reason code
     * @return string Reason label
     */
    public function getWasteReasonLabel(string $reason): string {
        if (self::$wasteReasonCache === null) {
            try {
                require_once __DIR__ . '/../models/SystemLabel.php';
                $labelModel = new \App\Models\SystemLabel();
                $labels = $labelModel->getByType('waste_reason');
                self::$wasteReasonCache = [];
                foreach ($labels as $label) {
                    self::$wasteReasonCache[$label['label_key']] = $label['label_value_tr'];
                }
            } catch (\Exception $e) {
                Logger::error('Failed to load waste reason labels: ' . $e->getMessage());
                self::$wasteReasonCache = [];
            }
        }
        
        return self::$wasteReasonCache[$reason] ?? $reason;
    }
    
    /**
     * Get vehicle type label
     * @param string $type Vehicle type code
     * @return string Vehicle type label
     */
    public function getVehicleTypeLabel(string $type): string {
        if (self::$vehicleTypeCache === null) {
            try {
                require_once __DIR__ . '/../models/SystemLabel.php';
                $labelModel = new \App\Models\SystemLabel();
                $labels = $labelModel->getByType('vehicle_type');
                self::$vehicleTypeCache = [];
                foreach ($labels as $label) {
                    self::$vehicleTypeCache[$label['label_key']] = $label['label_value_tr'];
                }
            } catch (\Exception $e) {
                Logger::error('Failed to load vehicle type labels: ' . $e->getMessage());
                self::$vehicleTypeCache = [];
            }
        }
        
        return self::$vehicleTypeCache[$type] ?? $type;
    }
    
    /**
     * Get integration platform label
     * @param string $platform Platform name
     * @return string Platform label
     */
    public function getIntegrationPlatformLabel(string $platform): string {
        if (self::$integrationPlatformCache === null) {
            try {
                require_once __DIR__ . '/../models/SystemLabel.php';
                $labelModel = new \App\Models\SystemLabel();
                $labels = $labelModel->getByType('integration_platform');
                self::$integrationPlatformCache = [];
                foreach ($labels as $label) {
                    self::$integrationPlatformCache[$label['label_key']] = $label['label_value_tr'];
                }
            } catch (\Exception $e) {
                Logger::error('Failed to load integration platform labels: ' . $e->getMessage());
                self::$integrationPlatformCache = [];
            }
        }
        
        return self::$integrationPlatformCache[$platform] ?? $platform;
    }
    
    /**
     * Clear all caches
     */
    public function clearCache(): void {
        self::$roleLabelCache = null;
        self::$statusLabelCache = null;
        self::$statusColorCache = null;
        self::$expenseCategoryCache = null;
        self::$wasteReasonCache = null;
        self::$vehicleTypeCache = null;
        self::$integrationPlatformCache = null;
    }
    
    /**
     * Get current language
     * @return string Language code
     */
    private function getCurrentLanguage(): string {
        require_once __DIR__ . '/../helpers/translations.php';
        return getCurrentLanguage();
    }
}

