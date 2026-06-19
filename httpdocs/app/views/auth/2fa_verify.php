<?php
/**
 * Web 2FA challenge screen.
 *
 * Receives from the controller:
 *   - $methods        array of enrolled + admin-allowed methods
 *                     (any of 'totp', 'whatsapp', 'email', 'sms')
 *   - $currentMethod  the method we pre-selected
 */
$methods       = isset($methods) && is_array($methods) ? $methods : [];
$currentMethod = $currentMethod ?? ($_SESSION['2fa_method'] ?? 'email');

$methodMeta = [
    'totp'     => ['label' => 'Authenticator', 'icon' => 'key',       'subtitle' => 'Authenticator uygulamanızdaki 6 haneli kodu girin'],
    'whatsapp' => ['label' => 'WhatsApp',      'icon' => 'whatsapp',  'subtitle' => 'WhatsApp üzerinden gönderilen 6 haneli kodu girin'],
    'email'    => ['label' => 'E-posta',       'icon' => 'mail',      'subtitle' => 'E-posta ile gönderilen 6 haneli kodu girin'],
    'sms'      => ['label' => 'SMS',           'icon' => 'phone',     'subtitle' => 'SMS ile gönderilen 6 haneli kodu girin'],
];
$activeMeta = $methodMeta[$currentMethod] ?? $methodMeta['email'];
?>
<!DOCTYPE html>
<html lang="<?php echo getCurrentLanguage(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, viewport-fit=cover">
    <meta name="theme-color" content="#ffffff">
    <title><?php echo getAppConfig()->getAppName(); ?> - 2FA Doğrulama</title>
    <?php echo getAssetManager()->getTailwindCssScript(); ?>
    <?php echo getAssetManager()->getGoogleFontsLink(); ?>
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #f8fafc;
            -webkit-tap-highlight-color: transparent;
            overflow-x: hidden;
        }
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        .animate-slide-up { animation: slideUp 0.4s cubic-bezier(0.16, 1, 0.3, 1); }
        @keyframes slideUp {
            0% { transform: translateY(100%); opacity: 0; }
            100% { transform: translateY(0); opacity: 1; }
        }
        .keypad-button { transition: all 0.1s ease; }
        .keypad-button:active { transform: scale(0.9); background-color: #f1f5f9; }

        .method-chip {
            transition: all 0.18s ease;
            white-space: nowrap;
        }
        .method-chip[data-active="1"] {
            background-color: #f97316;
            color: #fff;
            border-color: #f97316;
            box-shadow: 0 6px 16px -6px rgba(249, 115, 22, 0.55);
        }
    </style>
    <script>
        if (typeof tailwind !== 'undefined' && tailwind && typeof tailwind === 'object') {
            tailwind.config = {
                theme: {
                    extend: {
                        fontFamily: { mono: ['Space Mono', 'monospace'] },
                        colors: {
                            primary: { 50: '#fff7ed', 100: '#ffedd5', 200: '#fed7aa', 300: '#fdba74', 400: '#fb923c', 500: '#f97316', 600: '#ea580c', 700: '#c2410c', 800: '#9a3412', 900: '#7c2d12' }
                        },
                        boxShadow: { 'soft': '0 10px 40px -10px rgba(0,0,0,0.05)', 'keypad': '0 4px 20px -2px rgba(0,0,0,0.05)' }
                    }
                }
            };
        }
    </script>
</head>
<body>
    <div class="min-h-screen flex items-center justify-center bg-[#f8fafc] p-3 sm:p-4 md:p-6 overflow-hidden">
        <div class="w-full max-w-sm transition-all duration-300 animate-slide-up">
            <div class="text-center mb-6 sm:mb-8 lg:mb-10">
                <div class="w-16 h-16 sm:w-20 sm:h-20 lg:w-24 lg:h-24 bg-white shadow-2xl rounded-2xl sm:rounded-[30px] lg:rounded-[40px] flex items-center justify-center mx-auto mb-4 sm:mb-6 lg:mb-8">
                    <?php 
                    require_once __DIR__ . '/../partials/icons.php';
                    echo icon_shield(['class' => 'w-8 h-8 sm:w-10 sm:h-10 lg:w-12 lg:h-12 text-orange-500']); 
                    ?>
                </div>
                <h1 class="text-2xl sm:text-3xl lg:text-4xl font-black text-slate-900 tracking-tighter">İki Adımlı Doğrulama</h1>
                <p id="methodSubtitle" class="text-slate-500 font-semibold mt-3 text-xs sm:text-sm leading-snug px-2">
                    <?php echo htmlspecialchars($activeMeta['subtitle']); ?>
                </p>
            </div>

            <?php if (count($methods) > 1): ?>
            <div class="mb-6 -mx-1 px-1 overflow-x-auto no-scrollbar">
                <div class="flex items-center gap-2" id="methodChips">
                    <?php foreach ($methods as $m):
                        $meta = $methodMeta[$m] ?? ['label' => strtoupper($m), 'icon' => 'key'];
                    ?>
                        <button type="button"
                                class="method-chip px-4 py-2 rounded-full border-2 border-slate-200 bg-white text-slate-700 text-xs sm:text-[13px] font-bold"
                                data-method="<?php echo htmlspecialchars($m); ?>"
                                data-active="<?php echo $m === $currentMethod ? '1' : '0'; ?>"
                                onclick="switchMethod('<?php echo htmlspecialchars($m); ?>')">
                            <?php echo htmlspecialchars($meta['label']); ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <form method="POST" action="<?php echo BASE_URL; ?>/auth/2fa/verify" id="verifyForm">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="code" id="codeInput" value="">
                <input type="hidden" name="method" id="methodInput" value="<?php echo htmlspecialchars($currentMethod); ?>">
            </form>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-xl text-red-700 text-xs sm:text-sm font-bold text-center">
                    <?php echo htmlspecialchars($_SESSION['error']); ?>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['success'])): ?>
                <div class="mb-4 p-3 bg-green-50 border border-green-200 rounded-xl text-green-700 text-xs sm:text-sm font-bold text-center">
                    <?php echo htmlspecialchars($_SESSION['success']); ?>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>
            
            <div class="flex justify-center gap-3 sm:gap-4 lg:gap-6 mb-8 sm:mb-10 lg:mb-12">
                <?php for ($i = 0; $i < 6; $i++): ?>
                    <div id="codeDot<?php echo $i; ?>" class="w-4 h-4 sm:w-5 sm:h-5 lg:w-6 lg:h-6 rounded-full border-2 sm:border-[3px] lg:border-[4px] transition-all duration-300 border-slate-200"></div>
                <?php endfor; ?>
            </div>
            
            <div class="bg-white rounded-3xl sm:rounded-[40px] lg:rounded-[50px] p-6 sm:p-8 lg:p-10 shadow-2xl border border-slate-50">
                <div class="grid grid-cols-3 gap-3 sm:gap-4 lg:gap-6">
                    <?php for ($i = 1; $i <= 9; $i++): ?>
                        <button type="button" onclick="enterDigit('<?php echo $i; ?>')" class="keypad-button aspect-square bg-slate-50 hover:bg-slate-100 active:bg-slate-200 rounded-xl sm:rounded-2xl lg:rounded-[30px] font-black text-xl sm:text-2xl lg:text-3xl text-slate-900 shadow-keypad">
                            <?php echo $i; ?>
                        </button>
                    <?php endfor; ?>
                    <button type="button" onclick="clearCode()" class="keypad-button aspect-square bg-red-50 hover:bg-red-100 active:bg-red-200 rounded-xl sm:rounded-2xl lg:rounded-[30px] font-black text-base sm:text-lg lg:text-xl text-red-600 shadow-keypad">
                        Sil
                    </button>
                    <button type="button" onclick="enterDigit('0')" class="keypad-button aspect-square bg-slate-50 hover:bg-slate-100 active:bg-slate-200 rounded-xl sm:rounded-2xl lg:rounded-[30px] font-black text-xl sm:text-2xl lg:text-3xl text-slate-900 shadow-keypad">
                        0
                    </button>
                    <button type="button" onclick="submitCode()" class="keypad-button aspect-square bg-orange-500 hover:bg-orange-600 active:bg-orange-700 rounded-xl sm:rounded-2xl lg:rounded-[30px] font-black text-base sm:text-lg lg:text-xl text-white shadow-keypad">
                        ✓
                    </button>
                </div>
            </div>
            
            <div class="mt-6 sm:mt-8 text-center">
                <button type="button" id="resendBtn" onclick="resendCode()" class="text-slate-400 hover:text-slate-600 font-bold text-xs sm:text-sm transition-colors">
                    Kodu Tekrar Gönder
                </button>
            </div>
        </div>
    </div>
    
    <script>
        let code = '';
        const maxLength = 6;
        const baseUrl = '<?php echo BASE_URL; ?>';
        let currentMethod = <?php echo json_encode($currentMethod); ?>;
        const subtitleMap = <?php echo json_encode(array_column($methodMeta, 'subtitle', null) ?: []); ?>;
        const csrfToken = <?php echo json_encode(generateCSRFToken()); ?>;

        function enterDigit(digit) {
            if (code.length < maxLength) {
                code += digit;
                updateDots();
                if (code.length === maxLength) setTimeout(() => submitCode(), 300);
            }
        }
        function clearCode() { code = ''; updateDots(); }

        function updateDots() {
            for (let i = 0; i < maxLength; i++) {
                const dot = document.getElementById('codeDot' + i);
                if (!dot) continue;
                if (i < code.length) {
                    dot.classList.add('bg-orange-500', 'border-orange-500');
                    dot.classList.remove('border-slate-200');
                } else {
                    dot.classList.remove('bg-orange-500', 'border-orange-500');
                    dot.classList.add('border-slate-200');
                }
            }
        }

        function submitCode() {
            if (code.length !== maxLength) {
                if (window.NotificationManager) {
                    window.NotificationManager.warning('Lütfen 6 haneli kodu girin.');
                }
                return;
            }
            document.getElementById('codeInput').value = code;
            document.getElementById('methodInput').value = currentMethod;
            document.getElementById('verifyForm').submit();
        }

        async function switchMethod(method) {
            if (method === currentMethod) return;
            try {
                const res = await fetch(`${baseUrl}/auth/2fa/switch`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-CSRF-Token': csrfToken
                    },
                    body: `method=${encodeURIComponent(method)}&csrf_token=${encodeURIComponent(csrfToken)}`
                });
                const data = await res.json();
                if (!data.success) {
                    if (window.NotificationManager) {
                        window.NotificationManager.error(data.message || 'Yöntem değiştirilemedi');
                    }
                    return;
                }
                currentMethod = data.method || method;
                document.getElementById('methodInput').value = currentMethod;
                document.querySelectorAll('#methodChips .method-chip').forEach(el => {
                    el.dataset.active = el.dataset.method === currentMethod ? '1' : '0';
                });
                const sub = document.getElementById('methodSubtitle');
                if (sub && subtitleMap[currentMethod]) sub.textContent = subtitleMap[currentMethod];
                const resendBtn = document.getElementById('resendBtn');
                if (resendBtn) resendBtn.style.display = currentMethod === 'totp' ? 'none' : '';
                clearCode();
                if (window.NotificationManager && data.sent) {
                    window.NotificationManager.success('Yeni kod gönderildi.');
                }
            } catch (e) {
                if (window.NotificationManager) {
                    window.NotificationManager.error('Ağ hatası: ' + e.message);
                }
            }
        }

        async function resendCode() {
            if (currentMethod === 'totp') {
                if (window.NotificationManager) {
                    window.NotificationManager.info('Authenticator uygulaması kodu kendi üretir, sunucudan gönderilmez.');
                }
                return;
            }
            try {
                const res = await fetch(`${baseUrl}/auth/2fa/resend`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-CSRF-Token': csrfToken
                    },
                    body: `csrf_token=${encodeURIComponent(csrfToken)}`
                });
                const data = await res.json();
                if (window.NotificationManager) {
                    if (data.success) {
                        window.NotificationManager.success('Kod tekrar gönderildi.');
                    } else {
                        window.NotificationManager.error(data.message || 'Kod gönderilemedi');
                    }
                }
            } catch (e) {
                if (window.NotificationManager) {
                    window.NotificationManager.error('Kod gönderilirken bir hata oluştu.');
                }
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            const resendBtn = document.getElementById('resendBtn');
            if (resendBtn && currentMethod === 'totp') resendBtn.style.display = 'none';
        });

        document.addEventListener('keydown', function(e) {
            if (e.key >= '0' && e.key <= '9') enterDigit(e.key);
            else if (e.key === 'Backspace') clearCode();
            else if (e.key === 'Enter') submitCode();
        });
    </script>
</body>
</html>
