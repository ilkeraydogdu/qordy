<?php
namespace App\Core\Auth;

/**
 * Permission Checker
 * Handles permission-based access control checks
 */
class PermissionChecker {
    private $permissions = [];
    private $rolePermissions = [];
    private $roleService = null;
    private $db = null;
    
    public function __construct($db, $roleService, array $permissions = [], array $rolePermissions = []) {
        $this->db = $db;
        $this->roleService = $roleService;
        $this->permissions = $permissions;
        $this->rolePermissions = $rolePermissions;
    }
    
    /**
     * Check if user is super admin
     * @param string|null $roleId User's role ID
     * @param string|null $roleCode User's role code (fallback)
     * @return bool True if user is super admin
     */
    public function isSuperAdmin(?string $roleId = null, ?string $roleCode = null): bool {
        // Check session first
        require_once __DIR__ . '/../SessionManager.php';
        $sessionRole = \App\Core\SessionManager::get('role');
        $sessionRoleId = \App\Core\SessionManager::get('role_id');
        $isSuperAdminSession = \App\Core\SessionManager::get('is_super_admin') === true;
        
        if ($isSuperAdminSession) {
            return true;
        }
        
        // Check by role code
        if ($sessionRole) {
            $normalizedRole = strtoupper(trim($sessionRole));
            if ($normalizedRole === 'SUPER_ADMIN' || $normalizedRole === 'QODMIN' || 
                $normalizedRole === 'ROLE_SUPER_ADMIN' || $normalizedRole === 'ROLE_QODMIN') {
                return true;
            }
        }
        
        // Check by role_id
        if ($sessionRoleId) {
            $normalizedRoleId = strtoupper(trim($sessionRoleId));
            if ($normalizedRoleId === 'ROLE_SUPER_ADMIN' || $normalizedRoleId === 'ROLE_QODMIN') {
                return true;
            }
        }
        
        // Check parameters
        if ($roleCode) {
            $normalizedRoleCode = strtoupper(trim($roleCode));
            if ($normalizedRoleCode === 'SUPER_ADMIN' || $normalizedRoleCode === 'QODMIN' ||
                $normalizedRoleCode === 'ROLE_SUPER_ADMIN' || $normalizedRoleCode === 'ROLE_QODMIN') {
                return true;
            }
        }
        
        if ($roleId) {
            $normalizedRoleId = strtoupper(trim($roleId));
            if ($normalizedRoleId === 'ROLE_SUPER_ADMIN' || $normalizedRoleId === 'ROLE_QODMIN') {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if user has a specific permission
     * @param string $permission Permission key
     * @param string|null $roleId User's role ID
     * @param string|null $roleCode User's role code (fallback)
     * @return bool True if user has permission
     */
    public function hasPermission(string $permission, ?string $roleId = null, ?string $roleCode = null): bool {
        // Super admin has all permissions
        if ($this->isSuperAdmin($roleId, $roleCode)) {
            return true;
        }
        
        // Get user's role permissions
        $userPermissions = $this->getUserPermissions($roleId, $roleCode);
        return in_array($permission, $userPermissions);
    }
    
    /**
     * Check if user has any of the specified permissions
     * @param array $permissions Array of permission keys
     * @param string|null $roleId User's role ID
     * @param string|null $roleCode User's role code (fallback)
     * @return bool True if user has at least one permission
     */
    public function hasAnyPermission(array $permissions, ?string $roleId = null, ?string $roleCode = null): bool {
        $userPermissions = $this->getUserPermissions($roleId, $roleCode);
        foreach ($permissions as $permission) {
            if (in_array($permission, $userPermissions)) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Check if user has all of the specified permissions
     * @param array $permissions Array of permission keys
     * @param string|null $roleId User's role ID
     * @param string|null $roleCode User's role code (fallback)
     * @return bool True if user has all permissions
     */
    public function hasAllPermissions(array $permissions, ?string $roleId = null, ?string $roleCode = null): bool {
        $userPermissions = $this->getUserPermissions($roleId, $roleCode);
        foreach ($permissions as $permission) {
            if (!in_array($permission, $userPermissions)) {
                return false;
            }
        }
        return true;
    }
    
    /**
     * Get user's permissions based on role
     * @param string|null $roleId User's role ID
     * @param string|null $roleCode User's role code (fallback)
     * @return array Array of permission keys
     */
    public function getUserPermissions(?string $roleId = null, ?string $roleCode = null): array {
        // Try role_id first
        if ($roleId && isset($this->rolePermissions[$roleId])) {
            return $this->rolePermissions[$roleId];
        }
        
        // Fallback to role_code
        if ($roleCode) {
            $normalizedRoleCode = strtoupper(trim($roleCode));
            if (isset($this->rolePermissions[$normalizedRoleCode])) {
                return $this->rolePermissions[$normalizedRoleCode];
            }
        }
        
        return [];
    }
    
    /**
     * Check if customer has package permission
     * @param string $permission Permission key
     * @param string $customerId Customer ID
     * @return bool True if customer has permission through package
     */
    public function hasPackagePermission(string $permission, string $customerId): bool {
        try {
            require_once __DIR__ . '/../../core/DependencyFactory.php';
            $subscriptionService = \App\Core\DependencyFactory::getSubscriptionService();
            
            $subscription = $subscriptionService->getCustomerSubscription($customerId);
            if (!$subscription) {
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::debug('PermissionChecker::hasPackagePermission - No subscription found', [
                        'customer_id' => $customerId,
                        'permission' => $permission
                    ]);
                }
                return false;
            }
            
            if ($subscription['status'] !== 'active') {
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::debug('PermissionChecker::hasPackagePermission - Subscription not active', [
                        'customer_id' => $customerId,
                        'subscription_id' => $subscription['subscription_id'] ?? 'unknown',
                        'subscription_status' => $subscription['status'] ?? 'unknown',
                        'permission' => $permission
                    ]);
                }
                return false;
            }
            
            $packagePermissions = $subscriptionService->getSubscriptionPermissions($subscription['subscription_id']);
            $hasPermission = in_array($permission, $packagePermissions);
            
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::debug('PermissionChecker::hasPackagePermission - Result', [
                    'customer_id' => $customerId,
                    'subscription_id' => $subscription['subscription_id'] ?? 'unknown',
                    'package_id' => $subscription['package_id'] ?? 'unknown',
                    'permission' => $permission,
                    'has_permission' => $hasPermission,
                    'package_permissions_count' => count($packagePermissions),
                    'permission_found' => in_array($permission, $packagePermissions)
                ]);
            }
            
            return $hasPermission;
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('PermissionChecker::hasPackagePermission - Error', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'customer_id' => $customerId,
                    'permission' => $permission
                ]);
            }
            return false;
        }
    }
    
    /**
     * Get customer's all permissions (role + package)
     * @param string $customerId Customer ID
     * @param string|null $roleId User's role ID
     * @param string|null $roleCode User's role code (fallback)
     * @return array Array of permission keys
     */
    public function getCustomerPermissions(string $customerId, ?string $roleId = null, ?string $roleCode = null): array {
        // Get role-based permissions
        $rolePermissions = $this->getUserPermissions($roleId, $roleCode);
        
        // Get package-based permissions
        $packagePermissions = [];
        try {
            require_once __DIR__ . '/../../core/DependencyFactory.php';
            $subscriptionService = \App\Core\DependencyFactory::getSubscriptionService();
            
            $subscription = $subscriptionService->getCustomerSubscription($customerId);
            if ($subscription && $subscription['status'] === 'active') {
                $packagePermissions = $subscriptionService->getSubscriptionPermissions($subscription['subscription_id']);
            }
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::warning('PermissionChecker::getCustomerPermissions - Error', [
                    'error' => $e->getMessage(),
                    'customer_id' => $customerId
                ]);
            }
        }
        
        // Merge and remove duplicates
        return array_unique(array_merge($rolePermissions, $packagePermissions));
    }
    
    /**
     * Get all available permissions
     * @return array Array of permission keys
     */
    public function getAllPermissions(): array {
        return array_keys($this->permissions);
    }
    
    /**
     * Get permission for a route
     * @param string $route Route path
     * @param string $method HTTP method
     * @return string|null Permission key or null
     */
    public function getPermissionForRoute(string $route, string $method = 'GET'): ?string {
        // Route-to-permission mapping (must match system_permissions keys)
        $routeMap = [
            '/admin/orders'          => 'orders.view',
            '/admin/tables'          => 'tables.view',
            '/admin/menu'            => 'menu.view',
            '/admin/users'           => 'staff.view',
            '/admin/staff'           => 'staff.view',
            '/admin/settings'        => 'settings.view',
            '/admin/finance'         => 'finance.view',
            '/admin/inventory'       => 'stock.view',
            '/admin/stock'           => 'stock.view',
            '/admin/reservations'    => 'reservations.view',
            '/admin/reports'         => 'reports.view',
            '/admin/printers'        => 'printers.view',
            '/admin/roles-permissions' => 'roles.view',
            '/business/orders'       => 'orders.view',
            '/business/tables'       => 'tables.view',
            '/business/menu'         => 'menu.view',
            '/business/staff'        => 'staff.view',
            '/business/settings'     => 'settings.view',
            '/business/finance'      => 'finance.view',
            '/business/inventory'    => 'stock.view',
            '/business/reservations' => 'reservations.view',
            '/business/reports'      => 'reports.view',
            '/business/printers'     => 'printers.view',
            '/business/kitchen'      => 'kitchen.view',
            '/business/waiter'       => 'waiter.view',
        ];
        
        return $routeMap[$route] ?? null;
    }
    
    /**
     * Update permissions cache
     * @param array $permissions
     * @param array $rolePermissions
     */
    public function updateCache(array $permissions, array $rolePermissions): void {
        $this->permissions = $permissions;
        $this->rolePermissions = $rolePermissions;
    }
}

