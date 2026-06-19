<?php
namespace App\Services;

use App\Core\BaseService;
use App\Repositories\BusinessSettingsRepository;
use App\Core\DependencyFactory;

class BusinessSettingsService extends BaseService {
    private $systemSettingsService;

    public function __construct(BusinessSettingsRepository $repository) {
        parent::__construct($repository);
        $this->systemSettingsService = DependencyFactory::getSystemSettingsService();
    }

    /**
     * Get business settings for a business
     * @param string $businessId Business ID
     * @return array Settings array
     */
    public function getSettings(string $businessId): array {
        $cache = \App\Core\DependencyFactory::getCacheService();
        $cacheKey = "business_settings:{$businessId}";
        
        return $cache->remember($cacheKey, function() use ($businessId) {
            $settings = $this->repository->getByBusinessId($businessId);
            
            // If no settings exist, return defaults
            if (!$settings) {
                return [
                    'waiter_delete_requires_approval' => 0
                ];
            }
            
            return $settings;
        }, 300); // Cache for 5 minutes (optimized for real-time updates)
    }

    /**
     * Get a specific setting value for a business
     * @param string $businessId Business ID
     * @param string $settingKey Setting key
     * @param mixed $defaultValue Default value if setting not found
     * @return mixed Setting value or default
     */
    public function getSetting(string $businessId, string $settingKey, $defaultValue = null) {
        $settings = $this->getSettings($businessId);
        return $settings[$settingKey] ?? $defaultValue;
    }

    /**
     * Update business settings
     * @param string $businessId Business ID
     * @param array $data Settings data
     * @return bool Success
     */
    public function updateSettings(string $businessId, array $data): bool {
        // Prepare data - convert boolean values
        $preparedData = [];
        
        if (isset($data['waiter_delete_requires_approval'])) {
            $preparedData['waiter_delete_requires_approval'] = (int)$data['waiter_delete_requires_approval'];
        }
        
        if (empty($preparedData)) {
            return false;
        }
        
        $result = $this->repository->createOrUpdate($businessId, $preparedData);
        
        if ($result) {
            // Invalidate cache
            $cache = \App\Core\DependencyFactory::getCacheService();
            $cache->delete("business_settings:{$businessId}");
        }
        
        return $result;
    }

    /**
     * Update a specific setting for a business
     * @param string $businessId Business ID
     * @param string $settingKey Setting key
     * @param mixed $value Setting value
     * @return bool Success
     */
    public function updateSetting(string $businessId, string $settingKey, $value): bool {
        $result = $this->repository->updateSetting($businessId, $settingKey, $value);
        
        if ($result) {
            // Invalidate cache
            $cache = \App\Core\DependencyFactory::getCacheService();
            $cache->delete("business_settings:{$businessId}");
        }
        
        return $result;
    }

    /**
     * Check if waiter delete/remove requires approval for a business
     * Falls back to system-wide setting if business setting doesn't exist
     * @param string $businessId Business ID
     * @return bool True if approval required
     */
    public function waiterDeleteRequiresApproval(string $businessId): bool {
        // Check if business settings exist
        $settings = $this->repository->getByBusinessId($businessId);
        
        if ($settings && isset($settings['waiter_delete_requires_approval'])) {
            // Business has explicitly configured this setting
            return (int)$settings['waiter_delete_requires_approval'] === 1;
        }
        
        // Business setting doesn't exist, fall back to system-wide setting for backward compatibility
        $systemSetting = $this->systemSettingsService->getSetting('order_edit_requires_approval', '1');
        return $systemSetting === '1';
    }
}
