<?php
namespace App\Services;

require_once __DIR__ . '/TranslationService.php';

/**
 * SEO Service - MVC, OOP, Centralized, Dynamic Multi-Language SEO
 * Handles all SEO operations with multi-language support
 */
class SEOService {
    private $translationService;
    private $baseUrl;
    private $defaultLanguage = 'tr';
    private $supportedLanguages = ['tr', 'en'];
    
    /**
     * Constructor with dependency injection
     * @param \App\Services\TranslationService|null $translationService Optional translation service
     */
    public function __construct(?\App\Services\TranslationService $translationService = null) {
        if ($translationService !== null) {
            $this->translationService = $translationService;
        } else {
            // Use DependencyFactory or helper function for DI
            try {
                require_once __DIR__ . '/../core/DependencyFactory.php';
                // TranslationService might not be in DependencyFactory, use helper
                if (function_exists('getTranslationService')) {
                    $this->translationService = getTranslationService();
                } else {
                    $this->translationService = new TranslationService();
                }
            } catch (\Exception $e) {
                // Fallback
                $this->translationService = new TranslationService();
            }
        }
        $this->baseUrl = defined('BASE_URL') ? BASE_URL : '';
    }
    
    /**
     * Generate SEO meta tags for a page
     * @param string $page - Page identifier (e.g., 'dashboard', 'menu', 'order')
     * @param array $params - Additional parameters (title, description, etc.)
     * @param string|null $lang - Language code
     * @return string - HTML meta tags
     */
    public function generateMetaTags($page, $params = [], $lang = null) {
        if ($lang === null) {
            $lang = $this->translationService->getCurrentLanguage();
        }
        
        $title = $this->getPageTitle($page, $params, $lang);
        $description = $this->getPageDescription($page, $params, $lang);
        $keywords = $this->getPageKeywords($page, $params, $lang);
        $canonical = $this->getCanonicalUrl($page, $params, $lang);
        $ogTags = $this->getOpenGraphTags($page, $params, $lang);
        $alternateLanguages = $this->getAlternateLanguageTags($page, $params);
        
        $html = '';
        
        // Charset meta tag (if not already in layout)
        $html .= '<meta charset="UTF-8">' . "\n";
        
        // Basic meta tags - ensure null values are converted to empty strings
        $html .= sprintf('<title>%s</title>', htmlspecialchars($title ?? '', ENT_QUOTES, 'UTF-8')) . "\n";
        $html .= sprintf('<meta name="description" content="%s">', htmlspecialchars($description ?? '', ENT_QUOTES, 'UTF-8')) . "\n";
        $html .= sprintf('<meta name="keywords" content="%s">', htmlspecialchars($keywords ?? '', ENT_QUOTES, 'UTF-8')) . "\n";
        $html .= sprintf('<meta name="language" content="%s">', $lang) . "\n";
        
        // Canonical URL
        $html .= sprintf('<link rel="canonical" href="%s">', htmlspecialchars($canonical ?? '', ENT_QUOTES, 'UTF-8')) . "\n";
        
        // Alternate language links (hreflang)
        $html .= $alternateLanguages;
        
        // Open Graph tags
        $html .= $ogTags;
        
        // Twitter Card tags
        $html .= $this->getTwitterCardTags($page, $params, $lang);
        
        return $html;
    }
    
    /**
     * Get page title
     * @param string $page
     * @param array $params
     * @param string $lang
     * @return string
     */
    private function getPageTitle($page, $params, $lang) {
        // Try to get site_name from settings first
        $siteName = null;
        try {
            require_once __DIR__ . '/../core/DependencyFactory.php';
            $settingsService = \App\Core\DependencyFactory::getSystemSettingsService();
            $siteName = $settingsService->getSiteName();
        } catch (\Exception $e) {
            // Fallback to translation if settings service fails
        }
        
        // If site_name not found in settings, use translation
        if (empty($siteName)) {
            $siteName = $this->translationService->translate('site.name', $lang);
        }
        
        $pageTitle = $this->translationService->translate("seo.{$page}.title", $lang, $params);
        
        if ($pageTitle === "seo.{$page}.title") {
            // Fallback to page name
            $pageTitle = $this->translationService->translate("pages.{$page}", $lang);
        }
        
        return $pageTitle . ' - ' . $siteName;
    }
    
    /**
     * Get page description
     * @param string $page
     * @param array $params
     * @param string $lang
     * @return string
     */
    private function getPageDescription($page, $params, $lang) {
        $description = $this->translationService->translate("seo.{$page}.description", $lang, $params);
        
        if ($description === "seo.{$page}.description") {
            // Fallback to default
            $description = $this->translationService->translate('seo.default.description', $lang);
        }
        
        return $description;
    }
    
    /**
     * Get page keywords
     * @param string $page
     * @param array $params
     * @param string $lang
     * @return string
     */
    private function getPageKeywords($page, $params, $lang) {
        $keywords = $this->translationService->translate("seo.{$page}.keywords", $lang, $params);
        
        if ($keywords === "seo.{$page}.keywords") {
            // Fallback to default
            $keywords = $this->translationService->translate('seo.default.keywords', $lang);
        }
        
        return $keywords;
    }
    
    /**
     * Get canonical URL
     * @param string $page
     * @param array $params
     * @param string $lang
     * @return string
     */
    private function getCanonicalUrl($page, $params, $lang) {
        $url = rtrim($this->baseUrl, '/');

        // Add language prefix if not default
        if ($lang !== $this->defaultLanguage) {
            $url .= '/' . $lang;
        }

        // Ana sayfa için kanonik URL `/home` DEĞİL, site kökü olmalı.
        // Aksi takdirde Google Search Console "alternate page with proper
        // canonical tag" uyarısı verir ve homepage indexlenmez.
        $isHome = ($page === '' || $page === '/' || $page === 'home' || $page === 'anasayfa' || $page === 'landing');

        if ($isHome) {
            return $url === '' ? '/' : $url;
        }

        // Add page path
        $url .= '/' . ltrim((string)$page, '/');

        // Add query parameters if needed
        if (!empty($params['id'])) {
            $url .= '/' . rawurlencode((string)$params['id']);
        }

        return $url;
    }
    
    /**
     * Get alternate language tags (hreflang)
     * @param string $page
     * @param array $params
     * @return string
     */
    private function getAlternateLanguageTags($page, $params) {
        $html = '';
        
        foreach ($this->supportedLanguages as $lang) {
            $url = $this->getCanonicalUrl($page, $params, $lang);
            $html .= sprintf('<link rel="alternate" hreflang="%s" href="%s">', $lang, htmlspecialchars($url ?? '', ENT_QUOTES, 'UTF-8')) . "\n";
        }
        
        // x-default
        $defaultUrl = $this->getCanonicalUrl($page, $params, $this->defaultLanguage);
        $html .= sprintf('<link rel="alternate" hreflang="x-default" href="%s">', htmlspecialchars($defaultUrl ?? '', ENT_QUOTES, 'UTF-8')) . "\n";
        
        return $html;
    }
    
    /**
     * Get Open Graph tags
     * @param string $page
     * @param array $params
     * @param string $lang
     * @return string
     */
    private function getOpenGraphTags($page, $params, $lang) {
        // Try to get site_name from settings first
        $siteName = null;
        try {
            require_once __DIR__ . '/../core/DependencyFactory.php';
            $settingsService = \App\Core\DependencyFactory::getSystemSettingsService();
            $siteName = $settingsService->getSiteName();
        } catch (\Exception $e) {
            // Fallback to translation if settings service fails
        }
        
        // If site_name not found in settings, use translation
        if (empty($siteName)) {
            $siteName = $this->translationService->translate('site.name', $lang);
        }
        
        $title = $this->getPageTitle($page, $params, $lang);
        $description = $this->getPageDescription($page, $params, $lang);
        $url = $this->getCanonicalUrl($page, $params, $lang);
        $image = $this->baseUrl . '/assets/images/og-image.jpg';
        
        $html = '';
        $html .= sprintf('<meta property="og:type" content="website">') . "\n";
        $html .= sprintf('<meta property="og:title" content="%s">', htmlspecialchars($title ?? '', ENT_QUOTES, 'UTF-8')) . "\n";
        $html .= sprintf('<meta property="og:description" content="%s">', htmlspecialchars($description ?? '', ENT_QUOTES, 'UTF-8')) . "\n";
        $html .= sprintf('<meta property="og:url" content="%s">', htmlspecialchars($url ?? '', ENT_QUOTES, 'UTF-8')) . "\n";
        $html .= sprintf('<meta property="og:image" content="%s">', htmlspecialchars($image ?? '', ENT_QUOTES, 'UTF-8')) . "\n";
        $html .= sprintf('<meta property="og:site_name" content="%s">', htmlspecialchars($siteName ?? '', ENT_QUOTES, 'UTF-8')) . "\n";
        $html .= sprintf('<meta property="og:locale" content="%s">', $lang === 'tr' ? 'tr_TR' : 'en_US') . "\n";
        
        return $html;
    }
    
    /**
     * Get Twitter Card tags
     * @param string $page
     * @param array $params
     * @param string $lang
     * @return string
     */
    private function getTwitterCardTags($page, $params, $lang) {
        $title = $this->getPageTitle($page, $params, $lang);
        $description = $this->getPageDescription($page, $params, $lang);
        $image = $this->baseUrl . '/assets/images/twitter-card.jpg';
        
        $html = '';
        $html .= sprintf('<meta name="twitter:card" content="summary_large_image">') . "\n";
        $html .= sprintf('<meta name="twitter:title" content="%s">', htmlspecialchars($title ?? '', ENT_QUOTES, 'UTF-8')) . "\n";
        $html .= sprintf('<meta name="twitter:description" content="%s">', htmlspecialchars($description ?? '', ENT_QUOTES, 'UTF-8')) . "\n";
        $html .= sprintf('<meta name="twitter:image" content="%s">', htmlspecialchars($image ?? '', ENT_QUOTES, 'UTF-8')) . "\n";
        
        return $html;
    }
    
    /**
     * Generate structured data (JSON-LD)
     * @param string $page
     * @param array $params
     * @param string $lang
     * @return string
     */
    public function generateStructuredData($page, $params = [], $lang = null) {
        if ($lang === null) {
            $lang = $this->translationService->getCurrentLanguage();
        }
        
        // Try to get site_name from settings first
        $siteName = null;
        try {
            require_once __DIR__ . '/../core/DependencyFactory.php';
            $settingsService = \App\Core\DependencyFactory::getSystemSettingsService();
            $siteName = $settingsService->getSiteName();
        } catch (\Exception $e) {
            // Fallback to translation if settings service fails
        }
        
        // If site_name not found in settings, use translation
        if (empty($siteName)) {
            $siteName = $this->translationService->translate('site.name', $lang);
        }
        
        $url = $this->getCanonicalUrl($page, $params, $lang);
        $description = $this->getPageDescription($page, $params, $lang);
        
        $structuredDataArray = [];
        
        // 1. Organization Schema
        $structuredDataArray[] = [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => $siteName,
            'url' => $this->baseUrl,
            'logo' => $this->baseUrl . '/assets/images/logo.png',
            'description' => $description,
            'sameAs' => [
                // Social media links can be added here from settings
            ],
            'contactPoint' => [
                '@type' => 'ContactPoint',
                'contactType' => 'customer service',
                'email' => 'info@qordy.com',
                'availableLanguage' => ['Turkish', 'English']
            ]
        ];
        
        // 2. SoftwareApplication Schema (main product)
        //
        // NOT: Önceki sürüm `aggregateRating` alanını sabit 4.8 / 150 ile
        // dolduruyordu. Bu, gerçek kullanıcı oyu olmadığı için Google'ın
        // "Structured data policies" altında yanıltıcı içerik sayılır ve
        // yaptırıma yol açabilir. Rating ancak gerçek review verisi
        // eklenince açılmalı — bu yüzden schema'dan çıkardık.
        //
        // Ayrıca `price=0` de yanıltıcıydı (ürün ücretli); Offer bloğunu
        // değişken bırakıp fiyatı Pricing sayfasındaki Product/Offer JSON-LD
        // ile ifade ediyoruz (aşağıda).
        $structuredDataArray[] = [
            '@context' => 'https://schema.org',
            '@type' => 'SoftwareApplication',
            'name' => $siteName,
            'applicationCategory' => 'BusinessApplication',
            'applicationSubCategory' => $lang === 'tr' ? 'Restoran Yönetim Yazılımı' : 'Restaurant Management Software',
            'operatingSystem' => 'Web, iOS, Android',
            'description' => $lang === 'tr'
                ? 'Restoran yönetimini dijitalleştirin. QR menü, adisyon programı, mutfak ekranı, stok ve raporlama tek platformda.'
                : 'Digitize restaurant management. QR menu, POS, kitchen display system, inventory and reporting on a single platform.',
            'featureList' => [
                $lang === 'tr' ? 'QR Menü Sistemi' : 'QR Menu System',
                $lang === 'tr' ? 'Adisyon / POS Sistemi' : 'POS System',
                $lang === 'tr' ? 'Mutfak Ekranı (KDS)' : 'Kitchen Display System',
                $lang === 'tr' ? 'Rezervasyon Takibi' : 'Reservation Tracking',
                $lang === 'tr' ? 'Stok ve Maliyet Yönetimi' : 'Inventory and Cost Management',
                $lang === 'tr' ? 'Raporlama ve Analitik' : 'Reporting and Analytics',
                $lang === 'tr' ? 'Çoklu Şube Yönetimi' : 'Multi-branch Management',
                $lang === 'tr' ? 'Garson Çağrı Sistemi' : 'Waiter Call System',
            ],
            'screenshot' => $this->baseUrl . '/assets/images/og-image.jpg',
            'url' => $this->baseUrl,
        ];
        
        // 3. Product Schema for features
        if ($page === 'features' || $page === 'home') {
            $structuredDataArray[] = [
                '@context' => 'https://schema.org',
                '@type' => 'Product',
                'name' => $lang === 'tr' ? 'QR Menü Sistemi' : 'QR Menu System',
                'description' => $lang === 'tr' 
                    ? 'Dijital QR menü sistemi ile müşterilerinize temasız menü deneyimi sunun.'
                    : 'Offer contactless menu experience to your customers with digital QR menu system.',
                'brand' => [
                    '@type' => 'Brand',
                    'name' => $siteName
                ],
                'category' => $lang === 'tr' ? 'Restoran Yazılımı' : 'Restaurant Software'
            ];
        }
        
        // 4. BreadcrumbList Schema (for navigation)
        $breadcrumbs = $this->generateBreadcrumbs($page, $params, $lang);
        if (!empty($breadcrumbs)) {
            $structuredDataArray[] = [
                '@context' => 'https://schema.org',
                '@type' => 'BreadcrumbList',
                'itemListElement' => $breadcrumbs
            ];
        }
        
        // Combine all structured data
        $output = '';
        foreach ($structuredDataArray as $data) {
            $output .= '<script type="application/ld+json">' . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
        }
        
        return $output;
    }
    
    /**
     * Generate breadcrumbs for structured data
     * @param string $page
     * @param array $params
     * @param string $lang
     * @return array
     */
    private function generateBreadcrumbs($page, $params = [], $lang = null) {
        if ($lang === null) {
            $lang = $this->translationService->getCurrentLanguage();
        }
        
        $breadcrumbs = [];
        $position = 1;
        
        // Home
        $breadcrumbs[] = [
            '@type' => 'ListItem',
            'position' => $position++,
            'name' => $lang === 'tr' ? 'Ana Sayfa' : 'Home',
            'item' => $this->baseUrl
        ];
        
        // Page-specific breadcrumbs
        if ($page !== 'home') {
            $pageName = $this->translationService->translate("pages.{$page}", $lang);
            if ($pageName === "pages.{$page}") {
                $pageName = ucfirst($page);
            }
            
            $breadcrumbs[] = [
                '@type' => 'ListItem',
                'position' => $position++,
                'name' => $pageName,
                'item' => $this->getCanonicalUrl($page, $params, $lang)
            ];
        }
        
        return $breadcrumbs;
    }
}

