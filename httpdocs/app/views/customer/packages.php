<?php
/**
 * Customer Packages Page - Standalone
 */

require_once __DIR__ . '/../../helpers/translations.php';

$packages = $packages ?? [];
$activeSubscription = $activeSubscription ?? null;
$customer = $customer ?? null;
$highlightPackageId = $highlightPackageId ?? null;

if (!defined('BASE_URL')) {
    require_once __DIR__ . '/../../config/config.php';
}

$baseUrl = defined('BASE_URL') ? BASE_URL : '';

$appName = 'Qordy';
try { $appName = htmlspecialchars(getAppConfig()->getAppName()); } catch (\Exception $e) {}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Planlar - <?php echo $appName; ?></title>
    <link rel="icon" type="image/png" href="<?php echo $baseUrl; ?>/assets/images/favicon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --primary: #6366f1; --primary-dark: #4f46e5;
            --g900: #0f172a; --g600: #475569; --g500: #64748b;
            --g400: #94a3b8; --g200: #e2e8f0; --g100: #f1f5f9; --g50: #f8fafc;
        }
        body {
            font-family: 'Inter', system-ui, sans-serif;
            background: var(--g50);
            color: var(--g900);
            min-height: 100vh;
            -webkit-font-smoothing: antialiased;
        }
        .container { max-width: 1100px; margin: 0 auto; padding: 0 1.5rem; }
        
        .page-nav {
            display: flex; align-items: center; justify-content: space-between;
            padding: 1rem 0;
            border-bottom: 1px solid var(--g200);
            margin-bottom: 2rem;
        }
        .page-nav img { height: 28px; }
        .page-nav a { color: var(--g500); text-decoration: none; font-size: 0.875rem; font-weight: 600; }
        .page-nav a:hover { color: var(--primary); }
        
        .page-header { text-align: center; padding: 3rem 0 2.5rem; }
        .page-header h1 {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 2.5rem; font-weight: 900; color: var(--g900);
            letter-spacing: -0.03em; line-height: 1.1; margin-bottom: 0.75rem;
        }
        .page-header p { color: var(--g500); font-size: 1.05rem; max-width: 500px; margin: 0 auto; }
        
        .active-sub {
            background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 12px;
            padding: 1rem 1.5rem; display: flex; align-items: center; justify-content: space-between;
            flex-wrap: wrap; gap: 1rem; margin-bottom: 2rem;
        }
        .active-sub-info { display: flex; align-items: center; gap: 0.75rem; }
        .active-sub-dot { width: 8px; height: 8px; background: #22c55e; border-radius: 50%; }
        .active-sub h3 { font-size: 0.9rem; font-weight: 700; color: #166534; }
        .active-sub p { font-size: 0.8rem; color: #16a34a; }
        
        .packages-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem; padding-bottom: 4rem;
        }
        .pkg-card {
            background: #fff; border: 2px solid var(--g200); border-radius: 16px;
            padding: 2rem; display: flex; flex-direction: column;
            transition: all 0.3s; position: relative;
        }
        .pkg-card:hover { box-shadow: 0 12px 40px rgba(0,0,0,0.08); transform: translateY(-4px); }
        .pkg-card.featured { border-color: var(--primary); box-shadow: 0 8px 32px rgba(99,102,241,0.12); }
        .pkg-badge {
            position: absolute; top: -12px; left: 50%; transform: translateX(-50%);
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: #fff; font-size: 0.75rem; font-weight: 800;
            padding: 0.35rem 1.25rem; border-radius: 999px; white-space: nowrap;
        }
        .pkg-card h3 { font-size: 1.35rem; font-weight: 900; color: var(--g900); margin-bottom: 0.25rem; }
        .pkg-card .desc { font-size: 0.875rem; color: var(--g500); margin-bottom: 1.25rem; }
        .pkg-price { margin-bottom: 1.5rem; }
        .pkg-price .old { font-size: 0.85rem; color: var(--g400); text-decoration: line-through; }
        .pkg-price .discount { font-size: 0.75rem; color: #16a34a; font-weight: 700; background: #f0fdf4; padding: 0.15rem 0.5rem; border-radius: 4px; margin-left: 0.5rem; }
        .pkg-price .amount { font-family: 'Plus Jakarta Sans', sans-serif; font-size: 2.25rem; font-weight: 900; color: var(--g900); }
        .pkg-price .period { font-size: 0.9rem; color: var(--g400); }
        .pkg-price .monthly { font-size: 0.8rem; color: var(--g400); margin-top: 0.25rem; }
        
        .pkg-features { list-style: none; flex: 1; margin-bottom: 1.5rem; }
        .pkg-features li {
            display: flex; align-items: flex-start; gap: 0.5rem;
            padding: 0.4rem 0; font-size: 0.875rem; color: var(--g600);
        }
        .pkg-features svg { width: 16px; height: 16px; color: #22c55e; flex-shrink: 0; margin-top: 0.1rem; }
        
        .btn-pkg {
            display: block; width: 100%; text-align: center; padding: 0.8rem 1.5rem;
            font-size: 0.95rem; font-weight: 700; border-radius: 10px;
            border: none; cursor: pointer; text-decoration: none;
            transition: all 0.2s;
        }
        .btn-pkg-primary {
            background: var(--primary); color: #fff;
            box-shadow: 0 4px 14px rgba(99,102,241,0.35);
        }
        .btn-pkg-primary:hover { background: var(--primary-dark); transform: translateY(-2px); box-shadow: 0 6px 20px rgba(99,102,241,0.45); }
        .btn-pkg-secondary { background: var(--g100); color: var(--g900); }
        .btn-pkg-secondary:hover { background: var(--g200); }
        
        .empty-state {
            text-align: center; padding: 4rem 2rem;
            background: #fff; border-radius: 16px; border: 1px solid var(--g200);
        }
        .empty-state h3 { font-size: 1.25rem; font-weight: 800; color: var(--g900); margin-bottom: 0.5rem; }
        .empty-state p { color: var(--g500); font-size: 0.95rem; }
        
        .page-footer {
            text-align: center; padding: 2rem 0; border-top: 1px solid var(--g200);
            margin-top: 2rem;
        }
        .page-footer p { font-size: 0.8rem; color: var(--g400); }
        .page-footer a { color: var(--g400); }
        .page-footer a:hover { color: var(--primary); }
        
        @media (max-width: 640px) {
            .page-header h1 { font-size: 1.75rem; }
            .packages-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="container">
        <nav class="page-nav">
            <a href="<?php echo $baseUrl; ?>/">
                <img src="<?php echo $baseUrl; ?>/assets/images/logo.png" alt="<?php echo $appName; ?>">
            </a>
            <a href="<?php echo $baseUrl; ?>/">← Ana Sayfa</a>
        </nav>
        
        <div class="page-header">
            <h1>İşletmenize Uygun<br>Planı Seçin</h1>
            <p>Tüm planlarda 14 gün ücretsiz deneme. Kredi kartı gerekmez.</p>
        </div>
        
        <?php if ($activeSubscription): ?>
        <div class="active-sub">
            <div class="active-sub-info">
                <div class="active-sub-dot"></div>
                <div>
                    <h3>Aktif: <?php echo htmlspecialchars($activeSubscription['package_name'] ?? ''); ?></h3>
                    <p>
                        <?php if ($activeSubscription['end_date']): ?>
                        Bitiş: <?php echo date('d.m.Y', strtotime($activeSubscription['end_date'])); ?>
                        <?php else: ?>
                        Süresiz
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if (empty($packages)): ?>
        <div class="empty-state">
            <h3>Henüz Plan Bulunmuyor</h3>
            <p>Lütfen daha sonra tekrar deneyiniz.</p>
        </div>
        <?php else: ?>
        <div class="packages-grid">
            <?php foreach ($packages as $package): 
                $packageName = htmlspecialchars($package['name'] ?? 'Paket');
                $description = htmlspecialchars($package['description'] ?? '');
                $packageId = htmlspecialchars($package['package_id'] ?? '');
                $featuresArray = $package['features_array'] ?? [];
                $yearlyDiscount = floatval($package['yearly_discount'] ?? 0);
                $isFeatured = !empty($package['is_featured']);
                $yearlyPrice = floatval($package['price_yearly'] ?? 0);
                $discountedYearly = floatval($package['discounted_price_yearly'] ?? $yearlyPrice);
                $monthlyEquiv = $yearlyPrice > 0 ? round($discountedYearly / 12, 0) : 0;
                $isHighlighted = ($highlightPackageId && $packageId === $highlightPackageId);
            ?>
            <div id="package-<?php echo $packageId; ?>" class="pkg-card <?php echo $isFeatured ? 'featured' : ''; ?>">
                <?php if ($isFeatured): ?>
                <div class="pkg-badge">Önerilen</div>
                <?php endif; ?>
                
                <h3><?php echo $packageName; ?></h3>
                <?php if (!empty($description)): ?>
                <p class="desc"><?php echo $description; ?></p>
                <?php endif; ?>
                
                <?php if ($yearlyPrice > 0): ?>
                <div class="pkg-price">
                    <?php if ($yearlyDiscount > 0): ?>
                    <span class="old">₺<?php echo number_format($yearlyPrice, 0, ',', '.'); ?></span>
                    <span class="discount">%<?php echo number_format($yearlyDiscount, 0); ?> indirim</span>
                    <br>
                    <?php endif; ?>
                    <span class="amount">₺<?php echo number_format($discountedYearly, 0, ',', '.'); ?></span>
                    <span class="period">/yıl</span>
                    <?php if ($monthlyEquiv > 0): ?>
                    <div class="monthly">Aylık ~₺<?php echo number_format($monthlyEquiv, 0, ',', '.'); ?></div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($featuresArray)): ?>
                <ul class="pkg-features">
                    <?php foreach ($featuresArray as $feature): 
                        $featureText = is_array($feature) ? ($feature['name'] ?? $feature['title'] ?? '') : $feature;
                        if (empty($featureText)) continue;
                    ?>
                    <li>
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                        <?php echo htmlspecialchars($featureText); ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
                
                <a href="<?php echo BASE_URL; ?>/customer/packages/<?php echo $packageId; ?>/purchase?pricing_type=yearly" 
                   class="btn-pkg <?php echo $isFeatured ? 'btn-pkg-primary' : 'btn-pkg-secondary'; ?>">
                    Hemen Başla
                </a>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <div class="page-footer">
            <p>&copy; <?php echo date('Y'); ?> <?php echo $appName; ?>. Bir <a href="https://pofudukdijital.com" target="_blank">Pofuduk Dijital</a> ürünüdür.</p>
        </div>
    </div>

    <?php if ($highlightPackageId): ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const el = document.getElementById('package-<?php echo htmlspecialchars($highlightPackageId); ?>');
        if (el) {
            setTimeout(function() {
                el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                el.style.boxShadow = '0 0 0 3px rgba(99,102,241,0.3), 0 12px 40px rgba(99,102,241,0.15)';
                setTimeout(() => { el.style.boxShadow = ''; }, 2000);
            }, 300);
        }
    });
    </script>
    <?php endif; ?>
</body>
</html>
