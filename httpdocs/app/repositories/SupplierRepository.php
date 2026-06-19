<?php
namespace App\Repositories;

use App\Core\BaseRepository;

class SupplierRepository extends BaseRepository {
    protected $table = 'suppliers';
    protected $primaryKey = 'supplier_id';

    public function __construct($database) {
        parent::__construct($database);
    }

    public function getAllOrdered(): array {
        $sql = "SELECT * FROM {$this->table}";
        $params = [];
        
        // Add tenant filter
        $sql = $this->addTenantToWhere($sql, $params);
        $sql .= " ORDER BY name ASC";
        
        return $this->fetchAll($sql, $params);
    }

    public function getByCategory(string $category): array {
        $sql = "SELECT * FROM {$this->table} WHERE category = :category";
        $params = ['category' => $category];
        
        // Add tenant filter
        $sql = $this->addTenantToWhere($sql, $params);
        $sql .= " ORDER BY name ASC";
        
        return $this->fetchAll($sql, $params);
    }

    public function updateBalance(string $supplierId, float $amount): bool {
        // updateBalance needs tenant isolation too
        $sql = "UPDATE {$this->table} SET balance = balance + :amount WHERE {$this->primaryKey} = :supplier_id";
        $params = [
            'supplier_id' => $supplierId,
            'amount' => $amount
        ];
        
        // Add tenant filter for security
        $filter = $this->getTenantFilter();
        if (!empty($filter['where'])) {
            $sql .= " AND " . $filter['where'];
            $params = array_merge($params, $filter['params']);
        }
        
        return $this->execute($sql, $params);
    }
}
