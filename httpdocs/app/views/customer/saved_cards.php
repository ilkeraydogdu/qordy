<?php
/**
 * Customer Saved Cards Page
 * Müşteriler için kayıtlı kartlar sayfası
 */

require_once __DIR__ . '/../../helpers/translations.php';
require_once __DIR__ . '/../../core/Security/CSRFManager.php';

$savedCards = $savedCards ?? [];
$customer = $customer ?? null;
?>

<div class="p-3 sm:p-4 md:p-5 lg:p-6 h-full overflow-y-auto bg-[#f8fafc] space-y-3 sm:space-y-4 md:space-y-5 lg:space-y-6 animate-slide-up no-scrollbar w-full max-w-full overflow-x-hidden">
    
    <!-- Header -->
    <div class="bg-white p-4 sm:p-5 md:p-6 rounded-lg sm:rounded-xl md:rounded-2xl shadow-soft border border-slate-100">
        <h1 class="text-xl sm:text-2xl md:text-3xl font-black text-slate-800 mb-2 sm:mb-3">Kayıtlı Kartlar</h1>
        <p class="text-sm sm:text-base text-slate-600">Kayıtlı ödeme yöntemlerinizi yönetin.</p>
    </div>
    
    <!-- Saved Cards List -->
    <?php if (empty($savedCards)): ?>
    <div class="bg-white p-4 sm:p-5 md:p-6 rounded-lg sm:rounded-xl md:rounded-2xl shadow-soft border border-slate-100 text-center py-8 sm:py-10 md:py-12">
        <div class="text-5xl sm:text-6xl mb-4">💳</div>
        <p class="text-slate-500 text-sm sm:text-base mb-4">Henüz kayıtlı kartınız bulunmamaktadır.</p>
        <p class="text-xs sm:text-sm text-slate-400 mb-6">Ödeme yaparken kart bilgilerinizi kaydedebilirsiniz.</p>
    </div>
    <?php else: ?>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-5 md:gap-6">
        <?php foreach ($savedCards as $card): ?>
        <div class="bg-gradient-to-br from-slate-50 to-slate-100 p-4 sm:p-5 md:p-6 rounded-lg sm:rounded-xl border border-slate-200<?php echo $card['is_default'] ? ' ring-2 ring-indigo-500' : ''; ?>">
            <?php if ($card['is_default']): ?>
            <div class="mb-3">
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-black bg-indigo-600 text-white">
                    Varsayılan
                </span>
            </div>
            <?php endif; ?>
            
            <div class="mb-4 sm:mb-5">
                <div class="flex items-center justify-between mb-2">
                    <div class="text-sm sm:text-base font-black text-slate-700">
                        <?php 
                        $brandLabels = [
                            'visa' => 'Visa',
                            'mastercard' => 'Mastercard',
                            'amex' => 'American Express',
                            'troy' => 'Troy'
                        ];
                        echo $brandLabels[strtolower($card['card_brand'] ?? '')] ?? $card['card_brand'] ?? 'Kart';
                        ?>
                    </div>
                    <div class="text-xs sm:text-sm text-slate-500">
                        <?php echo htmlspecialchars($card['gateway'] ?? 'iyzico'); ?>
                    </div>
                </div>
                
                <div class="text-lg sm:text-xl md:text-2xl font-black text-slate-800 mb-2">
                    •••• •••• •••• <?php echo htmlspecialchars($card['card_last4'] ?? '****'); ?>
                </div>
                
                <?php if ($card['card_expiry_month'] && $card['card_expiry_year']): ?>
                <div class="text-xs sm:text-sm text-slate-500">
                    Son Kullanma: <?php echo str_pad($card['card_expiry_month'], 2, '0', STR_PAD_LEFT); ?>/<?php echo substr($card['card_expiry_year'], -2); ?>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="flex gap-2 sm:gap-3">
                <?php if (!$card['is_default']): ?>
                <form method="POST" action="<?php echo BASE_URL; ?>/customer/saved-cards/<?php echo htmlspecialchars($card['saved_card_id']); ?>/set-default" class="flex-1">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(\App\Core\Security\CSRFManager::generateToken()); ?>">
                    <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-3 rounded-lg text-xs sm:text-sm transition-colors">
                        Varsayılan Yap
                    </button>
                </form>
                <?php endif; ?>
                
                <form method="POST" action="<?php echo BASE_URL; ?>/customer/saved-cards/<?php echo htmlspecialchars($card['saved_card_id']); ?>/delete" class="flex-1">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(\App\Core\Security\CSRFManager::generateToken()); ?>">
                    <button type="button" onclick="handleDeleteSavedCard(this)"
                            class="w-full bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-3 rounded-lg text-xs sm:text-sm transition-colors">
                        Sil
                    </button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    
    <!-- Info Box -->
    <div class="bg-blue-50 p-4 sm:p-5 rounded-lg sm:rounded-xl border border-blue-200">
        <div class="flex items-start gap-3">
            <svg class="w-5 h-5 text-blue-600 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <div class="text-sm sm:text-base text-blue-800">
                <p class="font-bold mb-1">Kart Bilgileri Güvenli</p>
                <p class="text-xs sm:text-sm">Kart bilgileriniz güvenli bir şekilde saklanmaktadır. Tam kart numarası saklanmaz, sadece son 4 hanesi görüntülenir.</p>
            </div>
        </div>
    </div>
    
</div>
<script>
async function handleDeleteSavedCard(btn) {
    let confirmed = false;
    if (window.NotificationManager && window.NotificationManager.confirm) {
        confirmed = await window.NotificationManager.confirm('Bu kartı silmek istediğinizden emin misiniz?', 'Kartı Sil');
    } else {
        confirmed = confirm('Bu kartı silmek istediğinizden emin misiniz?');
    }
    if (confirmed) {
        btn.closest('form').submit();
    }
}
</script>
