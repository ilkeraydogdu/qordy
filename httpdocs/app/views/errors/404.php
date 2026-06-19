<?php
// Safe initialization with fallbacks (modernized 2026-06-11)
$lang = 'tr';
$baseUrl = '/';
$fontFamily = 'system-ui, -apple-system, sans-serif';
$backgroundColor = '#fafaf9'; // stone-50 for consistency

try {
 if (!defined('ERROR_PAGE_LOADING') && !defined('BASE_URL') && file_exists(__DIR__ . '/../../config/config.php')) {
 require_once __DIR__ . '/../../config/config.php';
 }
 if (defined('BASE_URL')) {
 $baseUrl = BASE_URL;
 } else {
 $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
 $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
 $scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
 if ($scriptDir === '/') { $scriptDir = ''; }
 $baseUrl = $protocol . '://' . $host . $scriptDir;
 }
} catch (\Exception $e) {
 $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
 $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
 $baseUrl = $protocol . '://' . $host;
}

try {
 if (session_status() === PHP_SESSION_ACTIVE) {
 $lang = $_SESSION['lang'] ?? $_SESSION['language'] ?? 'tr';
 }
} catch (\Exception $e) { $lang = 'tr'; }

try {
 if (!defined('ERROR_PAGE_LOADING') && file_exists(__DIR__ . '/../../helpers/translations.php')) {
 require_once __DIR__ . '/../../helpers/translations.php';
 if (function_exists('getCurrentLanguage')) {
 $lang = getCurrentLanguage();
 }
 }
} catch (\Exception $e) { /* fallback */ }

try {
 if (!defined('ERROR_PAGE_LOADING') && file_exists(__DIR__ . '/../../services/DesignSystem.php')) {
 require_once __DIR__ . '/../../services/DesignSystem.php';
 if (class_exists('\App\Services\DesignSystem')) {
 $designSystem = \App\Services\DesignSystem::getInstance();
 $fontFamily = $designSystem->getFont('sans') ?? $fontFamily;
 $backgroundColor = $designSystem->getBackground() ?? $backgroundColor;
 }
 }
} catch (\Exception $e) {
 $designSystem = null;
}
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($lang); ?>">
<head>
 <meta charset="UTF-8">
 <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, viewport-fit=cover">
 <title><?php
 try {
 $title = function_exists('t') ? t('errors.404.title') : '404';
 echo ($title === 'errors.404.title' || empty($title)) ? '404' : htmlspecialchars($title);
 } catch (\Exception $e) { echo '404'; }
 ?> — <?php
 try {
 $heading = function_exists('t') ? t('errors.404.heading') : 'Not Found';
 echo ($heading === 'errors.404.heading' || empty($heading)) ? ($lang === 'en' ? 'Not Found' : 'Sayfa Bulunamadı') : htmlspecialchars($heading);
 } catch (\Exception $e) { echo ($lang === 'en') ? 'Not Found' : 'Sayfa Bulunamadı'; }
 ?></title>
 <?php
 // Try loading assets the modern way
 try {
 if (function_exists('getAssetManager')) {
 echo getAssetManager()->getTailwindCssScript();
 echo getAssetManager()->getGoogleFontsLink();
 }
 } catch (\Exception $e) { /* fallback */ }
 ?>
 <style>
 body {
 font-family: <?php echo htmlspecialchars($fontFamily); ?>;
 background-color: <?php echo htmlspecialchars($backgroundColor); ?>;
 }
 </style>
 <?php if ($designSystem !== null): ?>
 <?php echo $designSystem->getAnimationsCSS(); ?>
 <?php echo $designSystem->getTailwindConfigScript(); ?>
 <?php endif; ?>
</head>
<body class="flex flex-col min-h-screen">
 <!-- Minimal Header -->
 <header class="py-6 px-4 flex justify-center">
 <a href="<?php echo htmlspecialchars($baseUrl); ?>" class="flex items-center gap-2 group">
 <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-orange-500 to-amber-500 flex items-center justify-center shadow-lg shadow-orange-500/20 group-hover:scale-105 transition-transform">
 <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
 <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
 </svg>
 </div>
 <span class="text-xl font-black text-slate-900 tracking-tight">Qordy</span>
 </a>
 </header>

 <!-- Main Content -->
 <main class="flex-1 flex items-center justify-center px-4 py-12">
 <div class="text-center max-w-md mx-auto">
 <!-- Large 404 with animated number -->
 <div class="mb-6">
 <div class="inline-flex items-center justify-center w-32 h-32 rounded-full bg-gradient-to-br from-slate-100 to-slate-50 mb-6">
 <span class="text-7xl font-black text-slate-300">404</span>
 </div>
 </div>

 <h1 class="text-3xl md:text-4xl font-black text-slate-900 mb-3">
 <?php
 try {
 $heading = function_exists('t') ? t('errors.404.heading') : '';
 echo ($heading === 'errors.404.heading' || empty($heading)) ? 'Sayfa Bulunamadı' : htmlspecialchars($heading);
 } catch (\Exception $e) { echo 'Sayfa Bulunamadı'; }
 ?>
 </h1>
 <p class="text-lg text-slate-500 mb-8 max-w-sm mx-auto">
 <?php
 try {
 $message = function_exists('t') ? t('errors.404.message') : '';
 if ($message === 'errors.404.message' || empty($message)) {
 echo ($lang === 'en') ? 'The page you are looking for could not be found or has been moved.' : 'Aradığınız sayfa bulunamadı veya taşınmış olabilir.';
 } else {
 echo htmlspecialchars($message);
 }
 } catch (\Exception $e) {
 echo ($lang === 'en') ? 'The page you are looking for could not be found or has been moved.' : 'Aradığınız sayfa bulunamadı veya taşınmış olabilir.';
 }
 ?>
 </p>

 <!-- Action Buttons -->
 <div class="flex flex-col sm:flex-row gap-3 justify-center">
 <?php $homeUrlToUse = $homeUrl ?? ($baseUrl . '/'); ?>
 <?php if (empty($homeUrlToUse) || $homeUrlToUse === '/') { $homeUrlToUse = $baseUrl . '/'; } ?>

 <a href="<?php echo htmlspecialchars($homeUrlToUse); ?>"
 class="inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl bg-slate-900 text-white font-black hover:bg-slate-800 transition-all shadow-lg shadow-slate-900/20 hover:shadow-xl hover:-translate-y-0.5">
 <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
 <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
 </svg>
 <?php echo ($lang === 'en') ? 'Go Home' : 'Ana Sayfaya Dön'; ?>
 </a>
 <button onclick="history.back()"
 class="inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl bg-white text-slate-700 font-black border border-slate-200 hover:bg-slate-50 transition-all">
 <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
 <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
 </svg>
 <?php echo ($lang === 'en') ? 'Go Back' : 'Geri'; ?>
 </button>
 </div>
 </div>
 </main>

 <!-- Minimal Footer -->
 <footer class="py-6 px-4 text-center">
 <p class="text-sm text-slate-400">
 &copy; <?php echo date('Y'); ?> Qordy.
 <?php echo ($lang === 'en') ? 'All rights reserved.' : 'Tüm hakları saklıdır.'; ?>
 </p>
 </footer>
</body>
</html>