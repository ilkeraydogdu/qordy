<?php
namespace App\Repositories;

use App\Core\BaseRepository;

class ExpenseRepository extends BaseRepository {
    protected $table = 'expenses';
    protected $primaryKey = 'expense_id';

    public function __construct($database) {
        parent::__construct($database);
    }

    public function getByDateRange(string $startDate, string $endDate): array {
        $sql = "SELECT * FROM {$this->table} WHERE date BETWEEN :start_date AND :end_date";
        $params = [
            'start_date' => $startDate,
            'end_date' => $endDate
        ];
        
        // Add tenant filter
        $sql = $this->addTenantToWhere($sql, $params);
        $sql .= " ORDER BY date DESC";
        
        return $this->fetchAll($sql, $params);
    }

    public function getByCategory(string $category): array {
        $sql = "SELECT * FROM {$this->table} WHERE category = :category";
        $params = ['category' => $category];
        
        // Add tenant filter
        $sql = $this->addTenantToWhere($sql, $params);
        $sql .= " ORDER BY date DESC";
        
        return $this->fetchAll($sql, $params);
    }

    public function getTotalByDateRange(string $startDate, string $endDate): float {
        $sql = "SELECT SUM(amount) as total FROM {$this->table} WHERE date BETWEEN :start_date AND :end_date";
        $params = [
            'start_date' => $startDate,
            'end_date' => $endDate
        ];
        
        // Add tenant filter
        $sql = $this->addTenantToWhere($sql, $params);
        
        $result = $this->fetchOne($sql, $params);
        return (float)($result['total'] ?? 0);
    }
}
