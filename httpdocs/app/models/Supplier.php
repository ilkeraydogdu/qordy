<?php
namespace App\Models;

require_once __DIR__ . '/../core/Model.php';

class Supplier extends \App\Core\Model {
    protected $table = 'suppliers';
    
    public function getAll() {
        return $this->query()
            ->from('suppliers')
            ->orderBy('name')
            ->get();
    }
    
    public function getById($supplierId) {
        return $this->query()
            ->from('suppliers')
            ->where('supplier_id', $supplierId)
            ->first();
    }
    
    public function create($data) {
        return $this->query()
            ->from('suppliers')
            ->insert($data);
    }

    public function updateSupplier($supplierId, $data) {
        return $this->query()
            ->from('suppliers')
            ->where('supplier_id', $supplierId)
            ->update($data);
    }

    public function deleteSupplier($supplierId) {
        return $this->query()
            ->from('suppliers')
            ->where('supplier_id', $supplierId)
            ->delete();
    }
    
    public function getBalance($supplierId) {
        $result = $this->query()
            ->from('suppliers')
            ->where('supplier_id', $supplierId)
            ->first();
        return $result ? (float)($result['balance'] ?? 0) : 0;
    }
    
    public function updateBalance($supplierId, $amount) {
        $sql = "UPDATE suppliers SET balance = balance + :amount WHERE supplier_id = :supplier_id";
        return $this->rawQuery($sql, ['supplier_id' => $supplierId, 'amount' => $amount]);
    }
    
    public function getByCategory($category) {
        return $this->query()
            ->from('suppliers')
            ->where('category', $category)
            ->orderBy('name')
            ->get();
    }
}