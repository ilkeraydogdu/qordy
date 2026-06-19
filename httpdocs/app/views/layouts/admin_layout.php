<!DOCTYPE html>
<?php
// Set flag to indicate we're using admin layout (prevents header.php from showing navigation bar)
// Use only global variable - no constant to avoid redefinition errors
$GLOBALS['using_admin_layout'] = true;
if (!headers_sent()) {
    header('X-Robots-Tag: noindex, nofollow', true);
}
?>
<html lang="<?php
    // HelperLoader already loaded helpers, including functions.php
    $translationService = getTranslationService();
    echo $translationService->getCurrentLanguage();
?>">
<head>
    <?php require_once __DIR__ . '/../partials/google_analytics.php'; ?>
    <meta charset="UTF-8">
    <?php
    // Load SEO Service
    $seoService = getSEOService();
    $page = $page ?? 'dashboard';
    // Merge seoParams from data with defaults
    $seoParams = array_merge(['id' => $id ?? null], $seoParams ?? []);
    echo $seoService->generateMetaTags($page, $seoParams);
    echo $seoService->generateStructuredData($page, $seoParams);
    ?>

    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <?php
    // CSRF Token for JavaScript
    require_once __DIR__ . '/../../core/Security/CSRFManager.php';
    $csrfToken = \App\Core\Security\CSRFManager::generateToken();
    ?>
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
    <?php
    // WebSocket URL: Use /ws proxy (same as mobile) - must be set before realtime.js
    //
    // IMPORTANT: The /ws reverse-proxy is configured ONLY on the apex host
    // (qordy.com / www.qordy.com). Tenant subdomains (foo.qordy.com) are
    // static vhosts that don't proxy /ws, so
    //   wss://foo.qordy.com/ws
    // fails with a silent "WebSocket connection failed" every time the
    // business owner is on their own subdomain. Force the WS URL onto the
    // apex whenever we're on a *.qordy.com subdomain so realtime keeps
    // working for every tenant.
    $wsBase = (defined('BASE_URL') && BASE_URL) ? rtrim(BASE_URL, '/') : '';
    $wsProtocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'wss' : 'ws';
    $wsHost = $wsBase ? preg_replace('#^https?://#', '', $wsBase) : ($_SERVER['HTTP_HOST'] ?? 'localhost');
    // Strip :port if present, keep host only.
    $wsHost = preg_replace('#:\d+$#', '', $wsHost);
    // Canonicalise to apex for tenant subdomains using the centralized
    // UrlService so the apex host is not hardcoded to qordy.com.
    try {
        $urlService = \App\Core\DependencyFactory::getUrlService();
        $apexHost = $urlService ? strtolower($urlService->getApexDomain()) : '';
    } catch (\Throwable $e) {
        $apexHost = '';
    }
    if ($apexHost !== '') {
        $hostLower = strtolower($wsHost);
        $wwwApex = 'www.' . $apexHost;
        if ($hostLower !== $apexHost && $hostLower !== $wwwApex
            && substr($hostLower, -strlen('.' . $apexHost)) === '.' . $apexHost) {
            $wsHost = $apexHost;
        }
    }
    $websocketUrl = $wsProtocol . '://' . $wsHost . '/ws';
    $websocketPort = 8080;
    try {
        $settingsService = \App\Core\DependencyFactory::getSystemSettingsService();
        $websocketPort = $settingsService->getWebsocketPort();
    } catch (\Exception $e) {
        $websocketPort = isset($_ENV['WEBSOCKET_PORT']) ? (int)$_ENV['WEBSOCKET_PORT'] : 8080;
    }
    ?>
    
    <!-- OTOMATIK CACHE TEMİZLEYİCİ - HER ZAMAN İLK SIRA -->
    <script src="<?php echo BASE_URL; ?>/auto-cache-killer.js"></script>
    
    <script>
        window.CSRF_TOKEN = '<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>';
        <?php
        $bid = \App\Core\TenantResolver::resolve();
        if (empty($bid) && class_exists('\App\Core\TenantContext')) {
            $bid = \App\Core\TenantContext::getId();
        }
        if (!empty($bid)) {
            echo "window.BUSINESS_ID = " . json_encode((string)$bid) . ";";

            // WS AUTH token: sunucu tarafında HMAC ile imzalı, 15 dk ömürlü
            // kısa token üretiyoruz. realtime.js bunu AUTH mesajında
            // gönderir. Client-supplied business_id ile cross-tenant
            // kanallara bağlanma girişimlerine karşı ana savunma.
            try {
                $wsUserId = $_SESSION['user_id'] ?? null;
                $wsToken = \App\Services\WebSocketTokenService::mint(
                    (string)$bid,
                    $wsUserId !== null ? (string)$wsUserId : null,
                    900
                );
                if ($wsToken !== null) {
                    echo "window.WEBSOCKET_AUTH_TOKEN = " . json_encode($wsToken) . ";";
                }
            } catch (\Throwable $e) {
                // Token üretilemezse sessiz düş - realtime.js legacy AUTH'a
                // fallback yapar.
            }
        }
        // WebSocket URL (proxy /ws) - must be set before realtime.js loads
        echo "window.WEBSOCKET_URL = " . json_encode($websocketUrl ?? '') . ";";
        ?>

    </script>
    <?php
    // Try to load favicon and logo from settings
    $faviconUrl = BASE_URL . '/assets/images/favicon.png?v=' . time();
    $logoUrl = getQordyLogoUrl() . '?v=' . time();
    try {
        $settingsService = \App\Core\DependencyFactory::getSystemSettingsService();
        if ($settingsService !== null) {
            $faviconUrlFromDb = $settingsService->getSetting('favicon_url');
            if ($faviconUrlFromDb) {
                $faviconUrl = $faviconUrlFromDb . '?v=' . time();
            }
        }
    } catch (\Exception $e) {
        // Silent fail - use defaults
        if (class_exists('\App\Core\Logger')) {
            \App\Core\Logger::error('Failed to load settings for favicon/logo: ' . $e->getMessage());
        } else {
            error_log('Failed to load settings for favicon/logo: ' . $e->getMessage());
        }
    } catch (\Throwable $e) {
        // Silent fail - use defaults
        if (class_exists('\App\Core\Logger')) {
            \App\Core\Logger::error('Failed to load settings for favicon/logo: ' . $e->getMessage());
        } else {
            error_log('Failed to load settings for favicon/logo: ' . $e->getMessage());
        }
    }
    ?>
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="<?php echo htmlspecialchars($faviconUrl); ?>">
    <link rel="icon" type="image/png" href="<?php echo htmlspecialchars($faviconUrl); ?>">
    <link rel="apple-touch-icon" href="<?php echo htmlspecialchars($faviconUrl); ?>">
    <link rel="manifest" href="<?php echo BASE_URL; ?>/manifest.json?v=<?php echo time(); ?>">

    <!-- Admin Layout Config JS (MUST load before Tailwind to suppress warnings) -->
    <script src="<?php echo BASE_URL; ?>/assets/js/admin/admin-layout-config.js"></script>

    <?php echo getAssetManager()->getTailwindCssScript(); ?>
    <?php echo getAssetManager()->getGoogleFontsLink(); ?>

    <!-- Qordy Design System (centralized): tokens -> legacy admin-layout -> component layer -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/tokens.css?v=<?php echo @filemtime(dirname(__DIR__,3)."/public/assets/css/tokens.css"); ?>">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/admin-layout.css?v=<?php echo @filemtime(dirname(__DIR__,3).'/public/assets/css/admin-layout.css'); ?>">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/admin-components.css?v=<?php echo @filemtime(dirname(__DIR__,3)."/public/assets/css/admin-components.css"); ?>">
    <?php
    // Business theme — admin_layout is exclusively the unified q-biz panel shell
    $__needsBizTheme = true;
    echo '<link rel="stylesheet" href="' . BASE_URL . '/assets/css/business-theme.css?v=' . @filemtime(dirname(__DIR__,3) . '/public/assets/css/business-theme.css') . '">';
    ?>
    <script src="<?php echo BASE_URL; ?>/assets/js/realtime.js"></script>
    <?php
    require_once __DIR__ . '/../../helpers/sounds.php';
    echo getSoundScript();
    ?>
    <script src="<?php echo BASE_URL; ?>/assets/js/csrf.js"></script>

    <?php
    // Check if this is a waiter/kitchen/pos view - these don't need admin navigation scripts
    $isWaiterView = isset($view) && (strpos($view, 'waiter/') === 0 || strpos($view, 'kitchen/') === 0 || strpos($view, 'pos/') === 0 || strpos($view, 'cashier/') === 0 || strpos($view, 'preparation-screen/') === 0);
    ?>
    
    <!-- Mobile nav must load on all panel routes (including POS/waiter embeds) -->
    <script src="<?php echo BASE_URL; ?>/assets/js/admin/admin-mobile-menu.js?v=<?php echo (int)@filemtime(dirname(__DIR__, 3) . '/public/assets/js/admin/admin-mobile-menu.js'); ?>" onerror="window.adminMobileMenuLoaded = false; console.error('Failed to load admin-mobile-menu.js');"></script>
    <?php if ($isWaiterView): ?>
    <script src="<?php echo BASE_URL; ?>/assets/js/sidebar.js?v=<?php echo (int)@filemtime(dirname(__DIR__, 3) . '/public/assets/js/sidebar.js'); ?>"></script>
    <?php endif; ?>

    <?php if (!$isWaiterView): ?>
    <!-- Load modules asynchronously to prevent blocking -->
    <script type="module" defer>
        // Load config module first
        import('<?php echo BASE_URL; ?>/assets/js/modules/config.js').then(function(module) {
            window.appConfig = module.config;
        }).catch(function(error) {
            console.warn('Config module failed to load:', error);
            if (typeof window.BASE_URL !== 'undefined') {
                window.appConfig = { getBaseUrl: function() { return window.BASE_URL; } };
            }
        });

        // Load error handler module with error handling
        import('<?php echo BASE_URL; ?>/assets/js/modules/error-handler.js').catch(function(error) {
            console.warn('Error handler module failed to load:', error);
        });
    </script>

    <!-- Sidebar visibility will be handled by admin-navigation.js -->
    <script src="<?php echo BASE_URL; ?>/assets/js/admin/admin-navigation.js" onerror="window.adminNavigationLoaded = false; console.error('Failed to load admin-navigation.js');"></script>
    <?php endif; ?>

    <!-- Admin Layout Init JS -->
    <script src="<?php echo BASE_URL; ?>/assets/js/admin/admin-layout-init.js" defer></script>
    <?php if (!empty($__needsBizTheme)): ?>
    <script src="<?php echo BASE_URL; ?>/assets/js/admin/panel-sidebar.js?v=<?php echo @filemtime(dirname(__DIR__, 3) . '/public/assets/js/admin/panel-sidebar.js'); ?>" defer></script>
    <?php endif; ?>

    <!-- Load core JavaScript files - utils.js MUST load first without defer -->
    <script src="<?php echo BASE_URL; ?>/assets/js/utils.js"></script>
    <script src="<?php echo BASE_URL; ?>/assets/js/csrf-helper.js" defer></script>
    <script src="<?php echo BASE_URL; ?>/assets/js/notification.js" defer></script>
    <script src="<?php echo BASE_URL; ?>/assets/js/notifications.js" defer></script>
    <script src="<?php echo BASE_URL; ?>/assets/js/api.js" defer></script>
    <script src="<?php echo BASE_URL; ?>/assets/js/cart.js" defer></script>
    <script src="<?php echo BASE_URL; ?>/assets/js/main.js" defer></script>
    <style>
    @media print {
        nav, .sidebar, #sidebar, [data-sidebar], .no-print,
        header:not(.print-header), .mobile-nav, #mobile-nav,
        button:not(.print-btn), .notification-bell { display: none !important; }
        main, .main-content, #main-content { margin-left: 0 !important; padding: 10px !important; width: 100% !important; max-width: 100% !important; }
        body { background: #fff !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
        .print-only { display: block !important; }
    }
    </style>
</head>
    <?php
    // SessionManager and HelperLoader are already loaded by Controller
    // translations.php is already loaded by HelperLoader
    \App\Core\SessionManager::ensureSession();
    \App\Core\HelperLoader::ensureLoaded();
    require_once __DIR__ . '/../../helpers/navigation_renderer.php';
    $currentUser = getCurrentUser() ?? ['username' => t('common.user'), 'role' => ''];
    // Role is already normalized by getCurrentUser(), but ensure it is normalized
    $rawRole = $currentUser['role'] ?? null;
    if ($rawRole) {
        // RoleMapper is loaded on-demand
        if (!class_exists('\App\Services\RoleMapper')) {
            require_once __DIR__ . '/../../services/RoleMapper.php';
        }
        $roleMapper = \App\Services\RoleMapper::getInstance();
        $role = $roleMapper->normalizeRole($rawRole);
        // Update currentUser array with normalized role
        $currentUser['role'] = $role;
    } else {
        $role = null;
    }

    // Get current role codes for restricted role check (needed before body tag)
    $currentRoleId = $_SESSION['role_id'] ?? null;
    $currentRoleCode = $_SESSION['role'] ?? $currentUser['role'] ?? null;

    // Initialize restricted role flags
    $isKitchenRole = false;
    $isCashierRole = false;
    $isWaiterRole = false;
    $isManagerRole = false;
    $isRestrictedRole = false;

    // Determine roles from role_code or role_id (early check for body class)
    $normalizedRoleCode = null;

    // First, try to get role from session role_code
    if ($currentRoleCode) {
        $normalizedRoleCode = strtoupper(str_replace('ROLE_', '', trim($currentRoleCode)));
        $isKitchenRole = ($normalizedRoleCode === 'KITCHEN');
        $isCashierRole = ($normalizedRoleCode === 'CASHIER');
        $isWaiterRole = ($normalizedRoleCode === 'WAITER');
        $isManagerRole = ($normalizedRoleCode === 'MANAGER');
    }

    // If not found, try to get from role_id using RoleMapper
    if (!$normalizedRoleCode && $currentRoleId) {
        try {
            if (!class_exists('\App\Services\RoleMapper')) {
                require_once __DIR__ . '/../../services/RoleMapper.php';
            }
            $roleMapper = \App\Services\RoleMapper::getInstance();
            $roleCodeFromId = $roleMapper->getRoleCode($currentRoleId);
            if ($roleCodeFromId) {
                $normalizedRoleCode = strtoupper(str_replace('ROLE_', '', trim($roleCodeFromId)));
                $isKitchenRole = ($normalizedRoleCode === 'KITCHEN');
                $isCashierRole = ($normalizedRoleCode === 'CASHIER');
                $isWaiterRole = ($normalizedRoleCode === 'WAITER');
                $isManagerRole = ($normalizedRoleCode === 'MANAGER');
            }
        } catch (\Exception $e) {
            // Silent fail
        }
    }

    // Also check from currentUser array if still not determined
    if (!$normalizedRoleCode && isset($currentUser['role']) && !empty($currentUser['role'])) {
        $normalizedRoleCode = strtoupper(str_replace('ROLE_', '', trim($currentUser['role'])));
        $isKitchenRole = ($normalizedRoleCode === 'KITCHEN');
        $isCashierRole = ($normalizedRoleCode === 'CASHIER');
        $isWaiterRole = ($normalizedRoleCode === 'WAITER');
        $isManagerRole = ($normalizedRoleCode === 'MANAGER');
    }

    // Additional check: Try to get role from user_id if available
    if (!$normalizedRoleCode && isset($_SESSION['user_id'])) {
        try {
            if (!class_exists('\App\Models\User')) {
                require_once __DIR__ . '/../../models/User.php';
            }
            $userModel = new \App\Models\User();
            $user = $userModel->findByUserId($_SESSION['user_id']);
            if ($user && isset($user['role_id'])) {
                if (!class_exists('\App\Services\RoleMapper')) {
                    require_once __DIR__ . '/../../services/RoleMapper.php';
                }
                $roleMapper = \App\Services\RoleMapper::getInstance();
                $roleCodeFromUserId = $roleMapper->getRoleCode($user['role_id']);
                if ($roleCodeFromUserId) {
                    $normalizedRoleCode = strtoupper(str_replace('ROLE_', '', trim($roleCodeFromUserId)));
                    $isKitchenRole = ($normalizedRoleCode === 'KITCHEN');
                    $isCashierRole = ($normalizedRoleCode === 'CASHIER');
                    $isWaiterRole = ($normalizedRoleCode === 'WAITER');
                    $isManagerRole = ($normalizedRoleCode === 'MANAGER');
                }
            }
        } catch (\Exception $e) {
            // Silent fail
        }
    }

    // Manager role should NOT be restricted (can see sidebar and menu)
    // Note: We only check actual role, not permissions, to avoid false positives
    // (e.g., kitchen role might have orders.view permission but should still be restricted)

    // Final check: If role_id is ROLE_MANAGER, set isManagerRole to true
    if (!$isManagerRole && $currentRoleId) {
        try {
            if (!class_exists('\App\Services\RoleMapper')) {
                require_once __DIR__ . '/../../services/RoleMapper.php';
            }
            $roleMapper = \App\Services\RoleMapper::getInstance();
            $roleCodeCheck = $roleMapper->getRoleCode($currentRoleId);
            if ($roleCodeCheck) {
                $normalizedCheck = strtoupper(str_replace('ROLE_', '', trim($roleCodeCheck)));
                if ($normalizedCheck === 'MANAGER') {
                    $isManagerRole = true;
                    $normalizedRoleCode = $normalizedCheck;
                }
            }
        } catch (\Exception $e) {
            // Silent fail
        }
    }

    // Additional aggressive check: Check if role_id matches ROLE_MANAGER, ROLE_SUPER_ADMIN, or ROLE_QODMIN constants
    if (!$isManagerRole && $currentRoleId) {
        // Check if role_id is 'ROLE_MANAGER', 'ROLE_SUPER_ADMIN', or 'ROLE_QODMIN' directly
        $currentRoleIdUpper = strtoupper(trim($currentRoleId));
        if ($currentRoleIdUpper === 'ROLE_MANAGER' ||
            $currentRoleIdUpper === 'MANAGER' ||
            $currentRoleIdUpper === 'ROLE_SUPER_ADMIN' ||
            $currentRoleIdUpper === 'SUPER_ADMIN' ||
            $currentRoleIdUpper === 'ROLE_QODMIN' ||
            $currentRoleIdUpper === 'QODMIN') {
            $isManagerRole = true;
            $normalizedRoleCode = str_replace('ROLE_', '', $currentRoleIdUpper);
        }
    }

    // Check if role_code contains 'MANAGER' in any form
    if (!$isManagerRole) {
        $allRoleSources = [
            $currentRoleCode,
            $currentUser['role'] ?? null,
            $_SESSION['role'] ?? null
        ];
        foreach ($allRoleSources as $roleSource) {
            if ($roleSource && stripos($roleSource, 'MANAGER') !== false) {
                $isManagerRole = true;
                $normalizedRoleCode = 'MANAGER';
                break;
            }
        }
    }

    // Check if user is a "business tenant" user — i.e. the person who
    // owns / operates a specific tenant. We unify BUSINESS_MANAGER,
    // BUSINESS_OWNER and TRIAL here because registration defaults to
    // BUSINESS_OWNER (or TRIAL while a trial subscription is active)
    // and downstream UI (trial banner, "paket satın al" gating, sidebar
    // logo, subscription check) must treat all three the same way.
    // Without this, a freshly-registered owner would see "Paket Satın
    // Al" and no logo even though their trial sub is live in DB.
    $isBusinessManagerRole = false;
    $businessTenantMarkers = ['BUSINESS_MANAGER', 'BUSINESS_OWNER', 'ROLE_TRIAL'];
    $allRoleSources = [
        $currentRoleCode,
        $currentUser['role'] ?? null,
        $_SESSION['role'] ?? null
    ];
    foreach ($allRoleSources as $roleSource) {
        if (!$roleSource) continue;
        $u = strtoupper(trim((string)$roleSource));
        foreach ($businessTenantMarkers as $marker) {
            if (stripos($u, $marker) !== false) {
                $isBusinessManagerRole = true;
                break 2;
            }
        }
        // Bare "TRIAL" token (without ROLE_ prefix) is a valid business
        // tenant user too — catch it explicitly so `stripos` can't
        // accidentally match it against an unrelated role string.
        if ($u === 'TRIAL') {
            $isBusinessManagerRole = true;
            break;
        }
    }

    // Check if user is SUPER_ADMIN or QODMIN
    $isSuperAdminRole = false;
    if (!$isSuperAdminRole) {
        $allRoleSources = [
            $currentRoleCode,
            $currentUser['role'] ?? null,
            $_SESSION['role'] ?? null,
            $currentRoleId
        ];
        foreach ($allRoleSources as $roleSource) {
            if ($roleSource && (stripos($roleSource, 'SUPER_ADMIN') !== false || stripos($roleSource, 'QODMIN') !== false)) {
                $isSuperAdminRole = true;
                break;
            }
        }
    }

    // ------------------------------------------------------------------ //
    //  Trial-specific data (any business manager with a trial sub)       //
    // ------------------------------------------------------------------ //
    $isTrialRole = false;
    foreach ([$currentRoleCode, $currentUser['role'] ?? null, $_SESSION['role'] ?? null] as $_rs) {
        if ($_rs && (strtoupper(trim($_rs)) === 'TRIAL' || strtoupper(trim($_rs)) === 'ROLE_TRIAL')) {
            $isTrialRole = true; break;
        }
    }

    // Trial status values (used for banner + blocking)
    $trialInfo = [
        'is_trial'           => false,
        'trial_ends_at'      => null,
        'trial_end_ts'       => null,
        'days_left'          => null,   // null = not trial
        'is_expired'         => false,
        'grace_days_left'    => null,   // days left in 7-day grace period
        'fully_blocked'      => false,  // grace period also over
    ];

    // Show trial banner for ANY business manager whose active subscription is a trial.
    // This covers both TRIAL-role users AND BUSINESS_MANAGER users whose session
    // may not yet reflect the role change (e.g. before re-login).
    if ($isBusinessManagerRole && !$isSuperAdminRole) {
        try {
            $_trialCustomerId = $_SESSION['customer_id'] ?? null;
            if ($_trialCustomerId) {
                $_trialSubSvc = \App\Core\DependencyFactory::getSubscriptionService();
                $_trialSub = $_trialSubSvc->getCustomerSubscription($_trialCustomerId);
                if ($_trialSub && !empty($_trialSub['is_trial'])) {
                    $_endsAt = $_trialSub['trial_ends_at'] ?? $_trialSub['trial_end'] ?? $_trialSub['current_period_end'] ?? null;
                    $_endTs  = $_endsAt ? strtotime($_endsAt) : null;
                    $_now    = time();
                    $_daysLeft = $_endTs ? max(0, (int) ceil(($_endTs - $_now) / 86400)) : null;
                    $_isExpired = $_endTs && $_endTs < $_now;
                    $_graceEndTs = $_endTs ? ($_endTs + 7 * 86400) : null;
                    $_graceDaysLeft = $_graceEndTs ? max(0, (int) ceil(($_graceEndTs - $_now) / 86400)) : null;
                    $_fullyBlocked = $_isExpired && $_graceEndTs && $_now > $_graceEndTs;
                    $trialInfo = [
                        'is_trial'        => true,
                        'trial_ends_at'   => $_endsAt,
                        'trial_end_ts'    => $_endTs,
                        'days_left'       => $_daysLeft,
                        'is_expired'      => $_isExpired,
                        'grace_days_left' => $_graceDaysLeft,
                        'fully_blocked'   => $_fullyBlocked,
                    ];
                    // Ensure $isTrialRole reflects trial subscription even if session role is stale
                    $isTrialRole = true;
                }
            }
        } catch (\Exception $_e) { /* graceful degradation */ }
    }

    // Check if BUSINESS_MANAGER (or TRIAL with active trial) has active subscription
    // Reuse $trialInfo data already fetched above to avoid a second DB query.
    $hasActiveSubscription = false;
    if ($isBusinessManagerRole && !$isSuperAdminRole) {
        if ($trialInfo['is_trial']) {
            // Trial sub exists — active only if trial has NOT expired
            $hasActiveSubscription = !$trialInfo['is_expired'];
        } else {
            // Non-trial sub: check subscription status directly
            try {
                $bmCustomerId = $_SESSION['customer_id'] ?? null;
                if ($bmCustomerId) {
                    $bmSubService = \App\Core\DependencyFactory::getSubscriptionService();
                    $bmSub = $bmSubService->getCustomerSubscription($bmCustomerId);
                    if ($bmSub && !empty($bmSub['status']) && strtoupper($bmSub['status']) === 'ACTIVE') {
                        $hasActiveSubscription = true;
                    }
                }
            } catch (\Exception $e) { /* graceful degradation */ }
        }
    } elseif ($isSuperAdminRole || $isManagerRole) {
        $hasActiveSubscription = true;
    }

    // Determine active tab from URL - always parse URI first (needed for redirect checks)
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    // Use $_GET directly instead of RequestParser to avoid class loading issues
    $activeTab = $_GET['tab'] ?? null;

    // Parse URL to determine active tab (always parse, needed for redirect checks)
    $uri = function_exists('normalizeAppRequestPath')
        ? normalizeAppRequestPath($requestUri)
        : (parse_url($requestUri, PHP_URL_PATH) ?: '/');

    // For SUPER_ADMIN, always show sidebar regardless of page
    if ($isSuperAdminRole) {
        $isRestrictedRole = false;
    } else {
        // Restricted roles: ALL roles except MANAGER, BUSINESS_MANAGER, and SUPER_ADMIN (these roles show sidebar, all others see full-screen)
        // Manager, Business Manager and Super Admin roles can access admin pages with sidebar visible
        // Force isRestrictedRole to false if Manager, Business Manager or Super Admin role is detected
        if ($isManagerRole || $isBusinessManagerRole) {
            $isRestrictedRole = false;
        } else {
            // All non-manager roles are restricted (no menubar, full-screen view)
            $isRestrictedRole = true;
        }
    }

    // Additional URL-based check: Force restricted role for non-manager pages
    // This is a fallback in case role detection fails - if user is on waiter/kitchen/cashier pages, they must be restricted
    // BUT: SUPER_ADMIN should always have access to sidebar regardless of page
    // Staff-only fullscreen on ops routes — business owners/managers keep panel + mobile nav
    if (!$isSuperAdminRole && !$isManagerRole && !$isBusinessManagerRole && isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
        $uriLower = strtolower($uri);
        $restrictedPages = ['/business/waiter', '/business/kitchen', '/business/pos', '/business/preparation-screen', '/waiter', '/kitchen', '/pos', '/preparation-screen'];
        foreach ($restrictedPages as $restrictedPage) {
            if (strpos($uriLower, $restrictedPage) === 0) {
                $isRestrictedRole = true;
                break;
            }
        }
    }

    // SUPER_ADMIN should always have sidebar visible regardless of page
    if ($isSuperAdminRole) {
        $isRestrictedRole = false;
    }

    // Debug: Log role detection for troubleshooting (always log for waiter and kitchen pages)
    $shouldLogDebug = (defined('DEBUG_ROLE_DETECTION') && DEBUG_ROLE_DETECTION) ||
                      strpos(strtolower($uri), '/waiter') === 0 ||
                      strpos(strtolower($uri), '/kitchen') === 0;
    if ($shouldLogDebug) {
        error_log("Role Detection Debug: isManagerRole=" . ($isManagerRole ? 'true' : 'false') .
                 ", isRestrictedRole=" . ($isRestrictedRole ? 'true' : 'false') .
                 ", isWaiterRole=" . ($isWaiterRole ? 'true' : 'false') .
                 ", isKitchenRole=" . ($isKitchenRole ? 'true' : 'false') .
                 ", isCashierRole=" . ($isCashierRole ? 'true' : 'false') .
                 ", normalizedRoleCode=" . ($normalizedRoleCode ?? 'null') .
                 ", currentRoleId=" . ($currentRoleId ?? 'null') .
                 ", currentRoleCode=" . ($currentRoleCode ?? 'null') .
                 ", userRole=" . ($currentUser['role'] ?? 'null') .
                 ", uri=" . ($uri ?? 'null'));
    }

    // Business owner/manager admin surface (sidebar + marketing-aligned chrome)
    $isQodminSurface = ((strpos($uri, '/qodmin/') === 0) || $uri === '/qodmin') && !$isRestrictedRole;
    $isBusinessOwnerSurface = !$isRestrictedRole && !$isQodminSurface && (
        strpos($uri, '/business/') === 0
        || preg_match('#^/(?:pos|waiter|kitchen|preparation-screen)(?:/|$)#', $uri)
    );
    // All non-restricted roles use the compact q-biz panel chrome (never legacy sidebar)
    $isPanelSurface = !$isRestrictedRole;
    // Ops routes (POS, waiter, kitchen, tables, prep screens) embedded in manager/qodmin shell
    $isStaffOpsRoute = false;
    if ($isPanelSurface && !$isRestrictedRole) {
        $opsRoutePrefixes = [
            '/business/pos',
            '/business/waiter',
            '/business/kitchen',
            '/pos',
            '/waiter',
            '/kitchen',
            '/preparation-screen',
            '/qodmin/pos',
            '/qodmin/waiter',
            '/qodmin/kitchen',
        ];
        foreach ($opsRoutePrefixes as $opsPrefix) {
            if ($uri === $opsPrefix || strpos($uri, $opsPrefix . '/') === 0) {
                $isStaffOpsRoute = true;
                break;
            }
        }
        if (!$isStaffOpsRoute) {
            if ($uri === '/business/tables' || $uri === '/qodmin/tables'
                || strpos($uri, '/business/tables/') === 0 || strpos($uri, '/qodmin/tables/') === 0) {
                $isStaffOpsRoute = true;
            } elseif (preg_match('#^/(?:business|qodmin)/preparation-screens/[^/]+$#', $uri)) {
                $isStaffOpsRoute = true;
            }
        }
    }
    $isOpsEmbedded = $isStaffOpsRoute;
    // Back-compat alias used in scroll wrapper / shell props
    $isStaffEmbedded = $isOpsEmbedded;
    $bizPanelTopbarHtml = '';
    $bizTopbarRangeLabel = '';
    $bizBusinessNumber = '';

    // İşletme kodu (business_number) — personel mobil girişi ve panel üst barı
    if (!empty($currentUser['customer_id'])) {
        try {
            $customerRepo = \App\Core\DependencyFactory::getCustomerRepository();
            $bizCustomerRow = $customerRepo->findById($currentUser['customer_id']);
            if ($bizCustomerRow && !empty($bizCustomerRow['business_number'])) {
                $bizBusinessNumber = trim((string) $bizCustomerRow['business_number']);
            }
        } catch (\Exception $e) {
            // Silent fail
        }
    }

    // If tab is not in GET params, determine from URL
    if ($activeTab === null) {

        // Map URLs to tab IDs (support both /qodmin and /business prefixes)
        $urlToTabMap = [
            '/qodmin/dashboard' => 'SUPER_ADMIN_DASHBOARD',
            '/qodmin' => 'SUPER_ADMIN_DASHBOARD',
            '/business/dashboard' => 'DASHBOARD',
            '/business' => 'DASHBOARD',
            '/pos/dashboard' => 'POS',
            '/pos' => 'POS',
            '/business/pos' => 'POS',
            '/business/pos/dashboard' => 'POS',
            '/waiter/dashboard' => 'WAITER',
            '/waiter/pos' => 'WAITER',
            '/waiter' => 'WAITER',
            '/business/waiter' => 'WAITER',
            '/business/waiter/dashboard' => 'WAITER',
            '/business/waiter/pos' => 'WAITER',
            '/kitchen/dashboard' => 'KITCHEN',
            '/kitchen' => 'KITCHEN',
            '/business/kitchen' => 'KITCHEN',
            '/business/kitchen/dashboard' => 'KITCHEN',
            '/qodmin/menu' => 'MENU',
            '/business/menu' => 'MENU',
            '/qodmin/orders' => 'ORDERS',
            '/business/orders' => 'ORDERS',
            '/qodmin/tables' => 'TABLES',
            '/business/tables' => 'TABLES',
            '/qodmin/reservations' => 'RESERVATIONS',
            '/business/reservations' => 'RESERVATIONS',
            '/qodmin/reservations/add' => 'RESERVATIONS',
            '/business/reservations/add' => 'RESERVATIONS',
            '/qodmin/inventory' => 'FINANCE_INVENTORY',
            '/business/inventory' => 'FINANCE_INVENTORY',
            '/qodmin/preparation-screens' => 'PREPARATION_SCREENS',
            '/business/preparation-screens' => 'PREPARATION_SCREENS',
            '/qodmin/preparation-screens/create' => 'PREPARATION_SCREENS',
            '/business/preparation-screens/create' => 'PREPARATION_SCREENS',
            '/qodmin/preparation-screens/edit' => 'PREPARATION_SCREENS',
            '/business/preparation-screens/edit' => 'PREPARATION_SCREENS',
            '/qodmin/finance' => 'FINANCE',
            '/business/finance' => 'FINANCE',
            '/qodmin/finance/expenses' => 'FINANCE_EXPENSES',
            '/business/finance/expenses' => 'FINANCE_EXPENSES',
            '/qodmin/finance/invoices' => 'FINANCE_INVOICES',
            '/business/finance/invoices' => 'FINANCE_INVOICES',
            '/qodmin/finance/suppliers' => 'FINANCE_SUPPLIERS',
            '/business/finance/suppliers' => 'FINANCE_SUPPLIERS',
            '/qodmin/finance/waste' => 'FINANCE_WASTE',
            '/business/finance/waste' => 'FINANCE_WASTE',
            '/qodmin/settings' => 'SETTINGS',
            '/qodmin/trial-settings' => 'TRIAL_MANAGEMENT',
            '/qodmin/trial-users' => 'TRIAL_MANAGEMENT',
            '/qodmin/payment-links' => 'PAYMENT_LINKS',
            '/qodmin/payment-links/create' => 'PAYMENT_LINKS',
            '/qodmin/legal-pages' => 'LEGAL_PAGES',
            '/business/settings' => 'SYSTEM_SETTINGS',
            '/business/features' => 'FEATURE_FLAGS',
            '/qodmin/analytics' => 'ANALYTICS',
            '/business/analytics' => 'ANALYTICS',
            '/qodmin/reports' => 'REPORTS',
            '/business/reports' => 'REPORTS',
            '/business/ai-onerileri' => 'AI_SUGGESTIONS',
            '/qodmin/ai-onerileri' => 'AI_SUGGESTIONS',
            '/business/receipts' => 'RECEIPTS',
            '/business/profile' => 'PROFILE',
            '/business/operations' => 'ORDERS',
            '/qodmin/error-analytics' => 'ERROR_ANALYTICS',
            '/business/error-analytics' => 'ERROR_ANALYTICS',
            '/qodmin/printers/bridge-setup' => 'PRINTERS',
            '/business/printers/bridge-setup' => 'PRINTERS',
            '/qodmin/printers' => 'PRINTERS',
            '/business/printers' => 'PRINTERS',
            '/qodmin/roles-permissions' => 'ROLES',
            '/business/roles-permissions' => 'ROLES',
                        '/qodmin/contact-forms' => 'CONTACT_FORMS',
            '/qodmin/queue' => 'QUEUE',
        ];

        // Check exact match first
        if (isset($urlToTabMap[$uri])) {
            $activeTab = $urlToTabMap[$uri];
        } else {
            // Check partial matches (longest match first)
            $matchedTab = null;
            $matchedLength = 0;
            foreach ($urlToTabMap as $urlPattern => $tabId) {
                if (strpos($uri, $urlPattern) === 0 && strlen($urlPattern) > $matchedLength) {
                    $matchedTab = $tabId;
                    $matchedLength = strlen($urlPattern);
                }
            }
            if ($matchedTab !== null) {
                $activeTab = $matchedTab;
            }
        }

        // Default fallback - only for truly unknown pages, not for specific admin pages
        if ($activeTab === null) {
            if (preg_match('#^/(?:business/)?waiter(?:/|$)#', $uri)) {
                $activeTab = 'WAITER';
            } elseif (preg_match('#^/(?:business/)?kitchen(?:/|$)#', $uri)) {
                $activeTab = 'KITCHEN';
            } elseif (preg_match('#^/(?:business/)?pos(?:/|$)#', $uri)) {
                $activeTab = 'POS';
            } elseif (preg_match('#^/(?:business|qodmin)/preparation-screens/[^/]+(?:/|$)#', $uri)
                || preg_match('#^/preparation-screen/[^/]+(?:/|$)#', $uri)) {
                $activeTab = 'PREPARATION_SCREENS';
            } elseif ($role === 'KITCHEN') {
                $activeTab = 'KITCHEN';
            } elseif (strpos($uri, '/qodmin') === 0) {
                // For admin pages, don't set a default - let checkActive determine based on URI
                $activeTab = null;
            } else {
                // For non-admin pages, use DASHBOARD as fallback
                $activeTab = 'DASHBOARD';
            }
        }
    }

    // Business desktop topbar — page context + business name
    $bizHeaderBusinessName = getAppConfig()->getAppName();
    $bizTopbarLogoUrl = '';
    $bizTopbarContext = 'Yönetim Paneli';
    if ($isBusinessOwnerSurface) {
        if (isset($currentUser['role'])) {
            $normalizedRole = strtoupper(str_replace('ROLE_', '', trim($currentUser['role'])));
            if (in_array($normalizedRole, ['BUSINESS_MANAGER', 'BUSINESS_OWNER', 'TRIAL'], true) && !empty($currentUser['customer_id'])) {
                try {
                    $customerRepo = \App\Core\DependencyFactory::getCustomerRepository();
                    $customer = $customerRepo->findById($currentUser['customer_id']);
                    if ($customer && !empty($customer['company_name'])) {
                        $bizHeaderBusinessName = $customer['company_name'];
                    }
                    if ($customer && function_exists('resolveBusinessPanelLogoUrl')) {
                        $bizTopbarLogoUrl = resolveBusinessPanelLogoUrl($customer);
                    }
                } catch (\Exception $e) {
                    // Silent fail
                }
            }
        }
        $bizTopbarLabels = [
            'DASHBOARD' => 'Genel Bakış',
            'ORDERS' => 'Siparişler',
            'TABLES' => 'Masalar',
            'RESERVATIONS' => 'Rezervasyonlar',
            'MENU' => 'Menü',
            'CATEGORIES' => 'Kategoriler',
            'RECEIPTS' => 'Fişler',
            'QUEUE' => 'Sıra',
            'POS' => 'POS',
            'WAITER' => 'Garson Paneli',
            'KITCHEN' => 'Mutfak',
            'PREPARATION_SCREENS' => 'Hazırlık Ekranları',
            'FINANCE' => 'Finans',
            'FINANCE_EXPENSES' => 'Giderler',
            'FINANCE_INVOICES' => 'Faturalar',
            'FINANCE_INVENTORY' => 'Stok',
            'FINANCE_WASTE' => 'Fire',
            'FINANCE_SUPPLIERS' => 'Tedarikçiler',
            'ANALYTICS' => 'Analiz',
            'REPORTS' => 'Raporlar',
            'AI_SUGGESTIONS' => 'AI Önerileri',
            'ERROR_ANALYTICS' => 'Hata Analizi',
            'SYSTEM_SETTINGS' => 'Ayarlar',
            'SETTINGS' => 'Ayarlar',
            'FEATURE_FLAGS' => 'Özellikler',
            'PRINTERS' => 'Yazıcılar',
            'ROLES' => 'Roller',
            'PROFILE' => 'Profil',
        ];
        if (!empty($activeTab) && isset($bizTopbarLabels[$activeTab])) {
            $bizTopbarContext = $bizTopbarLabels[$activeTab];
        }
        $bizTopbarRangeLabel = '';
        $bizTopbarRangeKey = '';
        $bizTopbarShowRange = false;
        if (strpos($uri, '/business/dashboard') === 0 && function_exists('resolveDashboardRangeKey')) {
            $dashboardRangeLabels = function_exists('getDashboardRangeLabels')
                ? getDashboardRangeLabels()
                : ['today' => 'Bugün'];
            $bizTopbarRangeKey = resolveDashboardRangeKey($uri);
            $bizTopbarRangeLabel = $dashboardRangeLabels[$bizTopbarRangeKey] ?? 'Bugün';
            $bizTopbarShowRange = true;
            $_SESSION['dashboard_range'] = $bizTopbarRangeKey;
        }
        if (function_exists('renderBusinessPanelTopbar')) {
            $bizPanelTopbarHtml = renderBusinessPanelTopbar(
                $bizTopbarContext,
                $bizHeaderBusinessName,
                $bizTopbarRangeLabel,
                [
                    'logoUrl' => $bizTopbarLogoUrl,
                    'brandLogoUrl' => $logoUrl,
                    'currentRange' => $bizTopbarRangeKey,
                    'showRangeChip' => $bizTopbarShowRange,
                    'businessNumber' => $bizBusinessNumber,
                ]
            );
        }
    } elseif ($isQodminSurface) {
        $bizTopbarLabels = [
            'SUPER_ADMIN_DASHBOARD' => 'Platform Özeti',
            'DASHBOARD' => 'Genel Bakış',
            'ORDERS' => 'Siparişler',
            'TABLES' => 'Masalar',
            'RESERVATIONS' => 'Rezervasyonlar',
            'MENU' => 'Menü',
            'CATEGORIES' => 'Kategoriler',
            'RECEIPTS' => 'Fişler',
            'QUEUE' => 'Sıra',
            'POS' => 'POS',
            'WAITER' => 'Garson Paneli',
            'KITCHEN' => 'Mutfak',
            'PREPARATION_SCREENS' => 'Hazırlık Ekranları',
            'FINANCE' => 'Finans',
            'FINANCE_EXPENSES' => 'Giderler',
            'FINANCE_INVOICES' => 'Faturalar',
            'FINANCE_INVENTORY' => 'Stok',
            'FINANCE_WASTE' => 'Fire',
            'FINANCE_SUPPLIERS' => 'Tedarikçiler',
            'ANALYTICS' => 'Analiz',
            'REPORTS' => 'Raporlar',
            'AI_SUGGESTIONS' => 'AI Önerileri',
            'ERROR_ANALYTICS' => 'Hata Analizi',
            'SYSTEM_SETTINGS' => 'Ayarlar',
            'SETTINGS' => 'Ayarlar',
            'FEATURE_FLAGS' => 'Özellikler',
            'PRINTERS' => 'Yazıcılar',
            'ROLES' => 'Roller',
            'PROFILE' => 'Profil',
            'TRIAL_MANAGEMENT' => 'Deneme Yönetimi',
            'PAYMENT_LINKS' => 'Ödeme Linkleri',
            'LEGAL_PAGES' => 'Yasal Sayfalar',
            'CONTACT_FORMS' => 'İletişim Formları',
        ];
        if (!empty($activeTab) && isset($bizTopbarLabels[$activeTab])) {
            $bizTopbarContext = $bizTopbarLabels[$activeTab];
        } else {
            $bizTopbarContext = 'Platform Yönetimi';
        }
        $bizHeaderBusinessName = getAppConfig()->getAppName() ?: 'Qordy Platform';
        if (function_exists('renderBusinessPanelTopbar')) {
            $bizPanelTopbarHtml = renderBusinessPanelTopbar(
                $bizTopbarContext,
                $bizHeaderBusinessName,
                '',
                [
                    'homeUrl' => BASE_URL . '/qodmin/dashboard',
                    'profileUrl' => BASE_URL . '/qodmin/profile',
                    'brandLogoUrl' => $logoUrl,
                    'logoUrl' => '',
                ]
            );
        }
    }

    $isMobileNavOpen = false;

    // Note: Role checks already done above for body class, variables are already set

    // Redirect restricted role users away from non-allowed pages
    // Only redirect if headers haven't been sent yet and URI is defined
    if (!headers_sent() && isset($uri) && !empty($uri)) {
        if ($isKitchenRole) {
            // Check if current page is a kitchen page
            $kitchenPages = ['/kitchen/dashboard', '/kitchen/orders', '/kitchen', '/business/kitchen/dashboard', '/business/kitchen/orders', '/business/kitchen'];
            $isKitchenPage = false;
            foreach ($kitchenPages as $kitchenPage) {
                if (strpos($uri, $kitchenPage) === 0) {
                    $isKitchenPage = true;
                    break;
                }
            }
            // If not on a kitchen page, redirect to kitchen dashboard
            if (!$isKitchenPage && $uri !== '/') {
                // CRITICAL: Use current host (with subdomain) for redirect to preserve subdomain
                $currentHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $redirectUrl = $protocol . '://' . $currentHost . '/kitchen/dashboard';
                header('Location: ' . $redirectUrl);
                exit;
            }
        } elseif ($isCashierRole) {
            // Cashier role: redirect to POS dashboard (cashier pages removed)
            if ($uri !== '/' && strpos($uri, '/pos') !== 0 && strpos($uri, '/business/pos') !== 0) {
                // CRITICAL: Use current host (with subdomain) for redirect to preserve subdomain
                $currentHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $redirectUrl = $protocol . '://' . $currentHost . '/pos';
                header('Location: ' . $redirectUrl);
                exit;
            }
        } elseif ($isWaiterRole) {
            // Check if current page is a waiter page
            $waiterPages = ['/waiter/dashboard', '/waiter/pos', '/waiter', '/business/waiter/dashboard', '/business/waiter/pos', '/business/waiter'];
            $isWaiterPage = false;
            foreach ($waiterPages as $waiterPage) {
                if (strpos($uri, $waiterPage) === 0) {
                    $isWaiterPage = true;
                    break;
                }
            }
            // If not on a waiter page, redirect to waiter dashboard (zone view)
            if (!$isWaiterPage && $uri !== '/') {
                // CRITICAL: Use current host (with subdomain) for redirect to preserve subdomain
                $currentHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $redirectUrl = $protocol . '://' . $currentHost . '/waiter/dashboard';
                header('Location: ' . $redirectUrl);
                exit;
            }
        }
    }
    ?>
<body class="<?php echo !$isRestrictedRole ? 'flex flex-col' : 'flex'; ?> h-screen q-page-canvas <?php echo !$isRestrictedRole ? 'q-biz-layout' : ''; ?> <?php echo $isRestrictedRole ? 'restricted-role-fullscreen overflow-hidden' : 'overflow-hidden lg:overflow-hidden'; ?> <?php echo (!empty($_SESSION['superadmin_backup']) && !empty($_SESSION['logged_in_as'])) ? 'pt-[42px]' : ''; ?>"
      data-page="<?php echo strtolower(explode('/', trim($uri, '/'))[0] ?? ''); ?>"
      data-base-url="<?php echo htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8'); ?>"
      data-websocket-url="<?php echo htmlspecialchars($websocketUrl, ENT_QUOTES, 'UTF-8'); ?>"
      data-websocket-port="<?php echo htmlspecialchars($websocketPort, ENT_QUOTES, 'UTF-8'); ?>">
    <a href="#main-content" class="q-skip-link">İçeriğe geç</a>
    <?php if (!empty($_SESSION['superadmin_backup']) && !empty($_SESSION['logged_in_as'])): ?>
    <?php
        // Qodmin'e dön linki: subdomain üzerindeysek admin_return_url
        // (mutlak URL) kullanılır, qordy.com üzerindeysek eski in-session
        // restore akışı çalışır.
        $impReturnUrl = $_SESSION['admin_return_url'] ?? (BASE_URL . '/qodmin/restore-session');
    ?>
    <div class="fixed top-0 left-0 right-0 z-[10050] bg-amber-400 text-slate-900 text-xs sm:text-sm font-bold px-3 py-2.5 flex flex-wrap items-center justify-center gap-3 shadow-md border-b border-amber-500/30">
        <span class="flex items-center gap-2"><svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
        Süper admin olarak <?php echo htmlspecialchars($_SESSION['logged_in_as'] ?? '', ENT_QUOTES, 'UTF-8'); ?> hesabına giriş yaptınız.</span>
        <a href="<?php echo htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8'); ?>/qodmin/restore-session" class="inline-flex items-center gap-1.5 bg-slate-900 text-white px-3 py-1.5 rounded-lg text-xs font-black hover:bg-slate-800 transition-colors">Qodmin’e dön</a>
    </div>
    <?php endif; ?>

    <?php /* ============================================================
           TRIAL PERIOD BANNERS & BLOCKING OVERLAY
           ------------------------------------------------------------
           Personel (waiter/pos/kitchen/cashier/preparation-screen)
           ekranlarında "Paket Al", "Planları Gör" gibi CTA'lar ve
           "beğendiyseniz paket alın" gibi satış metinleri
           GÖSTERİLMEZ — personelin paket satın alma yetkisi yoktur,
           mesajları sadece işletme sahibi/yönetici görür. Personele
           sadece nötr bir bilgi satırı çıkar.
           ============================================================ */
    $__isStaffView = !empty($isWaiterView);
    if ($isTrialRole && !$isSuperAdminRole && $trialInfo['is_trial']): ?>

        <?php if ($trialInfo['fully_blocked']): ?>
        <!-- ======= FULL BLOCK: grace period over ======= -->
        <div id="trial-full-block" class="fixed inset-0 z-[10060] flex items-center justify-center bg-slate-900/95 backdrop-blur-sm">
            <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full mx-4 p-8 text-center">
                <div class="w-20 h-20 rounded-full bg-red-100 flex items-center justify-center mx-auto mb-5">
                    <svg class="w-10 h-10 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                </div>
                <?php
                $__supportEmail = 'destek@qordy.com';
                try {
                    $__settingsSvc = \App\Core\DependencyFactory::getSystemSettingsService();
                    if ($__settingsSvc) {
                        $__supportEmail = $__settingsSvc->getSupportEmail();
                    }
                } catch (\Throwable $__e) {}
                $__supportEmailSafe = htmlspecialchars($__supportEmail, ENT_QUOTES, 'UTF-8');
                ?>
                <?php if ($__isStaffView): ?>
                <h2 class="text-2xl font-black text-slate-900 mb-3">Hizmet Geçici Olarak Duraklatıldı</h2>
                <p class="text-slate-500 mb-6">Sistem şu anda kullanıma kapatılmıştır. Lütfen işletme yöneticinize başvurun; o gerekli işlemleri başlatabilir.</p>
                <p class="text-xs text-slate-400 mt-2">Destek için <a href="mailto:<?= $__supportEmailSafe ?>" class="underline"><?= $__supportEmailSafe ?></a></p>
                <?php else: ?>
                <h2 class="text-2xl font-black text-slate-900 mb-3">İşletmeniz Askıya Alındı</h2>
                <p class="text-slate-500 mb-6">Deneme süreniz sona erdi ve 7 günlük ek süre de doldu. Sistemi kullanmaya devam etmek için bir paket satın alın.</p>
                <a href="<?= BASE_URL ?>/customer/packages/list" class="inline-block w-full bg-slate-900 hover:bg-slate-800 text-white font-black py-4 px-6 rounded-xl text-lg transition-all">
                    Paket Satın Al
                </a>
                <p class="text-xs text-slate-400 mt-4">Sorularınız için <a href="mailto:<?= $__supportEmailSafe ?>" class="underline"><?= $__supportEmailSafe ?></a></p>
                <?php endif; ?>
            </div>
        </div>

        <?php elseif ($trialInfo['is_expired']): ?>
        <!-- ======= GRACE PERIOD: trial expired, 1-7 days remain ======= -->
        <div id="trial-grace-banner" class="fixed top-0 left-0 right-0 z-[10049] bg-red-600 text-white text-xs sm:text-sm font-bold px-3 py-2.5 flex flex-wrap items-center justify-center gap-3 shadow-md">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <?php if ($__isStaffView): ?>
            <span>Deneme süresi sona erdi. Sistem <strong>yalnızca görüntüleme</strong> modundadır. Lütfen işletme yöneticinizle iletişime geçin.</span>
            <?php else: ?>
            <span>Deneme süreniz <strong>bitti</strong>. <?= $trialInfo['grace_days_left'] ?> gün içinde paket satın almazsanız işletmeniz tamamen askıya alınacak. Artık <strong>yalnızca görüntüleme</strong> yapabilirsiniz.</span>
            <a href="<?= BASE_URL ?>/customer/packages/list" class="inline-flex items-center gap-1.5 bg-white text-red-700 px-3 py-1.5 rounded-lg text-xs font-black hover:bg-red-50 transition-colors shrink-0">
                Hemen Paket Al →
            </a>
            <?php endif; ?>
        </div>
        <style>body { padding-top: 42px !important; }</style>

        <?php else: ?>
        <!-- ======= ACTIVE TRIAL: countdown banner ======= -->
        <?php $_daysLeft = $trialInfo['days_left']; ?>
        <div id="trial-countdown-banner"
             class="fixed top-0 left-0 right-0 z-[10049] text-white text-xs sm:text-sm font-bold px-3 py-2.5 flex flex-wrap items-center justify-center gap-3 shadow-md <?= $_daysLeft <= 3 ? 'bg-orange-500' : 'bg-emerald-600' ?>">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <?php if ($__isStaffView): ?>
                <span>
                    Qordy <strong>deneme sürümü</strong> aktif — tüm özellikler açık. İyi çalışmalar, kolay gelsin!
                </span>
            <?php else: ?>
                <span>
                    <?php if ($_daysLeft <= 0): ?>
                        Deneme süreniz <strong>bugün bitiyor!</strong>
                    <?php elseif ($_daysLeft === 1): ?>
                        Deneme süreniz bitmesine <strong>1 gün</strong> kaldı.
                    <?php else: ?>
                        Deneme süreniz bitmesine <strong><?= (int)$_daysLeft ?> gün</strong> kaldı.
                    <?php endif; ?>
                    Tüm özellikler açık — beğendiyseniz paket alın.
                </span>
                <span id="trial-timer" class="font-mono bg-black/20 px-2 py-0.5 rounded text-xs"></span>
                <a href="<?= BASE_URL ?>/customer/packages/list" class="inline-flex items-center gap-1.5 bg-white <?= $_daysLeft <= 3 ? 'text-orange-700' : 'text-emerald-700' ?> px-3 py-1.5 rounded-lg text-xs font-black hover:opacity-90 transition-colors shrink-0">
                    Paket Al →
                </a>
            <?php endif; ?>
        </div>
        <style>body { padding-top: 42px !important; }</style>
        <script>
        (function(){
            var endTs = <?= (int)($trialInfo['trial_end_ts'] ?? 0) ?> * 1000;
            var el = document.getElementById('trial-timer');
            if (!el || !endTs) return;
            function pad(n) { return String(n).padStart(2,'0'); }
            function tick() {
                var diff = endTs - Date.now();
                if (diff <= 0) { el.textContent = ''; location.reload(); return; }
                var totalSecs = Math.floor(diff / 1000);
                var days  = Math.floor(totalSecs / 86400);
                var hours = Math.floor((totalSecs % 86400) / 3600);
                var mins  = Math.floor((totalSecs % 3600) / 60);
                var secs  = totalSecs % 60;
                var text;
                if (totalSecs >= 86400) {
                    // More than 1 day left — show days only
                    text = days + ' gün ' + pad(hours) + ':' + pad(mins);
                } else if (totalSecs >= 3600) {
                    // Less than 24h — show hours and minutes
                    text = pad(hours) + ':' + pad(mins) + ' saat';
                } else {
                    // Less than 1h — show mm:ss countdown
                    text = pad(mins) + ':' + pad(secs);
                }
                el.textContent = text;
            }
            tick(); setInterval(tick, 1000);
        })();
        </script>
        <?php endif; ?>
    <?php endif; /* end TRIAL banners */ ?>

    <!-- Desktop Sidebar - Hidden for restricted roles (all non-manager roles) -->
    <?php if (!$isRestrictedRole): ?>
    <?php if ($isPanelSurface && $bizPanelTopbarHtml !== ''): ?>
    <?php echo $bizPanelTopbarHtml; ?>
    <script src="<?php echo BASE_URL; ?>/assets/js/admin/panel-topbar.js?v=<?php echo (int)@filemtime(dirname(__DIR__, 3) . '/public/assets/js/admin/panel-topbar.js'); ?>" defer></script>
    <?php endif; ?>
    <?php if ($isPanelSurface): ?>
    <div class="q-biz-layout__frame">
    <?php endif; ?>
    <aside class="desktop-sidebar <?php echo htmlspecialchars(renderBusinessPanelSidebarAsideClass(false), ENT_QUOTES, 'UTF-8'); ?>">
        <?php echo renderBusinessPanelSidebarNavOpenTag('main-navigation'); ?>
            <?php
            // NO FALLBACK FUNCTIONS - Only use database data
            
            // Navigation render error tracking
            $navigationError = null;
            $navigationRendered = false;
            $navigationErrorDetails = [];
            $panelSections = [];

 // CRITICAL FIX (v2.5): Default closures BEFORE try — closure use() snapshots
 // must never be null. If try fails before real definitions, these defaults apply.
 $checkActive = function($itemUrl, $itemId) { return false; };
 $checkGroupActive = function($items) { return false; };

            try {
                // Ensure UI helpers are loaded for getIcon function
                // ui.php and Authorization are already loaded by HelperLoader and Controller

                // CRITICAL: Ensure session is started and consistent
                if (session_status() === PHP_SESSION_NONE) {
                    \App\Core\SessionManager::ensureSession();
                    if (session_status() === PHP_SESSION_NONE) {
                        throw new \Exception('Session could not be started');
                    }
                }

                // Verify session data consistency
                $hasUserId = !empty($_SESSION['user_id']);
                $hasRole = !empty($_SESSION['role']) || !empty($_SESSION['role_id']);

                if (!$hasUserId && !$hasRole) {
                    // Log detailed error for debugging
                    $navigationErrorDetails['session_data'] = [
                        'user_id' => $_SESSION['user_id'] ?? 'not_set',
                        'role' => $_SESSION['role'] ?? 'not_set',
                        'role_id' => $_SESSION['role_id'] ?? 'not_set',
                        'logged_in' => $_SESSION['logged_in'] ?? 'not_set'
                    ];

                    if (class_exists('\App\Core\Logger')) {
                        \App\Core\Logger::warning('Navigation render: User not authenticated', $navigationErrorDetails);
                    }

                    throw new \Exception('User not authenticated - session data incomplete');
                }

                // Use centralized NavigationService to get navigation items
                require_once __DIR__ . '/../../core/DependencyFactory.php';
                $navigationService = \App\Core\DependencyFactory::getNavigationService();

                // Get filtered navigation items (with role and permission filtering)
                // NavigationService already handles filtering, sorting, and processing
                $navigationItems = $navigationService->getNavigationItems(true); // Use cache
                
                // Ensure navigationItems is always an array
                if (!is_array($navigationItems)) {
                    $navigationItems = [];
                }
                
                // Check if navigation items are empty
                if (empty($navigationItems)) {
                    $navigationError = 'Navigation items are empty. Please run navigation seed script or check database.';
                    $navigationErrorDetails['empty_items'] = true;
                    $navigationErrorDetails['is_super_admin'] = $isSuperAdminRole ?? false;
                    $navigationErrorDetails['user_id'] = $_SESSION['user_id'] ?? 'not_set';
                    $navigationErrorDetails['role'] = $_SESSION['role'] ?? 'not_set';
                    
                    if (class_exists('\App\Core\Logger')) {
                        \App\Core\Logger::error('Navigation render: Empty navigation items', $navigationErrorDetails);
                    }
                    
                    throw new \Exception($navigationError);
                }

            // Process children items - extract children from parent items and handle them separately
            // Children items should NOT be added as separate navigation items if they belong to a parent
            $childrenItemIds = [];
            $processedNavigationItems = [];

            foreach ($navigationItems as $item) {
                $itemId = $item['id'] ?? '';
                $children = $item['children'] ?? [];

                // If item has children, mark children IDs to avoid duplicate processing
                if (!empty($children) && is_array($children)) {
                    foreach ($children as $child) {
                        $childId = $child['id'] ?? '';
                        if (!empty($childId)) {
                            $childrenItemIds[] = $childId;
                        }
                    }
                }

                $processedNavigationItems[] = $item;
            }

            // Keep all processed items to allow our custom grouping to map them correctly
            $finalNavigationItems = $processedNavigationItems;

            // FIX (2026-06-11): NavigationService returns nested children but the
            // grouping loop below only inspects parent_id - so children are never
            // grouped. Flatten nested children so parent_id-based grouping works.
            $flattened = [];
            foreach ($finalNavigationItems as $parentItem) {
                $flattened[] = $parentItem;
                if (!empty($parentItem['children']) && is_array($parentItem['children'])) {
                    foreach ($parentItem['children'] as $child) {
                        $child['parent_id'] = $child['parent_id'] ?? $parentItem['id'] ?? null;
                        $flattened[] = $child;
                    }
                }
            }
            $finalNavigationItems = $flattened;


            // Group items based on database parent_id relationships
            // Modernized: clean categorize, no legacy fallback chains
            $standaloneItems = [];
            $groupedItems = [
                'screens' => [],
                'operations' => [],
                'finance' => [],
                'analytics' => [],
 'hr' => [],
                'settings' => [],
                'superadmin' => []
            ];

            // Finance sub-groups (4 kategori)
            $financeSubIds = [
                'FINANCE_INVOICES', 'FINANCE_EXPENSES',
                'FINANCE_INVENTORY', 'FINANCE_PURCHASES', 'FINANCE_STOCK_CATEGORIES', 'FINANCE_LOW_STOCK',
                'FINANCE_SUPPLIERS', 'FINANCE_SUPPLIER_PERFORMANCE',
                'FINANCE_WASTE',
            ];

            // Top-level ID → group map (sadece DB'de parent_id = null olan gerçek top-level item'lar)
 // NOT: POS/WAITER/KITCHEN/PREPARATION_SCREENS'in parent_id=SCREENS, TABLES/MENU/ORDERS'un parent_id=OPERATIONS
 // — bunlar zaten parent'larının children'ı olarak gelecek, burada TEKRAR listelenmemeli (duplicate olur).
            $topLevelGroupMap = [
               
               
               
               
               
                'GENERAL_ANALYTICS' => 'analytics', 'PRODUCT_SALES' => 'analytics', 'REPORTS' => 'analytics',
                'ERROR_LOGS' => 'analytics', 'SYSTEM_LOGS' => 'analytics',
                'STAFF' => 'settings', 'ROLES_PERMISSIONS' => 'settings', 'SYSTEM_SETTINGS' => 'settings',
                'PRINTERS' => 'settings', 'PAYMENT_GATEWAYS' => 'settings',
                'HR_SHIFTS' => 'hr', 'HR_LEAVES' => 'hr', 'HR_GUEST_STAFF' => 'hr',
            ];

            // Super Admin only item'lar
            $superAdminOnlyIds = [
                'PACKAGES', 'SUBSCRIPTIONS', 'ALL_BUSINESSES', 'BUSINESS_OWNERS',
                'BANK_TRANSFERS', 'BANK_ACCOUNTS', 'CONTACT_FORMS'
            ];

            foreach ($finalNavigationItems as $item) {
                $itemId   = $item['id']   ?? '';
                $parentId = $item['parent_id'] ?? null;
                if (empty($itemId)) continue;

                // 1) Item kendisi bir üst-grup (SCREENS, OPERATIONS vb.) — sadece SAAS_MANAGEMENT'ı superadmin'e ekle, gerisini atla
                $topGroupIds = ['SCREENS','OPERATIONS','FINANCE','FINANCE_MAIN','ANALYTICS','HR','SETTINGS'];
                if (in_array($itemId, $topGroupIds, true)) {
                    if ($itemId === 'SAAS_MANAGEMENT') {
                        $groupedItems['superadmin'][] = $item;
                    }
                    // Aksi hâlde parent grup satırını gruba koyma, child'ları zaten parentId ile gelecek
                    continue;
                }

                // 2) parent_id'ye göre grup ataması
                $parentGroupMap = [
                    'SCREENS'         => 'screens',
                    'OPERATIONS'      => 'operations',
                    'FINANCE'         => 'finance',
                    'FINANCE_MAIN'    => 'finance',
                    'ANALYTICS'       => 'analytics',
                    'HR'              => 'hr',
                    'SETTINGS'        => 'settings',
                    'SAAS_MANAGEMENT' => 'superadmin',
                ];
                if ($parentId && isset($parentGroupMap[$parentId])) {
                    $groupedItems[$parentGroupMap[$parentId]][] = $item;
                    continue;
                }

                // 3) Finance sub-item'ları (parent_id yok ama FINANCE grubuna ait)
                if (in_array($itemId, $financeSubIds, true)) {
                    $groupedItems['finance'][] = $item;
                    continue;
                }

                // 4) topLevelGroupMap ile ek atamalar
                if (isset($topLevelGroupMap[$itemId])) {
                    $groupedItems[$topLevelGroupMap[$itemId]][] = $item;
                    continue;
                }

                // 5) Super Admin only
                if (in_array($itemId, $superAdminOnlyIds, true)) {
                    $groupedItems['superadmin'][] = $item;
                    continue;
                }

                // 6) Default: standalone
                $standaloneItems[] = $item;
            }


            // Dedupe inside groups (DB bazen aynı id'yi iki kez gönderir)
            foreach ($groupedItems as $gKey => $gItems) {
                $seen = [];
                $groupedItems[$gKey] = array_values(array_filter($gItems, function($i) use (&$seen) {
                    $id = $i['id'] ?? '';
                    if (isset($seen[$id])) { return false; }
                    $seen[$id] = true;
                    return true;
                }));
            }
            

            // Function to check if item is active
            $checkActive = function($itemUrl, $itemId) use ($uri, $activeTab) {
                $isActive = false;

                // Special handling for DASHBOARD - only active on exact /qodmin/dashboard or /business/dashboard
                // Do NOT use activeTab check as it's too broad and causes false positives
                if ($itemId === 'DASHBOARD' || $itemId === 'SUPER_ADMIN_DASHBOARD') {
                    $normalizedUri = rtrim($uri, '/');
                    if ($normalizedUri === '/qodmin' || $normalizedUri === '/qodmin/dashboard') {
                        $isActive = true;
                    }
                    if ($normalizedUri === '/business' || $normalizedUri === '/business/dashboard') {
                        $isActive = true;
                    }
                    return $isActive;
                }

                // Super admin: sıra (/qodmin/queue ve /qodmin/queue/{tenant})
                if ($itemId === 'QUEUE') {
                    $normalizedUri = rtrim($uri, '/');
                    if (strpos($normalizedUri, '/qodmin/queue') === 0) {
                        $isActive = true;
                    }
                    return $isActive;
                }

                // Special handling for ROLES - only active on exact /admin/roles-permissions
                if ($itemId === 'ROLES') {
                    $normalizedUri = rtrim($uri, '/');
                    // Check exact match with /admin/roles-permissions
                    if ($normalizedUri === '/qodmin/roles-permissions') {
                        $isActive = true;
                        return $isActive;
                    }
                    // Also check with itemUrl if provided
                    if (!empty($itemUrl)) {
                        $normalizedItemUrl = rtrim($itemUrl, '/');
                        if ($normalizedUri === $normalizedItemUrl) {
                            $isActive = true;
                        }
                    }
                    return $isActive;
                }

                // Special handling for ANALYTICS - active on /admin/analytics
                if ($itemId === 'ANALYTICS') {
                    $normalizedUri = rtrim($uri, '/');
                    $normalizedItemUrl = !empty($itemUrl) ? rtrim($itemUrl, '/') : '/qodmin/analytics';
                    if ($normalizedUri === $normalizedItemUrl || $normalizedUri === '/qodmin/analytics') {
                        $isActive = true;
                    }
                    return $isActive;
                }

                // Special handling for finance sub-items - check if current URI matches finance sub-page
                if (strpos($itemId, 'FINANCE_') === 0) {
                    $normalizedCurrentUri = rtrim($uri, '/');
                    $normalizedItemUrl = rtrim($itemUrl, '/');

                    // Exact match
                    if ($normalizedCurrentUri === $normalizedItemUrl) {
                        $isActive = true;
                    }
                    // Check activeTab as fallback
                    if (!$isActive && !empty($activeTab) && $activeTab === $itemId) {
                        $isActive = true;
                    }
                    return $isActive;
                }

                // Special handling for MENU and MENU_ITEMS - both should be active on /admin/menu
                if ($itemId === 'MENU' || $itemId === 'MENU_ITEMS') {
                    $normalizedCurrentUri = rtrim($uri, '/');
                    $normalizedItemUrl = rtrim($itemUrl, '/');
                    if ($normalizedCurrentUri === '/qodmin/menu' || $normalizedCurrentUri === $normalizedItemUrl) {
                        $isActive = true;
                    }
                    // Also check if any child of MENU is active
                    if (!$isActive && $normalizedCurrentUri === '/qodmin/menu') {
                        $isActive = true;
                    }
                }

                // Special handling for CATEGORIES - active on /admin/categories
                if ($itemId === 'CATEGORIES') {
                    $normalizedCurrentUri = rtrim($uri, '/');
                    if ($normalizedCurrentUri === '/qodmin/categories') {
                        $isActive = true;
                    }
                }

                if (!empty($itemUrl)) {
                    $normalizedItemUrl = rtrim($itemUrl, '/');
                    $normalizedCurrentUri = rtrim($uri, '/');

                    // Check URL hash for settings tabs
                    if (strpos($normalizedItemUrl, '#') !== false) {
                        $urlParts = explode('#', $normalizedItemUrl);
                        $baseUrl = $urlParts[0];
                        $hash = $urlParts[1] ?? '';
                        if ($normalizedCurrentUri === $baseUrl && !empty($hash)) {
                            // Check if hash matches (for settings tabs)
                            if (isset($_GET['tab']) && $_GET['tab'] === $hash) {
                                $isActive = true;
                            }
                            // Also check if we're on settings page and hash matches
                            if ($normalizedCurrentUri === '/qodmin/settings' && $hash === 'receipt') {
                                // Will be handled by JavaScript hash change
                                $isActive = false; // Let JavaScript handle it
                            }
                        }
                    }

                    // Exact match
                    if ($normalizedCurrentUri === $normalizedItemUrl) {
                        $isActive = true;
                    }
                    // Prefix match for parent items (e.g., /admin/finance matches /admin/finance/expenses)
                    elseif (strpos($normalizedCurrentUri, $normalizedItemUrl) === 0) {
                        // Only match if it's exact or followed by a slash (to avoid partial matches)
                        if ($normalizedCurrentUri === $normalizedItemUrl ||
                            substr($normalizedCurrentUri, strlen($normalizedItemUrl), 1) === '/') {
                            $isActive = true;
                        }
                    }
                }

                // Fallback: check activeTab (but NOT for DASHBOARD to avoid false positives)
                if (!$isActive && !empty($itemId) && $itemId !== 'DASHBOARD' && $activeTab === $itemId) {
                    $isActive = true;
                }

                return $isActive;
            };

            // Function to check if any item in group is active
            $checkGroupActive = function($items) use ($checkActive) {
                foreach ($items as $item) {
                    if ($checkActive($item['url'] ?? '', $item['id'] ?? '')) {
                        return true;
                    }
                    // Also check children items
                    $children = $item['children'] ?? [];
                    if (!empty($children) && is_array($children)) {
                        foreach ($children as $child) {
                            if ($checkActive($child['url'] ?? '', $child['id'] ?? '')) {
                                return true;
                            }
                        }
                    }
                }
                return false;
            };

            if (function_exists('buildBusinessPanelSections')) {
                $panelSections = buildBusinessPanelSections($standaloneItems, $groupedItems, [
                    'isSuperAdminRole' => $isSuperAdminRole,
                    'isManagerRole' => $isManagerRole,
                    'isBusinessManagerRole' => $isBusinessManagerRole,
                ]);
                if (function_exists('renderBusinessPanelNavContent')) {
                    echo renderBusinessPanelNavContent($panelSections, $checkActive, [
                        'isSuperAdminRole' => $isSuperAdminRole,
                        'isBusinessManagerRole' => $isBusinessManagerRole,
                        'hasActiveSubscription' => $hasActiveSubscription,
                    ]);
                }
            }

                // Mark navigation as successfully rendered
                $navigationRendered = true;

                // CRITICAL: Set navigation loaded flag IMMEDIATELY after render completes
                // This ensures the flag is set before any JavaScript files execute
                $renderedItemsCount = (is_array($standaloneItems) ? count($standaloneItems) : 0) +
                    (isset($groupedItems['screens']) && is_array($groupedItems['screens']) ? count($groupedItems['screens']) : 0) +
                    (isset($groupedItems['operations']) && is_array($groupedItems['operations']) ? count($groupedItems['operations']) : 0) +
                    (isset($groupedItems['analytics']) && is_array($groupedItems['analytics']) ? count($groupedItems['analytics']) : 0) +
                    (isset($groupedItems['finance']) && is_array($groupedItems['finance']) ? count($groupedItems['finance']) : 0) +
                    (isset($groupedItems['hr']) && is_array($groupedItems['hr']) ? count($groupedItems['hr']) : 0) +
                    (isset($groupedItems['settings']) && is_array($groupedItems['settings']) ? count($groupedItems['settings']) : 0);

                $debugInfo = $navigationDebugInfo ?? [];

                ?>
                <script>
                    (function() {
                        // Set flags immediately for both desktop and mobile navigation
                        const navElement = document.getElementById("main-navigation");
                        const mobileNavElement = document.getElementById("mobile-navigation");

                        if (navElement) {
                            navElement.setAttribute("data-navigation-loaded", "true");
                            navElement.setAttribute("data-navigation-items-count", "<?php echo $renderedItemsCount; ?>");
                            navElement.setAttribute("data-navigation-timestamp", "<?php echo time(); ?>");
                            navElement.removeAttribute("data-navigation-rendering");
                        }

                        // Also set flag for mobile navigation immediately
                        if (mobileNavElement) {
                            mobileNavElement.setAttribute("data-navigation-loaded", "true");
                            mobileNavElement.setAttribute("data-navigation-timestamp", "<?php echo time(); ?>");
                        }

                        // CRITICAL: Force re-initialization on page navigation
                        // This ensures sidebar dropdowns work correctly when navigating between pages
                        function initializeNavigationOnLoad() {
                            const navElement = document.getElementById("main-navigation");
                            if (navElement && navElement.getAttribute("data-navigation-loaded") === "true") {
                                // Navigation render edilmiş, initialize et
                                if (typeof window.resetNavigationInitialization === "function") {
                                    window.resetNavigationInitialization();
                                }
                                if (typeof window.initializeNavigationSafely === "function") {
                                    setTimeout(function() {
                                        window.initializeNavigationSafely();
                                    }, 100);
                                }
                            }
                        }

                        // Initialize immediately if DOM is ready
                        if (document.readyState === "loading") {
                            document.addEventListener("DOMContentLoaded", initializeNavigationOnLoad, { once: true });
                        } else {
                            // DOM already loaded, initialize after a short delay
                            setTimeout(initializeNavigationOnLoad, 50);
                        }

                        // Also initialize on window load as fallback
                        window.addEventListener("load", function() {
                            initializeNavigationOnLoad();
                        }, { once: true });

                        // Also listen for pageshow event to handle back/forward navigation
                        window.addEventListener("pageshow", function(event) {
                            if (event.persisted || (performance && performance.navigation && performance.navigation.type === 2)) {
                                // Page was loaded from cache (back/forward navigation)
                                if (typeof window.resetNavigationInitialization === "function") {
                                    window.resetNavigationInitialization();
                                }
                                setTimeout(function() {
                                    if (typeof window.initializeNavigationSafely === "function") {
                                        window.initializeNavigationSafely();
                                    }
                                }, 100);
                            } else {
                                // Normal page load, also initialize
                                initializeNavigationOnLoad();
                            }
                        }, { once: false });

                        // Log errors if any
                        <?php
                        $hasErrorsJson = json_encode(!empty($debugInfo['errors']) || !empty($debugInfo['skipped_items']));
                        if ($hasErrorsJson === false) {
                            $hasErrorsJson = 'false';
                        }
                        ?>
                        var hasErrors = <?php echo $hasErrorsJson; ?>;
                        if (hasErrors) {
                            <?php
                            $errorsJson = json_encode($debugInfo['errors'] ?? [], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
                            $skippedJson = json_encode($debugInfo['skipped_items'] ?? [], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
                            if ($errorsJson === false) $errorsJson = '[]';
                            if ($skippedJson === false) $skippedJson = '[]';
                            ?>
                            console.warn("Navigation filtering issues detected:", {
                                errors: <?php echo $errorsJson; ?>,
                                skipped_items: <?php echo $skippedJson; ?>
                            });
                        }
                    })();
                </script>
                <script>
                (function() {
                    function updateOrderApprovalBadge() {
                        var links = document.querySelectorAll('a[data-nav-id="ORDER_APPROVALS"]');
                        if (links.length === 0) return;
                        var baseUrl = (typeof window.BASE_URL !== 'undefined' ? window.BASE_URL : '') || '';
                        var path = window.location.pathname || '';
                        var apiPrefix = (path.indexOf('/qodmin') !== -1) ? '/api/qodmin' : '/api/business';
                        fetch(baseUrl + apiPrefix + '/order-approvals/pending-count', { credentials: 'same-origin' })
                            .then(function(r) { return r.json(); })
                            .then(function(data) {
                                var count = (data && data.success && data.count) ? parseInt(data.count, 10) : 0;
                                links.forEach(function(link) {
                                    var existing = link.querySelector('.order-approval-badge');
                                    if (existing) existing.remove();
                                    if (count > 0) {
                                        var badge = document.createElement('span');
                                        badge.className = 'order-approval-badge absolute -top-1 -right-1 min-w-[18px] h-[18px] px-1 flex items-center justify-center bg-red-500 text-white text-[10px] font-black rounded-full';
                                        badge.textContent = count > 99 ? '99+' : count;
                                        link.style.position = 'relative';
                                        link.appendChild(badge);
                                    }
                                });
                            })
                            .catch(function() {});
                    }
                    if (document.readyState === 'loading') {
                        document.addEventListener('DOMContentLoaded', function() { setTimeout(updateOrderApprovalBadge, 500); });
                    } else {
                        setTimeout(updateOrderApprovalBadge, 500);
                    }
                    setInterval(updateOrderApprovalBadge, 30000);
                })();
                </script>
                <?php
            } catch (\Exception $e) {
                // Log navigation render error with detailed context
                $navigationError = $e->getMessage();
                $navigationRendered = false;

                // Add error details for debugging
                $errorContext = array_merge($navigationErrorDetails, [
                    'file' => __FILE__,
                    'line' => __LINE__,
                    'exception_type' => get_class($e),
                    'session_status' => session_status(),
                    'user_id' => $_SESSION['user_id'] ?? 'not_set',
                    'role' => $_SESSION['role'] ?? 'not_set',
                    'role_id' => $_SESSION['role_id'] ?? 'not_set',
                    'uri' => $uri ?? 'not_set',
                    'trace' => $e->getTraceAsString()
                ]);

                // Log error to error log AND console for debugging
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::error('Navigation render error: ' . $navigationError, [
                        'context' => $errorContext,
                        'trace' => $e->getTraceAsString()
                    ]);
                } else {
                    error_log('Navigation render error: ' . $navigationError . ' in ' . __FILE__ . ':' . __LINE__ . ' | Context: ' . json_encode($errorContext));
                }

                // Output error to console for debugging
                echo '<script>console.error("Navigation render error:", ' . json_encode([
                    'message' => $navigationError,
                    'context' => $errorContext
                ]) . ');</script>';

                // Show fallback navigation with basic menu items
                ?>
                <div class="space-y-2">
                    <div class="p-4 bg-yellow-50 border border-yellow-200 rounded-xl mb-4">
                        <p class="text-xs text-yellow-800 font-bold mb-2">⚠️ Menü yüklenirken bir hata oluştu</p>
                        <p class="text-xs text-yellow-700 mb-3">Temel menü öğeleri gösteriliyor. Sayfayı yenileyin.</p>
                        <p class="text-xs text-yellow-600 mb-2">Hata: <?php echo htmlspecialchars($navigationError); ?></p>
                        <button onclick="window.location.reload()" class="px-3 py-1.5 bg-yellow-600 text-white rounded-lg text-xs font-bold hover:bg-yellow-700 transition-all">
                            Sayfayı Yenile
                        </button>
                    </div>
                    <!-- Fallback basic navigation -->
                    <a href="<?php echo BASE_URL; ?>/admin" class="w-full flex items-center gap-3 px-4 py-3 rounded-xl text-slate-500 hover:bg-slate-50 font-bold text-sm">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                        Ana Sayfa
                    </a>
                    <a href="<?php echo BASE_URL; ?>/admin/menu" class="w-full flex items-center gap-3 px-4 py-3 rounded-xl text-slate-500 hover:bg-slate-50 font-bold text-sm">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                        Menü
                    </a>
                </div>
                <?php
            } catch (\Throwable $e) {
                // Catch any other errors (including fatal errors)
                $navigationError = $e->getMessage();
                $navigationRendered = false;

                $errorContext = array_merge($navigationErrorDetails, [
                    'file' => __FILE__,
                    'line' => __LINE__,
                    'exception_type' => get_class($e),
                    'session_status' => session_status()
                ]);

                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::error('Navigation render fatal error: ' . $navigationError, [
                        'context' => $errorContext,
                        'trace' => $e->getTraceAsString()
                    ]);
                } else {
                    error_log('Navigation render fatal error: ' . $navigationError . ' in ' . __FILE__ . ':' . __LINE__ . ' | Context: ' . json_encode($errorContext));
                }

                ?>
                <div class="space-y-2">
                    <div class="p-4 bg-red-50 border border-red-200 rounded-xl mb-4">
                        <p class="text-xs text-red-800 font-bold mb-2">❌ Kritik Hata</p>
                        <p class="text-xs text-red-700 mb-3">Menü yüklenemedi. Lütfen sayfayı yenileyin veya yöneticiye bildirin.</p>
                        <p class="text-xs text-red-600 mb-2 font-mono">Hata: <?php echo htmlspecialchars($navigationError); ?></p>
                        <?php if (defined('APP_DEBUG') && APP_DEBUG && !empty($errorContext)): ?>
                            <details class="mt-2">
                                <summary class="text-xs text-red-600 cursor-pointer">Detayları göster</summary>
                                <pre class="text-xs text-red-700 mt-2 bg-red-100 p-2 rounded overflow-auto max-h-40"><?php echo htmlspecialchars(print_r($errorContext, true)); ?></pre>
                            </details>
                        <?php endif; ?>
                        <button onclick="window.location.reload()" class="px-3 py-1.5 bg-red-600 text-white rounded-lg text-xs font-bold hover:bg-red-700 transition-all mt-2">
                            Sayfayı Yenile
                        </button>
                    </div>
                    <!-- Fallback basic navigation -->
                    <a href="<?php echo BASE_URL; ?>/admin" class="w-full flex items-center gap-3 px-4 py-3 rounded-xl text-slate-500 hover:bg-slate-50 font-bold text-sm">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                        Ana Sayfa
                    </a>
                </div>
                <?php
            }

            // Set navigation loaded flag in data attribute (backup/ensure flag is set)
            if ($navigationRendered) {
                // Flag should already be set above, but ensure it is set here too as backup
                // Also trigger initialization attempts
                echo '<script>
                    (function() {
                        const navElement = document.getElementById("main-navigation");
                        const mobileNavElement = document.getElementById("mobile-navigation");

                        // Ensure flags are set (backup)
                        if (navElement && navElement.getAttribute("data-navigation-loaded") !== "true") {
                            navElement.setAttribute("data-navigation-loaded", "true");
                        }
                        if (mobileNavElement && mobileNavElement.getAttribute("data-navigation-loaded") !== "true") {
                            mobileNavElement.setAttribute("data-navigation-loaded", "true");
                        }

                        // Wait for admin-navigation.js to load and initialize
                        function waitForScriptsAndInit(attempts) {
                            attempts = attempts || 0;
                            const maxAttempts = 30; // Maximum 3 seconds (30 * 100ms)

                            // Check if admin-navigation.js loaded
                            const scriptsLoaded = typeof window.initializeNavigationSafely === \'function\';

                            if (scriptsLoaded) {
                                // Scripts loaded, initialize once
                                try {
                                    window.initializeNavigationSafely();
                                } catch (e) {
                                    console.error(\'Error in initializeNavigationSafely:\', e);
                                }
                            } else if (attempts < maxAttempts) {
                                // Scripts not loaded yet, wait a bit more
                                setTimeout(function() {
                                    waitForScriptsAndInit(attempts + 1);
                                }, 100);
                            }
                        }

                        // Start waiting for scripts
                        if (document.readyState === \'loading\') {
                            document.addEventListener(\'DOMContentLoaded\', function() {
                                waitForScriptsAndInit(0);
                            }, { once: true });
                        } else {
                            waitForScriptsAndInit(0);
                        }
                    })();
                </script>';
            }
            ?>
        </nav>
        <?php
        if (function_exists('renderBusinessPanelSidebarFooter')) {
            echo renderBusinessPanelSidebarFooter();
        }
        ?>
    </aside>
    <?php endif; ?>

    <!-- Mobile Nav Overlay (Hidden by default, shown with JS) - Hidden for restricted roles -->
    <?php if (!$isRestrictedRole): ?>
    <div id="mobile-nav-overlay" class="lg:hidden fixed inset-0 z-[9999] hidden" aria-hidden="true">
        <div class="absolute inset-0 bg-slate-950/40 backdrop-blur-sm transition-opacity duration-300" data-mobile-nav-backdrop="true"></div>
        <aside class="<?php echo htmlspecialchars(renderBusinessPanelSidebarAsideClass(true), ENT_QUOTES, 'UTF-8'); ?>" onclick="event.stopPropagation()" style="max-width: 85vw;">
            <?php
            $drawerHomeUrl = BASE_URL . ($isQodminSurface ? '/qodmin/dashboard' : '/business/dashboard');
            echo renderBusinessPanelSidebarHeader($drawerHomeUrl, $logoUrl);
            ?>
            <?php echo renderBusinessPanelSidebarNavOpenTag('mobile-navigation', true); ?>
                <?php
                // Mobile navigation uses same variables as desktop navigation
                // If desktop navigation failed, mobile will also fail
                if (!isset($navigationRendered) || !$navigationRendered || !empty($navigationError)) {
                    ?>
                    <div class="p-4 text-center">
                        <p class="text-sm text-slate-500 font-bold mb-2">Menü yüklenirken bir hata oluştu.</p>
                        <button onclick="window.location.reload()" class="px-4 py-2 bg-slate-900 text-white rounded-lg text-xs font-bold hover:bg-slate-800 transition-all">
                            Sayfayı Yenile
                        </button>
                    </div>
                    <?php
                } else {
                    if (function_exists('renderBusinessPanelNavContent')) {
                        echo renderBusinessPanelNavContent($panelSections ?? [], $checkActive, [
                            'isSuperAdminRole' => $isSuperAdminRole,
                            'isBusinessManagerRole' => $isBusinessManagerRole,
                            'hasActiveSubscription' => $hasActiveSubscription,
                        ], ['isMobile' => true]);
                    }

                    // Close the else block for mobile navigation
                    }

                    // Mobile navigation flag is already set in the desktop navigation script above
                    // No need to set it again here to prevent double execution
                ?>
            </nav>
            <?php
            if (function_exists('renderBusinessPanelSidebarActions')) {
                echo renderBusinessPanelSidebarActions($translationService ?? null);
            }
            if (function_exists('renderBusinessPanelSidebarFooter')) {
                echo renderBusinessPanelSidebarFooter();
            }
            ?>
        </aside>
    </div>
    <?php endif; ?>

    <main id="main-content" class="flex-1 relative overflow-hidden flex flex-col min-w-0 <?php echo $isRestrictedRole ? 'w-full' : ''; ?> q-biz-layout__main" style="max-width: 100%; overflow-x: hidden;" tabindex="-1">

        <?php if ($isRestrictedRole && $bizBusinessNumber !== '' && function_exists('renderStaffBusinessCodeBar')): ?>
        <?php echo renderStaffBusinessCodeBar($bizBusinessNumber); ?>
        <?php endif; ?>

        <div class="flex-1 relative min-h-0 min-w-0 <?php echo !empty($isOpsEmbedded) ? 'overflow-hidden q-biz-ops-embed' : 'overflow-y-auto overflow-x-hidden container-padding-responsive'; ?><?php echo empty($isOpsEmbedded) ? ' q-biz-layout__scroll' : ''; ?>" style="max-width: 100%;">
            <!-- Flash Messages -->
            <?php
            $toastService = getToastNotificationService();
            $flashMessages = $toastService->getFlashMessages();
            if (!empty($flashMessages)):
            ?>
                <script>
                    if (window.NotificationManager) {
                        <?php if (isset($flashMessages['success'])): ?>
                            window.NotificationManager.success(<?php echo json_encode($flashMessages['success'], JSON_UNESCAPED_UNICODE); ?>);
                        <?php endif; ?>
                        <?php if (isset($flashMessages['error'])): ?>
                            window.NotificationManager.error(<?php echo json_encode($flashMessages['error'], JSON_UNESCAPED_UNICODE); ?>);
                        <?php endif; ?>
                        <?php if (isset($flashMessages['warning'])): ?>
                            window.NotificationManager.warning(<?php echo json_encode($flashMessages['warning'], JSON_UNESCAPED_UNICODE); ?>);
                        <?php endif; ?>
                        <?php if (isset($flashMessages['info'])): ?>
                            window.NotificationManager.info(<?php echo json_encode($flashMessages['info'], JSON_UNESCAPED_UNICODE); ?>);
                        <?php endif; ?>
                    }
                </script>
            <?php endif; ?>

            <?php
            // Trial banner — YALNIZCA sayfa üstündeki sabit countdown/grace
            // banner (trial-countdown-banner / trial-grace-banner / full-block)
            // gösterilmiyorsa render et. Aksi halde aynı bilgi hem yeşil/kırmızı
            // üst bar hem mor alt bar olarak çift gözüküyordu.
            //
            // Personel (waiter/kitchen/pos/cashier/preparation-screen)
            // ekranlarında mor "Planları Gör / Paket Al" CTA'sı ASLA
            // gösterilmez; personelin paket satın alma yetkisi yoktur.
            require_once __DIR__ . '/../../middleware/TrialMiddleware.php';
            $__hasTopTrialBanner = ($isTrialRole ?? false)
                && !($isSuperAdminRole ?? false)
                && !empty($trialInfo['is_trial']);
            $__isStaffViewForBanner = !empty($isWaiterView);
            $trialBanner = ($__hasTopTrialBanner || $__isStaffViewForBanner)
                ? null
                : \App\Middleware\TrialMiddleware::getTrialBannerData();
            if ($trialBanner):
                $bannerColors = [
                    'red' => 'bg-red-600',
                    'orange' => 'bg-amber-500',
                    'blue' => 'bg-indigo-600',
                ];
                $bgClass = $bannerColors[$trialBanner['color'] ?? 'blue'] ?? 'bg-indigo-600';
            ?>
            <div class="<?php echo $bgClass; ?> text-white px-4 py-2.5 flex items-center justify-between gap-3 shrink-0" id="trialBanner">
                <div class="flex items-center gap-2 text-sm font-medium min-w-0">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span class="truncate"><?php echo htmlspecialchars($trialBanner['message']); ?></span>
                </div>
                <a href="<?php echo htmlspecialchars($trialBanner['cta_url']); ?>" 
                   class="shrink-0 px-3 py-1 bg-white/20 hover:bg-white/30 rounded-md text-xs font-bold transition-colors whitespace-nowrap backdrop-blur-sm">
                    <?php echo htmlspecialchars($trialBanner['cta_text']); ?>
                </a>
            </div>
            <?php endif; ?>

            <?php if (isset($content)): ?>
                <?php if ($isPanelSurface && empty($isOpsEmbedded)): ?>
                    <?php
                    require_once __DIR__ . '/components/BusinessPanelShell.php';
                    echo \App\Views\Components\BusinessPanelShell::render([
                        'content' => $content,
                        'opsEmbed' => false,
                    ]);
                    ?>
                <?php elseif (!empty($isOpsEmbedded)): ?>
                    <?php
                    require_once __DIR__ . '/components/BusinessPanelShell.php';
                    echo \App\Views\Components\BusinessPanelShell::render([
                        'content' => $content,
                        'opsEmbed' => true,
                    ]);
                    ?>
                <?php else: ?>
                    <?php echo $content; ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </main>
    <?php if ($isPanelSurface && !$isRestrictedRole): ?>
    </div><!-- /.q-biz-layout__frame -->
    <?php endif; ?>

    <!-- Dropdown and mobile menu functions moved to external files -->
    <!-- toggleDropdown and toggleMobileNav are defined early in head section -->
    <!-- admin-mobile-menu.js, admin-navigation.js handle the full implementation -->

<?php
$isStaffScreen = isset($view) && (strpos($view, 'waiter/') === 0 || strpos($view, 'pos/') === 0 || strpos($view, 'kitchen/') === 0 || strpos($view, 'preparation-screen/') === 0);
?>
<?php
if (!empty($_SESSION['is_demo'])) {
    if (empty($_SESSION['demo_audit_page_logged'])) {
        $_SESSION['demo_audit_page_logged'] = true;
        try {
            $cid = \App\Core\TenantResolver::resolve();
            if ($cid) {
                $fw = \App\Middleware\SecurityMiddleware::getFirewall();
                $ip = $fw->getClientIP();
                \App\Core\DependencyFactory::getDemoAccessLogRepository()->log(
                    $cid,
                    $_SESSION['user_id'] ?? null,
                    $ip,
                    $_SERVER['HTTP_USER_AGENT'] ?? '',
                    $_SERVER['REQUEST_METHOD'] ?? 'GET',
                    $_SERVER['REQUEST_URI'] ?? '',
                    'page'
                );
            }
        } catch (\Throwable $e) {
        }
    }
    $demoBannerMs = defined('DEMO_BANNER_DELAY_MS') ? (int)DEMO_BANNER_DELAY_MS : 180000;
    $lang = $translationService->getCurrentLanguage();
    $demoBannerTitle = ($lang === 'en')
        ? 'This is a demo version. Purchase a package to get your own workspace!'
        : 'Bu demo sürümüdür. Kendi işletmeniz için paket satın alın!';
    $demoBannerCta = ($lang === 'en') ? 'Buy Package' : 'Paket Satın Al';
    $demoBannerLater = ($lang === 'en') ? 'Later' : 'Sonra';
    $mainDomain = defined('BASE_DOMAIN') ? BASE_DOMAIN : 'qordy.com';
?>
<div id="demo-top-bar" class="fixed top-0 left-0 right-0 z-[9999] bg-gradient-to-r from-orange-500 to-amber-500 text-white text-center py-2 px-4 text-sm font-bold shadow-md" style="min-height:36px;">
    <span><?php echo ($lang === 'en')
        ? 'You are viewing the demo version. Data changes are not saved.'
        : 'Demo sürümünü görüntülüyorsunuz. Veri değişiklikleri kaydedilmez.'; ?></span>
    <a href="https://<?php echo htmlspecialchars($mainDomain); ?>/register" class="ml-3 inline-block px-3 py-0.5 bg-white text-orange-600 rounded-full text-xs font-black hover:bg-orange-50 transition-all"><?php echo ($lang === 'en') ? 'Register Now' : 'Hemen Kayıt Ol'; ?></a>
</div>
<style>#demo-top-bar ~ *, .main-content, .sidebar, [class*="fixed"] { margin-top: 0; } body { padding-top: 36px !important; }</style>

<div id="demo-upsell-overlay" class="hidden fixed inset-0 z-[9998] bg-black/50 flex items-end sm:items-center justify-center p-4" aria-hidden="true">
    <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-xl max-w-md w-full p-6 border border-slate-200 dark:border-slate-700">
        <p class="text-lg font-bold text-slate-900 dark:text-white mb-2"><?php echo htmlspecialchars($demoBannerTitle); ?></p>
        <p class="text-sm text-slate-600 dark:text-slate-300 mb-4"><?php echo ($lang === 'en')
            ? 'This is a read-only demo. Register and purchase a package for your own workspace.'
            : 'Bu salt okunur bir demodur. Kendi işletmeniz için kayıt olun ve paket satın alın.'; ?></p>
        <div class="flex flex-col sm:flex-row gap-2">
            <a href="https://<?php echo htmlspecialchars($mainDomain); ?>/register" id="demo-upsell-cta" class="flex-1 py-3 rounded-xl bg-orange-500 hover:bg-orange-600 text-white font-semibold text-center no-underline"><?php echo htmlspecialchars($demoBannerCta); ?></a>
            <button type="button" id="demo-upsell-dismiss" class="flex-1 py-3 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-800 dark:text-slate-100 font-medium"><?php echo htmlspecialchars($demoBannerLater); ?></button>
        </div>
    </div>
</div>
<script>
(function() {
    var delay = <?php echo (int)$demoBannerMs; ?>;
    var overlay = document.getElementById('demo-upsell-overlay');
    if (!overlay) return;
    var t = setTimeout(function() { overlay.classList.remove('hidden'); overlay.setAttribute('aria-hidden', 'false'); }, delay);
    document.getElementById('demo-upsell-dismiss')?.addEventListener('click', function() {
        clearTimeout(t);
        overlay.classList.add('hidden');
        overlay.setAttribute('aria-hidden', 'true');
    });
})();
</script>
<?php } ?>

<?php
/**
 * ÖZEL TEKLİF POPUP'I (super admin tarafından müşteriye özel paket/fiyat linki)
 *
 * Her sayfa yüklemede /api/customer/custom-offers fetch edilir; müşteriye
 * tanımlanmış aktif (ödenmemiş) link varsa ve dismiss cooldown'u geçmişse
 * modal açılır. Kullanıcı kapatırsa dismiss endpoint'ine post atılır ve
 * cooldown süresi kadar (45dk) tekrar gösterilmez. Navbar'daki sabit
 * "Özel Teklifiniz" rozeti müşteri modal'ı kapatsa bile görünür kalır
 * ki kullanıcı istediği zaman erişebilsin.
 *
 * Yalnızca oturum açmış, giriş-yapılmış-olarak-müşteri (impersonation değil) için render edilir.
 */
$cplIsLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
$cplIsImpersonation = !empty($_SESSION['superadmin_backup']) && !empty($_SESSION['logged_in_as']);
if ($cplIsLoggedIn && !$cplIsImpersonation):
?>
<div id="cpl-offer-modal" role="dialog" aria-hidden="true" aria-labelledby="cpl-modal-title"
     style="display:none;position:fixed;inset:0;z-index:99998;background:rgba(15,23,42,0.55);backdrop-filter:blur(4px);align-items:center;justify-content:center;padding:1rem;">
    <div style="background:#fff;border-radius:20px;max-width:520px;width:100%;box-shadow:0 30px 60px -15px rgba(15,23,42,0.35);overflow:hidden;font-family:'Inter',system-ui,sans-serif;">
        <div style="background:var(--color-gradient-brand, linear-gradient(135deg,#6366f1,#8b5cf6));padding:1.75rem 1.75rem 1.5rem;color:#fff;position:relative;">
            <button type="button" id="cpl-close-btn" aria-label="Kapat"
                    style="position:absolute;top:0.85rem;right:0.85rem;background:rgba(255,255,255,0.2);border:0;color:#fff;width:32px;height:32px;border-radius:50%;cursor:pointer;font-size:1.1rem;line-height:1;">×</button>
            <div style="display:inline-flex;align-items:center;gap:0.5rem;background:rgba(255,255,255,0.15);padding:0.35rem 0.75rem;border-radius:999px;font-size:0.7rem;font-weight:700;letter-spacing:0.05em;text-transform:uppercase;margin-bottom:0.9rem;">
                <span>✨</span><span>Size Özel Teklif</span>
            </div>
            <h2 id="cpl-modal-title" style="font-family:'Plus Jakarta Sans','Inter',sans-serif;font-size:1.45rem;font-weight:800;margin:0 0 0.25rem;">Özel bir paket sizin için hazırlandı</h2>
            <p id="cpl-offer-desc" style="font-size:0.88rem;opacity:0.92;margin:0;line-height:1.5;">Qordy ekibi sizin için kişiselleştirilmiş bir fiyat oluşturdu.</p>
        </div>
        <div style="padding:1.5rem 1.75rem;">
            <div id="cpl-offer-body" style="display:flex;flex-direction:column;gap:0.75rem;">
                <div style="display:flex;justify-content:space-between;align-items:center;padding:0.5rem 0;">
                    <span style="font-size:0.85rem;color:#64748b;">Paket</span>
                    <span id="cpl-pkg-name" style="font-size:0.95rem;font-weight:700;color:#0f172a;"></span>
                </div>
                <div style="display:flex;justify-content:space-between;align-items:center;padding:0.5rem 0;">
                    <span style="font-size:0.85rem;color:#64748b;">Süre</span>
                    <span id="cpl-duration" style="font-size:0.95rem;font-weight:700;color:#0f172a;"></span>
                </div>
                <div style="display:flex;justify-content:space-between;align-items:center;padding:0.75rem 0;border-top:2px dashed #e2e8f0;margin-top:0.25rem;">
                    <span style="font-size:0.9rem;color:#0f172a;font-weight:600;">Size Özel Fiyat</span>
                    <span id="cpl-price" style="font-family:'Plus Jakarta Sans',sans-serif;font-size:1.6rem;font-weight:900;color:#6366f1;"></span>
                </div>
                <p id="cpl-note" style="background:#f5f3ff;border:1px solid #c7d2fe;color:#4338ca;border-radius:12px;padding:0.75rem 1rem;font-size:0.8rem;line-height:1.5;margin:0;display:none;"></p>
            </div>
            <div style="display:flex;flex-direction:column;gap:0.5rem;margin-top:1.25rem;">
                <a id="cpl-buy-btn" href="#"
                   style="display:flex;align-items:center;justify-content:center;padding:0.95rem 1.5rem;background:#6366f1;color:#fff;border-radius:12px;font-weight:700;font-size:0.95rem;text-decoration:none;box-shadow:0 10px 20px -6px rgba(99,102,241,0.45);">
                    Şimdi Satın Al
                </a>
                <button type="button" id="cpl-later-btn"
                        style="padding:0.75rem 1.5rem;background:transparent;color:#64748b;border:0;font-weight:600;font-size:0.85rem;cursor:pointer;">Daha sonra hatırlat</button>
            </div>
        </div>
    </div>
</div>

<div id="cpl-offer-badge" style="display:none;position:fixed;bottom:1.25rem;right:1.25rem;z-index:99990;">
    <button type="button" id="cpl-badge-btn"
            style="display:flex;align-items:center;gap:0.6rem;background:var(--color-gradient-brand, linear-gradient(135deg,#6366f1,#8b5cf6));color:#fff;border:0;padding:0.75rem 1.1rem;border-radius:999px;font-weight:700;font-size:0.85rem;cursor:pointer;box-shadow:0 12px 24px -8px rgba(99,102,241,0.5);font-family:'Inter',system-ui,sans-serif;">
        <span style="font-size:1rem;">🎁</span>
        <span>Size Özel Teklif</span>
        <span id="cpl-badge-count" style="background:#fff;color:#6366f1;border-radius:999px;min-width:20px;height:20px;font-size:0.7rem;display:inline-flex;align-items:center;justify-content:center;padding:0 6px;"></span>
    </button>
</div>

<script>
(function() {
    const BASE_URL = <?php echo json_encode(defined('BASE_URL') ? BASE_URL : ''); ?>;
    const CSRF_TOKEN = <?php echo json_encode($csrfToken); ?>;
    const modal = document.getElementById('cpl-offer-modal');
    const badge = document.getElementById('cpl-offer-badge');
    const badgeCount = document.getElementById('cpl-badge-count');
    const badgeBtn = document.getElementById('cpl-badge-btn');
    if (!modal || !badge) return;

    let currentOffers = [];
    let currentIndex = 0;

    function money(amount, currency) {
        try {
            return new Intl.NumberFormat('tr-TR', {
                style: 'currency', currency: currency || 'TRY',
                minimumFractionDigits: 2
            }).format(amount);
        } catch (e) {
            return (amount || 0).toFixed(2) + ' ' + (currency || 'TRY');
        }
    }

    function renderOffer(offer) {
        if (!offer) return;
        document.getElementById('cpl-pkg-name').textContent = offer.package_name || 'Özel Paket';
        document.getElementById('cpl-duration').textContent = (offer.duration_months || 0) + ' ay';
        document.getElementById('cpl-price').textContent = money(offer.custom_price, offer.currency);
        const note = document.getElementById('cpl-note');
        if (offer.note) {
            note.textContent = offer.note;
            note.style.display = 'block';
        } else {
            note.style.display = 'none';
        }
        document.getElementById('cpl-buy-btn').setAttribute('href', offer.public_url);
    }

    function openModal() {
        if (!currentOffers.length) return;
        renderOffer(currentOffers[currentIndex]);
        modal.style.display = 'flex';
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }
    function closeModal() {
        modal.style.display = 'none';
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    async function dismissCurrent() {
        const offer = currentOffers[currentIndex];
        if (!offer) return;
        try {
            await fetch(BASE_URL + '/api/customer/custom-offers/' + encodeURIComponent(offer.link_id) + '/dismiss', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': CSRF_TOKEN,
                    'Accept': 'application/json'
                }
            });
        } catch (e) { /* silently ignore — sunucu ulaşılamazsa LS fallback */ }
        try { localStorage.setItem('cpl_dismiss_' + offer.link_id, String(Date.now())); } catch(e) {}
    }

    document.getElementById('cpl-close-btn')?.addEventListener('click', function() {
        dismissCurrent();
        closeModal();
    });
    document.getElementById('cpl-later-btn')?.addEventListener('click', function() {
        dismissCurrent();
        closeModal();
    });
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            dismissCurrent();
            closeModal();
        }
    });
    badgeBtn?.addEventListener('click', function() {
        openModal();
    });

    async function loadOffers() {
        try {
            const res = await fetch(BASE_URL + '/api/customer/custom-offers', {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-CSRF-Token': CSRF_TOKEN }
            });
            if (!res.ok) return;
            const data = await res.json();
            if (!data.success || !Array.isArray(data.offers)) return;
            currentOffers = data.offers;
            currentIndex = 0;
            if (currentOffers.length === 0) {
                badge.style.display = 'none';
                return;
            }
            badgeCount.textContent = String(currentOffers.length);
            badge.style.display = 'block';

            const popupCandidate = currentOffers.find(o => o.should_show_popup);
            if (popupCandidate) {
                currentIndex = currentOffers.indexOf(popupCandidate);
                setTimeout(openModal, 800);
            }
        } catch (e) { /* ignore */ }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', loadOffers);
    } else {
        loadOffers();
    }
})();
</script>
<?php endif; ?>
</body>
</html>
