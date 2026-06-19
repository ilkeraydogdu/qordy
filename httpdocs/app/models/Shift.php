<?php
namespace App\Models;

require_once __DIR__ . '/../core/Model.php';

class Shift extends \App\Core\Model {
    protected $table = 'shifts';
    
    public function getAll() {
        return $this->query()
            ->select(['s.*', 'u.name as staff_name'])
            ->from('shifts s')
            ->leftJoin('users u', 's.staff_id', '=', 'u.user_id')
            ->orderBy('s.start_time', 'DESC')
            ->get();
    }
    
    public function getById($shiftId) {
        return $this->query()
            ->select(['s.*', 'u.name as staff_name'])
            ->from('shifts s')
            ->leftJoin('users u', 's.staff_id', '=', 'u.user_id')
            ->where('s.shift_id', $shiftId)
            ->first();
    }
    
    public function getByStaff($staffId) {
        try {
            return $this->query()
                ->where('staff_id', $staffId)
                ->orderBy('start_time', 'DESC')
                ->get();
        } catch (\Exception $e) {
            // Table doesn't exist or other error
            error_log("Shift::getByStaff - Error: " . $e->getMessage());
            return [];
        }
    }
    
    public function getByDateRange($startDate, $endDate) {
        return $this->query()
            ->select(['s.*', 'u.name as staff_name'])
            ->from('shifts s')
            ->leftJoin('users u', 's.staff_id', '=', 'u.user_id')
            ->whereBetween('s.start_time', [$startDate, $endDate])
            ->orderBy('s.start_time', 'DESC')
            ->get();
    }
    
    public function create($data) {
        return $this->query()
            ->insert($data);
    }

    public function updateShift($shiftId, $data) {
        return $this->query()
            ->where('shift_id', $shiftId)
            ->update($data);
    }

    public function deleteShift($shiftId) {
        return $this->query()
            ->where('shift_id', $shiftId)
            ->delete();
    }
    
    public function getCurrentShift($staffId) {
        return $this->query()
            ->where('staff_id', $staffId)
            ->where('status', 'OPEN')
            ->orderBy('start_time', 'DESC')
            ->limit(1)
            ->first();
    }
    
    public function closeShift($shiftId, $closingCash, $totalSales) {
        return $this->query()
            ->where('shift_id', $shiftId)
            ->update([
                'end_time' => date('Y-m-d H:i:s'),
                'closing_cash' => $closingCash,
                'total_sales' => $totalSales,
                'status' => 'CLOSED'
            ]);
    }
    
    public function getOpenShifts() {
        return $this->query()
            ->select(['s.*', 'u.name as staff_name'])
            ->from('shifts s')
            ->leftJoin('users u', 's.staff_id', '=', 'u.user_id')
            ->where('s.status', 'OPEN')
            ->orderBy('s.start_time')
            ->get();
    }
    
    public function getDailyShiftReport($date) {
        return $this->query()
            ->select(['s.*', 'u.name as staff_name', 'COUNT(pt.id) as transaction_count', 'COALESCE(SUM(pt.amount), 0) as total_revenue'])
            ->from('shifts s')
            ->leftJoin('users u', 's.staff_id', '=', 'u.user_id')
            ->leftJoin('payment_transactions pt', 's.shift_id', '=', 'pt.shift_id')
            ->whereRaw('DATE(s.start_time) = ?', [$date])
            ->groupBy('s.shift_id')
            ->orderBy('s.start_time')
            ->get();
    }
}