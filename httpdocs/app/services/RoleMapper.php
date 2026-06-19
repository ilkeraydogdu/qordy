<?php
namespace App\Services;

/**
 * Role Mapper Service
 * Dynamically maps role names to role codes from database
 */
class RoleMapper {
    private static $instance = null;
    private $roleService = null;
    private $roleCache = null; // Cache for roles from database
    private $roleMapping = null; // Dynamic mapping from database
    
    private function __construct() {
        $this->loadRolesFromDatabase();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Load roles from database dynamically
     */
    private function loadRolesFromDatabase(): void {
        try {
            require_once __DIR__ . '/../core/DependencyFactory.php';
            $this->roleService = \App\Core\DependencyFactory::getRoleService();
            $roles = $this->roleService->getActiveRoles();
            
            $this->roleCache = [];
            $this->roleMapping = [];
            
            foreach ($roles as $role) {
                $roleId = $role['role_id'];
                $roleCode = strtoupper($role['role_code'] ?? '');
                $roleName = $role['role_name'] ?? '';
                $isActive = isset($role['is_active']) ? (bool)$role['is_active'] : true;
                
                // Cache by role_id (cache all roles, but check is_active when using)
                $this->roleCache[$roleId] = $role;
                
                // Only create mapping for ACTIVE roles
                // This prevents inactive roles like SUBDOMAIN from being mapped
                if ($isActive) {
                    // Create mapping: role_name (Turkish/English) => role_code
                    if (!empty($roleName)) {
                        $normalizedName = strtolower(trim($roleName));
                        $this->roleMapping[$normalizedName] = $roleCode;
                        
                        // Also map common variations
                        $variations = $this->getRoleNameVariations($roleName);
                        foreach ($variations as $variation) {
                            $this->roleMapping[strtolower($variation)] = $roleCode;
                        }
                    }
                    
                    // Map role_code itself
                    if (!empty($roleCode)) {
                        $this->roleMapping[strtolower($roleCode)] = $roleCode;
                    }
                }
            }
        } catch (\Exception $e) {
            // Fallback to hardcoded mapping if database fails
            error_log("RoleMapper: Failed to load roles from database: " . $e->getMessage());
            $this->loadFallbackMapping();
        }
    }
    
    /**
     * Get role name variations for mapping
     */
    private function getRoleNameVariations(string $roleName): array {
        $variations = [];
        $lower = strtolower($roleName);
        
        // Common Turkish variations
        $turkishVariations = [
            'yönetici' => ['yonetici', 'manager', 'admin', 'administrator'],
            'garson' => ['waiter'],
            'mutfak' => ['kitchen'],
            'kasiyer' => ['cashier'],
            'müşteri' => ['musteri', 'customer'],
        ];
        
        foreach ($turkishVariations as $turkish => $vars) {
            if (strpos($lower, $turkish) !== false) {
                $variations = array_merge($variations, $vars);
            }
        }
        
        return $variations;
    }
    
    /**
     * Fallback mapping if database fails
     */
    private function loadFallbackMapping(): void {
        $this->roleMapping = [
            'yönetici' => 'MANAGER',
            'yonetici' => 'MANAGER',
            'manager' => 'MANAGER',
            'admin' => 'ADMIN',
            'administrator' => 'ADMINISTRATOR',
            'garson' => 'WAITER',
            'waiter' => 'WAITER',
            'mutfak' => 'KITCHEN',
            'kitchen' => 'KITCHEN',
            'kasiyer' => 'CASHIER',
            'cashier' => 'CASHIER',
            'müşteri' => 'CUSTOMER',
            'musteri' => 'CUSTOMER',
            'customer' => 'CUSTOMER',
        ];
    }
    
    /**
     * Normalize role to standard role_code
     * @param string $role Raw role from database or input (can be role_id, role_code, or role_name)
     * @return string Normalized role_code (MANAGER, WAITER, etc.) or empty string if invalid
     */
    public function normalizeRole(string $role): string {
        if (empty($role)) {
            return '';
        }
        
        // Reload cache if empty (in case roles were added after initialization)
        if ($this->roleMapping === null || empty($this->roleMapping)) {
            $this->loadRolesFromDatabase();
        }
        
        $normalized = strtolower(trim($role));
        
        // Check if it's a role_id (starts with ROLE_)
        if (strpos($role, 'ROLE_') === 0) {
            if ($this->roleCache && isset($this->roleCache[$role])) {
                $roleCode = strtoupper($this->roleCache[$role]['role_code'] ?? '');
                // Validate that role is active
                if (!empty($roleCode) && isset($this->roleCache[$role]['is_active']) && $this->roleCache[$role]['is_active']) {
                    return $roleCode;
                }
                // If role is inactive, return empty string
                return '';
            }
        }
        
        // Check dynamic mapping
        if (isset($this->roleMapping[$normalized])) {
            $mappedRole = $this->roleMapping[$normalized];
            // Validate that mapped role is active
            if ($this->roleCache) {
                foreach ($this->roleCache as $roleData) {
                    if (strtoupper($roleData['role_code'] ?? '') === $mappedRole) {
                        // Check if role is active
                        if (isset($roleData['is_active']) && $roleData['is_active']) {
                            return $mappedRole;
                        }
                        // Role exists but is inactive
                        return '';
                    }
                }
            }
            return $mappedRole;
        }
        
        // If not found, try uppercase version (might already be normalized)
        $upperRole = strtoupper(trim($role));
        
        // Check if it's a valid role_code in cache
        if ($this->roleCache) {
            foreach ($this->roleCache as $roleData) {
                if (strtoupper($roleData['role_code'] ?? '') === $upperRole) {
                    // Validate that role is active
                    if (isset($roleData['is_active']) && $roleData['is_active']) {
                        return $upperRole;
                    }
                    // Role exists but is inactive
                    return '';
                }
            }
        }
        
        // CRITICAL: Don't return invalid roles as uppercase fallback
        // This prevents roles like "SUBDOMAIN" from being accepted
        // Return empty string instead
        return '';
    }
    
    /**
     * Check if a role is valid (exists in database and is active)
     * @param string $role Role to check
     * @return bool True if role is valid and active
     */
    public function isValidRole(string $role): bool {
        if (empty($role)) {
            return false;
        }
        
        if ($this->roleCache === null || empty($this->roleCache)) {
            $this->loadRolesFromDatabase();
        }
        
        $normalized = $this->normalizeRole($role);
        
        // normalizeRole already checks for active roles, so if it returns empty, role is invalid
        if (empty($normalized)) {
            return false;
        }
        
        // Double-check that role exists and is active
        if ($this->roleCache) {
            foreach ($this->roleCache as $roleData) {
                if (strtoupper($roleData['role_code'] ?? '') === $normalized) {
                    // Check if role is active
                    $isActive = isset($roleData['is_active']) ? (bool)$roleData['is_active'] : true;
                    return $isActive;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Get all valid role codes from database
     * @return array List of valid role codes
     */
    public function getValidRoles(): array {
        if ($this->roleCache === null || empty($this->roleCache)) {
            $this->loadRolesFromDatabase();
        }
        
        $roles = [];
        if ($this->roleCache) {
            foreach ($this->roleCache as $roleData) {
                if (!empty($roleData['role_code'])) {
                    $roles[] = strtoupper($roleData['role_code']);
                }
            }
        }
        
        return $roles;
    }
    
    /**
     * Get role label (from database)
     * @param string $role Normalized role code or role_id
     * @return string Role label
     */
    public function getRoleLabel(string $role): string {
        if ($this->roleCache === null || empty($this->roleCache)) {
            $this->loadRolesFromDatabase();
        }
        
        // Check if it's role_id
        if (strpos($role, 'ROLE_') === 0 && $this->roleCache && isset($this->roleCache[$role])) {
            return $this->roleCache[$role]['role_name'] ?? $role;
        }
        
        // Check by role_code
        $normalized = strtoupper(trim($role));
        if ($this->roleCache) {
            foreach ($this->roleCache as $roleData) {
                if (strtoupper($roleData['role_code'] ?? '') === $normalized) {
                    return $roleData['role_name'] ?? $role;
                }
            }
        }
        
        return $role;
    }
    
    /**
     * Get role_id from role_code
     * @param string $roleCode Role code (MANAGER, WAITER, etc.)
     * @return string|null Role ID or null
     */
    public function getRoleId(string $roleCode): ?string {
        if ($this->roleCache === null || empty($this->roleCache)) {
            $this->loadRolesFromDatabase();
        }
        
        $normalized = strtoupper(trim($roleCode));
        if ($this->roleCache) {
            foreach ($this->roleCache as $roleData) {
                if (strtoupper($roleData['role_code'] ?? '') === $normalized) {
                    return $roleData['role_id'] ?? null;
                }
            }
        }
        
        return null;
    }
    
    /**
     * Get role_code from role_id
     * @param string $roleId Role ID (ROLE_MANAGER, etc.)
     * @return string|null Role code or null
     */
    public function getRoleCode(string $roleId): ?string {
        if ($this->roleCache === null || empty($this->roleCache)) {
            $this->loadRolesFromDatabase();
        }
        
        // Check if role_id exists in cache
        if ($this->roleCache && isset($this->roleCache[$roleId])) {
            return strtoupper($this->roleCache[$roleId]['role_code'] ?? '');
        }
        
        // Also check if role_id matches any role_id in cache (case-insensitive)
        $normalizedRoleId = strtoupper(trim($roleId));
        if ($this->roleCache) {
            foreach ($this->roleCache as $cachedRoleId => $roleData) {
                if (strtoupper($cachedRoleId) === $normalizedRoleId) {
                    return strtoupper($roleData['role_code'] ?? '');
                }
            }
        }
        
        return null;
    }
    
    /**
     * Clear cache and reload roles from database
     * Call this after roles are created, updated, or deleted
     */
    public function clearCache(): void {
        $this->roleCache = null;
        $this->roleMapping = null;
        $this->loadRolesFromDatabase();
    }
    
    /**
     * Reset singleton instance (for testing or cache clearing)
     */
    public static function resetInstance(): void {
        self::$instance = null;
    }
}

