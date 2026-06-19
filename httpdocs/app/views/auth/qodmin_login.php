<?php
// Ensure BASE_URL is defined
if (!defined('BASE_URL')) {
    require_once __DIR__ . '/../../config/config.php';
}
// Ensure helper functions are loaded
if (!function_exists('getAppConfig')) {
    require_once __DIR__ . '/../../helpers/functions.php';
}
if (!function_exists('generateCSRFToken')) {
    require_once __DIR__ . '/../../helpers/functions.php';
}

$appName = 'Qordy';
try {
    $appName = htmlspecialchars(getAppConfig()->getAppName());
} catch (\Exception $e) {
    $appName = 'Qordy';
}
?>
<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $appName; ?> - Super Admin Girişi</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }
        .animate-float {
            animation: float 3s ease-in-out infinite;
        }
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }
        .animate-shake {
            animation: shake 0.5s;
        }
    </style>
</head>
<body>
    <div class="min-h-screen flex items-center justify-center bg-[#f8fafc] p-2 sm:p-4 md:p-6 overflow-hidden">
        <div class="w-full max-w-xs sm:max-w-sm md:max-w-md transition-all duration-300" id="loginContainer">
            <div class="text-center mb-4 sm:mb-6 md:mb-8">
                <div class="w-14 h-14 sm:w-16 sm:h-16 md:w-20 md:h-20 bg-white shadow-xl rounded-xl sm:rounded-2xl md:rounded-3xl flex items-center justify-center mx-auto mb-3 sm:mb-4 md:mb-5 animate-float p-2 sm:p-3 md:p-4">
                    <img src="https://pofudukdijital.com/wp-content/uploads/2023/11/cropped-icon-1-192x192.png" alt="Logo" class="w-full h-full object-contain">
                </div>
                <h1 class="text-xl sm:text-2xl md:text-3xl font-black text-slate-900 tracking-tighter"><?php echo $appName; ?></h1>
                <p class="text-slate-400 font-bold mt-1 sm:mt-2 uppercase tracking-[0.2em] text-[7px] sm:text-[8px] md:text-[9px]">Super Admin Girişi</p>
            </div>
            
            <form method="POST" action="<?php echo BASE_URL; ?>/qodmin/login" id="loginForm">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                
                <?php
                // Get flash messages from ToastNotificationService
                require_once __DIR__ . '/../../helpers/functions.php';
                $toastService = getToastNotificationService();
                $flashMessages = $toastService->getFlashMessages();
                ?>
                
                <?php if (!empty($flashMessages)): ?>
                    <div id="flashMessagesContainer" class="mb-3 sm:mb-4 space-y-2">
                        <?php if (isset($flashMessages['error'])): ?>
                            <div class="bg-red-50 border border-red-200 text-red-700 px-3 sm:px-4 py-2 sm:py-3 rounded-lg text-xs sm:text-sm">
                                <?php echo htmlspecialchars($flashMessages['error']); ?>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($flashMessages['warning'])): ?>
                            <div class="bg-yellow-50 border border-yellow-200 text-yellow-700 px-3 sm:px-4 py-2 sm:py-3 rounded-lg text-xs sm:text-sm">
                                <?php echo htmlspecialchars($flashMessages['warning']); ?>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($flashMessages['success'])): ?>
                            <div class="bg-green-50 border border-green-200 text-green-700 px-3 sm:px-4 py-2 sm:py-3 rounded-lg text-xs sm:text-sm">
                                <?php echo htmlspecialchars($flashMessages['success']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <div class="bg-white rounded-xl sm:rounded-2xl shadow-xl p-4 sm:p-5 md:p-6 space-y-4 sm:space-y-5">
                    <div>
                        <label for="email" class="block text-xs sm:text-sm font-bold text-slate-700 mb-2">E-posta Adresi</label>
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            required 
                            autocomplete="email"
                            class="w-full px-3 sm:px-4 py-2 sm:py-3 border-2 border-slate-200 rounded-lg sm:rounded-xl focus:border-slate-900 focus:outline-none transition-colors text-sm sm:text-base"
                            placeholder="ornek@email.com"
                        >
                    </div>
                    
                    <div>
                        <label for="password" class="block text-xs sm:text-sm font-bold text-slate-700 mb-2">Şifre</label>
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            required 
                            autocomplete="current-password"
                            class="w-full px-3 sm:px-4 py-2 sm:py-3 border-2 border-slate-200 rounded-lg sm:rounded-xl focus:border-slate-900 focus:outline-none transition-colors text-sm sm:text-base"
                            placeholder="••••••••"
                        >
                    </div>
                    
                    <button 
                        type="submit" 
                        class="w-full bg-slate-900 hover:bg-slate-800 text-white font-bold py-3 sm:py-4 px-4 sm:px-6 rounded-lg sm:rounded-xl transition-colors text-sm sm:text-base shadow-lg"
                    >
                        Giriş Yap
                    </button>
                </div>
            </form>
            
            <div class="mt-8 sm:mt-10 md:mt-12 text-center">
                <div class="flex items-center justify-center mb-3 sm:mb-4">
                    <img src="https://pofudukdijital.com/wp-content/uploads/2023/11/logo1.svg" alt="Pofuduk Dijital Logo" class="h-6 sm:h-7 md:h-8 opacity-60">
                </div>
                <p class="text-[8px] sm:text-[9px] md:text-[10px] text-slate-400 font-medium">
                    © <?php echo date('Y'); ?> Pofuduk Dijital. Tüm hakları saklıdır.
                </p>
            </div>
        </div>
    </div>

    <script>
        <?php if (!empty($flashMessages) && (isset($flashMessages['error']) || isset($flashMessages['warning']))): ?>
        // Show error animation
        setTimeout(() => {
            const container = document.getElementById('loginContainer');
            container.classList.add('animate-shake');
            setTimeout(() => {
                container.classList.remove('animate-shake');
            }, 500);
        }, 100);
        <?php endif; ?>
    </script>
</body>
</html>
