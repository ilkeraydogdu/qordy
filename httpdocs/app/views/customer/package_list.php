<?php
/**
 * Customer Package List Page
 * Admin layout embedded package selection
 */

require_once __DIR__ . '/../../helpers/translations.php';

$packages = $packages ?? [];
$currentSubscription = $currentSubscription ?? null;
$customerId = $customerId ?? null;
$hasPendingBankTransfer = !empty($has_pending_bank_transfer);

$currentPackageId = null;
if ($currentSubscription && !empty($currentSubscription['package_id'])) {
    $currentPackageId = $currentSubscription['package_id'];
}
?>

<div class="p-3 sm:p-4 md:p-6 lg:p-8 flex-1 overflow-y-auto bg-[#f8fafc] animate-slide-up no-scrollbar w-full max-w-full overflow-x-hidden">
    
    <div class="max-w-5xl mx-auto space-y-5">
        
        <!-- Header -->
        <div class="bg-white p-5 sm:p-6 rounded-xl border border-slate-100 shadow-sm">
            <h1 class="text-xl sm:text-2xl lg:text-3xl font-black text-slate-900 tracking-tighter mb-1">Planlar</h1>
            <p class="text-sm text-slate-500">İşletmeniz için en uygun planı seçin ve hemen başlayın.</p>
        </div>

        <?php if ($hasPendingBankTransfer): ?>
        <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 flex items-start gap-3">
            <div class="w-9 h-9 bg-amber-100 rounded-lg flex items-center justify-center flex-shrink-0 text-amber-800">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l0 0m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div>
                <h3 class="text-sm font-bold text-amber-900">Bekleyen bir ödemeniz var</h3>
                <p class="text-xs text-amber-800 mt-0.5">Aynı hesapta, tamamlanmamış bir banka havalesi/EFT ya da yüklediğiniz dekont kaydı var. Bu iş bittikten sonra yeni plan seçebilirsiniz; aynı anda ikinci plan satın alma kapalı.</p>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($currentSubscription && !empty($currentSubscription['status']) && strtoupper($currentSubscription['status']) === 'ACTIVE'): ?>
        <div class="bg-green-50 border border-green-200 rounded-xl p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 bg-green-500 rounded-lg flex items-center justify-center flex-shrink-0">
                    <svg class="w-4.5 h-4.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-green-900">Aktif aboneliğiniz var</h3>
                    <p class="text-xs text-green-700">Mevcut planınızı görüntüleyebilirsiniz.</p>
                </div>
            </div>
            <a href="<?php echo BASE_URL; ?>/customer/subscription" class="inline-flex items-center justify-center gap-2 bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg text-sm transition-colors">
                Aboneliğimi Gör
            </a>
        </div>
        <?php endif; ?>
        
        <?php if (empty($packages)): ?>
        <div class="bg-white p-10 rounded-xl border border-slate-200 text-center">
            <div class="w-16 h-16 bg-slate-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
            </div>
            <h3 class="text-lg font-black text-slate-900 mb-1">Henüz plan bulunmamaktadır</h3>
            <p class="text-sm text-slate-500">Lütfen daha sonra tekrar deneyiniz.</p>
        </div>
        <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php foreach ($packages as $package): 
                $packageName = htmlspecialchars($package['name'] ?? 'Paket');
                $description = htmlspecialchars($package['description'] ?? '');
                $packageId = $package['package_id'] ?? $package['id'] ?? '';
                $featuresArray = $package['features_array'] ?? [];
                $yearlyDiscount = floatval($package['yearly_discount'] ?? 0);
                $isFeatured = !empty($package['is_featured']);
                $yearlyPrice = floatval($package['price_yearly'] ?? 0);
                $discountedYearlyPrice = floatval($package['discounted_price_yearly'] ?? $yearlyPrice);
                $isCurrentPackage = ($currentPackageId && $packageId === $currentPackageId);
                
                if (empty($packageId)) continue;
            ?>
            <div class="bg-white rounded-xl border-2 <?php echo $isFeatured ? 'border-indigo-400 shadow-lg shadow-indigo-50' : ($isCurrentPackage ? 'border-green-300' : 'border-slate-200'); ?> flex flex-col relative transition-all hover:shadow-lg hover:-translate-y-0.5">
                
                <?php if ($isFeatured): ?>
                <div class="absolute -top-3 left-1/2 -translate-x-1/2 z-10">
                    <span class="bg-indigo-600 text-white text-xs font-bold px-3 py-1 rounded-full whitespace-nowrap">Önerilen</span>
                </div>
                <?php endif; ?>
                
                <?php if ($isCurrentPackage): ?>
                <div class="absolute -top-3 right-4 z-10">
                    <span class="bg-green-600 text-white text-xs font-bold px-2.5 py-1 rounded-full">Aktif</span>
                </div>
                <?php endif; ?>
                
                <div class="p-5 flex flex-col flex-1">
                    <h3 class="text-lg font-black text-slate-900 mb-0.5"><?php echo $packageName; ?></h3>
                    <?php if (!empty($description)): ?>
                    <p class="text-xs text-slate-500 mb-3 line-clamp-2"><?php echo $description; ?></p>
                    <?php endif; ?>
                    
                    <?php if ($yearlyPrice > 0): ?>
                    <div class="mb-4">
                        <?php if ($yearlyDiscount > 0): ?>
                        <div class="flex items-center gap-2 mb-0.5">
                            <span class="text-xs text-slate-400 line-through">₺<?php echo number_format($yearlyPrice, 0, ',', '.'); ?></span>
                            <span class="text-xs text-green-600 font-bold bg-green-50 px-1.5 py-0.5 rounded">%<?php echo number_format($yearlyDiscount, 0); ?></span>
                        </div>
                        <?php endif; ?>
                        <span class="text-2xl font-black text-slate-900">₺<?php echo number_format($discountedYearlyPrice, 0, ',', '.'); ?></span>
                        <span class="text-sm text-slate-400">/yıl</span>
                        <?php $monthlyEquiv = round($discountedYearlyPrice / 12, 0); ?>
                        <?php if ($monthlyEquiv > 0): ?>
                        <div class="text-xs text-slate-400 mt-0.5">Aylık ~₺<?php echo number_format($monthlyEquiv, 0, ',', '.'); ?></div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($featuresArray)): ?>
                    <ul class="space-y-1.5 mb-4 flex-1">
                        <?php foreach (array_slice($featuresArray, 0, 5) as $feature): 
                            $featureText = is_array($feature) ? ($feature['name'] ?? $feature['title'] ?? '') : $feature;
                            if (empty($featureText)) continue;
                        ?>
                        <li class="flex items-start gap-2 text-xs text-slate-600">
                            <svg class="w-3.5 h-3.5 text-green-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                            <span><?php echo htmlspecialchars($featureText); ?></span>
                        </li>
                        <?php endforeach; ?>
                        <?php if (count($featuresArray) > 5): ?>
                        <li class="text-xs text-slate-400 pl-5">+<?php echo count($featuresArray) - 5; ?> özellik daha</li>
                        <?php endif; ?>
                    </ul>
                    <?php else: ?>
                    <div class="flex-1"></div>
                    <?php endif; ?>
                    
                    <div class="pt-3 border-t border-slate-100 mt-auto">
                        <?php if ($yearlyPrice > 0): ?>
                        <?php if ($hasPendingBankTransfer): ?>
                        <span class="block w-full text-center py-2.5 rounded-lg font-bold text-sm bg-slate-200 text-slate-500 cursor-not-allowed" title="Bekleyen ödeme bitene kadar yeni satın alma yok.">Beklemede</span>
                        <?php else: ?>
                        <a href="<?php echo BASE_URL; ?>/customer/packages/<?php echo urlencode($packageId); ?>/purchase?pricing_type=yearly" 
                           class="block w-full text-center py-2.5 rounded-lg font-bold text-sm transition-all <?php echo $isFeatured ? 'bg-indigo-600 hover:bg-indigo-700 text-white' : 'bg-slate-100 hover:bg-slate-200 text-slate-800'; ?>">
                            <?php echo $isCurrentPackage ? 'Yenile' : 'Hemen Başla'; ?>
                        </a>
                        <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
