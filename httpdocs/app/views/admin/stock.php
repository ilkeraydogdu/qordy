<?php
/**
 * Stock Management View - Dynamic Real-time Stock Management
 * All data is loaded dynamically via JavaScript/API
 */

$locations = $locations ?? [];
$movementTypes = $movementTypes ?? [];
$stockUnits = $stockUnits ?? [];
// Ensure BASE_URL is defined with fallback
$baseUrl = defined('BASE_URL') && !empty(BASE_URL) ? BASE_URL : '';
if (empty($baseUrl)) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $baseUrl = $protocol . '://' . $host;
}
$baseUrl = $baseUrl ?: 'http://localhost';

require_once __DIR__ . '/../../helpers/translations.php';
require_once __DIR__ . '/../../helpers/toast.php';
require_once __DIR__ . '/../../helpers/ui.php';
if (!function_exists('getAdminUrl')) {
    require_once __DIR__ . '/../../helpers/url_helper.php';
}
$isSuperAdmin = $is_super_admin ?? false;
$apiPrefix = $isSuperAdmin ? '/api/qodmin' : '/api/business';
$stockCategoriesPath = $isSuperAdmin ? '/qodmin/stock-categories' : '/business/stock-categories';
?>

<?php echo getToastScript(); ?>

<div class="q-page q-biz-theme animate-slide-up">
<div class="q-container q-stack q-stack--lg" id="stock-admin-root">
    <?php if ($is_super_admin ?? false): ?>
    <!-- SUPER ADMIN VIEW: Business Selection First -->
    <div id="business-selection-view">
        <header class="q-page-header">
            <div>
                <p class="q-page-header__eyebrow">Stok</p>
                <h1 class="q-page-header__title">İşletme Seçin</h1>
                <p class="q-page-header__subtitle">Stoklarını görüntülemek istediğiniz işletmeyi seçin</p>
            </div>
            <div class="q-page-header__actions">
                <div class="relative" style="min-width:16rem;">
                    <input type="text"
                           id="business-search"
                           placeholder="İşletme ara..."
                           onkeyup="BusinessSelector.searchBusinesses(this.value)"
                           class="q-input" style="padding-left:2.5rem;">
                    <svg class="absolute left-3 top-1/2 transform -translate-y-1/2 w-4 h-4" style="color:var(--color-text-muted);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                </div>
            </div>
        </header>
        
        <div id="business-grid" class="q-grid q-grid--4">
            <div class="col-span-full text-center py-12">
                <div class="q-spinner q-spinner--lg mx-auto"></div>
                <p class="mt-4 q-hint font-bold">İşletmeler yükleniyor...</p>
            </div>
        </div>
    </div>
    
    <!-- Stock Management View (shown after business selection) -->
    <div id="stock-management-view" class="hidden">
    <?php endif; ?>
    
    <!-- Loading Indicator -->
    <div id="stock-loading" class="hidden q-loading-toast" style="position:fixed;top:var(--space-4);right:var(--space-4);z-index:50;">
        <div class="q-spinner"></div>
        <span class="text-xs font-bold">Güncelleniyor...</span>
    </div>

    <header class="q-page-header">
        <div>
            <p class="q-page-header__eyebrow">Stok</p>
            <?php if ($is_super_admin ?? false): ?>
            <h1 class="q-page-header__title"><span id="selected-business-name"></span></h1>
            <p class="q-page-header__subtitle">Stok Yönetimi</p>
            <?php else: ?>
            <h1 class="q-page-header__title">Stok Yönetimi</h1>
            <p class="q-page-header__subtitle">Malzeme ve ürün stoklarını yönetin</p>
            <?php endif; ?>
        </div>
        <div class="q-page-header__actions">
            <div class="q-tab-row q-tab-row--card" role="tablist" aria-label="Stok görünümleri">
                <button type="button" role="tab" onclick="setStockTab('STOCK')" id="stock-tab-btn" class="q-tab selected" aria-selected="true">Stok Listesi</button>
                <button type="button" role="tab" onclick="setStockTab('LOW')" id="low-tab-btn" class="q-tab" aria-selected="false">
                    Düşük Stok
                    <span id="low-stock-badge" class="hidden q-badge q-badge--danger" style="margin-left:4px;">0</span>
                </button>
                <button type="button" role="tab" onclick="setStockTab('MOVEMENTS')" id="movements-tab-btn" class="q-tab" aria-selected="false">Hareket Geçmişi</button>
            </div>
        </div>
    </header>
    <div id="stock-summary" class="q-toolbar" style="flex-wrap:wrap;margin-bottom:var(--space-4);">
            <!-- Summary will be loaded dynamically -->
    </div>

    <!-- Stock List Tab -->
    <div id="stock-tab" class="stock-tab-content">
        <div class="mb-4 px-0.5 sm:px-1">
            <div class="q-card q-card--pad">
                <div class="q-toolbar" style="flex-wrap:wrap;margin-bottom:var(--space-3);">
                    <div>
                        <h3 class="q-card__title" style="margin:0;font-size:var(--font-size-sm);text-transform:uppercase;letter-spacing:0.12em;">Stok kategorisi</h3>
                        <p class="q-hint">Stok kategorileri sayfasındaki ağaç ile aynı veri — üst kategori altındaki tüm ürünleri de kapsar.</p>
                    </div>
                    <a href="<?php echo htmlspecialchars($stockCategoriesPath, ENT_QUOTES, 'UTF-8'); ?>"
                       class="q-btn q-btn--soft q-btn--sm">
                        <svg class="w-4 h-4 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"/>
                        </svg>
                        Kategorileri düzenle
                    </a>
                </div>
                <div class="flex items-stretch gap-2 min-w-0">
                    <button type="button" data-stock-cat="ALL"
                            class="stock-cat-filter is-all active q-btn q-btn--soft q-btn--sm shrink-0">Tümü</button>
                    <div class="min-w-0 flex-1 overflow-hidden q-card q-card--pad" style="padding:var(--space-2) var(--space-3);background:var(--color-surface-muted);">
                        <div id="stock-category-filter-chips"
                             class="flex flex-nowrap items-center gap-2 overflow-x-auto scroll-smooth [scrollbar-width:thin] [scrollbar-color:rgb(203_213_225)_transparent]">
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="q-card" style="padding:0;overflow:hidden;">
            <div id="stock-list-container" class="overflow-x-auto">
                <div class="p-8 text-center">
                    <div class="q-spinner mx-auto mb-4"></div>
                    <p class="q-hint">Yükleniyor...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Low Stock Tab -->
    <div id="low-tab" class="stock-tab-content hidden">
        <div class="q-card q-card--pad">
            <div class="q-toolbar" style="flex-wrap:wrap;margin-bottom:var(--space-4);">
                <div>
                    <h3 class="q-card__title" style="margin:0;">Düşük Stok Uyarıları</h3>
                    <p class="q-hint">Stoku biten veya eşiğin altına düşen ürünler. Eşiği doğrudan bu panelden güncelleyebilirsiniz.</p>
                </div>
                <div class="q-toolbar">
                    <label class="q-label" style="margin:0;">Varsayılan eşik</label>
                    <input type="number" id="low-stock-default-threshold" value="10" min="0"
                           class="q-input" style="width:5rem;"/>
                    <button type="button" onclick="loadLowStock()" class="q-btn q-btn--primary q-btn--sm">Yenile</button>
                </div>
            </div>
            <div id="low-stock-list" class="q-stack q-stack--sm">
                <div class="p-8 text-center">
                    <div class="q-spinner mx-auto mb-4"></div>
                    <p class="q-hint font-bold">Yükleniyor...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Movements Tab -->
    <div id="movements-tab" class="stock-tab-content hidden">
        <div class="q-card q-card--pad">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 sm:gap-6">
                <!-- Tree Structure: Categories and Products -->
                <div class="q-card q-card--pad" style="background:var(--color-surface-muted);overflow-y:auto;max-height:70vh;">
                    <div class="q-field" style="margin-bottom:var(--space-3);">
                        <input type="text" id="product-search-tree" placeholder="Ürün ara..." class="q-input"/>
                    </div>
                    <div id="product-tree" class="q-stack q-stack--xs">
                        <div class="text-center py-8">
                            <div class="q-spinner mx-auto mb-2"></div>
                            <p class="q-hint font-bold">Yükleniyor...</p>
                        </div>
                    </div>
                </div>
                
                <!-- Product Stock History Details -->
                <div class="lg:col-span-2 space-y-4">
                    <div id="stock-history-placeholder" class="text-center py-12 q-card q-card--pad" style="background:var(--color-surface-muted);">
                        <svg class="w-16 h-16 mx-auto mb-4" style="color:var(--color-text-muted);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        <p class="q-hint font-bold">Stok hareket geçmişini görmek için bir ürün seçin</p>
                    </div>
                    
                    <div id="stock-history-details" class="hidden space-y-4">
                        <!-- Selected Product Info -->
                        <div class="q-callout q-card--pad mb-0">
                            <h4 id="selected-product-name" class="q-card__title mb-2"></h4>
                            <div class="flex flex-wrap gap-4 text-sm">
                                <span id="selected-product-category" class="q-hint font-bold"></span>
                                <span id="selected-product-stock" class="q-hint font-bold"></span>
                            </div>
                        </div>
                        
                        <!-- Date Selector -->
                        <div class="flex items-center gap-3">
                            <label class="q-label whitespace-nowrap mb-0">Tarih:</label>
                            <input type="date" id="stock-history-date" value="<?php echo date('Y-m-d'); ?>" class="q-input" style="width:auto;"/>
                        </div>
                        
                        <!-- Statistics -->
                        <div id="stock-history-stats" class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                            <!-- Stats will be loaded here -->
                        </div>
                        
                        <!-- Tables List -->
                        <div class="q-card q-card--pad">
                            <h5 class="q-section-title mb-3">Satın Alan Masalar</h5>
                            <div id="stock-history-tables" class="space-y-2">
                                <!-- Tables will be loaded here -->
                            </div>
                        </div>
                        
                        <!-- Hourly Sales Chart -->
                        <div class="q-card q-card--pad">
                            <h5 class="q-section-title mb-3">Saatlik Satış Dağılımı</h5>
                            <div id="stock-history-hourly-chart" class="space-y-2">
                                <!-- Chart will be loaded here -->
                            </div>
                        </div>
                        
                        <!-- Order Items List -->
                        <div class="q-card q-card--pad">
                            <h5 class="q-section-title mb-3">Detaylı Hareket Geçmişi</h5>
                            <div id="stock-history-order-items" class="space-y-2 max-h-96 overflow-y-auto">
                                <!-- Order items will be loaded here -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php if ($is_super_admin ?? false): ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<script>
const baseUrl = <?php echo json_encode($baseUrl, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
const movementTypes = <?php echo json_encode($movementTypes ?? [], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

<?php if ($is_super_admin ?? false): ?>
// Super Admin: Load BusinessSelector
const bsScript = document.createElement('script');
bsScript.src = '<?php echo htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8'); ?>/assets/js/business-selector.js?v=<?php echo time(); ?>';
bsScript.onload = function() {
    if (typeof BusinessSelector === 'undefined') return;
    
    BusinessSelector.init({ baseUrl: <?php echo json_encode($baseUrl, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?> });
    
    // Check if business_id is in URL (page reload scenario)
    const urlParams = new URLSearchParams(window.location.search);
    const businessIdFromUrl = urlParams.get('business_id');
    
    if (businessIdFromUrl) {
        // Business ID in URL - load business info directly from API and show stock view
        const apiPrefix = <?php echo json_encode($apiPrefix); ?>;
        fetch(`${BusinessSelector.config.baseUrl}${apiPrefix}/businesses`)
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
                    
                    // Load stock for this business
                    loadBusinessStock(businessIdFromUrl, businessName);
                } else {
                    console.error('Business not found:', businessIdFromUrl);
                }
            })
            .catch(error => {
                console.error('Error loading business info:', error);
            });
    } else {
        // No business_id in URL - show business selection
        BusinessSelector.loadBusinesses().then(businesses => {
            BusinessSelector.renderBusinessGrid('business-grid', function(businessId, businessName) {
                // Set business ID in session storage
                sessionStorage.setItem('selected_business_id', businessId);
                sessionStorage.setItem('selected_business_name', businessName);
                window.currentBusinessId = businessId;
                
                // Update URL without page reload
                const url = new URL(window.location.href);
                url.searchParams.set('business_id', businessId);
                window.history.pushState({ businessId, businessName }, '', url.toString());
                
                // Load stock for this business
                loadBusinessStock(businessId, businessName);
            });
        });
    }
};
document.head.appendChild(bsScript);

window.backToBusinessSelection = function() {
    BusinessSelector.showSelectionView('business-selection-view', 'stock-management-view');
    
    // Remove business_id from URL
    const url = new URL(window.location.href);
    url.searchParams.delete('business_id');
    window.history.pushState({}, '', url.toString());
    
    // Clear session storage
    sessionStorage.removeItem('selected_business_id');
    sessionStorage.removeItem('selected_business_name');
    window.currentBusinessId = null;
};

function loadBusinessStock(businessId, businessName) {
    window.currentBusinessId = businessId;
    stockCategoryRows = [];
    stockCategoryCacheKey = '';
    
    // Update business name display
    const businessNameElement = document.getElementById('selected-business-name');
    if (businessNameElement) {
        businessNameElement.textContent = businessName;
    }
    
    // Show the stock management view
    BusinessSelector.showContentView('business-selection-view', 'stock-management-view', businessName);
    
    // Load stock data
    if (typeof loadStockList === 'function') {
        loadStockList();
    }
    if (typeof loadMovements === 'function') {
        loadMovements();
    }
}
<?php endif; ?>
</script>

<script>
const apiPrefix = <?php echo json_encode($apiPrefix, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
let currentTab = 'STOCK';
let stockList = [];
let pollingInterval = null;
let isPolling = false;

// Tab switching
function setStockTab(tab) {
    currentTab = tab;
    
    // Hide all tabs
    document.querySelectorAll('.stock-tab-content').forEach(el => el.classList.add('hidden'));
    document.querySelectorAll('[id$="-tab-btn"]').forEach(el => {
        el.classList.remove('selected');
        el.setAttribute('aria-selected', 'false');
    });

    // Show selected tab
    const tabMap = {
        'STOCK':     { content: 'stock-tab',     btn: 'stock-tab-btn' },
        'LOW':       { content: 'low-tab',       btn: 'low-tab-btn' },
        'MOVEMENTS': { content: 'movements-tab', btn: 'movements-tab-btn' }
    };

    if (tabMap[tab]) {
        document.getElementById(tabMap[tab].content).classList.remove('hidden');
        const btn = document.getElementById(tabMap[tab].btn);
        btn.classList.add('selected');
        btn.setAttribute('aria-selected', 'true');
    }

    if (tab === 'STOCK') {
        loadStockList();
    } else if (tab === 'MOVEMENTS') {
        loadMovements();
    } else if (tab === 'LOW') {
        loadLowStock();
    }
}

// Low-stock panel --------------------------------------------------------
async function loadLowStock() {
    const container = document.getElementById('low-stock-list');
    const badge     = document.getElementById('low-stock-badge');
    if (!container) return;

    const threshold = Math.max(0, parseInt(
        document.getElementById('low-stock-default-threshold')?.value ?? '10', 10
    ) || 0);

    container.innerHTML = `
        <div class="p-8 text-center text-slate-400">
            <div class="w-8 h-8 border-2 border-slate-300 border-t-transparent rounded-full animate-spin mx-auto mb-4"></div>
            <p class="text-sm font-bold">Yükleniyor...</p>
        </div>`;

    try {
        const url = withBusinessId(`${baseUrl}${apiPrefix}/stock/low?threshold=${threshold}`);
        const res = await fetch(url, {
            method: 'GET',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin'
        });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const data = await res.json();
        if (!data.success) throw new Error(data.error || 'Yüklenemedi');

        const items = Array.isArray(data.items) ? data.items : [];
        const total = data.counts?.total ?? items.length;

        if (badge) {
            if (total > 0) {
                badge.textContent = String(total);
                badge.classList.remove('hidden');
            } else {
                badge.classList.add('hidden');
            }
        }

        if (items.length === 0) {
            container.innerHTML = `
                <div class="q-card q-card--pad text-center" style="border:2px dashed var(--color-status-success); background:var(--color-status-success-bg);">
                    <div class="w-12 h-12 mx-auto mb-3 rounded-full flex items-center justify-center font-black text-xl text-white" style="background:var(--color-status-success);">✓</div>
                    <p class="font-black text-sm" style="color:var(--color-status-success);">Tüm stoklar yeterli seviyede.</p>
                </div>`;
            return;
        }

        container.innerHTML = items.map(it => {
            const isOut = (it.severity ?? '') === 'out';
            const type  = it.item_type === 'MENU_ITEM' ? 'Menü Ürünü' : 'Malzeme';
            const unit  = it.unit ? ` ${escapeHtmlSafe(String(it.unit))}` : '';
            const stock = Number(it.current_stock ?? 0);
            const thr   = Number(it.min_threshold ?? 0);
            const cat   = it.category ? ` · ${escapeHtmlSafe(String(it.category))}` : '';
            const cardBorder = isOut ? 'var(--color-status-danger)' : 'var(--color-status-warning)';
            const badgeClass = isOut ? 'q-badge--danger' : 'q-badge--warning';
            const stockColor = isOut ? 'var(--color-status-danger)' : 'var(--color-status-warning)';
            return `
                <div class="q-card q-card--pad flex flex-col sm:flex-row sm:items-center gap-3" style="border:2px solid ${cardBorder};">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="q-badge ${badgeClass}">
                                ${isOut ? 'Bitti' : 'Azaldı'}
                            </span>
                            <span class="q-hint text-[10px] font-bold uppercase">${type}${cat}</span>
                        </div>
                        <h4 class="font-black truncate" style="color:var(--color-text-primary);">${escapeHtmlSafe(String(it.name || ''))}</h4>
                        <p class="text-xs font-bold q-hint mt-1">
                            Mevcut: <span class="font-black" style="color:${stockColor};">${stock}${unit}</span>
                            · Eşik: <span class="font-black">${thr}${unit}</span>
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <input type="number" min="0" value="${thr}"
                               id="thr-${escapeHtmlSafe(String(it.item_id))}"
                               class="q-input" style="width:6rem;"/>
                        <button type="button" onclick="saveLowStockThreshold('${escapeHtmlSafe(String(it.item_type))}','${escapeHtmlSafe(String(it.item_id))}')"
                                class="q-btn q-btn--ink q-btn--sm whitespace-nowrap">
                            Eşiği Kaydet
                        </button>
                    </div>
                </div>`;
        }).join('');
    } catch (err) {
        console.error('loadLowStock error', err);
        container.innerHTML = `
            <div class="p-6 bg-red-50 border border-red-200 rounded-xl text-red-700 font-bold text-sm">
                Düşük stok verileri yüklenirken bir hata oluştu: ${escapeHtmlSafe(err.message || '')}
            </div>`;
    }
}

async function saveLowStockThreshold(itemType, itemId) {
    const input = document.getElementById(`thr-${itemId}`);
    if (!input) return;
    const value = Math.max(0, parseInt(input.value, 10) || 0);

    try {
        const form = new FormData();
        form.set('item_type', itemType);
        form.set('item_id',   itemId);
        form.set('threshold', String(value));
        const urlParams = new URLSearchParams(window.location.search);
        const bid = window.currentBusinessId
                 || urlParams.get('business_id')
                 || sessionStorage.getItem('selected_business_id');
        if (bid) form.set('business_id', bid);

        const res = await fetch(`${baseUrl}${apiPrefix}/stock/threshold`, {
            method: 'POST',
            body: form,
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok || !data.success) {
            throw new Error(data.error || `HTTP ${res.status}`);
        }
        if (typeof showToast === 'function') {
            showToast('Eşik güncellendi', 'success');
        }
        loadLowStock();
    } catch (err) {
        console.error('saveLowStockThreshold error', err);
        if (typeof showToast === 'function') {
            showToast('Eşik güncellenemedi: ' + (err.message || ''), 'error');
        }
    }
}

function escapeHtmlSafe(s) {
    const div = document.createElement('div');
    div.textContent = String(s ?? '');
    return div.innerHTML;
}

// Show loading indicator
function showLoading() {
    const loading = document.getElementById('stock-loading');
    if (loading) loading.classList.remove('hidden');
}

// Hide loading indicator
function hideLoading() {
    const loading = document.getElementById('stock-loading');
    if (loading) loading.classList.add('hidden');
}

// Append the super-admin-selected business_id to a URL when present,
// so the StockController's applyTenantContext() resolves the right tenant.
function withBusinessId(url) {
    try {
        const urlParams = new URLSearchParams(window.location.search);
        const bid = window.currentBusinessId
            || urlParams.get('business_id')
            || sessionStorage.getItem('selected_business_id');
        if (!bid) return url;
        const sep = url.includes('?') ? '&' : '?';
        return `${url}${sep}business_id=${encodeURIComponent(bid)}`;
    } catch (e) {
        return url;
    }
}

// Load stock list from API
async function loadStockList() {
    try {
        showLoading();
        await ensureStockCategoryFiltersLoaded();
        const response = await fetch(withBusinessId(`${baseUrl}${apiPrefix}/stock/list`), {
            method: 'GET',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin'
        });
        
        if (!response.ok) {
            // Try to get error message from response
            let errorMessage = `HTTP error! status: ${response.status}`;
            try {
                const errorData = await response.json();
                if (errorData.error) {
                    errorMessage = errorData.error;
                }
            } catch (e) {
                // Ignore JSON parse errors
            }
            throw new Error(errorMessage);
        }
        
        const data = await response.json();
        
        if (data.success && data.data) {
            stockList = data.data || [];
            updateStockDisplay();
            // Only update summary if it's not already being updated
            if (typeof updateStockSummary === 'function') {
                updateStockSummary().catch(err => {
                    console.error('Error updating stock summary:', err);
                });
            }
            populateIngredientSelects();
        } else {
            console.error('Failed to load stock list:', data.error);
            stockList = [];
            updateStockDisplay();
            if (typeof showToast === 'function') {
                showToast(data.error || 'Stok listesi yüklenemedi', 'error');
            }
        }
    } catch (error) {
        console.error('Error loading stock list:', error);
        stockList = [];
        updateStockDisplay();
        if (typeof showToast === 'function') {
            showToast('Stok listesi yüklenirken hata oluştu. Lütfen sayfayı yenileyin.', 'error');
        }
    } finally {
        hideLoading();
    }
}

// Stok kategorisi filtresi: /stock-categories ile aynı kaynak (stock_categories tablosu)
let stockCategoryFilter = 'ALL';
let stockCategoryRows = [];
let stockCategoryCacheKey = '';

const STOCK_SUB_TYPE_LABELS = {
    INGREDIENT: 'Malzeme',
    RAW_MATERIAL: 'Hammadde',
    KITCHEN_SUPPLY: 'Mutfak Sarfı',
    CLEANING: 'Temizlik',
    MENU_ITEM: 'Menü Ürünü',
    OTHER: 'Diğer'
};

function stockCategoryBusinessKey() {
    return window.currentBusinessId
        || new URLSearchParams(window.location.search).get('business_id')
        || sessionStorage.getItem('selected_business_id')
        || '_tenant';
}

async function ensureStockCategoryFiltersLoaded() {
    const key = stockCategoryBusinessKey();
    if (stockCategoryCacheKey === key) {
        return;
    }
    try {
        const res = await fetch(withBusinessId(`${baseUrl}${apiPrefix}/stock-categories?include_inactive=0`), {
            method: 'GET',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin'
        });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const j = await res.json();
        stockCategoryRows = (j.success && Array.isArray(j.data)) ? j.data : [];
        stockCategoryCacheKey = key;
    } catch (e) {
        console.error('Stock category filter load failed', e);
        stockCategoryRows = [];
        stockCategoryCacheKey = '';
    }
    renderStockCategoryFilterButtons();
}

function renderStockCategoryFilterButtons() {
    const wrap = document.getElementById('stock-category-filter-chips');
    if (!wrap) return;
    if (!stockCategoryRows.length) {
        wrap.innerHTML = `
            <div class="flex flex-wrap items-center justify-center sm:justify-start gap-x-2 gap-y-1 text-xs text-slate-500 font-medium w-full min-h-[2.25rem]">
                <span>Henüz stok kategorisi yok.</span>
                <a href="<?php echo htmlspecialchars($stockCategoriesPath, ENT_QUOTES, 'UTF-8'); ?>"
                   class="inline-flex items-center font-black text-indigo-600 hover:text-indigo-700 hover:underline underline-offset-2">Kategori oluştur</a>
            </div>`;
        return;
    }
    wrap.innerHTML = stockCategoryRows.map(row => {
        const id = String(row.category_id || '').replace(/"/g, '&quot;');
        const depth = Math.min(8, parseInt(row.depth, 10) || 0);
        const rawName = String(row.name || id);
        const name = escapeHtml(rawName);
        const iconRaw = (row.icon && String(row.icon).trim()) ? String(row.icon).trim() : '';
        const iconHtml = iconRaw
            ? `<span class="text-[1.15rem] leading-none shrink-0" aria-hidden="true">${escapeHtml(iconRaw)}</span>`
            : '';
        const treeHint = depth > 0
            ? `<span class="shrink-0 w-3 flex justify-end text-slate-300" aria-hidden="true">${'·'.repeat(Math.min(depth, 3))}</span>`
            : '<span class="w-0 shrink-0" aria-hidden="true"></span>';
        const safeTitle = rawName.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
        return `<button type="button" data-stock-cat="${id}" title="${safeTitle}"
            class="stock-cat-filter max-w-[min(100%,14rem)] sm:max-w-[16rem]">
            <span class="inline-flex items-center gap-1.5 min-w-0 w-full">
                ${treeHint}
                ${iconHtml}
                <span class="truncate text-left font-bold leading-tight">${name}</span>
            </span>
        </button>`;
    }).join('');
    highlightStockCategoryFilter();
}

function highlightStockCategoryFilter() {
    document.querySelectorAll('.stock-cat-filter').forEach(btn => {
        const v = btn.getAttribute('data-stock-cat') || 'ALL';
        const active = v === stockCategoryFilter;
        const isAll = v === 'ALL';
        btn.classList.toggle('active', active);
        btn.classList.toggle('is-all', isAll);
    });
}

function setStockCategoryFilter(filter) {
    stockCategoryFilter = filter || 'ALL';
    highlightStockCategoryFilter();
    updateStockDisplay();
}

function itemMatchesStockCategoryFilter(item) {
    if (stockCategoryFilter === 'ALL') {
        return true;
    }
    const sel = stockCategoryRows.find(c => String(c.category_id) === String(stockCategoryFilter));
    const itemCid = item.category_id ? String(item.category_id) : '';
    if (!itemCid) {
        return false;
    }
    if (!sel) {
        return itemCid === String(stockCategoryFilter);
    }
    const selPath = String(sel.path || sel.category_id || '');
    const row = stockCategoryRows.find(c => String(c.category_id) === itemCid);
    if (!row) {
        return itemCid === String(sel.category_id);
    }
    const p = String(row.path || itemCid);
    if (p === selPath) {
        return true;
    }
    return selPath !== '' && p.startsWith(selPath + '/');
}

document.addEventListener('click', (ev) => {
    const btn = ev.target.closest('.stock-cat-filter');
    if (!btn) return;
    setStockCategoryFilter(btn.getAttribute('data-stock-cat') || 'ALL');
});

// Update stock display
function updateStockDisplay() {
    const container = document.getElementById('stock-list-container');
    if (!container) return;
    
    if (stockList.length === 0) {
        container.innerHTML = '<div class="p-8 text-center text-slate-400">Stok bulunamadı</div>';
        return;
    }

    // Apply stock_categories filter (same tree as /business/stock-categories).
    const filtered = stockList.filter(it => itemMatchesStockCategoryFilter(it));

    if (filtered.length === 0) {
        container.innerHTML = `
            <div class="p-10 sm:p-12 text-center">
                <p class="text-slate-500 font-bold text-sm mb-2">Bu kategoride stok satırı yok</p>
                <p class="text-xs text-slate-400">Ürünleri bu stok kategorisine atamak için stok kategorileri ekranından hammadde / menü öğesini düzenleyin.</p>
            </div>`;
        return;
    }

    const th = 'p-3 sm:p-4 lg:p-5 whitespace-nowrap';
    const td = 'p-3 sm:p-4 lg:p-5 align-middle';

    let html = `
        <table class="w-full text-left min-w-[720px]">
            <thead class="bg-slate-50/80 border-b border-slate-100 text-[9px] sm:text-[10px] font-black text-slate-500 uppercase tracking-wider">
                <tr>
                    <th class="${th}">Malzeme / Ürün</th>
                    <th class="${th}">Tip</th>
                    <th class="${th}">Stok kategorisi</th>
                    <th class="${th}">Birim</th>
                    <th class="${th} text-right">Mevcut</th>
                    <th class="${th} text-right">Min. eşik</th>
                    <th class="${th}">Durum</th>
                    <th class="${th}">Son hareket</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
    `;
    
    filtered.forEach(item => {
        const currentStock = parseFloat(item.current_stock || 0);
        const minThreshold = parseFloat(item.min_threshold || 0);
        const statusClass = item.status_class || 'text-green-500';
        const statusText = item.status_label || 'Normal';
        const lastMovement = item.last_movement_date ? new Date(item.last_movement_date).toLocaleDateString('tr-TR') : '-';
        const subType = item.sub_type || (item.item_type === 'MENU_ITEM' ? 'MENU_ITEM' : 'INGREDIENT');
        const subTypeLabel = STOCK_SUB_TYPE_LABELS[subType] || subType;
        const hasCat = !!(item.category && String(item.category).trim());
        const categoryHtml = hasCat
            ? `<span class="inline-flex items-center max-w-[11rem] truncate rounded-lg bg-indigo-50 text-indigo-900 border border-indigo-100/80 px-2 py-0.5 text-[11px] font-bold">${escapeHtml(String(item.category).trim())}</span>`
            : `<span class="text-slate-300 text-xs font-bold">—</span>`;
        const tipBadge = subType === 'MENU_ITEM'
            ? 'bg-amber-50 text-amber-800 border-amber-100'
            : 'bg-slate-100 text-slate-700 border-slate-200/80';

        html += `
            <tr class="hover:bg-indigo-50/20 transition-colors">
                <td class="${td} text-sm font-bold text-slate-900 max-w-[12rem] sm:max-w-none"><span class="line-clamp-2">${escapeHtml(item.name || '')}</span></td>
                <td class="${td}"><span class="inline-flex rounded-md border px-1.5 py-0.5 text-[10px] font-black uppercase tracking-tight ${tipBadge}">${escapeHtml(subTypeLabel)}</span></td>
                <td class="${td}">${categoryHtml}</td>
                <td class="${td} text-sm text-slate-600 font-semibold">${escapeHtml(item.unit || 'ADET')}</td>
                <td class="${td} text-right text-sm font-black text-slate-900 tabular-nums">${formatNumber(currentStock)}</td>
                <td class="${td} text-right text-sm text-slate-600 font-semibold tabular-nums">${formatNumber(minThreshold)}</td>
                <td class="${td} text-xs font-extrabold ${statusClass}">${escapeHtml(statusText)}</td>
                <td class="${td} text-xs text-slate-500 font-medium tabular-nums">${lastMovement}</td>
            </tr>
        `;
    });
    
    html += `
            </tbody>
        </table>
    `;
    
    container.innerHTML = html;
}

// Update stock summary
async function updateStockSummary() {
    try {
        const response = await fetch(withBusinessId(`${baseUrl}${apiPrefix}/stock/summary`), {
            method: 'GET',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin'
        });
        
        if (!response.ok) {
            // Try to get error message from response
            let errorMessage = `HTTP error! status: ${response.status}`;
            try {
                const contentType = response.headers.get('content-type');
                if (contentType && contentType.includes('application/json')) {
                    const errorData = await response.json();
                    if (errorData.error) {
                        errorMessage = errorData.error;
                    }
                } else {
                    // Response is not JSON, might be HTML error page
                    const text = await response.text();
                    errorMessage = `Server returned non-JSON response (status: ${response.status})`;
                }
            } catch (e) {
                // Ignore JSON parse errors
                errorMessage = `Failed to parse error response (status: ${response.status})`;
            }
            throw new Error(errorMessage);
        }
        
        // Check if response is JSON before parsing
        const contentType = response.headers.get('content-type');
        
        // Clone response to read text without consuming the body
        const responseClone = response.clone();
        const responseText = await responseClone.text();
        
        if (!contentType || !contentType.includes('application/json')) {
            throw new Error(`Server returned non-JSON response (${response.status}): ${responseText.substring(0, 200)}`);
        }
        
        // Check if response is actually JSON (not boolean or empty)
        if (!responseText.trim() || responseText.trim() === 'true' || responseText.trim() === 'false') {
            throw new Error(`Server returned boolean/empty instead of JSON (${response.status}): ${responseText}`);
        }
        
        let data;
        try {
            data = JSON.parse(responseText);
        } catch (parseError) {
            throw new Error(`Failed to parse JSON response (${response.status}): ${responseText.substring(0, 200)}`);
        }
        
        if (data.success && data.summary) {
            const summary = data.summary;
            const container = document.getElementById('stock-summary');
            
            if (container) {
                container.innerHTML = `
                    <div class="bg-white px-3 py-2 rounded-lg border border-slate-200 text-xs">
                        <div class="font-black text-slate-900">Toplam: ${summary.total_items || 0}</div>
                        <div class="text-green-500">Normal: ${summary.normal_stock || 0}</div>
                        <div class="text-indigo-600">Düşük: ${summary.low_stock || 0}</div>
                        <div class="text-red-500">Tükendi: ${summary.out_of_stock || 0}</div>
                    </div>
                `;
            }
            
            // Show alerts if any
            if (data.alerts && data.alerts.length > 0) {
                data.alerts.forEach(alert => {
                    showToast(alert.alert_message, alert.alert_type === 'OUT_OF_STOCK' ? 'error' : 'warning');
                });
            }
        } else {
            // Handle case where success is false but no error thrown
            const container = document.getElementById('stock-summary');
            if (container) {
                container.innerHTML = `
                    <div class="bg-white px-3 py-2 rounded-lg border border-red-200 text-xs text-red-500">
                        <div>Stok özeti yüklenemedi</div>
                    </div>
                `;
            }
            
            if (data.error) {
                showToast(data.error, 'error');
            }
        }
    } catch (error) {
        console.error('Error loading stock summary:', error);
        
        // Show user-friendly error message
        const container = document.getElementById('stock-summary');
        if (container) {
            container.innerHTML = `
                <div class="bg-white px-3 py-2 rounded-lg border border-red-200 text-xs text-red-500">
                    <div>Stok özeti yüklenirken hata oluştu</div>
                </div>
            `;
        }
        
        // Only show toast if error handler is available
        if (typeof showToast === 'function') {
            showToast('Stok özeti yüklenirken hata oluştu. Lütfen sayfayı yenileyin.', 'error');
        }
    }
}

// Load movements from API
async function loadMovements(limit = 100) {
    try {
        showLoading();
        const response = await fetch(withBusinessId(`${baseUrl}${apiPrefix}/stock/movements?limit=${limit}`), {
            method: 'GET',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin'
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        
        if (data.success && data.data) {
            updateMovementsDisplay(data.data);
        } else {
            console.error('Failed to load movements:', data.error);
            showToast('Hareket geçmişi yüklenemedi', 'error');
        }
    } catch (error) {
        console.error('Error loading movements:', error);
        showToast('Hareket geçmişi yüklenirken hata oluştu', 'error');
    } finally {
        hideLoading();
    }
}

// Update movements display
function updateMovementsDisplay(movements) {
    const container = document.getElementById('movements-container');
    if (!container) return;
    
    if (movements.length === 0) {
        container.innerHTML = '<div class="p-8 text-center text-slate-400">Hareket kaydı bulunamadı</div>';
        return;
    }
    
    let html = `
        <table class="w-full text-left">
            <thead class="bg-slate-50/50 border-b text-[8px] sm:text-[9px] lg:text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]">
                <tr>
                    <th class="p-4 sm:p-6 lg:p-10">Tarih</th>
                    <th class="p-4 sm:p-6 lg:p-10">Malzeme</th>
                    <th class="p-4 sm:p-6 lg:p-10">Hareket Tipi</th>
                    <th class="p-4 sm:p-6 lg:p-10">Miktar</th>
                    <th class="p-4 sm:p-6 lg:p-10">Birim</th>
                    <th class="p-4 sm:p-6 lg:p-10">İşlemi Yapan</th>
                    <th class="p-4 sm:p-6 lg:p-10">Notlar</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
    `;
    
    movements.forEach(movement => {
        const date = new Date(movement.created_at);
        const formattedDate = date.toLocaleDateString('tr-TR') + ' ' + date.toLocaleTimeString('tr-TR', { hour: '2-digit', minute: '2-digit' });
        const itemName = movement.item_name || movement.item_id || 'Bilinmiyor';
        const movementType = movement.movement_type || '';
        const typeLabel = movementTypes[movementType] || movementType;
        const quantity = parseFloat(movement.quantity || 0);
        const unit = movement.unit || 'ADET';
        const createdBy = movement.created_by_name || 'Sistem';
        const notes = movement.notes || '';
        
        html += `
            <tr class="hover:bg-slate-50">
                <td class="p-4 sm:p-6 lg:p-10 text-sm">${escapeHtml(formattedDate)}</td>
                <td class="p-4 sm:p-6 lg:p-10 text-sm font-semibold">${escapeHtml(itemName)}</td>
                <td class="p-4 sm:p-6 lg:p-10 text-sm">${escapeHtml(typeLabel)}</td>
                <td class="p-4 sm:p-6 lg:p-10 text-sm font-bold">${formatNumber(quantity)}</td>
                <td class="p-4 sm:p-6 lg:p-10 text-sm">${escapeHtml(unit)}</td>
                <td class="p-4 sm:p-6 lg:p-10 text-sm">${escapeHtml(createdBy)}</td>
                <td class="p-4 sm:p-6 lg:p-10 text-sm text-slate-500">${escapeHtml(notes)}</td>
            </tr>
        `;
    });
    
    html += `
            </tbody>
        </table>
    `;
    
    container.innerHTML = html;
}

// Populate ingredient selects in forms
function populateIngredientSelects() {
    const selects = ['add-stock-item', 'remove-stock-item', 'transfer-stock-item', 'adjust-stock-item'];
    
    selects.forEach(selectId => {
        const select = document.getElementById(selectId);
        if (!select) return;
        
        select.innerHTML = '<option value="">Seçiniz</option>';
        
        stockList.forEach(item => {
            const option = document.createElement('option');
            option.value = item.ingredient_id;
            option.textContent = item.name;
            select.appendChild(option);
        });
    });
}

// Setup polling for real-time updates
function setupPolling() {
    // Only poll if page is visible
    if (document.hidden) {
        return;
    }
    
    // Stop existing polling
    if (pollingInterval) {
        clearInterval(pollingInterval);
    }
    
    // Don't poll if user is interacting
    if (isPolling) {
        return;
    }
    
    // Poll every 10 seconds
    pollingInterval = setInterval(() => {
        if (document.hidden) {
            return;
        }
        
        if (currentTab === 'STOCK') {
            loadStockList();
        } else if (currentTab === 'MOVEMENTS') {
            loadMovements();
        }
    }, 10000);
}

// Stop polling when page is hidden
document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
        if (pollingInterval) {
            clearInterval(pollingInterval);
            pollingInterval = null;
        }
    } else {
        setupPolling();
    }
});

// Helper functions
// Use Utils.escapeHtml from utils.js (loaded globally)

function formatNumber(num) {
    return parseFloat(num).toFixed(2).replace(/\.?0+$/, '');
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Load initial data
    loadStockList();
    loadMovements();
    // Warm the low-stock badge without switching tabs so the count is visible up-front.
    loadLowStock();

    // Setup polling
    setupPolling();
    
    // Clean up on page unload
    window.addEventListener('beforeunload', function() {
        if (pollingInterval) {
            clearInterval(pollingInterval);
        }
    });
});

// Product Stock History Management
(function() {
    let selectedProductId = null;
    let categories = [];
    let menuItems = [];
    
    // Load menu items and categories when Movements tab is opened
    function loadMenuData() {
        const businessId = window.currentBusinessId || sessionStorage.getItem('selected_business_id');
        const urlParams = new URLSearchParams(window.location.search);
        const businessIdFromUrl = urlParams.get('business_id');
        const finalBusinessId = businessId || businessIdFromUrl;
        
        // Load categories - use /api/categories which now returns JSON for API requests
        const apiPrefix = <?php echo json_encode($apiPrefix); ?>;
        let categoriesUrl = `${baseUrl}/api/categories`;
        // Note: /api/categories now handles API requests and returns JSON
        // If businessId is needed, it will be handled by tenant context
        
        Promise.all([
            fetch(categoriesUrl, {
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin'
            }).then(async r => {
                if (!r.ok) {
                    const text = await r.text();
                    throw new Error(`Categories API error: ${r.status} - ${text.substring(0, 100)}`);
                }
                const contentType = r.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    const text = await r.text();
                    throw new Error(`Categories API returned non-JSON: ${text.substring(0, 100)}`);
                }
                return r.json();
            }),
            // Use /api/menu which handles tenant context automatically
            fetch(`${baseUrl}/api/menu`, {
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin'
            }).then(async r => {
                if (!r.ok) {
                    const text = await r.text();
                    throw new Error(`Menu API error: ${r.status} - ${text.substring(0, 100)}`);
                }
                const contentType = r.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    const text = await r.text();
                    throw new Error(`Menu API returned non-JSON: ${text.substring(0, 100)}`);
                }
                return r.json();
            })
        ]).then(([categoriesData, menuData]) => {
            categories = Array.isArray(categoriesData) ? categoriesData : (categoriesData.categories || categoriesData.data || []);
            menuItems = menuData.menu_items || menuData.items || (Array.isArray(menuData) ? menuData : []);
            
            if (typeof loadProductTree === 'function') {
                loadProductTree();
            }
        }).catch(error => {
            console.error('Error loading menu data:', error);
            const productTreeEl = document.getElementById('product-tree');
            if (productTreeEl) {
                productTreeEl.innerHTML = '<p class="text-sm text-red-500 text-center py-4">Menü verileri yüklenirken hata oluştu. Lütfen sayfayı yenileyin.</p>';
            }
            if (typeof showToast === 'function') {
                showToast('Menü verileri yüklenemedi', 'error');
            }
        });
    }
    
    // Load product tree structure
    function loadProductTree() {
        const treeContainer = document.getElementById('product-tree');
        if (!treeContainer) return;
        
        // Group menu items by category
        const itemsByCategory = {};
        menuItems.forEach(item => {
            const categoryId = item.category_id || 'uncategorized';
            if (!itemsByCategory[categoryId]) {
                itemsByCategory[categoryId] = [];
            }
            itemsByCategory[categoryId].push(item);
        });
        
        // Build tree HTML
        let treeHTML = '';
        
        categories.forEach(category => {
            const categoryItems = itemsByCategory[category.category_id] || [];
            // Only show products with stock tracking enabled
            const stockableItems = categoryItems.filter(item => item.track_stock == 1 || item.track_stock === true);
            if (stockableItems.length === 0) return;
            
            const categoryId = `cat-${category.category_id}`;
            treeHTML += `
                <div class="tree-category">
                    <button type="button" class="w-full flex items-center justify-between p-2 hover:bg-white rounded-lg transition-colors" 
                            onclick="toggleStockCategory('${categoryId}')">
                        <span class="text-sm font-black text-slate-700">${escapeHtml(category.name || 'Kategori')}</span>
                        <svg id="chevron-${categoryId}" class="w-4 h-4 text-slate-500 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </button>
                    <div id="${categoryId}" class="hidden pl-4 mt-1 space-y-1">
                        ${stockableItems.map(item => {
                            const itemId = item.menu_item_id || item.id;
                            const itemName = item.name || 'Ürün';
                            const stockQty = item.stock_quantity || 0;
                            return `
                                <button type="button" 
                                        class="w-full text-left p-2 hover:bg-white rounded-lg transition-colors text-slate-900"
                                        onclick="selectStockProduct(${itemId}, '${escapeHtml(itemName)}', '${escapeHtml(category.name)}', ${stockQty})">
                                    <div class="flex items-center justify-between">
                                        <span class="text-xs font-bold">${escapeHtml(itemName)}</span>
                                        <span class="text-xs text-slate-500">Stok: ${stockQty}</span>
                                    </div>
                                </button>
                            `;
                        }).join('')}
                    </div>
                </div>
            `;
        });
        
        // Add uncategorized items
        const uncategorizedItems = itemsByCategory['uncategorized'] || [];
        const stockableUncategorized = uncategorizedItems.filter(item => item.track_stock == 1 || item.track_stock === true);
        if (stockableUncategorized.length > 0) {
            treeHTML += `
                <div class="tree-category">
                    <button type="button" class="w-full flex items-center justify-between p-2 hover:bg-white rounded-lg transition-colors" 
                            onclick="toggleStockCategory('cat-uncategorized')">
                        <span class="text-sm font-black text-slate-700">Kategorisiz</span>
                        <svg id="chevron-cat-uncategorized" class="w-4 h-4 text-slate-500 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </button>
                    <div id="cat-uncategorized" class="hidden pl-4 mt-1 space-y-1">
                        ${stockableUncategorized.map(item => {
                            const itemId = item.menu_item_id || item.id;
                            const itemName = item.name || 'Ürün';
                            const stockQty = item.stock_quantity || 0;
                            return `
                                <button type="button" 
                                        class="w-full text-left p-2 hover:bg-white rounded-lg transition-colors text-slate-900"
                                        onclick="selectStockProduct(${itemId}, '${escapeHtml(itemName)}', 'Kategorisiz', ${stockQty})">
                                    <div class="flex items-center justify-between">
                                        <span class="text-xs font-bold">${escapeHtml(itemName)}</span>
                                        <span class="text-xs text-slate-500">Stok: ${stockQty}</span>
                                    </div>
                                </button>
                            `;
                        }).join('')}
                    </div>
                </div>
            `;
        }
        
        treeContainer.innerHTML = treeHTML || '<p class="text-sm text-slate-500 text-center py-4">Stok takibi yapılan ürün bulunamadı</p>';
    }
    
    // Toggle category expand/collapse
    window.toggleStockCategory = function(categoryId) {
        const categoryDiv = document.getElementById(categoryId);
        const chevron = document.getElementById(`chevron-${categoryId}`);
        if (categoryDiv && chevron) {
            const isHidden = categoryDiv.classList.contains('hidden');
            if (isHidden) {
                categoryDiv.classList.remove('hidden');
                chevron.style.transform = 'rotate(90deg)';
            } else {
                categoryDiv.classList.add('hidden');
                chevron.style.transform = 'rotate(0deg)';
            }
        }
    };
    
    // Select product and load stock history
    window.selectStockProduct = function(productId, productName, categoryName, stockQty) {
        selectedProductId = productId;
        
        // Update selected product info
        document.getElementById('selected-product-name').textContent = productName;
        document.getElementById('selected-product-category').textContent = `Kategori: ${categoryName}`;
        document.getElementById('selected-product-stock').textContent = `Mevcut Stok: ${stockQty}`;
        
        // Show details, hide placeholder
        document.getElementById('stock-history-placeholder').classList.add('hidden');
        document.getElementById('stock-history-details').classList.remove('hidden');
        
        // Load stock history
        loadProductStockHistory(productId);
    };
    
    // Load product stock history from API
    function loadProductStockHistory(productId) {
        const date = document.getElementById('stock-history-date').value;
        const businessId = window.currentBusinessId || sessionStorage.getItem('selected_business_id');
        const urlParams = new URLSearchParams(window.location.search);
        const businessIdFromUrl = urlParams.get('business_id');
        const finalBusinessId = businessId || businessIdFromUrl;
        
        const apiPrefix = <?php echo json_encode($apiPrefix); ?>;
        let apiUrl = `${baseUrl}${apiPrefix}/menu-items/${productId}/stock-history?date=${date}`;
        if (finalBusinessId) {
            apiUrl += `&business_id=${encodeURIComponent(finalBusinessId)}`;
        }
        
        // Show loading state
        document.getElementById('stock-history-stats').innerHTML = '<div class="col-span-full text-center py-4"><div class="inline-block animate-spin rounded-full h-6 w-6 border-t-2 border-b-2 border-indigo-500"></div></div>';
        document.getElementById('stock-history-tables').innerHTML = '<p class="text-sm text-slate-500 text-center py-4">Yükleniyor...</p>';
        document.getElementById('stock-history-order-items').innerHTML = '<p class="text-sm text-slate-500 text-center py-4">Yükleniyor...</p>';
        
        fetch(apiUrl)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayStockHistory(data);
                } else {
                    console.error('Failed to load stock history:', data);
                    document.getElementById('stock-history-stats').innerHTML = '<div class="col-span-full text-center py-4 text-red-500">Veri yüklenirken hata oluştu</div>';
                }
            })
            .catch(error => {
                console.error('Error loading stock history:', error);
                document.getElementById('stock-history-stats').innerHTML = '<div class="col-span-full text-center py-4 text-red-500">Veri yüklenirken hata oluştu</div>';
            });
    }
    
    // Display stock history data
    function displayStockHistory(data) {
        const stats = data.statistics || {};
        
        // Display statistics
        document.getElementById('stock-history-stats').innerHTML = `
            <div class="bg-white rounded-lg p-3 border border-slate-200">
                <div class="text-xs text-slate-500 font-bold uppercase mb-1">Toplam Satış</div>
                <div class="text-lg font-black text-slate-900">${stats.total_sales_count || 0}</div>
            </div>
            <div class="bg-white rounded-lg p-3 border border-slate-200">
                <div class="text-xs text-slate-500 font-bold uppercase mb-1">Toplam Adet</div>
                <div class="text-lg font-black text-slate-900">${stats.total_quantity || 0}</div>
            </div>
            <div class="bg-white rounded-lg p-3 border border-slate-200">
                <div class="text-xs text-slate-500 font-bold uppercase mb-1">Toplam Gelir</div>
                <div class="text-lg font-black text-slate-900">₺${(stats.total_revenue || 0).toFixed(2)}</div>
            </div>
            <div class="bg-white rounded-lg p-3 border border-slate-200">
                <div class="text-xs text-slate-500 font-bold uppercase mb-1">Masa Sayısı</div>
                <div class="text-lg font-black text-slate-900">${stats.unique_tables || 0}</div>
            </div>
        `;
        
        // Display tables
        const tables = data.tables || [];
        if (tables.length > 0) {
            document.getElementById('stock-history-tables').innerHTML = tables.map(table => `
                <div class="bg-white rounded-lg p-3 border border-slate-200 flex items-center justify-between">
                    <div>
                        <div class="text-sm font-black text-slate-900">${escapeHtml(table.table_name || `Masa ${table.table_id}`)}</div>
                        <div class="text-xs text-slate-500">Masa ID: ${table.table_id}</div>
                    </div>
                    <div class="text-right">
                        <div class="text-sm font-black text-slate-900">${table.quantity} adet</div>
                        <div class="text-xs text-slate-500">${table.count} sipariş</div>
                    </div>
                </div>
            `).join('');
        } else {
            document.getElementById('stock-history-tables').innerHTML = '<p class="text-sm text-slate-500 text-center py-4">Bu tarihte satış yapılmamış</p>';
        }
        
        // Display hourly chart
        const hourlySales = data.sales_by_hour || {};
        const hours = Object.keys(hourlySales).sort();
        if (hours.length > 0) {
            const maxSales = Math.max(...hours.map(h => hourlySales[h]));
            document.getElementById('stock-history-hourly-chart').innerHTML = hours.map(hour => {
                const count = hourlySales[hour];
                const percentage = maxSales > 0 ? (count / maxSales) * 100 : 0;
                return `
                    <div class="flex items-center gap-3">
                        <div class="text-xs font-bold text-slate-700 w-12">${hour}:00</div>
                        <div class="flex-1 bg-slate-200 rounded-full h-6 relative overflow-hidden">
                            <div class="bg-indigo-500 h-full rounded-full transition-all" style="width: ${percentage}%"></div>
                        </div>
                        <div class="text-xs font-black text-slate-900 w-12 text-right">${count}</div>
                    </div>
                `;
            }).join('');
        } else {
            document.getElementById('stock-history-hourly-chart').innerHTML = '<p class="text-sm text-slate-500 text-center py-4">Saatlik veri bulunamadı</p>';
        }
        
        // Display order items
        const orderItems = data.order_items || [];
        if (orderItems.length > 0) {
            document.getElementById('stock-history-order-items').innerHTML = orderItems.map(item => {
                const time = new Date(item.created_at).toLocaleTimeString('tr-TR', { hour: '2-digit', minute: '2-digit' });
                return `
                    <div class="bg-white rounded-lg p-3 border border-slate-200">
                        <div class="flex items-center justify-between mb-2">
                            <div class="text-sm font-black text-slate-900">${time}</div>
                            <div class="text-sm font-black text-slate-900">${item.quantity} adet × ₺${parseFloat(item.price).toFixed(2)}</div>
                        </div>
                        <div class="text-xs text-slate-500">
                            Masa: ${escapeHtml(item.table_name || `Masa ${item.table_id}`)} | 
                            Sipariş: #${item.order_id || 'N/A'}
                        </div>
                    </div>
                `;
            }).join('');
        } else {
            document.getElementById('stock-history-order-items').innerHTML = '<p class="text-sm text-slate-500 text-center py-4">Bu tarihte sipariş bulunamadı</p>';
        }
    }
    
    // Date change handler
    const dateInput = document.getElementById('stock-history-date');
    if (dateInput) {
        dateInput.addEventListener('change', function() {
            if (selectedProductId) {
                loadProductStockHistory(selectedProductId);
            }
        });
    }
    
    // Product search in tree
    const productSearchTree = document.getElementById('product-search-tree');
    if (productSearchTree) {
        productSearchTree.addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const treeItems = document.querySelectorAll('#product-tree .tree-category');
            
            treeItems.forEach(category => {
                const categoryName = category.querySelector('button span').textContent.toLowerCase();
                const products = category.querySelectorAll('button[onclick*="selectStockProduct"]');
                let hasVisibleProducts = false;
                
                products.forEach(product => {
                    const productName = product.textContent.toLowerCase();
                    if (productName.includes(searchTerm) || categoryName.includes(searchTerm)) {
                        product.style.display = '';
                        hasVisibleProducts = true;
                    } else {
                        product.style.display = 'none';
                    }
                });
                
                // Show/hide category based on visible products
                if (hasVisibleProducts || categoryName.includes(searchTerm)) {
                    category.style.display = '';
                } else {
                    category.style.display = 'none';
                }
            });
        });
    }
    
    // Load menu data when Movements tab is opened
    const originalSetStockTab = window.setStockTab;
    window.setStockTab = function(tab) {
        originalSetStockTab(tab);
        if (tab === 'MOVEMENTS') {
            loadMenuData();
        }
    };
    
    // Also load on initial page load if Movements tab is active
    if (currentTab === 'MOVEMENTS') {
        loadMenuData();
    }
})();
</script>
