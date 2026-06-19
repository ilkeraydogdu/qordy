<?php
if (!defined('BASE_URL')) {
    require_once __DIR__ . '/../../config/config.php';
}
if (!function_exists('getAppConfig')) {
    require_once __DIR__ . '/../../helpers/functions.php';
}

$appName = 'Qordy';
try {
    $appName = htmlspecialchars(getAppConfig()->getAppName());
} catch (\Exception $e) {
    $appName = 'Qordy';
}

$baseUrl = defined('BASE_URL') ? BASE_URL : '';

$registerLegalPages = [];
try {
    $registerLegalPages = \App\Core\DependencyFactory::getLegalPageService()->getRegisterPages();
} catch (\Exception $e) {}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <?php require_once __DIR__ . '/../partials/google_analytics.php'; ?>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="designer" content="Pofuduk Dijital Medya ve Yazılım Limited Şirketi — İlker Aydoğdu — pofudukdijital.com">
    <meta name="description" content="<?php echo $appName; ?> - Ücretsiz hesap oluşturun">
    <title><?php echo $appName; ?> - Kayıt Ol</title>
    <link rel="icon" type="image/png" href="<?php echo $baseUrl; ?>/assets/images/favicon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --primary: #6366f1; --primary-dark: #4f46e5; --primary-light: #a5b4fc;
            --green: #22c55e; --green-bg: #f0fdf4; --green-border: #bbf7d0;
            --red: #ef4444; --yellow: #f59e0b;
            --g900: #0f172a; --g700: #334155; --g600: #475569; --g500: #64748b;
            --g400: #94a3b8; --g300: #cbd5e1; --g200: #e2e8f0; --g100: #f1f5f9; --g50: #f8fafc;
        }
        body { font-family: 'Inter', system-ui, -apple-system, sans-serif; background: var(--g50); color: var(--g900); line-height: 1.5; min-height: 100vh; -webkit-font-smoothing: antialiased; }

        .page { display: flex; min-height: 100vh; }
        .sidebar { display: none; flex: 1; background: linear-gradient(135deg, #6366f1 0%, #7c3aed 50%, #8b5cf6 100%); padding: 3rem; position: relative; overflow: hidden; }
        @media (min-width: 1024px) { .sidebar { display: flex; flex-direction: column; justify-content: center; } }
        .sidebar::before { content: ''; position: absolute; top: -15%; right: -10%; width: 400px; height: 400px; background: rgba(255,255,255,0.08); border-radius: 50%; }
        .sidebar::after { content: ''; position: absolute; bottom: -10%; left: -5%; width: 250px; height: 250px; background: rgba(255,255,255,0.05); border-radius: 50%; }
        .sb-content { position: relative; z-index: 2; color: white; max-width: 480px; }
        .sb-content h2 { font-size: 2.25rem; font-weight: 800; line-height: 1.2; margin-bottom: 0.75rem; letter-spacing: -0.02em; }
        .sb-content p { font-size: 1.05rem; opacity: 0.85; margin-bottom: 2.5rem; }
        .sb-feature { display: flex; align-items: center; gap: 1rem; margin-bottom: 1.25rem; }
        .sb-feature-icon { width: 44px; height: 44px; background: rgba(255,255,255,0.15); border-radius: 12px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; backdrop-filter: blur(4px); }
        .sb-feature-icon svg { width: 22px; height: 22px; stroke: white; fill: none; }
        .sb-feature-text { font-weight: 600; font-size: 0.95rem; }
        .sb-feature-sub { font-size: 0.825rem; opacity: 0.7; font-weight: 400; }
        .sb-quote { margin-top: 2.5rem; padding: 1.25rem 1.5rem; background: rgba(255,255,255,0.1); border-radius: 16px; border: 1px solid rgba(255,255,255,0.1); }
        .sb-quote p { font-style: italic; font-size: 0.925rem; opacity: 0.95; margin-bottom: 1rem; }
        .sb-quote-author { display: flex; align-items: center; gap: 0.75rem; }
        .sb-quote-avatar { width: 36px; height: 36px; background: rgba(255,255,255,0.25); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.8rem; }
        .sb-quote-name { font-weight: 600; font-size: 0.85rem; }
        .sb-quote-role { font-size: 0.75rem; opacity: 0.65; }

        .main { flex: 1; display: flex; flex-direction: column; justify-content: center; padding: 1.5rem; background: white; }
        @media (min-width: 640px) { .main { padding: 2.5rem; } }
        .form-wrap { width: 100%; max-width: 460px; margin: 0 auto; }

        .back-link { display: inline-flex; align-items: center; gap: 0.4rem; font-size: 0.8125rem; color: var(--g400); text-decoration: none; margin-bottom: 1.5rem; transition: color 0.2s; }
        .back-link:hover { color: var(--primary); }
        .back-link svg { width: 16px; height: 16px; }
        .logo-img { height: 36px; display: block; margin-bottom: 1.75rem; }
        .page-title { font-size: 1.625rem; font-weight: 800; letter-spacing: -0.02em; margin-bottom: 0.35rem; }
        .page-subtitle { color: var(--g500); font-size: 0.9rem; margin-bottom: 2rem; }

        .steps { display: flex; align-items: center; gap: 0; margin-bottom: 2rem; position: relative; }
        .step-item { flex: 1; display: flex; flex-direction: column; align-items: center; position: relative; z-index: 2; }
        .step-circle { width: 36px; height: 36px; border-radius: 50%; border: 2px solid var(--g200); background: white; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.8rem; color: var(--g400); transition: all 0.3s ease; position: relative; }
        .step-circle svg { width: 18px; height: 18px; display: none; }
        .step-label { font-size: 0.7rem; font-weight: 600; color: var(--g400); margin-top: 0.4rem; transition: color 0.3s; text-align: center; }
        .step-item.active .step-circle { border-color: var(--primary); background: var(--primary); color: white; box-shadow: 0 0 0 4px rgba(99,102,241,0.15); }
        .step-item.active .step-label { color: var(--primary); }
        .step-item.done .step-circle { border-color: var(--g400); background: var(--g500); color: white; }
        .step-item.done .step-circle span { display: none; }
        .step-item.done .step-circle svg { display: block; stroke: white; fill: none; }
        .step-item.done .step-label { color: var(--g500); }
        .step-line { position: absolute; top: 18px; left: calc(50% / 3 + 18px); right: calc(50% / 3 + 18px); height: 2px; background: var(--g200); z-index: 1; }
        .steps::before { content: ''; position: absolute; top: 18px; left: 16.66%; right: 16.66%; height: 2px; background: var(--g200); z-index: 1; }

        .flash-msg { padding: 0.875rem 1rem; border-radius: 10px; font-size: 0.85rem; font-weight: 500; margin-bottom: 1.25rem; display: flex; align-items: center; gap: 0.6rem; }
        .flash-error { background: #fef2f2; border: 1px solid #fecaca; color: #dc2626; }
        .flash-success { background: var(--green-bg); border: 1px solid var(--green-border); color: #16a34a; }

        .step-panel { display: none; animation: fadeSlide 0.35s ease; }
        .step-panel.visible { display: block; }
        @keyframes fadeSlide { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: translateY(0); } }

        .field { margin-bottom: 1rem; }
        .field-label { display: block; font-size: 0.8125rem; font-weight: 600; color: var(--g700); margin-bottom: 0.4rem; }
        .field-input { width: 100%; padding: 0.7rem 0.875rem; font-size: 0.9rem; font-family: inherit; border: 1.5px solid var(--g200); border-radius: 10px; background: var(--g50); color: var(--g900); transition: border-color 0.2s, box-shadow 0.2s; outline: none; }
        .field-input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(99,102,241,0.1); background: white; }
        .field-input::placeholder { color: var(--g400); }
        .field-input[readonly] { background: var(--g100); color: var(--g600); cursor: default; }
        .field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; }
        @media (max-width: 480px) { .field-row { grid-template-columns: 1fr; } }
        .field-hint { font-size: 0.7rem; color: var(--g400); margin-top: 0.3rem; }

        .verify-row { display: flex; gap: 0.5rem; align-items: stretch; }
        .verify-row .field-input { flex: 1; }

        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; padding: 0.7rem 1.25rem; font-size: 0.875rem; font-weight: 600; font-family: inherit; border: none; border-radius: 10px; cursor: pointer; transition: all 0.2s ease; text-decoration: none; white-space: nowrap; }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; transform: none !important; }
        .btn-primary { background: linear-gradient(135deg, #6366f1, #8b5cf6); color: white; box-shadow: 0 2px 8px rgba(99,102,241,0.3); }
        .btn-primary:hover:not(:disabled) { transform: translateY(-1px); box-shadow: 0 4px 16px rgba(99,102,241,0.4); }
        .btn-secondary { background: white; color: var(--g700); border: 1.5px solid var(--g200); }
        .btn-secondary:hover:not(:disabled) { border-color: var(--primary); color: var(--primary); }
        .btn-sm { padding: 0.55rem 1rem; font-size: 0.8125rem; border-radius: 8px; }
        .btn-full { width: 100%; }
        .btn-outline-primary { background: white; color: var(--primary); border: 1.5px solid var(--primary); }
        .btn-outline-primary:hover:not(:disabled) { background: rgba(99,102,241,0.06); }
        .btn svg { width: 18px; height: 18px; flex-shrink: 0; }

        .btn-row { display: flex; gap: 0.75rem; margin-top: 1.5rem; }
        .btn-row .btn { flex: 1; }
        .btn-row .btn-primary { flex: 2; }

        .wa-banner { display: flex; align-items: flex-start; gap: 0.75rem; padding: 0.75rem 1rem; background: var(--g50); border: 1px solid var(--g200); border-radius: 10px; margin-bottom: 1rem; }
        .wa-banner svg { width: 18px; height: 18px; flex-shrink: 0; margin-top: 2px; fill: var(--g500); }
        .wa-banner span { font-size: 0.8rem; color: var(--g600); line-height: 1.45; }

        .phone-wrap { display: flex; gap: 0.5rem; align-items: stretch; }
        .country-picker { width: 105px; flex-shrink: 0; position: relative; }
        .cp-btn { display: flex; align-items: center; gap: 5px; padding: 0.55rem 0.6rem; font-size: 0.8rem; font-weight: 600; border: 1.5px solid var(--g200); border-radius: 10px; background: var(--g50); cursor: pointer; width: 100%; font-family: inherit; color: var(--g700); transition: border-color 0.2s; height: 100%; }
        .cp-btn:hover { border-color: var(--g300); }
        .cp-btn:disabled { opacity: 0.6; cursor: default; pointer-events: none; }
        .cp-btn img { width: 20px; height: 15px; object-fit: cover; border-radius: 2px; }
        .cp-btn svg { width: 12px; height: 12px; color: var(--g400); margin-left: auto; }
        .cp-drop { position: absolute; top: 100%; left: 0; right: 0; margin-top: 4px; max-height: 240px; overflow-y: auto; background: white; border: 1.5px solid var(--g200); border-radius: 10px; box-shadow: 0 8px 24px rgba(0,0,0,0.12); z-index: 50; display: none; }
        .cp-drop.open { display: block; }
        .cp-item { display: flex; align-items: center; gap: 8px; padding: 0.5rem 0.75rem; font-size: 0.8rem; cursor: pointer; transition: background 0.15s; }
        .cp-item:hover { background: var(--g100); }
        .cp-item img { width: 20px; height: 15px; object-fit: cover; border-radius: 2px; flex-shrink: 0; }

        .badge-verified { display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.35rem 0.65rem; font-size: 0.75rem; font-weight: 600; color: var(--g600); background: var(--g100); border-radius: 6px; margin-top: 0.5rem; }
        .badge-verified svg { width: 14px; height: 14px; stroke: var(--g500); fill: none; }

        .code-block { margin-top: 0.5rem; }
        .code-msg { font-size: 0.7rem; margin-top: 0.25rem; color: var(--g500); }

        .pw-meter { margin-top: 0.5rem; }
        .pw-bar { height: 4px; background: var(--g200); border-radius: 4px; overflow: hidden; }
        .pw-fill { height: 100%; border-radius: 4px; transition: all 0.3s ease; width: 0; }
        .pw-reqs { margin-top: 0.5rem; display: grid; grid-template-columns: 1fr 1fr; gap: 0.15rem 1rem; }
        @media (max-width: 480px) { .pw-reqs { grid-template-columns: 1fr; } }
        .pw-req { display: flex; align-items: center; gap: 0.35rem; font-size: 0.725rem; color: var(--g400); transition: color 0.2s; }
        .pw-req svg { width: 13px; height: 13px; flex-shrink: 0; }
        .pw-req.ok { color: var(--green); }
        .pw-match { font-size: 0.75rem; margin-top: 0.3rem; font-weight: 500; }

        .terms-row { display: flex; align-items: flex-start; gap: 0.6rem; margin-bottom: 1.5rem; }
        .terms-row input[type=checkbox] { width: 18px; height: 18px; margin-top: 2px; accent-color: var(--primary); flex-shrink: 0; cursor: pointer; }
        .terms-row label { font-size: 0.825rem; color: var(--g600); line-height: 1.5; cursor: pointer; }
        .terms-row a { color: var(--primary); font-weight: 500; text-decoration: none; }
        .terms-row a:hover { text-decoration: underline; }

        .login-link { text-align: center; color: var(--g500); font-size: 0.85rem; margin-top: 2rem; }
        .login-link a { color: var(--primary); font-weight: 600; text-decoration: none; }
        .login-link a:hover { text-decoration: underline; }

        .spinner { display: inline-block; width: 16px; height: 16px; border: 2px solid rgba(255,255,255,0.3); border-top-color: white; border-radius: 50%; animation: spin 0.6s linear infinite; }
        .spinner-dark { border-color: rgba(99,102,241,0.2); border-top-color: var(--primary); }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>
<div class="page">
    <div class="sidebar">
        <div class="sb-content">
            <h2>14 Gün Ücretsiz Deneyin</h2>
            <p>Kredi kartı gerekmez. Tüm özelliklere anında erişin ve sistemi keşfedin.</p>
            
            <div style="background: rgba(255,255,255,0.12); border-radius: 12px; padding: 1rem 1.25rem; margin-bottom: 2rem; border: 1px solid rgba(255,255,255,0.15);">
                <div style="font-size: 0.8rem; font-weight: 700; margin-bottom: 0.75rem; opacity: 0.9;">Deneme sürenize dahil:</div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem;">
                    <div style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.8rem; opacity: 0.9;">
                        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        QR Menü
                    </div>
                    <div style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.8rem; opacity: 0.9;">
                        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        POS Sistemi
                    </div>
                    <div style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.8rem; opacity: 0.9;">
                        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Mutfak Ekranı
                    </div>
                    <div style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.8rem; opacity: 0.9;">
                        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Garson Paneli
                    </div>
                    <div style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.8rem; opacity: 0.9;">
                        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Stok Takibi
                    </div>
                    <div style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.8rem; opacity: 0.9;">
                        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Raporlama
                    </div>
                </div>
            </div>
            
            <div class="sb-feature">
                <div class="sb-feature-icon"><svg viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg></div>
                <div><div class="sb-feature-text">2 Dakikada Başlayın</div><div class="sb-feature-sub">Hızlı kayıt, anında erişim</div></div>
            </div>
            <div class="sb-feature">
                <div class="sb-feature-icon"><svg viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg></div>
                <div><div class="sb-feature-text">Kredi Kartı Gerekmez</div><div class="sb-feature-sub">Hiçbir ödeme bilgisi istemiyoruz</div></div>
            </div>
            <div class="sb-feature">
                <div class="sb-feature-icon"><svg viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div>
                <div><div class="sb-feature-text">İptal Ücretsiz</div><div class="sb-feature-sub">Beğenmezseniz sadece kullanmayı bırakın</div></div>
            </div>
            
            <div class="sb-quote">
                <p>"QR menü sayesinde garson bekleme süresi ciddi şekilde azaldı. Müşterilerimiz masadan sipariş verebiliyor, mutfak anında görüyor."</p>
                <div class="sb-quote-author">
                    <div class="sb-quote-avatar">ED</div>
                    <div><div class="sb-quote-name">Emre D.</div><div class="sb-quote-role">Kafe İşletmecisi, İstanbul</div></div>
                </div>
            </div>
        </div>
        </div>
        
    <div class="main">
        <div class="form-wrap">
            <a href="<?php echo $baseUrl; ?>/" class="back-link">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    Ana Sayfa
                </a>
            <a href="<?php echo $baseUrl; ?>/"><img src="<?php echo $baseUrl; ?>/assets/images/logo.png" alt="<?php echo $appName; ?>" class="logo-img"></a>
            <h1 class="page-title">Ücretsiz Denemeyi Başlatın</h1>
            <p class="page-subtitle">14 gün boyunca tüm özelliklere erişin. Kredi kartı gerekmez.</p>
                
                <?php
                try { $toastService = getToastNotificationService(); $flashMessages = $toastService->getFlashMessages(); } catch (\Exception $e) { $flashMessages = []; }
                ?>
                <?php if (!empty($flashMessages['error'])): ?>
            <div class="flash-msg flash-error"><?php echo htmlspecialchars($flashMessages['error']); ?></div>
                <?php endif; ?>
                <?php if (!empty($flashMessages['success'])): ?>
            <div class="flash-msg flash-success"><?php echo htmlspecialchars($flashMessages['success']); ?></div>
                <?php endif; ?>
                
            <div class="steps">
                <div class="step-item active" id="si-1">
                    <div class="step-circle"><span>1</span><svg viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg></div>
                    <div class="step-label">Bilgiler</div>
                </div>
                <div class="step-item" id="si-2">
                    <div class="step-circle"><span>2</span><svg viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg></div>
                    <div class="step-label">Doğrulama</div>
                </div>
                <div class="step-item" id="si-3">
                    <div class="step-circle"><span>3</span><svg viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg></div>
                    <div class="step-label">Şifre</div>
                </div>
                </div>
                
            <form method="POST" action="<?php echo $baseUrl; ?>/register" id="regForm">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                <!-- STEP 1 -->
                <div class="step-panel visible" id="panel-1">
                    <div class="field-row">
                        <div class="field">
                            <label class="field-label">Ad *</label>
                            <input type="text" name="first_name" id="first_name" class="field-input" placeholder="Adınız" required>
                        </div>
                        <div class="field">
                            <label class="field-label">Soyad *</label>
                            <input type="text" name="last_name" id="last_name" class="field-input" placeholder="Soyadınız" required>
                        </div>
                    </div>
                    <div class="field">
                        <label class="field-label">İşletme Adı *</label>
                        <input type="text" name="company_name" id="company_name" class="field-input" placeholder="Restoranınızın adı" required autocomplete="off">
                    </div>
                    <div class="field">
                        <label class="field-label" for="subdomain">Kısa Bağlantı (Subdomain) *
                            <span class="text-slate-400 font-normal text-xs ml-1">— personel giriş adresi</span>
                        </label>
                        <div style="position:relative;">
                            <span style="position:absolute;left:12px;top:50%;transform:translateY(-50%);font-size:13px;font-weight:700;color:#94a3b8;pointer-events:none;white-space:nowrap;">qordy.com/</span>
                            <input type="text" name="subdomain" id="subdomain" class="field-input" placeholder="kafe-adi"
                                   style="padding-left:96px;" maxlength="40" required autocomplete="off"
                                   oninput="handleSubdomainInput(this)">
                        </div>
                        <!-- Live preview -->
                        <div id="subdomain-preview" style="margin-top:6px;display:none;padding:8px 12px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;font-size:12px;color:#475569;">
                            <span style="color:#94a3b8;">Personel giriş adresi:</span>
                            <strong id="subdomain-preview-val" style="color:#1e293b;"></strong>
                        </div>
                        <div id="subdomain-error" style="margin-top:5px;font-size:12px;color:#ef4444;display:none;"></div>
                        <div style="margin-top:5px;font-size:11px;color:#94a3b8;">
                            Sadece küçük harf (a-z), rakam ve tire (-) kullanın. Örnek: <strong>cafe-istanbul</strong>
                        </div>
                    </div>
                    <button type="button" id="btnNext1" class="btn btn-primary btn-full" onclick="goTo(2)" disabled>
                        Devam Et
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                    </button>
                    </div>
                    
                <!-- STEP 2 -->
                <div class="step-panel" id="panel-2">
                    <div class="field">
                        <label class="field-label">E-posta *</label>
                        <div class="verify-row">
                            <input type="email" name="email" id="email" class="field-input" placeholder="ornek@email.com" required>
                            <button type="button" id="btnEmailSend" class="btn btn-secondary btn-sm">Kod Gönder</button>
                        </div>
                        <div id="emailCodeWrap" class="code-block" style="display:none">
                            <div class="verify-row">
                                <input type="text" id="emailCode" class="field-input" placeholder="6 haneli kod" maxlength="6" inputmode="numeric">
                                <button type="button" id="btnEmailVerify" class="btn btn-primary btn-sm">Doğrula</button>
                            </div>
                            <div id="emailCodeMsg" class="code-msg"></div>
                                </div>
                        <div id="emailBadge" class="badge-verified" style="display:none">
                            <svg viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            E-posta doğrulandı
                        </div>
                        </div>
                        
                    <div id="phoneSection" style="display:none">
                        <div class="wa-banner">
                            <svg viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                            <span>WhatsApp hesabı olan numaranızı girin. Doğrulama kodu WhatsApp ile gönderilecektir.</span>
                            </div>
                        <div class="field">
                            <label class="field-label">Telefon *</label>
                                    <input type="hidden" id="countryCode" name="country_code" value="+90">
                            <div class="phone-wrap">
                                <div class="country-picker">
                                    <button type="button" class="cp-btn" id="cpBtn">
                                        <img id="cpFlag" src="https://flagcdn.com/w20/tr.png" alt="">
                                        <span id="cpDial">+90</span>
                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                        </button>
                                    <div class="cp-drop" id="cpDrop"></div>
                                </div>
                                <input type="tel" id="phone" name="phone" class="field-input" placeholder="5321234567" maxlength="15" inputmode="numeric">
                            </div>
                            <div id="phoneHint" class="field-hint">5 ile başlayan 10 haneli numara (başında 0 olmadan)</div>
                        </div>
                        <div id="phoneSendRow">
                            <button type="button" id="btnPhoneSend" class="btn btn-outline-primary btn-sm">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                                WhatsApp ile Kod Gönder
                            </button>
                            </div>
                        <div id="phoneCodeWrap" class="code-block" style="display:none">
                                <div class="verify-row">
                                <input type="text" id="phoneCode" class="field-input" placeholder="6 haneli kod" maxlength="6" inputmode="numeric">
                                <button type="button" id="btnPhoneVerify" class="btn btn-primary btn-sm">Doğrula</button>
                            </div>
                            <div id="phoneCodeMsg" class="code-msg"></div>
                        </div>
                        <div id="phoneBadge" class="badge-verified" style="display:none">
                            <svg viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            Telefon doğrulandı
                        </div>
                    </div>
                    
                    <div class="btn-row">
                        <button type="button" class="btn btn-secondary" onclick="goTo(1)">Geri</button>
                        <button type="button" id="btnNext2" class="btn btn-primary" onclick="goTo(3)" disabled>Devam Et</button>
                    </div>
                                </div>

                <!-- STEP 3 -->
                <div class="step-panel" id="panel-3">
                    <div class="field">
                        <label class="field-label">Şifre *</label>
                        <input type="password" name="password" id="password" class="field-input" placeholder="Güçlü bir şifre oluşturun" required minlength="8">
                        <div class="pw-meter" id="pwMeter" style="display:none">
                            <div class="pw-bar"><div class="pw-fill" id="pwFill"></div></div>
                            <div class="pw-reqs">
                                <div class="pw-req" id="r-len"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg> En az 8 karakter</div>
                                <div class="pw-req" id="r-up"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg> Büyük harf</div>
                                <div class="pw-req" id="r-low"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg> Küçük harf</div>
                                <div class="pw-req" id="r-num"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg> Rakam</div>
                                <div class="pw-req" id="r-sp"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg> Özel karakter</div>
                            </div>
                        </div>
                    </div>
                    <div class="field">
                        <label class="field-label">Şifre Tekrar *</label>
                        <input type="password" name="password_confirm" id="password_confirm" class="field-input" placeholder="Şifrenizi tekrar girin" required minlength="8">
                        <div id="matchMsg" class="pw-match"></div>
                        </div>
                    <div class="terms-row">
                        <input type="checkbox" name="acceptTerms" id="acceptTerms" required>
                        <label for="acceptTerms">
                            <?php if (!empty($registerLegalPages)): ?>
                                <?php 
                                $links = [];
                                foreach ($registerLegalPages as $rlp) {
                                    $links[] = '<a href="' . $baseUrl . '/sayfa/' . htmlspecialchars($rlp['slug']) . '" target="_blank">' . htmlspecialchars($rlp['title']) . '</a>';
                                }
                                echo implode(', ', $links);
                                ?>'nı okudum ve kabul ediyorum.
                            <?php else: ?>
                                <a href="<?php echo $baseUrl; ?>/sayfa/kullanim-kosullari" target="_blank">Kullanım Koşulları</a>,
                                <a href="<?php echo $baseUrl; ?>/sayfa/gizlilik-politikasi" target="_blank">Gizlilik Politikası</a> ve
                                <a href="<?php echo $baseUrl; ?>/sayfa/mesafeli-satis-sozlesmesi" target="_blank">Mesafeli Satış Sözleşmesi</a>'ni okudum ve kabul ediyorum.
                            <?php endif; ?>
                        </label>
                    </div>
                    <div class="btn-row">
                        <button type="button" class="btn btn-secondary" onclick="goTo(2)">Geri</button>
                        <button type="submit" id="btnSubmit" class="btn btn-primary" disabled>
                            Ücretsiz Denemeyi Başlat
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </button>
                        </div>
                    </div>
                </form>
                
            <p class="login-link">Zaten hesabınız var mı? <a href="<?php echo $baseUrl; ?>/login">Giriş Yap</a></p>
        </div>
        </div>
    </div>
    
    <script>
var B = '<?php echo addslashes($baseUrl); ?>';
var emailOk = false, phoneOk = false;
var countries = [
    {d:'+90',c:'tr'},{d:'+1',c:'us'},{d:'+44',c:'gb'},{d:'+49',c:'de'},{d:'+33',c:'fr'},{d:'+39',c:'it'},{d:'+34',c:'es'},{d:'+31',c:'nl'},{d:'+32',c:'be'},{d:'+43',c:'at'},{d:'+41',c:'ch'},{d:'+46',c:'se'},{d:'+47',c:'no'},{d:'+45',c:'dk'},{d:'+358',c:'fi'},{d:'+353',c:'ie'},{d:'+351',c:'pt'},{d:'+30',c:'gr'},{d:'+48',c:'pl'},{d:'+36',c:'hu'},{d:'+40',c:'ro'},{d:'+420',c:'cz'},{d:'+385',c:'hr'},{d:'+381',c:'rs'},{d:'+994',c:'az'},{d:'+995',c:'ge'},{d:'+998',c:'uz'},{d:'+7',c:'ru'},{d:'+380',c:'ua'},{d:'+98',c:'ir'},{d:'+966',c:'sa'},{d:'+971',c:'ae'},{d:'+974',c:'qa'},{d:'+20',c:'eg'},{d:'+212',c:'ma'},{d:'+91',c:'in'},{d:'+92',c:'pk'},{d:'+62',c:'id'},{d:'+60',c:'my'},{d:'+65',c:'sg'},{d:'+66',c:'th'},{d:'+81',c:'jp'},{d:'+82',c:'kr'},{d:'+86',c:'cn'},{d:'+61',c:'au'},{d:'+55',c:'br'},{d:'+52',c:'mx'}
];

function $(id) { return document.getElementById(id); }
function show(el) { if (el) el.style.display = ''; }
function hide(el) { if (el) el.style.display = 'none'; }
function msg(t) { if (window.NotificationManager) window.NotificationManager.warning(t); else alert(t); }
function err(t) { if (window.NotificationManager) window.NotificationManager.error(t); else alert(t); }

function goTo(step) {
    for (var i = 1; i <= 3; i++) {
        var p = $('panel-' + i), s = $('si-' + i);
        if (p) { p.classList.toggle('visible', i === step); }
        if (s) {
            s.classList.remove('active', 'done');
            if (i < step) s.classList.add('done');
            else if (i === step) s.classList.add('active');
        }
    }
    if (step === 2) $('phoneSection').style.display = emailOk ? '' : 'none';
    if (step === 3) checkSubmit();
}

// ── Subdomain helpers ──────────────────────────────────────────────
var trMap = {
    'ğ':'g','Ğ':'g','ü':'u','Ü':'u','ş':'s','Ş':'s',
    'ı':'i','İ':'i','ö':'o','Ö':'o','ç':'c','Ç':'c',
    ' ':'-','_':'-'
};
function slugify(str) {
    str = str.toLowerCase();
    str = str.replace(/[ğĞüÜşŞıİöÖçÇ _]/g, function(c){ return trMap[c] || c; });
    str = str.replace(/[^a-z0-9-]/g, '');
    str = str.replace(/-+/g, '-').replace(/^-+|-+$/g, '');
    return str;
}
function isValidSlug(str) {
    return /^[a-z0-9][a-z0-9-]{1,38}[a-z0-9]$|^[a-z0-9]{2,40}$/.test(str);
}
var subdomainOk = false;
function handleSubdomainInput(input) {
    var raw = input.value;
    var errEl = $('subdomain-error');
    var prevEl = $('subdomain-preview');
    var prevVal = $('subdomain-preview-val');

    // Block Turkish chars, uppercase, spaces — show inline error
    if (/[ğĞüÜşŞıİöÖçÇ]/.test(raw)) {
        errEl.textContent = 'Türkçe karakter kullanılamaz. Otomatik düzeltme: ' + slugify(raw);
        errEl.style.display = '';
        input.value = slugify(raw);
        raw = input.value;
    } else if (/[A-Z]/.test(raw)) {
        input.value = raw.toLowerCase();
        raw = input.value;
    } else if (/\s/.test(raw)) {
        input.value = raw.replace(/\s+/g, '-');
        raw = input.value;
    }
    // Remove chars that are not a-z0-9-
    if (/[^a-z0-9-]/.test(raw)) {
        errEl.textContent = 'Sadece a-z harfleri, rakam ve tire (-) kullanabilirsiniz.';
        errEl.style.display = '';
        input.value = raw.replace(/[^a-z0-9-]/g, '');
        raw = input.value;
    }

    if (!raw) {
        subdomainOk = false;
        prevEl.style.display = 'none';
        errEl.style.display = 'none';
        checkStep1(); return;
    }

    prevVal.textContent = raw + '.qordy.com';
    prevEl.style.display = '';

    if (!isValidSlug(raw)) {
        errEl.textContent = 'En az 2 karakter, yalnızca a-z, 0-9 ve tire (-). Baş/son tire olamaz.';
        errEl.style.display = '';
        subdomainOk = false;
        checkStep1(); return;
    }

    // Async availability check
    errEl.textContent = '⏳ Kontrol ediliyor…';
    errEl.style.display = '';
    subdomainOk = false;
    checkStep1();
    clearTimeout(subEl._checkTimer);
    subEl._checkTimer = setTimeout(function() {
        fetch('/api/register/check-subdomain?subdomain=' + encodeURIComponent(raw))
            .then(function(r){ return r.json(); })
            .then(function(d) {
                if (d.available) {
                    errEl.style.display = 'none';
                    subdomainOk = true;
                    prevEl.style.backgroundColor = '#f0fdf4';
                } else {
                    errEl.textContent = '⚠️ "' + raw + '" zaten kullanımda. Farklı bir kısa ad deneyin.';
                    errEl.style.display = '';
                    subdomainOk = false;
                    prevEl.style.backgroundColor = '#fef2f2';
                }
                checkStep1();
            }).catch(function(){ subdomainOk = true; checkStep1(); });
    }, 500);
}

// Auto-suggest subdomain from company name
var companyEl = $('company_name');
var subEl     = $('subdomain');
if (companyEl && subEl) {
    companyEl.addEventListener('input', function() {
        if (!subEl.dataset.manuallyEdited) {
            subEl.value = slugify(this.value).substring(0, 40);
            handleSubdomainInput(subEl);
        }
        checkStep1();
    });
    subEl.addEventListener('input', function() {
        this.dataset.manuallyEdited = '1';
    });
}
// ─────────────────────────────────────────────────────────────────

function checkStep1() {
    var ok = ($('first_name').value||'').trim() &&
             ($('last_name').value||'').trim()  &&
             ($('company_name').value||'').trim() &&
             subdomainOk;
    $('btnNext1').disabled = !ok;
}
['first_name','last_name'].forEach(function(id) {
    var el = $(id); if (el) { el.oninput = checkStep1; }
});

function checkSubmit() {
    var pw = $('password').value, c = $('password_confirm').value, t = $('acceptTerms').checked;
    var ok = pw.length >= 8 && /[A-Z]/.test(pw) && /[a-z]/.test(pw) && /[0-9]/.test(pw) && /[^\w\s]/.test(pw) && pw === c && t;
    $('btnSubmit').disabled = !ok;
}

$('password').oninput = function() {
    var pw = this.value;
    if (!pw) { hide($('pwMeter')); checkSubmit(); return; }
    show($('pwMeter'));
    var checks = [pw.length >= 8, /[A-Z]/.test(pw), /[a-z]/.test(pw), /[0-9]/.test(pw), /[^\w\s]/.test(pw)];
    var ids = ['r-len','r-up','r-low','r-num','r-sp'];
    var n = 0;
    for (var i = 0; i < 5; i++) {
        var el = $(ids[i]);
        el.classList.toggle('ok', checks[i]);
        el.querySelector('svg').innerHTML = checks[i]
            ? '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>'
            : '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>';
        if (checks[i]) n++;
    }
    var fill = $('pwFill');
    fill.style.width = (n/5*100) + '%';
    fill.style.background = n <= 2 ? '#ef4444' : n <= 4 ? '#f59e0b' : '#22c55e';
    checkSubmit();
};
$('password_confirm').oninput = function() {
    var pw = $('password').value, c = this.value, m = $('matchMsg');
    if (!c) { m.textContent = ''; checkSubmit(); return; }
    m.textContent = pw === c ? '✓ Şifreler eşleşiyor' : '✗ Şifreler eşleşmiyor';
    m.style.color = pw === c ? '#22c55e' : '#ef4444';
    checkSubmit();
};
$('acceptTerms').onchange = checkSubmit;

$('phone').oninput = function() {
    var cc = $('countryCode').value;
        this.value = this.value.replace(/\D/g, '');
    if (cc === '+90') { this.value = this.value.slice(0, 10); if (this.value && this.value[0] !== '5') this.value = '5' + this.value.slice(1); }
    else { this.value = this.value.slice(0, 15); }
};

    (function() {
    var btn = $('cpBtn'), drop = $('cpDrop'), hid = $('countryCode'), flag = $('cpFlag'), dial = $('cpDial');
    countries.forEach(function(c) {
        var d = document.createElement('div');
        d.className = 'cp-item';
        d.innerHTML = '<img src="https://flagcdn.com/w20/' + c.c + '.png" alt=""><span>' + c.d + '</span>';
        d.onclick = function() {
            hid.value = c.d; flag.src = 'https://flagcdn.com/w20/' + c.c + '.png'; dial.textContent = c.d; drop.classList.remove('open');
            $('phoneHint').textContent = c.d === '+90' ? '5 ile başlayan 10 haneli numara (başında 0 olmadan)' : 'Ülkenize göre numara formatını girin';
        };
        drop.appendChild(d);
    });
    btn.onclick = function(e) { e.stopPropagation(); if (!this.disabled) drop.classList.toggle('open'); };
        document.addEventListener('click', function() { drop.classList.remove('open'); });
    })();
    
function setLoading(btn, loading, text) {
    if (loading) { btn._txt = btn.innerHTML; btn.innerHTML = '<span class="spinner"></span> ' + (text || 'Gönderiliyor...'); btn.disabled = true; }
    else { btn.innerHTML = btn._txt || text || ''; btn.disabled = false; }
}

$('btnEmailSend').onclick = async function() {
    var email = $('email').value.trim().toLowerCase();
    if (!email) { msg('Önce e-posta adresinizi girin'); return; }
    setLoading(this, true, 'Gönderiliyor...');
    try {
        var r = await fetch(B + '/api/register/send-email-code', { method: 'POST', headers: {'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'}, body: JSON.stringify({email:email}) });
        var d = await r.json();
        if (d.success) { show($('emailCodeWrap')); $('emailCodeMsg').textContent = 'Doğrulama kodu e-postanıza gönderildi'; setLoading(this, false); this.textContent = 'Tekrar Gönder'; }
        else { err(d.error || 'Hata oluştu'); setLoading(this, false); }
    } catch(e) { err('Bağlantı hatası'); setLoading(this, false); }
};

$('btnEmailVerify').onclick = async function() {
    var email = $('email').value.trim().toLowerCase();
    var code = $('emailCode').value.trim();
    if (!code || code.length < 4) { msg('Doğrulama kodunu girin'); return; }
    setLoading(this, true, 'Doğrulanıyor...');
    try {
        var r = await fetch(B + '/api/register/verify-email', { method: 'POST', headers: {'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'}, body: JSON.stringify({email:email, code:code}) });
        var d = await r.json();
            if (d.success && d.verified) {
            emailOk = true;
            hide($('btnEmailSend')); hide($('emailCodeWrap')); show($('emailBadge'));
            $('email').readOnly = true;
            show($('phoneSection'));
            $('btnNext2').disabled = !phoneOk;
        } else { err(d.error || 'Geçersiz kod'); }
    } catch(e) { err('Bağlantı hatası'); }
    setLoading(this, false);
};

$('btnPhoneSend').onclick = async function() {
    var phone = $('phone').value.replace(/\D/g, '');
    var cc = $('countryCode').value;
    if (!phone || phone.length < 8) { msg('Telefon numaranızı girin'); return; }
    if (cc === '+90' && phone[0] !== '5') { msg('Türkiye numarası 5 ile başlamalıdır'); return; }
    setLoading(this, true, 'WhatsApp ile gönderiliyor...');
    try {
        var r = await fetch(B + '/api/register/send-phone-code', { method: 'POST', headers: {'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'}, body: JSON.stringify({phone:phone, country_code:cc}) });
        var d = await r.json();
        if (d.success) { show($('phoneCodeWrap')); $('phoneCodeMsg').textContent = 'Kod WhatsApp ile gönderildi'; setLoading(this, false); this.innerHTML = '<svg fill=\"none\" stroke=\"currentColor\" viewBox=\"0 0 24 24\" stroke-width=\"2\" width=\"18\" height=\"18\"><path stroke-linecap=\"round\" stroke-linejoin=\"round\" d=\"M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15\"/></svg> Tekrar Gönder'; }
        else { err(d.error || 'Hata oluştu'); setLoading(this, false); }
    } catch(e) { err('Bağlantı hatası'); setLoading(this, false); }
};

$('btnPhoneVerify').onclick = async function() {
    var phone = $('phone').value.replace(/\D/g, '');
    var cc = $('countryCode').value;
    var code = $('phoneCode').value.trim();
    if (!code || code.length < 4) { msg('Doğrulama kodunu girin'); return; }
    setLoading(this, true, 'Doğrulanıyor...');
    try {
        var r = await fetch(B + '/api/register/verify-phone', { method: 'POST', headers: {'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'}, body: JSON.stringify({phone:phone, country_code:cc, code:code}) });
        var d = await r.json();
            if (d.success && d.verified) {
            phoneOk = true;
            hide($('phoneSendRow')); hide($('phoneCodeWrap')); show($('phoneBadge'));
            $('phone').readOnly = true;
            var cb = $('cpBtn'); if (cb) { cb.disabled = true; }
            $('btnNext2').disabled = false;
        } else { err(d.error || 'Geçersiz kod'); }
    } catch(e) { err('Bağlantı hatası'); }
    setLoading(this, false);
};

$('regForm').onsubmit = function(e) {
    var pw = $('password').value, c = $('password_confirm').value, t = $('acceptTerms').checked;
    if (pw !== c) { e.preventDefault(); msg('Şifreler eşleşmiyor!'); return; }
    if (pw.length < 8 || !/[A-Z]/.test(pw) || !/[a-z]/.test(pw) || !/[0-9]/.test(pw) || !/[^\w\s]/.test(pw)) { e.preventDefault(); msg('Şifre gereksinimleri karşılanmıyor!'); return; }
    if (!t) { e.preventDefault(); msg('Kullanım şartlarını kabul etmelisiniz!'); return; }
    var btn = $('btnSubmit');
    btn.innerHTML = '<span class="spinner"></span> Hesap oluşturuluyor...';
    btn.disabled = true;
};
    </script>
</body>
</html>
