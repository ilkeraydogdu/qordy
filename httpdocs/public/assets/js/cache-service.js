/**
 * Global Cache Service for Frontend
 * Provides caching for API responses with automatic invalidation
 * Prevents duplicate API calls and improves performance
 */
class CacheService {
    constructor() {
        this.cache = new Map();
        this.cacheTimestamps = new Map();
        this.defaultTTL = 5 * 60 * 1000; // 5 minutes default
        this.maxCacheSize = 100; // Maximum number of cached items
        this.pendingRequests = new Map(); // Prevent duplicate concurrent requests
    }
    
    /**
     * Get cached value or fetch from API
     * @param {string} key Cache key
     * @param {Function} fetchFn Function that returns a Promise
     * @param {number} ttl Time to live in milliseconds
     * @returns {Promise} Cached value or fetch result
     */
    async get(key, fetchFn, ttl = null) {
        const cacheTTL = ttl ?? this.defaultTTL;
        const now = Date.now();
        
        // Check cache
        if (this.cache.has(key)) {
            const timestamp = this.cacheTimestamps.get(key);
            if (now - timestamp < cacheTTL) {
                return Promise.resolve(this.cache.get(key));
            } else {
                // Expired - remove from cache
                this.cache.delete(key);
                this.cacheTimestamps.delete(key);
            }
        }
        
        // Check if request is already pending (prevent duplicate concurrent requests)
        if (this.pendingRequests.has(key)) {
            return this.pendingRequests.get(key);
        }
        
        // Fetch data
        const fetchPromise = fetchFn()
            .then(data => {
                // Store in cache
                this.set(key, data, cacheTTL);
                return data;
            })
            .finally(() => {
                // Remove from pending requests
                this.pendingRequests.delete(key);
            });
        
        this.pendingRequests.set(key, fetchPromise);
        return fetchPromise;
    }
    
    /**
     * Set cache value
     * @param {string} key Cache key
     * @param {*} value Value to cache
     * @param {number} ttl Time to live in milliseconds
     */
    set(key, value, ttl = null) {
        // Enforce max cache size (LRU eviction)
        if (this.cache.size >= this.maxCacheSize) {
            // Remove oldest entry
            let oldestKey = null;
            let oldestTime = Infinity;
            
            for (const [k, time] of this.cacheTimestamps.entries()) {
                if (time < oldestTime) {
                    oldestTime = time;
                    oldestKey = k;
                }
            }
            
            if (oldestKey) {
                this.cache.delete(oldestKey);
                this.cacheTimestamps.delete(oldestKey);
            }
        }
        
        this.cache.set(key, value);
        this.cacheTimestamps.set(key, Date.now());
    }
    
    /**
     * Delete cache entry
     * @param {string} key Cache key
     */
    delete(key) {
        this.cache.delete(key);
        this.cacheTimestamps.delete(key);
    }
    
    /**
     * Clear all cache
     */
    clear() {
        this.cache.clear();
        this.cacheTimestamps.clear();
        this.pendingRequests.clear();
    }
    
    /**
     * Invalidate cache by pattern
     * @param {string} pattern Pattern to match (supports wildcards)
     */
    invalidate(pattern) {
        const regex = new RegExp('^' + pattern.replace(/\*/g, '.*') + '$');
        const keysToDelete = [];
        
        for (const key of this.cache.keys()) {
            if (regex.test(key)) {
                keysToDelete.push(key);
            }
        }
        
        keysToDelete.forEach(key => this.delete(key));
        return keysToDelete.length;
    }
    
    /**
     * Get cache statistics
     * @returns {Object} Cache stats
     */
    getStats() {
        const now = Date.now();
        let expiredCount = 0;
        let validCount = 0;
        
        for (const [key, timestamp] of this.cacheTimestamps.entries()) {
            if (now - timestamp >= this.defaultTTL) {
                expiredCount++;
            } else {
                validCount++;
            }
        }
        
        return {
            total: this.cache.size,
            valid: validCount,
            expired: expiredCount,
            pending: this.pendingRequests.size
        };
    }
    
    /**
     * Clean expired entries
     */
    cleanExpired() {
        const now = Date.now();
        const keysToDelete = [];
        
        for (const [key, timestamp] of this.cacheTimestamps.entries()) {
            if (now - timestamp >= this.defaultTTL) {
                keysToDelete.push(key);
            }
        }
        
        keysToDelete.forEach(key => this.delete(key));
        return keysToDelete.length;
    }
}

// Global instance
window.CacheService = new CacheService();

// Clean expired entries every minute
setInterval(() => {
    window.CacheService.cleanExpired();
}, 60000);

// Clear cache on page visibility change (when tab becomes visible, refresh stale data)
document.addEventListener('visibilitychange', () => {
    if (!document.hidden) {
        // Page became visible - clean expired cache
        window.CacheService.cleanExpired();
    }
});
