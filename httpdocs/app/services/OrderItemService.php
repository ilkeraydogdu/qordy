<?php
namespace App\Services;

use App\Core\BaseService;
use App\Repositories\OrderItemRepository;

class OrderItemService extends BaseService {
    
    public function __construct(OrderItemRepository $orderItemRepository) {
        parent::__construct($orderItemRepository);
    }
    
    /**
     * Get order items by order ID
     * @param string $orderId
     * @return array
     */
    public function getOrderItemsByOrder(string $orderId): array {
        return $this->repository->getByOrder($orderId);
    }
    
    /**
     * Get order items by multiple order IDs (batch operation for performance)
     * @param array $orderIds Array of order IDs
     * @return array Order items
     */
    public function getOrderItemsByOrderIds(array $orderIds): array {
        return $this->repository->getByOrderIds($orderIds);
    }
    
    /**
     * Get order item by ID
     * @param string $orderItemId
     * @return array|null
     */
    public function getOrderItemById(string $orderItemId): ?array {
        return $this->repository->findById($orderItemId);
    }

    /**
     * Get order item by ID with menu item name (for approval requests)
     * @param string $orderItemId
     * @return array|null Order item with item_name, variant_name
     */
    public function getOrderItemByIdWithName(string $orderItemId): ?array {
        return $this->repository->findByIdWithMenuItemName($orderItemId);
    }
    
    /**
     * Get order items by menu item ID
     * @param string $menuItemId
     * @return array
     */
    public function getOrderItemsByMenuItem(string $menuItemId): array {
        return $this->repository->getByMenuItem($menuItemId);
    }
    
    /**
     * Aynı ürün (menu_item_id, variant, excluded, extras) varsa mevcut order_item_id döner - merge için
     * @param string $orderId
     * @param string $menuItemId
     * @param string|null $variantId
     * @param array $excludedIngredients
     * @param array $selectedExtras
     * @return string|null order_item_id veya null
     */
    public function findMergeableOrderItem(string $orderId, string $menuItemId, ?string $variantId, array $excludedIngredients = [], array $selectedExtras = []): ?string {
        $items = $this->getOrderItemsByOrder($orderId);
        $exclNorm = array_values(array_unique(array_map(function ($x) {
            return is_string($x) ? trim($x) : trim($x['name'] ?? $x['ingredient_name'] ?? '');
        }, $excludedIngredients)));
        sort($exclNorm);
        $extrasNorm = array_values(array_unique(array_map(function ($x) {
            return is_array($x) ? ($x['name'] ?? '') : (string) $x;
        }, $selectedExtras)));
        sort($extrasNorm);
        foreach ($items as $item) {
            if ((string)($item['menu_item_id'] ?? '') !== (string)$menuItemId) continue;
            $itemVariant = $item['variant_id'] ?? null;
            if ((string)($variantId ?: '') !== (string)($itemVariant ?: '')) continue;
            $itemExcl = $item['excluded_ingredients'] ?? [];
            if (is_string($itemExcl)) $itemExcl = json_decode($itemExcl, true) ?: [];
            $itemExclNorm = array_values(array_unique(array_map(function ($x) {
                return is_string($x) ? trim($x) : trim($x['name'] ?? $x['ingredient_name'] ?? '');
            }, $itemExcl)));
            sort($itemExclNorm);
            if (json_encode($exclNorm) !== json_encode($itemExclNorm)) continue;
            $itemExtras = $item['selected_extras'] ?? [];
            if (is_string($itemExtras)) $itemExtras = json_decode($itemExtras, true) ?: [];
            $itemExtrasNorm = array_values(array_unique(array_map(function ($x) {
                return is_array($x) ? ($x['name'] ?? '') : (string) $x;
            }, $itemExtras)));
            sort($itemExtrasNorm);
            if (json_encode($extrasNorm) !== json_encode($itemExtrasNorm)) continue;
            return $item['order_item_id'] ?? null;
        }
        return null;
    }
    
    /**
     * Create order item
     * @param array $orderItemData
     * @return bool|string Order item ID on success, false on failure
     */
    public function createOrderItem(array $orderItemData) {
        if (empty($orderItemData['order_item_id'])) {
            $orderItemData['order_item_id'] = generateId('oi');
        }
        
        if (empty($orderItemData['order_id']) || empty($orderItemData['menu_item_id'])) {
            return false;
        }
        
        $defaults = [
            'quantity' => 1
        ];
        
        $orderItemData = array_merge($defaults, $orderItemData);
        
        // Remove variant_id if it's null or empty (column doesn't exist in database)
        if (isset($orderItemData['variant_id']) && empty($orderItemData['variant_id'])) {
            unset($orderItemData['variant_id']);
        }
        
        $result = $this->repository->create($orderItemData);
        
        if ($result) {
            return $orderItemData['order_item_id'];
        }
        
        return false;
    }
    
    /**
     * Update order item quantity
     * @param string $orderItemId
     * @param int $quantity
     * @return bool
     */
    public function updateQuantity(string $orderItemId, int $quantity): bool {
        return $this->repository->updateQuantity($orderItemId, $quantity);
    }
    
    /**
     * Delete order item
     * @param string $orderItemId
     * @return bool
     */
    public function deleteOrderItem(string $orderItemId): bool {
        return $this->repository->deleteOrderItem($orderItemId);
    }
    
    /**
     * Get total by menu item
     * @param string $menuItemId
     * @param string|null $startDate
     * @param string|null $endDate
     * @return array
     */
    public function getTotalByMenuItem(string $menuItemId, ?string $startDate = null, ?string $endDate = null): array {
        return $this->repository->getTotalByMenuItem($menuItemId, $startDate, $endDate);
    }

    /**
     * Update preparation_status for given order items (ekran bazlı: Bekliyor/Hazırlanıyor/Hazır)
     * @param array $orderItemIds Order item IDs
     * @param string $status PENDING|PREPARING|READY|SERVED
     * @return bool Success
     */
    public function updatePreparationStatusByIds(array $orderItemIds, string $status): bool {
        if (empty($orderItemIds)) {
            return true;
        }
        return $this->repository->updatePreparationStatusByIds($orderItemIds, $status);
    }

    /**
     * Check if table has any order items still in preparation (mutfak/hazırlık ekranında).
     * @param string $tableId Table ID
     * @return bool True if any item is PENDING or PREPARING
     */
    public function hasItemsInPreparationByTableId(string $tableId): bool {
        $ids = $this->repository->getOrderItemIdsInPreparationByTableId($tableId);
        return !empty($ids);
    }

    /**
     * Get order item IDs for table that are in preparation (for manager cancel approval).
     * @param string $tableId Table ID
     * @return array Order item IDs
     */
    public function getOrderItemIdsInPreparationByTableId(string $tableId): array {
        return $this->repository->getOrderItemIdsInPreparationByTableId($tableId);
    }
}

