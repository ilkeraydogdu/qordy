<?php
/**
 * Süper admin: Kısa linkler & tıklama analizi (pfdk.me entegrasyonu).
 *
 * Vars: $rows, $summary, $campaigns, $channels, $filters, $configured
 */
$rows       = $rows ?? [];
$summary    = $summary ?? ['total_links' => 0, 'total_clicks' => 0, 'unique_customers' => 0, 'active_campaigns' => 0];
$campaigns  = $campaigns ?? [];
$channels   = $channels ?? [];
$filters    = $filters ?? [];
$configured = !empty($configured);
$base       = BASE_URL;

$channelLabels = [
    'email'    => 'E-posta',
    'whatsapp' => 'WhatsApp',
    'sms'      => 'SMS',
    'web'      => 'Web',
    'mobile'   => 'Mobil',
];
$channelColors = [
    'email'    => 'bg-indigo-100 text-indigo-700',
    'whatsapp' => 'bg-emerald-100 text-emerald-700',
    'sms'      => 'bg-amber-100 text-amber-700',
    'web'      => 'bg-slate-100 text-slate-700',
    'mobile'   => 'bg-blue-100 text-blue-700',
];
?>

<div class="q-page animate-slide-up">
  <div class="q-container">

    <!-- Header -->
    <div class="relative overflow-hidden bg-gradient-to-br from-indigo-900 via-indigo-800 to-purple-900 text-white p-5 sm:p-7 rounded-2xl shadow-xl border border-white/10">
        <div class="pointer-events-none absolute -right-10 -top-10 h-40 w-40 rounded-full bg-purple-500/30 blur-3xl"></div>
        <div class="relative flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <p class="text-[10px] font-black uppercase tracking-[0.22em] text-indigo-300 mb-1">Qodmin &rsaquo; Pazarlama</p>
                <h1 class="text-2xl sm:text-3xl font-black tracking-tight mb-1">Kısa Linkler & Tıklama Analizi</h1>
                <p class="text-indigo-200 text-sm">E-posta ve WhatsApp üzerinden paylaşılan tüm kullanıcı linklerinin gerçek zamanlı performansı (pfdk.me)</p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <button onclick="syncAllShortLinks(this)" class="inline-flex items-center gap-2 rounded-xl bg-white text-indigo-900 px-4 py-2.5 text-sm font-bold shadow-lg hover:bg-indigo-50 transition disabled:opacity-50 disabled:cursor-not-allowed" <?php echo $configured ? '' : 'disabled title="pfdk.me API anahtarı yapılandırılmamış"'; ?>>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    Tümünü Senkronla
                </button>
            </div>
        </div>
    </div>

    <?php if (!$configured): ?>
    <div class="bg-amber-50 border border-amber-200 text-amber-900 rounded-xl p-4 text-sm">
        <strong>pfdk.me API anahtarı yapılandırılmamış.</strong>
        <code>.env</code> içine <code>PFDK_API_KEY</code> ekleyin; kısa linkler devre dışı — kampanya maillerinde düz URL kullanılacak.
    </div>
    <?php endif; ?>

    <!-- Summary cards -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div class="bg-white rounded-2xl p-4 border border-slate-200 shadow-sm">
            <div class="text-[10px] uppercase tracking-widest text-slate-500 font-bold mb-1">Toplam Link</div>
            <div class="text-2xl font-black text-slate-900"><?php echo (int)$summary['total_links']; ?></div>
        </div>
        <div class="bg-white rounded-2xl p-4 border border-slate-200 shadow-sm">
            <div class="text-[10px] uppercase tracking-widest text-slate-500 font-bold mb-1">Toplam Tıklama</div>
            <div class="text-2xl font-black text-indigo-700"><?php echo (int)$summary['total_clicks']; ?></div>
        </div>
        <div class="bg-white rounded-2xl p-4 border border-slate-200 shadow-sm">
            <div class="text-[10px] uppercase tracking-widest text-slate-500 font-bold mb-1">Tekil Müşteri</div>
            <div class="text-2xl font-black text-emerald-700"><?php echo (int)$summary['unique_customers']; ?></div>
        </div>
        <div class="bg-white rounded-2xl p-4 border border-slate-200 shadow-sm">
            <div class="text-[10px] uppercase tracking-widest text-slate-500 font-bold mb-1">Aktif Kampanya</div>
            <div class="text-2xl font-black text-purple-700"><?php echo (int)$summary['active_campaigns']; ?></div>
        </div>
    </div>

    <!-- Filters -->
    <form method="GET" class="bg-white rounded-2xl border border-slate-200 p-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
        <input type="text" name="q" value="<?php echo htmlspecialchars($filters['q'] ?? '', ENT_QUOTES); ?>" placeholder="Link / başlık / kod..."
               class="border border-slate-300 rounded-xl px-3 py-2 text-sm col-span-1 lg:col-span-2 focus:outline-none focus:ring-2 focus:ring-indigo-500">
        <select name="campaign" class="border border-slate-300 rounded-xl px-3 py-2 text-sm">
            <option value="">Tüm kampanyalar</option>
            <?php foreach ($campaigns as $c): ?>
                <option value="<?php echo htmlspecialchars($c, ENT_QUOTES); ?>" <?php echo ($filters['campaign'] ?? '') === $c ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($c); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <select name="channel" class="border border-slate-300 rounded-xl px-3 py-2 text-sm">
            <option value="">Tüm kanallar</option>
            <?php foreach ($channels as $c): ?>
                <option value="<?php echo htmlspecialchars($c, ENT_QUOTES); ?>" <?php echo ($filters['channel'] ?? '') === $c ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($channelLabels[$c] ?? $c); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <div class="flex gap-2">
            <button type="submit" class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl px-4 py-2 text-sm font-bold">Filtrele</button>
            <a href="<?php echo $base; ?>/qodmin/short-links" class="px-3 py-2 text-sm text-slate-600 hover:text-slate-900 flex items-center">Temizle</a>
        </div>
    </form>

    <!-- Table -->
    <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr class="text-left text-[11px] font-black uppercase tracking-wider text-slate-500">
                        <th class="px-4 py-3">Kısa Link</th>
                        <th class="px-4 py-3">Müşteri</th>
                        <th class="px-4 py-3">Kampanya / Kanal</th>
                        <th class="px-4 py-3 text-center">Tıklama</th>
                        <th class="px-4 py-3">Oluşturma</th>
                        <th class="px-4 py-3 text-right">İşlem</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($rows)): ?>
                    <tr><td colspan="6" class="px-4 py-12 text-center text-slate-400">Henüz kısa link yok.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $r):
                        $customerName = trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? ''));
                        $companyName  = $r['company_name'] ?? '';
                        $email        = $r['customer_email'] ?? '';
                        $channel      = $r['channel'] ?? '';
                        $channelBadge = $channelColors[$channel] ?? 'bg-slate-100 text-slate-700';
                        $campaign     = $r['campaign'] ?? '—';
                        $clicks       = (int)($r['click_count'] ?? 0);
                        $clickBg      = $clicks > 0 ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-500';
                    ?>
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 align-top">
                            <a href="<?php echo htmlspecialchars($r['short_url']); ?>" target="_blank" class="text-indigo-700 font-semibold hover:underline">
                                <?php echo htmlspecialchars($r['short_url']); ?>
                            </a>
                            <div class="text-[11px] text-slate-400 mt-1 truncate max-w-md" title="<?php echo htmlspecialchars($r['long_url']); ?>">
                                → <?php echo htmlspecialchars($r['long_url']); ?>
                            </div>
                            <?php if (!empty($r['title'])): ?>
                            <div class="text-[11px] text-slate-500 mt-0.5 italic"><?php echo htmlspecialchars($r['title']); ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 align-top">
                            <?php if ($r['customer_id']): ?>
                                <div class="font-semibold text-slate-800 text-sm"><?php echo htmlspecialchars($companyName ?: $customerName ?: $r['customer_id']); ?></div>
                                <div class="text-[11px] text-slate-500"><?php echo htmlspecialchars($email); ?></div>
                                <div class="text-[11px] text-slate-400 font-mono"><?php echo htmlspecialchars($r['customer_id']); ?></div>
                            <?php else: ?>
                                <span class="text-slate-400 italic">Genel</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 align-top">
                            <div class="text-xs font-semibold text-slate-700 mb-1"><?php echo htmlspecialchars($campaign); ?></div>
                            <?php if ($channel): ?>
                            <span class="inline-block px-2 py-0.5 rounded-md text-[11px] font-bold <?php echo $channelBadge; ?>">
                                <?php echo htmlspecialchars($channelLabels[$channel] ?? $channel); ?>
                            </span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-center align-top">
                            <span class="inline-flex items-center justify-center min-w-[44px] h-8 rounded-lg <?php echo $clickBg; ?> text-sm font-black px-2">
                                <?php echo $clicks; ?>
                            </span>
                            <?php if (!empty($r['last_synced_at'])): ?>
                            <div class="text-[10px] text-slate-400 mt-1"><?php echo date('d.m H:i', strtotime($r['last_synced_at'])); ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 align-top text-slate-500 text-xs"><?php echo date('d.m.Y H:i', strtotime($r['created_at'])); ?></td>
                        <td class="px-4 py-3 align-top text-right">
                            <button onclick="syncOne('<?php echo htmlspecialchars($r['pfdk_id'], ENT_QUOTES); ?>', this)" class="text-[11px] px-2 py-1 rounded-md bg-indigo-50 text-indigo-700 hover:bg-indigo-100 font-semibold" title="Bu linkin istatistiğini yenile">↻ Senkronla</button>
                            <button onclick="showAnalytics('<?php echo htmlspecialchars($r['pfdk_id'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($r['short_code'], ENT_QUOTES); ?>')" class="text-[11px] px-2 py-1 rounded-md bg-slate-50 text-slate-700 hover:bg-slate-100 font-semibold ml-1">Detay</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Analytics modal -->
<div id="analytics-modal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4">
    <div class="bg-white rounded-2xl w-full max-w-3xl max-h-[80vh] overflow-hidden flex flex-col">
        <div class="flex items-center justify-between p-4 border-b border-slate-200">
            <h3 class="font-black text-lg"><span id="analytics-title">Tıklama Detayı</span></h3>
            <button onclick="closeAnalytics()" class="text-slate-400 hover:text-slate-700">✕</button>
        </div>
        <div id="analytics-body" class="p-4 overflow-y-auto text-sm text-slate-700">Yükleniyor...</div>
    </div>

  </div>
</div>
<script>
(function(){
    const base = <?php echo json_encode(rtrim($base, '/')); ?>;

    const fetchOpts = function(extra) { return Object.assign({ credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } }, extra || {}); };

    window.syncOne = function(pfdkId, btn) {
        btn.disabled = true; btn.textContent = '...';
        fetch(base + '/api/qodmin/short-links/' + encodeURIComponent(pfdkId) + '/sync', fetchOpts({ method: 'POST' }))
            .then(r => r.json())
            .then(j => {
                btn.disabled = false; btn.textContent = '↻ Senkronla';
                if (j && j.success) { location.reload(); }
                else { alert('Senkron hatası: ' + (j && j.error ? j.error : 'bilinmeyen')); }
            })
            .catch(e => { btn.disabled = false; btn.textContent = '↻ Senkronla'; alert(e.message); });
    };

    window.syncAllShortLinks = function(btn) {
        if (btn.disabled) return;
        btn.disabled = true; const original = btn.innerHTML; btn.innerHTML = 'Senkronlanıyor...';
        fetch(base + '/api/qodmin/short-links/sync-all', fetchOpts({ method: 'POST' }))
            .then(r => r.json())
            .then(j => {
                btn.disabled = false; btn.innerHTML = original;
                if (j && j.success) { alert('Senkronlandı: ' + (j.synced || 0) + ' link'); location.reload(); }
                else { alert('Hata: ' + (j && j.error ? j.error : 'bilinmeyen')); }
            })
            .catch(e => { btn.disabled = false; btn.innerHTML = original; alert(e.message); });
    };

    window.showAnalytics = function(pfdkId, code) {
        const modal = document.getElementById('analytics-modal');
        const body = document.getElementById('analytics-body');
        const title = document.getElementById('analytics-title');
        title.textContent = 'Tıklama Detayı — ' + code;
        body.innerHTML = 'Yükleniyor...';
        modal.classList.remove('hidden'); modal.classList.add('flex');
        fetch(base + '/api/qodmin/short-links/' + encodeURIComponent(pfdkId) + '/analytics', fetchOpts())
            .then(r => r.json())
            .then(j => {
                if (!j || !j.success || !j.data || !j.data.data) {
                    body.innerHTML = '<div class="text-slate-500">Veri alınamadı' + (j && j.error ? ': ' + j.error : '') + '</div>';
                    return;
                }
                const entries = j.data.data;
                if (!entries.length) {
                    body.innerHTML = '<div class="text-slate-500 italic">Henüz tıklama kaydı yok.</div>';
                    return;
                }
                const rowsHtml = entries.map(function(e) {
                    const ts = e.timestamp ? new Date(e.timestamp).toLocaleString('tr-TR') : '';
                    const ua = (e.userAgent || '').substring(0, 80);
                    const country = e.country || '—';
                    const ip = e.ip || '';
                    return '<tr><td class="px-2 py-1">' + ts + '</td><td class="px-2 py-1">' + country + '</td><td class="px-2 py-1 font-mono text-xs">' + ip + '</td><td class="px-2 py-1 text-xs text-slate-500">' + ua + '</td></tr>';
                }).join('');
                body.innerHTML = '<div class="text-xs text-slate-500 mb-2">Toplam kayıt: ' + entries.length + '</div>' +
                    '<table class="w-full text-xs"><thead class="bg-slate-50"><tr>' +
                    '<th class="px-2 py-1 text-left">Zaman</th><th class="px-2 py-1 text-left">Ülke</th><th class="px-2 py-1 text-left">IP</th><th class="px-2 py-1 text-left">User Agent</th>' +
                    '</tr></thead><tbody class="divide-y divide-slate-100">' + rowsHtml + '</tbody></table>';
            })
            .catch(e => { body.innerHTML = '<div class="text-red-600">Hata: ' + e.message + '</div>'; });
    };

    window.closeAnalytics = function() {
        const modal = document.getElementById('analytics-modal');
        modal.classList.add('hidden'); modal.classList.remove('flex');
    };
})();
</script>
