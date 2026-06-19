<?php
/**
 * Trial users — Warm Ember Ops (.q-*)
 */
require_once __DIR__ . '/../../helpers/translations.php';

if (!function_exists('getAdminUrl')) {
    require_once __DIR__ . '/../../helpers/url_helper.php';
}

$users = $users ?? [];
$total = $total ?? 0;
$page = $page ?? 1;
$totalPages = $totalPages ?? 0;
$filter = $filter ?? 'all';
$stats = $stats ?? [];

$filters = [
    'all' => ['Tümü', $stats['total_trials'] ?? 0],
    'active' => ['Aktif', $stats['active_trials'] ?? 0],
    'expired' => ['Süresi Dolan', $stats['expired_trials'] ?? 0],
    'converted' => ['Satın Almış', $stats['converted_trials'] ?? 0],
];
?>
<div class="q-page q-biz-theme animate-slide-up">
  <div class="q-container">

    <header class="q-page-header">
      <div>
        <p class="q-page-header__eyebrow">Abonelik</p>
        <h1 class="q-page-header__title">Trial Kullanıcıları</h1>
        <p class="q-page-header__subtitle">Toplam <?php echo (int)$total; ?> deneme kullanıcısı</p>
      </div>
      <div class="q-page-header__actions">
        <a href="<?php echo getAdminUrl('trial-settings'); ?>" class="q-btn q-btn--ghost">Trial Ayarları</a>
      </div>
    </header>

    <section class="q-card q-card--pad">
      <div style="display:flex;flex-wrap:wrap;gap:var(--space-2);">
        <?php foreach ($filters as $key => [$label, $count]): ?>
          <?php $active = $filter === $key; ?>
          <a href="<?php echo getAdminUrl('trial-users?filter=' . $key); ?>"
             class="q-btn q-btn--sm <?php echo $active ? 'q-btn--primary' : 'q-btn--ghost'; ?>">
            <?php echo htmlspecialchars($label); ?>
            <span class="q-badge q-badge--neutral" style="margin-left:6px;"><?php echo (int)$count; ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="q-card" style="margin-top:var(--space-6);padding:0;overflow:hidden;">
      <?php if (empty($users)): ?>
        <div class="q-empty">
          <h2 class="q-empty__title">Henüz trial kullanıcı bulunmuyor</h2>
        </div>
      <?php else: ?>
        <div style="overflow-x:auto;">
          <table class="q-table">
            <thead>
              <tr>
                <th scope="col">Kullanıcı</th>
                <th scope="col">İşletme</th>
                <th scope="col">Paket</th>
                <th scope="col">Başlangıç</th>
                <th scope="col">Bitiş</th>
                <th scope="col">Durum</th>
                <th scope="col" style="text-align:right;">İşlem</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($users as $u):
                $endsAt = $u['trial_ends_at'] ?? $u['current_period_end'] ?? $u['end_date'] ?? null;
                $remainDays = $endsAt ? max(0, (int)ceil((strtotime($endsAt) - time()) / 86400)) : 0;
                $status = $u['status'] ?? 'pending';
                $isConverted = !empty($u['trial_converted']);
                $fullName = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''));
              ?>
              <tr>
                <td>
                  <div style="font-weight:var(--font-weight-bold);"><?php echo htmlspecialchars($fullName ?: '-'); ?></div>
                  <div class="q-hint"><?php echo htmlspecialchars($u['email'] ?? ''); ?></div>
                </td>
                <td><?php echo htmlspecialchars($u['company_name'] ?? '-'); ?></td>
                <td><?php echo htmlspecialchars($u['package_name'] ?? '-'); ?></td>
                <td><?php echo !empty($u['trial_started_at']) ? date('d.m.Y', strtotime($u['trial_started_at'])) : '-'; ?></td>
                <td><?php echo $endsAt ? date('d.m.Y', strtotime($endsAt)) : '-'; ?></td>
                <td>
                  <?php if ($isConverted): ?>
                    <span class="q-badge q-badge--success">Satın Aldı</span>
                  <?php elseif ($status === 'active'): ?>
                    <span class="q-badge q-badge--info"><?php echo $remainDays; ?> gün</span>
                  <?php elseif ($status === 'expired'): ?>
                    <span class="q-badge q-badge--danger">Süresi Doldu</span>
                  <?php else: ?>
                    <span class="q-badge q-badge--neutral"><?php echo htmlspecialchars(ucfirst($status)); ?></span>
                  <?php endif; ?>
                </td>
                <td style="text-align:right;">
                  <div style="display:flex;gap:var(--space-2);justify-content:flex-end;flex-wrap:wrap;">
                    <?php if ($status === 'active' || $status === 'expired'): ?>
                    <button type="button" onclick="trialExtend('<?php echo htmlspecialchars($u['subscription_id'] ?? ''); ?>')" class="q-btn q-btn--soft q-btn--sm">+7 Gün</button>
                    <?php endif; ?>
                    <?php if ($status === 'active'): ?>
                    <button type="button" onclick="trialCancel('<?php echo htmlspecialchars($u['subscription_id'] ?? ''); ?>')" class="q-btn q-btn--danger q-btn--sm">İptal</button>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>

    <?php if ($totalPages > 1): ?>
    <nav class="q-stack" style="margin-top:var(--space-6);display:flex;flex-direction:row;justify-content:center;gap:var(--space-2);flex-wrap:wrap;" aria-label="Sayfalama">
      <?php for ($i = 1; $i <= $totalPages; $i++): ?>
      <a href="<?php echo getAdminUrl('trial-users?filter=' . urlencode($filter) . '&page=' . $i); ?>"
         class="q-btn q-btn--sm <?php echo $i === $page ? 'q-btn--primary' : 'q-btn--ghost'; ?>"><?php echo $i; ?></a>
      <?php endfor; ?>
    </nav>
    <?php endif; ?>

  </div>
</div>

<script>
async function trialExtend(subId) {
    if (!confirm('Trial süresini 7 gün uzatmak istediğinize emin misiniz?')) return;
    try {
        const resp = await fetch('<?php echo rtrim(BASE_URL, '/'); ?>/api/qodmin/trial/extend', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': window.CSRF_TOKEN,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ subscription_id: subId, extra_days: 7 })
        });
        const data = await resp.json();
        if (data.success) {
            if (window.NotificationManager) window.NotificationManager.success('Trial süresi uzatıldı');
            location.reload();
        } else {
            if (window.NotificationManager) window.NotificationManager.error(data.message || 'Hata oluştu');
        }
    } catch (e) {
        if (window.NotificationManager) window.NotificationManager.error('Bağlantı hatası');
    }
}

async function trialCancel(subId) {
    if (!confirm('Bu trial aboneliği iptal etmek istediğinize emin misiniz?')) return;
    try {
        const resp = await fetch('<?php echo rtrim(BASE_URL, '/'); ?>/api/qodmin/trial/cancel', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': window.CSRF_TOKEN,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ subscription_id: subId })
        });
        const data = await resp.json();
        if (data.success) {
            if (window.NotificationManager) window.NotificationManager.success('Trial iptal edildi');
            location.reload();
        } else {
            if (window.NotificationManager) window.NotificationManager.error(data.message || 'Hata oluştu');
        }
    } catch (e) {
        if (window.NotificationManager) window.NotificationManager.error('Bağlantı hatası');
    }
}
</script>
