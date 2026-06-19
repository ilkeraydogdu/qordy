<?php
/**
 * QORDY React SPA shell.
 *
 * Replaces the legacy PHP landing/login/register views with the
 * Vite-built React bundle (../public/app/index.html). Server-side
 * we inject:
 *   - <meta name="csrf-token"> for fetch() requests
 *   - window.__QORDY__ with flash messages, base URL and pre-loaded
 *     pricing packages so the SPA paints instantly without a round-trip
 */

if (!defined('BASE_URL')) {
    require_once __DIR__ . '/../config/config.php';
}

$indexPath = __DIR__ . '/../../public/app/index.html';
if (!is_file($indexPath)) {
    http_response_code(503);
    echo 'Frontend bundle missing. Run `npm run build` inside /frontend.';
    exit;
}

$html = file_get_contents($indexPath);

// NOT: Asset URL'lerine `?v=` query EKLEMİYORUZ. Vite zaten dosya adına
// content-hash gömüyor (index-DNyZ0fXD.js); hash değişince URL değişir,
// bu doğru ve yeterli cache-busting'dir. Entry <script> etiketine `?v=`
// eklemek ES modül kimliğini bozar: lazy yüklenen chunk'lar (LandingPage
// vb.) paylaşılan entry modülünü query'siz import eder, tarayıcı `?v='li
// ve query'siz URL'leri AYRI modül sayar ve React + React Router'ı İKİ
// kez yükler. İki ayrı Router context'i oluşunca <NavLink>/useLocation
// null context görür ("Cannot destructure 'basename' of null") ve iki
// React reconciler aynı DOM'u yönetmeye çalışınca "removeChild" NotFound
// hatası fırlar. Vite content-hash'li dosya adları cache-busting için yeterlidir.

// Always mint a fresh CSRF token when rendering the SPA shell so that the
// public landing / login / register forms can POST without tripping CSRF
// validation. The runtime uses Redis-backed CSRFManager; falling back to
// $_SESSION['csrf_token'] is incorrect because Redis-mode tokens are NEVER
// stored in the session array.
$csrfToken = '';
try {
    if (!class_exists('\\App\\Core\\Security\\CSRFManager')) {
        $managerPath = __DIR__ . '/../core/Security/CSRFManager.php';
        if (is_file($managerPath)) {
            require_once $managerPath;
        }
    }
    if (class_exists('\\App\\Core\\Security\\CSRFManager')) {
        $csrfToken = \App\Core\Security\CSRFManager::generateToken();
    } elseif (function_exists('generateCSRFToken')) {
        $csrfToken = generateCSRFToken();
    } else {
        $csrfToken = $_SESSION['csrf_token'] ?? '';
    }
} catch (\Throwable $e) {
    if (class_exists('\\App\\Core\\Logger')) {
        \App\Core\Logger::warning('react_app.php: CSRF token generation failed', [
            'error' => $e->getMessage(),
        ]);
    }
    $csrfToken = $_SESSION['csrf_token'] ?? '';
}

// ToastNotificationService::setFlash stores under $_SESSION[$type]
// (see app/services/ToastNotificationService.php). Also handle the
// legacy / alternative keys used by some controllers.
$flashError   = $_SESSION['error']           ?? $_SESSION['error_message']
              ?? $_SESSION['flash_error']    ?? null;
$flashSuccess = $_SESSION['success']         ?? $_SESSION['success_message']
              ?? $_SESSION['flash_success']  ?? null;
$flashWarning = $_SESSION['warning']         ?? null;
$flashInfo    = $_SESSION['info']            ?? null;
unset(
    $_SESSION['error'],
    $_SESSION['error_message'],
    $_SESSION['flash_error'],
    $_SESSION['success'],
    $_SESSION['success_message'],
    $_SESSION['flash_success'],
    $_SESSION['warning'],
    $_SESSION['info']
);

// Pricing packages — passed by LandingController, optional otherwise.
$bootstrapPackages = isset($packages) && is_array($packages) ? array_values($packages) : null;

// Trial settings — centrally managed via /qodmin/trial-settings. Landing
// and register SPA consume these to render dynamic duration copy.
$trialBootstrap = [
    'enabled'             => true,
    'duration_days'       => 14,
    'data_retention_days' => 37, // 7 grace + 30 data retention (default)
];
try {
    if (!class_exists('\\App\\Core\\DependencyFactory')) {
        $dfPath = __DIR__ . '/../core/DependencyFactory.php';
        if (is_file($dfPath)) {
            require_once $dfPath;
        }
    }
    if (class_exists('\\App\\Core\\DependencyFactory')) {
        $trialService = \App\Core\DependencyFactory::getTrialService();
        if ($trialService) {
            $ts = $trialService->getTrialSettings();
            $grace = isset($ts['grace_period_days']) ? (int)$ts['grace_period_days'] : 7;
            $trialBootstrap = [
                'enabled'             => (bool)($ts['trial_enabled'] ?? 1),
                'duration_days'       => (int)($ts['trial_duration_days'] ?? 14),
                'data_retention_days' => $grace + 30,
            ];
        }
    }
} catch (\Throwable $e) {
    if (class_exists('\\App\\Core\\Logger')) {
        \App\Core\Logger::warning('react_app.php: trial settings bootstrap failed', [
            'error' => $e->getMessage(),
        ]);
    }
}

$bootstrap = [
    'csrfToken' => $csrfToken,
    'baseUrl'   => defined('BASE_URL') ? BASE_URL : '',
    'flash'     => [
        'error'   => $flashError,
        'success' => $flashSuccess,
        'warning' => $flashWarning,
        'info'    => $flashInfo,
    ],
    'packages'  => $bootstrapPackages,
    'trial'     => $trialBootstrap,
    'oldInput'  => $_SESSION['old_input'] ?? null,
];
unset($_SESSION['old_input']);

// ---------------------------------------------------------------------------
// SEO: sayfa bazlı meta, canonical, Open Graph, Twitter Card ve JSON-LD
// enjeksiyonu. React SPA build'i (public/app/index.html) sadece <title>
// ve viewport meta'sı içeriyor. Googlebot her ne kadar JS render etse de
// SOCIAL CRAWLER'lar (Facebook/LinkedIn/Twitter/WhatsApp) JS çalıştırmaz
// ve SEO puanının büyük kısmı ilk HTML payload'undan gelir. Bu yüzden
// server-side meta tag'leri enjekte etmek kritik.
// ---------------------------------------------------------------------------

$baseUrl = rtrim(defined('BASE_URL') ? BASE_URL : '', '/');
$rawPath = $_SERVER['REQUEST_URI'] ?? '/';
$pathOnly = parse_url($rawPath, PHP_URL_PATH) ?: '/';
$pathOnly = '/' . ltrim($pathOnly, '/');

// Canonical URL — query string ve fragment olmadan, kök slash normalize
// edilmiş halde. Homepage için "/" kullanıyoruz (Google önerisi: ana
// sayfa canonical'ı site root'u olmalı, `/home` DEĞİL).
$canonicalPath = $pathOnly === '' ? '/' : $pathOnly;
if (preg_match('#^/(login|register|forgot-password|reset-password|verify-email|verify-2fa)/?$#', $canonicalPath, $m)) {
    $canonicalPath = '/' . $m[1];
}
$canonicalUrl = $baseUrl . ($canonicalPath === '/' ? '' : $canonicalPath);
if ($canonicalUrl === '') { $canonicalUrl = $baseUrl . '/'; }

// Sayfa tipini path'e göre tespit et.
$pageType = 'home';
if ($canonicalPath === '/register') {
    $pageType = 'register';
} elseif (preg_match('#^/(login|forgot-password|reset-password|verify-email|verify-2fa)#', $canonicalPath)) {
    $pageType = 'auth';
} elseif ($canonicalPath === '/features' || $canonicalPath === '/ozellikler') {
    $pageType = 'features';
} elseif ($canonicalPath === '/pricing' || $canonicalPath === '/fiyatlar' || $canonicalPath === '/fiyatlandirma') {
    $pageType = 'pricing';
} elseif ($canonicalPath === '/hakkimizda') {
    $pageType = 'about';
} elseif ($canonicalPath === '/iletisim') {
    $pageType = 'contact';
} elseif ($canonicalPath === '/gizlilik') {
    $pageType = 'privacy';
} elseif ($canonicalPath === '/kullanim-sartlari') {
    $pageType = 'terms';
}

// Sayfa başlığı ve açıklaması — Turkish odaklı, rakip anahtar kelimeleri
// içerir (restoran yönetim yazılımı, adisyon programı, QR menü, mutfak
// ekranı, stok yönetimi, rezervasyon).
$seoMeta = [
    'home' => [
        'title'       => 'Qordy — Restoranlar için İşletim Sistemi · QR Menü, POS, Mutfak Ekranı',
        'description' => 'QORDY ile restoranınızı baştan sona dijitalleştirin: QR menü, adisyon programı, mutfak ekranı (KDS), stok, rezervasyon ve raporlama tek platformda. 14 gün ücretsiz deneyin.',
        'robots'      => 'index, follow, max-image-preview:large, max-snippet:-1',
    ],
    'features' => [
        'title'       => 'Özellikler — QR Menü, POS, Mutfak Ekranı, Stok · QORDY',
        'description' => 'QR menü sistemi, adisyon/POS, mutfak ekranı (KDS), rezervasyon, çoklu şube ve stok yönetimi. QORDY ile restoran operasyonunu uçtan uca otomatikleştirin.',
        'robots'      => 'index, follow, max-image-preview:large',
    ],
    'pricing' => [
        'title'       => 'Fiyatlandırma — Aylık ve Yıllık Paketler · QORDY Restoran Yazılımı',
        'description' => 'QORDY restoran yönetim yazılımı paketleri: esnek aylık ve yıllık fiyatlandırma. 14 gün ücretsiz deneme, kredi kartı gerekmez, istediğin zaman iptal et.',
        'robots'      => 'index, follow, max-image-preview:large',
    ],
    'auth' => [
        'title'       => 'Giriş — QORDY Restoran Yönetim Paneli',
        'description' => 'QORDY hesabınıza giriş yapın.',
        'robots'      => 'noindex, follow',
    ],
    'register' => [
        'title'       => 'Kayıt Ol — QORDY · 14 Gün Ücretsiz Deneme',
        'description' => 'QORDY restoran yönetim yazılımına kayıt olun. Kredi kartı gerekmez, 14 gün ücretsiz deneyin.',
        'robots'      => 'noindex, follow',
    ],
    'about' => [
        'title'       => 'Hakkımızda — QORDY Restoran Yönetim Yazılımı',
        'description' => 'QORDY ekibi ve misyonu: Türkiye\'deki restoran, kafe ve paket servis işletmeleri için modern, bulut tabanlı operasyon yazılımı.',
        'robots'      => 'index, follow, max-image-preview:large',
    ],
    'contact' => [
        'title'       => 'İletişim — QORDY Destek ve Satış',
        'description' => 'QORDY satış, destek ve demo talepleri için bize ulaşın. E-posta, telefon ve iletişim formu ile 7/24 yanıt.',
        'robots'      => 'index, follow',
    ],
    'privacy' => [
        'title'       => 'Gizlilik Politikası · QORDY',
        'description' => 'QORDY gizlilik politikası: kişisel verilerin işlenmesi, KVKK uyumu, çerezler ve veri güvenliği.',
        'robots'      => 'index, follow',
    ],
    'terms' => [
        'title'       => 'Kullanım Şartları · QORDY',
        'description' => 'QORDY hizmet kullanım şartları, abonelik koşulları ve sorumluluk sınırları.',
        'robots'      => 'index, follow',
    ],
];
$seo = $seoMeta[$pageType] ?? $seoMeta['home'];

$ogImage = $baseUrl . '/assets/images/og-image.jpg';
$twitterImage = $baseUrl . '/assets/images/twitter-card.jpg';
require_once __DIR__ . '/../helpers/brand.php';
$logoUrl = getQordyLogoUrl();

// JSON-LD — Organization + SoftwareApplication + (home için) FAQPage.
// Hardcoded aggregateRating EKLENMEDİ: gerçek kullanıcı rating verisi
// toplanana kadar bunu koymak Google policy ihlali sayılır.
$jsonLd = [];

$jsonLd[] = [
    '@context' => 'https://schema.org',
    '@type'    => 'Organization',
    'name'     => 'QORDY',
    'url'      => $baseUrl,
    'logo'     => $logoUrl,
    'sameAs'   => [
        'https://www.linkedin.com/company/qordy',
        'https://twitter.com/qordyapp',
    ],
    'contactPoint' => [
        '@type'             => 'ContactPoint',
        'contactType'       => 'customer service',
        'email'             => 'destek@qordy.com',
        'availableLanguage' => ['Turkish', 'English'],
    ],
];

$jsonLd[] = [
    '@context'               => 'https://schema.org',
    '@type'                  => 'SoftwareApplication',
    'name'                   => 'QORDY',
    'applicationCategory'    => 'BusinessApplication',
    'applicationSubCategory' => 'Restoran Yönetim Yazılımı',
    'operatingSystem'        => 'Web, iOS, Android',
    'description'            => 'Restoran yönetimini dijitalleştiren bulut tabanlı yazılım: QR menü, adisyon programı, mutfak ekranı, rezervasyon ve stok takibi.',
    'url'                    => $baseUrl,
    'featureList'            => [
        'QR Menü Sistemi',
        'Adisyon / POS',
        'Mutfak Ekranı (KDS)',
        'Rezervasyon Takibi',
        'Stok ve Maliyet Yönetimi',
        'Çoklu Şube Yönetimi',
        'Garson Çağrı Sistemi',
        'Raporlama ve Analitik',
    ],
];

if ($pageType === 'home') {
    // FAQPage — landing'de sık sorulan sorular için. Google bu schema'yı
    // zengin sonuçlarda gösterebilir ve CTR'yi artırır.
    $jsonLd[] = [
        '@context'   => 'https://schema.org',
        '@type'      => 'FAQPage',
        'mainEntity' => [
            [
                '@type'          => 'Question',
                'name'           => 'QORDY nedir?',
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text'  => 'QORDY, QR menü, adisyon programı, mutfak ekranı, stok ve rezervasyon yönetimini tek platformda sunan bulut tabanlı restoran yönetim yazılımıdır.',
                ],
            ],
            [
                '@type'          => 'Question',
                'name'           => 'QORDY ücretsiz denenebilir mi?',
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text'  => 'Evet, QORDY\'yi 14 gün boyunca ücretsiz deneyebilirsiniz. Kredi kartı bilgisi istenmez ve istediğiniz zaman iptal edebilirsiniz.',
                ],
            ],
            [
                '@type'          => 'Question',
                'name'           => 'QR menü nasıl çalışır?',
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text'  => 'Her masaya özel QR kod atanır. Müşteri telefonuyla kodu okutur, güncel menüyü görür, dilerse sipariş oluşturur ve garsonu çağırabilir. Tüm işlemler gerçek zamanlı olarak panelinize düşer.',
                ],
            ],
            [
                '@type'          => 'Question',
                'name'           => 'Mutfak ekranı (KDS) hangi cihazlarda çalışır?',
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text'  => 'QORDY mutfak ekranı tamamen web tabanlıdır; PC, tablet ve akıllı TV\'lerde çalışır. Siparişler otomatik olarak istasyonlara düşer ve hazırlık süreleri takip edilir.',
                ],
            ],
            [
                '@type'          => 'Question',
                'name'           => 'Çoklu şube yönetimi destekleniyor mu?',
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text'  => 'Evet. Tek panelden birden fazla şubeyi yönetebilir, şube bazlı raporlar alabilir ve merkezi menü/fiyat güncellemesi yapabilirsiniz.',
                ],
            ],
        ],
    ];
}

if ($pageType === 'pricing' && is_array($bootstrapPackages) && !empty($bootstrapPackages)) {
    // Pricing sayfasında her paket için Product + Offer schema'sı.
    foreach ($bootstrapPackages as $p) {
        $priceMonthly = (float)($p['price_monthly'] ?? $p['monthly'] ?? 0);
        if ($priceMonthly <= 0) continue;
        $jsonLd[] = [
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
                'url'           => $baseUrl . '/pricing',
            ],
        ];
    }
}

$metaInject  = '<meta name="csrf-token" content="' . htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') . "\">\n    ";
$metaInject .= '<meta name="description" content="' . htmlspecialchars($seo['description'], ENT_QUOTES, 'UTF-8') . "\">\n    ";
$metaInject .= '<meta name="robots" content="' . htmlspecialchars($seo['robots'], ENT_QUOTES, 'UTF-8') . "\">\n    ";
$metaInject .= '<meta name="author" content="QORDY">' . "\n    ";
$metaInject .= '<meta name="keywords" content="restoran yönetim yazılımı, adisyon programı, QR menü, mutfak ekranı, KDS, POS sistemi, restoran yazılımı, garson çağrı, stok yönetimi, rezervasyon yazılımı">' . "\n    ";
$metaInject .= '<link rel="canonical" href="' . htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8') . "\">\n    ";
$metaInject .= '<link rel="alternate" hreflang="tr" href="' . htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8') . "\">\n    ";
$metaInject .= '<link rel="alternate" hreflang="x-default" href="' . htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8') . "\">\n    ";

// Open Graph
$metaInject .= '<meta property="og:type" content="website">' . "\n    ";
$metaInject .= '<meta property="og:site_name" content="QORDY">' . "\n    ";
$metaInject .= '<meta property="og:locale" content="tr_TR">' . "\n    ";
$metaInject .= '<meta property="og:title" content="' . htmlspecialchars($seo['title'], ENT_QUOTES, 'UTF-8') . "\">\n    ";
$metaInject .= '<meta property="og:description" content="' . htmlspecialchars($seo['description'], ENT_QUOTES, 'UTF-8') . "\">\n    ";
$metaInject .= '<meta property="og:url" content="' . htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8') . "\">\n    ";
$metaInject .= '<meta property="og:image" content="' . htmlspecialchars($ogImage, ENT_QUOTES, 'UTF-8') . "\">\n    ";
$metaInject .= '<meta property="og:image:width" content="1200">' . "\n    ";
$metaInject .= '<meta property="og:image:height" content="630">' . "\n    ";

// Twitter Card
$metaInject .= '<meta name="twitter:card" content="summary_large_image">' . "\n    ";
$metaInject .= '<meta name="twitter:site" content="@qordyapp">' . "\n    ";
$metaInject .= '<meta name="twitter:title" content="' . htmlspecialchars($seo['title'], ENT_QUOTES, 'UTF-8') . "\">\n    ";
$metaInject .= '<meta name="twitter:description" content="' . htmlspecialchars($seo['description'], ENT_QUOTES, 'UTF-8') . "\">\n    ";
$metaInject .= '<meta name="twitter:image" content="' . htmlspecialchars($twitterImage, ENT_QUOTES, 'UTF-8') . "\">\n    ";

// JSON-LD bloğu
foreach ($jsonLd as $doc) {
    $metaInject .= '<script type="application/ld+json">'
        . json_encode($doc, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG)
        . "</script>\n    ";
}

// Bootstrap script'i
$metaInject .= '<script>window.__QORDY__ = '
    . json_encode($bootstrap, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE)
    . ';</script>' . "\n  ";

// Mevcut `<title>` etiketini sayfa tipine göre override et. Build edilmiş
// SPA her zaman aynı başlığı yazıyor; canonical path'e göre farklı başlık
// göstermek SEO için kritik.
$html = preg_replace(
    '#<title>.*?</title>#',
    '<title>' . htmlspecialchars($seo['title'], ENT_QUOTES, 'UTF-8') . '</title>',
    $html,
    1
);

echo str_replace('</head>', $metaInject . '</head>', $html);
