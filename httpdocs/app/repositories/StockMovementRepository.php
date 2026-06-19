<?php
namespace App\Repositories;

use App\Core\BaseRepository;
use PDO;

/**
 * Stock Movement Repository
 * Handles database operations for stock movements
 * 
 * @package App\Repositories
 */
class StockMovementRepository extends BaseRepository {
    protected $table = 'stock_movements';
    protected $primaryKey = 'movement_id';

    /**
     * Constructor
     * @param \PDO $database Database connection
     */
    public function __construct($database) {
        parent::__construct($database);
    }

    /**
     * Expose the underlying PDO connection for services that need to run
     * raw queries against related (but non-owned) tables such as initial_stock.
     * @return \PDO
     */
    public function getDb(): \PDO {
        return $this->db;
    }

    /**
     * Override to prefix the tenant filter with the `sm` alias, because all
     * queries in this repository join additional tables (users, ingredients,
     * stock_locations) that now also expose `tenant_id`, making an unqualified
     * filter ambiguous.
     */
    protected function addTenantToWhere(string $sql, array &$params): string {
        $filter = $this->getTenantFilter();
        if (empty($filter['where'])) {
            return $sql;
        }

        $where = $this->tenantWhereForAlias('sm', $filter['where']);
        $params = array_merge($params, $filter['params']);

        $hasWhere = stripos($sql, 'WHERE') !== false;
        if ($hasWhere) {
            return $sql . ' AND ' . $where;
        }

        $keywords = ['GROUP BY', 'ORDER BY', 'LIMIT', 'HAVING', 'UNION'];
        $insertPos = strlen($sql);
        foreach ($keywords as $keyword) {
            $pos = stripos($sql, $keyword);
            if ($pos !== false && $pos < $insertPos) {
                $insertPos = $pos;
            }
        }

        if ($insertPos < strlen($sql)) {
            $before = substr($sql, 0, $insertPos);
            $after = substr($sql, $insertPos);
            return trim($before) . ' WHERE ' . $where . ' ' . $after;
        }

        return $sql . ' WHERE ' . $where;
    }

    /**
     * Get the initial stock (opening balance) configured for a menu item.
     * Tenant-scoped: if the caller's tenant does not match the menu item's
     * tenant, nothing is returned.
     *
     * @param string $menuItemId Menu item ID
     * @return int Initial stock quantity (0 if none configured)
     */
    public function getInitialStockForMenuItem(string $menuItemId): int {
        $tenantId = \App\Core\TenantResolver::resolve();

        if ($tenantId) {
            // Guard: only return initial_stock when the menu item belongs to this tenant.
            $sql = "SELECT ist.initial_quantity
                    FROM initial_stock ist
                    INNER JOIN menu_items mi
                        ON ist.menu_item_id COLLATE utf8mb4_unicode_ci = mi.menu_item_id COLLATE utf8mb4_unicode_ci
                    WHERE ist.menu_item_id = :menu_item_id
                      AND mi.tenant_id = :tenant_id
                    LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'menu_item_id' => $menuItemId,
                'tenant_id' => $tenantId,
            ]);
        } else {
            $sql = "SELECT initial_quantity FROM initial_stock WHERE menu_item_id = :menu_item_id LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['menu_item_id' => $menuItemId]);
        }

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ? (int)($row['initial_quantity'] ?? 0) : 0;
    }

    /**
     * Get all stock movements with related data
     * @return array Stock movements
     */
    public function getAll(): array {
        $sql = "SELECT 
                    sm.*,
                    u.name as created_by_name,
                    fl.name as from_location_name,
                    tl.name as to_location_name,
                    i.name as item_name,
                    i.unit as item_unit,
                    i.min_threshold as item_min_threshold,
                    i.current_stock as item_current_stock
                FROM {$this->table} sm
                LEFT JOIN users u ON sm.created_by COLLATE utf8mb4_unicode_ci = u.user_id COLLATE utf8mb4_unicode_ci
                LEFT JOIN stock_locations fl ON sm.from_location_id COLLATE utf8mb4_unicode_ci = fl.location_id COLLATE utf8mb4_unicode_ci
                LEFT JOIN stock_locations tl ON sm.to_location_id COLLATE utf8mb4_unicode_ci = tl.location_id COLLATE utf8mb4_unicode_ci
                LEFT JOIN ingredients i ON sm.item_type = 'INGREDIENT' AND sm.item_id COLLATE utf8mb4_unicode_ci = i.ingredient_id COLLATE utf8mb4_unicode_ci";
        $params = [];
        
        // Add tenant filter
        $sql = $this->addTenantToWhere($sql, $params);
        $sql .= " ORDER BY sm.created_at DESC";
        
        return $this->fetchAll($sql, $params);
    }

    /**
     * Get stock movement by ID
     * @param string $movementId Movement ID
     * @return array|null Movement data or null
     */
    public function getById(string $movementId): ?array {
        $sql = "SELECT 
                    sm.*,
                    u.name as created_by_name,
                    fl.name as from_location_name,
                    tl.name as to_location_name,
                    i.name as item_name,
                    i.unit as item_unit,
                    i.min_threshold as item_min_threshold,
                    i.current_stock as item_current_stock
                FROM {$this->table} sm
                LEFT JOIN users u ON sm.created_by COLLATE utf8mb4_unicode_ci = u.user_id COLLATE utf8mb4_unicode_ci
                LEFT JOIN stock_locations fl ON sm.from_location_id COLLATE utf8mb4_unicode_ci = fl.location_id COLLATE utf8mb4_unicode_ci
                LEFT JOIN stock_locations tl ON sm.to_location_id COLLATE utf8mb4_unicode_ci = tl.location_id COLLATE utf8mb4_unicode_ci
                LEFT JOIN ingredients i ON sm.item_type = 'INGREDIENT' AND sm.item_id COLLATE utf8mb4_unicode_ci = i.ingredient_id COLLATE utf8mb4_unicode_ci
                WHERE sm.{$this->primaryKey} = :id";
        $params = ['id' => $movementId];
        
        // Add tenant filter
        $sql = $this->addTenantToWhere($sql, $params);
        $sql .= " LIMIT 1";
        
        return $this->fetchOne($sql, $params);
    }

    /**
     * Get movements by item
     * @param string $itemType Item type (INGREDIENT or MENU_ITEM)
     * @param string $itemId Item ID
     * @return array Movements
     */
    public function getByItem(string $itemType, string $itemId): array {
        if ($itemType === 'INGREDIENT') {
            $sql = "SELECT 
                        sm.*,
                        u.name as created_by_name,
                        fl.name as from_location_name,
                        tl.name as to_location_name,
                        i.name as item_name,
                        i.unit as item_unit,
                        i.min_threshold as item_min_threshold,
                        i.current_stock as item_current_stock
                    FROM {$this->table} sm
                    LEFT JOIN users u ON sm.created_by COLLATE utf8mb4_unicode_ci = u.user_id COLLATE utf8mb4_unicode_ci
                    LEFT JOIN stock_locations fl ON sm.from_location_id COLLATE utf8mb4_unicode_ci = fl.location_id COLLATE utf8mb4_unicode_ci
                    LEFT JOIN stock_locations tl ON sm.to_location_id COLLATE utf8mb4_unicode_ci = tl.location_id COLLATE utf8mb4_unicode_ci
                    LEFT JOIN ingredients i ON sm.item_type = 'INGREDIENT' AND sm.item_id COLLATE utf8mb4_unicode_ci = i.ingredient_id COLLATE utf8mb4_unicode_ci
                    WHERE sm.item_type = :item_type AND sm.item_id = :item_id";
        } else {
            // MENU_ITEM
            $sql = "SELECT 
                        sm.*,
                        u.name as created_by_name,
                        fl.name as from_location_name,
                        tl.name as to_location_name,
                        mi.name as item_name,
                        'ADET' as item_unit,
                        5 as item_min_threshold,
                        COALESCE(mi.stock, 0) as item_current_stock
                    FROM {$this->table} sm
                    LEFT JOIN users u ON sm.created_by COLLATE utf8mb4_unicode_ci = u.user_id COLLATE utf8mb4_unicode_ci
                    LEFT JOIN stock_locations fl ON sm.from_location_id COLLATE utf8mb4_unicode_ci = fl.location_id COLLATE utf8mb4_unicode_ci
                    LEFT JOIN stock_locations tl ON sm.to_location_id COLLATE utf8mb4_unicode_ci = tl.location_id COLLATE utf8mb4_unicode_ci
                    LEFT JOIN menu_items mi ON sm.item_type = 'MENU_ITEM' AND sm.item_id COLLATE utf8mb4_unicode_ci = mi.menu_item_id COLLATE utf8mb4_unicode_ci
                    WHERE sm.item_type = :item_type AND sm.item_id = :item_id";
        }
        
        $params = [
            'item_type' => $itemType,
            'item_id' => $itemId
        ];
        
        // Add tenant filter
        $sql = $this->addTenantToWhere($sql, $params);
        $sql .= " ORDER BY sm.created_at DESC";
        
        return $this->fetchAll($sql, $params);
    }

    /**
     * Get movements by date range
     * @param string $startDate Start date
     * @param string $endDate End date
     * @return array Movements
     */
    public function getByDateRange(string $startDate, string $endDate): array {
        $sql = "SELECT 
                    sm.*,
                    u.name as created_by_name,
                    fl.name as from_location_name,
                    tl.name as to_location_name,
                    i.name as item_name,
                    i.unit as item_unit,
                    i.min_threshold as item_min_threshold,
                    i.current_stock as item_current_stock
                FROM {$this->table} sm
                LEFT JOIN users u ON sm.created_by COLLATE utf8mb4_unicode_ci = u.user_id COLLATE utf8mb4_unicode_ci
                LEFT JOIN stock_locations fl ON sm.from_location_id COLLATE utf8mb4_unicode_ci = fl.location_id COLLATE utf8mb4_unicode_ci
                LEFT JOIN stock_locations tl ON sm.to_location_id COLLATE utf8mb4_unicode_ci = tl.location_id COLLATE utf8mb4_unicode_ci
                LEFT JOIN ingredients i ON sm.item_type = 'INGREDIENT' AND sm.item_id COLLATE utf8mb4_unicode_ci = i.ingredient_id COLLATE utf8mb4_unicode_ci
                WHERE DATE(sm.created_at) BETWEEN :start_date AND :end_date";
        $params = [
            'start_date' => $startDate,
            'end_date' => $endDate
        ];
        
        // Add tenant filter
        $sql = $this->addTenantToWhere($sql, $params);
        $sql .= " ORDER BY sm.created_at DESC";
        
        return $this->fetchAll($sql, $params);
    }

    /**
     * Get movements by type
     * @param string $movementType Movement type
     * @return array Movements
     */
    public function getByType(string $movementType): array {
        $sql = "SELECT 
                    sm.*,
                    u.name as created_by_name,
                    fl.name as from_location_name,
                    tl.name as to_location_name,
                    i.name as item_name,
                    i.unit as item_unit,
                    i.min_threshold as item_min_threshold,
                    i.current_stock as item_current_stock
                FROM {$this->table} sm
                LEFT JOIN users u ON sm.created_by COLLATE utf8mb4_unicode_ci = u.user_id COLLATE utf8mb4_unicode_ci
                LEFT JOIN stock_locations fl ON sm.from_location_id COLLATE utf8mb4_unicode_ci = fl.location_id COLLATE utf8mb4_unicode_ci
                LEFT JOIN stock_locations tl ON sm.to_location_id COLLATE utf8mb4_unicode_ci = tl.location_id COLLATE utf8mb4_unicode_ci
                LEFT JOIN ingredients i ON sm.item_type = 'INGREDIENT' AND sm.item_id COLLATE utf8mb4_unicode_ci = i.ingredient_id COLLATE utf8mb4_unicode_ci
                WHERE sm.movement_type = :movement_type";
        $params = ['movement_type' => $movementType];
        
        // Add tenant filter
        $sql = $this->addTenantToWhere($sql, $params);
        $sql .= " ORDER BY sm.created_at DESC";
        
        return $this->fetchAll($sql, $params);
    }

    /**
     * Get movements by reference
     * @param string $referenceType Reference type
     * @param string $referenceId Reference ID
     * @return array Movements
     */
    public function getByReference(string $referenceType, string $referenceId): array {
        $sql = "SELECT 
                    sm.*,
                    u.name as created_by_name,
                    fl.name as from_location_name,
                    tl.name as to_location_name,
                    i.name as item_name,
                    i.unit as item_unit,
                    i.min_threshold as item_min_threshold,
                    i.current_stock as item_current_stock
                FROM {$this->table} sm
                LEFT JOIN users u ON sm.created_by COLLATE utf8mb4_unicode_ci = u.user_id COLLATE utf8mb4_unicode_ci
                LEFT JOIN stock_locations fl ON sm.from_location_id COLLATE utf8mb4_unicode_ci = fl.location_id COLLATE utf8mb4_unicode_ci
                LEFT JOIN stock_locations tl ON sm.to_location_id COLLATE utf8mb4_unicode_ci = tl.location_id COLLATE utf8mb4_unicode_ci
                LEFT JOIN ingredients i ON sm.item_type = 'INGREDIENT' AND sm.item_id COLLATE utf8mb4_unicode_ci = i.ingredient_id COLLATE utf8mb4_unicode_ci
                WHERE sm.reference_type = :reference_type AND sm.reference_id = :reference_id";
        $params = [
            'reference_type' => $referenceType,
            'reference_id' => $referenceId
        ];
        
        // Add tenant filter
        $sql = $this->addTenantToWhere($sql, $params);
        $sql .= " ORDER BY sm.created_at DESC";
        
        return $this->fetchAll($sql, $params);
    }

    /**
     * Calculate current stock from movements
     * @param string $itemType Item type
     * @param string $itemId Item ID
     * @return float Current stock
     */
    public function calculateCurrentStock(string $itemType, string $itemId): float {
        $sql = "SELECT SUM(
                    CASE 
                        WHEN movement_type = 'IN' THEN quantity
                        WHEN movement_type = 'OUT' THEN -quantity
                        WHEN movement_type = 'TRANSFER' AND to_location_id IS NOT NULL THEN quantity
                        WHEN movement_type = 'TRANSFER' AND from_location_id IS NOT NULL THEN -quantity
                        WHEN movement_type = 'ADJUSTMENT' THEN quantity
                        WHEN movement_type = 'WASTE' THEN -quantity
                        WHEN movement_type = 'RETURN' THEN quantity
                        ELSE 0
                    END
                ) as total_stock
                FROM {$this->table}
                WHERE item_type = :item_type AND item_id = :item_id";
        $params = [
            'item_type' => $itemType,
            'item_id' => $itemId
        ];
        
        // Add tenant filter
        $sql = $this->addTenantToWhere($sql, $params);
        
        $result = $this->fetchOne($sql, $params);
        
        return $result ? (float)($result['total_stock'] ?? 0) : 0;
    }
    
    /**
     * Get stock summary with all ingredients and menu items with stock tracking
     * @return array Stock summary
     */
    public function getStockSummary(): array {
        try {
            // Get tenant filter
            $tenantFilter = $this->getTenantFilter();
            $params = [];
            
            // Build ingredients query
            $ingredientWhereConditions = [];
            if (!empty($tenantFilter['where'])) {
                // Extract the column name from the filter (e.g., "business_id = :tenant_filter_id" -> "business_id")
                $filterWhere = $tenantFilter['where'];
                // Remove parameter placeholder and extract column name
                if (preg_match('/(\w+)\s*=\s*:tenant_filter_id/', $filterWhere, $matches)) {
                    $columnName = $matches[1];
                    $ingredientWhereConditions[] = "i.{$columnName} = :ingredient_tenant_filter_id";
                    $params['ingredient_tenant_filter_id'] = $tenantFilter['params']['tenant_filter_id'] ?? null;
                } else {
                    // Fallback: use the filter as-is
                    $ingredientWhereConditions[] = "i." . $filterWhere;
                    $params = array_merge($params, $tenantFilter['params']);
                }
            }
            $ingredientWhereClause = !empty($ingredientWhereConditions) ? "WHERE " . implode(" AND ", $ingredientWhereConditions) : "";
            
            // Build menu items query (only items with track_stock = 1)
            $menuItemWhereConditions = ["mi.track_stock = 1"];
            $menuItemParams = [];
            if (!empty($tenantFilter['where'])) {
                // Extract the column name from the filter
                $filterWhere = $tenantFilter['where'];
                if (preg_match('/(\w+)\s*=\s*:tenant_filter_id/', $filterWhere, $matches)) {
                    $columnName = $matches[1];
                    $menuItemWhereConditions[] = "mi.{$columnName} = :menu_tenant_filter_id";
                    $menuItemParams['menu_tenant_filter_id'] = $tenantFilter['params']['tenant_filter_id'] ?? null;
                } else {
                    // Fallback: use the filter as-is
                    $menuItemWhereConditions[] = "mi." . $filterWhere;
                    $menuItemParams = $tenantFilter['params'];
                }
            }
            $menuItemWhereClause = "WHERE " . implode(" AND ", $menuItemWhereConditions);
            
            // Detect whether the optional `ingredients.item_type` / `category`
            // columns exist (migration may or may not be applied yet) so this
            // query degrades gracefully on older databases.
            $hasIngredientType = false;
            $hasIngredientCategory = false;
            $hasIngredientCategoryId = false;
            $hasMenuCategoryId = false;
            try {
                $hasIngredientType     = \App\Core\DbSchema::hasColumn('ingredients', 'item_type');
                $hasIngredientCategory = \App\Core\DbSchema::hasColumn('ingredients', 'category');
                $hasIngredientCategoryId = \App\Core\DbSchema::hasColumn('ingredients', 'category_id');
                $hasMenuCategoryId  = \App\Core\DbSchema::hasColumn('menu_items', 'category_id');
            } catch (\Throwable $e) {
                // ignore — safe defaults used
            }

            $ingredientSubTypeExpr = $hasIngredientType
                ? "COALESCE(i.item_type, 'INGREDIENT')"
                : "'INGREDIENT'";
            $ingredientCategoryExpr = $hasIngredientCategory
                ? "i.category"
                : "NULL";
            $ingredientCategoryIdSelect = $hasIngredientCategoryId
                ? "i.category_id"
                : "NULL";
            $scTableOk = false;
            try {
                $scTableOk = \App\Core\DbSchema::tableExists('stock_categories');
            } catch (\Throwable $e) {
                $scTableOk = false;
            }

            $ingredientDisplayCategoryExpr = ($hasIngredientCategoryId && $scTableOk)
                ? "COALESCE(sc_ing.name, {$ingredientCategoryExpr})"
                : $ingredientCategoryExpr;

            $menuCategoryIdSelect   = $hasMenuCategoryId ? "mi.category_id" : "NULL";
            $menuDisplayCategoryExpr = ($hasMenuCategoryId && $scTableOk)
                ? "sc_mi.name"
                : "NULL";

            $ingJoin = ($hasIngredientCategoryId && $scTableOk)
                ? " LEFT JOIN stock_categories sc_ing ON sc_ing.category_id = i.category_id
                        AND sc_ing.tenant_id = i.tenant_id "
                : "";
            $miJoin = ($hasMenuCategoryId && $scTableOk)
                ? " LEFT JOIN stock_categories sc_mi ON sc_mi.category_id = mi.category_id
                        AND sc_mi.tenant_id = mi.tenant_id "
                : "";

            $ingGroupExtra = [];
            if ($hasIngredientType) {
                $ingGroupExtra[] = 'i.item_type';
            }
            if ($hasIngredientCategory) {
                $ingGroupExtra[] = 'i.category';
            }
            if ($hasIngredientCategoryId) {
                $ingGroupExtra[] = 'i.category_id';
            }
            if ($hasIngredientCategoryId && $scTableOk) {
                $ingGroupExtra[] = 'sc_ing.name';
            }
            $ingGroupBy = 'i.ingredient_id, i.name, i.unit, i.current_stock, i.min_threshold, i.par_level'
                . (empty($ingGroupExtra) ? '' : ', ' . implode(', ', $ingGroupExtra));

            $miGroupExtra = [];
            if ($hasMenuCategoryId) {
                $miGroupExtra[] = 'mi.category_id';
            }
            if ($hasMenuCategoryId && $scTableOk) {
                $miGroupExtra[] = 'sc_mi.name';
            }
            $miGroupBy = 'mi.menu_item_id, mi.name, mi.stock'
                . (empty($miGroupExtra) ? '' : ', ' . implode(', ', $miGroupExtra));

            // Build SQL with UNION - ingredients and menu items
            // sub_type: legacy INGREDIENT / RAW_MATERIAL / … for the "Tip" column.
            // category + category_id: stock-categories (tenant) + filter chips on inventory page.
            $sql = "
                SELECT 
                    i.ingredient_id as item_id,
                    'INGREDIENT' as item_type,
                    {$ingredientSubTypeExpr} as sub_type,
                    {$ingredientDisplayCategoryExpr} as category,
                    {$ingredientCategoryIdSelect} as category_id,
                    i.name,
                    COALESCE(i.unit, 'ADET') as unit,
                    COALESCE(i.current_stock, 0) as current_stock,
                    COALESCE(i.min_threshold, 0) as min_threshold,
                    i.par_level,
                    COUNT(DISTINCT sm.movement_id) as total_movements,
                    MAX(sm.created_at) as last_movement_date
                FROM ingredients i
                {$ingJoin}
                LEFT JOIN {$this->table} sm ON sm.item_type = 'INGREDIENT' AND sm.item_id COLLATE utf8mb4_unicode_ci = i.ingredient_id COLLATE utf8mb4_unicode_ci
                {$ingredientWhereClause}
                GROUP BY {$ingGroupBy}
                
                UNION ALL
                
                SELECT 
                    mi.menu_item_id as item_id,
                    'MENU_ITEM' as item_type,
                    'MENU_ITEM' as sub_type,
                    {$menuDisplayCategoryExpr} as category,
                    {$menuCategoryIdSelect} as category_id,
                    mi.name,
                    'ADET' as unit,
                    COALESCE(mi.stock, 0) as current_stock,
                    5 as min_threshold,
                    NULL as par_level,
                    COUNT(DISTINCT sm2.movement_id) as total_movements,
                    MAX(sm2.created_at) as last_movement_date
                FROM menu_items mi
                {$miJoin}
                LEFT JOIN {$this->table} sm2 ON sm2.item_type = 'MENU_ITEM' AND sm2.item_id COLLATE utf8mb4_unicode_ci = mi.menu_item_id COLLATE utf8mb4_unicode_ci
                {$menuItemWhereClause}
                GROUP BY {$miGroupBy}
                
                ORDER BY name ASC
            ";
            
            // Merge params (menu item params use different key names to avoid conflicts)
            $allParams = array_merge($params, $menuItemParams);
            
            return $this->fetchAll($sql, $allParams);
        } catch (\Exception $e) {
            // Log error and return empty array to prevent 500 error
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('StockMovementRepository::getStockSummary - Error', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
            return [];
        }
    }
    
    /**
     * Get movements with full item details
     * @param int $limit Limit number of results
     * @return array Movements with item details
     */
    public function getMovementsWithItemDetails(int $limit = 100): array {
        $limit = max(1, min(1000, (int)$limit)); // Sanitize limit
        $sql = "SELECT 
                    sm.*,
                    u.name as created_by_name,
                    fl.name as from_location_name,
                    tl.name as to_location_name,
                    COALESCE(i.name, mi.name) as item_name,
                    COALESCE(i.unit, 'ADET') as item_unit,
                    COALESCE(i.min_threshold, 5) as item_min_threshold,
                    COALESCE(i.current_stock, COALESCE(mi.stock, 0)) as item_current_stock
                FROM {$this->table} sm
                LEFT JOIN users u ON sm.created_by COLLATE utf8mb4_unicode_ci = u.user_id COLLATE utf8mb4_unicode_ci
                LEFT JOIN stock_locations fl ON sm.from_location_id COLLATE utf8mb4_unicode_ci = fl.location_id COLLATE utf8mb4_unicode_ci
                LEFT JOIN stock_locations tl ON sm.to_location_id COLLATE utf8mb4_unicode_ci = tl.location_id COLLATE utf8mb4_unicode_ci
                LEFT JOIN ingredients i ON sm.item_type = 'INGREDIENT' AND sm.item_id COLLATE utf8mb4_unicode_ci = i.ingredient_id COLLATE utf8mb4_unicode_ci
                LEFT JOIN menu_items mi ON sm.item_type = 'MENU_ITEM' AND sm.item_id COLLATE utf8mb4_unicode_ci = mi.menu_item_id COLLATE utf8mb4_unicode_ci";
        $params = [];
        
        // Add tenant filter
        $sql = $this->addTenantToWhere($sql, $params);
        $sql .= " ORDER BY sm.created_at DESC
                LIMIT {$limit}";
        
        return $this->fetchAll($sql, $params);
    }
}

