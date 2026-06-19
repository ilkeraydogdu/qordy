<?php
/**
 * Invoices View - Fatura yönetimi
 * Warm Ember Ops (.q-* design system)
 */

$invoices   = $invoices ?? [];
$suppliers  = $suppliers ?? [];
$baseUrl    = BASE_URL;
$isSuperAdminView = $is_super_admin ?? false;

$financeUri = $_SERVER['REQUEST_URI'] ?? '';
$financeNavActive = static function (string $segment) use ($financeUri): bool {
    if ($segment === 'overview') {
        return strpos($financeUri, '/business/finance') !== false
            && strpos($financeUri, '/business/finance/') === false;
    }
    return strpos($financeUri, $segment) !== false;
};
?>

<div class="q-page q-biz-theme animate-slide-up">
  <div class="q-container q-stack q-stack--lg">

    <?php if ($isSuperAdminView): ?>
    <div id="business-selection-view">
      <header class="q-page-header">
        <div>
          <p class="q-page-header__eyebrow">Finans</p>
          <h1 class="q-page-header__title">Fatura Yönetimi — İşletme Seçin</h1>
          <p class="q-page-header__subtitle">Faturalarını görüntülemek istediğiniz işletmeyi seçin</p>
        </div>
        <div class="q-page-header__actions">
          <div class="q-field" style="margin:0;min-width:14rem;">
            <input type="text" id="business-search" placeholder="İşletme ara…"
                   onkeyup="BusinessSelector.searchBusinesses(this.value)" class="q-input"/>
          </div>
        </div>
      </header>
      <div id="business-grid" class="q-grid q-grid--4">
        <div class="q-empty" style="grid-column:1/-1;padding:var(--space-10);">
          <span class="q-spinner" aria-hidden="true"></span>
          <p>İşletmeler yükleniyor…</p>
        </div>
      </div>
    </div>

    <div id="invoice-management-view" class="hidden q-stack q-stack--lg">
      <header class="q-page-header">
        <div class="q-toolbar" style="gap:var(--space-3);">
          <button type="button" onclick="backToBusinessSelection()" class="q-icon-btn" aria-label="Geri">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
          </button>
          <div>
            <p class="q-page-header__eyebrow">Finans</p>
            <h1 class="q-page-header__title"><span id="selected-business-name"></span> — Fatura Yönetimi</h1>
          </div>
        </div>
      </header>
    <?php else: ?>

    <header class="q-page-header">
      <div>
        <p class="q-page-header__eyebrow">Finans</p>
        <h1 class="q-page-header__title"><?php echo t('finance.invoices.title', 'Fatura Yönetimi'); ?></h1>
        <p class="q-page-header__subtitle"><?php echo t('finance.invoices.subtitle', 'Tedarikçi faturalarını takip edin ve ödemeleri kaydedin.'); ?></p>
      </div>
    </header>

    <nav class="q-tab-row q-tab-row--card" role="tablist" aria-label="Finans menüsü">
      <a href="<?php echo $baseUrl; ?>/business/finance" role="tab" aria-selected="<?php echo $financeNavActive('overview') ? 'true' : 'false'; ?>" class="q-tab whitespace-nowrap <?php echo $financeNavActive('overview') ? 'selected' : ''; ?>">Genel Bakış</a>
      <a href="<?php echo $baseUrl; ?>/business/finance/expenses" role="tab" aria-selected="<?php echo $financeNavActive('/business/finance/expenses') ? 'true' : 'false'; ?>" class="q-tab whitespace-nowrap <?php echo $financeNavActive('/business/finance/expenses') ? 'selected' : ''; ?>">Giderler</a>
      <a href="<?php echo $baseUrl; ?>/business/finance/invoices" role="tab" aria-selected="true" class="q-tab whitespace-nowrap selected">Faturalar</a>
      <a href="<?php echo $baseUrl; ?>/business/finance/suppliers" role="tab" aria-selected="<?php echo $financeNavActive('/business/finance/suppliers') ? 'true' : 'false'; ?>" class="q-tab whitespace-nowrap <?php echo $financeNavActive('/business/finance/suppliers') ? 'selected' : ''; ?>">Tedarikçiler</a>
      <a href="<?php echo $baseUrl; ?>/business/finance/waste" role="tab" aria-selected="<?php echo $financeNavActive('/business/finance/waste') ? 'true' : 'false'; ?>" class="q-tab whitespace-nowrap <?php echo $financeNavActive('/business/finance/waste') ? 'selected' : ''; ?>">İsraf</a>
      <a href="<?php echo $baseUrl; ?>/business/inventory" role="tab" aria-selected="<?php echo $financeNavActive('/business/inventory') ? 'true' : 'false'; ?>" class="q-tab whitespace-nowrap <?php echo $financeNavActive('/business/inventory') ? 'selected' : ''; ?>">Stok Takibi</a>
    </nav>

    <?php endif; ?>

    <div class="q-grid q-grid--sidebar">
      <section id="invoices-container" style="min-width:0;">
        <?php if ($isSuperAdminView): ?>
        <?php elseif (empty($invoices)): ?>
          <div class="q-card q-card--pad">
            <p class="q-empty"><?php echo t('finance.invoices.noRecords', 'Fatura kaydı yok.'); ?></p>
          </div>
        <?php else: ?>
          <div class="q-card" style="padding:0;overflow:hidden;">
            <div style="overflow-x:auto;">
              <table class="q-table">
                <thead>
                  <tr>
                    <th><?php echo t('finance.suppliers.supplier', 'Tedarikçi'); ?></th>
                    <th><?php echo t('finance.invoices.invoiceNumber', 'Fatura No'); ?></th>
                    <th><?php echo t('common.date', 'Tarih'); ?></th>
                    <th style="text-align:right;"><?php echo t('finance.expenses.amount', 'Tutar'); ?></th>
                    <th><?php echo t('finance.invoices.status', 'Durum'); ?></th>
                    <th></th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($invoices as $invoice):
                    $isPaid = !empty($invoice['is_paid']);
                  ?>
                    <tr>
                      <td class="font-medium"><?php echo htmlspecialchars($invoice['supplier_name'] ?? t('finance.suppliers.supplier', 'Tedarikçi')); ?></td>
                      <td><?php echo htmlspecialchars($invoice['invoice_number'] ?? '—'); ?></td>
                      <td class="text-muted"><?php echo htmlspecialchars($invoice['date'] ?? date('Y-m-d')); ?></td>
                      <td style="text-align:right;font-weight:var(--font-weight-bold);color:<?php echo $isPaid ? 'var(--color-status-success)' : 'var(--color-status-danger)'; ?>;">
                        <?php echo formatCurrency($invoice['amount'] ?? 0); ?>
                      </td>
                      <td>
                        <?php if ($isPaid): ?>
                          <span class="q-badge q-badge--success"><?php echo t('finance.invoices.paid', 'Ödendi'); ?></span>
                        <?php else: ?>
                          <span class="q-badge q-badge--warning"><?php echo t('finance.invoices.unpaid', 'Bekliyor'); ?></span>
                        <?php endif; ?>
                      </td>
                      <td style="text-align:right;">
                        <?php if (!$isPaid): ?>
                          <button type="button" onclick="payInvoice('<?php echo htmlspecialchars($invoice['invoice_id'] ?? ''); ?>')" class="q-btn q-btn--primary q-btn--sm">
                            <?php echo t('finance.invoices.pay', 'Öde'); ?>
                          </button>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        <?php endif; ?>
      </section>

      <aside class="q-card q-card--pad q-stack q-stack--md" style="height:fit-content;position:sticky;top:var(--space-4);">
        <h2 class="q-card__title" style="margin:0;font-size:var(--font-size-lg);"><?php echo t('finance.invoices.newInvoice', 'Yeni Fatura'); ?></h2>
        <form id="invoice-form" method="POST" action="<?php echo $baseUrl; ?>/api/invoice/add" class="q-stack q-stack--md" onsubmit="event.preventDefault(); handleInvoiceFormSubmit(event); return false;">
          <?php echo csrf_field(); ?>
          <div class="q-field">
            <label class="q-label" for="invoice-supplier"><?php echo t('finance.suppliers.supplier', 'Tedarikçi'); ?></label>
            <select name="supplier_id" id="invoice-supplier" class="q-input q-select">
              <option value=""><?php echo t('finance.invoices.selectSupplier', 'Tedarikçi Seçin'); ?></option>
              <?php foreach ($suppliers as $supplier): ?>
                <option value="<?php echo htmlspecialchars($supplier['supplier_id'] ?? ''); ?>"><?php echo htmlspecialchars($supplier['name'] ?? ''); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="q-field">
            <label class="q-label" for="invoice-number"><?php echo t('finance.invoices.invoiceNumber', 'Fatura No'); ?></label>
            <input type="text" name="invoice_number" id="invoice-number" required class="q-input"/>
          </div>
          <div class="q-field">
            <label class="q-label" for="invoice-amount"><?php echo t('finance.expenses.amount', 'Tutar'); ?></label>
            <input type="number" name="amount" id="invoice-amount" step="0.01" required class="q-input"/>
          </div>
          <div class="q-field">
            <label class="q-label" for="invoice-date"><?php echo t('common.date', 'Tarih'); ?></label>
            <input type="date" name="date" id="invoice-date" value="<?php echo date('Y-m-d'); ?>" required class="q-input"/>
          </div>
          <div class="q-field">
            <label class="q-label" for="invoice-due"><?php echo t('finance.invoices.dueDate', 'Vade Tarihi'); ?></label>
            <input type="date" name="due_date" id="invoice-due" required class="q-input"/>
          </div>
          <button type="submit" class="q-btn q-btn--primary q-btn--block">
            <?php echo t('finance.invoices.addInvoice', 'Fatura Ekle'); ?>
          </button>
        </form>
      </aside>
    </div>

    <?php if ($isSuperAdminView): ?>
    </div>
    <?php endif; ?>

  </div>
</div>

<script>
const baseUrl = <?php echo json_encode($baseUrl ?? '', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text ?? '';
    return div.innerHTML;
}

function formatCurrency(amount) {
    return new Intl.NumberFormat('tr-TR', {
        style: 'currency',
        currency: 'TRY',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(amount || 0);
}

<?php if ($isSuperAdminView): ?>
const bsScript = document.createElement('script');
bsScript.src = '<?php echo BASE_URL; ?>/assets/js/business-selector.js?v=<?php echo time(); ?>';
bsScript.onload = function() {
    if (typeof BusinessSelector === 'undefined') return;
    BusinessSelector.init({ baseUrl: <?php echo json_encode(BASE_URL); ?> });
    const urlBusinessId = new URLSearchParams(window.location.search).get('business_id');
    if (urlBusinessId) {
        window.currentBusinessId = urlBusinessId;
        BusinessSelector.loadBusinesses().then(list => {
            const match = (Array.isArray(list) ? list : []).find(b => (b.business_id || b.id) === urlBusinessId);
            const name = match ? (match.company_name || match.business_name || match.name || 'İşletme') : 'İşletme';
            BusinessSelector.showContentView('business-selection-view', 'invoice-management-view', name);
        });
    } else {
        BusinessSelector.loadBusinesses().then(() => {
            BusinessSelector.renderBusinessGrid('business-grid', function(businessId) {
                const url = new URL(window.location.href);
                url.searchParams.set('business_id', businessId);
                window.location.href = url.toString();
            });
        });
    }
};
document.head.appendChild(bsScript);

window.backToBusinessSelection = function() {
    BusinessSelector.showSelectionView('business-selection-view', 'invoice-management-view');
    const container = document.getElementById('invoices-container');
    if (container) container.innerHTML = '';
};

function renderInvoicesTable(invoices) {
    const container = document.getElementById('invoices-container');
    if (!container) return;
    if (!invoices || !invoices.length) {
        container.innerHTML = '<div class="q-card q-card--pad"><p class="q-empty"><?php echo t('finance.invoices.noRecords', 'Fatura kaydı yok.'); ?></p></div>';
        return;
    }
    const rows = invoices.map(invoice => {
        const isPaid = invoice.is_paid || 0;
        const date = new Date(invoice.date);
        const formattedDate = date.toLocaleDateString('tr-TR', { day: 'numeric', month: 'long', year: 'numeric' });
        const amountColor = isPaid ? 'var(--color-status-success)' : 'var(--color-status-danger)';
        const statusBadge = isPaid
            ? '<span class="q-badge q-badge--success"><?php echo t('finance.invoices.paid', 'Ödendi'); ?></span>'
            : '<span class="q-badge q-badge--warning"><?php echo t('finance.invoices.unpaid', 'Bekliyor'); ?></span>';
        const payBtn = !isPaid
            ? `<button type="button" onclick="payInvoice('${escapeHtml(invoice.invoice_id)}')" class="q-btn q-btn--primary q-btn--sm"><?php echo t('finance.invoices.pay', 'Öde'); ?></button>`
            : '';
        return `<tr>
            <td class="font-medium">${escapeHtml(invoice.supplier_name || '<?php echo t('finance.suppliers.supplier', 'Tedarikçi'); ?>')}</td>
            <td>${escapeHtml(invoice.invoice_number || '—')}</td>
            <td class="text-muted">${escapeHtml(formattedDate)}</td>
            <td style="text-align:right;font-weight:var(--font-weight-bold);color:${amountColor};">${formatCurrency(invoice.amount || 0)}</td>
            <td>${statusBadge}</td>
            <td style="text-align:right;">${payBtn}</td>
        </tr>`;
    }).join('');
    container.innerHTML = `<div class="q-card" style="padding:0;overflow:hidden;"><div style="overflow-x:auto;"><table class="q-table"><thead><tr>
        <th><?php echo t('finance.suppliers.supplier', 'Tedarikçi'); ?></th>
        <th><?php echo t('finance.invoices.invoiceNumber', 'Fatura No'); ?></th>
        <th><?php echo t('common.date', 'Tarih'); ?></th>
        <th style="text-align:right;"><?php echo t('finance.expenses.amount', 'Tutar'); ?></th>
        <th><?php echo t('finance.invoices.status', 'Durum'); ?></th><th></th>
    </tr></thead><tbody>${rows}</tbody></table></div></div>`;
}

function loadBusinessInvoices(businessId, businessName) {
    window.currentBusinessId = businessId;
    const container = document.getElementById('invoices-container');
    if (container) {
        container.innerHTML = '<div class="q-card q-card--pad"><p class="q-empty"><span class="q-spinner"></span> Yükleniyor…</p></div>';
    }
    const apiPrefix = <?php echo json_encode($isSuperAdminView ? '/api/qodmin' : '/api/business'); ?>;
    fetch(`<?php echo BASE_URL; ?>${apiPrefix}/businesses/${businessId}/invoices`)
        .then(r => r.json())
        .then(data => {
            if (data.success && data.invoices) {
                BusinessSelector.showContentView('business-selection-view', 'invoice-management-view', businessName);
                renderInvoicesTable(data.invoices);
            }
        })
        .catch(error => {
            console.error('Error loading invoices:', error);
            if (container) {
                container.innerHTML = '<div class="q-card q-card--pad"><p class="q-empty" style="color:var(--color-status-danger);">Hata: Faturalar yüklenirken bir sorun oluştu.</p></div>';
            }
        });
}
<?php endif; ?>

window.handleInvoiceFormSubmit = async function(e) {
    e.preventDefault();
    e.stopPropagation();
    const form = e.target;
    const formData = new FormData(form);
    const submitButton = form.querySelector('button[type="submit"]');
    const originalText = submitButton ? submitButton.textContent : 'Ekle';
    const urlBusinessId = new URLSearchParams(window.location.search).get('business_id');
    const activeBusinessId = window.currentBusinessId || urlBusinessId;
    if (activeBusinessId && !formData.has('business_id')) formData.set('business_id', activeBusinessId);
    if (submitButton) { submitButton.disabled = true; submitButton.textContent = 'Ekleniyor…'; }
    try {
        const response = await fetch(form.action, { method: 'POST', body: formData, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) throw new Error('API endpoint JSON döndürmüyor');
        const data = await response.json();
        if (data.success) {
            window.NotificationManager && window.NotificationManager.success('Fatura başarıyla eklendi');
            form.reset();
            setTimeout(() => location.reload(), 500);
        } else {
            throw new Error(data.message || data.error || 'Fatura eklenirken bir hata oluştu');
        }
    } catch (error) {
        window.NotificationManager ? window.NotificationManager.error('Hata: ' + error.message) : alert(error.message);
        if (submitButton) { submitButton.disabled = false; submitButton.textContent = originalText; }
    }
    return false;
};

document.addEventListener('DOMContentLoaded', function() {
    const invoiceForm = document.getElementById('invoice-form');
    if (invoiceForm && !invoiceForm.getAttribute('data-bound')) {
        invoiceForm.setAttribute('data-bound', '1');
        invoiceForm.addEventListener('submit', window.handleInvoiceFormSubmit);
    }
});

async function payInvoice(invoiceId) {
    if (!window.NotificationManager) { console.error('NotificationManager is not available'); return; }
    const confirmed = await window.NotificationManager.confirm('<?php echo t('notifications.invoicePayConfirm'); ?>', '<?php echo t('notifications.invoicePay'); ?>');
    if (!confirmed) return;
    const csrfToken = window.CSRF_TOKEN || (typeof csrf_token !== 'undefined' ? csrf_token : '');
    try {
        const response = await fetch(`${baseUrl}/api/invoice/pay`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
            body: JSON.stringify({ invoice_id: invoiceId })
        });
        const data = await response.json();
        if (data.success) location.reload();
        else window.NotificationManager && window.NotificationManager.error(<?php echo json_encode(t('errors.invoicePayFailed', 'Hata: Fatura ödenemedi'), JSON_UNESCAPED_UNICODE); ?> + (data.error ? ': ' + data.error : ''));
    } catch (error) {
        console.error('Error:', error);
        window.NotificationManager && window.NotificationManager.error(<?php echo json_encode(t('errors.error', 'Bir hata oluştu'), JSON_UNESCAPED_UNICODE); ?>);
    }
}
</script>
