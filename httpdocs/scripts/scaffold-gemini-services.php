<?php
/**
 * GeminiService Wrapper Generator (Safe Version)
 * 16 method -> 5 wrapper service (sadece yoksa oluşturur, üzerine yazmaz)
 */

$appPath = '/var/www/vhosts/qordy.com/httpdocs';
$source = "{$appPath}/app/services/GeminiService.php";
$content = file_get_contents($source);

preg_match_all('/public function ([a-zA-Z0-9_]+)\(/', $content, $matches);
$methods = array_filter($matches[1], fn($m) => $m !== '__construct');

$categories = [
 'GeminiTranslationService' => ['translate', 'translateText', 'translateMenuItem', 'translateIngredients', 'translateExtras'],
 'GeminiContentService' => ['generate', 'improve', 'generateMenuDescription', 'generatePackageDescription', 'generateSEOContent', 'generateReceiptTemplate', 'improveText'],
 'GeminiImageService' => ['extractMenuFromImage', 'improveImagePrompt'],
 'GeminiAnalyticsService' => ['analyzeRestaurantPerformance', 'generateCustomerRecommendations'],
 'GeminiAPIService' => ['callGeminiAPI', 'isAvailable'],
];

$buckets = [];
foreach ($methods as $m) {
 $assigned = false;
 foreach ($categories as $cat => $keywords) {
 foreach ($keywords as $kw) {
 if (stripos($m, $kw) !== false) {
 $buckets[$cat][] = $m;
 $assigned = true;
 break 2;
 }
 }
 }
 if (!$assigned) {
 $buckets['GeminiAPIService'][] = $m; // default
 }
}

foreach ($buckets as $cat => $methods) {
 $file = "{$appPath}/app/services/{$cat}.php";
 $count = count($methods);

 if (file_exists($file)) {
 echo " ⏭️ {$cat}.php exists, skip\n";
 continue;
 }

 $delegations = '';
 foreach ($methods as $m) {
 $delegations .= " public function {$m}() { return \$this->delegate->{$m}(...func_get_args()); }\n";
 }

 $template = "<?php
/**
 * {$cat} (Q4 2026 Refactor — DELEGATE WRAPPER)
 *
 * Bu service, GeminiService'deki {$count} method'u organize eder.
 * Tam implementasyon Q2 2027'de yapılacak. Şimdilik delegate eder.
 */
declare(strict_types=1);

namespace App\\Services;

require_once __DIR__ . '/GeminiService.php';

class {$cat} extends GeminiService {
 protected GeminiService \$delegate;

 public function __construct() {
 parent::__construct();
 \$this->delegate = new GeminiService();
 }

{$delegations}
}
";

 file_put_contents($file, $template);
 echo " ✅ {$cat}.php: {$count} methods\n";
}