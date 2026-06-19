<?php
/**
 * Merkezi Asset Yardımcı Fonksiyonları
 * Dinamik asset URL'leri ve dosya yolları için
 */

if (!function_exists('asset')) {
    /**
     * Dinamik asset URL'si oluşturur
     * OTOMATIK CACHE BUSTING: Her asset'e version parametresi ekler
     * @param string $path Asset dosyasının yolu
     * @return string Tam asset URL'si (cache buster ile)
     */
    function asset(string $path): string {
        $basePath = defined('BASE_URL') ? BASE_URL : '';
        $assetPath = '/assets';
        
        // RouteManager varsa ondan al
        if (class_exists('App\\Core\\RouteManager')) {
            $url = \App\Core\RouteManager::asset($path);
        } else {
            $url = $basePath . $assetPath . '/' . ltrim($path, '/');
        }
        
        // CACHE BUSTER: Dosya varsa mtime, yoksa sistem timestamp kullan
        $version = getAssetVersion($path);
        $separator = (strpos($url, '?') !== false) ? '&' : '?';
        
        return $url . $separator . 'v=' . $version;
    }
}

if (!function_exists('image')) {
    /**
     * Dinamik resim URL'si oluşturur
     * @param string $path Resim dosyasının yolu
     * @return string Tam resim URL'si
     */
    function image(string $path): string {
        $basePath = defined('BASE_URL') ? BASE_URL : '';
        $imagePath = '/assets/images';
        
        // RouteManager varsa ondan al
        if (class_exists('App\\Core\\RouteManager')) {
            return \App\Core\RouteManager::image($path);
        }
        
        return $basePath . $imagePath . '/' . ltrim($path, '/');
    }
}

if (!function_exists('js')) {
    /**
     * Dinamik JavaScript URL'si oluşturur
     * OTOMATIK CACHE BUSTING: Her JS dosyasına version parametresi ekler
     * @param string $path JS dosyasının yolu
     * @return string Tam JS URL'si (cache buster ile)
     */
    function js(string $path): string {
        $basePath = defined('BASE_URL') ? BASE_URL : '';
        $jsPath = '/assets/js';
        
        // RouteManager varsa ondan al
        if (class_exists('App\\Core\\RouteManager')) {
            $url = \App\Core\RouteManager::js($path);
        } else {
            $url = $basePath . $jsPath . '/' . ltrim($path, '/');
        }
        
        // CACHE BUSTER
        $version = getAssetVersion('js/' . $path);
        $separator = (strpos($url, '?') !== false) ? '&' : '?';
        
        return $url . $separator . 'v=' . $version;
    }
}

if (!function_exists('css')) {
    /**
     * Dinamik CSS URL'si oluşturur
     * OTOMATIK CACHE BUSTING: Her CSS dosyasına version parametresi ekler
     * @param string $path CSS dosyasının yolu
     * @return string Tam CSS URL'si (cache buster ile)
     */
    function css(string $path): string {
        $basePath = defined('BASE_URL') ? BASE_URL : '';
        $cssPath = '/assets/css';
        
        // RouteManager varsa ondan al
        if (class_exists('App\\Core\\RouteManager')) {
            $url = \App\Core\RouteManager::css($path);
        } else {
            $url = $basePath . $cssPath . '/' . ltrim($path, '/');
        }
        
        // CACHE BUSTER
        $version = getAssetVersion('css/' . $path);
        $separator = (strpos($url, '?') !== false) ? '&' : '?';
        
        return $url . $separator . 'v=' . $version;
    }
}

if (!function_exists('upload_url')) {
    /**
     * Dinamik upload URL'si oluşturur
     * @param string $path Upload dosyasının yolu
     * @param string $entity Varlık türü (menu, user, table vs.)
     * @param string|null $size Resim boyutu (thumb, small, medium, large)
     * @return string Tam upload URL'si
     */
    function upload_url(string $path, string $entity = 'general', ?string $size = null): string {
        $basePath = defined('BASE_URL') ? BASE_URL : '';
        
        if ($size) {
            return $basePath . "/images/{$entity}/{$size}/" . ltrim($path, '/');
        }
        
        return $basePath . "/uploads/{$entity}/" . ltrim($path, '/');
    }
}

if (!function_exists('cdn_url')) {
    /**
     * CDN URL'si oluşturur (eğer yapılandırılmışsa)
     * @param string $path Dosya yolu
     * @return string CDN URL'si veya normal URL
     */
    function cdn_url(string $path): string {
        $cdnUrl = isset($_ENV['CDN_URL']) && !empty($_ENV['CDN_URL']) ? $_ENV['CDN_URL'] : null;
        
        if ($cdnUrl) {
            return rtrim($cdnUrl, '/') . '/' . ltrim($path, '/');
        }
        
        // CDN yapılandırılmamışsa normal asset URL'si döndür
        return asset($path);
    }
}

if (!function_exists('versioned_asset')) {
    /**
     * Versiyonlu asset URL'si oluşturur (cache busting için)
     * @param string $path Asset dosyasının yolu
     * @param string|null $version Versiyon numarası (null ise dosya zaman damgası kullanılır)
     * @return string Versiyonlu asset URL'si
     */
    function versioned_asset(string $path, ?string $version = null): string {
        $url = asset($path);
        
        if (!$version) {
            $fullPath = $_SERVER['DOCUMENT_ROOT'] . parse_url($url, PHP_URL_PATH);
            if (file_exists($fullPath)) {
                $version = filemtime($fullPath);
            } else {
                $version = time(); // Dosya bulunamazsa mevcut zaman
            }
        }
        
        $separator = strpos($url, '?') !== false ? '&' : '?';
        return $url . $separator . 'v=' . $version;
    }
}

if (!function_exists('responsive_image')) {
    /**
     * Responsive resim URL'si oluşturur
     * @param string $path Resim dosyasının yolu
     * @param string $entity Varlık türü
     * @param string $id Varlık ID'si
     * @param array $sizes Kullanılabilir boyutlar ['small' => '150x150', 'medium' => '300x300', 'large' => '600x600']
     * @return array Responsive resim URL'leri
     */
    function responsive_image(string $path, string $entity, string $id, array $sizes = []): array {
        $responsiveImages = [];
        
        // Varsayılan boyutlar
        $defaultSizes = [
            'thumb' => '150x150',
            'small' => '300x300',
            'medium' => '600x600',
            'large' => '1200x1200'
        ];
        
        $sizes = array_merge($defaultSizes, $sizes);
        
        foreach ($sizes as $sizeName => $dimensions) {
            $responsiveImages[$sizeName] = upload_url($path, $entity, $sizeName);
        }
        
        return $responsiveImages;
    }
}

if (!function_exists('get_image_alt')) {
    /**
     * Resim için alternatif metin oluşturur
     * @param string $imageName Resim adı
     * @param string $context Kullanım bağlamı
     * @return string Alternatif metin
     */
    function get_image_alt(string $imageName, string $context = ''): string {
        $cleanName = pathinfo($imageName, PATHINFO_FILENAME);
        $cleanName = str_replace(['-', '_', '.'], ' ', $cleanName);
        $cleanName = ucwords(strtolower($cleanName));
        
        if ($context) {
            return $context . ' - ' . $cleanName;
        }
        
        return $cleanName;
    }
}

if (!function_exists('getAssetVersion')) {
    /**
     * Asset için cache buster version döndürür
     * Dosya varsa mtime, yoksa cache_version.php'den okur
     * @param string $path Asset yolu
     * @return int Version numarası (timestamp)
     */
    function getAssetVersion(string $path): int {
        static $globalVersion = null;
        
        // Global version cache'i
        if ($globalVersion === null) {
            $versionFile = __DIR__ . '/../../cache_version.php';
            if (file_exists($versionFile)) {
                ob_start();
                include $versionFile;
                $globalVersion = (int) ob_get_clean();
            } else {
                $globalVersion = time();
            }
        }
        
        // Dosya yolu oluştur
        $assetPath = $_SERVER['DOCUMENT_ROOT'] . '/assets/' . ltrim($path, '/');
        
        // Dosya varsa mtime kullan (en güvenilir)
        if (file_exists($assetPath)) {
            return filemtime($assetPath);
        }
        
        // Public assets klasörünü dene
        $publicAssetPath = $_SERVER['DOCUMENT_ROOT'] . '/public/assets/' . ltrim($path, '/');
        if (file_exists($publicAssetPath)) {
            return filemtime($publicAssetPath);
        }
        
        // Dosya yoksa global version kullan
        return $globalVersion;
    }
}

if (!function_exists('get_favicon_url')) {
    /**
     * Favicon URL'si oluşturur
     * @return string Favicon URL'si
     */
    function get_favicon_url(): string {
        $faviconPath = $_SESSION['settings']['favicon_path'] ?? '/assets/images/favicon.ico';
        
        if (file_exists($_SERVER['DOCUMENT_ROOT'] . $faviconPath)) {
            return $faviconPath;
        }
        
        return asset('images/favicon.ico');
    }
}

if (!function_exists('get_logo_url')) {
    /**
     * Logo URL'si oluşturur
     * @return string Logo URL'si
     */
    function get_logo_url(): string {
        if (function_exists('getQordyLogoUrl')) {
            return getQordyLogoUrl();
        }

        $logoPath = $_SESSION['settings']['logo_path'] ?? '/assets/images/logo.png';
        
        if (file_exists($_SERVER['DOCUMENT_ROOT'] . $logoPath)) {
            return $logoPath;
        }
        
        return asset('images/logo.png');
    }
}