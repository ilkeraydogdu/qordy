<?php
namespace App\Repositories;

use App\Core\BaseRepository;

/**
 * System Permission Repository
 * Handles database operations for system permissions
 */
class SystemPermissionRepository extends BaseRepository {
    protected $table = 'system_permissions';
    protected $primaryKey = 'permission_id';

    /**
     * Get all permissions
     * @return array
     */
    public function getAll(): array {
        try {
            return $this->query()
                ->orderBy('permission_key')
                ->get();
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('SystemPermissionRepository::getAll error', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
            return [];
        }
    }

    /**
     * Get permission by ID
     * @param string $permissionId
     * @return array|null
     */
    public function getById(string $permissionId): ?array {
        try {
            return $this->query()
                ->where('permission_id', $permissionId)
                ->first();
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('SystemPermissionRepository::getById error', [
                    'error' => $e->getMessage(),
                    'permission_id' => $permissionId
                ]);
            }
            return null;
        }
    }

    /**
     * Get permission by key
     * @param string $permissionKey
     * @return array|null
     */
    public function getByKey(string $permissionKey): ?array {
        try {
            return $this->query()
                ->where('permission_key', $permissionKey)
                ->first();
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('SystemPermissionRepository::getByKey error', [
                    'error' => $e->getMessage(),
                    'permission_key' => $permissionKey
                ]);
            }
            return null;
        }
    }

    /**
     * Get permissions by role (supports both role_id and role_code)
     * @param string $role Role ID or role code
     * @return array Permissions
     */
    public function getByRole(string $role): array {
        try {
            $roleId = $this->normalizeRoleToId($role);
            return $this->query()
                ->select(['sp.*'])
                ->from('system_permissions sp')
                ->innerJoin('role_permissions rp', 'sp.permission_id', '=', 'rp.permission_id')
                ->where('rp.role_id', $roleId)
                ->orderBy('sp.permission_key')
                ->get();
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('SystemPermissionRepository::getByRole error', [
                    'error' => $e->getMessage(),
                    'role' => $role
                ]);
            }
            return [];
        }
    }

    /**
     * Get permission keys by role
     * @param string $role Role ID or role code
     * @return array Permission keys
     */
    public function getPermissionKeysByRole(string $role): array {
        try {
            $permissions = $this->getByRole($role);
            return array_column($permissions, 'permission_key');
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('SystemPermissionRepository::getPermissionKeysByRole error', [
                    'error' => $e->getMessage(),
                    'role' => $role
                ]);
            }
            return [];
        }
    }

    /**
     * Create new permission
     * @param array $data
     * @return mixed
     */
    public function create(array $data) {
        try {
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
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('SystemPermissionRepository::create error', [
                    'error' => $e->getMessage(),
                    'data' => $data
                ]);
            }
            return false;
        }
    }

    /**
     * Update permission
     * @param string $permissionId
     * @param array $data
     * @return bool
     */
    public function updatePermission(string $permissionId, array $data): bool {
        try {
            return $this->query()
                ->where('permission_id', $permissionId)
                ->update($data);
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('SystemPermissionRepository::updatePermission error', [
                    'error' => $e->getMessage(),
                    'permission_id' => $permissionId,
                    'data' => $data
                ]);
            }
            return false;
        }
    }

    /**
     * Delete permission
     * @param string $permissionId
     * @return bool
     */
    public function deletePermission(string $permissionId): bool {
        try {
            $this->deleteRolePermissions($permissionId);
            return $this->query()
                ->where('permission_id', $permissionId)
                ->delete();
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('SystemPermissionRepository::deletePermission error', [
                    'error' => $e->getMessage(),
                    'permission_id' => $permissionId
                ]);
            }
            return false;
        }
    }

    /**
     * Assign permission to role (supports both role_id and role_code for backward compatibility)
     * @param string $role Role ID (ROLE_*) or role code (MANAGER, WAITER, etc.)
     * @param string $permissionId Permission ID
     * @return bool Success
     */
    public function assignToRole(string $role, string $permissionId): bool {
        try {
            // Convert role_code to role_id if needed
            $roleId = $this->normalizeRoleToId($role);

            // Check for existing permission (use raw SQL to avoid table duplication)
            $existing = $this->db->prepare("SELECT 1 FROM role_permissions WHERE role_id = ? AND permission_id = ? LIMIT 1");
            $existing->execute([$roleId, $permissionId]);
            $result = $existing->fetch(\PDO::FETCH_ASSOC);

            if ($result) {
                return true;
            }

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
            $stmt = $this->db->prepare("INSERT IGNORE INTO role_permissions (role, role_id, permission_id) VALUES (?, ?, ?)");
            return $stmt->execute([$roleCode, $roleId, $permissionId]);
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('SystemPermissionRepository::assignToRole error', [
                    'error' => $e->getMessage(),
                    'role' => $role,
                    'permission_id' => $permissionId
                ]);
            }
            return false;
        }
    }

    /**
     * Remove permission from role
     * @param string $role Role ID or role code
     * @param string $permissionId Permission ID
     * @return bool Success
     */
    public function removeFromRole(string $role, string $permissionId): bool {
        try {
            $roleId = $this->normalizeRoleToId($role);
            return $this->query()
                ->from('role_permissions')
                ->where('role_id', $roleId)
                ->where('permission_id', $permissionId)
                ->delete();
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('SystemPermissionRepository::removeFromRole error', [
                    'error' => $e->getMessage(),
                    'role' => $role,
                    'permission_id' => $permissionId
                ]);
            }
            return false;
        }
    }

    /**
     * Normalize role to role_id
     * @param string $role Role ID or role code
     * @return string Role ID
     */
    private function normalizeRoleToId(string $role): string {
        try {
            // If already role_id, return it
            if (strpos($role, 'ROLE_') === 0) {
                return $role;
            }

            // Try to get from RoleService first
            require_once __DIR__ . '/../core/DependencyFactory.php';
            $roleService = \App\Core\DependencyFactory::getRoleService();
            $roleData = $roleService->getByRoleCode($role);
            if ($roleData && isset($roleData['role_id'])) {
                return $roleData['role_id'];
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
        } catch (\Exception $e) {
            // Final fallback mapping
            $mapping = [
                'MANAGER' => 'ROLE_MANAGER',
                'WAITER' => 'ROLE_WAITER',
                'KITCHEN' => 'ROLE_KITCHEN',
                'CASHIER' => 'ROLE_CASHIER',
                'CUSTOMER' => 'ROLE_CUSTOMER',
            ];

            return $mapping[strtoupper($role)] ?? $role;
        }
    }

    /**
     * Delete role permissions for a permission
     * @param string $permissionId Permission ID
     * @return bool Success
     */
    public function deleteRolePermissions(string $permissionId): bool {
        try {
            return $this->query()
                ->from('role_permissions')
                ->where('permission_id', $permissionId)
                ->delete();
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('SystemPermissionRepository::deleteRolePermissions error', [
                    'error' => $e->getMessage(),
                    'permission_id' => $permissionId
                ]);
            }
            return false;
        }
    }
}