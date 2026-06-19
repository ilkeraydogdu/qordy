<?php
/**
 * GeminiService Splitter
 * Tek hizmet dosyasını 6 ayrı sınıfa böler
 */

$appPath = '/var/www/vhosts/qordy.com/httpdocs';
$source = "{$appPath}/app/services/GeminiService.php";
$content = file_get_contents($source);

preg_match_all('/public function ([a-zA-Z0-9_]+)\(/', $content, $matches);
$methods = array_filter($matches[1], fn($m) => $m !== '__construct');

$categories = [
 'TranslationService' => ['translate', 'translateText', 'translateMenuItem', 'translateIngredients', 'translateExtras', 'translateProductName'],
 'ContentService' => ['generate', 'improve', 'generateMenuDescription', 'generatePackageDescription', 'generateSEOContent', 'generateReceiptTemplate', 'improveText', 'improveImagePrompt'],
 'ImageService' => ['image', 'extractMenuFromImage', 'improveImagePrompt'],
 'AnalyticsService' => ['analyze', 'analyzeRestaurantPerformance', 'generateCustomerRecommendations'],
 'APIService' => ['callGeminiAPI', 'isAvailable'],
];

// Extract method content from GeminiService
$methodContents = [];
foreach ($methods as $m) {
 // Find method start and end
 $pattern = "/public function {$m}\([^{]*\) \{(.*?)\}(?=\s*public|\s*private|\s*protected|\s*\$|\s*)/s";
 preg_match($pattern, $content, $matches);
 if (!empty($matches[1])) {
 $methodContents[$m] = $matches[1];
 }
}

$buckets = [];
foreach ($methods as $m) {
 $assigned = false;
 foreach ($categories as $cat => $keywords) {
 foreach ($keywords as $kw) {
 if (stripos($m, $kw) !== false) {
 $buckets[$cat][$m] = $methodContents[$m] ?? '';
 $assigned = true;
 break 2;
 }
 }
 }
 if (!$assigned) {
 $buckets['APIService'][$m] = $methodContents[$m] ?? ''; // default to API
 }
}

// Create separate service files
foreach ($buckets as $cat => $methods) {
 $file = "{$appPath}/app/services/{$cat}.php";
 $constructor = '';
 $methodsCode = '';

 foreach ($methods as $m => $code) {
 $methodsCode .= "\n public function {$m}" . substr($code, 0, 50) . " {\n" . $code . "\n }\n";
 $constructor .= " require_once __DIR__ . '/GeminiService.php';\n";
 }

 $template = "<?php
/**
 * Gemini {$cat}
 *
 * Extracted from GeminiService.php (Q4 2026)
 *
 * Methods: " . count($methods) . "
 */

declare(strict_types=1);

namespace App\Services;

{$constructor}

class {$cat} extends GeminiService {
{$methodsCode}
}

return new {$cat}();
";

 file_put_contents($file, $template);
 echo " ✅ {$cat}.php: " . count($methods) . " methods\n";
}

echo "\nTotal: " . count($methods) . " methods split.\n";