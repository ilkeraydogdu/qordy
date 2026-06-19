/**
 * Qordy Dashboard List Renderers
 *
 * One render function per data target. Each takes the target element and
 * the payload, returns true on success, and inserts an empty-state node
 * when the payload is empty.
 */
(function (root) {
  'use strict';

  var escapeHtml = function (s) {
    if (s === null || s === undefined) return '';
    return String(s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  };

  var fmtMoney = function (n) {
    if (typeof window.formatCurrency === 'function') return window.formatCurrency(n);
    n = Number(n) || 0;
    return '₺' + n.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
  };

  var panelDataCache = {
    panel_top_selling: null,
    panel_category: null,
  };

  function getSortMode(panelId) {
    var btn = document.querySelector('[data-panel-sort="' + panelId + '"]');
    if (btn) return btn.getAttribute('data-sort-by') || 'quantity';
    return panelId === 'panel_category' ? 'revenue' : 'quantity';
  }

  function syncSortButton(panelId, mode) {
    var btn = document.querySelector('[data-panel-sort="' + panelId + '"]');
    if (!btn) return;
    btn.setAttribute('data-sort-by', mode);
    var label = btn.querySelector('.q-panel-sort-btn__label');
    if (label) label.textContent = mode === 'revenue' ? 'Ciro' : 'Adet';
    btn.title = mode === 'revenue'
      ? 'Ciro sıralaması — tıkla: adet'
      : 'Adet sıralaması — tıkla: ciro';
  }

  function sortTopSelling(items, mode) {
    return (items || []).slice().sort(function (a, b) {
      if (mode === 'revenue') return (Number(b.revenue) || 0) - (Number(a.revenue) || 0);
      return (Number(b.count) || 0) - (Number(a.count) || 0);
    });
  }

  function sortCategories(cats, mode) {
    return (cats || []).slice().sort(function (a, b) {
      if (mode === 'revenue') return (Number(b.revenue) || 0) - (Number(a.revenue) || 0);
      return (Number(b.quantity) || 0) - (Number(a.quantity) || 0);
    });
  }

  function refreshPanelSort(panelId) {
    if (!root.QordyDashboardLists) return;
    if (panelId === 'panel_top_selling') {
      var el = document.querySelector('[data-list-target="panel_top_selling"]');
      if (el && panelDataCache.panel_top_selling) {
        root.QordyDashboardLists.panel_top_selling(el, panelDataCache.panel_top_selling);
      }
      return;
    }
    if (panelId === 'panel_category') {
      var legendEl = document.querySelector('[data-list-target="panel_category_legend"]');
      if (legendEl && panelDataCache.panel_category) {
        root.QordyDashboardLists.panel_category_legend(legendEl, panelDataCache.panel_category);
      }
      var canvas = document.getElementById('orderStatusChart');
      if (canvas && root.QordyDashboardCharts && panelDataCache.panel_category) {
        var mode = getSortMode('panel_category');
        root.QordyDashboardCharts.categoryRevenue(
          canvas,
          sortCategories(panelDataCache.panel_category, mode),
          { metric: mode }
        );
      }
    }
  }

  function bindPanelSortToggles() {
    if (document.__qordyPanelSortBound) return;
    document.__qordyPanelSortBound = true;
    document.addEventListener('click', function (e) {
      var btn = e.target.closest('[data-panel-sort]');
      if (!btn) return;
      var panelId = btn.getAttribute('data-panel-sort');
      if (!panelId) return;
      var current = btn.getAttribute('data-sort-by') || getSortMode(panelId);
      var next = current === 'revenue' ? 'quantity' : 'revenue';
      syncSortButton(panelId, next);
      refreshPanelSort(panelId);
    });
  }

  var fmtDate = function (s) {
    if (!s) return '-';
    var t = typeof s === 'number' ? s : Date.parse(s);
    if (!t) return s;
    var d = new Date(t);
    var p = function (n) { return n < 10 ? '0' + n : '' + n; };
    return p(d.getDate()) + '.' + p(d.getMonth() + 1) + '.' + d.getFullYear() + ' ' + p(d.getHours()) + ':' + p(d.getMinutes());
  };

  var relativeTime = function (s) {
    if (!s) return '-';
    var t = typeof s === 'number' ? s : Date.parse(s);
    if (!t) return '-';
    var diff = Math.floor((Date.now() - t) / 1000);
    if (diff < 0) return fmtDate(s);
    if (diff < 60) return 'Az önce';
    if (diff < 3600) return Math.floor(diff / 60) + ' dk önce';
    if (diff < 86400) return Math.floor(diff / 3600) + ' saat önce';
    if (diff < 604800) return Math.floor(diff / 86400) + ' gün önce';
    return fmtDate(s);
  };

  var STATUS_COLORS = {
    pending: 'bg-amber-100 text-amber-800',
    preparing: 'bg-blue-100 text-blue-800',
    ready: 'bg-cyan-100 text-cyan-800',
    served: 'bg-emerald-100 text-emerald-800',
    completed: 'bg-emerald-100 text-emerald-800',
    cancelled: 'bg-rose-100 text-rose-800',
  };
  var STATUS_LABELS = {
    pending: 'Beklemede',
    preparing: 'Hazırlanıyor',
    ready: 'Hazır',
    served: 'Teslim Edildi',
    completed: 'Tamamlandı',
    cancelled: 'İptal Edildi',
  };

  function emptyHtml(message) {
    return '<div class="text-center py-6 text-slate-300 font-bold text-xs sm:text-sm">'
      + escapeHtml(message || 'Veri yok')
      + '</div>';
  }

  function rowShell(inner) {
    return '<div class="p-2 sm:p-3 rounded-lg bg-slate-50 border border-slate-100 animate-fade-in">'
      + '<div class="flex justify-between items-start gap-2">'
      + '<div class="flex-1 min-w-0">' + inner.left + '</div>'
      + '<div class="flex items-center gap-2 shrink-0">' + inner.right + '</div>'
      + '</div></div>';
  }

  // Source / table-status labels (Turkish)
  var SOURCE_LABELS = {
    GARSON: 'Garson / Masa',
    QR_MENU: 'QR Menü (Müşteri)',
    POS: 'Garson / Masa',
    WAITER: 'Garson / Masa',
    QR: 'QR Menü (Müşteri)',
    PHONE: 'Telefon Siparişi',
    ONLINE: 'Online',
    OTHER: 'Diğer',
    UNKNOWN: 'Bilinmiyor'
  };

  function normalizeSourceKey(key) {
    var k = String(key || '').toUpperCase();
    if (k === 'POS' || k === 'WAITER' || k === 'TABLET' || k === 'STAFF' || k === 'CASHIER') return 'GARSON';
    if (k === 'QR' || k === 'CUSTOMER') return 'QR_MENU';
    return k;
  }

  function mergeSourceEntries(sources) {
    var merged = {};
    Object.keys(sources).forEach(function (k) {
      var count = Number(sources[k]) || 0;
      if (count <= 0) return;
      var nk = normalizeSourceKey(k);
      merged[nk] = (merged[nk] || 0) + count;
    });
    return merged;
  }
  var TABLE_STATUS_LABELS = { FREE: 'Boş', OCCUPIED: 'Dolu', PAYMENT_PENDING: 'Ödeme Bekliyor', DIRTY: 'Kirli', RESERVED: 'Rezerve' };
  var TABLE_STATUS_COLORS = { FREE: 'bg-green-500', OCCUPIED: 'bg-orange-500', PAYMENT_PENDING: 'bg-yellow-500', DIRTY: 'bg-red-500', RESERVED: 'bg-blue-500' };

  function updateStaffPerformanceCount(count) {
    var card = document.querySelector('[data-widget="panel_staff"]');
    var badge = card && card.querySelector('[data-staff-performance-count]');
    if (!badge) return;
    if (count > 0) {
      badge.textContent = count + ' personel';
      badge.hidden = false;
    } else {
      badge.textContent = '';
      badge.hidden = true;
    }
  }

  function setupStaffPerformanceScroll(listEl) {
    var wrap = listEl && listEl.closest('.q-staff-performance');
    if (!wrap || !listEl) return;

    var update = function () {
      var canScroll = listEl.scrollHeight > listEl.clientHeight + 2;
      var atBottom = listEl.scrollTop + listEl.clientHeight >= listEl.scrollHeight - 6;
      wrap.classList.toggle('is-scrollable', canScroll);
      wrap.classList.toggle('is-at-bottom', !canScroll || atBottom);
    };

    if (listEl._staffScrollHandler) {
      listEl.removeEventListener('scroll', listEl._staffScrollHandler);
    }
    listEl._staffScrollHandler = update;
    listEl.addEventListener('scroll', update, { passive: true });

    if (listEl._staffResizeObserver) {
      listEl._staffResizeObserver.disconnect();
      listEl._staffResizeObserver = null;
    }
    if (typeof ResizeObserver !== 'undefined') {
      listEl._staffResizeObserver = new ResizeObserver(update);
      listEl._staffResizeObserver.observe(listEl);
    }

    requestAnimationFrame(update);
  }

  var Lists = {

    // ---------------------------------------------------------------------
    // v1 — basic lists
    // ---------------------------------------------------------------------

    recent_orders: function (el, orders) {
      if (!el) return false;
      if (!Array.isArray(orders) || !orders.length) {
        el.innerHTML = emptyHtml('Henüz sipariş yok');
        return false;
      }
      el.innerHTML = orders.slice(0, 8).map(function (o) {
        var status = (o.status || 'pending').toLowerCase();
        var cls = STATUS_COLORS[status] || 'bg-slate-100 text-slate-800';
        var label = STATUS_LABELS[status] || o.status || 'Bilinmiyor';
        return rowShell({
          left: '<div class="font-black text-slate-900 text-xs sm:text-sm truncate">'
            + escapeHtml((function (o) {
 if (o.customer_name) return o.customer_name;
 if (o.staff_or_creator) return o.staff_or_creator;
 if (o.staff_name) return o.staff_name;
 if (o.first_item_name) {
 var n = parseInt(o.item_count, 10) || 1;
 return n > 1 ? (o.first_item_name + ' +' + (n - 1)) : o.first_item_name;
 }
 if (o.table_name && o.table_name !== 'TEST') return o.table_name;
 return 'Misafir';
 })(o) || ('Masa ' + (o.table_id || '')))
            + '</div>'
            + '<div class="text-[10px] sm:text-xs text-slate-500">#'
            + escapeHtml((o.order_id || '').toString().slice(0, 8))
            + ' · ' + fmtDate(o.created_at || o.timestamp)
            + '</div>',
          right: '<span class="px-2 py-0.5 rounded text-[10px] sm:text-xs font-bold ' + cls + '">'
            + escapeHtml(label)
            + '</span>'
            + '<span class="font-black text-xs sm:text-sm text-slate-900">'
            + fmtMoney(o.total_amount || 0)
            + '</span>',
        });
      }).join('');
      return true;
    },

    panel_live_orders: function (el, orders) {
      if (!el) return false;
      if (!Array.isArray(orders) || !orders.length) {
        el.innerHTML = '<li class="q-panel-empty">Henüz sipariş yok</li>';
        return false;
      }

      function panelState(status) {
        var st = (status || 'pending').toLowerCase();
        if (st === 'pending') return { dot: 'new', pill: 'new', label: 'Yeni' };
        if (st === 'preparing') return { dot: 'prep', pill: 'prep', label: 'Hazırlanıyor' };
        if (st === 'ready') return { dot: 'ready', pill: 'ready', label: 'Hazır' };
        return { dot: 'other', pill: 'other', label: STATUS_LABELS[st] || st };
      }

      el.innerHTML = orders.slice(0, 8).map(function (o) {
        var state = panelState(o.status);
        var orderId = (o.order_id || o.id || '').toString();
        var shortId = orderId ? ('#' + orderId.slice(-5)) : '#—';
        var branch = o.table_name || o.zone_name || o.customer_name || o.staff_name || ('Masa ' + (o.table_id || ''));
        return '<li class="q-panel-order-row">'
          + '<span class="q-panel-order-dot q-panel-order-dot--' + state.dot + '"></span>'
          + '<span class="q-panel-order-id">' + escapeHtml(shortId) + '</span>'
          + '<span class="q-panel-order-branch">' + escapeHtml(branch) + '</span>'
          + '<span class="q-panel-order-amount">' + fmtMoney(o.total_amount || 0) + '</span>'
          + '<span class="q-panel-order-pill q-panel-order-pill--' + state.pill + '">' + escapeHtml(state.label) + '</span>'
          + '<span class="q-panel-order-time">' + escapeHtml(relativeTime(o.created_at || o.timestamp)) + '</span>'
          + '</li>';
      }).join('');
      return true;
    },

    panel_top_selling: function (el, items) {
      if (!el) return false;
      if (!Array.isArray(items) || !items.length) {
        el.innerHTML = '<li class="q-panel-empty">Henüz satış yok</li>';
        return false;
      }
      panelDataCache.panel_top_selling = items;
      var mode = getSortMode('panel_top_selling');
      var sorted = sortTopSelling(items, mode);
      var colors = [
        'linear-gradient(135deg, #fcd34d 0%, #fb923c 100%)',
        'linear-gradient(135deg, #fca5a5 0%, #fbbf24 100%)',
        'linear-gradient(135deg, #6ee7b7 0%, #10b981 100%)',
        'linear-gradient(135deg, #fde68a 0%, #f59e0b 100%)',
      ];
      el.classList.add('q-panel-product-list--scroll');
      el.innerHTML = sorted.map(function (it, i) {
        var count = Number(it.count || it.total_quantity || 0);
        var revenue = Number(it.revenue) || 0;
        var imgUrl = it.image_url || '';
        var thumb = imgUrl
          ? '<img src="' + escapeHtml(imgUrl) + '" alt="" loading="lazy" onerror="this.parentElement.innerHTML=\'<svg fill=\\\'none\\\' stroke=\\\'currentColor\\\' viewBox=\\\'0 0 24 24\\\'><path stroke-linecap=\\\'round\\\' stroke-linejoin=\\\'round\\\' stroke-width=\\\'2\\\' d=\\\'M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z\\\'/></svg>\'"/>'
          : '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>';
        return '<li class="q-panel-product-row">'
          + '<span class="q-panel-product-rank">' + (i + 1) + '</span>'
          + '<span class="q-panel-product-thumb" style="background:' + colors[i % colors.length] + '">' + thumb + '</span>'
          + '<span class="q-panel-product-info">'
          + '<span class="q-panel-product-name">' + escapeHtml(it.name || 'Ürün') + '</span>'
          + '<span class="q-panel-product-count">' + count + ' adet · ' + fmtMoney(revenue) + '</span>'
          + '</span>'
          + '<span class="q-panel-product-revenue">' + (mode === 'revenue' ? fmtMoney(revenue) : (count + ' adet')) + '</span>'
          + '</li>';
      }).join('');
      return true;
    },

    panel_zones: function (el, zones) {
      if (!el) return false;
      if (!zones || typeof zones !== 'object' || !Object.keys(zones).length) {
        el.innerHTML = '<div class="q-panel-empty">Bölge verisi yok</div>';
        return false;
      }
      var rows = '';
      Object.keys(zones).forEach(function (name) {
        var data = zones[name] || {};
        var total = Math.max(0, parseInt(data.total, 10) || 0);
        var occupied = Math.max(0, parseInt(data.occupied, 10) || 0);
        var orderCount = Math.max(0, parseInt(data.order_count, 10) || 0);
        var pct = typeof data.percent === 'number'
          ? data.percent
          : (total > 0 ? Math.round((occupied / total) * 100) : 0);
        var tier = pct >= 70 ? 'high' : (pct >= 35 ? 'mid' : 'low');
        var meta = occupied + '/' + total + ' masa · %' + pct;
        if (orderCount > 0) {
          meta = orderCount + ' sipariş · ' + meta;
        }
        rows += '<div class="q-panel-zone-row" title="' + escapeHtml(name + ': ' + meta) + '">'
          + '<span class="q-panel-map__dot q-panel-map__dot--' + tier + '"></span>'
          + '<span class="q-panel-zone-row__name">' + escapeHtml(name) + '</span>'
          + '<span class="q-panel-zone-row__meta">' + escapeHtml(meta) + '</span>'
          + '<span class="q-panel-zone-row__bar"><span style="width:' + pct + '%"></span></span>'
          + '</div>';
      });
      el.innerHTML = '<div class="q-panel-zones">' + rows + '</div>';
      return true;
    },

    panel_category_legend: function (el, cats) {
      if (!el) return false;
      if (!Array.isArray(cats) || !cats.length) {
        el.innerHTML = '<li class="q-panel-empty">Henüz kategori verisi yok</li>';
        return false;
      }
      panelDataCache.panel_category = cats;
      var mode = getSortMode('panel_category');
      var sorted = sortCategories(cats, mode);
      var palette = ['#6366F1', '#F59E0B', '#EC4899', '#10B981', '#3B82F6', '#94A3B8', '#8B5CF6', '#F97316'];
      var total = sorted.reduce(function (s, c) {
        return s + (mode === 'quantity' ? (Number(c.quantity) || 0) : (Number(c.revenue) || 0));
      }, 0) || 1;
      var totalEl = document.getElementById('category-donut-total');
      if (totalEl) {
        if (mode === 'quantity') {
          var qtyText = total.toLocaleString('tr-TR') + ' adet';
          totalEl.textContent = qtyText;
          totalEl.setAttribute('aria-label', 'Toplam adet: ' + qtyText);
        } else if (root.QordyDashboardCharts && typeof root.QordyDashboardCharts.updateDonutCenterLabel === 'function') {
          root.QordyDashboardCharts.updateDonutCenterLabel(totalEl, total);
        } else {
          totalEl.textContent = fmtMoney(total);
        }
      }
      el.classList.add('q-panel-category-list--scroll');
      el.innerHTML = sorted.map(function (c, i) {
        var rev = Number(c.revenue) || 0;
        var qty = Number(c.quantity) || 0;
        var value = mode === 'quantity' ? qty : rev;
        var pct = Math.round((value / total) * 100);
        var meta = mode === 'quantity'
          ? (qty.toLocaleString('tr-TR') + ' adet · %' + pct)
          : (fmtMoney(rev) + ' · %' + pct);
        return '<li class="q-panel-category-row">'
          + '<span class="q-panel-category-row__dot" style="background:' + palette[i % palette.length] + '"></span>'
          + '<span class="q-panel-category-row__label">' + escapeHtml(c.category_name || 'Bilinmeyen') + '</span>'
          + '<span class="q-panel-category-row__pct">' + escapeHtml(meta) + '</span>'
          + '</li>';
      }).join('');
      return true;
    },

    top_selling_items: function (el, items) {
      if (!el) return false;
      if (!Array.isArray(items) || !items.length) {
        el.innerHTML = emptyHtml('Henüz satış yok');
        return false;
      }
      var medals = ['🥇', '🥈', '🥉'];
      var maxCount = Math.max.apply(null, items.map(function(it) { return it.count || it.total_quantity || 0; })) || 1;
      el.innerHTML = items.map(function (it, i) {
        var count = it.count || it.total_quantity || 0;
        var revenue = Number(it.revenue) || 0;
        var pct = Math.round((count / maxCount) * 100);
        var imgUrl = it.image_url || '';
        var rankBadge = i < 3
          ? '<span class="text-lg leading-none">' + medals[i] + '</span>'
          : '<span class="w-7 h-7 rounded-lg bg-slate-100 text-slate-600 flex items-center justify-center text-xs font-black">' + (i + 1) + '</span>';
        var imgHtml = imgUrl
          ? '<img src="' + escapeHtml(imgUrl) + '" alt="' + escapeHtml(it.name || '') + '" class="w-10 h-10 sm:w-12 sm:h-12 rounded-xl object-cover border border-slate-200 shadow-sm" onerror="this.style.display=\'none\'"/>'
          : '<div class="w-10 h-10 sm:w-12 sm:h-12 rounded-xl bg-gradient-to-br from-orange-100 to-amber-50 flex items-center justify-center border border-orange-200"><svg class="w-5 h-5 text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg></div>';
        return '<div class="p-2.5 sm:p-3 rounded-xl bg-slate-50/80 border border-slate-100 animate-fade-in hover:bg-white hover:shadow-sm transition-all">'
          + '<div class="flex items-center gap-2.5 sm:gap-3">'
          + '<div class="shrink-0 flex items-center justify-center w-7 h-7">' + rankBadge + '</div>'
          + imgHtml
          + '<div class="flex-1 min-w-0">'
          + '<div class="font-bold text-xs sm:text-sm text-slate-900 truncate">' + escapeHtml(it.name || 'Ürün') + '</div>'
          + '<div class="flex items-center gap-2 mt-0.5">'
          + '<span class="text-[10px] sm:text-xs text-slate-500 font-bold">' + count + ' adet</span>'
          + '<span class="text-[10px] text-slate-300">·</span>'
          + '<span class="text-[10px] sm:text-xs text-orange-600 font-bold">' + fmtMoney(revenue) + '</span>'
          + '</div>'
          + '<div class="mt-1.5 h-1 bg-slate-100 rounded-full overflow-hidden">'
          + '<div class="h-full rounded-full transition-all duration-1000" style="width:' + pct + '%;background:linear-gradient(90deg,#f97316,#ea580c)"></div>'
          + '</div>'
          + '</div></div></div>';
      }).join('');
      return true;
    },

    category_revenue: function (el, cats) {
      if (!el) return false;
      if (!Array.isArray(cats) || !cats.length) {
        el.innerHTML = emptyHtml('Henüz kategori satışı yok');
        return false;
      }
      var total = cats.reduce(function (s, c) { return s + (Number(c.revenue) || 0); }, 0) || 1;
      el.innerHTML = cats.map(function (c) {
        var rev = Number(c.revenue) || 0;
        var pct = (rev / total) * 100;
        return '<div class="animate-fade-in">'
          + '<div class="flex justify-between mb-1 text-[10px] sm:text-xs font-bold text-slate-700">'
          + '<span class="truncate">' + escapeHtml(c.category_name || 'Bilinmeyen') + '</span>'
          + '<span class="tabular-nums shrink-0 ml-2">' + fmtMoney(rev) + '</span>'
          + '</div>'
          + '<div class="h-2 sm:h-2.5 bg-slate-100 rounded-full overflow-hidden">'
          + '<div class="h-full bg-orange-500 rounded-full transition-all duration-1000" style="width:' + pct.toFixed(1) + '%"></div>'
          + '</div></div>';
      }).join('');
      return true;
    },

    most_active_tables: function (el, tables) {
      if (!el) return false;
      if (!Array.isArray(tables) || !tables.length) {
        el.innerHTML = emptyHtml('Henüz aktivite yok');
        return false;
      }
      el.innerHTML = tables.map(function (t, i) {
        return '<div class="p-2 sm:p-3 rounded-lg bg-slate-50 border border-slate-100 animate-fade-in flex items-center justify-between gap-2">'
          + '<div class="flex items-center gap-2 sm:gap-3 flex-1 min-w-0">'
          + '<span class="w-6 h-6 rounded-full bg-orange-100 text-orange-600 flex items-center justify-center text-xs font-black shrink-0">' + (i + 1) + '</span>'
          + '<div class="min-w-0 flex-1">'
          + '<div class="font-bold text-xs sm:text-sm text-slate-900 truncate">' + escapeHtml(t.table_name || 'Masa') + '</div>'
          + '<div class="text-[10px] text-slate-500">' + (t.order_count || 0) + ' sipariş</div>'
          + '</div></div>'
          + '<span class="font-black text-xs sm:text-sm text-slate-600 shrink-0">' + fmtMoney(t.total_revenue || 0) + '</span>'
          + '</div>';
      }).join('');
      return true;
    },

    order_sources: function (el, sources) {
      if (!el || !sources || typeof sources !== 'object') return false;
      var merged = mergeSourceEntries(sources);
      var entries = Object.keys(merged).map(function (k) { return [k, merged[k]]; }).filter(function (e) { return e[1] > 0; });
      if (!entries.length) { el.innerHTML = emptyHtml('Veri yok'); return false; }
      var total = entries.reduce(function (s, e) { return s + e[1]; }, 0);
      el.innerHTML = entries.sort(function (a, b) { return b[1] - a[1]; }).map(function (e) {
        var pct = (e[1] / total) * 100;
        return '<div class="animate-fade-in">'
          + '<div class="flex justify-between mb-1 text-[10px] sm:text-xs font-bold text-slate-700">'
          + '<span class="truncate">' + escapeHtml(SOURCE_LABELS[e[0]] || e[0]) + '</span>'
          + '<span class="tabular-nums shrink-0 ml-2">' + e[1] + ' (' + pct.toFixed(1) + '%)</span>'
          + '</div>'
          + '<div class="h-2 sm:h-2.5 bg-slate-100 rounded-full overflow-hidden">'
          + '<div class="h-full bg-blue-500 rounded-full transition-all duration-1000" style="width:' + pct.toFixed(1) + '%"></div>'
          + '</div></div>';
      }).join('');
      return true;
    },

    table_status: function (el, statuses) {
      if (!el || !statuses || typeof statuses !== 'object') return false;
      var entries = Object.keys(statuses).map(function (k) { return [k, statuses[k]]; }).filter(function (e) { return e[1] > 0; });
      if (!entries.length) { el.innerHTML = emptyHtml('Veri yok'); return false; }
      var total = entries.reduce(function (s, e) { return s + e[1]; }, 0);
      el.innerHTML = entries.sort(function (a, b) { return b[1] - a[1]; }).map(function (e) {
        var pct = (e[1] / total) * 100;
        return '<div class="animate-fade-in">'
          + '<div class="flex justify-between mb-1 text-[10px] sm:text-xs font-bold text-slate-700">'
          + '<span class="truncate flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded ' + (TABLE_STATUS_COLORS[e[0]] || 'bg-slate-500') + '"></span>' + escapeHtml(TABLE_STATUS_LABELS[e[0]] || e[0]) + '</span>'
          + '<span class="tabular-nums shrink-0 ml-2">' + e[1] + ' (' + pct.toFixed(1) + '%)</span>'
          + '</div>'
          + '<div class="h-2 sm:h-2.5 bg-slate-100 rounded-full overflow-hidden">'
          + '<div class="h-full ' + (TABLE_STATUS_COLORS[e[0]] || 'bg-slate-500') + ' rounded-full transition-all duration-1000" style="width:' + pct.toFixed(1) + '%"></div>'
          + '</div></div>';
      }).join('');
      return true;
    },

    notifications: function (el, items) {
      if (!el) return false;
      if (!Array.isArray(items) || !items.length) {
        el.innerHTML = '<p class="text-center py-6 sm:py-8 text-slate-300 font-bold text-xs sm:text-sm">Henüz bildirim yok</p>';
        return false;
      }
      el.innerHTML = items.slice(0, 8).map(function (n) {
        var isRead = !!n.is_read;
        var data = {};
        try { data = typeof n.data === 'string' ? JSON.parse(n.data || '{}') : (n.data || {}); } catch (e) { data = {}; }
        var message = data.message || n.message || n.title || ('#' + (n.id || ''));
        return '<div class="p-2 sm:p-3 rounded-lg flex justify-between items-start gap-2 transition-all ' + (isRead ? 'opacity-50' : 'bg-orange-50 border border-orange-100 shadow-sm') + '">'
          + '<div class="flex gap-2 items-start min-w-0 flex-1">'
          + '<div class="w-7 h-7 sm:w-8 sm:h-8 rounded-lg ' + (isRead ? 'bg-slate-200' : 'bg-orange-200') + ' flex items-center justify-center shrink-0">'
          + '<svg class="w-3.5 h-3.5 sm:w-4 sm:h-4 ' + (isRead ? 'text-slate-500' : 'text-orange-600') + '" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5m-3-4v3m-4.333-3.667V17"/></svg>'
          + '</div>'
          + '<div class="min-w-0 flex-1">'
          + '<div class="font-black text-slate-900 text-xs sm:text-sm truncate">' + escapeHtml(n.table_name || n.title || 'Bildirim') + '</div>'
          + '<div class="text-[10px] sm:text-xs text-slate-500 truncate">' + escapeHtml(message) + '</div>'
          + '</div></div>'
          + '<div class="text-[10px] sm:text-xs text-slate-400 shrink-0 font-bold">' + relativeTime(n.created_at) + '</div>'
          + '</div>';
      }).join('');
      return true;
    },

    // ---------------------------------------------------------------------
    // v2.2 — owner-grade analytics
    // ---------------------------------------------------------------------

    staff_performance: function (el, data) {
      if (!el) return false;
      if (!data || !data.waiters || data.waiters.length === 0) {
        el.innerHTML = '<div class="text-center py-6 text-xs text-slate-500 font-bold">Henüz garson performans verisi yok</div>';
        updateStaffPerformanceCount(0);
        setupStaffPerformanceScroll(el);
        return false;
      }
      var maxRevenue = Math.max.apply(null, data.waiters.map(function (w) { return w.total_revenue || 0; })) || 1;
      var medals = ['🥇', '🥈', '🥉'];
      var colors = ['from-amber-400 to-orange-500', 'from-slate-400 to-slate-500', 'from-amber-300 to-amber-400'];
      var html = data.waiters.map(function (w, idx) {
        var pct = Math.round(((w.total_revenue || 0) / maxRevenue) * 100);
        var rank = idx < 3 ? '<span class="text-base leading-none">' + medals[idx] + '</span>' : '<span class="text-xs font-black text-slate-500">' + (idx + 1) + '</span>';
        var name = escapeHtml(w.waiter_name || 'Bilinmiyor');
        var roleLabel = escapeHtml(w.role_label || '');
        var avgOrder = w.avg_order_value ? fmtMoney(w.avg_order_value) : '-';
        var tablesServed = w.unique_tables_served || 0;
        var barColor = idx < 3 ? 'bg-gradient-to-r ' + colors[idx] : 'bg-slate-400';
        var roleHtml = roleLabel
          ? '<div class="text-[10px] font-bold text-indigo-600 truncate">' + roleLabel + '</div>'
          : '';
        return '<div class="q-staff-performance__item p-2.5 sm:p-3 rounded-xl bg-slate-50/80 border border-slate-100 animate-fade-in hover:bg-white hover:shadow-sm transition-all">'
          + '<div class="flex items-center gap-2.5 sm:gap-3">'
          + '<div class="w-8 h-8 sm:w-9 sm:h-9 rounded-xl ' + (idx === 0 ? 'bg-amber-100' : 'bg-slate-100') + ' flex items-center justify-center shrink-0">' + rank + '</div>'
          + '<div class="min-w-0 flex-1">'
          + '<div class="flex items-center justify-between gap-2 mb-0.5">'
          + '<div class="min-w-0"><div class="font-black text-slate-900 text-xs sm:text-sm truncate">' + name + '</div>' + roleHtml + '</div>'
          + '<div class="text-xs font-black text-orange-600 shrink-0">' + fmtMoney(w.total_revenue) + '</div>'
          + '</div>'
          + '<div class="flex items-center gap-2 text-[10px] text-slate-500 font-bold mb-1.5 flex-wrap">'
          + '<span>' + (w.order_count || 0) + ' sipariş</span>'
          + '<span class="text-slate-300">·</span>'
          + '<span>Ort: ' + avgOrder + '</span>'
          + (tablesServed > 0 ? '<span class="text-slate-300">·</span><span>' + tablesServed + ' masa</span>' : '')
          + '</div>'
          + '<div class="h-1.5 bg-slate-100 rounded-full overflow-hidden">'
          + '<div class="h-full ' + barColor + ' rounded-full transition-all duration-1000" style="width:' + pct + '%"></div>'
          + '</div>'
          + '</div></div></div>';
      }).join('');
      if (data.other_count > 0) {
        html += '<div class="q-staff-performance__item pt-2 mt-2 border-t border-slate-100 text-[10px] text-slate-500 flex items-center justify-between">'
          + '<span>+ ' + data.other_count + ' sipariş diğer personelden</span>'
          + '<span class="font-bold">' + fmtMoney(data.other_revenue) + '</span>'
          + '</div>';
      }
      el.innerHTML = html;
      updateStaffPerformanceCount(data.waiters.length);
      setupStaffPerformanceScroll(el);
      return true;
    },

    payment_distribution: function (el, data) {
      if (!el) return false;
      if (!data || typeof data !== 'object' || Object.keys(data).length === 0) {
        el.innerHTML = '<div class="text-center py-6 text-xs text-slate-500 font-bold">Henüz ödeme verisi yok</div>';
        return false;
      }
      var total = 0;
      var entries = [];
      for (var k in data) {
        if (!Object.prototype.hasOwnProperty.call(data, k)) continue;
        var d = data[k];
        var sum = parseFloat(d.total || 0);
        total += sum;
        entries.push({ key: k, count: d.count || 0, total: sum, label: d.label || k });
      }
      if (total === 0) {
        el.innerHTML = '<div class="text-center py-6 text-xs text-slate-500 font-bold">Henüz ödeme verisi yok</div>';
        return false;
      }
      entries.sort(function (a, b) { return b.total - a.total; });
      var palette = { CASH: 'bg-emerald-500', CARD: 'bg-indigo-500' };
      var html = entries.map(function (e, idx) {
        var pct = ((e.total / total) * 100).toFixed(1);
        var color = palette[e.key] || ['bg-orange-500', 'bg-blue-500', 'bg-amber-500'][idx % 3];
        return '<div class="flex items-center gap-2 sm:gap-3 py-1.5 sm:py-2">'
          + '<div class="w-2.5 h-2.5 sm:w-3 sm:h-3 rounded ' + color + ' shrink-0"></div>'
          + '<div class="min-w-0 flex-1">'
          + '<div class="flex items-center justify-between gap-2 mb-0.5">'
          + '<div class="font-black text-slate-900 text-xs sm:text-sm">' + escapeHtml(e.label) + '</div>'
          + '<div class="text-xs font-black text-slate-900">' + fmtMoney(e.total) + '</div>'
          + '</div>'
          + '<div class="flex items-center gap-2">'
          + '<div class="flex-1 h-1.5 bg-slate-100 rounded-full overflow-hidden">'
          + '<div class="h-full ' + color + ' rounded-full" style="width:' + pct + '%"></div>'
          + '</div>'
          + '<div class="text-[10px] text-slate-500 font-bold shrink-0">%' + pct + '</div>'
          + '</div></div></div>';
      }).join('');
      html += '<div class="pt-2 mt-2 border-t border-slate-100 flex items-center justify-between text-[10px]">'
        + '<span class="text-slate-500 font-bold">Toplam</span>'
        + '<span class="font-black text-slate-900">' + fmtMoney(total) + '</span>'
        + '</div>';
      el.innerHTML = html;
      return true;
    },

    period_comparison: function (el, data) {
      if (!el) return false;
      if (!data || typeof data !== 'object') {
        el.innerHTML = '<div class="q-panel-empty">Karşılaştırma verisi yok</div>';
        return false;
      }
      var rows = [
        { key: 'revenue', label: 'Ciro', money: true },
        { key: 'orders', label: 'Sipariş', money: false },
        { key: 'avg_basket', label: 'Ort. Sepet', money: true },
        { key: 'profit', label: 'Net Kâr', money: true },
        { key: 'customers', label: 'Masa Müşterisi', money: false },
        { key: 'cancellation_rate', label: 'İptal Oranı', money: false, suffix: '%', diff: true },
      ];
      var html = rows.map(function (row) {
        var item = data[row.key];
        if (!item) return '';
        var cur = item.current;
        var prev = item.previous;
        var change = item.change_pct;
        var curText = row.money ? fmtMoney(cur) : String(cur) + (row.suffix || '');
        var prevText = row.money ? fmtMoney(prev) : String(prev) + (row.suffix || '');
        var changeNum = Number(change);
        var changeClass = changeNum > 0 ? 'q-panel-compare__delta--up' : (changeNum < 0 ? 'q-panel-compare__delta--down' : 'q-panel-compare__delta--flat');
        var changePrefix = row.diff ? (changeNum >= 0 ? '+' : '') : (changeNum >= 0 ? '+' : '');
        var changeText = changePrefix + change + (row.suffix && !row.diff ? row.suffix : (row.diff ? ' puan' : '%'));
        return '<div class="q-panel-compare-row">'
          + '<div class="q-panel-compare-row__label">' + escapeHtml(row.label) + '</div>'
          + '<div class="q-panel-compare-row__values">'
          + '<span class="q-panel-compare-row__current">' + curText + '</span>'
          + '<span class="q-panel-compare-row__prev">' + prevText + '</span>'
          + '</div>'
          + '<div class="q-panel-compare-row__delta ' + changeClass + '">' + changeText + '</div>'
          + '</div>';
      }).join('');
      if (!html) {
        el.innerHTML = '<div class="q-panel-empty">Karşılaştırma verisi yok</div>';
        return false;
      }
      el.innerHTML = '<div class="q-panel-compare-grid">' + html + '</div>';
      return true;
    },

    auto_insights: function (el, items) {
      if (!el) return false;
      if (!Array.isArray(items) || !items.length) {
        el.innerHTML = '<div class="q-panel-empty">Öngörü üretilemedi</div>';
        return false;
      }
      var toneClass = { success: 'q-panel-insight--success', warning: 'q-panel-insight--warning', info: 'q-panel-insight--info', neutral: 'q-panel-insight--neutral' };
      el.innerHTML = items.map(function (item) {
        var tone = toneClass[item.tone] || toneClass.info;
        return '<article class="q-panel-insight ' + tone + '">'
          + '<div class="q-panel-insight__title">' + escapeHtml(item.title || 'Öngörü') + '</div>'
          + '<p class="q-panel-insight__text">' + escapeHtml(item.text || '') + '</p>'
          + '</article>';
      }).join('');
      return true;
    },

    heatmap: function (el, data) {
      if (!el) return false;
      if (!data || !data.days || data.days.length === 0) {
        el.innerHTML = '<div class="q-panel-empty">Henüz yoğunluk verisi yok — veriler biriktikçe görünecek</div>';
        return false;
      }

      var SLOT_SIZE = 3;
      var SLOT_COUNT = 8;

      function fmtMoneyShort(n) {
        n = Number(n) || 0;
        if (n >= 1000000) return '₺' + (n / 1000000).toFixed(1).replace('.', ',') + 'M';
        if (n >= 1000) return '₺' + Math.round(n / 1000) + 'K';
        return fmtMoney(n);
      }

      function aggregateSlots(cells) {
        var buckets = [];
        for (var s = 0; s < SLOT_COUNT; s++) {
          buckets.push({ count: 0, revenue: 0, customers: 0, startHour: s * SLOT_SIZE });
        }
        if (!cells) return buckets;
        for (var h = 0; h < 24; h++) {
          var cell = cells[h] || { count: 0, revenue: 0, customers: 0 };
          var slot = Math.floor(h / SLOT_SIZE);
          buckets[slot].count += cell.count || 0;
          buckets[slot].revenue += cell.revenue || 0;
          buckets[slot].customers += cell.customers || 0;
        }
        return buckets;
      }

      function daySlots(day) {
        if (Array.isArray(day.slots) && day.slots.length) {
          return day.slots.map(function (slot) {
            return {
              count: slot.count || 0,
              revenue: slot.revenue || 0,
              customers: slot.customers || 0,
              startHour: slot.start_hour || 0,
            };
          });
        }
        return aggregateSlots(day.cells);
      }

      var maxCount = 1;
      data.days.forEach(function (d) {
        daySlots(d).forEach(function (c) {
          if (c.count > maxCount) maxCount = c.count;
        });
      });

      function cellLevel(intensity, count) {
        if (count <= 0) return 'q-panel-heatmap__cell--empty';
        if (intensity >= 0.8) return 'q-panel-heatmap__cell--l5';
        if (intensity >= 0.6) return 'q-panel-heatmap__cell--l4';
        if (intensity >= 0.4) return 'q-panel-heatmap__cell--l3';
        if (intensity >= 0.2) return 'q-panel-heatmap__cell--l2';
        return 'q-panel-heatmap__cell--l1';
      }

      function slotLabel(startHour, compact) {
        var end = startHour + SLOT_SIZE;
        var pad = function (n) { return (n < 10 ? '0' : '') + n; };
        if (compact) return pad(startHour) + '–' + pad(end);
        return pad(startHour) + ':00–' + pad(end) + ':00';
      }

      function renderCell(cell) {
        var intensity = cell.count / maxCount;
        var level = cellLevel(intensity, cell.count);
        if (cell.count <= 0) {
          return '<div class="q-panel-heatmap__cell ' + level + '" aria-label="' + slotLabel(cell.startHour, true) + ' boş">'
            + '<span class="q-panel-heatmap__cell-dash">—</span>'
            + '</div>';
        }
        var tip = slotLabel(cell.startHour, false)
          + ' · ' + cell.count + ' sipariş'
          + ' · ' + cell.customers + ' masa'
          + ' · ' + fmtMoney(cell.revenue);
        return '<div class="q-panel-heatmap__cell ' + level + '" title="' + escapeHtml(tip) + '">'
          + '<span class="q-panel-heatmap__cell-count">' + cell.count + '</span>'
          + '<span class="q-panel-heatmap__cell-meta">' + cell.customers + ' masa</span>'
          + '<span class="q-panel-heatmap__cell-rev">' + fmtMoneyShort(cell.revenue) + '</span>'
          + '</div>';
      }

      var html = '<div class="q-panel-heatmap">';
      html += '<p class="q-panel-heatmap__hint">Yeşil = sakin, sarı = orta, kırmızı = yoğun · Masa = benzersiz masa sayısı</p>';
      html += '<div class="q-panel-heatmap__legend" aria-hidden="true">'
        + '<span class="q-panel-heatmap__legend-item"><i class="q-panel-heatmap__swatch q-panel-heatmap__swatch--l1"></i>Az</span>'
        + '<span class="q-panel-heatmap__legend-item"><i class="q-panel-heatmap__swatch q-panel-heatmap__swatch--l3"></i>Orta</span>'
        + '<span class="q-panel-heatmap__legend-item"><i class="q-panel-heatmap__swatch q-panel-heatmap__swatch--l5"></i>Yoğun</span>'
        + '</div>';

      html += '<div class="q-panel-heatmap__grid" style="--heatmap-slots:' + SLOT_COUNT + '">';
      html += '<div class="q-panel-heatmap__corner"></div>';
      for (var s = 0; s < SLOT_COUNT; s++) {
        var startH = s * SLOT_SIZE;
        html += '<div class="q-panel-heatmap__slot-head">'
          + '<span class="q-panel-heatmap__slot-head-full">' + slotLabel(startH, false) + '</span>'
          + '<span class="q-panel-heatmap__slot-head-short">' + slotLabel(startH, true) + '</span>'
          + '</div>';
      }

      data.days.forEach(function (day, dayIdx) {
        var isToday = day.label === 'Bugün' || dayIdx === data.days.length - 1;
        var dayClass = 'q-panel-heatmap__day' + (isToday ? ' q-panel-heatmap__day--today' : '');
        var slots = daySlots(day);
        html += '<div class="' + dayClass + '">' + escapeHtml(day.label || '') + '</div>';
        slots.forEach(function (cell) {
          html += renderCell(cell);
        });
      });
      html += '</div>';

      var modeHint = data.mode === 'weekday_aggregate' ? ' (gün ortalaması)' : '';
      if (data.peak_count > 0) {
        var peakSlot = Math.floor((data.peak_hour || 0) / SLOT_SIZE) * SLOT_SIZE;
        var peakParts = slotLabel(peakSlot, false)
          + ' · ' + data.peak_count + ' sipariş';
        if (data.peak_revenue > 0) {
          peakParts += ' · ' + fmtMoney(data.peak_revenue);
        }
        html += '<div class="q-panel-heatmap__footer">'
          + '<span class="q-panel-heatmap__footer-label">En yoğun saat' + modeHint + '</span>'
          + '<span class="q-panel-heatmap__footer-value">' + peakParts + '</span>'
          + '</div>';
      }
      if (data.total_orders > 0) {
        var totalParts = data.total_orders + ' sipariş';
        if (data.total_revenue > 0) {
          totalParts += ' · ' + fmtMoney(data.total_revenue) + ' ciro';
        }
        html += '<div class="q-panel-heatmap__footer">'
          + '<span class="q-panel-heatmap__footer-label">Dönem toplamı</span>'
          + '<span class="q-panel-heatmap__footer-value">' + totalParts + '</span>'
          + '</div>';
      }
      html += '</div>';
      el.innerHTML = html;
      return true;
    },
  };

  root.QordyDashboardLists = Lists;
  root.QordyDashboardPanelSort = {
    bind: bindPanelSortToggles,
    refresh: refreshPanelSort,
    getMode: getSortMode,
    sortCategories: sortCategories,
  };
  bindPanelSortToggles();
})(window);
