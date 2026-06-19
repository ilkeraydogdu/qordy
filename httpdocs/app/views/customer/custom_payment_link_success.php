<?php
$link             = $link ?? null;
$package          = $package ?? null;
$paid             = $paid ?? false;
$mode             = $mode ?? '';
$canSetPassword   = $can_set_password ?? false;
$isLoggedIn       = $is_logged_in ?? false;
$csrf             = $csrf_token ?? '';
$appName = 'Qordy';
try { $appName = htmlspecialchars(getAppConfig()->getAppName()); } catch (\Throwable $e) {}
$baseUrl = defined('BASE_URL') ? BASE_URL : '';
$token = $link['token'] ?? '';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrf); ?>">
    <title>Ödeme Tamamlandı — <?php echo $appName; ?></title>
    <link rel="icon" type="image/png" href="<?php echo $baseUrl; ?>/assets/images/favicon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@700;800;900&display=swap" rel="stylesheet">
    <style>
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
        body{font-family:'Inter',system-ui,-apple-system,sans-serif;color:#0f172a;min-height:100vh;-webkit-font-smoothing:antialiased;
             background:radial-gradient(1000px 600px at 100% -10%,#eef2ff 0%,transparent 60%),radial-gradient(900px 500px at -10% 110%,#ecfeff 0%,transparent 60%),#f8fafc;}
        .nav{display:flex;align-items:center;justify-content:space-between;padding:1rem 1.5rem;border-bottom:1px solid rgba(15,23,42,.06);background:rgba(255,255,255,.6);backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px)}
        .brand{display:flex;align-items:center;gap:.6rem;font-weight:900;color:#0f172a;text-decoration:none}
        .brand img{height:28px}
        .shell{max-width:540px;margin:0 auto;padding:3rem 1.25rem}
        .card{background:#fff;border:1px solid rgba(15,23,42,.06);border-radius:24px;box-shadow:0 1px 2px rgba(15,23,42,.04),0 12px 40px -12px rgba(15,23,42,.12);padding:2.25rem 2rem;text-align:center}
        .ic{width:72px;height:72px;border-radius:50%;margin:0 auto 1.25rem;display:flex;align-items:center;justify-content:center;color:#fff;font-size:28px;font-weight:900;
            background:linear-gradient(135deg,#10b981,#059669);box-shadow:0 10px 28px -6px rgba(16,185,129,.5)}
        .ic svg{width:32px;height:32px}
        h1{font-family:'Plus Jakarta Sans',sans-serif;font-size:1.6rem;font-weight:900;color:#0f172a;letter-spacing:-.01em}
        .lead{margin-top:.6rem;color:#64748b;font-size:.92rem;line-height:1.55}
        .plan-chip{display:inline-flex;align-items:center;gap:.5rem;padding:.6rem 1rem;background:linear-gradient(180deg,#fafbff,#fff);border:1px solid #eef2ff;border-radius:12px;margin-top:1.1rem;font-size:.85rem}
        .plan-chip .label{color:#64748b;font-weight:600}
        .plan-chip .val{font-weight:800;color:#0f172a}
        .divider{height:1px;background:#eef2ff;margin:1.5rem 0}
        .section-title{font-family:'Plus Jakarta Sans',sans-serif;font-size:1rem;font-weight:800;color:#0f172a;text-align:left;margin-bottom:.5rem}
        .section-sub{font-size:.82rem;color:#64748b;text-align:left;line-height:1.55;margin-bottom:1rem}
        .field{text-align:left;margin-bottom:.85rem}
        .field label{display:block;font-size:.75rem;font-weight:700;color:#475569;margin-bottom:.35rem;text-transform:uppercase;letter-spacing:.05em}
        .field input{width:100%;padding:.85rem 1rem;border:1.5px solid #e2e8f0;border-radius:12px;font-family:inherit;font-size:.95rem;color:#0f172a;transition:all .15s ease;background:#fafbff}
        .field input:focus{outline:none;border-color:#6366f1;box-shadow:0 0 0 4px rgba(99,102,241,.1);background:#fff}
        .hint{font-size:.72rem;color:#94a3b8;margin-top:.3rem}
        .btn{display:inline-flex;align-items:center;justify-content:center;gap:.45rem;padding:.95rem 1.25rem;font-size:.95rem;font-weight:800;font-family:inherit;border:none;border-radius:12px;cursor:pointer;text-decoration:none;transition:all .18s ease;width:100%}
        .btn-primary{background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff;box-shadow:0 8px 22px -6px rgba(99,102,241,.55)}
        .btn-primary:hover{transform:translateY(-1px);box-shadow:0 12px 28px -6px rgba(99,102,241,.6)}
        .btn-dark{background:#0f172a;color:#fff}
        .btn-dark:hover{background:#1e293b}
        .alert{padding:.85rem 1rem;border-radius:12px;font-size:.85rem;line-height:1.5;margin-top:1rem;text-align:left}
        .alert-warn{background:#fffbeb;color:#92400e;border:1px solid #fde68a}
        .alert-info{background:#eff6ff;color:#1e40af;border:1px solid #bfdbfe}
        .help{margin-top:1rem;text-align:center;font-size:.75rem;color:#94a3b8}
        .help a{color:#6366f1;text-decoration:none;font-weight:600}
    </style>
</head>
<body>
<nav class="nav">
    <a class="brand" href="<?php echo $baseUrl; ?>/">
        <img src="<?php echo $baseUrl; ?>/assets/images/logo.png" alt="<?php echo $appName; ?>">
    </a>
    <span style="font-size:.78rem;color:#64748b;font-weight:600;">Tamamlandı</span>
</nav>

<div class="shell">
    <div class="card">
        <?php if (!$paid): ?>
            <div class="ic" style="background:linear-gradient(135deg,#f59e0b,#d97706);">
                <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M4.93 19h14.14a2 2 0 001.743-2.99l-7.07-12.24a2 2 0 00-3.486 0L3.187 16.01A2 2 0 004.93 19z"/></svg>
            </div>
            <h1>Ödeme Bulunamadı</h1>
            <p class="lead">Bu bağlantı için henüz tamamlanmış bir ödeme yok. Ödeme sayfasından tekrar deneyebilirsiniz.</p>
            <a href="<?php echo $baseUrl; ?>/pay/<?php echo htmlspecialchars($token); ?>" class="btn btn-dark" style="margin-top:1.5rem;">Ödeme Sayfasına Dön</a>
        <?php else: ?>
            <div class="ic">
                <svg fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            </div>
            <h1>Ödemeniz Başarılı</h1>
            <p class="lead">Aboneliğiniz aktifleştirildi.</p>

            <?php if ($package && $link): ?>
                <div class="plan-chip">
                    <span class="label">Paket</span>
                    <span class="val"><?php echo htmlspecialchars($package['name']); ?></span>
                    <span style="color:#e2e8f0;">·</span>
                    <span class="label">Süre</span>
                    <span class="val"><?php echo (int)$link['duration_months']; ?> ay</span>
                </div>
            <?php endif; ?>

            <?php if ($mode === 'new_customer' && $canSetPassword): ?>
                <div class="divider"></div>
                <div class="section-title">Hesabınızı Aktifleştirin</div>
                <div class="section-sub">Panelinize giriş yapmak için bir şifre belirleyin. Şifreniz en az 8 karakter olmalıdır.</div>

                <form method="POST" action="<?php echo $baseUrl; ?>/pay/<?php echo htmlspecialchars($token); ?>/activate" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                    <div class="field">
                        <label for="password">Yeni Şifre</label>
                        <input type="password" id="password" name="password" minlength="8" required autocomplete="new-password" placeholder="En az 8 karakter">
                    </div>
                    <div class="field">
                        <label for="password2">Şifreyi Tekrarla</label>
                        <input type="password" id="password2" name="password2" minlength="8" required autocomplete="new-password" placeholder="Şifrenizi tekrar girin">
                        <div class="hint" id="match-hint"></div>
                    </div>
                    <button type="submit" class="btn btn-primary" id="activate-btn">
                        <svg fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" width="16" height="16"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                        Hesabımı Aktifleştir ve Giriş Yap
                    </button>
                </form>

                <script>
                (function(){
                    var p1 = document.getElementById('password');
                    var p2 = document.getElementById('password2');
                    var hint = document.getElementById('match-hint');
                    var btn = document.getElementById('activate-btn');
                    function check(){
                        if (!p2.value) { hint.textContent=''; return; }
                        if (p1.value && p1.value === p2.value) {
                            hint.textContent = 'Şifreler uyuşuyor';
                            hint.style.color = '#10b981';
                        } else {
                            hint.textContent = 'Şifreler uyuşmuyor';
                            hint.style.color = '#ef4444';
                        }
                    }
                    p1.addEventListener('input', check);
                    p2.addEventListener('input', check);
                })();
                </script>

            <?php elseif ($mode === 'new_customer' && !$canSetPassword): ?>
                <div class="alert alert-info" style="margin-top:1.25rem;">
                    Aktivasyon oturumunuz bulunamadı. Hesabınızı oluşturmak için lütfen <a href="mailto:destek@qordy.com" style="color:#1e40af;font-weight:700;">destek@qordy.com</a> ile iletişime geçin — paylaşacağımız bağlantı ile şifrenizi belirleyebilirsiniz.
                </div>

            <?php elseif ($mode === 'existing_customer' && $isLoggedIn): ?>
                <a href="<?php echo $baseUrl; ?>/business/dashboard" class="btn btn-primary" style="margin-top:1.5rem;">
                    <svg fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" width="16" height="16"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                    Panelime Git
                </a>
                <p class="help">4 saniye içinde otomatik yönlendirileceksiniz.</p>
                <meta http-equiv="refresh" content="4;url=<?php echo $baseUrl; ?>/business/dashboard">
            <?php else: ?>
                <a href="<?php echo $baseUrl; ?>/login" class="btn btn-dark" style="margin-top:1.5rem;">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="16" height="16"><path stroke-linecap="round" stroke-linejoin="round" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/></svg>
                    Giriş Yap
                </a>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <div class="help">
        Yardıma mı ihtiyacınız var? <a href="mailto:destek@qordy.com">destek@qordy.com</a>
    </div>
</div>
</body>
</html>
