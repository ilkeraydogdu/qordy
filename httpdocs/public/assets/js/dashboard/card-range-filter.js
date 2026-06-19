/**
 * Per-card dashboard date range pills (Bugün / Bu Hafta / Bu Ay / Son 3 Ay).
 * Dispatches qordy:card-range-changed for the poller to refetch with ?ranges= JSON.
 */
(function (root) {
  'use strict';

  var widgetRanges = {};
  /** Set only when the user clicks a per-card pill (not SSR / global sync). */
  var explicitOverrides = {};
  var debounceTimer = null;

  function resolveGlobalRange() {
    var root = document.querySelector('[data-dashboard-root]');
    return root ? (root.getAttribute('data-range') || '') : '';
  }

  function readFromDom() {
    var globalRange = resolveGlobalRange();
    document.querySelectorAll('[data-card-range-filter]').forEach(function (el) {
      var wid = el.getAttribute('data-widget');
      if (!wid) return;
      // Always inherit the global dashboard range unless the user clicked a card pill.
      if (globalRange) {
        widgetRanges[wid] = globalRange;
        return;
      }
      var active = el.querySelector('.q-panel-card__range--active');
      var fromPill = (active && active.getAttribute('data-range')) || '';
      widgetRanges[wid] = fromPill || 'today';
    });
    return widgetRanges;
  }

  function getRanges() {
    if (!Object.keys(widgetRanges).length) {
      readFromDom();
    }
    return Object.assign({}, widgetRanges);
  }

  /**
   * When the global dashboard range changes, align every per-card pill (and
   * in-memory map) so ?ranges= JSON does not override ?range= with stale keys.
   */
  function syncGlobalRange(rangeKey) {
    if (!rangeKey) return;
    explicitOverrides = {};
    document.querySelectorAll('[data-card-range-filter]').forEach(function (group) {
      var wid = group.getAttribute('data-widget');
      var matched = false;
      group.querySelectorAll('[data-range]').forEach(function (b) {
        var on = b.getAttribute('data-range') === rangeKey;
        if (on) matched = true;
        b.classList.toggle('q-panel-card__range--active', on);
        b.setAttribute('aria-pressed', on ? 'true' : 'false');
      });
      if (wid) {
        widgetRanges[wid] = rangeKey;
      }
      if (!matched) {
        group.querySelectorAll('[data-range]').forEach(function (b) {
          b.classList.remove('q-panel-card__range--active');
          b.setAttribute('aria-pressed', 'false');
        });
      }
    });
  }

  function start() {
    readFromDom();
    document.addEventListener('click', function (e) {
      var btn = e.target.closest('[data-card-range-filter] [data-range]');
      if (!btn) return;
      e.preventDefault();
      var group = btn.closest('[data-card-range-filter]');
      if (!group) return;
      var wid = group.getAttribute('data-widget');
      var range = btn.getAttribute('data-range') || 'today';
      group.querySelectorAll('[data-range]').forEach(function (b) {
        var on = b === btn;
        b.classList.toggle('q-panel-card__range--active', on);
        b.setAttribute('aria-pressed', on ? 'true' : 'false');
      });
      widgetRanges[wid] = range;
      explicitOverrides[wid] = range;
      clearTimeout(debounceTimer);
      debounceTimer = setTimeout(function () {
        try {
          window.dispatchEvent(new CustomEvent('qordy:card-range-changed', {
            detail: { widget: wid, range: range, ranges: getRanges() }
          }));
        } catch (err) { /* noop */ }
      }, 180);
    });
  }

  function hasExplicitOverrides() {
    return Object.keys(explicitOverrides).length > 0;
  }

  /** Returns null when no per-card overrides — poller must omit ?ranges= entirely. */
  function getApiRangePayload() {
    if (!hasExplicitOverrides()) return null;
    return getRanges();
  }

  root.QordyDashboardCardRange = {
    start: start,
    getRanges: getRanges,
    readFromDom: readFromDom,
    syncGlobalRange: syncGlobalRange,
    hasExplicitOverrides: hasExplicitOverrides,
    getApiRangePayload: getApiRangePayload
  };
})(window);
