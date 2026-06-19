/**
 * Admin Layout Initialization
 * Handles layout-specific initialization and loading state management
 * Optimized for non-blocking execution
 */

(function() {
    'use strict';
    
    // Prevent multiple initializations
    if (window._adminLayoutInitInitialized) {
        return;
    }
    window._adminLayoutInitInitialized = true;
    
    /**
     * Verify admin scripts are loaded
     */
    function verifyScriptsLoaded() {
        // Track script loading
        if (!window.adminScriptsLoaded) {
            window.adminScriptsLoaded = {
                mobileMenu: false,
                navigation: false,
                checked: false
            };
        }
        
        // Check if admin-mobile-menu.js loaded
        window.adminScriptsLoaded.mobileMenu = (
            typeof window.toggleMobileNav === 'function' ||
            typeof window.initMobileMenu === 'function' ||
            document.getElementById('mobile-nav-overlay') !== null
        );
        
        // Check if admin-navigation.js loaded
        window.adminScriptsLoaded.navigation = (
            typeof window.toggleDropdown === 'function' ||
            typeof window.resetNavigationInitialization === 'function' ||
            typeof window.initializeNavigationSafely === 'function'
        );
        
        window.adminScriptsLoaded.checked = true;
        
        // Log status for debugging
        if (!window.adminScriptsLoaded.mobileMenu || !window.adminScriptsLoaded.navigation) {
            console.warn('Admin navigation scripts loading status:', window.adminScriptsLoaded);
        }
    }
    
    /**
     * Initialize navigation from PHP-rendered data
     * Improved with retry mechanism and better navigation render detection
     */
    function initNavigationFromPHP() {
        // Check if navigation is already loaded
        const navElement = document.getElementById('main-navigation');
        const mobileNavElement = document.getElementById('mobile-navigation');
        
        if (!navElement && !mobileNavElement) {
            return; // Navigation not rendered (restricted role)
        }
        
        // Check if navigation is actually rendered (has content)
        function hasNavigationContent(element) {
            if (!element) return false;
            const hasLinks = element.querySelectorAll('a[href]').length > 0;
            const hasButtons = element.querySelectorAll('button').length > 0;
            const hasDropdowns = element.querySelectorAll('.dropdown-group').length > 0;
            return hasLinks || hasButtons || hasDropdowns;
        }
        
        // Check if navigation is rendered
        const isNavRendered = navElement && (
            navElement.getAttribute('data-navigation-loaded') === 'true' ||
            hasNavigationContent(navElement)
        );
        const isMobileNavRendered = mobileNavElement && (
            mobileNavElement.getAttribute('data-navigation-loaded') === 'true' ||
            hasNavigationContent(mobileNavElement)
        );
        
        // Ensure flags are set if navigation has content
        if (navElement && hasNavigationContent(navElement) && navElement.getAttribute('data-navigation-loaded') !== 'true') {
            navElement.setAttribute('data-navigation-loaded', 'true');
        }
        if (mobileNavElement && hasNavigationContent(mobileNavElement) && mobileNavElement.getAttribute('data-navigation-loaded') !== 'true') {
            mobileNavElement.setAttribute('data-navigation-loaded', 'true');
        }
        
        // Wait for admin-navigation.js to load and initialize
        function waitForScriptsAndInit(attempts) {
            attempts = attempts || 0;
            const maxAttempts = 30; // Maximum 3 seconds (30 * 100ms)
            
            // Check if admin-navigation.js loaded
            const scriptsLoaded = typeof window.initializeNavigationSafely === 'function';
            
            // Re-check navigation render status
            const navEl = document.getElementById('main-navigation');
            const mobileNavEl = document.getElementById('mobile-navigation');
            const navRendered = navEl && (
                navEl.getAttribute('data-navigation-loaded') === 'true' ||
                hasNavigationContent(navEl)
            );
            const mobileNavRendered = mobileNavEl && (
                mobileNavEl.getAttribute('data-navigation-loaded') === 'true' ||
                hasNavigationContent(mobileNavEl)
            );
            
            if (scriptsLoaded && (navRendered || mobileNavRendered)) {
                // Scripts loaded and navigation rendered, initialize
                try {
                    // Reset initialization flag to allow re-initialization
                    if (typeof window.resetNavigationInitialization === 'function') {
                        window.resetNavigationInitialization();
                    }
                    window.initializeNavigationSafely();
                } catch (e) {
                    console.error('Error in initializeNavigationSafely:', e);
                }
            } else if (attempts < maxAttempts) {
                // Scripts not loaded yet or navigation not rendered, wait a bit more
                setTimeout(function() {
                    waitForScriptsAndInit(attempts + 1);
                }, 100);
            } else {
                // Timeout - try to initialize anyway if scripts are loaded
                if (scriptsLoaded) {
                    try {
                        if (typeof window.resetNavigationInitialization === 'function') {
                            window.resetNavigationInitialization();
                        }
                        window.initializeNavigationSafely();
                    } catch (e) {
                        console.error('Error in initializeNavigationSafely (timeout):', e);
                    }
                }
            }
        }
        
        // Start waiting for scripts
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                waitForScriptsAndInit(0);
            }, { once: true });
        } else {
            waitForScriptsAndInit(0);
        }
        
        // Also check on window load as fallback
        window.addEventListener('load', function() {
            const navEl = document.getElementById('main-navigation');
            const mobileNavEl = document.getElementById('mobile-navigation');
            const navRendered = navEl && (
                navEl.getAttribute('data-navigation-loaded') === 'true' ||
                hasNavigationContent(navEl)
            );
            const mobileNavRendered = mobileNavEl && (
                mobileNavEl.getAttribute('data-navigation-loaded') === 'true' ||
                hasNavigationContent(mobileNavEl)
            );
            
            if ((navRendered || mobileNavRendered) && typeof window.initializeNavigationSafely === 'function') {
                try {
                    if (typeof window.resetNavigationInitialization === 'function') {
                        window.resetNavigationInitialization();
                    }
                    window.initializeNavigationSafely();
                } catch (e) {
                    console.error('Error in initializeNavigationSafely (load):', e);
                }
            }
        }, { once: true });
    }
    
    /**
     * Loading State Manager
     * Prevents pages from getting stuck in loading state
     */
    function initLoadingStateManager() {
        // Use requestIdleCallback if available for non-blocking execution
        const initFunction = function() {
            try {
                // Set a timeout to clear any stuck loading states
                var loadingTimeout = setTimeout(function() {
                    try {
                        // Use specific selectors to avoid performance issues
                        var specificSelectors = [
                            '#loadingIndicator',
                            '.loading-indicator',
                            '[data-loading="true"]'
                        ];
                        
                        var loadingElements = [];
                        specificSelectors.forEach(function(selector) {
                            try {
                                var elements = document.querySelectorAll(selector);
                                if (elements && elements.length > 0) {
                                    // Limit to prevent blocking
                                    var maxElements = Math.min(elements.length, 10);
                                    for (var i = 0; i < maxElements; i++) {
                                        loadingElements.push(elements[i]);
                                    }
                                }
                            } catch (e) {
                                // Ignore selector errors
                            }
                        });
                        
                        // Process elements with limit
                        var maxProcess = 10;
                        var processed = 0;
                        
                        loadingElements.forEach(function(el) {
                            if (processed >= maxProcess) return;
                            
                            try {
                                if (el && el.classList && !el.classList.contains('hidden')) {
                                    var startTime = el.getAttribute('data-loading-start');
                                    if (!startTime) {
                                        el.setAttribute('data-loading-start', Date.now());
                                    } else if (Date.now() - parseInt(startTime) > 30000) {
                                        // Force hide if stuck for more than 30 seconds
                                        el.classList.add('hidden');
                                    }
                                    processed++;
                                }
                            } catch (e) {
                                // Ignore individual element errors
                            }
                        });
                    } catch (error) {
                        console.warn('Loading state manager error:', error);
                    }
                }, 30000); // 30 second timeout
                
                // Clear timeout when page is fully loaded
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', function() {
                        clearTimeout(loadingTimeout);
                    }, { once: true });
                } else {
                    clearTimeout(loadingTimeout);
                }
                
                // Also clear timeout when window loads
                window.addEventListener('load', function() {
                    clearTimeout(loadingTimeout);
                }, { once: true });
            } catch (error) {
                console.warn('Error initializing loading state manager:', error);
            }
        };
        
        // Defer initialization to avoid blocking
        if (typeof requestIdleCallback !== 'undefined') {
            requestIdleCallback(initFunction, { timeout: 1000 });
        } else {
            setTimeout(initFunction, 100);
        }
    }
    
    /**
     * Language change handler
     */
    function initLanguageChange() {
        // Make changeLanguage available globally if not already defined
        if (typeof window.changeLanguage === 'function') {
            return; // Already defined
        }
        
        window.changeLanguage = function(lang) {
            try {
                // Show loading indicator
                const buttons = document.querySelectorAll('[onclick*="changeLanguage"]');
                buttons.forEach(btn => {
                    btn.disabled = true;
                    btn.style.opacity = '0.5';
                });
                
                const baseUrl = (typeof window !== 'undefined' && window.appConfig) 
                    ? window.appConfig.getBaseUrl() 
                    : (window.BASE_URL || '');
                
                const csrfToken = window.CSRF_TOKEN || (typeof csrf_token !== 'undefined' ? csrf_token : '');
                
                fetch(baseUrl + '/api/change-language', {
                    method: 'POST',
                    headers: { 
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-Token': csrfToken
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ language: lang })
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        // Reload page to apply language changes
                        window.location.reload();
                    } else {
                        if (window.NotificationManager) {
                            window.NotificationManager.error('Dil değiştirilemedi: ' + (data.error || 'Bilinmeyen hata'));
                        }
                        // Re-enable buttons
                        buttons.forEach(btn => {
                            btn.disabled = false;
                            btn.style.opacity = '1';
                        });
                    }
                })
                .catch(error => {
                    console.error('Language change error:', error);
                    if (window.NotificationManager) {
                        window.NotificationManager.error('Dil değiştirme sırasında bir hata oluştu: ' + error.message);
                    }
                    // Re-enable buttons
                    buttons.forEach(btn => {
                        btn.disabled = false;
                        btn.style.opacity = '1';
                    });
                });
            } catch (error) {
                console.error('Error in changeLanguage:', error);
            }
        };
    }
    
    /**
     * Sidebar: Uzun şirket adı taşarsa metin kaydırma (sağa uzamaz, marquee)
     */
    function initSidebarDisplayNameScroll() {
        var wrap = document.getElementById('sidebar-display-name-wrap');
        var span = document.getElementById('sidebar-display-name');
        if (!wrap || !span) return;
        if (span.scrollWidth > wrap.clientWidth) {
            wrap.classList.add('scroll');
            var text = span.textContent || span.innerText || '';
            span.textContent = text + '\u00A0\u00A0\u00A0\u00A0 ' + text;
            span.classList.add('sidebar-title-marquee');
        }
    }
    
    /**
     * Initialize all layout functionality
     */
    function init() {
        initLoadingStateManager();
        initLanguageChange();
        initSidebarDisplayNameScroll();
        verifyScriptsLoaded();
        initNavigationFromPHP();
    }
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        // DOM already loaded, defer slightly
        setTimeout(init, 0);
    }
    
    // Also verify scripts after window load
    window.addEventListener('load', function() {
        setTimeout(verifyScriptsLoaded, 100);
    }, { once: true });
})();

