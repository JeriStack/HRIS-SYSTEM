<?php
/**
 * Role-Aware Dynamic Navigation Sidebar Partial
 * Employee HRIS & Leave Management System
 * Supports 7-Group Admin Navigation Structure, Operational HR Navigation, and Employee Self-Service
 */

$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$currentQuery = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_QUERY) ?? '';
$queryParams = [];
if ($currentQuery !== '') {
    parse_str($currentQuery, $queryParams);
}
$currentTab = $queryParams['tab'] ?? ($_GET['tab'] ?? '');
$user = currentUser() ?? [];
$userName = $user['name'] ?? $user['username'] ?? 'User';
$rawRole = strtolower($user['role'] ?? 'employee');
$initials = strtoupper(substr($userName, 0, 2));

if ($rawRole === 'admin') {
    $dashboardUrl = url('/admin/dashboard');
    $brandLabel = 'Admin Portal';
    $roleTitle = 'System Administrator';
    $iconClass = 'admin';
} elseif ($rawRole === 'hr') {
    $dashboardUrl = url('/hr/dashboard');
    $brandLabel = 'HR Portal';
    $roleTitle = 'HR Officer';
    $iconClass = 'hr';
} else {
    $dashboardUrl = url('/employee/dashboard');
    $brandLabel = 'Self-Service';
    $roleTitle = 'Employee';
    $iconClass = 'emp';
}
?>
<aside class="app-sidebar" id="appSidebar" aria-label="Main Navigation">
    <div class="sidebar-header">
        <a href="<?= $dashboardUrl ?>" class="sidebar-brand">
            <span class="brand-icon <?= $iconClass ?>"><?= ($rawRole === 'admin') ? 'A' : (($rawRole === 'hr') ? 'H' : 'E') ?></span>
            <span><?= e($brandLabel) ?></span>
        </a>
    </div>

    <nav class="sidebar-nav">
        <?php if ($rawRole === 'admin'): ?>
            <!-- ==============================================================
                 ADMINISTRATOR 7-GROUP HIERARCHY
                 ============================================================== -->
            
            <!-- 01 — Dashboard -->
            <div class="nav-section-title">01 — Dashboard</div>
            <a href="<?= url('/admin/dashboard') ?>" class="nav-item <?= $currentPath === '/admin/dashboard' ? 'active' : '' ?>">
                <span class="icon" aria-hidden="true">📊</span>
                <span>Overview</span>
            </a>

            <!-- 02 — User Administration -->
            <div class="nav-section-title">02 — User Administration</div>
            <a href="<?= url('/admin/users') ?>" class="nav-item <?= ($currentPath === '/admin/users' || str_starts_with($currentPath, '/admin/users/')) ? 'active' : '' ?>">
                <span class="icon" aria-hidden="true">👥</span>
                <span>User Accounts</span>
            </a>
            <a href="<?= url('/admin/roles') ?>" class="nav-item <?= $currentPath === '/admin/roles' ? 'active' : '' ?>">
                <span class="icon" aria-hidden="true">🛡️</span>
                <span>Roles &amp; Permissions</span>
            </a>
            <a href="<?= url('/admin/provisioning') ?>" class="nav-item <?= $currentPath === '/admin/provisioning' ? 'active' : '' ?>">
                <span class="icon" aria-hidden="true">👤</span>
                <span>Admin Provisioning</span>
            </a>

            <!-- 03 — Organization Management -->
            <div class="nav-section-title">03 — Organization Management</div>
            <a href="<?= url('/hr/employees') ?>" class="nav-item <?= (str_contains($currentPath, '/employees') && !str_contains($currentPath, '/employee/')) ? 'active' : '' ?>">
                <span class="icon" aria-hidden="true">📋</span>
                <span>Employee Records</span>
            </a>
            <a href="<?= url('/hr/departments') ?>" class="nav-item <?= str_contains($currentPath, '/departments') ? 'active' : '' ?>">
                <span class="icon" aria-hidden="true">🏢</span>
                <span>Departments</span>
            </a>
            <a href="<?= url('/hr/positions') ?>" class="nav-item <?= str_contains($currentPath, '/positions') ? 'active' : '' ?>">
                <span class="icon" aria-hidden="true">💼</span>
                <span>Positions</span>
            </a>

            <!-- 04 — Leave Administration -->
            <div class="nav-section-title">04 — Leave Administration</div>
            <a href="<?= url('/leaves') ?>" class="nav-item <?= ($currentPath === '/leaves' || $currentPath === '/admin/leaves' || $currentPath === '/hr/leave-requests') ? 'active' : '' ?>">
                <span class="icon" aria-hidden="true">📅</span>
                <span>Leave Requests</span>
            </a>
            <a href="<?= url('/hr/leave-types') ?>" class="nav-item <?= str_contains($currentPath, '/leave-types') ? 'active' : '' ?>">
                <span class="icon" aria-hidden="true">🏷️</span>
                <span>Leave Types &amp; Policies</span>
            </a>
            <a href="<?= url('/leaves/balances') ?>" class="nav-item <?= str_contains($currentPath, '/leaves/balances') ? 'active' : '' ?>">
                <span class="icon" aria-hidden="true">⚖️</span>
                <span>Leave Balances</span>
            </a>

            <!-- 05 — Reports & Analytics -->
            <div class="nav-section-title">05 — Reports &amp; Analytics</div>
            <a href="<?= url('/reports') ?>" class="nav-item <?= (($currentPath === '/reports' || $currentPath === '/admin/reports') && $currentTab !== 'analytics') ? 'active' : '' ?>">
                <span class="icon" aria-hidden="true">📈</span>
                <span>System Reports</span>
            </a>
            <a href="<?= url('/reports?tab=analytics') ?>" class="nav-item <?= ((($currentPath === '/reports' || $currentPath === '/admin/reports') && $currentTab === 'analytics') || $currentPath === '/admin/analytics') ? 'active' : '' ?>">
                <span class="icon" aria-hidden="true">📊</span>
                <span>HR Analytics</span>
            </a>

            <!-- 06 — Security & Compliance -->
            <div class="nav-section-title">06 — Security &amp; Compliance</div>
            <a href="<?= url('/activity-logs') ?>" class="nav-item <?= $currentPath === '/activity-logs' ? 'active' : '' ?>">
                <span class="icon" aria-hidden="true">📜</span>
                <span>Audit Logs</span>
            </a>
            <a href="<?= url('/admin/security/events') ?>" class="nav-item <?= $currentPath === '/admin/security/events' ? 'active' : '' ?>">
                <span class="icon" aria-hidden="true">🚨</span>
                <span>Security Events</span>
            </a>
            <a href="<?= url('/admin/settings/auth') ?>" class="nav-item <?= $currentPath === '/admin/settings/auth' ? 'active' : '' ?>">
                <span class="icon" aria-hidden="true">🔐</span>
                <span>Authentication Settings</span>
            </a>

            <!-- 07 — System Administration -->
            <div class="nav-section-title">07 — System Administration</div>
            <a href="<?= url('/admin/settings') ?>" class="nav-item <?= $currentPath === '/admin/settings' ? 'active' : '' ?>">
                <span class="icon" aria-hidden="true">⚙️</span>
                <span>General Settings</span>
            </a>
            <a href="<?= url('/admin/system-health') ?>" class="nav-item <?= $currentPath === '/admin/system-health' ? 'active' : '' ?>">
                <span class="icon" aria-hidden="true">🩺</span>
                <span>System Health</span>
            </a>
            <a href="<?= url('/admin/settings/notifications') ?>" class="nav-item <?= $currentPath === '/admin/settings/notifications' ? 'active' : '' ?>">
                <span class="icon" aria-hidden="true">🔔</span>
                <span>Notification Settings</span>
            </a>

            <!-- Account Session -->
            <div class="nav-section-title">Account</div>
            <a href="<?= url('/admin/account') ?>" class="nav-item <?= $currentPath === '/admin/account' ? 'active' : '' ?>">
                <span class="icon" aria-hidden="true">👤</span>
                <span>My Account</span>
            </a>
            <form method="POST" action="<?= url('/logout') ?>" style="margin: 0;">
                <?= csrf_field() ?>
                <button type="submit" class="nav-item" style="width: 100%; background: none; border: none; text-align: left; cursor: pointer; font-family: inherit; font-size: inherit; color: inherit;">
                    <span class="icon" aria-hidden="true">🚪</span>
                    <span>Sign Out</span>
                </button>
            </form>

        <?php elseif ($rawRole === 'hr'): ?>
            <!-- ==============================================================
                 HR OFFICER OPERATIONAL NAVIGATION
                 ============================================================== -->
            <div class="nav-section-title">Overview</div>
            <a href="<?= url('/hr/dashboard') ?>" class="nav-item <?= str_contains($currentPath, '/dashboard') ? 'active' : '' ?>">
                <span class="icon" aria-hidden="true">📊</span>
                <span>Dashboard</span>
            </a>

            <div class="nav-section-title">Workforce Management</div>
            <a href="<?= url('/hr/employees') ?>" class="nav-item <?= str_contains($currentPath, '/hr/employees') ? 'active' : '' ?>">
                <span class="icon" aria-hidden="true">📋</span>
                <span>Employees Directory</span>
            </a>
            <a href="<?= url('/hr/departments') ?>" class="nav-item <?= str_contains($currentPath, '/hr/departments') ? 'active' : '' ?>">
                <span class="icon" aria-hidden="true">🏢</span>
                <span>Departments</span>
            </a>
            <a href="<?= url('/hr/positions') ?>" class="nav-item <?= str_contains($currentPath, '/hr/positions') ? 'active' : '' ?>">
                <span class="icon" aria-hidden="true">💼</span>
                <span>Job Positions</span>
            </a>

            <div class="nav-section-title">Leave Management</div>
            <a href="<?= url('/leaves') ?>" class="nav-item <?= (str_contains($currentPath, '/leaves') && !str_contains($currentPath, '/leaves/balances')) ? 'active' : '' ?>">
                <span class="icon" aria-hidden="true">📅</span>
                <span>Leave Review Queue</span>
            </a>
            <a href="<?= url('/leaves/balances') ?>" class="nav-item <?= str_contains($currentPath, '/leaves/balances') ? 'active' : '' ?>">
                <span class="icon" aria-hidden="true">⚖️</span>
                <span>Workforce Balances</span>
            </a>
            <a href="<?= url('/hr/leave-types') ?>" class="nav-item <?= str_contains($currentPath, '/hr/leave-types') ? 'active' : '' ?>">
                <span class="icon" aria-hidden="true">🏷️</span>
                <span>Leave Classifications</span>
            </a>

            <div class="nav-section-title">Analytics &amp; Tools</div>
            <a href="<?= url('/reports') ?>" class="nav-item <?= str_contains($currentPath, '/reports') ? 'active' : '' ?>">
                <span class="icon" aria-hidden="true">📈</span>
                <span>Reports &amp; Analytics</span>
            </a>
            <a href="<?= url('/activity-logs') ?>" class="nav-item <?= str_contains($currentPath, '/activity-logs') ? 'active' : '' ?>">
                <span class="icon" aria-hidden="true">📜</span>
                <span>Audit Activity Logs</span>
            </a>

            <div class="nav-section-title">Communications</div>
            <a href="<?= url('/notifications') ?>" class="nav-item <?= str_contains($currentPath, '/notifications') ? 'active' : '' ?>">
                <span class="icon" aria-hidden="true">🔔</span>
                <span>Notifications</span>
            </a>

            <div class="nav-section-title">Account Session</div>
            <form method="POST" action="<?= url('/logout') ?>" style="margin: 0;">
                <?= csrf_field() ?>
                <button type="submit" class="nav-item" style="width: 100%; background: none; border: none; text-align: left; cursor: pointer; font-family: inherit; font-size: inherit; color: inherit;">
                    <span class="icon" aria-hidden="true">🚪</span>
                    <span>Sign Out</span>
                </button>
            </form>

        <?php else: ?>
            <!-- ==============================================================
                 EMPLOYEE SELF-SERVICE NAVIGATION
                 ============================================================== -->
            <div class="nav-section-title">Overview</div>
            <a href="<?= url('/employee/dashboard') ?>" class="nav-item <?= str_contains($currentPath, '/dashboard') ? 'active' : '' ?>">
                <span class="icon" aria-hidden="true">📊</span>
                <span>Dashboard</span>
            </a>

            <div class="nav-section-title">Self-Service</div>
            <a href="<?= url('/employee/profile') ?>" class="nav-item <?= str_contains($currentPath, '/employee/profile') ? 'active' : '' ?>">
                <span class="icon" aria-hidden="true">👤</span>
                <span>My Profile</span>
            </a>
            <a href="<?= url('/employee/leave-balances') ?>" class="nav-item <?= str_contains($currentPath, '/employee/leave-balances') ? 'active' : '' ?>">
                <span class="icon" aria-hidden="true">⚖️</span>
                <span>My Leave Balance</span>
            </a>
            <a href="<?= url('/employee/leave-requests') ?>" class="nav-item <?= str_contains($currentPath, '/employee/leave-requests') ? 'active' : '' ?>">
                <span class="icon" aria-hidden="true">📋</span>
                <span>My Leave Requests</span>
            </a>
            <a href="<?= url('/leaves/apply') ?>" class="nav-item <?= str_contains($currentPath, '/leaves/apply') ? 'active' : '' ?>">
                <span class="icon" aria-hidden="true">✍️</span>
                <span>File Leave Request</span>
            </a>

            <div class="nav-section-title">Communications</div>
            <a href="<?= url('/notifications') ?>" class="nav-item <?= str_contains($currentPath, '/notifications') ? 'active' : '' ?>">
                <span class="icon" aria-hidden="true">🔔</span>
                <span>Notifications</span>
            </a>

            <div class="nav-section-title">Account Session</div>
            <form method="POST" action="<?= url('/logout') ?>" style="margin: 0;">
                <?= csrf_field() ?>
                <button type="submit" class="nav-item" style="width: 100%; background: none; border: none; text-align: left; cursor: pointer; font-family: inherit; font-size: inherit; color: inherit;">
                    <span class="icon" aria-hidden="true">🚪</span>
                    <span>Sign Out</span>
                </button>
            </form>
        <?php endif; ?>
    </nav>

    <div class="sidebar-footer">
        <div class="sidebar-user-avatar" aria-hidden="true"><?= e($initials) ?></div>
        <div class="sidebar-user-info">
            <div class="sidebar-user-name"><?= e($userName) ?></div>
            <div class="sidebar-user-role"><?= e($roleTitle) ?></div>
        </div>
    </div>
</aside>
