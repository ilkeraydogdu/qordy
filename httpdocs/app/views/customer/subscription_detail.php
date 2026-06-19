<?php
/**
 * Customer Subscription Detail Page
 * Müşteriler için aktif abonelik detay sayfası
 */

require_once __DIR__ . '/../../helpers/translations.php';
require_once __DIR__ . '/../../core/Security/CSRFManager.php';

$subscription = $subscription ?? null;
$package = $package ?? null;
$payments = $payments ?? [];
$customer = $customer ?? null;
?>

<div class="p-3 sm:p-4 md:p-5 lg:p-6 h-full overflow-y-auto bg-[#f8fafc] space-y-3 sm:space-y-4 md:space-y-5 lg:space-y-6 animate-slide-up no-scrollbar w-full max-w-full overflow-x-hidden">
    
    <!-- Header -->
    <div class="bg-white p-4 sm:p-5 md:p-6 rounded-lg sm:rounded-xl md:rounded-2xl shadow-soft border border-slate-100">
        <h1 class="text-xl sm:text-2xl md:text-3xl font-black text-slate-800 mb-2 sm:mb-3">Abonelik Detayları</h1>
        <p class="text-sm sm:text-base text-slate-600">Aktif aboneliğinizin detaylarını görüntüleyin ve yönetin.</p>
    </div>
    
    <?php if (!$subscription || !$package): ?>
    <div class="bg-white p-4 sm:p-5 md:p-6 rounded-lg sm:rounded-xl md:rounded-2xl shadow-soft border border-slate-100 text-center py-8 sm:py-10 md:py-12">
        <p class="text-slate-500 text-sm sm:text-base">Aktif aboneliğiniz bulunmamaktadır.</p>
        <a href="<?php echo BASE_URL; ?>/business/dashboard" class="inline-block mt-4 bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 sm:py-3 px-4 sm:px-5 rounded-lg sm:rounded-xl transition-colors shadow-lg hover:shadow-xl">
            Paketleri Görüntüle
        </a>
    </div>
    <?php else: ?>
    
    <!-- Subscription Info -->
    <div class="bg-gradient-to-br from-green-50 to-emerald-50 p-4 sm:p-5 md:p-6 rounded-lg sm:rounded-xl md:rounded-2xl border border-green-200">
        <div class="flex items-start justify-between mb-4">
            <div>
                <h2 class="text-lg sm:text-xl md:text-2xl font-black text-green-800 mb-2"><?php echo htmlspecialchars($package['name'] ?? 'Paket'); ?></h2>
                <p class="text-sm sm:text-base text-green-700"><?php echo htmlspecialchars($package['description'] ?? ''); ?></p>
            </div>
            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs sm:text-sm font-black bg-green-600 text-white">
                Aktif
            </span>
        </div>
        
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-6 mt-4 sm:mt-6">
            <div class="bg-white p-3 sm:p-4 rounded-lg sm:rounded-xl">
                <div class="text-xs sm:text-sm text-slate-500 font-bold mb-1">Başlangıç Tarihi</div>
                <div class="text-sm sm:text-base md:text-lg font-black text-slate-800">
                    <?php 
                    if ($subscription['start_date']) {
                        echo date('d.m.Y H:i', strtotime($subscription['start_date']));
                    } else {
                        echo '-';
                    }
                    ?>
                </div>
            </div>
            
            <div class="bg-white p-3 sm:p-4 rounded-lg sm:rounded-xl">
                <div class="text-xs sm:text-sm text-slate-500 font-bold mb-1">Bitiş Tarihi</div>
                <div class="text-sm sm:text-base md:text-lg font-black text-slate-800">
                    <?php 
                    if ($subscription['end_date']) {
                        echo date('d.m.Y H:i', strtotime($subscription['end_date']));
                    } else {
                        echo 'Süresiz';
                    }
                    ?>
                </div>
            </div>
            
            <div class="bg-white p-3 sm:p-4 rounded-lg sm:rounded-xl">
                <div class="text-xs sm:text-sm text-slate-500 font-bold mb-1">Fiyatlandırma</div>
                <div class="text-sm sm:text-base md:text-lg font-black text-slate-800">
                    <?php 
                    $pricingLabels = [
                        'one_time' => 'Tek Seferlik',
                        'monthly' => 'Aylık',
                        'yearly' => 'Yıllık'
                    ];
                    echo $pricingLabels[$subscription['pricing_type']] ?? $subscription['pricing_type'];
                    ?>
                </div>
            </div>
            
            <div class="bg-white p-3 sm:p-4 rounded-lg sm:rounded-xl">
                <div class="text-xs sm:text-sm text-slate-500 font-bold mb-1">Otomatik Yenileme</div>
                <div class="text-sm sm:text-base md:text-lg font-black text-slate-800">
                    <?php echo $subscription['auto_renew'] ? 'Aktif' : 'Pasif'; ?>
                </div>
            </div>
            
            <?php if ($subscription['auto_renew'] && $subscription['next_billing_date']): ?>
            <div class="bg-white p-3 sm:p-4 rounded-lg sm:rounded-xl">
                <div class="text-xs sm:text-sm text-slate-500 font-bold mb-1">Sonraki Ödeme Tarihi</div>
                <div class="text-sm sm:text-base md:text-lg font-black text-slate-800">
                    <?php echo date('d.m.Y H:i', strtotime($subscription['next_billing_date'])); ?>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="bg-white p-3 sm:p-4 rounded-lg sm:rounded-xl">
                <div class="text-xs sm:text-sm text-slate-500 font-bold mb-1">Ödeme Tutarı</div>
                <div class="text-sm sm:text-base md:text-lg font-black text-indigo-600">
                    ₺<?php echo number_format($subscription['amount'] ?? 0, 2, ',', '.'); ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Package Features -->
    <?php 
    $packageService = \App\Core\DependencyFactory::getPackageService();
    $featuresArray = $packageService->formatFeaturesForDisplay($package['features'] ?? null);
    if (!empty($featuresArray)):
    ?>
    <div class="bg-white p-4 sm:p-5 md:p-6 rounded-lg sm:rounded-xl md:rounded-2xl shadow-soft border border-slate-100">
        <h2 class="text-lg sm:text-xl md:text-2xl font-black text-slate-800 mb-4 sm:mb-5">Paket Özellikleri</h2>
        <ul class="space-y-2 sm:space-y-3">
            <?php foreach ($featuresArray as $feature): ?>
            <?php 
            $featureText = is_array($feature) ? ($feature['name'] ?? $feature['title'] ?? '') : $feature;
            if (empty($featureText)) continue;
            ?>
            <li class="flex items-start gap-2 sm:gap-3 text-sm sm:text-base text-slate-700">
                <svg class="w-5 h-5 text-green-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                <span><?php echo htmlspecialchars($featureText); ?></span>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>
    
    <!-- Recent Payments -->
    <?php if (!empty($payments)): ?>
    <div class="bg-white p-4 sm:p-5 md:p-6 rounded-lg sm:rounded-xl md:rounded-2xl shadow-soft border border-slate-100">
        <h2 class="text-lg sm:text-xl md:text-2xl font-black text-slate-800 mb-4 sm:mb-5">Son Ödemeler</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-sm sm:text-base">
                <thead>
                    <tr class="border-b border-slate-200">
                        <th class="text-left py-2 sm:py-3 px-2 sm:px-4 font-black text-slate-700">Tarih</th>
                        <th class="text-left py-2 sm:py-3 px-2 sm:px-4 font-black text-slate-700">Tutar</th>
                        <th class="text-left py-2 sm:py-3 px-2 sm:px-4 font-black text-slate-700">Yöntem</th>
                        <th class="text-left py-2 sm:py-3 px-2 sm:px-4 font-black text-slate-700">Durum</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($payments, 0, 5) as $payment): ?>
                    <tr class="border-b border-slate-100 hover:bg-slate-50">
                        <td class="py-2 sm:py-3 px-2 sm:px-4 text-slate-600">
                            <?php 
                            if ($payment['payment_date']) {
                                echo date('d.m.Y H:i', strtotime($payment['payment_date']));
                            } elseif ($payment['created_at']) {
                                echo date('d.m.Y H:i', strtotime($payment['created_at']));
                            } else {
                                echo '-';
                            }
                            ?>
                        </td>
                        <td class="py-2 sm:py-3 px-2 sm:px-4 font-bold text-slate-800">
                            ₺<?php echo number_format($payment['amount'] ?? 0, 2, ',', '.'); ?>
                        </td>
                        <td class="py-2 sm:py-3 px-2 sm:px-4 text-slate-600">
                            <?php 
                            $methodLabels = [
                                'iyzico' => 'iyzico',
                                'manual' => 'Manuel',
                                'gateway' => 'Online Ödeme',
                                'bank_transfer' => 'Havale/EFT',
                                'saved_card' => 'Kayıtlı Kart'
                            ];
                            echo $methodLabels[$payment['payment_method']] ?? $payment['payment_method'];
                            ?>
                        </td>
                        <td class="py-2 sm:py-3 px-2 sm:px-4">
                            <?php 
                            $statusColors = [
                                'completed' => 'bg-green-100 text-green-800',
                                'pending' => 'bg-yellow-100 text-yellow-800',
                                'failed' => 'bg-red-100 text-red-800',
                                'refunded' => 'bg-slate-100 text-slate-800'
                            ];
                            $statusColor = $statusColors[$payment['payment_status']] ?? 'bg-slate-100 text-slate-800';
                            $statusLabels = [
                                'completed' => 'Tamamlandı',
                                'pending' => 'Beklemede',
                                'failed' => 'Başarısız',
                                'refunded' => 'İade Edildi'
                            ];
                            $statusLabel = $statusLabels[$payment['payment_status']] ?? $payment['payment_status'];
                            ?>
                            <span class="inline-flex items-center px-2 py-1 rounded text-xs font-black <?php echo $statusColor; ?>">
                                <?php echo $statusLabel; ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="mt-4 sm:mt-5">
            <a href="<?php echo BASE_URL; ?>/customer/payment-history" class="text-indigo-600 hover:text-indigo-700 font-bold text-sm sm:text-base">
                Tüm Ödemeleri Görüntüle →
            </a>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Actions -->
    <div class="bg-white p-4 sm:p-5 md:p-6 rounded-lg sm:rounded-xl md:rounded-2xl shadow-soft border border-slate-100">
        <h2 class="text-lg sm:text-xl md:text-2xl font-black text-slate-800 mb-4 sm:mb-5">İşlemler</h2>
        <div class="flex flex-col sm:flex-row gap-3 sm:gap-4">
            <form method="POST" action="<?php echo BASE_URL; ?>/customer/subscription/cancel" class="flex-1">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(\App\Core\Security\CSRFManager::generateToken()); ?>">
                <button type="button" onclick="handleCancelSubscription(this)"
                        class="w-full bg-red-600 hover:bg-red-700 text-white font-bold py-2 sm:py-3 px-4 sm:px-5 rounded-lg sm:rounded-xl transition-colors shadow-lg hover:shadow-xl">
                    Aboneliği İptal Et
                </button>
            </form>
            <a href="<?php echo BASE_URL; ?>/business/dashboard" 
               class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 sm:py-3 px-4 sm:px-5 rounded-lg sm:rounded-xl transition-colors shadow-lg hover:shadow-xl text-center">
                Paketleri Görüntüle
            </a>
        </div>
    </div>
    
    <?php endif; ?>
    
</div>
<script>
async function handleCancelSubscription(btn) {
    let confirmed = false;
    if (window.NotificationManager && window.NotificationManager.confirm) {
        confirmed = await window.NotificationManager.confirm('Aboneliğinizi iptal etmek istediğinizden emin misiniz?', 'Abonelik İptali');
    } else {
        confirmed = confirm('Aboneliğinizi iptal etmek istediğinizden emin misiniz?');
    }
    if (confirmed) {
        btn.closest('form').submit();
    }
}
</script>
