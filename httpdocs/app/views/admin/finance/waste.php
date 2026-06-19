<?php
/**
 * Waste Records View - Atık / fire kayıtları
 * Warm Ember Ops (.q-* design system)
 */

$waste_records = $waste_records ?? [];
$baseUrl = BASE_URL;
$isSuperAdminView = $is_super_admin ?? false;
$apiPrefix = $isSuperAdminView ? '/api/qodmin' : '/api/business';

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
          <h1 class="q-page-header__title">Fire Kayıtları — İşletme Seçin</h1>
          <p class="q-page-header__subtitle">Fire kayıtlarını görüntülemek istediğiniz işletmeyi seçin</p>
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

    <div id="waste-management-view" class="hidden q-stack q-stack--lg">
      <header class="q-page-header">
        <div class="q-toolbar" style="gap:var(--space-3);">
          <button type="button" onclick="backToBusinessSelection()" class="q-icon-btn" aria-label="Geri">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
          </button>
          <div>
            <p class="q-page-header__eyebrow">Finans</p>
            <h1 class="q-page-header__title"><span id="selected-business-name"></span> — Fire Kayıtları</h1>
          </div>
        </div>
      </header>
    <?php else: ?>

    <header class="q-page-header">
      <div>
        <p class="q-page-header__eyebrow">Finans</p>
        <h1 class="q-page-header__title"><?php echo t('finance.fire.title', 'Fire Kayıtları'); ?></h1>
        <p class="q-page-header__subtitle"><?php echo t('finance.fire.subtitle', 'Atık ve fire kayıtlarını takip edin.'); ?></p>
      </div>
    </header>

    <nav class="q-tab-row q-tab-row--card" role="tablist" aria-label="Finans menüsü">
      <a href="<?php echo $baseUrl; ?>/business/finance" role="tab" class="q-tab whitespace-nowrap <?php echo $financeNavActive('overview') ? 'selected' : ''; ?>">Genel Bakış</a>
      <a href="<?php echo $baseUrl; ?>/business/finance/expenses" role="tab" class="q-tab whitespace-nowrap <?php echo $financeNavActive('/business/finance/expenses') ? 'selected' : ''; ?>">Giderler</a>
      <a href="<?php echo $baseUrl; ?>/business/finance/invoices" role="tab" class="q-tab whitespace-nowrap <?php echo $financeNavActive('/business/finance/invoices') ? 'selected' : ''; ?>">Faturalar</a>
      <a href="<?php echo $baseUrl; ?>/business/finance/suppliers" role="tab" class="q-tab whitespace-nowrap <?php echo $financeNavActive('/business/finance/suppliers') ? 'selected' : ''; ?>">Tedarikçiler</a>
      <a href="<?php echo $baseUrl; ?>/business/finance/waste" role="tab" aria-selected="true" class="q-tab whitespace-nowrap selected">İsraf</a>
      <a href="<?php echo $baseUrl; ?>/business/inventory" role="tab" class="q-tab whitespace-nowrap <?php echo $financeNavActive('/business/inventory') ? 'selected' : ''; ?>">Stok Takibi</a>
    </nav>

    <?php endif; ?>

    <div class="q-grid q-grid--sidebar">
      <section id="waste-container" style="min-width:0;">
        <?php if ($isSuperAdminView): ?>
        <?php elseif (empty($waste_records)): ?>
          <div class="q-card q-card--pad"><p class="q-empty"><?php echo t('finance.fire.noRecords', 'Fire kaydı yok.'); ?></p></div>
        <?php else: ?>
          <div class="q-card" style="padding:0;overflow:hidden;">
            <div style="overflow-x:auto;">
              <table class="q-table">
                <thead>
                  <tr>
                    <th><?php echo t('menu.ingredient', 'Malzeme'); ?></th>
                    <th><?php echo t('finance.waste.reason', 'Neden'); ?></th>
                    <th><?php echo t('common.date', 'Tarih'); ?></th>
                    <th style="text-align:right;"><?php echo t('common.quantity', 'Miktar'); ?></th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($waste_records as $waste): ?>
                    <tr>
                      <td class="font-medium"><?php echo htmlspecialchars($waste['ingredient_name'] ?? t('menu.ingredient', 'Malzeme')); ?></td>
                      <td><span class="q-badge q-badge--warning"><?php echo htmlspecialchars($waste['reason'] ?? '—'); ?></span></td>
                      <td class="text-muted"><?php echo htmlspecialchars($waste['date'] ?? date('Y-m-d')); ?></td>
                      <td style="text-align:right;font-weight:var(--font-weight-bold);color:var(--color-status-danger);">
                        <?php echo htmlspecialchars($waste['amount'] ?? 0); ?> <?php echo htmlspecialchars($waste['unit'] ?? ''); ?>
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
        <h2 class="q-card__title" style="margin:0;font-size:var(--font-size-lg);"><?php echo t('finance.fire.newRecord', 'Yeni Fire Kaydı'); ?></h2>
        <form id="waste-form" method="POST" enctype="multipart/form-data" action="<?php echo $baseUrl; ?>/api/waste/add" class="q-stack q-stack--md" onsubmit="event.preventDefault(); handleWasteFormSubmit(event); return false;">
          <?php echo csrf_field(); ?>

          <div class="q-field">
            <label class="q-label" for="waste-ingredient-select"><?php echo t('menu.ingredient', 'Malzeme'); ?></label>
            <select name="ingredient_id" id="waste-ingredient-select" required class="q-input q-select">
              <option value=""><?php echo htmlspecialchars(t('finance.waste.selectIngredient', 'Malzeme seçin…')); ?></option>
            </select>
          </div>

          <div class="q-field hidden" id="waste-batch-wrapper">
            <label class="q-label" for="waste-batch-select"><?php echo htmlspecialchars(t('finance.waste.batch', 'Parti / Lot (opsiyonel)')); ?></label>
            <select name="purchase_item_id" id="waste-batch-select" class="q-input q-select">
              <option value=""><?php echo htmlspecialchars(t('finance.waste.noBatch', 'Parti seçme (varsayılan)')); ?></option>
            </select>
            <p class="q-hint"><?php echo htmlspecialchars(t('finance.waste.batchHint', 'Parti seçerseniz tedarikçi ve birim maliyet otomatik bağlanır.')); ?></p>
          </div>

          <div class="q-grid q-grid--2">
            <div class="q-field">
              <label class="q-label" for="waste-amount"><?php echo t('common.quantity', 'Miktar'); ?></label>
              <input type="number" name="amount" id="waste-amount" step="0.001" required class="q-input"/>
            </div>
            <div class="q-field">
              <label class="q-label" for="waste-unit"><?php echo htmlspecialchars(t('common.unit', 'Birim')); ?></label>
              <input type="text" name="unit" id="waste-unit" value="adet" class="q-input"/>
            </div>
          </div>

          <div class="q-field">
            <label class="q-label" for="waste-reason"><?php echo t('finance.waste.reason', 'Neden'); ?></label>
            <select name="reason" id="waste-reason" required class="q-input q-select">
              <option value="SPOILAGE"><?php echo htmlspecialchars(t('finance.waste.reason.spoilage', 'Bozulma')); ?></option>
              <option value="EXPIRED"><?php echo htmlspecialchars(t('finance.waste.reason.expired', 'Son kullanma tarihi geçti')); ?></option>
              <option value="DAMAGED"><?php echo htmlspecialchars(t('finance.waste.reason.damaged', 'Hasarlı')); ?></option>
              <option value="CONTAMINATED"><?php echo htmlspecialchars(t('finance.waste.reason.contaminated', 'Kirlenme/Bulaşma')); ?></option>
              <option value="BURNT"><?php echo htmlspecialchars(t('finance.waste.reason.burnt', 'Yanma')); ?></option>
              <option value="SPILLAGE"><?php echo htmlspecialchars(t('finance.waste.reason.spillage', 'Dökülme')); ?></option>
              <option value="OVER_PRODUCTION"><?php echo htmlspecialchars(t('finance.waste.reason.overProduction', 'Aşırı üretim')); ?></option>
              <option value="QUALITY_DEFECT"><?php echo htmlspecialchars(t('finance.waste.reason.qualityDefect', 'Kalite sorunu')); ?></option>
              <option value="CUSTOMER_RETURN"><?php echo htmlspecialchars(t('finance.waste.reason.customerReturn', 'Müşteri iadesi')); ?></option>
              <option value="KITCHEN_PREP_LOSS"><?php echo htmlspecialchars(t('finance.waste.reason.kitchenPrepLoss', 'Mutfak hazırlama kaybı')); ?></option>
              <option value="MISTAKE"><?php echo htmlspecialchars(t('finance.waste.reason.mistake', 'İnsan hatası')); ?></option>
              <option value="OTHER"><?php echo htmlspecialchars(t('common.other', 'Diğer')); ?></option>
            </select>
          </div>

          <div class="q-field">
            <label class="q-label" for="waste-reason-detail"><?php echo htmlspecialchars(t('finance.waste.reasonDetail', 'Açıklama')); ?></label>
            <textarea name="reason_detail" id="waste-reason-detail" rows="2" placeholder="<?php echo htmlspecialchars(t('finance.waste.reasonDetailPlaceholder', 'Kısa not (opsiyonel)')); ?>" class="q-input q-textarea"></textarea>
          </div>

          <div class="q-field">
            <label class="q-label" for="waste-supplier-select"><?php echo htmlspecialchars(t('finance.waste.supplier', 'Tedarikçi (opsiyonel)')); ?></label>
            <select name="supplier_id" id="waste-supplier-select" class="q-input q-select">
              <option value=""><?php echo htmlspecialchars(t('common.none', 'Seçilmedi')); ?></option>
            </select>
          </div>

          <div class="q-field">
            <label class="q-label" for="waste-images"><?php echo htmlspecialchars(t('finance.waste.images', 'Görseller (opsiyonel)')); ?></label>
            <input type="file" name="images[]" id="waste-images" accept="image/*" multiple class="q-input"/>
          </div>

          <div class="q-field">
            <label class="q-label" for="waste-date"><?php echo t('common.date', 'Tarih'); ?></label>
            <input type="date" name="date" id="waste-date" value="<?php echo date('Y-m-d'); ?>" required class="q-input"/>
          </div>

          <button type="submit" class="q-btn q-btn--primary q-btn--block"><?php echo t('finance.fire.addRecord', 'Fire Kaydı Ekle'); ?></button>
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
const WASTE_API_PREFIX = <?php echo json_encode($apiPrefix); ?>;

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text ?? '';
    return div.innerHTML;
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
            BusinessSelector.showContentView('business-selection-view', 'waste-management-view', name);
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
    BusinessSelector.showSelectionView('business-selection-view', 'waste-management-view');
    const container = document.getElementById('waste-container');
    if (container) container.innerHTML = '';
};

function renderWasteTable(records) {
    const container = document.getElementById('waste-container');
    if (!container) return;
    if (!records || !records.length) {
        container.innerHTML = '<div class="q-card q-card--pad"><p class="q-empty"><?php echo t('finance.fire.noRecords', 'Fire kaydı yok.'); ?></p></div>';
        return;
    }
    const rows = records.map(waste => {
        const date = waste.date ? new Date(waste.date).toLocaleDateString('tr-TR') : '—';
        return `<tr>
            <td class="font-medium">${escapeHtml(waste.ingredient_name || '<?php echo t('menu.ingredient', 'Malzeme'); ?>')}</td>
            <td><span class="q-badge q-badge--warning">${escapeHtml(waste.reason || '—')}</span></td>
            <td class="text-muted">${escapeHtml(date)}</td>
            <td style="text-align:right;font-weight:var(--font-weight-bold);color:var(--color-status-danger);">${escapeHtml(waste.amount || 0)} ${escapeHtml(waste.unit || '')}</td>
        </tr>`;
    }).join('');
    container.innerHTML = `<div class="q-card" style="padding:0;overflow:hidden;"><div style="overflow-x:auto;"><table class="q-table"><thead><tr>
        <th><?php echo t('menu.ingredient', 'Malzeme'); ?></th>
        <th><?php echo t('finance.waste.reason', 'Neden'); ?></th>
        <th><?php echo t('common.date', 'Tarih'); ?></th>
        <th style="text-align:right;"><?php echo t('common.quantity', 'Miktar'); ?></th>
    </tr></thead><tbody>${rows}</tbody></table></div></div>`;
}

function loadBusinessWaste(businessId, businessName) {
    window.currentBusinessId = businessId;
    const container = document.getElementById('waste-container');
    if (container) container.innerHTML = '<div class="q-card q-card--pad"><p class="q-empty"><span class="q-spinner"></span> Yükleniyor…</p></div>';
    fetch(`<?php echo BASE_URL; ?>${WASTE_API_PREFIX}/businesses/${businessId}/waste`)
        .then(r => r.json())
        .then(data => {
            if (data.success && data.waste_records) {
                BusinessSelector.showContentView('business-selection-view', 'waste-management-view', businessName);
                renderWasteTable(data.waste_records);
            }
        })
        .catch(error => {
            console.error('Error loading waste records:', error);
            if (container) container.innerHTML = '<div class="q-card q-card--pad"><p class="q-empty" style="color:var(--color-status-danger);">Hata: Fire kayıtları yüklenemedi.</p></div>';
        });
}
<?php endif; ?>

(function initWasteFormPhase2() {
    const ingSel = document.getElementById('waste-ingredient-select');
    const supSel = document.getElementById('waste-supplier-select');
    const batchWrap = document.getElementById('waste-batch-wrapper');
    const batchSel = document.getElementById('waste-batch-select');
    const unitInp = document.getElementById('waste-unit');
    if (!ingSel) return;

    function jsonHeaders() { return { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }; }
    async function safeJson(url) {
        const r = await fetch(url, { headers: jsonHeaders() });
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.json();
    }

    safeJson(baseUrl + WASTE_API_PREFIX + '/ingredients').then(res => {
        const rows = Array.isArray(res) ? res : (res.data || []);
        rows.forEach(row => {
            const opt = document.createElement('option');
            opt.value = row.ingredient_id || row.id || '';
            opt.textContent = row.name || row.ingredient_name || '—';
            opt.dataset.unit = row.unit || 'adet';
            ingSel.appendChild(opt);
        });
    }).catch(() => {});

    if (supSel) {
        safeJson(baseUrl + WASTE_API_PREFIX + '/suppliers').then(res => {
            const rows = Array.isArray(res) ? res : (res.data || []);
            rows.forEach(row => {
                const opt = document.createElement('option');
                opt.value = row.supplier_id || row.id || '';
                opt.textContent = row.name || row.company_name || '—';
                supSel.appendChild(opt);
            });
        }).catch(() => {});
    }

    ingSel.addEventListener('change', async function () {
        const id = this.value;
        const opt = this.options[this.selectedIndex];
        if (opt && opt.dataset && opt.dataset.unit && unitInp) unitInp.value = opt.dataset.unit;
        if (!id || !batchWrap || !batchSel) return;
        try {
            const res = await safeJson(baseUrl + WASTE_API_PREFIX + '/purchases/active-lots/' + encodeURIComponent(id));
            const lots = Array.isArray(res) ? res : (res.data || []);
            batchSel.innerHTML = '<option value="">' + <?php echo json_encode(t('finance.waste.noBatch', 'Parti seçme (varsayılan)')); ?> + '</option>';
            if (!lots.length) { batchWrap.classList.add('hidden'); return; }
            lots.forEach(lot => {
                const opt = document.createElement('option');
                opt.value = lot.item_id;
                const parts = [];
                if (lot.batch_no) parts.push('#' + lot.batch_no);
                if (lot.received_at) parts.push(new Date(lot.received_at).toLocaleDateString('tr-TR'));
                if (lot.qty_remaining !== undefined) parts.push(lot.qty_remaining + ' ' + (lot.unit || unitInp.value));
                if (lot.unit_cost) parts.push(Number(lot.unit_cost).toFixed(2) + '₺/' + (lot.unit || unitInp.value));
                opt.textContent = parts.join(' • ') || lot.item_id;
                batchSel.appendChild(opt);
            });
            batchWrap.classList.remove('hidden');
        } catch (e) {
            batchWrap.classList.add('hidden');
        }
    });
})();

window.handleWasteFormSubmit = async function(e) {
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
            window.NotificationManager && window.NotificationManager.success('Fire kaydı başarıyla eklendi');
            form.reset();
            setTimeout(() => location.reload(), 500);
        } else {
            throw new Error(data.message || data.error || 'Fire kaydı eklenirken bir hata oluştu');
        }
    } catch (error) {
        window.NotificationManager ? window.NotificationManager.error('Hata: ' + error.message) : alert(error.message);
        if (submitButton) { submitButton.disabled = false; submitButton.textContent = originalText; }
    }
    return false;
};
</script>
