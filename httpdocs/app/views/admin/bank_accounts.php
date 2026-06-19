<?php
require_once __DIR__ . '/../../helpers/translations.php';
$accounts = $accounts ?? [];
$baseUrl = BASE_URL;
$adminPrefix = (isset($is_super_admin) && $is_super_admin) ? '/qodmin' : '/business';
?>

<div class="q-page q-biz-theme animate-slide-up">
  <div class="q-container q-stack q-stack--lg">
    <header class="q-page-header">
      <div>
        <p class="q-page-header__eyebrow">Ödeme</p>
        <h1 class="q-page-header__title">Banka Hesapları</h1>
        <p class="q-page-header__subtitle">Havale / EFT için IBAN ve hesap bilgilerini yönetin.</p>
      </div>
      <div class="q-page-header__actions">
        <button type="button" onclick="openAccountModal()" class="q-btn q-btn--primary">+ Yeni Hesap</button>
      </div>
    </header>

    <?php if (empty($accounts)): ?>
    <div class="q-card q-card--pad q-empty">
        <div class="text-5xl mb-4" aria-hidden="true">🏦</div>
        <p class="q-hint text-lg mb-4">Henüz banka hesabı eklenmemiş.</p>
        <button type="button" onclick="openAccountModal()" class="q-btn q-btn--primary">İlk Hesabı Ekle</button>
    </div>
    <?php else: ?>
    <div class="q-grid q-grid--3">
        <?php foreach ($accounts as $acc): ?>
        <div class="q-card q-card--pad">
            <div class="flex items-start justify-between mb-3">
                <h3 class="q-card__title"><?php echo htmlspecialchars($acc['bank_name']); ?></h3>
                <span class="q-badge <?php echo $acc['is_active'] ? 'q-badge--success' : 'q-badge--danger'; ?>">
                    <?php echo $acc['is_active'] ? 'Aktif' : 'Pasif'; ?>
                </span>
            </div>
            <div class="q-stack q-stack--sm text-sm mb-4" style="color:var(--color-text-secondary);">
                <div><span class="font-bold">IBAN:</span> <span class="font-mono text-xs"><?php echo htmlspecialchars($acc['iban']); ?></span></div>
                <div><span class="font-bold">Hesap Sahibi:</span> <?php echo htmlspecialchars($acc['account_holder']); ?></div>
                <?php if (!empty($acc['branch_code'])): ?>
                <div><span class="font-bold">Şube:</span> <?php echo htmlspecialchars($acc['branch_code']); ?></div>
                <?php endif; ?>
            </div>
            <div class="flex gap-2">
                <button type="button" onclick='editAccount(<?php echo json_encode($acc); ?>)' class="q-btn q-btn--primary q-btn--sm flex-1">Düzenle</button>
                <button type="button" onclick="deleteAccount('<?php echo htmlspecialchars($acc['account_id']); ?>')" class="q-btn q-btn--danger q-btn--sm">Sil</button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Account Modal -->
<div id="account-modal" class="q-modal-backdrop hidden fixed inset-0 z-[201] items-center justify-center p-4" aria-hidden="true">
    <div class="q-modal-backdrop__scrim" onclick="closeAccountModal()"></div>
    <div class="q-modal relative w-full max-w-lg">
        <div class="q-modal__header">
            <h2 id="modal-title" class="q-modal__title">Yeni Banka Hesabı</h2>
            <button type="button" onclick="closeAccountModal()" class="q-icon-btn" aria-label="Kapat">
                <svg class="w-5 h-5" style="color:var(--color-text-muted);" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <form id="account-form" class="q-modal__body q-stack q-stack--md">
            <input type="hidden" id="edit-account-id" value="">
            <div class="q-field">
                <label class="q-field__label" for="acc-bank-name">Banka Adı</label>
                <input type="text" id="acc-bank-name" required class="q-input">
            </div>
            <div class="q-field">
                <label class="q-field__label" for="acc-iban">IBAN</label>
                <input type="text" id="acc-iban" required maxlength="34" class="q-input font-mono">
            </div>
            <div class="q-field">
                <label class="q-field__label" for="acc-holder">Hesap Sahibi</label>
                <input type="text" id="acc-holder" required class="q-input">
            </div>
            <div class="q-grid q-grid--2">
                <div class="q-field">
                    <label class="q-field__label" for="acc-branch">Şube Kodu</label>
                    <input type="text" id="acc-branch" class="q-input">
                </div>
                <div class="q-field">
                    <label class="q-field__label" for="acc-sort">Sıralama</label>
                    <input type="number" id="acc-sort" value="0" class="q-input">
                </div>
            </div>
            <div class="flex items-center gap-2">
                <input type="checkbox" id="acc-active" checked class="w-4 h-4 rounded accent-amber-500">
                <label for="acc-active" class="q-label">Aktif</label>
            </div>
            <div class="flex gap-3 pt-1">
                <button type="submit" class="q-btn q-btn--primary flex-1">Kaydet</button>
                <button type="button" onclick="closeAccountModal()" class="q-btn q-btn--secondary">İptal</button>
            </div>
        </form>
    </div>
</div>

<script>
let editingId = null;

function openAccountModal(data) {
    editingId = null;
    document.getElementById('modal-title').textContent = 'Yeni Banka Hesabı';
    document.getElementById('edit-account-id').value = '';
    document.getElementById('acc-bank-name').value = '';
    document.getElementById('acc-iban').value = '';
    document.getElementById('acc-holder').value = '';
    document.getElementById('acc-branch').value = '';
    document.getElementById('acc-sort').value = '0';
    document.getElementById('acc-active').checked = true;
    showModal();
}

function editAccount(data) {
    editingId = data.account_id;
    document.getElementById('modal-title').textContent = 'Hesap Düzenle';
    document.getElementById('edit-account-id').value = data.account_id;
    document.getElementById('acc-bank-name').value = data.bank_name || '';
    document.getElementById('acc-iban').value = data.iban || '';
    document.getElementById('acc-holder').value = data.account_holder || '';
    document.getElementById('acc-branch').value = data.branch_code || '';
    document.getElementById('acc-sort').value = data.sort_order || 0;
    document.getElementById('acc-active').checked = !!data.is_active;
    showModal();
}

function showModal() {
    const m = document.getElementById('account-modal');
    m.classList.remove('hidden');
    m.classList.add('flex');
}

function closeAccountModal() {
    const m = document.getElementById('account-modal');
    m.classList.add('hidden');
    m.classList.remove('flex');
}

document.getElementById('account-form').addEventListener('submit', async function(e) {
    e.preventDefault();
    const csrfToken = window.CSRF_TOKEN || document.querySelector('meta[name="csrf-token"]')?.content || '';
    const data = {
        bank_name: document.getElementById('acc-bank-name').value.trim(),
        iban: document.getElementById('acc-iban').value.trim().replace(/\s/g, ''),
        account_holder: document.getElementById('acc-holder').value.trim(),
        branch_code: (document.getElementById('acc-branch').value || '').trim(),
        sort_order: parseInt(document.getElementById('acc-sort').value, 10) || 0,
        is_active: document.getElementById('acc-active').checked ? 1 : 0
    };
    if (csrfToken) data.csrf_token = csrfToken;
    const url = editingId
        ? '<?php echo $baseUrl . $adminPrefix; ?>/bank-accounts/' + editingId
        : '<?php echo $baseUrl . $adminPrefix; ?>/bank-accounts';

    try {
        const resp = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': csrfToken },
            body: JSON.stringify(data),
            credentials: 'same-origin'
        });
        const contentType = resp.headers.get('Content-Type') || '';
        const result = contentType.includes('application/json') ? await resp.json() : { success: false, error: 'Sunucu geçersiz yanıt döndü.' };
        if (result.success) {
            closeAccountModal();
            if (window.NotificationManager) window.NotificationManager.success(result.message || 'Başarıyla kaydedildi.');
            location.reload();
        } else {
            if (window.NotificationManager) window.NotificationManager.error(result.message || result.error || 'Güncelleme başarısız.');
            else alert(result.message || result.error || 'Güncelleme başarısız.');
        }
    } catch (err) {
        if (window.NotificationManager) window.NotificationManager.error('Hata: ' + (err.message || 'Bağlantı hatası'));
        else alert('Hata: ' + (err.message || 'Bağlantı hatası'));
    }
});

async function deleteAccount(id) {
    let confirmed = false;
    if (window.NotificationManager && window.NotificationManager.confirm) {
        confirmed = await window.NotificationManager.confirm('Bu banka hesabını silmek istediğinize emin misiniz?', 'Onay');
    } else {
        confirmed = confirm('Bu banka hesabını silmek istediğinize emin misiniz?');
    }
    if (!confirmed) return;
    const csrfToken = window.CSRF_TOKEN || document.querySelector('meta[name="csrf-token"]')?.content || '';
    try {
        const resp = await fetch('<?php echo $baseUrl . $adminPrefix; ?>/bank-accounts/' + id + '/delete', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': csrfToken },
            body: JSON.stringify({})
        });
        const data = await resp.json();
        if (data.success) {
            if (window.NotificationManager) window.NotificationManager.success(data.message || 'Hesap başarıyla silindi.');
            location.reload();
        } else {
            if (window.NotificationManager) window.NotificationManager.error(data.message || data.error || 'Silme başarısız.');
            else alert(data.message || data.error || 'Silme başarısız.');
        }
    } catch (err) {
        if (window.NotificationManager) window.NotificationManager.error('Hata: ' + err.message);
        else alert('Hata: ' + err.message);
    }
}
</script>