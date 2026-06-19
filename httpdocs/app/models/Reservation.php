<?php
namespace App\Models;

require_once __DIR__ . '/../core/Model.php';

class Reservation extends \App\Core\Model {
    protected $table = 'reservations';
    
    public function getAll() {
        return $this->query()
            ->select(['r.*', 't.name as table_name'])
            ->from('reservations r')
            ->leftJoin('tables t', 'r.table_id', '=', 't.table_id')
            ->orderBy('r.date')
            ->orderBy('r.time')
            ->get();
    }
    
    public function getById($reservationId) {
        return $this->query()
            ->select(['r.*', 't.name as table_name'])
            ->from('reservations r')
            ->leftJoin('tables t', 'r.table_id', '=', 't.table_id')
            ->where('r.reservation_id', $reservationId)
            ->first();
    }
    
    public function getByTable($tableId) {
        return $this->query()
            ->where('table_id', $tableId)
            ->orderBy('date')
            ->orderBy('time')
            ->get();
    }
    
    public function getByDate($date) {
        return $this->query()
            ->select(['r.*', 't.name as table_name'])
            ->from('reservations r')
            ->leftJoin('tables t', 'r.table_id', '=', 't.table_id')
            ->whereRaw('DATE(r.date) = ?', [$date])
            ->orderBy('r.time')
            ->get();
    }
    
    public function getByDateRange($startDate, $endDate) {
        return $this->query()
            ->select(['r.*', 't.name as table_name'])
            ->from('reservations r')
            ->leftJoin('tables t', 'r.table_id', '=', 't.table_id')
            ->whereBetween('r.date', [$startDate, $endDate])
            ->orderBy('r.date')
            ->orderBy('r.time')
            ->get();
    }
    
    /**
     * Get table (belongsTo relationship)
     * @param string $tableId
     * @return array|null
     */
    public function getTable(string $tableId): ?array {
        require_once __DIR__ . '/Table.php';
        $tableModel = new \App\Models\Table();
        return $tableModel->getById($tableId);
    }
    
    public function create($data) {
        return $this->query()
            ->insert($data);
    }

    public function updateReservation($reservationId, $data) {
        return $this->query()
            ->where('reservation_id', $reservationId)
            ->update($data);
    }

    public function deleteReservation($reservationId) {
        return $this->query()
            ->where('reservation_id', $reservationId)
            ->delete();
    }
    
    public function getTodayReservations() {
        return $this->query()
            ->select(['r.*', 't.name as table_name'])
            ->from('reservations r')
            ->leftJoin('tables t', 'r.table_id', '=', 't.table_id')
            ->whereRaw('DATE(r.date) = CURDATE()')
            ->orderBy('r.time')
            ->get();
    }
    
    public function getUpcomingReservations() {
        return $this->query()
            ->select(['r.*', 't.name as table_name'])
            ->from('reservations r')
            ->leftJoin('tables t', 'r.table_id', '=', 't.table_id')
            ->whereRaw('r.date >= CURDATE()')
            ->orderBy('r.date')
            ->orderBy('r.time')
            ->get();
    }
    
    public function isTableAvailable($tableId, $date, $time, $reservationId = '') {
        $count = $this->query()
            ->where('table_id', $tableId)
            ->where('date', $date)
            ->where('time', $time);
        
        if (!empty($reservationId)) {
            $count->where('reservation_id', '!=', $reservationId);
        }
        
        return (int)$count->count() === 0;
    }
}