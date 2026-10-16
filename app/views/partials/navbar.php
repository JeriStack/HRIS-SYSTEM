<?php
/**
 * Top Navigation Bar Partial
 * Employee HRIS & Leave Management System
 */

$user = currentUser() ?? [];
$userName = $user['name'] ?? $user['username'] ?? 'User';
$role = strtolower($user['role'] ?? 'employee');
?>
<header class="app-header top-bar" role="banner">
    <div class="header-left top-bar-left">
        <button type="button" class="sidebar-toggle header-toggle" id="sidebarToggle" aria-label="Toggle navigation drawer" aria-expanded="false" aria-controls="appSidebar">
            ☰
        </button>
        <div class="header-breadcrumb top-bar-title">
            <span class="system-status-indicator" title="System Status: Operational" aria-hidden="true"></span>
            <strong><?= e($pageTitle ?? 'Employee HRIS Portal') ?></strong>
        </div>
    </div>

    <div class="header-right top-bar-right">
        <span class="role-badge <?= e($role) ?>">
            <?= e(strtoupper($user['role'] ?? 'HR')) ?> ACCESS
        </span>
        <div class="header-user-meta" style="margin-right: 0.5rem;">
            <div class="header-user-name"><?= e($userName) ?></div>
            <div class="header-user-role"><?= e($user['employee_no'] ?? strtoupper($role)) ?></div>
        </div>
        <form method="POST" action="<?= url('/logout') ?>" style="margin: 0; display: inline;">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-secondary btn-sm" aria-label="Sign out of current account">
                Sign Out
            </button>
        </form>
    </div>
</header>
