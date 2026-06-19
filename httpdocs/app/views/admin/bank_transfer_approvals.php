<?php
require_once __DIR__ . '/../../helpers/translations.php';
if (!function_exists('getAdminUrl')) {
    require_once __DIR__ . '/../../helpers/url_helper.php';
}
$pendingTransfers = $pendingTransfers ?? [];
$allTransfers = $allTransfers ?? [];
$baseUrl = BASE_URL;
$adminPrefix = (isset($is_super_admin) && $is_super_admin) ? '/qodmin' : '/business';
?>

<div class="q-page q-biz-theme animate-slide-up">
  <div class="q-container q-stack q-stack--lg">
    <header class="q-page-header">
      <div>
        <p class="q-page-header__eyebrow">Ödeme</p>
        <h1 class="q-page-header__title">Havale Ödeme Onayları</h1>
      </div>
      <div class="q-page-header__actions">
            <?php if (count($pendingTransfers) > 0): ?>
            <span class="q-badge q-badge--warning"><?php echo count($pendingTransfers); ?> bekleyen</span>
            <?php endif; ?>
      </div>
    </header>

    <?php if (!empty($pendingTransfers)): ?>
    <section class="q-stack q-stack--md">
        <h2 class="q-card__title">Onay Bekleyenler</h2>
        <div class="q-stack q-stack--sm">
            <?php foreach ($pendingTransfers as $t): ?>
            <div id="transfer-<?php echo htmlspecialchars($t['transfer_id']); ?>" class="q-card q-card--pad" style="border-color:var(--color-brand-accent);">
                <div class="q-toolbar" style="flex-wrap:wrap;align-items:flex-start;gap:var(--space-4);">
                    <div class="flex-1 min-w-0 grid grid-cols-2 sm:grid-cols-4 gap-x-4 gap-y-1 text-sm">
                        <div class="col-span-2 sm:col-span-1">
                            <span class="q-hint">Müşteri</span>
                            <div class="font-bold truncate" style="color:var(--color-text-primary);"><?php echo htmlspecialchars(($t['first_name'] ?? '') . ' ' . ($t['last_name'] ?? '')); ?></div>
                            <div class="q-hint truncate"><?php echo htmlspecialchars($t['customer_email'] ?? ''); ?></div>
                        </div>
                        <div>
                            <span class="q-hint">Paket / Tutar</span>
                            <div class="font-bold" style="color:var(--color-text-primary);"><?php echo htmlspecialchars($t['package_name'] ?? 'N/A'); ?></div>
                            <div class="font-black" style="color:var(--color-brand-accent-hover);"><?php echo number_format($t['amount'] ?? 0, 2, ',', '.'); ?> ₺</div>
                        </div>
                        <div>
                            <span class="q-hint">Kod / Tarih</span>
                            <div class="font-mono font-bold" style="color:var(--color-text-primary);"><?php echo htmlspecialchars($t['unique_code'] ?? ''); ?></div>
                            <div class="q-hint"><?php echo htmlspecialchars($t['created_at'] ?? ''); ?></div>
                        </div>
                        <?php if (!empty($t['sender_name']) || !empty($t['sender_iban'])): ?>
                        <div class="col-span-2 sm:col-span-1">
                            <?php if (!empty($t['sender_name'])): ?><span class="q-hint">Gönderen</span><div class="font-bold text-xs"><?php echo htmlspecialchars($t['sender_name']); ?></div><?php endif; ?>
                            <?php if (!empty($t['sender_iban'])): ?><span class="q-hint">IBAN</span><div class="font-mono text-xs truncate"><?php echo htmlspecialchars($t['sender_iban']); ?></div><?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="q-toolbar shrink-0">
                        <?php if (!empty($t['receipt_file_path'])): ?>
                        <a href="<?php echo $baseUrl . $adminPrefix . '/bank-transfers/' . htmlspecialchars($t['transfer_id']) . '/receipt'; ?>" target="_blank" class="q-card q-card--pad flex items-center justify-center overflow-hidden" style="width:4rem;height:4rem;padding:0;">
                            <?php
                            $ext = strtolower(pathinfo($t['receipt_file_path'], PATHINFO_EXTENSION));
                            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])):
                            ?>
                            <img src="<?php echo $baseUrl . htmlspecialchars($t['receipt_file_path']); ?>" class="w-full h-full object-cover" alt="Dekont">
                            <?php else: ?>
                            <span class="q-hint">📄</span>
                            <?php endif; ?>
                        </a>
                        <?php else: ?>
                        <div class="q-card q-card--pad flex items-center justify-center" style="width:4rem;height:4rem;background:var(--color-surface-muted);border-style:dashed;">
                            <span class="q-hint" style="font-size:10px;">Dekont yok</span>
                        </div>
                        <?php endif; ?>
                        <input type="text" id="note-<?php echo htmlspecialchars($t['transfer_id']); ?>" placeholder="Not" class="q-input" class="text-sm">
                        <button onclick="approveTransfer('<?php echo htmlspecialchars($t['transfer_id']); ?>')" class="q-btn q-btn--primary min-h-[44px] min-w-[44px]" title="Onayla" aria-label="Onayla">✓</button>
                        <button onclick="rejectTransfer('<?php echo htmlspecialchars($t['transfer_id']); ?>')" class="q-btn q-btn--danger min-h-[44px] min-w-[44px]" title="Reddet" aria-label="Reddet">✗</button>
                        <button onclick="deleteTransfer('<?php echo htmlspecialchars($t['transfer_id']); ?>')" class="q-btn q-btn--secondary min-h-[44px] min-w-[44px]" title="Sil" aria-label="Sil">🗑</button>
                    </div>
                </div>
                <div class="q-toolbar" style="margin-top:var(--space-2);">
                    <span class="q-badge q-badge--warning">Bekliyor</span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php else: ?>
    <div class="q-card q-card--pad text-center">
        <div class="text-4xl mb-3">✅</div>
        <p class="q-hint font-bold">Onay bekleyen havale bulunmuyor.</p>
    </div>
    <?php endif; ?>

    <?php if (!empty($allTransfers)): ?>
    <section class="q-stack q-stack--md">
        <h2 class="q-card__title">Tüm Havale Geçmişi</h2>
        <div class="q-card" style="padding:0;overflow:hidden;">
            <div class="overflow-x-auto">
                <table class="q-table w-full text-sm">
                    <thead>
                        <tr>
                            <th>Müşteri</th>
                            <th>Paket</th>
                            <th>Tutar</th>
                            <th>Kod</th>
                            <th>Durum</th>
                            <th>Tarih</th>
                            <th style="text-align:right;">İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($allTransfers as $t): ?>
                        <tr id="row-<?php echo htmlspecialchars($t['transfer_id']); ?>">
                            <td>
                                <div class="font-bold"><?php echo htmlspecialchars(($t['first_name'] ?? '') . ' ' . ($t['last_name'] ?? '')); ?></div>
                                <div class="q-hint"><?php echo htmlspecialchars($t['customer_email'] ?? ''); ?></div>
                            </td>
                            <td class="font-bold"><?php echo htmlspecialchars($t['package_name'] ?? 'N/A'); ?></td>
                            <td class="font-black" style="color:var(--color-brand-accent-hover);"><?php echo number_format($t['amount'] ?? 0, 2, ',', '.'); ?> ₺</td>
                            <td class="font-mono text-xs"><?php echo htmlspecialchars($t['unique_code'] ?? ''); ?></td>
                            <td>
                                <?php
                                $statusBadge = ['pending' => 'q-badge--warning', 'approved' => 'q-badge--success', 'rejected' => 'q-badge--danger'];
                                $statusLabels = ['pending' => 'Bekliyor', 'approved' => 'Onaylandı', 'rejected' => 'Reddedildi'];
                                $st = $t['status'] ?? 'pending';
                                ?>
                                <span class="q-badge <?php echo $statusBadge[$st] ?? ''; ?>"><?php echo $statusLabels[$st] ?? $st; ?></span>
                            </td>
                            <td class="q-hint"><?php echo htmlspecialchars($t['created_at'] ?? ''); ?></td>
                            <td style="text-align:right;">
                                <button onclick="deleteTransfer('<?php echo htmlspecialchars($t['transfer_id']); ?>')" class="q-btn q-btn--secondary q-btn--sm" title="Havale kaydını sil">Sil</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
    <?php endif; ?>
  </div>
</div>

<script>
async function approveTransfer(transferId) {
    let confirmed = false;
    if (window.NotificationManager && window.NotificationManager.confirm) {
        confirmed = await window.NotificationManager.confirm('Bu havale ödemesini onaylamak istediğinize emin misiniz? Abonelik otomatik aktif edilecek.', 'Onay');
    } else {
        confirmed = confirm('Bu havale ödemesini onaylamak istediğinize emin misiniz? Abonelik otomatik aktif edilecek.');
    }
    if (!confirmed) return;
    const note = document.getElementById('note-' + transferId)?.value || '';
    await processTransfer(transferId, 'approve', note);
}

async function rejectTransfer(transferId) {
    let confirmed = false;
    if (window.NotificationManager && window.NotificationManager.confirm) {
        confirmed = await window.NotificationManager.confirm('Bu havale ödemesini reddetmek istediğinize emin misiniz? Abonelik iptal edilecek.', 'Onay');
    } else {
        confirmed = confirm('Bu havale ödemesini reddetmek istediğinize emin misiniz? Abonelik iptal edilecek.');
    }
    if (!confirmed) return;
    const note = document.getElementById('note-' + transferId)?.value || '';
    await processTransfer(transferId, 'reject', note);
}

async function deleteTransfer(transferId) {
    let confirmed = false;
    if (window.NotificationManager && window.NotificationManager.confirm) {
        confirmed = await window.NotificationManager.confirm('Bu havale kaydını kalıcı olarak silmek istediğinize emin misiniz? Dekont dosyası ve veritabanı kaydı silinecek. Diğer veriler (müşteri, abonelik vb.) etkilenmez.', 'Sil');
    } else {
        confirmed = confirm('Bu havale kaydını kalıcı olarak silmek istediğinize emin misiniz? Dekont dosyası ve veritabanı kaydı silinecek. Diğer veriler (müşteri, abonelik vb.) etkilenmez.');
    }
    if (!confirmed) return;
    try {
        const csrfToken = window.CSRF_TOKEN || document.querySelector('meta[name="csrf-token"]')?.content || '';
        const url = '<?php echo $baseUrl . $adminPrefix; ?>/bank-transfers/' + encodeURIComponent(transferId) + '/delete';
        const resp = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify({})
        });
        const text = await resp.text();
        let data = {};
        try { data = JSON.parse(text); } catch (e) { data = { success: false, message: text || 'Sunucu yanıtı işlenemedi.' }; }
        if (data.success) {
            if (window.NotificationManager) window.NotificationManager.success(data.message);
            else alert(data.message);
            document.getElementById('transfer-' + transferId)?.remove();
            document.getElementById('row-' + transferId)?.remove();
        } else {
            if (window.NotificationManager) window.NotificationManager.error(data.message || data.error || 'Silme başarısız.');
            else alert(data.message || data.error || 'Silme başarısız.');
        }
    } catch (err) {
        if (window.NotificationManager) window.NotificationManager.error('Hata: ' + err.message);
        else alert('Hata: ' + err.message);
    }
}

async function processTransfer(transferId, action, adminNote) {
    try {
        const csrfToken = window.CSRF_TOKEN || document.querySelector('meta[name="csrf-token"]')?.content || '';
        const url = '<?php echo $baseUrl . $adminPrefix; ?>/bank-transfers/' + encodeURIComponent(transferId) + '/' + action;
        const resp = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify({ admin_note: adminNote })
        });
        const text = await resp.text();
        let data = {};
        try { data = JSON.parse(text); } catch (e) { data = { success: false, message: text || 'Sunucu yanıtı işlenemedi.' }; }
        if (data.success) {
            if (window.NotificationManager) window.NotificationManager.success(data.message);
            else alert(data.message);
            const el = document.getElementById('transfer-' + transferId);
            if (el) { el.style.opacity = '0.5'; el.style.pointerEvents = 'none'; }
            setTimeout(() => location.reload(), 1000);
        } else {
            if (window.NotificationManager) window.NotificationManager.error(data.message || data.error || 'İşlem başarısız.');
            else alert(data.message || data.error || 'İşlem başarısız.');
        }
    } catch (err) {
        if (window.NotificationManager) window.NotificationManager.error('Hata: ' + err.message);
        else alert('Hata: ' + err.message);
    }
}
</script>
