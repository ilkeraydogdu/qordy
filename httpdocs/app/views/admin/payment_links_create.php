<?php
if (!function_exists('getAdminUrl')) {
    require_once __DIR__ . '/../../helpers/url_helper.php';
}

$packages  = $packages ?? [];
$customers = $customers ?? [];
?>

<div class="q-page q-biz-theme animate-slide-up">
<div class="q-container q-stack q-stack--lg">
    <header class="q-page-header">
        <div>
            <p class="q-page-header__eyebrow">Süper Admin</p>
            <h1 class="q-page-header__title">Yeni Ödeme Bağlantısı</h1>
            <p class="q-page-header__subtitle">Mevcut müşteri veya yeni e-posta için özel fiyat ve süre tanımla</p>
        </div>
        <div class="q-page-header__actions">
            <a href="<?php echo getAdminUrl('payment-links'); ?>" class="q-btn q-btn--ghost">← Geri</a>
        </div>
    </header>

    <form method="POST" action="<?php echo getAdminUrl('payment-links'); ?>"
          class="q-card q-card--pad q-stack q-stack--lg max-w-3xl">
        <?php echo csrf_field(); ?>

        <div class="q-field">
            <label class="q-label">Mod</label>
            <div class="q-grid q-grid--2 gap-3">
                <label class="q-card q-card--pad flex items-start gap-3 cursor-pointer has-[:checked]:ring-2" style="--tw-ring-color:var(--color-accent-primary);">
                    <input type="radio" name="mode" value="existing_customer" checked onchange="toggleMode(this.value)">
                    <div>
                        <div class="font-semibold">Mevcut Müşteri</div>
                        <div class="q-hint text-xs">Sistemdeki bir müşteriye özel teklif üret</div>
                    </div>
                </label>
                <label class="q-card q-card--pad flex items-start gap-3 cursor-pointer has-[:checked]:ring-2" style="--tw-ring-color:var(--color-accent-primary);">
                    <input type="radio" name="mode" value="new_customer" onchange="toggleMode(this.value)">
                    <div>
                        <div class="font-semibold">Yeni Müşteri</div>
                        <div class="q-hint text-xs">E-posta ile yeni hesap açılacak şekilde</div>
                    </div>
                </label>
            </div>
        </div>

        <div id="mode-existing" class="q-stack q-stack--md">
            <div class="q-field">
                <label class="q-label">Müşteri</label>
                <select name="customer_id" class="q-input">
                    <option value="">Müşteri seç…</option>
                    <?php foreach ($customers as $c): ?>
                        <option value="<?php echo htmlspecialchars($c['customer_id']); ?>">
                            <?php echo htmlspecialchars($c['name']); ?> — <?php echo htmlspecialchars($c['email']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div id="mode-new" class="q-stack q-stack--md hidden">
            <div class="q-grid q-grid--2 gap-4">
                <div class="q-field">
                    <label class="q-label">E-posta</label>
                    <input type="email" name="target_email" placeholder="ornek@firma.com" class="q-input">
                </div>
                <div class="q-field">
                    <label class="q-label">İsim (opsiyonel)</label>
                    <input type="text" name="target_name" placeholder="Ahmet Yılmaz / Firma Adı" class="q-input">
                </div>
            </div>
        </div>

        <hr style="border-color:var(--color-border-subtle);">

        <div class="q-grid q-grid--2 gap-4">
            <div class="q-field">
                <label class="q-label">Paket</label>
                <select name="package_id" required class="q-input">
                    <option value="">Paket seç…</option>
                    <?php foreach ($packages as $p): ?>
                        <option value="<?php echo htmlspecialchars($p['package_id']); ?>">
                            <?php echo htmlspecialchars($p['name']); ?>
                            <?php if (!empty($p['price_yearly'])): ?>
                                (Yıllık ₺<?php echo number_format((float)$p['price_yearly'], 2, ',', '.'); ?>)
                            <?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="q-field">
                <label class="q-label">Süre (ay)</label>
                <input type="number" name="duration_months" value="12" min="1" max="120" required class="q-input">
            </div>
        </div>

        <div class="q-grid q-grid--2 gap-4">
            <div class="q-field">
                <label class="q-label">Özel Fiyat (KDV dahil)</label>
                <input type="number" step="0.01" min="0" name="custom_price" required class="q-input">
            </div>
            <div class="q-field">
                <label class="q-label">Para Birimi</label>
                <select name="currency" class="q-input">
                    <option value="TRY">TRY</option>
                    <option value="USD">USD</option>
                    <option value="EUR">EUR</option>
                </select>
            </div>
        </div>

        <div class="q-field">
            <label class="q-label">Dahili Not (opsiyonel)</label>
            <textarea name="note" rows="3" placeholder="Satış sonrası hatırlatıcı, müzakere notu vs." class="q-input resize-none"></textarea>
        </div>

        <hr style="border-color:var(--color-border-subtle);">

        <div class="q-grid q-grid--3 gap-4 items-end">
            <label class="q-card q-card--pad flex items-center gap-3 cursor-pointer">
                <input type="checkbox" name="is_single_use" value="1" checked
                       onchange="document.getElementById('max-uses-wrap').classList.toggle('hidden', this.checked)">
                <span class="font-medium text-sm">Tek kullanımlık</span>
            </label>
            <div id="max-uses-wrap" class="q-field hidden">
                <label class="q-label">Maksimum kullanım</label>
                <input type="number" name="max_uses" value="5" min="1" max="999" class="q-input">
            </div>
            <div class="q-field">
                <label class="q-label">Son Kullanım (opsiyonel)</label>
                <input type="datetime-local" name="expires_at" class="q-input">
            </div>
        </div>

        <div class="flex justify-end gap-3">
            <a href="<?php echo getAdminUrl('payment-links'); ?>" class="q-btn q-btn--ghost">İptal</a>
            <button type="submit" class="q-btn q-btn--primary">Bağlantıyı Oluştur</button>
        </div>
    </form>
</div>
</div>

<script>
function toggleMode(mode) {
    document.getElementById('mode-existing').classList.toggle('hidden', mode !== 'existing_customer');
    document.getElementById('mode-new').classList.toggle('hidden', mode !== 'new_customer');
}
</script>
