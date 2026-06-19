<?php
// Recover Timed-Out Print Queue Items
// 
// This script should be run via cron every 5 minutes to recover:
// - PRINTING items older than 5 minutes (reset to PENDING)
// - FAILED items with retry_count < 3 (reset to PENDING for retry)
// - PENDING/FAILED items older than 4 hours (expire them)
// 
// Cron example:
// */5 * * * * /usr/bin/php /var/www/vhosts/qordy.com/httpdocs/scripts/recover_print_queue_timeouts.php

require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/core/DependencyFactory.php';

use App\Services\PrinterBridgeService;

echo "[" . date('Y-m-d H:i:s') . "] Starting print queue timeout recovery...\n";

try {
    $printerBridgeService = DependencyFactory::getPrinterBridgeService();
    $result = $printerBridgeService->recoverTimedOutItems();
    
    echo "[" . date('Y-m-d H:i:s') . "] Recovery completed:\n";
    echo "  - Stale locks reset: " . $result['stale_locks_reset'] . "\n";
    echo "  - Failed items reset: " . $result['failed_items_reset'] . "\n";
    echo "  - Expired old items: " . ($result['expired_old_items'] ?? 0) . "\n";
    echo "  - Total recovered: " . $result['total_recovered'] . "\n";
    
    $totalActions = $result['total_recovered'] + ($result['expired_old_items'] ?? 0);
    if ($totalActions > 0) {
        echo "[" . date('Y-m-d H:i:s') . "] ✓ Successfully processed " . $totalActions . " items\n";
    } else {
        echo "[" . date('Y-m-d H:i:s') . "] ✓ No items needed recovery\n";
    }
    
    exit(0);
} catch (\Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ✗ Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}

