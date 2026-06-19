<?php
require_once __DIR__ . '/../../helpers/translations.php';
require_once __DIR__ . '/../../helpers/role_helpers.php';
if (!function_exists('safeJsonEncodeForJs')) {
    require_once __DIR__ . '/../../helpers/json_helper.php';
}

$users = $users ?? [];
$rolesWithPermissions = $roles_with_permissions ?? [];
$allPermissions = $all_permissions ?? [];
$baseUrl = BASE_URL;
if (!function_exists('getAdminUrl')) {
    require_once __DIR__ . '/../../helpers/url_helper.php';
}
$isSuperAdmin = $is_super_admin ?? false;
$adminPrefix = $isSuperAdmin ? '/qodmin' : '/business';
$bsJsPath = dirname(__DIR__, 3) . '/public/assets/js/business-selector.js';
$bsJsVer = is_file($bsJsPath) ? (string) filemtime($bsJsPath) : '1';

// Translations for JavaScript
// Helper function to get translation with fallback
function getTranslation($key, $fallback) {
    $translation = t($key);
    // If translation returns the key itself (not found), use fallback
    return ($translation === $key || empty($translation)) ? $fallback : $translation;
}

$userTranslations = [
    'deleteConfirm' => getTranslation('users.deleteConfirm', 'Bu personeli silmek istediğinize emin misiniz?'),
    'staffDeleted' => getTranslation('users.staffDeleted', 'Personel silindi'),
    'deleteFailed' => getTranslation('users.deleteFailed', 'Personel silinemedi'),
    'fillAllFields' => getTranslation('users.fillAllFields', 'Lütfen tüm alanları doldurun'),
    'pinMustBe4Digits' => getTranslation('users.pinMustBe4Digits', 'PIN 4-10 haneli rakam olmalıdır'),
    'staffSaved' => getTranslation('users.staffSaved', 'Personel kaydedildi'),
    'saveFailed' => getTranslation('users.saveFailed', 'Kaydetme başarısız'),
    'editStaff' => getTranslation('users.editStaff', 'Personeli Düzenle'),
    'changePin' => getTranslation('users.changePin', 'PIN Değiştir'),
    'viewDetails' => getTranslation('users.viewDetails', 'Detayları Görüntüle'),
    'pinChanged' => getTranslation('users.pinChanged', 'PIN değiştirildi'),
    'staffUpdated' => getTranslation('users.staffUpdated', 'Personel güncellendi'),
    'showPin' => getTranslation('users.showPin', 'PIN\'i göster'),
    'hidePin' => getTranslation('users.hidePin', 'PIN\'i gizle'),
    'pinHashed' => getTranslation('users.pinHashed', 'Bu PIN hashlenmiş ve görüntülenemez'),
    'errorLoadingPin' => getTranslation('users.errorLoadingPin', 'PIN yüklenirken hata oluştu'),
];
?>

<div class="q-page q-biz-theme animate-slide-up">
  <div class="q-container">
    <?php if ($is_super_admin ?? false): ?>
    <!-- SUPER ADMIN VIEW: Business Selection First -->
    <div id="business-selection-view">
        <header class="q-page-header">
            <div>
                <p class="q-page-header__eyebrow">Personel</p>
                <h1 class="q-page-header__title">Personel Yönetimi</h1>
                <p class="q-page-header__subtitle">Personellerini görüntülemek istediğiniz işletmeyi seçin</p>
            </div>
            <div class="q-page-header__actions">
                <input type="text" id="business-search" placeholder="İşletme ara..." onkeyup="BusinessSelector.searchBusinesses(this.value)" class="q-input" style="min-width:14rem;">
            </div>
        </header>
        <div id="business-grid" class="q-grid q-grid--4">
            <div class="q-empty" style="grid-column:1/-1;">
                <div class="inline-block animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-indigo-500"></div>
                <p style="margin-top:var(--space-4);">İşletmeler yükleniyor...</p>
            </div>
        </div>
    </div>
    
    <!-- Staff Management View -->
    <div id="staff-management-view" class="hidden">
        <header class="q-page-header">
            <div style="display:flex;align-items:center;gap:var(--space-3);">
                <button type="button" onclick="backToBusinessSelection()" class="q-btn q-btn--ghost q-btn--icon" aria-label="Geri">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                </button>
                <div>
                    <p class="q-page-header__eyebrow">Personel</p>
                    <h1 class="q-page-header__title"><span id="selected-business-name"></span></h1>
                </div>
            </div>
        </header>
    <?php else: ?>
    <header class="q-page-header">
        <div>
            <p class="q-page-header__eyebrow">Personel</p>
            <h1 class="q-page-header__title">
                <span class="hidden sm:inline"><?php echo t('users.title'); ?></span>
                <span class="sm:hidden"><?php echo t('users.titleShort'); ?></span>
            </h1>
        </div>
    </header>
    <?php endif; ?>
    <div class="q-grid q-grid--3" style="align-items:start;">
        <section class="q-card q-card--pad" style="grid-column:span 2;">
            <div class="q-table">
                <table>
                    <thead>
                        <tr>
                            <th><?php echo t('users.name'); ?></th>
                            <th><?php echo t('users.role'); ?></th>
                            <th class="hidden sm:table-cell"><?php echo t('users.pin'); ?></th>
                            <th class="q-table__actions"><?php echo t('users.action'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="users-table-body">
                        <!-- Users will be loaded here -->
                    </tbody>
                </table>
            </div>
        </section>
        <?php if (hasPermissionForRole('staff.create')): ?>
        <section class="q-card q-card--pad q-stack" style="position:sticky;top:var(--space-4);">
            <h2 class="q-section-title"><?php echo t('users.newStaff'); ?></h2>
            <form id="add-user-form" class="q-stack">
                <?php echo csrf_field(); ?>
                <div class="q-field">
                    <label class="q-label" for="user-name"><?php echo t('users.name'); ?></label>
                    <input type="text" id="user-name" name="name" required class="q-input"/>
                </div>
                <div class="q-field">
                    <label class="q-label" for="user-pin"><?php echo t('users.pin'); ?></label>
                    <input type="password" id="user-pin" name="pin" required maxlength="10" pattern="[0-9]{4,10}" class="q-input" style="font-family:var(--font-mono);letter-spacing:0.15em;" placeholder="0000"/>
                </div>
                <div class="q-field">
                    <label class="q-label" for="user-role"><?php echo t('users.role'); ?></label>
                    <select id="user-role" name="role" required class="q-select">
                        <?php
                        // Check if user is superadmin
                        $isSuperAdmin = false;
                        try {
                            require_once __DIR__ . '/../../core/Authorization.php';
                            $auth = \App\Core\Authorization::getInstance();
                            $isSuperAdmin = $auth->isSuperAdmin();
                        } catch (\Exception $e) {
                            // Fallback check
                            $isSuperAdmin = isset($_SESSION['is_super_admin']) && $_SESSION['is_super_admin'] === true;
                        }
                        
                        $allRoles = getAllRoles();
                        $currentLang = getCurrentLanguage();
                        
                        // Allowed roles for business managers (non-superadmin)
                        $allowedBusinessRoles = ['WAITER', 'KITCHEN', 'CASHIER'];
                        
                        foreach ($allRoles as $role) {
                            $roleCode = $role['constant_key'] ?? $role['role_code'] ?? '';
                            $roleCodeUpper = strtoupper(trim($roleCode));
                            // Remove ROLE_ prefix for comparison
                            if (strpos($roleCodeUpper, 'ROLE_') === 0) {
                                $roleCodeUpper = substr($roleCodeUpper, 5);
                            }
                            
                            // If not superadmin, filter roles
                            if (!$isSuperAdmin) {
                                if (!in_array($roleCodeUpper, $allowedBusinessRoles)) {
                                    continue; // Skip this role
                                }
                            }
                            
                            $roleLabel = getRoleLabel($roleCode, $currentLang);
                            echo "<option value=\"{$roleCode}\">{$roleLabel}</option>";
                        }
                        
                        // Add preparation screens as selectable options for business managers
                        if (!$isSuperAdmin) {
                            $preparationScreens = $preparation_screens ?? [];
                            if (!empty($preparationScreens)) {
                                echo "<optgroup label=\"Hazırlık Ekranları\">";
                                foreach ($preparationScreens as $screen) {
                                    $screenName = htmlspecialchars($screen['name'] ?? $screen['screen_id'] ?? '');
                                    $screenId = htmlspecialchars($screen['screen_id'] ?? '');
                                    // Use special format: PREP_SCREEN_{screen_id}
                                    echo "<option value=\"PREP_SCREEN_{$screenId}\">{$screenName}</option>";
                                }
                                echo "</optgroup>";
                            }
                        }
                        ?>
                    </select>
                </div>
                <button type="submit" class="q-btn q-btn--primary q-btn--lg" style="width:100%;"><?php echo t('users.save'); ?></button>
            </form>
        </section>
        <?php endif; ?>
    </div>
    <?php if ($is_super_admin ?? false): ?>
    </div><!-- #staff-management-view -->
    <?php endif; ?>
  </div>
</div>

<!-- Edit Staff Modal -->
<div id="editStaffModal" class="q-modal-backdrop hidden" role="dialog" aria-modal="true">
    <div class="absolute inset-0 bg-slate-950/40 backdrop-blur-md" onclick="closeEditStaffModal()"></div>
    <div class="relative bg-white w-full max-w-md rounded-2xl sm:rounded-[40px] p-4 sm:p-6 lg:p-10 animate-slide-up shadow-2xl">
        <div class="flex justify-between items-center mb-4 sm:mb-6">
            <h2 class="text-xl sm:text-2xl lg:text-3xl font-black tracking-tighter"><?php echo getTranslation('users.editStaff', 'Personeli Düzenle'); ?></h2>
            <button onclick="closeEditStaffModal()" class="p-2 hover:bg-slate-100 rounded-lg transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        <form id="editStaffForm" class="space-y-4 sm:space-y-6">
            <input type="hidden" id="edit-user-id">
            <?php echo csrf_field(); ?>
            <div>
                <label class="text-[8px] sm:text-[9px] lg:text-[10px] font-black text-slate-300 uppercase ml-1 sm:ml-2 tracking-widest block mb-1 sm:mb-2"><?php echo escape(t('users.name') ?: 'Ad Soyad'); ?></label>
                <input type="text" id="edit-user-name" required
                       class="w-full p-3 sm:p-4 lg:p-6 bg-slate-50 rounded-xl sm:rounded-2xl lg:rounded-[30px] font-bold text-sm sm:text-base lg:text-lg outline-none border-2 lg:border-4 border-transparent focus:border-indigo-100 transition-all"/>
            </div>
            <div>
                <label class="text-[8px] sm:text-[9px] lg:text-[10px] font-black text-slate-300 uppercase ml-1 sm:ml-2 tracking-widest block mb-1 sm:mb-2"><?php echo escape(t('users.role') ?: 'Görev'); ?></label>
                <select id="edit-user-role" required
                        class="w-full p-3 sm:p-4 lg:p-6 bg-slate-50 rounded-xl sm:rounded-2xl lg:rounded-[30px] font-black text-sm sm:text-base outline-none border-2 lg:border-4 border-transparent focus:border-indigo-100 transition-all appearance-none">
                    <?php
                    // Check if user is superadmin
                    $isSuperAdmin = false;
                    try {
                        require_once __DIR__ . '/../../core/Authorization.php';
                        $auth = \App\Core\Authorization::getInstance();
                        $isSuperAdmin = $auth->isSuperAdmin();
                    } catch (\Exception $e) {
                        // Fallback check
                        $isSuperAdmin = isset($_SESSION['is_super_admin']) && $_SESSION['is_super_admin'] === true;
                    }
                    
                    $allRoles = $all_roles_db ?? getAllRoles();
                    $currentLang = getCurrentLanguage();
                    
                    // Allowed roles for business managers (non-superadmin)
                    $allowedBusinessRoles = ['WAITER', 'KITCHEN', 'CASHIER'];
                    
                    foreach ($allRoles as $role) {
                        $roleCode = $role['constant_key'] ?? $role['role_code'] ?? '';
                        $roleCodeUpper = strtoupper(trim($roleCode));
                        // Remove ROLE_ prefix for comparison
                        if (strpos($roleCodeUpper, 'ROLE_') === 0) {
                            $roleCodeUpper = substr($roleCodeUpper, 5);
                        }
                        
                        // If not superadmin, filter roles
                        if (!$isSuperAdmin) {
                            if (!in_array($roleCodeUpper, $allowedBusinessRoles)) {
                                continue; // Skip this role
                            }
                        }
                        
                        $roleLabel = getRoleLabel($roleCode, $currentLang);
                        echo "<option value=\"{$roleCode}\">{$roleLabel}</option>";
                    }
                    
                    // Add preparation screens as selectable options for business managers
                    if (!$isSuperAdmin) {
                        $preparationScreens = $preparation_screens ?? [];
                        if (!empty($preparationScreens)) {
                            echo "<optgroup label=\"Hazırlık Ekranları\">";
                            foreach ($preparationScreens as $screen) {
                                $screenName = htmlspecialchars($screen['name'] ?? $screen['screen_id'] ?? '');
                                $screenId = htmlspecialchars($screen['screen_id'] ?? '');
                                // Use special format: PREP_SCREEN_{screen_id}
                                echo "<option value=\"PREP_SCREEN_{$screenId}\">{$screenName}</option>";
                            }
                            echo "</optgroup>";
                        }
                    }
                    ?>
                </select>
            </div>
            <button type="submit" class="w-full py-3 sm:py-4 lg:py-6 bg-slate-900 text-white rounded-xl sm:rounded-2xl lg:rounded-[35px] font-black text-sm sm:text-base lg:text-xl shadow-2xl hover:scale-105 active:scale-95 transition-all">
                <?php echo escape(t('users.save') ?: 'KAYDET'); ?>
            </button>
        </form>
    </div>
</div>

<!-- Change PIN Modal -->
<div id="changePinModal" class="q-modal-backdrop hidden" role="dialog" aria-modal="true">
    <div class="absolute inset-0 bg-slate-950/40 backdrop-blur-md" onclick="closeChangePinModal()"></div>
    <div class="relative bg-white w-full max-w-md rounded-2xl sm:rounded-[40px] p-4 sm:p-6 lg:p-10 animate-slide-up shadow-2xl">
        <div class="flex justify-between items-center mb-4 sm:mb-6">
            <h2 class="text-xl sm:text-2xl lg:text-3xl font-black tracking-tighter"><?php echo getTranslation('users.changePin', 'PIN Değiştir'); ?></h2>
            <button onclick="closeChangePinModal()" class="p-2 hover:bg-slate-100 rounded-lg transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        <form id="changePinForm" class="space-y-4 sm:space-y-6">
            <input type="hidden" id="change-pin-user-id">
            <?php echo csrf_field(); ?>
            <div>
                <label class="text-[8px] sm:text-[9px] lg:text-[10px] font-black text-slate-300 uppercase ml-1 sm:ml-2 tracking-widest block mb-1 sm:mb-2"><?php echo t('users.newPin') ?? 'Yeni PIN'; ?></label>
                <input type="password" id="new-pin" required maxlength="10" pattern="[0-9]{4,10}"
                       class="w-full p-3 sm:p-4 lg:p-6 bg-slate-50 rounded-xl sm:rounded-2xl lg:rounded-[30px] font-mono text-sm sm:text-base lg:text-lg outline-none border-2 lg:border-4 border-transparent focus:border-indigo-100 transition-all tracking-widest"
                       placeholder="0000"/>
            </div>
            <button type="submit" class="w-full py-3 sm:py-4 lg:py-6 bg-slate-900 text-white rounded-xl sm:rounded-2xl lg:rounded-[35px] font-black text-sm sm:text-base lg:text-xl shadow-2xl hover:scale-105 active:scale-95 transition-all">
                <?php echo escape(t('users.save') ?: 'KAYDET'); ?>
            </button>
        </form>
    </div>
</div>

<script>
const baseUrl = <?php echo safeJsonEncodeForJs($baseUrl ?? BASE_URL, 'string'); ?>;
const userTranslations = <?php echo safeJsonEncodeForJs($userTranslations ?? [], 'object'); ?>;
let users = <?php echo safeJsonEncodeForJs($users ?? [], 'array'); ?>;
const rolesWithPermissions = <?php echo safeJsonEncodeForJs($rolesWithPermissions ?? [], 'object'); ?>;
const roleCodeToRoleId = <?php echo safeJsonEncodeForJs($role_code_to_role_id ?? [], 'object'); ?>;
const allPermissions = <?php echo safeJsonEncodeForJs($allPermissions ?? [], 'array'); ?>;
const preparationScreens = <?php echo safeJsonEncodeForJs($preparation_screens ?? [], 'array'); ?>;
// Determine if current user is business manager (not super admin)
const isBusinessManager = <?php echo json_encode(!($is_super_admin ?? false)); ?>;

// Role label mapping for JavaScript (fallback if API doesn't provide role_label)
const roleLabelMap = <?php
    $roleLabelMapJs = [];
    $allRolesForJs = $all_roles_db ?? getAllRoles();
    $currentLangJs = getCurrentLanguage();
    foreach ($allRolesForJs as $role) {
        $roleCode = strtoupper(trim($role['role_code'] ?? $role['constant_key'] ?? ''));
        // Remove ROLE_ prefix if exists
        if (strpos($roleCode, 'ROLE_') === 0) {
            $roleCode = substr($roleCode, 5);
        }
        $roleLabel = getRoleLabel($roleCode, $currentLangJs);
        $roleLabelMapJs[$roleCode] = $roleLabel;
        $roleLabelMapJs['ROLE_' . $roleCode] = $roleLabel;
    }
    echo json_encode($roleLabelMapJs, JSON_UNESCAPED_UNICODE);
?>;

// Permission translations (same as roles_permissions.php)
const permissionTranslations = {
    'dashboard.view': 'Dashboard Görüntüle',
    'dashboard.analytics': 'Analitik Görüntüle',
    'menu.view': 'Menü Görüntüle',
    'menu.create': 'Menü Öğesi Oluştur',
    'menu.edit': 'Menü Öğesi Düzenle',
    'menu.delete': 'Menü Öğesi Sil',
    'menu.categories': 'Kategorileri Yönet',
    'orders.view': 'Siparişleri Görüntüle',
    'orders.create': 'Sipariş Oluştur',
    'orders.edit': 'Sipariş Düzenle',
    'orders.delete': 'Sipariş Sil',
    'orders.process': 'Sipariş İşle',
    'orders.complete': 'Sipariş Tamamla',
    'tables.view': 'Masaları Görüntüle',
    'tables.manage': 'Masaları Yönet',
    'tables.transfer': 'Masaları Transfer Et',
    'table.history': 'Masa Geçmişini Görüntüle',
    'pos.view': 'POS Görüntüle',
    'pos.process_payment': 'Ödeme İşle',
    'pos.refund': 'İade İşle',
    'kitchen.view': 'Mutfak Ekranını Görüntüle',
    'kitchen.update_status': 'Sipariş Durumunu Güncelle',
    'reservations.view': 'Rezervasyonları Görüntüle',
    'reservations.create': 'Rezervasyon Oluştur',
    'reservations.edit': 'Rezervasyon Düzenle',
    'reservations.delete': 'Rezervasyon Sil',
    'finance.view': 'Finans Görüntüle',
    'finance.expenses': 'Giderleri Yönet',
    'finance.invoices': 'Faturaları Yönet',
    'finance.suppliers': 'Tedarikçileri Yönet',
    'finance.waste': 'İsraf Kayıtlarını Yönet',
    'finance.shifts': 'Vardiyaları Yönet',
    'staff.view': 'Personeli Görüntüle',
    'staff.create': 'Personel Oluştur',
    'staff.edit': 'Personel Düzenle',
    'staff.delete': 'Personel Sil',
    'settings.view': 'Ayarları Görüntüle',
    'settings.edit': 'Ayarları Düzenle',
    'settings.reset': 'Sistemi Sıfırla',
    'reports.view': 'Raporları Görüntüle',
    'reports.export': 'Raporları Dışa Aktar',
    'printers.view': 'Yazıcıları Görüntüle',
    'printers.create': 'Yazıcı Oluştur',
    'printers.edit': 'Yazıcı Düzenle',
    'printers.delete': 'Yazıcı Sil',
    'printers.test': 'Yazıcı Bağlantısını Test Et',
    'roles.view': 'Rolleri Görüntüle',
    'roles.create': 'Rol Oluştur',
    'roles.edit': 'Rol Düzenle',
    'roles.delete': 'Rol Sil',
    'permissions.view': 'İzinleri Görüntüle',
    'permissions.manage': 'İzinleri Yönet',
    'receipt.view': 'Fişleri Görüntüle',
    'receipt.print': 'Fiş Yazdır',
    'receipt.void': 'Fiş İptal Et',
    'receipt.refund': 'İade İşle',
    'waiter.view': 'Garson Dashboard Görüntüle',
    'waiter.manage_tables': 'Masaları Yönet',
    'waiter.view_notifications': 'Bildirimleri Görüntüle',
    'stock.view': 'Stok Görüntüle',
    'stock.edit': 'Stok Düzenle',
    'stock.movements': 'Stok Hareketlerini Görüntüle',
    'stock.transfer': 'Stok Transfer Et',
};

function getPermissionTranslation(permissionKey) {
    return permissionTranslations[permissionKey] || permissionKey;
}

function getPermissionName(permissionKey) {
    // Find permission in allPermissions array
    const perm = allPermissions.find(p => (p.permission_key || p['permission_key']) === permissionKey);
    return perm ? (perm.permission_name || perm['permission_name'] || permissionKey) : permissionKey;
}

// Ensure escapeHtml is available (from utils.js)
function escapeHtml(text) {
    if (typeof window.Utils !== 'undefined' && typeof window.Utils.escapeHtml === 'function') {
        return window.Utils.escapeHtml(text);
    }
    if (typeof window.escapeHtml === 'function') {
        return window.escapeHtml(text);
    }
    // Fallback implementation
    if (text === null || text === undefined) return '';
    const div = document.createElement('div');
    div.textContent = String(text);
    return div.innerHTML;
}

function isPinHashed(pin) {
    if (!pin || pin.length < 60) return false;
    return pin.startsWith('$2y$') || pin.startsWith('$2a$') || pin.startsWith('$2b$');
}

// Load users from server
async function loadUsers() {
    try {
        const adminPrefix = <?php echo json_encode($adminPrefix); ?>;
        
        // Add cache-busting parameter to ensure fresh data
        const cacheBuster = '?_=' + Date.now();
        const response = await fetch(`${baseUrl}${adminPrefix}/users${cacheBuster}`, {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'Cache-Control': 'no-cache'
            },
            credentials: 'same-origin'
        });
        
        if (!response.ok) {
            throw new Error('Failed to fetch users');
        }
        
        // If response is HTML (redirect), reload page
        const contentType = response.headers.get('content-type');
        if (contentType && contentType.includes('text/html')) {
            window.location.reload();
            return;
        }
        
        const data = await response.json();
        let usersData = null;
        
        // Check both response formats: {success: true, data: {users: [...]}} or {users: [...]}
        if (data.success && data.data && data.data.users) {
            usersData = data.data.users;
        } else if (data.success && data.users) {
            usersData = data.users;
        } else if (data.users) {
            // Direct users array
            usersData = data.users;
        } else {
            console.error('Failed to load users:', data.error || 'Unknown error');
            // Fallback: use initial users from PHP
            renderUsers();
            return;
        }
        
        // Add role labels if not present
        usersData = usersData.map(user => {
            if (!user.role_label && user.role) {
                const roleCodeForLookup = user.role.toUpperCase().replace(/^ROLE_/, '');
                user.role_label = roleLabelMap[roleCodeForLookup] || roleLabelMap[user.role.toUpperCase()] || user.role;
            }
            return user;
        });
        
        users = usersData;
        renderUsers();
    } catch (error) {
        console.error('Error loading users:', error);
        // Fallback: reload page
        window.location.reload();
    }
}

function renderUsers(usersToRender = null) {
    const tbody = document.getElementById('users-table-body');
    tbody.innerHTML = '';
    
    // Use provided users or fallback to global users variable
    const usersList = usersToRender || users;
    
    usersList.forEach(user => {
        // Ensure user object has required fields
        if (!user || typeof user !== 'object') {
            console.error('Invalid user object:', user);
            return;
        }
        
        // PIN artık API'den çekiliyor, bu yüzden her zaman revealHashedPin kullan
        const hasPin = user.has_pin !== false; // has_pin true veya undefined ise PIN var demektir
        
        // Ensure user_id exists
        const userId = user.user_id || user.id || '';
        if (!userId) {
            console.error('User ID not found for user:', user);
            return;
        }
        
        // Ensure name and role have default values
        const userName = user.name || '';
        const userRole = user.role || 'WAITER';
        
        // CRITICAL: If user has preparation_screen_id, show preparation screen name instead of role
        let userRoleLabel = user.role_label;
        const preparationScreenId = user.preparation_screen_id || null;
        
        // Backend already sets role_label to preparation screen name if preparation_screen_id exists
        // But we also check frontend fallback for safety
        if (preparationScreenId) {
            // First priority: use preparation_screen_name from backend if available
            if (user.preparation_screen_name) {
                userRoleLabel = user.preparation_screen_name;
            } 
            // Second priority: find in preparationScreens array
            else if (preparationScreens && Array.isArray(preparationScreens)) {
                const prepScreen = preparationScreens.find(screen => 
                    (screen.screen_id || screen.id || '') === preparationScreenId
                );
                if (prepScreen) {
                    userRoleLabel = prepScreen.name || prepScreen.screen_id || 'Hazırlık Ekranı';
                }
            }
            // Third priority: use role_label from backend (should already be set to prep screen name)
            // If still not set, fallback to role lookup
            if (!userRoleLabel || userRoleLabel === 'KITCHEN' || userRoleLabel === 'MUTFAK') {
                if (preparationScreens && Array.isArray(preparationScreens)) {
                    const prepScreen = preparationScreens.find(screen => 
                        (screen.screen_id || screen.id || '') === preparationScreenId
                    );
                    if (prepScreen) {
                        userRoleLabel = prepScreen.name || prepScreen.screen_id || 'Hazırlık Ekranı';
                    }
                }
            }
        }
        
        // Fallback: use role_label or lookup if still not set
        if (!userRoleLabel || (preparationScreenId && (userRoleLabel === 'KITCHEN' || userRoleLabel === 'MUTFAK'))) {
            const roleCodeForLookup = userRole.toUpperCase().replace(/^ROLE_/, '');
            const fallbackLabel = roleLabelMap[roleCodeForLookup] || roleLabelMap[userRole.toUpperCase()] || userRole;
            // Only use fallback if we don't have a preparation screen
            if (!preparationScreenId) {
                userRoleLabel = fallbackLabel;
            }
        }
        
        // PIN artık her zaman API'den çekiliyor - revealHashedPin kullan
        let pinHtml = '';
        if (hasPin) {
            // PIN var - API'den çekilecek
            pinHtml = `<span class="font-mono text-base lg:text-xl text-slate-300 tracking-widest pin-display" data-user-id="${userId}" data-pin="">****</span>
                       <button onclick="revealHashedPin('${userId}', this); event.stopPropagation();" class="p-2 text-slate-400 hover:text-slate-600 transition-all" title="${userTranslations.showPin || 'PIN\'i göster'}">
                           <svg class="w-4 h-4 pin-eye-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                           <svg class="w-4 h-4 pin-eye-off-icon hidden" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/><line x1="2" x2="22" y1="2" y2="22"/></svg>
                       </button>`;
        } else {
            // PIN yok
            pinHtml = `<span class="font-mono text-base lg:text-xl text-slate-300 tracking-widest">-</span>`;
        }
        
        const row = document.createElement('tr');
        row.className = 'hover:bg-slate-50/50 transition-all group cursor-pointer';
        row.onclick = (e) => {
            // Don't navigate if clicking on buttons or PIN area
            if (e.target.closest('button') || e.target.closest('.pin-display')) {
                return;
            }
            const adminPrefix = <?php echo json_encode($adminPrefix); ?>;
            window.location.href = `${baseUrl}${adminPrefix}/users/${userId}`;
        };
        row.innerHTML = `
            <td class="p-6 lg:p-10 font-black text-lg lg:text-2xl text-slate-800">${escapeHtml(userName)}</td>
            <td class="p-6 lg:p-10">
                <span class="px-3 lg:px-5 py-1.5 lg:py-2 bg-slate-100 rounded-xl text-[8px] lg:text-[10px] font-black uppercase tracking-widest">
                    ${escapeHtml(userRoleLabel)}
                </span>
            </td>
            <td class="p-6 lg:p-10">
                <div class="flex items-center gap-2" onclick="event.stopPropagation()">
                    ${pinHtml}
                </div>
            </td>
            <td class="p-6 lg:p-10 text-right">
                <div class="flex items-center justify-end gap-2" onclick="event.stopPropagation()">
                    <?php if (hasPermissionForRole('staff.edit')): ?>
                    <button onclick="event.stopPropagation(); editStaff('${userId}', '${escapeHtml(userName)}', '${escapeHtml(userRole)}', ${user.preparation_screen_id ? "'" + escapeHtml(user.preparation_screen_id) + "'" : 'null'})" 
                            class="p-3 lg:p-4 bg-blue-50 text-blue-400 rounded-xl lg:opacity-0 lg:group-hover:opacity-100 hover:bg-blue-500 hover:text-white transition-all"
                            title="${userTranslations.editStaff}">
                        <?php echo icon_edit(['class' => 'w-4 h-4 lg:w-6 lg:h-6']); ?>
                    </button>
                    <button onclick="event.stopPropagation(); changePin('${userId}')" 
                            class="p-3 lg:p-4 bg-indigo-50 text-indigo-400 rounded-xl lg:opacity-0 lg:group-hover:opacity-100 hover:bg-indigo-500 hover:text-white transition-all"
                            title="${userTranslations.changePin}">
                        <svg class="w-4 h-4 lg:w-6 lg:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                        </svg>
                    </button>
                    <?php endif; ?>
                    <?php if (hasPermissionForRole('staff.delete')): ?>
                    <button onclick="event.stopPropagation(); deleteUser('${userId}')" 
                            class="p-3 lg:p-4 bg-red-50 text-red-400 rounded-xl lg:opacity-0 lg:group-hover:opacity-100 hover:bg-red-500 hover:text-white transition-all"
                            title="Sil">
                        <?php echo icon_trash(['class' => 'w-4 h-4 lg:w-6 lg:h-6']); ?>
                    </button>
                    <?php endif; ?>
                </div>
            </td>
        `;
        tbody.appendChild(row);
    });
}

// Add event listeners for role changes
document.addEventListener('DOMContentLoaded', function() {
    // Role change listeners are no longer needed since preparation screen selection
    // is now handled directly in the role dropdown
});

function editStaff(userId, name, role, preparationScreenId = null) {
    document.getElementById('edit-user-id').value = userId;
    document.getElementById('edit-user-name').value = name;
    
    const editRoleSelect = document.getElementById('edit-user-role');
    
    // If user has a preparation screen, set the role dropdown to show the preparation screen option
    if (preparationScreenId && editRoleSelect) {
        // Check if there's a PREP_SCREEN option for this screen ID
        const prepScreenOption = Array.from(editRoleSelect.options).find(opt => 
            opt.value.toUpperCase() === `PREP_SCREEN_${preparationScreenId.toUpperCase()}`
        );
        
        if (prepScreenOption) {
            // Set role dropdown to preparation screen option
            editRoleSelect.value = prepScreenOption.value;
            // Store the preparation screen ID in dataset
            editRoleSelect.dataset.prepScreenId = preparationScreenId;
        } else {
            // Fallback: set to KITCHEN role
            editRoleSelect.value = role || 'KITCHEN';
        }
    } else {
        // No preparation screen, set role normally
        editRoleSelect.value = role || 'WAITER';
    }
    
    document.getElementById('editStaffModal').classList.remove('hidden');
    document.getElementById('editStaffModal').classList.add('flex');
}

// Preparation screen selection is now handled directly in the role dropdown
// No separate dropdown needed - removed to avoid duplication

function closeEditStaffModal() {
    document.getElementById('editStaffModal').classList.add('hidden');
    document.getElementById('editStaffModal').classList.remove('flex');
}

function changePin(userId) {
    document.getElementById('change-pin-user-id').value = userId;
    document.getElementById('new-pin').value = '';
    document.getElementById('changePinModal').classList.remove('hidden');
    document.getElementById('changePinModal').classList.add('flex');
}

function closeChangePinModal() {
    document.getElementById('changePinModal').classList.add('hidden');
    document.getElementById('changePinModal').classList.remove('flex');
}

async function deleteUser(id) {
    if (!id) {
        console.error('deleteUser: User ID is required');
        if (window.NotificationManager) {
            window.NotificationManager.error('Kullanıcı ID bulunamadı');
        }
        return;
    }
    
    if (!window.NotificationManager) {
        console.error('NotificationManager is not available');
        return;
    }
    
    const confirmed = await window.NotificationManager.confirm(
        userTranslations.deleteConfirm || 'Bu personeli silmek istediğinize emin misiniz?', 
        'Kullanıcı Silme'
    );
    if (!confirmed) {
        return;
    }
    
    // Get CSRF token
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || window.CSRF_TOKEN || '';
    
    const adminPrefix = <?php echo json_encode($adminPrefix); ?>;
    console.log('Deleting user:', id, 'URL:', `${baseUrl}${adminPrefix}/users/delete/${encodeURIComponent(id)}`);
    
    try {
        const response = await fetch(`${baseUrl}${adminPrefix}/users/delete/${encodeURIComponent(id)}`, {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            credentials: 'same-origin'
        });
        
        console.log('Delete response status:', response.status, 'ok:', response.ok);
        
        // Check if redirected
        if (response.redirected || response.status === 302) {
            window.location.href = response.url || `${baseUrl}/qodmin/users`;
            return;
        }
        
        // Get response text first
        const text = await response.text();
        console.log('Delete response text:', text);
        
        // Check if response is OK
        if (!response.ok) {
            let errorData;
            try {
                errorData = JSON.parse(text);
            } catch {
                errorData = { error: `HTTP ${response.status}: ${text.substring(0, 100)}` };
            }
            const errorMsg = errorData.error || errorData.message || (userTranslations.deleteFailed || 'Silme işlemi başarısız oldu');
            window.NotificationManager.error(errorMsg);
            return;
        }
        
        // Try to parse as JSON
        let data;
        if (text && text.trim() !== '') {
            try {
                data = JSON.parse(text);
            } catch (e) {
                console.error('JSON parse error:', e, 'Response text:', text);
                // If JSON parse fails but status is OK, assume success
                data = { success: true, message: userTranslations.staffDeleted || 'Personel silindi' };
            }
        } else {
            // Empty response, assume success
            data = { success: true, message: userTranslations.staffDeleted || 'Personel silindi' };
        }
        
        // Handle response
        if (data.error || data.success === false) {
            // Handle error response - error can be boolean true or string message
            let errorMsg = '';
            if (data.error === true || data.error === false) {
                errorMsg = data.message || data.translation_key || (userTranslations.deleteFailed || 'Silme işlemi başarısız oldu');
            } else {
                errorMsg = data.error || data.message || data.translation_key || (userTranslations.deleteFailed || 'Silme işlemi başarısız oldu');
            }
            
            // If user not found (404), remove from list anyway (already deleted)
            if (response.status === 404 || (data.message && (data.message.includes('bulunamadı') || data.message.includes('not found')))) {
                // User already deleted, remove from list
                users = users.filter(user => (user.user_id || user.id) !== id);
                renderUsers();
                window.NotificationManager.warning('Kullanıcı zaten silinmiş. Liste güncellendi.');
            } else {
                window.NotificationManager.error(errorMsg);
                console.error('Delete failed:', data);
            }
        } else {
            // Success - remove user from list immediately
            window.NotificationManager.success(data.message || userTranslations.staffDeleted || 'Personel silindi');
            
            // Remove user from local array
            const userIdToDelete = data.deleted_user_id || id;
            users = users.filter(user => (user.user_id || user.id) !== userIdToDelete);
            renderUsers();
        }
    } catch (error) {
        console.error('Delete user error:', error);
        const errorMsg = error.message || (userTranslations.deleteFailed || 'Silme işlemi başarısız oldu');
        window.NotificationManager.error(errorMsg);
        console.error('Delete user failed for ID:', id, 'Error:', error);
    }
}

document.getElementById('add-user-form').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const name = formData.get('name');
    const pin = formData.get('pin');
    let role = formData.get('role');
    let preparationScreenId = formData.get('preparation_screen_id') || null;
    
    // Check if a preparation screen is selected (format: PREP_SCREEN_{screen_id})
    const roleSelect = document.getElementById('user-role');
    console.log('DEBUG: Role value from form:', role);
    console.log('DEBUG: Role select element:', roleSelect);
    
    if (role && role.toUpperCase().startsWith('PREP_SCREEN_')) {
        // Extract screen ID from the role value
        preparationScreenId = role.replace(/^PREP_SCREEN_/i, '');
        // CRITICAL: Keep role as PREP_SCREEN format, backend will handle it
        // Don't change role to KITCHEN - backend will accept PREP_SCREEN format when preparation_screen_id is provided
        console.log('DEBUG: Extracted preparation_screen_id from PREP_SCREEN format:', preparationScreenId);
        console.log('DEBUG: Keeping role as PREP_SCREEN format for backend processing');
    }
    
    // Also check if preparation screen ID is stored in dataset
    if (roleSelect && roleSelect.dataset.prepScreenId && !preparationScreenId) {
        preparationScreenId = roleSelect.dataset.prepScreenId;
        console.log('DEBUG: Found preparation_screen_id in dataset:', preparationScreenId);
    }
    
    console.log('DEBUG: Final preparationScreenId:', preparationScreenId);
    console.log('DEBUG: Final role:', role);
    
    if (!name || !pin || !role) {
        window.NotificationManager.warning(userTranslations.fillAllFields);
        return;
    }
    
    if (pin.length < 4 || pin.length > 10 || !/^\d+$/.test(pin)) {
        window.NotificationManager.warning(userTranslations.pinMustBe4Digits);
        return;
    }
    
    // Get CSRF token
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || window.CSRF_TOKEN || '';
    
    try {
        const adminPrefix = <?php echo json_encode($adminPrefix); ?>;
        const requestBody = {
            name: name,
            pin: pin,
            role: role
        };
        
        // Add preparation_screen_id if provided
        if (preparationScreenId) {
            requestBody.preparation_screen_id = preparationScreenId;
            console.log('DEBUG: Adding preparation_screen_id to request:', preparationScreenId);
        } else {
            console.log('DEBUG: WARNING - preparationScreenId is empty, not adding to request');
        }
        
        console.log('DEBUG: Request body being sent:', JSON.stringify(requestBody, null, 2));
        
        const response = await fetch(`${baseUrl}${adminPrefix}/users/add`, {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            body: JSON.stringify(requestBody)
        });
        
        if (response.redirected || response.status === 302) {
            window.location.href = response.url || `${baseUrl}/qodmin/users`;
            return;
        }
        
        if (!response.ok) {
            const text = await response.text();
            let errorData;
            try {
                errorData = JSON.parse(text);
            } catch {
                errorData = { error: `HTTP ${response.status}: ${text.substring(0, 100)}` };
            }
            // Handle error response - error can be boolean true or string message
            let errorMsg = '';
            if (errorData.error === true || errorData.error === false) {
                errorMsg = errorData.message || errorData.translation_key || userTranslations.saveFailed;
            } else {
                errorMsg = errorData.error || errorData.message || errorData.translation_key || userTranslations.saveFailed;
            }
            window.NotificationManager.error(errorMsg);
            return;
        }
        
        const contentType = response.headers.get('content-type');
        if (contentType && contentType.includes('application/json')) {
            const text = await response.text();
            if (!text || text.trim() === '') {
                window.NotificationManager.success(userTranslations.staffSaved);
                setTimeout(() => {
                    window.location.href = window.location.pathname;
                }, 1000);
                return;
            }
            
            let data;
            try {
                data = JSON.parse(text);
            } catch (e) {
                console.error('JSON parse error:', e, 'Response text:', text);
                window.NotificationManager.success(userTranslations.staffSaved);
                setTimeout(() => {
                    window.location.href = window.location.pathname;
                }, 1000);
                return;
            }
            
            if (data.error || data.success === false) {
                // Handle error response - error can be boolean true or string message
                let errorMsg = '';
                if (data.error === true || data.error === false) {
                    errorMsg = data.message || data.translation_key || userTranslations.saveFailed;
                } else {
                    errorMsg = data.error || data.message || data.translation_key || userTranslations.saveFailed;
                }
                window.NotificationManager.error(errorMsg);
            } else if (data.success === true || data.message) {
                window.NotificationManager.success(data.message || userTranslations.staffSaved);
                // Clear form
                document.getElementById('add-user-form').reset();
                
                // Add new user to list immediately (if provided in response)
                if (data.user) {
                    users.push(data.user);
                    renderUsers();
                } else {
                    // Fallback: reload users list
                    loadUsers();
                }
            } else {
                window.NotificationManager.success(userTranslations.staffSaved);
                // Clear form
                document.getElementById('add-user-form').reset();
                // Reload users list
                loadUsers();
            }
        } else {
            window.NotificationManager.success(userTranslations.staffSaved);
            // Clear form
            document.getElementById('add-user-form').reset();
            // Reload users list
            loadUsers();
        }
    } catch (error) {
        console.error('Add user error:', error);
        const errorMsg = error.message || userTranslations.saveFailed || 'Kullanıcı ekleme işlemi başarısız oldu';
        window.NotificationManager.error(errorMsg);
        // Log to console for debugging
        console.error('Add user failed. Error:', error);
    }
});

document.getElementById('editStaffForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const userId = document.getElementById('edit-user-id').value;
    const name = document.getElementById('edit-user-name').value;
    let role = document.getElementById('edit-user-role').value;
    let preparationScreenId = document.getElementById('edit-preparation-screen-id')?.value || null;
    
    // Check if a preparation screen is selected (format: PREP_SCREEN_{screen_id})
    const roleSelect = document.getElementById('edit-user-role');
    if (role && role.toUpperCase().startsWith('PREP_SCREEN_')) {
        // Extract screen ID from the role value
        preparationScreenId = role.replace(/^PREP_SCREEN_/i, '');
        // CRITICAL: Keep role as PREP_SCREEN format, backend will handle it
        // Backend will extract preparation_screen_id and set role to KITCHEN
        console.log('DEBUG: Extracted preparation_screen_id from PREP_SCREEN format:', preparationScreenId);
    }
    
    // Also check if preparation screen ID is stored in dataset
    if (roleSelect && roleSelect.dataset.prepScreenId && !preparationScreenId) {
        preparationScreenId = roleSelect.dataset.prepScreenId;
        console.log('DEBUG: Found preparation_screen_id in dataset:', preparationScreenId);
    }
    
    if (!userId) {
        window.NotificationManager.error('Kullanıcı ID bulunamadı');
        return;
    }
    
    if (!name || !role) {
        window.NotificationManager.warning(userTranslations.fillAllFields || 'Lütfen tüm alanları doldurun');
        return;
    }
    
    // Get CSRF token
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || window.CSRF_TOKEN || '';
    
    // Determine API route based on user role
    const apiPrefix = isBusinessManager ? '/api/business' : '/api/qodmin';
    console.log('Updating user:', userId, 'URL:', `${baseUrl}${apiPrefix}/users/${encodeURIComponent(userId)}/update`);
    
    try {
        const requestBody = {
            name: name,
            role: role
        };
        
        // Add preparation_screen_id if provided
        if (preparationScreenId) {
            requestBody.preparation_screen_id = preparationScreenId;
        }
        
        const response = await fetch(`${baseUrl}${apiPrefix}/users/${encodeURIComponent(userId)}/update`, {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            body: JSON.stringify(requestBody),
            credentials: 'same-origin'
        });
        
        console.log('Update response status:', response.status, 'ok:', response.ok);
        
        if (response.redirected || response.status === 302) {
            window.location.href = response.url || `${baseUrl}/qodmin/users`;
            return;
        }
        
        // Get response text first
        const text = await response.text();
        console.log('Update response text:', text);
        
        if (!response.ok) {
            let errorData;
            try {
                errorData = JSON.parse(text);
            } catch {
                errorData = { error: `HTTP ${response.status}: ${text.substring(0, 100)}` };
            }
            
            // If user not found (404), remove from list anyway (already deleted)
            if (response.status === 404 || (errorData.message && errorData.message.includes('bulunamadı'))) {
                // User already deleted, remove from list
                users = users.filter(user => (user.user_id || user.id) !== userId);
                renderUsers();
                window.NotificationManager.warning('Kullanıcı bulunamadı. Zaten silinmiş olabilir. Liste güncellendi.');
                closeEditStaffModal();
                return;
            }
            
            // Handle error response - error can be boolean true or string message
            let errorMsg = '';
            if (errorData.error === true || errorData.error === false) {
                errorMsg = errorData.message || errorData.translation_key || (userTranslations.saveFailed || 'Güncelleme işlemi başarısız oldu');
            } else {
                errorMsg = errorData.error || errorData.message || errorData.translation_key || (userTranslations.saveFailed || 'Güncelleme işlemi başarısız oldu');
            }
            window.NotificationManager.error(errorMsg);
            return;
        }
        
        // Try to parse as JSON
        let data;
        if (text && text.trim() !== '') {
            try {
                data = JSON.parse(text);
            } catch (e) {
                console.error('JSON parse error:', e, 'Response text:', text);
                // If JSON parse fails but status is OK, assume success
                data = { success: true, message: userTranslations.staffUpdated || 'Personel güncellendi' };
            }
        } else {
            // Empty response, assume success
            data = { success: true, message: userTranslations.staffUpdated || 'Personel güncellendi' };
        }
        
        // Handle response
        if (data.error || data.success === false) {
            // If user not found (404), remove from list anyway (already deleted)
            if (response.status === 404 || (data.message && data.message.includes('bulunamadı'))) {
                // User already deleted, remove from list
                users = users.filter(user => (user.user_id || user.id) !== userId);
                renderUsers();
                window.NotificationManager.warning('Kullanıcı bulunamadı. Zaten silinmiş olabilir. Liste güncellendi.');
                closeEditStaffModal();
                return;
            }
            
            // Handle error response - error can be boolean true or string message
            let errorMsg = '';
            if (data.error === true || data.error === false) {
                errorMsg = data.message || data.translation_key || (userTranslations.saveFailed || 'Güncelleme işlemi başarısız oldu');
            } else {
                errorMsg = data.error || data.message || data.translation_key || (userTranslations.saveFailed || 'Güncelleme işlemi başarısız oldu');
            }
            window.NotificationManager.error(errorMsg);
            console.error('Update failed:', data);
        } else {
            // Success - update user in list immediately
            window.NotificationManager.success(data.message || userTranslations.staffUpdated || 'Personel güncellendi');
            
            // Update user in local array if provided
            if (data.user) {
                const userIndex = users.findIndex(u => (u.user_id || u.id) === userId);
                if (userIndex !== -1) {
                    users[userIndex] = { ...users[userIndex], ...data.user };
                } else {
                    // If not found, add it
                    users.push(data.user);
                }
                renderUsers();
            } else {
                // Fallback: reload page
                loadUsers();
            }
            
            closeEditStaffModal();
        }
    } catch (error) {
        console.error('Update staff error:', error);
        const errorMsg = error.message || (userTranslations.saveFailed || 'Güncelleme işlemi başarısız oldu');
        window.NotificationManager.error(errorMsg);
        console.error('Update staff failed for ID:', userId, 'Error:', error);
    }
});

document.getElementById('changePinForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const userId = document.getElementById('change-pin-user-id').value;
    const newPin = document.getElementById('new-pin').value;
    
    if (!userId) {
        window.NotificationManager.error('Kullanıcı ID bulunamadı');
        return;
    }
    
    if (!newPin || newPin.length < 4 || newPin.length > 10 || !/^\d+$/.test(newPin)) {
        window.NotificationManager.warning(userTranslations.pinMustBe4Digits);
        return;
    }
    
    // Get CSRF token
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || window.CSRF_TOKEN || '';
    
    try {
        // Determine API route based on user role
        const apiPrefix = isBusinessManager ? '/api/business' : '/api/qodmin';
        const response = await fetch(`${baseUrl}${apiPrefix}/users/${encodeURIComponent(userId)}/update-pin`, {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                pin: newPin
            })
        });
        
        if (response.redirected || response.status === 302) {
            window.location.href = response.url || `${baseUrl}/qodmin/users`;
            return;
        }
        
        if (!response.ok) {
            const text = await response.text();
            let errorData;
            try {
                errorData = JSON.parse(text);
            } catch {
                errorData = { error: `HTTP ${response.status}: ${text.substring(0, 100)}` };
            }
            // Handle error response - error can be boolean true or string message
            let errorMsg = '';
            if (errorData.error === true || errorData.error === false) {
                errorMsg = errorData.message || errorData.translation_key || userTranslations.saveFailed;
            } else {
                errorMsg = errorData.error || errorData.message || errorData.translation_key || userTranslations.saveFailed;
            }
            window.NotificationManager.error(errorMsg);
            return;
        }
        
        const contentType = response.headers.get('content-type');
        if (contentType && contentType.includes('application/json')) {
            const text = await response.text();
            if (!text || text.trim() === '') {
                window.NotificationManager.success(userTranslations.pinChanged);
                closeChangePinModal();
                setTimeout(() => {
                    window.location.href = window.location.pathname;
                }, 1000);
                return;
            }
            
            let data;
            try {
                data = JSON.parse(text);
            } catch (e) {
                console.error('JSON parse error:', e, 'Response text:', text);
                window.NotificationManager.success(userTranslations.pinChanged);
                closeChangePinModal();
                setTimeout(() => {
                    window.location.href = window.location.pathname;
                }, 1000);
                return;
            }
            
            if (data.error || data.success === false) {
                // Handle error response - error can be boolean true or string message
                let errorMsg = '';
                if (data.error === true || data.error === false) {
                    errorMsg = data.message || data.translation_key || userTranslations.saveFailed;
                } else {
                    errorMsg = data.error || data.message || data.translation_key || userTranslations.saveFailed;
                }
                window.NotificationManager.error(errorMsg);
            } else if (data.success === true || data.message) {
                window.NotificationManager.success(data.message || userTranslations.pinChanged);
                closeChangePinModal();
                setTimeout(() => {
                    window.location.href = window.location.pathname;
                }, 1000);
            } else {
                window.NotificationManager.success(userTranslations.pinChanged);
                closeChangePinModal();
                setTimeout(() => {
                    window.location.href = window.location.pathname;
                }, 1000);
            }
        } else {
            window.NotificationManager.success(userTranslations.pinChanged);
            closeChangePinModal();
            setTimeout(() => {
                window.location.href = window.location.href.split('?')[0] + '?t=' + Date.now();
            }, 1000);
        }
    } catch (error) {
        console.error('Change PIN error:', error);
        const errorMsg = error.message || userTranslations.saveFailed || 'PIN değiştirme işlemi başarısız oldu';
        window.NotificationManager.error(errorMsg);
        // Log to console for debugging
        console.error('Change PIN failed for ID:', userId, 'Error:', error);
    }
});

// Use Utils.escapeHtml from utils.js (loaded globally)

function togglePinVisibility(button) {
    const pinDisplay = button.closest('td').querySelector('.pin-display');
    const eyeIcon = button.querySelector('.pin-eye-icon');
    const eyeOffIcon = button.querySelector('.pin-eye-off-icon');
    const currentPin = pinDisplay.getAttribute('data-pin');
    
    if (pinDisplay.textContent === currentPin) {
        pinDisplay.textContent = '****';
        eyeIcon.classList.add('hidden');
        eyeOffIcon.classList.remove('hidden');
    } else {
        pinDisplay.textContent = currentPin;
        eyeIcon.classList.remove('hidden');
        eyeOffIcon.classList.add('hidden');
    }
}

async function revealHashedPin(userId, button) {
    if (!userId) {
        console.error('revealHashedPin: User ID is required');
        return;
    }
    
    // userId parametresini düzelt - eğer string değilse
    if (typeof userId === 'object') {
        userId = userId.user_id || userId.id;
    }
    
    const pinDisplay = button.closest('td').querySelector('.pin-display');
    const eyeIcon = button.querySelector('.pin-eye-icon');
    const eyeOffIcon = button.querySelector('.pin-eye-off-icon');
    
    if (!pinDisplay) {
        console.error('revealHashedPin: PIN display element not found');
        return;
    }
    
    // Eğer PIN zaten gösteriliyorsa gizle
    if (pinDisplay.textContent !== '****' && pinDisplay.getAttribute('data-pin')) {
        pinDisplay.textContent = '****';
        pinDisplay.setAttribute('data-pin', '');
        eyeIcon.classList.remove('hidden');
        eyeOffIcon.classList.add('hidden');
        return;
    }
    
    // PIN'i API'den çek
    try {
        // Determine API route based on user role
        const apiPrefix = isBusinessManager ? '/api/business' : '/api/qodmin';
        const response = await fetch(`${baseUrl}${apiPrefix}/users/${encodeURIComponent(userId)}/pin`);
        
        if (!response.ok) {
            const text = await response.text();
            let errorData;
            try {
                errorData = JSON.parse(text);
            } catch {
                errorData = { error: `HTTP ${response.status}: ${text.substring(0, 100)}` };
            }
            window.NotificationManager?.error(errorData.error || userTranslations.errorLoadingPin || 'PIN yüklenirken hata oluştu');
            return;
        }
        
        const data = await response.json();
        
        console.log('getStaffPin API response:', data); // Debug log
        
        if (data.error) {
            window.NotificationManager?.error(data.error || userTranslations.errorLoadingPin);
            return;
        }
        
        // Check if PIN exists and is not empty
        if (!data.pin || data.pin === '' || data.pin === null || data.pin === undefined) {
            console.error('getStaffPin: PIN is empty or null', data);
            window.NotificationManager?.error(userTranslations.errorLoadingPin || 'PIN bulunamadı');
            return;
        }
        
        // PIN'i göster (decrypt edilmiş veya hashlenmiş)
        pinDisplay.textContent = String(data.pin); // Ensure it's a string
        pinDisplay.setAttribute('data-pin', String(data.pin));
        
        if (data.is_hashed) {
            // Hashlenmiş PIN için stil ekle ve bildirim göster
            pinDisplay.classList.add('pin-hashed');
            if (window.NotificationManager) {
                window.NotificationManager.warning(
                    'Bu PIN hashlenmiş ve okunamaz. PIN\'i görmek için lütfen yeni bir PIN belirleyin.',
                    'PIN Gizli'
                );
            }
        } else {
            pinDisplay.classList.remove('pin-hashed');
        }
        
        eyeIcon.classList.add('hidden');
        eyeOffIcon.classList.remove('hidden');
    } catch (error) {
        console.error('Error fetching PIN:', error);
        window.NotificationManager?.error(userTranslations.errorLoadingPin || 'PIN yüklenirken hata oluştu');
    }
}

// Check if we need to open edit modal from URL parameter
window.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const editUserId = urlParams.get('edit');
    
    if (editUserId) {
        // Get user data from sessionStorage or fetch from API
        const editUserData = sessionStorage.getItem('editUser');
        if (editUserData) {
            try {
                const user = JSON.parse(editUserData);
                if (user.user_id === editUserId) {
                    editStaff(user.user_id, user.name || '', user.role || '', user.preparation_screen_id || null);
                    sessionStorage.removeItem('editUser');
                    // Remove edit parameter from URL
                    const newUrl = window.location.pathname + window.location.search.replace(/[?&]edit=[^&]*/, '').replace(/^&/, '?');
                    window.history.replaceState({}, '', newUrl);
                }
            } catch (e) {
                console.error('Error parsing edit user data:', e);
            }
        }
    }
});

// Initialize - load users from server
loadUsers();

// Role selection is handled by form submission

<?php if ($is_super_admin ?? false): ?>
// Super Admin: Load BusinessSelector
document.addEventListener('DOMContentLoaded', function() {
    const bsScript = document.createElement('script');
    bsScript.src = '<?php echo BASE_URL; ?>/assets/js/business-selector.js?v=<?php echo htmlspecialchars($bsJsVer, ENT_QUOTES, 'UTF-8'); ?>';
    bsScript.onload = function() {
        if (typeof BusinessSelector === 'undefined') return;
        
        BusinessSelector.init({ baseUrl: baseUrl });
        
        // Check if business_id is in URL (page reload scenario)
        const urlParams = new URLSearchParams(window.location.search);
        const businessIdFromUrl = urlParams.get('business_id');
        
        if (businessIdFromUrl) {
            // Business ID in URL - load business info directly from API and show staff view
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
                        
                        // Load staff for this business
                        loadBusinessStaff(businessIdFromUrl, businessName);
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
                    
                    // Load staff for this business
                    loadBusinessStaff(businessId, businessName);
                });
            });
        }
    };
    document.head.appendChild(bsScript);
});

window.backToBusinessSelection = function() {
    BusinessSelector.showSelectionView('business-selection-view', 'staff-management-view');
    const tbody = document.getElementById('users-table-body');
    if (tbody) tbody.innerHTML = '';
    
    // Remove business_id from URL
    const url = new URL(window.location.href);
    url.searchParams.delete('business_id');
    window.history.pushState({}, '', url.toString());
    
    // Clear session storage
    sessionStorage.removeItem('selected_business_id');
    sessionStorage.removeItem('selected_business_name');
    window.currentBusinessId = null;
};

function loadBusinessStaff(businessId, businessName) {
    const tbody = document.getElementById('users-table-body');
    if (tbody) {
        tbody.innerHTML = '<tr><td colspan="4" class="text-center py-12"><div class="inline-block animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-indigo-500"></div></td></tr>';
    }
    
    const apiPrefix = <?php echo json_encode($isSuperAdmin ? '/api/qodmin' : '/api/business'); ?>;
    fetch(`${baseUrl}${apiPrefix}/businesses/${businessId}/staff`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.staff) {
                window.currentBusinessId = businessId;
                
                // Add role labels if not present
                const staffWithLabels = data.staff.map(user => {
                    if (!user.role_label && user.role) {
                        const roleCodeForLookup = user.role.toUpperCase().replace(/^ROLE_/, '');
                        user.role_label = roleLabelMap[roleCodeForLookup] || roleLabelMap[user.role.toUpperCase()] || user.role;
                    }
                    return user;
                });
                
                // Update the global users variable
                window.allUsersData = staffWithLabels;
                users = staffWithLabels;
                // Render users
                renderUsers(staffWithLabels);
                BusinessSelector.showContentView('business-selection-view', 'staff-management-view', businessName);
            } else {
                if (window.NotificationManager) {
                    window.NotificationManager.error('Personel yüklenirken hata oluştu');
                }
            }
        })
        .catch(error => {
            console.error('Error loading staff:', error);
            if (window.NotificationManager) {
                window.NotificationManager.error('Personel yüklenirken hata oluştu');
            }
        });
}
<?php endif; ?>
</script>

