<?php
$link = $link ?? null;
$package = $package ?? null;
$appName = 'Qordy';
try { $appName = htmlspecialchars(getAppConfig()->getAppName()); } catch (\Throwable $e) {}

if (!$link) { return; }

$monthlyEquivalent = $link['duration_months'] > 0
    ? ((float)$link['custom_price']) / (int)$link['duration_months']
    : 0.0;

$features = [];
if (!empty($package['features'])) {
    try {
        $packageService = \App\Core\DependencyFactory::getPackageService();
        $features = $packageService->formatFeaturesForDisplay($package['features']);
    } catch (\Throwable $e) {}
}

$baseUrl = defined('BASE_URL') ? BASE_URL : '';

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
    <title>Size özel teklif — <?php echo $appName; ?></title>
    <link rel="icon" type="image/png" href="<?php echo $baseUrl; ?>/assets/images/favicon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@700;800;900&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif; color: #0f172a;
            min-height: 100vh; -webkit-font-smoothing: antialiased;
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
        .step.inactive .step-dot { background: #e2e8f0; color: #94a3b8; }
        .step.active span { color: #0f172a; }
        .step.inactive span { color: #94a3b8; }
        .step-line { width: 44px; height: 2px; background: #e2e8f0; border-radius: 2px; }
        .shell { max-width: 1080px; margin: 0 auto; padding: 0 1.25rem 4rem; }
        .grid { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1.4fr); gap: 1.25rem; align-items: flex-start; }
        @media (max-width: 900px) { .grid { grid-template-columns: 1fr; } }
        .summary-sticky { position: sticky; top: 88px; }
        @media (max-width: 900px) { .summary-sticky { position: static; } }
        .card {
            background: #fff; border: 1px solid rgba(15,23,42,0.06);
            border-radius: 20px;
            box-shadow: 0 1px 2px rgba(15,23,42,0.04), 0 12px 40px -12px rgba(15,23,42,0.12);
        }
        .plan-hero {
            padding: 1.4rem 1.35rem 1.1rem; border-bottom: 1px dashed #e2e8f0;
            background: linear-gradient(180deg, #fafbff, #fff);
            border-top-left-radius: 20px; border-top-right-radius: 20px;
        }
        .plan-badge { display: inline-flex; align-items: center; gap: 0.3rem; padding: 0.25rem 0.6rem; border-radius: 999px;
            background: linear-gradient(135deg, #ede9fe, #e0e7ff); color: #4338ca; font-size: 0.7rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.02em; }
        .plan-name { font-family: 'Plus Jakarta Sans', sans-serif; font-size: 1.4rem; font-weight: 900; color: #0f172a; margin-top: 0.6rem; line-height: 1.2; }
        .plan-desc { margin-top: 0.35rem; font-size: 0.82rem; color: #64748b; line-height: 1.55; }
        .price-block { padding: 1.25rem 1.35rem; border-bottom: 1px dashed #e2e8f0; }
        .price-row { display: flex; align-items: flex-end; justify-content: space-between; gap: 0.75rem; }
        .price-amount { font-family: 'Plus Jakarta Sans', sans-serif; font-size: 2.2rem; font-weight: 900; color: #0f172a; line-height: 1; }
        .price-currency { font-size: 1.05rem; color: #64748b; font-weight: 700; }
        .price-period { font-size: 0.8rem; color: #64748b; font-weight: 600; }
        .price-monthly { margin-top: 0.35rem; font-size: 0.78rem; color: #64748b; font-weight: 600; }
        .features { padding: 1rem 1.35rem 1.35rem; }
        .features h3 { font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.08em; color: #64748b; font-weight: 800; margin-bottom: 0.6rem; }
        .feature { display: flex; gap: 0.5rem; padding: 0.3rem 0; font-size: 0.82rem; color: #334155; line-height: 1.45; }
        .tick { flex-shrink: 0; width: 18px; height: 18px; border-radius: 50%; background: #dcfce7; color: #16a34a; display: inline-flex; align-items: center; justify-content: center; margin-top: 0.1rem; }
        .tick svg { width: 10px; height: 10px; }
        .total-bar { display: flex; align-items: center; justify-content: space-between; padding: 1rem 1.35rem; border-top: 1px solid #eef2ff; background: #f8fafc;
            border-bottom-left-radius: 20px; border-bottom-right-radius: 20px; }
        .total-bar .label { font-size: 0.75rem; color: #64748b; font-weight: 700; text-transform: uppercase; }
        .total-bar .value { font-family: 'Plus Jakarta Sans', sans-serif; font-size: 1.35rem; font-weight: 900; color: #4f46e5; }
        .card-body { padding: 1.5rem; }
        .card-body h2 { font-family: 'Plus Jakarta Sans', sans-serif; font-size: 1.1rem; font-weight: 800; color: #0f172a; margin-bottom: 0.5rem; }
        .card-body p.lead { font-size: 0.88rem; color: #64748b; line-height: 1.6; margin-bottom: 1rem; }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 0.45rem; padding: 0.95rem 1.25rem; font-size: 0.95rem; font-weight: 800; font-family: inherit; border: none; border-radius: 12px; cursor: pointer; text-decoration: none; transition: all 0.18s ease; width: 100%; }
        .btn-primary { background: linear-gradient(135deg, #6366f1, #8b5cf6); color: #fff; box-shadow: 0 8px 22px -6px rgba(99,102,241,0.55); }
        .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 12px 28px -6px rgba(99,102,241,0.6); }
        .btn-dark { background: #0f172a; color: #fff; }
        .btn-dark:hover { background: #1e293b; }
        .alert { padding: 0.85rem 1rem; border-radius: 12px; font-size: 0.85rem; line-height: 1.5; margin-bottom: 1rem; }
        .alert-warn { background: #fffbeb; color: #92400e; border: 1px solid #fde68a; }
        .target-info { display: flex; align-items: center; gap: 0.75rem; padding: 0.85rem 1rem; background: #f8fafc; border-radius: 12px; margin-bottom: 1rem; border: 1px solid #e2e8f0; }
        .target-info .avatar { width: 36px; height: 36px; border-radius: 50%; background: linear-gradient(135deg, #6366f1, #8b5cf6); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 0.85rem; flex-shrink: 0; }
        .target-info .who { font-size: 0.85rem; font-weight: 700; color: #0f172a; }
        .target-info .email { font-size: 0.75rem; color: #64748b; }
        .trust { display: flex; flex-wrap: wrap; align-items: center; justify-content: center; gap: 0.75rem 1.2rem; margin-top: 1.25rem; padding: 0.9rem 1rem; background: rgba(255,255,255,0.6);
            border: 1px solid rgba(15,23,42,0.05); border-radius: 14px; font-size: 0.72rem; color: #64748b; font-weight: 600; }
        .trust .item { display: inline-flex; align-items: center; gap: 0.35rem; }
        .trust svg { width: 14px; height: 14px; color: #10b981; }
        .trust .divider { width: 1px; height: 14px; background: #e2e8f0; }
        @media (max-width: 640px) { .nav { padding: 0.75rem 1rem; } .shell { padding: 0 0.75rem 3rem; } }
    </style>
</head>
<body>
<nav class="nav">
    <a class="brand" href="<?php echo $baseUrl; ?>/">
        <img src="<?php echo $baseUrl; ?>/assets/images/logo.png" alt="<?php echo $appName; ?>">
    </a>
    <a class="back" href="<?php echo $baseUrl; ?>/">
        <svg fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" width="14" height="14"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12h18M8 5l-7 7 7 7"/></svg>
        Ana sayfa
    </a>
</nav>

<div class="steps">
    <div class="step active"><div class="step-dot">1</div><span>Teklif</span></div>
    <div class="step-line"></div>
    <div class="step inactive"><div class="step-dot">2</div><span>Ödeme</span></div>
    <div class="step-line"></div>
    <div class="step inactive"><div class="step-dot">3</div><span>Tamamlandı</span></div>
</div>

<div class="shell">
    <div class="grid">
        <aside class="summary-sticky">
            <div class="card">
                <div class="plan-hero">
                    <span class="plan-badge">Size özel</span>
                    <div class="plan-name"><?php echo htmlspecialchars($package['name'] ?? $link['package_id']); ?></div>
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
                        <span class="tick">
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

        <div class="card">
            <div class="card-body">
                <?php if (!empty($customer_mismatch)): ?>
                <div class="alert alert-warn">Bu bağlantı başka bir müşteri hesabı içindir. Lütfen bağlantının gönderildiği hesap ile giriş yapın.</div>
                <?php endif; ?>

                <?php if (!empty($link['target_name']) || !empty($link['target_email'])): ?>
                <?php
                $initials = '?';
                if (!empty($link['target_name'])) {
                    $parts = explode(' ', trim((string)$link['target_name']));
                    $initials = strtoupper(substr($parts[0] ?? '', 0, 1) . substr($parts[1] ?? '', 0, 1));
                }
                ?>
                <div class="target-info">
                    <div class="avatar"><?php echo htmlspecialchars($initials ?: '?'); ?></div>
                    <div>
                        <div class="who"><?php echo htmlspecialchars($link['target_name'] ?? 'Müşteri'); ?></div>
                        <div class="email"><?php echo htmlspecialchars($link['target_email'] ?? ''); ?></div>
                    </div>
                </div>
                <?php endif; ?>

                <h2>Güvenli ödemeye geçin</h2>
                <p class="lead">Kart bilgilerinizi iyzico güvencesiyle gireceksiniz. 3D Secure SMS onayı aynı sayfada tamamlanır; yeni sekme açılmaz.</p>

                <?php if ($link['mode'] === 'existing_customer' && empty($is_logged_in)): ?>
                <form method="GET" action="<?php echo $baseUrl; ?>/login">
                    <input type="hidden" name="redirect" value="<?php echo $baseUrl; ?>/pay/<?php echo htmlspecialchars($link['token']); ?>">
                    <button type="submit" class="btn btn-dark">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="16" height="16"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12H3m0 0l4-4m-4 4l4 4m6-10h4a2 2 0 012 2v8a2 2 0 01-2 2h-4"/></svg>
                        Giriş yap ve devam et
                    </button>
                </form>
                <?php else: ?>
                <form method="POST" action="<?php echo $baseUrl; ?>/pay/<?php echo htmlspecialchars($link['token']); ?>/start">
                    <?php echo csrf_field(); ?>
                    <button type="submit" class="btn btn-primary">
                        <svg fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" width="18" height="18"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        Güvenli ödemeye geç
                    </button>
                </form>
                <?php endif; ?>

                <p style="margin-top:0.9rem;text-align:center;font-size:0.72rem;color:#94a3b8;">Bağlantı tek kullanımlık olabilir. İşlemi yarım bırakmayın.</p>
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
            <svg fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001z" clip-rule="evenodd"/></svg>
            iyzico güvencesi
        </span>
        <span class="divider"></span>
        <span class="item">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4"/></svg>
            3D Secure
        </span>
    </div>
</div>
</body>
</html>
