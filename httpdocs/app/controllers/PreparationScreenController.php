<?php
namespace App\Controllers;

require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../core/DependencyFactory.php';

class PreparationScreenController extends \App\Core\Controller {
    protected $preparationScreenService;
    protected $orderService;
    protected $orderItemService;
    protected $menuItemService;
    protected $categoryService;
    protected $constantsService;
    
    public function __construct() {
        parent::__construct();
        $this->preparationScreenService = \App\Core\DependencyFactory::getPreparationScreenService();
        $this->orderService = \App\Core\DependencyFactory::getOrderService();
        $this->orderItemService = \App\Core\DependencyFactory::getOrderItemService();
        $this->menuItemService = \App\Core\DependencyFactory::getMenuItemService();
        $this->categoryService = \App\Core\DependencyFactory::getCategoryService();
        $this->constantsService = \App\Core\DependencyFactory::getConstantsService();
    }
    
    /**
     * Dashboard for a specific preparation screen
     */
    public function dashboard($slug) {
        // CRITICAL: Ensure tenant context is set
        $this->ensureTenantContext();
        
        // Get screen by slug
        $screen = $this->preparationScreenService->getScreenBySlug($slug);
        
        if (!$screen) {
            header('Location: ' . BASE_URL . '/unauthorized');
            exit;
        }
        
        // CRITICAL: Verify tenant isolation (unless super admin)
        // Note: getScreenBySlug() already applies tenant filter via repository
        // If screen is found, it belongs to current tenant (tenant isolation is handled at repository level)
        // However, we still verify business_id/tenant_id for additional security
        if (!$this->isSuperAdmin()) {
            $tenantId = \App\Core\TenantContext::getId();
            if (!$tenantId) {
                \App\Core\Logger::warning('PreparationScreenController::dashboard - Tenant ID not set', [
                    'screen_id' => $screen['screen_id'] ?? 'unknown',
                    'slug' => $slug
                ]);
                header('Location: ' . BASE_URL . '/unauthorized');
                exit;
            }
            
            // Check both business_id and tenant_id columns
            // If both are null, repository filter already ensured tenant isolation, so allow access
            $screenBusinessId = $screen['tenant_id'] ?? null;
            $screenTenantId = $screen['tenant_id'] ?? null;
            
            // If both are null, repository filter already ensured tenant isolation
            if ($screenBusinessId === null && $screenTenantId === null) {
                // Repository filter already applied, screen belongs to current tenant
                \App\Core\Logger::debug('PreparationScreenController::dashboard - Screen found via tenant filter (both IDs null)', [
                    'screen_id' => $screen['screen_id'] ?? 'unknown',
                    'slug' => $slug,
                    'tenant_id' => $tenantId
                ]);
            } else {
                // At least one ID is set, verify it matches tenant
                $screenCombinedId = $screenBusinessId ?? $screenTenantId;
                if ($screenCombinedId !== $tenantId) {
                    \App\Core\Logger::warning('PreparationScreenController::dashboard - Screen tenant isolation violation', [
                        'screen_id' => $screen['screen_id'] ?? 'unknown',
                        'screen_business_id' => $screenBusinessId,
                        'screen_tenant_id' => $screenTenantId,
                        'screen_combined_id' => $screenCombinedId,
                        'tenant_id' => $tenantId,
                        'slug' => $slug
                    ]);
                    header('Location: ' . BASE_URL . '/unauthorized');
                    exit;
                }
            }
        }
        
        // Check if screen is active
        if (empty($screen['is_active'])) {
            header('Location: ' . BASE_URL . '/unauthorized');
            exit;
        }
        
        // Check permission: preparation-screen.{slug}.view
        // Fallback: If user has preparation-screens.view permission and screen belongs to their tenant, allow access
        // Note: hasPermission() now automatically checks fallback for dynamic permissions
        $permissionKey = "preparation-screen.{$slug}.view";
        if (!$this->hasPermission($permissionKey)) {
            \App\Core\Logger::warning('PreparationScreenController::dashboard - Permission denied', [
                'slug' => $slug,
                'screen_id' => $screen['screen_id'] ?? 'unknown',
                'user_role' => $_SESSION['role'] ?? 'unknown',
                'permission_key' => $permissionKey,
                'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown'
            ]);
            header('Location: ' . BASE_URL . '/unauthorized');
            exit;
        }
        
        // Get orders for this screen
        $activeOrders = $this->preparationScreenService->getOrdersForScreen($screen['screen_id']);
        
        // Get inactive statuses for filtering
        $inactiveStatuses = $this->orderService->getInactiveOrderStatuses();
        
        // Filter out inactive orders
        $activeOrders = array_filter($activeOrders, function($order) use ($inactiveStatuses) {
            return !in_array($order['status'] ?? '', $inactiveStatuses);
        });
        
        // Get order status constants for view
        $orderStatuses = $this->constantsService->getOrderStatusCodes();
        $activeStatuses = $this->orderService->getActiveOrderStatuses();
        
        $data = [
            'screen' => $screen,
            'active_orders' => $activeOrders,
            'order_statuses' => $orderStatuses,
            'active_statuses' => $activeStatuses,
            'inactive_statuses' => $inactiveStatuses
        ];
        
        $this->view('preparation-screen/dashboard', $data);
    }
    
    /**
     * Get orders for a screen (AJAX)
     */
    public function getOrders($slug) {
        // CRITICAL: Ensure tenant context is set
        $this->ensureTenantContext();
        
        // Get screen by slug
        $screen = $this->preparationScreenService->getScreenBySlug($slug);
        
        if (!$screen) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.not_found', ['item' => 'Ekran'], 404);
            return;
        }
        
        // CRITICAL: Verify tenant isolation (unless super admin)
        if (!$this->isSuperAdmin()) {
            $tenantId = \App\Core\TenantContext::getId();
            $screenBusinessId = $screen['tenant_id'] ?? null;
            
            if (!$tenantId || $screenBusinessId !== $tenantId) {
                \App\Core\Logger::warning('PreparationScreenController::getOrders - Screen tenant isolation violation', [
                    'screen_id' => $screen['screen_id'] ?? 'unknown',
                    'screen_business_id' => $screenBusinessId,
                    'tenant_id' => $tenantId
                ]);
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 403);
                return;
            }
        }
        
        // Check permission
        $permissionKey = "preparation-screen.{$slug}.view";
        if (!$this->hasPermission($permissionKey)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 401);
            return;
        }
        
        // Get orders for this screen
        $orders = $this->preparationScreenService->getOrdersForScreen($screen['screen_id']);
        
        // Filter by status if provided
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $status = $queryParams['status'] ?? 'all';
        
        if ($status !== 'all') {
            $orders = array_filter($orders, function($order) use ($status) {
                return ($order['status'] ?? '') === $status;
            });
        }
        
        $this->apiResponse($orders);
    }
    
    /**
     * Update order status
     */
    public function updateOrderStatus($slug) {
        // CRITICAL: Ensure tenant context is set
        $this->ensureTenantContext();
        
        // Get screen by slug
        $screen = $this->preparationScreenService->getScreenBySlug($slug);
        
        if (!$screen) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.not_found', ['item' => 'Ekran'], 404);
            return;
        }
        
        // CRITICAL: Verify tenant isolation (unless super admin)
        if (!$this->isSuperAdmin()) {
            $tenantId = \App\Core\TenantContext::getId();
            $screenBusinessId = $screen['tenant_id'] ?? null;
            
            if (!$tenantId || $screenBusinessId !== $tenantId) {
                \App\Core\Logger::warning('PreparationScreenController::updateOrderStatus - Screen tenant isolation violation', [
                    'screen_id' => $screen['screen_id'] ?? 'unknown',
                    'screen_business_id' => $screenBusinessId,
                    'tenant_id' => $tenantId
                ]);
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 403);
                return;
            }
        }
        
        // Check permission: update_status gerekli; yoksa aynı ekran için view varsa güncellemeye de izin ver
        // (Nargile vb. hazırlık ekranı personeli sadece view atanmış olsa bile Hazırla/Servis yapabilsin)
        $permissionUpdate = "preparation-screen.{$slug}.update_status";
        $permissionView = "preparation-screen.{$slug}.view";
        if (!$this->hasPermission($permissionUpdate) && !$this->hasPermission($permissionView)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 401);
            return;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $requestData = \App\Core\RequestParser::getRequestData();
            $orderId = $requestData['order_id'] ?? '';
            $status = $requestData['status'] ?? '';
            
            // CRITICAL: Verify order belongs to current tenant
            // NOTE: orders table uses tenant_id column (not business_id)
            if (!empty($orderId)) {
                $order = $this->orderService->getOrderById($orderId);
                if ($order && !$this->isSuperAdmin()) {
                    $tenantId = \App\Core\TenantContext::getId();
                    $orderTenantId = $order['tenant_id'] ?? null;
                    
                    if (!$tenantId || $orderTenantId !== $tenantId) {
                        \App\Core\Logger::warning('PreparationScreenController::updateOrderStatus - Order tenant isolation violation', [
                            'order_id' => $orderId,
                            'order_tenant_id' => $orderTenantId,
                            'tenant_id' => $tenantId
                        ]);
                        $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 403);
                        return;
                    }
                }
            }
            
            // Get valid statuses from service
            $validStatuses = $this->orderService->getKitchenValidStatuses();
            
            if (!empty($orderId) && in_array($status, $validStatuses)) {
                $result = $this->orderService->updateOrderStatus($orderId, $status);
                if ($result) {
                    $screenId = $screen['screen_id'] ?? null;
                    if ($screenId) {
                        $itemIds = $this->preparationScreenService->getOrderItemIdsForScreen($orderId, $screenId);
                        if (!empty($itemIds)) {
                            $this->orderItemService->updatePreparationStatusByIds($itemIds, $status);
                        }
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
}

