<?php
$order = $order ?? null;
$order_items = $order_items ?? [];
$baseUrl = BASE_URL;

if (!$order) {
    header('Location: ' . BASE_URL . '/qodmin/orders');
    exit;
}

$title = 'Sipariş Detayı - ' . getAppConfig()->getAppName();
// Note: Layout is automatically included by Controller::view() method
// No need for ob_start() or manual layout include
$status = strtolower($order['status'] ?? 'pending');
$statusBadgeMap = [
    'pending' => 'q-badge--warning',
    'preparing' => 'q-badge--info',
    'ready' => 'q-badge--success',
    'served' => 'q-badge--success',
    'delivered' => 'q-badge--success',
    'cancelled' => 'q-badge--danger',
];
$statusBadgeClass = $statusBadgeMap[$status] ?? 'q-badge--neutral';
?>

<div class="q-page q-biz-theme animate-slide-up">
  <div class="q-container q-stack q-stack--lg">
    <a href="<?php echo $baseUrl; ?>/qodmin/orders" class="q-btn q-btn--ghost q-btn--sm" style="width:fit-content;">
            <?php echo icon_arrow_left(['class' => 'w-5 h-5']); ?>
            Geri Dön
    </a>
    
    <div class="q-stack q-stack--lg" style="max-width:56rem;margin:0 auto;width:100%;">
        <div class="q-card q-card--pad">
            <div class="q-toolbar" style="flex-wrap:wrap;align-items:flex-start;">
                <div>
                    <p class="q-page-header__eyebrow">Sipariş</p>
                    <h1 class="q-page-header__title" style="margin:0;">#<?php echo htmlspecialchars($order['order_id'] ?? ''); ?></h1>
                    <p class="q-page-header__subtitle">
                        Masa: <?php echo htmlspecialchars($order['table_name'] ?? 'Bilinmiyor'); ?> • 
                        Tarih: <?php echo htmlspecialchars($order['created_at'] ?? date('Y-m-d H:i:s')); ?>
                    </p>
                </div>
                <div class="q-toolbar">
                    <span class="q-badge <?php echo $statusBadgeClass; ?>">
                        <?php echo htmlspecialchars(function_exists('getOrderStatusLabel') ? getOrderStatusLabel($order['status'] ?? '') : ($order['status'] ?? 'PENDING')); ?>
                    </span>
                    <div class="font-black text-xl" style="color:var(--color-text-primary);">
                            <?php echo number_format($order['total_amount'] ?? 0, 2); ?> ₺
                    </div>
                </div>
            </div>
        </div>
        
        <div class="q-card q-card--pad q-stack q-stack--md">
            <h2 class="q-card__title">Sipariş Öğeleri</h2>
            <div class="q-stack q-stack--sm">
                <?php if (empty($order_items)): ?>
                    <p class="q-hint text-center py-8">Sipariş öğesi bulunamadı.</p>
                <?php else: ?>
                    <?php foreach ($order_items as $item): ?>
                        <div class="q-toolbar q-card q-card--pad" style="justify-content:space-between;background:var(--color-surface-muted);">
                            <div class="flex-1 min-w-0">
                                <h3 class="font-black"><?php echo htmlspecialchars($item['menu_item_name'] ?? 'Ürün'); ?></h3>
                                <p class="q-hint">
                                    Miktar: <?php echo htmlspecialchars($item['quantity'] ?? 1); ?>
                                    <?php if (!empty($item['note'])): ?>
                                        • Not: <?php echo htmlspecialchars($item['note']); ?>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div class="text-right">
                                <div class="font-black text-xl">
                                    <?php echo number_format(($item['price'] ?? 0) * ($item['quantity'] ?? 1), 2); ?> ₺
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="q-card q-card--pad q-stack q-stack--md">
            <h2 class="q-card__title">İşlemler</h2>
            <div class="q-toolbar" style="flex-wrap:wrap;">
                <?php if (($order['status'] ?? '') === 'PENDING'): ?>
                    <form method="POST" action="<?php echo $baseUrl; ?>/qodmin/orders/update-status" class="inline">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="order_id" value="<?php echo htmlspecialchars($order['order_id'] ?? ''); ?>">
                        <input type="hidden" name="status" value="PREPARING">
                        <button type="submit" class="q-btn q-btn--primary">
                            Hazırlanmaya Başla
                        </button>
                    </form>
                <?php endif; ?>
                
                <?php if (($order['status'] ?? '') === 'PREPARING'): ?>
                    <form method="POST" action="<?php echo $baseUrl; ?>/qodmin/orders/update-status" class="inline">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="order_id" value="<?php echo htmlspecialchars($order['order_id'] ?? ''); ?>">
                        <input type="hidden" name="status" value="READY">
                        <button type="submit" class="q-btn q-btn--primary">
                            Hazır Olarak İşaretle
                        </button>
                    </form>
                <?php endif; ?>
                
                <?php if (($order['status'] ?? '') === 'READY'): ?>
                    <form method="POST" action="<?php echo $baseUrl; ?>/qodmin/orders/update-status" class="inline">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="order_id" value="<?php echo htmlspecialchars($order['order_id'] ?? ''); ?>">
                        <input type="hidden" name="status" value="DELIVERED">
                        <button type="submit" class="q-btn q-btn--primary">
                            Teslim Edildi
                        </button>
                    </form>
                <?php endif; ?>
                
                <?php if (in_array($order['status'] ?? '', ['PENDING', 'PREPARING'])): ?>
                    <form method="POST" action="<?php echo $baseUrl; ?>/qodmin/orders/update-status" class="inline">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="order_id" value="<?php echo htmlspecialchars($order['order_id'] ?? ''); ?>">
                        <input type="hidden" name="status" value="CANCELLED">
                        <button type="submit" class="q-btn q-btn--danger" 
                                onclick="return handleCancelOrder(event);">
                            İptal Et
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
  </div>
</div>

<script>
async function handleCancelOrder(event) {
    event.preventDefault();
    
    if (!window.NotificationManager) {
        console.error('NotificationManager is not available');
        return false;
    }
    
    const confirmed = await window.NotificationManager.confirm('Bu siparişi iptal etmek istediğinizden emin misiniz?', 'Sipariş İptal');
    if (confirmed) {
        event.target.closest('form').submit();
    }
    return false;
}
</script>

<?php
// Content is automatically captured by Controller::view() method
// Layout is automatically included by Controller::view() method
?>

