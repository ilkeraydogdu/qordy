<?php
namespace App\Core\Helpers;

use App\Core\DependencyFactory;
use App\Services\ConstantsService;

/**
 * ConstantsHelper
 * Centralized helper for accessing system constants
 * Provides static methods to get constants without hardcoding values
 */
class ConstantsHelper {
    private static ?ConstantsService $service = null;
    
    /**
     * Get ConstantsService instance
     */
    private static function getService(): ConstantsService {
        if (self::$service === null) {
            self::$service = DependencyFactory::getConstantsService();
        }
        return self::$service;
    }
    
    /**
     * Get role code by key
     * @param string $key Role key (e.g., 'MANAGER', 'WAITER')
     * @return string Role code
     */
    public static function getRole(string $key): string {
        try {
            $roles = self::getService()->getRoleCodes();
            return $roles[$key] ?? $key;
        } catch (\Exception $e) {
            \App\Core\Logger::error("ConstantsHelper::getRole error: " . $e->getMessage());
            return $key; // Fallback to key itself
        }
    }
    
    /**
     * Get all role codes
     * @return array Role codes array
     */
    public static function getRoles(): array {
        try {
            return self::getService()->getRoleCodes();
        } catch (\Exception $e) {
            \App\Core\Logger::error("ConstantsHelper::getRoles error: " . $e->getMessage());
            return ['MANAGER', 'BUSINESS_MANAGER', 'WAITER', 'KITCHEN', 'CASHIER', 'CUSTOMER', 'ADMIN', 'ADMINISTRATOR'];
        }
    }
    
    /**
     * Check if role exists
     * @param string $role Role to check
     * @return bool True if role exists
     */
    public static function isValidRole(string $role): bool {
        $roles = self::getRoles();
        // Remove ROLE_ prefix if present for comparison
        $normalizedRole = strtoupper(str_replace('ROLE_', '', trim($role)));
        
        // Check against roles
        if (in_array($normalizedRole, $roles, true)) {
            return true;
        }
        
        // Also check with ROLE_ prefix
        if (in_array('ROLE_' . $normalizedRole, $roles, true)) {
            return true;
        }
        
        // Fallback: check if it's a manager-equivalent role
        $managerRoles = ['MANAGER', 'BUSINESS_MANAGER', 'ADMIN', 'ADMINISTRATOR'];
        return in_array($normalizedRole, $managerRoles, true);
    }
    
    /**
     * Get order status code by key
     * @param string $key Status key (e.g., 'PENDING', 'PREPARING')
     * @return string Status code
     */
    public static function getOrderStatus(string $key): string {
        try {
            $statuses = self::getService()->getOrderStatusCodes();
            return $statuses[$key] ?? $key;
        } catch (\Exception $e) {
            \App\Core\Logger::error("ConstantsHelper::getOrderStatus error: " . $e->getMessage());
            return $key;
        }
    }
    
    /**
     * Get all order status codes
     * @return array Order status codes array
     */
    public static function getOrderStatuses(): array {
        try {
            return self::getService()->getOrderStatusCodes();
        } catch (\Exception $e) {
            \App\Core\Logger::error("ConstantsHelper::getOrderStatuses error: " . $e->getMessage());
            return ['PENDING', 'PREPARING', 'READY', 'SERVED', 'CANCELLED', 'ISSUE', 'ON_DELIVERY', 'DELIVERED'];
        }
    }
    
    /**
     * Check if order status is valid
     * @param string $status Status to check
     * @return bool True if status is valid
     */
    public static function isValidOrderStatus(string $status): bool {
        $statuses = self::getOrderStatuses();
        return in_array($status, $statuses, true);
    }
    
    /**
     * Get table status code by key
     * @param string $key Status key (e.g., 'FREE', 'OCCUPIED')
     * @return string Status code
     */
    public static function getTableStatus(string $key): string {
        try {
            $statuses = self::getService()->getTableStatusCodes();
            return $statuses[$key] ?? $key;
        } catch (\Exception $e) {
            \App\Core\Logger::error("ConstantsHelper::getTableStatus error: " . $e->getMessage());
            return $key;
        }
    }
    
    /**
     * Get all table status codes
     * @return array Table status codes array
     */
    public static function getTableStatuses(): array {
        try {
            return self::getService()->getTableStatusCodes();
        } catch (\Exception $e) {
            \App\Core\Logger::error("ConstantsHelper::getTableStatuses error: " . $e->getMessage());
            return ['FREE', 'OCCUPIED', 'PAYMENT_PENDING', 'DIRTY', 'RESERVED'];
        }
    }
    
    /**
     * Check if table status is valid
     * @param string $status Status to check
     * @return bool True if status is valid
     */
    public static function isValidTableStatus(string $status): bool {
        $statuses = self::getTableStatuses();
        return in_array($status, $statuses, true);
    }
    
    /**
     * Get production point code by key
     * @param string $key Production point key (e.g., 'KITCHEN', 'BAR')
     * @return string Production point code
     */
    public static function getProductionPoint(string $key): string {
        try {
            $points = self::getService()->getProductionPoints();
            return $points[$key] ?? $key;
        } catch (\Exception $e) {
            \App\Core\Logger::error("ConstantsHelper::getProductionPoint error: " . $e->getMessage());
            return $key;
        }
    }
    
    /**
     * Get all production points
     * @return array Production points array (keys)
     */
    public static function getProductionPoints(): array {
        try {
            $points = self::getService()->getProductionPoints();
            return array_keys($points);
        } catch (\Exception $e) {
            \App\Core\Logger::error("ConstantsHelper::getProductionPoints error: " . $e->getMessage());
            return ['KITCHEN', 'BAR', 'SERVICE', 'NONE'];
        }
    }
    
    /**
     * Check if production point is valid
     * @param string $point Production point to check
     * @return bool True if production point is valid
     */
    public static function isValidProductionPoint(string $point): bool {
        $points = self::getProductionPoints();
        return in_array($point, $points, true);
    }
    
    /**
     * Get payment method code by key
     * @param string $key Payment method key (e.g., 'CASH', 'CREDIT_CARD')
     * @return string Payment method code
     */
    public static function getPaymentMethod(string $key): string {
        try {
            $methods = self::getService()->getPaymentMethodCodes();
            return $methods[$key] ?? $key;
        } catch (\Exception $e) {
            \App\Core\Logger::error("ConstantsHelper::getPaymentMethod error: " . $e->getMessage());
            return $key;
        }
    }
    
    /**
     * Get all payment method codes
     * @return array Payment method codes array
     */
    public static function getPaymentMethods(): array {
        try {
            return self::getService()->getPaymentMethodCodes();
        } catch (\Exception $e) {
            \App\Core\Logger::error("ConstantsHelper::getPaymentMethods error: " . $e->getMessage());
            return ['CASH', 'CREDIT_CARD', 'ONLINE_PAYMENT', 'OTHER'];
        }
    }
    
    /**
     * Check if payment method is valid
     * @param string $method Payment method to check
     * @return bool True if payment method is valid
     */
    public static function isValidPaymentMethod(string $method): bool {
        $methods = self::getPaymentMethods();
        return in_array($method, $methods, true);
    }
    
    /**
     * Get constant value by type and key
     * @param string $type Constant type (e.g., 'ROLE', 'ORDER_STATUS')
     * @param string $key Constant key
     * @return string|null Constant value or null if not found
     */
    public static function getConstant(string $type, string $key): ?string {
        try {
            return self::getService()->getValue($type, $key);
        } catch (\Exception $e) {
            \App\Core\Logger::error("ConstantsHelper::getConstant error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get constant label (localized)
     * @param string $type Constant type
     * @param string $key Constant key
     * @param string $lang Language code (default: 'tr')
     * @return string|null Constant label or null if not found
     */
    public static function getLabel(string $type, string $key, string $lang = 'tr'): ?string {
        try {
            return self::getService()->getLabel($type, $key, $lang);
        } catch (\Exception $e) {
            \App\Core\Logger::error("ConstantsHelper::getLabel error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get all constants as key-value array
     * @param string $type Constant type
     * @return array Key-value array
     */
    public static function getAsKeyValue(string $type): array {
        try {
            return self::getService()->getAsKeyValue($type);
        } catch (\Exception $e) {
            \App\Core\Logger::error("ConstantsHelper::getAsKeyValue error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get all constants as key-label array (localized)
     * @param string $type Constant type
     * @param string $lang Language code (default: 'tr')
     * @return array Key-label array
     */
    public static function getAsKeyLabel(string $type, string $lang = 'tr'): array {
        try {
            return self::getService()->getAsKeyLabel($type, $lang);
        } catch (\Exception $e) {
            \App\Core\Logger::error("ConstantsHelper::getAsKeyLabel error: " . $e->getMessage());
            return [];
        }
    }
}

