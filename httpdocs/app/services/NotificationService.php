<?php
namespace App\Services;

require_once __DIR__ . '/../models/Notification.php';
require_once __DIR__ . '/../helpers/sounds.php';

/**
 * Notification Service - MVC, OOP, Centralized Notification Management
 * Handles all notification operations in a centralized way
 */
use App\Repositories\NotificationRepository;

class NotificationService {
    private $repository;
    private $soundEnabled = true;
    
    public function __construct(NotificationRepository $repository) {
        $this->repository = $repository;
    }
    
    /**
     * Create a notification
     * @param string $type - Notification type (NEW_ORDER, CALL_WAITER, etc.)
     * @param string $tableId - Table ID
     * @param string $tableName - Table name
     * @param array $data - Additional data
     * @param bool $playSound - Whether to play sound
     * @return bool|string - Notification ID on success, false on failure
     */
    // Valid notification types
    private const VALID_TYPES = [
        'NEW_ORDER', 'ORDER_READY', 'CALL_WAITER', 'REQUEST_BILL', 
        'CANCEL_ORDER', 'KITCHEN_ISSUE', 'EDIT_APPROVAL', 'ORDER_EDIT_APPROVAL',
        'TABLE_TRANSFER', 'PAYMENT_RECEIVED', 'ORDER_SERVED', 'SYSTEM'
    ];
    
    public function create($type, $tableId, $tableName, $data = [], $playSound = true) {
        require_once __DIR__ . '/../helpers/functions.php';
        
        // Validate notification type
        if (!in_array($type, self::VALID_TYPES)) {
            \App\Core\Logger::warning('NotificationService::create - Invalid notification type', [
                'type' => $type,
            ]);
            return false;
        }
        
        $now = date('Y-m-d H:i:s');
        $notificationData = [
            'notification_id' => generateId('n'),
            'type' => $type,
            'table_id' => $tableId,
            'table_name' => $tableName,
            'data' => is_array($data) ? json_encode($data) : $data,
            'timestamp' => $now,
            'created_at' => $now,
            'is_read' => 0
        ];
        
        $result = $this->repository->create($notificationData);
        
        if ($result && $playSound && $this->soundEnabled) {
            $this->playNotificationSound($type);
        }

        if ($result) {
            $this->dispatchPush($type, $tableId, $tableName, $data, $notificationData['notification_id']);
        }
        
        return $result ? $notificationData['notification_id'] : false;
    }

    /**
     * İlgili bildirim tipine göre tenant'taki doğru rollere FCM push gönderir.
     * Hatalar sessizce loglanır - bildirim üretimini asla bozmaz.
     */
    private function dispatchPush(string $type, $tableId, $tableName, $data, string $notificationId): void {
        try {
            $tenantId = null;
            try {
                if (class_exists('\\App\\Core\\TenantResolver') && method_exists('\\App\\Core\\TenantResolver', 'resolve')) {
                    $tenantId = \App\Core\TenantResolver::resolve();
                }
            } catch (\Exception $e) {}

            if (!$tenantId && !empty($tableId)) {
                try {
                    $db = \App\Core\DependencyFactory::getDatabase();
                    $stmt = $db->prepare("SELECT tenant_id FROM tables WHERE table_id = ? LIMIT 1");
                    $stmt->execute([$tableId]);
                    $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                    if ($row && !empty($row['tenant_id'])) {
                        $tenantId = $row['tenant_id'];
                    }
                } catch (\Exception $e) {}
            }

            if (!$tenantId) return;

            $roles = $this->rolesForType($type);
            if (empty($roles)) return;

            [$title, $body] = $this->copyForType($type, (string)$tableName, is_array($data) ? $data : []);

            $payload = [
                'type' => $type,
                'notification_id' => $notificationId,
                'table_id' => (string)$tableId,
                'table_name' => (string)$tableName,
                'tenant_id' => (string)$tenantId,
                'channel' => 'staff',
            ];
            if (is_array($data)) {
                foreach (['order_id', 'approval_id', 'amount'] as $k) {
                    if (!empty($data[$k])) {
                        $payload[$k] = (string)$data[$k];
                    }
                }
            }

            if (class_exists('\\App\\Core\\DependencyFactory') && method_exists('\\App\\Core\\DependencyFactory', 'getPushService')) {
                $push = \App\Core\DependencyFactory::getPushService();
            } elseif (class_exists('\\App\\Services\\PushService')) {
                $push = new \App\Services\PushService();
            } else {
                return;
            }
            $push->sendToTenantRoles($tenantId, $roles, $title, $body, $payload);
        } catch (\Exception $e) {
            if (class_exists('\\App\\Core\\Logger')) {
                \App\Core\Logger::warning('NotificationService::dispatchPush failed', [
                    'error' => $e->getMessage(), 'type' => $type, 'notification_id' => $notificationId,
                ]);
            }
        }
    }

    private function rolesForType(string $type): array {
        switch ($type) {
            case 'CALL_WAITER':
            case 'REQUEST_BILL':
            case 'ORDER_READY':
                return ['BUSINESS_OWNER', 'WAITER'];
            case 'NEW_ORDER':
                return ['BUSINESS_OWNER', 'WAITER', 'KITCHEN', 'CASHIER'];
            case 'KITCHEN_ISSUE':
                return ['BUSINESS_OWNER', 'WAITER', 'KITCHEN'];
            case 'CANCEL_ORDER':
            case 'EDIT_APPROVAL':
            case 'ORDER_EDIT_APPROVAL':
                return ['BUSINESS_OWNER', 'WAITER', 'CASHIER'];
            case 'PAYMENT_RECEIVED':
                return ['BUSINESS_OWNER', 'CASHIER'];
            case 'ORDER_SERVED':
            case 'TABLE_TRANSFER':
                return ['BUSINESS_OWNER', 'WAITER'];
            case 'SYSTEM':
                return ['BUSINESS_OWNER'];
        }
        return ['BUSINESS_OWNER'];
    }

    private function copyForType(string $type, string $tableName, array $data): array {
        $t = $tableName !== '' ? $tableName : 'Masa';
        switch ($type) {
            case 'CALL_WAITER':
                return ["$t - Garson Çağrısı", "$t sizi çağırıyor."];
            case 'REQUEST_BILL':
                return ["$t - Hesap İsteniyor", "$t hesap talep etti."];
            case 'NEW_ORDER':
                return ["$t - Yeni Sipariş", "$t yeni sipariş verdi."];
            case 'ORDER_READY':
                return ["$t - Sipariş Hazır", "Siparişi teslim edebilirsiniz."];
            case 'KITCHEN_ISSUE':
                $issue = isset($data['issue']) ? (string)$data['issue'] : 'Mutfak bildirimi';
                return ["$t - Mutfak", $issue];
            case 'CANCEL_ORDER':
                return ["$t - İptal Talebi", "$t sipariş iptali talep etti."];
            case 'EDIT_APPROVAL':
            case 'ORDER_EDIT_APPROVAL':
                return ["$t - Onay Bekliyor", "Sipariş değişiklik onayı bekliyor."];
            case 'PAYMENT_RECEIVED':
                $amt = isset($data['amount']) ? ' (' . (string)$data['amount'] . ')' : '';
                return ["$t - Ödeme Alındı", "Ödeme tamamlandı$amt."];
            case 'ORDER_SERVED':
                return ["$t - Servis Edildi", "Sipariş servis edildi."];
            case 'TABLE_TRANSFER':
                return ["$t - Masa Transferi", "Masa transferi yapıldı."];
        }
        return ["Qordy", "Yeni bildirim: $t"];
    }
    
    /**
     * Create notification for new order
     * @param string $tableId
     * @param string $tableName
     * @param string $orderId
     * @return bool|string
     */
    public function notifyNewOrder($tableId, $tableName, $orderId) {
        return $this->create('NEW_ORDER', $tableId, $tableName, ['order_id' => $orderId], true);
    }
    
    /**
     * Create notification for order ready
     * Prevents duplicate notifications for the same order
     * @param string $tableId
     * @param string $tableName
     * @param string $orderId
     * @return bool|string
     */
    public function notifyOrderReady($tableId, $tableName, $orderId) {
        // FIXED: Check for duplicates using SQL instead of loading ALL ORDER_READY notifications
        try {
            $db = \App\Core\DependencyFactory::getDatabase();
            $stmt = $db->prepare("
                SELECT notification_id FROM notifications 
                WHERE type = 'ORDER_READY' 
                AND table_id = :table_id
                AND is_read = 0
                AND JSON_UNQUOTE(JSON_EXTRACT(data, '$.order_id')) = :order_id
                LIMIT 1
            ");
            $stmt->execute(['table_id' => $tableId, 'order_id' => $orderId]);
            $existing = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($existing) {
                return $existing['notification_id'];
            }
        } catch (\Exception $e) {
            // If JSON check fails, fall back to LIKE
            try {
                $db = \App\Core\DependencyFactory::getDatabase();
                $likePattern = '%"order_id":"' . str_replace(['%', '_', '\\'], ['\\%', '\\_', '\\\\'], $orderId) . '"%';
                $stmt = $db->prepare("
                    SELECT notification_id FROM notifications 
                    WHERE type = 'ORDER_READY' 
                    AND table_id = :table_id
                    AND is_read = 0
                    AND data LIKE :order_like
                    LIMIT 1
                ");
                $stmt->execute(['table_id' => $tableId, 'order_like' => $likePattern]);
                $existing = $stmt->fetch(\PDO::FETCH_ASSOC);
                
                if ($existing) {
                    return $existing['notification_id'];
                }
            } catch (\Exception $e2) {
                // Continue with creation if duplicate check fails
            }
        }
        
        return $this->create('ORDER_READY', $tableId, $tableName, ['order_id' => $orderId], true);
    }
    
    /**
     * Create notification for waiter call
     * Prevents duplicate notifications within a short time window
     * @param string $tableId
     * @param string $tableName
     * @param string $type - CALL_WAITER, REQUEST_BILL, or CANCEL_ORDER
     * @param array $extraData - Additional data for notification (e.g., order_id, items)
     * @return bool|string
     */
    public function notifyWaiterCall($tableId, $tableName, $type = 'CALL_WAITER', $extraData = []) {
        // Check for recent duplicate notifications (within last 30 seconds)
        // For CANCEL_ORDER, check by order_id instead of just type
        try {
            $db = \App\Core\DependencyFactory::getDatabase();
            
            if ($type === 'CANCEL_ORDER' && !empty($extraData['order_id'])) {
                // For cancel orders, check by order_id to prevent duplicate cancel requests for same order
                $stmt = $db->prepare("
                    SELECT notification_id 
                    FROM notifications 
                    WHERE type = :type 
                    AND table_id = :table_id 
                    AND JSON_EXTRACT(data, '$.order_id') = :order_id
                    AND is_read = 0
                    AND created_at > DATE_SUB(NOW(), INTERVAL 30 SECOND)
                    ORDER BY created_at DESC 
                    LIMIT 1
                ");
                $stmt->execute([
                    'type' => $type,
                    'table_id' => $tableId,
                    'order_id' => $extraData['order_id']
                ]);
            } else {
                // For other types, check by type and table_id
                $stmt = $db->prepare("
                    SELECT notification_id 
                    FROM notifications 
                    WHERE type = :type 
                    AND table_id = :table_id 
                    AND is_read = 0
                    AND created_at > DATE_SUB(NOW(), INTERVAL 30 SECOND)
                    ORDER BY created_at DESC 
                    LIMIT 1
                ");
                $stmt->execute([
                    'type' => $type,
                    'table_id' => $tableId
                ]);
            }
            
            $existing = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($existing) {
                // Return existing notification ID instead of creating duplicate
                return $existing['notification_id'];
            }
        } catch (\Exception $e) {
            // If check fails, continue with creation
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::warning('Error checking for duplicate waiter call notification', [
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        // Merge extra data with table_id
        $data = array_merge(['table_id' => $tableId], $extraData);
        return $this->create($type, $tableId, $tableName, $data, true);
    }
    
    /**
     * Create notification for kitchen issue
     * @param string $tableId
     * @param string $tableName
     * @param string $issue
     * @return bool|string
     */
    public function notifyKitchenIssue($tableId, $tableName, $issue) {
        return $this->create('KITCHEN_ISSUE', $tableId, $tableName, ['issue' => $issue], true);
    }
    
    /**
     * Mark notification as read
     * @param string $notificationId
     * @return bool
     */
    public function markAsRead($notificationId) {
        return $this->repository->markAsRead($notificationId);
    }

    /**
     * Onay/red sonrası canlı bildirimlerde ilgili EDIT_APPROVAL bildirimini okundu işaretler.
     * @param string $approvalId order_edit_approvals.approval_id
     * @return bool
     */
    public function markAsReadByApprovalId(string $approvalId): bool {
        return $this->repository->markAsReadByApprovalId($approvalId);
    }
    
    /**
     * Mark all notifications as read
     * @return bool
     */
    public function markAllAsRead() {
        return $this->repository->markAllAsRead();
    }
    
    /**
     * Mark all notifications in business day range as read (for mobile daily view)
     */
    public function markAllAsReadForBusinessDay(string $startDatetime, string $endDatetime): bool {
        return $this->repository->markAllAsReadForBusinessDay($startDatetime, $endDatetime);
    }
    
    /**
     * Get unread notifications count
     * @return int
     */
    public function getUnreadCount() {
        return $this->repository->getUnreadCount();
    }
    
    /**
     * Get notifications for current business day (by business working hours)
     * @param string $startDatetime
     * @param string $endDatetime
     * @param int|null $limit
     * @return array
     */
    public function getForBusinessDay(string $startDatetime, string $endDatetime, ?int $limit = 100) {
        return $this->repository->getForBusinessDay($startDatetime, $endDatetime, $limit);
    }
    
    /**
     * Get unread count for current business day
     */
    public function getUnreadCountForBusinessDay(string $startDatetime, string $endDatetime): int {
        return $this->repository->getUnreadCountForBusinessDay($startDatetime, $endDatetime);
    }
    
    /**
     * Get notifications by table
     * @param string $tableId
     * @return array
     */
    public function getByTable($tableId) {
        return $this->repository->getByTable($tableId);
    }
    
    /**
     * Get all notifications
     * @param int $limit
     * @return array
     */
    public function getAll($limit = null) {
 return $this->repository->getAll($limit);
 }

 /**
 * Purge notifications older than N days. Cron-friendly housekeeping.
 * @param int $days
 * @return int rows deleted
 */
 public function purgeOlderThan(int $days = 1): int {
 return $this->repository->purgeOlderThan($days);
 }

 /**
        return $this->repository->getAll($limit);
    }
    
    /**
     * Get unread notifications
     * @param int $limit
     * @return array
     */
    public function getUnread($limit = null) {
        $notifications = $this->repository->getUnread();
        if ($limit) {
            return array_slice($notifications, 0, $limit);
        }
        return $notifications;
    }
    
    /**
     * Get recent notifications
     * @param int $limit - Number of recent notifications to retrieve (default: 10)
     * @return array
     */
    public function getRecent($limit = 10) {
        return $this->repository->getAll($limit);
    }
    
    /**
     * Masa taşındığında bu masaya ait tüm talepleri (garson çağrısı, hesap, iptal vb.) yeni masaya günceller.
     * @param string $fromTableId Eski masa ID
     * @param string $toTableId Yeni masa ID
     * @return bool
     */
    public function updateTableId(string $fromTableId, string $toTableId): bool {
        return $this->repository->updateTableId($fromTableId, $toTableId);
    }
    
    /**
     * Play sound for notification type
     * @param string $type
     * @return void
     */
    private function playNotificationSound($type) {
        $soundMap = [
            'NEW_ORDER' => 'NEW_ORDER',
            'ORDER_READY' => 'SUCCESS',
            'CALL_WAITER' => 'ALERT',
            'REQUEST_BILL' => 'ALERT',
            'KITCHEN_ISSUE' => 'ALERT'
        ];
        
        $soundType = $soundMap[$type] ?? 'SUCCESS';
        
        // Sound will be played via JavaScript on frontend
        // This is just for reference
    }
    
    /**
     * Enable/disable sound notifications
     * @param bool $enabled
     * @return void
     */
    public function setSoundEnabled($enabled) {
        $this->soundEnabled = $enabled;
    }
    
    /**
     * Delete notification after it's been approved/handled
     * @param string $notificationId
     * @return bool
     */
    public function deleteNotification($notificationId) {
        return $this->repository->delete($notificationId);
    }
    
    /**
     * Approve and delete notification in one operation
     * @param string $notificationId
     * @return bool
     */
    public function approveAndDelete($notificationId) {
        // Mark as read first (for audit trail if needed)
        $this->markAsRead($notificationId);
        
        // Delete immediately - approved notifications don't need to be kept
        return $this->deleteNotification($notificationId);
    }
    
    /**
     * Auto-cleanup old notifications (older than 24 hours)
     * Call this from a cron job or periodically
     * @return int Number of deleted notifications
     */
    public function cleanupOldNotifications() {
        try {
            $db = \App\Core\DependencyFactory::getDatabase();
            // FIXED: Handle both created_at and timestamp columns, and NULL values
            $stmt = $db->prepare("
                DELETE FROM notifications 
                WHERE is_read = 1 
                AND (
                    COALESCE(created_at, timestamp) < DATE_SUB(NOW(), INTERVAL 24 HOUR)
                    OR (created_at IS NULL AND timestamp IS NULL)
                )
            ");
            $stmt->execute();
            $deletedCount = $stmt->rowCount();
            
            if ($deletedCount > 0 && class_exists('\App\Core\Logger')) {
                \App\Core\Logger::info('Cleaned up old notifications', [
                    'deleted_count' => $deletedCount
                ]);
            }
            
            return $deletedCount;
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('Failed to cleanup old notifications', [
                    'error' => $e->getMessage()
                ]);
            }
            return 0;
        }
    }
    
    /**
     * Get notifications created since a specific timestamp
     * For delta updates (only new notifications)
     * @param int $timestamp Unix timestamp
     * @return array
     */
    public function getNotificationsSince($timestamp) {
        return $this->repository->getNotificationsSince($timestamp);
    }
}

