<?php
$subscription = $subscription ?? null;
$package = $package ?? null;
$history = $history ?? [];
$packageName = $package['name'] ?? 'Paket';
$status = $subscription['status'] ?? 'inactive';
$currentPeriodEnd = $subscription['current_period_end'] ?? null;
$amount = $subscription['amount'] ?? 0;
$billingCycle = $subscription['billing_cycle'] ?? 'monthly';

$statusLabels = [
    'active'    => ['label' => 'Aktif',        'cls' => 'bg-emerald-100 text-emerald-700'],
    'pending'   => ['label' => 'Ödeme Bekliyor','cls' => 'bg-amber-100 text-amber-700'],
    'cancelled' => ['label' => 'İptal Edildi', 'cls' => 'bg-slate-100 text-slate-600'],
    'expired'   => ['label' => 'Süresi Doldu', 'cls' => 'bg-red-100 text-red-600'],
    'suspended' => ['label' => 'Askıya Alındı','cls' => 'bg-red-100 text-red-600'],
    'trial'     => ['label' => 'Deneme',       'cls' => 'bg-indigo-100 text-indigo-700'],
];
$paymentStatusLabels = [
    'completed' => ['label' => 'Başarılı',    'cls' => 'text-emerald-600'],
    'pending'   => ['label' => 'Bekliyor',    'cls' => 'text-amber-600'],
    'failed'    => ['label' => 'Başarısız',   'cls' => 'text-red-600'],
    'refunded'  => ['label' => 'İade Edildi', 'cls' => 'text-slate-500'],
];
?>

<div class="q-page q-biz-theme animate-slide-up">
  <div class="q-container q-stack q-stack--lg max-w-4xl min-w-0">
        <header class="q-page-header">
            <div>
                <p class="q-page-header__eyebrow">Hesap</p>
                <h1 class="q-page-header__title">Paket Bilgim</h1>
                <p class="q-page-header__subtitle">Mevcut paket ve abonelik bilgileriniz</p>
            </div>
        </header>

        <?php if (!$subscription || strtolower($status) !== 'active'): ?>
            <div class="q-card q-card--pad border-2 border-red-200 bg-gradient-to-r from-red-50 to-indigo-50">
                <div class="flex items-start gap-4">
                    <div class="flex-shrink-0">
                        <div class="w-12 h-12 bg-red-500 rounded-full flex items-center justify-center">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="flex-1">
                        <h3 class="text-2xl font-black text-red-900 mb-2">Aktif Paket Bulunamadı</h3>
                        <p class="text-red-800 mb-4">Sistemden tam olarak faydalanmak için bir paket satın almanız gerekmektedir.</p>
                        <a href="<?php echo BASE_URL; ?>/customer/packages/list" 
                           class="inline-flex items-center gap-2 bg-gradient-to-r from-indigo-600 to-indigo-700 hover:from-indigo-700 hover:to-indigo-800 text-white font-black py-3 px-6 rounded-xl transition-all duration-300 shadow-lg hover:shadow-xl transform hover:scale-[1.02]">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                            </svg>
                            Paketleri Görüntüle
                        </a>
        </div>
    </div>
</div>
</div>
        <?php else: ?>
            <!-- Active Subscription -->
            <div class="bg-white rounded-2xl shadow-soft p-6 border border-slate-100">
                <!-- Package Header -->
                <div class="flex items-center justify-between mb-6 pb-6 border-b border-slate-200">
                    <div>
                        <h2 class="text-2xl font-black text-slate-900"><?php echo htmlspecialchars($packageName); ?></h2>
                        <p class="text-slate-600 mt-1">Mevcut Paketiniz</p>
                    </div>
                    <div class="text-right">
                        <div class="text-3xl font-black text-indigo-600">₺<?php echo number_format($amount, 2, ',', '.'); ?></div>
                        <p class="text-sm text-slate-600 mt-1"><?php echo $billingCycle === 'yearly' ? '/yıl' : '/ay'; ?></p>
                    </div>
                </div>

                <!-- Subscription Details -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="text-sm font-medium text-slate-600">Durum</label>
                        <p class="text-lg font-bold text-green-600 mt-1 flex items-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            Aktif
                        </p>
                    </div>
                    <?php if ($currentPeriodEnd): ?>
                    <div>
                        <label class="text-sm font-medium text-slate-600">Bitiş Tarihi</label>
                        <p class="text-lg font-bold text-slate-900 mt-1"><?php echo date('d.m.Y', strtotime($currentPeriodEnd)); ?></p>
                    </div>
                    <?php endif; ?>
                    <div>
                        <label class="text-sm font-medium text-slate-600">Faturalama Dönemi</label>
                        <p class="text-lg font-bold text-slate-900 mt-1"><?php echo $billingCycle === 'yearly' ? 'Yıllık' : 'Aylık'; ?></p>
                    </div>
                </div>

                <!-- Package Description -->
                <?php if (!empty($package['description'])): ?>
                <div class="mt-6 pt-6 border-t border-slate-200">
                    <h3 class="text-lg font-bold text-slate-800 mb-3">Paket Özellikleri</h3>
                    <p class="text-slate-600"><?php echo htmlspecialchars($package['description']); ?></p>
                </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Satın Alma / Abonelik Geçmişi -->
        <?php if (!empty($history)): ?>
        <div class="mt-8 bg-white rounded-2xl shadow-soft border border-slate-100 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100">
                <h2 class="text-xl font-black text-slate-900">Satın Alma Geçmişi</h2>
                <p class="text-sm text-slate-600 mt-1">Tüm abonelik ve ödeme kayıtlarınız</p>
            </div>
            <div class="divide-y divide-slate-100">
                <?php foreach ($history as $h):
                    $hStatus = strtolower((string)($h['status'] ?? ''));
                    $isTrial = !empty($h['is_trial']);
                    $statusKey = $isTrial && $hStatus === 'active' ? 'trial' : $hStatus;
                    $sl = $statusLabels[$statusKey] ?? ['label' => ucfirst($hStatus), 'cls' => 'bg-slate-100 text-slate-600'];
                    $cycleLabel = ($h['billing_cycle'] ?? '') === 'yearly' ? 'Yıllık' : (($h['billing_cycle'] ?? '') === 'monthly' ? 'Aylık' : ucfirst($h['billing_cycle'] ?? ''));
                    $amt = (float)($h['amount'] ?? 0);
                ?>
                <div class="p-6">
                    <div class="flex items-start justify-between gap-4 flex-wrap">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <h3 class="text-base font-bold text-slate-900"><?php echo htmlspecialchars($h['package_name'] ?? 'Paket'); ?></h3>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold <?php echo $sl['cls']; ?>">
                                    <?php echo htmlspecialchars($sl['label']); ?>
                                </span>
                                <?php if ($isTrial): ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-indigo-50 text-indigo-700">7 Gün Ücretsiz</span>
                                <?php endif; ?>
                            </div>
                            <div class="mt-2 text-sm text-slate-600 flex items-center gap-x-4 gap-y-1 flex-wrap">
                                <span>Faturalama: <?php echo htmlspecialchars($cycleLabel); ?></span>
                                <?php if (!empty($h['current_period_start'])): ?>
                                    <span>Başlangıç: <?php echo date('d.m.Y', strtotime($h['current_period_start'])); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($h['current_period_end'])): ?>
                                    <span>Bitiş: <?php echo date('d.m.Y', strtotime($h['current_period_end'])); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-lg font-black text-slate-900">₺<?php echo number_format($amt, 2, ',', '.'); ?></div>
                            <p class="text-xs text-slate-500 mt-0.5"><?php echo htmlspecialchars($h['currency'] ?? 'TRY'); ?></p>
                        </div>
                    </div>
                    <?php if (!empty($h['payments'])): ?>
                    <div class="mt-4 bg-slate-50 border border-slate-100 rounded-xl overflow-hidden">
                        <table class="w-full text-sm">
                            <thead class="bg-slate-100/50 text-slate-600">
                                <tr>
                                    <th class="text-left py-2 px-4 font-semibold text-xs uppercase tracking-wide">Tarih</th>
                                    <th class="text-left py-2 px-4 font-semibold text-xs uppercase tracking-wide">Yöntem</th>
                                    <th class="text-right py-2 px-4 font-semibold text-xs uppercase tracking-wide">Tutar</th>
                                    <th class="text-right py-2 px-4 font-semibold text-xs uppercase tracking-wide">Durum</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200">
                                <?php foreach ($h['payments'] as $p):
                                    $pDate = $p['payment_date'] ?? $p['created_at'] ?? null;
                                    $pStatus = strtolower((string)($p['payment_status'] ?? ''));
                                    $psl = $paymentStatusLabels[$pStatus] ?? ['label' => ucfirst($pStatus), 'cls' => 'text-slate-600'];
                                ?>
                                <tr>
                                    <td class="py-2 px-4 text-slate-700"><?php echo $pDate ? date('d.m.Y H:i', strtotime($pDate)) : '—'; ?></td>
                                    <td class="py-2 px-4 text-slate-700"><?php echo htmlspecialchars(ucfirst((string)($p['payment_method'] ?? ''))); ?></td>
                                    <td class="py-2 px-4 text-right text-slate-900 font-semibold">₺<?php echo number_format((float)($p['amount'] ?? 0), 2, ',', '.'); ?></td>
                                    <td class="py-2 px-4 text-right font-semibold <?php echo $psl['cls']; ?>"><?php echo htmlspecialchars($psl['label']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
  </div>
</div>
