<?php
/**
 * Permissions Configuration
 * Loads permissions from database with fallback to hardcoded values
 */

// Fallback permissions (used if database is empty or fails)
$fallbackPermissions = [
        // Dashboard permissions
        'dashboard.view' => 'View dashboard',
        'dashboard.analytics' => 'View analytics',
        
        // Menu permissions
        'menu.view' => 'View menu',
        'menu.create' => 'Create menu items',
        'menu.edit' => 'Edit menu items',
        'menu.delete' => 'Delete menu items',
        'menu.categories' => 'Manage categories',
        
        // Order permissions
        'orders.view' => 'View orders',
        'orders.create' => 'Create orders',
        'orders.edit' => 'Edit orders',
        'orders.update' => 'Update order status',
        'orders.delete' => 'Delete orders',
        'orders.process' => 'Process orders',
        'orders.complete' => 'Complete orders',
        
        // Table permissions
        'tables.view' => 'View tables',
        'tables.manage' => 'Manage tables',
        'tables.transfer' => 'Transfer tables',
        
        // POS permissions
        'pos.view' => 'View POS',
        'pos.dashboard' => 'Access POS dashboard',
        'pos.process_payment' => 'Process payments',
        'pos.refund' => 'Process refunds',
        
        // Kitchen permissions
        'kitchen.view' => 'View kitchen display',
        'kitchen.dashboard' => 'Access kitchen dashboard',
        'kitchen.update_status' => 'Update order status',
        'kitchen.print' => 'Print kitchen tickets',
        
        // Preparation Screens permissions (admin)
        'preparation-screens.view' => 'View preparation screens',
        'preparation-screens.create' => 'Create preparation screens',
        'preparation-screens.edit' => 'Edit preparation screens',
        'preparation-screens.delete' => 'Delete preparation screens',
        
        // Reservation permissions
        'reservations.view' => 'View reservations',
        'reservations.create' => 'Create reservations',
        'reservations.edit' => 'Edit reservations',
        'reservations.delete' => 'Delete reservations',
        
        // Finance permissions
        'finance.view' => 'View finance',
        'finance.expenses' => 'Manage expenses',
        'finance.invoices' => 'Manage invoices',
        'finance.suppliers' => 'Manage suppliers',
        'finance.waste' => 'Manage waste records',
        'finance.shifts' => 'Manage shifts',
        
        // Staff permissions
        'staff.view' => 'View staff',
        'staff.create' => 'Create staff',
        'staff.edit' => 'Edit staff',
        'staff.delete' => 'Delete staff',
        
        // Settings permissions
        'settings.view' => 'View settings',
        'settings.edit' => 'Edit settings',
        'settings.reset' => 'Reset system',
        
        // Reports permissions
        'reports.view' => 'View reports',
        'reports.export' => 'Export reports',
        
        // Printer permissions
        'printers.view' => 'View printers',
        'printers.create' => 'Create printers',
        'printers.edit' => 'Edit printers',
        'printers.delete' => 'Delete printers',
        'printers.test' => 'Test printer connections',
        
        // Role permissions
        'roles.view' => 'View roles',
        'roles.create' => 'Create roles',
        'roles.edit' => 'Edit roles',
        'roles.delete' => 'Delete roles',
        
        // Permission permissions
        'permissions.view' => 'View permissions',
        'permissions.manage' => 'Manage permissions',
        
        // Receipt permissions
        'receipt.view' => 'View receipts',
        'receipt.print' => 'Print receipts',
        'receipt.void' => 'Void receipts',
        'receipt.refund' => 'Process refunds',
        
        // Table history permissions
        'table.history' => 'View table history',
        
        // Waiter permissions
        'waiter.view' => 'View waiter dashboard',
        'waiter.dashboard' => 'Access waiter dashboard',
        'waiter.manage_tables' => 'Manage tables',
        'waiter.view_notifications' => 'View notifications',
        
        // Stock permissions
        'stock.view' => 'View stock',
        'stock.edit' => 'Edit stock',
        'stock.movements' => 'View stock movements',
        'stock.transfer' => 'Transfer stock',
];

// Minimal fallback role-permission mappings (only for critical failures)
// In normal operation, all permissions are loaded from database
$fallbackRolePermissions = [
        'BUSINESS_MANAGER' => [
            'dashboard.view', 'dashboard.analytics',
            'profile.view', 'profile.edit', 'profile.update',
            'account.view', 'account.edit', 'account.update',
            'packages.view', 'packages.purchase',
            'customer.packages', 'customer.packages.view',
            'subscriptions.view', 'subscriptions.manage',
        ],
        'MANAGER' => [
            'dashboard.view',
            'dashboard.analytics',
            'menu.view',
            'menu.create',
            'menu.edit',
            'menu.delete',
            'menu.categories',
            'orders.view',
            'orders.create',
            'orders.edit',
            'orders.delete',
            'orders.process',
            'orders.complete',
            'tables.view',
            'tables.manage',
            'tables.transfer',
            'table.history',
            'pos.view',
            'pos.process_payment',
            'pos.refund',
            'kitchen.view',
            'kitchen.update_status',
            'preparation-screens.view',
            'preparation-screens.create',
            'preparation-screens.edit',
            'preparation-screens.delete',
            'reservations.view',
            'reservations.create',
            'reservations.edit',
            'reservations.delete',
            'finance.view',
            'finance.expenses',
            'finance.invoices',
            'finance.suppliers',
            'finance.waste',
            'finance.shifts',
            'staff.view',
            'staff.create',
            'staff.edit',
            'staff.delete',
            'settings.view',
            'settings.edit',
            'settings.reset',
            'reports.view',
            'reports.export',
            'printers.view',
            'printers.create',
            'printers.edit',
            'printers.delete',
            'printers.test',
            'roles.view',
            'roles.create',
            'roles.edit',
            'roles.delete',
            'permissions.view',
            'permissions.manage',
            'receipt.view',
            'receipt.print',
            'receipt.void',
            'receipt.refund',
        ],
        'WAITER' => [
            'waiter.view',
            'waiter.dashboard',
            'waiter.manage_tables',
            'waiter.view_notifications',
            'dashboard.view',
            'menu.view',
            'orders.view',
            'orders.create',
            'orders.edit',
            'orders.update',
            'orders.process',
            'orders.complete',
            'tables.view',
            'tables.manage',
            'tables.transfer',
            'table.history',
            'pos.view',
            'pos.process_payment',
            'kitchen.view',
            'reservations.view',
            'reservations.create',
            'reservations.edit',
            'receipt.view',
            'receipt.print',
            'receipt.void',
            'receipt.refund',
        ],
        'KITCHEN' => [
            'kitchen.view',
            'kitchen.dashboard',
            'kitchen.update_status',
            'dashboard.view',
            'orders.view',
            'orders.update',
            'menu.view',
        ],
        'CASHIER' => [
            'pos.view',
            'pos.process_payment',
            'pos.refund',
            'dashboard.view',
            'orders.view',
            'orders.update',
            'orders.complete',
            'tables.view',
            'receipt.view',
            'receipt.print',
        ],
        'CUSTOMER' => [
            'menu.view',
            'orders.create',
        ],
];

// Try to load from database
$permissions = $fallbackPermissions;
$rolePermissions = $fallbackRolePermissions;

try {
    require_once __DIR__ . '/../core/DependencyFactory.php';
    // Use DependencyFactory for DI
    $permissionModel = new \App\Models\SystemPermission(\App\Core\DependencyFactory::getDatabase());
    
    // Load permissions from database
    $dbPermissions = $permissionModel->getPermissionsAsArray();
    if (!empty($dbPermissions) && is_array($dbPermissions)) {
        $permissions = $dbPermissions;
        error_log("Permissions loaded from database: " . count($permissions) . " permissions");
    } else {
        error_log("No permissions found in database, using fallback: " . count($fallbackPermissions) . " permissions");
    }
    
    // Load role-permission mappings from database
    $dbRolePermissions = $permissionModel->getRolePermissionsAsArray();
    if (!empty($dbRolePermissions) && is_array($dbRolePermissions)) {
        // Use database permissions directly (no merging with fallback in normal operation)
        $rolePermissions = $dbRolePermissions;
        error_log("Role permissions loaded from database: " . count($rolePermissions) . " roles");
    } else {
        // Only use fallback if database is completely empty (critical failure)
        error_log("WARNING: No role permissions found in database, using minimal fallback: " . count($fallbackRolePermissions) . " roles");
        $rolePermissions = $fallbackRolePermissions;
    }
} catch (\Exception $e) {
    // Use fallback if database fails
    error_log("Error loading permissions from database: " . $e->getMessage() . " - Using fallback permissions");
}

// Ensure we have valid data structures
if (empty($rolePermissions) || !is_array($rolePermissions)) {
    error_log("WARNING: rolePermissions is empty or invalid, using fallback");
    $rolePermissions = $fallbackRolePermissions;
}

if (empty($permissions) || !is_array($permissions)) {
    error_log("WARNING: permissions is empty or invalid, using fallback");
    $permissions = $fallbackPermissions;
}

return [
    'permissions' => $permissions,
    'role_permissions' => $rolePermissions,
];

