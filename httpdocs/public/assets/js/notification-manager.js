/**
 * Enhanced Notification Manager with Deduplication
 * Only shows notifications for critical successful operations
 * Version: 2.0
 */

const NotificationManager = {
    shownNotifications: new Map(),
    deduplicationWindow: 10000, // 10 seconds (must be larger than polling interval)
    
    // Critical operations that should show notifications
    criticalOperations: [
        'payment_completed',
        'order_sent_to_kitchen',
        'order_ready',
        'table_transferred',
        'order_served',
        'receipt_printed'
    ],
    
    /**
     * Show notification with deduplication
     * @param {string} message - Notification message
     * @param {string} type - Notification type (success, error, warning, info)
     * @param {string} operationType - Operation type for filtering
     */
    show: function(message, type = 'info', operationType = null) {
        // Check if this is a critical operation
        if (operationType && !this.criticalOperations.includes(operationType)) {
            console.log(`[NotificationManager] Skipping non-critical notification: ${operationType}`);
            return;
        }
        
        // Generate unique key for this notification
        const key = `${type}_${message}`;
        
        // Check if this notification was recently shown
        if (this.shownNotifications.has(key)) {
            const lastShown = this.shownNotifications.get(key);
            const timeSinceLastShow = Date.now() - lastShown;
            
            if (timeSinceLastShow < this.deduplicationWindow) {
                console.log(`[NotificationManager] Deduplicated notification: ${message}`);
                return; // Don't show duplicate
            }
        }
        
        // Record this notification
        this.shownNotifications.set(key, Date.now());
        
        // Clean up old entries after a while
        setTimeout(() => {
            this.shownNotifications.delete(key);
        }, this.deduplicationWindow);
        
        // Show the notification
        this.display(message, type);
    },
    
    /**
     * Display notification (actual rendering)
     * @param {string} message - Notification message
     * @param {string} type - Notification type
     */
    display: function(message, type) {
        // Check if toast notification service exists (prefer it)
        if (window.toastNotificationService && typeof window.toastNotificationService.show === 'function') {
            window.toastNotificationService.show(message, type);
            return;
        }
        
        // Fallback to creating our own notification
        const notification = document.createElement('div');
        notification.className = `fixed top-4 right-4 z-[10000] p-4 rounded-lg shadow-2xl animate-slide-down ${this.getColorClass(type)} max-w-sm`;
        notification.innerHTML = `
            <div class="flex items-start gap-3">
                <div class="flex-shrink-0">
                    ${this.getIcon(type)}
                </div>
                <div class="flex-1">
                    <p class="font-bold text-sm">${this.escapeHtml(message)}</p>
                </div>
                <button onclick="this.parentElement.parentElement.remove()" class="flex-shrink-0 ml-2 hover:opacity-70">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
        `;
        
        document.body.appendChild(notification);
        
        // Auto-remove after 3 seconds
        setTimeout(() => {
            notification.style.opacity = '0';
            notification.style.transform = 'translateY(-20px)';
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    },
    
    /**
     * Get color class for notification type
     * @param {string} type - Notification type
     * @returns {string} CSS classes
     */
    getColorClass: function(type) {
        const colors = {
            success: 'bg-emerald-500 text-white',
            error: 'bg-red-500 text-white',
            warning: 'bg-amber-500 text-white',
            info: 'bg-blue-500 text-white'
        };
        return colors[type] || colors.info;
    },
    
    /**
     * Get icon for notification type
     * @param {string} type - Notification type
     * @returns {string} SVG icon HTML
     */
    getIcon: function(type) {
        const icons = {
            success: '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>',
            error: '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>',
            warning: '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>',
            info: '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>'
        };
        return icons[type] || icons.info;
    },
    
    /**
     * Escape HTML to prevent XSS
     * @param {string} text - Text to escape
     * @returns {string} Escaped text
     */
    escapeHtml: function(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    },
    
    /**
     * Convenience methods for critical operations only
     */
    success: function(message, operationType = null) {
        this.show(message, 'success', operationType);
    },
    
    error: function(message) {
        // Errors should always be shown via modal, not notification
        console.error('[NotificationManager] Error:', message);
        if (window.ConfirmationModal) {
            window.ConfirmationModal.show(message, 'Hata', {
                confirmText: 'Tamam',
                cancelText: '',
                confirmClass: 'px-4 py-2 bg-red-500 text-white rounded-lg font-bold hover:bg-red-600 transition-all'
            });
        } else {
            alert(message);
        }
    },
    
    warning: function(message, operationType = null) {
        this.show(message, 'warning', operationType);
    },
    
    info: function(message, operationType = null) {
        this.show(message, 'info', operationType);
    },
    
    /**
     * Critical operation notifications
     */
    paymentCompleted: function(amount) {
        this.show(`Ödeme başarıyla alındı: ${amount}`, 'success', 'payment_completed');
    },
    
    orderSentToKitchen: function(orderId) {
        this.show(`Sipariş #${orderId} mutfağa gönderildi`, 'success', 'order_sent_to_kitchen');
    },
    
    orderReady: function(orderId) {
        this.show(`Sipariş #${orderId} hazır`, 'success', 'order_ready');
    },
    
    tableTransferred: function(fromTable, toTable) {
        this.show(`${fromTable} → ${toTable} masa transferi tamamlandı`, 'success', 'table_transferred');
    },
    
    orderServed: function(orderId) {
        this.show(`Sipariş #${orderId} tamamlandı`, 'success', 'order_served');
    },
    
    receiptPrinted: function() {
        this.show('Fiş yazdırıldı', 'success', 'receipt_printed');
    },
    
    /**
     * Confirmation dialog (uses ConfirmationModal)
     */
    confirm: async function(message, title = 'Onay') {
        if (window.ConfirmationModal) {
            return await window.ConfirmationModal.show(message, title);
        } else {
            return confirm(message);
        }
    }
};

// Export to global scope
window.NotificationManager = NotificationManager;
