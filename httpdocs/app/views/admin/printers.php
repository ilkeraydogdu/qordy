<?php
/**
 * Printers View - Yazıcı yönetimi
 */

$printers = $printers ?? [];
$baseUrl = BASE_URL;
if (!function_exists('getAdminUrl')) {
    require_once __DIR__ . '/../../helpers/url_helper.php';
}
$isSuperAdmin = $is_super_admin ?? false;
$adminPrefix = $isSuperAdmin ? '/qodmin' : '/business';
$apiPrefix = $isSuperAdmin ? '/api/qodmin' : '/api/business';
?>

<div class="q-page q-biz-theme animate-slide-up">
<div class="q-container q-stack q-stack--lg">
    <?php if ($is_super_admin ?? false): ?>
    <!-- SUPER ADMIN VIEW: Business Selection First -->
    <div id="business-selection-view">
        <header class="q-page-header">
            <div>
                <p class="q-page-header__eyebrow">Yazıcılar</p>
                <h1 class="q-page-header__title">Yazıcı Yönetimi</h1>
                <p class="q-page-header__subtitle">Yazıcılarını görüntülemek istediğiniz işletmeyi seçin</p>
            </div>
            <div class="q-page-header__actions q-field" style="min-width:16rem;margin:0;">
                <input type="text" id="business-search" placeholder="İşletme ara..."
                       onkeyup="BusinessSelector.searchBusinesses(this.value)" class="q-input"/>
            </div>
        </header>
        <div id="business-grid" class="q-grid q-grid--4">
            <div class="col-span-full text-center py-12">
                <div class="q-spinner" style="margin:0 auto;"></div>
                <p class="q-hint mt-4">İşletmeler yükleniyor...</p>
            </div>
        </div>
    </div>

    <!-- Printer Management View (shown after business selection) -->
    <div id="printer-management-view" class="hidden">
        <header class="q-page-header">
            <div class="q-toolbar" style="align-items:flex-start;">
                <button type="button" onclick="backToBusinessSelection()" class="q-btn q-btn--ghost q-btn--sm" aria-label="Geri">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                </button>
                <div>
                    <p class="q-page-header__eyebrow" id="selected-business-name"></p>
                    <h1 class="q-page-header__title">Yazıcı Yönetimi</h1>
                </div>
            </div>
            <div class="q-page-header__actions">
                <a href="<?php echo getAdminUrl('printers/bridge-setup'); ?>" class="q-btn q-btn--secondary">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path>
                    </svg>
                    Köprü Ayarları
                </a>
            </div>
        </header>
    <?php else: ?>
    <!-- REGULAR BUSINESS OWNER VIEW -->
    <header class="q-page-header">
        <div>
            <p class="q-page-header__eyebrow">Operasyon</p>
            <h1 class="q-page-header__title">
                <?php if (!empty($business_name ?? '')): ?>
                    <?php echo htmlspecialchars($business_name); ?> — Yazıcı Yönetimi
                <?php else: ?>
                    Yazıcı Yönetimi
                <?php endif; ?>
            </h1>
            <p class="q-page-header__subtitle">Yazıcı köprüleri ve ekran atamaları</p>
        </div>
        <div class="q-page-header__actions">
            <a href="<?php echo getAdminUrl('printers/bridge-setup'); ?>" class="q-btn q-btn--secondary">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path>
                </svg>
                Köprü Ayarları
            </a>
        </div>
    </header>
    <?php endif; ?>
    
    <div id="printers-container">
        <?php if ($is_super_admin ?? false): ?>
        <!-- Printers will be loaded here by JavaScript -->
        <?php else: ?>
        <?php if (empty($printers)): ?>
            <div class="q-card q-card--pad text-center" style="border:2px dashed var(--color-border-1);padding:var(--space-10);">
                <svg class="w-16 h-16 mx-auto mb-4" style="color:var(--color-text-muted);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                </svg>
                <p class="font-bold mb-6" style="color:var(--color-text-secondary);">Henüz yazıcı eklenmemiş.</p>
                <div id="bridges-container" class="mb-8"></div>
                <a href="<?php echo getAdminUrl('printers/bridge-setup'); ?>" class="q-btn q-btn--primary">
                    Yazıcı Köprüsü Kur
                </a>
            </div>
        <?php else: ?>
            <div class="q-grid q-grid--3">
                <?php foreach ($printers as $printer):
                    $isActive = ($printer['is_active'] ?? true);
                    $pid = htmlspecialchars($printer['printer_id'] ?? '', ENT_QUOTES, 'UTF-8');
                ?>
                    <div class="q-card q-card--pad q-stack q-stack--sm">
                        <div class="q-toolbar" style="align-items:flex-start;">
                            <div class="flex-1 min-w-0">
                                <div class="q-toolbar gap-2 mb-2">
                                    <svg class="w-6 h-6 shrink-0" style="color:var(--color-text-secondary);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                                    </svg>
                                    <h4 class="font-bold truncate" style="color:var(--color-text-primary);"><?php echo htmlspecialchars($printer['printer_name'] ?? 'Yazıcı'); ?></h4>
                                </div>
                                <?php if (!empty($printer['printer_location']) || !empty($printer['printer_serial']) || !empty($printer['bridge_name'])): ?>
                                <div class="q-stack q-stack--xs q-hint text-sm">
                                    <?php if (!empty($printer['bridge_name'])): ?>
                                    <div class="q-toolbar gap-1.5">
                                        <svg class="w-4 h-4 shrink-0" style="color:var(--color-brand-accent);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path>
                                        </svg>
                                        <span class="font-semibold" style="color:var(--color-brand-accent-hover);"><?php echo htmlspecialchars($printer['bridge_name']); ?></span>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (!empty($printer['printer_location'])): ?>
                                    <div class="q-toolbar gap-1.5">
                                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                        </svg>
                                        <span><?php echo htmlspecialchars($printer['printer_location']); ?></span>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (!empty($printer['printer_serial'])): ?>
                                    <div class="q-toolbar gap-1.5">
                                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"></path>
                                        </svg>
                                        <span class="font-mono text-xs"><?php echo htmlspecialchars($printer['printer_serial']); ?></span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="q-toolbar" style="border-top:1px solid var(--color-border-1);padding-top:var(--space-3);">
                            <span class="q-badge <?php echo $isActive ? 'q-badge--success' : ''; ?>">
                                <?php echo $isActive ? 'Aktif' : 'Pasif'; ?>
                            </span>
                            <div class="q-toolbar gap-1 ml-auto">
                                <button type="button" onclick="testPrintPrinter('<?php echo $pid; ?>')" class="q-icon-btn" title="Test Yazdır" aria-label="Test Yazdır">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                                    </svg>
                                </button>
                                <button type="button" onclick="openEditPrinterModal('<?php echo $pid; ?>')" class="q-icon-btn" title="Düzenle" aria-label="Düzenle">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                    </svg>
                                </button>
                                <button type="button" onclick="deletePrinter('<?php echo $pid; ?>')" class="q-icon-btn" title="Sil" aria-label="Sil" style="color:var(--color-status-danger);">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
</div>

<!-- Edit Printer Modal -->
<div id="printerModal" class="q-modal-backdrop hidden">
    <div class="q-modal-backdrop__scrim" onclick="closePrinterModal()"></div>
    <div class="q-modal q-modal--wide">
        <div class="q-modal__header">
            <h2 class="q-modal__title" id="modalPrinterTitle">Yazıcı Düzenle</h2>
            <button type="button" onclick="closePrinterModal()" class="q-btn q-btn--ghost q-btn--icon" aria-label="Kapat">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        <form id="printerForm" class="q-modal__body q-stack q-stack--md">
            <input type="hidden" id="printerId">
            <?php echo csrf_field(); ?>
            <div class="q-field">
                <label class="q-label" for="printerName">Yazıcı Adı *</label>
                <input type="text" id="printerName" required placeholder="Örn: Mutfak Yazıcısı, Bar Yazıcısı..." class="q-input"/>
            </div>
            <div class="q-field">
                <label class="q-label">Atanacağı Ekran *</label>
                <div id="screensList" class="q-card q-card--pad" style="max-height:16rem;overflow-y:auto;background:var(--color-surface-muted);">
                    <div class="text-center py-4 q-hint text-sm">Yükleniyor...</div>
                </div>
                <p class="q-hint mt-1">Bu yazıcının yazdıracağı ekranları seçin. Seçilen ekrana gelen fişler sadece bu yazıcıdan çıkacaktır.</p>
            </div>
            <div class="q-modal__footer q-toolbar">
                <button type="button" onclick="closePrinterModal()" class="q-btn q-btn--ghost">İptal</button>
                <button type="submit" class="q-btn q-btn--primary">Kaydet</button>
            </div>
        </form>
    </div>
</div>

<script>
const baseUrl = <?php echo json_encode($baseUrl ?? '', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

function escapeHtml(text) {
    if (text == null) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function buildPrinterCardHtml(printer) {
    const pid = escapeHtml(printer.printer_id || '');
    const isActive = printer.is_active !== false && printer.status !== 'INACTIVE';
    const badgeClass = isActive ? 'q-badge q-badge--success' : 'q-badge';
    const meta = [];
    if (printer.bridge_name) {
        meta.push(`<div class="q-toolbar gap-1.5"><svg class="w-4 h-4 shrink-0" style="color:var(--color-brand-accent);" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path></svg><span class="font-semibold" style="color:var(--color-brand-accent-hover);">${escapeHtml(printer.bridge_name)}</span></div>`);
    }
    if (printer.printer_location) {
        meta.push(`<div class="q-toolbar gap-1.5"><svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg><span>${escapeHtml(printer.printer_location)}</span></div>`);
    }
    if (printer.printer_serial) {
        meta.push(`<div class="q-toolbar gap-1.5"><svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"></path></svg><span class="font-mono text-xs">${escapeHtml(printer.printer_serial)}</span></div>`);
    }
    const metaBlock = meta.length ? `<div class="q-stack q-stack--xs q-hint text-sm">${meta.join('')}</div>` : '';
    return `
        <div class="q-card q-card--pad q-stack q-stack--sm">
            <div class="q-toolbar" style="align-items:flex-start;">
                <div class="flex-1 min-w-0">
                    <div class="q-toolbar gap-2 mb-2">
                        <svg class="w-6 h-6 shrink-0" style="color:var(--color-text-secondary);" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                        <h4 class="font-bold truncate" style="color:var(--color-text-primary);">${escapeHtml(printer.printer_name || 'Yazıcı')}</h4>
                    </div>
                    ${metaBlock}
                </div>
            </div>
            <div class="q-toolbar" style="border-top:1px solid var(--color-border-1);padding-top:var(--space-3);">
                <span class="${badgeClass}">${isActive ? 'Aktif' : 'Pasif'}</span>
                <div class="q-toolbar gap-1 ml-auto">
                    <button type="button" onclick="testPrintPrinter('${pid}')" class="q-icon-btn" title="Test Yazdır" aria-label="Test Yazdır"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg></button>
                    <button type="button" onclick="openEditPrinterModal('${pid}')" class="q-icon-btn" title="Düzenle" aria-label="Düzenle"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg></button>
                    <button type="button" onclick="deletePrinter('${pid}')" class="q-icon-btn" title="Sil" aria-label="Sil" style="color:var(--color-status-danger);"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg></button>
                </div>
            </div>
        </div>`;
}

<?php if ($is_super_admin ?? false): ?>
// Super Admin: Load BusinessSelector
const bsScript = document.createElement('script');
bsScript.src = '<?php echo BASE_URL; ?>/assets/js/business-selector.js?v=<?php echo time(); ?>';
bsScript.onload = function() {
    if (typeof BusinessSelector === 'undefined') return;
    
    BusinessSelector.init({ baseUrl: <?php echo json_encode(BASE_URL); ?> });
    BusinessSelector.loadBusinesses().then(businesses => {
        BusinessSelector.renderBusinessGrid('business-grid', function(businessId, businessName) {
            loadBusinessPrinters(businessId, businessName);
        });
    });
};
document.head.appendChild(bsScript);

window.backToBusinessSelection = function() {
    BusinessSelector.showSelectionView('business-selection-view', 'printer-management-view');
    const container = document.getElementById('printers-container');
    if (container) container.innerHTML = '';
};

function loadBusinessPrinters(businessId, businessName) {
    window.currentBusinessId = businessId;
    
    const container = document.getElementById('printers-container');
    if (container) {
        container.innerHTML = '<div class="col-span-full text-center py-12"><div class="q-spinner" style="margin:0 auto;"></div><p class="q-hint mt-4">Yükleniyor...</p></div>';
    }
    
    const apiPrefix = <?php echo json_encode($apiPrefix); ?>;
    fetch(`<?php echo BASE_URL; ?>${apiPrefix}/businesses/${businessId}/printers`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.printers) {
                BusinessSelector.showContentView('business-selection-view', 'printer-management-view', businessName);
                
                if (container) {
                    if (!data.printers || data.printers.length === 0) {
                        container.innerHTML = `
                            <div class="q-card q-card--pad text-center" style="border:2px dashed var(--color-border-1);padding:var(--space-10);">
                                <svg class="w-16 h-16 mx-auto mb-4" style="color:var(--color-text-muted);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                                </svg>
                                <p class="font-bold mb-6" style="color:var(--color-text-secondary);">Henüz yazıcı eklenmemiş.</p>
                                <a href="<?php echo getAdminUrl('printers/bridge-setup'); ?>" class="q-btn q-btn--primary">Yazıcı Köprüsü Kur</a>
                            </div>
                        `;
                    } else {
                        let html = '<div class="q-grid q-grid--3">';
                        data.printers.forEach(printer => { html += buildPrinterCardHtml(printer); });
                        html += '</div>';
                        container.innerHTML = html;
                    }
                }
            }
        })
        .catch(error => {
            console.error('Error loading printers:', error);
            if (container) {
                container.innerHTML = '<div class="q-card q-card--pad q-badge" style="color:var(--color-status-danger);background:var(--color-status-danger-bg);">Hata: Yazıcılar yüklenirken bir sorun oluştu.</div>';
            }
        });
}

<?php endif; ?>

async function deletePrinter(printerId) {
    let confirmed = false;
    if (window.NotificationManager && window.NotificationManager.confirm) {
        confirmed = await window.NotificationManager.confirm('Bu yazıcıyı silmek istediğinize emin misiniz?', 'Onay');
    } else {
        confirmed = confirm('Bu yazıcıyı silmek istediğinize emin misiniz?');
    }
    if (!confirmed) return;
    
    const csrfToken = window.CSRF_TOKEN || (typeof csrf_token !== 'undefined' ? csrf_token : '');
    const apiPrefix = <?php echo json_encode($apiPrefix); ?>;
    
    try {
        const response = await fetch(`${baseUrl}${apiPrefix}/printer/delete`, {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify({ printer_id: printerId })
        });
        const data = await response.json();
        
        if (data.success) {
            if (window.NotificationManager) {
                window.NotificationManager.success('Yazıcı başarıyla silindi');
            }
            location.reload();
        } else {
            const errorMsg = data.message || data.error || 'Yazıcı silinemedi.';
            window.NotificationManager.error('Hata: ' + errorMsg);
        }
    } catch (error) {
        console.error('Error:', error);
        window.NotificationManager.error('Bir hata oluştu.');
    }
}

let allScreens = [];
let selectedScreens = [];

async function loadAllScreensForModal() {
    try {
        const apiPrefix = <?php echo json_encode($apiPrefix); ?>;
        
        // Load preparation screens
        const prepResponse = await fetch(`${baseUrl}${apiPrefix}/preparation-screens/active`);
        const prepData = await prepResponse.json();
        const prepScreens = Array.isArray(prepData) ? prepData : (prepData.screens || []);
        
        // Add kitchen screen (special screen for kitchen orders)
        const kitchenScreen = {
            screen_id: 'KITCHEN',
            name: 'Mutfak',
            production_point: 'KITCHEN',
            is_kitchen: true
        };
        
        // Combine kitchen and preparation screens
        allScreens = [kitchenScreen, ...prepScreens];
        renderScreensList();
    } catch (error) {
        console.error('Error loading screens:', error);
        allScreens = [];
        document.getElementById('screensList').innerHTML = '<div class="text-center py-4 q-hint text-sm" style="color:var(--color-status-danger);">Ekranlar yüklenemedi</div>';
    }
}

function renderScreensList() {
    const container = document.getElementById('screensList');
    if (!container) return;
    
    if (allScreens.length === 0) {
        container.innerHTML = '<div class="text-center py-4 q-hint text-sm">Henüz ekran eklenmemiş</div>';
        return;
    }
    
    // Separate kitchen and preparation screens
    const kitchenScreens = allScreens.filter(s => s.is_kitchen || s.screen_id === 'KITCHEN');
    const prepScreens = allScreens.filter(s => !s.is_kitchen && s.screen_id !== 'KITCHEN');
    
    let html = '';
    
    // Kitchen section
    if (kitchenScreens.length > 0) {
        html += '<div class="mb-4"><div class="q-label mb-2">Mutfak Ekranları</div>';
        html += kitchenScreens.map(screen => {
            const screenId = screen.screen_id || screen.id;
            const screenName = screen.name || 'Mutfak';
            const isChecked = selectedScreens.includes(screenId);
            
            return `
                <label class="q-card q-card--pad q-toolbar gap-3 cursor-pointer mb-2" style="background:var(--color-surface-1);">
                    <input type="checkbox" 
                           value="${escapeHtml(screenId)}" 
                           ${isChecked ? 'checked' : ''}
                           onchange="toggleScreen('${escapeHtml(screenId)}', this.checked)"
                           class="shrink-0">
                    <div class="flex-1 min-w-0">
                        <div class="font-bold text-sm">${escapeHtml(screenName)}</div>
                        <div class="q-hint text-xs">Mutfak siparişleri için</div>
                    </div>
                </label>
            `;
        }).join('');
        html += '</div>';
    }
    
    // Preparation screens section
    if (prepScreens.length > 0) {
        html += '<div><div class="q-label mb-2">Hazırlık Ekranları</div>';
        html += prepScreens.map(screen => {
            const screenId = screen.screen_id || screen.id;
            const screenName = screen.name || '';
            const productionPoint = screen.production_point || '';
            const isChecked = selectedScreens.includes(screenId);
            
            return `
                <label class="q-card q-card--pad q-toolbar gap-3 cursor-pointer mb-2" style="background:var(--color-surface-1);">
                    <input type="checkbox" 
                           value="${escapeHtml(screenId)}" 
                           ${isChecked ? 'checked' : ''}
                           onchange="toggleScreen('${escapeHtml(screenId)}', this.checked)"
                           class="shrink-0">
                    <div class="flex-1 min-w-0">
                        <div class="font-bold text-sm">${escapeHtml(screenName)}</div>
                        ${productionPoint ? `<div class="q-hint text-xs">${escapeHtml(productionPoint)}</div>` : ''}
                    </div>
                </label>
            `;
        }).join('');
        html += '</div>';
    }
    
    container.innerHTML = html || '<div class="text-center py-4 q-hint text-sm">Henüz ekran eklenmemiş</div>';
}

function toggleScreen(screenId, checked) {
    if (checked) {
        if (!selectedScreens.includes(screenId)) {
            selectedScreens.push(screenId);
        }
    } else {
        selectedScreens = selectedScreens.filter(id => id !== screenId);
    }
}

async function openEditPrinterModal(printerId) {
    selectedScreens = [];
    document.getElementById('modalPrinterTitle').textContent = 'Yazıcı Düzenle';
    document.getElementById('printerForm').reset();
    document.getElementById('printerId').value = printerId;
    
    // Load printer data
    try {
        const apiPrefix = <?php echo json_encode($apiPrefix); ?>;
        const response = await fetch(`${baseUrl}${apiPrefix}/printer/${printerId}`);
        const data = await response.json();
        
        if (data.success && data.printer) {
            const printer = data.printer;
            document.getElementById('printerName').value = printer.printer_name || '';
            
            // Load assigned screens
            try {
                const screensResponse = await fetch(`${baseUrl}${apiPrefix}/printer/${printerId}/screens`);
                const screensData = await screensResponse.json();
                if (screensData.success && screensData.screens) {
                    // Map screens - handle both regular screens and KITCHEN special screen
                    selectedScreens = screensData.screens.map(s => {
                        const screenId = s.screen_id || s.id;
                        // If screen_id is KITCHEN or screen_name is Mutfak, use 'KITCHEN'
                        if (screenId === 'KITCHEN' || (s.screen_name && s.screen_name.toLowerCase().includes('mutfak'))) {
                            return 'KITCHEN';
                        }
                        return screenId;
                    }).filter(id => id && id !== '');
                }
            } catch (error) {
                console.error('Error loading printer screens:', error);
            }
        }
    } catch (error) {
        console.error('Error loading printer:', error);
        if (window.NotificationManager) {
            window.NotificationManager.error('Yazıcı bilgileri yüklenemedi');
        }
        return;
    }
    
    document.getElementById('printerModal').classList.remove('hidden');
    await loadAllScreensForModal();
}

function closePrinterModal() {
    document.getElementById('printerModal').classList.add('hidden');
    selectedScreens = [];
}

// Test print function
async function testPrintPrinter(printerId) {
    let confirmed = false;
    if (window.NotificationManager && window.NotificationManager.confirm) {
        confirmed = await window.NotificationManager.confirm('Bu yazıcıya test yazdırma gönderilsin mi?', 'Onay');
    } else {
        confirmed = confirm('Bu yazıcıya test yazdırma gönderilsin mi?');
    }
    if (!confirmed) return;
    
    const csrfToken = window.CSRF_TOKEN || (typeof csrf_token !== 'undefined' ? csrf_token : '');
    const apiPrefix = <?php echo json_encode($apiPrefix); ?>;
    
    try {
        const response = await fetch(`${baseUrl}${apiPrefix}/printer/test-print`, {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify({ printer_id: printerId })
        });
        
        const data = await response.json();
        if (data.success) {
            window.NotificationManager.success('Test yazdırma gönderildi');
        } else {
            const errorMsg = data.error || data.message || 'Test yazdırma başarısız';
            window.NotificationManager.error(errorMsg);
        }
    } catch (error) {
        console.error('Error:', error);
        window.NotificationManager.error('Test yazdırma başarısız');
    }
}

// Form submission - Wait for DOM to be ready
document.addEventListener('DOMContentLoaded', function() {
    const printerForm = document.getElementById('printerForm');
    if (!printerForm) {
        console.error('printerForm element not found');
        return;
    }
    
    printerForm.addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const printerId = document.getElementById('printerId').value;
    const printerName = document.getElementById('printerName').value.trim();
    
    if (!printerName) {
        if (window.NotificationManager) {
            window.NotificationManager.warning('Yazıcı adı gereklidir');
        }
        return;
    }
    
    if (selectedScreens.length === 0) {
        if (window.NotificationManager) {
            window.NotificationManager.warning('En az bir ekran seçmelisiniz');
        }
        return;
    }
    
    const csrfToken = window.CSRF_TOKEN || (typeof csrf_token !== 'undefined' ? csrf_token : '');
    
    if (!printerId) {
        if (window.NotificationManager) {
            window.NotificationManager.error('Yazıcı ID bulunamadı');
        }
        return;
    }
    
    try {
        const apiPrefix = <?php echo json_encode($apiPrefix); ?>;
        const url = `${baseUrl}${apiPrefix}/printer/update`;
        const method = 'POST';
        const body = JSON.stringify({
            printer_id: printerId,
            printer_name: printerName,
            screen_ids: selectedScreens
        });
        
        const response = await fetch(url, {
            method: method,
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            body: body
        });
        
        const data = await response.json();
        
        if (data.success) {
            if (window.NotificationManager) {
                window.NotificationManager.success('Yazıcı güncellendi');
            }
            closePrinterModal();
            location.reload();
        } else {
            if (window.NotificationManager) {
                window.NotificationManager.error('Hata: ' + (data.error || 'Kaydetme başarısız'));
            }
        }
    } catch (error) {
        console.error('Error:', error);
        if (window.NotificationManager) {
            window.NotificationManager.error('Bağlantı hatası');
        }
    }
    });
});

// Load bridges for business owner
<?php if (!($is_super_admin ?? false)): ?>
async function loadBridges() {
    const container = document.getElementById('bridges-container');
    if (!container) return;
    
    try {
        const response = await fetch('<?php echo BASE_URL; ?>/api/business/printer/bridges', {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': window.CSRF_TOKEN || ''
            },
            credentials: 'same-origin'
        });
        
        if (!response.ok) {
            throw new Error('API isteği başarısız');
        }
        
        const data = await response.json();
        
        if (data.success && data.data) {
            const bridges = data.data;
            
            if (bridges.length === 0) {
                container.innerHTML = '';
            } else {
                const bridgesHtml = bridges.map(bridge => {
                    const diff = Math.floor((Date.now() - new Date(bridge.last_heartbeat).getTime()) / 1000);
                    const isOnline = diff < 120;
                    const statusDot = isOnline 
                        ? '<span class="q-badge q-badge--success">Çevrimiçi</span>'
                        : '<span class="q-badge">Çevrimdışı</span>';
                    
                    return `
                        <div class="q-card q-card--pad mb-3 cursor-pointer" style="border:2px solid transparent;transition:border-color var(--transition-fast);"
                             onmouseover="this.style.borderColor='var(--color-brand-accent-muted)'" onmouseout="this.style.borderColor='transparent'"
                             onclick="showBridgePrinters('${bridge.bridge_id}', '${escapeHtml(bridge.device_name)}')">
                            <div class="q-toolbar mb-2">
                                <span class="font-bold">${escapeHtml(bridge.device_name)}</span>
                                ${statusDot}
                            </div>
                            <div class="q-hint text-xs mb-2">
                                <code style="background:var(--color-surface-muted);padding:2px 6px;border-radius:var(--radius-sm);">${escapeHtml(bridge.bridge_id.substring(0, 12))}...</code>
                            </div>
                            <div class="text-xs font-semibold" style="color:var(--color-brand-accent-hover);">
                                Yazıcıları Yönet →
                            </div>
                        </div>
                    `;
                }).join('');
                
                container.innerHTML = `
                    <div class="mb-6 text-left">
                        <p class="q-label mb-3">Bağlı Köprüler (${bridges.length})</p>
                        ${bridgesHtml}
                    </div>
                `;
            }
        }
    } catch (error) {
        console.error('Bridges loading error:', error);
        container.innerHTML = '';
    }
}

// Load bridges on page load
if (document.getElementById('bridges-container')) {
    loadBridges();
    setInterval(loadBridges, 30000);
}

// Show bridge printers modal
async function showBridgePrinters(bridgeId, bridgeName) {
    try {
        const response = await fetch(`<?php echo BASE_URL; ?>/api/business/printer/bridge/${bridgeId}/printers`, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': window.CSRF_TOKEN || ''
            },
            credentials: 'same-origin'
        });
        
        if (!response.ok) {
            throw new Error('API isteği başarısız');
        }
        
        const data = await response.json();
        
        if (data.success && data.data) {
            showPrintersModal(bridgeId, bridgeName, data.data);
        } else {
            if (window.NotificationManager) {
                window.NotificationManager.error('Yazıcılar yüklenemedi');
            }
        }
    } catch (error) {
        console.error('Error loading bridge printers:', error);
        if (window.NotificationManager) {
            window.NotificationManager.error('Bağlantı hatası');
        }
    }
}

// Show printers modal
function showPrintersModal(bridgeId, bridgeName, printers) {
    const modalHtml = `
        <div id="printersModal" class="q-modal-backdrop" onclick="if(event.target.id === 'printersModal') closePrintersModal()">
            <div class="q-modal-backdrop__scrim" onclick="closePrintersModal()"></div>
            <div class="q-modal q-modal--wide" style="max-height:90vh;overflow-y:auto;" onclick="event.stopPropagation()">
                <div class="q-modal__header" style="position:sticky;top:0;background:var(--color-surface-1);z-index:1;">
                    <div>
                        <h2 class="q-modal__title">${escapeHtml(bridgeName)} — Yazıcılar</h2>
                        <p class="q-hint mt-1">Köprü ID: <code style="background:var(--color-surface-muted);padding:2px 6px;border-radius:var(--radius-sm);font-size:var(--font-size-xs);">${bridgeId.substring(0, 12)}...</code></p>
                    </div>
                    <button type="button" onclick="closePrintersModal()" class="q-btn q-btn--ghost q-btn--icon" aria-label="Kapat">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                <div class="q-modal__body">
                    ${printers.length === 0 ? `
                        <div class="text-center py-12">
                            <svg class="w-16 h-16 mx-auto mb-4" style="color:var(--color-text-muted);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                            </svg>
                            <p class="font-bold q-hint">Bu köprüde yazıcı bulunamadı</p>
                            <p class="q-hint text-sm mt-2">Yazıcı uygulamasında yazıcı ekleyin</p>
                        </div>
                    ` : `
                        <div class="q-grid q-grid--2">
                            ${printers.map(printer => `
                                <div class="q-card q-card--pad q-stack q-stack--sm">
                                    <div class="q-toolbar" style="align-items:flex-start;">
                                        <div class="flex-1">
                                            <h3 class="font-bold">${escapeHtml(printer.name || printer.printer_name || 'İsimsiz Yazıcı')}</h3>
                                            <p class="q-hint text-sm">${escapeHtml(printer.model || printer.printer_model || 'Model bilinmiyor')}</p>
                                        </div>
                                        <span class="q-badge ${printer.status === 'online' ? 'q-badge--success' : ''}">${printer.status === 'online' ? 'Çevrimiçi' : 'Çevrimdışı'}</span>
                                    </div>
                                    <div class="q-stack q-stack--xs q-hint text-sm">
                                        ${printer.location ? `<div>${escapeHtml(printer.location)}</div>` : ''}
                                        ${printer.assigned_screen ? `<div style="color:var(--color-brand-accent-hover);font-weight:600;">${escapeHtml(printer.assigned_screen)}</div>` : ''}
                                    </div>
                                    <div class="q-toolbar gap-2">
                                        <button type="button" onclick="editPrinter('${printer.printer_id || printer.id}', '${escapeHtml(printer.name || printer.printer_name)}', '${printer.assigned_screen_id || ''}')" class="q-btn q-btn--primary flex-1">Düzenle</button>
                                        <button type="button" onclick="testPrint('${printer.printer_id || printer.id}')" class="q-btn q-btn--secondary">Test</button>
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    `}
                </div>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', modalHtml);
}

function closePrintersModal() {
    const modal = document.getElementById('printersModal');
    if (modal) {
        modal.remove();
    }
}

// Edit printer function
async function editPrinter(printerId, printerName, assignedScreenId) {
    // Load preparation screens first
    try {
        const response = await fetch(`<?php echo BASE_URL; ?>/api/business/preparation-screens`, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': window.CSRF_TOKEN || ''
            },
            credentials: 'same-origin'
        });
        
        if (!response.ok) {
            throw new Error('Hazırlık ekranları yüklenemedi');
        }
        
        const data = await response.json();
        const screens = data.data || data.screens || [];
        
        showEditPrinterModal(printerId, printerName, assignedScreenId, screens);
    } catch (error) {
        console.error('Error loading screens:', error);
        if (window.NotificationManager) {
            window.NotificationManager.error('Hazırlık ekranları yüklenemedi');
        }
    }
}

function showEditPrinterModal(printerId, printerName, assignedScreenId, screens) {
    const modalHtml = `
        <div id="editPrinterModal" class="q-modal-backdrop" onclick="if(event.target.id === 'editPrinterModal') closeEditPrinterModal()">
            <div class="q-modal-backdrop__scrim" onclick="closeEditPrinterModal()"></div>
            <div class="q-modal" onclick="event.stopPropagation()">
                <div class="q-modal__header">
                    <h2 class="q-modal__title">Yazıcı Düzenle</h2>
                    <button type="button" onclick="closeEditPrinterModal()" class="q-btn q-btn--ghost q-btn--icon" aria-label="Kapat">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                <form id="editPrinterForm" class="q-modal__body q-stack q-stack--md">
                    <input type="hidden" id="editPrinterId" value="${printerId}">
                    <div class="q-field">
                        <label class="q-label" for="editPrinterName">Yazıcı Adı</label>
                        <input type="text" id="editPrinterName" value="${escapeHtml(printerName)}" class="q-input"/>
                    </div>
                    <div class="q-field">
                        <label class="q-label" for="editPrinterScreen">Hazırlık Ekranı</label>
                        <select id="editPrinterScreen" class="q-input">
                            <option value="">Seçiniz...</option>
                            ${screens.map(screen => `
                                <option value="${screen.screen_id || screen.id}" ${assignedScreenId == (screen.screen_id || screen.id) ? 'selected' : ''}>
                                    ${escapeHtml(screen.screen_name || screen.name)}
                                </option>
                            `).join('')}
                        </select>
                        <p class="q-hint mt-1">Bu yazıcı seçilen hazırlık ekranına atanacak (Mutfak, Bar, Nargile vb.)</p>
                    </div>
                    <div class="q-modal__footer q-toolbar">
                        <button type="button" onclick="closeEditPrinterModal()" class="q-btn q-btn--ghost">İptal</button>
                        <button type="submit" class="q-btn q-btn--primary">Kaydet</button>
                    </div>
                </form>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    
    // Form submit handler
    document.getElementById('editPrinterForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const printerId = document.getElementById('editPrinterId').value;
        const printerName = document.getElementById('editPrinterName').value.trim();
        const screenId = document.getElementById('editPrinterScreen').value;
        
        if (!printerName) {
            if (window.NotificationManager) {
                window.NotificationManager.warning('Yazıcı adı gereklidir');
            }
            return;
        }
        
        try {
            const response = await fetch(`<?php echo BASE_URL; ?>/api/business/printer/update`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': window.CSRF_TOKEN || ''
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    printer_id: printerId,
                    printer_name: printerName,
                    screen_id: screenId
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                if (window.NotificationManager) {
                    window.NotificationManager.success('Yazıcı güncellendi!');
                }
                closeEditPrinterModal();
                closePrintersModal();
                location.reload();
            } else {
                if (window.NotificationManager) {
                    window.NotificationManager.error(data.error || 'Güncelleme başarısız');
                }
            }
        } catch (error) {
            console.error('Error updating printer:', error);
            if (window.NotificationManager) {
                window.NotificationManager.error('Bağlantı hatası');
            }
        }
    });
}

function closeEditPrinterModal() {
    const modal = document.getElementById('editPrinterModal');
    if (modal) {
        modal.remove();
    }
}

// Test print function
async function testPrint(printerId) {
    try {
        if (window.NotificationManager) {
            window.NotificationManager.info('Test çıktısı gönderiliyor...');
        }
        
        const response = await fetch(`<?php echo BASE_URL; ?>/api/business/printer/test`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': window.CSRF_TOKEN || ''
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                printer_id: printerId
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            if (window.NotificationManager) {
                window.NotificationManager.success('Test çıktısı gönderildi!');
            }
        } else {
            if (window.NotificationManager) {
                window.NotificationManager.error(data.error || 'Test başarısız');
            }
        }
    } catch (error) {
        console.error('Error testing printer:', error);
        if (window.NotificationManager) {
            window.NotificationManager.error('Bağlantı hatası');
        }
    }
}

<?php endif; ?>
</script>
