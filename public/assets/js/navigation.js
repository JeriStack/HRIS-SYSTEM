/**
 * Employee HRIS & Leave Management System
 * Production Navigation, Mobile Drawer Controller, Collapsed Tooltips & Active State Handler
 */

document.addEventListener('DOMContentLoaded', () => {
    initThemeManager();
    initMobileNavigation();
    initSidebarSectionToggles();
    initSidebarQuickFilter();
    initCollapsedSidebarTooltips();
    initKeyboardNavigation();
});

/**
 * Enterprise Theme Manager (Light & Dark Mode)
 */
function initThemeManager() {
    const savedTheme = localStorage.getItem('nexora_theme') || localStorage.getItem('hris_theme');
    const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
    const initialTheme = savedTheme || (prefersDark ? 'dark' : 'light');

    document.documentElement.setAttribute('data-theme', initialTheme);
    updateThemeButtons(initialTheme);

    const themeButtons = document.querySelectorAll('.theme-toggle-btn');
    themeButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            const currentTheme = document.documentElement.getAttribute('data-theme') || 'light';
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';

            document.documentElement.setAttribute('data-theme', newTheme);
            localStorage.setItem('nexora_theme', newTheme);
            try {
                localStorage.removeItem('hris_theme');
            } catch (err) {}
            updateThemeButtons(newTheme);
        });
    });
}

function updateThemeButtons(theme) {
    const sunSvg = '<svg class="svg-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>';
    const moonSvg = '<svg class="svg-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>';
    const themeButtons = document.querySelectorAll('.theme-toggle-btn');
    themeButtons.forEach(btn => {
        btn.setAttribute('aria-label', theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode');
        btn.setAttribute('title', theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode');
        btn.innerHTML = theme === 'dark' ? sunSvg : moonSvg;
    });
}

/**
 * Mobile Drawer Toggle & Desktop Sidebar Collapse Controller
 */
function initMobileNavigation() {
    const toggleButtons = document.querySelectorAll('#sidebarToggle, #mobileSidebarToggle, .sidebar-toggle, .header-toggle');
    const mobileCloseBtn = document.getElementById('mobileSidebarClose');
    const sidebar = document.querySelector('.app-sidebar');
    const shell = document.querySelector('.app-shell');

    const collapseIconSvg = '<svg class="svg-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="9" y1="3" x2="9" y2="21"></line><path d="m15 15-3-3 3-3"></path></svg>';
    const expandIconSvg = '<svg class="svg-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="9" y1="3" x2="9" y2="21"></line><path d="m13 9 3 3-3 3"></path></svg>';

    function syncSidebarToggleButtonState(isCollapsed) {
        document.documentElement.setAttribute('data-sidebar-collapsed', isCollapsed ? '1' : '0');

        toggleButtons.forEach(btn => {
            if (btn.id === 'mobileSidebarToggle') {
                return;
            }
            btn.classList.toggle('is-collapsed', isCollapsed);
            btn.setAttribute('aria-expanded', isCollapsed ? 'false' : 'true');
            const label = isCollapsed ? 'Expand sidebar (Ctrl+B)' : 'Minimize sidebar (Ctrl+B)';
            btn.setAttribute('aria-label', label);
            btn.setAttribute('title', label);
            btn.setAttribute('data-tooltip', label);
            btn.innerHTML = isCollapsed ? expandIconSvg : collapseIconSvg;
        });

        // Swap native title tooltips off while collapsed so custom accessible tooltip handles hover/focus cleanly
        if (sidebar) {
            sidebar.querySelectorAll('[data-tooltip]').forEach(item => {
                if (isCollapsed) {
                    if (item.hasAttribute('title') && !item.hasAttribute('data-native-title')) {
                        item.setAttribute('data-native-title', item.getAttribute('title') || '');
                    }
                    item.removeAttribute('title');
                } else if (item.hasAttribute('data-native-title')) {
                    item.setAttribute('title', item.getAttribute('data-native-title') || '');
                }
            });
        }
    }

    function toggleDesktopSidebar() {
        if (!shell || window.innerWidth < 768) {
            return;
        }
        const isCollapsed = shell.classList.toggle('sidebar-collapsed');
        localStorage.setItem('nexora_sidebar_collapsed', isCollapsed ? '1' : '0');
        syncSidebarToggleButtonState(isCollapsed);
    }

    // Expose keyboard toggle helper for initKeyboardNavigation
    window.__toggleDesktopSidebar = toggleDesktopSidebar;

    // Restore desktop collapsed preference
    if (shell && window.innerWidth >= 768) {
        const initiallyCollapsed = localStorage.getItem('nexora_sidebar_collapsed') === '1';
        shell.classList.toggle('sidebar-collapsed', initiallyCollapsed);
        syncSidebarToggleButtonState(initiallyCollapsed);
    }

    if (!sidebar) return;

    // Keep active item visible when sidebar has many links
    const activeNavItem = sidebar.querySelector('.sidebar-nav .nav-item.active');
    if (activeNavItem && typeof activeNavItem.scrollIntoView === 'function') {
        activeNavItem.scrollIntoView({ block: 'nearest' });
    }

    const appMainContainer = document.querySelector('.app-container');

    function setPageInert(isInert) {
        if (appMainContainer && 'inert' in appMainContainer) {
            appMainContainer.inert = Boolean(isInert);
        }
    }

    toggleButtons.forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            if (window.innerWidth >= 768) {
                toggleDesktopSidebar();
            } else {
                const isOpen = document.body.classList.toggle('sidebar-open');
                sidebar.classList.toggle('active', isOpen);
                btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                document.body.style.overflow = isOpen ? 'hidden' : '';
                setPageInert(isOpen);

                if (isOpen) {
                    const firstNavTarget = sidebar.querySelector('.nav-item.active, .nav-item, #mobileSidebarClose');
                    if (firstNavTarget && typeof firstNavTarget.focus === 'function') {
                        setTimeout(() => firstNavTarget.focus(), 40);
                    }
                }
            }
        });
    });

    if (mobileCloseBtn) {
        mobileCloseBtn.addEventListener('click', () => closeMobileDrawer(true));
    }

    // Close drawer when clicking outside the sidebar
    document.addEventListener('click', (e) => {
        if (document.body.classList.contains('sidebar-open')) {
            if (!sidebar.contains(e.target) && !e.target.closest('#sidebarToggle, #mobileSidebarToggle, .sidebar-toggle, .header-toggle')) {
                closeMobileDrawer(false);
            }
        }
    });

    // Close drawer on navigation click on mobile
    sidebar.querySelectorAll('.nav-item').forEach(link => {
        link.addEventListener('click', () => {
            if (window.innerWidth < 768) {
                closeMobileDrawer(false);
            }
        });
    });

    // Sync layout state cleanly when crossing mobile/desktop breakpoint
    window.addEventListener('resize', () => {
        if (window.innerWidth >= 768) {
            if (document.body.classList.contains('sidebar-open')) {
                closeMobileDrawer(false);
            }
            if (shell) {
                const shouldCollapse = localStorage.getItem('nexora_sidebar_collapsed') === '1';
                shell.classList.toggle('sidebar-collapsed', shouldCollapse);
                syncSidebarToggleButtonState(shouldCollapse);
            }
        } else if (shell) {
            syncSidebarToggleButtonState(false);
        }
    });
}

/**
 * Helper to close mobile drawer safely and optionally return focus to trigger
 */
function closeMobileDrawer(returnFocus) {
    const sidebar = document.querySelector('.app-sidebar');
    const appMainContainer = document.querySelector('.app-container');
    document.body.classList.remove('sidebar-open');
    document.body.style.overflow = '';
    if (sidebar) sidebar.classList.remove('active');
    if (appMainContainer && 'inert' in appMainContainer) {
        appMainContainer.inert = false;
    }

    const mobileToggle = document.getElementById('mobileSidebarToggle');
    const toggleButtons = document.querySelectorAll('#sidebarToggle, #mobileSidebarToggle, .sidebar-toggle, .header-toggle');
    toggleButtons.forEach(btn => {
        if (btn.id === 'mobileSidebarToggle' || window.innerWidth < 768) {
            btn.setAttribute('aria-expanded', 'false');
        }
    });

    if (returnFocus && mobileToggle && typeof mobileToggle.focus === 'function') {
        mobileToggle.focus();
    }
}

/**
 * Collapsible Sidebar Sections (Per-Role State Persistence, Keeps Active Section Open)
 */
function initSidebarSectionToggles() {
    const sidebar = document.getElementById('appSidebar');
    const shell = document.querySelector('.app-shell');
    if (!sidebar) return;

    const STORAGE_KEY = 'nexora_sidebar_collapsed_sections';
    const role = sidebar.getAttribute('data-role') || 'default';
    const groups = Array.from(sidebar.querySelectorAll('.nav-section-group[data-section-id]'));
    if (!groups.length) return;

    function readSavedState() {
        try {
            const raw = localStorage.getItem(STORAGE_KEY);
            const parsed = raw ? JSON.parse(raw) : {};
            return (parsed && typeof parsed === 'object') ? parsed : {};
        } catch (err) {
            return {};
        }
    }

    function writeSavedState(collapsedIds) {
        try {
            const all = readSavedState();
            all[role] = collapsedIds;
            localStorage.setItem(STORAGE_KEY, JSON.stringify(all));
        } catch (err) {}
    }

    function isDesktopCollapsed() {
        return Boolean(shell && window.innerWidth >= 768 && shell.classList.contains('sidebar-collapsed'));
    }

    function applyGroupPresentation(group, userCollapsed, forceShowItems) {
        const toggle = group.querySelector('.nav-section-toggle');
        const items = group.querySelector('.nav-section-items');
        if (!toggle || !items) return;

        const isExpanded = !userCollapsed;
        toggle.setAttribute('aria-expanded', isExpanded ? 'true' : 'false');
        group.classList.toggle('is-section-collapsed', userCollapsed);
        items.hidden = userCollapsed && !forceShowItems && !isDesktopCollapsed();
    }

    function syncAllSections(filterActive = false) {
        const savedMap = readSavedState();
        const savedCollapsed = Array.isArray(savedMap[role]) ? savedMap[role] : [];

        groups.forEach(group => {
            const sectionId = group.getAttribute('data-section-id') || '';
            const hasActivePage = Boolean(group.querySelector('[aria-current="page"], .nav-item.active'));
            const userCollapsed = !hasActivePage && savedCollapsed.includes(sectionId);
            applyGroupPresentation(group, userCollapsed, filterActive);
        });
    }

    groups.forEach(group => {
        const toggle = group.querySelector('.nav-section-toggle');
        const sectionId = group.getAttribute('data-section-id') || '';
        if (!toggle || !sectionId) return;

        toggle.addEventListener('click', () => {
            const currentlyExpanded = toggle.getAttribute('aria-expanded') !== 'false';
            const nextCollapsed = currentlyExpanded;

            const savedMap = readSavedState();
            const currentList = Array.isArray(savedMap[role]) ? savedMap[role] : [];
            const nextList = nextCollapsed
                ? Array.from(new Set([...currentList, sectionId]))
                : currentList.filter(id => id !== sectionId);

            writeSavedState(nextList);
            applyGroupPresentation(group, nextCollapsed, false);
        });
    });

    // Keep icon-rail mode showing all section links when desktop sidebar collapses/expands
    window.addEventListener('resize', () => syncAllSections(false));
    const sidebarObserver = shell && typeof MutationObserver === 'function'
        ? new MutationObserver(() => syncAllSections(Boolean(document.getElementById('sidebarNavFilter')?.value.trim())))
        : null;
    if (sidebarObserver && shell) {
        sidebarObserver.observe(shell, { attributes: true, attributeFilter: ['class'] });
    }

    window.__syncSidebarSections = syncAllSections;
    syncAllSections(false);
}

/**
 * Quick Navigation Filter ("/" shortcut, live filter, Enter-to-jump, Esc-to-clear)
 */
function initSidebarQuickFilter() {
    const sidebar = document.getElementById('appSidebar');
    const filterInput = document.getElementById('sidebarNavFilter');
    const clearBtn = document.getElementById('sidebarNavFilterClear');
    const emptyState = document.getElementById('sidebarSearchEmpty');
    const resetBtn = document.getElementById('sidebarSearchResetBtn');
    const shortcutHint = sidebar ? sidebar.querySelector('.sidebar-search-shortcut') : null;
    if (!sidebar || !filterInput) return;

    const groups = Array.from(sidebar.querySelectorAll('.nav-section-group'));
    const navItems = Array.from(sidebar.querySelectorAll('.sidebar-nav .nav-item'));

    function applyFilter() {
        const query = filterInput.value.trim().toLowerCase();
        const hasQuery = query !== '';

        if (clearBtn) {
            clearBtn.hidden = !hasQuery;
        }
        if (shortcutHint) {
            shortcutHint.hidden = hasQuery;
        }

        if (typeof window.__syncSidebarSections === 'function') {
            window.__syncSidebarSections(hasQuery);
        }

        let visibleCount = 0;

        navItems.forEach(item => {
            const labelEl = item.querySelector('.nav-item-label');
            const labelText = (labelEl ? labelEl.textContent : item.textContent || '').toLowerCase();
            const keywords = (item.getAttribute('data-keywords') || '').toLowerCase();
            const isMatch = !hasQuery || labelText.includes(query) || keywords.includes(query);

            const rowParent = item.closest('form') || item;
            rowParent.hidden = !isMatch;
            if (isMatch) {
                visibleCount++;
            }
        });

        groups.forEach(group => {
            const groupItems = Array.from(group.querySelectorAll('.nav-item'));
            const anyVisible = groupItems.some(item => !(item.closest('form') || item).hidden);
            group.hidden = hasQuery && !anyVisible;
        });

        if (emptyState) {
            emptyState.hidden = !(hasQuery && visibleCount === 0);
        }
    }

    function clearFilter(focusInput = false) {
        filterInput.value = '';
        applyFilter();
        if (focusInput) {
            filterInput.focus();
        }
    }

    filterInput.addEventListener('input', applyFilter);

    filterInput.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            if (filterInput.value !== '') {
                e.preventDefault();
                e.stopPropagation();
                clearFilter(true);
            }
            return;
        }

        if (e.key === 'Enter') {
            const firstMatch = navItems.find(item => !(item.closest('form') || item).hidden);
            if (firstMatch) {
                e.preventDefault();
                firstMatch.click();
            }
        }
    });

    if (clearBtn) {
        clearBtn.addEventListener('click', () => clearFilter(true));
    }

    if (resetBtn) {
        resetBtn.addEventListener('click', () => clearFilter(true));
    }
}

/**
 * Accessible floating tooltips for collapsed desktop sidebar items (WCAG 1.4.13 compliant)
 */
function initCollapsedSidebarTooltips() {
    const sidebar = document.getElementById('appSidebar');
    const shell = document.querySelector('.app-shell');
    if (!sidebar || !shell) return;

    const tooltip = document.createElement('div');
    tooltip.id = 'sidebarTooltip';
    tooltip.className = 'sidebar-tooltip';
    tooltip.setAttribute('role', 'tooltip');
    if (Object.hasOwn(HTMLElement.prototype, 'popover')) {
        tooltip.setAttribute('popover', 'manual');
    }
    tooltip.hidden = true;
    document.body.appendChild(tooltip);

    let activeTrigger = null;

    function isDesktopCollapsed() {
        return window.innerWidth >= 768 && shell.classList.contains('sidebar-collapsed');
    }

    function hideTooltip() {
        if (activeTrigger) {
            activeTrigger.removeAttribute('aria-describedby');
            activeTrigger = null;
        }
        if (typeof tooltip.hidePopover === 'function' && tooltip.matches(':popover-open')) {
            try {
                tooltip.hidePopover();
            } catch (err) {}
        }
        tooltip.classList.remove('is-visible');
        tooltip.hidden = true;
    }

    function showTooltip(trigger) {
        if (!isDesktopCollapsed()) {
            hideTooltip();
            return;
        }
        const text = trigger.getAttribute('data-tooltip');
        if (!text) {
            hideTooltip();
            return;
        }

        if (activeTrigger && activeTrigger !== trigger) {
            activeTrigger.removeAttribute('aria-describedby');
        }
        activeTrigger = trigger;
        tooltip.textContent = text;
        tooltip.hidden = false;

        if (typeof tooltip.showPopover === 'function') {
            try {
                if (!tooltip.matches(':popover-open')) {
                    tooltip.showPopover();
                }
            } catch (err) {}
        }

        const rect = trigger.getBoundingClientRect();
        tooltip.style.top = Math.round(rect.top + rect.height / 2) + 'px';
        tooltip.style.left = Math.round(rect.right + 10) + 'px';
        tooltip.classList.add('is-visible');
        trigger.setAttribute('aria-describedby', tooltip.id);
    }

    sidebar.addEventListener('mouseover', (e) => {
        const trigger = e.target.closest('[data-tooltip]');
        if (trigger && sidebar.contains(trigger)) {
            showTooltip(trigger);
        }
    });

    sidebar.addEventListener('mouseout', (e) => {
        if (!activeTrigger) return;
        const related = e.relatedTarget;
        if (!related || (!activeTrigger.contains(related) && !tooltip.contains(related))) {
            hideTooltip();
        }
    });

    sidebar.addEventListener('focusin', (e) => {
        const trigger = e.target.closest('[data-tooltip]');
        if (trigger && sidebar.contains(trigger) && trigger.matches(':focus-visible')) {
            showTooltip(trigger);
        }
    });

    sidebar.addEventListener('focusout', (e) => {
        if (!e.relatedTarget || !sidebar.contains(e.relatedTarget)) {
            hideTooltip();
        }
    });

    sidebar.addEventListener('scroll', hideTooltip, { passive: true });
    window.addEventListener('resize', hideTooltip);
    window.__hideSidebarTooltip = hideTooltip;
}

/**
 * Global Keyboard Accessibility (Esc to close drawer/tooltips/modals, Ctrl+B to toggle desktop sidebar)
 */
function initKeyboardNavigation() {
    document.addEventListener('keydown', (e) => {
        const active = document.activeElement;
        const tag = active ? (active.tagName || '').toLowerCase() : '';
        const isTypingTarget = tag === 'input' || tag === 'textarea' || tag === 'select' || (active && active.isContentEditable);

        if (e.key === '/' && !e.ctrlKey && !e.metaKey && !e.altKey && !e.shiftKey && !isTypingTarget) {
            const filterInput = document.getElementById('sidebarNavFilter');
            const shell = document.querySelector('.app-shell');
            const isSidebarCollapsed = shell && window.innerWidth >= 768 && shell.classList.contains('sidebar-collapsed');
            if (filterInput && !isSidebarCollapsed && (window.innerWidth >= 768 || document.body.classList.contains('sidebar-open'))) {
                e.preventDefault();
                filterInput.focus();
                filterInput.select();
            }
            return;
        }

        if ((e.ctrlKey || e.metaKey) && !e.shiftKey && !e.altKey && (e.key === 'b' || e.key === 'B')) {
            if (isTypingTarget) {
                return;
            }
            if (window.innerWidth >= 768 && typeof window.__toggleDesktopSidebar === 'function') {
                e.preventDefault();
                if (typeof window.__hideSidebarTooltip === 'function') {
                    window.__hideSidebarTooltip();
                }
                window.__toggleDesktopSidebar();
            }
            return;
        }

        if (e.key === 'Escape' || e.key === 'Esc') {
            if (typeof window.__hideSidebarTooltip === 'function') {
                window.__hideSidebarTooltip();
            }
            if (document.body.classList.contains('sidebar-open')) {
                closeMobileDrawer(true);
            }
            // Close active modal if open
            const openModal = document.querySelector('.modal-backdrop.is-open, .modal-overlay.is-open');
            if (openModal && typeof closeModal === 'function') {
                closeModal(openModal.id);
            }
        }
    });
}
