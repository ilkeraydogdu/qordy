<?php
require_once __DIR__ . '/../../helpers/translations.php';
require_once __DIR__ . '/../../helpers/role_helpers.php';

$roles = $roles ?? [];
$allPermissions = $all_permissions ?? $allPermissions ?? [];

/**
 * Returns a human-readable label for a permission key.
 * Priority: DB-stored permission_name → hardcoded fallback → the key itself.
 * The $dbNames map is built from system_permissions and passed as a global.
 */
function getPermissionTranslation($permissionKey, array $dbNames = []) {
    // 1. Use DB-stored name if available (dynamic, always up-to-date)
    if (!empty($dbNames[$permissionKey])) {
        return $dbNames[$permissionKey];
    }

    // 2. Hardcoded fallback for well-known permissions
    static $fallback = [
        'dashboard.view'             => 'Dashboard Görüntüle',
        'dashboard.analytics'        => 'Analitik Görüntüle',
        'menu.view'                  => 'Menü Görüntüle',
        'menu.create'                => 'Menü Öğesi Oluştur',
        'menu.edit'                  => 'Menü Öğesi Düzenle',
        'menu.delete'                => 'Menü Öğesi Sil',
        'menu.categories'            => 'Kategorileri Yönet',
        'orders.view'                => 'Siparişleri Görüntüle',
        'orders.create'              => 'Sipariş Oluştur',
        'orders.edit'                => 'Sipariş Düzenle',
        'orders.delete'              => 'Sipariş Sil',
        'orders.process'             => 'Sipariş İşle',
        'orders.complete'            => 'Sipariş Tamamla',
        'orders.approve'             => 'Sipariş Onayla',
        'tables.view'                => 'Masaları Görüntüle',
        'tables.manage'              => 'Masaları Yönet',
        'tables.transfer'            => 'Masaları Transfer Et',
        'table.history'              => 'Masa Geçmişini Görüntüle',
        'pos.view'                   => 'Kasa Görüntüle',
        'pos.process_payment'        => 'Ödeme İşle',
        'pos.refund'                 => 'İade İşle',
        'pos.dashboard'              => 'Kasa Dashboard',
        'kitchen.view'               => 'Mutfak Ekranı Görüntüle',
        'kitchen.update_status'      => 'Sipariş Durumu Güncelle',
        'kitchen.dashboard'          => 'Mutfak Dashboard',
        'kitchen.print'              => 'Mutfak Yazdır',
        'waiter.view'                => 'Garson Paneli Görüntüle',
        'waiter.dashboard'           => 'Garson Dashboard',
        'preparation-screens.view'   => 'Hazırlık Ekranları Görüntüle',
        'preparation-screens.create' => 'Hazırlık Ekranı Oluştur',
        'preparation-screens.edit'   => 'Hazırlık Ekranı Düzenle',
        'preparation-screens.delete' => 'Hazırlık Ekranı Sil',
        'reservations.view'          => 'Rezervasyonları Görüntüle',
        'reservations.create'        => 'Rezervasyon Oluştur',
        'reservations.edit'          => 'Rezervasyon Düzenle',
        'reservations.delete'        => 'Rezervasyon Sil',
        'finance.view'               => 'Finans Görüntüle',
        'finance.expenses'           => 'Giderleri Yönet',
        'finance.invoices'           => 'Faturaları Yönet',
        'finance.suppliers'          => 'Tedarikçileri Yönet',
        'finance.waste'              => 'Fire Kayıtlarını Yönet',
        'finance.shifts'             => 'Vardiyaları Yönet',
        'stock.view'                 => 'Stok Görüntüle',
        'stock.edit'                 => 'Stok Düzenle',
        'stock.movements'            => 'Stok Hareketleri',
        'stock.transfer'             => 'Stok Transfer',
        'staff.view'                 => 'Personel Görüntüle',
        'staff.create'               => 'Personel Oluştur',
        'staff.edit'                 => 'Personel Düzenle',
        'staff.delete'               => 'Personel Sil',
        'settings.view'              => 'Ayarları Görüntüle',
        'settings.edit'              => 'Ayarları Düzenle',
        'settings.reset'             => 'Sistemi Sıfırla',
        'reports.view'               => 'Raporları Görüntüle',
        'reports.export'             => 'Raporları Dışa Aktar',
        'printers.view'              => 'Yazıcıları Görüntüle',
        'printers.create'            => 'Yazıcı Oluştur',
        'printers.edit'              => 'Yazıcı Düzenle',
        'printers.delete'            => 'Yazıcı Sil',
        'printers.test'              => 'Yazıcı Test Et',
        'roles.view'                 => 'Rolleri Görüntüle',
        'roles.create'               => 'Rol Oluştur',
        'roles.edit'                 => 'Rol Düzenle',
        'roles.delete'               => 'Rol Sil',
        'permissions.view'           => 'İzinleri Görüntüle',
        'permissions.manage'         => 'İzinleri Yönet',
        'receipt.view'               => 'Fişleri Görüntüle',
        'receipt.print'              => 'Fiş Yazdır',
        'receipt.void'               => 'Fiş İptal Et',
        'receipt.refund'             => 'İade İşle',
        'receipt.settings.view'      => 'Fiş Ayarları Görüntüle',
    ];

    if (isset($fallback[$permissionKey])) {
        return $fallback[$permissionKey];
    }

    // 3. Auto-generate from key (for dynamic/unknown permissions)
    $parts  = explode('.', $permissionKey);
    $prefix = ucfirst(str_replace(['-', '_'], ' ', $parts[0] ?? $permissionKey));
    $action = isset($parts[1]) ? ucfirst(str_replace(['-', '_'], ' ', implode(' ', array_slice($parts, 1)))) : '';
    return $action ? "{$prefix} - {$action}" : $prefix;
}

// Check permissions (Manager bypass is now handled in Authorization class)
// Also check if user is Manager directly as fallback
$currentUserRole = getCurrentUserRole();
$currentRoleId = $_SESSION['role_id'] ?? null;

// More comprehensive Manager check - check both role code and role_id
$isManager = false;
if ($currentUserRole) {
    $normalizedRole = strtoupper(trim($currentUserRole));
    $isManager = ($normalizedRole === 'MANAGER' || $normalizedRole === 'ROLE_MANAGER');
}
if (!$isManager && $currentRoleId) {
    $normalizedRoleId = strtoupper(trim($currentRoleId));
    $isManager = ($normalizedRoleId === 'ROLE_MANAGER');
}

// Also check via Authorization class which has Manager bypass logic
if (!$isManager) {
    try {
        require_once __DIR__ . '/../../core/Authorization.php';
        $auth = \App\Core\Authorization::getInstance();
        $authRole = $auth->getCurrentRole();
        if ($authRole) {
            $normalizedAuthRole = strtoupper(trim($authRole));
            $isManager = ($normalizedAuthRole === 'MANAGER' || $normalizedAuthRole === 'ROLE_MANAGER');
        }
        // Check role_id via Authorization
        if (!$isManager) {
            $authRoleId = $auth->getCurrentRoleId();
            if ($authRoleId) {
                $normalizedAuthRoleId = strtoupper(trim($authRoleId));
                $isManager = ($normalizedAuthRoleId === 'ROLE_MANAGER');
            }
        }
    } catch (\Exception $e) {
        // Fallback to permission checks if Authorization fails
    }
}

// Debug: Check current user info
$debugInfo = [
    'currentUserRole' => $currentUserRole,
    'currentRoleId' => $currentRoleId,
    'isManager' => $isManager,
    'hasRolesView' => hasPermissionForRole('roles.view'),
    'hasRolesEdit' => hasPermissionForRole('roles.edit'),
    'hasRolesDelete' => hasPermissionForRole('roles.delete'),
    'session_user_id' => $_SESSION['user_id'] ?? 'not_set',
    'session_role' => $_SESSION['role'] ?? 'not_set',
    'session_role_id' => $_SESSION['role_id'] ?? 'not_set'
];
// Uncomment to debug:
// error_log("Roles Permissions Debug: " . json_encode($debugInfo));

// Always allow viewing if Manager, otherwise check permission
$canViewRoles = $isManager || hasPermissionForRole('roles.view');
// If Manager check failed but user is logged in, allow viewing (fallback)
if (!$canViewRoles && isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    // Fallback: allow viewing if logged in (permission check will happen in backend)
    $canViewRoles = true;
}

$canCreateRole = $isManager || hasPermissionForRole('roles.create');
$canEditRole = $isManager || hasPermissionForRole('roles.edit');
$canDeleteRole = $isManager || hasPermissionForRole('roles.delete');
$canManagePermissions = $isManager || hasPermissionForRole('permissions.manage');

// Build a lookup: permission_key → permission_name from DB (dynamic, takes priority over hardcoded translations)
$dbPermNameMap = [];
foreach ($allPermissions as $perm) {
    $k = $perm['permission_key'] ?? '';
    $n = $perm['permission_name'] ?? '';
    if ($k && $n) {
        $dbPermNameMap[$k] = $n;
    }
}

// Group permissions by category
$permissionGroups = [];
foreach ($allPermissions as $perm) {
    $key = $perm['permission_key'] ?? '';
    $parts = explode('.', $key);
    $category = $parts[0] ?? 'other';
    
    if (!isset($permissionGroups[$category])) {
        $permissionGroups[$category] = [];
    }
    
    $permissionGroups[$category][] = $perm;
}

$baseUrl = BASE_URL;
?>

<div class="q-page q-biz-theme animate-slide-up">
  <div class="q-container">

    <header class="q-page-header">
        <div>
            <p class="q-page-header__eyebrow">Personel</p>
            <h1 class="q-page-header__title"><?php echo t('roles.title', 'Rol ve Yetki Yönetimi'); ?></h1>
            <p class="q-page-header__subtitle">Rol tanımları, izin atamaları ve navigasyon senkronizasyonu.</p>
        </div>
    </header>
    
    <?php if (!$canViewRoles): ?>
    <section class="q-card q-card--pad">
        <p class="q-hint" style="color:var(--color-warning);font-weight:700;">Rol ve yetki listesini görüntüleme yetkiniz bulunmamaktadır.</p>
    </section>
    <?php else: ?>
    <section class="q-card q-card--pad">
            <div class="q-card__header">
                <h2 class="q-section-title" style="margin:0;">Roller</h2>
                <div style="display:flex;flex-wrap:wrap;gap:var(--space-2);">
                    <button type="button" onclick="fixMissingPermissions()" class="q-btn q-btn--secondary q-btn--sm">
                        Eksik İzinleri Ekle
                    </button>
                    <button type="button" id="sync-nav-permissions-btn" onclick="syncNavigationPermissions()" class="q-btn q-btn--secondary q-btn--sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                        İzinleri Senkronize Et
                    </button>
                    <button type="button" id="seed-role-permissions-btn" onclick="seedRolePermissions()" class="q-btn q-btn--secondary q-btn--sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                        Rol İzinlerini Güncelle
                    </button>
                    <?php if ($canCreateRole): ?>
                    <button type="button" onclick="showCreateRoleModal()" class="q-btn q-btn--primary q-btn--sm">
                        + Yeni Rol
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="q-table">
                <table>
                    <thead>
                        <tr>
                            <th>Rol Adı</th>
                            <th>Rol Kodu</th>
                            <th>İzin Sayısı</th>
                            <th class="q-table__actions">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody id="roles-table-body">
                        <?php foreach ($roles as $roleData): 
                            $role = $roleData['role'];
                            $permissions = $roleData['permissions'] ?? [];
                            $roleId = $role['role_id'] ?? '';
                            $isSystemRole = in_array($roleId, ['ROLE_WAITER', 'ROLE_KITCHEN', 'ROLE_CASHIER', 'ROLE_BUSINESS_MANAGER']);
                            // Debug: Log role info (can be removed later)
                            // error_log("Role: {$roleId}, isSystemRole: " . ($isSystemRole ? 'true' : 'false'));
                        ?>
                        <tr data-role-id="<?php echo htmlspecialchars($role['role_id']); ?>">
                            <td><?php echo htmlspecialchars($role['role_name'] ?? $role['role_id']); ?></td>
                            <td>
                                <span class="q-badge q-badge--neutral">
                                    <?php echo htmlspecialchars($role['role_code'] ?? ''); ?>
                                </span>
                            </td>
                            <td><?php echo count($permissions); ?></td>
                            <td class="q-table__actions">
                                <div style="display:flex;justify-content:flex-end;gap:var(--space-1);">
                                    <button type="button" onclick="editRolePermissions('<?php echo htmlspecialchars($role['role_id']); ?>')" 
                                            class="q-btn q-btn--ghost q-btn--icon"
                                            title="<?php echo t('titles.editPermissions'); ?>">
                                        <?php echo icon_settings(['class' => 'w-4 h-4']); ?>
                                    </button>
                                    <?php 
                                    // Manager can edit/delete system roles, others cannot
                                    $canEditSystemRole = $isManager || !$isSystemRole;
                                    $canDeleteSystemRole = $isManager || !$isSystemRole;
                                    
                                    $editButtonStyle = ($isSystemRole && !$isManager)
                                        ? "display: inline-flex !important; visibility: visible !important; opacity: 0.5 !important; cursor: not-allowed !important;" 
                                        : "display: inline-flex !important; visibility: visible !important; opacity: 1 !important;";
                                    $deleteButtonStyle = ($isSystemRole && !$isManager)
                                        ? "display: inline-flex !important; visibility: visible !important; opacity: 0.5 !important; cursor: not-allowed !important;" 
                                        : "display: inline-flex !important; visibility: visible !important; opacity: 1 !important;";
                                    
                                    $editTitle = ($isSystemRole && !$isManager) 
                                        ? 'Sistem rolleri düzenlenemez' 
                                        : t('titles.edit');
                                    $deleteTitle = ($isSystemRole && !$isManager) 
                                        ? 'Sistem rolleri silinemez' 
                                        : t('titles.delete');
                                    ?>
                                    <button type="button" onclick="editRole('<?php echo htmlspecialchars($role['role_id']); ?>')" 
                                            class="q-btn q-btn--ghost q-btn--icon"
                                            style="<?php echo $editButtonStyle; ?>"
                                            data-role-id="<?php echo htmlspecialchars($role['role_id']); ?>"
                                            data-is-system-role="<?php echo $isSystemRole ? 'true' : 'false'; ?>"
                                            title="<?php echo $editTitle; ?>"
                                            <?php if ($isSystemRole && !$isManager): ?>disabled<?php endif; ?>>
                                        <?php echo icon_edit(['class' => 'w-4 h-4']); ?>
                                    </button>
                                    <button type="button" onclick="deleteRole('<?php echo htmlspecialchars($role['role_id']); ?>')" 
                                            class="q-btn q-btn--ghost q-btn--icon q-btn--danger"
                                            style="<?php echo $deleteButtonStyle; ?>"
                                            data-role-id="<?php echo htmlspecialchars($role['role_id']); ?>"
                                            data-is-system-role="<?php echo $isSystemRole ? 'true' : 'false'; ?>"
                                            title="<?php echo $deleteTitle; ?>"
                                            <?php if ($isSystemRole && !$isManager): ?>disabled<?php endif; ?>>
                                        <?php echo icon_trash(['class' => 'w-4 h-4']); ?>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
    </section>
    <?php endif; ?>
  </div>
</div>

<!-- Create Role Modal -->
<div id="create-role-modal" class="q-modal-backdrop hidden" role="dialog" aria-modal="true">
    <div class="bg-white rounded-lg sm:rounded-xl md:rounded-2xl lg:rounded-3xl p-4 sm:p-6 md:p-8 lg:p-10 xl:p-12 max-w-md w-full animate-slide-up">
        <h2 class="text-lg sm:text-xl md:text-2xl font-black mb-4 sm:mb-5 md:mb-6 tracking-tighter">Yeni Rol Oluştur</h2>
        <form id="create-role-form" class="space-y-4 sm:space-y-5 md:space-y-6">
            <div>
                <label class="text-[7px] sm:text-[8px] md:text-[9px] lg:text-[10px] font-black text-slate-300 uppercase ml-2 tracking-widest block mb-1.5 sm:mb-2">ROL ADI</label>
                <input type="text" id="role-name" name="role_name" required
                       class="w-full p-3 sm:p-4 md:p-5 bg-slate-50 rounded-lg sm:rounded-xl md:rounded-2xl font-bold text-sm sm:text-base md:text-lg outline-none border-2 border-transparent focus:border-indigo-100 transition-all"/>
            </div>
            <div>
                <label class="text-[7px] sm:text-[8px] md:text-[9px] lg:text-[10px] font-black text-slate-300 uppercase ml-2 tracking-widest block mb-1.5 sm:mb-2">ROL KODU</label>
                <input type="text" id="role-code" name="role_code" required pattern="[A-Z0-9_]+"
                       class="w-full p-3 sm:p-4 md:p-5 bg-slate-50 rounded-lg sm:rounded-xl md:rounded-2xl font-bold text-sm sm:text-base md:text-lg outline-none border-2 border-transparent focus:border-indigo-100 transition-all uppercase"
                       placeholder="YENI_ROL"
                       oninput="this.value = this.value.toUpperCase().replace(/[^A-Z0-9_]/g, '')"/>
                <p class="text-[8px] sm:text-[9px] md:text-[10px] text-slate-400 mt-1 sm:mt-1.5">Sadece büyük harf, rakam ve alt çizgi kullanılabilir</p>
            </div>
            <div>
                <label class="text-[7px] sm:text-[8px] md:text-[9px] lg:text-[10px] font-black text-slate-300 uppercase ml-2 tracking-widest block mb-1.5 sm:mb-2">AÇIKLAMA</label>
                <textarea id="role-description" name="description" rows="3"
                          class="w-full p-3 sm:p-4 md:p-5 bg-slate-50 rounded-lg sm:rounded-xl md:rounded-2xl font-bold text-sm sm:text-base md:text-lg outline-none border-2 border-transparent focus:border-indigo-100 transition-all resize-none"></textarea>
            </div>
            <div class="flex flex-col sm:flex-row gap-2 sm:gap-3 md:gap-4">
                <button type="submit" class="flex-1 py-3 sm:py-4 md:py-5 bg-slate-900 text-white rounded-lg sm:rounded-xl md:rounded-2xl font-black text-sm sm:text-base md:text-lg shadow-2xl hover:scale-105 transition-all">
                    Oluştur
                </button>
                <button type="button" onclick="hideCreateRoleModal()" class="flex-1 py-3 sm:py-4 md:py-5 bg-slate-100 text-slate-700 rounded-lg sm:rounded-xl md:rounded-2xl font-black text-sm sm:text-base md:text-lg hover:bg-slate-200 transition-all">
                    İptal
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Role Modal -->
<div id="edit-role-modal" class="q-modal-backdrop hidden" role="dialog" aria-modal="true">
    <div class="bg-white rounded-lg sm:rounded-xl md:rounded-2xl lg:rounded-3xl p-4 sm:p-6 md:p-8 lg:p-10 xl:p-12 max-w-md w-full animate-slide-up">
        <h2 class="text-lg sm:text-xl md:text-2xl font-black mb-4 sm:mb-5 md:mb-6 tracking-tighter">Rol Düzenle</h2>
        <form id="edit-role-form" class="space-y-4 sm:space-y-5 md:space-y-6">
            <input type="hidden" id="edit-role-id" name="role_id">
            <div>
                <label class="text-[7px] sm:text-[8px] md:text-[9px] lg:text-[10px] font-black text-slate-300 uppercase ml-2 tracking-widest block mb-1.5 sm:mb-2">ROL ADI</label>
                <input type="text" id="edit-role-name" name="role_name" required
                       class="w-full p-3 sm:p-4 md:p-5 bg-slate-50 rounded-lg sm:rounded-xl md:rounded-2xl font-bold text-sm sm:text-base md:text-lg outline-none border-2 border-transparent focus:border-indigo-100 transition-all"/>
            </div>
            <div>
                <label class="text-[7px] sm:text-[8px] md:text-[9px] lg:text-[10px] font-black text-slate-300 uppercase ml-2 tracking-widest block mb-1.5 sm:mb-2">ROL KODU</label>
                <input type="text" id="edit-role-code" name="role_code" required pattern="[A-Z_]+"
                       class="w-full p-3 sm:p-4 md:p-5 bg-slate-50 rounded-lg sm:rounded-xl md:rounded-2xl font-bold text-sm sm:text-base md:text-lg outline-none border-2 border-transparent focus:border-indigo-100 transition-all uppercase"
                       placeholder="ROL_KODU"/>
                <p class="text-[8px] sm:text-[9px] md:text-[10px] text-slate-400 mt-1 sm:mt-1.5">Sistem rolleri için rol kodu değiştirilemez</p>
            </div>
            <div>
                <label class="text-[7px] sm:text-[8px] md:text-[9px] lg:text-[10px] font-black text-slate-300 uppercase ml-2 tracking-widest block mb-1.5 sm:mb-2">AÇIKLAMA</label>
                <textarea id="edit-role-description" name="description" rows="3"
                          class="w-full p-3 sm:p-4 md:p-5 bg-slate-50 rounded-lg sm:rounded-xl md:rounded-2xl font-bold text-sm sm:text-base md:text-lg outline-none border-2 border-transparent focus:border-indigo-100 transition-all resize-none"></textarea>
            </div>
            <div class="flex flex-col sm:flex-row gap-2 sm:gap-3 md:gap-4">
                <button type="submit" class="flex-1 py-3 sm:py-4 md:py-5 bg-slate-900 text-white rounded-lg sm:rounded-xl md:rounded-2xl font-black text-sm sm:text-base md:text-lg shadow-2xl hover:scale-105 transition-all">
                    Kaydet
                </button>
                <button type="button" onclick="hideEditRoleModal()" class="flex-1 py-3 sm:py-4 md:py-5 bg-slate-100 text-slate-700 rounded-lg sm:rounded-xl md:rounded-2xl font-black text-sm sm:text-base md:text-lg hover:bg-slate-200 transition-all">
                    İptal
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Permissions Modal -->
<div id="edit-permissions-modal" class="q-modal-backdrop hidden" role="dialog" aria-modal="true">
    <div class="bg-white rounded-lg sm:rounded-xl md:rounded-2xl lg:rounded-3xl p-4 sm:p-5 md:p-6 lg:p-8 xl:p-12 max-w-4xl w-full max-h-[90vh] overflow-hidden flex flex-col animate-slide-up">
        <h2 class="text-lg sm:text-xl md:text-2xl font-black mb-4 sm:mb-5 md:mb-6 tracking-tighter">İzinleri Düzenle</h2>
        <div class="flex-1 overflow-y-auto no-scrollbar">
            <div id="permissions-content" class="space-y-4 sm:space-y-5 md:space-y-6">
                <!-- Permissions will be loaded here -->
            </div>
        </div>
        <div class="flex flex-col sm:flex-row gap-2 sm:gap-3 md:gap-4 mt-4 sm:mt-5 md:mt-6 pt-4 sm:pt-5 md:pt-6 border-t">
            <button onclick="savePermissions()" class="flex-1 py-3 sm:py-4 md:py-5 bg-slate-900 text-white rounded-lg sm:rounded-xl md:rounded-2xl font-black text-sm sm:text-base md:text-lg shadow-2xl hover:scale-105 transition-all">
                Kaydet
            </button>
            <button type="button" onclick="hideEditPermissionsModal()" class="flex-1 py-3 sm:py-4 md:py-5 bg-slate-100 text-slate-700 rounded-lg sm:rounded-xl md:rounded-2xl font-black text-sm sm:text-base md:text-lg hover:bg-slate-200 transition-all">
                İptal
            </button>
        </div>
    </div>
</div>

<script>
const baseUrl = '<?php echo $baseUrl; ?>';
const isManager = <?php echo $isManager ? 'true' : 'false'; ?>;

// Helper function to check if current user is Manager
function checkIsManager() {
    return isManager === true;
}
const allPermissions = <?php echo safeJsonEncodeForJs($allPermissions ?? [], 'array'); ?>;
const permissionGroups = <?php echo safeJsonEncodeForJs($permissionGroups ?? [], 'object'); ?>;
let currentRoleId = null;


// Define helper functions early
function showEditRoleModal() {
    const modal = document.getElementById('edit-role-modal');
    if (modal) {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }
}

function hideEditRoleModal() {
    const modal = document.getElementById('edit-role-modal');
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }
    const form = document.getElementById('edit-role-form');
    if (form) form.reset();
}

// Define editRole and deleteRole functions early so they're available for onclick handlers
window.editRole = async function editRole(roleId) {
    // Check if it's a system role first
    const systemRoles = ['ROLE_WAITER', 'ROLE_KITCHEN', 'ROLE_CASHIER', 'ROLE_BUSINESS_MANAGER'];
    const isSystemRole = systemRoles.includes(roleId);
    
    // Allow Manager to edit system roles, block others
    if (isSystemRole && !checkIsManager()) {
        if (window.NotificationManager) window.NotificationManager.warning('Sistem rolleri düzenlenemez.');
        return;
    }
    
    const roleRow = document.querySelector(`tr[data-role-id="${roleId}"]`);
    if (!roleRow) {
        console.error('Role row not found for:', roleId);
        return;
    }
    
    const roleName = roleRow.querySelector('td').textContent.trim();
    const roleCode = roleRow.querySelector('td:nth-child(2) span').textContent.trim();
    
    // Get role description from data attribute or fetch from API
    let description = '';
    try {
        const response = await fetch(`${baseUrl}/api/qodmin/role-permissions?role_id=${roleId}`, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        
        if (response.ok) {
            const data = await response.json();
            if (data.role && data.role.description) {
                description = data.role.description;
            }
        } else {
            console.warn('Failed to fetch role data, using table data');
        }
    } catch (error) {
        console.warn('Error fetching role data, using table data:', error);
    }
    
    // Fill form fields - check if elements exist
    const editRoleIdInput = document.getElementById('edit-role-id');
    const editRoleNameInput = document.getElementById('edit-role-name');
    const editRoleCodeInput = document.getElementById('edit-role-code');
    const editRoleDescriptionInput = document.getElementById('edit-role-description');
    
    if (!editRoleIdInput || !editRoleNameInput || !editRoleCodeInput || !editRoleDescriptionInput) {
        console.error('Edit role modal form elements not found');
        if (window.NotificationManager) window.NotificationManager.error('Düzenleme formu bulunamadı. Sayfayı yenileyin.');
        return;
    }
    
    editRoleIdInput.value = roleId;
    editRoleNameInput.value = roleName;
    editRoleCodeInput.value = roleCode;
    editRoleDescriptionInput.value = description;
    
    // Disable role_code field for system roles (unless user is Manager)
    if (isSystemRole && !checkIsManager()) {
        editRoleCodeInput.disabled = true;
        editRoleCodeInput.classList.add('bg-slate-100', 'cursor-not-allowed');
    } else {
        editRoleCodeInput.disabled = false;
        editRoleCodeInput.classList.remove('bg-slate-100', 'cursor-not-allowed');
    }
    
    showEditRoleModal();
};

window.deleteRole = async function deleteRole(roleId) {
    // Check if it's a system role first
    const systemRoles = ['ROLE_WAITER', 'ROLE_KITCHEN', 'ROLE_CASHIER', 'ROLE_BUSINESS_MANAGER'];
    const isSystemRole = systemRoles.includes(roleId);
    
    // Allow Manager to delete system roles, block others
    if (isSystemRole && !checkIsManager()) {
        if (window.NotificationManager) window.NotificationManager.warning('Sistem rolleri silinemez. Bu roller uygulamanın çalışması için kritik öneme sahiptir.');
        return;
    }
    
    const deleteTitle = <?php echo json_encode(t('notifications.roleDelete', 'Rol Silme')); ?>;
    const deleteMessage = <?php echo json_encode(t('notifications.roleDeleteConfirm', 'Bu rolü silmek istediğinizden emin misiniz? Bu işlem geri alınamaz.')); ?>;
    
    let confirmed = false;
    if (window.NotificationManager && window.NotificationManager.confirm) {
        confirmed = await window.NotificationManager.confirm(
            deleteMessage || 'Bu rolü silmek istediğinizden emin misiniz? Bu işlem geri alınamaz.',
            deleteTitle || 'Rol Silme'
        );
    } else {
        confirmed = confirm(deleteMessage || 'Bu rolü silmek istediğinizden emin misiniz? Bu işlem geri alınamaz.');
    }
    if (!confirmed) return;
    
    const csrfToken = window.CSRF_TOKEN || (typeof csrf_token !== 'undefined' ? csrf_token : '') || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    
    try {
        const response = await fetch(`${baseUrl}/api/qodmin/delete-role?id=${roleId}`, {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': csrfToken
            }
        });
        
        if (!response.ok) {
            const errorText = await response.text();
            let errorData;
            try {
                errorData = JSON.parse(errorText);
            } catch (parseError) {
                // If parsing fails, create a structured error object
                errorData = { 
                    error: `HTTP ${response.status}: ${errorText.substring(0, 100)}`,
                    message: `Sunucu hatası (${response.status})`,
                    status: response.status
                };
            }
            
            // Extract error message with fallback
            let errorMessage = errorData.error || errorData.message || 'Rol silinirken bir hata oluştu.';
            
            // Ensure errorMessage is a string
            if (typeof errorMessage !== 'string') {
                errorMessage = String(errorMessage);
            }
            
            // Handle specific error types
            if (response.status === 403) {
                errorMessage = errorData.error || errorData.message || 'Bu işlem için yetkiniz bulunmamaktadır.';
                if (typeof errorMessage !== 'string') {
                    errorMessage = String(errorMessage);
                }
            } else if (response.status === 409) {
                errorMessage = errorData.error || errorData.message || 'Bu rol kullanıcılar tarafından kullanılıyor ve silinemez.';
                if (typeof errorMessage !== 'string') {
                    errorMessage = String(errorMessage);
                }
            } else if (response.status === 404) {
                errorMessage = errorData.error || errorData.message || 'Rol bulunamadı.';
                if (typeof errorMessage !== 'string') {
                    errorMessage = String(errorMessage);
                }
            }
            
            if (window.NotificationManager) window.NotificationManager.error(errorMessage);
            
            // Only log non-CSP errors
            if (typeof errorMessage === 'string' && 
                !errorMessage.toLowerCase().includes('content security policy') &&
                !errorMessage.toLowerCase().includes('csp')) {
                console.error('Delete role error:', errorData);
            }
            return;
        }
        
        let data;
        const responseText = await response.text();
        
        if (!responseText || responseText.trim() === '') {
            // Empty response, treat as success if status is OK
            if (response.ok) {
                if (window.NotificationManager) window.NotificationManager.success('Rol başarıyla silindi');
                setTimeout(() => {
                    location.reload();
                }, 500);
                return;
            }
        }
        
        try {
            data = JSON.parse(responseText);
        } catch (parseError) {
            // If JSON parsing fails, check if it's just "true" or "false"
            if (responseText.trim() === 'true' && response.ok) {
                if (window.NotificationManager) window.NotificationManager.success('Rol başarıyla silindi');
                setTimeout(() => {
                    location.reload();
                }, 500);
                return;
            }
            if (window.NotificationManager) window.NotificationManager.error('Rol silinirken bir hata oluştu.');
            return;
        }
        
        // Check if response indicates success
        // Success if: success === true OR (success !== false AND no error key AND response.ok)
        if (data.success === true || (data.success !== false && !data.error && response.ok)) {
            // Success response
            const successMessage = data.message || 'Rol başarıyla silindi';
            if (window.NotificationManager) window.NotificationManager.success(successMessage);
            setTimeout(() => {
                location.reload();
            }, 500);
        } else {
            let errorMessage = data.error || data.message || 'Rol silinirken bir hata oluştu.';
            if (typeof errorMessage !== 'string') {
                errorMessage = String(errorMessage);
            }
            if (data.translation_key && window.NotificationManager) {
                const translated = window.NotificationManager.translate(data.translation_key);
                if (translated && typeof translated === 'string') {
                    errorMessage = translated;
                }
            }
            if (window.NotificationManager) window.NotificationManager.error(errorMessage);
        }
    } catch (error) {
        // Filter CSP errors
        const errorMessage = error.message || error.toString() || '';
        const errorFilename = error.filename || error.source || '';
        const combinedErrorText = `${errorMessage} ${errorFilename}`.toLowerCase();
        
        // Ignore CSP and browser extension errors
        if (combinedErrorText.includes('content security policy') ||
            combinedErrorText.includes('csp directive') ||
            combinedErrorText.includes('unsafe-eval') ||
            combinedErrorText.includes('content.js') ||
            combinedErrorText.includes('extension://') ||
            combinedErrorText.includes('chrome-extension://') ||
            combinedErrorText.includes('moz-extension://')) {
            // Silently ignore CSP errors
            return;
        }
        
        // Log and show other errors
        console.error('Error deleting role:', error);
        if (window.NotificationManager) window.NotificationManager.error('Rol silinirken bir hata oluştu: ' + errorMessage);
    }
};

// Permission Türkçe çevirileri (JavaScript için)
const permissionTranslations = {
    // Dashboard
    'dashboard.view': 'Dashboard Görüntüle',
    'dashboard.analytics': 'Analitik Görüntüle',
    
    // Menu
    'menu.view': 'Menü Görüntüle',
    'menu.create': 'Menü Öğesi Oluştur',
    'menu.edit': 'Menü Öğesi Düzenle',
    'menu.delete': 'Menü Öğesi Sil',
    'menu.categories': 'Kategorileri Yönet',
    
    // Orders
    'orders.view': 'Siparişleri Görüntüle',
    'orders.create': 'Sipariş Oluştur',
    'orders.edit': 'Sipariş Düzenle',
    'orders.delete': 'Sipariş Sil',
    'orders.process': 'Sipariş İşle',
    'orders.complete': 'Sipariş Tamamla',
    
    // Tables
    'tables.view': 'Masaları Görüntüle',
    'tables.manage': 'Masaları Yönet',
    'tables.transfer': 'Masaları Transfer Et',
    'table.history': 'Masa Geçmişini Görüntüle',
    
    // POS
    'pos.view': 'POS Görüntüle',
    'pos.process_payment': 'Ödeme İşle',
    'pos.refund': 'İade İşle',
    
    // Kitchen
    'kitchen.view': 'Mutfak Ekranını Görüntüle',
    'kitchen.update_status': 'Sipariş Durumunu Güncelle',
    
    // Reservations
    'reservations.view': 'Rezervasyonları Görüntüle',
    'reservations.create': 'Rezervasyon Oluştur',
    'reservations.edit': 'Rezervasyon Düzenle',
    'reservations.delete': 'Rezervasyon Sil',
    
    // Finance
    'finance.view': 'Finans Görüntüle',
    'finance.expenses': 'Giderleri Yönet',
    'finance.invoices': 'Faturaları Yönet',
    'finance.suppliers': 'Tedarikçileri Yönet',
    'finance.waste': 'İsraf Kayıtlarını Yönet',
    'finance.shifts': 'Vardiyaları Yönet',
    
    // Staff
    'staff.view': 'Personeli Görüntüle',
    'staff.create': 'Personel Oluştur',
    'staff.edit': 'Personel Düzenle',
    'staff.delete': 'Personel Sil',
    
    // Settings
    'settings.view': 'Ayarları Görüntüle',
    'settings.edit': 'Ayarları Düzenle',
    'settings.reset': 'Sistemi Sıfırla',
    
    // Reports
    'reports.view': 'Raporları Görüntüle',
    'reports.export': 'Raporları Dışa Aktar',
    
    // Printers
    'printers.view': 'Yazıcıları Görüntüle',
    'printers.create': 'Yazıcı Oluştur',
    'printers.edit': 'Yazıcı Düzenle',
    'printers.delete': 'Yazıcı Sil',
    'printers.test': 'Yazıcı Bağlantısını Test Et',
    
    // Roles & Permissions
    'roles.view': 'Rolleri Görüntüle',
    'roles.create': 'Rol Oluştur',
    'roles.edit': 'Rol Düzenle',
    'roles.delete': 'Rol Sil',
    'permissions.view': 'İzinleri Görüntüle',
    'permissions.manage': 'İzinleri Yönet',
    
    // Receipt
    'receipt.view': 'Fişleri Görüntüle',
    'receipt.print': 'Fiş Yazdır',
    'receipt.void': 'Fiş İptal Et',
    'receipt.refund': 'İade İşle',
    
    // Waiter
    'waiter.view': 'Garson Dashboard Görüntüle',
    'waiter.manage_tables': 'Masaları Yönet',
    'waiter.view_notifications': 'Bildirimleri Görüntüle',
    
    // Stock
    'stock.view': 'Stok Görüntüle',
    'stock.edit': 'Stok Düzenle',
    'stock.movements': 'Stok Hareketlerini Görüntüle',
    'stock.transfer': 'Stok Transfer Et',
};

function getPermissionTranslationJS(permissionKey, defaultName) {
    // Mevcut dil kontrolü (session'dan veya cookie'den alınabilir)
    // Şimdilik her zaman Türkçe döndürüyoruz
    return permissionTranslations[permissionKey] || defaultName || permissionKey;
}

function showCreateRoleModal() {
    const modal = document.getElementById('create-role-modal');
    if (modal) {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }
}

function hideCreateRoleModal() {
    const modal = document.getElementById('create-role-modal');
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }
    const form = document.getElementById('create-role-form');
    if (form) form.reset();
}

function editRolePermissions(roleId) {
    currentRoleId = roleId;
    
    fetch(`${baseUrl}/api/qodmin/role-permissions?role_id=${roleId}`)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                if (window.NotificationManager) {
                    window.NotificationManager.error('Hata: ' + data.error);
                }
                return;
            }
            
            const currentPermissions = data.permissions || [];
            renderPermissionsModal(currentPermissions);
            showEditPermissionsModal();
        })
        .catch(error => {
            console.error('Error:', error);
            if (window.NotificationManager) {
                window.NotificationManager.error('İzinler yüklenirken bir hata oluştu.');
            }
        });
}

function renderPermissionsModal(currentPermissions) {
    const content = document.getElementById('permissions-content');
    if (!content) return;
    
    content.innerHTML = '';
    
    const roleRow = document.querySelector(`tr[data-role-id="${currentRoleId}"]`);
    const roleName = roleRow ? roleRow.querySelector('td').textContent.trim() : currentRoleId;
    
    content.innerHTML = `<h3 class="text-lg font-black mb-4">${escapeHtml(roleName)}</h3>`;
    
    for (const [category, perms] of Object.entries(permissionGroups)) {
        const groupDiv = document.createElement('div');
        groupDiv.className = 'mb-6';
        
        const categoryTitle = document.createElement('h4');
        categoryTitle.className = 'text-[7px] sm:text-[8px] md:text-[9px] lg:text-[10px] font-black text-slate-300 uppercase mb-2 sm:mb-3 tracking-widest';
        categoryTitle.textContent = category.toUpperCase();
        groupDiv.appendChild(categoryTitle);
        
        const permsContainer = document.createElement('div');
        permsContainer.className = 'grid grid-cols-1 sm:grid-cols-2 gap-2 sm:gap-3';
        
        perms.forEach(perm => {
            const permKey = perm.permission_key || perm['permission_key'];
            const permNameEn = perm.permission_name || perm['permission_name'] || permKey;
            const permName = getPermissionTranslationJS(permKey, permNameEn);
            const isChecked = currentPermissions.includes(permKey);
            
            const permDiv = document.createElement('div');
            permDiv.className = 'flex items-center gap-2 sm:gap-3 p-2 sm:p-3 bg-slate-50 rounded-lg sm:rounded-xl';
            
            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.id = `perm-${permKey}`;
            checkbox.value = permKey;
            checkbox.checked = isChecked;
            checkbox.className = 'w-4 h-4 sm:w-5 sm:h-5 rounded border-slate-300 text-slate-900 focus:ring-slate-500 shrink-0';
            
            const label = document.createElement('label');
            label.htmlFor = `perm-${permKey}`;
            label.className = 'flex-1 cursor-pointer min-w-0';
            
            const permKeySpan = document.createElement('div');
            permKeySpan.className = 'font-bold text-[10px] sm:text-xs text-slate-800 truncate';
            permKeySpan.textContent = permKey;
            
            const permNameSpan = document.createElement('div');
            permNameSpan.className = 'text-[9px] sm:text-[10px] text-slate-500 mt-0.5 sm:mt-1 line-clamp-1';
            permNameSpan.textContent = permName;
            
            label.appendChild(permKeySpan);
            label.appendChild(permNameSpan);
            
            permDiv.appendChild(checkbox);
            permDiv.appendChild(label);
            permsContainer.appendChild(permDiv);
        });
        
        groupDiv.appendChild(permsContainer);
        content.appendChild(groupDiv);
    }
}

function showEditPermissionsModal() {
    const modal = document.getElementById('edit-permissions-modal');
    if (modal) {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }
}

function hideEditPermissionsModal() {
    const modal = document.getElementById('edit-permissions-modal');
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }
    currentRoleId = null;
}

function savePermissions() {
    if (!currentRoleId) {
        if (window.NotificationManager) {
            window.NotificationManager.warning('Rol seçilmedi');
        }
        return;
    }
    
    const checkboxes = document.querySelectorAll('#permissions-content input[type="checkbox"]');
    const selectedPermissions = Array.from(checkboxes)
        .filter(cb => cb.checked)
        .map(cb => cb.value);
    
    const csrfToken = window.CSRF_TOKEN || (typeof csrf_token !== 'undefined' ? csrf_token : '');
    fetch(`${baseUrl}/api/qodmin/assign-permissions`, {
        method: 'POST',
        headers: { 
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify({
            role_id: currentRoleId,
            permissions: selectedPermissions
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.error) {
            if (window.NotificationManager) {
                window.NotificationManager.error('Hata: ' + data.error);
            }
        } else {
            if (window.NotificationManager) {
                window.NotificationManager.success('İzinler başarıyla güncellendi');
            }
            hideEditPermissionsModal();
            location.reload();
        }
    })
    .catch(error => {
        console.error('Error:', error);
        if (window.NotificationManager) {
            window.NotificationManager.error('İzinler kaydedilirken bir hata oluştu.');
        }
    });
}

// Edit Role Form Handler
const editRoleForm = document.getElementById('edit-role-form');
if (editRoleForm) {
    editRoleForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const roleId = document.getElementById('edit-role-id').value.trim();
        const roleName = document.getElementById('edit-role-name').value.trim();
        const roleCode = document.getElementById('edit-role-code').value.trim().toUpperCase();
        const description = document.getElementById('edit-role-description').value.trim();
        
        if (!roleId || !roleName || !roleCode) {
            if (window.NotificationManager) {
                window.NotificationManager.warning('Lütfen tüm zorunlu alanları doldurun.');
            }
            return;
        }
        
        // Check if it's a system role
        const systemRoles = ['ROLE_WAITER', 'ROLE_KITCHEN', 'ROLE_CASHIER', 'ROLE_BUSINESS_MANAGER'];
        const isSystemRole = systemRoles.includes(roleId);
        
        const updateData = {
            role_id: roleId,
            role_name: roleName,
            description: description
        };
        
        // Include role_code if it's not a system role OR if user is Manager
        if (!isSystemRole || checkIsManager()) {
            updateData.role_code = roleCode;
        }
        
        const csrfToken = window.CSRF_TOKEN || (typeof csrf_token !== 'undefined' ? csrf_token : '');
        try {
            const response = await fetch(`${baseUrl}/api/qodmin/update-role`, {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(updateData)
            });
            
            if (!response.ok) {
                const errorText = await response.text();
                let errorData;
                try {
                    errorData = JSON.parse(errorText);
                } catch {
                    errorData = { error: `HTTP ${response.status}: ${errorText.substring(0, 100)}` };
                }
                
                const errorMessage = errorData.error || errorData.message || 'Rol güncellenirken bir hata oluştu.';
                if (window.NotificationManager) window.NotificationManager.error(errorMessage);
                console.error('Update role error:', errorData);
                return;
            }
            
            const data = await response.json();
            if (data.error || data.success === false) {
                const errorMessage = data.error || data.message || 'Rol güncellenirken bir hata oluştu.';
                if (window.NotificationManager) window.NotificationManager.error(errorMessage);
            } else {
                if (window.NotificationManager) window.NotificationManager.success('Rol başarıyla güncellendi');
                hideEditRoleModal();
                setTimeout(() => {
                    location.reload();
                }, 500);
            }
        } catch (error) {
            console.error('Error updating role:', error);
            if (window.NotificationManager) window.NotificationManager.error('Rol güncellenirken bir hata oluştu: ' + error.message);
        }
    });
}

const createRoleForm = document.getElementById('create-role-form');
if (createRoleForm) {
    createRoleForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const roleName = document.getElementById('role-name').value.trim();
        const roleCode = document.getElementById('role-code').value.trim().toUpperCase();
        const description = document.getElementById('role-description').value.trim();
        
        if (!roleName || !roleCode) {
            if (window.NotificationManager) {
                window.NotificationManager.warning('Lütfen rol adı ve kodunu girin.');
            }
            return;
        }
        
        const roleId = 'ROLE_' + roleCode;
        
        const csrfToken = window.CSRF_TOKEN || (typeof csrf_token !== 'undefined' ? csrf_token : '');
        fetch(`${baseUrl}/api/qodmin/create-role`, {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify({
                role_id: roleId,
                role_name: roleName,
                role_code: roleCode,
                description: description
            })
        })
        .then(async response => {
            let data;
            let responseText = '';
            try {
                responseText = await response.text();
                console.log('Create role response:', responseText); // Debug log
                if (!responseText || responseText.trim() === '') {
                    data = { success: true };
                } else {
                    data = JSON.parse(responseText);
                }
            } catch (parseError) {
                console.error('Parse error:', parseError);
                console.error('Response text:', responseText);
                data = { error: 'Sunucudan geçersiz yanıt alındı. Lütfen konsolu kontrol edin.' };
            }
            
            if (!response.ok || data.error || data.success === false) {
                let errorMessage = data.error || data.message || 'Rol oluşturulamadı';
                if (typeof errorMessage !== 'string') {
                    errorMessage = String(errorMessage);
                }
                
                // Log to console for debugging
                if (console && console.error) {
                    console.error('Role creation error:', {
                        status: response.status,
                        statusText: response.statusText,
                        data: data
                    });
                }
                
                // Show error notification (backend may have already shown one, but show this one too for user feedback)
                if (window.NotificationManager) window.NotificationManager.error(errorMessage);
            } else {
                // Don't show notification here - backend already sent one
                hideCreateRoleModal();
                // Small delay to ensure backend cache is cleared
                setTimeout(() => {
                    location.reload();
                }, 300);
            }
        })
        .catch(error => {
            console.error('Network error:', error);
            const errorMessage = 'Rol oluşturulurken bir hata oluştu: ' + (error.message || 'Bilinmeyen hata');
            if (window.NotificationManager) window.NotificationManager.error(errorMessage);
        });
    });
}

// Use Utils.escapeHtml from utils.js (loaded globally)

function fixMissingPermissions() {
    if (window.NotificationManager) window.NotificationManager.info('Eksik permission\'lar ekleniyor, lütfen bekleyin...');
    
    fetch(`${baseUrl}/api/qodmin/add-printer-permissions`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.error) {
            if (window.NotificationManager) window.NotificationManager.error('Hata: ' + data.error);
        } else {
            if (window.NotificationManager) window.NotificationManager.success(data.message || 'Permission\'lar başarıyla eklendi!');
            setTimeout(() => {
                location.reload();
            }, 1500);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        if (window.NotificationManager) window.NotificationManager.error('Permission\'lar eklenirken bir hata oluştu.');
    });
}

/**
 * Sync system_permissions with navigation_items (dynamic permission discovery).
 * Creates missing permissions, removes orphaned ones, cleans up stale prep-screen permissions.
 */
function syncNavigationPermissions() {
    const btn = document.getElementById('sync-nav-permissions-btn');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<svg class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg> Senkronize ediliyor...';
    }

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content
        || (typeof window.getCsrfToken === 'function' ? window.getCsrfToken() : '');

    fetch('/api/qodmin/permissions/sync-nav', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify({})
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const s = data.stats || {};
            const msg = `İzinler senkronize edildi! ` +
                `Oluşturulan: ${s.created || 0}, ` +
                `Güncellenen: ${s.updated || 0}, ` +
                `Silinen: ${s.deleted || 0}` +
                (s.prep_screen_deleted ? `, Temizlenen ekran izni: ${s.prep_screen_deleted}` : '');
            if (window.NotificationManager) window.NotificationManager.success(msg);
            setTimeout(() => location.reload(), 2000);
        } else {
            if (window.NotificationManager) window.NotificationManager.error('Senkronizasyon hatası: ' + (data.error || 'Bilinmeyen hata'));
        }
    })
    .catch(err => {
        console.error('Sync error:', err);
        if (window.NotificationManager) window.NotificationManager.error('Senkronizasyon sırasında bir hata oluştu.');
    })
    .finally(() => {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg> İzinleri Senkronize Et';
        }
    });
}

/**
 * Seed / refresh BUSINESS_MANAGER and TRIAL role permissions in DB.
 * Ensures all business-feature permissions are assigned to both roles.
 */
function seedRolePermissions() {
    const btn = document.getElementById('seed-role-permissions-btn');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<svg class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg> Güncelleniyor...';
    }

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content
        || (typeof window.getCsrfToken === 'function' ? window.getCsrfToken() : '');

    fetch('/api/qodmin/navigation/seed-roles', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
        body: JSON.stringify({})
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            if (window.NotificationManager) window.NotificationManager.success('Rol izinleri güncellendi! Sayfa yenileniyor...');
            setTimeout(() => location.reload(), 2000);
        } else {
            if (window.NotificationManager) window.NotificationManager.error('Hata: ' + (data.error || 'Bilinmeyen hata'));
        }
    })
    .catch(err => {
        console.error('Seed roles error:', err);
        if (window.NotificationManager) window.NotificationManager.error('Bir hata oluştu.');
    })
    .finally(() => {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg> Rol İzinlerini Güncelle';
        }
    });
}
</script>

