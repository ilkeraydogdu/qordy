<?php
/**
 * Cron Job: Weekly Shift Schedule Notifier
 *
 * Her Pazar 18:00'de tüm tenant'lar için çalıştırılır:
 *   0 18 * * 0 /opt/plesk/php/8.3/bin/php /var/www/vhosts/qordy.com/httpdocs/app/scripts/notify_weekly_schedules.php >> /var/log/qordy-cron.log 2>&1
 *
 * İşlevleri:
 * 1. Aktif tüm tenant'ları loop'lar (tenants / businesses tablosu).
 * 2. Her tenant için TenantContext kurar, üst haftanın Pazartesi→Pazar
 *    aralığındaki shift_schedules kayıtlarını personele özel olarak
 *    WhatsApp / Email / Push / In-app kanallarına gönderir.
 */

$envFile = __DIR__ . '/../../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0 || strpos($line, '=') === false) continue;
        [$name, $value] = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
            (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
            $value = substr($value, 1, -1);
        }
        $_ENV[$name] = $value;
    }
}

require_once __DIR__ . '/../config/config.php';

echo '[' . date('Y-m-d H:i:s') . "] Weekly schedule notifier started\n";

try {
    $db = \App\Core\DependencyFactory::getDatabase();
    $pdo = $db instanceof \PDO ? $db : (method_exists($db, 'getPdo') ? $db->getPdo() : $db);

    $tenants = [];
    try {
        $stmt = $pdo->query("SELECT DISTINCT tenant_id FROM businesses WHERE tenant_id IS NOT NULL AND tenant_id <> '' LIMIT 1000");
        if ($stmt) {
            $tenants = $stmt->fetchAll(\PDO::FETCH_COLUMN) ?: [];
        }
    } catch (\Throwable $e) {
        // schema drift — fall back to `tenants.id`
        try {
            $stmt = $pdo->query("SELECT id FROM tenants LIMIT 1000");
            if ($stmt) {
                $tenants = $stmt->fetchAll(\PDO::FETCH_COLUMN) ?: [];
            }
        } catch (\Throwable $ignored) { /* ignore */ }
    }

    if (empty($tenants)) {
        echo "  No tenants found — skipping.\n";
        exit(0);
    }

    $notifier = \App\Core\DependencyFactory::getWeeklyScheduleNotifier();

    $totalStaff = 0;
    foreach ($tenants as $tenantId) {
        if (!is_string($tenantId) || $tenantId === '') continue;
        try {
            \App\Core\TenantContext::setId($tenantId);
            $log = $notifier->notifyUpcomingWeek();
            $totalStaff += count($log);
            echo "  tenant={$tenantId} staff_notified=" . count($log) . "\n";
        } catch (\Throwable $e) {
            echo "  tenant={$tenantId} ERROR: " . $e->getMessage() . "\n";
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('notify_weekly_schedules: tenant failed', [
                    'tenant_id' => $tenantId,
                    'error'     => $e->getMessage(),
                ]);
            }
        } finally {
            \App\Core\TenantContext::clear();
        }
    }

    echo "  tenants=" . count($tenants) . " total_staff_notified={$totalStaff}\n";
} catch (\Throwable $e) {
    echo '  FATAL: ' . $e->getMessage() . "\n";
    if (class_exists('\App\Core\Logger')) {
        \App\Core\Logger::error('notify_weekly_schedules: fatal', ['error' => $e->getMessage()]);
    }
    exit(1);
}

echo '[' . date('Y-m-d H:i:s') . "] Weekly schedule notifier finished\n";
