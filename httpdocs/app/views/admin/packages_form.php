<?php
require_once __DIR__ . '/../../helpers/translations.php';
require_once __DIR__ . '/../../helpers/ui.php';

$package = $package ?? null;
$navigationItems = $navigationItems ?? [];
$packagePermissionIds = $packagePermissionIds ?? [];
$packageRoles = $packageRoles ?? [];
$allRoles = $allRoles ?? [];

$isEditMode = $package !== null;
$baseUrl = BASE_URL;
if (!function_exists('getAdminUrl')) {
    require_once __DIR__ . '/../../helpers/url_helper.php';
}
$isSuperAdmin = $is_super_admin ?? false;
$adminPrefix = $isSuperAdmin ? '/qodmin' : '/business';

// Helper function to render navigation group
function renderPackageNavGroup($groupKey, $groupLabel, $groupIcon, $items, $packagePermissionIds) {
    if (empty($items)) return '';
    
    $groupId = 'package-nav-' . $groupKey;
    
    ob_start();
    ?>
    <div class="q-page q-biz-theme animate-slide-up">
  <div class="q-container">
        <div class="w-full flex items-center justify-between px-4 py-3 bg-slate-50 hover:bg-slate-100 transition-colors">
            <button type="button" 
                    onclick="togglePackageNavGroup('<?php echo $groupId; ?>')"
                    class="flex items-center gap-3 flex-1 text-left">
                <?php 
                $iconName = $groupIcon ?: 'Circle';
                echo getIcon($iconName, 'w-5 h-5 text-indigo-600');
                ?>
                <span class="text-sm font-bold text-slate-800"><?php echo htmlspecialchars($groupLabel); ?></span>
                <span class="text-xs text-slate-500">(<?php echo count($items); ?> menü)</span>
            </button>
            <div class="flex items-center gap-2">
                <button type="button" 
                        onclick="event.stopPropagation(); selectAllInGroup('<?php echo $groupId; ?>')"
                        class="text-xs font-bold text-indigo-600 hover:text-indigo-800 px-2 py-1 rounded transition-colors">
                    Tümünü Seç
                </button>
                <button type="button" onclick="togglePackageNavGroup('<?php echo $groupId; ?>')">
                    <svg id="chevron-<?php echo $groupId; ?>" 
                         class="w-5 h-5 text-slate-400 transition-transform duration-300 rotate-180" 
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                    </svg>
                </button>
            </div>
        </div>
        <div id="<?php echo $groupId; ?>" class="border-t border-slate-200 bg-white">
            <div class="p-3 space-y-2">
                <?php foreach ($items as $navItem): 
                    $navId = $navItem['nav_id'] ?? '';
                    $navLabel = $navItem['nav_label'] ?? $navId;
                    $permissions = $navItem['permissions'] ?? [];
                    $permissionPrefix = $navItem['permission_prefix'] ?? '';
                    $children = $navItem['children'] ?? [];
                    
                    $allSelected = true;
                    $selectedCount = 0;
                    foreach ($permissions as $perm) {
                        $permId = $perm['permission_id'] ?? '';
                        if (in_array($permId, $packagePermissionIds)) {
                            $selectedCount++;
                        } else {
                            $allSelected = false;
                        }
                    }
                    
                    $allChildrenSelected = true;
                    if (!empty($children)) {
                        foreach ($children as $child) {
                            foreach ($child['permissions'] ?? [] as $childPerm) {
                                $childPermId = $childPerm['permission_id'] ?? '';
                                if (!in_array($childPermId, $packagePermissionIds)) {
                                    $allChildrenSelected = false;
                                    break 2;
                                }
                            }
                        }
                    }
                ?>
                <div class="border border-slate-200 rounded-lg p-2 bg-slate-50">
                    <label class="flex items-center space-x-2 cursor-pointer hover:bg-white p-2 rounded transition-colors">
                        <input type="checkbox" 
                               class="package-nav-checkbox w-4 h-4 text-indigo-600 bg-white border-slate-300 rounded focus:ring-indigo-500"
                               data-group="<?php echo $groupId; ?>"
                               data-prefix="<?php echo htmlspecialchars($permissionPrefix); ?>"
                               data-nav-id="<?php echo htmlspecialchars($navId); ?>"
                               <?php echo ($allSelected && $allChildrenSelected) ? 'checked' : ''; ?>
                               onchange="togglePackageNavItem('<?php echo htmlspecialchars($permissionPrefix); ?>', '<?php echo htmlspecialchars($navId); ?>', this.checked, '<?php echo $groupId; ?>')">
                        <span class="text-sm font-bold text-slate-800 flex-1"><?php echo htmlspecialchars($navLabel); ?></span>
                    </label>
                    
                    <?php foreach ($permissions as $perm): 
                        $permId = $perm['permission_id'] ?? '';
                        // Skip empty permission IDs
                        if (empty($permId)) continue;
                    ?>
                    <input type="hidden" 
                           name="permissions[]" 
                           value="<?php echo htmlspecialchars($permId); ?>"
                           class="permission-input-<?php echo htmlspecialchars($permissionPrefix); ?> permission-input-group-<?php echo $groupId; ?>"
                           data-permission-id="<?php echo htmlspecialchars($permId); ?>"
                           data-nav-id="<?php echo htmlspecialchars($navId); ?>"
                           <?php echo in_array($permId, $packagePermissionIds) ? '' : 'disabled'; ?>>
                    <?php endforeach; ?>
                    
                    <?php if (!empty($children)): ?>
                    <div class="ml-6 mt-2 space-y-1 border-l-2 border-slate-200 pl-3">
                        <?php foreach ($children as $child): 
                            $childNavId = $child['nav_id'] ?? '';
                            $childNavLabel = $child['nav_label'] ?? $childNavId;
                            $childPermissions = $child['permissions'] ?? [];
                            $childPrefix = $child['permission_prefix'] ?? '';
                            
                            $childAllSelected = true;
                            foreach ($childPermissions as $childPerm) {
                                $childPermId = $childPerm['permission_id'] ?? '';
                                if (!in_array($childPermId, $packagePermissionIds)) {
                                    $childAllSelected = false;
                                    break;
                                }
                            }
                        ?>
                        <label class="flex items-center space-x-2 cursor-pointer hover:bg-white p-1.5 rounded transition-colors">
                            <input type="checkbox" 
                                   class="package-nav-checkbox w-4 h-4 text-indigo-600 bg-white border-slate-300 rounded focus:ring-indigo-500"
                                   data-group="<?php echo $groupId; ?>"
                                   data-prefix="<?php echo htmlspecialchars($childPrefix); ?>"
                                   data-nav-id="<?php echo htmlspecialchars($childNavId); ?>"
                                   data-parent-id="<?php echo htmlspecialchars($navId); ?>"
                                   <?php echo $childAllSelected ? 'checked' : ''; ?>
                                   onchange="togglePackageNavItem('<?php echo htmlspecialchars($childPrefix); ?>', '<?php echo htmlspecialchars($childNavId); ?>', this.checked, '<?php echo $groupId; ?>')">
                            <span class="text-xs font-semibold text-slate-700"><?php echo htmlspecialchars($childNavLabel); ?></span>
                        </label>
                        <?php foreach ($childPermissions as $childPerm): 
                            $childPermId = $childPerm['permission_id'] ?? '';
                            // Skip empty permission IDs
                            if (empty($childPermId)) continue;
                        ?>
                        <input type="hidden" 
                               name="permissions[]" 
                               value="<?php echo htmlspecialchars($childPermId); ?>"
                               class="permission-input-<?php echo htmlspecialchars($childPrefix); ?> permission-input-group-<?php echo $groupId; ?>"
                               data-permission-id="<?php echo htmlspecialchars($childPermId); ?>"
                               data-nav-id="<?php echo htmlspecialchars($childNavId); ?>"
                               data-parent-id="<?php echo htmlspecialchars($navId); ?>"
                               <?php echo in_array($childPermId, $packagePermissionIds) ? '' : 'disabled'; ?>>
                        <?php endforeach; ?>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
?>

<style>
.pkg-step-panel { display: none; }
.pkg-step-panel.active { display: block; }
.step-dot { transition: all .25s; }
.step-dot.done { background: #22c55e; color: #fff; }
.step-dot.current { background: #6366f1; color: #fff; box-shadow: 0 0 0 4px #e0e7ff; }
.step-dot.todo { background: #e2e8f0; color: #94a3b8; }
</style>

<div class="q-page q-biz-theme animate-slide-up min-w-0">
  <div class="q-container q-stack q-stack--lg min-w-0">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6 sm:mb-8">
        <div class="flex items-center gap-3">
            <a href="<?php echo getAdminUrl('packages'); ?>" class="p-2.5 hover:bg-white rounded-xl transition-all border border-transparent hover:border-slate-200 hover:shadow-sm">
                <svg class="w-5 h-5 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
            </a>
            <div>
                <p class="text-[10px] font-black uppercase tracking-[0.18em] text-indigo-500 mb-0.5">Paket Yönetimi</p>
                <h1 class="text-xl sm:text-2xl lg:text-3xl font-black tracking-tight text-slate-900">
                    <?php echo $isEditMode ? 'Paket Düzenle' : 'Yeni Paket Oluştur'; ?>
                </h1>
            </div>
        </div>
    </div>

    <!-- Wizard Step Indicator -->
    <div class="max-w-3xl mx-auto mb-6">
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 sm:p-5 flex items-center justify-between relative">
            <!-- line -->
            <div class="absolute left-0 right-0 top-1/2 mx-16 h-0.5 bg-slate-200 -translate-y-1/2 z-0 pointer-events-none"></div>
            <?php
            // Paket artık rol bazlı çalışıyor — "Menü Yetkileri" adımı
            // kaldırıldı. Menü erişimi doğrudan rollerin sahip olduğu
            // permission'lardan hesaplanıyor (packages_form.php altındaki
            // "Paket Rolleri" paneli edit modunda görünür).
            $steps = [
                ['label' => 'Genel Bilgiler', 'sub' => 'Ad & açıklama'],
                ['label' => 'Fiyat & Dönemler', 'sub' => 'Fiyatlandırma'],
            ];
            foreach ($steps as $si => $st):
            ?>
            <button type="button" onclick="wizardGo(<?php echo $si + 1; ?>)" class="relative z-10 flex flex-col items-center gap-1.5 flex-1 cursor-pointer group">
                <div id="step-dot-<?php echo $si + 1; ?>" class="step-dot w-9 h-9 rounded-full flex items-center justify-center text-sm font-black <?php echo $si === 0 ? 'current' : 'todo'; ?>">
                    <span id="step-dot-num-<?php echo $si + 1; ?>"><?php echo $si + 1; ?></span>
                </div>
                <span class="text-[11px] font-black text-slate-700 hidden sm:block"><?php echo $st['label']; ?></span>
                <span class="text-[9px] text-slate-400 hidden md:block"><?php echo $st['sub']; ?></span>
            </button>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Form Container -->
    <div class="max-w-3xl mx-auto bg-white rounded-2xl shadow-md border border-slate-200 overflow-hidden">
        <form id="package-form" method="POST" action="<?php echo $isEditMode ? getAdminUrl('packages/' . htmlspecialchars($package['package_id'] ?? '')) : getAdminUrl('packages'); ?>" novalidate>
            <?php echo csrf_field(); ?>
            <?php if ($isEditMode): ?>
            <input type="hidden" name="_method" value="PUT">
            <?php endif; ?>
            <input type="hidden" id="package-id" name="package_id" value="<?php echo $isEditMode ? htmlspecialchars($package['package_id'] ?? '') : ''; ?>">
            <input type="hidden" id="billing_options_json" name="billing_options">

            <!-- ── ADIM 1: Genel ── -->
            <div id="pkg-step-panel-1" class="pkg-step-panel active p-6 sm:p-8 space-y-5">
                <div class="flex items-center gap-2 pb-3 border-b border-slate-100">
                    <div class="w-7 h-7 bg-indigo-100 rounded-lg flex items-center justify-center flex-shrink-0">
                        <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                    </div>
                    <div>
                        <h2 class="text-base font-black text-slate-800">Genel Bilgiler</h2>
                        <p class="text-xs text-slate-500">Paketin adı ve kısa açıklaması</p>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-black text-slate-500 uppercase mb-1.5 tracking-widest">Paket Adı <span class="text-red-500">*</span></label>
                    <input type="text" name="name" id="package-name"
                           value="<?php echo $isEditMode ? htmlspecialchars($package['name'] ?? '') : ''; ?>"
                           placeholder="Örn: PRO PLUS, Temel, Kurumsal…"
                           class="w-full px-4 py-3 bg-slate-50 rounded-xl font-bold text-base outline-none border-2 border-transparent focus:border-indigo-500 focus:bg-white transition-all"/>
                    <p id="pkg-name-err" class="text-xs text-red-500 mt-1 hidden">Paket adı zorunludur.</p>
                </div>

                <div>
                    <div class="flex justify-between items-center mb-1.5">
                        <label class="block text-xs font-black text-slate-500 uppercase tracking-widest">Açıklama</label>
                        <button type="button" onclick="generateDescriptionWithGemini()" id="generate-description-btn"
                                class="px-3 py-1.5 bg-purple-500 hover:bg-purple-600 text-white rounded-lg text-xs font-bold transition-all flex items-center gap-1.5">
                            <span>✨</span><span>AI ile Oluştur</span>
                        </button>
                    </div>
                    <textarea name="description" id="package-description" rows="4"
                              placeholder="Bu paket ile işletmeler neler yapabilir? Kısa ve etkileyici yazın."
                              class="w-full px-4 py-3 bg-slate-50 rounded-xl text-sm outline-none border-2 border-transparent focus:border-indigo-500 focus:bg-white transition-all resize-none"><?php echo $isEditMode ? htmlspecialchars($package['description'] ?? '') : ''; ?></textarea>
                </div>

                <!-- Flags -->
                <div class="flex flex-wrap gap-6 pt-2">
                    <label class="flex items-center gap-2.5 cursor-pointer select-none">
                        <input type="checkbox" name="auto_renew" id="package-auto-renew" value="1"
                               <?php echo ($isEditMode && !empty($package['auto_renew'])) ? 'checked' : ''; ?>
                               class="w-4 h-4 text-indigo-600 rounded focus:ring-indigo-500">
                        <span class="text-sm font-bold text-slate-700">Otomatik yenileme</span>
                    </label>
                    <label class="flex items-center gap-2.5 cursor-pointer select-none">
                        <input type="checkbox" name="is_active" id="package-is-active" value="1"
                               <?php echo (!$isEditMode || !empty($package['is_active'])) ? 'checked' : ''; ?>
                               class="w-4 h-4 text-indigo-600 rounded focus:ring-indigo-500">
                        <span class="text-sm font-bold text-slate-700">Aktif (satışa açık)</span>
                    </label>
                </div>

                <div class="flex justify-end pt-2">
                    <button type="button" onclick="wizardNext(1)" class="px-7 py-3 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-black text-sm shadow-md transition-all">
                        Devam: Fiyatlandırma →
                    </button>
                </div>
            </div>

            <!-- ── ADIM 2: Fiyat ── -->
            <div id="pkg-step-panel-2" class="pkg-step-panel p-6 sm:p-8 space-y-6">
                <div class="flex items-center gap-2 pb-3 border-b border-slate-100">
                    <div class="w-7 h-7 bg-emerald-100 rounded-lg flex items-center justify-center flex-shrink-0">
                        <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <div>
                        <h2 class="text-base font-black text-slate-800">Fiyat & Dönemler</h2>
                        <p class="text-xs text-slate-500">Standart fiyatlar ve özel ödeme dönemleri</p>
                    </div>
                </div>

                <!-- Standart fiyatlar -->
                <div>
                    <p class="text-xs font-black text-slate-500 uppercase tracking-widest mb-3">Standart Fiyatlar (₺)</p>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div class="bg-slate-50 rounded-xl p-4 border-2 border-slate-200 focus-within:border-indigo-400 transition-all">
                            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-wider mb-2">Tek Seferlik</label>
                            <div class="flex items-center gap-1">
                                <span class="text-lg font-black text-slate-400">₺</span>
                                <input type="number" name="price_one_time" id="package-price-one-time" step="0.01" min="0"
                                       value="<?php echo $isEditMode ? htmlspecialchars($package['price_one_time'] ?? '') : ''; ?>"
                                       placeholder="0,00"
                                       class="flex-1 bg-transparent outline-none text-xl font-black text-slate-800 w-full"/>
                            </div>
                        </div>
                        <div class="bg-slate-50 rounded-xl p-4 border-2 border-slate-200 focus-within:border-indigo-400 transition-all">
                            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-wider mb-2">Aylık</label>
                            <div class="flex items-center gap-1">
                                <span class="text-lg font-black text-slate-400">₺</span>
                                <input type="number" name="price_monthly" id="package-price-monthly" step="0.01" min="0"
                                       value="<?php echo $isEditMode ? htmlspecialchars($package['price_monthly'] ?? '') : ''; ?>"
                                       placeholder="0,00"
                                       class="flex-1 bg-transparent outline-none text-xl font-black text-slate-800 w-full"/>
                            </div>
                        </div>
                        <div class="bg-slate-50 rounded-xl p-4 border-2 border-emerald-200 focus-within:border-emerald-400 relative transition-all">
                            <span class="absolute top-2 right-2 text-[9px] font-black bg-emerald-100 text-emerald-700 px-1.5 py-0.5 rounded-full">Yıllık</span>
                            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-wider mb-2">Yıllık</label>
                            <div class="flex items-center gap-1">
                                <span class="text-lg font-black text-slate-400">₺</span>
                                <input type="number" name="price_yearly" id="package-price-yearly" step="0.01" min="0"
                                       value="<?php echo $isEditMode ? htmlspecialchars($package['price_yearly'] ?? '') : ''; ?>"
                                       placeholder="0,00"
                                       class="flex-1 bg-transparent outline-none text-xl font-black text-slate-800 w-full"/>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Süre ve indirim -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-black text-slate-500 uppercase mb-1.5 tracking-widest">Abonelik süresi (gün)</label>
                        <input type="number" name="duration_days" id="package-duration-days" min="1"
                               value="<?php echo $isEditMode ? htmlspecialchars($package['duration_days'] ?? '') : ''; ?>"
                               placeholder="Boş = Süresiz"
                               class="w-full px-4 py-3 bg-slate-50 rounded-xl font-bold text-sm outline-none border-2 border-transparent focus:border-indigo-500 transition-all"/>
                    </div>
                    <div>
                        <label class="block text-xs font-black text-slate-500 uppercase mb-1.5 tracking-widest">Genel indirim (%)</label>
                        <input type="number" name="discount_percentage" id="package-discount-percentage" step="0.01" min="0" max="100"
                               value="<?php echo $isEditMode ? htmlspecialchars($package['discount_percentage'] ?? '') : ''; ?>"
                               placeholder="0"
                               class="w-full px-4 py-3 bg-slate-50 rounded-xl font-bold text-sm outline-none border-2 border-transparent focus:border-indigo-500 transition-all"/>
                    </div>
                </div>

                <!-- Özel dönemler builder -->
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <div>
                            <p class="text-xs font-black text-slate-500 uppercase tracking-widest">Özel Faturalama Dönemleri</p>
                            <p class="text-[11px] text-slate-400 mt-0.5">3 aylık, 6 aylık gibi özel süreler ve indirimler tanımlayın</p>
                        </div>
                        <button type="button" onclick="addBillingRow()" class="flex items-center gap-1.5 px-3 py-2 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 rounded-lg text-xs font-black transition-all border border-indigo-200">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                            Dönem Ekle
                        </button>
                    </div>

                    <div id="billing-rows-container" class="space-y-2 mb-3">
                        <!-- Rows rendered by JS -->
                    </div>

                    <div id="billing-rows-empty" class="rounded-xl border-2 border-dashed border-slate-200 bg-slate-50/50 p-5 text-center hidden">
                        <p class="text-xs text-slate-500">Özel dönem tanımlanmadı — yalnızca aylık/yıllık standart fiyatlar kullanılacak.</p>
                    </div>
                    <div id="billing-rows-guide" class="rounded-xl border border-slate-200 bg-blue-50/60 p-3.5 text-xs text-blue-700 flex gap-2 items-start">
                        <svg class="w-3.5 h-3.5 text-blue-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span>Aylık fiyat: ₺1.200 iken 3 aylık: ₺3.240 (%10 indirimli) veya 6 aylık: ₺6.000 (%16 indirimli) gibi dönemler tanımlayabilirsiniz. Bu dönemler müşteri kayıt/ödeme ekranına yansıtılır.</span>
                    </div>
                </div>

                <div class="flex justify-between pt-2">
                    <button type="button" onclick="wizardGo(1)" class="px-5 py-3 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-bold text-sm transition-all">
                        ← Geri
                    </button>
                    <button type="submit" class="px-8 py-3 bg-gradient-to-r from-indigo-600 to-indigo-700 hover:from-indigo-700 hover:to-indigo-800 text-white rounded-xl font-black text-sm shadow-md hover:shadow-lg transition-all" id="pkg-submit-btn">
                        <?php echo $isEditMode ? '✓ Paketi Güncelle' : '✓ Paketi Oluştur'; ?>
                    </button>
                </div>
            </div>

        </form>

        <?php if ($isEditMode): ?>
        <!-- ─── Rol-bazlı paket yönetimi ─── -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 mt-6" id="package-roles-panel">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="text-lg font-black text-slate-900">Paket Rolleri</h3>
                    <p class="text-xs text-slate-500">Bu paketi satın alan kullanıcıya hangi rollerin atanacağını seçin. <b>İşletme Sahibi</b> rolü varsayılan olarak önerilir.</p>
                </div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2" id="pkg-roles-list">
                <?php foreach ($allRoles as $role):
                    $rid = $role['role_id'] ?? '';
                    $code = $role['role_code'] ?? '';
                    $name = $role['role_name'] ?? $code;
                    $assigned = false;
                    $isOwner = false;
                    foreach ($packageRoles as $pr) {
                        if (($pr['role_id'] ?? '') === $rid) {
                            $assigned = true;
                            $isOwner = !empty($pr['is_owner_role']);
                            break;
                        }
                    }
                ?>
                <label class="flex items-start gap-2 p-3 border border-slate-200 rounded-xl hover:border-indigo-300 cursor-pointer">
                    <input type="checkbox"
                           class="pkg-role-cb mt-1"
                           data-role-id="<?php echo htmlspecialchars($rid); ?>"
                           data-owner="<?php echo $isOwner ? '1' : '0'; ?>"
                           <?php echo $assigned ? 'checked' : ''; ?>>
                    <div class="flex-1">
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-bold text-slate-800"><?php echo htmlspecialchars($name); ?></span>
                            <?php if ($code === 'BUSINESS_OWNER'): ?>
                                <span class="text-[10px] font-black px-2 py-0.5 bg-indigo-100 text-indigo-700 rounded-full">OWNER</span>
                            <?php endif; ?>
                        </div>
                        <div class="text-[11px] text-slate-500"><?php echo htmlspecialchars($code); ?></div>
                    </div>
                </label>
                <?php endforeach; ?>
            </div>
            <div class="flex items-center justify-between mt-4">
                <span id="pkg-roles-status" class="text-xs text-slate-500"></span>
                <button type="button" id="pkg-roles-save"
                    class="px-5 py-2.5 bg-gradient-to-r from-indigo-600 to-indigo-700 hover:from-indigo-700 hover:to-indigo-800 text-white rounded-xl font-black text-sm shadow-md hover:shadow-lg transition-all">
                    Rolleri Kaydet
                </button>
            
  </div>
</div>
<script>
            (function(){
                const btn = document.getElementById('pkg-roles-save');
                const status = document.getElementById('pkg-roles-status');
                if (!btn) return;
                btn.addEventListener('click', async () => {
                    const checks = document.querySelectorAll('.pkg-role-cb:checked');
                    const roles = Array.from(checks).map(c => ({
                        role_id: c.dataset.roleId,
                        is_owner_role: c.dataset.owner === '1' ? 1 : 0,
                    }));
                    // İlk rolü (ya da BUSINESS_OWNER olanı) owner olarak işaretle
                    const ownerIdx = roles.findIndex(r => r.is_owner_role === 1);
                    if (ownerIdx < 0 && roles.length > 0) roles[0].is_owner_role = 1;

                    status.textContent = 'Kaydediliyor...';
                    btn.disabled = true;
                    try {
                        const resp = await fetch('<?php echo $adminPrefix; ?>' === '/qodmin'
                            ? '/api/qodmin/packages/<?php echo htmlspecialchars($package['package_id']); ?>/roles'
                            : '/api/business/packages/<?php echo htmlspecialchars($package['package_id']); ?>/roles', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
                            body: JSON.stringify({roles})
                        });
                        const data = await resp.json();
                        if (data.success) {
                            status.textContent = 'Roller güncellendi ✓';
                            status.className = 'text-xs text-green-600 font-bold';
                        } else {
                            status.textContent = data.message || 'Hata oluştu';
                            status.className = 'text-xs text-red-600 font-bold';
                        }
                    } catch (e) {
                        status.textContent = 'Bağlantı hatası';
                        status.className = 'text-xs text-red-600 font-bold';
                    } finally {
                        btn.disabled = false;
                    }
                });
            })();
            </script>
        </div>
        <?php endif; ?>
    </div>
  </div>
</div>

<script>
// ─── Wizard logic ──────────────────────────────────────────────────────────
// Paket artık rol bazlı (navigation permissions kaldırıldı), sihirbaz 2 adıma
// indirildi: Genel Bilgiler + Fiyat & Dönemler.
let _currentStep = 1;
const TOTAL_STEPS = 2;

function wizardGo(step) {
    // Validate current before moving forward
    if (step > _currentStep && !wizardValidate(_currentStep)) return;
    // hide all
    for (let i = 1; i <= TOTAL_STEPS; i++) {
        document.getElementById('pkg-step-panel-' + i).classList.remove('active');
        const dot = document.getElementById('step-dot-' + i);
        if (dot) {
            dot.className = 'step-dot w-9 h-9 rounded-full flex items-center justify-center text-sm font-black ' +
                (i < step ? 'done' : i === step ? 'current' : 'todo');
            document.getElementById('step-dot-num-' + i).innerHTML =
                i < step ? '&#10003;' : i;
        }
    }
    document.getElementById('pkg-step-panel-' + step).classList.add('active');
    _currentStep = step;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function wizardNext(fromStep) {
    if (!wizardValidate(fromStep)) return;
    wizardGo(fromStep + 1);
}

function wizardValidate(step) {
    if (step === 1) {
        const name = document.getElementById('package-name').value.trim();
        const err = document.getElementById('pkg-name-err');
        if (!name) { err && err.classList.remove('hidden'); document.getElementById('package-name').focus(); return false; }
        err && err.classList.add('hidden');
    }
    return true;
}

// Init wizard on load
document.addEventListener('DOMContentLoaded', function() {
    wizardGo(1);
    initBillingBuilder();
});

// ─── Billing Options Builder ──────────────────────────────────────────────
let _billingRows = [];
<?php
$boRaw = $isEditMode ? ($package['billing_options'] ?? '') : '';
$boDecoded = [];
if ($boRaw && is_string($boRaw)) {
    $dec = json_decode($boRaw, true);
    if (is_array($dec)) $boDecoded = $dec;
}
echo 'const _billingInitial = ' . json_encode($boDecoded, JSON_UNESCAPED_UNICODE) . ';';
?>

function initBillingBuilder() {
    _billingRows = _billingInitial.map((r, i) => ({ id: i, months: r.months, price: r.price, discount: r.discount_percent ?? null }));
    renderBillingRows();
}

function addBillingRow() {
    const id = Date.now();
    _billingRows.push({ id, months: '', price: '', discount: '' });
    renderBillingRows();
    // focus first empty months input
    setTimeout(() => {
        const inp = document.querySelector(`.billing-row[data-id="${id}"] .bil-months`);
        if (inp) inp.focus();
    }, 50);
}

function removeBillingRow(id) {
    _billingRows = _billingRows.filter(r => r.id !== id);
    renderBillingRows();
}

function billingRowChange(id, field, val) {
    const row = _billingRows.find(r => r.id === id);
    if (row) row[field] = val;
    syncBillingJSON();
}

function syncBillingJSON() {
    const arr = _billingRows
        .filter(r => r.months !== '' && r.months !== null && r.price !== '' && r.price !== null)
        .map(r => ({
            months: parseInt(r.months) || 0,
            price: parseFloat(r.price) || 0,
            discount_percent: (r.discount !== '' && r.discount !== null) ? (parseFloat(r.discount) || 0) : null
        }))
        .filter(r => r.months > 0 && r.price > 0);
    document.getElementById('billing_options_json').value = arr.length ? JSON.stringify(arr) : '';
}

function renderBillingRows() {
    const container = document.getElementById('billing-rows-container');
    const empty = document.getElementById('billing-rows-empty');
    const guide = document.getElementById('billing-rows-guide');
    if (!container) return;

    if (_billingRows.length === 0) {
        container.innerHTML = '';
        empty && empty.classList.remove('hidden');
        guide && guide.classList.remove('hidden');
        syncBillingJSON();
        return;
    }
    empty && empty.classList.add('hidden');

    let html = `<div class="grid grid-cols-12 gap-2 px-1 mb-1">
        <div class="col-span-3 text-[10px] font-black text-slate-400 uppercase tracking-wide">Ay sayısı</div>
        <div class="col-span-4 text-[10px] font-black text-slate-400 uppercase tracking-wide">Fiyat (₺)</div>
        <div class="col-span-4 text-[10px] font-black text-slate-400 uppercase tracking-wide">İndirim (%)</div>
        <div class="col-span-1"></div>
    </div>`;

    _billingRows.forEach(row => {
        const monthlyPrice = parseFloat(document.getElementById('package-price-monthly')?.value || 0);
        let preview = '';
        if (row.months && row.price && monthlyPrice > 0) {
            const full = monthlyPrice * parseInt(row.months);
            const saving = full - parseFloat(row.price);
            if (saving > 0) {
                const pct = ((saving / full) * 100).toFixed(0);
                preview = `<span class="text-[10px] text-emerald-600 font-bold ml-2">₺${saving.toLocaleString('tr-TR', {maximumFractionDigits:2})} tasarruf (%${pct})</span>`;
            }
        }
        html += `<div class="billing-row grid grid-cols-12 gap-2 items-center bg-white border border-slate-200 rounded-xl p-3 shadow-sm" data-id="${row.id}">
            <div class="col-span-3">
                <div class="relative">
                    <input type="number" min="1" max="120" placeholder="3"
                           value="${row.months}"
                           class="bil-months w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm font-bold outline-none focus:border-indigo-400 transition-all"
                           onchange="billingRowChange(${row.id},'months',this.value); renderBillingRows();"
                           oninput="billingRowChange(${row.id},'months',this.value);">
                    <span class="absolute right-2 top-1/2 -translate-y-1/2 text-[10px] text-slate-400 pointer-events-none">ay</span>
                </div>
            </div>
            <div class="col-span-4">
                <div class="relative">
                    <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-sm text-slate-400 pointer-events-none">₺</span>
                    <input type="number" min="0" step="0.01" placeholder="0,00"
                           value="${row.price}"
                           class="bil-price w-full bg-slate-50 border border-slate-200 rounded-lg pl-7 pr-2 py-2 text-sm font-bold outline-none focus:border-indigo-400 transition-all"
                           onchange="billingRowChange(${row.id},'price',this.value); renderBillingRows();"
                           oninput="billingRowChange(${row.id},'price',this.value);">
                </div>
            </div>
            <div class="col-span-4">
                <div class="relative">
                    <input type="number" min="0" max="100" step="0.1" placeholder="Opsiyonel"
                           value="${row.discount !== null && row.discount !== '' ? row.discount : ''}"
                           class="bil-discount w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm font-bold outline-none focus:border-indigo-400 transition-all"
                           onchange="billingRowChange(${row.id},'discount',this.value);"
                           oninput="billingRowChange(${row.id},'discount',this.value);">
                    <span class="absolute right-2 top-1/2 -translate-y-1/2 text-[10px] text-slate-400 pointer-events-none">%</span>
                </div>
                ${preview}
            </div>
            <div class="col-span-1 flex justify-center">
                <button type="button" onclick="removeBillingRow(${row.id})"
                        class="w-7 h-7 rounded-full bg-red-50 hover:bg-red-100 text-red-500 hover:text-red-700 flex items-center justify-center transition-all font-black text-xs">✕</button>
            </div>
        </div>`;
    });
    container.innerHTML = html;
    syncBillingJSON();
}

// Sync before submit
document.getElementById('package-form').addEventListener('submit', function(e) {
    syncBillingJSON();
});

// ─── Form submit handler (AJAX) ───────────────────────────────────────────
document.getElementById('package-form').addEventListener('submit', async function(e) {
    e.preventDefault();
    syncBillingJSON();
    
    const form = this;
    const submitBtn = document.getElementById('pkg-submit-btn');
    const originalBtnText = submitBtn.innerHTML;
    
    // Disable submit button
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span>⏳</span><span>Kaydediliyor...</span>';
    submitBtn.classList.add('opacity-75', 'cursor-not-allowed');
    
    try {
        // Get CSRF token
        let csrfToken = window.CSRF_TOKEN || '';
        if (!csrfToken) {
            const metaTag = document.querySelector('meta[name="csrf-token"]');
            if (metaTag) csrfToken = metaTag.getAttribute('content') || '';
        }
        if (!csrfToken) {
            const csrfInput = document.querySelector('input[name="csrf_token"]');
            if (csrfInput) csrfToken = csrfInput.value || '';
        }
        
        // Prepare form data
        const formData = new FormData(form);
        const data = {};
        formData.forEach((value, key) => {
            if (key === 'permissions[]') {
                if (!data.permissions) data.permissions = [];
                if (value) data.permissions.push(value);
            } else if (key === 'csrf_token' || key === '_method') {
                data[key] = value;
            } else if (key === 'billing_options_json') {
                // Map hidden billing_options_json → billing_options
                data.billing_options = value === '' ? null : value;
            } else {
                if (value === '' && (key === 'description' || key === 'duration_days' || key === 'discount_percentage' ||
                    key === 'price_one_time' || key === 'price_monthly' || key === 'price_yearly')) {
                    data[key] = null;
                } else {
                    data[key] = value;
                }
            }
        });
        
        // Ensure boolean fields are properly set
        data.auto_renew = form.querySelector('#package-auto-renew')?.checked ? true : false;
        data.is_active = form.querySelector('#package-is-active')?.checked !== false; // Default to true
        
        // Ensure numeric fields are numbers or null
        if (data.price_one_time !== null && data.price_one_time !== '') {
            data.price_one_time = parseFloat(data.price_one_time) || 0;
        } else {
            data.price_one_time = null;
        }
        if (data.price_monthly !== null && data.price_monthly !== '') {
            data.price_monthly = parseFloat(data.price_monthly) || 0;
        } else {
            data.price_monthly = null;
        }
        if (data.price_yearly !== null && data.price_yearly !== '') {
            data.price_yearly = parseFloat(data.price_yearly) || 0;
        } else {
            data.price_yearly = null;
        }
        if (data.duration_days !== null && data.duration_days !== '') {
            data.duration_days = parseInt(data.duration_days) || null;
        } else {
            data.duration_days = null;
        }
        if (data.discount_percentage !== null && data.discount_percentage !== '') {
            data.discount_percentage = parseFloat(data.discount_percentage) || 0;
        } else {
            data.discount_percentage = null;
        }
        
        // Determine URL and method
        const isEditMode = <?php echo $isEditMode ? 'true' : 'false'; ?>;
        const adminPrefix = <?php echo json_encode($adminPrefix); ?>;
        const url = isEditMode 
            ? '<?php echo $baseUrl; ?>' + adminPrefix + '/packages/' + (data.package_id || '')
            : '<?php echo $baseUrl; ?>' + adminPrefix + '/packages';
        const method = isEditMode ? 'PUT' : 'POST';
        
        console.log('Submitting package:', { url, method, data });
        
        // Send request
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify({
                ...data,
                _method: isEditMode ? 'PUT' : 'POST'
            })
        });
        
        // Read response as text first (can only read once)
        const responseText = await response.text();
        console.log('Response status:', response.status);
        console.log('Response text (first 500 chars):', responseText.substring(0, 500));
        
        // Check if response is OK
        if (!response.ok) {
            console.error('Response error:', response.status, responseText);
            let errorMessage = 'Paket kaydedilirken bir hata oluştu.';
            try {
                const errorData = JSON.parse(responseText);
                if (errorData.message) {
                    errorMessage = errorData.message;
                }
            } catch (e) {
                // If not JSON, check if it's HTML error page
                if (responseText.includes('<html') || responseText.includes('<!DOCTYPE')) {
                    errorMessage = 'Sunucu hatası: HTML yanıt alındı (muhtemelen bir hata sayfası). Lütfen console\'u kontrol edin.';
                } else {
                    errorMessage = 'Sunucu hatası: ' + responseText.substring(0, 200);
                }
                console.error('Failed to parse error response:', e);
            }
            throw new Error(errorMessage);
        }
        
        // Parse JSON response
        let result;
        try {
            if (!responseText || responseText.trim() === '') {
                throw new Error('Boş yanıt alındı');
            }
            
            // Check if response is actually JSON
            if (responseText === 'true' || responseText === 'false') {
                throw new Error('Geçersiz yanıt formatı: ' + responseText);
            }
            
            result = JSON.parse(responseText);
        } catch (parseError) {
            console.error('JSON parse error:', parseError);
            console.error('Full response text:', responseText);
            throw new Error('Yanıt parse edilemedi: ' + parseError.message + '. Sunucu muhtemelen HTML döndürdü.');
        }
        
        if (!result || typeof result !== 'object') {
            throw new Error('Geçersiz yanıt formatı');
        }
        
        if (result.success) {
            // Success - show message and redirect
            if (window.NotificationManager) {
                window.NotificationManager.success(result.message || (isEditMode ? 'Paket güncellendi' : 'Paket başarıyla oluşturuldu'));
            }
            
            setTimeout(() => {
                window.location.href = '<?php echo $baseUrl . $adminPrefix; ?>/packages';
            }, 500);
        } else {
            // Error - show message
            const errorMsg = result.message || 'Paket kaydedilirken bir hata oluştu';
            window.NotificationManager.error(errorMsg);
            
            // Re-enable button
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;
            submitBtn.classList.remove('opacity-75', 'cursor-not-allowed');
        }
    } catch (error) {
        console.error('Form submit error:', error);
        const errorMsg = 'Paket kaydedilirken bir hata oluştu: ' + (error.message || 'Bilinmeyen hata');
        window.NotificationManager.error(errorMsg);
        
        // Re-enable button
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalBtnText;
        submitBtn.classList.remove('opacity-75', 'cursor-not-allowed');
    }
});
function togglePackageNavGroup(groupId) {
    const dropdown = document.getElementById(groupId);
    const chevron = document.getElementById('chevron-' + groupId);
    
    if (!dropdown) return;
    
    if (dropdown.classList.contains('hidden')) {
        dropdown.classList.remove('hidden');
        if (chevron) chevron.classList.add('rotate-180');
    } else {
        dropdown.classList.add('hidden');
        if (chevron) chevron.classList.remove('rotate-180');
    }
}

function togglePackageNavItem(prefix, navId, checked, groupId) {
    const permissionInputs = document.querySelectorAll(`.permission-input-${prefix}[data-nav-id="${navId}"]`);
    permissionInputs.forEach(input => {
        input.disabled = !checked;
    });
    
    const checkbox = document.querySelector(`input[data-nav-id="${navId}"][data-prefix="${prefix}"]`);
    if (checkbox && checkbox.dataset.parentId) {
        const parentId = checkbox.dataset.parentId;
        const parentCheckbox = document.querySelector(`input[data-nav-id="${parentId}"][data-group="${groupId}"]`);
        if (parentCheckbox) {
            const allChildren = document.querySelectorAll(`input[data-parent-id="${parentId}"][data-group="${groupId}"]`);
            const allChildrenChecked = Array.from(allChildren).every(cb => cb.checked);
            const parentPermissions = document.querySelectorAll(`.permission-input-group-${groupId}[data-nav-id="${parentId}"]`);
            const allParentChecked = Array.from(parentPermissions).every(input => !input.disabled);
            parentCheckbox.checked = allChildrenChecked && allParentChecked;
        }
    } else {
        const childCheckboxes = document.querySelectorAll(`input[data-parent-id="${navId}"][data-group="${groupId}"]`);
        childCheckboxes.forEach(childCb => {
            childCb.checked = checked;
            const childPrefix = childCb.dataset.prefix;
            const childNavId = childCb.dataset.navId;
            const childPermissions = document.querySelectorAll(`.permission-input-${childPrefix}[data-nav-id="${childNavId}"]`);
            childPermissions.forEach(input => {
                input.disabled = !checked;
            });
        });
    }
}

function selectAllInGroup(groupId) {
    const checkboxes = document.querySelectorAll(`.package-nav-checkbox[data-group="${groupId}"]`);
    checkboxes.forEach(checkbox => {
        if (!checkbox.checked) {
            checkbox.checked = true;
            const prefix = checkbox.dataset.prefix;
            const navId = checkbox.dataset.navId;
            togglePackageNavItem(prefix, navId, true, groupId);
        }
    });
}

function selectAllNavigationItems() {
    document.querySelectorAll('[id^="package-nav-"]').forEach(group => {
        selectAllInGroup(group.id);
    });
}

async function generateDescriptionWithGemini() {
    const packageName = document.getElementById('package-name').value.trim();
    const descriptionTextarea = document.getElementById('package-description');
    const generateBtn = document.getElementById('generate-description-btn');
    
    if (!packageName) {
        window.NotificationManager.warning('Lütfen önce paket adını girin.');
        document.getElementById('package-name').focus();
        return;
    }
    
    const originalBtnText = generateBtn.innerHTML;
    generateBtn.disabled = true;
    generateBtn.innerHTML = '<span>⏳</span><span>Oluşturuluyor...</span>';
    generateBtn.classList.add('opacity-75', 'cursor-not-allowed');
    
    try {
        // Get CSRF token from multiple sources
        let csrfToken = window.CSRF_TOKEN || '';
        if (!csrfToken) {
            const metaTag = document.querySelector('meta[name="csrf-token"]');
            if (metaTag) {
                csrfToken = metaTag.getAttribute('content') || '';
            }
        }
        if (!csrfToken) {
            const csrfInput = document.querySelector('input[name="csrf_token"]');
            if (csrfInput) {
                csrfToken = csrfInput.value || '';
            }
        }
        
        if (!csrfToken) {
            window.NotificationManager.error('CSRF token bulunamadı. Sayfayı yenileyip tekrar deneyin.');
            return;
        }
        
        const packageData = {
            package_name: packageName,
            price_one_time: document.getElementById('package-price-one-time').value || null,
            price_monthly: document.getElementById('package-price-monthly').value || null,
            price_yearly: document.getElementById('package-price-yearly').value || null
        };
        
        const packageId = document.getElementById('package-id').value;
        if (packageId) {
            packageData.package_id = packageId;
        }
        
        console.log('Sending request with CSRF token:', csrfToken ? 'Token found' : 'Token missing');
        console.log('Package data:', packageData);
        
        const adminPrefix = <?php echo json_encode($adminPrefix); ?>;
        const response = await fetch('<?php echo $baseUrl; ?>' + adminPrefix + '/packages/generate-description', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify(packageData)
        });
        
        console.log('Response status:', response.status, response.statusText);
        
        // Check if response is OK
        if (!response.ok) {
            const errorText = await response.text();
            console.error('Response error:', response.status, errorText);
            let errorMessage = 'Açıklama oluşturulurken bir hata oluştu.';
            try {
                const errorData = JSON.parse(errorText);
                if (errorData.message) {
                    errorMessage = errorData.message;
                }
            } catch (e) {
                // If not JSON, use default message
                console.error('Failed to parse error response as JSON:', e);
            }
            window.NotificationManager.error(errorMessage);
            return;
        }
        
        const responseText = await response.text();
        console.log('Response text:', responseText);
        
        let data;
        try {
            data = JSON.parse(responseText);
        } catch (e) {
            console.error('Failed to parse response as JSON:', e, responseText);
            window.NotificationManager.error('Sunucudan geçersiz yanıt alındı. Lütfen tekrar deneyin.');
            return;
        }
        
        if (data.success && data.description) {
            descriptionTextarea.value = data.description;
            generateBtn.classList.remove('bg-purple-500', 'hover:bg-purple-600');
            generateBtn.classList.add('bg-green-500', 'hover:bg-green-600');
            generateBtn.innerHTML = '<span>✓</span><span>Oluşturuldu!</span>';
            
            setTimeout(() => {
                generateBtn.classList.remove('bg-green-500', 'hover:bg-green-600');
                generateBtn.classList.add('bg-purple-500', 'hover:bg-purple-600');
                generateBtn.innerHTML = originalBtnText;
            }, 2000);
        } else {
            window.NotificationManager.error(data.message || 'Açıklama oluşturulurken bir hata oluştu.');
        }
    } catch (error) {
        console.error('Error generating description:', error);
        window.NotificationManager.error('Açıklama oluşturulurken bir hata oluştu: ' + (error.message || 'Bilinmeyen hata'));
    } finally {
        generateBtn.disabled = false;
        generateBtn.classList.remove('opacity-75', 'cursor-not-allowed');
        if (generateBtn.innerHTML.includes('Oluşturuluyor')) {
            generateBtn.innerHTML = originalBtnText;
        }
    }
}
</script>
