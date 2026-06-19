<?php
/**
 * Payment Fail Page — premium full-page design.
 *
 * Mirrors payment_success.php (same shell, typography, nav, steps) so
 * success/fail feel like two states of the same screen rather than two
 * different pages. Actions are scoped to what's useful on a failure:
 * retry, go back to the package list, or ask for help.
 */

require_once __DIR__ . '/../../helpers/translations.php';

$error = $error ?? 'Ödeme işlemi başarısız oldu.';
$message = $message ?? 'Ödeme işlemi tamamlanamadı. Lütfen tekrar deneyin.';
$appName = 'Qordy';
try { $appName = htmlspecialchars(getAppConfig()->getAppName()); } catch (\Throwable $e) {}
$baseUrl = defined('BASE_URL') ? BASE_URL : '';

// Figure out the "try again" target: if we know the subscription the
// user was trying to pay, bounce them straight back into /customer/payment
// so they don't have to re-select the package.
$subscriptionId = $_GET['subscription_id'] ?? '';
$retryUrl = $baseUrl . '/customer/packages';
if (!empty($subscriptionId) && preg_match('/^[a-zA-Z0-9_\-]+$/', $subscriptionId)) {
    $retryUrl = $baseUrl . '/customer/payment?subscription_id=' . urlencode($subscriptionId);
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ödeme Tamamlanamadı — <?php echo $appName; ?></title>
    <link rel="icon" type="image/png" href="<?php echo $baseUrl; ?>/assets/images/favicon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@700;800;900&display=swap" rel="stylesheet">
    <style>
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
        body{font-family:'Inter',system-ui,-apple-system,sans-serif;color:#0f172a;min-height:100vh;-webkit-font-smoothing:antialiased;
             background:radial-gradient(1000px 600px at 100% -10%,#fff1f2 0%,transparent 60%),radial-gradient(900px 500px at -10% 110%,#eef2ff 0%,transparent 60%),#f8fafc;}
        .nav{display:flex;align-items:center;justify-content:space-between;padding:1rem 1.5rem;border-bottom:1px solid rgba(15,23,42,.06);background:rgba(255,255,255,.6);backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px)}
        .brand{display:flex;align-items:center;gap:.6rem;font-weight:900;color:#0f172a;text-decoration:none}
        .brand img{height:28px}
        .steps{display:flex;align-items:center;justify-content:center;gap:.6rem;padding:1.4rem 1rem .25rem;font-family:'Plus Jakarta Sans',sans-serif}
        .step{display:flex;align-items:center;gap:.4rem;font-size:.78rem;font-weight:700;color:#94a3b8}
        .step-dot{width:26px;height:26px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.72rem;font-weight:800;background:#e2e8f0;color:#64748b}
        .step.done .step-dot{background:#10b981;color:#fff}
        .step.done{color:#334155}
        .step.fail .step-dot{background:linear-gradient(135deg,#ef4444,#dc2626);color:#fff;box-shadow:0 4px 14px rgba(239,68,68,.35)}
        .step.fail{color:#dc2626}
        .step-line{width:40px;height:2px;background:#cbd5e1;border-radius:2px}
        .step-line.done{background:#10b981}
        .shell{max-width:580px;margin:0 auto;padding:2rem 1.25rem 4rem}
        .card{background:#fff;border:1px solid rgba(15,23,42,.06);border-radius:24px;box-shadow:0 1px 2px rgba(15,23,42,.04),0 12px 40px -12px rgba(15,23,42,.12);padding:2.5rem 2rem;text-align:center;position:relative;overflow:hidden}
        .card::before{content:'';position:absolute;inset:0 0 auto 0;height:4px;background:linear-gradient(90deg,#ef4444,#dc2626)}
        .ic{width:80px;height:80px;border-radius:50%;margin:0 auto 1.25rem;display:flex;align-items:center;justify-content:center;color:#fff;
            background:linear-gradient(135deg,#ef4444,#dc2626);
            box-shadow:0 12px 32px -6px rgba(239,68,68,.5);animation:shake .5s ease}
        .ic svg{width:38px;height:38px}
        @keyframes shake{0%,100%{transform:translateX(0)}20%,60%{transform:translateX(-5px)}40%,80%{transform:translateX(5px)}}
        h1{font-family:'Plus Jakarta Sans',sans-serif;font-size:1.65rem;font-weight:900;color:#0f172a;letter-spacing:-.015em}
        .lead{margin-top:.6rem;color:#64748b;font-size:.95rem;line-height:1.6;max-width:420px;margin-left:auto;margin-right:auto}
        .err-chip{display:inline-flex;align-items:flex-start;gap:.55rem;padding:.75rem 1rem;background:#fef2f2;border:1px solid #fecaca;border-radius:14px;margin-top:1.15rem;font-size:.85rem;color:#991b1b;text-align:left;font-weight:600;max-width:100%}
        .err-chip svg{width:16px;height:16px;color:#dc2626;flex-shrink:0;margin-top:1px}
        .hint{margin-top:1.1rem;padding:.9rem 1rem;background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;font-size:.8rem;color:#475569;text-align:left;line-height:1.6}
        .hint b{color:#0f172a;font-weight:700;display:block;margin-bottom:.35rem}
        .hint ul{margin-left:1rem;margin-top:.25rem}
        .hint li{margin-top:.15rem}
        .actions{display:flex;gap:.75rem;margin-top:1.4rem;flex-wrap:wrap;justify-content:center}
        .btn{display:inline-flex;align-items:center;justify-content:center;gap:.45rem;padding:.9rem 1.35rem;font-size:.92rem;font-weight:800;font-family:inherit;border:none;border-radius:12px;cursor:pointer;text-decoration:none;transition:all .18s ease;flex:1;min-width:180px}
        .btn-primary{background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff;box-shadow:0 8px 22px -6px rgba(99,102,241,.55)}
        .btn-primary:hover{transform:translateY(-1px);box-shadow:0 12px 28px -6px rgba(99,102,241,.6)}
        .btn-secondary{background:#f1f5f9;color:#475569}
        .btn-secondary:hover{background:#e2e8f0;color:#1e293b}
        .help{margin-top:1.1rem;text-align:center;font-size:.75rem;color:#94a3b8}
        .help a{color:#6366f1;text-decoration:none;font-weight:600}
        @media (max-width:520px){.btn{flex:1 1 100%}}
    </style>
</head>
<body>
<nav class="nav">
    <a class="brand" href="<?php echo $baseUrl; ?>/">
        <img src="<?php echo $baseUrl; ?>/assets/images/logo.png" alt="<?php echo $appName; ?>">
    </a>
    <span style="font-size:.78rem;color:#dc2626;font-weight:700;display:inline-flex;align-items:center;gap:.3rem;">
        <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" width="14" height="14"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        Tamamlanamadı
    </span>
</nav>

<div class="steps">
    <div class="step done"><div class="step-dot"><svg width="12" height="12" fill="none" stroke="#fff" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg></div><span>Plan</span></div>
    <div class="step-line done"></div>
    <div class="step fail"><div class="step-dot">!</div><span>Ödeme</span></div>
    <div class="step-line"></div>
    <div class="step"><div class="step-dot">3</div><span>Tamamlandı</span></div>
</div>

<div class="shell">
    <div class="card">
        <div class="ic">
            <svg fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
        </div>
        <h1>Ödeme Tamamlanamadı</h1>
        <p class="lead"><?php echo htmlspecialchars($message); ?></p>

        <?php if (!empty($error) && $error !== $message): ?>
            <div class="err-chip">
                <svg fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86a2 2 0 001.74-3L13.74 4a2 2 0 00-3.48 0L3.33 16a2 2 0 001.74 3z"/></svg>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <div class="hint">
            <b>Olası çözümler</b>
            <ul>
                <li>Kart bilgilerinizi kontrol edip tekrar deneyin.</li>
                <li>Limit veya online alışveriş izninizi bankanızdan onaylayın.</li>
                <li>Farklı bir kart ile ödeme yapmayı deneyin.</li>
            </ul>
        </div>

        <div class="actions">
            <?php if (!empty($public)): ?>
                <a href="<?php echo $baseUrl; ?>/login" class="btn btn-primary">
                    <svg fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" width="16" height="16"><path stroke-linecap="round" stroke-linejoin="round" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/></svg>
                    Giriş Yap ve Tekrar Dene
                </a>
            <?php else: ?>
                <a href="<?php echo htmlspecialchars($retryUrl); ?>" class="btn btn-primary">
                    <svg fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" width="16" height="16"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    Tekrar Dene
                </a>
                <a href="<?php echo $baseUrl; ?>/customer/payment-history" class="btn btn-secondary">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="16" height="16"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Ödeme Geçmişi
                </a>
            <?php endif; ?>
        </div>
    </div>

    <p class="help">
        Sorun devam ediyor mu? <a href="mailto:destek@qordy.com">destek@qordy.com</a>
    </p>
</div>
</body>
</html>
