// QORDY Design System - Utilities

window.QordyUtils = {
    // Dark mode management
    DarkMode: {
        toggle: function() {
            document.body.classList.toggle('dark-mode');
            const isDark = document.body.classList.contains('dark-mode');
            localStorage.setItem('darkMode', isDark ? '1' : '0');
        },
        init: function() {
            const isDark = localStorage.getItem('darkMode') === '1';
            if (isDark) {
                document.body.classList.add('dark-mode');
            }
        }
    },
    
    // Toast notifications (if not already defined)
    toast: {
        success: function(title, message) {
            if (window.showToast) {
                window.showToast(message || title, 'success');
            } else {
                console.log('SUCCESS:', title, message);
            }
        },
        error: function(title, message) {
            if (window.showToast) {
                window.showToast(message || title, 'error');
            } else {
                console.error('ERROR:', title, message);
            }
        }
    }
};

// Initialize dark mode on page load
document.addEventListener('DOMContentLoaded', function() {
    if (window.QordyUtils && window.QordyUtils.DarkMode) {
        window.QordyUtils.DarkMode.init();
    }
});
