<?php
/**
 * Department Workforce & Staffing Report View (HR & Admin Access)
 * Audits department headcounts, available positions, and departmental leave usage
 * Employee HRIS & Leave Management System — Phase 13
 */
?>
<div class="page-header">
    <div class="page-title-group">
        <h1>Department Workforce & Staffing Report</h1>
        <p>Operational audit of departmental headcounts, active personnel distribution, staffing ratios, and leave utilization.</p>
    </div>
    <div class="page-actions">
        <button type="button" class="btn btn-secondary btn-sm" onclick="window.print();">
            <span>🖨️</span> Print Report
        </button>
    </div>
</div>

<!-- Reports Hub Sub-Navigation Tabs -->
<div class="report-nav-tabs" style="display: flex; gap: 0.5rem; margin-bottom: var(--space-6); border-bottom: 1px solid var(--color-border); padding-bottom: 0.75rem; flex-wrap: wrap;">
    <a href="<?= url('/reports') ?>" class="btn btn-sm <?= ($activeReportTab ?? '') === 'overview' ? 'btn-primary' : 'btn-secondary' ?>">
        📊 Executive Overview
    </a>
    <a href="<?= url('/reports/employees') ?>" class="btn btn-sm <?= ($activeReportTab ?? '') === 'employees' ? 'btn-primary' : 'btn-secondary' ?>">
        👥 Employee Workforce
    </a>
    <a href="<?= url('/reports/leaves') ?>" class="btn btn-sm <?= ($activeReportTab ?? '') === 'leaves' ? 'btn-primary' : 'btn-secondary' ?>">
        📅 Leave Utilization
    </a>
    <a href="<?= url('/reports/departments') ?>" class="btn btn-sm <?= ($activeReportTab ?? '') === 'departments' ? 'btn-primary' : 'btn-secondary' ?>">
        🏢 Department Distribution
    </a>
</div>

<!-- Department Overview Metric Cards -->
<div class="grid grid-cols-4" style="margin-bottom: var(--space-6);">
    <div class="stat-card">
        <div class="stat-info">
            <span class="stat-label">Active Divisions</span>
            <span class="stat-value"><?= count($departmentSummaries ?? []) ?></span>
            <span class="stat-subtext">Operating Departments</span>
        </div>
        <div class="stat-icon-wrapper icon-blue" aria-hidden="true">🏢</div>
    </div>
    <div class="stat-card">
        <div class="stat-info">
            <span class="stat-label">Total Workforce</span>
            <span class="stat-value"><?= (int)($totalHeadcount ?? 0) ?></span>
            <span class="stat-subtext">Across All Departments</span>
        </div>
        <div class="stat-icon-wrapper icon-teal" aria-hidden="true">👥</div>
    </div>
    <div class="stat-card">
        <div class="stat-info">
            <span class="stat-label">Active Headcount</span>
            <span class="stat-value"><?= (int)($totalActive ?? 0) ?></span>
            <span class="stat-subtext"><?= (int)($totalInactive ?? 0) ?> Inactive Accounts</span>
        </div>
        <div class="stat-icon-wrapper icon-green" aria-hidden="true">✓</div>
    </div>
    <div class="stat-card">
        <div class="stat-info">
            <span class="stat-label">Approved Absence</span>
            <span class="stat-value"><?= (int)($totalLeaveDays ?? 0) ?> Days</span>
            <span class="stat-subtext">Departmental Leave Total</span>
        </div>
        <div class="stat-icon-wrapper icon-purple" aria-hidden="true">📅</div>
    </div>
</div>

<!-- Department Roster Table Card -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">
            <span>🏢</span> Departmental Roster & Distribution (<?= count($departmentSummaries ?? []) ?> Operating Divisions)
        </h2>
    </div>
    <div class="table-responsive">
        <table class="table table-hover" aria-label="Department Workforce Summary Table">
            <thead>
                <tr>
                    <th>Department</th>
                    <th>Description</th>
                    <th>Headcount</th>
                    <th>Active Personnel</th>
                    <th>Inactive Accounts</th>
                    <th>Job Positions</th>
                    <th>Approved Leave Days</th>
                    <th>Workforce Share</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($departmentSummaries)): ?>
                    <tr>
                        <td colspan="8" class="text-center" style="padding: 2.5rem; color: var(--color-text-muted);">
                            No department records found.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($departmentSummaries as $dept): 
                        $total = (int)$dept['total_employees'];
                        $share = ($totalHeadcount ?? 0) > 0 ? round(($total / $totalHeadcount) * 100, 1) : 0;
                    ?>
                        <tr>
                            <td>
                                <strong>
                                    <a href="<?= url('/reports/employees?dept_id=' . $dept['id']) ?>" style="color: var(--color-primary);">
                                        <?= e($dept['department_name']) ?>
                                    </a>
                                </strong>
                            </td>
                            <td>
                                <span style="font-size: 0.8125rem; color: var(--color-text-muted);">
                                    <?= e($dept['description'] ?? 'No description provided.') ?>
                                </span>
                            </td>
                            <td>
                                <strong><?= $total ?></strong>
                            </td>
                            <td>
                                <span class="badge badge-success">
                                    <?= (int)$dept['active_employees'] ?> Active
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-neutral">
                                    <?= (int)$dept['inactive_employees'] ?> Inactive
                                </span>
                            </td>
                            <td>
                                <?= (int)$dept['total_positions'] ?> positions
                            </td>
                            <td>
                                <strong><?= (int)$dept['approved_leave_days'] ?></strong> days
                                <div style="font-size: 0.7rem; color: var(--color-text-muted);"><?= (int)$dept['total_leave_requests'] ?> filings</div>
                            </td>
                            <td style="min-width: 140px;">
                                <div class="balance-meter" role="progressbar" aria-valuenow="<?= $share ?>" aria-valuemin="0" aria-valuemax="100">
                                    <div class="balance-fill" style="width: <?= min(100, $share) ?>%;"></div>
                                </div>
                                <span style="font-size: 0.725rem; color: var(--color-text-muted);"><?= $share ?>% of workforce</span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
