/**
 * CSRF Token Management for AJAX Requests
 * Automatically adds CSRF token to all AJAX requests
 */
(function() {
    'use strict';
    
    const CSRF = {
        token: null,
        tokenName: 'csrf_token',
        headerName: 'X-CSRF-Token',
        
        /**
         * Initialize CSRF token from meta tag or hidden input
         */
        init: function() {
            // Try to get token from meta tag
            const metaTag = document.querySelector('meta[name="csrf-token"]');
            if (metaTag) {
                this.token = metaTag.getAttribute('content');
                return;
            }
            
            // Try to get token from hidden input
            const csrfInput = document.querySelector('input[name="' + this.tokenName + '"]');
            if (csrfInput) {
                this.token = csrfInput.value;
                return;
            }
            
            // Try to get token from window object (set by PHP)
            if (typeof window.CSRF_TOKEN !== 'undefined') {
                this.token = window.CSRF_TOKEN;
                return;
            }
            
            console.warn('CSRF: Token not found. CSRF protection may not work correctly.');
        },
        
        /**
         * Get current CSRF token
         * @returns {string|null} CSRF token
         */
        getToken: function() {
            if (!this.token) {
                this.init();
            }
            return this.token;
        },
        
        /**
         * Set CSRF token (for dynamic updates)
         * @param {string} token New CSRF token
         */
        setToken: function(token) {
            this.token = token;
            
            // Update meta tag if exists
            let metaTag = document.querySelector('meta[name="csrf-token"]');
            if (!metaTag) {
                metaTag = document.createElement('meta');
                metaTag.setAttribute('name', 'csrf-token');
                document.head.appendChild(metaTag);
            }
            metaTag.setAttribute('content', token);
            
            // Update hidden input if exists
            const csrfInput = document.querySelector('input[name="' + this.tokenName + '"]');
            if (csrfInput) {
                csrfInput.value = token;
            }
        },
        
        /**
         * Add CSRF token to headers.
         *
         * HTTP header names are case-insensitive, but plain JS objects are
         * not: if a caller already passed `X-CSRF-TOKEN` and we blindly
         * added `X-CSRF-Token`, the Fetch API would serialise BOTH and the
         * browser ends up sending a concatenated `token, token` value
         * (length ≈ 130) which the server then rejects as invalid CSRF.
         * So when writing into a plain object we first look for an
         * existing entry with the same case-insensitive name and either
         * reuse that key or skip entirely.
         *
         * @param {Headers|Object} headers Headers object
         */
        addToHeaders: function(headers) {
            const token = this.getToken();
            if (!token) {
                return;
            }

            if (headers instanceof Headers) {
                headers.set(this.headerName, token);
                return;
            }
            if (typeof headers !== 'object' || headers === null) {
                return;
            }

            const wantedLower = this.headerName.toLowerCase();
            let existingKey = null;
            for (const k in headers) {
                if (Object.prototype.hasOwnProperty.call(headers, k)
                    && typeof k === 'string'
                    && k.toLowerCase() === wantedLower) {
                    existingKey = k;
                    break;
                }
            }

            if (existingKey !== null) {
                const existingValue = headers[existingKey];
                if (existingValue && String(existingValue).length > 0) {
                    return;
                }
                headers[existingKey] = token;
                return;
            }

            headers[this.headerName] = token;
        },
        
        /**
         * Add CSRF token to FormData
         * @param {FormData} formData FormData object
         */
        addToFormData: function(formData) {
            const token = this.getToken();
            if (token) {
                formData.append(this.tokenName, token);
            }
        }
    };
    
    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            CSRF.init();
        });
    } else {
        CSRF.init();
    }
    
    /**
     * Classify a URL as same-origin relative to the current page.
     * Accepts strings and Request objects. Relative URLs, protocol-relative
     * URLs pointing at our host, and absolute URLs with matching origin
     * all count as same-origin. Everything else (iyzico, countly, hotjar,
     * sentry, etc.) is treated as cross-origin and will NOT receive the
     * CSRF header — otherwise we would trigger CORS preflight failures
     * against third-party APIs that don't allow `x-csrf-token`.
     */
    function isSameOrigin(url) {
        try {
            let raw;
            if (typeof url === 'string') {
                raw = url;
            } else if (url && typeof url.url === 'string') {
                // Request object
                raw = url.url;
            } else {
                return true; // Unknown, default to same-origin (safer for our app)
            }
            if (!raw) return true;
            // Protocol-relative or absolute — compare origins
            if (/^https?:\/\//i.test(raw) || raw.startsWith('//')) {
                const parsed = new URL(raw, window.location.href);
                return parsed.origin === window.location.origin;
            }
            // Relative path or other scheme (data:, blob:, mailto:) → same-origin / no-op
            return true;
        } catch (e) {
            return false;
        }
    }

    // Expose to other modules so they can share the heuristic.
    window.__QORDY_isSameOrigin = isSameOrigin;

    // Intercept fetch requests
    const originalFetch = window.fetch;
    window.fetch = function(url, options = {}) {
        // Skip CSRF for GET / HEAD / OPTIONS.
        const method = (options.method || 'GET').toUpperCase();
        if (method === 'GET' || method === 'HEAD' || method === 'OPTIONS') {
            return originalFetch.call(this, url, options);
        }

        // CRITICAL: only inject CSRF for same-origin requests. Adding
        // `X-CSRF-Token` to cross-origin fetches (iyzico, sentry, hotjar,
        // countly, ...) triggers CORS preflight and those third parties
        // don't whitelist our header → preflight fails and iyzico's
        // checkout form breaks.
        if (!isSameOrigin(url)) {
            return originalFetch.call(this, url, options);
        }

        if (!options.headers) {
            options.headers = {};
        }

        CSRF.addToHeaders(options.headers);

        return originalFetch.call(this, url, options);
    };

    // Intercept XMLHttpRequest
    const originalOpen = XMLHttpRequest.prototype.open;
    const originalSend = XMLHttpRequest.prototype.send;

    XMLHttpRequest.prototype.open = function(method, url, async, user, password) {
        this._method = method.toUpperCase();
        this._url = url;
        this._isSameOrigin = isSameOrigin(url);
        return originalOpen.call(this, method, url, async, user, password);
    };

    XMLHttpRequest.prototype.send = function(data) {
        // Only inject for same-origin state-changing requests.
        if (this._isSameOrigin && this._method && ['POST', 'PUT', 'DELETE', 'PATCH'].includes(this._method)) {
            const token = CSRF.getToken();
            if (token) {
                try {
                    this.setRequestHeader(CSRF.headerName, token);
                } catch (e) { /* header already set or disallowed */ }

                // If sending FormData, also add token to form data
                if (data instanceof FormData) {
                    CSRF.addToFormData(data);
                } else if (typeof data === 'string') {
                    try {
                        const params = new URLSearchParams(data);
                        params.append(CSRF.tokenName, token);
                        data = params.toString();
                    } catch (e) {
                        // If not URL-encoded, add as header only
                    }
                }
            }
        }

        return originalSend.call(this, data);
    };
    
    // Intercept jQuery AJAX if available
    if (typeof jQuery !== 'undefined' && jQuery.ajaxSetup) {
        jQuery.ajaxSetup({
            beforeSend: function(xhr, settings) {
                const method = (settings.type || settings.method || 'GET').toUpperCase();
                if (!['POST', 'PUT', 'DELETE', 'PATCH'].includes(method)) {
                    return;
                }
                // Only for same-origin calls (don't break cross-origin APIs).
                if (!isSameOrigin(settings.url)) {
                    return;
                }
                const token = CSRF.getToken();
                if (token) {
                    xhr.setRequestHeader(CSRF.headerName, token);
                    if (settings.data && typeof settings.data === 'object' && !(settings.data instanceof FormData)) {
                        settings.data[CSRF.tokenName] = token;
                    }
                }
            }
        });
    }
    
    // Expose CSRF globally
    window.CSRF = CSRF;
})();

