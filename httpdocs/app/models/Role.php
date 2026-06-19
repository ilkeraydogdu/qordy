<?php
namespace App\Models;

require_once __DIR__ . '/../core/Model.php';

/**
 * Role Model
 * Handles role data operations
 */
class Role extends \App\Core\Model {
    protected $table = 'roles';
    
    /**
     * Get role by role_id
     */
    public function getByRoleId(string $roleId) {
        return $this->query()
            ->where('role_id', $roleId)
            ->first();
    }
    
    /**
     * Get role by role_code (MANAGER, WAITER, etc.)
     */
    public function getByRoleCode(string $roleCode) {
        return $this->query()
            ->where('role_code', strtoupper($roleCode))
            ->first();
    }
    
    /**
     * Get all active roles
     */
    public function getActiveRoles(): array {
        try {
            // Try with display_order first
            return $this->query()
                ->where('is_active', true)
                ->orderBy('display_order')
                ->get();
        } catch (\Exception $e) {
            // Fallback if display_order column doesn't exist
            return $this->query()
                ->where('is_active', true)
                ->orderBy('role_code')
                ->get();
        }
    }
    
    /**
     * Get all roles
     */
    public function getAllRoles(): array {
        try {
            // Try with display_order first
            return $this->query()
                ->orderBy('display_order')
                ->get();
        } catch (\Exception $e) {
            // Fallback if display_order column doesn't exist
            return $this->query()
                ->orderBy('role_code')
                ->get();
        }
    }
    
    /**
     * Create new role
     */
    public function createRole(array $data) {
        return $this->query()
            ->insert($data);
    }
    
    /**
     * Update role
     */
    public function updateRole(string $roleId, array $data) {
        return $this->query()
            ->where('role_id', $roleId)
            ->update($data);
    }
    
    /**
     * Delete role (soft delete by setting is_active = false)
     */
    public function deleteRole(string $roleId) {
        return $this->query()
            ->where('role_id', $roleId)
            ->update(['is_active' => false]);
    }
    
    /**
     * Get role permissions
     */
    public function getRolePermissions(string $roleId): array {
        $sql = "
            SELECT sp.permission_key, sp.permission_name, sp.description
            FROM role_permissions rp
            INNER JOIN system_permissions sp ON rp.permission_id = sp.permission_id
            WHERE rp.role_id = :role_id
            ORDER BY sp.permission_key
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':role_id' => $roleId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Get role permission keys (array of permission_key strings)
     */
    public function getRolePermissionKeys(string $roleId): array {
        $permissions = $this->getRolePermissions($roleId);
        return array_column($permissions, 'permission_key');
    }
}

