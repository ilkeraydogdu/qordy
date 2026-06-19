<?php
/**
 * Qordy God-File Refactor Scaffold Generator
 *
 * Bu script, MobileAPIController.php (6600 satır, 152 method) gibi büyük
 * controller'ları kategorize ederek yeni controller'lara scaffold oluşturur.
 *
 * Kullanım:
 * php scripts/scaffold-godfile-refactor.php MobileAPIController
 */

declare(strict_types=1);

$sourceFile = $argv[1] ?? null;
if (!$sourceFile) {
 fwrite(STDERR, "Usage: php scaffold-godfile-refactor.php <ControllerName>\n");
 exit(1);
}

$appPath = '/var/www/vhosts/qordy.com/httpdocs';
$source = "{$appPath}/app/controllers/{$sourceFile}.php";
if (!file_exists($source)) {
 // Try API/ subdir
 $source = "{$appPath}/app/controllers/API/{$sourceFile}.php";
}
if (!file_exists($source)) {
 fwrite(STDERR, "File not found: $source\n");
 exit(1);
}

echo "Reading $source...\n";
$content = file_get_contents($source);

// Method extraction
preg_match_all('/public function ([a-zA-Z0-9_]+)\(/', $content, $matches);
$methods = $matches[1];
echo "Found " . count($methods) . " public methods.\n\n";

// Heuristic categorization
$categories = [
 'Auth' => ['login', 'logout', 'token', 'session', 'auth', '2fa', 'totp', 'whatsapp', 'verify', 'refresh', 'register'],
 'Order' => ['order', 'cart', 'checkout', 'payment', 'place'],
 'Menu' => ['menu', 'category', 'item', 'product', 'catalog'],
 'Table' => ['table', 'zone', 'floor', 'qr'],
 'Staff' => ['staff', 'employee', 'shift', 'schedule', 'leave', 'personnel'],
 'Customer' => ['customer', 'user', 'profile', 'account'],
 'Notification' => ['notification', 'push', 'email', 'sms', 'message'],
 'Report' => ['report', 'analytics', 'stats', 'metric', 'export'],
 'Settings' => ['setting', 'config', 'preference', 'option'],
 'Error' => ['error', 'log', 'debug'],
 'System' => ['system', 'health', 'status', 'version'],
];

$buckets = [];
foreach ($methods as $m) {
 $mLower = strtolower($m);
 $assigned = false;
 foreach ($categories as $cat => $keywords) {
 foreach ($keywords as $kw) {
 if (strpos($mLower, $kw) !== false) {
 $buckets[$cat][] = $m;
 $assigned = true;
 break 2;
 }
 }
 }
 if (!$assigned) {
 $buckets['Other'][] = $m;
 }
}

echo "=== Method Categories ===\n";
foreach ($buckets as $cat => $methods) {
 echo "\n[$cat] (" . count($methods) . " methods):\n";
 foreach ($methods as $m) {
 echo " - $m\n";
 }
}

echo "\n=== Recommended File Splits ===\n";
foreach ($buckets as $cat => $methods) {
 if (count($methods) > 1) {
 $targetFile = "{$appPath}/app/controllers/API/Mobile/{$cat}Controller.php";
 echo " {$cat}Controller: " . count($methods) . " methods → $targetFile\n";
 }
}
