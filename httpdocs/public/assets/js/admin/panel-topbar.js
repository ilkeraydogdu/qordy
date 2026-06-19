/**
 * Business panel topbar — live datetime chip + date-range sync.
 */
(function (root) {
  'use strict';

  var RANGE_LABELS = {
    today: 'Bugün',
    week: 'Bu Hafta',
    month: 'Bu Ay',
    '3months': 'Son 3 Ay',
    '6months': 'Son 6 Ay',
    '9months': 'Son 9 Ay',
    year: 'Bu Yıl',
    custom: 'Özel Aralık'
  };

  var liveClockTimer = null;

  var dateFormatter = new Intl.DateTimeFormat('tr-TR', {
    day: '2-digit',
    month: 'short',
    year: 'numeric'
  });

  var timeFormatter = new Intl.DateTimeFormat('tr-TR', {
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    hour12: false
  });

  function formatLiveDateTime(date) {
    return dateFormatter.format(date) + ' · ' + timeFormatter.format(date);
  }

  function tickLiveDateTime() {
    var now = new Date();
    var formatted = formatLiveDateTime(now);
    document.querySelectorAll('[data-live-datetime]').forEach(function (el) {
      el.textContent = formatted;
    });
  }

  function startLiveClock() {
    tickLiveDateTime();
    if (liveClockTimer !== null) return;
    liveClockTimer = setInterval(tickLiveDateTime, 1000);
  }

  function updateRangeChip(rangeKey) {
    document.querySelectorAll('[data-panel-range-chip]').forEach(function (chip) {
      var label = RANGE_LABELS[rangeKey] || rangeKey;
      chip.setAttribute('data-range', rangeKey);
      chip.setAttribute('aria-label', 'Tarih aralığı: ' + label + ', canlı saat');
    });
  }

  function scrollToDateFilter() {
    var filter = document.querySelector('[data-date-range-filter]');
    if (!filter) return;
    filter.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'nearest' });
    var active = filter.querySelector('[aria-pressed="true"]');
    if (active && typeof active.focus === 'function') {
      try { active.focus({ preventScroll: true }); } catch (e) { /* noop */ }
    }
  }

  function bindRangeChipClick() {
    document.querySelectorAll('[data-panel-range-chip]').forEach(function (chip) {
      if (chip.__qordyTopbarBound) return;
      chip.__qordyTopbarBound = true;
      chip.addEventListener('click', function () {
        scrollToDateFilter();
      });
    });
  }

  function bindRangeSync() {
    root.addEventListener('qordy:range-changed', function (e) {
      var rangeKey = (e && e.detail && e.detail.range) || 'today';
      updateRangeChip(rangeKey);
    });
  }

  function start() {
    bindRangeChipClick();
    bindRangeSync();
    startLiveClock();
    var filter = document.querySelector('[data-date-range-filter]');
    if (filter) {
      var current = filter.getAttribute('data-current');
      if (current) updateRangeChip(current);
    }
  }

  root.QordyPanelTopbar = { start: start, updateRangeChip: updateRangeChip };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start);
  } else {
    start();
  }
})(window);
