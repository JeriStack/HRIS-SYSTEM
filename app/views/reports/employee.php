<?php
/**
 * Employee Workforce Report View (HR & Admin Access)
 * Comprehensive workforce directory, headcount breakdown, and status reports
 * Employee HRIS & Leave Management System — Phase 13
 */
?>
<div class="page-header">
    <div class="page-title-group">
        <h1>Employee Workforce Report</h1>
        <p>Comprehensive headcount auditing, departmental staffing, and active employment lifecycle records.</p>
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

<!-- Headcount Metrics Grid -->
<div class="grid grid-cols-4" style="margin-bottom: var(--space-6);">
    <div class="stat-card">
        <div class="stat-info">
            <span class="stat-label">Total Headcount</span>
            <span class="stat-value"><?= array_sum($statusCounts ?? []) ?></span>
            <span class="stat-subtext">All Registered Records</span>
        </div>
        <div class="stat-icon-wrapper icon-blue" aria-hidden="true">👥</div>
    </div>
    <div class="stat-card">
        <div class="stat-info">
            <span class="stat-label">Active Personnel</span>
            <span class="stat-value"><?= (int)($statusCounts['Active'] ?? 0) ?></span>
            <span class="stat-subtext">Active on Payroll</span>
        </div>
        <div class="stat-icon-wrapper icon-green" aria-hidden="true">✓</div>
    </div>
    <div class="stat-card">
        <div class="stat-info">
            <span class="stat-label">Inactive Personnel</span>
            <span class="stat-value"><?= (int)($statusCounts['Inactive'] ?? 0) ?></span>
            <span class="stat-subtext">Deactivated Accounts</span>
        </div>
        <div class="stat-icon-wrapper icon-neutral" aria-hidden="true">⊘</div>
    </div>
    <div class="stat-card">
        <div class="stat-info">
            <span class="stat-label">Matching Query</span>
            <span class="stat-value"><?= (int)($totalRows ?? 0) ?></span>
            <span class="stat-subtext">Filtered Results</span>
        </div>
        <div class="stat-icon-wrapper icon-teal" aria-hidden="true">🔍</div>
    </div>
</div>

<!-- Filters Hub -->
<div class="card" style="margin-bottom: var(--space-6); padding: 1.25rem;">
    <form method="GET" action="<?= url('/reports/employees') ?>" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; align-items: flex-end;">
        <div>
            <label for="filterSearch" style="display: block; font-size: 0.75rem; font-weight: 600; color: var(--color-text-muted); margin-bottom: 0.25rem;">Search Query</label>
            <input type="text" id="filterSearch" name="q" value="<?= e($filters['q'] ?? '') ?>" class="form-control" placeholder="Name, ID, or Email..." style="padding: 6px 10px; font-size: 0.8125rem;">
        </div>

        <div>
            <label for="filterDept" style="display: block; font-size: 0.75rem; font-weight: 600; color: var(--color-text-muted); margin-bottom: 0.25rem;">Department</label>
            <select id="filterDept" name="dept_id" class="form-select" style="padding: 6px 10px; font-size: 0.8125rem;">
                <option value="">All Departments</option>
                <?php foreach ($departments ?? [] as $dept): ?>
                    <option value="<?= (int)$dept['id'] ?>" <?= (!empty($filters['dept_id']) && (int)$filters['dept_id'] === (int)$dept['id']) ? 'selected' : '' ?>>
                        <?= e($dept['department_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label for="filterPos" style="display: block; font-size: 0.75rem; font-weight: 600; color: var(--color-text-muted); margin-bottom: 0.25rem;">Job Position</label>
            <select id="filterPos" name="pos_id" class="form-select" style="padding: 6px 10px; font-size: 0.8125rem;">
                <option value="">All Positions</option>
                <?php foreach ($positions ?? [] as $pos): ?>
                    <option value="<?= (int)$pos['id'] ?>" <?= (!empty($filters['pos_id']) && (int)$filters['pos_id'] === (int)$pos['id']) ? 'selected' : '' ?>>
                        <?= e($pos['position_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label for="filterStatus" style="display: block; font-size: 0.75rem; font-weight: 600; color: var(--color-text-muted); margin-bottom: 0.25rem;">Employment Status</label>
            <select id="filterStatus" name="status" class="form-select" style="padding: 6px 10px; font-size: 0.8125rem;">
                <option value="">All Statuses</option>
                <option value="Active" <?= (($filters['status'] ?? '') === 'Active') ? 'selected' : '' ?>>Active</option>
                <option value="Inactive" <?= (($filters['status'] ?? '') === 'Inactive') ? 'selected' : '' ?>>Inactive</option>
                <option value="Probationary" <?= (($filters['status'] ?? '') === 'Probationary') ? 'selected' : '' ?>>Probationary</option>
                <option value="On Leave" <?= (($filters['status'] ?? '') === 'On Leave') ? 'selected' : '' ?>>On Leave</option>
            </select>
        </div>

        <div>
            <label for="filterStart" style="display: block; font-size: 0.75rem; font-weight: 600; color: var(--color-text-muted); margin-bottom: 0.25rem;">Hired On or After</label>
            <input type="date" id="filterStart" name="start_date" value="<?= e($filters['start_date'] ?? '') ?>" class="form-control" style="padding: 5px 8px; font-size: 0.8125rem;">
        </div>

        <div>
            <label for="filterEnd" style="display: block; font-size: 0.75rem; font-weight: 600; color: var(--color-text-muted); margin-bottom: 0.25rem;">Hired On or Before</label>
            <input type="date" id="filterEnd" name="end_date" value="<?= e($filters['end_date'] ?? '') ?>" class="form-control" style="padding: 5px 8px; font-size: 0.8125rem;">
        </div>

        <div style="display: flex; gap: 0.5rem;">
            <button type="submit" class="btn btn-primary btn-sm" style="flex: 1; padding: 6px 14px;">Apply Filters</button>
            <a href="<?= url('/reports/employees') ?>" class="btn btn-secondary btn-sm" style="padding: 6px 14px;">Reset</a>
        </div>
    </form>
</div>

<!-- Employee Records Table -->
<div class="card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
        <h2 class="card-title">
            <span>👥</span> Personnel Directory Records (<?= count($employees ?? []) ?> of <?= (int)$totalRows ?> Listed)
        </h2>
        <span style="font-size: 0.8125rem; color: var(--color-text-muted);">
            Page <?= (int)$currentPage ?> of <?= (int)$totalPages ?>
        </span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover" aria-label="Employee Workforce Report Table">
            <thead>
                <tr>
                    <th>Emp #</th>
                    <th>Full Name</th>
                    <th>Department</th>
                    <th>Job Position</th>
                    <th>Email Address</th>
                    <th>Date Hired</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($employees)): ?>
                    <tr>
                        <td colspan="7" class="text-center" style="padding: 2.5rem; color: var(--color-text-muted);">
                            No employee records match the selected filter criteria.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($employees as $emp): 
                        $fullName = trim("{$emp['first_name']} {$emp['last_name']}");
                        $statusClass = match ($emp['employment_status']) {
                            'Active'       => 'badge-success',
                            'Inactive'     => 'badge-neutral',
                            'Probationary' => 'badge-warning',
                            'On Leave'     => 'badge-info',
                            default        => 'badge-neutral',
                        };
                    ?>
                        <tr>
                            <td>
                                <a href="<?= url('/hr/employees/' . $emp['id']) ?>" style="font-weight: 600; color: var(--color-primary);">
                                    <?= e($emp['employee_no']) ?>
                                </a>
                            </td>
                            <td>
                                <strong><?= e($fullName) ?></strong>
                            </td>
                            <td>
                                <?= e($emp['department_name'] ?? 'Unassigned') ?>
                            </td>
                            <td>
                                <?= e($emp['position_name'] ?? 'Unassigned') ?>
                            </td>
                            <td>
                                <?= e($emp['email']) ?>
                            </td>
                            <td>
                                <?= !empty($emp['date_hired']) ? date('M d, Y', strtotime($emp['date_hired'])) : '—' ?>
                            </td>
                            <td>
                                <span class="badge <?= $statusClass ?>">
                                    <?= e($emp['employment_status']) ?>
                                </span>
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
                    <a href="<?= url('/reports/employees?' . http_build_query(array_merge($filters, ['page' => $currentPage - 1]))) ?>" class="btn btn-secondary btn-sm">
                        « Previous
                    </a>
                <?php endif; ?>

                <?php if ($currentPage < $totalPages): ?>
                    <a href="<?= url('/reports/employees?' . http_build_query(array_merge($filters, ['page' => $currentPage + 1]))) ?>" class="btn btn-secondary btn-sm">
                        Next »
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
