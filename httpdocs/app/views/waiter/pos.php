<?php
/**
 * Waiter POS View — Modern UI (2026 Redesign)
 * - Modern kart tasarımı (gradient, rounded-2xl, shadow)
 * - Kategori ikon sistemi (DB gerektirmez, ada göre otomatik)
 * - Ürün görsel boyutu optimize (16:9 + fallback gradient)
 * - Sepete eklenince otomatik sepet açma (eski davranış)
 * - Tüm mevcut JS fonksiyonları ve API çağrıları korunmuştur
 */

require_once __DIR__ . '/../../helpers/translations.php';
require_once __DIR__ . '/../partials/icons.php';
require_once __DIR__ . '/pos_icons.php';

$baseUrl = defined('BASE_URL') ? BASE_URL : '';
$translations = getTranslations(getCurrentLanguage());
$table = $table ?? null;
// CRITICAL: Get tableId from multiple sources to ensure it's always available
$tableId = $table_id ?? ($table['table_id'] ?? '');
$categories = $categories ?? [];
$menuItems = $menu_items ?? [];
$csrfToken = $csrf_token ?? '';

$tableName = $table['name'] ?? 'Masa';
$tableStatus = $table['status'] ?? 'FREE';

// Get zone name for the table
$tableZoneName = $table['zone'] ?? '';
if (empty($tableZoneName) && !empty($table['zone_id'])) {
    // Fallback: resolve zone name from zone_id
    try {
        $zoneService = \App\Core\DependencyFactory::getZoneService();
        $zoneData = $zoneService->getZoneById($table['zone_id']);
        $tableZoneName = $zoneData['name'] ?? '';
    } catch (\Exception $e) {
        // Continue without zone name
    }
}

// Check if the zone column has a name from the zones table (zone_id based)
if (empty($tableZoneName) && $table) {
    // Try zone_name field if available
    $tableZoneName = $table['zone_name'] ?? '';
}

// CRITICAL: If tableId is still empty, try to get from URL query parameter
if (empty($tableId)) {
    $queryParams = \App\Core\RequestParser::getQueryParams();
    $tableId = $queryParams['table'] ?? $queryParams['table_id'] ?? '';
}

// CRITICAL: Validate tableId exists
if (empty($tableId) && $table) {
    $tableId = $table['table_id'] ?? '';
}

// PERFORMANCE: Organize categories hierarchically for card-based navigation
$parentCategories = [];
$subCategoriesByParent = [];
$itemsByCategory = [];

// Group categories by parent
foreach ($categories as $cat) {
    $catId = $cat['category_id'] ?? '';
    $parentId = $cat['parent_id'] ?? '';
    
    if (empty($parentId)) {
        // Parent category
        $parentCategories[$catId] = $cat;
        $subCategoriesByParent[$catId] = [];
    } else {
        // Subcategory
        if (!isset($subCategoriesByParent[$parentId])) {
            $subCategoriesByParent[$parentId] = [];
        }
        $subCategoriesByParent[$parentId][] = $cat;
    }
}

// Group menu items by category
foreach ($menuItems as $item) {
    $itemCategoryId = $item['category_id'] ?? '';
    if (!empty($itemCategoryId)) {
        if (!isset($itemsByCategory[$itemCategoryId])) {
            $itemsByCategory[$itemCategoryId] = [];
        }
        $itemsByCategory[$itemCategoryId][] = $item;
    }
}

// DEBUG: Log table info (will be visible in console)
if (!empty($tableId)) {
    error_log("POS Screen - Table ID: " . $tableId);
    if ($table) {
        error_log("POS Screen - Table Name: " . ($table['name'] ?? 'Unknown'));
        error_log("POS Screen - Table Status: " . ($table['status'] ?? 'Unknown'));
    } else {
        error_log("POS Screen - Table object is NULL!");
    }
} else {
    error_log("POS Screen - No table ID found!");
}
?>

<style>
/* Safe area for iOS */
.safe-area-bottom {
    padding-bottom: env(safe-area-inset-bottom, 0);
}

/* Search highlight */
.highlight {
    background-color: #fef3c7;
    font-weight: 600;
}

/* Modal animation */
@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(20px) scale(0.98);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}
.animate-slide-up {
    animation: slideUp 0.25s ease-out forwards;
}
/* YENI */
@keyframes cartPulse { 0%{box-shadow:0 0 0 0 rgba(99,102,241,.55);transform:scale(1)} 50%{box-shadow:0 0 0 10px rgba(99,102,241,0);transform:scale(1.015)} 100%{box-shadow:0 0 0 0 rgba(99,102,241,0);transform:scale(1)} }
.cart-item-pulse{animation:cartPulse .7s ease-out}
@keyframes shimmerMove{0%{background-position:-200% 0}100%{background-position:200% 0}}
.product-image-placeholder{background-size:200% 200%;animation:shimmerMove 8s ease-in-out infinite}
@keyframes fadeSlideIn{from{opacity:0;transform:translateX(20px)}to{opacity:1;transform:translateX(0)}}
.fade-slide-in{animation:fadeSlideIn .3s ease-out forwards}
.tab-pill{position:relative;transition:all .2s cubic-bezier(.4,0,.2,1)}
.tab-pill:hover:not(.tab-pill--active){background-color:rgb(248 250 252);color:rgb(15 23 42)}
.tab-pill--active{background-color:rgb(15 23 42)!important;color:#fff!important;box-shadow:0 10px 25px -10px rgba(15,23,42,.5)}
.no-scrollbar::-webkit-scrollbar{display:none}
.no-scrollbar{-ms-overflow-style:none;scrollbar-width:none}
@keyframes badgePop{0%{transform:scale(0)}60%{transform:scale(1.2)}100%{transform:scale(1)}}
.badge-pop{animation:badgePop .3s ease-out}

/* Masa paneli — Siparişler / Sepet / Geçmiş (mevcut sidebar) */
#waiter-pos .waiter-pos-sidebar {
    display: none;
    flex-direction: column;
    flex-shrink: 0;
    min-height: 0;
    min-width: 0;
    width: 18rem;
    max-width: min(24rem, 34vw);
    background: #fff;
    border-left: 1px solid rgb(226 232 240);
}
@media (min-width: 640px) {
    body.q-biz-layout .q-biz-ops-embed #waiter-pos .waiter-pos-sidebar {
        display: flex;
    }
}
@media (min-width: 1024px) {
    #waiter-pos .waiter-pos-sidebar {
        display: flex;
        position: relative;
        transform: none;
        box-shadow: none;
    }
}
#waiter-pos #waiter-mobile-dock {
    display: block;
}
@media (min-width: 640px) {
    body.q-biz-layout .q-biz-ops-embed #waiter-pos #waiter-mobile-dock {
        display: none;
    }
}
@media (min-width: 1024px) {
    #waiter-pos #waiter-mobile-dock,
    #waiter-pos #waiter-pos-sidebar-overlay {
        display: none !important;
    }
}
#waiter-pos.waiter-pos--drawer-mode .waiter-pos-sidebar {
    display: flex;
    position: fixed;
    top: 0;
    right: 0;
    bottom: 0;
    width: min(100vw, 24rem);
    max-width: 24rem;
    z-index: 60;
    transform: translateX(100%);
    transition: transform 0.28s ease;
    box-shadow: -12px 0 40px rgba(15, 23, 42, 0.14);
}
#waiter-pos.waiter-pos--drawer-mode .waiter-pos-sidebar.is-open {
    transform: translateX(0);
}
#waiter-pos #menu-items-container.waiter-pos-has-dock {
    padding-bottom: 4.75rem;
}
#waiter-pos .waiter-mobile-dock-btn.is-active {
    color: rgb(79 70 229);
    background: rgb(238 242 255);
}
#waiter-pos > .flex-1.flex.overflow-hidden {
    min-height: 0;
}
#waiter-pos .waiter-pos-sidebar {
    height: 100%;
    max-height: 100%;
}
</style>

<div class="flex flex-col h-full min-h-0 max-h-full overflow-hidden q-biz-theme q-biz-ops" id="waiter-pos" style="background: #f4f5fa">
    <!-- Header (Modern - 2026 Redesign) -->
 <header class="bg-white/95 backdrop-blur-md border-b border-slate-200/80 px-3 sm:px-5 py-3 flex-shrink-0 sticky top-0 z-30 shadow-sm">
 <div class="flex items-center gap-2 sm:gap-3">
 <!-- Geri butonu -->
 <a href="<?php echo $baseUrl; ?>/waiter/dashboard" class="inline-flex items-center justify-center w-9 h-9 sm:w-10 sm:h-10 rounded-xl bg-slate-100 hover:bg-slate-200 active:scale-95 transition-all text-slate-600 hover:text-slate-900" title="Geri don">
 <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"></path></svg>
 </a>
 <!-- Masa adi + bolge -->
 <div class="flex items-center gap-2 min-w-0 flex-1">
 <h1 class="text-base sm:text-xl font-black text-slate-900 tracking-tight truncate"><?php echo htmlspecialchars($tableName); ?></h1>
 <?php if (!empty($tableZoneName)): ?>
 <span class="hidden sm:inline-flex items-center gap-1 text-[10px] sm:text-xs font-bold text-slate-500 bg-slate-100 px-2 py-0.5 rounded-md whitespace-nowrap">
 <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
 <?php echo htmlspecialchars($tableZoneName); ?>
 </span>
 <?php endif; ?>
 </div>
 <!-- Masa Tasi butonu (modern) -->
 <button onclick="showMoveTableModal()" class="inline-flex items-center gap-1.5 px-3 sm:px-4 py-2 sm:py-2.5 rounded-xl bg-indigo-50 hover:bg-indigo-100 active:scale-95 transition-all text-indigo-600 hover:text-indigo-700 text-xs sm:text-sm font-bold border border-indigo-100">
 <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path></svg>
 <span class="hidden sm:inline">Masa Tasi</span>
 </button>
 </div>

 <!-- Search Bar (Modern) -->
 <div class="mt-3 relative">
 <svg class="absolute left-3 sm:left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
 <input type="text"
 id="search-input"
 placeholder="Urun ara..."
 class="w-full pl-10 sm:pl-11 pr-10 py-2.5 sm:py-3 bg-slate-50 border border-slate-200 rounded-xl sm:rounded-2xl text-sm sm:text-base font-medium focus:bg-white focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 focus:outline-none transition-all"
 onkeypress="handleSearchKeyPress(event)"
 oninput="performSearch()"
 autocomplete="off">
 <button onclick="clearSearch()" id="clear-search" class="absolute right-2 sm:right-3 top-1/2 -translate-y-1/2 w-7 h-7 hidden items-center justify-center rounded-lg bg-slate-200 hover:bg-slate-300 text-slate-500 hover:text-slate-700 transition-colors">
 <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
 </button>
 </div>
 </header>
    
    <div class="flex-1 flex overflow-hidden">
        <!-- Main Content Area -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Breadcrumb -->
            <div class="bg-white border-b px-4 py-2.5 flex items-center gap-2 text-sm" id="breadcrumb-nav" style="display: none;">
                <button onclick="goBack()" class="text-indigo-600 hover:text-indigo-700 font-medium">← Geri</button>
                <span class="text-slate-300">|</span>
                <span id="breadcrumb-text" class="text-slate-600 font-medium"></span>
            </div>
            
            <!-- Categories/Products Container -->
            <div class="flex-1 overflow-y-auto p-4 sm:p-5 bg-slate-100" id="menu-items-container" style="-webkit-overflow-scrolling: touch;">
                <!-- Main Categories View -->
                <div id="main-categories-view" class="category-section">
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-3 xl:grid-cols-4 gap-3" id="categories-grid">
                        <?php 
                        // Empty state: No categories or menu items
                        if (empty($parentCategories) && empty($menuItems)): ?>
                            <div class="col-span-full flex flex-col items-center justify-center py-16 px-4 text-center">
                                <div class="w-16 h-16 rounded-full bg-amber-100 flex items-center justify-center mb-4">
                                    <svg class="w-8 h-8 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                                </div>
                                <h3 class="text-lg font-bold text-slate-800 mb-2"><?php echo t('waiter.no_menu_title', 'Menü henüz eklenmemiş'); ?></h3>
                                <p class="text-sm text-slate-600 mb-4 max-w-sm"><?php echo t('waiter.no_menu_desc', 'Kategori ve ürün eklemek için İşlemler > Menü bölümünden menünüzü oluşturun.'); ?></p>
                                <a href="<?php echo $baseUrl; ?>/business/menu" class="inline-flex items-center gap-2 px-4 py-2.5 bg-indigo-500 hover:bg-indigo-600 text-white font-semibold rounded-xl transition-colors">
                                    <?php echo t('waiter.go_to_menu', 'Menüye Git'); ?> →
                                </a>
                            </div>
                        <?php elseif (empty($parentCategories)): ?>
                            <div class="col-span-full flex flex-col items-center justify-center py-12 px-4 text-center">
                                <p class="text-sm text-slate-600 mb-3"><?php echo t('waiter.no_categories', 'Kategori bulunamadı. Ürünlerinizi kategorilere ekleyin.'); ?></p>
                                <a href="<?php echo $baseUrl; ?>/business/categories" class="text-indigo-600 hover:text-indigo-700 font-semibold text-sm"><?php echo t('waiter.manage_categories', 'Kategorileri Yönet'); ?></a>
                            </div>
                        <?php else:
                        // Show ALL parent categories as cards (no filtering)
                        foreach ($parentCategories as $parentCat): 
                            $parentCatId = $parentCat['category_id'] ?? '';
                            $parentCatName = $parentCat['name'] ?? '';
                            $subCats = $subCategoriesByParent[$parentCatId] ?? [];
                            $directItems = $itemsByCategory[$parentCatId] ?? [];
                            
                            // Count total items (direct + from subcategories + from sub-subcategories)
                            $totalItemCount = count($directItems);
                            foreach ($subCats as $subCat) {
                                $subCatId = $subCat['category_id'] ?? '';
                                $totalItemCount += count($itemsByCategory[$subCatId] ?? []);
                                
                                // Also count items from sub-subcategories
                                $subSubCats = $subCategoriesByParent[$subCatId] ?? [];
                                foreach ($subSubCats as $subSubCat) {
                                    $subSubCatId = $subSubCat['category_id'] ?? '';
                                    $totalItemCount += count($itemsByCategory[$subSubCatId] ?? []);
                                }
                            }
                            // Note: Removed filtering - ALL categories are shown
                        ?>
 <?php
 $catVisual = resolveCategoryVisual($parentCat);
 ?>
 <button onclick="showCategoryContent('<?php echo htmlspecialchars($parentCatId); ?>', '<?php echo htmlspecialchars(addslashes($parentCatName)); ?>')" 
 class="group relative bg-white rounded-2xl p-3 sm:p-4 text-left hover:shadow-lg transition-all active:scale-[0.97] border border-slate-200 hover:border-indigo-300 overflow-hidden">
 <?php if ($catVisual['type'] === 'image'): ?>
 <div class="relative w-full aspect-[4/3] mb-3 rounded-xl overflow-hidden bg-slate-100 shadow-sm">
 <img src="<?php echo htmlspecialchars($catVisual['image_url']); ?>" alt="" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" loading="lazy">
 </div>
 <?php else: ?>
 <div class="relative w-12 h-12 sm:w-14 sm:h-14 mb-3 rounded-xl sm:rounded-2xl bg-gradient-to-br <?php echo htmlspecialchars($catVisual['gradient']); ?> flex items-center justify-center shadow-md group-hover:scale-110 group-hover:rotate-3 transition-all duration-300">
 <svg class="w-7 h-7 sm:w-8 sm:h-8 <?php echo htmlspecialchars($catVisual['text']); ?> drop-shadow" fill="none" stroke="currentColor" viewBox="0 0 24 24">
 <?php echo posCategoryIconSvg($catVisual['icon']); ?>
 </svg>
 <div class="absolute inset-0 rounded-xl sm:rounded-2xl bg-white opacity-0 group-hover:opacity-10 transition-opacity"></div>
 </div>
 <?php endif; ?>
 <!-- Title + Count -->
 <h3 class="font-bold text-sm sm:text-base text-slate-900 mb-1 line-clamp-2 tracking-tight"><?php echo htmlspecialchars($parentCatName); ?></h3>
 <div class="flex items-center gap-1.5 text-[11px] sm:text-xs text-slate-500 font-medium">
 <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path></svg>
 <span><?php echo $totalItemCount; ?> ürün</span>
 <?php if (!empty($subCats)): ?>
 <span class="text-slate-300">•</span>
 <span><?php echo count($subCats); ?> alt</span>
 <?php endif; ?>
 </div>
 <!-- Arrow indicator -->
 <div class="absolute top-3 right-3 w-6 h-6 rounded-lg bg-slate-50 group-hover:bg-indigo-50 flex items-center justify-center text-slate-300 group-hover:text-indigo-600 transition-colors">
 <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
 </div>
 </button>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Subcategories/Products View -->
                <div id="category-content-view" class="hidden">
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-3 xl:grid-cols-4 gap-3" id="category-content-grid">
                        <!-- Content loaded via JavaScript -->
                    </div>
                </div>
                
                <!-- Products Grid (for search) -->
                <div id="all-items-section" class="category-section hidden">
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-3 xl:grid-cols-4 gap-3" id="products-grid">
                        <?php foreach ($menuItems as $item): 
                            $itemId = $item['menu_item_id'] ?? '';
                            $itemName = $item['name'] ?? '';
                            $itemPrice = $item['price'] ?? 0;
                            $itemImage = $item['image_url'] ?? '';
                            $categoryId = $item['category_id'] ?? '';
                            $hasImage = !empty($itemImage) && filter_var($itemImage, FILTER_VALIDATE_URL);
                        ?>
                            <button onclick="handleProductClick('<?php echo htmlspecialchars($itemId); ?>', '<?php echo htmlspecialchars(addslashes($itemName)); ?>', <?php echo $itemPrice; ?>)" 
                                    class="menu-item-btn group bg-white rounded-2xl overflow-hidden text-left hover:shadow-lg transition-all active:scale-[0.98] border border-slate-200 hover:border-indigo-300 flex flex-col h-full"
                                    data-item-id="<?php echo htmlspecialchars($itemId); ?>"
                                    data-item-name="<?php echo strtolower(htmlspecialchars($itemName)); ?>"
                                    data-category="<?php echo htmlspecialchars($categoryId); ?>">
                                <div class="relative w-full aspect-[4/3] bg-slate-100 overflow-hidden">
                                <?php if ($hasImage): ?>
                                    <img src="<?php echo htmlspecialchars($itemImage); ?>" alt="" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" loading="lazy">
                                <?php else: ?>
                                    <div class="w-full h-full flex items-center justify-center bg-gradient-to-br from-slate-100 to-slate-200 text-slate-400">
                                        <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                    </div>
                                <?php endif; ?>
                                </div>
                                <div class="p-3 flex flex-col flex-1">
                                    <h3 class="font-semibold text-sm text-slate-800 mb-1 line-clamp-2"><?php echo htmlspecialchars($itemName); ?></h3>
                                    <div class="text-base font-bold text-indigo-600 mt-auto"><?php echo number_format($itemPrice, 0); ?>₺</div>
                                </div>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Sidebar: Siparişler / Sepet / Geçmiş (garson masa paneli) -->
        <div id="waiter-pos-sidebar" class="waiter-pos-sidebar">
            <div class="lg:hidden flex items-center justify-between px-4 py-3 border-b bg-slate-50 flex-shrink-0">
                <span class="font-bold text-slate-800 text-sm">Masa Paneli</span>
                <button type="button" onclick="closeWaiterPanel()" class="text-xs font-semibold text-slate-500 hover:text-slate-800 px-2 py-1 rounded-lg hover:bg-slate-100">Kapat</button>
            </div>
            <!-- Tabs -->
            <div class="flex border-b flex-shrink-0">
                <button onclick="switchSidebarTab('orders')" id="sidebar-tab-orders" class="flex-1 py-3.5 text-sm font-semibold text-indigo-600 border-b-2 border-indigo-500 transition-all">
                    Siparişler
                </button>
                <button onclick="switchSidebarTab('cart')" id="sidebar-tab-cart" class="flex-1 py-3.5 text-sm font-semibold text-slate-500 hover:text-slate-800 border-b-2 border-transparent transition-all">
                    Sepet
                </button>
                <button onclick="switchSidebarTab('history')" id="sidebar-tab-history" class="flex-1 py-3.5 text-sm font-semibold text-slate-500 hover:text-slate-800 border-b-2 border-transparent transition-all">
                    Geçmiş
                </button>
            </div>
            
            <!-- Orders Tab -->
            <div id="sidebar-orders-content" class="flex-1 flex flex-col overflow-hidden">
                <div class="px-4 py-3 border-b bg-slate-50">
                    <span class="text-sm font-bold text-slate-800">Mevcut Siparişler</span>
                    <span class="text-xs text-slate-500 ml-2" id="orders-count"></span>
                </div>
                <div class="flex-1 p-3 space-y-2 overflow-y-auto" id="table-orders-list">
                    <div class="text-center text-slate-400 py-8 text-sm">Yükleniyor...</div>
                </div>
            </div>
            
            <!-- History Tab -->
            <div id="sidebar-history-content" class="flex-1 flex flex-col overflow-hidden hidden">
                <div class="px-4 py-3 border-b bg-slate-50 flex items-center justify-between gap-2">
                    <div>
                        <span class="text-sm font-bold text-slate-800">Hareket Geçmişi</span>
                        <span class="text-xs text-indigo-600 font-medium ml-2">Bugün</span>
                        <span class="text-xs text-slate-500 ml-1" id="history-count"></span>
                    </div>
                </div>
                <div class="flex-1 p-3 space-y-2 overflow-y-auto" id="table-history-list">
                    <div class="text-center text-slate-400 py-8 text-sm">Yükleniyor...</div>
                </div>
            </div>
            
            <!-- Cart Tab -->
            <div id="sidebar-cart-content" class="flex-1 flex flex-col overflow-hidden hidden">
                <div class="px-4 py-3 border-b bg-slate-50 flex justify-between items-center">
                    <div>
                        <span class="text-sm font-bold text-slate-800">Sepet</span>
                        <span class="text-xs text-slate-500 ml-2" id="cart-item-count">0 ürün</span>
                    </div>
                    <button onclick="clearCart()" class="text-xs text-red-500 hover:text-red-600 font-medium">Temizle</button>
                </div>
                <div class="flex-1 p-3 space-y-2 overflow-y-auto" id="cart-items">
                    <div class="text-center text-slate-400 py-8 text-sm">Sepet boş</div>
                </div>
                <div class="p-4 border-t bg-white">
                    <div class="flex justify-between items-center mb-4">
                        <span class="text-sm font-semibold text-slate-600">Toplam</span>
                        <span class="text-2xl font-black text-slate-800" id="cart-total">0₺</span>
                    </div>
                    <button onclick="sendOrder()" class="w-full bg-indigo-500 hover:bg-indigo-600 text-white py-3.5 rounded-xl font-bold text-sm transition-all active:scale-[0.98]">
                        Siparişi Gönder
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Mobile dock: mevcut sidebar sekmelerine bağlanır -->
    <div id="waiter-mobile-dock" class="fixed bottom-0 left-0 right-0 bg-white border-t z-50 safe-area-bottom shadow-[0_-4px_24px_rgba(15,23,42,0.08)]">
        <div class="grid grid-cols-3 divide-x divide-slate-100">
            <button type="button" onclick="openWaiterPanel('orders')" id="mobile-dock-orders" class="waiter-mobile-dock-btn flex flex-col items-center justify-center gap-0.5 py-2.5 text-[11px] font-bold text-slate-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                Siparişler
            </button>
            <button type="button" onclick="openWaiterPanel('cart')" id="mobile-dock-cart" class="waiter-mobile-dock-btn flex flex-col items-center justify-center gap-0.5 py-2.5 text-[11px] font-bold text-slate-600 relative">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                <span id="mobile-cart-badge">Sepet</span>
                <span id="mobile-cart-total-mini" class="text-[10px] font-black text-indigo-600">0₺</span>
            </button>
            <button type="button" onclick="openWaiterPanel('history')" id="mobile-dock-history" class="waiter-mobile-dock-btn flex flex-col items-center justify-center gap-0.5 py-2.5 text-[11px] font-bold text-slate-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                Geçmiş
            </button>
        </div>
    </div>
    <div id="waiter-pos-sidebar-overlay" class="fixed inset-0 bg-slate-900/50 z-[55] hidden backdrop-blur-sm" onclick="closeWaiterPanel()"></div>
</div>

<!-- Product Customization Modal -->
<div id="product-customization-modal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] flex flex-col overflow-hidden">
        <!-- Modal Header -->
        <div class="flex items-center justify-between px-6 py-4 border-b bg-gradient-to-r from-indigo-50 to-white shrink-0">
            <div>
                <h2 class="text-xl font-bold text-slate-900" id="customization-item-name">Ürün Özelleştirme</h2>
                <p class="text-sm text-slate-600 mt-1" id="customization-item-price">0₺</p>
            </div>
            <button onclick="closeProductCustomizationModal()" class="p-2 hover:bg-slate-100 rounded-lg transition-all">
                <svg class="w-5 h-5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        
        <!-- Modal Content -->
        <div class="flex-1 overflow-y-auto px-6 py-4 space-y-4">
            <input type="hidden" id="customization-item-id">
            <input type="hidden" id="customization-base-price">
            
            <!-- Variants Section -->
            <div id="variants-section" class="space-y-3 border-b pb-4 hidden">
                <label class="block text-sm font-bold text-slate-700">Varyant Seçin</label>
                <div id="variants-list-customization" class="space-y-2">
                    <!-- Variants will be added here -->
                </div>
            </div>
            
            <!-- Removable Ingredients Section -->
            <div id="ingredients-section" class="space-y-3 border-b pb-4 hidden">
                <label class="block text-sm font-bold text-slate-700">Çıkarılabilir Malzemeler</label>
                <p class="text-xs text-slate-500 mb-2">İstediğiniz malzemeleri çıkarabilirsiniz</p>
                <div id="ingredients-list-customization" class="space-y-2">
                    <!-- Ingredients will be added here -->
                </div>
            </div>
            
            <!-- Extras Section -->
            <div id="extras-section" class="space-y-3 border-b pb-4 hidden">
                <label class="block text-sm font-bold text-slate-700">Ekstralar</label>
                <p class="text-xs text-slate-500 mb-2">İstediğiniz ekstraları seçebilirsiniz</p>
                <div id="extras-list-customization" class="space-y-2">
                    <!-- Extras will be added here -->
                </div>
            </div>
            
            <!-- Price Summary -->
            <div class="bg-slate-50 rounded-lg p-4 border-2 border-slate-200">
                <div class="flex justify-between items-center">
                    <span class="font-bold text-slate-700">Toplam Fiyat</span>
                    <span class="font-black text-2xl text-indigo-600" id="customization-total-price">0₺</span>
                </div>
            </div>
        </div>
        
        <!-- Modal Footer -->
        <div class="flex gap-3 px-6 py-4 border-t bg-slate-50 shrink-0">
            <button onclick="closeProductCustomizationModal()" class="flex-1 px-4 py-3 bg-white border-2 border-slate-200 rounded-lg font-bold text-slate-700 hover:bg-slate-50 transition-all">
                İptal
            </button>
            <button onclick="addCustomizedProductToCart()" class="flex-1 px-4 py-3 bg-gradient-to-r from-indigo-500 to-indigo-600 text-white rounded-lg font-bold hover:from-indigo-600 hover:to-indigo-700 transition-all shadow-lg">
                Sepete Ekle
            </button>
        </div>
    </div>
</div>

<!-- Edit Menu Item Modal -->
<div id="edit-item-modal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] flex flex-col overflow-hidden">
        <!-- Modal Header -->
        <div class="flex items-center justify-between px-6 py-4 border-b bg-gradient-to-r from-blue-50 to-white shrink-0">
            <h2 class="text-xl font-bold text-slate-900">Ürünü Düzenle</h2>
            <button onclick="closeEditModal()" class="p-2 hover:bg-slate-100 rounded-lg transition-all">
                <svg class="w-5 h-5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        
        <!-- Modal Content -->
        <div class="flex-1 overflow-y-auto px-6 py-4 space-y-4">
            <input type="hidden" id="edit-item-id">
            
            <!-- Basic Info -->
            <div class="space-y-3">
                <label class="block text-sm font-bold text-slate-700">Ürün Adı</label>
                <input type="text" id="edit-item-name" class="w-full px-4 py-2 border-2 border-slate-200 rounded-lg focus:border-blue-500 focus:outline-none">
            </div>
            
            <div class="space-y-3">
                <label class="block text-sm font-bold text-slate-700">Fiyat (₺)</label>
                <input type="number" id="edit-item-price" step="0.01" class="w-full px-4 py-2 border-2 border-slate-200 rounded-lg focus:border-blue-500 focus:outline-none">
            </div>
            
            <!-- Ingredients Section -->
            <div class="space-y-3 border-t pt-4">
                <div class="flex items-center justify-between">
                    <label class="block text-sm font-bold text-slate-700">Malzemeler</label>
                    <button onclick="addIngredient()" class="px-3 py-1.5 bg-blue-500 text-white rounded-lg text-xs font-bold hover:bg-blue-600 transition-all">
                        + Ekle
                    </button>
                </div>
                <div id="ingredients-list" class="space-y-2">
                    <!-- Ingredients will be added here -->
                </div>
            </div>
            
            <!-- Variants Section -->
            <div class="space-y-3 border-t pt-4">
                <div class="flex items-center justify-between">
                    <label class="block text-sm font-bold text-slate-700">Varyantlar</label>
                    <button onclick="addVariant()" class="px-3 py-1.5 bg-blue-500 text-white rounded-lg text-xs font-bold hover:bg-blue-600 transition-all">
                        + Ekle
                    </button>
                </div>
                <div id="variants-list" class="space-y-2">
                    <!-- Variants will be added here -->
                </div>
            </div>
            
            <!-- Extras Section -->
            <div class="space-y-3 border-t pt-4">
                <div class="flex items-center justify-between">
                    <label class="block text-sm font-bold text-slate-700">Ekstralar</label>
                    <button onclick="addExtra()" class="px-3 py-1.5 bg-blue-500 text-white rounded-lg text-xs font-bold hover:bg-blue-600 transition-all">
                        + Ekle
                    </button>
                </div>
                <div id="extras-list" class="space-y-2">
                    <!-- Extras will be added here -->
                </div>
            </div>
        </div>
        
        <!-- Modal Footer -->
        <div class="flex gap-3 px-6 py-4 border-t bg-slate-50 shrink-0">
            <button onclick="closeEditModal()" class="flex-1 px-4 py-2 bg-white border-2 border-slate-200 rounded-lg font-bold text-slate-700 hover:bg-slate-50 transition-all">
                İptal
            </button>
            <button onclick="saveMenuItem()" class="flex-1 px-4 py-2 bg-blue-500 text-white rounded-lg font-bold hover:bg-blue-600 transition-all">
                Kaydet
            </button>
        </div>
    </div>
</div>

<!-- Move Table Modal -->
<div id="move-table-modal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
    <div class="bg-white rounded-3xl shadow-2xl max-w-3xl w-full max-h-[90vh] overflow-hidden flex flex-col animate-slide-up">
        <!-- Header -->
        <div class="p-5 sm:p-6 border-b border-slate-100 bg-white">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 bg-indigo-100 rounded-2xl flex items-center justify-center">
                        <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-xl sm:text-2xl font-black text-slate-900">Masa Taşı</h2>
                        <p class="text-sm text-slate-500 mt-0.5" id="move-table-from-info">Masa seçiliyor...</p>
                    </div>
                </div>
                <button onclick="closeMoveTableModal()" class="p-3 hover:bg-slate-100 rounded-2xl transition-all touch-manipulation" aria-label="Kapat">
                    <svg class="w-6 h-6 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            
            <div id="move-table-no-orders-warning" class="hidden mt-4 p-3 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 text-sm font-medium">
                Bu masada taşınacak aktif sipariş yok. Masa taşımak için önce sipariş oluşturun.
            </div>

            <!-- Filter Tabs -->
            <div class="flex gap-2 mt-4">
                <button onclick="filterMoveTablesByStatus('free')" id="move-filter-free" class="px-4 py-2 rounded-xl text-sm font-bold transition-all bg-emerald-500 text-white">
                    Boş Masalar
                </button>
                <button onclick="filterMoveTablesByStatus('all')" id="move-filter-all" class="px-4 py-2 rounded-xl text-sm font-bold transition-all bg-slate-100 text-slate-600 hover:bg-slate-200">
                    Tüm Masalar
                </button>
            </div>
        </div>
        
        <!-- Tables List -->
        <div class="flex-1 overflow-y-auto p-4 sm:p-6 bg-slate-50/50">
            <div id="tables-list-move" class="space-y-6">
                <!-- Tables grouped by zones will be loaded here -->
            </div>
        </div>
        
        <!-- Footer Info -->
        <div class="p-4 bg-slate-100/80 border-t border-slate-200">
            <p class="text-xs text-center text-slate-500">
                <span class="inline-flex items-center gap-1.5">
                    <span class="w-2 h-2 bg-emerald-500 rounded-full"></span>
                    Boş masalara tıklayarak içeriği taşıyabilirsiniz
                </span>
            </p>
        </div>
    </div>
</div>

<!-- Merkezi Bildirim Sistemi - ÖNCE YÜKLENMELİ ve SYNCHRONOUS -->
<script src="<?php echo $baseUrl; ?>/assets/js/notification.js"></script>
<script>
// DEBUG: Table Info
console.log('=== POS SCREEN DEBUG ===');
console.log('Table ID:', <?php echo json_encode($tableId ?? 'NO_TABLE_ID'); ?>);
console.log('Table Object:', <?php echo json_encode($table ?? null); ?>);
console.log('Base URL:', <?php echo json_encode($baseUrl); ?>);
console.log('======================');

// CRITICAL: Ensure NotificationManager is initialized before using it
(function() {
    'use strict';
    
    // Wait for NotificationManager to be available
    function waitForNotificationManager(callback, maxAttempts = 50) {
        if (window.NotificationManager && typeof window.NotificationManager.show === 'function') {
            callback();
        } else if (maxAttempts > 0) {
            setTimeout(() => waitForNotificationManager(callback, maxAttempts - 1), 100);
        } else {
            console.error('NotificationManager failed to load after waiting');
            callback(); // Continue anyway
        }
    }
    
    waitForNotificationManager(function() {
        console.log('NotificationManager ready');
    });
})();
</script>

<script>
const baseUrl = '<?php echo $baseUrl; ?>';
// CRITICAL: Get tableId from multiple sources
let tableId = '<?php echo htmlspecialchars($tableId); ?>';
const csrfToken = '<?php echo htmlspecialchars($csrfToken); ?>';
const requiresApprovalForOrderEdit = <?php echo !empty($requiresApprovalForOrderEdit) ? 'true' : 'false'; ?>;
const staffShowDeleteReduceButtons = <?php echo !empty($staffShowDeleteReduceButtons) ? 'true' : 'false'; ?>;
const managerShowDeleteReduceButtons = <?php echo !empty($managerShowDeleteReduceButtons) ? 'true' : 'false'; ?>;
const orderEditApprovalEnabled = <?php echo !empty($orderEditApprovalEnabled) ? 'true' : 'false'; ?>;
// Tek mantık: Onay açıkken sadece ilgili role'ün toggle'ı açıksa butonlar görünsün (yönetici → manager toggle, personel → staff toggle)
const showDeleteReduceButtons = orderEditApprovalEnabled && ((requiresApprovalForOrderEdit && staffShowDeleteReduceButtons) || (!requiresApprovalForOrderEdit && managerShowDeleteReduceButtons));
let cart = [];
let selectedCategory = 'all';
let searchQuery = '';
let isCompactMode = false;
let currentMoveTableId = null;
let currentTableData = null;

// PERFORMANCE: Pass category and menu item data to JavaScript
window.categoriesData = <?php echo json_encode($categories, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
window.menuItemsData = <?php echo json_encode($menuItems, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
window.parentCategoriesData = <?php echo json_encode($parentCategories, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
window.subCategoriesByParentData = <?php echo json_encode($subCategoriesByParent, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
window.itemsByCategoryData = <?php echo json_encode($itemsByCategory, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
<?php
$categoryIconMetaForJs = [];
foreach (posCategoryIconLibrary() as $iconKey => $iconMeta) {
    $categoryIconMetaForJs[$iconKey] = [
        'gradient' => $iconMeta['gradient'],
        'text' => $iconMeta['text'],
        'emoji' => $iconMeta['emoji'],
        'label' => $iconMeta['label'],
        'svgPath' => posCategoryIconSvg($iconKey),
    ];
}
?>
window.categoryIconMeta = <?php echo json_encode($categoryIconMetaForJs, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

function resolveCategoryVisualJs(category) {
    const imageUrl = (category?.image_url || '').trim();
    if (imageUrl) {
        return { type: 'image', image_url: imageUrl };
    }
    const iconKey = (category?.icon || '').trim();
    if (iconKey && window.categoryIconMeta && window.categoryIconMeta[iconKey]) {
        return { type: 'icon', icon: iconKey, ...window.categoryIconMeta[iconKey] };
    }
    return { type: 'icon', icon: 'plate', ...(window.categoryIconMeta?.plate || {}) };
}

function renderCategoryCardHtml(category, countText, onClickJs) {
    const visual = resolveCategoryVisualJs(category);
    const safeName = escapeHtml(category.name || '');
    let visualHtml = '';
    if (visual.type === 'image') {
        visualHtml = `<div class="relative w-full aspect-[4/3] mb-3 rounded-xl overflow-hidden bg-slate-100 shadow-sm"><img src="${escapeHtml(visual.image_url)}" alt="" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" loading="lazy"></div>`;
    } else {
        visualHtml = `<div class="relative w-12 h-12 sm:w-14 sm:h-14 mb-3 rounded-xl sm:rounded-2xl bg-gradient-to-br ${visual.gradient} flex items-center justify-center shadow-md group-hover:scale-110 transition-all duration-300"><svg class="w-7 h-7 sm:w-8 sm:h-8 ${visual.text} drop-shadow" fill="none" stroke="currentColor" viewBox="0 0 24 24">${visual.svgPath || ''}</svg></div>`;
    }
    return `
        <button onclick="${onClickJs}" class="group relative bg-white rounded-2xl p-3 sm:p-4 text-left hover:shadow-lg transition-all active:scale-[0.97] border border-slate-200 hover:border-indigo-300 overflow-hidden">
            ${visualHtml}
            <h3 class="font-bold text-sm sm:text-base text-slate-900 mb-1 line-clamp-2 tracking-tight">${safeName}</h3>
            <div class="text-xs text-slate-500 font-medium">${countText}</div>
        </button>
    `;
}

function renderProductCardHtml(item, categoryId) {
    const itemId = item.menu_item_id || '';
    const itemName = item.name || '';
    const itemPrice = item.price || 0;
    const itemImage = (item.image_url || '').trim();
    const hasImage = itemImage !== '';
    const imageBlock = hasImage
        ? `<img src="${escapeHtml(itemImage)}" alt="" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" loading="lazy">`
        : `<div class="w-full h-full flex items-center justify-center bg-gradient-to-br from-slate-100 to-slate-200 text-slate-400"><svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg></div>`;

    return `
        <button onclick="handleProductClick('${itemId}', '${itemName.replace(/'/g, "\\'")}', ${itemPrice})"
                class="group bg-white rounded-2xl overflow-hidden text-left hover:shadow-lg transition-all active:scale-[0.98] border border-slate-200 hover:border-indigo-300 flex flex-col h-full"
                data-item-id="${itemId}"
                data-item-name="${itemName.toLowerCase()}"
                data-category="${categoryId}">
            <div class="relative w-full aspect-[4/3] bg-slate-100 overflow-hidden">${imageBlock}</div>
            <div class="p-3 flex flex-col flex-1">
                <h3 class="font-semibold text-sm text-slate-800 mb-1 line-clamp-2">${escapeHtml(itemName)}</h3>
                <div class="text-base font-bold text-indigo-600 mt-auto">${parseFloat(itemPrice).toFixed(0)}₺</div>
            </div>
        </button>
    `;
}

function getMenuItemImageUrl(menuItemId) {
    const item = (window.menuItemsData || []).find(i => i.menu_item_id === menuItemId);
    return item && item.image_url ? String(item.image_url).trim() : '';
}

// CRITICAL: Validate and fix tableId on page load
(function() {
    // If tableId is empty, try to get from URL
    if (!tableId || tableId.trim() === '') {
        const urlParams = new URLSearchParams(window.location.search);
        const urlTableId = urlParams.get('table') || urlParams.get('table_id');
        if (urlTableId) {
            tableId = urlTableId;
            console.warn('Table ID was missing, using from URL:', tableId);
        } else {
            // Try to get from table data
            const tableData = <?php echo json_encode($table ?? null); ?>;
            if (tableData && tableData.table_id) {
                tableId = tableData.table_id;
                console.warn('Table ID was missing, using from table data:', tableId);
            } else {
                console.error('CRITICAL: Table ID is completely missing!', {
                    tableId: tableId,
                    table: tableData,
                    url: window.location.href
                });
            }
        }
    }
    
    // Log final tableId for debugging
    console.log('Waiter POS initialized with tableId:', tableId);
})();

currentTableData = {
    table_id: tableId,
    name: <?php echo json_encode($tableName, JSON_UNESCAPED_UNICODE); ?>,
    zone: <?php echo json_encode($tableZoneName, JSON_UNESCAPED_UNICODE); ?>,
    zone_name: <?php echo json_encode($tableZoneName, JSON_UNESCAPED_UNICODE); ?>
};

// Onay/Red geri bildirimi - garson talep ettiği silme/azaltma işleminin sonucunu görsün
(function approvalFeedbackPolling() {
    const apiPrefix = (window.location.pathname || '').indexOf('/qodmin/') !== -1 ? 'api/qodmin' : 'api/business';
    let lastSince = Math.floor(Date.now() / 1000) - 30; // Son 30 saniye
    const seenIds = new Set(JSON.parse(sessionStorage.getItem('approvalFeedbackSeen') || '[]'));
    
    function poll() {
        fetch(`${baseUrl}/${apiPrefix}/approval-feedback?since=${lastSince}`)
            .then(r => r.json())
            .then(data => {
                if (!data.success || !Array.isArray(data.feedback)) return;
                let maxProcessed = lastSince;
                for (const f of data.feedback) {
                    if (seenIds.has(f.approval_id)) continue;
                    seenIds.add(f.approval_id);
                    const processedAt = f.processed_at ? new Date(f.processed_at).getTime() / 1000 : 0;
                    if (processedAt > maxProcessed) maxProcessed = processedAt;
                    const itemName = f.item_name || 'Ürün';
                    if (f.status === 'APPROVED') {
                        if (window.NotificationManager && typeof window.NotificationManager.success === 'function') {
                            window.NotificationManager.success('Silme işlemi onaylandı');
                        }
                    } else if (f.status === 'REJECTED') {
                        const msg = (f.rejected_reason && f.rejected_reason.trim())
                            ? `Bu sebeple istediğiniz iptal edildi: ${f.rejected_reason.trim()}`
                            : 'İsteğiniz iptal edildi';
                        if (window.NotificationManager && typeof window.NotificationManager.error === 'function') {
                            window.NotificationManager.error(msg);
                        }
                    }
                }
                if (maxProcessed > lastSince) lastSince = Math.floor(maxProcessed) + 1;
                // Keep seen set bounded
                if (seenIds.size > 100) {
                    const arr = Array.from(seenIds).slice(-50);
                    seenIds.clear();
                    arr.forEach(id => seenIds.add(id));
                }
                sessionStorage.setItem('approvalFeedbackSeen', JSON.stringify(Array.from(seenIds)));
            })
            .catch(() => {});
    }
    poll();
    setInterval(poll, 5000);
})();

// Normalize string for search (Turkish locale for İ/i, I/ı)
function normalizeSearchText(str) {
    return (str || '').toLocaleLowerCase('tr-TR').trim();
}

// Search products and categories
function searchProducts(query) {
    searchQuery = normalizeSearchText(query);
    const clearBtn = document.getElementById('clear-search');
    
    if (searchQuery) {
        clearBtn?.classList.remove('hidden');
    } else {
        clearBtn?.classList.add('hidden');
    }
    
    filterProductsAndCategories();
}

// Handle Enter key press in search
function handleSearchKeyPress(event) {
    if (event.key === 'Enter') {
        event.preventDefault();
        performSearch();
    }
}

// Perform search
function performSearch() {
    const input = document.getElementById('search-input');
    if (input) {
        searchProducts(input.value);
    }
}

// Filter products and categories
function filterProductsAndCategories() {
    const mainView = document.getElementById('main-categories-view');
    const contentView = document.getElementById('category-content-view');
    const allItemsView = document.getElementById('all-items-section');
    
    // CRITICAL: Show search results container when user is searching, otherwise show main/category view
    if (searchQuery && searchQuery.length > 0) {
        if (mainView) mainView.classList.add('hidden');
        if (contentView) contentView.classList.add('hidden');
        if (allItemsView) allItemsView.classList.remove('hidden');
    } else {
        if (allItemsView) allItemsView.classList.add('hidden');
        if (currentView === 'main') {
            if (mainView) mainView.classList.remove('hidden');
            if (contentView) contentView.classList.add('hidden');
        } else {
            if (mainView) mainView.classList.add('hidden');
            if (contentView) contentView.classList.remove('hidden');
        }
    }
    
    const products = document.querySelectorAll('.menu-item-btn');
    const categoryButtons = document.querySelectorAll('.category-btn');
    let visibleCount = 0;
    let matchingCategories = new Set();
    
    // First, find matching categories
    if (searchQuery) {
        categoryButtons.forEach(btn => {
            const categoryName = normalizeSearchText(btn.textContent || '');
            if (categoryName.includes(searchQuery)) {
                const categoryId = btn.dataset.category;
                if (categoryId) {
                    matchingCategories.add(categoryId);
                }
            }
        });
    }
    
    // Filter products
    products.forEach(product => {
        const itemName = product.dataset.itemName || '';
        const itemCategory = product.dataset.category || '';
        
        // When searching, show results from all categories; otherwise respect category filter
        let categoryMatch = searchQuery ? true : (selectedCategory === 'all' || itemCategory === selectedCategory);
        
        // Check search filter - match product name or category
        let searchMatch = true;
        if (searchQuery) {
            const nameMatch = normalizeSearchText(itemName).includes(searchQuery);
            const catMatch = matchingCategories.has(itemCategory);
            searchMatch = nameMatch || catMatch;
        }
        
        if (categoryMatch && searchMatch) {
            product.style.display = '';
            visibleCount++;
            
            // Highlight search terms
            if (searchQuery) {
                const nameElement = product.querySelector('h3');
                if (nameElement) {
                    const originalText = nameElement.textContent;
                    const escaped = searchQuery.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
                    const regex = new RegExp(`(${escaped})`, 'gi');
                    nameElement.innerHTML = originalText.replace(regex, '<span class="highlight">$1</span>');
                }
            }
        } else {
            product.style.display = 'none';
        }
    });
    
    // Highlight matching categories
    if (searchQuery) {
        categoryButtons.forEach(btn => {
            const categoryName = normalizeSearchText(btn.textContent || '');
            if (categoryName.includes(searchQuery)) {
                btn.classList.add('ring-2', 'ring-indigo-400');
            } else {
                btn.classList.remove('ring-2', 'ring-indigo-400');
            }
        });
    } else {
        categoryButtons.forEach(btn => {
            btn.classList.remove('ring-2', 'ring-indigo-400');
        });
    }
}

function clearSearch() {
    document.getElementById('search-input').value = '';
    searchQuery = '';
    document.getElementById('clear-search')?.classList.add('hidden');
    filterProducts();
}

// Legacy function - redirects to new function
function filterProducts() {
    filterProductsAndCategories();
}

// Navigation state for card-based structure
let navigationStack = []; // Stack to track navigation history
let currentView = 'main'; // 'main', 'category', 'subcategory'

// Show category content (subcategories or products)
function showCategoryContent(categoryId, categoryName) {
    // Add to navigation stack
    navigationStack.push({
        view: currentView,
        categoryId: selectedCategory,
        categoryName: categoryName
    });
    
    currentView = 'category';
    selectedCategory = categoryId;
    
    // Show breadcrumb
    const breadcrumbNav = document.getElementById('breadcrumb-nav');
    const breadcrumbText = document.getElementById('breadcrumb-text');
    if (breadcrumbNav) breadcrumbNav.style.display = 'flex';
    if (breadcrumbText) breadcrumbText.textContent = categoryName;
    
    // Hide main categories view
    const mainView = document.getElementById('main-categories-view');
    const contentView = document.getElementById('category-content-view');
    const allItemsView = document.getElementById('all-items-section');
    
    if (mainView) mainView.classList.add('hidden');
    if (allItemsView) allItemsView.classList.add('hidden');
    if (contentView) {
        contentView.classList.remove('hidden');
        
        // Load content for this category
        loadCategoryContent(categoryId);
    }
}

// Load category content (subcategories or products)
function loadCategoryContent(categoryId) {
    const contentGrid = document.getElementById('category-content-grid');
    if (!contentGrid) return;
    
    // Get category data from PHP variables (passed via data attributes or global)
    const categories = window.categoriesData || [];
    const menuItems = window.menuItemsData || [];
    
    // Find category
    const category = categories.find(cat => cat.category_id === categoryId);
    if (!category) return;
    
    // Get subcategories (use pre-organized data if available)
    let subcategories = (window.subCategoriesByParentData && window.subCategoriesByParentData[categoryId]) ? window.subCategoriesByParentData[categoryId] : [];
    if (subcategories.length === 0) {
        // Fallback: filter from all categories
        subcategories = categories.filter(cat => cat.parent_id === categoryId);
    }
    
    // Get direct items (use pre-organized data if available)
    let directItems = (window.itemsByCategoryData && window.itemsByCategoryData[categoryId]) ? window.itemsByCategoryData[categoryId] : [];
    if (directItems.length === 0) {
        // Fallback: filter from all menu items
        directItems = menuItems.filter(item => item.category_id === categoryId);
    }
    
    let html = '';
    
    // If has subcategories, show ALL subcategory cards (no filtering)
    if (subcategories.length > 0) {
        subcategories.forEach(subCat => {
            // Use pre-organized data if available
            let subCatItems = (window.itemsByCategoryData && window.itemsByCategoryData[subCat.category_id]) ? window.itemsByCategoryData[subCat.category_id] : [];
            if (subCatItems.length === 0) {
                // Fallback: filter from all menu items
                subCatItems = menuItems.filter(item => item.category_id === subCat.category_id);
            }
            
            // Also check if this subcategory has its own subcategories
            let subSubcategories = (window.subCategoriesByParentData && window.subCategoriesByParentData[subCat.category_id]) ? window.subCategoriesByParentData[subCat.category_id] : [];
            if (subSubcategories.length === 0) {
                subSubcategories = categories.filter(cat => cat.parent_id === subCat.category_id);
            }
            
            // Calculate total items including items in sub-subcategories
            let totalItemCount = subCatItems.length;
            subSubcategories.forEach(subSubCat => {
                let subSubItems = (window.itemsByCategoryData && window.itemsByCategoryData[subSubCat.category_id]) ? window.itemsByCategoryData[subSubCat.category_id] : [];
                if (subSubItems.length === 0) {
                    subSubItems = menuItems.filter(item => item.category_id === subSubCat.category_id);
                }
                totalItemCount += subSubItems.length;
            });
            
            // ALWAYS show subcategory - no filtering based on item count
            let countText = '';
            if (totalItemCount > 0) {
                countText = `${totalItemCount} ürün`;
            } else if (subSubcategories.length > 0) {
                countText = `${subSubcategories.length} alt kategori`;
            } else {
                countText = '0 ürün';
            }
            
            html += renderCategoryCardHtml(
                subCat,
                countText,
                `showCategoryContent('${subCat.category_id}', '${subCat.name.replace(/'/g, "\\'")}')`
            );
        });
    }
    
    // Show direct products if no subcategories or if there are direct products
    if (subcategories.length === 0 || directItems.length > 0) {
        directItems.forEach(item => {
            html += renderProductCardHtml(item, categoryId);
        });
    }
    
    contentGrid.innerHTML = html || '<div class="col-span-full text-center py-12 text-slate-400">Bu kategoride içerik bulunamadı</div>';
}

// Go back in navigation
function goBack() {
    if (navigationStack.length > 0) {
        const previous = navigationStack.pop();
        currentView = previous.view;
        selectedCategory = previous.categoryId || 'all';
        
        // Update breadcrumb
        if (navigationStack.length === 0) {
            const breadcrumbNav = document.getElementById('breadcrumb-nav');
            if (breadcrumbNav) breadcrumbNav.style.display = 'none';
            
            // Show main categories view
            const mainView = document.getElementById('main-categories-view');
            const contentView = document.getElementById('category-content-view');
            
            if (mainView) mainView.classList.remove('hidden');
            if (contentView) contentView.classList.add('hidden');
        } else {
            const lastNav = navigationStack[navigationStack.length - 1];
            const breadcrumbText = document.getElementById('breadcrumb-text');
            if (breadcrumbText) breadcrumbText.textContent = lastNav.categoryName || '';
            
            // Load previous category content
            loadCategoryContent(previous.categoryId);
        }
    } else {
        // Go to main view
        currentView = 'main';
        selectedCategory = 'all';
        
        const breadcrumbNav = document.getElementById('breadcrumb-nav');
        const mainView = document.getElementById('main-categories-view');
        const contentView = document.getElementById('category-content-view');
        
        if (breadcrumbNav) breadcrumbNav.style.display = 'none';
        if (mainView) mainView.classList.remove('hidden');
        if (contentView) contentView.classList.add('hidden');
    }
}

// Select category (legacy function - kept for compatibility)
function selectCategory(categoryId) {
    selectedCategory = categoryId;
    
    // Update desktop sidebar buttons
    document.querySelectorAll('#categories-sidebar-container .category-btn').forEach(btn => {
        const btnCategory = btn.dataset.category || '';
        const isParent = !btn.dataset.parent;
        
        if (btnCategory === categoryId) {
            // Selected button
            if (categoryId === 'all') {
                btn.className = 'category-btn w-full px-3 py-2 rounded-lg font-bold text-xs transition-all bg-gradient-to-r from-blue-500 to-blue-600 text-white shadow-md text-left';
            } else {
                btn.className = 'category-btn w-full px-3 py-2 rounded-lg font-semibold text-xs transition-all bg-gradient-to-r from-indigo-500 to-indigo-600 text-white shadow-md border border-indigo-400 text-left' + (isParent ? '' : ' ml-3');
            }
        } else {
            // Unselected button
            if (btnCategory === 'all') {
                btn.className = 'category-btn w-full px-3 py-2 rounded-lg font-bold text-xs transition-all bg-gradient-to-r from-blue-500 to-blue-600 text-white shadow-md text-left';
            } else {
                btn.className = 'category-btn w-full px-3 py-2 rounded-lg font-semibold text-xs transition-all bg-white hover:bg-indigo-50 text-slate-700 hover:text-indigo-600 border border-slate-200 hover:border-indigo-300 text-left' + (isParent ? '' : ' ml-3 opacity-90');
            }
        }
    });
    
    // Update mobile category buttons
    document.querySelectorAll('#categories-container .category-btn').forEach(btn => {
        const btnCategory = btn.dataset.category || '';
        const isParent = !btn.dataset.parent;
        
        if (btnCategory === categoryId) {
            // Selected button
            if (categoryId === 'all') {
                btn.className = 'category-btn px-4 py-2 rounded-lg font-bold text-xs whitespace-nowrap transition-all bg-gradient-to-r from-blue-500 to-blue-600 text-white shadow-md';
            } else {
                btn.className = 'category-btn px-3.5 py-2 rounded-lg font-semibold text-xs whitespace-nowrap transition-all bg-gradient-to-r from-indigo-500 to-indigo-600 text-white shadow-md border border-indigo-400';
            }
        } else {
            // Unselected button
            if (btnCategory === 'all') {
                btn.className = 'category-btn px-4 py-2 rounded-lg font-bold text-xs whitespace-nowrap transition-all bg-gradient-to-r from-blue-500 to-blue-600 text-white shadow-md';
            } else {
                btn.className = 'category-btn px-3.5 py-2 rounded-lg font-semibold text-xs whitespace-nowrap transition-all bg-white hover:bg-indigo-50 text-slate-700 hover:text-indigo-600 border border-slate-200 hover:border-indigo-300' + (isParent ? '' : ' opacity-90');
            }
        }
    });
    
    filterProductsAndCategories();
}

// Toggle compact mode
function toggleCompactMode() {
    isCompactMode = !isCompactMode;
    const grid = document.getElementById('products-grid');
    if (isCompactMode) {
        grid?.classList.add('grid-compact');
    } else {
        grid?.classList.remove('grid-compact');
    }
}

// Handle product click - check if customization needed
async function handleProductClick(itemId, itemName, itemPrice) {
    try {
        // Fetch product details to check for variants, removable ingredients, extras
        const response = await fetch(`${baseUrl}/api/menu/item?id=${itemId}`, {
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin'
        });
        
        if (!response.ok) {
            // If API fails, add directly to cart
            addToCart(itemId, itemName, itemPrice);
            return;
        }
        
        const data = await response.json();
        const menuItem = data.menu_item || data;
        
        // Parse ingredients - handle multiple formats
        let ingredients = [];
        if (Array.isArray(menuItem.ingredients)) {
            ingredients = menuItem.ingredients;
        } else if (typeof menuItem.ingredients === 'string' && menuItem.ingredients.trim() !== '') {
            try {
                const parsed = JSON.parse(menuItem.ingredients);
                ingredients = Array.isArray(parsed) ? parsed : [];
            } catch (e) {
                ingredients = [];
            }
        }
        
        // Check if product has variants
        let hasVariants = false;
        if (menuItem.has_variants == 1 || menuItem.has_variants === '1') {
            hasVariants = true;
        } else if (Array.isArray(menuItem.variants) && menuItem.variants.length > 0) {
            hasVariants = true;
        }
        
        // If has variants but not loaded, try to fetch
        if (hasVariants && (!menuItem.variants || menuItem.variants.length === 0)) {
            try {
                const variantResponse = await fetch(`${baseUrl}/api/product-variants?product_id=${itemId}`, {
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin'
                });
                if (variantResponse.ok) {
                    const variantData = await variantResponse.json();
                    if (variantData.success && Array.isArray(variantData.variants) && variantData.variants.length > 0) {
                        menuItem.variants = variantData.variants;
                        hasVariants = true;
                    } else {
                        hasVariants = false;
                    }
                }
            } catch (e) {
                console.warn('Could not load variants:', e);
            }
        }
        
        // Check for removable ingredients - check if any ingredient has removable property
        let hasRemovableIngredients = false;
        if (Array.isArray(ingredients) && ingredients.length > 0) {
            hasRemovableIngredients = ingredients.some(ing => {
                if (typeof ing === 'string') {
                    // If it's a string, assume it might be removable (common case)
                    return true;
                }
                if (typeof ing === 'object' && ing !== null) {
                    return ing.is_removable === true || 
                           ing.is_removable === 1 || 
                           ing.is_removable === '1' ||
                           ing.removable === true ||
                           ing.removable === 1 ||
                           ing.removable === '1';
                }
                return false;
            });
        }
        
        // Check for extras
        let hasExtras = false;
        let extras = [];
        if (Array.isArray(menuItem.extras) && menuItem.extras.length > 0) {
            extras = menuItem.extras;
            hasExtras = true;
        } else if (Array.isArray(menuItem.available_extras) && menuItem.available_extras.length > 0) {
            extras = menuItem.available_extras;
            hasExtras = true;
        } else if (typeof menuItem.extras === 'string' && menuItem.extras.trim() !== '') {
            try {
                const parsed = JSON.parse(menuItem.extras);
                if (Array.isArray(parsed) && parsed.length > 0) {
                    extras = parsed;
                    hasExtras = true;
                }
            } catch (e) {}
        } else if (typeof menuItem.available_extras === 'string' && menuItem.available_extras.trim() !== '') {
            try {
                const parsed = JSON.parse(menuItem.available_extras);
                if (Array.isArray(parsed) && parsed.length > 0) {
                    extras = parsed;
                    hasExtras = true;
                }
            } catch (e) {}
        }
        
        // Update menuItem with parsed data
        menuItem.ingredients = ingredients;
        menuItem.extras = extras;
        
        // If product has customization options, open modal
        if (hasVariants || hasRemovableIngredients || hasExtras) {
            openProductCustomizationModal(itemId, itemName, itemPrice, menuItem);
        } else {
            // No customization needed, add directly to cart
            addToCart(itemId, itemName, itemPrice);
        }
    } catch (error) {
        console.error('Error checking product details:', error);
        // On error, add directly to cart
        addToCart(itemId, itemName, itemPrice);
    }
}

// Add to cart - Using centralized notification system
function addToCart(itemId, itemName, itemPrice, customizations = {}) {
    // Normalize customizations for comparison
    const normalizedCustomizations = {
        variant: customizations.variant ?? null,
        removed_ingredients: Array.isArray(customizations.removed_ingredients) ? customizations.removed_ingredients : [],
        removed_ingredient_names: Array.isArray(customizations.removed_ingredient_names) ? customizations.removed_ingredient_names : [],
        extras: Array.isArray(customizations.extras) ? customizations.extras : [],
        extra_details: Array.isArray(customizations.extra_details) ? customizations.extra_details : []
    };
    
    const existingItem = cart.find(item => {
        // Check if same item with same customizations
        if (item.menu_item_id !== itemId) return false;
        const itemCustomizations = item.customizations || {};
        // Compare core customization fields (variant, removed_ingredients, extras)
        const itemCompare = {
            variant: itemCustomizations.variant ?? null,
            removed_ingredients: Array.isArray(itemCustomizations.removed_ingredients) ? itemCustomizations.removed_ingredients : [],
            extras: Array.isArray(itemCustomizations.extras) ? itemCustomizations.extras : []
        };
        const newCompare = {
            variant: normalizedCustomizations.variant,
            removed_ingredients: normalizedCustomizations.removed_ingredients,
            extras: normalizedCustomizations.extras
        };
        
        return JSON.stringify(itemCompare) === JSON.stringify(newCompare);
    });
    
    if (existingItem) {
        existingItem.quantity++;
        // Update names in case they were missing before
        if (normalizedCustomizations.removed_ingredient_names.length > 0) {
            existingItem.customizations.removed_ingredient_names = normalizedCustomizations.removed_ingredient_names;
        }
        if (normalizedCustomizations.extra_details.length > 0) {
            existingItem.customizations.extra_details = normalizedCustomizations.extra_details;
        }
    } else {
        const menuItem = (window.menuItemsData || []).find(i => i.menu_item_id === itemId);
        cart.push({
            menu_item_id: itemId,
            name: itemName,
            price: itemPrice,
            quantity: 1,
            image_url: menuItem?.image_url || '',
            customizations: normalizedCustomizations
        });
    }
    updateCart();
    
    if (isWaiterDrawerMode()) {
        openWaiterPanel('cart');
    }
    
    // Show success notification using centralized system
    if (window.NotificationManager && typeof window.NotificationManager.success === 'function') {
        window.NotificationManager.success(`${itemName} sepete eklendi`);
    } else if (window.Toast && typeof window.Toast.success === 'function') {
        window.Toast.success(`${itemName} sepete eklendi`);
    }
}

// Remove from cart
function removeFromCart(cartItemKey) {
    // Find item by index (cartItemKey is the index in the cart array)
    const index = parseInt(cartItemKey);
    if (index >= 0 && index < cart.length) {
        cart.splice(index, 1);
        updateCart();
    }
}

// Update quantity
function updateQuantity(cartItemKey, change) {
    // Find item by index (cartItemKey is the index in the cart array)
    const index = parseInt(cartItemKey);
    if (index >= 0 && index < cart.length) {
        const item = cart[index];
        item.quantity += change;
        if (item.quantity <= 0) {
            removeFromCart(cartItemKey);
        } else {
            updateCart();
        }
    }
}

// Clear cart - Using centralized notification system
async function clearCart() {
    if (cart.length === 0) return;
    
    // Use centralized notification system
    let confirmed = false;
    
    if (window.NotificationManager && typeof window.NotificationManager.confirm === 'function') {
        confirmed = await window.NotificationManager.confirm('Sepeti temizlemek istediğinizden emin misiniz?', 'Sepeti Temizle');
    } else if (window.Toast && typeof window.Toast.confirm === 'function') {
        confirmed = await window.Toast.confirm('Sepeti temizlemek istediğinizden emin misiniz?');
    } else {
        confirmed = confirm('Sepeti temizlemek istediğinizden emin misiniz?');
    }
    
    if (!confirmed) return;
    
    cart = [];
    updateCart();
    
    // Show success notification
    if (window.NotificationManager && typeof window.NotificationManager.success === 'function') {
        window.NotificationManager.success('Sepet temizlendi');
    } else if (window.Toast && typeof window.Toast.success === 'function') {
        window.Toast.success('Sepet temizlendi');
    }
}

// Mobile / drawer masa paneli (mevcut sidebar sekmelerini kullanır)
function isWaiterDrawerMode() {
    const root = document.getElementById('waiter-pos');
    return !!(root && root.classList.contains('waiter-pos--drawer-mode'));
}

function syncWaiterPosLayoutMode() {
    const root = document.getElementById('waiter-pos');
    const dock = document.getElementById('waiter-mobile-dock');
    const menu = document.getElementById('menu-items-container');
    if (!root || !dock) return;

    const dockVisible = window.getComputedStyle(dock).display !== 'none';
    root.classList.toggle('waiter-pos--drawer-mode', dockVisible);
    if (menu) {
        menu.classList.toggle('waiter-pos-has-dock', dockVisible);
    }
    if (!dockVisible) {
        closeWaiterPanel(false);
    }
}

function updateMobileDockHighlight(tab) {
    ['orders', 'cart', 'history'].forEach(name => {
        const btn = document.getElementById(`mobile-dock-${name}`);
        if (btn) {
            btn.classList.toggle('is-active', name === tab);
        }
    });
}

function openWaiterPanel(tab) {
    switchSidebarTab(tab || 'orders');
    updateMobileDockHighlight(tab || 'orders');

    if (!isWaiterDrawerMode()) {
        return;
    }

    const sidebar = document.getElementById('waiter-pos-sidebar');
    const overlay = document.getElementById('waiter-pos-sidebar-overlay');
    if (sidebar) {
        sidebar.classList.add('is-open');
    }
    if (overlay) {
        overlay.classList.remove('hidden');
    }
    document.body.style.overflow = 'hidden';
}

function closeWaiterPanel(restoreScroll = true) {
    const sidebar = document.getElementById('waiter-pos-sidebar');
    const overlay = document.getElementById('waiter-pos-sidebar-overlay');
    if (sidebar) {
        sidebar.classList.remove('is-open');
    }
    if (overlay) {
        overlay.classList.add('hidden');
    }
    updateMobileDockHighlight('');
    if (restoreScroll) {
        document.body.style.overflow = '';
    }
}

function toggleMobileCart() {
    openWaiterPanel('cart');
}

// Update cart display
function updateCart() {
    const container = document.getElementById('cart-items');
    const totalElement = document.getElementById('cart-total');
    const mobileTotalMini = document.getElementById('mobile-cart-total-mini');
    const mobileBadge = document.getElementById('mobile-cart-badge');
    const itemCount = document.getElementById('cart-item-count');
    
    const totalItems = cart.reduce((sum, item) => sum + item.quantity, 0);
    
    if (mobileBadge) mobileBadge.textContent = totalItems > 0 ? `Sepet (${totalItems})` : 'Sepet';
    if (itemCount) itemCount.textContent = `${totalItems} ürün`;
    
    const emptyHtml = '<div class="text-center text-slate-400 py-8 text-sm">Sepet boş</div>';
    
    if (cart.length === 0) {
        if (container) container.innerHTML = emptyHtml;
        if (totalElement) totalElement.textContent = '0₺';
        if (mobileTotalMini) mobileTotalMini.textContent = '0₺';
        return;
    }
    
    let html = '';
    let total = 0;
    
    cart.forEach((item, index) => {
        let itemPrice = item.price;
        const itemTotal = itemPrice * item.quantity;
        total += itemTotal;
        
        // Build customization display text with full ingredient names
        let customizationHtml = '';
        if (item.customizations) {
            const lines = [];
            if (item.customizations.variant !== null && item.customizations.variant !== undefined) {
                lines.push('<span class="text-blue-600">Varyant seçili</span>');
            }
            // Show removed ingredient names
            const removedNames = item.customizations.removed_ingredient_names || [];
            if (Array.isArray(removedNames) && removedNames.length > 0) {
                lines.push(`<span class="text-red-600">Çıkar: ${removedNames.map(n => escapeHtml(n)).join(', ')}</span>`);
            } else if (Array.isArray(item.customizations.removed_ingredients) && item.customizations.removed_ingredients.length > 0) {
                lines.push(`<span class="text-red-600">-${item.customizations.removed_ingredients.length} malzeme çıkarıldı</span>`);
            }
            // Show extra names
            const extraDetails = item.customizations.extra_details || [];
            if (Array.isArray(extraDetails) && extraDetails.length > 0) {
                const extraNames = extraDetails.map(e => e.name || '').filter(Boolean);
                if (extraNames.length > 0) {
                    lines.push(`<span class="text-emerald-600">Ekstra: ${extraNames.map(n => escapeHtml(n)).join(', ')}</span>`);
                }
            } else if (Array.isArray(item.customizations.extras) && item.customizations.extras.length > 0) {
                lines.push(`<span class="text-emerald-600">+${item.customizations.extras.length} ekstra</span>`);
            }
            if (lines.length > 0) {
                customizationHtml = `<div class="text-[10px] mt-0.5 space-y-0.5">${lines.join('<br>')}</div>`;
            }
        }
        
        const cartItemIndex = index;
        const hasCustomizations = item.customizations && (
            (item.customizations.removed_ingredients && item.customizations.removed_ingredients.length > 0) ||
            (item.customizations.extras && item.customizations.extras.length > 0) ||
            item.customizations.variant !== null
        );
        const thumbUrl = (item.image_url || getMenuItemImageUrl(item.menu_item_id) || '').trim();
        const thumbHtml = thumbUrl
            ? `<div class="w-12 h-12 rounded-lg overflow-hidden shrink-0 bg-slate-200"><img src="${escapeHtml(thumbUrl)}" alt="" class="w-full h-full object-cover"></div>`
            : `<div class="w-12 h-12 rounded-lg shrink-0 bg-slate-200 flex items-center justify-center text-slate-400"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg></div>`;
        
        html += `
            <div class="p-3 bg-slate-50 rounded-lg">
                <div class="flex items-start gap-2 mb-2">
                    ${thumbHtml}
                    <div class="flex-1 min-w-0">
                        <div class="font-semibold text-sm text-slate-800 leading-tight break-words">${escapeHtml(item.name)}</div>
                        <div class="text-xs text-slate-500 mt-0.5">${item.price.toFixed(0)}₺ × ${item.quantity} = <span class="font-semibold text-slate-700">${itemTotal.toFixed(0)}₺</span></div>
                        ${customizationHtml}
                    </div>
                </div>
                <div class="flex items-center justify-end gap-1">
                    ${hasCustomizations ? `<button onclick="editCartItemCustomizations(${cartItemIndex})" class="w-7 h-7 bg-white border border-indigo-200 rounded-md font-medium text-indigo-500 hover:border-indigo-400 hover:bg-indigo-50 transition-all flex items-center justify-center text-xs" title="Düzenle">✎</button>` : ''}
                    <div class="flex items-center gap-1 bg-white border border-slate-200 rounded-md px-1">
                        <button onclick="updateQuantity(${cartItemIndex}, -1)" class="w-7 h-7 font-medium text-slate-600 hover:text-slate-800 transition-all flex items-center justify-center">−</button>
                        <span class="font-bold w-5 text-center text-sm text-slate-700">${item.quantity}</span>
                        <button onclick="updateQuantity(${cartItemIndex}, 1)" class="w-7 h-7 font-medium text-slate-600 hover:text-slate-800 transition-all flex items-center justify-center">+</button>
                    </div>
                    <button onclick="removeFromCart(${cartItemIndex})" class="w-7 h-7 text-red-400 hover:text-red-500 hover:bg-red-50 rounded-md transition-all flex items-center justify-center text-base">×</button>
                </div>
            </div>
        `;
    });
    
    if (container) container.innerHTML = html;
    if (totalElement) totalElement.textContent = total.toFixed(0) + '₺';
    if (mobileTotalMini) mobileTotalMini.textContent = total.toFixed(0) + '₺';
}

// Send order - Using centralized notification system
async function sendOrder() {
    if (cart.length === 0) {
        window.NotificationManager.error('Sepetiniz boş');
        return;
    }
    
    // CRITICAL: Re-check tableId before sending
    let finalTableId = tableId;
    if (!finalTableId || finalTableId.trim() === '') {
        // Try URL params
        const urlParams = new URLSearchParams(window.location.search);
        finalTableId = urlParams.get('table') || urlParams.get('table_id') || '';
        
        if (!finalTableId) {
            window.NotificationManager.error('Masa bilgisi bulunamadı. Lütfen masa seçimi ekranından tekrar deneyin.');
            console.error('Table ID missing:', {
                tableId: tableId,
                url: window.location.href,
                table: <?php echo json_encode($table ?? null); ?>
            });
            // Redirect to waiter dashboard after 2 seconds
            setTimeout(() => {
                window.location.href = `${baseUrl}/waiter/dashboard`;
            }, 2000);
            return;
        } else {
            // Update tableId for future use
            tableId = finalTableId;
            console.log('Table ID confirmed:', tableId);
        }
    }
    
    try {
        const items = cart.map(item => {
            const mapped = {
                menu_item_id: item.menu_item_id,
                quantity: item.quantity,
                price: item.price
            };
            
            // Include customizations if present
            if (item.customizations) {
                // Send excluded ingredient names for database storage
                const removedNames = item.customizations.removed_ingredient_names || [];
                if (removedNames.length > 0) {
                    mapped.excluded_ingredients = removedNames;
                }
                
                // Send extra details
                const extraDetails = item.customizations.extra_details || [];
                if (extraDetails.length > 0) {
                    mapped.selected_extras = extraDetails;
                }
                
                // Send variant
                if (item.customizations.variant !== null && item.customizations.variant !== undefined) {
                    mapped.variant_id = item.customizations.variant;
                }
            }
            
            return mapped;
        });
        
        console.log('Sending order:', {
            table_id: finalTableId,
            items_count: items.length,
            items: items
        });
        
        const response = await fetch(`${baseUrl}/pos/create-order`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                table_id: finalTableId,
                items: items
            })
        });
        
        // Handle non-JSON responses
        const contentType = response.headers.get('content-type');
        let data;
        if (contentType && contentType.includes('application/json')) {
            data = await response.json();
        } else {
            const text = await response.text();
            console.error('Non-JSON response:', text);
            window.NotificationManager.error('Sunucu yanıtı alınamadı. Lütfen internet bağlantınızı kontrol edin.');
            return;
        }
        
        if (response.ok && data?.success) {
            const message = data.message || 'Sipariş başarıyla gönderildi!';
            window.NotificationManager.success(message);
            cart = [];
            updateCart();
            switchSidebarTab('orders');
            loadTableOrders();
            closeWaiterPanel();
            
            // Don't redirect - keep waiter on the same table to continue adding items
            // Only reload table details if available
            if (typeof loadTableDetails === 'function') {
                setTimeout(() => {
                    loadTableDetails(finalTableId);
                }, 500);
            }
        } else {
            // Daha anlamlı hata mesajları
            let errorMsg = data.error || data.message || 'Sipariş oluşturulurken hata oluştu';
            
            // Validation hatalarını formatla
            if (data.errors && typeof data.errors === 'object') {
                const errorList = Object.values(data.errors).flat();
                if (errorList.length > 0) {
                    errorMsg = errorList[0]; // İlk hatayı göster
                }
            }
            
            window.NotificationManager.error(errorMsg);
            console.error('Order creation failed:', data);
        }
    } catch (error) {
        console.error('Error sending order:', error);
        
        // Network hatası mı kontrol et
        let errorMsg = 'Sipariş gönderilirken hata oluştu';
        if (error.message) {
            if (error.message.includes('Failed to fetch') || error.message.includes('NetworkError')) {
                errorMsg = 'İnternet bağlantısı kurulamadı. Lütfen bağlantınızı kontrol edin';
            } else if (error.message.includes('timeout')) {
                errorMsg = 'Sunucu yanıt vermedi. Lütfen tekrar deneyin';
            } else {
                errorMsg = error.message;
            }
        }
        
        window.NotificationManager.error(errorMsg);
    }
}

// Fullscreen toggle
function toggleFullscreen() {
    if (!document.fullscreenElement) {
        document.documentElement.requestFullscreen?.().catch(err => {});
    } else {
        document.exitFullscreen?.().catch(err => {});
    }
}

// Keyboard shortcuts
document.addEventListener('keydown', (e) => {
    // F - Focus search
    if (e.key === 'f' && e.ctrlKey) {
        e.preventDefault();
        document.getElementById('search-input')?.focus();
    }
    // ESC - Clear search
    if (e.key === 'Escape') {
        clearSearch();
    }
});

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    // Focus search input
    document.getElementById('search-input')?.focus();
    // Initialize cart display
    updateCart();
});

// Menu Item Editing Functions
let currentEditItem = null;
let editIngredients = [];
let editVariants = [];
let editExtras = [];

// Open edit modal
async function openEditModal(itemId) {
    const modal = document.getElementById('edit-item-modal');
    if (!modal) return;
    
    try {
        // Fetch menu item data
        const response = await fetch(`${baseUrl}/api/menu/item?id=${itemId}`, {
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin'
        });
        
        if (!response.ok) {
            throw new Error('Ürün bilgileri yüklenemedi');
        }
        
        const data = await response.json();
        if (!data.success || !data.menu_item) {
            throw new Error('Ürün bulunamadı');
        }
        
        const item = data.menu_item;
        currentEditItem = item;
        
        // Populate form
        document.getElementById('edit-item-id').value = item.menu_item_id || '';
        document.getElementById('edit-item-name').value = item.name || '';
        document.getElementById('edit-item-price').value = item.price || 0;
        
        // Load ingredients
        editIngredients = Array.isArray(item.ingredients) ? [...item.ingredients] : [];
        if (typeof item.ingredients === 'string') {
            try {
                editIngredients = JSON.parse(item.ingredients) || [];
            } catch (e) {
                editIngredients = [];
            }
        }
        renderIngredients();
        
        // Load variants - fetch from API if not included
        if (Array.isArray(item.variants) && item.variants.length > 0) {
            editVariants = item.variants.map(v => ({
                name: v.name || '',
                price_modifier: parseFloat(v.price_modifier || 0)
            }));
        } else if (item.has_variants == 1) {
            // Fetch variants separately
            try {
                const variantResponse = await fetch(`${baseUrl}/api/product-variants?product_id=${itemId}`, {
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin'
                });
                if (variantResponse.ok) {
                    const variantData = await variantResponse.json();
                    if (variantData.success && Array.isArray(variantData.variants)) {
                        editVariants = variantData.variants.map(v => ({
                            name: v.name || '',
                            price_modifier: parseFloat(v.price_modifier || 0)
                        }));
                    }
                }
            } catch (e) {
                console.warn('Could not load variants:', e);
                editVariants = [];
            }
        } else {
            editVariants = [];
        }
        renderVariants();
        
        // Load extras
        editExtras = Array.isArray(item.extras) ? [...item.extras] : [];
        if (typeof item.extras === 'string') {
            try {
                editExtras = JSON.parse(item.extras) || [];
            } catch (e) {
                editExtras = [];
            }
        }
        if (typeof item.available_extras === 'string') {
            try {
                const availableExtras = JSON.parse(item.available_extras) || [];
                if (availableExtras.length > 0) editExtras = availableExtras;
            } catch (e) {}
        }
        renderExtras();
        
        // Show modal
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    } catch (error) {
        console.error('Error loading menu item:', error);
        window.NotificationManager.error('Ürün bilgileri yüklenemedi: ' + error.message);
    }
}

// Close edit modal
function closeEditModal() {
    const modal = document.getElementById('edit-item-modal');
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }
    currentEditItem = null;
    editIngredients = [];
    editVariants = [];
    editExtras = [];
}

// Ingredients Management
function addIngredient() {
    const name = prompt('Malzeme adı:');
    if (name && name.trim()) {
        editIngredients.push(name.trim());
        renderIngredients();
    }
}

function removeIngredient(index) {
    editIngredients.splice(index, 1);
    renderIngredients();
}

function renderIngredients() {
    const container = document.getElementById('ingredients-list');
    if (!container) return;
    
    if (editIngredients.length === 0) {
        container.innerHTML = '<p class="text-sm text-slate-400">Malzeme eklenmemiş</p>';
        return;
    }
    
    container.innerHTML = editIngredients.map((ing, index) => `
        <div class="flex items-center gap-2 p-2 bg-slate-50 rounded-lg">
            <span class="flex-1 text-sm font-semibold">${escapeHtml(ing)}</span>
            <button onclick="removeIngredient(${index})" class="px-2 py-1 bg-red-100 text-red-600 rounded text-xs font-bold hover:bg-red-200">
                ×
            </button>
        </div>
    `).join('');
}

// Variants Management
function addVariant() {
    const name = prompt('Varyant adı:');
    if (!name || !name.trim()) return;
    
    const priceModifier = parseFloat(prompt('Fiyat farkı (₺):') || '0') || 0;
    
    editVariants.push({
        name: name.trim(),
        price_modifier: priceModifier
    });
    renderVariants();
}

function removeVariant(index) {
    editVariants.splice(index, 1);
    renderVariants();
}

function renderVariants() {
    const container = document.getElementById('variants-list');
    if (!container) return;
    
    if (editVariants.length === 0) {
        container.innerHTML = '<p class="text-sm text-slate-400">Varyant eklenmemiş</p>';
        return;
    }
    
    container.innerHTML = editVariants.map((variant, index) => `
        <div class="flex items-center gap-2 p-2 bg-slate-50 rounded-lg">
            <span class="flex-1 text-sm font-semibold">${escapeHtml(variant.name || '')}</span>
            <span class="text-sm text-slate-600">${variant.price_modifier >= 0 ? '+' : ''}${variant.price_modifier.toFixed(2)}₺</span>
            <button onclick="removeVariant(${index})" class="px-2 py-1 bg-red-100 text-red-600 rounded text-xs font-bold hover:bg-red-200">
                ×
            </button>
        </div>
    `).join('');
}

// Extras Management
function addExtra() {
    const name = prompt('Ekstra adı:');
    if (!name || !name.trim()) return;
    
    const price = parseFloat(prompt('Fiyat (₺):') || '0') || 0;
    
    editExtras.push({
        name: name.trim(),
        price: price
    });
    renderExtras();
}

function removeExtra(index) {
    editExtras.splice(index, 1);
    renderExtras();
}

function renderExtras() {
    const container = document.getElementById('extras-list');
    if (!container) return;
    
    if (editExtras.length === 0) {
        container.innerHTML = '<p class="text-sm text-slate-400">Ekstra eklenmemiş</p>';
        return;
    }
    
    container.innerHTML = editExtras.map((extra, index) => `
        <div class="flex items-center gap-2 p-2 bg-slate-50 rounded-lg">
            <span class="flex-1 text-sm font-semibold">${escapeHtml(extra.name || '')}</span>
            <span class="text-sm text-slate-600">${extra.price.toFixed(2)}₺</span>
            <button onclick="removeExtra(${index})" class="px-2 py-1 bg-red-100 text-red-600 rounded text-xs font-bold hover:bg-red-200">
                ×
            </button>
        </div>
    `).join('');
}

// Save menu item
async function saveMenuItem() {
    const itemId = document.getElementById('edit-item-id').value;
    const name = document.getElementById('edit-item-name').value.trim();
    const price = parseFloat(document.getElementById('edit-item-price').value) || 0;
    
    if (!name) {
        window.NotificationManager.warning('Ürün adı gereklidir');
        return;
    }
    
    try {
        const updateData = {
            name: name,
            price: price,
            ingredients: JSON.stringify(editIngredients),
            available_extras: JSON.stringify(editExtras),
            variants: editVariants
        };
        
        const response = await fetch(`${baseUrl}/business/menu/edit/${itemId}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                id: itemId,
                ...updateData
            })
        });
        
        const data = await response.json();
        
        if (response.ok && data.success) {
            window.NotificationManager.success('Ürün başarıyla güncellendi');
            closeEditModal();
            // Reload page to refresh menu items
            setTimeout(() => window.location.reload(), 1000);
        } else {
            throw new Error(data.error || data.message || 'Güncelleme başarısız');
        }
    } catch (error) {
        console.error('Error saving menu item:', error);
        window.NotificationManager.error('Hata: ' + error.message);
    }
}

// Helper function to escape HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Helper function to escape JS strings for use in onclick handlers
function escapeJs(str) {
    return String(str).replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/"/g, '\\"');
}

// Product Customization Modal Functions
let currentCustomizationItem = null;
let selectedVariant = null;
let removedIngredients = [];
let selectedExtras = [];
let ingredientNameMap = {}; // Maps ingredient ID to name
let extraNameMap = {}; // Maps extra ID to {name, price}
let editingCartIndex = -1; // For editing cart item customizations

async function openProductCustomizationModal(itemId, itemName, itemPrice, menuItem) {
    const modal = document.getElementById('product-customization-modal');
    if (!modal) return;
    
    currentCustomizationItem = {
        id: itemId,
        name: itemName,
        basePrice: parseFloat(itemPrice),
        menuItem: menuItem
    };
    
    // Reset selections
    selectedVariant = null;
    removedIngredients = [];
    selectedExtras = [];
    
    // Set modal header
    document.getElementById('customization-item-name').textContent = itemName;
    document.getElementById('customization-item-id').value = itemId;
    document.getElementById('customization-base-price').value = itemPrice;
    
    // Parse variants
    let variants = [];
    if (Array.isArray(menuItem.variants) && menuItem.variants.length > 0) {
        variants = menuItem.variants;
    } else if (menuItem.has_variants == 1) {
        // Try to fetch variants
        try {
            const variantResponse = await fetch(`${baseUrl}/api/product-variants?product_id=${itemId}`, {
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin'
            });
            if (variantResponse.ok) {
                const variantData = await variantResponse.json();
                if (variantData.success && Array.isArray(variantData.variants)) {
                    variants = variantData.variants;
                }
            }
        } catch (e) {
            console.warn('Could not load variants:', e);
        }
    }
    
    // Parse ingredients - handle multiple formats
    let ingredients = [];
    if (Array.isArray(menuItem.ingredients)) {
        ingredients = menuItem.ingredients;
    } else if (typeof menuItem.ingredients === 'string' && menuItem.ingredients.trim() !== '') {
        try {
            const parsed = JSON.parse(menuItem.ingredients);
            ingredients = Array.isArray(parsed) ? parsed : [];
        } catch (e) {
            console.warn('Could not parse ingredients JSON:', e);
            ingredients = [];
        }
    }
    
    // Filter removable ingredients - support multiple formats
    // If ingredients are strings, assume all are removable (common case in POS)
    // If ingredients are objects, check removable property
    const removableIngredients = ingredients.filter(ing => {
        if (typeof ing === 'string') {
            // If it's a string, assume it's removable (common case in POS systems)
            return true;
        }
        if (typeof ing === 'object' && ing !== null) {
            // Check various possible property names
            return ing.is_removable === true || 
                   ing.is_removable === 1 || 
                   ing.is_removable === '1' ||
                   ing.removable === true ||
                   ing.removable === 1 ||
                   ing.removable === '1' ||
                   // If no removable property specified, assume it's removable
                   (ing.is_removable === undefined && ing.removable === undefined);
        }
        return false;
    });
    
    // Parse extras - handle multiple formats
    let extras = [];
    if (Array.isArray(menuItem.extras)) {
        extras = menuItem.extras;
    } else if (Array.isArray(menuItem.available_extras)) {
        extras = menuItem.available_extras;
    } else if (typeof menuItem.extras === 'string' && menuItem.extras.trim() !== '') {
        try {
            const parsed = JSON.parse(menuItem.extras);
            extras = Array.isArray(parsed) ? parsed : [];
        } catch (e) {
            console.warn('Could not parse extras JSON:', e);
            extras = [];
        }
    } else if (typeof menuItem.available_extras === 'string' && menuItem.available_extras.trim() !== '') {
        try {
            const parsed = JSON.parse(menuItem.available_extras);
            extras = Array.isArray(parsed) ? parsed : [];
        } catch (e) {
            console.warn('Could not parse available_extras JSON:', e);
            extras = [];
        }
    }
    
    // Show/hide sections
    const variantsSection = document.getElementById('variants-section');
    const ingredientsSection = document.getElementById('ingredients-section');
    const extrasSection = document.getElementById('extras-section');
    
    if (variants.length > 0) {
        variantsSection.classList.remove('hidden');
        renderVariantsCustomization(variants);
    } else {
        variantsSection.classList.add('hidden');
    }
    
    if (removableIngredients.length > 0) {
        ingredientsSection.classList.remove('hidden');
        renderIngredientsCustomization(removableIngredients);
    } else {
        ingredientsSection.classList.add('hidden');
    }
    
    if (extras.length > 0) {
        extrasSection.classList.remove('hidden');
        renderExtrasCustomization(extras);
    } else {
        extrasSection.classList.add('hidden');
    }
    
    // Update price
    updateCustomizationPrice();
    
    // Show modal
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function closeProductCustomizationModal() {
    const modal = document.getElementById('product-customization-modal');
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }
    currentCustomizationItem = null;
    selectedVariant = null;
    removedIngredients = [];
    selectedExtras = [];
    editingCartIndex = -1; // Reset edit mode
}

// Edit cart item customizations - re-open the customization modal with pre-filled data
function editCartItemCustomizations(cartIndex) {
    const item = cart[cartIndex];
    if (!item) return;
    
    editingCartIndex = cartIndex;
    
    // Find the menu item data to get ingredients/extras list
    const menuItems = <?php echo json_encode($menu_items ?? []); ?>;
    const menuItem = menuItems.find(m => (m.menu_item_id || m.id) === item.menu_item_id);
    
    if (!menuItem) {
        // If menu item data not found, show a simple notification
        if (window.NotificationManager) {
            window.NotificationManager.error('Ürün bilgisi bulunamadı');
        }
        return;
    }
    
    // Pre-fill customization state from cart item
    const customizations = item.customizations || {};
    selectedVariant = customizations.variant || null;
    removedIngredients = Array.isArray(customizations.removed_ingredients) ? [...customizations.removed_ingredients] : [];
    selectedExtras = Array.isArray(customizations.extras) ? [...customizations.extras] : [];
    
    // Open the customization modal with existing data
    openProductCustomizationModal(item.menu_item_id, item.name, item.price, menuItem);
}


function renderVariantsCustomization(variants) {
    const container = document.getElementById('variants-list-customization');
    if (!container) return;
    
    container.innerHTML = variants.map((variant, index) => {
        const variantName = variant.name || variant.variant_name || variant.title || '';
        const priceModifier = parseFloat(variant.price_modifier || variant.price_modifier || variant.price_difference || variant.additional_price || 0);
        const variantId = variant.variant_id || variant.id || variant.variantId || `variant_${index}`;
        
        return `
            <label class="flex items-center gap-3 p-3 border-2 rounded-lg cursor-pointer hover:border-indigo-400 transition-all ${selectedVariant === variantId ? 'border-indigo-500 bg-indigo-50' : 'border-slate-200'}">
                <input type="radio" name="variant" value="${variantId}" 
                       onchange="selectVariant('${variantId}', ${priceModifier})" 
                       class="w-4 h-4 text-indigo-600 focus:ring-indigo-500"
                       ${selectedVariant === variantId ? 'checked' : ''}>
                <div class="flex-1">
                    <div class="font-semibold text-slate-900">${escapeHtml(variantName)}</div>
                    ${priceModifier !== 0 ? `<div class="text-sm text-slate-600">${priceModifier >= 0 ? '+' : ''}${priceModifier.toFixed(2)}₺</div>` : ''}
                </div>
            </label>
        `;
    }).join('');
    
    // Auto-select first variant if none selected and variants exist
    if (variants.length > 0 && selectedVariant === null) {
        const firstVariant = variants[0];
        const firstVariantId = firstVariant.variant_id || firstVariant.id || firstVariant.variantId || `variant_0`;
        const firstPriceModifier = parseFloat(firstVariant.price_modifier || firstVariant.price_modifier || firstVariant.price_difference || firstVariant.additional_price || 0);
        selectVariant(firstVariantId, firstPriceModifier);
    }
}

function renderIngredientsCustomization(ingredients) {
    const container = document.getElementById('ingredients-list-customization');
    if (!container) return;
    
    // Reset and rebuild ingredient name map
    ingredientNameMap = {};
    
    container.innerHTML = ingredients.map((ingredient, index) => {
        // Handle different ingredient formats
        let ingName = '';
        let ingId = '';
        
        if (typeof ingredient === 'string') {
            ingName = ingredient;
            ingId = `ing_${index}`;
        } else if (typeof ingredient === 'object' && ingredient !== null) {
            ingName = ingredient.name || ingredient.ingredient_name || ingredient.title || ingredient.text || '';
            ingId = ingredient.ingredient_id || ingredient.id || ingredient.ingredientId || `ing_${index}`;
        }
        
        if (!ingName) return '';
        
        // Build name map for later use
        ingredientNameMap[String(ingId)] = ingName;
        
        const isRemoved = removedIngredients.includes(ingId) || removedIngredients.includes(String(ingId));
        
        return `
            <label class="flex items-center gap-3 p-3 border-2 rounded-lg cursor-pointer hover:border-indigo-400 transition-all ${isRemoved ? 'border-red-300 bg-red-50' : 'border-slate-200'}">
                <input type="checkbox" 
                       onchange="toggleIngredient('${ingId}')" 
                       ${isRemoved ? 'checked' : ''}
                       class="w-4 h-4 text-red-600 focus:ring-red-500">
                <div class="flex-1">
                    <div class="font-semibold text-slate-900">${escapeHtml(ingName)}</div>
                    <div class="text-xs text-slate-500">Çıkar</div>
                </div>
            </label>
        `;
    }).filter(html => html !== '').join('');
}

function renderExtrasCustomization(extras) {
    const container = document.getElementById('extras-list-customization');
    if (!container) return;
    
    // Reset and rebuild extras name map
    extraNameMap = {};
    
    container.innerHTML = extras.map((extra, index) => {
        // Handle different extra formats
        const extraName = extra.name || extra.extra_name || extra.title || extra.text || '';
        const extraPrice = parseFloat(extra.price || extra.extra_price || extra.additional_price || 0);
        const extraId = extra.extra_id || extra.id || extra.extraId || `extra_${index}`;
        const isSelected = selectedExtras.includes(extraId) || selectedExtras.includes(String(extraId));
        
        if (!extraName) return '';
        
        // Build name map for later use
        extraNameMap[String(extraId)] = { name: extraName, price: extraPrice };
        
        return `
            <label class="flex items-center gap-3 p-3 border-2 rounded-lg cursor-pointer hover:border-indigo-400 transition-all ${isSelected ? 'border-indigo-500 bg-indigo-50' : 'border-slate-200'}">
                <input type="checkbox" 
                       onchange="toggleExtra('${extraId}', ${extraPrice})" 
                       ${isSelected ? 'checked' : ''}
                       class="w-4 h-4 text-indigo-600 focus:ring-indigo-500">
                <div class="flex-1">
                    <div class="font-semibold text-slate-900">${escapeHtml(extraName)}</div>
                    ${extraPrice > 0 ? `<div class="text-sm text-slate-600">+${extraPrice.toFixed(2)}₺</div>` : ''}
                </div>
            </label>
        `;
    }).filter(html => html !== '').join('');
}

function selectVariant(variantId, priceModifier) {
    selectedVariant = variantId;
    updateCustomizationPrice();
    // Update radio button visual state
    document.querySelectorAll('#variants-list-customization label').forEach(label => {
        const input = label.querySelector('input[type="radio"]');
        if (input && input.value === variantId) {
            label.classList.add('border-indigo-500', 'bg-indigo-50');
            label.classList.remove('border-slate-200');
        } else {
            label.classList.remove('border-indigo-500', 'bg-indigo-50');
            label.classList.add('border-slate-200');
        }
    });
}

function toggleIngredient(ingredientId) {
    const idStr = String(ingredientId);
    const index = removedIngredients.findIndex(id => String(id) === idStr);
    if (index > -1) {
        removedIngredients.splice(index, 1);
    } else {
        removedIngredients.push(ingredientId);
    }
    updateCustomizationPrice();
    // Update checkbox visual state
    const label = event.target.closest('label');
    if (label) {
        const isRemoved = removedIngredients.includes(ingredientId) || removedIngredients.some(id => String(id) === idStr);
        if (isRemoved) {
            label.classList.add('border-red-300', 'bg-red-50');
            label.classList.remove('border-slate-200');
        } else {
            label.classList.remove('border-red-300', 'bg-red-50');
            label.classList.add('border-slate-200');
        }
    }
}

function toggleExtra(extraId, extraPrice) {
    const idStr = String(extraId);
    const index = selectedExtras.findIndex(id => String(id) === idStr);
    if (index > -1) {
        selectedExtras.splice(index, 1);
    } else {
        selectedExtras.push(extraId);
    }
    updateCustomizationPrice();
    // Update checkbox visual state
    const label = event.target.closest('label');
    if (label) {
        const isSelected = selectedExtras.includes(extraId) || selectedExtras.some(id => String(id) === idStr);
        if (isSelected) {
            label.classList.add('border-indigo-500', 'bg-indigo-50');
            label.classList.remove('border-slate-200');
        } else {
            label.classList.remove('border-indigo-500', 'bg-indigo-50');
            label.classList.add('border-slate-200');
        }
    }
}

function updateCustomizationPrice() {
    if (!currentCustomizationItem) return;
    
    let totalPrice = currentCustomizationItem.basePrice;
    
    // Add variant price modifier
    if (selectedVariant !== null) {
        const variantInput = document.querySelector(`input[name="variant"][value="${selectedVariant}"]`);
        if (variantInput) {
            const label = variantInput.closest('label');
            const priceElement = label?.querySelector('.text-slate-600');
            if (priceElement) {
                const priceText = priceElement.textContent || '';
                const match = priceText.match(/[+-]?(\d+\.?\d*)/);
                if (match) {
                    const modifier = parseFloat(match[1]);
                    // Check if it's negative
                    if (priceText.includes('-')) {
                        totalPrice -= modifier;
                    } else {
                        totalPrice += modifier;
                    }
                }
            }
        }
    }
    
    // Add extras prices
    selectedExtras.forEach(extraId => {
        const extraInput = document.querySelector(`input[type="checkbox"][onchange*="${extraId}"]`);
        if (extraInput) {
            const label = extraInput.closest('label');
            const priceElement = label?.querySelector('.text-slate-600');
            if (priceElement) {
                const priceText = priceElement.textContent || '';
                const match = priceText.match(/\+(\d+\.?\d*)/);
                if (match) {
                    totalPrice += parseFloat(match[1]);
                }
            }
        }
    });
    
    const priceElement = document.getElementById('customization-total-price');
    if (priceElement) {
        priceElement.textContent = totalPrice.toFixed(0) + '₺';
    }
}

function addCustomizedProductToCart() {
    if (!currentCustomizationItem) return;
    
    // Calculate final price with customizations
    let finalPrice = currentCustomizationItem.basePrice;
    
    // Add variant price modifier
    if (selectedVariant !== null) {
        const variantInput = document.querySelector(`input[name="variant"][value="${selectedVariant}"]`);
        if (variantInput) {
            const label = variantInput.closest('label');
            const priceElement = label?.querySelector('.text-slate-600');
            if (priceElement) {
                const priceText = priceElement.textContent || '';
                const match = priceText.match(/[+-]?(\d+\.?\d*)/);
                if (match) {
                    const modifier = parseFloat(match[1]);
                    if (priceText.includes('-')) {
                        finalPrice -= modifier;
                    } else {
                        finalPrice += modifier;
                    }
                }
            }
        }
    }
    
    // Add extras prices
    selectedExtras.forEach(extraId => {
        const extraInput = document.querySelector(`input[type="checkbox"][onchange*="${extraId}"]`);
        if (extraInput) {
            const label = extraInput.closest('label');
            const priceElement = label?.querySelector('.text-slate-600');
            if (priceElement) {
                const priceText = priceElement.textContent || '';
                const match = priceText.match(/\+(\d+\.?\d*)/);
                if (match) {
                    finalPrice += parseFloat(match[1]);
                }
            }
        }
    });
    
    // Convert IDs to names for storage and server compatibility
    const removedIngredientNames = removedIngredients.map(id => ingredientNameMap[String(id)] || String(id)).filter(Boolean);
    const extraDetails = selectedExtras.map(id => {
        const info = extraNameMap[String(id)];
        return info ? { id: id, name: info.name, price: info.price } : { id: id, name: String(id), price: 0 };
    });
    
    const customizations = {
        variant: selectedVariant,
        removed_ingredients: removedIngredients,
        removed_ingredient_names: removedIngredientNames,
        extras: selectedExtras,
        extra_details: extraDetails
    };
    
    // Check if we're editing an existing cart item
    if (editingCartIndex >= 0 && editingCartIndex < cart.length) {
        // Update existing cart item in-place
        cart[editingCartIndex].price = finalPrice;
        cart[editingCartIndex].customizations = {
            variant: customizations.variant ?? null,
            removed_ingredients: Array.isArray(customizations.removed_ingredients) ? customizations.removed_ingredients : [],
            removed_ingredient_names: Array.isArray(customizations.removed_ingredient_names) ? customizations.removed_ingredient_names : [],
            extras: Array.isArray(customizations.extras) ? customizations.extras : [],
            extra_details: Array.isArray(customizations.extra_details) ? customizations.extra_details : []
        };
        editingCartIndex = -1;
        updateCart();
        if (window.NotificationManager && typeof window.NotificationManager.success === 'function') {
            window.NotificationManager.success('Özelleştirmeler güncellendi');
        }
    } else {
        addToCart(
            currentCustomizationItem.id,
            currentCustomizationItem.name,
            finalPrice,
            customizations
        );
    }
    
    closeProductCustomizationModal();
}

// Sidebar tab switching
function switchSidebarTab(tab) {
    const ordersTab = document.getElementById('sidebar-tab-orders');
    const cartTab = document.getElementById('sidebar-tab-cart');
    const historyTab = document.getElementById('sidebar-tab-history');
    const ordersContent = document.getElementById('sidebar-orders-content');
    const cartContent = document.getElementById('sidebar-cart-content');
    const historyContent = document.getElementById('sidebar-history-content');
    
    // Reset all tabs
    const allTabs = [ordersTab, cartTab, historyTab];
    const allContents = [ordersContent, cartContent, historyContent];
    
    allTabs.forEach(t => {
        if (t) {
            t.classList.remove('text-indigo-600', 'border-indigo-500');
            t.classList.add('text-slate-500', 'border-transparent');
        }
    });
    allContents.forEach(c => {
        if (c) c.classList.add('hidden');
    });
    
    if (tab === 'orders') {
        ordersTab.classList.remove('text-slate-500', 'border-transparent');
        ordersTab.classList.add('text-indigo-600', 'border-indigo-500');
        ordersContent.classList.remove('hidden');
        loadTableOrders();
    } else if (tab === 'history') {
        historyTab.classList.remove('text-slate-500', 'border-transparent');
        historyTab.classList.add('text-indigo-600', 'border-indigo-500');
        historyContent.classList.remove('hidden');
        loadTableActivityLogs();
    } else {
        cartTab.classList.remove('text-slate-500', 'border-transparent');
        cartTab.classList.add('text-indigo-600', 'border-indigo-500');
        cartContent.classList.remove('hidden');
    }

    updateMobileDockHighlight(tab);
}

// Load table activity logs
async function loadTableActivityLogs() {
    if (!tableId) return;
    
    const container = document.getElementById('table-history-list');
    const countElement = document.getElementById('history-count');
    if (!container) return;
    
    container.innerHTML = '<div class="text-center text-slate-400 py-8 text-sm">Yükleniyor...</div>';
    
    try {
        const response = await fetch(`${baseUrl}/api/waiter/table-activity-logs?table_id=${encodeURIComponent(tableId)}&date=today`);
        if (!response.ok) {
            container.innerHTML = '<div class="text-center text-slate-400 py-8 text-sm">Kayıtlar yüklenemedi</div>';
            return;
        }
        
        const data = await response.json();
        
        if (!data.success || !Array.isArray(data.logs) || data.logs.length === 0) {
            container.innerHTML = '<div class="text-center text-slate-400 py-8 text-sm">Bugün için hareket kaydı yok</div>';
            if (countElement) countElement.textContent = '0 kayıt';
            return;
        }
        
        if (countElement) countElement.textContent = `${data.logs.length} kayıt`;
        
        const actionLabels = {
            'ITEM_DELETED': { label: 'Ürün Silindi', icon: '🗑️', color: 'text-red-600 bg-red-50 border-red-200' },
            'ITEM_QUANTITY_REDUCED': { label: 'Miktar Azaltıldı', icon: '📉', color: 'text-amber-600 bg-amber-50 border-amber-200' },
            'ORDER_CANCELLED': { label: 'Sipariş İptal', icon: '❌', color: 'text-red-600 bg-red-50 border-red-200' },
            'ALL_ORDERS_DELETED': { label: 'Tüm Siparişler Silindi', icon: '🔥', color: 'text-red-700 bg-red-50 border-red-300' },
            'ORDER_TRANSFERRED': { label: 'Kasaya Devredildi', icon: '💰', color: 'text-blue-600 bg-blue-50 border-blue-200' },
            'TABLE_MOVED': { label: 'Masa Taşındı', icon: '🔄', color: 'text-purple-600 bg-purple-50 border-purple-200' }
        };
        
        let html = data.logs.map(log => {
            const action = actionLabels[log.action_type] || { label: log.action_type, icon: '📋', color: 'text-slate-600 bg-slate-50 border-slate-200' };
            const time = log.created_at ? new Date(log.created_at).toLocaleString('tr-TR', { hour: '2-digit', minute: '2-digit', day: '2-digit', month: '2-digit' }) : '';
            const performerName = log.performed_by_name || 'Bilinmeyen';
            const performerRole = log.performed_by_role || '';
            const itemName = log.item_name || '';
            const oldQty = log.old_quantity;
            const newQty = log.new_quantity;
            const totalAffected = parseFloat(log.total_affected || 0);
            
            let detailHtml = '';
            if (log.action_type === 'ITEM_DELETED') {
                detailHtml = `<div class="text-xs text-slate-700"><strong>${escapeHtml(itemName)}</strong> × ${oldQty || 1}</div>`;
                if (totalAffected > 0) {
                    detailHtml += `<div class="text-xs text-red-500">-${totalAffected.toFixed(2)} ₺</div>`;
                }
            } else if (log.action_type === 'ITEM_QUANTITY_REDUCED') {
                detailHtml = `<div class="text-xs text-slate-700"><strong>${escapeHtml(itemName)}</strong>: ${oldQty} → ${newQty}</div>`;
                if (totalAffected > 0) {
                    detailHtml += `<div class="text-xs text-amber-500">-${totalAffected.toFixed(2)} ₺</div>`;
                }
            } else if (log.action_type === 'ALL_ORDERS_DELETED') {
                let details = {};
                try { details = typeof log.action_details === 'string' ? JSON.parse(log.action_details) : (log.action_details || {}); } catch(e) {}
                const count = details.deleted_orders_count || 0;
                detailHtml = `<div class="text-xs text-slate-700">${count} sipariş silindi</div>`;
                if (totalAffected > 0) {
                    detailHtml += `<div class="text-xs text-red-500">-${totalAffected.toFixed(2)} ₺</div>`;
                }
            }
            
            return `
                <div class="p-2.5 rounded-lg border ${action.color} transition-all">
                    <div class="flex justify-between items-start">
                        <div class="flex items-center gap-1.5">
                            <span class="text-sm">${action.icon}</span>
                            <span class="text-xs font-bold">${action.label}</span>
                        </div>
                        <span class="text-[10px] text-slate-500">${time}</span>
                    </div>
                    ${detailHtml}
                    <div class="text-[10px] text-slate-500 mt-1 flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                        ${escapeHtml(performerName)} ${performerRole ? `(${escapeHtml(performerRole)})` : ''}
                    </div>
                </div>
            `;
        }).join('');
        
        container.innerHTML = html;
    } catch (error) {
        console.error('Error loading activity logs:', error);
        container.innerHTML = '<div class="text-center text-red-400 py-8 text-sm">Hata oluştu</div>';
    }
}

// Load table orders
async function loadTableOrders() {
    if (!tableId) {
        console.warn('loadTableOrders: tableId is missing');
        return;
    }
    
    const container = document.getElementById('table-orders-list');
    const countElement = document.getElementById('orders-count');
    
    if (!container) {
        console.warn('loadTableOrders: container not found');
        return;
    }
    
    try {
        const response = await fetch(`${baseUrl}/api/waiter/table-details/${tableId}`);
        if (!response.ok) {
            container.innerHTML = '<div class="text-center text-slate-400 py-8 text-sm">Siparişler yüklenemedi</div>';
            return;
        }
        
        const data = await response.json();
        
        if (!data.success) {
            container.innerHTML = '<div class="text-center text-red-400 py-8 text-sm">Hata: ' + (data.error || 'Siparişler yüklenemedi') + '</div>';
            if (countElement) countElement.textContent = '0 sipariş';
            return;
        }
        
        // Get orders - check multiple possible fields
        const orders = data.orders || data.order || [];
        
        if (!Array.isArray(orders) || orders.length === 0) {
            container.innerHTML = '<div class="text-center text-slate-400 py-8 text-sm">Sipariş bulunamadı</div>';
            if (countElement) countElement.textContent = '0 sipariş';
            return;
        }
        
        // Filter active orders (exclude SERVED and CANCELLED)
        const activeRaw = orders.filter(order => {
            const status = (order.status || '').toUpperCase();
            return status !== 'SERVED' && status !== 'CANCELLED';
        });
        
        // Deduplicate by order_id - badge must match displayed count (fix: "3" badge but 1 order)
        const seen = new Set();
        const activeOrders = activeRaw.filter(order => {
            const oid = order.order_id || '';
            if (!oid || seen.has(oid)) return false;
            seen.add(oid);
            return true;
        });
        
        if (countElement) countElement.textContent = `${activeOrders.length} sipariş`;
        
        if (activeOrders.length === 0) {
            container.innerHTML = '<div class="text-center text-slate-400 py-8 text-sm">Aktif sipariş bulunamadı</div>';
            return;
        }
        
        // Render "Delete All" button + orders (only when approval enabled and user may delete)
        const showDeleteReduce = (typeof showDeleteReduceButtons !== 'undefined' && showDeleteReduceButtons);
        let ordersHtml = showDeleteReduce ? `
            <div class="mb-3 flex gap-2">
                <button onclick="deleteAllTableOrders()" 
                        class="flex-1 px-3 py-2 bg-red-500 hover:bg-red-600 text-white rounded-lg text-xs font-bold transition-all flex items-center justify-center gap-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                    Tüm Siparişleri Sil
                </button>
            </div>
        ` : '';
        
        ordersHtml += activeOrders.map(order => {
            const orderId = order.order_id || '';
            const status = (order.status || 'PENDING').toUpperCase();
            const totalAmount = parseFloat(order.total_amount || 0);
            const createdAt = order.created_at ? new Date(order.created_at).toLocaleString('tr-TR') : '';
            const items = order.items || [];
            
            const statusBadge = {
                'PENDING': '<span class="px-2 py-1 bg-yellow-100 text-yellow-700 rounded text-xs font-bold">Beklemede</span>',
                'PREPARING': '<span class="px-2 py-1 bg-orange-100 text-orange-700 rounded text-xs font-bold">Hazırlanıyor</span>',
                'READY': '<span class="px-2 py-1 bg-green-100 text-green-700 rounded text-xs font-bold">Hazır</span>',
                'SERVED': '<span class="px-2 py-1 bg-blue-100 text-blue-700 rounded text-xs font-bold">Servis Edildi</span>'
            }[status] || '<span class="px-2 py-1 bg-slate-100 text-slate-700 rounded text-xs font-bold">' + status + '</span>';
            
            return `
                <div class="bg-white rounded-lg border-2 border-slate-200 p-3 hover:border-indigo-400 transition-all">
                    <div class="flex justify-between items-start mb-2">
                        <div>
                            <div class="font-black text-sm text-slate-900">Sipariş #${orderId.substring(0, 8)}</div>
                            <div class="text-xs text-slate-500 mt-1">${createdAt}</div>
                        </div>
                        ${statusBadge}
                    </div>
                    <div class="space-y-1 mb-2">
                        ${(window.Utils && window.Utils.groupOrderItemsForDisplay ? window.Utils.groupOrderItemsForDisplay(items) : items).map(item => {
                            const ids = item._order_item_ids && item._order_item_ids.length ? item._order_item_ids : (item.order_item_id ? [item.order_item_id] : []);
                            const idsJson = JSON.stringify(ids).replace(/"/g, '&quot;');
                            const itemName = item.menu_item_name || item.item_name || item.name || 'Ürün';
                            const quantity = parseInt(item.quantity || 1);
                            const price = parseFloat(item.price || 0);
                            const total = price * quantity;
                            const excluded = (item.excluded_ingredients || []);
                            const excludedList = Array.isArray(excluded) ? excluded : [];
                            const excludedStr = excludedList.map(e => typeof e === 'object' ? (e.name || e.ingredient_name || '') : e).filter(Boolean).join(', ');
                            const note = (item.note || '').trim();
                            const extras = (item.selected_extras || []);
                            const extrasList = Array.isArray(extras) ? extras : [];
                            const extrasStr = extrasList.map(e => typeof e === 'object' ? (e.name || '') : e).filter(Boolean).join(', ');
                            
                            return `
                                <div class="flex justify-between items-start text-xs bg-slate-50 rounded p-2">
                                    <div class="flex-1 min-w-0">
                                        <div class="font-bold text-slate-900 truncate">${escapeHtml(itemName)}</div>
                                        ${excludedStr ? `<div class="text-red-600 text-[10px] mt-0.5">Çıkar: ${escapeHtml(excludedStr)}</div>` : ''}
                                        ${extrasStr ? `<div class="text-emerald-600 text-[10px] mt-0.5">Ekstra: ${escapeHtml(extrasStr)}</div>` : ''}
                                        ${note ? `<div class="text-amber-700 text-[10px] mt-0.5">Not: ${escapeHtml(note)}</div>` : ''}
                                        <div class="text-slate-600 mt-0.5">${price.toFixed(2)} ₺ x ${quantity}</div>
                                    </div>
                                    <div class="flex items-center gap-1 ml-2">
                                        <span class="font-bold text-slate-900">${total.toFixed(2)} ₺</span>
                                        ${(typeof showDeleteReduceButtons !== 'undefined' && showDeleteReduceButtons) ? `
                                        ${quantity > 1 ? `
                                            <button onclick="requestReduceQuantityGroup(${idsJson}, ${quantity}, '${itemName.replace(/'/g, "\\'")}')" 
                                                    class="px-2 py-1 bg-yellow-500 hover:bg-yellow-600 text-white rounded text-[10px] font-bold transition-all" 
                                                    title="Miktarı Azalt">
                                                −
                                            </button>
                                        ` : ''}
                                        <button onclick="requestDeleteItemGroup(${idsJson}, '${itemName.replace(/'/g, "\\'")}')" 
                                                class="px-2 py-1 bg-red-500 hover:bg-red-600 text-white rounded text-[10px] font-bold transition-all" 
                                                title="Sil">
                                            ×
                                        </button>
                                        ` : ''}
                                    </div>
                                </div>
                            `;
                        }).join('')}
                    </div>
                    <div class="flex justify-between items-center pt-2 border-t border-slate-200">
                        <span class="font-bold text-slate-700">Toplam:</span>
                        <span class="font-black text-lg text-indigo-600">${totalAmount.toFixed(2)} ₺</span>
                    </div>
                </div>
            `;
        }).join('');
        
        container.innerHTML = ordersHtml;
    } catch (error) {
        console.error('Error loading table orders:', error);
        container.innerHTML = '<div class="text-center text-red-400 py-8 text-sm">Hata oluştu</div>';
    }
}

// Masa taşıma modalı
function updateMoveFromInfoText() {
    const fromInfo = document.getElementById('move-table-from-info');
    if (!fromInfo) return;

    const displayName = (currentTableData && currentTableData.name) ? currentTableData.name : tableId;
    const zoneName = (currentTableData && (currentTableData.zone || currentTableData.zone_name)) || '';
    fromInfo.textContent = zoneName
        ? `${zoneName} - ${displayName} masasından taşınacak`
        : `${displayName} masasından taşınacak`;
}

async function checkMoveTableOrders() {
    const warning = document.getElementById('move-table-no-orders-warning');
    if (!warning || !tableId) return;

    try {
        const response = await fetch(`${baseUrl}/api/waiter/table-details/${tableId}`);
        if (!response.ok) {
            warning.classList.add('hidden');
            return;
        }

        const data = await response.json();
        const orders = data.orders || [];
        const activeOrders = orders.filter(order => {
            const status = (order.status || '').toUpperCase();
            return status !== 'SERVED' && status !== 'CANCELLED';
        });

        warning.classList.toggle('hidden', activeOrders.length > 0);
    } catch (error) {
        warning.classList.add('hidden');
    }
}

function showMoveTableModal() {
    currentMoveTableId = tableId;
    const modal = document.getElementById('move-table-modal');
    
    if (!modal) return;
    
    updateMoveFromInfoText();
    checkMoveTableOrders();
    
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    
    loadTablesForMove(true); // Force refresh from API
}

function closeMoveTableModal() {
    const modal = document.getElementById('move-table-modal');
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }
    currentMoveTableId = null;
    moveTablesData = null; // Clear cached data
    moveTableFilter = 'free'; // Reset filter
}

// Cache tables data for move modal
let moveTablesData = null;
let moveTableFilter = 'free'; // 'free' or 'all'

function filterMoveTablesByStatus(filter) {
    moveTableFilter = filter;
    
    // Update filter button styles
    const freeBtn = document.getElementById('move-filter-free');
    const allBtn = document.getElementById('move-filter-all');
    
    if (filter === 'free') {
        freeBtn.className = 'px-4 py-2 rounded-xl text-sm font-bold transition-all bg-emerald-500 text-white';
        allBtn.className = 'px-4 py-2 rounded-xl text-sm font-bold transition-all bg-slate-100 text-slate-600 hover:bg-slate-200';
    } else {
        freeBtn.className = 'px-4 py-2 rounded-xl text-sm font-bold transition-all bg-slate-100 text-slate-600 hover:bg-slate-200';
        allBtn.className = 'px-4 py-2 rounded-xl text-sm font-bold transition-all bg-slate-700 text-white';
    }
    
    renderMoveTablesList();
}

async function loadTablesForMove(forceRefresh = false) {
    const container = document.getElementById('tables-list-move');
    if (!container) return;
    
    // Reset filter to free on modal open
    moveTableFilter = 'free';
    const freeBtn = document.getElementById('move-filter-free');
    const allBtn = document.getElementById('move-filter-all');
    if (freeBtn) freeBtn.className = 'px-4 py-2 rounded-xl text-sm font-bold transition-all bg-emerald-500 text-white';
    if (allBtn) allBtn.className = 'px-4 py-2 rounded-xl text-sm font-bold transition-all bg-slate-100 text-slate-600 hover:bg-slate-200';
    
    // If no cached data or force refresh, load from API
    if (!moveTablesData || forceRefresh) {
        container.innerHTML = `
            <div class="flex flex-col items-center justify-center py-16">
                <div class="w-12 h-12 border-4 border-indigo-200 border-t-indigo-500 rounded-full animate-spin mb-4"></div>
                <p class="text-slate-500 font-medium">Masalar yükleniyor...</p>
            </div>
        `;
        
        try {
            const response = await fetch(`${baseUrl}/api/waiter/tables`);
            if (!response.ok) {
                container.innerHTML = `
                    <div class="flex flex-col items-center justify-center py-16">
                        <svg class="w-16 h-16 text-slate-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <p class="text-slate-500 font-medium">Masalar yüklenemedi</p>
                    </div>
                `;
                return;
            }
            
            const data = await response.json();
            
            // API returns { zones: {...}, total_tables: N, ... }
            if (!data.zones || Object.keys(data.zones).length === 0) {
                container.innerHTML = `
                    <div class="flex flex-col items-center justify-center py-16">
                        <svg class="w-16 h-16 text-slate-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                        </svg>
                        <p class="text-slate-500 font-medium">Masa bulunamadı</p>
                    </div>
                `;
                return;
            }
            
            // Store the entire response - renderMoveTablesList uses moveTablesData.zones
            moveTablesData = data;
        } catch (error) {
            console.error('Error loading tables for move:', error);
            container.innerHTML = `
                <div class="flex flex-col items-center justify-center py-16">
                    <svg class="w-16 h-16 text-red-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                    <p class="text-red-500 font-medium">Bir hata oluştu</p>
                </div>
            `;
            return;
        }
    }
    
    renderMoveTablesList();
}

function renderMoveTablesList() {
    const container = document.getElementById('tables-list-move');
    if (!container || !moveTablesData) return;
    
    const zones = moveTablesData.zones || {};
    let html = '';
    let totalFreeTables = 0;
    let totalTables = 0;
    
    // Sort zones alphabetically
    const sortedZoneNames = Object.keys(zones).sort((a, b) => a.localeCompare(b, 'tr'));
    
    sortedZoneNames.forEach(zoneName => {
        const zone = zones[zoneName];
        const tables = zone.tables || [];
        
        // Filter: Exclude current table
        let filteredTables = tables.filter(table => table.table_id !== currentMoveTableId);
        
        // Apply status filter
        if (moveTableFilter === 'free') {
            filteredTables = filteredTables.filter(table => (table.status || 'FREE') === 'FREE');
        }
        
        // Sort tables by name naturally
        filteredTables.sort((a, b) => {
            const nameA = a.name || '';
            const nameB = b.name || '';
            return nameA.localeCompare(nameB, 'tr', { numeric: true });
        });
        
        if (filteredTables.length === 0) return;
        
        // Count tables
        const freeTablesInZone = filteredTables.filter(t => (t.status || 'FREE') === 'FREE').length;
        totalFreeTables += freeTablesInZone;
        totalTables += filteredTables.length;
        
        // Zone header
        html += `
            <div class="mb-6">
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-8 h-8 bg-slate-200 rounded-lg flex items-center justify-center">
                        <svg class="w-4 h-4 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                        </svg>
                    </div>
                    <h3 class="text-lg font-black text-slate-800">${escapeHtml(zoneName)}</h3>
                    <span class="text-xs font-bold text-slate-400 bg-slate-100 px-2 py-1 rounded-full">${filteredTables.length} masa</span>
                </div>
                <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 gap-2 sm:gap-3">
        `;
        
        filteredTables.forEach(table => {
            const tblId = table.table_id || '';
            const tblName = table.name || '';
            const status = table.status || 'FREE';
            const isFree = status === 'FREE';
            
            html += `
                <button onclick="selectTableForMove('${escapeJs(tblId)}', '${escapeJs(tblName)}')" 
                        class="group relative p-3 sm:p-4 rounded-2xl transition-all duration-200 touch-manipulation ${
                            isFree 
                                ? 'bg-white border-2 border-emerald-200 hover:border-emerald-400 hover:shadow-lg hover:shadow-emerald-100 hover:-translate-y-0.5' 
                                : 'bg-slate-50 border-2 border-slate-200 opacity-60 cursor-not-allowed'
                        }"
                        ${!isFree ? 'disabled' : ''}
                        data-table-id="${tblId}">
                    ${isFree ? `
                        <div class="absolute -top-1.5 -right-1.5 w-4 h-4 bg-emerald-500 rounded-full border-2 border-white shadow-sm"></div>
                    ` : ''}
                    <div class="text-center">
                        <div class="font-black text-sm sm:text-base text-slate-800 mb-0.5 truncate">${escapeHtml(tblName)}</div>
                        <div class="text-[10px] sm:text-xs font-medium ${isFree ? 'text-emerald-600' : 'text-amber-700'}">
                            ${isFree ? 'Boş' : 'Dolu'}
                        </div>
                    </div>
                    ${isFree ? `
                        <div class="absolute inset-0 rounded-2xl bg-emerald-500/0 group-hover:bg-emerald-500/5 transition-all pointer-events-none"></div>
                    ` : ''}
                </button>
            `;
        });
        
        html += `
                </div>
            </div>
        `;
    });
    
    if (!html) {
        html = `
            <div class="flex flex-col items-center justify-center py-16">
                <svg class="w-20 h-20 text-slate-200 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                </svg>
                <p class="text-slate-500 font-bold text-lg mb-1">Uygun Masa Yok</p>
                <p class="text-slate-400 text-sm">${moveTableFilter === 'free' ? 'Şu anda boş masa bulunmuyor' : 'Hiç masa bulunamadı'}</p>
            </div>
        `;
    }
    
    container.innerHTML = html;
}

// Select table for move (prevent multiple simultaneous requests)
let isMovingTable = false;

async function selectTableForMove(toTableId, toTableName) {
    // Prevent multiple simultaneous requests
    if (isMovingTable) {
        console.log('Table move already in progress, ignoring click');
        return;
    }
    
    if (!currentMoveTableId) {
        window.NotificationManager.error('Hata: Kaynak masa bulunamadı');
        return;
    }
    
    if (currentMoveTableId === toTableId) {
        window.NotificationManager.error('Aynı masayı seçemezsiniz');
        return;
    }
    
    let confirmed = false;
    if (window.NotificationManager && window.NotificationManager.confirm) {
        confirmed = await window.NotificationManager.confirm(`${toTableName} masasına taşımak istediğinizden emin misiniz?`, 'Masa Taşı');
    } else {
        confirmed = confirm(`${toTableName} masasına taşımak istediğinizden emin misiniz?`);
    }
    if (!confirmed) {
        console.log('User cancelled table move');
        return;
    }
    
    // Set lock after confirmation
    isMovingTable = true;
    
    try {
        const response = await fetch(`${baseUrl}/api/waiter/move-table`, {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify({ 
                from_table_id: currentMoveTableId,
                to_table_id: toTableId
            })
        });
        
        let data = {};
        try {
            data = await response.json();
        } catch (parseError) {
            data = {};
        }
        
        if (!response.ok || !data.success) {
            const errorMsg = (typeof data.error === 'string' && data.error)
                || (typeof data.message === 'string' && data.message)
                || 'Masa taşınamadı';
            window.NotificationManager.error(errorMsg);
            return;
        }

        window.NotificationManager.success('Masa başarıyla taşındı');
        closeMoveTableModal();
        loadTableOrders();
        setTimeout(() => {
            window.location.reload();
        }, 1000);
    } catch (error) {
        console.error('Error moving table:', error);
        const errorMsg = error.message || 'Hata oluştu';
        window.NotificationManager.error(errorMsg);
    } finally {
        // Always release lock
        isMovingTable = false;
    }
}

// Search handler for move table modal
const tableSearchMoveInput = document.getElementById('table-search-move');
if (tableSearchMoveInput) {
    tableSearchMoveInput.addEventListener('input', () => {
        loadTablesForMove();
    });
}

    // Delete order item group (multiple same-product items)
    async function requestDeleteItemGroup(orderItemIds, itemName) {
        const ids = Array.isArray(orderItemIds) ? orderItemIds : [orderItemIds];
        for (const id of ids) {
            await requestDeleteItem(id, itemName);
        }
    }
    
    // Reduce quantity for group - call group API
    async function requestReduceQuantityGroup(orderItemIds, currentQuantity, itemName) {
        if (currentQuantity <= 1) return;
        let newQuantity;
        if (currentQuantity === 2) {
            newQuantity = 1;
        } else {
            const val = await (window.NotificationManager && window.NotificationManager.prompt
                ? window.NotificationManager.prompt('Kaç adete düşürmek istiyorsunuz?', 'Yeni adet (1-' + currentQuantity + '):', (currentQuantity - 1).toString())
                : Promise.resolve(prompt('Kaç adete düşürmek istiyorsunuz? (1-' + currentQuantity + ')', currentQuantity - 1)));
            if (val === null || val === undefined || val === '') return;
            const parsed = parseInt(val, 10);
            if (isNaN(parsed) || parsed < 1 || parsed >= currentQuantity) {
                if (window.NotificationManager && window.NotificationManager.error) {
                    window.NotificationManager.error('Geçerli bir adet girin (1-' + (currentQuantity - 1) + ')');
                }
                return;
            }
            newQuantity = parsed;
        }
        const ids = Array.isArray(orderItemIds) ? orderItemIds : [orderItemIds];
        try {
            const response = await fetch(`${baseUrl}/api/waiter/update-order-item-group`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                body: JSON.stringify({ order_item_ids: ids, quantity: newQuantity })
            });
            const data = await response.json();
            if (data.success) {
                window.NotificationManager.success(data.message || 'Miktar güncellendi');
                loadTableOrders();
            } else {
                window.NotificationManager.error(data.error || 'İşlem başarısız');
            }
        } catch (error) {
            console.error('Error:', error);
            window.NotificationManager.error(error.message || 'Hata oluştu');
        }
    }
    
    // Delete order item (direct - no confirmation needed)
    async function requestDeleteItem(orderItemId, itemName) {
        try {
            const response = await fetch(`${baseUrl}/api/waiter/delete-order-item`, {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify({ 
                    order_item_id: orderItemId
                })
            });
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const data = await response.json();
            
            if (data.success) {
                const msg = data.approval_pending 
                    ? (data.message || 'İşletme yöneticinizden onay bekleniyor. Silme talebi onaya gönderildi.') 
                    : 'Ürün silindi';
                window.NotificationManager.success(msg);
                loadTableOrders();
                return true;
            } else {
                let errorMsg = 'İşlem başarısız';
                if (data.error && typeof data.error === 'string') {
                    errorMsg = data.error;
                }
                window.NotificationManager.error(errorMsg);
                return false;
            }
        } catch (error) {
            console.error('Error deleting order item:', error);
            window.NotificationManager.error(error.message || 'Hata oluştu');
            return false;
        }
    }
    
    // Miktar azaltma - quantity>2 ise prompt ile hedef miktar, 2 ise direkt 1'e düşür (tek tıkla)
    async function requestReduceQuantityWithPrompt(orderItemId, currentQuantity, itemName) {
        if (currentQuantity <= 1) return;
        let newQuantity;
        if (currentQuantity === 2) {
            newQuantity = 1;
        } else {
            const val = await (window.NotificationManager && window.NotificationManager.prompt
                ? window.NotificationManager.prompt('Kaç adete düşürmek istiyorsunuz?', 'Yeni adet (1-' + currentQuantity + '):', (currentQuantity - 1).toString())
                : Promise.resolve(prompt('Kaç adete düşürmek istiyorsunuz? (1-' + currentQuantity + ')', currentQuantity - 1)));
            if (val === null || val === undefined || val === '') return;
            const parsed = parseInt(val, 10);
            if (isNaN(parsed) || parsed < 1 || parsed >= currentQuantity) {
                if (window.NotificationManager && window.NotificationManager.error) {
                    window.NotificationManager.error('Geçerli bir adet girin (1-' + (currentQuantity - 1) + ')');
                }
                return;
            }
            newQuantity = parsed;
        }
        await requestReduceQuantity(orderItemId, currentQuantity, newQuantity, itemName);
    }
    
    // Reduce quantity (direct - no confirmation needed)
    async function requestReduceQuantity(orderItemId, oldQuantity, newQuantity, itemName) {
        try {
            const response = await fetch(`${baseUrl}/api/waiter/reduce-order-item-quantity`, {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify({ 
                    order_item_id: orderItemId,
                    new_quantity: newQuantity
                })
            });
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const data = await response.json();
            
            if (data.success) {
                const msg = data.approval_pending 
                    ? (data.message || 'İşletme yöneticinizden onay bekleniyor. Azaltma talebi onaya gönderildi.') 
                    : 'Miktar güncellendi';
                window.NotificationManager.success(msg);
                loadTableOrders();
                return true;
            } else {
                let errorMsg = 'İşlem başarısız';
                if (data.error && typeof data.error === 'string') {
                    errorMsg = data.error;
                }
                window.NotificationManager.error(errorMsg);
                return false;
            }
        } catch (error) {
            console.error('Error reducing quantity:', error);
            window.NotificationManager.error(error.message || 'Hata oluştu');
            return false;
        }
    }
    
    // Delete all orders for this table (direct - no confirmation needed)
    async function deleteAllTableOrders() {
        if (!tableId) return;
        
        try {
            const response = await fetch(`${baseUrl}/api/waiter/delete-all-table-orders`, {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify({ 
                    table_id: tableId
                })
            });
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const data = await response.json();
            
            if (data.success) {
                const msg = data.approval_pending 
                    ? (data.message || 'İşletme yöneticinizden onay bekleniyor. Tüm silme talepleri onaya gönderildi.') 
                    : (data.message || 'Tüm siparişler silindi');
                window.NotificationManager.success(msg);
                loadTableOrders();
                return true;
            } else {
                window.NotificationManager.error(data.error || 'İşlem başarısız');
                return false;
            }
        } catch (error) {
            console.error('Error deleting all orders:', error);
            window.NotificationManager.error(error.message || 'Hata oluştu');
            return false;
        }
    }
    
// Auto-load orders on page load
document.addEventListener('DOMContentLoaded', function() {
    syncWaiterPosLayoutMode();
    window.addEventListener('resize', syncWaiterPosLayoutMode);

    // Load orders immediately and switch to orders tab
    loadTableOrders();
    switchSidebarTab('orders');
    
    // Refresh orders every 15 seconds (only when orders tab is visible)
    const ordersRefreshInterval = setInterval(() => {
        const ordersContent = document.getElementById('sidebar-orders-content');
        if (ordersContent && !ordersContent.classList.contains('hidden')) {
            loadTableOrders();
        }
    }, 15000);
    
    // Cleanup on page unload
    window.addEventListener('beforeunload', () => {
        clearInterval(ordersRefreshInterval);
    });
});
</script>
