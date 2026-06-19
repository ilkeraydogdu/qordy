<?php
/**
 * Kitchen Dashboard View - React KitchenDisplay component'inin PHP versiyonu
 * Birebir aynı tasarım
 */

require_once __DIR__ . '/../../helpers/translations.php';
require_once __DIR__ . '/../partials/icons.php';
require_once __DIR__ . '/../../helpers/toast.php';
require_once __DIR__ . '/../../core/DependencyFactory.php';

// Get FormattingService for duration formatting
$formattingService = \App\Core\DependencyFactory::getFormattingService();

// Helper function for duration if not available
if (!function_exists('getDuration')) {
    function getDuration($startTime = null) {
        if (!$startTime) return '';
        $timestamp = is_numeric($startTime) ? intval($startTime) : strtotime($startTime);
        if ($timestamp === false || $timestamp <= 0) return '';
        $diff = floor((time() - $timestamp) / 60);
        if ($diff < 0) return '0 dk';
        if ($diff < 60) return $diff . ' dk';
        $h = floor($diff / 60);
        $m = $diff % 60;
        return $h . 's ' . $m . 'dk';
    }
}

// Get orders data - now using active_orders from controller (already filtered and sorted)
$activeOrders = $active_orders ?? [];

// Get status constants from controller (dynamic, from ConstantsService)
$inactiveStatuses = $inactive_statuses ?? [
    defined('ORDER_STATUS_SERVED') ? ORDER_STATUS_SERVED : 'SERVED',
    defined('ORDER_STATUS_CANCELLED') ? ORDER_STATUS_CANCELLED : 'CANCELLED'
];
$activeStatuses = $active_statuses ?? [
    defined('ORDER_STATUS_PENDING') ? ORDER_STATUS_PENDING : 'PENDING',
    defined('ORDER_STATUS_PREPARING') ? ORDER_STATUS_PREPARING : 'PREPARING',
    defined('ORDER_STATUS_READY') ? ORDER_STATUS_READY : 'READY'
];

// Get polling interval from config (default 2 seconds for faster updates)
$pollingInterval = defined('KITCHEN_POLLING_INTERVAL') ? KITCHEN_POLLING_INTERVAL : 2000;

// Define status constants early (before they're used in HTML)
$statusPending = defined('ORDER_STATUS_PENDING') ? ORDER_STATUS_PENDING : 'PENDING';
$statusPreparing = defined('ORDER_STATUS_PREPARING') ? ORDER_STATUS_PREPARING : 'PREPARING';
$statusReady = defined('ORDER_STATUS_READY') ? ORDER_STATUS_READY : 'READY';

$baseUrl = BASE_URL;
$isSuperAdmin = $is_super_admin ?? false;
?>

<?php if ($isSuperAdmin): ?>
<!-- SUPER ADMIN VIEW: Business Selection First -->
<div class="q-page q-biz-theme animate-slide-up">
  <div class="q-container q-stack q-stack--lg">
    <div id="business-selection-view">
        <header class="q-page-header">
            <div>
                <p class="q-page-header__eyebrow">Personel</p>
                <h1 class="q-page-header__title">Mutfak Ekranı — İşletme Seçin</h1>
                <p class="q-page-header__subtitle">Mutfak ekranına erişmek istediğiniz işletmeyi seçin</p>
            </div>
            <div class="q-page-header__actions">
                <div class="q-field" style="margin:0;min-width:14rem;">
                <input type="text" id="business-search" placeholder="İşletme ara…" onkeyup="BusinessSelector.searchBusinesses(this.value)" class="q-input">
                </div>
            </div>
        </header>
        <div id="business-grid" class="q-grid q-grid--4">
            <div class="q-empty" style="grid-column:1/-1;padding:var(--space-10);">
                <span class="q-spinner" aria-hidden="true"></span>
                <p>İşletmeler yükleniyor…</p>
            </div>
        </div>
    </div>
    
    <!-- Kitchen Management View -->
    <div id="kitchen-management-view" class="hidden">
        <header class="flex items-center gap-3 mb-4">
            <button onclick="backToBusinessSelection()" class="p-2 hover:bg-slate-200 rounded-lg transition-all">
                <svg class="w-6 h-6 text-slate-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
            </button>
            <h1 class="text-xl sm:text-2xl lg:text-3xl font-black text-slate-900 tracking-tighter">
                <span id="selected-business-name"></span> - Mutfak Ekranı
            </h1>
        </header>
    </div>
  </div>
</div>
<?php endif; ?>

<div id="kitchen-dashboard" class="p-4 sm:p-5 md:p-6 lg:p-7 xl:p-8 h-full overflow-y-auto animate-slide-up q-biz-theme q-biz-ops q-biz-ops-scroll" style="<?php echo $isSuperAdmin ? 'display: none;' : ''; ?>">
    <header class="kds-page-header flex flex-col gap-4 sm:gap-5">
 <div class="flex flex-col lg:flex-row justify-between lg:items-start gap-4 lg:gap-6">
            <div class="flex flex-col min-w-0">
                <p class="kds-eyebrow"><?php echo t('kitchen.title'); ?></p>
                <div class="flex items-center gap-2 sm:gap-3 flex-wrap">
                    <h1 class="kds-title shrink-0"><?php echo t('kitchen.activeOrders', 'Aktif Siparişler'); ?></h1>
                    <?php
                    $bizNumber = '';
                    if (class_exists('\App\Core\TenantContext') && \App\Core\TenantContext::isSet()) {
                        $tenant = \App\Core\TenantContext::get();
                        if (is_array($tenant) && !empty($tenant['business_number'])) {
                            $bizNumber = trim((string) $tenant['business_number']);
                        } elseif (is_object($tenant) && method_exists($tenant, 'getBusinessNumber')) {
                            $bizNumber = trim((string) $tenant->getBusinessNumber());
                        } elseif (is_object($tenant) && isset($tenant->business_number)) {
                            $bizNumber = trim((string) $tenant->business_number);
                        }
                    }
                    if (empty($bizNumber) && !empty($_SESSION['customer_id'])) {
                        try {
                            $customerRepo = \App\Core\DependencyFactory::getCustomerRepository();
                            $bizCustomerRow = $customerRepo->findById($_SESSION['customer_id']);
                            if ($bizCustomerRow && !empty($bizCustomerRow['business_number'])) {
                                $bizNumber = trim((string) $bizCustomerRow['business_number']);
                            }
                        } catch (\Exception $e) {}
                    }
                    if (!empty($bizNumber)):
                    ?>
                    <div class="px-2 py-1 inline-flex items-center gap-1.5 rounded-lg bg-indigo-50 border border-indigo-100 text-indigo-700 text-[11px] font-semibold shrink-0" role="status" aria-label="İşletme kodu <?php echo htmlspecialchars($bizNumber, ENT_QUOTES, 'UTF-8'); ?>">
                        <span class="text-indigo-400 font-sans font-bold text-[9px] uppercase tracking-wider">#</span>
                        <span class="font-mono font-black text-indigo-900 text-[12px]"><?php echo htmlspecialchars($bizNumber, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
                <p class="kds-subtitle mt-1"><?php echo t('kitchen.subtitle'); ?></p>
            </div>
            <div class="kds-actions flex flex-wrap items-center gap-2 sm:gap-3">
                <div class="kds-stat-pill">
                    <span class="active-orders-count kds-stat-pill__count"><?php echo count($activeOrders); ?></span>
                    <span><?php echo t('kitchen.activeOrders'); ?></span>
                </div>
                <button type="button" onclick="toggleFullscreen()" class="kds-icon-btn btn-touch" title="<?php echo t('titles.fullscreen'); ?>" aria-label="<?php echo t('titles.fullscreen'); ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"></path>
                    </svg>
                </button>
                <a href="<?php echo BASE_URL; ?>/logout" class="kds-icon-btn kds-icon-btn--danger btn-touch" title="<?php echo t('common.logout', 'Çıkış Yap'); ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                    </svg>
                    <span class="text-xs font-bold hidden sm:inline"><?php echo t('common.logout', 'Çıkış'); ?></span>
                </a>
            </div>
        </div>
        
        <div class="kds-toolbar gap-3 w-full">
            <div class="kds-search-wrap">
                <input type="text" id="kitchen-search" placeholder="<?php echo t('kitchen.searchPlaceholder', 'Masa veya sipariş ara...'); ?>"
                       class="kds-input form-input-responsive"
                       onkeyup="filterOrders()">
                <span class="kds-search-icon" aria-hidden="true">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                </span>
            </div>
            <select id="kitchen-status-filter" onchange="filterOrders()"
                    class="kds-select w-full sm:w-auto sm:min-w-[12rem] shrink-0">
                <option value="all"><?php echo t('kitchen.allStatuses', 'Tüm Durumlar'); ?></option>
                <option value="<?php echo htmlspecialchars($statusPending ?? 'PENDING'); ?>"><?php echo t('kitchen.pending', 'Beklemede'); ?></option>
                <option value="<?php echo htmlspecialchars($statusPreparing ?? 'PREPARING'); ?>"><?php echo t('kitchen.preparing', 'Hazırlanıyor'); ?></option>
                <option value="<?php echo htmlspecialchars($statusReady ?? 'READY'); ?>"><?php echo t('kitchen.ready', 'Hazır'); ?></option>
            </select>
        </div>
    </header>
    
    <?php if (empty($activeOrders)): ?>
        <div class="kds-empty">
            <div class="kds-empty__card">
                <?php echo icon_utensils(['class' => 'kds-empty__icon']); ?>
                <h2 class="kds-empty__title"><?php echo t('kitchen.kitchenQuiet'); ?></h2>
                <p class="kds-empty__hint">Yeni siparişler burada görünecek</p>
            </div>
        </div>
    <?php else: ?>
        <div class="kds-orders-grid" id="orders-grid">
            <?php foreach ($activeOrders as $order): 
                $orderId = $order['order_id'] ?? '';
                $tableName = $order['table_name'] ?? '';
                if (empty($tableName)) {
                    $tableName = t('table.default', t('table', 'Masa'));
                }
                $status = $order['status'] ?? (defined('ORDER_STATUS_PENDING') ? ORDER_STATUS_PENDING : 'PENDING');
                $createdAtStr = $order['created_at'] ?? null;
                $createdAt = is_numeric($createdAtStr) ? intval($createdAtStr) : strtotime($createdAtStr ?: 'now');
                
                $orderItems = [];
                if (isset($order['items']) && is_array($order['items'])) {
                    $orderItems = $order['items'];
                }
                
                $isPreparing = $status === $statusPreparing;
                $isReady = $status === $statusReady;
                $isPending = $status === $statusPending;
                
                // Calculate wait time for urgency indicator
                $waitMinutes = floor((time() - $createdAt) / 60);
                $urgencyClass = $waitMinutes > 15 ? 'kds-urgency--critical' : ($waitMinutes > 8 ? 'kds-urgency--warning' : 'kds-urgency--ok');
                $cardStatusClass = $isPreparing ? 'kds-order-card--preparing' : ($isReady ? 'kds-order-card--ready' : 'kds-order-card--pending');
            ?>
                <div class="kitchen-order-card kds-order-card <?php echo $cardStatusClass; ?>"
                     data-order-id="<?php echo htmlspecialchars($orderId); ?>" data-status="<?php echo htmlspecialchars($status); ?>"
                     data-table-name="<?php echo htmlspecialchars(strtolower($tableName)); ?>"
                     data-order-number="<?php echo htmlspecialchars($orderId); ?>" style="max-height: 85vh;">
                    
                    <div class="kds-order-card__head flex items-start justify-between gap-3">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 mb-1 flex-wrap">
                                <h3 class="kds-order-card__table"><?php echo htmlspecialchars($tableName); ?></h3>
                                <?php if (!empty($order['order_source'])):
                                    $sourceClassMap = [
                                        'POS' => 'kds-source-badge--pos',
                                        'QR' => 'kds-source-badge--qr',
                                        'PHONE' => 'kds-source-badge--phone',
                                    ];
                                    $srcClass = $sourceClassMap[$order['order_source']] ?? 'kds-source-badge--default';
                                ?>
                                    <span class="kds-source-badge <?php echo $srcClass; ?>">
                                        <?php echo htmlspecialchars($order['order_source']); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="kds-order-card__meta">
                                <span class="font-mono">#<?php echo htmlspecialchars(substr($orderId, -8)); ?></span>
                                <span class="kds-order-card__meta-dot"></span>
                                <span><?php echo date('H:i', $createdAt); ?></span>
                            </div>
                        </div>
                        
                        <div class="flex flex-col items-end gap-1 shrink-0">
                            <div class="kds-time-badge <?php echo $urgencyClass; ?>">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <span><?php echo getDuration($createdAt); ?></span>
                            </div>
                            <?php
                            $statusBadgeClass = $isPreparing ? 'kds-status-badge--preparing' : ($isReady ? 'kds-status-badge--ready' : 'kds-status-badge--pending');
                            $statusLabels = [
                                $statusPending => 'Bekliyor',
                                $statusPreparing => 'Hazırlanıyor',
                                $statusReady => 'Hazır',
                            ];
                            $statusLabel = $statusLabels[$status] ?? $statusLabels[$statusPending];
                            ?>
                            <span class="kds-status-badge <?php echo $statusBadgeClass; ?>"><?php echo $statusLabel; ?></span>
                        </div>
                    </div>
                    
                    <?php if (!empty($order['customer_note'])): ?>
                    <div class="kds-order-note">
                        <div class="flex items-start gap-2">
                            <svg class="w-4 h-4 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/>
                            </svg>
                            <p><?php echo htmlspecialchars($order['customer_note']); ?></p>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="kds-order-items">
                        <?php if (empty($orderItems)): ?>
                            <div class="kds-order-item text-center text-slate-400 text-sm"><?php echo t('kitchen.loadingDetails', 'Yükleniyor...'); ?></div>
                        <?php else: ?>
                            <?php foreach ($orderItems as $item):
                                $quantity = $item['quantity'] ?? 1;
                                $itemName = $item['name'] ?? $item['item_name'] ?? $item['menu_item_name'] ?? t('kitchen.product', 'Ürün');
                                $variantName = $item['variant_name'] ?? null;
                                
                                $excludedIngredientsRaw = $item['excluded_ingredients'] ?? '[]';
                                $excludedIngredients = is_string($excludedIngredientsRaw) ? json_decode($excludedIngredientsRaw, true) : $excludedIngredientsRaw;
                                $excludedIngredients = is_array($excludedIngredients) ? $excludedIngredients : [];
                                
                                $selectedExtrasRaw = $item['selected_extras'] ?? '[]';
                                $selectedExtras = is_string($selectedExtrasRaw) ? json_decode($selectedExtrasRaw, true) : $selectedExtrasRaw;
                                $selectedExtras = is_array($selectedExtras) ? $selectedExtras : [];
                                
                                $itemNote = $item['note'] ?? '';
                                $prepTime = $item['preparation_time'] ?? $item['prep_time'] ?? null;
                                $cookingTime = $item['cooking_time'] ?? null;
                                $serveTime = $item['serve_time'] ?? null;
                                $totalTime = ($prepTime || $cookingTime || $serveTime) ? (($prepTime ?? 0) + ($cookingTime ?? 0) + ($serveTime ?? 0)) : null;
                            ?>
                                <div class="kds-order-item">
                                    <div class="flex gap-3">
                                        <div class="shrink-0">
                                            <span class="kds-qty-badge"><?php echo $quantity; ?></span>
                                        </div>
                                        
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-start justify-between gap-2 mb-1">
                                                <div class="flex-1 min-w-0">
                                                    <h4 class="kds-item-name"><?php echo htmlspecialchars($itemName); ?></h4>
                                                    <?php if (!empty($variantName)): ?>
                                                        <span class="kds-item-variant"><?php echo htmlspecialchars($variantName); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if ($totalTime): ?>
                                                    <span class="kds-item-time"><?php echo $totalTime; ?> dk</span>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <?php if (!empty($excludedIngredients) || !empty($selectedExtras)): ?>
                                            <div class="flex flex-wrap gap-1.5 mt-2">
                                                <?php foreach ($excludedIngredients as $ex):
                                                    $ingName = is_array($ex) ? ($ex['name'] ?? $ex['ingredient_name'] ?? '') : $ex;
                                                    if (!empty($ingName)):
                                                ?>
                                                    <span class="kds-tag--exclude">
                                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                        </svg>
                                                        <?php echo htmlspecialchars($ingName); ?>
                                                    </span>
                                                <?php endif; endforeach; ?>
                                                
                                                <?php foreach ($selectedExtras as $ext):
                                                    $extName = is_array($ext) ? ($ext['name'] ?? '') : $ext;
                                                    if (!empty($extName)):
                                                ?>
                                                    <span class="kds-tag--extra">
                                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                                        </svg>
                                                        <?php echo htmlspecialchars($extName); ?>
                                                    </span>
                                                <?php endif; endforeach; ?>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($itemNote)): ?>
                                            <div class="kds-item-note">
                                                <span class="font-semibold">Not:</span> <?php echo htmlspecialchars($itemNote); ?>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <div class="kds-order-card__footer">
                        <?php if ($status === $statusPending): ?>
                            <button type="button" onclick="updateOrderStatus(<?php echo json_encode($orderId); ?>, <?php echo json_encode($statusPreparing); ?>, this)"
                                    class="kds-btn-prepare disabled:opacity-50 disabled:cursor-not-allowed">
                                <?php echo icon_clock(['class' => 'w-5 h-5']); ?>
                                <span><?php echo t('kitchen.prepare'); ?></span>
                            </button>
                        <?php elseif ($status === $statusPreparing): ?>
                            <button type="button" onclick="markOrderAsServed(<?php echo json_encode($orderId); ?>, this)"
                                    class="kds-btn-serve disabled:opacity-50 disabled:cursor-not-allowed">
                                <?php echo icon_check(['class' => 'w-5 h-5']); ?>
                                <span><?php echo t('kitchen.serve'); ?></span>
                            </button>
                        <?php else: ?>
                            <div class="kds-btn-ready">
                                <?php echo icon_check(['class' => 'w-5 h-5']); ?>
                                <span><?php echo t('kitchen.ready'); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php echo getToastScript(); ?>

<?php
// Get kitchen translations for JavaScript
$kitchenTranslations = [
    'ready' => t('kitchen.ready'),
    'serve' => t('kitchen.serve'),
    'prepare' => t('kitchen.prepare'),
    'orderPreparing' => t('kitchen.orderPreparing'),
    'orderReady' => t('kitchen.orderReady')
];
?>
<script>
<?php
// Inline safe JSON helper
if (!function_exists('_safeJson')) {
    function _safeJson($data, $default = '[]') {
        if ($data === null) return $default;
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        if ($json === false || json_last_error() !== JSON_ERROR_NONE) {
            return $default;
        }
        return $json;
    }
}
?>
const baseUrl = <?php echo _safeJson($baseUrl ?? '', '""'); ?>;

if (typeof window.BASE_URL === 'undefined') {
    window.BASE_URL = baseUrl;
}
<?php
$bid = \App\Core\TenantResolver::resolve();
if (empty($bid) && class_exists('\\App\\Core\\TenantContext')) {
    $bid = \App\Core\TenantContext::getId();
}
?>
if (!window.BUSINESS_ID) {
    window.BUSINESS_ID = <?php echo json_encode((string)($bid ?? '')); ?>;
}
if (!window.WEBSOCKET_URL) {
    window.WEBSOCKET_URL = (location.protocol === 'https:' ? 'wss://' : 'ws://') + location.host + '/ws';
}

if (!window.realtimeService) {
    const script = document.createElement('script');
    script.src = baseUrl + '/assets/js/realtime.js';
    document.head.appendChild(script);
}

const pollingInterval = <?php echo _safeJson($pollingInterval ?? 5000, '5000'); ?>;
const orderStatuses = <?php echo _safeJson([
    'PENDING' => defined('ORDER_STATUS_PENDING') ? ORDER_STATUS_PENDING : 'PENDING',
    'PREPARING' => defined('ORDER_STATUS_PREPARING') ? ORDER_STATUS_PREPARING : 'PREPARING',
    'READY' => defined('ORDER_STATUS_READY') ? ORDER_STATUS_READY : 'READY',
    'SERVED' => defined('ORDER_STATUS_SERVED') ? ORDER_STATUS_SERVED : 'SERVED',
    'CANCELLED' => defined('ORDER_STATUS_CANCELLED') ? ORDER_STATUS_CANCELLED : 'CANCELLED'
], '{}'); ?>;
const inactiveStatuses = <?php echo _safeJson($inactiveStatuses ?? [], '[]'); ?>;

// Kitchen translations from PHP
window.kitchenTranslations = <?php echo _safeJson($kitchenTranslations ?? [], '{}'); ?>;

// Notification translations for NotificationManager
window.notificationTranslations = {
    success: <?php echo _safeJson(t('notifications.success', 'Başarılı'), '""'); ?>,
    error: <?php echo _safeJson(t('notifications.error', 'Hata'), '""'); ?>,
    warning: <?php echo _safeJson(t('notifications.warning', 'Uyarı'), '""'); ?>,
    info: <?php echo _safeJson(t('notifications.info', 'Bilgi'), '""'); ?>,
    confirm: <?php echo _safeJson(t('notifications.confirm', 'Onay'), '""'); ?>,
    yes: <?php echo _safeJson(t('notifications.yes', 'Evet'), '""'); ?>,
    no: <?php echo _safeJson(t('notifications.no', 'Hayır'), '""'); ?>,
    input: <?php echo _safeJson(t('notifications.input', 'Giriş'), '""'); ?>,
    ok: <?php echo _safeJson(t('notifications.ok', 'Tamam'), '""'); ?>,
    cancel: <?php echo _safeJson(t('notifications.cancel', 'İptal'), '""'); ?>
};

// Helper function for translations
function t(key) {
    // Try to get from global translations object if available
    if (typeof window.kitchenTranslations !== 'undefined' && window.kitchenTranslations[key]) {
        return window.kitchenTranslations[key];
    }
    // Return key if translation not found (no hardcoded fallbacks)
    return key;
}

// Ensure Toast is available
if (typeof window.Toast === 'undefined' && typeof window.showToast === 'undefined') {
    // Fallback toast implementation
    window.showToast = function(message, type = 'info') {
        console.log(`[${type.toUpperCase()}] ${message}`);
        // Use browser notification if available, otherwise alert
        if ('Notification' in window && Notification.permission === 'granted') {
            new Notification(message, { icon: '/favicon.ico', tag: 'kitchen-notification' });
        } else if (window.NotificationManager) {
            window.NotificationManager.info(message);
        }
    };
}

// Check if order has pending notifications that need approval
function hasPendingNotificationForOrder(orderId) {
    if (!window.pendingNotifications || !orderId) return false;
    
    for (const [notifId, notifData] of window.pendingNotifications.entries()) {
        if (notifData.orderIds && notifData.orderIds.includes(orderId)) {
            return true;
        }
    }
    return false;
}

// Disable order button when notification is pending
function disableOrderButton(orderId) {
    if (!orderId) return;
    
    const buttons = document.querySelectorAll(`[data-order-id="${orderId}"] button`);
    buttons.forEach(button => {
        button.disabled = true;
        button.style.opacity = '0.5';
        button.style.cursor = 'not-allowed';
        button.title = 'Bildirimi onaylamanız gerekiyor';
    });
}

// Enable order button after notification is approved
function enableOrderButton(orderId) {
    if (!orderId) return;
    
    const buttons = document.querySelectorAll(`[data-order-id="${orderId}"] button`);
    buttons.forEach(button => {
        button.disabled = false;
        button.style.opacity = '1';
        button.style.cursor = 'pointer';
        button.title = '';
    });
}

// Approve kitchen notification - called when user clicks "Bildirimi Onayla" button
window.approveKitchenNotification = async function(notificationId, orderIds, buttonElement) {
    if (!notificationId || !orderIds || orderIds.length === 0) return;
    
    try {
        // Remove notification element
        if (window.pendingNotifications && window.pendingNotifications.has(notificationId)) {
            const notifData = window.pendingNotifications.get(notificationId);
            if (notifData && notifData.element) {
                if (window.NotificationManager && window.NotificationManager.remove) {
                    window.NotificationManager.remove(notifData.element);
                }
            }
            window.pendingNotifications.delete(notificationId);
        }
        
        // CRITICAL FIX: Mark notification as read in database (was missing)
        try {
            await fetch('/api/notifications/mark-read', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': window.CSRF_TOKEN || ''
                },
                body: JSON.stringify({ notification_id: notificationId })
            });
        } catch (apiErr) {
            console.warn('Failed to mark notification as read in DB:', apiErr);
        }
        
        // Enable order buttons
        orderIds.forEach(orderId => {
            enableOrderButton(orderId);
        });
        
        // Show success message
        if (window.NotificationManager && window.NotificationManager.success) {
            window.NotificationManager.success('Bildirim onaylandı', 2000);
        }
    } catch (error) {
        console.error('Error approving notification:', error);
    }
};

// Mark order as SERVED and remove from screen
function markOrderAsServed(orderId, buttonElement) {
    // Clean up stale order tracker
    staleOrderTimers.delete(orderId);
    if (activeStaleOrderId === orderId) dismissStalePopup();

    if (!buttonElement) {
        buttonElement = document.querySelector(`[data-order-id="${orderId}"] button`);
    }
    
    const orderCard = buttonElement?.closest('[data-order-id]') || document.querySelector(`[data-order-id="${orderId}"]`);
    if (!orderCard) {
        console.error('Order card not found:', orderId);
        return;
    }
    
    // Optimistic UI update - hide immediately
    orderCard.style.opacity = '0.5';
    orderCard.style.pointerEvents = 'none';
    buttonElement.disabled = true;
    
    const csrfToken = window.CSRF_TOKEN || (typeof csrf_token !== 'undefined' ? csrf_token : '');
    const servedStatus = orderStatuses.SERVED || 'SERVED';
    const updateStatusUrl = baseUrl.endsWith('/') ? `${baseUrl}kitchen/update-status` : `${baseUrl}/kitchen/update-status`;
    
    fetch(updateStatusUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken,
            'Accept': 'application/json'
        },
        credentials: 'same-origin',
        body: JSON.stringify({
            order_id: orderId,
            status: servedStatus
        })
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            // Remove order card from DOM immediately
            orderCard.style.transition = 'opacity 0.3s ease-out, transform 0.3s ease-out';
            orderCard.style.opacity = '0';
            orderCard.style.transform = 'scale(0.95)';
            
            setTimeout(() => {
                orderCard.remove();
                // Update order count
                const countElement = document.querySelector('.active-orders-count');
                if (countElement) {
                    const currentCount = parseInt(countElement.textContent) || 0;
                    countElement.textContent = Math.max(0, currentCount - 1);
                }
            }, 300);
            
            if (window.NotificationManager && window.NotificationManager.success) {
                window.NotificationManager.success('Sipariş servis edildi', 2000);
            } else if (window.showToast) {
                window.showToast('Sipariş servis edildi', 'success');
            }
            
            // Refresh orders list after a short delay
            setTimeout(() => {
                loadOrders(true); // true = suppress notifications
            }, 500);
        } else {
            // Revert optimistic update
            orderCard.style.opacity = '1';
            orderCard.style.pointerEvents = '';
            buttonElement.disabled = false;
            
            const errorMsg = data.error || 'Sipariş durumu güncellenemedi';
            if (window.NotificationManager && window.NotificationManager.error) {
                window.NotificationManager.error('Hata: ' + errorMsg);
            } else if (window.showToast) {
                window.showToast(errorMsg, 'error');
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
        // Revert optimistic update
        orderCard.style.opacity = '1';
        orderCard.style.pointerEvents = '';
        buttonElement.disabled = false;
        
        const errorMsg = 'Bir hata oluştu: ' + error.message;
        if (window.NotificationManager && window.NotificationManager.error) {
            window.NotificationManager.error(errorMsg);
        } else if (window.showToast) {
            window.showToast(errorMsg, 'error');
        }
    });
}

// Override global updateOrderStatus from main.js for kitchen-specific behavior
function updateOrderStatus(orderId, status, buttonElement) {
    // Clean up stale tracker when status changes
    const preparingStatus = orderStatuses.PREPARING || 'PREPARING';
    if (status !== preparingStatus) {
        staleOrderTimers.delete(orderId);
        if (activeStaleOrderId === orderId) dismissStalePopup();
    }

    if (!buttonElement) {
        buttonElement = document.querySelector(`[data-order-id="${orderId}"] button`);
    }
    
    // Check if there are pending notifications for this order
    if (hasPendingNotificationForOrder(orderId)) {
        // Find pending notification for this order
        let pendingNotifId = null;
        let pendingOrderIds = [];
        for (const [notifId, notifData] of window.pendingNotifications.entries()) {
            if (notifData.orderIds && notifData.orderIds.includes(orderId)) {
                pendingNotifId = notifId;
                pendingOrderIds = notifData.orderIds;
                break;
            }
        }
        
        // Auto-approve notification when user clicks order button (fallback behavior)
        if (pendingNotifId) {
            window.approveKitchenNotification(pendingNotifId, pendingOrderIds, null);
        } else {
            // Show warning that notification must be approved first
            window.NotificationManager.warning('Bu sipariş için bildirim onayı gerekiyor. Lütfen önce bildirimi onaylayın.');
            return;
        }
    }
    
    // Optimistic UI update
    const orderCard = buttonElement?.closest('[data-order-id]');
    if (orderCard) {
        orderCard.setAttribute('data-status', status);
        buttonElement.disabled = true;
        buttonElement.style.opacity = '0.5';
    }
    
    const csrfToken = window.CSRF_TOKEN || (typeof csrf_token !== 'undefined' ? csrf_token : '');
    // Use kitchen-specific endpoint instead of generic API endpoint
    // Ensure baseUrl doesn't have trailing slash and route path is correct
    const updateStatusUrl = baseUrl.endsWith('/') ? `${baseUrl}kitchen/update-status` : `${baseUrl}/kitchen/update-status`;
    fetch(updateStatusUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken,
            'Accept': 'application/json'
        },
        credentials: 'same-origin',
        body: JSON.stringify({
            order_id: orderId,
            status: status
        })
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            const preparingStatus = orderStatuses.PREPARING || 'PREPARING';
            const readyStatus = orderStatuses.READY || 'READY';
            
            if (status === readyStatus) {
                // Order is ready - notify waiter (notification is already sent by backend)
                if (window.showToast) {
                    window.showToast(<?php echo json_encode(t('kitchen.orderReady', 'Order ready! Waiter notified.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>, 'success');
                }
                // Play success sound only when order is ready
                playNotificationSound();
            } else if (status === preparingStatus) {
                // Order is being prepared - no sound notification, just visual feedback
                if (window.showToast) {
                    window.showToast('Sipariş hazırlanmaya başlandı', 'info');
                }
                // NO SOUND HERE - only visual feedback
            }
            
            // Update UI optimistically - change button state
            // Note: For simplicity, we'll let the page refresh handle the full UI update
            // Optimistic updates are already handled before the API call
            // Refresh orders after a short delay to sync with server
            // Suppress notifications to avoid duplicate sounds when status changes
            setTimeout(() => {
                console.debug('[Kitchen] Refreshing orders after status update (notifications suppressed)');
                loadOrders(true); // true = suppress notifications
            }, 1000);
        } else {
            // Revert optimistic update
            if (orderCard) {
                const preparingStatus = orderStatuses.PREPARING || 'PREPARING';
                const pendingStatus = orderStatuses.PENDING || 'PENDING';
                orderCard.setAttribute('data-status', status === preparingStatus ? pendingStatus : preparingStatus);
                buttonElement.disabled = false;
                buttonElement.style.opacity = '1';
            }
            const errorMsg = data.error || 'Sipariş durumu güncellenemedi';
            if (window.showToast) {
                window.showToast(errorMsg, 'error');
            } else if (window.NotificationManager) {
                window.NotificationManager.error('Hata: ' + errorMsg);
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
        // Revert optimistic update
        if (orderCard) {
            const preparingStatus = orderStatuses.PREPARING || 'PREPARING';
            const pendingStatus = orderStatuses.PENDING || 'PENDING';
            orderCard.setAttribute('data-status', status === preparingStatus ? pendingStatus : preparingStatus);
            buttonElement.disabled = false;
            buttonElement.style.opacity = '1';
        }
        const errorMsg = 'Bir hata oluştu: ' + error.message;
        if (window.showToast) {
            window.showToast(errorMsg, 'error');
        } else if (window.NotificationManager) {
            window.NotificationManager.error(errorMsg);
        }
    });
}

// Override global updateOrderStatus to ensure kitchen-specific function is used
window.updateOrderStatus = updateOrderStatus;

function loadOrders(suppressNotifications = false) {
    // Skip if rate limited
    if (rateLimitBlocked && Date.now() < rateLimitUntil) {
        return;
    }
    
    fetch(`${baseUrl}/kitchen/getOrders?status=all`)
        .then(response => {
            if (!response.ok) {
                // Handle rate limiting (429)
                if (response.status === 429) {
                    const retryAfter = 30; // Default 30 seconds
                    rateLimitBlocked = true;
                    rateLimitUntil = Date.now() + (retryAfter * 1000);
                    
                    // Stop polling
                    if (refreshInterval) {
                        clearInterval(refreshInterval);
                        refreshInterval = null;
                    }
                    
                    // Resume after retry_after
                    setTimeout(() => {
                        rateLimitBlocked = false;
                        rateLimitUntil = 0;
                        if (!isUsingWebSocket) {
                            startPolling();
                        }
                    }, retryAfter * 1000);
                    
                    return;
                }
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .catch(error => {
            // Only log error if not suppressed (to avoid spam)
            if (!suppressNotifications) {
                // Check if it's a network error
                if (error.message && error.message.includes('Failed to fetch')) {
                    console.error('Error loading orders: Network error - check your connection');
                } else {
                    console.error('Error loading orders:', error);
                }
            }
            // Don't throw - allow polling to continue, return empty array
            return [];
        })
        .then(orders => {
            // Handle case where fetch failed and returned empty array
            if (!Array.isArray(orders)) {
                orders = [];
            }
            
            // Filter active orders using dynamic statuses
            const activeOrders = orders.filter(order => 
                order && order.status && !inactiveStatuses.includes(order.status)
            );
            
            // Check for new PENDING orders (new orders arrived) - track by order IDs
            const pendingStatus = orderStatuses.PENDING || 'PENDING';
            const newPendingOrders = activeOrders.filter(order => order.status === pendingStatus);
            const newPendingOrderIds = new Set(newPendingOrders.map(order => order.order_id));
            
            // Find truly new orders (not yet notified)
            const trulyNewOrders = Array.from(newPendingOrderIds).filter(id => !notifiedOrderIds.has(id));
            
            // Only play notification if not suppressed and there are truly new orders
            if (trulyNewOrders.length > 0 && !suppressNotifications) {
                console.debug('[Kitchen] New pending orders detected:', trulyNewOrders);
                
                // Mark these orders as notified BEFORE playing sound/showing notification
                // This prevents duplicate notifications if loadOrders is called multiple times quickly
                trulyNewOrders.forEach(id => notifiedOrderIds.add(id));
                
                // New order arrived - play sound
                playNotificationSound();
                
                // Show notification with approval button
                const message = `${trulyNewOrders.length} yeni sipariş geldi!`;
                const duration = 0; // Don't auto-dismiss - wait for user approval
                
                if (window.NotificationManager && window.NotificationManager.show) {
                    const notificationElement = window.NotificationManager.show(
                        message,
                        'info',
                        duration,
                        {
                            action: `
                                <button class="approve-notification-btn w-full px-4 py-2 bg-orange-500 text-white rounded-xl font-black text-sm hover:bg-orange-600 transition-all" 
                                        data-order-ids="${trulyNewOrders.join(',')}">
                                    Bildirimi Onayla
                                </button>
                            `
                        }
                    );
                    
                    // Store notification element for later removal
                    if (notificationElement) {
                        if (!window.pendingNotifications) {
                            window.pendingNotifications = new Map();
                        }
                        const notificationId = 'kitchen-new-orders-' + Date.now();
                        window.pendingNotifications.set(notificationId, {
                            element: notificationElement,
                            orderIds: trulyNewOrders
                        });
                        
                        // Add event listener to approval button
                        setTimeout(() => {
                            const approveBtn = notificationElement.querySelector('.approve-notification-btn');
                            if (approveBtn) {
                                approveBtn.addEventListener('click', function() {
                                    const orderIds = this.getAttribute('data-order-ids').split(',').filter(id => id);
                                    window.approveKitchenNotification(notificationId, orderIds, this);
                                });
                            }
                        }, 100);
                    }
                    
                    // Disable buttons for new orders
                    trulyNewOrders.forEach(orderId => {
                        disableOrderButton(orderId);
                    });
                } else {
                    // Fallback: Show simple notification and disable buttons
                    trulyNewOrders.forEach(orderId => {
                        disableOrderButton(orderId);
                    });
                    
                    if (window.showToast) {
                        window.showToast(message + ' (Sipariş butonuna tıklayarak onaylayın)', 'info');
                    }
                    
                    // Store notifications for approval when order button is clicked
                    if (!window.pendingNotifications) {
                        window.pendingNotifications = new Map();
                    }
                    const notificationId = 'kitchen-new-orders-' + Date.now();
                    window.pendingNotifications.set(notificationId, {
                        element: null,
                        orderIds: trulyNewOrders
                    });
                }
            }
            
            // Clean up notifiedOrderIds - remove orders that are no longer PENDING or no longer active
            // This prevents memory leak and allows re-notification if order status changes back
            const allActiveOrderIds = new Set(activeOrders.map(order => order.order_id));
            const currentPendingOrderIds = new Set(activeOrders
                .filter(order => order.status === (orderStatuses.PENDING || 'PENDING'))
                .map(order => order.order_id));
            
            // Remove orders that are no longer active or no longer PENDING
            // This allows orders to be re-notified if they come back to PENDING status (rare but possible)
            notifiedOrderIds.forEach(id => {
                if (!allActiveOrderIds.has(id) || !currentPendingOrderIds.has(id)) {
                    console.debug('[Kitchen] Removing order from notified list:', id);
                    notifiedOrderIds.delete(id);
                }
            });
            
            // Update order count
            const countElement = document.querySelector('.active-orders-count');
            if (countElement) {
                countElement.textContent = activeOrders.length;
            }
            
            // Update orders grid incrementally instead of reloading page
            updateOrdersGrid(activeOrders);
        })
        .catch(error => {
            console.error('Error loading orders:', error);
            // Don't show error toast on every poll failure to avoid spam
            // Only log to console
        });
}

// Use central WebSocket service for real-time updates
let refreshInterval = null;
let isUsingWebSocket = false;
let rateLimitBlocked = false;
let rateLimitUntil = 0;
// Track notified orders to prevent duplicate notifications
let notifiedOrderIds = new Set(<?php echo json_encode(array_column($activeOrders, 'order_id')); ?>);

// Stale Order Auto-Serve — variable declarations (hoisted so markOrderAsServed/updateOrderStatus can reference them)
const staleOrderTimers = new Map();
let activeStalePopup = null;
let activeStaleOrderId = null;
let staleCountdownInterval = null;

function kdsCardStatusClass(status) {
    const pendingStatus = orderStatuses.PENDING || 'PENDING';
    const preparingStatus = orderStatuses.PREPARING || 'PREPARING';
    const readyStatus = orderStatuses.READY || 'READY';
    if (status === preparingStatus) return 'kds-order-card--preparing';
    if (status === readyStatus) return 'kds-order-card--ready';
    return 'kds-order-card--pending';
}

function kdsUrgencyClass(waitMin) {
    return waitMin > 15 ? 'kds-urgency--critical' : (waitMin > 8 ? 'kds-urgency--warning' : 'kds-urgency--ok');
}

function kdsStatusBadgeClass(status) {
    const pendingStatus = orderStatuses.PENDING || 'PENDING';
    const preparingStatus = orderStatuses.PREPARING || 'PREPARING';
    const readyStatus = orderStatuses.READY || 'READY';
    if (status === preparingStatus) return 'kds-status-badge--preparing';
    if (status === readyStatus) return 'kds-status-badge--ready';
    return 'kds-status-badge--pending';
}

function kdsStatusLabel(status) {
    const pendingStatus = orderStatuses.PENDING || 'PENDING';
    const preparingStatus = orderStatuses.PREPARING || 'PREPARING';
    const readyStatus = orderStatuses.READY || 'READY';
    const labels = {
        [pendingStatus]: 'Bekliyor',
        [preparingStatus]: 'Hazırlanıyor',
        [readyStatus]: 'Hazır'
    };
    return labels[status] || labels[pendingStatus];
}

function createOrderCard(order, container) {
    const oid = order.order_id || '';
    const status = order.status || 'PENDING';
    const pendingStatus = orderStatuses.PENDING || 'PENDING';
    const preparingStatus = orderStatuses.PREPARING || 'PREPARING';
    const tableName = order.table_name || order.tableName || 'Masa';
    const createdAt = order.created_at ? new Date(order.created_at) : new Date();
    const timeStr = createdAt.toLocaleTimeString('tr-TR', {hour: '2-digit', minute: '2-digit'});
    const waitMin = Math.floor((Date.now() - createdAt.getTime()) / 60000);
    const urgClass = kdsUrgencyClass(waitMin);
    const cardStatusCls = kdsCardStatusClass(status);
    const badgeCls = kdsStatusBadgeClass(status);
    const statusLabel = kdsStatusLabel(status);
    const esc = s => String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
    const escId = esc(oid).replace(/'/g, "\\'");

    let itemsHtml = '';
    const items = (window.Utils && window.Utils.groupOrderItemsForDisplay ? window.Utils.groupOrderItemsForDisplay(order.items || []) : (order.items || []));
    if (items.length) {
        items.forEach(item => {
            const qty = item.quantity || 1;
            const name = item.name || item.item_name || item.menu_item_name || 'Ürün';
            const note = item.note || '';
            const excluded = (() => { try { const e = typeof item.excluded_ingredients === 'string' ? JSON.parse(item.excluded_ingredients) : item.excluded_ingredients; return Array.isArray(e) ? e : []; } catch(x) { return []; } })();
            const extras = (() => { try { const e = typeof item.selected_extras === 'string' ? JSON.parse(item.selected_extras) : item.selected_extras; return Array.isArray(e) ? e : []; } catch(x) { return []; } })();
            let details = '';
            if (excluded.length) details += `<div class="flex flex-wrap gap-1 mt-1">${excluded.map(i => `<span class="kds-tag--exclude">✕ ${esc(typeof i === 'object' ? i.name || '' : i)}</span>`).join('')}</div>`;
            if (extras.length) details += `<div class="flex flex-wrap gap-1 mt-1">${extras.map(i => `<span class="kds-tag--extra">+ ${esc(typeof i === 'object' ? i.name || '' : i)}</span>`).join('')}</div>`;
            if (note) details += `<div class="kds-item-note">${esc(note)}</div>`;
            itemsHtml += `<div class="kds-order-item"><div class="flex gap-3">
                <div class="shrink-0"><span class="kds-qty-badge">${qty}</span></div>
                <div class="flex-1 min-w-0"><h4 class="kds-item-name">${esc(name)}</h4>${details}</div></div></div>`;
        });
    }

    let btnHtml = '';
    const prepareText = window.kitchenTranslations?.prepare || 'Hazırla';
    const serveText = window.kitchenTranslations?.serve || 'Servis Et';
    if (status === pendingStatus) {
        btnHtml = `<button type="button" onclick="updateOrderStatus('${escId}','${esc(preparingStatus).replace(/'/g,"\\'")}',this)" class="kds-btn-prepare"><span>${prepareText}</span></button>`;
    } else if (status === preparingStatus) {
        btnHtml = `<button type="button" onclick="markOrderAsServed('${escId}',this)" class="kds-btn-serve"><span>${serveText}</span></button>`;
    }

    const card = document.createElement('div');
    card.className = `kitchen-order-card kds-order-card ${cardStatusCls}`;
    card.setAttribute('data-order-id', oid);
    card.setAttribute('data-status', status);
    card.setAttribute('data-table-name', tableName.toLowerCase());
    card.setAttribute('data-order-number', oid);
    card.style.maxHeight = '85vh';
    card.innerHTML = `
        <div class="kds-order-card__head flex items-start justify-between gap-3">
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 mb-1"><h3 class="kds-order-card__table">${esc(tableName)}</h3></div>
                <div class="kds-order-card__meta"><span class="font-mono">#${esc(oid.slice(-8))}</span><span class="kds-order-card__meta-dot"></span><span>${timeStr}</span></div>
            </div>
            <div class="flex flex-col items-end gap-1 shrink-0">
                <div class="kds-time-badge ${urgClass}"><span>${waitMin}dk</span></div>
                <span class="kds-status-badge ${badgeCls}">${statusLabel}</span>
            </div>
        </div>
        <div class="kds-order-items">${itemsHtml || '<div class="kds-order-item text-center text-slate-400 text-sm">Yükleniyor...</div>'}</div>
        <div class="kds-order-card__footer">${btnHtml}</div>`;
    container.appendChild(card);
}

function updateOrdersGrid(orders) {
    const container = document.getElementById('orders-grid') || document.querySelector('.kds-orders-grid');
    if (!container) return;
    
    const currentOrderIds = new Set(Array.from(document.querySelectorAll('[data-order-id]'))
        .map(el => el.getAttribute('data-order-id')));
    const newOrderIds = new Set(orders.map(o => o.order_id));
    
    // Remove orders that no longer exist
    currentOrderIds.forEach(orderId => {
        if (!newOrderIds.has(orderId)) {
            const orderCard = document.querySelector(`[data-order-id="${orderId}"]`);
            if (orderCard) {
                orderCard.remove();
            }
            // Clean up stale tracker for removed orders
            staleOrderTimers.delete(orderId);
            if (activeStaleOrderId === orderId) dismissStalePopup();
        }
    });
    
    // Update or add orders
    let hasNewOrders = false;
    orders.forEach(order => {
        const existingCard = document.querySelector(`[data-order-id="${order.order_id}"]`);
        const status = order.status || 'PENDING';
        const pendingStatus = orderStatuses.PENDING || 'PENDING';
        const preparingStatus = orderStatuses.PREPARING || 'PREPARING';
        const readyStatus = orderStatuses.READY || 'READY';
        
        if (existingCard) {
            // Update existing card status
            existingCard.setAttribute('data-status', status);
            
            // If order left PREPARING, clean up stale tracker
            if (status !== preparingStatus) {
                staleOrderTimers.delete(order.order_id);
                if (activeStaleOrderId === order.order_id) dismissStalePopup();
            }
            
            // Update status classes
            existingCard.className = `kitchen-order-card kds-order-card ${kdsCardStatusClass(status)}`;
            existingCard.style.maxHeight = '85vh';
            
            // Update status badge
            const statusBadge = existingCard.querySelector('.kds-status-badge');
            if (statusBadge) {
                statusBadge.className = `kds-status-badge ${kdsStatusBadgeClass(status)}`;
                statusBadge.textContent = kdsStatusLabel(status);
            }
            
            // Update button area based on status
            const buttonArea = existingCard.querySelector('.kds-order-card__footer');
            if (buttonArea) {
                const escapedOrderId = String(order.order_id || '').replace(/'/g, "\\'").replace(/"/g, '\\"');
                const escapedPreparingStatus = String(preparingStatus || '').replace(/'/g, "\\'").replace(/"/g, '\\"');
                const prepareText = window.kitchenTranslations?.prepare || 'Hazırla';
                const serveText = window.kitchenTranslations?.serve || 'Servis Et';
                const readyText = window.kitchenTranslations?.ready || 'Hazır';
                
                if (status === pendingStatus) {
                    buttonArea.innerHTML = `
                        <button type="button" onclick="updateOrderStatus('${escapedOrderId}', '${escapedPreparingStatus}', this)"
                                class="kds-btn-prepare disabled:opacity-50 disabled:cursor-not-allowed">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <span>${prepareText}</span>
                        </button>
                    `;
                } else if (status === preparingStatus) {
                    buttonArea.innerHTML = `
                        <button type="button" onclick="markOrderAsServed('${escapedOrderId}', this)"
                                class="kds-btn-serve disabled:opacity-50 disabled:cursor-not-allowed">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>${serveText}</span>
                        </button>
                    `;
                } else if (status === readyStatus) {
                    buttonArea.innerHTML = `
                        <div class="kds-btn-ready">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>${readyText}</span>
                        </div>
                    `;
                }
            }
        } else {
            createOrderCard(order, container);
        }
    });
    
    // Update order count
    const countElement = document.querySelector('.active-orders-count');
    if (countElement) {
        countElement.textContent = orders.length;
    }
}

// Initialize real-time updates: WebSocket when connected, own polling when disconnected
function initRealtimeUpdates() {
    // Initial load
    loadOrders();
    
    if (typeof window.realtimeService !== 'undefined' && window.realtimeService) {
        isUsingWebSocket = true;
        
        // useCustomLoader: we use our own loadOrders() - RealtimeService only forwards WS events, no poll
        window.realtimeService.start('orders', (data) => {
            if (data && (data.type === 'ORDER_UPDATE' || data.type === 'order.updated' || data.type === 'ORDER_CREATED' || data.type === 'order.created')) {
                loadOrders();
            } else if (Array.isArray(data)) {
                updateOrdersGrid(data.filter(order => !inactiveStatuses.includes(order.status)));
            } else {
                loadOrders();
            }
        }, { interval: pollingInterval || 5000, useCustomLoader: true });
        
        // When WS disconnects, start our polling; when connects, stop it
        window.realtimeService.onStatusChange((status) => {
            if (status === 'connected') {
                if (refreshInterval) { clearInterval(refreshInterval); refreshInterval = null; }
            } else {
                if (!refreshInterval && !document.hidden) startPolling();
            }
        });
        
        // If WS not connected yet, use polling until it connects
        if (window.realtimeService.connectionStatus !== 'connected') {
            startPolling();
        }
        
        console.log('Kitchen dashboard: WebSocket + polling fallback');
    } else {
        isUsingWebSocket = false;
        startPolling();
        console.log('Kitchen dashboard: Polling only');
    }
}

function startPolling() {
    // Clear any existing interval
    if (refreshInterval) {
        clearInterval(refreshInterval);
    }
    
    // Poll at configured interval (default 2 seconds for faster updates)
    refreshInterval = setInterval(() => {
        loadOrders();
    }, pollingInterval || 2000);
}

// Initialize when page loads
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        initRealtimeUpdates();
    });
} else {
    initRealtimeUpdates();
}

// Stop polling when page is hidden (battery/performance optimization)
document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
        if (refreshInterval) {
            clearInterval(refreshInterval);
            refreshInterval = null;
        }
        if (staleCountdownInterval) {
            clearInterval(staleCountdownInterval);
            staleCountdownInterval = null;
        }
    } else {
        const wsConnected = window.realtimeService?.connectionStatus === 'connected';
        if (!wsConnected && !refreshInterval) {
            startPolling();
        }
        setTimeout(checkStaleOrders, 1000);
    }
});

// Cleanup on page unload to prevent memory leaks
window.addEventListener('beforeunload', () => {
    if (refreshInterval) {
        clearInterval(refreshInterval);
        refreshInterval = null;
    }
});

// Filter orders by search and status
function filterOrders() {
    const searchTerm = (document.getElementById('kitchen-search')?.value || '').toLowerCase();
    const statusFilter = document.getElementById('kitchen-status-filter')?.value || 'all';
    const orderCards = document.querySelectorAll('.kitchen-order-card');
    
    let visibleCount = 0;
    orderCards.forEach(card => {
        const tableName = (card.getAttribute('data-table-name') || '').toLowerCase();
        const orderNumber = (card.getAttribute('data-order-number') || '').toLowerCase();
        const status = card.getAttribute('data-status') || '';
        
        const matchesSearch = !searchTerm || tableName.includes(searchTerm) || orderNumber.includes(searchTerm);
        const matchesStatus = statusFilter === 'all' || status === statusFilter;
        
        if (matchesSearch && matchesStatus) {
            card.style.display = '';
            visibleCount++;
        } else {
            card.style.display = 'none';
        }
    });
    
    // Update active orders count
    const countElement = document.querySelector('.active-orders-count');
    if (countElement) {
        countElement.textContent = visibleCount;
    }
}

// Fullscreen toggle - hides any sidebar/menu and makes content fullscreen
function toggleFullscreen() {
    // Hide any sidebar/menu elements
    const sidebars = document.querySelectorAll('[id*="sidebar"], [class*="sidebar"], [id*="menu"], [class*="menu"]');
    sidebars.forEach(sidebar => {
        if (sidebar && sidebar.style) {
            sidebar.style.display = 'none';
        }
    });
    
    // Check if fullscreen API is supported
    if (!document.documentElement.requestFullscreen && 
        !document.documentElement.webkitRequestFullscreen && 
        !document.documentElement.mozRequestFullScreen && 
        !document.documentElement.msRequestFullscreen) {
        // Fullscreen not supported
        return;
    }
    
    // Toggle fullscreen
    if (!document.fullscreenElement && 
        !document.webkitFullscreenElement && 
        !document.mozFullScreenElement && 
        !document.msFullscreenElement) {
        // Enter fullscreen
        const requestFullscreen = document.documentElement.requestFullscreen ||
                                  document.documentElement.webkitRequestFullscreen ||
                                  document.documentElement.mozRequestFullScreen ||
                                  document.documentElement.msRequestFullscreen;
        
        if (requestFullscreen) {
            requestFullscreen.call(document.documentElement).catch(err => {
                // Only log errors that aren't related to user gesture requirements
                const errorMessage = err.message || err.toString() || '';
                if (!errorMessage.toLowerCase().includes('permission') && 
                    !errorMessage.toLowerCase().includes('not allowed') &&
                    !errorMessage.toLowerCase().includes('user gesture')) {
                    console.error('Fullscreen error:', err);
                }
            });
        }
    } else {
        // Exit fullscreen
        const exitFullscreen = document.exitFullscreen ||
                               document.webkitExitFullscreen ||
                               document.mozCancelFullScreen ||
                               document.msExitFullscreen;
        
        if (exitFullscreen) {
            exitFullscreen.call(document).catch(err => {
                console.error('Exit fullscreen error:', err);
            });
        }
    }
}

// Listen for fullscreen changes
document.addEventListener('fullscreenchange', function() {
    const sidebars = document.querySelectorAll('[id*="sidebar"], [class*="sidebar"], [id*="menu"], [class*="menu"]');
    if (document.fullscreenElement) {
        // In fullscreen, ensure sidebars are hidden
        sidebars.forEach(sidebar => {
            if (sidebar && sidebar.style) {
                sidebar.style.display = 'none';
            }
        });
    } else {
        // Exit fullscreen - sidebars will remain hidden (kitchen dashboard doesn't have sidebar)
        // This is fine for kitchen display screens
    }
});

// Notification sound
function playNotificationSound() {
    // Create a simple beep sound using Web Audio API
    try {
        const audioContext = new (window.AudioContext || window.webkitAudioContext)();
        const oscillator = audioContext.createOscillator();
        const gainNode = audioContext.createGain();
        
        oscillator.connect(gainNode);
        gainNode.connect(audioContext.destination);
        
        oscillator.frequency.value = 800;
        oscillator.type = 'sine';
        
        gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
        gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.3);
        
        oscillator.start(audioContext.currentTime);
        oscillator.stop(audioContext.currentTime + 0.3);
    } catch (e) {
        console.error('Sound error:', e);
    }
}

// Note: New order notifications are handled in loadOrders() function
// No need for separate checkForNewOrders() to avoid duplicate notifications

// ============================================================
// Stale Order Auto-Serve System
// Orders in PREPARING status for 15+ minutes get a popup asking
// if they're still being prepared. No response in 5 minutes
// triggers automatic SERVED status.
// ============================================================

const STALE_THRESHOLD_MS = 15 * 60 * 1000;   // 15 minutes
const AUTO_SERVE_DELAY_MS = 5 * 60 * 1000;   // 5 minutes
const STALE_CHECK_INTERVAL_MS = 30 * 1000;    // check every 30 seconds

function trackPreparingOrders() {
    const preparingStatus = orderStatuses.PREPARING || 'PREPARING';
    const cards = document.querySelectorAll('.kitchen-order-card');
    const currentPreparingIds = new Set();

    cards.forEach(card => {
        const orderId = card.getAttribute('data-order-id');
        const status = card.getAttribute('data-status');
        if (status === preparingStatus && orderId) {
            currentPreparingIds.add(orderId);
            if (!staleOrderTimers.has(orderId)) {
                staleOrderTimers.set(orderId, {
                    preparingSince: Date.now(),
                    popupShownAt: null
                });
            }
        }
    });

    // Clean up orders that are no longer PREPARING
    for (const [orderId] of staleOrderTimers) {
        if (!currentPreparingIds.has(orderId)) {
            staleOrderTimers.delete(orderId);
            if (activeStaleOrderId === orderId) {
                dismissStalePopup();
            }
        }
    }
}

function checkStaleOrders() {
    trackPreparingOrders();

    const now = Date.now();

    for (const [orderId, data] of staleOrderTimers) {
        const elapsed = now - data.preparingSince;

        if (data.popupShownAt) {
            // Popup already shown — check if auto-serve timeout passed
            const popupElapsed = now - data.popupShownAt;
            if (popupElapsed >= AUTO_SERVE_DELAY_MS) {
                autoServeStaleOrder(orderId);
            } else if (activeStaleOrderId !== orderId && !activeStalePopup) {
                // Popup was dismissed but timer still running — re-show if no other popup active
                showStalePopup(orderId, AUTO_SERVE_DELAY_MS - popupElapsed);
            }
        } else if (elapsed >= STALE_THRESHOLD_MS) {
            // First time exceeding threshold — show popup
            data.popupShownAt = now;
            showStalePopup(orderId, AUTO_SERVE_DELAY_MS);
        }
    }
}

function showStalePopup(orderId, remainingMs) {
    // Don't stack popups — dismiss any existing one first
    if (activeStalePopup) {
        // If we already show a popup for a different order, queue this one (checkStaleOrders will pick it up)
        if (activeStaleOrderId !== orderId) return;
        dismissStalePopup();
    }

    const card = document.querySelector(`[data-order-id="${orderId}"]`);
    const tableName = card ? (card.querySelector('h3')?.textContent || orderId) : orderId;

    activeStaleOrderId = orderId;

    const overlay = document.createElement('div');
    overlay.id = 'stale-order-overlay';
    overlay.className = 'fixed inset-0 z-[9999] flex items-center justify-center p-4';
    overlay.style.cssText = 'background:rgba(0,0,0,0.75);backdrop-filter:blur(4px);animation:stalePopupFadeIn 0.25s ease-out';

    const remainingSec = Math.max(0, Math.ceil(remainingMs / 1000));

    overlay.innerHTML = `
        <div class="kds-stale-modal" style="animation:stalePopupSlideUp 0.25s ease-out">
            <div class="kds-stale-modal__head">
                <div class="flex items-center gap-3 mb-2">
                    <div class="w-10 h-10 rounded-xl bg-amber-50 border border-amber-200 flex items-center justify-center">
                        <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="kds-stale-modal__title">Bu sipariş hâlâ mi hazırlanıyor?</h3>
                        <p class="text-slate-500 text-sm">${tableName} — 15+ dakikadır hazırlanıyor</p>
                    </div>
                </div>
            </div>
            <div class="kds-stale-modal__body">
                <div class="flex items-center justify-center gap-2 mb-4">
                    <div class="px-4 py-2 rounded-xl bg-red-50 border border-red-200">
                        <span class="text-red-600 font-mono font-bold text-xl" id="stale-countdown">${formatCountdown(remainingSec)}</span>
                    </div>
                    <span class="text-slate-500 text-sm">sonra otomatik servis edilecek</span>
                </div>
                <div class="w-full bg-slate-100 rounded-full h-1.5 overflow-hidden">
                    <div id="stale-progress" class="h-full bg-gradient-to-r from-amber-500 to-red-500 rounded-full transition-all duration-1000 ease-linear" style="width:100%"></div>
                </div>
            </div>
            <div class="px-6 pb-6 flex gap-3">
                <button type="button" onclick="stalePopupStillPreparing()" class="kds-btn-prepare flex-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Hâlâ Hazırlanıyor
                </button>
                <button type="button" onclick="stalePopupServeNow()" class="kds-btn-serve flex-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    Servis Edildi
                </button>
            </div>
        </div>
    `;

    document.body.appendChild(overlay);
    activeStalePopup = overlay;

    // Start live countdown
    let secondsLeft = remainingSec;
    const totalSeconds = Math.ceil(AUTO_SERVE_DELAY_MS / 1000);
    staleCountdownInterval = setInterval(() => {
        secondsLeft--;
        const countdownEl = document.getElementById('stale-countdown');
        const progressEl = document.getElementById('stale-progress');
        if (countdownEl) countdownEl.textContent = formatCountdown(Math.max(0, secondsLeft));
        if (progressEl) progressEl.style.width = Math.max(0, (secondsLeft / totalSeconds) * 100) + '%';
        if (secondsLeft <= 0) {
            clearInterval(staleCountdownInterval);
            staleCountdownInterval = null;
            autoServeStaleOrder(orderId);
        }
    }, 1000);

    // Play attention sound
    playStaleAlertSound();
}

function formatCountdown(seconds) {
    const m = Math.floor(seconds / 60);
    const s = seconds % 60;
    return String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
}

function dismissStalePopup() {
    if (staleCountdownInterval) {
        clearInterval(staleCountdownInterval);
        staleCountdownInterval = null;
    }
    if (activeStalePopup) {
        activeStalePopup.remove();
        activeStalePopup = null;
    }
    activeStaleOrderId = null;
}

function stalePopupStillPreparing() {
    const orderId = activeStaleOrderId;
    dismissStalePopup();
    if (orderId && staleOrderTimers.has(orderId)) {
        // Reset both timers — gives another full 15 minutes before next popup
        staleOrderTimers.set(orderId, {
            preparingSince: Date.now(),
            popupShownAt: null
        });
    }
    if (window.NotificationManager && window.NotificationManager.info) {
        window.NotificationManager.info('Siparis hazirlaniyor olarak devam ediyor', 2000);
    }
}

function stalePopupServeNow() {
    const orderId = activeStaleOrderId;
    dismissStalePopup();
    if (orderId) {
        staleOrderTimers.delete(orderId);
        markOrderAsServed(orderId, null);
    }
}

function autoServeStaleOrder(orderId) {
    dismissStalePopup();
    staleOrderTimers.delete(orderId);
    console.log('[StaleOrder] Auto-serving order:', orderId);
    markOrderAsServed(orderId, null);
    if (window.NotificationManager && window.NotificationManager.warning) {
        window.NotificationManager.warning('Siparis otomatik servis edildi (zaman asimi)', 3000);
    }
}

function playStaleAlertSound() {
    try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        // Two-tone alert: beep-beep
        [0, 0.2].forEach(delay => {
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.frequency.value = 600;
            osc.type = 'sine';
            gain.gain.setValueAtTime(0.25, ctx.currentTime + delay);
            gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + delay + 0.15);
            osc.start(ctx.currentTime + delay);
            osc.stop(ctx.currentTime + delay + 0.15);
        });
    } catch (e) { /* ignore audio errors */ }
}

// Inject keyframe animations for popup
(function() {
    const style = document.createElement('style');
    style.textContent = `
        @keyframes stalePopupFadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes stalePopupSlideUp { from { opacity: 0; transform: translateY(20px) scale(0.95); } to { opacity: 1; transform: translateY(0) scale(1); } }
    `;
    document.head.appendChild(style);
})();

// Start the stale order checker
setInterval(checkStaleOrders, STALE_CHECK_INTERVAL_MS);
// Also run once shortly after page load
setTimeout(checkStaleOrders, 5000);
</script>

<?php if ($isSuperAdmin): ?>
        </div>
        <!-- Kitchen Management View closing div -->
    </div>
    <!-- Super admin container closing div -->
</div>

<script>
// Super Admin Business Selector
(function() {
    const bsScript = document.createElement('script');
    bsScript.src = '<?php echo BASE_URL; ?>/assets/js/business-selector.js?v=<?php echo time(); ?>';
    bsScript.onload = function() {
        if (typeof BusinessSelector === 'undefined') {
            console.error('BusinessSelector not loaded');
            return;
        }
        
        BusinessSelector.init({
            baseUrl: <?php echo json_encode($baseUrl); ?>
        });
        
        // Check if business_id is in URL (page reload scenario)
        const urlParams = new URLSearchParams(window.location.search);
        const businessIdFromUrl = urlParams.get('business_id');
        
        if (businessIdFromUrl) {
            // Business ID in URL - load business info directly from API and show kitchen view
            fetch(`${BusinessSelector.config.baseUrl}/api/qodmin/businesses`)
                .then(response => response.json())
                .then(data => {
                    const businesses = Array.isArray(data) ? data : (data.businesses || data.data || []);
                    const business = businesses.find(b => 
                        (b.business_id || b.id) === businessIdFromUrl
                    );
                    
                    if (business) {
                        // Determine business name with improved fallback logic
                        let businessName = business.company_name || business.business_name || business.name;
                        if (!businessName || businessName.trim() === '') {
                            // Try owner name
                            const ownerName = business.owner_name || business.owner || '';
                            if (ownerName && ownerName.trim() !== '') {
                                businessName = ownerName;
                            } else {
                                // Try email
                                const email = business.email || business.business_email || '';
                                if (email && email.trim() !== '') {
                                    businessName = email.split('@')[0]; // Use email username part
                                } else {
                                    // Last resort: use generic name
                                    businessName = 'İşletme';
                                }
                            }
                        }
                        
                        // Set in session storage
                        sessionStorage.setItem('selected_business_id', businessIdFromUrl);
                        sessionStorage.setItem('selected_business_name', businessName);
                        window.currentBusinessId = businessIdFromUrl;
                        
                        // Show kitchen management view
                        document.getElementById('business-selection-view').classList.add('hidden');
                        document.getElementById('kitchen-management-view').classList.remove('hidden');
                        const kitchenDashboard = document.getElementById('kitchen-dashboard');
                        if (kitchenDashboard) {
                            kitchenDashboard.style.display = 'block';
                        }
                        
                        // Update business name display
                        const businessNameElement = document.getElementById('selected-business-name');
                        if (businessNameElement) {
                            businessNameElement.textContent = businessName;
                        }
                    } else {
                        console.error('Business not found:', businessIdFromUrl);
                    }
                })
                .catch(error => {
                    console.error('Error loading business info:', error);
                });
        } else {
            // No business_id in URL - show business selection
            BusinessSelector.loadBusinesses().then(() => {
                BusinessSelector.renderBusinessGrid('business-grid', function(businessId, businessName) {
                    // Set business ID in session storage
                    sessionStorage.setItem('selected_business_id', businessId);
                    sessionStorage.setItem('selected_business_name', businessName);
                    window.currentBusinessId = businessId;
                    
                    // Show kitchen management view
                    document.getElementById('business-selection-view').classList.add('hidden');
                    document.getElementById('kitchen-management-view').classList.remove('hidden');
                    const kitchenDashboard = document.getElementById('kitchen-dashboard');
                    if (kitchenDashboard) {
                        kitchenDashboard.style.display = 'block';
                    }
                    
                    // Update business name display
                    const businessNameElement = document.getElementById('selected-business-name');
                    if (businessNameElement) {
                        businessNameElement.textContent = businessName;
                    }
                    
                    // Update URL without page reload (use history.pushState instead of window.location.href)
                    const url = new URL(window.location.href);
                    url.searchParams.set('business_id', businessId);
                    window.history.pushState({ businessId, businessName }, '', url.toString());
                });
            });
        }
    };
    document.head.appendChild(bsScript);
})();

// Back to business selection
window.backToBusinessSelection = function() {
    BusinessSelector.showSelectionView('business-selection-view', 'kitchen-management-view');
    const kitchenDashboard = document.getElementById('kitchen-dashboard');
    if (kitchenDashboard) {
        kitchenDashboard.style.display = 'none';
    }
    
    // Remove business_id from URL
    const url = new URL(window.location.href);
    url.searchParams.delete('business_id');
    window.history.pushState({}, '', url.toString());
    
    // Clear session storage
    sessionStorage.removeItem('selected_business_id');
    sessionStorage.removeItem('selected_business_name');
    window.currentBusinessId = null;
};
</script>
<?php endif; ?>

