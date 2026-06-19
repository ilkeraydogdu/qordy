<!DOCTYPE html>
<html lang="<?php echo getCurrentLanguage(); ?>">
<head>
    <?php require_once __DIR__ . '/../partials/google_analytics.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#f8fafc">
    <?php
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $isSubdomain = !in_array($host, ['qordy.com', 'www.qordy.com']);
    $publicPrefix = $isSubdomain ? '' : '/public';
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $currentBaseUrl = $protocol . '://' . $host;
    $faviconUrl = $currentBaseUrl . $publicPrefix . '/assets/images/favicon.png';
    $assetBase = $currentBaseUrl . $publicPrefix;
    $cssV = static function (string $file): int {
        $path = dirname(__DIR__, 3) . '/public/assets/css/' . $file;
        return is_file($path) ? (int) @filemtime($path) : 1;
    };
    ?>
    <link rel="icon" type="image/png" href="<?php echo $faviconUrl; ?>">
    <link rel="apple-touch-icon" href="<?php echo $faviconUrl; ?>">
    <title><?php
        $companyName = $company_name ?? null;
        $appName = $companyName ? htmlspecialchars($companyName) : getAppConfig()->getAppName();
        echo $appName;
    ?> - Personel Girişi</title>
    <?php echo getAssetManager()->getGoogleFontsLink(); ?>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($assetBase . '/assets/css/tokens.css?v=' . $cssV('tokens.css'), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars($assetBase . '/assets/css/admin-components.css?v=' . $cssV('admin-components.css'), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars($assetBase . '/assets/css/staff-login.css?v=' . $cssV('staff-login.css'), ENT_QUOTES, 'UTF-8'); ?>">
    <style>
        .animate-shake { animation: shake 0.4s cubic-bezier(.36,.07,.19,.97) both; }
        @keyframes shake {
            10%, 90% { transform: translate3d(-1px, 0, 0); }
            20%, 80% { transform: translate3d(2px, 0, 0); }
            30%, 50%, 70% { transform: translate3d(-4px, 0, 0); }
            40%, 60% { transform: translate3d(4px, 0, 0); }
        }
    </style>
</head>
<body class="q-staff-login" id="pageBody">
   <main class="q-staff-login__main" id="main-content">
 <div class="q-staff-login__card" id="loginContainer">
            <div class="q-staff-login__brand">
 <?php
 $displayCompany = $company_name ?? null;
 $logoPath = $business_logo_path ?? null;
 $defaultLogo = $currentBaseUrl . $publicPrefix . '/assets/images/logo.png';
 $logoUrl = $logoPath ? $currentBaseUrl . $publicPrefix . $logoPath : null;
 ?>
 <?php if ($logoUrl): ?>
 <div class="q-staff-login__logo">
 <img src="<?php echo htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8'); ?>"
 alt="<?php echo htmlspecialchars($displayCompany ?: 'Logo', ENT_QUOTES, 'UTF-8'); ?>"
 data-default-logo="<?php echo htmlspecialchars($defaultLogo, ENT_QUOTES, 'UTF-8'); ?>"
 onerror="this.onerror=null;this.src=this.dataset.defaultLogo;">
 </div>
 <?php else: ?>
 <div class="q-staff-login__logo">
 <span class="q-staff-login__logo-fallback"><?php echo htmlspecialchars(mb_strtoupper(mb_substr($displayCompany ?: 'Q', 0, 1, 'UTF-8'), 'UTF-8'), ENT_QUOTES, 'UTF-8'); ?></span>
 </div>
 <?php endif; ?>
 <h1 class="q-staff-login__title"><?php echo $displayCompany ? htmlspecialchars($displayCompany) : htmlspecialchars(getAppConfig()->getAppName()); ?></h1>
 <p class="q-staff-login__subtitle">Personel Girişi</p>
 </div>

            <?php
            $currentHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
            $loginActionUrl = $protocol . '://' . $currentHost . '/login';
            ?>
            <form method="POST" action="<?php echo htmlspecialchars($loginActionUrl, ENT_QUOTES, 'UTF-8'); ?>" id="loginForm" hidden>
                <input type="hidden" name="pin" id="pinInput" value="">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            </form>

            <?php
            require_once __DIR__ . '/../../helpers/functions.php';
            $toastService = getToastNotificationService();
            $flashMessages = $toastService->getFlashMessages();

            $urlErrorCode = trim($_GET['err'] ?? $_GET['reason'] ?? '');
            if ($urlErrorCode !== '' && !isset($flashMessages['error'])) {
                $urlErrorMap = [
                    'pin_required'        => 'Lütfen PIN kodunuzu giriniz.',
                    'invalid_pin'         => 'Geçersiz PIN. Lütfen tekrar deneyin.',
                    'invalid_pin_format'  => 'PIN yalnızca rakamlardan oluşmalı ve 4-8 haneli olmalıdır.',
                    'invalid_pin_tenant'  => 'Geçersiz PIN veya bu PIN bu işletmeye ait değil.',
                    'pin_wrong_tenant'    => 'Bu PIN bu işletmeye ait değil. Lütfen doğru işletme adresinden giriş yapın.',
                    'pin_already_active'  => 'Bu PIN şu anda başka bir cihazda aktif. Lütfen önce diğer cihazdan çıkış yapın.',
                    'pin_in_use'          => 'Bu PIN başka bir cihazda kullanılıyor. Lütfen diğer cihazdan çıkış yapın.',
                    'business_inactive'   => 'İşletmeniz pasife alınmıştır. Lütfen yöneticinizle iletişime geçin.',
                    'business_suspended'  => 'İşletmenizin aboneliği askıya alınmıştır. Lütfen yöneticinizle iletişime geçin.',
                    'staff_inactive'      => 'Personel hesabınız pasif durumdadır. Lütfen yöneticinizle iletişime geçin.',
                    'role_missing'        => 'Giriş başarılı ancak rol bilgisi bulunamadı. Lütfen yöneticinizle iletişime geçin.',
                    'invalid_table'       => 'Geçersiz masa bilgisi.',
 'session_lost' => 'Lütfen tekrar giriş yapın.',
'logout'              => 'Başarıyla çıkış yaptınız.',
                ];
                $safeCode = preg_replace('/[^a-z0-9_]/', '', strtolower($urlErrorCode));
                if (isset($urlErrorMap[$safeCode])) {
                    if ($safeCode === 'logout') {
                        $flashMessages['success'] = $urlErrorMap[$safeCode];
                    } else {
                        $flashMessages['error'] = $urlErrorMap[$safeCode];
                    }
                }
            }
            ?>

            <?php if (!empty($flashMessages)): ?>
                <?php
                require_once __DIR__ . '/../../helpers/translations.php';
                $translationService = getTranslationService();
                ?>
                <div class="q-staff-login__flash" id="flashMessagesContainer">
                    <?php if (isset($flashMessages['error'])): ?>
                        <?php
                        $errorMsg = $flashMessages['error'];
                        if (strpos($errorMsg, '.') !== false && strpos($errorMsg, ' ') === false) {
                            $translated = $translationService->translate($errorMsg);
                            $errorMsg = $translated !== null ? $translated : $errorMsg;
                        }
                        ?>
                        <div class="q-staff-login__flash-item q-text-status-danger q-bg-status-danger-soft animate-shake">
                            <?php echo htmlspecialchars($errorMsg, ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                    <?php endif; ?>
                    <?php if (isset($flashMessages['warning'])): ?>
                        <?php
                        $warningMsg = $flashMessages['warning'];
                        if (strpos($warningMsg, '.') !== false && strpos($warningMsg, ' ') === false) {
                            $translated = $translationService->translate($warningMsg);
                            $warningMsg = $translated !== null ? $translated : $warningMsg;
                        }
                        ?>
                        <div class="q-staff-login__flash-item q-text-status-warning q-bg-status-warning-soft">
                            <?php echo htmlspecialchars($warningMsg, ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                    <?php endif; ?>
                    <?php if (isset($flashMessages['success'])): ?>
                        <?php
                        $successMsg = $flashMessages['success'];
                        if (strpos($successMsg, '.') !== false && strpos($successMsg, ' ') === false) {
                            $translated = $translationService->translate($successMsg);
                            $successMsg = $translated !== null ? $translated : $successMsg;
                        }
                        ?>
                        <div class="q-staff-login__flash-item q-text-status-success q-bg-status-success-soft">
                            <?php echo htmlspecialchars($successMsg, ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                    <?php endif; ?>
                    <?php if (isset($flashMessages['info'])): ?>
                        <?php
                        $infoMsg = $flashMessages['info'];
                        if (strpos($infoMsg, '.') !== false && strpos($infoMsg, ' ') === false) {
                            $translated = $translationService->translate($infoMsg);
                            $infoMsg = $translated !== null ? $translated : $infoMsg;
                        }
                        ?>
                        <div class="q-staff-login__flash-item q-text-status-info q-bg-status-info-soft">
                            <?php echo htmlspecialchars($infoMsg, ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="q-staff-login__pin-dots" aria-hidden="true">
                <?php for ($i = 0; $i < 4; $i++): ?>
                    <div id="pinDot<?php echo $i; ?>" class="q-staff-login__pin-dot"></div>
                <?php endfor; ?>
            </div>

            <div class="q-staff-login__keypad">
                <?php for ($num = 1; $num <= 9; $num++): ?>
                    <button type="button" onclick="handleNumberClick('<?php echo $num; ?>')" class="q-staff-login__key"><?php echo $num; ?></button>
                <?php endfor; ?>
                <button type="button" onclick="clearPin()" class="q-staff-login__key q-staff-login__key--action">Temizle</button>
                <button type="button" onclick="handleNumberClick('0')" class="q-staff-login__key">0</button>
                <button type="button" onclick="deletePin()" class="q-staff-login__key q-staff-login__key--action" aria-label="Son rakamı sil">
                    <?php echo icon_arrow_left(['class' => 'w-5 h-5']); ?>
                </button>
            </div>

           
 </div>
 </main>
<script>
        let pin = '';
        const maxPinLength = 4;
        const pageBody = document.getElementById('pageBody');

        function updatePinDots() {
            for (let i = 0; i < 4; i++) {
                const dot = document.getElementById('pinDot' + i);
                dot.classList.toggle('is-filled', pin.length > i);
            }
        }

        function handleNumberClick(num) {
            if (pin.length >= maxPinLength) {
                return;
            }
            pin += num;
            document.getElementById('pinInput').value = pin;
            updatePinDots();

            if (pin.length === maxPinLength) {
                document.getElementById('loginContainer').classList.add('animate-shake');
                setTimeout(function () {
                    document.getElementById('loginForm').submit();
                }, 150);
            }
        }

        function clearPin() {
            pin = '';
            document.getElementById('pinInput').value = '';
            updatePinDots();
        }

        function deletePin() {
            if (pin.length === 0) {
                return;
            }
            pin = pin.slice(0, -1);
            document.getElementById('pinInput').value = pin;
            updatePinDots();
        }

        window.addEventListener('DOMContentLoaded', function () {
updatePinDots();

        <?php if (!empty($flashMessages) && (isset($flashMessages['error']) || isset($flashMessages['warning']))): ?>
        setTimeout(function () {
            document.getElementById('loginContainer').classList.add('animate-shake');
        }, 100);
        <?php endif; ?>
    });
    </script>
    <style>.hidden { display: none !important; }</style>
</body>
</html>
