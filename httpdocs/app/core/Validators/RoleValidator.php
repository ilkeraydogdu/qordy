<?php
namespace App\Core\Validators;

/**
 * Role Validator
 * Single source of truth for role validation logic
 * Prevents code duplication and ensures consistency across the application
 */
class RoleValidator {
    /**
     * Valid role codes (without ROLE_ prefix)
     */
    private static $validRoles = [
        'MANAGER',
        'WAITER',
        'KITCHEN',
        'CASHIER',
        'CUSTOMER',
        'ADMIN',
        'ADMINISTRATOR',
        'BUSINESS_MANAGER',
        'SUPER_ADMIN',
        'QODMIN',
        'GARSON',
        'KASIYER',
        'MUTFAK',
        'CHEF',
        'STOCK_MANAGER',
        'STOK_YONETICISI',
        'HR_MANAGER',
        'IK_YONETICISI',
    ];
    
    /**
     * Validate if a role is valid
     * 
     * @param string|null $role Role code to validate (can include ROLE_ prefix)
     * @return bool True if role is valid
     */
    public static function isValid(?string $role): bool {
        if (empty($role)) {
            return false;
        }
        
        $normalizedRole = self::normalize($role);
        return !empty($normalizedRole) && in_array($normalizedRole, self::$validRoles, true);
    }
    
    /**
     * Normalize role code
     * Removes ROLE_ prefix and converts to uppercase
     * 
     * @param string $role Role code to normalize
     * @return string Normalized role code
     */
    public static function normalize(string $role): string {
        $normalized = strtoupper(trim($role));
        // Remove ROLE_ prefix if present
        if (strpos($normalized, 'ROLE_') === 0) {
            $normalized = substr($normalized, 5);
        }
        return $normalized;
    }
    
    /**
     * Get list of valid roles
     * 
     * @return array List of valid role codes
     */
    public static function getValidRoles(): array {
        return self::$validRoles;
    }
    
    /**
     * Check if role should be cleared from session
     * Invalid roles like "SUBDOMAIN" should be cleared to prevent redirect loops
     * 
     * @param string|null $role Role code to check
     * @param string|null $roleId Role ID (alternative identifier)
     * @return bool True if role should be cleared
     */
    public static function shouldClear(?string $role, ?string $roleId = null): bool {
        // If roleId exists and starts with ROLE_, it's valid
        if (!empty($roleId) && strpos($roleId, 'ROLE_') === 0) {
            return false;
        }
        
        // If role is empty and no roleId, should clear
        if (empty($role) && empty($roleId)) {
            return true;
        }
        
        // If role exists but is invalid, should clear
        if (!empty($role) && !self::isValid($role)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Validate role using RoleMapper (if available)
     * Falls back to basic validation if RoleMapper is not available
     * 
     * @param string $role Role code to validate
     * @return bool True if role is valid
     */
    public static function validateWithMapper(string $role): bool {
        // Try to use RoleMapper for advanced validation
        try {
            require_once __DIR__ . '/../../services/RoleMapper.php';
            $roleMapper = \App\Services\RoleMapper::getInstance();
            if (method_exists($roleMapper, 'isValidRole')) {
                return $roleMapper->isValidRole($role);
            }
        } catch (\Exception $e) {
            // RoleMapper not available, use basic validation
        }
        
        // Fallback to basic validation
        return self::isValid($role);
    }
}
