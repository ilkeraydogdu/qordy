<?php
/**
 * Cron: Smart Error Log Cleanup
 * Run daily: 0 3 * * * /opt/plesk/php/8.3/bin/php /var/www/vhosts/qordy.com/httpdocs/app/cron/cleanup_error_logs.php
 * 
 * Actions:
 * 1. Delete noise patterns (bot scans, expected warnings, WebSocket health checks)
 * 2. Delete resolved errors older than 7 days
 * 3. Delete all errors older than 30 days
 * 4. Optimize table after mass deletes
 */

$envFile = __DIR__ . '/../../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos(trim($line), '=') === false) continue;
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
            (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
            $value = substr($value, 1, -1);
        }
        $_ENV[$name] = $value;
        putenv("$name=$value");
    }
}

require_once __DIR__ . '/../config/database.php';

$startTime = microtime(true);
echo "[" . date('Y-m-d H:i:s') . "] Starting error log cleanup...\n";

try {
    $database = new \App\Config\Database();
    $db = $database->connect();

    $totalDeleted = 0;

    // 1. Delete noise from php_error_logs
    $noisePatterns = [
        'Route not found: %/ws%',
        '404 Error: Route not found: %/ws%',
        '%wp-admin%',
        '%wp-login%',
        '%wordpress%',
        '%wp-includes%',
        '%wp-content%',
        '%xmlrpc%',
        '%phpmyadmin%',
        '%adminer%',
        '%.env%',
        '%.git%',
        'Authorization: Package permission denied%',
        'QR access denied - tenant mismatch%',
        'Authorization: User not logged in for permission check%',
        'ReceiptService::generateReceipt - No order items found%',
        'ReceiptService::generateReceipt duplicate receipt_number retry%',
        'CSRF token validation failed%',
        '%Route not found: GET /favicon.ico%',
        '%Route not found: GET /robots.txt%',
        '%Route not found: GET /sitemap%',
        '%169.254.169.254%',
        '%meta-data/iam%',
        '%.well-known/assetlinks%',
        '%.well-known/apple-app-site%',
        '%generatePreparationReceipt - Dedup check failed%',
        'BaseRepository::create - Removed non-existent columns%',
        'UserRepository::findByPin - No matching user found%',
        'Authentication failed: Invalid PIN%',
        'PIN login failed - authentication returned false%',
    ];

    $noiseDeleted = 0;
    foreach ($noisePatterns as $pattern) {
        $stmt = $db->prepare("DELETE FROM php_error_logs WHERE message LIKE :pattern");
        $stmt->execute(['pattern' => $pattern]);
        $noiseDeleted += $stmt->rowCount();
    }
    echo "  Noise patterns deleted: $noiseDeleted\n";
    $totalDeleted += $noiseDeleted;

    // 2. Delete resolved errors older than 7 days
    $stmt = $db->prepare("DELETE FROM php_error_logs WHERE resolved_at IS NOT NULL AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $stmt->execute();
    $resolvedDeleted = $stmt->rowCount();
    echo "  Old resolved errors deleted: $resolvedDeleted\n";
    $totalDeleted += $resolvedDeleted;

    // 3. Delete ALL errors older than 30 days
    $stmt = $db->prepare("DELETE FROM php_error_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $stmt->execute();
    $oldDeleted = $stmt->rowCount();
    echo "  Errors older than 30 days deleted: $oldDeleted\n";
    $totalDeleted += $oldDeleted;

    // 4. Same for javascript_error_logs
    $jsNoiseDeleted = 0;
    try {
        $stmt = $db->prepare("DELETE FROM javascript_error_logs WHERE resolved_at IS NOT NULL AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $stmt->execute();
        $jsNoiseDeleted += $stmt->rowCount();

        $stmt = $db->prepare("DELETE FROM javascript_error_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $stmt->execute();
        $jsNoiseDeleted += $stmt->rowCount();
    } catch (\PDOException $e) {
        echo "  JS cleanup skipped: " . $e->getMessage() . "\n";
    }
    echo "  JavaScript old errors deleted: $jsNoiseDeleted\n";
    $totalDeleted += $jsNoiseDeleted;

    // 5. Optimize tables after mass deletes
    if ($totalDeleted > 100) {
        try {
            $db->query("OPTIMIZE TABLE php_error_logs")->closeCursor();
            echo "  Optimized php_error_logs table\n";
        } catch (\PDOException $e) {
            echo "  php_error_logs optimize skipped\n";
        }
        try {
            $db->query("OPTIMIZE TABLE javascript_error_logs")->closeCursor();
            echo "  Optimized javascript_error_logs table\n";
        } catch (\PDOException $e) {
            echo "  javascript_error_logs optimize skipped\n";
        }
    }

    $remaining = $db->query("SELECT COUNT(*) FROM php_error_logs")->fetchColumn();
    $elapsed = round((microtime(true) - $startTime) * 1000);

    echo "\n[" . date('Y-m-d H:i:s') . "] Cleanup completed!\n";
    echo "  Total deleted: $totalDeleted\n";
    echo "  Remaining rows: $remaining\n";
    echo "  Duration: {$elapsed}ms\n";

} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
