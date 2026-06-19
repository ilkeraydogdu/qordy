<?php
namespace App\Core;

/**
 * Merkezi Route Yönetimi Sistemi
 * Dinamik ve merkezi route tanımlamaları için
 */
class RouteManager {
    private static $routes = [];
    private static $dynamicRoutes = [];
    private static $compiledRoutes = [];
    private static $basePath = '';
    private static $assetPath = '/public/assets';
    private static $imagePath = '/assets/images';
    private static $jsPath = '/public/assets/js';
    private static $cssPath = '/public/assets/css';

    /**
     * Sistemdeki tüm route'ları yükle
     */
    public static function loadRoutes(): void {
        // Ana route dosyasını yükle
        $routeFile = __DIR__ . '/../config/routes.php';
        if (file_exists($routeFile)) {
            self::$routes = include $routeFile;
        }

        // Modül bazlı route dosyalarını yükle
        self::loadModuleRoutes();
        
        // Dinamik route'ları oluştur
        self::buildDynamicRoutes();
    }

    /**
     * Modül bazlı route dosyalarını yükle
     */
    private static function loadModuleRoutes(): void {
        $moduleDirs = [
            __DIR__ . '/../modules/',
            __DIR__ . '/../features/',
            __DIR__ . '/../components/'
        ];

        foreach ($moduleDirs as $dir) {
            if (is_dir($dir)) {
                $modules = scandir($dir);
                foreach ($modules as $module) {
                    if ($module !== '.' && $module !== '..' && is_dir($dir . $module)) {
                        $routeFile = $dir . $module . '/routes.php';
                        if (file_exists($routeFile)) {
                            $moduleRoutes = include $routeFile;
                            if (is_array($moduleRoutes)) {
                                self::$routes = array_merge(self::$routes, $moduleRoutes);
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Dinamik route'ları oluştur
     */
    private static function buildDynamicRoutes(): void {
        // Asset route'larını oluştur
        self::addAssetRoutes();
        
        // Image route'larını oluştur
        self::addImageRoutes();
        
        // API route'larını oluştur
        self::addApiRoutes();
        
        // Compiled routes oluştur
        self::compileRoutes();
    }

    /**
     * Asset route'larını ekle
     */
    private static function addAssetRoutes(): void {
        // CSS dosyaları için route
        self::$dynamicRoutes['GET:/css/{file}'] = function($file) {
            self::serveAsset(self::$cssPath . '/' . $file, 'text/css');
        };
        
        // JS dosyaları için route
        self::$dynamicRoutes['GET:/js/{file}'] = function($file) {
            self::serveAsset(self::$jsPath . '/' . $file, 'application/javascript');
        };
        
        // Image dosyaları için route
        self::$dynamicRoutes['GET:/images/{file}'] = function($file) {
            self::serveAsset(self::$imagePath . '/' . $file, self::getMimeType($file));
        };
        
        // Genel asset route
        self::$dynamicRoutes['GET:/assets/{path}'] = function($path) {
            $fullPath = self::$assetPath . '/' . $path;
            $mimeType = self::getMimeType($path);
            self::serveAsset($fullPath, $mimeType);
        };
    }

    /**
     * API route'larını ekle
     */
    private static function addApiRoutes(): void {
        // Dinamik API route'ları
        self::$dynamicRoutes['GET:/api/{resource}'] = function($resource) {
            self::handleApiRequest('GET', $resource, $_GET);
        };
        
        self::$dynamicRoutes['POST:/api/{resource}'] = function($resource) {
            $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            self::handleApiRequest('POST', $resource, $input);
        };
        
        self::$dynamicRoutes['PUT:/api/{resource}/{id}'] = function($resource, $id) {
            $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $input['id'] = $id;
            self::handleApiRequest('PUT', $resource, $input);
        };
        
        self::$dynamicRoutes['DELETE:/api/{resource}/{id}'] = function($resource, $id) {
            self::handleApiRequest('DELETE', $resource, ['id' => $id]);
        };
    }

    /**
     * Image route'larını ekle
     */
    private static function addImageRoutes(): void {
        // Dinamik resim route'ları
        self::$dynamicRoutes['GET:/images/{entity}/{id}/{filename}'] = function($entity, $id, $filename) {
            self::serveEntityImage($entity, $id, $filename);
        };
        
        // Responsive resim route'ları
        self::$dynamicRoutes['GET:/images/{entity}/{id}/{size}/{filename}'] = function($entity, $id, $size, $filename) {
            self::serveResponsiveImage($entity, $id, $size, $filename);
        };
    }

    /**
     * Compiled route'ları oluştur
     */
    private static function compileRoutes(): void {
        self::$compiledRoutes = array_merge(self::$routes, self::$dynamicRoutes);
    }

    /**
     * Asset dosyası sun
     */
    private static function serveAsset(string $filePath, string $mimeType): void {
        $fullPath = $_SERVER['DOCUMENT_ROOT'] . $filePath;
        
        if (file_exists($fullPath)) {
            // Cache header'ları ekle
            $lastModified = filemtime($fullPath);
            $etag = md5_file($fullPath);
            
            // Cache kontrolü
            if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) || isset($_SERVER['HTTP_IF_NONE_MATCH'])) {
                if ($_SERVER['HTTP_IF_MODIFIED_SINCE'] === gmdate('D, d M Y H:i:s', $lastModified) . ' GMT' ||
                    $_SERVER['HTTP_IF_NONE_MATCH'] == $etag) {
                    http_response_code(304);
                    exit;
                }
            }
            
            header('Content-Type: ' . $mimeType);
            header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $lastModified) . ' GMT');
            header('ETag: ' . $etag);
            header('Cache-Control: public, max-age=31536000'); // 1 year cache
            
            readfile($fullPath);
            exit;
        } else {
            http_response_code(404);
            echo "Asset not found";
            exit;
        }
    }

    /**
     * Varlık/ID/dosya adı segmentleri için katı doğrulama.
     * Sadece [A-Za-z0-9_-] ve basit dosya adı karakterleri kabul edilir;
     * nokta-dot, slash, backslash veya null byte reddedilir.
     */
    private static function assertSafeSegment(string $value, bool $allowDot = false): bool {
        if ($value === '' || strpos($value, "\0") !== false) {
            return false;
        }
        $pattern = $allowDot ? '/^[A-Za-z0-9._-]+$/' : '/^[A-Za-z0-9_-]+$/';
        if (!preg_match($pattern, $value)) {
            return false;
        }
        // '..' veya gizli dosya (.htaccess, .env vb) reddet
        if ($value === '..' || strpos($value, '..') !== false || $value[0] === '.') {
            return false;
        }
        return true;
    }

    /**
     * Path traversal koruması: gerçek yol (realpath) izin verilen kök
     * dizinin altında kalmalı. symlink / .. bypass engellenir.
     */
    private static function isPathWithinBase(string $fullPath, string $baseDir): bool {
        $realBase = realpath($baseDir);
        if ($realBase === false) {
            return false;
        }
        $realFull = realpath($fullPath);
        if ($realFull === false) {
            return false;
        }
        return strncmp($realFull, rtrim($realBase, '/') . '/', strlen(rtrim($realBase, '/')) + 1) === 0;
    }

    /**
     * Varlık bazlı resim sun
     */
    private static function serveEntityImage(string $entity, string $id, string $filename): void {
        $baseDir = __DIR__ . '/../../public/uploads/';

        if (!self::assertSafeSegment($entity) || !self::assertSafeSegment($id) ||
            !self::assertSafeSegment($filename, true)) {
            self::serveDefaultImage();
            return;
        }

        $filename = basename($filename);
        $imagePath = $baseDir . $entity . '/' . $id . '/' . $filename;

        if (file_exists($imagePath) && self::isPathWithinBase($imagePath, $baseDir)) {
            $mimeType = mime_content_type($imagePath) ?: 'application/octet-stream';
            header('Content-Type: ' . $mimeType);
            header('Cache-Control: public, max-age=31536000');
            readfile($imagePath);
            exit;
        } else {
            // Varsayılan resim sun
            self::serveDefaultImage();
        }
    }

    /**
     * Responsive resim sun
     */
    private static function serveResponsiveImage(string $entity, string $id, string $size, string $filename): void {
        // Boyut doğrulaması
        $allowedSizes = ['thumb', 'small', 'medium', 'large', 'original'];
        if (!in_array($size, $allowedSizes, true)) {
            $size = 'medium'; // Varsayılan boyut
        }

        $baseDir = __DIR__ . '/../../public/uploads/';
        if (!self::assertSafeSegment($entity) || !self::assertSafeSegment($id) ||
            !self::assertSafeSegment($filename, true)) {
            self::serveDefaultImage();
            return;
        }
        $filename = basename($filename);
        $imagePath = $baseDir . $entity . '/' . $id . '/' . $size . '/' . $filename;

        if (file_exists($imagePath) && self::isPathWithinBase($imagePath, $baseDir)) {
            $mimeType = mime_content_type($imagePath) ?: 'application/octet-stream';
            header('Content-Type: ' . $mimeType);
            header('Cache-Control: public, max-age=31536000');
            readfile($imagePath);
            exit;
        } else {
            // Boyutlanmış resim yoksa orijinali sun
            self::serveEntityImage($entity, $id, $filename);
        }
    }

    /**
     * Varsayılan resim sun
     */
    private static function serveDefaultImage(): void {
        $defaultImagePath = __DIR__ . '/../../public/assets/images/default.png';
        if (file_exists($defaultImagePath)) {
            header('Content-Type: image/png');
            header('Cache-Control: public, max-age=31536000');
            readfile($defaultImagePath);
        } else {
            // Eğer varsayılan resim bile yoksa boş bir PNG oluştur
            $im = imagecreate(200, 200);
            $bg = imagecolorallocate($im, 240, 240, 240);
            $textcolor = imagecolorallocate($im, 150, 150, 150);
            imagestring($im, 5, 50, 90, 'No Image', $textcolor);
            header('Content-Type: image/png');
            imagepng($im);
            imagedestroy($im);
        }
        exit;
    }

    /**
     * API isteğini işle
     */
    private static function handleApiRequest(string $method, string $resource, array $data): void {
        // API kontrolcüsüne yönlendir
        // Sanitize resource name to prevent code injection
        $resource = preg_replace('/[^a-zA-Z0-9_]/', '', $resource);
        
        // Convert plural resource names to singular for controller naming
        // categories -> Category, users -> User, etc.
        $singularResource = self::singularize($resource);
        $controllerName = 'App\\Controllers\\' . ucfirst($singularResource) . 'Controller';
        
        // Validate controller class exists and extends Controller base class
        if (class_exists($controllerName)) {
            $reflection = new \ReflectionClass($controllerName);
            if (!$reflection->isSubclassOf('App\\Core\\Controller') && $controllerName !== 'App\\Core\\Controller') {
                \App\Core\ApiResponseHelper::error('Invalid controller', 500, 'INVALID_CONTROLLER');
            }
            
            $controller = new $controllerName();
            
            // Metoda göre işlemi çağır
            switch ($method) {
                case 'GET':
                    if (method_exists($controller, 'index')) {
                        $controller->index();
                    } elseif (isset($data['id']) && method_exists($controller, 'show')) {
                        $controller->show($data['id']);
                    } else {
                        \App\Core\ApiResponseHelper::error('Endpoint not found', 404, 'ENDPOINT_NOT_FOUND');
                    }
                    break;
                case 'POST':
                    if (method_exists($controller, 'store')) {
                        $controller->store();
                    } else {
                        \App\Core\ApiResponseHelper::error('Endpoint not found', 404, 'ENDPOINT_NOT_FOUND');
                    }
                    break;
                case 'PUT':
                    if (isset($data['id']) && method_exists($controller, 'update')) {
                        $controller->update($data['id']);
                    } else {
                        \App\Core\ApiResponseHelper::error('Endpoint not found', 404, 'ENDPOINT_NOT_FOUND');
                    }
                    break;
                case 'DELETE':
                    if (isset($data['id']) && method_exists($controller, 'destroy')) {
                        $controller->destroy($data['id']);
                    } else {
                        \App\Core\ApiResponseHelper::error('Endpoint not found', 404, 'ENDPOINT_NOT_FOUND');
                    }
                    break;
                default:
                    \App\Core\ApiResponseHelper::error('Method not allowed', 405, 'METHOD_NOT_ALLOWED');
            }
        } else {
            \App\Core\ApiResponseHelper::error('Resource not found', 404, 'RESOURCE_NOT_FOUND');
        }
    }

    /**
     * MIME tipini belirle
     */
    private static function getMimeType(string $filename): string {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        $mimeTypes = [
            'css' => 'text/css',
            'js' => 'application/javascript',
            'json' => 'application/json',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'ico' => 'image/x-icon',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf',
            'eot' => 'application/vnd.ms-fontobject',
            'pdf' => 'application/pdf',
            'txt' => 'text/plain',
            'xml' => 'application/xml'
        ];
        
        return $mimeTypes[$extension] ?? 'application/octet-stream';
    }

    /**
     * Route eşleşmesi bul
     */
    public static function matchRoute(string $method, string $uri) {
        // Önce statik route'ları kontrol et
        $methodUri = $method . ':' . $uri;
        if (isset(self::$compiledRoutes[$methodUri])) {
            return self::$compiledRoutes[$methodUri];
        }
        
        // Doğrudan URI eşleşmesi (GET varsayılan)
        if (isset(self::$compiledRoutes[$uri])) {
            return self::$compiledRoutes[$uri];
        }
        
        // Desen eşleşmesi
        foreach (self::$compiledRoutes as $pattern => $handler) {
            $patternMethod = 'GET';
            $patternUri = $pattern;
            
            if (strpos($pattern, ':') !== false) {
                list($patternMethod, $patternUri) = explode(':', $pattern, 2);
            }
            
            if ($patternMethod === $method) {
                // Desen eşleştirme
                $regexPattern = preg_replace('/\{([^}]+)\}/', '([^/]+)', $patternUri);
                $regexPattern = '#^' . $regexPattern . '$#';
                
                if (preg_match($regexPattern, $uri, $matches)) {
                    array_shift($matches); // İlk eşleşmeyi kaldır
                    return ['handler' => $handler, 'params' => $matches];
                }
            }
        }
        
        return null;
    }

    /**
     * Tüm route'ları getir
     */
    public static function getRoutes(): array {
        return self::$compiledRoutes;
    }

    /**
     * Yeni route ekle
     */
    public static function addRoute(string $method, string $path, $handler): void {
        $routeKey = $method . ':' . $path;
        self::$routes[$routeKey] = $handler;
        self::$compiledRoutes[$routeKey] = $handler;
    }

    /**
     * Base path ayarla
     */
    public static function setBasePath(string $basePath): void {
        self::$basePath = rtrim($basePath, '/');
    }

    /**
     * Asset path ayarla
     */
    public static function setAssetPath(string $assetPath): void {
        self::$assetPath = rtrim($assetPath, '/');
    }

    /**
     * Image path ayarla
     */
    public static function setImagePath(string $imagePath): void {
        self::$imagePath = rtrim($imagePath, '/');
    }

    /**
     * JS path ayarla
     */
    public static function setJsPath(string $jsPath): void {
        self::$jsPath = rtrim($jsPath, '/');
    }

    /**
     * CSS path ayarla
     */
    public static function setCssPath(string $cssPath): void {
        self::$cssPath = rtrim($cssPath, '/');
    }

    /**
     * Base path getir
     */
    public static function getBasePath(): string {
        return self::$basePath;
    }

    /**
     * Asset path getir
     */
    public static function getAssetPath(): string {
        return self::$assetPath;
    }

    /**
     * Image path getir
     */
    public static function getImagePath(): string {
        return self::$imagePath;
    }

    /**
     * JS path getir
     */
    public static function getJsPath(): string {
        return self::$jsPath;
    }

    /**
     * CSS path getir
     */
    public static function getCssPath(): string {
        return self::$cssPath;
    }

    /**
     * Dinamik asset URL'si oluştur
     */
    public static function asset(string $path): string {
        return self::$basePath . self::$assetPath . '/' . ltrim($path, '/');
    }

    /**
     * Dinamik image URL'si oluştur
     */
    public static function image(string $path): string {
        return self::$basePath . self::$imagePath . '/' . ltrim($path, '/');
    }

    /**
     * Dinamik JS URL'si oluştur
     */
    public static function js(string $path): string {
        return self::$basePath . self::$jsPath . '/' . ltrim($path, '/');
    }

    /**
     * Dinamik CSS URL'si oluştur
     */
    public static function css(string $path): string {
        return self::$basePath . self::$cssPath . '/' . ltrim($path, '/');
    }

    /**
     * Dinamik route URL'si oluştur
     */
    public static function route(string $name, array $params = []): string {
        // Route ismine göre URL oluştur
        $routes = self::getRoutes();
        
        foreach ($routes as $route => $handler) {
            // Burada route isimlerine göre eşleştirme yapılacak
            // Basit bir implementasyon - gerçek sistemde daha gelişmiş olmalı
            if (is_string($handler) && strpos($handler, $name) !== false) {
                $url = str_replace(['GET:', 'POST:', 'PUT:', 'DELETE:'], '', $route);
                
                // Parametreleri yerleştir
                foreach ($params as $key => $value) {
                    $url = str_replace('{' . $key . '}', $value, $url);
                }
                
                return self::$basePath . $url;
            }
        }
        
        return '#'; // Bulunamazsa placeholder
    }
    
    /**
     * Convert plural resource name to singular for controller naming
     * @param string $resource Resource name (e.g., "categories", "users")
     * @return string Singular form (e.g., "category", "user")
     */
    private static function singularize(string $resource): string {
        // Common plural to singular conversions
        $irregular = [
            'categories' => 'category',
            'menus' => 'menu',
            'users' => 'user',
            'tables' => 'table',
            'orders' => 'order',
            'reservations' => 'reservation',
            'zones' => 'zone',
            'items' => 'item',
            'products' => 'product'
        ];
        
        // Check irregular forms first
        if (isset($irregular[strtolower($resource)])) {
            return $irregular[strtolower($resource)];
        }
        
        // Simple plural removal rules
        if (substr($resource, -3) === 'ies') {
            // categories -> category
            return substr($resource, 0, -3) . 'y';
        }
        
        if (substr($resource, -1) === 's' && strlen($resource) > 1) {
            // users -> user, tables -> table
            return substr($resource, 0, -1);
        }
        
        // Return as-is if no plural form detected
        return $resource;
    }
}