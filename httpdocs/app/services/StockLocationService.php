<?php
namespace App\Services;

use App\Core\BaseService;
use App\Repositories\StockLocationRepository;

/**
 * Stock Location Service
 * Handles stock location business logic
 * 
 * @package App\Services
 */
class StockLocationService extends BaseService {
    
    /**
     * Constructor
     * @param StockLocationRepository $repository Stock location repository
     */
    public function __construct(StockLocationRepository $repository) {
        parent::__construct($repository);
    }
    
    /**
     * Get all active locations
     * @return array Locations
     */
    public function getAll(): array {
        return $this->repository->getAll();
    }
    
    /**
     * Get all locations including inactive
     * @return array Locations
     */
    public function getAllIncludingInactive(): array {
        return $this->repository->getAllIncludingInactive();
    }
    
    /**
     * Get location by ID
     * @param string $locationId Location ID
     * @return array|null Location data or null
     */
    public function getById(string $locationId): ?array {
        return $this->repository->findById($locationId);
    }
    
    /**
     * Get location by code
     * @param string $code Location code
     * @return array|null Location data or null
     */
    public function getByCode(string $code): ?array {
        return $this->repository->getByCode($code);
    }
    
    /**
     * Create a new location
     * @param array $data Location data
     * @return bool|string Location ID on success, false on failure
     */
    public function createLocation(array $data) {
        if (empty($data['location_id'])) {
            $data['location_id'] = generateId('loc');
        }
        
        // Ensure code is uppercase
        if (!empty($data['code'])) {
            $data['code'] = strtoupper($data['code']);
        }
        
        return $this->repository->create($data);
    }
    
    /**
     * Update location
     * @param string $locationId Location ID
     * @param array $data Location data to update
     * @return bool Success
     */
    public function updateLocation(string $locationId, array $data): bool {
        // Ensure code is uppercase if provided
        if (!empty($data['code'])) {
            $data['code'] = strtoupper($data['code']);
        }
        
        return $this->repository->update($locationId, $data);
    }
    
    /**
     * Delete location (soft delete)
     * @param string $locationId Location ID
     * @return bool Success
     */
    public function deleteLocation(string $locationId): bool {
        return $this->repository->update($locationId, ['is_active' => false]);
    }
    
    /**
     * Get active locations
     * @return array Active locations
     */
    public function getActive(): array {
        return $this->repository->getActive();
    }
}

