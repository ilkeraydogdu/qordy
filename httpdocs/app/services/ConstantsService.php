<?php
namespace App\Services;

use App\Repositories\ConstantsRepository;
use App\Core\BaseService;

/**
 * Constants Service
 * Handles business logic for system constants with caching
 */
class ConstantsService extends BaseService {
    protected $repository;
    private static $cache = [];
    
    public function __construct(ConstantsRepository $repository) {
        $this->repository = $repository;
    }
    
    /**
     * Get constant by type and key (with cache)
     */
    public function getByTypeAndKey(string $type, string $key): ?array {
        $cacheKey = "{$type}:{$key}";
        if (isset(self::$cache[$cacheKey])) {
            return self::$cache[$cacheKey];
        }
        
        $constant = $this->repository->getByTypeAndKey($type, $key);
        if ($constant) {
            self::$cache[$cacheKey] = $constant;
        }
        return $constant;
    }
    
    /**
     * Get all constants by type (with cache)
     */
    public function getByType(string $type): array {
        $cacheKey = "type:{$type}";
        if (isset(self::$cache[$cacheKey])) {
            return self::$cache[$cacheKey];
        }
        
        $constants = $this->repository->getByType($type);
        self::$cache[$cacheKey] = $constants;
        return $constants;
    }
    
    /**
     * Get constant value by type and key
     */
    public function getValue(string $type, string $key): ?string {
        $constant = $this->getByTypeAndKey($type, $key);
        return $constant ? $constant['constant_value'] : null;
    }
    
    /**
     * Get constant label (localized)
     */
    public function getLabel(string $type, string $key, string $lang = 'tr'): ?string {
        $constant = $this->getByTypeAndKey($type, $key);
        if (!$constant) {
            return null;
        }
        
        $labelField = $lang === 'en' ? 'label_en' : 'label_tr';
        return $constant[$labelField] ?? $constant['label_tr'] ?? $constant['constant_key'];
    }
    
    /**
     * Get all constants as key-value array
     */
    public function getAsKeyValue(string $type): array {
        $cacheKey = "keyvalue:{$type}";
        if (isset(self::$cache[$cacheKey])) {
            return self::$cache[$cacheKey];
        }
        
        $result = $this->repository->getAsKeyValue($type);
        self::$cache[$cacheKey] = $result;
        return $result;
    }
    
    /**
     * Get all constants as key-label array (localized)
     */
    public function getAsKeyLabel(string $type, string $lang = 'tr'): array {
        $cacheKey = "keylabel:{$type}:{$lang}";
        if (isset(self::$cache[$cacheKey])) {
            return self::$cache[$cacheKey];
        }
        
        $result = $this->repository->getAsKeyLabel($type, $lang);
        self::$cache[$cacheKey] = $result;
        return $result;
    }
    
    /**
     * Get role constants
     */
    public function getRoles(): array {
        return $this->getByType('ROLE');
    }
    
    /**
     * Get role codes as array
     */
    public function getRoleCodes(): array {
        return array_keys($this->getAsKeyValue('ROLE'));
    }
    
    /**
     * Get order statuses
     */
    public function getOrderStatuses(): array {
        return $this->getByType('ORDER_STATUS');
    }
    
    /**
     * Get order status codes as array
     */
    public function getOrderStatusCodes(): array {
        return array_keys($this->getAsKeyValue('ORDER_STATUS'));
    }
    
    /**
     * Get table statuses
     */
    public function getTableStatuses(): array {
        return $this->getByType('TABLE_STATUS');
    }
    
    /**
     * Get table status codes as array
     */
    public function getTableStatusCodes(): array {
        return array_keys($this->getAsKeyValue('TABLE_STATUS'));
    }
    
    /**
     * Get payment methods
     */
    public function getPaymentMethods(): array {
        return $this->getByType('PAYMENT_METHOD');
    }
    
    /**
     * Get payment method codes as array
     */
    public function getPaymentMethodCodes(): array {
        return array_keys($this->getAsKeyValue('PAYMENT_METHOD'));
    }
    
    /**
     * Get production points as key-value array
     */
    public function getProductionPoints(): array {
        return $this->getAsKeyValue('PRODUCTION_POINT');
    }
    
    /**
     * Get production point codes as array
     */
    public function getProductionPointCodes(): array {
        return array_keys($this->getAsKeyValue('PRODUCTION_POINT'));
    }
    
    /**
     * Clear cache
     */
    public function clearCache(): void {
        self::$cache = [];
    }
    
    /**
     * Clear cache for specific type
     */
    public function clearCacheForType(string $type): void {
        foreach (array_keys(self::$cache) as $key) {
            if (strpos($key, $type) === 0 || strpos($key, "type:{$type}") === 0) {
                unset(self::$cache[$key]);
            }
        }
    }
}

