<?php
namespace App\Models;

require_once __DIR__ . '/../core/Model.php';

class MedicalReport extends \App\Core\Model {
    protected $table = 'medical_reports';
    
    public function getAll() {
        return $this->query()
            ->select(['mr.*', 'u.name as staff_name'])
            ->from('medical_reports mr')
            ->leftJoin('users u', 'mr.user_id', '=', 'u.user_id')
            ->orderBy('mr.start_date', 'DESC')
            ->get();
    }
    
    public function getById($reportId) {
        return $this->query()
            ->select(['mr.*', 'u.name as staff_name'])
            ->from('medical_reports mr')
            ->leftJoin('users u', 'mr.user_id', '=', 'u.user_id')
            ->where('mr.report_id', $reportId)
            ->first();
    }
    
    public function getByUser($userId) {
        return $this->query()
            ->from('medical_reports')
            ->where('user_id', $userId)
            ->orderBy('start_date', 'DESC')
            ->get();
    }
    
    public function getByDateRange($startDate, $endDate) {
        return $this->query()
            ->select(['mr.*', 'u.name as staff_name'])
            ->from('medical_reports mr')
            ->leftJoin('users u', 'mr.user_id', '=', 'u.user_id')
            ->whereBetween('mr.start_date', [$startDate, $endDate])
            ->orderBy('mr.start_date', 'DESC')
            ->get();
    }
    
    public function create($data) {
        return $this->query()
            ->insert($data);
    }
    
    public function updateMedicalReport($reportId, $data) {
        return $this->query()
            ->where('report_id', $reportId)
            ->update($data);
    }
    
    public function deleteMedicalReport($reportId) {
        return $this->query()
            ->where('report_id', $reportId)
            ->delete();
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

