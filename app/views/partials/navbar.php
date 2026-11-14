<?php

/**
 * Top Navigation Bar Partial
 * Employee HRIS & Leave Management System
 */

$user = currentUser() ?? [];
$userName = $user['name'] ?? $user['username'] ?? 'User';
$role = strtolower($user['role'] ?? 'employee');

// Determine portal home URL and portal label
$portalHome = url('/employee/dashboard');
$portalLabel = 'Employee Workspace';
if ($role === 'admin') {
    $portalHome = url('/admin/dashboard');
    $portalLabel = 'Administration Portal';
} elseif ($role === 'hr') {
    $portalHome = url('/hr/dashboard');
    $portalLabel = 'HR Portal';
}

// Current page and section breadcrumb resolution
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$sectionTitle = 'Overview';
if (str_contains($currentPath, '/users') || str_contains($currentPath, '/roles') || str_contains($currentPath, '/provisioning')) {
    $sectionTitle = 'User Administration';
} elseif (str_contains($currentPath, '/employees') || str_contains($currentPath, '/departments') || str_contains($currentPath, '/positions')) {
    $sectionTitle = 'Organization';
} elseif (str_contains($currentPath, '/leaves')) {
    $sectionTitle = 'Leave Administration';
} elseif (str_contains($currentPath, '/reports')) {
    $sectionTitle = 'Reports & Analytics';
} elseif (str_contains($currentPath, '/security') || str_contains($currentPath, '/activity-logs')) {
    $sectionTitle = 'Security & Compliance';
} elseif (str_contains($currentPath, '/settings') || str_contains($currentPath, '/system-health')) {
    $sectionTitle = 'System Administration';
}

// Real-time notification indicator (initial count rendered server-side)
$rtEnabled = false;
$rtUnread = 0;
$rtWsUrl = '';
if (isAuthenticated()) {
    $rtConfig = \App\Realtime\RealtimeConfig::fromEnvironment();
    $rtEnabled = $rtConfig->enabled;
    $rtWsUrl = $rtConfig->browserUrl((string)config('config.base_url', ''));
    try {
        $rtUnread = (new \App\Models\Notification())->getUnreadCount((int)currentUserId());
    } catch (\Throwable $e) {
        $rtUnread = 0;
    }
}
?>
<header class="app-header top-bar" role="banner">
    <div class="header-left top-bar-left">
        <button type="button" class="sidebar-toggle header-toggle mobile-toggle-only" id="mobileSidebarToggle" aria-label="Open navigation menu" title="Open navigation menu" aria-expanded="false" aria-controls="appSidebar">
            <?= icon('menu', 18) ?>
        </button>
        <nav class="header-breadcrumb" aria-label="Breadcrumb">
            <a href="<?= $portalHome ?>"><?= e($portalLabel) ?></a>
            <?php if ($role !== 'employee'): ?>
                <span class="crumb-separator" aria-hidden="true">/</span>
                <span><?= e($sectionTitle) ?></span>
            <?php endif; ?>
            <?php if (!empty($pageTitle)): ?>
                <span class="crumb-separator" aria-hidden="true">/</span>
                <span class="crumb-current" aria-current="page"><?= e($pageTitle === 'Employee Dashboard' ? 'Overview' : $pageTitle) ?></span>
            <?php endif; ?>
        </nav>
    </div>

    <div class="header-right top-bar-right">
        <!-- Theme Toggle Button (Light/Dark Mode) -->
        <button type="button" class="theme-toggle-btn" id="themeToggleBtn" aria-label="Toggle theme mode" title="Toggle theme mode">
            <?= icon('moon', 18) ?>
        </button>

        <?php if (isAuthenticated()): ?>
            <div class="rt-notify" id="rtNotify"
                data-enabled="<?= $rtEnabled ? '1' : '0' ?>"
                data-ws-url="<?= e($rtWsUrl) ?>"
                data-ticket-url="<?= e(url('/realtime/ticket')) ?>"
                data-history-url="<?= e(url('/api/v1/notifications?limit=8')) ?>"
                data-inbox-url="<?= e(url('/notifications')) ?>"
                data-base-url="<?= e(url('/')) ?>"
                data-csrf="<?= e(csrf_token()) ?>"
                data-unread="<?= (int)$rtUnread ?>">
                <button type="button" class="rt-bell" id="rtBell"
                    aria-haspopup="true" aria-expanded="false" aria-controls="rtPanel"
                    aria-label="Notifications<?= $rtUnread > 0 ? ', ' . (int)$rtUnread . ' unread' : '' ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false">
                        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                        <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                    </svg>
                    <strong class="rt-count" id="rtCount" <?= $rtUnread > 0 ? '' : 'hidden' ?>><?= $rtUnread > 99 ? '99+' : (int)$rtUnread ?></strong>
                    <i class="rt-dot" id="rtDot" data-state="<?= $rtEnabled ? 'connecting' : 'disabled' ?>" aria-hidden="true"></i>
                </button>
                <div class="rt-panel" id="rtPanel" role="region" aria-label="Recent notifications" hidden>
                    <div class="rt-panel-header">
                        <strong>Notifications</strong>
                        <small class="rt-conn" id="rtConn" role="status" aria-live="polite"><?= $rtEnabled ? 'Connecting…' : 'Live updates off' ?></small>
                    </div>
                    <ul class="rt-list" id="rtList">
                        <li class="rt-empty">Loading recent notifications…</li>
                    </ul>
                    <div class="rt-panel-footer">
                        <a href="<?= e(url('/notifications')) ?>">View all notifications</a>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php
        $headerProfileUrl = url('/employee/profile');
        $headerProfileTitle = 'View My Profile';
        if ($role === 'admin') {
            $headerProfileUrl = url('/admin/account');
            $headerProfileTitle = 'Account & Security Settings';
        } elseif ($role === 'hr') {
            $headerProfileUrl = !empty($user['employee_id'])
                ? url('/hr/employees/' . (int)$user['employee_id'])
                : url('/hr/dashboard');
            $headerProfileTitle = !empty($user['employee_id']) ? 'View My Profile' : 'HR Dashboard';
        }
        $avatarInitials = strtoupper(substr(trim($userName), 0, 2)) ?: 'U';
        ?>
        <a href="<?= $headerProfileUrl ?>"
           class="header-account-chip"
           title="<?= e($userName . ' — ' . $headerProfileTitle) ?>"
           aria-label="<?= e($userName . ' — ' . $headerProfileTitle) ?>">
            <span class="header-account-avatar role-<?= e($role) ?>" aria-hidden="true"><?= e($avatarInitials) ?></span>
        </a>
    </div>
</header>