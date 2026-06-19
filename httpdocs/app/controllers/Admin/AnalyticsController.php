<?php
namespace App\Controllers\Admin;

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../../core/DependencyFactory.php';

use App\Core\Controller;

class AnalyticsController extends Controller {
    protected $orderService;
    protected $settingsService;
    protected $zReportService;
    
    public function __construct() {
        parent::__construct();
        $this->orderService = \App\Core\DependencyFactory::getOrderService();
        $this->settingsService = \App\Core\DependencyFactory::getSystemSettingsService();
        $this->zReportService = \App\Core\DependencyFactory::getZReportService();
    }
    
    public function analytics() {
        $this->ensureTenantContext();
        if (!$this->hasPermission('dashboard.analytics')) {
            header('Location: ' . BASE_URL . '/unauthorized');
            exit;
        }
        
        $isSuperAdmin = $this->isSuperAdmin();
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $businessId = $queryParams['business_id'] ?? null;
        
        if ($isSuperAdmin && $businessId) {
            try {
                $customerService = \App\Core\DependencyFactory::getCustomerService();
                $customer = $customerService->getById($businessId);
                if ($customer) {
                    \App\Core\TenantContext::set($customer);
                }
            } catch (\Exception $e) {
                \App\Core\Logger::warning('AnalyticsController: tenant context set failed', [
                    'business_id' => $businessId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $queryParams = \App\Core\RequestParser::getQueryParams();
        $requestStart = $queryParams['start_date'] ?? null;
        $requestEnd = $queryParams['end_date'] ?? null;
        
        $businessRange = $this->settingsService->getBusinessDateRange();
        $businessDate = $businessRange['date'] ?? date('Y-m-d');
        $defaultStart = isset($businessRange['start']) ? substr($businessRange['start'], 0, 10) : $businessDate;
        $defaultEnd = isset($businessRange['end']) ? substr($businessRange['end'], 0, 10) : $businessDate;
        
        $startDate = $requestStart ?: $defaultStart;
        $endDate = $requestEnd ?: $defaultEnd;
        
        $dailyRevenue = [];
        $recentOrders = [];
        $topSellingItems = [];
        $totalRevenue = 0;
        $totalOrders = [];
        $avgOrderValue = 0;
        $revenueByCategory = [];
        $hourlySales = [];
        
        try {
            // Use the canonical (bug-fixed) revenue helpers in OrderService /
            // OrderRepository instead of inlining raw SQL here. They all share
            // the same predicate — non-cancelled AND (is_paid OR served) — and
            // are tenant-scoped via TenantContext, so every figure agrees and
            // there is a single source of truth for revenue math.
            $startDt = $startDate . ' 00:00:00';
            $endDt = $endDate . ' 23:59:59';

            $totalRevenue = $this->orderService->getDailyRevenueByDatetimeRange($startDt, $endDt);
            $dailyRevenue = $this->orderService->getDailyRevenueSeries($startDate, $endDate);
            $hourlySales = $this->orderService->getHourlySales($startDate, $endDate);
            $topSellingItems = $this->orderService->getTopSellingItems(10, $startDt, $endDt);
            $revenueByCategory = $this->orderService->getRevenueByCategory($startDate, $endDate);

            // Orders in range for counts + recent list. Counting orders (by
            // status) is not revenue math, so it is fine to do in PHP here.
            $allOrders = $this->orderService->getOrdersByDatetimeRange($startDt, $endDt);
            $allOrders = is_array($allOrders) ? $allOrders : [];
            $nonCancelledOrders = array_values(array_filter($allOrders, static function ($order) {
                return ($order['status'] ?? '') !== 'CANCELLED';
            }));

            $totalOrders = $nonCancelledOrders;
            $recentOrders = array_slice($allOrders, 0, 20);
            $orderCount = count($nonCancelledOrders);
            $avgOrderValue = $orderCount > 0 ? round($totalRevenue / $orderCount, 2) : 0;
        } catch (\Exception $e) {
            \App\Core\Logger::error('Analytics initial load error', ['error' => $e->getMessage()]);
        }
        
        $data = [
            'is_super_admin' => $isSuperAdmin,
            'business_id' => $businessId,
            'daily_revenue' => $dailyRevenue,
            'recent_orders' => $recentOrders,
            'top_selling_items' => $topSellingItems,
            'total_revenue' => $totalRevenue,
            'total_orders' => $totalOrders,
            'avg_order_value' => $avgOrderValue,
            'revenue_by_category' => $revenueByCategory,
            'hourly_sales' => $hourlySales,
            'date_range' => ['start' => $startDate, 'end' => $endDate]
        ];
        
        $this->view('admin/analytics', $data);
    }
    
    public function getAnalyticsData() {
        $this->ensureTenantContext();
        if (!$this->hasPermission('dashboard.analytics')) {
            $this->apiResponse(['success' => false, 'error' => 'Yetkisiz'], 401);
            return;
        }
        
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $startDate = $queryParams['start_date'] ?? date('Y-m-01');
        $endDate = $queryParams['end_date'] ?? date('Y-m-d');
        $businessId = $queryParams['business_id'] ?? null;
        
        if ($this->isSuperAdmin() && $businessId) {
            try {
                $customerService = \App\Core\DependencyFactory::getCustomerService();
                $customer = $customerService->getById($businessId);
                if ($customer) {
                    \App\Core\TenantContext::set($customer);
                }
            } catch (\Exception $e) {}
        }
        
        $businessRange = $this->settingsService->getBusinessDateRange();
        
        try {
            // Single source of truth: reuse the canonical OrderService helpers
            // (same predicate as the server-rendered page + the dashboard).
            $startDt = $startDate . ' 00:00:00';
            $endDt = $endDate . ' 23:59:59';

            $totalRevenue = $this->orderService->getDailyRevenueByDatetimeRange($startDt, $endDt);
            $dailyRevenue = $this->orderService->getDailyRevenueSeries($startDate, $endDate);
            $hourlySalesForView = $this->orderService->getHourlySales($startDate, $endDate);
            $revenueByCategory = $this->orderService->getRevenueByCategory($startDate, $endDate);

            $topItems = $this->orderService->getTopSellingItems(10, $startDt, $endDt);
            $topItemsForView = [];
            foreach ($topItems as $row) {
                $topItemsForView[] = [
                    'name' => $row['name'] ?? '',
                    'count' => (int)($row['count'] ?? 0),
                    'revenue' => floatval($row['revenue'] ?? 0),
                ];
            }

            $allOrders = $this->orderService->getOrdersByDatetimeRange($startDt, $endDt);
            $allOrders = is_array($allOrders) ? $allOrders : [];

            $completedStatuses = ['SERVED', 'READY', 'DELIVERED', 'ON_DELIVERY'];
            $totalOrders = 0;
            $completedCount = 0;
            $cancelledCount = 0;
            foreach ($allOrders as $order) {
                $status = $order['status'] ?? '';
                if ($status === 'CANCELLED') {
                    $cancelledCount++;
                    continue;
                }
                $totalOrders++;
                if (in_array($status, $completedStatuses, true)) {
                    $completedCount++;
                }
            }
            $avgOrderValue = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;
            $recentOrders = array_slice($allOrders, 0, 20);

            $this->apiResponse([
                'success' => true,
                'total_revenue' => $totalRevenue,
                'total_orders' => $totalOrders,
                'avg_order_value' => round($avgOrderValue, 2),
                'daily_revenue' => $dailyRevenue,
                'hourly_sales' => $hourlySalesForView,
                'top_selling_items' => $topItemsForView,
                'recent_orders' => $recentOrders,
                'revenue_by_category' => $revenueByCategory,
                'business_date' => $businessRange['date'] ?? date('Y-m-d'),
                'completed_orders' => $completedCount,
                'completed_orders_count' => $completedCount,
                'cancelled_orders' => $cancelledCount
            ]);
        } catch (\Exception $e) {
            $this->apiResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    public function endOfDay() {
        $this->ensureTenantContext();
        $businessRange = $this->settingsService->getBusinessDateRange();
        $this->apiResponse([
            'success' => true,
            'business_date' => $businessRange['date'] ?? date('Y-m-d'),
            'range' => $businessRange
        ]);
    }
    
    public function zReportPdf() {
        $this->ensureTenantContext();
        $this->bootstrapZReportTenantFromRequest();
        if (!$this->hasPermission('dashboard.analytics')) {
            header('HTTP/1.1 401 Unauthorized');
            echo 'Yetkisiz erişim';
            exit;
        }

        if (!\App\Core\TenantResolver::resolve()) {
            header('HTTP/1.1 403 Forbidden');
            echo 'İşletme bağlamı bulunamadı. Lütfen panelden tekrar giriş yapın veya işletme seçin.';
            exit;
        }
        
        $requestData = \App\Core\RequestParser::getRequestData();
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $businessRange = $this->settingsService->getBusinessDateRange();
        $date = $requestData['date'] ?? $queryParams['date'] ?? $businessRange['date'];
        $date = $this->normalizeReportDate($date, $businessRange['date'] ?? date('Y-m-d'));
        
        [$startDt, $endDt] = $this->resolveZReportDatetimeRange($date);
        
        try {
            $businessId = \App\Core\TenantContext::getId() ?: \App\Core\TenantResolver::resolve();
            if ($startDt && $endDt && $businessId) {
                $this->settingsService->logBusinessDayRange($businessId, $date, $startDt, $endDt, 'manual_z_pdf');
            }
            
            $reportData = $this->zReportService->buildZReportData($date, $startDt, $endDt);
            $this->generateZReportHtml($reportData);
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('Z Report PDF error', ['error' => $e->getMessage(), 'date' => $date]);
            }
            header('HTTP/1.1 500 Internal Server Error');
            echo 'Rapor oluşturulurken hata oluştu: ' . $e->getMessage();
            exit;
        }
    }
    
    public function zReportPrint() {
        $this->ensureTenantContext();
        $this->bootstrapZReportTenantFromRequest();
        if (!$this->hasPermission('dashboard.analytics')) {
            $this->apiResponse(['success' => false, 'error' => 'Yetkisiz'], 401);
            return;
        }

        if (!\App\Core\TenantResolver::resolve()) {
            $this->apiResponse(['success' => false, 'error' => 'İşletme bağlamı bulunamadı'], 403);
            return;
        }
        
        $requestData = \App\Core\RequestParser::getRequestData();
        $businessRange = $this->settingsService->getBusinessDateRange();
        $date = $requestData['date'] ?? $businessRange['date'];
        $date = $this->normalizeReportDate($date, $businessRange['date'] ?? date('Y-m-d'));
        
        [$startDt, $endDt] = $this->resolveZReportDatetimeRange($date);
        
        try {
            $businessId = \App\Core\TenantContext::getId() ?: \App\Core\TenantResolver::resolve();
            if ($startDt && $endDt && $businessId) {
                $this->settingsService->logBusinessDayRange($businessId, $date, $startDt, $endDt, 'manual_z_print');
            }
            
            $reportData = $this->zReportService->buildZReportData($date, $startDt, $endDt);
            $printData = $this->zReportService->getPrintPayload($reportData);
            
            $db = \App\Core\DependencyFactory::getDatabase();
            
            $screenId = 'cashier_main';
            $stmt = $db->prepare("SELECT screen_id FROM preparation_screens WHERE is_active = 1 AND tenant_id = ? AND (screen_type = 'CASHIER' OR LOWER(name) LIKE '%kasa%') LIMIT 1");
            $stmt->execute([$businessId]);
            $screen = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($screen) $screenId = $screen['screen_id'];
            
            $queueId = 'q_' . substr(md5(uniqid(mt_rand(), true)), 0, 12);
            $stmt = $db->prepare("INSERT INTO receipt_print_queue (queue_id, receipt_id, tenant_id, screen_id, print_data, status, created_at) VALUES (?, NULL, ?, ?, ?, 'PENDING', NOW())");
            $stmt->execute([$queueId, $businessId, $screenId, json_encode($printData, JSON_UNESCAPED_UNICODE)]);
            
            $this->apiResponse(['success' => true, 'message' => 'Z raporu yazıcıya gönderildi', 'queue_id' => $queueId]);
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('Z Report print error', ['error' => $e->getMessage()]);
            }
            $this->apiResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    public function autoZReport() {
        $isCron = isset($_SERVER['HTTP_X_CRON_JOB']) || php_sapi_name() === 'cli';
        if (!$isCron) {
            $this->apiResponse(['success' => false, 'error' => 'Unauthorized'], 401);
            return;
        }
        
        try {
            $db = \App\Core\DependencyFactory::getDatabase();
            $stmt = $db->query("SELECT customer_id FROM customers WHERE status = 'active' OR status = 'ACTIVE'");
            $businesses = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            $processed = 0;
            $now = new \DateTime('now', new \DateTimeZone('Europe/Istanbul'));
            
            foreach ($businesses as $biz) {
                $businessId = $biz['customer_id'];
                try {
                    \App\Core\TenantContext::setId($businessId);
                    $settingsService = \App\Core\DependencyFactory::getSystemSettingsService();
                    $settings = $settingsService->getSettings();
                    
                    $enabled = ($settings['working_hours_enabled'] ?? '0') === '1';
                    if (!$enabled) continue;
                    
                    $daysJson = $settings['working_hours_days'] ?? null;
                    $days = ($daysJson && is_string($daysJson)) ? json_decode($daysJson, true) : null;
                    
                    $dayMap = ['Mon' => 'mon', 'Tue' => 'tue', 'Wed' => 'wed', 'Thu' => 'thu', 'Fri' => 'fri', 'Sat' => 'sat', 'Sun' => 'sun'];
                    $currentDay = $dayMap[$now->format('D')] ?? 'mon';
                    $yesterdayDt = (clone $now)->modify('-1 day');
                    $yesterdayKey = $dayMap[$yesterdayDt->format('D')] ?? 'mon';
                    
                    $endTime = null;
                    if (is_array($days) && isset($days[$yesterdayKey])) {
                        $startTime = $days[$yesterdayKey]['start'] ?? '09:00';
                        $et = $days[$yesterdayKey]['end'] ?? '02:00';
                        if ($et < $startTime) $endTime = $et;
                    }
                    if (!$endTime && is_array($days) && isset($days[$currentDay])) {
                        $startTime = $days[$currentDay]['start'] ?? '09:00';
                        $et = $days[$currentDay]['end'] ?? '02:00';
                        if ($et < $startTime) $endTime = $et;
                    }
                    
                    if (!$endTime) continue;
                    
                    $currentTime = $now->format('H:i');
                    $endMinutes = intval(explode(':', $endTime)[0]) * 60 + intval(explode(':', $endTime)[1] ?? 0);
                    $currentMinutes = intval(explode(':', $currentTime)[0]) * 60 + intval(explode(':', $currentTime)[1] ?? 0);
                    $diff = abs($currentMinutes - $endMinutes);
                    
                    if ($diff > 5) continue;
                    
                    $businessDate = $yesterdayDt->format('Y-m-d');
                    
                    $dupCheck = $db->prepare("SELECT queue_id FROM receipt_print_queue WHERE tenant_id = ? AND print_data LIKE ? AND created_at > DATE_SUB(NOW(), INTERVAL 2 HOUR) LIMIT 1");
                    $dupCheck->execute([$businessId, '%"z_number":"Z' . date('Ymd', strtotime($businessDate)) . '%']);
                    if ($dupCheck->fetch()) continue;
                    
                    $this->printAutoZReport($businessId, $now);
                    $processed++;
                } catch (\Exception $e) {
                    if (class_exists('\App\Core\Logger')) {
                        \App\Core\Logger::error('Auto Z report error for business ' . $businessId, ['error' => $e->getMessage()]);
                    }
                }
            }
            
            $this->apiResponse(['success' => true, 'processed' => $processed]);
        } catch (\Exception $e) {
            $this->apiResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    private function printAutoZReport(string $businessId, \DateTime $now): void {
        $settingsService = \App\Core\DependencyFactory::getSystemSettingsService();
        \App\Core\TenantContext::setId($businessId);
        
        $businessRange = $settingsService->getBusinessDateRange();
        $businessDate = $businessRange['date'];
        
        $settingsService->logBusinessDayRange($businessId, $businessDate, $businessRange['start_datetime'] ?? $businessRange['start'], $businessRange['end_datetime'] ?? $businessRange['end'], 'auto_z_report');
        
        $reportData = $this->zReportService->buildZReportData($businessDate, $businessRange['start_datetime'] ?? $businessRange['start'], $businessRange['end_datetime'] ?? $businessRange['end']);
        $printData = $this->zReportService->getPrintPayload($reportData);
        
        $db = \App\Core\DependencyFactory::getDatabase();
        $screenId = 'cashier_main';
        $stmt = $db->prepare("SELECT screen_id FROM preparation_screens WHERE is_active = 1 AND tenant_id = ? AND (screen_type = 'CASHIER' OR LOWER(name) LIKE '%kasa%') LIMIT 1");
        $stmt->execute([$businessId]);
        $screen = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($screen) $screenId = $screen['screen_id'];
        
        $queueId = 'q_' . substr(md5(uniqid(mt_rand(), true)), 0, 12);
        $stmt = $db->prepare("INSERT INTO receipt_print_queue (queue_id, receipt_id, tenant_id, screen_id, print_data, status, created_at) VALUES (?, NULL, ?, ?, ?, 'PENDING', NOW())");
        $stmt->execute([$queueId, $businessId, $screenId, json_encode($printData, JSON_UNESCAPED_UNICODE)]);
    }
    
    private function bootstrapZReportTenantFromRequest(): void {
        if (!$this->isSuperAdmin()) {
            return;
        }

        $queryParams = \App\Core\RequestParser::getQueryParams();
        $requestData = \App\Core\RequestParser::getRequestData();
        $businessId = $queryParams['business_id'] ?? $requestData['business_id'] ?? null;
        if (!$businessId) {
            return;
        }

        try {
            $customerService = \App\Core\DependencyFactory::getCustomerService();
            $customer = $customerService->getById($businessId);
            if ($customer) {
                \App\Core\TenantContext::set($customer);
                $_SESSION['selected_business_id'] = $businessId;
            }
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::warning('Z Report tenant bootstrap failed', [
                    'business_id' => $businessId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function normalizeReportDate(?string $date, string $fallback): string {
        $date = trim((string)($date ?? ''));
        if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $fallback;
        }
        return $date;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveZReportDatetimeRange(string $date): array {
        $businessRange = $this->settingsService->getBusinessDateRange();
        $today = $businessRange['date'] ?? date('Y-m-d');

        if ($date === $today) {
            $startDt = $businessRange['start_datetime'] ?? $businessRange['start'] ?? ($date . ' 00:00:00');
            $endDt = $businessRange['end_datetime'] ?? $businessRange['end'] ?? ($date . ' 23:59:59');
            return [$startDt, $endDt];
        }

        $historicalRange = $this->settingsService->getBusinessDateRangeForDate($date);
        return [
            $historicalRange['start_datetime'] ?? ($date . ' 00:00:00'),
            $historicalRange['end_datetime'] ?? ($date . ' 23:59:59'),
        ];
    }

    private function generateZReportHtml(array $data): void {
        $fmt = function($v) { return number_format($v, 2, ',', '.'); };
        $fmtInt = function($v) { return number_format($v, 0, ',', '.'); };
        
        $business = $data['business'];
        $orderLines = $data['order_lines'];
        $totals = $data['totals'];
        $zNumber = $data['z_number'];
        $paymentBreakdown = $data['payment_breakdown'];
        
        $formattedDate = date('d.m.Y', strtotime($data['date']));
        $formattedReportTime = date('d.m.Y H:i');
        
        $totalPaymentAmount = $paymentBreakdown['cash']['total'] + $paymentBreakdown['card']['total'] + $paymentBreakdown['online']['total'];
        
        header('Content-Type: text/html; charset=utf-8');
        $csrfToken = '';
        if (class_exists('\App\Core\Security\CSRFManager')) {
            $csrfToken = \App\Core\Security\CSRFManager::getToken();
        }
        
        echo '<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="' . htmlspecialchars($csrfToken) . '">
<title>Z Raporu - ' . htmlspecialchars($formattedDate) . '</title>
<style>
@media print { @page { size: 80mm auto; margin: 2mm; } body { -webkit-print-color-adjust: exact !important; } .no-print { display: none !important; } .receipt { box-shadow: none; max-width: 80mm; } }
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family: "Courier New", Courier, monospace; font-size: 10px; line-height: 1.25; background: #f0f0f0; padding: 12px; color: #000; }
.receipt { max-width: 80mm; min-width: 280px; margin: 0 auto; background: #fff; padding: 8px; box-shadow: 0 1px 6px rgba(0,0,0,0.1); }
.c { text-align: center; } .b { font-weight: bold; }
.sep { border: none; border-top: 1px dashed #000; margin: 5px 0; }
.sep2 { border: none; border-top: 2px solid #000; margin: 5px 0; }
.r { display: flex; justify-content: space-between; padding: 1px 0; }
.r .l { flex: 1; } .r .v { text-align: right; white-space: nowrap; }
.sm { font-size: 8px; color: #555; }
.tbl { width: 100%; border-collapse: collapse; font-size: 8px; }
.tbl th { text-align: left; font-weight: bold; padding: 2px; border-bottom: 1px solid #000; font-size: 7px; text-transform: uppercase; white-space: nowrap; }
.tbl td { padding: 2px; border-bottom: 1px dotted #ccc; vertical-align: top; white-space: nowrap; }
.tbl td:last-child, .tbl th:last-child { text-align: right; }
.total-row { font-weight: bold; border-top: 1px solid #000; }
.grand { font-size: 11px; font-weight: bold; }
.print-bar { position: fixed; top: 0; left: 0; right: 0; background: #0f172a; padding: 10px 16px; display: flex; gap: 8px; justify-content: center; align-items: center; z-index: 100; }
.print-bar button { background: #fff; color: #0f172a; border: none; padding: 8px 20px; font-size: 12px; font-weight: bold; border-radius: 6px; cursor: pointer; }
.print-bar .thermal { background: #f97316; color: #fff; }
.print-bar .status { font-size: 11px; color: #94a3b8; margin-left: 8px; }
.print-bar .status.ok { color: #4ade80; } .print-bar .status.err { color: #f87171; }
.cancelled td { text-decoration: line-through; color: #999; }
</style>
</head>
<body>
<div class="print-bar no-print">
  <button onclick="window.print()">Yazdir / PDF</button>
  <button class="thermal" id="thermalPrintBtn" onclick="sendToThermalPrinter()">Termal Yazici (XPrinter)</button>
  <span id="printStatus" class="status"></span>
</div>

<div class="receipt" style="margin-top: 50px;">

<div class="c b" style="font-size:12px;margin-bottom:2px;">' . htmlspecialchars(mb_strtoupper($business['name'])) . '</div>
<div class="c b" style="font-size:11px;">Z RAPORU</div>
<div class="c sm">VKN: ' . htmlspecialchars($business['tax_number']) . '</div>
<div class="c sm">' . htmlspecialchars($business['address']) . '</div>
<div class="c sm">Tel: ' . htmlspecialchars($business['phone']) . '</div>
<hr class="sep2">

<div class="r"><span class="l">Z No: ' . htmlspecialchars($zNumber) . '</span><span class="v">' . $formattedDate . '</span></div>
<div class="r sm"><span class="l">Rapor: ' . $formattedReportTime . '</span></div>
<hr class="sep">';

        // === SATIS DETAY ===
        echo '
<div class="b" style="font-size:9px;margin-bottom:3px;">SATIS DETAY</div>
<table class="tbl">
<tr><th>#</th><th>Sip.No</th><th>Saat</th><th>Masa</th><th>Garson</th><th>Adet</th><th>Tutar</th></tr>';
        
        $lineNum = 0;
        foreach ($orderLines as $line) {
            $lineNum++;
            $table = mb_substr($line['table'], 0, 10);
            $waiter = mb_substr($line['waiter'], 0, 10);
            
            echo '<tr>';
            echo '<td>' . $lineNum . '</td>';
            echo '<td>' . htmlspecialchars($line['short_id']) . '</td>';
            echo '<td>' . htmlspecialchars($line['time']) . '</td>';
            echo '<td>' . htmlspecialchars($table) . '</td>';
            echo '<td>' . htmlspecialchars($waiter) . '</td>';
            echo '<td>' . $line['item_count'] . '</td>';
            echo '<td>' . $fmt($line['amount']) . '</td>';
            echo '</tr>';
        }
        
        $orderLinesTotal = $totals['order_lines_total'] ?? $totals['gross_revenue'];
        echo '<tr class="total-row"><td colspan="6" style="text-align:right;">TOPLAM:</td><td>' . $fmt($orderLinesTotal) . '</td></tr>';
        echo '</table>';
        
        if (count($orderLines) === 0) {
            echo '<div class="c sm" style="padding:6px 0;">Bu tarihte odemesi alinan satis bulunamadi.</div>';
        }
        
        echo '<hr class="sep">';
        
        // === URUN BAZLI SATIS ===
        $productBreakdown = $data['product_breakdown'] ?? [];
        $categoryBreakdown = $data['category_breakdown'] ?? [];
        
        echo '
<div class="b" style="font-size:9px;margin-bottom:3px;">URUN BAZLI SATIS</div>
<table class="tbl">
<tr><th>#</th><th>Urun</th><th>Adet</th><th>B.Fiyat</th><th>Toplam</th></tr>';
        
        $productGrandTotal = 0;
        $productGrandQty = 0;
        
        if (!empty($productBreakdown)) {
            $pIdx = 0;
            foreach ($productBreakdown as $product) {
                $pIdx++;
                $pName = mb_substr($product['name'], 0, 20);
                $productGrandTotal += $product['total'];
                $productGrandQty += $product['quantity'];
                
                echo '<tr>';
                echo '<td>' . $pIdx . '</td>';
                echo '<td>' . htmlspecialchars($pName) . '</td>';
                echo '<td>' . $product['quantity'] . '</td>';
                echo '<td>' . $fmt($product['unit_price']) . '</td>';
                echo '<td>' . $fmt($product['total']) . '</td>';
                echo '</tr>';
            }
            
            echo '<tr class="total-row"><td colspan="2" style="text-align:right;">TOPLAM:</td><td>' . $fmtInt($productGrandQty) . '</td><td></td><td>' . $fmt($productGrandTotal) . '</td></tr>';
        }
        echo '</table><hr class="sep">';
        
        // === KATEGORI OZETI (skip if only "Kategorisiz") ===
        $showCategories = false;
        if (!empty($categoryBreakdown)) {
            $catKeys = array_keys($categoryBreakdown);
            if (count($catKeys) > 1 || (count($catKeys) === 1 && $catKeys[0] !== 'Kategorisiz')) {
                $showCategories = true;
            }
        }
        
        if ($showCategories) {
            echo '
<div class="b" style="font-size:9px;margin-bottom:3px;">KATEGORI OZETI</div>';
            foreach ($categoryBreakdown as $catName => $catData) {
                echo '<div class="r"><span class="l">' . htmlspecialchars($catName) . ' (' . $catData['quantity'] . ' ad.)</span><span class="v">' . $fmt($catData['total']) . '</span></div>';
            }
            echo '<hr class="sep">';
        }
        
        // === SIPARIS OZETI ===
        echo '
<div class="b" style="font-size:9px;margin-bottom:3px;">SIPARIS OZETI</div>
<div class="r"><span class="l">Toplam Siparis</span><span class="v b">' . $totals['total_orders'] . '</span></div>
<div class="r"><span class="l">Tamamlanan</span><span class="v">' . $totals['completed_orders'] . '</span></div>
<div class="r"><span class="l">Iptal</span><span class="v">' . $totals['cancelled_orders'] . '</span></div>';
        
        if ($totals['pending_orders'] > 0) {
            echo '<div class="r"><span class="l">Bekleyen</span><span class="v">' . $totals['pending_orders'] . '</span></div>';
        }
        
        echo '<div class="r"><span class="l">Odenen</span><span class="v">' . $totals['paid_count'] . '</span></div>
<div class="r"><span class="l">Ort. Siparis</span><span class="v">' . $fmt($totals['avg_order']) . '</span></div>
<hr class="sep">';
        
        // === MALI OZET ===
        echo '
<div class="b" style="font-size:9px;margin-bottom:3px;">MALI OZET</div>
<div class="r grand"><span class="l">BRUT CIRO</span><span class="v">' . $fmt($totals['gross_revenue']) . ' TL</span></div>';
        
        if ($data['discount_total'] > 0) {
            echo '<div class="r"><span class="l">Indirim</span><span class="v">-' . $fmt($data['discount_total']) . '</span></div>';
        }
        if ($totals['cancelled_revenue'] > 0) {
            echo '<div class="r"><span class="l">Iptal Tutar</span><span class="v">' . $fmt($totals['cancelled_revenue']) . '</span></div>';
        }
        
        echo '<hr class="sep">
<div class="b" style="font-size:9px;margin-bottom:3px;">ODEME DAGILIMI</div>';
        
        if ($paymentBreakdown['cash']['total'] > 0) {
            echo '<div class="r"><span class="l">Nakit</span><span class="v">' . $fmt($paymentBreakdown['cash']['total']) . ' (' . $paymentBreakdown['cash']['count'] . ')</span></div>';
        }
        if ($paymentBreakdown['card']['total'] > 0) {
            echo '<div class="r"><span class="l">Kart</span><span class="v">' . $fmt($paymentBreakdown['card']['total']) . ' (' . $paymentBreakdown['card']['count'] . ')</span></div>';
        }
        if ($paymentBreakdown['online']['total'] > 0) {
            echo '<div class="r"><span class="l">Online</span><span class="v">' . $fmt($paymentBreakdown['online']['total']) . ' (' . $paymentBreakdown['online']['count'] . ')</span></div>';
        }
        
        echo '<div class="r grand"><span class="l">TAHSILAT</span><span class="v">' . $fmt($totalPaymentAmount) . ' TL</span></div>';
        
        echo '<hr class="sep2">';
        
        // === FOOTER ===
        echo '
<div class="c sm" style="margin-top:8px;">
' . $formattedReportTime . '<br>
' . htmlspecialchars($business['name']) . ' - QORDY POS<br>
<b>Mali belge yerine gecmez.</b>
</div>

</div>

<script>
function sendToThermalPrinter() {
    var btn = document.getElementById("thermalPrintBtn");
    var status = document.getElementById("printStatus");
    var reportDate = ' . json_encode($data['date']) . ';
    var baseUrl = ' . json_encode(BASE_URL) . ';
    var apiPrefixes = ["/api/business", "/api/qodmin"];
    btn.disabled = true; btn.textContent = "Gonderiliyor..."; status.textContent = "";
    
    function tryPrint(idx) {
        if (idx >= apiPrefixes.length) {
            btn.disabled = false; btn.textContent = "Termal Yazici (XPrinter)";
            status.textContent = "Hata"; status.className = "status err"; return;
        }
        var metaTag = document.querySelector(\'meta[name="csrf-token"]\');
        var csrfToken = metaTag ? metaTag.getAttribute("content") : "";
        fetch(baseUrl + apiPrefixes[idx] + "/z-report-print", {
            method: "POST",
            headers: { "Content-Type": "application/json", "X-CSRF-Token": csrfToken },
            credentials: "same-origin",
            body: JSON.stringify({ date: reportDate })
        })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.success) {
                btn.disabled = false; btn.textContent = "Termal Yazici (XPrinter)";
                status.textContent = "Yaziciya gonderildi!"; status.className = "status ok";
                setTimeout(function() { status.textContent = ""; }, 4000);
            } else if (idx === 0) { tryPrint(1); }
            else { btn.disabled = false; btn.textContent = "Termal Yazici (XPrinter)"; status.textContent = "Hata: " + (d.error || ""); status.className = "status err"; }
        })
        .catch(function() { if (idx === 0) { tryPrint(1); } else { btn.disabled = false; btn.textContent = "Termal Yazici (XPrinter)"; status.textContent = "Baglanti hatasi"; status.className = "status err"; } });
    }
    tryPrint(0);
}
</script>
</body></html>';
        exit;
    }
}
