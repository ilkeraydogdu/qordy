/**
 * Qordy Dashboard AI Advisor v5 — compact feed, type-colored rows, no inner scroll.
 */
(function (root) {
    'use strict';

    var isLoading = false;
    var hasRendered = false;
    var btn = null;
    var out = null;
    var metaEl = null;
    var dashboardRoot = null;
    var apiPrefix = '/api/business';
    var baseUrl = '';
    var savedIds = {};
    var debounceTimer = null;
    var CACHE_PREFIX = 'qordy-ai-feed-v5:';
    var MAX_ROWS = 4;

    var IMPACT_LABEL = {
        'yüksek': 'Yüksek',
        'orta': 'Orta',
        'düşük': 'Düşük'
    };

    var TYPE_META = {
        revenue:     { emoji: '💰', label: 'GELİR',      cls: 'q-ai-type--gelir' },
        expense:     { emoji: '📉', label: 'GİDER',      cls: 'q-ai-type--gider' },
        performance: { emoji: '⚡', label: 'OPERASYON',  cls: 'q-ai-type--operasyon' },
        product:     { emoji: '🍽', label: 'ÜRÜN',       cls: 'q-ai-type--urun' },
        waste:       { emoji: '🔥', label: 'FİRE',       cls: 'q-ai-type--fire' },
        customer:    { emoji: '👥', label: 'MÜŞTERİ',    cls: 'q-ai-type--musteri' },
        menu:        { emoji: '📋', label: 'MENÜ',       cls: 'q-ai-type--menu' },
        staff:       { emoji: '👤', label: 'PERSONEL',   cls: 'q-ai-type--personel' },
        system:      { emoji: 'ℹ️', label: 'SİSTEM',     cls: 'q-ai-type--sistem' }
    };

    function csrfToken() {
        var m = document.querySelector('meta[name="csrf-token"]');
        return m ? m.getAttribute('content') : '';
    }

    function escapeHtml(s) {
        var d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }

    function setButtonState(state) {
        if (!btn) return;
        btn.disabled = state === 'loading';
        btn.setAttribute('aria-busy', state === 'loading' ? 'true' : 'false');
    }

    function showLoadingSoft(text) {
        if (!out || hasRendered) return;
        out.innerHTML = ''
            + '<div class="q-ai-feed__loading" data-ai-loading>'
            + '<span class="q-spinner q-spinner--sm" role="status" aria-label="Yükleniyor"></span>'
            + '<span class="q-hint">' + escapeHtml(text || 'Öneriler yükleniyor…') + '</span>'
            + '</div>';
    }

    function typeMeta(item) {
        var key = (item.category_key || '').toLowerCase();
        if (TYPE_META[key]) return TYPE_META[key];
        var label = (item.category || 'SİSTEM').toUpperCase();
        return { emoji: item.emoji || '💡', label: label, cls: 'q-ai-type--sistem' };
    }

    function impactClass(impact) {
        var i = (impact || 'orta').toLowerCase();
        if (i === 'yüksek') return 'q-ai-feed__impact--high';
        if (i === 'düşük') return 'q-ai-feed__impact--low';
        return 'q-ai-feed__impact--med';
    }

    function renderRow(item) {
        var id = item.id || '';
        var saved = savedIds[id] === true;
        var meta = typeMeta(item);
        var impact = (item.impact || 'orta').toLowerCase();
        var body = item.text || '';
        if (item.action) {
            body = body ? body + ' ' + item.action : item.action;
        }

        return ''
            + '<article class="q-ai-feed__row ' + meta.cls + '" role="listitem" data-insight-id="' + escapeHtml(id) + '" data-category-key="' + escapeHtml(item.category_key || '') + '">'
            + '<div class="q-ai-feed__row-main">'
            + '<span class="q-ai-feed__type" aria-hidden="true">' + escapeHtml(meta.emoji) + '</span>'
            + '<div class="q-ai-feed__body">'
            + '<div class="q-ai-feed__row-head">'
            + '<span class="q-ai-feed__badge">' + escapeHtml(meta.label) + '</span>'
            + '<span class="q-ai-feed__impact ' + impactClass(impact) + '" title="' + escapeHtml(IMPACT_LABEL[impact] || 'Öncelik') + '">' + escapeHtml(IMPACT_LABEL[impact] || 'Orta') + '</span>'
            + '</div>'
            + '<h4 class="q-ai-feed__title">' + escapeHtml(item.title || '') + '</h4>'
            + (item.metric ? '<div class="q-ai-feed__metric">' + escapeHtml(item.metric) + '</div>' : '')
            + (body ? '<p class="q-ai-feed__text">' + escapeHtml(body) + '</p>' : '')
            + '</div>'
            + '<button type="button" class="q-ai-feed__save' + (saved ? ' is-saved' : '') + '" data-ai-save="' + escapeHtml(id) + '" aria-pressed="' + (saved ? 'true' : 'false') + '" aria-label="' + (saved ? 'Kaydedildi' : 'Kaydet') + '">'
            + '<svg width="14" height="14" viewBox="0 0 24 24" fill="' + (saved ? 'currentColor' : 'none') + '" stroke="currentColor" stroke-width="2" aria-hidden="true">'
            + '<path d="M19 21l-7-5-7 5V5a2 2 0 012-2h10a2 2 0 012 2z"/>'
            + '</svg></button>'
            + '</div></article>';
    }

    function renderFeed(insights) {
        if (!out) return;
        var rows = Array.isArray(insights) ? insights.slice(0, MAX_ROWS) : [];
        if (rows.length === 0) {
            out.innerHTML = '<div class="q-panel-empty q-ai-feed__empty"><p>Bu dönem için öneri üretilemedi. Tarih aralığını genişletmeyi deneyin.</p></div>';
            hasRendered = true;
            return;
        }
        out.innerHTML = rows.map(renderRow).join('');
        bindSaveButtons();
        hasRendered = true;
    }

    function updateMeta(data) {
        if (!metaEl) return;
        var parts = ['Veri tabanlı öneriler'];
        if (data && data.range && data.range.start && data.range.end) {
            parts.push(data.range.start + ' – ' + data.range.end);
        }
        if (data && data.expires_at) {
            try {
                var exp = new Date(data.expires_at);
                var mins = Math.max(1, Math.round((exp.getTime() - Date.now()) / 60000));
                parts.push('~' + mins + ' dk taze');
            } catch (e) { /* ignore */ }
        }
        if (data && data.source_label) {
            parts[0] = data.source_label;
        }
        metaEl.textContent = parts.join(' · ');
    }

    /** Align with DashboardController::resolveRangeDates */
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
        var yearEnd = d.getFullYear() + '-12-31';

        switch (r) {
            case 'today': return [todayStr, todayStr];
            case 'week': return [fmt(monday), fmt(sunday)];
            case 'month': return [fmt(new Date(d.getFullYear(), d.getMonth(), 1)), fmt(monthEnd)];
            case '3months': {
                var m3 = new Date(d); m3.setMonth(d.getMonth() - 3);
                return [fmt(m3), todayStr];
            }
            case '6months': {
                var m6 = new Date(d); m6.setMonth(d.getMonth() - 6);
                return [fmt(m6), todayStr];
            }
            case '9months': {
                var m9 = new Date(d); m9.setMonth(d.getMonth() - 9);
                return [fmt(m9), todayStr];
            }
            case 'year': return [d.getFullYear() + '-01-01', yearEnd];
            default: return [fmt(new Date(d.getFullYear(), d.getMonth(), 1)), fmt(monthEnd)];
        }
    }

    function getCurrentRange() {
        var el = dashboardRoot || document.querySelector('[data-dashboard-root]');
        var url = new URL(window.location.href);
        return (el && el.getAttribute('data-range')) || url.searchParams.get('range') || 'month';
    }

    function getRangeDates() {
        var el = dashboardRoot || document.querySelector('[data-dashboard-root]');
        var range = getCurrentRange();
        if (el) {
            var s = el.getAttribute('data-range-start');
            var e = el.getAttribute('data-range-end');
            if (s && e && el.getAttribute('data-range') === range) {
                return [s, e];
            }
        }
        return resolveRangeDates(range);
    }

    function cacheKey(range, dates) {
        return CACHE_PREFIX + range + ':' + dates[0] + ':' + dates[1];
    }

    function readCache(key) {
        try {
            var raw = sessionStorage.getItem(key);
            if (!raw) return null;
            var parsed = JSON.parse(raw);
            if (!parsed || !parsed.expires_at) return null;
            if (new Date(parsed.expires_at).getTime() <= Date.now()) {
                sessionStorage.removeItem(key);
                return null;
            }
            return parsed;
        } catch (e) {
            return null;
        }
    }

    function writeCache(key, data) {
        try {
            sessionStorage.setItem(key, JSON.stringify({
                batch_id: data.batch_id,
                expires_at: data.expires_at,
                insights: data.insights,
                source_label: data.source_label,
                saved_ids: data.saved_ids,
                range: data.range
            }));
        } catch (e) { /* quota */ }
    }

    function applySavedIds(ids) {
        savedIds = {};
        if (Array.isArray(ids)) {
            ids.forEach(function (id) { savedIds[id] = true; });
        }
    }

    function fetchInsights(forceRefresh) {
        if (isLoading) return Promise.resolve();
        if (!out) return Promise.resolve();

        var range = getCurrentRange();
        var dates = getRangeDates();
        var key = cacheKey(range, dates);

        if (!forceRefresh) {
            var cached = readCache(key);
            if (cached && Array.isArray(cached.insights)) {
                applySavedIds(cached.saved_ids);
                renderFeed(cached.insights);
                updateMeta(cached);
                return Promise.resolve();
            }
        }

        isLoading = true;
        setButtonState('loading');
        if (!hasRendered) {
            showLoadingSoft('Öneriler hazırlanıyor…');
        } else {
            out.classList.add('q-ai-feed--refreshing');
        }

        var url = baseUrl + apiPrefix + '/ai-insights?mode=feed'
            + '&range=' + encodeURIComponent(range)
            + '&start_date=' + encodeURIComponent(dates[0])
            + '&end_date=' + encodeURIComponent(dates[1]);
        if (forceRefresh) {
            url += '&refresh=1';
        }

        return fetch(url, {
            method: 'GET',
            credentials: 'same-origin',
            headers: { 'X-CSRF-Token': csrfToken(), 'Accept': 'application/json' }
        })
            .then(function (r) {
                if (r.status === 429) throw new Error('rate-limited');
                if (!r.ok) throw new Error('http-' + r.status);
                return r.json();
            })
            .then(function (data) {
                if (data && data.success) {
                    applySavedIds(data.saved_ids || []);
                    renderFeed(data.insights || []);
                    updateMeta(data);
                    writeCache(key, data);
                } else {
                    renderFeed([]);
                }
            })
            .catch(function (err) {
                if (err && err.message === 'rate-limited') {
                    if (!hasRendered) {
                        out.innerHTML = '<div class="q-panel-empty q-ai-feed__empty"><p>Çok sık yenilendi. Lütfen bir dakika bekleyin.</p></div>';
                    }
                    return;
                }
                if (!hasRendered) {
                    out.innerHTML = '<div class="q-panel-empty q-ai-feed__empty"><p>Öneriler yüklenemedi. Yenile\'yi deneyin.</p></div>';
                }
            })
            .then(function () {
                isLoading = false;
                setButtonState('idle');
                if (out) out.classList.remove('q-ai-feed--refreshing');
            });
    }

    function toggleSave(insightId, buttonEl) {
        var row = out && out.querySelector('[data-insight-id="' + insightId + '"]');
        if (!row) return;

        var isSaved = buttonEl.classList.contains('is-saved');
        var url = baseUrl + apiPrefix + '/ai-insights/' + (isSaved ? 'unsave' : 'save');
        var badgeEl = row.querySelector('.q-ai-feed__badge');
        var payload = {
            insight_id: insightId,
            category_key: row.getAttribute('data-category-key') || '',
            category: badgeEl ? badgeEl.textContent : '',
            title: (row.querySelector('.q-ai-feed__title') || {}).textContent || '',
            metric: (row.querySelector('.q-ai-feed__metric') || {}).textContent || '',
            text: (row.querySelector('.q-ai-feed__text') || {}).textContent || '',
            action: '',
            source: 'rule'
        };

        fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken(),
                'Accept': 'application/json'
            },
            body: JSON.stringify(payload)
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data || !data.success) return;
                if (isSaved) {
                    delete savedIds[insightId];
                    buttonEl.classList.remove('is-saved');
                    buttonEl.setAttribute('aria-pressed', 'false');
                    buttonEl.setAttribute('aria-label', 'Kaydet');
                    buttonEl.querySelector('svg').setAttribute('fill', 'none');
                } else {
                    savedIds[insightId] = true;
                    buttonEl.classList.add('is-saved');
                    buttonEl.setAttribute('aria-pressed', 'true');
                    buttonEl.setAttribute('aria-label', 'Kaydedildi');
                    buttonEl.querySelector('svg').setAttribute('fill', 'currentColor');
                }
            });
    }

    function bindSaveButtons() {
        if (!out) return;
        out.querySelectorAll('[data-ai-save]').forEach(function (btnEl) {
            btnEl.addEventListener('click', function (ev) {
                ev.preventDefault();
                var id = btnEl.getAttribute('data-ai-save');
                if (id) toggleSave(id, btnEl);
            });
        });
    }

    function onRangeChanged() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(function () {
            hasRendered = false;
            fetchInsights(true);
        }, 400);
    }

    function init() {
        var triggers = document.querySelectorAll('[data-ai-trigger]');
        btn = triggers.length ? triggers[0] : null;
        out = document.getElementById('ai-insights-text');
        metaEl = document.getElementById('ai-feed-meta');
        if (!out) return;

        dashboardRoot = document.querySelector('[data-dashboard-root]');
        apiPrefix = (dashboardRoot && dashboardRoot.getAttribute('data-api-prefix')) || apiPrefix;
        baseUrl = (root.BASE_URL || '').replace(/\/$/, '');

        triggers.forEach(function (triggerBtn) {
            triggerBtn.addEventListener('click', function () { fetchInsights(true); });
        });

        fetchInsights(false);
        root.addEventListener('qordy:range-changed', onRangeChanged);
    }

    function start() {
        init();
    }

    root.QordyDashboardAI = {
        start: start,
        refresh: function () { return fetchInsights(true); },
        resolveDatesForRange: resolveRangeDates
    };
})(window);
