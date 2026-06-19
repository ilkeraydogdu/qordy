<?php
require_once __DIR__ . '/../../helpers/translations.php';
if (!function_exists('getAdminUrl')) {
    require_once __DIR__ . '/../../helpers/url_helper.php';
}

$title = 'Ürün Bazlı Satış Analizi - ' . getAppConfig()->getAppName();
$isSuperAdmin = $is_super_admin ?? false;
$adminPrefix = $isSuperAdmin ? '/qodmin' : '/business';
$apiPrefix = $isSuperAdmin ? '/api/qodmin' : '/api/business';
$hasBusinessContext = !empty(\App\Core\TenantContext::getId());
$needsBusinessSelection = $isSuperAdmin && !$hasBusinessContext;
?>


<?php if ($needsBusinessSelection): ?>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/business-theme.css?v=<?php echo @filemtime(dirname(__DIR__, 3) . '/public/assets/css/business-theme.css'); ?>">
<div class="q-page q-biz-theme animate-slide-up min-w-0">
  <div class="q-container q-stack q-stack--lg min-w-0">
    <div id="business-selection-view">
        <header class="q-page-header q-page-header--split flex-col sm:flex-row gap-4">
            <div class="min-w-0">
                <p class="q-page-header__eyebrow">Analiz</p>
                <h1 class="q-page-header__title">Ürün Satışları — İşletme Seçin</h1>
                <p class="q-page-header__subtitle">Ürün satış analizini görüntülemek istediğiniz işletmeyi seçin</p>
            </div>
            <div class="q-input-icon-wrap w-full sm:w-64 shrink-0">
                <svg class="q-input-icon-wrap__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
                <input type="search" id="business-search" placeholder="İşletme ara..." onkeyup="BusinessSelector.searchBusinesses(this.value)" class="q-input" autocomplete="off">
            </div>
        </header>
        <div id="business-grid" class="q-grid q-grid--4 min-w-0">
            <div class="col-span-full q-empty q-empty--inline">
                <div class="q-spinner q-spinner--lg mx-auto" role="status" aria-label="Yükleniyor"></div>
                <p class="q-hint mt-4">İşletmeler yükleniyor…</p>
            </div>
        </div>
    </div>
  </div>
</div>
<script>
(function() {
    const bsScript = document.createElement('script');
    bsScript.src = '<?php echo BASE_URL; ?>/assets/js/business-selector.js?v=<?php echo time(); ?>';
    bsScript.onload = function() {
        if (typeof BusinessSelector === 'undefined') return;
        BusinessSelector.init({ baseUrl: <?php echo json_encode(BASE_URL); ?> });
        BusinessSelector.loadBusinesses().then(function() {
            BusinessSelector.renderBusinessGrid('business-grid', function(businessId) {
                window.location.href = '<?php echo BASE_URL . $adminPrefix; ?>/product-sales?business_id=' + businessId;
            });
        });
    };
    document.head.appendChild(bsScript);
})();
</script>
<?php else: ?>
<div class="q-page q-biz-theme product-sales-page">
    
    <!-- Header -->
    <header class="mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
            <div>
                <h1 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight flex items-center gap-2">
                    <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                    </svg>
                    Ürün Bazlı Satış Analizi
                </h1>
                <p class="text-slate-400 text-xs mt-1">Ürünlerinizin günlük, haftalık ve aylık satış performansını takip edin</p>
            </div>
            <div class="flex gap-2">
                <button onclick="sendToThermalPrinter()" id="thermalPrintBtn" class="px-4 py-2.5 bg-indigo-500 text-white rounded-xl text-xs font-semibold hover:bg-indigo-700 transition-all flex items-center gap-2">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                    </svg>
                    <span class="hidden sm:inline">Termal Yazıcı</span>
                </button>
                <button onclick="openProductReceipt()" class="px-4 py-2.5 bg-white text-slate-600 rounded-xl text-xs font-semibold hover:bg-slate-50 transition-all border border-slate-200 flex items-center gap-2">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                    </svg>
                    <span class="hidden sm:inline">Fiş Görüntüle</span>
                </button>
                <button onclick="exportProductSalesCSV()" class="px-4 py-2.5 bg-white text-slate-600 rounded-xl text-xs font-semibold hover:bg-slate-50 transition-all border border-slate-200 flex items-center gap-2">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    <span class="hidden sm:inline">CSV İndir</span>
                </button>
            </div>
        </div>
        
        <!-- Filters Bar -->
        <div class="bg-white rounded-2xl p-4 sm:p-5 border border-slate-100/80 shadow-[0_1px_3px_rgba(0,0,0,0.04)]">
            <div class="flex flex-col sm:flex-row gap-3 mb-3">
                <!-- Date Range -->
                <div class="flex gap-2 flex-1">
                    <div class="relative flex-1">
                        <label class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider mb-1 block">Başlangıç</label>
                        <input type="date" id="startDate" class="w-full px-3 py-2 bg-slate-50/80 text-slate-700 rounded-lg text-sm border border-slate-200/60 focus:outline-none focus:border-slate-300">
                    </div>
                    <div class="relative flex-1">
                        <label class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider mb-1 block">Bitiş</label>
                        <input type="date" id="endDate" class="w-full px-3 py-2 bg-slate-50/80 text-slate-700 rounded-lg text-sm border border-slate-200/60 focus:outline-none focus:border-slate-300">
                    </div>
                </div>
                
                <!-- Search -->
                <div class="relative flex-1">
                    <label class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider mb-1 block">Ürün Ara</label>
                    <input type="text" id="productSearch" placeholder="Ürün adı veya kategori ara..." 
                           oninput="filterProductTable()"
                           class="search-input w-full px-3 py-2 pl-9 bg-slate-50/80 text-slate-700 rounded-lg text-sm border border-slate-200/60 focus:outline-none focus:border-slate-300 placeholder:text-slate-350">
                    <svg class="absolute left-3 bottom-[10px] w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                </div>
            </div>
            
            <!-- Period Chips + Quick Dates -->
            <div class="q-ps-filters">
                <div class="q-ps-filters__row q-ps-filters__row--period">
                    <span class="q-ps-filters__label">Periyot:</span>
                    <div class="q-ps-filters__chips">
                        <button type="button" onclick="setPeriod('daily')" class="period-chip q-ps-chip" data-period="daily">Günlük</button>
                        <button type="button" onclick="setPeriod('weekly')" class="period-chip q-ps-chip" data-period="weekly">Haftalık</button>
                        <button type="button" onclick="setPeriod('monthly')" class="period-chip q-ps-chip" data-period="monthly">Aylık</button>
                    </div>
                </div>
                <div class="q-ps-filters__row q-ps-filters__row--quick">
                    <span class="q-ps-filters__label">Hızlı:</span>
                    <div class="q-ps-filters__chips">
                        <button type="button" onclick="setQuickDate('today')" class="q-ps-chip q-ps-chip--quick">Bugün</button>
                        <button type="button" onclick="setQuickDate('yesterday')" class="q-ps-chip q-ps-chip--quick">Dün</button>
                        <button type="button" onclick="setQuickDate('week')" class="q-ps-chip q-ps-chip--quick">Bu Hafta</button>
                        <button type="button" onclick="setQuickDate('month')" class="q-ps-chip q-ps-chip--quick">Bu Ay</button>
                        <button type="button" onclick="setQuickDate('last3')" class="q-ps-chip q-ps-chip--quick">Son 3 Ay</button>
                        <button type="button" onclick="setQuickDate('last6')" class="q-ps-chip q-ps-chip--quick">Son 6 Ay</button>
                        <button type="button" onclick="setQuickDate('last9')" class="q-ps-chip q-ps-chip--quick">Son 9 Ay</button>
                        <button type="button" onclick="setQuickDate('year')" class="q-ps-chip q-ps-chip--quick">Bu Yıl</button>
                    </div>
                </div>
                <button id="applyBtn" type="button" onclick="loadData()" class="q-ps-filters__apply">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                    </svg>
                    Uygula
                </button>
            </div>
        </div>
    </header>
    
    <!-- Summary Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6" id="summaryCards">
        <div class="stat-card bg-white rounded-2xl p-5 shadow-sm">
            <div class="flex items-center justify-between mb-3">
                <div class="w-10 h-10 bg-blue-50 rounded-xl flex items-center justify-center">
                    <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                    </svg>
                </div>
            </div>
            <p class="text-2xl font-bold text-slate-900" id="totalQuantity">-</p>
            <p class="text-xs text-slate-400 mt-1">Toplam Satış Adedi</p>
        </div>
        <div class="stat-card bg-white rounded-2xl p-5 shadow-sm">
            <div class="flex items-center justify-between mb-3">
                <div class="w-10 h-10 bg-green-50 rounded-xl flex items-center justify-center">
                    <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
            </div>
            <p class="text-2xl font-bold text-slate-900" id="totalRevenue">-</p>
            <p class="text-xs text-slate-400 mt-1">Toplam Gelir</p>
        </div>
        <div class="stat-card bg-white rounded-2xl p-5 shadow-sm">
            <div class="flex items-center justify-between mb-3">
                <div class="w-10 h-10 bg-purple-50 rounded-xl flex items-center justify-center">
                    <svg class="w-5 h-5 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                    </svg>
                </div>
            </div>
            <p class="text-2xl font-bold text-slate-900" id="totalProducts">-</p>
            <p class="text-xs text-slate-400 mt-1">Farklı Ürün Sayısı</p>
        </div>
    </div>
    
    <!-- Tabs -->
    <div class="flex gap-1 mb-4 bg-white rounded-xl p-1 border border-slate-100 w-fit">
        <button onclick="switchTab('overview')" class="tab-btn px-4 py-2 rounded-lg text-xs font-semibold transition-all active" data-tab="overview">
            <span class="flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                </svg>
                Genel Bakış
            </span>
        </button>
        <button onclick="switchTab('products')" class="tab-btn px-4 py-2 rounded-lg text-xs font-semibold transition-all" data-tab="products">
            <span class="flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path>
                </svg>
                Ürün Detayı
            </span>
        </button>
        <button onclick="switchTab('categories')" class="tab-btn px-4 py-2 rounded-lg text-xs font-semibold transition-all" data-tab="categories">
            <span class="flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                </svg>
                Kategoriler
            </span>
        </button>
        <button onclick="switchTab('timeline')" class="tab-btn px-4 py-2 rounded-lg text-xs font-semibold transition-all" data-tab="timeline">
            <span class="flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
                Zaman Çizelgesi
            </span>
        </button>
    </div>
    
    <!-- Tab Content -->
    <div id="tabContent">
        
        <!-- Overview Tab -->
        <div id="tab-overview" class="tab-panel fade-in">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Top 10 Products Chart -->
                <div class="bg-white rounded-2xl p-5 border border-slate-100/80 shadow-sm">
                    <h3 class="text-sm font-bold text-slate-800 mb-4 flex items-center gap-2">
                        <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                        </svg>
                        En Çok Satan 10 Ürün
                    </h3>
                    <div id="top10Chart" class="space-y-3">
                        <div class="text-center text-slate-400 text-sm py-10">Veri yükleniyor...</div>
                    </div>
                </div>
                
                <!-- Category Distribution -->
                <div class="bg-white rounded-2xl p-5 border border-slate-100/80 shadow-sm">
                    <h3 class="text-sm font-bold text-slate-800 mb-4 flex items-center gap-2">
                        <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z"></path>
                        </svg>
                        Kategori Dağılımı
                    </h3>
                    <div id="categoryChart" class="space-y-3">
                        <div class="text-center text-slate-400 text-sm py-10">Veri yükleniyor...</div>
                    </div>
                </div>
            </div>
            
            <!-- Daily Trend -->
            <div class="bg-white rounded-2xl p-5 border border-slate-100/80 shadow-sm mt-6">
                <h3 class="text-sm font-bold text-slate-800 mb-4 flex items-center gap-2">
                    <svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"></path>
                    </svg>
                    Günlük Satış Trendi
                </h3>
                <div id="trendChart" class="chart-container">
                    <div class="text-center text-slate-400 text-sm py-10">Veri yükleniyor...</div>
                </div>
            </div>
        </div>
        
        <!-- Products Detail Tab -->
        <div id="tab-products" class="tab-panel hidden fade-in">
            <div class="bg-white rounded-2xl border border-slate-100/80 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-slate-100">
                                <th class="text-left px-5 py-3 text-[10px] font-semibold text-slate-400 uppercase tracking-wider">#</th>
                                <th class="text-left px-5 py-3 text-[10px] font-semibold text-slate-400 uppercase tracking-wider cursor-pointer hover:text-slate-600" onclick="sortProducts('product_name')">
                                    Ürün Adı
                                    <svg class="w-3 h-3 inline ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"></path></svg>
                                </th>
                                <th class="text-left px-5 py-3 text-[10px] font-semibold text-slate-400 uppercase tracking-wider">Kategori</th>
                                <th class="text-right px-5 py-3 text-[10px] font-semibold text-slate-400 uppercase tracking-wider cursor-pointer hover:text-slate-600" onclick="sortProducts('total_quantity')">
                                    Satış Adedi
                                    <svg class="w-3 h-3 inline ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"></path></svg>
                                </th>
                                <th class="text-right px-5 py-3 text-[10px] font-semibold text-slate-400 uppercase tracking-wider cursor-pointer hover:text-slate-600" onclick="sortProducts('total_revenue')">
                                    Toplam Gelir
                                    <svg class="w-3 h-3 inline ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"></path></svg>
                                </th>
                                <th class="text-right px-5 py-3 text-[10px] font-semibold text-slate-400 uppercase tracking-wider">Birim Fiyat</th>
                                <th class="text-right px-5 py-3 text-[10px] font-semibold text-slate-400 uppercase tracking-wider">Sipariş Sayısı</th>
                                <th class="text-left px-5 py-3 text-[10px] font-semibold text-slate-400 uppercase tracking-wider w-32">Oran</th>
                            </tr>
                        </thead>
                        <tbody id="productsTableBody">
                            <tr>
                                <td colspan="8" class="text-center py-12 text-slate-400 text-sm">Veri yükleniyor...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Categories Tab -->
        <div id="tab-categories" class="tab-panel hidden fade-in">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4" id="categoriesGrid">
                <div class="text-center text-slate-400 text-sm py-12 col-span-full">Veri yükleniyor...</div>
            </div>
        </div>
        
        <!-- Timeline Tab -->
        <div id="tab-timeline" class="tab-panel hidden fade-in">
            <div class="bg-white rounded-2xl border border-slate-100/80 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-slate-100">
                                <th class="text-left px-5 py-3 text-[10px] font-semibold text-slate-400 uppercase tracking-wider">Tarih / Periyot</th>
                                <th class="text-left px-5 py-3 text-[10px] font-semibold text-slate-400 uppercase tracking-wider">Ürün</th>
                                <th class="text-left px-5 py-3 text-[10px] font-semibold text-slate-400 uppercase tracking-wider">Kategori</th>
                                <th class="text-right px-5 py-3 text-[10px] font-semibold text-slate-400 uppercase tracking-wider">Adet</th>
                                <th class="text-right px-5 py-3 text-[10px] font-semibold text-slate-400 uppercase tracking-wider">Gelir</th>
                            </tr>
                        </thead>
                        <tbody id="timelineTableBody">
                            <tr>
                                <td colspan="5" class="text-center py-12 text-slate-400 text-sm">Veri yükleniyor...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
</div>

<script>
(function() {
    // State
    let currentPeriod = 'daily';
    let currentSort = { field: 'total_quantity', dir: 'desc' };
    let cachedData = null;
    const businessId = '<?php echo $businessId ?? ''; ?>';
    
    // Colors palette
    const colors = [
        '#f97316', '#3b82f6', '#10b981', '#8b5cf6', '#ef4444',
        '#06b6d4', '#f59e0b', '#ec4899', '#14b8a6', '#6366f1',
        '#84cc16', '#f43f5e', '#22d3ee', '#a855f7', '#fb923c'
    ];
    
    // Initialize
    document.addEventListener('DOMContentLoaded', function() {
        // Set default dates
        const today = new Date();
        const weekAgo = new Date(today);
        weekAgo.setDate(weekAgo.getDate() - 7);
        
        document.getElementById('startDate').value = formatDate(weekAgo);
        document.getElementById('endDate').value = formatDate(today);
        
        // Auto-reload when the user edits either date picker directly.
        ['startDate', 'endDate'].forEach(function(id) {
            const el = document.getElementById(id);
            if (el) {
                el.addEventListener('change', function() {
                    if (document.getElementById('startDate').value &&
                        document.getElementById('endDate').value) {
                        loadData();
                    }
                });
            }
        });

        setPeriod('daily', true);
        loadData();
    });
    
    function formatDate(d) {
        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    }
    
    function formatCurrency(val) {
        return new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY', minimumFractionDigits: 2 }).format(val);
    }
    
    function formatNumber(val) {
        return new Intl.NumberFormat('tr-TR').format(val);
    }
    
    window.setPeriod = function(period, skipReload) {
        currentPeriod = period;
        document.querySelectorAll('.period-chip').forEach(el => {
            el.classList.toggle('active', el.dataset.period === period);
        });
        // Automatically refresh the data when the user switches period;
        // the initial DOMContentLoaded call passes skipReload=true because
        // it triggers loadData() on its own immediately afterwards.
        if (!skipReload) {
            loadData();
        }
    };
    
    window.setQuickDate = function(preset) {
        const today = new Date();
        let start = new Date(today);
        
        switch(preset) {
            case 'today':
                start = new Date(today);
                break;
            case 'yesterday':
                start.setDate(start.getDate() - 1);
                document.getElementById('endDate').value = formatDate(start);
                document.getElementById('startDate').value = formatDate(start);
                loadData();
                return;
            case 'week':
                start.setDate(start.getDate() - start.getDay() + 1);
                break;
            case 'month':
                start = new Date(today.getFullYear(), today.getMonth(), 1);
                break;
            case 'last3':
                start.setMonth(start.getMonth() - 3);
                break;
            case 'last6':
                start.setMonth(start.getMonth() - 6);
                break;
            case 'last9':
                start.setMonth(start.getMonth() - 9);
                break;
            case 'year':
                start = new Date(today.getFullYear(), 0, 1);
                break;
        }
        
        document.getElementById('startDate').value = formatDate(start);
        document.getElementById('endDate').value = formatDate(today);
        loadData();
    };
    
    window.loadData = function() {
        const startDate = document.getElementById('startDate').value;
        const endDate = document.getElementById('endDate').value;
        
        if (!startDate || !endDate) return;
        
        const params = new URLSearchParams({
            period: currentPeriod,
            start_date: startDate,
            end_date: endDate
        });
        
        if (businessId) {
            params.append('business_id', businessId);
        }
        
        const apiBase = '<?php echo $isSuperAdmin ? "/api/qodmin" : "/api/business"; ?>';
        
        fetch(`${apiBase}/product-sales/data?${params.toString()}`)
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    cachedData = data;
                    renderSummary(data.summary);
                    renderTop10(data.top_10_products);
                    renderCategories(data.category_totals);
                    renderTrend(data.daily_trend);
                    renderProductsTable(data.product_totals);
                    renderCategoriesGrid(data.category_totals);
                    renderTimeline(data.detailed_data);
                } else {
                    showError(data.error || 'Veri yüklenemedi');
                }
            })
            .catch(err => {
                console.error('Product sales data error:', err);
                showError('Bağlantı hatası');
            });
    };
    
    function showError(msg) {
        document.getElementById('totalQuantity').textContent = '-';
        document.getElementById('totalRevenue').textContent = '-';
        document.getElementById('totalProducts').textContent = '-';
    }
    
    function renderSummary(summary) {
        document.getElementById('totalQuantity').textContent = formatNumber(summary.total_quantity);
        document.getElementById('totalRevenue').textContent = formatCurrency(summary.total_revenue);
        document.getElementById('totalProducts').textContent = formatNumber(summary.total_products);
    }
    
    function renderTop10(products) {
        const container = document.getElementById('top10Chart');
        if (!products || products.length === 0) {
            container.innerHTML = '<div class="text-center text-slate-400 text-sm py-10">Bu tarih aralığında satış verisi bulunamadı</div>';
            return;
        }
        
        const maxQty = Math.max(...products.map(p => parseInt(p.total_quantity)));
        
        container.innerHTML = products.map((p, i) => {
            const pct = (parseInt(p.total_quantity) / maxQty * 100).toFixed(0);
            const color = colors[i % colors.length];
            return `
                <div class="flex items-center gap-3">
                    <div class="w-5 text-right text-[10px] font-bold text-slate-400">${i + 1}</div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-xs font-semibold text-slate-700 truncate">${escapeHtml(p.product_name)}</span>
                            <span class="text-xs font-bold text-slate-900 ml-2 whitespace-nowrap">${formatNumber(p.total_quantity)} adet</span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-bar-fill" style="width: ${pct}%; background: ${color};"></div>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    }
    
    function renderCategories(categories) {
        const container = document.getElementById('categoryChart');
        if (!categories || categories.length === 0) {
            container.innerHTML = '<div class="text-center text-slate-400 text-sm py-10">Kategori verisi bulunamadı</div>';
            return;
        }
        
        const totalQty = categories.reduce((sum, c) => sum + parseInt(c.total_quantity), 0);
        
        container.innerHTML = categories.map((c, i) => {
            const pct = totalQty > 0 ? (parseInt(c.total_quantity) / totalQty * 100).toFixed(1) : 0;
            const color = colors[i % colors.length];
            return `
                <div class="flex items-center gap-3">
                    <div class="w-3 h-3 rounded-full flex-shrink-0" style="background: ${color}"></div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-xs font-semibold text-slate-700 truncate">${escapeHtml(c.category_name || 'Kategorisiz')}</span>
                            <span class="text-[10px] font-bold text-slate-500 ml-2 whitespace-nowrap">${pct}% · ${formatNumber(c.total_quantity)} adet</span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-bar-fill" style="width: ${pct}%; background: ${color};"></div>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    }
    
    function renderTrend(trend) {
        const container = document.getElementById('trendChart');
        if (!trend || trend.length === 0) {
            container.innerHTML = '<div class="text-center text-slate-400 text-sm py-10">Trend verisi bulunamadı</div>';
            return;
        }
        
        const maxQty = Math.max(...trend.map(t => parseInt(t.total_quantity)));
        const maxRev = Math.max(...trend.map(t => parseFloat(t.total_revenue)));
        const chartHeight = 250;
        const barWidth = Math.max(20, Math.min(60, (container.offsetWidth - 80) / trend.length - 4));
        
        let html = '<div style="display:flex; align-items:flex-end; gap:4px; height:' + chartHeight + 'px; padding: 0 10px; overflow-x:auto;">';
        
        trend.forEach((t, i) => {
            const h = maxQty > 0 ? (parseInt(t.total_quantity) / maxQty * (chartHeight - 40)) : 0;
            const dateObj = new Date(t.date);
            const dayLabel = String(dateObj.getDate()).padStart(2, '0') + '/' + String(dateObj.getMonth() + 1).padStart(2, '0');
            const dayName = ['Paz','Pzt','Sal','Çar','Per','Cum','Cmt'][dateObj.getDay()];
            
            html += `
                <div class="flex flex-col items-center gap-1 flex-shrink-0" style="width:${barWidth}px;" title="${t.date}: ${formatNumber(t.total_quantity)} adet / ${formatCurrency(t.total_revenue)}">
                    <span class="text-[9px] font-bold text-slate-600">${formatNumber(t.total_quantity)}</span>
                    <div style="width:${barWidth - 4}px; height:${Math.max(4, h)}px; background: linear-gradient(180deg, #f97316 0%, #fb923c 100%); border-radius:6px 6px 2px 2px; transition: height 0.5s ease;"></div>
                    <span class="text-[9px] text-slate-400 font-medium">${dayLabel}</span>
                    <span class="text-[8px] text-slate-300">${dayName}</span>
                </div>
            `;
        });
        
        html += '</div>';
        
        // Revenue line below
        html += '<div class="flex items-center justify-between mt-3 pt-3 border-t border-slate-100 px-3">';
        if (trend.length >= 2) {
            const firstDayQty = parseInt(trend[0].total_quantity);
            const lastDayQty = parseInt(trend[trend.length - 1].total_quantity);
            const change = firstDayQty > 0 ? ((lastDayQty - firstDayQty) / firstDayQty * 100).toFixed(1) : 0;
            const isUp = change >= 0;
            html += `<span class="text-xs text-slate-400">Trend: <span class="${isUp ? 'trend-up' : 'trend-down'} font-semibold">${isUp ? '↑' : '↓'} %${Math.abs(change)}</span></span>`;
        }
        html += `<span class="text-xs text-slate-400">Toplam: <strong class="text-slate-700">${formatNumber(trend.reduce((s,t) => s + parseInt(t.total_quantity), 0))} adet</strong></span>`;
        html += '</div>';
        
        container.innerHTML = html;
    }
    
    function renderProductsTable(products) {
        const tbody = document.getElementById('productsTableBody');
        if (!products || products.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center py-12 text-slate-400 text-sm">Bu tarih aralığında satış verisi bulunamadı</td></tr>';
            return;
        }
        
        const totalQty = products.reduce((s, p) => s + parseInt(p.total_quantity), 0);
        
        // Sort
        const sorted = [...products].sort((a, b) => {
            let aVal = a[currentSort.field];
            let bVal = b[currentSort.field];
            if (['total_quantity', 'total_revenue', 'order_count', 'current_price'].includes(currentSort.field)) {
                aVal = parseFloat(aVal);
                bVal = parseFloat(bVal);
            }
            if (currentSort.dir === 'asc') return aVal > bVal ? 1 : -1;
            return aVal < bVal ? 1 : -1;
        });
        
        tbody.innerHTML = sorted.map((p, i) => {
            const pct = totalQty > 0 ? (parseInt(p.total_quantity) / totalQty * 100).toFixed(1) : 0;
            const color = colors[i % colors.length];
            return `
                <tr class="product-row border-b border-slate-50 last:border-0" data-name="${escapeHtml(p.product_name).toLowerCase()}" data-category="${escapeHtml(p.category_name || '').toLowerCase()}">
                    <td class="px-5 py-3 text-xs text-slate-400 font-mono">${i + 1}</td>
                    <td class="px-5 py-3">
                        <div class="flex items-center gap-2">
                            <div class="w-2 h-2 rounded-full flex-shrink-0" style="background:${color}"></div>
                            <span class="text-sm font-semibold text-slate-800">${escapeHtml(p.product_name)}</span>
                        </div>
                    </td>
                    <td class="px-5 py-3">
                        <span class="category-badge bg-slate-100 text-slate-500 font-medium">${escapeHtml(p.category_name || 'Kategorisiz')}</span>
                    </td>
                    <td class="px-5 py-3 text-right">
                        <span class="text-sm font-bold text-slate-900">${formatNumber(p.total_quantity)}</span>
                    </td>
                    <td class="px-5 py-3 text-right">
                        <span class="text-sm font-semibold text-green-600">${formatCurrency(p.total_revenue)}</span>
                    </td>
                    <td class="px-5 py-3 text-right">
                        <span class="text-xs text-slate-500">${formatCurrency(p.current_price)}</span>
                    </td>
                    <td class="px-5 py-3 text-right">
                        <span class="text-xs text-slate-500">${formatNumber(p.order_count)}</span>
                    </td>
                    <td class="px-5 py-3">
                        <div class="flex items-center gap-2">
                            <div class="progress-bar flex-1">
                                <div class="progress-bar-fill" style="width:${pct}%; background:${color};"></div>
                            </div>
                            <span class="text-[10px] font-bold text-slate-400 w-10 text-right">${pct}%</span>
                        </div>
                    </td>
                </tr>
            `;
        }).join('');
    }
    
    function renderCategoriesGrid(categories) {
        const container = document.getElementById('categoriesGrid');
        if (!categories || categories.length === 0) {
            container.innerHTML = '<div class="text-center text-slate-400 text-sm py-12 col-span-full">Kategori verisi bulunamadı</div>';
            return;
        }
        
        const totalQty = categories.reduce((s, c) => s + parseInt(c.total_quantity), 0);
        const totalRev = categories.reduce((s, c) => s + parseFloat(c.total_revenue), 0);
        
        container.innerHTML = categories.map((c, i) => {
            const pctQty = totalQty > 0 ? (parseInt(c.total_quantity) / totalQty * 100).toFixed(1) : 0;
            const pctRev = totalRev > 0 ? (parseFloat(c.total_revenue) / totalRev * 100).toFixed(1) : 0;
            const color = colors[i % colors.length];
            return `
                <div class="bg-white rounded-2xl p-5 border border-slate-100/80 shadow-sm hover:shadow-md transition-all">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-10 h-10 rounded-xl flex items-center justify-center" style="background:${color}15;">
                            <div class="w-4 h-4 rounded-full" style="background:${color};"></div>
                        </div>
                        <div>
                            <h4 class="text-sm font-bold text-slate-800">${escapeHtml(c.category_name || 'Kategorisiz')}</h4>
                            <span class="text-[10px] text-slate-400">${c.product_count} ürün</span>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <p class="text-lg font-bold text-slate-900">${formatNumber(c.total_quantity)}</p>
                            <p class="text-[10px] text-slate-400">Satış Adedi</p>
                            <p class="text-[10px] font-semibold" style="color:${color}">%${pctQty}</p>
                        </div>
                        <div>
                            <p class="text-lg font-bold text-green-600">${formatCurrency(c.total_revenue)}</p>
                            <p class="text-[10px] text-slate-400">Toplam Gelir</p>
                            <p class="text-[10px] font-semibold" style="color:${color}">%${pctRev}</p>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    }
    
    function renderTimeline(detailed) {
        const tbody = document.getElementById('timelineTableBody');
        if (!detailed || detailed.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center py-12 text-slate-400 text-sm">Bu tarih aralığında veri bulunamadı</td></tr>';
            return;
        }
        
        // Group by period
        const grouped = {};
        detailed.forEach(d => {
            const key = d.period_label;
            if (!grouped[key]) grouped[key] = [];
            grouped[key].push(d);
        });
        
        let html = '';
        Object.keys(grouped).forEach(period => {
            const items = grouped[period];
            const periodTotal = items.reduce((s, item) => s + parseInt(item.total_quantity), 0);
            const periodRevenue = items.reduce((s, item) => s + parseFloat(item.total_revenue), 0);
            
            // Period header row
            html += `
                <tr class="bg-slate-50/80 border-b border-slate-100">
                    <td class="px-5 py-2.5" colspan="3">
                        <span class="text-xs font-bold text-slate-700">${escapeHtml(formatPeriodLabel(period))}</span>
                    </td>
                    <td class="px-5 py-2.5 text-right">
                        <span class="text-xs font-bold text-slate-700">${formatNumber(periodTotal)}</span>
                    </td>
                    <td class="px-5 py-2.5 text-right">
                        <span class="text-xs font-bold text-green-600">${formatCurrency(periodRevenue)}</span>
                    </td>
                </tr>
            `;
            
            // Sort items within period by quantity desc
            items.sort((a, b) => parseInt(b.total_quantity) - parseInt(a.total_quantity));
            
            items.forEach(item => {
                html += `
                    <tr class="product-row border-b border-slate-50 last:border-0">
                        <td class="px-5 py-2 pl-10 text-xs text-slate-400"></td>
                        <td class="px-5 py-2 text-xs font-medium text-slate-700">${escapeHtml(item.product_name)}</td>
                        <td class="px-5 py-2">
                            <span class="category-badge bg-slate-100 text-slate-500">${escapeHtml(item.category_name || 'Kategorisiz')}</span>
                        </td>
                        <td class="px-5 py-2 text-right text-xs font-semibold text-slate-800">${formatNumber(item.total_quantity)}</td>
                        <td class="px-5 py-2 text-right text-xs text-green-600">${formatCurrency(item.total_revenue)}</td>
                    </tr>
                `;
            });
        });
        
        tbody.innerHTML = html;
    }
    
    function formatPeriodLabel(label) {
        // If it's a date (YYYY-MM-DD)
        if (/^\d{4}-\d{2}-\d{2}$/.test(label)) {
            const d = new Date(label);
            const days = ['Pazar','Pazartesi','Salı','Çarşamba','Perşembe','Cuma','Cumartesi'];
            const months = ['Ocak','Şubat','Mart','Nisan','Mayıs','Haziran','Temmuz','Ağustos','Eylül','Ekim','Kasım','Aralık'];
            return `${d.getDate()} ${months[d.getMonth()]} ${d.getFullYear()} - ${days[d.getDay()]}`;
        }
        // If it's a week (YYYY-Www)
        if (/^\d{4}-W\d{2}$/.test(label)) {
            return label.replace('-W', ' - Hafta ');
        }
        // If it's a month (YYYY-MM)
        if (/^\d{4}-\d{2}$/.test(label)) {
            const months = ['Ocak','Şubat','Mart','Nisan','Mayıs','Haziran','Temmuz','Ağustos','Eylül','Ekim','Kasım','Aralık'];
            const [y, m] = label.split('-');
            return months[parseInt(m) - 1] + ' ' + y;
        }
        return label;
    }
    
    window.switchTab = function(tab) {
        document.querySelectorAll('.tab-btn').forEach(el => {
            el.classList.toggle('active', el.dataset.tab === tab);
        });
        document.querySelectorAll('.tab-panel').forEach(el => {
            el.classList.add('hidden');
        });
        const panel = document.getElementById('tab-' + tab);
        if (panel) {
            panel.classList.remove('hidden');
            panel.classList.add('fade-in');
        }
    };
    
    window.sortProducts = function(field) {
        if (currentSort.field === field) {
            currentSort.dir = currentSort.dir === 'asc' ? 'desc' : 'asc';
        } else {
            currentSort.field = field;
            currentSort.dir = 'desc';
        }
        if (cachedData) {
            renderProductsTable(cachedData.product_totals);
        }
    };
    
    window.filterProductTable = function() {
        const search = document.getElementById('productSearch').value.toLowerCase().trim();
        const rows = document.querySelectorAll('#productsTableBody .product-row');
        
        rows.forEach(row => {
            const name = row.dataset.name || '';
            const cat = row.dataset.category || '';
            const match = !search || name.includes(search) || cat.includes(search);
            row.style.display = match ? '' : 'none';
        });
        
        // Also filter timeline
        const timelineRows = document.querySelectorAll('#timelineTableBody .product-row');
        timelineRows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = !search || text.includes(search) ? '' : 'none';
        });
    };
    
    window.sendToThermalPrinter = function() {
        const startDate = document.getElementById('startDate').value;
        const endDate = document.getElementById('endDate').value;
        
        if (!startDate || !endDate) {
            if (window.NotificationManager) window.NotificationManager.warning('Lütfen tarih aralığı seçin');
            else alert('Lütfen tarih aralığı seçin');
            return;
        }
        
        const btn = document.getElementById('thermalPrintBtn');
        const originalText = btn.querySelector('span.hidden')?.textContent || 'Termal Yazıcı';
        const spanEl = btn.querySelector('span.hidden');
        
        btn.disabled = true;
        if (spanEl) spanEl.textContent = 'Gönderiliyor...';
        
        const apiBase = '<?php echo $isSuperAdmin ? "/api/qodmin" : "/api/business"; ?>';
        const body = { start_date: startDate, end_date: endDate };
        if (businessId) body.business_id = businessId;
        
        const metaTag = document.querySelector('meta[name="csrf-token"]');
        const csrfToken = metaTag ? metaTag.getAttribute('content') : (window.CSRF_TOKEN || '');
        
        fetch(`${apiBase}/product-sales/print`, {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            credentials: 'same-origin',
            body: JSON.stringify(body)
        })
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            if (spanEl) spanEl.textContent = originalText;
            
            if (data.success) {
                if (window.NotificationManager) window.NotificationManager.success('Yazıcıya gönderildi!');
                else {
                    const toast = document.createElement('div');
                    toast.className = 'fixed top-4 right-4 bg-green-500 text-white px-4 py-3 rounded-xl text-sm font-semibold shadow-lg z-50';
                    toast.textContent = 'Yazıcıya gönderildi!';
                    document.body.appendChild(toast);
                    setTimeout(() => toast.remove(), 3000);
                }
            } else {
                if (window.NotificationManager) window.NotificationManager.error('Hata: ' + (data.error || 'Gönderilemedi'));
                else alert('Hata: ' + (data.error || 'Gönderilemedi'));
            }
        })
        .catch(err => {
            btn.disabled = false;
            if (spanEl) spanEl.textContent = originalText;
            if (window.NotificationManager) window.NotificationManager.error('Bağlantı hatası: ' + err.message);
            else alert('Bağlantı hatası: ' + err.message);
        });
    };
    
    window.openProductReceipt = function() {
        const startDate = document.getElementById('startDate').value;
        const endDate = document.getElementById('endDate').value;
        
        if (!startDate || !endDate) {
            if (window.NotificationManager) window.NotificationManager.warning('Lütfen tarih aralığı seçin');
            else alert('Lütfen tarih aralığı seçin');
            return;
        }
        
        const apiBase = '<?php echo $isSuperAdmin ? "/api/qodmin" : "/api/business"; ?>';
        const params = new URLSearchParams({ start_date: startDate, end_date: endDate });
        if (businessId) params.append('business_id', businessId);
        
        window.open(`${apiBase}/product-sales/receipt?${params.toString()}`, '_blank');
    };
    
    window.exportProductSalesCSV = function() {
        if (!cachedData || !cachedData.product_totals) return;
        
        const headers = ['Ürün Adı', 'Kategori', 'Satış Adedi', 'Toplam Gelir', 'Birim Fiyat', 'Sipariş Sayısı'];
        const rows = cachedData.product_totals.map(p => [
            p.product_name,
            p.category_name || 'Kategorisiz',
            p.total_quantity,
            parseFloat(p.total_revenue).toFixed(2),
            parseFloat(p.current_price).toFixed(2),
            p.order_count
        ]);
        
        let csv = '\uFEFF'; // BOM for Turkish chars
        csv += headers.join(';') + '\n';
        rows.forEach(r => {
            csv += r.map(val => '"' + String(val).replace(/"/g, '""') + '"').join(';') + '\n';
        });
        
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `urun-satis-analizi-${document.getElementById('startDate').value}-${document.getElementById('endDate').value}.csv`;
        a.click();
        URL.revokeObjectURL(url);
    };
    
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
})();
</script>
<?php endif; ?>
