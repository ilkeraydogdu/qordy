<?php
/**
 * Customer Cart - Q-System Edition
 * Mobile-first, large touch targets, sticky bottom checkout.
 */
$cart = $cart ?? [];
$settings = $settings ?? [];
$total = 0;
foreach ($cart as $item) {
 $total += ($item['total_price'] ?? 0);
}
?>
<!DOCTYPE html>
<html lang="<?php echo getTranslationService()->getCurrentLanguage(); ?>">
<head>
 <meta charset="UTF-8">
 <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
 <meta name="theme-color" content="#ffffff">
  <title><?php echo t('cart'); ?> - <?php echo getAppConfig()->getAppName(); ?></title>

 <!-- Q-System assets -->
 <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/tokens.css">
 <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/admin-components.css">

 <!-- Icons Helper -->
 <?php require_once __DIR__ . '/../partials/icons.php'; ?>
</head>
<body class="q-page">
 <a href="#main-content" class="skip-link">Sepete git</a>

 <header class="q-page-header">
 <div>
 <p class="q-page-header__eyebrow">MÜŞTERİ</p>
 <h1 class="q-page-header__title"><?php echo t('cart'); ?></h1>
 </div>
 <div class="q-page-header__actions">
 <a href="<?php echo BASE_URL; ?>/menu" class="q-btn q-btn--ghost" aria-label="Menüye dön">
 <?php echo icon_arrow_left(['class' => 'q-icon-sm']); ?>
 Menü
 </a>
 </div>
 </header>

 <main id="main-content" class="q-container q-stack q-stack--lg">
 <?php if (empty($cart)): ?>
 <section class="q-card q-card--pad q-empty" aria-live="polite">
 <div class="q-empty__icon-wrapper">
 <?php echo icon_utensils(['class' => 'q-icon-lg']); ?>
 </div>
 <p class="q-empty__title"><?php echo t('cart'); ?> <?php echo t('common.empty', 'boş'); ?></p>
 </section>
 <?php else: ?>
 <section class="q-stack q-stack--md" aria-label="Sepetteki ürünler">
 <?php foreach ($cart as $item): ?>
 <article class="q-card q-card--pad q-card--hover">
 <div class="q-card__body">
 <h2 class="q-card__title"><?php echo htmlspecialchars($item['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h2>
 <?php if (!empty($item['description'])): ?>
 <p class="q-hint"><?php echo htmlspecialchars($item['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
 <?php endif; ?>
 <div class="q-toolbar q-toolbar--between q-mt-3">
 <span class="q-text-metric q-text-brand"><?php echo formatCurrency($item['total_price'] ?? 0); ?></span>
 <span class="q-badge q-badge--neutral">x <?php echo (int)($item['quantity'] ?? 1); ?></span>
 </div>
 </div>
 </article>
 <?php endforeach; ?>
 </section>

 <section class="q-card q-card--pad q-cart-sticky" aria-label="Sipariş özeti">
 <div class="q-toolbar q-toolbar--between q-mb-4">
 <span class="q-text-label"><?php echo t('total'); ?></span>
 <span class="q-text-metric"><?php echo formatCurrency($total); ?></span>
 </div>
 <button type="button" onclick="placeOrder()" class="q-btn q-btn--primary q-btn--block q-btn--lg" aria-label="Siparişi onayla">
 <?php echo t('placeOrder'); ?>
 </button>
 </section>
 <?php endif; ?>
 </main>

 <script>
 function placeOrder() {
 if (window.NotificationManager) {
 window.NotificationManager.success('<?php echo t('orderSuccess'); ?>');
 }
 }
 </script>
</body>
</html>
