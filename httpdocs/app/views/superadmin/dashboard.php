<?php
/**
 * Super Admin Dashboard — SaaS Overview
 */
require_once __DIR__ . '/../../helpers/translations.php';

$totalCustomers        = $totalCustomers ?? 0;
$activeCustomers       = $activeCustomers ?? 0;
$activeSubscriptions   = $activeSubscriptions ?? 0;
$monthlyRevenue        = $monthlyRevenue ?? 0;
$recentCustomers       = $recentCustomers ?? [];
$packageDistribution   = $packageDistribution ?? [];
$businessesMonitoring  = $businessesMonitoring ?? [];
$allBusinessesForGrid  = $allBusinessesForGrid ?? [];
$saasRevenue           = $saasRevenue ?? ['daily'=>0,'weekly'=>0,'monthly'=>0,'yearly'=>0,'paid_active'=>0,'trial_active'=>0];
$packageSales          = $packageSales ?? [];
$totalStaff            = $totalStaff ?? 0;
$totalTables           = $totalTables ?? 0;
$totalOccupiedTables   = $totalOccupiedTables ?? 0;
$totalActiveTables     = $totalActiveTables ?? 0;
$totalRevenueToday     = $totalRevenueToday ?? 0;
$totalOrdersToday      = $totalOrdersToday ?? 0;
$totalOrders           = $totalOrders ?? 0;
$totalRevenue          = $totalRevenue ?? 0;
$activeBusinesses      = $activeBusinesses ?? 0;
$newCustomersThisMonth = $newCustomersThisMonth ?? 0;
$newCustomersLastMonth = $newCustomersLastMonth ?? 0;
$revenueTrend          = $revenueTrend ?? [];
$occupancyRate = $totalTables > 0 ? round(($totalActiveTables / $totalTables) * 100) : 0;
?>

<div class="q-page animate-slide-up">
  <div class="q-container" id="superadmin-dashboard-root">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight">Yönetim Paneli</h1>
            <?php
                // Ay adını Türkçe formatta yaz ("d F Y" PHP'de İngilizce ay adı
                // döner; 'MMMM' ise ICU formatıdır ve PHP date() tarafından
                // desteklenmez – her M için tek harf ay kısaltması basarak
                // "AprAprAprApr" gibi bozuk çıktı üretir).
                $__months = [1=>'Ocak','Şubat','Mart','Nisan','Mayıs','Haziran','Temmuz','Ağustos','Eylül','Ekim','Kasım','Aralık'];
                $__today = new \DateTime('now');
                $__dateStr = $__today->format('d') . ' ' . ($__months[(int)$__today->format('n')] ?? $__today->format('F')) . ' ' . $__today->format('Y');
            ?>
            <p class="text-slate-500 text-sm mt-0.5">Platform genel bakışı · <span class="font-semibold"><?= htmlspecialchars($__dateStr) ?></span></p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="/downloads/qordy-isletme-app.apk"
               download="qordy-isletme-app.apk"
               class="inline-flex items-center gap-1.5 bg-slate-900 text-white px-3 py-1.5 rounded-full text-xs font-bold border border-slate-900 hover:bg-slate-800 transition-colors">
                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                İşletme App
            </a>
            <a href="/downloads/qordy-personel-app.apk"
               download="qordy-personel-app.apk"
               class="inline-flex items-center gap-1.5 bg-indigo-600 text-white px-3 py-1.5 rounded-full text-xs font-bold border border-indigo-600 hover:bg-indigo-500 transition-colors">
                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                Personel App
            </a>
            <span class="inline-flex items-center gap-1.5 bg-emerald-50 text-emerald-700 px-3 py-1.5 rounded-full text-xs font-bold border border-emerald-200">
                <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full animate-pulse"></span>
                Canlı · <span id="lastRefresh"><?= date('H:i') ?></span>
            </span>
        </div>
    </div>

    <!-- ── SaaS Revenue Block ── -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 px-5 pt-5 pb-3 border-b border-slate-100">
            <div>
                <h2 class="text-sm font-black text-slate-800">Platform Geliri</h2>
                <p class="text-xs text-slate-400 mt-0.5">Paket satışlarından elde edilen gelir</p>
            </div>
            <!-- Period tabs -->
            <div class="inline-flex rounded-xl overflow-hidden border border-slate-200 text-xs font-bold shrink-0" id="period-tabs">
                <button onclick="setPeriod('daily')"   data-period="daily"   class="period-btn px-3 py-1.5 bg-slate-900 text-white">Günlük</button>
                <button onclick="setPeriod('weekly')"  data-period="weekly"  class="period-btn px-3 py-1.5 text-slate-600 hover:bg-slate-50">Haftalık</button>
                <button onclick="setPeriod('monthly')" data-period="monthly" class="period-btn px-3 py-1.5 text-slate-600 hover:bg-slate-50">Aylık</button>
                <button onclick="setPeriod('yearly')"  data-period="yearly"  class="period-btn px-3 py-1.5 text-slate-600 hover:bg-slate-50">Yıllık</button>
            </div>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-4 divide-x divide-slate-100">
            <!-- Revenue (changes by period) -->
            <div class="p-5">
                <div class="text-xs text-slate-400 font-semibold uppercase tracking-wider mb-1">Gelir</div>
                <div class="text-2xl font-black text-slate-900" id="saas-revenue-val">
                    <?= number_format($saasRevenue['daily'], 0, ',', '.') ?> ₺
                </div>
                <div class="text-xs text-slate-400 mt-1">paket satışı</div>
            </div>
            <!-- Paid subscribers -->
            <div class="p-5">
                <div class="text-xs text-slate-400 font-semibold uppercase tracking-wider mb-1">Ücretli</div>
                <div class="text-2xl font-black text-emerald-600"><?= $saasRevenue['paid_active'] ?></div>
                <div class="text-xs text-slate-400 mt-1">aktif abonelik</div>
            </div>
            <!-- Trial subscribers -->
            <div class="p-5">
                <div class="text-xs text-slate-400 font-semibold uppercase tracking-wider mb-1">Deneme</div>
                <div class="text-2xl font-black text-amber-500"><?= $saasRevenue['trial_active'] ?></div>
                <div class="text-xs text-slate-400 mt-1">aktif trial</div>
            </div>
            <!-- Total businesses -->
            <div class="p-5">
                <div class="text-xs text-slate-400 font-semibold uppercase tracking-wider mb-1">Müşteri</div>
                <div class="text-2xl font-black text-slate-900"><?= $totalCustomers ?></div>
                <div class="text-xs text-slate-400 mt-1"><?= $activeCustomers ?> aktif · <?= ($totalCustomers - $activeCustomers) ?> pasif</div>
            </div>
        </div>
    </div>

    <!-- ── Top KPI Row ── -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        <div class="bg-gradient-to-br from-orange-500 to-rose-500 text-white p-4 sm:p-5 rounded-2xl shadow-sm">
            <div class="flex items-center gap-2 mb-2.5">
                <div class="w-8 h-8 bg-white/20 rounded-xl flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <span class="text-xs font-bold text-white/80">Bugünkü Ciro</span>
            </div>
            <div class="text-xl sm:text-2xl font-black"><?= number_format($totalRevenueToday, 2, ',', '.') ?> ₺</div>
            <div class="text-xs text-white/70 mt-1"><?= $totalOrdersToday ?> sipariş</div>
        </div>

        <div class="bg-gradient-to-br from-blue-500 to-indigo-600 text-white p-4 sm:p-5 rounded-2xl shadow-sm">
            <div class="flex items-center gap-2 mb-2.5">
                <div class="w-8 h-8 bg-white/20 rounded-xl flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                </div>
                <span class="text-xs font-bold text-white/80">Aktif İşletme</span>
            </div>
            <div class="text-xl sm:text-2xl font-black"><?= $activeBusinesses ?></div>
            <div class="text-xs text-white/70 mt-1"><?= $totalCustomers ?> kayıtlı</div>
        </div>

        <div class="bg-gradient-to-br from-violet-500 to-purple-600 text-white p-4 sm:p-5 rounded-2xl shadow-sm">
            <div class="flex items-center gap-2 mb-2.5">
                <div class="w-8 h-8 bg-white/20 rounded-xl flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                </div>
                <span class="text-xs font-bold text-white/80">Masa Durumu</span>
            </div>
            <div class="text-xl sm:text-2xl font-black"><?= $totalActiveTables ?> / <?= $totalTables ?></div>
            <div class="text-xs text-white/70 mt-1">%<?= $occupancyRate ?> doluluk</div>
        </div>

        <div class="bg-gradient-to-br from-emerald-500 to-teal-600 text-white p-4 sm:p-5 rounded-2xl shadow-sm">
            <div class="flex items-center gap-2 mb-2.5">
                <div class="w-8 h-8 bg-white/20 rounded-xl flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </div>
                <span class="text-xs font-bold text-white/80">Toplam Personel</span>
            </div>
            <div class="text-xl sm:text-2xl font-black"><?= $totalStaff ?></div>
            <div class="text-xs text-white/70 mt-1">tüm işletmeler</div>
        </div>
    </div>

    <?php if (!empty($revenueTrend) && count($revenueTrend) > 1): ?>
    <!-- Revenue Trend Chart -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-sm font-black text-slate-800">Son 7 Gün — İşletme Ciro Trendi</h2>
            <span class="text-xs text-slate-400 font-medium">Tüm işletmeler</span>
        </div>
        <div class="h-40 flex items-end gap-1.5">
            <?php
            $maxR = max(array_column($revenueTrend, 'revenue'));
            if ($maxR <= 0) $maxR = 1;
            foreach ($revenueTrend as $day):
                $h = ($day['revenue'] / $maxR) * 100;
                $isToday = $day['date'] === date('Y-m-d');
                $dayName = date('d.m', strtotime($day['date']));
            ?>
            <div class="flex-1 flex flex-col items-center gap-1 group cursor-default">
                <div class="text-[10px] text-slate-500 font-bold opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap">
                    <?= number_format($day['revenue'], 0, ',', '.') ?> ₺
                </div>
                <div class="w-full rounded-t-lg transition-all duration-300 hover:opacity-80 <?= $isToday ? 'bg-gradient-to-t from-orange-500 to-orange-400' : 'bg-gradient-to-t from-slate-200 to-slate-100' ?>"
                     style="height: <?= max($h, 4) ?>%"
                     title="<?= number_format($day['revenue'], 2) ?> ₺ — <?= $day['order_count'] ?> sipariş"></div>
                <div class="text-[10px] <?= $isToday ? 'text-orange-600 font-black' : 'text-slate-400' ?>"><?= $dayName ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ── Businesses Grid with Logos ── -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 px-5 py-4 border-b border-slate-100">
            <div>
                <h2 class="text-sm font-black text-slate-800">İşletmeler</h2>
                <p class="text-xs text-slate-400 mt-0.5"><?= $totalCustomers ?> kayıtlı işletme</p>
            </div>
            <div class="flex items-center gap-2">
                <input type="text" id="biz-search" placeholder="İşletme ara…"
                       class="text-xs border border-slate-200 rounded-lg px-3 py-1.5 w-36 focus:outline-none focus:ring-2 focus:ring-orange-400/30 focus:border-orange-400">
                <a href="<?= BASE_URL ?>/qodmin/businesses"
                   class="text-xs font-bold text-orange-600 hover:text-orange-700 transition-colors whitespace-nowrap">Tümünü gör →</a>
            </div>
        </div>
        <?php if (empty($allBusinessesForGrid)): ?>
        <div class="py-12 text-center text-slate-400 text-sm">Henüz kayıtlı işletme yok.</div>
        <?php else: ?>
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-3 p-4" id="biz-grid">
            <?php foreach ($allBusinessesForGrid as $biz):
                $bizId   = $biz['customer_id'] ?? '';
                $bizName = $biz['company_name'] ?? 'Adsız';
                $logo    = $biz['logo_path'] ?? null;
                $isActive = (int)($biz['is_active'] ?? 0);
                $subStatus = $biz['sub_status'] ?? null;
                $isTrial  = !empty($biz['is_trial']);
                $pkgName  = $biz['package_name'] ?? null;
                $subdomain = $biz['subdomain'] ?? null;

                if ($subStatus === 'active' && !$isTrial) {
                    $badge = '<span class="bg-emerald-100 text-emerald-700 text-[10px] font-black px-1.5 py-0.5 rounded-full">Aktif</span>';
                } elseif ($subStatus === 'active' && $isTrial) {
                    $badge = '<span class="bg-amber-100 text-amber-700 text-[10px] font-black px-1.5 py-0.5 rounded-full">Trial</span>';
                } else {
                    $badge = '<span class="bg-slate-100 text-slate-500 text-[10px] font-black px-1.5 py-0.5 rounded-full">Pasif</span>';
                }
            ?>
            <a href="<?= BASE_URL ?>/qodmin/businesses/<?= htmlspecialchars($bizId) ?>"
               class="biz-card group bg-slate-50 hover:bg-white border border-slate-200 hover:border-orange-300 hover:shadow-md rounded-xl p-3 flex flex-col items-center gap-2 transition-all"
               data-name="<?= strtolower(htmlspecialchars($bizName)) ?>">
                <!-- Logo or initials -->
                <?php if ($logo && file_exists(rtrim($_SERVER['DOCUMENT_ROOT'] ?? '/var/www/vhosts/qordy.com/httpdocs', '/') . $logo)): ?>
                <div class="w-12 h-12 rounded-xl overflow-hidden bg-white border border-slate-100 flex-shrink-0 flex items-center justify-center">
                    <img src="<?= htmlspecialchars($logo) ?>" alt="<?= htmlspecialchars($bizName) ?>"
                         class="w-full h-full object-contain">
                </div>
                <?php else: ?>
                <?php
                    $colors = ['from-orange-400 to-rose-500','from-blue-400 to-indigo-500',
                               'from-emerald-400 to-teal-500','from-violet-400 to-purple-500',
                               'from-amber-400 to-orange-500','from-pink-400 to-rose-500'];
                    $ci = abs(crc32($bizId)) % count($colors);
                ?>
                <div class="w-12 h-12 rounded-xl bg-gradient-to-br <?= $colors[$ci] ?> flex items-center justify-center text-white font-black text-sm flex-shrink-0">
                    <?= strtoupper(mb_substr($bizName, 0, 2, 'UTF-8')) ?>
                </div>
                <?php endif; ?>
                <div class="w-full text-center min-w-0">
                    <div class="text-xs font-bold text-slate-800 group-hover:text-orange-600 transition-colors truncate leading-tight">
                        <?= htmlspecialchars($bizName) ?>
                    </div>
                    <?php if ($subdomain): ?>
                    <div class="text-[10px] text-slate-400 truncate"><?= htmlspecialchars($subdomain) ?>.qordy.com</div>
                    <?php endif; ?>
                    <div class="mt-1"><?= $badge ?></div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- ── Two-column: Package Sales + Monitoring Table ── -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

        <!-- Package Sales -->
        <?php if (!empty($packageSales)): ?>
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
            <h2 class="text-sm font-black text-slate-800 mb-4">Paket Satışları</h2>
            <div class="space-y-3">
                <?php
                $totalPkgRevenue = array_sum(array_column($packageSales, 'revenue'));
                foreach ($packageSales as $pk):
                    $pct = $totalPkgRevenue > 0 ? round(($pk['revenue'] / $totalPkgRevenue) * 100) : 0;
                ?>
                <div>
                    <div class="flex items-center justify-between text-xs mb-1">
                        <span class="font-bold text-slate-700 truncate max-w-[120px]"><?= htmlspecialchars($pk['name'] ?? 'Bilinmiyor') ?></span>
                        <div class="flex items-center gap-2 shrink-0">
                            <span class="text-slate-400"><?= $pk['sold'] ?> satış</span>
                            <span class="font-black text-slate-800"><?= number_format($pk['revenue'], 0, ',', '.') ?> ₺</span>
                        </div>
                    </div>
                    <div class="w-full bg-slate-100 rounded-full h-1.5">
                        <div class="h-1.5 rounded-full bg-gradient-to-r from-orange-400 to-orange-500 transition-all" style="width:<?= $pct ?>%"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Businesses Monitoring Table -->
        <div class="<?= !empty($packageSales) ? 'lg:col-span-2' : 'lg:col-span-3' ?> bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                <h2 class="text-sm font-black text-slate-800">İşletme Performansı</h2>
                <a href="<?= BASE_URL ?>/qodmin/businesses" class="text-xs font-bold text-orange-600 hover:text-orange-700 transition-colors">Tümünü gör →</a>
            </div>
            <?php if (empty($businessesMonitoring)): ?>
            <div class="py-10 text-center text-slate-400 text-sm">Henüz veri yok.</div>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="q-table">
                    <thead class="bg-slate-50 border-b border-slate-100">
                        <tr>
                            <th class="text-left py-2.5 px-4 text-[11px] font-black text-slate-400 uppercase tracking-wider">İşletme</th>
                            <th class="text-right py-2.5 px-4 text-[11px] font-black text-slate-400 uppercase tracking-wider">Bugün</th>
                            <th class="text-right py-2.5 px-4 text-[11px] font-black text-slate-400 uppercase tracking-wider hidden md:table-cell">Bu Ay</th>
                            <th class="text-right py-2.5 px-4 text-[11px] font-black text-slate-400 uppercase tracking-wider hidden lg:table-cell">Masa</th>
                            <th class="text-center py-2.5 px-4 text-[11px] font-black text-slate-400 uppercase tracking-wider">Durum</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <?php foreach ($businessesMonitoring as $b):
                            $bid = $b['business_id'] ?? '';
                            $tocc = $b['total_tables'] > 0 ? round(($b['active_tables']/$b['total_tables'])*100) : 0;
                            // find logo from allBusinessesForGrid
                            $bgRow = current(array_filter($allBusinessesForGrid, fn($x) => ($x['customer_id']??'') === $bid));
                            $bLogo = $bgRow['logo_path'] ?? null;
                            $bLogoExists = $bLogo && file_exists(rtrim($_SERVER['DOCUMENT_ROOT'] ?? '/var/www/vhosts/qordy.com/httpdocs', '/') . $bLogo);
                        ?>
                        <tr class="hover:bg-slate-50/80 transition-colors">
                            <td class="py-2.5 px-4">
                                <a href="<?= BASE_URL ?>/qodmin/businesses/<?= htmlspecialchars($bid) ?>" class="flex items-center gap-2.5 group">
                                    <?php if ($bLogoExists): ?>
                                    <div class="w-7 h-7 rounded-lg overflow-hidden bg-slate-100 border border-slate-100 flex-shrink-0">
                                        <img src="<?= htmlspecialchars($bLogo) ?>" class="w-full h-full object-contain">
                                    </div>
                                    <?php else: ?>
                                    <?php
                                        $ci2 = abs(crc32($bid)) % count($colors ?? ['from-orange-400 to-rose-500']);
                                        $colClass = ($colors ?? ['from-orange-400 to-rose-500'])[$ci2];
                                    ?>
                                    <div class="w-7 h-7 rounded-lg bg-gradient-to-br <?= $colClass ?> flex items-center justify-center text-white font-black text-[10px] flex-shrink-0">
                                        <?= strtoupper(mb_substr($b['company_name'] ?? '?', 0, 2, 'UTF-8')) ?>
                                    </div>
                                    <?php endif; ?>
                                    <div class="min-w-0">
                                        <div class="text-xs font-bold text-slate-800 group-hover:text-orange-600 transition-colors truncate"><?= htmlspecialchars($b['company_name']) ?></div>
                                        <div class="text-[10px] text-slate-400 truncate"><?= htmlspecialchars($b['email'] ?? '') ?></div>
                                    </div>
                                </a>
                            </td>
                            <td class="py-2.5 px-4 text-right">
                                <div class="text-xs font-black <?= $b['revenue_today'] > 0 ? 'text-emerald-600' : 'text-slate-300' ?>">
                                    <?= number_format($b['revenue_today'], 0, ',', '.') ?> ₺
                                </div>
                                <div class="text-[10px] text-slate-400"><?= $b['orders_today'] ?> sipariş</div>
                            </td>
                            <td class="py-2.5 px-4 text-right hidden md:table-cell">
                                <div class="text-xs font-bold text-slate-600"><?= number_format($b['revenue_month'], 0, ',', '.') ?> ₺</div>
                                <div class="text-[10px] text-slate-400"><?= $b['orders_month'] ?> sipariş</div>
                            </td>
                            <td class="py-2.5 px-4 text-right hidden lg:table-cell">
                                <div class="text-xs">
                                    <span class="font-bold text-slate-700"><?= $b['active_tables'] ?></span>
                                    <span class="text-slate-300">/<?= $b['total_tables'] ?></span>
                                </div>
                                <?php if ($b['total_tables'] > 0): ?>
                                <div class="w-full bg-slate-100 rounded-full h-1 mt-1">
                                    <div class="h-1 rounded-full <?= $tocc>70?'bg-emerald-400':($tocc>30?'bg-amber-400':'bg-slate-300') ?>" style="width:<?= $tocc ?>%"></div>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td class="py-2.5 px-4 text-center">
                                <?php if ($b['active_tables'] > 0): ?>
                                <span class="w-2 h-2 bg-emerald-500 rounded-full inline-block animate-pulse" title="Aktif"></span>
                                <?php elseif ($b['total_tables'] > 0): ?>
                                <span class="w-2 h-2 bg-amber-400 rounded-full inline-block" title="Boş"></span>
                                <?php else: ?>
                                <span class="w-2 h-2 bg-slate-200 rounded-full inline-block" title="Pasif"></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <?php $actions = [
            [BASE_URL.'/qodmin/businesses', 'orange', 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4', 'İşletmeler', 'Tüm müşteriler'],
            [BASE_URL.'/qodmin/subscriptions', 'emerald', 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z', 'Abonelikler', 'Aktif planlar'],
            [BASE_URL.'/qodmin/activity-logs', 'blue', 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2', 'Aktivite', 'Giriş kayıtları'],
            [BASE_URL.'/qodmin/short-links', 'indigo', 'M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1', 'Kısa Linkler', 'Tıklama analizi'],
            [BASE_URL.'/qodmin/error-logs', 'red', 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z', 'Hata Logları', 'Sistem hataları'],
        ]; foreach ($actions as [$url,$color,$path,$title,$sub]): ?>
        <a href="<?= $url ?>" class="bg-white p-4 rounded-2xl border border-slate-200 hover:border-<?= $color ?>-300 hover:shadow-md transition-all group flex items-center gap-3">
            <div class="w-9 h-9 bg-<?= $color ?>-50 rounded-xl flex items-center justify-center group-hover:bg-<?= $color ?>-500 transition-all flex-shrink-0">
                <svg class="w-4 h-4 text-<?= $color ?>-600 group-hover:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $path ?>"/></svg>
            </div>
            <div class="min-w-0">
                <div class="font-bold text-sm text-slate-800 truncate"><?= $title ?></div>
                <div class="text-xs text-slate-400"><?= $sub ?></div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>


  </div>
</div>
<script>
(function() {
    'use strict';

    // SaaS revenue data per period
    const saasData = {
        daily:   '<?= number_format($saasRevenue['daily'], 0, ',', '.') ?> ₺',
        weekly:  '<?= number_format($saasRevenue['weekly'], 0, ',', '.') ?> ₺',
        monthly: '<?= number_format($saasRevenue['monthly'], 0, ',', '.') ?> ₺',
        yearly:  '<?= number_format($saasRevenue['yearly'], 0, ',', '.') ?> ₺',
    };

    window.setPeriod = function(period) {
        document.getElementById('saas-revenue-val').textContent = saasData[period] || '-';
        document.querySelectorAll('.period-btn').forEach(btn => {
            const active = btn.dataset.period === period;
            btn.className = 'period-btn px-3 py-1.5 text-xs font-bold transition-colors '
                + (active ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-50');
        });
    };

    // Business search filter
    const searchInput = document.getElementById('biz-search');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const q = this.value.toLowerCase().trim();
            document.querySelectorAll('.biz-card').forEach(card => {
                card.style.display = !q || card.dataset.name.includes(q) ? '' : 'none';
            });
        });
    }

    // Auto-refresh every 60s (strict-mode safe: arguments.callee was
    // replaced with a named function reference).
    function refreshDashboard() {
        fetch(window.location.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.text())
            .then(html => {
                const doc = new DOMParser().parseFromString(html, 'text/html');
                const src = doc.querySelector('#superadmin-dashboard-root');
                const dst = document.querySelector('#superadmin-dashboard-root');
                if (src && dst) dst.innerHTML = src.innerHTML;
                const el = document.getElementById('lastRefresh');
                if (el) el.textContent = new Date().toLocaleTimeString('tr-TR', {hour:'2-digit',minute:'2-digit'});
            }).catch(() => {});
    }
    let timer = setInterval(refreshDashboard, 60000);

    document.addEventListener('visibilitychange', function() {
        if (document.hidden) { clearInterval(timer); timer = null; }
        else if (!timer) { timer = setInterval(refreshDashboard, 60000); }
    });
})();
</script>
