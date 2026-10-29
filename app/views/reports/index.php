<?php
/**
 * Workforce Reports & Analytics View (HR & Admin Access)
 * Employee HRIS & Leave Management System
 */
?>
<div class="page-header">
    <div class="page-title-group">
        <h1>Workforce Reports & Analytics</h1>
        <p>Operational reports on departmental headcount, organizational distribution, and annual leave utilization.</p>
    </div>
    <div class="page-actions">
        <button type="button" class="btn btn-secondary btn-sm" onclick="window.print();">
            <span>🖨️</span> Print Report
        </button>
    </div>
</div>

<!-- Reports Hub Sub-Navigation Tabs -->
<div class="report-nav-tabs" style="display: flex; gap: 0.5rem; margin-bottom: var(--space-6); border-bottom: 1px solid var(--color-border); padding-bottom: 0.75rem; flex-wrap: wrap;">
    <a href="<?= url('/reports') ?>" class="btn btn-sm <?= ($activeReportTab ?? 'overview') === 'overview' ? 'btn-primary' : 'btn-secondary' ?>">
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

<!-- Executive Summary Cards -->
<div class="grid grid-cols-3" style="margin-bottom: var(--space-6);">
    <div class="stat-card">
        <div class="stat-info">
            <span class="stat-label">Active Headcount</span>
            <span class="stat-value"><?= (int)$totalEmployees ?></span>
            <span class="stat-subtext">Active Personnel on Payroll</span>
        </div>
        <div class="stat-icon-wrapper icon-blue" aria-hidden="true">👥</div>
    </div>
    <div class="stat-card">
        <div class="stat-info">
            <span class="stat-label">Approved Leave Days</span>
            <span class="stat-value"><?= (int)$totalLeavesApproved ?></span>
            <span class="stat-subtext">Cumulative Approved Days</span>
        </div>
        <div class="stat-icon-wrapper icon-green" aria-hidden="true">📅</div>
    </div>
    <div class="stat-card">
        <div class="stat-info">
            <span class="stat-label">Operating Divisions</span>
            <span class="stat-value"><?= count($deptDistribution) ?></span>
            <span class="stat-subtext">Registered Departments</span>
        </div>
        <div class="stat-icon-wrapper icon-teal" aria-hidden="true">🏢</div>
    </div>
</div>

<!-- Report Table 1: Department Workforce Distribution -->
<div class="card" style="margin-bottom: var(--space-6);">
    <div class="card-header">
        <h2 class="card-title"><span>🏢</span> Department Headcount & Distribution</h2>
        <span style="font-size: 0.8125rem; color: var(--color-text-muted);">Real-time Database Aggregation</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover" aria-label="Department Headcount Distribution Table">
            <thead>
                <tr>
                    <th>Department Name</th>
                    <th>Total Employees</th>
                    <th>Active Personnel</th>
                    <th>Inactive / On Leave</th>
                    <th>Workforce Share</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($deptDistribution)): ?>
                    <tr>
                        <td colspan="5" class="text-center" style="padding: 2rem; color: var(--color-text-muted);">
                            No department records found.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($deptDistribution as $dept): 
                        $total = (int)$dept['employee_count'];
                        $share = $totalEmployees > 0 ? round(($total / $totalEmployees) * 100, 1) : 0;
                    ?>
                        <tr>
                            <td>
                                <strong><?= e($dept['department_name']) ?></strong>
                            </td>
                            <td>
                                <strong><?= $total ?></strong>
                            </td>
                            <td>
                                <span class="badge badge-success">
                                    <?= (int)$dept['active_count'] ?> Active
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-neutral">
                                    <?= (int)$dept['inactive_count'] ?> Inactive
                                </span>
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

<!-- Report Table 2: Leave Utilization by Classification -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title"><span>📊</span> Leave Utilization by Classification</h2>
        <span style="font-size: 0.8125rem; color: var(--color-text-muted);">Approved Days vs Pending Requests</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover" aria-label="Leave Utilization Analysis Table">
            <thead>
                <tr>
                    <th>Leave Classification</th>
                    <th>Annual Default Quota</th>
                    <th>Approved Days Consumed</th>
                    <th>Total Requests Processed</th>
                    <th>Pending Review</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($leaveSummary)): ?>
                    <tr>
                        <td colspan="5" class="text-center" style="padding: 2rem; color: var(--color-text-muted);">
                            No leave types configured.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($leaveSummary as $sum): ?>
                        <tr>
                            <td>
                                <strong><?= e($sum['leave_name']) ?></strong>
                            </td>
                            <td>
                                <?= (int)$sum['default_days'] ?> days / yr
                            </td>
                            <td>
                                <strong style="color: var(--color-primary);"><?= (int)$sum['approved_days'] ?></strong> days
                            </td>
                            <td>
                                <?= (int)$sum['total_requests'] ?> requests
                            </td>
                            <td>
                                <?php if ((int)$sum['pending_requests'] > 0): ?>
                                    <span class="badge badge-warning">
                                        <?= (int)$sum['pending_requests'] ?> Pending
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-neutral">0 Pending</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
