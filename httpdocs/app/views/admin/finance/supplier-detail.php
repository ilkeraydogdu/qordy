<?php
/**
 * Supplier Detail View
 *
 * Drill-down page that powers both /business/finance/suppliers/{id}
 * and /qodmin/finance/suppliers/{id}. All data is fetched client-side
 * via the FinanceController::supplierAnalytics endpoint.
 */

$supplier   = $supplier ?? [];
$isQodmin   = $isQodmin ?? false;
$businessId = $businessId ?? null;
$apiPrefix  = $isQodmin ? '/api/qodmin' : '/api/business';
$listUrl    = ($isQodmin ? '/qodmin/finance/suppliers' : '/business/finance/suppliers');
if ($isQodmin && $businessId) {
    $listUrl .= '?business_id=' . urlencode($businessId);
}
?>

<div class="q-page q-biz-theme animate-slide-up" id="supplier-detail-root"
     data-supplier-id="<?php echo htmlspecialchars($supplier['supplier_id'] ?? '', ENT_QUOTES); ?>"
     data-api-prefix="<?php echo htmlspecialchars($apiPrefix, ENT_QUOTES); ?>"
     data-business-id="<?php echo htmlspecialchars((string)($businessId ?? ''), ENT_QUOTES); ?>">
  <div class="q-container q-stack q-stack--lg">

    <header class="q-page-header">
        <div class="q-toolbar" style="align-items:flex-start;min-width:0;">
            <a href="<?php echo htmlspecialchars($listUrl, ENT_QUOTES); ?>" class="q-btn q-btn--ghost q-btn--sm">
                <?php echo icon_arrow_left(['class' => 'w-5 h-5']); ?>
            </a>
            <div class="min-w-0">
                <p class="q-page-header__eyebrow"><?php echo htmlspecialchars($supplier['category'] ?? 'Tedarikçi', ENT_QUOTES); ?></p>
                <h1 class="q-page-header__title truncate"><?php echo htmlspecialchars($supplier['name'] ?? 'Tedarikçi', ENT_QUOTES); ?></h1>
                <?php if (!empty($supplier['contact'])): ?>
                <p class="q-page-header__subtitle"><?php echo htmlspecialchars($supplier['contact'], ENT_QUOTES); ?></p>
                <?php endif; ?>
            </div>
        </div>

        <div class="q-page-header__actions q-toolbar" style="flex-wrap:wrap;">
            <label for="range-select" class="q-label" style="margin:0;">Zaman aralığı</label>
            <select id="range-select" class="q-input" style="width:auto;">
                <option value="today">Bugün</option>
                <option value="yesterday">Dün</option>
                <option value="week" selected>Son 7 gün</option>
                <option value="month">Bu ay</option>
                <option value="30d">Son 30 gün</option>
                <option value="90d">Son 90 gün</option>
                <option value="year">Bu yıl</option>
            </select>
            <button type="button" id="refresh-btn" class="q-btn q-btn--primary q-btn--sm">Yenile</button>
        </div>
    </header>

    <div id="kpi-grid" class="q-grid q-grid--4">
        <!-- KPI cards injected by JS -->
    </div>

    <section class="q-grid q-grid--sidebar">
        <div class="q-card q-card--pad" style="grid-column:span 2;">
            <div class="q-toolbar" style="margin-bottom:var(--space-3);">
                <h2 class="q-card__title" style="margin:0;">Alış Fişleri</h2>
                <span id="receipts-count" class="q-hint"></span>
            </div>
            <div class="overflow-x-auto">
                <table class="q-table w-full text-sm">
                    <thead>
                        <tr>
                            <th>Tarih</th>
                            <th>Fiş No</th>
                            <th style="text-align:right;">Kalem</th>
                            <th style="text-align:right;">Tutar</th>
                        </tr>
                    </thead>
                    <tbody id="receipts-body">
                        <tr><td colspan="4" class="q-hint text-center py-6">Yükleniyor...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="q-card q-card--pad">
            <h2 class="q-card__title">Güncel Bakiye & Faturalar</h2>
            <div id="balance-box" class="q-stack q-stack--sm text-sm"></div>
        </div>
    </section>

    <section class="q-card q-card--pad">
        <div class="q-toolbar" style="margin-bottom:var(--space-3);">
            <h2 class="q-card__title" style="margin:0;">Tedarik Edilen Ürünler</h2>
            <span id="items-count" class="q-hint"></span>
        </div>
        <div class="overflow-x-auto">
            <table class="q-table w-full text-sm">
                <thead>
                    <tr>
                        <th>Ürün</th>
                        <th style="text-align:right;">Stokta</th>
                        <th style="text-align:right;">Birim</th>
                        <th style="text-align:right;">Birim Maliyet</th>
                        <th style="text-align:right;">Stok Değeri</th>
                        <th>Durum</th>
                    </tr>
                </thead>
                <tbody id="items-body">
                    <tr><td colspan="6" class="q-hint text-center py-6">Yükleniyor...</td></tr>
                </tbody>
            </table>
        </div>
    </section>
  </div>
</div>

<script>
(function(){
    const root       = document.getElementById('supplier-detail-root');
    const supplierId = root.dataset.supplierId;
    const apiPrefix  = root.dataset.apiPrefix;
    const businessId = root.dataset.businessId || null;

    const fmtMoney = n => '₺' + (Number(n) || 0).toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const fmtNum   = n => (Number(n) || 0).toLocaleString('tr-TR');

    function kpiCard(label, value, sub) {
        return `
          <div class="q-card q-card--pad">
              <div class="q-hint">${label}</div>
              <div class="font-black text-xl mt-1" style="color:var(--color-text-primary);">${value}</div>
              ${sub ? `<div class="q-hint mt-1">${sub}</div>` : ''}
          </div>`;
    }

    function renderKpis(k) {
        document.getElementById('kpi-grid').innerHTML = [
            kpiCard('Alış Toplamı', fmtMoney(k.purchase_total), `${fmtNum(k.purchase_count)} fiş`),
            kpiCard('İade Tutarı',  fmtMoney(k.return_cost),   `${fmtNum(k.return_count)} iade`),
            kpiCard('Fire Tutarı',  fmtMoney(k.waste_cost),    `${fmtNum(k.waste_count)} kayıt`),
            kpiCard('Stok Değeri',  fmtMoney(k.stock_value),   `${fmtNum(k.items_count)} ürün`),
            kpiCard('Ödenmemiş Fatura', fmtMoney(k.unpaid_total), `${fmtNum(k.unpaid_count)} fatura`),
            kpiCard('Güncel Bakiye', fmtMoney(k.current_balance), 'Tedarikçi cari'),
        ].join('');
    }

    function renderReceipts(list) {
        const body = document.getElementById('receipts-body');
        const countEl = document.getElementById('receipts-count');
        countEl.textContent = (list.length || 0) + ' kayıt';
        if (!list.length) {
            body.innerHTML = `<tr><td colspan="4" class="q-hint text-center py-6">Seçilen aralıkta alış fişi yok.</td></tr>`;
            return;
        }
        body.innerHTML = list.map(r => {
            const d = r.received_at ? new Date(r.received_at.replace(' ', 'T')) : null;
            const when = d ? d.toLocaleDateString('tr-TR') + ' ' + d.toLocaleTimeString('tr-TR', { hour: '2-digit', minute: '2-digit' }) : '-';
            return `
              <tr class="hover:bg-slate-50">
                  <td class="py-2 font-semibold">${when}</td>
                  <td class="py-2">${r.invoice_no || '<span class="text-slate-400">—</span>'}</td>
                  <td class="py-2 text-right">${fmtNum(r.item_count)}</td>
                  <td class="py-2 text-right font-bold">${fmtMoney(r.total_cost)}</td>
              </tr>`;
        }).join('');
    }

    function renderItems(list) {
        const body = document.getElementById('items-body');
        const countEl = document.getElementById('items-count');
        countEl.textContent = (list.length || 0) + ' ürün';
        if (!list.length) {
            body.innerHTML = `<tr><td colspan="6" class="q-hint text-center py-6">Bu tedarikçiye bağlı ürün yok.</td></tr>`;
            return;
        }
        body.innerHTML = list.map(i => {
            const stock = Number(i.current_stock) || 0;
            const minT  = Number(i.min_threshold) || 0;
            let badge = '<span class="q-badge q-badge--success">Normal</span>';
            if (stock <= 0)                 badge = '<span class="q-badge q-badge--danger">Tükendi</span>';
            else if (minT > 0 && stock < minT) badge = '<span class="q-badge q-badge--warning">Düşük Stok</span>';
            return `
              <tr class="hover:bg-slate-50">
                  <td class="py-2 font-semibold">${i.name || '-'}</td>
                  <td class="py-2 text-right">${fmtNum(stock)}</td>
                  <td class="py-2 text-right">${i.unit || '-'}</td>
                  <td class="py-2 text-right">${fmtMoney(i.cost_per_unit)}</td>
                  <td class="py-2 text-right font-bold">${fmtMoney(i.stock_value)}</td>
                  <td class="py-2 pl-3">${badge}</td>
              </tr>`;
        }).join('');
    }

    function renderBalance(k, supplier) {
        const box = document.getElementById('balance-box');
        box.innerHTML = `
            <div class="q-toolbar q-card q-card--pad" style="background:var(--color-surface-muted);">
                <span class="q-hint">Güncel bakiye</span>
                <span class="font-black">${fmtMoney(k.current_balance)}</span>
            </div>
            <div class="q-toolbar q-card q-card--pad" style="background:var(--color-surface-muted);">
                <span class="q-hint">Ödenmemiş fatura</span>
                <span class="font-black" style="color:var(--color-danger);">${fmtMoney(k.unpaid_total)} <span class="q-hint">(${fmtNum(k.unpaid_count)})</span></span>
            </div>
            <div class="q-toolbar q-card q-card--pad" style="background:var(--color-surface-muted);">
                <span class="q-hint">Aralıkta alış</span>
                <span class="font-black">${fmtMoney(k.purchase_total)} <span class="q-hint">(${fmtNum(k.purchase_count)})</span></span>
            </div>
            <div class="q-toolbar q-card q-card--pad" style="background:var(--color-surface-muted);">
                <span class="q-hint">Aralıkta iade</span>
                <span class="font-black">${fmtMoney(k.return_cost)} <span class="q-hint">(${fmtNum(k.return_count)})</span></span>
            </div>
            <div class="q-toolbar q-card q-card--pad" style="background:var(--color-surface-muted);">
                <span class="q-hint">Aralıkta fire</span>
                <span class="font-black" style="color:var(--color-danger);">${fmtMoney(k.waste_cost)} <span class="q-hint">(${fmtNum(k.waste_count)})</span></span>
            </div>`;
    }

    async function load() {
        const range = document.getElementById('range-select').value || 'week';
        const qs = new URLSearchParams({ range });
        if (businessId) qs.set('business_id', businessId);
        const url = `${apiPrefix}/finance/suppliers/${encodeURIComponent(supplierId)}/analytics?${qs.toString()}`;
        try {
            const res = await fetch(url, { credentials: 'same-origin' });
            const json = await res.json();
            if (!json.success) throw new Error(json.error || 'Analitik alınamadı.');
            renderKpis(json.kpis || {});
            renderReceipts(json.receipts || []);
            renderItems(json.items || []);
            renderBalance(json.kpis || {}, json.supplier || {});
        } catch (e) {
            document.getElementById('kpi-grid').innerHTML = `
              <div class="col-span-full q-card q-card--pad" style="color:var(--color-danger);">
                Veri yüklenemedi: ${e.message || e}
              </div>`;
        }
    }

    document.getElementById('range-select').addEventListener('change', load);
    document.getElementById('refresh-btn').addEventListener('click', load);
    load();
})();
</script>
