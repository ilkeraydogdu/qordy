<?php
namespace App\Services;

use App\Repositories\ExpenseRepository;
use App\Repositories\InvoiceRepository;
use App\Repositories\SupplierRepository;

class FinanceService {
    private $expenseRepository;
    private $invoiceRepository;
    private $supplierRepository;
    private $db;
    
    public function __construct(
        ExpenseRepository $expenseRepository,
        InvoiceRepository $invoiceRepository,
        SupplierRepository $supplierRepository
    ) {
        $this->expenseRepository = $expenseRepository;
        $this->invoiceRepository = $invoiceRepository;
        $this->supplierRepository = $supplierRepository;
        $this->db = $expenseRepository->getDbConnection();
    }
    
    /**
     * Get all expenses
     * @return array All expenses
     */
    public function getAllExpenses(): array {
        return $this->expenseRepository->findAll();
    }
    
    /**
     * Get expenses by date range
     * @param string $startDate Start date (Y-m-d)
     * @param string $endDate End date (Y-m-d)
     * @return array Expenses
     */
    public function getExpensesByDateRange(string $startDate, string $endDate): array {
        return $this->expenseRepository->getByDateRange($startDate, $endDate);
    }
    
    /**
     * Get expenses by category
     * @param string $category Expense category
     * @return array Expenses
     */
    public function getExpensesByCategory(string $category): array {
        return $this->expenseRepository->getByCategory($category);
    }
    
    /**
     * Get total expenses by date range
     * @param string $startDate Start date (Y-m-d)
     * @param string $endDate End date (Y-m-d)
     * @return float Total expenses
     */
    public function getTotalExpensesByDateRange(string $startDate, string $endDate): float {
        return $this->expenseRepository->getTotalByDateRange($startDate, $endDate);
    }
    
    /**
     * Create a new expense
     * @param array $expenseData Expense data
     * @return bool|string Expense ID on success, false on failure
     */
    public function createExpense(array $expenseData) {
        if (empty($expenseData['expense_id'])) {
            $expenseData['expense_id'] = generateId('exp');
        }
        
        if (empty($expenseData['category']) || empty($expenseData['amount']) || empty($expenseData['date'])) {
            return false;
        }
        
        // Ensure title is set (required field in database)
        if (empty($expenseData['title'])) {
            $expenseData['title'] = $expenseData['description'] ?? 'Gider';
        }
        
        // Remove description if exists (not in database schema)
        if (isset($expenseData['description'])) {
            unset($expenseData['description']);
        }
        
        $expenseData['amount'] = floatval($expenseData['amount']);
        
        $result = $this->expenseRepository->create($expenseData);
        
        if ($result) {
            return $expenseData['expense_id'];
        }
        
        return false;
    }
    
    /**
     * Update expense
     * @param string $expenseId Expense ID
     * @param array $expenseData Expense data to update
     * @return bool Success
     */
    public function updateExpense(string $expenseId, array $expenseData): bool {
        return $this->expenseRepository->update($expenseId, $expenseData);
    }

    /**
     * Create a WASTE-category expense mirrored from a waste record so the
     * P&L view automatically reflects spoilage/loss costs. Called by
     * WasteRecordService after a waste row is persisted. Returns the new
     * expense_id (string) or null on failure.
     *
     * @param string $wasteId The originating waste_id (becomes source_id).
     * @param array  $payload supplier_id, amount, reason, reason_detail, waste_date, tenant_id.
     * @return string|null
     */
    public function createExpenseFromWaste(string $wasteId, array $payload): ?string
    {
        $amount = (float)($payload['amount'] ?? 0);
        if ($amount <= 0) {
            return null;
        }

        $title = 'Fire gideri';
        if (!empty($payload['reason_detail'])) {
            $title = 'Fire: ' . mb_substr((string)$payload['reason_detail'], 0, 200);
        } elseif (!empty($payload['reason'])) {
            $title = 'Fire: ' . $payload['reason'];
        }

        $expense = [
            'category'    => 'WASTE',
            'amount'      => $amount,
            'date'        => !empty($payload['waste_date'])
                ? date('Y-m-d', strtotime((string)$payload['waste_date']))
                : date('Y-m-d'),
            'title'       => $title,
            'source_type' => 'WASTE',
            'source_id'   => $wasteId,
        ];

        if (!empty($payload['supplier_id'])) {
            $expense['supplier_id'] = $payload['supplier_id'];
        }
        if (!empty($payload['tenant_id'])) {
            $expense['tenant_id'] = $payload['tenant_id'];
        }

        $result = $this->createExpense($expense);
        return is_string($result) ? $result : null;
    }
    
    /**
     * Delete expense
     * @param string $expenseId Expense ID
     * @return bool Success
     */
    public function deleteExpense(string $expenseId): bool {
        return $this->expenseRepository->delete($expenseId);
    }
    
    /**
     * Get all invoices
     * @return array All invoices
     */
    public function getAllInvoices(): array {
        return $this->invoiceRepository->findAll();
    }
    
    /**
     * Get invoices by supplier ID
     * @param string $supplierId Supplier ID
     * @return array Invoices
     */
    public function getInvoicesBySupplier(string $supplierId): array {
        return $this->invoiceRepository->getBySupplierId($supplierId);
    }
    
    /**
     * Get unpaid invoices
     * @return array Unpaid invoices
     */
    public function getUnpaidInvoices(): array {
        return $this->invoiceRepository->getUnpaid();
    }
    
    /**
     * Get invoices by date range
     * @param string $startDate Start date (Y-m-d)
     * @param string $endDate End date (Y-m-d)
     * @return array Invoices
     */
    public function getInvoicesByDateRange(string $startDate, string $endDate): array {
        return $this->invoiceRepository->getByDateRange($startDate, $endDate);
    }
    
    /**
     * Get total unpaid invoices amount
     * @return float Total unpaid amount
     */
    public function getTotalUnpaidInvoices(): float {
        return $this->invoiceRepository->getTotalUnpaid();
    }
    
    /**
     * Create a new invoice
     * @param array $invoiceData Invoice data
     * @return bool|string Invoice ID on success, false on failure
     */
    public function createInvoice(array $invoiceData) {
        if (empty($invoiceData['invoice_id'])) {
            $invoiceData['invoice_id'] = generateId('inv');
        }
        
        // Map invoice_date to date if provided
        if (isset($invoiceData['invoice_date']) && !isset($invoiceData['date'])) {
            $invoiceData['date'] = $invoiceData['invoice_date'];
            unset($invoiceData['invoice_date']);
        }
        
        if (empty($invoiceData['supplier_id']) || empty($invoiceData['amount']) || empty($invoiceData['date'])) {
            return false;
        }
        
        $defaults = [
            'status' => 'UNPAID'
        ];
        
        $invoiceData = array_merge($defaults, $invoiceData);
        
        // Parse amount correctly (handle Turkish locale comma as decimal separator)
        $amount = $invoiceData['amount'] ?? 0;
        
        // Handle null or empty
        if ($amount === null || $amount === '' || $amount === false) {
            $invoiceData['amount'] = 0.0;
        } elseif (is_numeric($amount)) {
            $invoiceData['amount'] = floatval($amount);
        } else {
            $amountStr = trim((string)$amount);
            
            // Remove currency symbols and spaces
            $amountStr = preg_replace('/[₺$€£,\s]/', '', $amountStr);
            
            // Replace Turkish comma with dot for decimal separator (if any comma exists)
            if (substr_count($amountStr, ',') === 1 && substr_count($amountStr, '.') === 0) {
                $amountStr = str_replace(',', '.', $amountStr);
            } else {
                // Remove commas (thousands separator)
                $amountStr = str_replace(',', '', $amountStr);
            }
            
            // Remove all non-numeric characters except dot
            $amountStr = preg_replace('/[^0-9.]/', '', $amountStr);
            
            // Ensure only one dot (decimal separator)
            $parts = explode('.', $amountStr);
            if (count($parts) > 2) {
                $amountStr = $parts[0] . '.' . implode('', array_slice($parts, 1));
            }
            
            $invoiceData['amount'] = floatval($amountStr);
        }
        
        // Log for debugging
        \App\Core\Logger::info('FinanceService::createInvoice - Amount parsed', [
            'original' => $invoiceData['amount'] ?? 'N/A',
            'parsed' => $invoiceData['amount']
        ]);
        
        $result = $this->invoiceRepository->create($invoiceData);
        
        if ($result) {
            // Update supplier balance (add invoice amount)
            $this->supplierRepository->updateBalance($invoiceData['supplier_id'], $invoiceData['amount']);
            
            return $invoiceData['invoice_id'];
        }
        
        return false;
    }
    
    /**
     * Update invoice
     * @param string $invoiceId Invoice ID
     * @param array $invoiceData Invoice data to update
     * @return bool Success
     */
    public function updateInvoice(string $invoiceId, array $invoiceData): bool {
        return $this->invoiceRepository->update($invoiceId, $invoiceData);
    }
    
    /**
     * Mark invoice as paid
     * @param string $invoiceId Invoice ID
     * @return bool Success
     */
    public function markInvoiceAsPaid(string $invoiceId): bool {
        $invoice = $this->invoiceRepository->findById($invoiceId);
        if (!$invoice) {
            return false;
        }
        
        $result = $this->invoiceRepository->update($invoiceId, ['status' => 'PAID', 'is_paid' => 1]);
        
        if ($result && !empty($invoice['supplier_id'])) {
            // Recalculate supplier balance from all unpaid invoices (more reliable)
            $this->recalculateSupplierBalance($invoice['supplier_id']);
        }
        
        return $result;
    }
    
    /**
     * Delete invoice
     * @param string $invoiceId Invoice ID
     * @return bool Success
     */
    public function deleteInvoice(string $invoiceId): bool {
        return $this->invoiceRepository->delete($invoiceId);
    }
    
    /**
     * Get all suppliers
     * @return array All suppliers
     */
    public function getAllSuppliers(): array {
        return $this->supplierRepository->getAllOrdered();
    }
    
    /**
     * Get suppliers by category
     * @param string $category Supplier category
     * @return array Suppliers
     */
    public function getSuppliersByCategory(string $category): array {
        return $this->supplierRepository->getByCategory($category);
    }
    
    /**
     * Get supplier by ID
     * @param string $supplierId Supplier ID
     * @return array|null Supplier data or null
     */
    public function getSupplierById(string $supplierId): ?array {
        return $this->supplierRepository->findById($supplierId);
    }
    
    /**
     * Recalculate supplier balance from unpaid invoices
     * This ensures balance always matches the sum of unpaid invoices
     * @param string $supplierId Supplier ID
     * @return bool Success
     */
    public function recalculateSupplierBalance(string $supplierId): bool {
        $totalUnpaid = $this->invoiceRepository->getTotalUnpaidBySupplier($supplierId);
        
        // Update balance directly using SQL to match unpaid invoices total
        $sql = "UPDATE suppliers SET balance = :balance WHERE supplier_id = :supplier_id";
        $params = [
            'supplier_id' => $supplierId,
            'balance' => $totalUnpaid
        ];
        
        // Add tenant filter for security
        $filter = $this->supplierRepository->getTenantFilter();
        if (!empty($filter['where'])) {
            $sql .= " AND " . $filter['where'];
            $params = array_merge($params, $filter['params']);
        }
        
        // Use the database connection directly
        $db = $this->supplierRepository->getDatabase();
        if (!$db) {
            return false;
        }
        
        try {
            $stmt = $db->prepare($sql);
            return $stmt->execute($params);
        } catch (\Exception $e) {
            \App\Core\Logger::error('Failed to recalculate supplier balance', [
                'supplier_id' => $supplierId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Create a new supplier
     * @param array $supplierData Supplier data
     * @return bool|string Supplier ID on success, false on failure
     */
    public function createSupplier(array $supplierData) {
        if (empty($supplierData['supplier_id'])) {
            $supplierData['supplier_id'] = generateId('sup');
        }
        
        if (empty($supplierData['name'])) {
            return false;
        }
        
        $defaults = [
            'balance' => 0
        ];
        
        $supplierData = array_merge($defaults, $supplierData);
        
        $result = $this->supplierRepository->create($supplierData);
        
        if ($result) {
            return $supplierData['supplier_id'];
        }
        
        return false;
    }
    
    /**
     * Update supplier
     * @param string $supplierId Supplier ID
     * @param array $supplierData Supplier data to update
     * @return bool Success
     */
    public function updateSupplier(string $supplierId, array $supplierData): bool {
        return $this->supplierRepository->update($supplierId, $supplierData);
    }
    
    /**
     * Delete supplier
     * @param string $supplierId Supplier ID
     * @return bool Success
     */
    public function deleteSupplier(string $supplierId): bool {
        return $this->supplierRepository->delete($supplierId);
    }
    
    /**
     * Calculate net profit for date range
     * @param string $startDate Start date (Y-m-d)
     * @param string $endDate End date (Y-m-d)
     * @param float $revenue Total revenue
     * @return float Net profit
     */
    public function calculateNetProfit(string $startDate, string $endDate, float $revenue): float {
        $expenses = $this->getTotalExpensesByDateRange($startDate, $endDate);
        return $revenue - $expenses;
    }
    
    /**
     * Process payment for a table
     * @param string $tableId Table ID
     * @param float $amount Payment amount
     * @param string $method Payment method (CASH, CARD, etc.)
     * @param float $tip Tip amount
     * @param array $itemsPaid Items that were paid
     * @return array|false Transaction data on success, false on failure
     */
    public function processPayment(string $tableId, float $amount, string $method = 'CASH', float $tip = 0, array $itemsPaid = []) {
        require_once __DIR__ . '/../models/PaymentTransaction.php';
        require_once __DIR__ . '/../helpers/functions.php';
        
        // CRITICAL: Get tenant_id from table for tenant isolation
        $tableService = \App\Core\DependencyFactory::getTableService();
        $table = $tableService->getTableById($tableId);
        $tenantId = null;
        
        if ($table) {
            $tenantId = $table['tenant_id'] ?? null;
        }
        
        // If no tenant_id from table, try to get from session/context
        if (!$tenantId) {
            $tenantId = \App\Core\TenantResolver::resolve();
            if (!$tenantId && class_exists('\\App\\Core\\TenantContext')) {
                try {
                    $tenantId = \App\Core\TenantContext::getId();
                } catch (\Exception $e) {
                    // TenantContext not available
                }
            }
        }
        
        $transactionId = generateId('pay');
        $transactionData = [
            'transaction_id' => $transactionId,
            'table_id' => $tableId,
            'amount' => $amount,
            'tip' => $tip,
            'payment_method' => $method,
            'created_at' => date('Y-m-d H:i:s'),
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        // CRITICAL: Set tenant_id for tenant isolation
        if ($tenantId) {
            $transactionData['tenant_id'] = $tenantId;
        }
        
        // Note: business_id, status, shift_id, session_id are automatically filtered by PaymentTransaction model
        $paymentTransactionModel = new \App\Models\PaymentTransaction();
        $result = $paymentTransactionModel->create($transactionData);
        
        if ($result) {
            return [
                'transaction_id' => $transactionId,
                'amount' => $amount,
                'tip' => $tip,
                'method' => $method
            ];
        }
        
        return false;
    }
    
    /**
     * Get financial summary for a date range
     * @param string $startDate Start date (Y-m-d)
     * @param string $endDate End date (Y-m-d)
     * @param \App\Services\OrderService|null $orderService Order service instance
     * @return array Financial summary
     */
    public function getFinancialSummary(string $startDate, string $endDate, $orderService = null): array {
        $revenue = 0;
        if ($orderService) {
            $revenue = $orderService->calculateTotalRevenue($startDate, $endDate);
        }
        
        $expenses = $this->getTotalExpensesByDateRange($startDate, $endDate);
        $netProfit = $revenue - $expenses;
        $profitMargin = $revenue > 0 ? ($netProfit / $revenue) * 100 : 0;
        
        // Get order count for average calculation
        $orderCount = 0;
        if ($orderService) {
            $orders = $orderService->getOrdersByDateRange($startDate, $endDate);
            $orderCount = count($orders);
        }
        $avgOrderValue = $orderCount > 0 ? $revenue / $orderCount : 0;
        
        return [
            'revenue' => $revenue,
            'expenses' => $expenses,
            'net_profit' => $netProfit,
            'profit_margin' => round($profitMargin, 2),
            'order_count' => $orderCount,
            'avg_order_value' => round($avgOrderValue, 2)
        ];
    }
    
    /**
     * Get revenue trend for last N days
     * @param int $days Number of days (default: 7)
     * @param \App\Services\OrderService|null $orderService Order service instance
     * @return array Revenue trend data
     */
    public function getRevenueTrend(int $days = 7, $orderService = null): array {
        if (!$orderService) {
            return [];
        }
        
        $trend = [];
        $today = new \DateTime();
        
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = clone $today;
            $date->modify("-{$i} days");
            $dateStr = $date->format('Y-m-d');
            
            $revenue = $orderService->getDailyRevenue($dateStr);
            $trend[] = [
                'date' => $dateStr,
                'revenue' => $revenue,
                'day_name' => $date->format('D'),
                'day_number' => $date->format('d')
            ];
        }
        
        return $trend;
    }
    
    /**
     * Get expense trend for last N days
     * @param int $days Number of days (default: 7)
     * @return array Expense trend data
     */
    public function getExpenseTrend(int $days = 7): array {
        $trend = [];
        $today = new \DateTime();
        
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = clone $today;
            $date->modify("-{$i} days");
            $dateStr = $date->format('Y-m-d');
            
            $expenses = $this->getTotalExpensesByDateRange($dateStr, $dateStr);
            $trend[] = [
                'date' => $dateStr,
                'expenses' => $expenses,
                'day_name' => $date->format('D'),
                'day_number' => $date->format('d')
            ];
        }
        
        return $trend;
    }
    
    /**
     * Calculate profit margin
     * @param float $revenue Total revenue
     * @param float $expenses Total expenses
     * @return float Profit margin percentage
     */
    public function getProfitMargin(float $revenue, float $expenses): float {
        if ($revenue <= 0) {
            return 0;
        }
        $netProfit = $revenue - $expenses;
        return round(($netProfit / $revenue) * 100, 2);
    }
    
    /**
     * Get expense breakdown by category
     * @param string $startDate Start date (Y-m-d)
     * @param string $endDate End date (Y-m-d)
     * @return array Category breakdown
     */
    public function getCategoryBreakdown(string $startDate, string $endDate): array {
        $expenses = $this->getExpensesByDateRange($startDate, $endDate);
        $breakdown = [];
        
        foreach ($expenses as $expense) {
            $category = $expense['category'] ?? 'Diğer';
            if (!isset($breakdown[$category])) {
                $breakdown[$category] = 0;
            }
            $breakdown[$category] += floatval($expense['amount'] ?? 0);
        }
        
        // Convert to array format and sort by amount
        $result = [];
        foreach ($breakdown as $category => $amount) {
            $result[] = [
                'category' => $category,
                'amount' => $amount
            ];
        }
        
        usort($result, function($a, $b) {
            return $b['amount'] <=> $a['amount'];
        });
        
        return $result;
    }
    
    /**
     * Get top expenses
     * @param int $limit Number of top expenses to return
     * @param string|null $startDate Optional start date filter
     * @param string|null $endDate Optional end date filter
     * @return array Top expenses
     */
    public function getTopExpenses(int $limit = 5, ?string $startDate = null, ?string $endDate = null): array {
        if ($startDate && $endDate) {
            $expenses = $this->getExpensesByDateRange($startDate, $endDate);
        } else {
            $expenses = $this->getAllExpenses();
        }
        
        // Sort by amount descending
        usort($expenses, function($a, $b) {
            $amountA = floatval($a['amount'] ?? 0);
            $amountB = floatval($b['amount'] ?? 0);
            return $amountB <=> $amountA;
        });
        
        return array_slice($expenses, 0, $limit);
    }
    
    /**
     * Get monthly comparison (last 3-6 months)
     * @param int $months Number of months to compare (default: 6)
     * @param \App\Services\OrderService|null $orderService Order service instance
     * @return array Monthly comparison data
     */
    public function getMonthlyComparison(int $months = 6, $orderService = null): array {
        $comparison = [];
        $today = new \DateTime();
        
        for ($i = $months - 1; $i >= 0; $i--) {
            $date = clone $today;
            $date->modify("-{$i} months");
            $monthStart = $date->format('Y-m-01');
            $monthEnd = $date->format('Y-m-t');
            
            $revenue = 0;
            if ($orderService) {
                $revenue = $orderService->calculateTotalRevenue($monthStart, $monthEnd);
            }
            
            $expenses = $this->getTotalExpensesByDateRange($monthStart, $monthEnd);
            $netProfit = $revenue - $expenses;
            
            $comparison[] = [
                'month' => $date->format('Y-m'),
                'month_name' => $date->format('M Y'),
                'revenue' => $revenue,
                'expenses' => $expenses,
                'net_profit' => $netProfit
            ];
        }
        
        return $comparison;
    }
    
    /**
     * Get cash flow analysis
     * @param string $startDate Start date (Y-m-d)
     * @param string $endDate End date (Y-m-d)
     * @param \App\Services\OrderService|null $orderService Order service instance
     * @return array Cash flow data
     */
    public function getCashFlow(string $startDate, string $endDate, $orderService = null): array {
        $revenue = 0;
        if ($orderService) {
            $revenue = $orderService->calculateTotalRevenue($startDate, $endDate);
        }
        
        $expenses = $this->getTotalExpensesByDateRange($startDate, $endDate);
        $unpaidInvoices = $this->getTotalUnpaidInvoices();
        
        return [
            'inflow' => $revenue,
            'outflow' => $expenses + $unpaidInvoices,
            'net_flow' => $revenue - $expenses - $unpaidInvoices,
            'unpaid_invoices' => $unpaidInvoices
        ];
    }
    
    /**
     * Get recent expenses
     * @param int $limit Number of recent expenses
     * @return array Recent expenses
     */
    public function getRecentExpenses(int $limit = 10): array {
        $expenses = $this->getAllExpenses();
        // Sort by date descending
        usort($expenses, function($a, $b) {
            $dateA = $a['date'] ?? '';
            $dateB = $b['date'] ?? '';
            return strcmp($dateB, $dateA);
        });
        
        return array_slice($expenses, 0, $limit);
    }

    /**
     * High-level aggregate used by the Business Admin Finance page
     * (business/finance route). Returns the shape the view expects:
     *   [
     *     'total_revenue'  => float,
     *     'total_expenses' => float,
     *     'net_profit'     => float,
     *     'period_start'   => 'YYYY-MM-DD',
     *     'period_end'     => 'YYYY-MM-DD',
     *   ]
     *
     * @param string|null $customerId Tenant id (used for logging/context,
     *                                tenant scoping happens inside the
     *                                repositories).
     * @param string|null $startDate  Period start (defaults to first day
     *                                of current month).
     * @param string|null $endDate    Period end (defaults to today).
     */
    public function getFinancialData(?string $customerId = null, ?string $startDate = null, ?string $endDate = null): array {
        $startDate = $startDate ?: date('Y-m-01');
        $endDate   = $endDate   ?: date('Y-m-d');

        $totalExpenses = 0.0;
        try {
            $totalExpenses = (float)$this->getTotalExpensesByDateRange($startDate, $endDate);
        } catch (\Throwable $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::warning('FinanceService::getFinancialData: expenses failed', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $totalRevenue = 0.0;
        try {
            $orderService = \App\Core\DependencyFactory::getOrderService();
            if ($orderService && method_exists($orderService, 'calculateTotalRevenue')) {
                $totalRevenue = (float)$orderService->calculateTotalRevenue($startDate, $endDate);
            }
        } catch (\Throwable $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::warning('FinanceService::getFinancialData: revenue failed', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'total_revenue'  => $totalRevenue,
            'total_expenses' => $totalExpenses,
            'net_profit'     => $totalRevenue - $totalExpenses,
            'period_start'   => $startDate,
            'period_end'     => $endDate,
        ];
    }

    /**
     * Income + expense list used by the Business Admin Finance page.
     * The view iterates over `income_expenses.income` and
     * `income_expenses.expenses`; it expects each row to carry `date`,
     * `description`, `amount`, `type`.
     *
     * Income: daily order totals for the period (one row per day with
     * revenue > 0). Expenses: expense rows from the expense repository,
     * normalised to the shape the view needs.
     */
    public function getIncomeExpenses(?string $customerId = null, ?string $startDate = null, ?string $endDate = null): array {
        $startDate = $startDate ?: date('Y-m-01');
        $endDate   = $endDate   ?: date('Y-m-d');

        $income = [];
        try {
            $orderService = \App\Core\DependencyFactory::getOrderService();
            if ($orderService && method_exists($orderService, 'getDailyRevenue')) {
                $cursor = new \DateTime($startDate);
                $end    = new \DateTime($endDate);
                while ($cursor <= $end) {
                    $day = $cursor->format('Y-m-d');
                    $rev = (float)$orderService->getDailyRevenue($day);
                    if ($rev > 0) {
                        $income[] = [
                            'date'        => $day,
                            'description' => 'Günlük satış',
                            'amount'      => $rev,
                            'type'        => 'Satış',
                        ];
                    }
                    $cursor->modify('+1 day');
                }
            }
        } catch (\Throwable $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::warning('FinanceService::getIncomeExpenses: income failed', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $expensesList = [];
        try {
            $raw = $this->getExpensesByDateRange($startDate, $endDate);
            foreach ($raw as $row) {
                $expensesList[] = [
                    'date'        => $row['date'] ?? $row['expense_date'] ?? date('Y-m-d'),
                    'title'       => $row['title'] ?? $row['description'] ?? 'Gider',
                    'description' => $row['description'] ?? $row['title'] ?? 'Gider',
                    'amount'      => (float)($row['amount'] ?? 0),
                    'type'        => $row['category'] ?? 'Gider',
                ];
            }
        } catch (\Throwable $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::warning('FinanceService::getIncomeExpenses: expenses failed', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'income'   => $income,
            'expenses' => $expensesList,
        ];
    }
}

