/**
 * Frontend Error Logger
 * Sends errors to backend instead of console
 */
(function() {
    'use strict';
    
    const ErrorLogger = {
        endpoint: '/api/log-error',
        enabled: true,
        
        /**
         * Log error to backend
         */
        log: function(message, context = {}, level = 'ERROR') {
            if (!this.enabled) return;
            
            const errorData = {
                message: message,
                level: level,
                context: context,
                url: window.location.href,
                userAgent: navigator.userAgent,
                timestamp: new Date().toISOString()
            };
            
            // Send to backend (non-blocking)
            if (navigator.sendBeacon) {
                navigator.sendBeacon(
                    this.endpoint,
                    JSON.stringify(errorData)
                );
            } else {
                fetch(this.endpoint, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(errorData),
                    keepalive: true
                }).catch(() => {}); // Silent fail
            }
        },
        
        /**
         * Log API error
         */
        logApiError: function(endpoint, error, statusCode) {
            this.log(`API Error: ${endpoint}`, {
                endpoint: endpoint,
                error: error.message || error,
                statusCode: statusCode
            }, 'ERROR');
        },
        
        /**
         * Log fetch error
         */
        logFetchError: function(url, error) {
            this.log(`Fetch Error: ${url}`, {
                url: url,
                error: error.message || error
            }, 'ERROR');
        },
        
        /**
         * Log validation error
         */
        logValidationError: function(field, error) {
            this.log(`Validation Error: ${field}`, {
                field: field,
                error: error
            }, 'WARNING');
        },
        
        /**
         * Catch global errors
         */
        init: function() {
            // Catch unhandled errors
            window.addEventListener('error', (event) => {
                this.log('Unhandled Error', {
                    message: event.message,
                    filename: event.filename,
                    lineno: event.lineno,
                    colno: event.colno
                }, 'ERROR');
            });
            
            // Catch unhandled promise rejections
            window.addEventListener('unhandledrejection', (event) => {
                this.log('Unhandled Promise Rejection', {
                    reason: event.reason
                }, 'ERROR');
            });
        }
    };
    
    // Initialize
    ErrorLogger.init();
    
    // Expose to window
    window.ErrorLogger = ErrorLogger;
    
    // Override console.error in production (optional)
    if (window.location.hostname !== 'localhost' && window.location.hostname !== '127.0.0.1') {
        const originalConsoleError = console.error;
        console.error = function(...args) {
            // Log to backend
            ErrorLogger.log(args.join(' '), {}, 'ERROR');
            // Still show in console for debugging (optional)
            // originalConsoleError.apply(console, args);
        };
    }
})();
