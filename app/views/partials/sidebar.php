<?php
/**
 * Role-Aware Dynamic Navigation Sidebar Partial
 * Employee HRIS & Leave Management System
 * Supports 7-Group Admin Navigation Structure, Operational HR Navigation, and Employee Self-Service
 */

$rawPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$currentPath = rtrim((string)$rawPath, '/') ?: '/';
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
    $brandLabel = 'NEXORA HR';
    $roleTitle = $user['employee_no'] ?? 'Employee';
    $iconClass = 'emp';
}

$sidebarUnreadCount = 0;
$sidebarPendingCount = 0;
if ($rawRole === 'employee' && isAuthenticated()) {
    try {
        $sidebarUnreadCount = (new \App\Models\Notification())->getUnreadCount((int)currentUserId());
    } catch (\Throwable $e) {
        $sidebarUnreadCount = 0;
    }

    if (!empty($user['employee_id'])) {
        try {
            $sidebarPendingCount = (new \App\Models\Leave())->countPendingByEmployee((int)$user['employee_id']);
        } catch (\Throwable $e) {
            $sidebarPendingCount = 0;
        }
    }
}
?>
<aside class="app-sidebar" id="appSidebar" aria-label="Main Navigation">
    <div class="sidebar-header">
        <a href="<?= $dashboardUrl ?>" class="sidebar-brand" aria-label="NEXORA HR — People. Processes. Progress." title="NEXORA HR" data-tooltip="NEXORA HR">
            <img
                src="<?= e(asset('img/nexora-logo-icon.png')) ?>"
                alt=""
                class="sidebar-logo-emblem"
                width="34"
                height="34"
            >
            <div class="sidebar-brand-lockup">
                <div class="sidebar-logo-wordmark">
                    <span class="logo-word-nexora">NEXOR<span class="logo-letter-a">A<svg class="logo-delta-mark" viewBox="0 0 12 10" aria-hidden="true" focusable="false"><polygon points="6,1 11,10 1,10" fill="currentColor"/></svg></span></span>
                    <span class="logo-word-hr">HR</span>
                </div>
                <div class="sidebar-logo-tagline">
                    <span class="logo-tagline-rule" aria-hidden="true"></span>
                    <span class="logo-tagline-text">People. Processes. Progress.</span>
                    <span class="logo-tagline-rule" aria-hidden="true"></span>
                </div>
            </div>
        </a>
        <button type="button" class="mobile-drawer-close" id="mobileSidebarClose" aria-label="Close navigation menu" title="Close navigation menu">
            <?= icon('x', 18) ?>
        </button>
    </div>

    <nav class="sidebar-nav">
        <?php if ($rawRole === 'admin'): ?>
            <!-- ==============================================================
                 ADMINISTRATOR 7-GROUP HIERARCHY
                 ============================================================== -->
            
            <!-- Dashboard -->
            <div class="nav-section-title">Dashboard</div>
            <a href="<?= url('/admin/dashboard') ?>" class="nav-item <?= $currentPath === '/admin/dashboard' ? 'active' : '' ?>" title="Overview">
                <span class="icon" aria-hidden="true"><?= icon('dashboard', 18) ?></span>
                <span class="nav-item-label">Overview</span>
            </a>

            <!-- User Administration -->
            <div class="nav-section-title">User Administration</div>
            <a href="<?= url('/admin/users') ?>" class="nav-item <?= ($currentPath === '/admin/users' || str_starts_with($currentPath, '/admin/users/')) ? 'active' : '' ?>" title="User Accounts">
                <span class="icon" aria-hidden="true"><?= icon('users', 18) ?></span>
                <span class="nav-item-label">User Accounts</span>
            </a>
            <a href="<?= url('/admin/roles') ?>" class="nav-item <?= $currentPath === '/admin/roles' ? 'active' : '' ?>" title="Roles &amp; Permissions">
                <span class="icon" aria-hidden="true"><?= icon('shield', 18) ?></span>
                <span class="nav-item-label">Roles &amp; Permissions</span>
            </a>
            <a href="<?= url('/admin/provisioning') ?>" class="nav-item <?= $currentPath === '/admin/provisioning' ? 'active' : '' ?>" title="Admin Provisioning">
                <span class="icon" aria-hidden="true"><?= icon('user', 18) ?></span>
                <span class="nav-item-label">Admin Provisioning</span>
            </a>

            <!-- Organization Management -->
            <div class="nav-section-title">Organization Management</div>
            <a href="<?= url('/hr/employees') ?>" class="nav-item <?= (str_contains($currentPath, '/employees') && !str_contains($currentPath, '/employee/')) ? 'active' : '' ?>" title="Employee Records">
                <span class="icon" aria-hidden="true"><?= icon('file-text', 18) ?></span>
                <span class="nav-item-label">Employee Records</span>
            </a>
            <a href="<?= url('/hr/departments') ?>" class="nav-item <?= str_contains($currentPath, '/departments') ? 'active' : '' ?>" title="Departments">
                <span class="icon" aria-hidden="true"><?= icon('building', 18) ?></span>
                <span class="nav-item-label">Departments</span>
            </a>
            <a href="<?= url('/hr/positions') ?>" class="nav-item <?= str_contains($currentPath, '/positions') ? 'active' : '' ?>" title="Positions">
                <span class="icon" aria-hidden="true"><?= icon('briefcase', 18) ?></span>
                <span class="nav-item-label">Positions</span>
            </a>

            <!-- Leave Administration -->
            <div class="nav-section-title">Leave Administration</div>
            <a href="<?= url('/leaves') ?>" class="nav-item <?= ($currentPath === '/leaves' || $currentPath === '/admin/leaves' || $currentPath === '/hr/leave-requests') ? 'active' : '' ?>" title="Leave Requests">
                <span class="icon" aria-hidden="true"><?= icon('calendar', 18) ?></span>
                <span class="nav-item-label">Leave Requests</span>
            </a>
            <a href="<?= url('/hr/leave-types') ?>" class="nav-item <?= str_contains($currentPath, '/leave-types') ? 'active' : '' ?>" title="Leave Types &amp; Policies">
                <span class="icon" aria-hidden="true"><?= icon('layers', 18) ?></span>
                <span class="nav-item-label">Leave Types &amp; Policies</span>
            </a>
            <a href="<?= url('/leaves/balances') ?>" class="nav-item <?= str_contains($currentPath, '/leaves/balances') ? 'active' : '' ?>" title="Leave Balances">
                <span class="icon" aria-hidden="true"><?= icon('balances', 18) ?></span>
                <span class="nav-item-label">Leave Balances</span>
            </a>

            <!-- Reports & Analytics -->
            <div class="nav-section-title">Reports &amp; Analytics</div>
            <a href="<?= url('/reports') ?>" class="nav-item <?= (($currentPath === '/reports' || $currentPath === '/admin/reports') && $currentTab !== 'analytics') ? 'active' : '' ?>" title="System Reports">
                <span class="icon" aria-hidden="true"><?= icon('bar-chart', 18) ?></span>
                <span class="nav-item-label">System Reports</span>
            </a>
            <a href="<?= url('/reports?tab=analytics') ?>" class="nav-item <?= ((($currentPath === '/reports' || $currentPath === '/admin/reports') && $currentTab === 'analytics') || $currentPath === '/admin/analytics') ? 'active' : '' ?>" title="HR Analytics">
                <span class="icon" aria-hidden="true"><?= icon('activity', 18) ?></span>
                <span class="nav-item-label">HR Analytics</span>
            </a>

            <!-- Security & Compliance -->
            <div class="nav-section-title">Security &amp; Compliance</div>
            <a href="<?= url('/activity-logs') ?>" class="nav-item <?= $currentPath === '/activity-logs' ? 'active' : '' ?>" title="Audit Logs">
                <span class="icon" aria-hidden="true"><?= icon('file-text', 18) ?></span>
                <span class="nav-item-label">Audit Logs</span>
            </a>
            <a href="<?= url('/admin/security/events') ?>" class="nav-item <?= $currentPath === '/admin/security/events' ? 'active' : '' ?>" title="Security Events">
                <span class="icon" aria-hidden="true"><?= icon('alert-triangle', 18) ?></span>
                <span class="nav-item-label">Security Events</span>
            </a>
            <a href="<?= url('/admin/settings/auth') ?>" class="nav-item <?= $currentPath === '/admin/settings/auth' ? 'active' : '' ?>" title="Authentication Settings">
                <span class="icon" aria-hidden="true"><?= icon('lock', 18) ?></span>
                <span class="nav-item-label">Authentication Settings</span>
            </a>

            <!-- System Administration -->
            <div class="nav-section-title">System Administration</div>
            <a href="<?= url('/admin/settings') ?>" class="nav-item <?= $currentPath === '/admin/settings' ? 'active' : '' ?>" title="General Settings">
                <span class="icon" aria-hidden="true"><?= icon('settings', 18) ?></span>
                <span class="nav-item-label">General Settings</span>
            </a>
            <a href="<?= url('/admin/system-health') ?>" class="nav-item <?= $currentPath === '/admin/system-health' ? 'active' : '' ?>" title="System Health">
                <span class="icon" aria-hidden="true"><?= icon('activity', 18) ?></span>
                <span class="nav-item-label">System Health</span>
            </a>
            <a href="<?= url('/admin/settings/notifications') ?>" class="nav-item <?= $currentPath === '/admin/settings/notifications' ? 'active' : '' ?>" title="Notification Settings">
                <span class="icon" aria-hidden="true"><?= icon('bell', 18) ?></span>
                <span class="nav-item-label">Notification Settings</span>
            </a>

            <!-- Account Session -->
            <div class="nav-section-title">Account</div>
            <a href="<?= url('/admin/account') ?>" class="nav-item <?= $currentPath === '/admin/account' ? 'active' : '' ?>" title="My Account">
                <span class="icon" aria-hidden="true"><?= icon('user', 18) ?></span>
                <span class="nav-item-label">My Account</span>
            </a>
            <form method="POST" action="<?= url('/logout') ?>">
                <?= csrf_field() ?>
                <button type="submit" class="nav-item nav-item-btn" title="Sign Out">
                    <span class="icon" aria-hidden="true"><?= icon('log-out', 18) ?></span>
                    <span class="nav-item-label">Sign Out</span>
                </button>
            </form>

        <?php elseif ($rawRole === 'hr'): ?>
            <!-- ==============================================================
                 HR OFFICER OPERATIONAL NAVIGATION
                 ============================================================== -->
            <div class="nav-section-title">Overview</div>
            <a href="<?= url('/hr/dashboard') ?>" class="nav-item <?= str_contains($currentPath, '/dashboard') ? 'active' : '' ?>" title="Dashboard">
                <span class="icon" aria-hidden="true"><?= icon('dashboard', 18) ?></span>
                <span class="nav-item-label">Dashboard</span>
            </a>

            <div class="nav-section-title">Workforce Management</div>
            <a href="<?= url('/hr/employees') ?>" class="nav-item <?= str_contains($currentPath, '/hr/employees') ? 'active' : '' ?>" title="Employees Directory">
                <span class="icon" aria-hidden="true"><?= icon('users', 18) ?></span>
                <span class="nav-item-label">Employees Directory</span>
            </a>
            <a href="<?= url('/hr/departments') ?>" class="nav-item <?= str_contains($currentPath, '/hr/departments') ? 'active' : '' ?>" title="Departments">
                <span class="icon" aria-hidden="true"><?= icon('building', 18) ?></span>
                <span class="nav-item-label">Departments</span>
            </a>
            <a href="<?= url('/hr/positions') ?>" class="nav-item <?= str_contains($currentPath, '/hr/positions') ? 'active' : '' ?>" title="Job Positions">
                <span class="icon" aria-hidden="true"><?= icon('briefcase', 18) ?></span>
                <span class="nav-item-label">Job Positions</span>
            </a>

            <div class="nav-section-title">Leave Management</div>
            <a href="<?= url('/leaves') ?>" class="nav-item <?= (str_contains($currentPath, '/leaves') && !str_contains($currentPath, '/leaves/balances')) ? 'active' : '' ?>" title="Leave Review Queue">
                <span class="icon" aria-hidden="true"><?= icon('calendar', 18) ?></span>
                <span class="nav-item-label">Leave Review Queue</span>
            </a>
            <a href="<?= url('/leaves/balances') ?>" class="nav-item <?= str_contains($currentPath, '/leaves/balances') ? 'active' : '' ?>" title="Workforce Balances">
                <span class="icon" aria-hidden="true"><?= icon('balances', 18) ?></span>
                <span class="nav-item-label">Workforce Balances</span>
            </a>
            <a href="<?= url('/hr/leave-types') ?>" class="nav-item <?= str_contains($currentPath, '/hr/leave-types') ? 'active' : '' ?>" title="Leave Classifications">
                <span class="icon" aria-hidden="true"><?= icon('layers', 18) ?></span>
                <span class="nav-item-label">Leave Classifications</span>
            </a>

            <div class="nav-section-title">Analytics &amp; Tools</div>
            <a href="<?= url('/reports') ?>" class="nav-item <?= str_contains($currentPath, '/reports') ? 'active' : '' ?>" title="Reports &amp; Analytics">
                <span class="icon" aria-hidden="true"><?= icon('bar-chart', 18) ?></span>
                <span class="nav-item-label">Reports &amp; Analytics</span>
            </a>
            <a href="<?= url('/activity-logs') ?>" class="nav-item <?= str_contains($currentPath, '/activity-logs') ? 'active' : '' ?>" title="Audit Activity Logs">
                <span class="icon" aria-hidden="true"><?= icon('file-text', 18) ?></span>
                <span class="nav-item-label">Audit Activity Logs</span>
            </a>

            <div class="nav-section-title">Communications</div>
            <a href="<?= url('/notifications') ?>" class="nav-item <?= str_contains($currentPath, '/notifications') ? 'active' : '' ?>" title="Notifications">
                <span class="icon" aria-hidden="true"><?= icon('bell', 18) ?></span>
                <span class="nav-item-label">Notifications</span>
            </a>

            <div class="nav-section-title">Account Session</div>
            <form method="POST" action="<?= url('/logout') ?>">
                <?= csrf_field() ?>
                <button type="submit" class="nav-item nav-item-btn" title="Sign Out">
                    <span class="icon" aria-hidden="true"><?= icon('log-out', 18) ?></span>
                    <span class="nav-item-label">Sign Out</span>
                </button>
            </form>

        <?php else: ?>
            <!-- ==============================================================
                 EMPLOYEE WORKSPACE NAVIGATION
                 ============================================================== -->
            <?php
            $isEmpDashboard = ($currentPath === '/employee/dashboard' || str_contains($currentPath, '/dashboard'));
            $isEmpProfile   = str_starts_with($currentPath, '/employee/profile');
            $isEmpBalances  = str_starts_with($currentPath, '/employee/leave-balances');
            $isEmpRequests  = (
                str_starts_with($currentPath, '/employee/leave-requests')
                || str_starts_with($currentPath, '/employee/leave-history')
                || preg_match('#^/leaves/\d+$#', $currentPath) === 1
            );
            $isEmpApply     = str_starts_with($currentPath, '/leaves/apply');
            $isEmpNotifs    = str_starts_with($currentPath, '/notifications');
            ?>
            <div class="nav-section-title">Workspace</div>
            <a href="<?= url('/employee/dashboard') ?>" class="nav-item <?= $isEmpDashboard ? 'active' : '' ?>"<?= $isEmpDashboard ? ' aria-current="page"' : '' ?> title="Dashboard" data-tooltip="Dashboard">
                <span class="icon" aria-hidden="true"><?= icon('dashboard', 18) ?></span>
                <span class="nav-item-label">Dashboard</span>
            </a>
            <a href="<?= url('/employee/profile') ?>" class="nav-item <?= $isEmpProfile ? 'active' : '' ?>"<?= $isEmpProfile ? ' aria-current="page"' : '' ?> title="My Profile" data-tooltip="My Profile">
                <span class="icon" aria-hidden="true"><?= icon('user', 18) ?></span>
                <span class="nav-item-label">My Profile</span>
            </a>

            <div class="nav-section-title">Leave &amp; Time Off</div>
            <a href="<?= url('/employee/leave-balances') ?>" class="nav-item <?= $isEmpBalances ? 'active' : '' ?>"<?= $isEmpBalances ? ' aria-current="page"' : '' ?> title="Leave Balances" data-tooltip="Leave Balances">
                <span class="icon" aria-hidden="true"><?= icon('calendar-check', 18) ?></span>
                <span class="nav-item-label">Leave Balances</span>
            </a>
            <a href="<?= url('/employee/leave-requests') ?>" class="nav-item <?= $isEmpRequests ? 'active' : '' ?>"<?= $isEmpRequests ? ' aria-current="page"' : '' ?> title="Leave Requests" data-tooltip="Leave Requests<?= $sidebarPendingCount > 0 ? ' (' . (int)$sidebarPendingCount . ' pending)' : '' ?>">
                <span class="icon" aria-hidden="true"><?= icon('file-text', 18) ?></span>
                <span class="nav-item-label">Leave Requests</span>
                <?php if ($sidebarPendingCount > 0): ?>
                    <span class="nav-badge nav-badge-pending" id="sidebarPendingBadge" aria-label="<?= (int)$sidebarPendingCount ?> pending leave requests"><?= $sidebarPendingCount > 99 ? '99+' : (int)$sidebarPendingCount ?></span>
                <?php endif; ?>
            </a>
            <a href="<?= url('/leaves/apply') ?>" class="nav-item <?= $isEmpApply ? 'active' : '' ?>"<?= $isEmpApply ? ' aria-current="page"' : '' ?> title="File Leave Request" data-tooltip="File Leave Request">
                <span class="icon" aria-hidden="true"><?= icon('file-plus', 18) ?></span>
                <span class="nav-item-label">File Leave Request</span>
            </a>

            <div class="nav-section-title">Updates</div>
            <a href="<?= url('/notifications') ?>" class="nav-item <?= $isEmpNotifs ? 'active' : '' ?>"<?= $isEmpNotifs ? ' aria-current="page"' : '' ?> title="Notifications" data-tooltip="Notifications">
                <span class="icon" aria-hidden="true"><?= icon('bell', 18) ?></span>
                <span class="nav-item-label">Notifications</span>
                <span class="nav-badge" id="sidebarUnreadBadge"<?= $sidebarUnreadCount > 0 ? '' : ' hidden' ?> aria-label="<?= (int)$sidebarUnreadCount ?> unread notifications"><?= $sidebarUnreadCount > 99 ? '99+' : (int)$sidebarUnreadCount ?></span>
            </a>

            <div class="nav-section-title">Session</div>
            <form method="POST" action="<?= url('/logout') ?>" style="margin: 0;">
                <?= csrf_field() ?>
                <button type="submit" class="nav-item nav-item-btn" title="Sign Out" data-tooltip="Sign Out">
                    <span class="icon" aria-hidden="true"><?= icon('log-out', 18) ?></span>
                    <span class="nav-item-label">Sign Out</span>
                </button>
            </form>
        <?php endif; ?>
    </nav>

    <div class="sidebar-footer">
        <a href="<?= ($rawRole === 'admin') ? url('/admin/account') : url('/employee/profile') ?>" class="sidebar-user-link" title="View Profile" aria-label="<?= e($userName) ?> (<?= e($roleTitle) ?>) — View Profile" data-tooltip="<?= e($userName) ?> · <?= e($roleTitle) ?>">
            <div class="sidebar-user-avatar" aria-hidden="true"><?= e($initials) ?></div>
            <div class="sidebar-user-info">
                <div class="sidebar-user-name"><?= e($userName) ?></div>
                <div class="sidebar-user-role"><?= e($roleTitle) ?></div>
            </div>
        </a>
        <button type="button" class="sidebar-toggle sidebar-footer-toggle" id="sidebarToggle" aria-label="Minimize sidebar (Ctrl+B)" title="Minimize sidebar (Ctrl+B)" aria-expanded="true" aria-controls="appSidebar" data-tooltip="Minimize sidebar (Ctrl+B)">
            <?= icon('panel-left-close', 18) ?>
        </button>
    </div>
</aside>
