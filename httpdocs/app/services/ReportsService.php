<?php
namespace App\Services;

use App\Core\BaseService;
use App\Repositories\ReportsRepository;
use App\Core\DependencyFactory;

/**
 * Reports Service
 * Handles business logic for reports
 * 
 * @package App\Services
 */
class ReportsService extends BaseService {
    private $financeService;
    private $tableService;

    public function __construct(ReportsRepository $reportsRepository) {
        parent::__construct($reportsRepository);
        $this->financeService = DependencyFactory::getFinanceService();
        $this->tableService = DependencyFactory::getTableService();
    }

    /**
     * Get time range data based on period
     * @param string $period Period (today, yesterday, last_7_days, last_30_days, this_month, last_month, this_year, custom)
     * @return array ['start' => 'Y-m-d', 'end' => 'Y-m-d']
     */
    public function getTimeRangeData(string $period): array {
        $today = date('Y-m-d');
        
        switch ($period) {
            case 'today':
                return ['start' => $today, 'end' => $today];
            
            case 'yesterday':
                $yesterday = date('Y-m-d', strtotime('-1 day'));
                return ['start' => $yesterday, 'end' => $yesterday];
            
            case 'last_7_days':
                return ['start' => date('Y-m-d', strtotime('-7 days')), 'end' => $today];
            
            case 'last_30_days':
                return ['start' => date('Y-m-d', strtotime('-30 days')), 'end' => $today];
            
            case 'this_month':
                return ['start' => date('Y-m-01'), 'end' => $today];
            
            case 'last_month':
                $firstDayLastMonth = date('Y-m-01', strtotime('first day of last month'));
                $lastDayLastMonth = date('Y-m-t', strtotime('last day of last month'));
                return ['start' => $firstDayLastMonth, 'end' => $lastDayLastMonth];
            
            case 'this_year':
                return ['start' => date('Y-01-01'), 'end' => $today];
            
            case 'custom':
            default:
                // For custom, caller should provide dates
                return ['start' => date('Y-m-01'), 'end' => $today];
        }
    }

    /**
     * Get sales report
     * @param string $startDate Start date (Y-m-d)
     * @param string $endDate End date (Y-m-d)
     * @param string|null $tableId Optional table ID filter
     * @return array Sales report data
     */
    public function getSalesReport(string $startDate, string $endDate, ?string $tableId = null): array {
        return $this->repository->getSalesReport($startDate, $endDate, $tableId);
    }

    /**
     * Get table-specific report
     * @param string $tableId Table ID
     * @param string $startDate Start date (Y-m-d)
     * @param string $endDate End date (Y-m-d)
     * @return array Table report data
     */
    public function getTableReport(string $tableId, string $startDate, string $endDate): array {
        $report = $this->repository->getTableReport($tableId, $startDate, $endDate);
        
        // Get table orders for additional details
        $orders = $this->repository->getTableOrders($tableId, $startDate, $endDate);
        
        // Get order items for each order
        foreach ($orders as &$order) {
            $order['items'] = $this->repository->getTableOrderItems($order['order_id']);
        }
        
        $report['orders'] = $orders;
        $report['order_count'] = count($orders);
        
        return $report;
    }

    /**
     * Get all tables report
     * @param string $startDate Start date (Y-m-d)
     * @param string $endDate End date (Y-m-d)
     * @return array All tables report data
     */
    public function getAllTablesReport(string $startDate, string $endDate): array {
        return $this->repository->getAllTablesReport($startDate, $endDate);
    }

    /**
     * Get employee performance report
     * @param string $startDate Start date (Y-m-d)
     * @param string $endDate End date (Y-m-d)
     * @return array Employee performance data
     */
    public function getEmployeePerformanceReport(string $startDate, string $endDate): array {
        $report = $this->repository->getEmployeePerformanceReport($startDate, $endDate);
        
        // Calculate additional metrics
        foreach ($report as &$employee) {
            $ordersHandled = (int)($employee['orders_handled'] ?? 0);
            $totalSales = (float)($employee['total_sales'] ?? 0);
            $employee['avg_order_value'] = $ordersHandled > 0 ? ($totalSales / $ordersHandled) : 0;
        }
        
        return $report;
    }

    /**
     * Get customer report
     * @param string $startDate Start date (Y-m-d)
     * @param string $endDate End date (Y-m-d)
     * @return array Customer report data
     */
    public function getCustomerReport(string $startDate, string $endDate): array {
        return $this->repository->getCustomerReport($startDate, $endDate);
    }

    /**
     * Get category revenue report
     * @param string $startDate Start date (Y-m-d)
     * @param string $endDate End date (Y-m-d)
     * @return array Category revenue data
     */
    public function getCategoryRevenueReport(string $startDate, string $endDate): array {
        return $this->repository->getCategoryRevenueReport($startDate, $endDate);
    }

    /**
     * Get hourly sales report
     * @param string $startDate Start date (Y-m-d)
     * @param string $endDate End date (Y-m-d)
     * @return array Hourly sales data (24 hours filled)
     */
    public function getHourlySalesReport(string $startDate, string $endDate): array {
        $hourlyData = $this->repository->getHourlySalesReport($startDate, $endDate);
        
        // Create a map for quick lookup
        $hourlyMap = [];
        foreach ($hourlyData as $data) {
            $hourlyMap[(int)$data['hour']] = $data;
        }
        
        // Fill all 24 hours
        $result = [];
        for ($hour = 0; $hour < 24; $hour++) {
            if (isset($hourlyMap[$hour])) {
                $result[] = $hourlyMap[$hour];
            } else {
                $result[] = [
                    'hour' => $hour,
                    'order_count' => 0,
                    'revenue' => 0,
                    'avg_order_value' => 0
                ];
            }
        }
        
        return $result;
    }

    /**
     * Get profit/loss report
     * @param string $startDate Start date (Y-m-d)
     * @param string $endDate End date (Y-m-d)
     * @return array Profit/loss data
     */
    public function getProfitLossReport(string $startDate, string $endDate): array {
        $salesReport = $this->getSalesReport($startDate, $endDate);
        $totalRevenue = (float)($salesReport['total_revenue'] ?? 0);
        
        $totalExpenses = $this->financeService->getTotalExpensesByDateRange($startDate, $endDate);
        $netProfit = $totalRevenue - $totalExpenses;
        $profitMargin = $totalRevenue > 0 ? (($netProfit / $totalRevenue) * 100) : 0;
        
        return [
            'total_revenue' => $totalRevenue,
            'total_expenses' => (float)$totalExpenses,
            'net_profit' => $netProfit,
            'profit_margin' => round($profitMargin, 2)
        ];
    }

    /**
     * Get daily revenue chart data
     * @param string $startDate Start date (Y-m-d)
     * @param string $endDate End date (Y-m-d)
     * @return array Daily revenue data
     */
    public function getDailyRevenueChart(string $startDate, string $endDate): array {
        return $this->repository->getDailyRevenueChart($startDate, $endDate);
    }

    /**
     * Get top selling items
     * @param string $startDate Start date (Y-m-d)
     * @param string $endDate End date (Y-m-d)
     * @param int $limit Limit
     * @return array Top selling items
     */
    public function getTopSellingItems(string $startDate, string $endDate, int $limit = 10): array {
        return $this->repository->getTopSellingItems($startDate, $endDate, $limit);
    }

    /**
     * Get comprehensive report data (all reports in one call)
     * @param string $startDate Start date (Y-m-d)
     * @param string $endDate End date (Y-m-d)
     * @param string|null $tableId Optional table ID filter
     * @return array Comprehensive report data
     */
    public function getComprehensiveReport(string $startDate, string $endDate, ?string $tableId = null): array {
        $result = [
            'sales_report' => ['total_orders' => 0, 'total_revenue' => 0, 'avg_order_value' => 0, 'completed_orders' => 0],
            'customer_report' => ['unique_customers' => 0, 'total_visits' => 0, 'avg_spent' => 0],
            'category_revenue' => [],
            'hourly_sales' => [],
            'daily_revenue_chart' => [],
            'top_selling_items' => [],
            'profit_loss_report' => ['total_revenue' => 0, 'total_expenses' => 0, 'net_profit' => 0, 'profit_margin' => 0],
            'employee_performance' => [],
            'tables_report' => []
        ];
        
        // Get each report with error handling
        try {
            $result['sales_report'] = $this->getSalesReport($startDate, $endDate, $tableId);
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('Error getting sales report', ['error' => $e->getMessage()]);
            }
        }
        
        try {
            $result['customer_report'] = $this->getCustomerReport($startDate, $endDate);
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('Error getting customer report', ['error' => $e->getMessage()]);
            }
        }
        
        try {
            $result['category_revenue'] = $this->getCategoryRevenueReport($startDate, $endDate);
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('Error getting category revenue', ['error' => $e->getMessage()]);
            }
        }
        
        try {
            $result['hourly_sales'] = $this->getHourlySalesReport($startDate, $endDate);
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('Error getting hourly sales', ['error' => $e->getMessage()]);
            }
        }
        
        try {
            $result['daily_revenue_chart'] = $this->getDailyRevenueChart($startDate, $endDate);
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('Error getting daily revenue chart', ['error' => $e->getMessage()]);
            }
        }
        
        try {
            $result['top_selling_items'] = $this->getTopSellingItems($startDate, $endDate, 10);
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('Error getting top selling items', ['error' => $e->getMessage()]);
            }
        }
        
        try {
            $result['profit_loss_report'] = $this->getProfitLossReport($startDate, $endDate);
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('Error getting profit loss report', ['error' => $e->getMessage()]);
            }
        }
        
        try {
            $result['employee_performance'] = $this->getEmployeePerformanceReport($startDate, $endDate);
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('Error getting employee performance', ['error' => $e->getMessage()]);
            }
        }
        
        try {
            $result['tables_report'] = $tableId 
                ? [$this->getTableReport($tableId, $startDate, $endDate)] 
                : $this->getAllTablesReport($startDate, $endDate);
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('Error getting tables report', ['error' => $e->getMessage()]);
            }
        }
        
        return $result;
    }
}

