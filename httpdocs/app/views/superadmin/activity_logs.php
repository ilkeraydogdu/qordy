<?php
$logs = $logs ?? [];
$filters = $filters ?? [];
$businesses = $businesses ?? [];
$base = BASE_URL;

$exportQs = array_merge($_GET, ['export' => 'csv']);
$exportUrl = $base . '/qodmin/activity-logs?' . http_build_query($exportQs);

$actionColors = [
    'login'               => 'bg-emerald-100 text-emerald-700',
    'logout'              => 'bg-slate-100 text-slate-600',
    'impersonation_start' => 'bg-amber-100 text-amber-800',
    'impersonation_end'   => 'bg-blue-100 text-blue-700',
    'create'              => 'bg-indigo-100 text-indigo-700',
    'update'              => 'bg-purple-100 text-purple-700',
    'delete'              => 'bg-red-100 text-red-700',
];
$actionLabels = [
    'login'               => 'Giriş',
    'logout'              => 'Çıkış',
    'impersonation_start' => 'Müşteri Oturumu',
    'impersonation_end'   => 'Oturum İadesi',
    'create'              => 'Oluşturma',
    'update'              => 'Güncelleme',
    'delete'              => 'Silme',
];
$actorColors = [
    'superadmin' => 'bg-red-50 text-red-700 border border-red-200',
    'admin'      => 'bg-indigo-50 text-indigo-700 border border-indigo-200',
    'user'       => 'bg-slate-50 text-slate-600 border border-slate-200',
    'staff'      => 'bg-purple-50 text-purple-700 border border-purple-200',
    'system'     => 'bg-amber-50 text-amber-700 border border-amber-200',
];

$totalLogs = count($logs);
$loginCount = 0; $impersonationCount = 0;
foreach ($logs as $l) {
    $a = $l['action'] ?? '';
    if ($a === 'login') $loginCount++;
    elseif ($a === 'impersonation_start') $impersonationCount++;
}
?>

<div class="q-page animate-slide-up">
  <div class="q-container">

    <!-- Header -->
    <div class="relative overflow-hidden bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 text-white p-5 sm:p-7 rounded-2xl shadow-xl border border-white/10">
        <div class="pointer-events-none absolute -right-10 -top-10 h-40 w-40 rounded-full bg-slate-600/30 blur-3xl"></div>
        <div class="relative flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <p class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-400 mb-1">Qodmin &rsaquo; Raporlar</p>
                <h1 class="text-2xl sm:text-3xl font-black tracking-tight mb-1">Aktivite Günlüğü</h1>
                <p class="text-slate-400 text-sm">Giriş, çıkış, oturum ve işlem kayıtları</p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <div class="bg-white/10 rounded-xl px-4 py-2.5 text-center min-w-[60px]">
                    <div class="text-xl font-black"><?php echo $totalLogs; ?></div>
                    <div class="text-[10px] text-slate-400 uppercase tracking-wide">Kayıt</div>
                </div>
                <div class="bg-emerald-500/20 border border-emerald-400/30 rounded-xl px-4 py-2.5 text-center min-w-[60px]">
                    <div class="text-xl font-black text-emerald-300"><?php echo $loginCount; ?></div>
                    <div class="text-[10px] text-slate-400 uppercase tracking-wide">Giriş</div>
                </div>
                <?php if ($impersonationCount > 0): ?>
                <div class="bg-amber-500/20 border border-amber-400/30 rounded-xl px-4 py-2.5 text-center min-w-[60px]">
                    <div class="text-xl font-black text-amber-300"><?php echo $impersonationCount; ?></div>
                    <div class="text-[10px] text-slate-400 uppercase tracking-wide">İmpersonation</div>
                </div>
                <?php endif; ?>
                <div class="flex gap-2">
                    <a href="<?php echo htmlspecialchars($exportUrl); ?>"
                       class="flex items-center gap-1.5 px-3.5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-black rounded-xl transition-all shadow-sm">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        CSV
                    </a>
                    <a href="<?php echo $base; ?>/qodmin/dashboard"
                       class="flex items-center gap-1.5 px-3.5 py-2 bg-white/10 hover:bg-white/20 text-white text-xs font-bold rounded-xl transition-all">
                        ← Qodmin
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Form -->
    <form method="get" action="" class="bg-white rounded-xl shadow-sm border border-slate-200 p-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-8 gap-3">
            <div class="xl:col-span-2">
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">İşletme</label>
                <select name="business_id" class="w-full rounded-lg border border-slate-200 text-sm font-medium px-3 py-2 focus:border-indigo-400 outline-none">
                    <option value="">Tüm işletmeler</option>
                    <?php foreach ($businesses as $b): ?>
                    <option value="<?php echo htmlspecialchars($b['customer_id']); ?>"
                            <?php echo (($filters['business_id'] ?? '') === $b['customer_id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($b['company_name'] ?: $b['email']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Kullanıcı ID</label>
                <input type="text" name="user_id" value="<?php echo htmlspecialchars($filters['user_id'] ?? ''); ?>"
                       placeholder="user_id…"
                       class="w-full rounded-lg border border-slate-200 text-sm font-mono px-3 py-2 focus:border-indigo-400 outline-none">
            </div>
            <div>
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">İşlem</label>
                <select name="action" class="w-full rounded-lg border border-slate-200 text-sm font-medium px-3 py-2 focus:border-indigo-400 outline-none">
                    <option value="">Tüm işlemler</option>
                    <?php foreach ($actionLabels as $av => $al): ?>
                    <option value="<?php echo $av; ?>" <?php echo (($filters['action'] ?? '') === $av) ? 'selected' : ''; ?>><?php echo $al; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Varlık Tipi</label>
                <input type="text" name="entity_type" value="<?php echo htmlspecialchars($filters['entity_type'] ?? ''); ?>"
                       placeholder="order, product…"
                       class="w-full rounded-lg border border-slate-200 text-sm font-mono px-3 py-2 focus:border-indigo-400 outline-none">
            </div>
            <div>
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Başlangıç</label>
                <input type="date" name="date_from" value="<?php echo htmlspecialchars($filters['date_from'] ?? ''); ?>"
                       class="w-full rounded-lg border border-slate-200 text-sm px-3 py-2 focus:border-indigo-400 outline-none">
            </div>
            <div>
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Bitiş</label>
                <input type="date" name="date_to" value="<?php echo htmlspecialchars($filters['date_to'] ?? ''); ?>"
                       class="w-full rounded-lg border border-slate-200 text-sm px-3 py-2 focus:border-indigo-400 outline-none">
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="flex-1 rounded-lg bg-slate-900 text-white font-black py-2.5 text-sm hover:bg-slate-800 transition-all">Filtrele</button>
                <a href="<?php echo $base; ?>/qodmin/activity-logs" class="px-3 py-2.5 rounded-lg bg-slate-100 text-slate-600 text-sm font-bold hover:bg-slate-200 transition-all">✕</a>
            </div>
        </div>
    </form>

    <!-- Active filters -->
    <?php
    $activeFilters = array_filter($filters, fn($v) => $v !== '' && $v !== null);
    if (!empty($activeFilters)): ?>
    <div class="flex flex-wrap gap-2 items-center">
        <span class="text-xs font-bold text-slate-500">Aktif filtreler:</span>
        <?php foreach ($activeFilters as $fk => $fv): if ($fk === 'export') continue; ?>
        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-indigo-100 text-indigo-700 text-xs font-bold">
            <?php echo htmlspecialchars($fk); ?>: <?php echo htmlspecialchars($fv); ?>
        </span>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Log Table -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <?php if (empty($logs)): ?>
        <div class="text-center py-16">
            <div class="w-14 h-14 bg-slate-100 rounded-2xl flex items-center justify-center mx-auto mb-3">
                <svg class="w-6 h-6 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
            </div>
            <h3 class="font-black text-slate-700 mb-1">Kayıt bulunamadı</h3>
            <p class="text-sm text-slate-500">Bu filtreler için aktivite kaydı yok. Giriş işlemleri sonrası burada görünecektir.</p>
        </div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 border-b-2 border-slate-200">
                    <tr class="text-[9px] font-black text-slate-500 uppercase tracking-widest">
                        <th class="px-4 py-3">Tarih</th>
                        <th class="px-4 py-3">İşlem</th>
                        <th class="px-4 py-3 hidden sm:table-cell">Aktör</th>
                        <th class="px-4 py-3 hidden md:table-cell">İşletme</th>
                        <th class="px-4 py-3 hidden lg:table-cell">Kullanıcı</th>
                        <th class="px-4 py-3 hidden md:table-cell">IP</th>
                        <th class="px-4 py-3">Detay</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($logs as $row):
                        $action = $row['action'] ?? '';
                        $actionClr = $actionColors[$action] ?? 'bg-slate-100 text-slate-600';
                        $actionLbl = $actionLabels[$action] ?? $action;
                        $actorType = $row['actor_type'] ?? 'user';
                        $actorClr = $actorColors[$actorType] ?? $actorColors['user'];
                        $meta = $row['metadata'] ?? '';
                        $metaArr = [];
                        if ($meta) { try { $metaArr = json_decode($meta, true) ?: []; } catch(\Exception $e){} }
                        $metaDisplay = [];
                        foreach ($metaArr as $mk => $mv) {
                            if (is_scalar($mv)) $metaDisplay[] = htmlspecialchars($mk) . ': ' . htmlspecialchars((string)$mv);
                        }
                    ?>
                    <tr class="hover:bg-slate-50/60 transition-colors">
                        <td class="px-4 py-3 whitespace-nowrap">
                            <div class="text-xs font-bold text-slate-700"><?php echo date('d.m.Y', strtotime($row['created_at'] ?? '')); ?></div>
                            <div class="text-[10px] text-slate-400"><?php echo date('H:i:s', strtotime($row['created_at'] ?? '')); ?></div>
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-black <?php echo $actionClr; ?>">
                                <?php echo htmlspecialchars($actionLbl); ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 hidden sm:table-cell">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[9px] font-black <?php echo $actorClr; ?>">
                                <?php echo htmlspecialchars($actorType); ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 hidden md:table-cell">
                            <span class="text-xs text-slate-600 font-mono">
                                <?php echo $row['business_id'] ? htmlspecialchars(mb_strtoupper(mb_substr($row['business_id'], -8))) : '<span class="text-slate-300">—</span>'; ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 hidden lg:table-cell">
                            <span class="text-xs text-slate-600 font-mono">
                                <?php echo $row['user_id'] ? htmlspecialchars(mb_substr($row['user_id'], 0, 16)) . (mb_strlen($row['user_id']) > 16 ? '…' : '') : '<span class="text-slate-300">—</span>'; ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 hidden md:table-cell">
                            <span class="text-xs text-slate-500"><?php echo htmlspecialchars($row['ip_address'] ?? ''); ?></span>
                        </td>
                        <td class="px-4 py-3">
                            <?php if (!empty($metaDisplay)): ?>
                            <div class="text-[10px] text-slate-500 space-y-0.5 max-w-[200px]">
                                <?php foreach (array_slice($metaDisplay, 0, 3) as $md): ?>
                                <div class="truncate"><?php echo $md; ?></div>
                                <?php endforeach; ?>
                                <?php if (count($metaDisplay) > 3): ?>
                                <div class="text-slate-400">+<?php echo count($metaDisplay) - 3; ?> daha…</div>
                                <?php endif; ?>
                            </div>
                            <?php elseif (!empty($row['entity_type'])): ?>
                            <span class="text-[10px] text-slate-400"><?php echo htmlspecialchars($row['entity_type']); ?> #<?php echo htmlspecialchars($row['entity_id'] ?? ''); ?></span>
                            <?php else: ?>
                            <span class="text-slate-300 text-xs">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-t border-slate-100 flex items-center justify-between">
            <p class="text-xs text-slate-500"><?php echo $totalLogs; ?> kayıt gösteriliyor (max 300)</p>
            <a href="<?php echo htmlspecialchars($exportUrl); ?>" class="text-xs font-bold text-emerald-600 hover:text-emerald-800">CSV olarak indir ↓</a>
        </div>
        <?php endif; ?>
    </div>

  </div>
</div>
