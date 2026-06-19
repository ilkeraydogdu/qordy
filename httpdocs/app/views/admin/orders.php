<?php
require_once __DIR__ . '/../../helpers/translations.php';
if (!function_exists('getAdminUrl')) {
    require_once __DIR__ . '/../../helpers/url_helper.php';
}

$title = t('orders.title', 'Sipariş Yönetimi') . ' - ' . getAppConfig()->getAppName();
$isSuperAdmin = $is_super_admin ?? false;
$apiPrefix = $isSuperAdmin ? '/api/qodmin' : '/api/business';
// Note: Layout is automatically included by Controller::view() method
// No need for ob_start() or manual layout include
?>

<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/business-theme.css?v=<?php echo @filemtime(dirname(__DIR__, 3) . '/public/assets/css/business-theme.css'); ?>">
<div class="q-page q-biz-theme animate-slide-up orders-page" data-orders-root>
  <div class="q-container q-stack">
    <?php if ($is_super_admin ?? false): ?>
    <!-- SUPER ADMIN VIEW -->
    <div id="business-selection-view">
        <header class="q-page-header">
            <div>
                <p class="q-page-header__eyebrow">Siparişler</p>
                <h1 class="q-page-header__title">Sipariş Yönetimi</h1>
                <p class="q-page-header__subtitle">Siparişlerini görüntülemek istediğiniz işletmeyi seçin</p>
            </div>
        </header>
        <div id="business-grid" class="q-grid q-grid--4"></div>
    </div>
    
    <div id="order-management-view" class="hidden">
        <header class="q-page-header">
            <div class="q-toolbar" style="gap:var(--space-3);">
                <button type="button" onclick="backToBusinessSelection()" class="q-icon-btn" aria-label="İşletme seçimine dön">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                </button>
                <div>
                    <p class="q-page-header__eyebrow">Siparişler</p>
                    <h1 class="q-page-header__title"><span id="selected-business-name"></span></h1>
                    <p class="q-page-header__subtitle">Sipariş Yönetimi</p>
                </div>
            </div>
        </header>
    <?php else: ?>
    <!-- REGULAR VIEW -->
        <div class="q-page-header">
            <div>
                <p class="q-page-header__eyebrow">Siparişler</p>
                <h1 class="q-page-header__title"><?php echo t('orders.title', 'Sipariş Yönetimi'); ?></h1>
                <p class="q-page-header__subtitle"><?php echo t('order.all'); ?></p>
            </div>
        </div>
    <?php endif; ?>

        <div class="q-card q-card--pad orders-search-bar q-stack">
            <div class="q-toolbar q-toolbar--between">
                <div class="q-input-icon-wrap">
                    <svg class="q-input-icon-wrap__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                    <input type="search" id="searchInput" placeholder="<?php echo htmlspecialchars(t('orders.searchByOrderNo', 'Sipariş numarası ile ara (örn: ord_abc123 veya son 6 karakter)')); ?>"
                           oninput="filterOrders()"
                           onkeydown="if(event.key==='Enter'){event.preventDefault();filterOrders();}"
                           class="search-input q-input"
                           autocomplete="off">
                </div>
                <div class="q-toolbar" style="flex-shrink:0;">
                    <button onclick="refreshOrders()" class="q-btn q-btn--primary q-btn--sm">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                        <span class="hidden sm:inline"><?php echo t('order.refresh'); ?></span>
                    </button>
                    <button onclick="exportOrders()" class="q-btn q-btn--ghost q-btn--sm">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        <span class="hidden sm:inline"><?php echo t('order.export'); ?></span>
                    </button>
                </div>
            </div>
            
            <div class="q-filter-bar">
                <span class="q-filter-group__label">Durum</span>
                <button onclick="filterByStatus('all')" class="filter-chip q-btn q-btn--ghost q-btn--sm q-btn--ink active" data-status="all"><?php echo t('order.allStatus'); ?></button>
                <button onclick="filterByStatus('pending')" class="filter-chip q-btn q-btn--ghost q-btn--sm" data-status="pending"><?php echo t('order.waiting'); ?></button>
                <button onclick="filterByStatus('preparing')" class="filter-chip q-btn q-btn--ghost q-btn--sm" data-status="preparing"><?php echo t('order.preparing'); ?></button>
                <button onclick="filterByStatus('ready')" class="filter-chip q-btn q-btn--ghost q-btn--sm" data-status="ready"><?php echo t('order.readyToServe'); ?></button>
                <button onclick="filterByStatus('served')" class="filter-chip q-btn q-btn--ghost q-btn--sm" data-status="served"><?php echo t('order.completed'); ?></button>
                <button onclick="filterByStatus('cancelled')" class="filter-chip q-btn q-btn--ghost q-btn--sm" data-status="cancelled"><?php echo t('order.cancelledStatus'); ?></button>
                
                <span class="q-filter-divider" aria-hidden="true"></span>
                <span class="q-filter-group__label">Tarih</span>
                <button onclick="filterByDate('all')" class="filter-chip q-btn q-btn--ghost q-btn--sm q-btn--ink active" data-date="all">Tümü</button>
                <button onclick="filterByDate('today')" class="filter-chip q-btn q-btn--ghost q-btn--sm" data-date="today">Bugün</button>
                <button onclick="filterByDate('week')" class="filter-chip q-btn q-btn--ghost q-btn--sm" data-date="week">Bu Hafta</button>
                <button onclick="filterByDate('month')" class="filter-chip q-btn q-btn--ghost q-btn--sm" data-date="month">Bu Ay</button>
                <button onclick="filterByDate('custom')" class="filter-chip q-btn q-btn--ghost q-btn--sm" data-date="custom">
                    <svg class="w-3 h-3 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>Özel
                </button>
            </div>
            
            <div id="customDateRange" class="hidden q-date-panel">
                <div class="q-toolbar" style="align-items:flex-end;flex-wrap:wrap;">
                    <div style="flex:1 1 140px;min-width:0;">
                        <label class="q-filter-group__label" for="startDate">Başlangıç</label>
                        <input type="date" id="startDate" class="q-input">
                    </div>
                    <div style="flex:1 1 140px;min-width:0;">
                        <label class="q-filter-group__label" for="endDate">Bitiş</label>
                        <input type="date" id="endDate" class="q-input">
                    </div>
                    <button type="button" onclick="applyCustomDateRange()" class="q-btn q-btn--primary q-btn--sm">Uygula</button>
                </div>
            </div>
        </div>

        <!-- Summary Stats -->
        <div id="orderSummary" class="q-grid q-grid--4">
            <div class="q-stat">
                <div class="q-stat__top"><span class="q-stat__label">Toplam</span></div>
                <div class="q-stat__value" id="totalOrdersCount">0</div>
            </div>
            <div class="q-stat">
                <div class="q-stat__top"><span class="q-stat__label">Tutar</span></div>
                <div class="q-stat__value" id="totalOrdersAmount">₺0.00</div>
            </div>
            <div class="q-stat">
                <div class="q-stat__top"><span class="q-stat__label">Bekleyen</span></div>
                <div class="q-stat__value" id="pendingOrdersCount">0</div>
            </div>
            <div class="q-stat">
                <div class="q-stat__top"><span class="q-stat__label">Hazır</span></div>
                <div class="q-stat__value" id="readyOrdersCount">0</div>
            </div>
        </div>

    <div class="q-stack" id="ordersZoneView">
        <div id="emptyStateView" class="hidden q-card q-empty">
            <svg class="q-empty__icon empty-state-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
            <p class="q-empty__title">Henüz sipariş bulunmuyor</p>
            <p class="q-hint">Filtreleri değiştirerek tekrar deneyin</p>
        </div>
    </div>
    <?php if ($is_super_admin ?? false): ?>
    </div><!-- /order-management-view -->
    <?php endif; ?>
  </div>
</div>

<!-- Order Details Modal -->
<div id="orderDetailsModal" class="q-modal-backdrop hidden modal-responsive">
    <div class="q-modal-backdrop__scrim" onclick="closeOrderModal()"></div>
    <div class="q-modal modal-content-responsive no-scrollbar">
        <div class="q-modal__header">
            <h2 class="q-modal__title">Sipariş Detayı</h2>
            <button type="button" onclick="closeOrderModal()" class="q-icon-btn" aria-label="Kapat">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        <div id="orderDetailsContent"></div>
    </div>
</div>

<!-- Table Sessions Modal -->
<div id="tableSessionsModal" class="q-modal-backdrop hidden">
    <div class="q-modal-backdrop__scrim" onclick="closeTableSessionsModal()"></div>
    <div class="q-modal q-modal--wide no-scrollbar">
        <div class="q-modal__header">
            <h2 class="q-modal__title">Masa Oturumları</h2>
            <button type="button" onclick="closeTableSessionsModal()" class="q-icon-btn" aria-label="Kapat">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        <div id="tableSessionsContent"></div>
    </div>
</div>

<script src="<?php echo BASE_URL; ?>/assets/js/admin/orders.js?v=<?php echo filemtime(__DIR__ . '/../../../public/assets/js/admin/orders.js') ?: time(); ?>"></script>
<script>
// Initialize OrdersPage with configuration
document.addEventListener('DOMContentLoaded', function() {
    // Check if OrdersPage is defined
    if (typeof OrdersPage === 'undefined') {
        console.error('OrdersPage module not loaded. Please check if orders.js is loaded correctly.');
        return;
    }
    OrdersPage.init({
        baseUrl: <?php echo json_encode(BASE_URL ?? '', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
        apiPrefix: <?php echo json_encode($apiPrefix ?? '/api/business', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
        translations: <?php echo json_encode([
            'allStatus' => t('order.allStatus'),
            'waiting' => t('order.waiting'),
            'preparing' => t('order.preparing'),
            'readyToServe' => t('order.readyToServe'),
            'completed' => t('order.completed'),
            'cancelledStatus' => t('order.cancelledStatus'),
            'prepare' => t('order.prepare'),
            'ready' => t('order.ready'),
            'serve' => t('order.serve'),
            'orderInfo' => t('order.orderInfo'),
            'orderId' => t('order.orderId'),
            'table' => t('order.table'),
            'status' => t('order.status'),
            'unknown' => t('order.unknown'),
            'items' => t('order.items'),
            'total' => t('order.total'),
            'orderDetailsFailed' => t('order.orderDetailsFailed'),
            'details' => t('common.details'),
            'tableLabel' => t('order.tableLabel'),
            'customerLabel' => t('order.customerLabel'),
            'amountLabel' => t('order.amountLabel'),
            'orderItems' => t('order.orderItems'),
            'close' => t('common.close'),
            'print' => t('common.print'),
            'complete' => t('common.complete'),
            'payment' => t('common.payment'),
            'cash' => t('common.cash'),
            'quantity' => t('common.quantity'),
            'product' => t('common.product'),
            'errorLoading' => t('order.errorLoading'),
            'served' => t('order.served', 'Tamamlandı'),
            'cancelled' => t('order.cancelled', 'İptal Edildi'),
            'ordersSingular' => t('order.ordersSingular', 'sipariş'),
        ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>
    });
    
    // Filter chip active state is managed by OrdersPage.filterByStatus/filterByDate
});

<?php if ($is_super_admin ?? false): ?>
// Super Admin Business Selector
const bsScript = document.createElement('script');
bsScript.src = '<?php echo BASE_URL; ?>/assets/js/business-selector.js?v=<?php echo time(); ?>';
bsScript.onload = function() {
    if (!BusinessSelector) return;
    BusinessSelector.init({ baseUrl: '<?php echo BASE_URL; ?>' });
    
    const urlParams = new URLSearchParams(window.location.search);
    const businessIdFromUrl = urlParams.get('business_id');
    
    if (businessIdFromUrl) {
        const apiPrefix = <?php echo json_encode($apiPrefix); ?>;
        fetch(`${BusinessSelector.config.baseUrl}${apiPrefix}/businesses`)
            .then(response => response.json())
            .then(data => {
                const businesses = Array.isArray(data) ? data : (data.businesses || data.data || []);
                const business = businesses.find(b => (b.business_id || b.id) === businessIdFromUrl);
                
                if (business) {
                    let businessName = business.company_name || business.business_name || business.name;
                    if (!businessName || businessName.trim() === '') {
                        const ownerName = business.owner_name || business.owner || '';
                        if (ownerName && ownerName.trim() !== '') {
                            businessName = ownerName;
                        } else {
                            const email = business.email || business.business_email || '';
                            businessName = email ? email.split('@')[0] : 'İşletme';
                        }
                    }
                    
                    sessionStorage.setItem('selected_business_id', businessIdFromUrl);
                    sessionStorage.setItem('selected_business_name', businessName);
                    window.currentBusinessId = businessIdFromUrl;
                    BusinessSelector.showContentView('business-selection-view', 'order-management-view', businessName);
                    if (typeof refreshOrders === 'function') refreshOrders();
                }
            })
            .catch(error => console.error('Error loading business info:', error));
    } else {
        BusinessSelector.loadBusinesses().then(() => {
            BusinessSelector.renderBusinessGrid('business-grid', (id, name) => {
                sessionStorage.setItem('selected_business_id', id);
                sessionStorage.setItem('selected_business_name', name);
                window.currentBusinessId = id;
                BusinessSelector.showContentView('business-selection-view', 'order-management-view', name);
                const url = new URL(window.location.href);
                url.searchParams.set('business_id', id);
                window.history.pushState({ businessId: id, businessName: name }, '', url.toString());
                if (typeof refreshOrders === 'function') refreshOrders();
            });
        });
    }
};
document.head.appendChild(bsScript);
window.backToBusinessSelection = () => {
    BusinessSelector.showSelectionView('business-selection-view', 'order-management-view');
    const url = new URL(window.location.href);
    url.searchParams.delete('business_id');
    window.history.pushState({}, '', url.toString());
    sessionStorage.removeItem('selected_business_id');
    sessionStorage.removeItem('selected_business_name');
    window.currentBusinessId = null;
};
<?php endif; ?>
</script>
