<?php
namespace App\Core\Auth;

/**
 * Role Manager
 * Handles role-based operations and role validation
 */
class RoleManager {
    private $roleService = null;
    private $db = null;
    
    public function __construct($db, $roleService) {
        $this->db = $db;
        $this->roleService = $roleService;
    }
    
    /**
     * Check if user has a specific role
     * @param string $role Role code or role ID
     * @param string|null $userRoleId User's role ID
     * @param string|null $userRoleCode User's role code
     * @return bool True if user has the role
     */
    public function hasRole(string $role, ?string $userRoleId = null, ?string $userRoleCode = null): bool {
        $normalizedRole = strtoupper(trim($role));
        $normalizedUserRoleCode = $userRoleCode ? strtoupper(trim($userRoleCode)) : null;
        
        // Check by role_id if available
        if ($userRoleId && $normalizedRole === strtoupper(trim($userRoleId))) {
            return true;
        }
        
        // Check by role_code
        if ($normalizedUserRoleCode && $normalizedRole === $normalizedUserRoleCode) {
            return true;
        }

        // Session'da sadece role_id (UUID) varken role_code yoksa veya farklı formatta olduğunda: DB'den çöz
        if ($this->roleService && $userRoleId) {
            try {
                $row = $this->roleService->getByRoleId($userRoleId);
                if (!empty($row['role_code']) && $normalizedRole === strtoupper(trim((string) $row['role_code']))) {
                    return true;
                }
            } catch (\Throwable $e) {
                // non-fatal
            }
        }

        return false;
    }
    
    /**
     * Check if user has any of the specified roles
     * @param array $roles Array of role codes or IDs
     * @param string|null $userRoleId User's role ID
     * @param string|null $userRoleCode User's role code
     * @return bool True if user has at least one role
     */
    public function hasAnyRole(array $roles, ?string $userRoleId = null, ?string $userRoleCode = null): bool {
        foreach ($roles as $role) {
            if ($this->hasRole($role, $userRoleId, $userRoleCode)) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Get current role from session
     * @return string|null Role ID or role code
     */
    public function getCurrentRole(): ?string {
        require_once __DIR__ . '/../SessionManager.php';
        \App\Core\SessionManager::ensureSession();
        
        // Try role_id first
        $roleId = \App\Core\SessionManager::get('role_id');
        if ($roleId) {
            return $roleId;
        }
        
        // Fallback to role_code
        $role = \App\Core\SessionManager::get('role');
        if ($role) {
            // Normalize role_code
            require_once __DIR__ . '/../../services/RoleMapper.php';
            $roleMapper = \App\Services\RoleMapper::getInstance();
            return $roleMapper->normalizeRole($role);
        }
        
        return null;
    }
    
    /**
     * Get current role ID from session
     * @return string|null Role ID
     */
    public function getCurrentRoleId(): ?string {
        require_once __DIR__ . '/../SessionManager.php';
        \App\Core\SessionManager::ensureSession();
        return \App\Core\SessionManager::get('role_id');
    }
    
    /**
     * Get all active roles
     * @return array Array of roles
     */
    public function getAllRoles(): array {
        if ($this->roleService) {
            try {
                return $this->roleService->getActiveRoles();
            } catch (\Exception $e) {
                error_log("RoleManager: Failed to get roles: " . $e->getMessage());
            }
        }
        return [];
    }
    
    /**
     * Ensure role_id is in session if user is logged in
     * @return void
     */
    public function ensureRoleIdInSession(): void {
        require_once __DIR__ . '/../SessionManager.php';
        \App\Core\SessionManager::ensureSession();
        
        if (!\App\Core\SessionManager::get('logged_in')) {
            return;
        }
        
        $roleId = \App\Core\SessionManager::get('role_id');
        if ($roleId) {
            return; // Already has role_id
        }
        
        $role = \App\Core\SessionManager::get('role');
        if (!$role) {
            return; // No role in session
        }
        
        // Try to convert role_code to role_id
        if ($this->roleService) {
            try {
                $roleData = $this->roleService->getByRoleCode($role);
                if ($roleData && isset($roleData['role_id'])) {
                    \App\Core\SessionManager::set('role_id', $roleData['role_id']);
                }
            } catch (\Exception $e) {
                error_log("RoleManager: Failed to auto-add role_id to session: " . $e->getMessage());
            }
        }
    }
}

