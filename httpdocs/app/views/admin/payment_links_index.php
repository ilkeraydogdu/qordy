<?php
if (!function_exists('getAdminUrl')) {
    require_once __DIR__ . '/../../helpers/url_helper.php';
}

$links = $links ?? [];
$filters = $filters ?? [];
$packagesById = $packagesById ?? [];
$customersById = $customersById ?? [];

$formatPrice = static function ($v) {
    return number_format((float)$v, 2, ',', '.');
};
?>

<div class="q-page q-biz-theme animate-slide-up">
<div class="q-container q-stack q-stack--lg">
    <header class="q-page-header">
        <div>
            <p class="q-page-header__eyebrow">Süper Admin</p>
            <h1 class="q-page-header__title">Özel Ödeme Bağlantıları</h1>
            <p class="q-page-header__subtitle">Kişiye özel fiyat ve süre ile ödeme linkleri oluşturun</p>
        </div>
        <div class="q-page-header__actions">
            <a href="<?php echo getAdminUrl('payment-links/create'); ?>" class="q-btn q-btn--primary">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Yeni Bağlantı
            </a>
        </div>
    </header>

    <form method="GET" class="q-card q-card--pad">
        <div class="q-grid q-grid--4 gap-3">
            <div class="q-field">
                <label class="q-label">Arama</label>
                <input type="text" name="q" value="<?php echo htmlspecialchars($filters['q'] ?? ''); ?>"
                       placeholder="E-posta / ad / not" class="q-input">
            </div>
            <div class="q-field">
                <label class="q-label">Mod</label>
                <select name="mode" class="q-input">
                    <option value="">Tümü</option>
                    <option value="existing_customer" <?php echo ($filters['mode'] ?? '') === 'existing_customer' ? 'selected' : ''; ?>>Mevcut Müşteri</option>
                    <option value="new_customer" <?php echo ($filters['mode'] ?? '') === 'new_customer' ? 'selected' : ''; ?>>Yeni Müşteri</option>
                </select>
            </div>
            <div class="q-field">
                <label class="q-label">Durum</label>
                <select name="is_active" class="q-input">
                    <option value="">Tümü</option>
                    <option value="1" <?php echo ($filters['is_active'] ?? '') === '1' ? 'selected' : ''; ?>>Aktif</option>
                    <option value="0" <?php echo ($filters['is_active'] ?? '') === '0' ? 'selected' : ''; ?>>Pasif</option>
                </select>
            </div>
            <div class="q-field flex items-end">
                <button type="submit" class="q-btn q-btn--ink w-full">Filtrele</button>
            </div>
        </div>
    </form>

    <div class="q-card overflow-hidden">
        <div class="q-table-wrap">
            <table class="q-table">
                <thead>
                    <tr>
                        <th>Hedef</th>
                        <th>Paket</th>
                        <th>Fiyat</th>
                        <th>Süre</th>
                        <th>Kullanım</th>
                        <th>Durum</th>
                        <th>URL</th>
                        <th>İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($links)): ?>
                    <tr>
                        <td colspan="8" class="q-empty">Henüz oluşturulmuş ödeme bağlantısı yok.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($links as $link): ?>
                        <?php
                            $isActive = (int)($link['is_active'] ?? 0) === 1;
                            $pkg = $packagesById[$link['package_id']] ?? null;
                            $pkgName = $pkg['name'] ?? $link['package_id'];
                            $targetLabel = '';
                            if ($link['mode'] === 'existing_customer') {
                                $cust = $customersById[$link['customer_id']] ?? null;
                                $targetLabel = $cust
                                    ? htmlspecialchars($cust['name'] ?? '') . '<br><span class="q-hint text-xs">' . htmlspecialchars($cust['email'] ?? '') . '</span>'
                                    : htmlspecialchars($link['customer_id'] ?? '');
                            } else {
                                $targetLabel = htmlspecialchars($link['target_name'] ?: '(isimsiz)') . '<br><span class="q-hint text-xs">' . htmlspecialchars($link['target_email'] ?? '') . '</span>';
                            }

                            $publicUrl = \App\Core\DependencyFactory::getCustomPaymentLinkService()->buildUrl($link['token']);
                        ?>
                        <tr>
                            <td class="align-top">
                                <div class="font-bold"><?php echo $targetLabel; ?></div>
                                <div class="q-hint text-[11px] uppercase font-semibold mt-1">
                                    <?php echo $link['mode'] === 'existing_customer' ? 'Mevcut müşteri' : 'Yeni müşteri'; ?>
                                </div>
                            </td>
                            <td class="align-top"><?php echo htmlspecialchars($pkgName); ?></td>
                            <td class="align-top font-bold">
                                <?php echo $formatPrice($link['custom_price']); ?> <?php echo htmlspecialchars($link['currency'] ?? 'TRY'); ?>
                            </td>
                            <td class="align-top"><?php echo (int)$link['duration_months']; ?> ay</td>
                            <td class="align-top">
                                <?php echo (int)$link['used_count']; ?>
                                <?php if ((int)$link['is_single_use'] === 1): ?>
                                    <span class="q-hint text-[10px]">/ tek kullanım</span>
                                <?php else: ?>
                                    <span class="q-hint text-[10px]">/ <?php echo (int)$link['max_uses']; ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="align-top">
                                <?php if ($isActive): ?>
                                    <span class="q-badge q-badge--success">Aktif</span>
                                <?php else: ?>
                                    <span class="q-badge q-badge--neutral">Pasif</span>
                                <?php endif; ?>
                            </td>
                            <td class="align-top">
                                <div class="q-toolbar q-toolbar--wrap">
                                    <input readonly class="q-input text-xs font-mono" style="max-width:15rem;"
                                           value="<?php echo htmlspecialchars($publicUrl); ?>">
                                    <button type="button"
                                            onclick="navigator.clipboard.writeText('<?php echo htmlspecialchars($publicUrl); ?>'); this.innerText = 'Kopyalandı';"
                                            class="q-btn q-btn--ghost q-btn--sm">
                                        Kopyala
                                    </button>
                                </div>
                            </td>
                            <td class="align-top">
                                <div class="q-stack q-stack--xs">
                                    <form method="POST" action="<?php echo getAdminUrl('payment-links/' . urlencode($link['link_id']) . '/toggle-reusable'); ?>">
                                        <?php echo csrf_field(); ?>
                                        <button type="submit" class="q-btn q-btn--ghost q-btn--sm">
                                            <?php echo (int)$link['is_single_use'] === 1 ? 'Çoklu kullanıma geçir' : 'Tek kullanıma geçir'; ?>
                                        </button>
                                    </form>
                                    <?php if ($isActive): ?>
                                    <form method="POST" action="<?php echo getAdminUrl('payment-links/' . urlencode($link['link_id']) . '/revoke'); ?>"
                                          onsubmit="return confirm('Bağlantıyı iptal etmek istediğinize emin misiniz?');">
                                        <?php echo csrf_field(); ?>
                                        <button type="submit" class="q-btn q-btn--danger q-btn--sm">İptal et</button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</div>
