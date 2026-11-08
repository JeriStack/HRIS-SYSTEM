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
    const themeButtons = document.querySelectorAll('.theme-toggle-btn');
    themeButtons.forEach(btn => {
        btn.setAttribute('aria-label', theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode');
        btn.innerHTML = theme === 'dark' ? '☀️' : '🌙';
    });
}

/**
 * Mobile Drawer Toggle & Desktop Sidebar Collapse Controller
 */
function initMobileNavigation() {
    const toggleButtons = document.querySelectorAll('#sidebarToggle, #mobileSidebarToggle, .sidebar-toggle, .header-toggle');
    const sidebar = document.querySelector('.app-sidebar');
    const shell = document.querySelector('.app-shell');

    // Restore desktop collapsed preference
    if (shell && window.innerWidth >= 768 && localStorage.getItem('nexora_sidebar_collapsed') === '1') {
        shell.classList.add('sidebar-collapsed');
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
                    btn.setAttribute('aria-expanded', isCollapsed ? 'false' : 'true');
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
