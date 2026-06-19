<?php
require_once __DIR__ . '/../../helpers/translations.php';
if (!function_exists('getAdminUrl')) {
    require_once __DIR__ . '/../../helpers/url_helper.php';
}

// Title is set by Controller, but we can override it
if (!isset($title)) {
    $title = t('tables.title', 'Masa Yönetimi') . ' - ' . getAppConfig()->getAppName();
}
$isSuperAdmin = $is_super_admin ?? false;
$apiPrefix = $isSuperAdmin ? '/api/qodmin' : '/api/business';
// Content is captured by Controller's renderViewContent() method
// No need for ob_start() here - Controller handles it
?>

<div class="q-page q-biz-theme q-biz-tables animate-slide-up">
  <div class="q-container q-stack">
    <?php if ($is_super_admin ?? false): ?>
    <!-- SUPER ADMIN VIEW: Business Selection First -->
    <div id="business-selection-view">
        <header class="flex flex-col sm:flex-row justify-between sm:items-end mb-5 sm:mb-6 lg:mb-8 gap-4 sm:gap-5">
            <div class="flex flex-col gap-3 sm:gap-4 min-w-0 flex-1">
                <h1 class="text-2xl sm:text-3xl lg:text-4xl xl:text-5xl font-black text-slate-900 tracking-tighter break-words">Masa Yönetimi - İşletme Seçin</h1>
                <p class="text-slate-600 font-medium">Masalarını görüntülemek istediğiniz işletmeyi seçin</p>
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
    
    <!-- Table Management View -->
    <div id="table-management-view" class="hidden">
        <header class="flex flex-col sm:flex-row justify-between sm:items-center mb-4 sm:mb-6 lg:mb-8 gap-3 sm:gap-4">
            <div class="flex items-center gap-3">
                <button onclick="backToBusinessSelection()" class="p-2 hover:bg-slate-200 rounded-lg transition-all">
                    <svg class="w-6 h-6 text-slate-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                </button>
                <div id="selected-business-logo" class="w-12 h-12 sm:w-14 sm:h-14 rounded-xl flex items-center justify-center flex-shrink-0 overflow-hidden bg-white shadow-md p-2 hidden">
                    <img src="" alt="" class="w-full h-full object-contain" id="selected-business-logo-img">
                </div>
                <div>
                    <h1 class="text-xl sm:text-2xl lg:text-3xl font-black text-slate-900 tracking-tighter">
                        <span id="selected-business-name"></span> - Masalar
                    </h1>
                    <p class="text-slate-400 font-bold uppercase text-[8px] sm:text-[9px] lg:text-[10px] tracking-widest mt-1"><?php echo t('tables.subtitle'); ?></p>
                </div>
            </div>
            <div class="flex gap-2 sm:gap-3 items-center">
                <button type="button" onclick="openZoneManagement()" class="q-btn q-btn--soft q-btn--sm" title="Bölge Yönetimi">
                    <svg class="biz-icon-floor-plan w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v14a1 1 0 01-1 1H5a1 1 0 01-1-1V5z"/><rect x="7" y="7" width="3.5" height="2.5" rx=".5" stroke-width="1.5"/><rect x="13.5" y="7" width="3.5" height="2.5" rx=".5" stroke-width="1.5"/><circle cx="8.5" cy="16" r="1.75" stroke-width="1.5"/><circle cx="15.5" cy="16" r="1.75" stroke-width="1.5"/></svg>
                    <span class="hidden sm:inline">Bölge Yönetimi</span>
                </button>
                <button type="button" onclick="openAddTableModal()" class="q-btn q-btn--primary q-btn--sm">
                    <?php echo icon_plus(['class' => 'w-4 h-4 sm:w-5 sm:h-5']); ?>
                    <span><?php echo t('tables.addTable'); ?></span>
                </button>
            </div>
        </header>
    <?php else: ?>
    <!-- REGULAR BUSINESS OWNER VIEW -->
    <header class="q-page-header">
        <div class="flex items-center gap-3 flex-wrap min-w-0">
            <?php if (!empty($business_logo_path)): ?>
            <div class="w-12 h-12 sm:w-14 sm:h-14 rounded-xl flex items-center justify-center flex-shrink-0 overflow-hidden bg-white shadow-md p-2">
                <img src="<?php echo BASE_URL . htmlspecialchars($business_logo_path); ?>"
                     alt="<?php echo htmlspecialchars($business_name ?? getAppConfig()->getAppName()); ?>"
                     class="w-full h-full object-contain">
            </div>
            <?php endif; ?>
            <div>
                <p class="q-page-header__eyebrow">Masalar</p>
                <h1 class="q-page-header__title"><?php echo htmlspecialchars($business_name ?? t('tables.title')); ?></h1>
                <p class="q-page-header__subtitle"><?php echo t('tables.subtitle'); ?></p>
            </div>
        </div>
        <div class="q-page-header__actions">
            <button type="button" onclick="openZoneManagement()" class="q-btn q-btn--soft q-btn--sm" title="Bölge Yönetimi">
                <svg class="biz-icon-floor-plan w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v14a1 1 0 01-1 1H5a1 1 0 01-1-1V5z"/><rect x="7" y="7" width="3.5" height="2.5" rx=".5" stroke-width="1.5"/><rect x="13.5" y="7" width="3.5" height="2.5" rx=".5" stroke-width="1.5"/><circle cx="8.5" cy="16" r="1.75" stroke-width="1.5"/><circle cx="15.5" cy="16" r="1.75" stroke-width="1.5"/></svg>
                <span class="hidden sm:inline">Bölge Yönetimi</span>
            </button>
            <button type="button" onclick="openAddTableModal()" class="q-btn q-btn--primary q-btn--sm">
                <?php echo icon_plus(['class' => 'w-4 h-4']); ?>
                <span><?php echo t('tables.addTable'); ?></span>
            </button>
        </div>
    </header>
    <?php endif; ?>

    <!-- Zone Management Section (Hidden by default) -->
    <div id="zoneManagementSection" class="hidden q-zone-mgmt" role="region" aria-label="Bölge Yönetimi">
            <div class="q-zone-mgmt__header">
                <div class="q-zone-mgmt__title-row">
                    <button type="button" onclick="closeZoneManagement()" class="q-btn q-btn--ghost q-btn--icon" aria-label="Masalara dön">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                    </button>
                    <svg class="biz-icon-floor-plan w-6 h-6 text-indigo-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v14a1 1 0 01-1 1H5a1 1 0 01-1-1V5z"/><rect x="7" y="7" width="3.5" height="2.5" rx=".5" stroke-width="1.5"/><rect x="13.5" y="7" width="3.5" height="2.5" rx=".5" stroke-width="1.5"/><circle cx="8.5" cy="16" r="1.75" stroke-width="1.5"/><circle cx="15.5" cy="16" r="1.75" stroke-width="1.5"/></svg>
                    <h2 class="q-zone-mgmt__title">Bölge Yönetimi</h2>
                </div>
                <button type="button" onclick="openAddZoneModal()" class="q-btn q-btn--primary q-btn--sm shrink-0">
                    <?php echo icon_plus(['class' => 'w-4 h-4']); ?>
                    <span>Yeni Bölge</span>
                </button>
            </div>
            <div id="zonesManagementList" class="q-zone-mgmt__grid">
                <!-- Zones will be loaded here -->
            </div>
    </div>

    <!-- Zone and Floor Filters -->
    <div id="filtersSection" class="mb-4 sm:mb-6">
        <div class="flex flex-wrap gap-2 sm:gap-3 items-center">
            <label class="text-xs sm:text-sm font-black text-slate-700"><?php echo t('common.filter', 'Filtrele'); ?>:</label>
            <select id="filterZone" onchange="updateFilters()" class="px-3 py-2 rounded-xl border border-slate-200 text-xs sm:text-sm font-bold bg-white focus:border-indigo-500 focus:outline-none">
                <option value=""><?php echo t('zones.allZones', 'Tüm Bölgeler'); ?></option>
                <!-- Zones will be loaded dynamically -->
            </select>
            <select id="filterFloor" onchange="updateFilters()" class="px-3 py-2 rounded-xl border border-slate-200 text-xs sm:text-sm font-bold bg-white focus:border-indigo-500 focus:outline-none">
                <option value="">Tüm Katlar</option>
                <!-- Floors will be loaded dynamically -->
            </select>
            <button onclick="clearFilters()" class="px-3 py-2 bg-slate-100 text-slate-700 rounded-xl text-xs sm:text-sm font-bold hover:bg-slate-200 transition-all">
                Filtreleri Temizle
            </button>
        </div>
    </div>

    <!-- Tables Grid -->
    <div class="min-w-0" id="tablesGrid">
        <!-- Tables will be loaded here -->
    </div>
</div>
  </div>
</div>

<!-- Add/Edit Table Modal -->
<div id="tableModal" class="q-modal-backdrop q-tables-modal hidden" role="dialog" aria-modal="true" aria-labelledby="modalTableTitle">
 <div class="q-modal-backdrop__scrim" onclick="closeTableModal()"></div>
 <div class="q-modal">
        <div class="flex justify-between items-center mb-4 sm:mb-6">
            <h2 class="text-xl sm:text-2xl lg:text-3xl font-black tracking-tighter" id="modalTableTitle"><?php echo t('tables.addTable'); ?></h2>
            <button onclick="closeTableModal()" class="p-2 hover:bg-slate-100 rounded-lg transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        <form id="tableForm" class="space-y-4 sm:space-y-6">
            <input type="hidden" id="tableId">
            <?php echo csrf_field(); ?>
            <div>
                <label class="text-[8px] sm:text-[9px] lg:text-[10px] font-black text-slate-600 uppercase ml-1 sm:ml-2 tracking-widest block mb-1 sm:mb-2"><?php echo t('tables.tableName'); ?></label>
                <input type="text" id="tableName" required
                       class="w-full p-3 sm:p-4 lg:p-6 bg-slate-50 rounded-xl sm:rounded-2xl lg:rounded-[30px] font-bold text-sm sm:text-base lg:text-lg text-slate-900 outline-none border-2 lg:border-4 border-transparent focus:border-indigo-100 transition-all"/>
            </div>
            <div>
                <label class="text-[8px] sm:text-[9px] lg:text-[10px] font-black text-slate-600 uppercase ml-1 sm:ml-2 tracking-widest block mb-1 sm:mb-2"><?php echo t('tables.zone'); ?></label>
                <div class="flex gap-2">
                    <select id="tableZone" required
                            class="flex-1 p-3 sm:p-4 lg:p-6 bg-slate-50 rounded-xl sm:rounded-2xl lg:rounded-[30px] font-black text-sm sm:text-base text-slate-900 outline-none border-2 lg:border-4 border-transparent focus:border-indigo-100 transition-all appearance-none">
                        <option value=""><?php echo t('tables.selectZone'); ?></option>
                        <!-- Zones will be loaded dynamically -->
                    </select>
                    <button type="button" onclick="openZoneManagement()" 
                       class="px-3 sm:px-4 bg-slate-900 text-white rounded-xl sm:rounded-2xl flex items-center justify-center hover:bg-slate-800 transition-all"
                       title="<?php echo t('zones.title'); ?>">
                        <?php echo icon_settings(['class' => 'w-4 h-4']); ?>
                    </button>
                </div>
            </div>
            <div>
                <label class="text-[8px] sm:text-[9px] lg:text-[10px] font-black text-slate-600 uppercase ml-1 sm:ml-2 tracking-widest block mb-1 sm:mb-2"><?php echo t('tables.capacity'); ?></label>
                <input type="number" id="tableCapacity" value="4" min="1"
                       class="w-full p-3 sm:p-4 lg:p-6 bg-slate-50 rounded-xl sm:rounded-2xl lg:rounded-[30px] font-black text-sm sm:text-base lg:text-lg text-slate-900 outline-none border-2 lg:border-4 border-transparent focus:border-indigo-100 transition-all"/>
            </div>
            <div id="remoteAccessField" class="mt-3 sm:mt-4">
                <div class="flex items-center justify-between bg-purple-50 rounded-xl sm:rounded-2xl p-3 sm:p-4">
                    <div class="flex-1">
                        <label class="text-xs sm:text-sm font-black text-purple-900 block">Her Yerden Erişim</label>
                        <p class="text-[10px] sm:text-xs text-purple-600 mt-0.5">Test/geliştirici: Konum kontrolünü bu masa için devre dışı bırakır</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer ml-4">
                        <input type="checkbox" id="tableRemoteAccess" class="sr-only peer">
                        <div class="w-11 h-6 bg-slate-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-purple-600"></div>
                    </label>
                </div>
            </div>
            <div class="flex gap-3 sm:gap-4 mt-4 sm:mt-6 lg:mt-8">
                <button type="button" onclick="closeTableModal()" class="flex-1 py-3 sm:py-4 lg:py-6 bg-slate-100 text-slate-900 rounded-xl sm:rounded-2xl lg:rounded-[30px] font-black uppercase text-[9px] sm:text-[10px] lg:text-xs tracking-widest">
                    <?php echo t('common.cancel'); ?>
                </button>
                <button type="submit" class="flex-1 py-3 sm:py-4 lg:py-6 bg-slate-900 text-white rounded-xl sm:rounded-2xl lg:rounded-[30px] font-black uppercase text-[9px] sm:text-[10px] lg:text-xs tracking-widest shadow-2xl">
                    <?php echo t('common.save'); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- QR Code Modal -->
<div id="qrModal" class="q-modal-backdrop q-tables-modal hidden" role="dialog" aria-modal="true">
 <div class="q-modal-backdrop__scrim" onclick="closeQrModal()"></div>
 <div class="q-modal">
        <div class="flex justify-between items-center mb-4 sm:mb-6">
            <h2 class="text-xl sm:text-2xl lg:text-3xl font-black tracking-tighter" id="qrModalTitle">QR Kod</h2>
            <button onclick="closeQRModal()" class="p-2 hover:bg-slate-100 rounded-lg transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        <div id="qrCodeContainer" class="mb-6 flex justify-center">
            <!-- QR Code will be displayed here -->
        </div>
        <div class="mb-4">
            <p class="text-sm text-slate-600 font-bold mb-2" id="qrTableInfo"></p>
            <input type="text" id="qrCodeUrl" readonly 
                   class="w-full p-3 bg-slate-50 rounded-xl text-xs font-mono text-center border-2 border-slate-100"/>
        </div>
        <div class="flex gap-3">
            <button onclick="copyQRUrl()" class="flex-1 py-3 bg-slate-100 text-slate-900 rounded-xl font-black uppercase text-xs tracking-widest hover:bg-slate-900 hover:text-white transition-all">
                Linki Kopyala
            </button>
            <button onclick="downloadQRCode()" class="q-btn q-btn--primary flex-1 py-3 rounded-xl font-black uppercase text-xs tracking-widest">
                İndir
            </button>
        </div>
    </div>
</div>

<!-- Zone Management Modal -->
<div id="zoneModal" class="q-modal-backdrop q-tables-modal hidden" role="dialog" aria-modal="true">
 <div class="q-modal-backdrop__scrim" onclick="closeZoneModal()"></div>
 <div class="q-modal">
        <div class="flex justify-between items-center mb-4 sm:mb-6">
            <h2 class="text-xl sm:text-2xl lg:text-3xl font-black tracking-tighter" id="modalZoneTitle">Bölge Ekle</h2>
            <button onclick="closeZoneModal()" class="p-2 hover:bg-slate-100 rounded-lg transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        <form id="zoneForm" class="space-y-4 sm:space-y-6" onsubmit="event.preventDefault(); saveZone();">
            <input type="hidden" id="zoneId">
            <?php echo csrf_field(); ?>
            <div>
                <label class="text-[8px] sm:text-[9px] lg:text-[10px] font-black text-slate-600 uppercase ml-1 sm:ml-2 tracking-widest block mb-1 sm:mb-2">Bölge Adı</label>
                <input type="text" id="zoneName" required placeholder="Örn: Teras, Salon, Bahçe..."
                       class="w-full p-3 sm:p-4 lg:p-6 bg-slate-50 rounded-xl sm:rounded-2xl lg:rounded-[30px] font-bold text-sm sm:text-base lg:text-lg text-slate-900 outline-none border-2 lg:border-4 border-transparent focus:border-indigo-100 transition-all"/>
            </div>
            <div>
                <label class="text-[8px] sm:text-[9px] lg:text-[10px] font-black text-slate-600 uppercase ml-1 sm:ml-2 tracking-widest block mb-1 sm:mb-2">Kat</label>
                <input type="text" id="zoneFloor" placeholder="Örn: 1. Kat, Zemin Kat, 2. Kat (İsteğe bağlı)"
                       class="w-full p-3 sm:p-4 lg:p-6 bg-slate-50 rounded-xl sm:rounded-2xl lg:rounded-[30px] font-bold text-sm sm:text-base lg:text-lg text-slate-900 outline-none border-2 lg:border-4 border-transparent focus:border-indigo-100 transition-all"/>
            </div>
            <div>
                <label class="text-[8px] sm:text-[9px] lg:text-[10px] font-black text-slate-600 uppercase ml-1 sm:ml-2 tracking-widest block mb-1 sm:mb-2">Açıklama (İsteğe Bağlı)</label>
                <textarea id="zoneDescription" rows="3" placeholder="Bölge açıklaması..."
                       class="w-full p-3 sm:p-4 lg:p-6 bg-slate-50 rounded-xl sm:rounded-2xl lg:rounded-[30px] font-bold text-sm sm:text-base text-slate-900 outline-none border-2 lg:border-4 border-transparent focus:border-indigo-100 transition-all resize-none"></textarea>
            </div>
            <div class="flex gap-3 sm:gap-4 mt-4 sm:mt-6 lg:mt-8">
                <button type="button" onclick="closeZoneModal()" class="flex-1 py-3 sm:py-4 lg:py-6 bg-slate-100 text-slate-900 rounded-xl sm:rounded-2xl lg:rounded-[30px] font-black uppercase text-[9px] sm:text-[10px] lg:text-xs tracking-widest">
                    İptal
                </button>
                <button type="submit" class="flex-1 py-3 sm:py-4 lg:py-6 bg-slate-900 text-white rounded-xl sm:rounded-2xl lg:rounded-[30px] font-black uppercase text-[9px] sm:text-[10px] lg:text-xs tracking-widest shadow-2xl">
                    Kaydet
                </button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
// Ensure CSRF_TOKEN is always available before any scripts run
if (typeof window.CSRF_TOKEN === 'undefined') {
    const metaToken = document.querySelector('meta[name="csrf-token"]');
    if (metaToken) {
        window.CSRF_TOKEN = metaToken.getAttribute('content') || '';
    } else {
        window.CSRF_TOKEN = '';
        console.warn('CSRF token not found in meta tag or window.CSRF_TOKEN');
    }
}

// Global error handler to catch undefined variable errors in onclick handlers
window.addEventListener('error', function(event) {
    // Check if error is about an undefined variable that looks like a CSRF token
    if (event.message && event.message.includes('is not defined')) {
        const match = event.message.match(/([a-z0-9]{16,}) is not defined/);
        if (match && match[1]) {
            const varName = match[1];
            // If it looks like a CSRF token (hex string), it might be a token used without quotes
            const adminPrefix = <?php echo json_encode($isSuperAdmin ? '/qodmin' : '/business'); ?>;
            if (/^[a-z0-9]{16,}$/i.test(varName) && event.filename && (event.filename.includes('/qodmin/tables') || event.filename.includes('/business/tables'))) {
                console.error('Possible CSRF token used as variable in onclick handler:', varName);
                console.error('Error details:', {
                    message: event.message,
                    filename: event.filename,
                    lineno: event.lineno,
                    colno: event.colno,
                    stack: event.error ? event.error.stack : 'No stack trace'
                });
                // Prevent the error from breaking the page
                event.preventDefault();
                return true;
            }
        }
    }
    return false;
}, true);
</script>
<script src="<?php echo BASE_URL; ?>/assets/js/admin/tables.js?v=<?php echo time(); ?>&nocache=<?php echo rand(10000, 99999); ?>"></script>
<script>
// Initialize TablesPage with configuration
document.addEventListener('DOMContentLoaded', function() {
    TablesPage.init({
        baseUrl: <?php echo json_encode(BASE_URL ?? '', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
        apiPrefix: <?php echo json_encode($apiPrefix, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
        translations: <?php echo json_encode([
            'free' => t('tables.free'),
            'occupied' => t('tables.occupied'),
            'paymentPending' => t('tables.paymentPending'),
            'tableDeleteConfirm' => t('notifications.tableDeleteConfirm', 'Bu masayı silmek istediğinizden emin misiniz?'),
            'tableDeleteTitle' => t('notifications.tableDelete', 'Masa Silme Onayı'),
            'zoneDeleteConfirm' => t('notifications.zoneDeleteConfirm'),
            'zoneDeleteTitle' => t('notifications.zoneDelete'),
            'dirty' => t('tables.dirty'),
            'reserved' => t('tables.reserved'),
            'all' => t('tables.all'),
            'addTable' => t('tables.addTable'),
            'editTable' => t('tables.editTable'),
            'deleteTable' => t('tables.deleteTable'),
            'tableName' => t('tables.tableName'),
            'zone' => t('tables.zone'),
            'capacity' => t('tables.capacity'),
            'status' => t('tables.status'),
            'person' => t('tables.person'),
            'deleteConfirm' => t('tables.deleteConfirm'),
            'deleteFailed' => t('tables.deleteFailed'),
            'saveFailed' => t('tables.saveFailed'),
            'fillRequiredFields' => t('tables.fillRequiredFields'),
            'error' => t('tables.error'),
            'statusUpdateFailed' => t('tables.statusUpdateFailed')
        ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>
    });
    
    <?php if ($is_super_admin ?? false): ?>
    // Super Admin: Load BusinessSelector
    const bsScript = document.createElement('script');
    bsScript.src = '<?php echo BASE_URL; ?>/assets/js/business-selector.js?v=<?php echo time(); ?>';
    bsScript.onload = function() {
        if (typeof BusinessSelector === 'undefined') return;
        
        BusinessSelector.init({ baseUrl: <?php echo json_encode(BASE_URL); ?> });
        
        // Check if business_id is in URL (page reload scenario)
        const urlParams = new URLSearchParams(window.location.search);
        const businessIdFromUrl = urlParams.get('business_id');
        
        if (businessIdFromUrl) {
            // Business ID in URL - load business info directly from API and show tables view
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
                        
                        // Get business logo
                        const businessLogo = business.logo_path || business.logo || null;
                        
                        // Set in session storage
                        sessionStorage.setItem('selected_business_id', businessIdFromUrl);
                        sessionStorage.setItem('selected_business_name', businessName);
                        if (businessLogo) {
                            sessionStorage.setItem('selected_business_logo', businessLogo);
                        }
                        window.currentBusinessId = businessIdFromUrl;
                        
                        // Load tables for this business
                        loadBusinessTables(businessIdFromUrl, businessName, businessLogo);
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
                BusinessSelector.renderBusinessGrid('business-grid', function(businessId, businessName, businessLogo) {
                    // Set business ID in session storage
                    sessionStorage.setItem('selected_business_id', businessId);
                    sessionStorage.setItem('selected_business_name', businessName);
                    if (businessLogo) {
                        sessionStorage.setItem('selected_business_logo', businessLogo);
                    }
                    window.currentBusinessId = businessId;
                    
                    // Update URL without page reload
                    const url = new URL(window.location.href);
                    url.searchParams.set('business_id', businessId);
                    window.history.pushState({ businessId, businessName }, '', url.toString());
                    
                    // Load tables for this business
                    loadBusinessTables(businessId, businessName, businessLogo);
                });
            });
        }
    };
    document.head.appendChild(bsScript);
    <?php endif; ?>
});

<?php if ($is_super_admin ?? false): ?>
window.backToBusinessSelection = function() {
    BusinessSelector.showSelectionView('business-selection-view', 'table-management-view');
    const grid = document.getElementById('tablesGrid');
    if (grid) grid.innerHTML = '';
    
    // Remove business_id from URL
    const url = new URL(window.location.href);
    url.searchParams.delete('business_id');
    window.history.pushState({}, '', url.toString());
    
    // Clear session storage
    sessionStorage.removeItem('selected_business_id');
    sessionStorage.removeItem('selected_business_name');
    window.currentBusinessId = null;
};

function loadBusinessTables(businessId, businessName, businessLogo = null) {
    const grid = document.getElementById('tablesGrid');
    if (grid) {
        grid.innerHTML = '<div class="col-span-full text-center py-12"><div class="inline-block animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-indigo-500"></div></div>';
    }
    
    // Update logo in header if provided
    const logoContainer = document.getElementById('selected-business-logo');
    const logoImg = document.getElementById('selected-business-logo-img');
    if (businessLogo && logoContainer && logoImg) {
        let logoUrl;
        if (businessLogo.startsWith('http')) {
            logoUrl = businessLogo;
        } else {
            // Add /public prefix for main domain paths
            const needsPublicPrefix = !businessLogo.startsWith('/public/');
            logoUrl = `<?php echo BASE_URL; ?>${needsPublicPrefix ? '/public' : ''}${businessLogo}`;
        }
        logoImg.src = logoUrl;
        logoImg.alt = businessName;
        logoContainer.classList.remove('hidden');
    } else if (logoContainer) {
        logoContainer.classList.add('hidden');
    }
    
    const apiPrefix = <?php echo json_encode($apiPrefix); ?>;
    fetch(`<?php echo BASE_URL; ?>${apiPrefix}/businesses/${businessId}/tables`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.tables) {
                window.currentBusinessId = businessId;
                if (TablesPage && TablesPage.updateData) {
                    TablesPage.updateData(data.tables);
                }
                BusinessSelector.showContentView('business-selection-view', 'table-management-view', businessName);
            }
        })
        .catch(error => {
            console.error('Error loading tables:', error);
            if (window.NotificationManager) {
                window.NotificationManager.error('Masalar yüklenirken hata oluştu');
            }
        });
}
<?php endif; ?>
</script>

<?php
// Content is already captured by Controller's view() method
// Layout is included by Controller, so we don't need to include it here
?>
