<?php
namespace App\Services;

use App\Core\BaseService;
use App\Repositories\IntegrationPlatformRepository;

/**
 * Integration Platform Service
 * Handles integration platform-related business logic
 * 
 * @package App\Services
 */
class IntegrationPlatformService extends BaseService {
    
    /**
     * Constructor
     * @param IntegrationPlatformRepository $repository Integration platform repository instance
     */
    public function __construct(IntegrationPlatformRepository $repository) {
        parent::__construct($repository);
    }
    
    /**
     * Get all integration platforms
     * @return array All integration platforms
     */
    public function getAll(): array {
        return $this->repository->getAll();
    }
    
    /**
     * Get integration platform by ID
     * @param string $platformId Platform ID
     * @return array|null Platform data or null
     */
    public function getById(string $platformId): ?array {
        return $this->repository->getById($platformId);
    }
    
    /**
     * Create a new integration platform
     * @param array $data Platform data
     * @return bool|string Platform ID on success, false on failure
     */
    public function createPlatform(array $data) {
        if (empty($data['platform_id'])) {
            $data['platform_id'] = generateId('plat');
        }
        return $this->repository->create($data);
    }
    
    /**
     * Update integration platform
     * @param string $platformId Platform ID
     * @param array $data Platform data to update
     * @return bool Success
     */
    public function updatePlatform(string $platformId, array $data): bool {
        return $this->repository->update($platformId, $data);
    }
    
    /**
     * Delete integration platform
     * @param string $platformId Platform ID
     * @return bool Success
     */
    public function deletePlatform(string $platformId): bool {
        return $this->repository->delete($platformId);
    }
    
    /**
     * Get active integration platforms
     * @return array Active platforms
     */
    public function getActivePlatforms(): array {
        return $this->repository->getActivePlatforms();
    }
    
    /**
     * Get inactive integration platforms
     * @return array Inactive platforms
     */
    public function getInactivePlatforms(): array {
        return $this->repository->getInactivePlatforms();
    }
    
    /**
     * Activate integration platform
     * @param string $platformId Platform ID
     * @return bool Success
     */
    public function activate(string $platformId): bool {
        return $this->repository->activate($platformId);
    }
    
    /**
     * Deactivate integration platform
     * @param string $platformId Platform ID
     * @return bool Success
     */
    public function deactivate(string $platformId): bool {
        return $this->repository->deactivate($platformId);
    }
    
    /**
     * Update platform sync information
     * @param string $platformId Platform ID
     * @param int $orderCount Number of orders synced
     * @return bool Success
     */
    public function updateSyncInfo(string $platformId, int $orderCount): bool {
        return $this->repository->updateSyncInfo($platformId, $orderCount);
    }
    
    /**
     * Get integration platform by name
     * @param string $name Platform name
     * @return array|null Platform data or null
     */
    public function getByName(string $name): ?array {
        return $this->repository->getByName($name);
    }
}
