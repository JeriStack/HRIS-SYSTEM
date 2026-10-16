<?php
/**
 * System Administrator Dashboard View
 * Employee HRIS & Leave Management System
 */

$empStats = $metrics['employees'] ?? [];
$userStats = $metrics['users'] ?? [];
?>
<div class="page-header">
    <div class="page-title-group">
        <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.35rem;">
            <h1>Administrator Console</h1>
            <span class="badge badge-info" style="font-weight: 700; letter-spacing: 0.05em;">ADMINISTRATOR CONSOLE</span>
        </div>
        <p>System-wide operational oversight, role governance, workforce metrics, and security audit log.</p>
    </div>
    <div class="page-actions">
        <span style="font-size: 0.8125rem; color: var(--color-text-muted); background: var(--color-bg-surface); padding: 0.45rem 0.85rem; border-radius: var(--radius-md); border: 1px solid var(--color-border);">
            📅 <?= date('l, F j, Y') ?>
        </span>
        <a href="<?= url('/admin/users') ?>" class="btn btn-primary btn-sm">
            <span>👥</span> Manage User Accounts
        </a>
    </div>
</div>

<!-- System-Wide Operational Metric Cards -->
<div class="grid grid-cols-4" style="margin-bottom: var(--space-6);">
    <!-- Stat 1: Total User Accounts -->
    <div class="stat-card">
        <div class="stat-info">
            <span class="stat-label">User Accounts</span>
            <span class="stat-value"><?= (int)($userStats['total'] ?? 0) ?></span>
            <span class="stat-subtext">
                <strong><?= (int)($userStats['active'] ?? 0) ?> Active</strong> &bull; <?= (int)($userStats['admin_count'] ?? 0) ?> Admins, <?= (int)($userStats['hr_count'] ?? 0) ?> HR
            </span>
        </div>
        <div class="stat-icon-wrapper icon-purple" aria-hidden="true">👥</div>
    </div>

    <!-- Stat 2: Total Active Workforce -->
    <div class="stat-card">
        <div class="stat-info">
            <span class="stat-label">Active Workforce</span>
            <span class="stat-value"><?= (int)($empStats['active'] ?? 0) ?></span>
            <span class="stat-subtext">
                <?= (int)($empStats['total'] ?? 0) ?> Total Records &bull; <?= (int)($empStats['inactive'] ?? 0) ?> Inactive
            </span>
        </div>
        <div class="stat-icon-wrapper icon-blue" aria-hidden="true">📋</div>
    </div>

    <!-- Stat 3: Organization Units -->
    <div class="stat-card">
        <div class="stat-info">
            <span class="stat-label">Departments & Roles</span>
            <span class="stat-value"><?= (int)($metrics['departments'] ?? 0) ?></span>
            <span class="stat-subtext">
                Across <?= (int)($metrics['positions'] ?? 0) ?> Approved Positions
            </span>
        </div>
        <div class="stat-icon-wrapper icon-teal" aria-hidden="true">🏢</div>
    </div>

    <!-- Stat 4: Pending Leaves -->
    <div class="stat-card">
        <div class="stat-info">
            <span class="stat-label">Pending Leave Queue</span>
            <span class="stat-value"><?= (int)($metrics['pending_leaves'] ?? 0) ?></span>
            <span class="stat-subtext">
                <?= ((int)($metrics['pending_leaves'] ?? 0) > 0) ? '⚠️ Requires HR Review' : '✓ All Requests Cleared' ?>
            </span>
        </div>
        <div class="stat-icon-wrapper icon-amber" aria-hidden="true">📅</div>
    </div>
</div>

<!-- Administrative Control Hub Shortcuts -->
<div class="card" style="margin-bottom: var(--space-6);">
    <div class="card-header">
        <h2 class="card-title"><span>⚡</span> Privileged Administration Hub</h2>
        <span style="font-size: 0.8125rem; color: var(--color-text-muted);">Strict Server-Enforced RBAC Governance</span>
    </div>
    <div class="card-body" style="padding: 1.25rem;">
        <div class="grid grid-cols-4">
            <a href="<?= url('/admin/users') ?>" class="card" style="padding: 1rem; margin: 0; text-decoration: none; border: 1px solid var(--color-border); border-radius: var(--radius-md); transition: transform 0.15s ease;">
                <div style="font-weight: 600; color: var(--color-text-main); margin-bottom: 0.25rem;">👥 User Accounts & Roles</div>
                <div style="font-size: 0.775rem; color: var(--color-text-muted);">Assign roles, review status, and prevent administrator lockouts.</div>
            </a>
            <a href="<?= url('/hr/employees') ?>" class="card" style="padding: 1rem; margin: 0; text-decoration: none; border: 1px solid var(--color-border); border-radius: var(--radius-md); transition: transform 0.15s ease;">
                <div style="font-weight: 600; color: var(--color-text-main); margin-bottom: 0.25rem;">📋 Employee Directory</div>
                <div style="font-size: 0.775rem; color: var(--color-text-muted);">Inspect workforce directory, deactivation records, and profiles.</div>
            </a>
            <a href="<?= url('/leaves') ?>" class="card" style="padding: 1rem; margin: 0; text-decoration: none; border: 1px solid var(--color-border); border-radius: var(--radius-md); transition: transform 0.15s ease;">
                <div style="font-weight: 600; color: var(--color-text-main); margin-bottom: 0.25rem;">📅 Leave Management</div>
                <div style="font-size: 0.775rem; color: var(--color-text-muted);">Review organization-wide leave requests and annual balances.</div>
            </a>
            <a href="<?= url('/dsa') ?>" class="card" style="padding: 1rem; margin: 0; text-decoration: none; border: 1px solid var(--color-border); border-radius: var(--radius-md); transition: transform 0.15s ease;">
                <div style="font-weight: 600; color: var(--color-text-main); margin-bottom: 0.25rem;">⚡ DSA Laboratory</div>
                <div style="font-size: 0.775rem; color: var(--color-text-muted);">Interactive performance benchmarks for BST, Queue, and Stack.</div>
            </a>
        </div>
    </div>
</div>

<!-- Two-Column Content Region: Recent Users & System Audit Logs -->
<div class="grid grid-cols-2" style="align-items: start;">
    <!-- Panel 1: User Account Directory Summary -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title"><span>👥</span> Recent User Accounts</h2>
            <a href="<?= url('/admin/users') ?>" class="btn btn-secondary btn-sm">View All Users</a>
        </div>
        <div class="table-responsive">
            <table class="table table-hover" aria-label="Recent User Accounts">
                <thead>
                    <tr>
                        <th>Account / Username</th>
                        <th>Assigned Role</th>
                        <th>Account Status</th>
                        <th>Registered Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentUsers)): ?>
                        <tr>
                            <td colspan="4" class="text-center" style="padding: 2rem; color: var(--color-text-muted);">
                                No user accounts registered.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recentUsers as $u): ?>
                            <tr>
                                <td>
                                    <strong><?= e($u['username']) ?></strong>
                                    <?php if (!empty($u['first_name'])): ?>
                                        <div style="font-size: 0.75rem; color: var(--color-text-muted);">
                                            <?= e($u['first_name'] . ' ' . $u['last_name']) ?> (<?= e($u['employee_no']) ?>)
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="role-badge <?= e(strtolower($u['role'])) ?>">
                                        <?= e(strtoupper($u['role'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge <?= ($u['status'] ?? 'Active') === 'Active' ? 'badge-success' : 'badge-danger' ?>">
                                        <span class="badge-dot" aria-hidden="true"></span>
                                        <?= e($u['status'] ?? 'Active') ?>
                                    </span>
                                </td>
                                <td style="font-size: 0.8125rem; color: var(--color-text-muted);">
                                    <?= date('M j, Y', strtotime($u['created_at'])) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Panel 2: Live Activity Audit Trail -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title"><span>📜</span> System Security Audit Trail</h2>
            <a href="<?= url('/activity-logs') ?>" class="btn btn-secondary btn-sm">Full Audit Trail</a>
        </div>
        <div class="table-responsive">
            <table class="table table-hover" aria-label="System Activity Logs">
                <thead>
                    <tr>
                        <th>Operator</th>
                        <th>Action Performed</th>
                        <th>IP Address</th>
                        <th>Timestamp</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentLogs)): ?>
                        <tr>
                            <td colspan="4" class="text-center" style="padding: 2rem; color: var(--color-text-muted);">
                                No security logs recorded.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recentLogs as $log): ?>
                            <tr>
                                <td>
                                    <strong><?= e($log['username'] ?? 'System') ?></strong>
                                    <?php if (!empty($log['role'])): ?>
                                        <div style="font-size: 0.725rem; color: var(--color-text-muted);">
                                            <?= e(strtoupper($log['role'])) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-neutral" style="font-family: var(--font-mono); font-size: 0.7rem;">
                                        <?= e($log['action']) ?>
                                    </span>
                                    <div style="font-size: 0.775rem; color: var(--color-text-body); margin-top: 2px;">
                                        <?= e($log['description']) ?>
                                    </div>
                                </td>
                                <td style="font-family: var(--font-mono); font-size: 0.75rem; color: var(--color-text-muted);">
                                    <?= e($log['ip_address'] ?? '127.0.0.1') ?>
                                </td>
                                <td style="font-size: 0.775rem; color: var(--color-text-muted); white-space: nowrap;">
                                    <?= date('M j, H:i', strtotime($log['created_at'])) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>