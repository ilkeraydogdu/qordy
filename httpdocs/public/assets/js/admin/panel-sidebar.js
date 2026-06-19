/**
 * Business panel sidebar — single-open accordion; on load only the active-route section opens.
 */
(function () {
    'use strict';

    function setGroupOpen(group, open) {
        if (!group) return;
        var toggle = group.querySelector('.q-panel-nav-group__toggle');
        group.classList.toggle('is-open', open);
        if (toggle) {
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        }
    }

    function closeAllGroups(nav, exceptGroup) {
        nav.querySelectorAll('.q-panel-nav-group[data-section-id]').forEach(function (group) {
            if (exceptGroup && group === exceptGroup) {
                return;
            }
            setGroupOpen(group, false);
        });
    }

    function applyInitialState(nav) {
        var groups = nav.querySelectorAll('.q-panel-nav-group[data-section-id]');
        if (!groups.length) {
            return;
        }

        closeAllGroups(nav);

        var activeGroup = null;
        groups.forEach(function (group) {
            if (group.getAttribute('data-has-active') === '1') {
                activeGroup = group;
            }
        });

        if (activeGroup) {
            setGroupOpen(activeGroup, true);
        }
    }

    function bindGroup(group, nav) {
        var toggle = group.querySelector('.q-panel-nav-group__toggle');
        if (!toggle || toggle.getAttribute('data-panel-sidebar-bound') === '1') {
            return;
        }
        toggle.setAttribute('data-panel-sidebar-bound', '1');
        toggle.addEventListener('click', function () {
            var isOpen = group.classList.contains('is-open');
            if (isOpen) {
                setGroupOpen(group, false);
                return;
            }
            closeAllGroups(nav, group);
            setGroupOpen(group, true);
        });
    }

    function initPanelSidebarNav(nav) {
        if (!nav || !nav.classList.contains('q-biz-sidebar__nav')) {
            return;
        }

        var groups = nav.querySelectorAll('.q-panel-nav-group[data-section-id]');
        if (!groups.length) {
            return;
        }

        applyInitialState(nav);
        groups.forEach(function (group) {
            bindGroup(group, nav);
        });
    }

    function init() {
        initPanelSidebarNav(document.getElementById('main-navigation'));
        initPanelSidebarNav(document.getElementById('mobile-navigation'));
    }

    window.initBusinessPanelSidebar = init;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }

    document.addEventListener('qordy:navigation-loaded', init);

    function observeNav(navEl) {
        if (!navEl || typeof MutationObserver === 'undefined') {
            return;
        }
        var observer = new MutationObserver(function (mutations) {
            for (var i = 0; i < mutations.length; i++) {
                var m = mutations[i];
                if (
                    m.type === 'attributes' &&
                    m.attributeName === 'data-navigation-loaded' &&
                    navEl.getAttribute('data-navigation-loaded') === 'true'
                ) {
                    init();
                    return;
                }
                if (m.type === 'childList' && m.target === navEl) {
                    init();
                    return;
                }
            }
        });
        observer.observe(navEl, {
            attributes: true,
            childList: true,
            subtree: false,
        });
    }

    observeNav(document.getElementById('main-navigation'));
    observeNav(document.getElementById('mobile-navigation'));
})();
