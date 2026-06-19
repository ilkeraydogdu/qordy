<?php
namespace App\Repositories;

use App\Core\BaseRepository;
use PDO;

class ShiftScheduleRepository extends BaseRepository {
    protected $table = 'shift_schedules';
    protected $primaryKey = 'schedule_id';

    public function __construct($database) {
        parent::__construct($database);
    }

    /**
     * Get schedules by staff ID
     * @param string $staffId
     * @return array
     */
    public function getByStaff(string $staffId): array {
        $sql = "SELECT ss.*, 
                CASE 
                    WHEN ss.staff_type = 'GUEST_STAFF' THEN ss.staff_name
                    ELSE u.name 
                END as staff_name,
                CASE 
                    WHEN ss.staff_type = 'GUEST_STAFF' THEN ss.staff_phone
                    ELSE NULL 
                END as staff_phone
                FROM {$this->table} ss 
                LEFT JOIN users u ON ss.staff_id = u.user_id AND ss.staff_type = 'USER'
                WHERE ss.staff_id = :staff_id 
                ORDER BY ss.shift_date ASC, ss.start_time ASC";
        return $this->fetchAll($sql, ['staff_id' => $staffId]);
    }

    /**
     * Get schedules by date range
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    public function getByDateRange(string $startDate, string $endDate): array {
        $sql = "SELECT ss.*, 
                CASE 
                    WHEN ss.staff_type = 'GUEST_STAFF' THEN ss.staff_name
                    ELSE u.name 
                END as staff_name,
                CASE 
                    WHEN ss.staff_type = 'GUEST_STAFF' THEN ss.staff_phone
                    ELSE NULL 
                END as staff_phone
                FROM {$this->table} ss 
                LEFT JOIN users u ON ss.staff_id = u.user_id AND ss.staff_type = 'USER'
                WHERE ss.shift_date BETWEEN :start_date AND :end_date 
                ORDER BY ss.shift_date ASC, ss.start_time ASC";
        return $this->fetchAll($sql, [
            'start_date' => $startDate,
            'end_date' => $endDate
        ]);
    }

    /**
     * Get schedules by date
     * @param string $date
     * @return array
     */
    public function getByDate(string $date): array {
        $sql = "SELECT ss.*, 
                CASE 
                    WHEN ss.staff_type = 'GUEST_STAFF' THEN ss.staff_name
                    ELSE u.name 
                END as staff_name,
                CASE 
                    WHEN ss.staff_type = 'GUEST_STAFF' THEN ss.staff_phone
                    ELSE NULL 
                END as staff_phone
                FROM {$this->table} ss 
                LEFT JOIN users u ON ss.staff_id = u.user_id AND ss.staff_type = 'USER'
                WHERE ss.shift_date = :shift_date 
                ORDER BY ss.start_time ASC";
        return $this->fetchAll($sql, ['shift_date' => $date]);
    }

    /**
     * Get schedules by staff and date
     * @param string $staffId
     * @param string $date
     * @return array|null
     */
    public function getByStaffAndDate(string $staffId, string $date): ?array {
        $sql = "SELECT * FROM {$this->table} 
                WHERE staff_id = :staff_id AND shift_date = :shift_date 
                LIMIT 1";
        return $this->fetchOne($sql, [
            'staff_id' => $staffId,
            'shift_date' => $date
        ]);
    }

    /**
     * Generate shifts from weekly schedule for a date range
     * @param string $staffId
     * @param string $startDate
     * @param string $endDate
     * @return int Number of shifts generated
     */
    public function generateFromWeeklySchedule(string $staffId, string $startDate, string $endDate): int {
        // This will be implemented in the service layer
        return 0;
    }
}

