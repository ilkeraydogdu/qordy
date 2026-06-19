<?php
/**
 * HR — İzin yönetimi (.q-* design system)
 */
$isSuperAdmin = $is_super_admin ?? false;
$apiPrefix = $isSuperAdmin ? '/api/qodmin' : '/api/business';
$baseUrl = BASE_URL;
$adminPrefix = $isSuperAdmin ? '/qodmin' : '/business';
$staffMembers = $staff_members ?? [];
$leaveTypes = $leave_types ?? [];
?>
<div class="q-page q-biz-theme animate-slide-up">
  <div class="q-container">

    <header class="q-page-header">
      <div>
        <p class="q-page-header__eyebrow">Personel</p>
        <h1 class="q-page-header__title">İzin Yönetimi</h1>
        <p class="q-page-header__subtitle">Talep oluşturun, onaylayın, personel bazlı rapor alın.</p>
      </div>
      <div class="q-page-header__actions">
        <button type="button" id="btn-open-leave-form" class="q-btn q-btn--primary q-btn--sm">+ İzin ekle</button>
      </div>
    </header>

    <section class="q-card q-card--pad q-stack" style="margin-bottom:var(--space-5);">
      <h2 class="q-section-title">Personel / dönem raporu</h2>
      <div style="display:flex;flex-wrap:wrap;gap:var(--space-3);align-items:flex-end;">
        <div class="q-field" style="margin:0;">
          <label class="q-label" for="rep-user">Personel</label>
          <select id="rep-user" class="q-select" style="min-width:200px;">
            <option value="">(Tümü)</option>
            <?php foreach ($staffMembers as $s): ?>
              <option value="<?php echo htmlspecialchars($s['user_id'] ?? ''); ?>"><?php echo htmlspecialchars($s['name'] ?? ''); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="q-field" style="margin:0;">
          <label class="q-label" for="rep-start">Başlangıç</label>
          <input type="date" id="rep-start" class="q-input" value="<?php echo date('Y-01-01'); ?>">
        </div>
        <div class="q-field" style="margin:0;">
          <label class="q-label" for="rep-end">Bitiş</label>
          <input type="date" id="rep-end" class="q-input" value="<?php echo date('Y-12-31'); ?>">
        </div>
        <button type="button" id="btn-report" class="q-btn q-btn--secondary q-btn--sm">Raporu getir</button>
      </div>
      <div id="rep-summary" class="q-grid q-grid--3 hidden" style="margin-top:var(--space-4);">
        <div class="q-stat">
          <div class="q-stat__top"><span class="q-stat__label">Toplam gün</span></div>
          <div class="q-stat__value" id="rep-total-days">0</div>
        </div>
        <div class="q-stat">
          <div class="q-stat__top"><span class="q-stat__label">Kayıt</span></div>
          <div class="q-stat__value" id="rep-count">0</div>
        </div>
        <div class="q-stat">
          <div class="q-stat__top"><span class="q-stat__label">Türe göre gün</span></div>
          <div class="q-hint" id="rep-by-type" style="margin-top:var(--space-2);">—</div>
        </div>
      </div>
    </section>

    <section class="q-card q-card--pad" style="padding:0;overflow:hidden;">
      <div style="display:flex;flex-wrap:wrap;justify-content:space-between;align-items:center;gap:var(--space-3);padding:var(--space-4) var(--space-5);border-bottom:1px solid var(--color-border-subtle);">
        <h2 class="q-card__title" style="margin:0;">İzin listesi</h2>
        <select id="leave-status" class="q-select w-full sm:w-auto min-w-0 sm:min-w-[160px]">
          <option value="PENDING">Beklemede</option>
          <option value="APPROVED">Onaylı</option>
          <option value="REJECTED">Reddedilmiş</option>
          <option value="">Tümü</option>
        </select>
      </div>
      <div style="overflow-x:auto;">
        <table class="q-table">
          <thead>
            <tr>
              <th>Personel</th>
              <th>Tür</th>
              <th>Başlangıç</th>
              <th>Bitiş</th>
              <th style="text-align:right;">Gün</th>
              <th>Durum</th>
              <th style="text-align:right;">İşlem</th>
            </tr>
          </thead>
          <tbody id="leave-rows">
            <tr><td colspan="7" class="q-empty" style="padding:var(--space-8);">Yükleniyor...</td></tr>
          </tbody>
        </table>
      </div>
    </section>

  </div>
</div>

<div id="leave-modal" class="hidden" style="position:fixed;inset:0;z-index:50;background:rgba(0,0,0,.5);display:flex;align-items:center;justify-content:center;padding:var(--space-4);">
  <div class="q-card q-card--pad q-stack" style="width:100%;max-height:90vh;overflow-y:auto;" onclick="event.stopPropagation()">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:var(--space-2);">
      <h3 class="q-card__title" id="leave-modal-title" style="margin:0;">İzin ekle</h3>
      <button type="button" class="q-btn q-btn--ghost q-btn--sm" data-close-leave-modal aria-label="Kapat">&times;</button>
    </div>
    <form id="leave-form" class="q-stack">
      <input type="hidden" name="editing_id" id="leave-editing-id" value="">
      <div class="q-field">
        <label class="q-label" for="lf-user">Personel *</label>
        <select name="user_id" id="lf-user" required class="q-select">
          <option value="">Seçin</option>
          <?php foreach ($staffMembers as $s): ?>
            <option value="<?php echo htmlspecialchars($s['user_id'] ?? ''); ?>"><?php echo htmlspecialchars($s['name'] ?? ''); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="q-field">
        <label class="q-label" for="lf-type">İzin türü *</label>
        <select name="leave_type_id" id="lf-type" required class="q-select">
          <option value="">Seçin</option>
          <?php foreach ($leaveTypes as $t): ?>
            <option value="<?php echo htmlspecialchars($t['leave_type_id'] ?? ''); ?>"><?php echo htmlspecialchars($t['type_name'] ?? $t['type_code'] ?? ''); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="q-grid q-grid--2">
        <div class="q-field">
          <label class="q-label" for="lf-start">Başlangıç *</label>
          <input type="date" name="start_date" id="lf-start" required class="q-input">
        </div>
        <div class="q-field">
          <label class="q-label" for="lf-end">Bitiş *</label>
          <input type="date" name="end_date" id="lf-end" required class="q-input">
        </div>
      </div>
      <div class="q-field" id="lf-initial-status-wrap">
        <label class="q-label" for="lf-status">Oluşturma</label>
        <select name="status" id="lf-status" class="q-select">
          <option value="PENDING">Talep (onay bekler)</option>
          <option value="APPROVED">Direkt onaylı</option>
        </select>
      </div>
      <div class="q-field">
        <label class="q-label" for="lf-reason">Gerekçe</label>
        <textarea name="reason" id="lf-reason" rows="2" class="q-textarea"></textarea>
      </div>
      <div class="q-field">
        <label class="q-label" for="lf-notes">Not</label>
        <textarea name="notes" id="lf-notes" rows="2" class="q-textarea"></textarea>
      </div>
      <div style="display:flex;gap:var(--space-2);justify-content:flex-end;">
        <button type="button" class="q-btn q-btn--ghost" data-close-leave-modal>İptal</button>
        <button type="submit" class="q-btn q-btn--primary">Kaydet</button>
      </div>
    </form>
  </div>
</div>

<script>
(function () {
    const apiPrefix = <?php echo json_encode($apiPrefix); ?>;
    const base = <?php echo json_encode($baseUrl); ?>;
    const urlBusinessId = new URLSearchParams(window.location.search).get('business_id');

    function businessQuery() {
        const p = new URLSearchParams();
        if (urlBusinessId) p.set('business_id', urlBusinessId);
        const s = p.toString();
        return s ? '?' + s : '';
    }

    function csrf() {
        return window.CSRF_TOKEN || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    }

    function esc(s) { return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

    async function j(url, opts) {
        const o = Object.assign({ credentials: 'same-origin' }, opts || {});
        o.headers = Object.assign({
            'Accept': 'application/json',
            'X-CSRF-Token': csrf(),
            'X-Requested-With': 'XMLHttpRequest'
        }, o.headers || {});
        if (o.body && typeof o.body === 'string' && !o.headers['Content-Type']) {
            o.headers['Content-Type'] = 'application/json';
        }
        const r = await fetch(url, o);
        const data = await r.json().catch(() => ({}));
        if (!r.ok) throw new Error(data.message || data.error || ('HTTP ' + r.status));
        return data;
    }

    const modal = document.getElementById('leave-modal');
    const form = document.getElementById('leave-form');

    function openModal(isEdit) {
        document.getElementById('leave-modal-title').textContent = isEdit ? 'İzni düzenle' : 'İzin ekle';
        document.getElementById('lf-initial-status-wrap').style.display = isEdit ? 'none' : 'block';
        if (!isEdit) {
            form.reset();
            document.getElementById('leave-editing-id').value = '';
        }
        modal.classList.remove('hidden');
        modal.style.display = 'flex';
    }
    function closeModal() {
        modal.classList.add('hidden');
        modal.style.display = '';
    }
    document.querySelectorAll('[data-close-leave-modal]').forEach(b => b.addEventListener('click', closeModal));
    modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });
    document.getElementById('btn-open-leave-form')?.addEventListener('click', () => openModal(false));

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const id = document.getElementById('leave-editing-id').value;
        const payload = {
            user_id: document.getElementById('lf-user').value,
            leave_type_id: document.getElementById('lf-type').value,
            start_date: document.getElementById('lf-start').value,
            end_date: document.getElementById('lf-end').value,
            reason: document.getElementById('lf-reason').value,
            notes: document.getElementById('lf-notes').value
        };
        try {
            if (id) {
                await j(base + apiPrefix + '/leaves/' + encodeURIComponent(id) + '/update' + businessQuery(), {
                    method: 'POST',
                    body: JSON.stringify(payload)
                });
            } else {
                payload.status = document.getElementById('lf-status').value;
                await j(base + apiPrefix + '/leaves/managed' + businessQuery(), {
                    method: 'POST',
                    body: JSON.stringify(payload)
                });
            }
            if (window.NotificationManager) window.NotificationManager.success('Kayıt tamam');
            closeModal();
            load();
        } catch (err) {
            if (window.NotificationManager) window.NotificationManager.error(err.message || 'Hata');
        }
    });

    function badge(s) {
        const st = String(s || '').toUpperCase();
        const map = { 'PENDING': 'q-badge--warning', 'APPROVED': 'q-badge--success', 'REJECTED': 'q-badge--danger' };
        const cls = map[st] || 'q-badge--neutral';
        return '<span class="q-badge ' + cls + '">' + esc(st) + '</span>';
    }

    async function load() {
        const status = document.getElementById('leave-status').value;
        const p = new URLSearchParams();
        if (status) p.set('status', status);
        if (urlBusinessId) p.set('business_id', urlBusinessId);
        const qs = p.toString();
        const res = await j(base + apiPrefix + '/leaves' + (qs ? '?' + qs : ''), { method: 'GET' });
        render((res && res.data) || []);
    }

    async function editRow(id) {
        const res = await j(base + apiPrefix + '/leaves/' + encodeURIComponent(id) + businessQuery());
        const r = (res && res.data) || res;
        if (!r || !(r.leave_id || r.id)) return;
        document.getElementById('leave-editing-id').value = r.leave_id || r.id;
        document.getElementById('lf-user').value = r.user_id || '';
        document.getElementById('lf-type').value = r.leave_type_id || '';
        document.getElementById('lf-start').value = (r.start_date || '').toString().substring(0, 10);
        document.getElementById('lf-end').value = (r.end_date || '').toString().substring(0, 10);
        document.getElementById('lf-reason').value = r.reason || '';
        document.getElementById('lf-notes').value = r.notes || '';
        openModal(true);
    }

    async function deleteRow(id) {
        if (!confirm('Bu izin kaydını silmek istiyor musunuz?')) return;
        const r = await fetch(base + apiPrefix + '/leaves/' + encodeURIComponent(id) + businessQuery(), {
            method: 'DELETE',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-CSRF-Token': csrf(), 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await r.json().catch(() => ({}));
        if (r.ok && data.success) { load(); return; }
        if (window.NotificationManager) window.NotificationManager.error(data.message || 'Silinemedi');
    }

    function render(rows) {
        const tbody = document.getElementById('leave-rows');
        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="7" class="q-empty" style="padding:var(--space-8);">Kayıt yok</td></tr>';
            return;
        }
        tbody.innerHTML = rows.map(r => {
            const id = r.leave_id || r.id;
            const st = String(r.status || '').toUpperCase();
            const actions = (st === 'PENDING'
                ? '<button type="button" class="q-btn q-btn--primary q-btn--sm lv-approve">Onayla</button> '
                 + '<button type="button" class="q-btn q-btn--danger q-btn--sm lv-reject">Reddet</button> '
                : '') + '<button type="button" class="q-btn q-btn--ghost q-btn--sm lv-edit">Düzenle</button> '
                 + '<button type="button" class="q-btn q-btn--danger q-btn--sm lv-del">Sil</button>';
            return '<tr data-id="' + esc(id) + '">'
                + '<td style="font-weight:800;">' + esc(r.staff_name || r.user_name || '—') + '</td>'
                + '<td>' + esc(r.leave_type_name || r.leave_type_code || '—') + '</td>'
                + '<td>' + esc(r.start_date) + '</td>'
                + '<td>' + esc(r.end_date) + '</td>'
                + '<td style="text-align:right;">' + Number(r.total_days || 0) + '</td>'
                + '<td>' + badge(r.status) + '</td>'
                + '<td style="text-align:right;white-space:nowrap;display:flex;gap:4px;justify-content:flex-end;flex-wrap:wrap;">' + actions + '</td>'
                + '</tr>';
        }).join('');

        tbody.querySelectorAll('tr[data-id]').forEach(tr => {
            const id = tr.dataset.id;
            tr.querySelector('.lv-approve')?.addEventListener('click', async () => {
                await j(base + apiPrefix + '/leaves/' + encodeURIComponent(id) + '/approve' + businessQuery(), { method: 'POST' });
                load();
            });
            tr.querySelector('.lv-reject')?.addEventListener('click', async () => {
                if (!confirm('Reddet?')) return;
                await j(base + apiPrefix + '/leaves/' + encodeURIComponent(id) + '/reject' + businessQuery(), { method: 'POST' });
                load();
            });
            tr.querySelector('.lv-edit')?.addEventListener('click', () => editRow(id));
            tr.querySelector('.lv-del')?.addEventListener('click', () => deleteRow(id));
        });
    }

    document.getElementById('leave-status')?.addEventListener('change', load);

    document.getElementById('btn-report')?.addEventListener('click', async () => {
        const u = document.getElementById('rep-user').value;
        const s = document.getElementById('rep-start').value;
        const e = document.getElementById('rep-end').value;
        const qs = new URLSearchParams({ start: s, end: e });
        if (u) qs.set('user_id', u);
        if (urlBusinessId) qs.set('business_id', urlBusinessId);
        try {
            const res = await j(base + apiPrefix + '/leaves/report?' + qs.toString(), { method: 'GET' });
            const d = (res && res.data) || {};
            const sum = d.summary || {};
            document.getElementById('rep-total-days').textContent = String(Math.round((sum.total_days || 0) * 10) / 10);
            document.getElementById('rep-count').textContent = String(sum.request_count || 0);
            const by = (sum.by_type || []).map(t => (t.leave_type_name || t.leave_type_id) + ': ' + (t.days || 0) + ' gün');
            document.getElementById('rep-by-type').textContent = by.length ? by.join(' · ') : '—';
            document.getElementById('rep-summary').classList.remove('hidden');
        } catch (err) {
            if (window.NotificationManager) window.NotificationManager.error(err.message || 'Rapor hatası');
        }
    });

    load();
})();
</script>
