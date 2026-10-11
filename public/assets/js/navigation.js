/**
 * Employee HRIS & Leave Management System
 * Navigation, Sidebar Toggle & Role Switcher
 */

document.addEventListener('DOMContentLoaded', () => {
    initSidebar();
    initRoleSwitcher();
    highlightActiveNavItem();
});

// Mobile Sidebar Drawer Toggle
function initSidebar() {
    const toggleBtn = document.getElementById('mobileSidebarToggle');
    if (toggleBtn) {
        toggleBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            document.body.classList.toggle('sidebar-open');
        });
    }

    // Close when clicking outside on mobile
    document.addEventListener('click', (e) => {
        if (document.body.classList.contains('sidebar-open')) {
            const sidebar = document.querySelector('.app-sidebar');
            if (sidebar && !sidebar.contains(e.target) && !e.target.closest('#mobileSidebarToggle')) {
                document.body.classList.remove('sidebar-open');
            }
        }
    });
}

// Simulated Role Switcher (HR / Admin vs Employee)
function initRoleSwitcher() {
    const savedRole = localStorage.getItem('hris_simulated_role') || 'admin';
    updateRoleUI(savedRole);

    const switcherBtn = document.getElementById('roleSwitcherBtn');
    if (switcherBtn) {
        switcherBtn.addEventListener('click', () => {
            const currentRole = localStorage.getItem('hris_simulated_role') || 'admin';
            const newRole = currentRole === 'admin' ? 'employee' : 'admin';
            localStorage.setItem('hris_simulated_role', newRole);
            
            showToast(`Switched simulated role to: ${newRole === 'admin' ? 'Administrator / HR' : 'Regular Employee'}`, 'info');
            
            setTimeout(() => {
                // Route to appropriate dashboard if on dashboard
                const path = window.location.pathname;
                if (path.includes('dashboard')) {
                    window.location.href = newRole === 'admin' ? 'hr-dashboard.html' : 'employee-dashboard.html';
                } else {
                    window.location.reload();
                }
            }, 600);
        });
    }
}

function updateRoleUI(role) {
    const roleBadges = document.querySelectorAll('.user-role-label');
    roleBadges.forEach(b => {
        b.textContent = role === 'admin' ? 'Administrator / HR' : 'Regular Employee';
    });

    const userNameEls = document.querySelectorAll('.user-name-label');
    userNameEls.forEach(el => {
        el.textContent = role === 'admin' ? PrototypeData.currentUser.name : PrototypeData.mockEmployeeUser.name;
    });

    const userAvatars = document.querySelectorAll('.user-avatar-label');
    userAvatars.forEach(av => {
        av.textContent = role === 'admin' ? PrototypeData.currentUser.avatar : PrototypeData.mockEmployeeUser.avatar;
    });

    // Toggle navigation sections based on role
    const adminNavs = document.querySelectorAll('.admin-only-nav');
    const employeeNavs = document.querySelectorAll('.employee-only-nav');

    if (role === 'employee') {
        adminNavs.forEach(nav => nav.style.display = 'none');
        employeeNavs.forEach(nav => nav.style.display = 'flex');
    } else {
        adminNavs.forEach(nav => nav.style.display = 'flex');
        employeeNavs.forEach(nav => nav.style.display = 'none');
    }
}

// Highlight currently active navigation item
function highlightActiveNavItem() {
    const currentPath = window.location.pathname.split('/').pop() || 'hr-dashboard.html';
    const navItems = document.querySelectorAll('.nav-item');
    navItems.forEach(item => {
        const href = item.getAttribute('href');
        if (href && href.includes(currentPath)) {
            item.classList.add('active');
        } else {
            item.classList.remove('active');
        }
    });
}
