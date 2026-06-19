/**
 * Admin Layout Configuration
 * Critical configuration and functions that must be available immediately
 * Loaded before other scripts to ensure functions are available when needed
 */

(function() {
    'use strict';
    
    // EN ERKEN: Orijinal console metodlarını window'a kaydet (tüm override'lardan önce)
    // Bu, menu.php ve diğer script'lerin orijinal console metodlarına erişmesini sağlar
    if (typeof console !== 'undefined' && !window._originalConsoleMethods) {
        window._originalConsoleMethods = {
            error: console.error ? console.error.bind(console) : function() {},
            warn: console.warn ? console.warn.bind(console) : function() {},
            log: console.log ? console.log.bind(console) : function() {},
            info: console.info ? console.info.bind(console) : function() {},
            debug: console.debug ? console.debug.bind(console) : function() {}
        };
    }
    
    // Suppress Tailwind CDN production warning - MUST be before Tailwind loads
    (function() {
        if (typeof console !== 'undefined' && console.warn) {
            // Orijinal console.warn'ı kullan (window'dan al, eğer yoksa mevcut console.warn'ı kullan)
            const originalWarn = window._originalConsoleMethods ? window._originalConsoleMethods.warn : console.warn;
            console.warn = function(...args) {
                const message = args[0];
                if (message && typeof message === 'string' && (
                    message.includes('cdn.tailwindcss.com') || 
                    message.includes('should not be used in production')
                )) {
                    return; // Suppress Tailwind CDN warning
                }
                // Orijinal console.warn'ı çağır (her zaman görünür olmalı)
                originalWarn.apply(console, args);
            };
        }
    })();
    
    // Tailwind config to suppress warnings - set before Tailwind loads
    // This must be set before Tailwind CDN script loads
    if (typeof window.tailwind === 'undefined') {
        window.tailwind = { config: { corePlugins: { preflight: true } } };
    }
    
    // Get configuration from data attributes (set by PHP)
    // Wait for body to be available
    function initConfig() {
        const body = document.body;
        if (!body) {
            // Body not ready yet, wait a bit
            setTimeout(initConfig, 10);
            return;
        }
        
        const baseUrl = body.getAttribute('data-base-url') || window.location.origin;
        const websocketUrl = body.getAttribute('data-websocket-url');
        const websocketPort = parseInt(body.getAttribute('data-websocket-port')) || 8080;
        
        // Set global variables (WEBSOCKET_URL = proxy path /ws, used by realtime.js)
        window.BASE_URL = baseUrl;
        window.WEBSOCKET_PORT = websocketPort;
        if (websocketUrl) window.WEBSOCKET_URL = websocketUrl;
        
        // Tailwind config (set after Tailwind loads)
        function setTailwindConfig() {
            const tailwindObj = window.tailwind || (typeof tailwind !== 'undefined' ? tailwind : null);
            if (tailwindObj) {
                tailwindObj.config = {
                    theme: {
                        extend: {
                            fontFamily: { mono: ['Space Mono', 'monospace'] },
                            colors: {
                                primary: { 
                                    50: '#fff7ed', 100: '#ffedd5', 200: '#fed7aa', 300: '#fdba74', 
                                    400: '#fb923c', 500: '#f97316', 600: '#ea580c', 700: '#c2410c', 
                                    800: '#9a3412', 900: '#7c2d12' 
                                }
                            },
                            boxShadow: { 
                                'soft': '0 10px 40px -10px rgba(0,0,0,0.05)', 
                                'up': '0 -10px 40px -10px rgba(0,0,0,0.08)', 
                                'keypad': '0 4px 20px -2px rgba(0,0,0,0.05)' 
                            },
                            spacing: {
                                '0.5': '0.125rem',
                                '1': '0.25rem',
                                '1.5': '0.375rem',
                                '2': '0.5rem',
                                '2.5': '0.625rem',
                                '3': '0.75rem',
                                '3.5': '0.875rem',
                                '4': '1rem',
                                '5': '1.25rem',
                                '6': '1.5rem',
                                '8': '2rem',
                                '10': '2.5rem',
                                '12': '3rem',
                            },
                            fontSize: {
                                'xs': ['0.625rem', { lineHeight: '1rem' }],
                                'sm': ['0.6875rem', { lineHeight: '1.125rem' }],
                                'base': ['0.75rem', { lineHeight: '1.25rem' }],
                                'lg': ['0.875rem', { lineHeight: '1.375rem' }],
                                'xl': ['1rem', { lineHeight: '1.5rem' }],
                                '2xl': ['1.25rem', { lineHeight: '1.75rem' }],
                                '3xl': ['1.5rem', { lineHeight: '2rem' }],
                                '4xl': ['1.75rem', { lineHeight: '2.25rem' }],
                            }
                        }
                    }
                };
            } else {
                // Tailwind not loaded yet, wait a bit
                setTimeout(setTailwindConfig, 50);
            }
        }
        
        // Initialize Tailwind config setup
        
        // Set Tailwind config after a short delay to ensure Tailwind is loaded
        setTimeout(setTailwindConfig, 100);
    }
    
    // Initialize config when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initConfig);
    } else {
        initConfig();
    }
    
    // CRITICAL: Define toggleMobileNav() immediately so it is available when button is clicked
    // This ensures the function exists even if other scripts haven't loaded yet
    function portalMobileNavOverlayToBody() {
        const overlay = document.getElementById('mobile-nav-overlay');
        if (overlay && overlay.parentElement !== document.body) {
            document.body.appendChild(overlay);
        }
        return overlay;
    }

    if (typeof window.toggleMobileNav !== 'function') {
        window.toggleMobileNav = function() {
            try {
                const overlay = portalMobileNavOverlayToBody();
                if (!overlay) return;
                
                const isMobile = window.innerWidth < 1024;
                if (!isMobile) {
                    overlay.classList.add('hidden');
                    overlay.style.removeProperty('display');
                    overlay.style.removeProperty('visibility');
                    overlay.setAttribute('aria-hidden', 'true');
                    document.body.style.overflow = '';
                    document.body.style.position = '';
                    document.body.style.top = '';
                    document.body.style.width = '';
                    document.body.removeAttribute('data-scroll-y');
                    return;
                }
                
                const isHidden = overlay.classList.contains('hidden') || overlay.style.display === 'none';
                if (isHidden) {
                    overlay.classList.remove('hidden');
                    overlay.style.removeProperty('display');
                    overlay.style.removeProperty('visibility');
                    overlay.setAttribute('aria-hidden', 'false');
                    if (isMobile) {
                        const scrollY = window.scrollY || window.pageYOffset || document.documentElement.scrollTop;
                        document.body.style.position = 'fixed';
                        document.body.style.top = `-${scrollY}px`;
                        document.body.style.width = '100%';
                        document.body.style.overflow = 'hidden';
                        document.body.setAttribute('data-scroll-y', scrollY.toString());
                    }
                    void overlay.offsetHeight;
                } else {
                    overlay.classList.add('hidden');
                    overlay.style.removeProperty('display');
                    overlay.style.removeProperty('visibility');
                    overlay.setAttribute('aria-hidden', 'true');
                    if (isMobile) {
                        const scrollY = document.body.getAttribute('data-scroll-y') || '0';
                        document.body.style.position = '';
                        document.body.style.top = '';
                        document.body.style.width = '';
                        document.body.style.overflow = '';
                        document.body.removeAttribute('data-scroll-y');
                        // Use requestAnimationFrame for smooth scroll restoration
                        requestAnimationFrame(function() {
                            window.scrollTo(0, parseInt(scrollY) || 0);
                        });
                    }
                }
            } catch (error) {
                console.error('Error in toggleMobileNav:', error);
            }
        };
    }
    
    // CRITICAL: Define toggleDropdown() immediately for onclick handlers
    // This ensures dropdowns work even if admin-navigation.js has not loaded yet
    if (typeof window.toggleDropdown !== 'function') {
        window.toggleDropdown = function(dropdownId) {
            try {
                const dropdown = document.getElementById('dropdown-' + dropdownId);
                const chevron = document.getElementById('chevron-' + dropdownId);
                
                if (!dropdown) {
                    console.warn('Dropdown not found:', 'dropdown-' + dropdownId);
                    return;
                }
                
                // Check if dropdown is open
                const currentHeight = dropdown.style.maxHeight;
                const isOpen = currentHeight && 
                              currentHeight !== '0px' && 
                              currentHeight !== '0' &&
                              currentHeight !== 'none' &&
                              (currentHeight === '1000px' || parseFloat(currentHeight) > 0);
                
                // Check if this dropdown has an active item
                const activeLink = dropdown.querySelector('a.bg-slate-100, a.border-orange-500, a[class*="border-orange"]');
                const parentButton = dropdown.closest('.dropdown-group')?.querySelector('button');
                const isParentActive = parentButton && (
                    parentButton.classList.contains('bg-slate-900') || 
                    parentButton.classList.contains('text-white')
                );
                const hasActiveItem = activeLink !== null || isParentActive;
                
                if (isOpen && !hasActiveItem) {
                    // Close if open and doesn't have active item
                    dropdown.style.maxHeight = '0';
                    if (chevron) chevron.classList.remove('rotate-180');
                } else if (!isOpen) {
                    // Open if closed - use requestAnimationFrame for smooth animation
                    requestAnimationFrame(function() {
                        const tempHeight = dropdown.style.maxHeight;
                        dropdown.style.maxHeight = 'none';
                        const scrollHeight = dropdown.scrollHeight;
                        dropdown.style.maxHeight = tempHeight;
                        const calculatedHeight = scrollHeight + 20;
                        dropdown.style.maxHeight = calculatedHeight + 'px';
                        if (chevron) chevron.classList.add('rotate-180');
                    });
                }
                // If already open and has active item, keep it open (do nothing)
            } catch (error) {
                console.error('Error in toggleDropdown:', error);
            }
        };
    }
})();
