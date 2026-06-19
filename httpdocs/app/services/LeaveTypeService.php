<?php
namespace App\Services;

use App\Core\BaseService;
use App\Repositories\LeaveTypeRepository;

/**
 * Leave Type Service
 * Handles leave type-related business logic
 * 
 * @package App\Services
 */
class LeaveTypeService extends BaseService {
    /**
     * Constructor
     * @param LeaveTypeRepository $leaveTypeRepository Leave type repository instance
     */
    public function __construct(LeaveTypeRepository $leaveTypeRepository) {
        parent::__construct($leaveTypeRepository);
    }

    /**
     * Find leave type by code
     * @param string $typeCode Type code
     * @return array|null Leave type data or null
     */
    public function findByCode(string $typeCode): ?array {
        return $this->repository->findByCode($typeCode);
    }

    /**
     * Get active leave types
     * @return array Active leave types
     */
    public function getActive(): array {
        return $this->repository->getActive() ?: [];
    }

    /**
     * Get all leave types
     * @return array All leave types
     */
    public function getAll(): array {
        return $this->repository->getAll() ?: [];
    }
}

