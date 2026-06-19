<?php
namespace App\Core;

require_once __DIR__ . '/SessionManager.php';
require_once __DIR__ . '/../middleware/SecurityHeadersMiddleware.php';
require_once __DIR__ . '/../middleware/SecurityMiddleware.php';
require_once __DIR__ . '/../middleware/CSRFMiddleware.php';

class Router {
    private $routes = [];
    private $middlewares = [];

    public function addRoute($method, $path, $handler, $middlewares = []) {
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'handler' => $handler,
            'middlewares' => $middlewares
        ];
    }

    public function get($path, $handler, $middlewares = []) {
        $this->addRoute('GET', $path, $handler, $middlewares);
    }

    public function post($path, $handler, $middlewares = []) {
        $this->addRoute('POST', $path, $handler, $middlewares);
    }

    public function put($path, $handler, $middlewares = []) {
        $this->addRoute('PUT', $path, $handler, $middlewares);
    }

    public function delete($path, $handler, $middlewares = []) {
        $this->addRoute('DELETE', $path, $handler, $middlewares);
    }

    public function addMiddleware($name, $callback) {
        $this->middlewares[$name] = $callback;
    }

    public function dispatch($method, $uri) {
        require_once __DIR__ . '/FlutterWebStatic.php';
        FlutterWebStatic::attemptServe($uri);

        // Start performance monitoring
        if (class_exists('\App\Services\PerformanceMonitor')) {
            \App\Services\PerformanceMonitor::start();
        }
        
        // Start query profiling
        if (class_exists('\App\Services\QueryProfiler')) {
            \App\Services\QueryProfiler::start();
        }
        
        // Load NO-CACHE middleware FIRST (prevents caching of dynamic content)
        require_once __DIR__ . '/../middleware/NoCacheMiddleware.php';
        \App\Middleware\NoCacheMiddleware::handle();
        
        // Load security headers middleware
        \App\Middleware\SecurityHeadersMiddleware::handle();
        
        // Load CSRF middleware (before security middleware)
        \App\Middleware\CSRFMiddleware::handle();
        
        // Load security middleware
        \App\Middleware\SecurityMiddleware::handle();
        
        // Trial / Grace / Suspend middleware for business routes
        if (strpos($uri, '/business/') === 0 || strpos($uri, '/customer/') === 0) {
            require_once __DIR__ . '/../middleware/TrialMiddleware.php';
            \App\Middleware\TrialMiddleware::handle();

            // Grace period → salt okunur: yazma istekleri 403 döner
            require_once __DIR__ . '/../middleware/ReadonlyMiddleware.php';
            \App\Middleware\ReadonlyMiddleware::handle();
        }

        require_once __DIR__ . '/../middleware/DemoReadOnlyMiddleware.php';
        \App\Middleware\DemoReadOnlyMiddleware::handle($uri);
        
        // Error handling middleware will wrap route execution
        require_once __DIR__ . '/../middleware/ErrorHandlingMiddleware.php';
        $errorHandlingMiddleware = new \App\Middleware\ErrorHandlingMiddleware();
        
        // Detect language prefix but don't remove it yet - routes handle it
        $languageCode = $this->detectLanguageFromUri($uri);
        if ($languageCode) {
            // Set language in session - use SessionManager to ensure session is properly initialized
            // Skip validation on login/auth pages to prevent redirect loops
            $isLoginPage = strpos($uri, '/login') !== false || strpos($uri, '/auth/') !== false;
            SessionManager::ensureSession($isLoginPage);
            $_SESSION['language'] = $languageCode;
        }
        
        // HEAD requests should use GET handlers (RFC 7231)
        $routeMethod = ($method === 'HEAD') ? 'GET' : $method;
        
        foreach ($this->routes as $route) {
            if ($route['method'] === $routeMethod) {
                // Convert route path to regex pattern
                $pattern = preg_replace('/\{([^}]+)\}/', '([^/]+)', $route['path']);
                $pattern = '#^' . $pattern . '$#';

                if (preg_match($pattern, $uri, $matches)) {
                    \App\Core\Logger::info("Route matched: " . $route['method'] . " " . $route['path'], [
                        'uri' => $uri,
                        'route_path' => $route['path'],
                        'pattern' => $pattern
                    ]);
                    array_shift($matches); // Remove full match

                    // Apply middlewares
                    foreach ($route['middlewares'] as $middleware) {
                        // Skip authentication and CSRF for printer bridge routes
                        if (($middleware === 'auth' || $middleware === 'csrf') && strpos($route['path'], '/pb/') === 0) {
                            continue;
                        }
                        if (isset($this->middlewares[$middleware])) {
                            $result = call_user_func($this->middlewares[$middleware]);
                            if ($result === false) {
                                return false;
                            }
                        }
                    }

                    // Execute handler wrapped in error handling middleware
                    return $errorHandlingMiddleware->handle(function() use ($route, $matches) {
                        if (is_callable($route['handler'])) {
                            return call_user_func_array($route['handler'], $matches);
                        } elseif (is_string($route['handler'])) {
                            // Handle controller@method format
                            list($controller, $method) = explode('@', $route['handler']);
                            // Replace forward slashes with backslashes for namespace
                            $controller = str_replace('/', '\\', $controller);
                            $controllerClass = "App\\Controllers\\{$controller}";

                            if (class_exists($controllerClass)) {
                                $controllerInstance = new $controllerClass();

                                if (method_exists($controllerInstance, $method)) {
                                    return call_user_func_array([$controllerInstance, $method], $matches);
                                } else {
                                    \App\Core\Logger::error("Method does not exist: {$controller}@{$method}");
                                    throw new \Exception("Method does not exist: {$controller}@{$method}");
                                }
                            } else {
                                \App\Core\Logger::error("Controller does not exist: {$controllerClass}");
                                throw new \Exception("Controller does not exist: {$controllerClass}");
                            }
                        }
                        return null;
                    });
                }
            }
        }

        // Return 404 if no route matched
        // Filter routes to show only matching method for better debugging
        $matchingMethodRoutes = array_filter($this->routes, function($r) use ($routeMethod) {
            return $r['method'] === $routeMethod;
        });
        
        \App\Core\Logger::warning("Route not found: {$method} {$uri}", [
            'uri' => $uri,
            'method' => $method,
            'route_method' => $routeMethod,
            'total_routes' => count($this->routes),
            'matching_method_routes' => count($matchingMethodRoutes),
            'available_routes_' . $routeMethod => array_map(function($r) { return $r['method'] . ' ' . $r['path']; }, $matchingMethodRoutes)
        ]);
        
        // Check if this is an API request
        $isApiRequest = strpos($uri, '/api/') === 0 || 
                       (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) ||
                       (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false);
        
        if ($isApiRequest) {
            // Return JSON response for API requests
            \App\Core\ApiResponseHelper::error('Route not found', 404, 'ROUTE_NOT_FOUND');
            return false;
        }
        
        // Return HTML 404 page for web requests
        \App\Core\ErrorHandler::handle404("Route not found: {$method} {$uri}");
        return false;
    }
    
    public function getRoutes() {
        return $this->routes;
    }
    
    /**
     * Detect language code from URI
     * @param string $uri Request URI
     * @return string|null Language code or null
     */
    private function detectLanguageFromUri(string $uri): ?string {
        // Check for language prefix like /tr/ or /en/
        if (preg_match('/^\/(tr|en)(\/|$)/', $uri, $matches)) {
            return $matches[1];
        }
        return null;
    }
}