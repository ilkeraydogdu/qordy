<?php
/**
 * Customer Dashboard for BUSINESS_MANAGER role
 */

require_once __DIR__ . '/../../helpers/translations.php';

$user = $user ?? [];
$customer = $customer ?? null;
$packages = $packages ?? [];
$subscription = $subscription ?? null;
$stats = $stats ?? ['active_users' => 0, 'total_orders' => 0, 'monthly_orders' => 0, 'monthly_revenue' => 0];
$showPackageSelection = $showPackageSelection ?? false;

$userEmail = $user['name'] ?? '';
$firstName = $customer['first_name'] ?? '';
$lastName = $customer['last_name'] ?? '';
$userName = trim($firstName . ' ' . $lastName);
if (empty($userName)) {
    $userName = $userEmail;
}

$hasSubscription = !empty($subscription);
$pendingBankTransfer = $pendingBankTransfer ?? null;
$showPackageSelection = $pendingBankTransfer ? false : ($showPackageSelection ?? false);
?>

<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/business-theme.css?v=<?php echo @filemtime(dirname(__DIR__, 3) . '/public/assets/css/business-theme.css'); ?>">
<div class="q-page q-biz-theme animate-slide-up min-w-0">
  <div class="q-container q-stack q-stack--lg min-w-0">
    <?php if (!$hasSubscription || $showPackageSelection): ?>
        <?php if ($pendingBankTransfer): ?>
        <div class="bg-amber-50 border border-amber-200 rounded-xl p-5 sm:p-6 mb-5">
            <div class="flex items-start gap-4">
                <div class="flex-shrink-0 w-10 h-10 bg-amber-500 rounded-xl flex items-center justify-center">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div class="flex-1">
                    <h3 class="text-lg font-black text-amber-900 mb-1">Ödemeniz Onay Bekliyor</h3>
                    <p class="text-sm text-amber-800">Havale/EFT ödemeniz incelenmektedir. Onaylandığında paketiniz otomatik aktif olacaktır.</p>
                    <?php if (!empty($pendingBankTransfer['amount'])): ?>
                    <p class="text-sm text-amber-700 mt-1 font-bold">Tutar: ₺<?php echo number_format($pendingBankTransfer['amount'], 2, ',', '.'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php else: ?>

        <!-- Welcoming package selection - not scary warning -->
        <div class="max-w-4xl mx-auto space-y-5">
            
            <!-- Welcome header -->
            <div class="text-center py-6">
                <div class="w-16 h-16 bg-indigo-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                </div>
                <h2 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight mb-2">Hoş Geldiniz<?php echo !empty($userName) ? ', ' . htmlspecialchars($userName) : ''; ?>!</h2>
                <p class="text-slate-500 text-sm sm:text-base max-w-md mx-auto">İşletmenizi dijitalleştirmek için bir plan seçin. Hemen başlayın.</p>
            </div>
            
            <!-- Package cards -->
            <?php if (!empty($packages)): ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach ($packages as $pkg): 
                    $packageId = $pkg['package_id'] ?? $pkg['id'] ?? '';
                    $packageName = htmlspecialchars($pkg['name'] ?? 'Paket');
                    $description = htmlspecialchars($pkg['description'] ?? '');
                    $yearlyPrice = floatval($pkg['price_yearly'] ?? 0);
                    $discountedYearly = floatval($pkg['discounted_price_yearly'] ?? $yearlyPrice);
                    $yearlyDiscount = floatval($pkg['yearly_discount'] ?? 0);
                    $featuresArray = $pkg['features_array'] ?? [];
                    $isFeatured = !empty($pkg['is_featured']);
                    if (empty($packageId)) continue;
                ?>
                <div class="bg-white rounded-xl border-2 <?php echo $isFeatured ? 'border-indigo-400 shadow-lg shadow-indigo-100' : 'border-slate-200'; ?> p-5 flex flex-col relative transition-all hover:shadow-lg hover:-translate-y-0.5">
                    <?php if ($isFeatured): ?>
                    <div class="absolute -top-3 left-1/2 -translate-x-1/2">
                        <span class="bg-indigo-600 text-white text-xs font-bold px-3 py-1 rounded-full whitespace-nowrap">Önerilen</span>
                    </div>
                    <?php endif; ?>
                    
                    <h3 class="text-lg font-black text-slate-900 mb-1"><?php echo $packageName; ?></h3>
                    <?php if (!empty($description)): ?>
                    <p class="text-xs text-slate-500 mb-3 line-clamp-2"><?php echo $description; ?></p>
                    <?php endif; ?>
                    
                    <?php if ($yearlyPrice > 0): ?>
                    <div class="mb-3">
                        <?php if ($yearlyDiscount > 0): ?>
                        <span class="text-xs text-slate-400 line-through">₺<?php echo number_format($yearlyPrice, 0, ',', '.'); ?></span>
                        <span class="text-xs text-green-600 font-bold ml-1">%<?php echo number_format($yearlyDiscount, 0); ?> indirim</span>
                        <br>
                        <?php endif; ?>
                        <span class="text-2xl font-black text-slate-900">₺<?php echo number_format($discountedYearly, 0, ',', '.'); ?></span>
                        <span class="text-sm text-slate-400">/yıl</span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($featuresArray)): ?>
                    <ul class="space-y-1.5 mb-4 flex-1">
                        <?php foreach (array_slice($featuresArray, 0, 4) as $feature): 
                            $featureText = is_array($feature) ? ($feature['name'] ?? $feature['title'] ?? '') : $feature;
                            if (empty($featureText)) continue;
                        ?>
                        <li class="flex items-start gap-2 text-xs text-slate-600">
                            <svg class="w-3.5 h-3.5 text-green-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                            <span><?php echo htmlspecialchars($featureText); ?></span>
                        </li>
                        <?php endforeach; ?>
                        <?php if (count($featuresArray) > 4): ?>
                        <li class="text-xs text-slate-400 pl-5">+<?php echo count($featuresArray) - 4; ?> özellik daha</li>
                        <?php endif; ?>
                    </ul>
                    <?php else: ?>
                    <div class="flex-1"></div>
                    <?php endif; ?>
                    
                    <a href="<?php echo BASE_URL; ?>/customer/packages/<?php echo urlencode($packageId); ?>/purchase?pricing_type=yearly" 
                       class="block w-full text-center py-2.5 rounded-lg font-bold text-sm transition-all <?php echo $isFeatured ? 'bg-indigo-600 hover:bg-indigo-700 text-white' : 'bg-slate-100 hover:bg-slate-200 text-slate-700'; ?>">
                        Hemen Başla
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="text-center">
                <a href="<?php echo BASE_URL; ?>/customer/packages/list" class="text-sm font-bold text-indigo-600 hover:text-indigo-700">
                    Tüm planları karşılaştır →
                </a>
            </div>
            <?php endif; ?>
            
            <!-- Quick guide -->
            <div class="bg-white rounded-xl border border-slate-200 p-5">
                <h3 class="text-base font-black text-slate-800 mb-3">Nasıl Başlarım?</h3>
                <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                    <div class="flex items-start gap-2.5">
                        <div class="w-6 h-6 bg-indigo-100 rounded-lg flex items-center justify-center flex-shrink-0">
                            <span class="text-xs font-black text-indigo-600">1</span>
                        </div>
                        <div>
                            <h4 class="text-xs font-bold text-slate-900">Plan Seçin</h4>
                            <p class="text-[11px] text-slate-500 mt-0.5">İşletmenize uygun planı seçin.</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-2.5">
                        <div class="w-6 h-6 bg-indigo-100 rounded-lg flex items-center justify-center flex-shrink-0">
                            <span class="text-xs font-black text-indigo-600">2</span>
                        </div>
                        <div>
                            <h4 class="text-xs font-bold text-slate-900">Menü Oluşturun</h4>
                            <p class="text-[11px] text-slate-500 mt-0.5">Ürünlerinizi ekleyin.</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-2.5">
                        <div class="w-6 h-6 bg-indigo-100 rounded-lg flex items-center justify-center flex-shrink-0">
                            <span class="text-xs font-black text-indigo-600">3</span>
                        </div>
                        <div>
                            <h4 class="text-xs font-bold text-slate-900">QR Kod Alın</h4>
                            <p class="text-[11px] text-slate-500 mt-0.5">Masalarınız için QR oluşturun.</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-2.5">
                        <div class="w-6 h-6 bg-green-100 rounded-lg flex items-center justify-center flex-shrink-0">
                            <svg class="w-3 h-3 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                        </div>
                        <div>
                            <h4 class="text-xs font-bold text-slate-900">Kullanmaya Başlayın</h4>
                            <p class="text-[11px] text-slate-500 mt-0.5">Sipariş almaya hazırsınız!</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <?php endif; ?>
        
    <?php else: ?>
        <!-- Has Subscription: Dashboard with Stats -->
        
        <div class="space-y-4 sm:space-y-5">
            <div class="bg-white p-5 sm:p-6 rounded-xl border border-slate-100 shadow-sm">
                <h2 class="text-xl sm:text-2xl lg:text-3xl font-black text-slate-900 tracking-tighter mb-1">Hoş Geldiniz, <?php echo htmlspecialchars($userName); ?></h2>
                <p class="text-sm text-slate-500">İşletmenizin genel durumunu buradan takip edebilirsiniz.</p>
            </div>
            
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
                <div class="bg-white p-4 sm:p-5 rounded-xl border border-slate-100 shadow-sm">
                    <div class="w-9 h-9 bg-blue-50 rounded-lg flex items-center justify-center mb-3">
                        <svg class="w-4.5 h-4.5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                    </div>
                    <div class="text-2xl sm:text-3xl font-black text-slate-900"><?php echo number_format($stats['active_users']); ?></div>
                    <div class="text-xs text-slate-500 font-medium mt-0.5">Aktif Kullanıcı</div>
                </div>
                <div class="bg-white p-4 sm:p-5 rounded-xl border border-slate-100 shadow-sm">
                    <div class="w-9 h-9 bg-green-50 rounded-lg flex items-center justify-center mb-3">
                        <svg class="w-4.5 h-4.5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    </div>
                    <div class="text-2xl sm:text-3xl font-black text-slate-900"><?php echo number_format($stats['total_orders']); ?></div>
                    <div class="text-xs text-slate-500 font-medium mt-0.5">Toplam İşlem</div>
                </div>
                <div class="bg-white p-4 sm:p-5 rounded-xl border border-slate-100 shadow-sm">
                    <div class="w-9 h-9 bg-purple-50 rounded-lg flex items-center justify-center mb-3">
                        <svg class="w-4.5 h-4.5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                    </div>
                    <div class="text-2xl sm:text-3xl font-black text-slate-900"><?php echo number_format($stats['monthly_orders']); ?></div>
                    <div class="text-xs text-slate-500 font-medium mt-0.5">Bu Ay Sipariş</div>
                </div>
                <div class="bg-white p-4 sm:p-5 rounded-xl border border-slate-100 shadow-sm">
                    <div class="w-9 h-9 bg-amber-50 rounded-lg flex items-center justify-center mb-3">
                        <svg class="w-4.5 h-4.5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <div class="text-2xl sm:text-3xl font-black text-slate-900"><?php echo number_format($stats['monthly_revenue'], 2); ?> ₺</div>
                    <div class="text-xs text-slate-500 font-medium mt-0.5">Aylık Gelir</div>
                </div>
            </div>
            
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                <!-- Quick Actions -->
                <div class="lg:col-span-2 bg-white p-5 rounded-xl border border-slate-100 shadow-sm">
                    <h3 class="text-base font-black text-slate-900 mb-3">Hızlı Erişim</h3>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                        <a href="<?php echo BASE_URL; ?>/business/profile" class="group flex flex-col items-center p-3 rounded-xl border border-slate-200 hover:border-indigo-300 hover:bg-indigo-50/50 transition-all">
                            <div class="w-10 h-10 bg-indigo-50 rounded-lg flex items-center justify-center mb-2 group-hover:bg-indigo-500 transition-all">
                                <svg class="w-5 h-5 text-indigo-600 group-hover:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            </div>
                            <span class="text-xs font-bold text-slate-700 group-hover:text-indigo-600">Hesabım</span>
                        </a>
                        <a href="<?php echo BASE_URL; ?>/business/packages" class="group flex flex-col items-center p-3 rounded-xl border border-slate-200 hover:border-green-300 hover:bg-green-50/50 transition-all">
                            <div class="w-10 h-10 bg-green-50 rounded-lg flex items-center justify-center mb-2 group-hover:bg-green-500 transition-all">
                                <svg class="w-5 h-5 text-green-600 group-hover:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                            </div>
                            <span class="text-xs font-bold text-slate-700 group-hover:text-green-600">Paketler</span>
                        </a>
                        <a href="<?php echo BASE_URL; ?>/business/billing" class="group flex flex-col items-center p-3 rounded-xl border border-slate-200 hover:border-purple-300 hover:bg-purple-50/50 transition-all">
                            <div class="w-10 h-10 bg-purple-50 rounded-lg flex items-center justify-center mb-2 group-hover:bg-purple-500 transition-all">
                                <svg class="w-5 h-5 text-purple-600 group-hover:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            </div>
                            <span class="text-xs font-bold text-slate-700 group-hover:text-purple-600">Faturalar</span>
                        </a>
                        <a href="<?php echo BASE_URL; ?>/business/payment-methods" class="group flex flex-col items-center p-3 rounded-xl border border-slate-200 hover:border-amber-300 hover:bg-amber-50/50 transition-all">
                            <div class="w-10 h-10 bg-amber-50 rounded-lg flex items-center justify-center mb-2 group-hover:bg-amber-500 transition-all">
                                <svg class="w-5 h-5 text-amber-600 group-hover:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                            </div>
                            <span class="text-xs font-bold text-slate-700 group-hover:text-amber-600">Ödeme</span>
                        </a>
                    </div>
                </div>
                
                <!-- Package Info -->
                <div class="bg-gradient-to-br from-indigo-600 to-purple-700 text-white p-5 rounded-xl shadow-lg">
                    <div class="flex items-start justify-between mb-3">
                        <div>
                            <h3 class="text-lg font-black"><?php echo htmlspecialchars($subscription['package_name'] ?? 'Paket'); ?></h3>
                            <p class="text-xs opacity-80 mt-0.5">
                                <?php 
                                if (!empty($subscription['end_date'])) {
                                    $endDate = new DateTime($subscription['end_date']);
                                    $now = new DateTime();
                                    $diff = $now->diff($endDate);
                                    echo $diff->days . ' gün kaldı';
                                } else {
                                    echo 'Aktif';
                                }
                                ?>
                            </p>
                        </div>
                        <span class="bg-white/20 backdrop-blur-sm px-2.5 py-1 rounded-lg text-xs font-bold">Aktif</span>
                    </div>
                    
                    <?php if (!empty($subscription['features'])): ?>
                    <div class="space-y-1.5 mt-3 pt-3 border-t border-white/15">
                        <?php foreach (array_slice((array)$subscription['features'], 0, 4) as $feature): ?>
                        <div class="flex items-center text-xs">
                            <svg class="w-3.5 h-3.5 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                            <span class="opacity-90"><?php echo htmlspecialchars(is_array($feature) ? ($feature['name'] ?? '') : $feature); ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    
                    <div class="mt-4 pt-3 border-t border-white/15">
                        <a href="<?php echo BASE_URL; ?>/business/subscriptions" class="inline-flex items-center text-xs font-bold hover:underline opacity-90 hover:opacity-100">
                            Aboneliği Yönet
                            <svg class="w-3.5 h-3.5 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
  </div>
</div>
