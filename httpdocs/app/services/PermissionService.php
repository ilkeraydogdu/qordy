<?php
namespace App\Services;

use App\Core\BaseService;
use App\Repositories\SystemPermissionRepository;

/**
 * Permission Service
 * Handles permission-related business logic
 */
class PermissionService extends BaseService {

    public function __construct(SystemPermissionRepository $repository) {
        parent::__construct($repository);
    }

    /**
     * Get all permissions
     * @return array
     */
    public function getAll(): array {
        try {
            return $this->repository->getAll();
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('PermissionService::getAll error', [
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
            return $this->repository->getById($permissionId);
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('PermissionService::getById error', [
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
            return $this->repository->getByKey($permissionKey);
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('PermissionService::getByKey error', [
                    'error' => $e->getMessage(),
                    'permission_key' => $permissionKey
                ]);
            }
            return null;
        }
    }

    /**
     * Get permissions by role
     * @param string $role Role ID or role code
     * @return array
     */
    public function getByRole(string $role): array {
        try {
            return $this->repository->getByRole($role);
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('PermissionService::getByRole error', [
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
     * @return array
     */
    public function getPermissionKeysByRole(string $role): array {
        try {
            return $this->repository->getPermissionKeysByRole($role);
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('PermissionService::getPermissionKeysByRole error', [
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
     * @return bool
     */
    public function create(array $data): bool {
        try {
            $result = $this->repository->create($data);
            return $result !== false;
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('PermissionService::create error', [
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
    public function update(string $permissionId, array $data): bool {
        try {
            return $this->repository->updatePermission($permissionId, $data);
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('PermissionService::update error', [
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
    public function delete(string $permissionId): bool {
        try {
            return $this->repository->deletePermission($permissionId);
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('PermissionService::delete error', [
                    'error' => $e->getMessage(),
                    'permission_id' => $permissionId
                ]);
            }
            return false;
        }
    }

    /**
     * Assign permission to role
     * @param string $role Role ID or role code
     * @param string $permissionId
     * @return bool
     */
    public function assignToRole(string $role, string $permissionId): bool {
        try {
            return $this->repository->assignToRole($role, $permissionId);
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('PermissionService::assignToRole error', [
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
     * @param string $permissionId
     * @return bool
     */
    public function removeFromRole(string $role, string $permissionId): bool {
        try {
            return $this->repository->removeFromRole($role, $permissionId);
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('PermissionService::removeFromRole error', [
                    'error' => $e->getMessage(),
                    'role' => $role,
                    'permission_id' => $permissionId
                ]);
            }
            return false;
        }
    }
}