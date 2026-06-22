/**
 * STORM Admin Sidebar v3 — Interactive Behaviors
 * - Collapse/Expand toggle (persisted to localStorage)
 * - Sub-menu expand/collapse
 * - Mobile drawer overlay
 * - Auto-expand active parent sections
 */

(function () {
    'use strict';

    const STORAGE_KEY = 'storm_sidebar_collapsed';

    // --- DOM References ---
    const sidebar = document.getElementById('stormSidebar');
    const layout = document.querySelector('.storm-admin-layout');
    const collapseBtn = document.getElementById('sidebarCollapseBtn');
    const mobileToggle = document.getElementById('sidebarMobileToggle');
    const overlay = document.getElementById('sidebarOverlay');

    if (!sidebar || !layout) return;

    // --- Collapse / Expand ---
    function setCollapsed(collapsed, save) {
        if (collapsed) {
            sidebar.classList.add('collapsed');
            layout.classList.add('sidebar-collapsed');
        } else {
            sidebar.classList.remove('collapsed');
            layout.classList.remove('sidebar-collapsed');
        }
        if (save) {
            try {
                localStorage.setItem(STORAGE_KEY, collapsed ? '1' : '0');
            } catch (e) { /* localStorage unavailable */ }
        }
    }

    // Restore saved state (only on desktop)
    function restoreState() {
        if (window.innerWidth <= 768) return;
        try {
            const saved = localStorage.getItem(STORAGE_KEY);
            if (saved === '1') {
                setCollapsed(true, false);
            }
        } catch (e) { /* localStorage unavailable */ }
    }

    restoreState();

    // Toggle collapse button
    if (collapseBtn) {
        collapseBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            const isCollapsed = sidebar.classList.contains('collapsed');
            setCollapsed(!isCollapsed, true);
        });
    }

    // --- Sub-menu Toggle ---
    const parentItems = sidebar.querySelectorAll('[data-submenu]');

    parentItems.forEach(function (item) {
        item.addEventListener('click', function (e) {
            // If collapsed on desktop, don't toggle submenu
            if (sidebar.classList.contains('collapsed') && window.innerWidth > 768) {
                return;
            }

            e.preventDefault();
            const submenuId = item.getAttribute('data-submenu');
            const submenu = document.getElementById(submenuId);
            if (!submenu) return;

            const isOpen = submenu.classList.contains('open');

            // Close all other submenus
            sidebar.querySelectorAll('.storm-sidebar__submenu.open').forEach(function (menu) {
                if (menu.id !== submenuId) {
                    menu.classList.remove('open');
                    const trigger = sidebar.querySelector('[data-submenu="' + menu.id + '"]');
                    if (trigger) trigger.classList.remove('expanded');
                }
            });

            // Toggle this submenu
            if (isOpen) {
                submenu.classList.remove('open');
                item.classList.remove('expanded');
            } else {
                submenu.classList.add('open');
                item.classList.add('expanded');
            }
        });
    });

    // --- Auto-expand active parent ---
    function autoExpandActive() {
        const activeSubItem = sidebar.querySelector('.storm-sidebar__subitem.active');
        if (activeSubItem) {
            const parentSubmenu = activeSubItem.closest('.storm-sidebar__submenu');
            if (parentSubmenu) {
                parentSubmenu.classList.add('open');
                const trigger = sidebar.querySelector('[data-submenu="' + parentSubmenu.id + '"]');
                if (trigger) trigger.classList.add('expanded');
            }
        }
    }

    autoExpandActive();

    // --- Mobile Drawer ---
    function openMobile() {
        sidebar.classList.add('mobile-open');
        if (overlay) {
            overlay.style.display = 'block';
            // Force reflow for transition
            overlay.offsetHeight;
            overlay.classList.add('visible');
        }
        document.body.style.overflow = 'hidden';
    }

    function closeMobile() {
        sidebar.classList.remove('mobile-open');
        if (overlay) {
            overlay.classList.remove('visible');
            setTimeout(function () {
                if (!sidebar.classList.contains('mobile-open')) {
                    overlay.style.display = 'none';
                }
            }, 300);
        }
        document.body.style.overflow = '';
    }

    if (mobileToggle) {
        mobileToggle.addEventListener('click', function (e) {
            e.preventDefault();
            if (sidebar.classList.contains('mobile-open')) {
                closeMobile();
            } else {
                openMobile();
            }
        });
    }

    if (overlay) {
        overlay.addEventListener('click', function () {
            closeMobile();
        });
    }

    // Close mobile on escape key
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && sidebar.classList.contains('mobile-open')) {
            closeMobile();
        }
    });

    // Close mobile drawer on window resize to desktop
    window.addEventListener('resize', function () {
        if (window.innerWidth > 768 && sidebar.classList.contains('mobile-open')) {
            closeMobile();
        }
    });

    // --- Prevent navigation on parent items (they toggle submenu) ---
    // Direct nav links (without submenu) should work normally — handled by <a href>
    // Parent items (with data-submenu) are <button> elements, so no href to prevent

})();
