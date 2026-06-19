<?php
/**
 * Waiter Dashboard View
 * Garson paneli - Zone bazlı masa yönetimi ve anlık bildirimler
 */

require_once __DIR__ . '/../../helpers/translations.php';
require_once __DIR__ . '/../partials/icons.php';
require_once __DIR__ . '/../components/button.php';
require_once __DIR__ . '/../components/card.php';
require_once __DIR__ . '/../components/badge.php';
require_once __DIR__ . '/../components/notification.php';

$baseUrl = defined('BASE_URL') ? BASE_URL : '';
$translations = getTranslations(getCurrentLanguage());
$tablesGrouped = $tables_grouped ?? [];
$zones = $zones ?? [];
$unreadNotifications = $unread_notifications ?? 0;

// Get waiter translations
$waiterTranslations = [
    'title' => t('waiter.title', 'Garson Paneli'),
    'zones' => t('waiter.zones', 'Bölgeler'),
    'all_zones' => t('waiter.all_zones', 'Tüm Bölgeler'),
    'free' => t('waiter.free', 'Boş'),
    'occupied' => t('waiter.occupied', 'Dolu'),
    'payment_pending' => t('waiter.payment_pending', 'Ödeme Bekliyor'),
    'call_waiter' => t('waiter.call_waiter', 'Garson Çağırıldı'),
    'request_bill' => t('waiter.request_bill', 'Hesap İstendi'),
    'order_ready' => t('waiter.order_ready', 'Sipariş Hazır'),
    'new_order' => t('waiter.new_order', 'Yeni Sipariş'),
    'kitchen_issue' => t('waiter.kitchen_issue', 'Mutfak Sorunu'),
    'table_details' => t('waiter.table_details', 'Masa Detayları'),
    'total' => t('waiter.total', 'Toplam'),
    'notifications' => t('waiter.notifications', 'Bildirimler'),
    'no_notifications' => t('waiter.no_notifications', 'Bildirim yok'),
    'loading' => t('waiter.loading', 'Yükleniyor...'),
    'search' => t('waiter.search', 'Ara...'),
    'no_tables' => t('waiter.no_tables', 'Masa bulunamadı'),
    'orders' => t('waiter.orders', 'Siparişler'),
    'status' => t('waiter.status', 'Durum'),
    'mark_read' => t('waiter.mark_read', 'Bildirimleri Okundu İşaretle'),
    'new' => t('waiter.new', 'Yeni'),
    'status_free' => t('waiter.status_free', 'Boş'),
    'status_occupied' => t('waiter.status_occupied', 'Dolu'),
    'status_payment_pending' => t('waiter.status_payment_pending', 'Ödeme Bekliyor'),
    'status_pending' => t('waiter.status_pending', 'Beklemede'),
    'error_occurred' => t('waiter.error_occurred', 'Hata oluştu'),
    'add_item' => t('waiter.add_item', 'Ürün Ekle'),
    'transfer_to_cashier' => t('waiter.transfer_to_cashier', 'Kasiyere Devret'),
    'clear_table' => t('waiter.clear_table', 'Masayı Boşalt'),
    'okunmamış' => t('waiter.unread', 'okunmamış'),
    'order' => t('waiter.order', 'Sipariş'),
    'all_zones_text' => t('waiter.all_zones_text', 'Tüm Bölgeler'),
    'deliver_order' => t('waiter.deliver_order', 'Teslim Et'),
    'ready_orders' => t('waiter.ready_orders', 'Servise Hazır Siparişler'),
    'no_ready_orders' => t('waiter.no_ready_orders', 'Servise hazır sipariş yok'),
    'accept_order' => t('waiter.accept_order', 'Üzerine Al'),
    'delivered' => t('waiter.delivered', 'Teslim Edildi'),
    'table_info' => t('waiter.table_info', 'Masa bilgileri'),
    'table_info_and_orders' => t('waiter.table_info_and_orders', 'Masa bilgileri ve siparişler'),
    'ready_orders_count' => t('waiter.ready_orders_count', 'servise hazır sipariş'),
    'total_orders' => t('waiter.total_orders', 'toplam sipariş'),
    'orders_singular' => t('waiter.orders_singular', 'sipariş'),
    'products' => t('waiter.products', 'ürün'),
    'completed' => t('waiter.completed', 'Tamamlandı'),
    'cancelled' => t('waiter.cancelled', 'İptal Edildi')
];

$isSuperAdmin = $is_super_admin ?? false;
?>

<style>
    /* Native App Feel - Optimized Touch Interactions */
    * {
        -webkit-tap-highlight-color: transparent;
    }
    
    button, a, [role="button"], [onclick] {
        touch-action: manipulation;
        user-select: none;
        -webkit-user-select: none;
    }
    
    .overflow-y-auto, .overflow-x-auto {
        -webkit-overflow-scrolling: touch;
        overscroll-behavior: contain;
        scroll-behavior: smooth;
    }
    
    /* Smooth button interactions */
    button {
        transition: transform 0.1s ease, background-color 0.2s ease, box-shadow 0.2s ease;
    }
    
    button:active:not(:disabled) {
        transform: scale(0.97);
    }
    
    /* Better scrollbar for desktop */
    @media (min-width: 1024px) {
        .overflow-y-auto::-webkit-scrollbar {
            width: 8px;
        }
        
        .overflow-y-auto::-webkit-scrollbar-track {
            background: transparent;
        }
        
        .overflow-y-auto::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }
        
        .overflow-y-auto::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
    }

</style>

<?php if ($isSuperAdmin): ?>
<!-- SUPER ADMIN VIEW: Business Selection First -->
<div class="q-page q-biz-theme animate-slide-up">
  <div class="q-container q-stack q-stack--lg">
    <div id="business-selection-view">
        <header class="q-page-header">
            <div>
                <p class="q-page-header__eyebrow">Personel</p>
                <h1 class="q-page-header__title">Garson Paneli — İşletme Seçin</h1>
                <p class="q-page-header__subtitle">Garson paneline erişmek istediğiniz işletmeyi seçin</p>
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
    
    <!-- Waiter Management View -->
    <div id="waiter-management-view" class="hidden">
        <header class="flex items-center gap-3 mb-4">
            <button onclick="backToBusinessSelection()" class="p-2 hover:bg-slate-200 rounded-lg transition-all">
                <svg class="w-6 h-6 text-slate-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
            </button>
            <h1 class="text-xl sm:text-2xl lg:text-3xl font-black text-indigo-600 tracking-tighter">
                <span id="selected-business-name"></span> - Garson Paneli
            </h1>
        </header>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="flex h-full min-h-0 max-h-full bg-[#f4f5fa] overflow-hidden q-biz-theme q-biz-ops" id="waiter-dashboard" <?php echo $isSuperAdmin ? 'hidden' : ''; ?>>
    <!-- Zone Sidebar -->
    <div id="zone-sidebar" class="pos-zone-sidebar sidebar-mobile fixed left-0 top-0 h-full w-[280px] sm:w-80 bg-white border border-slate-200 shadow-xl transform transition-transform duration-300 ease-out lg:relative lg:w-64 -translate-x-full lg:translate-x-0 rounded-r-2xl lg:rounded-2xl" style="max-width: 85vw; z-index: 9999; padding-top: env(safe-area-inset-top);">
        <div class="flex flex-col h-full">
            <div class="p-4 sm:p-5 border-b border-slate-200 flex items-center justify-between shrink-0" style="padding-top: max(1rem, env(safe-area-inset-top));">
                <h2 class="text-xl sm:text-2xl font-black text-slate-900"><?php echo $waiterTranslations['zones']; ?></h2>
                <button type="button" onclick="toggleSidebar()" class="lg:hidden p-3 hover:bg-slate-100 active:bg-slate-200 rounded-xl touch-manipulation min-w-[44px] min-h-[44px] flex items-center justify-center transition-colors" aria-label="<?php echo t('common.close', 'Kapat'); ?>">
                    <?php echo icon_x(['class' => 'w-6 h-6 text-slate-700']); ?>
                </button>
            </div>
            <nav class="flex-1 overflow-y-auto p-4 sm:p-5 -webkit-overflow-scrolling-touch" id="zone-list" style="padding-bottom: max(1rem, env(safe-area-inset-bottom));">
                <div class="text-center text-slate-400 py-10 text-base"><?php echo t('common.loading', 'Yükleniyor...'); ?></div>
            </nav>
            <div class="p-4 sm:p-5 border-t border-slate-200 shrink-0">
                <button type="button" onclick="showNotificationsModal()" class="pos-chip-toolbar flex items-center gap-2 px-3 py-2.5 w-full text-left">
                    <?php echo icon_bell(['class' => 'w-5 h-5 text-indigo-600 shrink-0']); ?>
                    <div class="flex-1 min-w-0 text-left">
                        <div class="text-sm font-bold text-slate-900"><?php echo $waiterTranslations['notifications']; ?></div>
                        <div class="text-xs text-slate-500 truncate" id="notification-count"><?php echo $unreadNotifications; ?> <?php echo $waiterTranslations['okunmamış']; ?></div>
                    </div>
                </button>
            </div>
        </div>
    </div>

    <div id="sidebar-overlay" class="sidebar-overlay fixed inset-0 bg-black bg-opacity-50 hidden lg:hidden" onclick="toggleSidebar()" style="z-index: 9998; transition: opacity 0.3s ease-out;"></div>

    <div class="flex-1 flex flex-col overflow-hidden min-w-0 min-h-0">
        <div class="flex-1 min-h-0 p-3 sm:p-4 md:p-5 lg:p-6 overflow-y-auto no-scrollbar w-full" id="tables-view" style="max-width: 100%; padding-top: max(0.75rem, env(safe-area-inset-top)); padding-bottom: max(1rem, env(safe-area-inset-bottom));">
            <header class="flex flex-col sm:flex-row justify-between sm:items-center mb-3 sm:mb-4 md:mb-5 gap-3 sm:gap-4 shrink-0">
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2 sm:gap-3 flex-wrap">
                        <h1 class="text-2xl sm:text-3xl md:text-4xl font-black tracking-tighter text-slate-900 leading-tight shrink-0"><?php echo $waiterTranslations['title']; ?></h1>
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
                        <?php echo t('waiter.subtitle', 'Masa ve Sipariş Yönetimi'); ?>
                    </p>
                    <div id="waiter-status-summary" class="flex items-center gap-2 mt-3 flex-wrap"></div>
                </div>
                <div class="flex items-center gap-2 sm:gap-3 w-full sm:w-auto flex-wrap sm:flex-nowrap">
                    <button type="button" onclick="toggleSidebar()" class="lg:hidden p-3 hover:bg-slate-100 active:bg-slate-200 rounded-xl touch-manipulation min-w-[48px] min-h-[48px] flex items-center justify-center transition-colors shadow-sm shrink-0" aria-label="Menü">
                        <?php echo icon_menu(['class' => 'w-7 h-7 text-slate-700']); ?>
                    </button>
                    <input type="text" id="table-search" placeholder="<?php echo $waiterTranslations['search']; ?>"
                           class="flex-1 sm:flex-none px-3 sm:px-4 py-2.5 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 min-w-0 bg-white shadow-sm">
                    <button type="button" onclick="toggleStandardView()" id="zone-view-toggle" class="pos-btn-primary px-4 sm:px-5 py-3 sm:py-3.5 rounded-xl font-bold text-sm sm:text-base transition-all touch-manipulation min-h-[48px] shrink-0">
                        <span id="zone-view-text" class="whitespace-nowrap"><?php echo t('pos.standardView', 'Standart Görünüm'); ?></span>
                    </button>
                    <button type="button" onclick="showNotificationsModal()" class="relative p-3 hover:bg-slate-100 active:bg-slate-200 rounded-xl transition-colors touch-manipulation min-w-[48px] min-h-[48px] flex items-center justify-center shadow-sm shrink-0" title="<?php echo $waiterTranslations['notifications']; ?>">
                        <?php echo icon_bell(['class' => 'w-6 h-6 text-slate-700']); ?>
                        <span id="header-notification-badge" class="hidden absolute -top-1 -right-1 min-w-[1.125rem] h-[1.125rem] px-1 bg-red-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center"></span>
                    </button>
                    <button type="button" onclick="toggleFullscreen()" class="p-3 hover:bg-slate-100 active:bg-slate-200 rounded-xl transition-colors touch-manipulation min-w-[48px] min-h-[48px] flex items-center justify-center shadow-sm shrink-0" title="<?php echo t('titles.fullscreen'); ?>">
                        <svg class="w-6 h-6 text-slate-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"></path></svg>
                    </button>
                    <a href="<?php echo BASE_URL; ?>/logout" class="p-3 hover:bg-red-100 active:bg-red-200 rounded-xl transition-colors text-red-500 touch-manipulation min-w-[48px] min-h-[48px] flex items-center justify-center shadow-sm shrink-0" title="<?php echo t('common.logout', 'Çıkış Yap'); ?>">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                    </a>
                </div>
            </header>

            <a href="<?php echo BASE_URL; ?>/business/queue"
               id="qd-queue-nudge"
               class="hidden mb-3 px-3 py-2.5 rounded-xl border border-amber-200 bg-amber-50 text-amber-900 text-sm font-bold items-center gap-3 hover:shadow-sm shrink-0"
               style="text-decoration:none">
                <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-amber-500 text-white font-black shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 2m6-2a9 9 0 1 1-18 0 9 9 0 0 1 18 0z"/></svg>
                </span>
                <span class="flex-1 min-w-0">
                    <span id="qd-queue-nudge-msg">Sırada <span id="qd-queue-nudge-n">0</span> misafir bekliyor · boş masaya çağır</span>
                </span>
                <span class="shrink-0 text-[11px] font-black uppercase tracking-widest text-amber-700">Çağır</span>
            </a>

            <div id="tables-loading" class="text-center py-20">
                <div class="text-slate-400"><?php echo $waiterTranslations['loading']; ?></div>
            </div>
            <div id="tables-content" class="hidden relative" style="z-index: 1;">
                <!-- Tables loaded by JavaScript -->
            </div>
        </div>
    </div>
</div>
<?php if ($isSuperAdmin): ?>
        </div>
        <!-- Waiter Management View closing div -->
    </div>
    <!-- Super admin container closing div -->
</div>
<?php endif; ?>

<!-- Table Details Modal -->
<div id="table-details-modal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden items-center justify-center p-0 sm:p-4">
    <div class="bg-white rounded-none sm:rounded-2xl shadow-2xl max-w-4xl w-full h-full sm:h-auto sm:max-h-[85vh] overflow-hidden flex flex-col">
        <div class="p-4 sm:p-6 border-b border-slate-200 bg-gradient-to-br from-slate-50 via-white to-indigo-50 flex items-center justify-between safe-area-top">
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 sm:gap-3 mb-2">
                    <div class="w-1 h-6 sm:h-8 bg-gradient-to-b from-indigo-500 to-indigo-600 rounded-full shrink-0"></div>
                    <div class="min-w-0 flex-1">
                        <h2 class="text-xl sm:text-2xl md:text-3xl font-black text-indigo-600 tracking-tight truncate" id="modal-table-name"><?php echo $waiterTranslations['table_details']; ?></h2>
                        <p class="text-xs sm:text-sm text-slate-500 font-medium mt-1 truncate" id="modal-table-subtitle"><?php echo $waiterTranslations['table_info_and_orders']; ?></p>
                    </div>
                </div>
            </div>
            <button onclick="closeTableModal()" class="p-2 sm:p-3 hover:bg-slate-100 rounded-xl transition-all hover:scale-110 ml-2 sm:ml-4 shrink-0 touch-manipulation" aria-label="Kapat">
                <?php echo icon_x(['class' => 'w-5 h-5 sm:w-6 sm:h-6 text-slate-600']); ?>
            </button>
        </div>
        <div class="flex-1 overflow-y-auto p-4 sm:p-6 bg-slate-50" id="modal-content">
            <!-- Content loaded by JavaScript -->
        </div>
    </div>
</div>

<!-- Move Table Modal -->
<div id="move-table-modal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full max-h-[85vh] overflow-hidden flex flex-col">
        <div class="p-4 sm:p-6 border-b border-slate-200 bg-gradient-to-br from-purple-50 via-white to-purple-50 flex items-center justify-between">
            <div class="flex-1 min-w-0">
                <h2 class="text-xl sm:text-2xl font-black text-indigo-600">Masa Taşı</h2>
                <p class="text-xs sm:text-sm text-slate-500 font-medium mt-1" id="move-table-from-info">Masa seçiliyor...</p>
            </div>
            <button onclick="closeMoveTableModal()" class="p-2 sm:p-3 hover:bg-slate-100 rounded-xl transition-all hover:scale-110 ml-2 sm:ml-4 shrink-0 touch-manipulation" aria-label="Kapat">
                <?php echo icon_x(['class' => 'w-5 h-5 sm:w-6 sm:h-6 text-slate-600']); ?>
            </button>
        </div>
        <div class="flex-1 overflow-y-auto p-4 sm:p-6 bg-slate-50">
            <div class="mb-4">
                <label class="block text-sm font-bold text-slate-700 mb-2">Hedef Masa Seçin:</label>
                <input type="text" id="table-search-move" placeholder="Masa ara..." 
                       class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 mb-4">
                <div id="tables-list-move" class="grid gap-2 sm:gap-3 min-w-0" style="grid-template-columns: repeat(auto-fill, minmax(min(100%, 9rem), 1fr));">
                    <!-- Tables will be loaded here -->
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Notifications Modal -->
<div id="notifications-modal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden items-center justify-center p-0 sm:p-4">
    <div class="bg-white rounded-none sm:rounded-2xl shadow-2xl max-w-2xl w-full h-full sm:h-auto sm:max-h-[85vh] overflow-hidden flex flex-col">
        <div class="p-4 sm:p-6 border-b border-slate-200 bg-gradient-to-br from-indigo-50 via-white to-slate-50 flex items-center justify-between safe-area-top">
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 sm:gap-3 mb-2">
                    <div class="w-1 h-6 sm:h-8 bg-gradient-to-b from-indigo-500 to-indigo-600 rounded-full shrink-0"></div>
                    <div class="min-w-0 flex-1">
                        <h2 class="text-xl sm:text-2xl md:text-3xl font-black text-indigo-600 tracking-tight truncate"><?php echo $waiterTranslations['notifications']; ?></h2>
                        <p class="text-xs sm:text-sm text-slate-500 font-medium mt-1 truncate" id="notifications-modal-subtitle"><?php echo $waiterTranslations['okunmamış']; ?></p>
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-2 ml-2 sm:ml-4 shrink-0">
                <!-- Tümünü Okundu İşaretle - Göz İkonu -->
                <button onclick="markAllNotificationsRead()" class="p-2.5 sm:p-3 bg-blue-500 hover:bg-blue-600 text-white rounded-xl transition-all hover:scale-110 touch-manipulation shadow-md" title="Tümünü Okundu İşaretle" aria-label="Tümünü Okundu İşaretle">
                    <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                    </svg>
                </button>
                <button onclick="closeNotificationsModal()" class="p-2 sm:p-3 hover:bg-slate-100 rounded-xl transition-all hover:scale-110 touch-manipulation" aria-label="Kapat">
                    <?php echo icon_x(['class' => 'w-5 h-5 sm:w-6 sm:h-6 text-slate-600']); ?>
                </button>
            </div>
        </div>
        <div class="flex-1 overflow-y-auto p-4 sm:p-6 bg-slate-50" id="notifications-modal-content">
            <div class="text-center py-10">
                <div class="inline-block animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-indigo-500"></div>
                <p class="mt-4 text-slate-600 font-bold"><?php echo $waiterTranslations['loading']; ?></p>
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
const baseUrl = '<?php echo $baseUrl; ?>';
const waiterTranslations = <?php echo _safeJson($waiterTranslations ?? [], '{}'); ?>;
let tablesData = {};
let notificationsData = {};
let currentZoneFilter = 'all';
let isStandardView = false;
let refreshInterval = null;

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

// Get status text in Turkish
function getStatusText(status) {
    const statusMap = {
        'FREE': waiterTranslations.status_free || waiterTranslations.free || 'Boş',
        'OCCUPIED': waiterTranslations.status_occupied || waiterTranslations.occupied || 'Dolu',
        'PAYMENT_PENDING': waiterTranslations.status_payment_pending || waiterTranslations.payment_pending || 'Ödeme Bekliyor',
        'PENDING': waiterTranslations.status_pending || 'Beklemede',
        'COMPLETED': waiterTranslations.completed || 'Tamamlandı',
        'CANCELLED': waiterTranslations.cancelled || 'İptal Edildi'
    };
    return statusMap[status] || status;
}

function isPageActive() {
    return !document.hidden;
}

// Sidebar toggle is handled by sidebar.js
// If sidebar.js is not loaded, provide fallback
if (typeof window.toggleSidebar !== 'function') {
    window.toggleSidebar = function() {
        const sidebar = document.getElementById('zone-sidebar') || document.getElementById('sidebar-menu');
        const overlay = document.getElementById('sidebar-overlay');
        const isDesktop = window.innerWidth >= 1024;
        
        if (!sidebar) {
            console.warn('Sidebar not found');
            return;
        }
        
        // Check if sidebar is open
        // On mobile: check if transform is translateX(-100%) or if -translate-x-full class exists
        // On desktop: sidebar should always be visible
        let isOpen;
        if (isDesktop) {
            // Desktop: sidebar is open if it doesn't have -translate-x-full
            isOpen = !sidebar.classList.contains('-translate-x-full');
        } else {
            // Mobile: check computed style and class
            const hasTranslateXFull = sidebar.classList.contains('-translate-x-full');
            const computedStyle = window.getComputedStyle(sidebar);
            const transform = computedStyle.transform;
            // If transform is 'none' or doesn't translate X negatively, sidebar is visible
            isOpen = !hasTranslateXFull && (transform === 'none' || !transform.includes('translateX') || !transform.includes('-100%'));
        }
        
        if (isOpen) {
            // Close sidebar
            sidebar.classList.remove('open');
            sidebar.classList.add('-translate-x-full');
            sidebar.style.transform = 'translateX(-100%)';
            
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
            sidebar.style.transform = 'translateX(0)';
            
            // On desktop, ensure it's visible
            if (isDesktop) {
                sidebar.style.transform = '';
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

// Toggle standard view vs zone-grouped view (mirrors POS toggleZoneView)
function toggleStandardView() {
    isStandardView = !isStandardView;

    const toggleBtn = document.getElementById('zone-view-toggle');
    const toggleText = document.getElementById('zone-view-text');

    if (isStandardView) {
        if (toggleBtn) {
            toggleBtn.classList.remove('pos-btn-primary');
            toggleBtn.classList.add('bg-slate-100', 'text-slate-700');
        }
        if (toggleText) toggleText.textContent = 'Bölge Görünümü';
    } else {
        if (toggleBtn) {
            toggleBtn.classList.add('pos-btn-primary');
            toggleBtn.classList.remove('bg-slate-100', 'text-slate-700');
        }
        if (toggleText) toggleText.textContent = 'Standart Görünüm';
    }

    renderTables();
}

// Fullscreen toggle - makes content fullscreen (sidebar stays visible)
function toggleFullscreen() {
    const overlay = document.getElementById('sidebar-overlay');
    
    // Hide overlay in fullscreen
    if (overlay) {
        overlay.classList.add('hidden');
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
    const sidebar = document.getElementById('zone-sidebar') || document.getElementById('sidebar-menu');
    const overlay = document.getElementById('sidebar-overlay');
    
    if (document.fullscreenElement) {
        // In fullscreen, hide overlay but keep sidebar visible
        if (overlay) {
            overlay.classList.add('hidden');
        }
        // Ensure sidebar is visible in fullscreen
        if (sidebar) {
            sidebar.style.display = '';
            sidebar.classList.remove('-translate-x-full');
        }
    } else {
        // Exit fullscreen - restore normal state
        if (sidebar) {
            sidebar.style.display = '';
            // On mobile, sidebar should be hidden by default
            if (window.innerWidth < 1024) {
                sidebar.classList.add('-translate-x-full');
            } else {
                sidebar.classList.remove('-translate-x-full');
            }
        }
    }
});

// Filter by zone
function waiterZoneBtnClass(isActive) {
    const base = 'btn-touch pos-zone-nav-btn w-full px-4 py-3.5 rounded-xl font-bold text-sm mb-2 text-left transition-all active:scale-[0.98] zone-item';
    return base + (isActive ? ' pos-zone-nav-btn--active' : ' pos-zone-nav-btn--inactive');
}

function setActiveZoneButton(activeEl) {
    document.querySelectorAll('#zone-list .zone-item').forEach(item => {
        item.classList.remove('pos-zone-nav-btn--active');
        item.classList.add('pos-zone-nav-btn--inactive');
    });
    if (activeEl) {
        activeEl.classList.add('pos-zone-nav-btn--active');
        activeEl.classList.remove('pos-zone-nav-btn--inactive');
    }
}

function filterZone(zoneName) {
    currentZoneFilter = zoneName;
    setActiveZoneButton(document.querySelector(`#zone-list [data-zone="${zoneName}"]`));
    renderTables();
}

// Load tables
let isTablesLoading = false;
async function loadTables() {
    if (!isPageActive() || isTablesLoading) {
        return;
    }
    isTablesLoading = true;
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 10000);
    try {
        const response = await fetch(`${baseUrl}/api/waiter/tables`, {
            signal: controller.signal,
            cache: 'no-cache'
        });
        
        // Check if response is JSON
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            const text = await response.text();
            console.error('API returned non-JSON response:', text.substring(0, 200));
            throw new Error('API returned non-JSON response');
        }
        
        const data = await response.json();
        
        if (data.zones) {
            // Log API response for debugging (only in development)
            // console.log('Tables API response:', data);
            
            tablesData = data;
            
            // CRITICAL: Build authorized tables list from API response
            // Only tables returned from /api/waiter/tables should be checked
            authorizedTableIds.clear();
            unauthorizedTableIds.clear();
            
            // Mark all tables from API response as authorized (they're already tenant-filtered by backend)
            Object.values(data.zones || {}).forEach(zone => {
                const tables = zone.tables || [];
                tables.forEach(table => {
                    const tableId = table.table_id || '';
                    if (tableId) {
                        authorizedTableIds.add(tableId);
                        // Remove from unauthorized list if it was there
                        unauthorizedTableIds.delete(tableId);
                    }
                });
            });
            
            renderZoneList();
            renderTables();
            // Batch check ready orders after rendering (with delay to let rendering complete)
            // Only check tables that are in authorizedTableIds
            setTimeout(() => {
                batchCheckReadyOrders();
            }, 500);
        } else {
            console.error('Invalid API response format:', data);
        }
    } catch (error) {
        if (error.name !== 'AbortError') {
            console.error('Error loading tables:', error);
        }
    } finally {
        clearTimeout(timeoutId);
        isTablesLoading = false;
        const loadingEl = document.getElementById('tables-loading');
        const contentEl = document.getElementById('tables-content');
        if (loadingEl) loadingEl.classList.add('hidden');
        if (contentEl) contentEl.classList.remove('hidden');
    }
}

const WAITER_FLOOR_PLAN_ICON = '<svg class="pos-zone-nav-icon w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v14a1 1 0 01-1 1H5a1 1 0 01-1-1V5z"/><rect x="7" y="7" width="3.5" height="2.5" rx=".5" stroke-width="1.5"/><rect x="13.5" y="7" width="3.5" height="2.5" rx=".5" stroke-width="1.5"/><circle cx="8.5" cy="16" r="1.75" stroke-width="1.5"/><circle cx="15.5" cy="16" r="1.75" stroke-width="1.5"/></svg>';
const WAITER_TABLE_TOP_ICON = '<svg class="biz-icon-table-top w-8 h-8 sm:w-9 sm:h-9 shrink-0 text-indigo-500/75" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><rect x="7" y="7" width="10" height="10" rx="1.5" stroke-width="1.5"/><circle cx="12" cy="4.5" r="1" fill="currentColor" stroke="none"/><circle cx="12" cy="19.5" r="1" fill="currentColor" stroke="none"/><circle cx="4.5" cy="12" r="1" fill="currentColor" stroke="none"/><circle cx="19.5" cy="12" r="1" fill="currentColor" stroke="none"/></svg>';

// Render zone list in sidebar
function updateStatusSummary() {
    const summaryEl = document.getElementById('waiter-status-summary');
    if (!summaryEl || !tablesData) return;

    let occupiedTables = 0;
    let freeTables = 0;
    Object.values(tablesData.zones || {}).forEach(zone => {
        (zone.tables || []).forEach(t => {
            const st = t.status || 'FREE';
            if (st === 'FREE') freeTables++;
            else occupiedTables++;
        });
    });

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

    updateStatusSummary();
    const zones = Object.keys(tablesData.zones || {});
    
    // "Tümü" butonu
    const allBtn = document.createElement('button');
    allBtn.className = waiterZoneBtnClass(currentZoneFilter === 'all');
    allBtn.setAttribute('data-zone', 'all');
    allBtn.innerHTML = `<span class="flex items-center justify-between gap-2 w-full"><span class="flex items-center gap-2 min-w-0 truncate">${WAITER_FLOOR_PLAN_ICON}<span class="truncate">${waiterTranslations.all_zones_text || waiterTranslations.all_zones}</span></span><span class="pos-zone-nav-count shrink-0">${Object.values(tablesData.zones || {}).reduce((sum, z) => sum + (z.total_count || 0), 0)}</span></span>`;
    allBtn.onclick = () => filterZone('all');
    container.innerHTML = '';
    container.appendChild(allBtn);
    
    // Zone butonları
    zones.forEach(zoneName => {
        const zone = tablesData.zones[zoneName];
        const btn = document.createElement('button');
        btn.className = waiterZoneBtnClass(currentZoneFilter === zoneName);
        btn.setAttribute('data-zone', zoneName);
        btn.innerHTML = `
            <div class="flex items-center justify-between gap-2 w-full">
                <span class="flex items-center gap-2 min-w-0 truncate">${WAITER_FLOOR_PLAN_ICON}<span class="truncate">${zoneName}</span></span>
                <span class="pos-zone-nav-count shrink-0">${zone.total_count || 0}</span>
            </div>
        `;
        btn.onclick = () => filterZone(zoneName);
        container.appendChild(btn);
    });
}

// Render tables
function renderTables() {
    const container = document.getElementById('tables-content');
    if (!container) return;
    
    container.innerHTML = '';
    
    const zones = tablesData.zones || {};
    const searchTerm = (document.getElementById('table-search')?.value || '').toLowerCase();
    
    if (isStandardView) {
        // Collect all tables across all zones
        let allTables = [];
        Object.keys(zones).forEach(zoneName => {
            const zone = zones[zoneName];
            const tables = zone.tables || [];
            tables.forEach(t => {
                t.zone_name = zoneName;
                allTables.push(t);
            });
        });
        
        // Filter by search term and selected zone (if zone filter is not 'all')
        const filteredTables = allTables.filter(table => {
            const matchesSearch = (table.name || '').toLowerCase().includes(searchTerm);
            const matchesZone = currentZoneFilter === 'all' || table.zone_name === currentZoneFilter;
            return matchesSearch && matchesZone;
        });
        
        if (filteredTables.length > 0) {
            const grid = document.createElement('div');
            grid.className = 'grid gap-3 sm:gap-4 md:gap-5 lg:gap-6 min-w-0'; 
            grid.style.gridTemplateColumns = 'repeat(auto-fill, minmax(min(100%, 9rem), 1fr))';
            grid.style.zIndex = '1';
            grid.style.position = 'relative';
            
            filteredTables.forEach(table => {
                const tableCard = createTableCard(table, table.zone_name || currentZoneFilter);
                grid.appendChild(tableCard);
            });
            
            container.appendChild(grid);
        }
    } else {
        // Eğer zone seçilmişse sadece o zone'u göster
        if (currentZoneFilter !== 'all' && zones[currentZoneFilter]) {
        const zone = zones[currentZoneFilter];
        const tables = zone.tables || [];
        
        // Filter by search term
        const filteredTables = tables.filter(table => {
            const name = (table.name || '').toLowerCase();
            return name.includes(searchTerm);
        });
        
        if (filteredTables.length > 0) {
            const grid = document.createElement('div');
            grid.className = 'grid gap-3 sm:gap-4 md:gap-5 lg:gap-6 min-w-0'; grid.style.gridTemplateColumns = 'repeat(auto-fill, minmax(min(100%, 9rem), 1fr))';
            grid.style.zIndex = '1';
            grid.style.position = 'relative';
            
            filteredTables.forEach(table => {
                const tableCard = createTableCard(table, currentZoneFilter);
                grid.appendChild(tableCard);
            });
            
            container.appendChild(grid);
        }
    } else {
        // Tüm zone'ları göster
        Object.keys(zones).forEach(zoneName => {
            const zone = zones[zoneName];
            const tables = zone.tables || [];
            
            // Filter by search term
            const filteredTables = tables.filter(table => {
                const name = (table.name || '').toLowerCase();
                return name.includes(searchTerm);
            });
            
            if (filteredTables.length === 0) {
                return;
            }
            
            // Zone section
            const zoneSection = document.createElement('div');
            zoneSection.className = 'mb-8';

            // Count occupied vs free for this zone (matching POS style)
            let zoneFreeCount = 0, zoneOccupiedCount = 0;
            filteredTables.forEach(t => {
                const st = t.status || 'FREE';
                if (st === 'FREE') zoneFreeCount++;
                else zoneOccupiedCount++;
            });

            const zoneHeader = document.createElement('div');
            zoneHeader.className = 'pos-zone-section-header flex items-center justify-between mb-4 sm:mb-5 md:mb-6';
            zoneHeader.innerHTML = `
                <h2 class="text-lg sm:text-xl md:text-2xl font-black text-slate-900">${escapeHtml(zoneName)}</h2>
                <div class="flex items-center gap-2">
                    ${zoneOccupiedCount > 0 ? `<span class="text-xs sm:text-sm font-bold px-2.5 py-1 bg-amber-100 border border-amber-300 rounded-lg text-amber-700">${zoneOccupiedCount} Dolu</span>` : ''}
                    ${zoneFreeCount > 0 ? `<span class="text-xs sm:text-sm font-bold px-2.5 py-1 bg-white border border-slate-200 rounded-lg text-slate-500">${zoneFreeCount} Boş</span>` : ''}
                    <span class="text-xs sm:text-sm text-slate-400 font-semibold px-2 py-1">${filteredTables.length} masa</span>
                </div>`;
            zoneSection.appendChild(zoneHeader);
            
            // Tables grid
            const grid = document.createElement('div');
            grid.className = 'grid gap-3 sm:gap-4 md:gap-5 lg:gap-6 min-w-0'; grid.style.gridTemplateColumns = 'repeat(auto-fill, minmax(min(100%, 9rem), 1fr))';
            grid.style.zIndex = '1';
            grid.style.position = 'relative';
            
            filteredTables.forEach(table => {
                const tableCard = createTableCard(table, zoneName);
                grid.appendChild(tableCard);
            });
            
            zoneSection.appendChild(grid);
            container.appendChild(zoneSection);
        });
    }
    }
    
    if (container.innerHTML === '') {
        container.innerHTML = `<div class="text-center py-20 text-slate-400">${waiterTranslations.no_tables}</div>`;
    }
    
    // Batch check ready orders after rendering (debounced)
    batchCheckReadyOrders();
}

// Create table card
function createTableCard(table, zoneName) {
    const card = document.createElement('div');
    card.className = 'relative h-full';
    card.style.zIndex = '1';
    card.style.position = 'relative';
    
    const tableId = table.table_id || '';
    const tableName = table.name || '';
    const status = table.status || 'FREE';
    const isFree = status === 'FREE';
    const isPaymentPending = status === 'PAYMENT_PENDING';
    const isOccupied = status === 'OCCUPIED';
    const isCustomerSeated = status === 'CUSTOMER_SEATED';
    
    // Get notification count for this table
    const notificationCount = notificationsData.table_counts?.[tableId] || 0;
    
    const isOccupiedSemantic = isOccupied || isCustomerSeated || isPaymentPending;
    
    let statusBadge = '';
    if (isFree) {
        statusBadge = '<span class="pos-table-badge pos-table-badge--empty">BOŞ</span>';
    } else if (isPaymentPending) {
        statusBadge = '<span class="pos-table-badge pos-table-badge--occupied" style="background-color: #fee2e2; color: #dc2626; border-color: #fca5a5;">' + (waiterTranslations.payment_pending || 'Ödeme Bekliyor') + '</span>';
    } else {
        statusBadge = '<span class="pos-table-badge pos-table-badge--occupied">DOLU</span>';
    }
    
    // Notification badge
    let notificationBadge = '';
    if (notificationCount > 0) {
        notificationBadge = `
            <div class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center text-[10px] font-bold shadow-sm z-10">
                ${notificationCount}
            </div>
        `;
    }
    
    const buttonClasses = `btn-touch pos-table-card w-full h-full p-4 sm:p-5 rounded-xl sm:rounded-2xl min-h-[100px] sm:min-h-[120px] transition-all active:scale-[0.98] relative group touch-manipulation ${
        isOccupiedSemantic ? 'pos-table-card--occupied' : 'pos-table-card--empty'
    }`;
    
    // Redirect to POS page for all tables (both free and occupied)
    const onClickFunction = `openTableMenu('${tableId}')`;
    
    card.innerHTML = `
        <button onclick="${onClickFunction}" 
                class="${buttonClasses}"
                style="z-index: 1 !important; position: relative !important;">
            ${notificationBadge}
            <div class="flex flex-col justify-between h-full w-full">
                <div class="flex items-start gap-2 mb-1 w-full">
                    ${WAITER_TABLE_TOP_ICON}
                    <div class="font-black text-base sm:text-lg md:text-xl text-slate-900 truncate flex-1 text-left min-w-0">${escapeHtml(tableName)}</div>
                    ${isOccupiedSemantic ? '<div class="pos-status-dot pos-status-dot--occupied ml-1 mt-1 shrink-0"></div>' : ''}
                </div>
                <div class="text-left w-full mt-2">
                    ${statusBadge}
                </div>
                <div class="text-[10px] sm:text-xs text-slate-500 font-semibold uppercase tracking-wider text-left mt-2">${zoneName}</div>
                ${!isFree ? `<div class="text-base sm:text-lg md:text-xl font-black text-slate-900 mt-auto pt-2 text-right pos-text-money" id="table-total-${tableId}">${waiterTranslations.loading || 'Yükleniyor...'}</div>` : `<div class="mt-auto pt-2" id="table-total-${tableId}"></div>`}
            </div>
        </button>
    `;
    
    // Load table total if not free
    if (!isFree) {
        loadTableTotal(tableId);
    }
    
    return card;
}

// Batch check READY orders for all tables (optimized)
// Only check tables that are returned from /api/waiter/tables endpoint
// This ensures we only check tables the user has access to (tenant isolation already applied)
let readyOrdersCheckCache = {};
let readyOrdersCheckTimeout = null;
let lastReadyOrdersCheck = 0;
let authorizedTableIds = new Set(); // Track tables that user has access to
let unauthorizedTableIds = new Set(); // Track tables that returned 403 to avoid repeated checks
function batchCheckReadyOrders() {
    // PERFORMANCE OPTIMIZATION: Use data already loaded by loadTables() instead of
    // making individual API calls per table. The tables API now includes ready_count
    // for each table, eliminating the N+1 API call problem entirely.
    if (!isPageActive() || !tablesData || !tablesData.zones) {
        return;
    }
    
    try {
        Object.values(tablesData.zones).forEach(zone => {
            const tables = zone.tables || [];
            tables.forEach(table => {
                const tableId = table.table_id || '';
                if (!tableId) return;
                
                const readyCount = parseInt(table.ready_count) || 0;
                updateReadyIndicator(tableId, readyCount);
            });
        });
    } catch (error) {
        console.debug('Error in batch check ready orders:', error);
    }
}

// Update ready indicator for a table
function updateReadyIndicator(tableId, readyCount) {
    // Find button by onclick attribute
    const buttons = document.querySelectorAll('button[onclick*="openTableMenu"], button[onclick*="showTableDetails"]');
    let targetButton = null;
    
    for (const button of buttons) {
        const onclick = button.getAttribute('onclick') || '';
        if (onclick.includes(`'${tableId}'`) || onclick.includes(`"${tableId}"`)) {
            targetButton = button;
            break;
        }
    }
    
    if (!targetButton) return;
    
    // Remove existing ready badge if any
    const existingBadge = targetButton.querySelector('.ready-order-badge');
    if (existingBadge) {
        existingBadge.remove();
    }
    
    // Remove ready border classes
    targetButton.classList.remove('border-green-500', 'ring-2', 'ring-green-300');
    
    if (readyCount > 0) {
        // Add READY indicator badge
        const readyBadge = document.createElement('div');
        readyBadge.className = 'ready-order-badge absolute top-2 right-2 bg-green-500 text-white rounded-full w-10 h-10 flex items-center justify-center text-sm font-black shadow-xl animate-pulse z-20';
        readyBadge.innerHTML = '🔔';
        readyBadge.title = `${readyCount} ${waiterTranslations.ready_orders_count || 'servise hazır sipariş'}`;
        targetButton.appendChild(readyBadge);
        
        // Add border highlight
        targetButton.classList.add('border-green-500', 'border-2', 'ring-2', 'ring-green-300');
        // Keep other border classes if they exist
    }
}

// Load table total - also check if table should actually be FREE
// Cache for table totals to prevent repeated API calls
const tableTotalCache = new Map();

// Helper function to determine table color based on order statuses
function getTableColorByOrderStatus(orders, tableStatus) {
    // Priority order: PAYMENT_PENDING > READY > PREPARING > PENDING > OCCUPIED
    
    if (tableStatus === 'PAYMENT_PENDING') {
        return {
            bg: 'bg-gradient-to-br from-red-50 to-red-100',
            border: 'border-red-400',
            badge: 'bg-red-100 text-red-700'
        };
    }
    
    if (!orders || orders.length === 0) {
        // No orders - default to blue for occupied
        return {
            bg: 'bg-blue-50',
            border: 'border-blue-200',
            badge: 'bg-blue-50 text-blue-600'
        };
    }
    
    // Check order statuses
    const hasReady = orders.some(order => (order.status || '') === 'READY');
    const hasPreparing = orders.some(order => (order.status || '') === 'PREPARING');
    const hasPending = orders.some(order => (order.status || '') === 'PENDING');
    
    if (hasReady) {
        // READY orders need attention - subtle emerald
        return {
            bg: 'bg-emerald-50',
            border: 'border-emerald-200',
            badge: 'bg-emerald-50 text-emerald-600'
        };
    }
    
    if (hasPreparing) {
        // PREPARING orders - subtle amber
        return {
            bg: 'bg-amber-50',
            border: 'border-amber-200',
            badge: 'bg-amber-50 text-amber-600'
        };
    }
    
    if (hasPending) {
        // PENDING orders (new) - subtle yellow
        return {
            bg: 'bg-yellow-50',
            border: 'border-yellow-200',
            badge: 'bg-yellow-50 text-yellow-600'
        };
    }
    
    // Default for occupied tables without specific status
    return {
        bg: 'bg-blue-50',
        border: 'border-blue-200',
        badge: 'bg-blue-50 text-blue-600'
    };
}

// Update table card color based on order statuses
function updateTableCardColor(tableId, orders, tableStatus) {
    const tableButton = document.querySelector(`button[onclick*="openTableMenu('${tableId}')"]`);
    if (!tableButton) return;
    
    const isFree = tableStatus === 'FREE';
    const isPaymentPending = tableStatus === 'PAYMENT_PENDING';
    const isCustomerSeated = tableStatus === 'CUSTOMER_SEATED';
    
    if (isFree) {
        // Free tables stay white
        tableButton.className = tableButton.className.replace(/bg-\S+/g, '').replace(/border-\S+/g, '').replace(/shadow-\S+/g, '').replace(/animate-\S+/g, '').replace(/\s+/g, ' ').trim();
        tableButton.className += ' bg-white border-2 border-slate-200 hover:border-slate-300 shadow-sm';
        return;
    }
    
    if (isPaymentPending) {
        tableButton.className = tableButton.className.replace(/bg-\S+/g, '').replace(/border-\S+/g, '').replace(/shadow-\S+/g, '').replace(/animate-\S+/g, '').replace(/\s+/g, ' ').trim();
        tableButton.className += ' bg-red-50 border-2 border-red-200 shadow-sm';
        return;
    }
    
    if (isCustomerSeated) {
        tableButton.className = tableButton.className.replace(/bg-\S+/g, '').replace(/border-\S+/g, '').replace(/shadow-\S+/g, '').replace(/animate-\S+/g, '').replace(/\s+/g, ' ').trim();
        tableButton.className += ' bg-amber-50 border-2 border-amber-200 shadow-sm';
        return;
    }
    
    // For occupied tables, determine color based on order statuses
    const colorScheme = getTableColorByOrderStatus(orders, tableStatus);
    
    // Update button classes - remove old color classes and add new ones
    tableButton.className = tableButton.className.replace(/bg-\S+/g, '').replace(/border-\S+/g, '').replace(/shadow-\S+/g, '').replace(/animate-\S+/g, '').replace(/\s+/g, ' ').trim();
    tableButton.className += ` ${colorScheme.bg} border-2 ${colorScheme.border} shadow-sm`;
    
    // Update status badge color if it exists
    // Use attribute selector to avoid CSS class name escaping issues with dots in Tailwind classes
    const statusBadge = tableButton.querySelector('span[class*="px-3"]') || 
                        tableButton.querySelector('span.badge') ||
                        tableButton.querySelector('span:last-child');
    if (statusBadge) {
        // Remove existing color classes, keep base classes
        const badgeClasses = statusBadge.className.split(' ').filter(cls => {
            return !cls.startsWith('bg-') || cls === 'bg-emerald-100' || cls === 'bg-slate-100';
        }).filter(cls => {
            return !cls.startsWith('text-') || cls === 'text-emerald-700' || cls === 'text-slate-700';
        });
        // Add new color
        statusBadge.className = [...badgeClasses, colorScheme.badge].join(' ');
    }
}

async function loadTableTotal(tableId) {
    // Skip if table is marked as unauthorized
    if (unauthorizedTableIds.has(tableId)) {
        const totalElement = document.getElementById(`table-total-${tableId}`);
        if (totalElement) {
            totalElement.remove();
        }
        return;
    }
    
    // Check cache first (5 second TTL)
    const cacheKey = `total_${tableId}`;
    const cached = tableTotalCache.get(cacheKey);
    if (cached && Date.now() - cached.timestamp < 5000) {
        const totalElement = document.getElementById(`table-total-${tableId}`);
        if (totalElement && cached.total !== null) {
            totalElement.textContent = `${parseFloat(cached.total).toFixed(2)} ₺`;
        }
        return;
    }
    
    const totalElement = document.getElementById(`table-total-${tableId}`);
    
    try {
        const response = await fetch(`${baseUrl}/api/waiter/table-details/${tableId}`);
        
        // Check if response is OK
        if (!response.ok) {
            if (response.status === 401 || response.status === 403) {
                // Mark as unauthorized and clear loading state
                unauthorizedTableIds.add(tableId);
                authorizedTableIds.delete(tableId);
                if (totalElement) {
                    totalElement.remove();
                }
                return;
            }
            // For other errors, clear loading state
            if (totalElement) {
                totalElement.remove();
            }
            return;
        }
        
        // Check if response is JSON
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            if (totalElement) {
                totalElement.remove();
            }
            return;
        }
        
        const data = await response.json();
        
        // Check for error in response
        if (data.error || !data.success) {
            if (data.error && (data.error.includes('Unauthorized') || data.error.includes('403') || data.error.includes('Forbidden'))) {
                unauthorizedTableIds.add(tableId);
                authorizedTableIds.delete(tableId);
            }
            // Clear loading state on error
            if (totalElement) {
                totalElement.remove();
            }
            return;
        }
        
        // Mark as authorized on success
        authorizedTableIds.add(tableId);
        unauthorizedTableIds.delete(tableId);
        
        if (data.table) {
            const tableStatus = data.table.status || 'FREE';
            const activeOrders = (data.orders || []).filter(order => {
                const status = order.status || '';
                return status !== 'SERVED' && status !== 'CANCELLED';
            });
            
            // If no active orders and table shows as occupied, it should be free
            if (activeOrders.length === 0 && tableStatus !== 'FREE') {
                // Table should be free - update status in tablesData if it exists
                if (tablesData.zones) {
                    Object.values(tablesData.zones).forEach(zone => {
                        const table = (zone.tables || []).find(t => t.table_id === tableId);
                        if (table) {
                            table.status = 'FREE';
                        }
                    });
                }
                // Remove total element since table is free
                if (totalElement) {
                    totalElement.remove();
                }
                // Clear cache
                tableTotalCache.delete(cacheKey);
                // Re-render tables to update status
                renderTables();
                return;
            }
            
            // Show total if there are active orders
            if (totalElement) {
                if (data.total_amount && activeOrders.length > 0) {
                    const total = parseFloat(data.total_amount).toFixed(2);
                    totalElement.textContent = `${total} ₺`;
                    // Cache successful result
                    tableTotalCache.set(cacheKey, {
                        total: data.total_amount,
                        timestamp: Date.now()
                    });
                } else {
                    totalElement.remove();
                    tableTotalCache.set(cacheKey, {
                        total: null,
                        timestamp: Date.now()
                    });
                }
            }
            
            // Update table card color based on order statuses
            if (activeOrders.length > 0) {
                updateTableCardColor(tableId, activeOrders, tableStatus);
            }
        } else if (totalElement) {
            totalElement.remove();
            tableTotalCache.set(cacheKey, {
                total: null,
                timestamp: Date.now()
            });
        }
    } catch (error) {
        console.error('Error loading table total:', error);
        // Clear loading state on error
        if (totalElement) {
            totalElement.remove();
        }
    }
}

// Load notifications - track by notification IDs to prevent duplicate alerts
let lastNotificationIds = new Set();
let isNotificationLoading = false;
let lastNotificationSummaryKey = '';

// Load shown notification IDs from sessionStorage on page load
let shownNotificationIds = new Set();
try {
    const savedIds = sessionStorage.getItem('waiter_shown_notification_ids');
    if (savedIds) {
        const idsArray = JSON.parse(savedIds);
        shownNotificationIds = new Set(idsArray);
    }
} catch (e) {
    console.error('Error loading shown notification IDs from sessionStorage:', e);
}

// Save shown notification IDs to sessionStorage
function saveShownNotificationIds() {
    try {
        // FIXED: Limit stored IDs to prevent unbounded sessionStorage growth
        let idsArray = Array.from(shownNotificationIds);
        if (idsArray.length > 200) {
            // Keep only the most recent 200 IDs
            idsArray = idsArray.slice(-200);
            shownNotificationIds = new Set(idsArray);
        }
        sessionStorage.setItem('waiter_shown_notification_ids', JSON.stringify(idsArray));
    } catch (e) {
        console.error('Error saving shown notification IDs to sessionStorage:', e);
    }
}

function getNotificationSummaryKey(totalUnread, tableCounts) {
    const entries = Object.entries(tableCounts || {})
        .sort(([a], [b]) => String(a).localeCompare(String(b)));
    const counts = entries.map(([tableId, count]) => `${tableId}:${count}`).join(',');
    return `${totalUnread}|${counts}`;
}

async function loadNotifications() {
    if (!isPageActive() || isNotificationLoading) {
        return;
    }
    isNotificationLoading = true;
    try {
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 8000);
        const response = await fetch(`${baseUrl}/api/waiter/table-notifications`, {
            signal: controller.signal,
            cache: 'no-cache'
        });
        clearTimeout(timeoutId);
        
        // Check if response is JSON
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            const text = await response.text();
            console.error('API returned non-JSON response:', text.substring(0, 200));
            throw new Error('API returned non-JSON response');
        }
        
        const data = await response.json();
        
        const currentUnreadCount = data.total_unread || 0;
        
        // Get all notifications and track their IDs
        const allNotifications = [];
        if (data.notifications) {
            Object.values(data.notifications).forEach(tableNotifs => {
                allNotifications.push(...tableNotifs);
            });
        }
        
        // Find new notifications that haven't been shown yet
        const currentNotificationIds = new Set();
        const newNotifications = [];
        
        allNotifications.forEach(notif => {
            const notifId = notif.notification_id || notif.id;
            if (notifId) {
                currentNotificationIds.add(notifId);
            }
            
            // Only show unread notifications that haven't been shown before
            if (!notif.is_read && notifId && !shownNotificationIds.has(notifId)) {
                newNotifications.push(notif);
                shownNotificationIds.add(notifId);
                // Save to sessionStorage immediately
                saveShownNotificationIds();
            }
        });
        
        // Clean up shownNotificationIds - remove IDs that are no longer unread (to prevent showing old notifications)
        let idsChanged = false;
        shownNotificationIds.forEach(id => {
            const stillExists = currentNotificationIds.has(id);
            if (!stillExists) {
                shownNotificationIds.delete(id);
                idsChanged = true;
            }
        });
        
        // Also remove IDs that are now read (from backend)
        allNotifications.forEach(notif => {
            const notifId = notif.notification_id || notif.id;
            if (notifId && notif.is_read) {
                shownNotificationIds.delete(notifId);
                idsChanged = true;
            }
        });
        
        // Save to sessionStorage if IDs changed
        if (idsChanged) {
            saveShownNotificationIds();
        }
        
        if (newNotifications.length > 0) {
            // Play sound for new notifications (only once)
            playNotificationSound();
            
            const toastDuration = 4000; // 4 seconds, auto-dismiss - enough time to read
            
            // Show notifications directly - one-time, short-lived
            newNotifications.forEach(notif => {
                const type = notif.type || '';
                const tableName = notif.table_name || 'Masa';
                const zoneName = notif.data?.zone_name || '';
                const notifId = notif.notification_id || notif.id;
                const tableId = notif.table_id || '';
                
                let message = '';
                if (type === 'CALL_WAITER') {
                    // Format: "Şu ... masası garson çağırdı"
                    message = zoneName ? `Şu ${zoneName} ${tableName} masası garson çağırdı` : `Şu ${tableName} masası garson çağırdı`;
                } else if (type === 'REQUEST_BILL') {
                    message = zoneName ? `Şu ${zoneName} ${tableName} masası hesap istiyor` : `Şu ${tableName} masası hesap istiyor`;
                } else if (type === 'CANCEL_ORDER') {
                    const orderShortId = notif.data?.order_short_id || notif.data?.order_id?.substring(0, 8) || '';
                    const items = notif.data?.items || '';
                    message = zoneName 
                        ? `Şu ${zoneName} ${tableName} masası sipariş #${orderShortId} iptal istiyor${items ? ': ' + items : ''}` 
                        : `Şu ${tableName} masası sipariş #${orderShortId} iptal istiyor${items ? ': ' + items : ''}`;
                } else if (type === 'ORDER_READY') {
                    message = zoneName ? `${zoneName} ${tableName} sipariş hazır` : `${tableName} sipariş hazır`;
                } else if (type === 'NEW_ORDER') {
                    message = zoneName ? `${zoneName} ${tableName} yeni sipariş` : `${tableName} yeni sipariş`;
                } else {
                    message = zoneName ? `${zoneName} ${tableName} bildirim` : `${tableName} bildirim`;
                }
                
                const toastType = type === 'ORDER_READY' ? 'success' : 'info';
                if (window.Toast && typeof window.Toast[toastType] === 'function') {
                    window.Toast[toastType](message, toastDuration);
                } else if (window.NotificationManager && window.NotificationManager.show) {
                    window.NotificationManager.show(message, toastType, toastDuration);
                } else if (window.showToast) {
                    window.showToast(message, toastType);
                } else {
                    console.log(message);
                }
                
                // Auto-mark notification as read after showing toast
                if (notifId) {
                    setTimeout(() => {
                        markNotificationAsRead(notifId, tableId);
                    }, toastDuration + 500);
                }
            });
        }
        
        notificationsData = data;

        const summaryKey = getNotificationSummaryKey(currentUnreadCount, data.table_counts || {});
        const hasSummaryChanged = summaryKey !== lastNotificationSummaryKey;
        lastNotificationSummaryKey = summaryKey;

        if (hasSummaryChanged) {
            // Update notification count
            const countElement = document.getElementById('notification-count');
            if (countElement) {
                countElement.textContent = `${currentUnreadCount} ${waiterTranslations.okunmamış || waiterTranslations.unread || 'okunmamış'}`;
            }

            const headerBadge = document.getElementById('header-notification-badge');
            if (headerBadge) {
                if (currentUnreadCount > 0) {
                    headerBadge.textContent = currentUnreadCount > 99 ? '99+' : String(currentUnreadCount);
                    headerBadge.classList.remove('hidden');
                } else {
                    headerBadge.classList.add('hidden');
                }
            }

            // Re-render tables to update notification badges
            renderTables();
        }
    } catch (error) {
        if (error.name !== 'AbortError') {
            console.error('Error loading notifications:', error);
        }
    } finally {
        // Always reset loading state
        isNotificationLoading = false;
    }
}

// Mark notification as read
async function markNotificationAsRead(notificationId, tableId = null) {
    try {
        const csrfToken = window.CSRF_TOKEN || (typeof csrf_token !== 'undefined' ? csrf_token : '');
        const response = await fetch(`${baseUrl}/api/waiter/notifications/mark-read`, {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify({ 
                notification_id: notificationId,
                table_id: tableId
            })
        });
        
        const data = await response.json();
        if (data.success) {
            // Remove from pending notifications if exists
            if (window.pendingNotifications && window.pendingNotifications.has(notificationId)) {
                window.pendingNotifications.delete(notificationId);
            }
            
            // Enable table button if no more pending notifications for this table
            if (tableId && !hasPendingNotifications(tableId)) {
                enableTableButton(tableId);
            }
            
            return true;
        }
        return false;
    } catch (error) {
        console.error('Error marking notification as read:', error);
        return false;
    }
}

// Play notification sound (similar to kitchen dashboard)
function playNotificationSound() {
    try {
        const audioContext = new (window.AudioContext || window.webkitAudioContext)();
        const oscillator = audioContext.createOscillator();
        const gainNode = audioContext.createGain();
        
        oscillator.connect(gainNode);
        gainNode.connect(audioContext.destination);
        
        oscillator.frequency.value = 600; // Different frequency for waiter
        oscillator.type = 'sine';
        
        gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
        gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.5);
        
        oscillator.start(audioContext.currentTime);
        oscillator.stop(audioContext.currentTime + 0.5);
    } catch (e) {
        console.error('Sound error:', e);
    }
}

// Check if table has pending notifications that need approval
function hasPendingNotifications(tableId) {
    if (!window.pendingNotifications || !tableId) return false;
    
    for (const [notifId, notifData] of window.pendingNotifications.entries()) {
        if (notifData.tableId === tableId) {
            return true;
        }
    }
    return false;
}

// Disable table button when notification is pending
function disableTableButton(tableId) {
    if (!tableId) return;
    
    const buttons = document.querySelectorAll(`button[onclick*="openTableMenu('${tableId}')"], button[onclick*="showTableDetails('${tableId}')"]`);
    buttons.forEach(button => {
        button.disabled = true;
        button.style.opacity = '0.5';
        button.style.cursor = 'not-allowed';
        button.title = 'Bildirimi onaylamanız gerekiyor';
    });
}

// Enable table button after notification is approved
function enableTableButton(tableId) {
    if (!tableId) return;
    
    const buttons = document.querySelectorAll(`button[onclick*="openTableMenu('${tableId}')"], button[onclick*="showTableDetails('${tableId}')"]`);
    buttons.forEach(button => {
        button.disabled = false;
        button.style.opacity = '1';
        button.style.cursor = 'pointer';
        button.title = '';
    });
}

// Approve notification - called when user clicks "Bildirimi Onayla" button
window.approveNotification = async function(notificationId, tableId, buttonElement) {
    if (!notificationId || !tableId) return;
    
    try {
        // Mark notification as read
        const result = await markNotificationAsRead(notificationId, tableId);
        
        if (!result) {
            console.error('Failed to mark notification as read');
            return;
        }
        
        // Remove notification element
        if (window.pendingNotifications && window.pendingNotifications.has(notificationId)) {
            const notifData = window.pendingNotifications.get(notificationId);
            if (notifData && notifData.element) {
                // Try to find the notification element in the DOM if it's not found
                let notificationElement = notifData.element;
                
                // If element is not in DOM, try to find it by notification ID
                if (!document.body.contains(notificationElement)) {
                    // Try to find notification by data attribute or other means
                    const allNotifications = document.querySelectorAll('.notification-item');
                    for (const notif of allNotifications) {
                        const approveBtn = notif.querySelector('.approve-notification-btn');
                        if (approveBtn && approveBtn.getAttribute('data-notification-id') === notificationId) {
                            notificationElement = notif;
                            break;
                        }
                    }
                }
                
                // Remove notification using NotificationManager
                if (window.NotificationManager && window.NotificationManager.remove) {
                    window.NotificationManager.remove(notificationElement);
                } else if (notificationElement && notificationElement.parentNode) {
                    // Fallback: remove directly if NotificationManager is not available
                    notificationElement.classList.add('notification-exit');
                    setTimeout(() => {
                        if (notificationElement.parentNode) {
                            notificationElement.parentNode.removeChild(notificationElement);
                        }
                    }, 300);
                }
            }
            window.pendingNotifications.delete(notificationId);
        }
        
        // Enable table button
        enableTableButton(tableId);
        
        // Reload notifications to update badge counts and remove from list
        loadNotifications();
    } catch (error) {
        console.error('Error approving notification:', error);
    }
};

// Open table menu (redirect to waiter POS page)
function openTableMenu(tableId) {
    if (!tableId) return;
    
    // Validate tableId to prevent injection
    if (!/^[a-zA-Z0-9_-]+$/.test(tableId)) {
        console.error('Invalid table ID');
        return;
    }
    
    // Check if there are pending notifications for this table
    if (hasPendingNotifications && hasPendingNotifications(tableId)) {
        // Find pending notification for this table
        let pendingNotifId = null;
        if (window.pendingNotifications) {
            for (const [notifId, notifData] of window.pendingNotifications.entries()) {
                if (notifData.tableId === tableId) {
                    pendingNotifId = notifId;
                    break;
                }
            }
        }
        
        // Auto-approve notification when user clicks table (fallback behavior)
        if (pendingNotifId && window.approveNotification) {
            window.approveNotification(pendingNotifId, tableId, null).then(() => {
                // Redirect after approval
                const cleanUrl = `${baseUrl}/waiter/pos?table=${encodeURIComponent(tableId)}`;
                window.location.href = cleanUrl;
            }).catch(() => {
                // Redirect even if approval fails
                const cleanUrl = `${baseUrl}/waiter/pos?table=${encodeURIComponent(tableId)}`;
                window.location.href = cleanUrl;
            });
            return;
        }
    }
    
    // Redirect to waiter POS page (waiter-specific POS, not cashier POS)
    // Using clean URL format with encoded parameter
    const cleanUrl = `${baseUrl}/waiter/pos?table=${encodeURIComponent(tableId)}`;
    window.location.href = cleanUrl;
}

// Show table details - redirects to POS page instead of opening modal
function showTableDetails(tableId) {
    if (!tableId) return;
    
    // Validate tableId to prevent injection
    if (!/^[a-zA-Z0-9_-]+$/.test(tableId)) {
        console.error('Invalid table ID');
        return;
    }
    
    // Redirect directly to waiter POS page for better performance
    const cleanUrl = `${baseUrl}/waiter/pos?table=${encodeURIComponent(tableId)}`;
    window.location.href = cleanUrl;
}

// Close table modal
function closeTableModal() {
    const modal = document.getElementById('table-details-modal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

// Mark table notifications as read
async function markTableNotificationsRead(tableId) {
    try {
        const csrfToken = window.CSRF_TOKEN || (typeof csrf_token !== 'undefined' ? csrf_token : '');
        const response = await fetch(`${baseUrl}/api/waiter/notifications/mark-read`, {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify({ table_id: tableId })
        });
        
        const data = await response.json();
        if (data.success) {
            loadNotifications();
            loadTables(); // Refresh tables list
        }
    } catch (error) {
        console.error('Error marking notifications as read:', error);
    }
}

// Show add item modal for empty table
window.showAddItemModal = async function(tableId) {
    // Validate tableId to prevent injection
    if (!tableId || !/^[a-zA-Z0-9_-]+$/.test(tableId)) {
        console.error('Invalid table ID');
        return;
    }
    // Redirect to waiter POS page (waiter-specific POS, not cashier POS)
    // Using clean URL format with encoded parameter
    const cleanUrl = `${baseUrl}/waiter/pos?table=${encodeURIComponent(tableId)}`;
    window.location.href = cleanUrl;
};

// Transfer table to cashier
window.transferTableToCashier = async function(tableId) {
    let transferConfirmed = false;
    if (window.NotificationManager && window.NotificationManager.confirm) {
        transferConfirmed = await window.NotificationManager.confirm('Masayı kasiyere devretmek istediğinizden emin misiniz?\n\nMasa kasiyere devredilecek ve ödeme için kasada görünecektir.\nGarson panelinde masa boş olarak görünecektir.', 'Kasiyere Devret');
    } else {
        transferConfirmed = confirm('Masayı kasiyere devretmek istediğinizden emin misiniz?\n\nMasa kasiyere devredilecek ve ödeme için kasada görünecektir.\nGarson panelinde masa boş olarak görünecektir.');
    }
    if (!transferConfirmed) {
        return;
    }
    
    try {
        const csrfToken = window.CSRF_TOKEN || (typeof csrf_token !== 'undefined' ? csrf_token : '');
        // Update table status to PAYMENT_PENDING using waiter-specific endpoint
        const response = await fetch(`${baseUrl}/api/waiter/transfer-to-cashier`, {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify({ 
                table_id: tableId
            })
        });
        
        const data = await response.json();
        if (data.success) {
            if (window.showToast) {
                window.showToast('✅ Masa kasaya devredildi. Ödeme kasiyer tarafından alınacak.', 'success');
            }
            // DON'T close modal - keep it open for waiter to see
            // closeTableModal(); // REMOVED - Modal stays open
            
            // Reload tables to show updated status (table will appear as FREE for waiter)
            loadTables();
        } else {
            if (window.showToast) {
                window.showToast(data.error || 'Hata oluştu', 'error');
            }
        }
    } catch (error) {
        console.error('Error transferring table to cashier:', error);
        if (window.showToast) {
            window.showToast('Hata oluştu', 'error');
        }
    }
};

// Process payment (redirect to POS payment page)
window.processPayment = function(tableId) {
    // Validate tableId to prevent injection
    if (!tableId || !/^[a-zA-Z0-9_-]+$/.test(tableId)) {
        console.error('Invalid table ID');
        return;
    }
    // Using clean URL format with encoded parameter
    const cleanUrl = `${baseUrl}/pos/dashboard?table=${encodeURIComponent(tableId)}`;
    window.location.href = cleanUrl;
};

// Show move table modal
let currentMoveTableId = null;
function showMoveTableModal(tableId) {
    currentMoveTableId = tableId;
    const modal = document.getElementById('move-table-modal');
    const fromInfo = document.getElementById('move-table-from-info');
    
    // Get current table info
    const currentTable = Object.values(tablesData.zones || {}).flatMap(zone => zone.tables || [])
        .find(table => table.table_id === tableId);
    
    if (currentTable) {
        const zoneName = currentTable.zone_name || Object.keys(tablesData.zones || {}).find(zone => 
            (tablesData.zones[zone]?.tables || []).some(t => t.table_id === tableId)
        ) || '';
        fromInfo.textContent = `${zoneName ? zoneName + ' - ' : ''}${currentTable.name} masasından taşınacak`;
    }
    
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    
    loadTablesForMove();
}

// Close move table modal
function closeMoveTableModal() {
    const modal = document.getElementById('move-table-modal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    currentMoveTableId = null;
    document.getElementById('table-search-move').value = '';
}

// Load tables for move selection
function loadTablesForMove() {
    const container = document.getElementById('tables-list-move');
    if (!container) return;
    
    container.innerHTML = '<div class="col-span-full text-center py-10 text-slate-400">Yükleniyor...</div>';
    
    const zones = tablesData.zones || {};
    const searchTerm = (document.getElementById('table-search-move')?.value || '').toLowerCase();
    
    let html = '';
    let hasTables = false;
    
    Object.keys(zones).forEach(zoneName => {
        const zone = zones[zoneName];
        const tables = zone.tables || [];
        
        const filteredTables = tables.filter(table => {
            if (table.table_id === currentMoveTableId) return false; // Don't show current table
            const name = (table.name || '').toLowerCase();
            return name.includes(searchTerm);
        });
        
        if (filteredTables.length === 0) return;
        
        hasTables = true;
        
        filteredTables.forEach(table => {
            const tableId = table.table_id || '';
            const tableName = table.name || '';
            const status = table.status || 'FREE';
            const isFree = status === 'FREE';
            
            html += `
                <button onclick="selectTableForMove('${tableId}', '${tableName.replace(/'/g, "\\'")}')" 
                        class="move-table-btn p-3 sm:p-4 rounded-xl border-2 transition-all touch-manipulation ${
                            isFree 
                                ? 'bg-white border-emerald-200 hover:border-emerald-400 hover:shadow-md shadow-sm' 
                                : 'bg-slate-50 border-slate-200 opacity-60 cursor-not-allowed shadow-sm'
                        }"
                        data-table-id="${tableId}"
                        data-table-name="${tableName.replace(/"/g, '&quot;')}">
                    <div class="text-center">
                        <div class="font-black text-base sm:text-lg text-slate-900 mb-1">${tableName}</div>
                        <div class="text-xs text-slate-600">${zoneName}</div>
                        <div class="text-xs mt-1 ${isFree ? 'text-emerald-600 font-bold' : 'text-amber-700 font-bold'}">
                            ${isFree ? 'Boş' : 'Dolu'}
                        </div>
                    </div>
                </button>
            `;
        });
    });
    
    if (!hasTables) {
        html = '<div class="col-span-full text-center py-10 text-slate-400">Masa bulunamadı</div>';
    }
    
    container.innerHTML = html;
}

// Select table for move
let isMovingTable = false;

async function selectTableForMove(toTableId, toTableName) {
    // Prevent multiple simultaneous requests
    if (isMovingTable) {
        console.log('Table move already in progress, ignoring click');
        return;
    }
    
    if (!currentMoveTableId) {
        if (window.showToast) {
            window.showToast('Hata: Kaynak masa bulunamadı', 'error');
        }
        return;
    }
    
    if (currentMoveTableId === toTableId) {
        if (window.showToast) {
            window.showToast('Aynı masayı seçemezsiniz', 'error');
        }
        return;
    }
    
    // Show confirmation dialog BEFORE setting the lock
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
        const csrfToken = window.CSRF_TOKEN || (typeof csrf_token !== 'undefined' ? csrf_token : '');
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
        
        const data = await response.json();
        
        if (data.success) {
            if (window.showToast) {
                window.showToast('Masa başarıyla taşındı', 'success');
            }
            closeMoveTableModal();
            closeTableModal();
            loadTables();
        } else {
            if (window.showToast) {
                window.showToast(data.error || 'Masa taşınamadı', 'error');
            }
        }
    } catch (error) {
        console.error('Error moving table:', error);
        if (window.showToast) {
            window.showToast('Hata oluştu', 'error');
        }
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

// Clear table (set status to FREE)
async function clearTable(tableId) {
    let clearConfirmed = false;
    if (window.NotificationManager && window.NotificationManager.confirm) {
        clearConfirmed = await window.NotificationManager.confirm('Masayı boşaltmak istediğinizden emin misiniz? Bu işlem geri alınamaz.', 'Masayı Boşalt');
    } else {
        clearConfirmed = confirm('Masayı boşaltmak istediğinizden emin misiniz? Bu işlem geri alınamaz.');
    }
    if (!clearConfirmed) {
        return;
    }
    
    try {
        const csrfToken = window.CSRF_TOKEN || (typeof csrf_token !== 'undefined' ? csrf_token : '');
        // Update table status to FREE
        const response = await fetch(`${baseUrl}/api/admin/update-table`, {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify({ 
                table_id: tableId,
                status: 'FREE'
            })
        });
        
        const data = await response.json();
        if (data.success) {
            if (window.showToast) {
                window.showToast('Masa boşaltıldı', 'success');
            }
            closeTableModal();
            loadTables();
        } else {
            if (window.showToast) {
                window.showToast(data.error || 'Hata oluştu', 'error');
            }
        }
    } catch (error) {
        console.error('Error clearing table:', error);
        if (window.showToast) {
            window.showToast('Hata oluştu', 'error');
        }
    }
}

// Search handler
const tableSearchInput = document.getElementById('table-search');
if (tableSearchInput) {
    tableSearchInput.addEventListener('input', () => {
        renderTables();
    });
}


// Deliver order
async function deliverOrder(orderId, tableId) {
    try {
        const csrfToken = window.CSRF_TOKEN || (typeof csrf_token !== 'undefined' ? csrf_token : '');
        const response = await fetch(`${baseUrl}/api/waiter/deliver-order`, {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify({ order_id: orderId })
        });
        
        const data = await response.json();
        
        if (data.success) {
            window.NotificationManager.success('Sipariş teslim edildi');
            
            // Refresh notifications
            loadNotifications();
            
            // Refresh tables to show updated status
            await loadTables();
        } else {
            window.NotificationManager.error(data.error || 'Sipariş teslim edilemedi');
        }
    } catch (error) {
        console.error('Error delivering order:', error);
        window.NotificationManager.error('Hata oluştu');
    }
}

// Initialize
// Sıra nudge: bekleyen misafir varsa garsonu bilgilendir (12 sn'de bir)
async function qdRefreshQueueNudge() {
    try {
        const r = await fetch(`${baseUrl}/api/business/queue/list`, { headers:{ 'Accept':'application/json' }, credentials:'same-origin' });
        if (!r.ok) return;
        const j = await r.json();
        if (!j || !j.success) return;
        const waiting = (j.active || []).filter(e => e.status === 'WAITING');
        const el = document.getElementById('qd-queue-nudge');
        const n  = document.getElementById('qd-queue-nudge-n');
        if (!el || !n) return;
        if (waiting.length > 0) {
            n.textContent = String(waiting.length);
            el.classList.remove('hidden');
            el.classList.add('flex');
        } else {
            el.classList.add('hidden');
            el.classList.remove('flex');
        }
    } catch(e) { /* sessiz */ }
}

document.addEventListener('DOMContentLoaded', function() {
    loadTables();
    loadNotifications();
    qdRefreshQueueNudge();
    setInterval(() => { if (typeof isPageActive === 'function' && !isPageActive()) return; qdRefreshQueueNudge(); }, 12000);
    
    // PERFORMANCE OPTIMIZED: Separate intervals for different data types with debouncing
    // Notifications: Optimized polling with debouncing (5 seconds) for better performance
    let lastNotificationCheck = 0;
    const notificationInterval = setInterval(() => {
        if (!isPageActive()) {
            return;
        }
        // Prevent overlapping requests
        if (isNotificationLoading) {
            return;
        }
        // Debounce: Only check if at least 5 seconds passed since last check
        const now = Date.now();
        if (now - lastNotificationCheck < 5000) {
            return;
        }
        lastNotificationCheck = now;
        loadNotifications();
    }, 5000); // 5 saniye - PERFORMANCE: Increased from 3s to reduce server load
    
    // Tables: Optimized polling (8 seconds) for status updates
    // PERFORMANCE: Increased from 5s to reduce database queries
    let lastTablesCheck = 0;
    const tablesInterval = setInterval(() => {
        if (!isPageActive()) {
            return;
        }
        // Prevent overlapping requests
        if (isTablesLoading) {
            return;
        }
        // Debounce: Only check if at least 8 seconds passed since last check
        const now = Date.now();
        if (now - lastTablesCheck < 8000) {
            return;
        }
        lastTablesCheck = now;
        loadTables();
    }, 8000); // 8 saniye - PERFORMANCE: Increased from 5s, now uses batch queries
    
    // Ready orders: Slow polling (20 seconds) - less critical
    // PERFORMANCE: Increased from 15s to reduce server load
    const readyOrdersInterval = setInterval(() => {
        if (!isPageActive()) {
            return;
        }
        batchCheckReadyOrders();
    }, 20000); // 20 saniye - PERFORMANCE: Increased from 15s
    
    // Store intervals for cleanup
    window.waiterDashboardIntervals = {
        notifications: notificationInterval,
        tables: tablesInterval,
        readyOrders: readyOrdersInterval
    };

    let lastVisibilityRefresh = 0;
    document.addEventListener('visibilitychange', () => {
        if (!isPageActive()) {
            return;
        }
        const now = Date.now();
        if (now - lastVisibilityRefresh < 3000) {
            return;
        }
        lastVisibilityRefresh = now;
        loadTables();
        loadNotifications();
    });
});

// Delete order item (direct - no confirmation needed)
async function deleteOrderItem(orderItemId, tableId) {
    try {
        const csrfToken = window.CSRF_TOKEN || (typeof csrf_token !== 'undefined' ? csrf_token : '');
        const response = await fetch(`${baseUrl}/api/waiter/delete-order-item`, {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify({ 
                order_item_id: orderItemId,
                table_id: tableId
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            if (window.showToast) {
                window.showToast(data.approval_pending ? 'Silme talebi onay kuyruğuna gönderildi' : 'Ürün silindi', 'success');
            }
            // Refresh table details
            if (tableId) {
                setTimeout(() => {
                    showTableDetails(tableId);
                }, 300);
            }
        } else {
            if (window.showToast) {
                window.showToast(data.error || 'İşlem başarısız', 'error');
            }
        }
    } catch (error) {
        console.error('Error deleting order item:', error);
        if (window.showToast) {
            window.showToast('Hata oluştu', 'error');
        }
    }
}

// Reduce order item quantity - quantity>2 ise prompt ile hedef miktar, 2 ise direkt 1'e düşür
async function reduceOrderItemQuantity(orderItemId, currentQuantity, tableId) {
    if (currentQuantity <= 1) {
        deleteOrderItem(orderItemId, tableId);
        return;
    }
    
    let newQty;
    if (currentQuantity === 2) {
        newQty = 1;
    } else {
        const val = await (window.NotificationManager && window.NotificationManager.prompt
            ? window.NotificationManager.prompt('Kaç adete düşürmek istiyorsunuz?', 'Yeni adet (1-' + currentQuantity + '):', (currentQuantity - 1).toString())
            : Promise.resolve(prompt('Kaç adete düşürmek istiyorsunuz? (1-' + currentQuantity + ')', currentQuantity - 1)));
        if (val === null || val === undefined || val === '') return;
        const parsed = parseInt(val, 10);
        if (isNaN(parsed) || parsed < 1 || parsed >= currentQuantity) {
            if (window.showToast) window.showToast('Geçerli bir adet girin (1-' + (currentQuantity - 1) + ')', 'error');
            return;
        }
        newQty = parsed;
    }
    
    try {
        const csrfToken = window.CSRF_TOKEN || (typeof csrf_token !== 'undefined' ? csrf_token : '');
        const response = await fetch(`${baseUrl}/api/waiter/reduce-order-item-quantity`, {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify({ 
                order_item_id: orderItemId,
                new_quantity: newQty,
                table_id: tableId
            })
        });
        
        if (!response.ok) {
            const errorText = await response.text();
            console.error('Reduce quantity HTTP error:', response.status, errorText);
            if (window.showToast) {
                window.showToast('Sunucu hatası: ' + response.status, 'error');
            }
            return;
        }
        
        const data = await response.json();
        
        if (data.success) {
            if (window.showToast) {
                window.showToast(data.approval_pending ? 'Azaltma talebi onay kuyruğuna gönderildi' : 'Miktar güncellendi', 'success');
            }
            if (tableId) {
                setTimeout(() => {
                    loadTables();
                }, 300);
            }
        } else {
            if (window.showToast) {
                window.showToast(data.error || 'İşlem başarısız', 'error');
            }
        }
    } catch (error) {
        console.error('Error reducing order item quantity:', error);
        if (window.showToast) {
            window.showToast('Hata oluştu: ' + (error.message || 'Bilinmeyen hata'), 'error');
        }
    }
}

// Delete all orders for a table (direct - no confirmation needed)
async function deleteAllTableOrders(tableId) {
    if (!tableId) return;
    
    try {
        const csrfToken = window.CSRF_TOKEN || (typeof csrf_token !== 'undefined' ? csrf_token : '');
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
        
        const data = await response.json();
        
        if (data.success) {
            if (window.showToast) {
                window.showToast(data.message || 'Tüm siparişler silindi', 'success');
            }
            setTimeout(() => {
                loadTables();
            }, 300);
        } else {
            if (window.showToast) {
                window.showToast(data.error || 'İşlem başarısız', 'error');
            }
        }
    } catch (error) {
        console.error('Error deleting all orders:', error);
        if (window.showToast) {
            window.showToast('Hata oluştu', 'error');
        }
    }
}

// Cleanup ALL intervals on page unload to prevent memory leaks
window.addEventListener('beforeunload', () => {
    if (typeof refreshInterval !== 'undefined' && refreshInterval) clearInterval(refreshInterval);
    if (typeof notificationInterval !== 'undefined' && notificationInterval) clearInterval(notificationInterval);
    if (typeof tablesInterval !== 'undefined' && tablesInterval) clearInterval(tablesInterval);
    if (typeof readyOrdersInterval !== 'undefined' && readyOrdersInterval) clearInterval(readyOrdersInterval);
});
</script>


<script>
<?php
$bid = \App\Core\TenantResolver::resolve();
if (empty($bid) && class_exists('\\App\\Core\\TenantContext')) {
    $bid = \App\Core\TenantContext::getId();
}
?>
if (!window.BASE_URL) window.BASE_URL = '<?php echo BASE_URL; ?>';
if (!window.BUSINESS_ID) window.BUSINESS_ID = <?php echo json_encode((string)($bid ?? '')); ?>;
if (!window.WEBSOCKET_URL) window.WEBSOCKET_URL = (location.protocol === 'https:' ? 'wss://' : 'ws://') + location.host + '/ws';
</script>
<script src="<?php echo BASE_URL; ?>/assets/js/realtime.js"></script>
<?php toast_scripts(); ?>
<?php display_queued_toasts(); ?>

<script>
// Waiter realtime: RealtimeService (WS + polling fallback) üzerinden
// masalar/siparişler/bildirimler güncellenir.
document.addEventListener('DOMContentLoaded', function() {
    function wireWaiterRealtime() {
        if (!window.realtimeService || typeof window.realtimeService.start !== 'function') {
            return false;
        }
        window.realtimeService.start('tables', function(payload) {
            if (typeof loadTables === 'function') {
                try { loadTables(); } catch (e) { /* sessiz */ }
            }
        }, { interval: 8000 });

        window.realtimeService.start('notifications', function(payload) {
            if (typeof loadNotifications === 'function') {
                try { loadNotifications(); } catch (e) { /* sessiz */ }
            }
            if (payload && (payload.type === 'order.ready' || payload.type === 'ORDER_READY')) {
                if (window.toast) {
                    var t = (payload.data && (payload.data.table_name || payload.data.table_id)) || '';
                    window.toast.success('Sipariş Hazır', t ? ('Masa ' + t) : '');
                }
            }
        }, { interval: 10000 });

        window.realtimeService.start('orders', function(payload) {
            if (typeof loadTables === 'function') {
                try { loadTables(); } catch (e) { /* sessiz */ }
            }
        }, { interval: 10000 });
        return true;
    }

    if (!wireWaiterRealtime()) {
        var tries = 0;
        var iv = setInterval(function() {
            if (wireWaiterRealtime() || ++tries > 20) { clearInterval(iv); }
        }, 250);
    }
});
</script>


<?php if ($isSuperAdmin): ?>
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
            // Business ID in URL - load business info directly from API and show waiter view
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
                        
                        // Show waiter management view
                        document.getElementById('business-selection-view').classList.add('hidden');
                        document.getElementById('waiter-management-view').classList.remove('hidden');
                        const waiterDashboard = document.getElementById('waiter-dashboard');
                        if (waiterDashboard) {
                            waiterDashboard.style.display = 'flex';
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
                    
                    // Show waiter management view
                    document.getElementById('business-selection-view').classList.add('hidden');
                    document.getElementById('waiter-management-view').classList.remove('hidden');
                    const waiterDashboard = document.getElementById('waiter-dashboard');
                    if (waiterDashboard) {
                        waiterDashboard.style.display = 'flex';
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
    BusinessSelector.showSelectionView('business-selection-view', 'waiter-management-view');
    const waiterDashboard = document.getElementById('waiter-dashboard');
    if (waiterDashboard) {
        waiterDashboard.style.display = 'none';
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

<script>
// Notifications Modal Functions - OPTIMIZED
function showNotificationsModal() {
    const modal = document.getElementById('notifications-modal');
    if (modal) {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        
        // OPTIMIZED: Show cached data immediately if available
        const contentEl = document.getElementById('notifications-modal-content');
        const now = Date.now();
        if (notificationsModalCache.data && (now - notificationsModalCache.timestamp) < notificationsModalCache.TTL) {
            // Render cached data immediately
            renderNotificationsToModal(notificationsModalCache.data, contentEl);
        } else {
            // Show loading only if no cache
            if (contentEl && !notificationsModalCache.data) {
                contentEl.innerHTML = '<div class="text-center py-10"><div class="inline-block animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-indigo-500"></div><p class="mt-4 text-slate-600 font-bold">Yükleniyor...</p></div>';
            }
        }
        
        // Load fresh data in background (non-blocking)
        loadNotificationsForModal(true);
    }
}

function closeNotificationsModal() {
    const modal = document.getElementById('notifications-modal');
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }
}

// Cache for notifications modal - OPTIMIZED
let notificationsModalCache = {
    data: null,
    timestamp: 0,
    TTL: 30000 // 30 seconds cache (increased from 5 seconds)
};

// Load notifications for modal display - OPTIMIZED with better caching
async function loadNotificationsForModal(forceRefresh = false) {
    const contentEl = document.getElementById('notifications-modal-content');
    if (!contentEl) return;
    
    // Check cache first (unless force refresh)
    const now = Date.now();
    if (!forceRefresh && notificationsModalCache.data && (now - notificationsModalCache.timestamp) < notificationsModalCache.TTL) {
        renderNotificationsToModal(notificationsModalCache.data, contentEl);
        return;
    }
    
    // Don't show loading if we have cached data (user already sees content)
    if (!notificationsModalCache.data) {
        contentEl.innerHTML = '<div class="text-center py-10"><div class="inline-block animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-indigo-500"></div><p class="mt-4 text-slate-600 font-bold">Yükleniyor...</p></div>';
    }
    
    try {
        // Use AbortController for request cancellation if needed
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 10000); // 10 second timeout
        
        const response = await fetch(`${baseUrl}/api/waiter/table-notifications`, {
            signal: controller.signal,
            cache: 'no-cache' // Always fetch fresh data from server
        });
        
        clearTimeout(timeoutId);
        
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            const text = await response.text();
            console.error('API returned non-JSON response:', text.substring(0, 200));
            throw new Error('API returned non-JSON response');
        }
        
        const data = await response.json();
        
        // Update cache
        notificationsModalCache.data = data;
        notificationsModalCache.timestamp = now;
        
        // Render notifications (only update if modal is still open)
        const modal = document.getElementById('notifications-modal');
        if (modal && !modal.classList.contains('hidden')) {
            renderNotificationsToModal(data, contentEl);
        }
        
    } catch (error) {
        if (error.name === 'AbortError') {
            console.warn('Notification fetch timeout');
            // Don't show error if we have cached data
            if (notificationsModalCache.data) {
                return;
            }
        }
        console.error('Error loading notifications for modal:', error);
        // Only show error if no cached data
        if (!notificationsModalCache.data && contentEl) {
            contentEl.innerHTML = `
                <div class="text-center py-12">
                    <p class="text-red-600 font-bold text-base">${waiterTranslations.error_occurred || 'Hata oluştu'}</p>
                </div>
            `;
        }
    }
}

// Render notifications to modal - OPTIMIZED with DocumentFragment
function renderNotificationsToModal(data, contentEl) {
    // Get all notifications flattened
    const allNotifications = [];
    if (data.notifications) {
        Object.values(data.notifications).forEach(tableNotifs => {
            allNotifications.push(...tableNotifs);
        });
    }
    
    // Sort by created_at (newest first)
    allNotifications.sort((a, b) => {
        const timeA = a.created_at ? (is_numeric(a.created_at) ? parseInt(a.created_at) : new Date(a.created_at).getTime()) : 0;
        const timeB = b.created_at ? (is_numeric(b.created_at) ? parseInt(b.created_at) : new Date(b.created_at).getTime()) : 0;
        return timeB - timeA;
    });
    
    // Update subtitle
    const subtitleEl = document.getElementById('notifications-modal-subtitle');
    if (subtitleEl) {
        const unreadCount = data.total_unread || 0;
        subtitleEl.textContent = `${unreadCount} ${waiterTranslations.okunmamış || 'okunmamış'}`;
    }
    
    // Use DocumentFragment for better performance
    const fragment = document.createDocumentFragment();
    
    if (allNotifications.length === 0) {
        const emptyDiv = document.createElement('div');
        emptyDiv.className = 'text-center py-12';
        emptyDiv.innerHTML = `
            <div class="inline-block w-16 h-16 bg-slate-100 rounded-full flex items-center justify-center mb-4">
                <?php echo icon_bell(['class' => 'w-8 h-8 text-slate-400']); ?>
            </div>
            <p class="text-slate-600 font-bold text-base">${waiterTranslations.no_notifications || 'Bildirim yok'}</p>
        `;
        fragment.appendChild(emptyDiv);
    } else {
        // OPTIMIZED: Limit to first 50 notifications initially for better performance
        // Can be extended with pagination if needed
        const notificationsToShow = allNotifications.slice(0, 50);
        
        notificationsToShow.forEach(notif => {
            const notifId = notif.notification_id || notif.id;
            const tableId = notif.table_id || '';
            const tableName = notif.table_name || 'Masa';
            const zoneName = notif.data?.zone_name || '';
            const type = notif.type || '';
            const isRead = notif.is_read || false;
            
            // Format message based on type
            let message = '';
            if (type === 'CALL_WAITER') {
                message = zoneName ? `${zoneName} ${tableName} masası garson çağırdı` : `${tableName} masası garson çağırdı`;
            } else if (type === 'REQUEST_BILL') {
                message = zoneName ? `${zoneName} ${tableName} masası hesap istiyor` : `${tableName} masası hesap istiyor`;
            } else if (type === 'CANCEL_ORDER') {
                const orderShortId = notif.data?.order_short_id || notif.data?.order_id?.substring(0, 8) || '';
                const items = notif.data?.items || '';
                message = zoneName 
                    ? `${zoneName} ${tableName} masası sipariş #${orderShortId} iptal istiyor${items ? ': ' + items : ''}` 
                    : `${tableName} masası sipariş #${orderShortId} iptal istiyor${items ? ': ' + items : ''}`;
            } else if (type === 'ORDER_READY') {
                message = zoneName ? `${zoneName} ${tableName} sipariş hazır` : `${tableName} sipariş hazır`;
            } else if (type === 'NEW_ORDER') {
                message = zoneName ? `${zoneName} ${tableName} yeni sipariş` : `${tableName} yeni sipariş`;
            } else {
                message = zoneName ? `${zoneName} ${tableName} bildirim` : `${tableName} bildirim`;
            }
            
            // Format time
            let timeText = '-';
            const createdAt = notif.created_at;
            if (createdAt) {
                const timestamp = is_numeric(createdAt) ? parseInt(createdAt) : new Date(createdAt).getTime() / 1000;
                if (timestamp && timestamp > 0) {
                    const now = Math.floor(Date.now() / 1000);
                    const diff = now - timestamp;
                    
                    if (diff < 0) {
                        timeText = new Date(timestamp * 1000).toLocaleDateString('tr-TR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
                    } else if (diff < 60) {
                        timeText = 'Az önce';
                    } else if (diff < 3600) {
                        const mins = Math.floor(diff / 60);
                        timeText = `${mins} dk önce`;
                    } else if (diff < 86400) {
                        const hours = Math.floor(diff / 3600);
                        timeText = `${hours} saat önce`;
                    } else if (diff < 604800) {
                        const days = Math.floor(diff / 86400);
                        timeText = `${days} gün önce`;
                    } else {
                        timeText = new Date(timestamp * 1000).toLocaleDateString('tr-TR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
                    }
                }
            }
            
            // Create notification element
            const notifDiv = document.createElement('div');
            notifDiv.className = `p-3 sm:p-4 rounded-lg sm:rounded-xl flex justify-between items-center transition-all cursor-pointer touch-manipulation mb-2 ${isRead ? 'opacity-50 bg-slate-50' : 'bg-orange-50 border border-orange-100 hover:bg-orange-100'}`;
            
            if (!isRead && notifId) {
                notifDiv.onclick = () => markSingleNotificationRead(notifId, tableId);
            }
            
            notifDiv.innerHTML = `
                <div class="flex gap-2 sm:gap-3 items-center overflow-hidden min-w-0 flex-1">
                    <div class="w-8 h-8 sm:w-10 sm:h-10 rounded-lg sm:rounded-xl flex items-center justify-center shrink-0 ${isRead ? 'bg-slate-200' : 'bg-orange-200'}">
                        <?php echo icon_bell(['class' => 'w-4 h-4 sm:w-5 sm:h-5 text-orange-600']); ?>
                    </div>
                    <div class="overflow-hidden min-w-0 flex-1">
                        <div class="font-black text-slate-900 text-sm sm:text-base truncate">${zoneName ? `${zoneName} - ${tableName}` : tableName}</div>
                        <div class="text-xs sm:text-sm font-bold text-slate-600 truncate">${message}</div>
                    </div>
                </div>
                <div class="text-xs font-black text-slate-400 ml-2 shrink-0">${timeText}</div>
            `;
            
            fragment.appendChild(notifDiv);
        });
        
        // Show message if there are more notifications
        if (allNotifications.length > 50) {
            const moreDiv = document.createElement('div');
            moreDiv.className = 'text-center py-4 text-slate-500 text-sm font-bold';
            moreDiv.textContent = `+${allNotifications.length - 50} bildirim daha`;
            fragment.appendChild(moreDiv);
        }
    }
    
    // Clear and append fragment (single DOM operation)
    contentEl.innerHTML = '';
    contentEl.appendChild(fragment);
}

// Mark single notification as read - OPTIMIZED
async function markSingleNotificationRead(notificationId, tableId) {
    if (!notificationId) return;
    
    try {
        await markNotificationAsRead(notificationId, tableId);
        
        // Invalidate cache
        notificationsModalCache.data = null;
        notificationsModalCache.timestamp = 0;
        
        // Update UI immediately without full reload
        const contentEl = document.getElementById('notifications-modal-content');
        if (contentEl) {
            const notifElement = contentEl.querySelector(`[onclick*="${notificationId}"]`);
            if (notifElement) {
                // Update single notification element
                notifElement.classList.remove('bg-orange-50', 'border-orange-100', 'hover:bg-orange-100');
                notifElement.classList.add('opacity-50', 'bg-slate-50');
                notifElement.onclick = null;
                
                // Update icon background
                const iconBg = notifElement.querySelector('.bg-orange-200');
                if (iconBg) {
                    iconBg.classList.remove('bg-orange-200');
                    iconBg.classList.add('bg-slate-200');
                }
            }
        }
        
        // Update subtitle
        const subtitleEl = document.getElementById('notifications-modal-subtitle');
        if (subtitleEl) {
            const currentText = subtitleEl.textContent;
            const match = currentText.match(/(\d+)/);
            if (match) {
                const currentCount = parseInt(match[1]) || 0;
                const newCount = Math.max(0, currentCount - 1);
                subtitleEl.textContent = `${newCount} ${waiterTranslations.okunmamış || 'okunmamış'}`;
            }
        }
        
        // Reload main notifications to update badge (async, don't block)
        loadNotifications();
    } catch (error) {
        console.error('Error marking notification as read:', error);
        // Fallback to full reload on error
        loadNotificationsForModal(true);
    }
}

// Mark all notifications as read - OPTIMIZED
async function markAllNotificationsRead(event) {
    try {
        // Disable button to prevent double-click
        const button = (event && event.target) || document.querySelector('button[onclick*="markAllNotificationsRead"]');
        if (button) {
            button.disabled = true;
            button.style.opacity = '0.5';
        }
        
        // Use cached data if available, otherwise fetch
        let data = notificationsModalCache.data;
        if (!data) {
            const response = await fetch(`${baseUrl}/api/waiter/table-notifications`);
            const contentType = response.headers.get('content-type');
            if (contentType && contentType.includes('application/json')) {
                data = await response.json();
            } else {
                throw new Error('Invalid response');
            }
        }
        
        // Get all unread notification IDs
        const unreadNotifications = [];
        if (data.notifications) {
            Object.values(data.notifications).forEach(tableNotifs => {
                tableNotifs.forEach(notif => {
                    if (!notif.is_read) {
                        unreadNotifications.push({
                            id: notif.notification_id || notif.id,
                            tableId: notif.table_id || ''
                        });
                    }
                });
            });
        }
        
        if (unreadNotifications.length === 0) {
            if (button) {
                button.disabled = false;
                button.style.opacity = '1';
            }
            return;
        }
        
        // Mark all as read in batches (max 10 at a time for better performance)
        const batchSize = 10;
        for (let i = 0; i < unreadNotifications.length; i += batchSize) {
            const batch = unreadNotifications.slice(i, i + batchSize);
            const promises = batch.map(notif => markNotificationAsRead(notif.id, notif.tableId));
            await Promise.all(promises);
        }
        
        // Invalidate cache
        notificationsModalCache.data = null;
        notificationsModalCache.timestamp = 0;
        
        // Update UI immediately
        const contentEl = document.getElementById('notifications-modal-content');
        if (contentEl) {
            // Update all notification elements
            const notifElements = contentEl.querySelectorAll('.bg-orange-50');
            notifElements.forEach(el => {
                el.classList.remove('bg-orange-50', 'border-orange-100', 'hover:bg-orange-100');
                el.classList.add('opacity-50', 'bg-slate-50');
                el.onclick = null;
                
                const iconBg = el.querySelector('.bg-orange-200');
                if (iconBg) {
                    iconBg.classList.remove('bg-orange-200');
                    iconBg.classList.add('bg-slate-200');
                }
            });
        }
        
        // Update subtitle
        const subtitleEl = document.getElementById('notifications-modal-subtitle');
        if (subtitleEl) {
            subtitleEl.textContent = `0 ${waiterTranslations.okunmamış || 'okunmamış'}`;
        }
        
        // Reload main notifications to update badge (async)
        loadNotifications();
        
        // Re-enable button
        if (button) {
            button.disabled = false;
            button.style.opacity = '1';
        }
        
        // Show success message
        if (window.NotificationManager && window.NotificationManager.success) {
            window.NotificationManager.success('Tüm bildirimler okundu olarak işaretlendi', 2000);
        } else if (window.showToast) {
            window.showToast('Tüm bildirimler okundu olarak işaretlendi', 'success');
        }
    } catch (error) {
        console.error('Error marking all notifications as read:', error);
        if (window.showToast) {
            window.showToast('Bildirimler işaretlenirken hata oluştu', 'error');
        }
        // Re-enable button on error
        const button = (event && event.target) || document.querySelector('button[onclick*="markAllNotificationsRead"]');
        if (button) {
            button.disabled = false;
            button.style.opacity = '1';
        }
    }
}

// Close modal when clicking outside
document.addEventListener('click', function(e) {
    const modal = document.getElementById('notifications-modal');
    if (modal && !modal.classList.contains('hidden')) {
        if (e.target === modal) {
            closeNotificationsModal();
        }
    }
});

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const modal = document.getElementById('notifications-modal');
        if (modal && !modal.classList.contains('hidden')) {
            closeNotificationsModal();
        }
    }
});

// Notify customer that waiter is coming (kept for table context menu)
async function notifyCustomerWaiterComing(tableId) {
    if (!tableId) {
        if (window.NotificationManager && window.NotificationManager.error) {
            window.NotificationManager.error('Masa bilgisi eksik');
        }
        return;
    }
    
    try {
        const csrfToken = window.CSRF_TOKEN || (typeof csrf_token !== 'undefined' ? csrf_token : '');
        const response = await fetch(`${baseUrl}/api/waiter/notify-customer-waiter-coming`, {
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
            throw new Error(`HTTP ${response.status}`);
        }
        
        const data = await response.json();
        
        if (data && data.success) {
            if (window.NotificationManager && window.NotificationManager.success) {
                window.NotificationManager.success('Müşteriye bildirim gönderildi! 🏃‍♂️', 2000);
            }
        } else {
            const errorMsg = (data && data.error) || 'Bildirim gönderilemedi';
            if (window.NotificationManager && window.NotificationManager.error) {
                window.NotificationManager.error(errorMsg);
            }
        }
    } catch (error) {
        if (window.NotificationManager && window.NotificationManager.error) {
            window.NotificationManager.error('Hata oluştu');
        }
        
        // Only log in development
        if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
            console.error('Error notifying customer:', error);
        }
    }
}

let _printReceiptInProgress = false;
async function printTableReceipt(tableId) {
    if (_printReceiptInProgress) return;
    if (!tableId) {
        if (window.NotificationManager && window.NotificationManager.error) {
            window.NotificationManager.error('Masa bilgisi eksik');
        }
        return;
    }
    
    _printReceiptInProgress = true;
    const printBtn = event && event.target ? event.target.closest('button') : null;
    if (printBtn) { printBtn.disabled = true; printBtn.classList.add('opacity-50'); }
    
    try {
        const csrfToken = window.CSRF_TOKEN || (typeof csrf_token !== 'undefined' ? csrf_token : '');
        const response = await fetch(`${baseUrl}/api/waiter/print-table-receipt`, {
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
            throw new Error(`HTTP ${response.status}`);
        }
        
        const data = await response.json();
        
        if (data && data.success) {
            if (window.NotificationManager && window.NotificationManager.success) {
                window.NotificationManager.success('Fiş yazdırmaya gönderildi!', 2000);
            }
        } else {
            const errorMsg = (data && data.error) || 'Fiş yazdırılamadı';
            if (window.NotificationManager && window.NotificationManager.error) {
                window.NotificationManager.error(errorMsg);
            }
        }
    } catch (error) {
        if (window.NotificationManager && window.NotificationManager.error) {
            window.NotificationManager.error('Bağlantı hatası');
        }
    } finally {
        setTimeout(() => {
            _printReceiptInProgress = false;
            if (printBtn) { printBtn.disabled = false; printBtn.classList.remove('opacity-50'); }
        }, 5000);
    }
}
</script>
