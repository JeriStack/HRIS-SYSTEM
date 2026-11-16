<?php
/**
 * Leave Utilization & Requests Report View (HR & Admin Access)
 * Audits absence applications, departmental leave utilization, and quota balances
 * Employee HRIS & Leave Management System — Phase 13
 */
?>
<div class="page-header">
    <div class="page-title-group">
        <h1>Leave Utilization & Applications Report</h1>
        <p>Operational audit of employee absence requests, approved utilization, pending review pipeline, and quotas.</p>
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

<!-- Leave Analytics Metrics Grid -->
<div class="grid grid-cols-4" style="margin-bottom: var(--space-6);">
    <div class="stat-card">
        <div class="stat-info">
            <span class="stat-label">Total Applications</span>
            <span class="stat-value"><?= (int)($aggregates['total_count'] ?? 0) ?></span>
            <span class="stat-subtext">All Filings Recorded</span>
        </div>
        <div class="stat-icon-wrapper icon-blue" aria-hidden="true">📋</div>
    </div>
    <div class="stat-card">
        <div class="stat-info">
            <span class="stat-label">Approved Absence</span>
            <span class="stat-value"><?= (int)($aggregates['approved_days'] ?? 0) ?> Days</span>
            <span class="stat-subtext"><?= (int)($aggregates['approved_count'] ?? 0) ?> Approved Requests</span>
        </div>
        <div class="stat-icon-wrapper icon-green" aria-hidden="true">✓</div>
    </div>
    <div class="stat-card">
        <div class="stat-info">
            <span class="stat-label">Pending Approval</span>
            <span class="stat-value"><?= (int)($aggregates['pending_days'] ?? 0) ?> Days</span>
            <span class="stat-subtext"><?= (int)($aggregates['pending_count'] ?? 0) ?> Requests in Queue</span>
        </div>
        <div class="stat-icon-wrapper icon-amber" aria-hidden="true">⏳</div>
    </div>
    <div class="stat-card">
        <div class="stat-info">
            <span class="stat-label">Declined / Cancelled</span>
            <span class="stat-value"><?= (int)($aggregates['rejected_count'] ?? 0) + (int)($aggregates['cancelled_count'] ?? 0) ?></span>
            <span class="stat-subtext">Non-Approved Requests</span>
        </div>
        <div class="stat-icon-wrapper icon-rose" aria-hidden="true">✕</div>
    </div>
</div>

<!-- Utilization by Classification Progress Cards -->
<div class="card" style="margin-bottom: var(--space-6); padding: 1.25rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem; margin-bottom: 1rem;">
        <h3 style="font-size: 1rem; font-weight: 700; color: var(--color-text-main); margin: 0;">
            Leave Quota Utilization by Classification
        </h3>
        <?php if (!empty($categoryBreakdown)): ?>
            <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                <span class="badge badge-statutory">Statutory: <?= (int)($categoryBreakdown['statutory_days'] ?? 0) ?>d</span>
                <span class="badge badge-company">Company Paid: <?= (int)($categoryBreakdown['company_days'] ?? 0) ?>d</span>
                <span class="badge badge-unpaid">Unpaid (LWOP): <?= (int)($categoryBreakdown['unpaid_days'] ?? 0) ?>d</span>
            </div>
        <?php endif; ?>
    </div>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem;">
        <?php foreach ($typeUtilization ?? [] as $t): 
            $days = (int)$t['approved_days'];
            $reqCount = (int)$t['request_count'];
            $isRedactedTu = !empty($t['is_redacted']);
        ?>
            <div style="background: var(--color-bg-muted); border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 0.85rem 1rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.35rem; gap: 0.4rem;">
                    <strong style="font-size: 0.85rem; color: var(--color-text-main);"><?= e($t['leave_name']) ?></strong>
                    <span class="badge <?= $isRedactedTu ? 'badge-confidential' : 'badge-primary' ?>" style="font-size: 0.7rem;"><?= $days ?> Days Taken</span>
                </div>
                <div style="font-size: 0.75rem; color: var(--color-text-muted);">
                    <?php if ($isRedactedTu): ?>
                        Protected aggregate &bull; <?= $reqCount ?> applications
                    <?php else: ?>
                        Default Quota: <?= (int)$t['default_days'] ?> days &bull; <?= $reqCount ?> applications
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Filters Hub -->
<div class="card" style="margin-bottom: var(--space-6); padding: 1.25rem;">
    <form method="GET" action="<?= url('/reports/leaves') ?>" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; align-items: flex-end;">
        <div>
            <label for="filterStatus" style="display: block; font-size: 0.75rem; font-weight: 600; color: var(--color-text-muted); margin-bottom: 0.25rem;">Status</label>
            <select id="filterStatus" name="status" class="form-select" style="padding: 6px 10px; font-size: 0.8125rem;">
                <option value="">All Statuses</option>
                <option value="Approved" <?= (($filters['status'] ?? '') === 'Approved') ? 'selected' : '' ?>>Approved</option>
                <option value="Pending" <?= (($filters['status'] ?? '') === 'Pending') ? 'selected' : '' ?>>Pending</option>
                <option value="Rejected" <?= (($filters['status'] ?? '') === 'Rejected') ? 'selected' : '' ?>>Rejected</option>
                <option value="Cancelled" <?= (($filters['status'] ?? '') === 'Cancelled') ? 'selected' : '' ?>>Cancelled</option>
            </select>
        </div>

        <div>
            <label for="filterLeaveType" style="display: block; font-size: 0.75rem; font-weight: 600; color: var(--color-text-muted); margin-bottom: 0.25rem;">Classification</label>
            <select id="filterLeaveType" name="leave_type_id" class="form-select" style="padding: 6px 10px; font-size: 0.8125rem;">
                <option value="">All Classifications</option>
                <?php foreach ($leaveTypes ?? [] as $lt): ?>
                    <option value="<?= (int)$lt['id'] ?>" <?= (!empty($filters['leave_type_id']) && (int)$filters['leave_type_id'] === (int)$lt['id']) ? 'selected' : '' ?>>
                        <?= e($lt['leave_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label for="filterDept" style="display: block; font-size: 0.75rem; font-weight: 600; color: var(--color-text-muted); margin-bottom: 0.25rem;">Department</label>
            <select id="filterDept" name="dept_id" class="form-select" style="padding: 6px 10px; font-size: 0.8125rem;">
                <option value="">All Departments</option>
                <?php foreach ($departments ?? [] as $d): ?>
                    <option value="<?= (int)$d['id'] ?>" <?= (!empty($filters['dept_id']) && (int)$filters['dept_id'] === (int)$d['id']) ? 'selected' : '' ?>>
                        <?= e($d['department_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label for="filterStartDate" style="display: block; font-size: 0.75rem; font-weight: 600; color: var(--color-text-muted); margin-bottom: 0.25rem;">Schedule From</label>
            <input type="date" id="filterStartDate" name="start_date" value="<?= e($filters['start_date'] ?? '') ?>" class="form-control" style="padding: 5px 8px; font-size: 0.8125rem;">
        </div>

        <div>
            <label for="filterEndDate" style="display: block; font-size: 0.75rem; font-weight: 600; color: var(--color-text-muted); margin-bottom: 0.25rem;">Schedule To</label>
            <input type="date" id="filterEndDate" name="end_date" value="<?= e($filters['end_date'] ?? '') ?>" class="form-control" style="padding: 5px 8px; font-size: 0.8125rem;">
        </div>

        <div style="display: flex; gap: 0.5rem;">
            <button type="submit" class="btn btn-primary btn-sm" style="flex: 1; padding: 6px 14px;">Apply Filters</button>
            <a href="<?= url('/reports/leaves') ?>" class="btn btn-secondary btn-sm" style="padding: 6px 14px;">Reset</a>
        </div>
    </form>
</div>

<!-- Leave Requests Table Card -->
<div class="card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
        <h2 class="card-title">
            <span>📋</span> Absence Applications Audit (<?= count($leaveRecords ?? []) ?> of <?= (int)$totalRows ?> Listed)
        </h2>
        <span style="font-size: 0.8125rem; color: var(--color-text-muted);">
            Page <?= (int)$currentPage ?> of <?= (int)$totalPages ?>
        </span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover" aria-label="Leave Applications Report Table">
            <thead>
                <tr>
                    <th>Ref #</th>
                    <th>Employee</th>
                    <th>Department</th>
                    <th>Classification</th>
                    <th>Schedule Dates</th>
                    <th>Duration</th>
                    <th>Status</th>
                    <th>Reviewed By</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($leaveRecords)): ?>
                    <tr>
                        <td colspan="8" class="text-center" style="padding: 2.5rem; color: var(--color-text-muted);">
                            No leave applications match the selected filter criteria.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($leaveRecords as $lr): 
                        $statusClass = match ($lr['status']) {
                            'Approved'  => 'badge-success',
                            'Pending'   => 'badge-warning',
                            'Rejected'  => 'badge-danger',
                            'Cancelled' => 'badge-neutral',
                            default     => 'badge-neutral',
                        };
                    ?>
                        <tr>
                            <td>
                                <a href="<?= url('/leaves/' . $lr['id']) ?>" style="font-weight: 600; color: var(--color-primary);">
                                    #LR-<?= str_pad((string)$lr['id'], 3, '0', STR_PAD_LEFT) ?>
                                </a>
                            </td>
                            <td>
                                <strong><?= e($lr['first_name'] . ' ' . $lr['last_name']) ?></strong>
                                <div style="font-size: 0.725rem; color: var(--color-text-muted);"><?= e($lr['employee_no']) ?></div>
                            </td>
                            <td>
                                <?= e($lr['department_name'] ?? 'Unassigned') ?>
                            </td>
                            <td>
                                <?= e($lr['leave_name']) ?>
                                <?php if (!empty($lr['is_redacted'])): ?>
                                    <div style="margin-top: 2px;"><span class="badge badge-confidential" style="font-size: 0.6875rem;">Redacted</span></div>
                                <?php elseif (!empty($lr['classification'])): ?>
                                    <div style="font-size: 0.7rem; color: var(--color-text-muted);"><?= e((string)$lr['classification']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= date('M d, Y', strtotime($lr['start_date'])) ?> &rarr; <?= date('M d, Y', strtotime($lr['end_date'])) ?>
                            </td>
                            <td>
                                <strong><?= (int)$lr['number_of_days'] ?></strong> <?= (($lr['entitlement_unit'] ?? 'working_days') === 'calendar_days') ? 'cal.' : 'working' ?> day<?= (int)$lr['number_of_days'] === 1 ? '' : 's' ?>
                            </td>
                            <td>
                                <span class="badge <?= $statusClass ?>">
                                    <?= e($lr['status']) ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($lr['reviewer_username'])): ?>
                                    <span style="font-size: 0.8125rem; color: var(--color-text-primary);">
                                        <?= e($lr['reviewer_username']) ?>
                                    </span>
                                    <div style="font-size: 0.7rem; color: var(--color-text-muted);">
                                        <?= !empty($lr['approved_at']) ? date('M d, Y', strtotime($lr['approved_at'])) : '' ?>
                                    </div>
                                <?php else: ?>
                                    <span style="color: var(--color-text-muted); font-size: 0.8125rem;">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination Footer -->
    <?php if (($totalPages ?? 1) > 1): ?>
        <div style="display: flex; justify-content: space-between; align-items: center; padding: 1rem 1.5rem; border-top: 1px solid var(--color-border); flex-wrap: wrap; gap: 0.5rem;">
            <div style="font-size: 0.8125rem; color: var(--color-text-muted);">
                Showing <?= min($totalRows, (($currentPage - 1) * 25) + 1) ?> to <?= min($totalRows, $currentPage * 25) ?> of <?= $totalRows ?> records
            </div>
            <div style="display: flex; gap: 0.4rem;">
                <?php if ($currentPage > 1): ?>
                    <a href="<?= url('/reports/leaves?' . http_build_query(array_merge($filters, ['page' => $currentPage - 1]))) ?>" class="btn btn-secondary btn-sm">
                        « Previous
                    </a>
                <?php endif; ?>

                <?php if ($currentPage < $totalPages): ?>
                    <a href="<?= url('/reports/leaves?' . http_build_query(array_merge($filters, ['page' => $currentPage + 1]))) ?>" class="btn btn-secondary btn-sm">
                        Next »
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
