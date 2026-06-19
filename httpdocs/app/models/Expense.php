<?php
namespace App\Models;

require_once __DIR__ . '/../core/Model.php';

class Expense extends \App\Core\Model {
    protected $table = 'expenses';
    
    public function getAll() {
        return $this->query()
            ->select(['e.*', 'u.name as added_by_name', 's.name as supplier_name'])
            ->from('expenses e')
            ->leftJoin('users u', 'e.added_by', '=', 'u.user_id')
            ->leftJoin('suppliers s', 'e.supplier_id', '=', 's.supplier_id')
            ->orderBy('e.date', 'DESC')
            ->get();
    }
    
    public function getById($expenseId) {
        return $this->query()
            ->select(['e.*', 'u.name as added_by_name', 's.name as supplier_name'])
            ->from('expenses e')
            ->leftJoin('users u', 'e.added_by', '=', 'u.user_id')
            ->leftJoin('suppliers s', 'e.supplier_id', '=', 's.supplier_id')
            ->where('e.expense_id', $expenseId)
            ->first();
    }
    
    public function getByDateRange($startDate, $endDate) {
        return $this->query()
            ->select(['e.*', 'u.name as added_by_name', 's.name as supplier_name'])
            ->from('expenses e')
            ->leftJoin('users u', 'e.added_by', '=', 'u.user_id')
            ->leftJoin('suppliers s', 'e.supplier_id', '=', 's.supplier_id')
            ->whereBetween('e.date', [$startDate, $endDate])
            ->orderBy('e.date', 'DESC')
            ->get();
    }
    
    public function getByCategory($category) {
        return $this->query()
            ->select(['e.*', 'u.name as added_by_name', 's.name as supplier_name'])
            ->from('expenses e')
            ->leftJoin('users u', 'e.added_by', '=', 'u.user_id')
            ->leftJoin('suppliers s', 'e.supplier_id', '=', 's.supplier_id')
            ->where('e.category', $category)
            ->orderBy('e.date', 'DESC')
            ->get();
    }
    
    public function create($data) {
        return $this->query()
            ->from('expenses')
            ->insert($data);
    }

    public function updateExpense($expenseId, $data) {
        return $this->query()
            ->from('expenses')
            ->where('expense_id', $expenseId)
            ->update($data);
    }

    public function deleteExpense($expenseId) {
        return $this->query()
            ->where('expense_id', $expenseId)
            ->delete();
    }
    
    public function getTotalByDateRange($startDate, $endDate) {
        $result = $this->query()
            ->whereBetween('date', [$startDate, $endDate])
            ->sum('amount');
        return (float)($result ?: 0);
    }
    
    public function getTotalByCategory($category, $startDate = null, $endDate = null) {
        $query = $this->query()
            ->where('category', $category);
        
        if ($startDate && $endDate) {
            $query->whereBetween('date', [$startDate, $endDate]);
        }
        
        $result = $query->sum('amount');
        return (float)($result ?: 0);
    }
}