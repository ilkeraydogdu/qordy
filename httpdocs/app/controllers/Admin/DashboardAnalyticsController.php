<?php
/**
 * Dashboard Period Analytics API — AJAX veri endpointi.
 *
 * Dashboard'daki tarih aralığı değiştiğinde bu endpoint çağrılır.
 * Bugün/hafta/ay/3-6-9 ay/yıl + custom için gerçek DB verileri döner.
 *
 * GET /api/business/dashboard/period?range=month
 * GET /api/business/dashboard/period?range=week
 * GET /api/business/dashboard/period?range=3months&start=2025-01-01&end=2025-03-31
 */

namespace App\Controllers\Admin;

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../../core/DependencyFactory.php';
require_once __DIR__ . '/../../core/TenantContext.php';

use App\Core\Controller;
use App\Core\Helpers\ConstantsHelper;

class DashboardAnalyticsController extends Controller {
 protected $orderService;
 protected $tableService;
 protected $financeService;

 public function __construct() {
 parent::__construct();
 $this->orderService = \App\Core\DependencyFactory::getOrderService();
 $this->tableService = \App\Core\DependencyFactory::getTableService();
 $this->financeService = \App\Core\DependencyFactory::getFinanceService();
 }

 public function getPeriodData() {
 // Auth kontrolü
 $this->requirePermission('dashboard.view');

 $queryParams = \App\Core\RequestParser::getQueryParams();
 $rangeKey = $queryParams['range'] ?? 'today';

 // Tarih aralığı hesapla
 $today = date('Y-m-d');
 $ranges = [
 'today' => [$today, $today],
 'week' => [
 date('Y-m-d', strtotime('monday this week')),
 date('Y-m-d', strtotime('sunday this week'))
 ],
 'month' => [date('Y-m-01'), date('Y-m-t')],
 '3months' => [date('Y-m-d', strtotime('-3 months')), $today],
 '6months' => [date('Y-m-d', strtotime('-6 months')), $today],
 '9months' => [date('Y-m-d', strtotime('-9 months')), $today],
 'year' => [date('Y-01-01'), date('Y-12-31')],
 ];

 $startDate = $ranges[$rangeKey][0] ?? $today;
 $endDate = $ranges[$rangeKey][1] ?? $today;
 if ($rangeKey === 'custom') {
 $startDate = $queryParams['start'] ?? $today;
 $endDate = $queryParams['end'] ?? $today;
 }

 $periodStart = $startDate . ' 00:00:00';
 $periodEnd = $endDate . ' 23:59:59';

 // Önceki eşit dönem (trend için)
 $daysDiff = max(1, (int)((strtotime($endDate) - strtotime($startDate)) / 86400) + 1);
 $prevStart = date('Y-m-d', strtotime($startDate . ' -' . $daysDiff . ' days'));
 $prevEnd = date('Y-m-d', strtotime($startDate . ' -1 day'));

 // --- GERÇEK DB VERİLERİ ---

 // Toplam gelir (periyod)
 $periodRevenue = 0.0;
 $periodOrderCount = 0;
 $periodOrders = [];
 try {
 $periodRevenue = (float)$this->orderService->calculateTotalRevenue($startDate, $endDate);
 $periodOrders = $this->orderService->getOrdersByDatetimeRange($periodStart, $periodEnd);
 $periodOrders = is_array($periodOrders) ? $periodOrders : [];
 $periodOrderCount = count($periodOrders);
 } catch (\Exception $e) {
 $this->toastNotificationService->sendApiResponse('error', 'Period veri hatası', ['error' => $e->getMessage()], 500);
 return;
 }

 // Gider
 $periodExpense = 0.0;
 try {
 $periodExpense = (float)$this->financeService->getTotalExpensesByDateRange($startDate, $endDate);
 } catch (\Exception $e) {
 $periodExpense = 0.0;
 }

 // Ortalama sipariş tutarı
 $avgOrderValue = $periodOrderCount > 0 ? round($periodRevenue / $periodOrderCount, 2) : 0.0;

 // Ödeme dağılımı
 $paymentDist = ['CASH' => 0, 'CARD' => 0, 'ONLINE' => 0, 'OTHER' => 0];
 $cancelCount = 0;
 $servedCount = 0;
 $servedStatus = ConstantsHelper::getOrderStatus('SERVED');
 foreach ($periodOrders as $o) {
 $st = strtoupper((string)($o['status'] ?? ''));
 if ($st === $servedStatus) $servedCount++;
 if ($st === 'CANCELLED') $cancelCount++;
 $pm = strtoupper((string)($o['payment_method'] ?? 'OTHER'));
 if (in_array($pm, ['CASH', 'NAKIT', 'NAKİT'])) $paymentDist['CASH'] += (float)($o['total_amount'] ?? 0);
 elseif (in_array($pm, ['CARD', 'KART', 'KREDIKARTI'])) $paymentDist['CARD'] += (float)($o['total_amount'] ?? 0);
 elseif (in_array($pm, ['ONLINE', 'ONLINE_POS'])) $paymentDist['ONLINE'] += (float)($o['total_amount'] ?? 0);
 else $paymentDist['OTHER'] += (float)($o['total_amount'] ?? 0);
 }
 $cancelRate = $periodOrderCount > 0 ? round(($cancelCount / $periodOrderCount) * 100, 1) : 0;

 // Trend (önceki dönem)
 $prevRevenue = 0.0;
 try {
 $prevOrders = $this->orderService->getOrdersByDatetimeRange(
 $prevStart . ' 00:00:00', $prevEnd . ' 23:59:59'
 );
 foreach ($prevOrders as $po) {
 if (strtoupper($po['status'] ?? '') !== 'CANCELLED') {
 $prevRevenue += (float)($po['total_amount'] ?? 0);
 }
 }
 } catch (\Exception $e) {
 $prevRevenue = 0;
 }
 $trendPct = $prevRevenue > 0 ? round((($periodRevenue - $prevRevenue) / $prevRevenue) * 100, 1) : ($periodRevenue > 0 ? 100 : 0);
 $trendDir = $trendPct > 0.5 ? 'up' : ($trendPct < -0.5 ? 'down' : 'flat');

 // En çok satan ürünler
 $topItems = [];
 try {
 $db = \App\Core\DependencyFactory::getDatabase();
 $tenantId = \App\Core\TenantContext::getId();
 $params = ['start' => $periodStart, 'end' => $periodEnd];
 if ($tenantId) $params['tid'] = $tenantId;
 $sql = "SELECT mi.name, SUM(oi.quantity) as qty, SUM(oi.price * oi.quantity) as revenue
 FROM order_items oi
 JOIN menu_items mi ON oi.menu_item_id = mi.menu_item_id
 JOIN orders o ON oi.order_id = o.order_id
 WHERE o.created_at BETWEEN :start AND :end AND o.status != 'CANCELLED'
 " . ($tenantId ? ' AND o.tenant_id = :tid' : '') . "
 GROUP BY mi.name
 ORDER BY qty DESC LIMIT 5";
 $stmt = $db->prepare($sql);
 $stmt->execute($params);
 $topItems = $stmt->fetchAll(\PDO::FETCH_ASSOC);
 } catch (\Exception $e) {
 $topItems = [];
 }

 // En aktif masalar
 $topTables = [];
 try {
 $db = \App\Core\DependencyFactory::getDatabase();
 $tenantId = \App\Core\TenantContext::getId();
 $params = ['start' => $periodStart, 'end' => $periodEnd];
 if ($tenantId) $params['tid'] = $tenantId;
 $sql = "SELECT t.table_number, t.name, COUNT(o.order_id) as orders, SUM(o.total_amount) as revenue
 FROM tables t
 JOIN orders o ON o.table_id = t.table_id
 WHERE o.created_at BETWEEN :start AND :end AND o.status != 'CANCELLED'
 " . ($tenantId ? ' AND o.tenant_id = :tid' : '') . "
 GROUP BY t.table_number, t.name
 ORDER BY orders DESC LIMIT 5";
 $stmt = $db->prepare($sql);
 $stmt->execute($params);
 $topTables = $stmt->fetchAll(\PDO::FETCH_ASSOC);
 } catch (\Exception $e) {
 $topTables = [];
 }

 // Saatlik satışlar (chart için)
 $hourlyData = [];
 try {
 $hourlyData = $this->orderService->getHourlySales($startDate, $endDate);
 } catch (\Exception $e) {
 $hourlyData = [];
 }

 // Kategori bazlı gelir (chart için)
 $categoryData = [];
 try {
 $categoryData = $this->orderService->getRevenueByCategory($startDate, $endDate);
 } catch (\Exception $e) {
 $categoryData = [];
 }

 // JSON yanıtı
 $this->toastNotificationService->sendApiResponse('success', 'Veriler yüklendi', [
 'range_key' => $rangeKey,
 'start_date' => $startDate,
 'end_date' => $endDate,
 'revenue' => $periodRevenue,
 'expense' => $periodExpense,
 'profit' => $periodRevenue - $periodExpense,
 'orders' => $periodOrderCount,
 'avg_order' => $avgOrderValue,
 'served' => $servedCount,
 'cancelled' => $cancelCount,
 'cancel_rate' => $cancelRate,
 'payment' => $paymentDist,
 'trend_pct' => $trendPct,
 'trend_dir' => $trendDir,
 'top_items' => $topItems,
 'top_tables' => $topTables,
 'hourly' => $hourlyData,
 'categories' => $categoryData,
 ], 200);
 }
}