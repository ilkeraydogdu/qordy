<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yetkisiz Erişim - <?php echo getAppConfig()->getAppName(); ?></title>
    <?php echo getAssetManager()->getTailwindCssScript(); ?>
    <?php echo getAssetManager()->getGoogleFontsLink(); ?>
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .shadow-soft {
            box-shadow: 0 2px 8px -2px rgba(0, 0, 0, 0.05), 0 4px 16px -4px rgba(0, 0, 0, 0.05);
        }
    </style>
</head>
<body class="bg-[#f8fafc] min-h-screen flex items-center justify-center p-4 sm:p-6 md:p-8">
<?php
// Get user information from session
\App\Core\SessionManager::ensureSession();
$isAuthenticated = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
$username = $_SESSION['username'] ?? 'Misafir';
$role = $_SESSION['role'] ?? '';
$roleId = $_SESSION['role_id'] ?? null;

// Get role-specific redirect URL
$homeUrl = BASE_URL . '/login';
$roleName = 'Kullanıcı';

if ($isAuthenticated && !empty($role)) {
    // Normalize role code
    $normalizedRole = strtoupper(str_replace('ROLE_', '', trim($role)));
    
    // Role-based redirect mapping (same as AuthController)
    $roleRedirects = [
        'MANAGER' => '/admin/dashboard',
        'WAITER' => '/waiter/dashboard',
        'KITCHEN' => '/kitchen/dashboard',
        'CASHIER' => '/pos/dashboard',
        'CUSTOMER' => '/menu'
    ];
    
    // Role names for display
    $roleNames = [
        'MANAGER' => t('roles.manager', 'Yönetici'),
        'WAITER' => t('roles.waiter', 'Garson'),
        'KITCHEN' => t('roles.kitchen', 'Mutfak'),
        'CASHIER' => t('roles.cashier', 'Kasiyer'),
        'CUSTOMER' => t('roles.customer', 'Müşteri')
    ];
    
    $homeUrl = BASE_URL . ($roleRedirects[$normalizedRole] ?? '/login');
    $roleName = $roleNames[$normalizedRole] ?? $normalizedRole;
}

$normalizedRole = strtoupper(str_replace('ROLE_', '', trim($role)));
?>
    <!-- Unauthorized Page Content -->
    <div class="w-full max-w-2xl animate-slide-up">
        <div class="bg-white rounded-2xl shadow-soft border border-slate-100 p-6 sm:p-8 md:p-10 lg:p-12">
            <!-- Icon -->
            <div class="flex justify-center mb-6">
                <div class="inline-flex items-center justify-center w-20 h-20 sm:w-24 sm:h-24 bg-orange-50 rounded-2xl">
                    <svg class="w-10 h-10 sm:w-12 sm:h-12 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                </div>
            </div>

            <!-- Title -->
            <div class="text-center mb-6">
                <h1 class="text-2xl sm:text-3xl lg:text-4xl font-black text-slate-900 tracking-tighter mb-3">
                    <?php echo t('errors.unauthorized.title', 'Yetkisiz Erişim'); ?>
                </h1>
                <p class="text-slate-500 font-bold uppercase text-[10px] sm:text-xs tracking-widest">
                    <?php echo t('errors.unauthorized.subtitle', 'Erişim Reddedildi'); ?>
                </p>
            </div>
            
            <!-- Message -->
            <p class="text-center text-base sm:text-lg text-slate-600 mb-6 sm:mb-8 font-semibold">
                <?php echo t('errors.unauthorized.message', 'Bu sayfaya erişim izniniz bulunmamaktadır.'); ?>
            </p>

            <?php if ($isAuthenticated): ?>
            <!-- User Info Card -->
            <div class="bg-slate-50 border border-slate-100 rounded-2xl p-5 sm:p-6 mb-6">
                <div class="flex items-center justify-center gap-4">
                    <div class="w-12 h-12 sm:w-14 sm:h-14 bg-slate-900 rounded-xl flex items-center justify-center text-white font-black text-lg sm:text-xl">
                        <?php echo strtoupper(substr($username, 0, 1)); ?>
                    </div>
                    <div class="text-left">
                        <p class="font-black text-slate-900 text-base sm:text-lg mb-1"><?php echo htmlspecialchars($username); ?></p>
                        <span class="inline-flex items-center px-3 py-1 rounded-xl text-xs sm:text-sm font-black bg-blue-100 text-blue-700 border border-blue-200">
                            <?php echo htmlspecialchars($roleName); ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Role-based Suggestions -->
            <div class="bg-orange-50 border border-orange-100 rounded-2xl p-5 sm:p-6 mb-6">
                <div class="flex gap-4">
                    <svg class="w-5 h-5 sm:w-6 sm:h-6 text-orange-600 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                    </svg>
                    <div class="flex-1">
                        <p class="font-black text-orange-900 mb-2 text-sm sm:text-base">
                            <?php echo t('errors.unauthorized.suggestion_title', 'Önerilen Sayfa'); ?>
                        </p>
                        <p class="text-sm sm:text-base text-orange-800 font-semibold">
                            <?php 
                            $suggestions = [
                                'WAITER' => t('errors.unauthorized.suggestion.waiter', 'Garson rolünüzle <strong>Masalar</strong> sayfasına erişebilirsiniz.'),
                                'KITCHEN' => t('errors.unauthorized.suggestion.kitchen', 'Mutfak rolünüzle <strong>Sipariş Ekranı</strong>na erişebilirsiniz.'),
                                'CASHIER' => t('errors.unauthorized.suggestion.cashier', 'Kasiyer rolünüzle <strong>POS Ekranı</strong>na erişebilirsiniz.'),
                                'MANAGER' => t('errors.unauthorized.suggestion.manager', 'Bu sayfa için gerekli izniniz bulunmamaktadır. Lütfen sistem yöneticinizle iletişime geçin.'),
                                'CUSTOMER' => t('errors.unauthorized.suggestion.customer', 'Müşteri olarak <strong>Menü</strong> sayfasına erişebilirsiniz.')
                            ];
                            echo $suggestions[$normalizedRole] ?? t('errors.unauthorized.suggestion.default', 'Lütfen ana sayfanıza dönün veya sistem yöneticinizle iletişime geçin.');
                            ?>
                        </p>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <!-- Not Authenticated Message -->
            <div class="bg-orange-50 border border-orange-100 rounded-2xl p-5 sm:p-6 mb-6">
                <div class="flex gap-4">
                    <svg class="w-5 h-5 sm:w-6 sm:h-6 text-orange-600 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                    </svg>
                    <div class="flex-1">
                        <p class="font-black text-orange-900 mb-2 text-sm sm:text-base">
                            <?php echo t('errors.unauthorized.not_logged_in', 'Giriş Gerekli'); ?>
                        </p>
                        <p class="text-sm sm:text-base text-orange-800 font-semibold">
                            <?php echo t('errors.unauthorized.please_login', 'Bu sayfaya erişmek için lütfen giriş yapın.'); ?>
                        </p>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Action Buttons -->
            <div class="flex flex-col sm:flex-row gap-3 sm:gap-4 justify-center">
                <a 
                    href="<?php echo htmlspecialchars($homeUrl); ?>" 
                    class="inline-flex items-center justify-center px-6 py-3 sm:px-8 sm:py-4 bg-slate-900 hover:bg-slate-800 text-white rounded-xl font-black text-sm sm:text-base transition-all shadow-soft hover:shadow-xl"
                >
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                    </svg>
                    <?php echo $isAuthenticated ? t('navigation.go_home', 'Ana Sayfaya Dön') : t('auth.login', 'Giriş Yap'); ?>
                </a>
                
                <?php if (!$isAuthenticated): ?>
                <a 
                    href="<?php echo BASE_URL; ?>/menu" 
                    class="inline-flex items-center justify-center px-6 py-3 sm:px-8 sm:py-4 bg-white hover:bg-slate-50 text-slate-700 rounded-xl font-black text-sm sm:text-base transition-all border border-slate-200 shadow-sm hover:shadow-soft"
                >
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                    </svg>
                    <?php echo t('menu.view_menu', 'Menüyü Görüntüle'); ?>
                </a>
                <?php endif; ?>
            </div>

            <!-- Footer Help Text -->
            <p class="text-center text-xs sm:text-sm text-slate-400 mt-6 sm:mt-8 font-semibold">
                <?php echo t('errors.unauthorized.contact_admin', 'Erişim sorunu yaşıyorsanız, lütfen sistem yöneticinizle iletişime geçin.'); ?>
            </p>
        </div>
    </div>
</body>
</html>
