<?php
namespace App\Core;

require_once __DIR__ . '/Router.php';
require_once __DIR__ . '/Logger.php';
require_once __DIR__ . '/ErrorHandler.php';
require_once __DIR__ . '/Container.php';
require_once __DIR__ . '/ServiceProvider.php';

class App {
    private $router;
    private $container;
    private $serviceProvider;

    public function __construct() {
        // CRITICAL: Set no-cache headers for all dynamic pages
        // This prevents browsers from caching PHP-generated content
        if (!headers_sent()) {
            header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            header('Expires: 0');
        }

        // Initialize error handling and logging
        ErrorHandler::init();
        Logger::init();

        Logger::info('Application started', [
            'uri' => $_SERVER['REQUEST_URI'] ?? '',
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET'
        ]);

        // Initialize dependency injection container
        $this->container = new Container();

        // Initialize service provider
        $this->serviceProvider = new ServiceProvider($this->container);

        // PERFORMANCE: Auto-migration disabled for maximum performance
        // Use 'php migrate.php' for manual migrations

        $this->router = new Router();
        $this->loadRoutes();
        $this->handleRequest();
    }
    
    /**
     * Check if migrations should run
     * Can be disabled in production with AUTO_MIGRATE=false
     * @return bool
     */
    private function shouldRunMigrations(): bool {
        // Check if auto-migration is disabled via environment variable
        if (isset($_ENV['AUTO_MIGRATE']) && $_ENV['AUTO_MIGRATE'] === 'false') {
            return false;
        }
        
        // Check if in production environment
        if (isset($_ENV['APP_ENV']) && $_ENV['APP_ENV'] === 'production') {
            // In production, only run if explicitly enabled
            return isset($_ENV['AUTO_MIGRATE']) && $_ENV['AUTO_MIGRATE'] === 'true';
        }
        
        // In development, run by default
        return true;
    }
    
    /**
     * Run pending migrations automatically
     * Uses cache to avoid running on every request
     * PERFORMANCE: This method should be disabled in production
     */
    private function runPendingMigrations() {
        try {
            // Skip migrations for static assets and API endpoints to improve performance
            $uri = $_SERVER['REQUEST_URI'] ?? '/';
            if ($this->isStaticAsset($uri) || strpos($uri, '/api/') === 0) {
                return;
            }
            
            // Check cache to see if migrations were recently checked
            $cacheService = \App\Core\DependencyFactory::getCacheService();
            $cacheKey = 'migrations_last_check';
            $lastCheck = $cacheService->get($cacheKey);
            
            // Only check migrations every 5 minutes to avoid performance impact
            if ($lastCheck && (time() - $lastCheck) < 300) {
                return;
            }
            
            // Update cache with current time
            $cacheService->set($cacheKey, time(), 600); // Cache for 10 minutes
            
            // Check if there are pending migrations
            require_once __DIR__ . '/../database/Migrator.php';
            $migrator = new \App\Database\Migrator();
            
            // Check if there are pending migrations
            $migrationFiles = $migrator->getMigrationFiles();
            $executedMigrations = $migrator->getExecutedMigrations();
            
            // Check if there are any pending migrations
            $hasPendingMigrations = false;
            foreach ($migrationFiles as $file) {
                $migrationName = basename($file, '.php');
                if (!in_array($migrationName, $executedMigrations)) {
                    $hasPendingMigrations = true;
                    break;
                }
            }
            
            // Run migrations if there are pending ones
            if ($hasPendingMigrations) {
                // Start output buffering to prevent any output from migrations
                // Use multiple levels to ensure all output is captured
                $obLevel = ob_get_level();
                while (ob_get_level() > 0) {
                    ob_end_clean();
                }
                ob_start();
                
                try {
                    $results = $migrator->up();
                } finally {
                    // Ensure all output is discarded
                    while (ob_get_level() > $obLevel) {
                        ob_end_clean();
                    }
                    if (ob_get_level() > 0) {
                        ob_end_clean();
                    }
                }
                
                // Sync dynamic permissions after migrations
                if (count($results['success']) > 0) {
                    try {
                        $dynamicPermissionService = \App\Core\DependencyFactory::getDynamicPermissionService();
                        $dynamicPermissionService->discoverAllDynamicPermissions();
                    } catch (\Exception $e) {
                        // Log but don't fail
                        Logger::error('Failed to sync dynamic permissions after auto-migration', [
                            'error' => $e->getMessage()
                        ]);
                    }
                }
                
                // Log migration results
                if (count($results['success']) > 0 || count($results['failed']) > 0) {
                    Logger::info('Auto-migrations executed', [
                        'executed' => count($results['success']),
                        'failed' => count($results['failed']),
                        'success_list' => $results['success'],
                        'failed_list' => $results['failed']
                    ]);
                }
            }
        } catch (\Exception $e) {
            // Log error but don't break the application
            Logger::error('Auto-migration check failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
    

    private function loadRoutes() {
        // Load routes from routes configuration
        $routes = include __DIR__ . '/../config/routes.php';

        foreach ($routes as $path => $handler) {
            // Determine method based on path or default to GET
            $method = 'GET';
            if (strpos($path, 'GET:') === 0) {
                $method = 'GET';
                $path = substr($path, 4);
            } elseif (strpos($path, 'POST:') === 0) {
                $method = 'POST';
                $path = substr($path, 5);
            } elseif (strpos($path, 'PUT:') === 0) {
                $method = 'PUT';
                $path = substr($path, 4);
            } elseif (strpos($path, 'DELETE:') === 0) {
                $method = 'DELETE';
                $path = substr($path, 7);
            }

            // Handle empty path (root route)
            $routePath = ($path === '') ? '/' : '/' . $path;
            
            $this->router->addRoute($method, $routePath, $handler);
        }
    }

    private function handleRequest() {
        require_once __DIR__ . '/FlutterWebStatic.php';
        // Flutter web: en erken aşamada yakala (REQUEST_URI / mobile-app)
        FlutterWebStatic::attemptServe();

        // Initialize tenant context first (multi-tenant support)
        $this->initializeTenantContext();
        
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        // Check if we have a url parameter (from .htaccess redirect)
        // Boş url= değerinde '/', mobil-app yolunu kaybetme — REQUEST_URI dalına düş
        if (isset($_GET['url']) && $_GET['url'] !== '' && $_GET['url'] !== null) {
            $urlParam = $_GET['url'];
            $uri = '/' . ltrim($urlParam, '/');
            
            // CRITICAL: Ensure API routes are properly formatted
            if (strpos($uri, '/api/') === 0) {
                // API routes should start with /api/
                // Debug: Log URL parameter parsing
                \App\Core\Logger::debug("URI from URL param (API): {$uri}", [
                    'url_param' => $urlParam,
                    'request_uri' => $_SERVER['REQUEST_URI'] ?? ''
                ]);
            }
        } else {
            // Check if REQUEST_URI exists before parsing
            $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
            
            // CRITICAL: For API routes, use REQUEST_URI directly if it starts with /api/
            // This handles subdomain API requests correctly
            if (strpos($requestUri, '/api/') === 0) {
                $uri = parse_url($requestUri, PHP_URL_PATH);
                if ($uri === null || $uri === false) {
                    $uri = $requestUri; // Fallback to original if parsing fails
                }
                
                // Debug: Log API route detection
                \App\Core\Logger::debug("API route detected from REQUEST_URI", [
                    'request_uri' => $requestUri,
                    'parsed_uri' => $uri
                ]);
            } else {
                $uri = parse_url($requestUri, PHP_URL_PATH);
            }

            // If parse_url returns null or false, use default
            if ($uri === null || $uri === false) {
                $uri = '/';
            }

            // Remove base path if needed (for when index.php is in a subdirectory)
            $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
            $scriptDir = dirname($scriptName);

            // Remove /public from script directory if present
            if (strpos($scriptDir, '/public') !== false) {
                $scriptDir = str_replace('/public', '', $scriptDir);
            }

            // Only remove base path if it's not root and URI starts with it
            if ($scriptDir !== '/' && $scriptDir !== '.' && $scriptDir !== '' && strpos($uri, $scriptDir) === 0) {
                $uri = substr($uri, strlen($scriptDir));
            }

            // Remove /public/ from URI if present
            if (strpos($uri, '/public/') === 0) {
                $uri = substr($uri, strlen('/public'));
            } elseif (strpos($uri, '/public') === 0) {
                $uri = substr($uri, strlen('/public'));
            }

            // Remove /qordy prefix if present (when accessing from root index.php)
            if (strpos($uri, '/qordy') === 0) {
                $uri = substr($uri, strlen('/qordy'));
            }

            // Ensure URI starts with /
            if ($uri === '' || $uri[0] !== '/') {
                $uri = '/' . $uri;
            }
            
            // Debug: Log URI parsing for API routes
            if (strpos($uri, '/api/') === 0) {
                \App\Core\Logger::debug("URI parsed from REQUEST_URI: {$uri}", [
                    'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
                    'script_name' => $_SERVER['SCRIPT_NAME'] ?? '',
                    'script_dir' => $scriptDir ?? ''
                ]);
            }
        }

        // Normalize empty URI to root
        if ($uri === '' || $uri === '//') {
            $uri = '/';
        }


        // Subdomain root routing: redirect to /login if subdomain exists
        // This must happen BEFORE router dispatch to prevent landing page from loading
        if ($uri === '/') {
            $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
            $subdomain = \App\Core\TenantContext::getSubdomainFromHost($host);
            
            if ($subdomain) {
                // Always redirect subdomain root to login, even if tenant not found yet
                // Tenant will be set when user accesses /login
                // CRITICAL: Use current host (with subdomain) for redirect to preserve subdomain
                $protocol = getProtocol();
                $loginUrl = $protocol . '://' . $host . '/login';
                header('Location: ' . $loginUrl);
                exit;
            }
        }

        // Check if this is a static asset request and serve it directly
        if ($this->isStaticAsset($uri)) {
            $this->serveStaticAsset($uri);
            return;
        }

        // Debug: Log dispatch for API routes
        if (strpos($uri, '/api/') === 0) {
            \App\Core\Logger::info("Dispatching API route", [
                'method' => $method,
                'uri' => $uri,
                'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
                'url_param' => $_GET['url'] ?? 'none'
            ]);
        }
        
        FlutterWebStatic::attemptServe($uri);

        $result = $this->router->dispatch($method, $uri);

        if ($result === false) {
            // 404 is already handled by Router
        }
    }

    /**
     * Check if the URI is a static asset request
     */
    private function isStaticAsset($uri) {
        // Check for common static asset paths
        $staticPaths = ['/assets/', '/public/assets/', '/favicon.ico', '/robots.txt', '/sitemap.xml'];

        foreach ($staticPaths as $path) {
            if (strpos($uri, $path) === 0) {
                return true;
            }
        }

        // Check for file extensions
        $extensions = ['.js', '.css', '.png', '.jpg', '.jpeg', '.gif', '.svg', '.ico', '.woff', '.woff2', '.ttf', '.eot', '.apk'];
        foreach ($extensions as $ext) {
            if (substr($uri, -strlen($ext)) === $ext) {
                return true;
            }
        }

        return false;
    }

    /**
     * Serve static asset files directly
     */
    private function serveStaticAsset($uri) {
        // Remove leading slash
        $filePath = ltrim($uri, '/');

        // Base directory for public assets
        $publicDir = __DIR__ . '/../../public/';

        // Normalize path - remove public/ prefix if present (URI already normalized)
        // Assets are stored in public/assets/, so if URI is /assets/..., file is at public/assets/...
        if (strpos($filePath, 'assets/') === 0) {
            $relativePath = $filePath; // Already in correct format: assets/js/realtime.js
        } elseif (strpos($filePath, 'public/assets/') === 0) {
            $relativePath = str_replace('public/', '', $filePath); // Remove public/ prefix
        } elseif (strpos($filePath, 'public/') === 0) {
            $relativePath = str_replace('public/', '', $filePath);
        } else {
            $relativePath = $filePath;
        }

        // Build paths to try
        $pathsToTry = [
            $publicDir . $relativePath,                    // public/assets/js/realtime.js
        ];

        // If path doesn't start with assets/, try adding it
        if (strpos($relativePath, 'assets/') !== 0) {
            $pathsToTry[] = $publicDir . 'assets/' . $relativePath;
        }

        // Special handling for favicon.ico
        if ($uri === '/favicon.ico' || strpos($uri, 'favicon.ico') !== false) {
            $pathsToTry[] = $publicDir . 'assets/images/favicon.ico';
            $pathsToTry[] = $publicDir . 'assets/images/favicon.png';
            $pathsToTry[] = $publicDir . 'favicon.ico';
        }

        // Find the first existing file
        $fileToServe = null;
        foreach ($pathsToTry as $path) {
            $normalizedPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
            if (file_exists($normalizedPath) && is_file($normalizedPath)) {
                $fileToServe = $normalizedPath;
                break;
            }
        }

        if ($fileToServe) {
            // Determine MIME type
            $mimeType = $this->getMimeType($fileToServe);

            // Set appropriate headers
            header('Content-Type: ' . $mimeType);
            header('Content-Length: ' . filesize($fileToServe));
            $apkExt = strtolower(pathinfo($fileToServe, PATHINFO_EXTENSION));
            if ($apkExt === 'apk') {
                header('Content-Disposition: attachment; filename="QORDY.apk"');
            }
            
            // REDUCED cache time from 1 year to 1 hour for faster updates
            // Add ETag for cache validation
            $etag = md5_file($fileToServe);
            header('ETag: "' . $etag . '"');
            header('Cache-Control: public, max-age=3600, must-revalidate');

            // Output file
            readfile($fileToServe);
            exit;
        } else {
            // File not found, return 404
            http_response_code(404);
            exit;
        }
    }

    /**
     * Get MIME type for a file
     */
    private function getMimeType($filePath) {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        $mimeTypes = [
            'js' => 'application/javascript',
            'css' => 'text/css',
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
            'txt' => 'text/plain',
            'xml' => 'application/xml',
            'json' => 'application/json',
            'wasm' => 'application/wasm',
            'map' => 'application/json',
            'apk' => 'application/vnd.android.package-archive'
        ];

        return $mimeTypes[$extension] ?? 'application/octet-stream';
    }

    /**
     * Get the dependency injection container
     * @return Container
     */
    public function getContainer() {
        return $this->container;
    }

    /**
     * Get the service provider
     * @return ServiceProvider
     */
    public function getServiceProvider() {
        return $this->serviceProvider;
    }
    
    /**
     * Initialize tenant context for multi-tenant support
     * Detects subdomain and sets tenant context
     */
    private function initializeTenantContext() {
        try {
            // Ensure TenantMiddleware class is loaded
            if (!class_exists('\App\Middleware\TenantMiddleware', false)) {
                // Try autoloader first
                $middlewarePath = __DIR__ . '/../middleware/TenantMiddleware.php';
                if (file_exists($middlewarePath)) {
                    require_once $middlewarePath;
                }
            }
            
            // Check if class exists and has handle method
            if (!class_exists('\App\Middleware\TenantMiddleware', false)) {
                Logger::warning('TenantMiddleware class not found, skipping tenant context initialization');
                return;
            }
            
            // Check if handle method exists
            if (!method_exists('\App\Middleware\TenantMiddleware', 'handle')) {
                Logger::warning('TenantMiddleware::handle() method not found, skipping tenant context initialization');
                return;
            }
            
            // Try to instantiate TenantMiddleware
            try {
                $tenantMiddleware = new \App\Middleware\TenantMiddleware();
            } catch (\Exception $e) {
                // If instantiation fails (e.g., due to missing dependencies), log and continue
                Logger::warning('Failed to instantiate TenantMiddleware', [
                    'error' => $e->getMessage()
                ]);
                return;
            }
            
            // Call handle method
            $tenantMiddleware->handle();
        } catch (\Exception $e) {
            // Log error but don't break the application
            Logger::error('Failed to initialize tenant context', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        } catch (\Error $e) {
            // Also catch fatal errors
            Logger::error('Fatal error initializing tenant context', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}