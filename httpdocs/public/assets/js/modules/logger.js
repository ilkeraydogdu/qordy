/**
 * Logger Module
 * Centralized logging system that respects environment settings
 * Production'da console.log'ları devre dışı bırakır
 */
class Logger {
    constructor() {
        this.isDevelopment = this.detectEnvironment();
        this.errorHandler = null;
        
        // ErrorHandler'ı bul
        if (typeof window !== 'undefined' && window.errorHandler) {
            this.errorHandler = window.errorHandler;
        }
    }
    
    /**
     * Environment detection
     */
    detectEnvironment() {
        // Check if we're in development mode
        if (typeof window !== 'undefined') {
            // Check app config
            if (window.appConfig && window.appConfig.environment === 'development') {
                return true;
            }
            // Check BASE_URL for localhost
            if (window.BASE_URL && window.BASE_URL.includes('localhost')) {
                return true;
            }
            // Check if debug mode is enabled
            if (window.DEBUG_MODE === true) {
                return true;
            }
        }
        // Default to production (no console.log)
        return false;
    }
    
    /**
     * Log debug message (only in development)
     */
    debug(...args) {
        if (this.isDevelopment) {
            console.log('[DEBUG]', ...args);
        }
    }
    
    /**
     * Log info message (only in development)
     */
    info(...args) {
        if (this.isDevelopment) {
            console.info('[INFO]', ...args);
        }
    }
    
    /**
     * Log warning (always logged, but sent to error handler if available)
     */
    warn(...args) {
        const message = args.join(' ');
        if (this.errorHandler) {
            this.errorHandler.logError(message, 'warning');
        }
        // Always show warnings
        console.warn('[WARN]', ...args);
    }
    
    /**
     * Log error (always logged, sent to error handler)
     */
    error(...args) {
        const message = args.join(' ');
        if (this.errorHandler) {
            this.errorHandler.logError(message, 'error');
        }
        // Always show errors
        console.error('[ERROR]', ...args);
    }
    
    /**
     * Log message (only in development)
     * Alias for debug for backward compatibility
     */
    log(...args) {
        this.debug(...args);
    }
}

// Create singleton instance
const logger = new Logger();

// Export for use in modules
export default logger;

// Also expose globally for backward compatibility
if (typeof window !== 'undefined') {
    window.Logger = Logger;
    window.logger = logger;
    
    // Override console.log in production
    if (!logger.isDevelopment) {
        const originalLog = console.log;
        console.log = function() {
            // Only log if it's an error/warn level message
            // Otherwise silently ignore
        };
        
        // Keep console.error and console.warn
        // They are already handled by ErrorHandler
    }
}

