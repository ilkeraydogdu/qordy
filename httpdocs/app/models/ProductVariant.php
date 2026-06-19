<?php
namespace App\Models;

require_once __DIR__ . '/../core/Model.php';

class ProductVariant extends \App\Core\Model {
    protected $table = 'product_variants';
    
    public function getByProduct($menuItemId) {
        return $this->query()
            ->from($this->table)
            ->where('menu_item_id', $menuItemId)
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();
    }
    
    public function getById($variantId) {
        return $this->query()
            ->from($this->table)
            ->where('variant_id', $variantId)
            ->first();
    }
    
    public function getDefaultVariant($menuItemId) {
        return $this->query()
            ->from($this->table)
            ->where('menu_item_id', $menuItemId)
            ->where('is_default', 1)
            ->first();
    }
    
    public function create($data) {
        return $this->query()
            ->from($this->table)
            ->insert($data);
    }
    
    public function update($variantId, $data) {
        return $this->query()
            ->from($this->table)
            ->where('variant_id', $variantId)
            ->update($data);
    }
    
    public function delete($variantId) {
        return $this->query()
            ->from($this->table)
            ->where('variant_id', $variantId)
            ->delete();
    }
    
    public function deleteByProduct($menuItemId) {
        return $this->query()
            ->from($this->table)
            ->where('menu_item_id', $menuItemId)
            ->delete();
    }
}

