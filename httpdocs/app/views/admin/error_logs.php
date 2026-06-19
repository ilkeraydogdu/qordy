<div class="q-page q-biz-theme animate-slide-up">
  <div class="q-container">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 sm:gap-6">
        <div>
            <h1 class="text-2xl sm:text-3xl lg:text-4xl font-black text-slate-900 tracking-tighter">Hata Logları</h1>
            <p class="text-sm sm:text-base text-slate-600 mt-2">Sistem hata log kayıtları</p>
        </div>
        <div class="flex gap-2 sm:gap-3">
            <button onclick="deleteAllErrors()" class="px-4 py-2.5 bg-red-500 text-white rounded-lg font-black uppercase text-xs tracking-wider shadow-lg hover:shadow-xl hover:bg-red-600 active:scale-95 transition-all flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                </svg>
                Tümünü Sil
            </button>
            <button onclick="copyAllLogs()" class="px-4 py-2.5 bg-slate-900 text-white rounded-lg font-black uppercase text-xs tracking-wider shadow-lg hover:shadow-xl hover:bg-slate-800 active:scale-95 transition-all flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                </svg>
                Tümünü Kopyala
            </button>
        </div>
    </div>

    <!-- Statistics Cards -->
    <?php if (isset($statistics) && !empty($statistics)): ?>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 lg:gap-6">
        <!-- Total Errors Card -->
        <div class="bg-gradient-to-br from-red-500 to-red-600 rounded-xl lg:rounded-2xl p-5 lg:p-6 text-white shadow-lg hover:shadow-xl transition-all">
            <div class="flex items-center justify-between mb-4">
                <div class="p-3 bg-white/20 rounded-lg">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                </div>
            </div>
            <div class="text-3xl lg:text-4xl font-black mb-1"><?php echo number_format($statistics['total']['all'] ?? 0); ?></div>
            <div class="text-sm font-bold text-red-100">Toplam Hata</div>
        </div>

        <!-- PHP Errors Card -->
        <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl lg:rounded-2xl p-5 lg:p-6 text-white shadow-lg hover:shadow-xl transition-all">
            <div class="flex items-center justify-between mb-4">
                <div class="p-3 bg-white/20 rounded-lg">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path>
                    </svg>
                </div>
            </div>
            <div class="text-3xl lg:text-4xl font-black mb-1"><?php echo number_format($statistics['total']['php'] ?? 0); ?></div>
            <div class="text-sm font-bold text-purple-100">PHP Hataları</div>
        </div>

        <!-- JavaScript Errors Card -->
        <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl lg:rounded-2xl p-5 lg:p-6 text-white shadow-lg hover:shadow-xl transition-all">
            <div class="flex items-center justify-between mb-4">
                <div class="p-3 bg-white/20 rounded-lg">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
                    </svg>
                </div>
            </div>
            <div class="text-3xl lg:text-4xl font-black mb-1"><?php echo number_format($statistics['total']['javascript'] ?? 0); ?></div>
            <div class="text-sm font-bold text-blue-100">JavaScript Hataları</div>
        </div>

        <!-- Unresolved Errors Card -->
        <div class="bg-gradient-to-br from-indigo-500 to-indigo-600 rounded-xl lg:rounded-2xl p-5 lg:p-6 text-white shadow-lg hover:shadow-xl transition-all">
            <div class="flex items-center justify-between mb-4">
                <div class="p-3 bg-white/20 rounded-lg">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
            </div>
            <div class="text-3xl lg:text-4xl font-black mb-1">
                <?php 
                $unresolved = ($statistics['total']['php'] ?? 0) + ($statistics['total']['javascript'] ?? 0) - 
                             (($statistics['resolved']['php'] ?? 0) + ($statistics['resolved']['javascript'] ?? 0));
                echo number_format($unresolved);
                ?>
            </div>
            <div class="text-sm font-bold text-indigo-100">Çözülmemiş Hatalar</div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="bg-white rounded-xl lg:rounded-2xl p-5 lg:p-6 border border-slate-200 shadow-sm">
        <div class="flex items-center gap-2 mb-5">
            <svg class="w-5 h-5 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path>
            </svg>
            <h2 class="text-lg sm:text-xl font-black text-slate-900">Filtreleme</h2>
        </div>
        <form method="GET" action="<?php echo buildUrl('/qodmin/error-logs'); ?>" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-4 lg:gap-5" id="error-logs-filter-form">
            <div>
                <label class="block text-xs font-black text-slate-700 uppercase tracking-wider mb-2.5">Kaynak</label>
                <select name="source" class="q-input rounded-lg font-bold text-sm border-2 border-slate-200 focus:border-indigo-500 focus:bg-white focus:ring-2 focus:ring-indigo-200 transition-all">
                    <option value="all" <?php echo ($filters['source'] ?? 'all') === 'all' ? 'selected' : ''; ?>>Tümü</option>
                    <option value="php" <?php echo ($filters['source'] ?? '') === 'php' ? 'selected' : ''; ?>>PHP</option>
                    <option value="javascript" <?php echo ($filters['source'] ?? '') === 'javascript' ? 'selected' : ''; ?>>JavaScript</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-black text-slate-700 uppercase tracking-wider mb-2.5">Hata Tipi/Seviye</label>
                <select name="level" class="q-input rounded-lg font-bold text-sm border-2 border-slate-200 focus:border-indigo-500 focus:bg-white focus:ring-2 focus:ring-indigo-200 transition-all">
                    <option value="">Tümü</option>
                    <option value="ERROR" <?php echo ($filters['level'] ?? '') === 'ERROR' ? 'selected' : ''; ?>>ERROR</option>
                    <option value="WARNING" <?php echo ($filters['level'] ?? '') === 'WARNING' ? 'selected' : ''; ?>>WARNING</option>
                    <option value="NOTICE" <?php echo ($filters['level'] ?? '') === 'NOTICE' ? 'selected' : ''; ?>>NOTICE</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-black text-slate-700 uppercase tracking-wider mb-2.5">Başlangıç Tarihi</label>
                <input type="date" name="date_from" value="<?php echo htmlspecialchars($filters['date_from'] ?? ''); ?>" class="q-input rounded-lg font-bold text-sm border-2 border-slate-200 focus:border-indigo-500 focus:bg-white focus:ring-2 focus:ring-indigo-200 transition-all">
            </div>
            <div>
                <label class="block text-xs font-black text-slate-700 uppercase tracking-wider mb-2.5">Bitiş Tarihi</label>
                <input type="date" name="date_to" value="<?php echo htmlspecialchars($filters['date_to'] ?? ''); ?>" class="q-input rounded-lg font-bold text-sm border-2 border-slate-200 focus:border-indigo-500 focus:bg-white focus:ring-2 focus:ring-indigo-200 transition-all">
            </div>
            <div>
                <label class="block text-xs font-black text-slate-700 uppercase tracking-wider mb-2.5">Durum</label>
                <select name="resolved" class="q-input rounded-lg font-bold text-sm border-2 border-slate-200 focus:border-indigo-500 focus:bg-white focus:ring-2 focus:ring-indigo-200 transition-all">
                    <option value="">Tümü</option>
                    <option value="0" <?php echo isset($filters['resolved']) && $filters['resolved'] === false ? 'selected' : ''; ?>>Çözülmemiş</option>
                    <option value="1" <?php echo isset($filters['resolved']) && $filters['resolved'] === true ? 'selected' : ''; ?>>Çözülmüş</option>
                </select>
            </div>
            <div class="flex items-end">
                <button type="submit" class="w-full py-3 bg-slate-900 text-white rounded-lg font-black uppercase text-xs tracking-wider shadow-lg hover:shadow-xl hover:bg-slate-800 active:scale-95 transition-all">Filtrele</button>
            </div>
        </form>
    </div>

    <!-- Error Logs Table -->
    <div class="bg-white rounded-xl lg:rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-5 lg:p-6 border-b border-slate-200 bg-gradient-to-r from-slate-50 to-white">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <h2 class="text-lg sm:text-xl font-black text-slate-900">Hata Logları</h2>
            </div>
            <p class="text-sm text-slate-600 mt-1.5">Toplam <?php echo number_format($pagination['total'] ?? 0); ?> kayıt</p>
        </div>
        <div class="overflow-x-auto">
            <table class="q-table">
                <thead class="bg-slate-100 border-b-2 border-slate-200">
                    <tr>
                        <th class="px-4 py-3.5 text-left text-xs font-black text-slate-700 uppercase tracking-wider">Kaynak</th>
                        <th class="px-4 py-3.5 text-left text-xs font-black text-slate-700 uppercase tracking-wider">Tarih</th>
                        <th class="px-4 py-3.5 text-left text-xs font-black text-slate-700 uppercase tracking-wider">Tip/Seviye</th>
                        <th class="px-4 py-3.5 text-left text-xs font-black text-slate-700 uppercase tracking-wider">Mesaj</th>
                        <th class="px-4 py-3.5 text-left text-xs font-black text-slate-700 uppercase tracking-wider">Dosya</th>
                        <th class="px-4 py-3.5 text-left text-xs font-black text-slate-700 uppercase tracking-wider">Satır</th>
                        <th class="px-4 py-3.5 text-left text-xs font-black text-slate-700 uppercase tracking-wider">Durum</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($error_logs)): ?>
                    <tr>
                        <td colspan="7" class="px-4 py-12 text-center">
                            <div class="flex flex-col items-center gap-3">
                                <svg class="w-12 h-12 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                <p class="text-slate-500 font-bold">Henüz hata logu yok</p>
                            </div>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($error_logs as $log): ?>
                    <?php 
                        $source = $log['source'] ?? 'unknown';
                        $errorType = $log['error_type'] ?? ($log['type'] ?? ($log['level'] ?? 'UNKNOWN'));
                        $isResolved = !empty($log['resolved_at']);
                    ?>
                    <tr class="hover:bg-slate-50 transition-colors duration-150 <?php echo $isResolved ? 'opacity-60' : ''; ?>">
                        <td class="px-4 py-3.5">
                            <span class="inline-block px-2.5 py-1 text-xs font-black rounded-lg <?php 
                                echo $source === 'php' ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800';
                            ?>">
                                <?php echo strtoupper($source); ?>
                            </span>
                        </td>
                        <td class="px-4 py-3.5">
                            <div class="text-sm font-bold text-slate-900"><?php echo date('d.m.Y', strtotime($log['created_at'])); ?></div>
                            <div class="text-xs text-slate-500"><?php echo date('H:i:s', strtotime($log['created_at'])); ?></div>
                        </td>
                        <td class="px-4 py-3.5">
                            <span class="inline-block px-2.5 py-1 text-xs font-black rounded-lg <?php 
                                if ($source === 'php') {
                                    echo $errorType === 'ERROR' ? 'bg-red-100 text-red-800' : 
                                         ($errorType === 'WARNING' ? 'bg-yellow-100 text-yellow-800' : 
                                         ($errorType === 'NOTICE' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800'));
                                } else {
                                    echo $errorType === 'javascript_error' ? 'bg-red-100 text-red-800' : 
                                         ($errorType === 'console_error' ? 'bg-indigo-100 text-indigo-800' : 
                                         ($errorType === 'console_warning' ? 'bg-yellow-100 text-yellow-800' : 
                                         ($errorType === 'promise_rejection' ? 'bg-yellow-100 text-yellow-800' : 'bg-blue-100 text-blue-800')));
                                }
                            ?>">
                                <?php echo htmlspecialchars($errorType); ?>
                            </span>
                        </td>
                        <td class="px-4 py-3.5">
                            <div class="text-sm font-bold text-slate-900 max-w-md truncate" title="<?php echo htmlspecialchars($log['message']); ?>">
                                <?php echo htmlspecialchars($log['message']); ?>
                            </div>
                        </td>
                        <td class="px-4 py-3.5 text-sm font-bold text-slate-600 max-w-xs truncate" title="<?php echo htmlspecialchars($log['file'] ?? $log['filename'] ?? 'N/A'); ?>">
                            <?php echo htmlspecialchars($log['file'] ?? $log['filename'] ?? 'N/A'); ?>
                        </td>
                        <td class="px-4 py-3.5 text-sm font-bold text-slate-600">
                            <?php 
                                $line = $log['line'] ?? $log['lineno'] ?? null;
                                echo $line && $line > 0 ? $line : '-';
                            ?>
                        </td>
                        <td class="px-4 py-3.5">
                            <?php if ($isResolved): ?>
                            <span class="inline-block px-2.5 py-1 text-xs font-black rounded-lg bg-green-100 text-green-800">
                                Çözüldü
                            </span>
                            <?php else: ?>
                            <span class="inline-block px-2.5 py-1 text-xs font-black rounded-lg bg-red-100 text-red-800">
                                Açık
                            </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($pagination['total_pages'] > 1): ?>
        <div class="px-5 py-4 bg-slate-50 border-t-2 border-slate-200 flex flex-col sm:flex-row items-center justify-between gap-4">
            <div class="text-sm font-bold text-slate-600">
                <span class="text-slate-900"><?php echo number_format($pagination['total']); ?></span> kayıt bulundu
                <span class="text-slate-500">(Sayfa <?php echo $pagination['page']; ?> / <?php echo $pagination['total_pages']; ?>)</span>
            </div>
            <div class="flex gap-2">
                <?php 
                    // Build clean URLs for pagination with current filters
                    $prevPageParams = array_merge($filters, ['page' => $pagination['page'] - 1]);
                    $nextPageParams = array_merge($filters, ['page' => $pagination['page'] + 1]);
                ?>
                <?php if ($pagination['page'] > 1): ?>
                <a href="<?php echo buildUrl('/qodmin/error-logs', $prevPageParams); ?>" class="px-4 py-2 bg-white border-2 border-slate-300 rounded-lg font-black text-sm text-slate-700 hover:bg-slate-50 hover:border-slate-400 transition-all shadow-sm">
                    <span class="flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                        </svg>
                        Önceki
                    </span>
                </a>
                <?php endif; ?>
                <?php if ($pagination['page'] < $pagination['total_pages']): ?>
                <a href="<?php echo buildUrl('/qodmin/error-logs', $nextPageParams); ?>" class="px-4 py-2 bg-slate-900 text-white rounded-lg font-black text-sm hover:bg-slate-800 transition-all shadow-sm hover:shadow">
                    <span class="flex items-center gap-1">
                        Sonraki
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </span>
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

  </div>
</div>
<script>
function copyAllLogs() {
    <?php 
    $logsJson = json_encode($error_logs ?? [], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    if (json_last_error() !== JSON_ERROR_NONE || $logsJson === false) {
        $logsJson = '[]';
    }
    ?>
    const logs = <?php echo $logsJson; ?>;
    
    if (!logs || logs.length === 0) {
        window.NotificationManager.warning('Kopyalanacak hata logu yok');
        return;
    }
    
    const logsJson = JSON.stringify(logs, null, 2);
    
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(logsJson).then(() => {
            window.NotificationManager.success('Tüm hata logları kopyalandı (' + logs.length + ' kayıt)');
        }).catch(err => {
            if (window.logger) {
                window.logger.error('Kopyalama hatası:', err);
            } else if (window.console && window.console.error) {
                window.console.error('Kopyalama hatası:', err);
            }
            window.NotificationManager.error('Kopyalama başarısız: ' + err.message);
        });
    } else {
        // Fallback for older browsers
        const textArea = document.createElement('textarea');
        textArea.value = logsJson;
        textArea.style.position = 'fixed';
        textArea.style.left = '-999999px';
        document.body.appendChild(textArea);
        textArea.select();
        try {
            document.execCommand('copy');
            window.NotificationManager.success('Tüm hata logları kopyalandı (' + logs.length + ' kayıt)');
        } catch (err) {
            if (window.logger) {
                window.logger.error('Kopyalama hatası:', err);
            } else {
                console.error('Kopyalama hatası:', err);
            }
            window.NotificationManager.error('Kopyalama başarısız');
        }
        document.body.removeChild(textArea);
    }
}

async function deleteAllErrors() {
    let confirmed = false;
    if (window.NotificationManager && window.NotificationManager.confirm) {
        confirmed = await window.NotificationManager.confirm('TÜM hataları silmek istediğinizden emin misiniz?\n\nBu işlem geri alınamaz ve tüm hata logları (PHP ve JavaScript) kalıcı olarak silinecektir.', 'Onay');
    } else {
        confirmed = confirm('TÜM hataları silmek istediğinizden emin misiniz?\n\nBu işlem geri alınamaz ve tüm hata logları (PHP ve JavaScript) kalıcı olarak silinecektir.\n\nDevam etmek istiyor musunuz?');
    }
    if (!confirmed) return;
    
    // Double confirmation
    let confirmed2 = false;
    if (window.NotificationManager && window.NotificationManager.confirm) {
        confirmed2 = await window.NotificationManager.confirm('SON UYARI: Tüm hata logları silinecek. Bu işlem geri alınamaz!\n\nEmin misiniz?', 'Son Uyarı');
    } else {
        confirmed2 = confirm('SON UYARI: Tüm hata logları silinecek. Bu işlem geri alınamaz!\n\nEmin misiniz?');
    }
    if (!confirmed2) return;
    
    try {
        const response = await fetch('<?php echo BASE_URL; ?>/api/errors/delete-all', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': window.CSRF_TOKEN || ''
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            window.NotificationManager.success(data.deleted_count + ' hata başarıyla silindi');
            setTimeout(() => {
                location.reload();
            }, 1000);
        } else {
            window.NotificationManager.error('Silme başarısız: ' + (data.message || 'Bilinmeyen hata'));
        }
    } catch (error) {
        console.error('Error deleting all errors:', error);
        window.NotificationManager.error('Bir hata oluştu: ' + error.message);
    }
}
</script>

<script>
// Handle form submission with clean URLs
document.addEventListener('DOMContentLoaded', function() {
    const filterForm = document.getElementById('error-logs-filter-form');
    if (filterForm) {
        filterForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(filterForm);
            const params = {};
            
            // Collect form values
            for (const [key, value] of formData.entries()) {
                if (value && value !== 'all' && value !== '') {
                    params[key] = value;
                }
            }
            
            // Build clean URL and navigate
            const cleanUrl = '<?php echo BASE_URL; ?>/qodmin/error-logs' + 
                (Object.keys(params).length > 0 ? '?' + new URLSearchParams(params).toString() : '');
            
            window.location.href = cleanUrl;
        });
    }
});
</script>
