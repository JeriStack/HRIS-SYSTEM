/**
 * Employee HRIS & Leave Management System
 * Production Navigation, Mobile Drawer Controller & Active State Handler
 */

document.addEventListener('DOMContentLoaded', () => {
    initThemeManager();
    initMobileNavigation();
    initKeyboardNavigation();
});

/**
 * Enterprise Theme Manager (Light & Dark Mode)
 */
function initThemeManager() {
    const savedTheme = localStorage.getItem('nexora_theme');
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
    const sidebar = document.querySelector('.app-sidebar');
    const shell = document.querySelector('.app-shell');

    const collapseIconSvg = '<svg class="svg-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="9" y1="3" x2="9" y2="21"></line><path d="m15 15-3-3 3-3"></path></svg>';
    const expandIconSvg = '<svg class="svg-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="9" y1="3" x2="9" y2="21"></line><path d="m13 9 3 3-3 3"></path></svg>';

    function syncSidebarToggleButtonState(isCollapsed) {
        toggleButtons.forEach(btn => {
            if (btn.id === 'mobileSidebarToggle') {
                return;
            }
            btn.classList.toggle('is-collapsed', isCollapsed);
            btn.setAttribute('aria-expanded', isCollapsed ? 'false' : 'true');
            const label = isCollapsed ? 'Expand sidebar' : 'Minimize sidebar';
            btn.setAttribute('aria-label', label);
            btn.setAttribute('title', label);
            btn.innerHTML = isCollapsed ? expandIconSvg : collapseIconSvg;
        });
    }

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
                // Desktop: Toggle collapsed state
                if (shell) {
                    const isCollapsed = shell.classList.toggle('sidebar-collapsed');
                    localStorage.setItem('nexora_sidebar_collapsed', isCollapsed ? '1' : '0');
                    syncSidebarToggleButtonState(isCollapsed);
                }
            } else {
                // Mobile: Toggle drawer
                const isOpen = document.body.classList.toggle('sidebar-open');
                sidebar.classList.toggle('active', isOpen);
                btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            }
        });
    });

    // Close drawer when clicking outside the sidebar
    document.addEventListener('click', (e) => {
        if (document.body.classList.contains('sidebar-open')) {
            if (!sidebar.contains(e.target) && !e.target.closest('#sidebarToggle, #mobileSidebarToggle, .sidebar-toggle, .header-toggle')) {
                closeMobileDrawer();
            }
        }
    });

    // Close drawer on navigation click (for mobile SPA-like feel or anchors)
    sidebar.querySelectorAll('.nav-item').forEach(link => {
        link.addEventListener('click', () => {
            if (window.innerWidth < 768) {
                closeMobileDrawer();
            }
        });
    });
}

/**
 * Helper to close mobile drawer safely
 */
function closeMobileDrawer() {
    const sidebar = document.querySelector('.app-sidebar');
    document.body.classList.remove('sidebar-open');
    if (sidebar) sidebar.classList.remove('active');
    const toggleButtons = document.querySelectorAll('#sidebarToggle, #mobileSidebarToggle, .sidebar-toggle, .header-toggle');
    toggleButtons.forEach(btn => btn.setAttribute('aria-expanded', 'false'));
}

/**
 * Global Keyboard Accessibility (Esc to close drawer / modals)
 */
function initKeyboardNavigation() {
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' || e.key === 'Esc') {
            if (document.body.classList.contains('sidebar-open')) {
                closeMobileDrawer();
            }
            // Close active modal if open
            const openModal = document.querySelector('.modal-backdrop.is-open, .modal-overlay.is-open');
            if (openModal && typeof closeModal === 'function') {
                closeModal(openModal.id);
            }
        }
    });
}
