<?php
/**
 * Reports View - Modern minimal responsive design with real-time data
 */
if (!function_exists('getAdminUrl')) {
    require_once __DIR__ . '/../../helpers/url_helper.php';
}
$sales_report = $sales_report ?? ['total_orders' => 0, 'total_revenue' => 0, 'avg_order_value' => 0, 'completed_orders' => 0];
$employee_performance = $employee_performance ?? [];
$customer_report = $customer_report ?? ['unique_customers' => 0, 'total_visits' => 0, 'avg_spent' => 0];
$expense_report = $expense_report ?? ['total_expenses' => 0, 'expense_count' => 0];
$profit_loss_report = $profit_loss_report ?? ['total_revenue' => 0, 'total_expenses' => 0, 'net_profit' => 0, 'profit_margin' => 0];
$tables_report = $tables_report ?? [];
$category_revenue = $category_revenue ?? [];
$hourly_sales = $hourly_sales ?? [];
$top_selling_items = $top_selling_items ?? [];
$date_range = $date_range ?? ['start' => date('Y-m-01'), 'end' => date('Y-m-d')];
$period = $period ?? 'this_month';
$selected_table_id = $selected_table_id ?? null;
$tables = $tables ?? [];
$baseUrl = BASE_URL;
$isSuperAdmin = $is_super_admin ?? false;
$adminPrefix = $isSuperAdmin ? '/qodmin' : '/business';
$apiPrefix = $isSuperAdmin ? '/api/qodmin' : '/api/business';
$hasBusinessContext = !empty(\App\Core\TenantContext::getId());
$needsBusinessSelection = $isSuperAdmin && !$hasBusinessContext;
?>

<?php if ($needsBusinessSelection): ?>
<div class="q-page q-biz-theme animate-slide-up">
  <div class="q-container q-stack q-stack--lg">
    <div id="business-selection-view">
        <header class="q-page-header">
            <div>
                <p class="q-page-header__eyebrow">Raporlar</p>
                <h1 class="q-page-header__title">İşletme Seçin</h1>
                <p class="q-page-header__subtitle">Raporlarını görüntülemek istediğiniz işletmeyi seçin</p>
            </div>
            <div class="q-page-header__actions">
                <div class="q-field" style="margin:0;min-width:14rem;">
                    <input type="text" id="business-search" placeholder="İşletme ara…" onkeyup="BusinessSelector.searchBusinesses(this.value)" class="q-input"/>
                </div>
            </div>
        </header>
        <div id="business-grid" class="q-grid q-grid--4">
            <div class="q-empty" style="grid-column:1/-1;padding:var(--space-10);">
                <span class="q-spinner" aria-hidden="true"></span>
                <p>İşletmeler yükleniyor…</p>
            </div>
        </div>
    </div>
  </div>
</div>
<script>
(function() {
    const bsScript = document.createElement('script');
    bsScript.src = '<?php echo BASE_URL; ?>/assets/js/business-selector.js?v=<?php echo time(); ?>';
    bsScript.onload = function() {
        if (typeof BusinessSelector === 'undefined') return;
        BusinessSelector.init({ baseUrl: <?php echo json_encode(BASE_URL); ?> });
        BusinessSelector.loadBusinesses().then(function() {
            BusinessSelector.renderBusinessGrid('business-grid', function(businessId, businessName) {
                window.location.href = '<?php echo $baseUrl . $adminPrefix; ?>/reports?business_id=' + businessId;
            });
        });
    };
    document.head.appendChild(bsScript);
})();
</script>
<?php else: ?>
<div class="q-page q-biz-theme animate-slide-up">
  <div class="q-container q-stack q-stack--lg space-y-5" id="reports-page-root">
    <!-- Header -->
    <header class="q-page-header">
        <div>
            <h1 class="q-page-header__title">Raporlar</h1>
            <p class="q-page-header__subtitle">Detaylı analiz raporları</p>
        </div>
        <div class="q-page-header__actions" style="position:relative;">
            <button type="button" onclick="toggleExportMenu()" class="q-btn q-btn--soft q-btn--sm">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                Dışa Aktar
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
            </button>
            <?php
                // Keep super-admin business context flowing through every export
                // link so the downstream controller scopes the CSV to the right tenant.
                $_qpBiz = '';
                if ($isSuperAdmin) {
                    $activeBusinessId = \App\Core\TenantContext::getId()
                        ?: ($_GET['business_id'] ?? ($_SESSION['business_id'] ?? ''));
                    if ($activeBusinessId) {
                        $_qpBiz = '&business_id=' . rawurlencode($activeBusinessId);
                    }
                }
                $_qpBase = 'start_date=' . rawurlencode($date_range['start']) .
                           '&end_date=' . rawurlencode($date_range['end']) . $_qpBiz;
            ?>
            <div id="export-menu" class="hidden reports-drop-in q-export-menu q-card absolute right-0 mt-2 w-48 z-50 overflow-hidden" style="padding:0;">
                <a href="<?php echo $baseUrl . $adminPrefix; ?>/export-report?type=sales&<?php echo $_qpBase; ?>">Satış Raporu</a>
                <a href="<?php echo $baseUrl . $adminPrefix; ?>/export-report?type=employees&<?php echo $_qpBase; ?>">Personel Performansı</a>
                <a href="<?php echo $baseUrl . $adminPrefix; ?>/export-report?type=customers&<?php echo $_qpBase; ?>">Müşteri Raporu</a>
                <a href="<?php echo $baseUrl . $adminPrefix; ?>/export-report?type=expenses&<?php echo $_qpBase; ?>">Gider Raporu</a>
                <a href="<?php echo $baseUrl . $adminPrefix; ?>/export-report?type=profit_loss&<?php echo $_qpBase; ?>">Kar/Zarar Raporu</a>
                <a href="<?php echo $baseUrl . $adminPrefix; ?>/export-report?type=tables&<?php echo $_qpBase; ?>">Masa Raporu</a>
            </div>
        </div>
    </header>

    <!-- Filters -->
    <div class="q-card q-card--pad q-stack">
        <div class="q-filter-bar" style="flex-direction:column;align-items:stretch;gap:var(--space-4);">
            <div>
                <span class="q-filter-group__label">Zaman Aralığı</span>
                <div class="q-filter-bar" style="margin-top:var(--space-2);">
                    <?php $periods = ['today' => 'Bugün', 'yesterday' => 'Dün', 'last_7_days' => 'Son 7 Gün', 'last_30_days' => 'Son 30 Gün', 'this_month' => 'Bu Ay', 'last_month' => 'Geçen Ay', 'this_year' => 'Bu Yıl']; ?>
                    <?php foreach ($periods as $key => $label): ?>
                    <button type="button" onclick="setPeriod('<?php echo $key; ?>')" data-period="<?php echo $key; ?>" 
                            class="period-btn period-chip filter-chip q-btn q-btn--ghost q-btn--sm <?php echo $period === $key ? 'active' : ''; ?>">
                        <?php echo $label; ?>
                    </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="q-toolbar" style="align-items:flex-end;flex-wrap:wrap;gap:var(--space-4);">
                <div style="flex:1 1 200px;min-width:0;">
                    <span class="q-filter-group__label">Özel Tarih</span>
                    <form id="dateRangeForm" class="q-toolbar" style="margin-top:var(--space-2);align-items:flex-end;flex-wrap:wrap;">
                        <input type="date" id="startDate" value="<?php echo htmlspecialchars($date_range['start']); ?>" class="q-input q-btn--sm" style="width:auto;">
                        <input type="date" id="endDate" value="<?php echo htmlspecialchars($date_range['end']); ?>" class="q-input q-btn--sm" style="width:auto;">
                        <button type="submit" class="q-btn q-btn--primary q-btn--sm">Uygula</button>
                    </form>
                </div>
                <div style="flex:0 1 12rem;min-width:10rem;">
                    <span class="q-filter-group__label">Masa</span>
                    <select id="tableFilter" onchange="setTableFilter(this.value)" class="q-input" style="margin-top:var(--space-2);width:100%;">
                    <option value="">Tüm Masalar</option>
                    <?php 
                    $tablesWithOrders = [];
                    foreach ($tables_report as $tableReport) {
                        if (($tableReport['total_orders'] ?? 0) > 0) {
                            $tablesWithOrders[$tableReport['table_id']] = $tableReport;
                        }
                    }
                    foreach ($tables as $table): 
                        $tableId = $table['table_id'] ?? '';
                        if (isset($tablesWithOrders[$tableId]) || $selected_table_id === $tableId):
                    ?>
                        <option value="<?php echo htmlspecialchars($tableId); ?>" <?php echo ($selected_table_id === $tableId) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars(($table['name'] ?? '') . ' - ' . ($table['zone'] ?? '')); ?>
                        </option>
                    <?php 
                        endif;
                    endforeach; 
                    ?>
                </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Indicator -->
    <div id="loadingIndicator" class="hidden q-loading-toast" style="position:fixed;inset:0;z-index:50;align-items:center;justify-content:center;background:rgb(255 255 255 / 0.6);backdrop-filter:blur(2px);">
        <div class="q-card q-card--pad q-stack" style="align-items:center;text-align:center;">
            <div class="q-spinner" aria-hidden="true"></div>
            <p class="q-hint">Veriler yükleniyor...</p>
        </div>
    </div>

    <!-- Sales Overview Cards -->
    <div class="q-grid q-grid--4" id="salesReport">
        <div class="q-stat">
            <div class="q-stat__top"><span class="q-stat__label">Toplam Sipariş</span></div>
            <div class="q-stat__value" id="totalOrders"><?php echo $sales_report['total_orders']; ?></div>
        </div>
        <div class="q-stat">
            <div class="q-stat__top"><span class="q-stat__label">Toplam Gelir</span></div>
            <div class="q-stat__value" id="totalRevenue"><?php echo formatCurrency($sales_report['total_revenue']); ?></div>
        </div>
        <div class="q-stat">
            <div class="q-stat__top"><span class="q-stat__label">Ortalama</span></div>
            <div class="q-stat__value" id="avgOrderValue"><?php echo formatCurrency($sales_report['avg_order_value']); ?></div>
        </div>
        <div class="q-stat">
            <div class="q-stat__top"><span class="q-stat__label">Tamamlanan</span></div>
            <div class="q-stat__value" id="completedOrders"><?php echo $sales_report['completed_orders']; ?></div>
        </div>
    </div>

    <!-- Tables Report -->
    <?php 
    $filtered_tables_report = array_filter($tables_report, function($table) {
        return ($table['total_orders'] ?? 0) > 0;
    });
    if ($selected_table_id) {
        foreach ($tables_report as $table) {
            if (($table['table_id'] ?? '') === $selected_table_id) {
                $filtered_tables_report = [$table];
                break;
            }
        }
    }
    ?>
    <?php if (!empty($filtered_tables_report)): ?>
    <div class="q-card q-card--pad" id="tablesReport">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
            <div>
                <h2 class="q-card__title">
                    <?php echo $selected_table_id ? 'Masa Detay Raporu' : 'Masa Bazlı Raporlar'; ?>
                </h2>
                <?php if (!$selected_table_id): ?>
                <p class="q-hint text-[11px] mt-0.5"><span id="totalTablesCount"><?php echo count($filtered_tables_report); ?></span> masa</p>
                <?php endif; ?>
            </div>
            
            <?php if (!$selected_table_id): ?>
            <!-- Search & Controls -->
            <div class="flex items-center gap-2">
                <div class="relative">
                    <input type="text" id="tableSearchInput" placeholder="Masa veya bölge ara..." 
                           class="q-input q-input-icon-wrap text-xs" class="w-full sm:w-auto pl-8"
                           oninput="filterTables()" onkeyup="filterTables()">
                    <svg class="absolute left-2.5 top-1/2 transform -translate-y-1/2 w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                </div>
                <select id="tableSortSelect" onchange="sortTables()" 
                        class="q-input text-xs" style="width:auto;">
                    <option value="revenue_desc">Gelir ↓</option>
                    <option value="revenue_asc">Gelir ↑</option>
                    <option value="orders_desc">Sipariş ↓</option>
                    <option value="orders_asc">Sipariş ↑</option>
                    <option value="name_asc">Masa A-Z</option>
                </select>
            </div>
            <?php endif; ?>
        </div>

        <?php if (!$selected_table_id): ?>
        <!-- View Toggle -->
        <div class="mb-4 flex items-center gap-4 border-b border-slate-100 pb-3">
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" id="showTreeView" onchange="toggleTableView()" checked class="w-3.5 h-3.5" style="accent-color:var(--color-brand-accent)">
                <span class="q-hint text-xs">Bölgelere göre grupla</span>
            </label>
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" id="showOnlyActiveTables" onchange="filterActiveTables()" class="w-3.5 h-3.5" style="accent-color:var(--color-brand-accent)">
                <span class="q-hint text-xs">Sadece aktif masalar</span>
            </label>
        </div>

        <!-- Tree View -->
        <div id="treeViewContainer" class="space-y-3">
            <?php
            $tablesByZone = [];
            foreach ($filtered_tables_report as $table) {
                $zoneName = !empty($table['zone']) ? $table['zone'] : 'Diğer';
                if (!isset($tablesByZone[$zoneName])) $tablesByZone[$zoneName] = [];
                $tablesByZone[$zoneName][] = $table;
            }
            ksort($tablesByZone);
            
            foreach ($tablesByZone as $zoneName => $zoneTables):
                $zoneId = 'zone_' . md5($zoneName);
                $zoneRevenue = array_sum(array_column($zoneTables, 'total_revenue'));
                $zoneOrders = array_sum(array_column($zoneTables, 'total_orders'));
                $activeTablesCount = count(array_filter($zoneTables, function($t) { return ($t['total_orders'] ?? 0) > 0; }));
            ?>
            <div class="q-zone-card zone-section" data-zone="<?php echo htmlspecialchars($zoneName); ?>">
                <div class="q-zone-card__header" onclick="toggleZone('<?php echo $zoneId; ?>')">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2.5">
                            <svg class="w-4 h-4 text-slate-300 transform transition-transform zone-arrow" id="arrow_<?php echo $zoneId; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                            <div>
                                <h3 class="text-sm font-semibold text-slate-900"><?php echo htmlspecialchars($zoneName); ?></h3>
                                <p class="text-[10px] text-slate-400 mt-0.5"><?php echo count($zoneTables); ?> masa &middot; <?php echo $activeTablesCount; ?> aktif</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-4 text-right">
                            <div>
                                <div class="text-xs font-semibold text-slate-900"><?php echo $zoneOrders; ?></div>
                                <div class="text-[9px] text-slate-400">sipariş</div>
                            </div>
                            <div>
                                <div class="text-xs font-bold text-slate-900"><?php echo formatCurrency($zoneRevenue); ?></div>
                                <div class="text-[9px] text-slate-400">gelir</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div id="<?php echo $zoneId; ?>" class="zone-content">
                    <div class="px-3.5 pb-3.5">
                        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-2">
                            <?php foreach ($zoneTables as $table): ?>
                            <div class="q-table-mini table-card <?php echo ($table['total_orders'] ?? 0) == 0 ? 'inactive-table opacity-40' : ''; ?>" 
                                 data-orders="<?php echo $table['total_orders'] ?? 0; ?>"
                                 data-table-name="<?php echo htmlspecialchars(strtolower($table['table_name'] ?? '')); ?>"
                                 data-zone="<?php echo htmlspecialchars(strtolower($zoneName)); ?>"
                                 data-revenue="<?php echo $table['total_revenue'] ?? 0; ?>">
                                <div class="flex items-center gap-1.5 mb-1.5">
                                    <span class="w-1.5 h-1.5 rounded-full <?php echo ($table['total_orders'] ?? 0) > 0 ? 'bg-green-400' : 'bg-slate-200'; ?>"></span>
                                    <span class="text-[11px] font-semibold text-slate-900 truncate"><?php echo htmlspecialchars($table['table_name'] ?? 'Bilinmeyen'); ?></span>
                                </div>
                                <div class="text-xs font-bold text-slate-900"><?php echo formatCurrency($table['total_revenue'] ?? 0); ?></div>
                                <div class="text-[9px] text-slate-400 mt-0.5"><?php echo $table['total_orders'] ?? 0; ?> sipariş</div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Table View (Traditional) -->
        <div id="tableViewContainer" class="hidden overflow-x-auto">
            <table class="q-table min-w-[600px]">
                <thead>
                    <tr class="border-b border-slate-100">
                        <th class="text-left p-3 text-[10px] font-semibold text-slate-400 uppercase tracking-wider cursor-pointer hover:text-slate-600" onclick="sortTablesByColumn('name')">Masa</th>
                        <th class="text-left p-3 text-[10px] font-semibold text-slate-400 uppercase tracking-wider cursor-pointer hover:text-slate-600" onclick="sortTablesByColumn('zone')">Bölge</th>
                        <th class="text-left p-3 text-[10px] font-semibold text-slate-400 uppercase tracking-wider cursor-pointer hover:text-slate-600" onclick="sortTablesByColumn('orders')">Sipariş</th>
                        <th class="text-left p-3 text-[10px] font-semibold text-slate-400 uppercase tracking-wider cursor-pointer hover:text-slate-600" onclick="sortTablesByColumn('revenue')">Gelir</th>
                        <th class="text-left p-3 text-[10px] font-semibold text-slate-400 uppercase tracking-wider">Ortalama</th>
                        <th class="text-left p-3 text-[10px] font-semibold text-slate-400 uppercase tracking-wider cursor-pointer hover:text-slate-600" onclick="sortTablesByColumn('active_days')">Aktif Gün</th>
                        <?php if ($selected_table_id): ?>
                        <th class="text-left p-3 text-[10px] font-semibold text-slate-400 uppercase tracking-wider">İşlem</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50" id="tablesTableBody">
                    <?php foreach ($filtered_tables_report as $table): ?>
                        <tr class="table-row emp-row" 
                            data-table-name="<?php echo htmlspecialchars(strtolower($table['table_name'] ?? '')); ?>"
                            data-zone="<?php echo htmlspecialchars(strtolower($table['zone'] ?? '')); ?>"
                            data-orders="<?php echo $table['total_orders'] ?? 0; ?>"
                            data-revenue="<?php echo $table['total_revenue'] ?? 0; ?>"
                            data-active-days="<?php echo $table['active_days'] ?? 0; ?>">
                            <td class="p-3 text-xs font-semibold text-slate-900"><?php echo htmlspecialchars($table['table_name'] ?? 'Bilinmeyen'); ?></td>
                            <td class="p-3 text-xs font-medium text-slate-500"><?php echo htmlspecialchars($table['zone'] ?? '-'); ?></td>
                            <td class="p-3 text-xs font-medium text-slate-600"><?php echo $table['total_orders'] ?? 0; ?></td>
                            <td class="p-3 text-xs font-semibold text-slate-900"><?php echo formatCurrency($table['total_revenue'] ?? 0); ?></td>
                            <td class="p-3 text-xs font-medium text-slate-500"><?php echo formatCurrency($table['avg_order_value'] ?? 0); ?></td>
                            <td class="p-3 text-xs font-medium text-slate-500"><?php echo $table['active_days'] ?? 0; ?></td>
                            <?php if ($selected_table_id): ?>
                            <td class="p-3"><button type="button" onclick="toggleTableDetails('<?php echo htmlspecialchars($table['table_id'] ?? ''); ?>')" class="q-btn q-btn--primary q-btn--sm">Detay</button></td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <script>
        function toggleZone(zoneId) {
            const content = document.getElementById(zoneId);
            const arrow = document.getElementById('arrow_' + zoneId);
            if (content.classList.contains('hidden')) {
                content.classList.remove('hidden');
                if (arrow) arrow.style.transform = 'rotate(180deg)';
            } else {
                content.classList.add('hidden');
                if (arrow) arrow.style.transform = 'rotate(0deg)';
            }
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.zone-content').forEach(function(zone) { zone.classList.remove('hidden'); });
            document.querySelectorAll('.zone-arrow').forEach(function(arrow) { arrow.style.transform = 'rotate(180deg)'; });
        });
        
        function toggleTableView() {
            const showTreeView = document.getElementById('showTreeView').checked;
            document.getElementById('treeViewContainer').classList.toggle('hidden', !showTreeView);
            document.getElementById('tableViewContainer').classList.toggle('hidden', showTreeView);
        }
        
        function filterActiveTables() {
            const showOnlyActive = document.getElementById('showOnlyActiveTables').checked;
            document.querySelectorAll('.inactive-table').forEach(t => t.style.display = showOnlyActive ? 'none' : '');
            document.querySelectorAll('.zone-section').forEach(section => {
                const visible = Array.from(section.querySelectorAll('.table-card')).filter(t => t.style.display !== 'none');
                section.style.display = (showOnlyActive && visible.length === 0) ? 'none' : '';
            });
            document.querySelectorAll('#tablesTableBody .table-row').forEach(row => {
                const orders = parseInt(row.getAttribute('data-orders') || '0');
                row.style.display = (showOnlyActive && orders === 0) ? 'none' : '';
            });
        }
        </script>
        <?php else: ?>
        <!-- Single table detail view -->
        <div class="overflow-x-auto">
            <table class="q-table min-w-[600px]">
                <thead>
                    <tr class="border-b border-slate-100">
                        <th class="text-left p-3 text-[10px] font-semibold text-slate-400 uppercase tracking-wider">Masa</th>
                        <th class="text-left p-3 text-[10px] font-semibold text-slate-400 uppercase tracking-wider">Bölge</th>
                        <th class="text-left p-3 text-[10px] font-semibold text-slate-400 uppercase tracking-wider">Sipariş</th>
                        <th class="text-left p-3 text-[10px] font-semibold text-slate-400 uppercase tracking-wider">Gelir</th>
                        <th class="text-left p-3 text-[10px] font-semibold text-slate-400 uppercase tracking-wider">Ortalama</th>
                        <th class="text-left p-3 text-[10px] font-semibold text-slate-400 uppercase tracking-wider">Aktif Gün</th>
                        <th class="text-left p-3 text-[10px] font-semibold text-slate-400 uppercase tracking-wider">İşlem</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php foreach ($filtered_tables_report as $table): ?>
                        <tr class="emp-row">
                            <td class="p-3 text-xs font-semibold text-slate-900"><?php echo htmlspecialchars($table['table_name'] ?? 'Bilinmeyen'); ?></td>
                            <td class="p-3 text-xs font-medium text-slate-500"><?php echo htmlspecialchars($table['zone'] ?? '-'); ?></td>
                            <td class="p-3 text-xs font-medium text-slate-600"><?php echo $table['total_orders'] ?? 0; ?></td>
                            <td class="p-3 text-xs font-semibold text-slate-900"><?php echo formatCurrency($table['total_revenue'] ?? 0); ?></td>
                            <td class="p-3 text-xs font-medium text-slate-500"><?php echo formatCurrency($table['avg_order_value'] ?? 0); ?></td>
                            <td class="p-3 text-xs font-medium text-slate-500"><?php echo $table['active_days'] ?? 0; ?></td>
                            <td class="p-3"><button type="button" onclick="toggleTableDetails('<?php echo htmlspecialchars($table['table_id'] ?? ''); ?>')" class="q-btn q-btn--primary q-btn--sm">Detay</button></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- Summary Stats -->
        <?php if (!$selected_table_id && count($filtered_tables_report) > 1): ?>
        <div class="q-grid q-grid--4 gap-2 mt-4 pt-4 border-t" style="border-color:var(--color-border-1)">
            <div class="text-center">
                <div class="q-stat-mini__label mb-0.5">Toplam Gelir</div>
                <div class="q-stat-mini__value" id="tablesTotalRevenue">
                    <?php $totalRevenue = array_sum(array_column($tables_report, 'total_revenue')); echo formatCurrency($totalRevenue); ?>
                </div>
            </div>
            <div class="text-center">
                <div class="q-stat-mini__label mb-0.5">Toplam Sipariş</div>
                <div class="q-stat-mini__value" id="tablesTotalOrders"><?php echo array_sum(array_column($tables_report, 'total_orders')); ?></div>
            </div>
            <div class="text-center">
                <div class="q-stat-mini__label mb-0.5">Ortalama</div>
                <div class="q-stat-mini__value" id="tablesAvgRevenue">
                    <?php $avgRevenue = count($filtered_tables_report) > 0 ? $totalRevenue / count($filtered_tables_report) : 0; echo formatCurrency($avgRevenue); ?>
                </div>
            </div>
            <div class="text-center">
                <div class="q-stat-mini__label mb-0.5">Aktif Masa</div>
                <div class="q-stat-mini__value" id="tablesActiveCount"><?php echo count($filtered_tables_report); ?></div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Table Orders History -->
    <?php 
    $selectedTableOrders = [];
    if ($selected_table_id && !empty($filtered_tables_report) && isset($filtered_tables_report[0]['orders'])) {
        $selectedTableOrders = $filtered_tables_report[0]['orders'] ?? [];
    }
    ?>
    <div class="q-card q-card--pad" id="tableOrdersHistory" style="display: <?php echo ($selected_table_id && !empty($selectedTableOrders)) ? 'block' : 'none'; ?>;">
        <h2 class="q-card__title mb-4">Masa Sipariş Geçmişi</h2>
        <div class="space-y-3" id="tableOrdersHistoryContent">
            <?php if ($selected_table_id && !empty($selectedTableOrders)): ?>
                <?php foreach ($selectedTableOrders as $order): ?>
                    <div class="q-card q-card--pad emp-row">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mb-2">
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-bold text-slate-900">#<?php echo htmlspecialchars($order['order_id'] ?? ''); ?></span>
                                <span class="q-badge q-badge--neutral text-[9px]">
                                    <?php echo htmlspecialchars($order['status'] ?? 'UNKNOWN'); ?>
                                </span>
                                <?php if (!empty($order['is_paid']) && $order['is_paid']): ?>
                                <span class="q-badge q-badge--success text-[9px]">Ödendi</span>
                                <?php endif; ?>
                            </div>
                            <div class="text-right">
                                <span class="q-stat-mini__value"><?php echo formatCurrency($order['total_amount'] ?? 0); ?></span>
                                <span class="text-[10px] text-slate-400 ml-2"><?php echo date('d.m.Y H:i', strtotime($order['created_at'] ?? '')); ?></span>
                            </div>
                        </div>
                        
                        <?php if (!empty($order['items'])): ?>
                        <div class="mt-2 pt-2 border-t border-slate-100">
                            <div class="space-y-1">
                                <?php foreach (function_exists('groupOrderItemsForDisplay') ? groupOrderItemsForDisplay($order['items']) : $order['items'] as $item): ?>
                                    <div class="flex justify-between items-center text-xs">
                                        <span class="text-slate-600">
                                            <?php echo htmlspecialchars($item['menu_item_name'] ?? 'Bilinmeyen'); ?>
                                            <span class="text-slate-400">x<?php echo $item['quantity'] ?? 1; ?></span>
                                        </span>
                                        <span class="font-semibold text-slate-900"><?php echo formatCurrency(($item['price'] ?? 0) * ($item['quantity'] ?? 1)); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="text-center py-8 text-slate-400 text-xs font-medium">Bu tarih aralığında sipariş bulunamadı.</div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Employee Performance Report - Enhanced -->
    <div class="q-card q-card--pad" id="employeeReport">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h2 class="q-card__title">Personel Performansı</h2>
                <p class="q-hint text-[11px] mt-0.5">Garson bazlı sipariş ve satış performansı</p>
            </div>
        </div>
        
        <?php if (!empty($employee_performance)): ?>
        <?php 
        // Calculate max values for visual bars
        $maxSales = max(array_column($employee_performance, 'total_sales') ?: [1]);
        $maxOrders = max(array_column($employee_performance, 'orders_handled') ?: [1]);
        $totalSalesAll = array_sum(array_column($employee_performance, 'total_sales'));
        $totalOrdersAll = array_sum(array_column($employee_performance, 'orders_handled'));
        ?>
        
        <!-- Performance Cards -->
        <div class="space-y-3" id="employeeTableBody">
            <?php foreach ($employee_performance as $index => $employee): 
                $salesPercent = $maxSales > 0 ? round(($employee['total_sales'] ?? 0) / $maxSales * 100) : 0;
                $ordersPercent = $maxOrders > 0 ? round(($employee['orders_handled'] ?? 0) / $maxOrders * 100) : 0;
                $sharePercent = $totalSalesAll > 0 ? round(($employee['total_sales'] ?? 0) / $totalSalesAll * 100, 1) : 0;
                $avgOrderValue = ($employee['orders_handled'] ?? 0) > 0 ? ($employee['total_sales'] ?? 0) / ($employee['orders_handled'] ?? 1) : 0;
                $rankBadgeClass = ['q-rank-badge--1', 'q-rank-badge--2', 'q-rank-badge--3'][$index] ?? '';
            ?>
            <div class="q-emp-row emp-row">
                <div class="flex items-start gap-3">
                    <!-- Rank -->
                    <div class="q-rank-badge <?php echo $rankBadgeClass; ?>">
                        <span class="text-xs font-bold"><?php echo $index + 1; ?></span>
                    </div>
                    
                    <!-- Info -->
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between mb-2">
                            <div>
                                <h3 class="text-sm font-semibold text-slate-900"><?php echo htmlspecialchars($employee['name'] ?? 'Bilinmeyen'); ?></h3>
                                <p class="text-[10px] text-slate-400 mt-0.5">
                                    <?php echo $employee['orders_handled'] ?? 0; ?> sipariş &middot; 
                                    ort. <?php echo formatCurrency($avgOrderValue); ?> &middot;
                                    %<?php echo $sharePercent; ?> pay
                                </p>
                            </div>
                            <div class="text-right">
                                <div class="q-stat-mini__value"><?php echo formatCurrency($employee['total_sales'] ?? 0); ?></div>
                            </div>
                        </div>
                        
                        <!-- Visual Bar -->
                        <div class="q-progress-track">
                            <div class="emp-bar reports-emp-bar h-full rounded-full" style="width: <?php echo $salesPercent; ?>%"></div>
                        </div>
                        
                        <!-- Mini Stats Row -->
                        <div class="flex items-center gap-4 mt-2">
                            <div class="flex items-center gap-1">
                                <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                                <span class="text-[10px] font-medium text-slate-500"><?php echo $employee['orders_handled'] ?? 0; ?> sipariş</span>
                            </div>
                            <div class="flex items-center gap-1">
                                <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg>
                                <span class="text-[10px] font-medium text-slate-500">ort. <?php echo formatCurrency($avgOrderValue); ?></span>
                            </div>
                            <div class="flex items-center gap-1">
                                <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"></path></svg>
                                <span class="text-[10px] font-medium text-slate-500">%<?php echo $sharePercent; ?> pay</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Summary -->
        <?php if (count($employee_performance) > 1): ?>
        <div class="mt-4 pt-4 border-t border-slate-100 grid grid-cols-3 gap-3 text-center">
            <div>
                <div class="q-stat-mini__label mb-0.5">Toplam Personel</div>
                <div class="q-stat-mini__value"><?php echo count($employee_performance); ?></div>
            </div>
            <div>
                <div class="q-stat-mini__label mb-0.5">Toplam Satış</div>
                <div class="q-stat-mini__value"><?php echo formatCurrency($totalSalesAll); ?></div>
            </div>
            <div>
                <div class="q-stat-mini__label mb-0.5">Ortalama/Personel</div>
                <div class="q-stat-mini__value"><?php echo formatCurrency($totalSalesAll / max(count($employee_performance), 1)); ?></div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php else: ?>
        <div class="text-center py-8">
            <svg class="w-10 h-10 text-slate-200 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
            <p class="text-xs font-medium text-slate-400">Bu tarih aralığında personel performans verisi bulunamadı.</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- Financial Summary & Customer Report -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <!-- Financial Summary -->
        <div class="q-card q-card--pad" id="financialSummary">
            <h2 class="q-card__title mb-4">Finansal Özet</h2>
            <div class="space-y-3">
                <div class="flex items-center justify-between p-3 rounded-xl bg-emerald-50/60">
                    <div class="flex items-center gap-2">
                        <div class="w-6 h-6 rounded-md bg-emerald-100 flex items-center justify-center">
                            <svg class="w-3 h-3 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"></path></svg>
                        </div>
                        <span class="text-xs font-medium text-slate-600">Toplam Gelir</span>
                    </div>
                    <span class="text-sm font-bold text-emerald-700" id="totalRevenueFinancial"><?php echo formatCurrency($profit_loss_report['total_revenue']); ?></span>
                </div>
                <div class="flex items-center justify-between p-3 rounded-xl bg-red-50/60">
                    <div class="flex items-center gap-2">
                        <div class="w-6 h-6 rounded-md bg-red-100 flex items-center justify-center">
                            <svg class="w-3 h-3 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"></path></svg>
                        </div>
                        <span class="text-xs font-medium text-slate-600">Toplam Gider</span>
                    </div>
                    <span class="text-sm font-bold text-red-700" id="totalExpenses"><?php echo formatCurrency($profit_loss_report['total_expenses']); ?></span>
                </div>
                <div class="q-fin-row" style="background:var(--color-amber-soft);">
                    <div class="flex items-center gap-2">
                        <div class="w-6 h-6 rounded-md flex items-center justify-center" style="background:var(--color-brand-accent);color:#fff;">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                        </div>
                        <span class="text-xs font-medium text-muted">Net Kar</span>
                    </div>
                    <span class="text-sm font-bold" style="color:var(--color-brand-accent-hover);" id="netProfit"><?php echo formatCurrency($profit_loss_report['net_profit']); ?></span>
                </div>
                <div class="q-fin-row q-fin-row--margin">
                    <span class="text-xs font-medium opacity-90">Kar Marjı</span>
                    <span class="text-sm font-bold" id="profitMargin"><?php echo round($profit_loss_report['profit_margin'], 1); ?>%</span>
                </div>
            </div>
        </div>

        <!-- Customer Report -->
        <div class="q-card q-card--pad" id="customerReport">
            <h2 class="q-card__title mb-4">Müşteri Analizi</h2>
            <div class="grid grid-cols-1 gap-3">
                <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50/60 border border-slate-200/40">
                    <div class="flex items-center gap-2">
                        <div class="w-6 h-6 rounded-md bg-slate-100 flex items-center justify-center">
                            <svg class="w-3 h-3 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                        </div>
                        <span class="text-xs font-medium text-slate-600">Benzersiz Müşteri</span>
                    </div>
                    <span class="q-stat-mini__value" id="uniqueCustomers"><?php echo $customer_report['unique_customers'] ?? 0; ?></span>
                </div>
                <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50/60 border border-slate-200/40">
                    <div class="flex items-center gap-2">
                        <div class="w-6 h-6 rounded-md bg-slate-100 flex items-center justify-center">
                            <svg class="w-3 h-3 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                        </div>
                        <span class="text-xs font-medium text-slate-600">Toplam Ziyaret</span>
                    </div>
                    <span class="q-stat-mini__value" id="totalVisits"><?php echo $customer_report['total_visits'] ?? 0; ?></span>
                </div>
                <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50/60 border border-slate-200/40">
                    <div class="flex items-center gap-2">
                        <div class="w-6 h-6 rounded-md bg-slate-100 flex items-center justify-center">
                            <svg class="w-3 h-3 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        </div>
                        <span class="text-xs font-medium text-slate-600">Ortalama Harcama</span>
                    </div>
                    <span class="q-stat-mini__value" id="avgSpent"><?php echo formatCurrency($customer_report['avg_spent'] ?? 0); ?></span>
                </div>
            </div>
        </div>
    </div>
  </div>
</div>

<script src="<?php echo BASE_URL; ?>/assets/js/reports.js?v=<?php echo filemtime(__DIR__ . '/../../../public/assets/js/reports.js') ?: time(); ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof ReportsPage !== 'undefined') {
        ReportsPage.init({
            baseUrl: '<?php echo BASE_URL; ?>',
            apiPrefix: '<?php echo $apiPrefix; ?>',
            currentPeriod: '<?php echo $period; ?>',
            startDate: '<?php echo $date_range['start']; ?>',
            endDate: '<?php echo $date_range['end']; ?>',
            selectedTableId: '<?php echo $selected_table_id ?? ''; ?>'
        });
    }
});

function toggleExportMenu() {
    document.getElementById('export-menu').classList.toggle('hidden');
}

document.addEventListener('click', function(event) {
    const menu = document.getElementById('export-menu');
    const button = event.target.closest('button[onclick="toggleExportMenu()"]');
    if (!button && menu && !menu.contains(event.target)) {
        menu.classList.add('hidden');
    }
});

function toggleTableDetails(tableId) {
    const historyDiv = document.getElementById('tableOrdersHistory');
    if (historyDiv) {
        if (historyDiv.style.display === 'none') historyDiv.style.display = 'block';
        historyDiv.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}
</script>
<?php endif; ?>
