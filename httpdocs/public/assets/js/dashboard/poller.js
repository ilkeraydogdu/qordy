/**
 * Qordy Dashboard Poller
 *
 * Owns the polling loop, rate-limit backoff, KPI updates, list dispatch,
 * chart painting, and per-widget range batching via ?ranges= JSON.
 */
(function (root) {
  'use strict';

  var POLL_INTERVAL_MS = 20000;
  var RATE_BACKOFF_MS = 60000;
  var isUpdating = false;
  var isBlocked = false;
  var blockedUntil = 0;
  var timer = null;
  var baseUrl = '';
  var apiPrefix = '/api/business';
  var dashboardRoot = null;
  var currentRange = '';
  var initialLoadingTimer = null;
  var started = false;
  var modulesReady = false;
  var pendingPayload = null;
  var lastPayload = null;

  function listsReady() {
    return !!(root.QordyDashboardLists && typeof root.QordyDashboardLists === 'object');
  }

  function chartsReady() {
    return !!(root.QordyDashboardCharts && typeof root.QordyDashboardCharts === 'object');
  }

  function canApplyPayload() {
    return listsReady();
  }

  var WIDGET_CHARTS = {
    panel_hourly: { canvasId: 'hourlySalesCanvas', key: 'hourly_sales', fn: 'hourlySales' },
    panel_category: { canvasId: 'orderStatusChart', key: 'category_revenue', fn: 'categoryRevenue' },
    panel_weekly_trend: { canvasId: 'weeklyTrendChart', key: 'weekly_trend', fn: 'weeklyTrend' }
  };

  var WIDGET_LISTS = {
    panel_top_selling: { target: 'panel_top_selling', key: 'panel_top_selling', altKey: 'top_selling_items' },
    panel_period_compare: { target: 'period_comparison', key: 'period_comparison' },
    panel_auto_insights: { target: 'auto_insights', key: 'auto_insights' },
    panel_payment: { target: 'payment_distribution', key: 'payment_distribution' },
    panel_order_sources: { target: 'order_sources', key: 'order_sources' },
    panel_staff: { target: 'staff_performance', key: 'staff_performance' },
  };

  function showLoading(on) {
    var el = document.getElementById('dashboard-loading');
    if (el) el.classList.toggle('hidden', !on);
  }

  function setKpi(key, value) {
    document.querySelectorAll('[data-kpi="' + key + '"]').forEach(function (n) {
      n.textContent = value;
    });
  }

  function fmtDelta(pct) {
    if (pct === null || pct === undefined || pct === '') return '';
    var n = Number(pct);
    if (isNaN(n)) return '';
    return (n >= 0 ? '+' : '') + String(n).replace(/(\.\d*?)0+$/, '$1').replace(/\.$/, '') + '%';
  }

  function setKpiDelta(key, pct) {
    document.querySelectorAll('[data-kpi-delta="' + key + '"]').forEach(function (el) {
      var text = fmtDelta(pct);
      if (!text) {
        el.style.display = 'none';
        return;
      }
      el.style.display = '';
      var icon = el.querySelector('.q-panel-kpi__delta-icon');
      el.textContent = '';
      if (icon) el.appendChild(icon);
      el.appendChild(document.createTextNode(text));
    });
  }

  function getCardRanges() {
    if (root.QordyDashboardCardRange && typeof root.QordyDashboardCardRange.getApiRangePayload === 'function') {
      var payload = root.QordyDashboardCardRange.getApiRangePayload();
      return payload || {};
    }
    if (root.QordyDashboardCardRange && typeof root.QordyDashboardCardRange.getRanges === 'function') {
      return root.QordyDashboardCardRange.getRanges();
    }
    return {};
  }

  function shouldSendCardRanges(ranges) {
    if (root.QordyDashboardCardRange && typeof root.QordyDashboardCardRange.hasExplicitOverrides === 'function') {
      if (!root.QordyDashboardCardRange.hasExplicitOverrides()) return false;
    }
    return cardRangesDifferFromGlobal(ranges);
  }

  function cardRangesDifferFromGlobal(ranges) {
    var keys = Object.keys(ranges || {});
    if (!keys.length) return false;
    var globalRange = currentRange || 'today';
    for (var i = 0; i < keys.length; i++) {
      if (ranges[keys[i]] !== globalRange) return true;
    }
    return false;
  }

  function clearChartLoading(canvas) {
    if (!canvas) return;
    var chartHost = canvas.closest('[data-chart-loading]') || canvas.parentElement;
    if (chartHost && chartHost.hasAttribute('data-chart-loading')) {
      chartHost.removeAttribute('data-chart-loading');
      var placeholder = chartHost.querySelector('[data-chart-placeholder]');
      if (placeholder) placeholder.remove();
    }
  }

  function paintWidgetChart(widgetId, payload) {
    var spec = WIDGET_CHARTS[widgetId];
    if (!spec || !root.QordyDashboardCharts) return;
    var host = document.querySelector('[data-widget="' + widgetId + '"]');
    var canvas = host ? host.querySelector('#' + spec.canvasId) : document.getElementById(spec.canvasId);
    if (!canvas) return;
    var fn = root.QordyDashboardCharts[spec.fn];
    if (typeof fn !== 'function') return;
    var data = payload[spec.key];
    if (!data) return;
    clearChartLoading(canvas);
    try {
      if (widgetId === 'panel_category' && root.QordyDashboardPanelSort) {
        var catMode = root.QordyDashboardPanelSort.getMode('panel_category');
        fn(canvas, root.QordyDashboardPanelSort.sortCategories(data, catMode), { metric: catMode });
      } else {
        fn(canvas, data);
      }
    } catch (e) {
      if (root.console && typeof root.console.warn === 'function') {
        root.console.warn('[QordyDashboard] chart render failed for ' + widgetId, e);
      }
      return;
    }
    if (widgetId === 'panel_hourly') {
      updateHourlyPeak(data, widgetId);
    }
  }

  function paintWidgetList(widgetId, payload) {
    var spec = WIDGET_LISTS[widgetId];
    if (!spec || !root.QordyDashboardLists) return;
    var host = document.querySelector('[data-widget="' + widgetId + '"]');
    var el = host ? host.querySelector('[data-list-target="' + spec.target + '"]') : null;
    if (!el) return;
    var fn = root.QordyDashboardLists[spec.target];
    if (typeof fn !== 'function') return;
    var payloadData = payload[spec.key] || payload[spec.altKey];
    if (el.hasAttribute('data-list-loading')) {
      el.removeAttribute('data-list-loading');
      var placeholder = el.querySelector('[data-list-placeholder]');
      if (placeholder) placeholder.remove();
    }
    fn(el, payloadData);
  }

  function applyWidgetData(widgetData) {
    if (!widgetData || typeof widgetData !== 'object') return;

    Object.keys(widgetData).forEach(function (widgetId) {
      var w = widgetData[widgetId];
      if (!w) return;

      switch (widgetId) {
        case 'kpi_revenue':
          if (w.daily_revenue !== undefined) setKpi('daily_revenue', fmtMoney(w.daily_revenue));
          if (w.revenue_change !== undefined) setKpiDelta('revenue_change', w.revenue_change);
          break;
        case 'kpi_orders':
          if (w.total_orders !== undefined) setKpi('total_orders', w.total_orders);
          if (w.orders_change !== undefined) setKpiDelta('orders_change', w.orders_change);
          break;
        case 'kpi_avg_basket':
          if (w.avg_order_value !== undefined) setKpi('avg_order_value', fmtMoney(w.avg_order_value));
          if (w.avg_order_change !== undefined) setKpiDelta('avg_order_change', w.avg_order_change);
          break;
        case 'panel_category':
          paintWidgetChart(widgetId, w);
          if (root.QordyDashboardLists && root.QordyDashboardLists.panel_category_legend) {
            var legendHost = document.querySelector('[data-widget="panel_category"]');
            var legendEl = legendHost ? legendHost.querySelector('[data-list-target="panel_category_legend"]') : null;
            if (legendEl && w.category_revenue) {
              if (legendEl.hasAttribute('data-list-loading')) {
                legendEl.removeAttribute('data-list-loading');
                var ph = legendEl.querySelector('[data-list-placeholder]');
                if (ph) ph.remove();
              }
              root.QordyDashboardLists.panel_category_legend(legendEl, w.category_revenue);
            }
          }
          break;
        case 'panel_period_compare':
        case 'panel_auto_insights':
        case 'panel_payment':
        case 'panel_order_sources':
        case 'panel_staff':
          paintWidgetList(widgetId, w);
          break;
        default:
          if (WIDGET_CHARTS[widgetId]) paintWidgetChart(widgetId, w);
          if (WIDGET_LISTS[widgetId]) paintWidgetList(widgetId, w);
      }
    });
  }

  function updateZones(zones) {
    if (!zones || typeof zones !== 'object') return;
    var target = document.querySelector('[data-zones-target="panel_zones"]');
    if (!target || !root.QordyDashboardLists || !root.QordyDashboardLists.panel_zones) return;
    root.QordyDashboardLists.panel_zones(target, zones);
  }

  function dispatchLists(data, widgetData) {
    document.querySelectorAll('[data-list-target]').forEach(function (el) {
      var target = el.getAttribute('data-list-target');
      if (!target || !root.QordyDashboardLists) return;

      if (target === 'panel_top_selling' && widgetData && widgetData.panel_top_selling) return;
      if (target === 'panel_category_legend' && widgetData && widgetData.panel_category) return;
      if (target === 'period_comparison' && widgetData && widgetData.panel_period_compare) return;
      if (target === 'auto_insights' && widgetData && widgetData.panel_auto_insights) return;
      if (target === 'payment_distribution' && widgetData && widgetData.panel_payment) return;
      if (target === 'order_sources' && widgetData && widgetData.panel_order_sources) return;
      if (target === 'staff_performance' && widgetData && widgetData.panel_staff) return;

      if (el.hasAttribute('data-list-loading')) {
        el.removeAttribute('data-list-loading');
        var placeholder = el.querySelector('[data-list-placeholder]');
        if (placeholder) placeholder.remove();
      }

      var fn = root.QordyDashboardLists[target];
      if (typeof fn !== 'function') return;

      var payload = data[target];
      if (target === 'panel_live_orders' && !payload && data.recent_orders) {
        payload = data.recent_orders;
      }
      if (target === 'panel_top_selling' && !payload && data.top_selling_items) {
        payload = data.top_selling_items;
      }
      if (target === 'panel_category_legend' && !payload && data.category_revenue) {
        payload = data.category_revenue;
      }
      fn(el, payload);
    });
  }

  function paintCharts(data, widgetData) {
    var pairs = [
      { id: 'hourlySalesCanvas', key: 'hourly_sales', fn: 'hourlySales', widget: 'panel_hourly' },
      { id: 'orderStatusChart', key: 'category_revenue', fn: 'categoryRevenue', widget: 'panel_category' },
      { id: 'weeklyTrendChart', key: 'weekly_trend', fn: 'weeklyTrend', widget: 'panel_weekly_trend' },
      { id: 'orderStatusDistributionChart', key: 'order_status_distribution', fn: 'orderStatus', widget: null }
    ];

    pairs.forEach(function (p) {
      if (widgetData && widgetData[p.widget]) return;
      var canvas = document.getElementById(p.id);
      if (!canvas || !root.QordyDashboardCharts) return;
      clearChartLoading(canvas);
      var fn = root.QordyDashboardCharts[p.fn];
      if (typeof fn !== 'function') return;
      if (p.fn === 'categoryRevenue' && root.QordyDashboardPanelSort) {
        var cats = data[p.key];
        var mode = root.QordyDashboardPanelSort.getMode('panel_category');
        fn(canvas, root.QordyDashboardPanelSort.sortCategories(cats, mode), { metric: mode });
      } else {
        fn(canvas, data[p.key]);
      }
    });
  }

  function clearLoadingStates() {
    document.querySelectorAll('[data-list-target]').forEach(function (el) {
      if (el.hasAttribute('data-list-loading')) {
        el.removeAttribute('data-list-loading');
        if (el.children.length === 0 || (el.children.length === 1 && el.querySelector('[data-list-placeholder]'))) {
          el.innerHTML = '<div class="flex flex-col items-center justify-center text-center py-6 px-3">'
            + '<div class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-slate-50 mb-2">'
            + '<svg class="w-5 h-5 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-1.414 1.414a1 1 0 01-.707.293h-2.172a1 1 0 01-.707-.293l-1.414-1.414A1 1 0 007.586 13H4"/></svg>'
            + '</div>'
            + '<p class="text-xs font-bold text-slate-500">Henüz veri yok</p>'
            + '<p class="text-[11px] text-slate-400 mt-0.5">Yeni veriler geldiğinde burada görünecek</p>'
            + '</div>';
        }
      }
    });
    document.querySelectorAll('[data-chart-loading]').forEach(function (parent) {
      parent.removeAttribute('data-chart-loading');
      var ph = parent.querySelector('[data-chart-placeholder]');
      if (ph) ph.remove();
    });
  }

  function apply(payload) {
    if (!payload) {
      if (initialLoadingTimer) { clearTimeout(initialLoadingTimer); initialLoadingTimer = null; }
      clearLoadingStates();
      return;
    }

    lastPayload = payload;

    if (!canApplyPayload()) {
      pendingPayload = payload;
      return;
    }

    if (initialLoadingTimer) { clearTimeout(initialLoadingTimer); initialLoadingTimer = null; }
    pendingPayload = null;
    renderPayload(payload);
  }

  function renderPayload(payload) {
    try {
      var data = payload.data || payload;
      var widgetData = data.widget_data || null;

      applyWidgetData(widgetData);

      var kpi = data.kpi || data;
    if (!widgetData || !widgetData.kpi_revenue) {
      if (kpi.daily_revenue !== undefined) setKpi('daily_revenue', fmtMoney(kpi.daily_revenue));
      if (kpi.revenue_change !== undefined) setKpiDelta('revenue_change', kpi.revenue_change);
    }
    if (!widgetData || !widgetData.kpi_orders) {
      if (kpi.total_orders_today !== undefined) setKpi('total_orders', kpi.total_orders_today);
      if (kpi.total_orders !== undefined) setKpi('total_orders', kpi.total_orders);
      if (kpi.orders_change !== undefined) setKpiDelta('orders_change', kpi.orders_change);
    }
    if (!widgetData || !widgetData.kpi_avg_basket) {
      if (kpi.avg_order_value !== undefined) setKpi('avg_order_value', fmtMoney(kpi.avg_order_value));
      if (kpi.avg_order_change !== undefined) setKpiDelta('avg_order_change', kpi.avg_order_change);
    }

    if (kpi.estimated_revenue !== undefined) setKpi('estimated_revenue', fmtMoney(kpi.estimated_revenue));
    if (kpi.real_profit !== undefined) setKpi('real_profit', fmtMoney(kpi.real_profit));
    if (kpi.occupancy_percent !== undefined) setKpi('occupancy', '%' + kpi.occupancy_percent);
    if (kpi.pending_orders_count !== undefined) setKpi('pending_orders', kpi.pending_orders_count);
    if (kpi.estimated_profit !== undefined) setKpi('estimated_profit', fmtMoney(kpi.estimated_profit));
    if (kpi.unique_customers_today !== undefined) setKpi('unique_customers', kpi.unique_customers_today);
    if (kpi.today_served_count !== undefined) setKpi('served_orders', kpi.today_served_count);
    if (kpi.expenses_today !== undefined) setKpi('expenses_today', fmtMoney(kpi.expenses_today));
    if (kpi.profit_margin_percent !== undefined) setKpi('profit_margin', '%' + kpi.profit_margin_percent);
    if (kpi.cost_ratio_percent !== undefined) setKpi('cost_ratio', '%' + kpi.cost_ratio_percent);
    if (kpi.table_turnover !== undefined) setKpi('table_turnover', kpi.table_turnover);
    if (kpi.cancellation_rate !== undefined) setKpi('cancellation_rate', '%' + kpi.cancellation_rate);
    if (kpi.preparing_orders_count !== undefined) setKpi('preparing_orders', kpi.preparing_orders_count);
    if (kpi.ready_orders_count !== undefined) setKpi('ready_orders', kpi.ready_orders_count);

    var activeCount = kpi.active_tables_count !== undefined ? kpi.active_tables_count : kpi.active_tables;
    if (activeCount !== undefined) {
      setKpi('active_tables', activeCount);
      var totalTables = data.total_tables;
      document.querySelectorAll('[data-kpi-delta="active_tables"]').forEach(function (el) {
        if (totalTables > 0) {
          el.textContent = activeCount + '/' + totalTables + ' dolu';
          el.style.display = '';
        }
      });
    }

    updateZones(data.zones);
    updateHeatmapMeta(data.heatmap);
    dispatchLists(data, widgetData);
    paintCharts(data, widgetData);

    if (!widgetData || !widgetData.panel_hourly) {
      updatePanelMeta(data);
    }
    } catch (err) {
      if (root.console && typeof root.console.error === 'function') {
        root.console.error('[QordyDashboard] render failed', err);
      }
    } finally {
      clearLoadingStates();
    }
  }

  function updateHourlyPeak(hourly, widgetId) {
    var peakEl = document.querySelector('[data-peak-for="' + widgetId + '"]') || document.getElementById('hourly-peak-label');
    if (!peakEl || !Array.isArray(hourly) || !hourly.length) return;
    var peak = hourly.reduce(function (best, row) {
      var rev = Number(row.revenue) || 0;
      return rev > (Number(best.revenue) || 0) ? row : best;
    }, hourly[0]);
    var h = peak.hour !== undefined ? peak.hour : '';
    peakEl.textContent = h !== '' ? ('Pik ' + h + ':00') : '—';
  }

  function updateHeatmapMeta(heatmap) {
    var meta = document.getElementById('heatmap-range-meta');
    if (!meta || !heatmap) return;
    var label = heatmap.range_label || '';
    if (heatmap.mode === 'weekday_aggregate' && label) {
      meta.textContent = label + ' · gün ortalaması';
    } else if (label) {
      meta.textContent = label;
    }
    var zonesMeta = document.getElementById('zones-range-meta');
    if (zonesMeta && label) {
      zonesMeta.textContent = label + ' · kullanılan masa';
    }
    var orderStatusMeta = document.getElementById('order-status-range-meta');
    if (orderStatusMeta && label) {
      orderStatusMeta.textContent = label;
    }
  }

  function updatePanelMeta(data) {
    updateHourlyPeak(data.hourly_sales, 'panel_hourly');
  }

  function fmtMoney(n) {
    if (typeof root.formatCurrency === 'function') return root.formatCurrency(n);
    n = Number(n) || 0;
    return '₺' + n.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
  }

  function scheduleBackoff(ms) {
    isBlocked = true;
    blockedUntil = Date.now() + ms;
    if (timer) { clearInterval(timer); timer = null; }
    setTimeout(function () {
      isBlocked = false;
      blockedUntil = 0;
      if (!timer) timer = setInterval(tick, POLL_INTERVAL_MS);
    }, ms);
  }

  function tick() {
    if (isBlocked && Date.now() < blockedUntil) return;
    if (isBlocked && Date.now() >= blockedUntil) { isBlocked = false; blockedUntil = 0; }
    if (isUpdating) return;
    isUpdating = true;
    showLoading(true);

    var ranges = getCardRanges();
    var url = baseUrl + apiPrefix + '/dashboard-data?range=' + encodeURIComponent(currentRange || 'today');
    if (shouldSendCardRanges(ranges)) {
      url += '&ranges=' + encodeURIComponent(JSON.stringify(ranges));
    }
    url += '&_=' + Date.now();

    fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
      .then(function (r) {
        if (r.status === 429) {
          return r.json().catch(function () { return {}; }).then(function (j) {
            var wait = (j && j.retry_after) ? (j.retry_after * 1000) : RATE_BACKOFF_MS;
            scheduleBackoff(wait);
            throw new Error('rate-limited');
          });
        }
        if (!r.ok) throw new Error('http-' + r.status);
        return r.json();
      })
      .then(function (data) { apply(data); })
      .catch(function (err) {
        if (root.console && typeof root.console.warn === 'function') {
          root.console.warn('[QordyDashboard] fetch failed', err && err.message ? err.message : err);
        }
        if (!lastPayload) {
          clearLoadingStates();
        }
      })
      .then(function () { isUpdating = false; showLoading(false); });
  }

  function resolveInitialRange(rootEl) {
    try {
      var urlParams = new URLSearchParams(window.location.search);
      var fromQuery = urlParams.get('range');
      if (fromQuery) return fromQuery;
      var pathMatch = window.location.pathname.match(/\/business\/dashboard\/([^/?#]+)/);
      if (pathMatch && pathMatch[1]) return pathMatch[1];
      return (rootEl && rootEl.getAttribute('data-range')) || 'today';
    } catch (e) {
      return 'today';
    }
  }

  function flushPendingPayload() {
    if (!canApplyPayload()) return;
    var payload = pendingPayload || lastPayload;
    if (!payload) return;
    pendingPayload = null;
    renderPayload(payload);
  }

  function onModulesReady() {
    modulesReady = true;
    var rootEl = document.querySelector('[data-dashboard-root]');
    if (!started && rootEl) {
      start(rootEl);
      return;
    }
    if (started) {
      flushPendingPayload();
      if (!isUpdating) {
        tick();
      }
    }
  }

  window.addEventListener('qordy:dashboard:modules-ready', onModulesReady);

  function start(rootEl) {
    dashboardRoot = rootEl || document;
    baseUrl = (root.BASE_URL || (document.querySelector('meta[name="base-url"]') || {}).content || '').replace(/\/$/, '');
    apiPrefix = (dashboardRoot.getAttribute('data-api-prefix') || apiPrefix);
    currentRange = resolveInitialRange(dashboardRoot);

    if (root.QordyDashboardCardRange && typeof root.QordyDashboardCardRange.start === 'function') {
      root.QordyDashboardCardRange.start();
    }
    if (currentRange
      && root.QordyDashboardCardRange
      && typeof root.QordyDashboardCardRange.syncGlobalRange === 'function') {
      root.QordyDashboardCardRange.syncGlobalRange(currentRange);
    }
    if (root.QordyDashboardCardRange && typeof root.QordyDashboardCardRange.readFromDom === 'function') {
      root.QordyDashboardCardRange.readFromDom();
    }

    if (started) {
      flushPendingPayload();
      return;
    }
    started = true;

    initialLoadingTimer = setTimeout(function () { clearLoadingStates(); }, 12000);

    // modules-ready may have fired before start(); tick immediately when renderers exist.
    if (canApplyPayload()) {
      modulesReady = true;
      tick();
    } else if (modulesReady && canApplyPayload()) {
      tick();
    }
    if (!timer) timer = setInterval(tick, POLL_INTERVAL_MS);

    window.addEventListener('beforeunload', function () {
      if (timer) { clearInterval(timer); timer = null; }
      if (initialLoadingTimer) { clearTimeout(initialLoadingTimer); initialLoadingTimer = null; }
    });

    window.addEventListener('qordy:range-changed', function (e) {
      currentRange = (e && e.detail && e.detail.range) || 'today';
      if (e && e.detail && e.detail.source === 'date-range-filter'
        && root.QordyDashboardCardRange
        && typeof root.QordyDashboardCardRange.syncGlobalRange === 'function') {
        root.QordyDashboardCardRange.syncGlobalRange(currentRange);
      }
      isUpdating = false;
      tick();
    });

    window.addEventListener('qordy:card-range-changed', function () {
      isUpdating = false;
      tick();
    });
  }

  root.QordyDashboard = {
    start: start,
    apply: apply,
    flushPendingPayload: flushPendingPayload
  };
})(window);
