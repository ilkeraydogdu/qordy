/**
 * Utility Functions for Qordy Application
 * Centralized utility functions to avoid code duplication
 */

// Ensure Utils namespace exists
if (typeof window.Utils === 'undefined') {
    window.Utils = {};
}

// Format currency according to locale
Utils.formatCurrency = function(amount, currency = 'TRY') {
    if (isNaN(amount) || amount === null || amount === undefined) return '0 ₺';

    try {
        return new Intl.NumberFormat('tr-TR', {
            style: 'currency',
            currency: currency
        }).format(amount);
    } catch (e) {
        return `${amount} ₺`;
    }
};

// Format date according to locale
Utils.formatDate = function(timestamp) {
    try {
        return new Date(timestamp).toLocaleString('tr-TR', {
            day: '2-digit',
            month: '2-digit',
            hour: '2-digit',
            minute: '2-digit'
        });
    } catch(e) {
        return '-';
    }
};

// Calculate duration from start time
Utils.getDuration = function(startTime) {
    if (!startTime) return '';
    const diff = Math.floor((Date.now() - startTime) / 60000);
    if (diff < 60) return `${diff} dk`;
    const h = Math.floor(diff / 60);
    const m = diff % 60;
    return `${h}s ${m}dk`;
};

// Generate a random ID
Utils.generateId = function() {
    return Math.random().toString(36).substring(2, 15) + Math.random().toString(36).substring(2, 15);
};

// Escape HTML to prevent XSS
Utils.escapeHtml = function(text) {
    if (text === null || text === undefined) return '';
    const div = document.createElement('div');
    div.textContent = String(text);
    return div.innerHTML;
};

// Sanitize input to prevent XSS (alias for escapeHtml)
Utils.sanitizeInput = function(input) {
    return Utils.escapeHtml(input);
};

// Validate email format
Utils.validateEmail = function(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
};

// Validate phone number format
Utils.validatePhone = function(phone) {
    const re = /^[\+]?[0-9]{10,15}$/;
    return re.test(phone);
};

// Debounce function to limit function calls
Utils.debounce = function(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
};

// Throttle function to limit function calls
Utils.throttle = function(func, limit) {
    let inThrottle;
    return function() {
        const args = arguments;
        const context = this;
        if (!inThrottle) {
            func.apply(context, args);
            inThrottle = true;
            setTimeout(() => inThrottle = false, limit);
        }
    };
};

// Deep clone object
Utils.deepClone = function(obj) {
    return JSON.parse(JSON.stringify(obj));
};

// Check if element is in viewport
Utils.isInViewport = function(element) {
    const rect = element.getBoundingClientRect();
    return (
        rect.top >= 0 &&
        rect.left >= 0 &&
        rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
        rect.right <= (window.innerWidth || document.documentElement.clientWidth)
    );
};

// Get URL parameters
Utils.getUrlParameter = function(name) {
    const urlParams = new URLSearchParams(window.location.search);
    return urlParams.get(name);
};

// Set URL parameter
Utils.setUrlParameter = function(key, value) {
    const url = new URL(window.location);
    url.searchParams.set(key, value);
    window.history.replaceState({}, '', url);
};

// Remove URL parameter
Utils.removeUrlParameter = function(key) {
    const url = new URL(window.location);
    url.searchParams.delete(key);
    window.history.replaceState({}, '', url);
};

// Local storage wrapper with error handling
Utils.storage = {
    set: function(key, value) {
        try {
            localStorage.setItem(key, JSON.stringify(value));
        } catch (e) {
            // Silent error handling
        }
    },
    
    get: function(key) {
        try {
            const item = localStorage.getItem(key);
            return item ? JSON.parse(item) : null;
        } catch (e) {
            return null;
        }
    },
    
    remove: function(key) {
        try {
            localStorage.removeItem(key);
        } catch (e) {
            // Silent error handling
        }
    },
    
    clear: function() {
        try {
            localStorage.clear();
        } catch (e) {
            // Silent error handling
        }
    }
};

// Session storage wrapper with error handling
Utils.sessionStorage = {
    set: function(key, value) {
        try {
            sessionStorage.setItem(key, JSON.stringify(value));
        } catch (e) {
            // Silent error handling
        }
    },
    
    get: function(key) {
        try {
            const item = sessionStorage.getItem(key);
            return item ? JSON.parse(item) : null;
        } catch (e) {
            return null;
        }
    },
    
    remove: function(key) {
        try {
            sessionStorage.removeItem(key);
        } catch (e) {
            // Silent error handling
        }
    },
    
    clear: function() {
        try {
            sessionStorage.clear();
        } catch (e) {
            // Silent error handling
        }
    }
};

// DOM manipulation utilities
Utils.dom = {
    // Wait for element to exist
    waitForElement: function(selector, timeout = 5000) {
        return new Promise((resolve, reject) => {
            const element = document.querySelector(selector);
            if (element) {
                resolve(element);
                return;
            }
            
            const observer = new MutationObserver(() => {
                const element = document.querySelector(selector);
                if (element) {
                    resolve(element);
                    observer.disconnect();
                }
            });
            
            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
            
            setTimeout(() => {
                observer.disconnect();
                reject(new Error(`Element ${selector} not found within ${timeout}ms`));
            }, timeout);
        });
    },
    
    // Add class with timeout
    addClassWithTimeout: function(element, className, timeout = 300) {
        element.classList.add(className);
        setTimeout(() => {
            element.classList.remove(className);
        }, timeout);
    },
    
    // Fade out element
    fadeOut: function(element, duration = 300) {
        element.style.transition = `opacity ${duration}ms ease-out`;
        element.style.opacity = '0';
        
        setTimeout(() => {
            element.style.display = 'none';
        }, duration);
    },
    
    // Fade in element
    fadeIn: function(element, duration = 300) {
        element.style.display = 'block';
        element.style.opacity = '0';
        element.style.transition = `opacity ${duration}ms ease-in`;
        
        setTimeout(() => {
            element.style.opacity = '1';
        }, 10);
    }
};

// Event utilities
Utils.events = {
    // Add event listener with automatic cleanup
    addListener: function(element, event, handler, options = {}) {
        element.addEventListener(event, handler, options);
        return () => element.removeEventListener(event, handler, options);
    },
    
    // Debounced event listener
    addDebouncedListener: function(element, event, handler, wait = 300) {
        const debouncedHandler = Utils.debounce(handler, wait);
        element.addEventListener(event, debouncedHandler);
        return () => element.removeEventListener(event, debouncedHandler);
    }
};

// Network utilities
Utils.network = {
    // Check if online
    isOnline: function() {
        return navigator.onLine;
    },
    
    // Retry failed requests
    retry: async function(fn, retries = 3, delay = 1000) {
        for (let i = 0; i < retries; i++) {
            try {
                return await fn();
            } catch (error) {
                if (i === retries - 1) throw error;
                await new Promise(resolve => setTimeout(resolve, delay * (i + 1)));
            }
        }
    }
};

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Any initialization code can go here
});

// CSRF Token Helper Functions
Utils.getCSRFToken = function() {
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
};

Utils.fetchWithCSRF = function(url, options = {}) {
    const method = (options.method || 'GET').toUpperCase();
    // Same-origin guard so we don't break CORS preflights against
    // external APIs (iyzico, sentry, hotjar, countly, ...).
    var sameOrigin = true;
    try {
        if (typeof window.__QORDY_isSameOrigin === 'function') {
            sameOrigin = window.__QORDY_isSameOrigin(url);
        } else if (typeof url === 'string' && (/^https?:\/\//i.test(url) || url.startsWith('//'))) {
            sameOrigin = new URL(url, window.location.href).origin === window.location.origin;
        }
    } catch (e) { sameOrigin = false; }

    if (['POST', 'PUT', 'DELETE', 'PATCH'].includes(method) && sameOrigin) {
        const token = Utils.getCSRFToken();

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

    return fetch(url, options);
};

/**
 * Group order items for display - aynı ürün (aynı özelleştirme) tek satırda birleştirilir
 * Farklı ekstra/çıkarılan malzeme/not = ayrı satır
 * @param {Array} items - Order items array
 * @returns {Array} Grouped items with summed quantity, first order_item_id, _order_item_ids for group ops
 */
Utils.groupOrderItemsForDisplay = function(items) {
    if (!Array.isArray(items) || items.length === 0) return [];
    const groups = {};
    items.forEach(item => {
        const menuItemId = item.menu_item_id || item.menu_item_Id || '';
        const variantId = item.variant_id || item.variant_Id || '';
        const extras = item.selected_extras || [];
        const extrasArr = Array.isArray(extras) ? extras : (typeof extras === 'string' ? (JSON.parse(extras || '[]') || []) : []);
        const extrasKey = extrasArr.map(e => (typeof e === 'object' ? (e.extra_id || e.name || '') : e)).sort().join('|');
        const excluded = item.excluded_ingredients || [];
        const excludedArr = Array.isArray(excluded) ? excluded : (typeof excluded === 'string' ? (JSON.parse(excluded || '[]') || []) : []);
        const excludedKey = excludedArr.map(e => (typeof e === 'object' ? (e.ingredient_name || e.name || '') : e)).sort().join('|');
        const note = (item.note || item.notes || item.item_note || '').trim();
        const key = [menuItemId, variantId, extrasKey, excludedKey, note].join('\0');
        if (!groups[key]) {
            groups[key] = {
                ...item,
                quantity: 0,
                _order_item_ids: []
            };
        }
        const qty = parseInt(item.quantity || 1);
        groups[key].quantity += qty;
        const oid = item.order_item_id || item.order_item_Id;
        if (oid) groups[key]._order_item_ids.push(oid);
        if (!groups[key].order_item_id && oid) groups[key].order_item_id = oid;
    });
    return Object.values(groups);
};

// Create global aliases for backward compatibility
// This ensures existing code using these functions directly will still work
if (typeof window.escapeHtml === 'undefined') {
    window.escapeHtml = Utils.escapeHtml;
}
if (typeof window.formatCurrency === 'undefined') {
    window.formatCurrency = Utils.formatCurrency;
}
if (typeof window.formatDate === 'undefined') {
    window.formatDate = Utils.formatDate;
}
if (typeof window.getCSRFToken === 'undefined') {
    window.getCSRFToken = Utils.getCSRFToken;
}
if (typeof window.fetchWithCSRF === 'undefined') {
    window.fetchWithCSRF = Utils.fetchWithCSRF;
}