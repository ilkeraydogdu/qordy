<?php
namespace App\Models;

require_once __DIR__ . '/../core/Model.php';

/**
 * Stock Movement Model
 * Handles stock movement data operations
 */
class StockMovement extends \App\Core\Model {
    protected $table = 'stock_movements';
    
    /**
     * Get all stock movements
     */
    public function getAll() {
        return $this->query()
            ->from('stock_movements')
            ->orderBy('created_at', 'DESC')
            ->get();
    }
    
    /**
     * Get stock movement by ID
     */
    public function getById($movementId) {
        return $this->query()
            ->from('stock_movements')
            ->where('movement_id', $movementId)
            ->first();
    }
    
    /**
     * Get movements by item
     */
    public function getByItem($itemType, $itemId) {
        return $this->query()
            ->from('stock_movements')
            ->where('item_type', $itemType)
            ->where('item_id', $itemId)
            ->orderBy('created_at', 'DESC')
            ->get();
    }
    
    /**
     * Get movements by date range
     */
    public function getByDateRange($startDate, $endDate) {
        return $this->query()
            ->from('stock_movements')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->orderBy('created_at', 'DESC')
            ->get();
    }
    
    /**
     * Get movements by type
     */
    public function getByType($movementType) {
        return $this->query()
            ->from('stock_movements')
            ->where('movement_type', $movementType)
            ->orderBy('created_at', 'DESC')
            ->get();
    }
    
    /**
     * Get movements by reference
     */
    public function getByReference($referenceType, $referenceId) {
        return $this->query()
            ->from('stock_movements')
            ->where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->orderBy('created_at', 'DESC')
            ->get();
    }
    
    /**
     * Create stock movement
     */
    public function create($data) {
        return $this->query()
            ->from('stock_movements')
            ->insert($data);
    }
    
    /**
     * Calculate current stock from movements
     */
    public function calculateCurrentStock($itemType, $itemId) {
        $result = $this->query()
            ->selectRaw('SUM(CASE 
                WHEN movement_type = "IN" THEN quantity
                WHEN movement_type = "OUT" THEN -quantity
                WHEN movement_type = "TRANSFER" AND to_location_id IS NOT NULL THEN quantity
                WHEN movement_type = "TRANSFER" AND from_location_id IS NOT NULL THEN -quantity
                WHEN movement_type = "ADJUSTMENT" THEN quantity
                WHEN movement_type = "WASTE" THEN -quantity
                WHEN movement_type = "RETURN" THEN quantity
                ELSE 0
            END) as total_stock')
            ->from('stock_movements')
            ->where('item_type', $itemType)
            ->where('item_id', $itemId)
            ->first();
        
        return $result ? (float)($result['total_stock'] ?? 0) : 0;
    }
}

