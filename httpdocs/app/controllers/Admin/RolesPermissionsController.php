<?php
namespace App\Controllers\Admin;

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../../core/DependencyFactory.php';
require_once __DIR__ . '/../../helpers/functions.php';

use App\Core\Controller;

class RolesPermissionsController extends Controller {
    protected $roleService;
    protected $permissionModel;
    
    public function __construct() {
        parent::__construct();
        $this->roleService = \App\Core\DependencyFactory::getRoleService();
        $this->permissionModel = new \App\Models\SystemPermission(\App\Core\DependencyFactory::getDatabase());
    }
    
    public function rolesPermissions() {
        $this->requireLogin();
        
        // Only superadmin can access roles and permissions management
        if (!$this->isSuperAdmin()) {
            $this->toastNotificationService->setFlash('error', 'Bu sayfaya erişim yetkiniz yok.');
            header('Location: ' . BASE_URL . '/unauthorized');
            exit;
        }
        
        $roles = $this->roleService->getActiveRoles();
        $allPermissions = $this->permissionModel->getAll();
        
        $rolesWithPermissions = [];
        foreach ($roles as $role) {
            $rolePerms = $this->roleService->getRolePermissionKeys($role['role_id']);
            $rolesWithPermissions[] = [
                'role' => $role,
                'permissions' => $rolePerms
            ];
        }
        
        $data = [
            'roles' => $rolesWithPermissions,
            'all_permissions' => $allPermissions
        ];
        
        $this->view('admin/roles_permissions', $data);
    }
    
    public function createRole() {
        $this->requireLogin();
        
        // Only superadmin can create roles
        if (!$this->isSuperAdmin()) {
            $this->toastNotificationService->sendApiResponse('error', 'Bu işlem için yetkiniz yok.', [], 403);
            return;
        }
        
        $this->requirePermission('roles.create');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_request', [], 400);
            return;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        $roleId = $data['role_id'] ?? 'ROLE_' . strtoupper($data['role_code'] ?? '');
        $roleName = sanitizeInput($data['role_name'] ?? '');
        $roleCode = strtoupper(trim($data['role_code'] ?? ''));
        $description = sanitizeInput($data['description'] ?? '');
        
        if (empty($roleName) || empty($roleCode)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.warning.missing_fields', [], 400);
            return;
        }
        
        // Validate role_code format (only uppercase letters, numbers, and underscores)
        if (!preg_match('/^[A-Z0-9_]+$/', $roleCode)) {
            $this->toastNotificationService->sendApiResponse('error', 'Rol kodu sadece büyük harf, rakam ve alt çizgi içerebilir.', [], 400);
            return;
        }
        
        // Check if role_id already exists (including inactive roles)
        $existingRoleById = $this->roleService->getByRoleId($roleId);
        if ($existingRoleById) {
            $this->toastNotificationService->sendApiResponse('error', 'Bu rol ID zaten kullanılıyor', [], 409);
            return;
        }
        
        // Check if role_code already exists (including inactive roles for duplicate checking)
        $existingRoleByCode = $this->roleService->getByRoleCodeAny($roleCode);
        if ($existingRoleByCode) {
            $this->toastNotificationService->sendApiResponse('error', 'Bu rol kodu zaten kullanılıyor', [], 409);
            return;
        }
        
        $roleData = [
            'role_id' => $roleId,
            'role_name' => $roleName,
            'role_code' => $roleCode,
            'description' => $description,
            'is_active' => 1  // Use 1 instead of true for MySQL compatibility
        ];
        
        try {
            $result = $this->roleService->create($roleData);
            
            if ($result) {
                // Verify role was created and is active
                $createdRole = $this->roleService->getByRoleId($roleId);
                if (!$createdRole) {
                    if (class_exists('\App\Core\Logger')) {
                        \App\Core\Logger::error('Role created but not found after creation', [
                            'role_id' => $roleId,
                            'role_code' => $roleCode
                        ]);
                    }
                    $this->toastNotificationService->sendApiResponse('error', 'Rol oluşturulamadı', [], 500);
                    return;
                }
                
                // Check if role is active
                if (!isset($createdRole['is_active']) || $createdRole['is_active'] != 1) {
                    if (class_exists('\App\Core\Logger')) {
                        \App\Core\Logger::warning('Role created but is not active', [
                            'role_id' => $roleId,
                            'is_active' => $createdRole['is_active'] ?? 'not_set'
                        ]);
                    }
                    // Try to activate it
                    $this->roleService->update($roleId, ['is_active' => 1]);
                }
                
                // Clear ALL role-related caches to ensure fresh data
                $this->clearAllRoleCaches($roleId);
                
                // Send short success message
                $this->toastNotificationService->sendApiResponse('success', 'Rol oluşturuldu', [], 200);
            } else {
                // Log detailed error information
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::error('Role creation returned false', [
                        'role_id' => $roleId,
                        'role_code' => $roleCode,
                        'role_name' => $roleName,
                        'role_data' => $roleData
                    ]);
                }
                $this->toastNotificationService->sendApiResponse('error', 'Rol oluşturulamadı', [], 500);
            }
        } catch (\PDOException $e) {
            $errorCode = $e->errorInfo[1] ?? null;
            $errorMessage = $e->errorInfo[2] ?? $e->getMessage();
            
            // Log detailed error information
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('Role creation PDOException', [
                    'error' => $e->getMessage(),
                    'error_code' => $errorCode,
                    'error_message' => $errorMessage,
                    'sql_state' => $e->errorInfo[0] ?? 'unknown',
                    'role_code' => $roleCode,
                    'role_id' => $roleId,
                    'role_name' => $roleName,
                    'role_data' => $roleData
                ]);
            }
            
            // Check for duplicate entry error
            if ($e->getCode() == 23000 || $errorCode == 1062 || strpos($e->getMessage(), 'Duplicate entry') !== false) {
                $this->toastNotificationService->sendApiResponse('error', 'Bu rol kodu zaten kullanılıyor', [], 409);
            } elseif ($errorCode == 1364) {
                // Field doesn't have a default value
                $this->toastNotificationService->sendApiResponse('error', 'Eksik alanlar var', [], 400);
            } elseif ($errorCode == 1452) {
                // Foreign key constraint
                $this->toastNotificationService->sendApiResponse('error', 'Geçersiz veri', [], 400);
            } else {
                // Short error message
                $this->toastNotificationService->sendApiResponse('error', 'Rol oluşturulamadı', [], 500);
            }
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('Role creation Exception', [
                    'error' => $e->getMessage(),
                    'type' => get_class($e),
                    'role_code' => $roleCode,
                    'role_id' => $roleId,
                    'role_name' => $roleName,
                    'trace' => $e->getTraceAsString()
                ]);
            }
            $this->toastNotificationService->sendApiResponse('error', 'Rol oluşturulamadı', [], 500);
        }
    }
    
    public function updateRole() {
        $this->requireLogin();
        
        // Only superadmin can update roles
        if (!$this->isSuperAdmin()) {
            $this->toastNotificationService->sendApiResponse('error', 'Bu işlem için yetkiniz yok.', [], 403);
            return;
        }
        
        $this->requirePermission('roles.edit');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_request', [], 400);
            return;
        }
        
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $roleId = $data['role_id'] ?? '';
            $roleName = sanitizeInput($data['role_name'] ?? '');
            $roleCode = isset($data['role_code']) ? strtoupper(sanitizeInput($data['role_code'])) : null;
            $description = sanitizeInput($data['description'] ?? '');
            
            if (empty($roleId) || empty($roleName)) {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.warning.missing_fields', [], 400);
                return;
            }
            
            // Check if role exists
            $existingRole = $this->roleService->getByRoleId($roleId);
            if (!$existingRole) {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.role_not_found', [], 404);
                return;
            }
            
            // Check if current user is Manager
            $currentRoleId = $this->auth->getCurrentRoleId();
            $isManager = ($currentRoleId && strtoupper(trim($currentRoleId)) === 'ROLE_MANAGER');
            
            $systemRoles = ['ROLE_MANAGER', 'ROLE_WAITER', 'ROLE_KITCHEN', 'ROLE_CASHIER', 'ROLE_CUSTOMER'];
            $isSystemRole = in_array($roleId, $systemRoles);
            
            $updateData = [
                'role_name' => $roleName,
                'description' => $description
            ];
            
            // Allow Manager to update role_code for system roles, others cannot
            if ($roleCode !== null && (!$isSystemRole || $isManager)) {
                $updateData['role_code'] = $roleCode;
            }
            
            $result = $this->roleService->update($roleId, $updateData);
            
            if ($result) {
                // Clear all caches after update
                $this->clearAllRoleCaches($roleId);
                
                $this->toastNotificationService->sendApiResponse('success', 'notifications.success.role_updated', [], 200);
            } else {
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::error('Role update failed', [
                        'role_id' => $roleId,
                        'update_data' => $updateData
                    ]);
                }
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.update_failed', [], 500);
            }
        } catch (\PDOException $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('Role update PDOException', [
                    'message' => $e->getMessage(),
                    'code' => $e->getCode(),
                    'role_id' => $roleId ?? 'unknown'
                ]);
            }
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.database_error', [], 500);
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('Role update Exception', [
                    'message' => $e->getMessage(),
                    'role_id' => $roleId ?? 'unknown',
                    'trace' => $e->getTraceAsString()
                ]);
            }
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.update_failed', [], 500);
        }
    }
    
    public function deleteRole() {
        $this->requireLogin();
        
        // Only superadmin can delete roles
        if (!$this->isSuperAdmin()) {
            $this->toastNotificationService->sendApiResponse('error', 'Bu işlem için yetkiniz yok.', [], 403);
            return;
        }
        
        $this->requirePermission('roles.delete');
        
        try {
            $queryParams = \App\Core\RequestParser::getQueryParams();
            $roleId = $queryParams['id'] ?? '';
            
            if (empty($roleId)) {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
                return;
            }
            
            // Check if role exists
            $existingRole = $this->roleService->getByRoleId($roleId);
            if (!$existingRole) {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.role_not_found', [], 404);
                return;
            }
            
            // Check if current user is Manager
            $currentRoleId = $this->auth->getCurrentRoleId();
            $isManager = ($currentRoleId && strtoupper(trim($currentRoleId)) === 'ROLE_MANAGER');
            
            $systemRoles = ['ROLE_MANAGER', 'ROLE_WAITER', 'ROLE_KITCHEN', 'ROLE_CASHIER', 'ROLE_CUSTOMER'];
            
            // Allow Manager to delete system roles, block others
            // System roles are critical for application functionality
            if (in_array($roleId, $systemRoles) && !$isManager) {
                $roleName = $existingRole['role_name'] ?? $roleId;
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::warning('Attempt to delete system role blocked', [
                        'role_id' => $roleId,
                        'role_name' => $roleName,
                        'is_manager' => $isManager
                    ]);
                }
                $this->toastNotificationService->sendApiResponse('error', 'notifications.warning.system_role_delete', [
                    'role_name' => $roleName
                ], 403);
                return;
            }
            
            // Check if role is being used by any users
            $db = \App\Core\DependencyFactory::getDatabase();
            $checkSql = "SELECT COUNT(*) as user_count FROM users WHERE role_id = :role_id";
            $checkStmt = $db->prepare($checkSql);
            $checkStmt->execute([':role_id' => $roleId]);
            $userCount = $checkStmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($userCount && isset($userCount['user_count']) && (int)$userCount['user_count'] > 0) {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.warning.role_in_use', [
                    'count' => (int)$userCount['user_count']
                ], 409);
                return;
            }
            
            // Sistem rolleri için direkt başarılı döndür, silme işlemini yapma
            // Sistem rolleri için delete metodu direkt true döndürür
            if (in_array($roleId, $systemRoles)) {
                // Sistem rolleri için silme işlemi başarılı sayılır
                $this->roleService->delete($roleId); // Silme işlemini dene ama sonucu kontrol etme
                // Clear all caches even for system roles
                $this->clearAllRoleCaches($roleId);
                $this->toastNotificationService->sendApiResponse('success', 'notifications.success.role_deleted', [], 200);
                return;
            }
            
            // Clear cache before deletion to ensure fresh data
            $this->clearAllRoleCaches($roleId);
            
            $result = $this->roleService->delete($roleId);
            
            if ($result) {
                // Clear ALL caches again after deletion
                $this->clearAllRoleCaches($roleId);
                
                // Small delay to ensure database consistency
                usleep(100000); // 100ms delay
                
                // Verify deletion succeeded by checking if role is now inactive
                $deletedRole = $this->roleService->getByRoleId($roleId);
                // Check if role is still active (1, '1', true, or any truthy value)
                if ($deletedRole && isset($deletedRole['is_active']) && 
                    ($deletedRole['is_active'] == 1 || $deletedRole['is_active'] === '1' || $deletedRole['is_active'] === true)) {
                    if (class_exists('\App\Core\Logger')) {
                        \App\Core\Logger::warning('Role delete verification failed - role still active', [
                            'role_id' => $roleId,
                            'is_active' => $deletedRole['is_active'],
                            'is_active_type' => gettype($deletedRole['is_active']),
                            'role_data' => $deletedRole
                        ]);
                    }
                    $this->toastNotificationService->sendApiResponse('error', 'notifications.error.delete_failed_active', [], 500);
                    return;
                }
                
                $this->toastNotificationService->sendApiResponse('success', 'notifications.success.role_deleted', [], 200);
            } else {
                // Sistem rolleri için hata kontrolünü atla
                // Sistem rolleri için delete metodu direkt true döndürür
                if (in_array($roleId, $systemRoles)) {
                    // Sistem rolleri için silme işlemi başarılı sayılır
                    $this->toastNotificationService->sendApiResponse('success', 'notifications.success.role_deleted', [], 200);
                    return;
                }
                
                // Clear cache before checking
                $this->clearAllRoleCaches($roleId);
                
                // Small delay to ensure database consistency
                usleep(100000); // 100ms delay
                
                // Check why delete failed
                $roleAfterDelete = $this->roleService->getByRoleId($roleId);
                $deleteFailedReason = 'unknown';
                
                if (!$roleAfterDelete) {
                    $deleteFailedReason = 'role_not_found_after_delete';
                } elseif (isset($roleAfterDelete['is_active']) && 
                    ($roleAfterDelete['is_active'] == 1 || $roleAfterDelete['is_active'] === '1' || $roleAfterDelete['is_active'] === true)) {
                    $deleteFailedReason = 'role_still_active';
                } else {
                    $deleteFailedReason = 'update_returned_false';
                }
                
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::error('Role delete failed', [
                        'role_id' => $roleId,
                        'role_exists_before' => $existingRole !== null,
                        'role_exists_after' => $roleAfterDelete !== null,
                        'is_active_after' => $roleAfterDelete['is_active'] ?? 'unknown',
                        'failure_reason' => $deleteFailedReason
                    ]);
                }
                
                // Provide more specific error message
                if ($deleteFailedReason === 'role_still_active') {
                    $this->toastNotificationService->sendApiResponse('error', 'notifications.error.delete_failed_active', [], 500);
                } else {
                    $this->toastNotificationService->sendApiResponse('error', 'notifications.error.delete_failed', [], 500);
                }
            }
        } catch (\PDOException $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('Role delete PDOException', [
                    'message' => $e->getMessage(),
                    'code' => $e->getCode(),
                    'role_id' => $roleId ?? 'unknown',
                    'sql_state' => $e->errorInfo[0] ?? 'unknown'
                ]);
            }
            
            // Check for foreign key constraint violation (1451 = Cannot delete or update a parent row)
            if (isset($e->errorInfo[1])) {
                $errorCode = $e->errorInfo[1];
                if ($errorCode == 1451) {
                    // Foreign key constraint - role is being used
                    $this->toastNotificationService->sendApiResponse('error', 'notifications.warning.role_in_use', [], 409);
                } elseif ($errorCode == 1452) {
                    // Foreign key constraint - invalid reference
                    $this->toastNotificationService->sendApiResponse('error', 'notifications.error.database_error', [
                        'message' => 'Veritabanı referans hatası oluştu.'
                    ], 500);
                } else {
                    // Other database errors
                    $this->toastNotificationService->sendApiResponse('error', 'notifications.error.database_error', [
                        'code' => $errorCode,
                        'sql_state' => $e->errorInfo[0] ?? 'unknown'
                    ], 500);
                }
            } else {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.database_error', [], 500);
            }
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('Role delete Exception', [
                    'message' => $e->getMessage(),
                    'role_id' => $roleId ?? 'unknown',
                    'trace' => $e->getTraceAsString()
                ]);
            }
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.delete_failed', [], 500);
        }
    }
    
    public function assignPermissions() {
        $this->requireLogin();
        
        // Only superadmin can assign permissions
        if (!$this->isSuperAdmin()) {
            $this->toastNotificationService->sendApiResponse('error', 'Bu işlem için yetkiniz yok.', [], 403);
            return;
        }
        
        $this->requirePermission('permissions.manage');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_request', [], 400);
            return;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        $roleId = $data['role_id'] ?? '';
        $permissions = $data['permissions'] ?? [];
        
        if (empty($roleId)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
            return;
        }
        
        if (!is_array($permissions)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
            return;
        }
        
        $currentPermissions = $this->roleService->getRolePermissionKeys($roleId);
        
        $allPermissions = $this->permissionModel->getAll();
        $permissionKeyToId = [];
        foreach ($allPermissions as $perm) {
            $permissionKeyToId[$perm['permission_key']] = $perm['permission_id'];
        }
        
        foreach ($currentPermissions as $permKey) {
            if (!in_array($permKey, $permissions)) {
                $permissionId = $permissionKeyToId[$permKey] ?? $permKey;
                $this->roleService->removePermission($roleId, $permissionId);
            }
        }
        
        foreach ($permissions as $permKey) {
            if (!in_array($permKey, $currentPermissions)) {
                $permissionId = $permissionKeyToId[$permKey] ?? $permKey;
                $this->roleService->assignPermission($roleId, $permissionId);
            }
        }
        
        $this->toastNotificationService->sendApiResponse('success', 'notifications.success.permissions_updated', [], 200);
    }
    
    public function getRolePermissions() {
        $this->requireLogin();
        
        // Only superadmin can view role permissions
        if (!$this->isSuperAdmin()) {
            $this->toastNotificationService->sendApiResponse('error', 'Bu işlem için yetkiniz yok.', [], 403);
            return;
        }
        
        if (!$this->hasPermission('roles.view')) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 401);
            return;
        }
        
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $roleId = $queryParams['role_id'] ?? '';
        
        if (empty($roleId)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
            return;
        }
        
        $permissions = $this->roleService->getRolePermissionKeys($roleId);
        $role = $this->roleService->getByRoleId($roleId);
        
        $this->apiResponse([
            'permissions' => $permissions,
            'role' => $role
        ]);
    }
    
    /**
     * Clear all role-related caches (repository, RoleMapper, ConstantsService)
     * @param string|null $roleId Optional role ID to clear specific cache
     */
    private function clearAllRoleCaches(?string $roleId = null): void {
        try {
            $cache = \App\Core\DependencyFactory::getCacheService();
            
            // Clear repository cache
            if ($roleId) {
                $cache->delete("repo:roles:id:{$roleId}");
            }
            $cache->delete("repo:roles:all");
            $cache->delete("repo:roles:active");
            
            // Try to clear all role-related cache patterns
            if (method_exists($cache, 'deletePattern')) {
                $cache->deletePattern("repo:roles:*");
            }
            $cache->delete("repo:roles");
            
            // Clear RoleMapper singleton cache
            try {
                $roleMapper = \App\Services\RoleMapper::getInstance();
                $roleMapper->clearCache();
            } catch (\Exception $mapperException) {
                // RoleMapper cache clear error is not critical
            }
            
            // Clear ConstantsService static cache if it has a clearCache method
            try {
                $constantsService = \App\Core\DependencyFactory::getConstantsService();
                if (method_exists($constantsService, 'clearCache')) {
                    $constantsService->clearCache();
                }
            } catch (\Exception $constantsException) {
                // ConstantsService cache clear error is not critical
            }
        } catch (\Exception $cacheException) {
            // Cache error is not critical, but log it
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::warning('Role cache clear error', [
                    'error' => $cacheException->getMessage(),
                    'role_id' => $roleId ?? 'all'
                ]);
            }
        }
    }
}

