<?php
namespace App\Controllers;

require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../core/RequestParser.php';
require_once __DIR__ . '/../core/DependencyFactory.php';
require_once __DIR__ . '/../helpers/auth.php';
use App\Core\Helpers\ConstantsHelper;

class APIController extends \App\Core\Controller {
    protected $orderService;
    protected $tableService;
    protected $zoneService;
    protected $categoryService;
    protected $reservationService;
    protected $financeService;
    protected $menuItemService;
    protected $orderItemService;
    protected $userService;
    protected $settingsService;
    protected $paymentTransactionService;
    protected $ingredientService;
    protected $wasteRecordService;
    protected $archivedSessionService;
    protected $integrationPlatformService;
    protected $contactFormService;
    public function __construct() {
        parent::__construct(); // Initialize auth and other services
        $this->orderService = \App\Core\DependencyFactory::getOrderService();
        $this->tableService = \App\Core\DependencyFactory::getTableService();
        $this->zoneService = \App\Core\DependencyFactory::getZoneService();
        $this->categoryService = \App\Core\DependencyFactory::getCategoryService();
        $this->reservationService = \App\Core\DependencyFactory::getReservationService();
        $this->financeService = \App\Core\DependencyFactory::getFinanceService();
        $this->menuItemService = \App\Core\DependencyFactory::getMenuItemService();
        $this->orderItemService = \App\Core\DependencyFactory::getOrderItemService();
        $this->userService = \App\Core\DependencyFactory::getUserService();
        $this->notificationService = \App\Core\DependencyFactory::getNotificationService();
        $this->settingsService = \App\Core\DependencyFactory::getSystemSettingsService();
        $this->paymentTransactionService = \App\Core\DependencyFactory::getPaymentTransactionService();
        $this->ingredientService = \App\Core\DependencyFactory::getIngredientService();
        $this->wasteRecordService = \App\Core\DependencyFactory::getWasteRecordService();
        $this->archivedSessionService = \App\Core\DependencyFactory::getArchivedSessionService();
        $this->integrationPlatformService = \App\Core\DependencyFactory::getIntegrationPlatformService();
        $this->contactFormService = new \App\Services\ContactFormService();
    }
    
    /**
     * Check if QR menu ordering is blocked for current tenant.
     * Returns qr_menu_status string or null if no restriction.
     */
    private function getQrMenuRestriction(): ?string {
        try {
            $tenantId = \App\Core\TenantContext::getId();
            if (!$tenantId) return null;

            $db = \App\Core\DependencyFactory::getDatabase();
            $stmt = $db->prepare("SELECT qr_menu_status, is_active FROM customers WHERE customer_id = :id LIMIT 1");
            $stmt->execute(['id' => $tenantId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$row) return null;

            $isActive = (int)($row['is_active'] ?? 1);
            $qrStatus = $row['qr_menu_status'] ?? 'active';

            if ($isActive === 0 || $qrStatus !== 'active') {
                return $qrStatus !== 'active' ? $qrStatus : 'passive';
            }

            // Trial expiry check: deny ordering when trial has expired
            try {
                $trialStmt = $db->prepare(
                    "SELECT trial_ends_at, trial_end, current_period_end
                     FROM subscriptions
                     WHERE tenant_id = :bid AND is_trial = 1
                     ORDER BY created_at DESC LIMIT 1"
                );
                $trialStmt->execute(['bid' => $tenantId]);
                $trialRow = $trialStmt->fetch(\PDO::FETCH_ASSOC);
                if ($trialRow) {
                    $_endsAt = $trialRow['trial_ends_at'] ?? $trialRow['trial_end'] ?? $trialRow['current_period_end'] ?? null;
                    if ($_endsAt && strtotime($_endsAt) < time()) {
                        $_graceEndTs = strtotime($_endsAt) + (7 * 86400);
                        return time() > $_graceEndTs ? 'passive' : 'menu_only';
                    }
                }
            } catch (\Throwable $_te) { /* graceful */ }

            return null;
        } catch (\Throwable $e) {
            return null;
        }
    }
    
    public function getMenu() {
        // CRITICAL: Ensure tenant context is set before fetching menu
        $this->ensureTenantContext();
        
        $categories = $this->categoryService->getAllCategories();
        $menuItems = $this->menuItemService->getAvailableMenuItems();
        
        $this->apiResponse([
            'categories' => $categories,
            'menu_items' => $menuItems
        ]);
    }
    
    public function getOrders() {
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $tableId = $queryParams['table_id'] ?? ($_SESSION['customer_table_id'] ?? '');
        
        if (!empty($tableId)) {
            // Store tableId in session for later use
            $_SESSION['customer_table_id'] = $tableId;
            
            // Get customer_session_id for per-customer filtering
            $customerSessionId = $_SESSION['customer_session_id'] ?? null;

            // Table-specific orders - no auth required (for customer QR menu)
            $orders = $this->orderService->getOrdersByTable($tableId);
            
            // CRITICAL: Filter orders to show only THIS customer's ACTIVE orders
            // Each customer at a table sees only their own unpaid/active order
            $orders = array_values(array_filter($orders, function($order) use ($customerSessionId) {
                // Never show paid or completed orders to new customers
                $status = strtoupper($order['status'] ?? '');
                $isPaid = !empty($order['is_paid']) && $order['is_paid'] == 1;
                
                // Skip completed/cancelled/paid orders
                if (in_array($status, ['SERVED', 'CANCELLED']) || $isPaid) {
                    return false;
                }
                
                // Filter by customer session - CRITICAL: never show other customers' orders
                $orderSessionId = $order['customer_session_id'] ?? null;
                if ($customerSessionId) {
                    // Only show orders belonging to THIS customer session
                    return $orderSessionId === $customerSessionId;
                }
                // No customer_session_id = new customer or session not established
                // Do NOT show any orders - cannot associate with current customer, would show old customers' orders
                return false;
            }));
            $customizationService = \App\Core\DependencyFactory::getIngredientCustomizationService();
            $menuItemScreenService = new \App\Services\MenuItemScreenService();
            $preparationScreenService = \App\Core\DependencyFactory::getPreparationScreenService();
            
            // Add order items and customizations to each order for customer display
            foreach ($orders as &$order) {
                $items = $this->orderItemService->getOrderItemsByOrder($order['order_id']);
                
                // Load all customizations for this order and group by order_item_id
                $customizations = $customizationService->getByOrder($order['order_id']);
                $customizationsByItem = [];
                foreach ($customizations as $customization) {
                    $orderItemId = $customization['order_item_id'] ?? null;
                    if ($orderItemId) {
                        if (!isset($customizationsByItem[$orderItemId])) {
                            $customizationsByItem[$orderItemId] = [];
                        }
                        $customizationsByItem[$orderItemId][] = $customization;
                    }
                }
                
                // Load extras and excluded ingredients for this order
                $db = \App\Core\DependencyFactory::getDatabase();
                $orderItemIds = array_column($items, 'order_item_id');
                $orderItemIdsPlaceholders = implode(',', array_fill(0, count($orderItemIds), '?'));
                
                // Fetch extras
                $extrasMap = [];
                if (!empty($orderItemIds)) {
                    try {
                        $extrasStmt = $db->prepare("SELECT * FROM order_item_extras WHERE order_item_id IN ($orderItemIdsPlaceholders)");
                        $extrasStmt->execute($orderItemIds);
                        $allExtras = $extrasStmt->fetchAll(\PDO::FETCH_ASSOC);
                        foreach ($allExtras as $extra) {
                            $oid = $extra['order_item_id'];
                            if (!isset($extrasMap[$oid])) {
                                $extrasMap[$oid] = [];
                            }
                            $extrasMap[$oid][] = $extra;
                        }
                    } catch (\Exception $e) {
                        // Ignore if table doesn't exist
                    }
                }
                
                // Fetch excluded ingredients
                $excludedMap = [];
                if (!empty($orderItemIds)) {
                    try {
                        $excludedStmt = $db->prepare("SELECT * FROM order_item_ingredients WHERE order_item_id IN ($orderItemIdsPlaceholders) AND is_excluded = 1");
                        $excludedStmt->execute($orderItemIds);
                        $allExcluded = $excludedStmt->fetchAll(\PDO::FETCH_ASSOC);
                        foreach ($allExcluded as $excluded) {
                            $oid = $excluded['order_item_id'];
                            if (!isset($excludedMap[$oid])) {
                                $excludedMap[$oid] = [];
                            }
                            $excludedMap[$oid][] = $excluded['ingredient_name'];
                        }
                    } catch (\Exception $e) {
                        // Ignore if table doesn't exist
                    }
                }
                
                foreach ($items as &$item) {
                    $orderItemId = $item['order_item_id'] ?? null;
                    $itemCustomizations = $orderItemId && isset($customizationsByItem[$orderItemId])
                        ? $customizationsByItem[$orderItemId]
                        : [];
                    
                    $item['customizations'] = $itemCustomizations;
                    $item['customizations_display'] = $customizationService->formatForDisplay($itemCustomizations);
                    
                    // Add extras and excluded ingredients
                    $item['selected_extras'] = $orderItemId && isset($extrasMap[$orderItemId]) ? $extrasMap[$orderItemId] : [];
                    $item['excluded_ingredients'] = $orderItemId && isset($excludedMap[$orderItemId]) ? $excludedMap[$orderItemId] : [];
                    
                    // Provide consistent name fields for frontend display
                    if (empty($item['menu_item_name']) && !empty($item['item_name'])) {
                        $item['menu_item_name'] = $item['item_name'];
                    }
                    if (empty($item['name']) && !empty($item['item_name'])) {
                        $item['name'] = $item['item_name'];
                    }
                    // Ekran bazlı gruplama için: screen_id, screen_name, preparation_status (Bekliyor/Hazırlanıyor/Hazır)
                    // Öncelik: menu_items.preparation_screen_id (Nargile, Bar vb.) → yoksa menu_item_screens → yoksa Mutfak
                    $item['preparation_status'] = strtoupper(trim($item['preparation_status'] ?? 'PENDING'));
                    if (!in_array($item['preparation_status'], ['PENDING', 'PREPARING', 'READY', 'SERVED'])) {
                        $item['preparation_status'] = 'PENDING';
                    }
                    $menuItemId = $item['menu_item_id'] ?? '';
                    $screenId = 'kitchen_main';
                    $screenName = 'Mutfak';
                    if ($menuItemId) {
                        $menuItem = $this->menuItemService->getMenuItemById($menuItemId);
                        $prepScreenId = $menuItem['preparation_screen_id'] ?? null;
                        if (!empty($prepScreenId)) {
                            $screen = $preparationScreenService->getScreenById($prepScreenId);
                            if ($screen && !empty($screen['name'])) {
                                $screenId = $screen['screen_id'] ?? $prepScreenId;
                                $screenName = $screen['name'];
                            }
                        }
                        if ($screenName === 'Mutfak') {
                            $screens = $menuItemScreenService->getScreensForItem($menuItemId);
                            $firstScreen = $screens[0] ?? null;
                            if ($firstScreen) {
                                $screenId = $firstScreen['screen_id'] ?? $screenId;
                                $screenName = $firstScreen['screen_name'] ?? $screenName;
                            }
                        }
                    }
                    $item['screen_id'] = $screenId;
                    $item['screen_name'] = $screenName;
                }
                unset($item);
                
                $order['items'] = $items;
            }
            unset($order); // Break reference

            // Tek hesap: tüm ödenmemiş siparişler tek kartta birleşik (paramparça değil)
            // Durum çubuğu en güncel siparişin durumunu gösterir
            $orders = $this->mergeOrdersForCustomerSession($orders);
            
            $this->apiResponse([
                'success' => true,
                'orders' => $orders
            ]);
        } else {
            // For staff, return all orders - requires permission
            if (!$this->checkPermissionOrFail('orders.view')) {
                $this->apiResponse([
                    'success' => false,
                    'error' => 'Unauthorized: You do not have permission to view orders'
                ], 403);
                return;
            }
            
            // Güvenlik: Müşteri ekranından bu API'ye erişim engelle
            // Note: preventCustomerAccess will send its own response if it blocks
            $this->preventCustomerAccess();
            
            $status = $queryParams['status'] ?? 'all';
            $limit = isset($queryParams['limit']) ? (int)$queryParams['limit'] : 100; // Default limit: 100
            $startDate = $queryParams['start_date'] ?? null;
            $endDate = $queryParams['end_date'] ?? null;
            
            // PERFORMANCE OPTIMIZATION: Always use date range or limit to prevent loading all orders
            if ($startDate && $endDate) {
                // Use date range filter
                $orders = $this->orderService->getOrdersByDateRange($startDate, $endDate);
                $orders = is_array($orders) ? $orders : [];
                
                // Filter by status if needed (case-insensitive - DB stores CANCELLED, frontend sends cancelled)
                if ($status !== 'all') {
                    $statusUpper = strtoupper($status);
                    $orders = array_filter($orders, function($order) use ($statusUpper) {
                        return strtoupper($order['status'] ?? '') === $statusUpper;
                    });
                    $orders = array_values($orders);
                }
                
                // Apply limit
                if ($limit > 0 && count($orders) > $limit) {
                    $orders = array_slice($orders, 0, $limit);
                }
            } else {
                // No date range - use recent orders with limit
                if ($status === 'all') {
                    $orders = method_exists($this->orderService, 'getRecentOrders') 
                        ? $this->orderService->getRecentOrders($limit)
                        : [];
                } else {
                    $allStatusOrders = $this->orderService->getOrdersByStatus($status);
                    $orders = is_array($allStatusOrders) ? array_slice($allStatusOrders, 0, $limit) : [];
                }
            }
            
            // Ensure orders is always an array
            if (!is_array($orders)) {
                \App\Core\Logger::warning('getOrders: orders is not an array, type: ' . gettype($orders));
                $orders = [];
            }
            
            \App\Core\Logger::debug('getOrders: Returning ' . count($orders) . ' orders');
            
            $this->apiResponse([
                'success' => true,
                'orders' => $orders
            ]);
        }
    }
    
    public function getTables() {
        try {
            // Get all tables
            $tables = $this->tableService->getAllTables();
            
            // Add zone information for each table
            if (!empty($tables) && is_array($tables)) {
                foreach ($tables as &$table) {
                    // If zone_id exists but zone_name doesn't, fetch zone info
                    if (!empty($table['zone_id']) && empty($table['zone_name'])) {
                        try {
                            $zone = $this->zoneService->getZoneById($table['zone_id']);
                            if ($zone) {
                                $table['zone_name'] = $zone['name'] ?? '';
                            }
                        } catch (\Exception $e) {
                            // Zone service might fail, continue without zone name
                            \App\Core\Logger::error('Error fetching zone for table: ' . $e->getMessage());
                        }
                    }
                    
                    // Ensure zone field exists for backward compatibility
                    if (empty($table['zone']) && !empty($table['zone_name'])) {
                        $table['zone'] = $table['zone_name'];
                    } elseif (empty($table['zone']) && !empty($table['zone_id'])) {
                        // Try to get zone name one more time
                        try {
                            $zone = $this->zoneService->getZoneById($table['zone_id']);
                            if ($zone) {
                                $table['zone'] = $zone['name'] ?? 'Bilinmiyor';
                                $table['zone_name'] = $zone['name'] ?? 'Bilinmiyor';
                            }
                        } catch (\Exception $e) {
                            $table['zone'] = 'Bilinmiyor';
                        }
                    } elseif (empty($table['zone'])) {
                        $table['zone'] = 'Bilinmiyor';
                    }
                }
            }
            
            $this->apiResponse([
                'success' => true,
                'tables' => $tables
            ]);
        } catch (\Exception $e) {
            \App\Core\Logger::error('Error in getTables: ' . $e->getMessage() . ' | Trace: ' . $e->getTraceAsString());
            $this->apiResponse([
                'success' => false,
                'error' => 'Masalar yüklenirken hata oluştu',
                'tables' => []
            ], 500);
        }
    }

    /**
     * Get table order sessions
     * Returns orders grouped by customer sessions for a specific table
     */
    public function getTableOrderSessions() {
        if (!$this->checkPermissionOrFail('orders.view')) {
            return;
        }
        
        $this->preventCustomerAccess();
        
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $tableId = $queryParams['table_id'] ?? '';
        
        if (empty($tableId)) {
            $this->apiResponse([
                'success' => false,
                'error' => 'Table ID is required'
            ], 400);
            return;
        }
        
        $tableSessions = $this->orderService->getTableOrderSessions($tableId);
        
        if ($tableSessions === null) {
            $this->apiResponse([
                'success' => false,
                'error' => 'Table not found or has no orders'
            ], 404);
            return;
        }
        
        $this->apiResponse([
            'success' => true,
            'data' => $tableSessions
        ]);
    }
    
    /**
     * Add table (API endpoint)
     */
    public function addTable() {
        // CRITICAL: Ensure tenant context is set before creating table
        $this->ensureTenantContext();
        
        if (!$this->checkPermissionOrFail('tables.manage')) {
            return;
        }
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_request', [], 405);
            return;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) {
            $data = $_POST;
        }
        
        // Validate request data using ValidationService
        $validationResult = $this->validateRequestData($data, 'table');
        
        if (!$validationResult['valid']) {
            $firstError = reset($validationResult['errors']);
            $errorMsg = is_array($firstError) ? reset($firstError) : $firstError;
            $this->toastNotificationService->sendApiResponse('error', 'notifications.warning.missing_fields', [], 400);
            return;
        }
        
        $validatedData = $validationResult['data'];
        
        // Additional validation for zone_id (might not be in validation rules)
        if (empty($data['zone_id'])) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.warning.missing_fields', [], 400);
            return;
        }
        
        // CRITICAL: Verify zone belongs to current tenant
        try {
            $zone = $this->zoneService->getZoneById($data['zone_id']);
            if (!$zone) {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.zone_not_found', [], 404);
                return;
            }
            
            // Check tenant isolation (unless super admin)
            if (!$this->isSuperAdmin()) {
                $tenantId = \App\Core\TenantContext::getId();
                $zoneBusinessId = $zone['tenant_id'] ?? null;
                
                if (!$tenantId || $zoneBusinessId !== $tenantId) {
                    \App\Core\Logger::warning('APIController::addTable - Zone tenant isolation violation', [
                        'zone_id' => $data['zone_id'],
                        'zone_business_id' => $zoneBusinessId,
                        'tenant_id' => $tenantId
                    ]);
                    $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 403);
                    return;
                }
            }
        } catch (\Exception $e) {
            \App\Core\Logger::error("Error fetching zone for table: " . $e->getMessage());
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.zone_not_found', [], 404);
            return;
        }
        
        require_once __DIR__ . '/../helpers/functions.php';
        $tableId = generateId('table');
        
        $tableData = [
            'table_id' => $tableId,
            'name' => $validatedData['name'] ?? '',
            'zone_id' => $data['zone_id'] ?? '',
            'capacity' => intval($validatedData['capacity'] ?? 4)
        ];
        
        // Set zone name for backward compatibility
        if ($zone && !empty($zone['name'])) {
            $tableData['zone'] = $zone['name'];
        }
        
        // Set default status only if database requires it
        if (isset($data['status'])) {
            $tableData['status'] = $data['status'];
        } else {
            $tableData['status'] = 'FREE'; // Default for database compatibility
        }
        
        // URL and QR code are auto-generated in createTable
        $result = $this->tableService->createTable($tableData);
        
        if ($result) {
            $table = $this->tableService->getTableById($result);
            $this->apiResponse(['success' => true, 'table' => $table]);
        } else {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.create_failed', [], 500);
        }
    }
    
    /**
     * Update table (API endpoint)
     */
    public function updateTable() {
        // CRITICAL: Ensure tenant context is set before updating table
        $this->ensureTenantContext();
        
        if (!$this->checkPermissionOrFail('tables.manage')) {
            return;
        }
        
        if ($_SERVER['REQUEST_METHOD'] !== 'PUT' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_request', [], 405);
            return;
        }
        
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $tableId = $queryParams['id'] ?? '';
        if (empty($tableId)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
            return;
        }
        
        // CRITICAL: Verify tenant isolation before update
        $existingTable = $this->tableService->getTableById($tableId);
        if (!$existingTable) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.table_not_found', [], 404);
            return;
        }
        
        // Check tenant isolation (unless super admin)
        if (!$this->isSuperAdmin()) {
            $tenantId = \App\Core\TenantContext::getId();
            $tableBusinessId = $existingTable['tenant_id'] ?? null;
            
            if (!$tenantId || $tableBusinessId !== $tenantId) {
                \App\Core\Logger::warning('APIController::updateTable - Tenant isolation violation', [
                    'table_id' => $tableId,
                    'table_business_id' => $tableBusinessId,
                    'tenant_id' => $tenantId
                ]);
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 403);
                return;
            }
        }
        
        $data = \App\Core\RequestParser::getRequestData();
        
        // Validate request data using ValidationService
        $validationResult = $this->validateRequestData($data, 'table');
        
        if (!$validationResult['valid']) {
            $firstError = reset($validationResult['errors']);
            $errorMsg = is_array($firstError) ? reset($firstError) : $firstError;
            $this->toastNotificationService->sendApiResponse('error', 'notifications.warning.missing_fields', [], 400);
            return;
        }
        
        $validatedData = $validationResult['data'];
        
        if (empty($validatedData['name'])) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.warning.missing_fields', [], 400);
            return;
        }
        
        $tableData = [
            'name' => $validatedData['name'] ?? '',
            'capacity' => intval($validatedData['capacity'] ?? 4)
        ];
        
        if (!empty($data['zone_id'])) {
            // CRITICAL: Verify new zone belongs to current tenant
            try {
                $zone = $this->zoneService->getZoneById($data['zone_id']);
                if (!$zone) {
                    $this->toastNotificationService->sendApiResponse('error', 'notifications.error.zone_not_found', [], 404);
                    return;
                }
                
                // Check tenant isolation (unless super admin)
                if (!$this->isSuperAdmin()) {
                    $tenantId = \App\Core\TenantContext::getId();
                    $zoneBusinessId = $zone['tenant_id'] ?? null;
                    
                    if (!$tenantId || $zoneBusinessId !== $tenantId) {
                        \App\Core\Logger::warning('APIController::updateTable - Zone tenant isolation violation', [
                            'zone_id' => $data['zone_id'],
                            'zone_business_id' => $zoneBusinessId,
                            'tenant_id' => $tenantId
                        ]);
                        $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 403);
                        return;
                    }
                }
                
                $tableData['zone_id'] = $data['zone_id'];
                // Also update zone name for backward compatibility
                if ($zone && !empty($zone['name'])) {
                    $tableData['zone'] = $zone['name'];
                }
            } catch (\Exception $e) {
                \App\Core\Logger::error("Error fetching zone for table update: " . $e->getMessage());
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.zone_not_found', [], 404);
                return;
            }
        }
        
        $result = $this->tableService->updateTable($tableId, $tableData);
        
        if ($result) {
            $table = $this->tableService->getTableById($tableId);
            $this->apiResponse(['success' => true, 'table' => $table]);
        } else {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.update_failed', [], 500);
        }
    }
    
    /**
     * Delete table (API endpoint)
     */
    public function deleteTable() {
        // CRITICAL: Ensure tenant context is set
        $this->ensureTenantContext();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'DELETE' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_request', [], 405);
            return;
        }
        
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $tableId = $queryParams['id'] ?? '';
        if (empty($tableId)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
            return;
        }
        
        // CRITICAL: Verify tenant isolation before deletion
        $table = $this->tableService->getTableById($tableId);
        if (!$table) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.table_not_found', [], 404);
            return;
        }
        
        // Check tenant isolation (unless super admin)
        // If table belongs to current tenant, allow deletion (business owner can delete their own tables)
        if (!$this->isSuperAdmin()) {
            $tenantId = \App\Core\TenantContext::getId();
            $tableBusinessId = $table['tenant_id'] ?? null;
            
            // If tenant matches, allow deletion (business owner deleting their own table)
            if ($tenantId && $tableBusinessId === $tenantId) {
                // Table belongs to current tenant - allow deletion
                // Permission check is bypassed for business owners deleting their own tables
            } else {
                // Table doesn't belong to current tenant - check permission
                if (!$this->hasPermission('tables.manage')) {
                    \App\Core\Logger::warning('APIController::deleteTable - Permission denied', [
                        'table_id' => $tableId,
                        'table_business_id' => $tableBusinessId,
                        'table_tenant_id' => $table['tenant_id'] ?? null,
                        'tenant_id' => $tenantId,
                        'has_permission' => false,
                        'user_id' => $_SESSION['user_id'] ?? 'unknown',
                        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                    ]);
                    $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 403);
                    return;
                }
                
                // Even with permission, don't allow deleting other tenant's tables
                if (!$tenantId || $tableBusinessId !== $tenantId) {
                    \App\Core\Logger::warning('APIController::deleteTable - Tenant isolation violation attempt', [
                        'table_id' => $tableId,
                        'table_business_id' => $tableBusinessId,
                        'table_tenant_id' => $table['tenant_id'] ?? null,
                        'tenant_id' => $tenantId,
                        'user_id' => $_SESSION['user_id'] ?? 'unknown',
                        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                    ]);
                    $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 403);
                    return;
                }
            }
        } else {
            // Super admin - still check permission
            if (!$this->checkPermissionOrFail('tables.manage')) {
                return;
            }
        }
        
        // Check for active orders (excludes SERVED and CANCELLED)
        $activeOrders = $this->orderService->getActiveOrdersByTable($tableId);
        if (count($activeOrders) > 0) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.warning.confirm_action', [], 400);
            return;
        }
        
        $result = $this->tableService->deleteTable($tableId);
        
        if ($result) {
            $this->apiResponse(['success' => true]);
        } else {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.delete_failed', [], 500);
        }
    }
    
    /**
     * Download QR code (API endpoint)
     */
    public function downloadQRCode() {
        if (!$this->checkPermissionOrFail('tables.view')) {
            return;
        }
        
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $tableId = $queryParams['id'] ?? '';
        if (empty($tableId)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
            return;
        }
        
        $table = $this->tableService->getTableById($tableId);
        if (!$table) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.table_not_found', [], 404);
            return;
        }
        
        $qrCodeUrl = $table['qr_code_url'] ?? '';
        if (empty($qrCodeUrl)) {
            // Generate QR code if not exists
            $qrCodeUrl = $this->tableService->generateQRCodeForTable($tableId);
            if (!$qrCodeUrl) {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.create_failed', [], 500);
                return;
            }
        }
        
        // Return QR code URL for download
        $this->apiResponse([
            'success' => true,
            'qr_code_url' => $qrCodeUrl,
            'table_url' => $table['url'] ?? $this->tableService->generateTableUrl($tableId)
        ]);
    }
    
    /**
     * Get zones (API endpoint)
     */
    public function getZones() {
        // CRITICAL: Ensure tenant context is set before fetching zones
        $this->ensureTenantContext();
        
        if (!$this->checkPermissionOrFail('tables.view')) {
            return;
        }
        
        $zones = $this->zoneService->getZonesWithTableCount();
        $this->apiResponse($zones);
    }
    
    /**
     * Get floors (API endpoint)
     */
    public function getFloors() {
        // CRITICAL: Ensure tenant context is set before fetching floors
        $this->ensureTenantContext();
        
        if (!$this->checkPermissionOrFail('tables.view')) {
            return;
        }
        
        $floors = $this->zoneService->getAllFloors();
        $this->apiResponse($floors);
    }
    
    public function placeOrder() {
        // Direct error_log for debugging - Logger might not be working
        error_log("=== PLACEORDER START ===");
        error_log("Method: " . ($_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN'));
        error_log("URI: " . ($_SERVER['REQUEST_URI'] ?? 'UNKNOWN'));
        
        try {
            // Check QR menu restriction
            $qrRestriction = $this->getQrMenuRestriction();
            if ($qrRestriction) {
                $msg = $qrRestriction === 'menu_only' 
                    ? 'Bu menü yalnızca görüntüleme amaçlıdır. Sipariş verme şu anda aktif değildir.' 
                    : 'QR menü geçici olarak servis dışıdır.';
                $this->apiResponse(['success' => false, 'error' => $msg], 403);
                return;
            }
            
            error_log("placeOrder: Inside try block");
            \App\Core\Logger::info('placeOrder: Method called', [
                'method' => $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
                'uri' => $_SERVER['REQUEST_URI'] ?? ''
            ]);
            
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                \App\Core\Logger::warning('placeOrder: Invalid request method', [
                    'method' => $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN'
                ]);
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_request', [], 405);
                return;
            }
            
            // Use RequestParser to handle both form and JSON requests (need early for duplicate check)
            $requestData = \App\Core\RequestParser::getRequestData();
            
            // CRITICAL: Server-side duplicate order prevention
            // Generate hash of order items to detect duplicate submissions
            $orderItemsHash = md5(json_encode([
                'table_id' => $requestData['table_id'] ?? '',
                'items' => $requestData['items'] ?? []
            ]));
            
            $duplicateWindowSeconds = 30; // Reject duplicate orders within 30 seconds
            $lastOrderKey = 'last_order_hash';
            $lastOrderTimeKey = 'last_order_time';
            
            $lastHash = $_SESSION[$lastOrderKey] ?? null;
            $lastTime = $_SESSION[$lastOrderTimeKey] ?? 0;
            $currentTime = time();
            
            if ($lastHash === $orderItemsHash && ($currentTime - $lastTime) < $duplicateWindowSeconds) {
                \App\Core\Logger::warning('placeOrder: Duplicate order detected', [
                    'hash' => $orderItemsHash,
                    'time_since_last' => $currentTime - $lastTime
                ]);
                // Return success to prevent client retry loops, order was already placed
                $this->apiResponse([
                    'success' => true, 
                    'message' => 'Sipariş zaten alındı',
                    'duplicate' => true
                ]);
                return;
            }
            
            // Store order hash and time BEFORE processing (to prevent race conditions)
            $_SESSION[$lastOrderKey] = $orderItemsHash;
            $_SESSION[$lastOrderTimeKey] = $currentTime;
            
            // Güvenlik: Admin kullanıcı müşteri API'sine erişememeli
            // Ancak müşteri ekranından (QR kod) erişimde session olmayabilir, bu durumda izin ver
            $isLoggedIn = isLoggedIn();
            $hasCustomerRole = hasRole('CUSTOMER');
            
            // Sadece admin/staff kullanıcılar engellenmeli, session olmayan müşteriler izin verilmeli
            if ($isLoggedIn && !$hasCustomerRole) {
                // Admin/staff kullanıcı müşteri API'sine erişememeli
                \App\Core\Logger::warning('placeOrder: Unauthorized user (admin/staff)', [
                    'is_logged_in' => $isLoggedIn,
                    'has_customer_role' => $hasCustomerRole,
                    'role' => $_SESSION['role'] ?? 'none'
                ]);
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 403);
                return;
            }
            // Müşteri veya session olmayan kullanıcı (QR kod erişimi) izin ver
            
            // Validate customer session is still active (not ended by payment)
            $customerSessionId = $_SESSION['customer_session_id'] ?? $requestData['customer_session_id'] ?? null;
            if ($customerSessionId && !str_starts_with($customerSessionId, 'virtual_')) {
                try {
                    $qrService = \App\Core\DependencyFactory::getQRCodeSecurityService();
                    $sessionStatus = $qrService->checkSessionActivity($customerSessionId, 30);
                    if (!($sessionStatus['active'] ?? true) && ($sessionStatus['reason'] ?? '') === 'SESSION_ENDED') {
                        $this->apiResponse([
                            'success' => false,
                            'error' => 'Oturum sona ermiş. Ödeme alındıktan sonra yeni sipariş veremezsiniz. Lütfen QR kodu tekrar okutun.',
                            'code' => 'SESSION_ENDED'
                        ], 403);
                        return;
                    }
                } catch (\Exception $e) {
                    // Fail-open: don't block order if session check fails
                }
            }
            
            \App\Core\Logger::info('placeOrder: Request data received', [
                'has_table_id' => !empty($requestData['table_id'] ?? ''),
                'items_count' => is_array($requestData['items'] ?? null) ? count($requestData['items']) : 0,
                'has_customizations' => !empty($requestData['customizations'] ?? null)
            ]);
            
            // Validate order data
            $orderData = [
                'table_id' => $requestData['table_id'] ?? '',
                'items' => is_array($requestData['items'] ?? null) ? $requestData['items'] : json_decode($requestData['items'] ?? '[]', true),
                'customizations' => is_array($requestData['customizations'] ?? null) ? $requestData['customizations'] : json_decode($requestData['customizations'] ?? '{}', true),
                'customer_note' => sanitizeInput($requestData['customer_note'] ?? ''),
                'order_source' => sanitizeInput($requestData['order_source'] ?? 'QR'),
            ];

            // Fallback to session table_id for QR customers
            if (empty($orderData['table_id']) && !empty($_SESSION['customer_table_id'])) {
                $orderData['table_id'] = $_SESSION['customer_table_id'];
            }
            
            \App\Core\Logger::info('placeOrder: Order data prepared', [
                'table_id' => $orderData['table_id'],
                'items_count' => is_array($orderData['items']) ? count($orderData['items']) : 0,
                'customizations_count' => is_array($orderData['customizations']) ? count($orderData['customizations']) : 0
            ]);
            
            // Validate order data (basic validation - table_id and items array)
            \App\Core\Logger::info('placeOrder: Starting validation');
            
            // Basic validation
            if (empty($orderData['table_id'])) {
                \App\Core\Logger::warning('placeOrder: table_id is empty');
                $this->toastNotificationService->sendApiResponse('error', 'notifications.warning.missing_fields', [], 400);
                return;
            }

            // Store tableId in session
            $_SESSION['customer_table_id'] = $orderData['table_id'];
            
            if (empty($orderData['items']) || !is_array($orderData['items']) || count($orderData['items']) === 0) {
                \App\Core\Logger::warning('placeOrder: items is empty or invalid');
                $this->toastNotificationService->sendApiResponse('error', 'notifications.warning.cart_empty', [], 400);
                return;
            }
            
            // Validate each order item manually (InputValidator doesn't support nested arrays)
            // Price is optional - server will use menu item price when not provided (secure, prevents client tampering)
            $itemErrors = [];
            foreach ($orderData['items'] as $index => $item) {
                if (empty($item['menu_item_id'])) {
                    $itemErrors["items.{$index}.menu_item_id"] = 'Menu item ID is required';
                }
                if (!isset($item['quantity']) || !is_numeric($item['quantity']) || intval($item['quantity']) < 1) {
                    $itemErrors["items.{$index}.quantity"] = 'Quantity must be at least 1';
                }
                // Price optional: if sent, must be non-negative; if missing, OrderService uses menu price
                if (array_key_exists('price', $item) && ( !is_numeric($item['price']) || floatval($item['price']) < 0 )) {
                    $itemErrors["items.{$index}.price"] = 'Price must be a non-negative number';
                }
            }
            
            if (!empty($itemErrors)) {
                \App\Core\Logger::warning('placeOrder: Order items validation failed', [
                    'errors' => $itemErrors
                ]);
                $this->apiResponse([
                    'success' => false,
                    'error' => 'Order items validation failed',
                    'errors' => $itemErrors,
                    'code' => 'VALIDATION_ERROR'
                ], 400);
                return;
            }
            
            \App\Core\Logger::info('placeOrder: Validation passed');
            
            // Add additional fields
            $orderData['is_delivery'] = isset($requestData['is_delivery']) && ($requestData['is_delivery'] === 'true' || $requestData['is_delivery'] === true);
            $orderData['created_by'] = 'customer';
            $orderData['delivery_address'] = sanitizeInput($requestData['delivery_address'] ?? '');
            $orderData['customer_phone'] = sanitizeInput($requestData['customer_phone'] ?? '');
            $orderData['delivery_location_lat'] = $requestData['delivery_location_lat'] ?? null;
            $orderData['delivery_location_lng'] = $requestData['delivery_location_lng'] ?? null;
            
            // CRITICAL: Pass customer_session_id for order consolidation
            // This enables: multiple order submissions by same customer = 1 order number
            $orderData['customer_session_id'] = $_SESSION['customer_session_id'] ?? $requestData['customer_session_id'] ?? null;
            
            \App\Core\Logger::info('placeOrder: Calling orderService->placeOrder');
            $result = $this->orderService->placeOrder($orderData);
            
            \App\Core\Logger::info('placeOrder: orderService->placeOrder returned', [
                'has_result' => !empty($result),
                'has_order_id' => (is_array($result) && array_key_exists('order_id', $result)),
                'result_type' => gettype($result)
            ]);
            
            if ($result && is_array($result) && array_key_exists('order_id', $result)) {
                \App\Core\Logger::info('placeOrder: Order created successfully', [
                    'order_id' => $result['order_id'],
                    'total_amount' => $result['total_amount'] ?? 0
                ]);
                $this->apiResponse(['success' => true, 'order_id' => $result['order_id'], 'total_amount' => $result['total_amount'] ?? 0]);
            } else {
                \App\Core\Logger::error('Order placement failed: placeOrder returned false or missing order_id', [
                    'order_data' => $orderData,
                    'result' => $result
                ]);
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.create_failed', [], 500);
            }
        } catch (\Exception $e) {
            \App\Core\Logger::error('Exception in placeOrder: ' . $e->getMessage(), [
                'order_data' => $orderData ?? [],
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.create_failed', [], 500);
        } catch (\Throwable $e) {
            \App\Core\Logger::error('Throwable in placeOrder: ' . $e->getMessage(), [
                'order_data' => $orderData ?? [],
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.create_failed', [], 500);
        }
    }
    
    public function updateOrderStatus() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_request', [], 405);
            return;
        }
        
        // Güvenlik: Permission kontrolü - kitchen veya orders edit yetkisi gerekli
        if (!$this->hasPermission('orders.edit') && !$this->hasPermission('kitchen.update_status')) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 401);
            return;
        }
        
        // Güvenlik: Müşteri ekranından bu API'ye erişim engelle
        $this->preventCustomerAccess();
        
        $requestData = \App\Core\RequestParser::getRequestData();
        $orderId = $requestData['order_id'] ?? '';
        $status = $requestData['status'] ?? '';
        
        // Validate required fields
        if (empty($orderId) || empty($status)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
            return;
        }
        
        // Validate status value
        $validStatuses = ConstantsHelper::getOrderStatuses();
        if (!ConstantsHelper::isValidOrderStatus($status)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
            return;
        }
        
        $result = $this->orderService->updateOrderStatus($orderId, $status);
        
        if ($result) {
            $this->apiResponse(['success' => true]);
        } else {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.update_failed', [], 500);
        }
    }
    
    public function callWaiter() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_request', [], 405);
            return;
        }
        
        $qrRestriction = $this->getQrMenuRestriction();
        if ($qrRestriction) {
            $this->apiResponse(['success' => false, 'error' => 'Bu fonksiyon şu anda aktif değildir.'], 403);
            return;
        }
        
        // Güvenlik: Admin kullanıcı müşteri API'sine erişememeli
        // Ancak müşteri ekranından (QR kod) erişimde session olmayabilir, bu durumda izin ver
        $isLoggedIn = isLoggedIn();
        $hasCustomerRole = hasRole('CUSTOMER');
        
        // Sadece admin/staff kullanıcılar engellenmeli, session olmayan müşteriler izin verilmeli
        if ($isLoggedIn && !$hasCustomerRole) {
            // Admin/staff kullanıcı müşteri API'sine erişememeli
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 403);
            return;
        }
        // Müşteri veya session olmayan kullanıcı (QR kod erişimi) izin ver
        
        $requestData = \App\Core\RequestParser::getRequestData();
        $tableId = $requestData['table_id'] ?? '';
        $type = $requestData['type'] ?? 'CALL_WAITER';
        
        if (empty($tableId)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.warning.missing_fields', [], 400);
            return;
        }
        
        $table = $this->tableService->getTableById($tableId);
        
        if ($table) {
            $notificationService = getNotificationService();
            $result = $notificationService->notifyWaiterCall($tableId, $table['name'] ?? '', $type);
            
            if ($result) {
                $this->apiResponse(['success' => true]);
            } else {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.create_failed', [], 500);
            }
        } else {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.table_not_found', [], 404);
        }
    }
    
    public function requestBill() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_request', [], 405);
            return;
        }
        
        $qrRestriction = $this->getQrMenuRestriction();
        if ($qrRestriction) {
            $this->apiResponse(['success' => false, 'error' => 'Bu fonksiyon şu anda aktif değildir.'], 403);
            return;
        }
        
        // Güvenlik: Admin kullanıcı müşteri API'sine erişememeli
        $isLoggedIn = isLoggedIn();
        $hasCustomerRole = hasRole('CUSTOMER');
        
        if ($isLoggedIn && !$hasCustomerRole) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 403);
            return;
        }
        
        $requestData = \App\Core\RequestParser::getRequestData();
        $tableId = $requestData['table_id'] ?? '';
        
        if (empty($tableId)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.warning.missing_fields', [], 400);
            return;
        }
        
        $table = $this->tableService->getTableById($tableId);
        
        if (!$table) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.table_not_found', [], 404);
            return;
        }
        
        // Validate: customer must have active (unpaid) orders to request bill
        $customerSessionId = $_SESSION['customer_session_id'] ?? null;
        $activeOrders = $this->orderService->getOrdersByTable($tableId);
        $hasActiveOrders = false;
        
        if (is_array($activeOrders)) {
            foreach ($activeOrders as $order) {
                $isPaid = !empty($order['is_paid']) && $order['is_paid'] == 1;
                $status = strtoupper($order['status'] ?? '');
                $isCancelled = $status === 'CANCELLED';
                
                if (!$isPaid && !$isCancelled) {
                    // If we have a session, only count orders from this session
                    if ($customerSessionId) {
                        if (($order['customer_session_id'] ?? null) === $customerSessionId) {
                            $hasActiveOrders = true;
                            break;
                        }
                    } else {
                        $hasActiveOrders = true;
                        break;
                    }
                }
            }
        }
        
        if (!$hasActiveOrders) {
            $this->apiResponse([
                'success' => false,
                'error' => 'Aktif siparişiniz bulunmamaktadır. Önce sipariş verin.'
            ], 400);
            return;
        }
        
        $this->tableService->updateTableStatus($tableId, 'PAYMENT_PENDING');
        
        $notificationService = getNotificationService();
        $result = $notificationService->notifyWaiterCall($tableId, $table['name'], 'REQUEST_BILL');
        
        if ($result) {
            $this->apiResponse(['success' => true]);
        } else {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.create_failed', [], 500);
        }
    }
    
    public function cancelOrderRequest() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_request', [], 405);
            return;
        }
        
        // Güvenlik: Admin kullanıcı müşteri API'sine erişememeli
        // Ancak müşteri ekranından (QR kod) erişimde session olmayabilir, bu durumda izin ver
        $isLoggedIn = isLoggedIn();
        $hasCustomerRole = hasRole('CUSTOMER');
        
        // Sadece admin/staff kullanıcılar engellenmeli, session olmayan müşteriler izin verilmeli
        if ($isLoggedIn && !$hasCustomerRole) {
            // Admin/staff kullanıcı müşteri API'sine erişememeli
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 403);
            return;
        }
        // Müşteri veya session olmayan kullanıcı (QR kod erişimi) izin ver
        
        $requestData = \App\Core\RequestParser::getRequestData();
        $orderId = $requestData['order_id'] ?? '';
        $tableId = $requestData['table_id'] ?? '';
        
        if (empty($orderId) || empty($tableId)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.warning.missing_fields', [], 400);
            return;
        }
        
        // Verify order exists and belongs to table
        $order = $this->orderService->getOrderById($orderId);
        if (!$order) {
            $this->toastNotificationService->sendApiResponse('error', 'Sipariş bulunamadı', [], 404);
            return;
        }
        
        // Verify order belongs to table
        if ($order['table_id'] !== $tableId) {
            $this->toastNotificationService->sendApiResponse('error', 'Sipariş bu masaya ait değil', [], 403);
            return;
        }
        
        // Only allow cancel request for PREPARING orders
        if ($order['status'] !== 'PREPARING') {
            $this->toastNotificationService->sendApiResponse('error', 'Sadece hazırlanan siparişler iptal edilebilir', [], 400);
            return;
        }
        
        $table = $this->tableService->getTableById($tableId);
        if (!$table) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.table_not_found', [], 404);
            return;
        }
        
        // Get order items for notification message
        $orderItems = $this->orderItemService->getOrderItemsByOrder($orderId);
        $itemNames = array_map(function($item) {
            return ($item['item_name'] ?? $item['name'] ?? 'Ürün') . ' x' . ($item['quantity'] ?? 1);
        }, $orderItems);
        $itemsText = implode(', ', array_slice($itemNames, 0, 3));
        if (count($itemNames) > 3) {
            $itemsText .= ' ve ' . (count($itemNames) - 3) . ' ürün daha';
        }
        
        $notificationService = getNotificationService();
        // Use CANCEL_ORDER type for cancel requests
        $result = $notificationService->notifyWaiterCall(
            $tableId, 
            $table['name'] ?? '', 
            'CANCEL_ORDER',
            [
                'order_id' => $orderId,
                'order_short_id' => substr($orderId, 0, 8),
                'items' => $itemsText,
                'table_id' => $tableId
            ]
        );
        
        if ($result) {
            $this->apiResponse(['success' => true, 'message' => 'İptal talebi garsona gönderildi']);
        } else {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.create_failed', [], 500);
        }
    }
    
    public function getNotifications() {
        try {
            $queryParams = \App\Core\RequestParser::getQueryParams();
            $tableId = $queryParams['table_id'] ?? '';
            
            if (!empty($tableId)) {
                // Müşteri için sadece kendi masasının bildirimleri - no auth required
                $notifications = $this->notificationService->getByTable($tableId);
                $this->apiResponse($notifications ?: []);
            } else {
                // For staff, return all notifications - requires permission
                if (!$this->checkPermissionOrFail('orders.view')) {
                    return;
                }
                
                // Güvenlik: Müşteri ekranından bu API'ye erişim engelle
                $this->preventCustomerAccess();
                
                $notifications = $this->notificationService->getAll();
                $this->apiResponse($notifications ?: []);
            }
        } catch (\Exception $e) {
            \App\Core\Logger::error("Error in getNotifications: " . $e->getMessage());
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.load_failed', [], 500);
        }
    }
    
    public function markNotificationRead($id = null) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_request', [], 405);
            return;
        }
        
        // Get notification ID from URL parameter, POST body, or GET parameter
        $requestData = \App\Core\RequestParser::getRequestData();
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $notificationId = $id ?? $requestData['notification_id'] ?? $queryParams['id'] ?? '';
        
        if (!empty($notificationId)) {
            $result = $this->notificationService->markAsRead($notificationId);
            
            if ($result) {
                $this->apiResponse(['success' => true]);
            } else {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.update_failed', [], 500);
            }
        } else {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
        }
    }
    
    public function getSettings() {
        $settings = $this->settingsService->getSettings();
        $this->apiResponse($settings);
    }
    
    public function updateSettings() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_request', [], 405);
            return;
        }
        
        // Check permission
        if (!$this->checkPermissionOrFail('settings.edit')) {
            return;
        }
        
        $requestData = \App\Core\RequestParser::getRequestData();
        $settingsData = [
            'service_charge_rate' => $requestData['service_charge_rate'] ?? 0,
            'cover_charge' => $requestData['cover_charge'] ?? 0,
            'currency' => $requestData['currency'] ?? 'TRY',
        ];
        
        // Validate settings data
        if (!$this->validateOrFail($settingsData, 'settings')) {
            return;
        }
        
        // Convert to string for storage
        $settingsData['service_charge_rate'] = (string)floatval($settingsData['service_charge_rate']);
        $settingsData['cover_charge'] = (string)floatval($settingsData['cover_charge']);
        $settingsData['currency'] = sanitizeInput($settingsData['currency']);
        
        $result = $this->settingsService->updateSettings($settingsData);
        
        if ($result) {
            $this->apiResponse(['success' => true]);
        } else {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.settings_update_failed', [], 500);
        }
    }
    
    public function getAnalytics() {
        // Check permission
        if (!$this->checkPermissionOrFail('dashboard.analytics')) {
            return;
        }
        
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $settingsService = \App\Core\DependencyFactory::getSystemSettingsService();
        $businessRange = $settingsService->getBusinessDateRange();
        $date = $queryParams['date'] ?? $businessRange['date'];
        
        // Use datetime range for today's revenue/orders (business hours), regular date for other dates
        $dailyRevenueValue = ($date === $businessRange['date']) 
            ? $this->orderService->getDailyRevenueByDatetimeRange($businessRange['start_datetime'], $businessRange['end_datetime'])
            : $this->orderService->getDailyRevenue($date);
        
        $totalOrders = ($date === $businessRange['date'])
            ? count($this->orderService->getOrdersByDatetimeRange($businessRange['start_datetime'], $businessRange['end_datetime']))
            : count($this->orderService->getOrdersByDateRange($date, $date));
        
        $analyticsData = [
            'daily_revenue' => $dailyRevenueValue,
            'daily_tip' => 0, // Would need to implement tip tracking
            'total_orders' => $totalOrders,
            'top_selling_items' => $this->orderService->getTopSellingItems(5),
            'hourly_busy' => [], // Would need to implement hourly data
            'category_sales' => [], // Would need to implement category sales
            'net_profit' => 0, // Would need to implement profit calculation
            'total_expenses' => 0 // Would need to implement expense tracking
        ];
        
        $this->apiResponse($analyticsData);
    }
    
    public function authenticate() {
        if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
            $this->apiResponse([
                'authenticated' => true,
                'user_id' => $_SESSION['user_id'],
                'username' => $_SESSION['username'],
                'role' => $_SESSION['role']
            ]);
        } else {
            $this->apiResponse(['authenticated' => false], 401);
        }
    }

    public function updateTableStatus() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_request', [], 405);
            return;
        }

        // Check permission
        if (!$this->checkPermissionOrFail('tables.manage')) {
            return;
        }

        $requestData = \App\Core\RequestParser::getRequestData();
        $tableData = [
            'table_id' => $requestData['table_id'] ?? '',
            'status' => $requestData['status'] ?? '',
        ];
        
        // Validate table data
        if (!$this->validateOrFail($tableData, 'table')) {
            return;
        }
        
        $tableId = $tableData['table_id'];
        $status = $tableData['status'];
        
        $validStatuses = ['FREE', 'OCCUPIED', 'PAYMENT_PENDING', 'DIRTY', 'RESERVED'];
        
        if (!in_array($status, $validStatuses)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
            return;
        }
        
        if (!empty($tableId)) {

            $result = $this->tableService->updateTableStatus($tableId, $status);

            if ($result) {
                $this->apiResponse(['success' => true]);
            } else {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.update_failed', [], 500);
            }
        } else {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
        }
    }

    public function getIngredients() {
        $ingredients = $this->ingredientService->getAll();
        $this->apiResponse($ingredients);
    }

    public function getSuppliers() {
        $suppliers = $this->financeService->getAllSuppliers();
        $this->apiResponse($suppliers);
    }

    public function getExpenses() {
        $expenses = $this->financeService->getAllExpenses();
        $this->apiResponse($expenses);
    }

    public function getWasteRecords() {
        $wasteRecords = $this->wasteRecordService->getAll();
        $this->apiResponse($wasteRecords);
    }

    // Shift management removed

    public function getReservations() {
        $reservations = $this->reservationService->getAllReservations();
        $this->apiResponse($reservations);
    }

    public function getInvoices() {
        $invoices = $this->financeService->getAllInvoices();
        $this->apiResponse($invoices);
    }

    public function getPaymentTransactions() {
        // Use service's findAll method (inherited from BaseService)
        $transactions = $this->paymentTransactionService->findAll();
        $this->apiResponse($transactions);
    }

    public function getIntegrationPlatforms() {
        $platforms = $this->integrationPlatformService->getAll();
        $this->apiResponse($platforms);
    }
    
    public function getTopSellingItems() {
        $items = $this->orderService->getTopSellingItems(10);
        $this->apiResponse($items);
    }

    public function getDailyRevenue() {
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $settingsService = \App\Core\DependencyFactory::getSystemSettingsService();
        $businessRange = $settingsService->getBusinessDateRange();
        $date = $queryParams['date'] ?? $businessRange['date'];
        
        // Use datetime range for today's revenue
        if ($date === $businessRange['date']) {
            $revenue = $this->orderService->getDailyRevenueByDatetimeRange($businessRange['start_datetime'], $businessRange['end_datetime']);
        } else {
            $revenue = $this->orderService->getDailyRevenue($date);
        }
        $this->apiResponse(['date' => $date, 'revenue' => $revenue]);
    }

    public function getHourlyBusy() {
        // Get hourly sales data
        $startDate = date('Y-m-d');
        $endDate = date('Y-m-d');
        $hourlySales = $this->orderService->getHourlySales($startDate, $endDate);
        
        $hourlyData = [];
        $hourlyMap = [];
        foreach ($hourlySales as $sale) {
            $hourlyMap[$sale['hour']] = [
                'count' => (int)$sale['order_count'],
                'revenue' => (float)$sale['revenue']
            ];
        }
        
        for ($hour = 0; $hour < 24; $hour++) {
            $data = $hourlyMap[$hour] ?? ['count' => 0, 'revenue' => 0];
            $maxCount = 20; // Normalize to percentage
            $heightPct = $maxCount > 0 ? min(100, ($data['count'] / $maxCount) * 100) : 0;
            
            $hourlyData[] = [
                'hour' => $hour,
                'count' => $data['count'],
                'heightPct' => $heightPct
            ];
        }
        
        $this->apiResponse($hourlyData);
    }

    public function getCategorySales() {
        // Get revenue by category for today
        $startDate = date('Y-m-d');
        $endDate = date('Y-m-d');
        $categoryRevenue = $this->orderService->getRevenueByCategory($startDate, $endDate);
        
        $categorySales = [];
        foreach ($categoryRevenue as $cat) {
            $categorySales[] = [
                'name' => $cat['category_name'],
                'value' => (float)$cat['revenue'],
                'color' => '#' . substr(md5($cat['category_name']), 0, 6) // Generate consistent color
            ];
        }

        $this->apiResponse($categorySales);
    }

    public function getNetProfit() {
        // Get date range from query parameters (default to current month)
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $startDate = $queryParams['start_date'] ?? date('Y-m-01'); // First day of current month
        $endDate = $queryParams['end_date'] ?? date('Y-m-d'); // Today
        
        // Validate dates
        $startDateTime = \DateTime::createFromFormat('Y-m-d', $startDate);
        $endDateTime = \DateTime::createFromFormat('Y-m-d', $endDate);
        
        if (!$startDateTime || !$endDateTime) {
            $startDate = date('Y-m-01');
            $endDate = date('Y-m-d');
        } else {
            // Ensure end date is not before start date
            if ($endDateTime < $startDateTime) {
                $endDate = $startDate;
            }
        }
        
        // Calculate total revenue from orders
        $totalRevenue = $this->orderService->calculateTotalRevenue($startDate, $endDate);
        
        // Calculate total expenses
        $totalExpenses = $this->financeService->getTotalExpensesByDateRange($startDate, $endDate);
        
        // Calculate net profit
        $netProfit = $totalRevenue - $totalExpenses;
        
        $this->apiResponse([
            'net_profit' => (float)$netProfit,
            'total_revenue' => (float)$totalRevenue,
            'total_expenses' => (float)$totalExpenses,
            'date_range' => [
                'start' => $startDate,
                'end' => $endDate
            ]
        ]);
    }

    public function searchMenuItems() {
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $query = $queryParams['q'] ?? '';

        if (empty($query)) {
            $this->apiResponse(['menu_items' => []]);
            return;
        }

        $results = $this->menuItemService->searchMenuItems($query);
        $this->apiResponse(['menu_items' => $results]);
    }

    public function getLowStockIngredients() {
        $ingredients = $this->ingredientService->getLowStock();
        $this->apiResponse($ingredients);
    }

    public function getOutOfStockMenuItems() {
        $results = $this->menuItemService->getOutOfStockItems();
        $this->apiResponse($results);
    }

    public function getActiveOrdersByTable() {
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $tableId = $queryParams['table_id'] ?? '';

        if (!empty($tableId)) {
            $orders = $this->orderService->getOrdersByTable($tableId);
            $this->apiResponse($orders);
        } else {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
        }
    }

    public function getOrderItems() {
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $orderId = $queryParams['order_id'] ?? '';

        if (!empty($orderId)) {
            $items = $this->orderItemService->getOrderItemsByOrder($orderId);
            $this->apiResponse($items);
        } else {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
        }
    }

    public function updateOrderItemQuantity() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_request', [], 405);
            return;
        }
        
        if (!$this->checkPermissionOrFail('orders.edit')) {
            return;
        }

        $requestData = \App\Core\RequestParser::getRequestData();
        $orderItemId = $requestData['order_item_id'] ?? '';
        $delta = intval($requestData['delta'] ?? 0);

        if (!empty($orderItemId) && $delta !== 0) {
            $orderItem = $this->orderItemService->getOrderItemById($orderItemId);

            if ($orderItem) {
                $newQuantity = $orderItem['quantity'] + $delta;

                if ($newQuantity <= 0) {
                    // Delete the order item if quantity becomes 0 or less
                    $result = $this->orderItemService->deleteOrderItem($orderItemId);
                } else {
                    // Update the quantity
                    $result = $this->orderItemService->updateQuantity($orderItemId, $newQuantity);
                }

                if ($result) {
                    // Update the parent order total
                    $order = $this->orderService->getOrderById($orderItem['order_id']);
                    $newTotal = $order['total_amount'];

                    // Recalculate order total
                    $orderItems = $this->orderItemService->getOrderItemsByOrder($orderItem['order_id']);
                    $newTotal = 0;
                    foreach ($orderItems as $item) {
                        $menuItem = $this->menuItemService->getMenuItemById($item['menu_item_id']);
                        if ($menuItem) {
                            $itemPrice = $menuItem['price'];
                            $extrasPrice = 0;

                            // Calculate extras price if needed (extras not yet in service)
                            // $extras = $this->orderItemModel->getExtras($item['order_item_id']);
                            // foreach ($extras as $extra) {
                            //     $extrasPrice += $extra['price'];
                            // }

                            $newTotal += ($itemPrice + $extrasPrice) * $item['quantity'];
                        }
                    }

                    $this->orderService->updateOrderTotal($orderItem['order_id'], $newTotal);

                    $this->apiResponse(['success' => true]);
                } else {
                    $this->toastNotificationService->sendApiResponse('error', 'notifications.error.update_failed', [], 500);
                }
            } else {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.not_found', [], 404);
            }
        } else {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
        }
    }

    public function removeOrderItem() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_request', [], 405);
            return;
        }
        
        if (!$this->checkPermissionOrFail('orders.edit')) {
            return;
        }

        $requestData = \App\Core\RequestParser::getRequestData();
        $orderItemId = $requestData['order_item_id'] ?? '';

        if (!empty($orderItemId)) {
            $orderItem = $this->orderItemService->getOrderItemById($orderItemId);

            if ($orderItem) {
                $result = $this->orderItemService->deleteOrderItem($orderItemId);

                if ($result) {
                    // Update the parent order total
                    $order = $this->orderService->getOrderById($orderItem['order_id']);
                    $newTotal = $order['total_amount'] - ($orderItem['price'] * $orderItem['quantity']);

                    if ($newTotal < 0) {
                        $newTotal = 0;
                    }

                    $this->orderService->updateOrderTotal($orderItem['order_id'], $newTotal);

                    $this->apiResponse(['success' => true]);
                } else {
                    $this->toastNotificationService->sendApiResponse('error', 'notifications.error.delete_failed', [], 500);
                }
            } else {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.not_found', [], 404);
            }
        } else {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
        }
    }

    public function processPayment() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_request', [], 405);
            return;
        }
        
        if (!$this->checkPermissionOrFail('pos.process_payment')) {
            return;
        }

        $requestData = \App\Core\RequestParser::getRequestData();
        $paymentData = [
            'table_id' => $requestData['table_id'] ?? '',
            'amount' => $requestData['amount'] ?? 0,
            'payment_method' => $requestData['method'] ?? 'CASH',
            'tip' => $requestData['tip'] ?? 0,
        ];
        
        // Validate payment data
        if (!$this->validateOrFail($paymentData, 'payment_transaction')) {
            return;
        }
        
        $tableId = $paymentData['table_id'];
        $amount = floatval($paymentData['amount']);
        $method = $paymentData['payment_method'];
        $tip = floatval($paymentData['tip']);

        // Shift management removed
        $shiftId = null;
        
        // Create payment transaction
        $transactionData = [
            'table_id' => $tableId,
            'amount' => $amount,
            'payment_method' => $method,
            'tip' => $tip,
            'timestamp' => date('Y-m-d H:i:s'),
            'processed_by' => $_SESSION['user_id'],
            'shift_id' => $shiftId
        ];
        
        $transactionResult = $this->paymentTransactionService->createTransaction($transactionData);

        if ($transactionResult) {
            // Update order status to SERVED for all orders on this table
            $orders = $this->orderService->getOrdersByTable($tableId);
            $totalRevenue = 0;
            $totalTip = $tip;
            
            $servedStatus = ConstantsHelper::getOrderStatus('SERVED');
            foreach ($orders as $order) {
                $this->orderService->updateOrderStatus($order['order_id'], $servedStatus);
                // Mark as paid so QR customers don't see old orders
                try {
                    $this->orderService->markOrderAsPaid($order['order_id']);
                } catch (\Exception $e) {
                    // Fallback: direct update if method doesn't exist
                    try {
                        $db = \App\Core\DependencyFactory::getDatabase();
                        $db->prepare("UPDATE orders SET is_paid = 1 WHERE order_id = ?")->execute([$order['order_id']]);
                    } catch (\Exception $e2) { /* non-critical */ }
                }
                $totalRevenue += floatval($order['total_amount'] ?? 0);
            }
            
            // Archive session when payment is processed
            $table = $this->tableService->getTableById($tableId);
            $tableName = $table['name'] ?? 'Masa';
            
            // Transactions are already created above
            
            // Create archived session
            $sessionData = [
                'session_id' => generateId('sess'),
                'table_id' => $tableId,
                'table_name' => $tableName,
                'start_time' => date('Y-m-d H:i:s', strtotime('-2 hours')), // Approximate start time
                'end_time' => date('Y-m-d H:i:s'),
                'total_revenue' => $totalRevenue,
                'total_tip' => $totalTip
            ];
            
            $this->archivedSessionService->createSession($sessionData);

            // Update table status to FREE
            $this->tableService->updateTableStatus($tableId, 'FREE');

            // Clear QR/table sessions after payment
            try {
                $tableSessionService = \App\Core\DependencyFactory::getTableSessionService();
                $customerSessionService = \App\Core\DependencyFactory::getCustomerSessionService();
                $tableSessionService->clearSessionsByTable($tableId);
                $customerSessionService->clearSessionsByTable($tableId);
            } catch (\Exception $e) {
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::warning('Failed to clear QR sessions after payment', [
                        'table_id' => $tableId,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            $this->apiResponse(['success' => true]);
        } else {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.payment_failed', [], 500);
        }
    }

    public function getMenuAdvanced() {
        // Using service layer
        $categories = $this->categoryService->getAllCategories();
        $menuItems = $this->menuItemService->getAvailableMenuItems();

        $this->apiResponse([
            'categories' => $categories,
            'menu_items' => $menuItems
        ]);
    }

    public function getAnalyticsAdvanced() {
        // Check permission
        if (!$this->checkPermissionOrFail('dashboard.analytics')) {
            return;
        }

        $analyticsService = getService('Analytics');
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $date = $queryParams['date'] ?? date('Y-m-d');

        $analyticsData = $analyticsService->getAnalyticsForDate($date);

        $this->apiResponse($analyticsData);
    }
    
    /**
     * Get menu item by ID
     */
    public function getMenuItem() {
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $itemId = $queryParams['id'] ?? '';
        if (empty($itemId)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
            return;
        }
        
        $menuItem = $this->menuItemService->getMenuItemById($itemId);
        if ($menuItem) {
            // Load translations
            $translationService = \App\Core\DependencyFactory::getMenuItemTranslationService();
            $translations = $translationService->repository->getTranslationsByMenuItem($itemId);
            
            if (!empty($translations)) {
                $menuItem['translations'] = [];
                foreach ($translations as $translation) {
                    $lang = $translation['language_code'];
                    $menuItem['translations'][$lang] = [
                        'name' => $translation['name'],
                        'description' => $translation['description'],
                        'meta_title' => $translation['meta_title'],
                        'meta_description' => $translation['meta_description'],
                        'meta_keywords' => $translation['meta_keywords'],
                        'slug' => $translation['slug']
                    ];
                }
            }
            
            $this->apiResponse($menuItem);
        } else {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.menu_item_not_found', [], 404);
        }
    }
    
    /**
     * Get category by ID
     */
    public function getCategory() {
        // CRITICAL: Ensure tenant context is set before fetching category
        $this->ensureTenantContext();
        
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $categoryId = $queryParams['id'] ?? '';
        if (empty($categoryId)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
            return;
        }
        
        $category = $this->categoryService->getCategoryById($categoryId);
        if ($category) {
            // CRITICAL: Verify tenant isolation
            if (!$this->isSuperAdmin()) {
                $tenantId = \App\Core\TenantContext::getId();
                $categoryBusinessId = $category['tenant_id'] ?? null;
                
                if (!$tenantId || $categoryBusinessId !== $tenantId) {
                    \App\Core\Logger::warning('APIController::getCategory - Tenant isolation violation', [
                        'category_id' => $categoryId,
                        'category_business_id' => $categoryBusinessId,
                        'tenant_id' => $tenantId
                    ]);
                    $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 403);
                    return;
                }
            }
            $this->apiResponse($category);
        } else {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.category_not_found', [], 404);
        }
    }
    
    /**
     * Require login for API requests
     * @param string|null $errorMessage Custom error message
     * @return bool True if logged in, false if not (response already sent)
     */
    protected function requireLoginOrFail(?string $errorMessage = null): bool {
        if (!$this->isLoggedIn()) {
            $this->unauthorizedResponse($errorMessage ?? 'Oturum açmanız gerekiyor');
            return false;
        }
        return true;
    }
    
    /**
     * Parse amount from form input (handle Turkish locale comma as decimal separator)
     * @param mixed $amount Amount value from form
     * @return float Parsed amount
     */
    /**
     * Honour a super-admin supplied `business_id` on write endpoints so that
     * mutations from the qodmin finance picker are scoped to the selected
     * tenant instead of the session one. No-op for regular users.
     */
    protected function applySuperAdminTenantFromBody(array $requestData): void {
        if (empty($requestData['business_id'])) {
            return;
        }
        if (!\App\Core\SessionManager::get('is_super_admin')) {
            return;
        }
        try {
            $cs = \App\Core\DependencyFactory::getCustomerService();
            $customer = $cs->getById((string)$requestData['business_id']);
            if ($customer) {
                \App\Core\TenantContext::set($customer);
            }
        } catch (\Throwable $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::warning('APIController: super-admin tenant override failed', [
                    'business_id' => $requestData['business_id'],
                    'error'       => $e->getMessage(),
                ]);
            }
        }
    }

    protected function parseAmount($amount): float {
        // Handle null or empty
        if ($amount === null || $amount === '' || $amount === false) {
            return 0.0;
        }
        
        // If already numeric, return as float
        if (is_numeric($amount)) {
            return floatval($amount);
        }
        
        // Convert to string and clean
        $amountStr = trim((string)$amount);
        
        // Remove currency symbols and spaces
        $amountStr = preg_replace('/[₺$€£,\s]/', '', $amountStr);
        
        // Replace Turkish comma with dot for decimal separator (if any comma exists)
        // But only if there's a single comma (decimal separator), not thousands separator
        if (substr_count($amountStr, ',') === 1 && substr_count($amountStr, '.') === 0) {
            $amountStr = str_replace(',', '.', $amountStr);
        } else {
            // Remove commas (thousands separator)
            $amountStr = str_replace(',', '', $amountStr);
        }
        
        // Remove all non-numeric characters except dot
        $amountStr = preg_replace('/[^0-9.]/', '', $amountStr);
        
        // Ensure only one dot (decimal separator)
        $parts = explode('.', $amountStr);
        if (count($parts) > 2) {
            // Multiple dots - keep first as integer part, join rest as decimal
            $amountStr = $parts[0] . '.' . implode('', array_slice($parts, 1));
        }
        
        $result = floatval($amountStr);
        
        // Log for debugging
        \App\Core\Logger::info('parseAmount', [
            'input' => $amount,
            'cleaned' => $amountStr,
            'output' => $result
        ]);
        
        return $result;
    }
    
    // startShift and endShift methods removed - shift management disabled
    
    /**
     * Add expense
     */
    public function addExpense() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_request', [], 405);
            return;
        }
        
        if (!$this->requireLoginOrFail()) {
            return;
        }
        
        if (!$this->checkPermissionOrFail('finance.expenses')) {
            return;
        }
        
        $requestData = \App\Core\RequestParser::getRequestData();
        $this->applySuperAdminTenantFromBody($requestData);
        $expenseId = 'e' . generateId();
        $data = [
            'expense_id' => $expenseId,
            'category' => $requestData['category'] ?? 'OTHER',
            'amount' => $this->parseAmount($requestData['amount'] ?? 0),
            'title' => $requestData['description'] ?? $requestData['title'] ?? 'Gider',
            'date' => $requestData['date'] ?? date('Y-m-d'),
            'supplier_id' => $requestData['supplier_id'] ?? null,
            'added_by' => $_SESSION['user_id']
        ];

        $result = $this->financeService->createExpense($data);
        if ($result) {
            $this->apiResponse(['success' => true, 'expense_id' => $expenseId]);
        } else {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.create_failed', [], 500);
        }
    }
    
    /**
     * Delete expense
     */
    public function deleteExpense() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_request', [], 405);
            return;
        }
        
        if (!$this->checkPermissionOrFail('finance.expenses')) {
            return;
        }
        
        $requestData = \App\Core\RequestParser::getRequestData();
        $expenseId = $requestData['expense_id'] ?? '';
        if (empty($expenseId)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
            return;
        }
        
        $result = $this->financeService->deleteExpense($expenseId);
        if ($result) {
            $this->apiResponse(['success' => true]);
        } else {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.delete_failed', [], 500);
        }
    }
    
    /**
     * Add waste (fire) record.
     *
     * Accepts either `ingredient_id` (canonical) or a free-text `ingredient_name`
     * (from the legacy waste form). When only a name is supplied, the ingredient
     * is looked up in the current tenant and auto-created on miss, so the ledger
     * can always point at a real row instead of silently dropping the entry.
     *
     * After the ledger row is saved, an equivalent `WASTE` stock movement is
     * recorded against the ingredient so `ingredients.current_stock` stays in
     * sync with the physical reality (this was previously missing).
     *
     * Super admins can scope the write to a specific tenant with `business_id`.
     */
    public function addWaste() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_request', [], 405);
            return;
        }
        if (!$this->requireLoginOrFail()) {
            return;
        }
        if (!$this->checkPermissionOrFail('finance.waste')) {
            return;
        }

        $requestData = \App\Core\RequestParser::getRequestData();
        $this->applySuperAdminTenantFromBody($requestData);

        // Resolve ingredient / menu item through central services so tenant
        // isolation, item-type defaults and `tenant_id` persistence all flow
        // through one code path. The `ingredients` table is tenant-scoped
        // (see 20260423 migration) so name lookups are tenant-filtered.
        $ingredientId   = trim((string)($requestData['ingredient_id']   ?? ''));
        $ingredientName = trim((string)($requestData['ingredient_name'] ?? ''));
        $menuItemId     = trim((string)($requestData['menu_item_id']    ?? ''));
        $unitRaw        = trim((string)($requestData['unit'] ?? 'adet'));
        $unit           = strtolower($unitRaw) ?: 'adet';

        if ($ingredientId === '' && $ingredientName !== '' && $menuItemId === '') {
            try {
                $ingSvc   = \App\Core\DependencyFactory::getIngredientService();
                $existing = $ingSvc->findByName($ingredientName);

                if ($existing && !empty($existing['ingredient_id'])) {
                    $ingredientId = (string)$existing['ingredient_id'];
                } else {
                    $ingredientId = 'ing_' . generateId();
                    $ingSvc->createIngredient([
                        'ingredient_id' => $ingredientId,
                        'name'          => $ingredientName,
                        'unit'          => $unit,
                        'current_stock' => 0,
                        'min_threshold' => 0,
                    ]);
                }
            } catch (\Throwable $e) {
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::error('addWaste: ingredient resolution failed', [
                        'name'  => $ingredientName,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        if ($ingredientId === '' && $menuItemId === '') {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
            return;
        }

        $amount = $this->parseAmount($requestData['amount'] ?? 0);
        if ($amount <= 0) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
            return;
        }

        // `WasteRecordService::createWasteRecord()` is the single source of
        // truth: it runs in a transaction, inserts the waste row and emits the
        // corresponding WASTE stock_movement (which decrements ingredient or
        // menu_item stock). Do NOT call removeStock here — that would double
        // the decrement.
        $wasteId = 'w' . generateId();

        $payload = [
            'waste_id'         => $wasteId,
            'ingredient_id'    => $ingredientId !== '' ? $ingredientId : null,
            'menu_item_id'     => $menuItemId   !== '' ? $menuItemId   : null,
            'amount'           => $amount,
            'unit'             => $unit,
            'reason'           => $requestData['reason'] ?? 'OTHER',
            'reason_detail'    => isset($requestData['reason_detail'])
                ? trim((string)$requestData['reason_detail']) : null,
            'date'             => $requestData['date']   ?? date('Y-m-d H:i:s'),
            'reported_by'      => $_SESSION['user_id'] ?? null,
            'supplier_id'      => isset($requestData['supplier_id']) && $requestData['supplier_id'] !== ''
                ? (string)$requestData['supplier_id'] : null,
            'purchase_item_id' => isset($requestData['purchase_item_id']) && $requestData['purchase_item_id'] !== ''
                ? (string)$requestData['purchase_item_id'] : null,
            'location_id'      => isset($requestData['location_id']) && $requestData['location_id'] !== ''
                ? (string)$requestData['location_id'] : null,
        ];

        $saved = $this->wasteRecordService->createWasteRecord($payload);

        if (!$saved) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.create_failed', [], 500);
            return;
        }

        // Handle optional image uploads. Form field name is `images[]`.
        $uploadedImages = [];
        if (!empty($_FILES['images']) && is_array($_FILES['images']['name'] ?? null)) {
            try {
                $imageService = \App\Core\DependencyFactory::getImageService();
                $count = count($_FILES['images']['name']);
                for ($i = 0; $i < $count; $i++) {
                    if (empty($_FILES['images']['name'][$i])) continue;
                    if ((int)$_FILES['images']['error'][$i] !== UPLOAD_ERR_OK) continue;
                    $single = [
                        'name'     => $_FILES['images']['name'][$i],
                        'type'     => $_FILES['images']['type'][$i],
                        'tmp_name' => $_FILES['images']['tmp_name'][$i],
                        'error'    => $_FILES['images']['error'][$i],
                        'size'     => $_FILES['images']['size'][$i],
                    ];
                    $res = $imageService->upload($single, 'waste', $wasteId);
                    if (!empty($res['success']) && !empty($res['data'])) {
                        $uploadedImages[] = $res['data'];
                    }
                }
                if (!empty($uploadedImages)) {
                    // Store a lightweight JSON list of URLs/paths for quick display.
                    $compact = array_map(static function ($img) {
                        return [
                            'media_id' => $img['media_id'] ?? ($img['id'] ?? null),
                            'url'      => $img['url'] ?? ($img['path'] ?? null),
                        ];
                    }, $uploadedImages);
                    $this->wasteRecordService->updateWasteRecord($wasteId, [
                        'images' => json_encode($compact, JSON_UNESCAPED_UNICODE),
                    ]);
                }
            } catch (\Throwable $e) {
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::warning('addWaste: image upload failed', [
                        'waste_id' => $wasteId,
                        'error'    => $e->getMessage(),
                    ]);
                }
            }
        }

        $this->apiResponse([
            'success'       => true,
            'waste_id'      => $wasteId,
            'ingredient_id' => $ingredientId !== '' ? $ingredientId : null,
            'menu_item_id'  => $menuItemId   !== '' ? $menuItemId   : null,
            'images'        => $uploadedImages,
        ]);
    }
    
    /**
     * Delete waste record
     */
    public function deleteWaste() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_request', [], 405);
            return;
        }
        
        if (!$this->checkPermissionOrFail('finance.waste')) {
            return;
        }
        
        $requestData = \App\Core\RequestParser::getRequestData();
        $wasteId = $requestData['waste_id'] ?? '';
        if (empty($wasteId)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
            return;
        }
        
        // WasteRecord model still used as service not yet created
        $result = $this->wasteRecordService->deleteWasteRecord($wasteId);
        if ($result) {
            $this->apiResponse(['success' => true]);
        } else {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.delete_failed', [], 500);
        }
    }
    
    /**
     * Delete order
     */
    /**
     * Transfer table
     */
    public function transferTable() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_request', [], 405);
            return;
        }
        
        if (!$this->checkPermissionOrFail('tables.transfer')) {
            return;
        }
        
        $requestData = \App\Core\RequestParser::getRequestData();
        $fromId = $requestData['from_id'] ?? '';
        $toId = $requestData['to_id'] ?? '';
        
        if (empty($fromId) || empty($toId)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.warning.missing_fields', [], 400);
            return;
        }
        
        // Get orders for from table
        $orders = $this->orderService->getOrdersByTable($fromId);
        
        if (empty($orders)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.not_found', [], 404);
            return;
        }
        
        // Get to table info
        $toTable = $this->tableService->getTableById($toId);
        if (!$toTable) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.table_not_found', [], 404);
            return;
        }
        
        // Update all orders to new table
        $success = true;
        foreach ($orders as $order) {
            $result = $this->orderService->update($order['order_id'], [
                'table_id' => $toId,
                'table_name' => $toTable['name'] ?? 'Masa'
            ]);
            if (!$result) {
                $success = false;
            }
        }
        
        if ($success) {
            // Talepleri yeni masaya taşı: garson çağrısı, hesap, iptal talepleri
            $this->notificationService->updateTableId($fromId, $toId);
            // Azaltma/iptal onay taleplerindeki masa bilgisini güncelle
            $orderIds = array_column($orders, 'order_id');
            $approvalService = \App\Core\DependencyFactory::getOrderEditApprovalService();
            $approvalService->updateTableForOrders($orderIds, $toId, $toTable['name'] ?? 'Masa');
            
            $this->tableService->updateTableStatus($fromId, 'FREE');
            $this->tableService->updateTableStatus($toId, 'OCCUPIED');
            $this->apiResponse(['success' => true]);
        } else {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.update_failed', [], 500);
        }
    }
    
    /**
     * Add reservation
     */
    public function addReservation() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_request', [], 405);
            return;
        }
        
        if (!$this->requireLoginOrFail()) {
            return;
        }
        
        if (!$this->checkPermissionOrFail('reservations.create')) {
            return;
        }
        
        $requestData = \App\Core\RequestParser::getRequestData();
        $reservationId = 'r' . generateId();
        $data = [
            'reservation_id' => $reservationId,
            'customer_name' => $requestData['customer_name'] ?? '',
            'contact' => $requestData['contact'] ?? '',
            'date' => $requestData['date'] ?? date('Y-m-d'),
            'time' => $requestData['time'] ?? '12:00',
            'guests' => intval($requestData['guests'] ?? 1),
            'table_id' => $requestData['table_id'] ?? null,
            'notes' => $requestData['notes'] ?? '',
            'status' => 'CONFIRMED',
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $result = $this->reservationService->createReservation($data);
        if ($result) {
            if (!empty($data['table_id'])) {
                $this->tableService->updateTableStatus($data['table_id'], 'RESERVED');
            }
            $this->apiResponse(['success' => true, 'reservation_id' => $reservationId]);
        } else {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.create_failed', [], 500);
        }
    }
    
    /**
     * Send reservation reminder email
     */
    public function sendReservationReminder() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_request', [], 405);
            return;
        }
        
        if (!$this->requireLoginOrFail()) {
            return;
        }
        
        if (!$this->checkPermissionOrFail('reservations.edit')) {
            return;
        }
        
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $requestData = \App\Core\RequestParser::getRequestData();
        $reservationId = $queryParams['id'] ?? $requestData['reservation_id'] ?? '';
        if (empty($reservationId)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
            return;
        }
        
        $reservation = $this->reservationService->getReservationById($reservationId);
        if (!$reservation) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.not_found', [], 404);
            return;
        }
        
        // Check reservation status - only send reminders for active reservations
        $status = $reservation['status'] ?? '';
        $pendingStatus = ConstantsHelper::getOrderStatus('PENDING');
        if (!in_array($status, [$pendingStatus, 'CONFIRMED'])) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
            return;
        }
        
        // Check if reservation date is in the future
        $reservationDate = $reservation['date'] ?? '';
        $reservationTime = $reservation['time'] ?? '';
        if (!empty($reservationDate) && !empty($reservationTime)) {
            $reservationDateTime = strtotime($reservationDate . ' ' . $reservationTime);
            if ($reservationDateTime < time()) {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
                return;
            }
        }
        
        // Get customer email
        $customerEmail = $reservation['customer_email'] ?? '';
        
        // If no email in customer_email, try to extract from contact field
        if (empty($customerEmail) || !filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
            $contact = $reservation['contact'] ?? '';
            if (!empty($contact) && preg_match('/[\w\.-]+@[\w\.-]+\.\w+/', $contact, $matches)) {
                $customerEmail = $matches[0];
            }
        }
        
        // Final email validation
        if (empty($customerEmail) || !filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
            return;
        }
        
        try {
            $emailService = \App\Core\DependencyFactory::getEmailService();
            
            // Get table name for email
            if (!empty($reservation['table_id'])) {
                $table = $this->tableService->getTableById($reservation['table_id']);
                $reservation['table_name'] = $table['name'] ?? 'Belirlenmedi';
            } else {
                $reservation['table_name'] = 'Belirlenmedi';
            }
            
            // Get hours before (default 24 hours)
            $requestData = \App\Core\RequestParser::getRequestData();
            $hoursBefore = intval($requestData['hours_before'] ?? 24);
            if ($hoursBefore < 1) {
                $hoursBefore = 24;
            }
            if ($hoursBefore > 168) { // Max 1 week
                $hoursBefore = 168;
            }
            
            $result = $emailService->sendReservationReminder($reservation, $hoursBefore);
            
            if ($result) {
                $this->apiResponse([
                    'success' => true, 
                    'message' => 'Hatırlatma emaili başarıyla gönderildi',
                    'email' => $customerEmail
                ]);
            } else {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.email_send_failed', [], 500);
            }
        } catch (\Exception $e) {
            \App\Core\Logger::error('Failed to send reservation reminder: ' . $e->getMessage());
            $this->apiResponse([
                'error' => 'Email gönderilirken bir hata oluştu: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Delete reservation
     */
    public function deleteReservation() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_request', [], 405);
            return;
        }
        
        if (!$this->checkPermissionOrFail('reservations.delete')) {
            return;
        }
        
        $requestData = \App\Core\RequestParser::getRequestData();
        $reservationId = $requestData['reservation_id'] ?? '';
        if (empty($reservationId)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
            return;
        }
        
        $reservation = $this->reservationService->getReservationById($reservationId);
        $result = $this->reservationService->deleteReservation($reservationId);
        if ($result) {
            if ($reservation && !empty($reservation['table_id'])) {
                $this->tableService->updateTableStatus($reservation['table_id'], 'FREE');
            }
            $this->apiResponse(['success' => true]);
        } else {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.delete_failed', [], 500);
        }
    }
    
    /**
     * Add supplier
     */
    public function addSupplier() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_request', [], 405);
            return;
        }
        
        if (!$this->requireLoginOrFail()) {
            return;
        }
        
        if (!$this->checkPermissionOrFail('finance.suppliers')) {
            return;
        }
        
        $supplierId = 'sup' . generateId();
        $requestData = \App\Core\RequestParser::getRequestData();
        $this->applySuperAdminTenantFromBody($requestData);
        $data = [
            'supplier_id' => $supplierId,
            'name' => $requestData['name'] ?? '',
            'contact' => $requestData['contact'] ?? '',
            'category' => $requestData['category'] ?? 'OTHER',
            'what_brings' => $requestData['what_brings'] ?? $requestData['category'] ?? 'OTHER',
            'balance' => 0,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $result = $this->financeService->createSupplier($data);
        if ($result) {
            $this->apiResponse(['success' => true, 'supplier_id' => $supplierId]);
        } else {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.create_failed', [], 500);
        }
    }
    
    /**
     * Update supplier
     */
    public function updateSupplier() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_request', [], 405);
            return;
        }
        
        if (!$this->checkPermissionOrFail('finance.suppliers')) {
            return;
        }
        
        $requestData = \App\Core\RequestParser::getRequestData();
        $supplierId = $requestData['supplier_id'] ?? '';
        if (empty($supplierId)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
            return;
        }
        
        $data = [
            'name' => $requestData['name'] ?? '',
            'contact' => $requestData['contact'] ?? '',
            'category' => $requestData['category'] ?? 'OTHER',
            'what_brings' => $requestData['what_brings'] ?? $requestData['category'] ?? 'OTHER'
        ];
        
        $result = $this->financeService->updateSupplier($supplierId, $data);
        if ($result) {
            $this->apiResponse(['success' => true]);
        } else {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.update_failed', [], 500);
        }
    }
    
    /**
     * Delete supplier
     */
    public function deleteSupplier() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_request', [], 405);
            return;
        }
        
        if (!$this->checkPermissionOrFail('finance.suppliers')) {
            return;
        }
        
        $requestData = \App\Core\RequestParser::getRequestData();
        $supplierId = $requestData['supplier_id'] ?? '';
        if (empty($supplierId)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
            return;
        }
        
        $result = $this->financeService->deleteSupplier($supplierId);
        if ($result) {
            $this->apiResponse(['success' => true]);
        } else {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.delete_failed', [], 500);
        }
    }
    
    /**
     * Add invoice
     */
    public function addInvoice() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_request', [], 405);
            return;
        }
        
        if (!$this->requireLoginOrFail()) {
            return;
        }
        
        if (!$this->checkPermissionOrFail('finance.invoices')) {
            return;
        }
        
        $invoiceId = 'inv' . generateId();
        $requestData = \App\Core\RequestParser::getRequestData();
        $this->applySuperAdminTenantFromBody($requestData);
        $data = [
            'invoice_id' => $invoiceId,
            'supplier_id' => $requestData['supplier_id'] ?? null,
            'invoice_number' => $requestData['invoice_number'] ?? '',
            'amount' => $this->parseAmount($requestData['amount'] ?? 0),
            'date' => $requestData['date'] ?? date('Y-m-d'),
            'due_date' => $requestData['due_date'] ?? date('Y-m-d', strtotime('+30 days')),
            'is_paid' => 0,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $result = $this->financeService->createInvoice($data);
        if ($result) {
            // Supplier balance is updated automatically by FinanceService
            $this->apiResponse(['success' => true, 'invoice_id' => $invoiceId]);
        } else {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.create_failed', [], 500);
        }
    }
    
    /**
     * Pay invoice
     */
    public function payInvoice() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_request', [], 405);
            return;
        }
        
        if (!$this->checkPermissionOrFail('finance.invoices')) {
            return;
        }
        
        $requestData = \App\Core\RequestParser::getRequestData();
        $invoiceId = $requestData['invoice_id'] ?? '';
        if (empty($invoiceId)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
            return;
        }
        
        $result = $this->financeService->markInvoiceAsPaid($invoiceId);
        if ($result) {
            $this->apiResponse(['success' => true]);
        } else {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.payment_failed', [], 500);
        }
    }
    
    // getCurrentShift method removed - shift management disabled
    
    /**
     * Müşteri ekranından admin API'lerine erişimi engelle
     * Referer kontrolü ile müşteri ekranından gelen istekleri tespit eder
     */
    private function preventCustomerAccess() {
        // Eğer kullanıcı admin olarak giriş yapmışsa, engelleme yapma
        $isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
        $userRole = $_SESSION['role'] ?? '';
        
        // Admin, Manager, Cashier, Waiter, Kitchen rolleri admin paneline erişebilir
        // Get admin roles from ConstantsService (all roles except CUSTOMER)
        try {
            $constantsService = \App\Core\DependencyFactory::getConstantsService();
            $allRoleCodes = $constantsService->getRoleCodes();
            // Filter out CUSTOMER role - all other roles can access admin panel
            $adminRoles = array_filter($allRoleCodes, function($role) {
                return $role !== 'CUSTOMER';
            });
        } catch (\Exception $e) {
            // Fallback to default admin roles if ConstantsService fails
            \App\Core\Logger::error("Failed to load roles from ConstantsService: " . $e->getMessage());
            $adminRoles = [
                'ADMIN',
                ConstantsHelper::getRole('MANAGER'),
                ConstantsHelper::getRole('CASHIER'),
                ConstantsHelper::getRole('WAITER'),
                ConstantsHelper::getRole('KITCHEN')
            ];
        }
        if ($isLoggedIn && in_array($userRole, $adminRoles)) {
            return; // Admin kullanıcıları engelleme - hemen çık
        }
        
        // Eğer admin değilse ve login değilse, müşteri ekranından gelip gelmediğini kontrol et
        if (!$isLoggedIn) {
            $referer = $_SERVER['HTTP_REFERER'] ?? '';
            
            // Referer'da customer veya table parametresi varsa engelle
            if (!empty($referer) && preg_match('/(customer|table|qr_menu)/i', $referer)) {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 403);
                return;
            }
        }
        
        // Referer kontrolü - sadece müşteri ekranından geliyorsa engelle
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        
        // Müşteri ekranı URL'lerini kontrol et (sadece referer'da varsa)
        $customerPaths = ['/t/', '/menu', '/customer/'];
        foreach ($customerPaths as $path) {
            // Sadece referer'da varsa engelle, requestUri'de olması normal (API endpoint)
            if (!empty($referer) && strpos($referer, $path) !== false) {
                // Ama admin kullanıcıysa yine de izin ver (yukarıda kontrol edildi)
                if ($isLoggedIn && in_array($userRole, $adminRoles)) {
                    continue;
                }
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 403);
                return;
            }
        }
    }

    public function changeLanguage() {
        // Support both POST and GET requests
        $input = [];
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Try JSON first
            $jsonInput = json_decode(file_get_contents('php://input'), true);
            if ($jsonInput !== null) {
                $input = $jsonInput;
            } else {
                $input = $_POST;
            }
        } else {
            $input = $_GET;
        }

        $queryParams = \App\Core\RequestParser::getQueryParams();
        $requestData = \App\Core\RequestParser::getRequestData();
        $lang = $input['language'] ?? $requestData['language'] ?? $queryParams['language'] ?? '';
        
        if (empty($lang) || !in_array($lang, ['tr', 'en'])) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
            return;
        }

        // Ensure session is started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Set language using TranslationService
        try {
            require_once __DIR__ . '/../helpers/translations.php';
            
            if (function_exists('setLanguage')) {
                setLanguage($lang);
                
                // Also update session directly for immediate effect
                $_SESSION['lang'] = $lang;
                $_SESSION['language'] = $lang;
                
                // Update CURRENT_LANGUAGE constant if defined
                if (defined('CURRENT_LANGUAGE')) {
                    // Constants can't be redefined, but session is updated
                }
                
                $this->apiResponse([
                    'success' => true, 
                    'language' => $lang,
                    'message' => 'Language changed successfully'
                ]);
            } else {
                // Fallback: set session directly
                $_SESSION['lang'] = $lang;
                $_SESSION['language'] = $lang;
                $this->apiResponse([
                    'success' => true, 
                    'language' => $lang,
                    'message' => 'Language changed successfully (fallback)'
                ]);
            }
        } catch (\Exception $e) {
            \App\Core\Logger::error('Language change error: ' . $e->getMessage());
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.language_change_failed', [], 500);
        }
    }
    
    public function createZone() {
        if (!$this->checkPermissionOrFail('tables.manage')) {
            return;
        }
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_request', [], 405);
            return;
        }
        
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $name = trim($input['name'] ?? '');
            $description = trim($input['description'] ?? '');
            
            if (empty($name)) {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.warning.missing_fields', [], 400);
                return;
            }
            
            $zoneService = \App\Core\DependencyFactory::getZoneService();
            $result = $zoneService->createZone([
                'name' => $name,
                'description' => $description
            ]);
            
            if ($result) {
                $this->toastNotificationService->sendApiResponse('success', 'notifications.success.zone_added', [], 200);
            } else {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.duplicate_entry', [], 400);
            }
        } catch (\Exception $e) {
            \App\Core\Logger::error("Error in createZone: " . $e->getMessage());
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.create_failed', [], 500);
        }
    }
    
    public function updateZone() {
        if (!$this->checkPermissionOrFail('tables.manage')) {
            return;
        }
        
        if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_request', [], 405);
            return;
        }
        
        try {
            $queryParams = \App\Core\RequestParser::getQueryParams();
            $zoneId = $queryParams['id'] ?? '';
            if (empty($zoneId)) {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
                return;
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            $name = trim($input['name'] ?? '');
            $description = trim($input['description'] ?? '');
            
            if (empty($name)) {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.warning.missing_fields', [], 400);
                return;
            }
            
            $zoneService = \App\Core\DependencyFactory::getZoneService();
            $result = $zoneService->updateZone($zoneId, [
                'name' => $name,
                'description' => $description
            ]);
            
            if ($result) {
                $this->toastNotificationService->sendApiResponse('success', 'notifications.success.zone_updated', [], 200);
            } else {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.update_failed', [], 500);
            }
        } catch (\Exception $e) {
            \App\Core\Logger::error("Error in updateZone: " . $e->getMessage());
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.update_failed', [], 500);
        }
    }
    
    public function deleteZone() {
        if (!$this->checkPermissionOrFail('tables.manage')) {
            return;
        }
        
        if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_request', [], 405);
            return;
        }
        
        try {
            $queryParams = \App\Core\RequestParser::getQueryParams();
            $zoneId = $queryParams['id'] ?? '';
            if (empty($zoneId)) {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
                return;
            }
            
            $zoneService = \App\Core\DependencyFactory::getZoneService();
            $result = $zoneService->deleteZone($zoneId);
            
            if ($result) {
                $this->toastNotificationService->sendApiResponse('success', 'notifications.success.zone_deleted', [], 200);
            } else {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.delete_failed', [], 500);
            }
        } catch (\Exception $e) {
            \App\Core\Logger::error("Error in deleteZone: " . $e->getMessage());
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.delete_failed', [], 500);
        }
    }
    
    /**
     * Get translation for a key (API endpoint)
     */
    public function translate() {
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $key = $queryParams['key'] ?? '';
        
        if (empty($key)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.warning.missing_fields', [], 400);
            return;
        }
        
        $translation = $this->toastNotificationService->translate($key);
        
        $this->apiResponse([
            'success' => true,
            'translation' => $translation,
            'key' => $key
        ]);
    }
    
    /**
     * Report client-side errors
     * POST /api/errors/report
     */
    public function reportError() {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!$data || !isset($data['message'])) {
                \App\Core\Logger::warning("Invalid error data received in reportError", [
                    'raw_data' => file_get_contents('php://input'),
                    'decoded_data' => $data
                ]);
                return $this->jsonResponse(['error' => true, 'message' => 'Invalid error data'], 400);
            }
            
            // Save to database
            try {
                $errorLogService = \App\Core\DependencyFactory::getJavaScriptErrorLogService();
                $result = $errorLogService->logError($data);
                
                if ($result === false) {
                    \App\Core\Logger::error("Failed to save JavaScript error to database", [
                        'error_data' => $data,
                        'result' => $result
                    ]);
                } else {
                    \App\Core\Logger::debug("JavaScript error saved to database", [
                        'error_id' => $result,
                        'message' => $data['message'] ?? 'unknown'
                    ]);
                }
            } catch (\Exception $dbException) {
                \App\Core\Logger::error("Database error while saving JavaScript error: " . $dbException->getMessage(), [
                    'error_data' => $data,
                    'exception' => get_class($dbException),
                    'trace' => $dbException->getTraceAsString()
                ]);
                // Continue to file logging even if database fails
            }
            
            // Also log to file for backup
            try {
                $loggerService = \App\Core\DependencyFactory::getLoggerService();
                $loggerService->error('Client-side error: ' . ($data['message'] ?? 'Unknown error'), [
                    'filename' => $data['filename'] ?? 'unknown',
                    'lineno' => $data['lineno'] ?? 0,
                    'colno' => $data['colno'] ?? 0,
                    'stack' => $data['stack'] ?? '',
                    'type' => $data['type'] ?? 'javascript_error',
                    'url' => $data['url'] ?? 'unknown',
                    'userAgent' => $data['userAgent'] ?? 'unknown',
                    'userId' => $data['userId'] ?? 'guest',
                    'context' => $data['context'] ?? []
                ]);
            } catch (\Exception $fileLogException) {
                // Even file logging failed, log to error_log as last resort
                error_log("Failed to log JavaScript error to file: " . $fileLogException->getMessage());
                error_log("Error data: " . json_encode($data));
            }
            
            return $this->jsonResponse(['success' => true, 'message' => 'Error reported']);
        } catch (\Exception $e) {
            \App\Core\Logger::error("Critical error in reportError: " . $e->getMessage(), [
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            // Also log to error_log as fallback
            error_log("Critical error in reportError: " . $e->getMessage());
            return $this->jsonResponse(['error' => true, 'message' => 'Failed to report error'], 500);
        }
    }
    
    /**
     * Resolve multiple errors
     * POST /api/errors/resolve
     */
    public function resolveErrors() {
        if (!$this->hasPermission('settings.view')) {
            return $this->jsonResponse(['error' => true, 'message' => 'Unauthorized'], 401);
        }
        
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $errorIds = $data['error_ids'] ?? [];
            
            if (empty($errorIds)) {
                return $this->jsonResponse(['error' => true, 'message' => 'No error IDs provided'], 400);
            }
            
            $unifiedErrorLogService = \App\Core\DependencyFactory::getUnifiedErrorLogService();
            $userId = $_SESSION['user_id'] ?? 'system';
            $count = $unifiedErrorLogService->resolveErrors($errorIds, $userId);
            
            return $this->jsonResponse(['success' => true, 'resolved_count' => $count]);
        } catch (\Exception $e) {
            \App\Core\Logger::error("Error in resolveErrors: " . $e->getMessage());
            return $this->jsonResponse(['error' => true, 'message' => 'Failed to resolve errors'], 500);
        }
    }
    
    /**
     * Delete all resolved errors
     * POST /api/errors/delete-resolved
     */
    public function deleteResolvedErrors() {
        if (!$this->hasPermission('settings.view')) {
            return $this->jsonResponse(['error' => true, 'message' => 'Unauthorized'], 401);
        }
        
        try {
            $unifiedErrorLogService = \App\Core\DependencyFactory::getUnifiedErrorLogService();
            $count = $unifiedErrorLogService->deleteResolvedErrors();
            
            return $this->jsonResponse(['success' => true, 'deleted_count' => $count]);
        } catch (\Exception $e) {
            \App\Core\Logger::error("Error in deleteResolvedErrors: " . $e->getMessage());
            return $this->jsonResponse(['error' => true, 'message' => 'Failed to delete errors'], 500);
        }
    }
    
    /**
     * Delete all errors (both resolved and unresolved)
     * POST /api/errors/delete-all
     */
    public function deleteAllErrors() {
        if (!$this->hasPermission('settings.view')) {
            return $this->jsonResponse(['error' => true, 'message' => 'Unauthorized'], 401);
        }
        
        try {
            $unifiedErrorLogService = \App\Core\DependencyFactory::getUnifiedErrorLogService();
            $count = $unifiedErrorLogService->deleteAllErrors();
            
            return $this->jsonResponse(['success' => true, 'deleted_count' => $count]);
        } catch (\Exception $e) {
            \App\Core\Logger::error("Error in deleteAllErrors: " . $e->getMessage());
            return $this->jsonResponse(['error' => true, 'message' => 'Failed to delete all errors'], 500);
        }
    }

    /**
     * Smart cleanup: remove noise, old resolved, apply retention
     * POST /api/errors/smart-cleanup
     */
    public function smartCleanup() {
        if (!$this->hasPermission('settings.view')) {
            return $this->jsonResponse(['error' => true, 'message' => 'Unauthorized'], 401);
        }
        
        try {
            $phpErrorLogService = \App\Core\DependencyFactory::getPhpErrorLogService();
            $result = $phpErrorLogService->smartCleanup();
            
            return $this->jsonResponse(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            \App\Core\Logger::error("Error in smartCleanup: " . $e->getMessage());
            return $this->jsonResponse(['error' => true, 'message' => 'Cleanup failed: ' . $e->getMessage()], 500);
        }
    }
    
    /**
     * Generate CAPTCHA challenge
     * Public endpoint - no authentication required
     * GET: api/contact/captcha
     */
    public function generateContactCaptcha() {
        try {
            \App\Core\SessionManager::ensureSession();
            
            // Generate math CAPTCHA: one single-digit (1-9), one double-digit (10-99)
            // Randomly decide which side is single/double digit
            $firstIsSingle = rand(0, 1) === 1;
            
            if ($firstIsSingle) {
                $num1 = rand(1, 9);      // Single digit
                $num2 = rand(10, 99);    // Double digit
            } else {
                $num1 = rand(10, 99);    // Double digit
                $num2 = rand(1, 9);      // Single digit
            }
            $answer = $num1 + $num2;
            
            // Store answer in session
            \App\Core\SessionManager::set('contact_captcha_answer', $answer);
            \App\Core\SessionManager::set('contact_captcha_time', time());
            
            return $this->jsonResponse([
                'success' => true,
                'question' => "{$num1} + {$num2} = ?",
                'num1' => $num1,
                'num2' => $num2
            ], 200);
            
        } catch (\Exception $e) {
            \App\Core\Logger::error("Error in generateContactCaptcha: " . $e->getMessage());
            return $this->jsonResponse([
                'success' => false,
                'message' => 'CAPTCHA oluşturulamadı.'
            ], 500);
        }
    }
    
    /**
     * Submit contact form
     * Public endpoint - no authentication required
     */
    public function submitContactForm() {
        try {
            // Get JSON input or POST data
            $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

            // --- Field-name harmonisation ---------------------------------
            // The React landing sends `name` + `subject`; older clients send
            // `full_name`. Normalise them here so the service only ever sees
            // the canonical column names and nothing is silently dropped.
            if (!isset($input['full_name']) && isset($input['name'])) {
                $input['full_name'] = $input['name'];
            }
            // `subject` isn't a real column — prepend it to the message so the
            // admin reviewer still sees which topic the visitor picked.
            if (!empty($input['subject']) && is_string($input['subject'])) {
                $topicMap = [
                    'general' => 'Genel Bilgi',
                    'demo'    => 'Demo Talebi',
                    'pricing' => 'Fiyatlandırma',
                    'support' => 'Teknik Destek',
                ];
                $topic  = $topicMap[$input['subject']] ?? $input['subject'];
                $input['message'] = '[' . $topic . ']' . "\n\n" . ($input['message'] ?? '');
                unset($input['subject']);
            }

            // --- CAPTCHA (opt-in) -----------------------------------------
            // The landing React form doesn't ship a visible CAPTCHA, so we
            // only enforce it when the client actually requested one. This
            // keeps the /api/contact/captcha flow alive for clients that DO
            // use it (older legacy page), but stops silently 400'ing every
            // modern submission.
            \App\Core\SessionManager::ensureSession();
            $captchaAnswer = \App\Core\SessionManager::get('contact_captcha_answer');
            $captchaTime   = \App\Core\SessionManager::get('contact_captcha_time');
            if ($captchaAnswer !== null && $captchaTime !== null) {
                $userAnswer = isset($input['captcha_answer']) ? (int)$input['captcha_answer'] : null;
                if ((time() - (int)$captchaTime) > 300
                    || $userAnswer === null
                    || $userAnswer !== (int)$captchaAnswer) {
                    \App\Core\SessionManager::remove('contact_captcha_answer');
                    \App\Core\SessionManager::remove('contact_captcha_time');
                    return $this->jsonResponse([
                        'success' => false,
                        'message' => 'CAPTCHA doğrulaması başarısız. Lütfen tekrar deneyin.',
                        'captcha_error' => true
                    ], 400);
                }
                \App\Core\SessionManager::remove('contact_captcha_answer');
                \App\Core\SessionManager::remove('contact_captcha_time');
                unset($input['captcha_answer']);
            }

            // --- Simple abuse throttle ------------------------------------
            // Prevents a no-CAPTCHA flood from a single IP: max 5 submissions
            // per 10 minutes. Logged via the session so it survives short
            // bursts without needing a dedicated table.
            $throttleKey = 'contact_submit_log';
            $now         = time();
            $log         = \App\Core\SessionManager::get($throttleKey);
            $log         = is_array($log) ? $log : [];
            $log         = array_values(array_filter($log, static fn($t) => ($now - (int)$t) < 600));
            if (count($log) >= 5) {
                return $this->jsonResponse([
                    'success' => false,
                    'message' => 'Çok fazla istek. Lütfen birkaç dakika sonra tekrar deneyin.'
                ], 429);
            }
            $log[] = $now;
            \App\Core\SessionManager::set($throttleKey, $log);

            // Submit form
            $result = $this->contactFormService->submitForm($input);
            
            if ($result['success']) {
                return $this->jsonResponse([
                    'success' => true,
                    'message' => $result['message']
                ], 200);
            } else {
                return $this->jsonResponse([
                    'success' => false,
                    'message' => $result['message'],
                    'errors' => $result['errors'] ?? []
                ], 400);
            }
            
        } catch (\Exception $e) {
            \App\Core\Logger::error("Error in submitContactForm: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Bir hata oluştu. Lütfen daha sonra tekrar deneyin.'
            ], 500);
        }
    }

    /**
     * Merge table orders into a single active session order for customer view
     * @param array $orders Orders sorted by created_at DESC
     * @return array Merged orders array (0 or 1 item)
     */
    private function mergeOrdersForCustomerSession(array $orders): array {
        if (empty($orders)) {
            return [];
        }

        $activeOrders = $this->getActiveSessionOrders($orders);
        if (empty($activeOrders)) {
            return [];
        }

        // Sort active orders oldest -> newest for stable display
        usort($activeOrders, function($a, $b) {
            $aTime = strtotime($a['created_at'] ?? '') ?: 0;
            $bTime = strtotime($b['created_at'] ?? '') ?: 0;
            return $aTime <=> $bTime;
        });

        $items = [];
        $notes = [];
        $orderIds = [];
        $totalAmount = 0.0;
        $createdAt = null;
        $updatedAt = null;

        foreach ($activeOrders as $order) {
            $orderIds[] = $order['order_id'] ?? null;
            $totalAmount += floatval($order['total_amount'] ?? 0);

            if (!empty($order['customer_note'])) {
                $notes[] = trim($order['customer_note']);
            }

            $orderItems = $order['items'] ?? [];
            if (!empty($orderItems)) {
                $items = array_merge($items, $orderItems);
            }

            $orderCreatedAt = $order['created_at'] ?? null;
            $orderUpdatedAt = $order['updated_at'] ?? null;
            if ($orderCreatedAt && (!$createdAt || strtotime($orderCreatedAt) < strtotime($createdAt))) {
                $createdAt = $orderCreatedAt;
            }
            if ($orderUpdatedAt && (!$updatedAt || strtotime($orderUpdatedAt) > strtotime($updatedAt))) {
                $updatedAt = $orderUpdatedAt;
            }
        }

        $mergedStatus = $this->resolveMergedOrderStatus($activeOrders);
        $mergedOrderId = $orderIds[0] ?? ($activeOrders[0]['order_id'] ?? null);
        $mergedIsPaid = 0;
        foreach ($activeOrders as $o) {
            if (!empty($o['is_paid']) && $o['is_paid'] != '0') {
                $mergedIsPaid = 1;
                break;
            }
        }

        return [[
            'order_id' => $mergedOrderId,
            'table_id' => $activeOrders[0]['table_id'] ?? null,
            'table_name' => $activeOrders[0]['table_name'] ?? null,
            'status' => $mergedStatus,
            'total_amount' => round($totalAmount, 2),
            'customer_note' => !empty($notes) ? implode(' | ', array_unique($notes)) : '',
            'created_at' => $createdAt ?? ($activeOrders[0]['created_at'] ?? null),
            'updated_at' => $updatedAt ?? ($activeOrders[0]['updated_at'] ?? null),
            'items' => $items,
            'is_paid' => $mergedIsPaid,
            'order_ids' => array_values(array_filter($orderIds)),
            'order_count' => count($activeOrders)
        ]];
    }

    /**
     * Get active session orders from a table (newest-first input)
     * @param array $orders
     * @return array Active session orders
     */
    private function getActiveSessionOrders(array $orders): array {
        $active = [];
        foreach ($orders as $order) {
            if ($this->orderEndsSession($order)) {
                // If the newest order ends a session, there is no active session
                if (empty($active)) {
                    return [];
                }
                break;
            }
            $active[] = $order;
        }
        return $active;
    }

    /**
     * Determine if order ends a session (müşteri ödeme yapana kadar siparişler görünsün)
     * @param array $order
     * @return bool
     */
    private function orderEndsSession(array $order): bool {
        $isPaid = !empty($order['is_paid']) && $order['is_paid'] != '0';
        return $isPaid;
    }

    /**
     * Resolve merged status from multiple orders
     * @param array $orders
     * @return string
     */
    private function resolveMergedOrderStatus(array $orders): string {
        $priority = [
            'PENDING' => 1,
            'CONFIRMED' => 2,
            'PREPARING' => 3,
            'READY' => 4,
            'SERVED' => 5,
            'CANCELLED' => 6
        ];

        $minScore = 999;
        $resolved = 'PENDING';

        foreach ($orders as $order) {
            $status = strtoupper($order['status'] ?? 'PENDING');
            $score = $priority[$status] ?? 1;
            if ($score < $minScore) {
                $minScore = $score;
                $resolved = $status;
            }
        }

        return $resolved;
    }
    
    /**
     * Update customer session location (called from QR menu JS)
     * POST /api/session/update-location
     */
    public function updateSessionLocation() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->apiResponse(['success' => false, 'error' => 'Method not allowed'], 405);
            return;
        }
        
        $featureService = \App\Core\DependencyFactory::getFeatureService();
        if (!$featureService->isEnabled('customer_presence_tracking')) {
            $this->apiResponse(['success' => false, 'error' => 'Feature disabled']);
            return;
        }
        
        $requestData = \App\Core\RequestParser::getRequestData();
        $latitude = floatval($requestData['latitude'] ?? 0);
        $longitude = floatval($requestData['longitude'] ?? 0);
        $sessionId = $_SESSION['customer_session_id'] ?? null;
        
        if (!$sessionId || (round((float)$latitude, 6) === 0.0 && round((float)$longitude, 6) === 0.0)) {
            $this->apiResponse(['success' => false, 'error' => 'Invalid data']);
            return;
        }
        
        try {
            $qrService = \App\Core\DependencyFactory::getQRCodeSecurityService();
            $result = $qrService->updateSessionLocation($sessionId, $latitude, $longitude);
            $this->apiResponse(['success' => $result]);
        } catch (\Exception $e) {
            $this->apiResponse(['success' => false, 'error' => $e->getMessage()]);
        }
    }
    
    /**
     * Check customer session status (activity, timeout, etc.)
     * GET /api/session/check
     */
    public function checkSession() {
        $sessionId = $_SESSION['customer_session_id'] ?? null;
        $tableId = $_SESSION['customer_table_id'] ?? null;
        
        if (!$sessionId) {
            $this->apiResponse([
                'success' => true,
                'session_active' => false,
                'reason' => 'NO_SESSION'
            ]);
            return;
        }
        
        // Virtual sessions are always active (not persisted in DB)
        if (str_starts_with($sessionId, 'virtual_')) {
            $this->apiResponse([
                'success' => true,
                'session_active' => true,
                'has_orders' => false,
                'session_id' => $sessionId,
                'table_id' => $tableId
            ]);
            return;
        }
        
        try {
            // ALWAYS update last_activity on every session check call.
            // If the browser is polling this endpoint, the user is present.
            $customerSessionRepo = \App\Core\DependencyFactory::getCustomerSessionRepository();
            $customerSessionRepo->updateLastActivity($sessionId);
            
            $qrService = \App\Core\DependencyFactory::getQRCodeSecurityService();
            $status = $qrService->checkSessionActivity($sessionId, 30); // 30 min inactivity (generous)
            
            // If session ended (payment processed), clear PHP session tokens
            if (($status['reason'] ?? null) === 'SESSION_ENDED') {
                unset($_SESSION['customer_qr_token']);
                unset($_SESSION['customer_session_token']);
                unset($_SESSION['customer_session_id']);
                unset($_SESSION['customer_table_id']);
                unset($_SESSION['cart']);
                if (isset($_COOKIE['customer_qr_token'])) {
                    setcookie('customer_qr_token', '', time() - 3600, '/', '', false, true);
                }
            }
            
            $this->apiResponse([
                'success' => true,
                'session_active' => $status['active'] ?? false,
                'reason' => $status['reason'] ?? null,
                'has_orders' => $status['has_orders'] ?? false,
                'remaining_seconds' => $status['remaining_seconds'] ?? null,
                'order_count' => $status['order_count'] ?? 0,
                'session_id' => $sessionId,
                'table_id' => $tableId
            ]);
        } catch (\Exception $e) {
            $this->apiResponse([
                'success' => true,
                'session_active' => true, // fail-open
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Continue/extend customer session
     * POST /api/session/continue
     */
    public function continueSession() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->apiResponse(['success' => false, 'error' => 'Method not allowed'], 405);
            return;
        }
        
        $sessionId = $_SESSION['customer_session_id'] ?? null;
        
        if (!$sessionId) {
            $this->apiResponse(['success' => false, 'error' => 'No session']);
            return;
        }
        
        try {
            $customerSessionService = \App\Core\DependencyFactory::getCustomerSessionService();
            // Extend session by 60 minutes
            $result = $customerSessionService->extendSession($sessionId, 60);
            
            // Also update last activity
            $qrService = \App\Core\DependencyFactory::getQRCodeSecurityService();
            $qrService->validateCustomerSession($_SESSION['customer_session_token'] ?? '');
            
            $this->apiResponse(['success' => true, 'extended' => $result]);
        } catch (\Exception $e) {
            $this->apiResponse(['success' => false, 'error' => $e->getMessage()]);
        }
    }
}
