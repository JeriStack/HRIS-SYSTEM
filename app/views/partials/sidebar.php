<?php
/**
 * Persistent HR Navigation Sidebar Partial
 * Employee HRIS & Leave Management System
 */

$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$user = currentUser() ?? [];
$userName = $user['name'] ?? $user['username'] ?? 'HR Admin';
$userRole = strtoupper($user['role'] ?? 'HR');
$initials = strtoupper(substr($userName, 0, 2));
?>
<aside class="app-sidebar" aria-label="Main Navigation">
    <div class="sidebar-header">
        <a href="<?= url('/hr/dashboard') ?>" class="sidebar-brand">
            <span class="brand-icon">H</span>
            <span>HRIS Portal</span>
        </a>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-section-title">Executive</div>
        <a href="<?= url('/hr/dashboard') ?>" class="nav-item <?= str_contains($currentPath, '/hr/dashboard') ? 'active' : '' ?>">
            <span class="icon">📊</span>
            <span>Dashboard</span>
        </a>

        <div class="nav-section-title">Workforce Management</div>
        <a href="<?= url('/hr/employees') ?>" class="nav-item <?= (str_contains($currentPath, '/hr/employees') && !str_contains($currentPath, '/departments') && !str_contains($currentPath, '/positions')) ? 'active' : '' ?>">
            <span class="icon">👥</span>
            <span>Employees Directory</span>
        </a>
        <a href="<?= url('/hr/departments') ?>" class="nav-item <?= str_contains($currentPath, '/hr/departments') ? 'active' : '' ?>">
            <span class="icon">🏢</span>
            <span>Departments</span>
        </a>
        <a href="<?= url('/hr/positions') ?>" class="nav-item <?= str_contains($currentPath, '/hr/positions') ? 'active' : '' ?>">
            <span class="icon">💼</span>
            <span>Job Positions</span>
        </a>

        <div class="nav-section-title">Leave Operations</div>
        <a href="<?= url('/prototype/leave-requests.html') ?>" class="nav-item">
            <span class="icon">⏳</span>
            <span>Leave Review Queue</span>
        </a>
        <a href="<?= url('/prototype/leave-types.html') ?>" class="nav-item">
            <span class="icon">📋</span>
            <span>Leave Policies</span>
        </a>
        <a href="<?= url('/prototype/leave-balances.html') ?>" class="nav-item">
            <span class="icon">💳</span>
            <span>Employee Balances</span>
        </a>

        <div class="nav-section-title">Analytics & Audit</div>
        <a href="<?= url('/prototype/reports.html') ?>" class="nav-item">
            <span class="icon">📈</span>
            <span>Workforce Reports</span>
        </a>
        <a href="<?= url('/prototype/activity-logs.html') ?>" class="nav-item">
            <span class="icon">🔒</span>
            <span>Activity Audit Logs</span>
        </a>

        <div class="nav-section-title">DSA Academic Lab</div>
        <a href="<?= url('/prototype/dsa-laboratory.html') ?>" class="nav-item">
            <span class="icon">🧪</span>
            <span>DSA Laboratory</span>
        </a>

        <div class="nav-section-title">System & Session</div>
        <form method="POST" action="<?= url('/logout') ?>" style="margin: 0;">
            <?= csrf_field() ?>
            <button type="submit" class="nav-item" style="width: 100%; background: none; border: none; text-align: left; cursor: pointer; font-family: inherit; font-size: inherit;">
                <span class="icon">🚪</span>
                <span>Sign Out</span>
            </button>
        </form>
    </nav>

    <div class="sidebar-footer">
        <div class="sidebar-user-avatar"><?= e($initials) ?></div>
        <div class="sidebar-user-info">
            <div class="sidebar-user-name"><?= e($userName) ?></div>
            <div class="sidebar-user-role"><?= e($userRole) ?> Administrator</div>
        </div>
    </div>
</aside>
