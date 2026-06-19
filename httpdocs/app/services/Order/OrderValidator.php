<?php
namespace App\Services\Order;

use App\Core\DependencyFactory;

/**
 * Order Validator
 * Handles order validation logic
 */
class OrderValidator {
    private $menuItemService;
    private $tableService;
    private $categoryService;
    
    public function __construct() {
        $this->menuItemService = DependencyFactory::getMenuItemService();
        $this->tableService = DependencyFactory::getTableService();
        $this->categoryService = DependencyFactory::getCategoryService();
    }
    
    /**
     * Validate order data
     * @param array $orderData Order data
     * @return array ['valid' => bool, 'errors' => array]
     */
    public function validateOrder(array $orderData): array {
        $errors = [];
        
        // Validate table_id (if not delivery)
        $isDelivery = $orderData['is_delivery'] ?? false;
        if (!$isDelivery) {
            $tableId = $orderData['table_id'] ?? '';
            if (empty($tableId)) {
                $errors[] = 'Table ID is required for non-delivery orders';
            } else {
                $table = $this->tableService->getTableById($tableId);
                if (!$table) {
                    $errors[] = 'Invalid table ID';
                }
            }
        } else {
            // Validate delivery address
            $deliveryAddress = $orderData['delivery_address'] ?? '';
            if (empty($deliveryAddress)) {
                $errors[] = 'Delivery address is required for delivery orders';
            }
        }
        
        // Validate items
        $items = $orderData['items'] ?? [];
        $orderSource = $orderData['order_source'] ?? 'QR';
        if (empty($items) || !is_array($items)) {
            $errors[] = 'Order must contain at least one item';
        } else {
            foreach ($items as $index => $item) {
                $itemErrors = $this->validateOrderItem($item, $index, $orderSource);
                if (!empty($itemErrors)) {
                    $errors = array_merge($errors, $itemErrors);
                }
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Validate a single order item
     * @param array $item Order item data
     * @param int $index Item index (for error messages)
     * @param string $orderSource Order source (POS, QR, etc.)
     * @return array Array of error messages
     */
    public function validateOrderItem(array $item, int $index = 0, string $orderSource = 'QR'): array {
        $errors = [];
        
        // Validate menu_item_id
        $menuItemId = $item['menu_item_id'] ?? '';
        if (empty($menuItemId)) {
            $errors[] = "Item #{$index}: Menu item ID is required";
        } else {
            $menuItem = $this->menuItemService->getMenuItemById($menuItemId);
            if (!$menuItem) {
                $errors[] = "Item #{$index}: Invalid menu item ID";
            }
        }
        
        // Validate quantity
        $quantity = $item['quantity'] ?? 0;
        if (empty($quantity) || $quantity <= 0) {
            $errors[] = "Item #{$index}: Quantity must be greater than 0";
        }
        
        // Validate price
        $price = $item['price'] ?? 0;
        if ($price < 0) {
            $errors[] = "Item #{$index}: Price cannot be negative";
        }
        
        return $errors;
    }
    
    /**
     * Check if order requires kitchen preparation
     * @param array $items Order items
     * @return bool True if order requires kitchen
     */
    public function checkIfOrderRequiresKitchen(array $items): bool {
        foreach ($items as $item) {
            $menuItemId = $item['menu_item_id'] ?? '';
            if (empty($menuItemId)) {
                continue;
            }
            
            $menuItem = $this->menuItemService->getMenuItemById($menuItemId);
            if (!$menuItem) {
                continue;
            }
            
            // Get production_point from menu item or category default
            $productionPoint = null;
            
            // First check menu item's production_point
            if (!empty($menuItem['production_point'])) {
                $productionPoint = $menuItem['production_point'];
            } else {
                // If not set, check category's default_production_point
                if (!empty($menuItem['category_id'])) {
                    $category = $this->categoryService->getCategoryById($menuItem['category_id']);
                    if ($category && !empty($category['default_production_point'])) {
                        $productionPoint = $category['default_production_point'];
                    }
                }
            }
            
            // Check if production_point is KITCHEN
            if ($productionPoint === 'KITCHEN') {
                return true;
            }
            
            // Fallback: Check legacy requires_kitchen field for backward compatibility
            if (isset($menuItem['requires_kitchen']) && $menuItem['requires_kitchen'] == 1) {
                return true;
            }
            
            if (isset($menuItem['category_id']) && !empty($menuItem['category_id'])) {
                $category = $this->categoryService->getCategoryById($menuItem['category_id']);
                if ($category && isset($category['requires_kitchen']) && $category['requires_kitchen'] == 1) {
                    return true;
                }
            }
        }
        
        return false;
    }
}

