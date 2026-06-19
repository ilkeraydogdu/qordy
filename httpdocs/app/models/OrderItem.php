<?php
namespace App\Models;

require_once __DIR__ . '/../core/Model.php';

class OrderItem extends \App\Core\Model {
    protected $table = 'order_items';
    
    public function getAll() {
        return $this->query()
            ->orderBy('created_at', 'DESC')
            ->get();
    }
    
    public function getById($orderItemId) {
        return $this->query()
            ->where('order_item_id', $orderItemId)
            ->first();
    }
    
    public function getByOrder($orderId) {
        return $this->query()
            ->select(['oi.*', 'mi.name as item_name', 'mi.price as menu_price', 'pv.name as variant_name', 'pv.price_modifier as variant_price_modifier'])
            ->from('order_items oi')
            ->leftJoin('menu_items mi', 'oi.menu_item_id', '=', 'mi.menu_item_id')
            ->leftJoin('product_variants pv', 'oi.variant_id', '=', 'pv.variant_id')
            ->where('oi.order_id', $orderId)
            ->get();
    }
    
    public function getByMenuItem($menuItemId) {
        return $this->query()
            ->where('menu_item_id', $menuItemId)
            ->orderBy('created_at', 'DESC')
            ->get();
    }
    
    public function create($data) {
        return $this->query()
            ->insert($data);
    }

    public function updateOrderItem($orderItemId, $data) {
        return $this->query()
            ->where('order_item_id', $orderItemId)
            ->update($data);
    }

    public function deleteOrderItem($orderItemId) {
        return $this->query()
            ->from('order_items')
            ->where('order_item_id', $orderItemId)
            ->delete();
    }
    
    public function updateQuantity($orderItemId, $quantity) {
        return $this->query()
            ->from('order_items')
            ->where('order_item_id', $orderItemId)
            ->update(['quantity' => $quantity]);
    }
    
    public function markAsPaid($orderItemId) {
        return $this->query()
            ->from('order_items')
            ->where('order_item_id', $orderItemId)
            ->update(['is_paid' => 1]);
    }
    
    public function getExtras($orderItemId) {
        return $this->query()
            ->from('order_item_extras')
            ->where('order_item_id', $orderItemId)
            ->get();
    }
    
    public function addExtra($orderItemId, $extraData) {
        $extraData['order_item_id'] = $orderItemId;
        return $this->query()
            ->from('order_item_extras')
            ->insert($extraData);
    }
    
    public function getIngredients($orderItemId) {
        return $this->query()
            ->from('order_item_ingredients')
            ->where('order_item_id', $orderItemId)
            ->get();
    }
    
    public function addIngredient($orderItemId, $ingredientData) {
        $ingredientData['order_item_id'] = $orderItemId;
        return $this->query()
            ->from('order_item_ingredients')
            ->insert($ingredientData);
    }
    
    public function getTotalByMenuItem($menuItemId, $startDate = null, $endDate = null) {
        $query = $this->query()
            ->from('order_items')
            ->where('menu_item_id', $menuItemId);
        
        if ($startDate && $endDate) {
            $query->whereBetween('created_at', [$startDate, $endDate]);
        }
        
        $totalQuantity = $query->sum('quantity');
        $totalRevenue = $query->sum('price * quantity');
        
        return [
            'quantity' => (float)($totalQuantity ?: 0),
            'revenue' => (float)($totalRevenue ?: 0)
        ];
    }
    
    /**
     * Get order (belongsTo relationship)
     * @param string $orderId
     * @return array|null
     */
    public function getOrder(string $orderId): ?array {
        require_once __DIR__ . '/Order.php';
        $orderModel = new \App\Models\Order();
        return $orderModel->getById($orderId);
    }
    
    /**
     * Get menu item (belongsTo relationship)
     * @param string $menuItemId
     * @return array|null
     */
    public function getMenuItem(string $menuItemId): ?array {
        require_once __DIR__ . '/MenuItem.php';
        $menuItemModel = new \App\Models\MenuItem();
        return $menuItemModel->getById($menuItemId);
    }
}