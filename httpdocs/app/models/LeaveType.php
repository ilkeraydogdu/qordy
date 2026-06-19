<?php
namespace App\Models;

require_once __DIR__ . '/../core/Model.php';

class LeaveType extends \App\Core\Model {
    protected $table = 'leave_types';
    
    public function getAll() {
        return $this->query()
            ->orderBy('type_name')
            ->get();
    }
    
    public function getById($leaveTypeId) {
        return $this->query()
            ->where('leave_type_id', $leaveTypeId)
            ->first();
    }
    
    public function getByCode($typeCode) {
        return $this->query()
            ->where('type_code', $typeCode)
            ->first();
    }
    
    public function getActive() {
        return $this->query()
            ->where('is_active', 1)
            ->orderBy('type_name')
            ->get();
    }
    
    public function create($data) {
        return $this->query()
            ->insert($data);
    }
    
    public function updateLeaveType($leaveTypeId, $data) {
        return $this->query()
            ->where('leave_type_id', $leaveTypeId)
            ->update($data);
    }
    
    public function deleteLeaveType($leaveTypeId) {
        return $this->query()
            ->where('leave_type_id', $leaveTypeId)
            ->delete();
    }
}

