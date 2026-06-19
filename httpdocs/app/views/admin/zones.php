<?php
require_once __DIR__ . '/../../helpers/translations.php';
if (!function_exists('getAdminUrl')) {
    require_once __DIR__ . '/../../helpers/url_helper.php';
}

$title = t('zones.title', 'Bölge Yönetimi') . ' - ' . getAppConfig()->getAppName();
$isSuperAdmin = $is_super_admin ?? false;
$apiPrefix = $isSuperAdmin ? '/api/qodmin' : '/api/business';
// Note: Layout is automatically included by Controller::view() method
// No need for ob_start() or manual layout include
?>

<div class="q-page q-biz-theme animate-slide-up">
<div class="q-container q-stack q-stack--lg">
    <header class="q-page-header">
        <div>
            <p class="q-page-header__eyebrow">Operasyon</p>
            <h1 class="q-page-header__title"><?php echo t('zones.title', 'Bölge Yönetimi'); ?></h1>
            <p class="q-page-header__subtitle">Restoran kat ve bölgelerini yönet</p>
        </div>
        <div class="q-page-header__actions">
            <button type="button" onclick="openAddZoneModal()" class="q-btn q-btn--primary">
                <?php echo icon_plus(['class' => 'w-4 h-4']); ?>
                <span>Bölge Ekle</span>
            </button>
        </div>
    </header>

    <div class="q-grid q-grid--4" id="zonesGrid">
        <!-- Zones will be loaded here -->
    </div>
</div>
</div>

<!-- Add/Edit Zone Modal -->
<div id="zoneModal" class="q-modal-backdrop hidden">
    <div class="q-modal-backdrop__scrim" onclick="closeZoneModal()"></div>
    <div class="q-modal">
        <div class="q-modal__header">
            <h2 class="q-modal__title" id="modalZoneTitle">Bölge Ekle</h2>
            <button type="button" onclick="closeZoneModal()" class="q-btn q-btn--ghost q-btn--icon" aria-label="Kapat">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        <form id="zoneForm" class="q-modal__body q-stack q-stack--md">
            <input type="hidden" id="zoneId">
            <?php echo csrf_field(); ?>
            <div class="q-field">
                <label class="q-label" for="zoneName">Bölge Adı</label>
                <input type="text" id="zoneName" required placeholder="Örn: Teras, Salon, Bahçe..." class="q-input"/>
            </div>
            <div class="q-field">
                <label class="q-label" for="zoneFloor">Kat</label>
                <input type="text" id="zoneFloor" placeholder="Örn: 1. Kat, Zemin Kat (İsteğe bağlı)" class="q-input"/>
            </div>
            <div class="q-field">
                <label class="q-label" for="zoneDescription">Açıklama (İsteğe Bağlı)</label>
                <textarea id="zoneDescription" rows="3" placeholder="Bölge açıklaması..." class="q-input"></textarea>
            </div>
            <div class="q-modal__footer q-toolbar">
                <button type="button" onclick="closeZoneModal()" class="q-btn q-btn--ghost">İptal</button>
                <button type="submit" class="q-btn q-btn--primary">Kaydet</button>
            </div>
        </form>
    </div>
</div>

<!-- Zone Printers Modal -->
<div id="zonePrintersModal" class="q-modal-backdrop hidden">
    <div class="q-modal-backdrop__scrim" onclick="closeZonePrintersModal()"></div>
    <div class="q-modal q-modal--lg">
        <div class="q-modal__header">
            <h2 class="q-modal__title" id="zonePrintersModalTitle">Bölge Yazıcıları</h2>
            <button type="button" onclick="closeZonePrintersModal()" class="q-btn q-btn--ghost q-btn--icon" aria-label="Kapat">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        <div id="zonePrintersContent" class="q-modal__body">
            <div class="q-hint text-center py-8">Yükleniyor...</div>
        </div>
    </div>
</div>

<script>
const baseUrl = <?php echo json_encode(BASE_URL ?? '', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?> || window.BASE_URL || '';
window.BASE_URL = baseUrl;
let zones = [];

async function loadZones() {
    try {
        const apiPrefix = <?php echo json_encode($apiPrefix); ?>;
        const response = await fetch(`${baseUrl}${apiPrefix}/zones`);
        const data = await response.json();
        
        if (data.error) {
            console.error('Error loading zones:', data.error);
            zones = [];
            renderZones();
            return;
        }
        
        zones = Array.isArray(data) ? data : (data.zones || []);
        renderZones();
    } catch (error) {
        console.error('Error loading zones:', error);
        zones = [];
        renderZones();
    }
}

function renderZones() {
    const grid = document.getElementById('zonesGrid');
    
    if (zones.length === 0) {
        grid.innerHTML = `
            <div class="col-span-full text-center py-20">
                <p class="text-slate-400 text-xl font-bold mb-4">Henüz bölge eklenmemiş</p>
                <p class="text-slate-300 text-sm">İlk bölgenizi ekleyin</p>
            </div>
        `;
        return;
    }
    
    // Sort zones naturally (bahçe1, bahçe2, bahçe10)
    const sortedZones = [...zones].sort((a, b) => {
        const nameA = String(a.name || '').toUpperCase();
        const nameB = String(b.name || '').toUpperCase();
        
        // Extract numeric and non-numeric parts
        const regex = /(\d+|\D+)/g;
        const partsA = nameA.match(regex) || [];
        const partsB = nameB.match(regex) || [];
        
        const minLength = Math.min(partsA.length, partsB.length);
        
        for (let i = 0; i < minLength; i++) {
            const partA = partsA[i];
            const partB = partsB[i];
            
            const numA = parseInt(partA, 10);
            const numB = parseInt(partB, 10);
            
            // If both are numbers, compare numerically
            if (!isNaN(numA) && !isNaN(numB)) {
                if (numA !== numB) {
                    return numA - numB;
                }
            } else {
                // Compare as strings
                if (partA !== partB) {
                    return partA.localeCompare(partB);
                }
            }
        }
        
        return partsA.length - partsB.length;
    });
    
    grid.innerHTML = sortedZones.map(zone => {
        const tableCount = zone.table_count || 0;
        const zoneId = zone.zone_id || zone.id;
        
        return `
            <div class="q-card q-card--pad q-card--hover" style="border-left:4px solid var(--color-brand-accent);">
                <div class="mb-4">
                    <h3 class="q-card__title mb-2">${escapeHtml(zone.name || 'İsimsiz Bölge')}</h3>
                    <div class="q-stack q-stack--xs text-sm q-hint mb-3">
                        ${zone.floor ? `<div class="font-bold"><span>Kat:</span> ${escapeHtml(zone.floor)}</div>` : '<div class="italic">Kat bilgisi yok</div>'}
                        ${zone.description ? `<div class="mt-1"><span>Açıklama:</span> ${escapeHtml(zone.description)}</div>` : '<div class="italic text-xs">Açıklama yok</div>'}
                        <div class="text-xs font-bold mt-2">${tableCount} Masa</div>
                    </div>
                </div>
                <div class="mb-3">
                    <div class="q-hint text-xs font-bold mb-1">Yazıcılar:</div>
                    <div id="zone-printers-${zoneId}" class="text-xs q-hint min-h-[20px]">Yükleniyor...</div>
                </div>
                <div class="q-toolbar q-toolbar--wrap">
                    <button type="button" onclick="openZonePrintersModal('${zoneId}')" class="q-btn q-btn--ghost q-btn--sm">🖨️ Yazıcılar</button>
                    <button type="button" onclick="openEditZoneModal('${zoneId}')" class="q-btn q-btn--ink q-btn--sm">Düzenle</button>
                    <button type="button" onclick="deleteZone('${zoneId}')" class="q-btn q-btn--danger q-btn--sm">Sil</button>
                </div>
            </div>
        `;
    }).join('');
    
    // Load printers for each zone
    sortedZones.forEach(zone => {
        const zoneId = zone.zone_id || zone.id;
        loadZonePrinters(zoneId);
    });
}

// escapeHtml is now available globally from utils.js

function openAddZoneModal() {
    document.getElementById('modalZoneTitle').textContent = 'Bölge Ekle';
    document.getElementById('zoneForm').reset();
    document.getElementById('zoneId').value = '';
    document.getElementById('zoneFloor').value = '';
    document.getElementById('zoneModal').classList.remove('hidden');
}

function openEditZoneModal(zoneId) {
    const zone = zones.find(z => (z.id || z.zone_id) === zoneId);
    if (!zone) return;
    
    document.getElementById('modalZoneTitle').textContent = 'Bölge Düzenle';
    document.getElementById('zoneId').value = zone.id || zone.zone_id;
    document.getElementById('zoneName').value = zone.name;
    document.getElementById('zoneFloor').value = zone.floor || '';
    document.getElementById('zoneDescription').value = zone.description || '';
    document.getElementById('zoneModal').classList.remove('hidden');
}

function closeZoneModal() {
    document.getElementById('zoneModal').classList.add('hidden');
}

async function deleteZone(zoneId) {
    if (!window.NotificationManager) {
        console.error('NotificationManager is not available');
        return;
    }
    
    const confirmed = await window.NotificationManager.confirm('<?php echo t('notifications.zoneDeleteConfirm'); ?>', '<?php echo t('notifications.zoneDelete'); ?>');
    if (!confirmed) {
        return;
    }
    
    try {
        const csrfToken = window.CSRF_TOKEN || (typeof csrf_token !== 'undefined' ? csrf_token : '');
        const response = await fetch(`${baseUrl}/api/qodmin/zones/${zoneId}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-Token': csrfToken
            }
        });
        const data = await response.json();
        
        if (data.success) {
            if (window.NotificationManager) {
                window.NotificationManager.success('Bölge başarıyla silindi');
            }
            loadZones();
        } else {
            if (window.NotificationManager) {
                window.NotificationManager.error('Hata: ' + (data.error || 'Bölge silinemedi'));
            }
        }
    } catch (error) {
        console.error('Error:', error);
        if (window.NotificationManager) {
            window.NotificationManager.error('Bağlantı hatası');
        }
    }
}

document.getElementById('zoneForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const id = document.getElementById('zoneId').value;
    const name = document.getElementById('zoneName').value.trim();
    const floor = document.getElementById('zoneFloor').value.trim();
    const description = document.getElementById('zoneDescription').value.trim();
    
    if (!name) {
        if (window.NotificationManager) {
            window.NotificationManager.warning('Bölge adı gereklidir');
        }
        return;
    }
    
    const zoneData = {
        name: name,
        floor: floor,
        description: description
    };
    
    const apiPrefix = <?php echo json_encode($apiPrefix); ?>;
    const url = id ? `${baseUrl}${apiPrefix}/zones/${id}` : `${baseUrl}${apiPrefix}/zones`;
    const method = id ? 'PUT' : 'POST';
    
    try {
        const csrfToken = window.CSRF_TOKEN || (typeof csrf_token !== 'undefined' ? csrf_token : '');
        const response = await fetch(url, {
            method: method,
            headers: { 
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify(zoneData)
        });
        const data = await response.json();
        
        if (data.success) {
            if (window.NotificationManager) {
                window.NotificationManager.success(id ? 'Bölge güncellendi' : 'Bölge eklendi');
            }
            loadZones();
            closeZoneModal();
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

let currentZoneIdForPrinters = null;
let allPrinters = [];

async function loadZonePrinters(zoneId) {
    const container = document.getElementById(`zone-printers-${zoneId}`);
    if (!container) return;
    
    try {
        const apiPrefix = <?php echo json_encode($apiPrefix); ?>;
        const response = await fetch(`${baseUrl}${apiPrefix}/zone/printers?zone_id=${zoneId}`);
        const data = await response.json();
        
        if (data.success && data.printers && data.printers.length > 0) {
            const printers = data.printers;
            container.innerHTML = printers.map(p => {
                const printerName = p.printer_name || p.name || 'Yazıcı';
                const printerLocation = p.printer_location || p.location || '';
                return `<div class="text-xs text-slate-600">• ${escapeHtml(printerName)}${printerLocation ? ' (' + escapeHtml(printerLocation) + ')' : ''}</div>`;
            }).join('');
        } else {
            container.innerHTML = '<div class="text-xs text-slate-400 italic">Yazıcı atanmamış</div>';
        }
    } catch (error) {
        console.error('Error loading zone printers:', error);
        container.innerHTML = '<div class="text-xs text-red-400">Yüklenemedi</div>';
    }
}

async function loadAllPrinters() {
    try {
        const response = await fetch(`${baseUrl}/api/qodmin/printer/all`);
        const data = await response.json();
        
        if (Array.isArray(data)) {
            allPrinters = data;
        } else if (data.printers && Array.isArray(data.printers)) {
            allPrinters = data.printers;
        } else {
            allPrinters = [];
        }
        return allPrinters;
    } catch (error) {
        console.error('Error loading printers:', error);
        allPrinters = [];
        return [];
    }
}

async function openZonePrintersModal(zoneId) {
    currentZoneIdForPrinters = zoneId;
    const zone = zones.find(z => (z.id || z.zone_id) === zoneId);
    const zoneName = zone ? (zone.name || 'Bölge') : 'Bölge';
    
    document.getElementById('zonePrintersModalTitle').textContent = `${zoneName} - Yazıcılar`;
    document.getElementById('zonePrintersContent').innerHTML = '<div class="text-center py-8 text-slate-400">Yükleniyor...</div>';
    document.getElementById('zonePrintersModal').classList.remove('hidden');
    
    // Load all printers and assigned printers
    const [allPrintersList, assignedPrinters] = await Promise.all([
        loadAllPrinters(),
        fetch(`${baseUrl}/api/qodmin/zone/printers?zone_id=${zoneId}`).then(r => r.json()).then(d => d.success && d.printers ? d.printers : []).catch(() => [])
    ]);
    
    const assignedPrinterIds = assignedPrinters.map(p => p.printer_id || p.id).filter(id => id);
    
    const content = document.getElementById('zonePrintersContent');
    if (allPrintersList.length === 0) {
        content.innerHTML = `
            <div class="text-center py-8">
                <p class="text-slate-400 mb-4">Henüz yazıcı eklenmemiş</p>
                const adminPrefix = <?php echo json_encode($isSuperAdmin ? '/qodmin' : '/business'); ?>;
                <a href="${baseUrl}${adminPrefix}/printers" class="inline-block px-4 py-2 bg-indigo-500 text-white rounded-lg font-bold text-sm hover:bg-indigo-700 transition-all">
                    Yazıcı Ekle
                </a>
            </div>
        `;
        return;
    }
    
    content.innerHTML = `
        <div class="space-y-3">
            ${allPrintersList.map(printer => {
                const printerId = printer.printer_id || printer.id;
                const printerName = printer.printer_name || printer.name || 'Yazıcı';
                const printerLocation = printer.printer_location || printer.location || '';
                const isAssigned = assignedPrinterIds.includes(printerId);
                
                return `
                    <label class="flex items-center gap-3 p-3 bg-slate-50 rounded-lg cursor-pointer hover:bg-slate-100 transition-colors">
                        <input type="checkbox" 
                               value="${escapeHtml(printerId)}" 
                               ${isAssigned ? 'checked' : ''}
                               onchange="toggleZonePrinter('${escapeHtml(printerId)}', this.checked)"
                               class="w-4 h-4 sm:w-5 sm:h-5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                        <div class="flex-1 min-w-0">
                            <div class="font-bold text-sm sm:text-base text-slate-900">${escapeHtml(printerName)}</div>
                            ${printerLocation ? `<div class="text-xs text-slate-500">${escapeHtml(printerLocation)}</div>` : ''}
                        </div>
                    </label>
                `;
            }).join('')}
        </div>
    `;
}

async function toggleZonePrinter(printerId, assign) {
    if (!currentZoneIdForPrinters) return;
    
    const csrfToken = window.CSRF_TOKEN || (typeof csrf_token !== 'undefined' ? csrf_token : '');
    
    try {
        const apiPrefix = <?php echo json_encode($apiPrefix); ?>;
        const url = assign ? `${baseUrl}${apiPrefix}/printer/assign-zone` : `${baseUrl}${apiPrefix}/printer/remove-zone`;
        const method = 'POST';
        const body = JSON.stringify({
            printer_id: printerId,
            zone_id: currentZoneIdForPrinters,
            priority: 1
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
            // Reload zone printers display
            loadZonePrinters(currentZoneIdForPrinters);
            
            if (window.NotificationManager) {
                window.NotificationManager.success(assign ? 'Yazıcı atandı' : 'Yazıcı kaldırıldı');
            }
        } else {
            if (window.NotificationManager) {
                window.NotificationManager.error('Hata: ' + (data.error || 'İşlem başarısız'));
            }
        }
    } catch (error) {
        console.error('Error toggling zone printer:', error);
        if (window.NotificationManager) {
            window.NotificationManager.error('Bağlantı hatası');
        }
    }
}

function closeZonePrintersModal() {
    document.getElementById('zonePrintersModal').classList.add('hidden');
    currentZoneIdForPrinters = null;
}

// Initialize
loadZones();
</script>

<?php
// Content is automatically captured by Controller::view() method
// Layout is automatically included by Controller::view() method
?>

