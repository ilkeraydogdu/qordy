<?php
namespace App\Models;

require_once __DIR__ . '/../core/Model.php';

class Notification extends \App\Core\Model {
    protected $table = 'notifications';
    
    public function getAll() {
        return $this->query()
            ->orderBy('timestamp', 'DESC')
            ->get();
    }
    
    public function getById($notificationId) {
        return $this->query()
            ->where('notification_id', $notificationId)
            ->first();
    }
    
    public function getByTable($tableId) {
        return $this->query()
            ->where('table_id', $tableId)
            ->orderBy('timestamp', 'DESC')
            ->get();
    }
    
    public function getByType($type) {
        return $this->query()
            ->where('type', $type)
            ->orderBy('timestamp', 'DESC')
            ->get();
    }
    
    public function getByDateRange($startDate, $endDate) {
        return $this->query()
            ->whereBetween('timestamp', [$startDate, $endDate])
            ->orderBy('timestamp', 'DESC')
            ->get();
    }
    
    public function create($data) {
        return $this->query()
            ->insert($data);
    }

    public function updateNotification($notificationId, $data) {
        return $this->query()
            ->where('notification_id', $notificationId)
            ->update($data);
    }

    public function deleteNotification($notificationId) {
        return $this->query()
            ->where('notification_id', $notificationId)
            ->delete();
    }
    
    public function markAsRead($notificationId) {
        return $this->query()
            ->where('notification_id', $notificationId)
            ->update(['is_read' => 1]);
    }
    
    public function markAllAsRead() {
        return $this->query()
            ->where('is_read', 0)
            ->update(['is_read' => 1]);
    }
    
    public function markTableNotificationsAsRead($tableId) {
        return $this->query()
            ->where('table_id', $tableId)
            ->where('is_read', 0)
            ->update(['is_read' => 1]);
    }
    
    public function getUnreadCount() {
        return $this->query()
            ->where('is_read', 0)
            ->count();
    }
    
    public function getUnreadByTable($tableId) {
        return $this->query()
            ->where('table_id', $tableId)
            ->where('is_read', 0)
            ->count();
    }
    
    public function getRecent($limit = 10) {
        return $this->query()
            ->orderBy('timestamp', 'DESC')
            ->limit((int)$limit)
            ->get();
    }
    
    public function getUnreadNotifications() {
        return $this->query()
            ->where('is_read', 0)
            ->orderBy('timestamp', 'DESC')
            ->get();
    }
}