/**
 * Centralized Notification System - MVC, OOP, Dynamic
 * Modern, design-consistent notification system for Qordy
 */

(function() {
    'use strict';

    /**
     * NotificationManager - Centralized notification management
     */
    class NotificationManager {
        constructor() {
            this.container = null;
            this.notifications = [];
            this.maxNotifications = 5;
            this.defaultDuration = 4000;
            // Wait for DOM to be ready before initializing
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', () => this.init());
            } else {
                this.init();
            }
        }

        /**
         * Get translation for a key
         * @param {string} key - Translation key (e.g., 'notifications.success')
         * @param {string} fallback - Fallback text if translation not found
         * @returns {string} - Translated text or fallback
         */
        getTranslation(key, fallback = null) {
            // First try window.notificationTranslations (set by views)
            if (window.notificationTranslations && window.notificationTranslations[key]) {
                return window.notificationTranslations[key];
            }
            
            // Try global getTranslation function if available (from main.js)
            if (typeof getTranslation === 'function') {
                // Note: getTranslation is async, but we need sync here
                // So we'll use it only if it's been cached or use sync version
                if (typeof translationCache !== 'undefined' && translationCache[key]) {
                    return translationCache[key];
                }
            }
            
            // Return fallback or key itself
            return fallback !== null ? fallback : key;
        }

        /**
         * Initialize notification container
         */
        init() {
            // Ensure document.body exists
            if (!document.body) {
                setTimeout(() => this.init(), 10);
                return;
            }
            this.createContainer();
            this.setupStyles();
        }

        /**
         * Create notification container
         */
        createContainer() {
            // Ensure document.body exists
            if (!document.body) {
                setTimeout(() => this.createContainer(), 10);
                return;
            }
            this.container = document.getElementById('notification-container');
            if (!this.container) {
                this.container = document.createElement('div');
                this.container.id = 'notification-container';
                this.container.className = 'fixed top-6 right-6 z-[9999] space-y-3 pointer-events-none';
                document.body.appendChild(this.container);
            }
        }

        /**
         * Setup custom styles
         */
        setupStyles() {
            if (!document.getElementById('notification-styles')) {
                const style = document.createElement('style');
                style.id = 'notification-styles';
                style.textContent = `
                    @keyframes notificationSlideIn {
                        from {
                            opacity: 0;
                            transform: translateX(100%) scale(0.95);
                        }
                        to {
                            opacity: 1;
                            transform: translateX(0) scale(1);
                        }
                    }
                    
                    @keyframes notificationSlideOut {
                        from {
                            opacity: 1;
                            transform: translateX(0) scale(1);
                        }
                        to {
                            opacity: 0;
                            transform: translateX(100%) scale(0.95);
                        }
                    }
                    
                    .notification-enter {
                        animation: notificationSlideIn 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards;
                    }
                    
                    .notification-exit {
                        animation: notificationSlideOut 0.3s cubic-bezier(0.4, 0, 1, 1) forwards;
                    }
                `;
                document.head.appendChild(style);
            }
        }

        /**
         * Show notification
         * @param {string} message - Notification message
         * @param {string} type - Type: 'success', 'error', 'warning', 'info'
         * @param {number} duration - Duration in milliseconds
         * @param {object} options - Additional options (icon, title, action)
         */
        show(message, type = 'info', duration = null, options = {}) {
            const notification = this.createNotification(message, type, options);
            this.container.appendChild(notification);
            this.notifications.push(notification);

            // Limit number of notifications
            if (this.notifications.length > this.maxNotifications) {
                this.remove(this.notifications[0]);
            }

            // Trigger enter animation
            requestAnimationFrame(() => {
                notification.classList.add('notification-enter');
            });

            // Auto remove
            const removeDuration = duration !== null ? duration : this.defaultDuration;
            if (removeDuration > 0) {
                setTimeout(() => {
                    this.remove(notification);
                }, removeDuration);
            }

            return notification;
        }

        /**
         * Create notification element
         */
        createNotification(message, type, options = {}) {
            const notification = document.createElement('div');
            notification.className = 'notification-item pointer-events-auto';
            
            const config = this.getTypeConfig(type);
            const icon = options.icon || config.icon;
            const title = options.title || config.title;
            const bgColor = config.bgColor;
            const borderColor = config.borderColor;
            const iconBg = config.iconBg;
            const textColor = config.textColor;

            notification.innerHTML = `
                <div class="bg-white rounded-3xl border-2 ${borderColor} ${bgColor} shadow-soft p-5 lg:p-6 min-w-[320px] max-w-[420px] backdrop-blur-sm">
                    <div class="flex items-start gap-4">
                        <!-- Icon -->
                        <div class="flex-shrink-0 w-12 h-12 ${iconBg} rounded-2xl flex items-center justify-center">
                            ${icon}
                        </div>
                        
                        <!-- Content -->
                        <div class="flex-1 min-w-0">
                            ${title ? `<div class="font-black text-base lg:text-lg ${textColor} mb-1">${title}</div>` : ''}
                            <div class="font-bold text-sm lg:text-base text-slate-700 leading-relaxed">${this.escapeHtml(message)}</div>
                            ${options.action ? `
                                <div class="mt-3">
                                    ${options.action}
                                </div>
                            ` : ''}
                        </div>
                        
                        <!-- Close Button -->
                        <button onclick="window.NotificationManager.remove(this.closest('.notification-item'))" 
                                class="flex-shrink-0 w-8 h-8 rounded-xl hover:bg-slate-100 flex items-center justify-center transition-all group">
                            <svg class="w-5 h-5 text-slate-400 group-hover:text-slate-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                </div>
            `;

            return notification;
        }

        /**
         * Get type configuration
         */
        getTypeConfig(type) {
            const configs = {
                success: {
                    title: this.getTranslation('notifications.success', 'Başarılı'),
                    bgColor: 'bg-green-50',
                    borderColor: 'border-green-200',
                    iconBg: 'bg-green-500',
                    textColor: 'text-green-700',
                    icon: `
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path>
                        </svg>
                    `
                },
                error: {
                    title: this.getTranslation('notifications.error', 'Hata'),
                    bgColor: 'bg-red-50',
                    borderColor: 'border-red-200',
                    iconBg: 'bg-red-500',
                    textColor: 'text-red-700',
                    icon: `
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    `
                },
                warning: {
                    title: this.getTranslation('notifications.warning', 'Uyarı'),
                    bgColor: 'bg-orange-50',
                    borderColor: 'border-orange-200',
                    iconBg: 'bg-orange-500',
                    textColor: 'text-orange-700',
                    icon: `
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                    `
                },
                info: {
                    title: this.getTranslation('notifications.info', 'Bilgi'),
                    bgColor: 'bg-blue-50',
                    borderColor: 'border-blue-200',
                    iconBg: 'bg-blue-500',
                    textColor: 'text-blue-700',
                    icon: `
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    `
                }
            };

            return configs[type] || configs.info;
        }

        /**
         * Remove notification
         */
        remove(notification) {
            if (!notification) return;
            
            const index = this.notifications.indexOf(notification);
            if (index > -1) {
                this.notifications.splice(index, 1);
            }

            notification.classList.remove('notification-enter');
            notification.classList.add('notification-exit');

            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 300);
        }

        /**
         * Clear all notifications
         */
        clear() {
            this.notifications.forEach(notification => {
                this.remove(notification);
            });
        }

        /**
         * Escape HTML
         */
        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        /**
         * Convenience methods
         */
        success(message, duration, options) {
            return this.show(message, 'success', duration, options);
        }

        error(message, duration, options) {
            return this.show(message, 'error', duration, options);
        }

        warning(message, duration, options) {
            return this.show(message, 'warning', duration, options);
        }

        info(message, duration, options) {
            return this.show(message, 'info', duration, options);
        }

        /**
         * Show confirmation dialog
         * @param {string} message - Confirmation message
         * @param {string} title - Dialog title (optional)
         * @param {object} options - Additional options (confirmText, cancelText)
         * @returns {Promise<boolean>} - Promise that resolves to true if confirmed, false if cancelled
         */
        confirm(message, title = null, options = {}) {
            return new Promise((resolve) => {
                const defaultTitle = title !== null ? title : this.getTranslation('notifications.confirm', 'Onay');
                const confirmText = options.confirmText || this.getTranslation('notifications.yes', 'Evet');
                const cancelText = options.cancelText || this.getTranslation('notifications.no', 'Hayır');
                
                // Create modal overlay
                const overlay = document.createElement('div');
                overlay.className = 'fixed inset-0 bg-black/50 backdrop-blur-sm z-[10000] flex items-center justify-center p-4';
                overlay.style.animation = 'fadeIn 0.2s ease-out';
                
                // Create modal
                const modal = document.createElement('div');
                modal.className = 'bg-white rounded-3xl border-2 border-orange-200 bg-orange-50 shadow-soft p-6 lg:p-8 max-w-md w-full';
                modal.style.animation = 'slideUp 0.3s cubic-bezier(0.16, 1, 0.3, 1)';
                
                modal.innerHTML = `
                    <div class="flex items-start gap-4 mb-6">
                        <div class="flex-shrink-0 w-12 h-12 bg-orange-500 rounded-2xl flex items-center justify-center">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="font-black text-lg lg:text-xl text-orange-700 mb-2">${this.escapeHtml(defaultTitle)}</div>
                            <div class="font-bold text-sm lg:text-base text-slate-700 leading-relaxed">${this.escapeHtml(message)}</div>
                        </div>
                    </div>
                    <div class="flex gap-3">
                        <button class="confirm-cancel-btn flex-1 py-3 bg-slate-100 text-slate-900 rounded-xl font-black text-sm hover:bg-slate-200 transition-all">
                            ${this.escapeHtml(cancelText)}
                        </button>
                        <button class="confirm-ok-btn flex-1 py-3 bg-orange-500 text-white rounded-xl font-black text-sm hover:bg-orange-600 transition-all">
                            ${this.escapeHtml(confirmText)}
                        </button>
                    </div>
                `;
                
                overlay.appendChild(modal);
                document.body.appendChild(overlay);
                
                // Add animations
                if (!document.getElementById('confirm-animations')) {
                    const style = document.createElement('style');
                    style.id = 'confirm-animations';
                    style.textContent = `
                        @keyframes fadeIn {
                            from { opacity: 0; }
                            to { opacity: 1; }
                        }
                        @keyframes fadeOut {
                            from { opacity: 1; }
                            to { opacity: 0; }
                        }
                        @keyframes slideUp {
                            from {
                                opacity: 0;
                                transform: translateY(20px) scale(0.95);
                            }
                            to {
                                opacity: 1;
                                transform: translateY(0) scale(1);
                            }
                        }
                    `;
                    document.head.appendChild(style);
                }
                
                // Handle confirm button
                const confirmBtn = modal.querySelector('.confirm-ok-btn');
                confirmBtn.addEventListener('click', () => {
                    this.removeConfirmDialog(overlay);
                    resolve(true);
                });
                
                // Handle cancel button
                const cancelBtn = modal.querySelector('.confirm-cancel-btn');
                cancelBtn.addEventListener('click', () => {
                    this.removeConfirmDialog(overlay);
                    resolve(false);
                });
                
                // Handle overlay click (close on outside click)
                overlay.addEventListener('click', (e) => {
                    if (e.target === overlay) {
                        this.removeConfirmDialog(overlay);
                        resolve(false);
                    }
                });
                
                // Handle ESC key
                const escHandler = (e) => {
                    if (e.key === 'Escape') {
                        this.removeConfirmDialog(overlay);
                        document.removeEventListener('keydown', escHandler);
                        resolve(false);
                    }
                };
                document.addEventListener('keydown', escHandler);
            });
        }

        /**
         * Remove confirmation dialog
         */
        removeConfirmDialog(overlay) {
            if (overlay && overlay.parentNode) {
                overlay.style.animation = 'fadeOut 0.2s ease-out';
                setTimeout(() => {
                    if (overlay.parentNode) {
                        overlay.parentNode.removeChild(overlay);
                    }
                }, 200);
            }
        }

        /**
         * Show prompt dialog (input dialog)
         * @param {string} message - Prompt message
         * @param {string} title - Dialog title (optional)
         * @param {string} defaultValue - Default input value (optional)
         * @param {object} options - Additional options (placeholder, confirmText, cancelText)
         * @returns {Promise<string|null>} - Promise that resolves to input value if confirmed, null if cancelled
         */
        prompt(message, title = null, defaultValue = '', options = {}) {
            return new Promise((resolve) => {
                const defaultTitle = title !== null ? title : this.getTranslation('notifications.input', 'Giriş');
                const confirmText = options.confirmText || this.getTranslation('notifications.ok', 'Tamam');
                const cancelText = options.cancelText || this.getTranslation('notifications.cancel', 'İptal');
                const placeholder = options.placeholder || '';
                
                // Create modal overlay
                const overlay = document.createElement('div');
                overlay.className = 'fixed inset-0 bg-black/50 backdrop-blur-sm z-[10000] flex items-center justify-center p-4';
                overlay.style.animation = 'fadeIn 0.2s ease-out';
                
                // Create modal
                const modal = document.createElement('div');
                modal.className = 'bg-white rounded-3xl border-2 border-blue-200 bg-blue-50 shadow-soft p-6 lg:p-8 max-w-md w-full';
                modal.style.animation = 'slideUp 0.3s cubic-bezier(0.16, 1, 0.3, 1)';
                
                const inputId = 'notification-prompt-input-' + Date.now();
                
                modal.innerHTML = `
                    <div class="flex items-start gap-4 mb-6">
                        <div class="flex-shrink-0 w-12 h-12 bg-blue-500 rounded-2xl flex items-center justify-center">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="font-black text-lg lg:text-xl text-blue-700 mb-2">${this.escapeHtml(defaultTitle)}</div>
                            <div class="font-bold text-sm lg:text-base text-slate-700 leading-relaxed mb-4">${this.escapeHtml(message)}</div>
                            <input 
                                type="text" 
                                id="${inputId}" 
                                class="w-full px-4 py-3 rounded-xl border-2 border-blue-200 focus:border-blue-500 focus:outline-none font-bold text-slate-900 text-sm lg:text-base"
                                placeholder="${this.escapeHtml(placeholder)}"
                                value="${this.escapeHtml(defaultValue)}"
                                autofocus
                            >
                        </div>
                    </div>
                    <div class="flex gap-3">
                        <button class="prompt-cancel-btn flex-1 py-3 bg-slate-100 text-slate-900 rounded-xl font-black text-sm hover:bg-slate-200 transition-all">
                            ${this.escapeHtml(cancelText)}
                        </button>
                        <button class="prompt-ok-btn flex-1 py-3 bg-blue-500 text-white rounded-xl font-black text-sm hover:bg-blue-600 transition-all">
                            ${this.escapeHtml(confirmText)}
                        </button>
                    </div>
                `;
                
                overlay.appendChild(modal);
                document.body.appendChild(overlay);
                
                // Focus input
                const input = document.getElementById(inputId);
                if (input) {
                    input.focus();
                    input.select();
                }
                
                // Handle confirm button
                const confirmBtn = modal.querySelector('.prompt-ok-btn');
                confirmBtn.addEventListener('click', () => {
                    const value = input ? input.value : '';
                    this.removePromptDialog(overlay);
                    resolve(value);
                });
                
                // Handle cancel button
                const cancelBtn = modal.querySelector('.prompt-cancel-btn');
                cancelBtn.addEventListener('click', () => {
                    this.removePromptDialog(overlay);
                    resolve(null);
                });
                
                // Handle Enter key
                const enterHandler = (e) => {
                    if (e.key === 'Enter' && input && document.activeElement === input) {
                        const value = input.value;
                        this.removePromptDialog(overlay);
                        document.removeEventListener('keydown', enterHandler);
                        resolve(value);
                    }
                };
                document.addEventListener('keydown', enterHandler);
                
                // Handle ESC key
                const escHandler = (e) => {
                    if (e.key === 'Escape') {
                        this.removePromptDialog(overlay);
                        document.removeEventListener('keydown', escHandler);
                        resolve(null);
                    }
                };
                document.addEventListener('keydown', escHandler);
                
                // Handle overlay click (close on outside click)
                overlay.addEventListener('click', (e) => {
                    if (e.target === overlay) {
                        this.removePromptDialog(overlay);
                        resolve(null);
                    }
                });
            });
        }

        /**
         * Remove prompt dialog
         */
        removePromptDialog(overlay) {
            if (overlay && overlay.parentNode) {
                overlay.style.animation = 'fadeOut 0.2s ease-out';
                setTimeout(() => {
                    if (overlay.parentNode) {
                        overlay.parentNode.removeChild(overlay);
                    }
                }, 200);
            }
        }
    }

    // Initialize global instance when DOM is ready
    function initNotificationManager() {
        if (document.body && !window.NotificationManager) {
            window.NotificationManager = new NotificationManager();
        } else if (!document.body) {
            // Retry if DOM is not ready
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initNotificationManager);
            } else {
                setTimeout(initNotificationManager, 10);
            }
        }
    }
    
    // Start initialization
    initNotificationManager();

    // Backward compatibility with Toast
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

    // Helper function for easy access
    window.showNotification = function(message, type, duration) {
        if (window.NotificationManager) {
            window.NotificationManager.show(message, type, duration);
        }
    };

    // NOTE: Native alert/confirm/prompt overrides removed - they break synchronous behavior
    // Use window.NotificationManager.confirm() and window.NotificationManager.prompt() directly
    // NOTE: Fetch/XHR interceptors removed - they caused duplicate notifications for ALL API responses
    // Views should call NotificationManager.show() explicitly when needed

})();

