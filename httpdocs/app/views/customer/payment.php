<?php
/**
 * Payment Page — iyzico (primary) + Banka Havalesi (secondary)
 *
 * Design goals:
 *   - Two-column desktop layout: plan summary on the left, payment block
 *     on the right. Stacks vertically on mobile with the summary first
 *     so the user always sees what they are paying.
 *   - iyzico's CheckoutForm is embedded via a same-origin iframe that
 *     points at /customer/payment/iyzico/frame. All iyzico's 3D Secure
 *     SMS navigation stays inside that iframe; the post-3DS bridge
 *     posts back to us via `window.postMessage` and we drive the final
 *     navigation here, on the page that still has the live session.
 *   - Single-payment only — taksit yok. That's also enforced server-
 *     side via enabledInstallments:[1] in IyzicoGateway.
 */

require_once __DIR__ . '/../../helpers/translations.php';

if (!defined('BASE_URL')) {
    require_once __DIR__ . '/../../config/config.php';
}

$subscriptionId = $_GET['subscription_id'] ?? '';
$subscription = $subscription ?? null;
$package = $package ?? null;
$baseUrl = defined('BASE_URL') ? BASE_URL : '';

if (empty($subscriptionId) && !$subscription) {
    header('Location: ' . $baseUrl . '/business/dashboard');
    exit;
}

if (!$subscription && !empty($subscriptionId)) {
    try {
        require_once __DIR__ . '/../../core/DependencyFactory.php';
        $subscriptionRepo = \App\Core\DependencyFactory::getSubscriptionRepository();
        $subscription = $subscriptionRepo->getSubscriptionWithPackage($subscriptionId);
        if ($subscription) {
            $packageService = \App\Core\DependencyFactory::getPackageService();
            $package = $packageService->getPackageById($subscription['package_id']);
        }
    } catch (\Exception $e) {}
}

if (!$subscription || !$package) {
    header('Location: ' . $baseUrl . '/business/dashboard');
    exit;
}

$bankTransferEnabled = false;
try {
    $settingsService = \App\Core\DependencyFactory::getSystemSettingsService();
    $v = $settingsService->getSetting('payment_bank_transfer_enabled', '0');
    $bankTransferEnabled = ($v === '1' || $v === 1 || $v === true || (is_string($v) && strcasecmp($v, 'true') === 0));
} catch (\Exception $e) {
    $bankTransferEnabled = false;
}

$iyzicoEnabled = false;
try {
    $pgRepo = \App\Core\DependencyFactory::getPaymentGatewayRepository();
    $iyzicoGw = $pgRepo->getByCode('iyzico');
    $iyzicoEnabled = $iyzicoGw && ($iyzicoGw['is_enabled'] ?? 0) == 1;
} catch (\Exception $e) {
    $iyzicoEnabled = false;
}

$billingCycle = $subscription['billing_cycle'] ?? 'yearly';

// Single authoritative price — mirror the rule used server-side in
// prepareIyzicoCheckout() so the UI total never drifts from the charge.
if (!isset($amount) || $amount === null || $amount === '' || floatval($amount) <= 0) {
    $amount = floatval($subscription['amount'] ?? 0);
    if ($amount <= 0) {
        require_once __DIR__ . '/../../core/DependencyFactory.php';
        $packageService = \App\Core\DependencyFactory::getPackageService();
        $amount = (float)$packageService->getDiscountedPrice($package, $billingCycle);
    }
    if ($amount <= 0) {
        $priceField = 'price_' . $billingCycle;
        $amount = floatval($package[$priceField] ?? 0);
    }
} else {
    $amount = floatval($amount);
}

// Secondary price info for the summary pane.
$rawYearly = (float)($package['price_yearly'] ?? 0);
$yearlyDiscount = (float)($package['yearly_discount'] ?? 0);
$monthlyEquivalent = $amount > 0 ? $amount / 12 : 0;
$savings = ($rawYearly > $amount) ? ($rawYearly - $amount) : 0;

// Package features (already parsed by PackageService when available).
$features = $package['features_array'] ?? [];
if (is_string($features)) {
    $decoded = json_decode($features, true);
    $features = is_array($decoded) ? $decoded : [];
}

$appName = 'Qordy';
try { $appName = htmlspecialchars(getAppConfig()->getAppName()); } catch (\Exception $e) {}

$payResult = $pay_result ?? $_GET['pay_result'] ?? null;
$payError = $pay_error ?? (isset($_GET['error']) ? (string) $_GET['error'] : null);
$payTransactionId = $pay_transaction_id ?? $_GET['transaction_id'] ?? null;
$showPaymentForm = ($payResult !== 'success');
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <?php require_once __DIR__ . '/../partials/google_analytics.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php
    require_once __DIR__ . '/../../core/Security/CSRFManager.php';
    $csrfToken = \App\Core\Security\CSRFManager::generateToken();
    ?>
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrfToken); ?>">
    <title>Güvenli Ödeme — <?php echo $appName; ?></title>
    <link rel="icon" type="image/png" href="<?php echo $baseUrl; ?>/assets/images/favicon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@700;800;900&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; }
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

        /* ── Top nav ───────────────────────────────────────────────── */
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

        /* ── Step indicator ────────────────────────────────────────── */
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

        /* ── Layout ────────────────────────────────────────────────── */
        .shell { max-width: 1080px; margin: 0 auto; padding: 1.5rem 1.25rem 4rem; }
        .grid { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1.4fr); gap: 1.25rem; align-items: flex-start; }
        @media (max-width: 900px) { .grid { grid-template-columns: 1fr; } }

        /* ── Cards ─────────────────────────────────────────────────── */
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

        /* ── Summary sticky ────────────────────────────────────────── */
        .summary-sticky { position: sticky; top: 88px; }
        @media (max-width: 900px) { .summary-sticky { position: static; } }

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
        .price-old {
            font-size: 0.85rem; color: #94a3b8; text-decoration: line-through; font-weight: 700;
            margin-right: 0.5rem;
        }
        .price-save {
            display: inline-flex; align-items: center; gap: 0.25rem;
            padding: 0.2rem 0.55rem; border-radius: 999px;
            background: #ecfdf5; color: #047857;
            font-size: 0.7rem; font-weight: 800;
            margin-top: 0.5rem;
        }
        .price-monthly {
            margin-top: 0.35rem;
            font-size: 0.78rem; color: #64748b; font-weight: 600;
        }

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

        /* ── Payment method tabs ───────────────────────────────────── */
        .method-row { display: grid; grid-template-columns: 1fr 1fr; gap: 0.6rem; margin-top: 0.8rem; }
        @media (max-width: 520px) { .method-row { grid-template-columns: 1fr; } }
        .method {
            position: relative;
            display: flex; align-items: center; gap: 0.75rem;
            padding: 0.9rem 1rem; border: 2px solid #e2e8f0; border-radius: 14px;
            cursor: pointer; transition: all 0.18s ease;
            background: #fff;
        }
        .method:hover { border-color: #c7d2fe; transform: translateY(-1px); }
        .method.selected {
            border-color: #6366f1;
            background: linear-gradient(180deg, #faf5ff 0%, #eef2ff 100%);
            box-shadow: 0 0 0 4px rgba(99,102,241,0.08);
        }
        .method input { position: absolute; opacity: 0; pointer-events: none; }
        .method-icon {
            width: 40px; height: 40px; border-radius: 10px;
            background: #eef2ff; color: #4f46e5;
            display: inline-flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        .method-icon svg { width: 20px; height: 20px; }
        .method.selected .method-icon { background: #6366f1; color: #fff; }
        .method-info h4 { font-size: 0.88rem; font-weight: 800; color: #0f172a; }
        .method-info p { font-size: 0.72rem; color: #64748b; margin-top: 0.1rem; }

        /* ── iyzico iframe container ───────────────────────────────── */
        .pay-frame-wrap {
            position: relative;
            margin-top: 1rem;
            min-height: 620px;
            border-radius: 16px; overflow: hidden;
            border: 1px solid #e2e8f0; background: #fff;
            transition: height 0.18s ease;
        }
        /* The iframe height is driven by postMessage from the inner
           frame — no hard min-height on the iframe itself so we don't
           create an internal scrollbar when iyzico renders short. */
        #iyzico-iframe { width: 100%; height: 620px; border: 0; display: block; background: transparent; }
        #iyzico-loading {
            position: absolute; inset: 0;
            display: flex; align-items: center; justify-content: center;
            background: linear-gradient(180deg, #fafbff 0%, #ffffff 100%);
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        .spinner { width: 32px; height: 32px; border: 3px solid #e2e8f0; border-top-color: #6366f1; border-radius: 50%; animation: spin 0.8s linear infinite; margin: 0 auto 0.7rem; }
        #iyzico-error {
            display: none; padding: 1rem 1.25rem;
            background: #fef2f2; color: #991b1b;
            border: 1px solid #fecaca; border-radius: 12px;
            font-size: 0.88rem; margin-top: 0.75rem;
        }

        /* ── Bank transfer panel ───────────────────────────────────── */
        .bank-panel {
            display: none;
            margin-top: 1rem;
            padding: 1.25rem;
            border-radius: 16px;
            background: linear-gradient(135deg, #eef2ff 0%, #f5f3ff 100%);
            border: 1px solid #c7d2fe;
        }
        .bank-panel.visible { display: block; }
        .bank-panel h4 { font-size: 0.9rem; font-weight: 800; color: #3730a3; margin-bottom: 0.45rem; }
        .bank-panel p { font-size: 0.8rem; color: #4338ca; line-height: 1.6; }

        .btn-actions { display: flex; gap: 0.75rem; margin-top: 1.1rem; }
        .btn {
            flex: 1; display: inline-flex; align-items: center; justify-content: center;
            gap: 0.45rem;
            padding: 0.9rem 1.25rem; font-size: 0.9rem; font-weight: 800;
            font-family: inherit; border: none; border-radius: 12px;
            cursor: pointer; text-decoration: none; transition: all 0.18s ease;
            letter-spacing: 0.01em;
        }
        .btn-primary {
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: #fff; box-shadow: 0 8px 22px -6px rgba(99,102,241,0.55);
        }
        .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 12px 28px -6px rgba(99,102,241,0.6); }
        .btn-secondary { background: #f1f5f9; color: #475569; }
        .btn-secondary:hover { background: #e2e8f0; color: #1e293b; }

        /* ── Trust row ─────────────────────────────────────────────── */
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
            .btn-actions { flex-direction: column; }
        }
        .pay-flash {
            max-width: 1200px; margin: 0 auto 1rem; padding: 1rem 1.25rem; border-radius: 16px;
            font-size: 0.9rem; line-height: 1.5; font-weight: 600;
        }
        .pay-flash-ok {
            background: #ecfdf5; border: 1px solid #6ee7b7; color: #065f46;
        }
        .pay-flash-bad {
            background: #fef2f2; border: 1px solid #fecaca; color: #991b1b;
        }
    </style>
</head>
<body>
    <nav class="nav">
        <a class="brand" href="<?php echo $baseUrl; ?>/">
            <img src="<?php echo $baseUrl; ?>/assets/images/logo.png" alt="<?php echo $appName; ?>">
        </a>
        <a class="back" href="<?php echo $baseUrl; ?>/business/dashboard">
            <svg fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" width="14" height="14"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            Dashboard'a Dön
        </a>
    </nav>

    <div class="steps">
        <div class="step done">
            <div class="step-dot">
                <svg width="12" height="12" fill="none" stroke="#fff" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            </div>
            <span>Plan Seçimi</span>
        </div>
        <div class="step-line"></div>
        <div class="step <?php echo $payResult === 'success' ? 'done' : 'active'; ?>">
            <?php if ($payResult === 'success'): ?>
            <div class="step-dot">
                <svg width="12" height="12" fill="none" stroke="#fff" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            </div>
            <?php else: ?>
            <div class="step-dot">2</div>
            <?php endif; ?>
            <span>Ödeme</span>
        </div>
        <div class="step-line"></div>
        <div class="step <?php echo $payResult === 'success' ? 'done' : 'inactive'; ?>">
            <?php if ($payResult === 'success'): ?>
            <div class="step-dot">
                <svg width="12" height="12" fill="none" stroke="#fff" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            </div>
            <?php else: ?>
            <div class="step-dot">3</div>
            <?php endif; ?>
            <span>Tamamlandı</span>
        </div>
    </div>

    <div class="shell">
        <?php if ($payResult === 'success'): ?>
        <div class="pay-flash pay-flash-ok" role="status">
            Ödemeniz onaylandı. Aboneliğiniz aktifleştirildi.
            <?php if (!empty($payTransactionId)): ?>
            <span style="display:block;font-size:0.78rem;opacity:0.85;margin-top:0.35rem;">İşlem ref: <?php echo htmlspecialchars($payTransactionId); ?></span>
            <?php endif; ?>
        </div>
        <?php elseif ($payResult === 'fail' && $payError !== null && $payError !== ''): ?>
        <div class="pay-flash pay-flash-bad" role="alert">
            <?php echo htmlspecialchars($payError); ?>
            <span style="display:block;font-size:0.78rem;font-weight:500;margin-top:0.4rem;opacity:0.9;">Kart bilgilerinizi kontrol edip aşağıdan tekrar deneyebilirsiniz. Banka 3D ekranı tüm pencereye açılmış olsa da sonuç bu ödeme sayfasında gösterilir.</span>
        </div>
        <?php endif; ?>
        <div class="grid">
            <!-- ═════ LEFT: plan summary ═════ -->
            <aside class="summary-sticky">
                <div class="card">
                    <div class="plan-hero">
                        <span class="plan-badge">
                            <svg fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" width="12" height="12"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                            Yıllık Abonelik
                        </span>
                        <div class="plan-name"><?php echo htmlspecialchars($package['name'] ?? 'Paket'); ?></div>
                        <?php if (!empty($package['description'])): ?>
                            <div class="plan-desc"><?php echo htmlspecialchars($package['description']); ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="price-block">
                        <div class="price-row">
                            <div>
                                <?php if ($savings > 0 && $rawYearly > 0): ?>
                                    <span class="price-old"><?php echo number_format($rawYearly, 0, ',', '.'); ?> ₺</span>
                                <?php endif; ?>
                                <span class="price-amount"><?php echo number_format($amount, 0, ',', '.'); ?><span class="price-currency"> ₺</span></span>
                            </div>
                            <span class="price-period">/ yıl (peşin)</span>
                        </div>
                        <?php if ($monthlyEquivalent > 0): ?>
                            <div class="price-monthly">Aylık karşılığı ~<?php echo number_format($monthlyEquivalent, 0, ',', '.'); ?> ₺ · yıllık ödeme</div>
                        <?php endif; ?>
                        <?php if ($savings > 0): ?>
                            <div class="price-save">
                                <svg fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24" width="12" height="12"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                <?php echo number_format($savings, 0, ',', '.'); ?> ₺ tasarruf
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if (is_array($features) && !empty($features)): ?>
                    <div class="features">
                        <h3>Pakete Dahil</h3>
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
                        <span class="value"><?php echo number_format($amount, 2, ',', '.'); ?> ₺</span>
                    </div>
                </div>
            </aside>

            <!-- ═════ RIGHT: payment ═════ -->
            <section>
                <div class="card">
                    <div class="card-body">
                        <?php if (!$showPaymentForm): ?>
                        <h2>Ödeme alındı</h2>
                        <p style="color:#64748b;font-size:0.9rem;line-height:1.55;margin:0.5rem 0 1.25rem;">Teşekkürler! İşletme panelinize dönüp tüm özelliklere devam edebilirsiniz.</p>
                        <a href="<?php echo $baseUrl; ?>/business/dashboard" class="btn btn-primary" style="width:100%;">İşletme paneline git</a>
                        <?php else: ?>
                        <h2>
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M5 6h14a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2z"/></svg>
                            Ödeme Yöntemi
                        </h2>

                        <form method="POST" action="<?php echo $baseUrl; ?>/customer/payment/process" id="payment-form">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="subscription_id" value="<?php echo htmlspecialchars($subscription['subscription_id']); ?>">

                            <div class="method-row">
                                <?php if ($iyzicoEnabled): ?>
                                <label class="method selected" id="option-iyzico" onclick="selectMethod('iyzico')">
                                    <input type="radio" name="payment_method" value="iyzico" checked>
                                    <span class="method-icon">
                                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="3"/><path d="M2 10h20" stroke-linecap="round"/><path d="M6 15h4" stroke-linecap="round"/></svg>
                                    </span>
                                    <span class="method-info">
                                        <h4>Kredi / Banka Kartı</h4>
                                        <p>3D Secure · iyzico güvencesi</p>
                                    </span>
                                </label>
                                <?php endif; ?>

                                <?php if ($bankTransferEnabled): ?>
                                <label class="method<?php echo !$iyzicoEnabled ? ' selected' : ''; ?>" id="option-bank" onclick="selectMethod('bank_transfer')">
                                    <input type="radio" name="payment_method" value="bank_transfer"<?php echo !$iyzicoEnabled ? ' checked' : ''; ?>>
                                    <span class="method-icon">
                                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 21h18M4 10h16l-8-7-8 7zm3 0v8m4-8v8m4-8v8m4-8v8"/></svg>
                                    </span>
                                    <span class="method-info">
                                        <h4>Havale / EFT</h4>
                                        <p>Banka hesabına transfer</p>
                                    </span>
                                </label>
                                <?php endif; ?>
                            </div>

                            <!-- iyzico inline frame (same-origin): 3DS SMS adımı da burada tamamlanır -->
                            <div class="pay-frame-wrap" id="iyzico-fields" style="display:none;">
                                <iframe id="iyzico-iframe"
                                        title="Güvenli ödeme"
                                        allow="payment"
                                        referrerpolicy="origin"
                                        src="about:blank"></iframe>
                                <div id="iyzico-loading">
                                    <div style="text-align:center;">
                                        <div class="spinner"></div>
                                        <p style="color:#64748b;font-size:0.85rem;font-weight:600;">Güvenli ödeme formu yükleniyor…</p>
                                    </div>
                                </div>
                            </div>
                            <div id="iyzico-error"></div>

                            <div class="bank-panel" id="bank-info">
                                <h4>Havale ile Ödeme</h4>
                                <p>Havale seçeneğini onayladığınızda banka bilgileri ve size özel ödeme kodu görüntülenecek. Havale sonrası dekontunuzu yükleyerek ödemenizi tamamlayabilirsiniz.</p>
                                <div class="btn-actions" style="margin-top:1rem;">
                                    <button type="submit" class="btn btn-primary" id="submit-btn">Havale Bilgilerini Gör</button>
                                </div>
                            </div>
                        </form>
                        <?php endif; ?>
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
                        3D Secure doğrulama
                    </span>
                </div>
            </section>
        </div>
    </div>

    <script>
    const baseUrl = <?php echo json_encode($baseUrl); ?>;
    const subscriptionId = <?php echo json_encode($subscription['subscription_id']); ?>;
    const iyzicoEnabled = <?php echo json_encode($iyzicoEnabled); ?>;
    const showPaymentForm = <?php echo $showPaymentForm ? 'true' : 'false'; ?>;

    function selectMethod(method) {
        document.querySelectorAll('.method').forEach(el => el.classList.remove('selected'));
        const optionId = method === 'bank_transfer' ? 'bank' : method;
        const el = document.getElementById('option-' + optionId);
        if (el) el.classList.add('selected');
        const radio = document.querySelector('input[value="' + method + '"]');
        if (radio) radio.checked = true;

        const iyzico = document.getElementById('iyzico-fields');
        const bank = document.getElementById('bank-info');

        if (method === 'iyzico') {
            iyzico.style.display = '';
            bank.classList.remove('visible');
            initIyzicoPayment();
        } else {
            iyzico.style.display = 'none';
            bank.classList.add('visible');
        }
    }

    let iyzicoInitialized = false;
    function initIyzicoPayment() {
        if (iyzicoInitialized) return;
        iyzicoInitialized = true;

        const iframe = document.getElementById('iyzico-iframe');
        const loading = document.getElementById('iyzico-loading');
        const errBox = document.getElementById('iyzico-error');
        if (!iframe) return;
        if (loading) loading.style.display = 'flex';
        if (errBox) { errBox.style.display = 'none'; errBox.textContent = ''; }

        // Same-origin, server-rendered page hosts iyzico's form. Keeping
        // it in an iframe means iyzico's 3DS navigation (bank SMS page,
        // callback POST) all happens inside the frame — our main page
        // keeps its session cookies untouched and can drive the final
        // top-level navigation via the postMessage bridge below.
        iframe.addEventListener('load', function () {
            if (loading) loading.style.display = 'none';
        }, { once: true });

        iframe.src = baseUrl + '/customer/payment/iyzico/frame?subscription_id=' + encodeURIComponent(subscriptionId);
    }

    // Bridge listener — callback page posts the outcome here once iyzico
    // has verified the 3DS result. Origin + payload shape are locked.
    window.addEventListener('message', function (ev) {
        if (!ev || !ev.data || typeof ev.data !== 'object') return;
        if (ev.origin !== window.location.origin) return;
        const data = ev.data;
        if (data.type === 'iyzico:resize') {
            const h = Math.max(parseInt(data.height, 10) || 0, 560);
            const iframe = document.getElementById('iyzico-iframe');
            const wrap = document.querySelector('.pay-frame-wrap');
            if (iframe) iframe.style.height = h + 'px';
            if (wrap) wrap.style.minHeight = h + 'px';
            return;
        }
        if (data.type === 'iyzico:result') {
            if (data.status === 'success') {
                const p = new URLSearchParams();
                p.set('subscription_id', subscriptionId);
                p.set('pay_result', 'success');
                if (data.transactionId) p.set('transaction_id', data.transactionId);
                window.location.replace(baseUrl + '/customer/payment?' + p.toString());
            } else {
                const p = new URLSearchParams();
                p.set('subscription_id', subscriptionId);
                p.set('pay_result', 'fail');
                p.set('error', data.error || 'Ödeme tamamlanamadı');
                window.location.replace(baseUrl + '/customer/payment?' + p.toString());
            }
        } else if (data.type === 'iyzico:init-fail') {
            const errBox = document.getElementById('iyzico-error');
            const loading = document.getElementById('iyzico-loading');
            if (loading) loading.style.display = 'none';
            if (errBox) { errBox.style.display = 'block'; errBox.textContent = 'Ödeme başlatılamadı: ' + (data.error || 'Bilinmeyen hata'); }
        }
    }, false);

    const payForm = document.getElementById('payment-form');
    if (payForm) {
        payForm.addEventListener('submit', function (e) {
            const selected = document.querySelector('input[name="payment_method"]:checked')?.value;
            if (selected === 'iyzico') { e.preventDefault(); return false; }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        if (showPaymentForm && iyzicoEnabled) { selectMethod('iyzico'); }
        try {
            var u = new URL(window.location.href);
            if (u.searchParams.get('pay_result')) {
                u.searchParams.delete('pay_result');
                u.searchParams.delete('error');
                u.searchParams.delete('transaction_id');
                window.history.replaceState({}, '', u.pathname + (u.search || ''));
            }
        } catch (e) {}
    });
    </script>
</body>
</html>
