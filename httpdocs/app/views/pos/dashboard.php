<?php
/**
 * POS Dashboard View - React POSModule component'inin PHP versiyonu
 * Basitleştirilmiş versiyon
 */

require_once __DIR__ . '/../../helpers/translations.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/role_helpers.php';

$baseUrl = defined('BASE_URL') ? BASE_URL : '';
$translations = getTranslations(getCurrentLanguage());
$tables = $tables ?? [];
$categories = $categories ?? [];
$menuItems = $menu_items ?? [];
$selectedCategory = $categories[0]['category_id'] ?? '';

// Check if user is cashier (can process payments)
// Manager role should NOT be treated as cashier for UI purposes (should see sidebar)
$currentRole = $_SESSION['role'] ?? null;
$currentRoleId = $_SESSION['role_id'] ?? null;

// Normalize role for comparison
$normalizedRole = null;
if ($currentRole) {
    $normalizedRole = strtoupper(str_replace('ROLE_', '', trim($currentRole)));
}
if (!$normalizedRole && $currentRoleId) {
    try {
        require_once __DIR__ . '/../../services/RoleMapper.php';
        $roleMapper = \App\Services\RoleMapper::getInstance();
        $roleCodeFromId = $roleMapper->getRoleCode($currentRoleId);
        if ($roleCodeFromId) {
            $normalizedRole = strtoupper(str_replace('ROLE_', '', trim($roleCodeFromId)));
        }
    } catch (\Exception $e) {
        // Silent fail
    }
}

// Also check from user model if still not determined
if (!$normalizedRole && isset($_SESSION['user_id'])) {
    try {
        require_once __DIR__ . '/../../models/User.php';
        $userModel = new \App\Models\User();
        $user = $userModel->findByUserId($_SESSION['user_id']);
        if ($user && isset($user['role_id'])) {
            require_once __DIR__ . '/../../services/RoleMapper.php';
            $roleMapper = \App\Services\RoleMapper::getInstance();
            $roleCodeFromUserId = $roleMapper->getRoleCode($user['role_id']);
            if ($roleCodeFromUserId) {
                $normalizedRole = strtoupper(str_replace('ROLE_', '', trim($roleCodeFromUserId)));
            }
        }
    } catch (\Exception $e) {
        // Silent fail
    }
}

$isManager = ($normalizedRole === 'MANAGER');
// Cashier check: role must be CASHIER OR has pos.process_payment permission
// For UI purposes, Manager should also see cashier panel (can process payments)
$isCashierRole = ($normalizedRole === 'CASHIER');
$isCashierPermission = hasPermissionForRole('pos.process_payment');
// UI flag: Manager and Cashier should see cashier panel UI
$isCashierUI = $isManager || $isCashierRole || $isCashierPermission;
// Functional flag: Only actual cashiers or managers can process payments (for fullscreen logic)
$isCashier = (!$isManager && $isCashierRole) || (!$isManager && $isCashierPermission);

$isSuperAdmin = $is_super_admin ?? false;

$onlinePaymentAvailable = false;
try {
    $gw = \App\Core\DependencyFactory::getPaymentGatewayService()->getGateway('iyzico');
    $onlinePaymentAvailable = $gw && $gw->isEnabled();
} catch (\Throwable $e) {
    $onlinePaymentAvailable = false;
}
?>

<?php if ($isSuperAdmin): ?>
<!-- SUPER ADMIN VIEW: Business Selection First -->
<div class="p-3 sm:p-4 md:p-6 lg:p-8 flex-1 overflow-y-auto bg-[#f4f5fa] animate-slide-up no-scrollbar w-full max-w-full overflow-x-hidden q-biz-theme">
    <div id="business-selection-view">
        <header class="flex flex-col sm:flex-row justify-between sm:items-end mb-5 sm:mb-6 lg:mb-8 gap-4 sm:gap-5">
            <div class="flex flex-col gap-3 sm:gap-4 min-w-0 flex-1">
                <h1 class="text-2xl sm:text-3xl lg:text-4xl xl:text-5xl font-black text-slate-900 tracking-tighter break-words">POS - İşletme Seçin</h1>
                <p class="text-slate-600 font-medium">POS sistemine erişmek istediğiniz işletmeyi seçin</p>
            </div>
            <div class="flex-shrink-0">
                <input type="text" id="business-search" placeholder="İşletme ara..." onkeyup="BusinessSelector.searchBusinesses(this.value)"
                       class="w-full sm:w-64 px-4 py-2.5 pl-10 bg-white rounded-xl border border-slate-200 text-sm font-bold outline-none focus:border-indigo-500 transition-all">
            </div>
        </header>
        <div id="business-grid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 sm:gap-6">
            <div class="col-span-full text-center py-12">
                <div class="inline-block animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-indigo-500"></div>
                <p class="mt-4 text-slate-600 font-bold">İşletmeler yükleniyor...</p>
            </div>
        </div>
    </div>
    
    <!-- POS Management View -->
    <div id="pos-management-view" class="hidden">
        <header class="flex items-center gap-3 mb-4">
            <button onclick="backToBusinessSelection()" class="p-2 hover:bg-slate-200 rounded-lg transition-all">
                <svg class="w-6 h-6 text-slate-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
            </button>
            <h1 class="text-xl sm:text-2xl lg:text-3xl font-black text-slate-900 tracking-tighter">
                <span id="selected-business-name"></span> - POS
            </h1>
        </header>
<?php endif; ?>

<div class="flex h-full min-h-0 max-h-full bg-[#f4f5fa] overflow-hidden q-biz-theme q-biz-ops" id="pos-dashboard" <?php echo $isSuperAdmin ? 'style="display: none;"' : ''; ?>>
    <!-- Zone Sidebar - Sol tarafta zone listesi -->
    <div id="zone-sidebar" class="pos-zone-sidebar sidebar-mobile fixed left-0 top-0 h-full w-[280px] sm:w-80 bg-white border border-slate-200 shadow-xl transform transition-transform duration-300 ease-out lg:relative lg:w-64 -translate-x-full lg:translate-x-0 rounded-r-2xl lg:rounded-2xl" style="max-width: 85vw; z-index: 9999; padding-top: env(safe-area-inset-top);">
        <div class="flex flex-col h-full">
            <!-- Sidebar Header -->
            <div class="p-4 sm:p-5 border-b border-slate-200 flex items-center justify-between shrink-0" style="padding-top: max(1rem, env(safe-area-inset-top));">
                <h2 class="text-xl sm:text-2xl font-black text-slate-900"><?php echo t('waiter.zones', 'Bölgeler'); ?></h2>
                <button onclick="toggleSidebar()" class="lg:hidden p-3 hover:bg-slate-100 active:bg-slate-200 rounded-xl touch-manipulation min-w-[44px] min-h-[44px] flex items-center justify-center transition-colors" aria-label="<?php echo t('common.close', 'Kapat'); ?>">
                    <?php echo icon_x(['class' => 'w-6 h-6 text-slate-700']); ?>
                </button>
            </div>
            
            <!-- Zone List - JavaScript ile doldurulacak -->
            <nav class="flex-1 overflow-y-auto p-4 sm:p-5 -webkit-overflow-scrolling-touch" id="zone-list" style="padding-bottom: max(1rem, env(safe-area-inset-bottom));">
                <div class="text-center text-slate-400 py-10 text-base"><?php echo t('common.loading', 'Yükleniyor...'); ?></div>
            </nav>
        </div>
    </div>
    
    <!-- Sidebar Overlay (Mobile) -->
    <div id="sidebar-overlay" class="sidebar-overlay fixed inset-0 bg-black bg-opacity-50 hidden lg:hidden" onclick="toggleSidebar()" style="z-index: 9998; transition: opacity 0.3s ease-out;"></div>
    
    <!-- Main Content -->
    <div class="flex-1 flex flex-col overflow-hidden min-w-0 min-h-0">
    <!-- Tables View -->
    <div class="flex-1 min-h-0 p-3 sm:p-4 md:p-5 lg:p-6 overflow-y-auto no-scrollbar w-full" id="tables-view" style="max-width: 100%; padding-top: max(0.75rem, env(safe-area-inset-top)); padding-bottom: max(1rem, env(safe-area-inset-bottom));">
        <header class="flex flex-col sm:flex-row justify-between sm:items-center mb-3 sm:mb-4 md:mb-5 gap-3 sm:gap-4 shrink-0">
            <div>
                <div class="flex items-center gap-2 sm:gap-3 flex-wrap">
                    <h1 class="text-2xl sm:text-3xl md:text-4xl font-black tracking-tighter text-slate-900 leading-tight shrink-0">
                        <?php echo $isCashierUI ? t('pos.cashierPanel', 'Kasiyer Paneli') : t('pos.title', 'POS'); ?>
                    </h1>
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
                <p class="text-slate-400 font-bold uppercase text-xs sm:text-sm lg:text-base tracking-widest mt-2">
                    <?php echo $isCashierUI ? t('pos.cashierSubtitle', 'Sipariş ve Ödeme İşlemleri') : t('pos.subtitle', 'Point of Sale'); ?>
                </p>
                <!-- Status summary badges - updated dynamically -->
                <div id="pos-status-summary" class="flex items-center gap-2 mt-3 flex-wrap">
                    <!-- Will be populated by JavaScript -->
                </div>
            </div>
            <div class="flex items-center gap-2 sm:gap-3">
                <button onclick="toggleSidebar()" class="lg:hidden p-3 hover:bg-slate-100 active:bg-slate-200 rounded-xl touch-manipulation min-w-[48px] min-h-[48px] flex items-center justify-center transition-colors shadow-sm">
                    <?php echo icon_menu(['class' => 'w-7 h-7 text-slate-700']); ?>
                </button>
                <?php if (!empty($is_cashier) || !empty($tables_grouped)): ?>
                <button onclick="toggleZoneView()" id="zone-view-toggle" class="pos-btn-primary px-4 sm:px-5 py-3 sm:py-3.5 rounded-xl font-bold text-sm sm:text-base transition-all touch-manipulation min-h-[48px]">
                    <span id="zone-view-text" class="whitespace-nowrap"><?php echo t('pos.standardView', 'Standard View'); ?></span>
                </button>
                <?php endif; ?>
                <button onclick="toggleFullscreen()" class="p-3 hover:bg-slate-100 active:bg-slate-200 rounded-xl transition-colors touch-manipulation min-w-[48px] min-h-[48px] flex items-center justify-center shadow-sm" title="<?php echo t('titles.fullscreen', 'Tam Ekran'); ?>">
                    <svg class="w-6 h-6 text-slate-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"></path>
                    </svg>
                </button>
                <a href="<?php echo BASE_URL; ?>/logout" class="p-3 hover:bg-red-100 active:bg-red-200 rounded-xl transition-colors text-red-500 touch-manipulation min-w-[48px] min-h-[48px] flex items-center justify-center shadow-sm" title="<?php echo t('common.logout', 'Çıkış Yap'); ?>">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                    </svg>
                </a>
            </div>
        </header>
        <?php if (empty($tables)): ?>
            <div class="text-center py-20">
                <p class="text-slate-400 text-xl font-bold mb-4"><?php echo t('pos.noTablesYet') ?: 'Henüz masa eklenmemiş'; ?></p>
                <p class="text-slate-300 text-sm"><?php echo t('pos.addTablesFromAdmin') ?: 'Yönetim panelinden masa ekleyebilirsiniz'; ?></p>
            </div>
        <?php else: ?>
        <!-- Zone Grouped View (Default view) -->
        <div id="zone-grouped-view">
            <!-- Zones will be loaded by JavaScript -->
        </div>
        
        <!-- Standard Grid View (Hidden by default) -->
        <div id="standard-grid-view" class="hidden grid gap-3 sm:gap-4 md:gap-5 lg:gap-6 w-full min-w-0" style="grid-template-columns: repeat(auto-fill, minmax(min(100%, 9rem), 1fr)); max-width: 100%;" style="max-width: 100%;">
            <?php foreach ($tables as $table): 
                $tableId = $table['table_id'] ?? '';
                $tableName = $table['name'] ?? t('pos.table');
                $tableZone = $table['zone'] ?? '';
                $tableStatus = $table['status'] ?? 'FREE';
                $isFree = ($tableStatus === 'FREE');
                
                // Simple: DOLU or BOŞ — semantic POS table card classes
                if (!$isFree) {
                    $cardClasses = 'pos-table-card pos-table-card--occupied';
                    $dotClasses = 'pos-status-dot pos-status-dot--occupied';
                    $statusText = t('pos.occupied') ?: 'DOLU';
                    $badgeClasses = 'pos-table-badge pos-table-badge--occupied';
                } else {
                    $cardClasses = 'pos-table-card pos-table-card--empty';
                    $dotClasses = 'pos-status-dot pos-status-dot--empty';
                    $statusText = t('pos.empty') ?: 'BOŞ';
                    $badgeClasses = 'pos-table-badge pos-table-badge--empty';
                }
            ?>
                <div class="relative group">
                    <button onclick="selectTable('<?php echo htmlspecialchars($tableId); ?>', '<?php echo htmlspecialchars($tableName); ?>', '<?php echo htmlspecialchars($tableStatus); ?>')" 
                            class="btn-touch w-full p-4 sm:p-5 md:p-6 lg:p-7 xl:p-8 min-h-[120px] sm:min-h-[140px] md:min-h-[160px] lg:min-h-[180px] xl:min-h-[200px] flex flex-col justify-between transition-all active:scale-[0.98] <?php echo $cardClasses; ?>">
                        <div class="flex items-start gap-2 w-full">
                            <svg class="biz-icon-table-top w-8 h-8 sm:w-9 sm:h-9 shrink-0 text-indigo-500/75" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><rect x="7" y="7" width="10" height="10" rx="1.5" stroke-width="1.5"/><circle cx="12" cy="4.5" r="1" fill="currentColor" stroke="none"/><circle cx="12" cy="19.5" r="1" fill="currentColor" stroke="none"/><circle cx="4.5" cy="12" r="1" fill="currentColor" stroke="none"/><circle cx="19.5" cy="12" r="1" fill="currentColor" stroke="none"/></svg>
                            <div class="flex-1 min-w-0 text-left">
                                <span class="font-black text-base sm:text-lg md:text-xl lg:text-2xl truncate block"><?php echo htmlspecialchars($tableName); ?></span>
                            </div>
                            <div class="<?php echo $dotClasses; ?> ml-1 shrink-0"></div>
                        </div>
                        <div class="text-left mt-1">
                            <span class="<?php echo $badgeClasses; ?>"><?php echo $statusText; ?></span>
                        </div>
                    <?php if (!$isFree): ?>
                        <div class="text-right mt-auto pt-2">
                            <div class="text-base sm:text-lg md:text-xl lg:text-2xl xl:text-3xl 2xl:text-4xl font-black text-slate-900 truncate" id="table-total-<?php echo htmlspecialchars($tableId); ?>">
                                <?php
                                    $tableTotal = floatval($table['total_amount'] ?? 0);
                                    echo number_format($tableTotal, 2, ',', '.') . ' ₺';
                                ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="mt-auto pt-2"></div>
                    <?php endif; ?>
                    </button>
                    <!-- History Button -->
                    <button onclick="event.stopPropagation(); showTableHistory('<?php echo htmlspecialchars($tableId); ?>', '<?php echo htmlspecialchars($tableName); ?>')" 
                            class="btn-touch absolute top-2 right-2 p-2 sm:p-2.5 bg-white rounded-lg sm:rounded-xl opacity-0 group-hover:opacity-100 group-active:opacity-100 sm:group-hover:opacity-100 transition-opacity shadow-md hover:shadow-lg active:shadow-xl z-10 min-w-[40px] min-h-[40px] flex items-center justify-center"
                            title="<?php echo t('common.history', 'Geçmiş'); ?>">
                        <?php echo icon_clock(['class' => 'w-4 h-4 sm:w-5 sm:h-5 text-slate-600']); ?>
                    </button>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Menu/Payment View (Hidden by default) -->
    <div class="flex-1 flex flex-col bg-white overflow-hidden relative hidden w-full" id="menu-payment-view" style="max-width: 100%;">
        <header class="p-2 sm:p-3 md:p-4 lg:p-6 xl:p-8 border-b flex flex-wrap gap-2 sm:gap-3 md:gap-4 items-center justify-between shrink-0">
            <div class="flex items-center gap-2 sm:gap-3 md:gap-4 lg:gap-6">
                <button onclick="showTablesView()" class="px-4 sm:px-6 py-2 sm:py-3 bg-slate-900 text-white rounded-xl sm:rounded-2xl font-black text-xs sm:text-sm hover:bg-slate-800 transition-all flex items-center gap-2">
                    <?php echo icon_arrow_left(['class' => 'w-4 h-4 sm:w-5 sm:h-5']); ?>
                    <span><?php echo t('common.back', 'Back'); ?></span>
                </button>
                <div class="flex flex-col min-w-0">
                    <h2 class="text-sm sm:text-base md:text-lg lg:text-2xl xl:text-3xl font-black tracking-tighter truncate max-w-[80px] sm:max-w-[100px] md:max-w-[120px] lg:max-w-none" id="current-table-name"><?php echo t('pos.table', 'Masa'); ?></h2>
                    <span class="text-[9px] sm:text-[10px] md:text-xs font-semibold text-slate-400 truncate" id="current-table-zone"></span>
                </div>
            </div>
            <div class="flex items-center gap-2 sm:gap-3">
                <?php if (!$isCashierUI): ?>
                <div class="flex gap-1.5 sm:gap-2 md:gap-3 lg:gap-4 p-1 sm:p-1.5 md:p-2 bg-slate-50 rounded-lg sm:rounded-xl md:rounded-2xl lg:rounded-3xl border w-full sm:w-auto">
                    <button onclick="showMenuView()" id="menu-tab-btn" class="flex-1 sm:flex-none px-3 sm:px-4 md:px-6 lg:px-8 py-2 sm:py-2.5 md:py-3 lg:py-4 rounded-md sm:rounded-lg md:rounded-xl lg:rounded-2xl font-black text-[9px] sm:text-[10px] md:text-xs lg:text-sm transition-all bg-slate-900 text-white shadow-xl"><?php echo t('pos.newOrder') ?: 'Yeni Sipariş'; ?></button>
                </div>
                <?php else: ?>
                <div class="pos-chip-toolbar px-4 sm:px-6 py-2 sm:py-3 text-sm sm:text-base">
                    <?php echo t('pos.cashierPanel', 'Kasiyer Paneli'); ?>
                </div>
                <?php endif; ?>
            </div>
        </header>
        
        <div class="flex-1 flex overflow-hidden">
            <!-- Menu View -->
            <div class="flex-1 flex flex-col lg:flex-row overflow-hidden relative hidden" id="menu-view">
                <div class="flex-1 flex flex-col lg:border-r overflow-hidden min-w-0">
                    <div class="px-2 sm:px-3 md:px-4 lg:px-8 py-2 sm:py-3 md:py-4 lg:py-6 flex gap-1.5 sm:gap-2 md:gap-3 lg:gap-4 overflow-x-auto no-scrollbar border-b shrink-0 bg-white">
                        <?php foreach ($categories as $cat): 
                            $catId = $cat['category_id'] ?? '';
                            $catName = $cat['name'] ?? '';
                        ?>
                            <button onclick="selectCategory('<?php echo htmlspecialchars($catId); ?>')" 
                                    class="category-btn px-2.5 sm:px-3 md:px-4 lg:px-8 py-1.5 sm:py-2 md:py-2.5 lg:py-4 rounded-md sm:rounded-lg md:rounded-xl lg:rounded-2xl font-black whitespace-nowrap text-[8px] sm:text-[9px] md:text-[10px] lg:text-sm transition-all <?php echo $catId === $selectedCategory ? 'bg-slate-900 text-white shadow-xl' : 'bg-slate-50 text-slate-400'; ?>" 
                                    data-category="<?php echo htmlspecialchars($catId); ?>">
                                <?php echo htmlspecialchars($catName); ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                    <div class="product-grid-responsive flex-1 p-3 sm:p-4 md:p-5 lg:p-8 overflow-y-auto grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4 gap-3 sm:gap-4 md:gap-5 lg:gap-6 no-scrollbar pb-20 sm:pb-24 lg:pb-8" id="menu-items-container">
                        <?php foreach ($menuItems as $item): 
                            $itemId = $item['menu_item_id'] ?? '';
                            $itemName = $item['name'] ?? '';
                            $itemPrice = $item['price'] ?? 0;
                            $itemImage = $item['image_url'] ?? '';
                            $itemCategory = $item['category_id'] ?? '';
                        ?>
                            <button onclick="addToCart(<?php echo json_encode($itemId); ?>, <?php echo json_encode($itemName); ?>, <?php echo json_encode((float)$itemPrice); ?>)" 
                                    class="btn-touch menu-item-btn p-3 sm:p-4 md:p-5 lg:p-6 rounded-xl sm:rounded-2xl md:rounded-3xl border border-slate-200 hover:border-indigo-200 text-left flex flex-col group transition-all hover:shadow-lg bg-white shadow-sm h-fit"
                                    data-category="<?php echo htmlspecialchars($itemCategory); ?>">
                                <div class="w-full h-20 sm:h-24 md:h-28 lg:h-32 xl:h-40 bg-slate-50 rounded-lg sm:rounded-xl md:rounded-2xl lg:rounded-3xl mb-1.5 sm:mb-2 md:mb-3 lg:mb-4 bg-cover bg-center border-2 md:border-3 lg:border-4 border-white shadow-soft" 
                                     style="background-image: url('<?php echo htmlspecialchars($itemImage); ?>')"></div>
                                <div class="font-black text-slate-800 line-clamp-1 text-[9px] sm:text-[10px] md:text-xs lg:text-base xl:text-lg mb-0.5 sm:mb-1"><?php echo htmlspecialchars($itemName); ?></div>
                                <div class="font-black text-[10px] sm:text-xs md:text-sm lg:text-lg xl:text-xl mt-auto pos-text-money"><?php echo formatCurrency($itemPrice); ?></div>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Cart Sidebar (Desktop) -->
                <div class="hidden lg:flex w-full lg:w-80 xl:w-[400px] 2xl:w-[450px] bg-slate-50 flex-col shrink-0">
                    <div class="p-4 lg:p-6 xl:p-10 border-b bg-white font-black text-lg lg:text-xl xl:text-2xl flex justify-between items-center shrink-0">
                        <span><?php echo t('pos.order') ?: 'Sipariş'; ?></span>
                        <button onclick="clearCart()" class="text-red-400 text-[9px] lg:text-[10px] xl:text-xs font-black uppercase tracking-widest border-b-2 border-red-50 pb-1"><?php echo t('pos.clear') ?: 'Temizle'; ?></button>
                    </div>
                    <div class="flex-1 p-3 lg:p-4 xl:p-8 space-y-2 lg:space-y-3 xl:space-y-4 overflow-y-auto no-scrollbar" id="cart-items">
                        <div class="text-center text-slate-400 py-6 lg:py-8 text-sm"><?php echo t('pos.cartEmpty') ?: 'Sepet boş'; ?></div>
                    </div>
                    <div class="p-4 lg:p-6 xl:p-10 border-t bg-white shrink-0">
                        <div class="flex justify-between items-center mb-4 lg:mb-6">
                            <span class="font-black text-base lg:text-lg xl:text-2xl"><?php echo t('pos.total') ?: 'Toplam'; ?></span>
                            <span class="font-black text-lg lg:text-xl xl:text-2xl 2xl:text-3xl pos-text-money" id="cart-total">0 ₺</span>
                        </div>
                        <button onclick="sendOrder()" class="w-full pos-btn-primary py-4 lg:py-5 xl:py-6 rounded-xl lg:rounded-2xl xl:rounded-3xl font-black text-base lg:text-lg xl:text-xl transition-all">
                            <?php echo t('pos.sendOrder') ?: 'Siparişi Gönder'; ?>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Mobile Cart Bottom Sheet -->
            <?php if (!$isCashierUI): ?>
            <div class="mobile-bottom-sheet-overlay lg:hidden fixed inset-0 z-mobile-overlay hidden">
                <div class="absolute inset-0 bg-black bg-opacity-50" onclick="toggleMobileCart()"></div>
            </div>
            <div class="mobile-bottom-sheet lg:hidden fixed bottom-0 left-0 right-0 bg-white border-t-2 border-slate-200 rounded-t-2xl sm:rounded-t-3xl shadow-2xl z-mobile-sidebar safe-area-bottom" id="mobile-cart-sheet" style="max-height: 70vh;">
                <div class="p-3 sm:p-4 border-b flex justify-between items-center">
                    <h3 class="font-black text-base sm:text-lg"><?php echo t('pos.order'); ?></h3>
                    <div class="flex items-center gap-2 sm:gap-3">
                        <button onclick="clearCart()" class="text-red-400 text-[10px] sm:text-xs font-black uppercase"><?php echo t('pos.clear'); ?></button>
                        <button onclick="toggleMobileCart()" class="p-1.5 sm:p-2 hover:bg-slate-100 rounded-lg">
                            <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                </div>
                <div class="flex-1 p-3 sm:p-4 space-y-2 sm:space-y-3 overflow-y-auto no-scrollbar" id="mobile-cart-items" style="max-height: calc(70vh - 140px);">
                    <div class="text-center text-slate-400 py-6 sm:py-8 text-sm"><?php echo t('pos.cartEmpty'); ?></div>
                </div>
                <div class="p-3 sm:p-4 border-t bg-white">
                    <div class="flex justify-between items-center mb-3 sm:mb-4">
                        <span class="font-black text-base sm:text-lg"><?php echo t('pos.total'); ?></span>
                        <span class="font-black text-xl sm:text-2xl pos-text-money" id="mobile-cart-total">0 ₺</span>
                    </div>
                    <button onclick="sendOrder()" class="w-full pos-btn-primary py-3 sm:py-4 rounded-xl sm:rounded-2xl font-black text-base sm:text-lg transition-all">
                        <?php echo t('pos.sendOrder'); ?>
                    </button>
                </div>
            </div>

            <!-- Mobile Cart Button (Floating) -->
            <button onclick="toggleMobileCart()" id="mobile-cart-button" class="btn-touch lg:hidden fixed bottom-4 right-4 pos-btn-primary p-4 sm:p-5 rounded-full shadow-2xl z-mobile-overlay transition-all safe-area-bottom" style="margin-bottom: max(1rem, env(safe-area-inset-bottom));">
                <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path>
                </svg>
                <span id="mobile-cart-count" class="absolute -top-1 -right-1 bg-red-500 text-white text-[10px] sm:text-xs font-black rounded-full w-4 h-4 sm:w-5 sm:h-5 flex items-center justify-center hidden">0</span>
            </button>
            <?php else: ?>
            <!-- Mobile cart elements hidden for cashiers - they should only use payment view -->
            <div class="mobile-bottom-sheet-overlay lg:hidden fixed inset-0 z-mobile-overlay hidden" id="mobile-cart-sheet-overlay">
                <div class="absolute inset-0 bg-black bg-opacity-50" onclick="toggleMobileCart()"></div>
            </div>
            <div class="mobile-bottom-sheet lg:hidden fixed bottom-0 left-0 right-0 bg-white border-t-2 border-slate-200 rounded-t-2xl sm:rounded-t-3xl shadow-2xl z-mobile-sidebar safe-area-bottom hidden" id="mobile-cart-sheet" style="max-height: 70vh;">
                <div class="p-3 sm:p-4 border-b flex justify-between items-center">
                    <h3 class="font-black text-base sm:text-lg"><?php echo t('pos.order'); ?></h3>
                    <div class="flex items-center gap-2 sm:gap-3">
                        <button onclick="clearCart()" class="text-red-400 text-[10px] sm:text-xs font-black uppercase"><?php echo t('pos.clear'); ?></button>
                        <button onclick="toggleMobileCart()" class="p-1.5 sm:p-2 hover:bg-slate-100 rounded-lg">
                            <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                </div>
                <div class="flex-1 p-3 sm:p-4 space-y-2 sm:space-y-3 overflow-y-auto no-scrollbar" id="mobile-cart-items" style="max-height: calc(70vh - 140px);">
                    <div class="text-center text-slate-400 py-6 sm:py-8 text-sm"><?php echo t('pos.cartEmpty'); ?></div>
                </div>
                <div class="p-3 sm:p-4 border-t bg-white">
                    <div class="flex justify-between items-center mb-3 sm:mb-4">
                        <span class="font-black text-base sm:text-lg"><?php echo t('pos.total'); ?></span>
                        <span class="font-black text-xl sm:text-2xl pos-text-money" id="mobile-cart-total">0 ₺</span>
                    </div>
                    <button onclick="sendOrder()" class="w-full pos-btn-primary py-3 sm:py-4 rounded-xl sm:rounded-2xl font-black text-base sm:text-lg transition-all">
                        <?php echo t('pos.sendOrder'); ?>
                    </button>
                </div>
            </div>
            <button onclick="toggleMobileCart()" id="mobile-cart-button" class="btn-touch lg:hidden fixed bottom-4 right-4 pos-btn-primary p-4 sm:p-5 rounded-full shadow-2xl z-mobile-overlay transition-all safe-area-bottom hidden" style="margin-bottom: max(1rem, env(safe-area-inset-bottom));">
                <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path>
                </svg>
                <span id="mobile-cart-count" class="absolute -top-1 -right-1 bg-red-500 text-white text-[10px] sm:text-xs font-black rounded-full w-4 h-4 sm:w-5 sm:h-5 flex items-center justify-center hidden">0</span>
            </button>
            <?php endif; ?>

            <!-- Payment View - Modern Minimal Design -->
            <div class="flex-1 flex flex-col overflow-hidden hidden bg-slate-50" id="payment-view" style="padding-top: max(0.75rem, env(safe-area-inset-top));">
                <!-- Minimal Header -->
                <header class="bg-white border-b border-slate-200 shrink-0">
                    <div class="px-4 sm:px-6 lg:px-8 py-4 sm:py-5">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-4">
                                <button onclick="showTablesView()" class="p-2 hover:bg-slate-100 rounded-xl transition-colors">
                                    <svg class="w-5 h-5 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                                    </svg>
                                </button>
                                <div>
                                    <h1 class="text-lg sm:text-xl font-bold text-slate-900"><?php echo t('pos.receivePayment') ?: 'Ödeme Al'; ?></h1>
                                    <div class="flex items-center gap-2 mt-0.5">
                                        <span id="payment-table-status" class="text-sm font-medium text-amber-600"><?php echo t('pos.occupied', 'Dolu'); ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="hidden sm:flex items-center gap-3">
                                <div class="text-right">
                                    <p class="text-xs text-slate-500 font-medium uppercase tracking-wide">Toplam</p>
                                    <p class="text-xl sm:text-2xl font-bold text-slate-900" id="payment-total-header">0 ₺</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </header>
                
                <div class="flex-1 flex flex-col lg:flex-row overflow-hidden min-h-0">
                    <!-- Left Side: Orders List -->
                    <div class="flex-1 flex flex-col bg-white lg:border-r border-slate-200 overflow-hidden min-w-0">
                        <div class="flex-1 overflow-y-auto" style="padding-bottom: max(1rem, env(safe-area-inset-bottom));">
                            <!-- Orders Header -->
                            <div class="sticky top-0 bg-white/95 backdrop-blur-sm border-b border-slate-100 px-4 sm:px-6 py-3 z-10">
                                <div class="flex items-center gap-2">
                                    <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                    </svg>
                                    <h3 class="font-semibold text-slate-700"><?php echo t('pos.currentOrders') ?: 'Mevcut Siparişler'; ?></h3>
                                </div>
                            </div>
                            
                            <!-- Orders List -->
                            <div id="payment-orders-list" class="divide-y divide-slate-100">
                                <div class="flex items-center justify-center py-16">
                                    <div class="text-center">
                                        <div class="inline-block animate-spin rounded-full h-8 w-8 border-2 border-slate-200 border-t-indigo-500 mb-3"></div>
                                        <p class="text-sm text-slate-400"><?php echo t('pos.loading', 'Yükleniyor...'); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Payment Summary - Fixed at bottom -->
                        <div class="border-t border-slate-200 bg-slate-50/80 backdrop-blur-sm shrink-0">
                            <div class="px-4 sm:px-6 py-4 space-y-2">
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-slate-500"><?php echo t('pos.subtotal') ?: 'Ara Toplam'; ?></span>
                                    <span class="text-sm font-semibold text-slate-700" id="payment-subtotal">0 ₺</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-slate-500"><?php echo t('pos.serviceCharge') ?: 'Servis Ücreti'; ?></span>
                                    <span class="text-sm font-semibold text-slate-700" id="payment-service-charge">0 ₺</span>
                                </div>
                                <div class="flex justify-between items-center pt-3 border-t border-slate-200">
                                    <span class="text-base font-semibold text-slate-900"><?php echo t('pos.grandTotal') ?: 'Genel Toplam'; ?></span>
                                    <span class="text-2xl sm:text-3xl font-bold pos-text-money" id="payment-total">0 ₺</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Right Side: Payment Methods -->
                    <?php if ($isCashierUI): ?>
                    <div class="w-full lg:w-80 xl:w-96 flex flex-col bg-white border-t lg:border-t-0 shrink-0" style="padding-bottom: max(1rem, env(safe-area-inset-bottom));">
                        <!-- Payment Methods Header -->
                        <div class="px-4 sm:px-6 py-4 border-b border-slate-100">
                            <h3 class="font-semibold text-slate-700"><?php echo t('pos.paymentMethods') ?: 'Ödeme Yöntemi'; ?></h3>
                        </div>
                        
                        <div class="flex-1 overflow-y-auto p-4 sm:p-5 space-y-3">
                            <!-- Print Adisyon Button -->
                            <button onclick="printOrderAdisyon()" id="print-adisyon-btn" class="btn-touch w-full bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl p-4 transition-all flex items-center gap-3 hidden">
                                <div class="p-2 bg-white rounded-lg shadow-sm">
                                    <svg class="w-5 h-5 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                                    </svg>
                                </div>
                                <span class="font-semibold text-sm"><?php echo t('pos.printAdisyon') ?: 'Adisyon Yazdır'; ?></span>
                            </button>
                            
                            <!-- Payment Options -->
                            <div class="space-y-2.5">
                                <!-- Cash Payment -->
                                <button id="btn-pay-cash" onclick="processPayment('CASH')" class="btn-touch w-full group bg-white border border-slate-200 hover:border-emerald-500 hover:bg-emerald-50/50 rounded-xl p-4 transition-all duration-200 flex items-center gap-4 active:scale-[0.98]">
                                    <div class="p-2.5 bg-emerald-100 rounded-xl group-hover:bg-emerald-200 transition-colors">
                                        <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                                        </svg>
                                    </div>
                                    <div class="flex-1 text-left">
                                        <p class="font-semibold text-slate-900"><?php echo t('payment.cash', 'Nakit'); ?></p>
                                        <p class="text-xs text-slate-500">Nakit ödeme</p>
                                    </div>
                                    <svg class="w-5 h-5 text-slate-300 group-hover:text-emerald-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                    </svg>
                                </button>
                                
                                <!-- Card Payment -->
                                <button id="btn-pay-card" onclick="processPayment('CARD')" class="btn-touch w-full group bg-white border border-slate-200 hover:border-blue-500 hover:bg-blue-50/50 rounded-xl p-4 transition-all duration-200 flex items-center gap-4 active:scale-[0.98]">
                                    <div class="p-2.5 bg-blue-100 rounded-xl group-hover:bg-blue-200 transition-colors">
                                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                                        </svg>
                                    </div>
                                    <div class="flex-1 text-left">
                                        <p class="font-semibold text-slate-900"><?php echo t('payment.card', 'Kredi Kartı'); ?></p>
                                        <p class="text-xs text-slate-500">Kredi/Banka kartı</p>
                                    </div>
                                    <svg class="w-5 h-5 text-slate-300 group-hover:text-blue-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                    </svg>
                                </button>
                                
                                <!-- Mixed Payment -->
                                <button onclick="showMixedPaymentModal()" class="btn-touch w-full group bg-white border border-slate-200 hover:border-violet-500 hover:bg-violet-50/50 rounded-xl p-4 transition-all duration-200 flex items-center gap-4 active:scale-[0.98]">
                                    <div class="p-2.5 bg-violet-100 rounded-xl group-hover:bg-violet-200 transition-colors">
                                        <svg class="w-6 h-6 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                                        </svg>
                                    </div>
                                    <div class="flex-1 text-left">
                                        <p class="font-semibold text-slate-900"><?php echo t('payment.mixed', 'Karışık Ödeme'); ?></p>
                                        <p class="text-xs text-slate-500">Nakit + Kart</p>
                                    </div>
                                    <svg class="w-5 h-5 text-slate-300 group-hover:text-violet-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                    </svg>
                                </button>

                                <?php if (!empty($onlinePaymentAvailable)): ?>
                                <button type="button" onclick="processIyzicoPayment()" class="btn-touch w-full group bg-white border border-slate-200 hover:border-fuchsia-500 hover:bg-fuchsia-50/50 rounded-xl p-4 transition-all duration-200 flex items-center gap-4 active:scale-[0.98]">
                                    <div class="p-2.5 bg-fuchsia-100 rounded-xl group-hover:bg-fuchsia-200 transition-colors">
                                        <svg class="w-6 h-6 text-fuchsia-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                    </div>
                                    <div class="flex-1 text-left">
                                        <p class="font-semibold text-slate-900"><?php echo t('payment.online', 'Online Ödeme'); ?></p>
                                        <p class="text-xs text-slate-500"><?php echo t('payment.onlineHint', 'iyzico ile kart'); ?></p>
                                    </div>
                                    <svg class="w-5 h-5 text-slate-300 group-hover:text-fuchsia-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                    </svg>
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Quick Action Button (Mobile SM view) -->
                        <div class="sm:hidden px-4 pb-4 border-t border-slate-100 pt-4">
                            <div class="flex items-center justify-between mb-3">
                                <span class="text-sm text-slate-500">Toplam</span>
                                <span class="text-lg font-bold pos-text-money" id="payment-total-mobile">0 ₺</span>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                    <!-- Non-cashier message -->
                    <div class="w-full lg:w-80 xl:w-96 flex items-center justify-center p-6 bg-slate-50">
                        <div class="text-center">
                            <div class="w-12 h-12 bg-indigo-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m0 0v2m0-2h2m-2 0H10m9.374-10.606A2 2 0 0119 6h-2m-4 0V4a2 2 0 10-4 0v2m4 0h-4m4 0a2 2 0 012 2v6a2 2 0 01-2 2H9a2 2 0 01-2-2V8a2 2 0 012-2"/>
                                </svg>
                            </div>
                            <p class="text-slate-700 font-medium mb-2">
                                <?php echo t('pos.paymentOnlyCashier') ?? 'Ödeme işlemi sadece kasiyer tarafından yapılabilir.'; ?>
                            </p>
                            <p class="text-sm text-slate-500">
                                <?php echo t('pos.contactCashier', 'Lütfen kasiyere başvurun.'); ?>
                            </p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

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
const posTranslations = <?php echo _safeJson($translations['pos'] ?? [], '{}'); ?>;
// System settings from backend
const systemSettings = {
    serviceChargeRate: <?php echo floatval($service_charge_rate ?? 0); ?> // Service charge rate as percentage (0-100)
};
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

// Additional POS translations for JavaScript
const posJsTranslations = {
    cashierPanel: <?php echo json_encode(t('pos.cashierPanel') ?? 'Kasiyer Paneli', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
    cashierSubtitle: <?php echo json_encode(t('pos.cashierSubtitle') ?? 'Sipariş ve Ödeme İşlemleri', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
    paymentConfirm: <?php echo json_encode(t('notifications.paymentConfirm'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
    paymentConfirmMessage: <?php echo json_encode(t('notifications.paymentConfirmMessage'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
    remainingAmount: <?php echo json_encode(t('notifications.remainingAmount'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
    remainingAmountConfirm: <?php echo json_encode(t('notifications.remainingAmountConfirm'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
    fullscreen: <?php echo json_encode(t('titles.fullscreen'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
    history: <?php echo json_encode(t('titles.history'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
    remove: <?php echo json_encode(t('titles.remove'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
    showMenu: <?php echo json_encode(t('titles.showMenu'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
    hideMenu: <?php echo json_encode(t('titles.hideMenu'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
    errorTableSelect: <?php echo json_encode(t('pos.errorTableSelect') ?? 'Masa seçilirken hata oluştu. Lütfen tekrar deneyin.', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
    errorTableSelectWithMessage: <?php echo json_encode(t('pos.errorTableSelectWithMessage') ?? 'Masa seçilirken hata oluştu: {message}', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
    warningCashierCannotAddItems: <?php echo json_encode(t('pos.warningCashierCannotAddItems') ?? 'Kasiyerler ürün ekleyemez. Sadece ödeme alabilirsiniz.', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
    warningPaymentOnlyCashier: <?php echo json_encode(t('pos.warningPaymentOnlyCashier') ?? 'Ödeme işlemi sadece kasiyer tarafından yapılabilir.', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
    errorPageElementsNotFound: <?php echo json_encode(t('pos.errorPageElementsNotFound') ?? 'Sayfa elementleri bulunamadı. Sayfayı yenileyin.', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
    infoTableOccupied: <?php 
        $infoTableOccupied = t('pos.infoTableOccupied', 'Bu masa dolu. Ödeme işlemi için kasiyere başvurun.');
        echo json_encode(empty($infoTableOccupied) ? 'Bu masa dolu. Ödeme işlemi için kasiyere başvurun.' : $infoTableOccupied, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); 
    ?>,
    infoTableEmpty: <?php 
        $infoTableEmpty = t('pos.infoTableEmpty', 'Bu masa boş. Ödeme için sipariş bekleniyor.');
        echo json_encode(empty($infoTableEmpty) ? 'Bu masa boş. Ödeme için sipariş bekleniyor.' : $infoTableEmpty, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); 
    ?>,
    history: <?php echo json_encode(t('common.history') ?? 'Geçmiş', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
    tableHistory: <?php echo json_encode(t('pos.tableHistory') ?? 'Masa Geçmişi', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
    loading: <?php echo json_encode(t('common.loading') ?? 'Yükleniyor...', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
    remove: <?php echo json_encode(t('common.remove') ?? 'Remove', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>
};

// Ensure escapeHtml is available (fallback if utils.js hasn't loaded yet)
if (typeof window.escapeHtml === 'undefined') {
    window.escapeHtml = function(text) {
        if (typeof text === 'undefined' || text === null) return '';
        const div = document.createElement('div');
        div.textContent = String(text);
        return div.innerHTML;
    };
}

// formatCurrency and escapeHtml are now available globally from utils.js

// Ensure BASE_URL is always defined with fallback
const baseUrl = <?php echo json_encode($baseUrl, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?> || window.BASE_URL || '';
window.BASE_URL = baseUrl;

/** POS design-system class helpers (shared with poller / zone renderer) */
const POS_ZONE_ICON = '<svg class="pos-zone-nav-icon w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v14a1 1 0 01-1 1H5a1 1 0 01-1-1V5z"/><rect x="7" y="7" width="3.5" height="2.5" rx=".5" stroke-width="1.5"/><rect x="13.5" y="7" width="3.5" height="2.5" rx=".5" stroke-width="1.5"/><circle cx="8.5" cy="16" r="1.75" stroke-width="1.5"/><circle cx="15.5" cy="16" r="1.75" stroke-width="1.5"/></svg>';
const POS_TABLE_TOP_ICON = '<svg class="biz-icon-table-top w-8 h-8 sm:w-9 sm:h-9 shrink-0 text-indigo-500/75" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><rect x="7" y="7" width="10" height="10" rx="1.5" stroke-width="1.5"/><circle cx="12" cy="4.5" r="1" fill="currentColor" stroke="none"/><circle cx="12" cy="19.5" r="1" fill="currentColor" stroke="none"/><circle cx="4.5" cy="12" r="1" fill="currentColor" stroke="none"/><circle cx="19.5" cy="12" r="1" fill="currentColor" stroke="none"/></svg>';

function posZoneNavClass(isActive) {
    const base = 'btn-touch pos-zone-nav-btn w-full px-5 py-4 sm:py-5 rounded-xl font-bold text-base sm:text-lg mb-3 text-left transition-all active:scale-[0.98]';
    return base + (isActive ? ' pos-zone-nav-btn--active' : ' pos-zone-nav-btn--inactive');
}

function posTableCardClass(hasActiveOrders) {
    const base = 'btn-touch pos-table-card w-full p-4 sm:p-5 md:p-6 rounded-xl sm:rounded-2xl min-h-[100px] sm:min-h-[120px] md:min-h-[140px] transition-all active:scale-[0.98]';
    return base + (hasActiveOrders ? ' pos-table-card--occupied' : ' pos-table-card--empty');
}

function applyPosTableCardState(card, hasActiveOrders) {
    if (!card) return;
    card.classList.remove('pos-table-card--occupied', 'pos-table-card--empty');
    card.classList.add(hasActiveOrders ? 'pos-table-card--occupied' : 'pos-table-card--empty');
    const badge = card.querySelector('.pos-table-badge');
    if (badge) {
        badge.classList.remove('pos-table-badge--occupied', 'pos-table-badge--empty');
        badge.classList.add(hasActiveOrders ? 'pos-table-badge--occupied' : 'pos-table-badge--empty');
        badge.textContent = hasActiveOrders ? 'DOLU' : 'BOŞ';
    }
    const dot = card.querySelector('.pos-status-dot');
    if (dot) {
        dot.classList.remove('pos-status-dot--occupied', 'pos-status-dot--empty');
        dot.classList.add(hasActiveOrders ? 'pos-status-dot--occupied' : 'pos-status-dot--empty');
    }
}

function setPosZoneNavActive(zoneName) {
    document.querySelectorAll('#zone-list button[data-zone]').forEach(btn => {
        const isActive = btn.getAttribute('data-zone') === zoneName;
        btn.className = posZoneNavClass(isActive) + (btn.classList.contains('zone-item') ? ' zone-item' : '');
    });
}

// CSRF Token for API requests - use window.CSRF_TOKEN from admin_layout (already defined)
// Fallback to meta tag or PHP variable if window.CSRF_TOKEN is not available
// Use let instead of const to allow updates when DOM is ready
let csrfToken = '';
if (typeof window !== 'undefined' && window.CSRF_TOKEN) {
    csrfToken = window.CSRF_TOKEN;
} else if (typeof document !== 'undefined') {
    const metaToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    if (metaToken) csrfToken = metaToken;
}
if (!csrfToken) {
    csrfToken = <?php echo json_encode($csrf_token ?? '', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?> || '';
}

// Update token when DOM is ready (in case window.CSRF_TOKEN loads after this script)
if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            if (!csrfToken && typeof window !== 'undefined' && window.CSRF_TOKEN) {
                csrfToken = window.CSRF_TOKEN;
            } else if (!csrfToken) {
                const metaToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                if (metaToken) csrfToken = metaToken;
            }
        });
    } else {
        // DOM already loaded, try to get token now
        if (!csrfToken && typeof window !== 'undefined' && window.CSRF_TOKEN) {
            csrfToken = window.CSRF_TOKEN;
        } else if (!csrfToken) {
            const metaToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            if (metaToken) csrfToken = metaToken;
        }
    }
}
let currentTableId = null;
let currentTableName = null;
let currentTableStatus = null;
let cart = [];
let selectedCategoryId = '<?php echo htmlspecialchars($selectedCategory); ?>';
let lastTablesUpdate = null;
const isCashier = <?php echo json_encode($isCashier); ?>;
const isCashierUI = <?php echo json_encode($isCashierUI); ?>;
const requiresApprovalForOrderEdit = <?php echo !empty($requiresApprovalForOrderEdit) ? 'true' : 'false'; ?>;
const staffShowDeleteReduceButtons = <?php echo !empty($staffShowDeleteReduceButtons) ? 'true' : 'false'; ?>;
const managerShowDeleteReduceButtons = <?php echo !empty($managerShowDeleteReduceButtons) ? 'true' : 'false'; ?>;
const orderEditApprovalEnabled = <?php echo !empty($orderEditApprovalEnabled) ? 'true' : 'false'; ?>;
// Tek mantık: Onay açıkken sadece ilgili role'ün toggle'ı açıksa butonlar görünsün (yönetici → manager toggle, personel → staff toggle)
const showDeleteReduceButtons = orderEditApprovalEnabled && ((requiresApprovalForOrderEdit && staffShowDeleteReduceButtons) || (!requiresApprovalForOrderEdit && managerShowDeleteReduceButtons));

// Onay/Red geri bildirimi - kasiyer talep ettiği silme/azaltma işleminin sonucunu görsün
(function approvalFeedbackPolling() {
    const apiPrefix = (window.location.pathname || '').indexOf('/qodmin/') !== -1 ? 'api/qodmin' : 'api/business';
    let lastSince = Math.floor(Date.now() / 1000) - 30;
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

function selectTable(tableId, tableName, status) {
    try {
        if (!tableId || !tableName) {
            console.error('selectTable: Missing parameters', { tableId, tableName, status });
            if (window.NotificationManager) {
                window.NotificationManager.error(posJsTranslations.errorTableSelect);
            }
            return;
        }
        
        currentTableId = tableId;
        currentTableName = tableName;
        currentTableStatus = status;
        
        const currentTableNameEl = document.getElementById('current-table-name');
        const tablesViewEl = document.getElementById('tables-view');
        const menuPaymentViewEl = document.getElementById('menu-payment-view');
        
        if (!currentTableNameEl || !tablesViewEl || !menuPaymentViewEl) {
            console.error('selectTable: Required elements not found', {
                currentTableName: !!currentTableNameEl,
                tablesView: !!tablesViewEl,
                menuPaymentView: !!menuPaymentViewEl
            });
            if (window.NotificationManager) {
                window.NotificationManager.error(posJsTranslations.errorPageElementsNotFound);
            }
            return;
        }
        
        currentTableNameEl.textContent = tableName;
        
        // Update zone info for the selected table
        const currentTableZoneEl = document.getElementById('current-table-zone');
        if (currentTableZoneEl) {
            // Find zone name from tablesGroupedData
            let foundZone = '';
            if (tablesGroupedData) {
                Object.keys(tablesGroupedData).forEach(zoneName => {
                    const tables = tablesGroupedData[zoneName] || [];
                    if (tables.some(t => t.table_id === tableId)) {
                        foundZone = zoneName;
                    }
                });
            }
            currentTableZoneEl.textContent = foundZone || '';
        }
        
        tablesViewEl.classList.add('hidden');
        menuPaymentViewEl.classList.remove('hidden');
        
        // Always check for active orders first, regardless of table status
        // This ensures we show correct information even if table status is out of sync
        fetch(`${baseUrl}/api/pos/table-orders?table_id=${tableId}`)
            .then(response => response.json())
            .then(orders => {
                // Filter out SERVED and CANCELLED orders
                const activeOrders = Array.isArray(orders) ? orders.filter(order => {
                    const orderStatus = order.status || '';
                    return orderStatus !== 'SERVED' && orderStatus !== 'CANCELLED';
                }) : [];
                
                const hasActiveOrders = activeOrders.length > 0;
                
                if (!hasActiveOrders) {
                    // No active orders - table is free - don't show payment view
                    if (isCashierUI) {
                        // Show tables view instead of payment view for empty tables
                        if (window.NotificationManager) {
                            const emptyTableMsg = posJsTranslations.infoTableEmpty || 'Bu masa boş. Ödeme için sipariş bekleniyor.';
                            if (emptyTableMsg && emptyTableMsg.trim() !== '') {
                                window.NotificationManager.info(emptyTableMsg);
                            }
                        }
                        showTablesView();
                    } else {
                        showMenuView();
                    }
                } else {
                    // Has active orders - show payment view for cashiers (including managers), menu view for others
                    if (isCashierUI) {
                        // Ensure payment view is shown and orders are loaded immediately
                        showPaymentView();
                        loadTableOrders(tableId);
                    } else {
                        // For non-cashiers, show menu view even for occupied tables
                        showMenuView();
                        if (window.NotificationManager) {
                            const occupiedMsg = posJsTranslations.infoTableOccupied || 'Bu masa dolu. Ödeme işlemi için kasiyere başvurun.';
                            if (occupiedMsg && occupiedMsg.trim() !== '') {
                                window.NotificationManager.info(occupiedMsg);
                            }
                        }
                    }
                }
            })
            .catch(error => {
                console.error('Error checking table orders:', error);
                // Fallback to original behavior based on status
                if (status === 'FREE') {
                    // Don't show payment view for empty tables
                    if (isCashierUI) {
                        if (window.NotificationManager) {
                            const emptyTableMsg = posJsTranslations.infoTableEmpty || 'Bu masa boş. Ödeme için sipariş bekleniyor.';
                            if (emptyTableMsg && emptyTableMsg.trim() !== '') {
                                window.NotificationManager.info(emptyTableMsg);
                            }
                        }
                        showTablesView();
                    } else {
                        showMenuView();
                    }
                } else {
                    // Has orders based on status - show payment view for cashiers
                    if (isCashierUI) {
                        showPaymentView();
                        loadTableOrders(tableId);
                    } else {
                        showMenuView();
                        if (window.NotificationManager) {
                            const occupiedMsg = posJsTranslations.infoTableOccupied || 'Bu masa dolu. Ödeme işlemi için kasiyere başvurun.';
                            if (occupiedMsg && occupiedMsg.trim() !== '') {
                                window.NotificationManager.info(occupiedMsg);
                            }
                        }
                    }
                }
            });
    } catch (error) {
        console.error('selectTable error:', error);
        if (window.NotificationManager) {
            window.NotificationManager.error(posJsTranslations.errorTableSelectWithMessage.replace('{message}', error.message));
        }
    }
}

function showTablesView() {
    // Show zone sidebar when returning to tables view
    const zoneSidebar = document.getElementById('zone-sidebar');
    if (zoneSidebar) {
        zoneSidebar.classList.remove('hidden');
    }
    
    document.getElementById('tables-view').classList.remove('hidden');
    document.getElementById('menu-payment-view').classList.add('hidden');
    cart = [];
    updateCart();
    
    // Hide mobile cart elements when returning to tables view
    const mobileCartSheet = document.getElementById('mobile-cart-sheet');
    const mobileCartButton = document.getElementById('mobile-cart-button');
    const mobileCartOverlay = document.querySelector('.mobile-bottom-sheet-overlay');
    
    if (mobileCartSheet) {
        mobileCartSheet.classList.add('hidden');
        mobileCartSheet.classList.remove('open');
    }
    if (mobileCartButton) {
        // Only hide if cashier UI, otherwise keep it visible for non-cashiers
        if (isCashierUI) {
            mobileCartButton.classList.add('hidden');
        }
    }
    if (mobileCartOverlay) {
        mobileCartOverlay.classList.add('hidden');
        mobileCartOverlay.classList.remove('open');
    }
    
    // Clear current table selection
    currentTableId = null;
    currentTableName = null;
    currentTableStatus = null;
}

function showMenuView() {
    // Cashiers (including managers) should not see menu view - they should use payment view
    if (isCashierUI) {
        if (window.NotificationManager) {
            window.NotificationManager.warning(posJsTranslations.warningCashierCannotAddItems);
        }
        return;
    }
    document.getElementById('menu-view').classList.remove('hidden');
    document.getElementById('payment-view').classList.add('hidden');
    
    // Show mobile cart elements on menu view (for non-cashiers to add items)
    const mobileCartButton = document.getElementById('mobile-cart-button');
    if (mobileCartButton) {
        mobileCartButton.classList.remove('hidden');
    }
    
    const menuTabBtn = document.getElementById('menu-tab-btn');
    if (menuTabBtn) {
        menuTabBtn.classList.add('bg-slate-900', 'text-white', 'shadow-xl');
        menuTabBtn.classList.remove('text-slate-400');
    }
    const paymentTabBtn = document.getElementById('payment-tab-btn');
    if (paymentTabBtn) {
        paymentTabBtn.classList.remove('bg-slate-900', 'text-white', 'shadow-xl');
        paymentTabBtn.classList.add('text-slate-400');
    }
    filterMenuByCategory(selectedCategoryId);
}

function showPaymentView() {
    // Check if user is cashier (including managers for UI purposes)
    if (!isCashierUI) {
        if (window.NotificationManager) {
            window.NotificationManager.warning(posJsTranslations.warningPaymentOnlyCashier);
        }
        showMenuView();
        return;
    }
    
    // Hide zone sidebar when showing payment view
    const zoneSidebar = document.getElementById('zone-sidebar');
    if (zoneSidebar) {
        zoneSidebar.classList.add('hidden');
    }
    
    // Hide tables view and show payment view
    const tablesView = document.getElementById('tables-view');
    const menuPaymentView = document.getElementById('menu-payment-view');
    
    if (tablesView) tablesView.classList.add('hidden');
    if (menuPaymentView) menuPaymentView.classList.remove('hidden');
    
    // For cashiers (including managers), hide menu view and show payment view
    const menuView = document.getElementById('menu-view');
    const paymentView = document.getElementById('payment-view');
    
    if (menuView) menuView.classList.add('hidden');
    if (paymentView) paymentView.classList.remove('hidden');
    
    // Hide mobile cart elements on payment view (cashiers should not add new items)
    const mobileCartSheet = document.getElementById('mobile-cart-sheet');
    const mobileCartButton = document.getElementById('mobile-cart-button');
    const mobileCartOverlay = document.querySelector('.mobile-bottom-sheet-overlay');
    
    if (mobileCartSheet) {
        mobileCartSheet.classList.add('hidden');
        mobileCartSheet.classList.remove('open');
    }
    if (mobileCartButton) {
        mobileCartButton.classList.add('hidden');
    }
    if (mobileCartOverlay) {
        mobileCartOverlay.classList.add('hidden');
        mobileCartOverlay.classList.remove('open');
    }
    
    // Update tab buttons only if they exist (non-cashiers)
    const paymentTabBtn = document.getElementById('payment-tab-btn');
    const menuTabBtn = document.getElementById('menu-tab-btn');
    
    if (paymentTabBtn) {
        paymentTabBtn.classList.add('bg-slate-900', 'text-white', 'shadow-xl');
        paymentTabBtn.classList.remove('text-slate-400');
    }
    if (menuTabBtn) {
        menuTabBtn.classList.remove('bg-slate-900', 'text-white', 'shadow-xl');
        menuTabBtn.classList.add('text-slate-400');
    }
    
    if (currentTableId) {
        loadTableOrders(currentTableId);
    }
}

function selectCategory(categoryId) {
    selectedCategoryId = categoryId;
    document.querySelectorAll('.category-btn').forEach(btn => {
        if (btn.dataset.category === categoryId) {
            btn.classList.add('bg-slate-900', 'text-white', 'shadow-xl');
            btn.classList.remove('bg-slate-50', 'text-slate-400');
        } else {
            btn.classList.remove('bg-slate-900', 'text-white', 'shadow-xl');
            btn.classList.add('bg-slate-50', 'text-slate-400');
        }
    });
    filterMenuByCategory(categoryId);
}

function filterMenuByCategory(categoryId) {
    document.querySelectorAll('.menu-item-btn').forEach(btn => {
        if (btn.dataset.category === categoryId) {
            btn.classList.remove('hidden');
        } else {
            btn.classList.add('hidden');
        }
    });
}

function addToCart(itemId, itemName, price, quantity = 1) {
    const existingItem = cart.find(item => item.id === itemId);
    if (existingItem) {
        existingItem.quantity += quantity;
    } else {
        cart.push({ id: itemId, name: itemName, price: price, quantity: quantity });
    }
    updateCart();
    
    // Show mobile cart if hidden
    if (window.innerWidth < 1024) {
        const sheet = document.getElementById('mobile-cart-sheet');
        if (sheet.style.transform === 'translateY(100%)' || !sheet.style.transform) {
            setTimeout(() => {
                sheet.style.transform = 'translateY(0%)';
            }, 100);
        }
    }
}

function updateCartItemQuantity(itemId, delta) {
    const item = cart.find(item => item.id === itemId);
    if (item) {
        item.quantity += delta;
        if (item.quantity <= 0) {
            removeFromCart(itemId);
        } else {
            updateCart();
        }
    }
}

function removeFromCart(itemId) {
    cart = cart.filter(item => item.id !== itemId);
    updateCart();
}

function clearCart() {
    cart = [];
    updateCart();
}

function updateCart() {
    const cartContainer = document.getElementById('cart-items');
    const cartTotal = document.getElementById('cart-total');
    const mobileCartContainer = document.getElementById('mobile-cart-items');
    const mobileCartTotal = document.getElementById('mobile-cart-total');
    const mobileCartCount = document.getElementById('mobile-cart-count');
    
    const cartItemHtml = (item, total) => `
        <div class="flex justify-between items-center p-2 sm:p-3 md:p-4 bg-white rounded-lg sm:rounded-xl md:rounded-2xl">
            <div class="flex-1 min-w-0">
                <div class="font-black text-xs sm:text-sm md:text-base truncate">${item.name}</div>
                <div class="text-[10px] sm:text-xs text-slate-400">${formatCurrency(item.price)} x ${item.quantity}</div>
            </div>
            <div class="flex items-center gap-1.5 sm:gap-2 md:gap-3 ml-2">
                <div class="flex items-center gap-1 sm:gap-1.5 border border-slate-200 rounded-lg">
                    <button onclick="updateCartItemQuantity('${item.id}', -1)" class="px-2 py-1 text-slate-600 hover:bg-slate-100 transition-colors font-bold">−</button>
                    <span class="px-2 py-1 text-sm font-black min-w-[2ch] text-center">${item.quantity}</span>
                    <button onclick="updateCartItemQuantity('${item.id}', 1)" class="px-2 py-1 text-slate-600 hover:bg-slate-100 transition-colors font-bold">+</button>
                </div>
                <span class="font-black text-sm sm:text-base md:text-lg min-w-[60px] text-right">${formatCurrency(item.price * item.quantity)}</span>
                <button onclick="removeFromCart('${item.id}')" class="text-red-400 text-lg sm:text-xl font-bold hover:text-red-600 transition-colors ml-1" title="${posJsTranslations.remove || 'Remove'}">×</button>
            </div>
        </div>
    `;
    
    if (cart.length === 0) {
        if (cartContainer) cartContainer.innerHTML = '<div class="text-center text-slate-400 py-6 lg:py-8 text-sm">' + (posTranslations.cartEmpty || 'Cart is empty') + '</div>';
        if (mobileCartContainer) mobileCartContainer.innerHTML = '<div class="text-center text-slate-400 py-6 sm:py-8 text-sm">' + (posTranslations.cartEmpty || 'Cart is empty') + '</div>';
        if (cartTotal) cartTotal.textContent = '0 ₺';
        if (mobileCartTotal) mobileCartTotal.textContent = '0 ₺';
        if (mobileCartCount) {
            mobileCartCount.classList.add('hidden');
            mobileCartCount.textContent = '0';
        }
    } else {
        let total = 0;
        const itemsHtml = cart.map(item => {
            total += item.price * item.quantity;
            return cartItemHtml(item, total);
        }).join('');
        
        if (cartContainer) cartContainer.innerHTML = itemsHtml;
        if (mobileCartContainer) mobileCartContainer.innerHTML = itemsHtml;
        if (cartTotal) cartTotal.textContent = formatCurrency(total);
        if (mobileCartTotal) mobileCartTotal.textContent = formatCurrency(total);
        
        const itemCount = cart.reduce((sum, item) => sum + item.quantity, 0);
        if (mobileCartCount) {
            mobileCartCount.textContent = itemCount;
            mobileCartCount.classList.remove('hidden');
        }
    }
}

function toggleMobileCart() {
    const sheet = document.getElementById('mobile-cart-sheet');
    const overlay = document.querySelector('.mobile-bottom-sheet-overlay');
    
    if (sheet) {
        sheet.classList.toggle('open');
    }
    if (overlay) {
        overlay.classList.toggle('open');
    }
}

// Helper function to get CSRF token dynamically - ALWAYS gets fresh token
function getCSRFToken() {
    // Try multiple sources in order of preference - check window.CSRF_TOKEN first
    if (typeof window !== 'undefined' && window.CSRF_TOKEN && window.CSRF_TOKEN.length > 0) {
        return window.CSRF_TOKEN;
    }
    
    // Try meta tag
    if (typeof document !== 'undefined') {
        const metaTag = document.querySelector('meta[name="csrf-token"]');
        if (metaTag) {
            const metaToken = metaTag.getAttribute('content');
            if (metaToken && metaToken.length > 0) {
                return metaToken;
            }
        }
    }
    
    // Fallback to csrfToken variable if defined
    if (typeof csrfToken !== 'undefined' && csrfToken && csrfToken.length > 0) {
        return csrfToken;
    }
    
    // Last resort: try to get from window again (in case it was set after page load)
    if (typeof window !== 'undefined' && window.CSRF_TOKEN && window.CSRF_TOKEN.length > 0) {
        return window.CSRF_TOKEN;
    }
    
    console.error('CSRF Token Error: No token found in any source', {
        windowCSRF: typeof window !== 'undefined' ? (window.CSRF_TOKEN || 'undefined') : 'window undefined',
        metaToken: typeof document !== 'undefined' ? (document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || 'not found') : 'document undefined',
        csrfTokenVar: typeof csrfToken !== 'undefined' ? (csrfToken || 'empty') : 'undefined'
    });
    
    return '';
}

async function refreshCSRFToken() {
    try {
        const resp = await fetch(`${baseUrl}/api/csrf-token`, {
            method: 'GET',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        });
        if (resp.ok) {
            const data = await resp.json();
            if (data.success && data.csrf_token) {
                window.CSRF_TOKEN = data.csrf_token;
                csrfToken = data.csrf_token;
                const metaTag = document.querySelector('meta[name="csrf-token"]');
                if (metaTag) metaTag.setAttribute('content', data.csrf_token);
            }
        }
    } catch (e) {
        // Silent fail - next attempt will retry
    }
}

setInterval(refreshCSRFToken, 5 * 60 * 1000);

function sendOrder() {
    if (!currentTableId || cart.length === 0) return;
    
    // Get fresh CSRF token dynamically
    const currentCsrfToken = getCSRFToken();
    
    if (!currentCsrfToken) {
        console.error('CSRF Token Error:', {
            windowCSRF: typeof window !== 'undefined' ? window.CSRF_TOKEN : 'undefined',
            metaToken: typeof document !== 'undefined' ? document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') : 'undefined',
            csrfToken: typeof csrfToken !== 'undefined' ? csrfToken : 'undefined'
        });
        if (window.NotificationManager) {
            window.NotificationManager.error('Güvenlik token\'ı bulunamadı. Lütfen sayfayı yenileyin.');
        }
        return;
    }
    
    // Convert cart items to order format (id -> menu_item_id)
    const orderItems = cart.map(item => ({
        menu_item_id: item.id,
        quantity: item.quantity,
        price: item.price
    }));
    
    // Debug: Log token before sending
    console.log('Sending order with CSRF token:', {
        tokenLength: currentCsrfToken.length,
        tokenPreview: currentCsrfToken.substring(0, 10) + '...',
        url: `${baseUrl}/pos/create-order`
    });
    
    fetch(`${baseUrl}/pos/create-order`, {
        method: 'POST',
        headers: { 
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-Token': currentCsrfToken
        },
        credentials: 'same-origin', // Ensure cookies/session are sent
        body: JSON.stringify({
            table_id: currentTableId,
            items: orderItems
        })
    })
    .then(response => {
        // Check if response is actually JSON
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            // Response is not JSON, might be HTML error page
            return response.text().then(text => {
                console.error('Non-JSON response received:', text.substring(0, 200));
                throw new Error('Server returned non-JSON response. Please check server logs.');
            });
        }
        
        if (!response.ok) {
            return response.text().then(text => {
                if (!text || text.trim() === '') {
                    return Promise.reject({ error: 'Empty Response', message: 'Sunucudan boş yanıt alındı.', code: response.status });
                }
                try {
                    const json = JSON.parse(text);
                    return Promise.reject(json);
                } catch (e) {
                    return Promise.reject({ error: 'Server Error', message: 'Sunucudan geçersiz JSON yanıtı alındı.', code: response.status, raw: text.substring(0, 200) });
                }
            });
        }
        return response.text().then(text => {
            if (!text || text.trim() === '') {
                throw new Error('Server returned empty response.');
            }
            try {
                return JSON.parse(text);
            } catch (e) {
                if (window.logger) {
                    window.logger.error('JSON parse error:', e, 'Response text:', text.substring(0, 200));
                } else {
                    console.error('JSON parse error:', e, 'Response text:', text.substring(0, 200));
                }
                throw new Error('Server returned invalid JSON response.');
            }
        });
    })
    .then(data => {
        if (data.success) {
            if (window.NotificationManager) {
                window.NotificationManager.success(posTranslations.orderSentSuccess || 'Order sent successfully!');
            }
            clearCart();
            // Update table status without reloading the page
            setTimeout(() => {
                showTablesView();
                // Refresh tables view if zone view is active
                if (zoneViewActive && typeof renderZoneGroupedView === 'function') {
                    renderZoneGroupedView();
                }
                // Note: Tables will be updated via WebSocket or polling, no need to reload page
            }, 500);
        } else {
            if (window.NotificationManager) {
                window.NotificationManager.error('Error: ' + (data.error || (posTranslations.orderSendFailed || 'Failed to send order')));
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
        if (window.NotificationManager) {
            window.NotificationManager.error(posTranslations.errorOccurred || 'An error occurred');
        }
    });
}

// PERFORMANCE OPTIMIZED: loadTableOrders with caching and debouncing
let loadTableOrdersCache = null;
let loadTableOrdersTimeout = null;
let lastTableId = null;

function loadTableOrders(tableId) {
    // Debounce: Cancel previous request if new one comes within 200ms
    if (loadTableOrdersTimeout) {
        clearTimeout(loadTableOrdersTimeout);
    }
    
    // Use cache if same table and cache is fresh (< 1 second)
    if (loadTableOrdersCache && loadTableOrdersCache.tableId === tableId && 
        (Date.now() - loadTableOrdersCache.timestamp) < 1000) {
        updatePaymentDisplay(loadTableOrdersCache.data, tableId);
        return;
    }
    
    // Execute immediately for first call, debounce rapid successive calls
    const executeLoad = () => {
        fetch(`${baseUrl}/api/pos/table-orders?table_id=${tableId}`, {
            cache: 'no-cache',
            headers: {
                'Accept': 'application/json'
            }
        })
            .then(response => {
                if (!response.ok) throw new Error(`HTTP ${response.status}`);
                return response.json();
            })
            .then(data => {
                // Cache the result
                loadTableOrdersCache = {
                    tableId: tableId,
                    data: data,
                    timestamp: Date.now()
                };
                updatePaymentDisplay(data, tableId);
            })
            .catch(error => {
                console.error('Error loading table orders:', error);
                const ordersList = document.getElementById('payment-orders-list');
                if (ordersList) {
                    ordersList.innerHTML = `<div class="text-center text-red-400 py-8 text-sm">Yükleme hatası: ${error.message}</div>`;
                }
                // Reset totals on error
                updatePaymentTotals(0, 0, 0);
            });
    };
    
    // If cache is fresh, use it immediately, otherwise fetch right away
    if (loadTableOrdersCache && loadTableOrdersCache.tableId === tableId && 
        (Date.now() - loadTableOrdersCache.timestamp) < 500) {
        loadTableOrdersTimeout = setTimeout(executeLoad, 50);
    } else {
        executeLoad();
    }
}

function updatePaymentDisplay(data, tableId) {
    let total = 0;
    let subtotal = 0;
    let serviceCharge = 0;
    const ordersList = document.getElementById('payment-orders-list');
    
    if (!ordersList) return;
    
    // Handle error response
    if (data.error) {
        ordersList.innerHTML = `<div class="text-center text-red-400 py-8 text-sm">${data.error}</div>`;
        updatePaymentTotals(0, 0, 0);
        return;
    }
    
    // Filter out SERVED and CANCELLED orders on frontend as well (double check)
    const activeOrders = Array.isArray(data) ? data.filter(order => {
        const status = order.status || '';
        return status !== 'SERVED' && status !== 'CANCELLED';
    }) : [];
    
    // Show/hide adisyon button based on active orders
    const printAdisyonBtn = document.getElementById('print-adisyon-btn');
    if (printAdisyonBtn) {
        if (activeOrders.length > 0) {
            printAdisyonBtn.classList.remove('hidden');
        } else {
            printAdisyonBtn.classList.add('hidden');
        }
    }
    
    if (activeOrders.length > 0) {
        // Calculate totals first (more efficient)
        activeOrders.forEach(order => {
            const orderTotal = parseFloat(order.total_amount || 0);
            total += orderTotal;
            subtotal += orderTotal;
        });
        
        // Calculate service charge
        serviceCharge = subtotal * (systemSettings.serviceChargeRate / 100);
        const grandTotal = subtotal + serviceCharge;
        
        // Display "Delete All" button + orders (only when approval is enabled and user may delete)
        const showDeleteReduce = (typeof showDeleteReduceButtons !== 'undefined' && showDeleteReduceButtons);
        let posOrdersHtml = showDeleteReduce ? `
            <div class="px-4 sm:px-6 py-3 border-b border-slate-100">
                <button onclick="deleteAllTableOrders()" 
                        class="w-full px-4 py-2.5 bg-red-500 hover:bg-red-600 text-white rounded-xl text-sm font-bold transition-all flex items-center justify-center gap-2 shadow-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                    Tüm Siparişleri Sil
                </button>
            </div>
        ` : '';
        
        posOrdersHtml += activeOrders.map(order => {
            const orderTotal = parseFloat(order.total_amount || 0);
            const orderDate = order.created_at ? new Date(order.created_at).toLocaleString('tr-TR', {
                hour: '2-digit',
                minute: '2-digit'
            }) : '';
            const statusColors = {
                'PENDING': 'bg-amber-100 text-amber-700',
                'PREPARING': 'bg-orange-100 text-orange-700',
                'READY': 'bg-emerald-100 text-emerald-700'
            };
            const statusLabels = { PENDING: 'Beklemede', PREPARING: 'Hazırlanıyor', READY: 'Hazır', SERVED: 'Tamamlandı', CANCELLED: 'İptal' };
            const statusClass = statusColors[order.status] || statusColors['PENDING'];
            const statusText = statusLabels[order.status] || order.status || 'Beklemede';
            
            return `
                <div class="border-b border-slate-100 last:border-b-0">
                    <!-- Order Header -->
                    <div class="px-4 sm:px-6 py-3 bg-slate-50/50 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <span class="text-xs font-medium text-slate-500">${orderDate}</span>
                            <span class="px-2 py-0.5 rounded text-[10px] font-medium ${statusClass}">${statusText}</span>
                        </div>
                        <span class="text-sm font-semibold text-slate-700">${formatCurrency(orderTotal)}</span>
                    </div>
                    
                    <!-- Order Items (grouped: same product + same customizations = one line) -->
                    ${order.items && order.items.length > 0 ? `
                        <div class="px-4 sm:px-6 py-2">
                            ${(window.Utils && window.Utils.groupOrderItemsForDisplay ? window.Utils.groupOrderItemsForDisplay(order.items) : order.items).map(item => {
                                const ids = item._order_item_ids && item._order_item_ids.length ? item._order_item_ids : (item.order_item_id ? [item.order_item_id] : []);
                                const idsJson = JSON.stringify(ids).replace(/"/g, '&quot;');
                                const qty = item.quantity || 1;
                                return `
                                <div class="flex items-center justify-between py-2.5 hover:bg-slate-50 -mx-2 px-2 rounded-lg transition-colors">
                                    <div class="flex items-center gap-3 flex-1 min-w-0">
                                        <span class="shrink-0 w-6 h-6 flex items-center justify-center bg-indigo-100 text-indigo-600 rounded-md text-xs font-semibold">${qty}</span>
                                        <span class="text-sm text-slate-700 truncate">${item.item_name || item.name || item.menu_item_name || posTranslations.item || 'Item'}</span>
                                        <div class="flex items-center gap-1">
                                            ${(typeof showDeleteReduceButtons !== 'undefined' && showDeleteReduceButtons) ? `
                                            <button onclick="editOrderItemQuantityOrGroup(${idsJson}, ${qty})" class="p-1.5 text-indigo-500 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors" title="<?php echo t('pos.editQuantity', 'Adet Düzenle'); ?>">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                                </svg>
                                            </button>
                                            <button onclick="removeOrderItemOrGroup(${idsJson})" class="p-1.5 text-red-500 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors" title="<?php echo t('pos.removeItem', 'Ürünü Kaldır'); ?>">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                </svg>
                                            </button>
                                            ` : ''}
                                        </div>
                                    </div>
                                    <span class="text-sm font-medium text-slate-900 shrink-0 ml-3">${formatCurrency((item.price || 0) * (item.quantity || 1))}</span>
                                </div>
                            `;
                            }).join('')}
                        </div>
                    ` : ''}
                </div>
            `;
        }).join('');
        
        ordersList.innerHTML = posOrdersHtml;
                
                // Update totals
                updatePaymentTotals(subtotal, serviceCharge, grandTotal);
                
                // Update table total in tables view if exists
                const tableTotalEl = document.getElementById(`table-total-${tableId}`);
                if (tableTotalEl) tableTotalEl.textContent = formatCurrency(grandTotal);
            } else {
                // No active orders - table should be free
                ordersList.innerHTML = `
                    <div class="flex flex-col items-center justify-center py-16 px-4">
                        <div class="w-12 h-12 bg-slate-100 rounded-full flex items-center justify-center mb-3">
                            <svg class="w-6 h-6 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                            </svg>
                        </div>
                        <p class="text-sm text-slate-500 text-center">${posTranslations.noOrders || 'Bu masa için aktif sipariş bulunmamaktadır'}</p>
                    </div>
                `;
                
                // Hide adisyon button when no active orders
                const printAdisyonBtn = document.getElementById('print-adisyon-btn');
                if (printAdisyonBtn) {
                    printAdisyonBtn.classList.add('hidden');
                }
                
                // Update table status display if available
                const tableStatusEl = document.getElementById('payment-table-status');
                if (tableStatusEl) {
                    tableStatusEl.textContent = posTranslations.empty || 'Boş';
                    tableStatusEl.classList.remove('text-amber-600');
                    tableStatusEl.classList.add('text-slate-400');
                }
                
                updatePaymentTotals(0, 0, 0);
            }
}

function updatePaymentTotals(subtotal, serviceCharge, grandTotal) {
    const subtotalEl = document.getElementById('payment-subtotal');
    const serviceChargeEl = document.getElementById('payment-service-charge');
    const totalEl = document.getElementById('payment-total');
    const totalHeaderEl = document.getElementById('payment-total-header');
    const totalMobileEl = document.getElementById('payment-total-mobile');
    
    if (subtotalEl) subtotalEl.textContent = formatCurrency(subtotal);
    if (serviceChargeEl) serviceChargeEl.textContent = formatCurrency(serviceCharge);
    if (totalEl) totalEl.textContent = formatCurrency(grandTotal);
    if (totalHeaderEl) totalHeaderEl.textContent = formatCurrency(grandTotal);
    if (totalMobileEl) totalMobileEl.textContent = formatCurrency(grandTotal);
}

async function printOrderAdisyon() {
    // Check if user is cashier (including managers)
    if (!isCashierUI) {
        if (window.NotificationManager) {
            window.NotificationManager.error(posJsTranslations.warningPaymentOnlyCashier);
        }
        return;
    }
    
    if (!currentTableId) {
        if (window.NotificationManager) {
            window.NotificationManager.error(<?php echo json_encode(t('pos.errorNoTableSelected', 'Lütfen bir masa seçin'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>);
        }
        return;
    }
    
    // Check if table has active orders
    const ordersList = document.getElementById('payment-orders-list');
    if (ordersList && (ordersList.innerHTML.includes('aktif sipariş bulunmamaktadır') || ordersList.innerHTML.includes('aktif sipariş yok'))) {
        if (window.NotificationManager) {
            window.NotificationManager.warning(<?php echo json_encode(t('pos.warningNoOrders', 'Bu masa için aktif sipariş bulunmamaktadır. Adisyon yazdırılamaz.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>);
        }
        return;
    }
    
    // Get fresh CSRF token dynamically
    const currentCsrfToken = getCSRFToken();
    
    if (!currentCsrfToken) {
        console.error('CSRF Token Error in printOrderAdisyon:', {
            windowCSRF: typeof window !== 'undefined' ? window.CSRF_TOKEN : 'undefined',
            metaToken: typeof document !== 'undefined' ? document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') : 'undefined',
            csrfToken: typeof csrfToken !== 'undefined' ? csrfToken : 'undefined'
        });
        if (window.NotificationManager) {
            window.NotificationManager.error('Güvenlik token\'ı bulunamadı. Lütfen sayfayı yenileyin.');
        }
        return;
    }
    
    // Disable button during request
    const printBtn = document.getElementById('print-adisyon-btn');
    if (printBtn) {
        printBtn.disabled = true;
        printBtn.classList.add('opacity-50', 'cursor-not-allowed');
    }
    
    try {
        const response = await fetch(`${baseUrl}/business/pos/print-adisyon`, {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json',
                'X-CSRF-Token': currentCsrfToken,
                'Accept': 'application/json'
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                table_id: currentTableId
            })
        });
        
        const contentType = response.headers.get('content-type');
        let data;
        if (contentType && contentType.includes('application/json')) {
            data = await response.json();
        } else {
            const text = await response.text();
            console.error('Non-JSON response received:', text.substring(0, 200));
            throw new Error('Server returned non-JSON response');
        }
        
        if (response.ok && data && data.success) {
            if (window.NotificationManager) {
                window.NotificationManager.success(<?php echo json_encode(t('pos.adisyonPrintSuccess', 'Adisyon başarıyla yazdırma kuyruğuna eklendi.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>);
            }
        } else {
            const errorMsg = data?.error || data?.message || <?php echo json_encode(t('pos.adisyonPrintFailed', 'Adisyon yazdırılamadı.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
            if (window.NotificationManager) {
                window.NotificationManager.error(errorMsg);
            }
        }
    } catch (error) {
        console.error('Error printing adisyon:', error);
        if (window.NotificationManager) {
            window.NotificationManager.error(<?php echo json_encode(t('pos.errorOccurred', 'Bir hata oluştu'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?> + ': ' + error.message);
        }
    } finally {
        // Re-enable button after 5 second cooldown to prevent double-print
        setTimeout(() => {
            if (printBtn) {
                printBtn.disabled = false;
                printBtn.classList.remove('opacity-50', 'cursor-not-allowed');
            }
        }, 5000);
    }
}

let _paymentInProgress = false;
async function processPayment(method) {
    if (_paymentInProgress) return;
    
    if (!isCashierUI) {
        if (window.NotificationManager) {
            window.NotificationManager.error(posJsTranslations.warningPaymentOnlyCashier);
        }
        return;
    }
    
    if (!currentTableId) {
        if (window.NotificationManager) {
            window.NotificationManager.error(<?php echo json_encode(t('pos.errorNoTableSelected', 'Lütfen bir masa seçin'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>);
        }
        return;
    }
    
    // Check if table has active orders
    const ordersList = document.getElementById('payment-orders-list');
    if (ordersList && ordersList.innerHTML.includes('aktif sipariş bulunmamaktadır')) {
        if (window.NotificationManager) {
            window.NotificationManager.warning(<?php echo json_encode(t('pos.warningNoOrders', 'Bu masa için aktif sipariş bulunmamaktadır. Ödeme alınamaz.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>);
        }
        return;
    }
    
    const totalElement = document.getElementById('payment-total');
    if (!totalElement) {
        if (window.NotificationManager) {
            window.NotificationManager.error(<?php echo json_encode(t('pos.errorOccurred', 'Bir hata oluştu'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>);
        }
        return;
    }
    
    const total = parseFloat(totalElement.textContent.replace(/[^\d,]/g, '').replace(',', '.'));
    
    if (isNaN(total) || total <= 0) {
        if (window.NotificationManager) {
            window.NotificationManager.error(<?php echo json_encode(t('pos.errorInvalidAmount', 'Geçersiz ödeme tutarı'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>);
        }
        return;
    }
    
    // Process payment directly without confirmation dialog
    if (!window.NotificationManager) {
        console.error('NotificationManager is not available');
        return;
    }
    
    // Get fresh CSRF token dynamically
    const currentCsrfToken = getCSRFToken();
    
    if (!currentCsrfToken) {
        console.error('CSRF Token Error in processPayment:', {
            windowCSRF: typeof window !== 'undefined' ? window.CSRF_TOKEN : 'undefined',
            metaToken: typeof document !== 'undefined' ? document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') : 'undefined',
            csrfToken: typeof csrfToken !== 'undefined' ? csrfToken : 'undefined'
        });
        if (window.NotificationManager) {
            window.NotificationManager.error('Güvenlik token\'ı bulunamadı. Lütfen sayfayı yenileyin.');
        }
        return;
    }
    
    _paymentInProgress = true;
    const payBtns = document.querySelectorAll('#btn-pay-cash, #btn-pay-card');
    payBtns.forEach(b => { b.disabled = true; b.classList.add('opacity-50', 'cursor-not-allowed'); });
    
    fetch(`${baseUrl}/pos/process-payment`, {
        method: 'POST',
        headers: { 
            'Content-Type': 'application/json',
            'X-CSRF-Token': currentCsrfToken,
            'Accept': 'application/json'
        },
        credentials: 'same-origin',
        body: JSON.stringify({
            table_id: currentTableId,
            amount: total,
            method: method,
            tip: 0
        })
    })
    .then(response => {
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            return response.text().then(text => {
                console.error('Non-JSON response received:', text.substring(0, 200));
                throw new Error('Server returned non-JSON response');
            });
        }
        if (!response.ok) {
            return response.json().then(json => Promise.reject(json));
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            if (window.NotificationManager) {
                window.NotificationManager.success(<?php echo json_encode(t('notifications.paymentSuccess', 'Ödeme başarıyla işlendi!'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>);
            }
            showTablesView();
            refreshTablesData();
        } else {
            if (window.NotificationManager) {
                let errorMsg = data.error || data.message || <?php echo json_encode(t('notifications.paymentFailed', 'Ödeme alınamadı'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
                if (typeof errorMsg !== 'string') {
                    errorMsg = <?php echo json_encode(t('notifications.paymentFailed', 'Ödeme alınamadı'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
                }
                window.NotificationManager.error(<?php echo json_encode(t('notifications.error', 'Hata'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?> + ': ' + errorMsg);
            }
        }
    })
    .catch(error => {
        console.error('Payment Error:', error);
        if (error && error.code === 'HAS_ITEMS_IN_PREPARATION') {
            document.getElementById('payment-prep-approval-message').textContent = error.error || 'Bu masada mutfak veya hazırlık ekranında hazırlanan ürünler var. Ödeme almak için yönetici onayı gerekir.';
            document.getElementById('payment-prep-approval-modal').classList.remove('hidden');
            return;
        }
        if (window.NotificationManager) {
            let errorMsg = error && (error.error || error.message) || <?php echo json_encode(t('notifications.unknownError', 'Bilinmeyen hata'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
            if (typeof errorMsg !== 'string') {
                errorMsg = <?php echo json_encode(t('notifications.unknownError', 'Bilinmeyen hata'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
            }
            window.NotificationManager.error(<?php echo json_encode(t('notifications.paymentError', 'Ödeme işlemi sırasında bir hata oluştu'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?> + ': ' + errorMsg);
        }
    })
    .finally(() => {
        setTimeout(() => {
            _paymentInProgress = false;
            payBtns.forEach(b => { b.disabled = false; b.classList.remove('opacity-50', 'cursor-not-allowed'); });
        }, 3000);
    });
}
function closePaymentPrepApprovalModal() {
    document.getElementById('payment-prep-approval-modal').classList.add('hidden');
}
async function requestPaymentPrepCancelFromModal() {
    if (!currentTableId) return;
    const btn = document.getElementById('btn-request-prep-cancel');
    if (btn) { btn.disabled = true; btn.textContent = 'Gönderiliyor...'; }
    const token = getCSRFToken();
    try {
        const res = await fetch(`${baseUrl}/business/pos/request-payment-prep-cancel`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': token, 'Accept': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ table_id: currentTableId })
        });
        const data = await res.json().catch(() => ({}));
        closePaymentPrepApprovalModal();
        if (data.success && window.NotificationManager) {
            window.NotificationManager.success(data.message || 'Yönetici onay talebi gönderildi. Onaylandıktan sonra tekrar ödeme alabilirsiniz.');
        } else if (!data.success && window.NotificationManager) {
            window.NotificationManager.error(data.error || 'Talep gönderilemedi.');
        }
    } catch (e) {
        closePaymentPrepApprovalModal();
        if (window.NotificationManager) window.NotificationManager.error('Talep gönderilemedi.');
    } finally {
        if (btn) { btn.disabled = false; btn.textContent = 'Yönetici Onayı İste'; }
    }
}

// Refresh tables data from API after payment
function refreshTablesData() {
    // Reload grouped tables from API
    fetch(`${baseUrl}/api/pos/tables-grouped`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            // Update tablesGroupedData - convert zones object to grouped format
            const groupedData = {};
            if (data.zones && typeof data.zones === 'object') {
                Object.keys(data.zones).forEach(zoneName => {
                    const zone = data.zones[zoneName];
                    groupedData[zoneName] = zone.tables || [];
                });
            }
            window.tablesGroupedData = groupedData;
            tablesGroupedData = groupedData;
            
            // Update zonesData for sidebar
            if (data.zones && typeof data.zones === 'object') {
                zonesData = data;
                renderZoneList();
            }
            
            // Re-render zone view if active
            if (zoneViewActive && typeof renderZoneGroupedView === 'function') {
                renderZoneGroupedView();
            }
            
            // Update standard grid view tables
            updateStandardGridViewTables(groupedData);
        })
        .catch(error => {
            console.error('Error refreshing tables data:', error);
            // Don't reload page on transient API errors - will retry on next interval
        });
}

// Update standard grid view tables
function updateStandardGridViewTables(groupedData) {
    // PERFORMANCE: Use pre-loaded order data - no per-table API calls
    Object.keys(groupedData).forEach(zoneName => {
        (groupedData[zoneName] || []).forEach(table => {
            const tableId = table.table_id || '';
            if (!tableId) return;
            
            const tableTotal = parseFloat(table.total_amount || 0);
            const hasActiveOrders = (parseInt(table.active_order_count) || 0) > 0;
            
            // Update table total element
            const totalElement = document.getElementById(`table-total-${tableId}`);
            if (totalElement) {
                totalElement.textContent = hasActiveOrders ? formatCurrency(tableTotal) : '';
            }
            
            // Update table card appearance
            const tableCard = document.querySelector(`[onclick*="selectTable('${tableId}'"]`);
            if (tableCard) {
                applyPosTableCardState(tableCard, hasActiveOrders);
            }
        });
    });
}

// Real-time updates
function updateTables(tables) {
    if (!tables || !Array.isArray(tables)) return;
    
    tables.forEach(table => {
        const tableId = table.table_id || '';
        if (!tableId) return;
        
        const tableTotal = parseFloat(table.total_amount || 0);
        const hasActiveOrders = (parseInt(table.active_order_count) || 0) > 0;
        
        // Update standard view total
        const totalElement = document.getElementById(`table-total-${tableId}`);
        if (totalElement) {
            totalElement.textContent = hasActiveOrders ? formatCurrency(tableTotal) : '';
        }
        
        // Update zone view total
        const zoneTotalElement = document.getElementById(`table-total-zone-${tableId}`);
        if (zoneTotalElement) {
            zoneTotalElement.textContent = hasActiveOrders ? formatCurrency(tableTotal) : '';
        }
        
        // Update zone view card appearance
        const card = document.querySelector(`[data-table-card="${tableId}"]`);
        if (card) {
            applyPosTableCardState(card, hasActiveOrders);
        }
        
        // Update standard view card appearance
        const standardCard = document.querySelector(`[onclick*="selectTable('${tableId}'"]`);
        if (standardCard) {
            applyPosTableCardState(standardCard, hasActiveOrders);
        }
    });
}

function updateTableOrders(orders) {
    if (!currentTableId) return;
    
    let total = 0;
    if (Array.isArray(orders)) {
        orders.forEach(order => {
            total += parseFloat(order.total_amount || 0);
        });
    }
    
    const totalElement = document.getElementById('payment-total');
    if (totalElement) {
        totalElement.textContent = formatCurrency(total);
    }
}

// Zone view toggle
let zoneViewActive = true; // Default to zone view
let tablesGroupedData = <?php echo json_encode($tables_grouped ?? [], JSON_UNESCAPED_UNICODE); ?>;
// Make it accessible globally for API updates
window.tablesGroupedData = tablesGroupedData;

function toggleZoneView() {
    const zoneView = document.getElementById('zone-grouped-view');
    const standardView = document.getElementById('standard-grid-view');
    const toggleBtn = document.getElementById('zone-view-toggle');
    const toggleText = document.getElementById('zone-view-text');
    
    if (!zoneView || !standardView || !toggleBtn || !toggleText) {
        console.error('Zone view elements not found');
        if (window.NotificationManager) {
            window.NotificationManager.error('Zone görünümü başlatılamadı. Sayfayı yenileyin.');
        }
        return;
    }
    
    zoneViewActive = !zoneViewActive;
    
    if (zoneViewActive) {
        zoneView.classList.remove('hidden');
        standardView.classList.add('hidden');
        toggleBtn.classList.add('pos-btn-primary');
        toggleBtn.classList.remove('bg-slate-100', 'text-slate-700');
        toggleText.textContent = 'Standart Görünüm';
        renderZoneGroupedView();
    } else {
        zoneView.classList.add('hidden');
        standardView.classList.remove('hidden');
        toggleBtn.classList.remove('pos-btn-primary');
        toggleBtn.classList.add('bg-slate-100', 'text-slate-700');
        toggleText.textContent = 'Zone Görünümü';
    }
}

function renderZoneGroupedView() {
    const container = document.getElementById('zone-grouped-view');
    if (!container) {
        // Container not found - might be in a different view mode or page structure
        // Check if we're in standard grid view mode instead
        const standardView = document.getElementById('standard-grid-view');
        if (standardView && !standardView.classList.contains('hidden')) {
            // We're in standard view mode, zone grouped view is not needed
            return;
        }
        // If container still not found, try to find it in a different location or create it
        const tablesView = document.getElementById('tables-view');
        if (tablesView) {
            // Check if zone-grouped-view exists inside tables-view
            const existingContainer = tablesView.querySelector('#zone-grouped-view');
            if (existingContainer) {
                // Use existing container
                return renderZoneGroupedViewInContainer(existingContainer);
            }
            // Create container if it doesn't exist
            const newContainer = document.createElement('div');
            newContainer.id = 'zone-grouped-view';
            tablesView.appendChild(newContainer);
            return renderZoneGroupedViewInContainer(newContainer);
        }
        // Last resort: silently return (container might not be needed in current view)
        return;
    }
    
    return renderZoneGroupedViewInContainer(container);
}

function renderZoneGroupedViewInContainer(container) {
    if (!container) {
        return;
    }
    
    // If tablesGroupedData is empty or undefined, try to load it from API
    if (!tablesGroupedData || Object.keys(tablesGroupedData).length === 0) {
        container.innerHTML = '<div class="text-center py-20"><div class="text-slate-400 mb-4">Zone verileri yükleniyor...</div></div>';
        
        // Load grouped tables from API
        fetch(`${baseUrl}/api/pos/tables-grouped`)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                // Update tablesGroupedData - convert zones object to grouped format
                const groupedData = {};
                if (data.zones && typeof data.zones === 'object') {
                    Object.keys(data.zones).forEach(zoneName => {
                        const zone = data.zones[zoneName];
                        groupedData[zoneName] = zone.tables || [];
                    });
                }
                window.tablesGroupedData = groupedData;
                tablesGroupedData = groupedData;
                
                // Update zonesData for sidebar
                if (data.zones && typeof data.zones === 'object') {
                    zonesData = data;
                    renderZoneList();
                }
                
                // Re-render
                renderZoneGroupedView();
            })
            .catch(error => {
                console.error('Error loading zone grouped data:', error);
                
                // Show user-friendly error notification
                if (window.NotificationManager) {
                    window.NotificationManager.error('Zone verileri yüklenemedi. Lütfen tekrar deneyin.');
                } else if (window.Toast) {
                    window.Toast.show('Zone verileri yüklenemedi', 'error');
                }
                
                container.innerHTML = `
                    <div class="text-center py-20">
                        <div class="text-red-400 mb-4 font-bold text-lg">Zone verileri yüklenemedi</div>
                        <div class="text-slate-400 text-sm mb-4">${error.message || 'Bilinmeyen hata'}</div>
                        <button onclick="renderZoneGroupedView()" class="pos-btn-primary px-4 py-2 rounded-lg font-bold transition-colors">
                            Tekrar Dene
                        </button>
                    </div>
                `;
            });
        return;
    }
    
    container.innerHTML = '';
    
    // Filter zones based on currentZoneFilter
    const zonesToRender = currentZoneFilter === 'all' 
        ? Object.keys(tablesGroupedData)
        : [currentZoneFilter].filter(zone => tablesGroupedData[zone]);
    
    zonesToRender.forEach(zoneName => {
        const tables = tablesGroupedData[zoneName] || [];
        if (tables.length === 0) return;
        
        const zoneSection = document.createElement('div');
        zoneSection.className = 'mb-8';
        
        // Calculate status counts for this zone
        let zoneFreeCount = 0, zoneOccupiedCount = 0;
        tables.forEach(t => {
            const hasOrders = (parseInt(t.active_order_count) || 0) > 0;
            if (hasOrders) zoneOccupiedCount++;
            else zoneFreeCount++;
        });
        
        const zoneHeader = document.createElement('div');
        zoneHeader.className = 'pos-zone-section-header flex items-center justify-between mb-4 sm:mb-5 md:mb-6';
        zoneHeader.innerHTML = `
            <h2 class="text-lg sm:text-xl md:text-2xl font-black text-slate-900">${escapeHtml(zoneName)}</h2>
            <div class="flex items-center gap-2">
                ${zoneOccupiedCount > 0 ? `<span class="text-xs sm:text-sm font-bold px-2.5 py-1 bg-amber-100 border border-amber-300 rounded-lg text-amber-700">${zoneOccupiedCount} Dolu</span>` : ''}
                ${zoneFreeCount > 0 ? `<span class="text-xs sm:text-sm font-bold px-2.5 py-1 bg-white border border-slate-200 rounded-lg text-slate-500">${zoneFreeCount} Boş</span>` : ''}
                <span class="text-xs sm:text-sm text-slate-400 font-semibold px-2 py-1">${tables.length} masa</span>
            </div>`;
        zoneSection.appendChild(zoneHeader);
        
        const grid = document.createElement('div');
        grid.className = 'grid gap-3 sm:gap-4 md:gap-5 lg:gap-6 min-w-0'; grid.style.gridTemplateColumns = 'repeat(auto-fill, minmax(min(100%, 9rem), 1fr))';
        
        tables.forEach(table => {
            const tableId = table.table_id || '';
            const tableName = table.name || '';
            const status = table.status || 'FREE';
            
            const card = document.createElement('button');
            card.onclick = () => {
                try {
                    selectTable(tableId, tableName, status);
                } catch (error) {
                    console.error('Error selecting table:', error);
                    if (window.NotificationManager) {
                        window.NotificationManager.error('Masa seçilirken hata oluştu.');
                    }
                }
            };
            
            // PERFORMANCE: Use pre-loaded order data from API instead of per-table fetch
            const tableTotal = parseFloat(table.total_amount || 0);
            const hasActiveOrders = (parseInt(table.active_order_count) || 0) > 0;
            
            card.className = posTableCardClass(hasActiveOrders);
            card.setAttribute('data-table-card', tableId);
            
            const totalDisplay = hasActiveOrders ? formatCurrency(tableTotal) : '';
            card.innerHTML = `
                <div class="flex flex-col justify-between h-full">
                    <div class="flex items-start gap-2 mb-1">
                        ${POS_TABLE_TOP_ICON}
                        <div class="font-black text-base sm:text-lg md:text-xl text-slate-900 truncate flex-1 text-left min-w-0">${escapeHtml(tableName)}</div>
                        ${hasActiveOrders ? '<div class="pos-status-dot pos-status-dot--occupied ml-1 mt-1 shrink-0"></div>' : ''}
                    </div>
                    <div class="text-left">
                        <span class="pos-table-badge ${hasActiveOrders ? 'pos-table-badge--occupied' : 'pos-table-badge--empty'}">${hasActiveOrders ? 'DOLU' : 'BOŞ'}</span>
                    </div>
                    ${totalDisplay ? `<div class="text-base sm:text-lg md:text-xl font-black text-slate-900 mt-auto pt-2 text-right" id="table-total-zone-${tableId}">${totalDisplay}</div>` : `<div class="mt-auto pt-2" id="table-total-zone-${tableId}"></div>`}
                </div>`;
            
            grid.appendChild(card);
        });
        
        zoneSection.appendChild(grid);
        container.appendChild(zoneSection);
    });
}

// Start real-time updates when page loads
document.addEventListener('DOMContentLoaded', function() {
    // Load zones data for sidebar
    loadZonesData();
    
    // Initialize zone view as default
    const zoneView = document.getElementById('zone-grouped-view');
    const standardView = document.getElementById('standard-grid-view');
    const toggleBtn = document.getElementById('zone-view-toggle');
    const toggleText = document.getElementById('zone-view-text');
    
    if (zoneView && standardView) {
        zoneView.classList.remove('hidden');
        standardView.classList.add('hidden');
        if (toggleBtn) {
            toggleBtn.classList.add('pos-btn-primary');
            toggleBtn.classList.remove('bg-slate-100', 'text-slate-700');
        }
        if (toggleText) {
            toggleText.textContent = 'Standart Görünüm';
        }
        // Render zone view
        if (typeof renderZoneGroupedView === 'function') {
            renderZoneGroupedView();
        }
    }
    
    // Note: Auto fullscreen removed - browsers require user gesture for fullscreen API
    // Cashiers can manually click the fullscreen button if needed
    <?php if ($isCashier && !$isManager): ?>
    // Fullscreen button is available in the UI for cashiers to use manually
    <?php endif; ?>
    
    // Update tables every 3 seconds
    if (window.realtimeService) {
        window.realtimeService.start('tables', updateTables, 3000);
        
        // Update current table orders if a table is selected
        if (currentTableId) {
            window.currentTableId = currentTableId;
            window.realtimeService.start('table-orders', updateTableOrders, 2000);
        }
    }
    
    // PERFORMANCE: Do NOT load table orders for every non-free table on init (was causing N requests and slow load).
    // Table content loads only when user selects a table (selectTable -> loadTableOrders).
});

// Initialize
filterMenuByCategory(selectedCategoryId);

// Table History Modal
function showTableHistory(tableId, tableName) {
    const modal = document.getElementById('table-history-modal');
    const modalTitle = document.getElementById('table-history-title');
    const modalContent = document.getElementById('table-history-content');
    
    modalTitle.textContent = tableName + ' - ' + posJsTranslations.history;
    modalContent.innerHTML = '<div class="text-center py-8 text-slate-400">' + posJsTranslations.loading + '</div>';
    modal.classList.remove('hidden');
    
    // Load table history
    fetch(`${baseUrl}/api/pos/table-history/${tableId}`)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                modalContent.innerHTML = `<div class="text-center py-8 text-red-400">${data.error}</div>`;
                return;
            }
            
            if (!data || data.length === 0) {
                modalContent.innerHTML = '<div class="text-center py-8 text-slate-400">Bu masa için geçmiş bulunamadı</div>';
                return;
            }
            
            // Group by date
            const groupedByDate = {};
            data.forEach(session => {
                const date = new Date(session.start_time || session.created_at).toLocaleDateString('tr-TR');
                if (!groupedByDate[date]) {
                    groupedByDate[date] = [];
                }
                groupedByDate[date].push(session);
            });
            
            let html = '';
            Object.keys(groupedByDate).sort().reverse().forEach(date => {
                html += `<div class="mb-6"><h3 class="font-black text-base sm:text-lg mb-3 text-slate-900">${date}</h3>`;
                groupedByDate[date].forEach(session => {
                    const startTime = new Date(session.start_time || session.created_at).toLocaleTimeString('tr-TR', { hour: '2-digit', minute: '2-digit' });
                    const endTime = session.end_time ? new Date(session.end_time).toLocaleTimeString('tr-TR', { hour: '2-digit', minute: '2-digit' }) : 'Devam ediyor';
                    const revenue = parseFloat(session.total_revenue || 0);
                    
                    html += `
                        <div class="bg-slate-50 p-3 sm:p-4 rounded-xl mb-2">
                            <div class="flex justify-between items-start mb-2">
                                <div>
                                    <div class="font-black text-sm sm:text-base text-slate-900">${startTime} - ${endTime}</div>
                                    <div class="text-xs text-slate-500">Oturum</div>
                                </div>
                                <div class="text-right">
                                    <div class="font-black text-base sm:text-lg pos-text-money">${formatCurrency(revenue)}</div>
                                </div>
                            </div>
                            ${session.receipt_ids ? `
                                <div class="text-xs text-slate-400 mt-2">
                                    <?php echo t('pos.receipts', 'Receipts'); ?>: ${session.receipt_ids.split(',').length} <?php echo t('common.pieces', 'pieces'); ?>
                                </div>
                            ` : ''}
                        </div>
                    `;
                });
                html += '</div>';
            });
            
            modalContent.innerHTML = html;
        })
        .catch(error => {
            console.error('Error:', error);
            modalContent.innerHTML = '<div class="text-center py-8 text-red-400">Bir hata oluştu</div>';
        });
}

function closeTableHistoryModal() {
    document.getElementById('table-history-modal').classList.add('hidden');
}

function showMixedPaymentModal() {
    if (!isCashierUI) {
        if (window.NotificationManager) {
            window.NotificationManager.error(posJsTranslations.warningPaymentOnlyCashier);
        }
        return;
    }
    
    const totalElement = document.getElementById('payment-total');
    if (!totalElement) {
        if (window.NotificationManager) {
            window.NotificationManager.error('Toplam tutar bulunamadı.');
        }
        return;
    }
    
    const total = parseFloat(totalElement.textContent.replace(/[^\d,]/g, '').replace(',', '.'));
    document.getElementById('mixed-payment-total').textContent = formatCurrency(total);
    document.getElementById('mixed-cash-amount').value = '';
    document.getElementById('mixed-card-amount').value = '';
    updateMixedPaymentRemaining();
    
    document.getElementById('mixed-payment-modal').classList.remove('hidden');
    
    // Add event listeners for real-time calculation
    ['mixed-cash-amount', 'mixed-card-amount'].forEach(id => {
        const input = document.getElementById(id);
        if (input) {
            input.removeEventListener('input', updateMixedPaymentRemaining);
            input.addEventListener('input', updateMixedPaymentRemaining);
        }
    });
}

function updateMixedPaymentRemaining() {
    const total = parseFloat(document.getElementById('mixed-payment-total').textContent.replace(/[^\d,]/g, '').replace(',', '.'));
    const cash = parseFloat(document.getElementById('mixed-cash-amount').value || 0);
    const card = parseFloat(document.getElementById('mixed-card-amount').value || 0);
    const paid = cash + card;
    const remaining = total - paid;
    
    const remainingEl = document.getElementById('mixed-payment-remaining');
    if (remainingEl) {
        remainingEl.textContent = formatCurrency(remaining);
        if (remaining < 0) {
            remainingEl.classList.add('text-red-600');
            remainingEl.classList.remove('pos-text-money', 'text-green-600');
        } else if (remaining === 0) {
            remainingEl.classList.add('text-green-600');
            remainingEl.classList.remove('pos-text-money', 'text-red-600');
        } else {
            remainingEl.classList.add('pos-text-money');
            remainingEl.classList.remove('text-red-600', 'text-green-600');
        }
    }
}

function closeMixedPaymentModal() {
    document.getElementById('mixed-payment-modal').classList.add('hidden');
}

function showOnlinePaymentModal() {
    processIyzicoPayment();
}

async function processIyzicoPayment() {
    if (!isCashierUI) {
        if (window.NotificationManager) {
            window.NotificationManager.error(posJsTranslations.warningPaymentOnlyCashier);
        }
        return;
    }
    if (!currentTableId) {
        if (window.NotificationManager) {
            window.NotificationManager.error(<?php echo json_encode(t('pos.errorNoTableSelected', 'Lütfen bir masa seçin'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>);
        }
        return;
    }
    const totalElement = document.getElementById('payment-total');
    if (!totalElement) {
        if (window.NotificationManager) {
            window.NotificationManager.error(<?php echo json_encode(t('pos.errorOccurred', 'Bir hata oluştu'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>);
        }
        return;
    }
    const total = parseFloat(totalElement.textContent.replace(/[^\d,]/g, '').replace(',', '.'));
    if (isNaN(total) || total <= 0) {
        if (window.NotificationManager) {
            window.NotificationManager.error(<?php echo json_encode(t('pos.errorInvalidAmount', 'Geçersiz ödeme tutarı'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>);
        }
        return;
    }

    const modal = document.getElementById('iyzico-payment-modal');
    const container = document.getElementById('iyzico-payment-container');
    if (!modal || !container) {
        if (window.NotificationManager) {
            window.NotificationManager.error('Ödeme penceresi yüklenemedi. Sayfayı yenileyin.');
        }
        return;
    }

    modal.classList.remove('hidden');
    container.innerHTML = '<div class="text-center py-8"><div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-purple-600"></div><p class="mt-4 text-gray-600">Ödeme formu yükleniyor...</p></div>';
    
    const currentCsrfToken = getCSRFToken();
    
    try {
        const response = await fetch(`${baseUrl}/api/payment/initiate`, {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json',
                'X-CSRF-Token': currentCsrfToken,
                'Accept': 'application/json'
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                gateway: 'iyzico',
                table_id: currentTableId,
                amount: total,
                customer_email: '',
                customer_name: '',
                customer_phone: '',
                customer_address: ''
            })
        });
        
        const data = await response.json();
        
        if (data.success && data.checkout_form_content) {
            container.innerHTML = data.checkout_form_content;
            
            // Auto-submit Iyzico form
            const form = container.querySelector('form');
            if (form) {
                form.submit();
            } else {
                // If form submission fails, redirect to payment page
                if (data.payment_page_url) {
                    window.location.href = data.payment_page_url;
                }
            }
        } else {
            const err = (data && (data.error || data.message)) ? (data.error || data.message) : 'Bilinmeyen hata';
            container.innerHTML = '<div class="text-center py-8 text-red-400">Ödeme başlatılamadı: ' + err + '</div>';
            if (window.NotificationManager) {
                window.NotificationManager.error(err || 'Ödeme başlatılamadı');
            }
        }
    } catch (error) {
        console.error('Iyzico Payment Error:', error);
        if (container) {
            container.innerHTML = '<div class="text-center py-8 text-red-400">Bir hata oluştu: ' + error.message + '</div>';
        }
        if (window.NotificationManager) {
            window.NotificationManager.error('Ödeme başlatılırken hata oluştu');
        }
    }
}

function closeIyzicoModal() {
    const m = document.getElementById('iyzico-payment-modal');
    const c = document.getElementById('iyzico-payment-container');
    if (m) m.classList.add('hidden');
    if (c) c.innerHTML = '';
}

function refundModal() {
    if (!isCashierUI) {
        if (window.NotificationManager) {
            window.NotificationManager.error(posJsTranslations.warningPaymentOnlyCashier);
        }
        return;
    }
    
    const modal = document.getElementById('refund-modal');
    if (!modal) {
        if (window.NotificationManager) {
            window.NotificationManager.error('İade modalı bulunamadı.');
        }
        return;
    }
    
    // Reset form
    const refundAmountInput = document.getElementById('refund-amount');
    const refundReasonInput = document.getElementById('refund-reason');
    if (refundAmountInput) refundAmountInput.value = '';
    if (refundReasonInput) refundReasonInput.value = '';
    
    modal.classList.remove('hidden');
}

function closeRefundModal() {
    const modal = document.getElementById('refund-modal');
    if (modal) {
        modal.classList.add('hidden');
    }
}

function countCashModal() {
    if (!isCashierUI) {
        if (window.NotificationManager) {
            window.NotificationManager.error(posJsTranslations.warningPaymentOnlyCashier);
        }
        return;
    }
    
    const modal = document.getElementById('count-cash-modal');
    if (!modal) {
        if (window.NotificationManager) {
            window.NotificationManager.error('Kasa sayım modalı bulunamadı.');
        }
        return;
    }
    
    // Reset form
    const cashAmountInput = document.getElementById('cash-count-amount');
    if (cashAmountInput) cashAmountInput.value = '';
    
    modal.classList.remove('hidden');
}

function closeCountCashModal() {
    const modal = document.getElementById('count-cash-modal');
    if (modal) {
        modal.classList.add('hidden');
    }
}

function updateCashCountDisplay() {
    const amountInput = document.getElementById('cash-count-amount');
    const displayEl = document.getElementById('cash-count-display');
    if (amountInput && displayEl) {
        const amount = parseFloat(amountInput.value || 0);
        displayEl.textContent = formatCurrency(amount);
    }
}

async function processRefund() {
    if (!isCashierUI) {
        if (window.NotificationManager) {
            window.NotificationManager.error(posJsTranslations.warningPaymentOnlyCashier);
        }
        return;
    }
    
    const amountInput = document.getElementById('refund-amount');
    const reasonInput = document.getElementById('refund-reason');
    
    if (!amountInput) {
        if (window.NotificationManager) {
            window.NotificationManager.error('İade tutarı alanı bulunamadı.');
        }
        return;
    }
    
    const amount = parseFloat(amountInput.value || 0);
    const reason = reasonInput ? reasonInput.value.trim() : '';
    
    if (amount <= 0) {
        if (window.NotificationManager) {
            window.NotificationManager.error('Lütfen geçerli bir iade tutarı girin.');
        }
        return;
    }
    
    if (!reason) {
        if (window.NotificationManager) {
            window.NotificationManager.error('Lütfen iade nedeni girin.');
        }
        return;
    }
    
    const confirmed = await window.NotificationManager.confirm(
        `${formatCurrency(amount)} tutarında iade yapmak istediğinizden emin misiniz?`,
        'İade Onayı'
    );
    
    if (!confirmed) {
        return;
    }
    
    // TODO: Implement actual refund API call
    if (window.NotificationManager) {
        window.NotificationManager.info('İade işlemi henüz entegre edilmedi.');
    }
    
    closeRefundModal();
}

async function saveCashCount() {
    if (!isCashierUI) {
        if (window.NotificationManager) {
            window.NotificationManager.error(posJsTranslations.warningPaymentOnlyCashier);
        }
        return;
    }
    
    const amountInput = document.getElementById('cash-count-amount');
    
    if (!amountInput) {
        if (window.NotificationManager) {
            window.NotificationManager.error('Kasa sayım tutarı alanı bulunamadı.');
        }
        return;
    }
    
    const amount = parseFloat(amountInput.value || 0);
    
    if (amount < 0) {
        if (window.NotificationManager) {
            window.NotificationManager.error('Kasa sayım tutarı negatif olamaz.');
        }
        return;
    }
    
    // Kasa sayımı API'si entegre edilmediği sürece kullanıcıya yanlış
    // "kaydedildi" mesajı verme — info olarak bilgi ver ve modalı kapat.
    if (window.NotificationManager) {
        window.NotificationManager.info(
            `Sayım tutarı alındı (${formatCurrency(amount)}) — kasa sayım kaydı henüz entegre edilmedi.`
        );
    }

    closeCountCashModal();
}

async function processMixedPayment() {
    if (!isCashierUI) {
        if (window.NotificationManager) {
            window.NotificationManager.error(posJsTranslations.warningPaymentOnlyCashier);
        }
        return;
    }
    
    const total = parseFloat(document.getElementById('mixed-payment-total').textContent.replace(/[^\d,]/g, '').replace(',', '.'));
    const cash = parseFloat(document.getElementById('mixed-cash-amount').value || 0);
    const card = parseFloat(document.getElementById('mixed-card-amount').value || 0);
    const paid = cash + card;
    const remaining = total - paid;
    
    if (paid <= 0) {
        if (window.NotificationManager) {
            window.NotificationManager.error('Lütfen en az bir ödeme yöntemi girin.');
        }
        return;
    }
    
    if (remaining > 0.01) {
        const confirmed = await window.NotificationManager.confirm(
            `Kalan tutar: ${formatCurrency(remaining)}\n\nKalan tutarı da ödemek istiyor musunuz?`,
            'Kalan Tutar'
        );
        if (!confirmed) {
            return;
        }
    }
    
    // Process as MIXED payment
    const currentCsrfToken = getCSRFToken();
    if (!currentCsrfToken) {
        if (window.NotificationManager) {
            window.NotificationManager.error('Güvenlik token\'ı bulunamadı. Lütfen sayfayı yenileyin.');
        }
        return;
    }
    
    fetch(`${baseUrl}/pos/process-payment`, {
        method: 'POST',
        headers: { 
            'Content-Type': 'application/json',
            'X-CSRF-Token': currentCsrfToken
        },
        body: JSON.stringify({
            table_id: currentTableId,
            amount: total,
            method: 'MIXED',
            tip: 0,
            payment_breakdown: {
                cash: cash,
                card: card
            }
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (window.NotificationManager) {
                window.NotificationManager.success('Ödeme başarıyla işlendi!');
            }
            // Clear current table selection
            currentTableId = null;
            currentTableName = null;
            currentTableStatus = null;
            
            closeMixedPaymentModal();
            setTimeout(() => {
                showTablesView();
                // Refresh tables data from API
                refreshTablesData();
            }, 500);
        } else {
            if (window.NotificationManager) {
                window.NotificationManager.error('Hata: ' + (data.error || 'Ödeme işlemi başarısız'));
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
        if (window.NotificationManager) {
            window.NotificationManager.error('Bir hata oluştu');
        }
    });
}

// Fullscreen functionality
let isFullscreen = false;
let menuWasVisible = false;

// Zone sidebar and filtering
let currentZoneFilter = 'all';
let zonesData = {};

// Sidebar toggle is handled by sidebar.js
// If sidebar.js is not loaded, provide fallback
if (typeof window.toggleSidebar !== 'function') {
    window.toggleSidebar = function(sidebarId) {
        const id = sidebarId || 'zone-sidebar';
        const sidebar = document.getElementById(id);
        const overlay = document.getElementById('sidebar-overlay');
        const isDesktop = window.innerWidth >= 1024;
        
        if (!sidebar) {
            console.warn('Sidebar not found:', id);
            return;
        }
        
        // Check if sidebar is open
        const isOpen = sidebar.classList.contains('open') || (!sidebar.classList.contains('-translate-x-full') && !isDesktop);
        
        if (isOpen) {
            // Close sidebar
            sidebar.classList.remove('open');
            sidebar.classList.add('-translate-x-full');
            if (!isDesktop) {
                sidebar.style.transform = 'translateX(-100%)';
            }
            
            // Hide overlay
            if (overlay) {
                overlay.classList.add('hidden');
                overlay.classList.remove('open');
            }
            
            // Restore body scroll
            document.body.style.overflow = '';
        } else {
            // Open sidebar
            sidebar.classList.add('open');
            sidebar.classList.remove('-translate-x-full');
            if (!isDesktop) {
                sidebar.style.transform = 'translateX(0)';
            }
            
            // Show overlay on mobile only
            if (overlay && !isDesktop) {
                overlay.classList.remove('hidden');
                overlay.classList.add('open');
            }
            
            // Prevent body scroll on mobile when sidebar is open
            if (!isDesktop) {
                document.body.style.overflow = 'hidden';
            }
        }
    };
}

// Filter by zone
function filterZone(zoneName) {
    currentZoneFilter = zoneName;
    setPosZoneNavActive(zoneName);
    
    // Re-render tables based on zone filter
    if (zoneViewActive && typeof renderZoneGroupedView === 'function') {
        // Check if zone-grouped-view container exists before rendering
        const zoneViewContainer = document.getElementById('zone-grouped-view');
        if (zoneViewContainer) {
            renderZoneGroupedView();
        } else if (typeof updateStandardGridViewTables === 'function') {
            // Fallback to standard view if zone view container doesn't exist
            updateStandardGridViewTables(tablesGroupedData);
        }
    } else if (typeof updateStandardGridViewTables === 'function') {
        updateStandardGridViewTables(tablesGroupedData);
    }
}

// Render zone list in sidebar
function updateStatusSummary() {
    const summaryEl = document.getElementById('pos-status-summary');
    if (!summaryEl || !zonesData) return;
    
    const occupiedTables = zonesData.occupied_tables || 0;
    const freeTables = zonesData.free_tables || 0;
    
    let html = '';
    if (occupiedTables > 0) {
        html += `<span class="pos-summary-pill--occupied inline-flex items-center gap-1.5 text-xs sm:text-sm font-bold px-3 py-1.5 rounded-lg shadow-sm"><span class="pos-status-dot pos-status-dot--occupied"></span>${occupiedTables} Dolu</span>`;
    }
    if (freeTables > 0) {
        html += `<span class="pos-summary-pill--empty inline-flex items-center gap-1.5 text-xs sm:text-sm font-bold px-3 py-1.5 rounded-lg shadow-sm"><span class="pos-status-dot pos-status-dot--empty"></span>${freeTables} Boş</span>`;
    }
    
    summaryEl.innerHTML = html;
}

function renderZoneList() {
    const container = document.getElementById('zone-list');
    if (!container) return;
    
    // Also update the status summary in the header
    updateStatusSummary();
    
    const zones = Object.keys(zonesData.zones || {});
    
    // "Tümü" butonu
    const allBtn = document.createElement('button');
    allBtn.className = posZoneNavClass(currentZoneFilter === 'all');
    allBtn.setAttribute('data-zone', 'all');
    allBtn.innerHTML = `<span class="flex items-center gap-3">${POS_ZONE_ICON}<span>Tüm Bölgeler</span></span>`;
    allBtn.onclick = () => {
        filterZone('all');
        // Close sidebar on mobile after selection
        if (window.innerWidth < 1024) {
            setTimeout(() => toggleSidebar(), 300);
        }
    };
    container.innerHTML = '';
    container.appendChild(allBtn);
    
    // Zone butonları
    zones.forEach(zoneName => {
        const zone = zonesData.zones[zoneName];
        const btn = document.createElement('button');
        btn.className = posZoneNavClass(currentZoneFilter === zoneName) + ' zone-item';
        btn.setAttribute('data-zone', zoneName);
        btn.innerHTML = `
            <div class="flex items-center justify-between">
                <span class="flex-1 truncate">${zoneName}</span>
                <span class="pos-zone-nav-count ml-3">${zone.total_count || 0}</span>
            </div>
        `;
        btn.onclick = () => {
            filterZone(zoneName);
            // Close sidebar on mobile after selection
            if (window.innerWidth < 1024) {
                setTimeout(() => toggleSidebar(), 300);
            }
        };
        container.appendChild(btn);
    });
}

// Load zones data
async function loadZonesData() {
    try {
        const response = await fetch(`${baseUrl}/api/pos/tables-grouped`);
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }
        const data = await response.json();
        
        if (data.zones && typeof data.zones === 'object') {
            zonesData = data;
            renderZoneList();
        }
    } catch (error) {
        console.error('Error loading zones data:', error);
    }
}

function toggleFullscreen() {
    const posDashboard = document.getElementById('pos-dashboard');
    const zoneSidebar = document.getElementById('zone-sidebar');
    const sidebarOverlay = document.getElementById('sidebar-overlay');
    
    // Hide sidebar
    if (zoneSidebar) {
        zoneSidebar.classList.add('-translate-x-full');
    }
    if (sidebarOverlay) {
        sidebarOverlay.classList.add('hidden');
    }
    
    // Hide admin layout sidebar and header
    const adminSidebar = document.querySelector('.desktop-sidebar, .sidebar, aside[class*="sidebar"]');
    const adminHeader = document.querySelector('header[class*="header"], .admin-header, nav[class*="header"]');
    const mainContainer = document.querySelector('.main-container, main, [class*="main"]');
    
    if (adminSidebar) adminSidebar.style.display = 'none';
    if (adminHeader) adminHeader.style.display = 'none';
    if (mainContainer) {
        mainContainer.style.marginLeft = '0';
        mainContainer.style.width = '100%';
    }
    
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
                // "Permissions check failed" and similar errors are expected when
                // fullscreen is called without a user gesture
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

// Listen for fullscreen changes - handled by sidebar.js
// Fallback if sidebar.js is not loaded
document.addEventListener('fullscreenchange', function() {
    const zoneSidebar = document.getElementById('zone-sidebar');
    const sidebarOverlay = document.getElementById('sidebar-overlay');
    
    if (zoneSidebar) {
        if (document.fullscreenElement) {
            // In fullscreen, hide overlay but keep sidebar visible on desktop
            if (sidebarOverlay) {
                sidebarOverlay.classList.add('hidden');
            }
            // On desktop, keep sidebar visible
            if (window.innerWidth >= 1024) {
                zoneSidebar.style.display = '';
                zoneSidebar.classList.remove('-translate-x-full');
            } else {
                // On mobile, hide sidebar
                zoneSidebar.classList.add('-translate-x-full');
            }
        } else {
            // Exit fullscreen, restore normal state
            zoneSidebar.style.display = '';
            if (window.innerWidth >= 1024) {
                zoneSidebar.classList.remove('-translate-x-full');
            } else {
                zoneSidebar.classList.add('-translate-x-full');
            }
        }
    }
});

function toggleFullscreenMenu() {
    const menuView = document.getElementById('menu-view');
    const paymentView = document.getElementById('payment-view');
    const menuToggleBtn = document.getElementById('fullscreen-menu-toggle');
    
    if (!menuView || !paymentView) return;
    
    const menuIsHidden = menuView.classList.contains('hidden');
    
    if (menuIsHidden) {
        // Show menu
        menuView.classList.remove('hidden');
        paymentView.classList.add('hidden');
        if (menuToggleBtn) {
            menuToggleBtn.innerHTML = `
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            `;
            menuToggleBtn.title = posJsTranslations.hideMenu || 'Menüyü Gizle';
        }
    } else {
        // Hide menu
        menuView.classList.add('hidden');
        paymentView.classList.remove('hidden');
        if (menuToggleBtn) {
            menuToggleBtn.innerHTML = `
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                </svg>
            `;
            menuToggleBtn.title = posJsTranslations.showMenu || 'Menüyü Göster';
        }
    }
}

// Order item editing functions
// Remove single or grouped order items
async function removeOrderItemOrGroup(orderItemIds) {
    const ids = Array.isArray(orderItemIds) ? orderItemIds : [orderItemIds];
    if (ids.length === 0) return;
    for (const id of ids) {
        await removeOrderItem(id);
    }
}

// Edit quantity - single item or grouped (consolidates when multiple)
async function editOrderItemQuantityOrGroup(orderItemIds, currentQuantity) {
    const ids = Array.isArray(orderItemIds) ? orderItemIds : [orderItemIds];
    if (ids.length === 0) return;
    if (ids.length === 1) {
        await editOrderItemQuantity(ids[0], currentQuantity);
        return;
    }
    const newQuantity = await window.NotificationManager.prompt(
        <?php echo json_encode(t('pos.editQuantity', 'Adet Düzenle'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
        <?php echo json_encode(t('pos.enterNewQuantity', 'Yeni adet girin'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?> + ':',
        currentQuantity.toString()
    );
    if (newQuantity === null || newQuantity === '') return;
    const quantity = parseInt(newQuantity);
    if (isNaN(quantity) || quantity < 1) {
        if (window.NotificationManager) window.NotificationManager.error(<?php echo json_encode(t('pos.errorInvalidQuantity', 'Geçersiz adet'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>);
        return;
    }
    if (quantity === currentQuantity) return;
    const currentCsrfToken = getCSRFToken();
    if (!currentCsrfToken) {
        if (window.NotificationManager) window.NotificationManager.error(<?php echo json_encode(t('pos.errorCsrfToken', 'Güvenlik token\'ı bulunamadı'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>);
        return;
    }
    try {
        const response = await fetch(`${baseUrl}/pos/update-order-item-group`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': currentCsrfToken, 'Accept': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ order_item_ids: ids, quantity: quantity })
        });
        const data = await response.json();
        if (data.success) {
            if (window.NotificationManager) {
                const msg = data.approval_pending ? '<?php echo addslashes(t('pos.approvalPendingReduce', 'Azaltma talebi onay kuyruğuna gönderildi')); ?>' : '<?php echo addslashes(t('pos.quantityUpdated', 'Adet güncellendi')); ?>';
                window.NotificationManager.success(msg);
            }
            if (currentTableId) loadTableOrders(currentTableId);
        } else {
            if (window.NotificationManager) window.NotificationManager.error(data.error || data.message || <?php echo json_encode(t('pos.errorUpdateFailed', 'Güncelleme başarısız'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>);
        }
    } catch (error) {
        console.error('Error updating quantity:', error);
        if (window.NotificationManager) window.NotificationManager.error(<?php echo json_encode(t('pos.errorOccurred', 'Bir hata oluştu'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>);
    }
}

// Edit order item quantity (direct - no approval needed)
async function editOrderItemQuantity(orderItemId, currentQuantity) {
    const newQuantity = await window.NotificationManager.prompt(
        <?php echo json_encode(t('pos.editQuantity', 'Adet Düzenle'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
        <?php echo json_encode(t('pos.enterNewQuantity', 'Yeni adet girin'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?> + ':',
        currentQuantity.toString()
    );
    
    if (newQuantity === null || newQuantity === '') {
        return;
    }
    
    const quantity = parseInt(newQuantity);
    if (isNaN(quantity) || quantity < 1) {
        if (window.NotificationManager) {
            window.NotificationManager.error(<?php echo json_encode(t('pos.errorInvalidQuantity', 'Geçersiz adet'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>);
        }
        return;
    }
    
    if (quantity === currentQuantity) {
        return; // No change
    }
    
    const currentCsrfToken = getCSRFToken();
    if (!currentCsrfToken) {
        if (window.NotificationManager) {
            window.NotificationManager.error(<?php echo json_encode(t('pos.errorCsrfToken', 'Güvenlik token\'ı bulunamadı'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>);
        }
        return;
    }
    
    try {
        const response = await fetch(`${baseUrl}/pos/update-order-item-quantity`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': currentCsrfToken,
                'Accept': 'application/json'
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                order_item_id: orderItemId,
                quantity: quantity
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            if (window.NotificationManager) {
                const msg = data.approval_pending ? '<?php echo addslashes(t('pos.approvalPendingReduce', 'Azaltma talebi onay kuyruğuna gönderildi')); ?>' : '<?php echo addslashes(t('pos.quantityUpdated', 'Adet güncellendi')); ?>';
                window.NotificationManager.success(msg);
            }
            if (currentTableId) {
                loadTableOrders(currentTableId);
            }
        } else {
            if (window.NotificationManager) {
                window.NotificationManager.error(data.error || data.message || <?php echo json_encode(t('pos.errorUpdateFailed', 'Güncelleme başarısız'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>);
            }
        }
    } catch (error) {
        console.error('Error updating quantity:', error);
        if (window.NotificationManager) {
            window.NotificationManager.error(<?php echo json_encode(t('pos.errorOccurred', 'Bir hata oluştu'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>);
        }
    }
}

// Remove order item (direct - no confirmation needed)
async function removeOrderItem(orderItemId) {
    const currentCsrfToken = getCSRFToken();
    if (!currentCsrfToken) {
        if (window.NotificationManager) {
            window.NotificationManager.error(<?php echo json_encode(t('pos.errorCsrfToken', 'Güvenlik token\'ı bulunamadı'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>);
        }
        return;
    }
    
    try {
        const response = await fetch(`${baseUrl}/pos/remove-item-from-order`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': currentCsrfToken,
                'Accept': 'application/json'
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                order_item_id: orderItemId
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            if (window.NotificationManager) {
                const msg = data.approval_pending ? '<?php echo addslashes(t('pos.approvalPending', 'Silme talebi onay kuyruğuna gönderildi')); ?>' : '<?php echo addslashes(t('pos.itemRemoved', 'Ürün kaldırıldı')); ?>';
                window.NotificationManager.success(msg);
            }
            if (currentTableId) {
                loadTableOrders(currentTableId);
            }
        } else {
            if (window.NotificationManager) {
                window.NotificationManager.error(data.error || data.message || <?php echo json_encode(t('pos.errorRemoveFailed', 'Kaldırma başarısız'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>);
            }
        }
    } catch (error) {
        console.error('Error removing item:', error);
        if (window.NotificationManager) {
            window.NotificationManager.error(<?php echo json_encode(t('pos.errorOccurred', 'Bir hata oluştu'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>);
        }
    }
}

// Delete all orders for current table (direct - no confirmation needed)
async function deleteAllTableOrders() {
    if (!currentTableId) {
        if (window.NotificationManager) {
            window.NotificationManager.error('Lütfen bir masa seçin');
        }
        return;
    }
    
    const currentCsrfToken = getCSRFToken();
    if (!currentCsrfToken) {
        if (window.NotificationManager) {
            window.NotificationManager.error(<?php echo json_encode(t('pos.errorCsrfToken', 'Güvenlik token\'ı bulunamadı'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>);
        }
        return;
    }
    
    try {
        const response = await fetch(`${baseUrl}/pos/delete-all-table-orders`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': currentCsrfToken,
                'Accept': 'application/json'
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                table_id: currentTableId
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            if (window.NotificationManager) {
                window.NotificationManager.success(data.message || (data.approval_pending ? 'İşletme yöneticinizden onay bekleniyor' : 'Tüm siparişler silindi'));
            }
            if (data.approval_pending) {
                if (currentTableId) loadTableOrders(currentTableId);
            } else {
                if (currentTableId) loadTableOrders(currentTableId);
                setTimeout(() => {
                    showTablesView();
                    if (typeof renderZoneGroupedView === 'function') renderZoneGroupedView();
                }, 1000);
            }
        } else {
            if (window.NotificationManager) {
                window.NotificationManager.error(data.error || 'İşlem başarısız');
            }
        }
    } catch (error) {
        console.error('Error deleting all orders:', error);
        if (window.NotificationManager) {
            window.NotificationManager.error(<?php echo json_encode(t('pos.errorOccurred', 'Bir hata oluştu'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>);
        }
    }
}

// Auto-refresh tables data every 15 seconds when on tables view
let posTablesRefreshInterval = null;

function startPosTablesPolling() {
    if (posTablesRefreshInterval) return;
    posTablesRefreshInterval = setInterval(() => {
        // Only refresh if tables view is visible (not in menu/payment view)
        const tablesView = document.getElementById('tables-view');
        if (tablesView && !tablesView.classList.contains('hidden') && document.visibilityState === 'visible') {
            refreshTablesData();
        }
    }, 15000);
}

// Start polling
startPosTablesPolling();

// Pause/resume on visibility change
document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') {
        refreshTablesData(); // Immediate refresh when tab becomes visible
        startPosTablesPolling();
    } else {
        if (posTablesRefreshInterval) {
            clearInterval(posTablesRefreshInterval);
            posTablesRefreshInterval = null;
        }
    }
});

// Cleanup on page unload
window.addEventListener('beforeunload', () => {
    if (posTablesRefreshInterval) {
        clearInterval(posTablesRefreshInterval);
        posTablesRefreshInterval = null;
    }
});
</script>

<style>
.fullscreen-mode {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 9999;
    background: white;
}

.fullscreen-mode header {
    display: none !important;
}
</style>

<!-- Mixed Payment Modal -->

<?php if (!empty($onlinePaymentAvailable)): ?>
<div id="iyzico-payment-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-[60] flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl sm:rounded-3xl p-6 sm:p-8 max-w-lg w-full max-h-[90vh] overflow-hidden flex flex-col shadow-2xl">
        <div class="flex items-center justify-between mb-4 sm:mb-6">
            <h2 class="text-xl sm:text-2xl font-black text-slate-900"><?php echo t('payment.online', 'Online Ödeme'); ?></h2>
            <button type="button" onclick="closeIyzicoModal()" class="p-2 hover:bg-slate-100 rounded-lg transition-colors" aria-label="<?php echo t('common.close', 'Kapat'); ?>">
                <?php echo icon_x(['class' => 'w-5 h-5 sm:w-6 sm:h-6 text-slate-600']); ?>
            </button>
        </div>
        <div id="iyzico-payment-container" class="flex-1 overflow-y-auto min-h-[120px]">
            <div class="text-center py-8 text-slate-500 text-sm"><?php echo t('common.loading', 'Yükleniyor...'); ?></div>
        </div>
    </div>
</div>
<?php endif; ?>

<div id="mixed-payment-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl sm:rounded-3xl p-6 sm:p-8 max-w-2xl w-full max-h-[90vh] overflow-hidden flex flex-col">
        <div class="flex items-center justify-between mb-4 sm:mb-6">
            <h2 class="text-xl sm:text-2xl font-black text-slate-900">Karışık Ödeme</h2>
            <button onclick="closeMixedPaymentModal()" class="p-2 hover:bg-slate-100 rounded-lg transition-colors">
                <?php echo icon_x(['class' => 'w-5 h-5 sm:w-6 sm:h-6 text-slate-600']); ?>
            </button>
        </div>
        <div class="flex-1 overflow-y-auto space-y-4">
            <div class="bg-gradient-to-r from-purple-50 to-purple-100 rounded-xl p-4 border-2 border-purple-200">
                <div class="text-sm font-bold text-slate-600 mb-2">Toplam Tutar</div>
                <div class="text-2xl font-black text-purple-600" id="mixed-payment-total">0 ₺</div>
            </div>
            <div class="space-y-3">
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-2"><?php echo t('payment.cashAmount', 'Cash Amount'); ?></label>
                    <input type="number" step="0.01" min="0" id="mixed-cash-amount" placeholder="0.00" class="w-full px-4 py-3 border-2 border-slate-200 rounded-xl font-black text-lg focus:border-indigo-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-2"><?php echo t('payment.cardAmount', 'Card Amount'); ?></label>
                    <input type="number" step="0.01" min="0" id="mixed-card-amount" placeholder="0.00" class="w-full px-4 py-3 border-2 border-slate-200 rounded-xl font-black text-lg focus:border-blue-500 focus:outline-none">
                </div>
            </div>
            <div class="bg-indigo-50 border-2 border-indigo-200 rounded-xl p-4">
                <div class="flex justify-between items-center">
                    <span class="text-sm font-bold text-slate-700">Kalan Tutar:</span>
                    <span class="text-xl font-black pos-text-money" id="mixed-payment-remaining">0 ₺</span>
                </div>
            </div>
        </div>
        <div class="mt-6 flex gap-3">
            <button onclick="closeMixedPaymentModal()" class="flex-1 px-4 py-3 bg-slate-100 text-slate-700 rounded-xl font-black hover:bg-slate-200 transition-colors">
                İptal
            </button>
            <button onclick="processMixedPayment()" class="flex-1 px-4 py-3 bg-gradient-to-r from-purple-600 to-purple-500 text-white rounded-xl font-black hover:from-purple-500 hover:to-purple-400 transition-all shadow-lg">
                Ödemeyi İşle
            </button>
        </div>
    </div>
</div>

<!-- Mutfak/Hazırlık uyarısı - Yönetici onayı modal -->
<div id="payment-prep-approval-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl sm:rounded-3xl p-6 sm:p-8 max-w-md w-full shadow-xl">
        <div class="flex items-center gap-3 mb-4">
            <div class="p-3 bg-amber-100 rounded-xl">
                <svg class="w-8 h-8 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            </div>
            <h2 class="text-xl font-black text-slate-900">Müşteri hâlâ siparişi var</h2>
        </div>
        <p id="payment-prep-approval-message" class="text-slate-600 mb-6">Bu masada mutfak veya hazırlık ekranında hazırlanan ürünler var. Ödeme almak için yönetici onayı gerekir. Onay verilirse hazırlanan ürünler iptal edilir ve ödeme alınabilir; reddedilirse siparişler hazırlanmaya devam eder.</p>
        <div class="flex gap-3">
            <button type="button" onclick="closePaymentPrepApprovalModal()" class="flex-1 px-4 py-3 bg-slate-100 text-slate-700 rounded-xl font-bold hover:bg-slate-200 transition-colors">İptal</button>
            <button type="button" id="btn-request-prep-cancel" onclick="requestPaymentPrepCancelFromModal()" class="flex-1 px-4 py-3 bg-amber-500 text-white rounded-xl font-bold hover:bg-amber-600 transition-colors">Yönetici Onayı İste</button>
        </div>
    </div>
</div>
<?php if ($isSuperAdmin): ?>
        </div>
        <!-- POS Management View closing div -->
    </div>
    <!-- Super admin container closing div -->
</div>
<?php endif; ?>

<!-- Table History Modal -->
<div id="table-history-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl sm:rounded-3xl p-6 sm:p-8 max-w-2xl w-full max-h-[90vh] overflow-hidden flex flex-col">
        <div class="flex items-center justify-between mb-4 sm:mb-6">
            <h2 id="table-history-title" class="text-xl sm:text-2xl font-black text-slate-900"><?php echo t('pos.tableHistory', 'Masa Geçmişi'); ?></h2>
            <button onclick="closeTableHistoryModal()" class="p-2 hover:bg-slate-100 rounded-lg transition-colors">
                <?php echo icon_x(['class' => 'w-5 h-5 sm:w-6 sm:h-6 text-slate-600']); ?>
            </button>
        </div>
        <div id="table-history-content" class="flex-1 overflow-y-auto">
            <!-- Content loaded dynamically -->
        </div>
    </div>
</div>

<!-- Refund Modal -->
<div id="refund-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl sm:rounded-3xl p-6 sm:p-8 max-w-2xl w-full max-h-[90vh] overflow-hidden flex flex-col">
        <div class="flex items-center justify-between mb-4 sm:mb-6">
            <h2 class="text-xl sm:text-2xl font-black text-slate-900">İade İşlemi</h2>
            <button onclick="closeRefundModal()" class="p-2 hover:bg-slate-100 rounded-lg transition-colors">
                <?php echo icon_x(['class' => 'w-5 h-5 sm:w-6 sm:h-6 text-slate-600']); ?>
            </button>
        </div>
        <div class="flex-1 overflow-y-auto space-y-4">
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-2">İade Tutarı (₺)</label>
                <input type="number" step="0.01" min="0" id="refund-amount" placeholder="0.00" class="w-full px-4 py-3 border-2 border-slate-200 rounded-xl font-black text-lg focus:border-red-500 focus:outline-none">
            </div>
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-2">İade Nedeni</label>
                <textarea id="refund-reason" rows="3" placeholder="İade nedeni..." class="w-full px-4 py-3 border-2 border-slate-200 rounded-xl text-sm focus:border-red-500 focus:outline-none"></textarea>
            </div>
        </div>
        <div class="mt-6 flex gap-3">
            <button onclick="closeRefundModal()" class="flex-1 px-4 py-3 bg-slate-100 text-slate-700 rounded-xl font-black hover:bg-slate-200 transition-colors">
                İptal
            </button>
            <button onclick="processRefund()" class="flex-1 px-4 py-3 bg-gradient-to-r from-red-600 to-red-500 text-white rounded-xl font-black hover:from-red-500 hover:to-red-400 transition-all shadow-lg">
                İade Et
            </button>
        </div>
    </div>
</div>

<!-- Count Cash Modal -->
<div id="count-cash-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl sm:rounded-3xl p-6 sm:p-8 max-w-2xl w-full max-h-[90vh] overflow-hidden flex flex-col">
        <div class="flex items-center justify-between mb-4 sm:mb-6">
            <h2 class="text-xl sm:text-2xl font-black text-slate-900">Kasa Sayımı</h2>
            <button onclick="closeCountCashModal()" class="p-2 hover:bg-slate-100 rounded-lg transition-colors">
                <?php echo icon_x(['class' => 'w-5 h-5 sm:w-6 sm:h-6 text-slate-600']); ?>
            </button>
        </div>
        <div class="flex-1 overflow-y-auto space-y-4">
            <div class="bg-gradient-to-r from-green-50 to-green-100 rounded-xl p-4 border-2 border-green-200">
                <div class="text-sm font-bold text-slate-600 mb-2">Kasadaki Nakit</div>
                <div class="text-2xl font-black text-green-600" id="cash-count-display">0 ₺</div>
            </div>
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-2">Sayılan Tutar (₺)</label>
                <input type="number" step="0.01" min="0" id="cash-count-amount" placeholder="0.00" class="w-full px-4 py-3 border-2 border-slate-200 rounded-xl font-black text-lg focus:border-green-500 focus:outline-none" oninput="updateCashCountDisplay()">
            </div>
        </div>
        <div class="mt-6 flex gap-3">
            <button onclick="closeCountCashModal()" class="flex-1 px-4 py-3 bg-slate-100 text-slate-700 rounded-xl font-black hover:bg-slate-200 transition-colors">
                İptal
            </button>
            <button onclick="saveCashCount()" class="flex-1 px-4 py-3 bg-gradient-to-r from-green-600 to-green-500 text-white rounded-xl font-black hover:from-green-500 hover:to-green-400 transition-all shadow-lg">
                Kaydet
            </button>
        </div>
    </div>
</div>

<!-- Standard Sidebar Management (loaded from admin_layout head for ops views) -->

<style>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap');

/* Apply modern typography across all POS and modal elements */
#pos-dashboard, 
#pos-dashboard *,
#table-history-modal,
#mixed-payment-modal,
#refund-modal,
#count-cash-modal,
#payment-prep-approval-modal {
    font-family: 'Plus Jakarta Sans', sans-serif !important;
}

/* Base resets & premium background color */
#pos-dashboard {
    background-color: #f4f5fa !important;
}

/* Header Buttons and Actions (Toggles, Fullscreen, Logout) */
#tables-view header button,
#tables-view header a {
    border-radius: 14px !important;
    border: 1.5px solid #e2e8f0 !important;
    background-color: #ffffff !important;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1) !important;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.01) !important;
}
#tables-view header button:hover {
    background-color: #f8fafc !important;
    border-color: #cbd5e1 !important;
    transform: translateY(-1px) !important;
}
#tables-view header a:hover {
    background-color: #fee2e2 !important;
    border-color: #fca5a5 !important;
    transform: translateY(-1px) !important;
}

/* Zone view toggle button */
#zone-view-toggle {
    background: linear-gradient(135deg, #818cf8 0%, #6366f1 55%, #4f46e5 100%) !important;
    color: #ffffff !important;
    border: 1.5px solid transparent !important;
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.22) !important;
}
#zone-view-toggle:hover {
    filter: brightness(1.03) !important;
    box-shadow: 0 6px 16px rgba(99, 102, 241, 0.28) !important;
}

/* Zone Section Grouped Headers */
#zone-grouped-view > div > div.bg-slate-50 {
    background: #ffffff !important;
    border: 1.5px solid #f1f5f9 !important;
    border-radius: 18px !important;
    padding: 14px 20px !important;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.02) !important;
    margin-bottom: 20px !important;
}
#zone-grouped-view h2 {
    font-weight: 800 !important;
    letter-spacing: -0.03em !important;
    color: #0f172a !important;
}

/* Modal form controls and layouts */
#mixed-payment-modal input, 
#refund-modal input, 
#count-cash-modal input,
#refund-modal textarea {
    border-radius: 14px !important;
    border: 2px solid #e2e8f0 !important;
    padding: 12px 16px !important;
    font-weight: 700 !important;
    font-size: 1.05rem !important;
    background-color: #f8fafc !important;
    color: #0f172a !important;
    transition: all 0.2s ease !important;
}
#mixed-payment-modal input:focus, 
#refund-modal input:focus, 
#count-cash-modal input:focus,
#refund-modal textarea:focus {
    border-color: #6366f1 !important;
    background-color: #ffffff !important;
    box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.12) !important;
    outline: none !important;
}
#mixed-payment-modal label,
#refund-modal label,
#count-cash-modal label {
    font-weight: 700 !important;
    color: #475569 !important;
    font-size: 0.875rem !important;
    letter-spacing: -0.01em;
}

/* Modal gradient panel containers */
#mixed-payment-modal .bg-gradient-to-r.from-purple-50.to-purple-100 {
    background: linear-gradient(135deg, #f5f3ff 0%, #ede9fe 100%) !important;
    border-color: #ddd6fe !important;
    border-radius: 16px !important;
}
#count-cash-modal .bg-gradient-to-r.from-green-50.to-green-100 {
    background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%) !important;
    border-color: #bbf7d0 !important;
    border-radius: 16px !important;
}
#mixed-payment-modal .bg-indigo-50.border-2.border-indigo-200 {
    background: linear-gradient(135deg, #eef2ff 0%, #e0e7ff 100%) !important;
    border-color: #c7d2fe !important;
    border-radius: 16px !important;
}

/* Modal action buttons default overrides */
#mixed-payment-modal button,
#refund-modal button,
#count-cash-modal button,
#payment-prep-approval-modal button {
    border-radius: 14px !important;
    padding: 12px 24px !important;
    font-weight: 700 !important;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1) !important;
}

/* Zone Sidebar Styling - Light Sleek Aesthetic */
#zone-sidebar {
    background-color: #ffffff !important; /* Pure White Sidebar */
    border-right: 1.5px solid #e2e8f0 !important;
}
#zone-sidebar h2 {
    color: #0f172a !important; /* Deep Slate text */
    font-size: 1.25rem !important;
    font-weight: 800 !important;
    letter-spacing: -0.025em;
}
#zone-sidebar .border-b {
    border-color: #f1f5f9 !important;
}

/* Zone list buttons (active vs inactive) */
#zone-list button {
    border-radius: 14px !important;
    padding: 14px 18px !important;
    font-weight: 700 !important;
    font-size: 0.95rem !important;
    border: 1.5px solid transparent !important;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1) !important;
    margin-bottom: 8px !important;
}
/* Active zone button — indigo panel nav accent */
#zone-list button.pos-zone-nav-btn--active {
    background: rgba(99, 102, 241, 0.12) !important;
    color: #4f46e5 !important;
    border-color: rgba(99, 102, 241, 0.28) !important;
    box-shadow: 0 6px 16px -4px rgba(99, 102, 241, 0.18) !important;
}
/* Inactive zone button */
#zone-list button.pos-zone-nav-btn--inactive {
    background-color: #ffffff !important;
    color: #64748b !important;
    border: 1.5px solid #f1f5f9 !important;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02) !important;
}
#zone-list button.pos-zone-nav-btn--inactive:hover {
    background-color: #f8fafc !important;
    color: #0f172a !important;
    border-color: #cbd5e1 !important;
}
/* Counter pill inside Zone Button */
#zone-list button span.bg-white {
    border-radius: 8px !important;
    font-size: 0.75rem !important;
    padding: 2px 8px !important;
    font-weight: 700 !important;
    border: 1px solid transparent !important;
}
#zone-list button.pos-zone-nav-btn--active .pos-zone-nav-count {
    background-color: #6366f1 !important;
    color: #ffffff !important;
}
#zone-list button.pos-zone-nav-btn--inactive .pos-zone-nav-count {
    background-color: #f1f5f9 !important;
    color: #64748b !important;
}

/* Header UI Elements */
#tables-view header {
    background: rgba(255, 255, 255, 0.8) !important;
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border-bottom: 1px solid #e2e8f0;
    padding: 20px 24px !important;
    margin: -12px -12px 24px -12px !important;
}
@media (min-width: 1024px) {
    #tables-view header {
        margin: -32px -32px 32px -32px !important;
        padding: 24px 40px !important;
    }
}
#tables-view header h1 {
    font-size: 1.875rem !important;
    font-weight: 800 !important;
    letter-spacing: -0.03em !important;
    color: #0f172a !important;
}

/* Status Summary Pill styling */
#pos-status-summary span {
    border-radius: 9999px !important;
    padding: 6px 14px !important;
    font-weight: 700 !important;
    letter-spacing: -0.01em;
}
#pos-status-summary span.pos-summary-pill--occupied {
    background-color: #fffbeb !important;
    border: 1px solid #fde68a !important;
    color: #d97706 !important;
}
#pos-status-summary span.pos-summary-pill--empty {
    background-color: #ffffff !important;
    border: 1px solid #e2e8f0 !important;
    color: #64748b !important;
}

/* Table Card Buttons */
button.btn-touch[data-table-card],
#standard-grid-view button.btn-touch {
    border-radius: 20px !important;
    border: 1.5px solid #e2e8f0 !important;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
    position: relative !important;
    overflow: hidden !important;
}

/* DOLU (Occupied) Table Cards */
button.btn-touch.pos-table-card--occupied,
#standard-grid-view button.btn-touch.pos-table-card--occupied {
    background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%) !important;
    border-color: #fde68a !important;
    box-shadow: 0 10px 15px -3px rgba(245, 158, 11, 0.08), 0 4px 6px -2px rgba(245, 158, 11, 0.04) !important;
}
button.btn-touch.pos-table-card--occupied:hover,
#standard-grid-view button.btn-touch.pos-table-card--occupied:hover {
    transform: translateY(-4px) scale(1.02) !important;
    border-color: #f59e0b !important;
    box-shadow: 0 20px 25px -5px rgba(245, 158, 11, 0.12), 0 10px 10px -5px rgba(245, 158, 11, 0.08) !important;
}

/* BOŞ (Free) Table Cards */
button.btn-touch.pos-table-card--empty,
#standard-grid-view button.btn-touch.pos-table-card--empty {
    background: #ffffff !important;
    border-color: #e2e8f0 !important;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.03), 0 2px 4px -1px rgba(0, 0, 0, 0.02) !important;
}
button.btn-touch.pos-table-card--empty:hover,
#standard-grid-view button.btn-touch.pos-table-card--empty:hover {
    transform: translateY(-4px) scale(1.02) !important;
    border-color: #cbd5e1 !important;
    box-shadow: 0 12px 20px -3px rgba(0, 0, 0, 0.08), 0 4px 6px -2px rgba(0, 0, 0, 0.04) !important;
}

/* Pulsing Status Dot for Active Orders */
@keyframes ring-glow {
    0% { transform: scale(0.85); opacity: 0.9; }
    50% { transform: scale(1.25); opacity: 0.4; }
    100% { transform: scale(0.85); opacity: 0.9; }
}
.pos-status-dot--occupied {
    background-color: #f59e0b !important;
    position: relative !important;
}
.pos-status-dot--occupied::after {
    content: '';
    position: absolute;
    top: -4px;
    left: -4px;
    right: -4px;
    bottom: -4px;
    border-radius: 50%;
    border: 2px solid #f59e0b;
    animation: ring-glow 2s infinite ease-in-out;
}

/* Table Card price text styling */
#standard-grid-view div[id^="table-total-"],
div[id^="table-total-zone-"] {
    font-size: 1.25rem !important;
    font-weight: 800 !important;
    letter-spacing: -0.03em !important;
    color: #1e293b !important;
}

/* ========================================================
   TABLE DETAILS VIEW (Menu, Items Grid, Cart, and Checkout)
   ======================================================== */
#menu-payment-view {
    background-color: #f4f5fa !important;
}

/* Detail view header styles */
#menu-payment-view header {
    background: #ffffff !important;
    border-bottom: 1px solid #e2e8f0 !important;
    padding: 16px 24px !important;
}
#menu-payment-view header button[onclick^="showTablesView"] {
    background: #f1f5f9 !important;
    color: #334155 !important;
    border: 1px solid #e2e8f0 !important;
    border-radius: 12px !important;
    padding: 10px 18px !important;
    transition: all 0.2s ease !important;
}
#menu-payment-view header button[onclick^="showTablesView"]:hover {
    background: #e2e8f0 !important;
    color: #0f172a !important;
}

/* Menu Category Pills selection bar */
.category-btn {
    border-radius: 14px !important;
    padding: 10px 22px !important;
    font-weight: 700 !important;
    font-size: 0.9rem !important;
    border: 1.5px solid #f1f5f9 !important;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1) !important;
}
.category-btn.bg-slate-900 {
    background: linear-gradient(135deg, #818cf8 0%, #6366f1 55%, #4f46e5 100%) !important;
    color: #ffffff !important;
    border-color: transparent !important;
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.22) !important;
}
.category-btn.bg-slate-50 {
    background-color: #ffffff !important;
    color: #64748b !important;
}
.category-btn.bg-slate-50:hover {
    background-color: #f8fafc !important;
    color: #0f172a !important;
    border-color: #cbd5e1 !important;
}

/* Product Cards Grid */
.menu-item-btn {
    border-radius: 24px !important;
    border: 1.5px solid #f1f5f9 !important;
    background-color: #ffffff !important;
    padding: 16px !important;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02) !important;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
}
.menu-item-btn:hover {
    transform: translateY(-4px) scale(1.02) !important;
    border-color: #c7d2fe !important;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.04), 0 10px 10px -5px rgba(0, 0, 0, 0.02) !important;
}
.menu-item-btn div[style*="background-image"] {
    border-radius: 18px !important;
    border: 3px solid #ffffff !important;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.04) !important;
}
.menu-item-btn .font-black.text-slate-800 {
    font-weight: 700 !important;
    color: #1e293b !important;
}
.menu-item-btn .pos-text-money {
    font-weight: 800 !important;
    color: #4f46e5 !important;
}

/* Desktop Cart Sidebar (Right container) */
#menu-view > div:last-child {
    background-color: #f8fafc !important;
    border-left: 1px solid #e2e8f0 !important;
}
#menu-view > div:last-child > div:first-child {
    background-color: #ffffff !important;
    border-bottom: 1px solid #e2e8f0 !important;
    padding: 20px 24px !important;
}
#cart-items {
    background-color: #f8fafc !important;
    padding: 20px !important;
}
/* Individual cart item rows styling */
#cart-items > div,
#mobile-cart-items > div {
    background-color: #ffffff !important;
    border-radius: 16px !important;
    border: 1.5px solid #f1f5f9 !important;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.01) !important;
    margin-bottom: 10px !important;
    padding: 12px 16px !important;
    transition: all 0.2s ease !important;
}
#cart-items > div:hover,
#mobile-cart-items > div:hover {
    border-color: #e2e8f0 !important;
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.03) !important;
}
#menu-view > div:last-child > div:last-child {
    background-color: #ffffff !important;
    border-top: 1px solid #e2e8f0 !important;
    padding: 24px !important;
}
/* Send Order Button styling */
button[onclick^="sendOrder"] {
    background: linear-gradient(135deg, #818cf8 0%, #6366f1 55%, #4f46e5 100%) !important;
    border-radius: 18px !important;
    font-weight: 800 !important;
    box-shadow: 0 10px 15px -3px rgba(99, 102, 241, 0.28) !important;
    transition: all 0.2s ease !important;
}
button[onclick^="sendOrder"]:hover {
    background: linear-gradient(135deg, #a5b4fc 0%, #818cf8 55%, #6366f1 100%) !important;
    transform: translateY(-2px) !important;
    box-shadow: 0 12px 20px -3px rgba(99, 102, 241, 0.32) !important;
}

/* ========================================================
   CASHIER VIEW (Payment Screen, Invoice Details)
   ======================================================== */
#payment-view {
    background-color: #f8fafc !important;
}
#payment-view header {
    background-color: #ffffff !important;
    border-bottom: 1px solid #e2e8f0 !important;
    padding: 16px 24px !important;
}
#payment-orders-list {
    background-color: #ffffff !important;
}
#payment-orders-list > div > div:first-child {
    background-color: #f8fafc !important;
    border-bottom: 1px solid #f1f5f9 !important;
    padding: 10px 20px !important;
}
#payment-orders-list .hover\:bg-slate-50 {
    padding: 12px 16px !important;
    border-radius: 12px !important;
    margin: 4px 12px !important;
    border: 1px solid transparent !important;
    transition: all 0.2s ease !important;
}
#payment-orders-list .hover\:bg-slate-50:hover {
    background-color: #f8fafc !important;
    border-color: #e2e8f0 !important;
}
#payment-orders-list span.bg-indigo-100 {
    background-color: #eef2ff !important;
    color: #4f46e5 !important;
    border-radius: 8px !important;
    width: 28px !important;
    height: 28px !important;
    font-size: 0.85rem !important;
}

/* Checkout Totals Panel */
#payment-view .bg-slate-50\/80 {
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.8) 0%, rgba(248, 250, 252, 0.95) 100%) !important;
    border-top: 1px solid #e2e8f0 !important;
    padding: 20px 24px !important;
}
#payment-total {
    font-size: 2rem !important;
    font-weight: 800 !important;
    letter-spacing: -0.04em !important;
    color: #4f46e5 !important;
}

/* Payment Method Selection Buttons - Premium Tactile Design */
#payment-view button[id^="btn-pay-"],
#payment-view button[onclick^="showMixedPaymentModal"],
#payment-view button[onclick^="processIyzicoPayment"] {
    border-radius: 16px !important;
    padding: 18px !important;
    border: 1.5px solid #e2e8f0 !important;
    background: #ffffff !important;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02) !important;
}

/* Cash Payment Option */
#btn-pay-cash:hover {
    border-color: #10b981 !important;
    background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%) !important;
    box-shadow: 0 10px 15px -3px rgba(16, 185, 129, 0.12) !important;
    transform: translateY(-2px) !important;
}
#btn-pay-cash:hover svg {
    color: #059669 !important;
}
#btn-pay-cash:hover div.bg-emerald-100 {
    background-color: #a7f3d0 !important;
}

/* Card Payment Option */
#btn-pay-card:hover {
    border-color: #3b82f6 !important;
    background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%) !important;
    box-shadow: 0 10px 15px -3px rgba(59, 130, 246, 0.12) !important;
    transform: translateY(-2px) !important;
}
#btn-pay-card:hover svg {
    color: #2563eb !important;
}
#btn-pay-card:hover div.bg-blue-100 {
    background-color: #bfdbfe !important;
}

/* Mixed Payment Option */
button[onclick^="showMixedPaymentModal"]:hover {
    border-color: #8b5cf6 !important;
    background: linear-gradient(135deg, #f5f3ff 0%, #ede9fe 100%) !important;
    box-shadow: 0 10px 15px -3px rgba(139, 92, 246, 0.12) !important;
    transform: translateY(-2px) !important;
}
button[onclick^="showMixedPaymentModal"]:hover svg {
    color: #7c3aed !important;
}
button[onclick^="showMixedPaymentModal"]:hover div.bg-violet-100 {
    background-color: #ddd6fe !important;
}

/* Online Payment Option */
button[onclick^="processIyzicoPayment"]:hover {
    border-color: #d946ef !important;
    background: linear-gradient(135deg, #fdf4ff 0%, #fae8ff 100%) !important;
    box-shadow: 0 10px 15px -3px rgba(217, 70, 239, 0.12) !important;
    transform: translateY(-2px) !important;
}
button[onclick^="processIyzicoPayment"]:hover svg {
    color: #c084fc !important;
}
button[onclick^="processIyzicoPayment"]:hover div.bg-fuchsia-100 {
    background-color: #f5d0fe !important;
}

/* Print Adisyon Button */
#print-adisyon-btn {
    border-radius: 16px !important;
    padding: 16px !important;
    background-color: #f1f5f9 !important;
    border: 1px solid #e2e8f0 !important;
    transition: all 0.2s ease !important;
}
#print-adisyon-btn:hover {
    background-color: #e2e8f0 !important;
    border-color: #cbd5e1 !important;
}

/* Modals with premium blur styling */
#mixed-payment-modal, 
#table-history-modal, 
#refund-modal, 
#count-cash-modal,
#payment-prep-approval-modal {
    backdrop-filter: blur(8px) !important;
    -webkit-backdrop-filter: blur(8px) !important;
    background-color: rgba(15, 23, 42, 0.4) !important;
    transition: all 0.3s ease-out !important;
}
#mixed-payment-modal > div, 
#table-history-modal > div, 
#refund-modal > div, 
#count-cash-modal > div,
#payment-prep-approval-modal > div {
    border-radius: 28px !important;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25) !important;
    border: 1px solid #f1f5f9 !important;
    overflow: hidden !important;
}

/* Scrollbars */
#pos-dashboard ::-webkit-scrollbar {
    width: 6px;
    height: 6px;
}
#pos-dashboard ::-webkit-scrollbar-track {
    background: transparent;
}
#pos-dashboard ::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 9999px;
}
#pos-dashboard ::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}

/* Mixed payment numbers design */
#mixed-payment-total {
    font-size: 2.25rem !important;
    font-weight: 800 !important;
    letter-spacing: -0.04em !important;
}
#mixed-payment-remaining {
    font-size: 1.5rem !important;
    font-weight: 800 !important;
    letter-spacing: -0.03em !important;
}

/* Mobile Responsive Enhancements */
@media (max-width: 1023px) {
    /* Ensure sidebar overlay covers entire screen on mobile */
    #sidebar-overlay {
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        right: 0 !important;
        bottom: 0 !important;
        width: 100vw !important;
        height: 100vh !important;
        z-index: 9998 !important;
    }
    
    /* Fix sidebar z-index on mobile */
    #zone-sidebar {
        z-index: 9999 !important;
        box-shadow: 4px 0 20px rgba(0, 0, 0, 0.15) !important;
    }
    
    /* Improve touch targets on mobile */
    #pos-dashboard button {
        -webkit-tap-highlight-color: rgba(249, 115, 22, 0.2);
        touch-action: manipulation;
    }
    
    /* Better scrolling on mobile */
    #tables-view,
    #zone-list,
    #payment-orders-list {
        -webkit-overflow-scrolling: touch;
        scroll-behavior: smooth;
    }
    
    /* Fix safe area for iOS */
    @supports (padding: max(0px)) {
        #pos-dashboard {
            padding-top: env(safe-area-inset-top);
            padding-bottom: env(safe-area-inset-bottom);
            padding-left: env(safe-area-inset-left);
            padding-right: env(safe-area-inset-right);
        }
    }
    
    /* Prevent horizontal scroll on mobile */
    body, html {
        overflow-x: hidden;
        max-width: 100vw;
    }
    
    /* Zone grouped view cards - better mobile spacing */
    #zone-grouped-view button {
        min-height: 100px !important;
        padding: 1rem !important;
    }
    
    /* Payment view - stack vertically on mobile */
    #payment-view > div:first-child {
        flex-direction: column !important;
    }
    
    /* Payment methods - horizontal scroll on mobile */
    #payment-view .grid {
        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)) !important;
    }
}

/* Desktop specific overrides */
@media (min-width: 1024px) {
    #zone-sidebar {
        position: relative !important;
        transform: translateX(0) !important;
    }
    
    #sidebar-overlay {
        display: none !important;
    }
}

/* Zone header spacing on mobile */
@media (max-width: 767px) {
    #zone-grouped-view > div {
        margin-bottom: 1.5rem !important;
    }
    
    #zone-grouped-view h2 {
        font-size: 1.125rem !important;
        margin-bottom: 0.75rem !important;
    }
}

/* Improve category buttons on mobile */
@media (max-width: 1023px) {
    .category-btn {
        min-height: 40px !important;
        padding: 0.625rem 1rem !important;
        font-size: 0.875rem !important;
    }
}

/* Better table card spacing on mobile */
@media (max-width: 639px) {
    #standard-grid-view,
    #zone-grouped-view .grid {
        gap: 1rem !important;
    }
}
</style>

<?php if ($isSuperAdmin): ?>
        </div>
        <!-- POS Management View closing div -->
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
            // Business ID in URL - load business info directly from API and show POS view
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
                        
                        // Show POS management view
                        document.getElementById('business-selection-view').classList.add('hidden');
                        document.getElementById('pos-management-view').classList.remove('hidden');
                        const posDashboard = document.getElementById('pos-dashboard');
                        if (posDashboard) {
                            posDashboard.style.display = 'flex';
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
                    
                    // Show POS management view
                    document.getElementById('business-selection-view').classList.add('hidden');
                    document.getElementById('pos-management-view').classList.remove('hidden');
                    const posDashboard = document.getElementById('pos-dashboard');
                    if (posDashboard) {
                        posDashboard.style.display = 'flex';
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
    BusinessSelector.showSelectionView('business-selection-view', 'pos-management-view');
    const posDashboard = document.getElementById('pos-dashboard');
    if (posDashboard) {
        posDashboard.style.display = 'none';
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

