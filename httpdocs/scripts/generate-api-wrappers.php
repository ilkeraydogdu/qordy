<?php
/**
 * Qordy Public API Wrapper Generator
 * APIController'ı (3620 satır, 76 method) 7 wrapper controller'a böler
 */

declare(strict_types=1);

$appPath = '/var/www/vhosts/qordy.com/httpdocs';
$source = "{$appPath}/app/controllers/APIController.php";
$content = file_get_contents($source);

preg_match_all('/public function ([a-zA-Z0-9_]+)\(/', $content, $matches);
$methods = $matches[1];
unset($methods[0]); // Remove __construct

$categories = [
 'Order' => ['order', 'cart', 'place', 'bill', 'cancel', 'request', 'waiter'],
 'Menu' => ['menu', 'category', 'item', 'product', 'ingredient'],
 'Table' => ['table', 'zone', 'floor', 'qr', 'session'],
 'Notification' => ['notification', 'mark', 'read'],
 'Settings' => ['setting', 'config', 'preference', 'option', 'getSettings', 'updateSettings'],
 'Analytics' => ['analytics', 'report', 'stats', 'metric'],
 'Auth' => ['authenticate', 'login', 'auth'],
 'Resource' => ['expense', 'supplier', 'waste', 'reservation', 'invoice', 'payment', 'transaction', 'staff', 'shift', 'orderRequest', 'orderHasKitchen'],
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
 $buckets['Resource'][] = $m;
 }
}

foreach ($buckets as $cat => $methods) {
 $count = count($methods);
 $delegations = '';
 foreach ($methods as $m) {
 $delegations .= " public function $m(): void { \$this->delegate->$m(); }\n";
 }

 $className = $cat . 'Controller';
 $file = "{$appPath}/app/controllers/API/{$className}.php";

 $template = "<?php\n/**\n * Public API {$cat} Controller (Q4 2026 Refactor — DELEGATE WRAPPER)\n *\n * Bu controller, APIController'daki {$count} {$cat} method'u organize eder.\n * Tam implementasyon Q1 2027'de yapılacak. Şimdilik delegate eder.\n */\ndeclare(strict_types=1);\nnamespace App\\Controllers\\API;\nrequire_once __DIR__ . '/../../../core/Controller.php';\nrequire_once __DIR__ . '/../APIController.php';\nuse App\\Core\\Controller;\nuse App\\Controllers\\APIController;\n\nclass {$className} extends Controller\n{\n private APIController \$delegate;\n\n public function __construct()\n {\n parent::__construct();\n \$this->delegate = new APIController();\n }\n\n{$delegations}}\n";

 file_put_contents($file, $template);
 echo " ✅ {$className}: {$count} methods\n";
}

echo "\nTotal: " . count($methods) . " methods across " . count($buckets) . " controllers.\n";
