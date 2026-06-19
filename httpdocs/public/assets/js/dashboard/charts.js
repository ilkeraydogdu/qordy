/**
 * Qordy Dashboard Charts — ApexCharts Edition
 *
 * Professional, animated, interactive charts powered by ApexCharts.
 * Each function is idempotent — re-renders into the same container.
 */
(function (root) {
  'use strict';

  var palette = {
    revenue: '#6366f1',
    revenueLight: '#818cf8',
    orders: '#f59e0b',
    profit: '#10b981',
    pending: '#f59e0b',
    preparing: '#6366f1',
    ready: '#10b981',
    served: '#059669',
    cancelled: '#ef4444',
    donut: ['#6366f1', '#f59e0b', '#ec4899', '#10b981', '#3b82f6', '#94a3b8'],
  };

  // Store chart instances for re-rendering
  var instances = {};

  function destroyIfExists(id) {
    if (instances[id]) {
      try { instances[id].destroy(); } catch (e) { /* noop */ }
      delete instances[id];
    }
  }

  function fmtMoney(n) {
    if (typeof root.formatCurrency === 'function') return root.formatCurrency(n);
    n = Number(n) || 0;
    if (n >= 1000000) return '₺' + (n / 1000000).toFixed(1) + 'M';
    if (n >= 1000) return '₺' + (n / 1000).toFixed(1) + 'K';
    return '₺' + n.toFixed(0);
  }

  function fmtMoneyFull(n) {
    if (typeof root.formatCurrency === 'function') return root.formatCurrency(n);
    n = Number(n) || 0;
    return '₺' + n.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
  }

  var PANEL_DONUT_LABELS_OFF = {
    show: false,
    name: { show: false },
    value: { show: false },
    total: { show: false }
  };

  var PANEL_DONUT_PX = 168;

  function updateDonutCenterLabel(el, amount) {
    if (!el) return;
    var text = fmtMoneyFull(amount);
    el.setAttribute('title', text);
    el.setAttribute('aria-label', 'Toplam ciro: ' + text);
    el.style.fontSize = '';
    el.style.transform = '';
    el.classList.remove('q-panel-donut__center--compact');

    var mainPart = text;
    var subPart = '';
    if (/,\d{1,2}$/.test(text)) {
      var commaIdx = text.lastIndexOf(',');
      mainPart = text.slice(0, commaIdx);
      subPart = text.slice(commaIdx);
    }

    el.textContent = '';
    if (text.length > 10 && subPart) {
      el.classList.add('q-panel-donut__center--compact');
      var mainSpan = document.createElement('span');
      mainSpan.className = 'q-panel-donut__value-main';
      mainSpan.textContent = mainPart;
      var subSpan = document.createElement('span');
      subSpan.className = 'q-panel-donut__value-sub';
      subSpan.textContent = subPart;
      el.appendChild(mainSpan);
      el.appendChild(subSpan);
      return;
    }

    el.textContent = text;
  }

  var Charts = {

    hourlySales: function (canvas, data) {
      if (!canvas || !Array.isArray(data) || !data.length) return false;

      var container = canvas.parentElement;
      destroyIfExists('hourlySales');
      canvas.style.display = 'none';

      var div = container.querySelector('.apexcharts-wrap') || document.createElement('div');
      div.className = 'apexcharts-wrap';
      div.style.width = '100%';
      div.style.height = '100%';
      if (!div.parentElement) container.appendChild(div);

      var hours = data.map(function (d) { return (d.hour !== undefined ? d.hour : '') + ':00'; });
      var revenues = data.map(function (d) { return d.revenue || 0; });
      var counts = data.map(function (d) { return d.order_count || 0; });

      var opts = {
        chart: {
          type: 'bar',
          height: '100%',
          fontFamily: 'system-ui, -apple-system, sans-serif',
          toolbar: { show: false },
          animations: { enabled: true, easing: 'easeinout', speed: 800, animateGradually: { enabled: true, delay: 40 } },
          dropShadow: { enabled: false }
        },
        series: [
          { name: 'Gelir', type: 'bar', data: revenues },
          { name: 'Sipariş', type: 'line', data: counts }
        ],
        colors: [palette.revenue, palette.orders],
        plotOptions: {
          bar: { borderRadius: 6, columnWidth: '55%', dataLabels: { position: 'top' } }
        },
        fill: {
          type: ['gradient', 'solid'],
          gradient: { shade: 'light', type: 'vertical', shadeIntensity: 0.25, gradientToColors: [palette.revenueLight], stops: [0, 100] }
        },
        dataLabels: { enabled: false },
        stroke: { width: [0, 2.5], curve: 'smooth' },
        xaxis: {
          categories: hours,
          labels: { style: { fontSize: '11px', fontWeight: 600, colors: '#64748b' }, rotate: -35, rotateAlways: false },
          axisBorder: { show: false },
          axisTicks: { show: false }
        },
        yaxis: [
          {
            title: { text: 'Gelir (₺)', style: { fontSize: '12px', fontWeight: 700, color: '#64748b' } },
            labels: { formatter: fmtMoney, style: { fontSize: '11px', fontWeight: 600, colors: '#64748b' } }
          },
          {
            opposite: true,
            title: { text: 'Sipariş', style: { fontSize: '12px', fontWeight: 700, color: '#64748b' } },
            labels: { style: { fontSize: '11px', fontWeight: 600, colors: '#64748b' } }
          }
        ],
        grid: { borderColor: '#e2e8f0', strokeDashArray: 4, padding: { left: 10, right: 10, top: 4, bottom: 0 } },
        tooltip: {
          shared: true,
          intersect: false,
          y: { formatter: function (val, opts) { return opts.seriesIndex === 0 ? fmtMoney(val) : val + ' sipariş'; } },
          theme: 'light',
          style: { fontSize: '13px', fontFamily: 'system-ui' }
        },
        legend: { show: true, position: 'top', fontSize: '12px', fontWeight: 700, markers: { radius: 4 } }
      };

      try {
        var chart = new ApexCharts(div, opts);
        chart.render();
        instances['hourlySales'] = chart;
      } catch (e) {
        if (root.console && typeof root.console.warn === 'function') {
          root.console.warn('[QordyDashboardCharts] hourlySales render failed', e);
        }
        return false;
      }
      return true;
    },

    weeklyTrend: function (canvas, data) {
      if (!canvas || !Array.isArray(data) || !data.length) return false;

      var container = canvas.parentElement;
      destroyIfExists('weeklyTrend');
      canvas.style.display = 'none';

      var div = container.querySelector('.apexcharts-wrap') || document.createElement('div');
      div.className = 'apexcharts-wrap';
      div.style.width = '100%';
      div.style.height = '100%';
      if (!div.parentElement) container.appendChild(div);

      var labels = data.map(function (d) { return d.day_name || ''; });
      var revenues = data.map(function (d) { return d.revenue || 0; });
      var counts = data.map(function (d) { return d.orders_count || 0; });

      var opts = {
        chart: {
          type: 'area',
          height: '100%',
          fontFamily: 'system-ui, -apple-system, sans-serif',
          toolbar: { show: false },
          animations: { enabled: true, easing: 'easeinout', speed: 1000, animateGradually: { enabled: true, delay: 80 } },
          zoom: { enabled: false }
        },
        series: [
          { name: 'Gelir', data: revenues },
          { name: 'Sipariş', data: counts }
        ],
        colors: [palette.revenue, palette.orders],
        fill: {
          type: 'gradient',
          gradient: { shadeIntensity: 1, opacityFrom: 0.35, opacityTo: 0.05, stops: [0, 90, 100] }
        },
        stroke: { curve: 'smooth', width: [3, 2] },
        markers: { size: [5, 4], strokeWidth: 2, strokeColors: '#fff', hover: { size: 7 } },
        xaxis: {
          categories: labels,
          labels: { style: { fontSize: '12px', fontWeight: 600, colors: '#64748b' } },
          axisBorder: { show: false },
          axisTicks: { show: false }
        },
        yaxis: [
          {
            title: { text: 'Gelir (₺)', style: { fontSize: '12px', fontWeight: 700, color: '#64748b' } },
            labels: { formatter: fmtMoney, style: { fontSize: '11px', fontWeight: 600, colors: '#64748b' } }
          },
          {
            opposite: true,
            title: { text: 'Sipariş', style: { fontSize: '12px', fontWeight: 700, color: '#64748b' } },
            labels: { style: { fontSize: '11px', fontWeight: 600, colors: '#64748b' } }
          }
        ],
        grid: { borderColor: '#e2e8f0', strokeDashArray: 4, padding: { left: 10, right: 10, top: 4, bottom: 0 } },
        tooltip: {
          shared: true,
          intersect: false,
          y: { formatter: function (val, opts) { return opts.seriesIndex === 0 ? fmtMoney(val) : val + ' sipariş'; } },
          theme: 'light',
          style: { fontSize: '13px' }
        },
        legend: { show: true, position: 'top', fontSize: '12px', fontWeight: 700, markers: { radius: 12 } }
      };

      try {
        var chart = new ApexCharts(div, opts);
        chart.render();
        instances['weeklyTrend'] = chart;
      } catch (e) {
        if (root.console && typeof root.console.warn === 'function') {
          root.console.warn('[QordyDashboardCharts] weeklyTrend render failed', e);
        }
        return false;
      }
      return true;
    },

    categoryRevenue: function (canvas, data, options) {
      if (!canvas || !Array.isArray(data) || !data.length) return false;

      options = options || {};
      var metric = options.metric === 'quantity' ? 'quantity' : 'revenue';

      var container = canvas.parentElement;
      var isPanelDonut = container && container.classList.contains('q-panel-donut');
      destroyIfExists('categoryRevenue');
      canvas.style.display = 'none';

      var div = container.querySelector('.apexcharts-wrap') || document.createElement('div');
      div.className = 'apexcharts-wrap';
      div.style.width = isPanelDonut ? (PANEL_DONUT_PX + 'px') : '100%';
      div.style.height = isPanelDonut ? (PANEL_DONUT_PX + 'px') : '100%';
      if (!div.parentElement) container.appendChild(div);

      var donutPalette = palette.donut;
      var entries = data.filter(function (c) { return (Number(c[metric]) || 0) > 0; });
      if (!entries.length) return false;

      var total = entries.reduce(function (s, c) { return s + (Number(c[metric]) || 0); }, 0) || 1;
      var totalEl = document.getElementById('category-donut-total');
      if (isPanelDonut && totalEl) {
        if (metric === 'quantity') {
          var qtyText = total.toLocaleString('tr-TR') + ' adet';
          totalEl.textContent = qtyText;
          totalEl.setAttribute('aria-label', 'Toplam adet: ' + qtyText);
          totalEl.classList.remove('q-panel-donut__center--compact');
        } else {
          updateDonutCenterLabel(totalEl, total);
        }
      } else if (totalEl) {
        totalEl.textContent = metric === 'quantity'
          ? total.toLocaleString('tr-TR') + ' adet'
          : fmtMoneyFull(total);
      }

      var opts = {
        chart: {
          type: 'donut',
          height: isPanelDonut ? PANEL_DONUT_PX : '100%',
          width: isPanelDonut ? PANEL_DONUT_PX : undefined,
          fontFamily: 'system-ui, -apple-system, sans-serif',
          sparkline: { enabled: false },
          animations: { enabled: true, easing: 'easeinout', speed: 1200, animateGradually: { enabled: true, delay: 100 } }
        },
        series: entries.map(function (c) { return Number(c[metric]) || 0; }),
        labels: entries.map(function (c) { return c.category_name || 'Bilinmeyen'; }),
        colors: entries.map(function (_, i) { return donutPalette[i % donutPalette.length]; }),
        plotOptions: {
          pie: {
            donut: {
              size: isPanelDonut ? '84%' : '65%',
              labels: isPanelDonut ? PANEL_DONUT_LABELS_OFF : { show: false }
            }
          }
        },
        stroke: { width: 3, colors: ['#fff'] },
        dataLabels: { enabled: false },
        legend: { show: false },
        tooltip: {
          y: {
            formatter: function (val) {
              var pct = ((val / total) * 100).toFixed(1);
              if (metric === 'quantity') {
                return val.toLocaleString('tr-TR') + ' adet (' + pct + '%)';
              }
              return fmtMoney(val) + ' (' + pct + '%)';
            }
          }
        }
      };

      var chart = new ApexCharts(div, opts);
      chart.render();
      instances['categoryRevenue'] = chart;
      return true;
    },

    orderStatus: function (canvas, data) {
      if (!canvas || !data || typeof data !== 'object') return false;
      var statusOrder = ['SERVED', 'DELIVERED', 'CANCELLED', 'PREPARING', 'READY', 'PENDING', 'ON_DELIVERY', 'ISSUE', 'REFUNDED'];
      var entries = Object.keys(data).map(function (k) { return [k, data[k]]; }).filter(function (e) { return e[1] > 0; });
      if (!entries.length) return false;

      entries.sort(function (a, b) {
        var ia = statusOrder.indexOf(a[0]);
        var ib = statusOrder.indexOf(b[0]);
        if (ia === -1) ia = 99;
        if (ib === -1) ib = 99;
        if (ia !== ib) return ia - ib;
        return b[1] - a[1];
      });

      var container = canvas.parentElement;
      var isPanelDonut = container && container.classList.contains('q-panel-donut');
      destroyIfExists('orderStatus');
      canvas.style.display = 'none';

      var div = container.querySelector('.apexcharts-wrap') || document.createElement('div');
      div.className = 'apexcharts-wrap';
      div.style.width = isPanelDonut ? (PANEL_DONUT_PX + 'px') : '100%';
      div.style.height = isPanelDonut ? (PANEL_DONUT_PX + 'px') : '100%';
      if (!div.parentElement) container.appendChild(div);

      var labels = {
        PENDING: 'Beklemede',
        PREPARING: 'Hazırlanıyor',
        READY: 'Hazır',
        SERVED: 'Teslim Edildi',
        DELIVERED: 'Teslim Edildi',
        CANCELLED: 'İptal',
        ON_DELIVERY: 'Yolda',
        ISSUE: 'Sorunlu',
        REFUNDED: 'İade',
      };
      var statusColors = {
        PENDING: palette.pending,
        PREPARING: palette.preparing,
        READY: palette.ready,
        SERVED: palette.served,
        DELIVERED: palette.served,
        CANCELLED: palette.cancelled,
        ON_DELIVERY: '#3b82f6',
        ISSUE: '#f59e0b',
        REFUNDED: '#94a3b8',
      };

      var total = entries.reduce(function (a, e) { return a + e[1]; }, 0);
      var cancelledCount = 0;
      var servedCount = 0;
      entries.forEach(function (e) {
        if (e[0] === 'CANCELLED') cancelledCount = e[1];
        if (e[0] === 'SERVED' || e[0] === 'DELIVERED') servedCount += e[1];
      });

      var totalEl = document.getElementById('order-status-donut-total');
      if (isPanelDonut && totalEl) {
        totalEl.textContent = total.toLocaleString('tr-TR');
        totalEl.setAttribute('aria-label', 'Toplam sipariş: ' + total);
        totalEl.classList.remove('q-panel-donut__center--compact');
      }

      var opts = {
        chart: {
          type: 'donut',
          height: isPanelDonut ? PANEL_DONUT_PX : '100%',
          width: isPanelDonut ? PANEL_DONUT_PX : undefined,
          fontFamily: 'system-ui, -apple-system, sans-serif',
          animations: { enabled: true, easing: 'easeinout', speed: 1200, animateGradually: { enabled: true, delay: 100 } }
        },
        series: entries.map(function (e) { return e[1]; }),
        labels: entries.map(function (e) { return labels[e[0]] || e[0]; }),
        colors: entries.map(function (e) { return statusColors[e[0]] || '#94a3b8'; }),
        plotOptions: {
          pie: {
            donut: {
              size: isPanelDonut ? '84%' : '65%',
              labels: isPanelDonut ? PANEL_DONUT_LABELS_OFF : { show: false }
            }
          }
        },
        stroke: { width: 3, colors: ['#fff'] },
        dataLabels: { enabled: false },
        legend: { show: false },
        tooltip: {
          y: {
            formatter: function (val) {
              var pct = ((val / total) * 100).toFixed(1);
              return val.toLocaleString('tr-TR') + ' sipariş (' + pct + '%)';
            }
          }
        }
      };

      var chart = new ApexCharts(div, opts);
      chart.render();
      instances['orderStatus'] = chart;

      var legend = document.getElementById('order-status-legend');
      if (legend) {
        legend.innerHTML = entries.map(function (e) {
          var pct = ((e[1] / total) * 100).toFixed(1);
          var color = statusColors[e[0]] || '#94a3b8';
          return '<li class="q-panel-category-row">'
            + '<span class="q-panel-category-row__dot" style="background:' + color + '"></span>'
            + '<span class="q-panel-category-row__label">' + (labels[e[0]] || e[0]) + '</span>'
            + '<span class="q-panel-category-row__pct">' + e[1].toLocaleString('tr-TR') + ' · %' + pct + '</span>'
            + '</li>';
        }).join('');

        if (cancelledCount > 0 || servedCount > 0) {
          legend.innerHTML += '<li class="q-panel-order-status-summary">'
            + (servedCount > 0 ? '<span>Teslim: <strong>' + servedCount.toLocaleString('tr-TR') + '</strong></span>' : '')
            + (cancelledCount > 0 ? '<span>İptal: <strong>' + cancelledCount.toLocaleString('tr-TR') + '</strong></span>' : '')
            + '</li>';
        }
      }

      var wrap = canvas.closest('.q-panel-donut-wrap');
      if (wrap) {
        wrap.removeAttribute('data-chart-loading');
        var ph = wrap.querySelector('[data-chart-placeholder]');
        if (ph) ph.remove();
      }
      return true;
    },
  };

  root.QordyDashboardCharts = Charts;
  Charts.updateDonutCenterLabel = updateDonutCenterLabel;
})(window);
