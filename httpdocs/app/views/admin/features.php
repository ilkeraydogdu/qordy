<?php
$features = $features ?? [];
$baseUrl = BASE_URL;

require_once __DIR__ . '/../../core/Authorization.php';
if (!function_exists('getAdminUrl')) {
    require_once __DIR__ . '/../../helpers/url_helper.php';
}
$auth = \App\Core\Authorization::getInstance();
$canEdit = $auth->hasPermission('settings.edit');
$isSuperAdmin = $is_super_admin ?? false;
$adminPrefix = $isSuperAdmin ? '/qodmin' : '/business';
$apiPrefix = $isSuperAdmin ? '/api/qodmin' : '/api/business';

$featureIcons = [
    'call_waiter' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>',
    'request_bill' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>',
    'online_payment' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>',
    'ingredient_customization' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/></svg>',
    'order_tracking' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>',
    'customer_presence_tracking' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>',
    'device_fingerprint' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11c0 3.517-1.009 6.799-2.753 9.571m-3.44-2.04l.054-.09A13.916 13.916 0 008 11a4 4 0 118 0c0 1.017-.07 2.019-.203 3m-2.118 6.844A21.88 21.88 0 0015.171 17m3.839 1.132c.645-2.266.99-4.659.99-7.132A8 8 0 008 4.07M3 15.364c.64-1.319 1-2.8 1-4.364 0-1.457.39-2.823 1.07-4"/></svg>',
];

$featureDescriptions = [
    'customer_presence_tracking' => 'Müşterinin masada olup olmadığını konum ve IP bilgisi ile doğrular. Aktif edildiğinde müşteri ilk QR okuttuğunda konum izni istenir.',
    'device_fingerprint' => 'Oturumları cihaza bağlar. Aynı cihazdan erişimi doğrular, farklı cihazlardan erişimi engeller. Ek güvenlik katmanı sağlar.',
];

$featureLabels = [
    'call_waiter' => 'Garson Çağır',
    'request_bill' => 'Hesap İste',
    'online_payment' => 'Online Ödeme',
    'ingredient_customization' => 'İçerik Özelleştirme',
    'order_tracking' => 'Sipariş Takibi',
    'customer_presence_tracking' => 'Müşteri Konum Takibi',
    'device_fingerprint' => 'Cihaz Parmak İzi',
];

$featureCategories = [
    'Müşteri Deneyimi' => ['call_waiter', 'request_bill', 'order_tracking', 'ingredient_customization'],
    'Ödeme' => ['online_payment'],
    'Güvenlik & Gizlilik' => ['customer_presence_tracking', 'device_fingerprint'],
];
?>

<div class="q-page q-biz-theme animate-slide-up">
  <div class="q-container">
    <h1 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-black mb-4 sm:mb-6 md:mb-8 lg:mb-10 tracking-tighter flex items-center gap-2 sm:gap-3 md:gap-4 lg:gap-6">
        <svg class="w-6 h-6 sm:w-7 sm:h-7 md:w-8 md:h-8 lg:w-10 lg:h-10 text-slate-900" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
        </svg>
        <span class="text-base sm:text-lg md:text-xl lg:text-2xl">Özellik Yönetimi</span>
    </h1>

    <?php if (!$canEdit): ?>
    <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 mb-6">
        <div class="flex items-center gap-3">
            <svg class="w-5 h-5 text-amber-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
            <span class="text-sm font-bold text-amber-800">Bu sayfayı görüntüleyebilirsiniz ancak değişiklik yapmak için yetkiniz bulunmamaktadır.</span>
        </div>
    </div>
    <?php endif; ?>

    <?php
    $featuresByKey = [];
    foreach ($features as $f) {
        $featuresByKey[$f['feature_key']] = $f;
    }
    ?>

    <?php foreach ($featureCategories as $categoryName => $categoryKeys): ?>
    <div class="mb-8">
        <h2 class="text-lg sm:text-xl font-black text-slate-800 mb-4 flex items-center gap-2">
            <?php if ($categoryName === 'Müşteri Deneyimi'): ?>
                <span class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center">
                    <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </span>
            <?php elseif ($categoryName === 'Ödeme'): ?>
                <span class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center">
                    <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
            <?php else: ?>
                <span class="w-8 h-8 bg-purple-100 rounded-lg flex items-center justify-center">
                    <svg class="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                </span>
            <?php endif; ?>
            <?php echo htmlspecialchars($categoryName); ?>
        </h2>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <?php foreach ($categoryKeys as $key):
                $feature = $featuresByKey[$key] ?? null;
                if (!$feature) continue;
                $isEnabled = !empty($feature['is_enabled']);
                $icon = $featureIcons[$key] ?? '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>';
                $label = $featureLabels[$key] ?? ($feature['feature_name'] ?? $key);
                $desc = $featureDescriptions[$key] ?? ($feature['description'] ?? '');
            ?>
            <div class="bg-white rounded-2xl border border-slate-200 p-5 hover:shadow-lg transition-all duration-200 feature-card" data-feature="<?php echo htmlspecialchars($key); ?>">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex items-start gap-3 flex-1">
                        <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0 <?php echo $isEnabled ? 'bg-indigo-100 text-indigo-600' : 'bg-slate-100 text-slate-400'; ?> transition-colors duration-200" id="icon-<?php echo htmlspecialchars($key); ?>">
                            <?php echo $icon; ?>
                        </div>
                        <div class="flex-1">
                            <h3 class="text-sm font-bold text-slate-800"><?php echo htmlspecialchars($label); ?></h3>
                            <?php if ($desc): ?>
                            <p class="text-xs text-slate-500 mt-1 leading-relaxed"><?php echo htmlspecialchars($desc); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <label class="relative inline-flex items-center cursor-pointer flex-shrink-0">
                        <input type="checkbox" 
                            class="sr-only peer feature-toggle" 
                            data-feature="<?php echo htmlspecialchars($key); ?>"
                            <?php echo $isEnabled ? 'checked' : ''; ?>
                            <?php echo $canEdit ? '' : 'disabled'; ?>>
                        <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600 <?php echo $canEdit ? '' : 'opacity-60'; ?>"></div>
                    </label>
                </div>
                
                <?php if ($key === 'customer_presence_tracking'): ?>
                <div class="mt-3 pt-3 border-t border-slate-100">
                    <div class="flex items-center gap-2 text-xs text-slate-500">
                        <svg class="w-3.5 h-3.5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span>Aktif edildiğinde müşteriden ilk QR okutmada konum izni istenir. IP adresi otomatik kaydedilir.</span>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($key === 'device_fingerprint'): ?>
                <div class="mt-3 pt-3 border-t border-slate-100">
                    <div class="flex items-center gap-2 text-xs text-slate-500">
                        <svg class="w-3.5 h-3.5 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span>Bu özellik ek bir güvenlik katmanı sağlar. Aktif edildiğinde oturum sadece aynı cihazdan kullanılabilir.</span>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>

  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const toggles = document.querySelectorAll('.feature-toggle');
    
    toggles.forEach(toggle => {
        toggle.addEventListener('change', function() {
            const featureKey = this.dataset.feature;
            const enabled = this.checked;
            const iconEl = document.getElementById('icon-' + featureKey);
            
            if (iconEl) {
                iconEl.className = iconEl.className.replace(/bg-\w+-100 text-\w+-\d+|bg-slate-100 text-slate-400/g, '');
                if (enabled) {
                    iconEl.classList.add('bg-indigo-100', 'text-indigo-600');
                } else {
                    iconEl.classList.add('bg-slate-100', 'text-slate-400');
                }
            }
            
            fetch('<?php echo $baseUrl . $apiPrefix; ?>/feature/toggle', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || ''
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    feature_key: featureKey,
                    enabled: enabled
                })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    if (window.NotificationManager) window.NotificationManager.success(enabled ? 'Özellik aktif edildi' : 'Özellik devre dışı bırakıldı');
                } else {
                    toggle.checked = !enabled;
                    if (window.NotificationManager) window.NotificationManager.error(data.error || 'Güncelleme başarısız');
                }
            })
            .catch(() => {
                toggle.checked = !enabled;
                if (window.NotificationManager) window.NotificationManager.error('Bağlantı hatası');
            });
        });
    });
});

</script>
