<?php
namespace App\Services;

use App\Core\BaseService;
use App\Repositories\FeatureRepository;

/**
 * Feature Service
 * Handles feature flag management
 * 
 * @package App\Services
 */
class FeatureService extends BaseService {
    private $cache = [];

    /**
     * Constructor
     * @param FeatureRepository $repository Feature repository
     */
    public function __construct(FeatureRepository $repository) {
        parent::__construct($repository);
    }

    /**
     * Check if feature is enabled
     * @param string $featureKey Feature key
     * @return bool Is enabled
     */
    public function isEnabled(string $featureKey): bool {
        // Check cache first (keyed by tenant for tenant-specific results)
        $tenantId = null;
        try {
            if (class_exists('\App\Core\TenantContext')) {
                $tenantId = \App\Core\TenantContext::getId();
            }
        } catch (\Throwable $e) {}
        
        $cacheKey = $featureKey . ':' . ($tenantId ?? 'global');
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        // 1. Check global feature_settings (admin toggles)
        $enabled = $this->repository->isEnabled($featureKey);
        
        // 2. If globally disabled, check tenant's package_features override
        // A package can grant features even if the global toggle is off
        if (!$enabled && $tenantId) {
            $enabled = $this->checkPackageFeature($tenantId, $featureKey);
        }
        
        $this->cache[$cacheKey] = $enabled;
        return $enabled;
    }
    
    /**
     * Check if a feature is enabled via the tenant's package subscription
     */
    private function checkPackageFeature(string $tenantId, string $featureKey): bool {
        try {
            $subscriptionService = \App\Core\DependencyFactory::getSubscriptionService();
            $subscription = $subscriptionService->getCustomerSubscription($tenantId);
            
            if (!$subscription || ($subscription['status'] ?? '') !== 'active' || empty($subscription['package_id'])) {
                return false;
            }
            
            $db = \App\Core\DependencyFactory::getDatabase();
            $stmt = $db->prepare("
                SELECT feature_value, feature_type 
                FROM package_features 
                WHERE package_id = :package_id AND feature_key = :feature_key
            ");
            $stmt->execute(['package_id' => $subscription['package_id'], 'feature_key' => $featureKey]);
            $feature = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$feature) {
                return false;
            }
            
            switch ($feature['feature_type'] ?? 'boolean') {
                case 'boolean': return filter_var($feature['feature_value'], FILTER_VALIDATE_BOOLEAN);
                case 'unlimited': return true;
                case 'number': return (int)($feature['feature_value'] ?? 0) > 0;
                case 'string': return !empty($feature['feature_value']);
                default: return false;
            }
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Get feature config
     * @param string $featureKey Feature key
     * @return array Config array
     */
    public function getConfig(string $featureKey): array {
        $feature = $this->repository->getByKey($featureKey);
        
        if (!$feature) {
            return [];
        }

        // Return feature data as config (config_json column doesn't exist in migration)
        return [
            'description' => $feature['description'] ?? '',
            'is_enabled' => $feature['is_enabled'] ?? false
        ];
    }

    /**
     * Get all features
     * @param bool $enabledOnly Return only enabled features
     * @return array Features
     */
    public function getAll(bool $enabledOnly = false): array {
        if ($enabledOnly) {
            return $this->repository->getEnabled();
        }
        return $this->repository->getAll();
    }

    /**
     * Update feature status
     * @param string $featureKey Feature key
     * @param bool $enabled Enabled status
     * @return bool Success
     */
    public function updateStatus(string $featureKey, bool $enabled): bool {
        $result = $this->repository->updateStatus($featureKey, $enabled);
        
        // Clear cache
        unset($this->cache[$featureKey]);
        
        return $result;
    }

    /**
     * Update feature config
     * @param string $featureKey Feature key
     * @param array $config Config array
     * @return bool Success
     */
    public function updateConfig(string $featureKey, array $config): bool {
        return $this->repository->updateConfig($featureKey, $config);
    }

    /**
     * Clear cache
     */
    public function clearCache(): void {
        $this->cache = [];
    }
}

