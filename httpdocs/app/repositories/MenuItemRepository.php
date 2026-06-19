<?php
namespace App\Repositories;

use App\Core\BaseRepository;
use PDO;

/**
 * Menu Item Repository
 * Handles database operations for menu items
 * 
 * @package App\Repositories
 */
class MenuItemRepository extends BaseRepository {
    protected $table = 'menu_items';
    protected $primaryKey = 'menu_item_id';

    /**
     * Constructor
     * @param \PDO $database Database connection
     */
    public function __construct($database) {
        parent::__construct($database);
    }

    /**
     * Get all menu items with category name
     * @return array Menu items
     */
    public function getAll(): array {
        // CRITICAL: Add tenant filtering - Use getTenantFilter() for smart column detection
        $filter = $this->getTenantFilter();
        $params = $filter['params'];
        $whereClause = !empty($filter['where']) ? "WHERE " . $filter['where'] : "";
        
        // If 'category' relation is requested, use join query
        if (in_array('category', $this->withRelations)) {
            $sql = "SELECT mi.*, c.name as category_name, c.category_id, c.description as category_description
                    FROM {$this->table} mi 
                    LEFT JOIN categories c ON mi.category_id = c.category_id
                    {$whereClause}
                    ORDER BY c.name, mi.name";
            $items = $this->fetchAll($sql, $params);
            
            // Format category data
            foreach ($items as &$item) {
                if (!empty($item['category_id'])) {
                    $item['category'] = [
                        'category_id' => $item['category_id'],
                        'name' => $item['category_name'],
                        'description' => $item['category_description'] ?? ''
                    ];
                }
            }
            
            return $items;
        }
        
        // Default query without join (faster if category not needed)
        $sql = "SELECT * FROM {$this->table} {$whereClause} ORDER BY name";
        
        $items = $this->fetchAll($sql, $params);
        
        // Load relationships if specified
        if (!empty($this->withRelations)) {
            $items = $this->loadRelationsForMany($items);
        }
        
        return $items;
    }
    
    /**
     * Get tenant ID from session or TenantContext
     * @return string|null
     */
    private function getTenantId(): ?string {
        // Try session first
        $tenantId = \App\Core\TenantResolver::resolve();
        
        // If not in session, try TenantContext
        if (!$tenantId && class_exists('\App\Core\TenantContext')) {
            try {
                $tenantId = \App\Core\TenantContext::getId();
            } catch (\Exception $e) {
                // TenantContext not set, continue
            }
        }
        
        return $tenantId;
    }
    
    /**
     * Load category relationship for menu items (batch loading)
     * @param array $items Menu items data
     * @return array Menu items with category
     */
    protected function loadRelationForMany(array $items, string $relation): array {
        if ($relation === 'category') {
            return $this->loadCategoriesForItems($items);
        }
        return parent::loadRelationForMany($items, $relation);
    }
    
    /**
     * Load categories for multiple menu items (batch loading)
     * @param array $items Menu items data
     * @return array Menu items with categories
     */
    private function loadCategoriesForItems(array $items): array {
        if (empty($items)) {
            return $items;
        }
        
        // Get unique category IDs
        $categoryIds = array_unique(array_filter(array_column($items, 'category_id')));
        
        if (empty($categoryIds)) {
            return $items;
        }
        
        // Load all categories in one query
        $placeholders = implode(',', array_fill(0, count($categoryIds), '?'));
        $sql = "SELECT * FROM categories WHERE category_id IN ({$placeholders})";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($categoryIds);
        $categories = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // Index categories by ID
        $categoriesById = [];
        foreach ($categories as $category) {
            $categoriesById[$category['category_id']] = $category;
        }
        
        // Attach categories to items
        foreach ($items as &$item) {
            $categoryId = $item['category_id'] ?? null;
            if ($categoryId && isset($categoriesById[$categoryId])) {
                $item['category'] = $categoriesById[$categoryId];
            }
        }
        
        return $items;
    }

    /**
     * Get available menu items with category name
     * @return array Available menu items
     */
    public function getAvailable(?string $languageCode = null): array {
        // CRITICAL: Add tenant filtering
        $filter = $this->getTenantFilter();
        $params = $filter['params'];
        $whereConditions = ['mi.is_available = 1'];
        
        if (!empty($filter['where'])) {
            // getTenantFilter() returns "business_id = :tenant_filter_id" without table prefix
            // We need to add "mi." prefix for the alias
            $whereConditions[] = $this->tenantWhereForAlias('mi', $filter['where']);
        }
        
        $whereClause = "WHERE " . implode(" AND ", $whereConditions);
        
        // Add translation join if language code provided
        if ($languageCode) {
            $params['language_code'] = $languageCode;
            
            $columnCache = [
                'mit_extras'      => \App\Core\DbSchema::hasColumn('menu_item_translations', 'extras'),
                'mit_ingredients' => \App\Core\DbSchema::hasColumn('menu_item_translations', 'ingredients'),
            ];
            
            // Build ingredients field conditionally
            $ingredientsField = $columnCache['mit_ingredients']
                ? "COALESCE(mit.ingredients, mi.ingredients) as ingredients"
                : "COALESCE(mi.ingredients, '[]') as ingredients";
            
            // Build extras field conditionally
            $extrasField = $columnCache['mit_extras']
                ? "COALESCE(mit.extras, mi.available_extras, '[]') as extras"
                : "COALESCE(mi.available_extras, '[]') as extras";
            
            // Note: Avoid mi.* to prevent selecting non-existent columns
            // Core columns from menu_items table (verified: stock, not stock_quantity)
            $sql = "SELECT 
                        mi.menu_item_id,
                        mi.category_id,
                        mi.price,
                        mi.is_available,
                        mi.image_url,
                        mi.preparation_screen_id,
                        mi.production_point,
                        mi.is_direct_service,
                        mi.available_extras,
                        mi.ingredients,
                        mi.created_at,
                        mi.updated_at,
                        c.name as category_name,
                        COALESCE(mit.name, mi.name) as name,
                        COALESCE(mit.description, mi.description) as description,
                        {$ingredientsField},
                        {$extrasField},
                        mit.meta_title,
                        mit.meta_description,
                        mit.slug,
                        CASE WHEN mit.menu_item_id IS NOT NULL THEN 1 ELSE 0 END as has_translation
                    FROM {$this->table} mi 
                    LEFT JOIN categories c ON mi.category_id = c.category_id
                    LEFT JOIN menu_item_translations mit ON mi.menu_item_id = mit.menu_item_id AND mit.language_code = :language_code
                    {$whereClause}
                    ORDER BY c.name, mi.name";
        } else {
            $sql = "SELECT 
                        mi.menu_item_id,
                        mi.category_id,
                        mi.name,
                        mi.description,
                        mi.price,
                        mi.is_available,
                        mi.image_url,
                        mi.preparation_screen_id,
                        mi.production_point,
                        mi.is_direct_service,
                        mi.available_extras,
                        mi.ingredients,
                        mi.created_at,
                        mi.updated_at,
                        c.name as category_name
                    FROM {$this->table} mi 
                    LEFT JOIN categories c ON mi.category_id = c.category_id 
                    {$whereClause}
                    ORDER BY c.name, mi.name";
        }
        
        return $this->fetchAll($sql, $params);
    }

    /**
     * Get menu items by category
     * @param string $categoryId Category ID
     * @return array Menu items
     */
    public function getByCategory(string $categoryId): array {
        // CRITICAL: Add tenant filtering
        $filter = $this->getTenantFilter();
        $params = array_merge(['category_id' => $categoryId], $filter['params']);
        $whereConditions = ['mi.category_id = :category_id', 'mi.is_available = 1'];
        
        if (!empty($filter['where'])) {
            // getTenantFilter() returns "business_id = :tenant_filter_id" without table prefix
            $whereConditions[] = $this->tenantWhereForAlias('mi', $filter['where']);
        }
        
        $whereClause = "WHERE " . implode(" AND ", $whereConditions);
        
        $sql = "SELECT mi.*, c.name as category_name
                FROM {$this->table} mi 
                LEFT JOIN categories c ON mi.category_id = c.category_id 
                {$whereClause}
                ORDER BY mi.name";
        return $this->fetchAll($sql, $params);
    }

    /**
     * Find menu item by ID
     * @param string $menuItemId Menu item ID
     * @return array|null Menu item data or null
     */
    public function findById(string $menuItemId): ?array {
        // CRITICAL: Add tenant filtering
        $filter = $this->getTenantFilter();
        $params = array_merge(['menu_item_id' => $menuItemId], $filter['params']);
        $whereConditions = ['mi.menu_item_id = :menu_item_id'];
        
        if (!empty($filter['where'])) {
            // getTenantFilter() returns "business_id = :tenant_filter_id" without table prefix
            $whereConditions[] = $this->tenantWhereForAlias('mi', $filter['where']);
        }
        
        $whereClause = "WHERE " . implode(" AND ", $whereConditions);
        
        $sql = "SELECT mi.*, c.name as category_name
                FROM {$this->table} mi 
                LEFT JOIN categories c ON mi.category_id = c.category_id 
                {$whereClause}
                LIMIT 1";
        return $this->fetchOne($sql, $params);
    }

    /**
     * Update menu item stock (decrease)
     * @param string $menuItemId Menu item ID
     * @param int $quantity Quantity to decrease
     * @return bool Success
     */
    public function updateStock(string $menuItemId, int $quantity): bool {
        // CRITICAL: Add tenant filter for security
        $filter = $this->getTenantFilter();
        $params = [
            'menu_item_id' => $menuItemId,
            'quantity' => $quantity
        ];

        $whereConditions = ['menu_item_id = :menu_item_id', 'stock >= :quantity'];
        if (!empty($filter['where'])) {
            $whereConditions[] = $filter['where'];
            $params = array_merge($params, $filter['params']);
        }

        $sql = "UPDATE {$this->table}
                SET stock = stock - :quantity
                WHERE " . implode(" AND ", $whereConditions);

        $result = $this->execute($sql, $params);

        // If stock update was successful, reconcile availability based on the
        // new stock level. Three thresholds are honoured:
        //   - stock <= 0                → auto-disable (out of stock)
        //   - stock <= low_threshold    → stays enabled, but marked as "low"
        //     (the UI badges it; order path still allows sales)
        //   - stock above all thresholds AND previously disabled → auto-enable
        if ($result) {
            $checkSql = "SELECT stock, track_stock, is_available, low_stock_threshold
                         FROM {$this->table} WHERE menu_item_id = :menu_item_id";
            $checkStmt = $this->db->prepare($checkSql);
            $checkStmt->execute(['menu_item_id' => $menuItemId]);
            $item = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if ($item && (int)$item['track_stock'] === 1) {
                $stock = (int)$item['stock'];
                $available = (int)$item['is_available'];
                if ($stock <= 0 && $available === 1) {
                    // Fully depleted → pull from sale.
                    $availabilityStmt = $this->db->prepare(
                        "UPDATE {$this->table} SET is_available = 0 WHERE menu_item_id = :menu_item_id"
                    );
                    $availabilityStmt->execute(['menu_item_id' => $menuItemId]);
                } elseif ($stock > 0 && $available === 0) {
                    // Restocked → put back on sale.
                    $availabilityStmt = $this->db->prepare(
                        "UPDATE {$this->table} SET is_available = 1 WHERE menu_item_id = :menu_item_id"
                    );
                    $availabilityStmt->execute(['menu_item_id' => $menuItemId]);
                }
            }
        }

        return $result;
    }

    /**
     * Get menu items considered "low stock". An item is low-stock when
     * `track_stock = 1` AND `stock > 0` AND `stock <= max(low_stock_threshold, fallback)`.
     * The $threshold argument acts as a lower-bound fallback for items where
     * `low_stock_threshold` is zero (not explicitly configured).
     *
     * @param int $threshold Fallback threshold (default: 10)
     * @return array          Menu items with low stock, cheapest first.
     */
    public function getLowStock(int $threshold = 10): array {
        $filter = $this->getTenantFilter();
        $params = array_merge(['threshold' => $threshold], $filter['params']);
        $whereConditions = [
            'mi.track_stock = 1',
            'mi.stock > 0',
            '(
                (mi.low_stock_threshold > 0 AND mi.stock <= mi.low_stock_threshold)
                OR (COALESCE(mi.low_stock_threshold, 0) = 0 AND mi.stock <= :threshold)
            )',
        ];

        if (!empty($filter['where'])) {
            $whereConditions[] = $this->tenantWhereForAlias('mi', $filter['where']);
        }

        $whereClause = "WHERE " . implode(" AND ", $whereConditions);

        $sql = "SELECT mi.*, c.name as category_name
                FROM {$this->table} mi
                LEFT JOIN categories c ON mi.category_id = c.category_id
                {$whereClause}
                ORDER BY mi.stock ASC";
        return $this->fetchAll($sql, $params);
    }

    /**
     * Get out of stock menu items
     * @return array Out of stock menu items
     */
    public function getOutOfStock(): array {
        // CRITICAL: Add tenant filtering
        $filter = $this->getTenantFilter();
        $params = $filter['params'];
        $whereConditions = ['mi.stock <= 0', 'mi.is_available = 1'];
        
        if (!empty($filter['where'])) {
            // getTenantFilter() returns "business_id = :tenant_filter_id" without table prefix
            $whereConditions[] = $this->tenantWhereForAlias('mi', $filter['where']);
        }
        
        $whereClause = "WHERE " . implode(" AND ", $whereConditions);
        
        $sql = "SELECT mi.*, c.name as category_name
                FROM {$this->table} mi 
                LEFT JOIN categories c ON mi.category_id = c.category_id 
                {$whereClause}
                ORDER BY mi.name";
        return $this->fetchAll($sql, $params);
    }

    /**
     * Search menu items by name or description
     * @param string $query Search query
     * @return array Matching menu items
     */
    public function search(string $query): array {
        // CRITICAL: Add tenant filtering
        $filter = $this->getTenantFilter();
        $params = array_merge(['query' => '%' . $query . '%'], $filter['params']);
        $whereConditions = ['(mi.name LIKE :query OR mi.description LIKE :query)'];
        
        if (!empty($filter['where'])) {
            // getTenantFilter() returns "business_id = :tenant_filter_id" without table prefix
            $whereConditions[] = $this->tenantWhereForAlias('mi', $filter['where']);
        }
        
        $whereClause = "WHERE " . implode(" AND ", $whereConditions);
        
        $sql = "SELECT mi.*, c.name as category_name
                FROM {$this->table} mi
                LEFT JOIN categories c ON mi.category_id = c.category_id
                {$whereClause}
                ORDER BY c.name, mi.name";
        return $this->fetchAll($sql, $params);
    }

    /**
     * Get menu items by tenant_id (business)
     * @param string $tenantId Tenant/Business ID
     * @return array Menu items for the specific business
     */
    public function getByTenantId(string $tenantId): array {
        $col = $this->detectTenantColumn();
        if (!$col) {
            return [];
        }
        $sql = "SELECT mi.*, c.name as category_name
                FROM {$this->table} mi
                LEFT JOIN categories c ON mi.category_id = c.category_id
                WHERE mi.{$col} = :tenant_id
                ORDER BY c.name, mi.name";
        return $this->fetchAll($sql, ['tenant_id' => $tenantId]);
    }
}
