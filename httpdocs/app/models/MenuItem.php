<?php
namespace App\Models;

require_once __DIR__ . '/../core/Model.php';

class MenuItem extends \App\Core\Model {
    protected $table = 'menu_items';
    
    public function getAll() {
        return $this->query()
            ->select(['mi.*', 'c.name as category_name'])
            ->from('menu_items mi')
            ->leftJoin('categories c', 'mi.category_id', '=', 'c.category_id')
            ->orderBy('c.name')
            ->orderBy('mi.name')
            ->get();
    }
    
    public function getById($menuItemId) {
        return $this->query()
            ->select(['mi.*', 'c.name as category_name'])
            ->from('menu_items mi')
            ->leftJoin('categories c', 'mi.category_id', '=', 'c.category_id')
            ->where('mi.menu_item_id', $menuItemId)
            ->first();
    }
    
    public function getByCategory($categoryId) {
        return $this->query()
            ->select(['mi.*', 'c.name as category_name'])
            ->from('menu_items mi')
            ->leftJoin('categories c', 'mi.category_id', '=', 'c.category_id')
            ->where('mi.category_id', $categoryId)
            ->where('mi.is_available', 1)
            ->orderBy('mi.name')
            ->get();
    }
    
    public function getAvailable() {
        return $this->query()
            ->select(['mi.*', 'c.name as category_name'])
            ->from('menu_items mi')
            ->leftJoin('categories c', 'mi.category_id', '=', 'c.category_id')
            ->where('mi.is_available', 1)
            ->orderBy('c.name')
            ->orderBy('mi.name')
            ->get();
    }
    
    public function create($data) {
        return $this->query()
            ->insert($data);
    }

    public function updateMenuItem($menuItemId, $data) {
        return $this->query()
            ->where('menu_item_id', $menuItemId)
            ->update($data);
    }

    public function deleteMenuItem($menuItemId) {
        return $this->query()
            ->where('menu_item_id', $menuItemId)
            ->delete();
    }
    
    public function updateStock($menuItemId, $quantity) {
        $sql = "UPDATE menu_items SET stock = stock - :quantity WHERE menu_item_id = :menu_item_id AND stock >= :quantity";
        return $this->rawQuery($sql, ['menu_item_id' => $menuItemId, 'quantity' => $quantity]);
    }
    
    public function getIngredients($menuItemId) {
        return $this->query()
            ->select(['mii.*', 'i.name as ingredient_name'])
            ->from('menu_item_ingredients mii')
            ->leftJoin('ingredients i', 'mii.ingredient_id', '=', 'i.ingredient_id')
            ->where('mii.menu_item_id', $menuItemId)
            ->get();
    }
    
    public function getExtras($menuItemId) {
        return $this->query()
            ->from('menu_extras')
            ->where('menu_item_id', $menuItemId)
            ->get();
    }
    
    public function addExtra($menuItemId, $extraData) {
        $extraData['menu_item_id'] = $menuItemId;
        return $this->query()
            ->from('menu_extras')
            ->insert($extraData);
    }
    
    /**
     * Get category (belongsTo relationship)
     * @param string $categoryId
     * @return array|null
     */
    public function getCategory(string $categoryId): ?array {
        require_once __DIR__ . '/Category.php';
        $categoryModel = new \App\Models\Category();
        return $categoryModel->getById($categoryId);
    }
    
    /**
     * Get order items (hasMany relationship)
     * @param string $menuItemId
     * @return array
     */
    public function getOrderItems(string $menuItemId): array {
        require_once __DIR__ . '/OrderItem.php';
        $orderItemModel = new \App\Models\OrderItem();
        return $orderItemModel->getByMenuItem($menuItemId);
    }
}