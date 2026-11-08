<?php
/**
 * Role-Aware Dynamic Navigation Sidebar Partial
 * Employee HRIS & Leave Management System
 */

$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$user = currentUser() ?? [];
$userName = $user['name'] ?? $user['username'] ?? 'User';
$rawRole = strtolower($user['role'] ?? 'employee');
$initials = strtoupper(substr($userName, 0, 2));

if ($rawRole === 'admin') {
    $dashboardUrl = url('/admin/dashboard');
    $brandLabel = 'Admin Portal';
    $roleTitle = 'Administrator';
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
        <div class="nav-section-title">Overview</div>
        <a href="<?= $dashboardUrl ?>" class="nav-item <?= str_contains($currentPath, '/dashboard') ? 'active' : '' ?>">
            <span class="icon" aria-hidden="true">📊</span>
            <span>Dashboard</span>
        </a>

        <?php if ($rawRole === 'admin'): ?>
            <div class="nav-section-title">Administration</div>
            <a href="<?= url('/admin/users') ?>" class="nav-item <?= (str_contains($currentPath, '/admin/users') || str_contains($currentPath, '/hr/users')) ? 'active' : '' ?>">
                <span class="icon" aria-hidden="true">👥</span>
                <span>User Accounts & Roles</span>
            </a>
        <?php endif; ?>

        <?php if ($rawRole === 'admin' || $rawRole === 'hr'): ?>
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

            <div class="nav-section-title">Analytics & Tools</div>
            <a href="<?= url('/reports') ?>" class="nav-item <?= str_contains($currentPath, '/reports') ? 'active' : '' ?>">
                <span class="icon" aria-hidden="true">📈</span>
                <span>Reports & Analytics</span>
            </a>
            <a href="<?= url('/activity-logs') ?>" class="nav-item <?= str_contains($currentPath, '/activity-logs') ? 'active' : '' ?>">
                <span class="icon" aria-hidden="true">📜</span>
                <span>Audit Activity Logs</span>
            </a>
        <?php endif; ?>

        <?php if ($rawRole === 'employee'): ?>
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
        <?php endif; ?>

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
    </nav>

    <div class="sidebar-footer">
        <div class="sidebar-user-avatar" aria-hidden="true"><?= e($initials) ?></div>
        <div class="sidebar-user-info">
            <div class="sidebar-user-name"><?= e($userName) ?></div>
            <div class="sidebar-user-role"><?= e($roleTitle) ?></div>
        </div>
    </div>
</aside>
