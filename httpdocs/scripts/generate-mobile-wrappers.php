<?php
/**
 * Qordy Mobile API Wrapper Generator
 * MobileAPIController'ı 10 wrapper controller'a böler
 */

declare(strict_types=1);

$appPath = '/var/www/vhosts/qordy.com/httpdocs';
$source = "{$appPath}/app/controllers/API/MobileAPIController.php";
$content = file_get_contents($source);

preg_match_all('/public function ([a-zA-Z0-9_]+)\(/', $content, $matches);
$methods = $matches[1];
unset($methods[0]); // Remove __construct

$categories = [
 'Table' => ['table', 'zone', 'floor', 'qr', 'move'],
 'Menu' => ['menu', 'category', 'item', 'product', 'catalog', 'ingredient'],
 'Staff' => ['staff', 'employee', 'shift', 'schedule', 'leave', 'personnel', 'dashboard'],
 'Notification' => ['notification', 'push', 'mark', 'email', 'sms', 'message'],
 'Report' => ['report', 'analytics', 'stats', 'metric', 'zreport', 'export'],
 'Settings' => ['setting', 'config', 'preference', 'option', 'queue'],
 'Error' => ['error', 'log', 'debug'],
 'System' => ['kitchen', 'preparation', 'subscription', 'ticket', 'transferto'],
 'Resource' => ['expense', 'package', 'offer', 'stock', 'receipt', 'printer', 'prep', 'supplier', 'waste', 'pos', 'feature', 'invoice', 'reservation', 'role', 'permission', 'subdomain', 'validate'],
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
 $file = "{$appPath}/app/controllers/API/Mobile/{$className}.php";

 $template = "<?php\n/**\n * Mobile API {$cat} Controller (Q4 2026 Refactor — DELEGATE WRAPPER)\n *\n * Bu controller, MobileAPIController'daki {$count} {$cat} method'u organize eder.\n * Tam implementasyon Q1 2027'de yapılacak. Şimdilik delegate eder.\n */\ndeclare(strict_types=1);\nnamespace App\\Controllers\\API\\Mobile;\nrequire_once __DIR__ . '/../../../../core/Controller.php';\nrequire_once __DIR__ . '/../../MobileAPIController.php';\nuse App\\Core\\Controller;\nuse App\\Controllers\\API\\MobileAPIController;\n\nclass {$className} extends Controller\n{\n private MobileAPIController \$delegate;\n\n public function __construct()\n {\n parent::__construct();\n \$this->delegate = new MobileAPIController();\n }\n\n{$delegations}}\n";

 file_put_contents($file, $template);
 echo " ✅ {$className}: {$count} methods\n";
}

echo "\nTotal: " . count($methods) . " methods across " . count($buckets) . " controllers.\n";
