<?php
/**
 * Payment Success Page — premium full-page design.
 *
 * Rendered standalone (Controller::view() does a bare require for this
 * path, no layout wrapper). Visual language matches /customer/payment
 * and /pay/{token} flows so the user never feels they've jumped into
 * a different product at the end of a transaction.
 */

require_once __DIR__ . '/../../helpers/translations.php';

$transactionId = $transaction_id ?? '';
$message = $message ?? 'Ödemeniz başarıyla tamamlandı!';
$appName = 'Qordy';
try { $appName = htmlspecialchars(getAppConfig()->getAppName()); } catch (\Throwable $e) {}
$baseUrl = defined('BASE_URL') ? BASE_URL : '';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
        .steps{display:flex;align-items:center;justify-content:center;gap:.6rem;padding:1.4rem 1rem .25rem;font-family:'Plus Jakarta Sans',sans-serif}
        .step{display:flex;align-items:center;gap:.4rem;font-size:.78rem;font-weight:700}
        .step-dot{width:26px;height:26px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.7rem;font-weight:800}
        .step.done .step-dot{background:#10b981;color:#fff;box-shadow:0 4px 14px rgba(16,185,129,.35)}
        .step-line{width:40px;height:2px;background:#10b981;border-radius:2px}
        .shell{max-width:580px;margin:0 auto;padding:2rem 1.25rem 4rem}
        .card{background:#fff;border:1px solid rgba(15,23,42,.06);border-radius:24px;box-shadow:0 1px 2px rgba(15,23,42,.04),0 12px 40px -12px rgba(15,23,42,.12);padding:2.5rem 2rem;text-align:center;position:relative;overflow:hidden}
        .card::before{content:'';position:absolute;inset:0 0 auto 0;height:4px;background:linear-gradient(90deg,#10b981,#059669)}
        .ic{width:80px;height:80px;border-radius:50%;margin:0 auto 1.25rem;display:flex;align-items:center;justify-content:center;color:#fff;
            background:linear-gradient(135deg,#10b981,#059669);
            box-shadow:0 12px 32px -6px rgba(16,185,129,.55);animation:pop .5s cubic-bezier(.34,1.56,.64,1)}
        .ic svg{width:38px;height:38px}
        @keyframes pop{0%{transform:scale(.4);opacity:0}100%{transform:scale(1);opacity:1}}
        h1{font-family:'Plus Jakarta Sans',sans-serif;font-size:1.7rem;font-weight:900;color:#0f172a;letter-spacing:-.015em}
        .lead{margin-top:.6rem;color:#64748b;font-size:.95rem;line-height:1.6;max-width:420px;margin-left:auto;margin-right:auto}
        .tx-chip{display:inline-flex;align-items:center;gap:.5rem;padding:.55rem .95rem;background:linear-gradient(180deg,#fafbff,#fff);border:1px solid #eef2ff;border-radius:999px;margin-top:1.15rem;font-size:.78rem;color:#475569;font-weight:600;font-family:ui-monospace,SFMono-Regular,Menlo,monospace}
        .tx-chip svg{width:13px;height:13px;color:#10b981}
        .actions{display:flex;gap:.75rem;margin-top:1.6rem;flex-wrap:wrap;justify-content:center}
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
    <span style="font-size:.78rem;color:#10b981;font-weight:700;display:inline-flex;align-items:center;gap:.3rem;">
        <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" width="14" height="14"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
        Tamamlandı
    </span>
</nav>

<div class="steps">
    <div class="step done"><div class="step-dot"><svg width="12" height="12" fill="none" stroke="#fff" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg></div><span>Plan</span></div>
    <div class="step-line"></div>
    <div class="step done"><div class="step-dot"><svg width="12" height="12" fill="none" stroke="#fff" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg></div><span>Ödeme</span></div>
    <div class="step-line"></div>
    <div class="step done"><div class="step-dot"><svg width="12" height="12" fill="none" stroke="#fff" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg></div><span>Tamamlandı</span></div>
</div>

<div class="shell">
    <div class="card">
        <div class="ic">
            <svg fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
        </div>
        <h1>Ödemeniz Başarıyla Tamamlandı</h1>
        <p class="lead"><?php echo htmlspecialchars($message); ?></p>

        <?php if (!empty($transactionId)): ?>
            <div class="tx-chip">
                <svg fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                İşlem ID: <?php echo htmlspecialchars($transactionId); ?>
            </div>
        <?php endif; ?>

        <div class="actions">
            <?php if (!empty($public)): ?>
                <a href="<?php echo $baseUrl; ?>/login" class="btn btn-primary">
                    <svg fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" width="16" height="16"><path stroke-linecap="round" stroke-linejoin="round" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/></svg>
                    Giriş Yap
                </a>
            <?php else: ?>
                <a href="<?php echo $baseUrl; ?>/business/dashboard" class="btn btn-primary">
                    <svg fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" width="16" height="16"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                    Panelime Git
                </a>
                <a href="<?php echo $baseUrl; ?>/customer/subscription" class="btn btn-secondary">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="16" height="16"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                    Aboneliğim
                </a>
            <?php endif; ?>
        </div>
    </div>

    <p class="help">
        Sorun mu yaşıyorsunuz? <a href="mailto:destek@qordy.com">destek@qordy.com</a>
    </p>
</div>
</body>
</html>
