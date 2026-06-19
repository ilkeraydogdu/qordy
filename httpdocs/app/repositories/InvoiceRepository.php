<?php
namespace App\Repositories;

use App\Core\BaseRepository;

class InvoiceRepository extends BaseRepository {
    protected $table = 'invoices';
    protected $primaryKey = 'invoice_id';

    public function __construct($database) {
        parent::__construct($database);
    }

    public function getBySupplierId(string $supplierId): array {
        // CRITICAL: Add tenant filtering
        $filter = $this->getTenantFilter();
        $params = ['supplier_id' => $supplierId];
        $whereConditions = ['i.supplier_id = :supplier_id'];
        
        if (!empty($filter['where'])) {
            $whereConditions[] = $this->tenantWhereForAlias('i', $filter['where']);
            $params = array_merge($params, $filter['params']);
        }
        
        $sql = "SELECT i.* FROM {$this->table} i WHERE " . implode(" AND ", $whereConditions) . " ORDER BY i.date DESC";
        return $this->fetchAll($sql, $params);
    }

    public function getUnpaid(): array {
        // CRITICAL: Add tenant filtering
        $filter = $this->getTenantFilter();
        $params = [];
        $whereConditions = ['(i.is_paid = 0 OR i.is_paid IS NULL)'];
        
        if (!empty($filter['where'])) {
            $whereConditions[] = $this->tenantWhereForAlias('i', $filter['where']);
            $params = array_merge($params, $filter['params']);
        }
        
        $sql = "SELECT i.* FROM {$this->table} i WHERE " . implode(" AND ", $whereConditions) . " ORDER BY i.date ASC";
        return $this->fetchAll($sql, $params);
    }

    public function getByDateRange(string $startDate, string $endDate): array {
        // CRITICAL: Add tenant filtering
        $filter = $this->getTenantFilter();
        $params = [
            'start_date' => $startDate,
            'end_date' => $endDate
        ];
        $whereConditions = ['i.date BETWEEN :start_date AND :end_date'];
        
        if (!empty($filter['where'])) {
            $whereConditions[] = $this->tenantWhereForAlias('i', $filter['where']);
            $params = array_merge($params, $filter['params']);
        }
        
        $sql = "SELECT i.* FROM {$this->table} i WHERE " . implode(" AND ", $whereConditions) . " ORDER BY i.date DESC";
        return $this->fetchAll($sql, $params);
    }

    public function getTotalUnpaid(): float {
        // CRITICAL: Add tenant filtering
        $filter = $this->getTenantFilter();
        $params = [];
        $whereConditions = ['(i.is_paid = 0 OR i.is_paid IS NULL)'];
        
        if (!empty($filter['where'])) {
            $whereConditions[] = $this->tenantWhereForAlias('i', $filter['where']);
            $params = array_merge($params, $filter['params']);
        }
        
        $sql = "SELECT SUM(i.amount) as total FROM {$this->table} i WHERE " . implode(" AND ", $whereConditions);
        $result = $this->fetchOne($sql, $params);
        return (float)($result['total'] ?? 0);
    }
    
    /**
     * Get total unpaid amount for a specific supplier
     * @param string $supplierId Supplier ID
     * @return float Total unpaid amount
     */
    public function getTotalUnpaidBySupplier(string $supplierId): float {
        // CRITICAL: Add tenant filtering
        $filter = $this->getTenantFilter();
        $params = ['supplier_id' => $supplierId];
        $whereConditions = [
            'i.supplier_id = :supplier_id',
            '(i.is_paid = 0 OR i.is_paid IS NULL)'
        ];
        
        if (!empty($filter['where'])) {
            $whereConditions[] = $this->tenantWhereForAlias('i', $filter['where']);
            $params = array_merge($params, $filter['params']);
        }
        
        $sql = "SELECT SUM(i.amount) as total FROM {$this->table} i WHERE " . implode(" AND ", $whereConditions);
        $result = $this->fetchOne($sql, $params);
        return (float)($result['total'] ?? 0);
    }
}

