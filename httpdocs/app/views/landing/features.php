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
// Login URL'sini pricing.php ve React SPA ile hizaladık (`/login`).
// Önceki `/public-login` route registered değildi → 404.
$loginUrl = $baseUrl . '/login';
$registerUrl = $baseUrl . '/register';
$canonicalUrl = $baseUrl . '/features';
$seoTitle = 'Özellikler — QR Menü, POS, Mutfak Ekranı, Stok · QORDY';
$seoDescription = 'QORDY restoran yönetim yazılımının özellikleri: QR menü sistemi, adisyon/POS, mutfak ekranı (KDS), rezervasyon takibi, stok yönetimi, çoklu şube, garson çağrı ve gerçek zamanlı raporlama.';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <?php require_once __DIR__ . '/../partials/google_analytics.php'; ?>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?php echo htmlspecialchars($seoDescription, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1">
    <meta name="keywords" content="restoran yönetim yazılımı, QR menü, adisyon programı, mutfak ekranı, KDS, POS sistemi, stok yönetimi, rezervasyon yazılımı, garson çağrı sistemi, çoklu şube">
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
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'Özellikler', 'item' => $canonicalUrl],
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?></script>
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
                <a href="<?php echo $baseUrl; ?>/features" class="active">Özellikler</a>
                <a href="<?php echo $baseUrl; ?>/#pricing">Fiyatlandırma</a>
                <a href="<?php echo $baseUrl; ?>/#contact">İletişim</a>
            </nav>
            <div class="header-actions">
                <a href="<?php echo $loginUrl; ?>" class="btn btn-secondary">Giriş Yap</a>
                <a href="<?php echo $registerUrl; ?>" class="btn btn-primary">Kayıt Ol</a>
            </div>
        </div>
    </header>

    <!-- Features Section -->
    <section class="section" style="padding-top: 8rem;">
        <div class="container">
            <div class="section-header">
                <span class="section-badge">Özellikler</span>
                <h1 class="section-title">Restoran Yönetim Yazılımı Özellikleri</h1>
                <p class="section-desc">QR menü, adisyon programı, mutfak ekranı, rezervasyon ve stok — restoran operasyonunun her adımını QORDY ile tek platformdan yönetin.</p>
            </div>
            
            <div class="features-grid">
                <!-- QR Menu -->
                <div class="feature-card">
                    <div class="feature-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path></svg>
                    </div>
                    <h3 class="feature-title">QR Menü</h3>
                    <p class="feature-desc">Temassız dijital menü. QR kod ile sipariş.</p>
                </div>
                
                <!-- POS -->
                <div class="feature-card">
                    <div class="feature-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                    </div>
                    <h3 class="feature-title">POS Sistemi</h3>
                    <p class="feature-desc">Profesyonel satış noktası. Her cihazda çalışır.</p>
                </div>
                
                <!-- Kitchen Display -->
                <div class="feature-card">
                    <div class="feature-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"></path></svg>
                    </div>
                    <h3 class="feature-title">Mutfak Ekranı</h3>
                    <p class="feature-desc">Gerçek zamanlı sipariş takibi.</p>
                </div>
                
                <!-- Payment -->
                <div class="feature-card">
                    <div class="feature-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path></svg>
                    </div>
                    <h3 class="feature-title">Güvenli Ödeme</h3>
                    <p class="feature-desc">PCI uyumlu ödeme altyapısı.</p>
                </div>
                
                <!-- Reports -->
                <div class="feature-card">
                    <div class="feature-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                    </div>
                    <h3 class="feature-title">Raporlama</h3>
                    <p class="feature-desc">Detaylı satış ve stok analizleri.</p>
                </div>
                
                <!-- Stock -->
                <div class="feature-card">
                    <div class="feature-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                    </div>
                    <h3 class="feature-title">Stok Yönetimi</h3>
                    <p class="feature-desc">Otomatik stok takibi.</p>
                </div>
            </div>
            
            <!-- CTA -->
            <div style="text-align: center; margin-top: 4rem;">
                <a href="<?php echo $registerUrl; ?>" class="btn btn-primary btn-lg">
                    Ücretsiz Deneyin
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path></svg>
                </a>
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
                    <a href="<?php echo $baseUrl; ?>/#pricing">Fiyatlandırma</a>
                    <a href="<?php echo $baseUrl; ?>/#contact">İletişim</a>
                </div>
            </div>
        </div>
    </footer>
</body>
</html>
