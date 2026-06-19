/**
 * Toast Notification System
 * DEPRECATED: This file is deprecated. Use notification.js instead.
 * This file is kept for backward compatibility only.
 * 
 * All functionality has been moved to notification.js (NotificationManager)
 * which provides a more robust, centralized notification system.
 */

// Redirect to NotificationManager if available
if (typeof window !== 'undefined') {
    // Wait for NotificationManager to be loaded
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            if (window.NotificationManager && !window.Toast) {
                // Create backward compatibility wrapper
                window.Toast = {
                    show: function(message, type, duration) {
                        if (window.NotificationManager) {
                            window.NotificationManager.show(message, type, duration);
                        }
                    },
                    success: function(message, duration) {
                        if (window.NotificationManager) {
                            window.NotificationManager.success(message, duration);
                        }
                    },
                    error: function(message, duration) {
                        if (window.NotificationManager) {
                            window.NotificationManager.error(message, duration);
                        }
                    },
                    warning: function(message, duration) {
                        if (window.NotificationManager) {
                            window.NotificationManager.warning(message, duration);
                        }
                    },
                    info: function(message, duration) {
                        if (window.NotificationManager) {
                            window.NotificationManager.info(message, duration);
                        }
                    },
                    remove: function(toast) {
                        if (window.NotificationManager) {
                            window.NotificationManager.remove(toast);
                        }
                    }
                };
            }
        });
    } else {
        // DOM already loaded
        if (window.NotificationManager && !window.Toast) {
            window.Toast = {
                show: function(message, type, duration) {
                    window.NotificationManager.show(message, type, duration);
                },
                success: function(message, duration) {
                    window.NotificationManager.success(message, duration);
                },
                error: function(message, duration) {
                    window.NotificationManager.error(message, duration);
                },
                warning: function(message, duration) {
                    window.NotificationManager.warning(message, duration);
                },
                info: function(message, duration) {
                    window.NotificationManager.info(message, duration);
                },
                remove: function(toast) {
                    window.NotificationManager.remove(toast);
                }
            };
        }
    }
}
