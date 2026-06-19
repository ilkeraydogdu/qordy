<?php
namespace App\Repositories;

use App\Core\BaseRepository;
use PDO;

/**
 * Role Repository
 * Handles database operations for roles
 */
class RoleRepository extends BaseRepository {
    protected $table = 'roles';
    protected $primaryKey = 'role_id';
    
    /**
     * Get role by role_code (only active roles)
     */
    public function getByRoleCode(string $roleCode): ?array {
        $sql = "SELECT * FROM {$this->table} WHERE role_code = :role_code AND is_active = 1 LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':role_code' => strtoupper($roleCode)]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }
    
    /**
     * Get role by role_code (including inactive roles - for duplicate checking)
     */
    public function getByRoleCodeAny(string $roleCode): ?array {
        $sql = "SELECT * FROM {$this->table} WHERE role_code = :role_code LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':role_code' => strtoupper($roleCode)]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }
    
    /**
     * Get all active roles
     * Sorted with MANAGER first, then by display_order, then by role_code
     * Uses cache for performance but can be cleared when roles are modified
     */
    public function getActiveRoles(): array {
        // Try cache first
        $cacheKey = $this->getCacheKey('active');
        $cache = $this->getCache();
        
        if ($cache->has($cacheKey)) {
            $cached = $cache->get($cacheKey);
            if (is_array($cached)) {
                return $cached;
            }
        }
        
        // Check if display_order column exists
        try {
            $hasDisplayOrder = \App\Core\DbSchema::hasColumn($this->table, 'display_order');

            if ($hasDisplayOrder) {
                // MANAGER first, then by display_order, then by role_code
                $sql = "SELECT * FROM {$this->table} 
                        WHERE is_active = 1 
                        ORDER BY 
                            CASE 
                                WHEN role_code = 'MANAGER' OR role_id = 'ROLE_MANAGER' THEN 0 
                                ELSE 1 
                            END ASC,
                            display_order ASC,
                            role_code ASC";
            } else {
                // MANAGER first, then by role_code
                $sql = "SELECT * FROM {$this->table} 
                        WHERE is_active = 1 
                        ORDER BY 
                            CASE 
                                WHEN role_code = 'MANAGER' OR role_id = 'ROLE_MANAGER' THEN 0 
                                ELSE 1 
                            END ASC,
                            role_code ASC";
            }
        } catch (\Exception $e) {
            // Fallback if check fails - MANAGER first, then by role_code
            $sql = "SELECT * FROM {$this->table} 
                    WHERE is_active = 1 
                    ORDER BY 
                        CASE 
                            WHEN role_code = 'MANAGER' OR role_id = 'ROLE_MANAGER' THEN 0 
                            ELSE 1 
                        END ASC,
                        role_code ASC";
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // Cache results for 5 minutes (shorter than findAll to ensure freshness)
        $cache->set($cacheKey, $results, 300);
        
        return $results;
    }
    
    /**
     * Get all roles (including inactive)
     */
    public function getAll(): array {
        // Check if display_order column exists
        try {
            $hasDisplayOrder = \App\Core\DbSchema::hasColumn($this->table, 'display_order');

            if ($hasDisplayOrder) {
                $sql = "SELECT * FROM {$this->table} ORDER BY display_order ASC, role_code ASC";
            } else {
                $sql = "SELECT * FROM {$this->table} ORDER BY role_code ASC";
            }
        } catch (\Exception $e) {
            // Fallback if check fails
            $sql = "SELECT * FROM {$this->table} ORDER BY role_code ASC";
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Get role permissions by role_id
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
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get role permission keys as array
     */
    public function getRolePermissionKeys(string $roleId): array {
        $permissions = $this->getRolePermissions($roleId);
        return array_column($permissions, 'permission_key');
    }
    
    /**
     * Get permissions for multiple roles at once (batch loading)
     * @param array $roleIds Array of role IDs
     * @return array Associative array: role_id => [permission_key1, permission_key2, ...]
     */
    public function getRolePermissionKeysBatch(array $roleIds): array {
        if (empty($roleIds)) {
            return [];
        }
        
        $placeholders = implode(',', array_fill(0, count($roleIds), '?'));
        $sql = "
            SELECT rp.role_id, sp.permission_key
            FROM role_permissions rp
            INNER JOIN system_permissions sp ON rp.permission_id = sp.permission_id
            WHERE rp.role_id IN ({$placeholders})
            ORDER BY rp.role_id, sp.permission_key
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($roleIds);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $permissionsByRole = [];
        foreach ($results as $row) {
            $roleId = $row['role_id'];
            if (!isset($permissionsByRole[$roleId])) {
                $permissionsByRole[$roleId] = [];
            }
            $permissionsByRole[$roleId][] = $row['permission_key'];
        }
        
        return $permissionsByRole;
    }
    
    /**
     * Assign permission to role
     */
    public function assignPermission(string $roleId, string $permissionId): bool {
        // Note: role_permissions table has both 'role' and 'role_id' columns
        // Unique constraint is on (role_id, permission_id), so 'role' column is redundant
        // However, it's kept for backward compatibility. We'll insert empty string for 'role' column
        // TODO: Remove 'role' column from role_permissions table in future migration
        
        // Try INSERT without 'role' column first (if column was removed)
        try {
            $sql = "INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (:role_id, :permission_id)";
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([
                ':role_id' => $roleId, 
                ':permission_id' => $permissionId
            ]);
            if ($result) {
                return true;
            }
        } catch (\PDOException $e) {
            // If column doesn't exist error, try with 'role' column (backward compatibility)
            if ($e->getCode() == '42S22' || strpos($e->getMessage(), "Unknown column 'role'") !== false) {
                // Column doesn't exist, retry without it (shouldn't happen, but handle it)
                return false;
            }
            // If 'role' column is required, use it
        }
        
        // Fallback: Include 'role' column for backward compatibility
        // Get role_code for the role column (if still needed)
        $roleCode = '';
        try {
            $role = $this->findById($roleId);
            if ($role && isset($role['role_code'])) {
                $roleCode = $role['role_code'];
            }
        } catch (\Exception $e) {
            // Fall through - use empty string
        }
        
        $sql = "INSERT IGNORE INTO role_permissions (role, role_id, permission_id) VALUES (:role, :role_id, :permission_id)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':role' => $roleCode,
            ':role_id' => $roleId, 
            ':permission_id' => $permissionId
        ]);
    }
    
    /**
     * Remove permission from role
     */
    public function removePermission(string $roleId, string $permissionId): bool {
        $sql = "DELETE FROM role_permissions WHERE role_id = :role_id AND permission_id = :permission_id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':role_id' => $roleId, ':permission_id' => $permissionId]);
    }
}

