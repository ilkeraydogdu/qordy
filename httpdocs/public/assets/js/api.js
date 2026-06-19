/**
 * API Helper Functions
 * Provides AJAX helper functions for API calls
 */

window.API = {
    baseURL: (typeof window !== 'undefined' && window.appConfig) 
        ? window.appConfig.getBaseUrl() || window.location.origin
        : window.location.origin,

    /**
     * Get CSRF token from meta tag or window variable
     * @returns {string|null} CSRF token
     */
    getCSRFToken: function() {
        // Try to get from meta tag
        const metaTag = document.querySelector('meta[name="csrf-token"]');
        if (metaTag) {
            return metaTag.getAttribute('content');
        }
        // Try to get from window variable
        if (window.CSRF_TOKEN) {
            return window.CSRF_TOKEN;
        }
        return null;
    },

    /**
     * Make an API request
     * Includes CSRF token for POST/PUT/DELETE requests
     * @param {string} endpoint - API endpoint
     * @param {object} options - Fetch options
     * @returns {Promise}
     */
    request: async function(endpoint, options = {}) {
        const url = `${this.baseURL}${endpoint}`;
        
        // Get CSRF token for state-changing requests
        const method = (options.method || 'GET').toUpperCase();
        const needsCSRF = ['POST', 'PUT', 'DELETE', 'PATCH'].includes(method);
        const csrfToken = needsCSRF ? this.getCSRFToken() : null;
        
        const defaultOptions = {
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            credentials: 'same-origin', // Include cookies for session-based auth
        };

        // Add CSRF token to headers if needed
        if (csrfToken) {
            defaultOptions.headers['X-CSRF-Token'] = csrfToken;
        }

        const config = {
            ...defaultOptions,
            ...options,
            headers: {
                ...defaultOptions.headers,
                ...(options.headers || {}),
            },
        };

        try {
            const response = await fetch(url, config);
            const data = await response.json();
            
            if (!response.ok) {
                throw new Error(data.message || data.error || 'API request failed');
            }
            
            return data;
        } catch (error) {
            if (window.logger) {
                window.logger.error('API Error:', error);
            } else {
                console.error('API Error:', error);
            }
            throw error;
        }
    },

    /**
     * GET request
     */
    get: function(endpoint) {
        return this.request(endpoint, { method: 'GET' });
    },

    /**
     * POST request
     */
    post: function(endpoint, data) {
        return this.request(endpoint, {
            method: 'POST',
            body: JSON.stringify(data),
        });
    },

    /**
     * PUT request
     */
    put: function(endpoint, data) {
        return this.request(endpoint, {
            method: 'PUT',
            body: JSON.stringify(data),
        });
    },

    /**
     * DELETE request
     */
    delete: function(endpoint) {
        return this.request(endpoint, { method: 'DELETE' });
    },

    // Specific API methods
    getMenu: function() {
        return this.get('/api/menu');
    },

    getOrders: function() {
        return this.get('/api/orders');
    },

    getTables: function() {
        return this.get('/api/tables');
    },

    placeOrder: function(orderData) {
        return this.post('/api/place-order', orderData);
    },

    updateOrderStatus: function(orderId, status) {
        return this.post('/api/update-order-status', {
            order_id: orderId,
            status: status,
        });
    },

    callWaiter: function(tableId, type = 'CALL_WAITER') {
        return this.post('/api/call-waiter', {
            table_id: tableId,
            type: type,
        });
    },

    requestBill: function(tableId) {
        return this.callWaiter(tableId, 'REQUEST_BILL');
    },

    updateTableStatus: function(tableId, status) {
        return this.post('/api/update-table-status', {
            table_id: tableId,
            status: status,
        });
    },

    /**
     * Log error to server
     * @param {Object} errorData - Error data to log
     * @returns {Promise}
     */
    logError: async function(errorData) {
        try {
            const url = `${this.baseURL}/api/errors/report`;
            const csrfToken = this.getCSRFToken();
            
            const options = {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify(errorData),
                credentials: 'same-origin',
            };
            
            if (csrfToken) {
                options.headers['X-CSRF-Token'] = csrfToken;
            }
            
            const response = await fetch(url, options);
            return response.ok;
        } catch (error) {
            // Silently fail - don't break error logging
            if (window.logger) {
                window.logger.warn('Failed to log error to server:', error);
            } else {
                console.warn('Failed to log error to server:', error);
            }
            return false;
        }
    },
};

