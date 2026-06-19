/**
 * Admin Mobile Menu Handler
 * Handles mobile navigation menu toggle and overlay functionality
 * Optimized for performance with event delegation and lazy initialization
 */

(function() {
    'use strict';
    
    // Prevent multiple initializations
    if (window._adminMobileMenuInitialized) {
        return;
    }
    window._adminMobileMenuInitialized = true;
    
    // Cache DOM elements
    let overlay = null;
    let mobileNavButton = null;
    
    /**
     * Get or cache overlay element
     */
    function getOverlay() {
        if (!overlay) {
            overlay = document.getElementById('mobile-nav-overlay');
        }
        return overlay;
    }
    
    /**
     * Get or cache mobile nav button
     */
    function getMobileNavButton() {
        if (!mobileNavButton) {
            mobileNavButton = document.getElementById('mobile-nav-toggle-button');
        }
        return mobileNavButton;
    }
    
    /**
     * Toggle mobile navigation overlay
     * This function must be available globally for onclick handlers
     * Enhance inline version if it exists, otherwise define it
     */
    if (typeof window.toggleMobileNav !== 'function') {
        // Define if not already defined inline
        window.toggleMobileNav = function() {
            try {
                portalMobileNavOverlay();
                const overlayEl = getOverlay();
                if (!overlayEl) {
                    return; // Silently fail if overlay doesn't exist
                }
                
                // Check if we're on mobile (viewport width < 1024px)
                const isMobile = window.innerWidth < 1024;
                
                if (!isMobile) {
                    // On desktop, always hide mobile overlay
                    overlayEl.classList.add('hidden');
                    overlayEl.style.removeProperty('display');
                    overlayEl.style.removeProperty('visibility');
                    overlayEl.setAttribute('aria-hidden', 'true');
                    // Restore body styles if they were changed
                    restoreBodyScroll();
                    return;
                }
                
                // On mobile, toggle hidden class
                const isHidden = overlayEl.classList.contains('hidden') || overlayEl.style.display === 'none';
                
                if (isHidden) {
                    // Show overlay
                    overlayEl.classList.remove('hidden');
                    overlayEl.style.removeProperty('display');
                    overlayEl.style.removeProperty('visibility');
                    overlayEl.setAttribute('aria-hidden', 'false');
                    restartDrawerAnimation(overlayEl);
                    syncMobileNavAria(true);
                    // Prevent body scroll when menu is open (only on mobile)
                    lockBodyScroll();
                    // Force reflow to ensure animation works
                    void overlayEl.offsetHeight;
                } else {
                    // Hide overlay
                    overlayEl.classList.add('hidden');
                    overlayEl.style.removeProperty('display');
                    overlayEl.style.removeProperty('visibility');
                    overlayEl.setAttribute('aria-hidden', 'true');
                    syncMobileNavAria(false);
                    // Restore body scroll (only on mobile)
                    restoreBodyScroll();
                }
            } catch (error) {
                console.error('Error in toggleMobileNav:', error);
            }
        };
    } else {
        // Enhance existing inline version with improved scroll handling
        const originalToggle = window.toggleMobileNav;
        window.toggleMobileNav = function() {
            try {
                portalMobileNavOverlay();
                const result = originalToggle.apply(this, arguments);
                
                const overlayEl = getOverlay();
                if (overlayEl) {
                    const isMobile = window.innerWidth < 1024;
                    const isHidden = overlayEl.classList.contains('hidden') || overlayEl.style.display === 'none';
                    syncMobileNavAria(isMobile && !isHidden);
                    
                    if (isMobile && !isHidden) {
                        lockBodyScroll();
                    } else if (isMobile && isHidden) {
                        restoreBodyScroll();
                    }
                }
                
                return result;
            } catch (error) {
                console.error('Error in enhanced toggleMobileNav:', error);
                // Fallback to original
                try {
                    return originalToggle.apply(this, arguments);
                } catch (e) {
                    console.error('Error in fallback toggleMobileNav:', e);
                }
            }
        };
    }
    
    // Ensure function is always available (prevent loss on page navigation)
    Object.defineProperty(window, 'toggleMobileNav', {
        writable: true,
        configurable: true,
        enumerable: true
    });
    
    /**
     * Move overlay to <body> so position:fixed covers full viewport (not clipped by .q-biz-layout__frame overflow).
     */
    function portalMobileNavOverlay() {
        var overlayEl = getOverlay();
        if (!overlayEl || overlayEl.parentElement === document.body) {
            return;
        }
        document.body.appendChild(overlayEl);
        overlay = overlayEl;
    }

    /**
     * Direct handler on the toggle button (fallback if document delegation is blocked).
     */
    function bindMobileNavToggleButton() {
        var btn = getMobileNavButton();
        if (!btn || btn.__qordyMobileNavBound) {
            return;
        }
        btn.__qordyMobileNavBound = true;
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            if (typeof window.toggleMobileNav === 'function') {
                window.toggleMobileNav();
            }
        }, false);
    }

    function syncMobileNavAria(isOpen) {
        var btn = getMobileNavButton();
        if (!btn) return;
        btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        btn.setAttribute('aria-controls', 'mobile-nav-overlay');
        document.body.classList.toggle('q-mobile-nav-open', !!isOpen);
    }

    function restartDrawerAnimation(overlayEl) {
        try {
            const drawer = overlayEl && overlayEl.querySelector('aside');
            if (!drawer || !drawer.classList.contains('animate-slide-right')) {
                return;
            }
            drawer.classList.remove('animate-slide-right');
            void drawer.offsetWidth;
            drawer.classList.add('animate-slide-right');
        } catch (error) {
            console.warn('Error restarting drawer animation:', error);
        }
    }

    /**
     * Lock body scroll (prevent scrolling when menu is open)
     */
    function lockBodyScroll() {
        try {
            const scrollY = window.scrollY || window.pageYOffset || document.documentElement.scrollTop;
            document.body.style.position = 'fixed';
            document.body.style.top = `-${scrollY}px`;
            document.body.style.width = '100%';
            document.body.style.overflow = 'hidden';
            // Store scroll position for restoration
            document.body.setAttribute('data-scroll-y', scrollY.toString());
            // Also store in sessionStorage as backup
            try {
                sessionStorage.setItem('mobile-nav-scroll-y', scrollY.toString());
            } catch (e) {
                // Ignore storage errors
            }
        } catch (error) {
            console.warn('Error locking body scroll:', error);
        }
    }
    
    /**
     * Restore body scroll (allow scrolling when menu is closed)
     */
    function restoreBodyScroll() {
        try {
            // Get scroll position from attribute or sessionStorage
            let scrollY = document.body.getAttribute('data-scroll-y');
            if (!scrollY) {
                try {
                    scrollY = sessionStorage.getItem('mobile-nav-scroll-y') || '0';
                } catch (e) {
                    scrollY = '0';
                }
            }
            
            // Restore body styles
            document.body.style.position = '';
            document.body.style.top = '';
            document.body.style.width = '';
            document.body.style.overflow = '';
            document.body.removeAttribute('data-scroll-y');
            
            // Restore scroll position
            const scrollPosition = parseInt(scrollY) || 0;
            // Use requestAnimationFrame for smooth restoration
            requestAnimationFrame(() => {
                window.scrollTo(0, scrollPosition);
            });
            
            // Clean up sessionStorage
            try {
                sessionStorage.removeItem('mobile-nav-scroll-y');
            } catch (e) {
                // Ignore storage errors
            }
        } catch (error) {
            console.warn('Error restoring body scroll:', error);
            // Fallback: just restore styles
            document.body.style.position = '';
            document.body.style.top = '';
            document.body.style.width = '';
            document.body.style.overflow = '';
            document.body.removeAttribute('data-scroll-y');
        }
    }
    
    /**
     * Initialize mobile menu functionality
     */
    function initMobileMenu() {
        try {
            portalMobileNavOverlay();
            bindMobileNavToggleButton();

            const overlayEl = getOverlay();
            if (overlayEl) {
                // Ensure overlay is closed on page load (CSS drives visibility)
                overlayEl.classList.add('hidden');
                overlayEl.style.removeProperty('display');
                overlayEl.style.removeProperty('visibility');
                overlayEl.setAttribute('aria-hidden', 'true');
                
                // Close mobile nav when clicking on any link inside (event delegation)
                // Use capture phase and check if listener already added
                if (!overlayEl.hasAttribute('data-listener-added')) {
                    overlayEl.addEventListener('click', function(e) {
                        try {
                            const link = e.target.closest('a[href]');
                            if (link && !overlayEl.classList.contains('hidden')) {
                                if (typeof window.toggleMobileNav === 'function') {
                                    // Small delay to allow navigation to start
                                    setTimeout(function() {
                                        window.toggleMobileNav();
                                    }, 100);
                                }
                            }
                        } catch (err) {
                            console.warn('Error in overlay click handler:', err);
                        }
                    }, true);
                    overlayEl.setAttribute('data-listener-added', 'true');
                }
            }
            
            // Backdrop only — toggle button uses direct bind (avoids double-toggle)
            if (!document.documentElement.hasAttribute('data-mobile-nav-delegation-added')) {
                document.addEventListener('click', function(e) {
                    try {
                        const backdrop = e.target.closest('[data-mobile-nav-backdrop="true"]');
                        if (backdrop && typeof window.toggleMobileNav === 'function') {
                            window.toggleMobileNav();
                        }
                    } catch (err) {
                        console.warn('Error in mobile nav delegation:', err);
                    }
                }, true);
                document.documentElement.setAttribute('data-mobile-nav-delegation-added', 'true');
            }
            
            // Close mobile nav on window resize to desktop
            let resizeTimer;
            const resizeHandler = function() {
                clearTimeout(resizeTimer);
                resizeTimer = setTimeout(function() {
                    const overlayEl = getOverlay();
                    if (overlayEl && window.innerWidth >= 1024) {
                        overlayEl.classList.add('hidden');
                        overlayEl.style.removeProperty('display');
                        overlayEl.style.removeProperty('visibility');
                        overlayEl.setAttribute('aria-hidden', 'true');
                        // Restore body styles using restore function
                        restoreBodyScroll();
                    }
                }, 250);
            };
            
            // Only add resize listener once
            if (!window._mobileNavResizeListenerAdded) {
                window.addEventListener('resize', resizeHandler, { passive: true });
                window._mobileNavResizeListenerAdded = true;
            }
        } catch (error) {
            console.error('Error initializing mobile menu:', error);
        }
    }
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initMobileMenu, { once: true });
    } else {
        // DOM already loaded, defer slightly to avoid blocking
        setTimeout(initMobileMenu, 0);
    }
    
    // CRITICAL: Re-initialize on page navigation to ensure functions are always available
    window.addEventListener('pageshow', function(event) {
        // Ensure toggleMobileNav is always available
        if (typeof window.toggleMobileNav !== 'function') {
            // Re-define if lost
            const overlayEl = getOverlay();
            if (overlayEl) {
                window.toggleMobileNav = function() {
                    try {
                        portalMobileNavOverlay();
                        const overlay = document.getElementById('mobile-nav-overlay');
                        if (!overlay) return;
                        
                        const isMobile = window.innerWidth < 1024;
                        const isHidden = overlay.classList.contains('hidden') || overlay.style.display === 'none';
                        
                        if (isHidden) {
                            overlay.classList.remove('hidden');
                            overlay.style.removeProperty('display');
                            overlay.style.removeProperty('visibility');
                            overlay.setAttribute('aria-hidden', 'false');
                            restartDrawerAnimation(overlay);
                            syncMobileNavAria(true);
                            if (isMobile) {
                                lockBodyScroll();
                            }
                            void overlay.offsetHeight;
                        } else {
                            overlay.classList.add('hidden');
                            overlay.style.removeProperty('display');
                            overlay.style.removeProperty('visibility');
                            overlay.setAttribute('aria-hidden', 'true');
                            syncMobileNavAria(false);
                            if (isMobile) {
                                restoreBodyScroll();
                            }
                        }
                    } catch (error) {
                        console.error('Error in toggleMobileNav:', error);
                    }
                };
            }
        }
        
        // Re-initialize mobile menu
        setTimeout(initMobileMenu, 50);
    });
    
    // Also handle popstate (browser back/forward)
    window.addEventListener('popstate', function(event) {
        setTimeout(function() {
            if (typeof window.toggleMobileNav !== 'function') {
                initMobileMenu();
            }
        }, 50);
    });
})();

