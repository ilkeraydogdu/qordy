<?php
namespace App\Services;

use App\Repositories\UserRepository;
use App\Repositories\LeaveRepository;
use App\Repositories\MedicalReportRepository;
use App\Models\Shift;

/**
 * Personnel Service
 * Handles personnel-related business logic and aggregates data
 * 
 * @package App\Services
 */
class PersonnelService {
    protected $userRepository;
    protected $leaveRepository;
    protected $medicalReportRepository;
    protected $shiftModel;
    
    public function __construct(
        UserRepository $userRepository,
        LeaveRepository $leaveRepository,
        MedicalReportRepository $medicalReportRepository
    ) {
        $this->userRepository = $userRepository;
        $this->leaveRepository = $leaveRepository;
        $this->medicalReportRepository = $medicalReportRepository;
        $this->shiftModel = new Shift();
    }

    /**
     * Get personnel detail data
     * @param string $userId User ID
     * @return array Personnel detail data
     */
    public function getPersonnelDetail(string $userId): array {
        // Trim and validate userId
        $userId = trim($userId);
        if (empty($userId)) {
            error_log("PersonnelService::getPersonnelDetail - Empty userId provided");
            return $this->getDefaultPersonnelData();
        }
        
        // Log for debugging
        error_log("PersonnelService::getPersonnelDetail called with userId: " . $userId);
        
        $user = null;
        try {
            $user = $this->userRepository->findByUserId($userId);
        } catch (\Exception $e) {
            error_log("PersonnelService::getPersonnelDetail - Error finding user: " . $e->getMessage());
        }
        
        // Log for debugging
        error_log("PersonnelService::getPersonnelDetail - user found: " . ($user ? 'yes' : 'no'));
        
        if (!$user) {
            error_log("PersonnelService::getPersonnelDetail - user not found for userId: " . $userId);
            return $this->getDefaultPersonnelData();
        }
        
        // Get shifts (with error handling)
        $shifts = [];
        try {
            $shifts = $this->shiftModel->getByStaff($userId) ?: [];
            error_log("PersonnelService::getPersonnelDetail - shifts count: " . count($shifts));
        } catch (\Exception $e) {
            error_log("PersonnelService::getPersonnelDetail - Error getting shifts: " . $e->getMessage());
            $shifts = [];
        }
        
        // Get leaves (with error handling)
        $leaves = [];
        try {
            $leaves = $this->leaveRepository->getByUserId($userId) ?: [];
            error_log("PersonnelService::getPersonnelDetail - leaves count: " . count($leaves));
        } catch (\Exception $e) {
            error_log("PersonnelService::getPersonnelDetail - Error getting leaves: " . $e->getMessage());
            $leaves = [];
        }
        
        // Get medical reports (with error handling)
        $medicalReports = [];
        try {
            $medicalReports = $this->medicalReportRepository->getByUserId($userId) ?: [];
            error_log("PersonnelService::getPersonnelDetail - medical reports count: " . count($medicalReports));
        } catch (\Exception $e) {
            error_log("PersonnelService::getPersonnelDetail - Error getting medical reports: " . $e->getMessage());
            $medicalReports = [];
        }
        
        // Calculate statistics (with error handling)
        $currentYear = date('Y');
        $stats = $this->getDefaultStatistics($currentYear);
        try {
            $calculatedStats = $this->calculateStatistics($userId, $currentYear);
            if (!empty($calculatedStats)) {
                $stats = array_merge($stats, $calculatedStats);
            }
        } catch (\Exception $e) {
            error_log("PersonnelService::getPersonnelDetail - Error calculating statistics: " . $e->getMessage());
            // Use default statistics already set
        }
        
        return [
            'user' => $user,
            'shifts' => $shifts,
            'leaves' => $leaves,
            'medical_reports' => $medicalReports,
            'statistics' => $stats
        ];
    }
    
    /**
     * Get default personnel data structure
     * @return array Default personnel data
     */
    private function getDefaultPersonnelData(): array {
        $currentYear = date('Y');
        return [
            'user' => null,
            'shifts' => [],
            'leaves' => [],
            'medical_reports' => [],
            'statistics' => $this->getDefaultStatistics($currentYear)
        ];
    }
    
    /**
     * Get default statistics structure
     * @param string $year Year
     * @return array Default statistics
     */
    private function getDefaultStatistics(string $year): array {
        return [
            'year' => $year,
            'worked_days' => 0,
            'total_work_hours' => 0,
            'total_leave_days' => 0,
            'annual_leave_days' => 0,
            'remaining_annual_leave' => 14, // Default max annual leave
            'medical_report_days' => 0,
            'total_absence_days' => 0
        ];
    }

    /**
     * Calculate personnel statistics
     * @param string $userId User ID
     * @param string $year Year (YYYY)
     * @return array Statistics
     */
    public function calculateStatistics(string $userId, string $year): array {
        // Validate inputs
        $userId = trim($userId);
        $year = trim($year);
        
        if (empty($userId) || empty($year)) {
            error_log("PersonnelService::calculateStatistics - Empty userId or year provided");
            return $this->getDefaultStatistics($year ?: date('Y'));
        }
        
        // Initialize default statistics
        $stats = $this->getDefaultStatistics($year);
        
        // Total worked days (from shifts) - with error handling
        $shifts = [];
        try {
            $shifts = $this->shiftModel->getByStaff($userId) ?: [];
        } catch (\Exception $e) {
            error_log("PersonnelService::calculateStatistics - Error getting shifts: " . $e->getMessage());
            $shifts = [];
        }
        
        $workedDays = 0;
        $totalWorkHours = 0;
        
        foreach ($shifts as $shift) {
            if (isset($shift['start_time']) && isset($shift['end_time'])) {
                try {
                    $start = new \DateTime($shift['start_time']);
                    $end = new \DateTime($shift['end_time']);
                    
                    // Check if shift is in the specified year
                    if ($start->format('Y') === $year || $end->format('Y') === $year) {
                        $workedDays++;
                        
                        // Calculate hours
                        $diff = $start->diff($end);
                        $hours = $diff->h + ($diff->days * 24) + ($diff->i / 60);
                        $totalWorkHours += $hours;
                    }
                } catch (\Exception $e) {
                    error_log("PersonnelService::calculateStatistics - Error processing shift: " . $e->getMessage());
                    continue;
                }
            }
        }
        
        $stats['worked_days'] = $workedDays;
        $stats['total_work_hours'] = round($totalWorkHours, 2);
        
        // Total leave days used - with error handling
        $totalLeaveDays = 0;
        try {
            $totalLeaveDays = $this->leaveRepository->getTotalDaysByUser($userId, $year);
        } catch (\Exception $e) {
            error_log("PersonnelService::calculateStatistics - Error getting leave days: " . $e->getMessage());
            $totalLeaveDays = 0;
        }
        
        $stats['total_leave_days'] = $totalLeaveDays;
        
        // Annual leave days used - with error handling
        $annualLeaveType = null;
        try {
            $annualLeaveType = $this->getAnnualLeaveType();
        } catch (\Exception $e) {
            error_log("PersonnelService::calculateStatistics - Error getting annual leave type: " . $e->getMessage());
            $annualLeaveType = null;
        }
        
        $annualLeaveDays = 0;
        if ($annualLeaveType) {
            try {
                $annualLeaveDays = $this->leaveRepository->getTotalDaysByUser($userId, $year, $annualLeaveType['leave_type_id']);
            } catch (\Exception $e) {
                error_log("PersonnelService::calculateStatistics - Error getting annual leave days: " . $e->getMessage());
                $annualLeaveDays = 0;
            }
        }
        
        $stats['annual_leave_days'] = $annualLeaveDays;
        
        // Remaining annual leave (assuming max 14 days)
        $maxAnnualLeave = $annualLeaveType['max_days_per_year'] ?? 14;
        $stats['remaining_annual_leave'] = max(0, $maxAnnualLeave - $annualLeaveDays);
        
        // Total medical report days - with error handling
        $totalMedicalReportDays = 0;
        try {
            $totalMedicalReportDays = $this->medicalReportRepository->getTotalDaysByUser($userId, $year);
        } catch (\Exception $e) {
            error_log("PersonnelService::calculateStatistics - Error getting medical report days: " . $e->getMessage());
            $totalMedicalReportDays = 0;
        }
        
        $stats['medical_report_days'] = $totalMedicalReportDays;
        $stats['total_absence_days'] = $totalLeaveDays + $totalMedicalReportDays;
        
        return $stats;
    }

    /**
     * Get annual leave type
     * @return array|null Annual leave type or null
     */
    private function getAnnualLeaveType(): ?array {
        try {
            require_once __DIR__ . '/../core/DependencyFactory.php';
            $db = \App\Core\DependencyFactory::getDatabase();
            $sql = "SELECT * FROM leave_types WHERE type_code = 'ANNUAL' LIMIT 1";
            $stmt = $db->prepare($sql);
            $stmt->execute();
            return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
        } catch (\PDOException $e) {
            // Table doesn't exist or other database error
            error_log("PersonnelService::getAnnualLeaveType - Error: " . $e->getMessage());
            return null;
        } catch (\Exception $e) {
            error_log("PersonnelService::getAnnualLeaveType - Error: " . $e->getMessage());
            return null;
        }
    }
}

