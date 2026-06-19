<?php
/**
 * Table Selection - Q-System Edition
 * Mobile-first, large touch targets (44px+), accessible.
 */
?>
<!DOCTYPE html>
<html lang="<?php echo defined('CURRENT_LANGUAGE') ? CURRENT_LANGUAGE : 'tr'; ?>">
<head>
 <meta charset="UTF-8">
 <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
 <meta name="theme-color" content="#ffffff">
 <title><?php echo t('table.title'); ?> - <?php echo getAppConfig()->getAppName(); ?></title>

 <!-- Q-System assets -->
 <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/tokens.css">
 <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/admin-components.css">

 <!-- Icons Helper -->
 <?php require_once __DIR__ . '/../partials/icons.php'; ?>
</head>
<body class="q-page">
 <a href="#main-content" class="skip-link">Masalara git</a>

 <main id="main-content" class="q-container q-stack q-stack--lg">
 <header class="q-page-header q-text-center">
 <span class="q-page-header__eyebrow">QR MENÜ</span>
 <h1 class="q-page-header__title"><?php echo t('welcome'); ?></h1>
 <p class="q-page-header__subtitle"><?php echo t('scanQR'); ?></p>
 </header>

 <?php if (isset($error)): ?>
 <div class="q-card q-card--pad q-bg-danger-soft q-text-status-danger" role="alert">
 <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
 </div>
 <?php endif; ?>

 <section class="q-card q-card--pad" aria-label="Masa seçimi">
 <div class="q-card__header">
 <h2 class="q-card__title"><?php echo t('demoSelect'); ?></h2>
 </div>
 <?php if (!empty($tables)): ?>
 <div class="q-grid q-grid--4">
 <?php foreach ($tables as $table): ?>
 <a href="<?php echo BASE_URL; ?>/t/<?php echo htmlspecialchars($table['table_id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
 class="q-card q-card--pad q-card--hover q-text-center"
 aria-label="Masa <?php echo htmlspecialchars($table['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
 <div class="q-text-metric"><?php echo htmlspecialchars($table['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
 <p class="q-hint q-mt-2"><?php echo htmlspecialchars($table['zone'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
 </a>
 <?php endforeach; ?>
 </div>
 <?php else: ?>
 <div class="q-empty q-empty--inline">
 <div class="q-empty__icon-wrapper">
 <?php echo icon_qr_code(['class' => 'q-icon-lg']); ?>
 </div>
 <p class="q-empty__title">Aktif masa yok</p>
 </div>
 <?php endif; ?>
 </section>
 </main>
</body>
</html>
