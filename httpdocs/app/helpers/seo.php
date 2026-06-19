<?php
/**
 * SEO Helper Functions
 * Provides functions for generating SEO meta tags, structured data, etc.
 */

if (!function_exists('generateMetaTags')) {
    /**
     * Generate HTML meta tags
     * @param string $title Page title
     * @param string $description Page description
     * @param string $keywords Comma-separated keywords
     * @param string $image Image URL for OG
     * @param string $url Canonical URL
     * @return string HTML meta tags
     */
    function generateMetaTags($title, $description = '', $keywords = '', $image = '', $url = '') {
        if (!defined('BASE_URL')) {
            throw new \Exception('BASE_URL constant is not defined. Please check your configuration.');
        }
        $baseUrl = BASE_URL;
        // Get site name from database (via helper function)
        $siteName = function_exists('getSiteName') ? getSiteName() : (defined('SITE_NAME') ? SITE_NAME : 'Qordy');
        
        if (empty($url)) {
            $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
            $url = $baseUrl . $requestUri;
        } else {
            $url = $baseUrl . $url;
        }
        
        if (empty($image)) {
            $image = $baseUrl . '/assets/images/logo.png';
        } elseif (strpos($image, 'http') !== 0) {
            $image = $baseUrl . $image;
        }
        
        $tags = [];
        
        // Basic meta tags
        $tags[] = '<meta charset="UTF-8">';
        $tags[] = '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
        $tags[] = '<title>' . htmlspecialchars($title) . '</title>';
        
        if (!empty($description)) {
            $tags[] = '<meta name="description" content="' . htmlspecialchars($description) . '">';
        }
        
        if (!empty($keywords)) {
            $tags[] = '<meta name="keywords" content="' . htmlspecialchars($keywords) . '">';
        }
        
        // Canonical URL
        $tags[] = '<link rel="canonical" href="' . htmlspecialchars($url) . '">';
        
        // Open Graph tags
        $tags[] = '<meta property="og:type" content="website">';
        $tags[] = '<meta property="og:title" content="' . htmlspecialchars($title) . '">';
        if (!empty($description)) {
            $tags[] = '<meta property="og:description" content="' . htmlspecialchars($description) . '">';
        }
        $tags[] = '<meta property="og:url" content="' . htmlspecialchars($url) . '">';
        $tags[] = '<meta property="og:image" content="' . htmlspecialchars($image) . '">';
        $tags[] = '<meta property="og:site_name" content="' . htmlspecialchars($siteName) . '">';
        $tags[] = '<meta property="og:locale" content="tr_TR">';
        
        // Twitter Card tags
        $tags[] = '<meta name="twitter:card" content="summary_large_image">';
        $tags[] = '<meta name="twitter:title" content="' . htmlspecialchars($title) . '">';
        if (!empty($description)) {
            $tags[] = '<meta name="twitter:description" content="' . htmlspecialchars($description) . '">';
        }
        $tags[] = '<meta name="twitter:image" content="' . htmlspecialchars($image) . '">';
        
        // Language
        $tags[] = '<meta http-equiv="content-language" content="tr">';
        
        return implode("\n    ", $tags);
    }
}

if (!function_exists('generateCanonicalUrl')) {
    /**
     * Generate canonical URL
     * @param string $path URL path (optional)
     * @return string Canonical URL
     */
    function generateCanonicalUrl($path = '') {
        if (!defined('BASE_URL')) {
            throw new \Exception('BASE_URL constant is not defined. Please check your configuration.');
        }
        $baseUrl = BASE_URL;
        
        if (empty($path)) {
            $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
            $path = parse_url($requestUri, PHP_URL_PATH);
        }
        
        return $baseUrl . $path;
    }
}

if (!function_exists('generateStructuredData')) {
    /**
     * Generate JSON-LD structured data
     * @param string $type Type of structured data (Restaurant, Menu, MenuItem, etc.)
     * @param array $data Data for structured data
     * @return string JSON-LD script tag
     */
    function generateStructuredData($type, $data) {
        if (!defined('BASE_URL')) {
            throw new \Exception('BASE_URL constant is not defined. Please check your configuration.');
        }
        $baseUrl = BASE_URL;
        
        $structuredData = [
            '@context' => 'https://schema.org',
            '@type' => $type
        ];
        
        // Merge provided data
        $structuredData = array_merge($structuredData, $data);
        
        // Ensure URLs are absolute
        foreach ($structuredData as $key => $value) {
            if (is_string($value) && (strpos($key, 'url') !== false || strpos($key, 'image') !== false || strpos($key, 'logo') !== false)) {
                if (strpos($value, 'http') !== 0 && strpos($value, '/') === 0) {
                    $structuredData[$key] = $baseUrl . $value;
                }
            }
        }
        
        $json = json_encode($structuredData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        
        return '<script type="application/ld+json">' . "\n" . $json . "\n" . '</script>';
    }
}

if (!function_exists('generateRestaurantStructuredData')) {
    /**
     * Generate Restaurant structured data
     * @param array $restaurantData Restaurant information
     * @return string JSON-LD script tag
     */
    function generateRestaurantStructuredData($restaurantData = []) {
        if (!defined('BASE_URL')) {
            throw new \Exception('BASE_URL constant is not defined. Please check your configuration.');
        }
        $baseUrl = BASE_URL;
        // Get site name from database (via helper function)
        $siteName = function_exists('getSiteName') ? getSiteName() : (defined('SITE_NAME') ? SITE_NAME : 'Qordy');
        
        $defaultData = [
            'name' => $siteName,
            'url' => $baseUrl,
            'image' => $baseUrl . '/assets/images/logo.png',
            'servesCuisine' => 'Turkish',
            'priceRange' => '$$'
        ];
        
        $data = array_merge($defaultData, $restaurantData);
        
        return generateStructuredData('Restaurant', $data);
    }
}

if (!function_exists('productNameToFilename')) {
    /**
     * Convert product name to SEO-friendly filename
     * @param string $productName Product name
     * @param string $extension File extension (default: png)
     * @return string SEO-friendly filename
     */
    function productNameToFilename($productName, $extension = 'png') {
        if (empty($productName)) {
            return 'product.' . $extension;
        }
        
        // Convert to lowercase
        $filename = mb_strtolower($productName, 'UTF-8');
        
        // Turkish character transliteration
        $turkish = [
            'ç' => 'c', 'Ç' => 'c',
            'ğ' => 'g', 'Ğ' => 'g',
            'ı' => 'i', 'İ' => 'i',
            'ö' => 'o', 'Ö' => 'o',
            'ş' => 's', 'Ş' => 's',
            'ü' => 'u', 'Ü' => 'u',
        ];
        $filename = str_replace(array_keys($turkish), array_values($turkish), $filename);
        
        // Generic transliteration
        $filename = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $filename);
        
        // Replace non-alphanumeric with dash
        $filename = preg_replace('/[^a-z0-9]+/', '-', $filename);
        
        // Remove multiple dashes
        $filename = preg_replace('/-+/', '-', $filename);
        
        // Trim dashes from ends
        $filename = trim($filename, '-');
        
        // Limit length (max 100 chars for filename, minus extension)
        $maxLength = 100 - strlen($extension) - 1; // -1 for dot
        if (strlen($filename) > $maxLength) {
            $filename = substr($filename, 0, $maxLength);
            $filename = rtrim($filename, '-');
        }
        
        // Ensure we have a valid filename
        if (empty($filename)) {
            $filename = 'product';
        }
        
        return $filename . '.' . $extension;
    }
}

if (!function_exists('generateMenuItemStructuredData')) {
    /**
     * Generate MenuItem structured data
     * @param array $item Menu item data
     * @return string JSON-LD script tag
     */
    function generateMenuItemStructuredData($item) {
        if (!defined('BASE_URL')) {
            throw new \Exception('BASE_URL constant is not defined. Please check your configuration.');
        }
        $baseUrl = BASE_URL;
        
        $data = [
            'name' => $item['name'] ?? '',
            'description' => $item['description'] ?? '',
            'image' => isset($item['image_url']) ? ($item['image_url'] ?: $baseUrl . '/assets/images/default-menu-item.png') : $baseUrl . '/assets/images/default-menu-item.png',
            'offers' => [
                '@type' => 'Offer',
                'price' => $item['price'] ?? 0,
                'priceCurrency' => 'TRY'
            ]
        ];
        
        return generateStructuredData('MenuItem', $data);
    }
}

if (!function_exists('generateMenuMetaTags')) {
    /**
     * Generate SEO meta tags for menu item
     * @param array $menuItem Menu item data with translations
     * @param string $languageCode Language code
     * @return string HTML meta tags
     */
    function generateMenuMetaTags(array $menuItem, string $languageCode = 'tr'): string {
        if (!defined('BASE_URL')) {
            throw new \Exception('BASE_URL constant is not defined. Please check your configuration.');
        }
        $baseUrl = BASE_URL;
        // Get site name from database (via helper function)
        $siteName = function_exists('getSiteName') ? getSiteName() : (defined('SITE_NAME') ? SITE_NAME : 'Qordy');
        
        $metaTitle = $menuItem['meta_title'] ?? ($menuItem['name'] ?? '');
        $metaDescription = $menuItem['meta_description'] ?? ($menuItem['description'] ?? '');
        $metaKeywords = $menuItem['meta_keywords'] ?? '';
        $image = $menuItem['image_url'] ?? ($baseUrl . '/assets/images/logo.png');
        $slug = $menuItem['slug'] ?? '';
        
        // Build canonical URL
        $canonicalUrl = $baseUrl . '/' . $languageCode . '/menu/' . $slug;
        
        $tags = [];
        // Title is set separately in view, so we don't include it here to avoid duplication
        $tags[] = '<meta name="description" content="' . htmlspecialchars($metaDescription) . '">';
        
        if (!empty($metaKeywords)) {
            $tags[] = '<meta name="keywords" content="' . htmlspecialchars($metaKeywords) . '">';
        }
        
        $tags[] = '<link rel="canonical" href="' . htmlspecialchars($canonicalUrl) . '">';
        
        return implode("\n    ", $tags);
    }
}

if (!function_exists('generateMenuOGTags')) {
    /**
     * Generate Open Graph tags for menu item
     * @param array $menuItem Menu item data
     * @param string $languageCode Language code
     * @return string HTML OG tags
     */
    function generateMenuOGTags(array $menuItem, string $languageCode = 'tr'): string {
        if (!defined('BASE_URL')) {
            throw new \Exception('BASE_URL constant is not defined. Please check your configuration.');
        }
        $baseUrl = BASE_URL;
        // Get site name from database (via helper function)
        $siteName = function_exists('getSiteName') ? getSiteName() : (defined('SITE_NAME') ? SITE_NAME : 'Qordy');
        
        $title = $menuItem['meta_title'] ?? ($menuItem['name'] ?? '');
        $description = $menuItem['meta_description'] ?? ($menuItem['description'] ?? '');
        $image = $menuItem['image_url'] ?? ($baseUrl . '/assets/images/logo.png');
        $slug = $menuItem['slug'] ?? '';
        
        // Make image URL absolute
        if (strpos($image, 'http') !== 0) {
            $image = $baseUrl . $image;
        }
        
        $url = $baseUrl . '/' . $languageCode . '/menu/' . $slug;
        
        $localeMap = [
            'tr' => 'tr_TR',
            'en' => 'en_US',
            'de' => 'de_DE',
            'fr' => 'fr_FR',
            'es' => 'es_ES',
            'ar' => 'ar_SA'
        ];
        $locale = $localeMap[$languageCode] ?? 'tr_TR';
        
        $tags = [];
        $tags[] = '<meta property="og:type" content="product">';
        $tags[] = '<meta property="og:title" content="' . htmlspecialchars($title) . '">';
        $tags[] = '<meta property="og:description" content="' . htmlspecialchars($description) . '">';
        $tags[] = '<meta property="og:url" content="' . htmlspecialchars($url) . '">';
        $tags[] = '<meta property="og:image" content="' . htmlspecialchars($image) . '">';
        $tags[] = '<meta property="og:site_name" content="' . htmlspecialchars($siteName) . '">';
        $tags[] = '<meta property="og:locale" content="' . htmlspecialchars($locale) . '">';
        
        // Product-specific OG tags
        if (isset($menuItem['price'])) {
            $tags[] = '<meta property="product:price:amount" content="' . htmlspecialchars($menuItem['price']) . '">';
            $tags[] = '<meta property="product:price:currency" content="TRY">';
        }
        
        return implode("\n    ", $tags);
    }
}

if (!function_exists('generateHreflangTags')) {
    /**
     * Generate hreflang tags for language alternatives
     * @param array $alternateUrls Array of [language_code => url]
     * @return string HTML hreflang tags
     */
    function generateHreflangTags(array $alternateUrls): string {
        $tags = [];
        
        foreach ($alternateUrls as $lang => $url) {
            $tags[] = '<link rel="alternate" hreflang="' . htmlspecialchars($lang) . '" href="' . htmlspecialchars($url) . '">';
        }
        
        // Add x-default if available
        if (isset($alternateUrls['tr'])) {
            $tags[] = '<link rel="alternate" hreflang="x-default" href="' . htmlspecialchars($alternateUrls['tr']) . '">';
        }
        
        return implode("\n    ", $tags);
    }
}

if (!function_exists('generateSitemap')) {
    /**
     * Generate sitemap XML for menu items
     * @param array $menuItems Array of menu items with translations
     * @return string XML sitemap content
     */
    function generateSitemap(array $menuItems): string {
        if (!defined('BASE_URL')) {
            throw new \Exception('BASE_URL constant is not defined. Please check your configuration.');
        }
        $baseUrl = BASE_URL;
        
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . "\n";
        $xml .= '        xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";
        
        foreach ($menuItems as $item) {
            if (empty($item['translations'])) {
                continue;
            }
            
            foreach ($item['translations'] as $lang => $translation) {
                if (empty($translation['slug'])) {
                    continue;
                }
                
                $url = $baseUrl . '/' . $lang . '/menu/' . $translation['slug'];
                $lastmod = date('Y-m-d');
                
                $xml .= '  <url>' . "\n";
                $xml .= '    <loc>' . htmlspecialchars($url) . '</loc>' . "\n";
                $xml .= '    <lastmod>' . $lastmod . '</lastmod>' . "\n";
                $xml .= '    <changefreq>weekly</changefreq>' . "\n";
                $xml .= '    <priority>0.8</priority>' . "\n";
                
                // Add alternate language links
                foreach ($item['translations'] as $altLang => $altTrans) {
                    if ($altLang !== $lang && !empty($altTrans['slug'])) {
                        $altUrl = $baseUrl . '/' . $altLang . '/menu/' . $altTrans['slug'];
                        $xml .= '    <xhtml:link rel="alternate" hreflang="' . htmlspecialchars($altLang) . '" href="' . htmlspecialchars($altUrl) . '"/>' . "\n";
                    }
                }
                
                $xml .= '  </url>' . "\n";
            }
        }
        
        $xml .= '</urlset>';
        
        return $xml;
    }
}

