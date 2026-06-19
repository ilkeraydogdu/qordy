<?php
/**
 * Admin Shifts View - Vardiya Planlama Sistemi
 * Personel çalışma saatleri, haftalık/aylık vardiya planlama
 */

require_once __DIR__ . '/../../helpers/translations.php';
require_once __DIR__ . '/../partials/icons.php';

$baseUrl = defined('BASE_URL') ? BASE_URL : '';
$viewType = $view_type ?? 'weekly';
$selectedDate = $selected_date ?? date('Y-m-d');
$startDate = $start_date ?? date('Y-m-01');
$endDate = $end_date ?? date('Y-m-d');
$shiftSchedules = $shift_schedules ?? [];
$actualShifts = $actual_shifts ?? [];
$staffMembers = $staff_members ?? [];
$staffSchedules = $staff_schedules ?? [];
if (!function_exists('getAdminUrl')) {
    require_once __DIR__ . '/../../helpers/url_helper.php';
}
$isSuperAdmin = $is_super_admin ?? false;
$adminPrefix = $isSuperAdmin ? '/qodmin' : '/business';
$apiPrefix = $isSuperAdmin ? '/api/qodmin' : '/api/business';
$show_all_staff = $show_all_staff ?? false;
$schedule_stats = $schedule_stats ?? ['total' => 0, 'by_status' => [], 'unique_staff' => 0];

$dayNames = [
    0 => t('shifts.sunday', 'Pazar'),
    1 => t('shifts.monday', 'Pazartesi'),
    2 => t('shifts.tuesday', 'Salı'),
    3 => t('shifts.wednesday', 'Çarşamba'),
    4 => t('shifts.thursday', 'Perşembe'),
    5 => t('shifts.friday', 'Cuma'),
    6 => t('shifts.saturday', 'Cumartesi')
];
$bsJsVer = @filemtime(__DIR__ . '/../../../public/assets/js/business-selector.js') ?: 1;
?>

<div class="q-page q-biz-theme animate-slide-up">
  <div class="q-container" id="shifts-admin-root">
    <?php if ($is_super_admin ?? false): ?>
    <!-- SUPER ADMIN VIEW: Business Selection First -->
    <div id="business-selection-view">
        <header class="q-page-header">
            <div>
                <p class="q-page-header__eyebrow">Vardiya</p>
                <h1 class="q-page-header__title">İşletme Seçin</h1>
                <p class="q-page-header__subtitle">Vardiyalarını görüntülemek istediğiniz işletmeyi seçin</p>
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
    
    <!-- Shift Management View (shown after business selection) -->
    <div id="shift-management-view" class="hidden">
    <?php endif; ?>
    
    <div id="table-alert" class="hidden q-card q-card--pad" style="margin-bottom:var(--space-5);border-color:#fde68a;background:var(--color-status-warning-bg);">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:var(--space-3);flex-wrap:wrap;">
            <div>
                <div style="font-weight:800;color:#92400e;">Vardiya Tabloları Oluşturulmalı</div>
                <div class="q-hint">Vardiya planlama sistemini kullanmak için veritabanı tablolarının oluşturulması gerekiyor.</div>
            </div>
            <button type="button" onclick="createShiftTables()" class="q-btn q-btn--warning q-btn--sm">Tabloları Oluştur</button>
        </div>
    </div>
    
    <header class="q-page-header">
        <div>
            <p class="q-page-header__eyebrow">Personel</p>
            <?php if ($is_super_admin ?? false): ?>
            <div style="display:flex;align-items:center;gap:var(--space-2);">
                <button type="button" onclick="backToBusinessSelection()" class="q-btn q-btn--ghost q-btn--sm" aria-label="Geri">←</button>
                <div>
                    <h1 class="q-page-header__title"><span id="selected-business-name"></span> — <?php echo t('shifts.title', 'Vardiya Planlama'); ?></h1>
                    <p class="q-page-header__subtitle"><?php echo t('shifts.subtitle', 'Personel Çalışma Saatleri ve Vardiya Yönetimi'); ?></p>
                </div>
            </div>
            <?php else: ?>
            <h1 class="q-page-header__title"><?php echo t('shifts.title', 'Vardiya Planlama'); ?></h1>
            <p class="q-page-header__subtitle"><?php echo t('shifts.subtitle', 'Personel Çalışma Saatleri ve Vardiya Yönetimi'); ?></p>
            <?php endif; ?>
        </div>
        <div class="q-page-header__actions" style="flex-wrap:wrap;">
            <select id="view-type" onchange="changeViewType()" class="q-select" style="width:auto;">
                <option value="weekly" <?php echo $viewType === 'weekly' ? 'selected' : ''; ?>><?php echo t('shifts.weekly', 'Haftalık'); ?></option>
                <option value="monthly" <?php echo $viewType === 'monthly' ? 'selected' : ''; ?>><?php echo t('shifts.monthly', 'Aylık'); ?></option>
            </select>
            <input type="date" id="selected-date" value="<?php echo htmlspecialchars($selectedDate); ?>" onchange="changeDate()" class="q-input" style="width:auto;"/>
            <button type="button" onclick="openStaffScheduleModal()" class="q-btn q-btn--ghost q-btn--sm"><?php echo t('shifts.workHours', 'Çalışma Saatleri'); ?></button>
            <button type="button" onclick="openCreateWeeklyShiftModal()" class="q-btn q-btn--secondary q-btn--sm"><?php echo t('shifts.createWeekly', 'Haftalık Vardiya'); ?></button>
            <button type="button" onclick="openCreateShiftModal()" class="q-btn q-btn--primary q-btn--sm"><?php echo t('shifts.createShift', 'Vardiya Oluştur'); ?></button>
            <a href="<?php echo htmlspecialchars($baseUrl . $adminPrefix . '/shifts?view=' . urlencode($viewType) . '&date=' . urlencode($selectedDate) . ($show_all_staff ? '' : '&show_all=1')); ?>"
               class="q-btn q-btn--sm <?php echo $show_all_staff ? 'q-btn--secondary' : 'q-btn--ghost'; ?>">
                <?php echo $show_all_staff ? t('shifts.allStaff', 'Tüm personel') : t('shifts.showAllStaff', 'Tüm personeli göster'); ?>
            </a>
        </div>
    </header>

    <section class="q-grid q-grid--4" aria-label="Vardiya özeti" style="margin-bottom:var(--space-5);">
        <div class="q-stat">
            <div class="q-stat__top"><span class="q-stat__label"><?php echo t('shifts.plannedRecords', 'Planlı kayıt'); ?></span></div>
            <div class="q-stat__value"><?php echo (int)($schedule_stats['total'] ?? 0); ?></div>
        </div>
        <div class="q-stat">
            <div class="q-stat__top"><span class="q-stat__label"><?php echo t('shifts.staffInRange', 'Personel (bu aralık)'); ?></span></div>
            <div class="q-stat__value"><?php echo (int)($schedule_stats['unique_staff'] ?? 0); ?></div>
        </div>
        <div class="q-stat" style="grid-column:span 2;">
            <div class="q-stat__top"><span class="q-stat__label"><?php echo t('shifts.statusDistribution', 'Durum dağılımı'); ?></span></div>
            <div style="display:flex;flex-wrap:wrap;gap:var(--space-2);margin-top:var(--space-2);">
                <?php
                $bs = $schedule_stats['by_status'] ?? [];
                if (empty($bs)) {
                    echo '<span class="q-hint">—</span>';
                } else {
                    $badgeMap = [
                        'PLANNED'   => 'q-badge--info',
                        'COMPLETED' => 'q-badge--success',
                        'CONFIRMED' => 'q-badge--success',
                        'CANCELLED' => 'q-badge--danger',
                        'ABSENT'    => 'q-badge--warning',
                    ];
                    foreach ($bs as $k => $v) {
                        $cls = $badgeMap[strtoupper((string)$k)] ?? 'q-badge--neutral';
                        echo '<span class="q-badge ' . $cls . '">' . htmlspecialchars((string)$k) . ': ' . (int)$v . '</span>';
                    }
                }
                ?>
            </div>
        </div>
    </section>
    
    <section class="q-card q-card--pad">
        <div id="schedule-container">
            <?php
            // Shared with both partials (controller already provides these).
            $guest_staff = $guest_staff ?? [];
            if ($viewType === 'weekly'):
                $weeklySelectedDate = $selectedDate;
                include __DIR__ . '/shifts_weekly.php';
            else:
                include __DIR__ . '/shifts_monthly.php';
            endif; ?>
        </div>
    </section>
    <?php if ($is_super_admin ?? false): ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php if ($is_super_admin ?? false): ?>
<script>
// Super Admin: Load BusinessSelector
const bsScript = document.createElement('script');
bsScript.src = '<?php echo BASE_URL; ?>/assets/js/business-selector.js?v=<?php echo (int)$bsJsVer; ?>';
bsScript.onload = function() {
    if (typeof BusinessSelector === 'undefined') return;
    
    BusinessSelector.init({ baseUrl: <?php echo json_encode(BASE_URL); ?> });
    
    // Check if business_id is in URL (page reload scenario)
    const urlParams = new URLSearchParams(window.location.search);
    const businessIdFromUrl = urlParams.get('business_id');
    
        if (businessIdFromUrl) {
            // Business ID in URL - load business info directly from API and show shifts view
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
                        
                        // Show shifts view (if there's a management view)
                        const businessNameElement = document.getElementById('selected-business-name');
                        if (businessNameElement) {
                            businessNameElement.textContent = businessName;
                        }
                        
                        // Reload shifts data if needed
                        if (typeof refreshShifts === 'function') {
                            refreshShifts();
                        } else {
                            // Fallback: reload the page content
                            location.reload();
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
        BusinessSelector.loadBusinesses().then(businesses => {
            BusinessSelector.renderBusinessGrid('business-grid', function(businessId, businessName) {
                // Set business ID in session storage
                sessionStorage.setItem('selected_business_id', businessId);
                sessionStorage.setItem('selected_business_name', businessName);
                window.currentBusinessId = businessId;
                
                // Update business name display
                const businessNameElement = document.getElementById('selected-business-name');
                if (businessNameElement) {
                    businessNameElement.textContent = businessName;
                }
                
                // Update URL without page reload (use history.pushState instead of window.location.href)
                const url = new URL(window.location.href);
                url.searchParams.set('business_id', businessId);
                window.history.pushState({ businessId, businessName }, '', url.toString());
                
                // Reload shifts data if needed
                if (typeof refreshShifts === 'function') {
                    refreshShifts();
                } else {
                    // Fallback: reload the page content
                    location.reload();
                }
            });
        });
    }
};
document.head.appendChild(bsScript);

window.backToBusinessSelection = function() {
    // Remove business_id from URL
    const url = new URL(window.location.href);
    url.searchParams.delete('business_id');
    window.history.pushState({}, '', url.toString());
    
    // Clear session storage
    sessionStorage.removeItem('selected_business_id');
    sessionStorage.removeItem('selected_business_name');
    window.currentBusinessId = null;
    
    // Reload page to show business selection
    location.reload();
};
</script>
<?php endif; ?>

<!-- Staff Schedule Modal (Çalışma Saatleri) -->
<div id="staffScheduleModal" class="q-modal-backdrop hidden" onclick="if(event.target === this) closeStaffScheduleModal()">
    <div class="q-modal q-modal--wide" onclick="event.stopPropagation()">
        <div class="flex justify-between items-center mb-6">
            <h2 class="q-modal__title"><?php echo t('shifts.workHours', 'Personel Çalışma Saatleri'); ?></h2>
            <button onclick="closeStaffScheduleModal()" class="q-modal__close" type="button">&times;</button>
        </div>
        
        <form id="staffScheduleForm" onsubmit="saveStaffSchedule(event)">
            <div class="mb-4">
                <label class="q-label"><?php echo t('shifts.staff', 'Personel'); ?> *</label>
                <select id="schedule_staff_id" name="staff_id" required 
                        onchange="loadStaffSchedule()"
                        class="q-select">
                    <option value=""><?php echo t('common.select', 'Seçiniz'); ?></option>
                    <?php foreach ($staffMembers as $staff): ?>
                        <option value="<?php echo htmlspecialchars($staff['user_id'] ?? ''); ?>">
                            <?php echo htmlspecialchars($staff['name'] ?? ''); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="space-y-4" id="weekly-schedule-form">
                <?php for ($day = 1; $day <= 6; $day++): // Monday to Saturday ?>
                    <div class="q-card q-card--pad">
                        <div class="flex items-center gap-3 mb-3">
                            <input type="checkbox" id="day_<?php echo $day; ?>_working" 
                                   name="day_<?php echo $day; ?>_working" value="1"
                                   onchange="toggleDaySchedule(<?php echo $day; ?>)"
                                   class="w-5 h-5">
                            <label for="day_<?php echo $day; ?>_working" class="q-label mb-0">
                                <?php echo $dayNames[$day]; ?>
                            </label>
                        </div>
                        <div class="grid grid-cols-2 gap-3 day-schedule-fields" id="day_<?php echo $day; ?>_fields" style="display: none;">
                            <div>
                                <label class="q-label q-label--sm"><?php echo t('shifts.startTime', 'Başlangıç'); ?></label>
                                <input type="time" id="day_<?php echo $day; ?>_start" name="day_<?php echo $day; ?>_start" 
                                       value="09:00" class="q-input">
                            </div>
                            <div>
                                <label class="q-label q-label--sm"><?php echo t('shifts.endTime', 'Bitiş'); ?></label>
                                <input type="time" id="day_<?php echo $day; ?>_end" name="day_<?php echo $day; ?>_end" 
                                       value="17:00" class="q-input">
                            </div>
                        </div>
                    </div>
                <?php endfor; ?>
                
                <!-- Sunday -->
                <div class="q-card q-card--pad">
                    <div class="flex items-center gap-3 mb-3">
                        <input type="checkbox" id="day_0_working" 
                               name="day_0_working" value="1"
                               onchange="toggleDaySchedule(0)"
                               class="w-5 h-5">
                        <label for="day_0_working" class="q-label mb-0">
                            <?php echo $dayNames[0]; ?>
                        </label>
                    </div>
                    <div class="grid grid-cols-2 gap-3 day-schedule-fields" id="day_0_fields" style="display: none;">
                        <div>
                            <label class="q-label q-label--sm"><?php echo t('shifts.startTime', 'Başlangıç'); ?></label>
                            <input type="time" id="day_0_start" name="day_0_start" 
                                   value="09:00" class="q-input">
                        </div>
                        <div>
                            <label class="q-label q-label--sm"><?php echo t('shifts.endTime', 'Bitiş'); ?></label>
                            <input type="time" id="day_0_end" name="day_0_end" 
                                   value="17:00" class="q-input">
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="flex gap-3 justify-end mt-6">
                <button type="button" onclick="closeStaffScheduleModal()" 
                        class="q-btn q-btn--ghost">
                    <?php echo t('common.cancel', 'İptal'); ?>
                </button>
                <button type="submit" 
                        class="q-btn q-btn--primary">
                    <?php echo t('common.save', 'Kaydet'); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Create Weekly Shift Schedule Modal -->
<div id="weeklyShiftModal" class="q-modal-backdrop hidden" onclick="if(event.target === this) closeWeeklyShiftModal()">
    <div class="bg-white rounded-2xl p-6 sm:p-8 max-w-3xl w-full max-h-[90vh] overflow-y-auto shadow-2xl" onclick="event.stopPropagation()">
        <div class="flex justify-between items-center mb-6">
            <h2 class="q-modal__title"><?php echo t('shifts.createWeekly', 'Haftalık Vardiya Oluştur'); ?></h2>
            <button onclick="closeWeeklyShiftModal()" class="q-modal__close" type="button">&times;</button>
        </div>
        
        <form id="weeklyShiftForm" onsubmit="saveWeeklyShiftSchedule(event)">
            <div class="space-y-4 mb-6">
                <div>
                    <label class="q-label"><?php echo t('shifts.staffType', 'Personel Tipi'); ?> *</label>
                    <select id="weekly_staff_type" name="staff_type" required 
                            onchange="toggleWeeklyStaffType()"
                            class="q-select">
                        <option value="USER"><?php echo t('shifts.systemStaff', 'Sistem Personeli'); ?></option>
                        <option value="GUEST_STAFF"><?php echo t('shifts.guestStaff', 'Misafir/Geçici Personel'); ?></option>
                    </select>
                </div>
                
                <div id="weekly-system-staff-select">
                    <label class="q-label"><?php echo t('shifts.staff', 'Personel'); ?> *</label>
                    <select id="weekly_staff_id" name="staff_id" 
                            onchange="loadWeeklySchedule()"
                            class="q-select">
                        <option value=""><?php echo t('common.select', 'Seçiniz'); ?></option>
                        <?php foreach ($staffMembers as $staff): ?>
                            <option value="<?php echo htmlspecialchars($staff['user_id'] ?? ''); ?>">
                                <?php echo htmlspecialchars($staff['name'] ?? ''); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div id="weekly-guest-staff-fields" style="display: none;">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="q-label"><?php echo t('shifts.firstName', 'Ad'); ?> *</label>
                            <input type="text" id="weekly_first_name" name="first_name" 
                                   class="q-select">
                        </div>
                        <div>
                            <label class="q-label"><?php echo t('shifts.lastName', 'Soyad'); ?> *</label>
                            <input type="text" id="weekly_last_name" name="last_name" 
                                   class="q-select">
                        </div>
                    </div>
                    <div>
                        <label class="q-label"><?php echo t('shifts.phone', 'Telefon'); ?> *</label>
                        <input type="tel" id="weekly_phone" name="phone" 
                               class="q-select">
                    </div>
                    <div>
                        <label class="q-label"><?php echo t('shifts.email', 'E-posta'); ?></label>
                        <input type="email" id="weekly_email" name="email" 
                               class="q-select">
                    </div>
                </div>
                
                <div>
                    <label class="q-label"><?php echo t('shifts.weekStart', 'Hafta Başlangıç Tarihi'); ?> *</label>
                    <input type="date" id="weekly_start_date" name="week_start_date" required 
                           min="<?php echo date('Y-m-d'); ?>"
                           value="<?php 
                               // Get next Monday
                               $nextMonday = new DateTime();
                               $nextMonday->modify('next monday');
                               echo $nextMonday->format('Y-m-d');
                           ?>"
                           class="q-select">
                    <p class="text-xs text-slate-500 mt-1"><?php echo t('shifts.weekStartHint', 'Haftanın ilk günü (Pazartesi) seçin. Geçmiş tarih seçilemez.'); ?></p>
                </div>
                
                <div class="bg-blue-50 border border-blue-200 rounded-xl p-4">
                    <div class="text-sm font-black text-blue-900 mb-2"><?php echo t('shifts.weeklyScheduleInfo', 'Haftalık Program Bilgisi'); ?></div>
                    <div id="weekly-schedule-info" class="text-xs text-blue-700">
                        <?php echo t('shifts.selectStaffFirst', 'Önce personel seçin, haftalık programı otomatik yüklenecek.'); ?>
                    </div>
                </div>
                
                <div class="border border-slate-200 rounded-xl p-4 bg-slate-50">
                    <div class="text-sm font-black text-slate-700 mb-3"><?php echo t('shifts.weekDays', 'Haftanın Günleri'); ?></div>
                    <div id="weekly-days-container" class="space-y-2 max-h-64 overflow-y-auto">
                        <p class="text-xs text-slate-500 text-center py-4"><?php echo t('shifts.selectStaffFirst', 'Önce personel seçin'); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="flex gap-3 justify-end">
                <button type="button" onclick="closeWeeklyShiftModal()" 
                        class="q-btn q-btn--ghost">
                    <?php echo t('common.cancel', 'İptal'); ?>
                </button>
                <button type="submit" 
                        class="q-btn q-btn--primary">
                    <?php echo t('shifts.createWeek', 'Haftayı Oluştur'); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Create Shift Schedule Modal -->
<div id="shiftModal" class="q-modal-backdrop hidden" onclick="if(event.target === this) closeShiftModal()">
    <div class="q-modal" onclick="event.stopPropagation()">
        <div class="flex justify-between items-center mb-6">
            <h2 class="q-modal__title" id="modalTitle"><?php echo t('shifts.createShift', 'Vardiya Oluştur'); ?></h2>
            <p class="text-xs text-slate-500 font-bold hidden" id="editingHint">Kaydı düzenliyorsunuz — kaydedince güncellenir.</p>
            <button onclick="closeShiftModal()" class="q-modal__close" type="button">&times;</button>
        </div>
        
        <form id="shiftForm" onsubmit="saveShiftSchedule(event)">
            <input type="hidden" id="editing_schedule_id" name="editing_schedule_id" value="">
            <div class="space-y-4 mb-6">
                <div>
                    <label class="q-label"><?php echo t('shifts.staffType', 'Personel Tipi'); ?> *</label>
                    <select id="shift_staff_type" name="staff_type" required 
                            onchange="toggleStaffType()"
                            class="q-select">
                        <option value="USER"><?php echo t('shifts.systemStaff', 'Sistem Personeli'); ?></option>
                        <option value="GUEST_STAFF"><?php echo t('shifts.guestStaff', 'Misafir/Geçici Personel'); ?></option>
                    </select>
                </div>
                
                <div id="system-staff-select">
                    <label class="q-label"><?php echo t('shifts.staff', 'Personel'); ?> *</label>
                    <select id="shift_staff_id" name="staff_id" 
                            class="q-select">
                        <option value=""><?php echo t('common.select', 'Seçiniz'); ?></option>
                        <?php foreach ($staffMembers as $staff): ?>
                            <option value="<?php echo htmlspecialchars($staff['user_id'] ?? ''); ?>">
                                <?php echo htmlspecialchars($staff['name'] ?? ''); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div id="guest-staff-fields" style="display: none;">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="q-label"><?php echo t('shifts.firstName', 'Ad'); ?> *</label>
                            <input type="text" id="shift_first_name" name="first_name" 
                                   class="q-select">
                        </div>
                        <div>
                            <label class="q-label"><?php echo t('shifts.lastName', 'Soyad'); ?> *</label>
                            <input type="text" id="shift_last_name" name="last_name" 
                                   class="q-select">
                        </div>
                    </div>
                    <div>
                        <label class="q-label"><?php echo t('shifts.phone', 'Telefon'); ?> *</label>
                        <input type="tel" id="shift_phone" name="phone" 
                               class="q-select">
                    </div>
                    <div>
                        <label class="q-label"><?php echo t('shifts.email', 'E-posta'); ?></label>
                        <input type="email" id="shift_email" name="email" 
                               class="q-select">
                    </div>
                </div>
                
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="q-label"><?php echo t('shifts.date', 'Tarih'); ?> *</label>
                    <input type="date" id="shift_date" name="shift_date" required 
                           min="<?php echo date('Y-m-d'); ?>"
                           value="<?php echo date('Y-m-d'); ?>"
                           class="q-select">
                    <p class="text-xs text-slate-500 mt-1"><?php echo t('shifts.noPastDate', 'Geçmiş tarih seçilemez'); ?></p>
                </div>
                    
                    <div>
                        <label class="q-label"><?php echo t('shifts.shiftType', 'Vardiya Tipi'); ?></label>
                        <select id="shift_type" name="shift_type" 
                                class="q-select">
                            <option value="REGULAR"><?php echo t('shifts.regular', 'Normal'); ?></option>
                            <option value="OVERTIME"><?php echo t('shifts.overtime', 'Mesai'); ?></option>
                            <option value="HOLIDAY"><?php echo t('shifts.holiday', 'Tatil'); ?></option>
                        </select>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="q-label"><?php echo t('shifts.startTime', 'Başlangıç Saati'); ?> *</label>
                        <input type="time" id="shift_start_time" name="start_time" required 
                               value="09:00"
                               class="q-select">
                    </div>
                    
                    <div>
                        <label class="q-label"><?php echo t('shifts.endTime', 'Bitiş Saati'); ?> *</label>
                        <input type="time" id="shift_end_time" name="end_time" required 
                               value="17:00"
                               class="q-select">
                    </div>
                </div>
                
                <div>
                    <label class="q-label"><?php echo t('shifts.notes', 'Notlar'); ?></label>
                    <textarea id="shift_notes" name="notes" rows="3" 
                              class="q-select"></textarea>
                </div>
            </div>
            
            <div class="flex gap-3 justify-end">
                <button type="button" onclick="closeShiftModal()" 
                        class="q-btn q-btn--ghost">
                    <?php echo t('common.cancel', 'İptal'); ?>
                </button>
                <button type="submit" 
                        class="q-btn q-btn--primary">
                    <?php echo t('common.save', 'Kaydet'); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
const baseUrl = <?php echo json_encode($baseUrl, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?> || window.BASE_URL || '';
const adminPrefix = <?php echo json_encode($adminPrefix); ?>;
const apiPrefix = <?php echo json_encode($apiPrefix); ?>;
const viewType = <?php echo json_encode($viewType, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
const selectedDate = <?php echo json_encode($selectedDate, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
const shiftSchedules = <?php echo json_encode($shiftSchedules, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
const staffSchedules = <?php echo json_encode($staffSchedules, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
const staffMembers = <?php echo json_encode($staffMembers, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
const guestStaff = <?php echo json_encode($guest_staff ?? [], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

function getCsrf() {
    return window.CSRF_TOKEN || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

function toggleStaffType() {
    const staffType = document.getElementById('shift_staff_type').value;
    const systemStaff = document.getElementById('system-staff-select');
    const guestFields = document.getElementById('guest-staff-fields');
    const staffSelect = document.getElementById('shift_staff_id');
    
    if (staffType === 'GUEST_STAFF') {
        systemStaff.style.display = 'none';
        guestFields.style.display = 'block';
        staffSelect.removeAttribute('required');
        document.getElementById('shift_first_name').setAttribute('required', 'required');
        document.getElementById('shift_last_name').setAttribute('required', 'required');
        document.getElementById('shift_phone').setAttribute('required', 'required');
    } else {
        systemStaff.style.display = 'block';
        guestFields.style.display = 'none';
        staffSelect.setAttribute('required', 'required');
        document.getElementById('shift_first_name').removeAttribute('required');
        document.getElementById('shift_last_name').removeAttribute('required');
        document.getElementById('shift_phone').removeAttribute('required');
    }
}

function changeViewType() {
    const vt = document.getElementById('view-type').value;
    const date = document.getElementById('selected-date').value;
    const showAll = new URLSearchParams(window.location.search).get('show_all');
    let url = `${baseUrl}${adminPrefix}/shifts?view=${encodeURIComponent(vt)}&date=${encodeURIComponent(date)}`;
    if (showAll === '1') url += '&show_all=1';
    window.location.href = url;
}

function changeDate() {
    changeViewType();
}

function openStaffScheduleModal() {
    document.getElementById('staffScheduleModal').classList.remove('hidden');
    document.getElementById('staffScheduleModal').classList.add('flex');
}

function closeStaffScheduleModal() {
    document.getElementById('staffScheduleModal').classList.add('hidden');
    document.getElementById('staffScheduleModal').classList.remove('flex');
}

function toggleDaySchedule(day) {
    const checkbox = document.getElementById(`day_${day}_working`);
    const fields = document.getElementById(`day_${day}_fields`);
    fields.style.display = checkbox.checked ? 'grid' : 'none';
}

function loadStaffSchedule() {
    const staffId = document.getElementById('schedule_staff_id').value;
    if (!staffId) return;
    
    const schedule = staffSchedules[staffId];
    if (!schedule) return;
    
    for (let day = 0; day < 7; day++) {
        const daySchedule = schedule[day];
        const checkbox = document.getElementById(`day_${day}_working`);
        const startInput = document.getElementById(`day_${day}_start`);
        const endInput = document.getElementById(`day_${day}_end`);
        const fields = document.getElementById(`day_${day}_fields`);
        
        if (daySchedule && daySchedule.is_working == 1) {
            checkbox.checked = true;
            startInput.value = daySchedule.start_time ? daySchedule.start_time.substring(0, 5) : '09:00';
            endInput.value = daySchedule.end_time ? daySchedule.end_time.substring(0, 5) : '17:00';
            fields.style.display = 'grid';
        } else {
            checkbox.checked = false;
            fields.style.display = 'none';
        }
    }
}

function saveStaffSchedule(event) {
    event.preventDefault();
    const formData = new FormData(event.target);
    
    fetch(`${baseUrl}${adminPrefix}/shifts/save-schedule`, {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token': getCsrf()
        }
    })
    .then(response => {
        // Check if response is JSON
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            return response.text().then(text => {
                console.error('Non-JSON response:', text.substring(0, 200));
                throw new Error('Server returned non-JSON response. Check permissions.');
            });
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            closeStaffScheduleModal();
            location.reload();
        } else {
            const errorMsg = data.message || data.data?.message || 'Kaydetme hatası';
            window.NotificationManager.error(errorMsg);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        window.NotificationManager.error('Kaydetme hatası: ' + (error.message || 'Bilinmeyen hata'));
    });
}

function openCreateShiftModal() {
    document.getElementById('shiftForm').reset();
    document.getElementById('editing_schedule_id').value = '';
    const hint = document.getElementById('editingHint');
    if (hint) hint.classList.add('hidden');
    const mt = document.getElementById('modalTitle');
    if (mt) mt.textContent = '<?php echo t('shifts.createShift', 'Vardiya Oluştur'); ?>';
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('shift_date').value = selectedDate >= today ? selectedDate : today;
    document.getElementById('shift_date').min = today;
    document.getElementById('shift_staff_type').value = 'USER';
    toggleStaffType();
    document.getElementById('shiftModal').classList.remove('hidden');
    document.getElementById('shiftModal').classList.add('flex');
}

function openCreateWeeklyShiftModal() {
    document.getElementById('weeklyShiftForm').reset();
    const today = new Date();
    const nextMonday = new Date(today);
    const dayOfWeek = nextMonday.getDay();
    const daysUntilMonday = dayOfWeek === 0 ? 1 : (8 - dayOfWeek) % 7 || 7;
    nextMonday.setDate(today.getDate() + daysUntilMonday);
    
    document.getElementById('weekly_start_date').value = nextMonday.toISOString().split('T')[0];
    document.getElementById('weekly_start_date').min = today.toISOString().split('T')[0];
    document.getElementById('weekly_staff_type').value = 'USER';
    toggleWeeklyStaffType();
    document.getElementById('weekly-days-container').innerHTML = '';
    document.getElementById('weekly-schedule-info').textContent = '<?php echo t('shifts.selectStaffFirst', 'Önce personel seçin'); ?>';
    document.getElementById('weeklyShiftModal').classList.remove('hidden');
    document.getElementById('weeklyShiftModal').classList.add('flex');
}

function closeWeeklyShiftModal() {
    document.getElementById('weeklyShiftModal').classList.add('hidden');
    document.getElementById('weeklyShiftModal').classList.remove('flex');
}

function loadWeeklySchedule() {
    const staffType = document.getElementById('weekly_staff_type')?.value || 'USER';
    const staffId = document.getElementById('weekly_staff_id').value;
    
    if (staffType === 'GUEST_STAFF') {
        // Guest staff doesn't have weekly schedule
        document.getElementById('weekly-days-container').innerHTML = '';
        document.getElementById('weekly-schedule-info').textContent = '<?php echo t('shifts.guestStaffNoSchedule', 'Misafir personel için haftalık program yok, varsayılan saatler kullanılacak'); ?>';
        return;
    }
    
    if (!staffId) {
        document.getElementById('weekly-days-container').innerHTML = '';
        document.getElementById('weekly-schedule-info').textContent = '<?php echo t('shifts.selectStaffFirst', 'Önce personel seçin'); ?>';
        return;
    }
    
    const schedule = staffSchedules[staffId];
    const weekStart = document.getElementById('weekly_start_date').value;
    
    if (!weekStart) {
        return;
    }
    
    // Check if date is in the past
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const selectedDate = new Date(weekStart);
    if (selectedDate < today) {
        window.NotificationManager.warning('<?php echo t('shifts.noPastDate', 'Geçmiş tarih seçilemez'); ?>');
        document.getElementById('weekly_start_date').value = today.toISOString().split('T')[0];
        return;
    }
    
    // Calculate week dates (Monday to Sunday)
    const startDate = new Date(weekStart);
    const dayOfWeek = startDate.getDay();
    const daysToMonday = dayOfWeek === 0 ? 6 : dayOfWeek - 1;
    startDate.setDate(startDate.getDate() - daysToMonday);
    
    const dayNames = ['<?php echo $dayNames[0]; ?>', '<?php echo $dayNames[1]; ?>', '<?php echo $dayNames[2]; ?>', '<?php echo $dayNames[3]; ?>', '<?php echo $dayNames[4]; ?>', '<?php echo $dayNames[5]; ?>', '<?php echo $dayNames[6]; ?>'];
    
    let html = '';
    let scheduleInfo = '';
    
    for (let i = 0; i < 7; i++) {
        const currentDate = new Date(startDate);
        currentDate.setDate(startDate.getDate() + i);
        const dateStr = currentDate.toISOString().split('T')[0];
        const dayOfWeek = currentDate.getDay();
        
        const daySchedule = schedule && schedule[dayOfWeek] ? schedule[dayOfWeek] : null;
        const isWorking = daySchedule && daySchedule.is_working == 1;
        const startTime = daySchedule ? (daySchedule.start_time ? daySchedule.start_time.substring(0, 5) : '09:00') : '09:00';
        const endTime = daySchedule ? (daySchedule.end_time ? daySchedule.end_time.substring(0, 5) : '17:00') : '17:00';
        
        if (isWorking) {
            scheduleInfo += `${dayNames[dayOfWeek]}: ${startTime}-${endTime}; `;
        }
        
        const isPast = new Date(dateStr) < new Date();
        const isPastClass = isPast ? 'opacity-50' : '';
        const isPastDisabled = isPast ? 'disabled' : '';
        
        html += `
            <div class="flex flex-col sm:flex-row items-start sm:items-center gap-2 sm:gap-3 p-3 bg-white border border-slate-200 rounded-lg ${isPastClass}">
                <div class="flex items-center gap-2 flex-1 min-w-0">
                    <div class="w-20 sm:w-24 font-black text-xs sm:text-sm text-slate-700 flex-shrink-0">${dayNames[dayOfWeek]}</div>
                    <div class="text-xs text-slate-500 flex-shrink-0">${currentDate.toLocaleDateString('tr-TR', { day: '2-digit', month: '2-digit' })}</div>
                    <div class="flex-1 grid grid-cols-2 gap-2 min-w-0">
                        <input type="time" name="day_${i}_start" value="${startTime}" 
                               class="px-2 py-1.5 border border-slate-200 rounded-lg text-xs sm:text-sm font-bold ${!isWorking ? 'bg-slate-100' : ''}" 
                               ${!isWorking || isPast ? 'disabled' : ''}>
                        <input type="time" name="day_${i}_end" value="${endTime}" 
                               class="px-2 py-1.5 border border-slate-200 rounded-lg text-xs sm:text-sm font-bold ${!isWorking ? 'bg-slate-100' : ''}" 
                               ${!isWorking || isPast ? 'disabled' : ''}>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    ${isPast ? '<span class="text-xs text-red-600">Geçmiş</span>' : ''}
                    <input type="checkbox" name="day_${i}_enabled" value="1" ${isWorking && !isPast ? 'checked' : ''}
                           onchange="toggleWeeklyDay(${i})" 
                           class="w-5 h-5 cursor-pointer" ${isPast ? 'disabled' : ''}>
                </div>
                <input type="hidden" name="day_${i}_date" value="${dateStr}">
            </div>
        `;
    }
    
    document.getElementById('weekly-days-container').innerHTML = html;
    document.getElementById('weekly-schedule-info').textContent = scheduleInfo || '<?php echo t('shifts.noWeeklySchedule', 'Haftalık program tanımlı değil, varsayılan saatler kullanılacak'); ?>';
}

function toggleWeeklyDay(day) {
    const checkbox = document.querySelector(`input[name="day_${day}_enabled"]`);
    const dateInput = document.querySelector(`input[name="day_${day}_date"]`);
    const dateStr = dateInput ? dateInput.value : '';
    const isPast = dateStr && new Date(dateStr) < new Date();
    
    if (isPast) {
        checkbox.checked = false;
        window.NotificationManager.warning('<?php echo t('shifts.noPastDate', 'Geçmiş tarih seçilemez'); ?>');
        return;
    }
    
    const inputs = document.querySelectorAll(`input[name^="day_${day}_"]`);
    inputs.forEach(input => {
        if (input.type === 'time') {
            input.disabled = !checkbox.checked || isPast;
            if (checkbox.checked && !isPast) {
                input.classList.remove('bg-slate-100');
            } else {
                input.classList.add('bg-slate-100');
            }
        }
    });
}

function saveWeeklyShiftSchedule(event) {
    event.preventDefault();
    const formData = new FormData(event.target);
    
    const staffType = formData.get('staff_type');
    
    // Validate guest staff fields
    if (staffType === 'GUEST_STAFF') {
        const firstName = formData.get('first_name');
        const lastName = formData.get('last_name');
        const phone = formData.get('phone');
        
        if (!firstName || !lastName || !phone) {
            window.NotificationManager.warning('<?php echo t('shifts.guestStaffRequired', 'Misafir personel için ad, soyad ve telefon gereklidir'); ?>');
            return;
        }
    } else {
        const staffId = formData.get('staff_id');
        if (!staffId) {
            window.NotificationManager.warning('<?php echo t('shifts.selectStaff', 'Personel seçmelisiniz'); ?>');
            return;
        }
    }
    
    // Validate dates are not in the past
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    let hasValidDay = false;
    for (let i = 0; i < 7; i++) {
        const dateStr = formData.get(`day_${i}_date`);
        const enabled = formData.get(`day_${i}_enabled`) === '1';
        if (dateStr && enabled) {
            const date = new Date(dateStr);
            date.setHours(0, 0, 0, 0);
            if (date < today) {
                window.NotificationManager.warning('<?php echo t('shifts.noPastDate', 'Geçmiş tarih seçilemez'); ?>');
                return;
            }
            hasValidDay = true;
        }
    }
    
    if (!hasValidDay) {
        window.NotificationManager.warning('<?php echo t('shifts.selectAtLeastOneDay', 'En az bir gün seçmelisiniz'); ?>');
        return;
    }
    
    fetch(`${baseUrl}${adminPrefix}/shifts/create-weekly-schedule`, {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token': getCsrf()
        }
    })
    .then(response => {
        // Check if response is JSON
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            return response.text().then(text => {
                console.error('Non-JSON response:', text.substring(0, 200));
                throw new Error('Server returned non-JSON response. Check permissions.');
            });
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            const created = data.data?.created || data.created || 0;
            const skipped = data.data?.skipped || data.skipped || 0;
            let message = `<?php echo t('shifts.weeklyCreated', 'Haftalık vardiya oluşturuldu'); ?>: ${created} gün`;
            if (skipped > 0) {
                message += ` (${skipped} gün atlandı)`;
            }
            window.NotificationManager.success(message);
            closeWeeklyShiftModal();
            location.reload();
        } else {
            const errorMsg = data.message || data.data?.message || 'Kaydetme hatası';
            window.NotificationManager.error(errorMsg);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        window.NotificationManager.error('Kaydetme hatası: ' + (error.message || 'Bilinmeyen hata'));
    });
}

function toggleWeeklyStaffType() {
    const staffType = document.getElementById('weekly_staff_type').value;
    const systemStaff = document.getElementById('weekly-system-staff-select');
    const guestFields = document.getElementById('weekly-guest-staff-fields');
    const staffSelect = document.getElementById('weekly_staff_id');
    
    if (staffType === 'GUEST_STAFF') {
        systemStaff.style.display = 'none';
        guestFields.style.display = 'block';
        staffSelect.removeAttribute('required');
        document.getElementById('weekly_first_name').setAttribute('required', 'required');
        document.getElementById('weekly_last_name').setAttribute('required', 'required');
        document.getElementById('weekly_phone').setAttribute('required', 'required');
        document.getElementById('weekly-days-container').innerHTML = '';
        document.getElementById('weekly-schedule-info').textContent = '<?php echo t('shifts.guestStaffNoSchedule', 'Misafir personel için haftalık program yok'); ?>';
    } else {
        systemStaff.style.display = 'block';
        guestFields.style.display = 'none';
        staffSelect.setAttribute('required', 'required');
        document.getElementById('weekly_first_name').removeAttribute('required');
        document.getElementById('weekly_last_name').removeAttribute('required');
        document.getElementById('weekly_phone').removeAttribute('required');
        const staffId = staffSelect.value;
        if (staffId) {
            loadWeeklySchedule();
        }
    }
}

document.getElementById('weekly_start_date')?.addEventListener('change', function() {
    const staffType = document.getElementById('weekly_staff_type')?.value || 'USER';
    if (staffType === 'USER') {
        const staffId = document.getElementById('weekly_staff_id').value;
        if (staffId) {
            loadWeeklySchedule();
        }
    } else {
        // For guest staff, just show default message
        document.getElementById('weekly-days-container').innerHTML = '';
        document.getElementById('weekly-schedule-info').textContent = '<?php echo t('shifts.guestStaffNoSchedule', 'Misafir personel için haftalık program yok'); ?>';
    }
});

function closeShiftModal() {
    document.getElementById('shiftModal').classList.add('hidden');
    document.getElementById('shiftModal').classList.remove('flex');
}

function saveShiftSchedule(event) {
    event.preventDefault();
    const formData = new FormData(event.target);
    const editId = (document.getElementById('editing_schedule_id')?.value || '').trim();
    
    const staffType = formData.get('staff_type');
    if (!editId) {
        if (staffType === 'GUEST_STAFF') {
            const firstName = formData.get('first_name');
            const lastName = formData.get('last_name');
            const phone = formData.get('phone');
            if (!firstName || !lastName || !phone) {
                window.NotificationManager.warning('<?php echo t('shifts.guestStaffRequired', 'Misafir personel için ad, soyad ve telefon gereklidir'); ?>');
                return;
            }
        } else {
            const staffId = formData.get('staff_id');
            if (!staffId) {
                window.NotificationManager.warning('<?php echo t('shifts.selectStaff', 'Personel seçmelisiniz'); ?>');
                return;
            }
        }
    }
    
    const shiftDate = formData.get('shift_date');
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const selD = new Date(shiftDate);
    selD.setHours(0, 0, 0, 0);
    if (selD < today) {
        window.NotificationManager.warning('<?php echo t('shifts.noPastDate', 'Geçmiş tarih seçilemez'); ?>');
        return;
    }
    
    if (editId) {
        const payload = {
            shift_date: formData.get('shift_date'),
            start_time: formData.get('start_time'),
            end_time: formData.get('end_time'),
            shift_type: formData.get('shift_type'),
            notes: formData.get('notes') || ''
        };
        fetch(`${baseUrl}${apiPrefix}/shift-schedules/${encodeURIComponent(editId)}/update`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-Token': getCsrf(),
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(payload),
            credentials: 'same-origin'
        })
        .then(r => r.json().then(data => ({ ok: r.ok, data })))
        .then(({ ok, data }) => {
            if (ok && data.success) {
                closeShiftModal();
                location.reload();
            } else {
                window.NotificationManager.error(data.message || 'Güncelleme başarısız');
            }
        })
        .catch(err => {
            console.error(err);
            window.NotificationManager.error('Güncelleme hatası');
        });
        return;
    }
    
    fetch(`${baseUrl}${adminPrefix}/shifts/create-schedule`, {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token': getCsrf()
        }
    })
    .then(response => {
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            return response.text().then(text => {
                console.error('Non-JSON response:', text.substring(0, 200));
                throw new Error('Server returned non-JSON response. Check permissions.');
            });
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            closeShiftModal();
            location.reload();
        } else {
            const errorMsg = data.message || data.data?.message || 'Kaydetme hatası';
            window.NotificationManager.error(errorMsg);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        window.NotificationManager.error('Kaydetme hatası: ' + (error.message || 'Bilinmeyen hata'));
    });
}

function createShiftForDate(staffId, date, startTime = '09:00', endTime = '17:00', staffType = 'USER', staffName = '', staffPhone = '') {
    openCreateShiftModal();
    document.getElementById('shift_staff_type').value = staffType;
    toggleStaffType();
    if (staffType === 'GUEST_STAFF') {
        const nameParts = (staffName || '').split(' ');
        document.getElementById('shift_first_name').value = nameParts[0] || '';
        document.getElementById('shift_last_name').value = nameParts.slice(1).join(' ') || '';
        document.getElementById('shift_phone').value = staffPhone;
    } else {
        document.getElementById('shift_staff_id').value = staffId;
    }
    document.getElementById('shift_date').value = date;
    document.getElementById('shift_start_time').value = (startTime || '09:00').toString().substring(0, 5);
    document.getElementById('shift_end_time').value = (endTime || '17:00').toString().substring(0, 5);
}

async function editShiftSchedule(scheduleId) {
    try {
        const r = await fetch(`${baseUrl}${apiPrefix}/shift-schedules/${encodeURIComponent(scheduleId)}`, {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        });
        const j = await r.json();
        if (!j.success || !j.data) {
            throw new Error(j.message || 'Yüklenemedi');
        }
        const d = j.data;
        document.getElementById('editing_schedule_id').value = scheduleId;
        document.getElementById('editingHint')?.classList.remove('hidden');
        document.getElementById('modalTitle').textContent = 'Vardiyayı düzenle';
        document.getElementById('shift_staff_type').value = d.staff_type || 'USER';
        toggleStaffType();
        if ((d.staff_type || 'USER') === 'GUEST_STAFF') {
            const full = (d.staff_name || d.staff_display_name || '').trim();
            const parts = full.split(/\s+/);
            document.getElementById('shift_first_name').value = parts[0] || '';
            document.getElementById('shift_last_name').value = parts.slice(1).join(' ') || '';
            document.getElementById('shift_phone').value = d.staff_phone || '';
        } else {
            document.getElementById('shift_staff_id').value = d.staff_id || '';
        }
        document.getElementById('shift_date').value = (d.shift_date || '').toString().substring(0, 10);
        const st = (d.start_time || '09:00:00').toString();
        const et = (d.end_time || '17:00:00').toString();
        document.getElementById('shift_start_time').value = st.length >= 5 ? st.substring(0, 5) : st;
        document.getElementById('shift_end_time').value = et.length >= 5 ? et.substring(0, 5) : et;
        document.getElementById('shift_type').value = d.shift_type || 'REGULAR';
        document.getElementById('shift_notes').value = d.notes || '';
        document.getElementById('shiftModal').classList.remove('hidden');
        document.getElementById('shiftModal').classList.add('flex');
    } catch (e) {
        console.error(e);
        window.NotificationManager?.error(e.message || 'Vardiya yüklenemedi');
    }
}

function deletePlannedShift(scheduleId) {
    if (!scheduleId) return;
    if (!confirm('Bu planlı vardiyayı silmek istediğinize emin misiniz?')) return;
    fetch(`${baseUrl}${apiPrefix}/shift-schedules/${encodeURIComponent(scheduleId)}/delete`, {
        method: 'POST',
        headers: {
            'Accept': 'application/json',
            'X-CSRF-Token': getCsrf(),
            'X-Requested-With': 'XMLHttpRequest'
        },
        credentials: 'same-origin'
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            window.NotificationManager?.error(data.message || 'Silinemedi');
        }
    })
    .catch(err => {
        console.error(err);
        window.NotificationManager?.error('Silme hatası');
    });
}

async function createShiftTables() {
    let confirmed = false;
    if (window.NotificationManager && window.NotificationManager.confirm) {
        confirmed = await window.NotificationManager.confirm('Vardiya tablolarını oluşturmak istediğinizden emin misiniz?', 'Onay');
    } else {
        confirmed = confirm('Vardiya tablolarını oluşturmak istediğinizden emin misiniz?');
    }
    if (!confirmed) return;
    
    fetch(`${baseUrl}${adminPrefix}/shifts/create-tables`, {
        method: 'POST'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.NotificationManager.success('Tablolar başarıyla oluşturuldu!');
            location.reload();
        } else {
            window.NotificationManager.error(data.message || 'Tablo oluşturma hatası');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        window.NotificationManager.error('Tablo oluşturma hatası');
    });
}

// Check if tables exist on page load
document.addEventListener('DOMContentLoaded', function() {
    // Try to fetch shift schedules - if it fails, show alert
    fetch(`${baseUrl}${adminPrefix}/shifts?view=weekly&date=${selectedDate}`)
        .then(response => {
            if (!response.ok && response.status === 500) {
                document.getElementById('table-alert').classList.remove('hidden');
            }
        })
        .catch(() => {
            // If fetch fails, might be table issue
            document.getElementById('table-alert').classList.remove('hidden');
        });
});
</script>

