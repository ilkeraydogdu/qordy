<?php
namespace App\Controllers;

use App\Core\Helpers\ConstantsHelper;

// Controller base class, DependencyFactory, and helpers are autoloaded via HelperLoader

class PosController extends \App\Core\Controller {
    protected $orderService;
    protected $tableService;
    protected $categoryService;
    protected $menuItemService;
    protected $orderItemService;
    protected $paymentService;
    protected $paymentTransactionService;
    protected $receiptService;
    protected $zoneService;

    public function __construct() {
        parent::__construct(); // Initialize auth and other services
        $this->orderService = \App\Core\DependencyFactory::getOrderService();
        $this->tableService = \App\Core\DependencyFactory::getTableService();
        $this->categoryService = \App\Core\DependencyFactory::getCategoryService();
        $this->menuItemService = \App\Core\DependencyFactory::getMenuItemService();
        $this->orderItemService = \App\Core\DependencyFactory::getOrderItemService();
        $this->paymentService = \App\Core\DependencyFactory::getPaymentService();
        $this->paymentTransactionService = \App\Core\DependencyFactory::getPaymentTransactionService();
        $this->receiptService = \App\Core\DependencyFactory::getReceiptService();
        $this->zoneService = \App\Core\DependencyFactory::getZoneService();
    }

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
                    \App\Core\Logger::debug('Tenant context set in PosController::dashboard', [
                        'subdomain' => $subdomain,
                        'tenant_id' => \App\Core\TenantContext::getId()
                    ]);
                }
            } catch (\Exception $e) {
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::error('Failed to set tenant context in PosController::dashboard', [
                        'error' => $e->getMessage(),
                        'subdomain' => $subdomain
                    ]);
                }
            }
        }
        
        $this->ensureTenantContext();
        $this->requirePermission('pos.view');
        
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
        if (!$isSuperAdmin) {
            try {
                $customerId = $_SESSION['customer_id'] ?? \App\Core\SessionManager::get('customer_id') ?? null;
                if ($customerId) {
                    $subscriptionService = \App\Core\DependencyFactory::getSubscriptionService();
                    $subscription = $subscriptionService->getCustomerSubscription($customerId);
                    
                    // If no active subscription, show error and redirect
                    if (!$subscription || empty($subscription['status']) || strtoupper($subscription['status']) !== 'ACTIVE') {
                        $this->toastNotificationService->setFlash('warning', 'POS sistemine erişmek için aktif bir paket aboneliğiniz olmalıdır.');
                        header('Location: ' . BASE_URL . '/customer/packages');
                        exit;
                    }
                }
            } catch (\Exception $e) {
                // If subscription check fails, log but allow access (graceful degradation)
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::error('POS subscription check failed', [
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }

        // Get ALL tables (including FREE ones) for POS dashboard
        $tables = $this->tableService->getAllTables();
        $tablesGrouped = $this->tableService->getTablesGroupedByZone();

        // Try to get zones, but handle if table doesn't exist
        try {
            $zones = $this->zoneService->getAllZones();
        } catch (\Exception $e) {
            $zones = [];
        }

        // CRITICAL: Enrich tables with active order data (same logic as getTablesGrouped API)
        // Without this, initial page load shows all tables as empty/FREE
        $allTableIds = [];
        foreach ($tablesGrouped as $zoneName => $zoneTables) {
            foreach ($zoneTables as $t) {
                $tid = $t['table_id'] ?? '';
                if (!empty($tid)) {
                    $allTableIds[] = $tid;
                }
            }
        }
        
        $activeOrdersByTableId = [];
        if (!empty($allTableIds)) {
            $activeOrdersByTableId = $this->orderService->getActiveOrdersByTableIds($allTableIds);
        }
        
        // Enrich both $tables and $tablesGrouped with order data
        foreach ($tablesGrouped as $zoneName => &$zoneTables) {
            foreach ($zoneTables as &$t) {
                $tid = $t['table_id'] ?? '';
                $activeOrders = $activeOrdersByTableId[$tid] ?? [];
                $activeOrderCount = count($activeOrders);
                $tableTotalAmount = 0;
                foreach ($activeOrders as $order) {
                    $tableTotalAmount += floatval($order['total_amount'] ?? 0);
                }
                $t['total_amount'] = $tableTotalAmount;
                $t['active_order_count'] = $activeOrderCount;
                
                // Fix status based on actual orders
                if ($activeOrderCount > 0) {
                    $t['status'] = 'OCCUPIED';
                } else {
                    $dbStatus = $t['status'] ?? 'FREE';
                    $t['status'] = 'FREE';
                    if ($dbStatus === 'PAYMENT_PENDING' || $dbStatus === 'OCCUPIED') {
                        try {
                            $this->tableService->updateTableStatus($tid, 'FREE');
                        } catch (\Exception $e) {
                            \App\Core\Logger::warning('PosController: table status update to FREE failed', [
                                'table_id' => $tid,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                }
            }
            unset($t);
        }
        unset($zoneTables);
        
        // Also enrich the flat $tables array
        foreach ($tables as &$t) {
            $tid = $t['table_id'] ?? '';
            $activeOrders = $activeOrdersByTableId[$tid] ?? [];
            $activeOrderCount = count($activeOrders);
            $tableTotalAmount = 0;
            foreach ($activeOrders as $order) {
                $tableTotalAmount += floatval($order['total_amount'] ?? 0);
            }
            $t['total_amount'] = $tableTotalAmount;
            $t['active_order_count'] = $activeOrderCount;
            if ($activeOrderCount > 0) {
                $t['status'] = 'OCCUPIED';
            } elseif (($t['status'] ?? 'FREE') !== 'FREE') {
                $t['status'] = 'FREE';
            }
        }
        unset($t);

        $categories = $this->categoryService->getAllCategories();
        $menuItems = $this->menuItemService->getAvailableMenuItems();

        // Check if user is cashier
        $cashierRole = ConstantsHelper::getRole('CASHIER');
        $isCashier = hasRole($cashierRole) || hasRole('ROLE_' . $cashierRole);

        // Generate CSRF token
        $csrfToken = generateCSRFToken();

        // Get system settings for service charge rate
        $settingsService = \App\Core\DependencyFactory::getSystemSettingsService();
        $settings = $settingsService->getSettings();
        $serviceChargeRate = floatval($settings['service_charge_rate'] ?? 0);
        
        // Get order edit approval settings - use requiresApproval for role + business awareness
        $approvalService = \App\Core\DependencyFactory::getOrderEditApprovalService();
        $businessId = \App\Core\TenantResolver::resolve();
        $requiresApprovalForOrderEdit = $approvalService->requiresApproval($_SESSION['user_id'] ?? '', $businessId);
        // İşletme yöneticisi: session role BUSINESS_MANAGER ise (müşteri girişi) her zaman yönetici sayılır → buton görünürlüğü manager toggle'a bağlı
        $sessionRole = strtoupper(trim($_SESSION['role'] ?? \App\Core\SessionManager::get('role') ?? ''));
        if ($sessionRole === 'BUSINESS_MANAGER') {
            $requiresApprovalForOrderEdit = false;
        }
        $approvalRole = $settings['order_edit_approval_role'] ?? 'MANAGER';
        $staffShowDeleteReduceButtons = !isset($settings['staff_show_delete_reduce_buttons']) || $settings['staff_show_delete_reduce_buttons'] === '' || $settings['staff_show_delete_reduce_buttons'] === '1' || $settings['staff_show_delete_reduce_buttons'] === 1;
        $managerShowDeleteReduceButtons = !isset($settings['manager_show_delete_reduce_buttons']) || $settings['manager_show_delete_reduce_buttons'] === '' || $settings['manager_show_delete_reduce_buttons'] === '1' || $settings['manager_show_delete_reduce_buttons'] === 1;
        $orderEditApprovalEnabled = (isset($settings['order_edit_requires_approval']) && ($settings['order_edit_requires_approval'] === '1' || $settings['order_edit_requires_approval'] === 1));

        $data = [
            'tables' => $tables ?: [],
            'tables_grouped' => $tablesGrouped ?: [],
            'zones' => $zones ?: [],
            'categories' => $categories ?: [],
            'menu_items' => $menuItems ?: [],
            'is_cashier' => $isCashier,
            'csrf_token' => $csrfToken,
            'service_charge_rate' => $serviceChargeRate,
            'requiresApprovalForOrderEdit' => $requiresApprovalForOrderEdit,
            'staffShowDeleteReduceButtons' => $staffShowDeleteReduceButtons,
            'managerShowDeleteReduceButtons' => $managerShowDeleteReduceButtons,
            'orderEditApprovalEnabled' => $orderEditApprovalEnabled,
            'approvalRole' => $approvalRole,
            'is_super_admin' => $isSuperAdmin
        ];

        $this->view('pos/dashboard', $data);
    }

    /**
     * Zone bazlı gruplandırılmış masalar API (kasiyer için)
     */
    public function getTablesGrouped() {
        if (!$this->checkPermissionOrFail('pos.view')) {
            return;
        }

        $tablesGrouped = $this->tableService->getTablesGroupedByZone();

        // Try to get zones, but handle if table doesn't exist
        try {
            $zones = $this->zoneService->getAllZones();
        } catch (\Exception $e) {
            $zones = [];
        }

        // PERFORMANCE: Batch-load active orders for ALL tables in a single query
        // This eliminates the N+1 problem where frontend was making per-table API calls
        $allTableIds = [];
        foreach ($tablesGrouped as $zoneName => $tables) {
            foreach ($tables as $table) {
                $tableId = $table['table_id'] ?? '';
                if (!empty($tableId)) {
                    $allTableIds[] = $tableId;
                }
            }
        }
        
        $activeOrdersByTableId = [];
        if (!empty($allTableIds)) {
            $activeOrdersByTableId = $this->orderService->getActiveOrdersByTableIds($allTableIds);
        }

        // Calculate statistics for each zone
        $result = [
            'zones' => [],
            'total_tables' => 0,
            'occupied_tables' => 0,
            'free_tables' => 0,
            'payment_pending_tables' => 0
        ];

        foreach ($tablesGrouped as $zoneName => $tables) {
            $occupiedCount = 0;
            $freeCount = 0;
            $paymentPendingCount = 0;

            foreach ($tables as &$table) {
                $tableId = $table['table_id'] ?? '';
                $activeOrders = $activeOrdersByTableId[$tableId] ?? [];
                
                // Calculate total amount from active orders
                $tableTotalAmount = 0;
                $activeOrderCount = count($activeOrders);
                foreach ($activeOrders as $order) {
                    $tableTotalAmount += floatval($order['total_amount'] ?? 0);
                }
                
                // Include order data in table response
                $table['total_amount'] = $tableTotalAmount;
                $table['active_order_count'] = $activeOrderCount;
                
                // Determine real status based on ACTUAL active orders (not database status)
                // This fixes the bug where PAYMENT_PENDING tables had no real orders
                $dbStatus = $table['status'] ?? 'FREE';
                if ($activeOrderCount > 0) {
                    $table['status'] = 'OCCUPIED';
                    $occupiedCount++;
                } else {
                    // No active orders = table is free, regardless of database status
                    $table['status'] = 'FREE';
                    $freeCount++;
                    
                    // Also fix the database if status was stuck as PAYMENT_PENDING or OCCUPIED
                    if ($dbStatus === 'PAYMENT_PENDING' || $dbStatus === 'OCCUPIED') {
                        try {
                            $this->tableService->updateTableStatus($tableId, 'FREE');
                        } catch (\Exception $e) {
                            // Silent fail - don't break the API response
                        }
                    }
                }
            }
            unset($table);

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
                'tables' => $tables,
                'occupied_count' => $occupiedCount,
                'total_count' => count($tables),
                'free_count' => $freeCount,
                'payment_pending_count' => $paymentPendingCount
            ];

            $result['total_tables'] += count($tables);
            $result['occupied_tables'] += $occupiedCount;
            $result['free_tables'] += $freeCount;
            $result['payment_pending_tables'] += $paymentPendingCount;
        }

        $this->apiResponse($result);
    }

    public function orders() {
        $this->requirePermission('orders.view');

        $queryParams = \App\Core\RequestParser::getQueryParams();
        $tableId = $queryParams['table_id'] ?? '';

        if (!empty($tableId)) {
            $orders = $this->orderService->getOrdersByTable($tableId);
        } else {
            $pendingStatus = ConstantsHelper::getOrderStatus('PENDING');
            $orders = $this->orderService->getOrdersByStatus($pendingStatus);
        }

        $data = [
            'orders' => $orders,
            'table_id' => $tableId
        ];

        $this->view('pos/orders', $data);
    }

    public function createOrder() {
        // CRITICAL: Ensure tenant context is set
        $this->ensureTenantContext();
        
        if (!$this->checkPermissionOrFail('orders.create')) {
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Handle both JSON and form data
            $input = json_decode(file_get_contents('php://input'), true);
            if ($input === null) {
                $input = $_POST;
            }

            // CSRF token validation is handled by CSRFMiddleware before reaching this controller
            // No need to check again here - token is validated in header (X-CSRF-Token) for JSON requests

            // Validate input data
            $validationRules = [
                'table_id' => 'required|string|min:1|max:50',
                'items' => 'required|array|min:1',
                'customer_note' => 'string|max:500'
            ];

            $errors = validateInputs($input, $validationRules);
            
            // Validate items array separately (nested validation)
            if (empty($errors) && isset($input['items']) && is_array($input['items'])) {
                foreach ($input['items'] as $index => $item) {
                    if (empty($item['menu_item_id'])) {
                        $errors['items'][$index][] = 'Menu item ID is required';
                    }
                    if (!isset($item['quantity']) || !is_numeric($item['quantity']) || $item['quantity'] < 1) {
                        $errors['items'][$index][] = 'Quantity must be at least 1';
                    }
                    if (isset($item['price']) && (!is_numeric($item['price']) || $item['price'] < 0)) {
                        $errors['items'][$index][] = 'Price must be a positive number';
                    }
                }
            }
            
            if (!empty($errors)) {
                $this->apiResponse([
                    'success' => false,
                    'error' => 'Doğrulama hatası',
                    'errors' => $errors
                ], 400);
                return;
            }

            $tableId = $input['table_id'] ?? '';
            $items = is_array($input['items'] ?? null) ? $input['items'] : json_decode($input['items'] ?? '[]', true);
            $customerNote = sanitizeInput($input['customer_note'] ?? '');
            $staffName = $_SESSION['username'] ?? '';

            if (empty($tableId) || empty($items) || !is_array($items)) {
                $this->apiResponse([
                    'success' => false,
                    'error' => 'Eksik alanlar: masa ID ve ürünler gerekli'
                ], 400);
                return;
            }

            // Tenant fallback: when TenantContext not set but user has tableId, derive from table
            // Security: only set if table's business matches user's business (waiter/staff belongs to that business)
            if (!\App\Core\TenantContext::isSet() && !empty($tableId)) {
                $tableUnscoped = $this->tableService->getTableByIdUnscoped($tableId);
                if ($tableUnscoped) {
                    $tableBusinessId = $tableUnscoped['tenant_id'] ?? null;
                    $userBusinessId = \App\Core\TenantResolver::resolve();
                    if (!$userBusinessId && !empty($_SESSION['user_id'])) {
                        $userService = \App\Core\DependencyFactory::getUserService();
                        $user = $userService->findByUserId($_SESSION['user_id']);
                        $userBusinessId = $user['tenant_id'] ?? null;
                    }
                    if ($tableBusinessId && $userBusinessId === $tableBusinessId) {
                        try {
                            $customerService = \App\Core\DependencyFactory::getCustomerService();
                            $customer = $customerService->getById($tableBusinessId);
                            if ($customer) {
                                \App\Core\TenantContext::set($customer);
                            } else {
                                \App\Core\TenantContext::setId($tableBusinessId);
                            }
                        } catch (\Exception $e) {
                            \App\Core\TenantContext::setId($tableBusinessId);
                        }
                    }
                }
            }

            // CRITICAL: Verify table belongs to current tenant
            $table = $this->tableService->getTableById($tableId);
            if (!$table) {
                $this->apiResponse([
                    'success' => false,
                    'error' => 'Masa bulunamadı'
                ], 404);
                return;
            }
            
            // Check tenant isolation (unless super admin)
            if (!$this->isSuperAdmin()) {
                $tenantId = \App\Core\TenantContext::getId();
                $tableBusinessId = $table['tenant_id'] ?? null;

                if (!$tenantId || !$tableBusinessId || $tableBusinessId !== $tenantId) {
                    \App\Core\Logger::warning('PosController::createOrder - Table tenant isolation violation', [
                        'table_id' => $tableId,
                        'table_business_id' => $table['business_id'] ?? 'not_set',
                        'table_tenant_id' => $table['tenant_id'] ?? 'not_set',
                        'table_tenant_value' => $tableBusinessId,
                        'tenant_id' => $tenantId
                    ]);
                    $this->apiResponse([
                        'success' => false,
                        'error' => 'Bu masaya erişim yetkiniz bulunmamaktadır'
                    ], 403);
                    return;
                }
            }

            // CRITICAL: Check if table has active orders - if yes, add items to existing order instead of creating new one
            $activeOrders = $this->orderService->getActiveOrdersByTable($tableId);
            $existingOrder = null;
            
            // Find the most recent active order (prefer non-delivery orders)
            foreach ($activeOrders as $order) {
                $status = $order['status'] ?? '';
                if ($status !== 'SERVED' && $status !== 'CANCELLED') {
                    $existingOrder = $order;
                    break; // Use first active order found
                }
            }
            
            // If there's an existing active order, add items to it instead of creating new order
            if ($existingOrder && !empty($existingOrder['order_id'])) {
                $orderId = $existingOrder['order_id'];
                $addedItems = [];
                $failedItems = [];
                
                // Add each item to the existing order
                foreach ($items as $item) {
                    $menuItemId = $item['menu_item_id'] ?? '';
                    $quantity = intval($item['quantity'] ?? 1);
                    $note = sanitizeInput($item['note'] ?? '');
                    
                    if (empty($menuItemId) || $quantity <= 0) {
                        continue;
                    }
                    
                    // Use addItemToOrder logic
                    $menuItem = $this->menuItemService->getMenuItemById($menuItemId);
                    if (!$menuItem) {
                        $failedItems[] = $menuItemId;
                        continue;
                    }
                    
                    // Get variant_id if provided
                    $variantId = $item['variant_id'] ?? null;
                    $variantPriceModifier = 0;
                    
                    if ($variantId && !empty($menuItem['has_variants']) && $menuItem['has_variants'] == 1) {
                        $productVariantService = \App\Core\DependencyFactory::getProductVariantService();
                        $variants = $productVariantService->getActiveVariantsByProduct($menuItemId);
                        foreach ($variants as $variant) {
                            if ($variant['variant_id'] === $variantId) {
                                $variantPriceModifier = floatval($variant['price_modifier'] ?? 0);
                                break;
                            }
                        }
                    }
                    
                    $orderItemData = [
                        'order_id' => $orderId,
                        'menu_item_id' => $menuItemId,
                        'variant_id' => $variantId,
                        'quantity' => $quantity,
                        'price' => $menuItem['price'] + $variantPriceModifier,
                        'note' => $note
                    ];
                    
                    // Handle excluded ingredients
                    $excludedIngredients = $item['excluded_ingredients'] ?? [];
                    if (!empty($excludedIngredients) && is_array($excludedIngredients)) {
                        $orderItemData['excluded_ingredients'] = $excludedIngredients;
                    }
                    
                    // Handle selected extras
                    $selectedExtras = $item['selected_extras'] ?? [];
                    if (!empty($selectedExtras) && is_array($selectedExtras)) {
                        $orderItemData['selected_extras'] = $selectedExtras;
                    }
                    
                    // Aynı ürün (variant, excluded, extras aynı) varsa miktarı artır, yoksa yeni satır ekle
                    $mergeableId = $this->orderItemService->findMergeableOrderItem(
                        $orderId, $menuItemId, $variantId ?? null,
                        $excludedIngredients ?? [], $selectedExtras ?? []
                    );
                    $itemResult = false;
                    if ($mergeableId) {
                        $existingItem = $this->orderItemService->getOrderItemById($mergeableId);
                        if ($existingItem) {
                            $oldQty = intval($existingItem['quantity'] ?? 1);
                            $newQty = $oldQty + $quantity;
                            $itemResult = $this->orderItemService->updateQuantity($mergeableId, $newQty) ? $mergeableId : false;
                        }
                    }
                    if (!$itemResult) {
                        $itemResult = $this->orderItemService->createOrderItem($orderItemData);
                    }
                    
                    if ($itemResult) {
                        $addedItems[] = $menuItemId; // Hem merge hem create için - mutfak fişi için
                    }
                    // Save excluded ingredients and extras to relation tables (sadece yeni oluşturulduysa)
                    if ($itemResult && !$mergeableId) {
                        // Save excluded ingredients to order_item_ingredients table
                        if (!empty($excludedIngredients)) {
                            try {
                                $db = \App\Core\DependencyFactory::getDatabase();
                                $ingredientStmt = $db->prepare("INSERT INTO order_item_ingredients (order_item_id, ingredient_name, is_excluded) VALUES (?, ?, 1)");
                                foreach ($excludedIngredients as $ingredient) {
                                    $ingredientName = is_string($ingredient) ? $ingredient : ($ingredient['name'] ?? '');
                                    if (!empty($ingredientName)) {
                                        $ingredientStmt->execute([$itemResult, $ingredientName]);
                                    }
                                }
                            } catch (\Exception $e) {
                                \App\Core\Logger::warning('PosController: Failed to save excluded ingredients', [
                                    'order_item_id' => $itemResult,
                                    'error' => $e->getMessage()
                                ]);
                            }
                        }
                        
                        // Save selected extras to order_item_extras table
                        if (!empty($selectedExtras)) {
                            try {
                                $db = \App\Core\DependencyFactory::getDatabase();
                                $extraStmt = $db->prepare("INSERT INTO order_item_extras (order_item_id, name, price) VALUES (?, ?, ?)");
                                foreach ($selectedExtras as $extra) {
                                    $extraName = is_array($extra) ? ($extra['name'] ?? '') : $extra;
                                    $extraPrice = is_array($extra) ? floatval($extra['price'] ?? 0) : 0;
                                    if (!empty($extraName)) {
                                        $extraStmt->execute([$itemResult, $extraName, $extraPrice]);
                                    }
                                }
                            } catch (\Exception $e) {
                                \App\Core\Logger::warning('PosController: Failed to save extras', [
                                    'order_item_id' => $itemResult,
                                    'error' => $e->getMessage()
                                ]);
                            }
                        }
                    } else {
                        $failedItems[] = $menuItemId;
                    }
                }
                
                if (count($addedItems) > 0) {
                    // Recalculate and update order total
                    $orderItems = $this->orderItemService->getOrderItemsByOrder($orderId);
                    $newTotal = 0;
                    foreach ($orderItems as $item) {
                        $itemPrice = floatval($item['price'] ?? 0);
                        $quantity = intval($item['quantity'] ?? 1);
                        $newTotal += $itemPrice * $quantity;
                    }
                    
                    // Apply service charge if needed (same logic as placeOrder)
                    $orderCalculator = new \App\Services\Order\OrderCalculator();
                    $finalTotal = $orderCalculator->calculateFinalTotal($newTotal);
                    $totalAmount = $finalTotal['total'];
                    
                    $this->orderService->updateOrderTotal($orderId, $totalAmount);
                    
                    // ================================================================
                    // CRITICAL: Generate preparation receipts for newly added items
                    // Same logic as OrderService::placeOrder() lines 346-502
                    // ================================================================
                    try {
                        $receiptService = \App\Core\DependencyFactory::getReceiptService();
                        $preparationScreenService = \App\Core\DependencyFactory::getPreparationScreenService();
                        $businessId = \App\Core\TenantContext::getId();
                        
                        if ($businessId) {
                            if (class_exists('\App\Core\TenantContext')) {
                                \App\Core\TenantContext::setId($businessId);
                            }
                            
                            $kitchenScreenId = 'kitchen_main';
                            $itemsByScreen = [];
                            
                            // Group added items by screen
                            foreach ($items as $item) {
                                $menuItemId = $item['menu_item_id'] ?? '';
                                if (empty($menuItemId) || !in_array($menuItemId, $addedItems)) {
                                    continue;
                                }
                                
                                $menuItem = $this->menuItemService->getMenuItemById($menuItemId);
                                if (!$menuItem) {
                                    continue;
                                }
                                
                                // Skip direct service products
                                $productionPoint = $menuItem['production_point'] ?? null;
                                $isDirectService = !empty($menuItem['is_direct_service']) && $menuItem['is_direct_service'] == 1;
                                if ($productionPoint === 'NONE' || $isDirectService) {
                                    continue;
                                }
                                
                                $screenId = null;
                                
                                // Priority 1: Direct screen assignment
                                $itemPreparationScreenId = $menuItem['preparation_screen_id'] ?? null;
                                if (!empty($itemPreparationScreenId)) {
                                    $screenId = $itemPreparationScreenId;
                                } else {
                                    // Priority 2: Category-based assignment
                                    $itemCategoryId = $menuItem['category_id'] ?? '';
                                    if (!empty($itemCategoryId)) {
                                        $allScreens = $preparationScreenService->getAllScreens();
                                        foreach ($allScreens as $screen) {
                                            $screenCategoryIds = $preparationScreenService->getScreenCategoryIds($screen['screen_id']);
                                            if (in_array($itemCategoryId, $screenCategoryIds)) {
                                                $screenId = $screen['screen_id'];
                                                break;
                                            }
                                        }
                                        
                                        // Priority 2b: Nargile keyword fallback
                                        if (empty($screenId)) {
                                            foreach ($allScreens as $screen) {
                                                $sName = strtolower(trim($screen['name'] ?? ''));
                                                $sSlug = strtolower(trim($screen['slug'] ?? ''));
                                                $isNargile = (strpos($sName, 'nargile') !== false || strpos($sSlug, 'nargile') !== false ||
                                                             strpos($sName, 'hookah') !== false || strpos($sSlug, 'hookah') !== false);
                                                if ($isNargile) {
                                                    try {
                                                        $catService = \App\Core\DependencyFactory::getCategoryService();
                                                        $cat = $catService->getCategoryById($itemCategoryId);
                                                        if ($cat) {
                                                            $catName = strtolower(trim($cat['name'] ?? ''));
                                                            if (strpos($catName, 'nargile') !== false || strpos($catName, 'hookah') !== false || strpos($catName, 'shisha') !== false) {
                                                                $screenId = $screen['screen_id'];
                                                                break;
                                                            }
                                                        }
                                                    } catch (\Exception $ncEx) { /* fallback yok */ }
                                                }
                                            }
                                        }
                                    }
                                    
                                    // Priority 3: Default to kitchen
                                    if (empty($screenId)) {
                                        if ($productionPoint === 'KITCHEN' || 
                                            (isset($menuItem['requires_kitchen']) && intval($menuItem['requires_kitchen']) == 1)) {
                                            $screenId = $kitchenScreenId;
                                        } else if ($productionPoint !== 'NONE') {
                                            $screenId = $kitchenScreenId;
                                        }
                                    }
                                }
                                
                                if ($screenId === 'KITCHEN' && $kitchenScreenId) {
                                    $screenId = $kitchenScreenId;
                                }
                                
                                if ($screenId) {
                                    if (!isset($itemsByScreen[$screenId])) {
                                        $itemsByScreen[$screenId] = [];
                                    }
                                    // Build order item with item_name for receipt
                                    $orderItemForReceipt = [
                                        'menu_item_id' => $menuItemId,
                                        'item_name' => $menuItem['name'] ?? $menuItem['item_name'] ?? 'Ürün',
                                        'quantity' => intval($item['quantity'] ?? 1),
                                        'price' => floatval($menuItem['price'] ?? 0),
                                        'note' => sanitizeInput($item['note'] ?? ''),
                                        'excluded_ingredients' => $item['excluded_ingredients'] ?? [],
                                        'selected_extras' => $item['selected_extras'] ?? [],
                                        'variant_id' => $item['variant_id'] ?? null,
                                    ];
                                    $itemsByScreen[$screenId][] = $orderItemForReceipt;
                                }
                            }
                            
                            // Generate ONE preparation receipt per screen with ALL items for that screen
                            foreach ($itemsByScreen as $screenId => $screenItems) {
                                $receiptService->generatePreparationReceipt([
                                    'order_id' => $orderId,
                                    'screen_id' => $screenId,
                                    'business_id' => $businessId,
                                    'items' => $screenItems,
                                    'customizations' => []
                                ]);
                                
                                \App\Core\Logger::info('PosController::createOrder: Generated preparation receipt for added items', [
                                    'order_id' => $orderId,
                                    'screen_id' => $screenId,
                                    'item_count' => count($screenItems)
                                ]);
                            }
                        }
                    } catch (\Exception $e) {
                        \App\Core\Logger::error('PosController::createOrder: Failed to generate preparation receipts for added items', [
                            'order_id' => $orderId,
                            'error' => $e->getMessage()
                        ]);
                        // Don't fail the order if receipt generation fails
                    }
                    
                    $this->apiResponse([
                        'success' => true, 
                        'order_id' => $orderId,
                        'message' => 'Ürünler mevcut siparişe eklendi',
                        'added_items' => count($addedItems),
                        'failed_items' => count($failedItems)
                    ]);
                    return;
                } else {
                    $this->apiResponse([
                        'success' => false,
                        'error' => 'Ürünler eklenemedi'
                    ], 500);
                    return;
                }
            }

            // Validation and calculation are handled by OrderService
            // No need to duplicate business logic here

            $orderData = [
                'table_id' => $tableId,
                'items' => $items,
                'customer_note' => $customerNote,
                'order_source' => 'POS',
                'created_by' => $_SESSION['user_id'] ?? 'staff',
                'staff_name' => $staffName
            ];

            try {
                $result = $this->orderService->placeOrder($orderData);

                if ($result && isset($result['success']) && $result['success'] && isset($result['order_id'])) {
                    // Update table status to OCCUPIED when order is created
                    try {
                        $this->tableService->updateTableStatus($tableId, 'OCCUPIED');
                    } catch (\Exception $e) {
                        // Log error but don't fail the order creation
                        \App\Core\Logger::error("Failed to update table status: " . $e->getMessage());
                    }
                    
                    $this->apiResponse(['success' => true, 'order_id' => $result['order_id']]);
                } else {
                    $errorMsg = 'Sipariş oluşturulamadı';
                    $errors = $result['errors'] ?? [];
                    if (!empty($errors)) {
                        $errorMsg = is_array($errors[0] ?? null) ? implode(', ', (array)$errors[0]) : (string)($errors[0] ?? $errorMsg);
                    }
                    $response = ['success' => false, 'error' => $errorMsg];
                    if (!empty($errors)) {
                        $response['errors'] = $errors;
                    }
                    $this->apiResponse($response, 200);
                }
            } catch (\Exception $e) {
                \App\Core\Logger::error("PosController::createOrder exception: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
                
                // Kullanıcı dostu hata mesajı
                $errorMessage = 'Sipariş oluşturulurken bir hata oluştu';
                
                // Bazı yaygın hataları daha anlamlı hale getir
                $exceptionMsg = strtolower($e->getMessage());
                if (strpos($exceptionMsg, 'duplicate') !== false || strpos($exceptionMsg, 'unique') !== false) {
                    $errorMessage = 'Bu sipariş zaten oluşturulmuş';
                } elseif (strpos($exceptionMsg, 'foreign key') !== false || strpos($exceptionMsg, 'not found') !== false) {
                    $errorMessage = 'Seçilen ürün veya masa bulunamadı';
                } elseif (strpos($exceptionMsg, 'connection') !== false || strpos($exceptionMsg, 'timeout') !== false) {
                    $errorMessage = 'Veritabanı bağlantı hatası. Lütfen tekrar deneyin';
                }
                
                $this->apiResponse([
                    'success' => false,
                    'error' => $errorMessage
                ], 500);
            }
        }
    }

    public function processPayment() {
        // Check permission - this is sufficient, no need for additional role check
        if (!$this->checkPermissionOrFail('pos.process_payment')) {
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Handle both JSON and form data
            $input = json_decode(file_get_contents('php://input'), true);
            if ($input === null) {
                $input = $_POST;
            }

            // Validate CSRF token for form submissions
            $requestData = \App\Core\RequestParser::getRequestData();
            if (isset($requestData['csrf_token'])) {
                if (!validateCSRFToken($requestData['csrf_token'])) {
                    $this->toastNotificationService->sendApiResponse('error', 'notifications.error.csrf_invalid', [], 403);
                    return;
                }
            }

            // Validate input data
            $validationRules = [
                'table_id' => 'required|string|min:1|max:50',
                'amount' => 'required|numeric|min:0',
                'method' => 'required|in:CASH,CARD,MIXED',
                'tip' => 'numeric|min:0',
                'items_paid' => 'array',
                'payment_breakdown' => 'array'
            ];

            $errors = validateInputs($input, $validationRules);
            if (!empty($errors)) {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.warning.validation_error', [], 400);
                return;
            }

            $tableId = $input['table_id'] ?? '';
            $amount = floatval($input['amount'] ?? 0);
            $method = $input['method'] ?? 'CASH';
            $tip = floatval($input['tip'] ?? 0);
            $itemsPaid = $input['items_paid'] ?? [];
            if (is_string($itemsPaid)) {
                $itemsPaid = json_decode($itemsPaid, true) ?? [];
            }

            if (empty($tableId) || $amount <= 0) {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
                return;
            }

            // Sanitize inputs
            $tableId = xssClean($tableId);
            $method = xssClean($method);

            // Payment is allowed regardless of item preparation status
            // Items in preparation will be automatically handled after payment

            // Shift management removed
            $shiftId = null;

            // Get ACTIVE orders only - never process already-SERVED or CANCELLED orders
            $orders = $this->orderService->getActiveOrdersByTable($tableId);
            $orderId = !empty($orders) ? ($orders[0]['order_id'] ?? null) : null;

            // Use PaymentService (central payment infrastructure with multiple gateways)
            $paymentData = [
                'table_id' => $tableId,
                'order_id' => $orderId,
                'amount' => $amount,
                'payment_method' => $method,
                'tip' => $tip,
                'processed_by' => $_SESSION['user_id'],
                'shift_id' => $shiftId
            ];
            
            // Add MIXED payment breakdown amounts
            if ($method === 'MIXED' && isset($input['payment_breakdown'])) {
                $paymentData['cash_amount'] = floatval($input['payment_breakdown']['cash'] ?? 0);
                $paymentData['card_amount'] = floatval($input['payment_breakdown']['card'] ?? 0);
            }

            // Process payment through PaymentService (uses gateway adapters)
            $paymentResult = $this->paymentService->processPayment($paymentData);

            if ($paymentResult && isset($paymentResult['success']) && $paymentResult['success']) {
                // Payment processed successfully through gateway
                $transactionId = $paymentResult['transaction_id'] ?? null;
                
                // Update order status to SERVED for all orders on this table
                if (empty($orders)) {
                    $orders = $this->orderService->getActiveOrdersByTable($tableId);
                }
                $totalRevenue = 0;
                $totalTip = $tip;
                $receiptIds = [];

                try {
                    foreach ($orders as $order) {
                        $orderStatus = $order['status'] ?? '';
                        if (in_array($orderStatus, ['SERVED', 'CANCELLED', 'REFUNDED'])) {
                            continue;
                        }
                        $servedStatus = ConstantsHelper::getOrderStatus('SERVED');
                        $this->orderService->updateOrderStatus($order['order_id'], $servedStatus);
                        try {
                            $db = \App\Core\DependencyFactory::getDatabase();
                            $db->prepare("UPDATE orders SET is_paid = 1 WHERE order_id = ?")->execute([$order['order_id']]);
                        } catch (\Exception $paidEx) { /* non-critical */ }
                        $totalRevenue += floatval($order['total_amount'] ?? 0);

                        // Generate receipt for each order (Otomatik fiş oluşturma)
                        $receiptData = [
                            'order_id' => $order['order_id'],
                            'payment_method' => $method,
                            'receipt_type' => 'FULL',
                            'discount_amount' => 0,
                            'printer_id' => $input['printer_id'] ?? null,
                            'created_by' => $_SESSION['user_id'] ?? 'system',
                            'payment_breakdown' => ($method === 'MIXED' && isset($input['payment_breakdown']))
                                ? $input['payment_breakdown']
                                : null
                        ];

                        $receiptResult = $this->receiptService->generateReceipt($receiptData);
                        if ($receiptResult && !empty($receiptResult['receipt_id'])) {
                            $receiptIds[] = $receiptResult['receipt_id'];

                            $this->receiptService->printReceipt(
                                $receiptResult['receipt_id'],
                                $receiptData['printer_id'],
                                'CASHIER'
                            );
                        }
                    }
                } catch (\PDOException $e) {
                    $errMsg = $e->getMessage();
                    $code = $e->getCode();
                    $isDuplicateReceipt = (($code === '23000' || $code === 23000) && strpos($errMsg, 'receipt_number') !== false);
                    $this->apiResponse([
                        'success' => false,
                        'error' => $isDuplicateReceipt
                            ? 'Fiş numarası çakışması oluştu, lütfen tekrar deneyin.'
                            : ('Ödeme işlemi sırasında bir hata oluştu: ' . $errMsg),
                        'code' => 'RECEIPT_ERROR'
                    ], 500);
                    return;
                } catch (\Throwable $e) {
                    \App\Core\Logger::error('processPayment receipt generation failed', [
                        'error' => $e->getMessage(),
                        'table_id' => $tableId
                    ]);
                    $this->apiResponse([
                        'success' => false,
                        'error' => 'Ödeme işlemi sırasında bir hata oluştu: ' . $e->getMessage(),
                        'code' => 'PAYMENT_ERROR'
                    ], 500);
                    return;
                }

                // Archive session when payment is processed
                $archivedSessionService = \App\Core\DependencyFactory::getArchivedSessionService();

                // Get table info
                $table = $this->tableService->getTableById($tableId);
                $tableName = $table['name'] ?? 'Masa';

                // Create archived session
                $sessionData = [
                    'table_id' => $tableId,
                    'table_name' => $tableName,
                    'start_time' => date('Y-m-d H:i:s', strtotime('-2 hours')), // Approximate start time
                    'end_time' => date('Y-m-d H:i:s'),
                    'total_revenue' => $totalRevenue,
                    'total_tip' => $totalTip,
                    'receipt_ids' => implode(',', $receiptIds),
                    'payment_breakdown' => json_encode(
                        $method === 'MIXED' && isset($input['payment_breakdown']) 
                            ? $input['payment_breakdown'] 
                            : [
                                'cash' => $method === 'CASH' ? $amount : 0,
                                'card' => $method === 'CARD' ? $amount : 0
                            ]
                    )
                ];

                $archivedSessionService->createSession($sessionData);

                // After payment is processed, all orders are marked as SERVED
                // Therefore, table should always be set to FREE
                $this->tableService->updateTableStatus($tableId, 'FREE');

                // Clear QR/table sessions after payment
                try {
                    $tableSessionService = \App\Core\DependencyFactory::getTableSessionService();
                    $customerSessionService = \App\Core\DependencyFactory::getCustomerSessionService();
                    $tableSessionService->clearSessionsByTable($tableId);
                    $customerSessionService->clearSessionsByTable($tableId);
                } catch (\Exception $e) {
                    if (class_exists('\App\Core\Logger')) {
                        \App\Core\Logger::warning('Failed to clear QR sessions after payment (POS)', [
                            'table_id' => $tableId,
                            'error' => $e->getMessage()
                        ]);
                    }
                }

                $this->apiResponse([
                    'success' => true,
                    'transaction_id' => $transactionId,
                    'receipt_ids' => $receiptIds,
                    'receipt_count' => count($receiptIds),
                    'payment_reference' => $paymentResult['reference'] ?? null,
                    'table_status' => 'FREE'
                ]);
            } else {
                // Payment failed through gateway
                $errorMessage = $paymentResult['error'] ?? 'Ödeme işlemi başarısız oldu';
                $errorCode = $paymentResult['code'] ?? 'PAYMENT_FAILED';
                
                $this->apiResponse([
                    'success' => false,
                    'error' => $errorMessage,
                    'code' => $errorCode
                ], 500);
            }
        }
    }

    public function printOrderAdisyon() {
        // Check permission
        if (!$this->checkPermissionOrFail('pos.process_payment')) {
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Handle both JSON and form data
            $input = json_decode(file_get_contents('php://input'), true);
            if ($input === null) {
                $input = $_POST;
            }

            // Validate CSRF token for form submissions
            $requestData = \App\Core\RequestParser::getRequestData();
            if (isset($requestData['csrf_token'])) {
                if (!validateCSRFToken($requestData['csrf_token'])) {
                    $this->toastNotificationService->sendApiResponse('error', 'notifications.error.csrf_invalid', [], 403);
                    return;
                }
            }

            // Validate input data
            $tableId = $input['table_id'] ?? '';
            if (empty($tableId)) {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
                return;
            }

            // Sanitize input
            $tableId = xssClean($tableId);

            // Check if table has active orders
            $activeOrders = $this->orderService->getActiveOrdersByTable($tableId);
            if (empty($activeOrders)) {
                $this->apiResponse([
                    'success' => false,
                    'error' => 'Bu masa için aktif sipariş bulunmamaktadır.'
                ], 400);
                return;
            }

            // Get optional printer ID, order_id, print_all
            $printerId = !empty($input['printer_id']) ? xssClean($input['printer_id']) : null;
            $orderId = !empty($input['order_id']) ? xssClean($input['order_id']) : null;
            $printAll = !empty($input['print_all']) && ($input['print_all'] === true || $input['print_all'] === 'true' || $input['print_all'] === '1');

            try {
                $orderPrintService = \App\Core\DependencyFactory::getOrderPrintService();
                $result = $orderPrintService->printAdisyonForTable($tableId, $printerId, $orderId, $printAll);

                if ($result && isset($result['success']) && $result['success']) {
                    $this->apiResponse([
                        'success' => true,
                        'message' => $result['message'] ?? 'Adisyon başarıyla yazdırma kuyruğuna eklendi',
                        'printed_count' => $result['printed_count'] ?? 0,
                        'total_orders' => $result['total_orders'] ?? count($activeOrders)
                    ]);
                } else {
                    $this->apiResponse([
                        'success' => false,
                        'error' => $result['error'] ?? 'Adisyon yazdırılamadı'
                    ], 500);
                }
            } catch (\PDOException $e) {
                $msg = $e->getMessage();
                $code = $e->getCode();
                $isDuplicate = (($code === '23000' || $code === 23000) && strpos($msg, 'receipt_number') !== false);
                $this->apiResponse([
                    'success' => false,
                    'error' => $isDuplicate ? 'Fiş numarası çakışması, lütfen tekrar deneyin.' : ('Adisyon yazdırılamadı: ' . $msg)
                ], 500);
            } catch (\Throwable $e) {
                \App\Core\Logger::error('printOrderAdisyon failed', ['error' => $e->getMessage(), 'table_id' => $tableId]);
                $this->apiResponse([
                    'success' => false,
                    'error' => 'Adisyon yazdırılamadı: ' . $e->getMessage()
                ], 500);
            }
        }
    }

    /**
     * Mutfak/hazırlık ekranında ürün varken ödeme almak için yönetici onayı iste.
     * POST table_id -> onay talebi oluşturulur, yönetici onaylarsa hazırlanan ürünler iptal edilir.
     */
    public function requestPaymentPrepCancel() {
        if (!$this->checkPermissionOrFail('pos.process_payment')) {
            return;
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->apiResponse(['success' => false, 'error' => 'Method not allowed'], 405);
            return;
        }
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $tableId = isset($input['table_id']) ? xssClean($input['table_id']) : '';
        if (empty($tableId)) {
            $this->apiResponse(['success' => false, 'error' => 'table_id gerekli'], 400);
            return;
        }
        $orderItemService = \App\Core\DependencyFactory::getOrderItemService();
        if (!$orderItemService->hasItemsInPreparationByTableId($tableId)) {
            $this->apiResponse(['success' => false, 'error' => 'Bu masada hazırlanan ürün bulunmuyor.'], 400);
            return;
        }
        $approvalService = \App\Core\DependencyFactory::getOrderEditApprovalService();
        if ($approvalService->hasPendingPaymentPrepCancelForTable($tableId)) {
            $this->apiResponse(['success' => false, 'error' => 'Bu masa için zaten onay talebi bekliyor.'], 400);
            return;
        }
        $table = $this->tableService->getTableById($tableId);
        $tableName = $table['name'] ?? 'Masa ' . $tableId;
        $orders = $this->orderService->getActiveOrdersByTable($tableId);
        $firstOrderId = !empty($orders) ? ($orders[0]['order_id'] ?? '') : '';
        $prepItems = [];
        foreach ($orders as $order) {
            $items = $orderItemService->getOrderItemsByOrder($order['order_id'] ?? '');
            foreach ($items as $it) {
                $status = strtoupper(trim($it['preparation_status'] ?? 'PENDING'));
                if (!in_array($status, ['PENDING', 'PREPARING'])) {
                    continue;
                }
                $qty = intval($it['quantity'] ?? 1);
                $price = floatval($it['price'] ?? 0);
                $prepItems[] = [
                    'name' => $it['item_name'] ?? $it['menu_item_name'] ?? $it['name'] ?? 'Ürün',
                    'quantity' => $qty,
                    'price' => $price,
                    'total' => $qty * $price,
                    'note' => $it['note'] ?? $it['notes'] ?? $it['item_note'] ?? '',
                    'excluded_ingredients' => $it['excluded_ingredients'] ?? [],
                    'selected_extras' => $it['selected_extras'] ?? [],
                    'variant_name' => $it['variant_name'] ?? '',
                    'preparation_status' => $status,
                ];
            }
        }
        $prepCount = count($prepItems);
        $prepTotal = array_sum(array_column($prepItems, 'total'));
        $approvalData = [
            'order_item_id' => 'prep_cancel_' . $tableId,
            'order_id' => $firstOrderId,
            'table_id' => $tableId,
            'table_name' => $tableName,
            'action_type' => 'PAYMENT_PREP_CANCEL',
            'old_quantity' => $prepCount ?: 1,
            'new_quantity' => null,
            'item_name' => $prepCount ? ($prepCount . ' hazırlanan ürün (' . number_format($prepTotal, 2) . ' ₺)') : 'Ödeme - hazırlanan ürünleri iptal',
            'item_price' => $prepCount ? $prepTotal : null,
            'requested_by' => $_SESSION['user_id'] ?? '',
            'requested_by_name' => $_SESSION['username'] ?? $_SESSION['name'] ?? 'Kullanıcı',
            'prep_items_snapshot' => $prepItems,
        ];
        $approvalId = $approvalService->createApprovalRequest($approvalData);
        if ($approvalId) {
            $this->apiResponse([
                'success' => true,
                'message' => 'Yönetici onay talebi gönderildi. Onaylandıktan sonra ödeme alabilirsiniz.',
                'approval_id' => $approvalId
            ]);
        } else {
            $this->apiResponse(['success' => false, 'error' => 'Onay talebi oluşturulamadı.'], 500);
        }
    }

    public function getActiveOrders() {
        // CRITICAL: Ensure tenant context
        $this->ensureTenantContext();
        
        // Check permission (this one still needs permission as it's for staff)
        if (!$this->checkPermission('orders.view')) {
            $this->apiResponse([], 200); // Return empty array if no permission
            return;
        }

        $queryParams = \App\Core\RequestParser::getQueryParams();
        $tableId = $queryParams['table_id'] ?? '';

        if (!empty($tableId)) {
            $orders = $this->orderService->getOrdersByTable($tableId);
        } else {
            $pendingStatus = ConstantsHelper::getOrderStatus('PENDING');
            $orders = $this->orderService->getOrdersByStatus($pendingStatus);
        }

        $this->apiResponse($orders);
    }

    public function getTableOrders() {
        $this->ensureTenantContext();
        
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $tableId = $queryParams['table_id'] ?? '';

        if (!empty($tableId)) {
            try {
                // PERFORMANCE: Short-lived cache (2 sec) to avoid repeated DB hits when user keeps same table selected
                $cacheKey = 'pos_table_orders:' . $tableId . ':' . (\App\Core\TenantContext::getId() ?? $_SESSION['customer_id'] ?? '');
                $cache = \App\Core\DependencyFactory::getCacheService();
                $cached = $cache->get($cacheKey);
                if (is_array($cached)) {
                    $this->apiResponse($cached);
                    return;
                }

                $table = $this->tableService->getTableById($tableId);
                if (!$table) {
                    $this->apiResponse([], 200);
                    return;
                }
                
                $orders = $this->orderService->getActiveOrdersByTable($tableId);

                if (!empty($orders)) {
                    $orderIds = array_column($orders, 'order_id');
                    $allOrderItems = $this->orderItemService->getOrderItemsByOrderIds($orderIds);
                    
                    // Group items by order_id
                    $itemsByOrderId = [];
                    foreach ($allOrderItems as $item) {
                        $orderId = $item['order_id'];
                        if (!isset($itemsByOrderId[$orderId])) {
                            $itemsByOrderId[$orderId] = [];
                        }
                        $itemsByOrderId[$orderId][] = $item;
                    }
                    
                    // Attach items; exclude CANCELLED; fix 0 TL recalc from items
                    foreach ($orders as &$order) {
                        $oid = $order['order_id'];
                        $allItems = $itemsByOrderId[$oid] ?? [];
                        $items = array_values(array_filter($allItems, function ($it) {
                            return ($it['preparation_status'] ?? '') !== 'CANCELLED';
                        }));
                        $order['items'] = $items;
                        $dbTotal = isset($order['total_amount']) ? floatval($order['total_amount']) : 0;
                        if (!empty($items)) {
                            $calcTotal = 0;
                            foreach ($items as $it) {
                                $calcTotal += floatval($it['price'] ?? 0) * intval($it['quantity'] ?? 1);
                            }
                            $order['total_amount'] = round($calcTotal, 2);
                            
                            // Also fix stale DB total
                            if (abs($dbTotal - $calcTotal) > 0.01) {
                                try {
                                    $this->orderService->updateOrderTotal($oid, round($calcTotal, 2));
                                } catch (\Exception $e) {
                                    // Non-critical
                                }
                            }
                        }
                    }
                    unset($order);
                }

                $cache->set($cacheKey, $orders, 2); // 2 second TTL
                $this->apiResponse($orders);
            } catch (\Exception $e) {
                \App\Core\Logger::error("getTableOrders error: " . $e->getMessage());
                $this->apiResponse([], 200);
            }
        } else {
            $this->apiResponse([], 200);
        }
    }

    public function getTableHistory() {
        // Check permission
        if (!$this->checkPermissionOrFail('table.history')) {
            return;
        }

        $queryParams = \App\Core\RequestParser::getQueryParams();
        $tableId = $queryParams['id'] ?? '';
        if (empty($tableId)) {
            // Try to get from route parameter
            $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
            if (preg_match('/table-history\/([^\/]+)/', $path, $matches)) {
                $tableId = $matches[1];
            }
        }

        if (empty($tableId)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
            return;
        }

        $archivedSessionService = \App\Core\DependencyFactory::getArchivedSessionService();
        $sessions = $archivedSessionService->getByTable($tableId);

        // Get today's sessions
        $today = date('Y-m-d');
        $todaySessions = $archivedSessionService->getTableDailyHistory($tableId, $today);

        // Combine and sort by date
        $allSessions = array_merge($sessions, $todaySessions);
        usort($allSessions, function($a, $b) {
            $timeA = strtotime($a['start_time'] ?? $a['created_at'] ?? '1970-01-01');
            $timeB = strtotime($b['start_time'] ?? $b['created_at'] ?? '1970-01-01');
            return $timeB - $timeA;
        });

        $this->apiResponse($allSessions);
    }

    public function addItemToOrder() {
        // CRITICAL: Ensure tenant context is set
        $this->ensureTenantContext();
        
        // Check permission
        if (!$this->checkPermissionOrFail('orders.create')) {
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Validate CSRF token (check body first, then header fallback)
            $requestData = \App\Core\RequestParser::getRequestData();
            $csrfToken = $requestData['csrf_token'] ?? \App\Core\Security\CSRFManager::extractTokenFromRequest() ?? '';
            if (!validateCSRFToken($csrfToken)) {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.csrf_invalid', [], 403);
                return;
            }

            $orderId = $requestData['order_id'] ?? '';
            $menuItemId = $requestData['menu_item_id'] ?? '';
            $quantity = intval($requestData['quantity'] ?? 1);
            $note = sanitizeInput($requestData['note'] ?? '');

            if (empty($orderId) || empty($menuItemId) || $quantity <= 0) {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
                return;
            }

            // CRITICAL: Verify order belongs to current tenant
            $order = $this->orderService->getOrderById($orderId);
            if (!$order) {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.order_not_found', [], 404);
                return;
            }
            
            // Check tenant isolation (unless super admin)
            // NOTE: orders table uses tenant_id column (not business_id)
            if (!$this->isSuperAdmin()) {
                $tenantId = \App\Core\TenantContext::getId();
                $orderTenantId = $order['tenant_id'] ?? null;
                
                if (!$tenantId || $orderTenantId !== $tenantId) {
                    \App\Core\Logger::warning('PosController::addItemToOrder - Order tenant isolation violation', [
                        'order_id' => $orderId,
                        'order_tenant_id' => $orderTenantId,
                        'tenant_id' => $tenantId
                    ]);
                    $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 403);
                    return;
                }
            }

            $menuItem = $this->menuItemService->getMenuItemById($menuItemId);
            if (!$menuItem) {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.menu_item_not_found', [], 404);
                return;
            }
            
            // CRITICAL: Verify menu item belongs to current tenant
            if (!$this->isSuperAdmin()) {
                $tenantId = \App\Core\TenantContext::getId();
                $menuItemBusinessId = $menuItem['tenant_id'] ?? null;
                
                if (!$tenantId || $menuItemBusinessId !== $tenantId) {
                    \App\Core\Logger::warning('PosController::addItemToOrder - Menu item tenant isolation violation', [
                        'menu_item_id' => $menuItemId,
                        'menu_item_business_id' => $menuItemBusinessId,
                        'tenant_id' => $tenantId
                    ]);
                    $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 403);
                    return;
                }
            }

            // Get variant_id if provided
            $variantId = $requestData['variant_id'] ?? null;
            $variantPriceModifier = 0;
            
            // If product has variants and variant_id is provided, get variant price modifier
            if ($variantId && !empty($menuItem['has_variants']) && $menuItem['has_variants'] == 1) {
                $productVariantService = \App\Core\DependencyFactory::getProductVariantService();
                $variants = $productVariantService->getActiveVariantsByProduct($menuItemId);
                foreach ($variants as $variant) {
                    if ($variant['variant_id'] === $variantId) {
                        $variantPriceModifier = floatval($variant['price_modifier'] ?? 0);
                        break;
                    }
                }
            }
            
            // Create order item
            $orderItemData = [
                'order_id' => $orderId,
                'menu_item_id' => $menuItemId,
                'variant_id' => $variantId,
                'quantity' => $quantity,
                'price' => $menuItem['price'] + $variantPriceModifier,
                'note' => $note
            ];

            $result = $this->orderItemService->createOrderItem($orderItemData);

            if ($result) {
                // Update stock only if product has stock tracking enabled
                $trackStock = isset($menuItem['track_stock']) && $menuItem['track_stock'] == 1;
                if ($trackStock) {
                    $this->menuItemService->updateStock($menuItemId, $quantity);
                }

                // CRITICAL FIX: Recalculate total from ALL items (prevents 0 TL drift)
                $allItems = $this->orderItemService->getOrderItemsByOrder($orderId);
                $newTotal = 0;
                foreach ($allItems as $it) {
                    if (($it['preparation_status'] ?? '') !== 'CANCELLED') {
                        $newTotal += floatval($it['price'] ?? 0) * intval($it['quantity'] ?? 1);
                    }
                }
                $this->orderService->updateOrderTotal($orderId, round($newTotal, 2));

                // ================================================================
                // CRITICAL: Generate preparation receipt for the newly added item
                // Same logic as OrderService::placeOrder() lines 346-502
                // ================================================================
                try {
                    $receiptService = \App\Core\DependencyFactory::getReceiptService();
                    $preparationScreenService = \App\Core\DependencyFactory::getPreparationScreenService();
                    $businessId = \App\Core\TenantContext::getId();
                    
                    if ($businessId) {
                        // Skip direct service products
                        $productionPoint = $menuItem['production_point'] ?? null;
                        $isDirectService = !empty($menuItem['is_direct_service']) && $menuItem['is_direct_service'] == 1;
                        
                        if ($productionPoint !== 'NONE' && !$isDirectService) {
                            $kitchenScreenId = 'kitchen_main';
                            $screenId = null;
                            
                            // Priority 1: Direct screen assignment
                            $itemPreparationScreenId = $menuItem['preparation_screen_id'] ?? null;
                            if (!empty($itemPreparationScreenId)) {
                                $screenId = $itemPreparationScreenId;
                            } else {
                                // Priority 2: Category-based assignment
                                $itemCategoryId = $menuItem['category_id'] ?? '';
                                if (!empty($itemCategoryId)) {
                                    $allScreens = $preparationScreenService->getAllScreens();
                                    foreach ($allScreens as $screen) {
                                        $screenCategoryIds = $preparationScreenService->getScreenCategoryIds($screen['screen_id']);
                                        if (in_array($itemCategoryId, $screenCategoryIds)) {
                                            $screenId = $screen['screen_id'];
                                            break;
                                        }
                                    }
                                    
                                    // Priority 2b: Nargile keyword fallback
                                    if (empty($screenId)) {
                                        foreach ($allScreens as $screen) {
                                            $sName = strtolower(trim($screen['name'] ?? ''));
                                            $sSlug = strtolower(trim($screen['slug'] ?? ''));
                                            $isNargile = (strpos($sName, 'nargile') !== false || strpos($sSlug, 'nargile') !== false ||
                                                         strpos($sName, 'hookah') !== false || strpos($sSlug, 'hookah') !== false);
                                            if ($isNargile) {
                                                try {
                                                    $catService = \App\Core\DependencyFactory::getCategoryService();
                                                    $cat = $catService->getCategoryById($itemCategoryId);
                                                    if ($cat) {
                                                        $catName = strtolower(trim($cat['name'] ?? ''));
                                                        if (strpos($catName, 'nargile') !== false || strpos($catName, 'hookah') !== false || strpos($catName, 'shisha') !== false) {
                                                            $screenId = $screen['screen_id'];
                                                            break;
                                                        }
                                                    }
                                                } catch (\Exception $ncEx) { /* fallback yok */ }
                                            }
                                        }
                                    }
                                }
                                
                                // Priority 3: Default to kitchen
                                if (empty($screenId)) {
                                    if ($productionPoint === 'KITCHEN' || 
                                        (isset($menuItem['requires_kitchen']) && intval($menuItem['requires_kitchen']) == 1)) {
                                        $screenId = $kitchenScreenId;
                                    } else if ($productionPoint !== 'NONE') {
                                        $screenId = $kitchenScreenId;
                                    }
                                }
                            }
                            
                            if ($screenId === 'KITCHEN' && $kitchenScreenId) {
                                $screenId = $kitchenScreenId;
                            }
                            
                            if ($screenId) {
                                // Build order item with item_name for receipt
                                $orderItemForReceipt = $orderItemData;
                                $orderItemForReceipt['order_item_id'] = $result; // order_item_id from createOrderItem
                                $orderItemForReceipt['item_name'] = $menuItem['name'] ?? $menuItem['item_name'] ?? 'Ürün';
                                
                                // Get customizations if provided
                                $customizations = [];
                                $requestCustomizations = $requestData['customizations'] ?? [];
                                if (!empty($requestCustomizations[$menuItemId])) {
                                    $customizations[$menuItemId] = $requestCustomizations[$menuItemId];
                                }
                                
                                $receiptService->generatePreparationReceipt([
                                    'order_id' => $orderId,
                                    'screen_id' => $screenId,
                                    'business_id' => $businessId,
                                    'items' => [$orderItemForReceipt],
                                    'customizations' => $customizations
                                ]);
                                
                                \App\Core\Logger::info('PosController: Generated preparation receipt for added item', [
                                    'order_id' => $orderId,
                                    'screen_id' => $screenId,
                                    'menu_item_id' => $menuItemId,
                                    'item_name' => $menuItem['name'] ?? 'unknown'
                                ]);
                            }
                        }
                    }
                } catch (\Exception $e) {
                    \App\Core\Logger::error('PosController: Failed to generate preparation receipt for added item', [
                        'order_id' => $orderId,
                        'menu_item_id' => $menuItemId,
                        'error' => $e->getMessage()
                    ]);
                    // Don't fail the add-item operation if receipt generation fails
                }

                $this->apiResponse(['success' => true]);
            } else {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.create_failed', [], 500);
            }
        }
    }

    public function removeItemFromOrder() {
        // CRITICAL: Ensure tenant context is set
        $this->ensureTenantContext();
        
        // Check permission
        if (!$this->checkPermissionOrFail('orders.edit')) {
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Validate CSRF token (check body first, then header fallback)
            $requestData = \App\Core\RequestParser::getRequestData();
            $csrfToken = $requestData['csrf_token'] ?? \App\Core\Security\CSRFManager::extractTokenFromRequest() ?? '';
            if (!validateCSRFToken($csrfToken)) {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.csrf_invalid', [], 403);
                return;
            }

            $orderItemId = $requestData['order_item_id'] ?? '';

            if (!empty($orderItemId)) {
                $orderItem = $this->orderItemService->getOrderItemById($orderItemId);

                if (!$orderItem) {
                    $this->toastNotificationService->sendApiResponse('error', 'notifications.error.not_found', [], 404);
                    return;
                }
                
                // CRITICAL: Verify order belongs to current tenant
                // NOTE: orders table uses tenant_id column (not business_id)
                $orderId = $orderItem['order_id'];
                $order = $this->orderService->getOrderById($orderId);
                if ($order && !$this->isSuperAdmin()) {
                    $tenantId = \App\Core\TenantContext::getId();
                    $orderTenantId = $order['tenant_id'] ?? null;
                    
                    if (!$tenantId || $orderTenantId !== $tenantId) {
                        \App\Core\Logger::warning('PosController::removeItemFromOrder - Order tenant isolation violation', [
                            'order_id' => $orderId,
                            'order_tenant_id' => $orderTenantId,
                            'tenant_id' => $tenantId
                        ]);
                        $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 403);
                        return;
                    }
                }

                // Check if approval required - create approval request instead of direct delete
                $approvalService = \App\Core\DependencyFactory::getOrderEditApprovalService();
                $businessId = \App\Core\TenantResolver::resolve();
                $requiresApproval = $approvalService->requiresApproval($_SESSION['user_id'] ?? '', $businessId);
                
                if ($requiresApproval) {
                    if ($approvalService->hasPendingApprovalForOrderItem($orderItemId)) {
                        $this->toastNotificationService->sendApiResponse('error', 'Bu ürün için zaten bekleyen onay talebi var', [], 400);
                        return;
                    }
                    $tableId = $order['table_id'] ?? '';
                    $table = !empty($tableId) ? $this->tableService->getTableById($tableId) : null;
                    $itemName = $orderItem['menu_item_name'] ?? $orderItem['item_name'] ?? $orderItem['name'] ?? 'Ürün';
                    $userId = $_SESSION['user_id'] ?? '';
                    $userName = $_SESSION['user_name'] ?? $_SESSION['username'] ?? $_SESSION['first_name'] ?? 'Kasiyer';
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
                        'order_id' => $orderId,
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
                        $this->apiResponse(['success' => true, 'approval_pending' => true]);
                        return;
                    }
                    $this->toastNotificationService->sendApiResponse('error', 'Onay talebi oluşturulamadı', [], 500);
                    return;
                }

                // Delete the order item
                $result = $this->orderItemService->deleteOrderItem($orderItemId);

                if ($result) {
                    // CRITICAL FIX: Recalculate total from ALL remaining items (prevents 0 TL drift)
                    $remainingItems = $this->orderItemService->getOrderItemsByOrder($orderId);
                    $newTotal = 0;
                    foreach ($remainingItems as $it) {
                        if (($it['preparation_status'] ?? '') !== 'CANCELLED') {
                            $newTotal += floatval($it['price'] ?? 0) * intval($it['quantity'] ?? 1);
                        }
                    }
                    $this->orderService->updateOrderTotal($orderId, round($newTotal, 2));

                    $this->apiResponse(['success' => true]);
                } else {
                    $this->toastNotificationService->sendApiResponse('error', 'notifications.error.delete_failed', [], 500);
                }
            } else {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
            }
        }
    }

    public function updateOrderItemQuantity() {
        // CRITICAL: Ensure tenant context is set
        $this->ensureTenantContext();
        
        // Check permission
        if (!$this->checkPermissionOrFail('orders.edit')) {
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Validate CSRF token (check body first, then header fallback)
            $requestData = \App\Core\RequestParser::getRequestData();
            $csrfToken = $requestData['csrf_token'] ?? \App\Core\Security\CSRFManager::extractTokenFromRequest() ?? '';
            if (!validateCSRFToken($csrfToken)) {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.csrf_invalid', [], 403);
                return;
            }

            $orderItemId = $requestData['order_item_id'] ?? '';
            $quantity = intval($requestData['quantity'] ?? 1);

            if (!empty($orderItemId) && $quantity > 0) {
                $orderItem = $this->orderItemService->getOrderItemById($orderItemId);

                if (!$orderItem) {
                    $this->toastNotificationService->sendApiResponse('error', 'notifications.error.not_found', [], 404);
                    return;
                }
                
                // CRITICAL: Verify order belongs to current tenant
                // NOTE: orders table uses tenant_id column (not business_id)
                $orderId = $orderItem['order_id'];
                $order = $this->orderService->getOrderById($orderId);
                if ($order && !$this->isSuperAdmin()) {
                    $tenantId = \App\Core\TenantContext::getId();
                    $orderTenantId = $order['tenant_id'] ?? null;
                    
                    if (!$tenantId || $orderTenantId !== $tenantId) {
                        \App\Core\Logger::warning('PosController::updateOrderItemQuantity - Order tenant isolation violation', [
                            'order_id' => $orderId,
                            'order_tenant_id' => $orderTenantId,
                            'tenant_id' => $tenantId
                        ]);
                        $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 403);
                        return;
                    }
                }
                
                if ($orderItem) {

                    $oldQuantity = intval($orderItem['quantity'] ?? 1);
                    
                    // Check if quantity is being reduced - may need approval
                    if ($quantity < $oldQuantity) {
                        $approvalService = \App\Core\DependencyFactory::getOrderEditApprovalService();
                        $businessId = \App\Core\TenantResolver::resolve();
                        $requiresApproval = $approvalService->requiresApproval($_SESSION['user_id'] ?? '', $businessId);
                        
                        if ($requiresApproval) {
                            if ($approvalService->hasPendingApprovalForOrderItem($orderItemId)) {
                                $this->toastNotificationService->sendApiResponse('error', 'Bu ürün için zaten bekleyen onay talebi var', [], 400);
                                return;
                            }
                            $tableId = $order['table_id'] ?? '';
                            $table = !empty($tableId) ? $this->tableService->getTableById($tableId) : null;
                            $itemName = $orderItem['menu_item_name'] ?? $orderItem['item_name'] ?? $orderItem['name'] ?? 'Ürün';
                            $userId = $_SESSION['user_id'] ?? '';
                            $userName = $_SESSION['user_name'] ?? $_SESSION['username'] ?? $_SESSION['first_name'] ?? 'Kasiyer';
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
                                'order_id' => $orderId,
                                'table_id' => $tableId,
                                'table_name' => $table['name'] ?? '',
                                'order_item_id' => $orderItemId,
                                'action_type' => 'REDUCE_QUANTITY',
                                'old_quantity' => $oldQuantity,
                                'new_quantity' => $quantity,
                                'item_name' => $itemName,
                                'item_price' => $price,
                                'requested_by' => $userId,
                                'requested_by_name' => $userName,
                                'affected_item_snapshot' => $affectedSnapshot,
                            ]);
                            if ($approvalId) {
                                $this->apiResponse(['success' => true, 'approval_pending' => true]);
                                return;
                            }
                            $this->toastNotificationService->sendApiResponse('error', 'Onay talebi oluşturulamadı', [], 500);
                            return;
                        }
                    }
                    
                    // Update the order item quantity
                    $result = $this->orderItemService->updateQuantity($orderItemId, $quantity);

                    if ($result) {
                        // CRITICAL FIX: Recalculate total from ALL items (prevents 0 TL drift)
                        $allItems = $this->orderItemService->getOrderItemsByOrder($orderId);
                        $newTotal = 0;
                        foreach ($allItems as $it) {
                            if (($it['preparation_status'] ?? '') !== 'CANCELLED') {
                                $newTotal += floatval($it['price'] ?? 0) * intval($it['quantity'] ?? 1);
                            }
                        }
                        $this->orderService->updateOrderTotal($orderId, round($newTotal, 2));
                        
                        // Log activity if quantity was reduced
                        if ($quantity < $oldQuantity) {
                            try {
                                $activityLogService = \App\Core\DependencyFactory::getTableActivityLogService();
                                $performer = \App\Services\TableActivityLogService::getPerformerInfo();
                                $tableId = $order['table_id'] ?? '';
                                $table = !empty($tableId) ? $this->tableService->getTableById($tableId) : null;
                                $itemName = $orderItem['menu_item_name'] ?? $orderItem['item_name'] ?? $orderItem['name'] ?? 'Ürün';
                                $priceDiff = floatval($orderItem['price'] ?? 0) * ($oldQuantity - $quantity);
                                
                                $activityLogService->logQuantityReduced(array_merge($performer, [
                                    'business_id' => \App\Services\TableActivityLogService::getBusinessId(),
                                    'table_id' => $tableId,
                                    'table_name' => $table['name'] ?? '',
                                    'order_id' => $orderId,
                                    'order_item_id' => $orderItemId,
                                    'item_name' => $itemName,
                                    'old_quantity' => $oldQuantity,
                                    'new_quantity' => $quantity,
                                    'item_price' => floatval($orderItem['price'] ?? 0),
                                    'total_affected' => $priceDiff,
                                ]));
                            } catch (\Exception $e) { /* Non-critical */ }
                        }

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
    }
    
    /**
     * Update order item group - consolidate multiple same-product items and set new quantity
     * POST /pos/update-order-item-group { order_item_ids: [...], quantity: n }
     */
    public function updateOrderItemGroup() {
        $this->ensureTenantContext();
        if (!$this->checkPermissionOrFail('orders.edit')) return;
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->apiResponse(['success' => false, 'error' => 'Method not allowed'], 405);
            return;
        }
        $requestData = \App\Core\RequestParser::getRequestData();
        if (!validateCSRFToken($requestData['csrf_token'] ?? \App\Core\Security\CSRFManager::extractTokenFromRequest() ?? '')) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.csrf_invalid', [], 403);
            return;
        }
        $ids = $requestData['order_item_ids'] ?? [];
        $newQuantity = intval($requestData['quantity'] ?? 0);
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
        $totalCurrent = 0;
        foreach ($ids as $id) {
            $it = $this->orderItemService->getOrderItemById($id);
            if ($it && ($it['order_id'] ?? '') === $orderId) {
                $totalCurrent += intval($it['quantity'] ?? 1);
            }
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
        $this->apiResponse(['success' => true]);
    }
    
    /**
     * Delete all orders for a table (direct - no approval needed)
     * POST /api/pos/delete-all-table-orders
     */
    public function deleteAllTableOrders() {
        $this->ensureTenantContext();
        
        if (!$this->checkPermissionOrFail('orders.edit')) {
            return;
        }
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->apiResponse(['success' => false, 'error' => 'Method not allowed'], 405);
            return;
        }
        
        $requestData = \App\Core\RequestParser::getRequestData();
        $csrfToken = $requestData['csrf_token'] ?? \App\Core\Security\CSRFManager::extractTokenFromRequest() ?? '';
        if (!validateCSRFToken($csrfToken)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.csrf_invalid', [], 403);
            return;
        }
        
        $tableId = $requestData['table_id'] ?? '';
        
        if (empty($tableId)) {
            $this->apiResponse(['success' => false, 'error' => 'Masa ID gerekli'], 400);
            return;
        }
        
        // CRITICAL: Verify table belongs to current tenant
        $table = $this->tableService->getTableById($tableId);
        if (!$table) {
            $this->apiResponse(['success' => false, 'error' => 'Masa bulunamadı'], 404);
            return;
        }
        
        if (!$this->isSuperAdmin()) {
            $tenantId = \App\Core\TenantContext::getId();
            $tableBusinessId = $table['tenant_id'] ?? null;
            if (!$tenantId || !$tableBusinessId || $tableBusinessId !== $tenantId) {
                $this->apiResponse(['success' => false, 'error' => 'Yetkisiz erişim'], 403);
                return;
            }
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
                $this->apiResponse(['success' => false, 'error' => 'Bu masa için zaten bekleyen onay talebi var'], 400);
                return;
            }
            $userId = $_SESSION['user_id'] ?? '';
            $userName = $_SESSION['user_name'] ?? $_SESSION['username'] ?? $_SESSION['first_name'] ?? 'Kasiyer';
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
            $servedStatus = ConstantsHelper::getOrderStatus('CANCELLED');
            $this->orderService->updateOrderStatus($orderId, $servedStatus ?: 'CANCELLED');
            $this->orderService->updateOrderTotal($orderId, 0);
            
            // Cancel any pending/printing print queue entries for this order
            try {
                $db = \App\Core\DependencyFactory::getDatabase();
                $db->prepare("
                    UPDATE receipt_print_queue 
                    SET status = 'EXPIRED', error_message = 'Order cancelled'
                    WHERE status IN ('PENDING', 'PRINTING')
                    AND (
                        JSON_EXTRACT(print_data, '$.order_id') = ?
                        OR JSON_EXTRACT(print_data, '$.order_id') = ?
                    )
                ")->execute([$orderId, (string)$orderId]);
            } catch (\Exception $e) {
                // Non-critical - jobs will expire naturally
            }
            
            $deletedCount++;
        }
        
        // Set table to FREE
        $this->tableService->updateTableStatus($tableId, 'FREE');
        
        // Log activity
        try {
            $activityLogService = \App\Core\DependencyFactory::getTableActivityLogService();
            $performer = \App\Services\TableActivityLogService::getPerformerInfo();
            $activityLogService->logAllOrdersDeleted(array_merge($performer, [
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
}