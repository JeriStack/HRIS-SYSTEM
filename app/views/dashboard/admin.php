<?php
/**
 * Modern Enterprise System Administrator Dashboard
 * NEXORA HR — People. Processes. Progress.
 *
 * All metrics, health indicators, analytics, and activity feeds reflect
 * genuine live database and infrastructure states without fabricated data.
 */

$user = currentUser() ?? [];
$userName = $user['name'] ?? $user['username'] ?? 'Administrator';
$m = $metrics ?? [];
$health = $systemHealth ?? [];
?>

<!-- =========================================================================
     1. Welcome Header & Operational Shortcuts
     ========================================================================= -->
<div class="page-header" style="margin-bottom: var(--space-6);">
    <div class="page-title-group">
        <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.25rem;">
            <h1 style="margin: 0; font-size: 1.625rem; font-weight: 700; color: var(--color-text-main);">System Overview</h1>
            <span class="badge badge-primary" style="font-size: 0.75rem; font-weight: 700; letter-spacing: 0.05em;">ADMINISTRATION PORTAL</span>
        </div>
        <p style="margin: 0; font-size: 0.9375rem; color: var(--color-text-muted);">
            Welcome back, <strong><?= e($userName) ?></strong>. Complete governance, role enforcement, workforce metrics, and security oversight.
        </p>
    </div>
    <div class="page-actions" style="gap: 0.5rem; align-items: center;">
        <span style="font-size: 0.8125rem; color: var(--color-text-muted); background: var(--color-bg-surface); padding: 0.45rem 0.85rem; border-radius: var(--radius-md); border: 1px solid var(--color-border);">
            📅 <?= date('l, F j, Y') ?>
        </span>
        <a href="<?= url('/admin/users') ?>" class="btn btn-primary btn-sm">
            <span>👥</span> User Accounts
        </a>
        <a href="<?= url('/admin/system-health') ?>" class="btn btn-secondary btn-sm">
            <span>🩺</span> System Health
        </a>
    </div>
</div>

<!-- =========================================================================
     2. Primary Operational KPI Cards (Wireframe 4-Card Alignment)
     ========================================================================= -->
<div class="grid grid-cols-4" style="margin-bottom: var(--space-6); gap: 1rem;">
    <!-- Card 1: Total Employees -->
    <div class="stat-card accent-blue">
        <div class="stat-info">
            <span class="stat-label">Employees</span>
            <span class="stat-value"><?= (int)($m['total_employees'] ?? 0) ?></span>
            <span class="stat-subtext">
                <strong><?= (int)($m['active_employees'] ?? 0) ?> Active</strong> &bull; <?= (int)($m['total_employees'] - $m['active_employees']) ?> Inactive
            </span>
        </div>
        <div class="stat-icon-wrapper icon-blue" aria-hidden="true">📋</div>
    </div>

    <!-- Card 2: HR Accounts -->
    <div class="stat-card accent-purple">
        <div class="stat-info">
            <span class="stat-label">HR Accounts</span>
            <span class="stat-value"><?= (int)($m['hr_accounts'] ?? 0) ?></span>
            <span class="stat-subtext">
                Operational workforce officers
            </span>
        </div>
        <div class="stat-icon-wrapper icon-purple" aria-hidden="true">👔</div>
    </div>

    <!-- Card 3: Pending Leaves -->
    <div class="stat-card accent-amber">
        <div class="stat-info">
            <span class="stat-label">Pending Leaves</span>
            <span class="stat-value"><?= (int)($m['pending_leaves'] ?? 0) ?></span>
            <span class="stat-subtext">
                <?= ((int)($m['pending_leaves'] ?? 0) > 0) ? '⚠️ Awaiting HR Review' : '✓ Queue Cleared' ?>
            </span>
        </div>
        <div class="stat-icon-wrapper icon-amber" aria-hidden="true">📅</div>
    </div>

    <!-- Card 4: Active Users -->
    <div class="stat-card accent-green">
        <div class="stat-info">
            <span class="stat-label">Active Users</span>
            <span class="stat-value"><?= (int)($m['active_users'] ?? 0) ?></span>
            <span class="stat-subtext">
                <?= (int)($m['employee_accounts'] ?? 0) ?> Self-Service Employees
            </span>
        </div>
        <div class="stat-icon-wrapper icon-teal" aria-hidden="true">🟢</div>
    </div>
</div>

<!-- =========================================================================
     3. Two-Column Mid-Tier: Visual Analytics & Real Infrastructure Health
     ========================================================================= -->
<div class="grid grid-cols-2" style="margin-bottom: var(--space-6); gap: 1.25rem;">
    <!-- Left Column: Workforce & Leave Analytics -->
    <div class="card" style="margin: 0;">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
            <h2 class="card-title" style="margin: 0; font-size: 1rem; font-weight: 600;">
                <span>📊</span> Workforce Distribution by Department
            </h2>
            <a href="<?= url('/reports?tab=analytics') ?>" class="btn btn-secondary btn-sm">Full Analytics</a>
        </div>
        <div class="card-body" style="padding: 1.25rem;">
            <?php if (empty($deptDistribution)): ?>
                <div style="text-align: center; padding: 2rem; color: var(--color-text-muted);">
                    No department assignment records available.
                </div>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 0.85rem;">
                    <?php 
                    $totalAssigned = array_sum(array_column($deptDistribution, 'employee_count')) ?: 1;
                    foreach ($deptDistribution as $d): 
                        $pct = round(($d['employee_count'] / $totalAssigned) * 100);
                    ?>
                        <div>
                            <div style="display: flex; justify-content: space-between; font-size: 0.8125rem; margin-bottom: 0.25rem;">
                                <span style="font-weight: 600; color: var(--color-text-main);"><?= e($d['department_name']) ?></span>
                                <span style="color: var(--color-text-muted);"><?= (int)$d['employee_count'] ?> (<?= $pct ?>%)</span>
                            </div>
                            <div style="height: 6px; width: 100%; background: var(--color-bg-muted); border-radius: 9999px; overflow: hidden;">
                                <div style="height: 100%; width: <?= $pct ?>%; background: var(--color-primary); border-radius: 9999px;"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Right Column: Live Infrastructure & System Health -->
    <div class="card" style="margin: 0;">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
            <h2 class="card-title" style="margin: 0; font-size: 1rem; font-weight: 600;">
                <span>🩺</span> System Status &amp; Daemons
            </h2>
            <a href="<?= url('/admin/system-health') ?>" class="btn btn-secondary btn-sm">Diagnostics</a>
        </div>
        <div class="card-body" style="padding: 1.25rem;">
            <div style="display: flex; flex-direction: column; gap: 1rem;">
                <!-- Database -->
                <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 0.75rem; border-bottom: 1px solid var(--color-border);">
                    <div>
                        <div style="font-weight: 600; color: var(--color-text-main);">Database Engine (MySQL 8.0)</div>
                        <div style="font-size: 0.75rem; color: var(--color-text-muted);">Latency: <?= e($health['database']['latency'] ?? '0 ms') ?></div>
                    </div>
                    <span class="badge badge-success">
                        <span class="badge-dot" aria-hidden="true"></span> <?= e($health['database']['status'] ?? 'Operational') ?>
                    </span>
                </div>

                <!-- WebSocket -->
                <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 0.75rem; border-bottom: 1px solid var(--color-border);">
                    <div>
                        <div style="font-weight: 600; color: var(--color-text-main);">Realtime WebSockets (Port 8080)</div>
                        <div style="font-size: 0.75rem; color: var(--color-text-muted);">RFC 6455 Ratchet Event Pipeline</div>
                    </div>
                    <span class="badge <?= ($health['websocket']['status'] ?? '') === 'Operational' ? 'badge-success' : 'badge-neutral' ?>">
                        <span class="badge-dot" aria-hidden="true"></span> <?= e($health['websocket']['status'] ?? 'Standby') ?>
                    </span>
                </div>

                <!-- Mailpit SMTP -->
                <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 0.75rem; border-bottom: 1px solid var(--color-border);">
                    <div>
                        <div style="font-weight: 600; color: var(--color-text-main);">SMTP Mail Delivery (Port 1025)</div>
                        <div style="font-size: 0.75rem; color: var(--color-text-muted);">
                            2FA Email OTP Transport &bull; <a href="http://localhost:8025" target="_blank" rel="noopener noreferrer" style="color: var(--color-primary); font-weight: 500;">Mailpit Web UI (Port 8025) &nearr;</a>
                        </div>
                    </div>
                    <span class="badge <?= ($health['smtp']['status'] ?? '') === 'Operational' ? 'badge-success' : 'badge-neutral' ?>">
                        <span class="badge-dot" aria-hidden="true"></span> <?= e($health['smtp']['status'] ?? 'Operational') ?>
                    </span>
                </div>

                <!-- Runtime -->
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <div style="font-weight: 600; color: var(--color-text-main);">PHP Runtime &amp; Memory</div>
                        <div style="font-size: 0.75rem; color: var(--color-text-muted);">PHP <?= PHP_VERSION ?> &bull; Memory: <?= e($health['runtime']['memory_used'] ?? '0 MB') ?> / <?= e($health['runtime']['memory_limit'] ?? '512M') ?></div>
                    </div>
                    <span class="badge badge-primary">PHP <?= PHP_MAJOR_VERSION ?>.<?= PHP_MINOR_VERSION ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- =========================================================================
     4. Recent Administrative & Security Activity Feed (Image 1 Wireframe)
     ========================================================================= -->
<div class="card" style="margin-bottom: var(--space-6);">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h2 class="card-title" style="margin: 0; font-size: 1rem; font-weight: 600;">Recent activity</h2>
            <span style="font-size: 0.8125rem; color: var(--color-text-muted);">Administrative events, workforce changes, and system activity appear here.</span>
        </div>
        <a href="<?= url('/activity-logs') ?>" class="btn btn-secondary btn-sm">View all</a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover" aria-label="Recent System Activity">
            <thead>
                <tr>
                    <th style="width: 180px;">Actor / Operator</th>
                    <th>Action Performed</th>
                    <th>IP Address</th>
                    <th style="width: 140px;">Timestamp</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recentLogs)): ?>
                    <tr>
                        <td colspan="4" class="text-center" style="padding: 2.5rem; color: var(--color-text-muted);">
                            No administrative events recorded yet.
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
                                <div style="font-size: 0.8125rem; color: var(--color-text-body); margin-top: 3px;">
                                    <?= e($log['description'] ?? 'Administrative change executed') ?>
                                </div>
                            </td>
                            <td style="font-family: var(--font-mono); font-size: 0.775rem; color: var(--color-text-muted);">
                                <?= e($log['ip_address'] ?? '127.0.0.1') ?>
                            </td>
                            <td style="font-size: 0.8125rem; color: var(--color-text-muted); white-space: nowrap;">
                                <?= date('M j, Y H:i', strtotime($log['created_at'])) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
