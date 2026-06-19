<?php
namespace App\Controllers;

require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../core/DependencyFactory.php';

use App\Core\Controller;

/**
 * SuperAdmin Controller
 * Handles super admin operations including cross-business data access
 */
class SuperAdminController extends Controller {
    
    protected $menuItemService;
    protected $categoryService;
    protected $tableService;
    protected $userService;
    protected $orderService;
    protected $zoneService;
    
    public function __construct() {
        parent::__construct();
        $this->menuItemService = \App\Core\DependencyFactory::getMenuItemService();
        $this->categoryService = \App\Core\DependencyFactory::getCategoryService();
        $this->tableService = \App\Core\DependencyFactory::getTableService();
        $this->userService = \App\Core\DependencyFactory::getUserService();
        $this->orderService = \App\Core\DependencyFactory::getOrderService();
        $this->zoneService = \App\Core\DependencyFactory::getZoneService();
    }
    
    /**
     * Require super admin role
     */
    protected function requireSuperAdmin() {
        if (!$this->isSuperAdmin()) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 403);
            exit;
        }
    }
    
    /**
     * Get all businesses with statistics
     * Updated to use CustomerService for proper business listing
     */
    public function getBusinesses() {
        $this->requireSuperAdmin();
        
        try {
            // Use CustomerService to get all businesses (filters out super admin users)
            $customerService = \App\Core\DependencyFactory::getCustomerService();
            $customers = $customerService->getAllWithSubscriptions();
            
            // Log for debugging
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::info('SuperAdminController::getBusinesses - Retrieved customers', [
                    'count' => count($customers),
                    'customer_ids' => array_column($customers, 'customer_id')
                ]);
            }
            
            // Format businesses for frontend
            $businesses = [];
            foreach ($customers as $customer) {
                $businessId = $customer['customer_id'];
                $businessName = $customer['company_name'] ?? $customer['business_name'] ?? ($customer['first_name'] . ' ' . $customer['last_name']);
                
                $businesses[] = [
                    'business_id' => $businessId,
                    'id' => $businessId,
                    'business_name' => trim($businessName),
                    'company_name' => $customer['company_name'] ?? trim($businessName),
                    'name' => trim($businessName),
                    'email' => $customer['email'] ?? '',
                    'subdomain' => $customer['subdomain'] ?? '',
                    'logo_path' => $customer['logo_path'] ?? null,
                    'logo_url' => $customer['logo_url'] ?? null,
                    'status' => ($customer['is_active'] ?? 1) ? 'active' : 'inactive',
                    'is_active' => $customer['is_active'] ?? 1,
                    'package_name' => $customer['package_name'] ?? 'Standart',
                    'subscription_id' => $customer['subscription_id'] ?? null,
                    'owner_name' => ($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? ''),
                    'location' => $customer['city'] ?? $customer['location'] ?? '',
                    // Basic stats (can be enhanced later)
                    'total_revenue' => 0,
                    'total_orders' => 0,
                    'total_tables' => 0,
                    'total_staff' => 0
                ];
            }
            
            $this->apiResponse([
                'success' => true,
                'businesses' => $businesses,
                'total' => count($businesses)
            ]);
        } catch (\Exception $e) {
            \App\Core\Logger::error('SuperAdmin getBusinesses error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            // Return empty array instead of error to prevent frontend issues
            $this->apiResponse([
                'success' => true,
                'businesses' => [],
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Execute all migrations and sync scripts
     * GET /qodmin/migrations/execute-all
     */
    public function executeAllMigrations() {
        try {
            $this->requireSuperAdmin();
        } catch (\Exception $e) {
            http_response_code(403);
            die('Unauthorized: ' . $e->getMessage());
        }
        
        // Set content type for plain text output
        header('Content-Type: text/plain; charset=utf-8');
        
        // Change to project root
        $projectRoot = dirname(dirname(__DIR__));
        chdir($projectRoot);
        
        // Load environment variables
        $envFile = $projectRoot . '/.env';
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                if (strpos($line, '#') === 0) continue;
                if (strpos($line, '=') === false) continue;
                list($name, $value) = explode('=', $line, 2);
                $name = trim($name);
                $value = trim($value);
                if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
                    (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
                    $value = substr($value, 1, -1);
                }
                $_ENV[$name] = $value;
                putenv("$name=$value");
            }
        }
        
        // Execute migrations directly
        try {
            $db = \App\Core\DependencyFactory::getDatabase();
        } catch (\Exception $e) {
            echo "❌ Database connection failed: " . $e->getMessage() . "\n";
            echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
            exit(1);
        }
        $results = [];
        
        // Migration 1: Add business fields to customers
        echo "📋 Migration 2: Add business fields to customers table\n";
        echo str_repeat('=', 60) . "\n";
        try {
            $checkTable = $db->query("SHOW TABLES LIKE 'customers'")->fetchColumn();
            if (!$checkTable) {
                echo "❌ customers table doesn't exist\n";
                $results[] = ['migration' => '20260111_add_business_fields_to_customers', 'status' => 'error'];
            } else {
                $columnNames = \App\Core\DbSchema::columns('customers');
                $added = [];
                
                if (!in_array('company_name', $columnNames)) {
                    $db->exec("ALTER TABLE customers ADD COLUMN company_name VARCHAR(255) DEFAULT NULL AFTER last_name");
                    echo "✅ Added 'company_name' column\n";
                    $added[] = 'company_name';
                } else {
                    echo "✓ Column 'company_name' already exists\n";
                }
                
                if (!in_array('logo_path', $columnNames)) {
                    $db->exec("ALTER TABLE customers ADD COLUMN logo_path VARCHAR(255) DEFAULT NULL AFTER company_name");
                    echo "✅ Added 'logo_path' column\n";
                    $added[] = 'logo_path';
                } else {
                    echo "✓ Column 'logo_path' already exists\n";
                }
                
                if (!in_array('is_active', $columnNames)) {
                    $db->exec("ALTER TABLE customers ADD COLUMN is_active TINYINT(1) DEFAULT 1 AFTER logo_path");
                    echo "✅ Added 'is_active' column\n";
                    $added[] = 'is_active';
                } else {
                    echo "✓ Column 'is_active' already exists\n";
                }
                
                $results[] = ['migration' => '20260111_add_business_fields_to_customers', 'status' => !empty($added) ? 'success' : 'skipped', 'added' => $added];
                echo "✅ Business fields migration completed!\n";
            }
        } catch (\Exception $e) {
            echo "❌ Error: " . $e->getMessage() . "\n";
            $results[] = ['migration' => '20260111_add_business_fields_to_customers', 'status' => 'error', 'error' => $e->getMessage()];
        }
        echo "\n";
        
        // Summary
        echo "\n📊 SUMMARY\n";
        echo str_repeat('=', 60) . "\n";
        foreach ($results as $result) {
            $name = $result['migration'] ?? $result['script'] ?? 'unknown';
            $status = $result['status'] ?? 'unknown';
            $icon = $status === 'success' ? '✅' : ($status === 'error' ? '❌' : '⏭️');
            echo "{$icon} {$name}: {$status}\n";
            if (isset($result['error'])) {
                echo "   Error: {$result['error']}\n";
            }
        }
        
        echo "\n✅ ALL OPERATIONS COMPLETED!\n";
        exit;
    }
    
    /**
     * Debug endpoint to check business data in database
     * GET /api/qodmin/businesses/{id}/debug
     */
    public function debugBusiness($id = null) {
        $this->requireSuperAdmin();
        
        $businessId = $id ?? $_GET['id'] ?? '';
        if (empty($businessId)) {
            $this->apiResponse([
                'success' => false,
                'error' => 'Business ID required'
            ], 400);
            return;
        }
        
        $db = \App\Core\DependencyFactory::getDatabase();
        $data = [
            'business_id' => $businessId,
            'customers' => null,
            'users' => null,
            'problem' => null
        ];
        
        // Check customers
        try {
            $stmt = $db->prepare("SELECT customer_id, company_name, first_name, last_name, email, logo_path, logo_url FROM customers WHERE customer_id = ? LIMIT 1");
            $stmt->execute([$businessId]);
            $data['customers'] = $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
        } catch (\Exception $e) {
            $data['customers'] = ['error' => $e->getMessage()];
        }
        
        // Check users
        try {
            $stmt = $db->prepare("SELECT user_id, first_name, last_name, email, role FROM users WHERE tenant_id = ? LIMIT 5");
            $stmt->execute([$businessId]);
            $data['users'] = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {
            $data['users'] = ['error' => $e->getMessage()];
        }
        
        // Determine problem
        $hasBusinessName = false;
        if (!empty($data['customers']['company_name'])) {
            $hasBusinessName = true;
        } elseif (!empty($data['customers']['first_name']) || !empty($data['customers']['last_name'])) {
            $hasBusinessName = true;
        } elseif (!empty($data['customers']['email'])) {
            $hasBusinessName = true;
        } elseif (!empty($data['users'])) {
            foreach ($data['users'] as $user) {
                if (!empty($user['first_name']) || !empty($user['last_name']) || !empty($user['email'])) {
                    $hasBusinessName = true;
                    break;
                }
            }
        }
        
        if (!$hasBusinessName) {
            $data['problem'] = 'No business name found in any table. All fields are empty or NULL.';
        }
        
        $this->apiResponse([
            'success' => true,
            'data' => $data
        ]);
    }
    
    /**
     * Get statistics for a specific business
     * Uses BusinessService for centralized business info (single source: customers table)
     */
    private function getBusinessStats($businessId) {
        $db = \App\Core\DependencyFactory::getDatabase();
        
        // Initialize defaults
        $companyName = $businessId;
        $logoPath = '';
        $logoUrl = '';
        $email = '';
        $firstName = '';
        $lastName = '';
        $ownerName = '';
        $location = '';
        
        // Get business info from BusinessService (single source: customers table)
        try {
            $businessService = \App\Core\DependencyFactory::getBusinessService();
            $businessInfo = $businessService->getBusinessInfo($businessId);
            
            // Extract business info
            $companyName = $businessInfo['company_name'] ?? $businessId;
            $logoPath = $businessInfo['logo_path'] ?? '';
            $logoUrl = $businessInfo['logo_url'] ?? $logoPath;
            $email = $businessInfo['email'] ?? '';
            $firstName = $businessInfo['first_name'] ?? '';
            $lastName = $businessInfo['last_name'] ?? '';
            $ownerName = trim($firstName . ' ' . $lastName);
            $location = $businessInfo['city'] ?? '';
        } catch (\Exception $e) {
            // Log error but continue with defaults
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('SuperAdminController::getBusinessStats - Error getting business info', [
                    'business_id' => $businessId,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        // Additional stats
        $packageName = 'Standart';
        $status = 'active';
        
        $stats = [
            'business_id' => $businessId,
            'business_name' => $companyName,
            'company_name' => $companyName, // For compatibility
            'name' => $companyName, // For compatibility
            'total_orders' => 0,
            'total_revenue' => 0,
            'total_tables' => 0,
            'total_staff' => 0,
            'owner_name' => $ownerName,
            'owner' => $ownerName, // For compatibility
            'email' => $email,
            'business_email' => $email, // For compatibility
            'logo_path' => $logoPath,
            'logo_url' => $logoUrl,
            'location' => $location,
            'city' => $location, // For compatibility
            'package_name' => $packageName,
            'package' => $packageName, // For compatibility
            'status' => $status
        ];
        
        try {
            // Get order count safely
            try {
                $checkTable = $db->query("SHOW TABLES LIKE 'orders'");
                if ($checkTable->rowCount() > 0) {
                    $orderCountStmt = $db->prepare("SELECT COUNT(*) FROM orders WHERE tenant_id = :bid");
                    $orderCountStmt->execute(['bid' => $businessId]);
                    $stats['total_orders'] = (int)$orderCountStmt->fetchColumn();
                    
                    $revenueStmt = $db->prepare("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE tenant_id = :bid AND status = 'SERVED'");
                    $revenueStmt->execute(['bid' => $businessId]);
                    $stats['total_revenue'] = (float)$revenueStmt->fetchColumn();
                }
            } catch (\Exception $e) {
                // Orders table doesn't exist or error, continue
            }
            
            // Get table count safely
            try {
                $checkTable = $db->query("SHOW TABLES LIKE 'tables'");
                if ($checkTable->rowCount() > 0) {
                    // tables table uses tenant_id, not business_id
                    $tableCountStmt = $db->prepare("SELECT COUNT(*) FROM tables WHERE tenant_id = :bid");
                    $tableCountStmt->execute(['bid' => $businessId]);
                    $stats['total_tables'] = (int)$tableCountStmt->fetchColumn();
                }
            } catch (\Exception $e) {
                // Tables table doesn't exist or error, continue
            }
            
            // Get staff count safely
            try {
                $checkTable = $db->query("SHOW TABLES LIKE 'users'");
                if ($checkTable->rowCount() > 0) {
                    $staffCountStmt = $db->prepare("SELECT COUNT(*) FROM users WHERE tenant_id = :bid");
                    $staffCountStmt->execute(['bid' => $businessId]);
                    $stats['total_staff'] = (int)$staffCountStmt->fetchColumn();
                }
            } catch (\Exception $e) {
                // Users table doesn't exist or error, continue
            }
            
            return $stats;
        } catch (\Exception $e) {
            \App\Core\Logger::error('getBusinessStats error for ' . $businessId . ': ' . $e->getMessage());
            return $stats; // Return default stats
        }
    }
    
    /**
     * Get menu items for a specific business
     */
    public function getBusinessMenuItems($businessId = null) {
        $this->requireSuperAdmin();
        
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $businessId = $businessId ?? $queryParams['business_id'] ?? $queryParams['id'] ?? '';
        
        if (empty($businessId)) {
            $this->toastNotificationService->sendApiResponse('error', 'Business ID required', [], 400);
            return;
        }
        
        try {
            $db = \App\Core\DependencyFactory::getDatabase();
            // IMPORTANT: menu_items table uses tenant_id, not business_id
            $sql = "SELECT * FROM menu_items WHERE tenant_id = :business_id ORDER BY created_at DESC";
            $stmt = $db->prepare($sql);
            $stmt->execute(['business_id' => $businessId]);
            $menuItems = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            $this->apiResponse([
                'success' => true,
                'menu_items' => $menuItems
            ]);
        } catch (\Exception $e) {
            \App\Core\Logger::error('getBusinessMenuItems error: ' . $e->getMessage());
            $this->apiResponse([
                'success' => false,
                'message' => 'Failed to load menu items',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get categories for a specific business
     */
    public function getBusinessCategories($businessId = null) {
        $this->requireSuperAdmin();
        
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $businessId = $businessId ?? $queryParams['business_id'] ?? $queryParams['id'] ?? '';
        
        if (empty($businessId)) {
            $this->apiResponse([
                'success' => false,
                'error' => 'Business ID required'
            ], 400);
            return;
        }
        
        try {
            $db = \App\Core\DependencyFactory::getDatabase();
            // IMPORTANT: categories table uses tenant_id, not business_id
            $sql = "SELECT * FROM categories WHERE tenant_id = :business_id ORDER BY display_order, name";
            $stmt = $db->prepare($sql);
            $stmt->execute(['business_id' => $businessId]);
            $categories = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            $this->apiResponse([
                'success' => true,
                'categories' => $categories
            ]);
        } catch (\Exception $e) {
            \App\Core\Logger::error('getBusinessCategories error: ' . $e->getMessage(), [
                'business_id' => $businessId,
                'trace' => $e->getTraceAsString()
            ]);
            $this->apiResponse([
                'success' => false,
                'error' => 'Failed to load categories',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get tables for a specific business
     */
    public function getBusinessTables($businessId = null) {
        $this->requireSuperAdmin();
        
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $businessId = $businessId ?? $queryParams['business_id'] ?? $queryParams['id'] ?? '';
        
        if (empty($businessId)) {
            $this->toastNotificationService->sendApiResponse('error', 'Business ID required', [], 400);
            return;
        }
        
        try {
            $db = \App\Core\DependencyFactory::getDatabase();
            // IMPORTANT: tables table uses tenant_id, not business_id
            $sql = "SELECT t.*, z.name as zone_name 
                    FROM tables t 
                    LEFT JOIN zones z ON t.zone_id = z.zone_id
                    WHERE t.tenant_id = :business_id 
                    ORDER BY z.name, t.name";
            $stmt = $db->prepare($sql);
            $stmt->execute(['business_id' => $businessId]);
            $tables = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            $this->apiResponse([
                'success' => true,
                'tables' => $tables
            ]);
        } catch (\Exception $e) {
            \App\Core\Logger::error('getBusinessTables error: ' . $e->getMessage());
            $this->apiResponse([
                'success' => false,
                'message' => 'Failed to load tables',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get staff/users for a specific business
     */
    public function getBusinessStaff($businessId = null) {
        $this->requireSuperAdmin();
        
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $businessId = $businessId ?? $queryParams['business_id'] ?? $queryParams['id'] ?? '';
        
        if (empty($businessId)) {
            $this->toastNotificationService->sendApiResponse('error', 'Business ID required', [], 400);
            return;
        }
        
        try {
            $db = \App\Core\DependencyFactory::getDatabase();
            $sql = "SELECT user_id, name, role, pin, created_at 
                    FROM users 
                    WHERE tenant_id = :business_id 
                    ORDER BY created_at DESC";
            $stmt = $db->prepare($sql);
            $stmt->execute(['business_id' => $businessId]);
            $staff = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            // Add role labels to staff data
            $roleService = \App\Core\DependencyFactory::getRoleService();
            $allRoles = $roleService->getActiveRoles();
            $roleLabelMap = [];
            foreach ($allRoles as $role) {
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
            require_once __DIR__ . '/../helpers/role_helpers.php';
            
            // Add role_label to each staff member
            $staff = array_map(function($member) use ($roleLabelMap, $currentLang) {
                $roleCode = strtoupper(trim($member['role'] ?? 'WAITER'));
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
                
                $member['role_label'] = $roleLabel ?: ($member['role'] ?? 'WAITER');
                return $member;
            }, $staff);
            
            $this->apiResponse([
                'success' => true,
                'staff' => $staff
            ]);
        } catch (\Exception $e) {
            \App\Core\Logger::error('getBusinessStaff error: ' . $e->getMessage());
            $this->toastNotificationService->sendApiResponse('error', 'Failed to load staff', [], 500);
        }
    }
    
    /**
     * Get orders for a specific business
     */
    public function getBusinessOrders($businessId = null) {
        $this->requireSuperAdmin();
        
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $businessId = $businessId ?? $queryParams['business_id'] ?? $queryParams['id'] ?? '';
        
        if (empty($businessId)) {
            $this->toastNotificationService->sendApiResponse('error', 'Business ID required', [], 400);
            return;
        }
        
        try {
            $db = \App\Core\DependencyFactory::getDatabase();
            // orders table uses tenant_id, not business_id
            $sql = "SELECT o.*, t.name as table_name 
                    FROM orders o 
                    LEFT JOIN tables t ON o.table_id = t.table_id
                    WHERE o.tenant_id = :business_id 
                    ORDER BY o.created_at DESC 
                    LIMIT 100";
            $stmt = $db->prepare($sql);
            $stmt->execute(['business_id' => $businessId]);
            $orders = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            $this->apiResponse([
                'success' => true,
                'orders' => $orders
            ]);
        } catch (\Exception $e) {
            \App\Core\Logger::error('getBusinessOrders error: ' . $e->getMessage());
            $this->toastNotificationService->sendApiResponse('error', 'Failed to load orders', [], 500);
        }
    }
    
    /**
     * Get zones for a specific business
     */
    public function getBusinessZones($businessId = null) {
        $this->requireSuperAdmin();
        
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $businessId = $businessId ?? $queryParams['business_id'] ?? $queryParams['id'] ?? '';
        
        if (empty($businessId)) {
            $this->toastNotificationService->sendApiResponse('error', 'Business ID required', [], 400);
            return;
        }
        
        try {
            $db = \App\Core\DependencyFactory::getDatabase();
            
            $hasBusinessId = \App\Core\DbSchema::hasColumn('zones', 'tenant_id');
            
            // zones table doesn't have tenant_id
            // Get zones related to tables of this business
            $sql = "SELECT DISTINCT z.* 
                    FROM zones z 
                    INNER JOIN tables t ON z.zone_id = t.zone_id 
                    WHERE t.tenant_id = :business_id 
                    ORDER BY z.name";
            $stmt = $db->prepare($sql);
            $stmt->execute(['business_id' => $businessId]);
            
            $zones = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            $this->apiResponse([
                'success' => true,
                'zones' => $zones
            ]);
        } catch (\Exception $e) {
            \App\Core\Logger::error('getBusinessZones error: ' . $e->getMessage());
            $this->toastNotificationService->sendApiResponse('error', 'Failed to load zones', [], 500);
        }
    }
    
    /**
     * Get expenses for a specific business
     */
    public function getBusinessExpenses($businessId = null) {
        $this->requireSuperAdmin();
        
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $businessId = $businessId ?? $queryParams['business_id'] ?? $queryParams['id'] ?? '';
        
        if (empty($businessId)) {
            $this->toastNotificationService->sendApiResponse('error', 'Business ID required', [], 400);
            return;
        }
        
        try {
            $db = \App\Core\DependencyFactory::getDatabase();
            
            $hasBusinessId = \App\Core\DbSchema::hasColumn('expenses', 'tenant_id');
            
            if ($hasBusinessId) {
                $sql = "SELECT e.*, u.name as added_by_name, s.name as supplier_name 
                        FROM expenses e 
                        LEFT JOIN users u ON e.added_by = u.user_id 
                        LEFT JOIN suppliers s ON e.supplier_id = s.supplier_id 
                        WHERE e.tenant_id = :business_id 
                        ORDER BY e.date DESC";
                $stmt = $db->prepare($sql);
                $stmt->execute(['business_id' => $businessId]);
            } else {
                // Fallback: return empty array if tenant_id column doesn't exist
                $expenses = [];
                $this->apiResponse([
                    'success' => true,
                    'expenses' => $expenses
                ]);
                return;
            }
            
            $expenses = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            $this->apiResponse([
                'success' => true,
                'expenses' => $expenses
            ]);
        } catch (\Exception $e) {
            \App\Core\Logger::error('getBusinessExpenses error: ' . $e->getMessage());
            $this->toastNotificationService->sendApiResponse('error', 'Failed to load expenses', [], 500);
        }
    }
    
    /**
     * Get invoices for a specific business
     */
    public function getBusinessInvoices($businessId = null) {
        $this->requireSuperAdmin();
        
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $businessId = $businessId ?? $queryParams['business_id'] ?? $queryParams['id'] ?? '';
        
        if (empty($businessId)) {
            $this->toastNotificationService->sendApiResponse('error', 'Business ID required', [], 400);
            return;
        }
        
        try {
            $db = \App\Core\DependencyFactory::getDatabase();
            
            $hasBusinessId = \App\Core\DbSchema::hasColumn('invoices', 'tenant_id');
            
            if ($hasBusinessId) {
                $sql = "SELECT i.*, s.name as supplier_name 
                        FROM invoices i 
                        LEFT JOIN suppliers s ON i.supplier_id = s.supplier_id 
                        WHERE i.tenant_id = :business_id 
                        ORDER BY i.date DESC";
                $stmt = $db->prepare($sql);
                $stmt->execute(['business_id' => $businessId]);
            } else {
                // Fallback: return empty array if tenant_id column doesn't exist
                $invoices = [];
                $this->apiResponse([
                    'success' => true,
                    'invoices' => $invoices
                ]);
                return;
            }
            
            $invoices = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            $this->apiResponse([
                'success' => true,
                'invoices' => $invoices
            ]);
        } catch (\Exception $e) {
            \App\Core\Logger::error('getBusinessInvoices error: ' . $e->getMessage());
            $this->toastNotificationService->sendApiResponse('error', 'Failed to load invoices', [], 500);
        }
    }
    
    /**
     * Get suppliers for a specific business
     */
    public function getBusinessSuppliers($businessId = null) {
        $this->requireSuperAdmin();
        
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $businessId = $businessId ?? $queryParams['business_id'] ?? $queryParams['id'] ?? '';
        
        if (empty($businessId)) {
            $this->toastNotificationService->sendApiResponse('error', 'Business ID required', [], 400);
            return;
        }
        
        try {
            $db = \App\Core\DependencyFactory::getDatabase();
            
            $hasBusinessId = \App\Core\DbSchema::hasColumn('suppliers', 'tenant_id');
            
            // suppliers table doesn't have tenant_id
            // Return empty array or all suppliers (depending on requirements)
            if (false) {
                // This code is kept for reference but won't execute
                $sql = "SELECT * FROM suppliers WHERE tenant_id = :business_id ORDER BY name ASC";
                $stmt = $db->prepare($sql);
                $stmt->execute(['business_id' => $businessId]);
            } else {
                // Fallback: return empty array if tenant_id column doesn't exist
                $suppliers = [];
                $this->apiResponse([
                    'success' => true,
                    'suppliers' => $suppliers
                ]);
                return;
            }
            
            $suppliers = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            $this->apiResponse([
                'success' => true,
                'suppliers' => $suppliers
            ]);
        } catch (\Exception $e) {
            \App\Core\Logger::error('getBusinessSuppliers error: ' . $e->getMessage());
            $this->toastNotificationService->sendApiResponse('error', 'Failed to load suppliers', [], 500);
        }
    }
    
    /**
     * Get waste records for a specific business
     */
    public function getBusinessWaste($businessId = null) {
        $this->requireSuperAdmin();

        $queryParams = \App\Core\RequestParser::getQueryParams();
        $businessId = $businessId ?? $queryParams['business_id'] ?? $queryParams['id'] ?? '';

        if (empty($businessId)) {
            $this->toastNotificationService->sendApiResponse('error', 'Business ID required', [], 400);
            return;
        }

        try {
            $wasteService = \App\Core\DependencyFactory::getWasteRecordService();
            $waste_records = $wasteService->getAllForTenant((string)$businessId);

            $this->apiResponse([
                'success' => true,
                'waste_records' => $waste_records,
            ]);
        } catch (\Throwable $e) {
            \App\Core\Logger::error('getBusinessWaste error: ' . $e->getMessage());
            $this->toastNotificationService->sendApiResponse('error', 'Failed to load waste records', [], 500);
        }
    }
    
    /**
     * Get printers for a specific business
     */
    public function getBusinessPrinters($businessId = null) {
        $this->requireSuperAdmin();
        
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $businessId = $businessId ?? $queryParams['business_id'] ?? $queryParams['id'] ?? '';
        
        if (empty($businessId)) {
            $this->toastNotificationService->sendApiResponse('error', 'Business ID required', [], 400);
            return;
        }
        
        try {
            $db = \App\Core\DependencyFactory::getDatabase();
            
            $hasBusinessId = \App\Core\DbSchema::hasColumn('printers', 'tenant_id');
            
            if ($hasBusinessId) {
                $sql = "SELECT p.*, c.company_name, c.email as business_email 
                        FROM printers p 
                        LEFT JOIN customers c ON p.tenant_id = c.customer_id 
                        WHERE p.tenant_id = :business_id 
                        ORDER BY p.printer_name ASC";
                $stmt = $db->prepare($sql);
                $stmt->execute(['business_id' => $businessId]);
                $printers = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                
                // Enrich printers with bridge information
                try {
                    $printerBridgeService = \App\Core\DependencyFactory::getPrinterBridgeService();
                    $bridges = $printerBridgeService->getBridgesByBusiness($businessId);
                    
                    // Create a map of bridge_id to bridge info
                    $bridgeMap = [];
                    foreach ($bridges as $bridge) {
                        $bridgeMap[$bridge['bridge_id']] = $bridge;
                    }
                    
                    // Add bridge info to printers
                    foreach ($printers as &$printer) {
                        $printer['available_bridges'] = $bridges;
                        if (!empty($bridges)) {
                            // Use first bridge as default (can be improved with better matching logic)
                            $printer['bridge_id'] = $bridges[0]['bridge_id'] ?? null;
                            $printer['bridge_name'] = $bridges[0]['bridge_name'] ?? null;
                        }
                    }
                    unset($printer); // Break reference
                } catch (\Exception $e) {
                    error_log("SuperAdminController::getBusinessPrinters - Error getting bridge info: " . $e->getMessage());
                }
            } else {
                // Fallback: return empty array if tenant_id column doesn't exist
                $printers = [];
            }
            
            $this->apiResponse([
                'success' => true,
                'printers' => $printers
            ]);
        } catch (\Exception $e) {
            \App\Core\Logger::error('getBusinessPrinters error: ' . $e->getMessage());
            $this->toastNotificationService->sendApiResponse('error', 'Failed to load printers', [], 500);
        }
    }
}
