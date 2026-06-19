<?php
namespace App\Controllers;

require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../core/DependencyFactory.php';
use App\Core\Helpers\ConstantsHelper;

class KitchenController extends \App\Core\Controller {
    protected $orderService;
    protected $orderItemService;
    protected $menuItemService;
    protected $categoryService;
    protected $constantsService;
    protected $preparationScreenService;
    
    public function __construct() {
        parent::__construct(); // Initialize auth and other services
        $this->orderService = \App\Core\DependencyFactory::getOrderService();
        $this->orderItemService = \App\Core\DependencyFactory::getOrderItemService();
        $this->menuItemService = \App\Core\DependencyFactory::getMenuItemService();
        $this->categoryService = \App\Core\DependencyFactory::getCategoryService();
        $this->constantsService = \App\Core\DependencyFactory::getConstantsService();
        $this->preparationScreenService = \App\Core\DependencyFactory::getPreparationScreenService();
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
                    \App\Core\Logger::debug('Tenant context set in KitchenController::dashboard', [
                        'subdomain' => $subdomain,
                        'tenant_id' => \App\Core\TenantContext::getId()
                    ]);
                }
            } catch (\Exception $e) {
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::error('Failed to set tenant context in KitchenController::dashboard', [
                        'error' => $e->getMessage(),
                        'subdomain' => $subdomain
                    ]);
                }
            }
        }
        
        $this->ensureTenantContext();
        // Use permission-based authorization
        $this->checkPermissionOrFail('kitchen.view');
        
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
                        $this->toastNotificationService->setFlash('warning', 'Mutfak ekranına erişmek için aktif bir paket aboneliğiniz olmalıdır.');
                        header('Location: ' . BASE_URL . '/customer/packages');
                        exit;
                    }
                }
            } catch (\Exception $e) {
                // If subscription check fails, log but allow access (graceful degradation)
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::error('Kitchen subscription check failed', [
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }
        
        // Get active orders that require kitchen preparation (PENDING, PREPARING, READY)
        // This now returns orders with items already filtered to KITCHEN production point only
        $activeOrders = $this->orderService->getActiveOrders(true); // true = kitchen only
        
        // Get inactive statuses for filtering
        $inactiveStatuses = $this->orderService->getInactiveOrderStatuses();
        
        // Filter out inactive orders (safety check - should already be filtered by repository)
        $activeOrders = array_filter($activeOrders, function($order) use ($inactiveStatuses) {
            return !in_array($order['status'] ?? '', $inactiveStatuses);
        });
        
        // Sort by created_at (ASC - oldest first)
        usort($activeOrders, function($a, $b) {
            $timeA = strtotime($a['created_at'] ?? 'now');
            $timeB = strtotime($b['created_at'] ?? 'now');
            return $timeA - $timeB;
        });
        
        // NOTE: Items are already included in $activeOrders from getActiveOrders()
        // Each order now has 'items' field containing only KITCHEN items
        // No need for additional batch loading
        
        // Ensure all orders have items array (for safety)
        foreach ($activeOrders as &$order) {
            if (!isset($order['items'])) {
                $order['items'] = [];
            }
        }
        unset($order);
        
        $kitchenCategories = $this->categoryService->getCategoriesWithKitchenRequirement();
        
        // Get order status constants for view
        $orderStatuses = $this->constantsService->getOrderStatusCodes();
        $activeStatuses = $this->orderService->getActiveOrderStatuses();
        
        $data = [
            'active_orders' => $activeOrders,
            'kitchen_categories' => $kitchenCategories,
            'order_statuses' => $orderStatuses,
            'active_statuses' => $activeStatuses,
            'inactive_statuses' => $inactiveStatuses,
            'is_super_admin' => $isSuperAdmin
        ];
        
        $this->view('kitchen/dashboard', $data);
    }
    
    public function orders() {
        // CRITICAL: Ensure tenant context is set
        $this->ensureTenantContext();
        $this->requirePermission('kitchen.view');
        
        // Get status constants dynamically
        $statusPending = ConstantsHelper::getOrderStatus('PENDING');
        $statusPreparing = ConstantsHelper::getOrderStatus('PREPARING');
        $statusReady = ConstantsHelper::getOrderStatus('READY');
        
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $status = $queryParams['status'] ?? 'pending';
        
        switch ($status) {
            case 'preparing':
                $orders = $this->orderService->getOrdersByStatus($statusPreparing);
                break;
            case 'ready':
                $orders = $this->orderService->getOrdersByStatus($statusReady);
                break;
            case 'all':
                $orders = array_merge(
                    $this->orderService->getOrdersByStatus($statusPending),
                    $this->orderService->getOrdersByStatus($statusPreparing),
                    $this->orderService->getOrdersByStatus($statusReady)
                );
                break;
            case 'pending':
            default:
                $orders = $this->orderService->getOrdersByStatus($statusPending);
                $status = 'pending';
                break;
        }
        
        // Load order items and customizations for each order (N+1 fix: batch query)
        require_once __DIR__ . '/../core/DependencyFactory.php';
        $orderItemService = \App\Core\DependencyFactory::getOrderItemService();
        $customizationService = \App\Core\DependencyFactory::getIngredientCustomizationService();

        $orderIds = array_filter(array_map(fn($o) => $o['order_id'] ?? null, $orders));
        $orderItemsByOrderId = !empty($orderIds) ? $orderItemService->getOrderItemsByOrderIds($orderIds) : [];
        $allOrderItemIds = [];
        foreach ($orderItemsByOrderId as $items) {
            foreach ($items as $it) {
                if (!empty($it['order_item_id'])) {
                    $allOrderItemIds[] = $it['order_item_id'];
                }
            }
        }
        $customizationsByItemId = !empty($allOrderItemIds) ? $customizationService->getByOrderItemIds($allOrderItemIds) : [];

        foreach ($orders as &$order) {
            $oid = $order['order_id'] ?? '';
            $orderItems = $orderItemsByOrderId[$oid] ?? [];
            foreach ($orderItems as &$orderItem) {
                $oiid = $orderItem['order_item_id'] ?? '';
                $customizations = $customizationsByItemId[$oiid] ?? [];
                $orderItem['customizations'] = $customizations;
                $orderItem['customizations_display'] = !empty($customizations) ? $customizationService->formatForDisplay($customizations) : '';
            }
            unset($orderItem);
            $order['items'] = $orderItems;
        }
        unset($order);
        
        $data = [
            'orders' => $orders,
            'status' => $status
        ];
        
        $this->view('kitchen/orders', $data);
    }
    
    public function updateOrderStatus() {
        if (!$this->hasPermission('kitchen.update_status')) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 401);
            return;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Use RequestParser for both JSON and form data (MVC/OOP compliant)
            $requestData = \App\Core\RequestParser::getRequestData();
            $orderId = $requestData['order_id'] ?? '';
            $status = $requestData['status'] ?? '';
            
            // Get valid statuses from service (dynamic, not hardcoded)
            $validStatuses = $this->orderService->getKitchenValidStatuses();
            
            if (!empty($orderId) && in_array($status, $validStatuses)) {
                $result = $this->orderService->updateOrderStatus($orderId, $status);
                
                if ($result) {
                    $this->apiResponse(['success' => true]);
                } else {
                    $this->toastNotificationService->sendApiResponse('error', 'notifications.error.update_failed', [], 500);
                }
            } else {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
            }
        }
    }
    
    public function getOrders() {
        // CRITICAL: Ensure tenant context is set
        $this->ensureTenantContext();
        
        // Check if user has permission
        if (!$this->hasPermission('kitchen.view')) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 401);
            return;
        }
        
        // Get status constants dynamically
        $statusPending = ConstantsHelper::getOrderStatus('PENDING');
        $statusPreparing = ConstantsHelper::getOrderStatus('PREPARING');
        $statusReady = ConstantsHelper::getOrderStatus('READY');
        
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $status = $queryParams['status'] ?? 'all';
        
        switch ($status) {
            case 'preparing':
                $orders = $this->orderService->getOrdersByStatus($statusPreparing);
                break;
            case 'ready':
                $orders = $this->orderService->getOrdersByStatus($statusReady);
                break;
            case 'all':
                // Get active orders with KITCHEN items only (filtered)
                $orders = $this->orderService->getActiveOrders(true); // true = kitchen only
                // Items are already included and filtered
                $this->apiResponse($orders);
                return;
            case 'pending':
            default:
                $orders = $this->orderService->getOrdersByStatus($statusPending);
                break;
        }
        
        // For specific status queries (not 'all'), we still need to load and filter items
        // Get order IDs for batch loading items
        $orderIds = array_column($orders, 'order_id');
        
        // Load items for all orders in one query (prevents N+1)
        if (!empty($orderIds)) {
            $ordersWithItems = $this->orderService->getOrdersWithItems($orderIds);
            
            // Create a map for quick lookup
            $ordersMap = [];
            foreach ($ordersWithItems as $order) {
                $ordersMap[$order['order_id']] = $order;
            }
            
            // Merge items into orders and filter to KITCHEN items only
            foreach ($orders as &$order) {
                if (isset($ordersMap[$order['order_id']])) {
                    $allItems = $ordersMap[$order['order_id']]['items'] ?? [];
                    // Filter items to only KITCHEN production point
                    $kitchenItems = [];
                    foreach ($allItems as $item) {
                        $menuItemId = $item['menu_item_id'] ?? '';
                        if (empty($menuItemId)) {
                            continue;
                        }
                        
                        $menuItem = $this->menuItemService->getMenuItemById($menuItemId);
                        if (!$menuItem) {
                            continue;
                        }
                        
                        // Get production_point
                        $productionPoint = $menuItem['production_point'] ?? null;
                        if (empty($productionPoint) && !empty($menuItem['category_id'])) {
                            $category = $this->categoryService->getCategoryById($menuItem['category_id']);
                            $productionPoint = $category['default_production_point'] ?? null;
                        }
                        
                        // Include item if it's KITCHEN or legacy requires_kitchen
                        if ($productionPoint === 'KITCHEN' || 
                            (isset($menuItem['requires_kitchen']) && intval($menuItem['requires_kitchen']) == 1)) {
                            $kitchenItems[] = $item;
                        }
                    }
                    $order['items'] = $kitchenItems;
                } else {
                    $order['items'] = [];
                }
            }
            unset($order); // Break reference
            
            // Filter out orders with no kitchen items
            $orders = array_values(array_filter($orders, function($order) {
                return !empty($order['items']);
            }));
        } else {
            $orders = [];
        }
        
        $this->apiResponse($orders);
    }
    
    public function markAsPreparing() {
        // Check if user has permission
        if (!$this->hasPermission('kitchen.update_status')) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 401);
            return;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Use RequestParser for both JSON and form data
            $requestData = \App\Core\RequestParser::getRequestData();
            $orderId = $requestData['order_id'] ?? '';
            
            if (!empty($orderId)) {
                $statusPreparing = ConstantsHelper::getOrderStatus('PREPARING');
                $result = $this->orderService->updateOrderStatus($orderId, $statusPreparing);
                if ($result) {
                    $kitchenItemIds = $this->preparationScreenService->getOrderItemIdsForScreen($orderId, 'kitchen_main');
                    if (!empty($kitchenItemIds)) {
                        $this->orderItemService->updatePreparationStatusByIds($kitchenItemIds, 'PREPARING');
                    }
                }
                if ($result) {
                    $this->apiResponse(['success' => true]);
                } else {
                    $this->toastNotificationService->sendApiResponse('error', 'notifications.error.update_failed', [], 500);
                }
            } else {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
            }
        }
    }
    
    public function markAsReady() {
        // Check if user has permission
        if (!$this->hasPermission('kitchen.update_status')) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 401);
            return;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Use RequestParser for both JSON and form data
            $requestData = \App\Core\RequestParser::getRequestData();
            $orderId = $requestData['order_id'] ?? '';
            
            if (!empty($orderId)) {
                $statusReady = defined('ORDER_STATUS_READY') ? ORDER_STATUS_READY : 'READY';
                $result = $this->orderService->updateOrderStatus($orderId, $statusReady);
                if ($result) {
                    $kitchenItemIds = $this->preparationScreenService->getOrderItemIdsForScreen($orderId, 'kitchen_main');
                    if (!empty($kitchenItemIds)) {
                        $this->orderItemService->updatePreparationStatusByIds($kitchenItemIds, 'READY');
                    }
                }
                if ($result) {
                    $this->apiResponse(['success' => true]);
                } else {
                    $this->toastNotificationService->sendApiResponse('error', 'notifications.error.update_failed', [], 500);
                }
            } else {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
            }
        }
    }
    
    public function getOrderByCategory() {
        // Check if user has permission
        if (!$this->hasPermission('kitchen.view')) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 401);
            return;
        }
        
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $categoryId = $queryParams['category_id'] ?? '';
        
        if (!empty($categoryId)) {
            // Get active orders with items (optimized - prevents N+1)
            $activeOrders = $this->orderService->getActiveOrders();
            $orderIds = array_column($activeOrders, 'order_id');
            
            if (!empty($orderIds)) {
                $ordersWithItems = $this->orderService->getOrdersWithItems($orderIds);
            } else {
                $ordersWithItems = [];
            }
            
            // Filter orders that contain items from the specified category
            $filteredOrders = [];
            foreach ($ordersWithItems as $order) {
                $hasCategoryItem = false;
                foreach ($order['items'] ?? [] as $item) {
                    // Get menu item to check category
                    $menuItem = $this->menuItemService->getMenuItemById($item['menu_item_id'] ?? '');
                    if ($menuItem && ($menuItem['category_id'] ?? '') === $categoryId) {
                        $hasCategoryItem = true;
                        break;
                    }
                }
                if ($hasCategoryItem) {
                    $filteredOrders[] = $order;
                }
            }
            
            $this->apiResponse($filteredOrders);
        } else {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
        }
    }
    
    public function reportIssue() {
        // Check if user has permission
        if (!$this->hasPermission('kitchen.update_status')) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 401);
            return;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Use RequestParser for both JSON and form data
            $requestData = \App\Core\RequestParser::getRequestData();
            $orderId = $requestData['order_id'] ?? '';
            $issueDescription = sanitizeInput($requestData['issue_description'] ?? '');
            
            if (!empty($orderId)) {
                // Update order status to issue
                $statusIssue = defined('ORDER_STATUS_ISSUE') ? ORDER_STATUS_ISSUE : 'ISSUE';
                $result = $this->orderService->updateOrderStatus($orderId, $statusIssue);
                
                if ($result) {
                    $this->apiResponse(['success' => true]);
                } else {
                    $this->toastNotificationService->sendApiResponse('error', 'notifications.error.update_failed', [], 500);
                }
            } else {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
            }
        }
    }
}
