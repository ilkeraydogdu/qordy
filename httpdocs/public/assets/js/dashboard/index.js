/**
 * Qordy Dashboard — entry point (v2.1)
 *
 * Loaded by admin/dashboard.php. Each module exposes a single global:
 * - window.QordyDashboard (poller + start)
 * - window.QordyDashboardLists (per-target renderers)
 * - window.QordyDashboardCharts (canvas painters)
 * - window.QordyDashboardAI (AI advisor)
 *
 * v2.1: Modules are loaded in parallel (no sequential dependency),
 * and bootstrap auto-runs on DOMContentLoaded.
 */
(function () {
 var modules = [
 { src: 'dashboard/charts.js', global: 'QordyDashboardCharts' },
 { src: 'dashboard/lists.js', global: 'QordyDashboardLists' },
 { src: 'dashboard/card-range-filter.js', global: 'QordyDashboardCardRange' },
 { src: 'dashboard/date-range-filter.js', global: 'QordyDateRangeFilter' },
 { src: 'dashboard/ai-advisor.js', global: 'QordyDashboardAI' },
 { src: 'dashboard/poller.js', global: 'QordyDashboard' },
 ];

 var loadedCount = 0;
 var total = modules.length;
 var failed = [];

 function onModuleLoaded() {
 loadedCount++;
 // If all modules loaded (or attempts finished), dispatch event
 if (loadedCount + failed.length >= total) {
 try {
 window.dispatchEvent(new CustomEvent('qordy:dashboard:modules-ready', {
 detail: { loaded: loadedCount, failed: failed.length, errors: failed }
 }));
 } catch (e) { /* older browsers */ }
 }
 }

 function loadModule(mod) {
 var s = document.createElement('script');
 s.src = '/assets/js/' + mod.src + '?v=20260617k';
 s.defer = true;
 s.onload = function () { onModuleLoaded(); };
 s.onerror = function () {
 failed.push(mod.src);
 // Continue loading other modules — partial dashboard is better than stuck
 onModuleLoaded();
 };
 document.head.appendChild(s);
 }

 // Start loading all modules in parallel
 modules.forEach(loadModule);

 // Auto-start poller when DOM is ready AND all dashboard modules are loaded
 function tryStart() {
 var root = document.querySelector('[data-dashboard-root]');
 if (!root) return;

 if (window.QordyDateRangeFilter && typeof window.QordyDateRangeFilter.start === 'function') {
 try { window.QordyDateRangeFilter.start(); } catch (e) { /* noop */ }
 }
 if (window.QordyDashboardCardRange && typeof window.QordyDashboardCardRange.start === 'function') {
 try { window.QordyDashboardCardRange.start(); } catch (e) { /* noop */ }
 }
 }

 function tryStartPoller() {
 var root = document.querySelector('[data-dashboard-root]');
 if (!root) return;
 if (!window.QordyDashboard || typeof window.QordyDashboard.start !== 'function') return;
 if (!window.QordyDashboardLists) return;

 try { window.QordyDashboard.start(root); } catch (e) { /* start may already have run */ }
 if (window.QordyDashboardAI && typeof window.QordyDashboardAI.start === 'function') {
 try { window.QordyDashboardAI.start(root); } catch (e) { /* noop */ }
 }
 }

 if (document.readyState === 'loading') {
 document.addEventListener('DOMContentLoaded', tryStart);
 } else {
 tryStart();
 }

 // Failsafe: bind date/card filters even if a module failed to load
 setTimeout(tryStart, 3000);

 // Start poller only after list/chart renderers are available
 window.addEventListener('qordy:dashboard:modules-ready', function () {
 tryStart();
 tryStartPoller();
 });
})();
