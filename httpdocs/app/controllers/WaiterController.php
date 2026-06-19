<?php
namespace App\Controllers;

require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../core/DependencyFactory.php';
use App\Core\Helpers\ConstantsHelper;
require_once __DIR__ . '/../helpers/translations.php';

class WaiterController extends \App\Core\Controller {
    protected $tableService;
    protected $notificationService;
    protected $orderService;
    protected $zoneService;
    protected $orderItemService;
    protected $activityLogService;
    
    public function __construct() {
        parent::__construct();
        $this->tableService = \App\Core\DependencyFactory::getTableService();
        $this->notificationService = \App\Core\DependencyFactory::getNotificationService();
        $this->orderService = \App\Core\DependencyFactory::getOrderService();
        $this->zoneService = \App\Core\DependencyFactory::getZoneService();
        $this->orderItemService = \App\Core\DependencyFactory::getOrderItemService();
        $this->activityLogService = \App\Core\DependencyFactory::getTableActivityLogService();
    }
    
    /**
     * Garson dashboard sayfası
     */
    public function dashboard() {
        // CRITICAL: Ensure tenant context is set from subdomain BEFORE any other operations
        // This ensures subdomain-based access works correctly after login redirect
        $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
        $subdomain = \App\Core\TenantContext::getSubdomainFromHost($host);
        
        if ($subdomain && !\App\Core\TenantContext::isSet()) {
            try {
                require_once __DIR__ . '/../middleware/TenantMiddleware.php';
                $tenantMiddleware = new \App\Middleware\TenantMiddleware();
                $tenantMiddleware->handle();
                
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::debug('Tenant context set in WaiterController::dashboard', [
                        'subdomain' => $subdomain,
                        'tenant_id' => \App\Core\TenantContext::getId()
                    ]);
                }
            } catch (\Exception $e) {
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::error('Failed to set tenant context in WaiterController::dashboard', [
                        'error' => $e->getMessage(),
                        'subdomain' => $subdomain
                    ]);
                }
            }
        }
        
        $this->ensureTenantContext();
        // Garson rolü kontrolü - sadece giriş yapmış garsonlar erişebilir
        $this->requireLogin();
        
        // Permission kontrolü - waiter.view veya MANAGER rolü gerekli
        // BUSINESS_MANAGER için waiter.view temel izinler listesinde var
        if (!$this->isSuperAdmin() && !$this->hasPermission('waiter.view') && !$this->hasRole('MANAGER')) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::warning('WaiterController: Unauthorized access attempt', [
                    'role' => $this->getCurrentRole(),
                    'user_id' => $this->getCurrentUserId(),
                    'has_waiter_view' => $this->hasPermission('waiter.view'),
                    'has_manager_role' => $this->hasRole('MANAGER')
                ]);
            }
            // CRITICAL: Use current host (with subdomain) for redirect to preserve subdomain
            $currentHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $unauthorizedUrl = $protocol . '://' . $currentHost . '/unauthorized';
            header('Location: ' . $unauthorizedUrl);
            exit;
        }
        
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
        
        // Check subscription for non-super-admin users
        // NOTE: Temporarily disabled to prevent 504 Gateway Timeout errors
        // Subscription check can be re-enabled after fixing performance issues
        // The subscription check was causing timeout issues due to slow database queries
        /*
        if (!$isSuperAdmin) {
            try {
                $customerId = $_SESSION['customer_id'] ?? \App\Core\SessionManager::get('customer_id') ?? null;
                if ($customerId) {
                    // Set timeout for subscription check to prevent gateway timeout
                    $oldTimeout = ini_get('max_execution_time');
                    set_time_limit(5); // 5 second timeout for subscription check
                    
                    $subscriptionService = \App\Core\DependencyFactory::getSubscriptionService();
                    $subscription = $subscriptionService->getCustomerSubscription($customerId);
                    
                    // Restore timeout
                    set_time_limit($oldTimeout);
                    
                    // If no active subscription, show error and redirect
                    if (!$subscription || empty($subscription['status']) || strtoupper($subscription['status']) !== 'ACTIVE') {
                        $this->toastNotificationService->setFlash('warning', 'Garson paneline erişmek için aktif bir paket aboneliğiniz olmalıdır.');
                        // CRITICAL: Use current host (with subdomain) for redirect
                        $currentHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
                        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                        header('Location: ' . $protocol . '://' . $currentHost . '/customer/packages');
                        exit;
                    }
                }
            } catch (\Exception $e) {
                // If subscription check fails, log but allow access (graceful degradation)
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::error('Waiter subscription check failed', [
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }
        */
        
        // Get zones with tables (handle if zones table doesn't exist)
        try {
            $zones = $this->zoneService->getAllZones();
        } catch (\Exception $e) {
            $zones = [];
        }
        
        $tablesGrouped = $this->tableService->getTablesGroupedByZone();
        
        // Get unread notifications count
        $unreadCount = $this->notificationService->getUnreadCount();
        
        $data = [
            'zones' => $zones ?: [],
            'tables_grouped' => $tablesGrouped ?: [],
            'unread_notifications' => $unreadCount,
            'is_super_admin' => $isSuperAdmin
        ];
        
        $this->view('waiter/dashboard', $data);
    }
    
    /**
     * Zone bazlı gruplandırılmış masalar API
     */
    public function getTables() {
        // CRITICAL: API endpoint - always return JSON
        header('Content-Type: application/json; charset=utf-8');
        
        // CRITICAL: Ensure tenant context is set
        $this->ensureTenantContext();
        
        // Garson için sadece login kontrolü - API request ise redirect yapma
        if (!isLoggedIn()) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized', 'success' => false], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        // CRITICAL: Get tenant ID to filter tables
        $tenantId = \App\Core\TenantContext::getId();
        $isSuperAdmin = $this->isSuperAdmin();
        
        $tablesGrouped = $this->tableService->getTablesGroupedByZone();
        
        // Try to get zones, but handle if table doesn't exist
        try {
            $zones = $this->zoneService->getAllZones();
        } catch (\Exception $e) {
            $zones = [];
        }
        
        // Calculate statistics for each zone
        $result = [
            'zones' => [],
            'total_tables' => 0,
            'occupied_tables' => 0,
            'free_tables' => 0
        ];
        
        // PERFORMANCE OPTIMIZATION: Collect all table IDs first for batch query
        $allTableIds = [];
        $tablesByTableId = [];
        
        foreach ($tablesGrouped as $zoneName => $tables) {
            foreach ($tables as $table) {
                $tableId = $table['table_id'] ?? '';
                if (!empty($tableId)) {
                    // CRITICAL: Verify table belongs to current tenant (unless super admin)
                    if (!$isSuperAdmin && $tenantId) {
                        $tableBusinessId = $table['tenant_id'] ?? null;
                        if (!$tableBusinessId || $tableBusinessId !== $tenantId) {
                            continue; // Skip tables that don't belong to current tenant
                        }
                    }
                    $allTableIds[] = $tableId;
                    $tablesByTableId[$tableId] = $table;
                }
            }
        }
        
        // PERFORMANCE OPTIMIZATION: Get all active orders for all tables in a single batch query
        // This eliminates N+1 query problem (was: 1 query per table, now: 1 query for all tables)
        $activeOrdersByTableId = [];
        if (!empty($allTableIds)) {
            $ordersGrouped = $this->orderService->getActiveOrdersByTableIds($allTableIds);
            $activeOrdersByTableId = $ordersGrouped;
        }
        
        foreach ($tablesGrouped as $zoneName => $tables) {
            $occupiedCount = 0;
            $freeCount = 0;
            
            // CRITICAL: Filter tables by tenant (unless super admin)
            $filteredTables = [];
            foreach ($tables as $index => $table) {
                $tableId = $table['table_id'] ?? '';
                if (empty($tableId)) {
                    continue;
                }
                
                // CRITICAL: Verify table belongs to current tenant (unless super admin)
                if (!$isSuperAdmin && $tenantId) {
                    $tableBusinessId = $table['tenant_id'] ?? null;
                    // Skip tables that don't belong to current tenant or have no tenant_id
                    if (!$tableBusinessId || $tableBusinessId !== $tenantId) {
                        // Skip tables that don't belong to current tenant
                        continue;
                    }
                }
                
                // PERFORMANCE OPTIMIZATION: Get active orders from batch result instead of individual query
                $activeOrders = $activeOrdersByTableId[$tableId] ?? [];
                
                // Determine actual table status based on active orders
                $currentStatus = $table['status'] ?? 'FREE';
                $newStatus = $currentStatus;
                
                // For waiter dashboard: PAYMENT_PENDING tables should appear as FREE
                // since they're transferred to cashier
                if ($currentStatus === 'PAYMENT_PENDING') {
                    $newStatus = 'FREE';
                } elseif (empty($activeOrders)) {
                    $newStatus = 'FREE';
                } else {
                    $newStatus = 'OCCUPIED';
                }
                
                // Update table status in array
                $table['status'] = $newStatus;
                
                // PERFORMANCE: Include ready order count and total amount in tables response
                // This eliminates the need for separate batchCheckReadyOrders API calls
                $readyCount = 0;
                $tableTotalAmount = 0;
                foreach ($activeOrders as $order) {
                    $orderStatus = strtoupper($order['status'] ?? '');
                    if ($orderStatus === 'READY') {
                        $readyCount++;
                    }
                    $tableTotalAmount += floatval($order['total_amount'] ?? 0);
                }
                $table['ready_count'] = $readyCount;
                $table['active_order_count'] = count($activeOrders);
                $table['total_amount'] = $tableTotalAmount;
                
                $filteredTables[] = $table;
                
                // Count based on updated status
                // For waiter: PAYMENT_PENDING tables count as FREE
                if ($newStatus === 'OCCUPIED') {
                    $occupiedCount++;
                } else {
                    $freeCount++;
                }
            }
            
            // Only add zone if it has tables after filtering
            if (empty($filteredTables)) {
                continue;
            }
            
            // Find zone info
            $zoneInfo = null;
            foreach ($zones as $zone) {
                if (($zone['name'] ?? '') === $zoneName) {
                    $zoneInfo = $zone;
                    break;
                }
            }
            
            $result['zones'][$zoneName] = [
                'zone_id' => $zoneInfo['zone_id'] ?? null,
                'name' => $zoneName,
                'tables' => $filteredTables,
                'occupied_count' => $occupiedCount,
                'total_count' => count($filteredTables),
                'free_count' => $freeCount
            ];
            
            $result['total_tables'] += count($filteredTables);
            $result['occupied_tables'] += $occupiedCount;
            $result['free_tables'] += $freeCount;
        }
        
        $this->apiResponse($result);
    }
    
    /**
     * Masa bazlı bildirimler API
     */
    public function getTableNotifications() {
        // CRITICAL: API endpoint - always return JSON
        header('Content-Type: application/json; charset=utf-8');
        
        // Garson için sadece login kontrolü - API request ise redirect yapma
        if (!isLoggedIn()) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized', 'success' => false], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $tableId = $queryParams['table_id'] ?? '';
        
        if (!empty($tableId)) {
            // Get notifications for specific table
            $notifications = $this->notificationService->getByTable($tableId);
        } else {
            // Get all unread notifications
            $notifications = $this->notificationService->getUnread();
        }
        
        // Group by table
        $grouped = [];
        foreach ($notifications as $notification) {
            $tId = $notification['table_id'] ?? '';
            if (empty($tId)) continue;
            
            if (!isset($grouped[$tId])) {
                $grouped[$tId] = [];
            }
            
            // Get table info to find zone
            $table = $this->tableService->getTableById($tId);
            $zoneName = '';
            if ($table && isset($table['zone_name'])) {
                $zoneName = $table['zone_name'];
            } elseif ($table && isset($table['zone_id'])) {
                // Try to get zone name from zone_id
                try {
                    $zone = $this->zoneService->getZoneById($table['zone_id']);
                    if ($zone) {
                        $zoneName = $zone['name'] ?? '';
                    }
                } catch (\Exception $e) {
                    // Silent fail
                }
            }
            
            // Get existing data or create new
            $notifData = json_decode($notification['data'] ?? '{}', true);
            if (!is_array($notifData)) {
                $notifData = [];
            }
            $notifData['zone_name'] = $zoneName;
            
            $grouped[$tId][] = [
                'notification_id' => $notification['notification_id'] ?? '',
                'type' => $notification['type'] ?? '',
                'table_name' => $notification['table_name'] ?? '',
                'timestamp' => $notification['timestamp'] ?? '',
                'is_read' => $notification['is_read'] ?? 0,
                'data' => $notifData
            ];
        }
        
        // Count unread per table
        $tableCounts = [];
        foreach ($grouped as $tId => $notifs) {
            $unreadCount = 0;
            foreach ($notifs as $notif) {
                if (empty($notif['is_read'])) {
                    $unreadCount++;
                }
            }
            $tableCounts[$tId] = $unreadCount;
        }
        
        $this->apiResponse([
            'notifications' => $grouped,
            'table_counts' => $tableCounts,
            'total_unread' => $this->notificationService->getUnreadCount()
        ]);
    }
    
    /**
     * Bildirim okundu işaretleme
     */
    public function markNotificationRead() {
        // Garson için sadece login kontrolü
        $this->requireLogin();
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            if ($input === null) {
                $input = $_POST;
            }
            
            $notificationId = $input['notification_id'] ?? '';
            $tableId = $input['table_id'] ?? '';
            
            if (!empty($notificationId)) {
                // Mark specific notification as read
                $result = $this->notificationService->markAsRead($notificationId);
                $this->apiResponse(['success' => $result]);
            } elseif (!empty($tableId)) {
                // Mark all notifications for table as read
                $notifications = $this->notificationService->getByTable($tableId);
                $success = true;
                foreach ($notifications as $notification) {
                    if (empty($notification['is_read'])) {
                        $result = $this->notificationService->markAsRead($notification['notification_id']);
                        if (!$result) {
                            $success = false;
                        }
                    }
                }
                $this->apiResponse(['success' => $success]);
            } else {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
            }
        }
    }
    
    /**
     * Masa detayları (siparişler, toplam, durum)
     * @param string|null $id Route parameter (table ID)
     */
    public function getTableDetails($id = null) {
        // CRITICAL: API endpoint - always return JSON
        header('Content-Type: application/json; charset=utf-8');
        
        // Garson için sadece login kontrolü - API request ise redirect yapma
        if (!isLoggedIn()) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized', 'success' => false], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        // Get table ID from route parameter, GET parameter, or POST
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $tableId = $id ?? $queryParams['table_id'] ?? $queryParams['id'] ?? '';
        
        if (empty($tableId)) {
            http_response_code(400);
            echo json_encode(['error' => 'Table ID is required', 'success' => false], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        // CRITICAL: Verify tenant isolation
        $this->ensureTenantContext();
        
        // Get table info
        $table = $this->tableService->getTableById($tableId);
        if (!$table) {
            http_response_code(404);
            echo json_encode(['error' => 'Table not found', 'success' => false], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        // CRITICAL: Verify table belongs to current tenant (unless super admin)
        if (!$this->isSuperAdmin()) {
            $tenantId = \App\Core\TenantContext::getId();
            $tableBusinessId = $table['tenant_id'] ?? null;
            
            if (!$tenantId || !$tableBusinessId || $tableBusinessId !== $tenantId) {
                // Silently return 403 - don't log every violation to prevent log spam
                // Only log if it's a data integrity issue (table exists but has no tenant_id)
                if (!$tableBusinessId && $table) {
                    \App\Core\Logger::warning('WaiterController::getTableDetails - Table missing tenant_id', [
                        'table_id' => $tableId,
                        'table_business_id' => $table['business_id'] ?? 'not_set',
                        'table_tenant_id' => $table['tenant_id'] ?? 'not_set',
                        'tenant_id' => $tenantId
                    ]);
                }
                http_response_code(403);
                echo json_encode(['error' => 'Unauthorized', 'success' => false], JSON_UNESCAPED_UNICODE);
                exit;
            }
        }
        
        // OPTIMIZED: Get active orders with items in single query using JOIN
        // This eliminates N+1 query problem
        $activeOrders = $this->orderService->getActiveOrdersByTable($tableId);
        
        // Update table status based on active orders
        $tableStatus = $table['status'] ?? 'FREE';
        $statusUpdated = false;
        
        if (empty($activeOrders)) {
            // No active orders - table should be FREE
            if ($tableStatus !== 'FREE' && $tableStatus !== 'PAYMENT_PENDING') {
                // Don't change PAYMENT_PENDING to FREE automatically
                $this->tableService->updateTableStatus($tableId, 'FREE');
                $tableStatus = 'FREE';
                $statusUpdated = true;
            }
        } else {
            // Has active orders - table should be OCCUPIED (unless it's PAYMENT_PENDING)
            if ($tableStatus === 'FREE') {
                $this->tableService->updateTableStatus($tableId, 'OCCUPIED');
                $tableStatus = 'OCCUPIED';
                $statusUpdated = true;
            }
        }
        
        // Reload table to get updated status if it was updated
        if ($statusUpdated) {
            $table = $this->tableService->getTableById($tableId);
        }
        
        // OPTIMIZED: Get order items for all orders in batch using single query with JOIN
        // This eliminates N+1 query problem by fetching all items at once
        $orderIds = array_column($activeOrders, 'order_id');
        $totalAmount = 0;
        
        if (!empty($orderIds)) {
            // Get all order items for these orders in a single query using repository pattern
            $allOrderItems = $this->orderItemService->getOrderItemsByOrderIds($orderIds);
            
            // Group items by order_id
            $itemsByOrder = [];
            foreach ($allOrderItems as $item) {
                $orderId = $item['order_id'];
                if (!isset($itemsByOrder[$orderId])) {
                    $itemsByOrder[$orderId] = [];
                }
                $itemsByOrder[$orderId][] = $item;
            }
            
            // Attach items to orders and calculate total (recalculate from items to fix 0 TL bug)
            foreach ($activeOrders as &$order) {
                $orderId = $order['order_id'];
                $items = $itemsByOrder[$orderId] ?? [];
                
                // Filter out CANCELLED items
                $activeItems = array_values(array_filter($items, function($it) {
                    return ($it['preparation_status'] ?? '') !== 'CANCELLED';
                }));
                
                $order['items'] = $activeItems;
                
                // CRITICAL FIX: Recalculate total from items instead of trusting DB value
                // This fixes the "0 TL" display bug when total_amount is stale/incorrect
                $calcTotal = 0;
                foreach ($activeItems as $it) {
                    $calcTotal += floatval($it['price'] ?? 0) * intval($it['quantity'] ?? 1);
                }
                
                // Use recalculated total, fallback to DB only if no items
                if (!empty($activeItems)) {
                    $order['total_amount'] = round($calcTotal, 2);
                    
                    // Also update DB total if it differs (async fix)
                    $dbTotal = floatval($order['total_amount'] ?? 0);
                    if (abs($dbTotal - $calcTotal) > 0.01) {
                        try {
                            $this->orderService->updateOrderTotal($orderId, round($calcTotal, 2));
                        } catch (\Exception $e) {
                            // Non-critical, continue
                        }
                    }
                }
                
                $totalAmount += floatval($order['total_amount'] ?? 0);
            }
            unset($order); // Break reference
        }
        
        // OPTIMIZED: Get notifications only if needed (lazy loading)
        // Notifications are not critical for table details, can be loaded separately
        $notifications = [];
        $unreadNotifications = [];
        
        // Customer recommendations removed - Gemini only available for dashboard
        $recommendations = [];
        
        // Add cache headers for better performance
        header('Cache-Control: private, max-age=5'); // Cache for 5 seconds
        
        $this->apiResponse([
            'success' => true,
            'table' => $table,
            'orders' => $activeOrders,
            'total_amount' => $totalAmount,
            'notifications' => $notifications,
            'unread_count' => count($unreadNotifications),
            'recommendations' => $recommendations
        ]);
    }
    
    /**
     * Waiter için POS sayfası (ürün ekleme)
     */
    public function pos() {
        // CRITICAL FIX: Ensure tenant context is set BEFORE any database operations
        $this->ensureTenantContext();
        
        // Garson için login kontrolü - eğer login yoksa requireLogin zaten redirect eder
        // Ama waiter role kontrolü yapmıyoruz çünkü waiter dashboard'dan geliyor
        if (!$this->isLoggedIn()) {
            $this->requireLogin();
            return;
        }
        
        // Get table ID from query parameter
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $tableId = $queryParams['table'] ?? $queryParams['table_id'] ?? '';
        
        // Get table info
        $table = null;
        if (!empty($tableId)) {
            $table = $this->tableService->getTableById($tableId);
        }
        
        // Debug logging for tenant context
        if (class_exists('\App\Core\Logger')) {
            \App\Core\Logger::debug('WaiterController::pos - Loading menu', [
                'table_id' => $tableId,
                'tenant_id' => \App\Core\TenantContext::getId(),
                'has_table' => !empty($table)
            ]);
        }
        
        // Get categories and menu items
        $categoryService = \App\Core\DependencyFactory::getCategoryService();
        $menuItemService = \App\Core\DependencyFactory::getMenuItemService();
        
        // Clear category cache if requested (for debugging/refreshing)
        $queryParams = \App\Core\RequestParser::getQueryParams();
        if (isset($queryParams['refresh']) || isset($queryParams['nocache'])) {
            $categoryService->clearAllCategoryCache();
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::info('WaiterController::pos - Category cache cleared on request');
            }
        }
        
        $categories = $categoryService->getAllCategories();
        $menuItems = $menuItemService->getAvailableMenuItems();
        
        // Debug: Check if data is loaded
        if (class_exists('\App\Core\Logger')) {
            if (empty($categories)) {
                \App\Core\Logger::warning('WaiterController::pos - No categories found', [
                    'tenant_id' => \App\Core\TenantContext::getId()
                ]);
            } else {
                \App\Core\Logger::debug('WaiterController::pos - Data loaded successfully', [
                    'categories_count' => count($categories),
                    'menu_items_count' => count($menuItems)
                ]);
            }
        }
        
        // Generate CSRF token
        $csrfToken = generateCSRFToken();
        
        $settingsService = \App\Core\DependencyFactory::getSystemSettingsService();
        $settings = $settingsService->getSettings();
        $approvalService = \App\Core\DependencyFactory::getOrderEditApprovalService();
        $businessId = \App\Core\TenantResolver::resolve();
        $requiresApprovalForOrderEdit = $approvalService->requiresApproval($_SESSION['user_id'] ?? '', $businessId);
        // İşletme yöneticisi: session role BUSINESS_MANAGER ise (müşteri girişi) her zaman yönetici sayılır → buton görünürlüğü manager toggle'a bağlı
        $sessionRole = strtoupper(trim($_SESSION['role'] ?? \App\Core\SessionManager::get('role') ?? ''));
        if ($sessionRole === 'BUSINESS_MANAGER') {
            $requiresApprovalForOrderEdit = false;
        }
        $staffShowDeleteReduceButtons = !isset($settings['staff_show_delete_reduce_buttons']) || $settings['staff_show_delete_reduce_buttons'] === '' || $settings['staff_show_delete_reduce_buttons'] === '1' || $settings['staff_show_delete_reduce_buttons'] === 1;
        $managerShowDeleteReduceButtons = !isset($settings['manager_show_delete_reduce_buttons']) || $settings['manager_show_delete_reduce_buttons'] === '' || $settings['manager_show_delete_reduce_buttons'] === '1' || $settings['manager_show_delete_reduce_buttons'] === 1;
        $orderEditApprovalEnabled = (isset($settings['order_edit_requires_approval']) && ($settings['order_edit_requires_approval'] === '1' || $settings['order_edit_requires_approval'] === 1));
        
        $data = [
            'table' => $table,
            'table_id' => $tableId,
            'categories' => $categories ?: [],
            'menu_items' => $menuItems ?: [],
            'csrf_token' => $csrfToken,
            'requiresApprovalForOrderEdit' => $requiresApprovalForOrderEdit,
            'staffShowDeleteReduceButtons' => $staffShowDeleteReduceButtons,
            'managerShowDeleteReduceButtons' => $managerShowDeleteReduceButtons,
            'orderEditApprovalEnabled' => $orderEditApprovalEnabled
        ];
        
        $this->view('waiter/pos', $data);
    }
    
    /**
     * READY durumundaki siparişleri getir
     */
    public function getReadyOrders() {
        // Garson için sadece login kontrolü
        $this->requireLogin();
        
        $statusReady = ConstantsHelper::getOrderStatus('READY');
        $orders = $this->orderService->getOrdersByStatus($statusReady);
        
        // Add order items and table info to each order
        foreach ($orders as &$order) {
            $order['items'] = $this->orderItemService->getOrderItemsByOrder($order['order_id']);
            
            // Get table info
            if (!empty($order['table_id'])) {
                $table = $this->tableService->getTableById($order['table_id']);
                if ($table) {
                    $order['table_name'] = $table['name'] ?? '';
                    $order['table_zone'] = $table['zone_name'] ?? $table['zone'] ?? '';
                }
            }
        }
        
        $this->apiResponse([
            'success' => true,
            'orders' => $orders
        ]);
    }
    
    /**
     * Siparişi garson üzerine al (opsiyonel, takip için)
     */
    public function acceptOrder() {
        // Garson için sadece login kontrolü
        $this->requireLogin();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->apiResponse([
                'success' => false,
                'error' => 'Geçersiz istek metodu'
            ], 405);
            return;
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        if ($input === null) {
            $input = $_POST;
        }
        
        $orderId = $input['order_id'] ?? '';
        
        if (empty($orderId)) {
            $this->apiResponse([
                'success' => false,
                'error' => 'Sipariş ID gerekli'
            ], 400);
            return;
        }
        
        // Check if order exists and is READY
        $order = $this->orderService->getOrderById($orderId);
        if (!$order) {
            $this->apiResponse([
                'success' => false,
                'error' => 'Sipariş bulunamadı'
            ], 404);
            return;
        }
        
        $statusReady = ConstantsHelper::getOrderStatus('READY');
        if (($order['status'] ?? '') !== $statusReady) {
            $this->apiResponse([
                'success' => false,
                'error' => t('waiter.orderNotReady', 'Sipariş servise hazır değil')
            ], 400);
            return;
        }
        
        // For now, we just return success - in future we could track which waiter accepted
        $this->apiResponse([
            'success' => true,
            'message' => 'Sipariş üzerine alındı'
        ]);
    }
    
    /**
     * Masa taşıma (garson için)
     */
    public function moveTable() {
        $this->ensureTenantContext();
        $this->requireLogin();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->apiResponse([
                'success' => false,
                'error' => 'Geçersiz istek metodu'
            ], 405);
            return;
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        if ($input === null) {
            $input = $_POST;
        }
        
        $fromTableId = $input['from_table_id'] ?? '';
        $toTableId = $input['to_table_id'] ?? '';
        
        if (empty($fromTableId) || empty($toTableId)) {
            $this->apiResponse([
                'success' => false,
                'error' => 'Kaynak ve hedef masa ID\'leri gerekli'
            ], 400);
            return;
        }
        
        if ($fromTableId === $toTableId) {
            $this->apiResponse([
                'success' => false,
                'error' => 'Aynı masayı seçemezsiniz'
            ], 400);
            return;
        }
        
        $fromTable = $this->tableService->getTableById($fromTableId);
        $toTable = $this->tableService->getTableById($toTableId);
        
        if (!$fromTable) {
            $this->apiResponse([
                'success' => false,
                'error' => 'Kaynak masa bulunamadı'
            ], 404);
            return;
        }
        
        if (!$toTable) {
            $this->apiResponse([
                'success' => false,
                'error' => 'Hedef masa bulunamadı'
            ], 404);
            return;
        }
        
        if (!$this->isSuperAdmin()) {
            $tenantId = \App\Core\TenantContext::getId();
            $fromTenant = $fromTable['tenant_id'] ?? null;
            $toTenant = $toTable['tenant_id'] ?? null;
            
            if (!$tenantId || $fromTenant !== $tenantId || $toTenant !== $tenantId) {
                $this->apiResponse([
                    'success' => false,
                    'error' => 'Bu masalar için yetkiniz yok'
                ], 403);
                return;
            }
        }
        
        $activeOrders = $this->orderService->getActiveOrdersByTable($fromTableId);
        
        if (empty($activeOrders)) {
            $this->apiResponse([
                'success' => false,
                'error' => 'Taşınacak aktif sipariş bulunamadı. Önce sipariş oluşturun veya sepeti gönderin.'
            ], 400);
            return;
        }
        
        // Update all active orders to new table
        $success = true;
        foreach ($activeOrders as $order) {
            $result = $this->orderService->update($order['order_id'], [
                'table_id' => $toTableId,
                'table_name' => $toTable['name'] ?? 'Masa'
            ]);
            if (!$result) {
                $success = false;
            }
        }
        
        // Update table statuses
        if ($success) {
            // Talepleri yeni masaya taşı: garson çağrısı, hesap, iptal talepleri
            $this->notificationService->updateTableId($fromTableId, $toTableId);
            // Azaltma/iptal onay taleplerindeki masa bilgisini güncelle
            $orderIds = array_column($activeOrders, 'order_id');
            $approvalService = \App\Core\DependencyFactory::getOrderEditApprovalService();
            $approvalService->updateTableForOrders($orderIds, $toTableId, $toTable['name'] ?? 'Masa');
            
            // Check if from table has any remaining active orders
            $remainingOrders = $this->orderService->getOrdersByTable($fromTableId);
            $remainingActiveOrders = array_filter($remainingOrders, function($order) {
                $status = $order['status'] ?? '';
                return $status !== 'SERVED' && $status !== 'CANCELLED';
            });
            
            if (empty($remainingActiveOrders)) {
                $this->tableService->updateTableStatus($fromTableId, 'FREE');
            }
            
            // Update to table status to OCCUPIED
            $this->tableService->updateTableStatus($toTableId, 'OCCUPIED');
            
            $this->apiResponse([
                'success' => true,
                'message' => 'Masa başarıyla taşındı'
            ]);
        } else {
            $this->apiResponse([
                'success' => false,
                'error' => 'Masa taşınamadı'
            ], 500);
        }
    }
    
    /**
     * Delete order item (direct or via approval queue when order_edit_requires_approval is enabled)
     */
    public function deleteOrderItem() {
        $this->ensureTenantContext();
        $this->requireLogin();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->apiResponse([
                'success' => false,
                'error' => 'Geçersiz istek metodu'
            ], 405);
            return;
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        if ($input === null) {
            $input = $_POST;
        }
        
        $orderItemId = $input['order_item_id'] ?? '';
        
        if (empty($orderItemId)) {
            $this->apiResponse([
                'success' => false,
                'error' => 'Sipariş öğesi ID gerekli'
            ], 400);
            return;
        }
        
        // Get order item (with menu item name for activity logging)
        $orderItem = $this->orderItemService->getOrderItemByIdWithName($orderItemId);
        if (!$orderItem) {
            $orderItem = $this->orderItemService->getOrderItemById($orderItemId);
        }
        if (!$orderItem) {
            $this->apiResponse([
                'success' => false,
                'error' => 'Sipariş öğesi bulunamadı'
            ], 404);
            return;
        }
        
        // Get order info
        $order = $this->orderService->getOrderById($orderItem['order_id']);
        if (!$order) {
            $this->apiResponse([
                'success' => false,
                'error' => 'Sipariş bulunamadı'
            ], 404);
            return;
        }
        
        // Check if approval required - create approval request instead of direct delete
        $approvalService = \App\Core\DependencyFactory::getOrderEditApprovalService();
        $businessId = \App\Core\TenantResolver::resolve();
        $requiresApproval = $approvalService->requiresApproval($_SESSION['user_id'] ?? '', $businessId);
        
        if ($requiresApproval) {
            if ($approvalService->hasPendingApprovalForOrderItem($orderItemId)) {
                $this->apiResponse([
                    'success' => false,
                    'error' => 'Bu ürün için zaten bekleyen onay talebi var'
                ], 400);
                return;
            }
            $tableId = $order['table_id'] ?? '';
            $table = !empty($tableId) ? $this->tableService->getTableById($tableId) : null;
            $itemName = $orderItem['menu_item_name'] ?? $orderItem['item_name'] ?? $orderItem['name'] ?? 'Ürün';
            $userId = $_SESSION['user_id'] ?? '';
            $userName = $_SESSION['user_name'] ?? $_SESSION['username'] ?? $_SESSION['first_name'] ?? 'Garson';
            if (!empty($_SESSION['last_name'])) {
                $userName = trim(($userName ?: '') . ' ' . $_SESSION['last_name']);
            }
            $qty = intval($orderItem['quantity'] ?? 1);
            $price = floatval($orderItem['price'] ?? 0);
            $affectedSnapshot = [
                'name' => $itemName,
                'quantity' => $qty,
                'price' => $price,
                'total' => $qty * $price,
                'note' => $orderItem['note'] ?? $orderItem['notes'] ?? $orderItem['item_note'] ?? '',
                'excluded_ingredients' => $orderItem['excluded_ingredients'] ?? [],
                'selected_extras' => $orderItem['selected_extras'] ?? [],
                'variant_name' => $orderItem['variant_name'] ?? '',
                'preparation_status' => strtoupper(trim($orderItem['preparation_status'] ?? 'PENDING')),
            ];
            $approvalId = $approvalService->createApprovalRequest([
                'order_id' => $order['order_id'],
                'table_id' => $tableId,
                'table_name' => $table['name'] ?? '',
                'order_item_id' => $orderItemId,
                'action_type' => 'DELETE',
                'old_quantity' => $qty,
                'new_quantity' => null,
                'item_name' => $itemName,
                'item_price' => $price,
                'requested_by' => $userId,
                'requested_by_name' => $userName,
                'affected_item_snapshot' => $affectedSnapshot,
            ]);
            if ($approvalId) {
                $this->apiResponse([
                    'success' => true,
                    'message' => 'Silme talebi onay kuyruğuna gönderildi',
                    'approval_pending' => true
                ]);
                return;
            }
            $this->apiResponse(['success' => false, 'error' => 'Onay talebi oluşturulamadı'], 500);
            return;
        }
        
        // Direct delete - no approval needed
        $result = $this->orderItemService->deleteOrderItem($orderItemId);
        
        if ($result) {
            // Update order total
            $itemTotal = floatval($orderItem['price'] ?? 0) * intval($orderItem['quantity'] ?? 1);
            $newTotal = floatval($order['total_amount'] ?? 0) - $itemTotal;
            if ($newTotal < 0) $newTotal = 0;
            
            $this->orderService->updateOrderTotal($order['order_id'], $newTotal);
            
            // Get table info for logging
            $tableId = $order['table_id'] ?? '';
            $table = !empty($tableId) ? $this->tableService->getTableById($tableId) : null;
            $itemName = $orderItem['menu_item_name'] ?? $orderItem['item_name'] ?? $orderItem['name'] ?? 'Ürün';
            
            // Log activity
            try {
                $performer = \App\Services\TableActivityLogService::getPerformerInfo();
                $this->activityLogService->logItemDeleted(array_merge($performer, [
                    'business_id' => \App\Services\TableActivityLogService::getBusinessId(),
                    'table_id' => $tableId,
                    'table_name' => $table['name'] ?? '',
                    'order_id' => $order['order_id'],
                    'order_item_id' => $orderItemId,
                    'item_name' => $itemName,
                    'old_quantity' => intval($orderItem['quantity'] ?? 1),
                    'new_quantity' => 0,
                    'item_price' => floatval($orderItem['price'] ?? 0),
                    'total_affected' => $itemTotal,
                    'action_details' => [
                        'item_name' => $itemName,
                        'quantity' => intval($orderItem['quantity'] ?? 1),
                        'price' => floatval($orderItem['price'] ?? 0),
                        'total' => $itemTotal
                    ]
                ]));
            } catch (\Exception $e) {
                // Non-critical, continue
            }
            
            // Check if order has any remaining items
            $remainingItems = $this->orderItemService->getOrderItemsByOrder($order['order_id']);
            if (empty($remainingItems)) {
                // No items left, cancel the order
                $this->orderService->updateOrderStatus($order['order_id'], 'CANCELLED');
                
                // Check if table has any other active orders
                if (!empty($tableId)) {
                    $activeOrders = $this->orderService->getActiveOrdersByTable($tableId);
                    if (empty($activeOrders)) {
                        $this->tableService->updateTableStatus($tableId, 'FREE');
                    }
                }
            }
            
            $this->apiResponse([
                'success' => true,
                'message' => 'Ürün silindi'
            ]);
        } else {
            $this->apiResponse([
                'success' => false,
                'error' => 'Sipariş öğesi silinemedi'
            ], 500);
        }
    }
    
    /**
     * Reduce order item quantity (direct or via approval queue when order_edit_requires_approval is enabled)
     */
    public function reduceOrderItemQuantity() {
        $this->ensureTenantContext();
        $this->requireLogin();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->apiResponse([
                'success' => false,
                'error' => 'Geçersiz istek metodu'
            ], 405);
            return;
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        if ($input === null) {
            $input = $_POST;
        }
        
        $orderItemId = $input['order_item_id'] ?? '';
        $newQuantity = intval($input['new_quantity'] ?? 0);
        
        if (empty($orderItemId) || $newQuantity < 1) {
            $this->apiResponse([
                'success' => false,
                'error' => 'Geçersiz parametreler'
            ], 400);
            return;
        }
        
        // Get order item (with menu item name for activity logging)
        $orderItem = $this->orderItemService->getOrderItemByIdWithName($orderItemId);
        if (!$orderItem) {
            $orderItem = $this->orderItemService->getOrderItemById($orderItemId);
        }
        if (!$orderItem) {
            $this->apiResponse([
                'success' => false,
                'error' => 'Sipariş öğesi bulunamadı'
            ], 404);
            return;
        }
        
        $oldQuantity = intval($orderItem['quantity'] ?? 1);
        
        if ($newQuantity >= $oldQuantity) {
            $this->apiResponse([
                'success' => false,
                'error' => 'Yeni miktar mevcut miktardan küçük olmalıdır'
            ], 400);
            return;
        }
        
        // Get order info
        $order = $this->orderService->getOrderById($orderItem['order_id']);
        if (!$order) {
            $this->apiResponse([
                'success' => false,
                'error' => 'Sipariş bulunamadı'
            ], 404);
            return;
        }
        
        // Check if approval required - create approval request instead of direct update
        $approvalService = \App\Core\DependencyFactory::getOrderEditApprovalService();
        $businessId = \App\Core\TenantResolver::resolve();
        $requiresApproval = $approvalService->requiresApproval($_SESSION['user_id'] ?? '', $businessId);
        
        if ($requiresApproval) {
            if ($approvalService->hasPendingApprovalForOrderItem($orderItemId)) {
                $this->apiResponse([
                    'success' => false,
                    'error' => 'Bu ürün için zaten bekleyen onay talebi var'
                ], 400);
                return;
            }
            $tableId = $order['table_id'] ?? '';
            $table = !empty($tableId) ? $this->tableService->getTableById($tableId) : null;
            $itemName = $orderItem['menu_item_name'] ?? $orderItem['item_name'] ?? $orderItem['name'] ?? 'Ürün';
            $userId = $_SESSION['user_id'] ?? '';
            $userName = $_SESSION['user_name'] ?? $_SESSION['username'] ?? $_SESSION['first_name'] ?? 'Garson';
            if (!empty($_SESSION['last_name'])) {
                $userName = trim(($userName ?: '') . ' ' . $_SESSION['last_name']);
            }
            $price = floatval($orderItem['price'] ?? 0);
            $affectedSnapshot = [
                'name' => $itemName,
                'quantity' => $oldQuantity,
                'price' => $price,
                'total' => $oldQuantity * $price,
                'note' => $orderItem['note'] ?? $orderItem['notes'] ?? $orderItem['item_note'] ?? '',
                'excluded_ingredients' => $orderItem['excluded_ingredients'] ?? [],
                'selected_extras' => $orderItem['selected_extras'] ?? [],
                'variant_name' => $orderItem['variant_name'] ?? '',
                'preparation_status' => strtoupper(trim($orderItem['preparation_status'] ?? 'PENDING')),
            ];
            $approvalId = $approvalService->createApprovalRequest([
                'order_id' => $order['order_id'],
                'table_id' => $tableId,
                'table_name' => $table['name'] ?? '',
                'order_item_id' => $orderItemId,
                'action_type' => 'REDUCE_QUANTITY',
                'old_quantity' => $oldQuantity,
                'new_quantity' => $newQuantity,
                'item_name' => $itemName,
                'item_price' => $price,
                'requested_by' => $userId,
                'requested_by_name' => $userName,
                'affected_item_snapshot' => $affectedSnapshot,
            ]);
            if ($approvalId) {
                $this->apiResponse([
                    'success' => true,
                    'message' => 'Azaltma talebi onay kuyruğuna gönderildi',
                    'approval_pending' => true
                ]);
                return;
            }
            $this->apiResponse(['success' => false, 'error' => 'Onay talebi oluşturulamadı'], 500);
            return;
        }
        
        // Direct update - no approval needed
        try {
            $result = $this->orderItemService->updateQuantity($orderItemId, $newQuantity);
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('reduceOrderItemQuantity: updateQuantity failed', [
                    'order_item_id' => $orderItemId,
                    'new_quantity' => $newQuantity,
                    'error' => $e->getMessage()
                ]);
            }
            $this->apiResponse([
                'success' => false,
                'error' => 'Miktar güncellenirken hata oluştu'
            ], 500);
            return;
        }
        
        if ($result) {
            // Update order total
            $quantityDiff = $oldQuantity - $newQuantity;
            $priceDiff = floatval($orderItem['price'] ?? 0) * $quantityDiff;
            $newTotal = floatval($order['total_amount'] ?? 0) - $priceDiff;
            if ($newTotal < 0) $newTotal = 0;
            
            $this->orderService->updateOrderTotal($order['order_id'], $newTotal);
            
            // Get table info for logging
            $tableId = $order['table_id'] ?? '';
            $table = !empty($tableId) ? $this->tableService->getTableById($tableId) : null;
            $itemName = $orderItem['menu_item_name'] ?? $orderItem['item_name'] ?? $orderItem['name'] ?? 'Ürün';
            
            // Log activity
            try {
                $performer = \App\Services\TableActivityLogService::getPerformerInfo();
                $this->activityLogService->logQuantityReduced(array_merge($performer, [
                    'business_id' => \App\Services\TableActivityLogService::getBusinessId(),
                    'table_id' => $tableId,
                    'table_name' => $table['name'] ?? '',
                    'order_id' => $order['order_id'],
                    'order_item_id' => $orderItemId,
                    'item_name' => $itemName,
                    'old_quantity' => $oldQuantity,
                    'new_quantity' => $newQuantity,
                    'item_price' => floatval($orderItem['price'] ?? 0),
                    'total_affected' => $priceDiff,
                    'action_details' => [
                        'item_name' => $itemName,
                        'old_quantity' => $oldQuantity,
                        'new_quantity' => $newQuantity,
                        'price_per_unit' => floatval($orderItem['price'] ?? 0),
                        'total_reduced' => $priceDiff
                    ]
                ]));
            } catch (\Exception $e) {
                // Non-critical, continue
            }
            
            $this->apiResponse([
                'success' => true,
                'message' => 'Miktar güncellendi'
            ]);
        } else {
            $this->apiResponse([
                'success' => false,
                'error' => 'Miktar güncellenemedi'
            ], 500);
        }
    }
    
    /**
     * Update order item group - consolidate multiple same-product items
     * POST api/waiter/update-order-item-group { order_item_ids: [...], quantity: n }
     */
    public function updateOrderItemGroup() {
        $this->ensureTenantContext();
        $this->requireLogin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->apiResponse(['success' => false, 'error' => 'Method not allowed'], 405);
            return;
        }
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $ids = $input['order_item_ids'] ?? [];
        $newQuantity = intval($input['quantity'] ?? 0);
        if (!is_array($ids) || empty($ids) || $newQuantity < 0) {
            $this->apiResponse(['success' => false, 'error' => 'Geçersiz veri'], 400);
            return;
        }
        $first = $this->orderItemService->getOrderItemById($ids[0]);
        if (!$first) {
            $this->apiResponse(['success' => false, 'error' => 'Ürün bulunamadı'], 404);
            return;
        }
        $orderId = $first['order_id'];
        $order = $this->orderService->getOrderById($orderId);
        if (!$order || (!$this->isSuperAdmin() && ($order['tenant_id'] ?? '') !== \App\Core\TenantContext::getId())) {
            $this->apiResponse(['success' => false, 'error' => 'Yetkisiz'], 403);
            return;
        }
        if ($newQuantity === 0) {
            foreach ($ids as $id) {
                $this->orderItemService->deleteOrderItem($id);
            }
        } else {
            $this->orderItemService->updateQuantity($ids[0], $newQuantity);
            for ($i = 1; $i < count($ids); $i++) {
                $this->orderItemService->deleteOrderItem($ids[$i]);
            }
        }
        $allItems = $this->orderItemService->getOrderItemsByOrder($orderId);
        $newTotal = 0;
        foreach ($allItems as $it) {
            if (($it['preparation_status'] ?? '') !== 'CANCELLED') {
                $newTotal += floatval($it['price'] ?? 0) * intval($it['quantity'] ?? 1);
            }
        }
        $this->orderService->updateOrderTotal($orderId, round($newTotal, 2));
        $this->apiResponse(['success' => true, 'message' => 'Miktar güncellendi']);
    }
    
    /**
     * Delete all orders for a table (direct or via approval queue when order_edit_requires_approval is enabled)
     */
    public function deleteAllTableOrders() {
        $this->requireLogin();
        $this->ensureTenantContext();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->apiResponse(['success' => false, 'error' => 'Geçersiz istek metodu'], 405);
            return;
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        if ($input === null) {
            $input = $_POST;
        }
        
        $tableId = $input['table_id'] ?? '';
        
        if (empty($tableId)) {
            $this->apiResponse(['success' => false, 'error' => 'Masa ID gerekli'], 400);
            return;
        }
        
        // Get active orders for table
        $activeOrders = $this->orderService->getActiveOrdersByTable($tableId);
        
        if (empty($activeOrders)) {
            $this->apiResponse(['success' => false, 'error' => 'Masada aktif sipariş bulunamadı'], 400);
            return;
        }
        
        // Check if approval required - create approval requests instead of direct delete
        $approvalService = \App\Core\DependencyFactory::getOrderEditApprovalService();
        $businessId = \App\Core\TenantResolver::resolve();
        $requiresApproval = $approvalService->requiresApproval($_SESSION['user_id'] ?? '', $businessId);

        if ($requiresApproval) {
            if ($approvalService->hasPendingApprovalForTable($tableId)) {
                $this->apiResponse([
                    'success' => false,
                    'error' => 'Bu masa için zaten bekleyen onay talebi var'
                ], 400);
                return;
            }
            $table = $this->tableService->getTableById($tableId);
            $userId = $_SESSION['user_id'] ?? '';
            $userName = $_SESSION['user_name'] ?? $_SESSION['username'] ?? $_SESSION['first_name'] ?? 'Garson';
            if (!empty($_SESSION['last_name'])) {
                $userName = trim(($userName ?: '') . ' ' . $_SESSION['last_name']);
            }
            $createdCount = 0;
            foreach ($activeOrders as $order) {
                $orderId = $order['order_id'];
                $items = $this->orderItemService->getOrderItemsByOrder($orderId);
                $itemCount = count($items);
                $totalForOrder = 0;
                foreach ($items as $it) {
                    $totalForOrder += floatval($it['price'] ?? 0) * intval($it['quantity'] ?? 1);
                }
                $firstItemId = !empty($items) ? ($items[0]['order_item_id'] ?? '') : '';
                $snapshot = [];
                foreach ($items as $it) {
                    $qty = intval($it['quantity'] ?? 1);
                    $price = floatval($it['price'] ?? 0);
                    $snapshot[] = [
                        'name' => $it['item_name'] ?? $it['menu_item_name'] ?? $it['name'] ?? 'Ürün',
                        'quantity' => $qty,
                        'price' => $price,
                        'total' => $qty * $price,
                        'note' => $it['note'] ?? $it['notes'] ?? $it['item_note'] ?? '',
                        'excluded_ingredients' => $it['excluded_ingredients'] ?? [],
                        'selected_extras' => $it['selected_extras'] ?? [],
                        'variant_name' => $it['variant_name'] ?? '',
                        'preparation_status' => strtoupper(trim($it['preparation_status'] ?? 'PENDING')),
                    ];
                }
                $approvalId = $approvalService->createApprovalRequest([
                    'order_id' => $orderId,
                    'table_id' => $tableId,
                    'table_name' => $table['name'] ?? '',
                    'order_item_id' => $firstItemId,
                    'action_type' => 'DELETE_ORDER',
                    'old_quantity' => $itemCount,
                    'new_quantity' => null,
                    'item_name' => $itemCount . ' ürün (' . number_format($totalForOrder, 2) . ' ₺)',
                    'item_price' => $totalForOrder,
                    'requested_by' => $userId,
                    'requested_by_name' => $userName,
                    'order_items_snapshot' => $snapshot,
                ]);
                if ($approvalId) {
                    $createdCount++;
                }
            }
            if ($createdCount > 0) {
                $this->apiResponse([
                    'success' => true,
                    'message' => 'Tüm silme talepleri onaya gönderildi. İşletme yöneticinizden onay bekleniyor.',
                    'approval_pending' => true
                ]);
                return;
            }
            $this->apiResponse(['success' => false, 'error' => 'Onay talepleri oluşturulamadı'], 500);
            return;
        }
        
        $deletedCount = 0;
        $totalAffected = 0;
        $deletedItemDetails = [];
        
        // Get table info for logging
        $table = $this->tableService->getTableById($tableId);
        
        foreach ($activeOrders as $order) {
            $orderId = $order['order_id'];
            
            // Delete all items of this order
            $items = $this->orderItemService->getOrderItemsByOrder($orderId);
            foreach ($items as $item) {
                $itemTotal = floatval($item['price'] ?? 0) * intval($item['quantity'] ?? 1);
                $totalAffected += $itemTotal;
                $deletedItemDetails[] = [
                    'item_name' => $item['menu_item_name'] ?? $item['item_name'] ?? $item['name'] ?? 'Ürün',
                    'quantity' => intval($item['quantity'] ?? 1),
                    'price' => floatval($item['price'] ?? 0),
                    'total' => $itemTotal
                ];
                $this->orderItemService->deleteOrderItem($item['order_item_id']);
            }
            
            // Cancel the order
            $this->orderService->updateOrderStatus($orderId, 'CANCELLED');
            $this->orderService->updateOrderTotal($orderId, 0);
            $deletedCount++;
        }
        
        // Set table to FREE
        $this->tableService->updateTableStatus($tableId, 'FREE');
        
        // Log activity
        try {
            $performer = \App\Services\TableActivityLogService::getPerformerInfo();
            $this->activityLogService->logAllOrdersDeleted(array_merge($performer, [
                'business_id' => \App\Services\TableActivityLogService::getBusinessId(),
                'table_id' => $tableId,
                'table_name' => $table['name'] ?? '',
                'order_id' => $activeOrders[0]['order_id'] ?? null,
                'total_affected' => $totalAffected,
                'action_details' => [
                    'deleted_orders_count' => $deletedCount,
                    'total_amount_cancelled' => $totalAffected,
                    'items' => $deletedItemDetails
                ]
            ]));
        } catch (\Exception $e) {
            // Non-critical, continue
        }
        
        $this->apiResponse([
            'success' => true,
            'message' => $deletedCount . ' sipariş silindi',
            'deleted_count' => $deletedCount
        ]);
    }
    
    /**
     * Garson görünen adı (onay istekleri için). users.name veya customers company_name; e-posta ise okunaklı kısım.
     */
    private function getWaiterDisplayName(string $userId): string {
        $userName = $_SESSION['user_name'] ?? $_SESSION['username'] ?? '';
        if ($userId !== '') {
            try {
                $userService = \App\Core\DependencyFactory::getUserService();
                $user = $userService->findByUserId($userId);
                if ($user && !empty(trim($user['name'] ?? ''))) {
                    return trim($user['name']);
                }
                if ($user && !empty($user['email'] ?? '')) {
                    $userName = $user['email'];
                }
            } catch (\Exception $e) {
                // ignore
            }
            if ($userName === '' || strpos($userName, '@') !== false) {
                try {
                    $customerService = \App\Core\DependencyFactory::getCustomerService();
                    $customer = $customerService->getById($userId);
                    if ($customer && !empty(trim($customer['company_name'] ?? $customer['business_name'] ?? ''))) {
                        return trim($customer['company_name'] ?? $customer['business_name']);
                    }
                    if ($customer && !empty($customer['email'] ?? '')) {
                        $userName = $customer['email'];
                    }
                } catch (\Exception $e) {
                    // ignore
                }
            }
        }
        if ($userName === '') {
            return 'Garson';
        }
        if (strpos($userName, '@') !== false) {
            $namePart = explode('@', $userName)[0];
            if (preg_match('/^[a-z0-9._-]+$/i', $namePart) && strlen($namePart) > 2) {
                return ucfirst($namePart);
            }
            return 'Garson (' . $userName . ')';
        }
        return $userName;
    }
    
    /**
     * Siparişi teslim et (SERVED durumuna geçir)
     */
    public function deliverOrder() {
        // Garson için sadece login kontrolü
        $this->requireLogin();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->apiResponse([
                'success' => false,
                'error' => 'Geçersiz istek metodu'
            ], 405);
            return;
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        if ($input === null) {
            $input = $_POST;
        }
        
        $orderId = $input['order_id'] ?? '';
        
        if (empty($orderId)) {
            $this->apiResponse([
                'success' => false,
                'error' => 'Sipariş ID gerekli'
            ], 400);
            return;
        }
        
        // Check if order exists
        $order = $this->orderService->getOrderById($orderId);
        if (!$order) {
            $this->apiResponse([
                'success' => false,
                'error' => 'Sipariş bulunamadı'
            ], 404);
            return;
        }
        
        // Update order status to SERVED
        $statusServed = ConstantsHelper::getOrderStatus('SERVED');
        $result = $this->orderService->updateOrderStatus($orderId, $statusServed);
        
        if ($result) {
            // Check if table has any active orders left
            $tableId = $order['table_id'] ?? '';
            if (!empty($tableId)) {
                $tableOrders = $this->orderService->getOrdersByTable($tableId);
                $activeOrders = array_filter($tableOrders, function($o) {
                    $status = $o['status'] ?? '';
                    $servedStatus = ConstantsHelper::getOrderStatus('SERVED');
                    $cancelledStatus = ConstantsHelper::getOrderStatus('CANCELLED');
                    return $status !== $servedStatus && $status !== $cancelledStatus;
                });
                
                // If no active orders remain, update table status to FREE
                if (empty($activeOrders)) {
                    $this->tableService->updateTableStatus($tableId, 'FREE');
                }
            }
            
            $this->apiResponse([
                'success' => true,
                'message' => 'Sipariş teslim edildi'
            ]);
        } else {
            $this->apiResponse([
                'success' => false,
                'error' => 'Sipariş durumu güncellenemedi'
            ], 500);
        }
    }
    
    /**
     * Masayı kasiyere devret (PAYMENT_PENDING durumuna geçir)
     */
    public function transferToCashier() {
        // Garson için sadece login kontrolü
        $this->requireLogin();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->apiResponse([
                'success' => false,
                'error' => 'Geçersiz istek metodu'
            ], 405);
            return;
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        if ($input === null) {
            $input = $_POST;
        }
        
        $tableId = $input['table_id'] ?? '';
        
        if (empty($tableId)) {
            $this->apiResponse([
                'success' => false,
                'error' => 'Masa ID gerekli'
            ], 400);
            return;
        }
        
        // Get table info
        $table = $this->tableService->getTableById($tableId);
        if (!$table) {
            $this->apiResponse([
                'success' => false,
                'error' => 'Masa bulunamadı'
            ], 404);
            return;
        }
        
        // Check if table has active orders
        $orders = $this->orderService->getOrdersByTable($tableId);
        $activeOrders = array_filter($orders, function($order) {
            $status = $order['status'] ?? '';
            $servedStatus = ConstantsHelper::getOrderStatus('SERVED');
            $cancelledStatus = ConstantsHelper::getOrderStatus('CANCELLED');
            return $status !== $servedStatus && $status !== $cancelledStatus;
        });
        
        if (empty($activeOrders)) {
            $this->apiResponse([
                'success' => false,
                'error' => 'Masada aktif sipariş bulunamadı'
            ], 400);
            return;
        }
        
        // Update table status to PAYMENT_PENDING
        $result = $this->tableService->updateTableStatus($tableId, 'PAYMENT_PENDING');
        
        if ($result) {
            $this->apiResponse([
                'success' => true,
                'message' => 'Masa kasaya devredildi'
            ]);
        } else {
            $this->apiResponse([
                'success' => false,
                'error' => 'Masa durumu güncellenemedi'
            ], 500);
        }
    }
    
    /**
     * Sipariş durumunu güncelle ve müşteriye bildirim gönder
     */
    public function updateOrderStatusWithNotification() {
        $this->requireLogin();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->apiResponse([
                'success' => false,
                'error' => 'Invalid request method'
            ], 405);
            return;
        }
        
        $requestData = \App\Core\RequestParser::getRequestData();
        $orderId = $requestData['order_id'] ?? '';
        $status = $requestData['status'] ?? '';
        
        if (empty($orderId) || empty($status)) {
            $this->apiResponse([
                'success' => false,
                'error' => 'Sipariş ID ve durum gerekli'
            ], 400);
            return;
        }
        
        // Get order details
        $order = $this->orderService->getOrderById($orderId);
        if (!$order) {
            $this->apiResponse([
                'success' => false,
                'error' => 'Sipariş bulunamadı'
            ], 404);
            return;
        }
        
        $tableId = $order['table_id'] ?? '';
        
        // Update order status
        $result = $this->orderService->updateOrderStatus($orderId, $status);
        
        if ($result) {
            // Müşteriye sipariş durumu bildirimi gönder
            try {
                $table = $this->tableService->getTableById($tableId);
                $tableName = $table['name'] ?? 'Masa';
                
                // Durum mesajı
                $statusMessages = [
                    'PREPARING' => 'Siparişiniz hazırlanıyor',
                    'READY' => 'Siparişiniz hazır, garson getiriyor',
                    'SERVING' => 'Garsonunuz geliyor',
                    'SERVED' => 'Siparişiniz teslim edildi. Afiyet olsun!'
                ];
                
                $message = $statusMessages[$status] ?? 'Sipariş durumu güncellendi';
                
                // Create notification for customer (signature: type, tableId, tableName, data, playSound)
                $this->notificationService->create(
                    'ORDER_STATUS_UPDATE',
                    $tableId,
                    $tableName,
                    ['message' => $message, 'order_id' => $orderId, 'status' => $status, 'table_name' => $tableName],
                    true
                );
                
            } catch (\Exception $e) {
                // Log error but don't fail the request
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::error('Failed to send customer notification', [
                        'order_id' => $orderId,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            
            $this->apiResponse([
                'success' => true,
                'message' => 'Sipariş durumu güncellendi'
            ]);
        } else {
            $this->apiResponse([
                'success' => false,
                'error' => 'Sipariş durumu güncellenemedi'
            ], 500);
        }
    }
    
    /**
     * Müşteriye "Garson Geliyor" bildirimi gönder
     */
    public function notifyCustomerWaiterComing() {
        $this->requireLogin();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->apiResponse([
                'success' => false,
                'error' => 'Invalid request method'
            ], 405);
            return;
        }
        
        $requestData = \App\Core\RequestParser::getRequestData();
        $tableId = $requestData['table_id'] ?? '';
        
        if (empty($tableId)) {
            $this->apiResponse([
                'success' => false,
                'error' => 'Masa ID gerekli'
            ], 400);
            return;
        }
        
        try {
            $table = $this->tableService->getTableById($tableId);
            if (!$table) {
                $this->apiResponse([
                    'success' => false,
                    'error' => 'Masa bulunamadı'
                ], 404);
                return;
            }
            
            $tableName = $table['name'] ?? 'Masa';
            
            // Create notification for customer (signature: type, tableId, tableName, data, playSound)
            $result = $this->notificationService->create(
                'WAITER_COMING',
                $tableId,
                $tableName,
                ['message' => 'Garsonunuz geliyor', 'table_name' => $tableName, 'timestamp' => time()],
                true
            );
            
            if ($result) {
                $this->apiResponse([
                    'success' => true,
                    'message' => 'Müşteriye bildirim gönderildi'
                ]);
            } else {
                $this->apiResponse([
                    'success' => false,
                    'error' => 'Bildirim gönderilemedi'
                ], 500);
            }
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('Failed to notify customer', [
                    'table_id' => $tableId,
                    'error' => $e->getMessage()
                ]);
            }
            
            $this->apiResponse([
                'success' => false,
                'error' => 'Bildirim gönderilirken hata oluştu'
            ], 500);
        }
    }
    
    /**
     * Print table receipt (adisyon)
     * POST /api/waiter/print-table-receipt
     */
    public function printTableReceipt() {
        $this->ensureTenantContext();
        $this->requireLogin();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->apiResponse(['success' => false, 'error' => 'Method not allowed'], 405);
            return;
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        $tableId = $input['table_id'] ?? '';
        
        if (empty($tableId)) {
            $this->apiResponse(['success' => false, 'error' => 'Table ID required'], 400);
            return;
        }
        
        try {
            // Get active orders for table
            $orders = $this->orderService->getActiveOrdersByTable($tableId);
            
            if (empty($orders)) {
                $this->apiResponse(['success' => false, 'error' => 'No active orders for this table'], 400);
                return;
            }
            
            // Generate receipt for first order (or combine all)
            $receiptService = \App\Core\DependencyFactory::getReceiptService();
            $firstOrder = $orders[0];
            
            $receiptData = [
                'order_id' => $firstOrder['order_id'],
                'receipt_type' => 'ADISYON',
                'payment_method' => null, // Adisyon doesn't have payment yet
                'created_by' => $_SESSION['user_id'] ?? 'waiter'
            ];
            
            $receiptResult = $receiptService->generateReceipt($receiptData);
            
            if ($receiptResult) {
                // Print to cashier printer
                $receiptService->printReceipt(
                    $receiptResult['receipt_id'],
                    null, // Will use cashier screen's printer
                    'CASHIER'
                );
                
                $this->apiResponse(['success' => true, 'message' => 'Receipt sent to printer']);
            } else {
                $this->apiResponse(['success' => false, 'error' => 'Failed to generate receipt'], 500);
            }
            
        } catch (\Exception $e) {
            \App\Core\Logger::error('printTableReceipt failed', [
                'table_id' => $tableId,
                'error' => $e->getMessage()
            ]);
            $this->apiResponse(['success' => false, 'error' => 'Print failed'], 500);
        }
    }
    
    /**
     * Get table activity logs
     * GET /api/waiter/table-activity-logs?table_id=xxx
     */
    public function getTableActivityLogs() {
        header('Content-Type: application/json; charset=utf-8');
        
        if (!isLoggedIn()) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized', 'success' => false], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        $this->ensureTenantContext();
        
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $tableId = $queryParams['table_id'] ?? '';
        $dateFilter = $queryParams['date'] ?? 'today';
        if ($dateFilter !== 'today' && $dateFilter !== 'all') {
            $dateFilter = 'today';
        }
        
        if (empty($tableId)) {
            $this->apiResponse(['success' => false, 'error' => 'Table ID required'], 400);
            return;
        }
        
        try {
            $logs = $this->activityLogService->getByTable(
                $tableId,
                100,
                0,
                $dateFilter === 'all' ? null : 'today'
            );
            
            $this->apiResponse([
                'success' => true,
                'logs' => $logs,
                'date_filter' => $dateFilter
            ]);
        } catch (\Exception $e) {
            $this->apiResponse(['success' => false, 'error' => 'Hareket kayıtları alınamadı'], 500);
        }
    }
}

