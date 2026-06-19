<?php
/**
 * Dynamic sitemap.xml generator
 * Generates sitemap with all public pages and dynamic content
 * Includes multi-language support with hreflang tags
 */

// Bootstrap minimal config
require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/core/DependencyFactory.php';
require_once __DIR__ . '/../app/helpers/seo.php';
require_once __DIR__ . '/../app/services/SoroBlogMirrorService.php';

header('Content-Type: application/xml; charset=utf-8');
header('X-Robots-Tag: noindex'); // the sitemap itself is not indexable

$baseUrl = defined('BASE_URL') ? BASE_URL : 'http://localhost';
$baseUrl = rtrim($baseUrl, '/');

// Get current date for lastmod
$currentDate = date('Y-m-d');

// Get supported languages
$settingsService = \App\Core\DependencyFactory::getSystemSettingsService();
$supportedLanguagesJson = $settingsService->getSetting('supported_languages', '["tr","en"]');
$supportedLanguages = json_decode($supportedLanguagesJson, true);
if (!is_array($supportedLanguages) || empty($supportedLanguages)) {
    $supportedLanguages = ['tr', 'en'];
}

// Start XML output
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . "\n";
echo '        xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";

// Helper function to output URL entry with hreflang
function outputUrl($loc, $lastmod, $changefreq = 'weekly', $priority = '0.8', $alternates = []) {
    echo "    <url>\n";
    echo "        <loc>" . htmlspecialchars($loc) . "</loc>\n";
    echo "        <lastmod>{$lastmod}</lastmod>\n";
    echo "        <changefreq>{$changefreq}</changefreq>\n";
    echo "        <priority>{$priority}</priority>\n";
    
    // Add hreflang alternates
    foreach ($alternates as $lang => $altUrl) {
        echo "        <xhtml:link rel=\"alternate\" hreflang=\"{$lang}\" href=\"" . htmlspecialchars($altUrl) . "\"/>\n";
    }
    
    echo "    </url>\n";
}

// Static public pages (with language variants)
foreach ($supportedLanguages as $lang) {
    outputUrl($baseUrl . '/' . $lang . '/menu', $currentDate, 'daily', '0.9');
}
outputUrl($baseUrl . '/menu', $currentDate, 'daily', '0.9'); // Default
outputUrl($baseUrl . '/', $currentDate, 'daily', '1.0');
outputUrl($baseUrl . '/login', $currentDate, 'monthly', '0.5');
outputUrl($baseUrl . '/register', $currentDate, 'monthly', '0.6');

// Legal pages remain as separate SPA routes.
outputUrl($baseUrl . '/gizlilik', $currentDate, 'monthly', '0.5');
outputUrl($baseUrl . '/kullanim-sartlari', $currentDate, 'monthly', '0.5');

// Yayınlanmış "sayfa/{slug}" (ör. hakkımızda, kvkk, gizlilik, kullanım şartları).
try {
    $db = \App\Core\DependencyFactory::getDatabase();
    $legalStmt = $db->prepare("SELECT slug, COALESCE(updated_at, created_at) AS mod_date FROM legal_pages WHERE is_active = 1");
    $legalStmt->execute();
    while ($row = $legalStmt->fetch(\PDO::FETCH_ASSOC)) {
        if (empty($row['slug'])) continue;
        $lastmod = !empty($row['mod_date']) ? date('Y-m-d', strtotime($row['mod_date'])) : $currentDate;
        outputUrl($baseUrl . '/sayfa/' . rawurlencode($row['slug']), $lastmod, 'monthly', '0.6');
    }
} catch (\Throwable $e) {
    // Legal pages tablosu yoksa veya DB hazır değilse sessizce atla;
    // /sayfa/* URL'leri olmasa da sitemap çalışmaya devam etmeli.
}

// Dynamic menu items with translations
try {
    $menuItemService = \App\Core\DependencyFactory::getMenuItemService();
    $translationService = \App\Core\DependencyFactory::getMenuItemTranslationService();
    $menuItems = $menuItemService->getAllMenuItems();
    
    foreach ($menuItems as $item) {
        if (isset($item['is_available']) && $item['is_available']) {
            $lastmod = isset($item['updated_at']) ? date('Y-m-d', strtotime($item['updated_at'])) : $currentDate;
            
            // Get translations for this menu item
            $translations = $translationService->getTranslationsForEdit($item['menu_item_id']);
            
            // Build alternate URLs
            $alternateUrls = [];
            foreach ($translations as $lang => $trans) {
                if (!empty($trans['slug'])) {
                    $alternateUrls[$lang] = $baseUrl . '/' . $lang . '/menu/' . $trans['slug'];
                }
            }
            
            // Output URL for each language
            foreach ($alternateUrls as $lang => $url) {
                outputUrl($url, $lastmod, 'weekly', '0.7', $alternateUrls);
            }
            
            // Fallback: if no translations, use old format
            if (empty($alternateUrls)) {
                outputUrl($baseUrl . '/menu#' . urlencode($item['menu_item_id']), $lastmod, 'weekly', '0.7');
            }
        }
    }
} catch (\Exception $e) {
    // Silently fail if database is not available
}

// Dynamic table QR codes (public)
try {
    $tableModel = new \App\Models\Table();
    $tables = $tableModel->getAll();
    
    foreach ($tables as $table) {
        if (isset($table['table_id'])) {
            $lastmod = isset($table['updated_at']) ? date('Y-m-d', strtotime($table['updated_at'])) : $currentDate;
            outputUrl($baseUrl . '/t/' . urlencode($table['table_id']), $lastmod, 'weekly', '0.6');
        }
    }
} catch (\Exception $e) {
    // Silently fail if database is not available
}

// Blog pages
try {
    $db = \App\Core\DependencyFactory::getDatabase();
    $blogPostRepo = new \App\Repositories\BlogPostRepository($db);
    $blogCategoryRepo = new \App\Repositories\BlogCategoryRepository($db);
    
    // Blog index page — `/tr/blog` gibi dil prefix'li URL'ler registered
    // route değil, sadece ana `/blog` çalışıyor. Bu yüzden dil prefix'li
    // variant'lar YAYINLANMIYOR; aksi halde GSC "404 alternate" uyarısı
    // verir ve blog puanı düşer.
    outputUrl($baseUrl . '/blog', $currentDate, 'daily', '0.9');

    // Blog categories
    $categories = $blogCategoryRepo->getActive();
    foreach ($categories as $category) {
        if (empty($category['slug'])) continue;
        outputUrl($baseUrl . '/blog/category/' . rawurlencode($category['slug']), $currentDate, 'weekly', '0.8');
    }

    // Blog posts
    $posts = $blogPostRepo->getPublished(1000, 0);
    foreach ($posts as $post) {
        if (empty($post['slug'])) continue;
        $lastmod = isset($post['updated_at']) ? date('Y-m-d', strtotime($post['updated_at'])) : $currentDate;
        outputUrl($baseUrl . '/blog/' . rawurlencode($post['slug']), $lastmod, 'weekly', '0.7');
    }
} catch (\Exception $e) {
    // Silently fail if database is not available
}

// Soro-mirrored blog articles (autonomously refreshed by cron/soro_mirror.php)
try {
    $soroMirror = new \App\Services\SoroBlogMirrorService();

    // Lazy refresh if stale so the sitemap stays warm even if cron is late
    try { $soroMirror->refreshIfStale(); } catch (\Throwable $e) { /* non-fatal */ }

    $soroArticles   = $soroMirror->getArticles();
    $soroCategories = $soroMirror->getCategories();

    // Always emit the blog index (already emitted above for internal blog but
    // add an explicit Soro entry in case internal block fails).
    if (!empty($soroArticles) || !empty($soroCategories)) {
        outputUrl($baseUrl . '/blog', $currentDate, 'daily', '0.9');
    }

    foreach ($soroCategories as $c) {
        if (!empty($c['slug'])) {
            outputUrl($baseUrl . '/blog/category/' . rawurlencode($c['slug']), $currentDate, 'weekly', '0.7');
        }
    }

    foreach ($soroArticles as $a) {
        if (empty($a['slug'])) continue;
        $lastmod = !empty($a['updated_at']) ? date('Y-m-d', strtotime($a['updated_at']))
                 : (!empty($a['published_at']) ? date('Y-m-d', strtotime($a['published_at'])) : $currentDate);
        // Guard against invalid timestamps
        if ($lastmod === '1970-01-01') { $lastmod = $currentDate; }
        outputUrl($baseUrl . '/blog/' . rawurlencode($a['slug']), $lastmod, 'weekly', '0.7');
    }
} catch (\Exception $e) {
    // Silently fail - Soro mirror is optional
}

// Close XML
echo '</urlset>';

