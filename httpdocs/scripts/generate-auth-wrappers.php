<?php
/**
 * AuthController Wrapper Generator
 * 19 method -> 4 wrapper (Public, Admin, Qodmin, Register, TwoFA)
 */

$appPath = '/var/www/vhosts/qordy.com/httpdocs';
$source = "{$appPath}/app/controllers/AuthController.php";
$content = file_get_contents($source);

preg_match_all('/public function ([a-zA-Z0-9_]+)\(/', $content, $matches);
$methods = array_filter($matches[1], fn($m) => $m !== '__construct');

$categories = [
 'PublicAuth' => ['publicLogin', 'login', 'index', 'logout', 'apiCheckAuth', 'unauthorized', 'refreshCsrfToken'],
 'QodminAuth' => ['qodminLogin', 'qodmin', 'show2FAVerify', 'verify2FA', 'switch2FAMethod', 'resend2FACode'],
 'Register' => ['register', 'checkSubdomainAvailability', 'sendRegisterEmailCode', 'verifyRegisterEmail', 'sendRegisterPhoneCode', 'verifyRegisterPhone'],
 'TwoFA' => ['enable2FA', 'disable2FA', 'send2FACode', 'verify2FA', 'verify2FAChallenge', 'send2FAChallengeCode'],
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
 $buckets['PublicAuth'][] = $m; // default bucket
 }
}

foreach ($buckets as $cat => $methods) {
 $count = count($methods);
 $delegations = '';
 foreach ($methods as $m) {
 $delegations .= " public function $m(): void { \$this->delegate->$m(); }\n";
 }

 $className = $cat . 'Controller';
 $file = "{$appPath}/app/controllers/auth/{$className}.php";

 $dir = dirname($file);
 if (!is_dir($dir)) {
 mkdir($dir, 0755, true);
 }

 $template = "<?php\n/**\n * Auth {$cat} Controller (Q4 2026 — DELEGATE)\n *\n * {$count} method delegated to AuthController.\n */\ndeclare(strict_types=1);\nnamespace App\\Controllers\\auth;\nrequire_once __DIR__ . '/../../core/Controller.php';\nrequire_once __DIR__ . '/../AuthController.php';\nuse App\\Core\\Controller;\nuse App\\Controllers\\AuthController;\n\nclass {$className} extends Controller\n{\n private AuthController \$delegate;\n public function __construct() { parent::__construct(); \$this->delegate = new AuthController(); }\n{$delegations}}\n";

 file_put_contents($file, $template);
 echo " ✅ auth/{$className}.php: {$count} methods\n";
}

echo "\nTotal: " . count($methods) . " methods.\n";