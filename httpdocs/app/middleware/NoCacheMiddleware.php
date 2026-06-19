<?php
namespace App\Middleware;

/**
 * No-Cache Middleware
 * API, admin paneli ve dinamik sayfalar için agresif cache prevention
 */
class NoCacheMiddleware {
    /**
     * Dinamik içerik için cache'i tamamen devre dışı bırak
     */
    public static function handle(): void {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        
        // API, admin, dinamik sayfalar için NO-CACHE
        $noCachePaths = [
            '/api/',
            '/qodmin/',
            '/business/',
            '/admin/',
            '/pos/',
            '/waiter/',
            '/kitchen/',
            '/preparation-screen/',
            '/receipt/',
            '/login',
            '/register',
            '/logout',
            '/auth/',
        ];
        
        $shouldNoCache = false;
        foreach ($noCachePaths as $path) {
            if (strpos($uri, $path) !== false) {
                $shouldNoCache = true;
                break;
            }
        }
        
        if ($shouldNoCache) {
            // AGRESIF NO-CACHE HEADERS
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Cache-Control: post-check=0, pre-check=0', false);
            header('Pragma: no-cache');
            header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
            
            // ETags'i devre dışı bırak
            header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
            
            // Vary header ekle (proxy cache için)
            header('Vary: *');
        } else {
            // Statik asset'ler için uzun cache (sadece images, fonts)
            if (self::isStaticAsset($uri)) {
                // 1 yıl cache (değişirse URL'deki version parametresi değişecek)
                header('Cache-Control: public, max-age=31536000, immutable');
            }
        }
    }
    
    /**
     * URI statik asset mi kontrol et
     */
    private static function isStaticAsset(string $uri): bool {
        $staticExtensions = [
            '.jpg', '.jpeg', '.png', '.gif', '.svg', '.webp', '.ico',
            '.woff', '.woff2', '.ttf', '.eot', '.otf'
        ];
        
        foreach ($staticExtensions as $ext) {
            if (substr($uri, -strlen($ext)) === $ext) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Tüm cache header'larını temizle (development için)
     */
    public static function clearAllCacheHeaders(): void {
        header_remove('Cache-Control');
        header_remove('Pragma');
        header_remove('Expires');
        header_remove('Last-Modified');
        header_remove('ETag');
    }
}
