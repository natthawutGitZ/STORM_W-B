/**
 * STORM Admin Topbar — Interactive Behaviors
 * - Notification dropdown toggle
 * - Profile dropdown toggle
 * - Spotlight search (Ctrl+K / ⌘K)
 * - Keyboard navigation in search
 * - Click outside to close
 */

(function () {
    'use strict';

    // --- DOM References ---
    const searchBtn = document.getElementById('topbarSearchBtn');
    const searchModal = document.getElementById('topbarSearchModal');
    const searchInput = document.getElementById('topbarSearchInput');
    const searchResults = document.getElementById('topbarSearchResults');
    const notifBtn = document.getElementById('topbarNotifBtn');
    const notifDropdown = document.getElementById('topbarNotifDropdown');
    const profileBtn = document.getElementById('topbarProfileBtn');
    const profileDropdown = document.getElementById('topbarProfileDropdown');

    // --- Helper: Close all dropdowns ---
    function closeAllDropdowns() {
        if (notifDropdown) notifDropdown.classList.remove('open');
        if (profileDropdown) profileDropdown.classList.remove('open');
        if (profileBtn) profileBtn.classList.remove('open');
    }

    // --- Notification Dropdown ---
    if (notifBtn && notifDropdown) {
        notifBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            const isOpen = notifDropdown.classList.contains('open');
            closeAllDropdowns();
            if (!isOpen) {
                notifDropdown.classList.add('open');
            }
        });
    }

    // --- Profile Dropdown ---
    if (profileBtn && profileDropdown) {
        profileBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            const isOpen = profileDropdown.classList.contains('open');
            closeAllDropdowns();
            if (!isOpen) {
                profileDropdown.classList.add('open');
                profileBtn.classList.add('open');
            }
        });
    }

    // --- Click Outside to Close Dropdowns ---
    document.addEventListener('click', function (e) {
        // Notification dropdown
        if (notifDropdown && notifDropdown.classList.contains('open')) {
            if (!notifDropdown.contains(e.target) && !notifBtn.contains(e.target)) {
                notifDropdown.classList.remove('open');
            }
        }
        // Profile dropdown
        if (profileDropdown && profileDropdown.classList.contains('open')) {
            if (!profileDropdown.contains(e.target) && !profileBtn.contains(e.target)) {
                profileDropdown.classList.remove('open');
                profileBtn.classList.remove('open');
            }
        }
    });

    // --- Spotlight Search ---
    function openSearch() {
        if (!searchModal) return;
        searchModal.classList.add('open');
        document.body.style.overflow = 'hidden';
        if (searchInput) {
            searchInput.value = '';
            filterSearchItems('');
            setTimeout(function () {
                searchInput.focus();
            }, 100);
        }
        resetFocusIndex();
    }

    function closeSearch() {
        if (!searchModal) return;
        searchModal.classList.remove('open');
        document.body.style.overflow = '';
    }

    // Open search button
    if (searchBtn) {
        searchBtn.addEventListener('click', function (e) {
            e.preventDefault();
            openSearch();
        });
    }

    // Close search on overlay click
    if (searchModal) {
        const overlay = searchModal.querySelector('.storm-topbar__search-modal-overlay');
        if (overlay) {
            overlay.addEventListener('click', closeSearch);
        }
    }

    // Keyboard shortcut: Ctrl+K / ⌘K
    document.addEventListener('keydown', function (e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            if (searchModal && searchModal.classList.contains('open')) {
                closeSearch();
            } else {
                openSearch();
            }
        }
        // Escape to close
        if (e.key === 'Escape') {
            if (searchModal && searchModal.classList.contains('open')) {
                closeSearch();
            }
            closeAllDropdowns();
        }
    });

    // --- Search Filtering ---
    let focusIndex = -1;

    function getVisibleItems() {
        if (!searchResults) return [];
        return Array.from(searchResults.querySelectorAll('.storm-topbar__search-item:not(.hidden)'));
    }

    function resetFocusIndex() {
        focusIndex = -1;
        const items = getVisibleItems();
        items.forEach(function (item) {
            item.classList.remove('focused');
        });
    }

    function setFocusIndex(idx) {
        const items = getVisibleItems();
        items.forEach(function (item) {
            item.classList.remove('focused');
        });
        if (idx >= 0 && idx < items.length) {
            focusIndex = idx;
            items[idx].classList.add('focused');
            items[idx].scrollIntoView({ block: 'nearest' });
        }
    }

    function filterSearchItems(query) {
        if (!searchResults) return;
        const items = searchResults.querySelectorAll('.storm-topbar__search-item');
        const q = query.toLowerCase().trim();

        items.forEach(function (item) {
            if (q === '') {
                item.classList.remove('hidden');
                return;
            }
            const searchData = (item.getAttribute('data-search') || '') + ' ' + item.textContent;
            if (searchData.toLowerCase().includes(q)) {
                item.classList.remove('hidden');
            } else {
                item.classList.add('hidden');
            }
        });

        resetFocusIndex();

        // Show/hide group label
        const groups = searchResults.querySelectorAll('.storm-topbar__search-group');
        groups.forEach(function (group) {
            const visibleInGroup = group.querySelectorAll('.storm-topbar__search-item:not(.hidden)');
            const label = group.querySelector('.storm-topbar__search-group-label');
            if (label) {
                label.style.display = visibleInGroup.length === 0 ? 'none' : '';
            }
        });
    }

    if (searchInput) {
        searchInput.addEventListener('input', function () {
            filterSearchItems(this.value);
        });

        searchInput.addEventListener('keydown', function (e) {
            const items = getVisibleItems();

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                const nextIdx = focusIndex < items.length - 1 ? focusIndex + 1 : 0;
                setFocusIndex(nextIdx);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                const prevIdx = focusIndex > 0 ? focusIndex - 1 : items.length - 1;
                setFocusIndex(prevIdx);
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (focusIndex >= 0 && focusIndex < items.length) {
                    const href = items[focusIndex].getAttribute('href');
                    if (href) window.location.href = href;
                }
            }
        });
    }

})();
