/**
 * Admin Navigation Handler
 * Handles dropdown menus and navigation interactions
 * Uses event delegation for optimal performance
 */

(function() {
    'use strict';
    
    // Prevent multiple initializations
    if (window._adminNavigationInitialized) {
        return;
    }
    window._adminNavigationInitialized = true;
    
    // Cache for dropdown elements
    const dropdownCache = new Map();
    let navigationInitialized = false;
    
    /**
     * Close all desktop dropdowns
     * Expose globally for onclick handlers
     */
    window.closeAllDropdowns = function() {
        try {
            // Use cached selectors if available
            let dropdowns = dropdownCache.get('desktop-dropdowns');
            if (!dropdowns) {
                dropdowns = Array.from(document.querySelectorAll('[id^="dropdown-"]:not([id$="-mobile"])'));
                dropdownCache.set('desktop-dropdowns', dropdowns);
            }
            
            dropdowns.forEach(dropdown => {
                if (!document.contains(dropdown)) {
                    // Remove from cache if element no longer exists
                    dropdownCache.delete('desktop-dropdowns');
                    return;
                }
                dropdown.style.maxHeight = '0';
                const dropdownId = dropdown.id.replace('dropdown-', '');
                const chevron = document.getElementById(`chevron-${dropdownId}`);
                if (chevron) chevron.classList.remove('rotate-180');
            });
        } catch (error) {
            console.warn('Error closing dropdowns:', error);
        }
    };
    
    // Also create local function for backward compatibility
    function closeAllDropdowns() {
        return window.closeAllDropdowns();
    }
    
    /**
     * Toggle desktop dropdown
     * Enhance the inline version if it exists, otherwise define it
     */
    if (typeof window.toggleDropdown !== 'function') {
        // Define if not already defined inline
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
                    requestAnimationFrame(() => {
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
    } else {
        // Enhance existing inline version with better error handling and animation
        const originalToggle = window.toggleDropdown;
        window.toggleDropdown = function(dropdownId) {
            try {
                const result = originalToggle.apply(this, arguments);
                // Ensure smooth animation with requestAnimationFrame
                const dropdown = document.getElementById('dropdown-' + dropdownId);
                if (dropdown && dropdown.style.maxHeight && parseFloat(dropdown.style.maxHeight) > 0) {
                    // Dropdown is opening, ensure smooth animation
                    requestAnimationFrame(() => {
                        const chevron = document.getElementById('chevron-' + dropdownId);
                        if (chevron && !chevron.classList.contains('rotate-180')) {
                            chevron.classList.add('rotate-180');
                        }
                    });
                }
                return result;
            } catch (error) {
                console.error('Error in toggleDropdown:', error);
                // Fallback: try basic toggle
                const dropdown = document.getElementById('dropdown-' + dropdownId);
                if (dropdown) {
                    const isHidden = dropdown.style.maxHeight === '0' || dropdown.style.maxHeight === '0px';
                    if (isHidden) {
                        dropdown.style.maxHeight = dropdown.scrollHeight + 'px';
                        const chevron = document.getElementById('chevron-' + dropdownId);
                        if (chevron) chevron.classList.add('rotate-180');
                    } else {
                        dropdown.style.maxHeight = '0';
                        const chevron = document.getElementById('chevron-' + dropdownId);
                        if (chevron) chevron.classList.remove('rotate-180');
                    }
                }
            }
        };
    }
    
    // Ensure function is always available (prevent loss on page navigation)
    Object.defineProperty(window, 'toggleDropdown', {
        writable: true,
        configurable: true,
        enumerable: true
    });
    
    /**
     * Toggle mobile dropdown
     */
    window.toggleMobileDropdown = function(groupName) {
        try {
            const dropdown = document.getElementById('dropdown-' + groupName);
            if (!dropdown) {
                return;
            }
            
            const chevronId = groupName.replace('-mobile', '');
            const chevron = document.getElementById('chevron-' + chevronId);
            const isHidden = dropdown.classList.contains('hidden');
            
            if (isHidden) {
                // Open - close other mobile dropdowns first
                const mobileDropdowns = Array.from(document.querySelectorAll('[id^="dropdown-"][id$="-mobile"]'));
                mobileDropdowns.forEach(otherDropdown => {
                    if (otherDropdown !== dropdown) {
                        otherDropdown.classList.add('hidden');
                        const otherDropdownId = otherDropdown.id.replace('dropdown-', '').replace('-mobile', '');
                        const otherChevron = document.getElementById(`chevron-${otherDropdownId}`);
                        if (otherChevron) otherChevron.classList.remove('rotate-180');
                    }
                });
                
                dropdown.classList.remove('hidden');
                if (chevron) chevron.classList.add('rotate-180');
            } else {
                // Close
                dropdown.classList.add('hidden');
                if (chevron) chevron.classList.remove('rotate-180');
            }
        } catch (error) {
            console.warn('Error toggling mobile dropdown:', error);
        }
    };
    
    /**
     * Initialize desktop dropdowns
     */
    function initializeDropdowns() {
        try {
            const dropdownElements = document.querySelectorAll('[id^="dropdown-"]:not([id$="-mobile"])');
            
            if (!dropdownElements || dropdownElements.length === 0) {
                return;
            }
            
            // Cache dropdowns
            dropdownCache.set('desktop-dropdowns', Array.from(dropdownElements));
            
            dropdownElements.forEach(dropdownContent => {
                // Check if element is still in DOM
                if (!dropdownContent || !document.contains(dropdownContent)) {
                    return;
                }
                
                const initialState = dropdownContent.getAttribute('data-initial-state');
                const dropdownId = dropdownContent.id ? dropdownContent.id.replace('dropdown-', '') : '';
                
                if (!dropdownId) {
                    return;
                }
                
                const chevron = document.getElementById(`chevron-${dropdownId}`);
                
                // Check if dropdown has active item
                const activeLink = dropdownContent.querySelector('a.bg-slate-100, a.border-orange-500, a[class*="border-orange"]');
                const group = dropdownContent.closest('.dropdown-group');
                const parentButton = group ? group.querySelector('button') : null;
                const isParentActive = parentButton && (
                    parentButton.classList.contains('bg-slate-900') ||
                    parentButton.classList.contains('text-white') ||
                    parentButton.classList.contains('shadow-2xl')
                );
                
                const hasActiveItem = activeLink !== null || isParentActive;
                // Only open dropdown if it has an active item (not just initial state)
                // This prevents all dropdowns from being open on every page load
                const shouldBeOpen = hasActiveItem;
                
                if (shouldBeOpen) {
                    // Use requestAnimationFrame for non-blocking DOM manipulation
                    requestAnimationFrame(() => {
                        if (!dropdownContent || !document.contains(dropdownContent)) {
                            return;
                        }
                        
                        try {
                            const originalMaxHeight = dropdownContent.style.maxHeight || '';
                            dropdownContent.style.maxHeight = 'none';
                            const scrollHeight = dropdownContent.scrollHeight;
                            dropdownContent.style.maxHeight = originalMaxHeight;
                            dropdownContent.style.maxHeight = scrollHeight + 'px';
                            
                            if (chevron) {
                                chevron.classList.add('rotate-180');
                            }
                        } catch (error) {
                            console.warn('Error setting dropdown height:', error);
                        }
                    });
                } else {
                    if (!dropdownContent || !document.contains(dropdownContent)) {
                        return;
                    }
                    dropdownContent.style.maxHeight = '0';
                    if (chevron) {
                        chevron.classList.remove('rotate-180');
                    }
                }
            });
        } catch (error) {
            console.warn('Error in initializeDropdowns:', error);
        }
    }
    
    /**
     * Initialize mobile dropdowns
     */
    function initializeMobileDropdowns() {
        try {
            const mobileDropdowns = document.querySelectorAll('[id^="dropdown-"][id$="-mobile"]');
            
            mobileDropdowns.forEach(mobileDropdown => {
                if (!mobileDropdown || !document.contains(mobileDropdown)) {
                    return;
                }
                
                const subLinks = mobileDropdown.querySelectorAll('a');
                let hasActiveItem = false;
                
                // Limit iteration to prevent blocking
                const maxLinks = 50;
                let linkCount = 0;
                for (const link of subLinks) {
                    if (linkCount++ >= maxLinks) break;
                    
                    if (link && (
                        link.classList.contains('bg-slate-100') ||
                        link.classList.contains('border-orange-500') ||
                        link.classList.contains('border-l-4') ||
                        link.getAttribute('class')?.includes('border-orange')
                    )) {
                        hasActiveItem = true;
                        break;
                    }
                }
                
                // Check parent button
                const group = mobileDropdown.closest('.dropdown-group, [class*="dropdown"]');
                const parentButton = group ? group.querySelector('button') : null;
                const isParentActive = parentButton && (
                    parentButton.classList.contains('bg-slate-900') ||
                    parentButton.classList.contains('text-white')
                );
                
                if (hasActiveItem || isParentActive) {
                    mobileDropdown.classList.remove('hidden');
                    const dropdownId = mobileDropdown.id.replace('dropdown-', '').replace('-mobile', '');
                    const chevron = document.getElementById(`chevron-${dropdownId}`);
                    if (chevron) chevron.classList.add('rotate-180');
                }
            });
        } catch (error) {
            console.warn('Error in initializeMobileDropdowns:', error);
        }
    }
    
    /**
     * Reset navigation initialization
     * Expose globally for PHP to call
     */
    window.resetNavigationInitialization = function() {
        navigationInitialized = false;
        // Clear dropdown cache
        dropdownCache.clear();
        // Reset navigation loaded flag to force re-check
        const navElement = document.getElementById('main-navigation');
        if (navElement) {
            navElement.setAttribute('data-navigation-loaded', 'false');
        }
        const mobileNavElement = document.getElementById('mobile-navigation');
        if (mobileNavElement) {
            mobileNavElement.setAttribute('data-navigation-loaded', 'false');
        }
    };
    
    // Also create local function for backward compatibility
    function resetNavigationInitialization() {
        return window.resetNavigationInitialization();
    }
    
    /**
     * Check if navigation has actual content (not just flag)
     * @param {HTMLElement} navElement Navigation element
     * @return {boolean} True if navigation has content
     */
    function hasNavigationContent(navElement) {
        if (!navElement) return false;
        
        // Check for actual navigation items (links, buttons, dropdowns)
        const hasLinks = navElement.querySelectorAll('a[href]').length > 0;
        const hasButtons = navElement.querySelectorAll('button').length > 0;
        const hasDropdowns = navElement.querySelectorAll('.dropdown-group').length > 0;
        const hasItems = navElement.querySelectorAll('[class*="nav"], [class*="menu"]').length > 0;
        
        // Navigation is considered loaded if it has at least some content
        return hasLinks || hasButtons || hasDropdowns || hasItems;
    }
    
    /**
     * Initialize navigation safely
     * Expose globally for PHP to call
     */
    window.initializeNavigationSafely = function() {
        // Check if navigation elements exist
        const navElement = document.getElementById('main-navigation');
        const mobileNavElement = document.getElementById('mobile-navigation');
        
        if (!navElement && !mobileNavElement) {
            // Navigation not rendered (restricted role), skip initialization
            return;
        }
        
        // Check if navigation is actually loaded (has flag AND content)
        const isNavLoaded = navElement && (
            navElement.getAttribute('data-navigation-loaded') === 'true' ||
            hasNavigationContent(navElement)
        );
        const isMobileNavLoaded = mobileNavElement && (
            mobileNavElement.getAttribute('data-navigation-loaded') === 'true' ||
            hasNavigationContent(mobileNavElement)
        );
        
        // If navigation is not loaded yet, wait a bit
        if (!isNavLoaded && !isMobileNavLoaded) {
            // Wait for navigation to load (max 3 seconds with more aggressive checks)
            let waitCount = 0;
            const maxWaits = 30; // 30 * 100ms = 3 seconds
            const checkNavLoaded = () => {
                waitCount++;
                const navEl = document.getElementById('main-navigation');
                const mobileNavEl = document.getElementById('mobile-navigation');
                
                const navLoaded = navEl && (
                    navEl.getAttribute('data-navigation-loaded') === 'true' ||
                    hasNavigationContent(navEl)
                );
                const mobileLoaded = mobileNavEl && (
                    mobileNavEl.getAttribute('data-navigation-loaded') === 'true' ||
                    hasNavigationContent(mobileNavEl)
                );
                
                if (navLoaded || mobileLoaded || waitCount >= maxWaits) {
                    // Navigation loaded or timeout, proceed with initialization
                    if (!navigationInitialized) {
                        performInitialization();
                    }
                } else {
                    setTimeout(checkNavLoaded, 100);
                }
            };
            setTimeout(checkNavLoaded, 50); // Start checking sooner
            return;
        }
        
        // Navigation is loaded, proceed with initialization
        if (!navigationInitialized) {
            performInitialization();
        }
    };
    
    // Also create local function for backward compatibility
    function initializeNavigationSafely() {
        return window.initializeNavigationSafely();
    }
    
    /**
     * Ensure sidebar visibility is correct based on viewport size
     * Optimized single check - no double requestAnimationFrame needed
     */
    function ensureSidebarVisibility() {
        try {
            const sidebar = document.querySelector('aside.desktop-sidebar');
            if (!sidebar) {
                return; // Sidebar doesn't exist (restricted role or not rendered)
            }
            
            const isMobile = window.innerWidth < 1024;
            const isRestrictedRole = document.body.classList.contains('restricted-role-fullscreen');
            
            // Don't modify sidebar if it's for restricted roles
            if (isRestrictedRole) {
                if (sidebar.style.display !== 'none') {
                    sidebar.style.display = 'none';
                }
                return;
            }
            
            // On desktop (1024px+), ensure sidebar is visible
            if (!isMobile) {
                if (sidebar.style.display === 'none') {
                    sidebar.style.display = '';
                }
                if (!sidebar.classList.contains('lg:flex')) {
                    sidebar.classList.add('lg:flex');
                }
                // Check computed style once
                const computedStyle = window.getComputedStyle(sidebar);
                if (computedStyle.display === 'none') {
                    sidebar.style.display = 'flex';
                }
            } else {
                // On mobile, ensure it's hidden
                const computedStyle = window.getComputedStyle(sidebar);
                if (computedStyle.display !== 'none') {
                    sidebar.style.display = 'none';
                }
                if (sidebar.classList.contains('lg:flex')) {
                    sidebar.classList.remove('lg:flex');
                }
            }
        } catch (error) {
            console.warn('Error ensuring sidebar visibility:', error);
        }
    }
    
    // Expose globally for PHP to call
    window.ensureSidebarVisibility = ensureSidebarVisibility;
    
    /**
     * Perform actual initialization
     */
    function performInitialization() {
        try {
            // Clear cache before initialization
            dropdownCache.clear();
            ensureSidebarVisibility();
            initializeDropdowns();
            initializeMobileDropdowns();
            navigationInitialized = true;
        } catch (error) {
            console.warn('Error initializing navigation:', error);
        }
    }
    
    /**
     * Event delegation for dropdown clicks
     * Handles onclick="toggleDropdown('...')" via event delegation
     */
    function setupDropdownClickHandler() {
        // Use event delegation for dropdown buttons
        document.addEventListener('click', function(event) {
            try {
                // Check if clicked element is a dropdown button
                const button = event.target.closest('button[onclick*="toggleDropdown"]');
                if (button) {
                    // Extract dropdown ID from onclick attribute
                    const onclickAttr = button.getAttribute('onclick');
                    const match = onclickAttr.match(/toggleDropdown\(['"]([^'"]+)['"]\)/);
                    if (match && match[1]) {
                        event.preventDefault();
                        event.stopPropagation();

                        // Extract ID and pass to toggleDropdown
                        if (typeof window.toggleDropdown === 'function') {
                            window.toggleDropdown(match[1]);
                        }
                        return false;
                    }
                }
            } catch (error) {
                // Silent fail - no console spam
            }
        }, true); // Use capture phase
    }
    
    /**
     * Event delegation for click outside dropdowns
     */
    function setupClickOutsideHandler() {
        // Use event delegation - single listener for all clicks
        document.addEventListener('click', function(event) {
            try {
                const clickedGroup = event.target.closest('.dropdown-group');
                if (!clickedGroup) {
                    // Check if any dropdown has an active item before closing
                    let shouldKeepOpen = false;
                    const groups = document.querySelectorAll('.dropdown-group');
                    
                    // Limit iteration to prevent blocking
                    const maxGroups = 20;
                    let groupCount = 0;
                    for (const group of groups) {
                        if (groupCount++ >= maxGroups) break;
                        
                        const dropdownContent = group.querySelector('[id^="dropdown-"]:not([id$="-mobile"])');
                        if (dropdownContent) {
                            const activeLink = dropdownContent.querySelector('a.bg-slate-100, a.border-orange-500');
                            if (activeLink) {
                                shouldKeepOpen = true;
                                break;
                            }
                        }
                    }
                    
                    if (!shouldKeepOpen) {
                        if (typeof window.closeAllDropdowns === 'function') {
                            window.closeAllDropdowns();
                        }
                    }
                }
            } catch (error) {
                console.warn('Error in click outside handler:', error);
            }
        }, { passive: true });
    }
    
    // Initialize when DOM is ready - Single, optimized initialization
    function attemptInitialization() {
        ensureSidebarVisibility();
        setupDropdownClickHandler();
        setupClickOutsideHandler();
        initializeNavigationSafely();
    }
    
    // CRITICAL: Reset navigation flag on every page load to allow re-initialization
    // This ensures navigation works correctly when navigating between pages
    function resetOnPageLoad() {
        // Reset flag to allow re-initialization
        navigationInitialized = false;
        
        // Check if navigation is rendered
        const navElement = document.getElementById('main-navigation');
        if (navElement && navElement.getAttribute('data-navigation-loaded') === 'true') {
            // Navigation is rendered, initialize it
            setTimeout(function() {
                attemptInitialization();
            }, 50);
        } else {
            // Navigation not rendered yet, wait for it
            let checkCount = 0;
            const maxChecks = 30; // 3 seconds max
            const checkNav = function() {
                checkCount++;
                const navEl = document.getElementById('main-navigation');
                if (navEl && navEl.getAttribute('data-navigation-loaded') === 'true') {
                    attemptInitialization();
                } else if (checkCount < maxChecks) {
                    setTimeout(checkNav, 100);
                } else {
                    // Timeout, try anyway
                    attemptInitialization();
                }
            };
            setTimeout(checkNav, 50);
        }
    }
    
    // Single initialization point - reset and initialize on every page load
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            resetOnPageLoad();
        }, { once: true });
    } else {
        // DOM already loaded, reset and initialize immediately
        resetOnPageLoad();
    }
    
    // Fallback check on window load
    window.addEventListener('load', function() {
        if (!navigationInitialized) {
            ensureSidebarVisibility();
            initializeNavigationSafely();
        }
    }, { once: true });
    
    // CRITICAL: Re-initialize navigation on page navigation (back/forward, link clicks)
    // This ensures navigation works correctly when navigating between pages
    window.addEventListener('pageshow', function(event) {
        // Ensure sidebar visibility on page show
        ensureSidebarVisibility();
        
        // Ensure toggleDropdown is always available
        if (typeof window.toggleDropdown !== 'function') {
            // Re-define if lost - use the same logic as inline script
            window.toggleDropdown = function(dropdownId) {
                try {
                    const dropdown = document.getElementById('dropdown-' + dropdownId);
                    const chevron = document.getElementById('chevron-' + dropdownId);
                    
                    if (!dropdown) return;
                    
                    const currentHeight = dropdown.style.maxHeight;
                    const isOpen = currentHeight && 
                                  currentHeight !== '0px' && 
                                  currentHeight !== '0' &&
                                  currentHeight !== 'none' &&
                                  (currentHeight === '1000px' || parseFloat(currentHeight) > 0);
                    
                    const activeLink = dropdown.querySelector('a.bg-slate-100, a.border-orange-500, a[class*="border-orange"]');
                    const parentButton = dropdown.closest('.dropdown-group')?.querySelector('button');
                    const isParentActive = parentButton && (
                        parentButton.classList.contains('bg-slate-900') || 
                        parentButton.classList.contains('text-white')
                    );
                    const hasActiveItem = activeLink !== null || isParentActive;
                    
                    if (isOpen && !hasActiveItem) {
                        dropdown.style.maxHeight = '0';
                        if (chevron) chevron.classList.remove('rotate-180');
                    } else if (!isOpen) {
                        requestAnimationFrame(function() {
                            const tempHeight = dropdown.style.maxHeight;
                            dropdown.style.maxHeight = 'none';
                            const scrollHeight = dropdown.scrollHeight;
                            dropdown.style.maxHeight = tempHeight;
                            dropdown.style.maxHeight = scrollHeight + 20 + 'px';
                            if (chevron) chevron.classList.add('rotate-180');
                        });
                    }
                } catch (error) {
                    console.error('Error in toggleDropdown:', error);
                }
            };
        }
        
        // Check if this is a back/forward navigation (from cache)
        if (event.persisted) {
            resetNavigationInitialization();
        }
        
        // CRITICAL: Always reset and re-initialize on page navigation
        // This ensures dropdowns are properly initialized after page changes
        resetNavigationInitialization();
        
        // Wait a bit for DOM to be ready, then initialize
        setTimeout(function() {
            const navElement = document.getElementById('main-navigation');
            const isNavLoaded = navElement && navElement.getAttribute('data-navigation-loaded') === 'true';
            
            if (isNavLoaded) {
                initializeNavigationSafely();
            } else {
                // Wait a bit more for navigation to load
                setTimeout(function() {
                    initializeNavigationSafely();
                }, 200);
            }
        }, 100);
    });
    
    // CRITICAL: Reset navigation on admin link clicks
    // This ensures navigation is properly initialized when navigating to new pages
    document.addEventListener('click', function(e) {
        // Find the closest anchor tag
        const link = e.target.closest('a[href^="/admin"], a[href^="/pos"], a[href^="/waiter"], a[href^="/kitchen"]');
        if (link && !link.hasAttribute('data-no-reset')) {
            // Check if it's a navigation link (not external or special link)
            const href = link.getAttribute('href');
            if (href && !href.startsWith('#') && !href.startsWith('javascript:') && !link.hasAttribute('target')) {
                // Reset navigation before navigation
                resetNavigationInitialization();
            }
        }
    }, true); // Use capture phase to catch early
    
    // Listen for popstate (browser back/forward)
    window.addEventListener('popstate', function(event) {
        // Ensure sidebar visibility on popstate
        ensureSidebarVisibility();
        
        // Ensure toggleDropdown is available
        if (typeof window.toggleDropdown !== 'function') {
            // Re-define using same logic as pageshow
            const dropdownHandler = function(dropdownId) {
                const dropdown = document.getElementById('dropdown-' + dropdownId);
                const chevron = document.getElementById('chevron-' + dropdownId);
                if (!dropdown) return;
                
                const currentHeight = dropdown.style.maxHeight;
                const isOpen = currentHeight && currentHeight !== '0px' && currentHeight !== '0' && currentHeight !== 'none';
                
                if (isOpen) {
                    dropdown.style.maxHeight = '0';
                    if (chevron) chevron.classList.remove('rotate-180');
                } else {
                    requestAnimationFrame(function() {
                        dropdown.style.maxHeight = dropdown.scrollHeight + 20 + 'px';
                        if (chevron) chevron.classList.add('rotate-180');
                    });
                }
            };
            window.toggleDropdown = dropdownHandler;
        }
        
        resetNavigationInitialization();
        setTimeout(function() {
            initializeNavigationSafely();
        }, 100);
    });
    
    // Listen for navigation link clicks to reset initialization before navigation
    document.addEventListener('click', function(event) {
        const link = event.target.closest('a[href]');
        if (link && link.href && !link.href.startsWith('javascript:') && !link.href.startsWith('#')) {
            // Check if it's an internal navigation link
            try {
                const currentHost = window.location.host;
                const linkUrl = new URL(link.href, window.location.href);
                const linkHost = linkUrl.host;
                
                if (linkHost === currentHost || linkHost === '') {
                    // Reset navigation initialization before navigation
                    resetNavigationInitialization();
                }
            } catch (e) {
                // Invalid URL, ignore
            }
        }
    }, true);
    
    // Also listen for visibility change (tab switch)
    document.addEventListener('visibilitychange', function() {
        if (!document.hidden && !navigationInitialized) {
            initializeNavigationSafely();
        }
    });
    
    // Handle window resize to ensure sidebar visibility is correct
    let resizeTimeout;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(function() {
            ensureSidebarVisibility();
        }, 150); // Debounce resize events
    }, { passive: true });
    
    // Monitor navigation element for changes (MutationObserver) - Optimized
    if (typeof MutationObserver !== 'undefined') {
        let initTimeout;
        const navObserver = new MutationObserver(function(mutations) {
            // Only check if not already initialized
            if (navigationInitialized) return;
            
            // Check for data-navigation-loaded attribute changes
            const hasLoadedAttr = mutations.some(function(mutation) {
                return mutation.type === 'attributes' && 
                       mutation.attributeName === 'data-navigation-loaded';
            });
            
            if (hasLoadedAttr) {
                clearTimeout(initTimeout);
                initTimeout = setTimeout(function() {
                    if (!navigationInitialized) {
                        initializeNavigationSafely();
                    }
                }, 100);
            }
        });
        
        // Start observing when DOM is ready
        function startObserving() {
            const navElement = document.getElementById('main-navigation');
            const mobileNavElement = document.getElementById('mobile-navigation');
            
            if (navElement) {
                navObserver.observe(navElement, {
                    attributes: true,
                    attributeFilter: ['data-navigation-loaded']
                });
            }
            
            if (mobileNavElement) {
                navObserver.observe(mobileNavElement, {
                    attributes: true,
                    attributeFilter: ['data-navigation-loaded']
                });
            }
        }
        
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', startObserving, { once: true });
        } else {
            startObserving();
        }
    }
})();

