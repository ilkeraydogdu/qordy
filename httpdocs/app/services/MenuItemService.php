<?php
namespace App\Services;

use App\Core\BaseService;
use App\Repositories\MenuItemRepository;

class MenuItemService extends BaseService {
    
    public function __construct(MenuItemRepository $menuItemRepository) {
        parent::__construct($menuItemRepository);
    }
    
    /**
     * Get menu items by business (for SUPER_ADMIN)
     * @param string $businessId Business ID
     * @return array Menu items for the specific business
     */
    public function getMenuItemsByBusiness(string $businessId): array {
        $cache = \App\Core\DependencyFactory::getCacheService();
        $cacheKey = 'menu:items:business:' . $businessId;

        return $cache->remember($cacheKey, function() use ($businessId) {
            return $this->repository->getByTenantId($businessId);
        }, 600); // Cache for 10 minutes (optimized for fresh data)
    }

    /**
     * Get tenant ID for cache key
     * @return string
     */
    private function getTenantIdForCache(): string {
        $tenantId = \App\Core\TenantResolver::resolve();
        
        if (!$tenantId && class_exists('\App\Core\TenantContext')) {
            try {
                $tenantId = \App\Core\TenantContext::getId();
            } catch (\Exception $e) {
                // TenantContext not set
            }
        }
        
        return $tenantId ?: 'global';
    }
    
    /**
     * Get all menu items (with cache)
     * @return array
     */
    public function getAllMenuItems(): array {
        // NO CACHE - Always fetch fresh data
        $items = $this->repository->getAll();
        
        // DEBUG: Log items count
        if (class_exists('\App\Core\Logger')) {
            \App\Core\Logger::debug('MenuItemService::getAllMenuItems - Items fetched', [
                'count' => count($items),
                'tenant_id' => $this->getTenantIdForCache()
            ]);
        }
        
        // Process items (no cache wrapper)
        if (true) {

            // Load variants for items that have variants - BULK OPERATION (no N+1)
            $productVariantService = \App\Core\DependencyFactory::getProductVariantService();
            
            // Collect all item IDs that have variants
            $itemIdsWithVariants = [];
            foreach ($items as $item) {
                if (!empty($item['has_variants']) && $item['has_variants'] == 1) {
                    $itemIdsWithVariants[] = $item['menu_item_id'];
                }
            }
            
            // Fetch ALL variants in one query (instead of N queries)
            $allVariants = [];
            if (!empty($itemIdsWithVariants)) {
                $allVariants = $productVariantService->getActiveVariantsByProducts($itemIdsWithVariants);
            }
            
            // Now assign variants to items
            foreach ($items as &$item) {
                // Assign variants from bulk result
                $item['variants'] = $allVariants[$item['menu_item_id']] ?? [];

                // If stock tracking is enabled, calculate stock from movements
                if (!empty($item['track_stock']) && $item['track_stock'] == 1) {
                    $calculatedStock = $this->getCurrentStock($item['menu_item_id']);
                    $item['calculated_stock'] = $calculatedStock;
                    $item['stock'] = $calculatedStock; // Override with calculated stock
                }
                
                // Normalize available_extras to extras for consistency
                if (isset($item['available_extras']) && !isset($item['extras'])) {
                    $item['extras'] = $item['available_extras'];
                }
                
                // Parse JSON fields if they exist
                if (!empty($item['ingredients']) && is_string($item['ingredients'])) {
                    $item['ingredients'] = json_decode($item['ingredients'], true) ?? [];
                }
                if (!empty($item['extras']) && is_string($item['extras'])) {
                    $item['extras'] = json_decode($item['extras'], true) ?? [];
                }
            }

            return $items;
        }
        
        return $items;
    }
    
    /**
     * Get available menu items (with cache and translation support)
     * @param string|null $languageCode Language code (default: current language)
     * @return array
     */
    public function getAvailableMenuItems(?string $languageCode = null): array {
        require_once __DIR__ . '/../helpers/translations.php';
        
        if ($languageCode === null) {
            $languageCode = getCurrentLanguage();
        }
        
        $cache = \App\Core\DependencyFactory::getCacheService();
        $tenantId = $this->getTenantIdForCache();
        $cacheKey = 'menu:items:available:' . $tenantId . ':' . $languageCode;

        // DEBUG: Log tenant context
        if (class_exists('\App\Core\Logger')) {
            \App\Core\Logger::debug('MenuItemService::getAvailableMenuItems - Called', [
                'tenant_id' => $tenantId,
                'language' => $languageCode,
                'cache_key' => $cacheKey
            ]);
        }

        return $cache->remember($cacheKey, function() use ($languageCode, $tenantId) {
            $items = $this->repository->getAvailable($languageCode);
            
            // DEBUG: Log result count
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::debug('MenuItemService::getAvailableMenuItems - Fetched from DB', [
                    'tenant_id' => $tenantId,
                    'count' => count($items),
                    'language' => $languageCode
                ]);
            }

            // Load variants for items that have variants - BULK OPERATION (no N+1)
            $productVariantService = \App\Core\DependencyFactory::getProductVariantService();
            
            // Collect all item IDs that have variants
            $itemIdsWithVariants = [];
            foreach ($items as $item) {
                if (!empty($item['has_variants']) && $item['has_variants'] == 1) {
                    $itemIdsWithVariants[] = $item['menu_item_id'];
                }
            }
            
            // Fetch ALL variants in one query (instead of N queries)
            $allVariants = [];
            if (!empty($itemIdsWithVariants)) {
                $allVariants = $productVariantService->getActiveVariantsByProducts($itemIdsWithVariants);
            }
            
            // Now assign variants to items
            foreach ($items as &$item) {
                // Assign variants from bulk result
                $item['variants'] = $allVariants[$item['menu_item_id']] ?? [];

                // If stock tracking is enabled, calculate stock from movements
                if (!empty($item['track_stock']) && $item['track_stock'] == 1) {
                    $calculatedStock = $this->getCurrentStock($item['menu_item_id']);
                    $item['calculated_stock'] = $calculatedStock;
                    $item['stock'] = $calculatedStock; // Override with calculated stock
                }
                
                // Parse JSON fields if they exist
                if (!empty($item['ingredients']) && is_string($item['ingredients'])) {
                    $item['ingredients'] = json_decode($item['ingredients'], true) ?? [];
                }
                if (!empty($item['extras']) && is_string($item['extras'])) {
                    $item['extras'] = json_decode($item['extras'], true) ?? [];
                }
            }

            return $items;
        }, 600); // Cache for 10 minutes (optimized for fresh data)
    }
    
    /**
     * Get menu items by category
     * @param string $categoryId
     * @return array
     */
    public function getMenuItemsByCategory(string $categoryId): array {
        $items = $this->repository->getByCategory($categoryId);

        // Calculate stock from movements for items with stock tracking enabled
        foreach ($items as &$item) {
            if (!empty($item['track_stock']) && $item['track_stock'] == 1) {
                $calculatedStock = $this->getCurrentStock($item['menu_item_id']);
                $item['calculated_stock'] = $calculatedStock;
                $item['stock'] = $calculatedStock; // Override with calculated stock
            }
        }

        return $items;
    }
    
    /**
     * Get menu item by ID
     * @param string $menuItemId
     * @return array|null
     */
    public function getMenuItemById(string $menuItemId): ?array {
        $menuItem = $this->repository->findById($menuItemId);

        // If stock tracking is enabled, calculate stock from movements
        if ($menuItem && !empty($menuItem['track_stock']) && $menuItem['track_stock'] == 1) {
            $calculatedStock = $this->getCurrentStock($menuItemId);
            $menuItem['calculated_stock'] = $calculatedStock;
            $menuItem['stock'] = $calculatedStock; // Override with calculated stock
        }

        return $menuItem;
    }

    /**
     * Batch load menu items to avoid N+1 queries in loops.
     * Returns a map of menu_item_id => menu_item data.
     * Note: does NOT compute calculated stock (use getMenuItemById for that).
     *
     * @param string[] $menuItemIds
     * @return array<string, array>
     */
    public function getMenuItemsByIds(array $menuItemIds): array {
        $menuItemIds = array_values(array_unique(array_filter($menuItemIds, fn($id) => is_string($id) && $id !== '')));
        if (empty($menuItemIds)) {
            return [];
        }

        $items = $this->repository->findByIds($menuItemIds);
        $byId = [];
        foreach ($items as $item) {
            $byId[$item['menu_item_id']] = $item;
        }
        return $byId;
    }
    
    /**
     * Create menu item
     * @param array $menuItemData
     * @return bool|string Menu item ID on success, false on failure
     * @throws \Exception If validation fails or database error occurs
     */
    public function createMenuItem(array $menuItemData) {
        try {
            if (empty($menuItemData['menu_item_id'])) {
                $menuItemData['menu_item_id'] = generateId('mi');
            }
            
            // Validate required fields with detailed error messages
            $validationErrors = [];
            
            if (empty($menuItemData['name']) || trim($menuItemData['name']) === '') {
                $validationErrors['name'] = 'Menü öğesi adı gereklidir.';
            }
            
            if (empty($menuItemData['category_id']) || trim($menuItemData['category_id']) === '') {
                $validationErrors['category_id'] = 'Kategori seçimi gereklidir.';
            }
            
            // Allow price = 0 for items without price (e.g., drinks, free items)
            if (!isset($menuItemData['price']) || !is_numeric($menuItemData['price']) || floatval($menuItemData['price']) < 0) {
                $validationErrors['price'] = 'Geçerli bir fiyat gereklidir (0 veya daha büyük olmalıdır).';
            }
            
            if (!empty($validationErrors)) {
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::warning('MenuItemService::createMenuItem - Validation failed', [
                        'errors' => $validationErrors,
                        'data' => array_keys($menuItemData)
                    ]);
                }
                throw new \InvalidArgumentException('Validation failed: ' . json_encode($validationErrors));
            }
            
            // Check for duplicate product name in the same category
            $existing = $this->repository->findOneBy([
                'name' => trim($menuItemData['name']),
                'category_id' => $menuItemData['category_id']
            ]);
            
            if ($existing) {
                $errorMessage = 'Bu kategoride aynı isimde bir ürün zaten mevcut: ' . $menuItemData['name'];
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::warning('MenuItemService::createMenuItem - Duplicate product', [
                        'name' => $menuItemData['name'],
                        'category_id' => $menuItemData['category_id'],
                        'existing_id' => $existing['menu_item_id'] ?? 'unknown'
                    ]);
                }
                throw new \InvalidArgumentException($errorMessage);
            }
            
            $defaults = [
                'is_available' => 1,
                'stock' => 0
            ];
            
            $menuItemData = array_merge($defaults, $menuItemData);
            
            // Ensure price is float
            $menuItemData['price'] = floatval($menuItemData['price']);
            
            // Ensure stock is integer
            if (isset($menuItemData['stock'])) {
                $menuItemData['stock'] = intval($menuItemData['stock']);
            }
            
            // Ensure is_available is integer (0 or 1)
            if (isset($menuItemData['is_available'])) {
                $menuItemData['is_available'] = (int)$menuItemData['is_available'];
            }
            
            // Check if stock tracking is enabled
            $trackStock = !empty($menuItemData['track_stock']) && $menuItemData['track_stock'] == 1;
            $initialStock = 0;

            if ($trackStock && isset($menuItemData['stock'])) {
                $initialStock = intval($menuItemData['stock']);
                // For new items with stock tracking, set initial stock to 0 in menu_items table
                // Actual stock will be managed separately in stock movements
                $menuItemData['stock'] = 0;
            } else {
                // If not tracking stock, keep the original stock value (or default to 999)
                $initialStock = 0;
            }

            $result = $this->repository->create($menuItemData);

            if ($result) {
                // If stock tracking is enabled, save initial stock and create stock movement
                if ($trackStock && $initialStock > 0) {
                    $this->saveInitialStock($menuItemData['menu_item_id'], $initialStock);
                    // Create initial stock movement record
                    $this->createInitialStockMovement($menuItemData['menu_item_id'], $initialStock);
                }

                // CRITICAL: Clear ALL menu cache
                try {
                    $this->clearAllMenuCache();
                } catch (\Exception $e) {
                    // Cache error is not critical, log and continue
                    if (class_exists('\App\Core\Logger')) {
                        \App\Core\Logger::warning('MenuItemService::createMenuItem - Cache invalidation failed', [
                            'error' => $e->getMessage()
                        ]);
                    }
                }

                return $menuItemData['menu_item_id'];
            }
            
            // If result is false but no exception was thrown, log error
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('MenuItemService::createMenuItem - Repository create returned false', [
                    'menu_item_id' => $menuItemData['menu_item_id'] ?? 'unknown',
                    'name' => $menuItemData['name'] ?? 'unknown'
                ]);
            }
            
            return false;
        } catch (\PDOException $e) {
            // Database error
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('MenuItemService::createMenuItem - PDOException', [
                    'message' => $e->getMessage(),
                    'code' => $e->getCode(),
                    'sql_state' => $e->errorInfo[0] ?? 'unknown',
                    'error_code' => $e->errorInfo[1] ?? 'unknown',
                    'error_message' => $e->errorInfo[2] ?? 'unknown',
                    'menu_item_id' => $menuItemData['menu_item_id'] ?? 'unknown'
                ]);
            }
            throw new \RuntimeException('Veritabanı hatası: Menü öğesi oluşturulamadı. ' . ($e->errorInfo[2] ?? $e->getMessage()), 0, $e);
        } catch (\Exception $e) {
            // Other errors (including validation errors)
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('MenuItemService::createMenuItem - Exception', [
                    'message' => $e->getMessage(),
                    'type' => get_class($e),
                    'menu_item_id' => $menuItemData['menu_item_id'] ?? 'unknown'
                ]);
            }
            throw $e;
        }
    }

    /**
     * Save initial stock for a menu item
     * @param string $menuItemId Menu item ID
     * @param int $quantity Initial stock quantity
     * @return bool Success
     */
    private function saveInitialStock(string $menuItemId, int $quantity): bool {
        try {
            // BaseRepository exposes the PDO handle as getDbConnection();
            // the shorter getDb() alias only exists on a handful of
            // repositories (e.g. StockMovementRepository). Calling it on
            // MenuItemRepository raises a fatal Error and took down the
            // business/menu page.
            $db = $this->repository->getDbConnection();
            $sql = "INSERT INTO initial_stock (menu_item_id, initial_quantity) VALUES (:menu_item_id, :initial_quantity)
                    ON DUPLICATE KEY UPDATE initial_quantity = :initial_quantity";
            $stmt = $db->prepare($sql);
            return $stmt->execute([
                'menu_item_id' => $menuItemId,
                'initial_quantity' => $quantity
            ]);
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('MenuItemService::saveInitialStock - Error saving initial stock', [
                    'menu_item_id' => $menuItemId,
                    'quantity' => $quantity,
                    'error' => $e->getMessage()
                ]);
            }
            return false;
        }
    }

    /**
     * Create initial stock movement record for a menu item
     * @param string $menuItemId Menu item ID
     * @param int $quantity Initial stock quantity
     * @return bool Success
     */
    private function createInitialStockMovement(string $menuItemId, int $quantity): bool {
        try {
            $stockMovementService = \App\Core\DependencyFactory::getStockMovementService();
            
            // Get current user ID
            require_once __DIR__ . '/../core/SessionManager.php';
            $userId = \App\Core\SessionManager::get('user_id') ?? 'system';
            
            // Create initial stock movement (IN type)
            $movementData = [
                'item_type' => 'MENU_ITEM',
                'item_id' => $menuItemId,
                'movement_type' => 'IN',
                'quantity' => (float)$quantity,
                'unit' => 'ADET',
                'reference_type' => 'INITIAL_STOCK',
                'reference_id' => $menuItemId,
                'description' => 'İlk stok girişi - Menü öğesi oluşturuldu',
                'created_by' => $userId,
                'notes' => 'Menüden eklenen ürün için başlangıç stoku'
            ];
            
            $result = $stockMovementService->recordMovement($movementData);
            
            if (!$result) {
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::warning('MenuItemService::createInitialStockMovement - Failed to create movement', [
                        'menu_item_id' => $menuItemId,
                        'quantity' => $quantity
                    ]);
                }
                return false;
            }
            
            return true;
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('MenuItemService::createInitialStockMovement - Error creating initial stock movement', [
                    'menu_item_id' => $menuItemId,
                    'quantity' => $quantity,
                    'error' => $e->getMessage()
                ]);
            }
            return false;
        }
    }

    /**
     * Get current stock for a menu item based on movements
     * @param string $menuItemId Menu item ID
     * @return int Current stock quantity
     */
    public function getCurrentStock(string $menuItemId): int {
        $menuItem = $this->repository->findById($menuItemId);
        if (!$menuItem) {
            return 0;
        }

        // Only calculate stock from movements if track_stock is enabled
        if (empty($menuItem['track_stock']) || $menuItem['track_stock'] != 1) {
            return (int)($menuItem['stock'] ?? 0);
        }

        // Use StockMovementService to calculate current stock from movements
        try {
            $stockMovementService = \App\Core\DependencyFactory::getStockMovementService();
            return $stockMovementService->getCurrentStockForMenuItem($menuItemId);
        } catch (\Throwable $e) {
            // Catch Throwable (not just Exception) so Error/TypeError don't crash the menu page.
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::warning('MenuItemService::getCurrentStock - Falling back to stored stock', [
                    'menu_item_id' => $menuItemId,
                    'error' => $e->getMessage()
                ]);
            }
            return (int)($menuItem['stock'] ?? 0);
        }
    }

    /**
     * Get menu item by ID with calculated stock
     * @param string $menuItemId Menu item ID
     * @return array|null Menu item data with calculated stock
     */
    public function getMenuItemByIdWithCalculatedStock(string $menuItemId): ?array {
        $menuItem = $this->repository->findById($menuItemId);
        if (!$menuItem) {
            return null;
        }

        // If stock tracking is enabled, calculate stock from movements
        if (!empty($menuItem['track_stock']) && $menuItem['track_stock'] == 1) {
            $calculatedStock = $this->getCurrentStock($menuItemId);
            $menuItem['calculated_stock'] = $calculatedStock;
            $menuItem['stock'] = $calculatedStock; // Override with calculated stock
        }

        return $menuItem;
    }
    
    /**
     * Update menu item
     * @param string $menuItemId
     * @param array $menuItemData
     * @return bool
     */
    public function updateMenuItem(string $menuItemId, array $menuItemData): bool {
        // Get tenant ID from existing menu item before update
        $existingItem = $this->repository->findById($menuItemId);
        $tenantId = $existingItem['tenant_id'] ?? $this->getTenantIdForCache();

        // Check if stock tracking is enabled
        $trackStock = !empty($menuItemData['track_stock']) && $menuItemData['track_stock'] == 1;
        $initialStock = 0;

        if ($trackStock && isset($menuItemData['stock'])) {
            $initialStock = intval($menuItemData['stock']);
            // For items with stock tracking, set initial stock to 0 in menu_items table
            // Actual stock will be managed separately in stock movements
            $menuItemData['stock'] = 0;
        } else {
            // If not tracking stock, keep the original stock value (or default to 999)
            $initialStock = 0;
        }

        // Log before update
        if (class_exists('\App\Core\Logger')) {
            \App\Core\Logger::debug('MenuItemService::updateMenuItem - Before update', [
                'menu_item_id' => $menuItemId,
                'category_id' => $menuItemData['category_id'] ?? 'not_set',
                'original_category_id' => $existingItem['category_id'] ?? null,
                'category_changed' => isset($menuItemData['category_id']) && ($menuItemData['category_id'] !== ($existingItem['category_id'] ?? null)),
                'menuItemData_keys' => array_keys($menuItemData)
            ]);
        }

        $result = $this->repository->update($menuItemId, $menuItemData);

        if ($result) {
            // If stock tracking is enabled, save/update initial stock
            if ($trackStock && $initialStock > 0) {
                $this->saveInitialStock($menuItemId, $initialStock);
            }

            // CRITICAL: Clear ALL menu cache (comprehensive invalidation)
            try {
                $this->clearAllMenuCache();
            } catch (\Exception $e) {
                // Cache error is not critical, log and continue
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::warning('MenuItemService::updateMenuItem - Cache invalidation failed', [
                        'error' => $e->getMessage(),
                        'menu_item_id' => $menuItemId
                    ]);
                }
            }
        } else {
            // Log update failure
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::warning('MenuItemService::updateMenuItem - Update failed', [
                    'menu_item_id' => $menuItemId,
                    'category_id' => $menuItemData['category_id'] ?? 'not_set'
                ]);
            }
        }

        return $result;
    }
    
    /**
     * Delete menu item
     * @param string $menuItemId
     * @return bool
     */
    public function deleteMenuItem(string $menuItemId): bool {
        // Get tenant ID from existing menu item before deletion
        $existingItem = $this->repository->findById($menuItemId);
        $tenantId = $existingItem['tenant_id'] ?? $this->getTenantIdForCache();
        
        $result = $this->repository->delete($menuItemId);
        
        if ($result) {
            // CRITICAL: Clear ALL menu cache
            try {
                $this->clearAllMenuCache();
            } catch (\Exception $e) {
                // Cache error is not critical, just log
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::warning('MenuItemService - Cache clear failed', [
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }
        
        return $result;
    }
    
    /**
     * Update stock
     * @param string $menuItemId
     * @param int $quantity
     * @return bool
     */
    public function updateStock(string $menuItemId, int $quantity): bool {
        return $this->repository->updateStock($menuItemId, $quantity);
    }
    
    /**
     * Get low stock items
     * @param int $threshold
     * @return array
     */
    public function getLowStockItems(int $threshold = 10): array {
        return $this->repository->getLowStock($threshold);
    }
    
    /**
     * Get out of stock items
     * @return array
     */
    public function getOutOfStockItems(): array {
        return $this->repository->getOutOfStock();
    }

    /**
     * Get low stock alerts for menu items
     * @param int $threshold Threshold for low stock warning (default: 5)
     * @return array Low stock alerts
     */
    public function getLowStockAlerts(int $threshold = 5): array {
        $lowStockItems = $this->repository->getLowStock($threshold);
        $outOfStockItems = $this->repository->getOutOfStock();

        $alerts = [];

        // Add out of stock alerts
        foreach ($outOfStockItems as $item) {
            $alerts[] = [
                'menu_item_id' => $item['menu_item_id'],
                'name' => $item['name'],
                'current_stock' => (int)($item['stock'] ?? 0),
                'threshold' => 0,
                'alert_type' => 'OUT_OF_STOCK',
                'alert_message' => $item['name'] . ' tükendi!'
            ];
        }

        // Add low stock alerts
        foreach ($lowStockItems as $item) {
            // Skip if already in out of stock alerts
            $alreadyAdded = false;
            foreach ($alerts as $alert) {
                if ($alert['menu_item_id'] === $item['menu_item_id']) {
                    $alreadyAdded = true;
                    break;
                }
            }

            if (!$alreadyAdded) {
                $alerts[] = [
                    'menu_item_id' => $item['menu_item_id'],
                    'name' => $item['name'],
                    'current_stock' => (int)($item['stock'] ?? 0),
                    'threshold' => $threshold,
                    'alert_type' => 'LOW_STOCK',
                    'alert_message' => $item['name'] . ' stoku azaldı! (Kalan: ' . $item['stock'] . ')'
                ];
            }
        }

        return $alerts;
    }
    
    /**
     * Search menu items
     * @param string $query
     * @return array
     */
    public function searchMenuItems(string $query): array {
        return $this->repository->search($query);
    }
    
    /**
     * Clear all menu item and category cache
     * Called after create/update/delete operations to prevent stale data
     */
    private function clearAllMenuCache(): void {
        $cache = \App\Core\DependencyFactory::getCacheService();
        $tenantId = $this->getTenantIdForCache();
        
        // CRITICAL: Use pattern-based deletion for comprehensive cache clearing
        // This ensures ALL menu-related cache is cleared, preventing stale data
        try {
            // Method 1: Try deleteByPattern if available (Redis/FileCache)
            if (method_exists($cache, 'deleteByPattern')) {
                $cache->deleteByPattern('menu:*');
                $cache->deleteByPattern('menu_*');
            } else {
                // Method 2: Fallback - try invalidate
                $cache->invalidate('menu:*');
                $cache->invalidate('menu_*');
            }
        } catch (\Exception $e) {
            // If pattern-based deletion fails, fall back to explicit key deletion
            $cacheKeys = [
                'menu:items:all:' . $tenantId,
                'menu:items:available:' . $tenantId,
                'menu:items:business:' . $tenantId,
                'menu:categories',
                'menu:categories:' . $tenantId . ':tr',
                'menu:categories:' . $tenantId . ':en',
                'menu:categories:with_products:' . $tenantId . ':tr',
                'menu:categories:with_products:' . $tenantId . ':en',
                'menu:categories:with_products:tr',
                'menu:categories:with_products:en',
            ];
            
            foreach ($cacheKeys as $key) {
                $cache->delete($key);
            }
        }
        
        // Log cache clear for debugging
        if (class_exists('\App\Core\Logger')) {
            \App\Core\Logger::debug('MenuItemService - All menu cache cleared', [
                'tenant_id' => $tenantId
            ]);
        }
    }
}

