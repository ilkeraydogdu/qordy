<?php
/**
 * Finance Dashboard - Profesyonel Finans Yönetim Sistemi
 * Kapsamlı finansal analiz ve raporlama dashboard'u
 */

// Load JSON helper for safeJsonEncodeForJs function
if (!function_exists('safeJsonEncodeForJs')) {
    require_once __DIR__ . '/../../../helpers/json_helper.php';
}

// HelperLoader already loads translations.php, no need to require it again

$currentShift = $current_shift ?? null;
$kpis = $kpis ?? [];
$charts = $charts ?? [];
$recentExpenses = $recent_expenses ?? [];
$unpaidInvoices = $unpaid_invoices ?? [];
$topExpenses = $top_expenses ?? [];
$cashFlow = $cash_flow ?? [];
$dateRange = $date_range ?? 'today';
$startDate = $start_date ?? date('Y-m-d');
$endDate = $end_date ?? date('Y-m-d');
if (!function_exists('getAdminUrl')) {
    require_once __DIR__ . '/../../../helpers/url_helper.php';
}
$isSuperAdmin = $is_super_admin ?? false;
$adminPrefix = $isSuperAdmin ? '/qodmin' : '/business';
$apiPrefix = $isSuperAdmin ? '/api/qodmin' : '/api/business';

// Extract KPI values
$dailyRevenue = $kpis['daily_revenue'] ?? 0;
$dailyExpenses = $kpis['daily_expenses'] ?? 0;
$netProfit = $kpis['net_profit'] ?? 0;
$profitMargin = $kpis['profit_margin'] ?? 0;
$avgOrderValue = $kpis['avg_order_value'] ?? 0;
$orderCount = $kpis['order_count'] ?? 0;

// Extract chart data
$revenueExpenseTrend = $charts['revenue_expense_trend'] ?? ['revenue' => [], 'expenses' => []];
$categoryBreakdown = $charts['category_breakdown'] ?? [];
$monthlyComparison = $charts['monthly_comparison'] ?? [];

$baseUrl = BASE_URL;
?>

<div class="q-page q-biz-theme animate-slide-up">
  <div class="q-container q-stack q-stack--lg">
    <?php if ($is_super_admin ?? false): ?>
    <div id="business-selection-view" class="q-stack q-stack--lg">
        <header class="q-page-header">
            <div>
                <p class="q-page-header__eyebrow">Finans</p>
                <h1 class="q-page-header__title">Finans Yönetimi</h1>
                <p class="q-page-header__subtitle">İşletme seçin</p>
            </div>
        </header>
        <div id="business-grid" class="q-grid q-grid--4"></div>
    </div>
    <div id="finance-management-view" class="hidden q-stack q-stack--lg">
        <div class="q-toolbar">
            <button type="button" onclick="backToBusinessSelection()" class="q-btn q-btn--ghost q-btn--sm">
                <?php echo icon_arrow_left(['class' => 'w-5 h-5']); ?>
            </button>
            <h1 class="q-page-header__title" style="margin:0;"><span id="selected-business-name"></span> — Finans</h1>
        </div>
    <?php endif; ?>
    
    <!-- Header with Filters -->
    <header class="q-page-header">
        <div>
            <?php if (!($is_super_admin ?? false)): ?>
            <p class="q-page-header__eyebrow">Finans</p>
            <h1 class="q-page-header__title"><?php echo t('finance.title') ?? 'Finans Yönetimi'; ?></h1>
            <?php endif; ?>
            <p class="q-page-header__subtitle"><?php echo t('finance.subtitle', 'Finansal Analiz ve Raporlama'); ?></p>
        </div>
        
        <div class="q-page-header__actions q-toolbar" style="flex-wrap:wrap;">
            <select id="date-range-filter" class="q-input" style="width:auto;min-width:10rem;">
                <option value="today" <?php echo $dateRange === 'today' ? 'selected' : ''; ?>><?php echo t('common.today', 'Bugün'); ?></option>
                <option value="week" <?php echo $dateRange === 'week' ? 'selected' : ''; ?>><?php echo t('common.last7Days', 'Son 7 Gün'); ?></option>
                <option value="month" <?php echo $dateRange === 'month' ? 'selected' : ''; ?>><?php echo t('common.thisMonth', 'Bu Ay'); ?></option>
                <option value="custom"><?php echo t('common.customDate', 'Özel Tarih'); ?></option>
            </select>
            
            <div id="custom-date-range" class="hidden q-toolbar">
                <input type="date" id="start-date" value="<?php echo htmlspecialchars($startDate); ?>" class="q-input"/>
                <span class="q-hint">—</span>
                <input type="date" id="end-date" value="<?php echo htmlspecialchars($endDate); ?>" class="q-input"/>
                <button type="button" onclick="applyCustomDateRange()" class="q-btn q-btn--primary q-btn--sm">
                    <?php echo t('buttons.apply', 'Uygula'); ?>
                </button>
            </div>
            
            <button type="button" onclick="refreshFinancialData()" class="q-btn q-btn--ink q-btn--sm">
                <?php echo icon_signal(['class' => 'w-4 h-4']); ?>
                <span class="hidden sm:inline"><?php echo t('buttons.refresh', 'Yenile'); ?></span>
            </button>
        </div>
    </header>

    <!-- Shift widget removed -->

    <!-- KPI Cards Row 1 -->
    <div class="q-grid q-grid--4">
        <!-- Günlük Ciro -->
        <div class="q-card q-card--pad">
            <div class="flex items-center gap-3 sm:gap-4 mb-3">
                <div class="w-10 h-10 sm:w-12 sm:h-12 md:w-14 md:h-14 bg-indigo-100 rounded-lg sm:rounded-xl flex items-center justify-center text-indigo-600 shrink-0">
                    <?php echo icon_wallet(['class' => 'w-5 h-5 sm:w-6 sm:h-6 md:w-7 md:h-7']); ?>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="text-[8px] sm:text-[9px] md:text-[10px] font-black text-slate-400 uppercase tracking-widest"><?php echo t('finance.dailyRevenue', 'Günlük Ciro'); ?></div>
                    <div class="text-lg sm:text-xl md:text-2xl lg:text-3xl font-black text-slate-900 truncate" id="kpi-revenue">
                        <?php echo formatCurrency($dailyRevenue); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Günlük Giderler -->
        <div class="q-card q-card--pad">
            <div class="flex items-center gap-3 sm:gap-4 mb-3">
                <div class="w-10 h-10 sm:w-12 sm:h-12 md:w-14 md:h-14 bg-red-100 rounded-lg sm:rounded-xl flex items-center justify-center text-red-600 shrink-0">
                    <?php echo icon_trending_down(['class' => 'w-5 h-5 sm:w-6 sm:h-6 md:w-7 md:h-7']); ?>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="text-[8px] sm:text-[9px] md:text-[10px] font-black text-slate-400 uppercase tracking-widest"><?php echo t('finance.dailyExpenses', 'Günlük Giderler'); ?></div>
                    <div class="text-lg sm:text-xl md:text-2xl lg:text-3xl font-black text-slate-900 truncate" id="kpi-expenses">
                        <?php echo formatCurrency($dailyExpenses); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Net Kâr -->
        <div class="q-card q-card--pad">
            <div class="flex items-center gap-3 sm:gap-4 mb-3">
                <div class="w-10 h-10 sm:w-12 sm:h-12 md:w-14 md:h-14 bg-emerald-100 rounded-lg sm:rounded-xl flex items-center justify-center text-emerald-600 shrink-0">
                    <?php echo icon_bar_chart(['class' => 'w-5 h-5 sm:w-6 sm:h-6 md:w-7 md:h-7']); ?>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="text-[8px] sm:text-[9px] md:text-[10px] font-black text-slate-400 uppercase tracking-widest"><?php echo t('finance.netProfit', 'Net Kâr'); ?></div>
                    <div class="text-lg sm:text-xl md:text-2xl lg:text-3xl font-black <?php echo $netProfit >= 0 ? 'text-emerald-600' : 'text-red-600'; ?> truncate" id="kpi-profit">
                        <?php echo formatCurrency($netProfit); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kâr Marjı -->
        <div class="q-card q-card--pad">
            <div class="flex items-center gap-3 sm:gap-4 mb-3">
                <div class="w-10 h-10 sm:w-12 sm:h-12 md:w-14 md:h-14 bg-blue-100 rounded-lg sm:rounded-xl flex items-center justify-center text-blue-600 shrink-0">
                    <?php echo icon_signal(['class' => 'w-5 h-5 sm:w-6 sm:h-6 md:w-7 md:h-7']); ?>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="text-[8px] sm:text-[9px] md:text-[10px] font-black text-slate-400 uppercase tracking-widest"><?php echo t('finance.profitMargin', 'Kâr Marjı'); ?></div>
                    <div class="text-lg sm:text-xl md:text-2xl lg:text-3xl font-black <?php echo $profitMargin >= 0 ? 'text-emerald-600' : 'text-red-600'; ?> truncate" id="kpi-margin">
                        %<?php echo number_format($profitMargin, 2); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- KPI Cards Row 2 -->
    <div class="q-grid q-grid--3">
        <!-- Ortalama Sipariş Değeri -->
        <div class="q-card q-card--pad">
            <div class="flex items-center gap-3 sm:gap-4 mb-3">
                <div class="w-10 h-10 sm:w-12 sm:h-12 md:w-14 md:h-14 rounded-lg sm:rounded-xl flex items-center justify-center shrink-0" style="background:var(--color-amber-soft, #fef3c7);color:var(--color-brand-accent);">
                    <?php echo icon_wallet(['class' => 'w-5 h-5 sm:w-6 sm:h-6 md:w-7 md:h-7']); ?>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="text-[8px] sm:text-[9px] md:text-[10px] font-black text-slate-400 uppercase tracking-widest"><?php echo t('finance.avgOrder', 'Ort. Sipariş'); ?></div>
                    <div class="text-lg sm:text-xl md:text-2xl lg:text-3xl font-black text-slate-900 truncate" id="kpi-avg-order">
                        <?php echo formatCurrency($avgOrderValue); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Toplam Sipariş -->
        <div class="q-card q-card--pad">
            <div class="flex items-center gap-3 sm:gap-4 mb-3">
                <div class="w-10 h-10 sm:w-12 sm:h-12 md:w-14 md:h-14 rounded-lg sm:rounded-xl flex items-center justify-center shrink-0" style="background:var(--color-surface-2, #f8fafc);color:var(--color-text-secondary);">
                    <?php echo icon_bar_chart(['class' => 'w-5 h-5 sm:w-6 sm:h-6 md:w-7 md:h-7']); ?>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="text-[8px] sm:text-[9px] md:text-[10px] font-black text-slate-400 uppercase tracking-widest"><?php echo t('dashboard.totalOrders', 'Toplam Sipariş'); ?></div>
                    <div class="text-lg sm:text-xl md:text-2xl lg:text-3xl font-black text-slate-900 truncate" id="kpi-orders">
                        <?php echo $orderCount; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Nakit Akışı -->
        <div class="q-card q-card--pad">
            <div class="flex items-center gap-3 sm:gap-4 mb-3">
                <div class="w-10 h-10 sm:w-12 sm:h-12 md:w-14 md:h-14 bg-cyan-100 rounded-lg sm:rounded-xl flex items-center justify-center text-cyan-600 shrink-0">
                    <?php echo icon_signal(['class' => 'w-5 h-5 sm:w-6 sm:h-6 md:w-7 md:h-7']); ?>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="text-[8px] sm:text-[9px] md:text-[10px] font-black text-slate-400 uppercase tracking-widest"><?php echo t('finance.cashFlow', 'Nakit Akışı'); ?></div>
                    <div class="text-lg sm:text-xl md:text-2xl lg:text-3xl font-black <?php echo ($cashFlow['net_flow'] ?? 0) >= 0 ? 'text-emerald-600' : 'text-red-600'; ?> truncate" id="kpi-cashflow">
                        <?php echo formatCurrency($cashFlow['net_flow'] ?? 0); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="q-grid q-grid--2">
        <!-- Revenue vs Expenses Trend Chart -->
        <div class="q-card q-card--pad">
            <h3 class="text-base sm:text-lg md:text-xl font-black mb-4 sm:mb-6"><?php echo t('finance.revenueExpenseTrend', 'Gelir - Gider Trendi'); ?></h3>
            <div class="h-64 sm:h-80">
                <canvas id="revenueExpenseChart"></canvas>
            </div>
        </div>

        <!-- Category Breakdown Chart -->
        <div class="q-card q-card--pad">
            <h3 class="text-base sm:text-lg md:text-xl font-black mb-4 sm:mb-6"><?php echo t('finance.categoryBreakdown', 'Kategori Bazlı Gider Dağılımı'); ?></h3>
            <div class="h-64 sm:h-80">
                <canvas id="categoryChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Monthly Comparison Chart -->
    <div class="q-card q-card--pad">
        <h3 class="text-base sm:text-lg md:text-xl font-black mb-4 sm:mb-6"><?php echo t('finance.monthlyComparison', 'Aylık Karşılaştırma'); ?></h3>
        <div class="h-64 sm:h-80">
            <canvas id="monthlyChart"></canvas>
        </div>
    </div>

    <!-- Analytics Hub: Stok / Fire / Tedarikçi -->
    <section id="analytics-hub" class="space-y-4 sm:space-y-6">
        <div class="flex items-center justify-between">
            <h2 class="text-base sm:text-lg md:text-xl font-black text-slate-900">
                Operasyonel Analiz
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest ml-2">Stok · Fire · Tedarikçi</span>
            </h2>
            <span id="analytics-range-label" class="text-xs font-bold text-slate-500"></span>
        </div>

        <!-- Stock / Waste / Supplier summary cards -->
        <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-2 sm:gap-3">
            <div class="bg-white rounded-xl sm:rounded-2xl border border-emerald-100 p-3 sm:p-4 shadow-sm">
                <div class="text-[10px] font-black text-emerald-600 uppercase tracking-widest">Stok Değeri</div>
                <div class="text-lg sm:text-xl md:text-2xl font-black text-slate-900" id="ah-stock-value">₺0,00</div>
                <div class="text-[11px] font-bold text-slate-500"><span id="ah-stock-ing">0</span> hammadde · <span id="ah-stock-menu">0</span> menü</div>
            </div>
            <div class="bg-white rounded-xl sm:rounded-2xl border border-amber-100 p-3 sm:p-4 shadow-sm">
                <div class="text-[10px] font-black text-amber-600 uppercase tracking-widest">Düşük Stok</div>
                <div class="text-lg sm:text-xl md:text-2xl font-black text-amber-700" id="ah-low-stock">0</div>
                <div class="text-[11px] font-bold text-slate-500">Minimum altında</div>
            </div>
            <div class="bg-white rounded-xl sm:rounded-2xl border border-rose-100 p-3 sm:p-4 shadow-sm">
                <div class="text-[10px] font-black text-rose-600 uppercase tracking-widest">Tükenen Ürün</div>
                <div class="text-lg sm:text-xl md:text-2xl font-black text-rose-700" id="ah-out-stock">0</div>
                <div class="text-[11px] font-bold text-slate-500">Stok = 0</div>
            </div>
            <div class="bg-white rounded-xl sm:rounded-2xl border border-red-100 p-3 sm:p-4 shadow-sm">
                <div class="text-[10px] font-black text-red-600 uppercase tracking-widest">Fire Tutarı</div>
                <div class="text-lg sm:text-xl md:text-2xl font-black text-red-700" id="ah-waste-cost">₺0,00</div>
                <div class="text-[11px] font-bold text-slate-500"><span id="ah-waste-count">0</span> kayıt</div>
            </div>
            <div class="bg-white rounded-xl sm:rounded-2xl border border-blue-100 p-3 sm:p-4 shadow-sm">
                <div class="text-[10px] font-black text-blue-600 uppercase tracking-widest">Alış (Tedarikçi)</div>
                <div class="text-lg sm:text-xl md:text-2xl font-black text-blue-700" id="ah-purchase-total">₺0,00</div>
                <div class="text-[11px] font-bold text-slate-500"><span id="ah-purchase-count">0</span> fiş</div>
            </div>
            <div class="bg-white rounded-xl sm:rounded-2xl border border-violet-100 p-3 sm:p-4 shadow-sm">
                <div class="text-[10px] font-black text-violet-600 uppercase tracking-widest">Ödenmemiş Fatura</div>
                <div class="text-lg sm:text-xl md:text-2xl font-black text-violet-700" id="ah-unpaid-total">₺0,00</div>
                <div class="text-[11px] font-bold text-slate-500"><span id="ah-unpaid-count">0</span> fatura</div>
            </div>
        </div>

        <!-- Detail grids -->
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-3 sm:gap-4 md:gap-6">
            <!-- Top wasted ingredients -->
            <div class="bg-white p-4 sm:p-5 md:p-6 rounded-xl sm:rounded-2xl border border-slate-50 shadow-soft">
                <h3 class="text-sm sm:text-base font-black mb-3">En Çok Fire Verilen Ürünler</h3>
                <div class="overflow-x-auto -mx-2 px-2">
                    <table class="w-full text-xs sm:text-sm">
                        <thead class="text-[10px] uppercase text-slate-500 tracking-wider">
                            <tr class="border-b border-slate-100">
                                <th class="text-left py-1.5 font-bold">Ürün</th>
                                <th class="text-right py-1.5 font-bold">Adet</th>
                                <th class="text-right py-1.5 font-bold">Tutar</th>
                            </tr>
                        </thead>
                        <tbody id="ah-top-wasted" class="divide-y divide-slate-100"></tbody>
                    </table>
                </div>
            </div>

            <!-- Waste by reason -->
            <div class="bg-white p-4 sm:p-5 md:p-6 rounded-xl sm:rounded-2xl border border-slate-50 shadow-soft">
                <h3 class="text-sm sm:text-base font-black mb-3">Fire Nedenleri</h3>
                <div class="space-y-2" id="ah-waste-reasons"></div>
            </div>

            <!-- Top suppliers -->
            <div class="bg-white p-4 sm:p-5 md:p-6 rounded-xl sm:rounded-2xl border border-slate-50 shadow-soft">
                <h3 class="text-sm sm:text-base font-black mb-3">En Çok Alış Yapılan Tedarikçiler</h3>
                <div class="overflow-x-auto -mx-2 px-2">
                    <table class="w-full text-xs sm:text-sm">
                        <thead class="text-[10px] uppercase text-slate-500 tracking-wider">
                            <tr class="border-b border-slate-100">
                                <th class="text-left py-1.5 font-bold">Tedarikçi</th>
                                <th class="text-right py-1.5 font-bold">Fiş</th>
                                <th class="text-right py-1.5 font-bold">Tutar</th>
                            </tr>
                        </thead>
                        <tbody id="ah-top-suppliers" class="divide-y divide-slate-100"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>

    <!-- Tables Section -->
    <div class="q-grid q-grid--3">
        <!-- Recent Expenses -->
        <div class="q-card q-card--pad">
            <h3 class="text-base sm:text-lg md:text-xl font-black mb-4 sm:mb-6"><?php echo t('finance.recentExpenses', 'Son Giderler'); ?></h3>
            <div class="space-y-2 sm:space-y-3 max-h-96 overflow-y-auto">
                <?php if (empty($recentExpenses)): ?>
                    <p class="text-center p-4 text-slate-400 font-bold text-sm"><?php echo t('finance.expenses.noRecords', 'Henüz gider kaydı yok'); ?></p>
                <?php else: ?>
                    <?php foreach (array_slice($recentExpenses, 0, 5) as $expense): ?>
                        <div class="p-3 sm:p-4 bg-slate-50 rounded-lg sm:rounded-xl border border-slate-100">
                            <div class="flex justify-between items-start mb-2">
                                <div class="flex-1 min-w-0">
                                    <div class="font-black text-slate-900 text-sm truncate"><?php echo htmlspecialchars($expense['description'] ?? $expense['category'] ?? 'Gider'); ?></div>
                                    <div class="text-xs font-bold text-slate-500"><?php echo htmlspecialchars($expense['category'] ?? ''); ?></div>
                                </div>
                                <div class="text-right ml-2 shrink-0">
                                    <div class="text-sm sm:text-base font-black text-red-600"><?php echo formatCurrency($expense['amount'] ?? 0); ?></div>
                                </div>
                            </div>
                            <div class="text-[10px] sm:text-xs font-bold text-slate-400">
                                <?php echo htmlspecialchars($expense['date'] ?? date('Y-m-d')); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Unpaid Invoices -->
        <div class="q-card q-card--pad">
            <h3 class="text-base sm:text-lg md:text-xl font-black mb-4 sm:mb-6"><?php echo t('finance.unpaidInvoices', 'Ödenmemiş Faturalar'); ?></h3>
            <div class="space-y-2 sm:space-y-3 max-h-96 overflow-y-auto">
                <?php if (empty($unpaidInvoices)): ?>
                    <p class="text-center p-4 text-slate-400 font-bold text-sm"><?php echo t('finance.invoices.noUnpaid', 'Ödenmemiş fatura yok'); ?></p>
                <?php else: ?>
                    <?php foreach (array_slice($unpaidInvoices, 0, 5) as $invoice): ?>
                        <div class="p-3 sm:p-4 bg-slate-50 rounded-lg sm:rounded-xl border border-slate-100">
                            <div class="flex justify-between items-start mb-2">
                                <div class="flex-1 min-w-0">
                                    <div class="font-black text-slate-900 text-sm truncate"><?php echo htmlspecialchars($invoice['supplier_name'] ?? t('finance.suppliers.supplier', 'Tedarikçi')); ?></div>
                                    <div class="text-xs font-bold text-slate-500"><?php echo htmlspecialchars($invoice['invoice_number'] ?? ''); ?></div>
                                </div>
                                <div class="text-right ml-2 shrink-0">
                                    <div class="text-sm sm:text-base font-black text-indigo-600"><?php echo formatCurrency($invoice['amount'] ?? 0); ?></div>
                                </div>
                            </div>
                            <div class="text-[10px] sm:text-xs font-bold text-slate-400">
                                <?php echo t('finance.invoices.dueDate', 'Vade'); ?>: <?php echo htmlspecialchars($invoice['due_date'] ?? date('Y-m-d')); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Top Expenses -->
        <div class="q-card q-card--pad">
            <h3 class="text-base sm:text-lg md:text-xl font-black mb-4 sm:mb-6"><?php echo t('finance.topExpenses', 'En Yüksek Giderler'); ?></h3>
            <div class="space-y-2 sm:space-y-3 max-h-96 overflow-y-auto">
                <?php if (empty($topExpenses)): ?>
                    <p class="text-center p-4 text-slate-400 font-bold text-sm"><?php echo t('finance.expenses.noRecords', 'Gider kaydı yok'); ?></p>
                <?php else: ?>
                    <?php foreach ($topExpenses as $index => $expense): ?>
                        <div class="p-3 sm:p-4 bg-slate-50 rounded-lg sm:rounded-xl border border-slate-100">
                            <div class="flex items-center gap-3 mb-2">
                                <div class="w-6 h-6 sm:w-8 sm:h-8 bg-red-100 rounded-lg flex items-center justify-center text-red-600 font-black text-xs sm:text-sm shrink-0">
                                    <?php echo $index + 1; ?>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="font-black text-slate-900 text-sm truncate"><?php echo htmlspecialchars($expense['description'] ?? $expense['category'] ?? 'Gider'); ?></div>
                                    <div class="text-xs font-bold text-slate-500"><?php echo htmlspecialchars($expense['category'] ?? ''); ?></div>
                                </div>
                                <div class="text-right ml-2 shrink-0">
                                    <div class="text-sm sm:text-base font-black text-red-600"><?php echo formatCurrency($expense['amount'] ?? 0); ?></div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
  </div>
</div>

<!-- Chart.js Library -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<!-- Finance Dashboard JavaScript -->
<script>
// Chart instances
let revenueExpenseChart = null;
let categoryChart = null;
let monthlyChart = null;

// Initialize charts on page load
document.addEventListener('DOMContentLoaded', function() {
    initializeCharts();
});

function initializeCharts() {
    // Revenue vs Expenses Trend Chart
    const revenueExpenseCtx = document.getElementById('revenueExpenseChart');
    if (revenueExpenseCtx) {
        const revenueData = <?php echo safeJsonEncodeForJs($revenueExpenseTrend['revenue'] ?? [], 'array'); ?>;
        const expenseData = <?php echo safeJsonEncodeForJs($revenueExpenseTrend['expenses'] ?? [], 'array'); ?>;
        
        const labels = revenueData.map(item => item.day_name || item.date);
        const revenueValues = revenueData.map(item => parseFloat(item.revenue || 0));
        const expenseValues = expenseData.map(item => parseFloat(item.expenses || 0));
        
        revenueExpenseChart = new Chart(revenueExpenseCtx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: <?php echo safeJsonEncodeForJs(t('finance.revenue', 'Gelir'), 'string'); ?>,
                    data: revenueValues,
                    borderColor: 'rgb(249, 115, 22)',
                    backgroundColor: 'rgba(249, 115, 22, 0.1)',
                    tension: 0.4,
                    fill: true
                }, {
                    label: <?php echo safeJsonEncodeForJs(t('finance.expenses.expense', 'Gider'), 'string'); ?>,
                    data: expenseValues,
                    borderColor: 'rgb(239, 68, 68)',
                    backgroundColor: 'rgba(239, 68, 68, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }
    
    // Category Breakdown Chart
    const categoryCtx = document.getElementById('categoryChart');
    if (categoryCtx) {
        const categoryData = <?php echo safeJsonEncodeForJs($categoryBreakdown ?? [], 'array'); ?>;
        
        const categoryLabels = categoryData.map(item => item.category);
        const categoryValues = categoryData.map(item => parseFloat(item.amount || 0));
        
        categoryChart = new Chart(categoryCtx, {
            type: 'doughnut',
            data: {
                labels: categoryLabels,
                datasets: [{
                    data: categoryValues,
                    backgroundColor: [
                        'rgba(249, 115, 22, 0.8)',
                        'rgba(239, 68, 68, 0.8)',
                        'rgba(34, 197, 94, 0.8)',
                        'rgba(59, 130, 246, 0.8)',
                        'rgba(168, 85, 247, 0.8)',
                        'rgba(236, 72, 153, 0.8)',
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                    }
                }
            }
        });
    }
    
    // Monthly Comparison Chart
    const monthlyCtx = document.getElementById('monthlyChart');
    if (monthlyCtx) {
        const monthlyData = <?php echo safeJsonEncodeForJs($monthlyComparison ?? [], 'array'); ?>;
        
        const monthlyLabels = monthlyData.map(item => item.month_name || item.month);
        const monthlyRevenue = monthlyData.map(item => parseFloat(item.revenue || 0));
        const monthlyExpenses = monthlyData.map(item => parseFloat(item.expenses || 0));
        const monthlyProfit = monthlyData.map(item => parseFloat(item.net_profit || 0));
        
        monthlyChart = new Chart(monthlyCtx, {
            type: 'bar',
            data: {
                labels: monthlyLabels,
                datasets: [{
                    label: <?php echo safeJsonEncodeForJs(t('finance.revenue', 'Gelir'), 'string'); ?>,
                    data: monthlyRevenue,
                    backgroundColor: 'rgba(249, 115, 22, 0.8)',
                }, {
                    label: <?php echo safeJsonEncodeForJs(t('finance.expenses.expense', 'Gider'), 'string'); ?>,
                    data: monthlyExpenses,
                    backgroundColor: 'rgba(239, 68, 68, 0.8)',
                }, {
                    label: <?php echo safeJsonEncodeForJs(t('finance.netProfit', 'Net Kâr'), 'string'); ?>,
                    data: monthlyProfit,
                    backgroundColor: 'rgba(34, 197, 94, 0.8)',
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }
}

// Date range filter handler
document.getElementById('date-range-filter')?.addEventListener('change', function() {
    if (this.value === 'custom') {
        document.getElementById('custom-date-range')?.classList.remove('hidden');
    } else {
        document.getElementById('custom-date-range')?.classList.add('hidden');
        refreshFinancialData();
    }
});

function applyCustomDateRange() {
    refreshFinancialData();
}

function refreshFinancialData() {
    const dateRange = document.getElementById('date-range-filter')?.value || 'today';
    const startDate = document.getElementById('start-date')?.value || '';
    const endDate = document.getElementById('end-date')?.value || '';
    
    const apiPrefix = <?php echo json_encode($apiPrefix); ?>;
    let url = window.BASE_URL + apiPrefix + '/finance/data?date_range=' + dateRange;
    if (dateRange === 'custom' && startDate && endDate) {
        url += '&start_date=' + startDate + '&end_date=' + endDate;
    }
    
    fetch(url)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data) {
                updateDashboard(data.data);
            }
        })
        .catch(error => {
            console.error('Error fetching financial data:', error);
        });

    loadAnalyticsHub(dateRange, startDate, endDate);
}

// ---------------------------------------------------------------------------
// Operasyonel Analiz (Stok / Fire / Tedarikçi) — FinanceAnalyticsService
// ---------------------------------------------------------------------------
async function loadAnalyticsHub(dateRange, startDate, endDate) {
    const apiPrefix = <?php echo json_encode($apiPrefix); ?>;
    // The analytics endpoint accepts ?range=today|week|month|30d|... Map UI
    // values onto the service's accepted presets. "custom" uses start/end.
    const rangeMap = { today: 'today', week: 'week', month: 'month', custom: 'custom' };
    const range = rangeMap[dateRange] || 'month';
    const qs = new URLSearchParams({ range });
    if (range === 'custom') {
        if (startDate) qs.set('start', startDate);
        if (endDate)   qs.set('end',   endDate);
    }
    // Super admin: pass business_id through if set on window.
    const bid = new URLSearchParams(window.location.search).get('business_id') || window.currentBusinessId;
    if (bid) qs.set('business_id', bid);

    try {
        const res  = await fetch(window.BASE_URL + apiPrefix + '/finance/analytics?' + qs.toString(), { credentials: 'same-origin' });
        const json = await res.json();
        if (!json || !json.success) return;
        if (json.needs_business) return;
        renderAnalyticsHub(json);
    } catch (e) {
        console.error('analytics hub load failed', e);
    }
}

function renderAnalyticsHub(d) {
    const money = v => formatCurrency(v || 0);
    const num   = v => (Number(v) || 0).toLocaleString('tr-TR');

    if (d.range) {
        document.getElementById('analytics-range-label').textContent =
            d.range.start + ' → ' + d.range.end;
    }

    const s = d.stock || {};
    document.getElementById('ah-stock-value').textContent = money(s.stock_value);
    document.getElementById('ah-stock-ing').textContent   = num(s.ingredient_count);
    document.getElementById('ah-stock-menu').textContent  = num(s.menu_item_count);
    document.getElementById('ah-low-stock').textContent   = num(s.low_stock_count);
    document.getElementById('ah-out-stock').textContent   = num(s.out_of_stock_count);

    const w = d.waste || {};
    document.getElementById('ah-waste-cost').textContent  = money(w.total_cost);
    document.getElementById('ah-waste-count').textContent = num(w.record_count);

    const sup = d.suppliers || {};
    document.getElementById('ah-purchase-total').textContent = money(sup.purchase_total);
    document.getElementById('ah-purchase-count').textContent = num(sup.purchase_count);
    document.getElementById('ah-unpaid-total').textContent   = money(sup.unpaid_total);
    document.getElementById('ah-unpaid-count').textContent   = num(sup.unpaid_count);

    // Top wasted ingredients
    const tw = d.top_wasted || [];
    const twBody = document.getElementById('ah-top-wasted');
    twBody.innerHTML = tw.length ? tw.map(r => `
        <tr class="hover:bg-slate-50">
            <td class="py-1.5 font-semibold truncate max-w-[180px]">${escapeHtml(r.name || '—')}</td>
            <td class="py-1.5 text-right">${num(r.record_count)}</td>
            <td class="py-1.5 text-right font-bold text-red-700">${money(r.total_cost)}</td>
        </tr>`).join('') : `<tr><td colspan="3" class="py-4 text-center text-slate-400 text-xs">Fire kaydı yok.</td></tr>`;

    // Waste by reason
    const reasons = d.waste_by_reason || [];
    const total = reasons.reduce((acc, r) => acc + (Number(r.total_cost) || 0), 0);
    const reasonBox = document.getElementById('ah-waste-reasons');
    const reasonLabels = {
        EXPIRED: 'Son Kullanma', SPOILAGE: 'Bozulma', DAMAGED: 'Hasar',
        CONTAMINATED: 'Kontaminasyon', OVER_PRODUCTION: 'Fazla Üretim',
        BURNT: 'Yanık', SPILLAGE: 'Dökülme', SPILL: 'Dökülme',
        QUALITY_DEFECT: 'Kalite', CUSTOMER_RETURN: 'Müşteri İadesi',
        KITCHEN_PREP_LOSS: 'Hazırlık Kaybı', MISTAKE: 'Hata', OTHER: 'Diğer'
    };
    reasonBox.innerHTML = reasons.length ? reasons.map(r => {
        const pct = total > 0 ? Math.round((Number(r.total_cost) / total) * 100) : 0;
        const lbl = reasonLabels[r.reason] || r.reason || '—';
        return `
          <div>
              <div class="flex items-center justify-between text-xs mb-1">
                  <span class="font-bold text-slate-700">${escapeHtml(lbl)}</span>
                  <span class="font-black text-red-700">${money(r.total_cost)}</span>
              </div>
              <div class="h-2 bg-slate-100 rounded-full overflow-hidden">
                  <div class="h-full bg-red-500" style="width:${pct}%"></div>
              </div>
              <div class="text-[10px] text-slate-500 font-semibold mt-0.5">${num(r.record_count)} kayıt · %${pct}</div>
          </div>`;
    }).join('') : '<div class="text-center text-slate-400 text-xs py-4">Fire kaydı yok.</div>';

    // Top suppliers
    const ts = d.top_suppliers || [];
    const detailBase = <?php echo json_encode($adminPrefix . '/finance/suppliers/'); ?>;
    const tsBody = document.getElementById('ah-top-suppliers');
    tsBody.innerHTML = ts.length ? ts.map(r => `
        <tr class="hover:bg-slate-50">
            <td class="py-1.5 font-semibold truncate max-w-[180px]">
                <a href="${detailBase + encodeURIComponent(r.supplier_id)}${bid() ? '?business_id=' + encodeURIComponent(bid()) : ''}" class="hover:text-blue-700">
                    ${escapeHtml(r.name || '—')}
                </a>
            </td>
            <td class="py-1.5 text-right">${num(r.receipt_count)}</td>
            <td class="py-1.5 text-right font-bold text-blue-700">${money(r.total_purchase)}</td>
        </tr>`).join('') : `<tr><td colspan="3" class="py-4 text-center text-slate-400 text-xs">Bu aralıkta alış fişi yok.</td></tr>`;

    function bid() {
        return new URLSearchParams(window.location.search).get('business_id') || window.currentBusinessId || '';
    }
}

function escapeHtml(str) {
    return String(str ?? '').replace(/[&<>"']/g, ch => ({
        '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
    }[ch]));
}

// Initial population of the analytics hub on page load.
document.addEventListener('DOMContentLoaded', () => {
    const dateRange = document.getElementById('date-range-filter')?.value || 'today';
    const startDate = document.getElementById('start-date')?.value || '';
    const endDate   = document.getElementById('end-date')?.value || '';
    loadAnalyticsHub(dateRange, startDate, endDate);
});

function updateDashboard(data) {
    // Update KPIs
    if (data.kpis) {
        document.getElementById('kpi-revenue').textContent = formatCurrency(data.kpis.revenue || 0);
        document.getElementById('kpi-expenses').textContent = formatCurrency(data.kpis.expenses || 0);
        document.getElementById('kpi-profit').textContent = formatCurrency(data.kpis.net_profit || 0);
        document.getElementById('kpi-margin').textContent = '%' + (data.kpis.profit_margin || 0).toFixed(2);
        document.getElementById('kpi-avg-order').textContent = formatCurrency(data.kpis.avg_order_value || 0);
        document.getElementById('kpi-orders').textContent = data.kpis.order_count || 0;
        if (data.cash_flow) {
            document.getElementById('kpi-cashflow').textContent = formatCurrency(data.cash_flow.net_flow || 0);
        }
    }
    
    // Update charts
    if (data.charts) {
        if (data.charts.revenue_expense_trend && revenueExpenseChart) {
            const revenueData = data.charts.revenue_expense_trend.revenue || [];
            const expenseData = data.charts.revenue_expense_trend.expenses || [];
            
            revenueExpenseChart.data.labels = revenueData.map(item => item.day_name || item.date);
            revenueExpenseChart.data.datasets[0].data = revenueData.map(item => parseFloat(item.revenue || 0));
            revenueExpenseChart.data.datasets[1].data = expenseData.map(item => parseFloat(item.expenses || 0));
            revenueExpenseChart.update();
        }
        
        if (data.charts.category_breakdown && categoryChart) {
            const categoryData = data.charts.category_breakdown || [];
            categoryChart.data.labels = categoryData.map(item => item.category);
            categoryChart.data.datasets[0].data = categoryData.map(item => parseFloat(item.amount || 0));
            categoryChart.update();
        }
        
        if (data.charts.monthly_comparison && monthlyChart) {
            const monthlyData = data.charts.monthly_comparison || [];
            monthlyChart.data.labels = monthlyData.map(item => item.month_name || item.month);
            monthlyChart.data.datasets[0].data = monthlyData.map(item => parseFloat(item.revenue || 0));
            monthlyChart.data.datasets[1].data = monthlyData.map(item => parseFloat(item.expenses || 0));
            monthlyChart.data.datasets[2].data = monthlyData.map(item => parseFloat(item.net_profit || 0));
            monthlyChart.update();
        }
    }
}

// formatCurrency is now available globally from utils.js

<?php if ($is_super_admin ?? false): ?>
const bsScript = document.createElement('script');
bsScript.src = '<?php echo BASE_URL; ?>/assets/js/business-selector.js?v=<?php echo time(); ?>';
bsScript.onload = () => {
    BusinessSelector.init({ baseUrl: '<?php echo BASE_URL; ?>' });
    BusinessSelector.loadBusinesses().then(() => {
        BusinessSelector.renderBusinessGrid('business-grid', (id, name) => {
            BusinessSelector.showContentView('business-selection-view', 'finance-management-view', name);
            window.currentBusinessId = id;
        });
    });
};
document.head.appendChild(bsScript);
window.backToBusinessSelection = () => BusinessSelector.showSelectionView('business-selection-view', 'finance-management-view');
<?php endif; ?>
</script>
