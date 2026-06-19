<?php
/**
 * MenuController Wrapper Generator
 * 20 method -> 4 wrapper
 */

$appPath = '/var/www/vhosts/qordy.com/httpdocs';
$source = "{$appPath}/app/controllers/MenuController.php";
$content = file_get_contents($source);

preg_match_all('/public function ([a-zA-Z0-9_]+)\(/', $content, $matches);
$methods = array_filter($matches[1], fn($m) => $m !== '__construct');

$categories = [
 'Item' => ['add', 'edit', 'delete', 'item', 'availability', 'stock', 'price', 'translateProductName', 'getProductStockHistory'],
 'Category' => ['category', 'addCategory', 'editCategory', 'deleteCategory', 'translateCategoryName'],
 'Extract' => ['extractMenuFromImage', 'bulkAddFromExtraction', 'translate'],
 'Index' => ['index', 'fixPreparationScreens', 'getMenuItem', 'getMenuItemTranslations'],
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
 $buckets['Index'][] = $m;
 }
}

foreach ($buckets as $cat => $methods) {
 $count = count($methods);
 $delegations = '';
 foreach ($methods as $m) {
 $delegations .= " public function $m(): void { \$this->delegate->$m(); }\n";
 }

 $className = $cat . 'Controller';
 $file = "{$appPath}/app/controllers/admin/menu/{$className}.php";

 $dir = dirname($file);
 if (!is_dir($dir)) {
 mkdir($dir, 0755, true);
 }

 $template = "<?php\n/**\n * Admin Menu {$cat} Controller (Q4 2026 — DELEGATE)\n */\ndeclare(strict_types=1);\nnamespace App\\Controllers\\admin\\menu;\nrequire_once __DIR__ . '/../../../core/Controller.php';\nrequire_once __DIR__ . '/../../MenuController.php';\nuse App\\Core\\Controller;\nuse App\\Controllers\\MenuController;\n\nclass {$className} extends Controller\n{\n private MenuController \$delegate;\n public function __construct() { parent::__construct(); \$this->delegate = new MenuController(); }\n{$delegations}}\n";

 file_put_contents($file, $template);
 echo " ✅ admin/menu/{$className}.php: {$count} methods\n";
}

echo "\nTotal: " . count($methods) . " methods.\n";