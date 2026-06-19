<?php
namespace App\Controllers;

require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../core/DependencyFactory.php';
require_once __DIR__ . '/../core/SessionManager.php';
require_once __DIR__ . '/../core/HelperLoader.php';

use App\Core\Controller;
use App\Core\SessionManager;
use App\Core\HelperLoader;

class BusinessAdminController extends Controller {

    public function __construct() {
        parent::__construct();
        $this->checkPackageSubscription();
        $this->checkPackagePageAccess();
    }

    /**
     * Check if customer has active subscription
     * If not, redirect to package selection page
     */
    /**
     * Resolve the landing page for a non-owner user trying to reach the
     * owner dashboard. Looks up the user row once so we can honour a
     * preparation-screen assignment, falling back to the POS surface
     * and then to the generic waiter dashboard. Returning `/business/pos`
     * as the ultimate fallback keeps a `BUSINESS_MANAGER` staff member
     * productive: they can continue taking orders and issuing adisyons
     * without ever seeing the plan-selection / upgrade copy.
     */
    private function resolveStaffHome(string $userId, string $normalizedRole): string {
        try {
            if ($userId !== '') {
                $userService = \App\Core\DependencyFactory::getUserService();
                $userRow = method_exists($userService, 'findByUserId')
                    ? $userService->findByUserId($userId)
                    : null;
                if (is_array($userRow) && !empty($userRow['preparation_screen_id'])) {
                    $screenId = $userRow['preparation_screen_id'];
                    try {
                        $prepService = \App\Core\DependencyFactory::getPreparationScreenService();
                        $screen = $prepService->getScreenById($screenId);
                        if (is_array($screen) && !empty($screen['slug'])) {
                            return '/preparation-screen/' . $screen['slug'];
                        }
                    } catch (\Throwable $e) {
                        // fall through
                    }
                }
            }
        } catch (\Throwable $e) {
            // fall through to role-based defaults
        }

        $map = [
            'WAITER'  => '/waiter/dashboard',
            'GARSON'  => '/waiter/dashboard',
            'KITCHEN' => '/kitchen/dashboard',
            'MUTFAK'  => '/kitchen/dashboard',
            'CHEF'    => '/kitchen/dashboard',
            'CASHIER' => '/pos',
            'KASIYER' => '/pos',
        ];
        return $map[$normalizedRole] ?? '/pos';
    }

    private function checkPackageSubscription(): void {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $requestPath = parse_url($uri, PHP_URL_PATH) ?? $uri;

        // Only these pages are accessible without an active subscription:
        // - Dashboard (shows warning + package selection)
        // - Package listing/purchase pages
        // - Payment pages
        // - Public pages (landing, pricing, login, register)
        // - API endpoints (handled by their own controllers)
        $allowedPaths = [
            '/business/dashboard',
            '/customer/payment',
            '/customer/packages/',
            '/customer/packages/list',
            '/business/packages',
            '/api/',
        ];
        $publicPaths = ['/', '/pricing', '/register', '/login'];

        if (in_array($requestPath, $publicPaths, true)) {
            return;
        }
        foreach ($allowedPaths as $allowed) {
            if (strpos($requestPath, $allowed) === 0) {
                return;
            }
        }

        try {
            SessionManager::ensureSession();
            $customerId = SessionManager::get('customer_id') ?? $_SESSION['customer_id'] ?? null;

            if (!$customerId) {
                return; // Will be handled by individual methods
            }

            // Ensure tenant context is set
            $this->ensureTenantContext();

            $subscriptionService = \App\Core\DependencyFactory::getSubscriptionService();
            $subscription = $subscriptionService->getCustomerSubscription($customerId);

            // Check subscription status (case-insensitive)
            $subscriptionStatus = !empty($subscription['status']) ? strtoupper(trim($subscription['status'])) : null;
            $hasActiveSubscription = $subscription && $subscriptionStatus === 'ACTIVE';

            // Fallback: TrialService checks both customer_id AND business_id columns
            if (!$hasActiveSubscription) {
                try {
                    $trialService = \App\Core\DependencyFactory::getTrialService();
                    if ($trialService->hasPaidSubscription($customerId)) {
                        $hasActiveSubscription = true;
                    }
                } catch (\Exception $e) {
                    // Ignore fallback errors
                }
            }

            if (!$hasActiveSubscription) {
                // Allow dashboard render with package-selection overlay — DO NOT redirect to /business/dashboard
                // (the old behaviour caused ERR_TOO_MANY_REDIRECTS because the constructor of
                // BusinessAdminController kept re-evaluating this exact same condition on every
                // request). The dashboard method already handles the no-subscription state by
                // rendering admin/customer_dashboard with package options, so we simply return
                // here and let the dashboard method decide what to show.
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::info('BusinessAdminController: No active subscription - dashboard will render package-selection state', [
                        'customer_id' => $customerId,
                        'has_subscription' => !empty($subscription),
                        'subscription_status' => $subscriptionStatus ?? 'none'
                    ]);
                }
                return;
            }
        } catch (\Exception $e) {
            // If subscription check fails, allow access (graceful degradation)
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('Package subscription check failed', [
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Enforce page-level access based on the active package's permissions.
     * BUSINESS_MANAGER users with a subscription can only access pages
     * whose permission prefixes are included in their package.
     * Super Admin and Manager bypass this check.
     */
    private function checkPackagePageAccess(): void {
        try {
            SessionManager::ensureSession();

            // Super Admin and Manager bypass
            if ($this->isSuperAdmin()) {
                return;
            }
            $roleCode = $_SESSION['role'] ?? '';
            $normalizedRole = strtoupper(str_replace('ROLE_', '', trim($roleCode)));
            if (in_array($normalizedRole, ['MANAGER', 'ADMIN', 'ADMINISTRATOR', 'SUPER_ADMIN', 'QODMIN'])) {
                return;
            }
            
            $isTrial = ($normalizedRole === 'TRIAL');
            $isBusinessManager = ($normalizedRole === 'BUSINESS_MANAGER');
            
            if (!$isTrial && !$isBusinessManager) {
                return;
            }

            $customerId = SessionManager::get('customer_id') ?? $_SESSION['customer_id'] ?? null;
            if (!$customerId) {
                return;
            }

            $uri = $_SERVER['REQUEST_URI'] ?? '';
            $requestPath = parse_url($uri, PHP_URL_PATH) ?? $uri;

            // Map routes to required permission prefixes
            $routePermissionMap = [
                '/business/settings'    => 'settings',
                '/business/operations'  => 'orders',
                '/business/finance'     => 'finance',
                '/business/analysis'    => 'dashboard',
                '/business/menu'        => 'menu',
                '/business/categories'  => 'menu',
                '/business/tables'      => 'tables',
                '/business/orders'      => 'orders',
                '/business/reservations' => 'reservations',
                '/business/staff'       => 'staff',
                '/business/printers'    => 'printers',
                '/business/kitchen'     => 'kitchen',
                '/business/waiter'      => 'waiter',
                '/business/pos'         => 'pos',
                '/business/preparation-screen' => 'preparation-screens',
                '/business/reports'     => 'reports',
                '/business/stock'       => 'stock',
                '/business/inventory'   => 'stock',
                '/business/product-sales' => 'reports',
            ];

            $requiredPrefix = null;
            foreach ($routePermissionMap as $route => $prefix) {
                if (strpos($requestPath, $route) === 0) {
                    $requiredPrefix = $prefix;
                    break;
                }
            }

            if (!$requiredPrefix) {
                return;
            }

            // TRIAL: only allow specific feature pages
            if ($isTrial) {
                $trialAllowedPrefixes = ['menu', 'tables', 'orders', 'dashboard'];
                if (!in_array($requiredPrefix, $trialAllowedPrefixes, true)) {
                    $this->toastNotificationService->setFlash('dashboard_warning', 'Bu özellik deneme sürümünde kullanılamaz. Lütfen bir paket satın alın.');
                    header('Location: ' . BASE_URL . '/business/dashboard');
                    exit;
                }
                return;
            }

            // BUSINESS_MANAGER: check package-based permissions
            $subscriptionService = \App\Core\DependencyFactory::getSubscriptionService();
            $subscription = $subscriptionService->getCustomerSubscription($customerId);

            if (!$subscription || strtoupper($subscription['status'] ?? '') !== 'ACTIVE') {
                return; // checkPackageSubscription already handles this
            }

            $packagePermissions = [];
            try {
                $packagePermissions = $subscriptionService->getSubscriptionPermissions($subscription['subscription_id']);
            } catch (\Exception $e) {
                return; // Graceful degradation
            }

            if (empty($packagePermissions)) {
                return;
            }

            $hasPagePermission = false;
            foreach ($packagePermissions as $perm) {
                if (strpos($perm, $requiredPrefix . '.') === 0 || $perm === $requiredPrefix) {
                    $hasPagePermission = true;
                    break;
                }
            }

            if (!$hasPagePermission) {
                $this->toastNotificationService->setFlash('dashboard_warning', 'Bu sayfaya erişim paketinize dahil değil.');
                header('Location: ' . BASE_URL . '/business/dashboard');
                exit;
            }
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('Package page access check failed', [
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Override the view method to use admin layout
     * BusinessAdminController uses admin_layout.php for consistent sidebar
     */
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
            require_once __DIR__ . '/../core/Security/CSRFManager.php';
            $data['csrf_token'] = \App\Core\Security\CSRFManager::generateToken();
        }

        $viewPath = __DIR__ . '/../views/' . $view . '.php';
        if (file_exists($viewPath)) {
            try {
                // admin/dashboard and admin/customer_dashboard need admin_layout.php for sidebar
                // These views only contain content, not full HTML structure
                $isDashboardView = in_array($view, ['admin/dashboard', 'admin/customer_dashboard']);

                if ($isDashboardView) {
                    // Render dashboard views with admin_layout.php (contains sidebar with package-based filtering)
                    $content = $this->renderViewContent($viewPath, $data);
                    $title = $data['title'] ?? 'Admin Panel - Qordy';
                    extract($data);
                    include __DIR__ . '/../views/layouts/admin_layout.php';
                } else {
                    // Other views use admin_layout.php wrapper
                    // This ensures consistent sidebar across all business admin pages
                    // Sidebar menus will be filtered by package permissions in NavigationService

                    // Ensure currentUser is available for layout (layout needs it for role detection)
                    if (!isset($data['currentUser'])) {
                        $userId = SessionManager::get('user_id');
                        $currentUser = null;
                        if ($userId) {
                            try {
                                $userService = \App\Core\DependencyFactory::getUserService();
                                $currentUser = $userService->findByUserId($userId);
                            } catch (\Exception $e) {
                                // User not found, continue with null
                            }
                        }
                        $data['currentUser'] = $currentUser ?? [
                            'user_id' => $userId,
                            'role' => SessionManager::get('role'),
                            'role_id' => SessionManager::get('role_id'),
                            'name' => SessionManager::get('first_name') . ' ' . SessionManager::get('last_name')
                        ];
                    }

                    $content = $this->renderViewContent($viewPath, $data);
                    $title = $data['title'] ?? 'Müşteri Paneli - Qordy';

                    // Extract data for layout (layout needs access to variables)
                    extract($data);

                    include __DIR__ . '/../views/layouts/admin_layout.php';
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
     * Business admin dashboard
     *
     * v2.1: Accepts optional $rangeParam from path-based routing
     * (e.g. /business/dashboard/year → $rangeParam = 'year').
     * Falls back to ?range=year for backward compatibility.
     */
    public function dashboard(?string $rangeParam = null) {
        // Map path-param → $_GET['range'] so downstream reads it uniformly
        if ($rangeParam !== null && $rangeParam !== '') {
            $_GET['range'] = $rangeParam;
            // v2.2: Persist range in session for poller/API
            $_SESSION['dashboard_range'] = $rangeParam;
        }
        // Normalize the active range (path-param -> ?range -> session -> 'today')
        $currentRange = $_GET['range'] ?? ($_SESSION['dashboard_range'] ?? 'today');
        $allowedRanges = ['today', 'week', 'month', '3months', '6months', '9months', 'year', 'custom'];
        if (!in_array($currentRange, $allowedRanges, true)) {
            $currentRange = 'today';
        }
        $_GET['range'] = $currentRange;
        $_SESSION['dashboard_range'] = $currentRange;
        try {
            // Get business-specific data
            SessionManager::ensureSession();
            $customerId = SessionManager::get('customer_id') ?? $_SESSION['customer_id'] ?? null;

            // For Super Admin, allow access without customer_id (they can view any business)
            $isSuperAdmin = $this->isSuperAdmin();

            // Log session data for debugging
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::debug('BusinessAdminController::dashboard - Session check', [
                    'customer_id' => $customerId,
                    'user_id' => SessionManager::get('user_id'),
                    'role' => SessionManager::get('role'),
                    'is_super_admin' => $isSuperAdmin,
                    'session_keys' => array_keys($_SESSION ?? [])
                ]);
            }

            // -----------------------------------------------------------------
            // ROLE / CHANNEL GUARD
            // -----------------------------------------------------------------
            // /business/dashboard is the OWNER surface: it can legitimately
            // render the "pick a plan" upgrade screen when the account has
            // no active subscription. Staff members (anyone who authenticated
            // via PIN or sits under a non-owner role) must never see that
            // screen because:
            //   1. they cannot purchase packages on the subdomain; that is
            //      an account-holder action on the main domain.
            //   2. the copy ("İşletmenizi dijitalleştirmek için bir plan
            //      seçin") is nonsensical for e.g. a waiter.
            //
            // Signals we inspect (any of these => bounce staff away):
            //   * `login_channel === 'staff_pin'` (explicit, set in
            //     AuthenticationService::authenticateWithPin).
            //   * Role matches one of the well-known staff codes
            //     (WAITER / KITCHEN / CASHIER / …).
            //   * We are on a tenant subdomain (host !== main domain) AND
            //     the role is BUSINESS_MANAGER. Staff users are commonly
            //     assigned BUSINESS_MANAGER by the role picker even when
            //     they operate as waiters; in that case the safe answer
            //     on a subdomain is to send them to an operational page
            //     rather than the owner-only plan picker.
            // -----------------------------------------------------------------
            $currentRole = (string)(SessionManager::get('role') ?? $_SESSION['role'] ?? '');
            $normalizedRole = strtoupper(str_replace('ROLE_', '', trim($currentRole)));
            $loginChannel = (string)(SessionManager::get('login_channel') ?? $_SESSION['login_channel'] ?? '');
            $userId = (string)(SessionManager::get('user_id') ?? $_SESSION['user_id'] ?? '');

            $currentHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $baseHost = defined('BASE_DOMAIN') ? BASE_DOMAIN : 'qordy.com';
            $hostNoPort = strtolower(preg_replace('/:\d+$/', '', $currentHost));
            $isSubdomain = $hostNoPort !== '' && $hostNoPort !== $baseHost
                && $hostNoPort !== 'www.' . $baseHost
                && str_ends_with($hostNoPort, '.' . $baseHost);

            $wellKnownStaffRedirectMap = [
                'WAITER'   => '/waiter/dashboard',
                'GARSON'   => '/waiter/dashboard',
                'KITCHEN'  => '/kitchen/dashboard',
                'MUTFAK'   => '/kitchen/dashboard',
                'CHEF'     => '/kitchen/dashboard',
                'CASHIER'  => '/pos',
                'KASIYER'  => '/pos',
            ];

            $shouldBounceStaff = false;
            $bounceTarget = null;
            $bounceReason = '';

            if (!$isSuperAdmin) {
                if (isset($wellKnownStaffRedirectMap[$normalizedRole])) {
                    $shouldBounceStaff = true;
                    $bounceTarget = $wellKnownStaffRedirectMap[$normalizedRole];
                    $bounceReason = 'well_known_staff_role';
                } elseif ($loginChannel === 'staff_pin') {
                    $shouldBounceStaff = true;
                    $bounceTarget = $this->resolveStaffHome($userId, $normalizedRole);
                    $bounceReason = 'login_channel_pin';
                } elseif ($isSubdomain
                    && $normalizedRole === 'BUSINESS_MANAGER'
                    && $loginChannel !== 'email_password') {
                    // Subdomain + BUSINESS_MANAGER with no explicit
                    // email-owner channel is almost always a staff member
                    // whose role was assigned to the shared "business
                    // manager" code during onboarding. Owners buy/renew
                    // packages from the main domain with an email/password
                    // login; those sessions carry login_channel =
                    // email_password and remain on the upgrade screen.
                    // Everyone else on the subdomain is sent to an
                    // operational home so they never see the owner-only
                    // plan picker.
                    $shouldBounceStaff = true;
                    $bounceTarget = $this->resolveStaffHome($userId, $normalizedRole);
                    $bounceReason = 'subdomain_business_manager_non_owner';
                }
            }

            if ($shouldBounceStaff && $bounceTarget) {
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::info('BusinessAdminController::dashboard - Bouncing user away from owner surface', [
                        'role' => $currentRole,
                        'normalized_role' => $normalizedRole,
                        'login_channel' => $loginChannel,
                        'reason' => $bounceReason,
                        'target' => $bounceTarget,
                        'host' => $currentHost,
                    ]);
                }
                header('Location: ' . $protocol . '://' . $currentHost . $bounceTarget);
                exit;
            }

            // NO AUTH CHECK - Removed login redirect

            // Ensure tenant context is set for this customer
            if ($customerId) {
                $this->ensureTenantContext();
            }

            // Get business info
            $customerService = \App\Core\DependencyFactory::getCustomerService();
            $business = null;

            if ($customerId) {
                try {
                    $business = $customerService->getById($customerId);
                    if (!$business && method_exists($customerService, 'getCustomerById')) {
                        $business = $customerService->getCustomerById($customerId);
                    }

                    // Set tenant context if business found
                    if ($business && class_exists('\App\Core\TenantContext')) {
                        \App\Core\TenantContext::set($business);
                    }
                } catch (\Exception $e) {
                    if (class_exists('\App\Core\Logger')) {
                        \App\Core\Logger::error('Error getting business info', [
                            'error' => $e->getMessage(),
                            'customer_id' => $customerId
                        ]);
                    }
                }
            }

            // For Super Admin without customer_id, redirect to super admin dashboard
            if (!$business && $isSuperAdmin) {
                header('Location: ' . BASE_URL . '/qodmin/dashboard');
                exit;
            }

            // NO AUTH CHECK - Allow access even without business

            // Get business statistics
            $financeService = \App\Core\DependencyFactory::getFinanceService();
            $orderService = \App\Core\DependencyFactory::getOrderService();
            $userService = \App\Core\DependencyFactory::getUserService();

            // Get recent orders for this business
            $recentOrders = [];
            try {
                if (method_exists($orderService, 'getRecentOrders')) {
                    $recentOrders = $orderService->getRecentOrders(10); // Last 10 orders
                }
                if (!is_array($recentOrders)) {
                    $recentOrders = [];
                }
            } catch (\Exception $e) {
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::error('Error getting recent orders', [
                        'error' => $e->getMessage()
                    ]);
                }
                $recentOrders = [];
            }

            // Get financial summary (last 30 days)
            $financialSummary = [];
            try {
                if (method_exists($financeService, 'getFinancialSummary')) {
                    $startDate = date('Y-m-d', strtotime('-30 days'));
                    $endDate = date('Y-m-d');
                    $financialSummary = $financeService->getFinancialSummary($startDate, $endDate, $orderService);
                }
                if (!is_array($financialSummary)) {
                    $financialSummary = [];
                }
            } catch (\Exception $e) {
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::error('Error getting financial summary', [
                        'error' => $e->getMessage()
                    ]);
                }
                $financialSummary = [];
            }

            // Get user counts by role for this business
            $staff = [];
            $staffCount = 0;
            try {
                if (method_exists($userService, 'getAll')) {
                    $staff = $userService->getAll();
                }
                if (is_array($staff)) {
                    $staffCount = count($staff);
                }
            } catch (\Exception $e) {
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::error('Error getting staff', [
                        'error' => $e->getMessage()
                    ]);
                }
                $staff = [];
                $staffCount = 0;
            }

            // PERFORMANCE OPTIMIZATION: Cache subscription check in session to avoid repeated DB queries
            // Check subscription status (skip for Super Admin)
            $subscriptionService = \App\Core\DependencyFactory::getSubscriptionService();
            $subscription = null;
            $hasSubscription = false;

            // Super Admin always has access
            if ($isSuperAdmin) {
                $hasSubscription = true;
            } else if ($customerId) {
                // PERFORMANCE: Use session cache for subscription check (valid for 5 minutes)
                $cacheKey = 'subscription_check_' . $customerId;
                $cacheTime = 300; // 5 minutes
                $cachedSubscription = $_SESSION[$cacheKey . '_data'] ?? null;
                $cachedTime = $_SESSION[$cacheKey . '_time'] ?? 0;
                $cachedPhase = $_SESSION[$cacheKey . '_phase'] ?? null;

                if ($cachedSubscription !== null && (time() - $cachedTime) < $cacheTime) {
                    // Use cached subscription data
                    $subscription = $cachedSubscription;
                    $hasSubscription = $subscription && !empty($subscription['status']) && strtoupper($subscription['status']) === 'ACTIVE';
                } else {
                    // Cache expired or not found - fetch from database
                    try {
                        $subscription = $subscriptionService->getCustomerSubscription($customerId);

                        $_SESSION[$cacheKey . '_data'] = $subscription;
                        $_SESSION[$cacheKey . '_time'] = time();

                        $hasSubscription = $subscription && !empty($subscription['status']) && strtoupper($subscription['status']) === 'ACTIVE';
                    } catch (\Exception $e) {
                        if (class_exists('\App\Core\Logger')) {
                            \App\Core\Logger::error('Error checking subscription', [
                                'error' => $e->getMessage(),
                                'customer_id' => $customerId,
                                'trace' => $e->getTraceAsString()
                            ]);
                        }
                    }
                }

                // KRİTİK: Aktif trial kullanıcıları da dashboard'a erişmeli.
                // TrialService.getSubscriptionPhase trial/active/grace phase'lerinde
                // access verir; sadece suspended/expired erişimi engeller.
                if (!$hasSubscription) {
                    try {
                        $trialService = \App\Core\DependencyFactory::getTrialService();
                        // Yakın kontrolü: cached phase varsa onu kullan, yoksa DB.
                        $phaseData = $cachedPhase;
                        if ($phaseData === null || (time() - $cachedTime) >= $cacheTime) {
                            $phaseData = $trialService->getSubscriptionPhase($customerId);
                            $_SESSION[$cacheKey . '_phase'] = $phaseData;
                        }
                        $phase = $phaseData['phase'] ?? 'none';
                        if (in_array($phase, ['trial', 'active', 'grace'], true)) {
                            $hasSubscription = true;
                            if (empty($subscription) && !empty($phaseData['subscription'])) {
                                $subscription = $phaseData['subscription'];
                            }
                        }
                    } catch (\Exception $e) {
                        // Ignore fallback errors
                    }
                }

                // Ek fallback: TrialService checks both customer_id AND business_id columns
                if (!$hasSubscription) {
                    try {
                        $trialService = \App\Core\DependencyFactory::getTrialService();
                        if ($trialService->hasPaidSubscription($customerId)) {
                            $hasSubscription = true;
                        }
                    } catch (\Exception $e) {
                        // Ignore fallback errors
                    }
                }
            }

            if ($hasSubscription) {
                // Has package - use full admin dashboard design with business data
                $tableService = \App\Core\DependencyFactory::getTableService();

                // Get today's data - use business date range for overnight support
                $settingsService = \App\Core\DependencyFactory::getSystemSettingsService();
                $businessRange = $settingsService->getBusinessDateRange();
                $today = $businessRange['date'];
                $dailyRevenue = $orderService->getDailyRevenueByDatetimeRange($businessRange['start_datetime'], $businessRange['end_datetime']);
                $allTables = $tableService->getAllTables();
                $allTables = $allTables ?: [];

                // Get order status constants
                require_once __DIR__ . '/../core/Helpers/ConstantsHelper.php';
                $pendingStatus = \App\Core\Helpers\ConstantsHelper::getOrderStatus('PENDING');
                $servedStatus = \App\Core\Helpers\ConstantsHelper::getOrderStatus('SERVED');

                $pendingOrders = $orderService->getOrdersByStatus($pendingStatus);
                $topSellingItems = $orderService->getTopSellingItems(5);
                $activeTables = $tableService->getActiveTables();

                // Calculate Row 2 KPI values - use datetime range for business hours (matches ciro reset)
                $todayOrders = $orderService->getOrdersByDatetimeRange($businessRange['start_datetime'], $businessRange['end_datetime']);
                $todayOrders = is_array($todayOrders) ? $todayOrders : [];
                $totalOrdersToday = count($todayOrders);

                $avgOrderValue = $totalOrdersToday > 0 ? ($dailyRevenue / $totalOrdersToday) : 0;

                // Unique customers (unique table_ids) today
                $uniqueTablesToday = array_unique(array_column($todayOrders, 'table_id'));
                $uniqueCustomersToday = count(array_filter($uniqueTablesToday, function($tableId) {
                    return !empty($tableId);
                }));

                // Today's served orders count
                $todayServedOrders = array_filter($todayOrders, function($order) use ($servedStatus) {
                    return ($order['status'] ?? '') === $servedStatus;
                });
                $todayServedCount = count($todayServedOrders);

                // Calculate Row 1 KPI values
                $occupiedCount = $tableService->getOccupiedCount();
                $occupancyPercent = count($allTables) > 0 ? round(($occupiedCount / count($allTables)) * 100) : 0;
                $pendingOrdersCount = is_array($pendingOrders) ? count($pendingOrders) : 0;
                
                // Calculate net profit using actual expenses (revenue - expenses)
                $financeService = \App\Core\DependencyFactory::getFinanceService();
                $todayExpenses = $financeService->getTotalExpensesByDateRange($today, $today);
                $estimatedProfit = $dailyRevenue - $todayExpenses;

                $zones = $tableService->getAllZones();

                // Get recent notifications
                $recentNotifications = [];
                try {
                    $recentNotifications = $this->notificationService->getAll(10);
                    $recentNotifications = is_array($recentNotifications) ? $recentNotifications : [];
                } catch (\Exception $e) {
                    $recentNotifications = [];
                }

                // Get pending approval requests
                $pendingApprovals = [];
                try {
                    $approvalService = \App\Core\DependencyFactory::getOrderEditApprovalService();
                    $pendingApprovals = $approvalService->getPendingApprovals();
                    $pendingApprovals = is_array($pendingApprovals) ? $pendingApprovals : [];
                } catch (\Exception $e) {
                    $pendingApprovals = [];
                }

                // PERFORMANCE OPTIMIZATION: Get only recent orders (last 5) instead of all orders
                // getAllOrders() without limit can load thousands of orders - very slow!
                $recentOrdersForDashboard = [];
                try {
                    if (method_exists($orderService, 'getRecentOrders')) {
                        $recentOrdersForDashboard = $orderService->getRecentOrders(5);
                    } else {
                        // Fallback: Use business day orders only
                        $todayOrders = $orderService->getOrdersByDatetimeRange($businessRange['start_datetime'], $businessRange['end_datetime']);
                        $recentOrdersForDashboard = is_array($todayOrders) ? array_slice($todayOrders, 0, 5) : [];
                    }
                    $recentOrdersForDashboard = is_array($recentOrdersForDashboard) ? $recentOrdersForDashboard : [];
                } catch (\Exception $e) {
                    $recentOrdersForDashboard = [];
                }

                // Calculate active orders (pending, preparing, ready)
                $activeOrdersCount = 0;
                $activeStatuses = ['pending', 'preparing', 'ready'];
                if (!empty($recentOrdersForDashboard)) {
                    $activeOrdersCount = count(array_filter($recentOrdersForDashboard, function($o) use ($activeStatuses) {
                        return in_array($o['status'] ?? 'pending', $activeStatuses);
                    }));
                }

                // Use admin/dashboard view (AI Danışman butonlu) with full business data
                $this->view('admin/dashboard', [
                    'daily_revenue' => $dailyRevenue,
                    'occupancy_percent' => $occupancyPercent,
                    'pending_orders_count' => $pendingOrdersCount,
                    'estimated_profit' => $estimatedProfit,
                    'active_tables_count' => $occupiedCount,
                    'unread_notifications_count' => $this->notificationService ? ($this->notificationService->getUnreadCount() ?: 0) : 0,
                    'recent_orders' => $recentOrdersForDashboard,
                    'active_orders' => $activeOrdersCount,
                    'top_selling_items' => is_array($topSellingItems) ? $topSellingItems : [],
                    'active_tables' => is_array($activeTables) ? $activeTables : [],
                    'tables' => $allTables,
                    'zones' => $zones,
                    'notifications' => $recentNotifications,
                    'pending_approvals' => $pendingApprovals,
                    'total_orders_today' => $totalOrdersToday,
                    'avg_order_value' => $avgOrderValue,
                    'unique_customers_today' => $uniqueCustomersToday,
                    'today_served_count' => $todayServedCount,
                    'range' => $currentRange,
                    'range_key' => $currentRange,
                ]);
            } else {
                // No package - use customer dashboard but with content
                // Get user data
                $user = null;
                try {
                    $userId = $_SESSION['user_id'] ?? null;
                    if ($userId) {
                        $user = $userService->findByUserId($userId);
                    }
                } catch (\Exception $e) {
                    // User not found, continue with business data
                }

                // Get packages for display
                $packages = [];
                try {
                    $packageService = \App\Core\DependencyFactory::getPackageService();
                    $packages = $packageService->getActivePackages();
                } catch (\Exception $e) {
                    $packages = [];
                }

                $pendingBankTransfer = null;
                $customerIdForTransfer = $business['customer_id'] ?? null;
                if ($customerIdForTransfer) {
                    try {
                        $bankTransferService = \App\Core\DependencyFactory::getBankTransferService();
                        $pendingList = $bankTransferService->getTransfersByCustomerId($customerIdForTransfer, 'pending');
                        $pendingBankTransfer = !empty($pendingList) ? $pendingList[0] : null;
                    } catch (\Exception $e) {
                        // ignore
                    }
                }

                // Use admin/customer_dashboard view (paket satın alma sayfası) for no-subscription state
                $this->view('admin/customer_dashboard', [
                    'user' => $user ?? ['name' => $business['email'] ?? ''],
                    'customer' => $business,
                    'packages' => $packages,
                    'subscription' => null,
                    'pendingBankTransfer' => $pendingBankTransfer,
                    'showPackageSelection' => $pendingBankTransfer ? false : false,
                    'stats' => [
                        'active_users' => 0,
                        'total_orders' => 0,
                        'monthly_orders' => 0,
                        'monthly_revenue' => 0
                    ]
                ]);
            }

        } catch (\Throwable $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('Business Admin Dashboard error', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]);
            }

            // Show error page instead of redirect loop
            $this->toastNotificationService->setFlash('dashboard_error', 'Panel bilgileri alınırken hata oluştu: ' . $e->getMessage());

            // Fallback data - try to resolve real tenant info before falling back
            $fallbackCompanyName = '';
            try {
                if (!empty($customerId)) {
                    $customerService = \App\Core\DependencyFactory::getCustomerService();
                    if ($customerService) {
                        $cust = $customerService->getById($customerId);
                        if (is_array($cust)) {
                            $fallbackCompanyName = $cust['company_name']
                                ?? ($cust['business_name']
                                ?? ($cust['name'] ?? ''));
                        }
                    }
                }
                if ($fallbackCompanyName === '' && class_exists('\\App\\Core\\TenantContext')) {
                    $ctx = \App\Core\TenantContext::get();
                    if (is_array($ctx)) {
                        $fallbackCompanyName = $ctx['company_name']
                            ?? ($ctx['business_name']
                            ?? ($ctx['name'] ?? ''));
                    }
                }
            } catch (\Throwable $resolveEx) {
                // Silently ignore and render a generic banner below
            }
            $data = [
                'business' => [
                    'customer_id' => $customerId ?? '',
                    'company_name' => $fallbackCompanyName !== '' ? $fallbackCompanyName : 'İşletme',
                ],
                'recent_orders' => [],
                'financial_summary' => [],
                'staff_count' => 0,
                'page' => 'business-admin-dashboard'
            ];

            try {
                $this->view('business_admin/dashboard', $data);
            } catch (\Throwable $viewError) {
                // If view also fails, show simple error message
                http_response_code(500);
                echo '<!DOCTYPE html><html><head><title>Hata</title></head><body>';
                echo '<h1>Bir hata oluştu</h1>';
                echo '<p>Panel yüklenirken bir sorun oluştu. Lütfen daha sonra tekrar deneyin.</p>';
                echo '<p><a href="' . BASE_URL . '/login">Giriş Sayfasına Dön</a></p>';
                echo '</body></html>';
                exit;
            }
        }
    }

    /**
     * Operations section
     */
    public function operations() {
        try {
            $customerId = $_SESSION['customer_id'] ?? null;

            if (!$customerId) {
                $this->toastNotificationService->setFlash('dashboard_error', 'İşletme bilgisi bulunamadı');
                // CRITICAL: Use current host (with subdomain) for redirect
                $currentHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                header('Location: ' . $protocol . '://' . $currentHost . '/login');
                exit;
            }

            // Get operations data for this business
            $orderService = \App\Core\DependencyFactory::getOrderService();
            $reservationService = \App\Core\DependencyFactory::getReservationService();
            $tableService = \App\Core\DependencyFactory::getTableService();

            $orders = $orderService->getOrdersByDateRange(date('Y-m-d', strtotime('-7 days')), date('Y-m-d'));
            $reservations = $reservationService->getReservationsByDateRange(date('Y-m-d', strtotime('-7 days')), date('Y-m-d'));
            $tables = $tableService->getAllTables();

            $data = [
                'orders' => $orders,
                'reservations' => $reservations,
                'tables' => $tables,
                'page' => 'business-admin-operations'
            ];

            $this->view('business_admin/operations', $data);

        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('Business Admin Operations error', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }

            $this->toastNotificationService->setFlash('dashboard_error', 'Operasyon bilgileri alınırken hata oluştu');
            header('Location: ' . BASE_URL . '/business/dashboard');
            exit;
        }
    }

    /**
     * Finance section
     */
    public function finance() {
        try {
            $customerId = $_SESSION['customer_id'] ?? null;

            if (!$customerId) {
                $this->toastNotificationService->setFlash('dashboard_error', 'İşletme bilgisi bulunamadı');
                // CRITICAL: Use current host (with subdomain) for redirect
                $currentHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                header('Location: ' . $protocol . '://' . $currentHost . '/login');
                exit;
            }

            // Get finance data for this business
            $financeService = \App\Core\DependencyFactory::getFinanceService();
            $paymentService = \App\Core\DependencyFactory::getPaymentService();

            $financialData = $financeService->getFinancialData($customerId);
            $paymentMethods = $paymentService->getAllPaymentMethods();
            $incomeExpenses = $financeService->getIncomeExpenses($customerId);

            $data = [
                'financial_data' => $financialData,
                'payment_methods' => $paymentMethods,
                'income_expenses' => $incomeExpenses,
                'page' => 'business-admin-finance'
            ];

            $this->view('business_admin/finance', $data);

        } catch (\Throwable $e) {
            // NOTE: Must be Throwable (not Exception) — PHP 8 raises a bare
            // Error for `Call to undefined method`, which was what
            // originally surfaced as the generic 500 page on this route.
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('Business Admin Finance error', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }

            $this->toastNotificationService->setFlash('dashboard_error', 'Finans bilgileri alınırken hata oluştu');
            header('Location: ' . BASE_URL . '/business/dashboard');
            exit;
        }
    }

    /**
     * Settings section - Business settings (financial, wifi, working hours)
     */
    public function settings() {
        try {
            // SECURITY: ensure TenantContext is populated from session/subdomain
            // before loading any tenant-scoped data. Without this the settings
            // repository could fall back to another tenant's row.
            $this->ensureTenantContext();
            $customerId = $_SESSION['customer_id'] ?? null;

            if (!$customerId) {
                $this->toastNotificationService->setFlash('dashboard_error', 'İşletme bilgisi bulunamadı');
                $currentHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                header('Location: ' . $protocol . '://' . $currentHost . '/login');
                exit;
            }

            // Get system settings for this business
            $settingsService = \App\Core\DependencyFactory::getSystemSettingsService();
            $settings = $settingsService->getSettings();
            
            // Financial settings
            $serviceChargeRate = $settings['service_charge_rate'] ?? '0';
            $coverCharge = $settings['cover_charge'] ?? '0';
            $currency = $settings['currency'] ?? 'TRY';
            $orderIdPrefix = $settings['order_id_prefix'] ?? 'cd';
            
            // WiFi settings
            $wifiName = $settings['wifi_name'] ?? '';
            $wifiPassword = $settings['wifi_password'] ?? '';
            $wifiShowToCustomer = isset($settings['wifi_show_to_customer']) && ($settings['wifi_show_to_customer'] === '1' || $settings['wifi_show_to_customer'] === 1 || $settings['wifi_show_to_customer'] === true);
            
            // Working hours settings (centralized default comes from service)
            $workingHoursEnabled = isset($settings['working_hours_enabled']) && ($settings['working_hours_enabled'] === '1' || $settings['working_hours_enabled'] === 1 || $settings['working_hours_enabled'] === true);
            $workingHoursDays = $settingsService->resolveWorkingHoursDays($settings);

            $data = [
                'settings' => $settings,
                'service_charge_rate' => $serviceChargeRate,
                'cover_charge' => $coverCharge,
                'currency' => $currency,
                'order_id_prefix' => $orderIdPrefix,
                'wifi_name' => $wifiName,
                'wifi_password' => $wifiPassword,
                'wifi_show_to_customer' => $wifiShowToCustomer,
                'working_hours_enabled' => $workingHoursEnabled,
                'working_hours_days' => $workingHoursDays,
                'page' => 'business-admin-settings'
            ];

            $this->view('business_admin/settings', $data);

        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('Business Admin Settings error', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }

            $this->toastNotificationService->setFlash('dashboard_error', 'Ayarlar bilgileri alınırken hata oluştu');
            header('Location: ' . BASE_URL . '/business/dashboard');
            exit;
        }
    }

    /**
     * Update business settings (API endpoint)
     */
    public function updateBusinessSettings() {
        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_request', [], 405);
                return;
            }

            $customerId = $_SESSION['customer_id'] ?? null;

            if (!$customerId) {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 401);
                return;
            }

            // Ensure tenant context is set
            $this->ensureTenantContext();

            $input = json_decode(file_get_contents('php://input'), true);
            if ($input === null) {
                $input = $_POST;
            }

            // Use system settings service for business-level settings
            $settingsService = \App\Core\DependencyFactory::getSystemSettingsService();

            // Allowed settings that business managers can update
            $allowedSettings = [
                'service_charge_rate', 'cover_charge', 'currency',
                'order_id_prefix',
                'wifi_name', 'wifi_password', 'wifi_show_to_customer',
                'working_hours_enabled', 'working_hours_days',
                'waiter_delete_requires_approval',
                'order_edit_requires_approval', 'order_edit_approval_role',
                'staff_show_delete_reduce_buttons', 'manager_show_delete_reduce_buttons',
                'business_latitude', 'business_longitude', 'business_radius', 'business_address'
            ];

            $settingsToUpdate = [];
            
            // Handle checkbox fields explicitly (they may be '0' string)
            // Only update if explicitly provided in input
            $checkboxFields = ['wifi_show_to_customer', 'working_hours_enabled', 'order_edit_requires_approval', 'staff_show_delete_reduce_buttons', 'manager_show_delete_reduce_buttons'];
            foreach ($checkboxFields as $checkboxKey) {
                if (isset($input[$checkboxKey])) {
                    $settingsToUpdate[$checkboxKey] = ($input[$checkboxKey] === '1' || $input[$checkboxKey] === 1 || $input[$checkboxKey] === true || $input[$checkboxKey] === 'true') ? '1' : '0';
                }
                // If not set, don't update (preserve existing value)
            }
            
            // Handle other fields
            foreach ($allowedSettings as $key) {
                // Skip checkbox fields (already handled)
                if (in_array($key, $checkboxFields)) {
                    continue;
                }
                
                if (isset($input[$key])) {
                    $value = $input[$key];
                    // Convert to string, but preserve JSON strings
                    if (is_array($value)) {
                        $value = json_encode($value);
                    } elseif (is_bool($value)) {
                        $value = $value ? '1' : '0';
                    } elseif (is_null($value)) {
                        $value = '';
                    }
                    $settingsToUpdate[$key] = (string)$value;
                }
            }
            
            // Ensure working_hours_days is always included if working_hours_enabled is set
            if (isset($settingsToUpdate['working_hours_enabled']) && !isset($settingsToUpdate['working_hours_days'])) {
                // Centralized default schedule from SystemSettingsService
                $settingsToUpdate['working_hours_days'] = json_encode(
                    $settingsService->getDefaultWorkingHoursDays()
                );
            }

            if (empty($settingsToUpdate)) {
                header('Content-Type: application/json');
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Gecersiz ayar verisi', 'received' => array_keys($input ?? [])]);
                return;
            }

            // Log what we're trying to save
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::info('BusinessAdminController::updateBusinessSettings - Saving', [
                    'settings' => array_keys($settingsToUpdate),
                    'customer_id' => $customerId
                ]);
            }

            $result = $settingsService->updateSettings($settingsToUpdate);

            if ($result) {
                header('Content-Type: application/json');
                http_response_code(200);
                echo json_encode(['success' => true, 'message' => 'Ayarlar guncellendi', 'updated' => array_keys($settingsToUpdate)]);
            } else {
                header('Content-Type: application/json');
                http_response_code(500);
                $lastError = \App\Services\SystemSettingsService::getLastUpdateError();
                $repoError = \App\Repositories\SystemSettingsRepository::$lastSetValueError ?? '';
                // Always log server-side with full detail
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::error('BusinessAdminController::updateBusinessSettings - persistence failed', [
                        'attempted_keys' => array_keys($settingsToUpdate),
                        'service_error' => $lastError,
                        'repo_error' => $repoError,
                        'customer_id' => $customerId,
                    ]);
                }
                // Only expose internal error details when debug mode is on
                $payload = [
                    'success' => false,
                    'message' => 'Ayarlar guncellenemedi. Lutfen tekrar deneyin.',
                    'attempted_keys' => array_keys($settingsToUpdate),
                ];
                if ($settingsService->getAppDebug()) {
                    $payload['debug'] = $lastError ?: $repoError;
                }
                echo json_encode($payload);
            }
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('BusinessAdminController::updateBusinessSettings error', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'input' => $input ?? null
                ]);
            }
            header('Content-Type: application/json');
            http_response_code(500);
            $debugOn = false;
            try {
                $debugOn = \App\Core\DependencyFactory::getSystemSettingsService()->getAppDebug();
            } catch (\Throwable $ignored) {
                \App\Core\Logger::warning('BusinessAdminController: getAppDebug failed', [
                    'error' => $ignored->getMessage(),
                ]);
            }
            $message = $debugOn
                ? ('Hata: ' . $e->getMessage())
                : 'Ayarlar guncellenirken bir hata olustu. Lutfen tekrar deneyin.';
            echo json_encode(['success' => false, 'message' => $message]);
            return;
        }
    }

    /**
     * Analysis section (business-specific only)
     */
    public function analysis() {
        try {
            $customerId = $_SESSION['customer_id'] ?? null;

            if (!$customerId) {
                $this->toastNotificationService->setFlash('dashboard_error', 'İşletme bilgisi bulunamadı');
                // CRITICAL: Use current host (with subdomain) for redirect
                $currentHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                header('Location: ' . $protocol . '://' . $currentHost . '/login');
                exit;
            }

            // Get analysis data for this business (tenant-scoped via ReportsRepository)
            $reportService = \App\Core\DependencyFactory::getReportsService();
            $range = $reportService->getTimeRangeData('this_year');
            $startDate = $range['start'];
            $endDate = $range['end'];

            $salesReport = $reportService->getSalesReport($startDate, $endDate);
            $customerReport = $reportService->getCustomerReport($startDate, $endDate);

            $businessAnalytics = [
                'total_orders' => (int) ($salesReport['total_orders'] ?? 0),
                'total_revenue' => (float) ($salesReport['total_revenue'] ?? 0),
                'avg_order_value' => (float) ($salesReport['avg_order_value'] ?? 0),
                'customer_count' => (int) ($customerReport['unique_customers'] ?? 0),
            ];

            // No persisted report archive for business admin; list stays empty until export history exists.
            $reports = [];

            $data = [
                'business_analytics' => $businessAnalytics,
                'reports' => $reports,
                'page' => 'business-admin-analysis'
            ];

            $this->view('business_admin/analysis', $data);

        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('Business Admin Analysis error', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }

            $this->toastNotificationService->setFlash('dashboard_error', 'Analiz bilgileri alınırken hata oluştu');
            header('Location: ' . BASE_URL . '/business/dashboard');
            exit;
        }
    }

    /**
     * Check if user is business admin
     * Includes BUSINESS_OWNER, BUSINESS_MANAGER, BUSINESS_ADMIN, MANAGER roles
     */
    private function isBusinessAdmin(): bool {
        $role = $this->getCurrentRole();
        if (!$role) {
            return false;
        }

        $normalizedRole = strtoupper(trim($role));

        // Check direct role matches
        $businessRoles = [
            'BUSINESS_OWNER', 'BUSINESS_MANAGER', 'BUSINESS_ADMIN', 'MANAGER', 'TRIAL',
            'ROLE_BUSINESS_OWNER', 'ROLE_BUSINESS_MANAGER', 'ROLE_BUSINESS_ADMIN', 'ROLE_MANAGER', 'ROLE_TRIAL'
        ];

        if (in_array($normalizedRole, $businessRoles)) {
            return true;
        }

        // Remove ROLE_ prefix and check again
        $roleCode = str_replace('ROLE_', '', $normalizedRole);
        $businessRoleCodes = ['BUSINESS_OWNER', 'BUSINESS_MANAGER', 'BUSINESS_ADMIN', 'MANAGER'];

        if (in_array($roleCode, $businessRoleCodes)) {
            return true;
        }

        // Also check via Authorization::hasRole for compatibility
        if ($this->auth && method_exists($this->auth, 'hasRole')) {
            if ($this->auth->hasRole('BUSINESS_MANAGER') ||
                $this->auth->hasRole('MANAGER') ||
                $this->auth->hasRole('BUSINESS_OWNER')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Staff login with PIN for subdomain access
     */
    public function staffLogin() {
        // Allow access without requiring business admin login
        // This method will be accessed via subdomain

        try {
            $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

            $pin = $input['pin'] ?? '';
            $email = $input['email'] ?? '';

            if (empty($pin) || empty($email)) {
                return $this->jsonResponse([
                    'success' => false,
                    'message' => 'PIN ve e-posta bilgileri eksik'
                ], 400);
            }

            // Get user by email
            $userService = \App\Core\DependencyFactory::getUserService();
            $user = $userService->findByEmail($email);

            if (!$user) {
                return $this->jsonResponse([
                    'success' => false,
                    'message' => 'Kullanıcı bulunamadı'
                ], 400);
            }

            // Verify PIN
            $verified = $userService->verifyPin($user['user_id'], $pin);

            if (!$verified) {
                return $this->jsonResponse([
                    'success' => false,
                    'message' => 'Geçersiz PIN'
                ], 400);
            }

            // Check if user belongs to the current business (based on subdomain)
            $currentSubdomain = $this->getCurrentSubdomain();
            $customerService = \App\Core\DependencyFactory::getCustomerService();
            $customer = $customerService->getBySubdomain($currentSubdomain);

            if (!$customer) {
                return $this->jsonResponse([
                    'success' => false,
                    'message' => 'İşletme bulunamadı'
                ], 400);
            }

            if ($user['tenant_id'] !== $customer['customer_id']) {
                return $this->jsonResponse([
                    'success' => false,
                    'message' => 'Bu kullanıcı bu işleteye ait değil'
                ], 400);
            }

            // Set session for the user
            \App\Core\SessionManager::ensureSession();
            \App\Core\SessionManager::set('user_id', $user['user_id']);
            \App\Core\SessionManager::set('customer_id', $customer['customer_id']);
            \App\Core\SessionManager::set('role', $user['role']);
            \App\Core\SessionManager::set('role_id', $user['role_id'] ?? null);
            \App\Core\SessionManager::set('first_name', $user['first_name'] ?? '');
            \App\Core\SessionManager::set('last_name', $user['last_name'] ?? '');
            \App\Core\SessionManager::set('is_super_admin', false);
            \App\Core\SessionManager::set('logged_in', true);

            session_write_close();

            return $this->jsonResponse([
                'success' => true,
                'message' => 'Giriş başarılı',
                'redirect_url' => BASE_URL . '/business/dashboard'
            ]);

        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('Staff login error', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }

            return $this->jsonResponse([
                'success' => false,
                'message' => 'Giriş yapılırken bir hata oluştu'
            ], 500);
        }
    }

    /**
     * Get current subdomain from request
     */
    private function getCurrentSubdomain(): ?string {
        $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
        $hostParts = explode('.', $host);

        // Assuming format is subdomain.domain.com
        if (count($hostParts) >= 3) {
            return $hostParts[0];
        }

        return null;
    }

    /** Alias: updateSettings → updateBusinessSettings */
    public function updateSettings() {
        return $this->updateBusinessSettings();
    }

    /**
     * Staff management page for business managers
     */
    public function staff() {
        header('Location: ' . BASE_URL . '/business/users');
        exit;
    }

    /**
     * Roles & permissions page for business managers
     */
    public function roles() {
        header('Location: ' . BASE_URL . '/business/roles-permissions');
        exit;
    }

    /**
     * Receipts / Fiş Listesi page
     */
    public function receipts() {
        try {
            $this->ensureTenantContext();
            $this->view('admin/receipts', ['page' => 'receipts']);
        } catch (\Exception $e) {
            \App\Core\Logger::error('BusinessAdmin::receipts error: ' . $e->getMessage());
            header('Location: ' . BASE_URL . '/business/dashboard');
            exit;
        }
    }

    /**
     * Order approvals page
     */
    public function orderApprovals() {
        try {
            $this->ensureTenantContext();
            $this->view('admin/order_approvals', ['page' => 'order-approvals']);
        } catch (\Exception $e) {
            \App\Core\Logger::error('BusinessAdmin::orderApprovals error: ' . $e->getMessage());
            header('Location: ' . BASE_URL . '/business/dashboard');
            exit;
        }
    }

    /**
     * Order approval history page
     */
    public function orderApprovalHistory() {
        try {
            $this->ensureTenantContext();
            $this->view('admin/order_approval_history', ['page' => 'order-approval-history']);
        } catch (\Exception $e) {
            \App\Core\Logger::error('BusinessAdmin::orderApprovalHistory error: ' . $e->getMessage());
            header('Location: ' . BASE_URL . '/business/dashboard');
            exit;
        }
    }
}

