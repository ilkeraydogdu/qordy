<?php
namespace App\Controllers\Admin;

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../../core/DependencyFactory.php';

use App\Core\Controller;

class ProductSalesController extends Controller {
    protected $orderService;
    protected $orderItemService;
    protected $menuItemService;
    
    public function __construct() {
        parent::__construct();
        $this->orderService = \App\Core\DependencyFactory::getOrderService();
        $this->orderItemService = \App\Core\DependencyFactory::getOrderItemService();
        $this->menuItemService = \App\Core\DependencyFactory::getMenuItemService();
    }
    
    public function index() {
        $isSuperAdmin = $this->isSuperAdmin();
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $businessId = $queryParams['business_id'] ?? null;
        
        if ($isSuperAdmin && $businessId) {
            try {
                $customerService = \App\Core\DependencyFactory::getCustomerService();
                $customer = $customerService->getById($businessId);
                if ($customer) { \App\Core\TenantContext::set($customer); }
            } catch (\Exception $e) {}
        } elseif ($isSuperAdmin && !$businessId) {
            $this->view('admin/product_sales', [
                'is_super_admin' => true,
                'business_id' => null
            ]);
            return;
        }
        
        $this->ensureTenantContext();
        
        $this->view('admin/product_sales', [
            'is_super_admin' => $isSuperAdmin,
            'business_id' => $businessId
        ]);
    }
    
    private function resolveDatetimeRange(string $startDate, string $endDate): array {
        if ($startDate === $endDate) {
            $settingsService = \App\Core\DependencyFactory::getSystemSettingsService();
            $businessRange = $settingsService->getBusinessDateRange();
            
            if ($startDate === $businessRange['date']) {
                return [$businessRange['start_datetime'] ?? $businessRange['start'], $businessRange['end_datetime'] ?? $businessRange['end']];
            }
            
            $historicalRange = $settingsService->getBusinessDateRangeForDate($startDate);
            return [$historicalRange['start_datetime'], $historicalRange['end_datetime']];
        }
        
        return [$startDate . ' 00:00:00', $endDate . ' 23:59:59'];
    }
    
    private function getPaidFilter(): string {
        return "AND (o.is_paid = 1 OR o.payment_method IS NOT NULL)";
    }
    
    public function getData() {
        $this->ensureTenantContext();
        
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $period = $queryParams['period'] ?? 'daily';
        $startDate = $queryParams['start_date'] ?? date('Y-m-d', strtotime('-7 days'));
        $endDate = $queryParams['end_date'] ?? date('Y-m-d');
        $businessId = $queryParams['business_id'] ?? null;
        
        if ($this->isSuperAdmin() && $businessId) {
            try {
                $customerService = \App\Core\DependencyFactory::getCustomerService();
                $customer = $customerService->getById($businessId);
                if ($customer) { \App\Core\TenantContext::set($customer); }
            } catch (\Exception $e) {}
        }
        
        try {
            $db = \App\Core\DependencyFactory::getDatabase();
            $tenantId = \App\Core\TenantContext::getId();
            
            list($startDt, $endDt) = $this->resolveDatetimeRange($startDate, $endDate);
            
            $tenantWhere = '';
            $params = ['start_date' => $startDt, 'end_date' => $endDt];
            if ($tenantId) {
                $tenantWhere = 'AND o.tenant_id = :tenant_id';
                $params['tenant_id'] = $tenantId;
            }
            
            $paidFilter = $this->getPaidFilter();
            
            if ($period === 'daily') {
                $dateGroup = 'DATE(o.created_at)';
            } elseif ($period === 'weekly') {
                $dateGroup = "DATE_FORMAT(o.created_at, '%x-W%v')";
            } else {
                $dateGroup = "DATE_FORMAT(o.created_at, '%Y-%m')";
            }
            
            $sql = "SELECT mi.menu_item_id, mi.name as product_name, c.name as category_name, {$dateGroup} as period_label, SUM(oi.quantity) as total_quantity, SUM(oi.price * oi.quantity) as total_revenue, COUNT(DISTINCT o.order_id) as order_count
                FROM order_items oi INNER JOIN orders o ON oi.order_id = o.order_id INNER JOIN menu_items mi ON oi.menu_item_id = mi.menu_item_id LEFT JOIN categories c ON mi.category_id = c.category_id
                WHERE o.created_at BETWEEN :start_date AND :end_date AND o.status != 'CANCELLED' {$paidFilter} {$tenantWhere}
                GROUP BY mi.menu_item_id, mi.name, c.name, {$dateGroup} ORDER BY {$dateGroup} DESC, total_quantity DESC";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $detailedData = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            $sqlTotals = "SELECT mi.menu_item_id, mi.name as product_name, c.name as category_name, mi.price as current_price, SUM(oi.quantity) as total_quantity, SUM(oi.price * oi.quantity) as total_revenue, COUNT(DISTINCT o.order_id) as order_count, ROUND(AVG(oi.quantity), 1) as avg_per_order
                FROM order_items oi INNER JOIN orders o ON oi.order_id = o.order_id INNER JOIN menu_items mi ON oi.menu_item_id = mi.menu_item_id LEFT JOIN categories c ON mi.category_id = c.category_id
                WHERE o.created_at BETWEEN :start_date AND :end_date AND o.status != 'CANCELLED' {$paidFilter} {$tenantWhere}
                GROUP BY mi.menu_item_id, mi.name, c.name, mi.price ORDER BY total_quantity DESC";
            $stmtTotals = $db->prepare($sqlTotals);
            $stmtTotals->execute($params);
            $productTotals = $stmtTotals->fetchAll(\PDO::FETCH_ASSOC);
            
            $sqlCat = "SELECT c.name as category_name, SUM(oi.quantity) as total_quantity, SUM(oi.price * oi.quantity) as total_revenue, COUNT(DISTINCT mi.menu_item_id) as product_count
                FROM order_items oi INNER JOIN orders o ON oi.order_id = o.order_id INNER JOIN menu_items mi ON oi.menu_item_id = mi.menu_item_id LEFT JOIN categories c ON mi.category_id = c.category_id
                WHERE o.created_at BETWEEN :start_date AND :end_date AND o.status != 'CANCELLED' {$paidFilter} {$tenantWhere}
                GROUP BY c.category_id, c.name ORDER BY total_quantity DESC";
            $stmtCat = $db->prepare($sqlCat);
            $stmtCat->execute($params);
            $categoryTotals = $stmtCat->fetchAll(\PDO::FETCH_ASSOC);
            
            $sqlTrend = "SELECT DATE(o.created_at) as date, SUM(oi.quantity) as total_quantity, SUM(oi.price * oi.quantity) as total_revenue, COUNT(DISTINCT o.order_id) as order_count
                FROM order_items oi INNER JOIN orders o ON oi.order_id = o.order_id
                WHERE o.created_at BETWEEN :start_date AND :end_date AND o.status != 'CANCELLED' {$paidFilter} {$tenantWhere}
                GROUP BY DATE(o.created_at) ORDER BY date ASC";
            $stmtTrend = $db->prepare($sqlTrend);
            $stmtTrend->execute($params);
            $dailyTrend = $stmtTrend->fetchAll(\PDO::FETCH_ASSOC);
            
            $top10 = array_slice($productTotals, 0, 10);
            $grandTotalQty = array_sum(array_column($productTotals, 'total_quantity'));
            $grandTotalRevenue = array_sum(array_column($productTotals, 'total_revenue'));
            
            $this->apiResponse([
                'success' => true,
                'period' => $period,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'detailed_data' => $detailedData,
                'product_totals' => $productTotals,
                'category_totals' => $categoryTotals,
                'daily_trend' => $dailyTrend,
                'top_10_products' => $top10,
                'summary' => [
                    'total_quantity' => (int)$grandTotalQty,
                    'total_revenue' => round((float)$grandTotalRevenue, 2),
                    'total_products' => count($productTotals)
                ]
            ]);
        } catch (\Exception $e) {
            $this->apiResponse(['success' => false, 'error' => $e->getMessage()]);
        }
    }
    
    public function printReceipt() {
        $this->ensureTenantContext();
        $requestData = \App\Core\RequestParser::getRequestData();
        $startDate = $requestData['start_date'] ?? date('Y-m-d');
        $endDate = $requestData['end_date'] ?? date('Y-m-d');
        $businessId = $requestData['business_id'] ?? null;
        
        if ($this->isSuperAdmin() && $businessId) {
            try {
                $customerService = \App\Core\DependencyFactory::getCustomerService();
                $customer = $customerService->getById($businessId);
                if ($customer) { \App\Core\TenantContext::set($customer); }
            } catch (\Exception $e) {}
        }
        
        try {
            $db = \App\Core\DependencyFactory::getDatabase();
            $tenantId = \App\Core\TenantContext::getId();
            list($startDt, $endDt) = $this->resolveDatetimeRange($startDate, $endDate);
            
            $tenantWhere = '';
            $params = ['start_date' => $startDt, 'end_date' => $endDt];
            if ($tenantId) {
                $tenantWhere = 'AND o.tenant_id = :tenant_id';
                $params['tenant_id'] = $tenantId;
            }
            $paidFilter = $this->getPaidFilter();
            
            $sql = "SELECT mi.name as product_name, c.name as category_name, mi.price as current_price, SUM(oi.quantity) as total_quantity, SUM(oi.price * oi.quantity) as total_revenue, AVG(oi.price) as avg_unit_price, COUNT(DISTINCT o.order_id) as order_count
                FROM order_items oi INNER JOIN orders o ON oi.order_id = o.order_id INNER JOIN menu_items mi ON oi.menu_item_id = mi.menu_item_id LEFT JOIN categories c ON mi.category_id = c.category_id
                WHERE o.created_at BETWEEN :start_date AND :end_date AND o.status != 'CANCELLED' {$paidFilter} {$tenantWhere}
                GROUP BY mi.menu_item_id, mi.name, c.name, mi.price ORDER BY total_quantity DESC";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $products = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            $businessInfo = $this->getBusinessInfo();
            $grandTotalQty = 0;
            $grandTotalRevenue = 0;
            foreach ($products as $p) {
                $grandTotalQty += intval($p['total_quantity']);
                $grandTotalRevenue += floatval($p['total_revenue']);
            }
            
            $isSingleDay = ($startDate === $endDate);
            $dateLabel = $isSingleDay ? date('d.m.Y', strtotime($startDate)) : (date('d.m.Y', strtotime($startDate)) . ' - ' . date('d.m.Y', strtotime($endDate)));
            
            $printData = [
                'receipt_type' => 'product_sales_report',
                'receipt_type_override' => 'product_sales_report',
                'type' => 'product_sales_report',
                'order_id' => 'PSRPT-' . date('Ymd', strtotime($startDate)),
                'table_name' => '',
                'screen_type' => 'CASHIER',
                'business_name' => $businessInfo['name'],
                'date_label' => $dateLabel,
                'report_time' => date('d.m.Y H:i'),
                'products' => $products,
                'summary' => [
                    'total_products' => count($products),
                    'total_quantity' => $grandTotalQty,
                    'total_revenue' => round($grandTotalRevenue, 2)
                ]
            ];
            
            $screenId = 'cashier_main';
            $stmt = $db->prepare("SELECT screen_id FROM preparation_screens WHERE is_active = 1 AND tenant_id = ? AND (screen_type = 'CASHIER' OR LOWER(name) LIKE '%kasa%') LIMIT 1");
            $stmt->execute([$tenantId]);
            $screen = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($screen) $screenId = $screen['screen_id'];
            
            $queueId = 'q_' . substr(md5(uniqid(mt_rand(), true)), 0, 12);
            $stmt = $db->prepare("INSERT INTO receipt_print_queue (queue_id, receipt_id, tenant_id, screen_id, print_data, status, created_at) VALUES (?, NULL, ?, ?, ?, 'PENDING', NOW())");
            $stmt->execute([$queueId, $tenantId, $screenId, json_encode($printData, JSON_UNESCAPED_UNICODE)]);
            
            $this->apiResponse(['success' => true, 'message' => 'Ürün satış raporu yazıcıya gönderildi', 'queue_id' => $queueId]);
        } catch (\Exception $e) {
            $this->apiResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    public function receipt() {
        $this->ensureTenantContext();
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $startDate = $queryParams['start_date'] ?? date('Y-m-d');
        $endDate = $queryParams['end_date'] ?? date('Y-m-d');
        $businessId = $queryParams['business_id'] ?? null;
        
        if ($this->isSuperAdmin() && $businessId) {
            try {
                $customerService = \App\Core\DependencyFactory::getCustomerService();
                $customer = $customerService->getById($businessId);
                if ($customer) { \App\Core\TenantContext::set($customer); }
            } catch (\Exception $e) {}
        }
        
        try {
            $db = \App\Core\DependencyFactory::getDatabase();
            $tenantId = \App\Core\TenantContext::getId();
            list($startDt, $endDt) = $this->resolveDatetimeRange($startDate, $endDate);
            
            $tenantWhere = '';
            $params = ['start_date' => $startDt, 'end_date' => $endDt];
            if ($tenantId) {
                $tenantWhere = 'AND o.tenant_id = :tenant_id';
                $params['tenant_id'] = $tenantId;
            }
            $paidFilter = $this->getPaidFilter();
            
            $sql = "SELECT mi.name as product_name, c.name as category_name, mi.price as current_price, SUM(oi.quantity) as total_quantity, SUM(oi.price * oi.quantity) as total_revenue, AVG(oi.price) as avg_unit_price
                FROM order_items oi INNER JOIN orders o ON oi.order_id = o.order_id INNER JOIN menu_items mi ON oi.menu_item_id = mi.menu_item_id LEFT JOIN categories c ON mi.category_id = c.category_id
                WHERE o.created_at BETWEEN :start_date AND :end_date AND o.status != 'CANCELLED' {$paidFilter} {$tenantWhere}
                GROUP BY mi.menu_item_id, mi.name, c.name, mi.price ORDER BY total_quantity DESC";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $products = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            $sqlCat = "SELECT c.name as category_name, SUM(oi.quantity) as total_quantity, SUM(oi.price * oi.quantity) as total_revenue
                FROM order_items oi INNER JOIN orders o ON oi.order_id = o.order_id INNER JOIN menu_items mi ON oi.menu_item_id = mi.menu_item_id LEFT JOIN categories c ON mi.category_id = c.category_id
                WHERE o.created_at BETWEEN :start_date AND :end_date AND o.status != 'CANCELLED' {$paidFilter} {$tenantWhere}
                GROUP BY c.category_id, c.name ORDER BY total_quantity DESC";
            $stmtCat = $db->prepare($sqlCat);
            $stmtCat->execute($params);
            $categories = $stmtCat->fetchAll(\PDO::FETCH_ASSOC);
            
            $sqlOrders = "SELECT COUNT(DISTINCT o.order_id) as total_orders, SUM(o.total_amount) as total_revenue FROM orders o WHERE o.created_at BETWEEN :start_date AND :end_date AND o.status != 'CANCELLED' {$paidFilter} {$tenantWhere}";
            $stmtOrders = $db->prepare($sqlOrders);
            $stmtOrders->execute($params);
            $orderSummary = $stmtOrders->fetch(\PDO::FETCH_ASSOC);
            
            $businessInfo = $this->getBusinessInfo();
            $this->generateReceiptHtml($businessInfo, $products, $categories, $orderSummary, $startDate, $endDate);
        } catch (\Exception $e) {
            header('Content-Type: text/html; charset=utf-8');
            echo '<h3>Hata: ' . htmlspecialchars($e->getMessage()) . '</h3>';
            exit;
        }
    }
    
    private function getBusinessInfo(): array {
        $tenantId = \App\Core\TenantContext::getId();
        $businessName = '';
        $taxNumber = '';
        $address = '';
        $phone = '';
        if ($tenantId) {
            try {
                $customerService = \App\Core\DependencyFactory::getCustomerService();
                $customer = $customerService->getById($tenantId);
                if ($customer) {
                    $businessName = $customer['company_name'] ?? $customer['business_name'] ?? '';
                    $taxNumber = $customer['tax_number'] ?? $customer['vkn'] ?? '';
                    $address = $customer['address'] ?? '';
                    $phone = $customer['phone'] ?? '';
                }
            } catch (\Exception $e) {}
        }
        if (empty($businessName)) $businessName = getAppConfig()->getAppName();
        return ['name' => $businessName, 'tax_number' => $taxNumber ?: '-', 'address' => $address ?: '-', 'phone' => $phone ?: '-'];
    }
    
    private function generateReceiptHtml(array $business, array $products, array $categories, array $orderSummary, string $startDate, string $endDate): void {
        $fmt = function($v) { return number_format($v, 2, ',', '.'); };
        $fmtInt = function($v) { return number_format($v, 0, ',', '.'); };
        $isSingleDay = ($startDate === $endDate);
        $dateLabel = $isSingleDay ? date('d.m.Y', strtotime($startDate)) : (date('d.m.Y', strtotime($startDate)) . ' - ' . date('d.m.Y', strtotime($endDate)));
        $reportTime = date('d.m.Y H:i');
        $grandTotalQty = 0;
        $grandTotalRevenue = 0;
        foreach ($products as $p) {
            $grandTotalQty += intval($p['total_quantity']);
            $grandTotalRevenue += floatval($p['total_revenue']);
        }
        $totalOrders = intval($orderSummary['total_orders'] ?? 0);
        $reportNo = 'US' . date('Ymd', strtotime($startDate)) . '-001';
        
        header('Content-Type: text/html; charset=utf-8');
        $csrfToken = class_exists('\App\Core\Security\CSRFManager') ? \App\Core\Security\CSRFManager::getToken() : '';
        
        echo '<!DOCTYPE html><html lang="tr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><meta name="csrf-token" content="' . htmlspecialchars($csrfToken) . '"><title>Urun Satis Raporu - ' . htmlspecialchars($dateLabel) . '</title>
<style>@media print { @page { size: 80mm auto; margin: 2mm; } .no-print { display: none !important; } .receipt { box-shadow: none; max-width: 80mm; } }
* { margin:0;padding:0;box-sizing:border-box; } body { font-family:"Courier New",monospace;font-size:10px;line-height:1.25;background:#f0f0f0;padding:12px;color:#000; }
.receipt { max-width:80mm;min-width:280px;margin:0 auto;background:#fff;padding:8px;box-shadow:0 1px 6px rgba(0,0,0,0.1); }
.c{text-align:center;}.b{font-weight:bold;}.sep{border:none;border-top:1px dashed #000;margin:5px 0;}.sep2{border:none;border-top:2px solid #000;margin:5px 0;}
.r{display:flex;justify-content:space-between;padding:1px 0;}.r .l{flex:1;}.r .v{text-align:right;white-space:nowrap;}.sm{font-size:8px;color:#555;}
.tbl{width:100%;border-collapse:collapse;font-size:8px;}.tbl th{text-align:left;font-weight:bold;padding:2px;border-bottom:1px solid #000;font-size:7px;text-transform:uppercase;white-space:nowrap;}
.tbl td{padding:2px;border-bottom:1px dotted #ccc;vertical-align:top;white-space:nowrap;}.tbl td:last-child,.tbl th:last-child{text-align:right;}
.total-row{font-weight:bold;border-top:1px solid #000;}.grand{font-size:11px;font-weight:bold;}
.print-bar{position:fixed;top:0;left:0;right:0;background:#0f172a;padding:10px 16px;display:flex;gap:8px;justify-content:center;align-items:center;z-index:100;}
.print-bar button{background:#fff;color:#0f172a;border:none;padding:8px 20px;font-size:12px;font-weight:bold;border-radius:6px;cursor:pointer;}
.print-bar .thermal{background:#f97316;color:#fff;}.print-bar .status{font-size:11px;color:#94a3b8;margin-left:8px;}
</style></head><body>
<div class="print-bar no-print"><button onclick="window.print()">Yazdir / PDF</button><button class="thermal" id="thermalPrintBtn" onclick="sendToThermalPrinter()">Termal Yazici</button><span id="printStatus" class="status"></span></div>
<div class="receipt" style="margin-top:50px;">
<div class="c b" style="font-size:12px;margin-bottom:2px;">' . htmlspecialchars(mb_strtoupper($business['name'])) . '</div>
<div class="c b" style="font-size:11px;">URUN BAZLI SATIS RAPORU</div>
<div class="c sm">VKN: ' . htmlspecialchars($business['tax_number']) . '</div><hr class="sep2">
<div class="r"><span class="l">Rapor No: ' . $reportNo . '</span></div><div class="r"><span class="l">Tarih: ' . $dateLabel . '</span></div><div class="r sm"><span class="l">Rapor: ' . $reportTime . '</span></div><hr class="sep">
<div class="b" style="font-size:9px;margin-bottom:3px;">GENEL OZET</div>
<div class="r"><span class="l">Toplam Siparis</span><span class="v b">' . $fmtInt($totalOrders) . '</span></div>
<div class="r"><span class="l">Toplam Satis Adedi</span><span class="v b">' . $fmtInt($grandTotalQty) . '</span></div>
<div class="r grand"><span class="l">TOPLAM CIRO</span><span class="v">' . $fmt($grandTotalRevenue) . ' TL</span></div><hr class="sep">
<div class="b" style="font-size:9px;margin-bottom:3px;">URUN BAZLI SATIS DETAYI</div><table class="tbl"><tr><th>#</th><th>Urun</th><th>Adet</th><th>B.Fiyat</th><th>Toplam</th></tr>';
        
        if (!empty($products)) {
            $idx = 0;
            foreach ($products as $p) {
                $idx++;
                echo '<tr><td>' . $idx . '</td><td>' . htmlspecialchars(mb_substr($p['product_name'], 0, 20)) . '</td><td>' . $fmtInt(intval($p['total_quantity'])) . '</td><td>' . $fmt(floatval($p['avg_unit_price'] ?? $p['current_price'])) . '</td><td>' . $fmt(floatval($p['total_revenue'])) . '</td></tr>';
            }
            echo '<tr class="total-row"><td colspan="2" style="text-align:right;">TOPLAM:</td><td>' . $fmtInt($grandTotalQty) . '</td><td></td><td>' . $fmt($grandTotalRevenue) . '</td></tr>';
        }
        echo '</table><hr class="sep2">
<div class="c sm" style="margin-top:8px;">' . $reportTime . '<br>' . htmlspecialchars($business['name']) . ' - QORDY POS<br><b>Mali belge yerine gecmez.</b></div></div>
<script>
function sendToThermalPrinter() {
    var btn=document.getElementById("thermalPrintBtn"),status=document.getElementById("printStatus");
    var startDate=' . json_encode($startDate) . ',endDate=' . json_encode($endDate) . ',baseUrl=' . json_encode(BASE_URL) . ';
    var prefixes=["/api/business","/api/qodmin"];
    btn.disabled=true;btn.textContent="Gonderiliyor...";
    function tryPrint(i){if(i>=prefixes.length){btn.disabled=false;btn.textContent="Termal Yazici";status.textContent="Hata";return;}
    var csrf=document.querySelector("meta[name=csrf-token]");csrf=csrf?csrf.getAttribute("content"):"";
    fetch(baseUrl+prefixes[i]+"/product-sales/print",{method:"POST",headers:{"Content-Type":"application/json","X-CSRF-Token":csrf},credentials:"same-origin",body:JSON.stringify({start_date:startDate,end_date:endDate})})
    .then(function(r){return r.json();}).then(function(d){if(d.success){btn.disabled=false;btn.textContent="Termal Yazici";status.textContent="Gonderildi!";setTimeout(function(){status.textContent="";},4000);}else if(i===0){tryPrint(1);}else{btn.disabled=false;btn.textContent="Termal Yazici";status.textContent="Hata";}}).catch(function(){if(i===0)tryPrint(1);else{btn.disabled=false;btn.textContent="Termal Yazici";status.textContent="Hata";}});}
    tryPrint(0);
}
</script></body></html>';
        exit;
    }
}
