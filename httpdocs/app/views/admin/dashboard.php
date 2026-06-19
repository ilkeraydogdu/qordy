<?php
/**
 * Business Dashboard — Marketing #panel layout (live data via poller).
 */
require_once __DIR__ . '/../../helpers/translations.php';
require_once __DIR__ . '/../../core/HelperLoader.php';

\App\Core\HelperLoader::ensureLoaded();
if (!function_exists('getAdminUrl')) {
    require_once __DIR__ . '/../../helpers/url_helper.php';
}
if (!function_exists('formatCurrency')) {
    require_once __DIR__ . '/../../helpers/functions.php';
}

require_once __DIR__ . '/../layouts/components/Icons.php';
require_once __DIR__ . '/../layouts/components/DateRangeFilter.php';
require_once __DIR__ . '/../layouts/components/ChartCanvas.php';
require_once __DIR__ . '/../layouts/components/BusinessPanelShell.php';
require_once __DIR__ . '/../layouts/components/AIInsightsBox.php';

$isSuperAdmin = $is_super_admin ?? false;
$apiPrefix = $isSuperAdmin ? '/api/qodmin' : '/api/business';

$payloadKpi = $kpi ?? [];
$dailyRevenue = $payloadKpi['daily_revenue'] ?? ($daily_revenue ?? 0);
$totalOrdersToday = $payloadKpi['total_orders_today'] ?? ($total_orders_today ?? 0);
$avgOrderValue = $payloadKpi['avg_order_value'] ?? ($avg_order_value ?? 0);
$revenueChange = $payloadKpi['revenue_change_percent'] ?? ($revenue_change_percent ?? null);
$ordersChange = $payloadKpi['orders_change'] ?? ($orders_change ?? null);
$avgOrderChange = $payloadKpi['avg_order_change'] ?? ($avg_order_change ?? null);
$activeTablesCount = $active_tables_count ?? ($payloadKpi['active_tables'] ?? 0);
$totalTables = $total_tables ?? ($payloadKpi['total_tables'] ?? 0);
$realProfit = $real_profit ?? ($payloadKpi['real_profit'] ?? 0);
$profitMargin = $profit_margin_percent ?? ($payloadKpi['profit_margin_percent'] ?? 0);
$expensesToday = $expenses_today ?? ($payloadKpi['expenses_today'] ?? 0);
$cancellationRate = $cancellation_rate ?? ($payloadKpi['cancellation_rate'] ?? 0);
$uniqueCustomers = $unique_customers_today ?? ($payloadKpi['unique_customers_today'] ?? 0);
$tableTurnover = $table_turnover ?? ($payloadKpi['table_turnover'] ?? 0);

$currentRange = $range_key ?? ($_SESSION['dashboard_range'] ?? 'today');
$allowedRanges = ['today', 'week', 'month', '3months', '6months', '9months', 'year', 'custom'];
if (!in_array($currentRange, $allowedRanges, true)) {
    $currentRange = 'today';
}

$zones = [];
if (!empty($zones_formatted ?? []) && is_array($zones_formatted)) {
    $zones = $zones_formatted;
} elseif (!empty($tables ?? [])) {
    $zonesById = [];
    try {
        $zoneService = \App\Core\DependencyFactory::getZoneService();
        foreach ($zoneService->getAllZones() as $z) {
            $zId = $z['zone_id'] ?? null;
            if ($zId) {
                $zonesById[$zId] = $z;
            }
        }
    } catch (\Exception $e) {
        // empty
    }
    foreach ($tables as $table) {
        $zoneName = null;
        $zoneId = $table['zone_id'] ?? null;
        if ($zoneId && isset($zonesById[$zoneId])) {
            $zoneName = $zonesById[$zoneId]['name'] ?? null;
        }
        if (!$zoneName) {
            $zoneName = $table['zone'] ?? null;
        }
        if (!$zoneName || trim($zoneName) === '') {
            $zoneName = t('dashboard.zoneDefault', 'Genel');
        }
        if (!isset($zones[$zoneName])) {
            $zones[$zoneName] = ['total' => 0, 'occupied' => 0];
        }
        $zones[$zoneName]['total']++;
        if (($table['status'] ?? 'FREE') !== 'FREE') {
            $zones[$zoneName]['occupied']++;
        }
    }
}

$fmtDelta = static function ($pct) {
    if ($pct === null || $pct === '') {
        return '';
    }
    $n = (float)$pct;
    return ($n >= 0 ? '+' : '') . rtrim(rtrim(number_format($n, 1, '.', ''), '0'), '.') . '%';
};

$rangeLabels = function_exists('getDashboardRangeLabels')
    ? getDashboardRangeLabels()
    : [
        'today' => 'Bugün',
        'week' => 'Bu Hafta',
        'month' => 'Bu Ay',
        '3months' => 'Son 3 Ay',
        '6months' => 'Son 6 Ay',
        '9months' => 'Son 9 Ay',
        'year' => 'Bu Yıl',
        'custom' => 'Özel Aralık',
    ];
$panelRangeLabel = $rangeLabels[$currentRange] ?? 'Bugün';

$cardRangeKeys = function_exists('getDashboardCardRangeKeys')
    ? getDashboardCardRangeKeys()
    : ['today', 'week', 'month', '3months'];
$cardRangeDefault = in_array($currentRange, $cardRangeKeys, true) ? $currentRange : '';

$renderCardRange = static function (string $widgetId) use ($cardRangeDefault): string {
    if (!function_exists('renderDashboardCardRangeFilter')) {
        return '';
    }
    return renderDashboardCardRangeFilter($widgetId, $cardRangeDefault);
};

$renderPanelSortToggle = static function (string $panelId, string $defaultMode = 'quantity'): string {
    $label = $defaultMode === 'revenue' ? 'Ciro' : 'Adet';
    $title = $defaultMode === 'revenue' ? 'Ciro sıralaması — tıkla: adet' : 'Adet sıralaması — tıkla: ciro';
    return '<button type="button" class="q-panel-sort-btn" data-panel-sort="'
        . htmlspecialchars($panelId, ENT_QUOTES, 'UTF-8')
        . '" data-sort-by="'
        . htmlspecialchars($defaultMode, ENT_QUOTES, 'UTF-8')
        . '" aria-label="Sıralama değiştir" title="'
        . htmlspecialchars($title, ENT_QUOTES, 'UTF-8')
        . '"><svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"/></svg><span class="q-panel-sort-btn__label">'
        . htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
        . '</span></button>';
};

$panelKpis = [
    [
        'label' => 'Toplam Ciro',
        'widget' => 'kpi_revenue',
        'filter' => true,
        'kpi' => 'daily_revenue',
        'delta_kpi' => 'revenue_change',
        'value' => formatCurrency($dailyRevenue),
        'delta' => $fmtDelta($revenueChange),
    ],
    [
        'label' => 'Toplam Sipariş',
        'widget' => 'kpi_orders',
        'filter' => true,
        'kpi' => 'total_orders',
        'delta_kpi' => 'orders_change',
        'value' => (string)$totalOrdersToday,
        'delta' => $fmtDelta($ordersChange),
    ],
    [
        'label' => 'Ortalama Sepet',
        'widget' => 'kpi_avg_basket',
        'filter' => true,
        'kpi' => 'avg_order_value',
        'delta_kpi' => 'avg_order_change',
        'value' => formatCurrency($avgOrderValue),
        'delta' => $fmtDelta($avgOrderChange),
    ],
    [
        'label' => 'Aktif Masa',
        'widget' => 'kpi_active_tables',
        'filter' => false,
        'kpi' => 'active_tables',
        'delta_kpi' => 'active_tables',
        'value' => (string)$activeTablesCount,
        'delta' => $totalTables > 0 ? ($activeTablesCount . '/' . $totalTables . ' dolu') : '',
    ],
];

$secondaryKpis = [
    ['label' => 'Net Kâr', 'kpi' => 'real_profit', 'value' => formatCurrency($realProfit)],
    ['label' => 'Kâr Marjı', 'kpi' => 'profit_margin', 'value' => '%' . $profitMargin],
    ['label' => 'Giderler', 'kpi' => 'expenses_today', 'value' => formatCurrency($expensesToday)],
    ['label' => 'İptal Oranı', 'kpi' => 'cancellation_rate', 'value' => '%' . $cancellationRate],
    ['label' => 'Masa Müşterisi', 'kpi' => 'unique_customers', 'value' => (string)$uniqueCustomers],
    ['label' => 'Masa Devir', 'kpi' => 'table_turnover', 'value' => (string)$tableTurnover],
];

$dateFilterHtml = \App\Views\Components\DateRangeFilter::render([
    'current' => $currentRange,
    'base_url' => BASE_URL . '/business/dashboard',
    'mode' => 'page',
]);

$aiBtn = '';
if (function_exists('hasPermissionForRole') && hasPermissionForRole('dashboard.analytics')) {
    $aiBtn = '<button type="button" data-ai-trigger class="q-btn q-btn--primary q-btn--sm q-panel-ai-btn">'
        . '<svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>'
        . ' AI Danışman</button>';
}

$appDownloadIcon = '<svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>';
$appDownloadsHtml = '<a href="/downloads/qordy-isletme-app.apk" download="qordy-isletme-app.apk" class="q-btn q-btn--ink q-btn--sm">'
    . $appDownloadIcon . ' İşletme App</a>'
    . '<a href="/downloads/qordy-personel-app.apk" download="qordy-personel-app.apk" class="q-btn q-btn--soft q-btn--sm">'
    . $appDownloadIcon . ' Personel App</a>';

$kpiHtml = '';
foreach ($panelKpis as $pk) {
    $widgetId = $pk['widget'] ?? '';
    $hasFilter = !empty($pk['filter']);
    $deltaKey = $pk['delta_kpi'] ?? $pk['kpi'];
    if (!empty($pk['filter']) && !empty($deltaKey)) {
        $deltaInner = !empty($pk['delta'])
            ? '<svg class="q-panel-kpi__delta-icon" width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M7 17L17 7M17 7H9M17 7V15"/></svg>'
                . htmlspecialchars($pk['delta'], ENT_QUOTES, 'UTF-8')
            : '';
        $deltaStyle = empty($pk['delta']) ? ' style="display:none"' : '';
        $deltaHtml = '<div class="q-panel-kpi__delta" data-kpi-delta="' . htmlspecialchars($deltaKey, ENT_QUOTES, 'UTF-8') . '"' . $deltaStyle . '>' . $deltaInner . '</div>';
    } else {
        $deltaHtml = !empty($pk['delta'])
            ? '<div class="q-panel-kpi__delta" data-kpi-delta="' . htmlspecialchars($deltaKey, ENT_QUOTES, 'UTF-8') . '">'
                . htmlspecialchars($pk['delta'], ENT_QUOTES, 'UTF-8') . '</div>'
            : '';
    }
    $filterHtml = $hasFilter && $widgetId ? $renderCardRange($widgetId) : '';
    $widgetAttr = $widgetId ? ' data-widget="' . htmlspecialchars($widgetId, ENT_QUOTES, 'UTF-8') . '"' : '';
    $kpiHtml .= sprintf(
        '<div class="q-panel-kpi"%s>'
        . '<div class="q-panel-kpi__head"><div class="q-panel-kpi__label">%s</div>%s</div>'
        . '<div class="q-panel-kpi__value" data-kpi="%s">%s</div>%s</div>',
        $widgetAttr,
        htmlspecialchars($pk['label'], ENT_QUOTES, 'UTF-8'),
        $filterHtml,
        htmlspecialchars($pk['kpi'], ENT_QUOTES, 'UTF-8'),
        $pk['value'],
        $deltaHtml
    );
}

$secondaryKpiHtml = '';
foreach ($secondaryKpis as $sk) {
    $secondaryKpiHtml .= sprintf(
        '<div class="q-panel-kpi q-panel-kpi--compact">'
        . '<div class="q-panel-kpi__label">%s</div>'
        . '<div class="q-panel-kpi__value" data-kpi="%s">%s</div>'
        . '</div>',
        htmlspecialchars($sk['label'], ENT_QUOTES, 'UTF-8'),
        htmlspecialchars($sk['kpi'], ENT_QUOTES, 'UTF-8'),
        $sk['value']
    );
}

$aiInsightsPanelBody = \App\Views\Components\AIInsightsBox::render([
    'placeholder' => 'AI önerileri yükleniyor…',
]);

$hourlyChart = \App\Views\Components\ChartCanvas::render([
    'title' => '',
    'canvas_id' => 'hourlySalesCanvas',
    'size' => 'sm',
    'bare' => true,
]);

$categoryChart = <<<'HTML'
<div class="q-panel-donut-wrap" data-chart-loading="1">
  <div class="q-panel-donut">
    <canvas id="orderStatusChart" aria-label="Kategorilere göre ciro grafiği"></canvas>
    <div class="q-panel-donut__center" id="category-donut-total" aria-live="polite" aria-label="Toplam ciro">—</div>
  </div>
  <ul id="panel-category-list" data-list-target="panel_category_legend" class="q-panel-category-list q-panel-category-list--scroll" data-list-loading="1">
    <li class="q-panel-empty" data-list-placeholder><div class="q-spinner q-spinner--sm" role="status" aria-label="Yükleniyor"></div></li>
  </ul>
  <div data-chart-placeholder class="q-panel-chart-placeholder">
    <div class="q-spinner q-spinner--lg" role="status" aria-label="Yükleniyor"></div>
  </div>
</div>
HTML;

$orderStatusChart = <<<'HTML'
<div class="q-panel-donut-wrap" data-chart-loading="1" data-widget="panel_order_status">
  <div class="q-panel-donut">
    <canvas id="orderStatusDistributionChart" aria-label="Sipariş durumu grafiği"></canvas>
    <div class="q-panel-donut__center" id="order-status-donut-total" aria-live="polite" aria-label="Toplam sipariş">—</div>
  </div>
  <ul id="order-status-legend" class="q-panel-category-list q-panel-order-status-legend"></ul>
  <div data-chart-placeholder class="q-panel-chart-placeholder">
    <div class="q-spinner q-spinner--lg" role="status" aria-label="Yükleniyor"></div>
  </div>
</div>
HTML;

$panelContent = <<<HTML
<div class="q-panel-toolbar">
  {$dateFilterHtml}
  <div class="q-panel-toolbar__actions">
  {$aiBtn}
  {$appDownloadsHtml}
  </div>
</div>

<div class="q-panel-kpi-grid" aria-label="Ana KPI göstergeleri">{$kpiHtml}</div>

<div class="q-panel-kpi-grid q-panel-kpi-grid--secondary" aria-label="Detay KPI göstergeleri">{$secondaryKpiHtml}</div>

<div class="q-panel-grid q-panel-grid--2">
  <div class="q-panel-card" data-widget="panel_period_compare">
    <div class="q-panel-card__head">
      <span class="q-panel-card__title">Önceki Döneme Göre</span>
HTML;
$panelContent .= $renderCardRange('panel_period_compare');
$panelContent .= <<<'HTML'
    </div>
    <div id="panel-period-compare" data-list-target="period_comparison" class="q-panel-insights-list" data-list-loading="1">
      <div class="q-panel-empty" data-list-placeholder><div class="q-spinner q-spinner--sm" role="status" aria-label="Yükleniyor"></div></div>
    </div>
  </div>

  <div class="q-panel-card" data-widget="panel_auto_insights">
    <div class="q-panel-card__head">
      <span class="q-panel-card__title">Otomatik Öngörü</span>
      <span class="q-panel-card__meta q-panel-badge--muted">Kural tabanlı</span>
HTML;
$panelContent .= $renderCardRange('panel_auto_insights');
$panelContent .= <<<'HTML'
    </div>
    <div id="panel-auto-insights" data-list-target="auto_insights" class="q-panel-insights-list" data-list-loading="1">
      <div class="q-panel-empty" data-list-placeholder><div class="q-spinner q-spinner--sm" role="status" aria-label="Yükleniyor"></div></div>
    </div>
  </div>
</div>

<div class="q-panel-grid q-panel-grid--1">
  <div class="q-panel-card q-panel-card--ai">
    <div class="q-panel-card__head">
      <span class="q-panel-card__title">AI Danışman</span>
      <span class="q-panel-card__meta q-panel-badge--ai">Veri tabanlı</span>
    </div>
HTML;
$panelContent .= $aiInsightsPanelBody;
$panelContent .= <<<'HTML'
  </div>
</div>

<div class="q-panel-grid q-panel-grid--2">
  <div class="q-panel-card">
    <div class="q-panel-card__head">
      <span class="q-panel-card__title">Canlı Sipariş Akışı</span>
      <span class="q-panel-live-badge"><span class="q-panel-live-badge__dot"></span>Canlı</span>
    </div>
    <ul id="panel-live-orders-list" data-list-target="panel_live_orders" class="q-panel-order-list" data-list-loading="1">
      <li class="q-panel-empty" data-list-placeholder>
        <div class="q-spinner q-spinner--lg" role="status" aria-label="Yükleniyor"></div>
        <p>Siparişler yükleniyor…</p>
      </li>
    </ul>
  </div>

  <div class="q-panel-card">
    <div class="q-panel-card__head">
      <span class="q-panel-card__title">Kat / Bölge Doluluk</span>
      <span class="q-panel-card__meta q-panel-badge--muted" id="zones-range-meta"><?php echo htmlspecialchars($panelRangeLabel, ENT_QUOTES, 'UTF-8'); ?> · kullanılan masa</span>
    </div>
    <div data-zones-target="panel_zones">
HTML;

$panelContent .= \App\Views\Components\BusinessPanelShell::zonePerformance($zones);
$panelContent .= <<<'HTML'
    </div>
  </div>
</div>

<div class="q-panel-grid q-panel-grid--3">
  <div class="q-panel-card" data-widget="panel_top_selling">
    <div class="q-panel-card__head">
      <span class="q-panel-card__title">Popüler Ürünler</span>
HTML;
$panelContent .= $renderCardRange('panel_top_selling');
$panelContent .= $renderPanelSortToggle('panel_top_selling', 'quantity');
$panelContent .= <<<'HTML'
    </div>
    <ul id="panel-products-list" data-list-target="panel_top_selling" class="q-panel-product-list q-panel-product-list--scroll" data-list-loading="1">
      <li class="q-panel-empty" data-list-placeholder>
        <div class="q-spinner q-spinner--lg" role="status" aria-label="Yükleniyor"></div>
        <p>Ürünler yükleniyor…</p>
      </li>
    </ul>
  </div>

  <div class="q-panel-card" data-widget="panel_category">
    <div class="q-panel-card__head">
      <span class="q-panel-card__title">Kategorilere Göre Ciro</span>
HTML;
$panelContent .= $renderCardRange('panel_category');
$panelContent .= $renderPanelSortToggle('panel_category', 'revenue');
$panelContent .= <<<'HTML'
    </div>
HTML;
$panelContent .= $categoryChart;
$panelContent .= <<<'HTML'
  </div>

  <div class="q-panel-card" data-widget="panel_hourly">
    <div class="q-panel-card__head">
      <span class="q-panel-card__title">Saatlik Ciro</span>
      <span class="q-panel-card__meta" id="hourly-peak-label" data-peak-for="panel_hourly">—</span>
HTML;
$panelContent .= $renderCardRange('panel_hourly');
$panelContent .= <<<'HTML'
    </div>
HTML;
$panelContent .= $hourlyChart;
$panelContent .= <<<'HTML'
  </div>
</div>

<div class="q-panel-grid q-panel-grid--1">
  <div class="q-panel-card q-panel-card--chart" data-widget="panel_weekly_trend">
    <div class="q-panel-card__head">
      <span class="q-panel-card__title">Ciro Trendi</span>
HTML;
$panelContent .= $renderCardRange('panel_weekly_trend');
$panelContent .= <<<'HTML'
    </div>
HTML;
$panelContent .= \App\Views\Components\ChartCanvas::render([
    'title' => '',
    'canvas_id' => 'weeklyTrendChart',
    'size' => 'sm',
    'bare' => true,
]);
$panelContent .= <<<'HTML'
  </div>
</div>

<div class="q-panel-grid q-panel-grid--2">
  <div class="q-panel-card" data-widget="panel_payment">
    <div class="q-panel-card__head">
      <span class="q-panel-card__title">Ödeme Dağılımı</span>
HTML;
$panelContent .= $renderCardRange('panel_payment');
$panelContent .= <<<'HTML'
    </div>
    <div data-list-target="payment_distribution" class="q-panel-analytics-list" data-list-loading="1">
      <div class="q-panel-empty" data-list-placeholder><div class="q-spinner q-spinner--sm" role="status" aria-label="Yükleniyor"></div></div>
    </div>
  </div>

  <div class="q-panel-card" data-widget="panel_order_sources">
    <div class="q-panel-card__head">
      <span class="q-panel-card__title">Sipariş Kanalları</span>
HTML;
$panelContent .= $renderCardRange('panel_order_sources');
$panelContent .= <<<'HTML'
    </div>
    <div data-list-target="order_sources" class="q-panel-analytics-list" data-list-loading="1">
      <div class="q-panel-empty" data-list-placeholder><div class="q-spinner q-spinner--sm" role="status" aria-label="Yükleniyor"></div></div>
    </div>
  </div>
</div>

<div class="q-panel-grid q-panel-grid--2">
  <div class="q-panel-card q-panel-card--staff" data-widget="panel_staff">
    <div class="q-panel-card__head">
      <span class="q-panel-card__title">Personel Performansı</span>
      <span class="q-staff-performance__count" data-staff-performance-count hidden></span>
HTML;
$panelContent .= $renderCardRange('panel_staff');
$panelContent .= <<<'HTML'
    </div>
    <div class="q-staff-performance">
      <div class="q-staff-performance__scroll q-panel-analytics-list" data-list-target="staff_performance" data-list-loading="1">
        <div class="q-panel-empty" data-list-placeholder><div class="q-spinner q-spinner--sm" role="status" aria-label="Yükleniyor"></div></div>
      </div>
      <div class="q-staff-performance__fade" aria-hidden="true"></div>
    </div>
  </div>

  <div class="q-panel-card">
    <div class="q-panel-card__head">
      <span class="q-panel-card__title">En Aktif Masalar</span>
    </div>
    <div data-list-target="most_active_tables" class="q-panel-analytics-list" data-list-loading="1">
      <div class="q-panel-empty" data-list-placeholder><div class="q-spinner q-spinner--sm" role="status" aria-label="Yükleniyor"></div></div>
    </div>
  </div>
</div>

<div class="q-panel-grid q-panel-grid--2">
  <div class="q-panel-card q-panel-card--chart" data-widget="panel_order_status">
    <div class="q-panel-card__head">
      <span class="q-panel-card__title">Sipariş Durumu</span>
      <span class="q-panel-card__meta q-panel-badge--muted" id="order-status-range-meta"><?php echo htmlspecialchars($panelRangeLabel, ENT_QUOTES, 'UTF-8'); ?></span>
    </div>
HTML;
$panelContent .= $orderStatusChart;
$panelContent .= <<<'HTML'
  </div>

  <div class="q-panel-card">
    <div class="q-panel-card__head">
      <span class="q-panel-card__title">Masa Durumu</span>
    </div>
    <div data-list-target="table_status" class="q-panel-analytics-list" data-list-loading="1">
      <div class="q-panel-empty" data-list-placeholder><div class="q-spinner q-spinner--sm" role="status" aria-label="Yükleniyor"></div></div>
    </div>
  </div>
</div>

<div class="q-panel-grid q-panel-grid--1">
  <div class="q-panel-card">
    <div class="q-panel-card__head">
      <span class="q-panel-card__title">Yoğunluk Haritası</span>
      <span class="q-panel-card__meta" id="heatmap-range-meta" data-heatmap-meta><?php echo htmlspecialchars($panelRangeLabel, ENT_QUOTES, 'UTF-8'); ?></span>
    </div>
    <div data-list-target="heatmap" class="q-panel-heatmap-wrap" data-list-loading="1">
      <div class="q-panel-empty" data-list-placeholder><div class="q-spinner q-spinner--sm" role="status" aria-label="Yükleniyor"></div></div>
    </div>
  </div>
</div>
HTML;
?>

<?php
$rangeStartDate = $range_start_date ?? date('Y-m-d');
$rangeEndDate = $range_end_date ?? date('Y-m-d');
?>
<div class="q-page q-biz-theme animate-slide-up"
     data-dashboard-root
     data-api-prefix="<?php echo htmlspecialchars($apiPrefix, ENT_QUOTES, 'UTF-8'); ?>"
     data-range="<?php echo htmlspecialchars($currentRange, ENT_QUOTES, 'UTF-8'); ?>"
     data-range-start="<?php echo htmlspecialchars($rangeStartDate, ENT_QUOTES, 'UTF-8'); ?>"
     data-range-end="<?php echo htmlspecialchars($rangeEndDate, ENT_QUOTES, 'UTF-8'); ?>">
  <div class="q-container q-container--panel">
    <?php echo $panelContent; ?>
  </div>
</div>

<div id="dashboard-loading" class="hidden q-biz-refresh-indicator" role="status" aria-live="polite">
  <div class="q-spinner" aria-hidden="true"></div>
  <span>Güncelleniyor…</span>
</div>

<script src="https://cdn.jsdelivr.net/npm/apexcharts@3.49.0/dist/apexcharts.min.js"></script>
<script src="/assets/js/dashboard/index.js?v=<?php echo (int)@filemtime(__DIR__ . '/../../../public/assets/js/dashboard/index.js'); ?>" defer></script>
