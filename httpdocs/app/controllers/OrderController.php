<?php
namespace App\Controllers;

require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../core/DependencyFactory.php';
use App\Core\Helpers\ConstantsHelper;

class OrderController extends \App\Core\Controller {
    protected $orderService;
    protected $tableService;
    protected $orderItemService;
    protected $menuItemService;

    public function __construct() {
        parent::__construct(); // Initialize auth and other services
        $this->orderService = \App\Core\DependencyFactory::getOrderService();
        $this->tableService = \App\Core\DependencyFactory::getTableService();
        $this->orderItemService = \App\Core\DependencyFactory::getOrderItemService();
        $this->menuItemService = \App\Core\DependencyFactory::getMenuItemService();
    }
    
    public function index() {
        $this->checkAuth();
        
        // Super admin kontrolü
        $isSuperAdmin = $this->isSuperAdmin();
        
        // İşletme ID'sini query parametresinden al (super admin için)
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $businessId = $queryParams['business_id'] ?? null;
        
        if ($isSuperAdmin) {
            if ($businessId) {
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
            } else {
                // Super admin için business_id yoksa, tüm işletmelerin siparişlerini göster
                // Tenant context set etmeden devam et (tüm işletmelerin siparişlerini görmek için)
                // Bu durumda tenant context set edilmeyecek, böylece tüm siparişler görünecek
            }
        }
        
        // Tenant context'i ensure et (super admin için business_id varsa zaten set edildi)
        // Super Admin için business_id yoksa tenant context set etme (tüm siparişleri görmek için)
        if ($isSuperAdmin && !$businessId) {
            // Super Admin ve business_id yok - tenant context set etme, tüm siparişleri göster
        } else {
            $this->ensureTenantContext();
        }
        
        // NOTE: The orders list is loaded client-side via GET /api/orders
        // (APIController@getOrders, backed by the canonical OrderService /
        // OrderRepository). The view does not consume a server-rendered order
        // list, so we intentionally avoid an unused DB round-trip here.
        $data = [
            'is_super_admin' => $isSuperAdmin,
            'business_id' => $businessId
        ];
        
        $this->view('admin/orders', $data);
    }
    
    public function detail() {
        // CRITICAL: Ensure tenant context is set
        $this->ensureTenantContext();
        
        // CRITICAL: If tenant context is not set, try to set it from session
        if (!\App\Core\TenantContext::isSet()) {
            $customerId = \App\Core\TenantResolver::resolve();
            if ($customerId) {
                try {
                    $customerService = \App\Core\DependencyFactory::getCustomerService();
                    $customer = $customerService->getById($customerId);
                    if ($customer) {
                        \App\Core\TenantContext::set($customer);
                    }
                } catch (\Exception $e) {
                    if (class_exists('\App\Core\Logger')) {
                        \App\Core\Logger::error('Failed to set tenant context from session', [
                            'customer_id' => $customerId,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }
        }
        
        $this->requirePermission('orders.view');
        
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $orderId = $queryParams['id'] ?? '';
        
        $order = $this->orderService->getOrderById($orderId);
        if (!$order) {
            $this->toastNotificationService->setFlash('error', 'notifications.error.order_not_found');
            \App\Core\HelperLoader::ensureLoaded();
            header('Location: ' . getAdminUrl('orders'));
            exit;
        }
        
        // CRITICAL: Verify tenant isolation (unless super admin)
        // NOTE: orders table uses tenant_id column (not business_id)
        if (!$this->isSuperAdmin()) {
            $tenantId = \App\Core\TenantContext::getId();
            $orderTenantId = $order['tenant_id'] ?? null;
            
            if (!$tenantId || $orderTenantId !== $tenantId) {
                \App\Core\Logger::warning('OrderController::detail - Tenant isolation violation', [
                    'order_id' => $orderId,
                    'order_tenant_id' => $orderTenantId,
                    'tenant_id' => $tenantId
                ]);
                $this->toastNotificationService->setFlash('error', 'notifications.error.unauthorized');
                \App\Core\HelperLoader::ensureLoaded();
                header('Location: ' . getAdminUrl('orders'));
                exit;
            }
        }
        
        $orderItems = $this->orderItemService->getOrderItemsByOrder($orderId);
        
        $data = [
            'order' => $order,
            'order_items' => $orderItems
        ];
        
        $this->view('admin/order_detail', $data);
    }
    
    public function updateStatus() {
        // CRITICAL: Ensure tenant context is set
        $this->ensureTenantContext();
        
        if (!$this->hasPermission('orders.process')) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 401);
            return;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $requestData = \App\Core\RequestParser::getRequestData();
            $orderId = $requestData['order_id'] ?? '';
            $status = $requestData['status'] ?? '';
            
            $validStatuses = [
                ConstantsHelper::getOrderStatus('PENDING'),
                ConstantsHelper::getOrderStatus('PREPARING'),
                ConstantsHelper::getOrderStatus('READY'),
                ConstantsHelper::getOrderStatus('SERVED'),
                ConstantsHelper::getOrderStatus('CANCELLED'),
                ConstantsHelper::getOrderStatus('ISSUE'),
                'ON_DELIVERY', 'DELIVERED'
            ];
            
            if (!empty($orderId) && in_array($status, $validStatuses)) {
                // CRITICAL: Verify tenant isolation before update
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
                        \App\Core\Logger::warning('OrderController::updateStatus - Tenant isolation violation', [
                            'order_id' => $orderId,
                            'order_tenant_id' => $orderTenantId,
                            'tenant_id' => $tenantId
                        ]);
                        $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 403);
                        return;
                    }
                }
                
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
    
    public function create() {
        // CRITICAL: Ensure tenant context is set
        $this->ensureTenantContext();
        $this->requirePermission('orders.create');
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $requestData = \App\Core\RequestParser::getRequestData();
            $tableId = $requestData['table_id'] ?? '';
            $items = json_decode($requestData['items'] ?? '[]', true);
            $customerNote = $requestData['customer_note'] ?? '';
            $staffName = $_SESSION['username'] ?? '';
            
            // CRITICAL: Verify table belongs to current tenant
            if (!empty($tableId)) {
                $table = $this->tableService->getTableById($tableId);
                if (!$table) {
                    $this->toastNotificationService->setFlash('error', 'notifications.error.table_not_found');
                    header('Location: ' . BASE_URL . '/pos');
                    exit;
                }
                
                // Check tenant isolation (unless super admin)
                if (!$this->isSuperAdmin()) {
                    $tenantId = \App\Core\TenantContext::getId();
                    $tableBusinessId = $table['tenant_id'] ?? null;
                    
                    if (!$tenantId || $tableBusinessId !== $tenantId) {
                        \App\Core\Logger::warning('OrderController::create - Table tenant isolation violation', [
                            'table_id' => $tableId,
                            'table_business_id' => $tableBusinessId,
                            'tenant_id' => $tenantId
                        ]);
                        $this->toastNotificationService->setFlash('error', 'notifications.error.unauthorized');
                        header('Location: ' . BASE_URL . '/pos');
                        exit;
                    }
                }
            }
            
            // Validate order data
            $orderValidationData = [
                'table_id' => $tableId,
                'items' => $items,
                'customer_note' => $customerNote
            ];
            
            $validationResult = $this->validateRequestData($orderValidationData, 'order');
            
            if (!$validationResult['valid']) {
                $firstError = reset($validationResult['errors']);
                $errorMsg = is_array($firstError) ? reset($firstError) : $firstError;
                $this->toastNotificationService->setFlash('error', 'notifications.warning.missing_fields');
                header('Location: ' . BASE_URL . '/pos');
                exit;
            }
            
            $validatedData = $validationResult['data'];
            $customerNote = $validatedData['customer_note'] ?? '';
            
            if (empty($tableId) || empty($items)) {
                $this->toastNotificationService->setFlash('error', 'notifications.warning.missing_fields');
                header('Location: ' . BASE_URL . '/pos');
                exit;
            }
            
            // Total amount calculation is handled by OrderService
            // No need to duplicate business logic here
            
            $orderData = [
                'table_id' => $tableId,
                'items' => $items,
                'customer_note' => $customerNote,
                'order_source' => 'POS',
                'created_by' => $_SESSION['user_id'] ?? 'staff',
                'staff_name' => $staffName
            ];
            
            $result = $this->orderService->placeOrder($orderData);
            
            if ($result && isset($result['order_id'])) {
                $this->toastNotificationService->setFlash('success', 'notifications.success.order_created');
            } else {
                $this->toastNotificationService->setFlash('error', 'notifications.error.create_failed');
            }
            
            \App\Core\HelperLoader::ensureLoaded();
            header('Location: ' . getAdminUrl('orders'));
            exit;
        }
    }
    
    public function cancel() {
        // CRITICAL: Ensure tenant context is set
        $this->ensureTenantContext();
        
        if (!$this->hasPermission('orders.delete')) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 401);
            return;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $requestData = \App\Core\RequestParser::getRequestData();
            $orderId = $requestData['order_id'] ?? '';
            
            // CRITICAL: Verify tenant isolation before cancel
            // NOTE: orders table uses tenant_id column (not business_id)
            if (!empty($orderId)) {
                $order = $this->orderService->getOrderById($orderId);
                if ($order && !$this->isSuperAdmin()) {
                    $tenantId = \App\Core\TenantContext::getId();
                    $orderTenantId = $order['tenant_id'] ?? null;
                    
                    if (!$tenantId || $orderTenantId !== $tenantId) {
                        \App\Core\Logger::warning('OrderController::cancel - Tenant isolation violation', [
                            'order_id' => $orderId,
                            'order_tenant_id' => $orderTenantId,
                            'tenant_id' => $tenantId
                        ]);
                        $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 403);
                        return;
                    }
                }
            }
            
            $requestData = \App\Core\RequestParser::getRequestData();
            $orderId = $requestData['order_id'] ?? '';
            
            if (!empty($orderId)) {
                $result = $this->orderService->updateOrderStatus($orderId, 'CANCELLED');
                
                if ($result) {
                    $this->apiResponse(['success' => true]);
                } else {
                        $this->toastNotificationService->sendApiResponse('error', 'notifications.error.delete_failed', [], 500);
                }
            } else {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
            }
        }
    }
    
    public function getForKitchen() {
        if (!$this->hasPermission('kitchen.view')) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 401);
            return;
        }
        
        $orders = $this->orderService->getForKitchen();
        $this->apiResponse($orders);
    }
    
    public function updateQuantity() {
        if (!$this->hasPermission('orders.edit')) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 401);
            return;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $requestData = \App\Core\RequestParser::getRequestData();
            $orderItemId = $requestData['order_item_id'] ?? '';
            $delta = intval($requestData['delta'] ?? 0);
            
            if (!empty($orderItemId)) {
                $orderItem = $this->orderItemService->getOrderItemById($orderItemId);
                
                if ($orderItem) {
                    $newQuantity = $orderItem['quantity'] + $delta;
                    
                    if ($newQuantity <= 0) {
                        $result = $this->orderItemService->deleteOrderItem($orderItemId);
                    } else {
                        $result = $this->orderItemService->updateQuantity($orderItemId, $newQuantity);
                    }
                    
                    if ($result) {
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
    
    public function getOrder() {
        $this->requirePermission('orders.view');
        
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $orderId = $queryParams['id'] ?? $queryParams['order_id'] ?? '';
        if (empty($orderId)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
            return;
        }
        
        $order = $this->orderService->getOrderById($orderId);
        if ($order) {
            $orderItems = $this->orderItemService->getOrderItemsByOrder($orderId);
            
            // Format order items for frontend (map item_name to name, include customizations for grouping)
            $formattedItems = [];
            foreach ($orderItems as $item) {
                $excl = $item['excluded_ingredients'] ?? [];
                $extras = $item['selected_extras'] ?? [];
                if (is_string($excl)) {
                    $excl = json_decode($excl, true) ?: [];
                }
                if (is_string($extras)) {
                    $extras = json_decode($extras, true) ?: [];
                }
                $formattedItems[] = [
                    'order_item_id' => $item['order_item_id'] ?? '',
                    'menu_item_id' => $item['menu_item_id'] ?? '',
                    'variant_id' => $item['variant_id'] ?? null,
                    'selected_extras' => $extras,
                    'excluded_ingredients' => $excl,
                    'name' => $item['item_name'] ?? $item['menu_item_name'] ?? 'Ürün',
                    'quantity' => intval($item['quantity'] ?? 1),
                    'price' => floatval($item['price'] ?? 0),
                    'note' => $item['note'] ?? $item['notes'] ?? '',
                    'item_name' => $item['item_name'] ?? $item['menu_item_name'] ?? 'Ürün', // Keep for backward compatibility
                    'menu_price' => floatval($item['menu_price'] ?? $item['price'] ?? 0)
                ];
            }
            
            $order['items'] = $formattedItems;
            $this->apiResponse($order);
        } else {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.order_not_found', [], 404);
        }
    }
    
    /**
     * Print order via bridge app
     * POST /api/qodmin/order/{id}/print - $id from route path
     */
    public function printOrder($id = null) {
        if (!$this->checkPermissionOrFail('orders.print')) {
            return;
        }
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_request', [], 405);
            return;
        }
        
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $orderId = $id ?? $queryParams['id'] ?? '';
        $requestData = \App\Core\RequestParser::getRequestData();
        
        if (empty($orderId)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
            return;
        }
        
        $printerId = $requestData['printer_id'] ?? null;
        $paymentMethod = $requestData['payment_method'] ?? 'CASH';
        
        $orderPrintService = \App\Core\DependencyFactory::getOrderPrintService();
        $result = $orderPrintService->printOrder($orderId, $printerId, $paymentMethod);
        
        $this->apiResponse($result);
    }
    
    /**
     * Download order PDF
     * GET /api/qodmin/order/{id}/pdf - $id from route path
     */
    public function downloadOrderPDF($id = null) {
        if (!$this->checkPermissionOrFail('orders.print')) {
            return;
        }
        
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $orderId = $id ?? $queryParams['id'] ?? '';
        if (empty($orderId)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
            return;
        }
        
        $paymentMethod = $queryParams['payment_method'] ?? 'CASH';
        
        $orderPrintService = \App\Core\DependencyFactory::getOrderPrintService();
        $receiptData = $orderPrintService->generateOrderPDFData($orderId, $paymentMethod);
        
        if (!$receiptData) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.receipt_not_found', [], 404);
            return;
        }
        
        // Use ReceiptController's PDF view
        $data = [
            'receipt' => $receiptData['receipt'],
            'order' => $receiptData['order'],
            'items' => $receiptData['items'],
            'settings' => $receiptData['settings']
        ];
        
        $this->view('receipt/pdf', $data);
    }
    
    /**
     * Send order PDF to customer email
     * POST /api/qodmin/order/{id}/send-email - $id from route path
     */
    public function sendOrderPDFEmail($id = null) {
        if (!$this->checkPermissionOrFail('orders.print')) {
            return;
        }
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_request', [], 405);
            return;
        }
        
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $orderId = $id ?? $queryParams['id'] ?? '';
        $requestData = \App\Core\RequestParser::getRequestData();
        
        if (empty($orderId)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
            return;
        }
        
        $email = $requestData['email'] ?? '';
        $paymentMethod = $requestData['payment_method'] ?? 'CASH';
        
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->toastNotificationService->sendApiResponse('error', 'Geçerli bir e-posta adresi girin', [], 400);
            return;
        }
        
        // Generate receipt data
        $orderPrintService = \App\Core\DependencyFactory::getOrderPrintService();
        $receiptData = $orderPrintService->generateOrderPDFData($orderId, $paymentMethod);
        
        if (!$receiptData) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.receipt_not_found', [], 404);
            return;
        }
        
        // Generate PDF URL
        $pdfUrl = BASE_URL . '/api/admin/order/' . $orderId . '/pdf?payment_method=' . urlencode($paymentMethod);
        
        // Get order info
        $order = $receiptData['order'];
        $receipt = $receiptData['receipt'];
        $settings = $receiptData['settings'];
        
        $restaurantName = $settings['restaurant_name'] ?? 'Restoran';
        $orderNumber = $order['order_id'] ?? '';
        $receiptNumber = $receipt['receipt_number'] ?? '';
        $totalAmount = number_format(floatval($receipt['total_amount'] ?? 0), 2, ',', '.') . ' ₺';
        $orderDate = date('d.m.Y H:i', strtotime($order['created_at'] ?? 'now'));
        
        // Create email body
        $emailSubject = "Adisyon - {$restaurantName} - {$orderNumber}";
        $emailBody = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #f97316; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
                .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 8px 8px; }
                .info-box { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; }
                .info-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #e9ecef; }
                .info-row:last-child { border-bottom: none; }
                .info-label { font-weight: bold; color: #666; }
                .info-value { color: #333; }
                .pdf-link { display: inline-block; background: #f97316; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; margin-top: 20px; }
                .footer { text-align: center; margin-top: 30px; color: #666; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>{$restaurantName}</h1>
                    <p>Adisyon</p>
                </div>
                <div class='content'>
                    <div class='info-box'>
                        <div class='info-row'>
                            <span class='info-label'>Sipariş No:</span>
                            <span class='info-value'>{$orderNumber}</span>
                        </div>
                        <div class='info-row'>
                            <span class='info-label'>Fiş No:</span>
                            <span class='info-value'>{$receiptNumber}</span>
                        </div>
                        <div class='info-row'>
                            <span class='info-label'>Tarih:</span>
                            <span class='info-value'>{$orderDate}</span>
                        </div>
                        <div class='info-row'>
                            <span class='info-label'>Toplam:</span>
                            <span class='info-value'><strong>{$totalAmount}</strong></span>
                        </div>
                    </div>
                    <div style='text-align: center;'>
                        <a href='{$pdfUrl}' class='pdf-link'>PDF Adisyonu İndir</a>
                    </div>
                    <div class='footer'>
                        <p>Teşekkür ederiz!</p>
                        <p>{$restaurantName}</p>
                    </div>
                </div>
            </div>
        </body>
        </html>
        ";
        
        // Send email
        $emailService = \App\Core\DependencyFactory::getEmailService();
        $result = $emailService->sendEmail($email, $emailSubject, $emailBody);
        
        if ($result) {
            $this->apiResponse([
                'success' => true,
                'message' => 'E-posta başarıyla gönderildi'
            ]);
        } else {
            $this->toastNotificationService->sendApiResponse('error', 'E-posta gönderilemedi', [], 500);
        }
    }
}