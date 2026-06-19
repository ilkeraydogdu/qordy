<?php
namespace App\Models;

require_once __DIR__ . '/../core/Model.php';

/**
 * Stock Location Model
 * Handles stock location/warehouse data operations
 */
class StockLocation extends \App\Core\Model {
    protected $table = 'stock_locations';
    
    /**
     * Get all stock locations
     */
    public function getAll() {
        return $this->query()
            ->from('stock_locations')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }
    
    /**
     * Get all locations including inactive
     */
    public function getAllIncludingInactive() {
        return $this->query()
            ->from('stock_locations')
            ->orderBy('name')
            ->get();
    }
    
    /**
     * Get location by ID
     */
    public function getById($locationId) {
        return $this->query()
            ->from('stock_locations')
            ->where('location_id', $locationId)
            ->first();
    }
    
    /**
     * Get location by code
     */
    public function getByCode($code) {
        return $this->query()
            ->from('stock_locations')
            ->where('code', $code)
            ->first();
    }
    
    /**
     * Get active locations
     */
    public function getActive() {
        return $this->query()
            ->from('stock_locations')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }
    
    /**
     * Create location
     */
    public function create($data) {
        return $this->query()
            ->from('stock_locations')
            ->insert($data);
    }
    
    /**
     * Update location
     */
    public function updateLocation($locationId, $data) {
        return $this->query()
            ->from('stock_locations')
            ->where('location_id', $locationId)
            ->update($data);
    }
    
    /**
     * Delete location (soft delete by setting is_active = false)
     */
    public function deleteLocation($locationId) {
        return $this->query()
            ->from('stock_locations')
            ->where('location_id', $locationId)
            ->update(['is_active' => false]);
    }
}

