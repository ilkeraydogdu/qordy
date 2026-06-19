/**
 * Configuration Module
 * Centralized configuration values (replaces global window variables)
 */
class AppConfig {
    constructor() {
        this.baseUrl = window.BASE_URL || '';
        this.websocketPort = window.WEBSOCKET_PORT || 8080;
        this.csrfToken = this.getCSRFToken();
    }
    
    getCSRFToken() {
        const metaTag = document.querySelector('meta[name="csrf-token"]');
        if (metaTag) {
            return metaTag.getAttribute('content');
        }
        return window.CSRF_TOKEN || null;
    }
    
    getBaseUrl() {
        return this.baseUrl;
    }
    
    getWebSocketPort() {
        return this.websocketPort;
    }
    
    getApiUrl(endpoint) {
        return `${this.baseUrl}${endpoint}`;
    }
    
    getWebSocketUrl() {
        // Use /ws proxy path (same as mobile) - port 8080 often blocked by firewall
        let base = (window.BASE_URL || '').toString().trim().replace(/\/$/, '');
        if (!base || !base.startsWith('http')) {
            base = window.location.origin + (base && base.startsWith('/') ? base : (base ? '/' + base : ''));
        }
        const isHttps = base.startsWith('https') || window.location.protocol === 'https:';
        const rest = base.replace(/^https?:\/\//, '');
        return (isHttps ? 'wss:' : 'ws:') + '//' + rest + (rest ? '/' : '') + 'ws';
    }
}

// Export singleton instance
export const config = new AppConfig();

// For backward compatibility, also expose on window (deprecated)
if (typeof window !== 'undefined') {
    window.AppConfig = AppConfig;
    window.appConfig = config;
}

