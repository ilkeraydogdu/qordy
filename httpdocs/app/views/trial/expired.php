<?php
if (!defined('BASE_URL')) {
    require_once __DIR__ . '/../../config/config.php';
}
if (!function_exists('getAppConfig')) {
    require_once __DIR__ . '/../../helpers/functions.php';
}

$appName = 'Qordy';
try { $appName = htmlspecialchars(getAppConfig()->getAppName()); } catch (\Exception $e) {}

$baseUrl = defined('BASE_URL') ? BASE_URL : '';
if (empty($baseUrl)) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $baseUrl = rtrim($protocol . '://' . $host, '/');
}

$packages    = $packages ?? [];
$trialInfo   = $trialInfo ?? null;
$phase       = $phase ?? ($phaseInfo['phase'] ?? 'unknown');
$isSuspended = isset($isSuspended) ? (bool)$isSuspended : in_array($phase, ['suspended', 'expired'], true);
$graceDays   = isset($graceDays) ? max(0, (int)$graceDays) : 7; // sistem ayarından gelir
$graceLeft   = null;
if (!$isSuspended && is_array($phaseInfo) && isset($phaseInfo['grace_remaining_days'])) {
    $graceLeft = (int)$phaseInfo['grace_remaining_days'];
}

// Suspended: kırmızı ikon + "Hesabınız Askıya Alındı"
// Grace:     sarı  ikon + "Deneme Süreniz Sona Erdi (N gün kaldı)"
$pageHeading   = $isSuspended ? 'Hesabınız Askıya Alındı' : 'Deneme Süreniz Sona Erdi';
$pageSubheading = $isSuspended
    ? ('Deneme süreniz ve ' . $graceDays . ' günlük bekleme süreniz doldu. Sistemi kullanmaya devam etmek için aşağıdan bir plan seçerek hesabınızı yeniden etkinleştirebilirsiniz.')
    : ($graceLeft !== null
        ? 'Ücretsiz deneme süreniz doldu. Salt-okunur modda devam ediyoruz — hesabınızın otomatik askıya alınmasına ' . $graceLeft . ' gün kaldı. Tüm verileriniz güvende.'
        : 'Ücretsiz deneme süreniz doldu. Sistemi kullanmaya devam etmek için bir plan seçin. Tüm verileriniz güvende ve sizi bekliyor.');
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($pageHeading); ?> - <?php echo $appName; ?></title>
    <link rel="icon" type="image/png" href="<?php echo $baseUrl; ?>/assets/images/favicon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            /* Warm ember / amber + lime on dark ink — Qordy brand */
            --ink-950: #0b0f16; --ink-900: #131922; --ink-700: #313a48;
            --ink-600: #4a5462; --ink-500: #6b7480; --ink-400: #98a1ad;
            --ink-200: #e3e7ed; --ink-100: #eef1f5; --ink-50: #f7f9fc;
            --ember-50: #fff7ed; --ember-100: #ffedd5; --ember-200: #fed7aa;
            --ember-500: #f97316; --ember-600: #ea580c; --ember-700: #c2410c;
            --lime-50: #f7fee7; --lime-500: #84cc16; --lime-600: #65a30d;
            --success: #16a34a; --warning: #d97706; --error: #dc2626;
        }
        html { -webkit-text-size-adjust: 100%; }
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background:
                radial-gradient(1100px 520px at 50% -8%, var(--ember-50) 0%, transparent 62%),
                radial-gradient(900px 480px at 100% 100%, var(--lime-50) 0%, transparent 60%),
                var(--ink-50);
            color: var(--ink-900); min-height: 100vh; line-height: 1.55;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            padding: clamp(1.25rem, 4vw, 3rem);
            -webkit-font-smoothing: antialiased; text-rendering: optimizeLegibility;
        }
        .shell { max-width: 1040px; width: 100%; }

        /* ── Hero ── */
        .hero { text-align: center; margin-bottom: clamp(2rem, 5vw, 3.25rem); }
        .status-icon {
            width: 84px; height: 84px; margin: 0 auto 1.5rem;
            border-radius: 24px; display: flex; align-items: center; justify-content: center;
            position: relative;
        }
        .status-icon svg { width: 38px; height: 38px; stroke-width: 1.9; }
        .status-icon.suspended { background: linear-gradient(160deg, #fff1f1, #ffe4e4); color: var(--error); box-shadow: 0 12px 34px rgba(220,38,38,.16); }
        .status-icon.grace { background: linear-gradient(160deg, var(--ember-50), var(--ember-100)); color: var(--ember-700); box-shadow: 0 12px 34px rgba(234,88,12,.16); }
        .eyebrow {
            display: inline-flex; align-items: center; gap: .5rem;
            font-size: .72rem; font-weight: 700; letter-spacing: .14em; text-transform: uppercase;
            color: var(--ember-700); background: var(--ember-50); border: 1px solid var(--ember-200);
            padding: .35rem .8rem; border-radius: 999px; margin-bottom: 1.1rem;
        }
        .hero h1 {
            font-family: 'Fraunces', Georgia, serif;
            font-size: clamp(2rem, 4.6vw, 3.1rem); font-weight: 600; letter-spacing: -.02em;
            line-height: 1.08; color: var(--ink-950); margin-bottom: .85rem;
        }
        .hero p {
            font-size: clamp(1rem, 1.4vw, 1.12rem); color: var(--ink-500);
            max-width: 620px; margin: 0 auto;
        }
        .data-safe {
            display: inline-flex; align-items: center; gap: .55rem;
            padding: .6rem 1.1rem; border-radius: 12px; margin-top: 1.5rem;
            font-size: .85rem; font-weight: 600;
            background: #f0fdf4; border: 1px solid #bbf7d0; color: var(--success);
        }
        .data-safe svg { width: 17px; height: 17px; flex-shrink: 0; }
        .data-safe.is-grace { background: var(--ember-50); border-color: var(--ember-200); color: var(--ember-700); }
        .data-safe.is-suspended { background: #fff1f1; border-color: #fecaca; color: #b91c1c; }

        /* ── Pricing ── */
        .pricing-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 1.25rem; margin-bottom: 2.5rem; align-items: stretch;
        }
        .card {
            background: #fff; border-radius: 20px; padding: 1.75rem;
            border: 1px solid var(--ink-200); text-align: left; position: relative;
            display: flex; flex-direction: column;
            transition: transform .25s ease, box-shadow .25s ease, border-color .25s ease;
        }
        .card:hover { transform: translateY(-4px); box-shadow: 0 18px 50px rgba(15,23,42,.10); border-color: var(--ember-200); }
        .card.popular { border-color: var(--ember-500); box-shadow: 0 16px 44px rgba(234,88,12,.14); }
        .badge {
            position: absolute; top: -12px; left: 50%; transform: translateX(-50%);
            background: linear-gradient(135deg, var(--ember-500), var(--ember-600));
            color: #fff; padding: .32rem 1rem; border-radius: 999px;
            font-size: .68rem; font-weight: 800; letter-spacing: .06em; text-transform: uppercase;
            white-space: nowrap; box-shadow: 0 6px 16px rgba(234,88,12,.3);
        }
        .pkg-name { font-size: 1.2rem; font-weight: 700; color: var(--ink-900); margin-bottom: .3rem; }
        .pkg-desc { font-size: .86rem; color: var(--ink-500); margin-bottom: 1.25rem; min-height: 1.2em; }
        .pkg-price { display: flex; align-items: baseline; gap: .35rem; margin-bottom: .35rem; }
        .pkg-price .amount { font-size: 2.1rem; font-weight: 800; color: var(--ink-950); letter-spacing: -.02em; }
        .pkg-price .period { font-size: .9rem; font-weight: 600; color: var(--ink-400); }
        .pkg-save {
            display: inline-block; font-size: .74rem; font-weight: 700; color: var(--lime-600);
            background: var(--lime-50); border: 1px solid #d9f99d; padding: .15rem .55rem;
            border-radius: 8px; margin-bottom: 1rem;
        }
        .pkg-meta { font-size: .76rem; color: var(--ink-400); margin-bottom: 1rem; }
        .pkg-features { list-style: none; margin: 0 0 1.5rem; display: grid; gap: .55rem; }
        .pkg-features li { display: flex; align-items: flex-start; gap: .55rem; font-size: .86rem; color: var(--ink-700); }
        .pkg-features li svg { width: 17px; height: 17px; color: var(--lime-600); flex-shrink: 0; margin-top: .12rem; }
        .card .btn { margin-top: auto; }

        .btn {
            display: inline-flex; align-items: center; justify-content: center; gap: .5rem;
            padding: .8rem 1.5rem; font-size: .92rem; font-weight: 700; font-family: inherit;
            border: 1.5px solid transparent; border-radius: 12px; cursor: pointer;
            text-decoration: none; transition: transform .18s ease, box-shadow .18s ease, background .18s ease; width: 100%;
        }
        .btn:focus-visible { outline: 2px solid var(--ember-600); outline-offset: 2px; }
        .btn-primary { background: linear-gradient(135deg, var(--ember-500), var(--ember-600)); color: #fff; box-shadow: 0 8px 20px rgba(234,88,12,.28); }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 12px 26px rgba(234,88,12,.36); }
        .btn-secondary { background: #fff; color: var(--ink-700); border-color: var(--ink-200); }
        .btn-secondary:hover { border-color: var(--ember-500); color: var(--ember-700); background: var(--ember-50); }

        .single-cta { display: flex; justify-content: center; margin-bottom: 2.5rem; }
        .single-cta .btn { max-width: 320px; }

        .bottom-actions { display: flex; justify-content: center; align-items: center; gap: 1rem; flex-wrap: wrap; }
        .bottom-actions a { font-size: .86rem; font-weight: 600; color: var(--ink-500); text-decoration: none; transition: color .15s ease; }
        .bottom-actions a:hover { color: var(--ember-700); }
        .bottom-actions .sep { color: var(--ink-200); }

        @media (max-width: 640px) {
            .pricing-grid { grid-template-columns: 1fr; }
        }
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { transition: none !important; animation: none !important; }
        }
    </style>
</head>
<body>
    <div class="q-page animate-slide-up">
  <div class="q-container">
        <header class="hero">
            <div class="status-icon <?php echo $isSuspended ? 'suspended' : 'grace'; ?>">
                <?php if ($isSuspended): ?>
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                </svg>
                <?php else: ?>
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <?php endif; ?>
            </div>

            <span class="eyebrow"><?php echo $isSuspended ? 'Hesap Durumu' : 'Deneme Süresi'; ?></span>
            <h1><?php echo htmlspecialchars($pageHeading); ?></h1>
            <p><?php echo htmlspecialchars($pageSubheading); ?></p>

            <?php if ($isSuspended): ?>
            <div class="data-safe is-suspended">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                Plan seçildiğinde tüm özellikler anında geri açılır
            </div>
            <?php elseif ($graceLeft !== null): ?>
            <div class="data-safe is-grace">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Otomatik askıya alınmasına <?php echo (int)$graceLeft; ?> gün kaldı
            </div>
            <?php else: ?>
            <div class="data-safe">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                Verileriniz güvende ve korunuyor
            </div>
            <?php endif; ?>
        </header>

        <?php if (!empty($packages)): ?>
        <div class="pricing-grid">
            <?php foreach ($packages as $pkg):
                $name = htmlspecialchars($pkg['name'] ?? 'Paket');
                $desc = htmlspecialchars($pkg['description'] ?? $pkg['desc'] ?? '');
                $monthly = floatval($pkg['price_monthly'] ?? 0);
                $yearly  = floatval($pkg['price_yearly'] ?? 0);

                // Hangi fiyatın gösterileceği ve satın alma döngüsü TUTARLI olmalı.
                // (Önceki sürümde aylık fiyat gösterilip link her zaman yearly idi — bu bir hataydı.)
                if ($yearly > 0) {
                    $pricingType = 'yearly';
                    $displayPrice = $yearly;
                    $periodLabel = '/yıl';
                } elseif ($monthly > 0) {
                    $pricingType = 'monthly';
                    $displayPrice = $monthly;
                    $periodLabel = '/ay';
                } else {
                    $pricingType = 'one_time';
                    $displayPrice = floatval($pkg['price_one_time'] ?? 0);
                    $periodLabel = '';
                }

                // Yıllık planda aylığa göre tasarruf (yalnızca her ikisi de tanımlıysa).
                $savePct = 0;
                if ($yearly > 0 && $monthly > 0) {
                    $fullYear = $monthly * 12;
                    if ($fullYear > $yearly) {
                        $savePct = (int)round((($fullYear - $yearly) / $fullYear) * 100);
                    }
                }
                $monthlyEq = ($yearly > 0) ? ($yearly / 12) : 0;

                $features = $pkg['features_array'] ?? $pkg['features'] ?? [];
                if (is_string($features)) $features = json_decode($features, true) ?: [];
                $isPopular = !empty($pkg['is_featured']) || !empty($pkg['popular']);
                $pkgId = $pkg['package_id'] ?? '';
            ?>
            <div class="card <?php echo $isPopular ? 'popular' : ''; ?>">
                <?php if ($isPopular): ?>
                <div class="badge">En Popüler</div>
                <?php endif; ?>

                <div class="pkg-name"><?php echo $name; ?></div>
                <div class="pkg-desc"><?php echo $desc; ?></div>

                <div class="pkg-price">
                    <span class="amount">₺<?php echo number_format($displayPrice, 0, ',', '.'); ?></span>
                    <?php if ($periodLabel): ?><span class="period"><?php echo $periodLabel; ?></span><?php endif; ?>
                </div>
                <?php if ($savePct > 0): ?>
                <span class="pkg-save">Aylığa göre %<?php echo $savePct; ?> tasarruf</span>
                <?php endif; ?>
                <?php if ($monthlyEq > 0): ?>
                <div class="pkg-meta">≈ ₺<?php echo number_format($monthlyEq, 0, ',', '.'); ?>/ay eşdeğeri</div>
                <?php endif; ?>

                <?php
                $featureList = is_array($features) ? $features : [];
                if (!empty($featureList)):
                ?>
                <ul class="pkg-features">
                    <?php foreach ($featureList as $f):
                        $fText = is_array($f) ? ($f['name'] ?? $f['title'] ?? '') : $f;
                        if (empty($fText)) continue;
                    ?>
                    <li>
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                        <span><?php echo htmlspecialchars($fText); ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>

                <a href="<?php echo $baseUrl; ?>/customer/packages/<?php echo urlencode($pkgId); ?>/purchase?pricing_type=<?php echo $pricingType; ?>"
                   class="btn <?php echo $isPopular ? 'btn-primary' : 'btn-secondary'; ?>">
                    Hemen Satın Al
                </a>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="single-cta">
            <a href="<?php echo $baseUrl; ?>/customer/packages" class="btn btn-primary">
                Planları Görüntüle
            </a>
        </div>
        <?php endif; ?>

        <div class="bottom-actions">
            <a href="<?php echo $baseUrl; ?>/customer/packages">Tüm Planları Karşılaştır</a>
            <span class="sep">·</span>
            <a href="<?php echo $baseUrl; ?>/#contact">Destek Al</a>
            <span class="sep">·</span>
            <a href="<?php echo $baseUrl; ?>/logout">Çıkış Yap</a>
        </div>
    </div>
</body>
</html>
  </div>
</div>
