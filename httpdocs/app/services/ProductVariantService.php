<?php
namespace App\Services;

use App\Core\BaseService;
use App\Repositories\ProductVariantRepository;

/**
 * Product Variant Service
 * Handles business logic for product variants
 */
class ProductVariantService extends BaseService {
    
    public function __construct(ProductVariantRepository $productVariantRepository) {
        parent::__construct($productVariantRepository);
    }
    
    /**
     * Get all variants for a product
     * @param string $menuItemId Menu item ID
     * @return array Variants
     */
    public function getVariantsByProduct(string $menuItemId): array {
        return $this->repository->getVariantsByProduct($menuItemId);
    }
    
    /**
     * Get active variants for a product
     * @param string $menuItemId Menu item ID
     * @return array Active variants
     */
    public function getActiveVariantsByProduct(string $menuItemId): array {
        return $this->repository->getActiveVariantsByProduct($menuItemId);
    }
    
    /**
     * Get active variants for multiple products (bulk - avoids N+1 query problem)
     * @param array $menuItemIds Array of menu item IDs
     * @return array Variants grouped by menu_item_id
     */
    public function getActiveVariantsByProducts(array $menuItemIds): array {
        return $this->repository->getActiveVariantsByProducts($menuItemIds);
    }
    
    /**
     * Get default variant for a product
     * @param string $menuItemId Menu item ID
     * @return array|null Default variant or null
     */
    public function getDefaultVariant(string $menuItemId): ?array {
        return $this->repository->getDefaultVariant($menuItemId);
    }
    
    /**
     * Create a variant
     * @param array $data Variant data
     * @return string|false Variant ID on success, false on failure
     */
    public function createVariant(array $data): string|false {
        if (empty($data['variant_id'])) {
            $data['variant_id'] = 'VAR_' . strtoupper(uniqid());
        }
        
        // If this is set as default, clear other defaults first
        if (!empty($data['is_default']) && $data['is_default'] == 1 && !empty($data['menu_item_id'])) {
            $this->repository->clearDefaultFlags($data['menu_item_id']);
        }
        
        $result = $this->repository->create($data);
        return $result ? $data['variant_id'] : false;
    }
    
    /**
     * Update a variant
     * @param string $variantId Variant ID
     * @param array $data Variant data
     * @return bool Success
     */
    public function updateVariant(string $variantId, array $data): bool {
        // If setting as default, get menu_item_id first
        if (!empty($data['is_default']) && $data['is_default'] == 1) {
            $variant = $this->repository->findById($variantId);
            if ($variant && !empty($variant['menu_item_id'])) {
                $this->repository->clearDefaultFlags($variant['menu_item_id']);
            }
        }
        
        return $this->repository->update($variantId, $data);
    }
    
    /**
     * Delete a variant
     * @param string $variantId Variant ID
     * @return bool Success
     */
    public function deleteVariant(string $variantId): bool {
        return $this->repository->delete($variantId);
    }
    
    /**
     * Delete all variants for a product
     * @param string $menuItemId Menu item ID
     * @return bool Success
     */
    public function deleteVariantsByProduct(string $menuItemId): bool {
        return $this->repository->deleteByProduct($menuItemId);
    }
    
    /**
     * Set a variant as default
     * @param string $variantId Variant ID
     * @param string $menuItemId Menu item ID
     * @return bool Success
     */
    public function setAsDefault(string $variantId, string $menuItemId): bool {
        return $this->repository->setAsDefault($variantId, $menuItemId);
    }
    
    /**
     * Create multiple variants for a product
     * @param string $menuItemId Menu item ID
     * @param array $variants Array of variant data
     * @return array Created variant IDs
     */
    public function createVariants(string $menuItemId, array $variants): array {
        $createdIds = [];
        $hasDefault = false;
        
        foreach ($variants as $variant) {
            $variant['menu_item_id'] = $menuItemId;
            
            // Ensure only one default variant
            if (!empty($variant['is_default']) && $variant['is_default'] == 1) {
                if ($hasDefault) {
                    $variant['is_default'] = 0; // Don't allow multiple defaults
                } else {
                    $hasDefault = true;
                }
            }
            
            $variantId = $this->createVariant($variant);
            if ($variantId) {
                $createdIds[] = $variantId;
            }
        }
        
        return $createdIds;
    }
}

