/**
 * Formatters Module
 * Centralized formatting functions for currency, dates, and other data types
 */

/**
 * Format currency according to locale
 * @param {number} amount - Amount to format
 * @param {string} currency - Currency code (default: 'TRY')
 * @returns {string} Formatted currency string
 */
export function formatCurrency(amount, currency = 'TRY') {
    if (isNaN(amount) || amount === null || amount === undefined) return '0 ₺';
    
    try {
        return new Intl.NumberFormat('tr-TR', { 
            style: 'currency', 
            currency: currency 
        }).format(amount);
    } catch (e) {
        return `${amount} ₺`;
    }
}

/**
 * Format date according to locale
 * @param {string|number|Date} timestamp - Date to format
 * @param {object} options - Formatting options
 * @returns {string} Formatted date string
 */
export function formatDate(timestamp, options = {}) {
    try {
        const defaultOptions = { 
            day: '2-digit', 
            month: '2-digit', 
            hour: '2-digit', 
            minute: '2-digit' 
        };
        return new Date(timestamp).toLocaleString('tr-TR', { ...defaultOptions, ...options });
    } catch(e) {
        return '-';
    }
}

/**
 * Format date as relative time (e.g., "2 dakika önce")
 * @param {string|number|Date} timestamp - Date to format
 * @returns {string} Relative time string
 */
export function formatRelativeTime(timestamp) {
    try {
        const date = new Date(timestamp);
        const now = new Date();
        const diffMs = now - date;
        const diffMins = Math.floor(diffMs / 60000);
        const diffHours = Math.floor(diffMins / 60);
        const diffDays = Math.floor(diffHours / 24);

        if (diffMins < 1) return 'Az önce';
        if (diffMins < 60) return `${diffMins} dakika önce`;
        if (diffHours < 24) return `${diffHours} saat önce`;
        if (diffDays < 7) return `${diffDays} gün önce`;
        
        return formatDate(timestamp);
    } catch(e) {
        return '-';
    }
}

/**
 * Calculate duration from start time
 * @param {number} startTime - Start timestamp in milliseconds
 * @returns {string} Formatted duration string
 */
export function getDuration(startTime) {
    if (!startTime) return '';
    const diff = Math.floor((Date.now() - startTime) / 60000);
    if (diff < 60) return `${diff} dk`;
    const h = Math.floor(diff / 60);
    const m = diff % 60;
    return `${h}s ${m}dk`;
}

/**
 * Format number with thousand separators
 * @param {number} number - Number to format
 * @returns {string} Formatted number string
 */
export function formatNumber(number) {
    if (isNaN(number) || number === null || number === undefined) return '0';
    return new Intl.NumberFormat('tr-TR').format(number);
}

