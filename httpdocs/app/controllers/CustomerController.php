<?php
namespace App\Controllers;

// Controller base class, DependencyFactory, and helpers are autoloaded via HelperLoader

class CustomerController extends \App\Core\Controller {
    protected $menuItemService;
    protected $categoryService;
    protected $tableService;
    protected $orderService;
    protected $settingsService;
    
    public function __construct() {
        parent::__construct(); // Initialize auth and other services
        $this->menuItemService = \App\Core\DependencyFactory::getMenuItemService();
        $this->categoryService = \App\Core\DependencyFactory::getCategoryService();
        $this->tableService = \App\Core\DependencyFactory::getTableService();
        $this->orderService = \App\Core\DependencyFactory::getOrderService();
        $this->notificationService = \App\Core\DependencyFactory::getNotificationService();
        $this->settingsService = \App\Core\DependencyFactory::getSystemSettingsService();
    }
    
    public function menu() {
        // Güvenlik: Eğer kullanıcı giriş yapmışsa ve CUSTOMER değilse, erişimi engelle
        if (isLoggedIn() && !hasRole('CUSTOMER')) {
            // Admin kullanıcı müşteri ekranına erişememeli
            \App\Core\HelperLoader::ensureLoaded();
            header('Location: ' . getAdminUrl('dashboard'));
            exit;
        }
        
        // Müşteri ekranı için admin session'ını izole et
        $this->isolateCustomerSession();
        
        // Check if table parameter is provided (for backward compatibility with ?table=)
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $tableId = $queryParams['table'] ?? $queryParams['table_id'] ?? '';
        
        if (!empty($tableId)) {
            // Redirect to table-specific menu with SEO-friendly URL
            $urlService = \App\Core\DependencyFactory::getUrlService();
            $seoUrl = $urlService->generateTableUrl($tableId, true);
            header('Location: ' . $seoUrl);
            exit;
        }
        
        // PERFORMANCE: Get tenant ID for caching
        $tenantId = \App\Core\TenantContext::getId();
        
        // PERFORMANCE: Try to get cached menu data first (5 minute cache)
        $cacheKey = "customer_menu_data_{$tenantId}";
        $cachedData = null;
        
        if (function_exists('apcu_fetch') && apcu_enabled()) {
            $cachedData = apcu_fetch($cacheKey);
        }
        
        if ($cachedData && is_array($cachedData)) {
            // Use cached data
            $categoriesWithProducts = $cachedData['categories_tree'];
            $categoriesFlat = $cachedData['categories'];
            $menuItems = $cachedData['menu_items'];
            $businessLogoPath = $cachedData['business_logo_path'];
            $businessName = $cachedData['business_name'];
            
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::debug('CustomerController::menu - Using cached data', [
                    'tenant_id' => $tenantId
                ]);
            }
        } else {
            // Load from database - OPTIMIZED: Single query for categories with products
            $categoriesWithProducts = $this->categoryService->getCategoriesWithProducts();
            $categoriesFlat = $this->categoryService->flattenCategoryTree($categoriesWithProducts);
            $menuItems = $this->menuItemService->getAvailableMenuItems();
            
            // Get business logo for display
            $businessLogoPath = null;
            $businessName = null;
            try {
                if ($tenantId) {
                    $businessService = \App\Core\DependencyFactory::getBusinessService();
                    $businessInfo = $businessService->getBusinessInfo($tenantId);
                    $businessLogoPath = $businessInfo['logo_path'] ?? $businessInfo['logo_url'] ?? null;
                    $businessName = $businessInfo['company_name'] ?? $businessInfo['business_name'] ?? null;
                }
            } catch (\Exception $e) {
                // Silent fail - use defaults
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::debug('CustomerController::menu - Failed to load business logo', [
                        'error' => $e->getMessage()
                    ]);
                }
            }
            
            // PERFORMANCE: Cache the data for 5 minutes (300 seconds)
            if (function_exists('apcu_store') && apcu_enabled()) {
                $dataToCache = [
                    'categories_tree' => $categoriesWithProducts,
                    'categories' => $categoriesFlat,
                    'menu_items' => $menuItems,
                    'business_logo_path' => $businessLogoPath,
                    'business_name' => $businessName
                ];
                apcu_store($cacheKey, $dataToCache, 300);
                
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::debug('CustomerController::menu - Data cached', [
                        'tenant_id' => $tenantId,
                        'categories_count' => count($categoriesFlat),
                        'menu_items_count' => count($menuItems)
                    ]);
                }
            }
        }
        
        $data = [
            'table' => null,
            'categories' => $categoriesFlat,
            'categories_tree' => $categoriesWithProducts, // Hierarchical structure for better organization
            'menu_items' => $menuItems,
            'settings' => $this->settingsService->getSettings(),
            'business_logo_path' => $businessLogoPath,
            'business_name' => $businessName
        ];
        
        $this->view('customer/menu', $data);
    }
    
    /**
     * Handle SEO-friendly table menu URL: /masa/{zoneSlug}/{tableSlug}
     * @param string $zoneSlug Zone slug
     * @param string $tableSlug Table slug
     */
    public function tableMenuBySlug($zoneSlug = null, $tableSlug = null) {
        // Start output buffering to prevent partial page rendering on error
        ob_start();
        
        try {
            // Get tableId from slugs
            $tableId = $this->getTableIdFromSlugs($zoneSlug, $tableSlug);
            
            if (empty($tableId)) {
                // Clear any output before redirect
                ob_end_clean();
                // If table not found by slug, redirect to general menu
                header('Location: ' . BASE_URL . '/menu?error=table_not_found');
                exit;
            }
            
            // Use existing tableMenu logic directly (no redirect to old format)
            // This keeps the SEO-friendly URL in the browser
            $this->tableMenu($tableId);
            
            // If we reach here, output was successful - flush the buffer
            ob_end_flush();
        } catch (\Throwable $e) {
            // Clear any output that may have been generated before the error
            ob_end_clean();
            
            // Log error and redirect to menu with error message
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('CustomerController::tableMenuBySlug - Error loading table menu', [
                    'zone_slug' => $zoneSlug,
                    'table_slug' => $tableSlug,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
            header('Location: ' . BASE_URL . '/menu?error=load_failed');
            exit;
        }
    }
    
    /**
     * Get table ID from zone and table slugs
     * CRITICAL: Only search tables belonging to the current subdomain's business
     * @param string $zoneSlug Zone slug
     * @param string $tableSlug Table slug
     * @return string|null Table ID or null if not found
     */
    private function getTableIdFromSlugs($zoneSlug, $tableSlug): ?string {
        // Validate input parameters
        if (empty($zoneSlug) || empty($tableSlug)) {
            return null;
        }
        
        require_once __DIR__ . '/../helpers/functions.php';
        
        // Ensure generateSlug function is available
        if (!function_exists('generateSlug')) {
            \App\Core\Logger::error('CustomerController::getTableIdFromSlugs - generateSlug function not found');
            return null;
        }
        
        // CRITICAL: Get business from subdomain for tenant isolation
        $subdomain = \App\Core\TenantContext::getSubdomainFromHost($_SERVER['HTTP_HOST'] ?? '');
        $tenantId = null;
        
        if ($subdomain) {
            try {
                $customerService = \App\Core\DependencyFactory::getCustomerService();
                $customer = $customerService->getBySubdomain($subdomain);
                if ($customer) {
                    $tenantId = $customer['customer_id'] ?? $customer['id'] ?? null;
                }
            } catch (\Exception $e) {
                \App\Core\Logger::error('CustomerController::getTableIdFromSlugs - Error getting customer by subdomain', [
                    'subdomain' => $subdomain,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        // If no tenant found from subdomain, try TenantContext
        if (!$tenantId && class_exists('\App\Core\TenantContext')) {
            try {
                $tenantId = \App\Core\TenantContext::getId();
            } catch (\Exception $e) {
                // TenantContext not set
            }
        }
        
        // Get all tables - repository will automatically filter by tenant if tenantId is set
        // But we need to ensure tenant context is set for repository filtering
        if ($tenantId) {
            try {
                $tenant = \App\Core\TenantContext::get();
                if (!$tenant) {
                    // Set tenant context if not already set
                    $customerService = \App\Core\DependencyFactory::getCustomerService();
                    $customer = $customerService->getById($tenantId);
                    if ($customer) {
                        \App\Core\TenantContext::set($customer);
                    }
                }
            } catch (\Exception $e) {
                // Ignore
            }
        }
        
        // First try to find table by unique_slug (new secure method)
        try {
            $tableRepository = \App\Core\DependencyFactory::getTableRepository();
            $table = $tableRepository->getByUniqueSlug($tableSlug);
            
            if ($table) {
                // Verify zone slug matches (security check)
                $zoneService = \App\Core\DependencyFactory::getZoneService();
                $zoneName = '';
                
                if (!empty($table['zone_id'])) {
                    try {
                        $zone = $zoneService->getZoneById($table['zone_id']);
                        if ($zone && !empty($zone['name'])) {
                            $zoneName = $zone['name'];
                        }
                    } catch (\Exception $e) {
                        // Ignore errors
                    }
                }
                
                // Fallback to old zone field
                if (empty($zoneName) && !empty($table['zone'])) {
                    $zoneName = $table['zone'];
                }
                
                $tableZoneSlug = !empty($zoneName) ? generateSlug($zoneName) : 'masa';
                
                // Verify zone slug matches
                if ($tableZoneSlug === $zoneSlug) {
                    return $table['table_id'];
                }
            }
        } catch (\Exception $e) {
            // If unique_slug lookup fails, fallback to old method
            \App\Core\Logger::debug('CustomerController::getTableIdFromSlugs - unique_slug lookup failed, using fallback', [
                'error' => $e->getMessage()
            ]);
        }
        
        // Fallback: Old method using table name slug (backward compatibility)
        try {
            $tables = $this->tableService->getAllTables();
            $zoneService = \App\Core\DependencyFactory::getZoneService();
            
            if (!is_array($tables)) {
                return null;
            }
            
            foreach ($tables as $table) {
                // CRITICAL: Filter by tenant_id to ensure table belongs to current business
                if ($tenantId) {
                    $tableTenantId = $table['tenant_id'] ?? null;
                    if ($tableTenantId !== $tenantId) {
                        // Skip tables from other businesses
                        continue;
                    }
                }
                
                // Get zone information
                $zoneName = '';
                if (!empty($table['zone_id'])) {
                    try {
                        $zone = $zoneService->getZoneById($table['zone_id']);
                        if ($zone && !empty($zone['name'])) {
                            $zoneName = $zone['name'];
                        }
                    } catch (\Exception $e) {
                        // Ignore errors
                    }
                }
                
                // Fallback to old zone field
                if (empty($zoneName) && !empty($table['zone'])) {
                    $zoneName = $table['zone'];
                }
                
                // Generate slugs for comparison
                $tableZoneSlug = !empty($zoneName) ? generateSlug($zoneName) : 'masa';
                $tableNameSlug = !empty($table['name']) ? generateSlug($table['name']) : generateSlug($table['table_id']);
                
                // Check if slugs match (old method for backward compatibility)
                if ($tableZoneSlug === $zoneSlug && $tableNameSlug === $tableSlug) {
                    return $table['table_id'];
                }
            }
        } catch (\Exception $e) {
            // Log error but don't throw - return null instead
            \App\Core\Logger::error('CustomerController::getTableIdFromSlugs - Error in fallback method', [
                'zone_slug' => $zoneSlug,
                'table_slug' => $tableSlug,
                'error' => $e->getMessage()
            ]);
        }
        
        return null;
    }
    
    public function tableMenu($tableId = null) {
        // CRITICAL: Customer menu must be accessible without authentication
        // QR menu should work for anyone with the link
        // No authentication or role check needed for customer menu
        
        // CRITICAL: Set tenant context from subdomain for customer menu access
        $subdomain = \App\Core\TenantContext::getSubdomainFromHost($_SERVER['HTTP_HOST'] ?? '');
        if ($subdomain) {
            try {
                $customerService = \App\Core\DependencyFactory::getCustomerService();
                $customer = $customerService->getBySubdomain($subdomain);
                if ($customer) {
                    \App\Core\TenantContext::set($customer);
                } else {
                    // Subdomain not found - redirect to error
                    header('Location: ' . BASE_URL . '/menu?error=invalid_subdomain');
                    exit;
                }
            } catch (\Exception $e) {
                \App\Core\Logger::error('CustomerController::tableMenu - Error setting tenant from subdomain', [
                    'subdomain' => $subdomain,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        // Get tableId from route parameter or GET parameter (for backward compatibility)
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $tableId = $tableId ?? $queryParams['table_id'] ?? '';
        
        if (empty($tableId)) {
            // If no table ID is provided, redirect to general menu
            header('Location: ' . BASE_URL . '/menu');
            exit;
        }
        
        // Get table first
        $table = $this->tableService->getTableById($tableId);
        
        if (!$table || !is_array($table)) {
            // Table not found, redirect to general menu with error
            header('Location: ' . BASE_URL . '/menu?error=table_not_found');
            exit;
        }
        
        // CRITICAL: Tenant isolation check - ensure table belongs to current tenant
        $tenantId = \App\Core\TenantContext::getId();
        if ($tenantId) {
            $tableTenantId = $table['tenant_id'] ?? null;
            if (empty($tableTenantId) || $tableTenantId !== $tenantId) {
                // Table doesn't belong to this tenant - deny access
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::warning('Table access denied - tenant mismatch', [
                        'table_id' => $tableId,
                        'table_tenant_id' => $tableTenantId,
                        'tenant_id' => $tenantId,
                        'subdomain' => $subdomain
                    ]);
                }
                header('Location: ' . BASE_URL . '/menu?error=access_denied');
                exit;
            }
        } elseif ($subdomain) {
            // If subdomain exists but tenant not found, deny access
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::warning('Table access denied - subdomain found but tenant not set', [
                    'table_id' => $tableId,
                    'subdomain' => $subdomain
                ]);
            }
            header('Location: ' . BASE_URL . '/menu?error=access_denied');
            exit;
        }
        
        // Redirect to SEO-friendly URL if using old format
        // Check if current URL is old format (/t/tableId)
        $currentPath = $_SERVER['REQUEST_URI'] ?? '';
        if (preg_match('#^/t/[^/]+$#', parse_url($currentPath, PHP_URL_PATH))) {
            // Generate SEO-friendly URL
            $seoUrl = $this->tableService->generateTableUrl($tableId, true);
            // Only redirect if URL is different (avoid infinite redirect)
            if ($seoUrl !== BASE_URL . $currentPath) {
                header('Location: ' . $seoUrl, true, 301); // 301 permanent redirect for SEO
                exit;
            }
        }
        
        // Try QR Code authentication (optional - if fails, still show menu)
        // QRCodeAuthMiddleware is autoloaded
        $qrAuth = \App\Middleware\QRCodeAuthMiddleware::handle($tableId);
        
        // Check if blocked due to working hours
        if ($qrAuth === false && isset($_SESSION['qr_session_error']) && $_SESSION['qr_session_error'] === 'OUTSIDE_WORKING_HOURS') {
            $meta = $_SESSION['qr_session_error_meta'] ?? [];
            $message = $meta['message'] ?? 'Mesai saati disindayiz';
            unset($_SESSION['qr_session_error'], $_SESSION['qr_session_error_meta']);
            
            // Show closed message
            header('Content-Type: text/html; charset=utf-8');
            echo '<!DOCTYPE html><html lang="tr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Kapali</title>
            <style>*{margin:0;padding:0;box-sizing:border-box}body{font-family:system-ui,-apple-system,sans-serif;background:#f8fafc;display:flex;align-items:center;justify-content:center;min-height:100vh;padding:20px}
            .card{background:white;border-radius:24px;padding:40px;text-align:center;max-width:400px;box-shadow:0 4px 24px rgba(0,0,0,0.08)}
            .icon{font-size:64px;margin-bottom:20px}.title{font-size:24px;font-weight:900;color:#1e293b;margin-bottom:12px}
            .msg{font-size:16px;color:#64748b;line-height:1.6}.hours{margin-top:20px;padding:16px;background:#f1f5f9;border-radius:16px;font-weight:700;color:#334155;font-size:18px}</style></head>
            <body><div class="card"><div class="icon">&#x1F319;</div><div class="title">Su An Kapaliyiz</div><div class="msg">' . htmlspecialchars($message) . '</div>
            <div class="hours">' . htmlspecialchars($meta['start_time'] ?? '09:00') . ' - ' . htmlspecialchars($meta['end_time'] ?? '23:00') . '</div></div></body></html>';
            exit;
        }
        
        // Note: We don't require QR auth - if it fails, we still show the menu
        // QR auth is just for additional security features, not blocking access
        
        // Update table status to occupied if it was free
        // This is non-critical - if it fails, we still show the menu
        if (isset($table['status']) && $table['status'] === 'FREE') {
            try {
                $this->tableService->updateTableStatus($tableId, 'CUSTOMER_SEATED');
            } catch (\Throwable $e) {
                // Log but don't fail - table status update is not critical for menu display
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::debug('Failed to update table status (non-critical)', [
                        'table_id' => $tableId,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }
        
        // Check QR menu status for this business
        $qrMenuStatus = 'active';
        try {
            $tenantId = \App\Core\TenantContext::getId();
            if ($tenantId) {
                $db = \App\Core\DependencyFactory::getDatabase();
                $qrStmt = $db->prepare("SELECT qr_menu_status, is_active, company_name FROM customers WHERE customer_id = :id LIMIT 1");
                $qrStmt->execute(['id' => $tenantId]);
                $bizRow = $qrStmt->fetch(\PDO::FETCH_ASSOC);
                if ($bizRow) {
                    $qrMenuStatus = $bizRow['qr_menu_status'] ?? 'active';
                    $bizIsActive = (int)($bizRow['is_active'] ?? 1);
                    if ($bizIsActive === 0 && $qrMenuStatus === 'active') {
                        $qrMenuStatus = 'passive';
                    }
                }

                // Trial expiry check: if business is on a trial that has expired AND
                // grace period (7 days) is also over → fully passive QR menu.
                // If trial is expired but within grace period → menu visible, ordering disabled.
                if ($qrMenuStatus !== 'passive') {
                    try {
                        $trialSub = $db->prepare(
                            "SELECT trial_ends_at, trial_end, current_period_end
                             FROM subscriptions
                             WHERE tenant_id = :bid AND is_trial = 1
                             ORDER BY created_at DESC LIMIT 1"
                        );
                        $trialSub->execute(['bid' => $tenantId]);
                        $trialRow = $trialSub->fetch(\PDO::FETCH_ASSOC);
                        if ($trialRow) {
                            $_trialEndsAt = $trialRow['trial_ends_at']
                                ?? $trialRow['trial_end']
                                ?? $trialRow['current_period_end']
                                ?? null;
                            if ($_trialEndsAt) {
                                $_trialEndTs = strtotime($_trialEndsAt);
                                if ($_trialEndTs < time()) {
                                    $_graceEndTs = $_trialEndTs + (7 * 86400);
                                    if (time() > $_graceEndTs) {
                                        // Fully blocked — passive QR menu
                                        $qrMenuStatus = 'passive';
                                    } else {
                                        // Grace period — menu visible, no ordering
                                        $qrMenuStatus = 'menu_only';
                                    }
                                }
                            }
                        }
                    } catch (\Throwable $_te) { /* graceful */ }
                }
            }
        } catch (\Throwable $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('Error checking QR menu status', ['error' => $e->getMessage()]);
            }
        }
        
        // If QR menu is fully passive, show "out of service" page
        if ($qrMenuStatus === 'passive') {
            $passiveBusinessName = $bizRow['company_name'] ?? 'İşletme';
            header('Content-Type: text/html; charset=utf-8');
            echo '<!DOCTYPE html><html lang="tr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>' . htmlspecialchars($passiveBusinessName) . '</title>
            <style>*{margin:0;padding:0;box-sizing:border-box}body{font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;background:linear-gradient(135deg,#f8fafc 0%,#e2e8f0 100%);display:flex;align-items:center;justify-content:center;min-height:100vh;padding:20px}
            .card{background:white;border-radius:28px;padding:48px 36px;text-align:center;max-width:420px;width:100%;box-shadow:0 8px 32px rgba(0,0,0,0.08)}
            .icon{font-size:72px;margin-bottom:24px;display:block}
            .title{font-size:22px;font-weight:900;color:#1e293b;margin-bottom:8px;line-height:1.3}
            .business-name{font-size:26px;font-weight:900;color:#6366f1;margin-bottom:20px}
            .msg{font-size:15px;color:#64748b;line-height:1.7}
            .divider{width:60px;height:3px;background:linear-gradient(90deg,#6366f1,#a78bfa);border-radius:2px;margin:24px auto}
            </style></head>
            <body><div class="card">
            <span class="icon">🏪</span>
            <div class="business-name">' . htmlspecialchars($passiveBusinessName) . '</div>
            <div class="title">Hoşgeldiniz!</div>
            <div class="divider"></div>
            <div class="msg">QR menümüz geçici olarak servis dışıdır.<br>Anlayışınız için teşekkür ederiz.</div>
            </div></body></html>';
            exit;
        }
        
        // Get feature service to check enabled features
        $featureService = \App\Core\DependencyFactory::getFeatureService();
        
        try {
        $allSettings = $this->settingsService->getSettings();
        $wifiShowToCustomer = isset($allSettings['wifi_show_to_customer']) && ($allSettings['wifi_show_to_customer'] === '1' || $allSettings['wifi_show_to_customer'] === 1 || $allSettings['wifi_show_to_customer'] === true);
        
        // Check for multiple WiFi networks (customer vs staff)
        $wifiNameCustomer = $allSettings['wifi_name'] ?? '';
        $wifiPasswordCustomer = $allSettings['wifi_password'] ?? '';
        $wifiNameStaff = $allSettings['wifi_name_staff'] ?? '';
        $wifiPasswordStaff = $allSettings['wifi_password_staff'] ?? '';
        $hasMultipleWifi = !empty($wifiNameStaff) || !empty($wifiPasswordStaff);
        
        // Get business info from tenant context (merkezi yapı)
        $businessName = getAppConfig()->getAppName();
        $logoUrl = BASE_URL . '/assets/images/logo.png';
        $faviconUrl = BASE_URL . '/assets/images/favicon.png';
        
        try {
            $tenantId = \App\Core\TenantContext::getId();
            if ($tenantId) {
                $customerService = \App\Core\DependencyFactory::getCustomerService();
                $customer = $customerService->getById($tenantId);
                
                if ($customer) {
                    // Business name from customer/tenant
                    $businessName = !empty($customer['company_name']) ? $customer['company_name'] : getAppConfig()->getAppName();
                    
                    // Logo URL from customer - check logo_url first, then logo_path
                    $customerLogoUrl = '';
                    if (!empty($customer['logo_url'])) {
                        $customerLogoUrl = trim($customer['logo_url']);
                    } elseif (!empty($customer['logo_path'])) {
                        $customerLogoUrl = trim($customer['logo_path']);
                    }
                    
                    $finalLogoPath = $customerLogoUrl;
                    
                    // If logo path found, make it absolute
                    if (!empty($finalLogoPath)) {
                        // If it starts with /, it's already relative to web root
                        if (strpos($finalLogoPath, '/') === 0) {
                            $logoUrl = BASE_URL . $finalLogoPath;
                        } 
                        // If it starts with http:// or https://, it's already absolute
                        elseif (preg_match('/^https?:\/\//', $finalLogoPath)) {
                            $logoUrl = $finalLogoPath;
                        }
                        // Otherwise, assume it's relative to BASE_URL
                        else {
                            $logoUrl = BASE_URL . '/' . ltrim($finalLogoPath, '/');
                        }
                    } elseif (!empty($allSettings['logo_url'])) {
                        // PRIORITY 3: Settings logo_url
                        $settingsLogoUrl = trim($allSettings['logo_url']);
                        if (strpos($settingsLogoUrl, '/') === 0) {
                            $logoUrl = BASE_URL . $settingsLogoUrl;
                        } elseif (preg_match('/^https?:\/\//', $settingsLogoUrl)) {
                            $logoUrl = $settingsLogoUrl;
                        } else {
                            $logoUrl = BASE_URL . '/' . ltrim($settingsLogoUrl, '/');
                        }
                    }
                    
                    // Favicon from customer logo or default
                    if (!empty($logoUrl) && $logoUrl !== BASE_URL . '/assets/images/logo.png') {
                        $faviconUrl = $logoUrl;
                    }
                    
                    // CRITICAL DEBUG: Always log to find the issue
                    if (class_exists('\App\Core\Logger')) {
                        \App\Core\Logger::info('Customer menu logo DEBUG', [
                            'tenant_id' => $tenantId,
                            'final_logo_url' => $logoUrl,
                            'final_logo_path' => $finalLogoPath,
                            'customer_logo_url' => $customer['logo_url'] ?? null,
                            'customer_logo_path' => $customer['logo_path'] ?? null,
                            'allSettings_logo_url' => $allSettings['logo_url'] ?? null,
                            'allSettings_logo_path' => $allSettings['logo_path'] ?? null,
                            'business_name' => $businessName
                        ]);
                    }
                }
            }
        } catch (\Exception $e) {
            // Use defaults if error
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('Error getting business info for customer menu', [
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        // Get current language
        require_once __DIR__ . '/../helpers/translations.php';
        $currentLanguage = getCurrentLanguage();
        $availableLanguages = ['tr', 'en']; // Can be extended from settings
        
        // Get only categories that have products, with hierarchy support and translations
        $categoriesWithProducts = $this->categoryService->getCategoriesWithProducts($currentLanguage);
        $categoriesFlat = $this->categoryService->flattenCategoryTree($categoriesWithProducts);
        
        // Get menu items with translations
        $menuItems = $this->menuItemService->getAvailableMenuItems($currentLanguage);
        
        // DEBUG: Log data counts
        if (class_exists('\App\Core\Logger')) {
            \App\Core\Logger::debug('CustomerController::tableMenu - Data loaded', [
                'table_id' => $tableId,
                'categories_with_products_count' => count($categoriesWithProducts),
                'categories_flat_count' => count($categoriesFlat),
                'menu_items_count' => count($menuItems),
                'current_language' => $currentLanguage,
                'tenant_id' => \App\Core\TenantContext::getId()
            ]);
        }
        
        // CRITICAL FIX: Remove /public/ prefix from logo URLs
        $logoUrl = str_replace('/public/', '/', $logoUrl);
        $faviconUrl = str_replace('/public/', '/', $faviconUrl);
        
        $data = [
            'table' => $table,
            'categories' => $categoriesFlat,
            'categories_tree' => $categoriesWithProducts, // Hierarchical structure for better organization
            'menu_items' => $menuItems,
            'current_language' => $currentLanguage,
            'available_languages' => $availableLanguages,
            'settings' => $allSettings,
            'business_name' => $businessName,
            'logo_url' => $logoUrl,
            'favicon_url' => $faviconUrl,
            'wifi_name' => $wifiNameCustomer,
            'wifi_password' => $wifiPasswordCustomer,
            'wifi_show_to_customer' => $wifiShowToCustomer,
            'has_multiple_wifi' => $hasMultipleWifi,
            'wifi_name_staff' => $wifiNameStaff,
            'wifi_password_staff' => $wifiPasswordStaff,
            'features' => [
                'call_waiter' => $featureService->isEnabled('call_waiter'),
                'request_bill' => $featureService->isEnabled('request_bill'),
                'online_payment' => $featureService->isEnabled('online_payment'),
                'ingredient_customization' => $featureService->isEnabled('ingredient_customization'),
                'order_tracking' => $featureService->isEnabled('order_tracking'),
                'customer_presence_tracking' => $featureService->isEnabled('customer_presence_tracking'),
                'device_fingerprint' => $featureService->isEnabled('device_fingerprint')
            ],
            'geo_fence' => $this->getGeoFenceData($table),
            'qr_menu_status' => $qrMenuStatus,
        ];
        
        $this->view('customer/qr_menu', $data);
        } catch (\Throwable $e) {
            // Log error
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('Error loading customer menu', [
                    'table_id' => $tableId,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
            
            // Check if called from tableMenuBySlug (output buffering will be active)
            // If so, re-throw to let tableMenuBySlug handle redirect and buffer cleanup
            // Otherwise, handle redirect here for direct calls
            $calledFromSlug = ob_get_level() > 0; // If output buffering is active, called from tableMenuBySlug
            
            if ($calledFromSlug) {
                // Re-throw exception to let tableMenuBySlug handle the redirect and buffer cleanup
                throw $e;
            } else {
                // Direct call - clear any output and handle redirect here
                if (ob_get_level() > 0) {
                    ob_end_clean();
                }
                header('Location: ' . BASE_URL . '/menu?error=load_failed');
                exit;
            }
        }
    }
    
    /**
     * Müşteri ekranı için admin session'ını izole et
     * Admin session bilgileri müşteri ekranında kullanılmamalı
     */
    private function isolateCustomerSession() {
        // Eğer admin session'ı varsa, müşteri ekranı için temizle
        if (isset($_SESSION['logged_in']) && isset($_SESSION['role']) && $_SESSION['role'] !== 'CUSTOMER') {
            // Admin session bilgilerini sakla (geri dönüş için)
            $_SESSION['_admin_backup'] = [
                'user_id' => $_SESSION['user_id'] ?? null,
                'username' => $_SESSION['username'] ?? null,
                'role' => $_SESSION['role'] ?? null,
                'logged_in' => $_SESSION['logged_in'] ?? false,
                'login_time' => $_SESSION['login_time'] ?? null
            ];
            
            // Müşteri ekranı için session'ı temizle
            unset($_SESSION['user_id']);
            unset($_SESSION['username']);
            unset($_SESSION['role']);
            unset($_SESSION['logged_in']);
            unset($_SESSION['login_time']);
        }
    }
    
    public function cart() {
        $cart = $_SESSION['cart'] ?? [];
        
        $data = [
            'cart' => $cart,
            'settings' => $this->settingsService->getSettings()
        ];
        
        $this->view('customer/cart', $data);
    }
    
    public function addToCart() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $requestData = \App\Core\RequestParser::getRequestData();
            $menuItemId = $requestData['menu_item_id'] ?? '';
            $quantity = intval($requestData['quantity'] ?? 1);
            $note = sanitizeInput($requestData['note'] ?? '');
            $excludedIngredients = $requestData['excluded_ingredients'] ?? [];
            $selectedExtras = $requestData['selected_extras'] ?? [];
            $customizations = $requestData['customizations'] ?? []; // Malzeme özelleştirmeleri
            
            if (empty($menuItemId) || $quantity <= 0) {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.menu_item_not_found', [], 400);
                return;
            }
            
            $menuItem = $this->menuItemService->getMenuItemById($menuItemId);
            
            if (!$menuItem || !$menuItem['is_available'] || $menuItem['stock'] < $quantity) {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.menu_item_not_found', [], 400);
                return;
            }
            
            // Get variant_id if provided (optional)
            $variantId = $requestData['variant_id'] ?? null;
            $variantPriceModifier = 0;
            $variantName = null;
            
            // If product has variants and variant_id is provided, validate it
            if (!empty($menuItem['has_variants']) && (int)$menuItem['has_variants'] === 1 && !empty($variantId)) {
                $productVariantService = \App\Core\DependencyFactory::getProductVariantService();
                $variants = $productVariantService->getActiveVariantsByProduct($menuItemId);
                $selectedVariant = null;
                foreach ($variants as $variant) {
                    if ($variant['variant_id'] === $variantId) {
                        $selectedVariant = $variant;
                        break;
                    }
                }
                
                if (!$selectedVariant) {
                    $this->toastNotificationService->sendApiResponse('error', 'Geçersiz varyant seçildi.', [], 400);
                    return;
                }
                
                $variantPriceModifier = floatval($selectedVariant['price_modifier'] ?? 0);
                $variantName = $selectedVariant['name'] ?? null;
            }
            
            // Calculate base price with variant modifier
            $basePrice = $menuItem['price'] + $variantPriceModifier;
            
            // Create cart item
            $cartItem = [
                'cart_id' => generateId('c'),
                'menu_item_id' => $menuItemId,
                'variant_id' => $variantId,
                'variant_name' => $variantName,
                'name' => $menuItem['name'],
                'description' => $menuItem['description'],
                'price' => $basePrice,
                'image_url' => $menuItem['image_url'],
                'quantity' => $quantity,
                'note' => $note,
                'excluded_ingredients' => $excludedIngredients,
                'selected_extras' => $selectedExtras,
                'customizations' => $customizations // Malzeme özelleştirmeleri
            ];
            
            // Calculate extra price
            $extraPrice = 0;
            if (!empty($selectedExtras)) {
                foreach ($selectedExtras as $extra) {
                    $extraPrice += floatval($extra['price'] ?? 0);
                }
            }
            $cartItem['extra_price'] = $extraPrice;
            $cartItem['total_price'] = ($basePrice + $extraPrice) * $quantity;
            
            // Add to cart session
            if (!isset($_SESSION['cart'])) {
                $_SESSION['cart'] = [];
            }
            
            $_SESSION['cart'][] = $cartItem;
            
            $this->apiResponse(['success' => true, 'cart_item' => $cartItem]);
        }
    }
    
    public function removeFromCart() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $requestData = \App\Core\RequestParser::getRequestData();
            $cartItemId = $requestData['cart_item_id'] ?? '';
            
            if (!empty($cartItemId) && isset($_SESSION['cart'])) {
                $cart = $_SESSION['cart'];
                $newCart = [];
                
                foreach ($cart as $item) {
                    if ($item['cart_id'] !== $cartItemId) {
                        $newCart[] = $item;
                    }
                }
                
                $_SESSION['cart'] = $newCart;
                
                $this->apiResponse(['success' => true]);
            } else {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
            }
        }
    }
    
    public function updateCartQuantity() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $requestData = \App\Core\RequestParser::getRequestData();
            $cartItemId = $requestData['cart_item_id'] ?? '';
            $delta = intval($requestData['delta'] ?? 0);
            
            if (!empty($cartItemId) && isset($_SESSION['cart']) && $delta !== 0) {
                $cart = $_SESSION['cart'];
                
                foreach ($cart as &$item) {
                    if ($item['cart_id'] === $cartItemId) {
                        $newQuantity = $item['quantity'] + $delta;
                        
                        if ($newQuantity <= 0) {
                            // Remove item if quantity is 0 or less
                            $newCart = [];
                            foreach ($cart as $cartItem) {
                                if ($cartItem['cart_id'] !== $cartItemId) {
                                    $newCart[] = $cartItem;
                                }
                            }
                            $_SESSION['cart'] = $newCart;
                        } else {
                            // Update quantity
                            $item['quantity'] = $newQuantity;
                            $item['total_price'] = ($item['price'] + ($item['extra_price'] ?? 0)) * $newQuantity;
                            $_SESSION['cart'] = $cart;
                        }
                        
                        $this->apiResponse(['success' => true]);
                        return;
                    }
                }
                
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.not_found', [], 404);
            } else {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
            }
        }
    }
    
    public function clearCart() {
        $_SESSION['cart'] = [];
        $this->apiResponse(['success' => true]);
    }
    
    public function placeOrder() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $requestData = \App\Core\RequestParser::getRequestData();
            $tableId = $requestData['table_id'] ?? '';
            $customerNote = sanitizeInput($requestData['customer_note'] ?? '');
            
            if (empty($tableId)) {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.warning.missing_fields', [], 400);
                return;
            }

            $cart = $_SESSION['cart'] ?? [];

            if (empty($cart)) {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.warning.cart_empty', [], 400);
                return;
            }
            
            // Total amount calculation and service charge are handled by OrderService
            // No need to duplicate business logic here
            
            // Get table info
            $table = $this->tableService->getTableById($tableId);
            if (!$table) {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.table_not_found', [], 404);
                return;
            }
            
            // CRITICAL: Verify table belongs to a valid tenant (tenant_id should be set from table)
            // OrderService will set tenant_id from table, so we just verify table exists
            
            // Prepare order data for service
            $items = [];
            $customizations = [];
            foreach ($cart as $item) {
                $menuItemId = $item['menu_item_id'];
                $items[] = [
                    'menu_item_id' => $menuItemId,
                    'variant_id' => $item['variant_id'] ?? null,
                    'quantity' => $item['quantity'],
                    'price' => $item['price'] + ($item['extra_price'] ?? 0),
                    'note' => $item['note'] ?? ''
                ];
                
                // Collect customizations for this menu item
                if (!empty($item['customizations'])) {
                    $customizations[$menuItemId] = $item['customizations'];
                }
            }
            
            $orderData = [
                'table_id' => $tableId,
                'items' => $items,
                'customizations' => $customizations, // Malzeme özelleştirmeleri
                'customer_note' => $customerNote,
                'order_source' => 'QR',
                'created_by' => 'customer',
                'customer_session_id' => $_SESSION['customer_session_id'] ?? null
            ];
            
            $orderResult = $this->orderService->placeOrder($orderData);
            
            if ($orderResult && isset($orderResult['order_id'])) {
                // Clear cart
                $_SESSION['cart'] = [];
                
                $this->apiResponse(['success' => true, 'order_id' => $orderResult['order_id']]);
            } else {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.create_failed', [], 500);
            }
        }
    }
    
    public function callWaiter() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $requestData = \App\Core\RequestParser::getRequestData();
            $tableId = $requestData['table_id'] ?? '';
            $type = $requestData['type'] ?? 'CALL_WAITER';
            
            if (empty($tableId)) {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.warning.missing_fields', [], 400);
            }
            
            $table = $this->tableService->getTableById($tableId);
            
            if ($table) {
                $notificationService = getNotificationService();
                $result = $notificationService->notifyWaiterCall($tableId, $table['name'], $type);
                
                if ($result) {
                    $this->apiResponse(['success' => true]);
                } else {
                    $this->toastNotificationService->sendApiResponse('error', 'notifications.error.create_failed', [], 500);
                }
            } else {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.table_not_found', [], 404);
            }
        }
    }
    
    public function requestBill() {
        // This is a specific case of calling for assistance
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $requestData = \App\Core\RequestParser::getRequestData();
            $tableId = $requestData['table_id'] ?? '';
            
            if (empty($tableId)) {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.warning.missing_fields', [], 400);
            }
            
            $table = $this->tableService->getTableById($tableId);
            
            if ($table) {
                // Update table status to payment pending
                $this->tableService->updateTableStatus($tableId, 'PAYMENT_PENDING');
                
                $notificationService = getNotificationService();
                $result = $notificationService->notifyWaiterCall($tableId, $table['name'], 'REQUEST_BILL');
                
                if ($result) {
                    $this->apiResponse(['success' => true]);
                } else {
                    $this->toastNotificationService->sendApiResponse('error', 'notifications.error.create_failed', [], 500);
                }
            } else {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.table_not_found', [], 404);
            }
        }
    }
    
    public function getCart() {
        $cart = $_SESSION['cart'] ?? [];
        $this->apiResponse($cart);
    }
    
    /**
     * Sync cart from client to session
     * Used by QR menu to persist cart across page refreshes
     */
    public function syncCart() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->apiResponse(['success' => false, 'error' => 'Method not allowed'], 405);
            return;
        }
        try {
            $requestData = \App\Core\RequestParser::getRequestData();
            $cart = $requestData['cart'] ?? [];
            $tableId = $requestData['table_id'] ?? null;

            // Temel tip doğrulaması: cart dizi olmak ZORUNDA; olmazsa session'u
            // boş diziyle yenile. Client'tan gelen tuhaf payload session'u
            // yozlaştıramasın. Ayrıca aşırı büyük payload kabul edilmez (DoS).
            if (!is_array($cart)) {
                $cart = [];
            }
            if (count($cart) > 200) {
                $cart = array_slice($cart, 0, 200);
            }

            // Her satırı normalize et. Beklenen alanlar:
            // menu_item_id (string), quantity (int >= 1), notes (string), customizations (array)
            $normalized = [];
            foreach ($cart as $row) {
                if (!is_array($row)) continue;
                $mi = $row['menu_item_id'] ?? ($row['id'] ?? null);
                if (!is_string($mi) && !is_int($mi)) continue;
                $qty = (int)($row['quantity'] ?? 1);
                if ($qty < 1) continue;
                if ($qty > 500) $qty = 500;
                $notes = (string)($row['notes'] ?? '');
                if (strlen($notes) > 500) $notes = substr($notes, 0, 500);
                $customizations = $row['customizations'] ?? [];
                if (!is_array($customizations)) $customizations = [];
                $normalized[] = [
                    'menu_item_id' => (string)$mi,
                    'quantity' => $qty,
                    'notes' => $notes,
                    'customizations' => array_slice($customizations, 0, 20),
                ];
            }

            $_SESSION['cart'] = $normalized;

            // Session key birleştirme: placeOrder `customer_table_id`'ye
            // bakıyordu, syncCart ise eski `table_id`'ye. Artık her iki
            // anahtar da aynı değere ayarlanır, böylece iki akış aynı
            // bağlamı paylaşır.
            if (is_string($tableId) && $tableId !== '' && strlen($tableId) <= 64) {
                $_SESSION['table_id'] = $tableId;
                $_SESSION['customer_table_id'] = $tableId;
            }

            $this->apiResponse(['success' => true, 'items' => count($normalized)]);
        } catch (\Throwable $e) {
            \App\Core\Logger::warning('syncCart failed', ['error' => $e->getMessage()]);
            $this->apiResponse(['success' => false, 'error' => 'sync_failed'], 500);
        }
    }
    
    public function getMenu() {
        require_once __DIR__ . '/../helpers/translations.php';
        $languageCode = $_GET['lang'] ?? getCurrentLanguage();
        
        // Get only categories that have products with translations
        $categoriesWithProducts = $this->categoryService->getCategoriesWithProducts($languageCode);
        $categoriesFlat = $this->categoryService->flattenCategoryTree($categoriesWithProducts);
        $menuItems = $this->menuItemService->getAvailableMenuItems($languageCode);
        
        $this->apiResponse([
            'categories' => $categoriesFlat,
            'categories_tree' => $categoriesWithProducts,
            'menu_items' => $menuItems,
            'language' => $languageCode
        ]);
    }
    
    public function changeLanguage() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                $requestData = \App\Core\RequestParser::getRequestData();
                $languageCode = $requestData['language_code'] ?? 'tr';
                
                if (in_array($languageCode, ['tr', 'en'])) {
                    require_once __DIR__ . '/../helpers/translations.php';
                    $translationService = getTranslationService();
                    $translationService->setLanguage($languageCode);
                    
                    // Clear cache for new language (with error handling)
                    try {
                        $cache = \App\Core\DependencyFactory::getCacheService();
                        $tenantId = \App\Core\TenantResolver::resolve();
                        if (!$tenantId && class_exists('\App\Core\TenantContext')) {
                            try {
                                $tenantId = \App\Core\TenantContext::getId();
                            } catch (\Exception $e) {
                                $tenantId = 'all';
                            }
                        }
                        if (!$tenantId) $tenantId = 'all';
                        
                        // Clear menu cache for all languages
                        foreach (['tr', 'en'] as $lang) {
                            $cache->forget('menu:items:available:' . $tenantId . ':' . $lang);
                            $cache->forget('menu:categories:with_products:' . $tenantId . ':' . $lang);
                        }
                    } catch (\Exception $e) {
                        // Cache clear failed - log but don't fail the request
                        if (class_exists('\App\Core\Logger')) {
                            \App\Core\Logger::warning('Failed to clear cache on language change', [
                                'error' => $e->getMessage(),
                                'language' => $languageCode
                            ]);
                        }
                    }
                    
                    $this->apiResponse([
                        'success' => true,
                        'language' => $languageCode,
                        'message' => 'Language changed successfully'
                    ]);
                } else {
                    $this->apiResponse([
                        'success' => false,
                        'error' => 'Invalid language code'
                    ], 400);
                }
            } catch (\Exception $e) {
                // Log error
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::error('changeLanguage failed', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
                
                $this->apiResponse([
                    'success' => false,
                    'error' => 'Failed to change language: ' . $e->getMessage()
                ], 500);
            }
        }
    }
    
    public function getTableInfo() {
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $tableId = $queryParams['table_id'] ?? '';
        
        if (!empty($tableId)) {
            $table = $this->tableService->getTableById($tableId);
            if ($table) {
                $this->apiResponse($table);
            } else {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.table_not_found', [], 404);
            }
        } else {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
        }
    }
    
    /**
     * Show menu item by slug with language support
     * @param string $slug Menu item slug
     * @param string|null $languageCode Language code from URL prefix (tr, en)
     */
    public function menuBySlug($slug = null, $languageCode = null) {
        // Güvenlik: Eğer kullanıcı giriş yapmışsa ve CUSTOMER değilse, erişimi engelle
        if (isLoggedIn() && !hasRole('CUSTOMER')) {
            \App\Core\HelperLoader::ensureLoaded();
            header('Location: ' . getAdminUrl('dashboard'));
            exit;
        }
        
        // Müşteri ekranı için admin session'ını izole et
        $this->isolateCustomerSession();
        
        // Get slug from route parameter or URL
        if (empty($slug)) {
            $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
            // Extract slug from path like /tr/menu/slug or /en/menu/slug
            if (preg_match('/\/(?:tr|en)\/menu\/([^\/]+)/', $path, $matches)) {
                $slug = $matches[1];
                $languageCode = strpos($path, '/tr/') !== false ? 'tr' : 'en';
            } elseif (preg_match('/\/menu\/([^\/]+)/', $path, $matches)) {
                $slug = $matches[1];
            }
        }
        
        // Determine language from URL prefix or session
        if (empty($languageCode)) {
            $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
            if (strpos($path, '/tr/') !== false) {
                $languageCode = 'tr';
            } elseif (strpos($path, '/en/') !== false) {
                $languageCode = 'en';
            } else {
                // Translations helper is loaded via HelperLoader
                $languageCode = getCurrentLanguage();
            }
        }
        
        // Set language in session
        // Translations helper is loaded via HelperLoader
        setLanguage($languageCode);
        
        if (empty($slug)) {
            // Slug not found, redirect to menu
            header('Location: ' . BASE_URL . '/' . $languageCode . '/menu');
            exit;
        }
        
        // Get menu item by slug
        $translationService = \App\Core\DependencyFactory::getMenuItemTranslationService();
        $menuItem = $translationService->getMenuItemBySlug($slug, $languageCode);
        
        if (!$menuItem) {
            // Menu item not found, redirect to menu
            header('Location: ' . BASE_URL . '/' . $languageCode . '/menu');
            exit;
        }
        
        // Get category
        $category = null;
        if (!empty($menuItem['category_id'])) {
            $category = $this->categoryService->getCategoryById($menuItem['category_id']);
        }
        
        // Get all translations for hreflang tags
        $allTranslations = $translationService->getTranslationsForEdit($menuItem['menu_item_id']);
        $alternateUrls = [];
        foreach ($allTranslations as $lang => $trans) {
            if (!empty($trans['slug'])) {
                $alternateUrls[$lang] = BASE_URL . '/' . $lang . '/menu/' . $trans['slug'];
            }
        }
        
        // Check if table is provided in query string
        $table = null;
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $tableId = $queryParams['table'] ?? $queryParams['table_id'] ?? '';
        if (!empty($tableId)) {
            $table = $this->tableService->getTableById($tableId);
        }
        
        $data = [
            'menu_item' => $menuItem,
            'category' => $category,
            'language_code' => $languageCode,
            'alternate_urls' => $alternateUrls,
            'table' => $table,
            'settings' => $this->settingsService->getSettings()
        ];
        
        $this->view('customer/menu_item_detail', $data);
    }
    
    private function getGeoFenceData(?array $table): array {
        try {
            $featureService = \App\Core\DependencyFactory::getFeatureService();
            if (!$featureService->isEnabled('customer_presence_tracking')) {
                return ['enabled' => false];
            }
            
            $settings = $this->settingsService->getSettings();
            $lat = floatval($settings['business_latitude'] ?? 0);
            $lng = floatval($settings['business_longitude'] ?? 0);
            $radius = intval($settings['business_radius'] ?? 500);
            
            if ((float)$lat === 0.0 && (float)$lng === 0.0) {
                return ['enabled' => false];
            }
            
            $allowRemote = !empty($table['allow_remote_access']);
            
            return [
                'enabled' => true,
                'lat' => $lat,
                'lng' => $lng,
                'radius' => $radius,
                'allow_remote' => $allowRemote,
            ];
        } catch (\Throwable $e) {
            return ['enabled' => false];
        }
    }
}
