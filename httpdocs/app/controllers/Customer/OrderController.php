<?php
namespace App\Controllers\Customer;

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../../core/DependencyFactory.php';

use App\Core\Controller;

class OrderController extends Controller {
    
    protected $orderService;
    protected $orderItemService;
    protected $receiptService;
    
    public function __construct() {
        parent::__construct();
        try {
            $this->orderService = \App\Core\DependencyFactory::getOrderService();
            $this->orderItemService = \App\Core\DependencyFactory::getOrderItemService();
            $this->receiptService = \App\Core\DependencyFactory::getReceiptService();
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('Failed to initialize OrderController dependencies', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
            // Services will be null, methods should handle this
        }
    }
    
    /**
     * Müşteri sipariş geçmişi listesi
     */
    public function index() {
        try {
            $this->requireLogin();
            
            // Ensure tenant context is set with error handling
            try {
                $this->ensureTenantContext();
            } catch (\Exception $e) {
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::error('Failed to ensure tenant context in OrderController', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
                // Continue - tenant context may not be critical for all operations
            }
            
            $customerId = $_SESSION['customer_id'] ?? \App\Core\SessionManager::get('customer_id') ?? null;
            
            if (!$customerId) {
                $this->toastNotificationService->setFlash('error', 'İşletme bilgisi bulunamadı');
                header('Location: ' . BASE_URL . '/business/dashboard');
                exit;
            }
            
            // Get query parameters for filtering
            try {
                $queryParams = \App\Core\RequestParser::getQueryParams();
            } catch (\Exception $e) {
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::error('Failed to get query params', [
                        'error' => $e->getMessage()
                    ]);
                }
                $queryParams = [];
            }
            
            $status = $queryParams['status'] ?? 'all';
            $startDate = $queryParams['start_date'] ?? null;
            $endDate = $queryParams['end_date'] ?? null;
            $page = max(1, intval($queryParams['page'] ?? 1));
            $perPage = 20;
            
            // Get orders for this customer (tenant context) with error handling
            $orderRepo = null;
            try {
                $orderRepo = \App\Core\DependencyFactory::getOrderRepository();
            } catch (\Exception $e) {
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::error('Failed to get OrderRepository', [
                        'error' => $e->getMessage(),
                        'customer_id' => $customerId
                    ]);
                }
                throw new \Exception('Sipariş veritabanına erişilemedi');
            }
            
            $criteria = [];
            
            // Filter by status if specified
            if ($status !== 'all') {
                $criteria['status'] = $status;
            }
            
            // Filter by date range if specified
            if ($startDate && $endDate) {
                // Date filtering will be done in the query
                $criteria['start_date'] = $startDate;
                $criteria['end_date'] = $endDate;
            }
            
            // Get all orders for this tenant with error handling
            $allOrders = [];
            try {
                $allOrders = $orderRepo->findAll($criteria);
                // Ensure it's an array
                if (!is_array($allOrders)) {
                    $allOrders = [];
                }
            } catch (\Exception $e) {
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::error('Failed to get orders', [
                        'error' => $e->getMessage(),
                        'customer_id' => $customerId,
                        'criteria' => $criteria
                    ]);
                }
                // Continue with empty array
                $allOrders = [];
            }
            
            // Filter by date range if specified
            if ($startDate && $endDate && !empty($allOrders)) {
                try {
                    $allOrders = array_filter($allOrders, function($order) use ($startDate, $endDate) {
                        $orderDate = date('Y-m-d', strtotime($order['created_at'] ?? ''));
                        return $orderDate >= $startDate && $orderDate <= $endDate;
                    });
                    $allOrders = array_values($allOrders);
                } catch (\Exception $e) {
                    if (class_exists('\App\Core\Logger')) {
                        \App\Core\Logger::error('Failed to filter orders by date', [
                            'error' => $e->getMessage()
                        ]);
                    }
                    // Continue with unfiltered orders
                }
            }
            
            // Pagination
            $totalOrders = count($allOrders);
            $totalPages = ceil($totalOrders / $perPage);
            $offset = ($page - 1) * $perPage;
            $orders = array_slice($allOrders, $offset, $perPage);
            
            // Get order items for each order with error handling
            foreach ($orders as &$order) {
                try {
                    if ($this->orderItemService && isset($order['order_id'])) {
                        $orderItems = $this->orderItemService->getOrderItemsByOrder($order['order_id']);
                        $order['items'] = is_array($orderItems) ? $orderItems : [];
                        $order['items_count'] = count($order['items']);
                    } else {
                        $order['items'] = [];
                        $order['items_count'] = 0;
                    }
                } catch (\Exception $e) {
                    if (class_exists('\App\Core\Logger')) {
                        \App\Core\Logger::error('Failed to get order items', [
                            'error' => $e->getMessage(),
                            'order_id' => $order['order_id'] ?? null
                        ]);
                    }
                    $order['items'] = [];
                    $order['items_count'] = 0;
                }
            }
            unset($order);
            
            // Get order statistics
            $stats = [
                'total' => $totalOrders,
                'pending' => count(array_filter($allOrders, fn($o) => ($o['status'] ?? '') === 'PENDING')),
                'preparing' => count(array_filter($allOrders, fn($o) => ($o['status'] ?? '') === 'PREPARING')),
                'ready' => count(array_filter($allOrders, fn($o) => ($o['status'] ?? '') === 'READY')),
                'served' => count(array_filter($allOrders, fn($o) => ($o['status'] ?? '') === 'SERVED')),
                'cancelled' => count(array_filter($allOrders, fn($o) => ($o['status'] ?? '') === 'CANCELLED')),
            ];
            
            $data = [
                'orders' => $orders,
                'stats' => $stats,
                'current_status' => $status,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'page' => $page,
                'total_pages' => $totalPages,
                'total_orders' => $totalOrders,
                'per_page' => $perPage,
                'page_title' => 'Sipariş Geçmişi'
            ];
            
            $this->view('customer/orders', $data);
            
        } catch (\Throwable $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error("Customer/OrderController::index - Error", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'customer_id' => $_SESSION['customer_id'] ?? null
                ]);
            }
            
            $this->toastNotificationService->setFlash('error', 'Sipariş geçmişi yüklenirken bir hata oluştu.');
            header('Location: ' . BASE_URL . '/business/dashboard');
            exit;
        }
    }
    
    /**
     * Sipariş detayı
     */
    public function detail($orderId) {
        $this->requireLogin();
        
        try {
            // Ensure tenant context is set
            $this->ensureTenantContext();
            
            $customerId = $_SESSION['customer_id'] ?? \App\Core\SessionManager::get('customer_id') ?? null;
            
            if (!$customerId) {
                $this->toastNotificationService->setFlash('error', 'İşletme bilgisi bulunamadı');
                header('Location: ' . BASE_URL . '/business/dashboard');
                exit;
            }
            
            // Get order
            $order = $this->orderService->getOrderById($orderId);
            
            if (!$order) {
                $this->toastNotificationService->setFlash('error', 'Sipariş bulunamadı');
                header('Location: ' . BASE_URL . '/customer/orders');
                exit;
            }
            
            // Verify order belongs to this customer (tenant check)
            // This is handled by tenant context, but we can add extra verification if needed
            
            // Get order items
            $orderItems = $this->orderItemService->getOrderItemsByOrder($orderId);
            
            // Get receipt if exists
            $receipt = null;
            try {
                $receiptRepo = $this->receiptService->getRepository();
                $receipts = $receiptRepo->getByOrder($orderId);
                if (!empty($receipts)) {
                    $receipt = $receipts[0];
                }
            } catch (\Exception $e) {
                // Receipt not found, continue
            }
            
            // Get table info if exists
            $table = null;
            if (!empty($order['table_id'])) {
                try {
                    $tableService = \App\Core\DependencyFactory::getTableService();
                    $table = $tableService->getTableById($order['table_id']);
                } catch (\Exception $e) {
                    // Table not found, continue
                }
            }
            
            $data = [
                'order' => $order,
                'order_items' => $orderItems,
                'receipt' => $receipt,
                'table' => $table,
                'page_title' => 'Sipariş Detayı'
            ];
            
            $this->view('customer/order_detail', $data);
            
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error("Customer/OrderController::detail - Error", [
                    'error' => $e->getMessage(),
                    'order_id' => $orderId,
                    'trace' => $e->getTraceAsString()
                ]);
            }
            
            $this->toastNotificationService->setFlash('error', 'Sipariş detayı yüklenirken bir hata oluştu.');
            header('Location: ' . BASE_URL . '/customer/orders');
            exit;
        }
    }
}
