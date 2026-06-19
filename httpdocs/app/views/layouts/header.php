<!DOCTYPE html>
<?php
require_once __DIR__ . '/../../helpers/functions.php';
require_once __DIR__ . '/../../helpers/translations.php';
$translationService = getTranslationService();
$currentLang = $translationService->getCurrentLanguage();
?>
<html lang="<?php echo htmlspecialchars($currentLang); ?>">
<head>
    <?php require_once __DIR__ . '/../partials/google_analytics.php'; ?>
    <?php
    // If custom SEO tags are provided (e.g., for menu items), use them
    if (isset($customSEOTags) && !empty($customSEOTags)) {
        echo $customSEOTags;
    } else {
        // Load SEO Service
        $seoService = getSEOService();
        
        // Get page identifier (default to 'home' if not set)
        $page = $page ?? 'home';
        $seoParams = [
            'id' => $id ?? null,
            'name' => $name ?? null
        ];
        
        // Generate SEO meta tags using SEOService
        echo $seoService->generateMetaTags($page, $seoParams);
        echo $seoService->generateStructuredData($page, $seoParams);
    }
    ?>
    
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, viewport-fit=cover">
    <meta name="theme-color" content="#1e293b">

    <?php
    // Early resource hints — these fire in parallel with HTML parsing and
    // are the single biggest win for LCP on pages that load third-party
    // widgets (Soro blog, analytics, fonts).
    ?>
    <link rel="preconnect" href="https://app.trysoro.com" crossorigin>
    <link rel="dns-prefetch" href="https://app.trysoro.com">
    <link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="dns-prefetch" href="https://www.googletagmanager.com">

    <!-- Critical above-the-fold CSS (inlined, ~1.5KB) — prevents FOUC
         while the main stylesheet is still being downloaded. -->
    <style>
    :root{--c-bg:#f8fafc;--c-ink:#0f172a;--c-muted:#475569;--c-brand:#ea580c;--c-brand-700:#c2410c;--c-card:#fff;--c-border:#e2e8f0}
    *,*::before,*::after{box-sizing:border-box}
    html,body{margin:0;padding:0}
    body{background:var(--c-bg);color:var(--c-ink);font-family:"Plus Jakarta Sans",ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif;line-height:1.55;-webkit-font-smoothing:antialiased}
    img{max-width:100%;height:auto}
    a{color:inherit}
    .container{width:100%;max-width:1280px;margin-left:auto;margin-right:auto;padding-left:1rem;padding-right:1rem}
    h1,h2,h3{margin:0 0 .75rem;line-height:1.15}
    h1{font-size:clamp(2rem,4vw,3rem);font-weight:800;color:#0f172a}
    h2{font-size:clamp(1.25rem,2.4vw,1.75rem);font-weight:700}
    p{margin:0 0 1rem;color:var(--c-muted)}
    .bg-white{background:var(--c-card)}
    .rounded-lg{border-radius:.5rem}
    .shadow{box-shadow:0 1px 3px 0 rgba(0,0,0,.08),0 1px 2px 0 rgba(0,0,0,.05)}
    .skeleton-shell{min-height:320px;background:linear-gradient(90deg,#f1f5f9 0%,#e2e8f0 50%,#f1f5f9 100%);background-size:200% 100%;animation:skel 1.4s linear infinite;border-radius:.5rem}
    @keyframes skel{0%{background-position:200% 0}100%{background-position:-200% 0}}
    </style>

    <!-- Favicon (low priority, loaded late by browser) -->
    <?php
    $faviconMtime = @filemtime(__DIR__ . '/../../../public/assets/images/favicon.png') ?: 1;
    $defaultFavicon = BASE_URL . '/assets/images/favicon.png?v=' . $faviconMtime;
    ?>
    <link rel="icon" type="image/png" href="<?php echo $defaultFavicon; ?>">
    <link rel="apple-touch-icon" href="<?php echo $defaultFavicon; ?>">
    <link rel="manifest" href="<?php echo BASE_URL; ?>/manifest.json">

    <?php
    require_once __DIR__ . '/../../core/Security/CSRFManager.php';
    $csrfToken = \App\Core\Security\CSRFManager::generateToken();
    ?>
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">

    <!-- Compiled Tailwind (preloaded, ~25KB gzipped) -->
    <?php echo getAssetManager()->getTailwindCssScript(); ?>
    <?php echo getAssetManager()->getGoogleFontsLink(); ?>

    <!-- Custom CSS (non-critical, can load deferred) -->
    <?php
    $customCssMtime = @filemtime(__DIR__ . '/../../../public/assets/css/style.css') ?: 1;
    ?>
    <link rel="preload" as="style" href="<?php echo BASE_URL; ?>/assets/css/style.css?v=<?php echo $customCssMtime; ?>" onload="this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css?v=<?php echo $customCssMtime; ?>"></noscript>

    <!-- CSRF token for JS consumers (tiny, inline to avoid extra blocking) -->
    <script>window.CSRF_TOKEN='<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>';</script>

    <!-- Cache manager: non-critical, defer so it never blocks FCP/LCP -->
    <script src="<?php echo BASE_URL; ?>/auto-cache-killer.js" defer></script>
    
    <!-- Icons Helper -->
    <?php require_once __DIR__ . '/../partials/icons.php'; ?>
    
    <?php
    // Add Restaurant structured data for public pages
    if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || (isset($_SESSION['role']) && $_SESSION['role'] === 'CUSTOMER')) {
        echo generateRestaurantStructuredData();
    }
    ?>
    
    <!-- Error Handler (load early, before other scripts) -->
    <script type="module">
        // Load error handler module first with error handling to prevent blocking
        try {
            import('<?php echo BASE_URL; ?>/assets/js/modules/error-handler.js').catch(function(error) {
                console.warn('Error handler module failed to load:', error);
                // Continue execution even if error handler fails
            });
        } catch (error) {
            console.warn('Error loading error handler:', error);
        }
    </script>
    
</head>
<body class="font-sans bg-slate-50">
 <a href="#main-content" class="q-skip-link">İçeriğe geç</a>
    <?php 
    // Check if we're using admin_layout (which has its own sidebar)
    // If so, don't show the header navigation bar
    $isAdminLayout = defined('USING_ADMIN_LAYOUT') || (isset($GLOBALS['using_admin_layout']) && $GLOBALS['using_admin_layout']);
    if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] && !$isAdminLayout): 
    ?>
    <!-- Navigation -->
    <nav class="bg-primary-600 text-white shadow-soft">
        <div class="container mx-auto px-3 sm:px-4">
            <div class="flex items-center justify-between h-14 sm:h-16">
                <a href="<?php echo BASE_URL; ?>/" class="flex items-center space-x-2 text-lg sm:text-xl font-bold hover:text-primary-100 transition-colors">
                    <?php echo icon_qr_code(['class' => 'w-5 h-5 sm:w-6 sm:h-6']); ?>
                    <span class="truncate"><?php echo getAppConfig()->getAppName(); ?></span>
                </a>
                
                <!-- Desktop Navigation -->
                <div class="hidden md:flex items-center space-x-1">
                    <?php if (hasPermissionForRole('dashboard.view') || hasPermissionForRole('menu.view')): ?>
                        <a href="<?php echo BASE_URL; ?>/admin" class="px-3 lg:px-4 py-2 rounded-lg hover:bg-primary-700 transition-colors flex items-center space-x-2 text-sm lg:text-base">
                            <?php echo icon_layout_grid(['class' => 'w-4 h-4 lg:w-5 lg:h-5']); ?>
                            <span class="hidden lg:inline"><?php echo t('nav.admin', 'Yönetim'); ?></span>
                        </a>
                        <a href="<?php echo BASE_URL; ?>/admin/menu" class="px-3 lg:px-4 py-2 rounded-lg hover:bg-primary-700 transition-colors flex items-center space-x-2 text-sm lg:text-base">
                            <?php echo icon_utensils(['class' => 'w-4 h-4 lg:w-5 lg:h-5']); ?>
                            <span class="hidden lg:inline"><?php echo t('nav.menu', 'Menu'); ?></span>
                        </a>
                        <a href="<?php echo BASE_URL; ?>/admin/orders" class="px-3 lg:px-4 py-2 rounded-lg hover:bg-primary-700 transition-colors flex items-center space-x-2 text-sm lg:text-base">
                            <?php echo icon_receipt(['class' => 'w-4 h-4 lg:w-5 lg:h-5']); ?>
                            <span class="hidden lg:inline"><?php echo t('nav.orders', 'Siparişler'); ?></span>
                        </a>
                        <a href="<?php echo BASE_URL; ?>/admin/tables" class="px-3 lg:px-4 py-2 rounded-lg hover:bg-primary-700 transition-colors flex items-center space-x-2 text-sm lg:text-base">
                            <?php echo icon_layout_grid(['class' => 'w-4 h-4 lg:w-5 lg:h-5']); ?>
                            <span class="hidden lg:inline"><?php echo t('nav.tables', 'Masalar'); ?></span>
                        </a>
                        <a href="<?php echo BASE_URL; ?>/pos" class="px-3 lg:px-4 py-2 rounded-lg hover:bg-primary-700 transition-colors flex items-center space-x-2 text-sm lg:text-base">
                            <?php echo icon_credit_card(['class' => 'w-4 h-4 lg:w-5 lg:h-5']); ?>
                            <span class="hidden lg:inline"><?php echo t('nav.pos', 'POS'); ?></span>
                        </a>
                    <?php endif; ?>
                    
                    <?php if (hasPermissionForRole('waiter.view') || hasRole('MANAGER')): ?>
                        <a href="<?php echo BASE_URL; ?>/waiter/dashboard" class="px-3 lg:px-4 py-2 rounded-lg hover:bg-primary-700 transition-colors flex items-center space-x-2 text-sm lg:text-base">
                            <?php echo icon_user(['class' => 'w-4 h-4 lg:w-5 lg:h-5']); ?>
                            <span class="hidden lg:inline"><?php echo t('nav.waiter', 'Waiter'); ?></span>
                        </a>
                    <?php endif; ?>
                    
                    <?php if (hasPermissionForRole('kitchen.view')): ?>
                        <a href="<?php echo BASE_URL; ?>/kitchen/dashboard" class="px-3 lg:px-4 py-2 rounded-lg hover:bg-primary-700 transition-colors flex items-center space-x-2 text-sm lg:text-base">
                            <?php echo icon_chef_hat(['class' => 'w-4 h-4 lg:w-5 lg:h-5']); ?>
                            <span class="hidden lg:inline"><?php echo t('nav.kitchen', 'Kitchen'); ?></span>
                        </a>
                    <?php endif; ?>
                    
                    <?php if (getCurrentUserRole() === 'CUSTOMER'): ?>
                        <a href="<?php echo BASE_URL; ?>/menu" class="px-3 lg:px-4 py-2 rounded-lg hover:bg-primary-700 transition-colors flex items-center space-x-2 text-sm lg:text-base">
                            <?php echo icon_utensils(['class' => 'w-4 h-4 lg:w-5 lg:h-5']); ?>
                            <span class="hidden lg:inline"><?php echo t('nav.menu', 'Menu'); ?></span>
                        </a>
                    <?php endif; ?>
                    
                    <!-- Language Selector -->
                    <?php 
                    $availableLanguages = $translationService->getAvailableLanguages();
                    if (count($availableLanguages) > 1): 
                    ?>
                    <div class="relative group ml-2 lg:ml-4">
                        <button class="flex items-center space-x-1 lg:space-x-2 px-2 lg:px-4 py-2 rounded-lg hover:bg-primary-700 transition-colors text-sm lg:text-base">
                            <span class="text-xs lg:text-sm font-bold"><?php echo strtoupper($currentLang); ?></span>
                            <svg class="w-3 h-3 lg:w-4 lg:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>
                        <div class="absolute right-0 mt-2 w-32 bg-white rounded-lg shadow-lg opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-50">
                            <?php 
                            $langIndex = 0;
                            $langCount = count($availableLanguages);
                            foreach ($availableLanguages as $langCode => $langInfo): 
                                $isFirst = ($langIndex === 0);
                                $isLast = ($langIndex === $langCount - 1);
                                $roundedClass = $isFirst && $isLast ? 'rounded-lg' : ($isFirst ? 'rounded-t-lg' : ($isLast ? 'rounded-b-lg' : ''));
                            ?>
                            <button onclick="changeLanguage('<?php echo htmlspecialchars($langCode); ?>')" class="w-full text-left px-3 lg:px-4 py-2 text-sm lg:text-base text-gray-800 hover:bg-gray-100 <?php echo $roundedClass; ?> <?php echo ($currentLang === $langCode) ? 'bg-primary-50 font-bold' : ''; ?>">
                                <?php echo htmlspecialchars($langInfo['flag'] ?? ''); ?> <?php echo htmlspecialchars($langInfo['name'] ?? strtoupper($langCode)); ?>
                            </button>
                            <?php 
                            $langIndex++;
                            endforeach; 
                            ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- User Menu -->
                    <div class="relative group ml-2 lg:ml-4">
                        <button class="flex items-center space-x-1 lg:space-x-2 px-2 lg:px-4 py-2 rounded-lg hover:bg-primary-700 transition-colors text-sm lg:text-base">
                            <?php echo icon_user(['class' => 'w-4 h-4 lg:w-5 lg:h-5']); ?>
                            <span class="hidden xl:inline truncate max-w-[100px]"><?php echo htmlspecialchars($_SESSION['username'] ?? t('common.user', 'Kullanıcı')); ?></span>
                            <svg class="w-3 h-3 lg:w-4 lg:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>
                        <div class="absolute right-0 mt-2 w-40 lg:w-48 bg-white rounded-lg shadow-lg opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-50">
                            <a href="<?php echo BASE_URL; ?>/profile" class="block px-3 lg:px-4 py-2 text-sm lg:text-base text-gray-800 hover:bg-gray-100 rounded-t-lg"><?php echo t('nav.profile', 'Profil'); ?></a>
                            <a href="<?php echo BASE_URL; ?>/logout" class="block px-3 lg:px-4 py-2 text-sm lg:text-base text-gray-800 hover:bg-gray-100 rounded-b-lg flex items-center space-x-2">
                                <?php echo icon_log_out(['class' => 'w-3 h-3 lg:w-4 lg:h-4']); ?>
                                <span><?php echo t('nav.logout', 'Çıkış Yap'); ?></span>
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Mobile Menu Button -->
                <button id="mobile-menu-button" class="md:hidden p-2 rounded-lg hover:bg-primary-700 active:opacity-70 transition-colors">
                    <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                    </svg>
                </button>
            </div>
            
            <!-- Mobile Navigation -->
            <div id="mobile-menu" class="hidden md:hidden pb-3 sm:pb-4">
                <?php if (hasPermissionForRole('dashboard.view') || hasPermissionForRole('menu.view')): ?>
                    <a href="<?php echo BASE_URL; ?>/admin" class="block px-3 sm:px-4 py-2 text-sm sm:text-base rounded-lg hover:bg-primary-700 transition-colors"><?php echo t('nav.admin', 'Admin'); ?></a>
                    <a href="<?php echo BASE_URL; ?>/admin/menu" class="block px-3 sm:px-4 py-2 text-sm sm:text-base rounded-lg hover:bg-primary-700 transition-colors"><?php echo t('nav.menu', 'Menu'); ?></a>
                    <a href="<?php echo BASE_URL; ?>/admin/orders" class="block px-3 sm:px-4 py-2 text-sm sm:text-base rounded-lg hover:bg-primary-700 transition-colors"><?php echo t('nav.orders', 'Orders'); ?></a>
                    <a href="<?php echo BASE_URL; ?>/admin/tables" class="block px-3 sm:px-4 py-2 text-sm sm:text-base rounded-lg hover:bg-primary-700 transition-colors"><?php echo t('nav.tables', 'Tables'); ?></a>
                    <a href="<?php echo BASE_URL; ?>/pos" class="block px-3 sm:px-4 py-2 text-sm sm:text-base rounded-lg hover:bg-primary-700 transition-colors"><?php echo t('nav.pos', 'POS'); ?></a>
                <?php endif; ?>
                
                <?php if (hasPermissionForRole('waiter.view') || hasRole('MANAGER')): ?>
                    <a href="<?php echo BASE_URL; ?>/waiter/dashboard" class="block px-3 sm:px-4 py-2 text-sm sm:text-base rounded-lg hover:bg-primary-700 transition-colors"><?php echo t('nav.waiter'); ?></a>
                <?php endif; ?>
                
                <?php if (hasPermissionForRole('kitchen.view')): ?>
                    <a href="<?php echo BASE_URL; ?>/kitchen/dashboard" class="block px-3 sm:px-4 py-2 text-sm sm:text-base rounded-lg hover:bg-primary-700 transition-colors"><?php echo t('nav.kitchen'); ?></a>
                <?php endif; ?>
                
                <?php if (getCurrentUserRole() === 'CUSTOMER'): ?>
                    <a href="<?php echo BASE_URL; ?>/menu" class="block px-3 sm:px-4 py-2 text-sm sm:text-base rounded-lg hover:bg-primary-700 transition-colors"><?php echo t('nav.menu'); ?></a>
                <?php endif; ?>
                
                <a href="<?php echo BASE_URL; ?>/profile" class="block px-3 sm:px-4 py-2 text-sm sm:text-base rounded-lg hover:bg-primary-700 transition-colors"><?php echo t('nav.profile'); ?></a>
                <div class="border-t border-primary-500 my-1"></div>
                <?php 
                $availableLanguages = $translationService->getAvailableLanguages();
                foreach ($availableLanguages as $langCode => $langInfo): 
                ?>
                <button onclick="changeLanguage('<?php echo htmlspecialchars($langCode); ?>')" class="w-full text-left px-3 sm:px-4 py-2 text-sm sm:text-base rounded-lg hover:bg-primary-700 transition-colors <?php echo ($currentLang === $langCode) ? 'bg-primary-500 font-bold' : ''; ?>">
                    <?php echo htmlspecialchars($langInfo['flag'] ?? ''); ?> <?php echo htmlspecialchars($langInfo['name'] ?? strtoupper($langCode)); ?>
                </button>
                <?php endforeach; ?>
                <a href="<?php echo BASE_URL; ?>/logout" class="block px-3 sm:px-4 py-2 text-sm sm:text-base rounded-lg hover:bg-primary-700 transition-colors"><?php echo t('nav.logout', 'Çıkış Yap'); ?></a>
            </div>
        </div>
    </nav>
    <?php endif; ?>
    
    <script>
    function changeLanguage(lang) {
        // Show loading indicator
        const buttons = document.querySelectorAll('[onclick*="changeLanguage"]');
        buttons.forEach(btn => {
            btn.disabled = true;
            btn.style.opacity = '0.5';
        });
        
        const csrfToken = window.CSRF_TOKEN || (typeof csrf_token !== 'undefined' ? csrf_token : '');
        fetch('<?php echo BASE_URL; ?>/api/change-language', {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': csrfToken
            },
            credentials: 'same-origin',
            body: JSON.stringify({ language: lang })
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                // Reload page to apply language changes
                window.location.reload();
            } else {
                if (window.NotificationManager) {
                    window.NotificationManager.error('Dil değiştirilemedi: ' + (data.error || 'Bilinmeyen hata'));
                }
                // Re-enable buttons
                buttons.forEach(btn => {
                    btn.disabled = false;
                    btn.style.opacity = '1';
                });
            }
        })
        .catch(error => {
            console.error('Language change error:', error);
            if (window.NotificationManager) {
                window.NotificationManager.error('Dil değiştirme sırasında bir hata oluştu: ' + error.message);
            }
            // Re-enable buttons
            buttons.forEach(btn => {
                btn.disabled = false;
                btn.style.opacity = '1';
            });
        });
    }
    </script>

    <main class="min-h-screen">
        <!-- Flash Messages -->
        <?php
        require_once __DIR__ . '/../../helpers/functions.php';
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
