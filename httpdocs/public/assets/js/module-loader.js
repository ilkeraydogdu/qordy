/**
 * Module Loader
 * Dynamically loads ES6 modules and provides backward compatibility
 * This allows gradual migration from global functions to ES6 modules
 */

(function() {
    'use strict';

    // Module cache
    const moduleCache = {};

    /**
     * Load a module dynamically
     * @param {string} modulePath - Path to module
     * @returns {Promise} Module exports
     */
    async function loadModule(modulePath) {
        if (moduleCache[modulePath]) {
            return moduleCache[modulePath];
        }

        try {
            // Try to use dynamic import if available
            if (typeof import !== 'undefined') {
                const module = await import(modulePath);
                moduleCache[modulePath] = module;
                return module;
            } else {
                // Fallback: modules should be bundled or loaded via script tags
                console.warn('ES6 modules not supported. Using global Utils instead.');
                return null;
            }
        } catch (error) {
            console.warn(`Failed to load module ${modulePath}:`, error);
            return null;
        }
    }

    /**
     * Initialize module system
     * Makes modules available globally for backward compatibility
     */
    function initModuleSystem() {
        // If modules are available, expose them globally
        if (typeof window !== 'undefined') {
            // Try to load modules from /modules/ directory
            // Use AppConfig module if available, fallback to window variables
            const basePath = (typeof window !== 'undefined' && window.appConfig)
                ? window.appConfig.getBaseUrl()
                : (window.BASE_URL || '');
            const modulesPath = `${basePath}/assets/js/modules/`;

            // For now, we'll use the traditional Utils approach
            // ES6 modules will be loaded via build system or bundler
            // This file provides a bridge for gradual migration
        }
    }

    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initModuleSystem);
    } else {
        initModuleSystem();
    }

    // Export for use in other scripts
    window.ModuleLoader = {
        load: loadModule,
        init: initModuleSystem
    };
})();

