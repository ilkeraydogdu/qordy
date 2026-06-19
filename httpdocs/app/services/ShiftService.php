<?php
namespace App\Services;

use App\Core\BaseService;
use App\Repositories\ShiftRepository;

class ShiftService extends BaseService {
    
    public function __construct(ShiftRepository $shiftRepository) {
        parent::__construct($shiftRepository);
    }
    
    /**
     * Get all shifts
     * @return array
     */
    public function getAllShifts(): array {
        return $this->repository->getAll();
    }
    
    /**
     * Get all shifts (alias for compatibility)
     * @return array
     */
    public function getAll(): array {
        return $this->getAllShifts();
    }
    
    /**
     * Get shifts by staff member
     * @param string $staffId
     * @return array
     */
    public function getShiftsByStaff(string $staffId): array {
        return $this->repository->getByStaff($staffId);
    }
    
    /**
     * Get shifts by date range
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    public function getShiftsByDateRange(string $startDate, string $endDate): array {
        return $this->repository->getByDateRange($startDate, $endDate);
    }
    
    /**
     * Get current shift for staff member
     * @param string $staffId
     * @return array|null
     */
    public function getCurrentShift(string $staffId): ?array {
        return $this->repository->getCurrentShift($staffId);
    }
    
    /**
     * Get open shifts
     * @return array
     */
    public function getOpenShifts(): array {
        return $this->repository->getOpenShifts();
    }
    
    /**
     * Create shift
     * @param array $shiftData
     * @return bool|string Shift ID on success, false on failure
     */
    public function createShift(array $shiftData) {
        if (empty($shiftData['shift_id'])) {
            $shiftData['shift_id'] = generateId('s');
        }
        
        if (empty($shiftData['staff_id'])) {
            return false;
        }
        
        $defaults = [
            'status' => 'OPEN',
            'start_time' => date('Y-m-d H:i:s')
        ];
        
        $shiftData = array_merge($defaults, $shiftData);
        
        $result = $this->repository->create($shiftData);
        
        if ($result) {
            return $shiftData['shift_id'];
        }
        
        return false;
    }
    
    /**
     * Update shift
     * @param string $shiftId
     * @param array $shiftData
     * @return bool
     */
    public function updateShift(string $shiftId, array $shiftData): bool {
        return $this->repository->update($shiftId, $shiftData);
    }
    
    /**
     * Close shift
     * @param string $shiftId
     * @param float $endCash
     * @param float $totalSales
     * @return bool
     */
    public function closeShift(string $shiftId, float $endCash, float $totalSales): bool {
        return $this->repository->closeShift($shiftId, $endCash, $totalSales);
    }
    
    /**
     * Delete shift
     * @param string $shiftId
     * @return bool
     */
    public function deleteShift(string $shiftId): bool {
        return $this->repository->delete($shiftId);
    }
    
    /**
     * Get shift by ID
     * @param string $shiftId
     * @return array|null
     */
    public function getShiftById(string $shiftId): ?array {
        return $this->repository->findById($shiftId);
    }
}

