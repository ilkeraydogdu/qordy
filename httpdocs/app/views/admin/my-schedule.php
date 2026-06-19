<?php
/**
 * "Benim vardiyam" — Warm Ember Ops (.q-* design system)
 */
$apiPrefix = '/api/business';
$baseUrl = BASE_URL;
?>
<div class="q-page q-biz-theme animate-slide-up">
  <div class="q-container">

    <header class="q-page-header">
      <div>
        <p class="q-page-header__eyebrow">Personel</p>
        <h1 class="q-page-header__title"><?php echo t('shifts.mySchedule', 'Vardiyalarım'); ?></h1>
        <p class="q-page-header__subtitle"><?php echo t('shifts.myScheduleSubtitle', 'Seçili dönemdeki vardiya programın ve mesai kayıtların.'); ?></p>
      </div>
      <div class="q-page-header__actions" style="align-items:flex-end;">
        <div class="q-field" style="margin:0;">
          <label class="q-label" for="ms-start"><?php echo t('common.startDate', 'Başlangıç'); ?></label>
          <input type="date" id="ms-start" class="q-input"/>
        </div>
        <div class="q-field" style="margin:0;">
          <label class="q-label" for="ms-end"><?php echo t('common.endDate', 'Bitiş'); ?></label>
          <input type="date" id="ms-end" class="q-input"/>
        </div>
        <button type="button" id="ms-load" class="q-btn q-btn--primary q-btn--sm"><?php echo t('common.load', 'Yükle'); ?></button>
      </div>
    </header>

    <section class="q-card q-card--pad" style="padding:0;overflow:hidden;">
      <div style="overflow-x:auto;">
        <table class="q-table">
          <thead>
            <tr>
              <th><?php echo t('shifts.date', 'Tarih'); ?></th>
              <th><?php echo t('shifts.hours', 'Saat'); ?></th>
              <th><?php echo t('shifts.type', 'Tip'); ?></th>
              <th><?php echo t('shifts.status', 'Durum'); ?></th>
              <th><?php echo t('shifts.clockIn', 'Giriş'); ?></th>
              <th><?php echo t('shifts.clockOut', 'Çıkış'); ?></th>
              <th style="text-align:right;"><?php echo t('shifts.overtimeMin', 'Mesai (dk)'); ?></th>
              <th></th>
            </tr>
          </thead>
          <tbody id="ms-rows">
            <tr><td colspan="8" class="q-empty" style="padding:var(--space-8);"><?php echo t('common.loading', 'Yükleniyor...'); ?></td></tr>
          </tbody>
        </table>
      </div>
    </section>

  </div>
</div>

<script>
(function () {
    const apiPrefix = <?php echo json_encode($apiPrefix); ?>;
    const base      = <?php echo json_encode($baseUrl); ?>;

    function esc(s) { return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
    function csrf() {
        return window.CSRF_TOKEN || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    }
    function fmt(d) {
        return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
    }

    const today = new Date();
    const weekLater = new Date(Date.now() + 7 * 86400000);
    document.getElementById('ms-start').value = fmt(today);
    document.getElementById('ms-end').value   = fmt(weekLater);

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

    function statusBadge(s) {
        const st = String(s || 'PLANNED').toUpperCase();
        const map = {
            'PLANNED':   'q-badge--info',
            'COMPLETED': 'q-badge--success',
            'CONFIRMED': 'q-badge--success',
            'CANCELLED': 'q-badge--danger',
            'ABSENT':    'q-badge--warning'
        };
        const cls = map[st] || 'q-badge--neutral';
        return '<span class="q-badge ' + cls + '">' + esc(st) + '</span>';
    }

    async function load() {
        const s = document.getElementById('ms-start').value;
        const e = document.getElementById('ms-end').value;
        const tbody = document.getElementById('ms-rows');
        tbody.innerHTML = `<tr><td colspan="8" class="q-empty" style="padding:var(--space-8);">${esc('<?php echo t('common.loading', 'Yükleniyor...'); ?>')}</td></tr>`;
        try {
            const res = await j(`${base}${apiPrefix}/shifts/my?start=${encodeURIComponent(s)}&end=${encodeURIComponent(e)}`);
            render((res && res.data) || []);
        } catch (err) {
            tbody.innerHTML = `<tr><td colspan="8" style="padding:var(--space-8);text-align:center;color:var(--color-status-danger);">${esc(err.message || 'Hata')}</td></tr>`;
        }
    }

    function render(rows) {
        const tbody = document.getElementById('ms-rows');
        if (!rows.length) {
            tbody.innerHTML = `<tr><td colspan="8" class="q-empty" style="padding:var(--space-8);"><?php echo t('shifts.noRecords', 'Bu dönemde kayıt yok'); ?></td></tr>`;
            return;
        }
        tbody.innerHTML = rows.map(r => {
            const ot = Number(r.overtime_minutes || 0);
            const scheduleId = r.schedule_id || r.id || '';
            return `
            <tr data-id="${esc(scheduleId)}">
                <td style="font-weight:700;">${esc(r.shift_date || '')}</td>
                <td>${esc((r.start_time || '').toString().substring(0,5))} – ${esc((r.end_time || '').toString().substring(0,5))}</td>
                <td>${esc(r.shift_type || 'REGULAR')}</td>
                <td>${statusBadge(r.status)}</td>
                <td>${esc((r.actual_start || '—').toString().substring(0,5) || '—')}</td>
                <td>${esc((r.actual_end || '—').toString().substring(0,5) || '—')}</td>
                <td style="text-align:right;font-weight:800;color:${ot > 0 ? 'var(--color-brand-accent-hover)' : 'var(--color-text-muted)'};">${ot}</td>
                <td style="text-align:right;white-space:nowrap;">
                    ${(!r.actual_start) ? `<button type="button" class="q-btn q-btn--primary q-btn--sm ms-in"><?php echo t('shifts.clockIn', 'Giriş'); ?></button>` : ''}
                    ${(r.actual_start && !r.actual_end) ? `<button type="button" class="q-btn q-btn--danger q-btn--sm ms-out" style="margin-left:4px;"><?php echo t('shifts.clockOut', 'Çıkış'); ?></button>` : ''}
                </td>
            </tr>`;
        }).join('');

        tbody.querySelectorAll('tr[data-id]').forEach(tr => {
            const id = tr.dataset.id;
            if (!id) return;
            tr.querySelector('.ms-in')?.addEventListener('click', async () => {
                try { await j(`${base}${apiPrefix}/shifts/${encodeURIComponent(id)}/clock-in`, { method: 'POST', body: '{}' }); load(); }
                catch (err) { window.NotificationManager?.error(err.message || 'Hata'); }
            });
            tr.querySelector('.ms-out')?.addEventListener('click', async () => {
                try { await j(`${base}${apiPrefix}/shifts/${encodeURIComponent(id)}/clock-out`, { method: 'POST', body: '{}' }); load(); }
                catch (err) { window.NotificationManager?.error(err.message || 'Hata'); }
            });
        });
    }

    document.getElementById('ms-load').addEventListener('click', load);
    load();
})();
</script>
