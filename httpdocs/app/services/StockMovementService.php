<?php
namespace App\Services;

use App\Core\BaseService;
use App\Repositories\StockMovementRepository;
use App\Repositories\IngredientRepository;
use App\Repositories\ConstantsRepository;

require_once __DIR__ . '/../helpers/functions.php';

/**
 * Stock Movement Service
 * Handles stock movement business logic
 * 
 * @package App\Services
 */
class StockMovementService extends BaseService {
    private $ingredientRepository;
    private $constantsRepository;
    
    /**
     * Constructor
     * @param StockMovementRepository $repository Stock movement repository
     * @param IngredientRepository $ingredientRepository Ingredient repository
     * @param ConstantsRepository $constantsRepository Constants repository
     */
    public function __construct(
        StockMovementRepository $repository,
        IngredientRepository $ingredientRepository,
        ConstantsRepository $constantsRepository
    ) {
        parent::__construct($repository);
        $this->ingredientRepository = $ingredientRepository;
        $this->constantsRepository = $constantsRepository;
    }
    
    /**
     * Record a stock movement
     * @param array $data Movement data
     * @return bool|string Movement ID on success, false on failure
     */
    public function recordMovement(array $data) {
        // Validate required fields
        if (empty($data['item_type']) || empty($data['item_id']) || empty($data['movement_type'])) {
            return false;
        }
        
        // Generate movement ID if not provided
        if (empty($data['movement_id'])) {
            $data['movement_id'] = generateId('mov');
        }
        
        // Get current user if not provided
        if (empty($data['created_by'])) {
            require_once __DIR__ . '/../core/SessionManager.php';
            $data['created_by'] = \App\Core\SessionManager::get('user_id') ?? 'system';
        }

        // `stock_movements.unit` is NOT NULL in the schema; default when missing.
        if (empty($data['unit'])) {
            $data['unit'] = 'adet';
        }

        // Ensure tenant_id is set so inserts respect isolation. Prefer
        // caller-provided tenant_id over an implicit resolve to allow batch
        // operations in super-admin contexts.
        if (empty($data['tenant_id']) && class_exists('\App\Core\TenantResolver')) {
            try {
                $resolved = \App\Core\TenantResolver::resolve();
                if ($resolved) {
                    $data['tenant_id'] = $resolved;
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        // Record the movement
        $result = $this->repository->create($data);
        
        if ($result) {
            // Update stock based on item type
            if ($data['item_type'] === 'INGREDIENT') {
                $this->updateIngredientStock($data['item_id'], $data['movement_type'], $data['quantity']);
            } elseif ($data['item_type'] === 'MENU_ITEM') {
                $this->updateMenuItemStock($data['item_id'], $data['movement_type'], $data['quantity']);
            }
        }
        
        return $result ? $data['movement_id'] : false;
    }
    
    /**
     * Update ingredient stock based on movement
     * @param string $ingredientId Ingredient ID
     * @param string $movementType Movement type
     * @param float $quantity Quantity
     */
    private function updateIngredientStock(string $ingredientId, string $movementType, float $quantity) {
        $ingredient = $this->ingredientRepository->getById($ingredientId);
        if (!$ingredient) {
            return;
        }
        
        $currentStock = (float)($ingredient['current_stock'] ?? 0);
        
        // Calculate new stock based on movement type
        switch ($movementType) {
            case 'IN':
            case 'RETURN':
                $newStock = $currentStock + $quantity;
                $this->ingredientRepository->update($ingredientId, ['current_stock' => $newStock]);
                break;
                
            case 'OUT':
            case 'WASTE':
                $newStock = max(0, $currentStock - $quantity);
                $this->ingredientRepository->update($ingredientId, ['current_stock' => $newStock]);
                break;
                
            case 'ADJUSTMENT':
                // Adjustment sets stock to a specific value
                $newStock = $quantity;
                $this->ingredientRepository->update($ingredientId, ['current_stock' => $newStock]);
                break;
                
            case 'TRANSFER':
                // Transfer doesn't change total stock, handled by from/to locations
                // But we still update if it's a simple transfer
                break;
        }
    }

    /**
     * Update menu item stock based on movement
     * @param string $menuItemId Menu item ID
     * @param string $movementType Movement type
     * @param float $quantity Quantity
     */
    private function updateMenuItemStock(string $menuItemId, string $movementType, float $quantity) {
        $menuItem = \App\Core\DependencyFactory::getMenuItemService()->getMenuItemById($menuItemId);
        if (!$menuItem) {
            return;
        }

        // Only update if stock tracking is enabled for this menu item
        if (empty($menuItem['track_stock']) || $menuItem['track_stock'] != 1) {
            return;
        }

        // Instead of updating the stock in menu_items table directly,
        // we should record the movement in the stock movements system
        // and calculate the current stock from movements

        // For now, we'll still update the stock in the menu_items table for backward compatibility
        // but in the future, we should calculate from movements
        $currentStock = (int)($menuItem['stock'] ?? 0);

        // Calculate new stock based on movement type
        switch ($movementType) {
            case 'IN':
            case 'RETURN':
                $newStock = $currentStock + (int)$quantity;
                // If item was unavailable and now has stock, make it available
                $updateData = ['stock' => $newStock];
                if ($menuItem['is_available'] == 0 && $newStock > 0) {
                    $updateData['is_available'] = 1;
                }
                \App\Core\DependencyFactory::getMenuItemService()->updateMenuItem($menuItemId, $updateData);
                break;

            case 'OUT':
                $newStock = max(0, $currentStock - (int)$quantity);
                // If stock becomes 0, make item unavailable
                $updateData = ['stock' => $newStock];
                if ($newStock <= 0 && $menuItem['is_available'] == 1) {
                    $updateData['is_available'] = 0;
                }
                \App\Core\DependencyFactory::getMenuItemService()->updateMenuItem($menuItemId, $updateData);
                break;

            case 'WASTE':
                $newStock = max(0, $currentStock - (int)$quantity);
                \App\Core\DependencyFactory::getMenuItemService()->updateMenuItem($menuItemId, ['stock' => $newStock]);
                break;

            case 'ADJUSTMENT':
                // Adjustment sets stock to a specific value
                $newStock = (int)$quantity;
                $updateData = ['stock' => $newStock];
                if ($newStock <= 0 && $menuItem['is_available'] == 1) {
                    $updateData['is_available'] = 0;
                } elseif ($newStock > 0 && $menuItem['is_available'] == 0) {
                    $updateData['is_available'] = 1;
                }
                \App\Core\DependencyFactory::getMenuItemService()->updateMenuItem($menuItemId, $updateData);
                break;

            case 'TRANSFER':
                // Transfer doesn't change total stock, handled by from/to locations
                // But we still update if it's a simple transfer
                break;

            default:
                // Unknown movement type, don't update stock
                break;
        }

        // NOTE: Do NOT insert another stock_movements row here. The caller
        // (`recordMovement`) already inserted the movement record with the
        // correct movement_id. Creating a second row produced duplicates
        // in history and double-counted subsequent "stock from movements"
        // calculations.
    }

    /**
     * Get current stock for a menu item based on movements
     * @param string $menuItemId Menu item ID
     * @return int Current stock quantity
     */
    public function getCurrentStockForMenuItem(string $menuItemId): int {
        try {
            $initialStock = $this->repository->getInitialStockForMenuItem($menuItemId);
        } catch (\Throwable $e) {
            // Never let stock calculation crash the page; log and treat as zero.
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::warning('StockMovementService::getCurrentStockForMenuItem - initial stock lookup failed', [
                    'menu_item_id' => $menuItemId,
                    'error' => $e->getMessage(),
                ]);
            }
            $initialStock = 0;
        }

        // Get all stock movements for this menu item
        $movements = $this->repository->getByItem('MENU_ITEM', $menuItemId);

        // Calculate current stock from initial stock and movements
        $currentStock = $initialStock;
        foreach ($movements as $movement) {
            $quantity = (int)($movement['quantity'] ?? 0);
            $type = $movement['movement_type'] ?? '';

            switch ($type) {
                case 'IN':
                case 'RETURN':
                    $currentStock += $quantity;
                    break;
                case 'OUT':
                case 'WASTE':
                    $currentStock -= $quantity;
                    break;
                case 'ADJUSTMENT':
                    // For adjustment, set stock to the quantity value
                    $currentStock = $quantity;
                    break;
            }
        }

        // Ensure stock doesn't go below 0
        return max(0, $currentStock);
    }

    /**
     * Get stock history for an item
     * @param string $itemType Item type
     * @param string $itemId Item ID
     * @return array Stock movements
     */
    public function getStockHistory(string $itemType, string $itemId): array {
        return $this->repository->getByItem($itemType, $itemId);
    }

    /**
     * Get stock history for a menu item
     * @param string $menuItemId Menu item ID
     * @return array Stock movements
     */
    public function getMenuStockHistory(string $menuItemId): array {
        return $this->getStockHistory('MENU_ITEM', $menuItemId);
    }

    /**
     * Get stock history for an ingredient
     * @param string $ingredientId Ingredient ID
     * @return array Stock movements
     */
    public function getIngredientStockHistory(string $ingredientId): array {
        return $this->getStockHistory('INGREDIENT', $ingredientId);
    }

    /**
     * Get current stock calculated from movements
     * @param string $itemType Item type
     * @param string $itemId Item ID
     * @return float Current stock
     */
    public function getCurrentStock(string $itemType, string $itemId): float {
        return $this->repository->calculateCurrentStock($itemType, $itemId);
    }
    
    /**
     * Add stock (IN movement)
     * @param string $itemType Item type
     * @param string $itemId Item ID
     * @param float $quantity Quantity to add
     * @param string $unit Unit
     * @param string|null $toLocationId Target location
     * @param string|null $referenceType Reference type
     * @param string|null $referenceId Reference ID
     * @param string|null $notes Notes
     * @return bool|string Movement ID on success
     */
    public function addStock(
        string $itemType,
        string $itemId,
        float $quantity,
        string $unit,
        ?string $toLocationId = null,
        ?string $referenceType = null,
        ?string $referenceId = null,
        ?string $notes = null
    ) {
        return $this->recordMovement([
            'item_type' => $itemType,
            'item_id' => $itemId,
            'movement_type' => 'IN',
            'quantity' => $quantity,
            'unit' => $unit,
            'to_location_id' => $toLocationId,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'notes' => $notes
        ]);
    }
    
    /**
     * Remove stock (OUT movement)
     * @param string $itemType Item type
     * @param string $itemId Item ID
     * @param float $quantity Quantity to remove
     * @param string $unit Unit
     * @param string|null $fromLocationId Source location
     * @param string|null $referenceType Reference type
     * @param string|null $referenceId Reference ID
     * @param string|null $notes Notes
     * @return bool|string Movement ID on success
     */
    public function removeStock(
        string $itemType,
        string $itemId,
        float $quantity,
        string $unit,
        ?string $fromLocationId = null,
        ?string $referenceType = null,
        ?string $referenceId = null,
        ?string $notes = null
    ) {
        return $this->recordMovement([
            'item_type' => $itemType,
            'item_id' => $itemId,
            'movement_type' => 'OUT',
            'quantity' => $quantity,
            'unit' => $unit,
            'from_location_id' => $fromLocationId,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'notes' => $notes
        ]);
    }
    
    /**
     * Transfer stock between locations
     * @param string $itemType Item type
     * @param string $itemId Item ID
     * @param float $quantity Quantity to transfer
     * @param string $unit Unit
     * @param string $fromLocationId Source location
     * @param string $toLocationId Target location
     * @param string|null $notes Notes
     * @return bool|string Movement ID on success
     */
    public function transferStock(
        string $itemType,
        string $itemId,
        float $quantity,
        string $unit,
        string $fromLocationId,
        string $toLocationId,
        ?string $notes = null
    ) {
        return $this->recordMovement([
            'item_type' => $itemType,
            'item_id' => $itemId,
            'movement_type' => 'TRANSFER',
            'quantity' => $quantity,
            'unit' => $unit,
            'from_location_id' => $fromLocationId,
            'to_location_id' => $toLocationId,
            'notes' => $notes
        ]);
    }
    
    /**
     * Adjust stock (ADJUSTMENT movement)
     * @param string $itemType Item type
     * @param string $itemId Item ID
     * @param float $newQuantity New stock quantity
     * @param string $unit Unit
     * @param string|null $locationId Location
     * @param string|null $notes Notes
     * @return bool|string Movement ID on success
     */
    public function adjustStock(
        string $itemType,
        string $itemId,
        float $newQuantity,
        string $unit,
        ?string $locationId = null,
        ?string $notes = null
    ) {
        return $this->recordMovement([
            'item_type' => $itemType,
            'item_id' => $itemId,
            'movement_type' => 'ADJUSTMENT',
            'quantity' => $newQuantity,
            'unit' => $unit,
            'to_location_id' => $locationId,
            'notes' => $notes
        ]);
    }
    
    /**
     * Get movements by date range
     * @param string $startDate Start date
     * @param string $endDate End date
     * @return array Movements
     */
    public function getByDateRange(string $startDate, string $endDate): array {
        return $this->repository->getByDateRange($startDate, $endDate);
    }
    
    /**
     * Get movements by type
     * @param string $movementType Movement type
     * @return array Movements
     */
    public function getByType(string $movementType): array {
        return $this->repository->getByType($movementType);
    }
    
    /**
     * Get all movements
     * @return array All movements
     */
    public function getAll(): array {
        return $this->repository->getAll();
    }
    
    /**
     * Get movement types from constants
     * @return array Movement types
     */
    public function getMovementTypes(): array {
        return $this->constantsRepository->getByType('STOCK_MOVEMENT_TYPE');
    }
    
    /**
     * Get stock units from constants
     * @return array Stock units
     */
    public function getStockUnits(): array {
        return $this->constantsRepository->getByType('STOCK_UNIT');
    }
    
    /**
     * Get stock summary with all ingredients and their status
     * @return array Stock summary
     */
    public function getStockSummary(): array {
        $summary = $this->repository->getStockSummary();
        
        // Add calculated status for each item
        foreach ($summary as &$item) {
            $currentStock = (float)($item['current_stock'] ?? 0);
            $minThreshold = (float)($item['min_threshold'] ?? 0);
            
            // Determine stock status
            if ($currentStock <= 0) {
                $item['status'] = 'OUT_OF_STOCK';
                $item['status_label'] = 'Tükendi';
                $item['status_class'] = 'text-red-500';
            } elseif ($minThreshold > 0 && $currentStock <= $minThreshold) {
                $item['status'] = 'LOW_STOCK';
                $item['status_label'] = 'Düşük';
                $item['status_class'] = 'text-orange-500';
            } else {
                $item['status'] = 'NORMAL';
                $item['status_label'] = 'Normal';
                $item['status_class'] = 'text-green-500';
            }
        }
        
        return $summary;
    }
    
    /**
     * Get stock with calculated values
     * @param string $itemType Item type
     * @param string $itemId Item ID
     * @return array|null Stock data with calculated values
     */
    public function getStockWithCalculatedValues(string $itemType, string $itemId): ?array {
        if ($itemType === 'INGREDIENT') {
            $ingredient = $this->ingredientRepository->getById($itemId);
            if (!$ingredient) {
                return null;
            }
            
            $currentStock = (float)($ingredient['current_stock'] ?? 0);
            $minThreshold = (float)($ingredient['min_threshold'] ?? 0);
            $calculatedStock = $this->getCurrentStock($itemType, $itemId);
            
            // Determine stock status
            $status = 'NORMAL';
            $statusLabel = 'Normal';
            $statusClass = 'text-green-500';
            
            if ($currentStock <= 0) {
                $status = 'OUT_OF_STOCK';
                $statusLabel = 'Tükendi';
                $statusClass = 'text-red-500';
            } elseif ($minThreshold > 0 && $currentStock <= $minThreshold) {
                $status = 'LOW_STOCK';
                $statusLabel = 'Düşük';
                $statusClass = 'text-orange-500';
            }
            
            return [
                'ingredient_id' => $ingredient['ingredient_id'],
                'item_id' => $ingredient['ingredient_id'],
                'item_type' => 'INGREDIENT',
                'name' => $ingredient['name'],
                'unit' => $ingredient['unit'],
                'current_stock' => $currentStock,
                'calculated_stock' => $calculatedStock,
                'min_threshold' => $minThreshold,
                'par_level' => $ingredient['par_level'] ?? null,
                'status' => $status,
                'status_label' => $statusLabel,
                'status_class' => $statusClass,
                'stock_difference' => $currentStock - $calculatedStock
            ];
        } elseif ($itemType === 'MENU_ITEM') {
            $menuItemService = \App\Core\DependencyFactory::getMenuItemService();
            $menuItem = $menuItemService->getMenuItemById($itemId);
            if (!$menuItem || empty($menuItem['track_stock']) || $menuItem['track_stock'] != 1) {
                return null;
            }
            
            $currentStock = (int)($menuItem['stock'] ?? 0);
            $minThreshold = 5; // Fixed threshold for menu items
            $calculatedStock = $this->getCurrentStock($itemType, $itemId);
            
            // Determine stock status
            $status = 'NORMAL';
            $statusLabel = 'Normal';
            $statusClass = 'text-green-500';
            
            if ($currentStock <= 0) {
                $status = 'OUT_OF_STOCK';
                $statusLabel = 'Tükendi';
                $statusClass = 'text-red-500';
            } elseif ($currentStock <= $minThreshold) {
                $status = 'LOW_STOCK';
                $statusLabel = 'Düşük';
                $statusClass = 'text-orange-500';
            }
            
            return [
                'menu_item_id' => $menuItem['menu_item_id'],
                'item_id' => $menuItem['menu_item_id'],
                'item_type' => 'MENU_ITEM',
                'name' => $menuItem['name'],
                'unit' => 'ADET',
                'current_stock' => $currentStock,
                'calculated_stock' => $calculatedStock,
                'min_threshold' => $minThreshold,
                'par_level' => null,
                'status' => $status,
                'status_label' => $statusLabel,
                'status_class' => $statusClass,
                'stock_difference' => $currentStock - $calculatedStock
            ];
        }
        
        return null;
    }
    
    /**
     * Get recent movements with full item details
     * @param int $limit Limit number of results
     * @return array Movements with item details
     */
    public function getRecentMovementsWithDetails(int $limit = 100): array {
        return $this->repository->getMovementsWithItemDetails($limit);
    }
    
    /**
     * Get stock status for an item
     * @param string $itemType Item type
     * @param string $itemId Item ID
     * @return array Stock status
     */
    public function getStockStatus(string $itemType, string $itemId): array {
        if ($itemType !== 'INGREDIENT') {
            return ['status' => 'UNKNOWN', 'status_label' => 'Bilinmiyor'];
        }
        
        $ingredient = $this->ingredientRepository->getById($itemId);
        if (!$ingredient) {
            return ['status' => 'NOT_FOUND', 'status_label' => 'Bulunamadı'];
        }
        
        $currentStock = (float)($ingredient['current_stock'] ?? 0);
        $minThreshold = (float)($ingredient['min_threshold'] ?? 0);
        
        if ($currentStock <= 0) {
            return [
                'status' => 'OUT_OF_STOCK',
                'status_label' => 'Tükendi',
                'status_class' => 'text-red-500'
            ];
        } elseif ($minThreshold > 0 && $currentStock <= $minThreshold) {
            return [
                'status' => 'LOW_STOCK',
                'status_label' => 'Düşük',
                'status_class' => 'text-orange-500'
            ];
        } else {
            return [
                'status' => 'NORMAL',
                'status_label' => 'Normal',
                'status_class' => 'text-green-500'
            ];
        }
    }
    
    /**
     * Get low stock alerts (for both ingredients and menu items)
     * @return array Low stock items
     */
    public function getLowStockAlerts(): array {
        $alerts = [];
        
        // Get ingredient alerts
        $lowStock = $this->ingredientRepository->getLowStock();
        $outOfStock = $this->ingredientRepository->getOutOfStock();
        
        foreach ($outOfStock as $item) {
            $alerts[] = [
                'item_id' => $item['ingredient_id'],
                'item_type' => 'INGREDIENT',
                'ingredient_id' => $item['ingredient_id'],
                'name' => $item['name'],
                'current_stock' => (float)($item['current_stock'] ?? 0),
                'min_threshold' => (float)($item['min_threshold'] ?? 0),
                'unit' => $item['unit'] ?? 'ADET',
                'alert_type' => 'OUT_OF_STOCK',
                'alert_message' => $item['name'] . ' tükendi!'
            ];
        }
        
        foreach ($lowStock as $item) {
            // Skip if already in out of stock
            $alreadyAdded = false;
            foreach ($alerts as $alert) {
                if (isset($alert['ingredient_id']) && $alert['ingredient_id'] === $item['ingredient_id']) {
                    $alreadyAdded = true;
                    break;
                }
            }
            
            if (!$alreadyAdded) {
                $alerts[] = [
                    'item_id' => $item['ingredient_id'],
                    'item_type' => 'INGREDIENT',
                    'ingredient_id' => $item['ingredient_id'],
                    'name' => $item['name'],
                    'current_stock' => (float)($item['current_stock'] ?? 0),
                    'min_threshold' => (float)($item['min_threshold'] ?? 0),
                    'unit' => $item['unit'] ?? 'ADET',
                    'alert_type' => 'LOW_STOCK',
                    'alert_message' => $item['name'] . ' stoku düşük!'
                ];
            }
        }
        
        // Get menu item alerts (only items with track_stock = 1 and stock <= 5)
        require_once __DIR__ . '/../core/DependencyFactory.php';
        $db = \App\Core\DependencyFactory::getDatabase();
        
        // Get tenant filter for menu items - use reflection or direct tenant context
        $menuItemWhereConditions = ["mi.track_stock = 1", "mi.stock <= 5"];
        $menuItemParams = [];
        
        // Get tenant ID from session or context
        $businessId = \App\Core\TenantResolver::resolve();
        if (!$businessId && class_exists('\App\Core\TenantContext')) {
            try {
                $businessId = \App\Core\TenantContext::getId();
            } catch (\Exception $e) {
                // Tenant context not available
            }
        }
        
        // Add tenant filter if business_id exists
        if ($businessId && \App\Core\DbSchema::hasColumn('menu_items', 'tenant_id')) {
            $menuItemWhereConditions[] = "mi.tenant_id = :menu_tenant_filter_id";
            $menuItemParams['menu_tenant_filter_id'] = $businessId;
        }
        
        $menuItemWhereClause = "WHERE " . implode(" AND ", $menuItemWhereConditions);
        
        $menuItemLowStockSql = "SELECT mi.menu_item_id, mi.name, mi.stock 
                                 FROM menu_items mi 
                                 {$menuItemWhereClause} 
                                 ORDER BY mi.stock ASC";
        
        $stmt = $db->prepare($menuItemLowStockSql);
        $stmt->execute($menuItemParams);
        $menuItemLowStock = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        foreach ($menuItemLowStock as $item) {
            $stock = (int)($item['stock'] ?? 0);
            $menuItemId = $item['menu_item_id'];
            
            // Check if already added
            $alreadyAdded = false;
            foreach ($alerts as $alert) {
                if (isset($alert['menu_item_id']) && $alert['menu_item_id'] === $menuItemId) {
                    $alreadyAdded = true;
                    break;
                }
            }
            
            if (!$alreadyAdded) {
                $alertType = $stock <= 0 ? 'OUT_OF_STOCK' : 'LOW_STOCK';
                $message = $stock <= 0 
                    ? $item['name'] . ' tükendi!' 
                    : $item['name'] . ' stoku düşük! (Son ' . $stock . ' adet)';
                
                $alerts[] = [
                    'item_id' => $menuItemId,
                    'item_type' => 'MENU_ITEM',
                    'menu_item_id' => $menuItemId,
                    'name' => $item['name'],
                    'current_stock' => $stock,
                    'min_threshold' => 5,
                    'unit' => 'ADET',
                    'alert_type' => $alertType,
                    'alert_message' => $message
                ];
            }
        }
        
        return $alerts;
    }
}

