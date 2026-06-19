<?php
$orders = $orders ?? [];
$table_id = $table_id ?? '';
$baseUrl = BASE_URL;
$title = 'POS - Siparişler';
ob_start();
?>

<div class="q-page q-biz-theme q-biz-ops animate-slide-up">
 <div class="q-container q-stack q-stack--lg">
 <a href="<?php echo $baseUrl; ?>/pos" class="q-btn q-btn--ghost q-btn--sm" aria-label="Masa Planına Dön">
 <?php echo icon_arrow_left(['class' => 'w-4 h-4']); ?>
 Masa Planına Dön
 </a>

 <header class="q-page-header">
 <p class="q-page-header__eyebrow">POS</p>
 <h1 class="q-page-header__title q-text-h1">Masa Siparişleri</h1>
 <?php if (!empty($table_id)): ?>
 <p class="q-text-secondary q-text-sm">Masa: <?php echo htmlspecialchars($table_id); ?></p>
 <?php endif; ?>
 </header>

 <?php if (empty($orders)): ?>
 <div class="q-card q-card--pad q-card--dashed q-text-center">
 <h3 class="q-card__title q-text-secondary">Bu masa için sipariş bulunamadı.</h3>
 </div>
 <?php else: ?>
 <div class="q-stack q-stack--md">
 <?php foreach ($orders as $order):
 $orderStatus = strtolower($order['status'] ?? 'pending');
 $statusConfig = [
 'pending' => 'q-badge--warning',
 'preparing' => 'q-badge--info',
 'ready' => 'q-badge--success',
 'served' => 'q-badge--success',
 'cancelled' => 'q-badge--danger',
 'refunded' => 'q-badge--neutral',
 ];
 $statusClass = $statusConfig[$orderStatus] ?? 'q-badge--neutral';
 ?>
 <article class="q-card q-card--pad">
 <div class="q-flex q-between q-items-start">
 <div>
 <h3 class="q-card__title">Sipariş #<?php echo htmlspecialchars($order['order_id'] ?? ''); ?></h3>
 <p class="q-text-secondary q-text-sm">
 <?php echo htmlspecialchars($order['created_at'] ?? ''); ?>
 </p>
 </div>
 <span class="q-badge <?php echo $statusClass; ?>" aria-label="Durum: <?php echo htmlspecialchars($order['status'] ?? ''); ?>">
 <span class="q-badge__dot" aria-hidden="true"></span>
 <?php echo htmlspecialchars(function_exists('getOrderStatusLabel') ? getOrderStatusLabel($order['status'] ?? '') : ($order['status'] ?? 'PENDING')); ?>
 </span>
 </div>
 <div class="q-card__footer q-text-right">
 <div class="q-text-h3 pos-text-money">
 <?php echo number_format($order['total_amount'] ?? 0, 2); ?> ₺
 </div>
 </div>
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
