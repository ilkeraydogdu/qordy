<?php
namespace App\Controllers\SuperAdmin;

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../../core/DependencyFactory.php';

use App\Core\Controller;

class DashboardController extends Controller {
    
    public function index() {
        // Require super admin role
        $this->requireLogin();
        
        if (!$this->isSuperAdmin()) {
            header('Location: ' . BASE_URL . '/unauthorized');
            exit;
        }
        
        try {
            // Get statistics
            $customerService = \App\Core\DependencyFactory::getCustomerService();
            $subscriptionService = \App\Core\DependencyFactory::getSubscriptionService();
            $packageService = \App\Core\DependencyFactory::getPackageService();
            $db = \App\Core\DependencyFactory::getDatabase();
            
            // Total customers
            $totalCustomers = method_exists($customerService, 'getTotalCount') 
                ? $customerService->getTotalCount() 
                : $customerService->getTotalBusinessCount();

            // Active customers (is_active = 1)
            $activeCustomers = 0;
            try {
                $r = $db->query("SELECT COUNT(*) FROM customers WHERE is_active = 1")->fetchColumn();
                $activeCustomers = (int)$r;
            } catch (\Exception $e) {}

            // Active subscriptions
            $activeSubscriptions = method_exists($subscriptionService, 'getActiveSubscriptionsCount')
                ? $subscriptionService->getActiveSubscriptionsCount()
                : 0;

            // Monthly revenue (current month)
            $currentMonth = date('Y-m');
            $monthlyRevenue = method_exists($subscriptionService, 'getMonthlyRevenue')
                ? $subscriptionService->getMonthlyRevenue($currentMonth)
                : 0;

            // Recent customers (last 10)
            $recentCustomers = method_exists($customerService, 'getRecentCustomers')
                ? $customerService->getRecentCustomers(10)
                : [];

            // Package distribution
            $packageDistribution = method_exists($subscriptionService, 'getPackageDistribution')
                ? $subscriptionService->getPackageDistribution()
                : [];

            // ---- SaaS Revenue by period (from subscriptions table) ----
            $saasRevenue = ['daily' => 0, 'weekly' => 0, 'monthly' => 0, 'yearly' => 0,
                            'paid_active' => 0, 'trial_active' => 0];
            try {
                $r = $db->query("SELECT
                    SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY) AND is_trial=0 THEN COALESCE(amount,0) ELSE 0 END) as daily,
                    SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) AND is_trial=0 THEN COALESCE(amount,0) ELSE 0 END) as weekly,
                    SUM(CASE WHEN DATE_FORMAT(created_at,'%Y-%m')=DATE_FORMAT(NOW(),'%Y-%m') AND is_trial=0 THEN COALESCE(amount,0) ELSE 0 END) as monthly,
                    SUM(CASE WHEN YEAR(created_at)=YEAR(NOW()) AND is_trial=0 THEN COALESCE(amount,0) ELSE 0 END) as yearly,
                    COUNT(CASE WHEN status='active' AND is_trial=0 THEN 1 END) as paid_active,
                    COUNT(CASE WHEN status='active' AND is_trial=1 THEN 1 END) as trial_active
                    FROM subscriptions")->fetch(\PDO::FETCH_ASSOC);
                $saasRevenue = [
                    'daily'        => (float)($r['daily'] ?? 0),
                    'weekly'       => (float)($r['weekly'] ?? 0),
                    'monthly'      => (float)($r['monthly'] ?? 0),
                    'yearly'       => (float)($r['yearly'] ?? 0),
                    'paid_active'  => (int)($r['paid_active'] ?? 0),
                    'trial_active' => (int)($r['trial_active'] ?? 0),
                ];
            } catch (\Exception $e) {}

            // ---- Package sales counts ----
            $packageSales = [];
            try {
                $ps = $db->query("SELECT p.name, COUNT(s.id) as sold, SUM(COALESCE(s.amount,0)) as revenue
                    FROM subscriptions s
                    LEFT JOIN packages p ON s.package_id = p.package_id
                    WHERE s.is_trial = 0
                    GROUP BY s.package_id, p.name
                    ORDER BY revenue DESC")->fetchAll(\PDO::FETCH_ASSOC);
                $packageSales = $ps;
            } catch (\Exception $e) {}

            // ---- All businesses with logos for the card grid ----
            $allBusinessesForGrid = [];
            try {
                $bg = $db->query("SELECT c.customer_id, c.company_name, c.logo_path, c.subdomain,
                    c.is_active, c.created_at,
                    s.status as sub_status, s.is_trial,
                    s.trial_ends_at, s.current_period_end,
                    p.name as package_name
                    FROM customers c
                    LEFT JOIN subscriptions s ON s.tenant_id = c.customer_id AND s.status = 'active'
                    LEFT JOIN packages p ON s.package_id = p.package_id
                    ORDER BY c.created_at DESC
                    LIMIT 50")->fetchAll(\PDO::FETCH_ASSOC);
                $allBusinessesForGrid = $bg;
            } catch (\Exception $e) {}
            
            // === ENHANCED STATISTICS FOR SUPER ADMIN ===
            
            // Total orders across all businesses (last 30 days)
            $totalOrders = 0;
            try {
                $ordersStmt = $db->query("
                    SELECT COUNT(*) as total 
                    FROM orders 
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                ");
                $ordersResult = $ordersStmt->fetch(\PDO::FETCH_ASSOC);
                $totalOrders = (int)($ordersResult['total'] ?? 0);
            } catch (\Exception $e) {
                // Ignore if orders table doesn't exist or error
            }
            
            // Total revenue across all businesses (last 30 days) - only paid orders
            $totalRevenue = 0;
            try {
                $revenueStmt = $db->query("
                    SELECT COALESCE(SUM(total_amount), 0) as total 
                    FROM orders 
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                    AND status != 'CANCELLED'
                    AND (is_paid = 1 OR status = 'SERVED')
                ");
                $revenueResult = $revenueStmt->fetch(\PDO::FETCH_ASSOC);
                $totalRevenue = (float)($revenueResult['total'] ?? 0);
            } catch (\Exception $e) {
                // Ignore if orders table doesn't exist or error
            }
            
            // Active businesses (with orders in last 30 days)
            $activeBusinesses = 0;
            try {
                $activeStmt = $db->query("
                    SELECT COUNT(DISTINCT COALESCE(business_id, tenant_id)) as total 
                    FROM orders 
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                    AND (business_id IS NOT NULL OR tenant_id IS NOT NULL)
                ");
                $activeResult = $activeStmt->fetch(\PDO::FETCH_ASSOC);
                $activeBusinesses = (int)($activeResult['total'] ?? 0);
            } catch (\Exception $e) {
                // Ignore if orders table doesn't exist or error
            }
            
            // Top performing businesses (by revenue, last 30 days)
            $topBusinesses = [];
            try {
                $topStmt = $db->query("
                    SELECT 
                        c.customer_id,
                        c.company_name,
                        c.email,
                        COUNT(o.order_id) as order_count,
                        COALESCE(SUM(o.total_amount), 0) as revenue
                    FROM customers c
                    LEFT JOIN orders o ON c.customer_id = o.tenant_id
                        AND o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                        AND o.status != 'CANCELLED'
                        AND (o.is_paid = 1 OR o.status = 'SERVED')
                    GROUP BY c.customer_id, c.company_name, c.email
                    ORDER BY revenue DESC, order_count DESC
                    LIMIT 10
                ");
                $topBusinesses = $topStmt->fetchAll(\PDO::FETCH_ASSOC);
            } catch (\Exception $e) {
                // Ignore if error
            }
            
            // Growth metrics (new customers this month vs last month)
            $newCustomersThisMonth = 0;
            $newCustomersLastMonth = 0;
            try {
                $thisMonthStmt = $db->query("
                    SELECT COUNT(*) as total 
                    FROM customers 
                    WHERE DATE_FORMAT(created_at, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m')
                ");
                $thisMonthResult = $thisMonthStmt->fetch(\PDO::FETCH_ASSOC);
                $newCustomersThisMonth = (int)($thisMonthResult['total'] ?? 0);
                
                $lastMonthStmt = $db->query("
                    SELECT COUNT(*) as total 
                    FROM customers 
                    WHERE DATE_FORMAT(created_at, '%Y-%m') = DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 1 MONTH), '%Y-%m')
                ");
                $lastMonthResult = $lastMonthStmt->fetch(\PDO::FETCH_ASSOC);
                $newCustomersLastMonth = (int)($lastMonthResult['total'] ?? 0);
            } catch (\Exception $e) {
                // Ignore if error
            }
            
            // Revenue trend (last 7 days)
            $revenueTrend = [];
            try {
                $trendStmt = $db->query("
                    SELECT 
                        DATE(created_at) as date,
                        COALESCE(SUM(total_amount), 0) as revenue,
                        COUNT(*) as order_count
                    FROM orders 
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                    AND status != 'CANCELLED'
                    AND (is_paid = 1 OR status = 'SERVED')
                    GROUP BY DATE(created_at)
                    ORDER BY date ASC
                ");
                $revenueTrend = $trendStmt->fetchAll(\PDO::FETCH_ASSOC);
            } catch (\Exception $e) {
                // Ignore if error
            }
            
            // === MONITORING DATA - Real-time business statistics ===
            $businessesMonitoring = [];
            try {
                // Get all businesses with their real-time stats
                $customerService = \App\Core\DependencyFactory::getCustomerService();
                $allBusinesses = $customerService->getAllBusinesses();
                
                foreach ($allBusinesses as $business) {
                    $businessId = $business['customer_id'] ?? null;
                    if (!$businessId) continue;
                    
                    $monitoringData = [
                        'business_id' => $businessId,
                        'company_name' => $business['company_name'] ?? 'Adsız İşletme',
                        'email' => $business['email'] ?? '',
                        'revenue_today' => 0,
                        'revenue_month' => 0,
                        'orders_today' => 0,
                        'orders_month' => 0,
                        'total_staff' => 0,
                        'total_tables' => 0,
                        'active_tables' => 0,
                        'occupied_tables' => 0,
                        'available_tables' => 0
                    ];
                    
                    // Get today's revenue and orders (only paid)
                    try {
                        $todayStmt = $db->prepare("
                            SELECT 
                                COUNT(*) as order_count,
                                COALESCE(SUM(total_amount), 0) as revenue
                            FROM orders 
                            WHERE tenant_id = :tid
                            AND DATE(created_at) = CURDATE()
                            AND status != 'CANCELLED'
                            AND (is_paid = 1 OR status = 'SERVED')
                        ");
                        $todayStmt->execute(['tid' => $businessId]);
                        $todayData = $todayStmt->fetch(\PDO::FETCH_ASSOC);
                        $monitoringData['orders_today'] = (int)($todayData['order_count'] ?? 0);
                        $monitoringData['revenue_today'] = (float)($todayData['revenue'] ?? 0);
                    } catch (\Exception $e) {
                        // Ignore
                    }
                    
                    // Get month's revenue and orders (only paid)
                    try {
                        $monthStmt = $db->prepare("
                            SELECT 
                                COUNT(*) as order_count,
                                COALESCE(SUM(total_amount), 0) as revenue
                            FROM orders 
                            WHERE tenant_id = :tid
                            AND DATE_FORMAT(created_at, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m')
                            AND status != 'CANCELLED'
                            AND (is_paid = 1 OR status = 'SERVED')
                        ");
                        $monthStmt->execute(['tid' => $businessId]);
                        $monthData = $monthStmt->fetch(\PDO::FETCH_ASSOC);
                        $monitoringData['orders_month'] = (int)($monthData['order_count'] ?? 0);
                        $monitoringData['revenue_month'] = (float)($monthData['revenue'] ?? 0);
                    } catch (\Exception $e) {
                        // Ignore
                    }
                    
                    // Get staff count
                    try {
                        $staffStmt = $db->prepare("
                            SELECT COUNT(*) as staff_count
                            FROM users 
                            WHERE tenant_id = :tenant_id
                        ");
                        $staffStmt->execute(['tenant_id' => $businessId]);
                        $staffData = $staffStmt->fetch(\PDO::FETCH_ASSOC);
                        $monitoringData['total_staff'] = (int)($staffData['staff_count'] ?? 0);
                    } catch (\Exception $e) {
                        // Ignore
                    }
                    
                    // Get table statistics
                    try {
                        $tableStmt = $db->prepare("
                            SELECT 
                                COUNT(*) as total_tables,
                                SUM(CASE WHEN status = 'OCCUPIED' THEN 1 ELSE 0 END) as occupied_tables,
                                SUM(CASE WHEN status IN ('OCCUPIED', 'PAYMENT_PENDING') THEN 1 ELSE 0 END) as active_tables,
                                SUM(CASE WHEN status = 'AVAILABLE' THEN 1 ELSE 0 END) as available_tables
                            FROM tables 
                            WHERE tenant_id = :business_id
                        ");
                        $tableStmt->execute(['business_id' => $businessId]);
                        $tableData = $tableStmt->fetch(\PDO::FETCH_ASSOC);
                        $monitoringData['total_tables'] = (int)($tableData['total_tables'] ?? 0);
                        $monitoringData['occupied_tables'] = (int)($tableData['occupied_tables'] ?? 0);
                        $monitoringData['active_tables'] = (int)($tableData['active_tables'] ?? 0);
                        $monitoringData['available_tables'] = (int)($tableData['available_tables'] ?? 0);
                    } catch (\Exception $e) {
                        // Ignore
                    }
                    
                    $businessesMonitoring[] = $monitoringData;
                }
                
                // Sort by today's revenue (descending)
                usort($businessesMonitoring, function($a, $b) {
                    return $b['revenue_today'] <=> $a['revenue_today'];
                });
            } catch (\Exception $e) {
                // Ignore if error
            }
            
            // Calculate totals
            $totalStaff = array_sum(array_column($businessesMonitoring, 'total_staff'));
            $totalTables = array_sum(array_column($businessesMonitoring, 'total_tables'));
            $totalOccupiedTables = array_sum(array_column($businessesMonitoring, 'occupied_tables'));
            $totalActiveTables = array_sum(array_column($businessesMonitoring, 'active_tables'));
            $totalRevenueToday = array_sum(array_column($businessesMonitoring, 'revenue_today'));
            $totalOrdersToday = array_sum(array_column($businessesMonitoring, 'orders_today'));
            
            $data = [
                'totalCustomers'        => $totalCustomers,
                'activeCustomers'       => $activeCustomers,
                'activeSubscriptions'   => $activeSubscriptions,
                'monthlyRevenue'        => $monthlyRevenue,
                'recentCustomers'       => $recentCustomers,
                'packageDistribution'   => $packageDistribution,
                // SaaS metrics
                'saasRevenue'           => $saasRevenue,
                'packageSales'          => $packageSales,
                'allBusinessesForGrid'  => $allBusinessesForGrid,
                // Enhanced statistics
                'totalOrders'           => $totalOrders,
                'totalRevenue'          => $totalRevenue,
                'activeBusinesses'      => $activeBusinesses,
                'topBusinesses'         => $topBusinesses,
                'newCustomersThisMonth' => $newCustomersThisMonth,
                'newCustomersLastMonth' => $newCustomersLastMonth,
                'revenueTrend'          => $revenueTrend,
                // Monitoring data
                'businessesMonitoring'  => $businessesMonitoring,
                'totalStaff'            => $totalStaff,
                'totalTables'           => $totalTables,
                'totalOccupiedTables'   => $totalOccupiedTables,
                'totalActiveTables'     => $totalActiveTables,
                'totalRevenueToday'     => $totalRevenueToday,
                'totalOrdersToday'      => $totalOrdersToday,
                'page'                  => 'superadmin-dashboard'
            ];
            
            $this->view('superadmin/dashboard', $data);
            
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('SuperAdmin Dashboard error', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
            
            // Fallback with empty data
            $data = [
                'totalCustomers' => 0,
                'activeSubscriptions' => 0,
                'monthlyRevenue' => 0,
                'recentCustomers' => [],
                'packageDistribution' => [],
                'page' => 'superadmin-dashboard'
            ];
            
            $this->view('superadmin/dashboard', $data);
        }
    }
}
