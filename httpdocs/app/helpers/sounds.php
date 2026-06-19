<?php
/**
 * Sound Notification Helper Functions
 * Provides sound notifications similar to React playSound() function
 */

if (!function_exists('playSound')) {
    /**
     * Play a sound notification
     * @param string $type - Sound type: 'NEW_ORDER', 'ALERT', 'SUCCESS'
     * @return string - JavaScript code to play sound
     */
    function playSound($type = 'SUCCESS') {
        $soundUrls = [
            'NEW_ORDER' => 'https://assets.mixkit.co/active_storage/sfx/2869/2869-preview.mp3',
            'ALERT' => 'https://assets.mixkit.co/active_storage/sfx/2190/2190-preview.mp3',
            'SUCCESS' => 'https://assets.mixkit.co/active_storage/sfx/1435/1435-preview.mp3'
        ];
        
        $url = $soundUrls[$type] ?? $soundUrls['SUCCESS'];
        
        // Return JavaScript code that can be embedded in HTML
        return "
        <script>
        (function() {
            try {
                const audio = new Audio('{$url}');
                audio.volume = 0.5;
                audio.play().catch(e => console.log('Audio play blocked or failed:', e));
            } catch (e) {
                console.error('Audio engine error:', e);
            }
        })();
        </script>";
    }
}

if (!function_exists('getSoundScript')) {
    /**
     * Get JavaScript function for playing sounds
     * This should be included once in the page
     * @return string - JavaScript code
     */
    function getSoundScript() {
        return "
        <script>
        window.playSound = function(type) {
            const soundUrls = {
                'NEW_ORDER': 'https://assets.mixkit.co/active_storage/sfx/2869/2869-preview.mp3',
                'ALERT': 'https://assets.mixkit.co/active_storage/sfx/2190/2190-preview.mp3',
                'SUCCESS': 'https://assets.mixkit.co/active_storage/sfx/1435/1435-preview.mp3'
            };
            
            try {
                const url = soundUrls[type] || soundUrls['SUCCESS'];
                const audio = new Audio(url);
                audio.volume = 0.5;
                audio.play().catch(e => console.log('Audio play blocked or failed:', e));
            } catch (e) {
                console.error('Audio engine error:', e);
            }
        };
        </script>";
    }
}

