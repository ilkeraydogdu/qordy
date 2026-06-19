<?php
/**
 * Order Approval History View (İşlem Geçmişi)
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
            <p class="q-page-header__eyebrow">Geçmiş</p>
            <h1 class="q-page-header__title">İşlem Geçmişi</h1>
            <p class="q-page-header__subtitle">Geçmişi görüntülemek istediğiniz işletmeyi seçin</p>
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
                window.location.href = '<?php echo $baseUrl; ?>/qodmin/order-approval-history?business_id=' + encodeURIComponent(businessId);
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
            <a href="<?php echo $baseUrl; ?>/qodmin/order-approval-history" onclick="sessionStorage.removeItem('selected_business_id')"
               class="q-icon-btn shrink-0" aria-label="İşletme seçimine dön">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
            </a>
            <?php endif; ?>
            <div class="min-w-0">
                <p class="q-page-header__eyebrow">Geçmiş</p>
                <h1 class="q-page-header__title">İşlem Geçmişi</h1>
                <p class="q-page-header__subtitle">Onay geçmişi, alınan ödemeler ve diğer işlemler</p>
            </div>
        </div>
    </header>

    <nav class="q-tab-row q-tab-row--card overflow-x-auto" role="tablist" aria-label="Geçmiş sekmeleri">
        <button type="button" id="tab-approval" data-tab="approval" role="tab" aria-selected="true" class="tab-btn q-tab selected whitespace-nowrap">Onay geçmişi</button>
        <button type="button" id="tab-payments" data-tab="payments" role="tab" aria-selected="false" class="tab-btn q-tab whitespace-nowrap">Alınan ödemeler</button>
        <button type="button" id="tab-other" data-tab="other" role="tab" aria-selected="false" class="tab-btn q-tab whitespace-nowrap">Diğer işlemler</button>
    </nav>

    <div class="q-card q-card--pad q-stack min-w-0">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 min-w-0">
            <div class="q-stack q-stack--sm min-w-0">
                <label class="q-label" for="filter-order-number">Sipariş No</label>
                <input type="text" id="filter-order-number" placeholder="cd1, cd2..." class="q-input"/>
            </div>
            <div class="q-stack q-stack--sm min-w-0">
                <label class="q-label" for="filter-waiter">Garson</label>
                <input type="text" id="filter-waiter" placeholder="Garson adı" class="q-input"/>
            </div>
            <div class="q-stack q-stack--sm min-w-0">
                <label class="q-label" for="filter-date-from">Tarih Başlangıç</label>
                <input type="date" id="filter-date-from" class="q-input"/>
            </div>
            <div class="q-stack q-stack--sm min-w-0">
                <label class="q-label" for="filter-date-to">Tarih Bitiş</label>
                <input type="date" id="filter-date-to" class="q-input"/>
            </div>
            <div class="q-stack q-stack--sm min-w-0">
                <label class="q-label" for="filter-item">Ürün</label>
                <input type="text" id="filter-item" placeholder="Ürün adı" class="q-input"/>
            </div>
            <div class="q-stack q-stack--sm min-w-0">
                <label class="q-label" for="filter-table">Masa</label>
                <input type="text" id="filter-table" placeholder="Masa adı" class="q-input"/>
            </div>
            <div class="q-stack q-stack--sm min-w-0">
                <label class="q-label" for="filter-status">Durum</label>
                <select id="filter-status" class="q-input">
                    <option value="">Tümü</option>
                    <option value="PENDING">Beklemede</option>
                    <option value="APPROVED">Onaylandı</option>
                    <option value="REJECTED">Reddedildi</option>
                </select>
            </div>
            <div class="q-stack q-stack--sm min-w-0">
                <label class="q-label" for="filter-action-type">İşlem</label>
                <select id="filter-action-type" class="q-input"><!-- doldurulur: actionTypeOptions --></select>
            </div>
            <div class="flex items-end gap-2 sm:col-span-2 lg:col-span-4 flex-wrap">
                <button type="button" onclick="loadCurrentTab()" class="q-btn q-btn--primary q-btn--sm flex-1 sm:flex-none">Filtrele</button>
                <button type="button" onclick="clearFilters()" class="q-btn q-btn--ghost q-btn--sm flex-1 sm:flex-none">Temizle</button>
            </div>
        </div>
    </div>

    <div class="q-card q-card--pad min-w-0 overflow-x-auto">
        <div id="tab-panel-approval" class="tab-panel">
            <div id="history-list">
                <div class="text-center py-14 text-slate-400">
                    <div class="inline-block w-6 h-6 border-2 border-slate-300 border-t-slate-500 rounded-full animate-spin"></div>
                    <p class="mt-3 text-sm">Yükleniyor...</p>
                </div>
            </div>
        </div>
        <div id="tab-panel-payments" class="tab-panel hidden">
            <div id="payments-list">
                <div class="text-center py-14 text-slate-400 text-sm">Alınan ödemeleri görmek için &quot;Alınan ödemeler&quot; sekmesini seçip Filtrele&#39;ye tıklayın.</div>
            </div>
        </div>
        <div id="tab-panel-other" class="tab-panel hidden">
            <div id="other-list">
                <div class="text-center py-14 text-slate-400 text-sm">Diğer işlemleri (giderler vb.) görmek için &quot;Diğer işlemler&quot; sekmesini seçip Filtrele&#39;ye tıklayın.</div>
            </div>
        </div>
    </div>
  </div>
</div>

<!-- Detay modal (göz ikonu tıklanınca) -->
<div id="approval-detail-modal" class="q-modal-backdrop hidden" role="dialog" aria-modal="true">
    <div class="flex min-h-full items-center justify-center p-4">
        <div class="fixed inset-0 bg-black/50" onclick="closeApprovalDetailModal()"></div>
        <div class="relative bg-white rounded-2xl shadow-xl max-w-lg w-full max-h-[90vh] overflow-hidden flex flex-col">
            <div class="p-5 border-b border-slate-200 flex items-center justify-between">
                <h2 class="text-lg font-bold text-slate-800">İşlem Detayı</h2>
                <button type="button" onclick="closeApprovalDetailModal()" class="p-2 rounded-lg hover:bg-slate-100 text-slate-500">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div id="approval-detail-body" class="p-5 overflow-y-auto flex-1 text-sm space-y-4">
                <div class="text-center py-8 text-slate-400">Yükleniyor...</div>
            </div>
        </div>
    </div>
</div>

<!-- Fiş detay modalı (Alınan ödemeler - Fiş butonu) -->
<div id="payment-receipt-modal" class="q-modal-backdrop hidden" role="dialog" aria-modal="true">
    <div class="flex min-h-full items-center justify-center p-4">
        <div class="fixed inset-0 bg-slate-900/60" onclick="closePaymentReceiptModal()"></div>
        <div class="relative w-full max-w-2xl max-h-[90vh] flex flex-col bg-white rounded-2xl shadow-xl overflow-hidden border border-slate-200">
            <div class="flex items-center justify-end gap-2 px-4 py-3 border-b border-slate-100 bg-slate-50/80 shrink-0">
                <a id="payment-receipt-pdf" href="#" target="_blank" class="inline-flex items-center gap-1.5 px-3 py-2 bg-slate-600 text-white rounded-lg font-bold text-xs hover:bg-slate-500">PDF</a>
                <button type="button" id="payment-receipt-close" class="p-2 rounded-lg text-slate-500 hover:bg-slate-200 hover:text-slate-800">×</button>
            </div>
            <div id="payment-receipt-body" class="flex-1 overflow-y-auto p-4 text-sm text-slate-800">
                <p class="text-slate-500">Yükleniyor...</p>
            </div>
        </div>
    </div>
</div>

<script>
const baseUrl = '<?php echo $baseUrl; ?>';
const apiPrefix = '<?php echo $apiPrefix; ?>';

// Tek kaynak: işlem tipi filtre + tablo etiketleri (yeni tip eklenince buraya eklenir)
const actionTypeOptions = { '': 'Tümü', DELETE: 'Silme', REDUCE_QUANTITY: 'Azaltma', DELETE_ORDER: 'Tüm sipariş silme', PAYMENT_PREP_CANCEL: 'Ödeme iptal' };

function preparationStatusLabel(st) {
    var s = (st || '').toString().toUpperCase();
    if (s === 'SERVED' || s === 'READY') return 'Teslim edildi';
    if (s === 'PREPARING') return 'Hazırlanıyor';
    if (s === 'PENDING') return 'Beklemede';
    if (s === 'CANCELLED') return 'İptal';
    return s || '—';
}

function getFilters() {
    var today = (function() {
        var d = new Date();
        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    })();
    var dateFrom = document.getElementById('filter-date-from')?.value?.trim() || '';
    var dateTo = document.getElementById('filter-date-to')?.value?.trim() || '';
    if (!dateFrom && !dateTo) {
        dateFrom = today;
        dateTo = today;
    }
    return {
        order_number: document.getElementById('filter-order-number')?.value?.trim() || '',
        requested_by_name: document.getElementById('filter-waiter')?.value?.trim() || '',
        date_from: dateFrom,
        date_to: dateTo,
        item_name: document.getElementById('filter-item')?.value?.trim() || '',
        table_name: document.getElementById('filter-table')?.value?.trim() || '',
        status: document.getElementById('filter-status')?.value || '',
        action_type: document.getElementById('filter-action-type')?.value || ''
    };
}

let currentTab = 'approval';

function switchTab(tab) {
    currentTab = tab;
    document.querySelectorAll('.tab-btn').forEach(function(btn) {
        var isActive = btn.getAttribute('data-tab') === tab;
        btn.setAttribute('aria-selected', isActive ? 'true' : 'false');
        if (isActive) {
            btn.classList.add('bg-white', 'text-slate-800', 'border-slate-200', '-mb-px');
            btn.classList.remove('bg-transparent', 'text-slate-500', 'border-transparent');
        } else {
            btn.classList.add('bg-transparent', 'text-slate-500', 'border-transparent');
            btn.classList.remove('bg-white', 'text-slate-800', 'border-slate-200', '-mb-px');
        }
    });
    document.querySelectorAll('.tab-panel').forEach(function(panel) {
        panel.classList.toggle('hidden', panel.id !== 'tab-panel-' + tab);
    });
    loadCurrentTab();
}

function loadCurrentTab() {
    if (currentTab === 'approval') loadHistory();
    else if (currentTab === 'payments') loadPayments();
    else if (currentTab === 'other') loadOtherTransactions();
}

function clearFilters() {
    document.getElementById('filter-order-number').value = '';
    document.getElementById('filter-waiter').value = '';
    var d = new Date();
    var today = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    document.getElementById('filter-date-from').value = today;
    document.getElementById('filter-date-to').value = today;
    document.getElementById('filter-item').value = '';
    document.getElementById('filter-table').value = '';
    document.getElementById('filter-status').value = '';
    document.getElementById('filter-action-type').value = '';
    loadCurrentTab();
}

async function loadHistory() {
    const container = document.getElementById('history-list');
    if (!container) return;
    
    container.innerHTML = '<div class="text-center py-14 text-slate-400"><div class="inline-block w-6 h-6 border-2 border-slate-300 border-t-slate-500 rounded-full animate-spin"></div><p class="mt-3 text-sm">Yükleniyor...</p></div>';
    
    const filters = getFilters();
    const params = new URLSearchParams();
    Object.keys(filters).forEach(k => { if (filters[k]) params.append(k, filters[k]); });
    
    try {
        const response = await fetch(`${baseUrl}${apiPrefix}/order-approvals/history?${params.toString()}`);
        if (!response.ok) {
            container.innerHTML = '<div class="text-center py-12 text-slate-500 text-sm">Yüklenemedi (HTTP ' + response.status + ')</div>';
            return;
        }
        
        const data = await response.json();
        
        if (data.success === false || !data.history) {
            container.innerHTML = '<div class="text-center py-12 text-slate-500 text-sm">Veri yüklenemedi</div>';
            return;
        }
        
        const history = data.history || [];
        
        if (history.length === 0) {
            container.innerHTML = '<div class="text-center py-14 text-slate-400"><p class="text-sm">Kayıt bulunamadı</p></div>';
            return;
        }
        
        const statusLabels = { PENDING: 'Beklemede', APPROVED: 'Onaylandı', REJECTED: 'Reddedildi' };
        const statusColors = { PENDING: 'bg-amber-100 text-amber-800', APPROVED: 'bg-emerald-100 text-emerald-800', REJECTED: 'bg-red-100 text-red-800' };
        const actionLabels = Object.fromEntries(Object.entries(actionTypeOptions).filter(function(e) { return e[0] !== ''; }));
        
        container.innerHTML = `
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-left text-xs font-black text-slate-500 uppercase tracking-widest">
                        <th class="py-3 px-2 whitespace-nowrap">Sipariş No</th>
                        <th class="py-3 px-2">Tarih/Saat</th>
                        <th class="py-3 px-2">Talep eden</th>
                        <th class="py-3 px-2">Masa</th>
                        <th class="py-3 px-2">Ürün</th>
                        <th class="py-3 px-2">İşlem</th>
                        <th class="py-3 px-2">Miktar</th>
                        <th class="py-3 px-2">Durum</th>
                        <th class="py-3 px-2 w-14">Detay</th>
                    </tr>
                </thead>
                <tbody>
                    ${history.map(h => {
                        const reqAt = h.requested_at ? new Date(h.requested_at).toLocaleString('tr-TR') : '-';
                        const orderNum = (h.order_number || h.order_id || '-').toString().trim();
                        let waiter = h.requested_by_name || '-';
                        const rRole = (h.requested_by_role || '').toString().toUpperCase();
                        if (rRole && rRole !== 'BUSINESS_MANAGER' && rRole !== 'ROLE_BUSINESS_MANAGER' && !/business|isletme|sahibi/i.test(rRole)) {
                            const roleShort = { WAITER: 'Garson', CASHIER: 'Kasiyer', KASIYER: 'Kasiyer', MANAGER: 'Yönetici', KITCHEN: 'Mutfak', PERSONEL: 'Personel' }[rRole] || h.requested_by_role;
                            if (roleShort) waiter = waiter + ' - ' + roleShort;
                        }
                        const table = h.table_name || '-';
                        const item = h.item_name || '-';
                        const action = actionLabels[h.action_type] || h.action_type || '-';
                        const qty = h.action_type === 'REDUCE_QUANTITY' 
                            ? `${h.old_quantity} → ${h.new_quantity}` 
                            : (h.action_type === 'DELETE_ORDER' ? h.old_quantity + ' ürün silindi' : (h.action_type === 'DELETE' ? h.old_quantity + ' adet silindi' : '-'));
                        const status = h.status || 'PENDING';
                        const statusLabel = statusLabels[status] || status;
                        const statusClass = statusColors[status] || 'bg-slate-100 text-slate-600';
                        const aid = (h.approval_id || '').replace(/'/g, "\\'");
                        return `<tr class="border-b border-slate-100 hover:bg-slate-50/50">
                            <td class="py-3 px-2 font-mono font-bold text-slate-800 whitespace-nowrap">${orderNum}</td>
                            <td class="py-3 px-2 text-slate-600">${reqAt}</td>
                            <td class="py-3 px-2">${waiter}</td>
                            <td class="py-3 px-2">${table}</td>
                            <td class="py-3 px-2">${item}</td>
                            <td class="py-3 px-2">${action}</td>
                            <td class="py-3 px-2">${qty}</td>
                            <td class="py-3 px-2"><span class="px-2 py-1 rounded-lg text-xs font-bold ${statusClass}">${statusLabel}</span></td>
                            <td class="py-3 px-2">
                                <button type="button" onclick="showApprovalDetail('${aid}')" class="p-2 rounded-lg hover:bg-slate-200 text-slate-500 hover:text-slate-700 transition-colors" title="Detayları Gör">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                </button>
                            </td>
                        </tr>`;
                    }).join('')}
                </tbody>
            </table>
        `;
    } catch (error) {
        console.error('Error loading history:', error);
        container.innerHTML = '<div class="text-center py-12 text-red-400 font-bold">Hata oluştu</div>';
    }
}

async function loadPayments() {
    const container = document.getElementById('payments-list');
    if (!container) return;
    const filters = getFilters();
    container.innerHTML = '<div class="text-center py-14 text-slate-400"><div class="inline-block w-6 h-6 border-2 border-slate-300 border-t-slate-500 rounded-full animate-spin"></div><p class="mt-3 text-sm">Yükleniyor...</p></div>';
    try {
        const params = new URLSearchParams({ date_from: filters.date_from || '', date_to: filters.date_to || '' });
        const response = await fetch(baseUrl + apiPrefix + '/order-approvals/payments?' + params.toString());
        if (!response.ok) {
            container.innerHTML = '<div class="text-center py-12 text-slate-500 text-sm">Yüklenemedi (HTTP ' + response.status + ')</div>';
            return;
        }
        const data = await response.json();
        if (!data.success || !Array.isArray(data.payments)) {
            container.innerHTML = '<div class="text-center py-12 text-slate-500 text-sm">Veri yüklenemedi</div>';
            return;
        }
        const payments = data.payments;
        if (payments.length === 0) {
            container.innerHTML = '<div class="text-center py-14 text-slate-400"><p class="text-sm">Bu tarih aralığında ödeme kaydı yok</p></div>';
            return;
        }
        const methodLabels = { CASH: 'Nakit', CARD: 'Kart', CREDIT_CARD: 'Kredi kartı', debit: 'Banka kartı', credit: 'Kredi kartı', other: 'Diğer' };
        container.innerHTML = '<table class="w-full text-sm"><thead><tr class="border-b border-slate-200 text-left text-xs font-black text-slate-500 uppercase tracking-widest"><th class="py-3 px-2">Tarih / Saat</th><th class="py-3 px-2">Masa</th><th class="py-3 px-2">Tutar</th><th class="py-3 px-2">Ödeme yöntemi</th><th class="py-3 px-2">İşlemi yapan</th><th class="py-3 px-2 min-w-[180px]">Detay</th></tr></thead><tbody>' +
            payments.map(function(p) {
                var dt = (p.timestamp || p.created_at || '-');
                if (dt !== '-') try { dt = new Date(dt).toLocaleString('tr-TR'); } catch(e) {}
                var amount = (p.amount != null ? parseFloat(p.amount) : 0).toFixed(2);
                var method = (p.method || '').toString();
                method = methodLabels[method] || method || '—';
                var tableName = p.table_name || '—';
                var processedBy = p.processed_by_display_name || p.processed_by_name || '—';
                var receiptId = (p.receipt_id || '').toString().trim();
                var receiptNumber = (p.receipt_number || '').toString().trim() || receiptId;
                var orderIdDisplay = (p.display_order_id || p.order_id || '').toString().trim();
                var detailCell = '';
                if (receiptId) {
                    detailCell += '<div class="flex flex-col gap-1">';
                    if (orderIdDisplay) detailCell += '<span class="text-xs text-slate-600">Sipariş #' + orderIdDisplay + '</span>';
                    detailCell += '<span class="text-xs text-slate-700 font-semibold">Fiş No: ' + (receiptNumber || receiptId) + '</span>';
                    detailCell += '<button type="button" onclick="showPaymentReceipt(\'' + receiptId.replace(/'/g, "\\'") + '\')" class="mt-1 w-fit px-2 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-xs font-bold transition-colors" title="Fiş detayı">Fişi Görüntüle</button>';
                    detailCell += '</div>';
                } else {
                    detailCell = '<span class="text-slate-500 text-xs">Fiş yok</span>';
                }
                return '<tr class="border-b border-slate-100 hover:bg-slate-50/50"><td class="py-3 px-2 text-slate-600">' + dt + '</td><td class="py-3 px-2">' + tableName + '</td><td class="py-3 px-2 font-bold">' + amount + ' ₺</td><td class="py-3 px-2">' + method + '</td><td class="py-3 px-2">' + processedBy + '</td><td class="py-3 px-2">' + detailCell + '</td></tr>';
            }).join('') +
            '</tbody></table>';
    } catch (err) {
        console.error('loadPayments:', err);
        container.innerHTML = '<div class="text-center py-12 text-red-400 font-bold">Hata oluştu</div>';
    }
}

async function loadOtherTransactions() {
    const container = document.getElementById('other-list');
    if (!container) return;
    const filters = getFilters();
    container.innerHTML = '<div class="text-center py-14 text-slate-400"><div class="inline-block w-6 h-6 border-2 border-slate-300 border-t-slate-500 rounded-full animate-spin"></div><p class="mt-3 text-sm">Yükleniyor...</p></div>';
    try {
        const params = new URLSearchParams({ date_from: filters.date_from || '', date_to: filters.date_to || '' });
        const response = await fetch(baseUrl + apiPrefix + '/order-approvals/other-transactions?' + params.toString());
        if (!response.ok) {
            container.innerHTML = '<div class="text-center py-12 text-slate-500 text-sm">Yüklenemedi (HTTP ' + response.status + ')</div>';
            return;
        }
        const data = await response.json();
        if (!data.success || !Array.isArray(data.transactions)) {
            container.innerHTML = '<div class="text-center py-12 text-slate-500 text-sm">Veri yüklenemedi</div>';
            return;
        }
        const list = data.transactions;
        if (list.length === 0) {
            container.innerHTML = '<div class="text-center py-14 text-slate-400"><p class="text-sm">Bu tarih aralığında diğer işlem (gider) kaydı yok</p></div>';
            return;
        }
        container.innerHTML = '<table class="w-full text-sm"><thead><tr class="border-b border-slate-200 text-left text-xs font-black text-slate-500 uppercase tracking-widest"><th class="py-3 px-2">Tarih</th><th class="py-3 px-2">Kategori</th><th class="py-3 px-2">Açıklama</th><th class="py-3 px-2">Tutar</th></tr></thead><tbody>' +
            list.map(function(t) {
                var d = t.date || t.created_at || '—';
                if (d !== '—') try { d = new Date(d).toLocaleDateString('tr-TR'); } catch(e) {}
                var cat = t.category || '—';
                var title = t.title || t.description || '—';
                var amount = (t.amount != null ? parseFloat(t.amount) : 0).toFixed(2);
                return '<tr class="border-b border-slate-100 hover:bg-slate-50/50"><td class="py-3 px-2 text-slate-600">' + d + '</td><td class="py-3 px-2">' + cat + '</td><td class="py-3 px-2">' + title + '</td><td class="py-3 px-2 font-bold text-slate-800">' + amount + ' ₺</td></tr>';
            }).join('') +
            '</tbody></table>';
    } catch (err) {
        console.error('loadOtherTransactions:', err);
        container.innerHTML = '<div class="text-center py-12 text-red-400 font-bold">Hata oluştu</div>';
    }
}

function closeApprovalDetailModal() {
    const modal = document.getElementById('approval-detail-modal');
    if (modal) modal.classList.add('hidden');
}

function showPaymentReceipt(receiptId) {
    if (!receiptId || String(receiptId).trim() === '') return;
    receiptId = String(receiptId).trim();
    var modal = document.getElementById('payment-receipt-modal');
    var body = document.getElementById('payment-receipt-body');
    var pdfLink = document.getElementById('payment-receipt-pdf');
    if (!modal || !body) return;
    modal.classList.remove('hidden');
    body.innerHTML = '<p class="text-slate-500">Yükleniyor...</p>';
    var apiUrl = baseUrl + apiPrefix + '/order-approvals/receipt-detail?id=' + encodeURIComponent(receiptId);
    fetch(apiUrl).then(function(r) {
        if (!r.ok) throw { status: r.status };
        return r.json();
    }).then(function(data) {
        if (data.error || !data.receipt_content) {
            body.innerHTML = '<p class="text-red-600 font-semibold">' + (data.message || 'Fiş bulunamadı.') + '</p>';
            return;
        }
        var html = '';
        if (data.order_detail_section) {
            html += '<div class="mb-4 text-xs text-slate-500 border-b border-slate-100 pb-3">' + data.order_detail_section + '</div>';
        }
        var other = data.other_receipts_for_order || [];
        if (other.length > 0) {
            html += '<div class="mb-4 text-xs text-slate-500"><p class="font-semibold text-slate-600 mb-1">Bu siparişe ait diğer fişler</p><ul class="list-disc list-inside">';
            for (var i = 0; i < other.length; i++) {
                var o = other[i];
                html += '<li>' + (o.receipt_number || o.receipt_id) + ' — ' + (o.receipt_type_label || o.receipt_type) + (o.created_at ? ' (' + o.created_at + ')' : '') + '</li>';
            }
            html += '</ul></div>';
        }
        html += '<div class="receipt-content bg-slate-50 border border-slate-200 rounded-xl p-4 overflow-x-auto text-[13px] leading-relaxed">' + (data.receipt_content || '') + '</div>';
        body.innerHTML = html;
        if (pdfLink) pdfLink.href = baseUrl + '/receipt/' + encodeURIComponent(receiptId) + '/pdf';
    }).catch(function() {
        body.innerHTML = '<p class="text-red-600 font-semibold">Yüklenirken hata oluştu.</p>';
    });
}
function closePaymentReceiptModal() {
    var modal = document.getElementById('payment-receipt-modal');
    if (modal) modal.classList.add('hidden');
}
(function() {
    var btn = document.getElementById('payment-receipt-close');
    if (btn) btn.addEventListener('click', closePaymentReceiptModal);
    document.addEventListener('keydown', function(e) { if (e.key === 'Escape') closePaymentReceiptModal(); });
})();

async function showApprovalDetail(approvalId) {
    function escapeHtml(str) {
        if (str == null) return '';
        var s = String(str);
        return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
    const modal = document.getElementById('approval-detail-modal');
    const body = document.getElementById('approval-detail-body');
    if (!modal || !body) return;
    modal.classList.remove('hidden');
    body.innerHTML = '<div class="text-center py-8 text-slate-400">Yükleniyor...</div>';
    
    try {
        const url = baseUrl + apiPrefix + '/order-approvals/detail?approval_id=' + encodeURIComponent(approvalId);
        const response = await fetch(url);
        const data = await response.json();
        
        if (!data.success || !data.detail) {
            body.innerHTML = '<div class="text-center py-8 text-red-500">Detay yüklenemedi.</div>';
            return;
        }
        
        const a = data.detail.approval || {};
        const items = data.detail.order_items || [];
        const deletedOrderItems = data.detail.deleted_order_items || [];
        const cancelledPrepItems = data.detail.cancelled_prep_items || [];
        const affectedItemSnapshot = data.detail.affected_item || null;
        const roleLabels = { WAITER: 'Garson', CASHIER: 'Kasiyer', KASIYER: 'Kasiyer', MANAGER: 'Yönetici', ADMIN: 'Yönetici', KITCHEN: 'Mutfak', PERSONEL: 'Personel', BUSINESS_MANAGER: 'İşletme sahibi', ROLE_BUSINESS_MANAGER: 'İşletme sahibi' };
        const actionLabels = Object.fromEntries(Object.entries(actionTypeOptions).filter(function(e) { return e[0] !== ''; }));
        const statusLabels = { PENDING: 'Beklemede', APPROVED: 'Onaylandı', REJECTED: 'Reddedildi' };
        
        const orderItemId = a.order_item_id || '';
        const affectedItem = orderItemId ? items.find(function(it) { return it.order_item_id === orderItemId; }) : null;
        const productName = (a.item_name && a.item_name !== 'Ürün') ? a.item_name : (affectedItem ? (affectedItem.name || affectedItem.item_name || affectedItem.menu_item_name || affectedItem.product_name) : a.item_name) || 'Ürün';
        
        const oldQty = a.old_quantity != null ? parseInt(a.old_quantity, 10) : 0;
        const newQty = a.action_type === 'DELETE' ? 0 : (a.new_quantity != null ? parseInt(a.new_quantity, 10) : null);
        const unitPrice = parseFloat(a.item_price) || 0;
        const previousTotal = oldQty * unitPrice;
        const nextTotal = (newQty !== null && newQty !== undefined ? newQty : 0) * unitPrice;
        const reducedOrRemoved = oldQty - (newQty !== null ? newQty : 0);
        
        const requestedAt = a.requested_at ? new Date(a.requested_at).toLocaleString('tr-TR') : '-';
        const processedAt = a.processed_at ? new Date(a.processed_at).toLocaleString('tr-TR') : '-';
        const rawRole = (a.requested_by_role || '').toString().toUpperCase();
        let role = roleLabels[rawRole] || a.requested_by_role || '-';
        if ((role === '-' || role === a.requested_by_role) && (a.requested_by_role || '').toString().match(/business|isletme|işletme|yönetici|sahibi/i)) role = 'İşletme sahibi';
        const action = actionLabels[a.action_type] || a.action_type || '-';
        const status = statusLabels[a.status] || a.status || '-';
        
        let html = '<div class="space-y-4">';
        
        // Muhasebe özeti (tüm işlem tipleri)
        (function() {
            var once = parseFloat(a.item_price) || 0;
            var removed = (a.action_type === 'DELETE' || a.action_type === 'DELETE_ORDER' || a.action_type === 'PAYMENT_PREP_CANCEL') ? (previousTotal || once) : (a.action_type === 'REDUCE_QUANTITY' ? (reducedOrRemoved * unitPrice) : 0);
            var after = (a.action_type === 'DELETE' ? 0 : (a.action_type === 'REDUCE_QUANTITY' && newQty !== null ? nextTotal : (a.action_type === 'DELETE_ORDER' || a.action_type === 'PAYMENT_PREP_CANCEL' ? 0 : null)));
            html += '<div class="bg-slate-800 text-white rounded-xl p-4 border border-slate-700">';
            html += '<p class="text-xs font-black text-slate-300 uppercase tracking-widest mb-3">Muhasebe özeti</p>';
            html += '<div class="grid grid-cols-2 gap-2 text-sm">';
            if (a.action_type === 'DELETE' || a.action_type === 'REDUCE_QUANTITY') {
                html += '<div><span class="text-slate-400">İşlem öncesi (bu kalem):</span> <span class="font-bold">' + previousTotal.toFixed(2) + ' ₺</span></div>';
                html += '<div><span class="text-slate-400">İptal/azaltılan tutar:</span> <span class="font-bold text-amber-300">' + (a.action_type === 'DELETE' ? previousTotal.toFixed(2) : (reducedOrRemoved * unitPrice).toFixed(2)) + ' ₺</span></div>';
                html += '<div><span class="text-slate-400">İşlem sonrası (bu kalem):</span> <span class="font-bold">' + (a.action_type === 'DELETE' ? '0.00' : nextTotal.toFixed(2)) + ' ₺</span></div>';
            } else if (a.action_type === 'DELETE_ORDER' || a.action_type === 'PAYMENT_PREP_CANCEL') {
                html += '<div class="col-span-2"><span class="text-slate-400">İptal edilen toplam tutar:</span> <span class="font-bold text-amber-300">' + (a.item_price != null ? parseFloat(a.item_price).toFixed(2) : '0.00') + ' ₺</span></div>';
                html += '<div class="col-span-2"><span class="text-slate-400">İşlem sonrası:</span> <span class="font-bold">0.00 ₺</span> (ilgili kalemler kaldırıldı)</div>';
            }
            html += '</div></div>';
        })();
        
        // Blok: İşlem yapılan ürün (net özet)
        if (a.action_type === 'REDUCE_QUANTITY' || a.action_type === 'DELETE') {
            html += '<div class="bg-slate-50 rounded-xl p-4 border border-slate-200">';
            html += '<p class="text-xs font-black text-slate-500 uppercase tracking-widest mb-3">İşlem yapılan ürün</p>';
            html += '<p class="font-bold text-slate-800 text-base mb-2">' + escapeHtml(affectedItemSnapshot ? (affectedItemSnapshot.name || productName) : productName) + '</p>';
            html += '<ul class="text-sm text-slate-700 space-y-1">';
            html += '<li><strong>Siparişte önce:</strong> ' + oldQty + ' adet</li>';
            if (a.action_type === 'REDUCE_QUANTITY') {
                html += '<li><strong>Azaltılan:</strong> ' + reducedOrRemoved + ' adet</li>';
                html += '<li><strong>Kalan:</strong> ' + (newQty !== null ? newQty : '-') + ' adet</li>';
            } else {
                html += '<li><strong>Silinen:</strong> ' + oldQty + ' adet</li>';
                html += '<li><strong>Kalan:</strong> 0 adet</li>';
            }
            html += '<li><strong>Birim fiyat:</strong> ' + unitPrice.toFixed(2) + ' ₺</li>';
            html += '<li><strong>Önceki tutar:</strong> ' + previousTotal.toFixed(2) + ' ₺</li>';
            html += '<li><strong>Sonraki tutar:</strong> ' + nextTotal.toFixed(2) + ' ₺</li>';
            if (affectedItemSnapshot && affectedItemSnapshot.preparation_status) {
                html += '<li><strong>Teslim durumu (işlem anında):</strong> ' + escapeHtml(preparationStatusLabel(affectedItemSnapshot.preparation_status)) + '</li>';
                var ps = (affectedItemSnapshot.preparation_status || '').toString().toUpperCase();
                if (ps === 'SERVED' || ps === 'READY') {
                    html += '<li class="text-amber-700 font-semibold">' + (a.action_type === 'DELETE' ? 'İptal edilen ürün müşteriye teslim edilmişti.' : 'Bu kalemden azaltılan kısım müşteriye teslim edilmişti.') + '</li>';
                }
            }
            html += '</ul></div>';
        } else if (a.action_type === 'DELETE_ORDER') {
            html += '<div class="bg-slate-50 rounded-xl p-4 border border-slate-200">';
            html += '<p class="text-xs font-black text-slate-500 uppercase tracking-widest mb-2">İşlem</p>';
            html += '<p class="font-bold text-slate-800">Tüm sipariş silindi</p>';
            html += '<p class="text-sm text-slate-600 mt-1">Siparişteki ' + oldQty + ' ürün kalemi kaldırıldı.</p></div>';
            html += '<div class="pt-3"><p class="font-bold text-slate-700 mb-2">Sipariş içeriği (silinen ürünler)</p>';
            if (deletedOrderItems.length > 0) {
                var anyServed = deletedOrderItems.some(function(it) { var s = (it.preparation_status || '').toString().toUpperCase(); return s === 'SERVED' || s === 'READY'; });
                if (anyServed) {
                    html += '<p class="text-amber-700 font-semibold text-sm mt-2">İptal edilen ürünlerden bazıları müşteriye teslim edilmişti.</p>';
                }
                html += '<ul class="list-none space-y-3 text-slate-700 mt-2">';
                deletedOrderItems.forEach(function(it) {
                    const name = it.name || it.item_name || '-';
                    const qty = it.quantity != null ? it.quantity : 0;
                    const price = parseFloat(it.price) || 0;
                    const total = (it.total != null ? parseFloat(it.total) : (qty * price)).toFixed(2);
                    const note = (it.note || it.notes || '').toString().trim();
                    const excluded = Array.isArray(it.excluded_ingredients) ? it.excluded_ingredients : (it.excluded_ingredients ? [].concat(it.excluded_ingredients) : []);
                    const extras = Array.isArray(it.selected_extras) ? it.selected_extras : (it.selected_extras ? [].concat(it.selected_extras) : []);
                    const variantName = (it.variant_name || '').toString().trim();
                    const prepStatus = preparationStatusLabel(it.preparation_status);
                    html += '<li class="bg-white rounded-lg p-3 border border-slate-200">';
                    html += '<div class="font-bold text-slate-800">' + escapeHtml(name) + '</div>';
                    if (variantName) html += '<div class="text-xs text-slate-500 mt-0.5">Varyant: ' + escapeHtml(variantName) + '</div>';
                    html += '<div class="text-sm mt-1">× ' + qty + ' — ' + price.toFixed(2) + ' ₺ birim — Toplam: ' + total + ' ₺</div>';
                    html += '<div class="text-sm mt-1 text-slate-600"><span class="font-bold">Teslim durumu:</span> ' + escapeHtml(prepStatus) + '</div>';
                    if (note) html += '<div class="text-sm mt-1 text-amber-800"><span class="font-bold">Not:</span> ' + escapeHtml(note) + '</div>';
                    if (excluded.length > 0) {
                        const exclNames = excluded.map(function(x) { return typeof x === 'object' ? (x.ingredient_name || x.name || '') : x; }).filter(Boolean);
                        html += '<div class="text-sm mt-1 text-slate-600"><span class="font-bold">Çıkarılan malzeme:</span> ' + escapeHtml(exclNames.join(', ')) + '</div>';
                    }
                    if (extras.length > 0) {
                        const extraParts = extras.map(function(x) {
                            if (typeof x === 'object') return (x.name || '') + (x.price != null ? ' (+' + parseFloat(x.price).toFixed(2) + ' ₺)' : '');
                            return x;
                        }).filter(Boolean);
                        html += '<div class="text-sm mt-1 text-slate-600"><span class="font-bold">Ekstra:</span> ' + escapeHtml(extraParts.join(', ')) + '</div>';
                    }
                    html += '</li>';
                });
                html += '</ul></div>';
            } else {
                html += '<p class="text-slate-500 text-sm">Bu işlem kaydında silinen ürün listesi saklanmamış (eski kayıt veya detay o dönem kaydedilmiyordu). Yeni yapılan tüm sipariş silme işlemlerinde liste gösterilir.</p>';
                if (items.length > 0) {
                    html += '<p class="text-slate-500 text-sm mt-2">Siparişte şu an görünen ürünler (güncel):</p><ul class="list-none space-y-2 text-slate-600 mt-1">';
                    items.forEach(function(it) {
                        var nm = it.name || it.item_name || it.menu_item_name || it.product_name || '-';
                        var q = it.quantity != null ? it.quantity : 0;
                        var pr = parseFloat(it.price) || 0;
                        var tot = (it.total != null ? parseFloat(it.total) : (q * pr)).toFixed(2);
                        html += '<li class="text-sm">' + escapeHtml(nm) + ' × ' + q + ' — ' + pr.toFixed(2) + ' ₺ — ' + tot + ' ₺</li>';
                    });
                    html += '</ul>';
                }
                html += '</div>';
            }
        } else if (a.action_type === 'PAYMENT_PREP_CANCEL') {
            html += '<div class="bg-slate-50 rounded-xl p-4 border border-slate-200">';
            html += '<p class="text-xs font-black text-slate-500 uppercase tracking-widest mb-2">İşlem</p>';
            html += '<p class="font-bold text-slate-800">Ödeme iptali</p>';
            html += '<p class="text-sm text-slate-600 mt-1">Masadaki hazırlanan ürünler iptal edildi.</p>';
            if (cancelledPrepItems.length > 0) {
                html += '<p class="text-slate-600 font-semibold text-sm mt-2">Bu ürünler hazırlık aşamasındaydı, müşteriye teslim edilmedi.</p>';
            }
            if (cancelledPrepItems.length === 0) {
                html += '<p class="text-xs text-slate-500 mt-2">Bu kayıt öncesinde iptal edilen ürün listesi saklanmadığı için detay gösterilemiyor.</p>';
            }
            html += '</div>';
            if (cancelledPrepItems.length > 0) {
                html += '<div class="pt-3"><p class="font-bold text-slate-700 mb-3">İptal edilen hazırlanan ürünler</p><ul class="list-none space-y-3 text-slate-700">';
                cancelledPrepItems.forEach(function(it) {
                    const name = it.name || it.item_name || '-';
                    const qty = it.quantity != null ? it.quantity : 0;
                    const price = parseFloat(it.price) || 0;
                    const total = (it.total != null ? parseFloat(it.total) : (qty * price)).toFixed(2);
                    const note = (it.note || it.notes || '').toString().trim();
                    const excluded = Array.isArray(it.excluded_ingredients) ? it.excluded_ingredients : (it.excluded_ingredients ? [].concat(it.excluded_ingredients) : []);
                    const extras = Array.isArray(it.selected_extras) ? it.selected_extras : (it.selected_extras ? [].concat(it.selected_extras) : []);
                    const variantName = (it.variant_name || '').toString().trim();
                    const prepStatus = preparationStatusLabel(it.preparation_status);
                    html += '<li class="bg-white rounded-lg p-3 border border-slate-200">';
                    html += '<div class="font-bold text-slate-800">' + escapeHtml(name) + '</div>';
                    if (variantName) html += '<div class="text-xs text-slate-500 mt-0.5">Varyant: ' + escapeHtml(variantName) + '</div>';
                    html += '<div class="text-sm mt-1">× ' + qty + ' — ' + price.toFixed(2) + ' ₺ birim — Toplam: ' + total + ' ₺</div>';
                    html += '<div class="text-sm mt-1 text-slate-600"><span class="font-bold">Teslim durumu:</span> ' + escapeHtml(prepStatus) + '</div>';
                    if (note) html += '<div class="text-sm mt-1 text-amber-800"><span class="font-bold">Not:</span> ' + escapeHtml(note) + '</div>';
                    if (excluded.length > 0) {
                        const exclNames = excluded.map(function(x) { return typeof x === 'object' ? (x.ingredient_name || x.name || '') : x; }).filter(Boolean);
                        html += '<div class="text-sm mt-1 text-slate-600"><span class="font-bold">Çıkarılan malzeme:</span> ' + escapeHtml(exclNames.join(', ')) + '</div>';
                    }
                    if (extras.length > 0) {
                        const extraParts = extras.map(function(x) {
                            if (typeof x === 'object') return (x.name || '') + (x.price != null ? ' (+' + parseFloat(x.price).toFixed(2) + ' ₺)' : '');
                            return x;
                        }).filter(Boolean);
                        html += '<div class="text-sm mt-1 text-slate-600"><span class="font-bold">Ekstra:</span> ' + escapeHtml(extraParts.join(', ')) + '</div>';
                    }
                    html += '</li>';
                });
                html += '</ul></div>';
            }
        }
        
        // Genel bilgiler
        const summaryLabel = a.action_type === 'PAYMENT_PREP_CANCEL' ? 'İşlem özeti' : 'Ürün';
        const summaryValue = (a.item_name && a.item_name !== 'Ürün') ? a.item_name : productName;
        const priceLabel = a.action_type === 'PAYMENT_PREP_CANCEL' ? 'İptal edilen toplam tutar' : (a.action_type === 'DELETE_ORDER' ? 'Silinen toplam tutar' : 'Birim fiyat');
        const priceValue = (a.action_type === 'PAYMENT_PREP_CANCEL' || a.action_type === 'DELETE_ORDER')
            ? (a.item_price != null && a.item_price !== '' ? parseFloat(a.item_price).toFixed(2) + ' ₺' : '-')
            : (unitPrice > 0 ? unitPrice.toFixed(2) + ' ₺' : (a.item_price != null ? parseFloat(a.item_price).toFixed(2) + ' ₺' : '-'));
        // Talep eden: işletme sahibi ise sadece işletme adı, garson/kasiyer ise "İsim - Rol"
        let talepEdenText = a.requested_by_name || '-';
        if (role && role !== '-' && role !== 'İşletme sahibi' && !/business|manager|isletme|sahibi|yönetici/i.test(role)) {
            talepEdenText = talepEdenText + ' - ' + role;
        }
        const rows = [
            ['Sipariş No', (a.order_number || a.order_id || '-').toString().trim()],
            ['Masa', a.table_name || '-'],
            ['Talep eden', talepEdenText],
            ['Talep saati', requestedAt],
            ['İşlem tipi', action],
            [summaryLabel, summaryValue],
            ['Eski miktar', oldQty !== null && oldQty !== undefined ? oldQty : (a.old_quantity != null ? a.old_quantity : '-')],
            ['Yeni miktar', newQty !== null && newQty !== undefined ? newQty : (a.new_quantity != null ? a.new_quantity : '-')],
            [priceLabel, priceValue],
            ['Durum', status],
            ['İşlemi yapan', a.approved_by_name || '-'],
            ['İşlem saati', processedAt],
        ];
        if (a.status === 'REJECTED' && (a.rejected_reason || '').trim() !== '') {
            rows.push(['Red nedeni', a.rejected_reason]);
        }
        rows.forEach(function(r) {
            html += '<div class="flex border-b border-slate-100 pb-2"><span class="font-bold text-slate-500 w-40 shrink-0">' + escapeHtml(r[0] || '') + '</span><span class="text-slate-800">' + escapeHtml(r[1] !== undefined && r[1] !== null ? String(r[1]) : '-') + '</span></div>';
        });
        
        // Siparişteki ürünler: işlem yapılan kalemi vurgula, önce/sonra bilgisi ekle
        if (items.length > 0) {
            html += '<div class="pt-3"><p class="font-bold text-slate-600 mb-2">Siparişteki ürünler (güncel)</p><ul class="list-none space-y-2 text-slate-700">';
            items.forEach(function(it) {
                const name = it.name || it.item_name || it.menu_item_name || it.product_name || '-';
                const qty = it.quantity != null ? it.quantity : '';
                const price = it.price != null ? parseFloat(it.price) : 0;
                const total = (qty !== '' ? parseInt(qty, 10) * price : 0).toFixed(2);
                const isAffected = orderItemId && (it.order_item_id === orderItemId);
                html += '<li class="flex flex-wrap items-baseline gap-x-2 ' + (isAffected ? 'bg-amber-50 rounded-lg px-3 py-2 border border-amber-200' : '') + '">';
                html += '<span class="font-medium">' + escapeHtml(name) + '</span>';
                html += '<span>× ' + (qty !== '' ? qty : '-') + '</span>';
                html += '<span>' + price.toFixed(2) + ' ₺ birim</span>';
                html += '<span>— Toplam: ' + total + ' ₺</span>';
                if (isAffected && (a.action_type === 'REDUCE_QUANTITY' || a.action_type === 'DELETE')) {
                    html += '<span class="text-amber-700 text-xs font-bold">(bu kalemden ' + reducedOrRemoved + ' adet ' + (a.action_type === 'DELETE' ? 'silindi' : 'azaltıldı') + '; önce ' + oldQty + ' adet, şimdi ' + (newQty !== null ? newQty : qty) + ' adet)</span>';
                }
                html += '</li>';
            });
            html += '</ul></div>';
        }
        html += '</div>';
        body.innerHTML = html;
    } catch (err) {
        console.error(err);
        body.innerHTML = '<div class="text-center py-8 text-red-500">Hata oluştu.</div>';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    var sel = document.getElementById('filter-action-type');
    if (sel) {
        Object.keys(actionTypeOptions).forEach(function(value) {
            var opt = document.createElement('option');
            opt.value = value;
            opt.textContent = actionTypeOptions[value];
            sel.appendChild(opt);
        });
    }
    var d = new Date();
    var today = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    var dateFromEl = document.getElementById('filter-date-from');
    var dateToEl = document.getElementById('filter-date-to');
    if (dateFromEl && !dateFromEl.value) dateFromEl.value = today;
    if (dateToEl && !dateToEl.value) dateToEl.value = today;
    document.querySelectorAll('.tab-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var t = btn.getAttribute('data-tab');
            if (t) switchTab(t);
        });
    });
    loadCurrentTab();
});
</script>
<?php endif; ?>
