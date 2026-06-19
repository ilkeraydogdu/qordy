<?php
namespace App\Controllers\Admin;

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../../core/DependencyFactory.php';
require_once __DIR__ . '/../../helpers/functions.php';
require_once __DIR__ . '/../../helpers/role_helpers.php';
require_once __DIR__ . '/../../helpers/translations.php';

use App\Core\Controller;
use App\Core\Helpers\ConstantsHelper;

class UsersController extends Controller {
    protected $userService;
    protected $roleService;
    protected $permissionModel;
    protected $personnelService;
    protected $leaveTypeService;
    
    public function __construct() {
        parent::__construct();
        $this->userService = \App\Core\DependencyFactory::getUserService();
        $this->roleService = \App\Core\DependencyFactory::getRoleService();
        $this->permissionModel = new \App\Models\SystemPermission(\App\Core\DependencyFactory::getDatabase());
        $this->personnelService = \App\Core\DependencyFactory::getPersonnelService();
        $this->leaveTypeService = \App\Core\DependencyFactory::getLeaveTypeService();
    }
    
    public function users() {
        $this->ensureTenantContext();
        $this->requirePermission('staff.view');
        
        // Super admin kontrolü
        $isSuperAdmin = $this->isSuperAdmin();
        
        // İşletme ID'sini query parametresinden al (super admin için)
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $businessId = $queryParams['business_id'] ?? null;
        
        if ($isSuperAdmin && $businessId) {
            // Tenant context'i işletme ID'sine göre set et
            try {
                $customerService = \App\Core\DependencyFactory::getCustomerService();
                $customer = $customerService->getById($businessId);
                if ($customer) {
                    \App\Core\TenantContext::set($customer);
                }
            } catch (\Exception $e) {
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::error('Failed to set tenant context from business_id', [
                        'business_id' => $businessId,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }
        
        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                  strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest' ||
                  !empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false;
        
        $requestData = \App\Core\RequestParser::getRequestData();
        
        // Fetch users from service (service handles caching with short TTL)
        $users = $this->userService->getAll();
        
        // Get all roles for label mapping
        $allRolesForLabels = $this->roleService->getActiveRoles();
        $roleLabelMap = [];
        foreach ($allRolesForLabels as $role) {
            $roleCode = strtoupper(trim($role['role_code'] ?? ''));
            // Remove ROLE_ prefix if exists
            if (strpos($roleCode, 'ROLE_') === 0) {
                $roleCode = substr($roleCode, 5);
            }
            $roleLabelMap[$roleCode] = $role['role_name'] ?? $roleCode;
            // Also add with ROLE_ prefix
            $roleLabelMap['ROLE_' . $roleCode] = $role['role_name'] ?? $roleCode;
        }
        
        // Get current language for role labels
        $currentLang = getCurrentLanguage();
        require_once __DIR__ . '/../../helpers/role_helpers.php';
        
        // Get preparation screens for name lookup
        $preparationScreenService = \App\Core\DependencyFactory::getPreparationScreenService();
        $preparationScreensForLookup = $preparationScreenService->getActiveScreens();
        $preparationScreenMap = [];
        foreach ($preparationScreensForLookup as $screen) {
            $screenId = $screen['screen_id'] ?? '';
            if (!empty($screenId)) {
                $preparationScreenMap[$screenId] = $screen['name'] ?? $screenId;
            }
        }
        
        $sanitizedUsers = array_map(function($user) use ($roleLabelMap, $currentLang, $preparationScreenMap) {
            if (!is_array($user)) {
                return null;
            }
            $roleCode = strtoupper(trim($user['role'] ?? 'WAITER'));
            // Remove ROLE_ prefix if exists for lookup
            $roleCodeForLookup = $roleCode;
            if (strpos($roleCodeForLookup, 'ROLE_') === 0) {
                $roleCodeForLookup = substr($roleCodeForLookup, 5);
            }
            
            // Get role label from map or use getRoleLabel helper
            $roleLabel = $roleLabelMap[$roleCode] ?? $roleLabelMap[$roleCodeForLookup] ?? null;
            if (!$roleLabel) {
                $roleLabel = getRoleLabel($roleCodeForLookup, $currentLang);
            }
            
            // CRITICAL: If user has preparation_screen_id, use preparation screen name as role label
            $preparationScreenId = $user['preparation_screen_id'] ?? null;
            if (!empty($preparationScreenId) && isset($preparationScreenMap[$preparationScreenId])) {
                $roleLabel = $preparationScreenMap[$preparationScreenId];
            }
            
            return [
                'user_id' => $user['user_id'] ?? '',
                'name' => $user['name'] ?? '',
                'role' => $user['role'] ?? 'WAITER',
                'role_label' => $roleLabel ?: ($user['role'] ?? 'WAITER'),
                'preparation_screen_id' => $preparationScreenId,
                'preparation_screen_name' => !empty($preparationScreenId) && isset($preparationScreenMap[$preparationScreenId]) ? $preparationScreenMap[$preparationScreenId] : null,
                // Don't send PIN in user list - use getStaffPin endpoint to retrieve it securely
                'pin' => null,
                'has_pin' => !empty($user['pin'])
            ];
        }, $users);
        
        $sanitizedUsers = array_filter($sanitizedUsers, function($user) {
            return $user !== null && !empty($user['user_id']);
        });
        
        // İşletme panelinde sadece "oturum açmış kullanıcıyı" ve gerçek işletme
        // sahibini listeden gizle. Önceki sürüm rol kodu olarak
        // `BUSINESS_MANAGER` kullanan HERKESİ gizliyordu; bulk-update bug'ı
        // tüm personelin rolünü BUSINESS_MANAGER'a çevirdiği zaman personel
        // listesi tamamen boş görünüyordu. Artık rol değil, `user_id`
        // bazında filtreliyoruz — sahip BusinessOwnerResolver ile tespit
        // edilir, bulunamazsa sadece oturumdaki kullanıcı gizlenir.
        if (!$isSuperAdmin) {
            $currentUserId = $_SESSION['user_id'] ?? null;
            $ownerUserId = null;
            try {
                $tenantId = \App\Core\TenantContext::getId();
                if ($tenantId) {
                    require_once __DIR__ . '/../../services/BusinessOwnerResolver.php';
                    $resolver = new \App\Services\BusinessOwnerResolver(
                        \App\Core\DependencyFactory::getDatabase()
                    );
                    $ownerUserId = $resolver->resolve((string)$tenantId);
                }
            } catch (\Throwable $e) {
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::warning('users(): owner resolve failed', [
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $hideIds = array_values(array_filter([$currentUserId, $ownerUserId]));
            if (!empty($hideIds)) {
                $sanitizedUsers = array_filter($sanitizedUsers, function ($user) use ($hideIds) {
                    return !in_array($user['user_id'] ?? '', $hideIds, true);
                });
            }
        }
        
        if ($isAjax) {
            $this->toastNotificationService->sendApiResponse('success', '', [], 200, [
                'users' => array_values($sanitizedUsers)
            ]);
            return;
        }
        
        $allRoles = $this->roleService->getActiveRoles();
        $rolesWithPermissions = [];
        $roleCodeToRoleId = [];
        
        // Batch load permissions for all roles (optimize N+1 query problem)
        $roleIds = array_filter(array_column($allRoles, 'role_id'));
        $permissionsByRole = !empty($roleIds) ? $this->roleService->getRolePermissionKeysBatch($roleIds) : [];
        
        foreach ($allRoles as $role) {
            $roleId = $role['role_id'] ?? '';
            $roleCode = $role['role_code'] ?? '';
            $rolePerms = $permissionsByRole[$roleId] ?? [];
            
            $rolesWithPermissions[$roleId] = $rolePerms;
            if (!empty($roleCode)) {
                $rolesWithPermissions[$roleCode] = $rolePerms;
                $rolesWithPermissions['ROLE_' . $roleCode] = $rolePerms;
                $roleCodeToRoleId[$roleCode] = $roleId;
                $roleCodeToRoleId['ROLE_' . $roleCode] = $roleId;
            }
        }
        
        $allPermissions = $this->permissionModel->getAll();
        
        // Get active preparation screens for the current tenant
        $preparationScreenService = \App\Core\DependencyFactory::getPreparationScreenService();
        $preparationScreens = $preparationScreenService->getActiveScreens();
        
        $data = [
            'users' => array_values($sanitizedUsers),
            'roles_with_permissions' => $rolesWithPermissions,
            'role_code_to_role_id' => $roleCodeToRoleId,
            'all_permissions' => $allPermissions,
            'all_roles_db' => $allRoles,
            'preparation_screens' => $preparationScreens,
            'is_super_admin' => $isSuperAdmin
        ];
        
        $this->view('admin/users', $data);
    }
    
    /**
     * Get decrypted PIN for a user (API endpoint)
     */
    public function getDecryptedPin($userId = null) {
        try {
            $this->requirePermission('staff.view');
            
            // Get user ID from route parameter or query string
            $queryParams = \App\Core\RequestParser::getQueryParams();
            $userId = $userId ?? $queryParams['user_id'] ?? $queryParams['id'] ?? '';
            
            if (empty($userId)) {
                $this->toastNotificationService->sendApiResponse('error', 'User ID required', [], 400);
                return;
            }
            
            $decryptedPin = $this->userService->getDecryptedPin($userId);
            
            if ($decryptedPin === null) {
                $this->toastNotificationService->sendApiResponse('error', 'PIN not found or could not be decrypted', [], 404);
                return;
            }
            
            $this->apiResponse([
                'success' => true,
                'pin' => $decryptedPin
            ]);
        } catch (\Exception $e) {
            \App\Core\Logger::error('getDecryptedPin error: ' . $e->getMessage());
            $this->toastNotificationService->sendApiResponse('error', 'Failed to retrieve PIN', [], 500);
        }
    }
    
    public function addStaff() {
        try {
            $this->requirePermission('staff.create');
        } catch (\Exception $e) {
            \App\Core\Logger::error("addStaff: Permission check failed - " . $e->getMessage());
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 403);
            return;
        }
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_request', [], 405);
            return;
        }
        
        $requestData = null;
        $userData = null;
        
        try {
            // Parse JSON input
            try {
                $input = json_decode(file_get_contents('php://input'), true);
                if ($input) {
                    $_POST = array_merge($_POST, $input);
                }
            } catch (\Exception $e) {
                \App\Core\Logger::error("addStaff: Error parsing JSON input - " . $e->getMessage());
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
                return;
            }
            
            // Parse request data
            try {
                $requestData = \App\Core\RequestParser::getRequestData();
            } catch (\Exception $e) {
                \App\Core\Logger::error("addStaff: Error parsing request data - " . $e->getMessage());
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
                return;
            }
            
            // Validate required fields
            if (empty($requestData['name']) || empty($requestData['pin']) || empty($requestData['role'])) {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.warning.missing_fields', [], 400);
                return;
            }
            
            // Validate PIN format
            $pin = $requestData['pin'] ?? '';
            if (strlen($pin) < 4 || strlen($pin) > 10 || !preg_match('/^\d+$/', $pin)) {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.warning.pin_invalid', [], 400);
                return;
            }
            
            $name = trim($requestData['name'] ?? '');
            
            // Check for duplicate name
            try {
                $existingUserByName = $this->userService->findByCredentials(['name' => $name]);
                if ($existingUserByName) {
                    $this->toastNotificationService->sendApiResponse('error', 'Bu isimde bir personel zaten mevcut. Lütfen farklı bir isim kullanın.', [], 400);
                    return;
                }
            } catch (\Exception $e) {
                \App\Core\Logger::error("addStaff: Error checking duplicate name - " . $e->getMessage() . " | Name: " . substr($name, 0, 50));
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.check_failed', [], 500);
                return;
            }
            
            // Check for duplicate PIN
            try {
                $allUsers = $this->userService->getAll();
                foreach ($allUsers as $user) {
                    $userPin = $user['pin'] ?? '';
                    if ($userPin === $pin || password_verify($pin, $userPin)) {
                        $this->toastNotificationService->sendApiResponse('error', 'Bu PIN zaten kullanılıyor. Lütfen farklı bir PIN seçin.', [], 400);
                        return;
                    }
                }
            } catch (\Exception $e) {
                \App\Core\Logger::error("addStaff: Error checking duplicate PIN - " . $e->getMessage());
                // Continue execution - PIN check failure shouldn't block user creation
            }
            
            // Get role_id from role code
            $roleCode = $requestData['role'] ?? 'WAITER';
            $roleCode = strtoupper(trim($roleCode));
            if (strpos($roleCode, 'ROLE_') === 0) {
                $roleCode = substr($roleCode, 5);
            }
            $roleCode = substr($roleCode, 0, 20);
            
            // Handle preparation screen role (PREP_SCREEN_{screen_id} format)
            // CRITICAL: Get preparation_screen_id from request first
            $preparationScreenId = $requestData['preparation_screen_id'] ?? null;
            $isPreparationScreenRole = false;
            
            // Check if role is in PREP_SCREEN_{screen_id} format
            if ($roleCode && strtoupper($roleCode) !== 'PREP_SCREEN_' && strpos(strtoupper($roleCode), 'PREP_SCREEN_') === 0) {
                // Role is in PREP_SCREEN_{screen_id} format
                $isPreparationScreenRole = true;
                // Extract screen ID from role format
                $extractedScreenId = str_replace('PREP_SCREEN_', '', strtoupper($roleCode));
                // Use extracted screen ID if preparation_screen_id is not already provided
                if (empty($preparationScreenId)) {
                    $preparationScreenId = $extractedScreenId;
                }
                // CRITICAL: Also update requestData so it's available later
                $requestData['preparation_screen_id'] = $preparationScreenId;
                // Set role to KITCHEN for database storage (preparation screens use KITCHEN role)
                $roleCode = 'KITCHEN';
                
                // Log for debugging
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::info('UsersController::addStaff - Extracted preparation_screen_id from role format', [
                        'role_code' => $requestData['role'] ?? 'unknown',
                        'extracted_screen_id' => $extractedScreenId,
                        'final_preparation_screen_id' => $preparationScreenId
                    ]);
                }
            }
            
            // Validate role for business managers (non-superadmin)
            // Business managers can only add staff with these roles: WAITER, KITCHEN, CASHIER
            // CRITICAL: If preparation_screen_id is provided, allow KITCHEN role even if it's for preparation screen
            if (!$this->isSuperAdmin()) {
                $allowedBusinessRoles = ['WAITER', 'KITCHEN', 'CASHIER'];
                if (!in_array($roleCode, $allowedBusinessRoles)) {
                    $this->toastNotificationService->sendApiResponse('error', 'Bu rol ile personel ekleyemezsiniz. Sadece Garson, Mutfak ve Kasa rolleri ile personel ekleyebilirsiniz.', [], 403);
                    return;
                }
            }
            
            $roleId = null;
            
            try {
                // First try: Get role_id from RoleService (database lookup)
                $roleData = $this->roleService->getByRoleCode($roleCode);
                if ($roleData && isset($roleData['role_id'])) {
                    $roleId = $roleData['role_id'];
                } else {
                    // Fallback: Try RoleMapper to get role_id from role_code
                    try {
                        require_once __DIR__ . '/../../services/RoleMapper.php';
                        $roleMapper = \App\Services\RoleMapper::getInstance();
                        $mappedRoleId = $roleMapper->getRoleId($roleCode);
                        if ($mappedRoleId) {
                            $roleId = $mappedRoleId;
                        }
                    } catch (\Exception $mapperError) {
                        \App\Core\Logger::warning("addStaff: RoleMapper failed - " . $mapperError->getMessage());
                    }
                    
                    // If still no role_id, try to get from all roles
                    if (empty($roleId)) {
                        try {
                            $allRoles = $this->roleService->getActiveRoles();
                            foreach ($allRoles as $role) {
                                $dbRoleCode = strtoupper(trim($role['role_code'] ?? ''));
                                if ($dbRoleCode === strtoupper($roleCode)) {
                                    $roleId = $role['role_id'] ?? null;
                                    if ($roleId) {
                                        break;
                                    }
                                }
                            }
                        } catch (\Exception $allRolesError) {
                            \App\Core\Logger::warning("addStaff: Error getting all roles - " . $allRolesError->getMessage());
                        }
                    }
                }
            } catch (\Exception $e) {
                \App\Core\Logger::error("addStaff: Error getting role_id for role {$roleCode} - " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
            }
            
            // Final fallback: If still no role_id, try to get WAITER role as default
            if (empty($roleId)) {
                try {
                    $waiterRole = $this->roleService->getByRoleCode('WAITER');
                    if ($waiterRole && isset($waiterRole['role_id'])) {
                        $roleId = $waiterRole['role_id'];
                        \App\Core\Logger::warning("addStaff: Using WAITER as fallback role_id for roleCode: {$roleCode}");
                    }
                } catch (\Exception $fallbackError) {
                    \App\Core\Logger::error("addStaff: Final fallback failed - " . $fallbackError->getMessage());
                }
            }
            
            if (empty($roleId)) {
                \App\Core\Logger::error("addStaff: roleId is empty after all attempts - roleCode: {$roleCode}");
                $this->toastNotificationService->sendApiResponse('error', 'Geçersiz rol seçildi. Lütfen geçerli bir rol seçin.', [], 400);
                return;
            }
            
            // Get business_id from tenant context or session
            $businessId = null;
            try {
                // First try: Get from TenantContext (for subdomain access)
                $businessId = \App\Core\TenantContext::getId();
                
                // Second try: Get from query params (for super admin with business_id param)
                if (!$businessId) {
                    $queryParams = \App\Core\RequestParser::getQueryParams();
                    $businessId = $queryParams['business_id'] ?? null;
                }
                
                // Third try: Get from auth service
                if (!$businessId && $this->auth) {
                    $businessId = $this->auth->getCurrentCustomerId();
                }
                
                // Fourth try: Get from session directly
                if (!$businessId) {
                    $businessId = \App\Core\TenantResolver::resolve();
                }
            } catch (\Exception $e) {
                \App\Core\Logger::warning("addStaff: Error getting business_id - " . $e->getMessage());
            }
            
            // If still no business_id, log error but continue (for super admin creating users without business)
            if (empty($businessId) && !$this->isSuperAdmin()) {
                \App\Core\Logger::error("addStaff: business_id is empty for non-superadmin user");
                $this->toastNotificationService->sendApiResponse('error', 'İşletme bilgisi bulunamadı. Lütfen tekrar deneyin.', [], 400);
                return;
            }
            
            // CRITICAL: preparation_screen_id is already extracted above from role format or request
            // No need to get it again - it's already in $preparationScreenId variable
            // Just log for debugging
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::info('UsersController::addStaff - Preparation screen ID check', [
                    'preparation_screen_id' => $preparationScreenId,
                    'is_preparation_screen_role' => $isPreparationScreenRole,
                    'request_data_keys' => array_keys($requestData),
                    'has_preparation_screen_id_in_request' => isset($requestData['preparation_screen_id']),
                    'role' => $roleCode,
                    'original_role_from_request' => $requestData['role'] ?? 'not_set'
                ]);
            }
            
            if (!empty($preparationScreenId)) {
                // CRITICAL: Verify preparation screen belongs to current tenant
                $preparationScreenService = \App\Core\DependencyFactory::getPreparationScreenService();
                $screen = $preparationScreenService->getScreenById($preparationScreenId);
                
                if (!$screen) {
                    $this->toastNotificationService->sendApiResponse('error', 'Seçilen hazırlık ekranı bulunamadı.', [], 404);
                    return;
                }
                
                // Tenant isolation check (unless super admin)
                // Note: getScreenById() already applies tenant filter via repository
                // If screen is found, it belongs to current tenant (tenant isolation is handled at repository level)
                // However, we still verify business_id/tenant_id for additional security
                if (!$this->isSuperAdmin()) {
                    $tenantId = \App\Core\TenantContext::getId();
                    if (!$tenantId) {
                        \App\Core\Logger::warning('UsersController::addStaff - Tenant ID not set', [
                            'preparation_screen_id' => $preparationScreenId
                        ]);
                        $this->toastNotificationService->sendApiResponse('error', 'Bu hazırlık ekranına erişim yetkiniz yok.', [], 403);
                        return;
                    }
                    
                    // Check both business_id and tenant_id columns
                    // If both are null, repository filter already ensured tenant isolation, so allow access
                    $screenBusinessId = $screen['tenant_id'] ?? null;
                    $screenTenantId = $screen['tenant_id'] ?? null;
                    
                    // If both are null, repository filter already applied, screen belongs to current tenant
                    if ($screenBusinessId === null && $screenTenantId === null) {
                        // Repository filter already applied, screen belongs to current tenant
                        \App\Core\Logger::debug('UsersController::addStaff - Screen found via tenant filter (both IDs null)', [
                            'preparation_screen_id' => $preparationScreenId,
                            'tenant_id' => $tenantId
                        ]);
                    } else {
                        // At least one ID is set, verify it matches tenant
                        $screenCombinedId = $screenBusinessId ?? $screenTenantId;
                        if ($screenCombinedId !== $tenantId) {
                            \App\Core\Logger::warning('UsersController::addStaff - Preparation screen tenant isolation violation', [
                                'preparation_screen_id' => $preparationScreenId,
                                'screen_business_id' => $screenBusinessId,
                                'screen_tenant_id' => $screenTenantId,
                                'screen_combined_id' => $screenCombinedId,
                                'tenant_id' => $tenantId
                            ]);
                            $this->toastNotificationService->sendApiResponse('error', 'Bu hazırlık ekranına erişim yetkiniz yok.', [], 403);
                            return;
                        }
                    }
                }
            }
            
            // Prepare user data
            $userData = [
                'user_id' => generateId('u'),
                'name' => trim($requestData['name'] ?? ''),
                'pin' => $pin,
                'role' => $roleCode,
                'role_id' => $roleId
            ];
            
            // Add business_id if available
            if ($businessId) {
                $userData['tenant_id'] = $businessId;
            }
            
            // Add preparation_screen_id if provided
            if (!empty($preparationScreenId)) {
                $userData['preparation_screen_id'] = $preparationScreenId;
                
                // Log for debugging
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::info('UsersController::addStaff - Adding preparation_screen_id to user data', [
                        'user_id' => $userData['user_id'],
                        'preparation_screen_id' => $preparationScreenId,
                        'user_data_keys' => array_keys($userData)
                    ]);
                }
            } else {
                // Log if preparation_screen_id is not being added
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::warning('UsersController::addStaff - preparation_screen_id is empty, not adding to user data', [
                        'preparation_screen_id' => $preparationScreenId,
                        'request_data' => array_keys($requestData)
                    ]);
                }
            }
            
            // Attempt to create user
            $result = $this->userService->create($userData);
            $isSuccess = ($result !== false && $result !== null);
            
            // Log user creation result
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::info('UsersController::addStaff - User creation result', [
                    'user_id' => $userData['user_id'],
                    'success' => $isSuccess,
                    'preparation_screen_id_in_data' => $userData['preparation_screen_id'] ?? 'not_set'
                ]);
            }
            
            if ($isSuccess) {
                // Assign preparation screen permissions if preparation_screen_id is set
                if (!empty($preparationScreenId)) {
                    try {
                        $this->assignPreparationScreenPermissions($userData['user_id'], $roleId, $preparationScreenId);
                    } catch (\Exception $e) {
                        // Log but don't fail staff creation
                        if (class_exists('\App\Core\Logger')) {
                            \App\Core\Logger::warning('Error assigning preparation screen permissions to staff', [
                                'error' => $e->getMessage(),
                                'user_id' => $userData['user_id'] ?? 'unknown',
                                'preparation_screen_id' => $preparationScreenId,
                                'role_id' => $roleId
                            ]);
                        }
                    }
                }
                
                // Assign package permissions to staff member
                try {
                    $customerId = $this->auth->getCurrentCustomerId();
                    if ($customerId) {
                        $subscriptionService = \App\Core\DependencyFactory::getSubscriptionService();
                        $subscription = $subscriptionService->getCustomerSubscription($customerId);
                        
                        // If business has active subscription, assign package permissions
                        if ($subscription && $subscription['status'] === 'active' && !empty($subscription['package_id'])) {
                            $packagePermissions = $subscriptionService->getSubscriptionPermissions($subscription['subscription_id']);
                            
                            if (!empty($packagePermissions)) {
                                // Filter permissions based on staff role
                                // Staff members get role-specific permissions from package
                                // Use all package permissions for now
                                $roleSpecificPermissions = $packagePermissions;
                                
                                if (!empty($roleSpecificPermissions)) {
                                    // Assign permissions to staff role
                                    $permissionModel = new \App\Models\SystemPermission(\App\Core\DependencyFactory::getDatabase());
                                    $assignedCount = 0;
                                    
                                    foreach ($roleSpecificPermissions as $permissionKey) {
                                        $permission = $permissionModel->getByKey($permissionKey);
                                        if ($permission && isset($permission['permission_id'])) {
                                            $assigned = $permissionModel->assignToRole($roleId, $permission['permission_id']);
                                            if ($assigned) {
                                                $assignedCount++;
                                            }
                                        }
                                    }
                                    
                                    if (class_exists('\App\Core\Logger')) {
                                        \App\Core\Logger::info('Package permissions assigned to staff member', [
                                            'user_id' => $userData['user_id'],
                                            'role' => $roleCode,
                                            'role_id' => $roleId,
                                            'package_id' => $subscription['package_id'],
                                            'assigned_count' => $assignedCount,
                                            'total_permissions' => count($roleSpecificPermissions)
                                        ]);
                                    }
                                }
                            }
                        }
                    }
                } catch (\Exception $e) {
                    // Log but don't fail staff creation
                    if (class_exists('\App\Core\Logger')) {
                        \App\Core\Logger::warning('Error assigning package permissions to staff', [
                            'error' => $e->getMessage(),
                            'user_id' => $userData['user_id'] ?? 'unknown',
                            'role' => $roleCode
                        ]);
                    }
                }
                
                // Clear cache
                try {
                    $cacheService = \App\Core\DependencyFactory::getCacheService();
                    $cacheService->delete('users:all');
                } catch (\Exception $e) {
                    \App\Core\Logger::warning("addStaff: Error clearing cache - " . $e->getMessage());
                    // Continue - cache clear failure shouldn't block success response
                }
                
                // Fetch created user
                $createdUser = null;
                try {
                    $createdUser = $this->userService->findByUserId($userData['user_id']);
                    if (!$createdUser) {
                        $createdUser = [
                            'user_id' => $userData['user_id'],
                            'name' => $userData['name'],
                            'role' => $userData['role'],
                            'pin' => $userData['pin']
                        ];
                    }
                } catch (\Exception $e) {
                    \App\Core\Logger::warning("addStaff: Error fetching created user - " . $e->getMessage());
                    $createdUser = [
                        'user_id' => $userData['user_id'],
                        'name' => $userData['name'],
                        'role' => $userData['role'],
                        'pin' => $userData['pin']
                    ];
                }
                
                $this->toastNotificationService->sendApiResponse('success', 'notifications.success.staff_added', [], 200, [
                    'user' => $createdUser
                ]);
            } else {
                \App\Core\Logger::error("addStaff: UserService->create returned false/null", [
                    'user_id' => $userData['user_id'] ?? 'unknown',
                    'name' => $userData['name'] ?? 'unknown',
                    'role' => $userData['role'] ?? 'unknown',
                    'role_id' => $userData['role_id'] ?? 'unknown',
                    'result' => var_export($result, true)
                ]);
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.staff_add_failed', [], 500);
            }
        } catch (\PDOException $e) {
            // Handle database-specific errors
            $errorCode = $e->errorInfo[1] ?? null;
            $errorMessage = $e->errorInfo[2] ?? $e->getMessage();
            
            \App\Core\Logger::error("addStaff: PDOException during user creation", [
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'sql_state' => $e->errorInfo[0] ?? 'unknown',
                'error_code' => $errorCode,
                'error_message' => $errorMessage,
                'user_data' => $userData ? array_merge($userData, ['pin' => '***REDACTED***']) : null,
                'request_data' => $requestData ? array_merge($requestData, ['pin' => '***REDACTED***']) : null,
                'trace' => $e->getTraceAsString()
            ]);
            
            // Provide specific error messages based on error code
            if ($errorCode == 1062) { // Duplicate entry
                $this->toastNotificationService->sendApiResponse('error', 'Bu kullanıcı zaten mevcut. Lütfen farklı bilgiler kullanın.', [], 409);
            } elseif ($errorCode == 1452) { // Foreign key constraint
                $this->toastNotificationService->sendApiResponse('error', 'Geçersiz rol seçildi. Lütfen geçerli bir rol seçin.', [], 400);
            } elseif ($errorCode == 1364) { // Field doesn't have a default value
                $this->toastNotificationService->sendApiResponse('error', 'Eksik zorunlu alanlar var. Lütfen tüm bilgileri doldurun.', [], 400);
            } else {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.database_error', [], 500);
            }
        } catch (\Exception $e) {
            \App\Core\Logger::error("addStaff: Unexpected error during user creation", [
                'message' => $e->getMessage(),
                'type' => get_class($e),
                'user_data' => $userData ? array_merge($userData, ['pin' => '***REDACTED***']) : null,
                'request_data' => $requestData ? array_merge($requestData, ['pin' => '***REDACTED***']) : null,
                'trace' => $e->getTraceAsString()
            ]);
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.staff_add_failed', [], 500);
        }
    }
    
    public function editStaff() {
        // CRITICAL: Ensure tenant context is set before editing staff
        $this->ensureTenantContext();
        
        $this->requirePermission('staff.edit');
        
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $userId = $queryParams['id'] ?? '';
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // CRITICAL: Verify tenant isolation before update
            $existingUser = $this->userService->findByUserId($userId);
            if (!$existingUser) {
                $this->toastNotificationService->setFlash('error', 'notifications.error.not_found');
                \App\Core\HelperLoader::ensureLoaded();
                header('Location: ' . getAdminUrl('users'));
                exit;
            }
            
            // Check tenant isolation (unless super admin)
            if (!$this->isSuperAdmin()) {
                $tenantId = \App\Core\TenantContext::getId();
                $userBusinessId = $existingUser['tenant_id'] ?? null;
                
                if (!$tenantId || $userBusinessId !== $tenantId) {
                    \App\Core\Logger::warning('UsersController::editStaff - Tenant isolation violation', [
                        'user_id' => $userId,
                        'user_business_id' => $userBusinessId,
                        'tenant_id' => $tenantId
                    ]);
                    $this->toastNotificationService->setFlash('error', 'notifications.error.unauthorized');
                    \App\Core\HelperLoader::ensureLoaded();
                    header('Location: ' . getAdminUrl('users'));
                    exit;
                }
            }
            
            $requestData = \App\Core\RequestParser::getRequestData();
            $name = sanitizeInput($requestData['name'] ?? '');
            $pin = $requestData['pin'] ?? '';
            $defaultRole = ConstantsHelper::getRole('WAITER');
            $role = sanitizeInput($requestData['role'] ?? $defaultRole);
            
            $userData = [
                'name' => $name,
                'role' => $role
            ];
            
            if (!empty($pin)) {
                if (strlen($pin) <= 10 && ctype_digit($pin)) {
                    $userData['pin'] = $pin;
                } else {
                    $this->toastNotificationService->setFlash('error', 'notifications.warning.invalid_pin_format');
                    \App\Core\HelperLoader::ensureLoaded();
                    header('Location: ' . getAdminUrl('users'));
                    exit;
                }
            }
            
            $result = $this->userService->update($userId, $userData);
            
            if ($result) {
                $this->toastNotificationService->setFlash('success', 'notifications.success.staff_updated');
            } else {
                $this->toastNotificationService->setFlash('error', 'notifications.error.staff_update_failed');
            }
            
            \App\Core\HelperLoader::ensureLoaded();
            header('Location: ' . getAdminUrl('settings') . '#staff');
            exit;
        }
        
        // CRITICAL: Verify tenant isolation before showing edit form
        $user = $this->userService->findByUserId($userId);
        if (!$user) {
            $this->toastNotificationService->setFlash('error', 'notifications.error.not_found');
            \App\Core\HelperLoader::ensureLoaded();
            header('Location: ' . getAdminUrl('users'));
            exit;
        }
        
        // Check tenant isolation (unless super admin)
        if (!$this->isSuperAdmin()) {
            $tenantId = \App\Core\TenantContext::getId();
            $userBusinessId = $user['tenant_id'] ?? null;
            
            if (!$tenantId || $userBusinessId !== $tenantId) {
                \App\Core\Logger::warning('UsersController::editStaff - Tenant isolation violation', [
                    'user_id' => $userId,
                    'user_business_id' => $userBusinessId,
                    'tenant_id' => $tenantId
                ]);
                $this->toastNotificationService->setFlash('error', 'notifications.error.unauthorized');
                \App\Core\HelperLoader::ensureLoaded();
                header('Location: ' . getAdminUrl('users'));
                exit;
            }
        }
        
        $data = [
            'user' => $user
        ];
        
        $this->view('admin/edit_user', $data);
    }
    
    public function deleteStaff($userId = null) {
        // CRITICAL: Ensure tenant context is set before deleting staff
        $this->ensureTenantContext();
        
        // BUSINESS_MANAGER bypass: Business owners can always delete their own staff
        // Super Admin bypass: Super admins can delete any user
        if (!$this->isSuperAdmin()) {
            $currentRole = $this->auth->getCurrentRole();
            $normalizedRole = strtoupper(trim($currentRole));
            $isBusinessManager = ($normalizedRole === 'BUSINESS_MANAGER' || $normalizedRole === 'ROLE_BUSINESS_MANAGER');
            
            if (!$isBusinessManager) {
                // Non-business-manager users need the permission
                $this->requirePermission('staff.delete');
            }
            // Business managers don't need permission check - they own the business
        }
        
        if (empty($userId)) {
            $queryParams = \App\Core\RequestParser::getQueryParams();
            $requestData = \App\Core\RequestParser::getRequestData();
            $userId = $queryParams['id'] ?? $requestData['id'] ?? '';
        }
        
        $userId = trim($userId);
        
        if (empty($userId)) {
            $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                      strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest' ||
                      !empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false;
            
            if ($isAjax) {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
            } else {
                $this->toastNotificationService->setFlash('error', 'notifications.error.invalid_data');
                header('Location: ' . BASE_URL . '/admin/users');
            }
            return;
        }
        
        // CRITICAL: Verify tenant isolation before deletion
        $existingUser = $this->userService->findByUserId($userId);
        
        if (!$existingUser) {
            $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                      strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest' ||
                      !empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false;
            
            if ($isAjax) {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.not_found', [], 404);
            } else {
                $this->toastNotificationService->setFlash('error', 'notifications.error.not_found');
                header('Location: ' . BASE_URL . '/admin/users');
            }
            return;
        }
        
        // Check tenant isolation (unless super admin)
        if (!$this->isSuperAdmin()) {
            $tenantId = \App\Core\TenantContext::getId();
            $userBusinessId = $existingUser['tenant_id'] ?? null;
            
            if (!$tenantId || $userBusinessId !== $tenantId) {
                \App\Core\Logger::warning('UsersController::deleteStaff - Tenant isolation violation', [
                    'user_id' => $userId,
                    'user_business_id' => $userBusinessId,
                    'tenant_id' => $tenantId
                ]);
                
                $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest' ||
                          !empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false;
                
                if ($isAjax) {
                    $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 403);
                } else {
                    $this->toastNotificationService->setFlash('error', 'notifications.error.unauthorized');
                    header('Location: ' . BASE_URL . '/admin/users');
                }
                return;
            }
        }
        
        $result = $this->userService->delete($userId);
        
        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                  strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest' ||
                  !empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false;
        
        if ($isAjax) {
            if ($result) {
                // Clear user cache after successful delete
                try {
                    $cacheService = \App\Core\DependencyFactory::getCacheService();
                    $cacheService->delete('users:all');
                } catch (\Exception $e) {
                    // Silent fail - cache will expire naturally
                }
                
                $this->toastNotificationService->sendApiResponse('success', 'notifications.success.staff_deleted', [], 200, [
                    'deleted_user_id' => $userId
                ]);
            } else {
                try {
                    $userExists = $this->userService->findByUserId($userId);
                    if ($userExists === null || empty($userExists)) {
                        $this->toastNotificationService->sendApiResponse('error', 'Kullanıcı bulunamadı. Zaten silinmiş olabilir.', [], 404);
                    } else {
                        $this->toastNotificationService->sendApiResponse('error', 'notifications.error.staff_delete_failed', [], 500);
                    }
                } catch (\Exception $e) {
                    $this->toastNotificationService->sendApiResponse('error', 'notifications.error.staff_delete_failed', [], 500);
                }
            }
            return;
        }
        
        if ($result) {
            $this->toastNotificationService->setFlash('success', 'notifications.success.staff_deleted');
        } else {
            $this->toastNotificationService->setFlash('error', 'notifications.error.staff_delete_failed');
        }
        
        header('Location: ' . BASE_URL . '/admin/users');
        exit;
    }
    
    public function updateStaff($userId = null) {
        $this->requirePermission('staff.edit');
        
        if (empty($userId)) {
            $queryParams = \App\Core\RequestParser::getQueryParams();
            $requestData = \App\Core\RequestParser::getRequestData();
            $userId = $queryParams['id'] ?? $requestData['id'] ?? '';
        }
        
        $userId = trim($userId);
        
        if (empty($userId)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
            return;
        }
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_request', [], 400);
            return;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) {
            $data = $_POST;
        }
        
        $roleList = 'MANAGER,WAITER,KITCHEN,CASHIER';
        try {
            $constantsService = \App\Core\DependencyFactory::getConstantsService();
            $roleCodes = $constantsService->getRoleCodes();
            if (!empty($roleCodes)) {
                $roleList = implode(',', $roleCodes);
            }
        } catch (\Exception $e) {
            \App\Core\Logger::error("Error loading role codes: " . $e->getMessage());
        }
        
        // Skip role validation if it's a PREP_SCREEN_* role (will be handled later)
        $skipRoleValidation = false;
        if (isset($data['role'])) {
            $roleToCheck = strtoupper(trim($data['role']));
            if (strpos($roleToCheck, 'ROLE_') === 0) {
                $roleToCheck = substr($roleToCheck, 5);
            }
            if (strpos($roleToCheck, 'PREP_SCREEN_') === 0) {
                $skipRoleValidation = true;
            }
        }
        
        $validationRules = [
            'name' => 'string|max:255'
        ];
        
        // Add role validation only if not a preparation screen role
        if (!$skipRoleValidation) {
            $validationRules['role'] = 'string|in:' . $roleList;
        }
        
        $validationService = \App\Core\DependencyFactory::getValidationService();
        $validationErrors = $validationService->validate($data, $validationRules);
        
        if (!empty($validationErrors)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
            return;
        }
        
        $updateData = [];
        
        if (isset($data['name'])) {
            $updateData['name'] = $data['name'];
        }
        
        if (isset($data['role'])) {
            $roleCode = strtoupper(trim($data['role']));
            if (strpos($roleCode, 'ROLE_') === 0) {
                $roleCode = substr($roleCode, 5);
            }
            
            // Handle preparation screen role (PREP_SCREEN_{screen_id} format)
            $newPreparationScreenId = $data['preparation_screen_id'] ?? null;
            $isPreparationScreenRole = false;
            
            if ($roleCode && strpos($roleCode, 'PREP_SCREEN_') === 0) {
                // Role is in PREP_SCREEN_{screen_id} format
                $isPreparationScreenRole = true;
                // Extract screen ID if not already provided
                if (empty($newPreparationScreenId)) {
                    $newPreparationScreenId = str_replace('PREP_SCREEN_', '', $roleCode);
                }
                // Set role to KITCHEN for database storage (preparation screens use KITCHEN role)
                $roleCode = 'KITCHEN';
            }
            
            // Validate role for business managers (non-superadmin)
            // Business managers can only update staff to these roles: WAITER, KITCHEN, CASHIER
            // CRITICAL: If preparation_screen_id is provided, allow KITCHEN role even if it's for preparation screen
            if (!$this->isSuperAdmin()) {
                $allowedBusinessRoles = ['WAITER', 'KITCHEN', 'CASHIER'];
                if (!in_array($roleCode, $allowedBusinessRoles)) {
                    $this->toastNotificationService->sendApiResponse('error', 'Bu rol ile personel güncelleyemezsiniz. Sadece Garson, Mutfak ve Kasa rolleri ile personel güncelleyebilirsiniz.', [], 403);
                    return;
                }
            }
            $roleCode = substr($roleCode, 0, 20);
            $updateData['role'] = $roleCode;
            
            $roleId = null;
            
            try {
                $roleData = $this->roleService->getByRoleCode($roleCode);
                if ($roleData && isset($roleData['role_id'])) {
                    $roleId = $roleData['role_id'];
                } else {
                    // Fallback: Try RoleMapper
                    try {
                        require_once __DIR__ . '/../../services/RoleMapper.php';
                        $roleMapper = \App\Services\RoleMapper::getInstance();
                        $mappedRoleId = $roleMapper->getRoleId($roleCode);
                        if ($mappedRoleId) {
                            $roleId = $mappedRoleId;
                        } else {
                            \App\Core\Logger::warning("updateStaff: Could not find role_id for role_code: {$roleCode}");
                        }
                    } catch (\Exception $mapperException) {
                        \App\Core\Logger::error("updateStaff: RoleMapper fallback failed: " . $mapperException->getMessage());
                    }
                }
            } catch (\Exception $e) {
                \App\Core\Logger::error("updateStaff: Error getting role_id for role {$roleCode}: " . $e->getMessage());
                // Fallback: Try RoleMapper as last resort
                try {
                    require_once __DIR__ . '/../../services/RoleMapper.php';
                    $roleMapper = \App\Services\RoleMapper::getInstance();
                    $mappedRoleId = $roleMapper->getRoleId($roleCode);
                    if ($mappedRoleId) {
                        $roleId = $mappedRoleId;
                    }
                } catch (\Exception $mapperException) {
                    \App\Core\Logger::error("updateStaff: RoleMapper fallback failed: " . $mapperException->getMessage());
                }
            }
            
            if ($roleId) {
                $updateData['role_id'] = $roleId;
            }
        }
        
        // Handle preparation_screen_id assignment
        $oldPreparationScreenId = null;
        $newPreparationScreenId = isset($data['preparation_screen_id']) ? ($data['preparation_screen_id'] ?: null) : null;
        
        $existingUser = $this->userService->findByUserId($userId);
        if (!$existingUser || empty($existingUser)) {
            $this->toastNotificationService->sendApiResponse('error', 'Kullanıcı bulunamadı. Zaten silinmiş olabilir.', [], 404);
            return;
        }
        
        $oldPreparationScreenId = $existingUser['preparation_screen_id'] ?? null;
        $currentRoleId = $existingUser['role_id'] ?? $updateData['role_id'] ?? null;
        
        if ($newPreparationScreenId !== null && $newPreparationScreenId !== $oldPreparationScreenId) {
            // Validate preparation screen belongs to tenant
            $preparationScreenService = \App\Core\DependencyFactory::getPreparationScreenService();
            $screen = $preparationScreenService->getScreenById($newPreparationScreenId);
            
            if (!$screen) {
                $this->toastNotificationService->sendApiResponse('error', 'Seçilen hazırlık ekranı bulunamadı.', [], 404);
                return;
            }
            
            // Tenant isolation check (unless super admin)
            // Note: getScreenById() already applies tenant filter via repository
            // If screen is found, it belongs to current tenant (tenant isolation is handled at repository level)
            // However, we still verify business_id/tenant_id for additional security
            if (!$this->isSuperAdmin()) {
                $tenantId = \App\Core\TenantContext::getId();
                if (!$tenantId) {
                    \App\Core\Logger::warning('UsersController::updateStaff - Tenant ID not set', [
                        'preparation_screen_id' => $newPreparationScreenId
                    ]);
                    $this->toastNotificationService->sendApiResponse('error', 'Bu hazırlık ekranına erişim yetkiniz yok.', [], 403);
                    return;
                }
                
                // Check both business_id and tenant_id columns
                // If both are null, repository filter already ensured tenant isolation, so allow access
                $screenBusinessId = $screen['tenant_id'] ?? null;
                $screenTenantId = $screen['tenant_id'] ?? null;
                
                // If both are null, repository filter already applied, screen belongs to current tenant
                if ($screenBusinessId === null && $screenTenantId === null) {
                    // Repository filter already applied, screen belongs to current tenant
                    \App\Core\Logger::debug('UsersController::updateStaff - Screen found via tenant filter (both IDs null)', [
                        'preparation_screen_id' => $newPreparationScreenId,
                        'tenant_id' => $tenantId
                    ]);
                } else {
                    // At least one ID is set, verify it matches tenant
                    $screenCombinedId = $screenBusinessId ?? $screenTenantId;
                    if ($screenCombinedId !== $tenantId) {
                        \App\Core\Logger::warning('UsersController::updateStaff - Preparation screen tenant isolation violation', [
                            'preparation_screen_id' => $newPreparationScreenId,
                            'screen_business_id' => $screenBusinessId,
                            'screen_tenant_id' => $screenTenantId,
                            'screen_combined_id' => $screenCombinedId,
                            'tenant_id' => $tenantId
                        ]);
                        $this->toastNotificationService->sendApiResponse('error', 'Bu hazırlık ekranına erişim yetkiniz yok.', [], 403);
                        return;
                    }
                }
            }
            
            $updateData['preparation_screen_id'] = $newPreparationScreenId;
        } elseif ($newPreparationScreenId === null && $oldPreparationScreenId !== null) {
            // Removing preparation screen assignment
            $updateData['preparation_screen_id'] = null;
        }
        
        if (empty($updateData)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
            return;
        }
        
        $result = $this->userService->update($userId, $updateData);
        
        // Handle permission updates if preparation screen changed
        if ($result && $currentRoleId && ($oldPreparationScreenId !== $newPreparationScreenId)) {
            try {
                // Remove old permissions if old screen exists
                if ($oldPreparationScreenId) {
                    $this->removePreparationScreenPermissions($currentRoleId, $oldPreparationScreenId);
                }
                
                // Assign new permissions if new screen exists
                if ($newPreparationScreenId) {
                    $this->assignPreparationScreenPermissions($userId, $currentRoleId, $newPreparationScreenId);
                }
            } catch (\Exception $e) {
                // Log but don't fail update
                \App\Core\Logger::warning('Error updating preparation screen permissions', [
                    'error' => $e->getMessage(),
                    'user_id' => $userId,
                    'old_screen_id' => $oldPreparationScreenId,
                    'new_screen_id' => $newPreparationScreenId
                ]);
            }
        }
        
        if ($result) {
            // PERFORMANCE: Aggressive cache clearing for updated users
            try {
                $cacheService = \App\Core\DependencyFactory::getCacheService();
                
                // Clear all user-related caches
                $cacheService->delete('users:all');
                
                // Clear by pattern if available
                if (method_exists($cacheService, 'deleteByPattern')) {
                    $cacheService->deleteByPattern('users:*');
                    $cacheService->deleteByPattern('user:*');
                }
                
                // Clear tenant-specific caches
                $tenantId = \App\Core\TenantContext::getId();
                if ($tenantId) {
                    $cacheService->delete('users:all:' . $tenantId);
                    $cacheService->delete('users:business:' . $tenantId);
                }
                
                // Force clear service-level caches
                if (method_exists($cacheService, 'forget')) {
                    $cacheService->forget('users:all');
                }
            } catch (\Exception $e) {
                \App\Core\Logger::warning("updateStaff: Error clearing cache - " . $e->getMessage());
            }
            
            $updatedUser = $this->userService->findByUserId($userId);
            if (!$updatedUser) {
                $updatedUser = [
                    'user_id' => $userId,
                    'name' => $updateData['name'] ?? '',
                    'role' => $updateData['role'] ?? '',
                    'pin' => ''
                ];
            } else {
                unset($updatedUser['pin']);
            }
            
            $this->toastNotificationService->sendApiResponse('success', 'notifications.success.staff_updated', [], 200, [
                'user' => $updatedUser
            ]);
        } else {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.staff_update_failed', [], 500);
        }
    }
    
    public function updateStaffPin($userId = null) {
        $this->requirePermission('staff.edit');
        
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $requestData = \App\Core\RequestParser::getRequestData();
        $userId = $userId ?? $queryParams['id'] ?? $requestData['id'] ?? '';
        
        if (empty($userId)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
            return;
        }
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_request', [], 400);
            return;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) {
            $data = $_POST;
        }
        
        $pin = $data['pin'] ?? '';
        
        if (empty($pin)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.warning.missing_fields', [], 400);
            return;
        }
        
        if (!preg_match('/^\d{4,10}$/', $pin)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.warning.pin_length', [], 400);
            return;
        }
        
        $result = $this->userService->update($userId, ['pin' => $pin]);
        
        if ($result) {
            $this->toastNotificationService->sendApiResponse('success', 'notifications.success.pin_updated', [], 200);
        } else {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.update_failed', [], 500);
        }
    }
    
    public function getStaffPin($userId = null) {
        $this->requirePermission('staff.view');
        
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $userId = $userId ?? $queryParams['id'] ?? '';
        
        if (empty($userId)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
            return;
        }
        
        $user = $this->userService->findByUserId($userId);
        
        if (!$user) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.staff_not_found', [], 404);
            return;
        }
        
        $pin = $user['pin'] ?? '';
        
        require_once __DIR__ . '/../../helpers/EncryptionHelper.php';
        
        // Check if PIN is hashed (bcrypt - cannot be decrypted)
        $isHashed = strlen($pin) >= 60 && 
                   (strpos($pin, '$2y$') === 0 || 
                    strpos($pin, '$2a$') === 0 || 
                    strpos($pin, '$2b$') === 0);
        
        if ($isHashed) {
            // Hashlenmiş PIN decrypt edilemez - kullanıcı değiştirmeli
            $this->apiResponse([
                'pin' => 'Gizli (Değiştir)',
                'is_hashed' => true,
                'is_encrypted' => false,
                'can_decrypt' => false
            ]);
            return;
        }
        
        // Try to decrypt - works for both properly encrypted PINs and old format
        try {
            $decryptedPin = \App\Helpers\EncryptionHelper::decrypt($pin);
            
            // Check if decryption actually worked (decrypt returns false on failure)
            if ($decryptedPin !== false && !empty($decryptedPin)) {
                // Successfully decrypted - log for debugging
                \App\Core\Logger::info('getStaffPin: Successfully decrypted PIN', [
                    'user_id' => $userId,
                    'encrypted_length' => strlen($pin),
                    'decrypted_length' => strlen($decryptedPin),
                    'decrypted_pin' => $decryptedPin
                ]);
                
                // Successfully decrypted - return decrypted PIN
                $this->apiResponse([
                    'pin' => $decryptedPin,
                    'is_hashed' => false,
                    'is_encrypted' => true
                ]);
                return;
            } else {
                // Decryption returned false
                \App\Core\Logger::warning('getStaffPin: Decrypt returned false', [
                    'user_id' => $userId,
                    'pin_length' => strlen($pin),
                    'pin_preview' => substr($pin, 0, 30)
                ]);
            }
        } catch (\Exception $e) {
            // Decryption failed - log for debugging
            \App\Core\Logger::error('getStaffPin: Decryption exception', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'pin_length' => strlen($pin)
            ]);
        }
        
        // If we're here, PIN is either:
        // 1. Plain text (old format)
        // 2. Encrypted with old key (can't decrypt)
        
        // Check if it looks encrypted (base64 with reasonable length)
        if (preg_match('/^[A-Za-z0-9+\/]+=*$/', $pin) && strlen($pin) > 20) {
            // Looks encrypted but we can't decrypt it
            // This is an old encrypted PIN - we need to reset it
            \App\Core\Logger::warning('getStaffPin: Found old encrypted PIN that cannot be decrypted', [
                'user_id' => $userId,
                'pin_length' => strlen($pin)
            ]);
            
            $this->apiResponse([
                'pin' => 'ESKI ŞİFRELİ - Lütfen Yeni PIN Girin',
                'is_hashed' => false,
                'is_encrypted' => false,
                'needs_reset' => true,
                'can_decrypt' => false
            ]);
            return;
        }
        
        // PIN is plain text
        $this->apiResponse([
            'pin' => $pin,
            'is_hashed' => false,
            'is_encrypted' => false
        ]);
    }
    
    public function staffDetail($userId = null) {
        $this->requirePermission('staff.view');
        
        if (empty($userId)) {
            $queryParams = \App\Core\RequestParser::getQueryParams();
            $requestData = \App\Core\RequestParser::getRequestData();
            $userId = $queryParams['id'] ?? $requestData['id'] ?? '';
        }
        
        $userId = trim($userId);
        
        if (empty($userId)) {
            $this->toastNotificationService->setFlash('error', 'notifications.error.invalid_data');
            $requestUri = $_SERVER['REQUEST_URI'] ?? '';
            $redirectUrl = (strpos($requestUri, '/business/') !== false) ? (BASE_URL . '/business/users') : (BASE_URL . '/admin/users');
            header('Location: ' . $redirectUrl);
            exit;
        }
        
        $personnelData = $this->personnelService->getPersonnelDetail($userId);
        
        if (empty($personnelData) || !isset($personnelData['user'])) {
            $this->toastNotificationService->setFlash('error', 'notifications.error.staff_not_found');
            $requestUriForRedirect = $_SERVER['REQUEST_URI'] ?? '';
            $redirectUrl = (strpos($requestUriForRedirect, '/business/') !== false) ? (BASE_URL . '/business/users') : (BASE_URL . '/admin/users');
            header('Location: ' . $redirectUrl);
            exit;
        }
        
        $allRoles = getAllRoles();
        $currentLang = getCurrentLanguage();
        
        $leaveTypes = [];
        try {
            $leaveTypes = $this->leaveTypeService->getActive();
        } catch (\Exception $e) {
            \App\Core\Logger::error("staffDetail - Error getting leave types: " . $e->getMessage());
            $leaveTypes = [];
        }
        
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        $isBusinessPanel = (strpos($requestUri, '/business/') !== false);
        $apiPrefix = $isBusinessPanel ? '/api/business' : '/api/qodmin';
        $usersListUrl = $isBusinessPanel ? (BASE_URL . '/business/users') : (BASE_URL . '/qodmin/users');
        
        $data = [
            'user' => $personnelData['user'],
            'shifts' => $personnelData['shifts'] ?? [],
            'leaves' => $personnelData['leaves'] ?? [],
            'medical_reports' => $personnelData['medical_reports'] ?? [],
            'statistics' => $personnelData['statistics'] ?? [
                'year' => date('Y'),
                'worked_days' => 0,
                'total_work_hours' => 0,
                'total_leave_days' => 0,
                'annual_leave_days' => 0,
                'remaining_annual_leave' => 0,
                'medical_report_days' => 0,
                'total_absence_days' => 0
            ],
            'all_roles' => $allRoles ?? [],
            'current_lang' => $currentLang,
            'leave_types' => $leaveTypes,
            'api_prefix' => $apiPrefix,
            'users_list_url' => $usersListUrl
        ];
        
        $this->view('admin/staff_detail', $data);
    }
    
    public function getUser($userId = null) {
        if (!$this->hasPermission('staff.view')) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 401);
            return;
        }
        
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $userId = $userId ?? $queryParams['id'] ?? '';
        if (empty($userId)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
            return;
        }
        
        $user = $this->userService->findByUserId($userId);
        if ($user) {
            unset($user['pin']);
            $this->apiResponse($user);
        } else {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.staff_not_found', [], 404);
        }
    }
    
    /**
     * Remove preparation screen permissions from a role
     * 
     * @param int $roleId Role ID
     * @param string $preparationScreenId Preparation screen ID
     * @return void
     */
    private function removePreparationScreenPermissions($roleId, $preparationScreenId) {
        if (empty($preparationScreenId)) {
            return;
        }
        
        $preparationScreenService = \App\Core\DependencyFactory::getPreparationScreenService();
        $screen = $preparationScreenService->getScreenById($preparationScreenId);
        
        if (!$screen) {
            return;
        }
        
        $slug = $screen['slug'] ?? null;
        if (empty($slug)) {
            return;
        }
        
        $permissionKeys = [
            "preparation-screen.{$slug}.view",
            "preparation-screen.{$slug}.update_status"
        ];
        
        $permissionModel = new \App\Models\SystemPermission(\App\Core\DependencyFactory::getDatabase());
        $removedCount = 0;
        
        foreach ($permissionKeys as $permissionKey) {
            $permission = $permissionModel->getByKey($permissionKey);
            if ($permission && isset($permission['permission_id'])) {
                $removed = $permissionModel->removeFromRole($roleId, $permission['permission_id']);
                if ($removed) {
                    $removedCount++;
                }
            }
        }
        
        if (class_exists('\App\Core\Logger')) {
            \App\Core\Logger::info('Preparation screen permissions removed from role', [
                'role_id' => $roleId,
                'preparation_screen_id' => $preparationScreenId,
                'slug' => $slug,
                'removed_count' => $removedCount
            ]);
        }
    }
    
    /**
     * Assign preparation screen permissions to a user's role
     * 
     * @param string $userId User ID
     * @param int $roleId Role ID
     * @param string $preparationScreenId Preparation screen ID
     * @return void
     */
    private function assignPreparationScreenPermissions($userId, $roleId, $preparationScreenId) {
        if (empty($preparationScreenId)) {
            return;
        }
        
        $preparationScreenService = \App\Core\DependencyFactory::getPreparationScreenService();
        $screen = $preparationScreenService->getScreenById($preparationScreenId);
        
        if (!$screen) {
            \App\Core\Logger::warning('assignPreparationScreenPermissions: Screen not found', [
                'preparation_screen_id' => $preparationScreenId
            ]);
            return;
        }
        
        $slug = $screen['slug'] ?? null;
        if (empty($slug)) {
            \App\Core\Logger::warning('assignPreparationScreenPermissions: Screen slug is empty', [
                'preparation_screen_id' => $preparationScreenId
            ]);
            return;
        }
        
        $permissionKeys = [
            "preparation-screen.{$slug}.view",
            "preparation-screen.{$slug}.update_status"
        ];
        
        $permissionModel = new \App\Models\SystemPermission(\App\Core\DependencyFactory::getDatabase());
        $assignedCount = 0;
        
        foreach ($permissionKeys as $permissionKey) {
            $permission = $permissionModel->getByKey($permissionKey);
            if ($permission && isset($permission['permission_id'])) {
                $assigned = $permissionModel->assignToRole($roleId, $permission['permission_id']);
                if ($assigned) {
                    $assignedCount++;
                }
            } else {
                // Permission might not exist yet, DynamicPermissionService should create it
                \App\Core\Logger::info('assignPreparationScreenPermissions: Permission not found, may be created dynamically', [
                    'permission_key' => $permissionKey
                ]);
            }
        }
        
        if (class_exists('\App\Core\Logger')) {
            \App\Core\Logger::info('Preparation screen permissions assigned to role', [
                'user_id' => $userId,
                'role_id' => $roleId,
                'preparation_screen_id' => $preparationScreenId,
                'slug' => $slug,
                'assigned_count' => $assignedCount,
                'total_permissions' => count($permissionKeys)
            ]);
        }
    }
}

