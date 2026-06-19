<?php
/**
 * Order Approvals View
 * Yönetici onay istekleri sayfası
 */
$isSuperAdmin = $is_super_admin ?? false;
$selectedBusinessId = $selected_business_id ?? null;
$apiPrefix = $api_prefix ?? ($isSuperAdmin ? '/api/qodmin' : '/api/business');
$baseUrl = BASE_URL;
?>

<?php if ($isSuperAdmin && !$selectedBusinessId): ?>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/business-theme.css?v=<?php echo @filemtime(dirname(__DIR__, 3) . '/public/assets/css/business-theme.css'); ?>">
<div class="q-page q-biz-theme animate-slide-up">
  <div class="q-container q-stack q-stack--lg min-w-0">
    <header class="q-page-header q-page-header--split flex-col sm:flex-row gap-4">
        <div class="min-w-0">
            <p class="q-page-header__eyebrow">Onaylar</p>
            <h1 class="q-page-header__title">Onay Bekleyen İşlemler</h1>
            <p class="q-page-header__subtitle">Onayları görüntülemek istediğiniz işletmeyi seçin</p>
        </div>
        <div class="q-input-icon-wrap w-full sm:w-64 shrink-0">
            <svg class="q-input-icon-wrap__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
            </svg>
            <input type="search" id="business-search" placeholder="İşletme ara..."
                   onkeyup="BusinessSelector.searchBusinesses(this.value)"
                   class="q-input" autocomplete="off">
        </div>
    </header>
    <div id="business-grid" class="q-grid q-grid--4 min-w-0">
        <div class="col-span-full q-empty q-empty--inline">
            <div class="q-spinner q-spinner--lg mx-auto" role="status" aria-label="Yükleniyor"></div>
            <p class="q-hint mt-4">İşletmeler yükleniyor…</p>
        </div>
    </div>
  </div>
</div>
<script>
(function() {
    const bsScript = document.createElement('script');
    bsScript.src = '<?php echo $baseUrl; ?>/assets/js/business-selector.js?v=<?php echo time(); ?>';
    bsScript.onload = function() {
        if (!BusinessSelector) return;
        BusinessSelector.init({ baseUrl: '<?php echo $baseUrl; ?>' });
        BusinessSelector.loadBusinesses().then(() => {
            BusinessSelector.renderBusinessGrid('business-grid', function(businessId) {
                window.location.href = '<?php echo $baseUrl; ?>/qodmin/order-approvals?business_id=' + encodeURIComponent(businessId);
            });
        });
    };
    document.head.appendChild(bsScript);
})();
</script>
<?php else: ?>

<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/business-theme.css?v=<?php echo @filemtime(dirname(__DIR__, 3) . '/public/assets/css/business-theme.css'); ?>">
<div class="q-page q-biz-theme animate-slide-up min-w-0">
  <div class="q-container q-stack q-stack--lg min-w-0">
    <header class="q-page-header">
        <div class="q-toolbar min-w-0" style="gap:var(--space-3);">
            <?php if ($isSuperAdmin && $selectedBusinessId): ?>
            <a href="<?php echo $baseUrl; ?>/qodmin/order-approvals" onclick="sessionStorage.removeItem('selected_business_id')"
               class="q-icon-btn shrink-0" aria-label="İşletme seçimine dön">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
            </a>
            <?php endif; ?>
            <div class="min-w-0">
                <p class="q-page-header__eyebrow">Onaylar</p>
                <h1 class="q-page-header__title">Onay Bekleyen İşlemler</h1>
                <p class="q-page-header__subtitle">Garson ve kasiyerin silme veya adet azaltma talepleri</p>
            </div>
        </div>
    </header>

    <div class="q-card q-card--pad q-stack min-w-0">
        <div class="q-panel-toolbar q-toolbar q-toolbar--between flex-wrap gap-3">
            <span class="q-hint font-medium">Bekleyen İstekler</span>
            <button type="button" onclick="loadApprovals()" class="q-btn q-btn--ghost q-btn--sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                Yenile
            </button>
        </div>
        
        <div id="approvals-list" class="q-stack q-stack--md min-w-0">
            <div class="q-empty q-empty--inline">
                <div class="q-spinner mx-auto" role="status" aria-label="Yükleniyor"></div>
                <p class="q-hint mt-3">Yükleniyor…</p>
            </div>
        </div>
    </div>

    <!-- Modal: JS ile body'e taşınır, böylece overflow/backdrop-blur'dan etkilenmez -->
    <div id="approval-detail-modal" class="q-modal-backdrop hidden" role="dialog" aria-modal="true" onclick="if(event.target===this)closeApprovalDetailModal()">
        <div class="bg-white rounded-2xl shadow-xl border border-slate-200/80 max-w-md w-full max-h-[88vh] overflow-hidden flex flex-col" onclick="event.stopPropagation()">
            <div class="px-5 py-4 bg-slate-50/80 border-b border-slate-200 flex justify-between items-center shrink-0">
                <h3 class="text-lg font-bold text-slate-800">Onay Talebi Detayı</h3>
                <button onclick="closeApprovalDetailModal()" class="p-2 -m-2 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-200/60 transition-colors" aria-label="Kapat">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            <div id="approval-detail-content" class="p-5 overflow-y-auto flex-1 min-h-0"></div>
            <div class="px-5 py-4 bg-slate-50/50 border-t border-slate-200 flex gap-3 justify-end shrink-0">
                <button onclick="rejectFromModal()" class="px-4 py-2.5 rounded-xl bg-white border border-slate-200 hover:bg-slate-50 hover:border-slate-300 text-slate-600 font-medium text-sm transition-colors shadow-sm">Reddet</button>
                <button onclick="approveFromModal()" class="px-4 py-2.5 rounded-xl bg-emerald-500 hover:bg-emerald-600 text-white font-medium text-sm transition-colors shadow-md shadow-emerald-500/25">Onayla</button>
            </div>
        </div>
    </div>
    <style>
        @keyframes modalIn {
            from { opacity: 0; transform: scale(0.96); }
            to { opacity: 1; transform: scale(1); }
        }
    </style>
  </div>
</div>

<script>
const baseUrl = '<?php echo $baseUrl; ?>';
const apiPrefix = '<?php echo $apiPrefix; ?>';
const csrfToken = '<?php echo $csrf_token ?? ''; ?>';

// Approval cache for detail modal (keyed by approval_id)
let approvalCache = {};
let currentModalApprovalId = null;

function escapeHtml(s) {
    if (s == null) return '';
    const t = String(s);
    return t.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

// Load approvals (showLoading: true = show spinner, false = silent refresh for interval)
async function loadApprovals(showLoading = true) {
    const container = document.getElementById('approvals-list');
    if (!container) return;
    
    if (showLoading) {
        container.innerHTML = '<div class="text-center py-14 text-slate-400"><div class="inline-block w-6 h-6 border-2 border-slate-300 border-t-slate-500 rounded-full animate-spin"></div><p class="mt-3 text-sm">Yükleniyor...</p></div>';
    }
    
    try {
        const response = await fetch(`${baseUrl}${apiPrefix}/order-approvals/pending`, {
            headers: {
                'X-CSRF-Token': csrfToken
            }
        });
        
        if (!response.ok) {
            container.innerHTML = '<div class="text-center py-12 text-slate-500 text-sm">Yüklenemedi (HTTP ' + response.status + ')</div>';
            return;
        }
        
        const data = await response.json();
        
        if (data.success === false) {
            const errMsg = data.error || data.message || 'Veriler yüklenemedi.';
            container.innerHTML = '<div class="text-center py-12"><p class="text-slate-600 text-sm">' + (String(errMsg).replace(/</g, '&lt;').replace(/>/g, '&gt;')) + '</p><p class="text-slate-400 text-xs mt-2">Yenile butonunu deneyin.</p></div>';
            return;
        }
        
        if (!data.approvals || data.approvals.length === 0) {
            container.innerHTML = '<div class="text-center py-14 text-slate-400"><p class="text-sm">Bekleyen onay isteği yok</p></div>';
            return;
        }
        
        approvalCache = {};
        data.approvals.forEach(a => { approvalCache[a.approval_id || ''] = a; });
        
        container.innerHTML = `
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-slate-500 border-b border-slate-200">
                            <th class="py-2 px-2 font-medium">Sipariş</th>
                            <th class="py-2 px-2 font-medium">Ürün</th>
                            <th class="py-2 px-2 font-medium text-right">Tutar</th>
                            <th class="py-2 px-2 font-medium">Tarih</th>
                            <th class="py-2 px-2 w-28 text-right"></th>
                        </tr>
                    </thead>
                    <tbody>
                        ${data.approvals.map(approval => {
            const approvalId = approval.approval_id || '';
            const actionType = approval.action_type || 'DELETE';
            const itemName = approval.item_name || 'Ürün';
            const oldQuantity = parseInt(approval.old_quantity || 1);
            const newQuantity = approval.new_quantity ? parseInt(approval.new_quantity) : null;
            const itemPrice = parseFloat(approval.item_price || approval.price || approval.unit_price || 0);
            const requestedAt = approval.requested_at ? new Date(approval.requested_at) : null;
            const dateStr = requestedAt ? requestedAt.toLocaleDateString('tr-TR', {day:'2-digit',month:'2-digit'}) + ' ' + requestedAt.toLocaleTimeString('tr-TR', {hour:'2-digit',minute:'2-digit'}) : '';
            const orderNumber = approval.order_id || approval.order_number || '-';
            const isPaymentPrepCancel = (actionType === 'PAYMENT_PREP_CANCEL');
            const isDeleteOrder = (actionType === 'DELETE_ORDER');
            const affectedQty = actionType === 'REDUCE_QUANTITY' ? (oldQuantity - (newQuantity || 0)) : oldQuantity;
            const totalAmount = isPaymentPrepCancel ? 0 : (isDeleteOrder ? itemPrice : (itemPrice * affectedQty));
            const amountStr = isPaymentPrepCancel ? '-' : (actionType === 'REDUCE_QUANTITY' && affectedQty > 0 ? totalAmount.toFixed(2) + ' ₺ (' + affectedQty + ' adet)' : totalAmount.toFixed(2) + ' ₺');
            return `
                            <tr class="border-b border-slate-100 hover:bg-slate-50/50 transition-colors">
                                <td class="py-2.5 px-2 font-mono font-semibold text-slate-800">#${escapeHtml(orderNumber)}</td>
                                <td class="py-2.5 px-2 text-slate-700 truncate max-w-[120px] sm:max-w-[180px]">${isDeleteOrder ? escapeHtml(itemName) : (oldQuantity + 'x ' + escapeHtml(itemName))}</td>
                                <td class="py-2.5 px-2 text-right font-semibold ${isPaymentPrepCancel ? 'text-slate-600' : 'text-indigo-600'}">${amountStr}</td>
                                <td class="py-2.5 px-2 text-slate-500 text-xs">${dateStr}</td>
                                <td class="py-2.5 px-2 text-right">
                                    <div class="flex gap-1 justify-end items-center">
                                        <button onclick="showApprovalDetail('${approvalId}')" class="p-1.5 rounded-md hover:bg-slate-200 text-slate-500 hover:text-slate-700 transition-colors" title="Detayları Gör">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                        </button>
                                        <button onclick="approveRequest('${approvalId}')" class="px-2 py-1 rounded-md bg-emerald-500 hover:bg-emerald-600 text-white text-xs font-medium transition-colors flex items-center gap-1">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg>
                                            Onayla
                                        </button>
                                        <button onclick="rejectRequest('${approvalId}')" class="px-2 py-1 rounded-md bg-slate-100 hover:bg-slate-200 text-slate-600 hover:text-slate-800 text-xs font-medium transition-colors flex items-center gap-1">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path></svg>
                                            Reddet
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        `;
        }).join('')}
                    </tbody>
                </table>
            </div>
        `;
    } catch (error) {
        console.error('Error loading approvals:', error);
        container.innerHTML = '<div class="text-center py-12 text-red-400 font-bold">Hata oluştu</div>';
    }
}

// Merkezi bildirim kullan
function notifySuccess(msg) {
    if (window.NotificationManager && typeof window.NotificationManager.success === 'function') {
        window.NotificationManager.success(msg);
    } else if (window.showToast) {
        window.showToast(msg, 'success');
    }
}
function notifyError(msg) {
    if (window.NotificationManager && typeof window.NotificationManager.error === 'function') {
        window.NotificationManager.error(msg);
    } else if (window.showToast) {
        window.showToast(msg, 'error');
    }
}

// Approve request
async function approveRequest(approvalId) {
    const confirmed = window.NotificationManager && typeof window.NotificationManager.confirm === 'function'
        ? await window.NotificationManager.confirm('Bu isteği onaylamak istediğinizden emin misiniz?', 'Onay')
        : confirm('Bu isteği onaylamak istediğinizden emin misiniz?');
    if (!confirmed) return;
    
    try {
        const response = await fetch(`${baseUrl}${apiPrefix}/order-approvals/approve`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify({
                approval_id: approvalId
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            notifySuccess('İstek onaylandı');
            loadApprovals(false);
        } else {
            notifyError(data.error || 'Onay başarısız');
        }
    } catch (error) {
        console.error('Error approving request:', error);
        notifyError('Hata oluştu');
    }
}

// Detail modal
function showApprovalDetail(approvalId) {
    const approval = approvalCache[approvalId];
    if (!approval) return;
    currentModalApprovalId = approvalId;
    
    const actionType = approval.action_type || 'DELETE';
    const itemName = approval.item_name || 'Ürün';
    const oldQuantity = parseInt(approval.old_quantity || 1);
    const newQuantity = approval.new_quantity != null ? parseInt(approval.new_quantity) : null;
    const itemPrice = parseFloat(approval.item_price || approval.price || approval.unit_price || 0);
    const tableName = approval.table_name || 'Masa';
    const requestedAt = approval.requested_at ? new Date(approval.requested_at) : null;
    const requestedDate = requestedAt ? requestedAt.toLocaleDateString('tr-TR') : '';
    const requestedTime = requestedAt ? requestedAt.toLocaleTimeString('tr-TR', {hour:'2-digit',minute:'2-digit'}) : '';
    const orderNumber = approval.order_id || approval.order_number || '-';
    
    const isPaymentPrepCancel = (actionType === 'PAYMENT_PREP_CANCEL');
    const isDeleteOrder = (actionType === 'DELETE_ORDER');
    const actionLabel = isPaymentPrepCancel ? 'Ödeme iptali' 
        : (isDeleteOrder ? 'Tüm sipariş silme' : (actionType === 'DELETE' ? 'Ürün silme' : 'Adet azaltma'));
    const affectedQty = actionType === 'REDUCE_QUANTITY' ? (oldQuantity - (newQuantity || 0)) : oldQuantity;
    const totalAmount = isPaymentPrepCancel ? 0 : (isDeleteOrder ? itemPrice : (itemPrice * affectedQty));
    
    // Talep eden: rol + isim (backend'den requested_by_role ve requested_by_name gelir)
    const roleCodeToLabel = { WAITER: 'Garson', CASHIER: 'Kasiyer', KASIYER: 'Kasiyer', MANAGER: 'Yönetici', ADMIN: 'Yönetici', BUSINESS_MANAGER: 'İşletme Yöneticisi', KITCHEN: 'Mutfak', PERSONEL: 'Personel' };
    const rawRole = approval.requested_by_role || approval.role_name || '';
    const roleLabel = roleCodeToLabel[rawRole?.toUpperCase?.()] || rawRole || '';
    const rawName = approval.requested_by_name || approval.requested_by || 'Personel';
    let displayName = (typeof rawName === 'string' && rawName.includes('@')) ? rawName.split('@')[0] : rawName;
    if (displayName && displayName.length > 0 && !displayName.includes(' ')) displayName = displayName.charAt(0).toUpperCase() + displayName.slice(1).toLowerCase();
    const requestedByFull = roleLabel ? `${roleLabel} - ${displayName}` : displayName;
    
    let quantityDesc = '';
    if (isDeleteOrder) {
        quantityDesc = `Siparişteki ${oldQuantity} ürünün tamamı silinecek.`;
    } else if (actionType === 'REDUCE_QUANTITY') {
        const reduceBy = oldQuantity - (newQuantity || 0);
        quantityDesc = `Siparişte ${oldQuantity} adet vardı, ${reduceBy} adet azaltılacak (yeni miktar: ${newQuantity}).`;
    } else {
        quantityDesc = `Siparişte ${oldQuantity} adet vardı, tamamı silinecek.`;
    }
    
    const content = document.getElementById('approval-detail-content');
    if (!content) return;
    content.innerHTML = `
        <div class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <div class="flex flex-col gap-0.5">
                    <span class="text-xs font-medium text-slate-400">Sipariş No</span>
                    <span class="font-mono font-bold text-slate-800">#${escapeHtml(orderNumber)}</span>
                </div>
                <div class="flex flex-col gap-0.5">
                    <span class="text-xs font-medium text-slate-400">İstek Tarihi</span>
                    <span class="font-medium text-slate-700">${requestedDate} ${requestedTime}</span>
                </div>
                <div class="flex flex-col gap-0.5 col-span-2">
                    <span class="text-xs font-medium text-slate-400">Talep Eden</span>
                    <span class="font-medium text-slate-700">${escapeHtml(requestedByFull)}</span>
                </div>
                <div class="flex flex-col gap-0.5">
                    <span class="text-xs font-medium text-slate-400">Masa</span>
                    <span class="font-medium text-slate-700">${escapeHtml(tableName)}</span>
                </div>
                <div class="flex flex-col gap-0.5">
                    <span class="text-xs font-medium text-slate-400">İşlem Türü</span>
                    <span class="font-medium text-slate-700">${actionLabel}</span>
                </div>
            </div>
            <div class="rounded-xl bg-amber-50/80 border border-amber-100 p-4">
                <span class="text-xs font-medium text-slate-400 block mb-1">İlgili Ürün</span>
                <p class="font-semibold text-slate-800">${isDeleteOrder ? escapeHtml(itemName) : (oldQuantity + 'x ' + escapeHtml(itemName))}</p>
                <p class="text-slate-600 text-sm mt-1">${quantityDesc}</p>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div class="flex flex-col gap-0.5">
                    <span class="text-xs font-medium text-slate-400">Eski Adet</span>
                    <span class="font-medium text-slate-700">${oldQuantity}</span>
                </div>
                ${actionType === 'REDUCE_QUANTITY' && newQuantity != null ? `<div class="flex flex-col gap-0.5"><span class="text-xs font-medium text-slate-400">Yeni Adet</span><span class="font-medium text-slate-700">${newQuantity}</span></div>` : ''}
                ${!isPaymentPrepCancel ? `
                <div class="flex flex-col gap-0.5 col-span-2 rounded-lg bg-slate-50 p-3">
                    <span class="text-xs font-medium text-slate-400">Azaltılan/Silinen Tutar</span>
                    ${actionType === 'REDUCE_QUANTITY' && affectedQty > 0 ? `<span class="text-slate-600 text-sm">${affectedQty} adet × ${itemPrice.toFixed(2)} ₺ = </span>` : ''}
                    <span class="font-bold text-indigo-600 text-lg">${totalAmount > 0 ? totalAmount.toFixed(2) + ' ₺' : '—'}</span>
                </div>` : ''}
            </div>
            ${isPaymentPrepCancel ? '<p class="text-slate-600 text-sm">Mutfak veya hazırlıktaki ürünler iptal edilecek, masa ödeme alabilecek.</p>' : ''}
        </div>
    `;
    
    const modal = document.getElementById('approval-detail-modal');
    if (modal) {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }
}

function closeApprovalDetailModal() {
    currentModalApprovalId = null;
    const modal = document.getElementById('approval-detail-modal');
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }
}

function approveFromModal() {
    if (currentModalApprovalId) {
        closeApprovalDetailModal();
        approveRequest(currentModalApprovalId);
    }
}

function rejectFromModal() {
    if (currentModalApprovalId) {
        closeApprovalDetailModal();
        rejectRequest(currentModalApprovalId);
    }
}

// Reject request
async function rejectRequest(approvalId) {
    let reason = '';
    if (window.NotificationManager && typeof window.NotificationManager.prompt === 'function') {
        const result = await window.NotificationManager.prompt('Reddetme nedeni (isteğe bağlı, boş bırakabilirsiniz)', 'Reddet', '');
        if (result === null) return; // İptal
        reason = (result && typeof result === 'string') ? result.trim() : '';
    } else if (typeof prompt === 'function') {
        const r = prompt('Reddetme nedeni (isteğe bağlı, boş bırakabilirsiniz):');
        if (r === null) return;
        reason = (r && typeof r === 'string') ? r.trim() : '';
    }
    
    try {
        const response = await fetch(`${baseUrl}${apiPrefix}/order-approvals/reject`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify({
                approval_id: approvalId,
                reason: reason
            })
        });
        
        let data = null;
        const ct = response.headers.get('Content-Type') || '';
        try {
            if (ct.indexOf('application/json') !== -1) {
                data = await response.json();
            }
        } catch (parseErr) {
            console.error('Reject response parse error:', parseErr);
        }
        if (!data) {
            notifyError('Sunucu yanıtı işlenemedi.');
            return;
        }
        
        if (data.success) {
            notifySuccess('İstek reddedildi');
            loadApprovals(false);
        } else {
            notifyError(data.error || data.message || 'Reddetme başarısız');
        }
    } catch (error) {
        console.error('Error rejecting request:', error);
        notifyError('Bağlantı hatası');
    }
}

// Auto-load on page load
document.addEventListener('DOMContentLoaded', function() {
    // Modal'ı body'e taşı - overflow/backdrop-blur/transform'lı parent'lar fixed'i bozuyor
    const modal = document.getElementById('approval-detail-modal');
    if (modal && document.body) {
        document.body.appendChild(modal);
    }
    
    loadApprovals(true);

    // Silent refresh every 5 seconds (no spinner, avoids constant flicker)
    setInterval(function() { loadApprovals(false); }, 5000);
});
</script>
<?php endif; ?>
