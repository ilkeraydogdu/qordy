<?php
namespace App\Repositories;

use App\Core\BaseRepository;

class ArchivedSessionRepository extends BaseRepository {
    protected $table = 'archived_sessions';
    protected $primaryKey = 'session_id';

    public function __construct($database) {
        parent::__construct($database);
    }

    public function getAll(): array {
        $sql = "SELECT * FROM {$this->table} ORDER BY start_time DESC";
        return $this->fetchAll($sql);
    }

    public function getById(string $sessionId): ?array {
        $sql = "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = :id LIMIT 1";
        return $this->fetchOne($sql, ['id' => $sessionId]);
    }

    public function getByTable(string $tableId): array {
        $sql = "SELECT * FROM {$this->table} WHERE table_id = :table_id ORDER BY start_time DESC";
        return $this->fetchAll($sql, ['table_id' => $tableId]);
    }

    public function getByDateRange(string $startDate, string $endDate): array {
        $sql = "SELECT * FROM {$this->table} WHERE start_time BETWEEN :start_date AND :end_date ORDER BY start_time DESC";
        return $this->fetchAll($sql, [
            'start_date' => $startDate,
            'end_date' => $endDate
        ]);
    }

    public function getDailyRevenue(string $date): float {
        $sql = "SELECT SUM(total_revenue) as total FROM {$this->table} WHERE DATE(start_time) = :date";
        $result = $this->fetchOne($sql, ['date' => $date]);
        return (float)($result['total'] ?? 0);
    }

    public function getDailyTips(string $date): float {
        $sql = "SELECT SUM(total_tip) as total FROM {$this->table} WHERE DATE(start_time) = :date";
        $result = $this->fetchOne($sql, ['date' => $date]);
        return (float)($result['total'] ?? 0);
    }

    public function getMonthlyRevenue(int $year, int $month): float {
        $sql = "SELECT SUM(total_revenue) as total FROM {$this->table} WHERE YEAR(start_time) = :year AND MONTH(start_time) = :month";
        $result = $this->fetchOne($sql, [
            'year' => $year,
            'month' => $month
        ]);
        return (float)($result['total'] ?? 0);
    }

    public function getTopEarningTables(int $limit = 5): array {
        $sql = "SELECT table_name, COUNT(*) as session_count, SUM(total_revenue) as total_revenue 
                FROM {$this->table} 
                GROUP BY table_name 
                ORDER BY total_revenue DESC 
                LIMIT :limit";
        return $this->fetchAll($sql, ['limit' => $limit]);
    }

    public function getAverageSessionDuration(): float {
        $sql = "SELECT AVG(TIMESTAMPDIFF(MINUTE, start_time, end_time)) as avg_duration 
                FROM {$this->table} 
                WHERE end_time IS NOT NULL";
        $result = $this->fetchOne($sql);
        return $result ? (float)($result['avg_duration'] ?? 0) : 0.0;
    }
}

