<?php
// Safe initialization with fallbacks
$lang = 'tr';
$baseUrl = '/';
$fontFamily = 'system-ui, -apple-system, sans-serif';
$backgroundColor = '#ffffff';

try {
    // Only require config if not already loaded and not in error page loading mode
    // Skip config.php entirely during error page rendering to prevent class redeclaration
    if (!defined('ERROR_PAGE_LOADING') && !defined('BASE_URL') && file_exists(__DIR__ . '/../../config/config.php')) {
        require_once __DIR__ . '/../../config/config.php';
    }
    if (defined('BASE_URL')) {
        $baseUrl = BASE_URL;
    } else {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
        if ($scriptDir === '/') {
            $scriptDir = '';
        }
        $baseUrl = $protocol . '://' . $host . $scriptDir;
    }
} catch (\Exception $e) {
    // Fallback BASE_URL
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $baseUrl = $protocol . '://' . $host;
}

try {
    if (session_status() === PHP_SESSION_ACTIVE) {
        $lang = $_SESSION['lang'] ?? $_SESSION['language'] ?? 'tr';
    }
} catch (\Exception $e) {
    $lang = 'tr';
}

try {
    // Only load helpers if not in error page loading mode to prevent class redeclaration
    if (!defined('ERROR_PAGE_LOADING') && file_exists(__DIR__ . '/../../helpers/translations.php')) {
        require_once __DIR__ . '/../../helpers/translations.php';
        if (function_exists('getCurrentLanguage')) {
            $lang = getCurrentLanguage();
        }
    }
} catch (\Exception $e) {
    // Use fallback language
}

try {
    // Only load DesignSystem if not in error page loading mode to prevent class redeclaration
    if (!defined('ERROR_PAGE_LOADING') && file_exists(__DIR__ . '/../../services/DesignSystem.php')) {
        require_once __DIR__ . '/../../services/DesignSystem.php';
        if (class_exists('\App\Services\DesignSystem')) {
            $designSystem = \App\Services\DesignSystem::getInstance();
            $fontFamily = $designSystem->getFont('sans') ?? $fontFamily;
            $backgroundColor = $designSystem->getBackground() ?? $backgroundColor;
        }
    }
} catch (\Exception $e) {
    // Use fallback values
}
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($lang); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, viewport-fit=cover">
    <title>500 - <?php echo ($lang === 'en') ? 'Server Error' : 'Sunucu Hatası'; ?></title>
    <?php echo getAssetManager()->getTailwindCssScript(); ?>
    <?php echo getAssetManager()->getGoogleFontsLink(); ?>
    <style>
        body {
            font-family: <?php echo htmlspecialchars($fontFamily); ?>;
            background-color: <?php echo htmlspecialchars($backgroundColor); ?>;
        }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen">
    <div class="text-center p-6">
        <div class="mb-8">
            <h1 class="text-8xl font-black text-slate-900 mb-4"><?php 
                try {
                    $title = t('errors.500.title');
                    echo ($title === 'errors.500.title' || $title === '500.title' || empty($title)) ? '500' : htmlspecialchars($title);
                } catch (\Exception $e) {
                    echo '500';
                }
            ?></h1>
            <h2 class="text-3xl font-black text-slate-700 mb-4"><?php 
                try {
                    $heading = t('errors.500.heading');
                    if ($heading === 'errors.500.heading' || $heading === '500.heading' || empty($heading)) {
                        $lang = getCurrentLanguage();
                        echo ($lang === 'en') ? 'Server Error' : 'Sunucu Hatası';
                    } else {
                        echo htmlspecialchars($heading);
                    }
                } catch (\Exception $e) {
                    $lang = isset($_SESSION['lang']) ? $_SESSION['lang'] : 'tr';
                    echo ($lang === 'en') ? 'Server Error' : 'Sunucu Hatası';
                }
            ?></h2>
            <p class="text-lg text-slate-500 mb-8 max-w-md mx-auto"><?php 
                try {
                    $message = t('errors.500.message');
                    if ($message === 'errors.500.message' || $message === '500.message' || empty($message)) {
                        $lang = getCurrentLanguage();
                        echo ($lang === 'en') ? 'An error occurred. Please try again later.' : 'Bir hata oluştu. Lütfen daha sonra tekrar deneyin.';
                    } else {
                        echo htmlspecialchars($message);
                    }
                } catch (\Exception $e) {
                    $lang = isset($_SESSION['lang']) ? $_SESSION['lang'] : 'tr';
                    echo ($lang === 'en') ? 'An error occurred. Please try again later.' : 'Bir hata oluştu. Lütfen daha sonra tekrar deneyin.';
                }
            ?></p>
            <?php 
            // Show detailed error in development mode OR for super admins
            // Get APP_ENV and APP_DEBUG from database instead of .env
            try {
                $settingsService = \App\Core\DependencyFactory::getSystemSettingsService();
                $isProduction = ($settingsService->getAppEnv() === 'production');
                $isDebug = $settingsService->getAppDebug();
            } catch (\Exception $e) {
                // Fallback to .env if database is not available
                $isProduction = (isset($_ENV['APP_ENV']) && $_ENV['APP_ENV'] === 'production');
                $isDebug = (isset($_ENV['APP_DEBUG']) && ($_ENV['APP_DEBUG'] === 'true' || $_ENV['APP_DEBUG'] === '1'));
            }
            
            // Check if user is super admin (from ErrorHandler or session)
            $isSuperAdmin = $isSuperAdmin ?? false;
            if (!$isSuperAdmin) {
                try {
                    \App\Core\SessionManager::ensureSession();
                    $sessionRole = \App\Core\SessionManager::get('role');
                    $isSuperAdminSession = \App\Core\SessionManager::get('is_super_admin') === true;
                    
                    if ($isSuperAdminSession) {
                        $isSuperAdmin = true;
                    } elseif ($sessionRole) {
                        $normalizedRole = strtoupper(trim($sessionRole));
                        $isSuperAdmin = ($normalizedRole === 'SUPER_ADMIN' || $normalizedRole === 'ROLE_SUPER_ADMIN' ||
                                       $normalizedRole === 'QODMIN' || $normalizedRole === 'ROLE_QODMIN');
                    }
                } catch (\Exception $e) {
                    // Ignore errors when checking super admin status
                }
            }
            
            // Use errorMessage instead of message to avoid conflict with t() function
            $displayError = $errorMessage ?? $message ?? '';
            // Show details if: not production OR debug mode OR super admin
            if (((!$isProduction || $isDebug) || $isSuperAdmin) && !empty($displayError)): 
            ?>
                <div class="mt-8 max-w-4xl mx-auto bg-red-50 border border-red-200 rounded-2xl p-6 text-left">
                    <h3 class="text-lg font-black text-red-900 mb-4">
                        Hata Detayları 
                        <?php if ($isSuperAdmin): ?>
                            <span class="text-xs bg-purple-500 text-white px-2 py-1 rounded">(Super Admin)</span>
                        <?php elseif (!$isProduction || $isDebug): ?>
                            <span class="text-xs bg-blue-500 text-white px-2 py-1 rounded">(Development Mode)</span>
                        <?php endif; ?>
                    </h3>
                    <pre class="text-sm text-red-800 bg-white p-4 rounded-lg overflow-auto border border-red-100 whitespace-pre-wrap"><?php echo htmlspecialchars($displayError); ?></pre>
                    <?php if (isset($errorFile) && !empty($errorFile)): ?>
                        <p class="text-sm text-red-700 mt-4"><strong>Dosya:</strong> <?php echo htmlspecialchars($errorFile); ?><?php if (isset($errorLine) && !empty($errorLine)): ?> (Satır: <?php echo htmlspecialchars($errorLine); ?>)<?php endif; ?></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="flex flex-col sm:flex-row gap-4 justify-center items-center">
            <?php
            // Use homeUrl from ErrorHandler if available, otherwise fallback to baseUrl
            $homeUrlToUse = $homeUrl ?? ($baseUrl . '/');
            // Ensure homeUrl is a valid URL
            if (empty($homeUrlToUse) || $homeUrlToUse === '/') {
                $homeUrlToUse = $baseUrl . '/';
            }
            ?>
            <a href="<?php echo htmlspecialchars($homeUrlToUse); ?>" class="inline-block bg-slate-900 text-white px-8 py-4 rounded-2xl font-black hover:bg-slate-800 transition-all shadow-xl">
                <?php 
                try {
                    $backHome = t('errors.500.backHome');
                    if ($backHome === 'errors.500.backHome' || $backHome === '500.backHome' || empty($backHome)) {
                        $lang = getCurrentLanguage();
                        echo ($lang === 'en') ? 'Back to Home' : 'Ana Sayfaya Dön';
                    } else {
                        echo htmlspecialchars($backHome);
                    }
                } catch (\Exception $e) {
                    $lang = isset($_SESSION['lang']) ? $_SESSION['lang'] : 'tr';
                    echo ($lang === 'en') ? 'Back to Home' : 'Ana Sayfaya Dön';
                }
                ?>
            </a>
            <button onclick="location.reload()" class="inline-block bg-slate-100 text-slate-700 px-8 py-4 rounded-2xl font-black hover:bg-slate-200 transition-all">
                <?php 
                // Direct fallback - don't rely on translation system for error pages
                $lang = 'tr';
                if (session_status() === PHP_SESSION_ACTIVE) {
                    $lang = $_SESSION['lang'] ?? $_SESSION['language'] ?? 'tr';
                }
                echo ($lang === 'en') ? 'Reload' : 'Yenile';
                ?>
            </button>
        </div>
    </div>
</body>
</html>

