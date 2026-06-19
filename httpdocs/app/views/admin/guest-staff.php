<?php
/**
 * Guest staff (yevmiyeci) — Warm Ember Ops (.q-* design system)
 */
$isSuperAdmin = $is_super_admin ?? false;
$apiPrefix = $isSuperAdmin ? '/api/qodmin' : '/api/business';
$baseUrl = BASE_URL;
?>
<div class="q-page q-biz-theme animate-slide-up">
  <div class="q-container">

    <header class="q-page-header">
      <div>
        <p class="q-page-header__eyebrow">Personel</p>
        <h1 class="q-page-header__title">Yevmiyeci / Geçici Personel</h1>
        <p class="q-page-header__subtitle">Vardiya listelerine eklenebilecek geçici personelleri yönetin.</p>
      </div>
      <div class="q-page-header__actions">
        <div class="q-field" style="margin:0;">
          <input type="search" id="gs-search" placeholder="Ara..." class="q-input" style="min-width:200px;"/>
        </div>
        <button type="button" id="gs-add" class="q-btn q-btn--primary q-btn--sm">+ Yeni</button>
      </div>
    </header>

    <section class="q-card q-card--pad" style="padding:0;overflow:hidden;">
      <div style="overflow-x:auto;">
        <table class="q-table">
          <thead>
            <tr>
              <th>Ad Soyad</th>
              <th>Telefon</th>
              <th>TC</th>
              <th style="text-align:right;">Yevmiye</th>
              <th></th>
            </tr>
          </thead>
          <tbody id="gs-rows"><tr><td colspan="5" class="q-empty" style="padding:var(--space-8);">Yükleniyor...</td></tr></tbody>
        </table>
      </div>
    </section>

  </div>
</div>

<div id="gs-modal" class="hidden" style="position:fixed;inset:0;z-index:50;background:rgba(0,0,0,.4);display:flex;align-items:center;justify-content:center;padding:var(--space-4);">
  <div class="q-card q-card--pad" style="width:100%;max-width:640px;max-height:90vh;overflow-y:auto;" onclick="event.stopPropagation()">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:var(--space-4);">
      <h3 class="q-card__title" id="gs-modal-title" style="margin:0;">Yeni Yevmiyeci</h3>
      <button type="button" id="gs-modal-close" class="q-btn q-btn--ghost q-btn--sm" aria-label="Kapat">&times;</button>
    </div>
    <form id="gs-form" class="q-grid q-grid--2">
      <input type="hidden" name="guest_staff_id"/>
      <div class="q-field">
        <label class="q-label">Ad *</label>
        <input name="first_name" required class="q-input"/>
      </div>
      <div class="q-field">
        <label class="q-label">Soyad</label>
        <input name="last_name" class="q-input"/>
      </div>
      <div class="q-field">
        <label class="q-label">Telefon</label>
        <input name="phone" class="q-input"/>
      </div>
      <div class="q-field">
        <label class="q-label">E-posta</label>
        <input type="email" name="email" class="q-input"/>
      </div>
      <div class="q-field">
        <label class="q-label">TC Kimlik</label>
        <input name="tc_no" maxlength="11" class="q-input"/>
      </div>
      <div class="q-field">
        <label class="q-label">Yevmiye (₺)</label>
        <input type="number" step="0.01" name="daily_rate" class="q-input"/>
      </div>
      <div class="q-field">
        <label class="q-label">Yaş</label>
        <input type="number" name="age" min="0" max="120" class="q-input"/>
      </div>
      <div class="q-field">
        <label class="q-label">Cinsiyet</label>
        <select name="gender" class="q-select">
          <option value="">—</option>
          <option value="M">Erkek</option>
          <option value="F">Kadın</option>
          <option value="O">Diğer</option>
        </select>
      </div>
      <div class="q-field">
        <label class="q-label">Boy (cm)</label>
        <input type="number" name="height_cm" min="0" max="250" class="q-input"/>
      </div>
      <div class="q-field">
        <label class="q-label">Kilo (kg)</label>
        <input type="number" name="weight_kg" min="0" max="300" class="q-input"/>
      </div>
      <div class="q-field" style="grid-column:1/-1;">
        <label class="q-label">Adres</label>
        <textarea name="address" rows="2" class="q-textarea"></textarea>
      </div>
      <div style="grid-column:1/-1;display:flex;justify-content:flex-end;gap:var(--space-2);margin-top:var(--space-2);">
        <button type="button" id="gs-cancel" class="q-btn q-btn--ghost">İptal</button>
        <button type="submit" class="q-btn q-btn--primary">Kaydet</button>
      </div>
    </form>
  </div>
</div>

<script>
(function () {
    const apiPrefix = <?php echo json_encode($apiPrefix); ?>;
    const base      = <?php echo json_encode($baseUrl); ?>;
    const urlBusinessId = new URLSearchParams(window.location.search).get('business_id');
    const businessQuery = urlBusinessId ? ('?business_id=' + encodeURIComponent(urlBusinessId)) : '';

    const modal = document.getElementById('gs-modal');
    const form  = document.getElementById('gs-form');
    const titleEl = document.getElementById('gs-modal-title');
    const rowCache = new Map();

    function esc(s) { return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
    function csrf() {
        return window.CSRF_TOKEN || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    }

    async function j(url, opts) {
        const o = Object.assign({ credentials: 'same-origin' }, opts || {});
        o.headers = Object.assign({
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrf(),
            'X-Requested-With': 'XMLHttpRequest'
        }, o.headers || {});
        const r = await fetch(url, o);
        const data = await r.json().catch(() => ({}));
        if (!r.ok) throw new Error(data.message || data.error || ('HTTP ' + r.status));
        return data;
    }

    async function load(q) {
        const sep = businessQuery ? '&' : '?';
        const url = base + apiPrefix + '/guest-staff' + businessQuery + (q ? (businessQuery ? sep : '?') + 'q=' + encodeURIComponent(q) : '');
        const res = await j(url);
        render((res && res.data) || []);
    }

    function render(rows) {
        const tbody = document.getElementById('gs-rows');
        rowCache.clear();
        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="5" class="q-empty" style="padding:var(--space-8);">Kayıt yok</td></tr>';
            return;
        }
        tbody.innerHTML = rows.map(r => {
            const id = String(r.guest_staff_id || '');
            rowCache.set(id, r);
            return `
            <tr>
                <td style="font-weight:800;">${esc(r.first_name || '')} ${esc(r.last_name || '')}</td>
                <td>${esc(r.phone || '—')}</td>
                <td>${esc(r.tc_no || '—')}</td>
                <td style="text-align:right;">${r.daily_rate ? Number(r.daily_rate).toLocaleString('tr-TR', {maximumFractionDigits: 2}) + ' ₺' : '—'}</td>
                <td style="text-align:right;white-space:nowrap;">
                    <button type="button" class="q-btn q-btn--ghost q-btn--sm gs-edit" data-id="${esc(id)}">Düzenle</button>
                    <button type="button" class="q-btn q-btn--danger q-btn--sm gs-del" data-id="${esc(id)}" style="margin-left:4px;">Sil</button>
                </td>
            </tr>`;
        }).join('');

        tbody.querySelectorAll('.gs-edit').forEach(b => {
            b.addEventListener('click', () => openModal(rowCache.get(b.dataset.id) || null));
        });
        tbody.querySelectorAll('.gs-del').forEach(b => {
            b.addEventListener('click', async () => {
                if (!confirm('Silmek istediğinize emin misiniz?')) return;
                await j(base + apiPrefix + '/guest-staff/' + encodeURIComponent(b.dataset.id) + businessQuery, { method: 'DELETE' });
                load();
            });
        });
    }

    function openModal(row) {
        form.reset();
        if (row && row.guest_staff_id) {
            titleEl.textContent = 'Yevmiyeci Düzenle';
            for (const k in row) {
                if (form.elements[k]) form.elements[k].value = row[k] ?? '';
            }
            form.elements['guest_staff_id'].value = row.guest_staff_id;
        } else {
            titleEl.textContent = 'Yeni Yevmiyeci';
        }
        modal.classList.remove('hidden');
        modal.style.display = 'flex';
    }
    function closeModal() {
        modal.classList.add('hidden');
        modal.style.display = '';
    }

    document.getElementById('gs-add').addEventListener('click', () => openModal(null));
    document.getElementById('gs-modal-close').addEventListener('click', closeModal);
    document.getElementById('gs-cancel').addEventListener('click', closeModal);
    document.getElementById('gs-search').addEventListener('input', e => load(e.target.value));

    form.addEventListener('submit', async e => {
        e.preventDefault();
        const fd = new FormData(form);
        const body = {};
        fd.forEach((v, k) => { body[k] = v; });
        const id = body.guest_staff_id;
        delete body.guest_staff_id;
        const url = id
            ? base + apiPrefix + '/guest-staff/' + encodeURIComponent(id) + businessQuery
            : base + apiPrefix + '/guest-staff' + businessQuery;
        try {
            await j(url, { method: 'POST', body: JSON.stringify(body) });
            closeModal();
            load();
        } catch (err) {
            window.NotificationManager?.error('Kaydedilemedi: ' + err.message) || alert('Kaydedilemedi: ' + err.message);
        }
    });

    load();
})();
</script>
