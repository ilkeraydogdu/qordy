<?php
/**
 * Cleanup old notifications (run every hour via cron)
 * Crontab: 0 * * * * php /var/www/vhosts/qordy.com/httpdocs/app/cron/cleanup_notifications.php
 */

// Bootstrap the application
require_once __DIR__ . '/../core/bootstrap.php';

try {
    $notificationService = \App\Core\DependencyFactory::getNotificationService();
    $deleted = $notificationService->cleanupOldNotifications();
    
    echo "[" . date('Y-m-d H:i:s') . "] Cleaned up {$deleted} old notifications\n";
    
    if (class_exists('\App\Core\Logger')) {
        \App\Core\Logger::info('Notification cleanup completed', [
            'deleted_count' => $deleted,
            'timestamp' => time()
        ]);
    }
} catch (\Exception $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
    
    if (class_exists('\App\Core\Logger')) {
        \App\Core\Logger::error('Notification cleanup failed', [
            'error' => $e->getMessage()
        ]);
    }
}
