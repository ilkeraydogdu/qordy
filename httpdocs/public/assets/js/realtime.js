/**
 * Real-time Update Service
 * WebSocket-first with polling fallback for orders, tables, and notifications
 */

(function(global) {
'use strict';

if (global.RealtimeService) return;

function RealtimeService() {
    this.intervals = {};
    this.callbacks = {};
    this.typeOptions = {};
    this.baseUrl = (global.appConfig && global.appConfig.getBaseUrl && global.appConfig.getBaseUrl()) ||
                   global.BASE_URL || '';
    if (this.baseUrl === '/') this.baseUrl = '';
    this.isActive = false;
    this.connectionStatus = 'disconnected';
    this.errorCount = {};
    this.maxRetries = 3;
    this.retryDelay = 5000;
    this.lastUpdateTime = {};
    this.requestCache = new Map();
    this.cacheTimeout = 1000;
    this.statusCallbacks = [];

    this.ws = null;
    this.wsUrl = null;
    this.wsReconnectAttempts = 0;
    this.maxWsReconnectAttempts = 15;
    this.wsReconnectDelay = 2000;
    this.useWebSocket = true;
    this.subscriptions = new Set();
    this.useCustomLoaderTypes = new Set();
    this._intentionalClose = false;
    // Bazı tarayıcılarda `ws.send(...)` onopen handler'ından önce çağrılabiliyor
    // (ör. `initWebSocket` içinden hemen subscribe çağrıldığında). Bu durumda
    // readyState = CONNECTING olur ve `InvalidStateError` fırlar. Bunu
    // kuyruğa alıp onopen anında tek seferde flush ederek kesin çözüyoruz.
    this._sendQueue = [];

    this.createStatusIndicator();
    this.initWebSocket();

    var self = this;
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            self.pauseAll();
            if (self.ws && self.ws.readyState === WebSocket.OPEN) {
                self._intentionalClose = true;
                self.ws.close();
            }
        } else {
            if (!self.ws || self.ws.readyState !== WebSocket.OPEN) {
                self.wsReconnectAttempts = 0;
                self.initWebSocket();
            }
            self.resumeAll();
        }
    });
}

RealtimeService.prototype.initWebSocket = function() {
    if (!this.useWebSocket || typeof WebSocket === 'undefined') {
        this.useWebSocket = false;
        this.connectionStatus = 'disconnected';
        this.updateStatusIndicator();
        return;
    }

    if (global.WEBSOCKET_URL) {
        this.wsUrl = global.WEBSOCKET_URL;
    } else if (global.appConfig && global.appConfig.getWebSocketUrl) {
        this.wsUrl = global.appConfig.getWebSocketUrl();
    } else {
        var base = (global.BASE_URL || '').toString().trim().replace(/\/$/, '');
        if (!base || !base.match(/^https?:/)) {
            base = location.origin + (base && base.charAt(0) === '/' ? base : (base ? '/' + base : ''));
        }
        var isHttps = base.indexOf('https') === 0 || location.protocol === 'https:';
        var rest = base.replace(/^https?:\/\//, '');
        this.wsUrl = (isHttps ? 'wss://' : 'ws://') + rest + '/ws';
    }

    try {
        var self = this;
        this.ws = new WebSocket(this.wsUrl);

        this.ws.onopen = function() {
            self.connectionStatus = 'connected';
            self.wsReconnectAttempts = 0;
            self._intentionalClose = false;
            self.updateStatusIndicator();

            var bizId = global.BUSINESS_ID || null;
            var token = global.WEBSOCKET_AUTH_TOKEN || null;
            if (bizId || token) {
                var authMsg = { type: 'AUTH' };
                if (token) authMsg.token = token;
                if (bizId) authMsg.business_id = bizId;
                self._safeSend(authMsg);
            }

            self.subscribe('orders');
            self.subscribe('tables');
            self.subscribe('notifications');

            if (self._sendQueue.length) {
                var queued = self._sendQueue.splice(0);
                for (var i = 0; i < queued.length; i++) {
                    self._safeSend(queued[i]);
                }
            }

            self.pauseAll();

            console.log('[WS] Connected', self.wsUrl, 'auth=' + !!bizId + ' token=' + !!token);
        };

        this.ws.onmessage = function(event) {
            try {
                self.handleWebSocketMessage(JSON.parse(event.data));
            } catch (e) {
                console.error('[WS] Parse error:', e);
            }
        };

        this.ws.onerror = function() {
            self.connectionStatus = 'error';
            self.updateStatusIndicator();
        };

        this.ws.onclose = function() {
            self.connectionStatus = 'disconnected';
            self.updateStatusIndicator();

            if (self._intentionalClose) {
                self._intentionalClose = false;
                return;
            }

            self.resumeAll();

            if (self.wsReconnectAttempts < self.maxWsReconnectAttempts) {
                self.wsReconnectAttempts++;
                var delay = Math.min(self.wsReconnectDelay * Math.pow(1.5, self.wsReconnectAttempts - 1), 30000);
                setTimeout(function() {
                    if (!document.hidden) self.initWebSocket();
                }, delay);
            } else {
                self.useWebSocket = false;
                self.resumeAll();
            }
        };
    } catch (e) {
        console.error('[WS] Init failed:', e);
        this.useWebSocket = false;
        this.connectionStatus = 'disconnected';
        this.updateStatusIndicator();
        this.resumeAll();
    }
};

RealtimeService.prototype._safeSend = function(payload) {
    if (!this.ws) return false;
    var data = (typeof payload === 'string') ? payload : JSON.stringify(payload);
    try {
        if (this.ws.readyState === WebSocket.OPEN) {
            this.ws.send(data);
            return true;
        }
        if (this.ws.readyState === WebSocket.CONNECTING) {
            // Socket hazır değilse mesajı kuyruğa al; onopen tetiklendiğinde
            // flush edilecek. `InvalidStateError: send while CONNECTING`
            // raporlanan client hatasının köküdür.
            this._sendQueue.push(payload);
            return false;
        }
    } catch (e) {
        console.warn('[WS] send failed:', e);
    }
    return false;
};

RealtimeService.prototype.subscribe = function(channel) {
    this._safeSend({ type: 'subscribe', channel: channel });
    this.subscriptions.add(channel);
};

RealtimeService.prototype.unsubscribe = function(channel) {
    this._safeSend({ type: 'unsubscribe', channel: channel });
    this.subscriptions.delete(channel);
};

RealtimeService.prototype.handleWebSocketMessage = function(data) {
    var type = ((data && data.type) || '').toUpperCase();

    if (type === 'ORDER_CREATED' || type === 'ORDER_UPDATE' || type === 'ORDER_STATUS_CHANGED') {
        if (this.callbacks['orders']) {
            try { this.callbacks['orders'](data.data ? Object.assign({}, data.data, { type: type }) : { type: type }); } catch (e) { console.error('[WS] orders cb:', e); }
        }
        return;
    }
    if (type === 'TABLES_SNAPSHOT') {
        // Yeni (batched) akışta sunucu, tüm masaları tek mesajda gönderir.
        // Callback imzası eskisiyle aynı kalsın diye diziyi data olarak
        // geçiriyoruz; tüketiciler hem tek-obje hem de Array formatını
        // kabul etmelidir (admin/orders.js kitchen/preparation/pos). Bu,
        // mevcut ekranları kırmayan "additive" bir değişikliktir.
        if (this.callbacks['tables']) {
            try {
                this.callbacks['tables']({
                    type: type,
                    snapshot: true,
                    tables: (data.data && data.data.tables) || [],
                });
            } catch (e) { console.error('[WS] tables snapshot cb:', e); }
        }
        return;
    }
    if (type === 'TABLE_UPDATE') {
        if (this.callbacks['tables']) {
            try { this.callbacks['tables'](data.data || data); } catch (e) { console.error('[WS] tables cb:', e); }
        }
        return;
    }
    if (type === 'NOTIFICATION') {
        if (this.callbacks['notifications']) {
            try { this.callbacks['notifications'](data.data || data); } catch (e) { console.error('[WS] notif cb:', e); }
        }
        return;
    }

    if (data.type === 'event' && data.event && data.data) {
        var eventType = data.event.split('.')[0];
        var key = eventType === 'order' ? 'orders' : eventType;
        if (this.callbacks[key]) {
            try { this.callbacks[key](data.data); } catch (e) { /* */ }
        }
    }
};

RealtimeService.prototype.createStatusIndicator = function() {
    if (document.getElementById('realtime-status-indicator')) return;
    if (!document.body) {
        var self = this;
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() { self.createStatusIndicator(); });
        } else {
            setTimeout(function() { self.createStatusIndicator(); }, 100);
        }
        return;
    }
    var el = document.createElement('div');
    el.id = 'realtime-status-indicator';
    el.className = 'fixed bottom-4 left-4 z-[9998] px-3 py-2 rounded-lg shadow-lg text-xs font-bold transition-all opacity-0 pointer-events-none';
    el.innerHTML = '<span class="status-dot inline-block w-2 h-2 rounded-full mr-2"></span><span class="status-text"></span>';
    document.body.appendChild(el);
    this.updateStatusIndicator();
};

RealtimeService.prototype.updateStatusIndicator = function() {
    var el = document.getElementById('realtime-status-indicator');
    if (!el) return;
    var dot = el.querySelector('.status-dot');
    var txt = el.querySelector('.status-text');
    if (this.connectionStatus === 'connected') {
        el.className = 'fixed bottom-4 left-4 z-[9998] px-3 py-2 rounded-lg shadow-lg text-xs font-bold transition-all bg-green-500 text-white';
        if (dot) dot.className = 'status-dot inline-block w-2 h-2 rounded-full mr-2 bg-white';
        if (txt) txt.textContent = 'Anlık veriler aktif';
        el.style.opacity = '0';
    } else if (this.connectionStatus === 'error') {
        el.className = 'fixed bottom-4 left-4 z-[9998] px-3 py-2 rounded-lg shadow-lg text-xs font-bold transition-all bg-red-500 text-white';
        if (dot) dot.className = 'status-dot inline-block w-2 h-2 rounded-full mr-2 bg-white animate-pulse';
        if (txt) txt.textContent = 'Bağlantı hatası';
        el.style.opacity = '1';
    } else {
        el.className = 'fixed bottom-4 left-4 z-[9998] px-3 py-2 rounded-lg shadow-lg text-xs font-bold transition-all bg-yellow-500 text-white';
        if (dot) dot.className = 'status-dot inline-block w-2 h-2 rounded-full mr-2 bg-white animate-pulse';
        if (txt) txt.textContent = 'Bağlantı kesildi';
        el.style.opacity = '1';
    }
    for (var i = 0; i < this.statusCallbacks.length; i++) {
        try { this.statusCallbacks[i](this.connectionStatus); } catch (e) { /* */ }
    }
};

RealtimeService.prototype.onStatusChange = function(cb) {
    if (typeof cb === 'function') this.statusCallbacks.push(cb);
};

RealtimeService.prototype.pauseAll = function() {
    var keys = Object.keys(this.intervals);
    for (var i = 0; i < keys.length; i++) {
        if (this.intervals[keys[i]]) {
            clearInterval(this.intervals[keys[i]]);
            this.intervals[keys[i]] = null;
        }
    }
};

RealtimeService.prototype.resumeAll = function() {
    var self = this;
    Object.keys(this.callbacks).forEach(function(type) {
        if (self.useCustomLoaderTypes.has(type)) return;
        if (self.callbacks[type] && !self.intervals[type]) {
            var opts = self.typeOptions[type] || {};
            var interval = opts.interval || 5000;
            var pollUrl = opts.pollUrl;
            self.intervals[type] = setInterval(function() {
                if (self.isActive && !document.hidden && (!self.ws || self.ws.readyState !== WebSocket.OPEN)) {
                    self.poll(type, pollUrl);
                }
            }, interval);
            if (!self.ws || self.ws.readyState !== WebSocket.OPEN) {
                self.poll(type, pollUrl);
            }
        }
    });
};

RealtimeService.prototype.start = function(type, callback, intervalOrOptions) {
    var opts = (typeof intervalOrOptions === 'object' && intervalOrOptions) ? intervalOrOptions : { interval: intervalOrOptions || 5000 };
    var interval = opts.interval || 5000;
    var useCustomLoader = opts.useCustomLoader === true;
    var pollUrl = opts.pollUrl;

    if (this.intervals[type]) {
        clearInterval(this.intervals[type]);
        delete this.intervals[type];
    }

    this.callbacks[type] = callback;
    this.typeOptions[type] = { interval: interval, pollUrl: pollUrl };
    this.isActive = true;

    if (useCustomLoader) {
        this.useCustomLoaderTypes.add(type);
    } else {
        this.useCustomLoaderTypes.delete(type);
    }

    if (this.ws && this.ws.readyState === WebSocket.OPEN && useCustomLoader) {
        return;
    }

    if (!useCustomLoader) {
        var self = this;
        this.poll(type, pollUrl);
        this.intervals[type] = setInterval(function() {
            if (self.isActive && (!self.ws || self.ws.readyState !== WebSocket.OPEN)) {
                self.poll(type, pollUrl);
            }
        }, interval);
    }
};

RealtimeService.prototype.stop = function(type) {
    if (this.subscriptions.has(type)) this.unsubscribe(type);
    if (this.intervals[type]) { clearInterval(this.intervals[type]); delete this.intervals[type]; }
    delete this.callbacks[type];
    delete this.typeOptions[type];
    this.useCustomLoaderTypes.delete(type);
};

RealtimeService.prototype.stopAll = function() {
    var self = this;
    this.subscriptions.forEach(function(ch) { self.unsubscribe(ch); });
    Object.keys(this.intervals).forEach(function(t) { self.stop(t); });
    this.isActive = false;
    if (this.ws) { this._intentionalClose = true; this.ws.close(); this.ws = null; }
};

RealtimeService.prototype.poll = function(type, customUrl) {
    if (!this.callbacks[type] || document.hidden) return;
    var cached = this.requestCache.get(type);
    if (cached && (Date.now() - cached.time) < this.cacheTimeout) return;

    var url = '';
    var ts = Date.now();

    if (customUrl) {
        var base = (this.baseUrl || '').replace(/\/$/, '');
        var path = customUrl.charAt(0) === '/' ? customUrl : '/' + customUrl;
        url = base + path + (customUrl.indexOf('?') >= 0 ? '&' : '?') + 't=' + ts;
    } else {
        switch (type) {
            case 'orders': url = this.baseUrl + '/api/orders?t=' + ts; break;
            case 'tables': url = this.baseUrl + '/api/tables?t=' + ts; break;
            case 'notifications': url = this.baseUrl + '/api/notifications?t=' + ts; break;
            case 'table-orders':
                var tid = global.currentTableId || '';
                if (!tid) return;
                url = this.baseUrl + '/api/orders?table_id=' + tid + '&t=' + ts;
                break;
            default: return;
        }
    }

    this.requestCache.set(type, { time: Date.now() });
    var self = this;
    var controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
    var timeoutId = controller ? setTimeout(function() { controller.abort(); }, 10000) : null;

    fetch(url, {
        method: 'GET',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
        signal: controller ? controller.signal : undefined
    }).then(function(resp) {
        if (timeoutId) clearTimeout(timeoutId);
        if (resp.ok) return resp.json();
        throw new Error('HTTP ' + resp.status);
    }).then(function(data) {
        self.lastUpdateTime[type] = Date.now();
        self.errorCount[type] = 0;
        if (self.connectionStatus !== 'connected' && self.ws && self.ws.readyState !== WebSocket.OPEN) {
            // only mark connected for polling if WS not available
        }
        if (self.callbacks[type]) {
            try { self.callbacks[type](data); } catch (e) { console.error('[RT] Callback error ' + type + ':', e); }
        }
    }).catch(function(err) {
        if (timeoutId) clearTimeout(timeoutId);
        self.errorCount[type] = (self.errorCount[type] || 0) + 1;
        if (self.errorCount[type] >= self.maxRetries && self.errorCount[type] === self.maxRetries) {
            self.stop(type);
        }
    });
};

RealtimeService.prototype.getTableOrders = function(tableId) {
    return fetch(this.baseUrl + '/api/orders?table_id=' + tableId, {
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin'
    }).then(function(r) { return r.ok ? r.json() : []; }).catch(function() { return []; });
};

RealtimeService.prototype.getTables = function() {
    return fetch(this.baseUrl + '/api/tables', {
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin'
    }).then(function(r) { return r.ok ? r.json() : []; }).catch(function() { return []; });
};

RealtimeService.prototype.getNotifications = function() {
    return fetch(this.baseUrl + '/api/notifications', {
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin'
    }).then(function(r) { return r.ok ? r.json() : []; }).catch(function() { return []; });
};

global.RealtimeService = RealtimeService;

function initRealtimeService() {
    if (!global.realtimeService) {
        global.realtimeService = new RealtimeService();
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initRealtimeService);
} else {
    initRealtimeService();
}

global.addEventListener('beforeunload', function() {
    if (global.realtimeService) global.realtimeService.stopAll();
});

})(typeof window !== 'undefined' ? window : this);
