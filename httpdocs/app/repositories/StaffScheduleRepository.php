<?php
namespace App\Repositories;

use App\Core\BaseRepository;
use PDO;

class StaffScheduleRepository extends BaseRepository {
    protected $table = 'staff_schedules';
    protected $primaryKey = 'schedule_id';

    public function __construct($database) {
        parent::__construct($database);
    }

    /**
     * Get schedule by staff ID
     * @param string $staffId
     * @return array
     */
    public function getByStaff(string $staffId): array {
        $sql = "SELECT * FROM {$this->table} 
                WHERE staff_id = :staff_id 
                ORDER BY day_of_week ASC";
        return $this->fetchAll($sql, ['staff_id' => $staffId]);
    }
    
    /**
     * Get schedules for multiple staff members at once (batch loading)
     * @param array $staffIds Array of staff IDs
     * @return array Associative array: staff_id => [schedule1, schedule2, ...]
     */
    public function getByStaffBatch(array $staffIds): array {
        if (empty($staffIds)) {
            return [];
        }
        
        $placeholders = implode(',', array_fill(0, count($staffIds), '?'));
        $sql = "SELECT * FROM {$this->table} 
                WHERE staff_id IN ({$placeholders})
                ORDER BY staff_id, day_of_week ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($staffIds);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $schedulesByStaff = [];
        foreach ($results as $schedule) {
            $staffId = $schedule['staff_id'];
            if (!isset($schedulesByStaff[$staffId])) {
                $schedulesByStaff[$staffId] = [];
            }
            $schedulesByStaff[$staffId][] = $schedule;
        }
        
        return $schedulesByStaff;
    }

    /**
     * Get schedule by staff and day
     * @param string $staffId
     * @param int $dayOfWeek 0=Sunday, 1=Monday, ..., 6=Saturday
     * @return array|null
     */
    public function getByStaffAndDay(string $staffId, int $dayOfWeek): ?array {
        $sql = "SELECT * FROM {$this->table} 
                WHERE staff_id = :staff_id AND day_of_week = :day_of_week 
                LIMIT 1";
        return $this->fetchOne($sql, [
            'staff_id' => $staffId,
            'day_of_week' => $dayOfWeek
        ]);
    }

    /**
     * Save or update schedule
     * @param array $scheduleData
     * @return bool
     */
    public function saveSchedule(array $scheduleData): bool {
        if (empty($scheduleData['schedule_id'])) {
            $scheduleData['schedule_id'] = generateId('ss');
        }

        $existing = $this->getByStaffAndDay(
            $scheduleData['staff_id'],
            $scheduleData['day_of_week']
        );

        if ($existing) {
            return $this->update($existing['schedule_id'], $scheduleData);
        } else {
            return $this->create($scheduleData) !== false;
        }
    }

    /**
     * Delete all schedules for a staff member
     * @param string $staffId
     * @return bool
     */
    public function deleteByStaff(string $staffId): bool {
        $sql = "DELETE FROM {$this->table} WHERE staff_id = :staff_id";
        return $this->execute($sql, ['staff_id' => $staffId]);
    }
}

