<?php
namespace App\Repositories;

use App\Core\BaseRepository;

/**
 * Order Item Customization Repository
 * Handles database operations for order item customizations
 * 
 * @package App\Repositories
 */
class OrderItemCustomizationRepository extends BaseRepository {
    protected $table = 'order_item_customizations';
    protected $primaryKey = 'customization_id';

    /**
     * Constructor
     * @param \PDO $database Database connection
     */
    public function __construct($database) {
        parent::__construct($database);
    }

    /**
     * Get customizations by order item ID
     * @param string $orderItemId Order item ID
     * @return array Customizations
     */
    public function getByOrderItem(string $orderItemId): array {
        $sql = "SELECT c.*, i.name as ingredient_name, i.unit as ingredient_unit 
                FROM {$this->table} c
                LEFT JOIN ingredients i ON c.ingredient_id = i.ingredient_id
                WHERE c.order_item_id = :order_item_id
                ORDER BY c.created_at";
        return $this->fetchAll($sql, ['order_item_id' => $orderItemId]);
    }

    /**
     * Get all customizations for an order
     * @param string $orderId Order ID
     * @return array Customizations
     */
    public function getByOrder(string $orderId): array {
        $sql = "SELECT c.*, oi.order_item_id, oi.menu_item_id, i.name as ingredient_name, i.unit as ingredient_unit
                FROM {$this->table} c
                INNER JOIN order_items oi ON c.order_item_id = oi.order_item_id
                LEFT JOIN ingredients i ON c.ingredient_id = i.ingredient_id
                WHERE oi.order_id = :order_id
                ORDER BY oi.order_item_id, c.created_at";
        return $this->fetchAll($sql, ['order_id' => $orderId]);
    }

    /**
     * Create customization
     * @param array $data Customization data
     * @return bool Success
     */
    public function createCustomization(array $data): bool {
        return $this->create($data);
    }

    /**
     * Delete customizations by order item ID
     * @param string $orderItemId Order item ID
     * @return bool Success
     */
    public function deleteByOrderItem(string $orderItemId): bool {
        $sql = "DELETE FROM {$this->table} WHERE order_item_id = :order_item_id";
        return $this->execute($sql, ['order_item_id' => $orderItemId]);
    }

 /**
 * Get customizations for many order items in one query (N+1 fix).
 * @param array $orderItemIds
 * @return array<string, array> indexed by order_item_id
 */
 public function getByOrderItemIds(array $orderItemIds): array {
 if (empty($orderItemIds)) {
 return [];
 }
 $placeholders = implode(',', array_fill(0, count($orderItemIds), '?'));
 $sql = "SELECT c.*, i.name as ingredient_name, i.unit as ingredient_unit
 FROM {$this->table} c
 LEFT JOIN ingredients i ON c.ingredient_id = i.ingredient_id
 WHERE c.order_item_id IN ({$placeholders})
 ORDER BY c.order_item_id, c.created_at";
 $rows = $this->fetchAll($sql, $orderItemIds);
 $byItemId = [];
 foreach ($rows as $row) {
 $oid = $row['order_item_id'] ?? null;
 if ($oid !== null) {
 $byItemId[$oid][] = $row;
 }
 }
 return $byItemId;
 }

}

