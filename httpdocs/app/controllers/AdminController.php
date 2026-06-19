<?php
/**
 * ============================================================================
 * REFACTORING ROADMAP (Q4 2026)
 * ============================================================================
 *
 * This controller is a god-class with 58 methods. Planned split:
 *
 * ┌─────────────────────────────────────────────────────────────────────────┐
 * │ App\Controllers\Admin\DashboardController (new) │
 * │ - dashboard() │
 * │ - getDashboardData() │
 * │ - getAIInsights()  │
 * └─────────────────────────────────────────────────────────────────────────┘
 *
 * ┌─────────────────────────────────────────────────────────────────────────┐
 * │ App\Controllers\Admin\ReportsController (new) │
 * │ - reports(), getReportsData() │
 * │ - exportOrders(), exportReport() │
 * │ - analytics(), getAnalyticsData(), getDailyRevenueForChart() │
 * └─────────────────────────────────────────────────────────────────────────┘
 *
 * ┌─────────────────────────────────────────────────────────────────────────┐
 * │ App\Controllers\Admin\SettingsController (new) │
 * │ - settings(), features(), toggleFeature() │
 * │ - paymentGateways(), updatePaymentGateway() │
 * │ - posDevices(), updatePOSDevice(), testPOSDevice(), addPOSDevice() │
 * │ - uploadLogo(), uploadFavicon(), resetSystem() │
 * │ - testEmail(), getEmailStatus() │
 * │ - enable2FA(), disable2FA(), send2FACode(), verify2FA() │
 * │ - createDatabaseBackup(), backupLogoAndFavicon() │
 * │ - deleteUploadedImages(), deleteDirectory() │
 * └─────────────────────────────────────────────────────────────────────────┘
 *
 * ┌─────────────────────────────────────────────────────────────────────────┐
 * │ App\Controllers\Admin\UsersController (new) │
 * │ - shifts(), createShift(), updateShift(), deleteShift() │
 * │ - saveStaffSchedule(), createShiftSchedule() │
 * │ - createWeeklyShiftSchedule(), syncDynamicPermissions() │
 * │ - createShiftTables(), generateShiftsFromSchedule()  │
 * │ - addLeave(), getLeave(), updateLeave(), deleteLeave() │
 * │ - getMedicalReport(), addMedicalReport(), updateMedicalReport() │
 * │ - deleteMedicalReport(), downloadMedicalReport() │
 * │ - addPrinterPermissions(), tableHistory() │
 * └─────────────────────────────────────────────────────────────────────────┘
 *
 * Refactor will use **method aliasing** for backward compatibility:
 * AdminController::dashboard() will forward to DashboardController::dashboard()
 *
 * See PRODUCTION_READINESS_REPORT.md → AdminController split (8h, P0)
 * ============================================================================
 */

namespace App\Controllers;

require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../core/DependencyFactory.php';
require_once __DIR__ . '/../core/Traits/HandlesFileUpload.php';
use App\Core\Helpers\ConstantsHelper;
use App\Core\Traits\HandlesFileUpload;

class AdminController extends \App\Core\Controller {
    use HandlesFileUpload;

    protected $userService;
    protected $orderService;
    protected $tableService;
    protected $zoneService;
    protected $settingsService;
    protected $financeService;
    // AI servis artık merkezi yapıda, static metodlar kullanılıyor
    protected $roleService;
    protected $permissionModel;
    protected $leaveTypeService;
    protected $leaveService;
    protected $medicalReportService;
    protected $personnelService;
    protected $reportsService;
    protected $constantsService;
    protected $staffScheduleService;
    protected $guestStaffService;
    protected $javascriptErrorLogService;
    protected $unifiedErrorLogService;
    
    public function __construct() {
        parent::__construct(); // Initialize auth and other services
        $this->userService = \App\Core\DependencyFactory::getUserService();
        $this->orderService = \App\Core\DependencyFactory::getOrderService();
        $this->tableService = \App\Core\DependencyFactory::getTableService();
        $this->zoneService = \App\Core\DependencyFactory::getZoneService();
        $this->settingsService = \App\Core\DependencyFactory::getSystemSettingsService();
        $this->notificationService = \App\Core\DependencyFactory::getNotificationService();
        $this->financeService = \App\Core\DependencyFactory::getFinanceService();
        // AI servis artık merkezi yapıda
        $this->roleService = \App\Core\DependencyFactory::getRoleService();
        $this->permissionModel = new \App\Models\SystemPermission(\App\Core\DependencyFactory::getDatabase());
        $this->leaveTypeService = \App\Core\DependencyFactory::getLeaveTypeService();
        $this->leaveService = \App\Core\DependencyFactory::getLeaveService();
        $this->medicalReportService = \App\Core\DependencyFactory::getMedicalReportService();
        $this->personnelService = \App\Core\DependencyFactory::getPersonnelService();
        $this->reportsService = \App\Core\DependencyFactory::getReportsService();
        $this->constantsService = \App\Core\DependencyFactory::getConstantsService();
        $this->staffScheduleService = \App\Core\DependencyFactory::getStaffScheduleService();
        $this->guestStaffService = \App\Core\DependencyFactory::getGuestStaffService();
        $this->javascriptErrorLogService = \App\Core\DependencyFactory::getJavaScriptErrorLogService();
        $this->unifiedErrorLogService = \App\Core\DependencyFactory::getUnifiedErrorLogService();
    }
    
    public function dashboard() {
        // CRITICAL: Ensure tenant context is set for security
        $this->ensureTenantContext();
        
        // Admin dashboard sadece MANAGER rolüne açık
        // Permission kontrolü: dashboard.analytics veya dashboard.view (fallback)
        if (!$this->hasPermission('dashboard.analytics') && !$this->hasPermission('dashboard.view')) {
            $this->requirePermission('dashboard.view'); // Bu zaten unauthorized'a yönlendirecek
            return;
        }
        
        // Role kontrolü: MANAGER, BUSINESS_MANAGER, ADMIN ve ADMINISTRATOR rollerine izin ver
        // hasRole metodu artık BUSINESS_MANAGER'ı MANAGER olarak kabul ediyor
        if (!$this->hasRole('MANAGER') && !$this->hasRole('ADMIN') && !$this->hasRole('ADMINISTRATOR')) {
            header('Location: ' . BASE_URL . '/unauthorized');
            exit;
        }
        
        $allTables = $this->tableService->getAllTables();
        $allTables = $allTables ?: [];
        
        // Use business date range (accounts for overnight working hours)
        $settingsService = \App\Core\DependencyFactory::getSystemSettingsService();
        $businessRange = $settingsService->getBusinessDateRange();
        $today = $businessRange['date'];
        $dailyRevenue = $this->orderService->getDailyRevenueByDatetimeRange($businessRange['start_datetime'], $businessRange['end_datetime']);
        $pendingOrders = $this->orderService->getOrdersByStatus('PENDING');
        // PERFORMANCE OPTIMIZATION: Get only recent orders (last 5) instead of all orders
        // getAllOrders() without limit can load thousands of orders - very slow!
        $recentOrders = [];
        try {
            if (method_exists($this->orderService, 'getRecentOrders')) {
                $recentOrders = $this->orderService->getRecentOrders(5);
            } else {
                // Fallback: Use business day orders only
                $todayOrders = $this->orderService->getOrdersByDatetimeRange($businessRange['start_datetime'], $businessRange['end_datetime']);
                $recentOrders = is_array($todayOrders) ? array_slice($todayOrders, 0, 5) : [];
            }
            $recentOrders = is_array($recentOrders) ? $recentOrders : [];
        } catch (\Exception $e) {
            $recentOrders = [];
        }
        $topSellingItems = $this->orderService->getTopSellingItems(5);
        $activeTables = $this->tableService->getActiveTables();
        
        // Calculate Row 2 KPI values - use datetime range for business hours (matches ciro reset)
        $todayOrders = $this->orderService->getOrdersByDatetimeRange($businessRange['start_datetime'], $businessRange['end_datetime']);
        $todayOrders = is_array($todayOrders) ? $todayOrders : [];
        $totalOrdersToday = count($todayOrders);
        
        $avgOrderValue = $totalOrdersToday > 0 ? ($dailyRevenue / $totalOrdersToday) : 0;
        
        // Unique customers (unique table_ids) today
        $uniqueTablesToday = array_unique(array_column($todayOrders, 'table_id'));
        $uniqueCustomersToday = count(array_filter($uniqueTablesToday, function($tableId) {
            return !empty($tableId);
        }));
        
        // Today's served orders count
        $servedStatus = ConstantsHelper::getOrderStatus('SERVED');
        $todayServedOrders = array_filter($todayOrders, function($order) use ($servedStatus) {
            return ($order['status'] ?? '') === $servedStatus;
        });
        $todayServedCount = count($todayServedOrders);
        
        // Calculate Row 1 KPI values (missing variables)
        $occupancyPercent = count($allTables) > 0 ? round(($this->tableService->getOccupiedCount() / count($allTables)) * 100) : 0;
        $pendingOrdersCount = is_array($pendingOrders) ? count($pendingOrders) : 0;
        
        // Calculate net profit using actual expenses (revenue - expenses)
        $todayExpenses = $this->financeService->getTotalExpensesByDateRange($today, $today);
        $estimatedProfit = $dailyRevenue - $todayExpenses;
        
        $zones = $this->tableService->getAllZones();
        
        // Get recent notifications from service
        $recentNotifications = $this->notificationService->getAll(10);
        $recentNotifications = is_array($recentNotifications) ? $recentNotifications : [];
        
        $data = [
            'daily_revenue' => $dailyRevenue,
            'occupancy_percent' => $occupancyPercent,
            'pending_orders_count' => $pendingOrdersCount,
            'estimated_profit' => $estimatedProfit,
            'active_tables_count' => $this->tableService->getOccupiedCount(),
            'unread_notifications_count' => $this->notificationService->getUnreadCount() ?: 0,
            'recent_orders' => is_array($recentOrders) ? array_slice($recentOrders, 0, 5) : [],
            'top_selling_items' => is_array($topSellingItems) ? $topSellingItems : [],
            'active_tables' => is_array($activeTables) ? $activeTables : [],
            'tables' => $allTables,
            'zones' => $zones,
            'notifications' => $recentNotifications,
            // Row 2 KPI values
            'total_orders_today' => $totalOrdersToday,
            'avg_order_value' => $avgOrderValue,
            'unique_customers_today' => $uniqueCustomersToday,
            'today_served_count' => $todayServedCount
        ];
        
        $this->view('admin/dashboard', $data);
    }
    
    /**
     * Get dashboard data for real-time updates (API endpoint)
     * Returns all dashboard metrics in a single response
     */
    public function getDashboardData() {
        // Admin dashboard API sadece MANAGER rolüne açık
        if (!$this->hasPermission('dashboard.analytics') && !$this->hasPermission('dashboard.view')) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 401);
            return;
        }
        
        // Role kontrolü: MANAGER, BUSINESS_MANAGER, ADMIN ve ADMINISTRATOR rollerine izin ver
        // hasRole metodu artık BUSINESS_MANAGER'ı MANAGER olarak kabul ediyor
        if (!$this->hasRole('MANAGER') && !$this->hasRole('ADMIN') && !$this->hasRole('ADMINISTRATOR')) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 401);
            return;
        }
        
        if (!$this->hasPermission('dashboard.view')) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 401);
            return;
        }
        
        try {
            $settingsService = \App\Core\DependencyFactory::getSystemSettingsService();
            $businessRange = $settingsService->getBusinessDateRange();
            $today = $businessRange['date'];
            $yesterday = date('Y-m-d', strtotime('-1 day', strtotime($today)));
            
            $allTables = $this->tableService->getAllTables();
            $allTables = $allTables ?: [];
            
            // Today's metrics - use datetime range for overnight support (matches ciro reset)
            $dailyRevenue = $this->orderService->getDailyRevenueByDatetimeRange($businessRange['start_datetime'], $businessRange['end_datetime']);
            $yesterdayRange = $settingsService->getBusinessDateRangeForDate($yesterday);
            $yesterdayRevenue = $this->orderService->getDailyRevenueByDatetimeRange($yesterdayRange['start_datetime'], $yesterdayRange['end_datetime']);
            $revenueChange = $yesterdayRevenue > 0 ? round((($dailyRevenue - $yesterdayRevenue) / $yesterdayRevenue) * 100, 1) : 0;
            
            $todayOrders = $this->orderService->getOrdersByDatetimeRange($businessRange['start_datetime'], $businessRange['end_datetime']);
            $todayOrders = is_array($todayOrders) ? $todayOrders : [];
            $totalOrdersToday = count($todayOrders);
            
            $yesterdayOrders = $this->orderService->getOrdersByDatetimeRange($yesterdayRange['start_datetime'], $yesterdayRange['end_datetime']);
            $yesterdayOrders = is_array($yesterdayOrders) ? $yesterdayOrders : [];
            $totalOrdersYesterday = count($yesterdayOrders);
            $ordersChange = $totalOrdersYesterday > 0 ? round((($totalOrdersToday - $totalOrdersYesterday) / $totalOrdersYesterday) * 100, 1) : 0;
            
            $avgOrderValue = $totalOrdersToday > 0 ? ($dailyRevenue / $totalOrdersToday) : 0;
            $yesterdayAvgOrderValue = $totalOrdersYesterday > 0 ? ($yesterdayRevenue / $totalOrdersYesterday) : 0;
            $avgOrderChange = $yesterdayAvgOrderValue > 0 ? round((($avgOrderValue - $yesterdayAvgOrderValue) / $yesterdayAvgOrderValue) * 100, 1) : 0;
            
            // Order status breakdown
            $pendingStatus = ConstantsHelper::getOrderStatus('PENDING');
            $preparingStatus = ConstantsHelper::getOrderStatus('PREPARING');
            $readyStatus = ConstantsHelper::getOrderStatus('READY');
            $servedStatus = ConstantsHelper::getOrderStatus('SERVED');
            
            $pendingOrders = $this->orderService->getOrdersByStatus($pendingStatus);
            $preparingOrders = $this->orderService->getOrdersByStatus($preparingStatus);
            $readyOrders = $this->orderService->getOrdersByStatus($readyStatus);
            $servedOrders = $this->orderService->getOrdersByStatus($servedStatus);
            
            $pendingCount = is_array($pendingOrders) ? count($pendingOrders) : 0;
            $preparingCount = is_array($preparingOrders) ? count($preparingOrders) : 0;
            $readyCount = is_array($readyOrders) ? count($readyOrders) : 0;
            $servedCount = is_array($servedOrders) ? count($servedOrders) : 0;
            
            // Today's served orders count
            $todayServedOrders = array_filter($todayOrders, function($order) {
                $servedStatus = ConstantsHelper::getOrderStatus('SERVED');
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
            $todayExpenses = $this->financeService->getTotalExpensesByDateRange($today, $today);
            $estimatedProfit = $dailyRevenue - $todayExpenses;
            
            // Get recent notifications
            $recentNotifications = $this->notificationService->getRecent(10);
            $recentNotifications = is_array($recentNotifications) ? $recentNotifications : [];
            
            // Get recent orders (last 10)
            // PERFORMANCE OPTIMIZATION: Get only recent orders (last 10) instead of all orders
            $recentOrders = [];
            try {
                if (method_exists($this->orderService, 'getRecentOrders')) {
                    $recentOrders = $this->orderService->getRecentOrders(10);
                } else {
                    // Fallback: Use business day orders only
                    $todayOrders = $this->orderService->getOrdersByDatetimeRange($businessRange['start_datetime'], $businessRange['end_datetime']);
                    $recentOrders = is_array($todayOrders) ? array_slice($todayOrders, 0, 10) : [];
                }
                $recentOrders = is_array($recentOrders) ? $recentOrders : [];
            } catch (\Exception $e) {
                $recentOrders = [];
            }
            
            // Get top selling items (detailed)
            $topSellingItems = $this->orderService->getTopSellingItems(10);
            $topSellingItems = is_array($topSellingItems) ? $topSellingItems : [];
            
            // Get hourly sales for today
            $hourlySales = $this->orderService->getHourlySales($today, $today);
            $hourlySales = is_array($hourlySales) ? $hourlySales : [];
            
            // Get revenue by category for today
            $revenueByCategory = $this->orderService->getRevenueByCategory($today, $today);
            $revenueByCategory = is_array($revenueByCategory) ? $revenueByCategory : [];
            
            // Group tables by zone for occupancy calculation
            $zonesData = [];
            if (!empty($allTables)) {
                foreach ($allTables as $table) {
                    $zone = $table['zone'] ?? 'Default';
                    if (!isset($zonesData[$zone])) {
                        $zonesData[$zone] = ['total' => 0, 'occupied' => 0];
                    }
                    $zonesData[$zone]['total']++;
                    if (($table['status'] ?? 'FREE') !== 'FREE') {
                        $zonesData[$zone]['occupied']++;
                    }
                }
            }
            
            // Format zones data with percentages
            $zonesFormatted = [];
            foreach ($zonesData as $zone => $zoneData) {
                $zonePercent = $zoneData['total'] > 0 ? round(($zoneData['occupied'] / $zoneData['total']) * 100) : 0;
                $zonesFormatted[$zone] = [
                    'total' => $zoneData['total'],
                    'occupied' => $zoneData['occupied'],
                    'percent' => $zonePercent
                ];
            }
            
            // Prepare hourly sales chart data
            $hourlyChartData = [];
            for ($hour = 0; $hour < 24; $hour++) {
                $hourData = array_filter($hourlySales, function($item) use ($hour) {
                    return (int)($item['hour'] ?? -1) === $hour;
                });
                $hourData = reset($hourData);
                $hourlyChartData[] = [
                    'hour' => $hour,
                    'order_count' => (int)($hourData['order_count'] ?? 0),
                    'revenue' => floatval($hourData['revenue'] ?? 0)
                ];
            }
            
            // Order status distribution for pie chart - dinamik olarak ConstantsService'den al
            $orderStatusCodes = $this->constantsService->getOrderStatusCodes();
            $orderStatusDistribution = [];
            foreach ($orderStatusCodes as $statusCode) {
                $orderStatusDistribution[$statusCode] = 0;
            }
            // Mevcut sayıları ekle
            $pendingStatus = ConstantsHelper::getOrderStatus('PENDING');
            $preparingStatus = ConstantsHelper::getOrderStatus('PREPARING');
            $readyStatus = ConstantsHelper::getOrderStatus('READY');
            $servedStatus = ConstantsHelper::getOrderStatus('SERVED');
            
            $orderStatusDistribution[$pendingStatus] = $pendingCount;
            $orderStatusDistribution[$preparingStatus] = $preparingCount;
            $orderStatusDistribution[$readyStatus] = $readyCount;
            $orderStatusDistribution[$servedStatus] = $servedCount;
            // Diğer statuslar için dinamik sayım
            foreach ($todayOrders as $order) {
                $status = $order['status'] ?? '';
                if (!empty($status) && isset($orderStatusDistribution[$status])) {
                    // PENDING, PREPARING, READY, SERVED zaten sayıldı, sadece diğerleri için
                    $validStatuses = [
                        ConstantsHelper::getOrderStatus('PENDING'),
                        ConstantsHelper::getOrderStatus('PREPARING'),
                        ConstantsHelper::getOrderStatus('READY'),
                        ConstantsHelper::getOrderStatus('SERVED')
                    ];
                    if (!in_array($status, $validStatuses)) {
                        $orderStatusDistribution[$status]++;
                    }
                }
            }
            
            // Order source distribution
            $orderSourceDistribution = [];
            foreach ($todayOrders as $order) {
                $source = $order['order_source'] ?? 'UNKNOWN';
                if (!isset($orderSourceDistribution[$source])) {
                    $orderSourceDistribution[$source] = 0;
                }
                $orderSourceDistribution[$source]++;
            }
            
            // Weekly trend (last 7 days)
            $weeklyTrend = [];
            for ($i = 6; $i >= 0; $i--) {
                $date = date('Y-m-d', strtotime("-$i days"));
                $dayRevenue = $this->orderService->getDailyRevenue($date);
                $dayOrders = $this->orderService->getOrdersByDateRange($date, $date);
                $dayOrders = is_array($dayOrders) ? $dayOrders : [];
                $weeklyTrend[] = [
                    'date' => $date,
                    'day_name' => date('D', strtotime($date)),
                    'revenue' => floatval($dayRevenue),
                    'orders_count' => count($dayOrders)
                ];
            }
            
            // Most active tables (by order count today)
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
            
            // Table status breakdown - dinamik olarak ConstantsService'den al
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
            
            $data = [
                'success' => true,
                'data' => [
                    'kpi' => [
                        'daily_revenue' => floatval($dailyRevenue),
                        'yesterday_revenue' => floatval($yesterdayRevenue),
                        'revenue_change' => $revenueChange,
                        'occupancy_percent' => $occupancyPercent,
                        'pending_orders_count' => $pendingCount,
                        'preparing_orders_count' => $preparingCount,
                        'ready_orders_count' => $readyCount,
                        'served_orders_count' => $servedCount,
                        'total_orders_today' => $totalOrdersToday,
                        'total_orders_yesterday' => $totalOrdersYesterday,
                        'orders_change' => $ordersChange,
                        'avg_order_value' => floatval($avgOrderValue),
                        'avg_order_change' => $avgOrderChange,
                        'unique_customers_today' => $uniqueCustomersToday,
                        'estimated_profit' => floatval($estimatedProfit),
                        'today_served_count' => $todayServedCount
                    ],
                    'notifications' => $recentNotifications,
                    'recent_orders' => $recentOrders,
                    'top_selling_items' => $topSellingItems,
                    'hourly_sales' => $hourlyChartData,
                    'revenue_by_category' => $revenueByCategory,
                    'order_status_distribution' => $orderStatusDistribution,
                    'order_source_distribution' => $orderSourceDistribution,
                    'weekly_trend' => $weeklyTrend,
                    'most_active_tables' => $mostActiveTables,
                    'table_status_breakdown' => $tableStatusBreakdown,
                    'zones' => $zonesFormatted,
                    'active_tables_count' => $activeTablesCount,
                    'total_tables' => $totalTables,
                    'unread_notifications_count' => $this->notificationService->getUnreadCount() ?: 0
                ]
            ];
            
            $this->apiResponse($data);
        } catch (\Exception $e) {
            \App\Core\Logger::error("Error in getDashboardData: " . $e->getMessage());
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.dashboard_load_failed', [], 500);
        }
    }
    
    public function tables() {
        // CRITICAL: Ensure tenant context is set for security
        $this->ensureTenantContext();
        $this->requirePermission('tables.view');
        
        $tables = $this->tableService->getAllTables();
        $zones = $this->tableService->getAllZones();
        
        $data = [
            'tables' => $tables ?: [],
            'zones' => $zones ?: []
        ];
        
        $this->view('admin/tables', $data);
    }
    
    public function zones() {
        // CRITICAL: Ensure tenant context is set for security
        $this->ensureTenantContext();
        $this->requirePermission('tables.view');
        
        $zones = $this->zoneService->getZonesWithTableCount();
        
        $data = [
            'zones' => $zones ?: []
        ];
        
        $this->view('admin/zones', $data);
    }
    
    public function analytics() {
        // CRITICAL: Ensure tenant context is set for security
        $this->ensureTenantContext();
        $this->requirePermission('dashboard.analytics');

        $requestData = \App\Core\RequestParser::getRequestData();
        $startDate = $requestData['start_date'] ?? date('Y-m-01');
        $endDate = $requestData['end_date'] ?? date('Y-m-d');

        $startDateTime = \DateTime::createFromFormat('Y-m-d', $startDate);
        $endDateTime = \DateTime::createFromFormat('Y-m-d', $endDate);

        if (!$startDateTime || !$endDateTime) {
            $startDate = date('Y-m-01');
            $endDate = date('Y-m-d');
        } else {
            if ($endDateTime < $startDateTime) {
                $endDate = $startDate;
            }
        }
        
        $orders = $this->orderService->getOrdersByDateRange($startDate, $endDate);
        $orders = is_array($orders) ? $orders : [];
        
        // Get recent orders (last 20, sorted by date descending)
        $recentOrders = $orders;
        usort($recentOrders, function($a, $b) {
            $dateA = strtotime($a['created_at'] ?? '1970-01-01');
            $dateB = strtotime($b['created_at'] ?? '1970-01-01');
            return $dateB - $dateA; // Descending order
        });
        $recentOrders = array_slice($recentOrders, 0, 20);
        
        $data = [
            'daily_revenue' => $this->getDailyRevenueForChart($startDate, $endDate),
            'recent_orders' => $recentOrders,
            'top_selling_items' => $this->orderService->getTopSellingItems(10),
            'settings' => $this->settingsService->getSettings(),
            'total_revenue' => $this->orderService->calculateTotalRevenue($startDate, $endDate),
            'total_orders' => $orders,
            'avg_order_value' => $this->orderService->calculateAvgOrderValue($startDate, $endDate),
            'revenue_by_category' => $this->orderService->getRevenueByCategory($startDate, $endDate),
            'hourly_sales' => $this->orderService->getHourlySales($startDate, $endDate),
            'date_range' => [
                'start' => $startDate,
                'end' => $endDate
            ]
        ];

        $this->view('admin/analytics', $data);
    }


    public function getAnalyticsData() {
        if (!$this->hasPermission('dashboard.analytics')) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 401);
            return;
        }

        $requestData = \App\Core\RequestParser::getRequestData();
        $startDate = $requestData['start_date'] ?? date('Y-m-01');
        $endDate = $requestData['end_date'] ?? date('Y-m-d');

        $startDateTime = \DateTime::createFromFormat('Y-m-d', $startDate);
        $endDateTime = \DateTime::createFromFormat('Y-m-d', $endDate);

        if (!$startDateTime || !$endDateTime) {
            $startDate = date('Y-m-01');
            $endDate = date('Y-m-d');
        } else {
            if ($endDateTime < $startDateTime) {
                $endDate = $startDate;
            }
        }

        $orders = $this->orderService->getOrdersByDateRange($startDate, $endDate);
        $orders = is_array($orders) ? $orders : [];
        
        // Get recent orders (last 20, sorted by date descending)
        $recentOrders = $orders;
        usort($recentOrders, function($a, $b) {
            $dateA = strtotime($a['created_at'] ?? '1970-01-01');
            $dateB = strtotime($b['created_at'] ?? '1970-01-01');
            return $dateB - $dateA; // Descending order
        });
        $recentOrders = array_slice($recentOrders, 0, 20);
        
        $data = [
            'daily_revenue' => $this->getDailyRevenueForChart($startDate, $endDate),
            'revenue_by_category' => $this->orderService->getRevenueByCategory($startDate, $endDate),
            'hourly_sales' => $this->orderService->getHourlySales($startDate, $endDate),
            'top_selling_items' => $this->orderService->getTopSellingItems(10),
            'total_revenue' => $this->orderService->calculateTotalRevenue($startDate, $endDate),
            'total_orders' => count($orders),
            'avg_order_value' => $this->orderService->calculateAvgOrderValue($startDate, $endDate),
            'recent_orders' => $recentOrders,
            'completed_orders_count' => count(array_filter($orders, function($order) {
                $servedStatus = ConstantsHelper::getOrderStatus('SERVED');
                return ($order['status'] ?? '') === $servedStatus;
            }))
        ];

        $this->apiResponse($data);
    }

    private function getDailyRevenueForChart($startDate, $endDate) {
        // Use ReportsService instead of direct database access
        $reportsService = \App\Core\DependencyFactory::getReportsService();
        return $reportsService->getDailyRevenueChart($startDate, $endDate);
    }

    public function reports() {
        // CRITICAL: Ensure tenant context is set for security
        $this->ensureTenantContext();
        $this->requirePermission('reports.view');

        // Get date range from query params or use default
        $requestData = \App\Core\RequestParser::getRequestData();
        $period = $requestData['period'] ?? 'this_month';
        $startDate = $requestData['start_date'] ?? null;
        $endDate = $requestData['end_date'] ?? null;
        $tableId = $requestData['table_id'] ?? null;

        // If custom dates provided, use them; otherwise use period
        if ($startDate && $endDate) {
            $startDateTime = \DateTime::createFromFormat('Y-m-d', $startDate);
            $endDateTime = \DateTime::createFromFormat('Y-m-d', $endDate);

            if (!$startDateTime || !$endDateTime) {
                $dateRange = $this->reportsService->getTimeRangeData('this_month');
                $startDate = $dateRange['start'];
                $endDate = $dateRange['end'];
            } else {
                if ($endDateTime < $startDateTime) {
                    $endDate = $startDate;
                }
            }
        } else {
            $dateRange = $this->reportsService->getTimeRangeData($period);
            $startDate = $dateRange['start'];
            $endDate = $dateRange['end'];
        }

        // Get all tables for dropdown
        $allTables = $this->tableService->getAllTables();

        // Get comprehensive report data
        $reportData = $this->reportsService->getComprehensiveReport($startDate, $endDate, $tableId);

        // Get expense report separately
        $expenses = $this->financeService->getExpensesByDateRange($startDate, $endDate);
        $expenseReport = [
            'total_expenses' => $this->financeService->getTotalExpensesByDateRange($startDate, $endDate),
            'expense_count' => count($expenses)
        ];

        $data = array_merge($reportData, [
            'expense_report' => $expenseReport,
            'date_range' => [
                'start' => $startDate,
                'end' => $endDate
            ],
            'period' => $period,
            'selected_table_id' => $tableId,
            'tables' => $allTables
        ]);

        $this->view('admin/reports', $data);
    }

    /**
     * API endpoint for getting reports data (AJAX)
     */
    public function getReportsData() {
        if (!$this->hasPermission('reports.view')) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 401);
            return;
        }

        try {
            $requestData = \App\Core\RequestParser::getRequestData();
            $period = $requestData['period'] ?? 'this_month';
            $startDate = $requestData['start_date'] ?? null;
            $endDate = $requestData['end_date'] ?? null;
            $tableId = $requestData['table_id'] ?? null;

            // If custom dates provided, use them; otherwise use period
            if ($startDate && $endDate) {
                $startDateTime = \DateTime::createFromFormat('Y-m-d', $startDate);
                $endDateTime = \DateTime::createFromFormat('Y-m-d', $endDate);

                if (!$startDateTime || !$endDateTime) {
                    $dateRange = $this->reportsService->getTimeRangeData('this_month');
                    $startDate = $dateRange['start'];
                    $endDate = $dateRange['end'];
                } else {
                    if ($endDateTime < $startDateTime) {
                        $endDate = $startDate;
                    }
                }
            } else {
                $dateRange = $this->reportsService->getTimeRangeData($period);
                $startDate = $dateRange['start'];
                $endDate = $dateRange['end'];
            }

            // Get comprehensive report data
            $reportData = $this->reportsService->getComprehensiveReport($startDate, $endDate, $tableId);

            // Get expense report separately
            $expenseReport = [
                'total_expenses' => $this->financeService->getTotalExpensesByDateRange($startDate, $endDate),
                'expense_count' => count($this->financeService->getExpensesByDateRange($startDate, $endDate))
            ];

            $this->apiResponse([
                'success' => true,
                'data' => array_merge($reportData, [
                    'expense_report' => $expenseReport,
                    'date_range' => [
                        'start' => $startDate,
                        'end' => $endDate
                    ],
                    'period' => $period
                ])
            ]);
        } catch (\Exception $e) {
            \App\Core\Logger::error("Error in getReportsData: " . $e->getMessage());
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.reports_load_failed', [], 500);
        }
    }
    

    public function exportOrders() {
        // Check if user has permission
        if (!$this->checkPermissionOrFail('orders.view')) {
            return;
        }
        
        $requestData = \App\Core\RequestParser::getRequestData();
        $format = $requestData['format'] ?? 'csv';
        $format = in_array($format, ['csv', 'excel', 'pdf']) ? $format : 'csv';
        
        // Get filter parameters
        $status = $requestData['status'] ?? 'all';
        $dateFilter = $requestData['date_filter'] ?? 'all';
        $startDate = $requestData['start_date'] ?? null;
        $endDate = $requestData['end_date'] ?? null;
        $searchQuery = $requestData['search'] ?? '';
        
        // PERFORMANCE OPTIMIZATION: Calculate date range first, then use getOrdersByDateRange()
        // This prevents loading all orders into memory
        $now = new \DateTime();
        $finalStartDate = null;
        $finalEndDate = null;
        
        // Apply date filter preset if provided
        if ($dateFilter !== 'all') {
            if ($dateFilter === 'today') {
                $finalStartDate = $now->format('Y-m-d');
                $finalEndDate = $now->format('Y-m-d');
            } elseif ($dateFilter === 'week') {
                $dayOfWeek = (int)$now->format('w');
                $weekStart = clone $now;
                $weekStart->modify('-' . $dayOfWeek . ' days');
                $finalStartDate = $weekStart->format('Y-m-d');
                $finalEndDate = $now->format('Y-m-d');
            } elseif ($dateFilter === 'month') {
                $finalStartDate = $now->format('Y-m-01');
                $finalEndDate = $now->format('Y-m-t');
            }
        }
        
        // Use provided dates if available (override preset)
        if ($startDate && $endDate) {
            $finalStartDate = $startDate;
            $finalEndDate = $endDate;
        }
        
        // PERFORMANCE: Default to last 30 days if no filter provided
        if (!$finalStartDate || !$finalEndDate) {
            $finalEndDate = date('Y-m-d');
            $finalStartDate = date('Y-m-d', strtotime('-30 days'));
        }
        
        // Get orders by date range (much more efficient than getAllOrders)
        $orders = $this->orderService->getOrdersByDateRange($finalStartDate, $finalEndDate);
        $orders = is_array($orders) ? $orders : [];
        
        // Filter by status if needed
        if ($status !== 'all') {
            $orders = array_filter($orders, function($order) use ($status) {
                return ($order['status'] ?? '') === $status;
            });
            $orders = array_values($orders); // Re-index array
        }
        
        // Apply search filter
        if (!empty($searchQuery)) {
            $searchLower = strtolower($searchQuery);
            $orders = array_filter($orders, function($order) use ($searchLower) {
                $orderId = strtolower($order['order_id'] ?? '');
                $tableName = strtolower($order['table_name'] ?? '');
                $customerName = strtolower($order['created_by'] ?? $order['customer_name'] ?? '');
                
                return strpos($orderId, $searchLower) !== false ||
                       strpos($tableName, $searchLower) !== false ||
                       strpos($customerName, $searchLower) !== false;
            });
            $orders = array_values($orders);
        }
        
        // Prepare order data for export
        $exportData = [];
        foreach ($orders as $order) {
            $statusMap = [
                'PENDING' => 'Bekliyor',
                'PREPARING' => 'Hazırlanıyor',
                'READY' => 'Hazır',
                'SERVED' => 'Tamamlandı',
                'CANCELLED' => 'İptal'
            ];
            $statusText = $statusMap[$order['status']] ?? $order['status'] ?? 'Bilinmiyor';
            
            $paymentMethodMap = [
                'CASH' => 'Nakit',
                'CARD' => 'Kredi Kartı',
                'QR' => 'QR Kod',
                'MIXED' => 'Karışık'
            ];
            $paymentMethod = $paymentMethodMap[$order['payment_method']] ?? $order['payment_method'] ?? 'Nakit';
            
            $exportData[] = [
                'id' => $order['order_id'] ?? '',
                'table' => $order['table_name'] ?? 'Bilinmiyor',
                'customer' => $order['created_by'] ?? $order['customer_name'] ?? 'QR Sipariş',
                'status' => strtolower($order['status'] ?? 'pending'),
                'date' => $order['created_at'] ?? '',
                'amount' => floatval($order['total_amount'] ?? 0),
                'payment_method' => $paymentMethod
            ];
        }
        
        // Prepare filters for filename
        $filters = [
            'date_filter' => $dateFilter,
            'start_date' => $startDate,
            'end_date' => $endDate
        ];
        
        // Export using ExportService
        $exportService = \App\Core\DependencyFactory::getExportService();
        $exportService->exportOrders($exportData, $format, $filters);
    }
    
    public function exportReport() {
        // Check if user has permission
        $this->requirePermission('reports.export');

        $requestData = \App\Core\RequestParser::getRequestData();
        $reportType = $requestData['type'] ?? '';
        $startDate = $requestData['start_date'] ?? date('Y-m-01');
        $endDate = $requestData['end_date'] ?? date('Y-m-d');

        // Validate dates
        $startDateTime = \DateTime::createFromFormat('Y-m-d', $startDate);
        $endDateTime = \DateTime::createFromFormat('Y-m-d', $endDate);

        if (!$startDateTime || !$endDateTime) {
            $startDate = date('Y-m-01');
            $endDate = date('Y-m-d');
        } else {
            if ($endDateTime < $startDateTime) {
                $endDate = $startDate;
            }
        }

        // Generate report data based on type
        $reportData = [];
        $filename = '';

        switch ($reportType) {
            case 'sales':
                $reportData = $this->generateSalesReport($startDate, $endDate);
                $filename = 'sales_report_' . $startDate . '_to_' . $endDate . '.csv';
                break;
            case 'inventory':
                $reportData = $this->generateInventoryReport();
                $filename = 'inventory_report_' . date('Y-m-d') . '.csv';
                break;
            case 'employees':
                $reportData = $this->generateEmployeePerformanceReport($startDate, $endDate);
                $filename = 'employee_report_' . $startDate . '_to_' . $endDate . '.csv';
                break;
            case 'customers':
                $reportData = $this->generateCustomerReport($startDate, $endDate);
                $filename = 'customer_report_' . $startDate . '_to_' . $endDate . '.csv';
                break;
            case 'expenses':
                $reportData = $this->generateExpenseReport($startDate, $endDate);
                $filename = 'expense_report_' . $startDate . '_to_' . $endDate . '.csv';
                break;
            case 'profit_loss':
                $reportData = $this->generateProfitLossReport($startDate, $endDate);
                $filename = 'profit_loss_report_' . $startDate . '_to_' . $endDate . '.csv';
                break;
            default:
                header('Location: ' . BASE_URL . '/admin/reports');
                exit;
        }

        // Export as CSV
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $output = fopen('php://output', 'w');

        // Add headers based on report type
        if ($reportType === 'employees') {
            fputcsv($output, ['Personel Adı', 'E-posta', 'İşlenen Sipariş', 'Toplam Satış', 'Ortalama Sipariş Değeri', 'Tamamlanan Sipariş']);
            foreach ($reportData as $row) {
                fputcsv($output, [
                    $row['name'] ?? '',
                    $row['email'] ?? '',
                    $row['orders_handled'] ?? 0,
                    $row['total_sales'] ?? 0,
                    $row['avg_order_value'] ?? 0,
                    $row['completed_orders'] ?? 0
                ]);
            }
        } elseif ($reportType === 'tables') {
            fputcsv($output, ['Masa Adı', 'Bölge', 'Toplam Sipariş', 'Toplam Gelir', 'Ortalama Sipariş Değeri', 'Tamamlanan Sipariş', 'Aktif Günler']);
            foreach ($reportData as $row) {
                fputcsv($output, [
                    $row['table_name'] ?? '',
                    $row['zone'] ?? '',
                    $row['total_orders'] ?? 0,
                    $row['total_revenue'] ?? 0,
                    $row['avg_order_value'] ?? 0,
                    $row['completed_orders'] ?? 0,
                    $row['active_days'] ?? 0
                ]);
            }
        } else {
            // For other reports, just export the data
            fputcsv($output, ['Metrik', 'Değer']);
            foreach ($reportData as $key => $value) {
                if (is_array($value)) {
                    // Handle nested arrays (like inventory items)
                    fputcsv($output, [$key, 'Detaylar']);
                    foreach ($value as $subItem) {
                        if (is_array($subItem)) {
                            fputcsv($output, ['', json_encode($subItem)]);
                        } else {
                            fputcsv($output, ['', $subItem]);
                        }
                    }
                } else {
                    fputcsv($output, [$key, $value]);
                }
            }
        }

        fclose($output);
        exit;
    }
    
    public function settings() {
        // CRITICAL: Ensure tenant context is set for security
        $this->ensureTenantContext();
        
        // Check if user has permission
        $this->requirePermission('settings.view');
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->requirePermission('settings.edit');
            
            $requestData = \App\Core\RequestParser::getRequestData();
            $settingsData = [];
            
            // Genel Ayarlar
            if (isset($requestData['site_name'])) {
                $settingsData['site_name'] = sanitizeInput($requestData['site_name']);
            }
            
            // Finansal Ayarlar
            if (isset($requestData['service_charge_rate'])) {
                $settingsData['service_charge_rate'] = floatval($requestData['service_charge_rate']);
            }
            if (isset($requestData['cover_charge'])) {
                $settingsData['cover_charge'] = floatval($requestData['cover_charge']);
            }
            if (isset($requestData['currency'])) {
                $settingsData['currency'] = sanitizeInput($requestData['currency']);
            }
            
            // Sipariş ID Formatı Ayarları
            if (isset($requestData['order_id_prefix'])) {
                $settingsData['order_id_prefix'] = sanitizeInput($requestData['order_id_prefix']);
            }
            if (isset($requestData['order_number_length'])) {
                $orderNumberLength = intval($requestData['order_number_length']);
                // Validate: between 1 and 10
                if ($orderNumberLength >= 1 && $orderNumberLength <= 10) {
                    $settingsData['order_number_length'] = $orderNumberLength;
                }
            }
            
            // Sistem Ayarları
            if (isset($requestData['app_env'])) {
                $settingsData['app_env'] = sanitizeInput($requestData['app_env']);
            }
            if (isset($requestData['app_debug'])) {
                // app_debug is a boolean, convert to string 'true' or 'false'
                $appDebugValue = sanitizeInput($requestData['app_debug']);
                $settingsData['app_debug'] = ($appDebugValue === 'true' || $appDebugValue === '1' || $appDebugValue === true || $appDebugValue === 1) ? 'true' : 'false';
            }
            if (isset($requestData['timezone'])) {
                $settingsData['timezone'] = sanitizeInput($requestData['timezone']);
            }
            if (isset($requestData['default_language'])) {
                $settingsData['default_language'] = sanitizeInput($requestData['default_language']);
            }
            if (isset($requestData['session_timeout'])) {
                $settingsData['session_timeout'] = intval($requestData['session_timeout']);
            }
            if (isset($requestData['max_upload_size'])) {
                $settingsData['max_upload_size'] = intval($requestData['max_upload_size']);
            }
            
            // Dil Ayarları
            // Handle supported_languages[] array from form
            // FormData sends arrays with [] suffix, PHP receives them as arrays
            if (isset($requestData['supported_languages']) && is_array($requestData['supported_languages'])) {
                // En az bir dil seçilmeli
                if (count($requestData['supported_languages']) > 0) {
                    $settingsData['supported_languages'] = json_encode($requestData['supported_languages']);
                } else {
                    // Eğer hiç dil seçilmemişse varsayılan dili ekle
                    $defaultLang = $requestData['default_language'] ?? 'tr';
                    $settingsData['supported_languages'] = json_encode([$defaultLang]);
                }
            } elseif (isset($requestData['supported_languages[]'])) {
                // Handle case where FormData sends as array with [] suffix
                $langs = is_array($requestData['supported_languages[]']) ? $requestData['supported_languages[]'] : [$requestData['supported_languages[]']];
                // Filter out empty values
                $langs = array_filter($langs, function($lang) { return !empty($lang) && $lang !== '0'; });
                if (count($langs) > 0) {
                    $settingsData['supported_languages'] = json_encode(array_values($langs));
                } else {
                    $defaultLang = $requestData['default_language'] ?? 'tr';
                    $settingsData['supported_languages'] = json_encode([$defaultLang]);
                }
            }
            if (isset($requestData['language_switcher_enabled'])) {
                $settingsData['language_switcher_enabled'] = '1';
            } else {
                $settingsData['language_switcher_enabled'] = '0';
            }
            if (isset($requestData['auto_detect_language'])) {
                $settingsData['auto_detect_language'] = '1';
            } else {
                $settingsData['auto_detect_language'] = '0';
            }
            
            // E-posta Ayarları
            if (isset($requestData['smtp_host'])) {
                $settingsData['smtp_host'] = sanitizeInput($requestData['smtp_host']);
            }
            if (isset($requestData['smtp_port'])) {
                $settingsData['smtp_port'] = intval($requestData['smtp_port']);
            }
            if (isset($requestData['smtp_encryption'])) {
                $settingsData['smtp_encryption'] = sanitizeInput($requestData['smtp_encryption']);
            }
            if (isset($requestData['smtp_username'])) {
                $settingsData['smtp_username'] = sanitizeInput($requestData['smtp_username']);
            }
            if (isset($requestData['smtp_password'])) {
                $settingsData['smtp_password'] = sanitizeInput($requestData['smtp_password']);
            }
            if (isset($requestData['smtp_from_name'])) {
                $settingsData['smtp_from_name'] = sanitizeInput($requestData['smtp_from_name']);
            }
            
            // Entegrasyonlar
            if (isset($requestData['iyzico_api_key'])) {
                $settingsData['iyzico_api_key'] = sanitizeInput($requestData['iyzico_api_key']);
            }
            if (isset($requestData['iyzico_secret_key'])) {
                $settingsData['iyzico_secret_key'] = sanitizeInput($requestData['iyzico_secret_key']);
            }
            if (isset($requestData['webhook_url'])) {
                $settingsData['webhook_url'] = sanitizeInput($requestData['webhook_url']);
            }
            
            // Netgsm SMS Ayarları
            if (isset($requestData['netgsm_username'])) {
                $settingsData['netgsm_username'] = sanitizeInput($requestData['netgsm_username']);
            }
            if (isset($requestData['netgsm_password'])) {
                $settingsData['netgsm_password'] = sanitizeInput($requestData['netgsm_password']);
            }
            if (isset($requestData['netgsm_msgheader'])) {
                $settingsData['netgsm_msgheader'] = sanitizeInput($requestData['netgsm_msgheader']);
            }
            
            // Check if this is an AJAX request
            $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                      strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
            
            // Validate settings data
            if (empty($settingsData)) {
                if ($isAjax) {
                    $this->toastNotificationService->sendApiResponse('error', 'notifications.error.not_found', [], 400);
                    return;
                }
                $this->toastNotificationService->setFlash('error', 'notifications.error.not_found');
                header('Location: ' . BASE_URL . '/admin/settings');
                exit;
            }
            
            try {
                $result = $this->settingsService->updateSettings($settingsData);
                
                // Reload EmailService driver if SMTP settings were updated
                if ($result && (isset($settingsData['smtp_host']) || isset($settingsData['smtp_username']) || isset($settingsData['smtp_password']))) {
                    try {
                        $emailService = \App\Core\DependencyFactory::getEmailService();
                        $emailService->reloadDriver();
                    } catch (\Exception $e) {
                        \App\Core\Logger::error('Failed to reload email driver: ' . $e->getMessage());
                    }
                }
                
                if ($isAjax) {
                    // Return JSON response for AJAX requests
                    if ($result) {
                        $this->toastNotificationService->sendApiResponse('success', 'notifications.success.settings_updated', [], 200);
                    } else {
                        // Log error for debugging
                        \App\Core\Logger::error('Settings update failed. Data: ' . json_encode($settingsData));
                        $this->toastNotificationService->sendApiResponse('error', 'notifications.error.settings_update_failed', [], 500);
                    }
                    return;
                }
            } catch (\Exception $e) {
                \App\Core\Logger::error('Settings update exception: ' . $e->getMessage());
                \App\Core\Logger::error('Stack trace: ' . $e->getTraceAsString());
                
                if ($isAjax) {
                    $this->toastNotificationService->sendApiResponse('error', 'notifications.error.settings_update_failed', ['error' => $e->getMessage()], 500);
                    return;
                }
                
                $this->toastNotificationService->setFlash('error', 'notifications.error.settings_update_failed', ['error' => $e->getMessage()]);
                header('Location: ' . BASE_URL . '/admin/settings');
                exit;
            }
            
            // Regular form submission (redirect)
            if ($result) {
                $this->toastNotificationService->setFlash('success', 'notifications.success.settings_updated');
            } else {
                $this->toastNotificationService->setFlash('error', 'notifications.error.settings_update_failed');
            }
            
            header('Location: ' . BASE_URL . '/admin/settings');
            exit;
        }
        
        // Get language statistics
        $translationService = \App\Core\DependencyFactory::getTranslationService();
        $languageStats = $translationService->getLanguageStatistics();
        
        $data = [
            'settings' => $this->settingsService->getAllSettings(),
            'page' => 'settings',
            'languageStats' => $languageStats
        ];
        
        $this->view('admin/settings', $data);
    }
    
    /**
     * Error logs page
     * GET /admin/error-logs
     */
    public function errorLogs() {
        $this->requirePermission('settings.view'); // Use settings permission for now
        
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $page = isset($queryParams['page']) ? (int)$queryParams['page'] : 1;
        $perPage = 50;
        $filters = [
            'source' => $queryParams['source'] ?? 'all', // 'php', 'javascript', 'all'
            'type' => $queryParams['type'] ?? '', // For JavaScript errors
            'level' => $queryParams['level'] ?? '', // For PHP errors
            'date_from' => $queryParams['date_from'] ?? '',
            'date_to' => $queryParams['date_to'] ?? '',
            'user_id' => $queryParams['user_id'] ?? '',
            'resolved' => isset($queryParams['resolved']) && $queryParams['resolved'] !== '' ? (bool)$queryParams['resolved'] : null
        ];
        
        // Remove empty filters
        $filters = array_filter($filters, function($value) {
            return $value !== '' && $value !== null;
        });
        
        $result = $this->unifiedErrorLogService->getAllErrorLogs($page, $perPage, $filters);
        $statistics = $this->unifiedErrorLogService->getUnifiedStatistics();
        
        $data = [
            'title' => 'Hata Logları',
            'error_logs' => $result['logs'],
            'pagination' => [
                'total' => $result['total'],
                'page' => $result['page'],
                'per_page' => $result['per_page'],
                'total_pages' => $result['total_pages']
            ],
            'filters' => [
                'source' => $queryParams['source'] ?? 'all',
                'type' => $queryParams['type'] ?? '',
                'level' => $queryParams['level'] ?? '',
                'date_from' => $queryParams['date_from'] ?? '',
                'date_to' => $queryParams['date_to'] ?? '',
                'user_id' => $queryParams['user_id'] ?? '',
                'resolved' => isset($queryParams['resolved']) ? (bool)$queryParams['resolved'] : null
            ],
            'statistics' => $statistics
        ];
        
        $this->view('admin/error_logs', $data);
    }
    
    /**
     * Error logs page - Hata Yakalama Merkezi
     * GET /admin/error-analytics
     */
    public function errorAnalytics() {
        $this->requirePermission('settings.view');
        
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $page = isset($queryParams['page']) ? (int)$queryParams['page'] : 1;
        $perPage = 50;
        $filters = [
            'source' => $queryParams['source'] ?? 'all', // 'php', 'javascript', 'all'
            'type' => $queryParams['type'] ?? '', // For JavaScript errors
            'level' => $queryParams['level'] ?? '', // For PHP errors
            'date_from' => $queryParams['date_from'] ?? '',
            'date_to' => $queryParams['date_to'] ?? '',
            'user_id' => $queryParams['user_id'] ?? '',
            'resolved' => isset($queryParams['resolved']) && $queryParams['resolved'] !== '' ? (bool)$queryParams['resolved'] : null
        ];
        
        // Remove empty filters
        $filters = array_filter($filters, function($value) {
            return $value !== '' && $value !== null;
        });
        
        // Get error logs with pagination
        $result = $this->unifiedErrorLogService->getAllErrorLogs($page, $perPage, $filters);
        
        // Get unified statistics for dashboard
        $statistics = $this->unifiedErrorLogService->getUnifiedStatistics();
        
        $data = [
            'title' => 'Hata Yakalama Merkezi',
            'error_logs' => $result['logs'],
            'pagination' => [
                'total' => $result['total'],
                'page' => $result['page'],
                'per_page' => $result['per_page'],
                'total_pages' => $result['total_pages']
            ],
            'filters' => [
                'source' => $queryParams['source'] ?? 'all',
                'type' => $queryParams['type'] ?? '',
                'level' => $queryParams['level'] ?? '',
                'date_from' => $queryParams['date_from'] ?? '',
                'date_to' => $queryParams['date_to'] ?? '',
                'user_id' => $queryParams['user_id'] ?? '',
                'resolved' => isset($queryParams['resolved']) ? (bool)$queryParams['resolved'] : null
            ],
            'statistics' => $statistics
        ];
        
        $this->view('admin/error_analytics', $data);
    }
    
    /**
     * Shifts Management - DISABLED
     */
    public function shifts() {
        // Shift management removed - redirect to finance
        \App\Core\HelperLoader::ensureLoaded();
        header('Location: ' . getAdminUrl('finance'));
        exit;

        // DEAD CODE BELOW - kept to avoid breaking large file restructure
        $this->requirePermission('finance.view');
        
        // Get view type (weekly or monthly)
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $viewType = $queryParams['view'] ?? 'weekly'; // weekly or monthly
        $selectedDate = $queryParams['date'] ?? date('Y-m-d');
        
        // Calculate date range based on view type
        if ($viewType === 'monthly') {
            $startDate = date('Y-m-01', strtotime($selectedDate));
            $endDate = date('Y-m-t', strtotime($selectedDate));
        } else {
            // Weekly view - get week start (Monday)
            $date = new \DateTime($selectedDate);
            $dayOfWeek = $date->format('w');
            $daysToMonday = ($dayOfWeek == 0) ? 6 : $dayOfWeek - 1;
            $date->modify("-{$daysToMonday} days");
            $startDate = $date->format('Y-m-d');
            $date->modify('+6 days');
            $endDate = $date->format('Y-m-d');
        }
        
        // Get shift schedules (planned shifts) - with error handling
        $shiftSchedules = [];
        try {
            $shiftSchedules = $this->shiftScheduleService->getByDateRange($startDate, $endDate);
        } catch (\Exception $e) {
            // Table might not exist yet, return empty array
            \App\Core\Logger::error("ShiftScheduleService error: " . $e->getMessage());
            $shiftSchedules = [];
        }
        
        // Get actual shifts (completed shifts)
        $endDateFull = $endDate . ' 23:59:59';
        $startDateFull = $startDate . ' 00:00:00';
        $actualShifts = [];
        try {
            $actualShifts = $this->shiftService->getShiftsByDateRange($startDateFull, $endDateFull);
        } catch (\Exception $e) {
            \App\Core\Logger::error("ShiftService error: " . $e->getMessage());
            $actualShifts = [];
        }
        
        // Get all staff for dropdown
        $allStaff = [];
        try {
            $allStaff = $this->userService->getAll();
            \App\Core\Logger::debug("Shifts: getAll returned " . count($allStaff) . " users");
        } catch (\Exception $e) {
            \App\Core\Logger::error("UserService getAll error: " . $e->getMessage());
            $allStaff = [];
        }
        
        // Filter out customers, only get staff members
        // Define valid staff roles explicitly
        $validStaffRoles = [
            'ROLE_MANAGER', 'ROLE_WAITER', 'ROLE_KITCHEN', 'ROLE_CASHIER',
            'MANAGER', 'WAITER', 'KITCHEN', 'CASHIER'
        ];
        
        $staffMembers = array_filter($allStaff, function($user) use ($validStaffRoles) {
            // Get user role (check both role and role_id fields)
            $userRole = strtoupper(trim($user['role'] ?? $user['role_id'] ?? ''));
            
            // Exclude if role is empty
            if (empty($userRole)) {
                return false;
            }
            
            // Explicitly exclude CUSTOMER role in any format
            if ($userRole === 'CUSTOMER' || 
                $userRole === 'ROLE_CUSTOMER' ||
                strpos($userRole, 'CUSTOMER') !== false) {
                return false;
            }
            
            // Check if role matches valid staff roles exactly
            if (in_array($userRole, $validStaffRoles, true)) {
                return true;
            }
            
            // Check if role starts with ROLE_ prefix and matches without prefix
            if (strpos($userRole, 'ROLE_') === 0) {
                $roleWithoutPrefix = substr($userRole, 5); // Remove 'ROLE_' prefix
                $validRoles = [
                    ConstantsHelper::getRole('MANAGER'),
                    ConstantsHelper::getRole('WAITER'),
                    ConstantsHelper::getRole('KITCHEN'),
                    ConstantsHelper::getRole('CASHIER')
                ];
                if (in_array($roleWithoutPrefix, $validRoles, true)) {
                    return true;
                }
            }
            
            // Exclude by default - only include explicitly defined staff roles
            return false;
        });
        $staffMembers = array_values($staffMembers);
        
        \App\Core\Logger::debug("Shifts: getAll returned " . count($allStaff) . " users, filtered to " . count($staffMembers) . " staff members");
        if (count($staffMembers) === 0 && count($allStaff) > 0) {
            // Debug: log first few users' roles to see what format is used
            foreach (array_slice($allStaff, 0, 5) as $index => $user) {
                \App\Core\Logger::debug("Shifts: User #{$index} - role: " . ($user['role'] ?? 'NULL') . ", role_id: " . ($user['role_id'] ?? 'NULL') . ", name: " . ($user['name'] ?? 'NULL'));
            }
        }
        
        // Get guest staff
        $guestStaff = [];
        try {
            $guestStaff = $this->guestStaffService->getActive();
        } catch (\Exception $e) {
            \App\Core\Logger::error("Error getting guest staff: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            $guestStaff = [];
        }
        
        // Get weekly schedules for all staff - with error handling
        $staffSchedules = [];
        try {
            foreach ($staffMembers as $staff) {
                try {
                    $staffSchedules[$staff['user_id']] = $this->staffScheduleService->getWeeklySchedule($staff['user_id']);
                } catch (\Exception $e) {
                    \App\Core\Logger::error("StaffScheduleService error for {$staff['user_id']}: " . $e->getMessage() . "\n" . $e->getTraceAsString());
                    $staffSchedules[$staff['user_id']] = [];
                }
            }
        } catch (\Exception $e) {
            \App\Core\Logger::error("StaffScheduleService error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
        }
        
        $data = [
            'page_title' => 'Vardiya Planlama',
            'page' => 'shifts',
            'view_type' => $viewType,
            'selected_date' => $selectedDate,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'shift_schedules' => $shiftSchedules,
            'actual_shifts' => $actualShifts,
            'staff_members' => $staffMembers,
            'guest_staff' => $guestStaff,
            'staff_schedules' => $staffSchedules
        ];
        
        $this->view('admin/shifts', $data);
    }
    
    /**
     * Create shift (API endpoint)
     */
    public function createShift() {
        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_request', [], 400);
                return;
            }
            
            $this->requirePermission('finance.shifts');
            
            $requestData = \App\Core\RequestParser::getRequestData();
            $staffId = $requestData['staff_id'] ?? '';
            $startDate = $requestData['start_date'] ?? date('Y-m-d');
            $startTime = $requestData['start_time'] ?? '09:00';
            $endTime = $requestData['end_time'] ?? '17:00';
            $openingCash = floatval($requestData['opening_cash'] ?? 0);
            $notes = $requestData['notes'] ?? '';
            
            if (empty($staffId)) {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.staff_required', [], 400);
                return;
            }
            
            // Combine date and time
            $startDateTime = $startDate . ' ' . $startTime . ':00';
            $endDateTime = $startDate . ' ' . $endTime . ':00';
            
            // Check if staff already has a shift on this date
            try {
                $existingShifts = $this->shiftService->getShiftsByStaff($staffId);
                foreach ($existingShifts as $existing) {
                    $existingDate = date('Y-m-d', strtotime($existing['start_time'] ?? ''));
                    if ($existingDate === $startDate && ($existing['status'] ?? '') === 'OPEN') {
                        $this->toastNotificationService->sendApiResponse('error', 'notifications.error.shift_already_exists', [], 400);
                        return;
                    }
                }
            } catch (\Exception $e) {
                \App\Core\Logger::error("createShift - error checking existing shifts: " . $e->getMessage());
                // Continue - don't block creation if check fails
            }
            
            // Get staff name
            try {
                $staff = $this->userService->findByUserId($staffId);
                $staffName = $staff['name'] ?? 'Bilinmeyen';
            } catch (\Exception $e) {
                \App\Core\Logger::error("createShift - error getting staff name: " . $e->getMessage());
                $staffName = 'Bilinmeyen';
            }
            
            $shiftData = [
                'staff_id' => $staffId,
                'staff_name' => $staffName,
                'start_time' => $startDateTime,
                'end_time' => $endDateTime,
                'opening_cash' => $openingCash,
                'status' => 'OPEN',
                'notes' => $notes
            ];
            
            $result = $this->shiftService->createShift($shiftData);
            
            if ($result) {
                $this->toastNotificationService->sendApiResponse('success', 'notifications.success.shift_created', [], 200);
            } else {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.shift_create_failed', [], 500);
            }
        } catch (\Exception $e) {
            \App\Core\Logger::error("createShift error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.shift_create_failed', [
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Update shift (API endpoint)
     */
    public function updateShift() {
        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_request', [], 400);
                return;
            }
            
            $this->requirePermission('finance.shifts');
            
            $requestData = \App\Core\RequestParser::getRequestData();
            $shiftId = $requestData['shift_id'] ?? '';
            if (empty($shiftId)) {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.shift_id_required', [], 400);
                return;
            }
            
            $startDate = $requestData['start_date'] ?? '';
            $startTime = $requestData['start_time'] ?? '';
            $endTime = $requestData['end_time'] ?? '';
            $openingCash = isset($requestData['opening_cash']) ? floatval($requestData['opening_cash']) : null;
            $notes = $requestData['notes'] ?? '';
            
            $shiftData = [];
            
            if ($startDate && $startTime) {
                $shiftData['start_time'] = $startDate . ' ' . $startTime . ':00';
            }
            
            if ($startDate && $endTime) {
                $shiftData['end_time'] = $startDate . ' ' . $endTime . ':00';
            }
            
            if ($openingCash !== null) {
                $shiftData['opening_cash'] = $openingCash;
            }
            
            if ($notes !== '') {
                $shiftData['notes'] = $notes;
            }
            
            if (empty($shiftData)) {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.no_data_to_update', [], 400);
                return;
            }
            
            $result = $this->shiftService->updateShift($shiftId, $shiftData);
            
            if ($result) {
                $this->toastNotificationService->sendApiResponse('success', 'notifications.success.shift_updated', [], 200);
            } else {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.shift_update_failed', [], 500);
            }
        } catch (\Exception $e) {
            \App\Core\Logger::error("updateShift error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.shift_update_failed', [
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Delete shift (API endpoint)
     */
    public function deleteShift() {
        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_request', [], 400);
                return;
            }
            
            $this->requirePermission('finance.shifts');
            
            $requestData = \App\Core\RequestParser::getRequestData();
            $queryParams = \App\Core\RequestParser::getQueryParams();
            $shiftId = $requestData['shift_id'] ?? $queryParams['id'] ?? '';
            if (empty($shiftId)) {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.shift_id_required', [], 400);
                return;
            }
            
            $result = $this->shiftService->deleteShift($shiftId);
            
            if ($result) {
                $this->toastNotificationService->sendApiResponse('success', 'notifications.success.shift_deleted', [], 200);
            } else {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.shift_delete_failed', [], 500);
            }
        } catch (\Exception $e) {
            \App\Core\Logger::error("deleteShift error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.shift_delete_failed', [
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Save staff weekly schedule (API endpoint)
     */
    public function saveStaffSchedule() {
        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_request', [], 400);
                return;
            }
            
            $this->requirePermission('finance.shifts');
            
            $requestData = \App\Core\RequestParser::getRequestData();
            $staffId = $requestData['staff_id'] ?? '';
            if (empty($staffId)) {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.staff_required', [], 400);
                return;
            }
            
            // Get weekly schedule data
            $weeklySchedule = [];
            for ($day = 0; $day < 7; $day++) {
                $isWorking = isset($requestData["day_{$day}_working"]) && $requestData["day_{$day}_working"] == '1';
                $weeklySchedule[$day] = [
                    'is_working' => $isWorking ? 1 : 0,
                    'start_time' => $requestData["day_{$day}_start"] ?? '09:00:00',
                    'end_time' => $requestData["day_{$day}_end"] ?? '17:00:00',
                    'break_start' => $requestData["day_{$day}_break_start"] ?? null,
                    'break_end' => $requestData["day_{$day}_break_end"] ?? null
                ];
            }
            
            $result = $this->staffScheduleService->saveWeeklySchedule($staffId, $weeklySchedule);
            
            if ($result) {
                $this->toastNotificationService->sendApiResponse('success', 'notifications.success.schedule_saved', [], 200);
            } else {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.schedule_save_failed', [], 500);
            }
        } catch (\Exception $e) {
            \App\Core\Logger::error("saveStaffSchedule error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.schedule_save_failed', [
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Create shift schedule (API endpoint)
     */
    public function createShiftSchedule() {
        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_request', [], 400);
                return;
            }
            
            $this->requirePermission('finance.shifts');
            
            $requestData = \App\Core\RequestParser::getRequestData();
            $staffType = $requestData['staff_type'] ?? 'USER'; // USER or GUEST_STAFF
            $staffId = $requestData['staff_id'] ?? '';
            $shiftDate = $requestData['shift_date'] ?? '';
            $startTime = $requestData['start_time'] ?? '09:00';
            $endTime = $requestData['end_time'] ?? '17:00';
            $shiftType = $requestData['shift_type'] ?? 'REGULAR';
            $notes = $requestData['notes'] ?? '';
            
            // For guest staff
            $firstName = $requestData['first_name'] ?? '';
            $lastName = $requestData['last_name'] ?? '';
            $phone = $requestData['phone'] ?? '';
            $guestStaffId = null;
            
            if ($staffType === 'GUEST_STAFF') {
                if (empty($firstName) || empty($lastName) || empty($phone)) {
                    $this->toastNotificationService->sendApiResponse('error', 'notifications.error.guest_staff_required', [], 400);
                    return;
                }
                
                // Create or get guest staff
                try {
                    $guestStaffId = $this->guestStaffService->createOrGet([
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                        'phone' => $phone,
                        'email' => $requestData['email'] ?? null
                    ]);
                    $staffId = $guestStaffId; // Use guest staff ID as staff_id
                } catch (\Exception $e) {
                    \App\Core\Logger::error("createShiftSchedule - guest staff creation error: " . $e->getMessage());
                    $this->toastNotificationService->sendApiResponse('error', 'notifications.error.guest_staff_creation_failed', [
                        'message' => $e->getMessage()
                    ], 500);
                    return;
                }
            } else {
                if (empty($staffId) || empty($shiftDate)) {
                    $this->toastNotificationService->sendApiResponse('error', 'notifications.error.required_fields', [], 400);
                    return;
                }
            }
            
            if (empty($shiftDate)) {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.required_fields', [], 400);
                return;
            }
            
            // Check if date is in the past
            $today = date('Y-m-d');
            if ($shiftDate < $today) {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.no_past_date', [], 400);
                return;
            }
            
            $scheduleData = [
                'staff_id' => $staffId,
                'staff_type' => $staffType,
                'shift_date' => $shiftDate,
                'start_time' => $startTime . ':00',
                'end_time' => $endTime . ':00',
                'shift_type' => $shiftType,
                'status' => 'PLANNED',
                'notes' => $notes,
                'created_by' => $_SESSION['user_id'] ?? ''
            ];
            
            if ($staffType === 'GUEST_STAFF') {
                $scheduleData['guest_staff_id'] = $guestStaffId;
                $scheduleData['staff_name'] = trim($firstName . ' ' . $lastName);
                $scheduleData['staff_phone'] = $phone;
            }
            
            $result = $this->shiftScheduleService->createSchedule($scheduleData);
            
            if ($result) {
                $this->toastNotificationService->sendApiResponse('success', 'notifications.success.shift_schedule_created', [], 200);
            } else {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.shift_schedule_create_failed', [], 500);
            }
        } catch (\Exception $e) {
            \App\Core\Logger::error("createShiftSchedule error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.shift_schedule_create_failed', [
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Create weekly shift schedules (API endpoint)
     * Creates shifts for a whole week for one staff member
     */
    public function createWeeklyShiftSchedule() {
        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_request', [], 400);
                return;
            }
            
            $this->requirePermission('finance.shifts');
            
            $requestData = \App\Core\RequestParser::getRequestData();
            $staffType = $requestData['staff_type'] ?? 'USER';
            $staffId = $requestData['staff_id'] ?? '';
            $weekStartDate = $requestData['week_start_date'] ?? '';
            
            // For guest staff
            $firstName = $requestData['first_name'] ?? '';
            $lastName = $requestData['last_name'] ?? '';
            $phone = $requestData['phone'] ?? '';
            $guestStaffId = null;
            
            if ($staffType === 'GUEST_STAFF') {
                if (empty($firstName) || empty($lastName) || empty($phone)) {
                    $this->toastNotificationService->sendApiResponse('error', 'notifications.error.guest_staff_required', [], 400);
                    return;
                }
                
                try {
                    $guestStaffId = $this->guestStaffService->createOrGet([
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                        'phone' => $phone,
                        'email' => $requestData['email'] ?? null
                    ]);
                    $staffId = $guestStaffId;
                } catch (\Exception $e) {
                    \App\Core\Logger::error("createWeeklyShiftSchedule - guest staff creation error: " . $e->getMessage());
                    $this->toastNotificationService->sendApiResponse('error', 'notifications.error.guest_staff_creation_failed', [
                        'message' => $e->getMessage()
                    ], 500);
                    return;
                }
            } else {
                if (empty($staffId) || empty($weekStartDate)) {
                    $this->toastNotificationService->sendApiResponse('error', 'notifications.error.required_fields', [], 400);
                    return;
                }
            }
            
            if (empty($weekStartDate)) {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.required_fields', [], 400);
                return;
            }
            
            // Check if date is in the past
            $today = date('Y-m-d');
            if ($weekStartDate < $today) {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.no_past_date', [], 400);
                return;
            }
            
            // Calculate week dates (Monday to Sunday)
            $startDate = new \DateTime($weekStartDate);
            $dayOfWeek = (int)$startDate->format('w');
            $daysToMonday = ($dayOfWeek == 0) ? 6 : $dayOfWeek - 1;
            $startDate->modify("-{$daysToMonday} days");
            
            $createdCount = 0;
            $skippedCount = 0;
            
            $isGuestStaff = ($staffType === 'GUEST_STAFF');
            
            // Get weekly schedule for staff (only for system staff)
            $weeklySchedule = [];
            if (!$isGuestStaff) {
                try {
                    $weeklySchedule = $this->staffScheduleService->getWeeklySchedule($staffId);
                } catch (\Exception $e) {
                    \App\Core\Logger::error("Error getting weekly schedule: " . $e->getMessage());
                }
            }
            
            // Create shifts for each day of the week
            for ($i = 0; $i < 7; $i++) {
                try {
                    $currentDate = clone $startDate;
                    $currentDate->modify("+{$i} days");
                    $dateStr = $currentDate->format('Y-m-d');
                    
                    // Skip past dates
                    if ($dateStr < $today) {
                        $skippedCount++;
                        continue;
                    }
                    
                    // Check if day is enabled
                    $dayEnabled = isset($requestData["day_{$i}_enabled"]) && $requestData["day_{$i}_enabled"] == '1';
                    if (!$dayEnabled) {
                        continue;
                    }
                    
                    // Check if shift already exists
                    try {
                        $existing = $this->shiftScheduleService->getRepository()->getByStaffAndDate($staffId, $dateStr);
                        if ($existing) {
                            $skippedCount++;
                            continue;
                        }
                    } catch (\Exception $e) {
                        // Continue if check fails
                        \App\Core\Logger::error("Error checking existing shift: " . $e->getMessage());
                    }
                    
                    // Get times from form or weekly schedule
                    $dayOfWeek = (int)$currentDate->format('w');
                    $startTime = $requestData["day_{$i}_start"] ?? '09:00';
                    $endTime = $requestData["day_{$i}_end"] ?? '17:00';
                    
                    // If weekly schedule exists for this day, use it (only for system staff)
                    if (!$isGuestStaff && isset($weeklySchedule[$dayOfWeek]) && 
                        ($weeklySchedule[$dayOfWeek]['is_working'] ?? 0) == 1) {
                        $daySchedule = $weeklySchedule[$dayOfWeek];
                        $startTime = $daySchedule['start_time'] ? substr($daySchedule['start_time'], 0, 5) : $startTime;
                        $endTime = $daySchedule['end_time'] ? substr($daySchedule['end_time'], 0, 5) : $endTime;
                    }
                    
                    // Ensure time format
                    if (strlen($startTime) == 5) {
                        $startTime .= ':00';
                    }
                    if (strlen($endTime) == 5) {
                        $endTime .= ':00';
                    }
                    
                    $scheduleData = [
                        'staff_id' => $staffId,
                        'staff_type' => $staffType,
                        'shift_date' => $dateStr,
                        'start_time' => $startTime,
                        'end_time' => $endTime,
                        'shift_type' => 'REGULAR',
                        'status' => 'PLANNED',
                        'created_by' => $_SESSION['user_id'] ?? ''
                    ];
                    
                    if ($isGuestStaff) {
                        $scheduleData['guest_staff_id'] = $guestStaffId;
                        $scheduleData['staff_name'] = trim($firstName . ' ' . $lastName);
                        $scheduleData['staff_phone'] = $phone;
                    }
                    
                    $result = $this->shiftScheduleService->createSchedule($scheduleData);
                    if ($result) {
                        $createdCount++;
                    }
                } catch (\Exception $e) {
                    \App\Core\Logger::error("Error creating shift for day {$i}: " . $e->getMessage());
                    // Continue with next day
                }
            }
            
            $this->toastNotificationService->sendApiResponse('success', 'notifications.success.weekly_shifts_created', [
                'created' => $createdCount,
                'skipped' => $skippedCount
            ], 200);
        } catch (\Exception $e) {
            \App\Core\Logger::error("createWeeklyShiftSchedule error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.weekly_shifts_create_failed', [
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Sync dynamic permissions (Admin only)
     */
    public function syncDynamicPermissions() {
        $this->requirePermission('permissions.manage');
        
        try {
            $dynamicPermissionService = \App\Core\DependencyFactory::getDynamicPermissionService();
            $results = $dynamicPermissionService->discoverAllDynamicPermissions();
            
            $response = [
                'success' => true,
                'results' => $results
            ];
            
            $this->toastNotificationService->sendApiResponse('success', 'notifications.success.permissions_synced', $response, 200);
        } catch (\Exception $e) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.permission_sync_failed', ['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Create shift tables directly (if migrations fail)
     */
    public function createShiftTables() {
        $this->requirePermission('settings.view');
        
        try {
            $db = \App\Core\DependencyFactory::getDatabase();
            
            // Create staff_schedules table
            $sql1 = "CREATE TABLE IF NOT EXISTS `staff_schedules` (
                `schedule_id` VARCHAR(50) PRIMARY KEY,
                `staff_id` VARCHAR(50) NOT NULL,
                `day_of_week` TINYINT NOT NULL COMMENT '0=Sunday, 1=Monday, ..., 6=Saturday',
                `start_time` TIME NOT NULL,
                `end_time` TIME NOT NULL,
                `is_working` TINYINT(1) DEFAULT 1 COMMENT '0=Off day, 1=Working day',
                `break_start` TIME NULL COMMENT 'Break start time',
                `break_end` TIME NULL COMMENT 'Break end time',
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_staff_id` (`staff_id`),
                INDEX `idx_day_of_week` (`day_of_week`),
                UNIQUE KEY `unique_staff_day` (`staff_id`, `day_of_week`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Personel haftalık çalışma programı'";
            
            $db->exec($sql1);
            
            // Create shift_schedules table
            $sql2 = "CREATE TABLE IF NOT EXISTS `shift_schedules` (
                `schedule_id` VARCHAR(50) PRIMARY KEY,
                `staff_id` VARCHAR(50) NOT NULL,
                `staff_type` VARCHAR(20) DEFAULT 'USER' COMMENT 'USER or GUEST_STAFF',
                `guest_staff_id` VARCHAR(50) NULL COMMENT 'Reference to guest_staff table',
                `staff_name` VARCHAR(200) NULL COMMENT 'Staff name for guest staff',
                `staff_phone` VARCHAR(20) NULL COMMENT 'Staff phone for guest staff',
                `shift_date` DATE NOT NULL,
                `start_time` TIME NOT NULL,
                `end_time` TIME NOT NULL,
                `shift_type` VARCHAR(20) DEFAULT 'REGULAR' COMMENT 'REGULAR, OVERTIME, HOLIDAY',
                `status` VARCHAR(20) DEFAULT 'PLANNED' COMMENT 'PLANNED, CONFIRMED, CANCELLED',
                `notes` TEXT NULL,
                `created_by` VARCHAR(50) NULL COMMENT 'User who created this schedule',
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_staff_id` (`staff_id`),
                INDEX `idx_shift_date` (`shift_date`),
                INDEX `idx_status` (`status`),
                INDEX `idx_staff_date` (`staff_id`, `shift_date`),
                INDEX `idx_guest_staff_id` (`guest_staff_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Planlanmış vardiyalar'";
            
            $db->exec($sql2);
            
            // Create guest_staff table
            $sql3 = "CREATE TABLE IF NOT EXISTS `guest_staff` (
                `guest_staff_id` VARCHAR(50) PRIMARY KEY,
                `first_name` VARCHAR(100) NOT NULL,
                `last_name` VARCHAR(100) NOT NULL,
                `phone` VARCHAR(20) NOT NULL,
                `email` VARCHAR(255) NULL,
                `notes` TEXT NULL,
                `is_active` TINYINT(1) DEFAULT 1,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_phone` (`phone`),
                INDEX `idx_active` (`is_active`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Misafir/Geçici çalışanlar'";
            
            $db->exec($sql3);
            
            // Record migrations if migrations table exists
            try {
                $stmt = $db->prepare("INSERT IGNORE INTO migrations (migration, batch) VALUES (?, ?)");
                $batch = 1;
                $stmt->execute(['016_create_staff_schedules_table', $batch]);
                $stmt->execute(['017_create_shift_schedules_table', $batch]);
                $stmt->execute(['018_create_guest_staff_table', $batch]);
                $stmt->execute(['019_enhance_shift_schedules_for_guest_staff', $batch]);
            } catch (\Exception $e) {
                // Migrations table may not exist, that's okay
            }
            
            $this->toastNotificationService->sendApiResponse('success', 'notifications.success.tables_created', [], 200);
        } catch (\Exception $e) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.table_creation_failed', ['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Generate shifts from weekly schedule (API endpoint)
     */
    public function generateShiftsFromSchedule() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_request', [], 400);
            return;
        }
        
        $this->requirePermission('finance.shifts');
        
        $requestData = \App\Core\RequestParser::getRequestData();
        $staffId = $requestData['staff_id'] ?? '';
        $startDate = $requestData['start_date'] ?? date('Y-m-d');
        $endDate = $requestData['end_date'] ?? date('Y-m-d', strtotime('+7 days'));
        
        if (empty($staffId)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.staff_required', [], 400);
            return;
        }
        
        $count = $this->shiftScheduleService->generateFromWeeklySchedule($staffId, $startDate, $endDate);
        
        $this->toastNotificationService->sendApiResponse('success', 'notifications.success.shifts_generated', ['count' => $count], 200);
    }
    
    /**
     * Upload logo
     */
    public function uploadLogo() {
        $this->requirePermission('settings.edit');

        // HandlesFileUpload trait'i kullanılarak finfo tabanlı MIME doğrulama +
        // güvenli uzantı eşlemesi + path traversal koruması sağlanır.
        $allowedTypes = ['image/png', 'image/jpeg', 'image/jpg', 'image/gif', 'image/svg+xml'];
        $uploadDir = __DIR__ . '/../../public/assets/images/';
        $result = $this->uploadFile('logo', $allowedTypes, 5 * 1024 * 1024, $uploadDir, 'logo');

        if (!$result['success']) {
            \App\Core\Logger::warning('Logo upload rejected', ['error' => $result['error']]);
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_file_type', [], 400);
            return;
        }

        $logoUrl = BASE_URL . '/assets/images/' . $result['filename'];
        $this->settingsService->setSetting('logo_url', $logoUrl);
        $this->toastNotificationService->sendApiResponse('success', 'notifications.success.logo_uploaded', ['url' => $logoUrl], 200);
    }
    
    /**
     * Upload favicon
     */
    public function uploadFavicon() {
        $this->requirePermission('settings.edit');

        $allowedTypes = ['image/x-icon', 'image/vnd.microsoft.icon', 'image/png', 'image/svg+xml'];
        $uploadDir = __DIR__ . '/../../public/assets/images/';
        $result = $this->uploadFile('favicon', $allowedTypes, 1 * 1024 * 1024, $uploadDir, 'favicon');

        if (!$result['success']) {
            \App\Core\Logger::warning('Favicon upload rejected', ['error' => $result['error']]);
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_file_type', [], 400);
            return;
        }

        $faviconUrl = BASE_URL . '/assets/images/' . $result['filename'];
        $this->settingsService->setSetting('favicon_url', $faviconUrl);
        $this->toastNotificationService->sendApiResponse('success', 'notifications.success.favicon_uploaded', ['url' => $faviconUrl], 200);
    }
    
    public function resetSystem() {
        // Check if user has permission
        if (!$this->hasPermission('settings.reset')) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 401);
            return;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            \App\Core\Logger::info("System reset initiated by user: " . ($_SESSION['username'] ?? 'Unknown') . " (ID: " . ($_SESSION['user_id'] ?? 'N/A') . ")");
            \App\Core\Logger::error("=== SYSTEM RESET STARTED ===");
            
            try {
                // STEP 1: Create database backup before resetting
                // Note: If backup fails, we still proceed with reset (backup is optional safety measure)
                \App\Core\Logger::error("STEP 1: Creating database backup...");
                $backupResult = $this->createDatabaseBackup();
                if (!$backupResult['success']) {
                    // Log warning but continue - backup failure shouldn't stop reset
                    \App\Core\Logger::error("Warning: Database backup failed, but continuing with reset: " . $backupResult['error']);
                    $backupResult = ['success' => false, 'filename' => null, 'error' => $backupResult['error']];
                } else {
                    \App\Core\Logger::error("STEP 1: Database backup created successfully: " . ($backupResult['filename'] ?? 'N/A'));
                }
                
                // STEP 2: Backup logo and favicon (optional, but recommended)
                \App\Core\Logger::error("STEP 2: Backing up logo and favicon...");
                try {
                    $logoFaviconBackup = $this->backupLogoAndFavicon();
                    if (isset($logoFaviconBackup['success']) && $logoFaviconBackup['success']) {
                        \App\Core\Logger::error("STEP 2: Logo/Favicon backup created: " . ($logoFaviconBackup['filename'] ?? 'N/A'));
                    } else {
                        \App\Core\Logger::error("STEP 2: Logo/Favicon backup skipped or failed");
                    }
                } catch (\Exception $e) {
                    \App\Core\Logger::error("STEP 2: Logo/Favicon backup error (non-critical): " . $e->getMessage());
                    $logoFaviconBackup = ['success' => false];
                }
                
                // STEP 3: Delete all uploaded images (except logo and favicon)
                \App\Core\Logger::error("STEP 3: Deleting uploaded images...");
                try {
                    $this->deleteUploadedImages();
                    \App\Core\Logger::error("STEP 3: Uploaded images deleted successfully");
                } catch (\Exception $e) {
                    \App\Core\Logger::error("STEP 3: Error deleting uploaded images (non-critical): " . $e->getMessage());
                }
                
                // Get database connection
                \App\Core\Logger::error("STEP 4: Getting database connection...");
                $pdo = \App\Core\DependencyFactory::getDatabase();
                
                // Start transaction
                \App\Core\Logger::error("STEP 5: Starting database transaction...");
                $pdo->beginTransaction();
                \App\Core\Logger::error("STEP 5: Transaction started");
                
                // CRITICAL TABLES THAT MUST BE PRESERVED:
                // - system_settings (ayarlar)
                // - roles, system_permissions, role_permissions (yetkilendirme)
                // - system_constants, system_labels (sistem sabitleri)
                // - migrations (migration geçmişi)
                // - leave_types (izin tipleri - sistem verisi)
                // - users (mevcut admin hariç tüm kullanıcılar silinir)
                
                $preservedTables = [
                    'system_settings',
                    'roles',
                    'system_permissions',
                    'role_permissions',
                    'system_constants',
                    'system_labels',
                    'migrations',
                    'leave_types',
                    'users' // Will be handled separately - delete all except current admin
                ];
                
                // Get all tables from database dynamically
                \App\Core\Logger::error("STEP 6: Getting all tables from database...");
                $stmt = $pdo->prepare("SHOW TABLES");
                $stmt->execute();
                $allTables = [];
                while ($row = $stmt->fetch(\PDO::FETCH_NUM)) {
                    $allTables[] = $row[0];
                }
                \App\Core\Logger::error("STEP 6: Found " . count($allTables) . " tables in database");
                
                // Filter out preserved tables
                $tables = array_filter($allTables, function($table) use ($preservedTables) {
                    return !in_array($table, $preservedTables);
                });
                $tables = array_values($tables); // Re-index array
                \App\Core\Logger::error("STEP 6: " . count($tables) . " tables will be truncated (excluding " . count($preservedTables) . " preserved tables)");
                
                // Disable foreign key checks temporarily
                \App\Core\Logger::error("STEP 7: Disabling foreign key checks...");
                $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
                
                // Truncate tables (this will also reset auto-increment)
                \App\Core\Logger::error("STEP 8: Truncating " . count($tables) . " tables...");
                $truncatedCount = 0;
                $failedCount = 0;
                $failedTables = [];
                
                foreach ($tables as $table) {
                    try {
                        // Sanitize table name
                        $sanitizedTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
                        // Get record count before deletion
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM `{$sanitizedTable}`");
                        $stmt->execute();
                        $countBefore = $stmt->fetchColumn();
                        \App\Core\Logger::error("Table {$sanitizedTable}: {$countBefore} records before deletion");

                        // Use DELETE instead of TRUNCATE because TRUNCATE commits the transaction
                        // DELETE works within transactions, TRUNCATE does not
                        $pdo->exec("DELETE FROM `{$sanitizedTable}`");

                        // Reset auto-increment after DELETE (similar to TRUNCATE behavior)
                        try {
                            $pdo->exec("ALTER TABLE `{$sanitizedTable}` AUTO_INCREMENT = 1");
                        } catch (\Exception $autoIncEx) {
                            // Some tables may not have auto-increment, ignore this error
                            \App\Core\Logger::error("Table {$table}: Could not reset auto-increment (non-critical): " . $autoIncEx->getMessage());
                        }
                        
                        // Verify deletion
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM `{$sanitizedTable}`");
                        $stmt->execute();
                        $countAfter = $stmt->fetchColumn();
                        
                        if ((int)$countAfter === 0) {
                            $truncatedCount++;
                            \App\Core\Logger::error("Table {$table}: Successfully cleared ({$countBefore} records deleted)");
                        } else {
                            throw new \Exception("Still has {$countAfter} records after DELETE");
                        }
                    } catch (\Exception $e) {
                        // Log the error and mark as failed
                        $failedCount++;
                        $failedTables[] = [
                            'table' => $table,
                            'error' => $e->getMessage()
                        ];
                        \App\Core\Logger::error("Table {$table}: Failed to clear - " . $e->getMessage());
                    }
                }
                
                \App\Core\Logger::error("STEP 8: Truncated {$truncatedCount} tables, {$failedCount} failed");
                if (!empty($failedTables)) {
                    \App\Core\Logger::error("STEP 8: Failed tables: " . json_encode($failedTables));
                }
                
                // Re-enable foreign key checks
                \App\Core\Logger::error("STEP 9: Re-enabling foreign key checks...");
                $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
                
                // Delete all users except the current admin user - use repository method
                // This preserves the current admin user but removes all other users
                \App\Core\Logger::error("STEP 10: Deleting users (except current admin)...");
                try {
                    $userRepository = \App\Core\DependencyFactory::getUserRepository();
                    $currentUserId = $_SESSION['user_id'] ?? '';
                    if (!empty($currentUserId)) {
                        $userRepository->deleteAllExcept($currentUserId);
                        \App\Core\Logger::error("STEP 10: Users deleted (kept user ID: {$currentUserId})");
                    } else {
                        // If no current user ID, delete all users (should not happen, but safety check)
                        \App\Core\Logger::error("Warning: No current user ID found during system reset");
                        $userRepository->deleteAll();
                        \App\Core\Logger::error("STEP 10: All users deleted (no current user ID found)");
                    }
                } catch (\Exception $e) {
                    \App\Core\Logger::error("STEP 10: Error deleting users: " . $e->getMessage());
                    throw $e; // Re-throw as this is critical
                }
                
                // Clear cache to ensure fresh data
                \App\Core\Logger::error("STEP 11: Clearing cache...");
                try {
                    $cacheService = \App\Core\DependencyFactory::getCacheService();
                    $cacheService->clear(); // Clear all cache
                    \App\Core\Logger::error("STEP 11: Cache cleared successfully");
                } catch (\Exception $e) {
                    \App\Core\Logger::error("STEP 11: Could not clear cache during system reset (non-critical): " . $e->getMessage());
                }
                
                // Commit transaction (only if still active)
                \App\Core\Logger::error("STEP 12: Checking transaction status...");
                try {
                    if ($pdo->inTransaction()) {
                        \App\Core\Logger::error("STEP 12: Committing transaction...");
                        $pdo->commit();
                        \App\Core\Logger::error("STEP 12: Transaction committed successfully");
                    } else {
                        \App\Core\Logger::error("STEP 12: No active transaction to commit (may have been auto-committed by DDL statements)");
                    }
                } catch (\Exception $commitEx) {
                    // If commit fails, log but don't throw - data is already deleted
                    \App\Core\Logger::error("STEP 12: Transaction commit failed (non-critical, data already deleted): " . $commitEx->getMessage());
                }
                
                \App\Core\Logger::error("=== SYSTEM RESET COMPLETED SUCCESSFULLY ===");
                \App\Core\Logger::info("System reset by user: " . ($_SESSION['username'] ?? 'Unknown') . " | Backup: " . (isset($backupResult) && isset($backupResult['filename']) ? $backupResult['filename'] : 'N/A') . " | Tables truncated: {$truncatedCount}, Failed: {$failedCount}");
                
                $responseMessage = 'Sistem başarıyla sıfırlandı. ';
                $responseMessage .= count($tables) . ' tablodan ' . $truncatedCount . ' tanesi temizlendi';
                
                if ($failedCount > 0) {
                    $responseMessage .= ', ' . $failedCount . ' tablo temizlenemedi';
                }
                
                $responseMessage .= '. Veritabanı yedeği: ' . (isset($backupResult) && isset($backupResult['filename']) ? $backupResult['filename'] : 'N/A');
                
                if (isset($logoFaviconBackup) && isset($logoFaviconBackup['success']) && $logoFaviconBackup['success']) {
                    $responseMessage .= ' | Logo/Favicon yedeği: ' . ($logoFaviconBackup['filename'] ?? 'N/A');
                }
                
                $responseData = [
                    'backup_file' => isset($backupResult) && isset($backupResult['filename']) ? $backupResult['filename'] : null,
                    'logo_favicon_backup' => isset($logoFaviconBackup) && isset($logoFaviconBackup['filename']) ? $logoFaviconBackup['filename'] : null,
                    'tables_truncated' => $truncatedCount,
                    'tables_failed' => $failedCount,
                    'total_tables' => count($tables)
                ];
                
                if (!empty($failedTables)) {
                    $responseData['failed_tables'] = $failedTables;
                }
                
                $this->toastNotificationService->sendApiResponse('success', $responseMessage, $responseData, 200);
            } catch (\Throwable $e) {
                // Rollback on error
                \App\Core\Logger::error("=== SYSTEM RESET FAILED - ROLLING BACK ===");
                if (isset($pdo) && $pdo->inTransaction()) {
                    try {
                        $pdo->rollBack();
                        \App\Core\Logger::error("Transaction rolled back successfully");
                    } catch (\Exception $rollbackEx) {
                        \App\Core\Logger::error("Error during rollback: " . $rollbackEx->getMessage());
                    }
                }
                
                $errorMessage = $e->getMessage();
                $errorTrace = $e->getTraceAsString();
                $errorFile = $e->getFile();
                $errorLine = $e->getLine();
                
                \App\Core\Logger::error("System reset failed: {$errorMessage} | File: {$errorFile}:{$errorLine} | Trace: {$errorTrace}");
                \App\Core\Logger::error("System reset exception: {$errorMessage}");
                \App\Core\Logger::error("Exception file: {$errorFile}:{$errorLine}");
                \App\Core\Logger::error("Stack trace: {$errorTrace}");
                
                // Send user-friendly error message
                $userMessage = 'Sistem sıfırlama başarısız oldu: ' . $errorMessage;
                $this->toastNotificationService->sendApiResponse('error', $userMessage, [
                    'error_details' => [
                        'message' => $errorMessage,
                        'file' => basename($errorFile),
                        'line' => $errorLine
                    ]
                ], 500);
            }
        } else {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_request', [], 400);
        }
    }
    
    /**
     * Get AI insights for restaurant performance
     */
    public function tableHistory() {
        $this->requirePermission('table.history');
        
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $tableId = $queryParams['id'] ?? '';
        if (empty($tableId)) {
            // Try to get from route parameter
            $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
            if (preg_match('/table-history\/([^\/\?]+)/', $path, $matches)) {
                $tableId = $matches[1];
            }
        }
        
        if (empty($tableId)) {
            $errorMessage = $this->toastNotificationService->translate('notifications.error.invalid_data');
            $this->view('admin/error', ['message' => $errorMessage]);
            return;
        }
        
        $table = $this->tableService->getTableById($tableId);
        if (!$table) {
            $errorMessage = $this->toastNotificationService->translate('notifications.error.table_not_found');
            $this->view('admin/error', ['message' => $errorMessage]);
            return;
        }
        
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $startDate = $queryParams['start_date'] ?? date('Y-m-01');
        $endDate = $queryParams['end_date'] ?? date('Y-m-d');
        
        $archivedSessionService = \App\Core\DependencyFactory::getArchivedSessionService();
        $sessions = $archivedSessionService->getByDateRange($startDate . ' 00:00:00', $endDate . ' 23:59:59');
        
        // Filter by table
        $tableSessions = array_filter($sessions, function($session) use ($tableId) {
            return ($session['table_id'] ?? '') === $tableId;
        });
        
        $data = [
            'table_id' => $tableId,
            'table_name' => $table['name'] ?? 'Masa',
            'sessions' => array_values($tableSessions),
            'start_date' => $startDate,
            'end_date' => $endDate
        ];
        
        $this->view('admin/table_history', $data);
    }
    
    public function getAIInsights() {
        if (!$this->hasPermission('dashboard.analytics')) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 401);
            return;
        }
        
        // Try GeminiService first (better quality), fallback to AIService
        $geminiService = \App\Core\DependencyFactory::getGeminiService();
        $aiServiceAvailable = false;
        
        if ($geminiService && $geminiService->isAvailable()) {
            $aiServiceAvailable = true;
        } elseif (\App\Services\AIService::isAvailable()) {
            $aiServiceAvailable = true;
        }
        
        if (!$aiServiceAvailable) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.ai_service_unavailable', [], 503);
            return;
        }
        
        try {
            // Get analytics data
            $queryParams = \App\Core\RequestParser::getQueryParams();
            $startDate = $queryParams['start_date'] ?? date('Y-m-01');
            $endDate = $queryParams['end_date'] ?? date('Y-m-d');
            
            $revenue = $this->orderService->calculateTotalRevenue($startDate, $endDate);
            $topItems = $this->orderService->getTopSellingItems(5);
            $expenses = $this->generateExpenseReport($startDate, $endDate);
            
            $analyticsData = [
                'revenue' => $revenue,
                'top' => $topItems,
                'expenses' => $expenses['total_expenses'] ?? 0
            ];
            
            // Use GeminiService if available, otherwise fallback to AIService
            $insights = '';
            if ($geminiService && $geminiService->isAvailable()) {
                $insights = $geminiService->analyzeRestaurantPerformance($analyticsData);
            } else {
                $insights = \App\Services\AIService::analyzeRestaurantPerformance($analyticsData);
            }
            
            if (empty($insights)) {
                throw new \Exception('AI analysis returned empty result');
            }
            
            $this->apiResponse([
                'success' => true,
                'insights' => $insights
            ]);
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('AI insights error: ' . $e->getMessage());
            }
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.ai_analysis_failed', [], 500, [
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Add leave
     */
    public function addLeave() {
        $this->requirePermission('staff.edit');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_request', [], 400);
            return;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) {
            $data = $_POST;
        }
        
        $userId = $data['user_id'] ?? '';
        $leaveTypeId = $data['leave_type_id'] ?? '';
        $startDate = $data['start_date'] ?? '';
        $endDate = $data['end_date'] ?? '';
        $reason = $data['reason'] ?? '';
        $notes = $data['notes'] ?? '';
        
        if (empty($userId) || empty($leaveTypeId) || empty($startDate) || empty($endDate)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.warning.missing_fields', [], 400);
            return;
        }
        
        // Validate dates
        $start = \DateTime::createFromFormat('Y-m-d', $startDate);
        $end = \DateTime::createFromFormat('Y-m-d', $endDate);
        
        if (!$start || !$end) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
            return;
        }
        
        if ($end < $start) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.warning.invalid_date_range', [], 400);
            return;
        }
        
        // Calculate total days
        $totalDays = $this->leaveService->calculateDays($startDate, $endDate);
        
        $leaveData = [
            'leave_id' => generateId('lv'),
            'user_id' => $userId,
            'leave_type_id' => $leaveTypeId,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'total_days' => $totalDays,
            'status' => 'APPROVED', // Admin tarafından eklenen izinler otomatik onaylı
            'approved_by' => $_SESSION['user_id'] ?? null,
            'approved_at' => date('Y-m-d H:i:s'),
            'reason' => sanitizeInput($reason),
            'notes' => sanitizeInput($notes)
        ];
        
        $result = $this->leaveService->create($leaveData);
        
        if ($result) {
            $this->toastNotificationService->sendApiResponse('success', 'notifications.success.leave_added', ['leave_id' => $leaveData['leave_id']], 200);
        } else {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.create_failed', [], 500);
        }
    }
    
    /**
     * Get leave by ID (API endpoint)
     */
    public function getLeave($leaveId = null) {
        $this->requirePermission('staff.view');
        
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $leaveId = $leaveId ?? $queryParams['id'] ?? '';
        
        if (empty($leaveId)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
            return;
        }
        
        $leave = $this->leaveService->findById($leaveId);
        
        if ($leave) {
            $this->apiResponse($leave);
        } else {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.not_found', [], 404);
        }
    }
    
    /**
     * Update leave
     */
    public function updateLeave($leaveId = null) {
        $this->requirePermission('staff.edit');
        
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $requestData = \App\Core\RequestParser::getRequestData();
        $leaveId = $leaveId ?? $queryParams['id'] ?? $requestData['id'] ?? '';
        
        if (empty($leaveId)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
            return;
        }
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_request', [], 400);
            return;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) {
            $data = $_POST;
        }
        
        $updateData = [];
        
        if (isset($data['leave_type_id'])) {
            $updateData['leave_type_id'] = sanitizeInput($data['leave_type_id']);
        }
        
        if (isset($data['start_date']) && isset($data['end_date'])) {
            $startDate = $data['start_date'];
            $endDate = $data['end_date'];
            
            $start = \DateTime::createFromFormat('Y-m-d', $startDate);
            $end = \DateTime::createFromFormat('Y-m-d', $endDate);
            
            if (!$start || !$end) {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
                return;
            }
            
            if ($end < $start) {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.warning.invalid_date_range', [], 400);
                return;
            }
            
            $updateData['start_date'] = $startDate;
            $updateData['end_date'] = $endDate;
            $updateData['total_days'] = $this->leaveService->calculateDays($startDate, $endDate);
        }
        
        if (isset($data['reason'])) {
            $updateData['reason'] = sanitizeInput($data['reason']);
        }
        
        if (isset($data['notes'])) {
            $updateData['notes'] = sanitizeInput($data['notes']);
        }
        
        if (isset($data['status'])) {
            $updateData['status'] = sanitizeInput($data['status']);
            if ($updateData['status'] === 'APPROVED') {
                $updateData['approved_by'] = $_SESSION['user_id'] ?? null;
                $updateData['approved_at'] = date('Y-m-d H:i:s');
            }
        }
        
        if (empty($updateData)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
            return;
        }
        
        $result = $this->leaveService->update($leaveId, $updateData);
        
        if ($result) {
            $this->toastNotificationService->sendApiResponse('success', 'notifications.success.leave_updated', [], 200);
        } else {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.update_failed', [], 500);
        }
    }
    
    /**
     * Delete leave
     */
    public function deleteLeave($leaveId = null) {
        $this->requirePermission('staff.edit');
        
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $requestData = \App\Core\RequestParser::getRequestData();
        $leaveId = $leaveId ?? $queryParams['id'] ?? $requestData['id'] ?? '';
        
        if (empty($leaveId)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
            return;
        }
        
        $result = $this->leaveService->delete($leaveId);
        
        if ($result) {
            $this->toastNotificationService->sendApiResponse('success', 'notifications.success.leave_deleted', [], 200);
        } else {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.delete_failed', [], 500);
        }
    }
    
    /**
     * Get medical report by ID (API endpoint)
     */
    public function getMedicalReport($reportId = null) {
        $this->requirePermission('staff.view');
        
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $reportId = $reportId ?? $queryParams['id'] ?? '';
        
        if (empty($reportId)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
            return;
        }
        
        $report = $this->medicalReportService->findById($reportId);
        
        if ($report) {
            $this->apiResponse($report);
        } else {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.not_found', [], 404);
        }
    }
    
    /**
     * Add medical report
     */
    public function addMedicalReport() {
        $this->requirePermission('staff.edit');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_request', [], 400);
            return;
        }
        
        $requestData = \App\Core\RequestParser::getRequestData();
        $userId = $requestData['user_id'] ?? '';
        $startDate = $requestData['start_date'] ?? '';
        $endDate = $requestData['end_date'] ?? '';
        $reportNumber = $requestData['report_number'] ?? '';
        $hospitalName = $requestData['hospital_name'] ?? '';
        $doctorName = $requestData['doctor_name'] ?? '';
        $notes = $requestData['notes'] ?? '';
        
        if (empty($userId) || empty($startDate) || empty($endDate)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.warning.missing_fields', [], 400);
            return;
        }
        
        // Validate dates
        $start = \DateTime::createFromFormat('Y-m-d', $startDate);
        $end = \DateTime::createFromFormat('Y-m-d', $endDate);
        
        if (!$start || !$end) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
            return;
        }
        
        if ($end < $start) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.warning.invalid_date_range', [], 400);
            return;
        }
        
        // Handle file upload
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.warning.missing_fields', [], 400);
            return;
        }
        
        $file = $_FILES['file'];
        
        // Validate file type
        $allowedTypes = ['application/pdf'];
        $fileType = mime_content_type($file['tmp_name']);
        
        if (!in_array($fileType, $allowedTypes) && pathinfo($file['name'], PATHINFO_EXTENSION) !== 'pdf') {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_file_type', [], 400);
            return;
        }
        
        // Validate file size (10MB)
        $maxSize = 10 * 1024 * 1024;
        if ($file['size'] > $maxSize) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.file_too_large', [], 400);
            return;
        }
        
        // Create upload directory
        $uploadDir = __DIR__ . '/../../public/uploads/medical_reports/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Generate unique filename
        $timestamp = time();
        $originalName = basename($file['name']);
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $filename = $userId . '_' . $timestamp . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName);
        $filepath = $uploadDir . $filename;
        
        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.file_upload_failed', [], 500);
            return;
        }
        
        // Calculate total days
        $totalDays = $this->medicalReportService->calculateDays($startDate, $endDate);
        
        $reportData = [
            'report_id' => generateId('mr'),
            'user_id' => $userId,
            'report_number' => sanitizeInput($reportNumber),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'total_days' => $totalDays,
            'hospital_name' => sanitizeInput($hospitalName),
            'doctor_name' => sanitizeInput($doctorName),
            'file_path' => '/public/uploads/medical_reports/' . $filename,
            'file_name' => $originalName,
            'file_size' => $file['size'],
            'notes' => sanitizeInput($notes)
        ];
        
        $result = $this->medicalReportService->create($reportData);
        
        if ($result) {
            $this->toastNotificationService->sendApiResponse('success', 'notifications.success.medical_report_added', ['report_id' => $reportData['report_id']], 200);
        } else {
            // Delete uploaded file if database insert failed
            @unlink($filepath);
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.create_failed', [], 500);
        }
    }
    
    /**
     * Update medical report
     */
    public function updateMedicalReport($reportId = null) {
        $this->requirePermission('staff.edit');
        
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $requestData = \App\Core\RequestParser::getRequestData();
        $reportId = $reportId ?? $queryParams['id'] ?? $requestData['id'] ?? '';
        
        if (empty($reportId)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
            return;
        }
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_request', [], 400);
            return;
        }
        
        $updateData = [];
        
        $requestData = \App\Core\RequestParser::getRequestData();
        if (isset($requestData['start_date']) && isset($requestData['end_date'])) {
            $startDate = $requestData['start_date'];
            $endDate = $requestData['end_date'];
            
            $start = \DateTime::createFromFormat('Y-m-d', $startDate);
            $end = \DateTime::createFromFormat('Y-m-d', $endDate);
            
            if (!$start || !$end) {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
                return;
            }
            
            if ($end < $start) {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.warning.invalid_date_range', [], 400);
                return;
            }
            
            $updateData['start_date'] = $startDate;
            $updateData['end_date'] = $endDate;
            $updateData['total_days'] = $this->medicalReportService->calculateDays($startDate, $endDate);
        }
        
        if (isset($requestData['report_number'])) {
            $updateData['report_number'] = sanitizeInput($requestData['report_number']);
        }
        
        if (isset($requestData['hospital_name'])) {
            $updateData['hospital_name'] = sanitizeInput($requestData['hospital_name']);
        }
        
        if (isset($requestData['doctor_name'])) {
            $updateData['doctor_name'] = sanitizeInput($requestData['doctor_name']);
        }
        
        if (isset($requestData['notes'])) {
            $updateData['notes'] = sanitizeInput($requestData['notes']);
        }
        
        // Handle file upload if provided
        if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['file'];
            
            // Validate file type
            $allowedTypes = ['application/pdf'];
            $fileType = mime_content_type($file['tmp_name']);
            
            if (!in_array($fileType, $allowedTypes) && pathinfo($file['name'], PATHINFO_EXTENSION) !== 'pdf') {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_file_type', [], 400);
                return;
            }
            
            // Validate file size (10MB)
            $maxSize = 10 * 1024 * 1024;
            if ($file['size'] > $maxSize) {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.file_too_large', [], 400);
                return;
            }
            
            // Get existing report to delete old file
            $existingReport = $this->medicalReportService->findById($reportId);
            
            // Create upload directory
            $uploadDir = __DIR__ . '/../../public/uploads/medical_reports/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            // Generate unique filename
            $userId = $existingReport['user_id'] ?? $requestData['user_id'] ?? '';
            $timestamp = time();
            $originalName = basename($file['name']);
            $filename = $userId . '_' . $timestamp . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName);
            $filepath = $uploadDir . $filename;
            
            // Move uploaded file
            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                // Delete old file
                if ($existingReport && isset($existingReport['file_path'])) {
                    $oldFilePath = __DIR__ . '/../..' . $existingReport['file_path'];
                    @unlink($oldFilePath);
                }
                
                $updateData['file_path'] = '/public/uploads/medical_reports/' . $filename;
                $updateData['file_name'] = $originalName;
                $updateData['file_size'] = $file['size'];
            } else {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.file_upload_failed', [], 500);
                return;
            }
        }
        
        if (empty($updateData)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
            return;
        }
        
        $result = $this->medicalReportService->update($reportId, $updateData);
        
        if ($result) {
            $this->toastNotificationService->sendApiResponse('success', 'notifications.success.medical_report_updated', [], 200);
        } else {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.update_failed', [], 500);
        }
    }
    
    /**
     * Delete medical report
     */
    public function deleteMedicalReport($reportId = null) {
        $this->requirePermission('staff.edit');
        
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $requestData = \App\Core\RequestParser::getRequestData();
        $reportId = $reportId ?? $queryParams['id'] ?? $requestData['id'] ?? '';
        
        if (empty($reportId)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
            return;
        }
        
        // Get report to delete file
        $report = $this->medicalReportService->findById($reportId);
        
        $result = $this->medicalReportService->delete($reportId);
        
        if ($result) {
            // Delete file
            if ($report && isset($report['file_path'])) {
                $filePath = __DIR__ . '/../..' . $report['file_path'];
                @unlink($filePath);
            }
            
            $this->toastNotificationService->sendApiResponse('success', 'notifications.success.medical_report_deleted', [], 200);
        } else {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.delete_failed', [], 500);
        }
    }
    
    /**
     * Download medical report
     */
    public function downloadMedicalReport($reportId = null) {
        $this->requirePermission('staff.view');
        
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $reportId = $reportId ?? $queryParams['id'] ?? '';
        
        if (empty($reportId)) {
            header('Location: ' . BASE_URL . '/admin/users');
            exit;
        }
        
        $report = $this->medicalReportService->findById($reportId);
        
        if (!$report || !isset($report['file_path'])) {
            $this->toastNotificationService->setFlash('error', 'notifications.error.not_found');
            header('Location: ' . BASE_URL . '/admin/users');
            exit;
        }
        
        $filePath = __DIR__ . '/../..' . $report['file_path'];
        
        if (!file_exists($filePath)) {
            $this->toastNotificationService->setFlash('error', 'notifications.error.not_found');
            header('Location: ' . BASE_URL . '/admin/users');
            exit;
        }
        
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $report['file_name'] . '"');
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
        exit;
    }
    
    /**
     * Test email sending
     * POST: api/admin/email/test
     */
    public function testEmail() {
        $this->requirePermission('settings.edit');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_request', [], 405);
            return;
        }
        
        $requestData = \App\Core\RequestParser::getRequestData();
        $email = $requestData['email'] ?? '';
        if (empty($email)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.warning.missing_fields', [], 400);
            return;
        }
        
        try {
            $emailService = \App\Core\DependencyFactory::getEmailService();
            $result = $emailService->testEmail($email);
            $this->apiResponse($result);
        } catch (\Exception $e) {
            \App\Core\Logger::error('Test email error: ' . $e->getMessage());
            $this->apiResponse([
                'success' => false,
                'message' => 'Test emaili gönderilirken bir hata oluştu: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get email service status
     * GET: api/admin/email/status
     */
    public function getEmailStatus() {
        $this->requirePermission('settings.view');
        
        try {
            $emailService = \App\Core\DependencyFactory::getEmailService();
            $status = $emailService->getStatus();
            $this->apiResponse($status);
        } catch (\Exception $e) {
            \App\Core\Logger::error('Email status error: ' . $e->getMessage());
            $this->apiResponse([
                'error' => 'Email durumu alınırken bir hata oluştu: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Enable 2FA for current user
     * POST: api/admin/2fa/enable
     */
    public function enable2FA() {
        $this->requirePermission('settings.edit');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_request', [], 400);
            return;
        }
        
        $requestData = \App\Core\RequestParser::getRequestData();
        $method = $requestData['method'] ?? '';
        $secretCode = $requestData['secret_code'] ?? '';
        $userId = $_SESSION['user_id'] ?? '';
        
        if (empty($method) || empty($secretCode) || empty($userId)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.warning.missing_fields', [], 400);
            return;
        }
        
        try {
            $twoFactorAuthService = \App\Core\DependencyFactory::getTwoFactorAuthService();
            $result = $twoFactorAuthService->enable2FA($userId, $method, $secretCode);
            
            if ($result['success']) {
                $this->apiResponse($result);
            } else {
                $this->apiResponse($result, 400);
            }
        } catch (\Exception $e) {
            \App\Core\Logger::error('Enable 2FA error: ' . $e->getMessage());
            $this->apiResponse([
                'error' => '2FA aktifleştirilirken bir hata oluştu: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Disable 2FA for current user
     * POST: api/admin/2fa/disable
     */
    public function disable2FA() {
        $this->requirePermission('settings.edit');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_request', [], 400);
            return;
        }
        
        $requestData = \App\Core\RequestParser::getRequestData();
        $method = $requestData['method'] ?? '';
        $userId = $_SESSION['user_id'] ?? '';
        
        if (empty($method) || empty($userId)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.warning.missing_fields', [], 400);
            return;
        }
        
        try {
            $twoFactorAuthService = \App\Core\DependencyFactory::getTwoFactorAuthService();
            $result = $twoFactorAuthService->disable2FA($userId, $method);
            
            if ($result['success']) {
                $this->apiResponse($result);
            } else {
                $this->apiResponse($result, 400);
            }
        } catch (\Exception $e) {
            \App\Core\Logger::error('Disable 2FA error: ' . $e->getMessage());
            $this->apiResponse([
                'error' => '2FA kapatılırken bir hata oluştu: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Send 2FA verification code
     * POST: api/admin/2fa/send-code
     */
    public function send2FACode() {
        $this->requirePermission('settings.edit');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_request', [], 400);
            return;
        }
        
        $requestData = \App\Core\RequestParser::getRequestData();
        $method = $requestData['method'] ?? '';
        $userId = $_SESSION['user_id'] ?? '';
        
        if (empty($method) || empty($userId)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.warning.missing_fields', [], 400);
            return;
        }
        
        try {
            $twoFactorAuthService = \App\Core\DependencyFactory::getTwoFactorAuthService();
            $result = $twoFactorAuthService->sendVerificationCode($userId, $method);
            
            if ($result['success']) {
                $this->apiResponse($result);
            } else {
                $this->apiResponse($result, 400);
            }
        } catch (\Exception $e) {
            \App\Core\Logger::error('Send 2FA code error: ' . $e->getMessage());
            $this->apiResponse([
                'error' => 'Kod gönderilirken bir hata oluştu: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Verify 2FA code
     * POST: api/admin/2fa/verify
     */
    public function verify2FA() {
        $this->requirePermission('settings.edit');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_request', [], 400);
            return;
        }
        
        $requestData = \App\Core\RequestParser::getRequestData();
        $code = $requestData['code'] ?? '';
        $method = $requestData['method'] ?? '';
        $userId = $_SESSION['user_id'] ?? '';
        
        if (empty($code) || empty($method) || empty($userId)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.warning.missing_fields', [], 400);
            return;
        }
        
        try {
            $twoFactorAuthService = \App\Core\DependencyFactory::getTwoFactorAuthService();
            $result = $twoFactorAuthService->verifyCode($userId, $code, $method);
            
            if ($result['success']) {
                $this->apiResponse($result);
            } else {
                $this->apiResponse($result, 400);
            }
        } catch (\Exception $e) {
            \App\Core\Logger::error('Verify 2FA code error: ' . $e->getMessage());
            $this->apiResponse([
                'error' => 'Kod doğrulanırken bir hata oluştu: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function features() {
        if (!$this->hasPermission('settings.edit')) {
            $this->toastNotificationService->setFlash('error', 'notifications.error.unauthorized');
            header('Location: ' . BASE_URL . '/admin');
            exit;
        }

        $featureService = \App\Core\DependencyFactory::getFeatureService();
        $features = $featureService->getAll();

        $data = [
            'features' => $features
        ];

        $this->view('admin/features', $data);
    }

    public function toggleFeature() {
        if (!$this->hasPermission('settings.edit')) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 401);
            return;
        }

        $requestData = \App\Core\RequestParser::getRequestData();
        $featureKey = $requestData['feature_key'] ?? '';
        $enabled = isset($requestData['enabled']) ? (bool)$requestData['enabled'] : false;

        if (empty($featureKey)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
            return;
        }

        $featureService = \App\Core\DependencyFactory::getFeatureService();
        $result = $featureService->updateStatus($featureKey, $enabled);

        if ($result) {
            $this->apiResponse(['success' => true]);
        } else {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.update_failed', [], 500);
        }
    }

    public function paymentGateways() {
        if (!$this->hasPermission('settings.edit')) {
            $this->toastNotificationService->setFlash('error', 'notifications.error.unauthorized');
            header('Location: ' . BASE_URL . '/admin');
            exit;
        }

        $paymentGatewayRepository = \App\Core\DependencyFactory::getPaymentGatewayRepository();
        $gateways = $paymentGatewayRepository->getAll();

        $data = [
            'gateways' => $gateways
        ];

        $this->view('admin/payment-gateways', $data);
    }

    public function updatePaymentGateway() {
        if (!$this->hasPermission('settings.edit')) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 401);
            return;
        }

        $requestData = \App\Core\RequestParser::getRequestData();
        $gatewayId = $requestData['gateway_id'] ?? '';
        $config = $requestData['config'] ?? [];

        if (empty($gatewayId)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
            return;
        }

        $paymentGatewayRepository = \App\Core\DependencyFactory::getPaymentGatewayRepository();
        $result = $paymentGatewayRepository->updateConfig($gatewayId, $config);

        if ($result) {
            // Reload gateways
            $paymentGatewayService = \App\Core\DependencyFactory::getPaymentGatewayService();
            $paymentGatewayService->reloadGateways();
            
            $this->apiResponse(['success' => true]);
        } else {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.update_failed', [], 500);
        }
    }

    public function posDevices() {
        if (!$this->hasPermission('settings.edit')) {
            $this->toastNotificationService->setFlash('error', 'notifications.error.unauthorized');
            header('Location: ' . BASE_URL . '/admin');
            exit;
        }

        $posDeviceRepository = \App\Core\DependencyFactory::getPOSDeviceRepository();
        $devices = $posDeviceRepository->getAll();

        $data = [
            'devices' => $devices
        ];

        $this->view('admin/pos-devices', $data);
    }

    public function updatePOSDevice() {
        if (!$this->hasPermission('settings.edit')) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 401);
            return;
        }

        $requestData = \App\Core\RequestParser::getRequestData();
        $deviceId = $requestData['device_id'] ?? '';
        $isEnabled = isset($requestData['is_enabled']) ? (bool)$requestData['is_enabled'] : null;

        if (empty($deviceId)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
            return;
        }

        $posDeviceRepository = \App\Core\DependencyFactory::getPOSDeviceRepository();
        
        if ($isEnabled !== null) {
            $result = $posDeviceRepository->updateStatus($deviceId, $isEnabled);
        } else {
            // Update other fields
            $updateData = [];
            if (isset($requestData['serial_port'])) $updateData['serial_port'] = $requestData['serial_port'];
            if (isset($requestData['network_host'])) $updateData['network_host'] = $requestData['network_host'];
            if (isset($requestData['network_port'])) $updateData['network_port'] = intval($requestData['network_port']);
            if (isset($requestData['api_endpoint'])) $updateData['api_endpoint'] = $requestData['api_endpoint'];
            if (isset($requestData['api_key'])) $updateData['api_key'] = $requestData['api_key'];
            
            $result = $posDeviceRepository->update($deviceId, $updateData);
        }

        if ($result) {
            // Reload devices
            $posDeviceService = \App\Core\DependencyFactory::getPOSDeviceService();
            $posDeviceService->reloadDevices();
            
            $this->apiResponse(['success' => true]);
        } else {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.update_failed', [], 500);
        }
    }

    public function testPOSDevice() {
        if (!$this->hasPermission('settings.edit')) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 401);
            return;
        }

        $requestData = \App\Core\RequestParser::getRequestData();
        $deviceId = $requestData['device_id'] ?? '';

        if (empty($deviceId)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
            return;
        }

        $posDeviceService = \App\Core\DependencyFactory::getPOSDeviceService();
        $result = $posDeviceService->testDevice($deviceId);

        $this->apiResponse($result);
    }
    
    public function addPOSDevice() {
        if (!$this->hasPermission('settings.edit')) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 401);
            return;
        }
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_request', [], 405);
            return;
        }
        
        $requestData = \App\Core\RequestParser::getRequestData();
        
        $deviceName = sanitizeInput($requestData['device_name'] ?? '');
        $deviceType = sanitizeInput($requestData['device_type'] ?? 'POS');
        $connectionType = sanitizeInput($requestData['connection_type'] ?? 'serial');
        
        if (empty($deviceName)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.warning.missing_fields', [], 400);
            return;
        }
        
        // Validate connection type
        $allowedConnectionTypes = ['serial', 'network', 'api'];
        if (!in_array($connectionType, $allowedConnectionTypes)) {
            $this->toastNotificationService->sendApiResponse('error', 'Geçersiz bağlantı tipi', [], 400);
            return;
        }
        
        $deviceData = [
            'device_name' => $deviceName,
            'device_type' => $deviceType,
            'connection_type' => $connectionType,
            'is_enabled' => 1
        ];
        
        // Add connection-specific fields
        if ($connectionType === 'serial') {
            $deviceData['serial_port'] = sanitizeInput($requestData['serial_port'] ?? '');
        } elseif ($connectionType === 'network') {
            $deviceData['network_host'] = sanitizeInput($requestData['network_host'] ?? '');
            $deviceData['network_port'] = intval($requestData['network_port'] ?? 9100);
        } elseif ($connectionType === 'api') {
            $deviceData['api_endpoint'] = sanitizeInput($requestData['api_endpoint'] ?? '');
            $deviceData['api_key'] = sanitizeInput($requestData['api_key'] ?? '');
        }
        
        $posDeviceService = \App\Core\DependencyFactory::getPOSDeviceService();
        $deviceId = $posDeviceService->addDevice($deviceData);
        
        if ($deviceId) {
            $this->apiResponse([
                'success' => true,
                'device_id' => $deviceId
            ]);
        } else {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.create_failed', [], 500);
        }
    }
    
    /**
     * Add all missing permissions to database and assign them to appropriate roles
     * This method adds all permissions if they don't exist and assigns them to roles
     */
    public function addPrinterPermissions() {
        // Allow this to run without permission check for initial setup
        // $this->requirePermission('permissions.manage');
        
        try {
            // ALL permissions that should exist in the system
            $allPermissions = [
                // Printers
                ['permission_key' => 'printers.view', 'permission_name' => 'View printers', 'description' => 'View printers'],
                ['permission_key' => 'printers.create', 'permission_name' => 'Create printers', 'description' => 'Create printers'],
                ['permission_key' => 'printers.edit', 'permission_name' => 'Edit printers', 'description' => 'Edit printers'],
                ['permission_key' => 'printers.delete', 'permission_name' => 'Delete printers', 'description' => 'Delete printers'],
                ['permission_key' => 'printers.test', 'permission_name' => 'Test printers', 'description' => 'Test printer connections'],
                
                // Roles & Permissions (if missing)
                ['permission_key' => 'roles.view', 'permission_name' => 'View roles', 'description' => 'View roles'],
                ['permission_key' => 'roles.create', 'permission_name' => 'Create roles', 'description' => 'Create roles'],
                ['permission_key' => 'roles.edit', 'permission_name' => 'Edit roles', 'description' => 'Edit roles'],
                ['permission_key' => 'roles.delete', 'permission_name' => 'Delete roles', 'description' => 'Delete roles'],
                ['permission_key' => 'permissions.view', 'permission_name' => 'View permissions', 'description' => 'View permissions'],
                ['permission_key' => 'permissions.manage', 'permission_name' => 'Manage permissions', 'description' => 'Manage permissions'],
            ];
            
            $addedCount = 0;
            $skippedCount = 0;
            
            // Add all permissions to database
            foreach ($allPermissions as $permission) {
                $existing = $this->permissionModel->getByKey($permission['permission_key']);
                
                if ($existing) {
                    $skippedCount++;
                } else {
                    try {
                        $this->permissionModel->create($permission);
                        $addedCount++;
                    } catch (\Exception $e) {
                        \App\Core\Logger::error("Error adding permission {$permission['permission_key']}: " . $e->getMessage());
                    }
                }
            }
            
            // Role-permission mappings
            $rolePermissions = [
                'ROLE_MANAGER' => [
                    'printers.view', 'printers.create', 'printers.edit', 'printers.delete', 'printers.test',
                    'roles.view', 'roles.create', 'roles.edit', 'roles.delete',
                    'permissions.view', 'permissions.manage'
                ]
            ];
            
            $totalAssigned = 0;
            $totalSkipped = 0;
            
            // Assign permissions to roles
            foreach ($rolePermissions as $roleCode => $permissionKeys) {
                $role = $this->roleService->getByRoleCode($roleCode);
                
                if (!$role) {
                    continue;
                }
                
                $roleId = $role['role_id'];
                
                foreach ($permissionKeys as $permissionKey) {
                    $perm = $this->permissionModel->getByKey($permissionKey);
                    
                    if (!$perm) {
                        continue;
                    }
                    
                    $permissionId = $perm['permission_id'];
                    $rolePerms = $this->roleService->getRolePermissionKeys($roleId);
                    
                    if (in_array($permissionKey, $rolePerms)) {
                        $totalSkipped++;
                    } else {
                        try {
                            $this->permissionModel->assignToRole($roleId, $permissionId);
                            $totalAssigned++;
                        } catch (\Exception $e) {
                            \App\Core\Logger::error("Error assigning permission {$permissionKey}: " . $e->getMessage());
                        }
                    }
                }
            }
            
            $this->apiResponse([
                'success' => true,
                'message' => 'Tüm permission\'lar başarıyla eklendi ve atandı',
                'added_permissions' => $addedCount,
                'skipped_permissions' => $skippedCount,
                'assigned_permissions' => $totalAssigned,
                'already_assigned' => $totalSkipped
            ]);
            
        } catch (\Exception $e) {
            \App\Core\Logger::error("Error in addPrinterPermissions: " . $e->getMessage());
            $this->toastNotificationService->sendApiResponse('error', 'Hata: ' . $e->getMessage(), [], 500);
        }
    }
    
    /**
     * Create database backup before system reset
     * @return array ['success' => bool, 'filename' => string, 'error' => string]
     */
    private function createDatabaseBackup(): array {
        try {
            // Parse host and port from DB_HOST (format: host:port or just host)
            $dbHost = $_ENV['DB_HOST'] ?? 'localhost';
            $dbPort = $_ENV['DB_PORT'] ?? '3306';
            
            // Check if port is in host string (format: localhost:3306)
            if (strpos($dbHost, ':') !== false) {
                list($dbHost, $dbPort) = explode(':', $dbHost, 2);
            }
            
            $dbConfig = [
                'host' => trim($dbHost),
                'port' => trim($dbPort),
                'name' => $_ENV['DB_NAME'] ?? '',
                'user' => $_ENV['DB_USER'] ?? '',
                'pass' => $_ENV['DB_PASS'] ?? '',
            ];
            
            if (empty($dbConfig['name']) || empty($dbConfig['user'])) {
                return ['success' => false, 'error' => 'Veritabanı bilgileri eksik'];
            }
            
            // Create backup directory in root
            $backupDir = __DIR__ . '/../../backups';
            if (!is_dir($backupDir)) {
                mkdir($backupDir, 0755, true);
            }
            
            // Generate backup filename with timestamp
            $timestamp = date('Y-m-d_H-i-s');
            $backupFilename = "db_backup_{$dbConfig['name']}_{$timestamp}.sql";
            $backupPath = $backupDir . '/' . $backupFilename;
            
            // Build mysqldump command with separate host and port
            $host = escapeshellarg($dbConfig['host']);
            $port = escapeshellarg($dbConfig['port']);
            $user = escapeshellarg($dbConfig['user']);
            $pass = escapeshellarg($dbConfig['pass']);
            $db = escapeshellarg($dbConfig['name']);
            
            // Try to find mysqldump or mariadb-dump
            $dumpCommand = null;
            $commands = ['/usr/bin/mariadb-dump', '/usr/bin/mysqldump', 'mariadb-dump', 'mysqldump'];
            
            foreach ($commands as $cmd) {
                $fullPath = trim(shell_exec("which {$cmd} 2>/dev/null"));
                if (!empty($fullPath) && is_executable($fullPath)) {
                    $dumpCommand = $fullPath;
                    break;
                }
                // Also try direct path
                if (file_exists($cmd) && is_executable($cmd)) {
                    $dumpCommand = $cmd;
                    break;
                }
            }
            
            if (!$dumpCommand) {
                // Fallback: try direct execution
                $dumpCommand = '/usr/bin/mariadb-dump';
            }
            
            // Execute backup command - redirect stderr to stdout to capture errors
            // Use -P for port (uppercase P for port, lowercase p for password)
            // IMPORTANT: Order matters - redirect output first, then stderr
            $command = "{$dumpCommand} -h {$host} -P {$port} -u {$user} -p{$pass} {$db} > " . escapeshellarg($backupPath) . " 2>&1";
            
            // Log command for debugging (without password)
            $logCommand = "{$dumpCommand} -h {$host} -P {$port} -u {$user} -p*** {$db} > " . escapeshellarg($backupPath) . " 2>&1";
            \App\Core\Logger::error("Database backup command: {$logCommand}");
            
            exec($command, $output, $returnCode);
            
            // Log execution result
            \App\Core\Logger::error("Database backup exec result - return code: {$returnCode}, output lines: " . count($output));
            
            // Check if backup file was created and has content
            if (!file_exists($backupPath)) {
                $error = implode("\n", $output);
                \App\Core\Logger::error("Database backup failed - file not created: {$error}");
                return ['success' => false, 'error' => 'Yedek dosyası oluşturulamadı: ' . ($error ?: 'Bilinmeyen hata')];
            }
            
            // Check file size (should be more than just headers/errors)
            $fileSize = filesize($backupPath);
            if ($fileSize < 500) { // Less than 500 bytes is likely just error message
                $backupContent = file_get_contents($backupPath);
                // Check if it's just an error message
                if (stripos($backupContent, 'error') !== false || 
                    stripos($backupContent, 'access denied') !== false ||
                    stripos($backupContent, 'unknown server host') !== false ||
                    stripos($backupContent, 'mysqldump:') !== false) {
                    $error = trim($backupContent);
                    \App\Core\Logger::error("Database backup failed - contains errors: {$error}");
                    @unlink($backupPath); // Remove error file
                    return ['success' => false, 'error' => 'Yedek hatası: ' . substr($error, 0, 200)];
                }
            }
            
            // Check if backup contains error messages in output
            if (!empty($output)) {
                $outputText = implode("\n", $output);
                if (stripos($outputText, 'error') !== false || 
                    stripos($outputText, 'access denied') !== false ||
                    stripos($outputText, 'unknown server host') !== false) {
                    \App\Core\Logger::error("Database backup failed - command output contains errors: {$outputText}");
                    @unlink($backupPath);
                    return ['success' => false, 'error' => 'Yedek komutu hatası: ' . substr($outputText, 0, 200)];
                }
            }
            
            // If return code is not 0, check if file has meaningful content
            if ($returnCode !== 0 && $fileSize < 500) {
                $error = implode("\n", $output);
                if (empty($error)) {
                    $backupContent = file_get_contents($backupPath);
                    $error = $backupContent;
                }
                \App\Core\Logger::error("Database backup failed - return code {$returnCode}, file size: {$fileSize}");
                @unlink($backupPath);
                return ['success' => false, 'error' => 'Yedek başarısız (kod: ' . $returnCode . '): ' . substr($error, 0, 200)];
            }
            
            // Compress backup (optional but recommended)
            if (function_exists('gzencode')) {
                $compressedPath = $backupPath . '.gz';
                $backupContent = file_get_contents($backupPath);
                $compressed = gzencode($backupContent, 9);
                file_put_contents($compressedPath, $compressed);
                unlink($backupPath); // Remove uncompressed version
                $backupPath = $compressedPath;
                $backupFilename .= '.gz';
            }
            
            return [
                'success' => true,
                'filename' => $backupFilename,
                'path' => $backupPath,
                'size' => filesize($backupPath)
            ];
            
        } catch (\Exception $e) {
            \App\Core\Logger::error("Database backup exception: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Backup logo and favicon before system reset
     * @return array ['success' => bool, 'filename' => string, 'error' => string]
     */
    private function backupLogoAndFavicon(): array {
        try {
            $backupDir = __DIR__ . '/../../backups';
            if (!is_dir($backupDir)) {
                mkdir($backupDir, 0755, true);
            }
            
            $timestamp = date('Y-m-d_H-i-s');
            $backupFilename = "logo_favicon_backup_{$timestamp}.tar.gz";
            $backupPath = $backupDir . '/' . $backupFilename;
            
            $imagesDir = __DIR__ . '/../../public/assets/images';
            $logoPath = $imagesDir . '/logo.png';
            $faviconPath = $imagesDir . '/favicon.ico';
            
            $filesToBackup = [];
            if (file_exists($logoPath)) {
                $filesToBackup[] = $logoPath;
            }
            if (file_exists($faviconPath)) {
                $filesToBackup[] = $faviconPath;
            }
            
            // Also check for other logo/favicon formats
            $logoFormats = ['logo.jpg', 'logo.jpeg', 'logo.svg', 'logo.webp'];
            $faviconFormats = ['favicon.png', 'favicon.svg'];
            
            foreach ($logoFormats as $format) {
                $path = $imagesDir . '/' . $format;
                if (file_exists($path)) {
                    $filesToBackup[] = $path;
                }
            }
            
            foreach ($faviconFormats as $format) {
                $path = $imagesDir . '/' . $format;
                if (file_exists($path)) {
                    $filesToBackup[] = $path;
                }
            }
            
            if (empty($filesToBackup)) {
                return ['success' => false, 'error' => 'Logo veya favicon bulunamadı'];
            }
            
            // Create tar.gz archive
            $tempDir = sys_get_temp_dir() . '/logo_favicon_backup_' . uniqid();
            mkdir($tempDir, 0755, true);
            
            foreach ($filesToBackup as $file) {
                $basename = basename($file);
                copy($file, $tempDir . '/' . $basename);
            }
            
            // Try to use tar command if available
            $tarCommand = trim(shell_exec("which tar 2>/dev/null"));
            if (!empty($tarCommand) && is_executable($tarCommand)) {
                $command = "cd " . escapeshellarg($tempDir) . " && {$tarCommand} -czf " . escapeshellarg($backupPath) . " * 2>&1";
                exec($command, $output, $returnCode);
                
                // Cleanup temp directory
                array_map('unlink', glob("{$tempDir}/*"));
                rmdir($tempDir);
                
                if ($returnCode === 0 && file_exists($backupPath)) {
                    return [
                        'success' => true,
                        'filename' => $backupFilename,
                        'path' => $backupPath,
                        'size' => filesize($backupPath)
                    ];
                }
            }
            
            // Fallback: Create zip archive using PHP
            $zipPath = str_replace('.tar.gz', '.zip', $backupPath);
            $backupFilename = str_replace('.tar.gz', '.zip', $backupFilename);
            
            if (class_exists('ZipArchive')) {
                $zip = new \ZipArchive();
                if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === TRUE) {
                    foreach ($filesToBackup as $file) {
                        $zip->addFile($file, basename($file));
                    }
                    $zip->close();
                    
                    // Cleanup temp directory
                    array_map('unlink', glob("{$tempDir}/*"));
                    rmdir($tempDir);
                    
                    return [
                        'success' => true,
                        'filename' => $backupFilename,
                        'path' => $zipPath,
                        'size' => filesize($zipPath)
                    ];
                }
            }
            
            // Last fallback: Just copy files
            $backupDirFiles = $backupDir . '/logo_favicon_' . $timestamp;
            mkdir($backupDirFiles, 0755, true);
            
            foreach ($filesToBackup as $file) {
                copy($file, $backupDirFiles . '/' . basename($file));
            }
            
            // Cleanup temp directory
            array_map('unlink', glob("{$tempDir}/*"));
            rmdir($tempDir);
            
            return [
                'success' => true,
                'filename' => 'logo_favicon_' . $timestamp . '/',
                'path' => $backupDirFiles,
                'size' => 0
            ];
            
        } catch (\Exception $e) {
            \App\Core\Logger::error("Logo/Favicon backup exception: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Delete all uploaded images except logo and favicon
     * @return void
     */
    private function deleteUploadedImages(): void {
        try {
            $uploadsDir = __DIR__ . '/../../public/uploads';
            
            if (!is_dir($uploadsDir)) {
                return; // Directory doesn't exist, nothing to delete
            }
            
            // Delete all files and directories in uploads folder
            $this->deleteDirectory($uploadsDir, true); // Keep directory structure but remove all content
            
        } catch (\Exception $e) {
            \App\Core\Logger::error("Error deleting uploaded images: " . $e->getMessage());
        }
    }
    
    /**
     * Recursively delete directory contents
     * @param string $dir Directory path
     * @param bool $keepDir Keep the directory itself, only delete contents
     * @return void
     */
    private function deleteDirectory(string $dir, bool $keepDir = false): void {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = array_diff(scandir($dir), ['.', '..', 'index.php']);
        
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            
            if (is_dir($path)) {
                $this->deleteDirectory($path, false);
            } else {
                @unlink($path);
            }
        }
        
        if (!$keepDir) {
            @rmdir($dir);
        }
    }
}