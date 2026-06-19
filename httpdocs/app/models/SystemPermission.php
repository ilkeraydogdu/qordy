<?php
namespace App\Models;

require_once __DIR__ . '/../core/Model.php';

class SystemPermission extends \App\Core\Model {
    protected $table = 'system_permissions';
    
    public function getAll() {
        return $this->query()
            ->orderBy('permission_key')
            ->get();
    }
    
    public function getById($permissionId) {
        return $this->query()
            ->from('system_permissions')
            ->where('permission_id', $permissionId)
            ->first();
    }
    
    public function getByKey($permissionKey) {
        return $this->query()
            ->where('permission_key', $permissionKey)
            ->first();
    }
    
    /**
     * Get permissions by role (supports both role_id and role_code)
     * @param string $role Role ID or role code
     * @return array Permissions
     */
    public function getByRole($role) {
        $roleId = $this->normalizeRoleToId($role);
        return $this->query()
            ->select(['sp.*'])
            ->from('system_permissions sp')
            ->innerJoin('role_permissions rp', 'sp.permission_id', '=', 'rp.permission_id')
            ->where('rp.role_id', $roleId)
            ->orderBy('sp.permission_key')
            ->get();
    }
    
    /**
     * Get permission keys by role
     * @param string $role Role ID or role code
     * @return array Permission keys
     */
    public function getPermissionKeysByRole($role) {
        $permissions = $this->getByRole($role);
        return array_column($permissions, 'permission_key');
    }
    
    public function create($data) {
        if (!isset($data['permission_id'])) {
            $data['permission_id'] = $data['permission_key'];
        }

        // Remove fields that don't exist in database table
        $allowedFields = ['permission_id', 'permission_key', 'permission_name', 'description'];
        $filteredData = [];
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $filteredData[$field] = $data[$field];
            }
        }

        return $this->query()
            ->insert($filteredData);
    }
    
    public function updatePermission($permissionId, $data) {
        return $this->query()
            ->where('permission_id', $permissionId)
            ->update($data);
    }
    
    public function deletePermission($permissionId) {
        $this->deleteRolePermissions($permissionId);
        return $this->query()
            ->where('permission_id', $permissionId)
            ->delete();
    }
    
    /**
     * Assign permission to role (supports both role_id and role_code for backward compatibility)
     * @param string $role Role ID (ROLE_*) or role code (MANAGER, WAITER, etc.)
     * @param string $permissionId Permission ID
     * @return bool Success
     */
    public function assignToRole($role, $permissionId) {
        // Convert role_code to role_id if needed
        $roleId = $this->normalizeRoleToId($role);
        
        // Check for existing permission (use raw SQL to avoid table duplication)
        $existing = $this->db->prepare("SELECT 1 FROM role_permissions WHERE role_id = ? AND permission_id = ? LIMIT 1");
        $existing->execute([$roleId, $permissionId]);
        $result = $existing->fetch(\PDO::FETCH_ASSOC);

        if ($result) {
            return true;
        }

        // Note: role_permissions table has both 'role' and 'role_id' columns
        // Unique constraint is on (role_id, permission_id), so 'role' column is redundant
        // However, it's kept for backward compatibility. We'll include 'role' column in INSERT
        // TODO: Remove 'role' column from role_permissions table in future migration
        
        // Get role_code for the role column (for backward compatibility)
        $roleCode = '';
        try {
            require_once __DIR__ . '/../core/DependencyFactory.php';
            $roleService = \App\Core\DependencyFactory::getRoleService();
            $roleData = $roleService->getByRoleId($roleId);
            if ($roleData && isset($roleData['role_code'])) {
                $roleCode = $roleData['role_code'];
            }
        } catch (\Exception $e) {
            // Fall through - use empty string
        }

        // Insert new permission (include role column for backward compatibility)
        // Note: 'role' column is redundant but kept for backward compatibility
        $stmt = $this->db->prepare("INSERT IGNORE INTO role_permissions (role, role_id, permission_id) VALUES (?, ?, ?)");
        return $stmt->execute([$roleCode, $roleId, $permissionId]);
    }
    
    /**
     * Remove permission from role
     * @param string $role Role ID or role code
     * @param string $permissionId Permission ID
     * @return bool Success
     */
    public function removeFromRole($role, $permissionId) {
        $roleId = $this->normalizeRoleToId($role);
        return $this->query()
            ->from('role_permissions')
            ->where('role_id', $roleId)
            ->where('permission_id', $permissionId)
            ->delete();
    }
    
    /**
     * Normalize role to role_id
     * @param string $role Role ID or role code
     * @return string Role ID
     */
    private function normalizeRoleToId($role): string {
        // If already role_id, return it
        if (strpos($role, 'ROLE_') === 0) {
            return $role;
        }
        
        // Try to get from RoleService first
        try {
            require_once __DIR__ . '/../core/DependencyFactory.php';
            $roleService = \App\Core\DependencyFactory::getRoleService();
            $roleData = $roleService->getByRoleCode($role);
            if ($roleData && isset($roleData['role_id'])) {
                return $roleData['role_id'];
            }
        } catch (\Exception $e) {
            // Fall through to RoleMapper
        }
        
        // Fallback: Try RoleMapper
        try {
            require_once __DIR__ . '/../services/RoleMapper.php';
            $roleMapper = \App\Services\RoleMapper::getInstance();
            $mappedRoleId = $roleMapper->getRoleId($role);
            if ($mappedRoleId) {
                return $mappedRoleId;
            }
        } catch (\Exception $e) {
            // Fall through to final fallback
        }
        
        // Final fallback mapping (should not be needed after migration)
        $mapping = [
            'MANAGER' => 'ROLE_MANAGER',
            'WAITER' => 'ROLE_WAITER',
            'KITCHEN' => 'ROLE_KITCHEN',
            'CASHIER' => 'ROLE_CASHIER',
            'CUSTOMER' => 'ROLE_CUSTOMER',
        ];
        
        return $mapping[strtoupper($role)] ?? $role;
    }
    
    public function deleteRolePermissions($permissionId) {
        return $this->query()
            ->from('role_permissions')
            ->where('permission_id', $permissionId)
            ->delete();
    }
    
    public function getPermissionsAsArray() {
        $permissions = $this->getAll();
        $result = [];
        
        foreach ($permissions as $permission) {
            $result[$permission['permission_key']] = $permission['permission_name'];
        }
        
        return $result;
    }
    
    public function getRolePermissionsAsArray() {
        $results = $this->query()
            ->select(['rp.role', 'sp.permission_key'])
            ->from('role_permissions rp')
            ->innerJoin('system_permissions sp', 'rp.permission_id', '=', 'sp.permission_id')
            ->orderBy('rp.role')
            ->orderBy('sp.permission_key')
            ->get();
        
        $mapping = [];
        foreach ($results as $row) {
            $role = $row['role'];
            if (!isset($mapping[$role])) {
                $mapping[$role] = [];
            }
            $mapping[$role][] = $row['permission_key'];
        }
        
        return $mapping;
    }
}

