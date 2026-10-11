<?php
/**
 * Top Navigation Bar Partial
 * Employee HRIS & Leave Management System
 */

$user = currentUser() ?? [];
$userName = $user['name'] ?? $user['username'] ?? 'HR Admin';
?>
<header class="top-bar">
    <div class="top-bar-left">
        <button type="button" class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle Navigation">
            ☰
        </button>
        <div class="top-bar-title">
            <span class="system-status-indicator online"></span>
            <strong><?= e($pageTitle ?? 'Human Resources Management') ?></strong>
        </div>
    </div>

    <div class="top-bar-right">
        <span class="badge badge-info" style="margin-right: 0.75rem;">
            <?= e(strtoupper($user['role'] ?? 'HR')) ?> ACCESS
        </span>
        <span style="font-size: 0.875rem; color: #475569; margin-right: 1rem;">
            <?= e($userName) ?>
        </span>
        <form method="POST" action="<?= url('/logout') ?>" style="margin: 0; display: inline;">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-secondary btn-sm" style="padding: 0.35rem 0.75rem;">
                Sign Out
            </button>
        </form>
    </div>
</header>
