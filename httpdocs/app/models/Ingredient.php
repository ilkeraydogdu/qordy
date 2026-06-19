<?php
namespace App\Models;

require_once __DIR__ . '/../core/Model.php';

class Ingredient extends \App\Core\Model {
    protected $table = 'ingredients';
    
    public function getAll() {
        return $this->query()
            ->from('ingredients')
            ->orderBy('name')
            ->get();
    }
    
    public function getById($ingredientId) {
        return $this->query()
            ->from('ingredients')
            ->where('ingredient_id', $ingredientId)
            ->first();
    }
    
    public function create($data) {
        return $this->query()
            ->from('ingredients')
            ->insert($data);
    }

    public function updateIngredient($ingredientId, $data) {
        return $this->query()
            ->from('ingredients')
            ->where('ingredient_id', $ingredientId)
            ->update($data);
    }

    public function deleteIngredient($ingredientId) {
        return $this->query()
            ->from('ingredients')
            ->where('ingredient_id', $ingredientId)
            ->delete();
    }
    
    public function updateStock($ingredientId, $amount) {
        $sql = "UPDATE ingredients SET current_stock = current_stock - :amount WHERE ingredient_id = :ingredient_id";
        return $this->rawQuery($sql, ['ingredient_id' => $ingredientId, 'amount' => $amount]);
    }
    
    public function addStock($ingredientId, $amount) {
        $sql = "UPDATE ingredients SET current_stock = current_stock + :amount WHERE ingredient_id = :ingredient_id";
        return $this->rawQuery($sql, ['ingredient_id' => $ingredientId, 'amount' => $amount]);
    }
    
    public function getLowStock() {
        return $this->query()
            ->from('ingredients')
            ->whereRaw('current_stock <= min_threshold')
            ->orderBy('current_stock')
            ->get();
    }
    
    public function getOutOfStock() {
        return $this->query()
            ->from('ingredients')
            ->where('current_stock', '<=', 0)
            ->orderBy('name')
            ->get();
    }
    
    public function getBelowParLevel() {
        return $this->query()
            ->from('ingredients')
            ->whereNotNull('par_level')
            ->whereRaw('current_stock < par_level')
            ->orderBy('current_stock')
            ->get();
    }
    
    public function getUsageByDateRange($startDate, $endDate) {
        return $this->query()
            ->select(['i.name', 'i.current_stock', 'i.unit', 'SUM(mii.amount * oi.quantity) as used_amount'])
            ->from('ingredients i')
            ->leftJoin('menu_item_ingredients mii', 'i.ingredient_id', '=', 'mii.ingredient_id')
            ->leftJoin('order_items oi', 'mii.menu_item_id', '=', 'oi.menu_item_id')
            ->leftJoin('orders o', 'oi.order_id', '=', 'o.order_id')
            ->whereBetween('o.created_at', [$startDate, $endDate])
            ->groupBy('i.ingredient_id')
            ->orderBy('i.name')
            ->get();
    }
}