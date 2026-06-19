<?php
namespace App\Controllers;

require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../core/DependencyFactory.php';
require_once __DIR__ . '/../models/SystemLabel.php';

class FinanceController extends \App\Core\Controller {
    protected $financeService;
    protected $orderService;
    protected $wasteRecordService;
    
    public function __construct() {
        parent::__construct(); // Initialize auth and other services
        $this->financeService = \App\Core\DependencyFactory::getFinanceService();
        $this->orderService = \App\Core\DependencyFactory::getOrderService();
        $this->wasteRecordService = \App\Core\DependencyFactory::getWasteRecordService();
    }

    /**
     * Resolve tenant context for a finance page request.
     *
     * Canonical order:
     *   1. super-admin + ?business_id=X → switch to that tenant
     *   2. super-admin + no business_id → return null (caller shows picker)
     *   3. regular user                 → fall back to session tenant
     *
     * This mirrors MenuController / ReportsController / StockController so all
     * finance tabs (expenses/invoices/suppliers/waste) behave identically.
     *
     * @return string|null Tenant id in effect, or null if a picker is needed.
     */
    protected function applyFinanceTenant(): ?string {
        $isSuper     = $this->isSuperAdmin();
        $qp          = \App\Core\RequestParser::getQueryParams();
        $requestedId = $qp['business_id'] ?? null;

        if ($isSuper) {
            if (!$requestedId) {
                // Caller decides whether to render a picker or an empty state.
                return null;
            }
            try {
                $cs = \App\Core\DependencyFactory::getCustomerService();
                $customer = $cs->getById($requestedId);
                if ($customer) {
                    \App\Core\TenantContext::set($customer);
                    $_SESSION['business_id'] = $requestedId;
                    $_SESSION['customer_id'] = $requestedId;
                    return $requestedId;
                }
            } catch (\Throwable $e) {
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::warning('FinanceController: tenant switch failed', [
                        'business_id' => $requestedId,
                        'error'       => $e->getMessage(),
                    ]);
                }
            }
            return null;
        }

        // Regular user: session-based tenant resolution.
        $this->ensureTenantContext();
        return \App\Core\TenantContext::getId();
    }
    
    public function index() {
        $this->requirePermission('finance.view');
        
        // Super admin kontrolü
        $isSuperAdmin = $this->isSuperAdmin();
        
        // İşletme ID'sini query parametresinden al (super admin için)
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $businessId = $queryParams['business_id'] ?? null;
        
        if ($isSuperAdmin) {
            if ($businessId) {
                // Tenant context'i işletme ID'sine göre set et
                try {
                    $customerService = \App\Core\DependencyFactory::getCustomerService();
                    $customer = $customerService->getById($businessId);
                    if ($customer) {
                        \App\Core\TenantContext::set($customer);
                    }
                } catch (\Exception $e) {
                    if (class_exists('\App\Core\Logger')) {
                        \App\Core\Logger::error('Failed to set tenant context from business_id', [
                            'business_id' => $businessId,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            } else {
                // Super admin için business_id yoksa, businesses listesini göster
                try {
                    $customerService = \App\Core\DependencyFactory::getCustomerService();
                    $businesses = $customerService->getAllBusinesses();
                    
                    $data = [
                        'current_shift' => null,
                        'date_range' => 'today',
                        'start_date' => date('Y-m-d'),
                        'end_date' => date('Y-m-d'),
                        'kpis' => [],
                        'revenue_trend' => [],
                        'expense_trend' => [],
                        'category_breakdown' => [],
                        'monthly_comparison' => [],
                        'recent_expenses' => [],
                        'unpaid_invoices' => [],
                        'top_expenses' => [],
                        'cash_flow' => [],
                        'is_super_admin' => $isSuperAdmin,
                        'businesses' => $businesses,
                        'message' => 'Lütfen bir işletme seçin'
                    ];
                    
                    $this->view('admin/finance/index', $data);
                    return;
                } catch (\Exception $e) {
                    if (class_exists('\App\Core\Logger')) {
                        \App\Core\Logger::error('FinanceController::index - Failed to load businesses', [
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }
        }
        
        // Tenant context'i ensure et (super admin için business_id varsa zaten set edildi)
        // Super Admin için business_id yoksa tenant context set etme (tüm finansal verileri görmek için)
        if ($isSuperAdmin && !$businessId) {
            // Super Admin ve business_id yok - tenant context set etme, tüm finansal verileri göster
        } else {
            $this->ensureTenantContext();
        }
        
        $currentShift = null; // Shift management removed
        
        // Get date range (default: today)
        $queryParams = \App\Core\RequestParser::getQueryParams();
$dateRange = $queryParams['date_range'] ?? 'today'; $startDate = date('Y-m-d'); $endDate = date('Y-m-d'); switch ($dateRange) { case 'today': $startDate = date('Y-m-d'); $endDate = date('Y-m-d'); break; case 'week': $startDate = date('Y-m-d', strtotime('-7 days')); $endDate = date('Y-m-d'); break; case 'month': $startDate = date('Y-m-01'); $endDate = date('Y-m-d'); break; case 'custom': $startDate = $queryParams['start_date'] ?? date('Y-m-d'); $endDate = $queryParams['end_date'] ?? $startDate;  $validatedStart = \DateTime::createFromFormat('Y-m-d', $startDate); $validatedEnd = \DateTime::createFromFormat('Y-m-d', $endDate); if ($validatedStart === false) { $startDate = date('Y-m-d'); } if ($validatedEnd === false) { $endDate = $startDate; } if (strtotime($endDate) < strtotime($startDate)) { $endDate = $startDate; } break; }
        
        // Get financial summary
        $summary = $this->financeService->getFinancialSummary($startDate, $endDate, $this->orderService);
        
        // Get trends (last 7 days)
        $revenueTrend = $this->financeService->getRevenueTrend(7, $this->orderService);
        $expenseTrend = $this->financeService->getExpenseTrend(7);
        
        // Get category breakdown
        $categoryBreakdown = $this->financeService->getCategoryBreakdown($startDate, $endDate);
        
        // Get monthly comparison (last 6 months)
        $monthlyComparison = $this->financeService->getMonthlyComparison(6, $this->orderService);
        
        // Get recent data
        $recentExpenses = $this->financeService->getRecentExpenses(10);
        $unpaidInvoices = $this->financeService->getUnpaidInvoices();
        $topExpenses = $this->financeService->getTopExpenses(5, $startDate, $endDate);
        
        // Get cash flow
        $cashFlow = $this->financeService->getCashFlow($startDate, $endDate, $this->orderService);
        
        $data = [
            'current_shift' => $currentShift,
            'date_range' => $dateRange,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'kpis' => [
                'daily_revenue' => $summary['revenue'],
                'daily_expenses' => $summary['expenses'],
                'net_profit' => $summary['net_profit'],
                'profit_margin' => $summary['profit_margin'],
                'avg_order_value' => $summary['avg_order_value'],
                'order_count' => $summary['order_count']
            ],
            'charts' => [
                'revenue_expense_trend' => [
                    'revenue' => $revenueTrend,
                    'expenses' => $expenseTrend
                ],
                'category_breakdown' => $categoryBreakdown,
                'monthly_comparison' => $monthlyComparison
            ],
            'recent_expenses' => $recentExpenses,
            'unpaid_invoices' => $unpaidInvoices,
            'top_expenses' => $topExpenses,
            'cash_flow' => $cashFlow,
            'is_super_admin' => $isSuperAdmin
        ];
        
        $this->view('admin/finance/index', $data);
    }
    
    /**
     * Get financial data via API (for AJAX requests)
     */
    public function getFinancialData() {
        // CRITICAL: Ensure tenant context is set before fetching financial data
        $this->ensureTenantContext();
        $this->requirePermission('finance.view');
        
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $dateRange = $queryParams['date_range'] ?? 'today';
        $startDate = date('Y-m-d');
        $endDate = date('Y-m-d');
        
        switch ($dateRange) {
 case "today":
 $startDate = date("Y-m-d");
 $endDate = date("Y-m-d");
 break;
 case "week":
 $startDate = date("Y-m-d", strtotime("-7 days"));
 $endDate = date("Y-m-d");
 break;
 case "month":
 $startDate = date("Y-m-01");
 $endDate = date("Y-m-d");
 break;
 case "custom":
 $startDate = $queryParams["start_date"] ?? date("Y-m-d");
 $endDate = $queryParams["end_date"] ?? $startDate;
 $validatedStart = \DateTime::createFromFormat("Y-m-d", $startDate);
 $validatedEnd = \DateTime::createFromFormat("Y-m-d", $endDate);
 if ($validatedStart === false) {
 $startDate = date("Y-m-d");
 }
 if ($validatedEnd === false) {
 $endDate = $startDate;
 }
 if (strtotime($endDate) < strtotime($startDate)) {
 $endDate = $startDate;
 }
 break;
}
        
        // Get financial summary
        $summary = $this->financeService->getFinancialSummary($startDate, $endDate, $this->orderService);
        
        // Get trends
        $revenueTrend = $this->financeService->getRevenueTrend(7, $this->orderService);
        $expenseTrend = $this->financeService->getExpenseTrend(7);
        
        // Get category breakdown
        $categoryBreakdown = $this->financeService->getCategoryBreakdown($startDate, $endDate);
        
        // Get monthly comparison
        $monthlyComparison = $this->financeService->getMonthlyComparison(6, $this->orderService);
        
        // Get recent data
        $recentExpenses = $this->financeService->getRecentExpenses(10);
        $unpaidInvoices = $this->financeService->getUnpaidInvoices();
        $topExpenses = $this->financeService->getTopExpenses(5, $startDate, $endDate);
        
        // Get cash flow
        $cashFlow = $this->financeService->getCashFlow($startDate, $endDate, $this->orderService);
        
        $this->apiResponse([
            'success' => true,
            'data' => [
                'kpis' => $summary,
                'charts' => [
                    'revenue_expense_trend' => [
                        'revenue' => $revenueTrend,
                        'expenses' => $expenseTrend
                    ],
                    'category_breakdown' => $categoryBreakdown,
                    'monthly_comparison' => $monthlyComparison
                ],
                'recent_expenses' => $recentExpenses,
                'unpaid_invoices' => $unpaidInvoices,
                'top_expenses' => $topExpenses,
                'cash_flow' => $cashFlow
            ]
        ]);
    }
    
    public function expenses() {
        $this->requirePermission('finance.expenses');

        $isSuperAdmin = $this->isSuperAdmin();
        $tenantId     = $this->applyFinanceTenant();

        // Super admin without a selection → picker only, no data load (avoids
        // leaking stale rows from the previous session tenant).
        if ($isSuperAdmin && !$tenantId) {
            $this->view('admin/finance/expenses', [
                'expenses'           => [],
                'suppliers'          => [],
                'expense_categories' => [],
                'is_super_admin'     => true,
            ]);
            return;
        }

        try {
            // Prefer tenant-scoped categories from finance_categories. Fall back
            // to legacy system_labels only if the tenant has not created any yet.
            $expenseCategories = \App\Core\DependencyFactory::getFinanceCategoryService()
                ->list(\App\Services\FinanceCategoryService::TYPE_EXPENSE);
        } catch (\Throwable $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('Failed to load expense categories', ['error' => $e->getMessage()]);
            }
            $expenseCategories = [];
        }

        $this->view('admin/finance/expenses', [
            'expenses'           => $this->financeService->getAllExpenses(),
            'suppliers'          => $this->financeService->getAllSuppliers(),
            'expense_categories' => $expenseCategories,
            'is_super_admin'     => $isSuperAdmin,
        ]);
    }

    public function invoices() {
        $this->requirePermission('finance.invoices');

        $isSuperAdmin = $this->isSuperAdmin();
        $tenantId     = $this->applyFinanceTenant();

        if ($isSuperAdmin && !$tenantId) {
            $this->view('admin/finance/invoices', [
                'invoices'       => [],
                'suppliers'      => [],
                'is_super_admin' => true,
            ]);
            return;
        }

        $this->view('admin/finance/invoices', [
            'invoices'       => $this->financeService->getAllInvoices(),
            'suppliers'      => $this->financeService->getAllSuppliers(),
            'is_super_admin' => $isSuperAdmin,
        ]);
    }

    public function suppliers() {
        $this->requirePermission('finance.suppliers');

        $isSuperAdmin = $this->isSuperAdmin();
        $tenantId     = $this->applyFinanceTenant();

        if ($isSuperAdmin && !$tenantId) {
            $this->view('admin/finance/suppliers', [
                'suppliers'           => [],
                'supplier_categories' => [],
                'is_super_admin'      => true,
            ]);
            return;
        }

        try {
            $supplierCategories = \App\Core\DependencyFactory::getFinanceCategoryService()
                ->list(\App\Services\FinanceCategoryService::TYPE_SUPPLIER);
        } catch (\Throwable $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('Failed to load supplier categories', ['error' => $e->getMessage()]);
            }
            $supplierCategories = [];
        }

        $this->view('admin/finance/suppliers', [
            'suppliers'           => $this->financeService->getAllSuppliers(),
            'supplier_categories' => $supplierCategories,
            'is_super_admin'      => $isSuperAdmin,
        ]);
    }

    public function waste() {
        $this->requirePermission('finance.waste');

        $isSuperAdmin = $this->isSuperAdmin();
        $tenantId     = $this->applyFinanceTenant();

        if ($isSuperAdmin && !$tenantId) {
            $this->view('admin/finance/waste', [
                'waste_records'  => [],
                'is_super_admin' => true,
            ]);
            return;
        }

        $this->view('admin/finance/waste', [
            'waste_records'  => $this->wasteRecordService->getAll(),
            'is_super_admin' => $isSuperAdmin,
        ]);
    }
    
    // Shift management removed - startShift and endShift methods no longer needed

    // -----------------------------------------------------------------------
    // Finance categories (supplier + expense) — CRUD endpoints
    // -----------------------------------------------------------------------

    /**
     * Resolve the tenant-scoped category service. Centralizes permission and
     * tenant context checks so every endpoint below can stay tiny.
     */
    private function categoryService(): \App\Services\FinanceCategoryService {
        $this->ensureTenantContext();
        return \App\Core\DependencyFactory::getFinanceCategoryService();
    }

    private function categoryTypeFromRequest(): string {
        $qp  = \App\Core\RequestParser::getQueryParams();
        $raw = strtoupper(trim((string)($qp['type'] ?? $_POST['type'] ?? '')));
        if (!in_array($raw, ['SUPPLIER', 'EXPENSE'], true)) {
            $this->apiResponse(['success' => false, 'error' => 'type parametresi SUPPLIER veya EXPENSE olmalı.'], 400);
        }
        return $raw;
    }

    /**
     * GET /api/business/finance/categories?type=SUPPLIER|EXPENSE
     * Lists every non-archived category for the current tenant. Super-admins
     * may pass ?business_id=X to switch tenant.
     */
    public function listCategories(): void {
        // Suppliers and expenses share this list; either permission is fine.
        if (!$this->hasPermission('finance.suppliers') && !$this->hasPermission('finance.expenses')) {
            $this->apiResponse(['success' => false, 'error' => 'Yetkisiz.'], 403);
            return;
        }
        $this->applyFinanceTenant();
        $qp   = \App\Core\RequestParser::getQueryParams();
        $type = isset($qp['type']) ? strtoupper(trim((string)$qp['type'])) : null;
        if ($type !== null && !in_array($type, ['SUPPLIER', 'EXPENSE'], true)) {
            $this->apiResponse(['success' => false, 'error' => 'type parametresi SUPPLIER veya EXPENSE olmalı.'], 400);
            return;
        }
        try {
            $categories = $this->categoryService()->list($type);
            $this->apiResponse(['success' => true, 'categories' => $categories]);
        } catch (\Throwable $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('listCategories failed', ['error' => $e->getMessage()]);
            }
            $this->apiResponse(['success' => false, 'error' => 'Kategoriler yüklenemedi.'], 500);
        }
    }

    /**
     * POST /api/business/finance/categories
     * Body: type=SUPPLIER|EXPENSE, label=..., color?=#rrggbb
     */
    public function createCategory(): void {
        $type = $this->categoryTypeFromRequest();
        $perm = $type === 'SUPPLIER' ? 'finance.suppliers' : 'finance.expenses';
        $this->requirePermission($perm);
        $this->applyFinanceTenant();

        $label = trim((string)($_POST['label'] ?? ''));
        $color = trim((string)($_POST['color'] ?? ''));
        try {
            $row = $this->categoryService()->create($type, $label, $color !== '' ? $color : null);
            $this->apiResponse(['success' => true, 'category' => $row]);
        } catch (\Throwable $e) {
            $this->apiResponse(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    /**
     * POST /api/business/finance/categories/rename
     * Body: category_id, label
     */
    public function renameCategory(): void {
        $categoryId = trim((string)($_POST['category_id'] ?? ''));
        $newLabel   = trim((string)($_POST['label'] ?? ''));
        if ($categoryId === '' || $newLabel === '') {
            $this->apiResponse(['success' => false, 'error' => 'category_id ve label zorunlu.'], 400);
            return;
        }
        $this->applyFinanceTenant();
        // Use the category row's type to pick the right permission.
        $repo    = \App\Core\DependencyFactory::getFinanceCategoryRepository();
        $current = $repo->getById($categoryId);
        if (!$current) {
            $this->apiResponse(['success' => false, 'error' => 'Kategori bulunamadı.'], 404);
            return;
        }
        $perm = ($current['type'] ?? '') === 'SUPPLIER' ? 'finance.suppliers' : 'finance.expenses';
        $this->requirePermission($perm);

        try {
            $this->categoryService()->rename($categoryId, $newLabel);
            $this->apiResponse(['success' => true]);
        } catch (\Throwable $e) {
            $this->apiResponse(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    /**
     * POST /api/business/finance/categories/delete
     * Body: category_id
     * Archives the row if any supplier/expense references it; hard deletes
     * otherwise.
     */
    public function deleteCategory(): void {
        $categoryId = trim((string)($_POST['category_id'] ?? ''));
        if ($categoryId === '') {
            $this->apiResponse(['success' => false, 'error' => 'category_id zorunlu.'], 400);
            return;
        }
        $this->applyFinanceTenant();
        $repo    = \App\Core\DependencyFactory::getFinanceCategoryRepository();
        $current = $repo->getById($categoryId);
        if (!$current) {
            $this->apiResponse(['success' => false, 'error' => 'Kategori bulunamadı.'], 404);
            return;
        }
        $perm = ($current['type'] ?? '') === 'SUPPLIER' ? 'finance.suppliers' : 'finance.expenses';
        $this->requirePermission($perm);

        try {
            $result = $this->categoryService()->delete($categoryId);
            $this->apiResponse(['success' => true] + $result);
        } catch (\Throwable $e) {
            $this->apiResponse(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    // ======================================================================
    // Finance analytics endpoints (qodmin + business finance dashboards)
    // ======================================================================

    /**
     * Resolve the date range from the request. Accepts either a preset
     * (?range=today|yesterday|week|month|30d|90d|year) or explicit
     * ?start=YYYY-MM-DD&end=YYYY-MM-DD.
     */
    private function rangeFromRequest(): array {
        $qp    = \App\Core\RequestParser::getQueryParams();
        $range = (string)($qp['range'] ?? 'month');
        $start = isset($qp['start']) ? trim((string)$qp['start']) : null;
        $end   = isset($qp['end'])   ? trim((string)$qp['end'])   : null;
        if ($start && $end) {
            $range = 'custom';
        }
        return \App\Services\FinanceAnalyticsService::resolveDateRange($range, $start, $end);
    }

    /**
     * GET /api/business/finance/analytics
     *
     * Returns a bundle of cross-domain KPIs used by the /qodmin/finance and
     * /business/finance dashboards: stock overview, waste overview + trend +
     * by-reason breakdown, supplier overview + top suppliers.
     */
    public function analytics(): void {
        $this->requirePermission('finance.view');
        $tenantId = $this->applyFinanceTenant();
        // If super admin opens the page without picking a business, return an
        // empty payload so the front-end can prompt for a business selection.
        if (!$tenantId && $this->isSuperAdmin()) {
            $this->apiResponse([
                'success'        => true,
                'needs_business' => true,
            ]);
            return;
        }

        [$start, $end] = $this->rangeFromRequest();
        try {
            $svc = \App\Core\DependencyFactory::getFinanceAnalyticsService();
            $this->apiResponse([
                'success'          => true,
                'range'            => ['start' => $start, 'end' => $end],
                'stock'            => $svc->getStockOverview(),
                'stock_types'      => $svc->getStockTypeCounts(),
                'waste'            => $svc->getWasteOverview($start, $end),
                'waste_by_reason'  => $svc->getWasteByReason($start, $end),
                'waste_trend'      => $svc->getWasteTrend(14),
                'top_wasted'       => $svc->getTopWastedIngredients($start, $end, 10),
                'suppliers'        => $svc->getSupplierOverview($start, $end),
                'top_suppliers'    => $svc->getTopSuppliers($start, $end, 10),
            ]);
        } catch (\Throwable $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('analytics failed', ['error' => $e->getMessage()]);
            }
            $this->apiResponse([
                'success' => false,
                'error'   => 'Analitik verileri yüklenemedi: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/business/finance/suppliers/{id}/analytics
     *
     * Drill-down for a single supplier: purchase receipts, returns, waste,
     * stocked ingredients and unpaid invoices within the selected window.
     */
    public function supplierAnalytics(string $supplierId = ''): void {
        $this->requirePermission('finance.suppliers');
        $supplierId = trim($supplierId);
        if ($supplierId === '') {
            $qp         = \App\Core\RequestParser::getQueryParams();
            $supplierId = trim((string)($qp['supplier_id'] ?? ''));
        }
        if ($supplierId === '') {
            $this->apiResponse(['success' => false, 'error' => 'supplier_id zorunlu.'], 400);
            return;
        }
        $this->applyFinanceTenant();
        [$start, $end] = $this->rangeFromRequest();
        try {
            $svc     = \App\Core\DependencyFactory::getFinanceAnalyticsService();
            $payload = $svc->getSupplierDetail($supplierId, $start, $end);
            if (empty($payload['supplier'])) {
                $this->apiResponse(['success' => false, 'error' => 'Tedarikçi bulunamadı.'], 404);
                return;
            }
            $this->apiResponse([
                'success' => true,
                'range'   => ['start' => $start, 'end' => $end],
            ] + $payload);
        } catch (\Throwable $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('supplierAnalytics failed', [
                    'supplier_id' => $supplierId,
                    'error'       => $e->getMessage(),
                ]);
            }
            $this->apiResponse([
                'success' => false,
                'error'   => 'Tedarikçi detayı yüklenemedi: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /business/finance/suppliers/{id}
     * Renders the supplier detail HTML page.
     */
    public function supplierDetail(string $supplierId = ''): void {
        $this->requirePermission('finance.suppliers');
        $supplierId = trim($supplierId);
        if ($supplierId === '') {
            header('Location: /business/finance/suppliers');
            exit;
        }
        $tenantId = $this->applyFinanceTenant();
        if (!$tenantId) {
            header('Location: /business/finance/suppliers');
            exit;
        }
        $supplierRepo = \App\Core\DependencyFactory::getSupplierRepository();
        $supplier     = $supplierRepo->findById($supplierId);
        if (!$supplier) {
            header('Location: /business/finance/suppliers');
            exit;
        }
        $isQodmin = str_contains($_SERVER['REQUEST_URI'] ?? '', '/qodmin/');
        $data = [
            'supplier'  => $supplier,
            'isQodmin'  => $isQodmin,
            'businessId'=> $tenantId,
        ];
        $this->render('admin/finance/supplier-detail', $data);
    }
}

