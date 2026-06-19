<?php
namespace App\Services\Order;

use App\Core\DependencyFactory;

/**
 * Order Item Manager
 * Handles order item creation, updates, and batch operations
 */
class OrderItemManager {
    private $orderItemService;
    private $menuItemService;
    
    public function __construct() {
        $this->orderItemService = DependencyFactory::getOrderItemService();
        $this->menuItemService = DependencyFactory::getMenuItemService();
    }
    
    /**
     * Batch create order items
     * @param string $orderId Order ID
     * @param array $items Array of order items
     * @return array Array of created order items with order_item_id and menu_item_id
     */
    public function batchCreateOrderItems(string $orderId, array $items): array {
        $createdItems = [];
        
        if (empty($orderId) || empty($items)) {
            \App\Core\Logger::warning('OrderItemManager: Empty orderId or items', [
                'order_id' => $orderId,
                'items_count' => count($items)
            ]);
            return $createdItems;
        }
        
        foreach ($items as $index => $item) {
            try {
                $menuItemId = $item['menu_item_id'] ?? '';
                $quantity = intval($item['quantity'] ?? 1);
                $note = $item['note'] ?? '';
                
                if (empty($menuItemId)) {
                    \App\Core\Logger::warning('OrderItemManager: Empty menu_item_id', [
                        'order_id' => $orderId,
                        'item_index' => $index,
                        'item' => $item
                    ]);
                    continue; // Skip invalid items
                }
                
                // Get menu item details
                $menuItem = $this->menuItemService->getMenuItemById($menuItemId);
                if (!$menuItem) {
                    \App\Core\Logger::warning('OrderItemManager: Menu item not found', [
                        'order_id' => $orderId,
                        'menu_item_id' => $menuItemId
                    ]);
                    continue; // Skip if menu item not found
                }
                
                // Base price - use client's unit_price if provided (includes extras), otherwise use menu item price
                $useClientPrice = isset($item['unit_price']) && is_numeric($item['unit_price']) && floatval($item['unit_price']) >= 0;
                $price = $useClientPrice
                    ? floatval($item['unit_price'])
                    : floatval($menuItem['price'] ?? 0);
                
                // Get variant_id if provided
                $variantId = $item['variant_id'] ?? null;
                
                // If product has variants and variant_id is provided, get variant price modifier
                // Only add modifier if we're using base price (not client's calculated price)
                $variantPriceModifier = 0;
                if (!$useClientPrice && $variantId && !empty($menuItem['has_variants']) && $menuItem['has_variants'] == 1) {
                    try {
                        $productVariantService = DependencyFactory::getProductVariantService();
                        $variant = $productVariantService->getVariantsByProduct($menuItemId);
                        foreach ($variant as $v) {
                            if ($v['variant_id'] === $variantId) {
                                $variantPriceModifier = floatval($v['price_modifier'] ?? 0);
                                break;
                            }
                        }
                        // Adjust price with variant modifier
                        $price = $price + $variantPriceModifier;
                    } catch (\Exception $e) {
                        \App\Core\Logger::warning('OrderItemManager: Failed to get variant', [
                            'order_id' => $orderId,
                            'menu_item_id' => $menuItemId,
                            'variant_id' => $variantId,
                            'error' => $e->getMessage()
                        ]);
                        // Continue without variant modifier
                    }
                }
                
                // If using base price (not client price), add extras prices
                if (!$useClientPrice) {
                    $selectedExtras = $item['selected_extras'] ?? [];
                    if (!empty($selectedExtras) && is_array($selectedExtras)) {
                        foreach ($selectedExtras as $extra) {
                            if (is_array($extra) && isset($extra['price'])) {
                                $price += floatval($extra['price']);
                            }
                        }
                    }
                }
                
                // Prepare order item data
                $orderItemId = generateId('oi');
                $orderItemData = [
                    'order_item_id' => $orderItemId,
                    'order_id' => $orderId,
                    'menu_item_id' => $menuItemId,
                    'quantity' => $quantity,
                    'price' => $price,
                    'note' => $note ?: ''
                ];
                
                // Only add variant_id if it's not null/empty
                if (!empty($variantId)) {
                    $orderItemData['variant_id'] = $variantId;
                }
                
                // Create order item
                $result = $this->orderItemService->createOrderItem($orderItemData);
                if ($result) {
                    $createdItems[] = [
                        'order_item_id' => $orderItemId,
                        'menu_item_id' => $menuItemId,
                        'quantity' => $quantity,
                        'note' => $note
                    ];
                    
                    // Save selected extras to order_item_extras table
                    $selectedExtras = $item['selected_extras'] ?? [];
                    if (!empty($selectedExtras) && is_array($selectedExtras)) {
                        try {
                            $db = DependencyFactory::getDatabase();
                            $extraStmt = $db->prepare("INSERT INTO order_item_extras (order_item_id, name, price) VALUES (?, ?, ?)");
                            foreach ($selectedExtras as $extra) {
                                $extraName = is_array($extra) ? ($extra['name'] ?? '') : $extra;
                                $extraPrice = is_array($extra) ? floatval($extra['price'] ?? 0) : 0;
                                if (!empty($extraName)) {
                                    $extraStmt->execute([$orderItemId, $extraName, $extraPrice]);
                                }
                            }
                            \App\Core\Logger::debug('OrderItemManager: Extras saved', [
                                'order_item_id' => $orderItemId,
                                'extras_count' => count($selectedExtras)
                            ]);
                        } catch (\Exception $e) {
                            \App\Core\Logger::error('OrderItemManager: Failed to save extras', [
                                'order_item_id' => $orderItemId,
                                'error' => $e->getMessage()
                            ]);
                            throw new \RuntimeException("Sipariş ekstra malzemeleri kaydedilemedi: " . $e->getMessage(), 0, $e);
                        }
                    }
                    
                    // Save excluded ingredients to order_item_ingredients table
                    $excludedIngredients = $item['excluded_ingredients'] ?? [];
                    if (!empty($excludedIngredients) && is_array($excludedIngredients)) {
                        try {
                            $db = DependencyFactory::getDatabase();
                            $ingredientStmt = $db->prepare("INSERT INTO order_item_ingredients (order_item_id, ingredient_name, is_excluded) VALUES (?, ?, 1)");
                            foreach ($excludedIngredients as $ingredient) {
                                $ingredientName = is_string($ingredient) ? $ingredient : ($ingredient['name'] ?? '');
                                if (!empty($ingredientName)) {
                                    $ingredientStmt->execute([$orderItemId, $ingredientName]);
                                }
                            }
                            \App\Core\Logger::debug('OrderItemManager: Excluded ingredients saved', [
                                'order_item_id' => $orderItemId,
                                'excluded_count' => count($excludedIngredients)
                            ]);
                        } catch (\Exception $e) {
                            \App\Core\Logger::error('OrderItemManager: Failed to save excluded ingredients', [
                                'order_item_id' => $orderItemId,
                                'error' => $e->getMessage()
                            ]);
                            throw new \RuntimeException("Sipariş hariç tutulan malzemeleri kaydedilemedi: " . $e->getMessage(), 0, $e);
                        }
                    }
                    
                    // Auto-deduct stock if product has stock tracking enabled
                    $trackStock = isset($menuItem['track_stock']) && $menuItem['track_stock'] == 1;
                    if ($trackStock && !empty($menuItemId)) {
                        try {
                            $this->menuItemService->updateStock($menuItemId, $quantity);
                            \App\Core\Logger::info('OrderItemManager: Stock deducted', [
                                'order_id' => $orderId,
                                'menu_item_id' => $menuItemId,
                                'quantity' => $quantity
                            ]);
                        } catch (\Exception $e) {
                            \App\Core\Logger::error('OrderItemManager: Failed to deduct stock', [
                                'order_id' => $orderId,
                                'menu_item_id' => $menuItemId,
                                'quantity' => $quantity,
                                'error' => $e->getMessage()
                            ]);
                            // Don't fail order creation if stock update fails, but log it
                        }
                    }
                } else {
                    \App\Core\Logger::error('OrderItemManager: Failed to create order item', [
                        'order_id' => $orderId,
                        'menu_item_id' => $menuItemId,
                        'order_item_data' => $orderItemData
                    ]);
                    throw new \RuntimeException("Sipariş kalemi (Menü ID: {$menuItemId}) kaydedilemedi.");
                }
            } catch (\PDOException $e) {
                \App\Core\Logger::error('OrderItemManager: PDOException creating order item', [
                    'order_id' => $orderId,
                    'item_index' => $index,
                    'item' => $item,
                    'order_item_data' => $orderItemData ?? [],
                    'error' => $e->getMessage(),
                    'sql_state' => $e->errorInfo[0] ?? 'unknown',
                    'error_code' => $e->errorInfo[1] ?? 'unknown',
                    'error_message' => $e->errorInfo[2] ?? 'unknown',
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]);
                error_log("OrderItemManager PDOException: " . $e->getMessage() . " | SQLState: " . ($e->errorInfo[0] ?? 'unknown') . " | Error: " . ($e->errorInfo[2] ?? 'unknown'));
                // Don't continue - re-throw to stop order creation
                throw $e;
            } catch (\Exception $e) {
                \App\Core\Logger::error('OrderItemManager: Exception creating order item', [
                    'order_id' => $orderId,
                    'item_index' => $index,
                    'item' => $item,
                    'order_item_data' => $orderItemData ?? [],
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]);
                error_log("OrderItemManager Exception: " . $e->getMessage());
                // Don't continue - re-throw to stop order creation
                throw $e;
            }
        }
        
        return $createdItems;
    }
    
    /**
     * Update order item quantity
     * @param string $orderItemId Order item ID
     * @param int $quantity New quantity
     * @return bool True on success, false on failure
     */
    public function updateItemQuantity(string $orderItemId, int $quantity): bool {
        if ($quantity <= 0) {
            return false;
        }
        
        $orderItem = $this->orderItemService->getById($orderItemId);
        if (!$orderItem) {
            return false;
        }
        
        return $this->orderItemService->update($orderItemId, [
            'quantity' => $quantity,
            'total_price' => floatval($orderItem['price']) * $quantity
        ]);
    }
    
    /**
     * Remove order item
     * @param string $orderItemId Order item ID
     * @return bool True on success, false on failure
     */
    public function removeItem(string $orderItemId): bool {
        return $this->orderItemService->delete($orderItemId);
    }
    
    /**
     * Get order items for an order
     * @param string $orderId Order ID
     * @return array Array of order items
     */
    public function getOrderItems(string $orderId): array {
        return $this->orderItemService->getByOrder($orderId);
    }
}

