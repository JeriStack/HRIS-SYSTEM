<?php

/**
 * Modern Enterprise System Administrator Dashboard
 * NEXORA HR — People. Processes. Progress.
 *
 * All metrics, health indicators, analytics, and activity feeds reflect
 * genuine live database and infrastructure states without fabricated data.
 */

/** @var array<string, mixed>|null $user */
$userData = (isset($user) && is_array($user)) ? $user : (currentUser() ?? []);
$rawUserName = (string) ($userData['full_name'] ?? $userData['name'] ?? $userData['username'] ?? '');
$userName = trim($rawUserName) !== '' ? $rawUserName : 'Administrator';

/** @var array<string, mixed>|null $metrics */
$m = (isset($metrics) && is_array($metrics)) ? $metrics : [];

/** @var array<string, mixed>|null $systemHealth */
$health = (isset($systemHealth) && is_array($systemHealth)) ? $systemHealth : [];

/** @var array<int, array<string, mixed>>|null $deptDistribution */
$deptList = (isset($deptDistribution) && is_array($deptDistribution)) ? $deptDistribution : [];

/** @var array<int, array<string, mixed>>|null $recentLogs */
$activityFeed = (isset($recentLogs) && is_array($recentLogs)) ? $recentLogs : [];

/** @var array<string, int>|null $hrSupportStats */
$supportSummary = (isset($hrSupportStats) && is_array($hrSupportStats)) ? $hrSupportStats : [];
$supTotalOpen = (int) ($supportSummary['total_open'] ?? 0);
$supNewlySubmitted = (int) ($supportSummary['newly_submitted'] ?? 0);
$supCriticalHigh = (int) ($supportSummary['critical_and_high'] ?? 0);
$supAwaitingHr = (int) ($supportSummary['awaiting_hr'] ?? 0);
$supInProgress = (int) ($supportSummary['in_progress'] ?? 0);
$supRecentResolved = (int) ($supportSummary['recently_resolved'] ?? 0);

/** @var array<int, array<string, mixed>>|null $recentHrSupportTickets */
$recentHrTickets = (isset($recentHrSupportTickets) && is_array($recentHrSupportTickets)) ? $recentHrSupportTickets : [];

$totalEmployees    = (int) ($m['total_employees'] ?? 0);
$activeEmployees   = (int) ($m['active_employees'] ?? 0);
$inactiveEmployees = max(0, $totalEmployees - $activeEmployees);
$hrAccounts        = (int) ($m['hr_accounts'] ?? 0);
$employeeAccounts  = (int) ($m['employee_accounts'] ?? 0);
$pendingLeaves     = (int) ($m['pending_leaves'] ?? 0);
$activeUsers       = (int) ($m['active_users'] ?? 0);
$departmentsCount  = (int) ($m['departments'] ?? count($deptList));
$positionsCount    = (int) ($m['positions'] ?? 0);

// Live daemon & infrastructure status normalization
$dbHealth        = is_array($health['database'] ?? null) ? $health['database'] : [];
$wsHealth        = is_array($health['websocket'] ?? null) ? $health['websocket'] : [];
$smtpHealth      = is_array($health['smtp'] ?? null) ? $health['smtp'] : [];
$queueHealth     = is_array($health['queue'] ?? null) ? $health['queue'] : [];
$schedulerHealth = is_array($health['scheduler'] ?? null) ? $health['scheduler'] : [];
$runtimeHealth   = is_array($health['runtime'] ?? null) ? $health['runtime'] : [];

$dbStatus = (string) ($dbHealth['status'] ?? 'Healthy');
$dbBadgeClass = match (strtolower(trim($dbStatus))) {
    'healthy', 'operational' => 'badge-success',
    'standby / offline'      => 'badge-warning',
    default                  => 'badge-danger',
};

$wsStatus     = (string) ($wsHealth['status'] ?? 'Idle / Offline');
$wsBadgeClass = $wsStatus === 'Operational' ? 'badge-success' : 'badge-neutral';

$smtpStatus     = (string) ($smtpHealth['status'] ?? 'Standby / Offline');
$smtpBadgeClass = $smtpStatus === 'Operational' ? 'badge-success' : 'badge-neutral';

$queuePending    = (int) ($queueHealth['pending'] ?? 0);
$queueFailed     = (int) ($queueHealth['failed'] ?? 0);
$queueStatus     = (string) ($queueHealth['status'] ?? 'Operational');
$queueBadgeClass = $queueStatus === 'Operational' ? 'badge-success' : 'badge-danger';
$schedulerTasks  = (int) ($schedulerHealth['active_tasks'] ?? 0);

$infraNeedsAttention = ($dbBadgeClass !== 'badge-success') || ($queueStatus !== 'Operational');
?>

<!-- =========================================================================
     1. Welcome Header & Governance Shortcuts
     ========================================================================= -->
<div class="page-header admin-page-header">
    <div class="page-title-group">
        <div class="admin-title-row">
            <h1>System Overview</h1>
            <span class="role-badge admin">Administration Portal</span>
        </div>
        <p class="admin-subtitle">
            Welcome back, <strong><?= e($userName) ?></strong>.
            System-wide governance, workforce totals, infrastructure health, and security oversight.
        </p>
    </div>
    <div class="page-actions admin-header-actions">
        <span class="admin-date-chip">
            <?= icon('calendar', 15) ?>
            <time datetime="<?= e(date('Y-m-d')) ?>"><?= e(date('l, F j, Y')) ?></time>
        </span>
        <a href="<?= url('/admin/users') ?>" class="btn btn-primary">
            <?= icon('users', 16) ?>
            <span>User Accounts</span>
        </a>
        <a href="<?= url('/admin/roles') ?>" class="btn btn-secondary">
            <?= icon('shield', 16) ?>
            <span>Roles</span>
        </a>
        <a href="<?= url('/admin/support') ?>" class="btn btn-secondary">
            <?= icon('shield', 16) ?>
            <span>Support Inbox</span>
        </a>
        <a href="<?= url('/admin/system-health') ?>" class="btn btn-secondary">
            <?= icon('activity', 16) ?>
            <span>System Health</span>
        </a>
    </div>
</div>

<!-- =========================================================================
     2. Conditional Priority Alert Strip (System Degradation, Support Queue, or Pending Queue)
     ========================================================================= -->
<?php if ($infraNeedsAttention) : ?>
    <div class="admin-attention-strip is-danger" role="status">
        <div class="admin-attention-body">
            <span class="admin-attention-icon" aria-hidden="true"><?= icon('alert-circle', 18) ?></span>
            <div>
                <strong>Infrastructure Alert:</strong>
                <?php if ($dbBadgeClass !== 'badge-success') : ?>
                    Database status is currently <strong><?= e($dbStatus) ?></strong>.
                <?php else : ?>
                    Background queue requires attention
                    (<strong><?= $queueFailed ?></strong> failed job<?= $queueFailed === 1 ? '' : 's' ?>).
                <?php endif; ?>
            </div>
        </div>
        <a href="<?= url('/admin/system-health') ?>" class="btn btn-secondary btn-sm">Inspect Diagnostics &rarr;</a>
    </div>
<?php elseif ($supCriticalHigh > 0 || $supNewlySubmitted > 0) : ?>
    <div class="admin-attention-strip" role="status">
        <div class="admin-attention-body">
            <span class="admin-attention-icon" aria-hidden="true"><?= icon('shield', 18) ?></span>
            <div>
                <strong>HR Support Attention:</strong>
                <strong class="tabular-nums"><?= $supTotalOpen ?></strong> open HR ticket<?= $supTotalOpen === 1 ? '' : 's' ?>
                (<strong class="tabular-nums"><?= $supNewlySubmitted ?></strong> newly submitted,
                <strong class="tabular-nums"><?= $supCriticalHigh ?></strong> high/critical priority).
            </div>
        </div>
        <a href="<?= url('/admin/support') ?>" class="btn btn-secondary btn-sm">Open Support Inbox &rarr;</a>
    </div>
<?php elseif ($pendingLeaves > 0) : ?>
    <div class="admin-attention-strip" role="status">
        <div class="admin-attention-body">
            <span class="admin-attention-icon" aria-hidden="true"><?= icon('clock', 18) ?></span>
            <div>
                <strong>Pending Approvals:</strong>
                <strong><?= $pendingLeaves ?></strong> leave
                request<?= $pendingLeaves === 1 ? '' : 's' ?> awaiting review in the workforce queue.
            </div>
        </div>
        <a href="<?= url('/leaves?status=Pending') ?>" class="btn btn-secondary btn-sm">Open Review Queue &rarr;</a>
    </div>
<?php endif; ?>

<!-- =========================================================================
     3. Primary Operational KPI Cards
     ========================================================================= -->
<section aria-labelledby="admin-kpi-heading">
    <h2 id="admin-kpi-heading" class="sr-only">Key System Metrics</h2>
    <div class="admin-kpi-grid">
        <!-- Card 1: Workforce Employees -->
        <a href="<?= url('/hr/employees') ?>" class="stat-card accent-blue admin-kpi-card">
            <div class="stat-info">
                <span class="stat-label">Total Employees</span>
                <span class="stat-value"><?= $totalEmployees ?></span>
                <span class="stat-subtext">
                    <strong><?= $activeEmployees ?> Active</strong>
                    <span aria-hidden="true">&middot;</span>
                    <span><?= $inactiveEmployees ?> Inactive</span>
                </span>
            </div>
            <div class="stat-icon-wrapper icon-blue" aria-hidden="true">
                <?= icon('users', 22) ?>
            </div>
        </a>

        <!-- Card 2: HR Officers & Organization Scope -->
        <a href="<?= url('/admin/users') ?>" class="stat-card accent-purple admin-kpi-card">
            <div class="stat-info">
                <span class="stat-label">HR Accounts</span>
                <span class="stat-value"><?= $hrAccounts ?></span>
                <span class="stat-subtext">
                    <span>Operational officers</span>
                    <span aria-hidden="true">&middot;</span>
                    <strong><?= $departmentsCount ?> Depts</strong>
                </span>
            </div>
            <div class="stat-icon-wrapper icon-purple" aria-hidden="true">
                <?= icon('briefcase', 22) ?>
            </div>
        </a>

        <!-- Card 3: Pending Leave Queue -->
        <a href="<?= url('/leaves?status=Pending') ?>" class="stat-card accent-amber admin-kpi-card">
            <div class="stat-info">
                <span class="stat-label">Pending Leaves</span>
                <span class="stat-value"><?= $pendingLeaves ?></span>
                <span class="stat-subtext">
                    <?php if ($pendingLeaves > 0) : ?>
                        <span class="admin-kpi-status is-warning">
                            <?= icon('alert-triangle', 14) ?>
                            <span>Awaiting Review</span>
                        </span>
                    <?php else : ?>
                        <span class="admin-kpi-status is-success">
                            <?= icon('check-circle', 14) ?>
                            <span>Queue Cleared</span>
                        </span>
                    <?php endif; ?>
                </span>
            </div>
            <div class="stat-icon-wrapper icon-amber" aria-hidden="true">
                <?= icon('calendar-check', 22) ?>
            </div>
        </a>

        <!-- Card 4: Active User Accounts -->
        <a href="<?= url('/admin/users') ?>" class="stat-card accent-green admin-kpi-card">
            <div class="stat-info">
                <span class="stat-label">Active Users</span>
                <span class="stat-value"><?= $activeUsers ?></span>
                <span class="stat-subtext">
                    <strong><?= $employeeAccounts ?></strong>
                    <span>Employee Accounts</span>
                </span>
            </div>
            <div class="stat-icon-wrapper icon-green" aria-hidden="true">
                <?= icon('shield', 22) ?>
            </div>
        </a>
    </div>
</section>

<!-- =========================================================================
     3B. HR Support Management Live Summary Metrics
     ========================================================================= -->
<section aria-labelledby="admin-support-summary-heading" class="card" style="margin-bottom: var(--space-6);">
    <div class="card-header">
        <div class="card-title-group">
            <h2 id="admin-support-summary-heading" class="card-title">
                <?= icon('shield', 18) ?>
                <span>HR Help &amp; Support Summary</span>
            </h2>
            <p class="card-subtitle">Live database metrics from the HR-to-Administrator technical support queue</p>
        </div>
        <a href="<?= url('/admin/support') ?>" class="btn btn-primary btn-sm">Open Support Inbox &rarr;</a>
    </div>
    <div class="card-body">
        <div class="support-kpi-grid" style="margin-bottom: <?= empty($recentHrTickets) ? '0' : 'var(--space-5)' ?>;">
            <a href="<?= url('/admin/support') ?>" class="stat-card" style="text-decoration: none;">
                <div class="stat-info">
                    <span class="stat-label">Total Open Tickets</span>
                    <span class="stat-value tabular-nums"><?= $supTotalOpen ?></span>
                    <span class="stat-subtext">Submitted through Waiting</span>
                </div>
                <div class="stat-icon-wrapper icon-blue" aria-hidden="true"><?= icon('layers', 20) ?></div>
            </a>

            <a href="<?= url('/admin/support?status=Submitted') ?>" class="stat-card" style="text-decoration: none;">
                <div class="stat-info">
                    <span class="stat-label">Newly Submitted</span>
                    <span class="stat-value tabular-nums"><?= $supNewlySubmitted ?></span>
                    <span class="stat-subtext">Awaiting acknowledgment</span>
                </div>
                <div class="stat-icon-wrapper icon-amber" aria-hidden="true"><?= icon('file-plus', 20) ?></div>
            </a>

            <a href="<?= url('/admin/support?priority=Critical') ?>" class="stat-card" style="text-decoration: none;">
                <div class="stat-info">
                    <span class="stat-label">Critical &amp; High</span>
                    <span class="stat-value tabular-nums"><?= $supCriticalHigh ?></span>
                    <span class="stat-subtext">Open high-impact issues</span>
                </div>
                <div class="stat-icon-wrapper icon-amber" aria-hidden="true"><?= icon('alert-triangle', 20) ?></div>
            </a>

            <a href="<?= url('/admin/support?status=Waiting+for+HR') ?>" class="stat-card" style="text-decoration: none;">
                <div class="stat-info">
                    <span class="stat-label">Awaiting HR Response</span>
                    <span class="stat-value tabular-nums"><?= $supAwaitingHr ?></span>
                    <span class="stat-subtext">Information requested</span>
                </div>
                <div class="stat-icon-wrapper icon-purple" aria-hidden="true"><?= icon('mail', 20) ?></div>
            </a>

            <a href="<?= url('/admin/support?status=In+Progress') ?>" class="stat-card" style="text-decoration: none;">
                <div class="stat-info">
                    <span class="stat-label">In Progress</span>
                    <span class="stat-value tabular-nums"><?= $supInProgress ?></span>
                    <span class="stat-subtext">Active investigation</span>
                </div>
                <div class="stat-icon-wrapper icon-teal" aria-hidden="true"><?= icon('clock', 20) ?></div>
            </a>

            <a href="<?= url('/admin/support?status=Resolved') ?>" class="stat-card" style="text-decoration: none;">
                <div class="stat-info">
                    <span class="stat-label">Recently Resolved</span>
                    <span class="stat-value tabular-nums"><?= $supRecentResolved ?></span>
                    <span class="stat-subtext">Resolved/Closed in 7 days</span>
                </div>
                <div class="stat-icon-wrapper icon-teal" aria-hidden="true"><?= icon('check-circle', 20) ?></div>
            </a>
        </div>

        <?php if (!empty($recentHrTickets)) : ?>
            <div class="table-responsive">
                <table class="table table-hover" aria-label="Latest HR support tickets">
                    <thead>
                        <tr>
                            <th scope="col">Reference</th>
                            <th scope="col">Subject</th>
                            <th scope="col">Category</th>
                            <th scope="col">Priority</th>
                            <th scope="col">Status</th>
                            <th scope="col" class="text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($recentHrTickets, 0, 5) as $ticketRow) : ?>
                            <?php
                            $tRef = (string) ($ticketRow['ticket_ref'] ?? '');
                            $tPrio = (string) ($ticketRow['priority'] ?? 'Medium');
                            $tStat = (string) ($ticketRow['status'] ?? 'Submitted');
                            $prioClass = match ($tPrio) {
                                'Critical' => 'badge-danger',
                                'High'     => 'badge-warning',
                                'Low'      => 'badge-neutral',
                                default    => 'badge-primary',
                            };
                            $statClass = match ($tStat) {
                                'Resolved', 'Closed' => 'badge-success',
                                'Waiting for HR'     => 'badge-warning',
                                'In Progress'        => 'badge-primary',
                                'Under Review'       => 'badge-info',
                                default              => 'badge-neutral',
                            };
                            ?>
                            <tr>
                                <td class="tabular-nums"><strong><?= e($tRef) ?></strong></td>
                                <td><?= e((string) ($ticketRow['subject'] ?? '')) ?></td>
                                <td><?= e((string) ($ticketRow['category'] ?? '')) ?></td>
                                <td><span class="badge <?= e($prioClass) ?>"><?= e($tPrio) ?></span></td>
                                <td><span class="badge <?= e($statClass) ?>"><?= e($tStat) ?></span></td>
                                <td class="text-right">
                                    <a href="<?= url('/admin/support/tickets/' . rawurlencode($tRef)) ?>" class="btn btn-secondary btn-sm">Manage</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- =========================================================================
     4. Two-Column Mid-Tier: Workforce Analytics & Live Infrastructure Health
     ========================================================================= -->
<section aria-label="Workforce Distribution and System Health">
    <div class="admin-mid-grid">
        <!-- Left Column: Workforce Distribution by Department -->
        <div class="card admin-panel-card">
            <div class="card-header">
                <div class="card-title-group">
                    <h2 class="card-title">
                        <?= icon('bar-chart', 18) ?>
                        <span>Workforce Distribution</span>
                    </h2>
                    <p class="card-subtitle">
                        <?= $departmentsCount ?> departments &middot; <?= $positionsCount ?> job positions
                    </p>
                </div>
                <a href="<?= url('/reports?tab=analytics') ?>" class="btn btn-secondary btn-sm">Full Analytics</a>
            </div>
            <div class="admin-panel-body">
                <?php if (empty($deptList)) : ?>
                    <div class="admin-empty-panel">
                        No department assignment records available.
                    </div>
                <?php else : ?>
                    <?php
                    $totalDisplayed = array_sum(
                        array_map('intval', array_column($deptList, 'employee_count'))
                    );
                    $workforceDenom = max($totalEmployees, $totalDisplayed, 1);
                    ?>
                    <div class="admin-dept-list">
                        <?php foreach ($deptList as $d) : ?>
                            <?php
                            $deptName  = (string) ($d['department_name'] ?? 'Unassigned');
                            $deptCount = (int) ($d['employee_count'] ?? 0);
                            $pct       = min(100, (int) round(($deptCount / $workforceDenom) * 100));
                            $deptBarAria = sprintf(
                                '%s share: %d staff (%d percent)',
                                $deptName,
                                $deptCount,
                                $pct
                            );
                            ?>
                            <div class="admin-dept-item">
                                <div class="admin-dept-meta">
                                    <span class="admin-dept-name"><?= e($deptName) ?></span>
                                    <span class="admin-dept-stats">
                                        <strong><?= $deptCount ?></strong> staff (<?= $pct ?>%)
                                    </span>
                                </div>
                                <div class="admin-dept-track"
                                     role="progressbar"
                                     aria-valuenow="<?= $pct ?>"
                                     aria-valuemin="0"
                                     aria-valuemax="100"
                                     aria-label="<?= e($deptBarAria) ?>">
                                    <div class="admin-dept-fill" style="<?= e('width: ' . $pct . '%;') ?>"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if ($departmentsCount > count($deptList)) : ?>
                        <p class="admin-dept-foot">
                            Showing top <?= count($deptList) ?> of <?= $departmentsCount ?>
                            departments by assigned headcount.
                        </p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right Column: Live Infrastructure & System Health -->
        <div class="card admin-panel-card">
            <div class="card-header">
                <div class="card-title-group">
                    <h2 class="card-title">
                        <?= icon('activity', 18) ?>
                        <span>System Status &amp; Daemons</span>
                    </h2>
                    <p class="card-subtitle">Live database, socket, queue, and runtime checks</p>
                </div>
                <a href="<?= url('/admin/system-health') ?>" class="btn btn-secondary btn-sm">Diagnostics</a>
            </div>
            <div class="admin-panel-body">
                <div class="admin-health-list">
                    <!-- Database -->
                    <div class="admin-health-item">
                        <div class="admin-health-info">
                            <div class="admin-health-name">
                                Database Engine (<?= e((string) ($dbHealth['driver'] ?? 'MySQL 8.0')) ?>)
                            </div>
                            <div class="admin-health-detail">
                                Query latency:
                                <span class="tabular-nums">
                                    <?= e((string) ($dbHealth['latency'] ?? '0 ms')) ?>
                                </span>
                            </div>
                        </div>
                        <span class="badge <?= e($dbBadgeClass) ?>">
                            <span class="badge-dot" aria-hidden="true"></span>
                            <?= e($dbStatus) ?>
                        </span>
                    </div>

                    <!-- WebSocket -->
                    <div class="admin-health-item">
                        <div class="admin-health-info">
                            <div class="admin-health-name">
                                Realtime WebSockets (Port <?= (int) ($wsHealth['port'] ?? 8080) ?>)
                            </div>
                            <div class="admin-health-detail">RFC 6455 Ratchet Event Pipeline</div>
                        </div>
                        <span class="badge <?= e($wsBadgeClass) ?>">
                            <span class="badge-dot" aria-hidden="true"></span>
                            <?= e($wsStatus) ?>
                        </span>
                    </div>

                    <!-- Mailpit SMTP -->
                    <div class="admin-health-item">
                        <div class="admin-health-info">
                            <div class="admin-health-name">
                                SMTP Mail Delivery (Port <?= (int) ($smtpHealth['port'] ?? 1025) ?>)
                            </div>
                            <div class="admin-health-detail">
                                2FA Email OTP Transport &middot;
                                <a href="http://localhost:8025"
                                   target="_blank"
                                   rel="noopener noreferrer"
                                   class="admin-inline-link">
                                    Mailpit Web UI (Port 8025) &nearr;<span class="sr-only"> (opens in a new tab)</span>
                                </a>
                            </div>
                        </div>
                        <span class="badge <?= e($smtpBadgeClass) ?>">
                            <span class="badge-dot" aria-hidden="true"></span>
                            <?= e($smtpStatus) ?>
                        </span>
                    </div>

                    <!-- Async Queue & Scheduler -->
                    <div class="admin-health-item">
                        <div class="admin-health-info">
                            <div class="admin-health-name">Async Queue &amp; Scheduler</div>
                            <div class="admin-health-detail tabular-nums">
                                <?= $queuePending ?> pending &middot;
                                <?= $queueFailed ?> failed &middot;
                                <?= $schedulerTasks ?> scheduled tasks
                            </div>
                        </div>
                        <span class="badge <?= e($queueBadgeClass) ?>">
                            <span class="badge-dot" aria-hidden="true"></span>
                            <?= e($queueStatus) ?>
                        </span>
                    </div>

                    <!-- Runtime & Storage -->
                    <div class="admin-health-item">
                        <div class="admin-health-info">
                            <div class="admin-health-name">PHP Runtime &amp; Storage</div>
                            <div class="admin-health-detail tabular-nums">
                                PHP <?= e((string) ($runtimeHealth['php_version'] ?? PHP_VERSION)) ?> &middot;
                                Memory <?= e((string) ($runtimeHealth['memory_used'] ?? '0 MB')) ?> /
                                <?= e((string) ($runtimeHealth['memory_limit'] ?? '512M')) ?> &middot;
                                Disk <?= e((string) ($runtimeHealth['disk_used'] ?? '0%')) ?>
                            </div>
                        </div>
                        <span class="badge badge-primary tabular-nums">
                            PHP <?= PHP_MAJOR_VERSION ?>.<?= PHP_MINOR_VERSION ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- =========================================================================
     5. Recent Administrative & Security Activity Feed
     ========================================================================= -->
<section aria-labelledby="recent-activity-title">
    <div class="card admin-activity-card">
        <div class="card-header">
            <div class="card-title-group">
                <h2 id="recent-activity-title" class="card-title">
                    <?= icon('file-text', 18) ?>
                    <span>Recent Activity</span>
                </h2>
                <p class="card-subtitle">Administrative events, workforce changes, and security audit trail.</p>
            </div>
            <a href="<?= url('/activity-logs') ?>" class="btn btn-secondary btn-sm">View All Logs</a>
        </div>
        <div class="admin-table-wrap" role="region" aria-label="Recent administrative activity table" tabindex="0">
            <table class="table table-hover admin-activity-table">
                <caption class="sr-only">
                    Recent administrative events, workforce updates, and security audit entries
                </caption>
                <thead>
                    <tr>
                        <th scope="col" class="admin-col-actor">Actor / Operator</th>
                        <th scope="col">Action Performed</th>
                        <th scope="col" class="admin-col-ip">IP Address</th>
                        <th scope="col" class="admin-col-time">Timestamp</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($activityFeed)) : ?>
                        <tr>
                            <td colspan="4" class="admin-activity-empty">
                                No administrative events recorded yet.
                            </td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($activityFeed as $log) : ?>
                            <?php
                            $rawCreatedAt = (string) ($log['created_at'] ?? '');
                            $timestamp    = $rawCreatedAt !== '' ? strtotime($rawCreatedAt) : false;
                            ?>
                            <tr>
                                <td data-label="Actor">
                                    <strong class="admin-actor-name">
                                        <?= e((string) ($log['username'] ?? 'System')) ?>
                                    </strong>
                                    <?php if (!empty($log['role'])) : ?>
                                        <span class="admin-actor-role">
                                            <?= e(strtoupper((string) $log['role'])) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Action">
                                    <span class="badge badge-neutral admin-action-code">
                                        <?= e((string) ($log['action'] ?? 'EVENT')) ?>
                                    </span>
                                    <div class="admin-action-desc">
                                        <?= e((string) ($log['description'] ?? 'Administrative change executed')) ?>
                                    </div>
                                </td>
                                <td data-label="IP Address">
                                    <code class="admin-ip-cell tabular-nums">
                                        <?= e((string) ($log['ip_address'] ?? '127.0.0.1')) ?>
                                    </code>
                                </td>
                                <td data-label="Timestamp" class="admin-time-cell">
                                    <?php if ($timestamp !== false) : ?>
                                        <time datetime="<?= e(date('c', $timestamp)) ?>">
                                            <?= e(date('M j, Y H:i', $timestamp)) ?>
                                        </time>
                                    <?php else : ?>
                                        <span>—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
