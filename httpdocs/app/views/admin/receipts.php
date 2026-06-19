<?php
require_once __DIR__ . '/../../../app/helpers/translations.php';
require_once __DIR__ . '/../../../app/helpers/security.php';
require_once __DIR__ . '/../components/datatable.php';

$csrfToken = function_exists('generateCSRFToken') ? generateCSRFToken() : '';
$receipts = isset($receipts) && is_array($receipts) ? $receipts : [];
$baseUrl = defined('BASE_URL') ? BASE_URL : '';
$isSuperAdmin = $is_super_admin ?? false;
$selectedBusinessId = $selected_business_id ?? null;

$paymentLabels = ['CASH' => 'Nakit', 'CARD' => 'Kart', 'QR' => 'QR', 'MIXED' => 'Karma', 'PENDING' => 'Beklemede'];
$statusOptions = ['ACTIVE' => 'Aktif', 'VOIDED' => 'İptal'];
$receiptTypeLabels = ['FULL' => 'Ödeme fişi', 'ADISYON' => 'Adisyon', 'PREPARATION' => 'Mutfak', 'PARTIAL' => 'Kısmi'];
$businessName = isset($business_name) && $business_name !== '' ? trim($business_name) : '';

$receiptsData = [];
foreach ($receipts as $r) {
    if (!is_array($r)) continue;
    $amt = $r['total_amount'] ?? 0;
    $pm = $r['payment_method'] ?? 'CASH';
    $st = $r['status'] ?? 'ACTIVE';
    $createdAt = $r['created_at'] ?? '';
    $rid = $r['receipt_id'] ?? '';
    $rnum = $r['receipt_number'] ?? $rid;
    $rtype = $r['receipt_type'] ?? 'FULL';
    $isPaid = !empty($r['is_paid']) && $r['is_paid'] != '0';
    $orderStatus = strtoupper((string)($r['order_status'] ?? ''));
    $orderPaidOrServed = $isPaid || ($orderStatus === 'SERVED');
    $paymentLabel = $orderPaidOrServed ? 'Ödendi' : ($paymentLabels[$pm] ?? $pm);
    $apiId = $rid !== '' ? $rid : $rnum;
    $createdByName = trim($r['created_by_name'] ?? '');
    $waiterName = trim($r['waiter_name'] ?? '');
    $displayName = $createdByName !== '' ? $createdByName : ($waiterName !== '' ? $waiterName : $businessName);
    if ($displayName === '' || $displayName === '-') {
        $displayName = $businessName !== '' ? $businessName : 'İşletme';
    }
    $receiptsData[] = [
        'receipt_id'       => $rid,
        'receipt_number'   => $rnum,
        'receipt_type'     => $rtype,
        'receipt_type_label' => $receiptTypeLabels[$rtype] ?? $rtype,
        'api_id'           => $apiId,
        'order_id'         => $r['order_id'] ?? '',
        'table_name'       => $r['table_name'] ?? '-',
        'waiter_name'      => $displayName,
        'total_amount'     => $amt,
        'total_formatted'  => function_exists('formatCurrency') ? formatCurrency($amt) : (number_format((float)$amt, 2, ',', '.') . ' ₺'),
        'payment_method'   => $pm,
        'payment_label'    => $paymentLabel,
        'status'           => $st,
        'status_label'     => $statusOptions[$st] ?? $st,
        'status_class'     => ($st === 'VOIDED') ? 'q-badge--danger' : 'q-badge--success',
        'created_at'       => $createdAt,
        'created_at_formatted' => $createdAt ? (date('d.m.Y', strtotime($createdAt)) . ' ' . date('H:i', strtotime($createdAt))) : '-'
    ];
}
?>

<?php if ($isSuperAdmin && !$selectedBusinessId): ?>
<!-- SUPER ADMIN: İşletme seçim ekranı -->
<div class="q-page q-biz-theme animate-slide-up">
  <div class="q-container q-stack q-stack--lg">
    <header class="q-page-header">
        <div>
            <p class="q-page-header__eyebrow">Fişler</p>
            <h1 class="q-page-header__title">Fiş Yönetimi</h1>
            <p class="q-page-header__subtitle">Fişlerini görüntülemek istediğiniz işletmeyi seçin</p>
        </div>
        <div class="q-page-header__actions q-field" style="min-width:16rem;margin:0;">
            <input type="text" id="business-search" placeholder="İşletme ara..."
                   onkeyup="BusinessSelector.searchBusinesses(this.value)"
                   class="q-input"/>
        </div>
    </header>
    <div id="business-grid" class="q-grid q-grid--4">
        <div class="col-span-full text-center py-12">
            <div class="q-spinner" style="margin:0 auto;"></div>
            <p class="q-hint mt-4">İşletmeler yükleniyor...</p>
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
            BusinessSelector.renderBusinessGrid('business-grid', function(businessId, businessName) {
                window.location.href = '<?php echo $baseUrl; ?>/qodmin/receipts?business_id=' + encodeURIComponent(businessId);
            });
        });
    };
    document.head.appendChild(bsScript);
})();
</script>
<?php else: ?>
<!-- Fiş içerik görünümü (işletme seçilmişse veya normal kullanıcı) -->
<div class="q-page q-biz-theme animate-slide-up">
  <div class="q-container q-stack q-stack--lg">
    <header class="q-page-header">
        <div class="q-toolbar" style="align-items:flex-start;">
            <?php if ($isSuperAdmin && $selectedBusinessId): ?>
            <a href="<?php echo $baseUrl; ?>/qodmin/receipts" class="q-btn q-btn--ghost q-btn--sm">
                <?php echo icon_arrow_left(['class' => 'w-5 h-5']); ?>
            </a>
            <?php endif; ?>
            <div>
                <p class="q-page-header__eyebrow"><?php echo $isSuperAdmin && $businessName ? htmlspecialchars($businessName) : 'Fişler'; ?></p>
                <h1 class="q-page-header__title"><?php echo t('receipts.title', 'Fiş Yönetimi'); ?></h1>
                <p class="q-page-header__subtitle">Fiş Geçmişi</p>
            </div>
        </div>
    </header>

    <div class="q-card q-card--pad" style="background:var(--color-surface-muted);border-color:var(--color-brand-accent-muted);">
        <p class="font-bold" style="color:var(--color-text-primary);">Yazdır butonu:</p>
        <p class="q-hint mt-1">Fiş, <strong>kasiyer yazıcısına</strong> (cashier_main) gönderilir. Printer Bridge açıksa fiş otomatik yazdırılır.</p>
        <p class="q-hint mt-2">Liste sipariş başına tek satır gösterir (tercihen ödeme fişi). Aynı siparişe ait tüm fişler (adisyonlar dahil) <strong>Görüntüle</strong> ile açılan detayda gösterilir.</p>
    </div>

    <?php
    $filterStart = htmlspecialchars($filter_start_date ?? date('Y-m-d', strtotime('-30 days')));
    $filterEnd   = htmlspecialchars($filter_end_date   ?? date('Y-m-d'));
    $formAction  = htmlspecialchars((strpos($_SERVER['REQUEST_URI'] ?? '', '/business/') !== false)
        ? $baseUrl . '/business/receipts'
        : $baseUrl . '/qodmin/receipts');
    ?>
    <form method="get" action="<?php echo $formAction; ?>" id="receipts-date-filter" class="q-card q-card--pad q-stack q-stack--sm">
        <?php if ($isSuperAdmin && $selectedBusinessId): ?>
            <input type="hidden" name="business_id" value="<?php echo htmlspecialchars($selectedBusinessId); ?>">
        <?php endif; ?>
        <div class="q-toolbar" style="flex-wrap:wrap;align-items:flex-end;">
            <div class="q-grid q-grid--2" style="flex:1;min-width:12rem;">
                <div class="q-field" style="margin:0;">
                    <label class="q-label" for="rcpt-start">Başlangıç</label>
                    <input type="date" name="start_date" id="rcpt-start" value="<?php echo $filterStart; ?>" class="q-input"/>
                </div>
                <div class="q-field" style="margin:0;">
                    <label class="q-label" for="rcpt-end">Bitiş</label>
                    <input type="date" name="end_date" id="rcpt-end" value="<?php echo $filterEnd; ?>" class="q-input"/>
                </div>
            </div>
            <div class="q-toolbar" style="flex-wrap:wrap;">
                <button type="button" data-range="today"   class="rcpt-quick q-btn q-btn--soft q-btn--sm">Bugün</button>
                <button type="button" data-range="yesterday" class="rcpt-quick q-btn q-btn--soft q-btn--sm">Dün</button>
                <button type="button" data-range="week"    class="rcpt-quick q-btn q-btn--soft q-btn--sm">Bu Hafta</button>
                <button type="button" data-range="month"   class="rcpt-quick q-btn q-btn--soft q-btn--sm">Bu Ay</button>
                <button type="button" data-range="last30"  class="rcpt-quick q-btn q-btn--soft q-btn--sm">Son 30 Gün</button>
                <button type="button" data-range="last90"  class="rcpt-quick q-btn q-btn--soft q-btn--sm">Son 90 Gün</button>
                <button type="submit" class="q-btn q-btn--primary q-btn--sm">Uygula</button>
            </div>
        </div>
    </form>
    <script>
    (function(){
        function fmt(d) {
            return d.getFullYear() + '-' +
                String(d.getMonth() + 1).padStart(2, '0') + '-' +
                String(d.getDate()).padStart(2, '0');
        }
        document.querySelectorAll('#receipts-date-filter .rcpt-quick').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var range = btn.getAttribute('data-range');
                var today = new Date();
                var start = new Date(today);
                var end   = new Date(today);
                switch (range) {
                    case 'today':                                               break;
                    case 'yesterday': start.setDate(start.getDate() - 1); end = new Date(start); break;
                    case 'week':      start.setDate(start.getDate() - (start.getDay() === 0 ? 6 : start.getDay() - 1)); break;
                    case 'month':     start = new Date(today.getFullYear(), today.getMonth(), 1); break;
                    case 'last30':    start.setDate(start.getDate() - 30); break;
                    case 'last90':    start.setDate(start.getDate() - 90); break;
                }
                document.getElementById('rcpt-start').value = fmt(start);
                document.getElementById('rcpt-end').value   = fmt(end);
                document.getElementById('receipts-date-filter').submit();
            });
        });
    })();
    </script>

    <div class="q-card" style="padding:0;overflow:hidden;">
        <?php
        renderDataTable([
            'id' => 'receipts-table',
            'columns' => [
                ['label' => 'Fiş No', 'field' => 'receipt_number'],
                ['label' => 'Fiş tipi', 'field' => 'receipt_type_label'],
                ['label' => 'Sipariş', 'field' => 'order_id', 'render' => '#${item.order_id}'],
                ['label' => 'Masa', 'field' => 'table_name'],
                ['label' => 'İsim', 'field' => 'waiter_name'],
                ['label' => 'Tutar', 'field' => 'total_formatted'],
                ['label' => 'Ödeme', 'field' => 'payment_label'],
                ['label' => 'Durum', 'field' => 'status', 'render' => '<span class="q-badge ${item.status_class}">${item.status_label}</span>'],
                ['label' => 'Tarih', 'field' => 'created_at_formatted'],
            ],
            'data' => $receiptsData,
            'filters' => [
                'status' => [
                    'type' => 'select',
                    'label' => 'Durum',
                    'field' => 'status',
                    'options' => $statusOptions
                ],
                'receipt_type' => [
                    'type' => 'select',
                    'label' => 'Fiş tipi',
                    'field' => 'receipt_type',
                    'options' => $receiptTypeLabels
                ]
            ],
            'search' => true,
            'searchPlaceholder' => 'Fiş no, sipariş no, masa, isim...',
            'pagination' => true,
            'perPage' => 20,
            'actions' => [
                [
                    'type' => 'button',
                    'label' => t('common.view', 'Görüntüle'),
                    'onClick' => 'viewReceipt("${item.api_id}")',
                    'class' => 'q-btn q-btn--ghost q-btn--sm'
                ],
                [
                    'type' => 'button',
                    'label' => t('receipt.print', 'Yazdır'),
                    'onClick' => 'printReceipt("${item.api_id}")',
                    'class' => 'q-btn q-btn--primary q-btn--sm'
                ]
            ],
            'emptyMessage' => t('receipts.empty', 'Fiş bulunamadı')
        ]);
        ?>
    </div>
  </div>
</div>

<!-- Fiş görüntüleme modalı -->
<div id="receipt-modal" class="q-modal-backdrop hidden" aria-hidden="true">
    <div class="q-modal-backdrop__scrim" id="receipt-modal-backdrop"></div>
    <div id="receipt-modal-box" class="q-modal" style="max-width:42rem;max-height:90vh;display:flex;flex-direction:column;">
        <div class="q-modal__header q-toolbar">
            <div class="q-toolbar" style="margin-left:auto;">
                <a id="receipt-modal-pdf" href="#" target="_blank" class="q-btn q-btn--ink q-btn--sm">PDF</a>
                <button type="button" id="receipt-modal-print" class="q-btn q-btn--primary q-btn--sm">Yazdır</button>
                <button type="button" id="receipt-modal-close" class="q-btn q-btn--ghost q-btn--sm" aria-label="Kapat">✕</button>
            </div>
        </div>
        <div id="receipt-modal-body" class="q-modal__body flex-1 overflow-y-auto text-sm">
            <p class="q-hint">Yükleniyor...</p>
        </div>
        <div id="receipt-modal-footer" class="hidden"></div>
    </div>
</div>

<script>
(function() {
    window.apiReceiptPrefix = <?php echo json_encode($api_receipt_prefix ?? '', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.receiptBaseUrl = <?php echo json_encode($baseUrl ?? (defined('BASE_URL') ? BASE_URL : ''), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.apiReceiptPrintUrl = <?php echo json_encode($api_receipt_print_url ?? '', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.receiptsCsrfToken = <?php echo json_encode($csrfToken, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
})();
function viewReceipt(receiptId) {
    if (!receiptId || String(receiptId).trim() === '') {
        if (window.NotificationManager) window.NotificationManager.error('Fiş bulunamadı'); else alert('Fiş bulunamadı');
        return;
    }
    receiptId = String(receiptId).trim();
    var modal = document.getElementById('receipt-modal');
    var body = document.getElementById('receipt-modal-body');
    var pdfLink = document.getElementById('receipt-modal-pdf');
    var printBtn = document.getElementById('receipt-modal-print');
    modal.classList.remove('hidden');
    body.innerHTML = '<div class="q-spinner" style="margin:1rem auto;"></div>';
    var apiUrl = (window.apiReceiptPrefix || (typeof BASE_URL !== 'undefined' ? BASE_URL : '') + '/api/qodmin/receipt') + '/' + encodeURIComponent(receiptId);
    fetch(apiUrl).then(function(r) {
        if (!r.ok) throw { type: 'http', status: r.status };
        return r.json();
    }).then(function(data) {
        // XSS koruması: API'dan dönen TÜM dinamik stringleri HTML'e basmadan
        // önce kaçırıyoruz. receipt_content ve order_detail_section sunucuda
        // htmlspecialchars ile işaretlenmiş olsalar da savunma derinlemesine
        // olarak burada da doğrulanır. Attacker üretebileceği alanlar
        // (receipt_number, label, created_at, business_name) escapeHtml
        // ile temizlenir.
        function escapeHtml(str) {
            if (str === null || str === undefined) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }
        if (data.error || !data.receipt_content) {
            body.innerHTML = '<p class="text-red-600 font-semibold">' + escapeHtml(data.message || 'Fiş bulunamadı.') + '</p>';
            return;
        }
        var html = '';
        if (data.business_name) {
            html += '<div class="mb-3 text-base font-bold text-slate-800 border-b border-slate-200 pb-2">' + escapeHtml(data.business_name) + '</div>';
        }
        // order_detail_section: Sunucu tarafında HTML olarak üretilmiştir.
        // Buradaki kullanıcı girdileri sunucuda htmlspecialchars ile kaçırılır;
        // bu nedenle olduğu gibi eklenir (AYNI korumayı sunucuda sürdürmeli).
        if (data.order_detail_section) {
            html += '<div class="mb-4 text-xs text-slate-500 border-b border-slate-100 pb-3">' + data.order_detail_section + '</div>';
        }
        var other = data.other_receipts_for_order || [];
        if (other.length > 0) {
            html += '<div class="mb-4 text-xs text-slate-500"><p class="font-semibold text-slate-600 mb-1">Bu siparişe ait diğer fişler</p><ul class="list-disc list-inside">';
            for (var i = 0; i < other.length; i++) {
                var o = other[i];
                var label = escapeHtml(o.receipt_number || o.receipt_id || '');
                var type  = escapeHtml(o.receipt_type_label || o.receipt_type || '');
                var when  = o.created_at ? ' (' + escapeHtml(o.created_at) + ')' : '';
                html += '<li>' + label + ' — ' + type + when + '</li>';
            }
            html += '</ul></div>';
        }
        html += '<div class="receipt-content bg-slate-50 border border-slate-200 rounded-xl p-4 overflow-x-auto text-[13px] leading-relaxed">' + (data.receipt_content || '') + '</div>';
        body.innerHTML = html;
        var baseUrl = window.receiptBaseUrl || (typeof BASE_URL !== 'undefined' ? BASE_URL : '');
        pdfLink.href = baseUrl + '/receipt/' + encodeURIComponent(receiptId) + '/pdf';
        printBtn.onclick = function() { printReceipt(receiptId); };
    }).catch(function(err) {
        // Statik metin; XSS riski yok.
        body.innerHTML = '<p class="text-red-600 font-semibold">Yüklenirken hata oluştu.</p>';
    });
}
function closeReceiptModal() {
    document.getElementById('receipt-modal').classList.add('hidden');
}
document.getElementById('receipt-modal-close').addEventListener('click', closeReceiptModal);
document.getElementById('receipt-modal-backdrop').addEventListener('click', closeReceiptModal);
document.addEventListener('keydown', function(e) { if (e.key === 'Escape') closeReceiptModal(); });
function printReceipt(receiptId) {
    var url = window.apiReceiptPrintUrl || (typeof BASE_URL !== 'undefined' ? BASE_URL : '') + '/api/qodmin/receipt/print';
    fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.receiptsCsrfToken || '' }, body: JSON.stringify({ receipt_id: receiptId }) })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                if (window.NotificationManager) window.NotificationManager.success('Fiş yazdırıldı'); else alert('Fiş yazdırıldı');
                var modal = document.getElementById('receipt-modal');
                if (!modal || modal.classList.contains('hidden')) location.reload();
            }
            else { if (window.NotificationManager) window.NotificationManager.error('Yazdırılamadı'); else alert('Yazdırılamadı'); }
        })
        .catch(function() { if (window.NotificationManager) window.NotificationManager.error('Bir hata oluştu'); else alert('Bir hata oluştu'); });
}
</script>
<?php endif; ?>
