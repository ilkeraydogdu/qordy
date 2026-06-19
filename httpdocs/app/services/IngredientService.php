<?php
namespace App\Services;

use App\Core\BaseService;
use App\Repositories\IngredientRepository;

/**
 * Ingredient Service
 * Handles ingredient-related business logic
 * 
 * @package App\Services
 */
class IngredientService extends BaseService {
    private $stockMovementService = null;
    
    /**
     * Constructor
     * @param IngredientRepository $repository Ingredient repository instance
     */
    public function __construct(IngredientRepository $repository) {
        parent::__construct($repository);
    }
    
    /**
     * Get stock movement service (lazy loading)
     * @return \App\Services\StockMovementService
     */
    private function getStockMovementService() {
        if ($this->stockMovementService === null) {
            require_once __DIR__ . '/../core/DependencyFactory.php';
            $this->stockMovementService = \App\Core\DependencyFactory::getStockMovementService();
        }
        return $this->stockMovementService;
    }
    
    /**
     * Supported ingredient item types. Exposed so views / controllers can
     * render consistent dropdowns without hard-coding the list.
     * @return array<string,string>
     */
    public static function getItemTypes(): array {
        return [
            'INGREDIENT'      => 'Yemek Malzemesi',
            'RAW_MATERIAL'    => 'Hammadde',
            'KITCHEN_SUPPLY'  => 'Mutfak Sarfı',
            'CLEANING'        => 'Temizlik Malzemesi',
            'OTHER'           => 'Diğer',
        ];
    }

    /**
     * Get all ingredients
     * @return array All ingredients
     */
    public function getAll(): array {
        return $this->repository->getAll();
    }

    /**
     * Get ingredients filtered by item type (e.g. CLEANING, KITCHEN_SUPPLY).
     * @param string $itemType
     * @return array
     */
    public function getByItemType(string $itemType): array {
        if (!array_key_exists($itemType, self::getItemTypes())) {
            return [];
        }
        if (method_exists($this->repository, 'getByItemType')) {
            return $this->repository->getByItemType($itemType);
        }
        // Graceful fallback for older repos.
        return array_values(array_filter(
            $this->repository->getAll(),
            fn($row) => ($row['item_type'] ?? 'INGREDIENT') === $itemType
        ));
    }

    /**
     * Get ingredient by ID
     * @param string $ingredientId Ingredient ID
     * @return array|null Ingredient data or null
     */
    public function getById(string $ingredientId): ?array {
        return $this->repository->getById($ingredientId);
    }

    /**
     * Find an ingredient by name within the current tenant scope (case-insensitive).
     * Returns null if not found — callers typically create one when null.
     *
     * @param string $name
     * @return array|null
     */
    public function findByName(string $name): ?array {
        if (!method_exists($this->repository, 'findByName')) {
            return null;
        }
        return $this->repository->findByName($name);
    }
    
    /**
     * Create a new ingredient
     * @param array $data Ingredient data
     * @return bool|string Ingredient ID on success, false on failure
     */
    public function createIngredient(array $data) {
        if (empty($data['ingredient_id'])) {
            $data['ingredient_id'] = generateId('ing');
        }

        // Default to classic ingredient classification if caller omitted it.
        if (empty($data['item_type']) || !array_key_exists($data['item_type'], self::getItemTypes())) {
            $data['item_type'] = 'INGREDIENT';
        }

        // Ensure tenant_id is populated so rows are tenant-isolated.
        if (empty($data['tenant_id']) && class_exists('\App\Core\TenantResolver')) {
            $resolved = \App\Core\TenantResolver::resolve();
            if ($resolved) {
                $data['tenant_id'] = $resolved;
            }
        }

        return $this->repository->create($data);
    }
    
    /**
     * Update ingredient
     * @param string $ingredientId Ingredient ID
     * @param array $data Ingredient data to update
     * @return bool Success
     */
    public function updateIngredient(string $ingredientId, array $data): bool {
        return $this->repository->update($ingredientId, $data);
    }
    
    /**
     * Delete ingredient
     * @param string $ingredientId Ingredient ID
     * @return bool Success
     */
    public function deleteIngredient(string $ingredientId): bool {
        return $this->repository->delete($ingredientId);
    }
    
    /**
     * Update ingredient stock (decrease)
     * Uses new stock movement system for tracking
     * @param string $ingredientId Ingredient ID
     * @param float $amount Amount to decrease
     * @return bool Success
     */
    public function updateStock(string $ingredientId, float $amount): bool {
        // Get ingredient to get unit
        $ingredient = $this->repository->getById($ingredientId);
        if (!$ingredient) {
            return false;
        }
        
        $unit = $ingredient['unit'] ?? 'ADET';
        
        // Use stock movement service to record the movement
        $stockService = $this->getStockMovementService();
        $result = $stockService->removeStock(
            'INGREDIENT',
            $ingredientId,
            $amount,
            $unit,
            null,
            null,
            null,
            'Stok çıkışı (eski sistem uyumluluğu)'
        );
        
        // Also update directly for backward compatibility
        if ($result) {
            return $this->repository->updateStock($ingredientId, $amount);
        }
        
        return false;
    }
    
    /**
     * Add ingredient stock (increase)
     * Uses new stock movement system for tracking
     * @param string $ingredientId Ingredient ID
     * @param float $amount Amount to increase
     * @return bool Success
     */
    public function addStock(string $ingredientId, float $amount): bool {
        // Get ingredient to get unit
        $ingredient = $this->repository->getById($ingredientId);
        if (!$ingredient) {
            return false;
        }
        
        $unit = $ingredient['unit'] ?? 'ADET';
        
        // Use stock movement service to record the movement
        $stockService = $this->getStockMovementService();
        $result = $stockService->addStock(
            'INGREDIENT',
            $ingredientId,
            $amount,
            $unit,
            null,
            null,
            null,
            'Stok girişi (eski sistem uyumluluğu)'
        );
        
        // Also update directly for backward compatibility
        if ($result) {
            return $this->repository->addStock($ingredientId, $amount);
        }
        
        return false;
    }
    
    /**
     * Get ingredients with low stock
     * @return array Ingredients below minimum threshold
     */
    public function getLowStock(): array {
        return $this->repository->getLowStock();
    }
    
    /**
     * Get out of stock ingredients
     * @return array Ingredients with zero or negative stock
     */
    public function getOutOfStock(): array {
        return $this->repository->getOutOfStock();
    }
    
    /**
     * Get ingredients below par level
     * @return array Ingredients below par level
     */
    public function getBelowParLevel(): array {
        return $this->repository->getBelowParLevel();
    }
    
    /**
     * Get ingredient usage by date range
     * @param string $startDate Start date (Y-m-d)
     * @param string $endDate End date (Y-m-d)
     * @return array Usage data
     */
    public function getUsageByDateRange(string $startDate, string $endDate): array {
        return $this->repository->getUsageByDateRange($startDate, $endDate);
    }
}

