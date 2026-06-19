/**
 * Qordy Dashboard — Date Range Filter
 *
 * Hijacks clicks on [data-date-range-filter] buttons to:
 * 1. Update the active style (without full page reload)
 * 2. Update the dashboard root's data-range attribute
 * 3. Fire a CustomEvent('qordy:range-changed') that poller.js listens to,
 * triggering an immediate API refresh with the new range.
 * 4. Push the new URL to history (?range=…/path-based) for shareable links.
 *
 * The component renders page-mode (no full reload) by default now; the old
 * behaviour (window.location.href) is preserved as a fallback when JS errors.
 */
(function (root) {
 'use strict';

 // ARIA durumuna göre butonu görsel olarak senkronize et.
 // KRITIK: classList.remove() tüm olası active + inactive class'ları temizler
 // (Tailwind specificity sorununu önler), sonra sadece ARIA durumuna
 // uygun class'ları ekler. Eski basılan buton "yapışmış" görünmez.
 function scrollActiveIntoView(filter) {
  var active = filter.querySelector('[aria-pressed="true"]');
  if (!active || typeof active.scrollIntoView !== 'function') return;
  try {
   active.scrollIntoView({ inline: 'center', block: 'nearest', behavior: 'smooth' });
  } catch (e) {
   active.scrollIntoView(false);
  }
 }

 function activate(filter, button) {
 var btns = filter.querySelectorAll('[data-range]');
 btns.forEach(function (b) {
 var isActive = (b === button);
 b.setAttribute('aria-pressed', isActive ? 'true' : 'false');
 b.classList.toggle('q-btn--primary', isActive);
 b.classList.toggle('q-btn--ghost', !isActive);
 });
 scrollActiveIntoView(filter);
 }

 function updateUrl(rangeKey) {
  try {
  var url = new URL(window.location.href);
  url.searchParams.delete('_'); // bust any cache buster
  // Also rewrite path-based /business/dashboard/{range} for shareability
  var pathRe = /^(\/business\/dashboard)(\/[^/?#]*)?/;
  var m = url.pathname.match(pathRe);
  if (m) {
  url.pathname = '/business/dashboard/' + encodeURIComponent(rangeKey);
  url.searchParams.delete('range'); // remove redundant query parameter
  } else {
  url.searchParams.set('range', rangeKey);
  }
  window.history.replaceState({ range: rangeKey }, '', url.toString());
  } catch (e) { /* noop */ }
  }

 function resolveRangeDates(rangeKey) {
 var d = new Date();
 var pad = function (n) { return n < 10 ? '0' + n : '' + n; };
 var fmt = function (dt) { return dt.getFullYear() + '-' + pad(dt.getMonth() + 1) + '-' + pad(dt.getDate()); };
 var todayStr = fmt(d);
 var r = (rangeKey || '').toLowerCase();
 var monday = new Date(d);
 monday.setDate(d.getDate() - ((d.getDay() + 6) % 7));
 var sunday = new Date(monday);
 sunday.setDate(monday.getDate() + 6);
 var monthEnd = new Date(d.getFullYear(), d.getMonth() + 1, 0);
 switch (r) {
 case 'today': return [todayStr, todayStr];
 case 'week': return [fmt(monday), fmt(sunday)];
 case 'month': return [fmt(new Date(d.getFullYear(), d.getMonth(), 1)), fmt(monthEnd)];
 case '3months': { var m3 = new Date(d); m3.setMonth(d.getMonth() - 3); return [fmt(m3), todayStr]; }
 case '6months': { var m6 = new Date(d); m6.setMonth(d.getMonth() - 6); return [fmt(m6), todayStr]; }
 case '9months': { var m9 = new Date(d); m9.setMonth(d.getMonth() - 9); return [fmt(m9), todayStr]; }
 case 'year': return [d.getFullYear() + '-01-01', d.getFullYear() + '-12-31'];
 default: return [fmt(new Date(d.getFullYear(), d.getMonth(), 1)), fmt(monthEnd)];
 }
 }

 function onClick(e) {
 var btn = e.target.closest('[data-range]');
 if (!btn) return;
 var filter = btn.closest('[data-date-range-filter]');
 if (!filter) return;
 var rangeKey = btn.getAttribute('data-range');
 if (!rangeKey) return;

 // Stop the legacy <a> / window.location fallback (we update in place)
 e.preventDefault();
 e.stopPropagation();

 // Activate the new button visually
 activate(filter, btn);
 filter.setAttribute('data-current', rangeKey);

 // Update the dashboard root's data-range so the poller picks it up
 var dashboardRoot = document.querySelector('[data-dashboard-root]');
 if (dashboardRoot) {
 dashboardRoot.setAttribute('data-range', rangeKey);
 var bounds = resolveRangeDates(rangeKey);
 if (bounds && bounds[0] && bounds[1]) {
 dashboardRoot.setAttribute('data-range-start', bounds[0]);
 dashboardRoot.setAttribute('data-range-end', bounds[1]);
 }
 }

 // Push URL to history
 updateUrl(rangeKey);

 // Align per-card pills so ?ranges= does not override global ?range=
 if (root.QordyDashboardCardRange && typeof root.QordyDashboardCardRange.syncGlobalRange === 'function') {
 root.QordyDashboardCardRange.syncGlobalRange(rangeKey);
 }

 // Notify poller.js (and any other listener)
 try {
 window.dispatchEvent(new CustomEvent('qordy:range-changed', {
 detail: { range: rangeKey, source: 'date-range-filter' }
 }));
 } catch (err) { /* older browsers */ }

 // Keep layout topbar chip in sync (panel-topbar.js also listens)
 if (root.QordyPanelTopbar && typeof root.QordyPanelTopbar.updateRangeChip === 'function') {
 root.QordyPanelTopbar.updateRangeChip(rangeKey);
 }
 }

 function bind() {
 var filters = document.querySelectorAll('[data-date-range-filter]');
 filters.forEach(function (f) {
 if (f.__qordyBound) return;
 f.__qordyBound = true;
 f.addEventListener('click', onClick);
 scrollActiveIntoView(f);
 });
 }

 function start() {
 bind();
 }

 root.QordyDateRangeFilter = { start: start, bind: bind };
})(window);