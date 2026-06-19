<?php
/**
 * Super-admin: single-business queue detail.
 *
 * Read-only snapshot — super admins can see the same state the business owner
 * sees at /business/queue, but without switching tenants. For mutating actions
 * they still "Log in as" via the Businesses page.
 */
require_once __DIR__ . '/../../../helpers/translations.php';

/** @var array $business */
$business = $business ?? [];
/** @var array $settings */
$settings = $settings ?? [];
/** @var array $active */
$active   = $active   ?? [];
/** @var array $recent */
$recent   = $recent   ?? [];
/** @var array $stats */
$stats    = $stats    ?? [];

$tenantId = (string)($business['customer_id'] ?? '');
$company  = (string)($business['company_name'] ?? '—');
$subdomain = (string)($business['subdomain'] ?? '');

$fmtDateTime = static function ($v) {
    if (!$v) return '—';
    $ts = strtotime((string)$v);
    return $ts ? date('d.m.Y H:i', $ts) : '—';
};
$fmtRel = static function ($v) {
    if (!$v) return '—';
    $ts = strtotime((string)$v);
    if (!$ts) return '—';
    $d = time() - $ts;
    if ($d < 60) return $d . 's önce';
    if ($d < 3600) return floor($d/60) . 'd önce';
    if ($d < 86400) return floor($d/3600) . 's önce';
    return floor($d/86400) . 'g önce';
};

$statusMap = [
    'WAITING'   => ['Bekliyor',  'bg-amber-100 text-amber-700'],
    'NOTIFIED'  => ['Çağrıldı',  'bg-sky-100 text-sky-700'],
    'SEATED'    => ['Masada',    'bg-emerald-100 text-emerald-700'],
    'CANCELLED' => ['İptal',     'bg-slate-200 text-slate-600'],
    'NO_SHOW'   => ['Gelmedi',   'bg-rose-100 text-rose-700'],
    'EXPIRED'   => ['Süresi doldu', 'bg-slate-200 text-slate-600'],
];

$renderEntry = static function (array $e) use ($statusMap, $fmtDateTime) {
    $status = strtoupper((string)($e['status'] ?? ''));
    [$lbl, $cls] = $statusMap[$status] ?? [$status ?: '—', 'bg-slate-200 text-slate-600'];
    $name = trim(($e['name'] ?? '') . ' ' . ($e['surname'] ?? '')) ?: '—';
    $phone = (string)($e['phone'] ?? '');
    ?>
    <tr class="border-b border-slate-100 hover:bg-slate-50/60">
        <td class="py-2.5 px-3 text-sm font-black text-slate-900">
            #<?= (int)($e['queue_number'] ?? 0) ?>
        </td>
        <td class="py-2.5 px-3 text-sm text-slate-800"><?= htmlspecialchars($name) ?></td>
        <td class="py-2.5 px-3 text-sm text-slate-600 font-mono"><?= htmlspecialchars($phone) ?></td>
        <td class="py-2.5 px-3 text-sm text-center text-slate-700"><?= (int)($e['party_size'] ?? 1) ?></td>
        <td class="py-2.5 px-3 text-sm">
            <span class="inline-block px-2 py-0.5 rounded text-[11px] font-bold <?= $cls ?>"><?= htmlspecialchars($lbl) ?></span>
        </td>
        <td class="py-2.5 px-3 text-xs text-slate-600"><?= htmlspecialchars($fmtDateTime($e['created_at'] ?? null)) ?></td>
    </tr>
    <?php
};

$queueEnabled   = (int)($settings['is_enabled'] ?? 0) === 1;
$queueAccepting = (int)($settings['is_accepting_queue'] ?? 0) === 1;
?>

<div class="q-page animate-slide-up">
  <div class="q-container">

    <!-- Breadcrumb + header -->
    <div class="flex items-center gap-2 text-xs font-semibold text-slate-500">
        <a href="<?php echo BASE_URL; ?>/qodmin/queue" class="hover:text-orange-600">Sıra Yönetimi</a>
        <span>/</span>
        <span class="text-slate-900"><?= htmlspecialchars($company) ?></span>
    </div>

    <div class="bg-white p-6 rounded-2xl shadow-soft border border-slate-100 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-black text-slate-900"><?= htmlspecialchars($company) ?></h1>
            <div class="mt-1 text-sm text-slate-600">
                <?php if ($subdomain !== ''): ?>
                    <a href="https://<?= htmlspecialchars($subdomain) ?>.qordy.com/sira" target="_blank" rel="noopener"
                       class="text-orange-600 hover:underline font-mono"><?= htmlspecialchars($subdomain) ?>.qordy.com/sira</a>
                <?php endif; ?>
            </div>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="<?php echo BASE_URL; ?>/qodmin/businesses/<?= htmlspecialchars((string)($business['customer_id'] ?? '')) ?>/login-as"
               class="px-4 py-2 rounded-lg bg-orange-500 text-white font-black text-sm hover:bg-orange-600">
                İşletmeye giriş yap
            </a>
            <a href="<?php echo BASE_URL; ?>/qodmin/queue"
               class="px-4 py-2 rounded-lg border-2 border-slate-200 text-slate-700 font-bold text-sm hover:bg-slate-50">
                İşletme seçimine dön
            </a>
        </div>
    </div>

    <!-- Status strip -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4">
            <div class="text-xs font-semibold text-slate-500 uppercase">Sistem durumu</div>
            <div class="mt-1 text-base font-black <?= $queueEnabled ? 'text-emerald-700' : 'text-slate-500' ?>">
                <?= $queueEnabled ? 'Açık' : 'Kapalı' ?>
            </div>
            <div class="mt-1 text-[11px] text-slate-500">
                Kabul: <?= $queueAccepting ? 'Sıra modu' : 'Karşılama modu' ?>
            </div>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4">
            <div class="text-xs font-semibold text-slate-500 uppercase">Aktif sırada</div>
            <div class="mt-1 text-2xl font-black text-slate-900"><?= (int)($stats['active_count'] ?? 0) ?></div>
            <div class="mt-1 text-[11px] text-slate-500">WAITING + NOTIFIED</div>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4">
            <div class="text-xs font-semibold text-slate-500 uppercase">Bugün</div>
            <div class="mt-1 text-2xl font-black text-slate-900"><?= (int)($stats['today_total'] ?? 0) ?></div>
            <div class="mt-1 text-[11px] text-slate-500">Dün: <?= (int)($stats['yesterday_total'] ?? 0) ?></div>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4">
            <div class="text-xs font-semibold text-slate-500 uppercase">Toplam</div>
            <div class="mt-1 text-2xl font-black text-slate-900"><?= (int)($stats['lifetime_total'] ?? 0) ?></div>
            <div class="mt-1 text-[11px] text-slate-500">
                Oturtulan: <?= (int)($stats['seated_total'] ?? 0) ?> · İptal: <?= (int)($stats['cancelled_total'] ?? 0) ?>
            </div>
        </div>
    </div>

    <!-- Settings summary + Active queue side by side -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

        <!-- Settings summary -->
        <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-5 space-y-3 lg:col-span-1">
            <h2 class="text-sm font-black uppercase tracking-wide text-slate-700">Ayarlar</h2>
            <dl class="text-sm space-y-2">
                <div class="flex justify-between gap-4"><dt class="text-slate-500">Tema</dt><dd class="font-semibold text-slate-800 uppercase"><?= htmlspecialchars((string)($settings['display_theme'] ?? '—')) ?></dd></div>
                <div class="flex justify-between gap-4"><dt class="text-slate-500">Varsayılan dil</dt><dd class="font-semibold text-slate-800 uppercase"><?= htmlspecialchars((string)($settings['default_language'] ?? '—')) ?></dd></div>
                <div class="flex justify-between gap-4"><dt class="text-slate-500">Ort. bekleme</dt><dd class="font-semibold text-slate-800"><?= (int)($settings['average_wait_minutes'] ?? 0) ?> dk</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-slate-500">Maks. grup</dt><dd class="font-semibold text-slate-800"><?= (int)($settings['max_party_size'] ?? 0) ?></dd></div>
                <div class="flex justify-between gap-4"><dt class="text-slate-500">WhatsApp bildirim</dt><dd class="font-semibold <?= (int)($settings['whatsapp_enabled'] ?? 0) ? 'text-emerald-700' : 'text-slate-400' ?>"><?= (int)($settings['whatsapp_enabled'] ?? 0) ? 'Açık' : 'Kapalı' ?></dd></div>
                <div class="flex justify-between gap-4"><dt class="text-slate-500">E-posta bildirim</dt><dd class="font-semibold <?= (int)($settings['email_enabled'] ?? 0) ? 'text-emerald-700' : 'text-slate-400' ?>"><?= (int)($settings['email_enabled'] ?? 0) ? 'Açık' : 'Kapalı' ?></dd></div>
                <div class="flex justify-between gap-4"><dt class="text-slate-500">Son giriş</dt><dd class="font-semibold text-slate-800"><?= htmlspecialchars($fmtRel($stats['last_entry_at'] ?? null)) ?></dd></div>
            </dl>
            <?php if (!empty($settings['welcome_title']) || !empty($settings['display_title'])): ?>
                <div class="pt-3 border-t border-slate-100 space-y-2 text-sm">
                    <?php if (!empty($settings['welcome_title'])): ?>
                        <div><span class="text-slate-500">Karşılama başlığı:</span> <span class="font-semibold text-slate-800"><?= htmlspecialchars((string)$settings['welcome_title']) ?></span></div>
                    <?php endif; ?>
                    <?php if (!empty($settings['display_title'])): ?>
                        <div><span class="text-slate-500">Sıra başlığı:</span> <span class="font-semibold text-slate-800"><?= htmlspecialchars((string)$settings['display_title']) ?></span></div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Active queue -->
        <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-5 lg:col-span-2">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-sm font-black uppercase tracking-wide text-slate-700">Şu an sırada</h2>
                <span class="text-xs font-bold text-slate-500"><?= count($active) ?> kayıt</span>
            </div>
            <?php if (empty($active)): ?>
                <div class="text-center py-8 text-sm text-slate-500">Aktif sırada bekleyen yok.</div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="q-table">
                        <thead class="bg-slate-50/70">
                            <tr class="border-b border-slate-200">
                                <th class="text-left py-2 px-3 text-[11px] font-black uppercase text-slate-600">No</th>
                                <th class="text-left py-2 px-3 text-[11px] font-black uppercase text-slate-600">Ad</th>
                                <th class="text-left py-2 px-3 text-[11px] font-black uppercase text-slate-600">Telefon</th>
                                <th class="text-center py-2 px-3 text-[11px] font-black uppercase text-slate-600">Kişi</th>
                                <th class="text-left py-2 px-3 text-[11px] font-black uppercase text-slate-600">Durum</th>
                                <th class="text-left py-2 px-3 text-[11px] font-black uppercase text-slate-600">Giriş</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($active as $e) $renderEntry($e); ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recent history -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-5">
        <div class="flex items-center justify-between mb-3">
            <h2 class="text-sm font-black uppercase tracking-wide text-slate-700">Son girişler (50)</h2>
            <span class="text-xs font-bold text-slate-500"><?= count($recent) ?> kayıt</span>
        </div>
        <?php if (empty($recent)): ?>
            <div class="text-center py-8 text-sm text-slate-500">Henüz hiç giriş yok.</div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="q-table">
                    <thead class="bg-slate-50/70">
                        <tr class="border-b border-slate-200">
                            <th class="text-left py-2 px-3 text-[11px] font-black uppercase text-slate-600">No</th>
                            <th class="text-left py-2 px-3 text-[11px] font-black uppercase text-slate-600">Ad</th>
                            <th class="text-left py-2 px-3 text-[11px] font-black uppercase text-slate-600">Telefon</th>
                            <th class="text-center py-2 px-3 text-[11px] font-black uppercase text-slate-600">Kişi</th>
                            <th class="text-left py-2 px-3 text-[11px] font-black uppercase text-slate-600">Durum</th>
                            <th class="text-left py-2 px-3 text-[11px] font-black uppercase text-slate-600">Giriş</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent as $e) $renderEntry($e); ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

  </div>
</div>
