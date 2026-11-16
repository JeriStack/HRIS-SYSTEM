<?php
/**
 * HR Operations Dashboard View
 * Employee HRIS & Leave Management System
 */
?>
<div class="page-header">
    <div class="page-title-group">
        <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.35rem;">
            <h1>Human Resources Management</h1>
            <span class="role-badge hr" style="font-weight: 700; letter-spacing: 0.05em;">HR OPERATIONAL PORTAL</span>
        </div>
        <p>Workforce daily operations, employee profiles, department records, and pending leave application reviews.</p>
    </div>
    <div class="page-actions">
        <span style="font-size: 0.8125rem; color: var(--color-text-muted); background: var(--color-bg-surface); padding: 0.45rem 0.85rem; border-radius: var(--radius-md); border: 1px solid var(--color-border);">
            📅 <?= date('l, F j, Y') ?>
        </span>
        <a href="<?= url('/hr/employees/create') ?>" class="btn btn-primary btn-sm">
            <span>➕</span> Add New Employee
        </a>
    </div>
</div>

<!-- Operational Metric Cards Grid -->
<div class="grid grid-cols-4" style="margin-bottom: var(--space-6);">
    <!-- Stat 1: Active Employees -->
    <div class="stat-card">
        <div class="stat-info">
            <span class="stat-label">Active Employees</span>
            <span class="stat-value"><?= (int)($metrics['total_employees'] ?? 0) ?></span>
            <span class="stat-subtext">Active Personnel On Payroll</span>
        </div>
        <div class="stat-icon-wrapper icon-blue" aria-hidden="true">👥</div>
    </div>

    <!-- Stat 2: Pending Leave Requests Queue -->
    <div class="stat-card">
        <div class="stat-info">
            <span class="stat-label">Pending Leaves</span>
            <span class="stat-value"><?= (int)($metrics['pending_leaves'] ?? 0) ?></span>
            <span class="stat-subtext">
                <?php if ((int)($metrics['pending_leaves'] ?? 0) > 0): ?>
                    <span style="color: var(--color-warning); font-weight: 600;">⚠️ Awaiting HR Review</span>
                <?php else: ?>
                    <span style="color: var(--color-success); font-weight: 600;">✓ Queue Clear</span>
                <?php endif; ?>
            </span>
        </div>
        <div class="stat-icon-wrapper icon-amber" aria-hidden="true">📅</div>
    </div>

    <!-- Stat 3: Total Departments -->
    <div class="stat-card">
        <div class="stat-info">
            <span class="stat-label">Departments</span>
            <span class="stat-value"><?= (int)($metrics['total_departments'] ?? 0) ?></span>
            <span class="stat-subtext">Operational Divisions</span>
        </div>
        <div class="stat-icon-wrapper icon-teal" aria-hidden="true">🏢</div>
    </div>

    <!-- Stat 4: Job Positions -->
    <div class="stat-card">
        <div class="stat-info">
            <span class="stat-label">Job Positions</span>
            <span class="stat-value"><?= (int)($metrics['total_positions'] ?? 0) ?></span>
            <span class="stat-subtext">Standardized Roles</span>
        </div>
        <div class="stat-icon-wrapper icon-purple" aria-hidden="true">💼</div>
    </div>
</div>

<!-- Pending Leaves Attention Banner (if pending) -->
<?php if ((int)($metrics['pending_leaves'] ?? 0) > 0): ?>
    <div class="alert alert-warning" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
        <div style="display: flex; align-items: center; gap: 0.75rem;">
            <span style="font-size: 1.25rem;">⚠️</span>
            <div>
                <strong>Action Required:</strong> You have <strong><?= (int)$metrics['pending_leaves'] ?> pending leave application(s)</strong> awaiting management decision.
            </div>
        </div>
        <a href="<?= url('/leaves') ?>" class="btn btn-warning btn-sm" style="background-color: #B45309; color: #FFFFFF;">
            Open Review Queue &rarr;
        </a>
    </div>
<?php endif; ?>

<!-- Quick Operational Hub -->
<div class="card" style="margin-bottom: var(--space-6);">
    <div class="card-header">
        <h2 class="card-title"><span>⚡</span> Daily HR Workflows</h2>
        <span style="font-size: 0.8125rem; color: var(--color-text-muted);">Quick Operational Shortcuts</span>
    </div>
    <div class="card-body" style="padding: 1.25rem;">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem;">
            <a href="<?= url('/hr/employees') ?>" class="card" style="padding: 1rem; margin: 0; text-decoration: none; border: 1px solid var(--color-border); border-radius: var(--radius-md); transition: transform 0.15s ease;">
                <div style="font-weight: 600; color: var(--color-text-main); margin-bottom: 0.25rem;">📋 Employee Directory</div>
                <div style="font-size: 0.775rem; color: var(--color-text-muted);">Search, filter, update details, or manage employee records.</div>
            </a>
            <a href="<?= url('/leaves') ?>" class="card" style="padding: 1rem; margin: 0; text-decoration: none; border: 1px solid var(--color-border); border-radius: var(--radius-md); transition: transform 0.15s ease;">
                <div style="font-weight: 600; color: var(--color-text-main); margin-bottom: 0.25rem;">📅 Leave Review Queue</div>
                <div style="font-size: 0.775rem; color: var(--color-text-muted);">Approve, reject, or audit employee leave applications.</div>
            </a>
            <a href="<?= url('/hr/departments') ?>" class="card" style="padding: 1rem; margin: 0; text-decoration: none; border: 1px solid var(--color-border); border-radius: var(--radius-md); transition: transform 0.15s ease;">
                <div style="font-weight: 600; color: var(--color-text-main); margin-bottom: 0.25rem;">🏢 Departments Management</div>
                <div style="font-size: 0.775rem; color: var(--color-text-muted);">Configure organizational departments and headcount distribution.</div>
            </a>
            <a href="<?= url('/hr/positions') ?>" class="card" style="padding: 1rem; margin: 0; text-decoration: none; border: 1px solid var(--color-border); border-radius: var(--radius-md); transition: transform 0.15s ease;">
                <div style="font-weight: 600; color: var(--color-text-main); margin-bottom: 0.25rem;">💼 Job Positions</div>
                <div style="font-size: 0.775rem; color: var(--color-text-muted);">Maintain department-scoped job titles and responsibilities.</div>
            </a>
            <?php
            $annPubCount = (int)(($announcementStats['published'] ?? 0));
            $annSchedCount = (int)(($announcementStats['scheduled'] ?? 0));
            ?>
            <a href="<?= url('/hr/announcements') ?>" class="card" style="padding: 1rem; margin: 0; text-decoration: none; border: 1px solid var(--color-border); border-radius: var(--radius-md); transition: transform 0.15s ease;">
                <div style="font-weight: 600; color: var(--color-text-main); margin-bottom: 0.25rem; display: flex; align-items: center; justify-content: space-between; gap: 0.5rem;">
                    <span style="display: inline-flex; align-items: center; gap: 0.4rem;"><?= icon('megaphone', 16) ?> Announcements Management</span>
                    <?php if ($annSchedCount > 0): ?>
                        <span class="badge badge-primary tabular-nums"><?= $annSchedCount ?> scheduled</span>
                    <?php elseif ($annPubCount > 0): ?>
                        <span class="badge badge-success tabular-nums"><?= $annPubCount ?> active</span>
                    <?php endif; ?>
                </div>
                <div style="font-size: 0.775rem; color: var(--color-text-muted);">Create, schedule, publish, and track official company announcements and read receipts.</div>
            </a>
            <?php
            $hrToAdminOpen = (int)(($hrAdminSupportStats['open'] ?? 0));
            $hrToAdminWaiting = (int)(($hrAdminSupportStats['waiting_for_hr'] ?? 0));
            ?>
            <a href="<?= url('/hr/help') ?>" class="card" style="padding: 1rem; margin: 0; text-decoration: none; border: 1px solid var(--color-border); border-radius: var(--radius-md); transition: transform 0.15s ease;">
                <div style="font-weight: 600; color: var(--color-text-main); margin-bottom: 0.25rem; display: flex; align-items: center; justify-content: space-between; gap: 0.5rem;">
                    <span style="display: inline-flex; align-items: center; gap: 0.4rem;"><?= icon('alert-circle', 16) ?> Help &amp; Support (Admin)</span>
                    <?php if ($hrToAdminWaiting > 0): ?>
                        <span class="badge badge-warning tabular-nums"><?= $hrToAdminWaiting ?> awaiting reply</span>
                    <?php elseif ($hrToAdminOpen > 0): ?>
                        <span class="badge badge-primary tabular-nums"><?= $hrToAdminOpen ?> open</span>
                    <?php endif; ?>
                </div>
                <div style="font-size: 0.775rem; color: var(--color-text-muted);">Report technical issues, system errors, or access problems directly to Administrators.</div>
            </a>
            <a href="<?= url('/reports') ?>" class="card" style="padding: 1rem; margin: 0; text-decoration: none; border: 1px solid var(--color-border); border-radius: var(--radius-md); transition: transform 0.15s ease;">
                <div style="font-weight: 600; color: var(--color-text-main); margin-bottom: 0.25rem; display: flex; align-items: center; gap: 0.4rem;"><?= icon('bar-chart', 16) ?> Workforce Reports</div>
                <div style="font-size: 0.775rem; color: var(--color-text-muted);">Inspect leave summaries, rosters, and analytics.</div>
            </a>
            <?php
            $hrSupportOpen = (int)(($supportOverview['total_open'] ?? 0));
            $hrSupportConfShow = !empty($supportOverview['show_confidential_metric']);
            $hrSupportConfCount = (int)(($supportOverview['confidential_attention'] ?? 0));
            ?>
            <a href="<?= url('/hr/support') ?>" class="card" style="padding: 1rem; margin: 0; text-decoration: none; border: 1px solid var(--color-border); border-radius: var(--radius-md); transition: transform 0.15s ease;">
                <div style="font-weight: 600; color: var(--color-text-main); margin-bottom: 0.25rem; display: flex; align-items: center; justify-content: space-between; gap: 0.5rem;">
                    <span style="display: inline-flex; align-items: center; gap: 0.4rem;"><?= icon('shield', 16) ?> Employee Support Inbox</span>
                    <?php if ($hrSupportOpen > 0): ?>
                        <span class="badge badge-primary tabular-nums"><?= $hrSupportOpen ?> open</span>
                    <?php endif; ?>
                </div>
                <div style="font-size: 0.775rem; color: var(--color-text-muted);">
                    Triage employee requests and inquiries<?= ($hrSupportConfShow && $hrSupportConfCount > 0) ? " ({$hrSupportConfCount} confidential needing attention)" : '' ?>.
                </div>
            </a>
        </div>
    </div>
</div>

<!-- Recent Employee Hires Table -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title"><span>👥</span> Recent Workforce Additions</h2>
        <a href="<?= url('/hr/employees') ?>" class="btn btn-secondary btn-sm">Full Employee Directory</a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover" aria-label="Recent Employee Records">
            <thead>
                <tr>
                    <th>Employee ID</th>
                    <th>Full Name</th>
                    <th>Email Address</th>
                    <th>Department</th>
                    <th>Position Title</th>
                    <th>Hire Date</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recentEmployees)): ?>
                    <tr>
                        <td colspan="7" class="text-center" style="padding: 2.5rem; color: var(--color-text-muted);">
                            No active employee records found.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($recentEmployees as $emp): ?>
                        <tr>
                            <td>
                                <strong style="font-family: var(--font-mono); font-size: 0.8125rem; color: var(--color-primary);">
                                    <?= e($emp['employee_no']) ?>
                                </strong>
                            </td>
                            <td>
                                <strong><?= e($emp['first_name'] . ' ' . $emp['last_name']) ?></strong>
                            </td>
                            <td style="font-size: 0.8125rem; color: var(--color-text-muted);">
                                <?= e($emp['email']) ?>
                            </td>
                            <td>
                                <span class="badge badge-neutral">
                                    <?= e($emp['department_name'] ?? 'Unassigned') ?>
                                </span>
                            </td>
                            <td style="font-size: 0.8125rem;">
                                <?= e($emp['position_name'] ?? 'Unassigned') ?>
                            </td>
                            <td style="font-size: 0.8125rem; color: var(--color-text-muted);">
                                <?= !empty($emp['date_hired']) ? date('M j, Y', strtotime($emp['date_hired'])) : '—' ?>
                            </td>
                            <td class="text-right">
                                <a href="<?= url('/hr/employees') ?>?q=<?= urlencode($emp['employee_no']) ?>" class="btn btn-secondary btn-sm">
                                    View
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>