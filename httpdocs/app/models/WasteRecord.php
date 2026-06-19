<?php
namespace App\Models;

require_once __DIR__ . '/../core/Model.php';

class WasteRecord extends \App\Core\Model {
    protected $table = 'waste_records';
    
    public function getAll() {
        return $this->query()
            ->select(['wr.*', 'i.name as ingredient_name', 'u.name as reported_by_name'])
            ->from('waste_records wr')
            ->leftJoin('ingredients i', 'wr.ingredient_id', '=', 'i.ingredient_id')
            ->leftJoin('users u', 'wr.reported_by', '=', 'u.user_id')
            ->orderBy('wr.date', 'DESC')
            ->get();
    }
    
    public function getById($wasteId) {
        return $this->query()
            ->select(['wr.*', 'i.name as ingredient_name', 'u.name as reported_by_name'])
            ->from('waste_records wr')
            ->leftJoin('ingredients i', 'wr.ingredient_id', '=', 'i.ingredient_id')
            ->leftJoin('users u', 'wr.reported_by', '=', 'u.user_id')
            ->where('wr.waste_id', $wasteId)
            ->first();
    }
    
    public function getByDateRange($startDate, $endDate) {
        return $this->query()
            ->select(['wr.*', 'i.name as ingredient_name', 'u.name as reported_by_name'])
            ->from('waste_records wr')
            ->leftJoin('ingredients i', 'wr.ingredient_id', '=', 'i.ingredient_id')
            ->leftJoin('users u', 'wr.reported_by', '=', 'u.user_id')
            ->whereBetween('wr.date', [$startDate, $endDate])
            ->orderBy('wr.date', 'DESC')
            ->get();
    }
    
    public function getByReason($reason) {
        return $this->query()
            ->select(['wr.*', 'i.name as ingredient_name', 'u.name as reported_by_name'])
            ->from('waste_records wr')
            ->leftJoin('ingredients i', 'wr.ingredient_id', '=', 'i.ingredient_id')
            ->leftJoin('users u', 'wr.reported_by', '=', 'u.user_id')
            ->where('wr.reason', $reason)
            ->orderBy('wr.date', 'DESC')
            ->get();
    }
    
    public function create($data) {
        return $this->query()
            ->from('waste_records')
            ->insert($data);
    }

    public function updateWasteRecord($wasteId, $data) {
        return $this->query()
            ->from('waste_records')
            ->where('waste_id', $wasteId)
            ->update($data);
    }

    public function deleteWasteRecord($wasteId) {
        return $this->query()
            ->from('waste_records')
            ->where('waste_id', $wasteId)
            ->delete();
    }
    
    public function getTotalWasteByDateRange($startDate, $endDate) {
        $result = $this->query()
            ->from('waste_records')
            ->whereBetween('date', [$startDate, $endDate])
            ->sum('amount');
        return (float)($result ?: 0);
    }
    
    public function getTotalWasteByReason($reason, $startDate = null, $endDate = null) {
        $query = $this->query()
            ->from('waste_records')
            ->where('reason', $reason);
        
        if ($startDate && $endDate) {
            $query->whereBetween('date', [$startDate, $endDate]);
        }
        
        $result = $query->sum('amount');
        return (float)($result ?: 0);
    }
    
    public function getWasteByIngredient($ingredientId, $startDate = null, $endDate = null) {
        $query = $this->query()
            ->from('waste_records')
            ->where('ingredient_id', $ingredientId);
        
        if ($startDate && $endDate) {
            $query->whereBetween('date', [$startDate, $endDate]);
        }
        
        $result = $query->sum('amount');
        return (float)($result ?: 0);
    }
}