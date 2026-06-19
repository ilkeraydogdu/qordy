/**
 * Main JavaScript File
 * Global utility functions and initialization
 */

// Utils is loaded globally from utils.js (loaded before main.js)
// formatCurrency, formatDate, escapeHtml, and getDuration are now available globally
// No need to redeclare them here

// Prevent redeclaration if already defined
if (typeof generateId === 'undefined') {
    var generateId = window.Utils?.generateId || function(length = 21) {
        // Fallback: timestamp + random
        const timestamp = Date.now().toString(36);
        const random = Math.random().toString(36).substring(2);
        return (timestamp + random).substring(0, length);
    };
}

// Translation cache for notification messages
if (typeof translationCache === 'undefined') {
    var translationCache = {};
}

// Get translation from API
async function getTranslation(key) {
    if (translationCache[key]) {
        return translationCache[key];
    }
    
    try {
        const baseUrl = (typeof window !== 'undefined' && window.appConfig) 
            ? window.appConfig.getBaseUrl() 
            : (window.BASE_URL || '');
        const response = await fetch(`${baseUrl}/api/translate?key=${encodeURIComponent(key)}`);
        if (response.ok) {
            const data = await response.json();
            if (data.translation) {
                translationCache[key] = data.translation;
                return data.translation;
            }
        }
    } catch (e) {
        console.warn('Translation fetch failed:', e);
    }
    
    // Fallback: return key as-is
    return key;
}

// Legacy compatibility - use Toast API instead
async function showToast(message, type = 'info') {
    // Check if message is a translation key
    let displayMessage = message;
    if (typeof message === 'string' && message.startsWith('notifications.')) {
        displayMessage = await getTranslation(message);
    }
    
    if (window.NotificationManager) {
        window.NotificationManager.show(displayMessage, type);
    } else if (window.Toast) {
        window.Toast.show(displayMessage, type);
    } else {
        console.warn('Toast system not loaded');
    }
}

// Legacy compatibility - use API.updateOrderStatus instead
// Only define if not already defined (allows pages like kitchen dashboard to override)
if (typeof window.updateOrderStatus === 'undefined') {
    window.updateOrderStatus = function updateOrderStatus(orderId, newStatus, callback) {
        // Check if third parameter is a DOM element (kitchen dashboard signature) vs callback function
        // If it's a DOM element, this function shouldn't handle it - let page-specific handlers take over
        if (callback && (callback instanceof HTMLElement || callback.nodeType === 1)) {
            console.warn('updateOrderStatus called with DOM element - page-specific handler should be used');
            return;
        }
        
        if (window.API) {
            window.API.updateOrderStatus(orderId, newStatus)
                .then(data => {
                    if (data && data.success) {
                        if (typeof callback === 'function') {
                            callback();
                        }
                        showToast('notifications.success.order_updated', 'success');
                    } else {
                        showToast('notifications.error.order_update_failed', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('notifications.error.generic_error', 'error');
                    // Re-throw if callback expects error handling
                    if (typeof callback === 'function' && callback.length > 0) {
                        callback(error);
                    }
                });
        } else {
            console.warn('API not available for updateOrderStatus');
        }
    };
}

// Legacy compatibility - use API.callWaiter instead
function callWaiter(tableId, type = 'CALL_WAITER') {
    if (window.API) {
        window.API.callWaiter(tableId, type)
            .then(data => {
                if (data.success) {
                    const msgKey = type === 'CALL_WAITER' ? 'notifications.success.waiter_called' : 'notifications.success.bill_requested';
                    showToast(msgKey, 'info');
                } else {
                    showToast('notifications.error.request_failed', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('notifications.error.generic_error', 'error');
            });
    }
}

// Legacy compatibility - use API.updateTableStatus instead
function updateTableStatus(tableId, newStatus) {
    if (window.API) {
        window.API.updateTableStatus(tableId, newStatus)
            .then(data => {
                if (data.success) {
                    showToast('notifications.success.table_updated', 'success');
                } else {
                    showToast('notifications.error.table_update_failed', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('notifications.error.generic_error', 'error');
            });
    }
}

// Document ready function
// NOTE: Kitchen, preparation, waiter, POS screens use WebSocket + loadOrders() for real-time updates.
// DO NOT use location.reload() - it causes poor UX and performance issues.
document.addEventListener('DOMContentLoaded', function() {
    // No auto-refresh - real-time updates handled by RealtimeService/WebSocket per screen
});