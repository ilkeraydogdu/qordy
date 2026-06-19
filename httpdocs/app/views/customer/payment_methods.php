<?php
/**
 * Payment Methods Page for BUSINESS_MANAGER
 * Ödeme yöntemleri yönetimi
 *
 * Kart verisi çiğ olarak alınmaz. Ekleme, ödeme akışı sırasında
 * "Kartımı kaydet" onayı ile iyzico üzerinden yapılır.
 */

require_once __DIR__ . '/../../helpers/translations.php';
require_once __DIR__ . '/../../core/Security/CSRFManager.php';

$paymentMethods = $paymentMethods ?? [];
$csrfToken      = \App\Core\Security\CSRFManager::generateToken();
?>

<div class="p-3 sm:p-4 md:p-5 lg:p-6 h-full overflow-y-auto bg-[#f8fafc] space-y-4 sm:space-y-5 md:space-y-6 no-scrollbar w-full max-w-full overflow-x-hidden">

    <div class="q-card q-card--pad">
        <div class="flex items-center justify-between gap-3 flex-wrap">
            <div>
                <h1 class="text-2xl sm:text-3xl font-black text-slate-800 mb-2">Ödeme Yöntemleri</h1>
                <p class="text-slate-600">Kayıtlı kredi kartlarınızı ve varsayılan ödeme kaynağınızı yönetin.</p>
            </div>
            <a href="<?php echo BASE_URL; ?>/customer/packages"
               class="hidden sm:inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold transition-all">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Paket Satın Al / Kart Ekle
            </a>
        </div>
    </div>

    <?php if (empty($paymentMethods)): ?>
    <div class="bg-white p-12 rounded-2xl shadow-soft border border-slate-100 text-center">
        <div class="w-24 h-24 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <svg class="w-12 h-12 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
            </svg>
        </div>
        <h3 class="text-xl font-black text-slate-800 mb-2">Kayıtlı Kart Yok</h3>
        <p class="text-slate-600 mb-6">Henüz kayıtlı bir ödeme yönteminiz bulunmamaktadır. Kart, bir ödeme işlemi sırasında "Kartımı kaydet" seçeneği ile eklenir.</p>
        <a href="<?php echo BASE_URL; ?>/customer/packages"
           class="inline-flex items-center gap-2 px-8 py-3 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold transition-all">
            Paketleri Görüntüle
        </a>
    </div>
    <?php else: ?>
    <div class="space-y-4">
        <?php foreach ($paymentMethods as $method): ?>
        <div class="bg-white p-6 rounded-xl shadow-soft border border-slate-100 flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <div class="w-14 h-14 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-lg flex items-center justify-center">
                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                    </svg>
                </div>
                <div>
                    <div class="font-black text-slate-800">
                        **** **** **** <?php echo htmlspecialchars($method['last4'] ?? '****'); ?>
                    </div>
                    <div class="text-sm text-slate-500">
                        <?php echo htmlspecialchars(ucfirst($method['brand'] ?? '')); ?>
                        <?php if (!empty($method['exp_date'])): ?> &middot; Son kullanma: <?php echo htmlspecialchars($method['exp_date']); ?><?php endif; ?>
                        <?php if (!empty($method['gateway'])): ?> &middot; <?php echo htmlspecialchars($method['gateway']); ?><?php endif; ?>
                    </div>
                    <?php if (!empty($method['is_default'])): ?>
                    <span class="inline-block mt-1 px-2 py-0.5 text-xs font-bold bg-emerald-100 text-emerald-700 rounded">Varsayılan</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <?php if (empty($method['is_default'])): ?>
                <form method="POST" action="<?php echo BASE_URL; ?>/business/payment-methods/set-default" class="inline">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                    <input type="hidden" name="saved_card_id" value="<?php echo htmlspecialchars($method['saved_card_id']); ?>">
                    <button type="submit"
                            class="px-3 py-2 rounded-lg border border-slate-200 text-slate-700 font-bold text-xs sm:text-sm hover:bg-slate-50 transition-all">
                        Varsayılan Yap
                    </button>
                </form>
                <?php endif; ?>
                <form method="POST" action="<?php echo BASE_URL; ?>/business/payment-methods/delete" class="inline js-delete-card">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                    <input type="hidden" name="saved_card_id" value="<?php echo htmlspecialchars($method['saved_card_id']); ?>">
                    <button type="submit"
                            class="p-2 text-red-500 hover:bg-red-50 rounded-lg transition-all"
                            aria-label="Kartı sil">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                    </button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="bg-blue-50 border-2 border-blue-200 rounded-2xl p-6">
        <div class="flex gap-4">
            <div class="flex-shrink-0">
                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <div>
                <h3 class="font-black text-slate-800 mb-1">Güvenli Ödeme</h3>
                <p class="text-sm text-slate-600">
                    Kart bilgileriniz bizim sunucularımızda saklanmaz. Yalnızca iyzico tarafından
                    döndürülen kart tokenı, son 4 hane ve marka bilgisi tutulur. Kart eklemek için
                    paket satın alma ekranında "Kartımı kaydet" seçeneğini işaretlemeniz yeterlidir.
                </p>
            </div>
        </div>
    </div>

</div>

<script>
(function() {
    document.querySelectorAll('form.js-delete-card').forEach(function(frm) {
        frm.addEventListener('submit', async function(e) {
            e.preventDefault();
            let confirmed = false;
            const msg = 'Bu kartı kayıtlı kartlarınızdan kaldırmak istediğinize emin misiniz?';
            if (window.NotificationManager && window.NotificationManager.confirm) {
                confirmed = await window.NotificationManager.confirm(msg, 'Kartı Sil');
            } else {
                confirmed = confirm(msg);
            }
            if (confirmed) {
                frm.submit();
            }
        });
    });
})();
</script>
