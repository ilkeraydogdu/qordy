<?php
namespace App\Models;

require_once __DIR__ . '/../core/Model.php';

class ArchivedSession extends \App\Core\Model {
    protected $table = 'archived_sessions';
    
    public function getAll() {
        return $this->query()
            ->from('archived_sessions')
            ->orderBy('start_time', 'DESC')
            ->get();
    }
    
    public function getById($sessionId) {
        return $this->query()
            ->from('archived_sessions')
            ->where('session_id', $sessionId)
            ->first();
    }
    
    public function getByTable($tableId) {
        return $this->query()
            ->from('archived_sessions')
            ->where('table_id', $tableId)
            ->orderBy('start_time', 'DESC')
            ->get();
    }
    
    public function getByDateRange($startDate, $endDate) {
        return $this->query()
            ->from('archived_sessions')
            ->whereBetween('start_time', [$startDate, $endDate])
            ->orderBy('start_time', 'DESC')
            ->get();
    }
    
    public function create($data) {
        return $this->query()
            ->from('archived_sessions')
            ->insert($data);
    }

    public function updateSession($sessionId, $data) {
        return $this->query()
            ->from('archived_sessions')
            ->where('session_id', $sessionId)
            ->update($data);
    }

    public function deleteSession($sessionId) {
        return $this->query()
            ->from('archived_sessions')
            ->where('session_id', $sessionId)
            ->delete();
    }
    
    public function getDailyRevenue($date) {
        $result = $this->query()
            ->from('archived_sessions')
            ->whereRaw('DATE(start_time) = ?', [$date])
            ->sum('total_revenue');
        return (float)($result ?: 0);
    }
    
    public function getDailyTips($date) {
        $result = $this->query()
            ->from('archived_sessions')
            ->whereRaw('DATE(start_time) = ?', [$date])
            ->sum('total_tip');
        return (float)($result ?: 0);
    }
    
    public function getMonthlyRevenue($year, $month) {
        $result = $this->query()
            ->from('archived_sessions')
            ->whereRaw('YEAR(start_time) = ? AND MONTH(start_time) = ?', [$year, $month])
            ->sum('total_revenue');
        return (float)($result ?: 0);
    }
    
    public function getTopEarningTables($limit = 5) {
        return $this->query()
            ->select(['table_name', 'COUNT(*) as session_count', 'SUM(total_revenue) as total_revenue'])
            ->from('archived_sessions')
            ->groupBy('table_name')
            ->orderBy('total_revenue', 'DESC')
            ->limit((int)$limit)
            ->get();
    }
    
    public function getAverageSessionDuration() {
        $result = $this->query()
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, start_time, end_time)) as avg_duration')
            ->from('archived_sessions')
            ->whereNotNull('end_time')
            ->first();
        return $result ? (float)($result['avg_duration'] ?? 0) : 0;
    }
}