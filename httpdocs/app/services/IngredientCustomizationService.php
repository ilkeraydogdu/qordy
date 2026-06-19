<?php
namespace App\Services;

use App\Core\BaseService;
use App\Repositories\OrderItemCustomizationRepository;
use App\Repositories\IngredientRepository;

/**
 * Ingredient Customization Service
 * Handles ingredient customization for menu items
 * 
 * @package App\Services
 */
class IngredientCustomizationService extends BaseService {
    private $ingredientRepository = null;

    /**
     * Constructor
     * @param OrderItemCustomizationRepository $repository Order item customization repository
     */
    public function __construct(OrderItemCustomizationRepository $repository) {
        parent::__construct($repository);
    }

    /**
     * Get ingredient repository (lazy loading)
     * @return \App\Repositories\IngredientRepository
     */
    private function getIngredientRepository() {
        if ($this->ingredientRepository === null) {
            require_once __DIR__ . '/../core/DependencyFactory.php';
            $this->ingredientRepository = \App\Core\DependencyFactory::getIngredientRepository();
        }
        return $this->ingredientRepository;
    }

    /**
     * Get customizations for order item
     * @param string $orderItemId Order item ID
     * @return array Customizations
     */
    public function getByOrderItem(string $orderItemId): array {
        return $this->repository->getByOrderItem($orderItemId);
    }

    /**
     * Get all customizations for an order
     * @param string $orderId Order ID
     * @return array Customizations
     */
    public function getByOrder(string $orderId): array {
        return $this->repository->getByOrder($orderId);
    }

    /**
     * Create customization
     * @param string $orderItemId Order item ID
     * @param array $customizations Customization data array
     * @return bool Success
     */
    public function createCustomizations(string $orderItemId, array $customizations): bool {
        $success = true;

        foreach ($customizations as $customization) {
            $ingredientId = $customization['ingredient_id'] ?? null;
            $action = $customization['action'] ?? 'remove';
            $quantity = floatval($customization['quantity'] ?? 1.0);
            $note = $customization['note'] ?? '';

            // Validate action
            if (!in_array($action, ['add', 'remove', 'extra'])) {
                continue;
            }

            // Validate ingredient exists if provided
            if ($ingredientId) {
                $ingredient = $this->getIngredientRepository()->getById($ingredientId);
                if (!$ingredient) {
                    continue;
                }
            }

            $customizationData = [
                'customization_id' => generateId('cust'),
                'order_item_id' => $orderItemId,
                'ingredient_id' => $ingredientId,
                'action' => $action,
                'quantity' => $quantity,
                'note' => $note
            ];

            if (!$this->repository->createCustomization($customizationData)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Get available ingredients for customization
     * @param string $menuItemId Menu item ID
     * @return array Available ingredients with customization options
     */
    public function getAvailableIngredients(string $menuItemId): array {
        // Get menu item ingredients
        require_once __DIR__ . '/../core/DependencyFactory.php';
        $menuItemService = \App\Core\DependencyFactory::getMenuItemService();
        $menuItem = $menuItemService->getMenuItemById($menuItemId);

        if (!$menuItem) {
            return [];
        }

        // Parse ingredients from menu item
        $ingredients = json_decode($menuItem['ingredients'] ?? '[]', true);
        if (!is_array($ingredients)) {
            $ingredients = [];
        }

        $availableIngredients = [];

        foreach ($ingredients as $ingredientData) {
            $ingredientId = $ingredientData['ingredient_id'] ?? null;
            if (!$ingredientId) {
                continue;
            }

            $ingredient = $this->getIngredientRepository()->getById($ingredientId);
            if (!$ingredient) {
                continue;
            }

            $availableIngredients[] = [
                'ingredient_id' => $ingredientId,
                'name' => $ingredient['name'],
                'unit' => $ingredient['unit'] ?? '',
                'is_removable' => $ingredientData['is_removable'] ?? false,
                'is_addable' => $ingredientData['is_addable'] ?? false,
                'default_quantity' => floatval($ingredientData['default_quantity'] ?? 1.0)
            ];
        }

        return $availableIngredients;
    }

    /**
     * Format customizations for display (kitchen view)
     * @param array $customizations Customizations array
     * @return string Formatted string
     */
    public function formatForDisplay(array $customizations): string {
        if (empty($customizations)) {
            return '';
        }

        $parts = [];

        foreach ($customizations as $customization) {
            $ingredientName = $customization['ingredient_name'] ?? 'Malzeme';
            $action = $customization['action'] ?? 'remove';
            $quantity = floatval($customization['quantity'] ?? 1.0);
            $note = $customization['note'] ?? '';

            $actionText = '';
            switch ($action) {
                case 'add':
                    $actionText = '+ ' . $ingredientName;
                    break;
                case 'remove':
                    $actionText = '- ' . $ingredientName;
                    break;
                case 'extra':
                    $actionText = '++ ' . $ingredientName;
                    break;
            }

            if ($quantity > 1) {
                $actionText .= ' (x' . $quantity . ')';
            }

            if (!empty($note)) {
                $actionText .= ' [' . $note . ']';
            }

            $parts[] = $actionText;
        }

        return implode(', ', $parts);
    }

 /**
 * Get customizations for many order items in one query (N+1 fix).
 * @param array $orderItemIds
 * @return array<string, array> indexed by order_item_id
 */
 public function getByOrderItemIds(array $orderItemIds): array {
 return $this->repository->getByOrderItemIds($orderItemIds);
 }

}

