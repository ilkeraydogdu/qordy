/**
 * Notification Sounds and Audio Management
 * Provides sound notifications for orders, alerts, and success messages
 */

(function() {
    'use strict';

const NotificationSounds = {
    // Sound URLs - using CDN for reliability
    SOUND_URLS: {
        NEW_ORDER: 'https://assets.mixkit.co/active_storage/sfx/2869/2869-preview.mp3',
        ALERT: 'https://assets.mixkit.co/active_storage/sfx/2190/2190-preview.mp3',
        SUCCESS: 'https://assets.mixkit.co/active_storage/sfx/1435/1435-preview.mp3'
    },
    
    // Audio cache
    audioCache: {},
    
    /**
     * Play a sound notification
     * @param {string} type - Sound type: 'NEW_ORDER', 'ALERT', 'SUCCESS'
     */
    playSound: function(type) {
        try {
            const soundUrl = this.SOUND_URLS[type];
            if (!soundUrl) {
                console.warn('Unknown sound type:', type);
                return;
            }
            
            // Check if audio is cached
            if (!this.audioCache[type]) {
                this.audioCache[type] = new Audio(soundUrl);
                this.audioCache[type].volume = 0.5;
            }
            
            // Clone and play to allow overlapping sounds
            const audio = this.audioCache[type].cloneNode();
            audio.volume = 0.5;
            
            // Try to play audio - browsers may block autoplay without user interaction
            audio.play().catch(e => {
                // Silently handle autoplay blocking - this is expected browser behavior
                // Don't log to console to avoid unnecessary error reports
            });
        } catch (e) {
            console.error('Audio engine error:', e);
        }
    },
    
    /**
     * Play sound for new order notification
     */
    playNewOrder: function() {
        this.playSound('NEW_ORDER');
    },
    
    /**
     * Play sound for alert/issue notification
     */
    playAlert: function() {
        this.playSound('ALERT');
    },
    
    /**
     * Play sound for success notification
     */
    playSuccess: function() {
        this.playSound('SUCCESS');
    },
    
    /**
     * Initialize notification sounds (preload audio)
     */
    init: function() {
        // Preload audio files
        Object.keys(this.SOUND_URLS).forEach(type => {
            try {
                const audio = new Audio(this.SOUND_URLS[type]);
                audio.volume = 0;
                audio.load();
                this.audioCache[type] = audio;
            } catch (e) {
                console.warn('Failed to preload sound:', type, e);
            }
        });
    }
};

// Auto-initialize on page load
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => NotificationSounds.init());
} else {
    NotificationSounds.init();
}

// Export for use in other scripts
if (typeof window !== 'undefined') {
    window.NotificationSounds = NotificationSounds;
}

})();

