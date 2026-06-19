<?php
namespace App\Models;

require_once __DIR__ . '/../core/Model.php';

class Leave extends \App\Core\Model {
    protected $table = 'leaves';
    
    public function getAll() {
        return $this->query()
            ->select(['l.*', 'u.name as staff_name', 'lt.type_name as leave_type_name', 'lt.type_code as leave_type_code', 'a.name as approved_by_name'])
            ->from('leaves l')
            ->leftJoin('users u', 'l.user_id', '=', 'u.user_id')
            ->leftJoin('leave_types lt', 'l.leave_type_id', '=', 'lt.leave_type_id')
            ->leftJoin('users a', 'l.approved_by', '=', 'a.user_id')
            ->orderBy('l.start_date', 'DESC')
            ->get();
    }
    
    public function getById($leaveId) {
        return $this->query()
            ->select(['l.*', 'u.name as staff_name', 'lt.type_name as leave_type_name', 'lt.type_code as leave_type_code', 'a.name as approved_by_name'])
            ->from('leaves l')
            ->leftJoin('users u', 'l.user_id', '=', 'u.user_id')
            ->leftJoin('leave_types lt', 'l.leave_type_id', '=', 'lt.leave_type_id')
            ->leftJoin('users a', 'l.approved_by', '=', 'a.user_id')
            ->where('l.leave_id', $leaveId)
            ->first();
    }
    
    public function getByUser($userId) {
        return $this->query()
            ->select(['l.*', 'lt.type_name as leave_type_name', 'lt.type_code as leave_type_code', 'a.name as approved_by_name'])
            ->from('leaves l')
            ->leftJoin('leave_types lt', 'l.leave_type_id', '=', 'lt.leave_type_id')
            ->leftJoin('users a', 'l.approved_by', '=', 'a.user_id')
            ->where('l.user_id', $userId)
            ->orderBy('l.start_date', 'DESC')
            ->get();
    }
    
    public function getByDateRange($startDate, $endDate) {
        return $this->query()
            ->select(['l.*', 'u.name as staff_name', 'lt.type_name as leave_type_name', 'lt.type_code as leave_type_code'])
            ->from('leaves l')
            ->leftJoin('users u', 'l.user_id', '=', 'u.user_id')
            ->leftJoin('leave_types lt', 'l.leave_type_id', '=', 'lt.leave_type_id')
            ->whereBetween('l.start_date', [$startDate, $endDate])
            ->orderBy('l.start_date', 'DESC')
            ->get();
    }
    
    public function getByStatus($status) {
        return $this->query()
            ->select(['l.*', 'u.name as staff_name', 'lt.type_name as leave_type_name'])
            ->from('leaves l')
            ->leftJoin('users u', 'l.user_id', '=', 'u.user_id')
            ->leftJoin('leave_types lt', 'l.leave_type_id', '=', 'lt.leave_type_id')
            ->where('l.status', $status)
            ->orderBy('l.start_date', 'DESC')
            ->get();
    }
    
    public function create($data) {
        return $this->query()
            ->insert($data);
    }
    
    public function updateLeave($leaveId, $data) {
        return $this->query()
            ->where('leave_id', $leaveId)
            ->update($data);
    }
    
    public function deleteLeave($leaveId) {
        return $this->query()
            ->where('leave_id', $leaveId)
            ->delete();
    }
    
    public function approveLeave($leaveId, $approvedBy) {
        return $this->query()
            ->where('leave_id', $leaveId)
            ->update([
                'status' => 'APPROVED',
                'approved_by' => $approvedBy,
                'approved_at' => date('Y-m-d H:i:s')
            ]);
    }
    
    public function rejectLeave($leaveId, $approvedBy) {
        return $this->query()
            ->where('leave_id', $leaveId)
            ->update([
                'status' => 'REJECTED',
                'approved_by' => $approvedBy,
                'approved_at' => date('Y-m-d H:i:s')
            ]);
    }
    
    /**
     * Calculate total days between start and end date (inclusive)
     */
    public function calculateDays($startDate, $endDate) {
        $start = new \DateTime($startDate);
        $end = new \DateTime($endDate);
        $end->modify('+1 day'); // Include end date
        $interval = $start->diff($end);
        return $interval->days;
    }
}

