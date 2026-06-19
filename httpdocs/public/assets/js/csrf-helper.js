/**
 * CSRF Token Helper
 * Provides utilities for handling CSRF tokens in AJAX requests
 */

// Prevent duplicate loading
if (window.CSRFHelperLoaded) {
    console.log('CSRF Helper already loaded, skipping...');
} else {
    window.CSRFHelperLoaded = true;

(function() {
    'use strict';
    
    /**
     * Get CSRF token from various sources
     * @returns {string|null} CSRF token or null if not found
     */
    function getCSRFToken() {
        // Try window.CSRF_TOKEN first (set by admin_layout.php)
        if (typeof window.CSRF_TOKEN !== 'undefined' && window.CSRF_TOKEN) {
            return window.CSRF_TOKEN;
        }
        
        // Try meta tag
        const metaTag = document.querySelector('meta[name="csrf-token"]');
        if (metaTag && metaTag.content) {
            return metaTag.content;
        }
        
        // Try global csrfToken variable (set by individual views)
        if (typeof csrfToken !== 'undefined' && csrfToken) {
            return csrfToken;
        }
        
        return null;
    }
    
    /**
     * Fetch wrapper that automatically includes CSRF token
     * @param {string} url - Request URL
     * @param {object} options - Fetch options
     * @returns {Promise} Fetch promise
     */
    function _isSameOrigin(url) {
        if (typeof window.__QORDY_isSameOrigin === 'function') {
            return window.__QORDY_isSameOrigin(url);
        }
        try {
            if (typeof url !== 'string') return true;
            if (/^https?:\/\//i.test(url) || url.startsWith('//')) {
                return new URL(url, window.location.href).origin === window.location.origin;
            }
            return true;
        } catch (e) { return false; }
    }

    function fetchWithCSRF(url, options = {}) {
        // Only add CSRF token for state-changing, same-origin requests.
        const method = (options.method || 'GET').toUpperCase();
        if (['POST', 'PUT', 'DELETE', 'PATCH'].includes(method) && _isSameOrigin(url)) {
            const token = getCSRFToken();

            if (!token) {
                console.warn('CSRF token not found. Request may fail.');
            }

            options.headers = options.headers || {};

            if (!options.headers['X-CSRF-Token'] && !options.headers['x-csrf-token']) {
                if (token) {
                    options.headers['X-CSRF-Token'] = token;
                }
            }
        }

        // Call original fetch and handle response properly
        return fetch(url, options).then(async function(response) {
            // Check if response is ok and is JSON
            if (response.ok) {
                const contentType = response.headers.get('content-type');
                if (contentType && contentType.includes('application/json')) {
                    return response.json();
                } else {
                    // Check if response text looks like JSON
                    const responseText = await response.text();
                    const trimmedText = responseText.trim();
                    if (trimmedText.startsWith('{') || trimmedText.startsWith('[')) {
                        try {
                            return JSON.parse(responseText);
                        } catch (e) {
                            // If it's not valid JSON, return the text
                            return responseText;
                        }
                    } else {
                        return responseText;
                    }
                }
            } else {
                // For error responses, also check if it's JSON
                const contentType = response.headers.get('content-type');
                if (contentType && contentType.includes('application/json')) {
                    const errorData = await response.json();
                    throw errorData;
                } else {
                    // Check if response text looks like JSON
                    const responseText = await response.text();
                    const trimmedText = responseText.trim();
                    if (trimmedText.startsWith('{') || trimmedText.startsWith('[')) {
                        try {
                            const errorData = JSON.parse(responseText);
                            throw errorData;
                        } catch (e) {
                            // If it's not valid JSON, throw a generic error
                            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                        }
                    } else {
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }
                }
            }
        });
    }
    
    /**
     * XMLHttpRequest wrapper that automatically includes CSRF token
     * @param {string} method - HTTP method
     * @param {string} url - Request URL
     * @param {object} options - Request options
     * @returns {Promise} Promise that resolves with response
     */
    function xhrWithCSRF(method, url, options = {}) {
        return new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            const upperMethod = method.toUpperCase();
            
            xhr.open(upperMethod, url, true);
            
            // Set headers
            if (options.headers) {
                Object.keys(options.headers).forEach(key => {
                    xhr.setRequestHeader(key, options.headers[key]);
                });
            }
            
            // Add CSRF token for same-origin state-changing methods.
            if (['POST', 'PUT', 'DELETE', 'PATCH'].includes(upperMethod) && _isSameOrigin(url)) {
                const token = getCSRFToken();
                if (token) {
                    try { xhr.setRequestHeader('X-CSRF-Token', token); } catch (e) { /* already set */ }
                }
            }
            
            xhr.onload = function() {
                if (xhr.status >= 200 && xhr.status < 300) {
                    try {
                        const response = xhr.responseText;
                        const contentType = xhr.getResponseHeader('Content-Type');
                        if (contentType && contentType.includes('application/json')) {
                            resolve(JSON.parse(response));
                        } else {
                            // Check if response looks like JSON even without proper content-type
                            const trimmedResponse = response.trim();
                            if (trimmedResponse.startsWith('{') || trimmedResponse.startsWith('[')) {
                                try {
                                    resolve(JSON.parse(response));
                                } catch (parseError) {
                                    // If it's not valid JSON, return as text
                                    resolve(response);
                                }
                            } else {
                                resolve(response);
                            }
                        }
                    } catch (e) {
                        resolve(xhr.responseText);
                    }
                } else {
                    // For error responses, also check if it's JSON
                    try {
                        const response = xhr.responseText;
                        const contentType = xhr.getResponseHeader('Content-Type');
                        if (contentType && contentType.includes('application/json')) {
                            reject(JSON.parse(response));
                        } else {
                            // Check if response looks like JSON even without proper content-type
                            const trimmedResponse = response.trim();
                            if (trimmedResponse.startsWith('{') || trimmedResponse.startsWith('[')) {
                                try {
                                    reject(JSON.parse(response));
                                } catch (parseError) {
                                    // If it's not valid JSON, return as error object
                                    reject(new Error(`HTTP ${xhr.status}: ${xhr.statusText}`));
                                }
                            } else {
                                reject(new Error(`HTTP ${xhr.status}: ${xhr.statusText}`));
                            }
                        }
                    } catch (e) {
                        reject(new Error(`HTTP ${xhr.status}: ${xhr.statusText}`));
                    }
                }
            };
            
            xhr.onerror = function() {
                reject(new Error('Network error'));
            };
            
            if (options.body) {
                xhr.send(options.body);
            } else {
                xhr.send();
            }
        });
    }
    
    // Export functions to global scope
    window.getCSRFToken = getCSRFToken;
    window.fetchWithCSRF = fetchWithCSRF;
    window.xhrWithCSRF = xhrWithCSRF;
    
    // Override global fetch if option is enabled
    if (window.ENABLE_CSRF_AUTO_INJECT === true) {
        window._originalFetch = window.fetch;
        window.fetch = fetchWithCSRF;
    }
    
    console.log('CSRF Helper loaded');
})();

} // End of duplicate prevention guard

