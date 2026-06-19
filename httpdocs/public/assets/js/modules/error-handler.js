/**
 * Error Handler Module
 * Centralized JavaScript error handling and reporting
 */
class ErrorHandler {
    constructor() {
        this.errors = [];
        this.maxErrors = 100; // Keep last 100 errors in memory
        this.reportEndpoint = '/api/errors/report';
        this.initialized = false;
        this._originalConsoleError = null; // Will be set in init()
    }
    
    /**
     * Initialize error handler
     */
    init() {
        if (this.initialized) {
            return;
        }
        
        // Catch unhandled errors
        window.addEventListener('error', (event) => {
            // Check if this is a CSP warning before handling
            const errorInfo = {
                message: event.message,
                filename: event.filename,
                lineno: event.lineno,
                colno: event.colno,
                error: event.error
            };
            
            // Skip CSP warnings from browser extensions
            if (!this.isCSPWarning(errorInfo)) {
                this.handleError(errorInfo);
            } else {
                // Still log to console for debugging, but don't send to server
                if (this._originalConsoleError) {
                    this._originalConsoleError('CSP Warning (filtered):', event.message, event.filename);
                }
            }
        });
        
        // Catch unhandled promise rejections
        window.addEventListener('unhandledrejection', (event) => {
            this.handleError({
                message: event.reason?.message || 'Unhandled Promise Rejection',
                error: event.reason,
                type: 'promise_rejection'
            });
        });
        
        // Save original console methods
        this._originalConsoleError = console.error ? console.error.bind(console) : function() {};
        this._originalConsoleWarn = console.warn ? console.warn.bind(console) : function() {};
        this._originalConsoleLog = console.log ? console.log.bind(console) : function() {};
        this._originalConsoleInfo = console.info ? console.info.bind(console) : function() {};
        this._originalConsoleDebug = console.debug ? console.debug.bind(console) : function() {};
        
        let isLogging = false; // Prevent infinite loop
        const self = this;
        
        // Override console.error to capture errors
        console.error = (...args) => {
            if (isLogging) {
                self._originalConsoleError.apply(console, args);
                return;
            }
            
            isLogging = true;
            try {
                self._originalConsoleError.apply(console, args);
                
                // Check if this is a CSP warning - skip server logging
                const message = args.join(' ');
                if (!self._handlingError && !self.isCSPWarning(message)) {
                    self.logError(message, 'console_error');
                }
            } finally {
                isLogging = false;
            }
        };
        
        // Override console.warn to capture warnings
        console.warn = (...args) => {
            if (isLogging) {
                self._originalConsoleWarn.apply(console, args);
                return;
            }
            
            isLogging = true;
            try {
                self._originalConsoleWarn.apply(console, args);
                
                // Check if this is a CSP warning - skip server logging
                const message = args.join(' ');
                if (!self._handlingError && !self.isCSPWarning(message)) {
                    self.logError(message, 'console_warning');
                }
            } finally {
                isLogging = false;
            }
        };
        
        // Override console.log to capture logs (optional, can be filtered)
        console.log = (...args) => {
            if (isLogging) {
                self._originalConsoleLog.apply(console, args);
                return;
            }
            
            isLogging = true;
            try {
                self._originalConsoleLog.apply(console, args);
                // Only log to server in development or if it contains error keywords
                const message = args.join(' ');
                const isErrorLike = /error|exception|fail|warning/i.test(message);
                // Skip CSP warnings and only log if it's error-like
                if (isErrorLike && !self._handlingError && !self.isCSPWarning(message)) {
                    self.logError(message, 'console_log');
                }
            } finally {
                isLogging = false;
            }
        };
        
        // Override console.info to capture info messages
        console.info = (...args) => {
            if (isLogging) {
                self._originalConsoleInfo.apply(console, args);
                return;
            }
            
            isLogging = true;
            try {
                self._originalConsoleInfo.apply(console, args);
                // Only log important info messages
                const message = args.join(' ');
                const isImportant = /error|exception|fail|warning|critical/i.test(message);
                // Skip CSP warnings
                if (isImportant && !self._handlingError && !self.isCSPWarning(message)) {
                    self.logError(message, 'console_info');
                }
            } finally {
                isLogging = false;
            }
        };
        
        // Override console.debug to capture debug messages (optional)
        console.debug = (...args) => {
            if (isLogging) {
                self._originalConsoleDebug.apply(console, args);
                return;
            }
            
            isLogging = true;
            try {
                self._originalConsoleDebug.apply(console, args);
                // Debug messages are usually not sent to server unless they contain errors
                const message = args.join(' ');
                const isErrorLike = /error|exception|fail/i.test(message);
                // Skip CSP warnings
                if (isErrorLike && !self._handlingError && !self.isCSPWarning(message)) {
                    self.logError(message, 'console_debug');
                }
            } finally {
                isLogging = false;
            }
        };
        
        this.initialized = true;
    }
    
    /**
     * Handle an error
     * @param {Object} errorInfo Error information
     */
    handleError(errorInfo) {
        // Prevent recursive calls
        if (this._handlingError) {
            return;
        }
        
        // Check if this is a CSP warning from browser extension - skip server reporting
        const isCSP = this.isCSPWarning(errorInfo);
        
        this._handlingError = true;
        try {
            const error = {
                message: errorInfo.message || 'Unknown error',
                filename: errorInfo.filename || 'unknown',
                lineno: errorInfo.lineno || 0,
                colno: errorInfo.colno || 0,
                stack: errorInfo.error?.stack || errorInfo.stack || '',
                type: errorInfo.type || 'javascript_error',
                timestamp: new Date().toISOString(),
                userAgent: navigator.userAgent,
                url: window.location.href,
                userId: this.getUserId(),
                context: errorInfo.context || {}
            };
            
            // Log to console using original console.error to avoid recursion
            // Use the saved original console.error (set in init())
            if (this._originalConsoleError) {
                this._originalConsoleError('ErrorHandler:', error);
            } else {
                // Fallback if _originalConsoleError is not set (shouldn't happen)
                const originalError = console.error ? console.error.bind(console) : console.log;
                originalError('ErrorHandler:', error);
            }
            
            // Skip server reporting for CSP warnings (browser extension issues)
            if (isCSP) {
                // Still add to errors array for local debugging, but don't send to server
                this.errors.push(error);
                if (this.errors.length > this.maxErrors) {
                    this.errors.shift(); // Remove oldest error
                }
                // Don't report CSP warnings to server - they're harmless browser extension issues
                return;
            }
            
            // Add to errors array
            this.errors.push(error);
            if (this.errors.length > this.maxErrors) {
                this.errors.shift(); // Remove oldest error
            }
            
            // Report to server (async, don't block)
            this.reportError(error).catch(err => {
                if (this._originalConsoleError) {
                    this._originalConsoleError('Failed to report error to server:', err);
                } else {
                    console.warn('Failed to report error to server:', err);
                }
            });
            
            // Show user-friendly error message if needed
            if (this.shouldShowUserMessage(error)) {
                this.showUserMessage(error);
            }
        } finally {
            this._handlingError = false;
        }
    }
    
    /**
     * Report error to server
     * @param {Object} error Error object
     * @returns {Promise}
     */
    async reportError(error) {
        try {
            const baseUrl = (typeof window !== 'undefined' && window.appConfig) 
                ? window.appConfig.getBaseUrl() 
                : (window.BASE_URL || '');
            
            const csrfToken = this.getCSRFToken();
            if (!csrfToken) {
                console.warn('Error reporting skipped: CSRF token not available');
                return;
            }
            
            const response = await fetch(`${baseUrl}${this.reportEndpoint}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify(error),
                credentials: 'same-origin'
            });
            
            if (!response.ok) {
                const errorText = await response.text().catch(() => 'Unknown error');
                throw new Error(`HTTP ${response.status}: ${errorText}`);
            }
            
            const result = await response.json().catch(() => ({}));
            if (!result.success) {
                console.warn('Error reporting returned failure:', result);
            }
        } catch (err) {
            // Log to console with details, but don't break the app
            if (this._originalConsoleError) {
                this._originalConsoleError('Error reporting failed:', {
                    error: err,
                    errorMessage: err.message,
                    errorStack: err.stack,
                    attemptedError: error
                });
            } else {
                console.error('Error reporting failed:', err, 'Original error:', error);
            }
            
            // Store in sessionStorage as fallback for manual inspection
            try {
                const failedReports = JSON.parse(sessionStorage.getItem('failed_error_reports') || '[]');
                failedReports.push({
                    error: error,
                    failureReason: err.message,
                    timestamp: new Date().toISOString()
                });
                // Keep only last 10 failed reports
                if (failedReports.length > 10) {
                    failedReports.shift();
                }
                sessionStorage.setItem('failed_error_reports', JSON.stringify(failedReports));
            } catch (storageErr) {
                // Ignore storage errors
            }
        }
    }
    
    /**
     * Log an error manually
     * @param {string} message Error message
     * @param {string} type Error type
     * @param {Object} context Additional context
     */
    logError(message, type = 'manual', context = {}) {
        // Prevent recursive calls
        if (this._handlingError) {
            return;
        }
        
        // Extract filename and line from context if available
        const errorInfo = {
            message: message,
            type: type,
            context: context
        };
        
        // If context contains error object, extract stack trace
        if (context && context.error && context.error.stack) {
            errorInfo.stack = context.error.stack;
            errorInfo.filename = context.error.filename || context.filename;
            errorInfo.lineno = context.error.lineno || context.lineno;
            errorInfo.colno = context.error.colno || context.colno;
        } else if (context) {
            errorInfo.filename = context.filename;
            errorInfo.lineno = context.lineno;
            errorInfo.colno = context.colno;
            errorInfo.stack = context.stack;
        }
        
        this.handleError(errorInfo);
    }
    
    /**
     * Check if error is a CSP (Content Security Policy) warning from browser extension
     * @param {Object|string} errorInfo Error information or message string
     * @returns {boolean} True if this is a CSP warning that should be filtered
     */
    isCSPWarning(errorInfo) {
        // Handle both object and string inputs
        const message = typeof errorInfo === 'string' 
            ? errorInfo 
            : (errorInfo.message || errorInfo.error?.message || '');
        
        const filename = errorInfo.filename || errorInfo.source || '';
        const stack = errorInfo.stack || errorInfo.error?.stack || '';
        
        // Combine all text for pattern matching
        const combinedText = `${message} ${filename} ${stack}`.toLowerCase();
        
        // Aggressively filter CSP-related patterns and browser extension errors
        const cspPatterns = [
            'content security policy',
            'csp directive',
            'unsafe-eval',
            'unsafe-inline',
            'violates the following content security policy',
            'violates.*content security policy',
            'script-src',
            'content.js',  // Browser extension file
            'chrome-extension://',
            'moz-extension://',
            'safari-extension://',
            'extension://',
            'edge-extension://',
            'evaluating a string as javascript',
            'eval.*violates'
        ];
        
        // Check if any CSP pattern matches
        for (const pattern of cspPatterns) {
            if (combinedText.includes(pattern)) {
                return true;
            }
        }
        
        // Aggressive check: if filename is content.js, ignore ALL errors from it
        if (filename.includes('content.js') || filename.includes('content_script')) {
            return true;
        }
        
        // Check if error is from browser extension context
        if (filename.includes('extension://') || filename.includes('chrome-extension://') || 
            filename.includes('moz-extension://') || filename.includes('safari-extension://') ||
            filename.includes('edge-extension://')) {
            return true;
        }
        
        // Additional check: if message mentions CSP-related terms
        if (message.toLowerCase().includes('security') && 
            (message.toLowerCase().includes('policy') || message.toLowerCase().includes('directive') ||
             message.toLowerCase().includes('csp') || message.toLowerCase().includes('eval'))) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Get user ID from session/storage
     * @returns {string|null}
     */
    getUserId() {
        // Try to get from various sources
        if (window.userId) {
            return window.userId;
        }
        
        try {
            const userData = sessionStorage.getItem('user');
            if (userData) {
                const user = JSON.parse(userData);
                return user.id || user.user_id || null;
            }
        } catch (e) {
            // Ignore
        }
        
        return null;
    }
    
    /**
     * Get CSRF token
     * @returns {string|null}
     */
    getCSRFToken() {
        const metaTag = document.querySelector('meta[name="csrf-token"]');
        if (metaTag) {
            return metaTag.getAttribute('content');
        }
        return window.CSRF_TOKEN || null;
    }
    
    /**
     * Determine if user message should be shown
     * @param {Object} error Error object
     * @returns {boolean}
     */
    shouldShowUserMessage(error) {
        // Don't show for console errors or minor issues
        if (error.type === 'console_error') {
            return false;
        }
        
        // Show for critical errors
        return error.message.includes('Network') || 
               error.message.includes('Failed to fetch') ||
               error.message.includes('500') ||
               error.message.includes('Internal Server Error');
    }
    
    /**
     * Show user-friendly error message
     * @param {Object} error Error object
     */
    showUserMessage(error) {
        // Use notification system if available
        if (window.NotificationManager) {
            window.NotificationManager.show(
                'Bir hata oluştu. Lütfen sayfayı yenileyin veya daha sonra tekrar deneyin.',
                'error'
            );
        } else if (window.Toast) {
            window.Toast.show(
                'Bir hata oluştu. Lütfen sayfayı yenileyin.',
                'error'
            );
        } else if (window.NotificationManager) {
            window.NotificationManager.error('Bir hata oluştu. Lütfen sayfayı yenileyin.');
        }
    }
    
    /**
     * Get all errors
     * @returns {Array}
     */
    getErrors() {
        return [...this.errors];
    }
    
    /**
     * Clear errors
     */
    clearErrors() {
        this.errors = [];
    }
    
    /**
     * Get error count
     * @returns {number}
     */
    getErrorCount() {
        return this.errors.length;
    }
}

// Create singleton instance
const errorHandler = new ErrorHandler();

// Initialize IMMEDIATELY - don't wait for DOMContentLoaded
// This ensures console.error override happens before any scripts run
errorHandler.init();

// Export for use in modules
export default errorHandler;

// Also expose globally for backward compatibility
if (typeof window !== 'undefined') {
    window.ErrorHandler = ErrorHandler;
    window.errorHandler = errorHandler;
}

