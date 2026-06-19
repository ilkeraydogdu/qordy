<?php
namespace App\Core;

use App\Interfaces\ServiceInterface;

/**
 * Base Service
 * Abstract base class for all services
 * 
 * @package App\Core
 */
abstract class BaseService implements ServiceInterface {
    /**
     * @var \App\Core\BaseRepository Repository instance
     */
    protected $repository;
    
    /**
     * @var \PDO Database connection
     */
    protected $db;

    /**
     * Constructor
     * @param \App\Core\BaseRepository|null $repository Repository instance (can be null for services that don't need repositories)
     */
    public function __construct($repository = null) {
        $this->repository = $repository;
        if ($repository !== null) {
            $this->db = $repository->getDbConnection();
        } else {
            // For services that don't need repositories, get database connection directly
            $this->db = \App\Core\DependencyFactory::getDatabase();
        }
    }

    /**
     * Create a new record
     * @param array $data Record data
     * @return bool|string Record ID on success, false on failure
     */
    public function create(array $data) {
        return $this->repository->create($data);
    }

    /**
     * Update a record
     * @param string $id Record ID
     * @param array $data Record data to update
     * @return bool Success
     */
    public function update(string $id, array $data): bool {
        return $this->repository->update($id, $data);
    }

    /**
     * Delete a record
     * @param string $id Record ID
     * @return bool Success
     */
    public function delete(string $id): bool {
        return $this->repository->delete($id);
    }

    /**
     * Find record by ID
     * @param string $id Record ID
     * @return array|null Record data or null
     */
    public function findById(string $id): ?array {
        return $this->repository->findById($id);
    }

    /**
     * Find all records
     * @param array $criteria Search criteria
     * @return array Records
     */
    public function findAll(array $criteria = []): array {
        return $this->repository->findAll($criteria);
    }

    /**
     * Validate data
     * @param array $data Data to validate
     * @return bool True if valid
     */
    public function validate(array $data): bool {
        // Basic validation - override in child classes for specific validation
        return !empty($data);
    }

    /**
     * Get repository instance
     * @return \App\Core\BaseRepository|null Repository instance
     */
    public function getRepository() {
        return $this->repository;
    }
}