<?php
if (!defined('BASE_URL')) {
    require_once __DIR__ . '/../../config/config.php';
}
if (!function_exists('getAppConfig')) {
    require_once __DIR__ . '/../../helpers/functions.php';
}

$appName = 'Qordy';
try { $appName = htmlspecialchars(getAppConfig()->getAppName()); } catch (\Exception $e) {}
$baseUrl = defined('BASE_URL') ? BASE_URL : '';
$page = $page ?? [];
$pageTitle = htmlspecialchars($page['title'] ?? 'Sayfa');
$content = $page['content'] ?? '';
$metaDesc = htmlspecialchars($page['meta_description'] ?? '');
$updatedAt = !empty($page['updated_at']) ? date('d.m.Y', strtotime($page['updated_at'])) : '';
$version = $page['version'] ?? 1;
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="designer" content="Pofuduk Dijital Medya ve Yazılım Limited Şirketi — İlker Aydoğdu — pofudukdijital.com">
    <title><?php echo $pageTitle; ?> - <?php echo $appName; ?></title>
    <?php if ($metaDesc): ?>
    <meta name="description" content="<?php echo $metaDesc; ?>">
    <?php endif; ?>
    <link rel="icon" type="image/png" href="<?php echo $baseUrl; ?>/assets/images/favicon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo $baseUrl; ?>/public/assets/landing/css/custom.css">
    <style>
        body { font-family: 'Inter', system-ui, sans-serif; background: #f8fafc; color: #0f172a; margin: 0; }
        .legal-header { background: #0f172a; color: #fff; padding: 2rem 0; }
        .legal-header .container { max-width: 900px; margin: 0 auto; padding: 0 1.5rem; }
        .legal-header h1 { font-size: 2rem; font-weight: 800; letter-spacing: -0.03em; margin: 0 0 0.5rem; }
        .legal-meta { font-size: 0.875rem; color: #94a3b8; display: flex; gap: 1.5rem; flex-wrap: wrap; }
        .legal-body { max-width: 900px; margin: 0 auto; padding: 2.5rem 1.5rem 4rem; }
        .legal-content { background: #fff; border-radius: 16px; padding: 2.5rem; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,.04); }
        .legal-content h2 { font-size: 1.35rem; font-weight: 700; margin: 2rem 0 0.75rem; color: #0f172a; }
        .legal-content h3 { font-size: 1.1rem; font-weight: 700; margin: 1.5rem 0 0.5rem; color: #1e293b; }
        .legal-content p { line-height: 1.8; color: #475569; margin-bottom: 1rem; }
        .legal-content ul, .legal-content ol { line-height: 1.8; color: #475569; margin-bottom: 1rem; padding-left: 1.5rem; }
        .legal-content li { margin-bottom: 0.35rem; }
        .legal-content strong { color: #0f172a; }
        .legal-content a { color: #6366f1; text-decoration: underline; }
        .legal-nav { display: flex; align-items: center; justify-content: space-between; max-width: 900px; margin: 0 auto; padding: 1rem 1.5rem; }
        .legal-nav a { color: #64748b; text-decoration: none; font-size: 0.875rem; font-weight: 600; }
        .legal-nav a:hover { color: #6366f1; }
        .legal-nav img { height: 28px; }
        @media (max-width: 640px) {
            .legal-header h1 { font-size: 1.5rem; }
            .legal-content { padding: 1.5rem; }
        }
    </style>
</head>
<body>
    <nav class="legal-nav">
        <a href="<?php echo $baseUrl; ?>/">
            <img src="<?php echo $baseUrl; ?>/assets/images/logo.png" alt="<?php echo $appName; ?>">
        </a>
        <a href="<?php echo $baseUrl; ?>/">← Ana Sayfa</a>
    </nav>

    <div class="legal-header">
        <div class="container">
            <h1><?php echo $pageTitle; ?></h1>
            <div class="legal-meta">
                <?php if ($updatedAt): ?>
                <span>Son güncelleme: <?php echo $updatedAt; ?></span>
                <?php endif; ?>
                <span>Versiyon: <?php echo $version; ?>.0</span>
            </div>
        </div>
    </div>

    <div class="legal-body">
        <div class="legal-content">
            <?php echo $content; ?>
        </div>
    </div>

    <footer class="footer" style="margin-top: 0;">
        <div class="container" style="max-width: 900px;">
            <div class="footer-bottom" style="border-top: none; padding-top: 0;">
                <p class="footer-copyright">
                    &copy; <?php echo date('Y'); ?> <?php echo $appName; ?>. Tüm hakları saklıdır.
                    Bir <a href="https://pofudukdijital.com" target="_blank" style="color: var(--gray-300, #94a3b8);">Pofuduk Dijital</a> ürünüdür.
                </p>
                <div class="footer-legal">
                    <a href="<?php echo $baseUrl; ?>/">Ana Sayfa</a>
                    <a href="<?php echo $baseUrl; ?>/#contact">İletişim</a>
                </div>
            </div>
        </div>
    </footer>
</body>
</html>
