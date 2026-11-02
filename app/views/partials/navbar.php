<?php
/**
 * Top Navigation Bar Partial
 * Employee HRIS & Leave Management System
 */

$user = currentUser() ?? [];
$userName = $user['name'] ?? $user['username'] ?? 'User';
$role = strtolower($user['role'] ?? 'employee');

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
        <button type="button" class="sidebar-toggle header-toggle" id="sidebarToggle" aria-label="Toggle navigation drawer" aria-expanded="false" aria-controls="appSidebar">
            ☰
        </button>
        <div class="header-breadcrumb top-bar-title">
            <span class="system-status-indicator" title="System Status: Operational" aria-hidden="true"></span>
            <strong><?= e($pageTitle ?? 'NEXORA HR') ?></strong>
        </div>
    </div>

    <div class="header-right top-bar-right">
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
