/**
 * Employee HRIS & Leave Management System
 * Production Navigation, Mobile Drawer Controller & Active State Handler
 */

document.addEventListener('DOMContentLoaded', () => {
    initMobileNavigation();
    initKeyboardNavigation();
});

/**
 * Mobile Drawer Toggle & Backdrop Dismissal
 */
function initMobileNavigation() {
    const toggleButtons = document.querySelectorAll('#sidebarToggle, #mobileSidebarToggle, .sidebar-toggle, .header-toggle');
    const sidebar = document.querySelector('.app-sidebar');

    if (!sidebar) return;

    toggleButtons.forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            const isOpen = document.body.classList.toggle('sidebar-open');
            sidebar.classList.toggle('active', isOpen);
            btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
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
