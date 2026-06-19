<?php
/**
 * Supplier performance dashboard (Phase 2).
 *
 * Combines the leaderboard (worst → best waste ratio), a waste-cost trend
 * chart over the selected date range, and a drill-down detail panel for a
 * single supplier. All data is fetched via JSON endpoints exposed by
 * {@see \App\Controllers\SupplierPerformanceController}.
 */
$isSuperAdmin = $is_super_admin ?? false;
$apiPrefix = $isSuperAdmin ? '/api/qodmin' : '/api/business';
$baseUrl = BASE_URL;
?>
<div class="q-page q-biz-theme animate-slide-up">
  <div class="q-container q-stack q-stack--lg">
    <header class="q-page-header">
        <div>
            <p class="q-page-header__eyebrow">Stok</p>
            <h1 class="q-page-header__title">Tedarikçi Performansı</h1>
            <p class="q-page-header__subtitle">Satın alınan / fireye giden miktarlara göre tedarikçi karnesi</p>
        </div>
        <form id="sp-filters" class="q-page-header__actions q-toolbar" style="flex-wrap:wrap;">
            <div class="q-field" style="margin:0;">
                <label class="q-label">Başlangıç</label>
                <input type="date" name="start" id="sp-start"
                       value="<?php echo date('Y-m-d', strtotime('-90 days')); ?>"
                       class="q-input"/>
            </div>
            <div class="q-field" style="margin:0;">
                <label class="q-label">Bitiş</label>
                <input type="date" name="end" id="sp-end"
                       value="<?php echo date('Y-m-d'); ?>"
                       class="q-input"/>
            </div>
            <button type="submit" class="q-btn q-btn--primary q-btn--sm">Uygula</button>
        </form>
    </header>

    <div id="sp-kpis" class="q-grid q-grid--4"></div>

    <div class="q-grid q-grid--sidebar">
        <div class="q-card" style="padding:0;overflow:hidden;">
            <div class="q-card q-card--pad q-toolbar" style="border:0;border-bottom:1px solid var(--color-border-1);border-radius:0;">
                <h3 class="q-card__title" style="margin:0;">Tedarikçi Listesi</h3>
                <span class="q-hint">Yüksek fire oranına göre sıralı</span>
            </div>
            <div class="overflow-x-auto">
                <table class="q-table w-full text-sm">
                    <thead>
                        <tr>
                            <th>Tedarikçi</th>
                            <th style="text-align:right;">Alım</th>
                            <th style="text-align:right;">Fire</th>
                            <th style="text-align:right;">Fire Oranı</th>
                            <th style="text-align:right;">Fire Maliyeti</th>
                        </tr>
                    </thead>
                    <tbody id="sp-rows"></tbody>
                </table>
            </div>
        </div>

        <div class="q-card q-card--pad" id="sp-detail">
            <h3 class="q-card__title">Detay</h3>
            <p class="q-hint">Bir tedarikçi seçin.</p>
        </div>
    </div>
  </div>
</div>

<script>
(function () {
    const apiPrefix = <?php echo json_encode($apiPrefix); ?>;
    const base      = <?php echo json_encode($baseUrl); ?>;

    const urlBusinessId = new URLSearchParams(window.location.search).get('business_id');
    const businessQuery = urlBusinessId ? ('&business_id=' + encodeURIComponent(urlBusinessId)) : '';

    async function j(url) {
        const r = await fetch(url, { headers: { 'Accept': 'application/json' } });
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.json();
    }

    function money(n) {
        const v = Number(n || 0);
        return v.toLocaleString('tr-TR', { maximumFractionDigits: 2 }) + ' ₺';
    }
    function num(n) {
        const v = Number(n || 0);
        return v.toLocaleString('tr-TR', { maximumFractionDigits: 3 });
    }
    function esc(s) {
        return String(s ?? '').replace(/[&<>"']/g, c => ({
            '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
        }[c]));
    }

    async function loadLeaderboard() {
        const start = document.getElementById('sp-start').value;
        const end   = document.getElementById('sp-end').value;
        const url = `${base}${apiPrefix}/supplier-performance/leaderboard?start=${start}&end=${end}${businessQuery}`;
        const res = await j(url);
        const rows = (res && res.data) || [];
        renderKpis(rows);
        renderRows(rows);
    }

    function renderKpis(rows) {
        const totals = rows.reduce((acc, r) => {
            acc.purchased += Number(r.purchased_cost || 0);
            acc.wasted    += Number(r.wasted_cost || 0);
            acc.qty       += Number(r.wasted_qty || 0);
            return acc;
        }, { purchased: 0, wasted: 0, qty: 0 });
        const ratio = totals.purchased > 0
            ? ((totals.wasted / totals.purchased) * 100).toFixed(2)
            : '0.00';

        const k = [
            { label: 'Toplam Alım Maliyeti',  value: money(totals.purchased), tone: '' },
            { label: 'Toplam Fire Maliyeti',  value: money(totals.wasted),    tone: 'danger' },
            { label: 'Fire Miktarı (toplam)', value: num(totals.qty),         tone: 'warning' },
            { label: 'Ortalama Fire Oranı',   value: ratio + '%',             tone: 'info' },
        ];

        document.getElementById('sp-kpis').innerHTML = k.map(x => `
            <div class="q-card q-card--pad">
                <div class="q-hint">${esc(x.label)}</div>
                <div class="font-black text-2xl mt-1" style="color:var(--color-text-primary);">${esc(x.value)}</div>
            </div>
        `).join('');
    }

    function renderRows(rows) {
        const tbody = document.getElementById('sp-rows');
        if (!rows.length) {
            tbody.innerHTML = `<tr><td colspan="5" class="q-hint text-center py-10">Veri yok</td></tr>`;
            return;
        }
        tbody.innerHTML = rows.map(r => {
            const pct = Number(r.waste_ratio_pct || 0);
            const badge = pct >= 10 ? 'q-badge--danger'
                        : pct >= 5  ? 'q-badge--warning'
                        : 'q-badge--success';
            return `
                <tr class="cursor-pointer" data-sid="${esc(r.supplier_id)}" data-name="${esc(r.supplier_name)}">
                    <td class="font-black">${esc(r.supplier_name || '—')}</td>
                    <td style="text-align:right;">${num(r.purchased_qty)}</td>
                    <td style="text-align:right;font-weight:700;color:var(--color-danger);">${num(r.wasted_qty)}</td>
                    <td style="text-align:right;">
                        <span class="q-badge ${badge}">${pct.toFixed(2)}%</span>
                    </td>
                    <td style="text-align:right;font-weight:700;color:var(--color-danger);">${money(r.wasted_cost)}</td>
                </tr>
            `;
        }).join('');

        tbody.querySelectorAll('tr[data-sid]').forEach(tr => {
            tr.addEventListener('click', () => loadDetail(tr.dataset.sid, tr.dataset.name));
        });
    }

    async function loadDetail(supplierId, supplierName) {
        const box = document.getElementById('sp-detail');
        box.innerHTML = `<div class="q-spinner" style="margin:2rem auto;"></div>`;
        const start = document.getElementById('sp-start').value;
        const end   = document.getElementById('sp-end').value;
        try {
            const res = await j(`${base}${apiPrefix}/supplier-performance/${encodeURIComponent(supplierId)}?start=${start}&end=${end}${businessQuery}`);
            const data = (res && res.data) || {};
            const items = data.items || [];
            const totals = data.totals || {};
            const rows = items.map(i => `
                <tr>
                    <td class="font-bold">${esc(i.ingredient_name || '—')}</td>
                    <td style="text-align:right;">${num(i.purchased_qty)} ${esc(i.unit || '')}</td>
                    <td style="text-align:right;color:var(--color-danger);">${num(i.wasted_qty)} ${esc(i.unit || '')}</td>
                    <td style="text-align:right;font-weight:700;">${Number(i.waste_ratio_pct || 0).toFixed(2)}%</td>
                </tr>
            `).join('') || `<tr><td colspan="4" class="q-hint text-center py-6">Bu aralıkta veri yok</td></tr>`;

            box.innerHTML = `
                <div class="q-toolbar" style="align-items:flex-start;margin-bottom:1rem;">
                    <div>
                        <h3 class="q-card__title" style="margin:0;">${esc(supplierName || '—')}</h3>
                        <p class="q-hint">${esc(start)} → ${esc(end)}</p>
                    </div>
                </div>
                <div class="q-grid q-grid--2" style="margin-bottom:1rem;">
                    <div class="q-card q-card--pad" style="background:var(--color-surface-muted);">
                        <div class="q-hint">Alım</div>
                        <div class="font-black">${money(totals.purchased_cost)}</div>
                    </div>
                    <div class="q-card q-card--pad" style="background:var(--color-surface-muted);">
                        <div class="q-hint">Fire</div>
                        <div class="font-black" style="color:var(--color-danger);">${money(totals.wasted_cost)}</div>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="q-table w-full text-xs">
                        <thead>
                            <tr>
                                <th>Ürün</th>
                                <th style="text-align:right;">Alım</th>
                                <th style="text-align:right;">Fire</th>
                                <th style="text-align:right;">%</th>
                            </tr>
                        </thead>
                        <tbody>${rows}</tbody>
                    </table>
                </div>
            `;
        } catch (e) {
            box.innerHTML = `<div class="q-hint text-center py-10" style="color:var(--color-danger);">Hata: ${esc(e.message)}</div>`;
        }
    }

    document.getElementById('sp-filters').addEventListener('submit', e => {
        e.preventDefault();
        loadLeaderboard();
    });

    loadLeaderboard();
})();
</script>
