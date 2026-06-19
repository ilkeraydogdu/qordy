<?php
namespace App\Services;

use App\Core\BaseService;
use App\Repositories\MedicalReportRepository;

/**
 * Medical Report Service
 * Handles medical report-related business logic
 * 
 * @package App\Services
 */
class MedicalReportService extends BaseService {
    /**
     * Constructor
     * @param MedicalReportRepository $medicalReportRepository Medical report repository instance
     */
    public function __construct(MedicalReportRepository $medicalReportRepository) {
        parent::__construct($medicalReportRepository);
    }

    /**
     * Create a new medical report
     * @param array $data Medical report data
     * @return bool|string Report ID on success, false on failure
     */
    public function create(array $data) {
        // Calculate total days if not provided
        if (!isset($data['total_days']) && isset($data['start_date']) && isset($data['end_date'])) {
            $data['total_days'] = $this->calculateDays($data['start_date'], $data['end_date']);
        }
        
        return parent::create($data);
    }

    /**
     * Get medical reports by user ID
     * @param string $userId User ID
     * @return array Medical reports
     */
    public function getByUserId(string $userId): array {
        return $this->repository->getByUserId($userId) ?: [];
    }

    /**
     * Get medical reports by date range
     * @param string $startDate Start date
     * @param string $endDate End date
     * @return array Medical reports
     */
    public function getByDateRange(string $startDate, string $endDate): array {
        return $this->repository->getByDateRange($startDate, $endDate) ?: [];
    }

    /**
     * Calculate total report days for a user in a year
     * @param string $userId User ID
     * @param string|null $year Year (YYYY), defaults to current year
     * @return int Total days
     */
    public function getTotalDaysByUser(string $userId, ?string $year = null): int {
        if (!$year) {
            $year = date('Y');
        }
        return $this->repository->getTotalDaysByUser($userId, $year);
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

