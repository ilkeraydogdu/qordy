<?php
namespace App\Services;

use App\Core\BaseService;
use App\Repositories\MenuItemRepository;

class MenuService extends BaseService {
    public function __construct(MenuItemRepository $menuItemRepository) {
        parent::__construct($menuItemRepository);
    }

    public function getAllCategories() {
        $sql = "SELECT * FROM categories ORDER BY name";
        return $this->db->fetchAll($sql);
    }

    public function getAvailableMenuItems() {
        $sql = "SELECT mi.*, c.name as category_name 
                FROM menu_items mi 
                LEFT JOIN categories c ON mi.category_id = c.category_id 
                WHERE mi.is_available = 1
                ORDER BY c.name, mi.name";
        return $this->db->fetchAll($sql);
    }

    public function getMenuByCategory($categoryId) {
        $sql = "SELECT mi.*, c.name as category_name 
                FROM menu_items mi 
                LEFT JOIN categories c ON mi.category_id = c.category_id 
                WHERE mi.category_id = :category_id AND mi.is_available = 1
                ORDER BY mi.name";
        return $this->db->fetchAll($sql, ['category_id' => $categoryId]);
    }

    public function updateStock($menuItemId, $quantity) {
        $sql = "UPDATE menu_items SET stock = stock - :quantity WHERE menu_item_id = :menu_item_id";
        return $this->db->execute($sql, [
            'menu_item_id' => $menuItemId,
            'quantity' => $quantity
        ]);
    }

    public function getTopSellingItems($limit = 10) {
        $sql = "SELECT 
                    mi.name,
                    COUNT(oi.order_item_id) as count
                FROM order_items oi
                JOIN menu_items mi ON oi.menu_item_id = mi.menu_item_id
                GROUP BY mi.menu_item_id
                ORDER BY count DESC
                LIMIT :limit";
        return $this->db->fetchAll($sql, ['limit' => $limit]);
    }
}