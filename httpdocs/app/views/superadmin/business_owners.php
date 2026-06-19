<?php
/**
 * Super Admin - Business Owners (İşletme Sahipleri)
 */
require_once __DIR__ . '/../../helpers/translations.php';

$businessOwners = $business_owners ?? [];
$totalBusinesses = count($businessOwners);
$activeCount = 0; $subscriptionCount = 0; $totalStaff = 0;
foreach ($businessOwners as $bo) {
    if (!empty($bo['is_active'])) $activeCount++;
    if (!empty($bo['has_subscription'])) $subscriptionCount++;
    $totalStaff += (int)($bo['staff_count'] ?? 0);
}
$inactiveCount = $totalBusinesses - $activeCount;
?>

<div class="q-page animate-slide-up">
  <div class="q-container">

    <!-- Page Header -->
    <div class="relative overflow-hidden bg-gradient-to-br from-slate-900 via-slate-800 to-orange-950 text-white p-5 sm:p-8 rounded-2xl shadow-xl border border-white/10">
        <div class="pointer-events-none absolute -right-16 -top-12 h-52 w-52 rounded-full bg-orange-500/20 blur-3xl"></div>
        <div class="pointer-events-none absolute left-1/2 bottom-0 h-28 w-52 rounded-full bg-amber-400/10 blur-2xl"></div>
        <div class="relative flex flex-col sm:flex-row sm:items-center sm:justify-between gap-5">
            <div>
                <p class="text-[10px] font-black uppercase tracking-[0.22em] text-orange-300/90 mb-1">Qodmin &rsaquo; İşletmeler</p>
                <h1 class="text-2xl sm:text-3xl font-black tracking-tight mb-1">İşletme Sahipleri</h1>
                <p class="text-slate-300 text-sm">Kayıtlı işletmeleri, abonelikleri ve personeli yönetin</p>
            </div>
            <div class="flex flex-wrap items-center gap-2 sm:gap-4">
                <div class="bg-white/10 rounded-2xl px-4 py-3 text-center min-w-[72px]">
                    <div class="text-2xl font-black"><?php echo $totalBusinesses; ?></div>
                    <div class="text-[10px] text-slate-300 uppercase tracking-wide">Toplam</div>
                </div>
                <div class="bg-emerald-500/20 border border-emerald-400/30 rounded-2xl px-4 py-3 text-center min-w-[72px]">
                    <div class="text-2xl font-black text-emerald-300"><?php echo $activeCount; ?></div>
                    <div class="text-[10px] text-slate-300 uppercase tracking-wide">Aktif</div>
                </div>
                <div class="bg-orange-500/20 border border-orange-400/30 rounded-2xl px-4 py-3 text-center min-w-[72px]">
                    <div class="text-2xl font-black text-orange-300"><?php echo $subscriptionCount; ?></div>
                    <div class="text-[10px] text-slate-300 uppercase tracking-wide">Abonelikli</div>
                </div>
                <?php if ($inactiveCount > 0): ?>
                <div class="bg-slate-500/20 border border-slate-400/30 rounded-2xl px-4 py-3 text-center min-w-[72px]">
                    <div class="text-2xl font-black text-slate-300"><?php echo $inactiveCount; ?></div>
                    <div class="text-[10px] text-slate-300 uppercase tracking-wide">Pasif</div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Search & Filter -->
    <div class="bg-white p-3.5 rounded-xl shadow-sm border border-slate-200 flex flex-col sm:flex-row gap-2.5">
        <div class="relative flex-1">
            <svg class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <input type="text" id="owner-search"
                   placeholder="İşletme adı, sahip veya e-posta ile ara…"
                   class="w-full pl-9 pr-3 py-2.5 rounded-lg border border-slate-200 text-sm font-medium focus:border-orange-400 focus:ring-1 focus:ring-orange-200 outline-none transition-all">
        </div>
        <select id="owner-filter"
                class="px-3 py-2.5 rounded-lg border border-slate-200 text-sm font-bold bg-white focus:border-orange-400 outline-none min-w-[160px]">
            <option value="all">Tümü</option>
            <option value="active">Aktif</option>
            <option value="inactive">Pasif</option>
            <option value="subscribed">Abonelikli</option>
            <option value="no-subscription">Abonelksiz</option>
        </select>
    </div>

    <!-- Table / Empty state -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <?php if (empty($businessOwners)): ?>
        <div class="text-center py-20">
            <div class="w-20 h-20 bg-slate-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
                <svg class="w-9 h-9 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                </svg>
            </div>
            <h3 class="text-lg font-black text-slate-800 mb-2">Kayıtlı işletme bulunamadı</h3>
            <p class="text-slate-500 text-sm">Henüz kayıtlı işletme sahibi yok.</p>
        </div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full" id="owners-table">
                <thead>
                    <tr class="bg-slate-50/80 border-b-2 border-slate-200">
                        <th class="text-left py-3 px-4 text-[10px] font-black text-slate-500 uppercase tracking-widest">İşletme</th>
                        <th class="text-left py-3 px-4 text-[10px] font-black text-slate-500 uppercase tracking-widest hidden sm:table-cell">Sahip</th>
                        <th class="text-left py-3 px-4 text-[10px] font-black text-slate-500 uppercase tracking-widest hidden md:table-cell">İletişim</th>
                        <th class="text-center py-3 px-4 text-[10px] font-black text-slate-500 uppercase tracking-widest hidden lg:table-cell">Personel / Masa</th>
                        <th class="text-left py-3 px-4 text-[10px] font-black text-slate-500 uppercase tracking-widest hidden md:table-cell">Abonelik</th>
                        <th class="text-center py-3 px-4 text-[10px] font-black text-slate-500 uppercase tracking-widest">Durum</th>
                        <th class="text-center py-3 px-4 text-[10px] font-black text-slate-500 uppercase tracking-widest">İşlemler</th>
                    </tr>
                </thead>
                <tbody id="owners-table-body" class="divide-y divide-slate-100">
                    <?php foreach ($businessOwners as $owner): ?>
                    <tr class="hover:bg-slate-50/60 transition-colors owner-row"
                        data-user-id="<?php echo htmlspecialchars($owner['user_id'] ?? ''); ?>"
                        data-customer-id="<?php echo htmlspecialchars($owner['customer_id'] ?? ''); ?>"
                        data-name="<?php echo htmlspecialchars(strtolower(($owner['name'] ?? '') . ' ' . ($owner['business_name'] ?? '') . ' ' . ($owner['email'] ?? ''))); ?>"
                        data-active="<?php echo !empty($owner['is_active']) ? '1' : '0'; ?>"
                        data-subscribed="<?php echo !empty($owner['has_subscription']) ? '1' : '0'; ?>">

                        <!-- İşletme -->
                        <td class="py-3.5 px-4">
                            <a href="<?php echo BASE_URL; ?>/qodmin/businesses/<?php echo htmlspecialchars($owner['customer_id'] ?? ''); ?>" class="group flex items-center gap-3">
                                <div class="relative w-11 h-11 rounded-xl overflow-hidden border border-slate-200/80 flex-shrink-0 shadow-sm bg-white">
                                    <?php if (!empty($owner['logo_path'])): ?>
                                    <img src="<?php echo htmlspecialchars(BASE_URL . $owner['logo_path']); ?>" alt="" class="w-full h-full object-contain p-1">
                                    <?php else: ?>
                                    <div class="absolute inset-0 bg-gradient-to-br from-orange-400 to-amber-600 flex items-center justify-center text-white font-black text-sm">
                                        <?php echo strtoupper(mb_substr($owner['business_name'] ?: '?', 0, 2)); ?>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (!empty($owner['is_active'])): ?>
                                    <span class="absolute -bottom-0.5 -right-0.5 w-3 h-3 bg-emerald-400 border-2 border-white rounded-full"></span>
                                    <?php endif; ?>
                                </div>
                                <div class="min-w-0">
                                    <div class="font-black text-sm text-slate-800 group-hover:text-orange-600 transition-colors truncate max-w-[140px]">
                                        <?php echo htmlspecialchars($owner['business_name'] ?? '-'); ?>
                                    </div>
                                    <?php if (!empty($owner['subdomain'])): ?>
                                    <div class="text-[10px] text-slate-400 truncate"><?php echo htmlspecialchars($owner['subdomain']); ?>.qordy.com</div>
                                    <?php endif; ?>
                                    <?php if (!empty($owner['created_at'])): ?>
                                    <div class="text-[10px] text-slate-400"><?php echo date('d.m.Y', strtotime($owner['created_at'])); ?></div>
                                    <?php endif; ?>
                                </div>
                            </a>
                        </td>

                        <!-- Sahip -->
                        <td class="py-3.5 px-4 hidden sm:table-cell">
                            <div class="text-sm font-bold text-slate-700"><?php echo htmlspecialchars($owner['name'] ?? '-'); ?></div>
                            <div class="text-xs text-slate-400 truncate max-w-[160px]"><?php echo htmlspecialchars($owner['email'] ?? ''); ?></div>
                        </td>

                        <!-- İletişim -->
                        <td class="py-3.5 px-4 hidden md:table-cell">
                            <div class="text-sm text-slate-600"><?php echo htmlspecialchars($owner['phone'] ?: '—'); ?></div>
                        </td>

                        <!-- Personel / Masa -->
                        <td class="py-3.5 px-4 text-center hidden lg:table-cell">
                            <div class="flex items-center justify-center gap-3">
                                <span class="flex items-center gap-1 text-xs font-bold text-indigo-600 bg-indigo-50 px-2 py-1 rounded-lg">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                    <?php echo (int)($owner['staff_count'] ?? 0); ?>
                                </span>
                                <span class="flex items-center gap-1 text-xs font-bold text-purple-600 bg-purple-50 px-2 py-1 rounded-lg">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                                    <?php echo (int)($owner['table_count'] ?? 0); ?>
                                </span>
                            </div>
                        </td>

                        <!-- Abonelik -->
                        <td class="py-3.5 px-4 hidden md:table-cell">
                            <?php if (!empty($owner['has_subscription'])): ?>
                            <div class="text-xs font-black text-emerald-700">
                                <?php echo htmlspecialchars($owner['subscription_package'] ?: 'Aktif Abonelik'); ?>
                            </div>
                            <?php if (!empty($owner['subscription_end_date'])): ?>
                            <div class="text-[10px] text-slate-400 mt-0.5">
                                <?php echo date('d.m.Y', strtotime($owner['subscription_end_date'])); ?>'e kadar
                            </div>
                            <?php endif; ?>
                            <?php else: ?>
                            <span class="text-[10px] font-bold text-slate-400">Abonelik yok</span>
                            <?php endif; ?>
                        </td>

                        <!-- Durum -->
                        <td class="py-3.5 px-4 text-center">
                            <?php if (!empty($owner['is_active'])): ?>
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 text-[10px] font-black bg-emerald-100 text-emerald-700 rounded-full">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>Aktif
                            </span>
                            <?php else: ?>
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 text-[10px] font-black bg-slate-100 text-slate-500 rounded-full">
                                <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>Pasif
                            </span>
                            <?php endif; ?>
                        </td>

                        <!-- İşlemler -->
                        <td class="py-3.5 px-4">
                            <div class="flex items-center justify-center gap-1">
                                <a href="<?php echo BASE_URL; ?>/qodmin/businesses/<?php echo htmlspecialchars($owner['customer_id'] ?? ''); ?>"
                                   class="p-2 text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-all" title="Detay">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                </a>
                                <a href="<?php echo BASE_URL; ?>/qodmin/businesses/<?php echo htmlspecialchars($owner['customer_id'] ?? ''); ?>/login-as"
                                   class="p-2 text-slate-400 hover:text-emerald-600 hover:bg-emerald-50 rounded-lg transition-all" title="Müşteri olarak giriş yap">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/></svg>
                                </a>
                                <button onclick="deleteOwner('<?php echo htmlspecialchars($owner['user_id'] ?? ''); ?>', '<?php echo htmlspecialchars(addslashes($owner['business_name'] ?? ''), ENT_QUOTES); ?>')"
                                        class="p-2 text-slate-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition-all" title="Sil">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div id="owners-empty-filter" class="hidden text-center py-10 text-sm text-slate-500">Filtreyle eşleşen kayıt bulunamadı.</div>
        <?php endif; ?>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="delete-owner-modal" class="fixed inset-0 bg-black/60 z-[60] hidden flex items-center justify-center p-4 backdrop-blur-sm" onclick="if(event.target===this)closeDeleteModal()">
    <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full animate-slide-up">
        <div class="p-6">
            <div class="w-12 h-12 bg-red-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
                <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
            </div>
            <h3 class="text-lg font-black text-slate-900 text-center mb-1">İşletmeyi Sil</h3>
            <p class="text-slate-600 text-sm text-center mb-4">
                <strong id="del-biz-name" class="text-slate-900"></strong> işletmesini kalıcı olarak silmek istediğinize emin misiniz?
            </p>
            <div class="bg-red-50 border border-red-200 rounded-xl p-3 mb-4 text-xs text-red-700">
                Bu işlem geri alınamaz. İşletmeye ait tüm veriler kalıcı olarak silinecektir.
            </div>
            <p class="text-xs text-slate-500 mb-2 text-center">Onaylamak için işletme adını yazın:</p>
            <input type="text" id="del-confirm-input" placeholder="İşletme adını buraya yazın"
                   class="w-full px-3 py-2.5 border-2 border-slate-200 rounded-xl mb-4 focus:border-red-400 focus:ring-2 focus:ring-red-100 outline-none transition-all text-sm">
            <div class="flex gap-3">
                <button onclick="closeDeleteModal()" class="flex-1 px-4 py-2.5 bg-slate-100 text-slate-700 rounded-xl font-bold hover:bg-slate-200 transition-all text-sm">İptal</button>
                <button id="del-confirm-btn" disabled onclick="confirmDelete()"
                        class="flex-1 px-4 py-2.5 bg-red-600 text-white rounded-xl font-black disabled:opacity-40 disabled:cursor-not-allowed hover:bg-red-700 transition-all text-sm">Sil</button>
            </div>
        </div>
    </div>

  </div>
</div>
<script>
const _boBase = '<?php echo BASE_URL; ?>';
let _delUid = null, _delName = null;

// ── Search & Filter ──
document.getElementById('owner-search')?.addEventListener('input', applyOwnerFilter);
document.getElementById('owner-filter')?.addEventListener('change', applyOwnerFilter);

function applyOwnerFilter() {
    const q = (document.getElementById('owner-search')?.value || '').toLowerCase().trim();
    const f = document.getElementById('owner-filter')?.value || 'all';
    let vis = 0;
    document.querySelectorAll('.owner-row').forEach(row => {
        const name = row.dataset.name || '';
        const active = row.dataset.active === '1';
        const sub = row.dataset.subscribed === '1';
        let show = (!q || name.includes(q));
        if (f === 'active') show = show && active;
        else if (f === 'inactive') show = show && !active;
        else if (f === 'subscribed') show = show && sub;
        else if (f === 'no-subscription') show = show && !sub;
        row.classList.toggle('hidden', !show);
        if (!row.classList.contains('hidden')) vis++;
    });
    const emp = document.getElementById('owners-empty-filter');
    if (emp) emp.classList.toggle('hidden', vis > 0);
}

// ── Delete ──
function deleteOwner(uid, name) {
    _delUid = uid; _delName = name;
    document.getElementById('del-biz-name').textContent = name;
    document.getElementById('del-confirm-input').value = '';
    document.getElementById('del-confirm-btn').disabled = true;
    document.getElementById('delete-owner-modal').classList.remove('hidden');
}

function closeDeleteModal() {
    document.getElementById('delete-owner-modal').classList.add('hidden');
    _delUid = null; _delName = null;
}

document.getElementById('del-confirm-input')?.addEventListener('input', function() {
    document.getElementById('del-confirm-btn').disabled = this.value.trim() !== _delName;
});

function confirmDelete() {
    if (!_delUid) return;
    const btn = document.getElementById('del-confirm-btn');
    btn.disabled = true; btn.textContent = 'Siliniyor…';
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content
              || document.querySelector('input[name="csrf_token"]')?.value || '';
    fetch(_boBase + '/api/qodmin/business-owners/delete', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf, 'Accept': 'application/json' },
        body: JSON.stringify({ user_id: _delUid })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const row = document.querySelector(`tr[data-user-id="${_delUid}"]`);
            if (row) { row.style.transition = 'opacity .3s'; row.style.opacity = '0'; setTimeout(() => row.remove(), 300); }
            closeDeleteModal();
            window.NotificationManager?.success('İşletme silindi.');
        } else throw new Error(data.message || 'Silme başarısız');
    })
    .catch(err => {
        btn.disabled = false; btn.textContent = 'Sil';
        window.NotificationManager?.error(err.message);
    });
}
</script>
