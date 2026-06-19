<?php
namespace App\Controllers;

require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../core/DependencyFactory.php';

class ReceiptController extends \App\Core\Controller {
    protected $receiptService;
    protected $receiptTemplateService;
    protected $printerService;
    protected $orderService;
    protected $orderItemService;
    protected $settingsService;
    
    public function __construct() {
        parent::__construct();
        $this->receiptService = \App\Core\DependencyFactory::getReceiptService();
        $this->receiptTemplateService = \App\Core\DependencyFactory::getReceiptTemplateService();
        $this->printerService = \App\Core\DependencyFactory::getPrinterService();
        $this->orderService = \App\Core\DependencyFactory::getOrderService();
        $this->orderItemService = \App\Core\DependencyFactory::getOrderItemService();
        $this->settingsService = \App\Core\DependencyFactory::getSystemSettingsService();
    }
    
    /**
     * View receipt
     * GET /receipt/{id}
     */
    public function viewReceipt() {
        $this->requirePermission('receipt.view');
        
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $receiptId = $queryParams['id'] ?? '';
        if (empty($receiptId)) {
            // Try to get from route parameter
            $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
            if (preg_match('/receipt\/([^\/]+)/', $path, $matches)) {
                $receiptId = $matches[1];
            }
        }
        
        if (empty($receiptId)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
            return;
        }
        
        $receiptData = $this->receiptService->getReceiptData($receiptId);
        if (!$receiptData) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.receipt_not_found', [], 404);
            return;
        }
        if (!empty($receiptData['created_by'])) {
            $userRepo = \App\Core\DependencyFactory::getUserRepository();
            $creator = $userRepo->findById($receiptData['created_by']);
            $receiptData['created_by_name'] = $creator ? trim($creator['name'] ?? '') : '';
            $receiptData['created_by_role'] = '';
            if ($creator && !empty($creator['role_id'])) {
                $roleMapper = \App\Services\RoleMapper::getInstance();
                $receiptData['created_by_role'] = strtoupper((string)($roleMapper->getRoleCode($creator['role_id']) ?? ''));
            }
        }
        if (empty($receiptData['created_by_name'])) {
            $receiptData['created_by_name'] = '';
        }
        
        $orderId = $receiptData['order_id'] ?? '';
        $order = $orderId ? $this->orderService->getOrderById($orderId) : [];
        $items = $orderId ? $this->orderItemService->getOrderItemsByOrder($orderId) : [];
        if (!empty($items)) {
            foreach ($items as &$item) {
                if (empty($item['item_name'])) {
                    $item['item_name'] = $item['name'] ?? $item['menu_item_name'] ?? 'Ürün';
                }
            }
            unset($item);
        }
        $settings = $this->settingsService->getSettings() ?? [];
        if (!is_array($settings)) {
            $settings = [];
        }
        $settings = $this->ensureBusinessNameInSettings($settings);
        
        $orderDetailSection = $this->buildReceiptOrderDetailSection($receiptData, $order, $items, $orderId, $settings);
        $otherReceipts = [];
        if ($orderId !== '') {
            $allForOrder = $this->receiptService->getRepository()->getByOrder($orderId);
            $typeLabels = ['FULL' => 'Ödeme fişi', 'ADISYON' => 'Adisyon', 'PREPARATION' => 'Mutfak', 'PARTIAL' => 'Kısmi'];
            foreach ($allForOrder as $or) {
                if (($or['receipt_id'] ?? '') === ($receiptData['receipt_id'] ?? '')) {
                    continue;
                }
                $otherReceipts[] = [
                    'receipt_id' => $or['receipt_id'] ?? '',
                    'receipt_number' => $or['receipt_number'] ?? '',
                    'receipt_type' => $or['receipt_type'] ?? '',
                    'receipt_type_label' => $typeLabels[$or['receipt_type'] ?? ''] ?? ($or['receipt_type'] ?? ''),
                    'created_at' => $or['created_at'] ?? ''
                ];
            }
        }
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        $isBusiness = (strpos($path, '/business/') !== false);
        $baseUrl = defined('BASE_URL') ? BASE_URL : '';
        $receiptListUrl = $baseUrl . ($isBusiness ? '/business/receipts' : '/qodmin/receipts');
        
        $data = [
            'receipt' => $receiptData,
            'order' => $order,
            'items' => $items,
            'settings' => $settings,
            'order_detail_section' => $orderDetailSection,
            'other_receipts_for_order' => $otherReceipts,
            'receipt_list_url' => $receiptListUrl,
            'embed' => !empty($_GET['embed'])
        ];
        
        $this->view('receipt/view', $data);
    }
    
    /**
     * Receipts list page (history)
     * GET /business/receipts or GET /qodmin/receipts
     * Uses existing DataTable structure (renderDataTable in view).
     */
    public function history() {
        $this->requirePermission('receipt.view');

        $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        $isBusiness = (strpos($path, '/business/') !== false);
        $baseUrl = defined('BASE_URL') ? BASE_URL : '';
        $isSuperAdmin = $this->isSuperAdmin();

        // Super admin: önce işletme seçimi, sonra o işletmenin fişlerini yükle
        if ($isSuperAdmin && !$isBusiness) {
            $queryParams = \App\Core\RequestParser::getQueryParams();
            $businessId = $queryParams['business_id'] ?? null;

            if ($businessId) {
                // Seçilen işletme için tenant context'i set et
                try {
                    $customerService = \App\Core\DependencyFactory::getCustomerService();
                    $customer = $customerService->getById($businessId);
                    if ($customer) {
                        \App\Core\TenantContext::set($customer);
                    }
                } catch (\Exception $e) {
                    if (class_exists('\App\Core\Logger')) {
                        \App\Core\Logger::error('ReceiptController::history - failed to set tenant context', [
                            'business_id' => $businessId, 'error' => $e->getMessage()
                        ]);
                    }
                }
                $startDate = date('Y-m-d', strtotime('-30 days'));
                $endDate = date('Y-m-d');
                $receipts = $this->receiptService->getReceiptsByDateRangeForList($startDate, $endDate);
                $settings = $this->settingsService->getSettings() ?? [];
                $settings = is_array($settings) ? $settings : [];
                $settings = $this->ensureBusinessNameInSettings($settings);
                $businessName = trim($settings['business_name'] ?? $settings['restaurant_name'] ?? '');
            } else {
                // Henüz işletme seçilmedi – sadece seçim ekranı göster
                $receipts = [];
                $businessName = '';
            }

            $this->view('admin/receipts', [
                'receipts'             => $receipts,
                'business_name'        => $businessName,
                'is_super_admin'       => true,
                'selected_business_id' => $businessId ?? null,
                'receipt_view_url'     => $baseUrl . '/receipt/',
                'api_receipt_prefix'   => $baseUrl . '/api/qodmin/receipt',
                'api_receipt_print_url' => $baseUrl . '/api/qodmin/receipt/print'
            ]);
            return;
        }

        $this->ensureTenantContext();

        // Read optional ?start_date / ?end_date / ?date query params so the
        // list view can show historic data. Defaults to the last 30 days.
        $query = \App\Core\RequestParser::getQueryParams();
        $defaultStart = date('Y-m-d', strtotime('-30 days'));
        $defaultEnd   = date('Y-m-d');

        $singleDate = trim((string)($query['date'] ?? ''));
        $startDate  = trim((string)($query['start_date'] ?? ''));
        $endDate    = trim((string)($query['end_date']   ?? ''));

        $isValidDate = static function ($d): bool {
            if (!is_string($d) || $d === '') return false;
            $dt = \DateTime::createFromFormat('Y-m-d', $d);
            return $dt && $dt->format('Y-m-d') === $d;
        };

        if ($isValidDate($singleDate)) {
            $startDate = $singleDate;
            $endDate   = $singleDate;
        } else {
            if (!$isValidDate($startDate)) $startDate = $defaultStart;
            if (!$isValidDate($endDate))   $endDate   = $defaultEnd;
        }
        if (strcmp($startDate, $endDate) > 0) {
            // swap if user submitted reversed range
            [$startDate, $endDate] = [$endDate, $startDate];
        }

        $receipts = $this->receiptService->getReceiptsByDateRangeForList($startDate, $endDate);
        $settings = $this->settingsService->getSettings() ?? [];
        $settings = is_array($settings) ? $settings : [];
        $settings = $this->ensureBusinessNameInSettings($settings);
        $businessName = trim($settings['business_name'] ?? $settings['restaurant_name'] ?? '');

        $this->view('admin/receipts', [
            'receipts'             => $receipts,
            'business_name'        => $businessName,
            'is_super_admin'       => false,
            'filter_start_date'    => $startDate,
            'filter_end_date'      => $endDate,
            'receipt_view_url'     => $baseUrl . '/receipt/',
            'receipt_templates_url' => $isBusiness ? $baseUrl . '/business/receipt-templates' : $baseUrl . '/qodmin/receipt-templates',
            'api_receipt_prefix'    => $isBusiness ? $baseUrl . '/api/business/receipt' : $baseUrl . '/api/qodmin/receipt',
            'api_receipt_print_url' => $isBusiness ? $baseUrl . '/api/business/receipt/print' : $baseUrl . '/api/qodmin/receipt/print'
        ]);
    }
    
    /**
     * Get single receipt as JSON (for receipt list view popup)
     * GET /api/business/receipt/{id} or GET /api/qodmin/receipt/{id}
     * @param string|null $id Receipt id from route (Router passes this as first argument)
     */
    public function getReceipt($id = null) {
        if (!$this->checkPermissionOrFail('receipt.view')) {
            return;
        }
        
        $receiptId = $id ?? \App\Core\RequestParser::getQueryParams()['id'] ?? '';
        if (empty($receiptId)) {
            $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
            if (preg_match('#/receipt/([^/]+)(?:/|$)#', $path, $matches)) {
                $receiptId = $matches[1];
            }
        }
        $receiptId = is_string($receiptId) ? trim($receiptId) : '';
        
        if ($receiptId === '') {
            $this->apiResponse(['error' => 'invalid_data'], 400);
            return;
        }
        
        $receipt = $this->receiptService->getReceiptData($receiptId);
        if (!$receipt) {
            $this->apiResponse(['error' => 'receipt_not_found', 'message' => 'Fiş bulunamadı.'], 404);
            return;
        }
        $receiptId = $receipt['receipt_id'] ?? $receiptId;
        
        // Fişi kesen kasiyer/oluşturan kullanıcı adı ve rolü
        if (!empty($receipt['created_by'])) {
            $userRepo = \App\Core\DependencyFactory::getUserRepository();
            $creator = $userRepo->findById($receipt['created_by']);
            $receipt['created_by_name'] = $creator ? trim($creator['name'] ?? '') : '';
            $receipt['created_by_role'] = '';
            if ($creator && !empty($creator['role_id'])) {
                $roleMapper = \App\Services\RoleMapper::getInstance();
                $receipt['created_by_role'] = strtoupper((string)($roleMapper->getRoleCode($creator['role_id']) ?? ''));
            }
        }
        if (empty($receipt['created_by_name'])) {
            $receipt['created_by_name'] = '';
        }
        
        $orderId = $receipt['order_id'] ?? '';
        $order = $orderId ? $this->orderService->getOrderById($orderId) : [];
        if ($orderId && is_array($order)) {
            if (empty(trim($order['waiter_name'] ?? '')) && !empty(trim($order['staff_name'] ?? ''))) {
                $order['waiter_name'] = trim($order['staff_name']);
            }
            if (empty(trim($order['waiter_name'] ?? '')) && !empty($order['created_by'])) {
                $userRepo = \App\Core\DependencyFactory::getUserRepository();
                $orderUser = $userRepo->findById($order['created_by']);
                if ($orderUser && !empty(trim($orderUser['name'] ?? ''))) {
                    $order['waiter_name'] = trim($orderUser['name']);
                }
            }
        }
        $items = $orderId ? $this->orderItemService->getOrderItemsByOrder($orderId) : [];
        if (!empty($items)) {
            foreach ($items as &$item) {
                if (empty($item['item_name'])) {
                    $item['item_name'] = $item['name'] ?? $item['menu_item_name'] ?? 'Ürün';
                }
            }
            unset($item);
        }
        $settings = $this->settingsService->getSettings() ?? [];
        if (is_array($settings) && isset($settings['business_name'])) {
            // already flat
        } else {
            $settings = is_array($settings) ? $settings : [];
        }
        $settings = $this->ensureBusinessNameInSettings($settings);
        
        $receiptData = [
            'receipt' => $receipt,
            'order' => $order,
            'items' => $items,
            'settings' => $settings
        ];
        $paymentBreakdown = $receipt['payment_breakdown'] ?? $order['payment_breakdown'] ?? null;
        if (is_string($paymentBreakdown)) {
            $paymentBreakdown = json_decode($paymentBreakdown, true);
        }
        if (is_array($paymentBreakdown)) {
            $receiptData['payment_breakdown'] = [
                'cash' => floatval($paymentBreakdown['cash'] ?? 0),
                'card' => floatval($paymentBreakdown['card'] ?? 0)
            ];
        } else {
            $receiptData['payment_breakdown'] = null;
        }
        
        if (!function_exists('formatReceiptForPrint')) {
            require_once __DIR__ . '/../helpers/receipt.php';
        }
        $receiptContent = function_exists('formatReceiptForPrint') ? formatReceiptForPrint($receiptData, false) : '';
        $receiptContentHtml = '<pre class="receipt-text" style="white-space:pre-wrap;font-family:monospace;margin:0;">' . htmlspecialchars($receiptContent, ENT_QUOTES, 'UTF-8') . '</pre>';
        
        $otherReceipts = [];
        if ($orderId !== '') {
            $allForOrder = $this->receiptService->getRepository()->getByOrder($orderId);
            $typeLabels = ['FULL' => 'Ödeme fişi', 'ADISYON' => 'Adisyon', 'PREPARATION' => 'Mutfak', 'PARTIAL' => 'Kısmi'];
            foreach ($allForOrder as $or) {
                if (($or['receipt_id'] ?? '') === $receiptId) {
                    continue;
                }
                $otherReceipts[] = [
                    'receipt_id' => $or['receipt_id'] ?? '',
                    'receipt_number' => $or['receipt_number'] ?? '',
                    'receipt_type' => $or['receipt_type'] ?? '',
                    'receipt_type_label' => $typeLabels[$or['receipt_type'] ?? ''] ?? ($or['receipt_type'] ?? ''),
                    'created_at' => $or['created_at'] ?? ''
                ];
            }
        }
        
        $orderDetailSection = $this->buildReceiptOrderDetailSection($receipt, $order, $items, $orderId, $settings);
        
        $businessNameForResponse = trim($settings['business_name'] ?? $settings['restaurant_name'] ?? '');
        $this->apiResponse([
            'receipt_id' => $receiptId,
            'receipt_content' => $receiptContentHtml,
            'order_id' => $orderId,
            'business_name' => $businessNameForResponse !== '' ? $businessNameForResponse : null,
            'other_receipts_for_order' => $otherReceipts,
            'order_detail_section' => $orderDetailSection
        ]);
    }
    
    /**
     * Get receipt as JSON for order-approval-history page (Alınan ödemeler → Fiş).
     * Aynı sayfayı gören kullanıcı fişi de görsün diye sadece orders.edit yeterli.
     * GET /api/business/order-approvals/receipt-detail?id=xxx
     */
    public function getReceiptForOrderApprovalHistory($id = null) {
        if (method_exists($this, 'ensureTenantContext')) {
            $this->ensureTenantContext();
        }
        if (!$this->checkPermissionOrFail('orders.edit')) {
            return;
        }
        $receiptId = $id ?? \App\Core\RequestParser::getQueryParams()['id'] ?? '';
        $receiptId = is_string($receiptId) ? trim($receiptId) : '';
        if ($receiptId === '') {
            $this->apiResponse(['error' => 'invalid_data'], 400);
            return;
        }
        $receipt = $this->receiptService->getReceiptData($receiptId);
        if (!$receipt) {
            $this->apiResponse(['error' => 'receipt_not_found', 'message' => 'Fiş bulunamadı.'], 404);
            return;
        }
        $receiptId = $receipt['receipt_id'] ?? $receiptId;
        if (!empty($receipt['created_by'])) {
            $userRepo = \App\Core\DependencyFactory::getUserRepository();
            $creator = $userRepo->findById($receipt['created_by']);
            $receipt['created_by_name'] = $creator ? trim($creator['name'] ?? '') : '';
            $receipt['created_by_role'] = '';
            if ($creator && !empty($creator['role_id'])) {
                $roleMapper = \App\Services\RoleMapper::getInstance();
                $receipt['created_by_role'] = strtoupper((string)($roleMapper->getRoleCode($creator['role_id']) ?? ''));
            }
        }
        if (empty($receipt['created_by_name'])) {
            $receipt['created_by_name'] = '';
        }
        $orderId = $receipt['order_id'] ?? '';
        $order = $orderId ? $this->orderService->getOrderById($orderId) : [];
        if ($orderId && is_array($order)) {
            if (empty(trim($order['waiter_name'] ?? '')) && !empty(trim($order['staff_name'] ?? ''))) {
                $order['waiter_name'] = trim($order['staff_name']);
            }
            if (empty(trim($order['waiter_name'] ?? '')) && !empty($order['created_by'])) {
                $userRepo = \App\Core\DependencyFactory::getUserRepository();
                $orderUser = $userRepo->findById($order['created_by']);
                if ($orderUser && !empty(trim($orderUser['name'] ?? ''))) {
                    $order['waiter_name'] = trim($orderUser['name']);
                }
            }
        }
        $items = $orderId ? $this->orderItemService->getOrderItemsByOrder($orderId) : [];
        if (!empty($items)) {
            foreach ($items as &$item) {
                if (empty($item['item_name'])) {
                    $item['item_name'] = $item['name'] ?? $item['menu_item_name'] ?? 'Ürün';
                }
            }
            unset($item);
        }
        $settings = $this->settingsService->getSettings() ?? [];
        $settings = is_array($settings) ? $settings : [];
        $settings = $this->ensureBusinessNameInSettings($settings);
        $receiptData = [
            'receipt' => $receipt,
            'order' => $order,
            'items' => $items,
            'settings' => $settings
        ];
        $paymentBreakdown = $receipt['payment_breakdown'] ?? $order['payment_breakdown'] ?? null;
        if (is_string($paymentBreakdown)) {
            $paymentBreakdown = json_decode($paymentBreakdown, true);
        }
        if (is_array($paymentBreakdown)) {
            $receiptData['payment_breakdown'] = [
                'cash' => floatval($paymentBreakdown['cash'] ?? 0),
                'card' => floatval($paymentBreakdown['card'] ?? 0)
            ];
        } else {
            $receiptData['payment_breakdown'] = null;
        }
        if (!function_exists('formatReceiptForPrint')) {
            require_once __DIR__ . '/../helpers/receipt.php';
        }
        $receiptContent = function_exists('formatReceiptForPrint') ? formatReceiptForPrint($receiptData, false) : '';
        $receiptContentHtml = '<pre class="receipt-text" style="white-space:pre-wrap;font-family:monospace;margin:0;">' . htmlspecialchars($receiptContent, ENT_QUOTES, 'UTF-8') . '</pre>';
        $otherReceipts = [];
        if ($orderId !== '') {
            $allForOrder = $this->receiptService->getRepository()->getByOrder($orderId);
            $typeLabels = ['FULL' => 'Ödeme fişi', 'ADISYON' => 'Adisyon', 'PREPARATION' => 'Mutfak', 'PARTIAL' => 'Kısmi'];
            foreach ($allForOrder as $or) {
                if (($or['receipt_id'] ?? '') === $receiptId) {
                    continue;
                }
                $otherReceipts[] = [
                    'receipt_id' => $or['receipt_id'] ?? '',
                    'receipt_number' => $or['receipt_number'] ?? '',
                    'receipt_type' => $or['receipt_type'] ?? '',
                    'receipt_type_label' => $typeLabels[$or['receipt_type'] ?? ''] ?? ($or['receipt_type'] ?? ''),
                    'created_at' => $or['created_at'] ?? ''
                ];
            }
        }
        $orderDetailSection = $this->buildReceiptOrderDetailSection($receipt, $order, $items, $orderId, $settings);
        $this->apiResponse([
            'receipt_id' => $receiptId,
            'receipt_content' => $receiptContentHtml,
            'order_id' => $orderId,
            'other_receipts_for_order' => $otherReceipts,
            'order_detail_section' => $orderDetailSection
        ]);
    }
    
    /**
     * Fişte işletme adı yoksa mevcut tenant'ın müşteri kaydından (company_name) doldurur.
     * Böylece fişte her zaman gerçek işletme adı (örn. Cadde Cafe) görünür, QORDY/site_name kullanılmaz.
     */
    private function ensureBusinessNameInSettings(array $settings): array {
        $name = trim($settings['business_name'] ?? $settings['restaurant_name'] ?? '');
        if ($name !== '') {
            return $settings;
        }
        try {
            $tenantId = \App\Core\TenantContext::getId();
            if ($tenantId) {
                $customerService = \App\Core\DependencyFactory::getCustomerService();
                $customer = $customerService->getById($tenantId);
                $name = trim($customer['company_name'] ?? $customer['business_name'] ?? '') ?: '';
                if ($name !== '') {
                    $settings['business_name'] = $name;
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }
        return $settings;
    }
    
    /**
     * Build order history and 0 TL explanation for receipt detail (audit/security).
     * Includes: kasiyer (adisyonu alan), masa, fiş tarih/saat, sipariş bilgisi.
     */
    private function buildReceiptOrderDetailSection(array $receipt, array $order, array $items, string $orderId, array $settings = []): string {
        $businessName = trim($settings['business_name'] ?? $settings['restaurant_name'] ?? '');
        $createdByName = trim($receipt['created_by_name'] ?? '');
        $receiptCreatedAt = $receipt['created_at'] ?? '';
        $receiptDateFormatted = $receiptCreatedAt ? date('d.m.Y', strtotime($receiptCreatedAt)) : '-';
        $receiptTimeFormatted = $receiptCreatedAt ? date('H:i', strtotime($receiptCreatedAt)) : '-';
        $tableName = $order['table_name'] ?? '-';
        
        if ($orderId === '') {
            $line = 'Fiş tarih: ' . $receiptDateFormatted . ' ' . $receiptTimeFormatted;
            if ($createdByName !== '') $line .= ' · Adisyonu alan: ' . htmlspecialchars($createdByName);
            if ($businessName !== '' && $createdByName === '') $line .= ' · İşletme: ' . htmlspecialchars($businessName);
            return '<div class="order-detail-audit text-slate-600">' . $line . '</div>';
        }
        $total = floatval($receipt['total_amount'] ?? 0);
        $status = strtoupper(trim($order['status'] ?? ''));
        $statusLabels = [
            'PENDING' => 'Beklemede',
            'PREPARING' => 'Hazırlanıyor',
            'READY' => 'Hazır',
            'SERVED' => 'Müşteriye gitti',
            'CANCELLED' => 'İptal edildi'
        ];
        $statusLabel = $statusLabels[$status] ?? $status;
        $createdAt = $order['created_at'] ?? '';
        $createdFormatted = $createdAt ? date('d.m.Y H:i', strtotime($createdAt)) : '-';
        $itemsCount = count($items);
        $itemsTotalQty = 0;
        foreach ($items as $it) {
            $itemsTotalQty += intval($it['quantity'] ?? 1);
        }
        
        $html = '<div class="order-detail-audit text-slate-600">';
        $html .= 'Masa: ' . htmlspecialchars($tableName) . ' · Fiş tarih: ' . $receiptDateFormatted . ' ' . $receiptTimeFormatted;
        if ($createdByName !== '') {
            $html .= ' · Adisyonu alan: ' . htmlspecialchars($createdByName);
        } elseif ($businessName !== '') {
            $html .= ' · İşletme: ' . htmlspecialchars($businessName);
        }
        $html .= '<br>Sipariş #' . htmlspecialchars($orderId) . ' · ' . htmlspecialchars($createdFormatted) . ' · ' . htmlspecialchars($statusLabel) . ' · ' . $itemsCount . ' ürün, ' . $itemsTotalQty . ' adet.';
        
        if ($total <= 0) {
            $html .= '<div class="mt-2 p-2 bg-amber-50 border border-amber-200 rounded text-amber-900">';
            $html .= '<strong>Neden tutar 0 TL?</strong> ';
            if ($status === 'CANCELLED') {
                $html .= 'Sipariş iptal edildi.';
            } elseif ($itemsCount === 0 || $itemsTotalQty === 0) {
                $html .= 'Tüm kalemler kaldırılmış veya adet sıfırlanmış.';
            } else {
                $html .= 'Siparişte kalem var; özel durum veya indirim nedeniyle 0 TL olabilir.';
            }
            $html .= '</div>';
        } else {
            if ($status === 'SERVED') {
                $html .= ' <span class="text-emerald-600">· Ödeme fişi bu siparişe ait.</span>';
            } elseif ($status === 'CANCELLED') {
                $html .= ' <span class="text-red-600">· Sipariş iptal.</span>';
            }
        }
        
        $html .= '</div>';
        return $html;
    }
    
    /**
     * Print receipt (HTML view optimized for printing)
     * GET /receipt/{id}/print
     */
    public function print() {
        $this->requirePermission('receipt.print');
        
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $receiptId = $queryParams['id'] ?? '';
        if (empty($receiptId)) {
            $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
            if (preg_match('/receipt\/([^\/]+)\/print/', $path, $matches)) {
                $receiptId = $matches[1];
            }
        }
        
        if (empty($receiptId)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
            return;
        }
        
        $receiptData = $this->receiptService->getReceiptData($receiptId);
        if (!$receiptData) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.receipt_not_found', [], 404);
            return;
        }
        if (!empty($receiptData['created_by'])) {
            $userRepo = \App\Core\DependencyFactory::getUserRepository();
            $creator = $userRepo->findById($receiptData['created_by']);
            $receiptData['created_by_name'] = $creator ? trim($creator['name'] ?? '') : '';
            $receiptData['created_by_role'] = '';
            if ($creator && !empty($creator['role_id'])) {
                $roleMapper = \App\Services\RoleMapper::getInstance();
                $receiptData['created_by_role'] = strtoupper((string)($roleMapper->getRoleCode($creator['role_id']) ?? ''));
            }
        }
        if (empty($receiptData['created_by_name'])) {
            $receiptData['created_by_name'] = '';
        }
        
        $orderId = $receiptData['order_id'] ?? '';
        $order = $orderId ? $this->orderService->getOrderById($orderId) : [];
        $items = $orderId ? $this->orderItemService->getOrderItemsByOrder($orderId) : [];
        if (!empty($items)) {
            foreach ($items as &$item) {
                if (empty($item['item_name'])) {
                    $item['item_name'] = $item['name'] ?? $item['menu_item_name'] ?? 'Ürün';
                }
            }
            unset($item);
        }
        $settings = $this->settingsService->getSettings() ?? [];
        if (!is_array($settings)) {
            $settings = [];
        }
        $settings = $this->ensureBusinessNameInSettings($settings);
        
        $paymentBreakdown = $receiptData['payment_breakdown'] ?? $order['payment_breakdown'] ?? null;
        if (is_string($paymentBreakdown)) {
            $paymentBreakdown = json_decode($paymentBreakdown, true);
        }
        if (is_array($paymentBreakdown)) {
            $receiptData['payment_breakdown'] = [
                'cash' => floatval($paymentBreakdown['cash'] ?? 0),
                'card' => floatval($paymentBreakdown['card'] ?? 0)
            ];
        }
        
        // Print to Windows bridge app
        $printerId = $queryParams['printer_id'] ?? null;
        $this->receiptService->printReceipt($receiptId, $printerId);
        
        $orderData = $order;
        
        $data = [
            'receipt' => $receiptData,
            'order' => $orderData,
            'items' => $items,
            'settings' => $settings
        ];
        
        $this->view('receipt/print', $data);
    }
    
    /**
     * Generate PDF receipt
     * GET /receipt/{id}/pdf
     */
    public function pdf() {
        $this->requirePermission('receipt.print');
        
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $receiptId = $queryParams['id'] ?? '';
        if (empty($receiptId)) {
            $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
            if (preg_match('/receipt\/([^\/]+)\/pdf/', $path, $matches)) {
                $receiptId = $matches[1];
            }
        }
        
        if (empty($receiptId)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
            return;
        }
        
        $receiptData = $this->receiptService->getReceiptData($receiptId);
        if (!$receiptData) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.receipt_not_found', [], 404);
            return;
        }
        if (!empty($receiptData['created_by'])) {
            $userRepo = \App\Core\DependencyFactory::getUserRepository();
            $creator = $userRepo->findById($receiptData['created_by']);
            $receiptData['created_by_name'] = $creator ? trim($creator['name'] ?? '') : '';
            $receiptData['created_by_role'] = '';
            if ($creator && !empty($creator['role_id'])) {
                $roleMapper = \App\Services\RoleMapper::getInstance();
                $receiptData['created_by_role'] = strtoupper((string)($roleMapper->getRoleCode($creator['role_id']) ?? ''));
            }
        }
        if (empty($receiptData['created_by_name'])) {
            $receiptData['created_by_name'] = '';
        }
        
        $orderId = $receiptData['order_id'] ?? '';
        $order = $orderId ? $this->orderService->getOrderById($orderId) : [];
        $items = $orderId ? $this->orderItemService->getOrderItemsByOrder($orderId) : [];
        if (!empty($items)) {
            foreach ($items as &$item) {
                if (empty($item['item_name'])) {
                    $item['item_name'] = $item['name'] ?? $item['menu_item_name'] ?? 'Ürün';
                }
            }
            unset($item);
        }
        $settings = $this->settingsService->getSettings() ?? [];
        if (!is_array($settings)) {
            $settings = [];
        }
        $settings = $this->ensureBusinessNameInSettings($settings);
        
        $paymentBreakdownPdf = $receiptData['payment_breakdown'] ?? $order['payment_breakdown'] ?? null;
        if (is_string($paymentBreakdownPdf)) {
            $paymentBreakdownPdf = json_decode($paymentBreakdownPdf, true);
        }
        if (is_array($paymentBreakdownPdf)) {
            $receiptData['payment_breakdown'] = [
                'cash' => floatval($paymentBreakdownPdf['cash'] ?? 0),
                'card' => floatval($paymentBreakdownPdf['card'] ?? 0)
            ];
        }
        
        // For now, return HTML that can be converted to PDF
        $data = [
            'receipt' => $receiptData,
            'order' => $order,
            'items' => $items,
            'settings' => $settings
        ];
        
        $this->view('receipt/pdf', $data);
    }
    
    /**
     * Generate new receipt
     * POST /api/receipt/generate
     */
    public function generate() {
        if (!$this->checkPermissionOrFail('receipt.view')) {
            return;
        }
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_request', [], 405);
            return;
        }
        
        $input = \App\Core\RequestParser::getRequestData();
        
        $orderId = $input['order_id'] ?? '';
        $paymentMethod = $input['payment_method'] ?? 'CASH';
        $receiptType = $input['receipt_type'] ?? 'FULL';
        $discountAmount = floatval($input['discount_amount'] ?? 0);
        
        if (empty($orderId)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
            return;
        }
        
        $receiptData = [
            'order_id' => $orderId,
            'payment_method' => $paymentMethod,
            'receipt_type' => $receiptType,
            'discount_amount' => $discountAmount,
            'created_by' => $_SESSION['user_id'] ?? 'system'
        ];
        
        $result = $this->receiptService->generateReceipt($receiptData);
        
        if ($result) {
            $this->apiResponse([
                'success' => true,
                'receipt_id' => $result['receipt_id'],
                'receipt_number' => $result['receipt_number'],
                'total_amount' => $result['total_amount']
            ]);
        } else {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.create_failed', [], 500);
        }
    }
    
    /**
     * Reprint receipt
     * POST /receipt/{id}/reprint
     */
    public function reprint() {
        if (!$this->checkPermissionOrFail('receipt.print')) {
            return;
        }
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_request', [], 405);
            return;
        }
        
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $receiptId = $queryParams['id'] ?? '';
        if (empty($receiptId)) {
            $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
            if (preg_match('/receipt\/([^\/]+)\/reprint/', $path, $matches)) {
                $receiptId = $matches[1];
            }
        }
        
        if (empty($receiptId)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
            return;
        }
        
        $receiptData = $this->receiptService->reprintReceipt($receiptId);
        
        if ($receiptData) {
            $this->apiResponse([
                'success' => true,
                'receipt' => $receiptData['receipt'],
                'print_url' => BASE_URL . '/receipt/' . $receiptId . '/print'
            ]);
        } else {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.receipt_not_found', [], 404);
        }
    }
    
    /**
     * Void receipt
     * POST /api/receipt/{id}/void
     */
    public function void() {
        if (!$this->checkPermissionOrFail('receipt.void')) {
            return;
        }
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_request', [], 405);
            return;
        }
        
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $receiptId = $queryParams['id'] ?? '';
        if (empty($receiptId)) {
            $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
            if (preg_match('/receipt\/([^\/]+)\/void/', $path, $matches)) {
                $receiptId = $matches[1];
            }
        }
        
        if (empty($receiptId)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
            return;
        }
        
        $input = \App\Core\RequestParser::getRequestData();
        
        $reason = sanitizeInput($input['reason'] ?? '');
        $voidedBy = $_SESSION['user_id'] ?? 'system';
        
        if (empty($reason)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.warning.missing_fields', [], 400);
            return;
        }
        
        $result = $this->receiptService->voidReceipt($receiptId, $reason, $voidedBy);
        
        if ($result) {
            $this->toastNotificationService->sendApiResponse('success', 'notifications.success.receipt_cancelled', [], 200);
        } else {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.receipt_cancel_failed', [], 500);
        }
    }
    
    /**
     * Get receipts list
     * GET /api/receipts
     */
    public function getReceipts() {
        if (!$this->checkPermissionOrFail('receipt.view')) {
            return;
        }
        
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $tableId = $queryParams['table_id'] ?? '';
        $startDate = $queryParams['start_date'] ?? '';
        $endDate = $queryParams['end_date'] ?? '';
        $date = $queryParams['date'] ?? '';
        
        if (!empty($tableId)) {
            $receipts = $this->receiptService->getReceiptsByTable($tableId);
        } elseif (!empty($date)) {
            $receipts = $this->receiptService->getDailyReceipts($date);
        } elseif (!empty($startDate) && !empty($endDate)) {
            $receipts = $this->receiptService->getReceiptsByDateRange($startDate, $endDate);
        } else {
            // Get today's receipts
            $receipts = $this->receiptService->getDailyReceipts(date('Y-m-d'));
        }
        
        $this->apiResponse($receipts);
    }
    
    /**
     * Print receipt to Windows bridge app
     * POST /api/receipt/print
     */
    public function printToPrinter() {
        if (!$this->checkPermissionOrFail('receipt.print')) {
            return;
        }
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_request', [], 405);
            return;
        }
        
        $input = \App\Core\RequestParser::getRequestData();
        
        $receiptId = $input['receipt_id'] ?? '';
        $printerId = $input['printer_id'] ?? null;
        
        if (empty($receiptId)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
            return;
        }
        
        $result = $this->receiptService->printReceipt($receiptId, $printerId);
        
        if (!empty($result['success'])) {
            $this->apiResponse(['success' => true, 'message' => 'Fiş kasiyer yazıcısına gönderildi.'], 200);
        } else {
            $this->apiResponse(['success' => false, 'error' => $result['error'] ?? 'Yazdırılamadı'], 500);
        }
    }
    
    /**
     * Get receipt templates
     * GET /api/receipt/templates
     */
    public function templates() {
        if (!$this->checkPermissionOrFail('receipt.templates')) {
            return;
        }
        
        $templates = $this->receiptTemplateService->getAllTemplates();
        $defaultTemplate = $this->receiptTemplateService->getDefaultTemplate();
        
        $this->apiResponse([
            'templates' => $templates,
            'default_template' => $defaultTemplate
        ]);
    }
    
    /**
     * Save receipt template
     * POST /api/receipt/template/save
     */
    public function saveTemplate() {
        if (!$this->checkPermissionOrFail('receipt.templates.edit')) {
            return;
        }
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_request', [], 405);
            return;
        }
        
        $input = \App\Core\RequestParser::getRequestData();
        
        $templateId = $input['template_id'] ?? null;
        $templateName = sanitizeInput($input['template_name'] ?? '');
        $templateContent = $input['template_content'] ?? '';
        $designData = $input['design_data'] ?? null;
        $isDefault = intval($input['is_default'] ?? 0);
        
        if (empty($templateName) || empty($templateContent)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.warning.missing_fields', [], 400);
            return;
        }
        
        $templateData = [
            'template_name' => $templateName,
            'template_content' => $templateContent,
            'design_data' => $designData ? json_encode($designData) : null,
            'is_default' => $isDefault
        ];
        
        if ($templateId) {
            // Update
            $result = $this->receiptTemplateService->updateTemplate($templateId, $templateData);
        } else {
            // Create
            $result = $this->receiptTemplateService->createTemplate($templateData);
            $templateId = $result;
        }
        
        if ($result) {
            if ($isDefault) {
                $this->receiptTemplateService->setAsDefault($templateId);
            }
            $this->apiResponse(['success' => true, 'template_id' => $templateId]);
        } else {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.save_failed', [], 500);
        }
    }
}

