<?php
/**
 * Analytics View - Modern minimal responsive design
 */
$daily_revenue = $daily_revenue ?? [];
$recent_orders = $recent_orders ?? [];
$top_selling_items = $top_selling_items ?? [];
$total_revenue = $total_revenue ?? 0;
$total_orders = $total_orders ?? [];
$avg_order_value = $avg_order_value ?? 0;
$revenue_by_category = $revenue_by_category ?? [];
$hourly_sales = $hourly_sales ?? [];
$date_range = $date_range ?? ['start' => date('Y-m-01'), 'end' => date('Y-m-d')];
$baseUrl = BASE_URL;
if (!function_exists('getAdminUrl')) {
    require_once __DIR__ . '/../../helpers/url_helper.php';
}
$isSuperAdmin = $is_super_admin ?? false;
$adminPrefix = $isSuperAdmin ? '/qodmin' : '/business';
$apiPrefix = $isSuperAdmin ? '/api/qodmin' : '/api/business';
?>


<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/business-theme.css?v=<?php echo @filemtime(dirname(__DIR__, 3) . '/public/assets/css/business-theme.css'); ?>">
<?php
// Date-range filter markup — shared by the super-admin and regular headers
// (rendered once, echoed in both branches) so it never drifts out of sync.
ob_start(); ?>
<form id="dateRangeForm" class="q-page-header__actions" style="align-items:center;">
    <input type="date" id="startDate" value="<?php echo htmlspecialchars($date_range['start']); ?>"
           class="q-input" style="width:auto;padding:7px 10px;font-size:var(--font-size-sm);">
    <input type="date" id="endDate" value="<?php echo htmlspecialchars($date_range['end']); ?>"
           class="q-input" style="width:auto;padding:7px 10px;font-size:var(--font-size-sm);">
    <button type="submit" class="q-btn q-btn--primary q-btn--sm">Uygula</button>
</form>
<?php $dateRangeForm = ob_get_clean(); ?>

<div class="q-page q-biz-theme analytics-page animate-slide-up h-full overflow-y-auto no-scrollbar w-full max-w-full overflow-x-hidden">
  <div class="q-container q-stack q-stack--lg">
    <?php if ($is_super_admin ?? false): ?>
    <!-- SUPER ADMIN: business picker -->
    <div id="business-selection-view">
        <header class="q-page-header">
            <div>
                <p class="q-page-header__eyebrow">İŞLETME</p>
                <h1 class="q-page-header__title">Analitik</h1>
                <p class="q-page-header__subtitle">İşletme seçin</p>
            </div>
        </header>
        <div id="business-grid" class="q-grid q-grid--4" style="margin-top:var(--space-5);"></div>
    </div>

    <div id="analytics-management-view" class="hidden q-stack q-stack--lg">
        <header class="q-page-header">
            <div class="q-toolbar" style="gap:var(--space-3);">
                <button type="button" onclick="backToBusinessSelection()" class="q-btn q-btn--ghost q-btn--sm" aria-label="Geri">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                </button>
                <div>
                    <p class="q-page-header__eyebrow">İŞLETME</p>
                    <h1 class="q-page-header__title"><span id="selected-business-name"></span></h1>
                    <p class="q-page-header__subtitle">Analitik · Performans analizi</p>
                </div>
            </div>
            <?php echo $dateRangeForm; ?>
        </header>
    <?php else: ?>
    <!-- REGULAR VIEW -->
        <header class="q-page-header">
            <div>
                <p class="q-page-header__eyebrow">İŞLETME</p>
                <h1 class="q-page-header__title">Analitik</h1>
                <p class="q-page-header__subtitle">Performans analizi</p>
            </div>
            <?php echo $dateRangeForm; ?>
        </header>
    <?php endif; ?>

    <!-- KPI Cards -->
    <section class="q-grid q-grid--4" aria-label="KPI göstergeleri">
        <div class="q-stat">
            <div class="q-stat__top">
                <span class="q-stat__label">Toplam Gelir</span>
                <span class="q-stat__icon" style="background:rgba(245,158,11,.14);color:#d97706;">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </span>
            </div>
            <div class="q-stat__value" data-kpi="total_revenue"><?php echo formatCurrency($total_revenue); ?></div>
        </div>
        <div class="q-stat">
            <div class="q-stat__top">
                <span class="q-stat__label">Toplam Sipariş</span>
                <span class="q-stat__icon" style="background:var(--biz-indigo-soft,#eef2ff);color:var(--biz-indigo-hover,#4f46e5);">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                </span>
            </div>
            <div class="q-stat__value" data-kpi="total_orders"><?php echo is_array($total_orders) ? count($total_orders) : 0; ?></div>
        </div>
        <div class="q-stat">
            <div class="q-stat__top">
                <span class="q-stat__label">Ortalama Sepet</span>
                <span class="q-stat__icon" style="background:var(--biz-indigo-soft,#eef2ff);color:var(--biz-indigo,#6366f1);">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg>
                </span>
            </div>
            <div class="q-stat__value" data-kpi="avg_order_value"><?php echo formatCurrency($avg_order_value); ?></div>
        </div>
        <div class="q-stat">
            <div class="q-stat__top">
                <span class="q-stat__label">Tamamlanma</span>
                <span class="q-stat__icon" style="background:rgba(16,185,129,.12);color:#059669;">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                </span>
            </div>
            <div class="q-stat__value" data-kpi="completed_percent">
                <?php
                $completedStatuses = ['SERVED', 'READY', 'DELIVERED', 'ON_DELIVERY'];
                $totalOrdersCount = is_array($total_orders) ? count($total_orders) : 0;
                $completedOrders = is_array($total_orders) ? count(array_filter($total_orders, function($order) use ($completedStatuses) {
                    return in_array($order['status'] ?? '', $completedStatuses, true);
                })) : 0;
                echo $totalOrdersCount > 0 ? round(($completedOrders / $totalOrdersCount) * 100, 1) : 0;
                ?>%
            </div>
        </div>
    </section>

    <!-- Charts -->
    <div class="q-grid q-grid--sidebar">
        <section class="q-card q-card--pad">
            <h3 class="q-section-title">Gelir Grafiği</h3>
            <div class="q-chart-wrap" style="height:320px;">
                <canvas id="revenueChart"></canvas>
            </div>
        </section>
        <section class="q-card q-card--pad">
            <h3 class="q-section-title">Kategori Satışları</h3>
            <div class="q-chart-wrap" style="height:320px;">
                <canvas id="categoryChart"></canvas>
            </div>
        </section>
    </div>

    <div class="q-grid q-grid--2">
        <section class="q-card q-card--pad">
            <h3 class="q-section-title">Saatlik Aktivite</h3>
            <div class="q-chart-wrap" style="height:288px;">
                <canvas id="hourlyChart"></canvas>
            </div>
        </section>
        <section class="q-card q-card--pad">
            <h3 class="q-section-title">En Çok Satanlar</h3>
            <div class="q-chart-wrap" style="height:288px;">
                <canvas id="topSellingChart"></canvas>
            </div>
        </section>
    </div>

    <!-- End of Day & Z Report -->
    <section class="q-card q-card--pad">
        <div class="q-toolbar q-toolbar--between">
            <div>
                <h3 class="q-section-title" style="margin:0;">Gün Sonu Z Raporu</h3>
                <p class="q-hint">Kurumsal gün sonu raporu</p>
            </div>
            <div class="q-toolbar">
                <input type="date" id="zReportDate" value="<?php echo htmlspecialchars($date_range['end'] ?? date('Y-m-d')); ?>"
                       class="q-input" style="width:auto;padding:7px 10px;font-size:var(--font-size-sm);">
                <button id="endOfDayBtn" class="q-btn q-btn--soft q-btn--sm">Özet</button>
                <button id="zReportPdfBtn" class="q-btn q-btn--primary q-btn--sm">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    PDF
                </button>
                <button id="zReportPrintBtn" class="q-btn q-btn--ghost q-btn--sm">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                    Yazdır
                </button>
            </div>
        </div>
        <div id="endOfDayResult" class="mt-4 hidden"></div>
    </section>

    <!-- Recent Orders -->
    <section class="q-card q-card--pad">
        <div class="q-toolbar q-toolbar--between" style="margin-bottom:var(--space-4);">
            <h3 class="q-section-title" style="margin:0;">Son Siparişler</h3>
            <span class="q-badge"><?php echo count($recent_orders); ?> sipariş</span>
        </div>
        <div class="space-y-2">
            <?php foreach (array_slice($recent_orders, 0, 10) as $order): ?>
                <div class="order-row border border-slate-200/60 rounded-xl overflow-hidden">
                    <div class="p-3 cursor-pointer flex items-center justify-between" 
                         onclick="toggleOrderItems('<?php echo htmlspecialchars($order['order_id'] ?? ''); ?>')">
                        <div class="flex items-center gap-3 flex-1 min-w-0">
                            <svg class="w-3.5 h-3.5 text-slate-300 transition-transform order-arrow flex-shrink-0" id="arrow-<?php echo htmlspecialchars($order['order_id'] ?? ''); ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                            </svg>
                            <div class="flex-1 flex flex-wrap items-center gap-x-4 gap-y-1 min-w-0">
                                <span class="text-xs font-semibold text-slate-900">#<?php echo htmlspecialchars($order['order_id'] ?? ''); ?></span>
                                <span class="text-xs text-slate-500 font-medium"><?php echo htmlspecialchars($order['table_name'] ?? 'N/A'); ?></span>
                                <span class="status-badge px-2 py-0.5 rounded-md font-semibold uppercase inline-block <?php 
                                    $status = strtolower($order['status'] ?? 'pending');
                                    echo $status === 'served' ? 'bg-emerald-50 text-emerald-600' : 
                                         ($status === 'pending' ? 'bg-amber-50 text-amber-600' : 
                                         ($status === 'preparing' ? 'bg-blue-50 text-blue-600' : 
                                         ($status === 'ready' ? 'bg-green-50 text-green-600' : 
                                         ($status === 'cancelled' ? 'bg-red-50 text-red-600' : 'bg-slate-50 text-slate-600'))));
                                ?>">
                                    <?php echo htmlspecialchars($order['status'] ?? 'PENDING'); ?>
                                </span>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 flex-shrink-0 ml-2">
                            <span class="text-xs font-bold text-slate-900"><?php echo formatCurrency($order['total_amount'] ?? 0); ?></span>
                            <span class="text-[10px] text-slate-400 hidden sm:inline"><?php echo date('d/m H:i', strtotime($order['created_at'] ?? 'now')); ?></span>
                        </div>
                    </div>
                    
                    <div class="order-items hidden" id="items-<?php echo htmlspecialchars($order['order_id'] ?? ''); ?>">
                        <div class="px-3 pb-3 pt-0">
                            <div class="bg-slate-50/80 rounded-lg p-3">
                                <?php if (!empty($order['items']) && is_array($order['items'])): ?>
                                    <div class="space-y-1.5">
                                        <?php foreach (function_exists('groupOrderItemsForDisplay') ? groupOrderItemsForDisplay($order['items']) : $order['items'] as $item): ?>
                                            <div class="flex items-center justify-between">
                                                <div class="flex items-center gap-2 min-w-0">
                                                    <span class="text-xs font-medium text-slate-700 truncate">
                                                        <?php echo htmlspecialchars($item['item_name'] ?? $item['name'] ?? 'Bilinmeyen Ürün'); ?>
                                                    </span>
                                                    <span class="text-[10px] text-slate-400">x<?php echo ($item['quantity'] ?? 1); ?></span>
                                                </div>
                                                <span class="text-xs font-semibold text-slate-900 ml-2">
                                                    <?php echo formatCurrency(($item['price'] ?? 0) * ($item['quantity'] ?? 1)); ?>
                                                </span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <p class="text-center text-slate-400 text-xs py-2">Sipariş öğesi bulunamadı</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (empty($recent_orders)): ?>
                <div class="text-center py-8">
                    <svg class="w-10 h-10 text-slate-200 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    <p class="text-xs font-medium text-slate-400">Sipariş bulunamadı</p>
                </div>
            <?php endif; ?>
        </div>
    </section>
    <?php if ($is_super_admin ?? false): ?>
    </div><!-- /#analytics-management-view -->
    <?php endif; ?>
  </div><!-- /.q-container -->
</div><!-- /.q-page -->

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const revenueData = <?php echo safeJsonEncodeForJs($daily_revenue ?? [], 'array'); ?>;
    const categoryData = <?php echo safeJsonEncodeForJs($revenue_by_category ?? [], 'array'); ?>;
    const hourlyData = <?php echo safeJsonEncodeForJs($hourly_sales ?? [], 'array'); ?>;
    const topSellingData = <?php echo safeJsonEncodeForJs($top_selling_items ?? [], 'array'); ?>;

    // Chart.js global defaults - minimal style
    Chart.defaults.font.family = "'Inter', -apple-system, sans-serif";
    Chart.defaults.font.size = 11;
    Chart.defaults.color = '#94a3b8';
    
    const revenueLabels = revenueData && Array.isArray(revenueData) && revenueData.length > 0 
        ? revenueData.map(item => item.date || item['date'])
        : [];
    const revenueValues = revenueData && Array.isArray(revenueData) && revenueData.length > 0
        ? revenueData.map(item => parseFloat(item.revenue || item['revenue'] || 0))
        : [];

    const hourlyMap = {};
    if (hourlyData && Array.isArray(hourlyData)) {
        hourlyData.forEach(item => {
            hourlyMap[parseInt(item.hour || 0)] = {
                order_count: parseInt(item.order_count || 0),
                revenue: parseFloat(item.revenue || 0)
            };
        });
    }
    
    const hourlyLabels = [];
    const hourlyOrderCounts = [];
    for (let h = 0; h < 24; h++) {
        hourlyLabels.push(h + ':00');
        hourlyOrderCounts.push(hourlyMap[h] ? hourlyMap[h].order_count : 0);
    }

    const topSellingLabels = topSellingData && Array.isArray(topSellingData) && topSellingData.length > 0
        ? topSellingData.map(item => (item.name || 'Bilinmeyen').substring(0, 20))
        : [];
    const topSellingCounts = topSellingData && Array.isArray(topSellingData) && topSellingData.length > 0
        ? topSellingData.map(item => parseInt(item.count || 0))
        : [];

    let revenueChartInstance = null;
    let categoryChartInstance = null;
    let hourlyChartInstance = null;
    let topSellingChartInstance = null;

    if (window.chartInstances) {
        Object.values(window.chartInstances).forEach(c => { if (c) c.destroy(); });
    }
    window.chartInstances = {};

    // Revenue Chart
    const revenueCtx = document.getElementById('revenueChart');
    if (revenueCtx) {
        revenueChartInstance = new Chart(revenueCtx, {
            type: 'line',
            data: {
                labels: revenueLabels,
                datasets: [{
                    label: 'Gelir',
                    data: revenueValues,
                    borderColor: '#6366f1',
                    backgroundColor: 'rgba(99, 102, 241, 0.10)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 0,
                    pointHoverRadius: 4,
                    pointHoverBorderWidth: 2,
                    pointHoverBackgroundColor: '#fff',
                    pointHoverBorderColor: '#6366f1'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(0,0,0,0.03)', drawBorder: false },
                        border: { display: false },
                        ticks: { callback: v => '₺' + v.toLocaleString('tr-TR') }
                    },
                    x: {
                        grid: { display: false },
                        border: { display: false }
                    }
                }
            }
        });
        window.chartInstances.revenueChart = revenueChartInstance;
    }

    // Category Chart
    const categoryCtx = document.getElementById('categoryChart');
    if (categoryCtx && categoryData && categoryData.length > 0) {
        categoryChartInstance = new Chart(categoryCtx, {
            type: 'doughnut',
            data: {
                labels: categoryData.map(item => item.category_name || 'Bilinmeyen'),
                datasets: [{
                    data: categoryData.map(item => parseFloat(item.revenue || 0)),
                    backgroundColor: ['#6366f1', '#f59e0b', '#4338ca', '#818cf8', '#fbbf24', '#94a3b8'],
                    borderWidth: 0,
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { font: { size: 10 }, padding: 8, usePointStyle: true, pointStyleWidth: 8 }
                    }
                }
            }
        });
        window.chartInstances.categoryChart = categoryChartInstance;
    }

    // Hourly Chart
    const hourlyCtx = document.getElementById('hourlyChart');
    if (hourlyCtx) {
        hourlyChartInstance = new Chart(hourlyCtx, {
            type: 'bar',
            data: {
                labels: hourlyLabels,
                datasets: [{
                    label: 'Sipariş',
                    data: hourlyOrderCounts,
                    backgroundColor: 'rgba(99, 102, 241, 0.18)',
                    hoverBackgroundColor: '#6366f1',
                    borderRadius: 4,
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(0,0,0,0.03)', drawBorder: false },
                        border: { display: false },
                        ticks: { stepSize: 1 }
                    },
                    x: { grid: { display: false }, border: { display: false } }
                }
            }
        });
        window.chartInstances.hourlyChart = hourlyChartInstance;
    }

    // Top Selling Chart
    const topSellingCtx = document.getElementById('topSellingChart');
    if (topSellingCtx) {
        topSellingChartInstance = new Chart(topSellingCtx, {
            type: 'bar',
            data: {
                labels: topSellingLabels,
                datasets: [{
                    label: 'Satış',
                    data: topSellingCounts,
                    backgroundColor: 'rgba(245, 158, 11, 0.28)',
                    hoverBackgroundColor: '#f59e0b',
                    borderRadius: 4,
                    borderWidth: 0
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: {
                        beginAtZero: true,
                        grid: { color: 'rgba(0,0,0,0.03)', drawBorder: false },
                        border: { display: false },
                        ticks: { stepSize: 1 }
                    },
                    y: { grid: { display: false }, border: { display: false } }
                }
            }
        });
        window.chartInstances.topSellingChart = topSellingChartInstance;
    }

    // Date range form
    document.getElementById('dateRangeForm')?.addEventListener('submit', function(e) {
        e.preventDefault();
        const startDate = document.getElementById('startDate').value;
        const endDate = document.getElementById('endDate').value;
        const adminPrefix = <?php echo json_encode($adminPrefix); ?>;
        window.location.href = `<?php echo BASE_URL; ?>${adminPrefix}/analytics?start_date=${startDate}&end_date=${endDate}`;
    });

    // Polling for live updates
    function updateAnalyticsData() {
        const startDate = document.getElementById('startDate')?.value || '<?php echo $date_range['start']; ?>';
        const endDate = document.getElementById('endDate')?.value || '<?php echo $date_range['end']; ?>';
        const apiPrefix = <?php echo json_encode($apiPrefix); ?>;
        
        fetch(`<?php echo BASE_URL; ?>${apiPrefix}/analytics-data?start_date=${startDate}&end_date=${endDate}`, {
            method: 'GET',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        })
        .then(r => r.json())
        .then(data => {
            if (data.error) return;

            if (data.total_revenue !== undefined) {
                const el = document.querySelector('[data-kpi="total_revenue"]');
                if (el) el.textContent = new Intl.NumberFormat('tr-TR', { style: 'currency', currency: '<?php echo getAppConfig()->getCurrency(); ?>', minimumFractionDigits: 2 }).format(data.total_revenue);
            }
            if (data.total_orders !== undefined) {
                const el = document.querySelector('[data-kpi="total_orders"]');
                if (el) el.textContent = data.total_orders;
            }
            if (data.avg_order_value !== undefined) {
                const el = document.querySelector('[data-kpi="avg_order_value"]');
                if (el) el.textContent = new Intl.NumberFormat('tr-TR', { style: 'currency', currency: '<?php echo getAppConfig()->getCurrency(); ?>', minimumFractionDigits: 2 }).format(data.avg_order_value);
            }
            if (data.total_orders !== undefined && data.completed_orders_count !== undefined) {
                const el = document.querySelector('[data-kpi="completed_percent"]');
                if (el) el.textContent = (data.total_orders > 0 ? Math.round((data.completed_orders_count / data.total_orders) * 100 * 10) / 10 : 0) + '%';
            }
            if (data.daily_revenue && revenueChartInstance) {
                revenueChartInstance.data.labels = data.daily_revenue.map(i => i.date);
                revenueChartInstance.data.datasets[0].data = data.daily_revenue.map(i => parseFloat(i.revenue || 0));
                revenueChartInstance.update('none');
            }
            if (data.revenue_by_category && categoryChartInstance && data.revenue_by_category.length > 0) {
                categoryChartInstance.data.labels = data.revenue_by_category.map(i => i.category_name || 'Bilinmeyen');
                categoryChartInstance.data.datasets[0].data = data.revenue_by_category.map(i => parseFloat(i.revenue || 0));
                categoryChartInstance.update('none');
            }
            if (data.hourly_sales && hourlyChartInstance) {
                const hm = {};
                if (Array.isArray(data.hourly_sales)) data.hourly_sales.forEach(i => { hm[parseInt(i.hour || 0)] = parseInt(i.order_count || 0); });
                const nc = []; for (let h = 0; h < 24; h++) nc.push(hm[h] || 0);
                hourlyChartInstance.data.datasets[0].data = nc;
                hourlyChartInstance.update('none');
            }
            if (data.top_selling_items && topSellingChartInstance && data.top_selling_items.length > 0) {
                topSellingChartInstance.data.labels = data.top_selling_items.map(i => (i.name || 'Bilinmeyen').substring(0, 20));
                topSellingChartInstance.data.datasets[0].data = data.top_selling_items.map(i => parseInt(i.count || 0));
                topSellingChartInstance.update('none');
            }
        })
        .catch(() => {});
    }

    updateAnalyticsData();
    let analyticsPollInterval = setInterval(updateAnalyticsData, 30000);
    window.addEventListener('beforeunload', () => clearInterval(analyticsPollInterval));
    
    // Toggle order items
    window.toggleOrderItems = function(orderId) {
        const el = document.getElementById('items-' + orderId);
        const arrow = document.getElementById('arrow-' + orderId);
        if (el && arrow) { el.classList.toggle('hidden'); arrow.classList.toggle('rotate-90'); }
    };
    
    // End of Day
    document.getElementById('endOfDayBtn')?.addEventListener('click', function() {
        const btn = this;
        const resultDiv = document.getElementById('endOfDayResult');
        const selectedDate = document.getElementById('zReportDate')?.value || new Date().toISOString().split('T')[0];
        btn.disabled = true; btn.textContent = 'Yükleniyor...';
        resultDiv.classList.add('hidden');
        const apiPrefix = <?php echo json_encode($apiPrefix); ?>;
        
        fetch(`<?php echo BASE_URL; ?>${apiPrefix}/end-of-day?date=${selectedDate}`, {
            method: 'GET', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin'
        })
        .then(r => r.json())
        .then(data => {
            btn.disabled = false; btn.textContent = 'Özet';
            if (data.success && data.summary) {
                const s = data.summary;
                const fd = new Date(selectedDate).toLocaleDateString('tr-TR');
                const fmt = v => new Intl.NumberFormat('tr-TR', {style:'currency',currency:'TRY'}).format(v || 0);
                resultDiv.innerHTML = `
                    <div class="bg-emerald-50/60 border border-emerald-200/60 rounded-xl p-4">
                        <div class="text-xs font-semibold text-emerald-700 mb-3">Gün Sonu - ${fd}</div>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                            <div><div class="text-[9px] text-emerald-600 font-semibold uppercase">Gelir</div><div class="text-sm font-bold text-emerald-900">${fmt(s.total_revenue)}</div></div>
                            <div><div class="text-[9px] text-emerald-600 font-semibold uppercase">Sipariş</div><div class="text-sm font-bold text-emerald-900">${s.total_orders || 0}</div></div>
                            <div><div class="text-[9px] text-emerald-600 font-semibold uppercase">Ortalama</div><div class="text-sm font-bold text-emerald-900">${fmt(s.avg_order_value)}</div></div>
                            <div><div class="text-[9px] text-emerald-600 font-semibold uppercase">Tamamlanma</div><div class="text-sm font-bold text-emerald-900">${s.completion_rate || 0}%</div></div>
                        </div>
                    </div>`;
                resultDiv.classList.remove('hidden');
            } else {
                resultDiv.innerHTML = '<div class="bg-red-50 border border-red-200/60 rounded-xl p-3 text-red-600 text-xs font-medium">Rapor alınamadı</div>';
                resultDiv.classList.remove('hidden');
            }
        })
        .catch(() => {
            btn.disabled = false; btn.textContent = 'Özet';
            resultDiv.innerHTML = '<div class="bg-red-50 border border-red-200/60 rounded-xl p-3 text-red-600 text-xs font-medium">Bir hata oluştu</div>';
            resultDiv.classList.remove('hidden');
        });
    });
    
    // Z Report PDF
    document.getElementById('zReportPdfBtn')?.addEventListener('click', function() {
        const selectedDate = document.getElementById('zReportDate')?.value || new Date().toISOString().split('T')[0];
        const apiPrefix = <?php echo json_encode($apiPrefix); ?>;
        const urlParams = new URLSearchParams(window.location.search);
        const businessId = urlParams.get('business_id');
        let url = `<?php echo BASE_URL; ?>${apiPrefix}/z-report-pdf?date=${encodeURIComponent(selectedDate)}`;
        if (businessId) {
            url += `&business_id=${encodeURIComponent(businessId)}`;
        }
        window.open(url, '_blank');
    });
    
    // Z Report Print
    document.getElementById('zReportPrintBtn')?.addEventListener('click', function() {
        const btn = this;
        const selectedDate = document.getElementById('zReportDate')?.value || new Date().toISOString().split('T')[0];
        const apiPrefix = <?php echo json_encode($apiPrefix); ?>;
        btn.disabled = true;
        const origHTML = btn.innerHTML;
        btn.innerHTML = '<span class="text-xs">Gönderiliyor...</span>';
        
        // Get CSRF token
        const metaTag = document.querySelector('meta[name="csrf-token"]');
        const csrfToken = metaTag ? metaTag.getAttribute('content') : (window.CSRF_TOKEN || '');
        
        fetch(`<?php echo BASE_URL; ?>${apiPrefix}/z-report-print`, {
            method: 'POST', 
            headers: { 
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            }, 
            body: JSON.stringify({ date: selectedDate })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                if (window.NotificationManager) window.NotificationManager.success('Yazıcıya gönderildi!');
                btn.innerHTML = '<span class="text-xs text-green-600">Gönderildi!</span>';
                setTimeout(() => { btn.innerHTML = origHTML; btn.disabled = false; }, 2000);
            } else {
                if (window.NotificationManager) window.NotificationManager.error('Hata: ' + (data.error || 'Bilinmeyen hata'));
                else alert('Hata: ' + (data.error || 'Bilinmeyen hata'));
                btn.innerHTML = origHTML; btn.disabled = false;
            }
        })
        .catch(err => {
            if (window.NotificationManager) window.NotificationManager.error('Bağlantı hatası: ' + err.message);
            else alert('Bağlantı hatası: ' + err.message);
            btn.innerHTML = origHTML; btn.disabled = false;
        });
    });
});

<?php if ($is_super_admin ?? false): ?>
const bsScript = document.createElement('script');
bsScript.src = '<?php echo BASE_URL; ?>/assets/js/business-selector.js?v=<?php echo time(); ?>';
bsScript.onload = function() {
    if (!BusinessSelector) return;
    BusinessSelector.init({ baseUrl: '<?php echo BASE_URL; ?>' });
    const urlParams = new URLSearchParams(window.location.search);
    const businessIdFromUrl = urlParams.get('business_id');
    
    if (businessIdFromUrl) {
        const apiPrefix = <?php echo json_encode($apiPrefix); ?>;
        fetch(`${BusinessSelector.config.baseUrl}${apiPrefix}/businesses`)
            .then(r => r.json())
            .then(data => {
                const businesses = Array.isArray(data) ? data : (data.businesses || data.data || []);
                const business = businesses.find(b => (b.business_id || b.id) === businessIdFromUrl);
                if (business) {
                    let businessName = business.company_name || business.business_name || business.name;
                    if (!businessName || !businessName.trim()) {
                        businessName = business.owner_name || business.owner || (business.email ? business.email.split('@')[0] : 'İşletme');
                    }
                    sessionStorage.setItem('selected_business_id', businessIdFromUrl);
                    sessionStorage.setItem('selected_business_name', businessName);
                    window.currentBusinessId = businessIdFromUrl;
                    BusinessSelector.showContentView('business-selection-view', 'analytics-management-view', businessName);
                    if (typeof loadBusinessAnalytics === 'function') loadBusinessAnalytics(businessIdFromUrl, businessName);
                }
            })
            .catch(e => console.error('Error:', e));
    } else {
        BusinessSelector.loadBusinesses().then(() => {
            BusinessSelector.renderBusinessGrid('business-grid', (id, name) => {
                window.currentBusinessId = id;
                sessionStorage.setItem('selected_business_id', id);
                sessionStorage.setItem('selected_business_name', name);
                BusinessSelector.showContentView('business-selection-view', 'analytics-management-view', name);
                const url = new URL(window.location); url.searchParams.set('business_id', id);
                window.history.pushState({}, '', url);
                if (typeof loadBusinessAnalytics === 'function') loadBusinessAnalytics(id, name);
            });
        });
    }
};
document.head.appendChild(bsScript);
window.backToBusinessSelection = () => {
    BusinessSelector.showSelectionView('business-selection-view', 'analytics-management-view');
    window.currentBusinessId = null;
    sessionStorage.removeItem('selected_business_id');
    sessionStorage.removeItem('selected_business_name');
    const url = new URL(window.location.href); url.searchParams.delete('business_id');
    window.history.pushState({}, '', url.toString());
};
<?php endif; ?>
</script>
