<?php
namespace App\Controllers\SuperAdmin;

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../../core/DependencyFactory.php';

use App\Core\Controller;

class AnalyticsController extends Controller {
    
    public function index() {
        // Require super admin role
        $this->requireLogin();
        
        if (!$this->isSuperAdmin()) {
            header('Location: ' . BASE_URL . '/unauthorized');
            exit;
        }
        
        try {
            $db = \App\Core\DependencyFactory::getDatabase();
            $subscriptionService = \App\Core\DependencyFactory::getSubscriptionService();
            $customerService = \App\Core\DependencyFactory::getCustomerService();
            
            // Get monthly revenue for last 12 months
            $monthlyRevenue = [];
            for ($i = 11; $i >= 0; $i--) {
                $month = date('Y-m', strtotime("-$i months"));
                $revenue = method_exists($subscriptionService, 'getMonthlyRevenue')
                    ? $subscriptionService->getMonthlyRevenue($month)
                    : 0;
                $monthlyRevenue[$month] = $revenue;
            }
            
            // Get customer growth for last 12 months
            $customerGrowth = [];
            for ($i = 11; $i >= 0; $i--) {
                $month = date('Y-m', strtotime("-$i months"));
                try {
                    $stmt = $db->prepare("SELECT COUNT(*) as count FROM customers WHERE DATE_FORMAT(created_at, '%Y-%m') <= ?");
                    $stmt->execute([$month]);
                    $result = $stmt->fetch(\PDO::FETCH_ASSOC);
                    $customerGrowth[$month] = (int)($result['count'] ?? 0);
                } catch (\Exception $e) {
                    $customerGrowth[$month] = 0;
                }
            }
            
            // Calculate MRR (Monthly Recurring Revenue)
            $currentMonth = date('Y-m');
            $mrr = method_exists($subscriptionService, 'getMonthlyRevenue')
                ? $subscriptionService->getMonthlyRevenue($currentMonth)
                : 0;
            
            // Calculate churn rate (simplified - customers lost this month / total customers)
            $totalCustomers = method_exists($customerService, 'getTotalBusinessCount')
                ? $customerService->getTotalBusinessCount()
                : 0;
            
            $churnRate = 0;
            if ($totalCustomers > 0) {
                try {
                    // Get cancelled subscriptions this month
                    $stmt = $db->prepare("
                        SELECT COUNT(*) as count 
                        FROM subscriptions 
                        WHERE status = 'cancelled' 
                        AND DATE_FORMAT(updated_at, '%Y-%m') = ?
                    ");
                    $stmt->execute([$currentMonth]);
                    $result = $stmt->fetch(\PDO::FETCH_ASSOC);
                    $cancelled = (int)($result['count'] ?? 0);
                    $churnRate = ($cancelled / $totalCustomers) * 100;
                } catch (\Exception $e) {
                    $churnRate = 0;
                }
            }
            
            // Get total orders and revenue across all businesses
            $totalOrders = 0;
            $totalRevenue = 0;
            try {
                $stmt = $db->query("
                    SELECT 
                        COUNT(*) as order_count,
                        COALESCE(SUM(total_amount), 0) as revenue
                    FROM orders 
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                    AND status != 'CANCELLED'
                    AND (is_paid = 1 OR status = 'SERVED')
                ");
                $result = $stmt->fetch(\PDO::FETCH_ASSOC);
                $totalOrders = (int)($result['order_count'] ?? 0);
                $totalRevenue = (float)($result['revenue'] ?? 0);
            } catch (\Exception $e) {
                // Ignore if orders table doesn't exist
            }
            
            $data = [
                'monthlyRevenue' => $monthlyRevenue,
                'customerGrowth' => $customerGrowth,
                'churnRate' => $churnRate,
                'mrr' => $mrr,
                'totalOrders' => $totalOrders,
                'totalRevenue' => $totalRevenue,
                'page' => 'superadmin-analytics'
            ];
            
            $this->view('superadmin/analytics', $data);
            
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('SuperAdmin Analytics error', [
                    'error' => $e->getMessage()
                ]);
            }
            
            $data = [
                'monthlyRevenue' => [],
                'customerGrowth' => [],
                'churnRate' => 0,
                'mrr' => 0,
                'page' => 'superadmin-analytics'
            ];
            
            $this->view('superadmin/analytics', $data);
        }
    }
}
