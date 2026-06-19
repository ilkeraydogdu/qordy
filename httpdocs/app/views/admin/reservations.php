<?php
/**
 * Reservations Management Page
 * Modern, minimal design with fixed FOUC issues
 */

// Suppress any warnings/notices that might break JavaScript
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/../../helpers/translations.php';
require_once __DIR__ . '/../../helpers/role_helpers.php';
require_once __DIR__ . '/../components/datatable.php';

// Ensure variables are arrays
$reservations = isset($reservations) && is_array($reservations) ? $reservations : [];
$tables = isset($tables) && is_array($tables) ? $tables : [];
$baseUrl = defined('BASE_URL') ? BASE_URL : '';
$translations = getTranslations(getCurrentLanguage());
if (!function_exists('getAdminUrl')) {
    require_once __DIR__ . '/../../helpers/url_helper.php';
}
$isSuperAdmin = $is_super_admin ?? false;
$adminPrefix = $isSuperAdmin ? '/qodmin' : '/business';

// Prepare reservations data with table names
$reservationsData = [];

if (!empty($reservations) && is_array($reservations)) {
    foreach ($reservations as $res) {
        if (!is_array($res)) continue;
        
        $tableId = $res['table_id'] ?? '';
        $tableName = $res['table_name'] ?? null;
        
        // Find table name if not included in JOIN result
        if (empty($tableName) && !empty($tableId) && !empty($tables) && is_array($tables)) {
            foreach ($tables as $table) {
                if (is_array($table) && ($table['table_id'] ?? '') === $tableId) {
                    $tableName = $table['name'] ?? 'Belirlenmedi';
                    break;
                }
            }
        }
        
        if (empty($tableName)) {
            $tableName = 'Belirlenmedi';
        }
        
        $reservationsData[] = [
            'reservation_id' => $res['reservation_id'] ?? '',
            'customer_name' => $res['customer_name'] ?? t('common.customer', 'Müşteri'),
            'contact' => $res['contact'] ?? '',
            'customer_email' => $res['customer_email'] ?? '',
            'date' => $res['date'] ?? '',
            'time' => $res['time'] ?? '',
            'guests' => $res['guests'] ?? $res['guest_count'] ?? 1,
            'table_id' => $tableId,
            'table_name' => $tableName,
            'status' => $res['status'] ?? 'PENDING',
            'notes' => $res['notes'] ?? '',
            'special_requests' => $res['special_requests'] ?? '',
            'created_at' => $res['created_at'] ?? ''
        ];
    }
}

// Status options for filter
$statusOptions = [
    'PENDING' => 'Beklemede',
    'CONFIRMED' => 'Onaylandı',
    'CANCELLED' => 'İptal',
    'COMPLETED' => 'Tamamlandı',
    'NO_SHOW' => 'Gelmedi'
];
?>

<!-- CRITICAL: CSS styles MUST be at the top to prevent FOUC (Flash of Unstyled Content) -->

<!-- Helper functions defined ONCE at the very beginning -->
<script>
// CRITICAL: Define helper functions FIRST and ONLY ONCE
window.formatReservationDate = function(dateString) {
    if (!dateString) return '';
    try {
        const date = new Date(dateString + 'T00:00:00');
        if (isNaN(date.getTime())) return dateString;
        const day = String(date.getDate()).padStart(2, '0');
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const year = date.getFullYear();
        return `${day}.${month}.${year}`;
    } catch (e) {
        return dateString;
    }
};

window.escapeHtml = function(text) {
    if (text === null || text === undefined) return '';
    const div = document.createElement('div');
    div.textContent = String(text);
    return div.innerHTML;
};

window.getStatusLabel = function(status) {
    const statusLabels = {
        'PENDING': 'Beklemede',
        'CONFIRMED': 'Onaylandı',
        'CANCELLED': 'İptal',
        'COMPLETED': 'Tamamlandı',
        'NO_SHOW': 'Gelmedi'
    };
    return statusLabels[status] || status || 'Beklemede';
};

// Also assign to local scope for backwards compatibility
const formatReservationDate = window.formatReservationDate;
const escapeHtml = window.escapeHtml;
const getStatusLabel = window.getStatusLabel;
</script>

<div class="q-page q-biz-theme animate-slide-up">
  <div class="q-container q-stack q-stack--lg">
    <?php if ($isSuperAdmin): ?>
    <!-- SUPER ADMIN: Business Selection -->
    <div id="business-selection-view">
        <div class="mb-8">
            <h1 class="text-3xl sm:text-4xl font-black text-slate-900 tracking-tight">Rezervasyonlar</h1>
            <p class="text-slate-500 mt-2 font-medium">İşletme seçerek rezervasyonları yönetin</p>
        </div>
        <div id="business-grid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6"></div>
    </div>
    
    <!-- Reservation Management View -->
    <div id="reservation-management-view" class="hidden">
    <?php endif; ?>
    
    <!-- Success/Error Messages -->
    <?php if (isset($_SESSION['success'])): ?>
        <div id="success-message" class="mb-6 p-4 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-2xl font-semibold flex items-center gap-3 animate-slide-up">
            <svg class="w-5 h-5 text-emerald-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div id="error-message" class="mb-6 p-4 bg-red-50 border border-red-200 text-red-700 rounded-2xl font-semibold flex items-center gap-3 animate-slide-up">
            <svg class="w-5 h-5 text-red-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>
    
    <!-- Header Section -->
    <header class="q-page-header">
        <div class="q-toolbar" style="gap:var(--space-3);">
            <?php if ($isSuperAdmin): ?>
            <button type="button" onclick="backToBusinessSelection()" class="q-icon-btn" aria-label="İşletme seçimine dön">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
            </button>
            <?php endif; ?>
            <div>
                <p class="q-page-header__eyebrow"><?php echo t('reservations.title', 'Rezervasyonlar'); ?></p>
                <h1 class="q-page-header__title">
                    <?php if ($isSuperAdmin): ?>
                    <span id="selected-business-name"></span>
                    <?php else: ?>
                    <?php echo t('reservations.title', 'Rezervasyonlar'); ?>
                    <?php endif; ?>
                </h1>
                <p class="q-page-header__subtitle"><?php echo t('reservations.subtitle', 'Rezervasyonları görüntüleyin ve yönetin'); ?></p>
            </div>
        </div>
        <div class="q-page-header__actions">
            <?php if (hasPermissionForRole('queue.view') || hasPermissionForRole('reservations.view')): ?>
            <a href="<?php echo $adminPrefix; ?>/queue" class="q-btn q-btn--ghost q-btn--sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87M12 12a4 4 0 100-8 4 4 0 000 8zm8-4a2 2 0 11-4 0 2 2 0 014 0zM8 8a2 2 0 11-4 0 2 2 0 014 0z"></path>
                </svg>
                <?php echo t('queue.title', 'Sıra (Kuyruk)'); ?>
            </a>
            <?php endif; ?>
            <?php if (hasPermissionForRole('reservations.create')): ?>
            <button type="button" onclick="openReservationModal()" class="q-btn q-btn--primary q-btn--sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                <?php echo t('reservations.addNew', 'Yeni Rezervasyon'); ?>
            </button>
            <?php endif; ?>
        </div>
    </header>
    
    <!-- DataTable -->
    <div class="reservation-card">
        <?php
        renderDataTable([
            'id' => 'reservations-table',
            'columns' => [
                ['label' => 'Müşteri', 'field' => 'customer_name'],
                ['label' => 'İletişim', 'field' => 'contact'],
                ['label' => 'Tarih', 'field' => 'date', 'render' => '${formatReservationDate(item.date)}'],
                ['label' => 'Saat', 'field' => 'time'],
                ['label' => 'Kişi', 'field' => 'guests', 'render' => '${item.guests} kişi'],
                ['label' => 'Masa', 'field' => 'table_name', 'render' => '${item.table_name || "Belirlenmedi"}'],
                ['label' => 'Durum', 'field' => 'status', 'render' => '<span class="status-badge cursor-pointer" data-status="${item.status}" onclick="changeReservationStatus(\'${item.reservation_id}\', \'${item.status}\')" title="Durumu değiştirmek için tıklayın">${getStatusLabel(item.status)}</span>']
            ],
            'data' => $reservationsData,
            'filters' => [
                'status' => [
                    'type' => 'select',
                    'label' => 'Durum',
                    'field' => 'status',
                    'options' => $statusOptions
                ],
                'date' => [
                    'type' => 'date',
                    'label' => 'Tarih',
                    'field' => 'date'
                ],
                'daterange' => [
                    'type' => 'daterange',
                    'label' => 'Tarih Aralığı',
                    'field' => 'date'
                ]
            ],
            'search' => true,
            'pagination' => true,
            'perPage' => 10,
            'actions' => [
                [
                    'type' => 'button',
                    'label' => 'Görüntüle',
                    'onClick' => 'viewReservation("${item.reservation_id}")',
                    'class' => 'px-4 py-2 bg-slate-100 text-slate-700 rounded-xl text-xs font-bold hover:bg-slate-200 transition-all'
                ],
                [
                    'type' => 'button',
                    'label' => 'Düzenle',
                    'onClick' => 'editReservation("${item.reservation_id}")',
                    'class' => 'px-4 py-2 bg-blue-50 text-blue-700 rounded-xl text-xs font-bold hover:bg-blue-100 transition-all'
                ],
                [
                    'type' => 'button',
                    'label' => 'Sil',
                    'onClick' => 'deleteReservation("${item.reservation_id}")',
                    'class' => 'px-4 py-2 bg-red-50 text-red-700 rounded-xl text-xs font-bold hover:bg-red-100 transition-all'
                ]
            ],
            'emptyMessage' => t('reservations.noReservations', 'Henüz rezervasyon bulunmuyor')
        ]);
        ?>
    </div>
    
    <?php if ($isSuperAdmin): ?>
    </div> <!-- Close reservation-management-view -->
    <?php endif; ?>
  </div>
</div>

<!-- Reservation Modal -->
<div id="reservation-modal" class="fixed inset-0 z-[200] hidden items-center justify-center p-4">
    <div class="modal-overlay absolute inset-0" onclick="closeReservationModal()"></div>
    <div class="modal-content relative w-full max-w-2xl p-6 sm:p-8 animate-slide-up max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-xl sm:text-2xl font-black text-slate-900" id="modal-title">
                <?php echo t('reservations.addNew', 'Yeni Rezervasyon'); ?>
            </h2>
            <button onclick="closeReservationModal()" class="p-2 hover:bg-slate-100 rounded-xl transition-all">
                <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        
        <form id="reservation-form" method="POST" action="<?php echo getAdminUrl('reservations/add'); ?>" class="space-y-5">
            <?php echo csrf_field(); ?>
            <input type="hidden" id="reservation-id" name="reservation_id">
            <input type="hidden" id="form-status" name="status" value="PENDING">
            
            <!-- Customer Info -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="q-field__label"><?php echo t('reservations.customer', 'Müşteri Adı'); ?> *</label>
                    <input type="text" id="form-customer-name" name="customer_name" required
                           class="q-input" placeholder="Müşteri adını girin"/>
                </div>
                <div>
                    <label class="q-field__label"><?php echo t('reservations.contact', 'İletişim'); ?> *</label>
                    <input type="text" id="form-contact" name="contact" required
                           class="q-input" placeholder="Telefon numarası"/>
                </div>
            </div>
            
            <!-- Email -->
            <div>
                <label class="q-field__label"><?php echo t('reservations.email', 'E-posta'); ?> <span class="text-slate-400 normal-case">(Opsiyonel)</span></label>
                <input type="email" id="form-email" name="customer_email"
                       class="q-input" placeholder="ornek@email.com"/>
            </div>
            
            <!-- Date and Time -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="q-field__label"><?php echo t('reservations.date', 'Tarih'); ?> *</label>
                    <input type="date" id="form-date" name="date" value="<?php echo date('Y-m-d'); ?>" 
                           min="<?php echo date('Y-m-d'); ?>" 
                           max="<?php echo date('Y-m-d', strtotime('+1 year')); ?>" 
                           required class="q-input"/>
                </div>
                <div>
                    <label class="q-field__label"><?php echo t('reservations.time', 'Saat'); ?> *</label>
                    <input type="time" id="form-time" name="time" value="12:00" required class="q-input"/>
                </div>
            </div>
            
            <!-- Guests and Table -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="q-field__label"><?php echo t('reservations.guestCount', 'Kişi Sayısı'); ?> *</label>
                    <input type="number" id="form-guests" name="guests" value="1" min="1" max="50" required class="q-input"/>
                </div>
                <div>
                    <label class="q-field__label"><?php echo t('reservations.table', 'Masa'); ?></label>
                    <select id="form-table" name="table_id" class="q-input">
                        <option value=""><?php echo t('reservations.selectTable', 'Masa seçin'); ?></option>
                        <?php foreach ($tables as $table): 
                            $tableId = $table['table_id'] ?? '';
                            $tableName = $table['name'] ?? '';
                            $tableZone = $table['zone'] ?? '';
                            $tableCapacity = intval($table['capacity'] ?? 0);
                        ?>
                            <option value="<?php echo htmlspecialchars($tableId); ?>" data-capacity="<?php echo $tableCapacity; ?>">
                                <?php echo htmlspecialchars($tableName); ?> 
                                <?php if (!empty($tableZone)): ?>(<?php echo htmlspecialchars($tableZone); ?>)<?php endif; ?>
                                - <?php echo $tableCapacity; ?> kişi
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="text-xs text-slate-500 mt-2 ml-1" id="table-capacity-info"></p>
                </div>
            </div>
            
            <!-- Form Actions -->
            <div class="flex gap-3 pt-4">
                <button type="button" onclick="closeReservationModal()" class="q-btn q-btn--secondary flex-1 text-sm uppercase tracking-wider">
                    İptal
                </button>
                <button type="submit" class="q-btn q-btn--primary flex-1 text-sm uppercase tracking-wider">
                    <span id="submit-text">Kaydet</span>
                    <span id="submit-loading" class="hidden">Kaydediliyor...</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Reservation Detail Modal -->
<div id="reservation-detail-modal" class="fixed inset-0 z-[200] hidden items-center justify-center p-4">
    <div class="modal-overlay absolute inset-0" onclick="closeReservationDetailModal()"></div>
    <div class="modal-content relative w-full max-w-xl p-6 sm:p-8 animate-slide-up max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-xl sm:text-2xl font-black text-slate-900">Rezervasyon Detayları</h2>
            <button onclick="closeReservationDetailModal()" class="p-2 hover:bg-slate-100 rounded-xl transition-all">
                <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        <div id="reservation-detail-content" class="space-y-4">
            <!-- Content loaded dynamically -->
        </div>
    </div>
</div>

<?php
// Prepare JavaScript variables safely
$jsBaseUrl = json_encode($baseUrl, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '""';
$jsReservationsData = json_encode($reservationsData, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '[]';
$jsStatusOptions = json_encode($statusOptions, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '{}';
$jsTodayDate = json_encode(date('Y-m-d'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '""';
$jsAddNewText = json_encode(t('reservations.addNew', 'Yeni Rezervasyon'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '"Yeni Rezervasyon"';
$jsAdminPrefix = json_encode($adminPrefix, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '"/business"';
?>

<script>
// JavaScript variables
const baseUrl = <?php echo $jsBaseUrl; ?>;
const reservationsData = <?php echo $jsReservationsData; ?>;
const statusOptions = <?php echo $jsStatusOptions; ?>;
const jsTodayDate = <?php echo $jsTodayDate; ?>;
const jsAddNewText = <?php echo $jsAddNewText; ?>;
const adminPrefix = <?php echo $jsAdminPrefix; ?>;

// Modal functions
function openReservationModal(reservationId = null) {
    const modal = document.getElementById('reservation-modal');
    const form = document.getElementById('reservation-form');
    const modalTitle = document.getElementById('modal-title');
    const submitText = document.getElementById('submit-text');
    const submitLoading = document.getElementById('submit-loading');
    
    if (!modal) return;
    
    if (reservationId) {
        // Edit mode
        const reservation = reservationsData.find(r => r.reservation_id === reservationId);
        if (reservation) {
            document.getElementById('reservation-id').value = reservation.reservation_id || '';
            document.getElementById('form-customer-name').value = reservation.customer_name || '';
            document.getElementById('form-contact').value = reservation.contact || '';
            document.getElementById('form-email').value = reservation.customer_email || '';
            document.getElementById('form-date').value = reservation.date || '';
            document.getElementById('form-time').value = reservation.time || '';
            document.getElementById('form-guests').value = reservation.guests || 1;
            document.getElementById('form-table').value = reservation.table_id || '';
            document.getElementById('form-status').value = reservation.status || 'PENDING';
            
            if (modalTitle) modalTitle.textContent = 'Rezervasyon Düzenle';
            if (submitText) submitText.textContent = 'Güncelle';
            if (form) form.action = `${baseUrl}${adminPrefix}/reservations/update/${reservationId}`;
        }
    } else {
        // Add mode
        if (form) form.reset();
        document.getElementById('reservation-id').value = '';
        document.getElementById('form-date').value = jsTodayDate;
        document.getElementById('form-time').value = '12:00';
        document.getElementById('form-guests').value = 1;
        document.getElementById('form-status').value = 'PENDING';
        
        if (modalTitle) modalTitle.textContent = jsAddNewText;
        if (submitText) submitText.textContent = 'Kaydet';
        if (form) form.action = `${baseUrl}${adminPrefix}/reservations/add`;
    }
    
    // Reset loading state
    if (submitText) submitText.classList.remove('hidden');
    if (submitLoading) submitLoading.classList.add('hidden');
    if (form) {
        const submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) submitBtn.disabled = false;
    }
    
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    document.body.style.overflow = 'hidden';
    updateTableCapacityInfo();
}

function closeReservationModal() {
    const modal = document.getElementById('reservation-modal');
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        document.body.style.overflow = '';
    }
    const form = document.getElementById('reservation-form');
    if (form) {
        form.reset();
        form.action = `${baseUrl}${adminPrefix}/reservations/add`;
    }
}

function viewReservation(reservationId) {
    const reservation = reservationsData.find(r => r.reservation_id === reservationId);
    if (!reservation) {
        showError('Rezervasyon bulunamadı');
        return;
    }
    
    const modal = document.getElementById('reservation-detail-modal');
    const content = document.getElementById('reservation-detail-content');
    
    const formattedDate = window.formatReservationDate(reservation.date);
    const reservationDateTime = new Date(reservation.date + 'T' + reservation.time);
    const now = new Date();
    const isUpcoming = reservationDateTime > now;
    const timeUntil = isUpcoming ? getTimeUntil(reservationDateTime) : null;
    
    content.innerHTML = `
        <div class="space-y-4">
            ${isUpcoming && timeUntil ? `
            <div class="p-4 bg-gradient-to-r from-blue-50 to-indigo-50 rounded-2xl border border-blue-100">
                <div class="info-box-label text-blue-600">Rezervasyon Süresi</div>
                <div class="info-box-value text-blue-900">${timeUntil}</div>
            </div>
            ` : ''}
            <div class="grid grid-cols-2 gap-3">
                <div class="info-box">
                    <div class="info-box-label">Müşteri</div>
                    <div class="info-box-value">${window.escapeHtml(reservation.customer_name)}</div>
                </div>
                <div class="info-box">
                    <div class="info-box-label">İletişim</div>
                    <div class="info-box-value">${window.escapeHtml(reservation.contact)}</div>
                </div>
            </div>
            ${reservation.customer_email ? `
            <div class="info-box">
                <div class="info-box-label">E-posta</div>
                <div class="info-box-value">
                    <a href="mailto:${window.escapeHtml(reservation.customer_email)}" class="text-blue-600 hover:underline">
                        ${window.escapeHtml(reservation.customer_email)}
                    </a>
                </div>
            </div>
            ` : ''}
            <div class="grid grid-cols-2 gap-3">
                <div class="info-box">
                    <div class="info-box-label">Tarih</div>
                    <div class="info-box-value">${formattedDate}</div>
                </div>
                <div class="info-box">
                    <div class="info-box-label">Saat</div>
                    <div class="info-box-value">${window.escapeHtml(reservation.time)}</div>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div class="info-box">
                    <div class="info-box-label">Kişi Sayısı</div>
                    <div class="info-box-value">${reservation.guests} kişi</div>
                </div>
                <div class="info-box">
                    <div class="info-box-label">Masa</div>
                    <div class="info-box-value">${window.escapeHtml(reservation.table_name || 'Belirlenmedi')}</div>
                </div>
            </div>
            <div class="info-box">
                <div class="info-box-label">Durum</div>
                <div class="mt-1">
                    <span class="status-badge" data-status="${window.escapeHtml(reservation.status)}">${window.getStatusLabel(reservation.status)}</span>
                </div>
            </div>
            <div class="flex gap-3 pt-4">
                <button data-reservation-id="${window.escapeHtml(reservation.reservation_id)}"
                        class="q-btn q-btn--primary flex-1 text-sm js-edit-reservation">
                    Düzenle
                </button>
            </div>
        </div>
    `;

    // onclick="fn('${id}')" attribute içinde string interpolation XSS'e
    // açıktır. Button'u data-attribute + addEventListener ile bağlıyoruz.
    const editBtn = content.querySelector('.js-edit-reservation');
    if (editBtn) {
        editBtn.addEventListener('click', function() {
            const id = this.getAttribute('data-reservation-id');
            editReservation(id);
            closeReservationDetailModal();
        });
    }
    
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    document.body.style.overflow = 'hidden';
}

function getTimeUntil(dateTime) {
    const now = new Date();
    const diff = dateTime - now;
    
    if (diff < 0) return null;
    
    const days = Math.floor(diff / (1000 * 60 * 60 * 24));
    const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
    const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
    
    if (days > 0) {
        return `${days} gün, ${hours} saat sonra`;
    } else if (hours > 0) {
        return `${hours} saat, ${minutes} dakika sonra`;
    } else {
        return `${minutes} dakika sonra`;
    }
}

function closeReservationDetailModal() {
    const modal = document.getElementById('reservation-detail-modal');
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        document.body.style.overflow = '';
    }
}

function editReservation(reservationId) {
    openReservationModal(reservationId);
}

async function changeReservationStatus(reservationId, currentStatus) {
    // Tüm dinamik stringler escapeHtml ile HTML bağlamında güvenli hale getirilir.
    // reservationId data-attribute olarak geçer, onclick string interpolation'dan
    // KAÇINIRIZ (XSS'e karşı katmanlı savunma).
    let selectHtml = '<select id="status-select" class="q-input">';
    for (const [value, label] of Object.entries(statusOptions)) {
        const selected = value === currentStatus ? 'selected' : '';
        selectHtml += `<option value="${window.escapeHtml(value)}" ${selected}>${window.escapeHtml(label)}</option>`;
    }
    selectHtml += '</select>';

    const modalDiv = document.createElement('div');
    modalDiv.className = 'fixed inset-0 z-[300] flex items-center justify-center p-4';
    modalDiv.innerHTML = `
        <div class="modal-overlay absolute inset-0 js-modal-overlay"></div>
        <div class="modal-content relative p-6 max-w-md w-full animate-slide-up">
            <h3 class="text-xl font-black text-slate-900 mb-4">Durumu Değiştir</h3>
            <div class="space-y-4">
                <p class="text-slate-600">Yeni durumu seçin:</p>
                ${selectHtml}
            </div>
            <div class="flex gap-3 mt-6">
                <button class="q-btn q-btn--secondary flex-1 text-sm js-modal-cancel">
                    İptal
                </button>
                <button class="q-btn q-btn--primary flex-1 text-sm js-modal-confirm" data-reservation-id="${window.escapeHtml(reservationId)}">
                    Güncelle
                </button>
            </div>
        </div>
    `;
    document.body.appendChild(modalDiv);

    const removeModal = function() { modalDiv.remove(); };
    const overlay = modalDiv.querySelector('.js-modal-overlay');
    const cancel = modalDiv.querySelector('.js-modal-cancel');
    const confirm = modalDiv.querySelector('.js-modal-confirm');
    if (overlay) overlay.addEventListener('click', removeModal);
    if (cancel) cancel.addEventListener('click', removeModal);
    if (confirm) {
        confirm.addEventListener('click', function() {
            const id = this.getAttribute('data-reservation-id');
            updateReservationStatus(id);
        });
    }
}

async function updateReservationStatus(reservationId) {
    const selectElement = document.getElementById('status-select');
    if (!selectElement) return;
    
    const newStatus = selectElement.value;
    const csrfToken = window.CSRF_TOKEN || (typeof csrf_token !== 'undefined' ? csrf_token : '');
    
    try {
        const response = await fetch(`${baseUrl}/qodmin/reservations/update-status/${reservationId}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify({ status: newStatus })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showSuccess('Rezervasyon durumu güncellendi');
            // Close modal
            document.querySelector('.fixed.z-\\[300\\]')?.remove();
            // Reload page
            setTimeout(() => window.location.reload(), 500);
        } else {
            showError(data.message || 'Güncelleme başarısız');
        }
    } catch (error) {
        console.error('Error updating status:', error);
        showError('Güncelleme sırasında bir hata oluştu');
    }
}

async function deleteReservation(reservationId) {
    let confirmed = false;
    if (window.NotificationManager && window.NotificationManager.confirm) {
        confirmed = await window.NotificationManager.confirm('Bu rezervasyonu silmek istediğinizden emin misiniz? Bu işlem geri alınamaz.', 'Rezervasyon Silme');
    } else {
        confirmed = confirm('Bu rezervasyonu silmek istediğinizden emin misiniz?');
    }
    if (!confirmed) return;
    
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = `${baseUrl}${adminPrefix}/reservations/delete/${reservationId}`;
    
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'reservation_id';
    input.value = reservationId;
    form.appendChild(input);
    
    const csrfInput = document.createElement('input');
    csrfInput.type = 'hidden';
    csrfInput.name = 'csrf_token';
    const existingCsrf = document.querySelector('input[name="csrf_token"]');
    csrfInput.value = existingCsrf ? existingCsrf.value : '';
    form.appendChild(csrfInput);
    
    document.body.appendChild(form);
    form.submit();
}

function updateTableCapacityInfo() {
    const tableSelect = document.getElementById('form-table');
    const guestsInput = document.getElementById('form-guests');
    const infoEl = document.getElementById('table-capacity-info');
    
    if (!tableSelect || !guestsInput || !infoEl) return;
    
    function updateInfo() {
        const selectedOption = tableSelect.options[tableSelect.selectedIndex];
        if (selectedOption && selectedOption.value) {
            const capacity = parseInt(selectedOption.getAttribute('data-capacity')) || 0;
            const guests = parseInt(guestsInput.value) || 0;
            
            if (capacity > 0) {
                if (guests > capacity) {
                    infoEl.textContent = `Uyarı: Kişi sayısı masa kapasitesinden (${capacity}) fazla — yine de kaydedebilirsiniz.`;
                    infoEl.className = 'text-xs text-amber-600 mt-2 ml-1 font-semibold';
                } else {
                    infoEl.textContent = `✓ Masa kapasitesi: ${capacity} kişi`;
                    infoEl.className = 'text-xs text-emerald-600 mt-2 ml-1 font-semibold';
                }
            } else {
                infoEl.textContent = '';
            }
        } else {
            infoEl.textContent = '';
        }
    }
    
    tableSelect.addEventListener('change', updateInfo);
    guestsInput.addEventListener('input', updateInfo);
    updateInfo();
}

// Form submission — AJAX only. The server still keeps a full redirect
// fallback path for bookmarks / no-JS clients, but we intentionally avoid
// the "modal → add page → redirect" round trip here.
const reservationForm = document.getElementById('reservation-form');
if (reservationForm) {
    reservationForm.addEventListener('submit', async function(e) {
        e.preventDefault();

        const customerName = document.getElementById('form-customer-name')?.value.trim();
        const contact      = document.getElementById('form-contact')?.value.trim();
        const date         = document.getElementById('form-date')?.value;
        const time         = document.getElementById('form-time')?.value;
        const guests       = parseInt(document.getElementById('form-guests')?.value || 0);

        if (!customerName) { showError('Müşteri adı gereklidir'); return false; }
        if (!contact)      { showError('İletişim bilgisi gereklidir'); return false; }
        if (!date)         { showError('Tarih gereklidir'); return false; }
        if (!time)         { showError('Saat gereklidir'); return false; }
        if (guests < 1)    { showError('Kişi sayısı en az 1 olmalıdır'); return false; }

        // Capacity check is now a SOFT warning — we do NOT block submit.
        // Server-side will persist the reservation and echo a warning in
        // the JSON response that we surface to the user.
        const tableSelect = document.getElementById('form-table');
        if (tableSelect && tableSelect.value) {
            const selectedOption = tableSelect.options[tableSelect.selectedIndex];
            const capacity = parseInt(selectedOption.getAttribute('data-capacity') || 0);
            if (capacity > 0 && guests > capacity) {
                showWarning(`Uyarı: Kişi sayısı (${guests}) seçilen masanın kapasitesinden (${capacity}) fazla. Yine de kaydediliyor.`);
            }
        }

        const submitBtn     = this.querySelector('button[type="submit"]');
        const submitText    = document.getElementById('submit-text');
        const submitLoading = document.getElementById('submit-loading');
        if (submitBtn && submitText && submitLoading) {
            submitBtn.disabled = true;
            submitText.classList.add('hidden');
            submitLoading.classList.remove('hidden');
        }

        try {
            const formData = new FormData(this);
            const response = await fetch(this.action, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            });

            let payload = {};
            try { payload = await response.json(); }
            catch (_) { payload = { success: response.ok, errors: response.ok ? [] : ['Sunucudan geçersiz cevap.'] }; }

            if (response.ok && payload.success) {
                if (Array.isArray(payload.warnings)) {
                    payload.warnings.forEach(w => showWarning(typeof w === 'string' ? w : (w?.message || '')));
                }
                showSuccess('Rezervasyon kaydedildi.');
                closeReservationModal();
                // Give the toast a beat to render, then refresh the list.
                setTimeout(() => window.location.reload(), 400);
                return;
            }

            const errors = Array.isArray(payload.errors) ? payload.errors : ['Rezervasyon kaydedilemedi.'];
            errors.forEach(err => showError(typeof err === 'string' ? err : (err?.message || 'Hata')));
        } catch (err) {
            showError('Ağ hatası: Rezervasyon kaydedilemedi.');
            if (window.console && console.error) console.error('reservation submit failed', err);
        } finally {
            if (submitBtn && submitText && submitLoading) {
                submitBtn.disabled = false;
                submitText.classList.remove('hidden');
                submitLoading.classList.add('hidden');
            }
        }
    });
}

function showWarning(message) {
    if (!message) return;
    if (window.NotificationManager && typeof window.NotificationManager.warning === 'function') {
        window.NotificationManager.warning(message);
    } else if (window.NotificationManager && typeof window.NotificationManager.info === 'function') {
        window.NotificationManager.info(message);
    } else {
        console.warn(message);
    }
}

function showError(message) {
    window.NotificationManager.error(message);
}

function showSuccess(message) {
    window.NotificationManager.success(message);
}

// Auto-hide messages
document.addEventListener('DOMContentLoaded', function() {
    const successMsg = document.getElementById('success-message');
    const errorMsg = document.getElementById('error-message');
    
    if (successMsg) {
        setTimeout(() => {
            successMsg.style.transition = 'opacity 0.5s';
            successMsg.style.opacity = '0';
            setTimeout(() => successMsg.remove(), 500);
        }, 5000);
    }
    
    if (errorMsg) {
        setTimeout(() => {
            errorMsg.style.transition = 'opacity 0.5s';
            errorMsg.style.opacity = '0';
            setTimeout(() => errorMsg.remove(), 500);
        }, 7000);
    }
});

<?php if ($isSuperAdmin): ?>
// Super Admin: Business Selection
function loadReservations() {
    const businessId = window.currentBusinessId;
    if (businessId) {
        const url = new URL(window.location.href);
        url.searchParams.set('business_id', businessId);
        window.history.pushState({ businessId }, '', url.toString());
        location.reload();
    }
}

const bsScript = document.createElement('script');
bsScript.src = '<?php echo BASE_URL; ?>/assets/js/business-selector.js?v=<?php echo time(); ?>';
bsScript.onload = () => {
    BusinessSelector.init({ baseUrl: '<?php echo BASE_URL; ?>' });
    
    const urlParams = new URLSearchParams(window.location.search);
    const businessIdFromUrl = urlParams.get('business_id');
    
    if (businessIdFromUrl) {
        const apiPrefix = <?php echo json_encode($isSuperAdmin ? '/api/qodmin' : '/api/business'); ?>;
        fetch(`${BusinessSelector.config.baseUrl}${apiPrefix}/businesses`)
            .then(response => response.json())
            .then(data => {
                const businesses = Array.isArray(data) ? data : (data.businesses || data.data || []);
                const business = businesses.find(b => (b.business_id || b.id) === businessIdFromUrl);
                
                if (business) {
                    let businessName = business.company_name || business.business_name || business.name || 'İşletme';
                    
                    sessionStorage.setItem('selected_business_id', businessIdFromUrl);
                    sessionStorage.setItem('selected_business_name', businessName);
                    window.currentBusinessId = businessIdFromUrl;
                    
                    BusinessSelector.showContentView('business-selection-view', 'reservation-management-view', businessName);
                }
            })
            .catch(error => console.error('Error loading business info:', error));
    } else {
        BusinessSelector.loadBusinesses().then(() => {
            BusinessSelector.renderBusinessGrid('business-grid', (id, name) => {
                sessionStorage.setItem('selected_business_id', id);
                sessionStorage.setItem('selected_business_name', name);
                window.currentBusinessId = id;
                
                BusinessSelector.showContentView('business-selection-view', 'reservation-management-view', name);
                
                const url = new URL(window.location.href);
                url.searchParams.set('business_id', id);
                window.history.pushState({ businessId: id, businessName: name }, '', url.toString());
                
                loadReservations();
            });
        });
    }
};
document.head.appendChild(bsScript);

window.backToBusinessSelection = () => {
    BusinessSelector.showSelectionView('business-selection-view', 'reservation-management-view');
    
    const url = new URL(window.location.href);
    url.searchParams.delete('business_id');
    window.history.pushState({}, '', url.toString());
    
    sessionStorage.removeItem('selected_business_id');
    sessionStorage.removeItem('selected_business_name');
    window.currentBusinessId = null;
};
<?php endif; ?>
</script>
