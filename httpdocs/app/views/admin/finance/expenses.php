<?php
/**
 * Expenses View - Gider yönetimi + Tenant-scoped kategori yönetimi
 * Warm Ember Ops (.q-* design system)
 */

$expenses          = $expenses ?? [];
$suppliers         = $suppliers ?? [];
$expenseCategories = $expense_categories ?? [];
$baseUrl           = BASE_URL;
$isSuperAdminView  = $is_super_admin ?? false;
$apiPrefix         = $isSuperAdminView ? '/api/qodmin' : '/api/business';

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
          <h1 class="q-page-header__title">Gider Yönetimi — İşletme Seçin</h1>
          <p class="q-page-header__subtitle">Giderlerini görüntülemek istediğiniz işletmeyi seçin</p>
        </div>
        <div class="q-page-header__actions">
          <div class="q-field" style="margin:0;min-width:14rem;">
            <input type="text" id="business-search" placeholder="İşletme ara…"
                   onkeyup="BusinessSelector.searchBusinesses(this.value)"
                   class="q-input"/>
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

    <div id="expense-management-view" class="hidden q-stack q-stack--lg">
      <header class="q-page-header">
        <div class="q-toolbar" style="gap:var(--space-3);">
          <button type="button" onclick="backToBusinessSelection()" class="q-icon-btn" aria-label="Geri">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
          </button>
          <div>
            <p class="q-page-header__eyebrow">Finans</p>
            <h1 class="q-page-header__title"><span id="selected-business-name"></span> — Gider Yönetimi</h1>
          </div>
        </div>
      </header>
    <?php else: ?>

    <header class="q-page-header">
      <div>
        <p class="q-page-header__eyebrow">Finans</p>
        <h1 class="q-page-header__title"><?php echo t('finance.expenses.title', 'Gider Yönetimi'); ?></h1>
        <p class="q-page-header__subtitle"><?php echo t('finance.expenses.subtitle', 'İşletme giderlerinizi kaydedin ve kategorilere ayırın.'); ?></p>
      </div>
      <div class="q-page-header__actions">
        <button type="button" onclick="openCategoryManager()" class="q-btn q-btn--secondary q-btn--sm">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5a1.99 1.99 0 011.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.99 1.99 0 013 12V7a4 4 0 014-4z"/></svg>
          Kategori Yönetimi
        </button>
      </div>
    </header>

    <?php if (!$isSuperAdminView): ?>
    <nav class="q-tab-row q-tab-row--card" role="tablist" aria-label="Finans menüsü">
      <a href="<?php echo $baseUrl; ?>/business/finance" role="tab" aria-selected="<?php echo $financeNavActive('overview') ? 'true' : 'false'; ?>" class="q-tab whitespace-nowrap <?php echo $financeNavActive('overview') ? 'selected' : ''; ?>">Genel Bakış</a>
      <a href="<?php echo $baseUrl; ?>/business/finance/expenses" role="tab" aria-selected="<?php echo $financeNavActive('/business/finance/expenses') ? 'true' : 'false'; ?>" class="q-tab whitespace-nowrap selected">Giderler</a>
      <a href="<?php echo $baseUrl; ?>/business/finance/invoices" role="tab" aria-selected="<?php echo $financeNavActive('/business/finance/invoices') ? 'true' : 'false'; ?>" class="q-tab whitespace-nowrap <?php echo $financeNavActive('/business/finance/invoices') ? 'selected' : ''; ?>">Faturalar</a>
      <a href="<?php echo $baseUrl; ?>/business/finance/suppliers" role="tab" aria-selected="<?php echo $financeNavActive('/business/finance/suppliers') ? 'true' : 'false'; ?>" class="q-tab whitespace-nowrap <?php echo $financeNavActive('/business/finance/suppliers') ? 'selected' : ''; ?>">Tedarikçiler</a>
      <a href="<?php echo $baseUrl; ?>/business/finance/waste" role="tab" aria-selected="<?php echo $financeNavActive('/business/finance/waste') ? 'true' : 'false'; ?>" class="q-tab whitespace-nowrap <?php echo $financeNavActive('/business/finance/waste') ? 'selected' : ''; ?>">İsraf</a>
      <a href="<?php echo $baseUrl; ?>/business/inventory" role="tab" aria-selected="<?php echo $financeNavActive('/business/inventory') ? 'true' : 'false'; ?>" class="q-tab whitespace-nowrap <?php echo $financeNavActive('/business/inventory') ? 'selected' : ''; ?>">Stok Takibi</a>
    </nav>
    <?php endif; ?>

    <?php endif; ?>

    <div class="q-grid q-grid--sidebar">
      <section class="q-stack q-stack--md" id="expenses-container" style="min-width:0;">
        <?php if (empty($expenses)): ?>
          <div class="q-card q-card--pad">
            <p class="q-empty"><?php echo t('finance.expenses.noRecords', 'Gider kaydı yok.'); ?></p>
          </div>
        <?php else: ?>
          <div class="q-card" style="padding:0;overflow:hidden;">
            <div style="overflow-x:auto;">
              <table class="q-table">
                <thead>
                  <tr>
                    <th><?php echo t('common.description', 'Açıklama'); ?></th>
                    <th><?php echo t('finance.expenses.category', 'Kategori'); ?></th>
                    <th><?php echo t('common.date', 'Tarih'); ?></th>
                    <th style="text-align:right;"><?php echo t('finance.expenses.amount', 'Tutar'); ?></th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($expenses as $expense): ?>
                    <tr>
                      <td class="font-medium"><?php echo htmlspecialchars($expense['title'] ?? $expense['description'] ?? t('finance.expenses.expense', 'Gider')); ?></td>
                      <td>
                        <?php if (!empty($expense['category'])): ?>
                          <span class="q-badge q-badge--danger"><?php echo htmlspecialchars($expense['category']); ?></span>
                        <?php else: ?>
                          <span class="q-badge q-badge--neutral">—</span>
                        <?php endif; ?>
                      </td>
                      <td class="text-muted"><?php echo formatDate(strtotime($expense['date'] ?? 'now')); ?></td>
                      <td style="text-align:right;font-weight:var(--font-weight-bold);color:var(--color-status-danger);"><?php echo formatCurrency($expense['amount'] ?? 0); ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        <?php endif; ?>
      </section>

      <aside class="q-card q-card--pad q-stack q-stack--md" style="height:fit-content;position:sticky;top:var(--space-4);">
        <h2 class="q-card__title" style="margin:0;font-size:var(--font-size-lg);"><?php echo t('finance.expenses.newExpense', 'Yeni Gider'); ?></h2>
        <form id="expense-form" method="POST" action="<?php echo $baseUrl; ?>/api/expense/add" class="q-stack q-stack--md" onsubmit="return false;">
          <?php echo csrf_field(); ?>
          <div class="q-field">
            <div class="q-toolbar" style="justify-content:space-between;margin-bottom:var(--space-1);">
              <label class="q-label" for="expense-category-select"><?php echo t('finance.expenses.category', 'Kategori'); ?></label>
              <button type="button" onclick="openCategoryManager()" class="q-btn q-btn--ghost q-btn--sm">+ Yönet</button>
            </div>
            <select name="category" id="expense-category-select" required class="q-input q-select">
              <option value="">— Seçiniz —</option>
              <?php foreach ($expenseCategories as $cat): ?>
                <option value="<?php echo htmlspecialchars($cat['label']); ?>"><?php echo htmlspecialchars($cat['label']); ?></option>
              <?php endforeach; ?>
            </select>
            <?php if (empty($expenseCategories)): ?>
              <p class="q-hint">Henüz gider kategorisi yok. “Yönet” ile oluşturabilirsiniz.</p>
            <?php endif; ?>
          </div>
          <div class="q-field">
            <label class="q-label" for="expense-amount"><?php echo t('finance.expenses.amount', 'Tutar'); ?></label>
            <input type="number" name="amount" id="expense-amount" step="0.01" required class="q-input"/>
          </div>
          <div class="q-field">
            <label class="q-label" for="expense-description"><?php echo t('common.description', 'Açıklama'); ?></label>
            <input type="text" name="description" id="expense-description" required class="q-input"/>
          </div>
          <div class="q-field">
            <label class="q-label" for="expense-date"><?php echo t('common.date', 'Tarih'); ?></label>
            <input type="date" name="date" id="expense-date" value="<?php echo date('Y-m-d'); ?>" required class="q-input"/>
          </div>
          <button type="submit" class="q-btn q-btn--primary q-btn--block">
            <?php echo t('finance.expenses.addExpense', 'Gider Ekle'); ?>
          </button>
        </form>
      </aside>
    </div>

    <?php if ($isSuperAdminView): ?>
    </div>
    <?php endif; ?>

  </div>
</div>

<!-- Category Manager Modal -->
<div id="categoryModal" class="q-modal-backdrop hidden" role="dialog" aria-modal="true" aria-labelledby="categoryModalTitle">
  <div class="q-modal-backdrop__scrim" onclick="closeCategoryManager()"></div>
  <div class="q-modal">
    <div class="q-modal__header">
      <h3 id="categoryModalTitle" class="q-modal__title">Gider Kategorileri</h3>
      <button type="button" onclick="closeCategoryManager()" class="q-icon-btn" aria-label="Kapat">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>
    <form id="categoryCreateForm" onsubmit="return createCategory(event)" class="q-toolbar" style="margin-bottom:var(--space-4);">
      <input type="text" id="newCategoryLabel" required maxlength="120" placeholder="Yeni kategori (örn. Kira)" class="q-input" style="flex:1;"/>
      <button type="submit" class="q-btn q-btn--primary q-btn--sm">Ekle</button>
    </form>
    <div id="categoryList" class="q-stack q-stack--sm" style="max-height:20rem;overflow-y:auto;">
      <p class="q-empty" style="padding:var(--space-6);">Yükleniyor…</p>
    </div>
  </div>
</div>

<script>
const FINANCE_API_PREFIX = <?php echo json_encode($apiPrefix); ?>;
const CATEGORY_TYPE = 'EXPENSE';
const baseUrl = <?php echo json_encode($baseUrl, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

<?php if ($isSuperAdminView): ?>
const bsScript = document.createElement('script');
bsScript.src = baseUrl + '/assets/js/business-selector.js?v=<?php echo time(); ?>';
bsScript.onload = function() {
    if (typeof BusinessSelector === 'undefined') return;
    BusinessSelector.init({ baseUrl: baseUrl });
    const urlBusinessId = new URLSearchParams(window.location.search).get('business_id');
    if (urlBusinessId) {
        window.currentBusinessId = urlBusinessId;
        BusinessSelector.loadBusinesses().then(list => {
            const match = (Array.isArray(list) ? list : []).find(b => (b.business_id || b.id) === urlBusinessId);
            const name = match ? (match.company_name || match.business_name || match.name || 'İşletme') : 'İşletme';
            BusinessSelector.showContentView('business-selection-view', 'expense-management-view', name);
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
    BusinessSelector.showSelectionView('business-selection-view', 'expense-management-view');
    const c = document.getElementById('expenses-container'); if (c) c.innerHTML = '';
};
<?php endif; ?>

function escapeHtml(t) { const d = document.createElement('div'); d.textContent = t ?? ''; return d.innerHTML; }
function formatCurrency(amount) { return new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY', minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(amount || 0); }

function openCategoryManager() { document.getElementById('categoryModal').classList.remove('hidden'); refreshCategoryList(); }
function closeCategoryManager() { document.getElementById('categoryModal').classList.add('hidden'); }

async function refreshCategoryList() {
    const list = document.getElementById('categoryList');
    list.innerHTML = '<p class="q-empty" style="padding:var(--space-6);">Yükleniyor…</p>';
    try {
        const res = await fetch(`${baseUrl}${FINANCE_API_PREFIX}/finance/categories?type=${CATEGORY_TYPE}`, { credentials: 'same-origin' });
        const data = await res.json();
        if (!data.success) throw new Error(data.error || 'Yüklenemedi');
        renderCategoryList(data.categories || []);
        refreshCategoryOptions(data.categories || []);
    } catch (e) {
        list.innerHTML = `<p class="q-empty" style="padding:var(--space-6);color:var(--color-status-danger);">Hata: ${escapeHtml(e.message)}</p>`;
    }
}

function renderCategoryList(cats) {
    const list = document.getElementById('categoryList');
    if (!cats.length) {
        list.innerHTML = '<p class="q-empty" style="padding:var(--space-6);">Henüz kategori yok.</p>';
        return;
    }
    list.innerHTML = cats.map(c => `
        <div class="q-toolbar" style="padding:var(--space-3);background:var(--color-surface-2);border-radius:var(--radius-md);border:1px solid var(--color-border-1);">
            <div style="min-width:0;flex:1;">
                <div style="font-weight:var(--font-weight-bold);">${escapeHtml(c.label)}</div>
                <div class="q-hint">${c.usage_count || 0} kayıt${c.is_archived == 1 ? ' · Arşivlenmiş' : ''}</div>
            </div>
            <div class="q-toolbar" style="gap:var(--space-1);">
                <button type="button" onclick='renameCategory(${JSON.stringify(c.category_id)}, ${JSON.stringify(c.label)})' class="q-icon-btn" title="Yeniden adlandır" aria-label="Yeniden adlandır">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                </button>
                <button type="button" onclick='deleteCategory(${JSON.stringify(c.category_id)})' class="q-icon-btn" title="Sil" aria-label="Sil" style="color:var(--color-status-danger);">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                </button>
            </div>
        </div>`).join('');
}

function refreshCategoryOptions(cats) {
    const sel = document.getElementById('expense-category-select');
    if (!sel) return;
    const current = sel.value;
    const visible = cats.filter(c => c.is_archived != 1);
    sel.innerHTML = '<option value="">— Seçiniz —</option>' + visible.map(c => `<option value="${escapeHtml(c.label)}">${escapeHtml(c.label)}</option>`).join('');
    if (visible.some(c => c.label === current)) sel.value = current;
}

async function createCategory(e) {
    e.preventDefault();
    const input = document.getElementById('newCategoryLabel');
    const label = input.value.trim();
    if (!label) return false;
    const fd = new FormData();
    fd.append('type', CATEGORY_TYPE);
    fd.append('label', label);
    try {
        const res = await fetch(`${baseUrl}${FINANCE_API_PREFIX}/finance/categories`, { method: 'POST', body: fd, credentials: 'same-origin' });
        const data = await res.json();
        if (!data.success) throw new Error(data.error || 'Eklenemedi');
        input.value = '';
        refreshCategoryList();
        window.NotificationManager && window.NotificationManager.success('Kategori eklendi');
    } catch (err) {
        window.NotificationManager ? window.NotificationManager.error(err.message) : alert(err.message);
    }
    return false;
}

async function renameCategory(categoryId, currentLabel) {
    const next = prompt('Yeni kategori adı:', currentLabel);
    if (next == null) return;
    const trimmed = next.trim();
    if (!trimmed || trimmed === currentLabel) return;
    const fd = new FormData();
    fd.append('category_id', categoryId);
    fd.append('label', trimmed);
    try {
        const res = await fetch(`${baseUrl}${FINANCE_API_PREFIX}/finance/categories/rename`, { method: 'POST', body: fd, credentials: 'same-origin' });
        const data = await res.json();
        if (!data.success) throw new Error(data.error || 'Güncellenemedi');
        refreshCategoryList();
        window.NotificationManager && window.NotificationManager.success('Kategori güncellendi');
    } catch (err) {
        window.NotificationManager ? window.NotificationManager.error(err.message) : alert(err.message);
    }
}

async function deleteCategory(categoryId) {
    const ok = window.NotificationManager
        ? await window.NotificationManager.confirm('Bu kategoriyi silmek istediğine emin misin?', 'Kategoriyi Sil')
        : confirm('Silinsin mi?');
    if (!ok) return;
    const fd = new FormData();
    fd.append('category_id', categoryId);
    try {
        const res = await fetch(`${baseUrl}${FINANCE_API_PREFIX}/finance/categories/delete`, { method: 'POST', body: fd, credentials: 'same-origin' });
        const data = await res.json();
        if (!data.success) throw new Error(data.error || 'Silinemedi');
        if (data.archived) {
            window.NotificationManager && window.NotificationManager.success(`Kategori arşivlendi (${data.usage_count} kayıt kullanıyor).`);
        } else {
            window.NotificationManager && window.NotificationManager.success('Kategori silindi');
        }
        refreshCategoryList();
    } catch (err) {
        window.NotificationManager ? window.NotificationManager.error(err.message) : alert(err.message);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const f = document.getElementById('expense-form');
    if (f) f.addEventListener('submit', handleExpenseFormSubmit);
});

async function handleExpenseFormSubmit(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);
    const urlBusinessId = new URLSearchParams(window.location.search).get('business_id');
    const activeBusinessId = window.currentBusinessId || urlBusinessId;
    if (activeBusinessId && !formData.has('business_id')) formData.set('business_id', activeBusinessId);
    const submitButton = form.querySelector('button[type="submit"]');
    const originalText = submitButton ? submitButton.textContent : 'Ekle';
    if (submitButton) { submitButton.disabled = true; submitButton.textContent = 'Ekleniyor…'; }
    try {
        const res = await fetch(form.action, { method: 'POST', body: formData, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const ct = res.headers.get('content-type') || '';
        if (!ct.includes('application/json')) throw new Error('Sunucu beklenmedik yanıt döndü.');
        const data = await res.json();
        if (!data.success) throw new Error(data.message || data.error || 'Gider eklenemedi');
        window.NotificationManager && window.NotificationManager.success('Gider eklendi');
        setTimeout(() => location.reload(), 400);
    } catch (err) {
        window.NotificationManager ? window.NotificationManager.error(err.message) : alert(err.message);
        if (submitButton) { submitButton.disabled = false; submitButton.textContent = originalText; }
    }
    return false;
}
</script>
