<?php
namespace App\Repositories;

use App\Core\BaseRepository;
use PDO;

/**
 * Order Item Repository
 * Handles database operations for order items
 * 
 * @package App\Repositories
 */
class OrderItemRepository extends BaseRepository {
    protected $table = 'order_items';
    protected $primaryKey = 'order_item_id';

    /**
     * Constructor
     * @param \PDO $database Database connection
     */
    public function __construct($database) {
        parent::__construct($database);
    }


    /**
     * Get order items by order ID
     * @param string $orderId Order ID
     * @return array Order items with menu item details
     */
    public function getByOrder(string $orderId): array {
        $columnCache = [
            'excluded_ingredients' => \App\Core\DbSchema::hasColumn($this->table, 'excluded_ingredients'),
            'selected_extras'      => \App\Core\DbSchema::hasColumn($this->table, 'selected_extras'),
            'preparation_time'     => \App\Core\DbSchema::hasColumn('menu_items', 'preparation_time'),
            'cooking_time'         => \App\Core\DbSchema::hasColumn('menu_items', 'cooking_time'),
            'serve_time'           => \App\Core\DbSchema::hasColumn('menu_items', 'serve_time'),
        ];

        $excludedIngredientsField = $columnCache['excluded_ingredients'] 
            ? "COALESCE(oi.excluded_ingredients, '[]') as excluded_ingredients" 
            : "'[]' as excluded_ingredients";
        $selectedExtrasField = $columnCache['selected_extras'] 
            ? "COALESCE(oi.selected_extras, '[]') as selected_extras" 
            : "'[]' as selected_extras";
        
        // Build time fields conditionally
        $timeFields = '';
        if ($columnCache['preparation_time'] || $columnCache['cooking_time'] || $columnCache['serve_time']) {
            $timeFieldsArray = [];
            if ($columnCache['preparation_time']) {
                $timeFieldsArray[] = 'mi.preparation_time';
            }
            if ($columnCache['cooking_time']) {
                $timeFieldsArray[] = 'mi.cooking_time';
            }
            if ($columnCache['serve_time']) {
                $timeFieldsArray[] = 'mi.serve_time';
            }
            if (!empty($timeFieldsArray)) {
                $timeFields = ',' . implode(',', $timeFieldsArray);
            }
        }
        
        $sql = "SELECT oi.*, 
                    mi.name as item_name, 
                    mi.price as menu_price,
                    pv.name as variant_name,
                    pv.price_modifier as variant_price_modifier{$timeFields},
                    {$excludedIngredientsField},
                    {$selectedExtrasField}
                FROM {$this->table} oi 
                LEFT JOIN menu_items mi ON oi.menu_item_id = mi.menu_item_id 
                LEFT JOIN product_variants pv ON oi.variant_id = pv.variant_id
                WHERE oi.order_id = :order_id 
                ORDER BY oi.created_at";
        $items = $this->fetchAll($sql, ['order_id' => $orderId]);
        return $this->enrichItemsWithIngredientAndExtras($items);
    }

    /**
     * Load excluded_ingredients from order_item_ingredients and selected_extras from order_item_extras,
     * merge into each item so waiter/kitchen/admin/customer screens all show malzeme çıkar and ürün notu.
     */
    private function enrichItemsWithIngredientAndExtras(array $items): array {
        if (empty($items)) {
            return $items;
        }
        $orderItemIds = array_values(array_unique(array_filter(array_column($items, 'order_item_id'))));
        if (empty($orderItemIds)) {
            return $items;
        }
        $placeholders = implode(',', array_fill(0, count($orderItemIds), '?'));
        $excludedMap = [];
        $extrasMap = [];
        try {
            $excludedStmt = $this->db->prepare("SELECT order_item_id, ingredient_name FROM order_item_ingredients WHERE order_item_id IN ($placeholders) AND is_excluded = 1");
            $excludedStmt->execute($orderItemIds);
            foreach ($excludedStmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $oid = $row['order_item_id'];
                if (!isset($excludedMap[$oid])) {
                    $excludedMap[$oid] = [];
                }
                $excludedMap[$oid][] = $row['ingredient_name'];
            }
        } catch (\Throwable $e) {
            // Table might not exist
        }
        try {
            $extrasStmt = $this->db->prepare("SELECT order_item_id, name, price FROM order_item_extras WHERE order_item_id IN ($placeholders)");
            $extrasStmt->execute($orderItemIds);
            foreach ($extrasStmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $oid = $row['order_item_id'];
                if (!isset($extrasMap[$oid])) {
                    $extrasMap[$oid] = [];
                }
                $extrasMap[$oid][] = ['name' => $row['name'], 'price' => (float)($row['price'] ?? 0)];
            }
        } catch (\Throwable $e) {
            // Table might not exist
        }
        foreach ($items as &$item) {
            $oid = $item['order_item_id'] ?? null;
            if ($oid) {
                $item['excluded_ingredients'] = $excludedMap[$oid] ?? [];
                $item['selected_extras'] = $extrasMap[$oid] ?? [];
            }
        }
        unset($item);
        return $items;
    }

    /**
     * Get order items by multiple order IDs (batch operation)
     * @param array $orderIds Array of order IDs
     * @return array Order items grouped by order_id
     */
    public function getByOrderIds(array $orderIds): array {
        if (empty($orderIds)) {
            return [];
        }
        
        $columnCache = [
            'excluded_ingredients' => \App\Core\DbSchema::hasColumn($this->table, 'excluded_ingredients'),
            'selected_extras'      => \App\Core\DbSchema::hasColumn($this->table, 'selected_extras'),
            'preparation_time'     => \App\Core\DbSchema::hasColumn('menu_items', 'preparation_time'),
            'cooking_time'         => \App\Core\DbSchema::hasColumn('menu_items', 'cooking_time'),
            'serve_time'           => \App\Core\DbSchema::hasColumn('menu_items', 'serve_time'),
        ];

        $excludedIngredientsField = $columnCache['excluded_ingredients'] 
            ? "COALESCE(oi.excluded_ingredients, '[]') as excluded_ingredients" 
            : "'[]' as excluded_ingredients";
        $selectedExtrasField = $columnCache['selected_extras'] 
            ? "COALESCE(oi.selected_extras, '[]') as selected_extras" 
            : "'[]' as selected_extras";
        
        // Build time fields conditionally
        $timeFields = '';
        if ($columnCache['preparation_time'] || $columnCache['cooking_time'] || $columnCache['serve_time']) {
            $timeFieldsArray = [];
            if ($columnCache['preparation_time']) {
                $timeFieldsArray[] = 'mi.preparation_time';
            }
            if ($columnCache['cooking_time']) {
                $timeFieldsArray[] = 'mi.cooking_time';
            }
            if ($columnCache['serve_time']) {
                $timeFieldsArray[] = 'mi.serve_time';
            }
            if (!empty($timeFieldsArray)) {
                $timeFields = ',' . implode(',', $timeFieldsArray);
            }
        }
        
        // Create placeholders for IN clause
        $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
        
        $sql = "SELECT oi.*, 
                    mi.name as item_name, 
                    mi.price as menu_price,
                    pv.name as variant_name,
                    pv.price_modifier as variant_price_modifier{$timeFields},
                    {$excludedIngredientsField},
                    {$selectedExtrasField}
                FROM {$this->table} oi 
                LEFT JOIN menu_items mi ON oi.menu_item_id = mi.menu_item_id 
                LEFT JOIN product_variants pv ON oi.variant_id = pv.variant_id
                WHERE oi.order_id IN ($placeholders)
                ORDER BY oi.order_id, oi.created_at";
        
        $items = $this->fetchAll($sql, $orderIds);
        return $this->enrichItemsWithIngredientAndExtras($items);
    }

    /**
     * Get order items by menu item ID
     * @param string $menuItemId Menu item ID
     * @return array Order items
     */
    public function getByMenuItem(string $menuItemId): array {
        $sql = "SELECT * FROM {$this->table} 
                WHERE menu_item_id = :menu_item_id 
                ORDER BY created_at DESC";
        return $this->fetchAll($sql, ['menu_item_id' => $menuItemId]);
    }

    /**
     * Update order item quantity
     * @param string $orderItemId Order item ID
     * @param int $quantity New quantity
     * @return bool Success
     */
    public function updateQuantity(string $orderItemId, int $quantity): bool {
        $sql = "UPDATE {$this->table} 
                SET quantity = :quantity 
                WHERE order_item_id = :order_item_id";
        return $this->execute($sql, [
            'order_item_id' => $orderItemId,
            'quantity' => $quantity
        ]);
    }

    /**
     * Get total sales by menu item
     * @param string $menuItemId Menu item ID
     * @param string|null $startDate Optional start date
     * @param string|null $endDate Optional end date
     * @return array Total quantity and revenue
     */
    public function getTotalByMenuItem(string $menuItemId, ?string $startDate = null, ?string $endDate = null): array {
        $sql = "SELECT 
                    SUM(quantity) as total_quantity,
                    SUM(price * quantity) as total_revenue
                FROM {$this->table} 
                WHERE menu_item_id = :menu_item_id";
        
        $params = ['menu_item_id' => $menuItemId];
        
        if ($startDate && $endDate) {
            $sql .= " AND created_at BETWEEN :start_date AND :end_date";
            $params['start_date'] = $startDate;
            $params['end_date'] = $endDate;
        }
        
        $result = $this->fetchOne($sql, $params);
        
        return [
            'quantity' => (float)($result['total_quantity'] ?? 0),
            'revenue' => (float)($result['total_revenue'] ?? 0)
        ];
    }


    /**
     * Update preparation_status for given order item IDs (ekran bazlı Bekliyor/Hazırlanıyor/Hazır)
     * @param array $orderItemIds Order item IDs
     * @param string $status PENDING|PREPARING|READY|SERVED
     * @return bool Success
     */
    public function updatePreparationStatusByIds(array $orderItemIds, string $status): bool {
        if (empty($orderItemIds)) {
            return true;
        }
        try {
            $placeholders = implode(',', array_fill(0, count($orderItemIds), '?'));
            $sql = "UPDATE {$this->table} SET preparation_status = ? WHERE order_item_id IN ($placeholders)";
            $params = array_values(array_merge([$status], $orderItemIds));
            return $this->execute($sql, $params);
        } catch (\Exception $e) {
            error_log('OrderItemRepository::updatePreparationStatusByIds: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get order item by ID with menu item name (for approval requests, etc.)
     * @param string $orderItemId Order item ID
     * @return array|null Order item with item_name from menu_items
     */
    public function findByIdWithMenuItemName(string $orderItemId): ?array {
        $sql = "SELECT oi.*, 
                mi.name as item_name,
                pv.name as variant_name
                FROM {$this->table} oi 
                LEFT JOIN menu_items mi ON oi.menu_item_id = mi.menu_item_id 
                LEFT JOIN product_variants pv ON oi.variant_id = pv.variant_id
                WHERE oi.{$this->primaryKey} = :id 
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $orderItemId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Get order item IDs for a table that are still in preparation (PENDING or PREPARING).
     * Used to block payment until manager approves cancel or items are served.
     * @param string $tableId Table ID
     * @return array [order_item_id, ...]
     */
    public function getOrderItemIdsInPreparationByTableId(string $tableId): array {
        try {
            $sql = "SELECT oi.order_item_id FROM {$this->table} oi
                    INNER JOIN orders o ON oi.order_id = o.order_id
                    WHERE o.table_id = ? AND o.status NOT IN ('SERVED', 'CANCELLED')
                    AND oi.preparation_status IN ('PENDING', 'PREPARING')";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$tableId]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            return array_column($rows, 'order_item_id');
        } catch (\Exception $e) {
            error_log('OrderItemRepository::getOrderItemIdsInPreparationByTableId: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Delete order item
     * @param string $orderItemId Order item ID
     * @return bool Success
     */
    public function deleteOrderItem(string $orderItemId): bool {
        return $this->delete($orderItemId);
    }
}

