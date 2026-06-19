<?php
require_once __DIR__ . '/../../helpers/translations.php';

$subscriptions = $subscriptions ?? [];
$baseUrl = BASE_URL;
$totalCollectedTry = floatval($total_collected_try ?? 0);

$statusColors = [
    'pending'   => 'bg-amber-100 text-amber-800',
    'active'    => 'bg-emerald-100 text-emerald-800',
    'expired'   => 'bg-red-100 text-red-700',
    'cancelled' => 'bg-slate-100 text-slate-600',
    'suspended' => 'bg-indigo-100 text-indigo-800',
];
$statusLabels = [
    'pending'   => 'Beklemede',
    'active'    => 'Aktif',
    'expired'   => 'Süresi Dolmuş',
    'cancelled' => 'İptal',
    'suspended' => 'Askıda',
];
$cycleLabels = [
    'monthly'   => 'Aylık',
    'yearly'    => 'Yıllık',
    'one_time'  => 'Tek Seferlik',
    'quarterly' => '3 Aylık',
    'biannual'  => '6 Aylık',
];
$methodLabels = [
    'manual'        => 'Manuel',
    'gateway'       => 'Online',
    'bank_transfer' => 'Havale',
    'card'          => 'Kart',
];

// Stats
$total = count($subscriptions);
$activeCount = 0;
$pendingCount = 0;
$suspendedCount = 0;
foreach ($subscriptions as $s) {
    $st = $s['status'] ?? '';
    if ($st === 'active') {
        $activeCount++;
    } elseif ($st === 'pending') {
        $pendingCount++;
    } elseif ($st === 'suspended') {
        $suspendedCount++;
    }
}
?>

<div class="q-page q-biz-theme animate-slide-up">
  <div class="q-container">

    <!-- Header -->
    <div class="rounded-2xl border border-slate-200/90 bg-white shadow-sm overflow-hidden">
        <div class="px-5 sm:px-8 py-6 sm:py-7 border-b border-slate-100 bg-gradient-to-b from-slate-50/90 to-white">
            <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-5">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-widest text-slate-500 mb-1.5">Qodmin</p>
                    <h1 class="text-2xl sm:text-[1.75rem] font-bold text-slate-900 tracking-tight">Abonelikler</h1>
                    <p class="text-sm text-slate-600 mt-1.5">Tüm müşteri aboneliklerini yönetin</p>
                </div>
            </div>
        </div>
        <div class="p-4 sm:p-6 grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-5 gap-3 sm:gap-4">
            <div class="rounded-xl border border-slate-200 bg-white px-4 py-3.5 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Toplam</p>
                <p class="text-2xl font-bold text-slate-900 tabular-nums mt-1"><?php echo $total; ?></p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white px-4 py-3.5 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Aktif</p>
                <p class="text-2xl font-bold text-slate-900 tabular-nums mt-1"><?php echo $activeCount; ?></p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white px-4 py-3.5 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Bekleyen</p>
                <p class="text-2xl font-bold text-slate-900 tabular-nums mt-1"><?php echo $pendingCount; ?></p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white px-4 py-3.5 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Askıda</p>
                <p class="text-2xl font-bold text-slate-900 tabular-nums mt-1"><?php echo $suspendedCount; ?></p>
            </div>
            <div class="rounded-xl border border-indigo-200/80 bg-indigo-50/50 px-4 py-3.5 shadow-sm col-span-2 sm:col-span-3 xl:col-span-1">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-indigo-800/80">Toplam tahsilat</p>
                <p class="text-2xl font-bold text-indigo-950 tabular-nums mt-1">₺<?php echo number_format($totalCollectedTry, 0, ',', '.'); ?></p>
            </div>
        </div>
    </div>

    <!-- Filter bar -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-3 flex flex-wrap gap-2">
        <input type="text" id="sub-search" placeholder="İşletme adı veya paket ara…"
               class="flex-1 min-w-[200px] px-3 py-2 rounded-lg border border-slate-200 text-sm font-medium focus:border-indigo-400 focus:ring-1 focus:ring-indigo-200 outline-none transition-all"
               oninput="filterSubs()">
        <select id="sub-filter-status" onchange="filterSubs()"
                class="px-3 py-2 rounded-lg border border-slate-200 text-sm font-bold bg-white focus:border-indigo-400 outline-none">
            <option value="">Tüm durumlar</option>
            <?php foreach ($statusLabels as $sv => $sl): ?>
            <option value="<?php echo $sv; ?>"><?php echo $sl; ?></option>
            <?php endforeach; ?>
        </select>
        <select id="sub-filter-cycle" onchange="filterSubs()"
                class="px-3 py-2 rounded-lg border border-slate-200 text-sm font-bold bg-white focus:border-indigo-400 outline-none">
            <option value="">Tüm döngüler</option>
            <?php foreach ($cycleLabels as $cv => $cl): ?>
            <option value="<?php echo $cv; ?>"><?php echo $cl; ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <?php if (empty($subscriptions)): ?>
        <div class="text-center py-16">
            <div class="w-16 h-16 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-3">
                <svg class="w-7 h-7 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
            </div>
            <h3 class="font-black text-slate-700 mb-1">Henüz abonelik yok</h3>
            <p class="text-sm text-slate-500">Müşteri abonelikleri burada görünecektir.</p>
        </div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-left" id="subs-table">
                <thead class="bg-slate-50 border-b-2 border-slate-200">
                    <tr class="text-[9px] sm:text-[10px] font-black text-slate-500 uppercase tracking-widest">
                        <th class="px-4 py-3">İşletme / Müşteri</th>
                        <th class="px-4 py-3">Paket</th>
                        <th class="px-4 py-3 hidden md:table-cell">Döngü</th>
                        <th class="px-4 py-3">Plan tutarı</th>
                        <th class="px-4 py-3 hidden md:table-cell">Tahsil edilen</th>
                        <th class="px-4 py-3 hidden lg:table-cell">Son ödeme</th>
                        <th class="px-4 py-3">Durum</th>
                        <th class="px-4 py-3 hidden xl:table-cell">Bitiş</th>
                        <th class="px-4 py-3 text-right">İşlem</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100" id="subs-body">
                    <?php foreach ($subscriptions as $sub):
                        $status = $sub['status'] ?? 'pending';
                        $stColor = $statusColors[$status] ?? 'bg-slate-100 text-slate-600';
                        $stLabel = $statusLabels[$status] ?? $status;
                        $cycle   = $sub['billing_cycle'] ?? '';
                        $clLabel = $cycleLabels[$cycle] ?? ($cycle ?: '-');
                        $amt     = floatval($sub['amount'] ?? 0);
                        $tahsil  = floatval($sub['total_paid_completed'] ?? 0);
                        $cur     = $sub['currency'] ?? 'TRY';
                        $lpd     = $sub['last_payment_date'] ?? null;
                        $lpa     = isset($sub['last_payment_amount']) ? floatval($sub['last_payment_amount']) : 0;
                        $lpm     = $sub['last_payment_method'] ?? '';
                        $company = $sub['company_name'] ?? '';
                        $fullName= trim(($sub['first_name'] ?? '') . ' ' . ($sub['last_name'] ?? ''));
                        $businessLabel = $company ?: $fullName ?: '-';
                    ?>
                    <tr class="hover:bg-slate-50/60 transition-colors sub-row"
                        data-name="<?php echo htmlspecialchars(strtolower($businessLabel . ' ' . ($sub['package_name'] ?? ''))); ?>"
                        data-status="<?php echo htmlspecialchars($status); ?>"
                        data-cycle="<?php echo htmlspecialchars($cycle); ?>">
                        <td class="px-4 py-3.5">
                            <div class="font-bold text-sm text-slate-800 leading-tight"><?php echo htmlspecialchars($businessLabel); ?></div>
                            <?php if ($company && $fullName): ?>
                            <div class="text-xs text-slate-500 mt-0.5"><?php echo htmlspecialchars($fullName); ?></div>
                            <?php endif; ?>
                            <div class="text-xs text-slate-400 mt-0.5 truncate max-w-[180px]"><?php echo htmlspecialchars($sub['customer_email'] ?? ''); ?></div>
                            <?php if ($sub['is_trial'] ?? false): ?>
                            <span class="inline-block mt-1 px-1.5 py-0.5 rounded text-[9px] font-black bg-blue-100 text-blue-700">Deneme</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3.5">
                            <span class="font-bold text-sm text-slate-800"><?php echo htmlspecialchars($sub['package_name'] ?? '-'); ?></span>
                        </td>
                        <td class="px-4 py-3.5 hidden md:table-cell">
                            <span class="text-xs font-bold text-slate-600 bg-slate-100 px-2 py-1 rounded-lg"><?php echo htmlspecialchars($clLabel); ?></span>
                        </td>
                        <td class="px-4 py-3.5">
                            <span class="text-sm font-bold text-slate-800">
                                <?php echo $amt > 0 ? (($cur === 'TRY' ? '₺' : $cur . ' ') . number_format($amt, 0, ',', '.')) : '<span class="text-slate-400 font-medium">—</span>'; ?>
                            </span>
                        </td>
                        <td class="px-4 py-3.5 hidden md:table-cell">
                            <span class="text-sm font-bold <?php echo $tahsil > 0 ? 'text-slate-800' : 'text-slate-400'; ?>">
                                <?php echo $tahsil > 0 ? (($cur === 'TRY' ? '₺' : $cur . ' ') . number_format($tahsil, 0, ',', '.')) : '—'; ?>
                            </span>
                        </td>
                        <td class="px-4 py-3.5 hidden lg:table-cell">
                            <?php if ($lpd || $lpa > 0): ?>
                            <div class="text-xs font-black text-slate-800">₺<?php echo number_format($lpa, 0, ',', '.'); ?></div>
                            <div class="text-[10px] text-slate-500"><?php echo htmlspecialchars($methodLabels[$lpm] ?? ($lpm ?: '-')); ?></div>
                            <div class="text-[10px] text-slate-400"><?php echo $lpd ? date('d.m.Y', strtotime($lpd)) : ''; ?></div>
                            <?php else: ?>
                            <span class="text-xs text-slate-400">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3.5">
                            <span class="px-2.5 py-1 rounded-lg text-[10px] font-black <?php echo $stColor; ?>">
                                <?php echo $stLabel; ?>
                            </span>
                        </td>
                        <td class="px-4 py-3.5 hidden xl:table-cell">
                            <span class="text-xs text-slate-600">
                                <?php echo !empty($sub['current_period_end']) ? date('d.m.Y', strtotime($sub['current_period_end'])) : '—'; ?>
                            </span>
                        </td>
                        <td class="px-4 py-3.5 text-right">
                            <a href="<?php echo $baseUrl; ?>/qodmin/subscriptions/<?php echo htmlspecialchars($sub['subscription_id']); ?>"
                               class="inline-flex items-center gap-1 px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white font-black text-xs rounded-lg transition-colors shadow-sm">
                                Detay →
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div id="subs-empty-filter" class="hidden text-center py-10 text-sm text-slate-500">Filtreyle eşleşen abonelik bulunamadı.</div>
        <?php endif; ?>
    </div>

  </div>
</div>
<script>
function filterSubs() {
    const q = document.getElementById('sub-search')?.value.toLowerCase() ?? '';
    const s = document.getElementById('sub-filter-status')?.value ?? '';
    const c = document.getElementById('sub-filter-cycle')?.value ?? '';
    let visible = 0;
    document.querySelectorAll('.sub-row').forEach(row => {
        const name   = row.dataset.name ?? '';
        const status = row.dataset.status ?? '';
        const cycle  = row.dataset.cycle ?? '';
        const show = (!q || name.includes(q)) && (!s || status === s) && (!c || cycle === c);
        row.classList.toggle('hidden', !show);
        if (show) visible++;
    });
    const emp = document.getElementById('subs-empty-filter');
    if (emp) emp.classList.toggle('hidden', visible > 0);
}
</script>
