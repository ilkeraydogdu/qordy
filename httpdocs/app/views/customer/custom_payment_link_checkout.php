<?php
$form  = $form  ?? '';
$link  = $link  ?? null;
$package = $package ?? null;
$appName = 'Qordy';
try { $appName = htmlspecialchars(getAppConfig()->getAppName()); } catch (\Throwable $e) {}
$baseUrl = defined('BASE_URL') ? BASE_URL : '';
$publicToken = is_array($link) ? (string)($link['token'] ?? '') : '';

$features = [];
if (!empty($package['features'] ?? null)) {
    try {
        $packageService = \App\Core\DependencyFactory::getPackageService();
        $features = $packageService->formatFeaturesForDisplay($package['features']);
    } catch (\Throwable $e) {}
}

$monthlyEquivalent = 0.0;
if (is_array($link) && (int)($link['duration_months'] ?? 0) > 0) {
    $monthlyEquivalent = ((float)($link['custom_price'] ?? 0)) / (int)$link['duration_months'];
}

$iframeDoc = '<!DOCTYPE html><html lang="tr"><head><meta charset="utf-8">'
    . '<meta name="viewport" content="width=device-width,initial-scale=1">'
    . '<title>Ödeme</title>'
    . '<link rel="preconnect" href="https://fonts.googleapis.com">'
    . '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
    . '<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">'
    . '<style>html,body{margin:0;padding:0;background:transparent;font-family:Inter,system-ui,-apple-system,sans-serif;color:#0f172a;-webkit-font-smoothing:antialiased;overflow:hidden;}'
    . '#iyzipay-checkout-form{min-height:560px}'
    . '#iyzipay-checkout-form iframe{width:100%!important;border:0!important;min-height:560px;background:transparent;display:block;}</style>'
    . '</head><body>'
    . '<div id="iyzipay-checkout-form" class="responsive"></div>'
    . $form
    . '<script>(function(){var lastH=0;function r(){try{var c=document.getElementById("iyzipay-checkout-form");var h=Math.max(document.body.scrollHeight,document.documentElement.scrollHeight,c?c.getBoundingClientRect().height:0,c?c.offsetHeight:0);h=Math.round(h);if(h&&h!==lastH){lastH=h;try{window.parent.postMessage({type:"iyzico:custom-link-resize",height:h},window.location.origin);}catch(e){}}}catch(e){}}setInterval(r,250);window.addEventListener("load",r);window.addEventListener("resize",r);if(window.ResizeObserver){try{var ro=new ResizeObserver(r);ro.observe(document.body);var c=document.getElementById("iyzipay-checkout-form");if(c){ro.observe(c);}}catch(e){}}if(window.MutationObserver){try{new MutationObserver(r).observe(document.body,{childList:true,subtree:true,attributes:true});}catch(e){}}})();</script>'
    . '</body></html>';
?>
<?php
require_once __DIR__ . '/../../core/Security/CSRFManager.php';
$csrfToken = \App\Core\Security\CSRFManager::generateToken();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <?php require_once __DIR__ . '/../partials/google_analytics.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrfToken); ?>">
    <title>Güvenli Ödeme — <?php echo $appName; ?></title>
    <link rel="icon" type="image/png" href="<?php echo $baseUrl; ?>/assets/images/favicon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@700;800;900&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, sans-serif;
            color: #0f172a;
            min-height: 100vh;
            -webkit-font-smoothing: antialiased;
            background:
                radial-gradient(1000px 600px at 100% -10%, #eef2ff 0%, transparent 60%),
                radial-gradient(900px 500px at -10% 110%, #ecfeff 0%, transparent 60%),
                #f8fafc;
        }
        .nav {
            display: flex; align-items: center; justify-content: space-between;
            padding: 1rem 1.5rem; border-bottom: 1px solid rgba(15,23,42,0.06);
            background: rgba(255,255,255,0.6); backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            position: sticky; top: 0; z-index: 20;
        }
        .nav .brand { display: flex; align-items: center; gap: 0.6rem; color: #0f172a; text-decoration: none; font-weight: 900; }
        .nav .brand img { height: 28px; }
        .nav a.back { color: #64748b; text-decoration: none; font-size: 0.875rem; font-weight: 600; display: inline-flex; align-items: center; gap: 0.35rem; }
        .nav a.back:hover { color: #4f46e5; }
        .steps {
            display: flex; align-items: center; justify-content: center;
            gap: 0.6rem; padding: 1.5rem 1rem 0.5rem;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }
        .step { display: flex; align-items: center; gap: 0.4rem; font-size: 0.8rem; font-weight: 700; }
        .step-dot {
            width: 28px; height: 28px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.75rem; font-weight: 800;
        }
        .step.active .step-dot { background: linear-gradient(135deg, #6366f1, #8b5cf6); color: #fff; box-shadow: 0 6px 18px rgba(99,102,241,0.35); }
        .step.done .step-dot   { background: #10b981; color: #fff; }
        .step.inactive .step-dot { background: #e2e8f0; color: #94a3b8; }
        .step.active span { color: #0f172a; }
        .step.inactive span { color: #94a3b8; }
        .step-line { width: 44px; height: 2px; background: #e2e8f0; border-radius: 2px; }
        .shell { max-width: 1080px; margin: 0 auto; padding: 1.5rem 1.25rem 4rem; }
        .grid { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1.4fr); gap: 1.25rem; align-items: flex-start; }
        @media (max-width: 900px) { .grid { grid-template-columns: 1fr; } }
        .summary-sticky { position: sticky; top: 88px; }
        @media (max-width: 900px) { .summary-sticky { position: static; } }
        .card {
            background: #fff;
            border: 1px solid rgba(15,23,42,0.06);
            border-radius: 20px;
            box-shadow: 0 1px 2px rgba(15,23,42,0.04), 0 12px 40px -12px rgba(15,23,42,0.12);
        }
        .card-body { padding: 1.35rem; }
        .card h2 {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 1.05rem; font-weight: 800; color: #0f172a;
            display: flex; align-items: center; gap: 0.55rem;
        }
        .card h2 svg { width: 18px; height: 18px; color: #6366f1; }
        .plan-hero {
            padding: 1.4rem 1.35rem 1.1rem;
            border-bottom: 1px dashed #e2e8f0;
            background: linear-gradient(180deg, #fafbff 0%, #ffffff 100%);
            border-top-left-radius: 20px; border-top-right-radius: 20px;
        }
        .plan-badge {
            display: inline-flex; align-items: center; gap: 0.3rem;
            padding: 0.25rem 0.6rem; border-radius: 999px;
            background: linear-gradient(135deg, #ede9fe, #e0e7ff);
            color: #4338ca; font-size: 0.7rem; font-weight: 800; letter-spacing: 0.02em;
            text-transform: uppercase;
        }
        .plan-name {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 1.4rem; font-weight: 900; margin-top: 0.6rem; color: #0f172a;
            line-height: 1.2;
        }
        .plan-desc { margin-top: 0.35rem; font-size: 0.82rem; color: #64748b; line-height: 1.55; }
        .price-block { padding: 1.25rem 1.35rem; border-bottom: 1px dashed #e2e8f0; }
        .price-row { display: flex; align-items: flex-end; justify-content: space-between; gap: 0.75rem; }
        .price-amount {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 2.25rem; font-weight: 900; color: #0f172a;
            line-height: 1; letter-spacing: -0.01em;
        }
        .price-currency { font-size: 1.05rem; color: #64748b; font-weight: 700; margin-left: 0.15rem; }
        .price-period { font-size: 0.8rem; color: #64748b; font-weight: 600; }
        .price-monthly { margin-top: 0.35rem; font-size: 0.78rem; color: #64748b; font-weight: 600; }
        .features { padding: 1rem 1.35rem 1.35rem; }
        .features h3 {
            font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.08em;
            color: #64748b; font-weight: 800; margin-bottom: 0.6rem;
        }
        .feature { display: flex; align-items: flex-start; gap: 0.5rem; padding: 0.3rem 0; font-size: 0.82rem; color: #334155; line-height: 1.45; }
        .feature-tick {
            flex-shrink: 0; width: 18px; height: 18px; border-radius: 50%;
            background: #dcfce7; color: #16a34a;
            display: inline-flex; align-items: center; justify-content: center;
            margin-top: 0.1rem;
        }
        .feature-tick svg { width: 10px; height: 10px; }
        .total-bar {
            display: flex; align-items: center; justify-content: space-between;
            padding: 1rem 1.35rem; border-top: 1px solid #eef2ff;
            background: #f8fafc;
            border-bottom-left-radius: 20px; border-bottom-right-radius: 20px;
        }
        .total-bar .label { font-size: 0.75rem; color: #64748b; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; }
        .total-bar .value { font-family: 'Plus Jakarta Sans', sans-serif; font-size: 1.35rem; font-weight: 900; color: #4f46e5; }
        .pay-frame-wrap {
            position: relative;
            margin-top: 1rem;
            min-height: 620px;
            border-radius: 16px; overflow: hidden;
            border: 1px solid #e2e8f0; background: #fff;
            transition: height 0.18s ease;
        }
        #pay-frame { width: 100%; height: 620px; border: 0; display: block; background: transparent; }
        #pay-loading {
            position: absolute; inset: 0;
            display: flex; align-items: center; justify-content: center;
            background: linear-gradient(180deg, #fafbff 0%, #ffffff 100%);
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        .spinner { width: 32px; height: 32px; border: 3px solid #e2e8f0; border-top-color: #6366f1; border-radius: 50%; animation: spin 0.8s linear infinite; margin: 0 auto 0.7rem; }
        .lead { color: #64748b; font-size: 0.9rem; line-height: 1.55; margin: 0.4rem 0 0.25rem; }
        .trust {
            display: flex; flex-wrap: wrap; align-items: center; justify-content: center;
            gap: 0.75rem 1.2rem; margin-top: 1.25rem; padding: 0.9rem 1rem;
            background: rgba(255,255,255,0.6); border: 1px solid rgba(15,23,42,0.05);
            border-radius: 14px;
            font-size: 0.72rem; color: #64748b; font-weight: 600;
        }
        .trust .item { display: inline-flex; align-items: center; gap: 0.35rem; }
        .trust svg { width: 14px; height: 14px; color: #10b981; }
        .trust .divider { width: 1px; height: 14px; background: #e2e8f0; }
        @media (max-width: 520px) { .trust .divider { display: none; } }
        @media (max-width: 640px) {
            .nav { padding: 0.75rem 1rem; }
            .shell { padding: 1rem 0.75rem 3rem; }
            .card-body { padding: 1rem; }
            .plan-hero, .price-block, .features, .total-bar { padding-left: 1rem; padding-right: 1rem; }
        }
    </style>
</head>
<body>
<nav class="nav">
    <a class="brand" href="<?php echo $baseUrl; ?>/">
        <img src="<?php echo $baseUrl; ?>/assets/images/logo.png" alt="<?php echo $appName; ?>">
    </a>
    <?php if ($publicToken !== ''): ?>
    <a class="back" href="<?php echo $baseUrl; ?>/pay/<?php echo htmlspecialchars($publicToken); ?>">
        <svg fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" width="14" height="14"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
        Teklife dön
    </a>
    <?php else: ?>
    <span style="font-size:0.78rem;color:#64748b;font-weight:600;">Güvenli ödeme</span>
    <?php endif; ?>
</nav>

<div class="steps">
    <div class="step done">
        <div class="step-dot">
            <svg width="12" height="12" fill="none" stroke="#fff" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
        </div>
        <span>Teklif</span>
    </div>
    <div class="step-line"></div>
    <div class="step active"><div class="step-dot">2</div><span>Ödeme</span></div>
    <div class="step-line"></div>
    <div class="step inactive"><div class="step-dot">3</div><span>Tamamlandı</span></div>
</div>

<div class="shell">
    <div class="grid">
        <?php if ($link): ?>
        <aside class="summary-sticky">
            <div class="card">
                <div class="plan-hero">
                    <span class="plan-badge">Özel teklif</span>
                    <div class="plan-name"><?php echo htmlspecialchars($package['name'] ?? 'Abonelik'); ?></div>
                    <?php if (!empty($package['description'])): ?>
                    <div class="plan-desc"><?php echo htmlspecialchars($package['description']); ?></div>
                    <?php endif; ?>
                </div>
                <div class="price-block">
                    <div class="price-row">
                        <div>
                            <span class="price-amount"><?php echo number_format((float)$link['custom_price'], 0, ',', '.'); ?><span class="price-currency"> ₺</span></span>
                        </div>
                        <span class="price-period"><?php echo (int)$link['duration_months']; ?> ay peşin</span>
                    </div>
                    <?php if ($monthlyEquivalent > 0): ?>
                    <div class="price-monthly">Aylık eşdeğeri ~<?php echo number_format($monthlyEquivalent, 0, ',', '.'); ?> ₺</div>
                    <?php endif; ?>
                </div>
                <?php if (is_array($features) && !empty($features)): ?>
                <div class="features">
                    <h3>Pakete dahil</h3>
                    <?php foreach (array_slice($features, 0, 8) as $feat): ?>
                    <div class="feature">
                        <span class="feature-tick">
                            <svg fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        </span>
                        <span><?php echo htmlspecialchars(is_array($feat) ? ($feat['text'] ?? $feat['name'] ?? '') : (string)$feat); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <div class="total-bar">
                    <span class="label">Toplam</span>
                    <span class="value"><?php echo number_format((float)$link['custom_price'], 2, ',', '.'); ?> ₺</span>
                </div>
            </div>
        </aside>
        <?php endif; ?>

        <section>
            <div class="card">
                <div class="card-body">
                    <h2>
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M5 6h14a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2z"/></svg>
                        Kart bilgileri
                    </h2>
                    <p class="lead">3D Secure SMS doğrulaması bu sayfada tamamlanır. Sayfadan ayrılmayın.</p>
                    <div id="pay-frame-wrap" class="pay-frame-wrap">
                        <iframe id="pay-frame"
                                title="Güvenli ödeme"
                                allow="payment"
                                referrerpolicy="origin"></iframe>
                        <div id="pay-loading">
                            <div style="text-align:center;">
                                <div class="spinner"></div>
                                <p style="color:#64748b;font-size:0.85rem;font-weight:600;">Güvenli ödeme formu yükleniyor…</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="trust">
                <span class="item">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                    256-bit SSL
                </span>
                <span class="divider"></span>
                <span class="item">
                    <svg fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                    iyzico güvenli ödeme
                </span>
                <span class="divider"></span>
                <span class="item">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    3D Secure onayı
                </span>
            </div>
        </section>
    </div>
</div>
<?php
$iframeJson = json_encode($iframeDoc, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
if ($iframeJson === false) {
    $iframeJson = '""';
}
?>
<script>
(function () {
    var iframe = document.getElementById('pay-frame');
    var loading = document.getElementById('pay-loading');
    var wrap = document.getElementById('pay-frame-wrap');
    if (!iframe) return;
    iframe.addEventListener('load', function () { if (loading) loading.style.display = 'none'; }, { once: true });
    iframe.srcdoc = <?php echo $iframeJson; ?>;
    window.addEventListener('message', function (ev) {
        if (!ev || !ev.data || typeof ev.data !== 'object') return;
        if (ev.origin !== window.location.origin) return;
        var d = ev.data;
        if (d.type === 'iyzico:custom-link-resize') {
            if (loading) loading.style.display = 'none';
            var h = Math.max(parseInt(d.height, 10) || 0, 560);
            if (iframe) iframe.style.height = h + 'px';
            if (wrap) wrap.style.minHeight = h + 'px';
            return;
        }
        if (d.type === 'iyzico:custom-link-result' && typeof d.redirect === 'string' && d.redirect.length > 0) {
            window.location.replace(d.redirect);
        }
    }, false);
})();
</script>
</body>
</html>
