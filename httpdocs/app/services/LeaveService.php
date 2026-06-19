<?php
namespace App\Services;

use App\Core\BaseService;
use App\Repositories\LeaveRepository;

/**
 * Leave Service
 * Handles leave-related business logic
 * 
 * @package App\Services
 */
class LeaveService extends BaseService {
    /**
     * Constructor
     * @param LeaveRepository $leaveRepository Leave repository instance
     */
    public function __construct(LeaveRepository $leaveRepository) {
        parent::__construct($leaveRepository);
    }

    /**
     * Create a new leave
     * @param array $data Leave data
     * @return bool|string Leave ID on success, false on failure
     */
    public function create(array $data) {
        // Calculate total days if not provided
        if (!isset($data['total_days']) && isset($data['start_date']) && isset($data['end_date'])) {
            $data['total_days'] = $this->calculateDays($data['start_date'], $data['end_date']);
        }
        
        return parent::create($data);
    }

    /**
     * Get leaves by user ID
     * @param string $userId User ID
     * @return array Leaves
     */
    public function getByUserId(string $userId): array {
        return $this->repository->getByUserId($userId) ?: [];
    }

    /**
     * Return every leave row the repository can see (BaseRepository
     * tenant filter still applies to the underlying query).
     * @return array<int, array<string, mixed>>
     */
    public function getAll(): array {
        return method_exists($this->repository, 'getAll')
            ? ($this->repository->getAll() ?: [])
            : [];
    }

    /**
     * Fetch a single leave row by primary key.
     * @return array<string, mixed>|null
     */
    public function getById(string $leaveId): ?array {
        if (method_exists($this->repository, 'findById')) {
            return $this->repository->findById($leaveId) ?: null;
        }
        return null;
    }

    /**
     * Get leaves by date range
     * @param string $startDate Start date
     * @param string $endDate End date
     * @return array Leaves
     */
    public function getByDateRange(string $startDate, string $endDate): array {
        return $this->repository->getByDateRange($startDate, $endDate) ?: [];
    }

    /**
     * Get leaves by status
     * @param string $status Status
     * @return array Leaves
     */
    public function getByStatus(string $status): array {
        return $this->repository->getByStatus($status) ?: [];
    }

    /**
     * Approve leave
     * @param string $leaveId Leave ID
     * @param string $approvedBy User ID who approved
     * @return bool Success
     */
    public function approveLeave(string $leaveId, string $approvedBy): bool {
        return $this->repository->update($leaveId, [
            'status' => 'APPROVED',
            'approved_by' => $approvedBy,
            'approved_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Reject leave
     * @param string $leaveId Leave ID
     * @param string $approvedBy User ID who rejected
     * @return bool Success
     */
    public function rejectLeave(string $leaveId, string $approvedBy): bool {
        return $this->repository->update($leaveId, [
            'status' => 'REJECTED',
            'approved_by' => $approvedBy,
            'approved_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Calculate total leave days for a user in a year
     * @param string $userId User ID
     * @param string|null $year Year (YYYY), defaults to current year
     * @param string|null $leaveTypeId Optional leave type ID
     * @return int Total days
     */
    public function getTotalDaysByUser(string $userId, ?string $year = null, ?string $leaveTypeId = null): int {
        if (!$year) {
            $year = date('Y');
        }
        return $this->repository->getTotalDaysByUser($userId, $year, $leaveTypeId);
    }

    /**
     * Calculate total days between start and end date (inclusive)
     * @param string $startDate Start date (Y-m-d)
     * @param string $endDate End date (Y-m-d)
     * @return int Total days
     */
    public function calculateDays(string $startDate, string $endDate): int {
        $start = new \DateTime($startDate);
        $end = new \DateTime($endDate);
        $end->modify('+1 day'); // Include end date
        $interval = $start->diff($end);
        return $interval->days;
    }
}

