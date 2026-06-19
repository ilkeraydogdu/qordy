<?php
namespace App\Controllers\Admin;

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../../core/DependencyFactory.php';

use App\Core\Controller;

class ReportsController extends Controller {
    protected $reportsService;
    protected $tableService;
    protected $financeService;
    
    public function __construct() {
        parent::__construct();
        $this->reportsService = \App\Core\DependencyFactory::getReportsService();
        $this->tableService = \App\Core\DependencyFactory::getTableService();
        $this->financeService = \App\Core\DependencyFactory::getFinanceService();
    }
    
    public function reports() {
        $isSuperAdmin = $this->isSuperAdmin();
        
        // SuperAdmin icin business_id query parametresini kontrol et ve TenantContext'e set et
        if ($isSuperAdmin) {
            $queryParams = \App\Core\RequestParser::getQueryParams();
            $businessId = $queryParams['business_id'] ?? null;
            
            if ($businessId) {
                try {
                    $customerService = \App\Core\DependencyFactory::getCustomerService();
                    $customer = $customerService->getById($businessId);
                    if ($customer) {
                        \App\Core\TenantContext::set($customer);
                        $_SESSION['business_id'] = $businessId;
                        $_SESSION['customer_id'] = $businessId;
                    }
                } catch (\Exception $e) {
                    if (class_exists('\App\Core\Logger')) {
                        \App\Core\Logger::error('Failed to set tenant context from business_id in ReportsController', [
                            'business_id' => $businessId,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            } else {
                // No business selected - show business selection UI
                $this->view('admin/reports', [
                    'is_super_admin' => true
                ]);
                return;
            }
        }

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

        try {
            // Get all tables for dropdown
            $allTables = [];
            try {
                if (method_exists($this->tableService, 'getAllTables')) {
                    $allTables = $this->tableService->getAllTables();
                }
            } catch (\Exception $e) {
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::error('Error getting tables for reports', [
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // Get comprehensive report data
            $reportData = [];
            try {
                if (method_exists($this->reportsService, 'getComprehensiveReport')) {
                    $reportData = $this->reportsService->getComprehensiveReport($startDate, $endDate, $tableId);
                }
            } catch (\Exception $e) {
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::error('Error getting comprehensive report', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
                // Set default empty report data
                $reportData = [
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
            }

            // Get expense report separately
            $expenses = [];
            $expenseReport = ['total_expenses' => 0, 'expense_count' => 0];
            try {
                if (method_exists($this->financeService, 'getExpensesByDateRange')) {
                    $expenses = $this->financeService->getExpensesByDateRange($startDate, $endDate);
                }
                
                if (method_exists($this->financeService, 'getTotalExpensesByDateRange')) {
                    $expenseReport['total_expenses'] = $this->financeService->getTotalExpensesByDateRange($startDate, $endDate);
                }
                $expenseReport['expense_count'] = count($expenses);
            } catch (\Exception $e) {
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::error('Error getting expense report', [
                        'error' => $e->getMessage()
                    ]);
                }
            }

            $data = array_merge($reportData, [
                'expense_report' => $expenseReport,
                'date_range' => [
                    'start' => $startDate,
                    'end' => $endDate
                ],
                'period' => $period,
                'selected_table_id' => $tableId,
                'tables' => $allTables,
                'title' => 'Raporlar',
                'is_super_admin' => $isSuperAdmin
            ]);

            $this->view('admin/reports', $data);
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('Reports error', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
            
            // Fallback with empty data
            $data = [
                'sales_report' => ['total_orders' => 0, 'total_revenue' => 0, 'avg_order_value' => 0, 'completed_orders' => 0],
                'customer_report' => ['unique_customers' => 0, 'total_visits' => 0, 'avg_spent' => 0],
                'category_revenue' => [],
                'hourly_sales' => [],
                'daily_revenue_chart' => [],
                'top_selling_items' => [],
                'profit_loss_report' => ['total_revenue' => 0, 'total_expenses' => 0, 'net_profit' => 0, 'profit_margin' => 0],
                'employee_performance' => [],
                'tables_report' => [],
                'expense_report' => ['total_expenses' => 0, 'expense_count' => 0],
                'date_range' => ['start' => $startDate, 'end' => $endDate],
                'period' => $period,
                'selected_table_id' => $tableId,
                'tables' => [],
                'title' => 'Raporlar',
                'is_super_admin' => $isSuperAdmin
            ];
            
            $this->view('admin/reports', $data);
        }
    }

    /**
     * API endpoint for getting reports data (AJAX)
     */
    public function getReportsData() {
        // Super Admin bypass
        if (!$this->isSuperAdmin()) {
            if (!$this->hasPermission('reports.view')) {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 401);
                return;
            }
        }

        // Super admin: route the API through the selected business so every
        // downstream query (sales, expenses, customers...) resolves against
        // the intended tenant instead of an empty / stale context.
        if ($this->isSuperAdmin()) {
            $qp = \App\Core\RequestParser::getQueryParams();
            $rp = \App\Core\RequestParser::getRequestData();
            $requestedBusinessId = $qp['business_id'] ?? $rp['business_id'] ?? null;
            if ($requestedBusinessId) {
                try {
                    $customerService = \App\Core\DependencyFactory::getCustomerService();
                    $customer = $customerService->getById($requestedBusinessId);
                    if ($customer) {
                        \App\Core\TenantContext::set($customer);
                        $_SESSION['business_id']  = $requestedBusinessId;
                        $_SESSION['customer_id']  = $requestedBusinessId;
                    }
                } catch (\Throwable $e) {
                    if (class_exists('\App\Core\Logger')) {
                        \App\Core\Logger::warning('ReportsController::getReportsData — tenant switch failed', [
                            'business_id' => $requestedBusinessId,
                            'error'       => $e->getMessage(),
                        ]);
                    }
                }
            }
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
            $reportData = [];
            try {
                $reportData = $this->reportsService->getComprehensiveReport($startDate, $endDate, $tableId);
            } catch (\Exception $e) {
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::error('Error getting comprehensive report in API', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
                $reportData = [
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
            }

            // Get expense report separately
            $expenseReport = ['total_expenses' => 0, 'expense_count' => 0];
            try {
                $expenses = $this->financeService->getExpensesByDateRange($startDate, $endDate);
                $expenseReport = [
                    'total_expenses' => $this->financeService->getTotalExpensesByDateRange($startDate, $endDate),
                    'expense_count' => count($expenses)
                ];
            } catch (\Exception $e) {
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::error('Error getting expense report in API', [
                        'error' => $e->getMessage()
                    ]);
                }
            }

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
        
        $orderService = \App\Core\DependencyFactory::getOrderService();
        
        // PERFORMANCE OPTIMIZATION: Calculate date range first
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
        $orders = $orderService->getOrdersByDateRange($finalStartDate, $finalEndDate);
        $orders = is_array($orders) ? $orders : [];
        
        // Filter by status if needed
        if ($status !== 'all') {
            $orders = array_filter($orders, function($order) use ($status) {
                return ($order['status'] ?? '') === $status;
            });
            $orders = array_values($orders);
        }
        
        // Note: Date filter already applied above via getOrdersByDateRange()
        // No need to filter again here - this prevents double filtering
        
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
            $exportData[] = [
                'id' => $order['order_id'] ?? '',
                'table' => $order['table_name'] ?? 'Bilinmiyor',
                'customer' => $order['created_by'] ?? $order['customer_name'] ?? 'QR Sipariş',
                'status' => strtolower($order['status'] ?? 'pending'),
                'date' => $order['created_at'] ?? '',
                'amount' => floatval($order['total_amount'] ?? 0),
                'payment_method' => $order['payment_method'] ?? 'Nakit'
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
        // Super Admin bypass
        if (!$this->isSuperAdmin()) {
            $this->requirePermission('reports.export');
        }

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

        // Generate report data based on type using ReportsService
        $reportData = [];
        $filename = '';

        switch ($reportType) {
            case 'sales':
                $reportData = $this->reportsService->getSalesReport($startDate, $endDate);
                $filename = 'sales_report_' . $startDate . '_to_' . $endDate . '.csv';
                break;
            case 'inventory':
                // Inventory report would need inventory service
                $reportData = ['message' => 'Inventory report not yet implemented'];
                $filename = 'inventory_report_' . date('Y-m-d') . '.csv';
                break;
            case 'employees':
                $reportData = $this->reportsService->getEmployeePerformanceReport($startDate, $endDate);
                $filename = 'employee_report_' . $startDate . '_to_' . $endDate . '.csv';
                break;
            case 'customers':
                $reportData = $this->reportsService->getCustomerReport($startDate, $endDate);
                $filename = 'customer_report_' . $startDate . '_to_' . $endDate . '.csv';
                break;
            case 'expenses':
                $expenses = $this->financeService->getExpensesByDateRange($startDate, $endDate);
                $reportData = [
                    'total_expenses' => $this->financeService->getTotalExpensesByDateRange($startDate, $endDate),
                    'expense_count' => count($expenses),
                    'expenses' => $expenses
                ];
                $filename = 'expense_report_' . $startDate . '_to_' . $endDate . '.csv';
                break;
            case 'profit_loss':
                $reportData = $this->reportsService->getProfitLossReport($startDate, $endDate);
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
}

