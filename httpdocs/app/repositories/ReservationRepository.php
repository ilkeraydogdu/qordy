<?php
namespace App\Repositories;

use App\Core\BaseRepository;

class ReservationRepository extends BaseRepository {
    protected $table = 'reservations';
    protected $primaryKey = 'reservation_id';

    public function __construct($database) {
        parent::__construct($database);
    }

    /**
     * Get all reservations with table names
     * Override base findAll to include table name via JOIN
     * @param array $criteria Optional criteria for filtering
     * @return array All reservations with table names
     */
    public function findAll(array $criteria = []): array {
        $sql = "SELECT r.*, t.name as table_name 
                FROM {$this->table} r
                LEFT JOIN tables t ON r.table_id = t.table_id";
        $params = [];
        
        if (!empty($criteria)) {
            $conditions = [];
            foreach ($criteria as $field => $value) {
                // Sanitize field names to prevent SQL injection
                if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $field)) {
                    $conditions[] = "r.{$field} = :{$field}";
                    $params[$field] = $value;
                }
            }
            if (!empty($conditions)) {
                $sql .= " WHERE " . implode(' AND ', $conditions);
            }
        }
        
        $sql .= " ORDER BY r.date DESC, r.time DESC";
        
        return $this->fetchAll($sql, $params);
    }

    public function getByDate(string $date): array {
        $sql = "SELECT * FROM {$this->table} WHERE date = :date ORDER BY time ASC";
        return $this->fetchAll($sql, ['date' => $date]);
    }

    public function getByStatus(string $status): array {
        $sql = "SELECT * FROM {$this->table} WHERE status = :status ORDER BY date ASC, time ASC";
        return $this->fetchAll($sql, ['status' => $status]);
    }

    public function getUpcoming(): array {
        $sql = "SELECT * FROM {$this->table} WHERE date >= CURDATE() AND status IN ('PENDING', 'CONFIRMED') ORDER BY date ASC, time ASC";
        return $this->fetchAll($sql);
    }

    public function getByTableId(string $tableId): array {
        $sql = "SELECT * FROM {$this->table} WHERE table_id = :table_id ORDER BY date DESC, time DESC";
        return $this->fetchAll($sql, ['table_id' => $tableId]);
    }

    /**
     * Check if table is available for a specific date and time
     * Now includes time slot overlap checking (default 2 hours reservation duration)
     * @param string $tableId Table ID
     * @param string $date Date (Y-m-d)
     * @param string $time Time (H:i)
     * @param string $excludeReservationId Optional reservation ID to exclude from check
     * @param int $reservationDurationMinutes Reservation duration in minutes (default: 120 = 2 hours)
     * @return bool True if available, false if conflict exists
     */
    public function isTableAvailable(string $tableId, string $date, string $time, string $excludeReservationId = '', int $reservationDurationMinutes = 120): bool {
        // Convert time to datetime for comparison
        $requestDateTime = strtotime($date . ' ' . $time);
        $requestEndTime = $requestDateTime + ($reservationDurationMinutes * 60);
        
        // Get all active reservations for this table on this date
        $sql = "SELECT reservation_id, date, time, status 
                FROM {$this->table} 
                WHERE table_id = :table_id 
                AND date = :date 
                AND status IN ('PENDING', 'CONFIRMED')";
        
        $params = [
            'table_id' => $tableId,
            'date' => $date
        ];
        
        if (!empty($excludeReservationId)) {
            $sql .= " AND reservation_id != :exclude_id";
            $params['exclude_id'] = $excludeReservationId;
        }
        
        $reservations = $this->fetchAll($sql, $params);
        
        // Check for time overlaps
        foreach ($reservations as $reservation) {
            $reservationDateTime = strtotime($reservation['date'] . ' ' . $reservation['time']);
            // Assume same duration for existing reservations (or could be stored in DB)
            $reservationEndTime = $reservationDateTime + ($reservationDurationMinutes * 60);
            
            // Check if time slots overlap
            // Overlap occurs if: request starts before reservation ends AND request ends after reservation starts
            if ($requestDateTime < $reservationEndTime && $requestEndTime > $reservationDateTime) {
                return false; // Conflict found
            }
        }
        
        return true; // No conflicts
    }
    
    /**
     * Get conflicting reservations for a table, date, and time
     * @param string $tableId Table ID
     * @param string $date Date (Y-m-d)
     * @param string $time Time (H:i)
     * @param string $excludeReservationId Optional reservation ID to exclude from check
     * @param int $reservationDurationMinutes Reservation duration in minutes (default: 120)
     * @return array Conflicting reservations
     */
    public function getConflictingReservations(string $tableId, string $date, string $time, string $excludeReservationId = '', int $reservationDurationMinutes = 120): array {
        $conflicts = [];
        
        // Convert time to datetime for comparison
        $requestDateTime = strtotime($date . ' ' . $time);
        $requestEndTime = $requestDateTime + ($reservationDurationMinutes * 60);
        
        // Get all active reservations for this table on this date
        $sql = "SELECT reservation_id, customer_name, date, time, status 
                FROM {$this->table} 
                WHERE table_id = :table_id 
                AND date = :date 
                AND status IN ('PENDING', 'CONFIRMED')";
        
        $params = [
            'table_id' => $tableId,
            'date' => $date
        ];
        
        if (!empty($excludeReservationId)) {
            $sql .= " AND reservation_id != :exclude_id";
            $params['exclude_id'] = $excludeReservationId;
        }
        
        $reservations = $this->fetchAll($sql, $params);
        
        // Check for time overlaps
        foreach ($reservations as $reservation) {
            $reservationDateTime = strtotime($reservation['date'] . ' ' . $reservation['time']);
            $reservationEndTime = $reservationDateTime + ($reservationDurationMinutes * 60);
            
            // Check if time slots overlap
            if ($requestDateTime < $reservationEndTime && $requestEndTime > $reservationDateTime) {
                $conflicts[] = $reservation;
            }
        }
        
        return $conflicts;
    }
    
    /**
     * Get reservations by date range
     * @param string $startDate Start date (Y-m-d)
     * @param string $endDate End date (Y-m-d)
     * @return array Reservations
     */
    public function getReservationsByDateRange(string $startDate, string $endDate): array {
        $sql = "SELECT r.*, t.name as table_name 
                FROM {$this->table} r
                LEFT JOIN tables t ON r.table_id = t.table_id
                WHERE r.date BETWEEN :start_date AND :end_date 
                ORDER BY r.date ASC, r.time ASC";
        return $this->fetchAll($sql, [
            'start_date' => $startDate,
            'end_date' => $endDate
        ]);
    }

    /**
     * Get reservations for a specific date and time
     * @param string $date Date (Y-m-d)
     * @param string $time Time (H:i)
     * @return array Reservations
     */
    public function getByDateTime(string $date, string $time): array {
        $sql = "SELECT * FROM {$this->table} 
                WHERE date = :date 
                AND time = :time 
                AND status IN ('PENDING', 'CONFIRMED')
                ORDER BY time ASC";
        return $this->fetchAll($sql, ['date' => $date, 'time' => $time]);
    }

    /**
     * Get reserved table IDs for a specific date and time
     * @param string $date Date (Y-m-d)
     * @param string $time Time (H:i)
     * @return array Table IDs
     */
    public function getReservedTableIds(string $date, string $time): array {
        $sql = "SELECT DISTINCT table_id FROM {$this->table} 
                WHERE date = :date 
                AND time = :time 
                AND table_id IS NOT NULL
                AND status IN ('PENDING', 'CONFIRMED')";
        $results = $this->fetchAll($sql, ['date' => $date, 'time' => $time]);
        return array_column($results, 'table_id');
    }
}

