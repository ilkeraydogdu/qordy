<div class="q-page animate-slide-up">
  <div class="q-container">
    <!-- Header -->
    <div class="bg-gradient-to-r from-indigo-600 via-purple-600 to-pink-600 text-white p-6 sm:p-8 rounded-2xl shadow-xl mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 sm:gap-6">
            <div>
                <h1 class="text-2xl sm:text-3xl lg:text-4xl font-black tracking-tighter mb-2">Hata Logları - Super Admin</h1>
                <p class="text-sm sm:text-base text-white/90">Veritabanı ve sunucu hata log kayıtları - qordy.com ve tüm subdomainler</p>
            </div>
            <div class="flex flex-wrap gap-2 sm:gap-3">
                <button onclick="copyAllErrors()" class="px-4 py-2.5 bg-green-500/90 hover:bg-green-600 text-white rounded-lg font-black uppercase text-xs tracking-wider shadow-lg hover:shadow-xl active:scale-95 transition-all flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                    </svg>
                    Tüm Hataları Kopyala
                </button>
                <button onclick="deleteAllErrors()" class="px-4 py-2.5 bg-red-500/90 hover:bg-red-600 text-white rounded-lg font-black uppercase text-xs tracking-wider shadow-lg hover:shadow-xl active:scale-95 transition-all flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                    </svg>
                    Veritabanı Loglarını Sil
                </button>
                <button onclick="refreshLogs()" class="px-4 py-2.5 bg-white/20 hover:bg-white/30 text-white rounded-lg font-black uppercase text-xs tracking-wider shadow-lg hover:shadow-xl active:scale-95 transition-all flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                    </svg>
                    Yenile
                </button>
            </div>
        </div>
    </div>

    <!-- Log Source Tabs -->
    <div class="bg-white rounded-xl lg:rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="border-b border-slate-200 bg-gradient-to-r from-slate-50 to-white">
            <div class="flex overflow-x-auto">
                <button onclick="switchLogSource('database')" class="px-6 py-4 font-black text-sm uppercase tracking-wider transition-all border-b-2 <?php echo ($log_source ?? 'database') === 'database' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-slate-500 hover:text-slate-900'; ?>">
                    <span class="flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"></path>
                        </svg>
                        Veritabanı Logları
                    </span>
                </button>
                <button onclick="switchLogSource('server')" class="px-6 py-4 font-black text-sm uppercase tracking-wider transition-all border-b-2 <?php echo ($log_source ?? 'database') === 'server' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-slate-500 hover:text-slate-900'; ?>">
                    <span class="flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"></path>
                        </svg>
                        Sunucu Logları
                    </span>
                </button>
            </div>
        </div>
    </div>

    <!-- Database Logs View -->
    <div id="database-logs-view" class="<?php echo ($log_source ?? 'database') === 'database' ? '' : 'hidden'; ?>">
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
            <div class="bg-gradient-to-br from-orange-500 to-orange-600 rounded-xl lg:rounded-2xl p-5 lg:p-6 text-white shadow-lg hover:shadow-xl transition-all">
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
                <div class="text-sm font-bold text-orange-100">Çözülmemiş Hatalar</div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Filters -->
        <div class="bg-white rounded-xl lg:rounded-2xl p-5 lg:p-6 border border-slate-200 shadow-sm">
            <div class="flex items-center justify-between mb-5">
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path>
                    </svg>
                    <h2 class="text-lg sm:text-xl font-black text-slate-900">Filtreleme</h2>
                </div>
                <button onclick="runSmartCleanup()" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-black text-xs shadow-sm transition-all flex items-center gap-1.5" title="Gürültü kayıtları ve eski logları temizle">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    Akıllı Temizlik
                </button>
            </div>
            <form method="GET" action="<?php echo buildUrl('/qodmin/error-logs'); ?>" class="space-y-4" id="superadmin-error-logs-filter-form">
                <input type="hidden" name="log_source" value="database">
                <!-- Row 1: Search -->
                <div class="relative">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input type="text" name="search" placeholder="Hata mesajı veya dosya ara..." value="<?php echo htmlspecialchars(($queryParams ?? [])['search'] ?? ''); ?>" class="w-full pl-10 pr-4 p-3 bg-slate-50 rounded-lg font-bold text-sm border-2 border-slate-200 focus:border-indigo-500 focus:bg-white focus:ring-2 focus:ring-indigo-200 transition-all">
                </div>
                <!-- Row 2: Filters grid -->
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-7 gap-3">
                    <select name="source" class="p-2.5 bg-slate-50 rounded-lg font-bold text-xs border-2 border-slate-200 focus:border-indigo-500 transition-all">
                        <option value="all" <?php echo ($filters['source'] ?? 'all') === 'all' ? 'selected' : ''; ?>>T&uuml;m Kaynaklar</option>
                        <option value="php" <?php echo ($filters['source'] ?? '') === 'php' ? 'selected' : ''; ?>>PHP</option>
                        <option value="javascript" <?php echo ($filters['source'] ?? '') === 'javascript' ? 'selected' : ''; ?>>JavaScript</option>
                    </select>
                    <select name="level" class="p-2.5 bg-slate-50 rounded-lg font-bold text-xs border-2 border-slate-200 focus:border-indigo-500 transition-all">
                        <option value="">T&uuml;m Seviyeler</option>
                        <option value="ERROR" <?php echo ($filters['level'] ?? '') === 'ERROR' ? 'selected' : ''; ?>>ERROR</option>
                        <option value="WARNING" <?php echo ($filters['level'] ?? '') === 'WARNING' ? 'selected' : ''; ?>>WARNING</option>
                    </select>
                    <input type="date" name="date_from" value="<?php echo htmlspecialchars($filters['date_from'] ?? ''); ?>" class="p-2.5 bg-slate-50 rounded-lg font-bold text-xs border-2 border-slate-200 focus:border-indigo-500 transition-all" title="Ba&#x15F;lang&#305;&#231;">
                    <input type="date" name="date_to" value="<?php echo htmlspecialchars($filters['date_to'] ?? ''); ?>" class="p-2.5 bg-slate-50 rounded-lg font-bold text-xs border-2 border-slate-200 focus:border-indigo-500 transition-all" title="Biti&#x15F;">
                    <select name="resolved" class="p-2.5 bg-slate-50 rounded-lg font-bold text-xs border-2 border-slate-200 focus:border-indigo-500 transition-all">
                        <option value="">T&uuml;m Durumlar</option>
                        <option value="0" <?php echo isset($filters['resolved']) && $filters['resolved'] === false ? 'selected' : ''; ?>>&#199;&ouml;z&uuml;lmemi&#x15F;</option>
                        <option value="1" <?php echo isset($filters['resolved']) && $filters['resolved'] === true ? 'selected' : ''; ?>>&#199;&ouml;z&uuml;lm&uuml;&#x15F;</option>
                    </select>
                    <select name="min_occurrences" class="p-2.5 bg-slate-50 rounded-lg font-bold text-xs border-2 border-slate-200 focus:border-indigo-500 transition-all">
                        <option value="">T&uuml;m Tekrarlar</option>
                        <option value="2" <?php echo (($queryParams ?? [])['min_occurrences'] ?? '') === '2' ? 'selected' : ''; ?>>2+ tekrar</option>
                        <option value="5" <?php echo (($queryParams ?? [])['min_occurrences'] ?? '') === '5' ? 'selected' : ''; ?>>5+ tekrar</option>
                        <option value="10" <?php echo (($queryParams ?? [])['min_occurrences'] ?? '') === '10' ? 'selected' : ''; ?>>10+ tekrar</option>
                        <option value="50" <?php echo (($queryParams ?? [])['min_occurrences'] ?? '') === '50' ? 'selected' : ''; ?>>50+ tekrar</option>
                    </select>
                    <button type="submit" class="p-2.5 bg-indigo-600 text-white rounded-lg font-black uppercase text-xs tracking-wider shadow-lg hover:bg-indigo-700 active:scale-95 transition-all">Filtrele</button>
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
                    <h2 class="text-lg sm:text-xl font-black text-slate-900">Veritabanı Hata Logları</h2>
                </div>
                <p class="text-sm text-slate-600 mt-1.5">Toplam <?php echo number_format($pagination['total'] ?? 0); ?> kayıt</p>
            </div>
            <div class="overflow-x-auto">
                <table class="q-table">
                    <thead class="bg-slate-100 border-b-2 border-slate-200">
                        <tr>
                            <th class="px-3 py-3 text-left text-xs font-black text-slate-700 uppercase tracking-wider">Kaynak</th>
                            <th class="px-3 py-3 text-left text-xs font-black text-slate-700 uppercase tracking-wider">Tarih</th>
                            <th class="px-3 py-3 text-left text-xs font-black text-slate-700 uppercase tracking-wider">Seviye</th>
                            <th class="px-3 py-3 text-left text-xs font-black text-slate-700 uppercase tracking-wider">Mesaj</th>
                            <th class="px-3 py-3 text-left text-xs font-black text-slate-700 uppercase tracking-wider">Dosya</th>
                            <th class="px-3 py-3 text-center text-xs font-black text-slate-700 uppercase tracking-wider" title="Ka&#231; kez tekrarland&#305;">Tekrar</th>
                            <th class="px-3 py-3 text-left text-xs font-black text-slate-700 uppercase tracking-wider">Durum</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if (empty($error_logs)): ?>
                        <tr>
                            <td colspan="7" class="px-4 py-12 text-center">
                                <div class="flex flex-col items-center gap-3">
                                    <svg class="w-12 h-12 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    <p class="text-slate-500 font-bold">Hata logu yok - harika!</p>
                                </div>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($error_logs as $log): ?>
                        <?php 
                            $source = $log['source'] ?? 'unknown';
                            $errorType = $log['error_type'] ?? ($log['type'] ?? ($log['level'] ?? 'UNKNOWN'));
                            $isResolved = !empty($log['resolved_at']);
                            $occurrenceCount = (int)($log['occurrence_count'] ?? 1);
                            $lastOccurred = $log['last_occurred_at'] ?? $log['created_at'] ?? null;
                            $filePath = $log['file'] ?? $log['filename'] ?? '';
                            $shortFile = $filePath ? basename($filePath) : 'N/A';
                            $lineNum = $log['line'] ?? $log['lineno'] ?? null;
                        ?>
                        <tr class="hover:bg-slate-50 transition-colors duration-150 <?php echo $isResolved ? 'opacity-50' : ''; ?>">
                            <td class="px-3 py-3">
                                <span class="inline-block px-2 py-0.5 text-[10px] font-black rounded-md <?php 
                                    echo $source === 'php' ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800';
                                ?>">
                                    <?php echo strtoupper($source); ?>
                                </span>
                            </td>
                            <td class="px-3 py-3">
                                <div class="text-xs font-bold text-slate-900"><?php echo date('d.m.Y', strtotime($log['created_at'])); ?></div>
                                <div class="text-[10px] text-slate-400"><?php echo date('H:i:s', strtotime($log['created_at'])); ?></div>
                                <?php if ($lastOccurred && $lastOccurred !== $log['created_at'] && $occurrenceCount > 1): ?>
                                <div class="text-[10px] text-orange-500 font-bold mt-0.5" title="Son tekrar">&#x21bb; <?php echo date('d.m H:i', strtotime($lastOccurred)); ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="px-3 py-3">
                                <span class="inline-block px-2 py-0.5 text-[10px] font-black rounded-md <?php 
                                    if ($source === 'php') {
                                        echo $errorType === 'ERROR' ? 'bg-red-100 text-red-800' : 
                                             ($errorType === 'WARNING' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800');
                                    } else {
                                        echo $errorType === 'javascript_error' ? 'bg-red-100 text-red-800' : 
                                             ($errorType === 'console_error' ? 'bg-orange-100 text-orange-800' : 
                                             ($errorType === 'console_warning' ? 'bg-yellow-100 text-yellow-800' : 'bg-blue-100 text-blue-800'));
                                    }
                                ?>">
                                    <?php echo htmlspecialchars($errorType); ?>
                                </span>
                            </td>
                            <td class="px-3 py-3">
                                <div class="text-xs font-bold text-slate-900 max-w-sm truncate" title="<?php echo htmlspecialchars($log['message']); ?>">
                                    <?php echo htmlspecialchars(mb_substr($log['message'], 0, 150)); ?>
                                </div>
                            </td>
                            <td class="px-3 py-3">
                                <div class="text-xs font-bold text-slate-600 truncate max-w-[140px]" title="<?php echo htmlspecialchars($filePath); ?>">
                                    <?php echo htmlspecialchars($shortFile); ?>
                                </div>
                                <?php if ($lineNum && $lineNum > 0): ?>
                                <div class="text-[10px] text-slate-400">sat&#305;r <?php echo $lineNum; ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="px-3 py-3 text-center">
                                <?php if ($occurrenceCount > 1): ?>
                                <span class="inline-flex items-center justify-center min-w-[28px] px-1.5 py-0.5 text-[10px] font-black rounded-full <?php
                                    echo $occurrenceCount >= 100 ? 'bg-red-500 text-white' : 
                                         ($occurrenceCount >= 10 ? 'bg-orange-100 text-orange-800' : 'bg-slate-100 text-slate-700');
                                ?>">
                                    <?php echo $occurrenceCount >= 1000 ? round($occurrenceCount/1000, 1) . 'K' : $occurrenceCount; ?>x
                                </span>
                                <?php else: ?>
                                <span class="text-[10px] text-slate-300">1</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-3 py-3">
                                <?php if ($isResolved): ?>
                                <span class="inline-block px-2 py-0.5 text-[10px] font-black rounded-md bg-green-100 text-green-800">
                                    &#10003;
                                </span>
                                <?php else: ?>
                                <span class="inline-block px-2 py-0.5 text-[10px] font-black rounded-md bg-red-100 text-red-800">
                                    A&#231;&#305;k
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
                        $prevPageParams = array_merge($filters, ['page' => $pagination['page'] - 1, 'log_source' => 'database']);
                        $nextPageParams = array_merge($filters, ['page' => $pagination['page'] + 1, 'log_source' => 'database']);
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
                    <a href="<?php echo buildUrl('/qodmin/error-logs', $nextPageParams); ?>" class="px-4 py-2 bg-indigo-600 text-white rounded-lg font-black text-sm hover:bg-indigo-700 transition-all shadow-sm hover:shadow">
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

    <!-- Server Logs View -->
    <div id="server-logs-view" class="<?php echo ($log_source ?? 'database') === 'server' ? '' : 'hidden'; ?>">
        <!-- Search Filter -->
        <div class="bg-white rounded-xl lg:rounded-2xl p-5 lg:p-6 border border-slate-200 shadow-sm mb-6">
            <div class="flex items-center gap-2 mb-5">
                <svg class="w-5 h-5 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path>
                </svg>
                <h2 class="text-lg sm:text-xl font-black text-slate-900">qordy.com Sunucu Logları</h2>
            </div>
            <form method="GET" action="<?php echo buildUrl('/qodmin/error-logs'); ?>" class="flex flex-col sm:flex-row gap-4" id="superadmin-server-logs-filter-form">
                <input type="hidden" name="log_source" value="server">
                <div class="flex-1">
                    <input type="text" name="search" placeholder="Log içinde ara..." value="<?php echo htmlspecialchars(($queryParams ?? [])['search'] ?? ''); ?>" class="q-input rounded-lg font-bold text-sm border-2 border-slate-200 focus:border-indigo-500 focus:bg-white focus:ring-2 focus:ring-indigo-200 transition-all">
                </div>
                <div>
                    <select name="level" class="q-input rounded-lg font-bold text-sm border-2 border-slate-200 focus:border-indigo-500 focus:bg-white focus:ring-2 focus:ring-indigo-200 transition-all">
                        <option value="">Tüm Seviyeler</option>
                        <option value="ERROR" <?php echo (($queryParams ?? [])['level'] ?? '') === 'ERROR' ? 'selected' : ''; ?>>ERROR</option>
                        <option value="WARNING" <?php echo (($queryParams ?? [])['level'] ?? '') === 'WARNING' ? 'selected' : ''; ?>>WARNING</option>
                        <option value="NOTICE" <?php echo (($queryParams ?? [])['level'] ?? '') === 'NOTICE' ? 'selected' : ''; ?>>NOTICE</option>
                    </select>
                </div>
                <button type="submit" class="px-6 py-3 bg-indigo-600 text-white rounded-lg font-black uppercase text-xs tracking-wider shadow-lg hover:shadow-xl hover:bg-indigo-700 active:scale-95 transition-all">Filtrele</button>
            </form>
        </div>

        <!-- Server Logs List -->
        <?php if (!empty($server_logs)): ?>
        <div class="space-y-4">
            <?php foreach ($server_logs as $log): ?>
            <div class="bg-white rounded-xl lg:rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="p-5 lg:p-6 border-b border-slate-200 bg-gradient-to-r from-slate-50 to-white">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <div>
                            <h3 class="text-lg font-black text-slate-900 flex items-center gap-2">
                                <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                <?php echo htmlspecialchars($log['name']); ?>
                            </h3>
                            <div class="flex flex-wrap items-center gap-3 mt-2 text-sm text-slate-600">
                                <span class="flex items-center gap-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"></path>
                                    </svg>
                                    <?php echo $log['size_formatted']; ?>
                                </span>
                                <span class="flex items-center gap-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    <?php echo date('d.m.Y H:i:s', $log['modified']); ?>
                                </span>
                                <span class="px-2 py-1 bg-indigo-100 text-indigo-800 rounded-lg text-xs font-black">
                                    <?php echo htmlspecialchars($log['domain']); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="p-4 bg-slate-900 text-green-400 font-mono text-xs overflow-x-auto max-h-96 overflow-y-auto">
                    <?php if (!empty($log['lines'])): ?>
                        <?php foreach ($log['lines'] as $lineData): ?>
                        <div class="mb-1 hover:bg-slate-800 px-2 py-1 rounded">
                            <span class="text-slate-500"><?php echo $lineData['line_number']; ?>:</span>
                            <?php if ($lineData['timestamp']): ?>
                            <span class="text-yellow-400">[<?php echo htmlspecialchars($lineData['timestamp']); ?>]</span>
                            <?php endif; ?>
                            <span class="ml-2"><?php echo htmlspecialchars($lineData['line']); ?></span>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-slate-500 text-center py-8">Bu log dosyasında kayıt bulunamadı</div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="bg-white rounded-xl lg:rounded-2xl border border-slate-200 shadow-sm p-12 text-center">
            <svg class="w-16 h-16 text-slate-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
            <p class="text-slate-500 font-bold text-lg">Sunucu log dosyası bulunamadı</p>
        </div>
        <?php endif; ?>
    </div>

  </div>
</div>
<script>
function switchLogSource(source) {
    const dbView = document.getElementById('database-logs-view');
    const serverView = document.getElementById('server-logs-view');
    
    if (source === 'database') {
        dbView.classList.remove('hidden');
        serverView.classList.add('hidden');
    } else {
        dbView.classList.add('hidden');
        serverView.classList.remove('hidden');
    }
    
    // Update URL with clean format
    const currentParams = new URLSearchParams(window.location.search);
    currentParams.set('log_source', source);
    const cleanUrl = '<?php echo BASE_URL; ?>/qodmin/error-logs' + 
        (currentParams.toString() ? '?' + currentParams.toString() : '');
    window.history.pushState({}, '', cleanUrl);
}

function refreshLogs() {
    window.location.reload();
}

function copyAllErrors() {
    // Get current filter parameters
    const urlParams = new URLSearchParams(window.location.search);
    const params = {};
    for (const [key, value] of urlParams.entries()) {
        if (key !== 'page' && key !== 'log_source') {
            params[key] = value;
        }
    }
    
    // Build export URL with current filters
    const exportUrl = '<?php echo BASE_URL; ?>/qodmin/error-logs/export-all' + 
        (Object.keys(params).length > 0 ? '?' + new URLSearchParams(params).toString() : '');
    
    // Show loading message
    const button = event.target.closest('button') || event.target;
    const originalText = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '<svg class="animate-spin h-4 w-4 inline-block" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Yükleniyor...';
    
    fetch(exportUrl)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data) {
                // Copy to clipboard
                navigator.clipboard.writeText(data.data).then(() => {
                    button.disabled = false;
                    button.innerHTML = originalText;
                    window.NotificationManager.success(
                        'Tüm hatalar kopyalandı! ' + 
                        '(Veritabanı: ' + data.database_count + ' kayıt, ' +
                        'Sunucu: ' + data.server_files_count + ' dosya)'
                    );
                }).catch(err => {
                    console.error('Clipboard copy failed:', err);
                    button.disabled = false;
                    button.innerHTML = originalText;
                    const textarea = document.createElement('textarea');
                    textarea.value = data.data;
                    textarea.style.position = 'fixed';
                    textarea.style.opacity = '0';
                    document.body.appendChild(textarea);
                    textarea.select();
                    try {
                        document.execCommand('copy');
                        document.body.removeChild(textarea);
                        window.NotificationManager.success(
                            'Tüm hatalar kopyalandı! ' + 
                            '(Veritabanı: ' + data.database_count + ' kayıt, ' +
                            'Sunucu: ' + data.server_files_count + ' dosya)'
                        );
                    } catch (fallbackErr) {
                        document.body.removeChild(textarea);
                        window.NotificationManager.error('Kopyalama başarısız: ' + fallbackErr.message);
                    }
                });
            } else {
                button.disabled = false;
                button.innerHTML = originalText;
                window.NotificationManager.error('Hatalar alınamadı: ' + (data.message || 'Bilinmeyen hata'));
            }
        })
        .catch(error => {
            console.error('Error fetching all errors:', error);
            button.disabled = false;
            button.innerHTML = originalText;
            window.NotificationManager.error('Bir hata oluştu: ' + error.message);
        });
}

async function runSmartCleanup() {
    let confirmed = false;
    if (window.NotificationManager && window.NotificationManager.confirm) {
        confirmed = await window.NotificationManager.confirm('Akıllı temizlik çalıştırılacak:\n\n• Gürültü kayıtları (bot taramaları, beklenen uyarılar) silinecek\n• 7 günden eski çözülmüş hatalar silinecek\n• 30 günden eski tüm hatalar silinecek\n\nDevam?', 'Akıllı Temizlik');
    } else {
        confirmed = confirm('Akıllı temizlik çalıştırılacak. Devam?');
    }
    if (!confirmed) return;

    const btn = event.target.closest('button');
    const origHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<svg class="animate-spin h-3.5 w-3.5 inline-block" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg> Temizleniyor...';

    fetch('<?php echo BASE_URL; ?>/api/errors/smart-cleanup', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.CSRF_TOKEN || '' }
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = origHtml;
        if (data.success) {
            const d = data.data || {};
            window.NotificationManager.success(
                'Temizlik tamamlandı! Silinen: ' + (d.total || 0) + 
                ' (Gürültü: ' + (d.noise || 0) + ', Eski çözülmüş: ' + (d.old_resolved || 0) + ', Eski: ' + (d.old_unresolved || 0) + ')'
            );
            setTimeout(() => location.reload(), 1500);
        } else {
            window.NotificationManager.error('Hata: ' + (data.message || 'Bilinmeyen'));
        }
    })
    .catch(e => {
        btn.disabled = false;
        btn.innerHTML = origHtml;
        window.NotificationManager.error('Hata: ' + e.message);
    });
}

async function deleteAllErrors() {
    let confirmed = false;
    if (window.NotificationManager && window.NotificationManager.confirm) {
        confirmed = await window.NotificationManager.confirm('TÜM veritabanı hatalarını silmek istediğinizden emin misiniz?\n\nBu işlem geri alınamaz ve tüm hata logları (PHP ve JavaScript) kalıcı olarak silinecektir.\n\nDevam etmek istiyor musunuz?', 'Tüm Hataları Sil');
    } else {
        confirmed = confirm('TÜM veritabanı hatalarını silmek istediğinizden emin misiniz?\n\nBu işlem geri alınamaz ve tüm hata logları (PHP ve JavaScript) kalıcı olarak silinecektir.\n\nDevam etmek istiyor musunuz?');
    }
    if (!confirmed) return;
    
    let confirmed2 = false;
    if (window.NotificationManager && window.NotificationManager.confirm) {
        confirmed2 = await window.NotificationManager.confirm('SON UYARI: Tüm hata logları silinecek. Bu işlem geri alınamaz!\n\nEmin misiniz?', 'Son Uyarı');
    } else {
        confirmed2 = confirm('SON UYARI: Tüm hata logları silinecek. Bu işlem geri alınamaz!\n\nEmin misiniz?');
    }
    if (!confirmed2) return;
    
    fetch('<?php echo BASE_URL; ?>/api/errors/delete-all', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': window.CSRF_TOKEN || ''
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.NotificationManager.success(data.deleted_count + ' hata başarıyla silindi');
            setTimeout(() => {
                location.reload();
            }, 1000);
        } else {
            window.NotificationManager.error('Silme başarısız: ' + (data.message || 'Bilinmeyen hata'));
        }
    })
    .catch(error => {
        console.error('Error deleting all errors:', error);
        window.NotificationManager.error('Bir hata oluştu: ' + error.message);
    });
}

// Handle form submissions with clean URLs
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const logSource = urlParams.get('log_source') || 'database';
    switchLogSource(logSource);
    
    // Database logs form
    const dbFilterForm = document.getElementById('superadmin-error-logs-filter-form');
    if (dbFilterForm) {
        dbFilterForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(dbFilterForm);
            const params = {};
            for (const [key, value] of formData.entries()) {
                if (value && value !== 'all' && value !== '') {
                    params[key] = value;
                }
            }
            const cleanUrl = '<?php echo BASE_URL; ?>/qodmin/error-logs' + 
                (Object.keys(params).length > 0 ? '?' + new URLSearchParams(params).toString() : '');
            window.location.href = cleanUrl;
        });
    }
    
    // Server logs form
    const serverFilterForm = document.getElementById('superadmin-server-logs-filter-form');
    if (serverFilterForm) {
        serverFilterForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(serverFilterForm);
            const params = {};
            for (const [key, value] of formData.entries()) {
                if (value && value !== '') {
                    params[key] = value;
                }
            }
            const cleanUrl = '<?php echo BASE_URL; ?>/qodmin/error-logs' + 
                (Object.keys(params).length > 0 ? '?' + new URLSearchParams(params).toString() : '');
            window.location.href = cleanUrl;
        });
    }
});
</script>
