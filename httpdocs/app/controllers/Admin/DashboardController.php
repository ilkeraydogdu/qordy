<?php
namespace App\Controllers\Admin;
require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../../core/DependencyFactory.php';
use App\Core\Controller;
use App\Core\Helpers\ConstantsHelper;
class DashboardController extends Controller {
    // Traits are already included in Controller base class, no need to redeclare
    
    protected $orderService;
    protected $tableService;
    protected $notificationService;
    protected $constantsService;
    protected $financeService;
    
    public function __construct() {
        parent::__construct();
        $this->orderService = \App\Core\DependencyFactory::getOrderService();
        $this->tableService = \App\Core\DependencyFactory::getTableService();
        $this->notificationService = \App\Core\DependencyFactory::getNotificationService();
        $this->constantsService = \App\Core\DependencyFactory::getConstantsService();
        $this->financeService = \App\Core\DependencyFactory::getFinanceService();
    }
    
    public function dashboard(?string $rangeParam = null) {
        // CRITICAL: Ensure tenant context is set (unless super admin)
        // Super admin kontrolü - super admin her şeye erişebilir (en önce kontrol et)
        \App\Core\SessionManager::ensureSession();
        $this->ensureTenantContext();
        $sessionRole = \App\Core\SessionManager::get('role');
        $isSuperAdminSession = \App\Core\SessionManager::get('is_super_admin') === true;
        $isSuperAdmin = false;

        // Check super admin by session flag first
        if ($isSuperAdminSession) {
            $isSuperAdmin = true;
        } elseif ($sessionRole) {
            $normalizedRole = strtoupper(trim($sessionRole));
            $isSuperAdmin = ($normalizedRole === 'SUPER_ADMIN' || $normalizedRole === 'ROLE_SUPER_ADMIN' ||
                           $normalizedRole === 'QODMIN' || $normalizedRole === 'ROLE_QODMIN');
        }

        // Try Authorization method as fallback
        if (!$isSuperAdmin && isset($this->auth) && $this->auth !== null) {
            try {
                $isSuperAdmin = $this->isSuperAdmin();
            } catch (\Exception $e) {
                // Ignore exception, use session check result
            }
        }

        if ($isSuperAdmin) {
            // Super admin için normal admin dashboard göster (permission kontrolü yapmadan)
            // Devam et, aşağıdaki kod çalışacak - role ve permission kontrolü yapma
        } else {
            // Role kontrolü: MANAGER ve BUSINESS_MANAGER rolleri erişebilir
            $currentRole = $_SESSION['role'] ?? \App\Core\SessionManager::get('role') ?? '';
            $normalizedRole = strtoupper(str_replace('ROLE_', '', trim($currentRole)));
            
            // Operational staff role redirect
            $wellKnownStaffRedirectMap = [
                'WAITER'   => '/business/waiter/dashboard',
                'GARSON'   => '/business/waiter/dashboard',
                'KITCHEN'  => '/business/kitchen/dashboard',
                'MUTFAK'   => '/business/kitchen/dashboard',
                'CHEF'     => '/business/kitchen/dashboard',
                'CASHIER'  => '/business/pos',
                'KASIYER'  => '/business/pos',
            ];
            if (isset($wellKnownStaffRedirectMap[$normalizedRole])) {
                header('Location: ' . BASE_URL . $wellKnownStaffRedirectMap[$normalizedRole]);
                exit;
            }

            $isManager = $this->hasRole('MANAGER') || $this->hasRole('ROLE_MANAGER');
            $isBusinessManager = $this->hasRole('BUSINESS_MANAGER') || $this->hasRole('ROLE_BUSINESS_MANAGER');
            if (!$isManager && !$isBusinessManager) {
                header('Location: ' . BASE_URL . '/unauthorized');
                exit;
            }
            // MANAGER/BUSINESS_MANAGER için normal admin dashboard
            // Permission kontrolü soft: izni yoksa da dashboard'u göster (read-only erişim)
            // Sadece requirePermission tüm yetkileri kapatırsa yönlendir
            try {
                if (!$this->hasPermission('dashboard.analytics') && !$this->hasPermission('dashboard.view')) {
                    $this->requirePermission('dashboard.view');
                    return;
                }
            } catch (\Exception $e) {
                // Permission servisi patladı, fallback: dashboard'u göster (read-only)
                \App\Core\Logger::warning('DashboardController: permission check failed, allowing access', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $settingsService = \App\Core\DependencyFactory::getSystemSettingsService();
        $businessRange = $settingsService->getBusinessDateRange();
        $today = $businessRange['date'];

        // ----- Date range resolution (path-based + query fallback) -----
        // v2.1: Accepts range as path param (/business/dashboard/year) OR ?range=year
        $allowedRanges = ['today', 'week', 'month', '3months', '6months', '9months', 'year', 'custom'];
        $rangeKey = $rangeParam ?? ($_GET['range'] ?? null);
        if (!$rangeKey || !in_array($rangeKey, $allowedRanges, true)) {
            $rangeKey = 'today';
        }
        // Persist in session for "sticky" range across requests
        $_SESSION['dashboard_range'] = $rangeKey;

        // Resolve start/end dates for the selected range
        list($startDate, $endDate) = $this->resolveRangeDates($rangeKey, $today, $_GET);

        // Range-aware: when range != today, use selected start/end (full day window)
        $queryStart = ($rangeKey !== 'today') ? ($startDate . ' 00:00:00') : $businessRange['start_datetime'];
        $queryEnd = ($rangeKey !== 'today') ? ($endDate . ' 23:59:59') : $businessRange['end_datetime'];

        $allTables = $this->tableService->getAllTables();
        $allTables = $allTables ?: [];

        $dailyRevenue = $this->orderService->getDailyRevenueByDatetimeRange($queryStart, $queryEnd);
        $estimatedRevenue = $this->orderService->getEstimatedRevenueByDatetimeRange($queryStart, $queryEnd);
        $pendingStatus = ConstantsHelper::getOrderStatus('PENDING');
        $pendingOrders = $this->orderService->getOrdersByStatus($pendingStatus);
        // PERFORMANCE OPTIMIZATION: Get only recent orders (last 5) instead of all orders
        // getAllOrders() without limit can load thousands of orders - very slow!
        $recentOrders = [];
        try {
            if (method_exists($this->orderService, 'getRecentOrders')) {
                $recentOrders = $this->orderService->getRecentOrders(5); // Repository handles today+cancel filtering
            } else {
                // Fallback: Use business day orders only
                $todayOrders = $this->orderService->getOrdersByDatetimeRange($queryStart, $queryEnd);
                $recentOrders = is_array($todayOrders) ? array_slice($todayOrders, 0, 5) : [];
            }
            $recentOrders = is_array($recentOrders) ? $recentOrders : [];
        } catch (\Exception $e) {
            $recentOrders = [];
        }
        $topSellingItems = $this->orderService->getTopSellingItems(25, $queryStart, $queryEnd);
        $activeTables = $this->tableService->getActiveTables();
        
        // Calculate Row 2 KPI values - use datetime range for business hours (matches ciro reset)
        $todayOrders = $this->orderService->getOrdersByDatetimeRange($queryStart, $queryEnd);
        $todayOrders = is_array($todayOrders) ? $todayOrders : [];
        $cancelledStatus = ConstantsHelper::getOrderStatus('CANCELLED');
        $todayOrders = array_filter($todayOrders, function($order) use ($cancelledStatus) {
            return ($order['status'] ?? '') !== $cancelledStatus;
        });
        $totalOrdersToday = count($todayOrders);

        // Canonical avg basket: paid/served non-cancelled orders (matches ciro charts).
        $avgOrderValue = $this->orderService->calculateAvgOrderValue($queryStart, $queryEnd);

        $rangeBundle = $this->buildRangeMetricsBundle($rangeKey, $today, $_GET, $settingsService, $businessRange);
        $dailyRevenue = $rangeBundle['daily_revenue'];
        $totalOrdersToday = $rangeBundle['total_orders'];
        $avgOrderValue = $rangeBundle['avg_order_value'];
        $revenueChange = $rangeBundle['revenue_change'];
        $ordersChange = $rangeBundle['orders_change'];
        $avgOrderChange = $rangeBundle['avg_order_change'];
        
        // Unique customers (unique table_ids) today
        $uniqueTablesToday = array_unique(array_column($todayOrders, 'table_id'));
        $uniqueCustomersToday = count(array_filter($uniqueTablesToday, function($tableId) {
            return !empty($tableId);
        }));
        
        // Today's served orders count (optimize: load status once, not in loop)
        $servedStatus = ConstantsHelper::getOrderStatus('SERVED');
        $todayServedOrders = array_filter($todayOrders, function($order) use ($servedStatus) {
                return ($order['status'] ?? '') === $servedStatus;
        });
        $todayServedCount = count($todayServedOrders);
        
        // Calculate Row 1 KPI values
        $occupancyPercent = count($allTables) > 0 ? round(($this->tableService->getOccupiedCount() / count($allTables)) * 100) : 0;
        $pendingOrdersCount = is_array($pendingOrders) ? count($pendingOrders) : 0;
        
        // Calculate net profit using actual expenses (revenue - expenses)
        $todayExpenses = $this->financeService->getTotalExpensesByDateRange($startDate, $endDate);
        $realProfit = $dailyRevenue - $todayExpenses;
        $estimatedProfit = $estimatedRevenue - $todayExpenses;
        
        $zones = $this->tableService->getAllZones();
        
        // Get recent notifications from service
        $recentNotifications = $this->notificationService->getAll(10);
        $recentNotifications = is_array($recentNotifications) ? $recentNotifications : [];

        $orderService = $this->orderService;
        $data = [
            'daily_revenue' => $dailyRevenue,
            'estimated_revenue' => $estimatedRevenue,
            'occupancy_percent' => $occupancyPercent,
            'pending_orders_count' => $pendingOrdersCount,
            'estimated_profit' => $estimatedProfit,
            'real_profit' => $realProfit,
            'active_tables_count' => $this->tableService->getOccupiedCount(),
            'unread_notifications_count' => $this->notificationService->getUnreadCount() ?: 0,
            'recent_orders' => is_array($recentOrders) ? array_slice($recentOrders, 0, 5) : [],
            'top_selling_items' => is_array($topSellingItems) ? $topSellingItems : [],
            'active_tables' => is_array($activeTables) ? $activeTables : [],
            'tables' => $allTables,
            'zones' => $zones,
            'notifications' => $recentNotifications,
            'total_orders_today' => $totalOrdersToday,
            'avg_order_value' => $avgOrderValue,
            'unique_customers_today' => $uniqueCustomersToday,
            'today_served_count' => $todayServedCount,
            'expenses_today' => $todayExpenses,
            'profit_margin_percent' => $dailyRevenue > 0 ? round(($realProfit / $dailyRevenue) * 100, 1) : 0,
            'cost_ratio_percent' => $dailyRevenue > 0 ? round(($todayExpenses / $dailyRevenue) * 100, 1) : 0,
            'table_turnover' => count($allTables) > 0 ? round($totalOrdersToday / count($allTables), 1) : 0,
            'total_tables' => count($allTables),
            'revenue_change_percent' => $revenueChange,
            'orders_change' => $ordersChange,
            'avg_order_change' => $avgOrderChange,
            'heatmap' => $rangeBundle['heatmap'] ?? ['days' => [], 'peak_hour' => 0, 'peak_count' => 0],
            'range_key' => $rangeKey, // pass to view for active filter highlighting
            'range_start_date' => $startDate,
            'range_end_date' => $endDate,
            'zones_formatted' => $rangeBundle['zones'] ?? [],
        ];

        $this->view('admin/dashboard', $data);
    }

    /**
     * Resolve (startDate, endDate) for a given range key.
     * Single source of truth — used by both dashboard() and getDashboardData().
     *
     * @return array{0:string,1:string} [startDate, endDate] in 'Y-m-d' format
     */
    private function resolveRangeDates(string $rangeKey, string $today, array $queryParams = []): array
    {
        switch ($rangeKey) {
            case 'week':
                return [date('Y-m-d', strtotime('monday this week')), date('Y-m-d', strtotime('sunday this week'))];
            case 'month':
                return [date('Y-m-01'), date('Y-m-t')];
            case '3months':
                return [date('Y-m-d', strtotime('-3 months')), $today];
            case '6months':
                return [date('Y-m-d', strtotime('-6 months')), $today];
            case '9months':
                return [date('Y-m-d', strtotime('-9 months')), $today];
            case 'year':
                return [date('Y-01-01'), date('Y-12-31')];
            case 'custom':
                return [
                    !empty($queryParams['start_date']) ? (string)$queryParams['start_date'] : $today,
                    !empty($queryParams['end_date']) ? (string)$queryParams['end_date'] : $today,
                ];
            case 'today':
            default:
                return [$today, $today];
        }
    }

    /**
     * Widget ids that support per-card date range filters.
     *
     * @return list<string>
     */
    private function getDashboardWidgetIds(): array
    {
        return [
            'kpi_revenue',
            'kpi_orders',
            'kpi_avg_basket',
            'panel_top_selling',
            'panel_category',
            'panel_hourly',
            'panel_weekly_trend',
            'panel_period_compare',
            'panel_auto_insights',
            'panel_payment',
            'panel_order_sources',
            'panel_staff',
        ];
    }

    /**
     * Resolve SQL datetime window for a range key (business-day aware for "today").
     *
     * @return array{0:string,1:string,2:string,3:string}
     */
    private function resolveQueryWindow(string $rangeKey, string $today, array $queryParams, array $businessRange): array
    {
        list($startDate, $endDate) = $this->resolveRangeDates($rangeKey, $today, $queryParams);
        if ($rangeKey !== 'today') {
            $queryStart = $startDate . ' 00:00:00';
            $queryEnd = $endDate . ' 23:59:59';
        } else {
            $queryStart = $businessRange['start_datetime'];
            $queryEnd = $businessRange['end_datetime'];
        }
        return [$queryStart, $queryEnd, $startDate, $endDate];
    }

    /**
     * @return array<string, string>
     */
    private function parseWidgetRanges(string $defaultRange): array
    {
        $globalAllowed = function_exists('getDashboardRangeLabels')
            ? array_keys(getDashboardRangeLabels())
            : ['today', 'week', 'month', '3months', '6months', '9months', 'year', 'custom'];
        $fallback = in_array($defaultRange, $globalAllowed, true) ? $defaultRange : 'today';
        $widgets = [];
        foreach ($this->getDashboardWidgetIds() as $wid) {
            $widgets[$wid] = $fallback;
        }
        $raw = $_GET['ranges'] ?? null;
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                foreach ($decoded as $wid => $rk) {
                    if (isset($widgets[$wid]) && is_string($rk) && in_array($rk, $globalAllowed, true)) {
                        $widgets[$wid] = $rk;
                    }
                }
            }
        }
        return $widgets;
    }

    /**
     * @return array{0:string,1:string}
     */
    private function resolvePreviousQueryWindow(
        string $rangeKey,
        string $startDate,
        string $endDate,
        $settingsService,
        string $today
    ): array {
        if ($rangeKey === 'today') {
            $yesterday = date('Y-m-d', strtotime('-1 day', strtotime($today)));
            $prevRange = $settingsService->getBusinessDateRangeForDate($yesterday);
            return [$prevRange['start_datetime'], $prevRange['end_datetime']];
        }
        $spanDays = max(1, (int)floor((strtotime($endDate) - strtotime($startDate)) / 86400) + 1);
        $prevStart = date('Y-m-d', strtotime("-{$spanDays} days", strtotime($startDate)));
        $prevEnd = date('Y-m-d', strtotime("-{$spanDays} days", strtotime($endDate)));
        return [$prevStart . ' 00:00:00', $prevEnd . ' 23:59:59'];
    }

    /**
     * @param array<int, array<string, mixed>> $hourlySales
     * @return array<int, array<string, mixed>>
     */
    private function buildHourlyChartData(array $hourlySales): array
    {
        $hourlyChartData = [];
        for ($hour = 0; $hour < 24; $hour++) {
            $hourData = null;
            foreach ($hourlySales as $item) {
                if ((int)($item['hour'] ?? -1) === $hour) {
                    $hourData = $item;
                    break;
                }
            }
            $hourlyChartData[] = [
                'hour' => $hour,
                'order_count' => (int)($hourData['order_count'] ?? 0),
                'revenue' => floatval($hourData['revenue'] ?? 0),
            ];
        }
        return $hourlyChartData;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildWeeklyTrendForRange(string $rangeKey, string $startDate, string $endDate): array
    {
        $weeklyTrend = [];
        try {
            $period = 'day';
            switch ($rangeKey) {
                case 'today':
                    $period = 'hour';
                    break;
                case 'week':
                case 'month':
                    $period = 'day';
                    break;
                case '3months':
                case '6months':
                    $period = 'week';
                    break;
                case '9months':
                case 'year':
                    $period = 'month';
                    break;
            }
            $rangeOrders = $this->orderService->getOrdersByDateRange($startDate, $endDate);
            $rangeOrders = is_array($rangeOrders) ? $rangeOrders : [];
            $cancelledStatus = ConstantsHelper::getOrderStatus('CANCELLED');
            $buckets = [];
            foreach ($rangeOrders as $order) {
                if (($order['status'] ?? '') === $cancelledStatus) {
                    continue;
                }
                $isPaid = !empty($order['is_paid']);
                $isServed = ($order['status'] ?? '') === ConstantsHelper::getOrderStatus('SERVED');
                if (!$isPaid && !$isServed) {
                    continue;
                }
                $ts = strtotime($order['created_at'] ?? 'now');
                if (!$ts) {
                    continue;
                }
                if ($period === 'hour') {
                    $key = date('Y-m-d H:00:00', $ts);
                    $label = (int)date('H', $ts) . ':00';
                } elseif ($period === 'week') {
                    $key = date('o-W', $ts);
                    $label = date('d.m', strtotime('monday this week', $ts));
                } elseif ($period === 'month') {
                    $key = date('Y-m', $ts);
                    $label = date('M', $ts);
                } else {
                    $key = date('Y-m-d', $ts);
                    $label = date('d.m', $ts);
                }
                if (!isset($buckets[$key])) {
                    $buckets[$key] = ['orders' => 0, 'revenue' => 0, 'label' => $label];
                }
                $buckets[$key]['orders']++;
                $buckets[$key]['revenue'] += floatval($order['total_amount'] ?? 0);
            }
            if ($period === 'hour') {
                for ($h = 0; $h < 24; $h++) {
                    $key = $startDate . ' ' . sprintf('%02d:00:00', $h);
                    $b = $buckets[$key] ?? ['orders' => 0, 'revenue' => 0, 'label' => $h . ':00'];
                    $weeklyTrend[] = [
                        'date' => $key,
                        'day_name' => $b['label'],
                        'revenue' => floatval($b['revenue']),
                        'orders_count' => (int)$b['orders'],
                    ];
                }
            } elseif ($period === 'day') {
                $s = strtotime($startDate);
                $e = strtotime($endDate);
                for ($ts = $s; $ts <= $e; $ts += 86400) {
                    $key = date('Y-m-d', $ts);
                    $b = $buckets[$key] ?? ['orders' => 0, 'revenue' => 0, 'label' => date('d.m', $ts)];
                    $weeklyTrend[] = [
                        'date' => $key,
                        'day_name' => $b['label'],
                        'revenue' => floatval($b['revenue']),
                        'orders_count' => (int)$b['orders'],
                    ];
                }
            } elseif ($period === 'week') {
                $s = strtotime('monday this week', strtotime($startDate));
                $e = strtotime('sunday this week', strtotime($endDate));
                for ($ts = $s; $ts <= $e; $ts += 7 * 86400) {
                    $key = date('o-W', $ts);
                    $b = $buckets[$key] ?? ['orders' => 0, 'revenue' => 0, 'label' => date('d.m', $ts)];
                    $weeklyTrend[] = [
                        'date' => date('Y-m-d', $ts),
                        'day_name' => 'Hf ' . $b['label'],
                        'revenue' => floatval($b['revenue']),
                        'orders_count' => (int)$b['orders'],
                    ];
                }
            } else {
                $s = strtotime(date('Y-m-01', strtotime($startDate)));
                $e = strtotime(date('Y-m-t', strtotime($endDate)));
                for ($ts = $s; $ts <= $e; $ts += (int)date('t', $ts) * 86400) {
                    $key = date('Y-m', $ts);
                    $b = $buckets[$key] ?? ['orders' => 0, 'revenue' => 0, 'label' => date('M', $ts)];
                    $weeklyTrend[] = [
                        'date' => date('Y-m-d', $ts),
                        'day_name' => $b['label'],
                        'revenue' => floatval($b['revenue']),
                        'orders_count' => (int)$b['orders'],
                    ];
                }
            }
        } catch (\Exception $e) {
            \App\Core\Logger::warning('buildWeeklyTrendForRange: ' . $e->getMessage());
            $weeklyTrend = [];
        }
        return $weeklyTrend;
    }

    /**
     * Metrics bundle for one date range — shared by global + per-widget poller.
     *
     * @return array<string, mixed>
     */
    private function buildRangeMetricsBundle(
        string $rangeKey,
        string $today,
        array $queryParams,
        $settingsService,
        array $businessRange
    ): array {
        list($queryStart, $queryEnd, $startDate, $endDate) = $this->resolveQueryWindow(
            $rangeKey,
            $today,
            $queryParams,
            $businessRange
        );
        list($prevQueryStart, $prevQueryEnd) = $this->resolvePreviousQueryWindow(
            $rangeKey,
            $startDate,
            $endDate,
            $settingsService,
            $today
        );

        $dailyRevenue = $this->orderService->getDailyRevenueByDatetimeRange($queryStart, $queryEnd);
        $prevRevenue = $this->orderService->getDailyRevenueByDatetimeRange($prevQueryStart, $prevQueryEnd);
        $revenueChange = $prevRevenue > 0
            ? round((($dailyRevenue - $prevRevenue) / $prevRevenue) * 100, 1)
            : 0;

        $cancelledStatus = ConstantsHelper::getOrderStatus('CANCELLED');
        $orders = $this->orderService->getOrdersByDatetimeRange($queryStart, $queryEnd);
        $orders = is_array($orders) ? $orders : [];
        $orders = array_values(array_filter($orders, function ($order) use ($cancelledStatus) {
            return ($order['status'] ?? '') !== $cancelledStatus;
        }));
        $totalOrders = count($orders);

        $prevOrders = $this->orderService->getOrdersByDatetimeRange($prevQueryStart, $prevQueryEnd);
        $prevOrders = is_array($prevOrders) ? $prevOrders : [];
        $prevOrders = array_values(array_filter($prevOrders, function ($order) use ($cancelledStatus) {
            return ($order['status'] ?? '') !== $cancelledStatus;
        }));
        $totalOrdersPrev = count($prevOrders);
        $ordersChange = $totalOrdersPrev > 0
            ? round((($totalOrders - $totalOrdersPrev) / $totalOrdersPrev) * 100, 1)
            : 0;

        $avgOrderValue = $this->orderService->calculateAvgOrderValue($queryStart, $queryEnd);
        $prevAvg = $this->orderService->calculateAvgOrderValue($prevQueryStart, $prevQueryEnd);
        $avgOrderChange = $prevAvg > 0
            ? round((($avgOrderValue - $prevAvg) / $prevAvg) * 100, 1)
            : 0;

        $topSelling = $this->orderService->getTopSellingItems(25, $queryStart, $queryEnd);
        $hourlyRaw = $this->orderService->getHourlySales($queryStart, $queryEnd);
        $hourlySales = $this->buildHourlyChartData(is_array($hourlyRaw) ? $hourlyRaw : []);
        $categoryRevenue = $this->orderService->getRevenueByCategory($queryStart, $queryEnd);
        $categoryRevenue = is_array($categoryRevenue) ? $categoryRevenue : [];
        $weeklyTrend = $this->buildWeeklyTrendForRange($rangeKey, $startDate, $endDate);

        $allOrdersRaw = $this->orderService->getOrdersByDatetimeRange($queryStart, $queryEnd);
        $allOrdersRaw = is_array($allOrdersRaw) ? $allOrdersRaw : [];
        $cancelledCount = count(array_filter($allOrdersRaw, function ($o) use ($cancelledStatus) {
            return ($o['status'] ?? '') === $cancelledStatus;
        }));
        $cancellationRate = count($allOrdersRaw) > 0
            ? round(($cancelledCount / count($allOrdersRaw)) * 100, 1)
            : 0.0;

        $prevAllRaw = $this->orderService->getOrdersByDatetimeRange($prevQueryStart, $prevQueryEnd);
        $prevAllRaw = is_array($prevAllRaw) ? $prevAllRaw : [];
        $prevCancelled = count(array_filter($prevAllRaw, function ($o) use ($cancelledStatus) {
            return ($o['status'] ?? '') === $cancelledStatus;
        }));
        $prevCancellationRate = count($prevAllRaw) > 0
            ? round(($prevCancelled / count($prevAllRaw)) * 100, 1)
            : 0.0;

        $expenses = floatval($this->financeService->getTotalExpensesByDateRange($startDate, $endDate));
        $realProfit = floatval($dailyRevenue) - $expenses;
        $profitMargin = floatval($dailyRevenue) > 0
            ? round(($realProfit / floatval($dailyRevenue)) * 100, 1)
            : 0.0;

        list($prevStartDate, $prevEndDate) = $this->resolvePreviousDateRange($rangeKey, $startDate, $endDate, $settingsService, $today);
        $prevExpenses = floatval($this->financeService->getTotalExpensesByDateRange($prevStartDate, $prevEndDate));
        $prevProfit = floatval($prevRevenue) - $prevExpenses;

        $uniqueTables = array_unique(array_column($orders, 'table_id'));
        $uniqueCustomers = count(array_filter($uniqueTables, function ($tableId) {
            return !empty($tableId);
        }));
        $prevUniqueTables = array_unique(array_column($prevOrders, 'table_id'));
        $prevUniqueCustomers = count(array_filter($prevUniqueTables, function ($tableId) {
            return !empty($tableId);
        }));

        $paymentOrders = $this->orderService->getOrdersByDatetimeRange($queryStart, $queryEnd);
        $paymentDistribution = $this->computePaymentDistribution(is_array($paymentOrders) ? $paymentOrders : []);

        $orderSourceDistribution = $this->buildOrderSourceDistribution($orders);

        $staffPerformance = $this->computeStaffPerformance($orders);

        $heatmap = $this->computeHeatmapForRange($rangeKey, $startDate, $endDate, $today);
        $zoneUsage = $this->computeZoneUsageForRange($orders);
        $orderStatusDistribution = $this->computeOrderStatusDistribution($allOrdersRaw);

        $periodComparison = $this->computePeriodComparison(
            floatval($dailyRevenue),
            floatval($prevRevenue),
            $totalOrders,
            $totalOrdersPrev,
            floatval($avgOrderValue),
            floatval($prevAvg),
            $realProfit,
            $prevProfit,
            $uniqueCustomers,
            $prevUniqueCustomers,
            $cancellationRate,
            $prevCancellationRate
        );

        $autoInsights = $this->computeAutoInsights(
            $hourlySales,
            $categoryRevenue,
            $heatmap,
            $revenueChange,
            $cancellationRate,
            $totalOrders,
            floatval($dailyRevenue),
            $rangeKey,
            floatval($avgOrderValue),
            $periodComparison
        );

        return [
            'range' => $rangeKey,
            'daily_revenue' => floatval($dailyRevenue),
            'revenue_change' => $revenueChange,
            'total_orders' => $totalOrders,
            'orders_change' => $ordersChange,
            'avg_order_value' => floatval($avgOrderValue),
            'avg_order_change' => $avgOrderChange,
            'real_profit' => $realProfit,
            'profit_margin_percent' => $profitMargin,
            'expenses' => $expenses,
            'cancellation_rate' => $cancellationRate,
            'unique_customers' => $uniqueCustomers,
            'panel_top_selling' => is_array($topSelling) ? $topSelling : [],
            'top_selling_items' => is_array($topSelling) ? $topSelling : [],
            'hourly_sales' => $hourlySales,
            'category_revenue' => $categoryRevenue,
            'revenue_by_category' => $categoryRevenue,
            'weekly_trend' => $weeklyTrend,
            'payment_distribution' => $paymentDistribution,
            'order_sources' => $orderSourceDistribution,
            'staff_performance' => $staffPerformance,
            'period_comparison' => $periodComparison,
            'auto_insights' => $autoInsights,
            'heatmap' => $heatmap,
            'zones' => $zoneUsage,
            'order_status_distribution' => $orderStatusDistribution,
        ];
    }

    /**
     * @return array{0:string,1:string}
     */
    private function resolvePreviousDateRange(
        string $rangeKey,
        string $startDate,
        string $endDate,
        $settingsService,
        string $today
    ): array {
        if ($rangeKey === 'today') {
            $yesterday = date('Y-m-d', strtotime('-1 day', strtotime($today)));
            return [$yesterday, $yesterday];
        }
        $spanDays = max(1, (int)floor((strtotime($endDate) - strtotime($startDate)) / 86400) + 1);
        $prevStart = date('Y-m-d', strtotime("-{$spanDays} days", strtotime($startDate)));
        $prevEnd = date('Y-m-d', strtotime("-{$spanDays} days", strtotime($endDate)));
        return [$prevStart, $prevEnd];
    }

    /**
     * @return array<string, array<string, float|int>>
     */
    private function computePeriodComparison(
        float $revenue,
        float $prevRevenue,
        int $orders,
        int $prevOrders,
        float $avgBasket,
        float $prevAvgBasket,
        float $profit,
        float $prevProfit,
        int $customers,
        int $prevCustomers,
        float $cancelRate,
        float $prevCancelRate
    ): array {
        $pct = static function (float $current, float $previous): float {
            if ($previous > 0) {
                return round((($current - $previous) / $previous) * 100, 1);
            }
            return $current > 0 ? 100.0 : 0.0;
        };

        return [
            'revenue' => [
                'current' => round($revenue, 2),
                'previous' => round($prevRevenue, 2),
                'change_pct' => $pct($revenue, $prevRevenue),
            ],
            'orders' => [
                'current' => $orders,
                'previous' => $prevOrders,
                'change_pct' => $pct((float)$orders, (float)$prevOrders),
            ],
            'avg_basket' => [
                'current' => round($avgBasket, 2),
                'previous' => round($prevAvgBasket, 2),
                'change_pct' => $pct($avgBasket, $prevAvgBasket),
            ],
            'profit' => [
                'current' => round($profit, 2),
                'previous' => round($prevProfit, 2),
                'change_pct' => $pct($profit, $prevProfit),
            ],
            'customers' => [
                'current' => $customers,
                'previous' => $prevCustomers,
                'change_pct' => $pct((float)$customers, (float)$prevCustomers),
            ],
            'cancellation_rate' => [
                'current' => $cancelRate,
                'previous' => $prevCancelRate,
                'change_pct' => round($cancelRate - $prevCancelRate, 1),
            ],
        ];
    }

    /**
     * Rule-based insights from real metrics — labeled as automated, not generative AI.
     *
     * @return array<int, array<string, string>>
     */
    private function computeAutoInsights(
        array $hourlySales,
        array $categoryRevenue,
        array $heatmap,
        float $revenueChange,
        float $cancellationRate,
        int $totalOrders,
        float $dailyRevenue,
        string $rangeKey = 'today',
        float $avgOrderValue = 0.0,
        array $periodComparison = []
    ): array {
        $insights = [];
        $periodPhrase = $this->getInsightPeriodPhrase($rangeKey);

        if ($totalOrders === 0) {
            return [[
                'type' => 'no_orders',
                'title' => 'Veri Bekleniyor',
                'text' => 'Seçili dönemde sipariş bulunamadı. Dönem filtresini genişletmeyi deneyin.',
                'tone' => 'neutral',
            ]];
        }

        $totalHourlyRev = 0.0;
        $peakHour = null;
        $peakRev = 0.0;
        $quietHour = null;
        $quietRev = PHP_FLOAT_MAX;
        foreach ($hourlySales as $row) {
            $rev = floatval($row['revenue'] ?? 0);
            $hour = (int)($row['hour'] ?? 0);
            $totalHourlyRev += $rev;
            if ($rev > $peakRev) {
                $peakRev = $rev;
                $peakHour = $hour;
            }
            if ($rev < $quietRev) {
                $quietRev = $rev;
                $quietHour = $hour;
            }
        }
        if ($peakRev > 0 && $peakHour !== null) {
            $peakShare = $totalHourlyRev > 0 ? round(($peakRev / $totalHourlyRev) * 100) : 0;
            $insights[] = [
                'type' => 'peak_hour',
                'title' => 'Yoğun Saat',
                'text' => sprintf(
                    '%s %02d:00–%02d:59 aralığında en yüksek ciro (₺%s, toplamın ~%%%d’i).',
                    ucfirst($periodPhrase),
                    $peakHour,
                    $peakHour,
                    number_format($peakRev, 0, ',', '.'),
                    $peakShare
                ),
                'tone' => 'info',
            ];
        }

        if ($quietHour !== null && $peakHour !== null && $quietHour !== $peakHour && $quietRev < $peakRev * 0.15) {
            $insights[] = [
                'type' => 'quiet_hour',
                'title' => 'Sakin Saat Fırsatı',
                'text' => sprintf(
                    '%02d:00 civarı düşük yoğunluk — kampanya veya personel planlaması için uygun olabilir.',
                    $quietHour
                ),
                'tone' => 'neutral',
            ];
        }

        if ($revenueChange > 5) {
            $insights[] = [
                'type' => 'revenue_up',
                'title' => 'Ciro Artışı',
                'text' => 'Önceki döneme göre ciro %' . $revenueChange . ' arttı (₺' . number_format($dailyRevenue, 0, ',', '.') . ').',
                'tone' => 'success',
            ];
        } elseif ($revenueChange < -5) {
            $insights[] = [
                'type' => 'revenue_down',
                'title' => 'Ciro Düşüşü',
                'text' => 'Önceki döneme göre ciro %' . abs($revenueChange) . ' azaldı — yoğun saat ve menü performansını gözden geçirin.',
                'tone' => 'warning',
            ];
        }

        $ordersChange = floatval($periodComparison['orders']['change_pct'] ?? 0);
        if (abs($ordersChange) >= 10) {
            $insights[] = [
                'type' => 'orders_trend',
                'title' => $ordersChange > 0 ? 'Sipariş Artışı' : 'Sipariş Düşüşü',
                'text' => sprintf(
                    '%s %d sipariş alındı; önceki döneme göre %s%%%s.',
                    ucfirst($periodPhrase),
                    $totalOrders,
                    $ordersChange > 0 ? '+' : '',
                    rtrim(rtrim(number_format($ordersChange, 1, ',', ''), '0'), ',')
                ),
                'tone' => $ordersChange > 0 ? 'success' : 'warning',
            ];
        }

        if ($avgOrderValue > 0) {
            $basketChange = floatval($periodComparison['avg_basket']['change_pct'] ?? 0);
            if (abs($basketChange) >= 8) {
                $insights[] = [
                    'type' => 'basket_trend',
                    'title' => 'Sepet Değişimi',
                    'text' => sprintf(
                        'Ortalama sepet ₺%s — önceki döneme göre %s%%%s.',
                        number_format($avgOrderValue, 0, ',', '.'),
                        $basketChange > 0 ? '+' : '',
                        rtrim(rtrim(number_format($basketChange, 1, ',', ''), '0'), ',')
                    ),
                    'tone' => $basketChange > 0 ? 'success' : 'warning',
                ];
            }
        }

        if ($cancellationRate >= 8) {
            $insights[] = [
                'type' => 'cancel_high',
                'title' => 'Yüksek İptal Oranı',
                'text' => 'Siparişlerin %' . $cancellationRate . '’i iptal edildi — stok ve mutfak akışını kontrol edin.',
                'tone' => 'warning',
            ];
        }

        if (!empty($categoryRevenue)) {
            $sorted = $categoryRevenue;
            usort($sorted, function ($a, $b) {
                return floatval($b['revenue'] ?? 0) <=> floatval($a['revenue'] ?? 0);
            });
            $top = $sorted[0];
            $topName = $top['category_name'] ?? 'Kategori';
            $topRev = floatval($top['revenue'] ?? 0);
            if ($topRev > 0 && $dailyRevenue > 0) {
                $share = round(($topRev / $dailyRevenue) * 100);
                $insights[] = [
                    'type' => 'top_category',
                    'title' => 'Lider Kategori',
                    'text' => $topName . ' ₺' . number_format($topRev, 0, ',', '.') . ' ile toplam cironun ~%' . $share . '’ini oluşturdu.',
                    'tone' => 'info',
                ];
            }
            if (count($sorted) >= 2) {
                $second = $sorted[1];
                $secondRev = floatval($second['revenue'] ?? 0);
                if ($topRev > 0 && $secondRev > 0 && ($topRev / $secondRev) >= 2) {
                    $insights[] = [
                        'type' => 'category_concentration',
                        'title' => 'Kategori Yoğunlaşması',
                        'text' => ($top['category_name'] ?? 'Lider kategori') . ', '
                            . ($second['category_name'] ?? 'ikinci kategori') . '’den 2 kat fazla ciro üretti.',
                        'tone' => 'warning',
                    ];
                }
            }
        }

        if (!empty($heatmap['days'])) {
            $busiestLabel = null;
            $busiestFull = null;
            $maxOrders = 0;
            $heatmapTotal = 0;
            foreach ($heatmap['days'] as $day) {
                $cells = $day['cells'] ?? [];
                $dayTotal = 0;
                foreach ($cells as $cell) {
                    $dayTotal += (int)($cell['count'] ?? 0);
                }
                $heatmapTotal += $dayTotal;
                if ($dayTotal > $maxOrders) {
                    $maxOrders = $dayTotal;
                    $busiestLabel = $day['label'] ?? null;
                    $busiestFull = $day['label_full'] ?? $busiestLabel;
                }
            }
            if ($busiestFull && $maxOrders > 0) {
                $dayShare = $heatmapTotal > 0 ? round(($maxOrders / $heatmapTotal) * 100) : 0;
                $heatmapMode = $heatmap['mode'] ?? 'calendar';
                $dayWord = ($heatmapMode === 'weekday_aggregate') ? 'günleri' : 'günü';
                $insights[] = [
                    'type' => 'busy_day',
                    'title' => 'Yoğun Gün',
                    'text' => sprintf(
                        '%s %s %s en çok sipariş alındı (%d adet, ~%%%d).',
                        $busiestFull,
                        $dayWord,
                        $periodPhrase,
                        $maxOrders,
                        $dayShare
                    ),
                    'tone' => 'info',
                ];
            }

            if (($heatmap['peak_count'] ?? 0) > 0) {
                $peakH = (int)($heatmap['peak_hour'] ?? 0);
                $insights[] = [
                    'type' => 'heatmap_peak',
                    'title' => 'Yoğunluk Pik Noktası',
                    'text' => sprintf(
                        '%s tek bir saatte en fazla %d sipariş kaydedildi (%02d:00).',
                        ucfirst($periodPhrase),
                        (int)$heatmap['peak_count'],
                        $peakH
                    ),
                    'tone' => 'info',
                ];
            }
        }

        return array_slice($insights, 0, 6);
    }

    /**
     * Turkish period phrase for insight copy.
     */
    private function getInsightPeriodPhrase(string $rangeKey): string
    {
        $map = [
            'today' => 'bugün',
            'week' => 'bu hafta',
            'month' => 'bu ay',
            '3months' => 'son 3 ayda',
            '6months' => 'son 6 ayda',
            '9months' => 'son 9 ayda',
            'year' => 'bu yıl',
            'custom' => 'seçili dönemde',
        ];
        return $map[$rangeKey] ?? 'seçili dönemde';
    }

    /**
     * Human-readable dashboard range label.
     */
    private function getDashboardRangeLabel(string $rangeKey): string
    {
        if (function_exists('getDashboardRangeLabels')) {
            $labels = getDashboardRangeLabels();
            if (isset($labels[$rangeKey])) {
                return (string)$labels[$rangeKey];
            }
        }
        $fallback = [
            'today' => 'Bugün',
            'week' => 'Bu Hafta',
            'month' => 'Bu Ay',
            '3months' => 'Son 3 Ay',
            '6months' => 'Son 6 Ay',
            '9months' => 'Son 9 Ay',
            'year' => 'Bu Yıl',
            'custom' => 'Özel Aralık',
        ];
        return $fallback[$rangeKey] ?? 'Seçili Dönem';
    }

    /**
     * @return array{short: list<string>, full: list<string>}
     */
    private function getTurkishDayNames(): array
    {
        return [
            'short' => ['Paz', 'Pzt', 'Sal', 'Çar', 'Per', 'Cum', 'Cmt'],
            'full' => ['Pazar', 'Pazartesi', 'Salı', 'Çarşamba', 'Perşembe', 'Cuma', 'Cumartesi'],
        ];
    }

    /**
     * Build heatmap for the active dashboard range.
     * Short ranges: calendar days × 24h. Long ranges: weekday aggregate × 24h.
     *
     * @return array{days:array,peak_hour:int,peak_count:int,mode:string,range_label:string,total_orders:int}
     */
    private function computeHeatmapForRange(
        string $rangeKey,
        string $startDate,
        string $endDate,
        string $today
    ): array {
        $dayNames = $this->getTurkishDayNames();
        $spanDays = max(1, (int)floor((strtotime($endDate) - strtotime($startDate)) / 86400) + 1);
        $aggregateByWeekday = $spanDays > 14
            || in_array($rangeKey, ['month', '3months', '6months', '9months', 'year'], true);

        try {
            $orders = $this->orderService->getOrdersByDateRange($startDate, $endDate) ?: [];
        } catch (\Exception $e) {
            $orders = [];
        }
        $orders = is_array($orders) ? $orders : [];

        $peakHour = 0;
        $peakCount = 0;
        $peakRevenue = 0.0;
        $totalRevenue = 0.0;
        $days = [];

        if ($aggregateByWeekday) {
            $grid = [];
            for ($dow = 0; $dow < 7; $dow++) {
                $cells = [];
                for ($h = 0; $h < 24; $h++) {
                    $cells[$h] = ['hour' => $h, 'count' => 0, 'revenue' => 0.0, 'table_ids' => []];
                }
                $grid[$dow] = [
                    'date' => '',
                    'label' => $dayNames['short'][$dow],
                    'label_full' => $dayNames['full'][$dow],
                    'cells' => $cells,
                ];
            }
            foreach ($orders as $o) {
                $ts = strtotime($o['created_at'] ?? '');
                if (!$ts) {
                    continue;
                }
                $amount = floatval($o['total_amount'] ?? 0);
                $totalRevenue += $amount;
                $dow = (int)date('w', $ts);
                $hour = (int)date('G', $ts);
                $grid[$dow]['cells'][$hour]['count']++;
                $grid[$dow]['cells'][$hour]['revenue'] += $amount;
                $tableId = (string)($o['table_id'] ?? '');
                if ($tableId !== '') {
                    $grid[$dow]['cells'][$hour]['table_ids'][$tableId] = true;
                }
                if ($grid[$dow]['cells'][$hour]['count'] > $peakCount) {
                    $peakCount = $grid[$dow]['cells'][$hour]['count'];
                    $peakHour = $hour;
                    $peakRevenue = $grid[$dow]['cells'][$hour]['revenue'];
                }
            }
            foreach ([1, 2, 3, 4, 5, 6, 0] as $dow) {
                $grid[$dow]['slots'] = $this->aggregateHeatmapDaySlots($grid[$dow]['cells']);
                $grid[$dow]['cells'] = $this->normalizeHeatmapCells($grid[$dow]['cells']);
                $days[] = $grid[$dow];
            }
            $mode = 'weekday_aggregate';
        } else {
            $cellsByDate = [];
            foreach ($orders as $o) {
                $ts = strtotime($o['created_at'] ?? '');
                if (!$ts) {
                    continue;
                }
                $amount = floatval($o['total_amount'] ?? 0);
                $totalRevenue += $amount;
                $date = date('Y-m-d', $ts);
                $hour = (int)date('G', $ts);
                if (!isset($cellsByDate[$date])) {
                    $cellsByDate[$date] = [];
                    for ($h = 0; $h < 24; $h++) {
                        $cellsByDate[$date][$h] = ['count' => 0, 'revenue' => 0.0, 'table_ids' => []];
                    }
                }
                $cellsByDate[$date][$hour]['count']++;
                $cellsByDate[$date][$hour]['revenue'] += $amount;
                $tableId = (string)($o['table_id'] ?? '');
                if ($tableId !== '') {
                    $cellsByDate[$date][$hour]['table_ids'][$tableId] = true;
                }
                if ($cellsByDate[$date][$hour]['count'] > $peakCount) {
                    $peakCount = $cellsByDate[$date][$hour]['count'];
                    $peakHour = $hour;
                    $peakRevenue = $cellsByDate[$date][$hour]['revenue'];
                }
            }

            $displayDays = min($spanDays, 14);
            $endTs = strtotime($endDate);
            for ($i = $displayDays - 1; $i >= 0; $i--) {
                $dayDate = date('Y-m-d', strtotime("-$i days", $endTs));
                if (strtotime($dayDate) < strtotime($startDate)) {
                    continue;
                }
                $dow = (int)date('w', strtotime($dayDate));
                $isToday = ($dayDate === $today);
                $rawCells = $cellsByDate[$dayDate] ?? array_fill(0, 24, ['count' => 0, 'revenue' => 0.0, 'table_ids' => []]);
                $days[] = [
                    'date' => $dayDate,
                    'label' => $isToday ? 'Bugün' : $dayNames['short'][$dow],
                    'label_full' => $isToday ? 'Bugün' : $dayNames['full'][$dow],
                    'slots' => $this->aggregateHeatmapDaySlots($rawCells),
                    'cells' => $this->normalizeHeatmapCells($rawCells),
                ];
            }
            $mode = 'calendar';
        }

        return [
            'days' => $days,
            'peak_hour' => $peakHour,
            'peak_count' => $peakCount,
            'peak_revenue' => round($peakRevenue, 2),
            'mode' => $mode,
            'range_label' => $this->getDashboardRangeLabel($rangeKey),
            'total_orders' => count($orders),
            'total_revenue' => round($totalRevenue, 2),
        ];
    }

    /**
     * @param array<int|string,array<string,mixed>> $cells
     * @return array<int,array{hour:int,count:int,revenue:float,customers:int}>
     */
    private function normalizeHeatmapCells(array $cells): array
    {
        $normalized = [];
        for ($h = 0; $h < 24; $h++) {
            $cell = $cells[$h] ?? ['count' => 0, 'revenue' => 0.0, 'table_ids' => []];
            $tableIds = $cell['table_ids'] ?? [];
            $normalized[$h] = [
                'hour' => $h,
                'count' => (int)($cell['count'] ?? 0),
                'revenue' => floatval($cell['revenue'] ?? 0),
                'customers' => is_array($tableIds) ? count($tableIds) : 0,
            ];
        }
        return $normalized;
    }

    /**
     * @param array<int|string,array<string,mixed>> $cells
     * @return list<array{start_hour:int,count:int,revenue:float,customers:int}>
     */
    private function aggregateHeatmapDaySlots(array $cells, int $slotSize = 3): array
    {
        $slotCount = (int)(24 / $slotSize);
        $slots = [];
        for ($s = 0; $s < $slotCount; $s++) {
            $slots[$s] = [
                'start_hour' => $s * $slotSize,
                'count' => 0,
                'revenue' => 0.0,
                'table_ids' => [],
            ];
        }
        for ($h = 0; $h < 24; $h++) {
            $cell = $cells[$h] ?? ['count' => 0, 'revenue' => 0.0, 'table_ids' => []];
            $slot = (int)floor($h / $slotSize);
            $slots[$slot]['count'] += (int)($cell['count'] ?? 0);
            $slots[$slot]['revenue'] += floatval($cell['revenue'] ?? 0);
            $tableIds = $cell['table_ids'] ?? [];
            if (is_array($tableIds)) {
                foreach ($tableIds as $tableId => $_flag) {
                    $slots[$slot]['table_ids'][(string)$tableId] = true;
                }
            }
        }
        $result = [];
        foreach ($slots as $slot) {
            $result[] = [
                'start_hour' => (int)$slot['start_hour'],
                'count' => (int)$slot['count'],
                'revenue' => floatval($slot['revenue']),
                'customers' => count($slot['table_ids']),
            ];
        }
        return $result;
    }

    /**
     * @param array<string, string> $widgetRanges
     * @return array<string, array<string, mixed>>
     */
    private function buildWidgetData(
        array $widgetRanges,
        string $today,
        array $queryParams,
        $settingsService,
        array $businessRange
    ): array {
        $cache = [];
        $widgetData = [];
        foreach ($widgetRanges as $widgetId => $rangeKey) {
            if (!isset($cache[$rangeKey])) {
                $cache[$rangeKey] = $this->buildRangeMetricsBundle(
                    $rangeKey,
                    $today,
                    $queryParams,
                    $settingsService,
                    $businessRange
                );
            }
            $bundle = $cache[$rangeKey];
            switch ($widgetId) {
                case 'kpi_revenue':
                    $widgetData[$widgetId] = [
                        'range' => $rangeKey,
                        'daily_revenue' => $bundle['daily_revenue'],
                        'revenue_change' => $bundle['revenue_change'],
                    ];
                    break;
                case 'kpi_orders':
                    $widgetData[$widgetId] = [
                        'range' => $rangeKey,
                        'total_orders' => $bundle['total_orders'],
                        'orders_change' => $bundle['orders_change'],
                    ];
                    break;
                case 'kpi_avg_basket':
                    $widgetData[$widgetId] = [
                        'range' => $rangeKey,
                        'avg_order_value' => $bundle['avg_order_value'],
                        'avg_order_change' => $bundle['avg_order_change'],
                    ];
                    break;
                case 'panel_top_selling':
                    $widgetData[$widgetId] = [
                        'range' => $rangeKey,
                        'panel_top_selling' => $bundle['panel_top_selling'],
                    ];
                    break;
                case 'panel_category':
                    $widgetData[$widgetId] = [
                        'range' => $rangeKey,
                        'category_revenue' => $bundle['category_revenue'],
                    ];
                    break;
                case 'panel_hourly':
                    $widgetData[$widgetId] = [
                        'range' => $rangeKey,
                        'hourly_sales' => $bundle['hourly_sales'],
                    ];
                    break;
                case 'panel_weekly_trend':
                    $widgetData[$widgetId] = [
                        'range' => $rangeKey,
                        'weekly_trend' => $bundle['weekly_trend'],
                    ];
                    break;
                case 'panel_period_compare':
                    $widgetData[$widgetId] = [
                        'range' => $rangeKey,
                        'period_comparison' => $bundle['period_comparison'],
                    ];
                    break;
                case 'panel_auto_insights':
                    $widgetData[$widgetId] = [
                        'range' => $rangeKey,
                        'auto_insights' => $bundle['auto_insights'],
                    ];
                    break;
                case 'panel_payment':
                    $widgetData[$widgetId] = [
                        'range' => $rangeKey,
                        'payment_distribution' => $bundle['payment_distribution'],
                    ];
                    break;
                case 'panel_order_sources':
                    $widgetData[$widgetId] = [
                        'range' => $rangeKey,
                        'order_sources' => $bundle['order_sources'],
                    ];
                    break;
                case 'panel_staff':
                    $widgetData[$widgetId] = [
                        'range' => $rangeKey,
                        'staff_performance' => $bundle['staff_performance'],
                    ];
                    break;
            }
        }
        return $widgetData;
    }

    // =========================================================================
    // v2.2: "Real Business Owner" view — owner-grade analytics
    // =========================================================================

    /**
     * Compute staff performance from orders in the selected range.
     * Resolves names/roles from tenant users; maps owner session IDs (CUST_*);
     * enriches deleted staff from order/activity hints; lists every staff with data.
     *
     * @return array{waiters: array<int,array<string,mixed>>, other_count: int, other_revenue: float}
     */
    private function computeStaffPerformance(array $orders): array
    {
        $tenantId = \App\Core\TenantContext::getId()
            ?? \App\Core\TenantResolver::resolve()
            ?? ($_SESSION['customer_id'] ?? null);

        $staffById = $this->loadStaffDirectoryForPerformance($tenantId);
        $ownerUserId = $this->resolveOwnerUserIdForPerformance($staffById, $tenantId);
        $roleMapper = \App\Services\RoleMapper::getInstance();

        $skipCreators = ['customer', 'unknown', 'test', 'test_service', 'system', 'staff', ''];
        $byWaiter = [];
        $creatorIds = [];

        foreach ($orders as $o) {
            $rawId = trim((string)($o['created_by'] ?? $o['waiter_id'] ?? $o['staff_id'] ?? ''));
            if ($rawId === '' || in_array(strtolower($rawId), $skipCreators, true)) {
                continue;
            }
            $wid = $this->normalizeStaffPerformanceCreatorId($rawId, $tenantId, $ownerUserId);
            if ($wid === null) {
                continue;
            }
            $creatorIds[$wid] = true;
        }

        $hints = $this->loadStaffPerformanceHints($tenantId, array_keys($creatorIds));

        foreach ($orders as $o) {
            $rawId = trim((string)($o['created_by'] ?? $o['waiter_id'] ?? $o['staff_id'] ?? ''));
            if ($rawId === '' || in_array(strtolower($rawId), $skipCreators, true)) {
                continue;
            }

            $wid = $this->normalizeStaffPerformanceCreatorId($rawId, $tenantId, $ownerUserId);
            if ($wid === null) {
                continue;
            }

            $user = $staffById[$wid] ?? null;
            $isFormer = ($user === null);
            $identity = $this->resolveStaffPerformanceIdentity(
                $wid,
                $user,
                $hints,
                trim((string)($o['staff_name'] ?? '')),
                $roleMapper
            );

            if (!isset($byWaiter[$wid])) {
                $byWaiter[$wid] = [
                    'waiter_id' => $wid,
                    'waiter_name' => $identity['display_name'],
                    'role_code' => $identity['role_code'],
                    'role_label' => $identity['role_label'],
                    'is_former' => $isFormer,
                    'order_count' => 0,
                    'total_revenue' => 0.0,
                    'unique_tables' => [],
                ];
            }

            $byWaiter[$wid]['order_count']++;
            $byWaiter[$wid]['total_revenue'] += floatval($o['total_amount'] ?? 0);
            $tid = $o['table_id'] ?? null;
            if ($tid) {
                $byWaiter[$wid]['unique_tables'][$tid] = true;
            }
        }

        foreach ($byWaiter as &$w) {
            $w['unique_tables_served'] = count($w['unique_tables']);
            $w['avg_order_value'] = $w['order_count'] > 0
                ? round($w['total_revenue'] / $w['order_count'], 2)
                : 0;
            unset($w['unique_tables']);
        }
        unset($w);

        usort($byWaiter, fn($a, $b) => $b['total_revenue'] <=> $a['total_revenue']);

        return [
            'waiters' => array_values($byWaiter),
            'other_count' => 0,
            'other_revenue' => 0.0,
        ];
    }

    private function normalizeStaffPerformanceCreatorId(string $rawId, ?string $tenantId, ?string $ownerUserId): ?string
    {
        if ($tenantId !== null && $tenantId !== '' && $rawId === $tenantId) {
            return $ownerUserId ?: null;
        }

        if (strpos($rawId, 'CUST_') === 0) {
            if ($tenantId !== null && $rawId === $tenantId && $ownerUserId) {
                return $ownerUserId;
            }
            return null;
        }

        return $rawId;
    }

    /**
     * @param array<string,array{name?:string,role_code?:string}> $hints
     * @param array<string,mixed>|null $user
     * @return array{display_name:string,role_code:string,role_label:string}
     */
    private function resolveStaffPerformanceIdentity(
        string $userId,
        ?array $user,
        array $hints,
        string $orderStaffName,
        \App\Services\RoleMapper $roleMapper
    ): array {
        $hint = $hints[$userId] ?? [];
        $name = '';
        $roleCode = '';

        if ($user) {
            $name = trim((string)($user['name'] ?? ''));
            $roleCode = $this->normalizeStaffRoleCode(
                (string)($user['role'] ?? ''),
                (string)($user['role_id'] ?? ''),
                $roleMapper
            );
        }

        if ($name === '') {
            $name = trim((string)($hint['name'] ?? ''));
        }
        if ($name === '' && $orderStaffName !== '') {
            $name = $orderStaffName;
        }

        if ($roleCode === '') {
            $roleCode = strtoupper(str_replace('ROLE_', '', trim((string)($hint['role_code'] ?? ''))));
        }

        $isFormer = ($user === null);
        if ($name === '') {
            $name = $this->fallbackFormerStaffName($userId);
        }

        $roleLabel = $this->resolveStaffRoleLabel($roleCode, $roleMapper);
        if ($roleLabel === '' && $isFormer) {
            $roleLabel = 'Eski Personel';
        }

        $displayName = $name;
        if ($isFormer && stripos($displayName, 'eski personel') === false) {
            $displayName = $name . ' (Eski Personel)';
        }

        return [
            'display_name' => $displayName,
            'role_code' => $roleCode,
            'role_label' => $roleLabel,
        ];
    }

    private function resolveStaffRoleLabel(string $roleCode, \App\Services\RoleMapper $roleMapper): string
    {
        if ($roleCode === '') {
            return '';
        }

        $roleLabel = $roleMapper->getRoleLabel($roleCode);
        if ($roleLabel === '' || strtoupper($roleLabel) === strtoupper($roleCode)) {
            $fromId = $roleMapper->getRoleLabel('ROLE_' . strtoupper($roleCode));
            if ($fromId !== '' && strtoupper($fromId) !== strtoupper($roleCode)) {
                $roleLabel = $fromId;
            }
        }
        if ($roleLabel === '' || strtoupper($roleLabel) === strtoupper($roleCode)) {
            $roleLabel = $this->fallbackStaffRoleLabel($roleCode);
        }

        return $roleLabel;
    }

    private function fallbackFormerStaffName(string $userId): string
    {
        if (preg_match('/_(\d{4,})$/', $userId, $m)) {
            return 'Eski Personel #' . substr($m[1], -4);
        }

        return 'Eski Personel';
    }

    /**
     * @param array<int,string> $userIds
     * @return array<string,array{name?:string,role_code?:string}>
     */
    private function loadStaffPerformanceHints(?string $tenantId, array $userIds): array
    {
        $hints = [];
        if (empty($userIds)) {
            return $hints;
        }

        if ($tenantId !== null && $tenantId !== '') {
            try {
                $db = \App\Core\DependencyFactory::getDatabase();
                $placeholders = implode(',', array_fill(0, count($userIds), '?'));
                $sql = "SELECT performed_by,
                               MAX(NULLIF(TRIM(performed_by_name), '')) AS name,
                               MAX(NULLIF(TRIM(performed_by_role), '')) AS role_code
                        FROM table_activity_logs
                        WHERE tenant_id = ?
                          AND performed_by IN ({$placeholders})
                        GROUP BY performed_by";
                $stmt = $db->prepare($sql);
                $params = array_merge([$tenantId], $userIds);
                $stmt->execute($params);
                foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                    $uid = (string)($row['performed_by'] ?? '');
                    if ($uid === '') {
                        continue;
                    }
                    $hints[$uid] = [
                        'name' => trim((string)($row['name'] ?? '')),
                        'role_code' => trim((string)($row['role_code'] ?? '')),
                    ];
                }
            } catch (\Throwable $e) {
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::warning('DashboardController::loadStaffPerformanceHints activity logs failed', [
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            try {
                $db = \App\Core\DependencyFactory::getDatabase();
                $placeholders = implode(',', array_fill(0, count($userIds), '?'));
                $sql = "SELECT created_by,
                               MAX(NULLIF(TRIM(staff_name), '')) AS name
                        FROM orders
                        WHERE tenant_id = ?
                          AND created_by IN ({$placeholders})
                        GROUP BY created_by";
                $stmt = $db->prepare($sql);
                $params = array_merge([$tenantId], $userIds);
                $stmt->execute($params);
                foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                    $uid = (string)($row['created_by'] ?? '');
                    $name = trim((string)($row['name'] ?? ''));
                    if ($uid === '' || $name === '') {
                        continue;
                    }
                    if (!isset($hints[$uid])) {
                        $hints[$uid] = [];
                    }
                    if (empty($hints[$uid]['name'])) {
                        $hints[$uid]['name'] = $name;
                    }
                }
            } catch (\Throwable $e) {
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::warning('DashboardController::loadStaffPerformanceHints orders failed', [
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        try {
            $userRepo = \App\Core\DependencyFactory::getUserRepository();
            foreach ($userIds as $uid) {
                if (isset($hints[$uid]['name']) && ($hints[$uid]['name'] ?? '') !== '') {
                    continue;
                }
                $user = $userRepo->findByUserId($uid);
                if (!$user) {
                    continue;
                }
                if (!isset($hints[$uid])) {
                    $hints[$uid] = [];
                }
                if (empty($hints[$uid]['name'])) {
                    $hints[$uid]['name'] = trim((string)($user['name'] ?? ''));
                }
                if (empty($hints[$uid]['role_code'])) {
                    $hints[$uid]['role_code'] = trim((string)($user['role'] ?? ''));
                }
            }
        } catch (\Throwable $e) {
            // Non-fatal
        }

        return $hints;
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    private function loadStaffDirectoryForPerformance(?string $tenantId): array
    {
        if ($tenantId === null || $tenantId === '') {
            return [];
        }

        try {
            $userRepo = \App\Core\DependencyFactory::getUserRepository();
            $rows = $userRepo->getByBusinessId($tenantId) ?: [];
        } catch (\Exception $e) {
            return [];
        }

        $map = [];
        foreach ($rows as $u) {
            if (!empty($u['user_id'])) {
                $map[$u['user_id']] = $u;
            }
        }
        return $map;
    }

    /**
     * @param array<string,array<string,mixed>> $staffById
     */
    private function resolveOwnerUserIdForPerformance(array $staffById, ?string $tenantId): ?string
    {
        $preferredRoles = ['BUSINESS_MANAGER', 'BUSINESS_OWNER', 'MANAGER', 'ADMIN'];
        foreach ($preferredRoles as $role) {
            foreach ($staffById as $uid => $u) {
                $code = strtoupper(str_replace('ROLE_', '', trim((string)($u['role'] ?? ''))));
                if ($code === $role) {
                    return $uid;
                }
            }
        }

        if ($tenantId !== null && $tenantId !== '') {
            foreach ($staffById as $uid => $u) {
                if (($u['tenant_id'] ?? null) === $tenantId) {
                    return $uid;
                }
            }
        }

        return null;
    }

    private function normalizeStaffRoleCode(string $role, string $roleId, \App\Services\RoleMapper $roleMapper): string
    {
        $code = strtoupper(str_replace('ROLE_', '', trim($role)));
        if ($code !== '') {
            return $code;
        }
        if ($roleId !== '') {
            $fromId = $roleMapper->getRoleCode($roleId);
            if ($fromId) {
                return strtoupper(str_replace('ROLE_', '', trim($fromId)));
            }
        }
        return '';
    }

    private function fallbackStaffRoleLabel(string $roleCode): string
    {
        $map = [
            'WAITER' => 'Garson',
            'GARSON' => 'Garson',
            'CASHIER' => 'Kasiyer',
            'KASIYER' => 'Kasiyer',
            'KITCHEN' => 'Mutfak',
            'MANAGER' => 'Yönetici',
            'BUSINESS_MANAGER' => 'İşletme Yöneticisi',
            'BUSINESS_OWNER' => 'İşletme Sahibi',
            'ADMIN' => 'Yönetici',
        ];
        $key = strtoupper(str_replace('ROLE_', '', trim($roleCode)));
        return $map[$key] ?? ($key !== '' ? ucfirst(strtolower($key)) : '');
    }

    /**
     * Normalize order_source into user-facing channel buckets.
     */
    private function normalizeOrderChannel(string $source): string
    {
        $source = strtoupper(trim($source));
        if (in_array($source, ['POS', 'WAITER', 'TABLET', 'STAFF', 'CASHIER', 'KIOSK'], true)) {
            return 'GARSON';
        }
        if (in_array($source, ['QR', 'CUSTOMER', 'SELF_SERVICE'], true)) {
            return 'QR_MENU';
        }
        if ($source === 'PHONE') {
            return 'PHONE';
        }
        if ($source === 'ONLINE') {
            return 'ONLINE';
        }
        if ($source === '' || $source === 'UNKNOWN') {
            return 'GARSON';
        }
        return 'OTHER';
    }

    /**
     * @return array<string,int>
     */
    private function buildOrderSourceDistribution(array $orders): array
    {
        $dist = [];
        foreach ($orders as $order) {
            $channel = $this->normalizeOrderChannel((string)($order['order_source'] ?? 'UNKNOWN'));
            if (!isset($dist[$channel])) {
                $dist[$channel] = 0;
            }
            $dist[$channel]++;
        }
        return $dist;
    }

    /**
     * Paid-order payment split: Nakit / Kart only (MIXED split via receipt breakdown).
     *
     * @return array<string,array{count:int,total:float,label:string}>
     */
    private function computePaymentDistribution(array $orders): array
    {
        $dist = [
            'CASH' => ['count' => 0, 'total' => 0.0, 'label' => 'Nakit'],
            'CARD' => ['count' => 0, 'total' => 0.0, 'label' => 'Kart'],
        ];
        $mixedOrders = [];
        $cancelledStatus = ConstantsHelper::getOrderStatus('CANCELLED');

        foreach ($orders as $o) {
            $status = strtoupper((string)($o['status'] ?? ''));
            if ($status === 'CANCELLED' || $status === $cancelledStatus) {
                continue;
            }
            if (!$this->isOrderPaidForAnalytics($o)) {
                continue;
            }

            $amount = floatval($o['total_amount'] ?? 0);
            if ($amount <= 0) {
                continue;
            }

            $pm = strtoupper(trim((string)($o['payment_method'] ?? '')));
            if ($pm === 'MIXED') {
                $orderId = (string)($o['order_id'] ?? '');
                if ($orderId !== '') {
                    $mixedOrders[$orderId] = $amount;
                }
                continue;
            }

            $bucket = $this->mapPaymentMethodBucket($pm);
            $dist[$bucket]['count']++;
            $dist[$bucket]['total'] += $amount;
        }

        if (!empty($mixedOrders)) {
            $breakdowns = $this->loadReceiptPaymentBreakdowns(array_keys($mixedOrders));
            foreach ($mixedOrders as $orderId => $amount) {
                $bd = $breakdowns[$orderId] ?? null;
                $cashPart = is_array($bd) ? floatval($bd['cash'] ?? 0) : 0.0;
                $cardPart = is_array($bd) ? floatval($bd['card'] ?? 0) : 0.0;

                if ($cashPart <= 0 && $cardPart <= 0) {
                    $cashPart = $amount;
                } elseif (abs(($cashPart + $cardPart) - $amount) > 0.02) {
                    $sum = max($cashPart + $cardPart, 0.01);
                    $ratio = $amount / $sum;
                    $cashPart *= $ratio;
                    $cardPart *= $ratio;
                }

                if ($cashPart > 0) {
                    $dist['CASH']['count']++;
                    $dist['CASH']['total'] += $cashPart;
                }
                if ($cardPart > 0) {
                    $dist['CARD']['count']++;
                    $dist['CARD']['total'] += $cardPart;
                }
            }
        }

        return array_filter($dist, static function ($row) {
            return ($row['total'] ?? 0) > 0;
        });
    }

    private function isOrderPaidForAnalytics(array $order): bool
    {
        $isPaid = !empty($order['is_paid']) && ($order['is_paid'] == 1 || $order['is_paid'] === '1');
        $pm = strtoupper(trim((string)($order['payment_method'] ?? '')));
        $hasPm = $pm !== '' && !in_array($pm, ['PENDING', '-', 'ODENMEMIS', 'UNPAID', 'NONE'], true);
        return $isPaid || $hasPm;
    }

    private function mapPaymentMethodBucket(string $method): string
    {
        if (in_array($method, ['CARD', 'CREDIT_CARD', 'DEBIT_CARD', 'ONLINE', 'ONLINE_PAYMENT', 'POS_CARD', 'QR'], true)) {
            return 'CARD';
        }
        return 'CASH';
    }

    /**
     * @param list<string> $orderIds
     * @return array<string,array{cash:float,card:float}>
     */
    private function loadReceiptPaymentBreakdowns(array $orderIds): array
    {
        $orderIds = array_values(array_filter(array_unique($orderIds)));
        if (empty($orderIds)) {
            return [];
        }

        try {
            $db = \App\Core\DependencyFactory::getDatabase();
            $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
            $stmt = $db->prepare(
                "SELECT order_id, payment_breakdown
                 FROM receipts
                 WHERE order_id IN ($placeholders) AND status = 'ACTIVE'
                 ORDER BY created_at DESC"
            );
            $stmt->execute($orderIds);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $map = [];
            foreach ($rows as $row) {
                $oid = (string)($row['order_id'] ?? '');
                if ($oid === '' || isset($map[$oid])) {
                    continue;
                }
                $bd = $row['payment_breakdown'] ?? null;
                if (is_string($bd)) {
                    $bd = json_decode($bd, true);
                }
                if (!is_array($bd)) {
                    continue;
                }
                $map[$oid] = [
                    'cash' => floatval($bd['cash'] ?? 0),
                    'card' => floatval($bd['card'] ?? 0),
                ];
            }
            return $map;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Zone usage for selected period: distinct tables with orders / total tables per zone.
     *
     * @return array<string,array{total:int,occupied:int,percent:int,order_count:int}>
     */
    private function computeZoneUsageForRange(array $orders): array
    {
        $allTables = $this->tableService->getAllTables() ?: [];
        $allTables = is_array($allTables) ? $allTables : [];

        $zonesById = [];
        try {
            $zoneService = \App\Core\DependencyFactory::getZoneService();
            foreach ($zoneService->getAllZones() as $z) {
                $zId = $z['zone_id'] ?? null;
                if ($zId) {
                    $zonesById[$zId] = $z;
                }
            }
        } catch (\Exception $e) {
            // Continue without zone cache
        }

        $zonesData = [];
        $tableToZone = [];
        foreach ($allTables as $table) {
            $zoneName = $this->resolveTableZoneName($table, $zonesById);
            if (!isset($zonesData[$zoneName])) {
                $zonesData[$zoneName] = [
                    'total' => 0,
                    'used_tables' => [],
                    'order_count' => 0,
                ];
            }
            $zonesData[$zoneName]['total']++;
            $tableId = $table['table_id'] ?? null;
            if ($tableId) {
                $tableToZone[$tableId] = $zoneName;
            }
        }

        foreach ($orders as $order) {
            $tableId = $order['table_id'] ?? null;
            if (!$tableId || !isset($tableToZone[$tableId])) {
                continue;
            }
            $zoneName = $tableToZone[$tableId];
            $zonesData[$zoneName]['used_tables'][$tableId] = true;
            $zonesData[$zoneName]['order_count']++;
        }

        $formatted = [];
        foreach ($zonesData as $zone => $zoneData) {
            $used = count($zoneData['used_tables'] ?? []);
            $total = (int)($zoneData['total'] ?? 0);
            $formatted[$zone] = [
                'total' => $total,
                'occupied' => $used,
                'percent' => $total > 0 ? (int)round(($used / $total) * 100) : 0,
                'order_count' => (int)($zoneData['order_count'] ?? 0),
            ];
        }

        uasort($formatted, static function ($a, $b) {
            return ($b['occupied'] <=> $a['occupied']) ?: ($b['order_count'] <=> $a['order_count']);
        });

        return $formatted;
    }

    /**
     * Count orders by status for the selected period (includes cancelled).
     *
     * @return array<string,int>
     */
    private function computeOrderStatusDistribution(array $orders): array
    {
        $distribution = [];
        foreach ($this->constantsService->getOrderStatusCodes() as $statusCode) {
            $distribution[$statusCode] = 0;
        }

        foreach ($orders as $order) {
            $status = strtoupper(trim((string)($order['status'] ?? '')));
            if ($status === '') {
                continue;
            }
            if (!isset($distribution[$status])) {
                $distribution[$status] = 0;
            }
            $distribution[$status]++;
        }

        return $distribution;
    }

    private function resolveTableZoneName(array $table, array $zonesById): string
    {
        $zoneId = $table['zone_id'] ?? null;
        $zoneName = null;
        if ($zoneId && isset($zonesById[$zoneId])) {
            $zoneName = $zonesById[$zoneId]['name'] ?? null;
        }
        if (!$zoneName) {
            $zoneName = $table['zone'] ?? null;
        }
        if (!$zoneName || trim((string)$zoneName) === '') {
            return 'Diğer';
        }
        return (string)$zoneName;
    }

    /**
     * Find least-selling menu items by comparing today's orders to full menu.
     *
     * @return array<int,array{name:string,order_count:int,category:string}>
     */
    private function computeLeastSellingItems(int $limit = 5): array
    {
        try {
            $menuService = \App\Core\DependencyFactory::getMenuService();
            $allItems = method_exists($menuService, 'getAllMenuItems') ? $menuService->getAllMenuItems() : [];
        } catch (\Exception $e) {
            return [];
        }
        if (empty($allItems)) return [];

        // Count today's sales per item (already in memory as $topSellingItems? we don't have it here)
        // Caller will pass via session-temp if needed; for simplicity, just return unsold slice from menu
        $result = [];
        foreach ($allItems as $item) {
            $result[] = [
                'menu_item_id' => $item['menu_item_id'] ?? $item['id'] ?? null,
                'name' => $item['name'] ?? 'Ürün',
                'order_count' => intval($item['sales_count'] ?? $item['sold_count'] ?? 0),
                'category' => $item['category_name'] ?? $item['category'] ?? '',
            ];
        }
        usort($result, fn($a, $b) => $a['order_count'] <=> $b['order_count']);
        return array_slice($result, 0, $limit);
    }

    /**
     * Compute key premium metrics: profit margin, cost ratio, turnover.
     *
     * @return array<string,float>
     */
    private function computeKeyMetrics(array $orders, float $revenue, float $expenses, int $totalTables, int $activeTables): array
    {
        $profitMargin = $revenue > 0 ? round((($revenue - $expenses) / $revenue) * 100, 1) : 0.0;
        $costRatio = $revenue > 0 ? round(($expenses / $revenue) * 100, 1) : 0.0;
        $turnover = $totalTables > 0 ? round(count($orders) / $totalTables, 2) : 0.0;
        $avgOrder = count($orders) > 0 ? round($revenue / count($orders), 2) : 0.0;
        return [
            'profit_margin_percent' => $profitMargin,
            'cost_ratio_percent' => $costRatio,
            'table_turnover' => $turnover,
            'avg_order_value_calc' => $avgOrder,
            'active_tables_ratio' => $totalTables > 0 ? round(($activeTables / $totalTables) * 100, 1) : 0.0,
        ];
    }

    /**
     * Build a complete dashboard dataset for either server-render or AJAX.
     * Single source of truth — returns an array ready to be sent to view or JSON-encoded.
     *
     * @return array<string,mixed>
     */
    private function buildDashboardPayload(string $today, string $queryStart, string $queryEnd, string $rangeKey, bool $forApi = false): array
    {
        $allTables = $this->tableService->getAllTables() ?: [];
        $allActiveOrders = $this->orderService->getOrdersByDatetimeRange($queryStart, $queryEnd) ?: [];
        $allActiveOrders = is_array($allActiveOrders) ? $allActiveOrders : [];

        $dailyRevenue = $this->orderService->getDailyRevenueByDatetimeRange($queryStart, $queryEnd);
        $todayExpenses = $this->financeService->getTotalExpensesByDateRange($today, $today);
        $estimatedProfit = floatval($dailyRevenue) - floatval($todayExpenses);
        $activeTablesCount = $this->tableService->getOccupiedCount();
        $totalTables = count($allTables);

        $staffPerformance = $this->computeStaffPerformance($allActiveOrders);
        $paymentDistribution = $this->computePaymentDistribution($allActiveOrders);
        $heatmapStart = substr($queryStart, 0, 10);
        $heatmapEnd = substr($queryEnd, 0, 10);
        $heatmap = $this->computeHeatmapForRange($rangeKey, $heatmapStart, $heatmapEnd, $today);
        $keyMetrics = $this->computeKeyMetrics($allActiveOrders, floatval($dailyRevenue), floatval($todayExpenses), $totalTables, $activeTablesCount);

        // Status counts
        $pendingStatus = ConstantsHelper::getOrderStatus('PENDING');
        $preparingStatus = ConstantsHelper::getOrderStatus('PREPARING');
        $readyStatus = ConstantsHelper::getOrderStatus('READY');
        $servedStatus = ConstantsHelper::getOrderStatus('SERVED');
        $pendingCount = $preparingCount = $readyCount = $servedCount = 0;
        foreach ($allActiveOrders as $o) {
            $st = $o['status'] ?? '';
            if ($st === $pendingStatus) $pendingCount++;
            elseif ($st === $preparingStatus) $preparingCount++;
            elseif ($st === $readyStatus) $readyCount++;
            elseif ($st === $servedStatus) $servedCount++;
        }

        return [
            'kpi' => [
                'daily_revenue' => floatval($dailyRevenue),
                'estimated_profit' => floatval($estimatedProfit),
                'expenses_today' => floatval($todayExpenses),
                'profit_margin_percent' => $keyMetrics['profit_margin_percent'],
                'cost_ratio_percent' => $keyMetrics['cost_ratio_percent'],
                'total_orders_today' => count($allActiveOrders),
                'avg_order_value' => $keyMetrics['avg_order_value_calc'],
                'unique_customers_today' => count(array_unique(array_filter(array_column($allActiveOrders, 'table_id')))),
                'table_turnover' => $keyMetrics['table_turnover'],
                'active_tables' => $activeTablesCount,
                'total_tables' => $totalTables,
                'occupancy_percent' => $totalTables > 0 ? round(($activeTablesCount / $totalTables) * 100) : 0,
                'pending_orders_count' => $pendingCount,
                'preparing_orders_count' => $preparingCount,
                'ready_orders_count' => $readyCount,
                'served_orders_count' => $servedCount,
                'today_served_count' => $servedCount,
            ],
            'staff_performance' => $staffPerformance,
            'payment_distribution' => $paymentDistribution,
            'heatmap' => $heatmap,
            'key_metrics' => $keyMetrics,
            'range_key' => $rangeKey,
            'active_tables_count' => $activeTablesCount,
            'total_tables' => $totalTables,
            'tables' => $allTables,
        ];
    }

    /**
     * Get dashboard data for real-time updates (API endpoint)
     */
    public function getDashboardData() {
        // Super admin kontrolü - super admin her şeye erişebilir (en önce kontrol et)
        \App\Core\SessionManager::ensureSession();
        $this->ensureTenantContext();
        $sessionRole = \App\Core\SessionManager::get('role');
        $isSuperAdminSession = \App\Core\SessionManager::get('is_super_admin') === true;
        $isSuperAdmin = false;
        // Check super admin by session flag first
        if ($isSuperAdminSession) {
            $isSuperAdmin = true;
        } elseif ($sessionRole) {
            $normalizedRole = strtoupper(trim($sessionRole));
            $isSuperAdmin = ($normalizedRole === 'SUPER_ADMIN' || $normalizedRole === 'ROLE_SUPER_ADMIN' ||
                           $normalizedRole === 'QODMIN' || $normalizedRole === 'ROLE_QODMIN');
        }
        // Try Authorization method as fallback
        if (!$isSuperAdmin && isset($this->auth) && $this->auth !== null) {
            try {
                $isSuperAdmin = $this->isSuperAdmin();
            } catch (\Exception $e) {
                // Ignore exception, use session check result
            }
        }
        if ($isSuperAdmin) {
            // Super admin için normal admin dashboard API verisi (permission kontrolü yapmadan)
            // Devam et, aşağıdaki kod çalışacak - role ve permission kontrolü yapma
        } else {
            $managerRole = ConstantsHelper::getRole('MANAGER');
            $businessManagerRole = ConstantsHelper::getRole('BUSINESS_MANAGER');
            $isBusinessManager = $this->hasRole($businessManagerRole) ||
                                 $this->hasRole('ROLE_' . $businessManagerRole) ||
                                 $this->hasRole('BUSINESS_MANAGER');
            $isManager = $this->hasRole($managerRole) ||
                         $this->hasRole('ROLE_' . $managerRole);

            // BUSINESS_MANAGER / MANAGER: implicit dashboard access (matches dashboard() page)
            if (!$isBusinessManager && !$isManager
                && !$this->hasPermission('dashboard.analytics')
                && !$this->hasPermission('dashboard.view')) {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 401);
                return;
            }

            $hasRequiredRole = $isManager || $isBusinessManager;
            
            if (!$hasRequiredRole) {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 401);
                return;
            }

            if (!$isBusinessManager && !$isManager && !$this->hasPermission('dashboard.view')) {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 401);
                return;
            }
        }
        try {
            $settingsService = \App\Core\DependencyFactory::getSystemSettingsService();
            $businessRange = $settingsService->getBusinessDateRange();
            $today = $businessRange['date'];
            $yesterday = date('Y-m-d', strtotime('-1 day', strtotime($today)));
            $allTables = $this->tableService->getAllTables();
            $allTables = $allTables ?: [];
  // v2.1: Range resolution via single helper (deduped with dashboard())
 $allowedRangesApi = ['today', 'week', 'month', '3months', '6months', '9months', 'year', 'custom'];
 $apiRange = isset($_GET['range']) ? (string)$_GET['range'] : 'today';
 if (!in_array($apiRange, $allowedRangesApi, true)) { $apiRange = 'today'; }
 list($queryStart, $queryEnd, $apiStart, $apiEnd) = $this->resolveQueryWindow($apiRange, $today, $_GET, $businessRange);
            $rangeBundle = $this->buildRangeMetricsBundle($apiRange, $today, $_GET, $settingsService, $businessRange);
            $dailyRevenue = $rangeBundle['daily_revenue'];
            $revenueChange = $rangeBundle['revenue_change'];
            $totalOrdersToday = $rangeBundle['total_orders'];
            $ordersChange = $rangeBundle['orders_change'];
            $avgOrderValue = $rangeBundle['avg_order_value'];
            $avgOrderChange = $rangeBundle['avg_order_change'];
            $topSellingItems = $rangeBundle['top_selling_items'];
            $hourlyChartData = $rangeBundle['hourly_sales'];
            $revenueByCategory = $rangeBundle['category_revenue'];
            $weeklyTrend = $rangeBundle['weekly_trend'];

            $estimatedRevenue = $this->orderService->getEstimatedRevenueByDatetimeRange($queryStart, $queryEnd);
            list($prevQueryStart, $prevQueryEnd) = $this->resolvePreviousQueryWindow(
                $apiRange,
                $apiStart,
                $apiEnd,
                $settingsService,
                $today
            );
            $prevRevenue = $this->orderService->getDailyRevenueByDatetimeRange($prevQueryStart, $prevQueryEnd);
            $cancelledStatus = ConstantsHelper::getOrderStatus('CANCELLED');
            $todayOrders = $this->orderService->getOrdersByDatetimeRange($queryStart, $queryEnd);
            $todayOrders = is_array($todayOrders) ? $todayOrders : [];
            $todayOrders = array_values(array_filter($todayOrders, function ($order) use ($cancelledStatus) {
                return ($order['status'] ?? '') !== $cancelledStatus;
            }));
            $prevPeriodOrders = $this->orderService->getOrdersByDatetimeRange($prevQueryStart, $prevQueryEnd);
            $prevPeriodOrders = is_array($prevPeriodOrders) ? $prevPeriodOrders : [];
            $prevPeriodOrders = array_values(array_filter($prevPeriodOrders, function ($order) use ($cancelledStatus) {
                return ($order['status'] ?? '') !== $cancelledStatus;
            }));
            $totalOrdersPrev = count($prevPeriodOrders);
            // PERFORMANCE OPTIMIZATION: Get order counts in single query instead of 4 separate queries
            // This eliminates N+1 query problem for order status counts
            $pendingStatus = ConstantsHelper::getOrderStatus('PENDING');
            $preparingStatus = ConstantsHelper::getOrderStatus('PREPARING');
            $readyStatus = ConstantsHelper::getOrderStatus('READY');
            $servedStatus = ConstantsHelper::getOrderStatus('SERVED');
            
            // Get all active orders once and count by status in PHP (faster than 4 DB queries)
            $allActiveOrders = $this->orderService->getOrdersByDatetimeRange($queryStart, $queryEnd);
            $allActiveOrders = is_array($allActiveOrders) ? $allActiveOrders : [];
            
            $pendingCount = 0;
            $preparingCount = 0;
            $readyCount = 0;
            $servedCount = 0;
            
            foreach ($allActiveOrders as $order) {
                $status = $order['status'] ?? '';
                if ($status === $pendingStatus) {
                    $pendingCount++;
                } elseif ($status === $preparingStatus) {
                    $preparingCount++;
                } elseif ($status === $readyStatus) {
                    $readyCount++;
                } elseif ($status === $servedStatus) {
                    $servedCount++;
                }
            }
            
            // For pending orders list, still need to fetch them separately (but only if needed)
            $pendingOrders = [];
            if ($pendingCount > 0) {
                $pendingOrders = $this->orderService->getOrdersByStatus($pendingStatus);
            }
            // Today's served orders count (optimize: use already loaded $servedStatus)
            $todayServedOrders = array_filter($todayOrders, function($order) use ($servedStatus) {
                return ($order['status'] ?? '') === $servedStatus;
            });
            $todayServedCount = count($todayServedOrders);
            // Unique customers (unique table_ids) today
            $uniqueTablesToday = array_unique(array_column($todayOrders, 'table_id'));
            $uniqueCustomersToday = count(array_filter($uniqueTablesToday, function($tableId) {
                return !empty($tableId);
            }));
            $activeTablesCount = $this->tableService->getOccupiedCount();
            $totalTables = count($allTables);
            $occupancyPercent = $totalTables > 0 ? round(($activeTablesCount / $totalTables) * 100) : 0;
            
            // Calculate net profit using actual expenses (revenue - expenses)
            $todayExpenses = $this->financeService->getTotalExpensesByDateRange($apiStart, $apiEnd);
            $realProfit = $dailyRevenue - $todayExpenses;
            $estimatedProfit = $estimatedRevenue - $todayExpenses;
            // Get recent notifications
            $recentNotifications = $this->notificationService->getRecent(10);
            $recentNotifications = is_array($recentNotifications) ? $recentNotifications : [];
            // Get recent orders scoped to the current business day range
            $recentOrders = [];
            try {
                // Always scope to current business day to avoid showing stale historical data
                // Always scope to today + drop CANCELLED, so stale/cancelled rows don't pollute "Son Siparişler"
                if (method_exists($this->orderService, 'getRecentOrders')) {
                 $recentOrders = $this->orderService->getRecentOrders(10, true, true);
                } else {
                 $recentOrders = $this->orderService->getOrdersByDatetimeRange($queryStart, $queryEnd);
                }
                $recentOrders = is_array($recentOrders) ? array_slice($recentOrders, 0, 10) : [];
            } catch (\Exception $e) {
                $recentOrders = [];
            }
            // Zone usage for selected date range (distinct tables with orders per zone)
            $zonesFormatted = $rangeBundle['zones']
                ?? $this->computeZoneUsageForRange($todayOrders);
            // Order status distribution for selected date range (all statuses incl. cancelled)
            $orderStatusDistribution = $rangeBundle['order_status_distribution']
                ?? $this->computeOrderStatusDistribution($allActiveOrders);
            $orderSourceDistribution = $rangeBundle['order_sources']
                ?? $this->buildOrderSourceDistribution($todayOrders);
            // Most active tables
            $tableOrderCounts = [];
            foreach ($todayOrders as $order) {
                $tableId = $order['table_id'] ?? null;
                if ($tableId) {
                    if (!isset($tableOrderCounts[$tableId])) {
                        $tableOrderCounts[$tableId] = [
                            'table_id' => $tableId,
                            'table_name' => $order['table_name'] ?? 'Masa',
                            'order_count' => 0,
                            'total_revenue' => 0
                        ];
                    }
                    $tableOrderCounts[$tableId]['order_count']++;
                    $tableOrderCounts[$tableId]['total_revenue'] += floatval($order['total_amount'] ?? 0);
                }
            }
            usort($tableOrderCounts, function($a, $b) {
                return $b['order_count'] - $a['order_count'];
            });
            $mostActiveTables = array_slice($tableOrderCounts, 0, 5);
            // Table status breakdown
            $tableStatusCodes = $this->constantsService->getTableStatusCodes();
            $tableStatusBreakdown = [];
            foreach ($tableStatusCodes as $statusCode) {
                $tableStatusBreakdown[$statusCode] = 0;
            }
            foreach ($allTables as $table) {
                $status = $table['status'] ?? 'FREE';
                if (isset($tableStatusBreakdown[$status])) {
                    $tableStatusBreakdown[$status]++;
                }
            }
            $paymentDistribution = $rangeBundle['payment_distribution'] ?? [];
            $heatmap = $rangeBundle['heatmap']
                ?? $this->computeHeatmapForRange($apiRange, $apiStart, $apiEnd, $today);

            // 3) STAFF PERFORMANCE — bugünkü siparişlerden garson bazlı
            $staffPerformance = $this->computeStaffPerformance($todayOrders);

            // 4) KEY METRICS — kâr marjı, maliyet oranı, masa devri
            $keyMetrics = [];
            try {
                $profitMarginPct = $dailyRevenue > 0 ? round(($realProfit / $dailyRevenue) * 100, 1) : 0;
                $costRatioPct    = $dailyRevenue > 0 ? round(($todayExpenses / $dailyRevenue) * 100, 1) : 0;
                $tableTurnover   = $totalTables > 0 ? round($totalOrdersToday / $totalTables, 1) : 0;
                $keyMetrics = [
                    'profit_margin_percent' => $profitMarginPct,
                    'cost_ratio_percent'    => $costRatioPct,
                    'table_turnover'        => $tableTurnover,
                    'expenses_today'        => floatval($todayExpenses),
                    'revenue_change_percent'=> $revenueChange,
                ];
            } catch (\Exception $e) {
                $keyMetrics = [];
            }

            $widgetRanges = $this->parseWidgetRanges($apiRange);
            $widgetData = $this->buildWidgetData(
                $widgetRanges,
                $today,
                $_GET,
                $settingsService,
                $businessRange
            );

            $periodComparison = $rangeBundle['period_comparison'] ?? [];
            $autoInsights = $rangeBundle['auto_insights'] ?? [];
            $cancellationRate = $rangeBundle['cancellation_rate'] ?? 0.0;

            $data = [
                'success' => true,
                'data' => [
                    'kpi' => array_merge([
                        'daily_revenue'          => floatval($dailyRevenue),
                        'estimated_revenue'      => floatval($estimatedRevenue),
                        'yesterday_revenue'      => floatval($prevRevenue),
                        'prev_period_revenue'    => floatval($prevRevenue),
                        'revenue_change'         => $revenueChange,
                        'occupancy_percent'      => $occupancyPercent,
                        'pending_orders_count'   => $pendingCount,
                        'preparing_orders_count' => $preparingCount,
                        'ready_orders_count'     => $readyCount,
                        'served_orders_count'    => $servedCount,
                        'total_orders_today'     => $totalOrdersToday,
                        'total_orders_yesterday' => $totalOrdersPrev,
                        'total_orders_prev'      => $totalOrdersPrev,
                        'orders_change'          => $ordersChange,
                        'avg_order_value'        => floatval($avgOrderValue),
                        'avg_order_change'       => $avgOrderChange,
                        'unique_customers_today' => $uniqueCustomersToday,
                        'real_profit'            => floatval($realProfit),
                        'estimated_profit'       => floatval($estimatedProfit),
                        'today_served_count'     => $todayServedCount,
                        'expenses_today'         => floatval($todayExpenses),
                        'cancellation_rate'      => $cancellationRate,
                    ], $keyMetrics),
                    'notifications'            => $recentNotifications,
                    'recent_orders'            => $recentOrders,
                    'panel_live_orders'          => $recentOrders,
                    'top_selling_items'        => $topSellingItems,
                    'hourly_sales'             => $hourlyChartData,
                    'revenue_by_category'      => $revenueByCategory,
                    'category_revenue'         => $revenueByCategory,
                    'order_status_distribution'=> $orderStatusDistribution,
                    'order_source_distribution'=> $orderSourceDistribution,
                    'order_sources'            => $orderSourceDistribution,
                    'weekly_trend'             => $weeklyTrend,
                    'most_active_tables'       => $mostActiveTables,
                    'table_status_breakdown'   => $tableStatusBreakdown,
                    'table_status'             => $tableStatusBreakdown,
                    'zones'                    => $zonesFormatted,
                    'active_tables_count'      => $activeTablesCount,
                    'total_tables'             => $totalTables,
                    'unread_notifications_count' => $this->notificationService->getUnreadCount() ?: 0,
                    'staff_performance'        => $staffPerformance,
                    'payment_distribution'     => $paymentDistribution,
                    'heatmap'                  => $heatmap,
                    'key_metrics'              => $keyMetrics,
                    'period_comparison'        => $periodComparison,
                    'auto_insights'            => $autoInsights,
                    'widget_ranges'            => $widgetRanges,
                    'widget_data'              => $widgetData,
                ]
            ];
            $this->apiResponse($data);
        } catch (\Exception $e) {
            // Production ortamında detaylı hata mesajı gösterme
            $appEnv = $_ENV['APP_ENV'] ?? 'development';
            if ($appEnv === 'production') {
                \App\Core\Logger::error("Error in getDashboardData: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.dashboard_load_failed', [], 500);
            } else {
                // Development ortamında detaylı hata mesajı
                \App\Core\Logger::error("Error in getDashboardData: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.dashboard_load_failed', ['details' => $e->getMessage()], 500);
            }
        }
    }
    
    /**
     * Render customer dashboard for BUSINESS_MANAGER role
     */
    private function renderCustomerDashboard() {
        try {
            $userId = $_SESSION['user_id'] ?? null;
            
            if (!$userId) {
                header('Location: ' . BASE_URL . '/login');
                exit;
            }
            
            // Get user data
            $userService = \App\Core\DependencyFactory::getUserService();
            $user = $userService->findByUserId($userId);
            
            if (!$user) {
                $this->toastNotificationService->setFlash('error', 'Kullanıcı bilgileri bulunamadı');
                header('Location: ' . BASE_URL . '/login');
                exit;
            }
            
            // Get customer data from customers table (email ile eşleştir)
            $customer = null;
            $userEmail = $user['name'] ?? ''; // Email name field'ında saklanıyor
            
            if (!empty($userEmail)) {
                try {
                    $customerRepo = \App\Core\DependencyFactory::getCustomerRepository();
                    $customer = $customerRepo->findByEmail($userEmail);
                } catch (\Exception $e) {
                    // Customer bulunamadı - devam et
                    if (class_exists('\App\Core\Logger')) {
                        \App\Core\Logger::warning("Customer not found for email: " . $userEmail, [
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }
            
            // Paket listesi
            $packages = [];
            try {
                $packageService = \App\Core\DependencyFactory::getPackageService();
                $packages = $packageService->getActivePackages();
            } catch (\Exception $e) {
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::error("DashboardController::renderCustomerDashboard - PackageService error", [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
                $packages = [];
            }
            
            // Aktif abonelik
            $subscription = null;
            if ($customer && !empty($customer['customer_id'])) {
                try {
                    $subscriptionService = \App\Core\DependencyFactory::getSubscriptionService();
                    $subscription = $subscriptionService->getCustomerSubscription($customer['customer_id']);
                } catch (\Exception $e) {
                    if (class_exists('\App\Core\Logger')) {
                        \App\Core\Logger::error("DashboardController::renderCustomerDashboard - SubscriptionService error", [
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                            'customer_id' => $customer['customer_id'] ?? null
                        ]);
                    }
                    $subscription = null;
                }
            }
            
            // ✅ PAKET KONTROLÜ: Aktif abonelik yoksa uyarı göster (yönlendirme yapma, dashboard'da göster)
            // Paket kontrolü view'da yapılacak, burada sadece flag gönderiyoruz
            
            // Usage statistics (if has subscription)
            $stats = [
                'active_users' => 0,
                'total_orders' => 0,
                'monthly_orders' => 0,
                'monthly_revenue' => 0
            ];
            
            if ($subscription && $customer) {
                try {
                    // Get user count for this customer
                    $userService = \App\Core\DependencyFactory::getUserService();
                    $stats['active_users'] = $userService->countUsersByCustomerId($customer['customer_id']);
                    
                    // Get order statistics
                    $orderService = \App\Core\DependencyFactory::getOrderService();
                    $stats['total_orders'] = $orderService->countOrdersByCustomerId($customer['customer_id']);
                    
                    // Monthly orders and revenue
                    $currentMonth = date('Y-m');
                    $monthlyOrders = $orderService->getOrdersByCustomerAndMonth($customer['customer_id'], $currentMonth);
                    $stats['monthly_orders'] = count($monthlyOrders);
                    $stats['monthly_revenue'] = array_sum(array_column($monthlyOrders, 'total_amount'));
                } catch (\Exception $e) {
                    // Silently fail - stats will remain 0
                    if (class_exists('\App\Core\Logger')) {
                        \App\Core\Logger::warning("Could not load customer stats", [
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }
            
            // Welcome flag kontrolü
            $showPackageSelection = isset($_GET['welcome']) && ($_SESSION['show_package_selection'] ?? false);
            if ($showPackageSelection) {
                unset($_SESSION['show_package_selection']);
            }
            
            // Havale ödemesi onay bekliyor mu? Varsa paket satın almayı gösterme
            $pendingBankTransfer = null;
            if ($customer && !empty($customer['customer_id']) && !$subscription) {
                try {
                    $bankTransferService = \App\Core\DependencyFactory::getBankTransferService();
                    $pendingList = $bankTransferService->getTransfersByCustomerId($customer['customer_id'], 'pending');
                    $pendingBankTransfer = !empty($pendingList) ? $pendingList[0] : null;
                    if ($pendingBankTransfer) {
                        $showPackageSelection = false;
                    }
                } catch (\Exception $e) {
                    // ignore
                }
            }
            
            $data = [
                'user' => $user,
                'customer' => $customer,
                'packages' => $packages,
                'subscription' => $subscription,
                'stats' => $stats,
                'showPackageSelection' => $showPackageSelection,
                'pendingBankTransfer' => $pendingBankTransfer,
                'isCustomerDashboard' => true
            ];
            
            $this->view('admin/customer_dashboard', $data);
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error("DashboardController::renderCustomerDashboard - Fatal error", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]);
            }
            
            // Show error page or redirect based on role
            $role = $_SESSION['role'] ?? '';
            $dashboardUrl = ($role === 'SUPER_ADMIN' || $role === 'QODMIN') ? '/qodmin/dashboard' : '/business/dashboard';
            $this->toastNotificationService->setFlash('error', 'Dashboard yüklenirken bir hata oluştu: ' . $e->getMessage());
            header('Location: ' . BASE_URL . $dashboardUrl);
            exit;
        }
    }
}
