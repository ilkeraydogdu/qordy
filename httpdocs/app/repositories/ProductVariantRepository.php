<?php
namespace App\Repositories;

use App\Core\BaseRepository;

/**
 * Product Variant Repository
 * Handles database operations for product variants
 * 
 * @package App\Repositories
 */
class ProductVariantRepository extends BaseRepository {
    protected $table = 'product_variants';
    protected $primaryKey = 'variant_id';

    /**
     * Constructor
     * @param \PDO $database Database connection
     */
    public function __construct($database) {
        parent::__construct($database);
    }

    /**
     * Get all variants for a product
     * @param string $menuItemId Menu item ID
     * @return array Variants
     */
    public function getVariantsByProduct(string $menuItemId): array {
        $sql = "SELECT * FROM {$this->table} WHERE menu_item_id = :menu_item_id ORDER BY display_order ASC, name ASC";
        return $this->fetchAll($sql, ['menu_item_id' => $menuItemId]);
    }

    /**
     * Get active variants for a product
     * @param string $menuItemId Menu item ID
     * @return array Active variants
     */
    public function getActiveVariantsByProduct(string $menuItemId): array {
        $sql = "SELECT * FROM {$this->table} WHERE menu_item_id = :menu_item_id AND is_active = 1 ORDER BY display_order ASC, name ASC";
        return $this->fetchAll($sql, ['menu_item_id' => $menuItemId]);
    }

    /**
     * Get active variants for multiple products (bulk operation to avoid N+1)
     * @param array $menuItemIds Array of menu item IDs
     * @return array Variants grouped by menu_item_id
     */
    public function getActiveVariantsByProducts(array $menuItemIds): array {
        if (empty($menuItemIds)) {
            return [];
        }
        
        // Create placeholders for IN clause
        $placeholders = rtrim(str_repeat('?,', count($menuItemIds)), ',');
        $sql = "SELECT * FROM {$this->table} 
                WHERE menu_item_id IN ($placeholders) 
                AND is_active = 1 
                ORDER BY menu_item_id, display_order ASC, name ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($menuItemIds);
        $allVariants = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // Group variants by menu_item_id
        $grouped = [];
        foreach ($allVariants as $variant) {
            $menuItemId = $variant['menu_item_id'];
            if (!isset($grouped[$menuItemId])) {
                $grouped[$menuItemId] = [];
            }
            $grouped[$menuItemId][] = $variant;
        }
        
        return $grouped;
    }

    /**
     * Get default variant for a product
     * @param string $menuItemId Menu item ID
     * @return array|null Default variant or null
     */
    public function getDefaultVariant(string $menuItemId): ?array {
        $sql = "SELECT * FROM {$this->table} WHERE menu_item_id = :menu_item_id AND is_default = 1 LIMIT 1";
        $result = $this->fetchOne($sql, ['menu_item_id' => $menuItemId]);
        return $result ?: null;
    }

    /**
     * Remove default flag from all variants of a product
     * @param string $menuItemId Menu item ID
     * @return bool Success
     */
    public function clearDefaultFlags(string $menuItemId): bool {
        $sql = "UPDATE {$this->table} SET is_default = 0 WHERE menu_item_id = :menu_item_id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['menu_item_id' => $menuItemId]);
    }

    /**
     * Set a variant as default
     * @param string $variantId Variant ID
     * @param string $menuItemId Menu item ID
     * @return bool Success
     */
    public function setAsDefault(string $variantId, string $menuItemId): bool {
        // First clear all default flags for this product
        $this->clearDefaultFlags($menuItemId);
        
        // Then set this variant as default
        $sql = "UPDATE {$this->table} SET is_default = 1 WHERE variant_id = :variant_id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['variant_id' => $variantId]);
    }

    /**
     * Delete all variants for a product
     * @param string $menuItemId Menu item ID
     * @return bool Success
     */
    public function deleteByProduct(string $menuItemId): bool {
        $sql = "DELETE FROM {$this->table} WHERE menu_item_id = :menu_item_id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['menu_item_id' => $menuItemId]);
    }
}

