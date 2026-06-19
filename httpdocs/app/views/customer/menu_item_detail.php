<?php
require_once __DIR__ . '/../../helpers/seo.php';
require_once __DIR__ . '/../partials/icons.php';

$menuItem = $menu_item ?? [];
$category = $category ?? null;
$languageCode = $language_code ?? getAppConfig()->getDefaultLanguage();
$alternateUrls = $alternate_urls ?? [];
$settings = $settings ?? [];

// Generate SEO meta tags before header
$metaTitle = $menuItem['meta_title'] ?? ($menuItem['name'] ?? 'Menü Öğesi');
$metaDescription = $menuItem['meta_description'] ?? ($menuItem['description'] ?? '');
$metaKeywords = $menuItem['meta_keywords'] ?? '';

$title = $metaTitle . ' - ' . ($settings['site_name'] ?? getAppConfig()->getAppName());

// Generate SEO tags
$seoTags = generateMenuMetaTags($menuItem, $languageCode);
$ogTags = generateMenuOGTags($menuItem, $languageCode);
$hreflangTags = !empty($alternateUrls) ? generateHreflangTags($alternateUrls) : '';

// Set custom SEO tags for header
$customSEOTags = $seoTags . "\n    " . $ogTags . "\n    " . $hreflangTags;

include __DIR__ . '/../layouts/header.php';
?>

<div class="min-h-screen bg-slate-50 w-full max-w-full overflow-x-hidden">
    <!-- Header -->
    <div class="bg-white border-b sticky top-0 z-20 safe-area-top">
        <div class="container mx-auto px-3 sm:px-4 md:px-6 py-3 sm:py-4">
            <div class="flex items-center justify-between gap-2 sm:gap-3">
                <a href="<?php echo BASE_URL . '/' . $languageCode . '/menu'; ?>" class="btn-touch flex items-center gap-2 text-slate-600 hover:text-slate-900 transition-colors">
                    <?php echo icon_arrow_left(['class' => 'w-5 h-5 sm:w-6 sm:h-6']); ?>
                    <span class="font-bold text-sm sm:text-base">Geri</span>
                </a>
                <div class="flex items-center gap-2">
                    <?php foreach ($alternateUrls as $lang => $url): ?>
                        <a href="<?php echo htmlspecialchars($url); ?>" 
                           class="btn-touch px-2 sm:px-3 py-1.5 sm:py-2 rounded-lg text-xs sm:text-sm font-bold <?php echo $lang === $languageCode ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'; ?>">
                            <?php echo strtoupper($lang); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Menu Item Detail -->
    <div class="container mx-auto px-3 sm:px-4 md:px-6 py-4 sm:py-6 md:py-8 container-padding-responsive">
        <div class="max-w-4xl mx-auto w-full">
            <!-- Image -->
            <?php if (!empty($menuItem['image_url'])): ?>
                <div class="w-full h-64 sm:h-96 rounded-2xl overflow-hidden mb-6 shadow-lg">
                    <img src="<?php echo htmlspecialchars($menuItem['image_url']); ?>" 
                         alt="<?php echo htmlspecialchars($menuItem['name'] ?? ''); ?>"
                         class="w-full h-full object-cover">
                </div>
            <?php endif; ?>

            <!-- Content -->
            <div class="bg-white rounded-xl sm:rounded-2xl p-4 sm:p-5 md:p-6 shadow-lg">
                <div class="mb-4">
                    <?php if ($category): ?>
                        <span class="inline-block px-4 py-2 bg-slate-100 text-slate-600 rounded-lg text-sm font-bold mb-3">
                            <?php echo htmlspecialchars($category['name'] ?? ''); ?>
                        </span>
                    <?php endif; ?>
                    <h1 class="text-3xl sm:text-4xl font-black text-slate-900 mb-4">
                        <?php echo htmlspecialchars($menuItem['name'] ?? ''); ?>
                    </h1>
                    <div class="text-3xl sm:text-4xl font-black text-orange-500 mb-6">
                        <?php echo number_format($menuItem['price'] ?? 0, 2) . ' ₺'; ?>
                    </div>
                </div>

                <?php if (!empty($menuItem['description'])): ?>
                    <div class="mb-6">
                        <h2 class="text-lg font-bold text-slate-800 mb-2">Açıklama</h2>
                        <p class="text-slate-600 leading-relaxed">
                            <?php echo nl2br(htmlspecialchars($menuItem['description'])); ?>
                        </p>
                    </div>
                <?php endif; ?>

                <?php 
                $ingredients = [];
                if (!empty($menuItem['ingredients'])) {
                    $ingredients = is_string($menuItem['ingredients']) 
                        ? json_decode($menuItem['ingredients'], true) 
                        : $menuItem['ingredients'];
                }
                ?>
                <?php if (!empty($ingredients) && is_array($ingredients)): ?>
                    <div class="mb-6">
                        <h2 class="text-lg font-bold text-slate-800 mb-3">İçindekiler</h2>
                        <div class="flex flex-wrap gap-2">
                            <?php foreach ($ingredients as $ingredient): ?>
                                <span class="px-3 py-1 bg-slate-100 text-slate-700 rounded-lg text-sm font-bold">
                                    <?php echo htmlspecialchars($ingredient); ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php 
                $extras = [];
                if (!empty($menuItem['extras'])) {
                    $extras = is_string($menuItem['extras']) 
                        ? json_decode($menuItem['extras'], true) 
                        : $menuItem['extras'];
                }
                ?>
                <?php if (!empty($extras) && is_array($extras)): ?>
                    <div class="mb-6">
                        <h2 class="text-lg font-bold text-slate-800 mb-3">Ekstralar</h2>
                        <div class="space-y-2">
                            <?php foreach ($extras as $extra): ?>
                                <div class="flex items-center justify-between p-3 bg-slate-50 rounded-lg">
                                    <span class="font-bold text-slate-700">
                                        <?php echo htmlspecialchars($extra['name'] ?? ''); ?>
                                    </span>
                                    <span class="font-black text-orange-500">
                                        +<?php echo number_format($extra['price'] ?? 0, 2); ?> ₺
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Add to Cart Button (if table is available) -->
                <?php if (isset($table) && !empty($table)): ?>
                    <button onclick="addToCart()" 
                            class="w-full bg-orange-500 hover:bg-orange-600 text-white font-black py-4 rounded-xl transition-colors">
                        Sepete Ekle
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function addToCart() {
    // Add to cart functionality
    const menuItem = <?php echo json_encode($menuItem); ?>;
    // Implement cart functionality here
    if (window.NotificationManager) {
        window.NotificationManager.info('Sepete ekleme özelliği yakında eklenecek.');
    }
}
</script>

<?php include __DIR__ . '/../layouts/footer.php'; ?>

