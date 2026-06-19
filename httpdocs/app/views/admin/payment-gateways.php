<?php
require_once __DIR__ . '/../../helpers/translations.php';
$gateways = $gateways ?? [];
$isSuperAdmin = $is_super_admin ?? false;
$apiPrefix = $api_prefix ?? '/api/qodmin';
$gwUpdatePath = $isSuperAdmin ? ($apiPrefix . '/payment-gateways/update') : '/api/business/payment-gateway/update';
$gwSeedPath = $isSuperAdmin ? ($apiPrefix . '/payment-gateways/seed') : '/api/business/payment-gateway/seed';
// Havale / EFT aç-kapa ayarı tek bir yerden yönetilsin diye /qodmin/settings
// Ödeme sekmesine taşındı. Burada duplicate bir toggle tutulmuyor.
$paymentSettingsLink = $isSuperAdmin
    ? (BASE_URL . '/qodmin/settings?tab=payment#payment')
    : (BASE_URL . '/business/settings?tab=payment#payment');
?>

<div class="q-page q-biz-theme animate-slide-up">
  <div class="q-container q-stack q-stack--lg">
    <header class="q-page-header">
      <div>
        <p class="q-page-header__eyebrow">Ödeme</p>
        <h1 class="q-page-header__title">Ödeme Entegrasyonları</h1>
        <p class="q-page-header__subtitle">Ödeme gateway yapılandırması</p>
      </div>
        <?php if (empty($gateways)): ?>
        <div class="q-page-header__actions">
        <button type="button" onclick="seedGateways()" id="seedBtn" class="q-btn q-btn--primary">
            Gateway'leri Başlat
        </button>
        </div>
        <?php endif; ?>
    </header>

    <div class="q-card q-card--pad q-card--soft max-w-2xl mb-6 flex items-start gap-3">
            <div class="w-9 h-9 rounded-xl flex items-center justify-center text-xs font-black flex-shrink-0" style="background:var(--color-amber-soft);color:var(--color-brand-accent);">EFT</div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-bold" style="color:var(--color-ink);">Havale / EFT aç-kapa ayarı taşındı</p>
                <p class="q-hint text-xs mt-1">
                    Paket ödemelerinde havale seçeneğinin açık/kapalı olmasını
                    <a href="<?php echo htmlspecialchars($paymentSettingsLink); ?>" class="text-amber-700 font-bold hover:underline">Ayarlar &rarr; Ödeme</a>
                    sekmesinden yönetebilirsin.
                    <?php if (!empty($isSuperAdmin)): ?>
                    IBAN/banka hesapları ise <a href="<?php echo BASE_URL; ?>/qodmin/bank-accounts" class="text-amber-700 font-bold hover:underline">Banka Hesapları</a> ekranında tanımlanır.
                    <?php endif; ?>
                </p>
            </div>
    </div>

    <?php if (empty($gateways)): ?>
    <div class="q-empty q-card q-card--pad max-w-2xl">
            <div class="q-empty__icon mx-auto mb-5">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                </svg>
            </div>
            <h2 class="q-empty__title mb-2">Henüz Gateway Tanımlanmamış</h2>
            <p class="q-hint text-sm leading-relaxed mb-4">
                Ödeme gateway'lerini başlatmak için yukarıdaki butonu kullanın.
            </p>
    </div>
    <?php else: ?>

    <div class="q-grid q-grid--3 max-w-6xl" id="gatewayCards">
        <?php foreach ($gateways as $gw):
            $gwId = $gw['gateway_id'] ?? '';
            $gwCode = $gw['gateway_code'] ?? '';
            $gwName = $gw['display_name'] ?? $gw['gateway_name'] ?? $gwCode;
            $isEnabled = ($gw['is_enabled'] ?? 0) == 1;
            $testMode = ($gw['test_mode'] ?? 1) == 1;
            $fields = $gw['fields'] ?? [];
        ?>
        <div class="q-card overflow-hidden" data-gateway-id="<?php echo htmlspecialchars($gwId); ?>">
            <div class="q-card--pad q-toolbar" style="border-bottom:1px solid var(--color-border-subtle);">
                <div class="q-toolbar gap-3">
                    <div class="w-10 h-10 rounded-xl flex items-center justify-center text-lg font-black q-badge <?php echo $isEnabled ? 'q-badge--success' : 'q-badge--neutral'; ?>">
                        <?php echo strtoupper(substr($gwCode, 0, 2)); ?>
                    </div>
                    <div>
                        <h3 class="q-card__title"><?php echo htmlspecialchars($gwName); ?></h3>
                        <p class="q-hint text-xs"><?php echo htmlspecialchars($gw['description'] ?? ''); ?></p>
                    </div>
                </div>
                <span class="status-badge q-badge <?php echo $isEnabled ? 'q-badge--success' : 'q-badge--neutral'; ?>">
                    <?php echo $isEnabled ? 'Aktif' : 'Pasif'; ?>
                </span>
            </div>

            <div class="q-card--pad q-stack q-stack--md">
                <div class="q-toolbar">
                    <label class="q-label">Durum</label>
                    <input type="checkbox" class="gw-enabled shrink-0" data-gw="<?php echo htmlspecialchars($gwId); ?>"
                        <?php echo $isEnabled ? 'checked' : ''; ?>
                        aria-label="Gateway durumu">
                </div>

                <div class="q-toolbar">
                    <label class="q-label">Mod</label>
                    <select class="gw-testmode q-input w-full sm:w-auto min-w-0 sm:min-w-[10rem]" data-gw="<?php echo htmlspecialchars($gwId); ?>">
                        <option value="0" <?php echo !$testMode ? 'selected' : ''; ?>>Production</option>
                        <option value="1" <?php echo $testMode ? 'selected' : ''; ?>>Sandbox / Test</option>
                    </select>
                </div>

                <?php foreach ($fields as $fieldKey => $fieldDef):
                    $fieldLabel = $fieldDef['label'] ?? $fieldKey;
                    $fieldType = $fieldDef['type'] ?? 'text';
                    $fieldPlaceholder = $fieldDef['placeholder'] ?? '';
                    $fieldHelp = $fieldDef['help'] ?? '';
                    $currentVal = $gw[$fieldKey] ?? '';
                ?>
                <div class="q-field">
                    <label class="q-field__label"><?php echo htmlspecialchars($fieldLabel); ?></label>
                    <input type="<?php echo $fieldType === 'password' ? 'password' : 'text'; ?>"
                           class="gw-field q-input w-full"
                           data-gw="<?php echo htmlspecialchars($gwId); ?>"
                           data-field="<?php echo htmlspecialchars($fieldKey); ?>"
                           placeholder="<?php echo htmlspecialchars($fieldPlaceholder); ?>"
                           value="<?php echo htmlspecialchars($currentVal); ?>">
                    <?php if ($fieldHelp): ?>
                    <p class="q-hint text-xs mt-1"><?php echo htmlspecialchars($fieldHelp); ?></p>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>

                <button type="button" onclick="saveGateway('<?php echo htmlspecialchars($gwId); ?>')"
                        class="q-btn q-btn--primary q-btn--block mt-2">
                    Kaydet
                </button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<script>
const baseUrl = <?php echo json_encode(defined('BASE_URL') ? BASE_URL : ''); ?>;
const apiPrefix = <?php echo json_encode($apiPrefix); ?>;
const gwUpdatePath = <?php echo json_encode($gwUpdatePath); ?>;
const gwSeedPath = <?php echo json_encode($gwSeedPath); ?>;

// Havale / EFT toggle /qodmin/settings Ödeme sekmesine taşındı — burada
// ayrı bir endpoint tutulmuyor, controller tarafında da endpoint kaldırıldı.

function getCSRF() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

async function seedGateways() {
    const btn = document.getElementById('seedBtn');
    btn.disabled = true;
    btn.textContent = 'Başlatılıyor...';
    try {
        const r = await fetch(baseUrl + gwSeedPath, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCSRF(), 'Accept': 'application/json' },
            credentials: 'same-origin'
        });
        const d = await r.json();
        if (d.success) {
            showToast('Gateway\'ler başarıyla oluşturuldu', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(d.error || 'Hata oluştu', 'error');
            btn.disabled = false;
            btn.textContent = 'Gateway\'leri Başlat';
        }
    } catch (e) {
        showToast('Bağlantı hatası: ' + e.message, 'error');
        btn.disabled = false;
        btn.textContent = 'Gateway\'leri Başlat';
    }
}

async function saveGateway(gwId) {
    const card = document.querySelector(`[data-gateway-id="${gwId}"]`);
    if (!card) return;

    const config = {};
    const enabledEl = card.querySelector('.gw-enabled');
    const testModeEl = card.querySelector('.gw-testmode');
    config.is_enabled = enabledEl?.checked ? 1 : 0;
    config.test_mode = parseInt(testModeEl?.value || '1');

    card.querySelectorAll('.gw-field').forEach(input => {
        const field = input.dataset.field;
        if (field) config[field] = input.value;
    });

    const btn = card.querySelector('button');
    const origText = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Kaydediliyor...';

    try {
        const r = await fetch(baseUrl + gwUpdatePath, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCSRF(), 'Accept': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ gateway_id: gwId, config })
        });
        const d = await r.json();
        if (d.success) {
            showToast('Ayarlar kaydedildi', 'success');
            const badge = card.querySelector('.status-badge');
            if (badge) {
                if (config.is_enabled) {
                    badge.textContent = 'Aktif';
                    badge.className = 'status-badge q-badge q-badge--success';
                } else {
                    badge.textContent = 'Pasif';
                    badge.className = 'status-badge q-badge q-badge--neutral';
                }
            }
        } else {
            showToast(d.error || 'Kaydetme başarısız', 'error');
        }
    } catch (e) {
        showToast('Bağlantı hatası: ' + e.message, 'error');
    }
    btn.disabled = false;
    btn.textContent = origText;
}

function showToast(msg, type) {
    if (typeof window.showNotification === 'function') { window.showNotification(msg, type); return; }
    const t = document.createElement('div');
    t.className = `fixed top-4 right-4 z-[9999] px-5 py-3 rounded-xl text-sm font-bold text-white shadow-lg transition-all ${type === 'success' ? 'bg-green-600' : 'bg-red-600'}`;
    t.textContent = msg;
    document.body.appendChild(t);
    setTimeout(() => { t.style.opacity = '0'; setTimeout(() => t.remove(), 300); }, 3000);
}
</script>
