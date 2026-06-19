<?php
require_once __DIR__ . '/../../helpers/translations.php';

$subscription = $subscription ?? null;
$customer = $customer ?? null;
$payments = $payments ?? [];

if (!$subscription) {
    header('Location: ' . BASE_URL . '/qodmin/subscriptions');
    exit;
}

$baseUrl = BASE_URL;
$subId = htmlspecialchars($subscription['subscription_id'] ?? '');

$statusColors = [
    'pending'   => ['bg' => 'bg-amber-100', 'text' => 'text-amber-800', 'dot' => 'bg-amber-400'],
    'active'    => ['bg' => 'bg-emerald-100', 'text' => 'text-emerald-800', 'dot' => 'bg-emerald-500'],
    'expired'   => ['bg' => 'bg-red-100', 'text' => 'text-red-700', 'dot' => 'bg-red-400'],
    'cancelled' => ['bg' => 'bg-slate-100', 'text' => 'text-slate-600', 'dot' => 'bg-slate-400'],
    'suspended' => ['bg' => 'bg-indigo-100', 'text' => 'text-indigo-800', 'dot' => 'bg-indigo-400'],
];
$statusLabels = [
    'pending'   => 'Beklemede',
    'active'    => 'Aktif',
    'expired'   => 'Süresi Dolmuş',
    'cancelled' => 'İptal Edilmiş',
    'suspended' => 'Askıda',
];
$cycleLabels = [
    'monthly'  => 'Aylık',
    'yearly'   => 'Yıllık',
    'one_time' => 'Tek Seferlik',
    'quarterly'=> '3 Aylık',
    'biannual' => '6 Aylık',
];
$methodLabels = [
    'manual'        => 'Manuel',
    'gateway'       => 'Online Ödeme',
    'bank_transfer' => 'Banka Havalesi',
    'card'          => 'Kredi/Banka Kartı',
];
$paymentStatusLabels = [
    'pending'   => 'Beklemede',
    'completed' => 'Tamamlandı',
    'failed'    => 'Başarısız',
    'refunded'  => 'İade Edildi',
];
$paymentStatusColors = [
    'pending'   => 'bg-amber-100 text-amber-700',
    'completed' => 'bg-emerald-100 text-emerald-700',
    'failed'    => 'bg-red-100 text-red-700',
    'refunded'  => 'bg-slate-100 text-slate-600',
];

$status = $subscription['status'] ?? 'pending';
$statusClr = $statusColors[$status] ?? $statusColors['pending'];
$statusLbl = $statusLabels[$status] ?? $status;
$bc = $subscription['billing_cycle'] ?? '';
$amt = floatval($subscription['amount'] ?? 0);
$cur = $subscription['currency'] ?? 'TRY';
$csrfToken = htmlspecialchars(\App\Core\Security\CSRFManager::generateToken() ?? '');
?>

<div class="q-page q-biz-theme animate-slide-up">
  <div class="q-container">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div class="flex items-center gap-3">
            <a href="<?php echo $baseUrl; ?>/qodmin/subscriptions"
               class="p-2.5 hover:bg-white rounded-xl border border-transparent hover:border-slate-200 hover:shadow-sm transition-all">
                <svg class="w-5 h-5 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            </a>
            <div>
                <p class="text-[10px] font-black uppercase tracking-[0.18em] text-indigo-500 mb-0.5">Abonelik Detayı</p>
                <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">
                    <?php echo htmlspecialchars($subscription['package_name'] ?? 'Abonelik'); ?>
                </h1>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <span class="inline-flex items-center gap-2 px-4 py-2 rounded-full text-sm font-black <?php echo $statusClr['bg'] . ' ' . $statusClr['text']; ?>">
                <span class="w-2 h-2 rounded-full <?php echo $statusClr['dot']; ?>"></span>
                <?php echo $statusLbl; ?>
            </span>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">

        <!-- ── Sol: Abonelik Bilgileri ── -->
        <div class="xl:col-span-2 space-y-4">

            <!-- Özet Kart -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-2">
                    <div class="w-6 h-6 bg-indigo-100 rounded-lg flex items-center justify-center flex-shrink-0">
                        <svg class="w-3.5 h-3.5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    </div>
                    <h2 class="font-black text-sm text-slate-800">Abonelik Bilgileri</h2>
                    <span class="ml-auto font-mono text-[10px] text-slate-400">#<?php echo mb_strtoupper(mb_substr($subId, -8)); ?></span>
                </div>
                <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <?php
                    $fields = [
                        ['Paket', htmlspecialchars($subscription['package_name'] ?? '-')],
                        ['Faturalama', htmlspecialchars($cycleLabels[$bc] ?? ($bc ?: '-'))],
                        ['Tutar', $amt > 0 ? (($cur === 'TRY' ? '₺' : $cur . ' ') . number_format($amt, 2, ',', '.')) : '-'],
                        ['Dönem Başlangıç', !empty($subscription['current_period_start']) ? date('d.m.Y H:i', strtotime($subscription['current_period_start'])) : '-'],
                        ['Dönem Bitiş', !empty($subscription['current_period_end']) ? date('d.m.Y H:i', strtotime($subscription['current_period_end'])) : '—'],
                        ['Oluşturulma', !empty($subscription['created_at']) ? date('d.m.Y H:i', strtotime($subscription['created_at'])) : '-'],
                    ];
                    if (!empty($subscription['cancelled_at'])) {
                        $fields[] = ['İptal Tarihi', date('d.m.Y H:i', strtotime($subscription['cancelled_at']))];
                    }
                    foreach ($fields as [$lbl, $val]):
                    ?>
                    <div>
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-wider mb-1"><?php echo $lbl; ?></p>
                        <p class="text-sm font-bold text-slate-800"><?php echo $val; ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Müşteri Kartı -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-2">
                    <div class="w-6 h-6 bg-indigo-100 rounded-lg flex items-center justify-center flex-shrink-0">
                        <svg class="w-3.5 h-3.5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                    </div>
                    <h2 class="font-black text-sm text-slate-800">İşletme / Müşteri</h2>
                    <?php if ($customer): ?>
                    <a href="<?php echo $baseUrl; ?>/qodmin/businesses/<?php echo htmlspecialchars($customer['customer_id'] ?? ''); ?>" class="ml-auto text-xs font-bold text-indigo-600 hover:text-indigo-800">Profili Gör →</a>
                    <?php endif; ?>
                </div>
                <?php if ($customer): ?>
                <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <?php
                    $custName = trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? ''));
                    $custFields = [
                        ['Ad Soyad', htmlspecialchars($custName ?: '-')],
                        ['İşletme', htmlspecialchars($customer['company_name'] ?? '-')],
                        ['E-posta', htmlspecialchars($customer['email'] ?? '-')],
                        ['Telefon', htmlspecialchars($customer['phone'] ?? '-')],
                        ['Durum', !empty($customer['is_active']) ? '<span class="text-emerald-600 font-black">Aktif</span>' : '<span class="text-red-500 font-black">Pasif</span>'],
                    ];
                    foreach ($custFields as [$lbl, $val]):
                    ?>
                    <div>
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-wider mb-1"><?php echo $lbl; ?></p>
                        <p class="text-sm font-bold text-slate-800"><?php echo $val; ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="p-5 text-sm text-slate-500">Müşteri bilgisi bulunamadı.</div>
                <?php endif; ?>
            </div>

            <!-- Aksiyonlar -->
            <?php if ($status === 'cancelled' || $status === 'expired'): ?>
            <div class="bg-red-50 border border-red-200 rounded-2xl p-5">
                <div class="flex items-start gap-3 mb-4">
                    <div class="w-8 h-8 bg-red-100 rounded-xl flex items-center justify-center flex-shrink-0 mt-0.5">
                        <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    </div>
                    <div>
                        <h2 class="font-black text-sm text-red-800 mb-1">
                            <?php echo $status === 'cancelled' ? 'Abonelik İptal Edilmiş' : 'Abonelik Süresi Dolmuş'; ?>
                        </h2>
                        <p class="text-xs text-red-600">
                            <?php if ($status === 'cancelled'): ?>
                                Bu işletmenin aboneliği iptal edilmiştir. İşletme pasif duruma alınmıştır. Aboneliği tekrar aktifleştirmek için aşağıdaki butona tıklayın.
                            <?php else: ?>
                                Bu işletmenin abonelik süresi dolmuştur. Yeni bir abonelik başlatmak için aktifleştirin.
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
                <button type="button" onclick="subAction('activate')"
                        class="w-full px-6 py-3 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-black text-sm shadow-sm transition-all flex items-center justify-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    Aboneliği Yeniden Aktifleştir
                </button>
            </div>
            <?php elseif (in_array($status, ['pending', 'active', 'suspended'])): ?>
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
                <h2 class="font-black text-sm text-slate-800 mb-4">Aksiyon</h2>
                <div class="flex flex-wrap gap-3">
                    <?php if ($status === 'pending' || $status === 'suspended'): ?>
                    <button type="button" onclick="subAction('activate')"
                            class="px-6 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-black text-sm shadow-sm transition-all">
                        ✓ Aktifleştir
                    </button>
                    <?php endif; ?>
                    <?php if ($status === 'active'): ?>
                    <button type="button" onclick="subAction('cancel')"
                            class="px-6 py-2.5 bg-red-600 hover:bg-red-700 text-white rounded-xl font-black text-sm shadow-sm transition-all">
                        İptal Et
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- ── Sağ: Ödemeler ── -->
        <div class="xl:col-span-1">
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden h-full">
                <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-2">
                    <div class="w-6 h-6 bg-emerald-100 rounded-lg flex items-center justify-center flex-shrink-0">
                        <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <h2 class="font-black text-sm text-slate-800">Ödeme Geçmişi</h2>
                    <span class="ml-auto text-xs font-bold text-slate-400"><?php echo count($payments); ?> kayıt</span>
                </div>
                <div class="p-4">
                    <?php if (empty($payments)): ?>
                    <div class="py-8 text-center">
                        <div class="w-12 h-12 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-2">
                            <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                        </div>
                        <p class="text-sm font-bold text-slate-600 mb-1">Ödeme kaydı yok</p>
                        <p class="text-xs text-slate-400">Henüz ödeme işlemi yapılmamış.</p>
                    </div>
                    <?php else: ?>
                    <div class="space-y-3 max-h-[500px] overflow-y-auto pr-1" style="scrollbar-width: thin;">
                        <?php foreach ($payments as $pay):
                            $pAmt = floatval($pay['amount'] ?? 0);
                            $pStatus = $pay['payment_status'] ?? 'pending';
                            $pStatusLbl = $paymentStatusLabels[$pStatus] ?? $pStatus;
                            $pStatusClr = $paymentStatusColors[$pStatus] ?? 'bg-slate-100 text-slate-600';
                            $pMethod = $pay['payment_method'] ?? '';
                            $pMethodLbl = $methodLabels[$pMethod] ?? ($pMethod ?: '-');
                            $pDate = $pay['payment_date'] ?? '';
                        ?>
                        <div class="rounded-xl border border-slate-200 bg-slate-50/60 p-3.5">
                            <div class="flex items-start justify-between mb-2">
                                <div>
                                    <p class="text-base font-black text-slate-800">
                                        ₺<?php echo number_format($pAmt, 2, ',', '.'); ?>
                                    </p>
                                    <p class="text-xs text-slate-500 mt-0.5"><?php echo htmlspecialchars($pMethodLbl); ?></p>
                                </div>
                                <span class="px-2 py-1 rounded-lg text-[10px] font-black <?php echo $pStatusClr; ?>">
                                    <?php echo htmlspecialchars($pStatusLbl); ?>
                                </span>
                            </div>
                            <?php if ($pDate): ?>
                            <p class="text-xs text-slate-500 flex items-center gap-1">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                <?php echo date('d.m.Y H:i', strtotime($pDate)); ?>
                            </p>
                            <?php endif; ?>
                            <?php if (!empty($pay['gateway_transaction_id'])): ?>
                            <p class="text-[10px] text-slate-400 font-mono mt-1 truncate">TXN: <?php echo htmlspecialchars($pay['gateway_transaction_id']); ?></p>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

  </div>
</div>
<script>
const _subId = <?php echo json_encode($subscription['subscription_id'] ?? ''); ?>;
const _csrfToken = <?php echo json_encode($csrfToken); ?>;
const _baseUrl = <?php echo json_encode($baseUrl); ?>;

async function subAction(action) {
    const labels = { activate: 'Aktifleştir', cancel: 'İptal Et' };
    const msgs = { activate: 'Bu aboneliği aktifleştirmek istediğinize emin misiniz?', cancel: 'Bu aboneliği iptal etmek istediğinize emin misiniz?' };

    let confirmed = false;
    if (window.NotificationManager && window.NotificationManager.confirm) {
        confirmed = await window.NotificationManager.confirm(msgs[action] || 'Emin misiniz?', 'Onay');
    } else {
        confirmed = confirm(msgs[action] || 'Emin misiniz?');
    }
    if (!confirmed) return;

    try {
        const res = await fetch(_baseUrl + '/qodmin/subscriptions/' + _subId + '/' + action, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': _csrfToken
            },
            body: JSON.stringify({ csrf_token: _csrfToken })
        });
        const txt = await res.text();
        let data = {};
        try { data = JSON.parse(txt); } catch(e) {}

        if (data.success) {
            if (window.NotificationManager) window.NotificationManager.success(data.message || 'İşlem başarılı');
            setTimeout(() => window.location.reload(), 800);
        } else {
            if (window.NotificationManager) window.NotificationManager.error(data.message || 'İşlem başarısız');
        }
    } catch(err) {
        if (window.NotificationManager) window.NotificationManager.error('Bağlantı hatası: ' + err.message);
    }
}
</script>
