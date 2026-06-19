<?php
namespace App\Services;

use App\Core\BaseService;
use App\Repositories\StaffScheduleRepository;

class StaffScheduleService extends BaseService {
    
    public function __construct(StaffScheduleRepository $repository) {
        parent::__construct($repository);
    }

    /**
     * Get weekly schedule for a staff member
     * @param string $staffId
     * @return array Array with 7 days (0=Sunday to 6=Saturday)
     */
    public function getWeeklySchedule(string $staffId): array {
        $schedules = $this->repository->getByStaff($staffId);
        
        // Initialize with default values for all days
        $weeklySchedule = [];
        for ($day = 0; $day < 7; $day++) {
            $weeklySchedule[$day] = [
                'day_of_week' => $day,
                'is_working' => 0,
                'start_time' => '09:00:00',
                'end_time' => '17:00:00',
                'break_start' => null,
                'break_end' => null
            ];
        }
        
        // Fill with actual schedules
        foreach ($schedules as $schedule) {
            $day = (int)$schedule['day_of_week'];
            $weeklySchedule[$day] = $schedule;
        }
        
        return $weeklySchedule;
    }

    /**
     * Save weekly schedule for a staff member
     * @param string $staffId
     * @param array $weeklySchedule Array of schedule data for each day
     * @return bool
     */
    public function saveWeeklySchedule(string $staffId, array $weeklySchedule): bool {
        $success = true;
        
        foreach ($weeklySchedule as $day => $scheduleData) {
            if (!isset($scheduleData['is_working']) || !$scheduleData['is_working']) {
                // Delete if exists
                $existing = $this->repository->getByStaffAndDay($staffId, $day);
                if ($existing) {
                    $this->repository->delete($existing['schedule_id']);
                }
                continue;
            }
            
            $data = [
                'staff_id' => $staffId,
                'day_of_week' => $day,
                'start_time' => $scheduleData['start_time'] ?? '09:00:00',
                'end_time' => $scheduleData['end_time'] ?? '17:00:00',
                'is_working' => 1,
                'break_start' => $scheduleData['break_start'] ?? null,
                'break_end' => $scheduleData['break_end'] ?? null
            ];
            
            if (!$this->repository->saveSchedule($data)) {
                $success = false;
            }
        }
        
        return $success;
    }

    /**
     * Get weekly schedules for multiple staff members at once (batch loading)
     * @param array $staffIds Array of staff IDs
     * @return array Associative array: staff_id => weeklySchedule array
     */
    public function getWeeklySchedulesBatch(array $staffIds): array {
        if (empty($staffIds)) {
            return [];
        }
        
        $schedulesByStaff = $this->repository->getByStaffBatch($staffIds);
        $weeklySchedules = [];
        
        foreach ($staffIds as $staffId) {
            $schedules = $schedulesByStaff[$staffId] ?? [];
            
            // Initialize with default values for all days
            $weeklySchedule = [];
            for ($day = 0; $day < 7; $day++) {
                $weeklySchedule[$day] = [
                    'day_of_week' => $day,
                    'is_working' => 0,
                    'start_time' => '09:00:00',
                    'end_time' => '17:00:00',
                    'break_start' => null,
                    'break_end' => null
                ];
            }
            
            // Fill with actual schedules
            foreach ($schedules as $schedule) {
                $day = (int)$schedule['day_of_week'];
                $weeklySchedule[$day] = $schedule;
            }
            
            $weeklySchedules[$staffId] = $weeklySchedule;
        }
        
        return $weeklySchedules;
    }
    
    /**
     * Get all staff with their weekly schedules
     * @return array
     */
    public function getAllStaffSchedules(): array {
        // This will need UserService to get all staff
        return [];
    }
}

