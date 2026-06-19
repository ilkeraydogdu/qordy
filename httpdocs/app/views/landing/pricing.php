<?php
// Ensure dependencies
if (!defined('BASE_URL')) {
    require_once __DIR__ . '/../../config/config.php';
}
if (!function_exists('getAppConfig')) {
    require_once __DIR__ . '/../../helpers/functions.php';
}

$appName = 'Qordy';
try {
    $appName = htmlspecialchars(getAppConfig()->getAppName());
} catch (\Exception $e) {
    $appName = 'Qordy';
}

$baseUrl = rtrim(defined('BASE_URL') ? BASE_URL : '', '/');
$loginUrl = $baseUrl . '/login';
$registerUrl = $baseUrl . '/register';
$canonicalUrl = $baseUrl . '/pricing';

if (!isset($packages)) {
    $packages = [];
}
$seoTitle = 'Fiyatlandırma — Aylık ve Yıllık Paketler · QORDY Restoran Yazılımı';
$seoDescription = 'QORDY restoran yönetim yazılımı paket fiyatları: esnek aylık ve yıllık seçenekler, 14 gün ücretsiz deneme, kredi kartı gerekmez. Size uygun planı hemen karşılaştırın.';

// Pricing sayfası için Product/Offer JSON-LD — rich result'lar (price,
// availability) Google Shopping/SERP'de ürünü ön plana çıkarır.
$productLd = [];
foreach ($packages as $p) {
    $priceMonthly = (float)($p['price_monthly'] ?? $p['monthly'] ?? 0);
    if ($priceMonthly <= 0) continue;
    $productLd[] = [
        '@context'    => 'https://schema.org',
        '@type'       => 'Product',
        'name'        => 'QORDY — ' . (string)($p['name'] ?? 'Paket'),
        'description' => (string)($p['description'] ?? $p['desc'] ?? 'QORDY restoran yönetim yazılımı paketi.'),
        'brand'       => ['@type' => 'Brand', 'name' => 'QORDY'],
        'category'    => 'Restoran Yönetim Yazılımı',
        'offers'      => [
            '@type'         => 'Offer',
            'price'         => number_format($priceMonthly, 2, '.', ''),
            'priceCurrency' => 'TRY',
            'availability'  => 'https://schema.org/InStock',
            'url'           => $canonicalUrl,
        ],
    ];
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <?php require_once __DIR__ . '/../partials/google_analytics.php'; ?>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="designer" content="Pofuduk Dijital Medya ve Yazılım Limited Şirketi — İlker Aydoğdu — pofudukdijital.com">
    <meta name="description" content="<?php echo htmlspecialchars($seoDescription, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1">
    <meta name="keywords" content="restoran yönetim yazılımı fiyat, adisyon programı fiyatları, QR menü paketleri, POS yazılımı fiyat, restoran yazılımı abonelik">
    <title><?php echo htmlspecialchars($seoTitle, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="canonical" href="<?php echo htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="alternate" hreflang="tr" href="<?php echo htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="alternate" hreflang="x-default" href="<?php echo htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8'); ?>">

    <meta property="og:type" content="website">
    <meta property="og:site_name" content="QORDY">
    <meta property="og:locale" content="tr_TR">
    <meta property="og:title" content="<?php echo htmlspecialchars($seoTitle, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($seoDescription, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:url" content="<?php echo htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:image" content="<?php echo $baseUrl; ?>/assets/images/og-image.jpg">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo htmlspecialchars($seoTitle, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="twitter:description" content="<?php echo htmlspecialchars($seoDescription, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="twitter:image" content="<?php echo $baseUrl; ?>/assets/images/twitter-card.jpg">

    <link rel="icon" type="image/png" href="<?php echo $baseUrl; ?>/assets/images/favicon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo $baseUrl; ?>/assets/landing/css/custom.css">
    <script type="application/ld+json"><?php echo json_encode([
        '@context' => 'https://schema.org',
        '@type'    => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Ana Sayfa', 'item' => $baseUrl . '/'],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'Fiyatlandırma', 'item' => $canonicalUrl],
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?></script>
    <?php foreach ($productLd as $p): ?>
    <script type="application/ld+json"><?php echo json_encode($p, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?></script>
    <?php endforeach; ?>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <a href="<?php echo $baseUrl; ?>/" class="logo">
                <img src="<?php echo $baseUrl; ?>/assets/images/logo.png" alt="<?php echo $appName; ?>">
            </a>
            <nav class="nav">
                <a href="<?php echo $baseUrl; ?>/">Ana Sayfa</a>
                <a href="<?php echo $baseUrl; ?>/features">Özellikler</a>
                <a href="<?php echo $baseUrl; ?>/pricing" class="active">Fiyatlandırma</a>
                <a href="<?php echo $baseUrl; ?>/#contact">İletişim</a>
            </nav>
            <div class="header-actions">
                <a href="<?php echo $loginUrl; ?>" class="btn btn-secondary">Giriş Yap</a>
                <a href="<?php echo $registerUrl; ?>" class="btn btn-primary">Kayıt Ol</a>
            </div>
        </div>
    </header>

    <!-- Pricing Section -->
    <section class="section" style="padding-top: 8rem;">
        <div class="container">
            <div class="section-header">
                <span class="section-badge">Fiyatlandırma</span>
                <h1 class="section-title">QORDY Restoran Yazılımı Fiyatları</h1>
                <p class="section-desc">Size uygun planı seçin. 14 gün ücretsiz deneme, kredi kartı gerekmez.</p>
            </div>
            
            <!-- Billing Toggle -->
            <div class="pricing-toggle">
                <span class="toggle-label">Aylık</span>
                <label class="toggle-switch">
                    <input type="checkbox" id="billingToggle" onchange="toggleBilling()">
                    <span class="toggle-slider"></span>
                </label>
                <span class="toggle-label">Yıllık <small style="color: var(--success);">(%20 indirim)</small></span>
            </div>
            
            <?php if (empty($packages)): ?>
            <div style="text-align:center; padding: 3rem 1rem;">
                <p style="font-size:1.1rem; color: var(--gray-600);">Paketler yakında yayınlanacaktır. Lütfen daha sonra tekrar kontrol edin.</p>
            </div>
            <?php else: ?>
            <div class="pricing-grid">
                <?php foreach ($packages as $package): 
                    $packageName = htmlspecialchars($package['name'] ?? 'Paket');
                    $packageDesc = htmlspecialchars($package['description'] ?? $package['desc'] ?? '');
                    $monthlyPrice = floatval($package['price_monthly'] ?? $package['monthly'] ?? 0);
                    $yearlyPrice = floatval($package['price_yearly'] ?? $package['yearly'] ?? 0);
                    $features = $package['features_array'] ?? [];
                    $isPopular = !empty($package['is_featured']) || !empty($package['popular']);
                    $packageId = $package['package_id'] ?? '';
                ?>
                <div class="pricing-card <?php echo $isPopular ? 'pricing-popular' : ''; ?>">
                    <?php if ($isPopular): ?>
                    <span class="pricing-popular-badge">En Popüler</span>
                    <?php endif; ?>
                    
                    <h3 class="pricing-name"><?php echo $packageName; ?></h3>
                    <p class="pricing-desc"><?php echo $packageDesc; ?></p>
                    
                    <div class="pricing-price">
                        <span class="price-amount monthly-price">₺<?php echo number_format($monthlyPrice, 0, ',', '.'); ?></span>
                        <span class="price-amount yearly-price" style="display: none;">₺<?php echo number_format($yearlyPrice, 0, ',', '.'); ?></span>
                        <span class="price-period monthly-period">/ay</span>
                        <span class="price-period yearly-period" style="display: none;">/yıl</span>
                    </div>
                    
                    <?php if (!empty($features)): ?>
                    <ul class="pricing-features">
                        <?php foreach ($features as $feature): 
                            $featureText = is_array($feature) ? ($feature['name'] ?? $feature['title'] ?? '') : $feature;
                            if (empty($featureText)) continue;
                        ?>
                        <li>
                            <svg fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>
                            <?php echo htmlspecialchars($featureText); ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                    
                    <a href="<?php echo $registerUrl; ?><?php echo $packageId ? '?package=' . $packageId : ''; ?>" class="btn <?php echo $isPopular ? 'btn-primary' : 'btn-secondary'; ?>" style="width: 100%;">
                        Ücretsiz Dene
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <!-- Guarantee -->
            <div style="text-align: center; margin-top: 3rem; padding: 2rem; background: var(--gray-50); border-radius: var(--radius-xl);">
                <p style="color: var(--gray-600); margin: 0;">
                    <strong>14 Gün Para İade Garantisi</strong> - Memnun kalmazsanız paranızı iade ediyoruz.
                </p>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-bottom" style="border-top: none; padding-top: 0;">
                <p>&copy; <?php echo date('Y'); ?> <?php echo $appName; ?>. Tüm hakları saklıdır.</p>
                <div class="footer-legal">
                    <a href="<?php echo $baseUrl; ?>/">Ana Sayfa</a>
                    <a href="<?php echo $baseUrl; ?>/features">Özellikler</a>
                    <a href="<?php echo $baseUrl; ?>/#contact">İletişim</a>
                </div>
            </div>
        </div>
    </footer>

    <script>
        function toggleBilling() {
            const toggle = document.getElementById('billingToggle');
            const monthlyPrices = document.querySelectorAll('.monthly-price');
            const yearlyPrices = document.querySelectorAll('.yearly-price');
            const monthlyPeriods = document.querySelectorAll('.monthly-period');
            const yearlyPeriods = document.querySelectorAll('.yearly-period');
            
            if (toggle.checked) {
                monthlyPrices.forEach(el => el.style.display = 'none');
                yearlyPrices.forEach(el => el.style.display = 'inline');
                monthlyPeriods.forEach(el => el.style.display = 'none');
                yearlyPeriods.forEach(el => el.style.display = 'inline');
            } else {
                monthlyPrices.forEach(el => el.style.display = 'inline');
                yearlyPrices.forEach(el => el.style.display = 'none');
                monthlyPeriods.forEach(el => el.style.display = 'inline');
                yearlyPeriods.forEach(el => el.style.display = 'none');
            }
        }
    </script>
</body>
</html>
