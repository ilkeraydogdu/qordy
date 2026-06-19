/**
 * QORDY Simple Cache Manager
 * Adds cache-busting headers to fetch requests
 * Does NOT reload pages automatically to prevent infinite loops
 *
 * Also installs a lightweight console silencer for production:
 *   - Keeps console.warn / console.error untouched (real issues)
 *   - Silences console.log / info / debug unless the host looks like a
 *     dev environment (localhost / *.test / *.local) or the user has
 *     opted in via ?debug=1 or window.__QORDY_DEBUG = true.
 */

(function() {
    'use strict';

    // --- console silencer (prod default) --------------------------------
    try {
        var host = (window.location && window.location.hostname) || '';
        var qs = (window.location && window.location.search) || '';
        var isDevHost = /^localhost$|^127\.0\.0\.1$|\.test$|\.local$|\.lan$/i.test(host);
        var isDevParam = /[?&]debug=1\b/.test(qs);
        var isDevFlag = typeof window !== 'undefined' && window.__QORDY_DEBUG === true;
        if (!isDevHost && !isDevParam && !isDevFlag) {
            var noop = function() {};
            // Keep warn/error so production incidents stay visible.
            if (typeof console !== 'undefined') {
                console.log = noop;
                console.info = noop;
                console.debug = noop;
            }
        }
    } catch (e) { /* ignore */ }

    console.log('[CACHE-MANAGER] Simple cache manager initialized');
    
    /**
     * Override fetch to add cache-busting headers
     */
    if (window.fetch) {
        const originalFetch = window.fetch;

        /**
         * Same-origin check. `Cache-Control` is NOT a CORS-safelisted
         * request header, so adding it to cross-origin fetches forces a
         * preflight which third parties (iyzico / sentry / hotjar …)
         * generally reject. Only add it for requests against our own
         * host. See csrf.js for the same pattern.
         */
        function sameOrigin(url) {
            try {
                if (typeof url !== 'string') {
                    return url && url.url ? sameOrigin(url.url) : true;
                }
                if (!url) return true;
                if (/^https?:\/\//i.test(url) || url.startsWith('//')) {
                    const u = new URL(url, window.location.href);
                    return u.origin === window.location.origin;
                }
                return true; // relative URL
            } catch (e) {
                return false;
            }
        }

        window.fetch = function(url, options = {}) {
            // Only touch OUR api routes.
            if (typeof url !== 'string' || !url.includes('/api/') || !sameOrigin(url)) {
                return originalFetch.call(this, url, options);
            }

            if (!options.headers) {
                options.headers = {};
            }

            if (!options.headers['Cache-Control']) {
                options.headers['Cache-Control'] = 'no-cache';
            }

            return originalFetch.call(this, url, options);
        };
    }
    
    /**
     * Clean service workers if any exist (run once per session)
     */
    if ('serviceWorker' in navigator && !sessionStorage.getItem('sw_cleaned')) {
        navigator.serviceWorker.getRegistrations().then(registrations => {
            if (registrations.length > 0) {
                console.log('[CACHE-MANAGER] Cleaning', registrations.length, 'service workers');
                registrations.forEach(reg => reg.unregister());
                sessionStorage.setItem('sw_cleaned', 'true');
            }
        }).catch(() => {});
    }
    
    /**
     * Clean cache storage if any exists (run once per session)
     */
    if ('caches' in window && !sessionStorage.getItem('cache_cleaned')) {
        caches.keys().then(names => {
            if (names.length > 0) {
                console.log('[CACHE-MANAGER] Cleaning', names.length, 'cache storages');
                names.forEach(name => caches.delete(name));
                sessionStorage.setItem('cache_cleaned', 'true');
            }
        }).catch(() => {});
    }
    
    /**
     * Manual cache clean function (no reload)
     */
    window.forceCacheClean = function() {
        console.log('[CACHE-MANAGER] Manual cache clean requested');
        
        // Clean service workers
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.getRegistrations().then(regs => {
                regs.forEach(reg => reg.unregister());
            }).catch(() => {});
        }
        
        // Clean cache storage
        if ('caches' in window) {
            caches.keys().then(names => {
                names.forEach(name => caches.delete(name));
            }).catch(() => {});
        }
        
        console.log('[CACHE-MANAGER] Cache cleaned');
    };
    
    /**
     * Clear all caches function (no reload)
     */
    window.clearAllCaches = function() {
        window.forceCacheClean();
    };
    
    console.log('[CACHE-MANAGER] Ready - Manual clean: window.forceCacheClean()');
})();
