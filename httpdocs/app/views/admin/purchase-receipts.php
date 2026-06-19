<?php
/**
 * Purchase receipts (irsaliye) admin page.
 *
 * Left column: receipts list (click a row to see its line items).
 * Right column: "new receipt" form with a dynamic items table.
 *
 * All data flows through /api/business/purchases — no server-side filters
 * are needed because the BaseRepository tenant filter scopes everything.
 */
$baseUrl = defined('BASE_URL') && !empty(BASE_URL) ? BASE_URL : '';
if (empty($baseUrl)) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $baseUrl = $protocol . '://' . $host;
}
require_once __DIR__ . '/../../helpers/toast.php';
$isSuperAdmin = $is_super_admin ?? false;
$apiPrefix = $isSuperAdmin ? '/api/qodmin' : '/api/business';
?>
<?php echo getToastScript(); ?>
<div class="q-page q-biz-theme animate-slide-up">
  <div class="q-container q-stack q-stack--lg">
    <header class="q-page-header">
      <div>
        <p class="q-page-header__eyebrow">Stok</p>
        <h1 class="q-page-header__title">İrsaliye / Alım Fişleri</h1>
        <p class="q-page-header__subtitle">Tedarikçiden gelen alımlar, parti numaralı lot takibi ile kaydedilir.</p>
      </div>
    </header>

    <div class="q-grid q-grid--sidebar">
        <section class="q-card q-card--pad max-h-[75vh] overflow-y-auto">
            <div class="flex items-center justify-between mb-3">
                <h2 class="q-card__title text-xs uppercase tracking-wider">İrsaliyeler</h2>
                <button type="button" onclick="refreshList()" class="q-btn q-btn--ghost q-btn--sm">Yenile</button>
            </div>
            <div id="receipt-list" class="space-y-2 text-sm">
                <div class="text-slate-400 italic">Yükleniyor…</div>
            </div>
        </section>

        <section class="q-card q-card--pad lg:col-span-2">
            <h2 class="q-card__title text-xs uppercase tracking-wider mb-3">Yeni İrsaliye</h2>
            <form id="receipt-form" class="space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label class="text-[10px] font-black uppercase tracking-wider text-slate-500">Tedarikçi</label>
                        <select id="r-supplier" class="w-full mt-1 px-3 py-2 rounded-lg border border-slate-200 text-sm" required>
                            <option value="">Seçin…</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-[10px] font-black uppercase tracking-wider text-slate-500">Fatura / İrsaliye No</label>
                        <input id="r-invoice" class="w-full mt-1 px-3 py-2 rounded-lg border border-slate-200 text-sm" />
                    </div>
                    <div>
                        <label class="text-[10px] font-black uppercase tracking-wider text-slate-500">Tarih</label>
                        <input id="r-date" type="datetime-local" class="w-full mt-1 px-3 py-2 rounded-lg border border-slate-200 text-sm" />
                    </div>
                </div>
                <div>
                    <label class="text-[10px] font-black uppercase tracking-wider text-slate-500">Notlar</label>
                    <textarea id="r-notes" rows="2" class="w-full mt-1 px-3 py-2 rounded-lg border border-slate-200 text-sm"></textarea>
                </div>

                <div class="border-t border-slate-100 pt-3">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-[10px] font-black uppercase tracking-wider text-slate-500">Satırlar</span>
                        <button type="button" onclick="addRow()" class="text-xs font-black text-indigo-600">+ satır</button>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="text-[10px] font-black uppercase text-slate-400">
                                <tr>
                                    <th class="text-left py-1">Malzeme</th>
                                    <th class="text-right py-1">Miktar</th>
                                    <th class="text-left py-1">Birim</th>
                                    <th class="text-right py-1">Birim Fiyat</th>
                                    <th class="text-left py-1">Parti No</th>
                                    <th class="text-left py-1">SKT</th>
                                    <th class="text-right py-1">Tutar</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="r-items"></tbody>
                            <tfoot>
                                <tr class="font-black">
                                    <td colspan="6" class="text-right py-2 text-slate-500">Toplam</td>
                                    <td class="text-right py-2" id="r-total">0,00</td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <div class="flex justify-end gap-2">
                    <button type="reset" onclick="resetForm()" class="q-btn q-btn--secondary q-btn--sm">Temizle</button>
                    <button type="submit" class="q-btn q-btn--primary q-btn--sm">Kaydet</button>
                </div>
            </form>

            <div id="receipt-detail" class="mt-6 hidden border-t border-slate-100 pt-4">
                <h3 class="q-card__title text-xs uppercase tracking-wider mb-2">Seçili İrsaliye</h3>
                <div id="receipt-detail-body" class="text-sm"></div>
            </div>
        </section>
    </div>
  </div>
</div>

<script>
(function() {
    const baseUrl = <?php echo json_encode($baseUrl, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const apiPrefix = <?php echo json_encode($apiPrefix, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

    let ingredients = [];
    let suppliers = [];

    async function api(method, path, body) {
        const opts = { method, headers: { 'Accept': 'application/json' }, credentials: 'same-origin' };
        if (body !== undefined) {
            opts.headers['Content-Type'] = 'application/json';
            opts.body = JSON.stringify(body);
        }
        const res = await fetch(baseUrl + apiPrefix + path, opts);
        const data = await res.json().catch(() => ({}));
        if (!res.ok) throw new Error((data && data.error) || ('HTTP ' + res.status));
        return data;
    }
    async function apiRaw(path) {
        const res = await fetch(baseUrl + path, { credentials: 'same-origin' });
        return res.ok ? res.json() : Promise.reject(new Error('HTTP ' + res.status));
    }

    function escapeHtml(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    }
    function fmtMoney(v) { return (Number(v) || 0).toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }

    function recomputeTotal() {
        let total = 0;
        document.querySelectorAll('#r-items tr').forEach(tr => {
            const qty = parseFloat(tr.querySelector('.ri-qty').value || '0');
            const uc = parseFloat(tr.querySelector('.ri-cost').value || '0');
            const line = qty * uc;
            tr.querySelector('.ri-line').textContent = fmtMoney(line);
            total += line;
        });
        document.getElementById('r-total').textContent = fmtMoney(total);
    }

    window.addRow = function() {
        const tr = document.createElement('tr');
        tr.className = 'border-t border-slate-50';
        tr.innerHTML = `
            <td class="py-1 pr-2">
                <select class="ri-ing w-full px-2 py-1.5 rounded border border-slate-200 text-xs"></select>
            </td>
            <td class="py-1 pr-2"><input type="number" step="0.001" min="0" class="ri-qty w-24 px-2 py-1.5 rounded border border-slate-200 text-xs text-right" /></td>
            <td class="py-1 pr-2"><input class="ri-unit w-16 px-2 py-1.5 rounded border border-slate-200 text-xs" placeholder="kg" /></td>
            <td class="py-1 pr-2"><input type="number" step="0.0001" min="0" class="ri-cost w-24 px-2 py-1.5 rounded border border-slate-200 text-xs text-right" /></td>
            <td class="py-1 pr-2"><input class="ri-batch w-24 px-2 py-1.5 rounded border border-slate-200 text-xs" /></td>
            <td class="py-1 pr-2"><input type="date" class="ri-exp w-32 px-2 py-1.5 rounded border border-slate-200 text-xs" /></td>
            <td class="py-1 pr-2 text-right ri-line">0,00</td>
            <td class="py-1 text-right"><button type="button" class="text-xs text-slate-400 hover:text-red-600" onclick="this.closest('tr').remove();recomputeTotal()">×</button></td>
        `;
        document.getElementById('r-items').appendChild(tr);
        const sel = tr.querySelector('.ri-ing');
        sel.innerHTML = '<option value="">Seçin…</option>' + ingredients.map(i =>
            `<option value="${escapeHtml(i.ingredient_id)}" data-unit="${escapeHtml(i.unit || '')}" data-cost="${i.unit_cost || 0}">${escapeHtml(i.name)}</option>`
        ).join('');
        sel.addEventListener('change', () => {
            const opt = sel.selectedOptions[0];
            if (opt && opt.dataset.unit) tr.querySelector('.ri-unit').value = opt.dataset.unit;
            if (opt && opt.dataset.cost && !tr.querySelector('.ri-cost').value) tr.querySelector('.ri-cost').value = opt.dataset.cost;
        });
        tr.querySelectorAll('.ri-qty, .ri-cost').forEach(el => el.addEventListener('input', recomputeTotal));
    };

    window.resetForm = function() {
        document.getElementById('receipt-form').reset();
        document.getElementById('r-items').innerHTML = '';
        addRow();
        recomputeTotal();
    };

    async function loadSuppliers() {
        try {
            const res = await apiRaw(apiPrefix + '/suppliers').catch(() => apiRaw('/api/business/finance/suppliers'));
            const arr = Array.isArray(res.data) ? res.data : (Array.isArray(res) ? res : []);
            suppliers = arr;
            const sel = document.getElementById('r-supplier');
            sel.innerHTML = '<option value="">Seçin…</option>' +
                arr.map(s => `<option value="${escapeHtml(s.supplier_id)}">${escapeHtml(s.name || s.supplier_name || '-')}</option>`).join('');
        } catch (e) {
            console.warn('Suppliers load failed', e);
        }
    }
    async function loadIngredients() {
        try {
            const res = await apiRaw(apiPrefix + '/ingredients').catch(() => ({ data: [] }));
            ingredients = Array.isArray(res.data) ? res.data : (Array.isArray(res) ? res : []);
        } catch (e) { ingredients = []; }
    }

    window.refreshList = async function() {
        try {
            const res = await api('GET', '/purchases');
            const rows = res.data || [];
            const box = document.getElementById('receipt-list');
            if (!rows.length) {
                box.innerHTML = '<div class="text-slate-400 italic">Henüz irsaliye yok.</div>';
                return;
            }
            box.innerHTML = rows.map(r => `
                <button type="button" onclick="openReceipt('${r.receipt_id}')" class="w-full text-left px-3 py-2 rounded-lg hover:bg-slate-50 border border-slate-100">
                    <div class="font-black text-slate-900">${escapeHtml(r.supplier_name || 'Bilinmiyor')}</div>
                    <div class="text-xs text-slate-500 flex justify-between">
                        <span>${escapeHtml(r.invoice_no || '-')}</span>
                        <span>${escapeHtml((r.received_at || '').substring(0,10))}</span>
                    </div>
                    <div class="text-xs font-bold text-indigo-600">${fmtMoney(r.total_cost)} ₺</div>
                </button>
            `).join('');
        } catch (e) {
            showToast('Liste alınamadı: ' + e.message, 'error');
        }
    };

    window.openReceipt = async function(id) {
        try {
            const res = await api('GET', '/purchases/' + encodeURIComponent(id));
            const r = res.data;
            const items = r.items || [];
            const rows = items.map(i => `
                <tr class="border-t border-slate-50">
                    <td class="py-1">${escapeHtml(i.ingredient_name || i.ingredient_id)}</td>
                    <td class="py-1 text-right">${Number(i.qty).toLocaleString('tr-TR')} ${escapeHtml(i.unit || '')}</td>
                    <td class="py-1 text-right">${fmtMoney(i.unit_cost)} ₺</td>
                    <td class="py-1 text-right">${fmtMoney(i.line_total)} ₺</td>
                    <td class="py-1 text-right">${Number(i.qty_remaining).toLocaleString('tr-TR')}</td>
                    <td class="py-1">${escapeHtml(i.batch_no || '-')}</td>
                </tr>
            `).join('');
            document.getElementById('receipt-detail-body').innerHTML = `
                <div class="mb-2 text-xs text-slate-500">${escapeHtml(r.received_at || '')} • Fatura: ${escapeHtml(r.invoice_no || '-')}</div>
                <table class="w-full text-sm">
                    <thead class="text-[10px] font-black uppercase text-slate-400">
                        <tr><th class="text-left py-1">Malzeme</th><th class="text-right py-1">Miktar</th><th class="text-right py-1">Birim Fiyat</th><th class="text-right py-1">Tutar</th><th class="text-right py-1">Kalan</th><th class="text-left py-1">Parti</th></tr>
                    </thead>
                    <tbody>${rows}</tbody>
                </table>
                <div class="mt-2 text-right text-sm font-black">Toplam: ${fmtMoney(r.total_cost)} ₺</div>
                ${r.notes ? '<div class="mt-2 text-xs text-slate-500 whitespace-pre-wrap">' + escapeHtml(r.notes) + '</div>' : ''}
            `;
            document.getElementById('receipt-detail').classList.remove('hidden');
        } catch (e) {
            showToast('İrsaliye okunamadı: ' + e.message, 'error');
        }
    };

    document.getElementById('receipt-form').addEventListener('submit', async (ev) => {
        ev.preventDefault();
        const items = [];
        document.querySelectorAll('#r-items tr').forEach(tr => {
            const ing = tr.querySelector('.ri-ing').value;
            const qty = parseFloat(tr.querySelector('.ri-qty').value || '0');
            if (!ing || !(qty > 0)) return;
            items.push({
                ingredient_id: ing,
                qty,
                unit: tr.querySelector('.ri-unit').value,
                unit_cost: parseFloat(tr.querySelector('.ri-cost').value || '0'),
                batch_no: tr.querySelector('.ri-batch').value || null,
                expiry_date: tr.querySelector('.ri-exp').value || null,
            });
        });
        if (!items.length) { showToast('En az bir satır girin', 'error'); return; }
        const payload = {
            supplier_id: document.getElementById('r-supplier').value,
            invoice_no:  document.getElementById('r-invoice').value,
            received_at: document.getElementById('r-date').value ? document.getElementById('r-date').value.replace('T', ' ') + ':00' : null,
            notes:       document.getElementById('r-notes').value,
            items,
        };
        try {
            await api('POST', '/purchases', payload);
            showToast('İrsaliye kaydedildi', 'success');
            resetForm();
            refreshList();
        } catch (e) {
            showToast(e.message, 'error');
        }
    });

    (async () => {
        await Promise.all([loadSuppliers(), loadIngredients()]);
        addRow();
        refreshList();
    })();
})();
</script>
