<?php
namespace App\Repositories;

use App\Core\BaseRepository;
use PDO;

class ShiftRepository extends BaseRepository {
    protected $table = 'shifts';
    protected $primaryKey = 'shift_id';

    public function __construct($database) {
        parent::__construct($database);
    }

    public function getAll(): array {
        $sql = "SELECT s.*, u.name as staff_name 
                FROM {$this->table} s 
                LEFT JOIN users u ON s.staff_id = u.user_id 
                ORDER BY s.start_time DESC";
        return $this->fetchAll($sql);
    }

    public function getByStaff(string $staffId): array {
        $sql = "SELECT * FROM {$this->table} 
                WHERE staff_id = :staff_id 
                ORDER BY start_time DESC";
        return $this->fetchAll($sql, ['staff_id' => $staffId]);
    }

    public function getByDateRange(string $startDate, string $endDate): array {
        $sql = "SELECT s.*, u.name as staff_name 
                FROM {$this->table} s 
                LEFT JOIN users u ON s.staff_id = u.user_id 
                WHERE s.start_time BETWEEN :start_date AND :end_date 
                ORDER BY s.start_time DESC";
        return $this->fetchAll($sql, [
            'start_date' => $startDate,
            'end_date' => $endDate
        ]);
    }

    public function getCurrentShift(string $staffId): ?array {
        $sql = "SELECT * FROM {$this->table} 
                WHERE staff_id = :staff_id AND status = 'OPEN' 
                ORDER BY start_time DESC 
                LIMIT 1";
        return $this->fetchOne($sql, ['staff_id' => $staffId]);
    }

    public function getOpenShifts(): array {
        $sql = "SELECT s.*, u.name as staff_name 
                FROM {$this->table} s 
                LEFT JOIN users u ON s.staff_id = u.user_id 
                WHERE s.status = 'OPEN' 
                ORDER BY s.start_time";
        return $this->fetchAll($sql);
    }

    public function closeShift(string $shiftId, float $closingCash, float $totalSales): bool {
        $sql = "UPDATE {$this->table} 
                SET end_time = NOW(), 
                    closing_cash = :closing_cash, 
                    total_sales = :total_sales, 
                    status = 'CLOSED' 
                WHERE shift_id = :shift_id";
        return $this->execute($sql, [
            'shift_id' => $shiftId,
            'closing_cash' => $closingCash,
            'total_sales' => $totalSales
        ]);
    }
    
    /**
     * Calculate cash difference
     * @param string $shiftId
     * @return bool
     */
    public function calculateCashDifference(string $shiftId): bool {
        $sql = "UPDATE {$this->table} 
                SET cash_difference = (COALESCE(closing_cash, 0) - COALESCE(opening_cash, 0) - COALESCE(total_sales, 0))
                WHERE shift_id = :shift_id";
        return $this->execute($sql, ['shift_id' => $shiftId]);
    }
}

