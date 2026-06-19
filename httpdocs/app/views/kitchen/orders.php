<?php
$orders = $orders ?? [];
$status = $status ?? 'all';
$baseUrl = BASE_URL;
$title = 'Mutfak - Siparişler';
ob_start();
?>

<div class="q-page q-biz-theme animate-slide-up">
 <div class="q-container q-stack q-stack--lg">
 <a href="<?php echo $baseUrl; ?>/kitchen" class="q-btn q-btn--ghost q-btn--sm" aria-label="Mutfak Ekranına Dön">
 <?php echo icon_arrow_left(['class' => 'w-4 h-4']); ?>
 Mutfak Ekranına Dön
 </a>

 <header class="q-page-header">
 <p class="q-page-header__eyebrow">PERSONEL</p>
 <h1 class="q-page-header__title q-text-h1">Siparişler</h1>
 <p class="q-page-header__subtitle">Tüm aktif siparişleri görüntüleyin</p>
 </header>

 <?php if (empty($orders)): ?>
 <div class="q-card q-card--pad q-text-center">
 <svg class="q-icon q-icon--xl" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
 <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
 </svg>
 <h3 class="q-card__title">Sipariş bulunamadı</h3>
 <p class="q-text-secondary">Yeni siparişler burada görünecek</p>
 </div>
 <?php else: ?>
 <div class="q-grid q-grid--2 q-grid--md-3 q-grid--lg-4">
 <?php foreach ($orders as $order):
 $orderStatus = strtolower($order['status'] ?? 'pending');
 $statusConfig = [
 'pending'  => ['label' => 'Bekliyor', 'class' => 'q-badge q-badge--warning'],
 'preparing' => ['label' => 'Hazırlanıyor', 'class' => 'q-badge q-badge--info'],
 'ready' => ['label' => 'Hazır', 'class' => 'q-badge q-badge--success'],
 'served' => ['label' => 'Tamamlandı', 'class' => 'q-badge q-badge--neutral'],
 'cancelled' => ['label' => 'İptal', 'class' => 'q-badge q-badge--danger'],
 'refunded' => ['label' => 'İade', 'class' => 'q-badge q-badge--neutral']
 ];
 $statusInfo = $statusConfig[$orderStatus] ?? $statusConfig['pending'];
 ?>
 <article class="q-card q-card--pad q-card--hover">
 <div class="q-card__header">
 <h3 class="q-card__title">
 <?php echo htmlspecialchars($order['table_name'] ?? 'Bilinmiyor'); ?>
 </h3>
 <div class="q-flex q-gap-1 q-text-meta">
 <span class="q-font-mono">#<?php echo htmlspecialchars(substr((string)($order['order_id'] ?? ''), -8)); ?></span>
 <span class="q-text-secondary">·</span>
 <span><?php echo htmlspecialchars($order['created_at'] ?? ''); ?></span>
 </div>
 <span class="<?php echo $statusInfo['class']; ?>" aria-label="Durum: <?php echo $statusInfo['label']; ?>">
 <span class="q-badge__dot" aria-hidden="true"></span>
 <?php echo $statusInfo['label']; ?>
 </span>
 </div>

 <?php if (!empty($order['customer_note'])): ?>
 <div class="q-card__note">
 <span class="q-icon" aria-hidden="true">!</span>
 <p><?php echo htmlspecialchars($order['customer_note']); ?></p>
 </div>
 <?php endif; ?>

 <div class="q-card__body">
 <?php
 require_once __DIR__ . '/../../core/DependencyFactory.php';
 $orderItemService = \App\Core\DependencyFactory::getOrderItemService();
 $customizationService = \App\Core\DependencyFactory::getIngredientCustomizationService();
 $orderItems = $orderItemService->getOrderItemsByOrder($order['order_id'] ?? '');
 if (!empty($orderItems)):
 foreach ($orderItems as $orderItem):
 $customizations = $customizationService->getByOrderItem($orderItem['order_item_id'] ?? '');
 ?>
 <div class="q-card q-card--sm">
 <span class="q-badge q-badge--warning q-badge__qty" aria-label="Adet: <?php echo htmlspecialchars($orderItem['quantity'] ?? 1); ?>">
 <?php echo htmlspecialchars($orderItem['quantity'] ?? 1); ?>
 </span>
 <div class="q-flex-1">
 <h4 class="q-text-base q-font-semibold"><?php echo htmlspecialchars($orderItem['menu_item_name'] ?? ''); ?></h4>
 <?php if (!empty($orderItem['note'])): ?>
 <p class="q-text-meta q-text-warning">Not: <?php echo htmlspecialchars($orderItem['note']); ?></p>
 <?php endif; ?>
 <?php if (!empty($customizations)): ?>
 <p class="q-text-meta q-text-secondary"><?php echo htmlspecialchars($customizationService->formatForDisplay($customizations)); ?></p>
 <?php endif; ?>
 </div>
 </div>
 <?php
 endforeach;
 endif;
 ?>
 </div>

 <footer class="q-card__footer">
  <div class="q-flex q-between q-items-center">
 <span class="q-text-secondary q-text-sm">Toplam</span>
 <span class="q-text-h3"><?php echo number_format($order['total_amount'] ?? 0, 2); ?> ₺</span>
 </div>

 <?php if (($order['status'] ?? '') === 'PENDING'): ?>
 <form method="POST" action="<?php echo $baseUrl; ?>/kitchen/update-status">
 <?php echo csrf_field(); ?>
 <input type="hidden" name="order_id" value="<?php echo htmlspecialchars($order['order_id'] ?? ''); ?>">
 <input type="hidden" name="status" value="PREPARING">
 <button type="submit" class="q-btn q-btn--primary q-btn--block q-btn--lg" aria-label="Hazırlamaya Başla">
 <span class="q-icon" aria-hidden="true">▶</span>
 Hazırlamaya Başla
 </button>
 </form>
 <?php elseif (($order['status'] ?? '') === 'PREPARING'): ?>
 <form method="POST" action="<?php echo $baseUrl; ?>/kitchen/update-status">
 <?php echo csrf_field(); ?>
 <input type="hidden" name="order_id" value="<?php echo htmlspecialchars($order['order_id'] ?? ''); ?>">
 <input type="hidden" name="status" value="READY">
 <button type="submit" class="q-btn q-btn--primary q-btn--block q-btn--lg" aria-label="Hazır olarak işaretle">
 <span class="q-icon" aria-hidden="true">✓</span>
 Hazır
 </button>
 </form>
 <?php else: ?>
 <div class="q-badge q-badge--success q-btn--block" role="status" aria-label="Sipariş hazır">
 <span class="q-icon" aria-hidden="true">✓</span>
 Hazır
 </div>
 <?php endif; ?>
 </footer>
 </article>
 <?php endforeach; ?>
 </div>
 <?php endif; ?>
 </div>
</div>

<?php
$content = ob_get_clean();
include_once __DIR__ . '/../layouts/admin_layout.php';
?>
