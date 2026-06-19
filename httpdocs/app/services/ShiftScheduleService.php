<?php
namespace App\Services;

use App\Core\BaseService;
use App\Repositories\ShiftScheduleRepository;
use App\Repositories\StaffScheduleRepository;

class ShiftScheduleService extends BaseService {
    private $staffScheduleRepository;
    
    public function __construct(
        ShiftScheduleRepository $repository,
        StaffScheduleRepository $staffScheduleRepository
    ) {
        parent::__construct($repository);
        $this->staffScheduleRepository = $staffScheduleRepository;
    }

    /**
     * Get schedules by date range
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    public function getByDateRange(string $startDate, string $endDate): array {
        return $this->repository->getByDateRange($startDate, $endDate);
    }

    /**
     * Create a shift schedule
     * @param array $scheduleData
     * @return bool|string Schedule ID on success, false on failure
     */
    public function createSchedule(array $scheduleData) {
        if (empty($scheduleData['schedule_id'])) {
            $scheduleData['schedule_id'] = generateId('shs');
        }
        
        if (empty($scheduleData['staff_id']) || empty($scheduleData['shift_date'])) {
            return false;
        }
        
        $defaults = [
            'status' => 'PLANNED',
            'shift_type' => 'REGULAR',
            'staff_type' => 'USER'
        ];
        
        $scheduleData = array_merge($defaults, $scheduleData);
        
        $result = $this->repository->create($scheduleData);
        
        if ($result) {
            return $scheduleData['schedule_id'];
        }
        
        return false;
    }

    /**
     * Update shift schedule
     * @param string $scheduleId
     * @param array $scheduleData
     * @return bool
     */
    public function updateSchedule(string $scheduleId, array $scheduleData): bool {
        return $this->repository->update($scheduleId, $scheduleData);
    }

    /**
     * Delete shift schedule
     * @param string $scheduleId
     * @return bool
     */
    public function deleteSchedule(string $scheduleId): bool {
        return $this->repository->delete($scheduleId);
    }

    /**
     * Clock-in: stamp the actual start time on the schedule row.
     *
     * @param string $scheduleId
     * @param string|null $actualStart Y-m-d H:i:s; defaults to NOW().
     * @return bool
     */
    public function clockIn(string $scheduleId, ?string $actualStart = null): bool {
        return $this->repository->update($scheduleId, [
            'actual_start' => $actualStart ?: date('Y-m-d H:i:s'),
            'status'       => 'IN_PROGRESS',
        ]);
    }

    /**
     * Clock-out: stamp the actual end and optionally record overtime
     * in minutes. When overtime_minutes is not provided we compute it
     * against planned_end_time if the repository exposes it.
     *
     * @param string $scheduleId
     * @param string|null $actualEnd Y-m-d H:i:s; defaults to NOW().
     * @param int|null $overtimeMinutes Overrides computed value when given.
     * @return bool
     */
    public function clockOut(string $scheduleId, ?string $actualEnd = null, ?int $overtimeMinutes = null): bool {
        $actualEnd = $actualEnd ?: date('Y-m-d H:i:s');
        $payload = [
            'actual_end' => $actualEnd,
            'status'     => 'COMPLETED',
        ];
        if ($overtimeMinutes !== null) {
            $payload['overtime_minutes'] = max(0, (int)$overtimeMinutes);
        } else {
            // Best-effort auto-compute: look up planned end time.
            try {
                $row = method_exists($this->repository, 'findById')
                    ? $this->repository->findById($scheduleId)
                    : null;
                if (is_array($row) && !empty($row['end_time']) && !empty($row['shift_date'])) {
                    $plannedEnd = strtotime($row['shift_date'] . ' ' . $row['end_time']);
                    $end = strtotime($actualEnd);
                    if ($plannedEnd && $end && $end > $plannedEnd) {
                        $payload['overtime_minutes'] = (int)round(($end - $plannedEnd) / 60);
                    }
                }
            } catch (\Throwable $e) { /* ignore */ }
        }
        return $this->repository->update($scheduleId, $payload);
    }

    /**
     * Generate shift schedules from weekly schedule for a date range
     * @param string $staffId
     * @param string $startDate
     * @param string $endDate
     * @return int Number of schedules generated
     */
    public function generateFromWeeklySchedule(string $staffId, string $startDate, string $endDate): int {
        $weeklySchedule = $this->staffScheduleRepository->getByStaff($staffId);
        $count = 0;
        
        $start = new \DateTime($startDate);
        $end = new \DateTime($endDate);
        $current = clone $start;
        
        while ($current <= $end) {
            $dayOfWeek = (int)$current->format('w'); // 0=Sunday, 6=Saturday
            $dateStr = $current->format('Y-m-d');
            
            // Find schedule for this day
            $daySchedule = null;
            foreach ($weeklySchedule as $schedule) {
                if ((int)$schedule['day_of_week'] === $dayOfWeek && 
                    ($schedule['is_working'] ?? 0) == 1) {
                    $daySchedule = $schedule;
                    break;
                }
            }
            
            if ($daySchedule) {
                // Check if schedule already exists
                $existing = $this->repository->getByStaffAndDate($staffId, $dateStr);
                if (!$existing) {
                    $scheduleData = [
                        'staff_id' => $staffId,
                        'shift_date' => $dateStr,
                        'start_time' => $daySchedule['start_time'],
                        'end_time' => $daySchedule['end_time'],
                        'status' => 'PLANNED',
                        'shift_type' => 'REGULAR'
                    ];
                    
                    if ($this->createSchedule($scheduleData)) {
                        $count++;
                    }
                }
            }
            
            $current->modify('+1 day');
        }
        
        return $count;
    }

    /**
     * Get repository (for external access)
     * @return ShiftScheduleRepository
     */
    public function getRepository() {
        return $this->repository;
    }
    
    /**
     * Get schedules grouped by date
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    public function getGroupedByDate(string $startDate, string $endDate): array {
        $schedules = $this->getByDateRange($startDate, $endDate);
        $grouped = [];
        
        foreach ($schedules as $schedule) {
            $date = $schedule['shift_date'];
            if (!isset($grouped[$date])) {
                $grouped[$date] = [];
            }
            $grouped[$date][] = $schedule;
        }
        
        return $grouped;
    }
}

