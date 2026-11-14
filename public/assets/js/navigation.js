/**
 * Employee HRIS & Leave Management System
 * Production Navigation, Mobile Drawer Controller, Collapsed Tooltips & Active State Handler
 */

document.addEventListener('DOMContentLoaded', () => {
    initThemeManager();
    initMobileNavigation();
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
    document.body.classList.remove('sidebar-open');
    document.body.style.overflow = '';
    if (sidebar) sidebar.classList.remove('active');

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
        if ((e.ctrlKey || e.metaKey) && !e.shiftKey && !e.altKey && (e.key === 'b' || e.key === 'B')) {
            const active = document.activeElement;
            const tag = active ? (active.tagName || '').toLowerCase() : '';
            if (tag === 'input' || tag === 'textarea' || tag === 'select' || (active && active.isContentEditable)) {
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
