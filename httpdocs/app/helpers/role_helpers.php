<?php
/**
 * Role Helper Functions
 * Dynamic role-related helper functions using ConstantsService
 */

if (!function_exists('getAllRoles')) {
    /**
     * Get all active roles from roles table (not constants table)
     * @return array Array of roles with role_code and labels
     */
    function getAllRoles(): array {
        try {
            require_once __DIR__ . '/../core/DependencyFactory.php';
            $roleService = \App\Core\DependencyFactory::getRoleService();
            $roles = $roleService->getActiveRoles();
            
            // Transform roles table format to expected format
            $formattedRoles = [];
            foreach ($roles as $role) {
                $roleCode = $role['role_code'] ?? '';
                $roleId = $role['role_id'] ?? '';
                $roleName = $role['role_name'] ?? $roleCode;
                
                // Extract role code from role_id if needed (e.g., ROLE_MANAGER -> MANAGER)
                if (strpos($roleId, 'ROLE_') === 0 && empty($roleCode)) {
                    $roleCode = substr($roleId, 5);
                }
                
                $formattedRoles[] = [
                    'constant_key' => $roleCode,
                    'constant_value' => $roleId,
                    'label_tr' => $roleName,
                    'label_en' => $roleName, // Use same name for English if not available
                    'role_code' => $roleCode,
                    'role_id' => $roleId,
                    'role_name' => $roleName,
                ];
            }
            
            // If no roles found in database, return empty array (don't use hardcoded fallback)
            return $formattedRoles;
        } catch (\Exception $e) {
            // Log error but return empty array instead of hardcoded fallback
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('getAllRoles error', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
            return [];
        }
    }
}

if (!function_exists('getRoleLabel')) {
    /**
     * Get role label from roles table (localized)
     * @param string $roleCode Role code (MANAGER, WAITER, etc.)
     * @param string $lang Language code (tr, en)
     * @return string Role label
     */
    function getRoleLabel(string $roleCode, string $lang = 'tr'): string {
        try {
            require_once __DIR__ . '/../core/DependencyFactory.php';
            $roleService = \App\Core\DependencyFactory::getRoleService();
            
            // Try to get role by role_code
            $role = $roleService->getByRoleCode($roleCode);
            
            if ($role && isset($role['role_name'])) {
                return $role['role_name'];
            }
            
            // If not found, try with ROLE_ prefix
            if (strpos($roleCode, 'ROLE_') !== 0) {
                $role = $roleService->getByRoleCode('ROLE_' . $roleCode);
                if ($role && isset($role['role_name'])) {
                    return $role['role_name'];
                }
            }
            
            // Fallback to role code if not found
            return $roleCode;
        } catch (\Exception $e) {
            // Log error but return role code as fallback
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::warning('getRoleLabel error', [
                    'role_code' => $roleCode,
                    'error' => $e->getMessage()
                ]);
            }
            return $roleCode;
        }
    }
}

if (!function_exists('hasPermissionForRole')) {
    /**
     * Check if current user has permission to access role-based content
     * Uses permission system instead of direct role checks
     * @param string $permission Permission key
     * @return bool
     */
    function hasPermissionForRole(string $permission): bool {
        try {
            require_once __DIR__ . '/../core/Authorization.php';
            $auth = \App\Core\Authorization::getInstance();
            return $auth->hasPermission($permission);
        } catch (\Exception $e) {
            return false;
        }
    }
}

if (!function_exists('getCurrentUserRole')) {
    /**
     * Get current user's role code
     * @return string|null Role code or null if not logged in
     */
    function getCurrentUserRole(): ?string {
        \App\Core\SessionManager::ensureSession();
        return $_SESSION['role'] ?? null;
    }
}

if (!function_exists('canAccessRole')) {
    /**
     * Check if user can access content for specific roles
     * Uses permission-based check instead of direct role comparison
     * @param array $allowedRoles Array of role codes
     * @param string $permission Permission key to check
     * @return bool
     */
    function canAccessRole(array $allowedRoles, string $permission): bool {
        $currentRole = getCurrentUserRole();
        if (!$currentRole) {
            return false;
        }
        
        // Use permission-based check
        return hasPermissionForRole($permission);
    }
}

