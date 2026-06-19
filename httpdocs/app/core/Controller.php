<?php
namespace App\Core;

require_once __DIR__ . '/Middleware.php';
require_once __DIR__ . '/Authorization.php';
require_once __DIR__ . '/SessionManager.php';
require_once __DIR__ . '/HelperLoader.php';
require_once __DIR__ . '/SecurityFirewall.php';
require_once __DIR__ . '/../middleware/SecurityMiddleware.php';
require_once __DIR__ . '/Traits/HasPermissions.php';
require_once __DIR__ . '/Traits/HandlesAPIResponse.php';
require_once __DIR__ . '/Traits/HandlesValidation.php';
require_once __DIR__ . '/Traits/HandlesTranslation.php';

use App\Core\Traits\HandlesAPIResponse;
use App\Core\Traits\HasPermissions;
use App\Core\Traits\HandlesValidation;
use App\Core\Traits\HandlesTranslation;

class Controller {
    // Use method aliases to resolve trait collisions
    use HandlesAPIResponse, HasPermissions, HandlesValidation, HandlesTranslation {
        HandlesAPIResponse::isApiRequest insteadof HasPermissions;
        HandlesAPIResponse::apiResponse insteadof HasPermissions;
    }
    protected $auth;
    protected $firewall;
    protected $translationService;
    protected $notificationService;
    protected $toastNotificationService;
    protected $seoService;
    protected $filterService;
    protected $searchService;

    public function __construct() {
        // Skip validation on login/auth pages to prevent redirect loops
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        $isLoginPage = strpos($requestUri, '/login') !== false || strpos($requestUri, '/auth/') !== false;
        SessionManager::ensureSession($isLoginPage);
        HelperLoader::ensureLoaded(); // Ensure all helpers are loaded before using helper functions

        try {
            $this->auth = Authorization::getInstance();
            if ($this->auth === null) {
                throw new \Exception('Authorization instance is null');
            }
        } catch (\Exception $e) {
            error_log('Failed to initialize Authorization: ' . $e->getMessage());
            require_once __DIR__ . '/Authorization.php';
            $this->auth = Authorization::getInstance();
        }

        $this->firewall = \App\Middleware\SecurityMiddleware::getFirewall();

        // Initialize centralized services - ensure functions are available
        try {
            $this->translationService = getTranslationService();
        } catch (\Error $e) {
            // Fallback: load helpers again if function not found
            require_once __DIR__ . '/../helpers/functions.php';
            $this->translationService = getTranslationService();
        }
        
        try {
            $this->notificationService = getNotificationService();
        } catch (\Error $e) {
            // Fallback: load helpers again if function not found
            require_once __DIR__ . '/../helpers/functions.php';
            $this->notificationService = getNotificationService();
        }
        
        try {
            $this->toastNotificationService = getToastNotificationService();
        } catch (\Error $e) {
            // Fallback: load helpers again if function not found
            require_once __DIR__ . '/../helpers/functions.php';
            $this->toastNotificationService = getToastNotificationService();
        }
        
        try {
            $this->seoService = getSEOService();
        } catch (\Error $e) {
            require_once __DIR__ . '/../helpers/functions.php';
            $this->seoService = getSEOService();
        }
        
        try {
            $this->filterService = getFilterService();
        } catch (\Error $e) {
            require_once __DIR__ . '/../helpers/functions.php';
            $this->filterService = getFilterService();
        }
        
        try {
            $this->searchService = getSearchService();
        } catch (\Error $e) {
            require_once __DIR__ . '/../helpers/functions.php';
            $this->searchService = getSearchService();
        }
    }

    // Translation method is now provided by HandlesTranslation trait

    /**
     * Send a JSON response and terminate the request.
     *
     * Historical note: several controllers (SuperAdmin\BusinessesController,
     * etc.) call `$this->json(...)` assuming a base helper. That helper
     * didn't exist, every such call blew up with
     *   Call to undefined method ...::json()
     * while the business logic before it had already mutated the DB —
     * leading to "500 returned but the record was actually created/updated"
     * ghosts (e.g. subscription activated even though the UI reported
     * failure). Centralising this helper fixes all those endpoints at once
     * and gives us a single, consistent JSON emission path.
     */
    protected function json($data, int $statusCode = 200): void {
        if (!headers_sent()) {
            http_response_code($statusCode);
            header('Content-Type: application/json; charset=utf-8');
            header('X-Content-Type-Options: nosniff');
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    protected function view(string $view, array $data = []): void {
        SessionManager::ensureSession();
        HelperLoader::ensureLoaded();

        // Prevent page caching to ensure navigation always reloads
        // This ensures navigation menu is always fresh on each page load
        header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');

        // Automatically inject CSRF token into all views
        if (!isset($data['csrf_token'])) {
            require_once __DIR__ . '/Security/CSRFManager.php';
            $data['csrf_token'] = \App\Core\Security\CSRFManager::generateToken();
        }

        $viewPath = __DIR__ . '/../views/' . $view . '.php';
        if (file_exists($viewPath)) {
            try {
                extract($data);

                // Check if this is admin/dashboard or admin/customer_dashboard - use admin_layout for consistency
                if (strpos($view, 'admin/dashboard') === 0 || strpos($view, 'admin/customer_dashboard') === 0) {
                    // Dashboard views use admin layout for sidebar consistency after login
                    $content = $this->renderViewContent($viewPath, $data);
                    $title = $data['title'] ?? 'Müşteri Paneli - Qordy';
                    include __DIR__ . '/../views/layouts/admin_layout.php';
                } elseif (strpos($view, 'receipt/') === 0) {
                    // Receipt: minimal layout (no sidebar) – same-page popup style, works in iframe
                    $content = $this->renderViewContent($viewPath, $data);
                    $title = $data['title'] ?? 'Fiş - Qordy';
                    include __DIR__ . '/../views/layouts/receipt_minimal_layout.php';
                } elseif (strpos($view, 'admin/') === 0 || strpos($view, 'superadmin/') === 0 || strpos($view, 'pos/') === 0 || strpos($view, 'kitchen/') === 0 || strpos($view, 'waiter/') === 0 || strpos($view, 'cashier/') === 0 || strpos($view, 'preparation-screen/') === 0) {
                    $content = $this->renderViewContent($viewPath, $data);
                    $title = $data['title'] ?? (strpos($view, 'receipt/') === 0 ? 'Fiş - Qordy' : 'Admin Panel - Qordy');
                    // Pass view path to layout so it can conditionally load scripts
                    $data['view'] = $view;
                    extract($data);
                    include __DIR__ . '/../views/layouts/admin_layout.php';
                } elseif (strpos($view, 'customer/') === 0) {
                    // Customer panel pages (packages, payment, subscription, etc.) use admin layout when opened from sidebar
                    $customerPanelViews = [
                        'customer/package_list', 'customer/payment', 'customer/payment_history', 'customer/saved_cards',
                        'customer/subscription_detail', 'customer/bank_transfer', 'customer/account', 'customer/billing'
                    ];
                    if (in_array($view, $customerPanelViews, true)) {
                        $content = $this->renderViewContent($viewPath, $data);
                        $title = $data['title'] ?? 'Paketler - Qordy';
                        $data['view'] = $view;
                        extract($data);
                        include __DIR__ . '/../views/layouts/admin_layout.php';
                    } else {
                        require $viewPath;
                    }
                } else {
                    require $viewPath;
                }
            } catch (\Throwable $e) {
                \App\Core\ErrorHandler::handleException($e);
                exit; // ErrorHandler zaten exit yapıyor ama güvenlik için
            }
        } else {
            echo "View not found: " . $view;
        }
    }

    /**
     * Alias for view() method
     * @param string $view View name (relative to app/views/)
     * @param array $data Data to pass to view
     */
    protected function render(string $view, array $data = []): void {
        $this->view($view, $data);
    }

    protected function renderViewContent(string $viewPath, array $data = []): string {
        SessionManager::ensureSession();
        HelperLoader::ensureLoaded();

        // Ensure CSRF token is available in view data
        if (!isset($data['csrf_token'])) {
            require_once __DIR__ . '/Security/CSRFManager.php';
            $data['csrf_token'] = \App\Core\Security\CSRFManager::generateToken();
        }

        ob_start();
        try {
            extract($data);
            require $viewPath;
        } catch (\Throwable $e) {
            ob_end_clean();
            \App\Core\ErrorHandler::handleException($e);
            exit; // ErrorHandler zaten exit yapıyor ama güvenlik için
        }
        return ob_get_clean() ?: '';
    }

    // API response methods are now provided by HandlesAPIResponse trait

    protected function model(string $model) {
        $modelPath = __DIR__ . '/../models/' . $model . '.php';
        if (file_exists($modelPath)) {
            require_once $modelPath;
            $modelClass = "App\\Models\\{$model}";
            return new $modelClass();
        }
        return null;
    }

    protected function isLoggedIn(): bool {
        return $this->auth !== null && $this->auth->isLoggedIn();
    }

    protected function requireLogin(bool $redirect = true): bool {
        if ($this->auth === null) {
            if ($redirect) {
                $currentHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $loginUrl = $protocol . '://' . $currentHost . '/login';
                header('Location: ' . $loginUrl);
                exit;
            }
            return false;
        }
        $result = $this->auth->requireLogin($redirect);
        
        if ($result) {
            $this->checkBusinessActiveOrLogout();
        }
        
        return $result;
    }
    
    protected function checkBusinessActiveOrLogout(): void {
        \App\Core\SessionManager::ensureSession();
        
        if (\App\Core\SessionManager::get('is_super_admin') === true) {
            return;
        }
        $role = strtoupper(trim(\App\Core\SessionManager::get('role') ?? ''));
        if (in_array($role, ['SUPER_ADMIN', 'ROLE_SUPER_ADMIN', 'QODMIN', 'ROLE_QODMIN'], true)) {
            return;
        }
        
        $businessId = \App\Core\SessionManager::get('business_id') 
                    ?? \App\Core\SessionManager::get('customer_id') 
                    ?? null;
        
        if (!$businessId) {
            return;
        }
        
        try {
            $db = \App\Core\DependencyFactory::getDatabase();
            $stmt = $db->prepare("SELECT is_active FROM customers WHERE customer_id = :id LIMIT 1");
            $stmt->execute(['id' => $businessId]);
            $biz = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($biz && isset($biz['is_active']) && (int)$biz['is_active'] === 0) {
                $_SESSION = [];
                if (session_status() === PHP_SESSION_ACTIVE) {
                    session_regenerate_id(true);
                }
                
                \App\Core\SessionManager::ensureSession(true);
                \App\Core\SessionManager::resetInitialized();
                
                $toastService = null;
                try {
                    require_once __DIR__ . '/../helpers/functions.php';
                    $toastService = getToastNotificationService();
                } catch (\Exception $e) {}
                
                if ($toastService) {
                    $toastService->setFlash('error', 'İşletmeniz pasife alınmıştır. Lütfen yöneticinizle iletişime geçin.');
                }
                session_write_close();
                
                $currentHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                header('Location: ' . $protocol . '://' . $currentHost . '/login');
                exit;
            }
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::warning("Controller::checkBusinessActiveOrLogout - Failed", [
                    'business_id' => $businessId,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    // Permission methods are now provided by HasPermissions trait

    // Permission methods are now provided by HasPermissions trait
    // API response methods are now provided by HandlesAPIResponse trait

    protected function canAccess(string $route, string $method = 'GET'): bool {
        return $this->auth !== null && $this->auth->canAccess($route, $method);
    }

    protected function getCurrentRole(): ?string {
        return $this->auth !== null ? $this->auth->getCurrentRole() : null;
    }

    protected function getCurrentUserId(): ?string {
        return $this->auth !== null ? $this->auth->getCurrentUserId() : null;
    }

    protected function checkAuth(array $options = []): bool {
        return \App\Core\Middleware::check($options);
    }

    protected function generateCSRFToken(): string {
        return generateCSRFToken();
    }

    protected function validateCSRFToken(string $token): bool {
        return validateCSRFToken($token);
    }

    protected function getFirewall(): \App\Core\SecurityFirewall {
        return $this->firewall;
    }

    /**
     * Check if current user is SUPER_ADMIN
     */
    protected function isSuperAdmin(): bool {
        \App\Core\SessionManager::ensureSession();
        $sessionRole = \App\Core\SessionManager::get('role');
        $isSuperAdminSession = \App\Core\SessionManager::get('is_super_admin') === true;
        $isSuperAdmin = false;

        // Check super admin by session flag first
        if ($isSuperAdminSession) {
            $isSuperAdmin = true;
        } elseif ($sessionRole) {
            $normalizedRole = strtoupper(trim($sessionRole));
            $isSuperAdmin = ($normalizedRole === 'SUPER_ADMIN' || $normalizedRole === 'ROLE_SUPER_ADMIN' ||
                           $normalizedRole === 'QODMIN' || $normalizedRole === 'ROLE_QODMIN');
        }

        // Try Authorization method as fallback
        if (!$isSuperAdmin && isset($this->auth) && $this->auth !== null) {
            try {
                $isSuperAdmin = $this->auth->isSuperAdmin();
            } catch (\Exception $e) {
                // Ignore exception, use session check result
            }
        }

        return $isSuperAdmin;
    }

    // Validation methods are now provided by HandlesValidation trait

    /**
     * Ensure tenant context is set from session if not already set
     * This is needed when accessing pages from main domain (qordy.com) instead of subdomain
     * 
     * CRITICAL FIX: Superadmin için de tenant context set ediliyor (işletme seçildiğinde)
     */
    /**
     * Ensure tenant context is set
     * If accessing from main domain (qordy.com) without subdomain, redirect to user's subdomain
     * @throws \Exception if tenant context cannot be set
     */
    protected function ensureTenantContext(): void {
        // CRITICAL NEW LOGIC:
        // - Ana domain (qordy.com): İşletme yönetimi buradadır (/business/*, /qodmin/*)
        // - Subdomain (caddecafe.qordy.com): SADECE personel PIN girişi (/login)
        // - Subdomain'den /business/* veya başka route'lara erişim OLMAMALI!
        
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $subdomain = \App\Core\TenantContext::getSubdomainFromHost($host);
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        
        // CRITICAL: Subdomain'de SADECE /login ve API endpoint'lerine izin ver
        // Başka her şey ana domain'e redirect edilmeli
        if (!empty($subdomain)) {
            $isStaffLogin = (strpos($requestUri, '/login') !== false || $requestUri === '/');
            $isAPIEndpoint = (strpos($requestUri, '/api/') !== false);
            $isPublicAsset = (
                strpos($requestUri, '/assets/') !== false ||
                strpos($requestUri, '/public/') !== false ||
                strpos($requestUri, '/uploads/') !== false
            );
            
            // Subdomain'de /business/*, /qodmin/*, /customer/*, /waiter/*, /pos/*, /kitchen/* gibi
            // route'lara erişim için GİRİŞ YAPMAK ZORUNLU
            // GİRİŞ YAPMAMIŞ kullanıcılar ana domain'e redirect edilir
            // GİRİŞ YAPMIŞ personeller subdomain'de kalabilir (kendi dashboard'larına erişebilir)
            $isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
            
            if (!$isStaffLogin && !$isAPIEndpoint && !$isPublicAsset && !$isLoggedIn) {
                $protocol = isHttps() ? 'https://' : 'http://';

                // If the user is hitting a protected subdomain page without a
                // session we annotate the redirect with `?err=session_lost`
                // so the login view can tell them WHY they bounced back. We
                // skip the annotation when they're already on /login to
                // avoid noise on pure first-visit loads, and when the URL
                // already carries an error code.
                $hasCookie = !empty($_COOKIE[session_name()] ?? ($_COOKIE['PHPSESSID'] ?? ''));
 $hasExistingErr = isset($_GET['err']) || isset($_GET['reason']);
 $reachingLoginUi = rtrim(parse_url($requestUri, PHP_URL_PATH) ?? '/', '/') === '/login';

 // BUGFIX: Previously we annotated every redirect with err=session_expired
 // whenever a cookie was present. But the browser sends the cookie on EVERY
 // request (including the very first visit to a subdomain), and PHP
 // regenerates the session ID — so the server-side session may be missing
 // even on a brand-new visit. This caused 'Oturum süreniz doldu' to appear
 // for users who had never logged in.
 // Fix: only show session_expired when triggered by explicit logout.
 // Otherwise redirect silently.
 $isLogoutFlow = strpos($requestUri, 'logout') !== false;
 $loginUrl = $protocol . $host . '/login';
 if (!$reachingLoginUi && !$hasExistingErr) {
  if ($isLogoutFlow) {
  $loginUrl .= '?reason=logout';
  }
  // Otherwise: silent redirect
 }

                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::info('Subdomain: Redirecting to login (user not logged in)', [
                        'subdomain' => $subdomain,
                        'from' => $host . $requestUri,
                        'to' => $loginUrl,
                        'is_logged_in' => $isLoggedIn,
                        'had_cookie' => $hasCookie,
                    ]);
                }

                header('Location: ' . $loginUrl);
                exit;
            }
        }
        
        if (\App\Core\TenantContext::isSet()) {
            return;
        }

        // Superadmin: explicit business selection (query string or session)
        if ($this->isSuperAdmin()) {
            $businessIdFromQuery = $_GET['business_id'] ?? $_REQUEST['business_id'] ?? null;
            if ($businessIdFromQuery) {
                \App\Core\TenantContext::setId($businessIdFromQuery);
                $_SESSION['selected_business_id'] = $businessIdFromQuery;
                return;
            }
            $businessIdFromSession = $_SESSION['selected_business_id'] ?? null;
            if ($businessIdFromSession) {
                \App\Core\TenantContext::setId($businessIdFromSession);
                return;
            }
            return;
        }

        // Normal users: TenantResolver is the single source of truth
        $tenantId = \App\Core\TenantResolver::resolve();
        if (!$tenantId) {
            return;
        }
        try {
            $customerService = \App\Core\DependencyFactory::getCustomerService();
            $customer = $customerService->getById($tenantId);
            if ($customer) {
                \App\Core\TenantContext::set($customer);
            }
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('Failed to set tenant context', [
                    'tenant_id' => $tenantId,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }
    
    /**
     * Check if user owns the resource (for ownership-based access control)
     * Superadmin can access everything, Business owners can access their own resources
     * 
     * @param string|null $resourceBusinessId The business_id of the resource
     * @return bool True if user owns the resource or is superadmin
     */
    protected function userOwnsResource($resourceBusinessId): bool {
        if ($this->isSuperAdmin()) {
            return true;
        }
        if (!$resourceBusinessId) {
            return false;
        }
        $tenantId = \App\Core\TenantResolver::resolve();
        return $tenantId && $resourceBusinessId === $tenantId;
    }
}