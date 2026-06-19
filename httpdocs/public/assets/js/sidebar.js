/**
 * Zone / ops sidebar — mobile drawer with body portaling (avoids overflow:hidden clipping).
 */
(function () {
    'use strict';

    if (window._qordySidebarInitialized) {
        return;
    }
    window._qordySidebarInitialized = true;

    var Z_SIDEBAR = 10001;
    var Z_OVERLAY = 10000;

    function isDesktop() {
        return window.innerWidth >= 1024;
    }

    function getSidebar(sidebarId) {
        var id = sidebarId || 'zone-sidebar';
        return document.getElementById(id);
    }

    function getOverlay() {
        return document.getElementById('sidebar-overlay');
    }

    /** Move drawer + backdrop to <body> so fixed positioning is not clipped by embed shells. */
    function portalOpsSidebar(sidebar, overlay) {
        if (sidebar && sidebar.parentElement !== document.body) {
            if (!sidebar.dataset.qordySidebarParent) {
                sidebar.dataset.qordySidebarParent = sidebar.parentElement
                    ? sidebar.parentElement.id || 'qordy-ops-parent'
                    : 'qordy-ops-parent';
            }
            document.body.appendChild(sidebar);
        }
        if (overlay && overlay.parentElement !== document.body) {
            if (!overlay.dataset.qordyOverlayParent) {
                overlay.dataset.qordyOverlayParent = overlay.parentElement
                    ? overlay.parentElement.id || 'qordy-ops-parent'
                    : 'qordy-ops-parent';
            }
            document.body.appendChild(overlay);
        }
    }

    function applyMobileTransform(sidebar, open) {
        if (!sidebar || isDesktop()) {
            if (sidebar) {
                sidebar.style.transform = '';
            }
            return;
        }
        sidebar.style.transform = open ? 'translateX(0)' : 'translateX(-100%)';
    }

    function isSidebarOpen(sidebar) {
        if (!sidebar) {
            return false;
        }
        if (sidebar.classList.contains('open')) {
            return true;
        }
        if (isDesktop()) {
            return !sidebar.classList.contains('-translate-x-full');
        }
        var transform = window.getComputedStyle(sidebar).transform;
        if (transform && transform !== 'none') {
            if (transform.includes('-100%') || transform.includes('-280') || transform.includes('-320')) {
                return false;
            }
            if (transform.includes('matrix') && !transform.includes('-')) {
                return true;
            }
        }
        return !sidebar.classList.contains('-translate-x-full');
    }

    function setSidebarOpenState(sidebar, overlay, open) {
        var desktop = isDesktop();

        if (open) {
            if (!desktop) {
                portalOpsSidebar(sidebar, overlay);
            }
            sidebar.classList.add('open');
            sidebar.classList.remove('-translate-x-full');
            if (desktop) {
                sidebar.classList.add('lg:translate-x-0');
            }
            applyMobileTransform(sidebar, true);
            sidebar.style.zIndex = String(Z_SIDEBAR);

            if (overlay && !desktop) {
                overlay.classList.remove('hidden');
                overlay.classList.add('open');
                overlay.style.zIndex = String(Z_OVERLAY);
            }

            if (!desktop) {
                document.body.style.overflow = 'hidden';
            }
        } else {
            sidebar.classList.remove('open');
            sidebar.classList.add('-translate-x-full');
            if (desktop) {
                sidebar.classList.remove('lg:translate-x-0');
            }
            applyMobileTransform(sidebar, false);

            if (overlay) {
                overlay.classList.add('hidden');
                overlay.classList.remove('open');
            }

            document.body.style.overflow = '';
        }
    }

    window.toggleSidebar = function (sidebarId) {
        var sidebar = getSidebar(sidebarId);
        var overlay = getOverlay();

        if (!sidebar) {
            console.warn('Sidebar not found:', sidebarId || 'zone-sidebar');
            return;
        }

        setSidebarOpenState(sidebar, overlay, !isSidebarOpen(sidebar));
    };

    window.closeSidebar = function (sidebarId) {
        var sidebar = getSidebar(sidebarId);
        var overlay = getOverlay();
        if (sidebar) {
            setSidebarOpenState(sidebar, overlay, false);
        }
    };

    window.openSidebar = function (sidebarId) {
        var sidebar = getSidebar(sidebarId);
        var overlay = getOverlay();
        if (sidebar) {
            setSidebarOpenState(sidebar, overlay, true);
        }
    };

    function initSidebarState() {
        var sidebar = getSidebar();
        var overlay = getOverlay();

        if (!sidebar) {
            return;
        }

        if (isDesktop()) {
            sidebar.classList.remove('-translate-x-full', 'open');
            sidebar.classList.add('lg:translate-x-0');
            sidebar.style.transform = '';
            if (overlay) {
                overlay.classList.add('hidden');
                overlay.classList.remove('open');
            }
        } else {
            sidebar.classList.remove('lg:translate-x-0', 'open');
            sidebar.classList.add('-translate-x-full');
            applyMobileTransform(sidebar, false);
            if (overlay) {
                overlay.classList.add('hidden');
                overlay.classList.remove('open');
            }
        }
    }

    function initSidebar() {
        initSidebarState();

        var overlay = getOverlay();
        if (overlay && !overlay.dataset.qordyOverlayBound) {
            overlay.dataset.qordyOverlayBound = '1';
            overlay.addEventListener('click', function (e) {
                if (e.target === overlay) {
                    window.closeSidebar();
                }
            });
        }

        var resizeTimer;
        window.addEventListener('resize', function () {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(initSidebarState, 150);
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                var sidebar = getSidebar();
                if (sidebar && isSidebarOpen(sidebar)) {
                    window.closeSidebar();
                }
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSidebar);
    } else {
        initSidebar();
    }
})();
