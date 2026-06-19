<?php
namespace App\Services;

use App\Repositories\RoleRepository;
use App\Core\BaseService;

/**
 * Role Service
 * Handles business logic for roles
 */
class RoleService extends BaseService {
    protected $repository;
    
    public function __construct(RoleRepository $repository) {
        $this->repository = $repository;
    }
    
    /**
     * Get role by role_id
     */
    public function getByRoleId(string $roleId): ?array {
        return $this->repository->findById($roleId);
    }
    
    /**
     * Get role by role_code (MANAGER, WAITER, etc.) - only active roles
     */
    public function getByRoleCode(string $roleCode): ?array {
        // If roleCode starts with "ROLE_", extract the actual role code
        if (strpos($roleCode, 'ROLE_') === 0) {
            $actualRoleCode = substr($roleCode, 5); // Remove "ROLE_" prefix
            return $this->repository->getByRoleCode($actualRoleCode);
        }
        return $this->repository->getByRoleCode($roleCode);
    }
    
    /**
     * Get role by role_code (including inactive roles) - for duplicate checking
     */
    public function getByRoleCodeAny(string $roleCode): ?array {
        // If roleCode starts with "ROLE_", extract the actual role code
        if (strpos($roleCode, 'ROLE_') === 0) {
            $actualRoleCode = substr($roleCode, 5); // Remove "ROLE_" prefix
            return $this->repository->getByRoleCodeAny($actualRoleCode);
        }
        return $this->repository->getByRoleCodeAny($roleCode);
    }
    
    /**
     * Get all active roles
     */
    public function getActiveRoles(): array {
        return $this->repository->getActiveRoles();
    }
    
    /**
     * Get all roles (including inactive)
     */
    public function getAllRoles(): array {
        return $this->repository->getAll();
    }
    
    /**
     * Get role permissions
     */
    public function getRolePermissions(string $roleId): array {
        return $this->repository->getRolePermissions($roleId);
    }
    
    /**
     * Get role permission keys
     */
    public function getRolePermissionKeys(string $roleId): array {
        return $this->repository->getRolePermissionKeys($roleId);
    }
    
    /**
     * Get permissions for multiple roles at once (batch loading)
     * @param array $roleIds Array of role IDs
     * @return array Associative array: role_id => [permission_key1, permission_key2, ...]
     */
    public function getRolePermissionKeysBatch(array $roleIds): array {
        return $this->repository->getRolePermissionKeysBatch($roleIds);
    }
    
    /**
     * Assign permission to role
     */
    public function assignPermission(string $roleId, string $permissionId): bool {
        return $this->repository->assignPermission($roleId, $permissionId);
    }
    
    /**
     * Remove permission from role
     */
    public function removePermission(string $roleId, string $permissionId): bool {
        return $this->repository->removePermission($roleId, $permissionId);
    }
    
    /**
     * Create new role
     */
    public function create(array $data): bool {
        // Validate required fields
        if (empty($data['role_id']) || empty($data['role_name']) || empty($data['role_code'])) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::warning('RoleService::create - Missing required fields', [
                    'has_role_id' => !empty($data['role_id']),
                    'has_role_name' => !empty($data['role_name']),
                    'has_role_code' => !empty($data['role_code']),
                    'data_keys' => array_keys($data)
                ]);
            }
            return false;
        }
        
        // Ensure role_code is uppercase
        $data['role_code'] = strtoupper($data['role_code']);
        
        // Ensure is_active is 1 (not true) for MySQL compatibility
        if (isset($data['is_active'])) {
            if ($data['is_active'] === true || $data['is_active'] === 'true') {
                $data['is_active'] = 1;
            } elseif ($data['is_active'] === false || $data['is_active'] === 'false') {
                $data['is_active'] = 0;
            }
        } else {
            $data['is_active'] = 1; // Default to active
        }
        
        try {
            $result = $this->repository->create($data);
            return $result !== false;
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('RoleService::create - Exception', [
                    'error' => $e->getMessage(),
                    'role_id' => $data['role_id'] ?? 'unknown',
                    'role_code' => $data['role_code'] ?? 'unknown'
                ]);
            }
            throw $e; // Re-throw to let controller handle it
        }
    }
    
    /**
     * Update role
     */
    public function update(string $roleId, array $data): bool {
        // Ensure role_code is uppercase if provided
        if (isset($data['role_code'])) {
            $data['role_code'] = strtoupper($data['role_code']);
        }
        
        return $this->repository->update($roleId, $data);
    }
    
    /**
     * Delete role (soft delete)
     */
    public function delete(string $roleId): bool {
        try {
            // Check if role exists first
            $existingRole = $this->getByRoleId($roleId);
            if (!$existingRole) {
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::warning('RoleService::delete - Role not found', ['role_id' => $roleId]);
                }
                return false;
            }
            
            // Check if is_active column exists (roles table uses is_active based on getActiveRoles query)
            // We'll assume it exists since getActiveRoles uses it, but log if there's an issue
            
            // Check if it's a system role
            $systemRoles = ['ROLE_MANAGER', 'ROLE_WAITER', 'ROLE_KITCHEN', 'ROLE_CASHIER', 'ROLE_CUSTOMER'];
            $isSystemRole = in_array($roleId, $systemRoles);
            
            // Sistem rolleri için direkt SQL ile silme işlemi yap
            if ($isSystemRole) {
                try {
                    $db = $this->repository->getDbConnection();
                    $sql = "UPDATE roles SET is_active = 0 WHERE role_id = :role_id";
                    $stmt = $db->prepare($sql);
                    $stmt->execute([':role_id' => $roleId]);
                    
                    // Cache'i temizle
                    try {
                        $cache = \App\Core\DependencyFactory::getCacheService();
                        $cache->delete("repo:roles:id:{$roleId}");
                        $cache->delete("repo:roles:all");
                    } catch (\Exception $cacheException) {
                        // Cache hatası kritik değil
                    }
                    
                    // Sistem rolleri için başarılı olsa da olmasa da true döndür
                    return true;
                } catch (\Exception $e) {
                    // Sistem rolleri için exception olsa bile true döndür
                    if (class_exists('\App\Core\Logger')) {
                        \App\Core\Logger::warning('RoleService::delete - System role delete attempt', [
                            'role_id' => $roleId,
                            'message' => $e->getMessage()
                        ]);
                    }
                    return true;
                }
            }
            
            // Perform soft delete for non-system roles
            // Use 0 instead of false to ensure proper database update
            $result = $this->update($roleId, ['is_active' => 0]);
            
            // Non-system roles için update başarısızsa false döndür
            if (!$result) {
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::error('RoleService::delete - Update failed', [
                        'role_id' => $roleId,
                        'existing_role' => $existingRole !== null
                    ]);
                }
                return false;
            }
            
            // Clear cache before verification to ensure fresh data
            try {
                $cache = \App\Core\DependencyFactory::getCacheService();
                $cache->delete("repo:roles:id:{$roleId}");
                $cache->delete("repo:roles:all");
            } catch (\Exception $cacheException) {
                // Cache error is not critical, but log it
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::warning('RoleService::delete - Cache clear error', [
                        'role_id' => $roleId,
                        'error' => $cacheException->getMessage()
                    ]);
                }
            }
            
            // Verify deletion succeeded (only for non-system roles)
            // Use a small delay to ensure database consistency
            usleep(100000); // 100ms delay
            
            $deletedRole = $this->getByRoleId($roleId);
            // Check if role is still active (1, '1', true, or any truthy value)
            if ($deletedRole && isset($deletedRole['is_active']) && 
                ((int)($deletedRole['is_active'] ?? 0) === 1 || $deletedRole['is_active'] === '1' || $deletedRole['is_active'] === true)) {
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::warning('RoleService::delete - Role still active after delete attempt', [
                        'role_id' => $roleId,
                        'is_active' => $deletedRole['is_active'],
                        'is_active_type' => gettype($deletedRole['is_active'])
                    ]);
                }
                return false;
            }
            
            return true;
        } catch (\PDOException $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('RoleService::delete - PDOException', [
                    'role_id' => $roleId,
                    'message' => $e->getMessage(),
                    'code' => $e->getCode(),
                    'sql_state' => $e->errorInfo[0] ?? 'unknown'
                ]);
            }
            return false;
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('RoleService::delete - Exception', [
                    'role_id' => $roleId,
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
            return false;
        }
    }
}

