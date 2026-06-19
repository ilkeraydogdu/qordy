<?php
namespace App\Controllers;

require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../core/DependencyFactory.php';

/**
 * Stock Controller
 * Handles stock management operations
 */
class StockController extends \App\Core\Controller {
    protected $stockMovementService;
    protected $stockLocationService;
    protected $ingredientService;
    protected $constantsService;
    
    public function __construct() {
        parent::__construct();
        $this->stockMovementService = \App\Core\DependencyFactory::getStockMovementService();
        $this->stockLocationService = \App\Core\DependencyFactory::getStockLocationService();
        $this->ingredientService = \App\Core\DependencyFactory::getIngredientService();
        $this->constantsService = \App\Core\DependencyFactory::getConstantsService();
    }

    /**
     * Apply tenant context, honouring ?business_id= when the caller is a super
     * admin (the same pattern as MenuController / ReportsController). Regular
     * users always fall back to the session-based tenant. This makes every
     * /api/qodmin/stock/* endpoint transparently per-business.
     */
    protected function applyTenantContext(): void {
        if ($this->isSuperAdmin()) {
            $qp = \App\Core\RequestParser::getQueryParams();
            $requested = $qp['business_id'] ?? $qp['tenant_id'] ?? null;
            if ($requested) {
                try {
                    $cs = \App\Core\DependencyFactory::getCustomerService();
                    $customer = $cs->getById($requested);
                    if ($customer) {
                        \App\Core\TenantContext::set($customer);
                        $_SESSION['business_id'] = $requested;
                        $_SESSION['customer_id'] = $requested;
                        return;
                    }
                } catch (\Throwable $e) {
                    if (class_exists('\App\Core\Logger')) {
                        \App\Core\Logger::warning('StockController: failed to switch tenant', [
                            'business_id' => $requested,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }
            // Super admin without a business_id — skip strict ensure, endpoints
            // downstream will return an empty list rather than crashing.
            return;
        }
        // Regular user: session-based tenant resolution via parent helper.
        parent::ensureTenantContext();
    }

    /**
     * Check stock.view permission or fallback to finance permissions
     */
    protected function checkStockPermissionOrFail(): bool {
        // First check stock.view permission
        if ($this->hasPermission('stock.view')) {
            return true;
        }
        
        // If stock.view not found, check if user has any finance permissions
        // This allows users with finance permissions to access stock/inventory
        $financePermissions = ['finance.view', 'finance.expenses', 'finance.invoices', 'finance.suppliers', 'finance.waste'];
        foreach ($financePermissions as $perm) {
            if ($this->hasPermission($perm)) {
                return true;
            }
        }
        
        // No permission found - fail.
        // NOTE: ResponseHandler::error signature is (error, code, statusCode, errors).
        if (method_exists($this, 'isApiRequest') && $this->isApiRequest()) {
            \App\Core\ResponseHandler::error('Yetkilendirme hatası', 'UNAUTHORIZED', 401);
        } else {
            header('Location: ' . BASE_URL . '/unauthorized');
            exit;
        }
        return false;
    }
    
    /**
     * Stock management page
     */
    public function index() {
        $this->applyTenantContext();
        
        // CRITICAL: If tenant context is not set, try to set it from session
        if (!\App\Core\TenantContext::isSet()) {
            $customerId = \App\Core\TenantResolver::resolve();
            if ($customerId) {
                try {
                    $customerService = \App\Core\DependencyFactory::getCustomerService();
                    $customer = $customerService->getById($customerId);
                    if ($customer) {
                        \App\Core\TenantContext::set($customer);
                    }
                } catch (\Exception $e) {
                    if (class_exists('\App\Core\Logger')) {
                        \App\Core\Logger::error('Failed to set tenant context from session', [
                            'customer_id' => $customerId,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }
        }
        
        $this->checkStockPermissionOrFail();
        
        // Only pass static data needed for initial page load
        // All dynamic data will be loaded via JavaScript/API
        $locations = $this->stockLocationService->getAll();
        $movementTypes = $this->constantsService->getAsKeyLabel('STOCK_MOVEMENT_TYPE');
        $stockUnits = $this->constantsService->getAsKeyLabel('STOCK_UNIT');
        
        $data = [
            'locations' => $locations,
            'movement_types' => $movementTypes,
            'stock_units' => $stockUnits,
            'is_super_admin' => $this->isSuperAdmin()
        ];
        
        $this->view('admin/stock', $data);
    }
    
    /**
     * Get stock list (API)
     */
    public function getStockList() {
        $this->applyTenantContext();
        $this->checkStockPermissionOrFail();
        
        $stockList = $this->stockMovementService->getStockSummary();
        
        $this->apiResponse([
            'success' => true,
            'data' => $stockList,
            'count' => count($stockList)
        ]);
    }
    
    /**
     * Get stock summary/statistics (API)
     */
    public function getStockSummary() {
        try {
            $this->applyTenantContext();
            $this->checkStockPermissionOrFail();
            
            $stockList = $this->stockMovementService->getStockSummary();
            $lowStockAlerts = $this->stockMovementService->getLowStockAlerts();
            
            $totalItems = count($stockList);
            $outOfStock = 0;
            $lowStock = 0;
            $normalStock = 0;
            
            foreach ($stockList as $item) {
                if (isset($item['status'])) {
                    if ($item['status'] === 'OUT_OF_STOCK') {
                        $outOfStock++;
                    } elseif ($item['status'] === 'LOW_STOCK') {
                        $lowStock++;
                    } else {
                        $normalStock++;
                    }
                } else {
                    // If status is not set, calculate it
                    $currentStock = (float)($item['current_stock'] ?? 0);
                    $minThreshold = (float)($item['min_threshold'] ?? 0);
                    
                    if ($currentStock <= 0) {
                        $outOfStock++;
                    } elseif ($minThreshold > 0 && $currentStock <= $minThreshold) {
                        $lowStock++;
                    } else {
                        $normalStock++;
                    }
                }
            }
            
            $this->apiResponse([
                'success' => true,
                'summary' => [
                    'total_items' => $totalItems,
                    'out_of_stock' => $outOfStock,
                    'low_stock' => $lowStock,
                    'normal_stock' => $normalStock
                ],
                'alerts' => $lowStockAlerts
            ]);
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('StockController::getStockSummary - Error', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
            
            $this->apiResponse([
                'success' => false,
                'error' => 'Stok özeti yüklenirken hata oluştu',
                'summary' => [
                    'total_items' => 0,
                    'out_of_stock' => 0,
                    'low_stock' => 0,
                    'normal_stock' => 0
                ],
                'alerts' => []
            ], 500);
        }
    }
    
    /**
     * Get stock details for a specific item (API)
     */
    public function getStockDetails() {
        $this->applyTenantContext();
        $this->checkStockPermissionOrFail();
        
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $itemType = $queryParams['item_type'] ?? 'INGREDIENT';
        $itemId = $queryParams['item_id'] ?? '';
        
        if (empty($itemId)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
            return;
        }
        
        $stockDetails = $this->stockMovementService->getStockWithCalculatedValues($itemType, $itemId);
        $movements = $this->stockMovementService->getStockHistory($itemType, $itemId);
        
        if (!$stockDetails) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.not_found', [], 404);
            return;
        }
        
        $this->apiResponse([
            'success' => true,
            'stock' => $stockDetails,
            'movements' => array_slice($movements, 0, 50) // Last 50 movements
        ]);
    }
    
    /**
     * Get stock movements (API)
     */
    public function movements() {
        $this->applyTenantContext();
        $this->checkPermissionOrFail('stock.movements');
        
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $itemType = $queryParams['item_type'] ?? null;
        $itemId = $queryParams['item_id'] ?? null;
        $startDate = $queryParams['start_date'] ?? null;
        $endDate = $queryParams['end_date'] ?? null;
        $movementType = $queryParams['movement_type'] ?? null;
        $limit = isset($queryParams['limit']) ? (int)$queryParams['limit'] : 100;
        
        if ($itemType && $itemId) {
            $movements = $this->stockMovementService->getStockHistory($itemType, $itemId);
        } elseif ($startDate && $endDate) {
            $movements = $this->stockMovementService->getByDateRange($startDate, $endDate);
        } elseif ($movementType) {
            $movements = $this->stockMovementService->getByType($movementType);
        } else {
            $movements = $this->stockMovementService->getRecentMovementsWithDetails($limit);
        }
        
        $this->apiResponse([
            'success' => true,
            'data' => $movements,
            'count' => count($movements)
        ]);
    }
    
    /**
     * Add stock (API)
     */
    public function addStock() {
        $this->applyTenantContext();
        $this->checkPermissionOrFail('stock.edit');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_request', [], 405);
            return;
        }
        
        $requestData = \App\Core\RequestParser::getRequestData();
        $itemType = $requestData['item_type'] ?? 'INGREDIENT';
        $itemId = $requestData['item_id'] ?? '';
        $quantity = floatval($requestData['quantity'] ?? 0);
        $unit = $requestData['unit'] ?? 'ADET';
        $toLocationId = $requestData['to_location_id'] ?? null;
        $referenceType = $requestData['reference_type'] ?? null;
        $referenceId = $requestData['reference_id'] ?? null;
        $notes = $requestData['notes'] ?? null;
        
        if (empty($itemId) || $quantity <= 0) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
            return;
        }
        
        $result = $this->stockMovementService->addStock(
            $itemType,
            $itemId,
            $quantity,
            $unit,
            $toLocationId,
            $referenceType,
            $referenceId,
            $notes
        );
        
        if ($result) {
            // Get updated stock data
            $updatedStock = $this->stockMovementService->getStockWithCalculatedValues($itemType, $itemId);
            $this->apiResponse([
                'success' => true,
                'movement_id' => $result,
                'updated_stock' => $updatedStock
            ]);
        } else {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.stock_add_failed', [], 500);
        }
    }
    
    /**
     * Remove stock (API)
     */
    public function removeStock() {
        $this->applyTenantContext();
        $this->checkPermissionOrFail('stock.edit');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_request', [], 405);
            return;
        }
        
        $requestData = \App\Core\RequestParser::getRequestData();
        $itemType = $requestData['item_type'] ?? 'INGREDIENT';
        $itemId = $requestData['item_id'] ?? '';
        $quantity = floatval($requestData['quantity'] ?? 0);
        $unit = $requestData['unit'] ?? 'ADET';
        $fromLocationId = $requestData['from_location_id'] ?? null;
        $referenceType = $requestData['reference_type'] ?? null;
        $referenceId = $requestData['reference_id'] ?? null;
        $notes = $requestData['notes'] ?? null;
        
        if (empty($itemId) || $quantity <= 0) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
            return;
        }
        
        $result = $this->stockMovementService->removeStock(
            $itemType,
            $itemId,
            $quantity,
            $unit,
            $fromLocationId,
            $referenceType,
            $referenceId,
            $notes
        );
        
        if ($result) {
            // Get updated stock data
            $updatedStock = $this->stockMovementService->getStockWithCalculatedValues($itemType, $itemId);
            $this->apiResponse([
                'success' => true,
                'movement_id' => $result,
                'updated_stock' => $updatedStock
            ]);
        } else {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.stock_remove_failed', [], 500);
        }
    }
    
    /**
     * Transfer stock (API)
     */
    public function transferStock() {
        $this->applyTenantContext();
        $this->checkPermissionOrFail('stock.transfer');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_request', [], 405);
            return;
        }
        
        $requestData = \App\Core\RequestParser::getRequestData();
        $itemType = $requestData['item_type'] ?? 'INGREDIENT';
        $itemId = $requestData['item_id'] ?? '';
        $quantity = floatval($requestData['quantity'] ?? 0);
        $unit = $requestData['unit'] ?? 'ADET';
        $fromLocationId = $requestData['from_location_id'] ?? '';
        $toLocationId = $requestData['to_location_id'] ?? '';
        $notes = $requestData['notes'] ?? null;
        
        if (empty($itemId) || $quantity <= 0 || empty($fromLocationId) || empty($toLocationId)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
            return;
        }
        
        if ($fromLocationId === $toLocationId) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
            return;
        }
        
        $result = $this->stockMovementService->transferStock(
            $itemType,
            $itemId,
            $quantity,
            $unit,
            $fromLocationId,
            $toLocationId,
            $notes
        );
        
        if ($result) {
            // Get updated stock data
            $updatedStock = $this->stockMovementService->getStockWithCalculatedValues($itemType, $itemId);
            $this->apiResponse([
                'success' => true,
                'movement_id' => $result,
                'updated_stock' => $updatedStock
            ]);
        } else {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.stock_transfer_failed', [], 500);
        }
    }
    
    /**
     * Adjust stock (API)
     */
    public function adjustStock() {
        $this->applyTenantContext();
        $this->checkPermissionOrFail('stock.edit');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_request', [], 405);
            return;
        }
        
        $requestData = \App\Core\RequestParser::getRequestData();
        $itemType = $requestData['item_type'] ?? 'INGREDIENT';
        $itemId = $requestData['item_id'] ?? '';
        $newQuantity = floatval($requestData['new_quantity'] ?? 0);
        $unit = $requestData['unit'] ?? 'ADET';
        $locationId = $requestData['location_id'] ?? null;
        $notes = $requestData['notes'] ?? null;
        
        if (empty($itemId) || $newQuantity < 0) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
            return;
        }
        
        $result = $this->stockMovementService->adjustStock(
            $itemType,
            $itemId,
            $newQuantity,
            $unit,
            $locationId,
            $notes
        );
        
        if ($result) {
            // Get updated stock data
            $updatedStock = $this->stockMovementService->getStockWithCalculatedValues($itemType, $itemId);
            $this->apiResponse([
                'success' => true,
                'movement_id' => $result,
                'updated_stock' => $updatedStock
            ]);
        } else {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.stock_adjust_failed', [], 500);
        }
    }
    
    /**
     * Get current stock for an item (API)
     */
    public function getCurrentStock() {
        $this->applyTenantContext();
        $this->checkStockPermissionOrFail();
        
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $itemType = $queryParams['item_type'] ?? 'INGREDIENT';
        $itemId = $queryParams['item_id'] ?? '';
        
        if (empty($itemId)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
            return;
        }
        
        $currentStock = $this->stockMovementService->getCurrentStock($itemType, $itemId);
        $this->apiResponse(['current_stock' => $currentStock]);
    }

    /**
     * Low-stock alerts for the current tenant (both menu items and ingredients).
     * Powers the "stoğu azaldı" banner in the inventory UI.
     */
    public function getLowStock(): void {
        $this->applyTenantContext();
        $this->checkStockPermissionOrFail();

        $qp        = \App\Core\RequestParser::getQueryParams();
        $threshold = isset($qp['threshold']) ? max(0, (int)$qp['threshold']) : 10;

        // Ingredients: read from the ingredient repository directly. We intentionally
        // don't call StockMovementService::getLowStockAlerts() here — that helper
        // merges menu items into the ingredient payload, which would duplicate the
        // menu branch below.
        $ingredientAlerts = [];
        try {
            $ingRepo = \App\Core\DependencyFactory::getIngredientRepository();
            if ($ingRepo) {
                $ingLow = method_exists($ingRepo, 'getLowStock')   ? $ingRepo->getLowStock()   : [];
                $ingOut = method_exists($ingRepo, 'getOutOfStock') ? $ingRepo->getOutOfStock() : [];
                foreach ($ingOut as $row) {
                    $ingredientAlerts[] = [
                        'item_type'     => 'INGREDIENT',
                        'item_id'       => $row['ingredient_id'] ?? '',
                        'name'          => $row['name']          ?? '',
                        'current_stock' => (float)($row['current_stock'] ?? 0),
                        'min_threshold' => (float)($row['min_threshold'] ?? 0),
                        'unit'          => $row['unit']          ?? null,
                        'severity'      => 'out',
                    ];
                }
                foreach ($ingLow as $row) {
                    // Skip items already flagged OUT to avoid duplicates.
                    $id = $row['ingredient_id'] ?? '';
                    foreach ($ingredientAlerts as $already) {
                        if (($already['item_id'] ?? '') === $id) { continue 2; }
                    }
                    $ingredientAlerts[] = [
                        'item_type'     => 'INGREDIENT',
                        'item_id'       => $id,
                        'name'          => $row['name']          ?? '',
                        'current_stock' => (float)($row['current_stock'] ?? 0),
                        'min_threshold' => (float)($row['min_threshold'] ?? 0),
                        'unit'          => $row['unit']          ?? null,
                        'severity'      => 'low',
                    ];
                }
            }
        } catch (\Throwable $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::warning('StockController::getLowStock ingredient branch failed', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Menu items: union of LOW (stock>0 & under threshold) and OUT (stock<=0).
        $menuAlerts = [];
        try {
            $repo = \App\Core\DependencyFactory::getMenuItemRepository();
            if ($repo) {
                $lowItems = method_exists($repo, 'getLowStock')   ? $repo->getLowStock($threshold) : [];
                $outItems = method_exists($repo, 'getOutOfStock') ? $repo->getOutOfStock()         : [];
                foreach ($lowItems as $m) {
                    $menuAlerts[] = [
                        'item_type'     => 'MENU_ITEM',
                        'item_id'       => $m['menu_item_id'] ?? '',
                        'name'          => $m['name'] ?? '',
                        'current_stock' => (int)($m['stock'] ?? 0),
                        'min_threshold' => (int)($m['low_stock_threshold'] ?? 0),
                        'category'      => $m['category_name'] ?? null,
                        'severity'      => 'low',
                    ];
                }
                foreach ($outItems as $m) {
                    $menuAlerts[] = [
                        'item_type'     => 'MENU_ITEM',
                        'item_id'       => $m['menu_item_id'] ?? '',
                        'name'          => $m['name'] ?? '',
                        'current_stock' => 0,
                        'min_threshold' => (int)($m['low_stock_threshold'] ?? 0),
                        'category'      => $m['category_name'] ?? null,
                        'severity'      => 'out',
                    ];
                }
            }
        } catch (\Throwable $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::warning('StockController::getLowStock menu branch failed', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $all = array_merge($ingredientAlerts, $menuAlerts);
        $this->apiResponse([
            'success'   => true,
            'threshold' => $threshold,
            'counts'    => [
                'total' => count($all),
                'out'   => count(array_filter($all, static fn($a) => ($a['severity'] ?? '') === 'out')),
                'low'   => count(array_filter($all, static fn($a) => ($a['severity'] ?? '') === 'low')),
            ],
            'items'     => $all,
        ]);
    }

    /**
     * Update the low-stock threshold for a menu item. When set >0 the item
     * will appear in low-stock alerts as soon as its stock drops to or below
     * the configured value (stock=0 still hides it from the menu entirely).
     */
    public function updateThreshold(): void {
        $this->applyTenantContext();
        $this->checkPermissionOrFail('stock.edit');

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_request', [], 405);
            return;
        }

        $body      = \App\Core\RequestParser::getRequestData();
        $itemType  = strtoupper((string)($body['item_type'] ?? 'MENU_ITEM'));
        $itemId    = (string)($body['item_id'] ?? '');
        $threshold = max(0, (int)($body['threshold'] ?? 0));

        if ($itemId === '') {
            $this->apiResponse(['success' => false, 'error' => 'item_id_required'], 400);
            return;
        }

        try {
            $db       = \App\Core\DependencyFactory::getDatabase();
            $tenantId = \App\Core\TenantContext::getId();

            if ($itemType === 'MENU_ITEM') {
                $stmt = $db->prepare("UPDATE menu_items
                                      SET low_stock_threshold = :t
                                      WHERE menu_item_id = :id AND tenant_id = :tid");
                $stmt->execute([
                    ':t'   => $threshold,
                    ':id'  => $itemId,
                    ':tid' => $tenantId,
                ]);
            } elseif ($itemType === 'INGREDIENT') {
                // Tenant-scoped update: ingredients table now has tenant_id
                // (migration 20260423). Super admin writes without explicit tenant
                // are still possible via TenantContext switch earlier in the flow.
                if ($tenantId) {
                    $stmt = $db->prepare("UPDATE ingredients
                                          SET min_threshold = :t
                                          WHERE ingredient_id = :id AND tenant_id = :tid");
                    $stmt->execute([
                        ':t'   => $threshold,
                        ':id'  => $itemId,
                        ':tid' => $tenantId,
                    ]);
                } else {
                    $this->apiResponse(['success' => false, 'error' => 'tenant_required'], 400);
                    return;
                }
            } else {
                $this->apiResponse(['success' => false, 'error' => 'unsupported_item_type'], 400);
                return;
            }
            $this->apiResponse(['success' => true, 'threshold' => $threshold]);
        } catch (\Throwable $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('StockController::updateThreshold failed', [
                    'error' => $e->getMessage(), 'item_id' => $itemId,
                ]);
            }
            $this->apiResponse(['success' => false, 'error' => 'update_failed'], 500);
        }
    }
}

