<?php
namespace App\Core;

require_once __DIR__ . '/Auth/PermissionChecker.php';
require_once __DIR__ . '/Auth/RoleManager.php';
require_once __DIR__ . '/Auth/SessionValidator.php';
require_once __DIR__ . '/../services/CacheService.php';

use App\Core\Auth\PermissionChecker;
use App\Core\Auth\RoleManager;
use App\Core\Auth\SessionValidator;

/**
 * Centralized Authorization System (Facade Pattern)
 * 
 * Handles role-based and permission-based access control.
 * Uses singleton pattern to ensure consistent authorization across the application.
 * Delegates to specialized classes: PermissionChecker, RoleManager, SessionValidator
 * 
 * Features:
 * - Permission-based access control
 * - Role-based access control
 * - Session management
 * - Permission caching
 * 
 * @package App\Core
 * 
 * @example
 * $auth = Authorization::getInstance();
 * if ($auth->hasPermission('orders.view')) {
 *     // User can view orders
 * }
 */
class Authorization {
    private static $instance = null;
    private $permissions = [];
    private $rolePermissions = []; // role_id => [permissions]
    private $roleService = null;
    private $db = null;
    private $permissionsLoaded = false;
    
    // Delegated components
    private $permissionChecker = null;
    private $packagePermissionChecker = null;
    private $roleManager = null;
    private $sessionValidator = null;
    
    private function __construct() {
        require_once __DIR__ . '/SessionManager.php';
        // Skip validation during Authorization initialization to prevent redirect loops
        // Validation will be done later when actually checking permissions/roles
        SessionManager::ensureSession(true);
        
        if (!defined('BASE_URL')) {
            require_once __DIR__ . '/../config/config.php';
        }
        
        // Ensure Logger is loaded
        if (!class_exists('\App\Core\Logger')) {
            // Logger is autoloaded
        }
        
        // Initialize database connection and role service
        try {
            require_once __DIR__ . '/DependencyFactory.php';
            $this->db = DependencyFactory::getDatabase();
            $this->roleService = DependencyFactory::getRoleService();
        } catch (\Exception $e) {
            // If database is not available, use fallback
            \App\Core\Logger::error("Authorization: Could not initialize RoleService", ['error' => $e->getMessage()]);
        }
        
        // Initialize delegated components
        $this->permissionChecker = new PermissionChecker($this->db, $this->roleService, [], []);
        $this->roleManager = new RoleManager($this->db, $this->roleService);
        $this->sessionValidator = new SessionValidator();
        
        require_once __DIR__ . '/Auth/PackagePermissionChecker.php';
        $this->packagePermissionChecker = new \App\Core\Auth\PackagePermissionChecker($this);
        
        // Ensure role_id is in session if user is logged in
        $this->roleManager->ensureRoleIdInSession();
        
        $this->loadPermissions();
    }
    
    
    /**
     * Get singleton instance
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Load permissions from database (role_id based) with fallback to config
     * Cached for 300s to avoid hammering DB on every request.
     */
    private function loadPermissions() {
        // Cache hit: skip DB and config file load
        try {
            $cache = new \App\Services\CacheService();
            $cached = $cache->get('auth:permissions:all');
            if (is_array($cached) && isset($cached['permissions'], $cached['rolePermissions'])) {
                $this->permissions = $cached['permissions'];
                $this->rolePermissions = $cached['rolePermissions'];
                $this->permissionsLoaded = true;
                if ($this->permissionChecker) {
                    $this->permissionChecker->updateCache($this->permissions, $this->rolePermissions);
                }
                return;
            }
        } catch (\Throwable $e) {
            // Cache miss/error, fall through to DB load
        }

        // Ensure Logger is loaded
        if (!class_exists('\App\Core\Logger')) {
            // Logger is autoloaded
        }

        // Try to load from database first (role_id based)
        if ($this->db && $this->roleService) {
            try {
                // Sync dynamic permissions before loading (ensures new structures are integrated)
                try {
                    $dynamicPermissionService = DependencyFactory::getDynamicPermissionService();
                    $dynamicPermissionService->discoverAllDynamicPermissions();
                } catch (\Exception $e) {
                    // Non-critical, continue loading permissions
                    if (class_exists('\App\Core\Logger')) {
                        \App\Core\Logger::debug("Dynamic permission sync skipped: " . $e->getMessage());
                    }
                }
                
                // Load all active roles
                $roles = $this->roleService->getActiveRoles();

                foreach ($roles as $role) {
                    $roleId = $role['role_id'];
                    $roleCode = $role['role_code'] ?? '';

                    // Get permissions for this role_id
                    $permissionKeys = $this->roleService->getRolePermissionKeys($roleId);
                    
                    // Store by role_id (primary)
                    $this->rolePermissions[$roleId] = $permissionKeys;
                    
                    // Also store by role_code for backward compatibility
                    if (!empty($roleCode)) {
                        $this->rolePermissions[$roleCode] = $permissionKeys;
                    }
                }
                
                // Load all permissions
                $sql = "SELECT permission_key, permission_name FROM system_permissions ORDER BY permission_key";
                $stmt = $this->db->prepare($sql);
                $stmt->execute();
                $permissions = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                foreach ($permissions as $perm) {
                    $this->permissions[$perm['permission_key']] = $perm['permission_name'];
                }
                
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::debug("Authorization: Permissions loaded from database", [
                        'roles_count' => count($roles),
                        'role_permissions_count' => count($this->rolePermissions),
                        'permissions_count' => count($this->permissions)
                    ]);
                }
                
                // Update PermissionChecker cache
                if ($this->permissionChecker) {
                    $this->permissionChecker->updateCache($this->permissions, $this->rolePermissions);
                }
                
                // Persist to cache for 300s
                try {
                    $cache->set('auth:permissions:all', [
                        'permissions' => $this->permissions,
                        'rolePermissions' => $this->rolePermissions,
                    ], 300);
                } catch (\Throwable $cacheEx) {
                    // Cache write failure is non-critical
                }

                $this->permissionsLoaded = true;
                return; // Successfully loaded from database
            } catch (\Exception $e) {
                \App\Core\Logger::error("Authorization: Failed to load permissions from database", ['error' => $e->getMessage()]);
                // Fall through to config file fallback
            }
        }
        
        // Fallback to config file (for backward compatibility and initial setup)
        $permissionsFile = __DIR__ . '/../config/permissions.php';
        if (file_exists($permissionsFile)) {
            $config = include $permissionsFile;
            $this->permissions = $config['permissions'] ?? [];
            $fallbackRolePermissions = $config['role_permissions'] ?? [];
            
            // Dynamically convert role_code to role_id using RoleService
            if ($this->roleService) {
                try {
                    $roles = $this->roleService->getActiveRoles();
                    $roleCodeToRoleId = [];
                    foreach ($roles as $role) {
                        if (!empty($role['role_code']) && !empty($role['role_id'])) {
                            $roleCodeToRoleId[strtoupper($role['role_code'])] = $role['role_id'];
                        }
                    }
                    
                    foreach ($fallbackRolePermissions as $roleCode => $permissions) {
                        // Store by role_code (for backward compatibility)
                        $this->rolePermissions[$roleCode] = $permissions;
                        
                        // Also store by role_id if mapping exists
                        $upperRoleCode = strtoupper($roleCode);
                        if (isset($roleCodeToRoleId[$upperRoleCode])) {
                            $this->rolePermissions[$roleCodeToRoleId[$upperRoleCode]] = $permissions;
                        }
                    }
                } catch (\Exception $e) {
                    // If RoleService fails, just use role_code
                    foreach ($fallbackRolePermissions as $roleCode => $permissions) {
                        $this->rolePermissions[$roleCode] = $permissions;
                    }
                }
            } else {
                // No RoleService, just use role_code
                foreach ($fallbackRolePermissions as $roleCode => $permissions) {
                    $this->rolePermissions[$roleCode] = $permissions;
                }
            }
            
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::debug("Authorization: Permissions loaded from config file (fallback)", [
                    'role_permissions_count' => count($this->rolePermissions),
                    'permissions_count' => count($this->permissions)
                ]);
            }
            
            // Update PermissionChecker cache
            if ($this->permissionChecker) {
                $this->permissionChecker->updateCache($this->permissions, $this->rolePermissions);
            }

            // Persist to cache for 300s
            try {
                $cache->set('auth:permissions:all', [
                    'permissions' => $this->permissions,
                    'rolePermissions' => $this->rolePermissions,
                ], 300);
            } catch (\Throwable $cacheEx) {
                // Cache write failure is non-critical
            }

            $this->permissionsLoaded = true;
        } else {
            $logMsg = "Authorization: Permissions config file not found - file_path: {$permissionsFile}";
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error("Authorization: Permissions config file not found", [
                    'file_path' => $permissionsFile
                ]);
            } else {
                \App\Core\Logger::debug($logMsg);
            }
        }
    }
    
    /**
     * Check if user is logged in (delegated to SessionValidator)
     */
    public function isLoggedIn() {
        return $this->sessionValidator->isLoggedIn();
    }
    
    /**
     * Get current user role (delegated to RoleManager)
     */
    public function getCurrentRole() {
        return $this->roleManager->getCurrentRole();
    }
    
    /**
     * Get current user role_id (always returns role_id)
     */
    public function getCurrentRoleId(): ?string {
        $role = $this->getCurrentRole();
        if (!$role) {
            return null;
        }
        
        // If already role_id, return it
        if (strpos($role, 'ROLE_') === 0) {
            return $role;
        }
        
        // Convert role_code to role_id
        if ($this->roleService) {
            try {
                $roleData = $this->roleService->getByRoleCode($role);
                if ($roleData && isset($roleData['role_id'])) {
                    return $roleData['role_id'];
                }
            } catch (\Exception $e) {
                // Fall through
            }
        }
        
            // Try to get from RoleService dynamically
            if ($this->roleService) {
                try {
                    $roleData = $this->roleService->getByRoleCode($role);
                    if ($roleData && isset($roleData['role_id'])) {
                        return $roleData['role_id'];
                    }
                } catch (\Exception $e) {
                    // Fall through
                }
            }
            
            // Fallback: Try RoleMapper
            // RoleMapper is autoloaded
            $roleMapper = \App\Services\RoleMapper::getInstance();
            return $roleMapper->getRoleId($role);
    }
    
    /**
     * Get current user ID
     */
    public function getCurrentUserId() {
        SessionManager::ensureSession();
        return SessionManager::get('user_id');
    }
    
    /**
     * Get current customer ID
     */
    public function getCurrentCustomerId() {
        SessionManager::ensureSession();
        return SessionManager::get('customer_id');
    }
    
    /**
     * Check if user has specific role
     * BUSINESS_MANAGER is treated as MANAGER for compatibility
     */
    public function hasRole($role) {
        require_once __DIR__ . '/SessionManager.php';
        SessionManager::ensureSession();
        if ($this->roleManager->hasRole(
            (string) $role,
            $this->roleManager->getCurrentRoleId(),
            SessionManager::get('role')
        )) {
            return true;
        }

        $currentRole = $this->getCurrentRole();
        if (!$currentRole) {
            return false;
        }

        $normalizedCurrentRole = strtoupper(trim($currentRole));
        $normalizedRole = strtoupper(trim($role));
        
        // Direct match
        if ($normalizedCurrentRole === $normalizedRole) {
            return true;
        }
        
        // Remove ROLE_ prefix for comparison
        $currentRoleCode = str_replace('ROLE_', '', $normalizedCurrentRole);
        $targetRoleCode = str_replace('ROLE_', '', $normalizedRole);
        
        // Match after removing prefix
        if ($currentRoleCode === $targetRoleCode) {
            return true;
        }
        
        // BUSINESS_MANAGER and TRIAL are both equivalent to MANAGER
        if (($currentRoleCode === 'BUSINESS_MANAGER' || $currentRoleCode === 'TRIAL' ||
             $normalizedCurrentRole === 'ROLE_BUSINESS_MANAGER' || $normalizedCurrentRole === 'ROLE_TRIAL') &&
            ($targetRoleCode === 'MANAGER' || $normalizedRole === 'ROLE_MANAGER')) {
            return true;
        }

        // TRIAL is equivalent to BUSINESS_MANAGER
        if ($currentRoleCode === 'TRIAL' &&
            ($targetRoleCode === 'BUSINESS_MANAGER' || $normalizedRole === 'ROLE_BUSINESS_MANAGER')) {
            return true;
        }
        
        if (($currentRoleCode === 'MANAGER' || $normalizedCurrentRole === 'ROLE_MANAGER') &&
            ($targetRoleCode === 'BUSINESS_MANAGER' || $normalizedRole === 'ROLE_BUSINESS_MANAGER')) {
            return true;
        }
        
        // SUPER_ADMIN has all roles
        if ($currentRoleCode === 'SUPER_ADMIN' || $normalizedCurrentRole === 'ROLE_SUPER_ADMIN') {
            return true; // Super admin has all roles
        }
        
        // ADMIN and ADMINISTRATOR are equivalent to MANAGER
        $managerRoles = ['MANAGER', 'BUSINESS_MANAGER', 'ADMIN', 'ADMINISTRATOR'];
        $currentIsManager = in_array($currentRoleCode, $managerRoles) || in_array($normalizedCurrentRole, ['ROLE_MANAGER', 'ROLE_BUSINESS_MANAGER', 'ROLE_ADMIN', 'ROLE_ADMINISTRATOR']);
        $targetIsManager = in_array($targetRoleCode, $managerRoles) || in_array($normalizedRole, ['ROLE_MANAGER', 'ROLE_BUSINESS_MANAGER', 'ROLE_ADMIN', 'ROLE_ADMINISTRATOR']);
        
        if ($currentIsManager && $targetIsManager) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Check if user has any of the specified roles
     * BUSINESS_MANAGER is treated as MANAGER for compatibility
     */
    public function hasAnyRole($roles) {
        if (!is_array($roles)) {
            $roles = [$roles];
        }
        require_once __DIR__ . '/SessionManager.php';
        SessionManager::ensureSession();

        // Aynı mantık requireAnyRole() ile: role_id + role_code (getCurrentRole() UUID döndüğü için
        // eskiden tüm dize tabanlı eşleşmeler bozuluyordu)
        $roleId = $this->roleManager->getCurrentRoleId();
        $roleCode = SessionManager::get('role');
        if ($this->roleManager->hasAnyRole($roles, $roleId, $roleCode)) {
            return true;
        }

        $currentRole = $this->getCurrentRole();
        if ($this->roleService && $roleId && (!$currentRole || $currentRole === $roleId)) {
            try {
                $row = $this->roleService->getByRoleId($roleId);
                if (!empty($row['role_code'])) {
                    $currentRole = (string) $row['role_code'];
                }
            } catch (\Throwable $e) {
                // keep getCurrentRole value
            }
        }

        $normalizedCurrentRole = strtoupper(trim((string) $currentRole));

        $normalizedRoles = array_map(function ($role) {
            return strtoupper(trim($role));
        }, $roles);

        if (in_array($normalizedCurrentRole, $normalizedRoles, true)) {
            return true;
        }

        $currentRoleCode = str_replace('ROLE_', '', $normalizedCurrentRole);
        foreach ($normalizedRoles as $r) {
            if (str_replace('ROLE_', '', strtoupper(trim($r))) === $currentRoleCode && $currentRoleCode !== '') {
                return true;
            }
        }

        // Önceki sürümle uyum: yalnızca klasik yönetici rolleri (TRIAL/business owner burada değil)
        $managerRolesLegacy = [
            'MANAGER', 'ROLE_MANAGER', 'BUSINESS_MANAGER', 'ROLE_BUSINESS_MANAGER',
            'ADMIN', 'ROLE_ADMIN', 'ADMINISTRATOR', 'ROLE_ADMINISTRATOR',
        ];
        $hasManagerRole = in_array($normalizedCurrentRole, $managerRolesLegacy, true);
        $requiresManagerRole = false;
        foreach ($normalizedRoles as $role) {
            if (in_array($role, $managerRolesLegacy, true)) {
                $requiresManagerRole = true;
                break;
            }
        }
        if ($hasManagerRole && $requiresManagerRole) {
            return true;
        }

        return false;
    }
    
    /**
     * Check if current user is super admin
     * @return bool True if user is super admin
     */
    public function isSuperAdmin(): bool {
        require_once __DIR__ . '/SessionManager.php';
        SessionManager::ensureSession();
        
        $roleId = $this->getCurrentRoleId();
        $roleCode = SessionManager::get('role');
        $isSuperAdminSession = SessionManager::get('is_super_admin') === true;
        
        // Debug log
        if (class_exists('\App\Core\Logger')) {
            \App\Core\Logger::debug("Authorization::isSuperAdmin check", [
                'is_super_admin_session' => $isSuperAdminSession,
                'role_code' => $roleCode,
                'role_id' => $roleId,
                'session_role' => SessionManager::get('role'),
                'session_is_super_admin' => SessionManager::get('is_super_admin')
            ]);
        }
        
        if ($isSuperAdminSession) {
            return true;
        }
        
        // Check by role code
        if ($roleCode) {
            $normalizedRole = strtoupper(trim($roleCode));
            if ($normalizedRole === 'SUPER_ADMIN' || $normalizedRole === 'ROLE_SUPER_ADMIN' || 
                $normalizedRole === 'QODMIN' || $normalizedRole === 'ROLE_QODMIN') {
                return true;
            }
        }
        
        return $this->permissionChecker->isSuperAdmin($roleId, $roleCode);
    }
    
    /**
     * Check if user has specific permission
     */
    public function hasPermission($permission) {
        // Super admin has all permissions
        if ($this->isSuperAdmin()) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::debug("Authorization: Permission granted (Super Admin bypass)", [
                    'permission' => $permission,
                    'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown'
                ]);
            }
            return true;
        }
        
        // CRITICAL: Check if user is a tenant employee (WAITER, KITCHEN, CASHIER, etc.)
        // Tenant employees should use role-based permissions ONLY, not package permissions
        $currentRole = $this->getCurrentRole();
        $isTenantEmployee = false;
        $tenantEmployeeRoles = [
            'WAITER', 'GARSON', 'ROLE_WAITER',
            'KITCHEN', 'MUTFAK', 'ROLE_KITCHEN',
            'CASHIER', 'KASIYER', 'ROLE_CASHIER',
            'BARISTA', 'ROLE_BARISTA',
            'BARTENDER', 'ROLE_BARTENDER',
            'VALET', 'VALE', 'ROLE_VALET',
            'HOOKAH', 'NARGILE', 'ROLE_HOOKAH'
        ];
        
        if ($currentRole) {
            $normalizedRole = strtoupper(trim($currentRole));
            $isTenantEmployee = in_array($normalizedRole, $tenantEmployeeRoles);
        }
        
        // CRITICAL: For tenant employees, skip package permission check
        // They should ONLY use role-based permissions from the permissions system
        if ($isTenantEmployee) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::debug("Authorization: Tenant employee detected, using role-based permissions only", [
                    'permission' => $permission,
                    'role' => $currentRole,
                    'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown'
                ]);
            }
            
            // Continue to role-based permission check (skip package check below)
            return $this->checkRoleBasedPermission($permission);
        }
        
        // Check if user is BUSINESS_MANAGER or TRIAL
        // Both roles have identical access — TRIAL is the free-trial version of BUSINESS_MANAGER.
        $isBusinessManager = false;
        if ($currentRole) {
            $normalizedRole = strtoupper(trim($currentRole));
            $isBusinessManager = in_array($normalizedRole, [
                'BUSINESS_MANAGER', 'ROLE_BUSINESS_MANAGER',
                'TRIAL', 'ROLE_TRIAL',
            ], true);
        }

        if ($isBusinessManager) {
            $normalizedPermission = strtolower(trim($permission));

            // 1. Dynamic preparation-screen permission fallback:
            //    preparation-screen.{slug}.view  →  preparation-screens.view
            if (preg_match('/^preparation-screen\.(.+)\.(view|update_status)$/', $normalizedPermission)) {
                // Recheck against the generic preparation-screens.view (avoid infinite loop)
                static $prepFallbackGuard = [];
                if (empty($prepFallbackGuard[$normalizedPermission])) {
                    $prepFallbackGuard[$normalizedPermission] = true;
                    $result = $this->hasPermission('preparation-screens.view');
                    unset($prepFallbackGuard[$normalizedPermission]);
                    if ($result) return true;
                }
            }

            // 2. SuperAdmin-only prefix block (always deny for non-superadmin)
            $superAdminOnlyPrefixes = [
                'superadmin.', 'system.logs.', 'system.settings.',
                'businesses.', 'business_owners.',
            ];
            foreach ($superAdminOnlyPrefixes as $prefix) {
                if (strpos($normalizedPermission, $prefix) === 0) {
                    return false;
                }
            }

            // 3. "Always-allowed" permissions — no subscription required.
            //    These cover account management, billing, packages, and basic settings.
            static $alwaysAllowed = [
                'dashboard.view', 'dashboard.analytics',
                'profile.view', 'profile.edit', 'profile.update',
                'account.view', 'account.edit', 'account.update', 'account.delete',
                'packages.view', 'packages.purchase',
                'customer.packages', 'customer.packages.view',
                'subscriptions.view', 'subscriptions.manage',
                'company.view', 'company.edit', 'company.update',
                'payment.methods.view', 'payment.methods.add', 'payment.methods.delete',
                'billing.view', 'billing.download', 'invoices.view',
                'settings.view', 'settings.edit',
                'receipt.templates.view', 'receipt.templates.edit',
            ];
            if (in_array($normalizedPermission, $alwaysAllowed, true)) {
                return true;
            }

            // 4. Feature permissions — require an active subscription (trial or paid).
            //    With an active subscription, check role_permissions in DB.
            //    Both BUSINESS_MANAGER and TRIAL roles have all business features assigned.
            $customerId = $this->getCurrentCustomerId();
            if ($customerId) {
                // Per-request subscription cache to avoid repeated DB queries
                static $subscriptionCache = [];
                if (!array_key_exists($customerId, $subscriptionCache)) {
                    try {
                        $subscriptionService = \App\Core\DependencyFactory::getSubscriptionService();
                        $subscriptionCache[$customerId] = $subscriptionService->getCustomerSubscription($customerId);
                    } catch (\Exception $e) {
                        $subscriptionCache[$customerId] = null;
                    }
                }
                $subscription = $subscriptionCache[$customerId];

                $subStatus = strtolower($subscription['status'] ?? '');
                $treatAsSubscribed = $subscription && (
                    $subStatus === 'active'
                    || ($subStatus === 'pending' && floatval($subscription['amount'] ?? 0) > 0)
                );

                if ($treatAsSubscribed) {

                    // ---- TRIAL expiry enforcement ----
                    // For TRIAL role users, check whether the trial period has actually ended.
                    // The subscription row keeps status='active' until a cron flips it, so we
                    // must check trial_ends_at directly.
                    $isTrial = !empty($subscription['is_trial']);
                    if ($isTrial) {
                        $trialEndsAt = $subscription['trial_ends_at']
                            ?? $subscription['trial_end']
                            ?? $subscription['current_period_end']
                            ?? null;
                        $trialEndTs = $trialEndsAt ? strtotime($trialEndsAt) : PHP_INT_MAX;
                        $now = time();

                        if ($now > $trialEndTs) {
                            // Trial has expired.
                            $gracePeriodDays  = 7;
                            $graceEndTs       = $trialEndTs + ($gracePeriodDays * 86400);

                            if ($now > $graceEndTs) {
                                // Grace period also over — full block.
                                // Only "always-allowed" items (already handled above) pass through.
                                return false;
                            }

                            // Within grace period → VIEW-ONLY mode.
                            // Only allow read-type permissions (ends with .view, .analytics,
                            // .list, .read, .download, .print) so users can see their data
                            // but cannot mutate anything.
                            if (preg_match('/\.(view|analytics|list|read|download|print)$/i', $normalizedPermission)) {
                                return true;
                            }
                            // Deny everything else (create, edit, delete, process, etc.)
                            return false;
                        }
                        // Trial is still active — fall through to role-based check.
                    }
                    // ---- end TRIAL expiry enforcement ----

                    // Check role_permissions for this user's role (BUSINESS_MANAGER or TRIAL)
                    // This is the primary access-control mechanism — all features are role-granted.
                    if ($this->checkRoleBasedPermission($permission)) {
                        return true;
                    }
                    // Backward-compat: also check package_permissions (for existing packages)
                    if (!empty($subscription['package_id'])) {
                        if ($this->permissionChecker->hasPackagePermission($permission, $customerId)) {
                            return true;
                        }
                    }
                }
            }

            // No active subscription or permission not granted — deny.
            return false;
        }
        
        // Non-BUSINESS_MANAGER için paket kontrolü (eğer customer_id varsa)
        $customerId = $this->getCurrentCustomerId();
        if ($customerId && $this->permissionChecker->hasPackagePermission($permission, $customerId)) {
            return true;
        }
        
        // Continue with role-based permission check (for MANAGER, ADMIN, etc. and tenant employees)
        return $this->checkRoleBasedPermission($permission);
    }
    
    /**
     * Check role-based permission (without package check)
     * Used for tenant employees and other non-business-manager roles
     */
    private function checkRoleBasedPermission($permission) {
        // Ensure Logger is loaded
        if (!class_exists('\App\Core\Logger')) {
            // Logger is autoloaded
        }

        // Log permission check attempt
        if (class_exists('\App\Core\Logger')) {
            \App\Core\Logger::debug("Authorization: Checking permission", [
                'permission' => $permission,
                'is_logged_in' => $this->isLoggedIn(),
                'session_role' => SessionManager::get('role'),
                'role_permissions_loaded' => !empty($this->rolePermissions),
                'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown'
            ]);
        } else {
            \App\Core\Logger::debug("Authorization: Checking permission", [
                'permission' => $permission,
                'is_logged_in' => $this->isLoggedIn()
            ]);
        }

        if (!$this->isLoggedIn()) {
            $logMsg = "Authorization: User not logged in for permission check - permission: {$permission}, uri: " . ($_SERVER['REQUEST_URI'] ?? 'unknown');
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::warning($logMsg, [
                    'permission' => $permission,
                    'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown'
                ]);
            } else {
                \App\Core\Logger::debug($logMsg);
            }
            return false;
        }

        // Ensure permissions are loaded
        if (empty($this->rolePermissions) || !is_array($this->rolePermissions)) {
            $this->loadPermissions();
            $logMsg = "Authorization: Permissions reloaded - count: " . count($this->rolePermissions);
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::debug($logMsg, ['role_permissions_count' => count($this->rolePermissions)]);
            } else {
                \App\Core\Logger::debug($logMsg);
            }
        }

        $currentRole = $this->getCurrentRole();
        if (!$currentRole) {
            // Try to normalize role using RoleMapper
            // RoleMapper is autoloaded
            $roleMapper = \App\Services\RoleMapper::getInstance();
            $sessionRole = SessionManager::get('role');
            if ($sessionRole) {
                $currentRole = $roleMapper->normalizeRole($sessionRole);
                // Update session with normalized role
                SessionManager::set('role', $currentRole);
            }

            if (!$currentRole) {
                // No role found - check if this is a new login (within 5 seconds)
                // If so, wait a bit for role to be set (race condition)
                $loginTime = SessionManager::get('login_time');
                $isNewLogin = $loginTime && (time() - $loginTime) <= 5;
                
                if ($isNewLogin) {
                    // New login - role might not be set yet, try to get it from RoleManager
                    try {
                        $roleId = $this->roleManager->getCurrentRoleId();
                        if ($roleId) {
                            $roleData = $this->roleService->getByRoleId($roleId);
                            if ($roleData && isset($roleData['role_code'])) {
                                $currentRole = $roleData['role_code'];
                                SessionManager::set('role', $currentRole);
                                // Retry permission check
                                if ($this->permissionChecker->hasPermission($permission, $roleId, $currentRole)) {
                                    return true;
                                }
                            }
                        }
                    } catch (\Exception $e) {
                        // Role service error - continue with normal flow
                        if (class_exists('\App\Core\Logger')) {
                            \App\Core\Logger::debug("Authorization: Failed to get role for new login: " . $e->getMessage());
                        }
                    }
                }
                
                // No role found - session is invalid
                // CRITICAL: Check if we're on login/auth page to prevent redirect loops
                $uri = $_SERVER['REQUEST_URI'] ?? '';
                $isLoginPage = strpos($uri, '/login') !== false || strpos($uri, '/auth/') !== false;
                $isApiRequest = strpos($uri, '/api/') !== false;
                
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::warning("Authorization: No role found in session - clearing session", [
                        'permission' => $permission,
                        'is_new_login' => $isNewLogin,
                        'login_time' => $loginTime,
                        'is_login_page' => $isLoginPage,
                        'session_data' => [
                            'user_id' => SessionManager::get('user_id'),
                            'logged_in' => SessionManager::get('logged_in'),
                            'role' => SessionManager::get('role'),
                            'role_id' => SessionManager::get('role_id')
                        ],
                        'request_uri' => $uri
                    ]);
                }
                
                // If we're on login page, don't redirect (prevent redirect loop)
                // Just return false and let login page handle session clearing
                if ($isLoginPage) {
                    return false;
                }
                
                // Clear session and redirect to login (not unauthorized)
                // Only redirect if NOT on login page to prevent redirect loops
                session_destroy();
                session_start();
                SessionManager::resetInitialized();
                
                if (!$isApiRequest) {
                    // CRITICAL: Use current host (with subdomain) for redirect to preserve subdomain
                    $currentHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
                    $protocol = getProtocol();
                    $loginUrl = $protocol . '://' . $currentHost . '/login?session_invalid=1';
                    header('Location: ' . $loginUrl);
                    exit;
                }
                
                return false;
            }
        }

        // CRITICAL: Super Admin bypass - Super Admin has all permissions
        // Check if current user is Super Admin - if yes, grant all permissions
        $isSuperAdmin = false;
        if ($currentRole) {
            $normalizedRole = strtoupper(trim($currentRole));
            $isSuperAdmin = (
                $normalizedRole === 'SUPER_ADMIN' ||
                $normalizedRole === 'ROLE_SUPER_ADMIN' ||
                $normalizedRole === 'QODMIN' ||
                $normalizedRole === 'ROLE_QODMIN'
            );
        }

        // If Super Admin, return true immediately
        if ($isSuperAdmin) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::debug("Authorization: Permission granted (Super Admin bypass)", [
                    'permission' => $permission,
                    'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown'
                ]);
            }
            return true;
        }

        // Get role_id
        $roleId = $this->getCurrentRoleId();
        
        // Check using PermissionChecker
        if ($this->permissionChecker->hasPermission($permission, $roleId, $currentRole)) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::debug("Authorization: Permission granted via role-based check", [
                    'permission' => $permission,
                    'role' => $currentRole,
                    'role_id' => $roleId,
                    'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown'
                ]);
            }
            return true;
        }
        
        // CRITICAL: Check for dynamic permission fallback (e.g., preparation-screen.{slug}.view -> preparation-screens.view)
        // This allows users with general permissions to access specific resources
        if (preg_match('/^preparation-screen\.(.+)\.view$/', $permission, $matches)) {
            $fallbackPermission = 'preparation-screens.view';
            // Recursively check fallback permission (but prevent infinite loop)
            static $fallbackCheckedGeneral = [];
            if (!isset($fallbackCheckedGeneral[$permission])) {
                $fallbackCheckedGeneral[$permission] = true;
                if ($this->hasPermission($fallbackPermission)) {
                    if (class_exists('\App\Core\Logger')) {
                        \App\Core\Logger::debug("Authorization: Dynamic permission granted via fallback", [
                            'permission' => $permission,
                            'fallback_permission' => $fallbackPermission,
                            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown'
                        ]);
                    }
                    unset($fallbackCheckedGeneral[$permission]);
                    return true;
                }
                unset($fallbackCheckedGeneral[$permission]);
            }
        }
        
        // Hazırlık ekranı: update_status için aynı ekranın view izni yeterli (nargile/içecek personeli Hazırla/Servis yapabilsin)
        if (preg_match('/^preparation-screen\.(.+)\.update_status$/', $permission, $matches)) {
            $slug = $matches[1];
            $viewPermission = "preparation-screen.{$slug}.view";
            static $fallbackCheckedUpdate = [];
            if (!isset($fallbackCheckedUpdate[$permission])) {
                $fallbackCheckedUpdate[$permission] = true;
                if ($this->hasPermission($viewPermission)) {
                    if (class_exists('\App\Core\Logger')) {
                        \App\Core\Logger::debug("Authorization: preparation-screen update_status granted via view", [
                            'permission' => $permission,
                            'view_permission' => $viewPermission
                        ]);
                    }
                    unset($fallbackCheckedUpdate[$permission]);
                    return true;
                }
                unset($fallbackCheckedUpdate[$permission]);
            }
        }
        
        // CRITICAL: Manager/Admin role bypass - Managers and Admins have all permissions
        // Check if current user is Manager/Admin/Administrator - if yes, grant all permissions
        // Also check role_id for new login scenarios
        if ($currentRole) {
            $normalizedRole = strtoupper(trim($currentRole));
            $roleId = $this->roleManager->getCurrentRoleId();

            // Check by role code - include MANAGER, BUSINESS_MANAGER, ADMIN, ADMINISTRATOR
            $isManager = (
                $normalizedRole === 'MANAGER' ||
                $normalizedRole === 'ROLE_MANAGER' ||
                $normalizedRole === 'BUSINESS_MANAGER' ||
                $normalizedRole === 'ROLE_BUSINESS_MANAGER' ||
                $normalizedRole === 'ADMIN' ||
                $normalizedRole === 'ROLE_ADMIN' ||
                $normalizedRole === 'ADMINISTRATOR' ||
                $normalizedRole === 'ROLE_ADMINISTRATOR'
            );
            
            // Also check by role_id if available (for new login scenarios)
            if (!$isManager && $roleId && $this->roleService) {
                try {
                    $roleData = $this->roleService->getByRoleId($roleId);
                    if ($roleData && isset($roleData['role_code'])) {
                        $roleCode = strtoupper(trim($roleData['role_code']));
                        $isManager = (
                            $roleCode === 'MANAGER' || 
                            $roleCode === 'ROLE_MANAGER' ||
                            $roleCode === 'ADMIN' ||
                            $roleCode === 'ROLE_ADMIN' ||
                            $roleCode === 'ADMINISTRATOR' ||
                            $roleCode === 'ROLE_ADMINISTRATOR'
                        );
                        if ($isManager) {
                            // Update session with correct role
                            SessionManager::set('role', $roleCode);
                            $currentRole = $roleCode;
                        }
                    }
                } catch (\Exception $e) {
                    // Role service error - continue with role code check
                    if (class_exists('\App\Core\Logger')) {
                        \App\Core\Logger::debug("Authorization: Failed to check role_id for Manager/Admin bypass: " . $e->getMessage());
                    }
                }
            }
            
            if ($isManager) {
                // Manager/Admin has all permissions - bypass check
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::debug("Authorization: Permission granted (Manager/Admin bypass)", [
                        'permission' => $permission,
                        'role' => $normalizedRole,
                        'role_id' => $roleId,
                        'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown'
                    ]);
                }
                return true;
            }
        }
        
        // Get role_id (preferred) or role_code (fallback)
        $roleId = $this->getCurrentRoleId();
        $roleCode = null;
        
        // Also check role_id for Manager/Admin bypass (in case currentRole was role_code)
        // BUSINESS_MANAGER excluded - they only have package-based permissions
        if ($roleId) {
            $normalizedRoleId = strtoupper(trim($roleId));
            if (
                $normalizedRoleId === 'ROLE_MANAGER' ||
                $normalizedRoleId === 'ROLE_ADMIN' ||
                $normalizedRoleId === 'ROLE_ADMINISTRATOR'
            ) {
                // Manager/Admin has all permissions - bypass check
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::debug("Authorization: Permission granted (Manager/Admin bypass by role_id)", [
                        'permission' => $permission,
                        'role_id' => $normalizedRoleId,
                        'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown'
                    ]);
                }
                return true;
            }
        }
        
        // Determine if currentRole is role_id or role_code
        if ($currentRole && strpos($currentRole, 'ROLE_') === 0) {
            // It's a role_id
            $roleId = $currentRole;
            // Also get role_code for fallback lookup
            if ($this->roleService) {
                try {
                    $roleData = $this->roleService->getByRoleId($roleId);
                    if ($roleData && isset($roleData['role_code'])) {
                        $roleCode = strtoupper(trim($roleData['role_code']));
                    }
                } catch (\Exception $e) {
                    // Fall through
                }
            }
        } else {
            // It's a role_code
            $roleCode = strtoupper(trim($currentRole));
            // Try to convert role_code to role_id
            if ($this->roleService) {
                try {
                    $roleData = $this->roleService->getByRoleCode($roleCode);
                    if ($roleData && isset($roleData['role_id'])) {
                        $roleId = $roleData['role_id'];
                        // Update session with role_id for future use
                        SessionManager::set('role_id', $roleId);
                    }
                } catch (\Exception $e) {
                    // Fall through
                }
            }
        }
        
        // Get role permissions - try role_id first, then role_code
        $rolePerms = [];
        $usedRoleKey = null;
        
        // First try: role_id from cache
        if ($roleId && isset($this->rolePermissions[$roleId])) {
            $rolePerms = $this->rolePermissions[$roleId];
            $usedRoleKey = $roleId;
        } 
        // Second try: role_code from cache
        elseif ($roleCode && isset($this->rolePermissions[$roleCode])) {
            $rolePerms = $this->rolePermissions[$roleCode];
            $usedRoleKey = $roleCode;
            
            // Also cache by role_id if we have it
            if ($roleId) {
                $this->rolePermissions[$roleId] = $rolePerms;
            }
        } 
        // Third try: load from database
        else {
            // Try to load from database if available
            if ($this->roleService && $roleId) {
                try {
                    $rolePerms = $this->roleService->getRolePermissionKeys($roleId);
                    if (!empty($rolePerms)) {
                        // Cache it by both role_id and role_code
                        $this->rolePermissions[$roleId] = $rolePerms;
                        if ($roleCode) {
                            $this->rolePermissions[$roleCode] = $rolePerms;
                        }
                        $usedRoleKey = $roleId;
                    }
                } catch (\Exception $e) {
                    // Fall through to fallback
                }
            }
            
            // If still not found and we have role_code but not role_id, try loading by role_code
            if (empty($rolePerms) && $roleCode && !$roleId && $this->roleService) {
                try {
                    $roleData = $this->roleService->getByRoleCode($roleCode);
                    if ($roleData && isset($roleData['role_id'])) {
                        $roleId = $roleData['role_id'];
                        $rolePerms = $this->roleService->getRolePermissionKeys($roleId);
                        if (!empty($rolePerms)) {
                            // Cache it by both role_id and role_code
                            $this->rolePermissions[$roleId] = $rolePerms;
                            $this->rolePermissions[$roleCode] = $rolePerms;
                            // Update session with role_id
                            SessionManager::set('role_id', $roleId);
                            $usedRoleKey = $roleId;
                        }
                    }
                } catch (\Exception $e) {
                    // Fall through to fallback
                }
            }
            
            // If still not found, try fallback from config
            if (empty($rolePerms)) {
                $permissionsFile = __DIR__ . '/../config/permissions.php';
                if (file_exists($permissionsFile)) {
                    $config = include $permissionsFile;
                    $fallbackRolePermissions = $config['role_permissions'] ?? [];
                    
                    // Try role_code first
                    if ($roleCode && isset($fallbackRolePermissions[$roleCode])) {
                        $rolePerms = $fallbackRolePermissions[$roleCode];
                        $usedRoleKey = $roleCode;
                    } else {
                        // Try to find by role_id mapping (dynamic)
                        if ($roleId && $this->roleService) {
                            try {
                                $roleData = $this->roleService->getByRoleId($roleId);
                                if ($roleData && !empty($roleData['role_code'])) {
                                    $mappedRoleCode = strtoupper($roleData['role_code']);
                                    if (isset($fallbackRolePermissions[$mappedRoleCode])) {
                                        $rolePerms = $fallbackRolePermissions[$mappedRoleCode];
                                        $usedRoleKey = $roleId;
                                    }
                                }
                            } catch (\Exception $e) {
                                // Fall through
                            }
                        }
                    }
                    
                    // Cache if found
                    if (!empty($rolePerms)) {
                        if ($roleId) {
                            $this->rolePermissions[$roleId] = $rolePerms;
                        }
                        if ($roleCode) {
                            $this->rolePermissions[$roleCode] = $rolePerms;
                        }
                    }
                }
            }
        }
        
        // Ensure it's an array
        if (!is_array($rolePerms)) {
            $rolePerms = [];
        }
        
        // If still empty, log warning
        if (empty($rolePerms)) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::warning("Authorization: Role permissions not found", [
                    'permission' => $permission,
                    'current_role' => $currentRole,
                    'role_id' => $roleId,
                    'role_code' => $roleCode,
                    'available_roles' => array_keys($this->rolePermissions),
                    'session_role' => SessionManager::get('role'),
                    'session_role_id' => SessionManager::get('role_id'),
                    'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown'
                ]);
            }
        }

        // Check if role has this permission
        $hasPermission = in_array($permission, $rolePerms);

        // Always log the result for debugging
        $roleDisplay = $usedRoleKey ?? ($roleId ?? $roleCode ?? $currentRole ?? 'unknown');
        if ($hasPermission) {
            $logMsg = "Authorization: Permission granted - permission: {$permission}, role: {$roleDisplay}, uri: " . ($_SERVER['REQUEST_URI'] ?? 'unknown');
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::debug($logMsg, [
                    'permission' => $permission,
                    'role_id' => $roleId,
                    'role_code' => $roleCode,
                    'role_display' => $roleDisplay,
                    'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown'
                ]);
            } else {
                \App\Core\Logger::debug($logMsg);
            }
        } else {
            $logMsg = "Authorization: Permission denied - permission: {$permission}, role: {$roleDisplay}, available_permissions: " . implode(', ', $rolePerms) . ", uri: " . ($_SERVER['REQUEST_URI'] ?? 'unknown');
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::warning("Authorization: Permission denied", [
                    'permission' => $permission,
                    'role_id' => $roleId,
                    'role_code' => $roleCode,
                    'role_display' => $roleDisplay,
                    'role_permissions' => $rolePerms,
                    'role_permissions_count' => count($rolePerms),
                    'permission_in_array' => in_array($permission, $rolePerms),
                    'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown'
                ]);
            } else {
                \App\Core\Logger::debug($logMsg);
            }
        }

        return $hasPermission;
    }
    
    /**
     * Check if user has any of the specified permissions
     */
    public function hasAnyPermission($permissions) {
        if (!is_array($permissions)) {
            $permissions = [$permissions];
        }
        
        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if user has all of the specified permissions
     */
    /**
     * Check if user has all permissions (delegated to PermissionChecker)
     */
    public function hasAllPermissions($permissions) {
        if (!is_array($permissions)) {
            $permissions = [$permissions];
        }
        $roleId = $this->roleManager->getCurrentRoleId();
        $roleCode = SessionManager::get('role');
        return $this->permissionChecker->hasAllPermissions($permissions, $roleId, $roleCode);
    }
    
    /**
     * Old hasAllPermissions implementation (kept for reference)
     */
    private function hasAllPermissionsOld($permissions) {
        if (!is_array($permissions)) {
            $permissions = [$permissions];
        }
        
        foreach ($permissions as $permission) {
            if (!$this->hasPermission($permission)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Require user to be logged in
     */
    /**
     * Require login (delegated to SessionValidator)
     */
    public function requireLogin($redirect = true) {
        return $this->sessionValidator->requireLogin($redirect);
    }
    
    /**
     * Old requireLogin implementation (kept for reference)
     */
    private function requireLoginOld($redirect = true) {
        SessionManager::ensureSession();
        if (!$this->isLoggedIn()) {
            if ($redirect) {
                SessionManager::set('redirect_to', $_SERVER['REQUEST_URI'] ?? '/');
                // CRITICAL: Use current host (with subdomain) for redirect to preserve subdomain
                $currentHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
                $protocol = getProtocol();
                $loginUrl = $protocol . '://' . $currentHost . '/login';
                header('Location: ' . $loginUrl);
                exit;
            }
            return false;
        }
        return true;
    }
    
    /**
     * Require user to have specific role
     */
    /**
     * Require role (delegated to RoleManager + SessionValidator)
     */
    public function requireRole($role, $redirect = true) {
        $roleId = $this->roleManager->getCurrentRoleId();
        $roleCode = SessionManager::get('role');
        if ($this->roleManager->hasRole($role, $roleId, $roleCode)) {
            return true;
        }
        
        if ($redirect) {
            if (!defined('BASE_URL')) {
                throw new \Exception('BASE_URL constant is not defined. Please check your configuration.');
            }
            $baseUrl = BASE_URL;
            header('Location: ' . $baseUrl . '/unauthorized');
            exit;
        }
        return false;
    }
    
    /**
     * Old requireRole implementation (kept for reference)
     */
    private function requireRoleOld($role, $redirect = true) {
        if (!$this->hasRole($role)) {
            if ($redirect) {
                // CRITICAL: Use current host (with subdomain) for redirect
                $currentHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
                $protocol = getProtocol();
                header('Location: ' . $protocol . '://' . $currentHost . '/unauthorized');
                exit;
            }
            return false;
        }
        return true;
    }
    
    /**
     * Require user to have any of the specified roles
     */
    /**
     * Require any role (delegated to RoleManager + SessionValidator)
     */
    public function requireAnyRole($roles, $redirect = true) {
        if (!is_array($roles)) {
            $roles = [$roles];
        }
        $roleId = $this->roleManager->getCurrentRoleId();
        $roleCode = SessionManager::get('role');
        if ($this->roleManager->hasAnyRole($roles, $roleId, $roleCode)) {
            return true;
        }
        
        if ($redirect) {
            if (!defined('BASE_URL')) {
                throw new \Exception('BASE_URL constant is not defined. Please check your configuration.');
            }
            $baseUrl = BASE_URL;
            header('Location: ' . $baseUrl . '/unauthorized');
            exit;
        }
        return false;
    }
    
    /**
     * Old requireAnyRole implementation (kept for reference)
     */
    private function requireAnyRoleOld($roles, $redirect = true) {
        if (!$this->hasAnyRole($roles)) {
            if ($redirect) {
                // CRITICAL: Use current host (with subdomain) for redirect
                $currentHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
                $protocol = getProtocol();
                header('Location: ' . $protocol . '://' . $currentHost . '/unauthorized');
                exit;
            }
            return false;
        }
        return true;
    }
    
    /**
     * Require user to have specific permission
     */
    /**
     * Require permission (delegated to hasPermission which includes Manager bypass)
     */
    public function requirePermission($permission, $redirect = true) {
        // Use hasPermission instead of PermissionChecker directly to get Manager bypass
        if ($this->hasPermission($permission)) {
            return true;
        }
        
        if ($redirect) {
            // Check if this is an API request
            $uri = $_SERVER['REQUEST_URI'] ?? '';
            $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
            $acceptHeader = $_SERVER['HTTP_ACCEPT'] ?? '';
            $requestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
            
            $isApiRequest = (
                strpos($uri, '/api/') !== false ||
                // Shift routes removed
                // ((strpos($uri, '/qodmin/shifts/') !== false || strpos($uri, '/business/shifts/') !== false) && $_SERVER['REQUEST_METHOD'] === 'POST') ||
                strpos($contentType, 'application/json') !== false ||
                strpos($acceptHeader, 'application/json') !== false ||
                strtolower($requestedWith) === 'xmlhttprequest'
            );
            
            if ($isApiRequest) {
                // Return JSON response for API requests
                \App\Core\ApiResponseHelper::error('Yetkilendirme hatası', 401, 'UNAUTHORIZED');
            }
            
            // For view requests, redirect to unauthorized page
            if (!defined('BASE_URL')) {
                throw new \Exception('BASE_URL constant is not defined. Please check your configuration.');
            }
            $baseUrl = BASE_URL;
            header('Location: ' . $baseUrl . '/unauthorized');
            exit;
        }
        return false;
    }
    
    /**
     * Old requirePermission implementation (kept for reference)
     */
    private function requirePermissionOld($permission, $redirect = true) {
        // Ensure Logger is loaded
        if (!class_exists('\App\Core\Logger')) {
            // Logger is autoloaded
        }
        
        // Log permission requirement
        $logMsg = "Authorization: requirePermission called - permission: {$permission}, redirect: " . ($redirect ? 'true' : 'false');
        if (class_exists('\App\Core\Logger')) {
            \App\Core\Logger::debug($logMsg, [
                'permission' => $permission,
                'redirect' => $redirect,
                'request_uri' => $_SERVER['REQUEST_URI'] ?? ''
            ]);
        } else {
            error_log($logMsg);
        }
        
        if (!$this->hasPermission($permission)) {
            // Log detailed information for debugging
            $logData = [
                'permission' => $permission,
                'current_role' => $this->getCurrentRole(),
                'session_role' => SessionManager::get('role'),
                'session_username' => SessionManager::get('username'), // Add username to logs
                'is_logged_in' => $this->isLoggedIn(),
                'user_id' => $this->getCurrentUserId(),
                'available_roles' => array_keys($this->rolePermissions),
                'role_permissions_count' => count($this->rolePermissions),
                'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
                'request_method' => $_SERVER['REQUEST_METHOD'] ?? '',
                'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'session_data' => [
                    'user_id' => SessionManager::get('user_id'),
                    'username' => SessionManager::get('username'),
                    'role' => SessionManager::get('role'),
                    'logged_in' => SessionManager::get('logged_in'),
                    'login_time' => SessionManager::get('login_time')
                ]
            ];

            $logMsg = "Authorization: Permission required but denied - permission: {$permission}, username: " . (SessionManager::get('username') ?? 'unknown') . ", role: " . ($this->getCurrentRole() ?? 'none') . ", uri: " . ($_SERVER['REQUEST_URI'] ?? 'unknown');
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::warning($logMsg, $logData);
            } else {
                \App\Core\Logger::debug($logMsg);
            }

            // Flush output buffer to ensure logs are written before redirect
            if (ob_get_level() > 0) {
                ob_end_flush();
            }

            if ($redirect) {
                // CRITICAL: Use current host (with subdomain) for redirect
                $currentHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
                $protocol = getProtocol();
                header('Location: ' . $protocol . '://' . $currentHost . '/unauthorized');
                exit;
            }
            return false;
        }
        
        // Log successful permission check
        $logMsg = "Authorization: Permission granted - permission: {$permission}";
        if (class_exists('\App\Core\Logger')) {
            \App\Core\Logger::debug($logMsg, ['permission' => $permission]);
        } else {
            error_log($logMsg);
        }
        
        return true;
    }
    
    /**
     * Require user to have any of the specified permissions
     */
    /**
     * Require any permission (delegated to hasAnyPermission which includes Manager bypass)
     */
    public function requireAnyPermission($permissions, $redirect = true) {
        // Use hasAnyPermission instead of PermissionChecker directly to get Manager bypass
        if ($this->hasAnyPermission($permissions)) {
            return true;
        }
        
        if ($redirect) {
            if (!defined('BASE_URL')) {
                throw new \Exception('BASE_URL constant is not defined. Please check your configuration.');
            }
            $baseUrl = BASE_URL;
            header('Location: ' . $baseUrl . '/unauthorized');
            exit;
        }
        return false;
    }
    
    /**
     * Old requireAnyPermission implementation (kept for reference)
     */
    private function requireAnyPermissionOld($permissions, $redirect = true) {
        if (!$this->hasAnyPermission($permissions)) {
            if ($redirect) {
                // CRITICAL: Use current host (with subdomain) for redirect
                $currentHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
                $protocol = getProtocol();
                header('Location: ' . $protocol . '://' . $currentHost . '/unauthorized');
                exit;
            }
            return false;
        }
        return true;
    }
    
    /**
     * Check if user can access a route/action
     */
    /**
     * Check if user can access route (delegated to PermissionChecker)
     */
    public function canAccess($route, $method = 'GET') {
        $permission = $this->permissionChecker->getPermissionForRoute($route, $method);
        if (!$permission) {
            return true; // No permission required for this route
        }
        $roleId = $this->roleManager->getCurrentRoleId();
        $roleCode = SessionManager::get('role');
        return $this->permissionChecker->hasPermission($permission, $roleId, $roleCode);
    }
    
    /**
     * Old canAccess implementation (kept for reference)
     */
    private function canAccessOld($route, $method = 'GET') {
        // Get permission for this route
        $permission = $this->getPermissionForRoute($route, $method);
        
        if ($permission === null) {
            // No permission required, allow access
            return true;
        }
        
        return $this->hasPermission($permission);
    }
    
    /**
     * Get permission name for a route
     */
    /**
     * Get permission for route (delegated to PermissionChecker)
     */
    private function getPermissionForRoute($route, $method = 'GET') {
        return $this->permissionChecker->getPermissionForRoute($route, $method);
    }
    
    /**
     * Old getPermissionForRoute implementation (kept for reference)
     */
    private function getPermissionForRouteOld($route, $method = 'GET') {
        // This can be extended to map routes to permissions
        // For now, return null (no permission required)
        return null;
    }
    
    /**
     * Get all permissions for current user's role
     */
    /**
     * Get user permissions (delegated to PermissionChecker)
     */
    public function getUserPermissions() {
        $roleId = $this->roleManager->getCurrentRoleId();
        $roleCode = SessionManager::get('role');
        return $this->permissionChecker->getUserPermissions($roleId, $roleCode);
    }
    
    /**
     * Old getUserPermissions implementation (kept for reference)
     */
    private function getUserPermissionsOld() {
        $currentRole = $this->getCurrentRole();
        if (!$currentRole) {
            return [];
        }
        
        // Normalize role to uppercase
        $normalizedRole = strtoupper($currentRole);
        return $this->rolePermissions[$normalizedRole] ?? [];
    }
    
    /**
     * Get all available permissions
     */
    public function getAllPermissions() {
        return $this->permissions;
    }
    
    /**
     * Get all roles
     */
    public function getAllRoles() {
        return array_keys($this->rolePermissions);
    }
}


