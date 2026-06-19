<?php
/**
 * QORDY WebSocket Server - Production
 * 
 * Features:
 * - Ratchet WebSocket server on port 8080
 * - DB polling every 5 seconds for order/table/notification changes
 * - Auto-broadcast to subscribed business channels
 * - PID file management to prevent duplicate instances
 * - Keepalive every 30 seconds
 * 
 * Run: /opt/plesk/php/8.3/bin/php app/websocket/server.php
 */

if (php_sapi_name() !== 'cli') {
    die("CLI only\n");
}

define('WEBSOCKET_SERVER', true);

error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/websocket-server.log');

$port = 8080;
$pidFile = __DIR__ . '/../../storage/websocket.pid';
$myPid = getmypid();

// === PID FILE: Clean stale entries ===
if (file_exists($pidFile)) {
    $oldPid = (int) trim(file_get_contents($pidFile));
    if ($oldPid > 0 && $oldPid !== $myPid && file_exists("/proc/{$oldPid}")) {
        echo "[CLEANUP] Killing old server PID {$oldPid}\n";
        posix_kill($oldPid, SIGTERM);
        sleep(2);
        if (file_exists("/proc/{$oldPid}")) posix_kill($oldPid, SIGKILL);
        sleep(1);
    }
    @unlink($pidFile);
}

// === PORT CHECK: Retry up to 5 times ===
$portOk = false;
for ($attempt = 1; $attempt <= 5; $attempt++) {
    $sock = @stream_socket_server("tcp://0.0.0.0:{$port}", $errno, $errstr, STREAM_SERVER_BIND);
    if ($sock !== false) {
        fclose($sock);
        $portOk = true;
        break;
    }
    echo "[WAIT] Port {$port} busy, killing occupants... (attempt {$attempt}/5)\n";
    $lsofOutput = @shell_exec("lsof -ti :{$port} 2>/dev/null");
    if ($lsofOutput) {
        foreach (array_filter(array_map('trim', explode("\n", $lsofOutput))) as $p) {
            if ((int)$p !== $myPid && (int)$p > 0) {
                posix_kill((int)$p, $attempt >= 3 ? SIGKILL : SIGTERM);
            }
        }
    }
    sleep(2);
}
if (!$portOk) {
    echo "[ABORT] Port {$port} still in use after 5 attempts\n";
    exit(1);
}

// Write PID file
file_put_contents($pidFile, $myPid);

// Clean up PID file on exit
register_shutdown_function(function () use ($pidFile, $myPid) {
    if (file_exists($pidFile) && (int)trim(file_get_contents($pidFile)) === $myPid) {
        @unlink($pidFile);
    }
});

// Handle signals - only SIGTERM for graceful shutdown (systemctl stop)
// SIGINT is ignored: PM2 God daemon sends SIGINT when it crashes/restarts,
// but we want the WS server to survive PM2 daemon restarts.
if (function_exists('pcntl_signal')) {
    pcntl_async_signals(true);
    
    pcntl_signal(SIGTERM, function ($signo) use ($pidFile, $myPid) {
        echo "\n[STOP] SIGTERM received, shutting down...\n";
        if (file_exists($pidFile) && (int)trim(file_get_contents($pidFile)) === $myPid) {
            @unlink($pidFile);
        }
        exit(0);
    });
    
    // Ignore SIGINT (PM2 daemon crash sends this)
    pcntl_signal(SIGINT, SIG_IGN);
    // Ignore SIGHUP (terminal close)
    pcntl_signal(SIGHUP, SIG_IGN);
}

require_once __DIR__ . '/../../vendor/autoload.php';

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use App\websocket\WebSocketHandler;

echo "=== QORDY WebSocket Server ===\n";
echo "Starting on port {$port}...\n";
echo "PID: {$myPid}\n";

$handler = new WebSocketHandler();
$wsServer = new WsServer($handler);

$server = IoServer::factory(
    new HttpServer($wsServer),
    $port,
    '0.0.0.0'
);

try {
    $wsServer->enableKeepAlive($server->loop, 30);
    echo "[OK] KeepAlive enabled (30s)\n";
} catch (\Throwable $e) {
    echo "[WARN] KeepAlive: {$e->getMessage()}\n";
}

// DB polling timer - every 5 seconds (was 3, reduced load)
$server->loop->addPeriodicTimer(5, function () use ($handler) {
    try {
        $handler->pollDatabaseChanges();
    } catch (\Throwable $e) {
        echo "[WS] Poll timer error: {$e->getMessage()}\n";
    }
});
echo "[OK] DB polling timer enabled (5s)\n";

// Stats timer - every 5 minutes (was 60s, less spam)
$server->loop->addPeriodicTimer(300, function () use ($handler) {
    $stats = $handler->getStats();
    echo "[STATS] conns:{$stats['total_connections']} channels:{$stats['business_channels']} bridges:{$stats['print_bridges']}\n";
});

echo "[OK] Server running on 0.0.0.0:{$port}\n";
echo "Press Ctrl+C to stop\n\n";

$server->run();
