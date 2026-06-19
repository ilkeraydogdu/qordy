<?php
/**
 * Low Stock Configuration (Phase 2).
 *
 * Per-ingredient panel where the business chooses:
 *   - low_stock_action  (NONE | NOTIFY_ONLY | NOTIFY_AND_DISABLE | DISABLE_ONLY)
 *   - notify_channels   (any of: in_app, email, whatsapp, push)
 *   - notify_recipients (extra emails/phones; optional — falls back to owners)
 *   - min_threshold     (inherited from existing stock config)
 *
 * Talks to {@see \App\Controllers\LowStockController}. Everything is
 * tenant-scoped via session; super-admin can pin a business via ?business_id=.
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
        <h1 class="q-page-header__title">Düşük Stok Uyarıları</h1>
        <p class="q-page-header__subtitle">Her ürün için eşik aksiyonunu ve bildirim kanallarını seçin.</p>
      </div>
      <div class="q-page-header__actions q-toolbar" style="gap:var(--space-2);">
        <div class="q-field" style="margin:0;">
          <input type="search" id="ls-search" placeholder="Ürün ara…" class="q-input"/>
        </div>
        <button type="button" id="ls-trigger" class="q-btn q-btn--primary">Şimdi Tetikle</button>
      </div>
    </header>

    <div class="q-card q-card--pad" style="padding:0;overflow:hidden;">
        <div class="overflow-x-auto">
            <table class="q-table w-full text-sm">
                <thead>
                    <tr>
                        <th class="px-4 py-3 text-left">Ürün</th>
                        <th class="px-4 py-3 text-right">Mevcut</th>
                        <th class="px-4 py-3 text-right">Min Eşik</th>
                        <th class="px-4 py-3 text-left">Aksiyon</th>
                        <th class="px-4 py-3 text-left">Kanallar</th>
                        <th class="px-4 py-3 text-left">Alıcılar</th>
                        <th class="px-4 py-3 text-left">Durum</th>
                    </tr>
                </thead>
                <tbody id="ls-rows"><tr><td colspan="7" class="py-10 text-center q-hint">Yükleniyor...</td></tr></tbody>
            </table>
        </div>
    </div>
  </div>
</div>

<script>
(function () {
    const apiPrefix = <?php echo json_encode($apiPrefix); ?>;
    const base      = <?php echo json_encode($baseUrl); ?>;
    const urlBusinessId = new URLSearchParams(window.location.search).get('business_id');
    const businessQuery = urlBusinessId ? ('?business_id=' + encodeURIComponent(urlBusinessId)) : '';

    const CHANNELS = [
        { id: 'in_app',   label: 'Uygulama' },
        { id: 'email',    label: 'E-posta' },
        { id: 'whatsapp', label: 'WhatsApp' },
        { id: 'push',     label: 'Push' },
    ];
    const ACTIONS = [
        { id: 'NOTIFY_ONLY',        label: 'Sadece bildirim' },
        { id: 'NOTIFY_AND_DISABLE', label: 'Bildirim + satışı kapat' },
        { id: 'DISABLE_ONLY',       label: 'Sadece satışı kapat' },
        { id: 'NONE',               label: 'Kapalı' },
    ];

    function esc(s) {
        return String(s ?? '').replace(/[&<>"']/g, c => ({
            '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
        }[c]));
    }

    async function j(url, opts) {
        const r = await fetch(url, Object.assign({
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' }
        }, opts || {}));
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.json();
    }

    let allRows = [];

    async function load() {
        const res = await j(base + apiPrefix + '/low-stock' + businessQuery);
        allRows = (res && res.data) || [];
        render(allRows);
    }

    function render(rows) {
        const tbody = document.getElementById('ls-rows');
        if (!rows.length) {
            tbody.innerHTML = `<tr><td colspan="7" class="py-10 text-center q-hint">Kayıt yok</td></tr>`;
            return;
        }

        tbody.innerHTML = rows.map(r => {
            const channels = (r.notify_channels || '').split(',').map(s => s.trim()).filter(Boolean);
            const recipients = (() => {
                try { const p = JSON.parse(r.notify_recipients || '[]'); return Array.isArray(p) ? p : []; }
                catch { return []; }
            })();
            const isDisabled = Number(r.is_available) === 0;
            return `
                <tr class="align-top" data-id="${esc(r.ingredient_id)}">
                    <td class="px-4 py-3">
                        <div class="font-bold">${esc(r.name)}</div>
                        <div class="q-hint text-xs">${esc(r.unit || '')}</div>
                    </td>
                    <td class="px-4 py-3 text-right font-bold">${Number(r.current_stock).toLocaleString('tr-TR')}</td>
                    <td class="px-4 py-3 text-right">
                        <input type="number" step="0.01" min="0" value="${Number(r.min_threshold)}"
                               class="ls-thr q-input w-24 text-right"/>
                    </td>
                    <td class="px-4 py-3">
                        <select class="ls-action q-input text-xs">
                            ${ACTIONS.map(a => `<option value="${a.id}" ${r.low_stock_action === a.id ? 'selected' : ''}>${esc(a.label)}</option>`).join('')}
                        </select>
                    </td>
                    <td class="px-4 py-3">
                        <div class="q-toolbar flex-wrap" style="gap:var(--space-1);">
                            ${CHANNELS.map(c => `
                                <label class="q-toolbar q-card q-card--pad text-xs font-bold cursor-pointer" style="padding:var(--space-1) var(--space-2);gap:var(--space-1);">
                                    <input type="checkbox" class="ls-ch shrink-0" value="${c.id}" ${channels.includes(c.id) ? 'checked' : ''}/>
                                    ${esc(c.label)}
                                </label>
                            `).join('')}
                        </div>
                    </td>
                    <td class="px-4 py-3">
                        <textarea rows="2" placeholder='[{"email":"...","phone":"..."}]'
                                  class="ls-rcp q-input text-[11px]">${recipients.length ? esc(JSON.stringify(recipients)) : ''}</textarea>
                    </td>
                    <td class="px-4 py-3">
                        <button type="button" class="ls-save q-btn q-btn--primary q-btn--sm">Kaydet</button>
                        ${isDisabled ? `<div class="mt-2 q-badge q-badge--danger">Satışa kapalı</div>` : ''}
                    </td>
                </tr>
            `;
        }).join('');

        tbody.querySelectorAll('tr[data-id]').forEach(tr => {
            const btn = tr.querySelector('.ls-save');
            btn.addEventListener('click', async () => {
                const id  = tr.dataset.id;
                const body = {
                    low_stock_action: tr.querySelector('.ls-action').value,
                    notify_channels: Array.from(tr.querySelectorAll('.ls-ch'))
                        .filter(c => c.checked).map(c => c.value).join(',') || 'in_app',
                    min_threshold: Number(tr.querySelector('.ls-thr').value || 0),
                };
                const raw = tr.querySelector('.ls-rcp').value.trim();
                if (raw) {
                    try { body.notify_recipients = JSON.parse(raw); }
                    catch { window.NotificationManager?.error('Alıcı listesi JSON formatında olmalı'); return; }
                } else {
                    body.notify_recipients = null;
                }
                btn.disabled = true;
                btn.textContent = '...';
                try {
                    await j(base + apiPrefix + '/low-stock/' + encodeURIComponent(id) + businessQuery, {
                        method: 'POST',
                        body: JSON.stringify(body),
                    });
                    window.NotificationManager?.success?.('Kaydedildi');
                } catch (e) {
                    window.NotificationManager?.error?.('Kaydedilemedi: ' + e.message);
                } finally {
                    btn.disabled = false;
                    btn.textContent = 'Kaydet';
                }
            });
        });
    }

    document.getElementById('ls-search').addEventListener('input', e => {
        const q = e.target.value.toLowerCase();
        render(allRows.filter(r => (r.name || '').toLowerCase().includes(q)));
    });

    document.getElementById('ls-trigger').addEventListener('click', async () => {
        try {
            const res = await j(base + apiPrefix + '/low-stock/trigger' + businessQuery, { method: 'POST' });
            const s = (res && res.data) || {};
            window.NotificationManager?.success?.(
                `Tamamlandı: taranan ${s.scanned ?? 0}, bildirim ${s.notified ?? 0}, kapatılan ${s.disabled ?? 0}`
            );
        } catch (e) {
            window.NotificationManager?.error?.('Tetiklenemedi: ' + e.message);
        }
    });

    load().catch(e => {
        document.getElementById('ls-rows').innerHTML =
            `<tr><td colspan="7" class="py-10 text-center text-red-500">Hata: ${esc(e.message)}</td></tr>`;
    });
})();
</script>
