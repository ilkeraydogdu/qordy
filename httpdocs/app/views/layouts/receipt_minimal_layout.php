<?php
/**
 * Minimal layout for receipt view – no sidebar, single focused card.
 * Used for /receipt/{id} so the page opens as a clean “popup style” and can be embedded in iframe.
 */
$GLOBALS['using_admin_layout'] = true;
if (!headers_sent()) {
    header('X-Robots-Tag: noindex, nofollow', true);
}
$pageTitle = $title ?? 'Fiş - Qordy';
$baseUrl = defined('BASE_URL') ? BASE_URL : '';
require_once __DIR__ . '/../../core/Security/CSRFManager.php';
$csrfToken = \App\Core\Security\CSRFManager::generateToken();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <script>window.CSRF_TOKEN = '<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>';</script>
    <script src="<?php echo $baseUrl; ?>/auto-cache-killer.js"></script>
    <script src="<?php echo $baseUrl; ?>/assets/js/admin/admin-layout-config.js"></script>
    <?php echo getAssetManager()->getTailwindCssScript(); ?>
    <?php echo getAssetManager()->getGoogleFontsLink(); ?>
    <link rel="stylesheet" href="<?php echo $baseUrl; ?>/assets/css/admin-layout.css">
    <link rel="icon" href="<?php echo $baseUrl; ?>/assets/images/favicon.png">
    <style>
        body { min-height: 100vh; }
        @media print {
            .receipt-minimal-close, .no-print-receipt { display: none !important; }
            body { background: #fff !important; }
        }
    </style>
</head>
<body class="bg-slate-100/90 min-h-screen flex items-start justify-center p-4 sm:p-6 print:p-0">
    <div class="w-full max-w-4xl mx-auto">
        <?php echo $content ?? ''; ?>
    </div>
    <script>
        (function() {
            if (window.parent !== window && window.postMessage) {
                window.addEventListener('message', function(e) {
                    if (e.data === 'receipt-close') window.parent.postMessage('receipt-close', '*');
                });
            }
        })();
    </script>
</body>
</html>
