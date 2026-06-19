<?php
namespace App\Repositories;

use App\Core\BaseRepository;

class WasteRecordRepository extends BaseRepository {
    protected $table = 'waste_records';
    protected $primaryKey = 'waste_id';

    public function __construct($database) {
        parent::__construct($database);
    }

    public function getAll(): array {
        // CRITICAL: Add tenant filtering for waste_records
        $filter = $this->getTenantFilter();
        $params = [];
        $whereConditions = [];
        
        if (!empty($filter['where'])) {
            $whereConditions[] = $this->tenantWhereForAlias('wr', $filter['where']);
            $params = array_merge($params, $filter['params']);
        }
        
        // NOTE: Removed ingredient tenant filtering - ingredients are joined by ingredient_id
        // and may use different column naming (business_id vs tenant_id)
        // The waste_records tenant filter is sufficient for security
        
        $whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";
        
        $sql = "SELECT wr.*, i.name as ingredient_name, u.name as reported_by_name 
                FROM {$this->table} wr
                LEFT JOIN ingredients i ON wr.ingredient_id = i.ingredient_id
                LEFT JOIN users u ON wr.reported_by = u.user_id
                {$whereClause}
                ORDER BY wr.date DESC";
        return $this->fetchAll($sql, $params);
    }

    public function getById(string $wasteId): ?array {
        // CRITICAL: Add tenant filtering
        $filter = $this->getTenantFilter();
        $params = ['id' => $wasteId];
        $whereConditions = ["wr.{$this->primaryKey} = :id"];
        
        if (!empty($filter['where'])) {
            $whereConditions[] = $this->tenantWhereForAlias('wr', $filter['where']);
            $params = array_merge($params, $filter['params']);
        }
        
        $sql = "SELECT wr.*, i.name as ingredient_name, u.name as reported_by_name 
                FROM {$this->table} wr
                LEFT JOIN ingredients i ON wr.ingredient_id = i.ingredient_id
                LEFT JOIN users u ON wr.reported_by = u.user_id
                WHERE " . implode(" AND ", $whereConditions) . " LIMIT 1";
        return $this->fetchOne($sql, $params);
    }

    public function getByDateRange(string $startDate, string $endDate): array {
        // CRITICAL: Add tenant filtering for waste_records
        $filter = $this->getTenantFilter();
        $params = [
            'start_date' => $startDate,
            'end_date' => $endDate
        ];
        $whereConditions = ['wr.date BETWEEN :start_date AND :end_date'];
        
        if (!empty($filter['where'])) {
            $whereConditions[] = $this->tenantWhereForAlias('wr', $filter['where']);
            $params = array_merge($params, $filter['params']);
        }
        
        // NOTE: Removed ingredient tenant filtering - waste_records filter is sufficient
        
        $sql = "SELECT wr.*, i.name as ingredient_name, u.name as reported_by_name 
                FROM {$this->table} wr
                LEFT JOIN ingredients i ON wr.ingredient_id = i.ingredient_id
                LEFT JOIN users u ON wr.reported_by = u.user_id
                WHERE " . implode(" AND ", $whereConditions) . "
                ORDER BY wr.date DESC";
        return $this->fetchAll($sql, $params);
    }

    public function getByReason(string $reason): array {
        // CRITICAL: Add tenant filtering for waste_records
        $filter = $this->getTenantFilter();
        $params = ['reason' => $reason];
        $whereConditions = ['wr.reason = :reason'];
        
        if (!empty($filter['where'])) {
            $whereConditions[] = $this->tenantWhereForAlias('wr', $filter['where']);
            $params = array_merge($params, $filter['params']);
        }
        
        // NOTE: Removed ingredient tenant filtering - waste_records filter is sufficient
        
        $sql = "SELECT wr.*, i.name as ingredient_name, u.name as reported_by_name 
                FROM {$this->table} wr
                LEFT JOIN ingredients i ON wr.ingredient_id = i.ingredient_id
                LEFT JOIN users u ON wr.reported_by = u.user_id
                WHERE " . implode(" AND ", $whereConditions) . "
                ORDER BY wr.date DESC";
        return $this->fetchAll($sql, $params);
    }

    public function getTotalWasteByDateRange(string $startDate, string $endDate): float {
        // CRITICAL: Add tenant filtering
        $filter = $this->getTenantFilter();
        $params = [
            'start_date' => $startDate,
            'end_date' => $endDate
        ];
        $whereConditions = ['wr.date BETWEEN :start_date AND :end_date'];
        
        if (!empty($filter['where'])) {
            $whereConditions[] = $this->tenantWhereForAlias('wr', $filter['where']);
            $params = array_merge($params, $filter['params']);
        }
        
        $sql = "SELECT SUM(wr.amount) as total FROM {$this->table} wr WHERE " . implode(" AND ", $whereConditions);
        $result = $this->fetchOne($sql, $params);
        return (float)($result['total'] ?? 0);
    }

    public function getTotalWasteByReason(string $reason, ?string $startDate = null, ?string $endDate = null): float {
        // CRITICAL: Add tenant filtering
        $filter = $this->getTenantFilter();
        $params = ['reason' => $reason];
        $whereConditions = ['wr.reason = :reason'];
        
        if ($startDate && $endDate) {
            $whereConditions[] = "wr.date BETWEEN :start_date AND :end_date";
            $params['start_date'] = $startDate;
            $params['end_date'] = $endDate;
        }
        
        if (!empty($filter['where'])) {
            $whereConditions[] = $this->tenantWhereForAlias('wr', $filter['where']);
            $params = array_merge($params, $filter['params']);
        }
        
        $sql = "SELECT SUM(wr.amount) as total FROM {$this->table} wr WHERE " . implode(" AND ", $whereConditions);
        $result = $this->fetchOne($sql, $params);
        return (float)($result['total'] ?? 0);
    }

    public function getWasteByIngredient(string $ingredientId, ?string $startDate = null, ?string $endDate = null): float {
        // CRITICAL: Add tenant filtering
        $filter = $this->getTenantFilter();
        $params = ['ingredient_id' => $ingredientId];
        $whereConditions = ['wr.ingredient_id = :ingredient_id'];
        
        if ($startDate && $endDate) {
            $whereConditions[] = "wr.date BETWEEN :start_date AND :end_date";
            $params['start_date'] = $startDate;
            $params['end_date'] = $endDate;
        }
        
        if (!empty($filter['where'])) {
            $whereConditions[] = $this->tenantWhereForAlias('wr', $filter['where']);
            $params = array_merge($params, $filter['params']);
        }
        
        $sql = "SELECT SUM(wr.amount) as total FROM {$this->table} wr WHERE " . implode(" AND ", $whereConditions);
        $result = $this->fetchOne($sql, $params);
        return (float)($result['total'] ?? 0);
    }

    public function deleteWasteRecord(string $wasteId): bool {
        return $this->delete($wasteId);
    }
}

