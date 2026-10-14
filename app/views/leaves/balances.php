<?php
/**
 * Workforce Leave Balances View (HR & Admin Access)
 * Employee HRIS & Leave Management System - Phase 8
 */
?>
<div class="page-header">
    <div class="page-title-group">
        <h1>Workforce Leave Balances</h1>
        <p>Annual leave entitlements, usage tracking, remaining quotas, and authorized adjustments.</p>
    </div>
    <div class="page-actions">
        <a href="<?= url('/leaves') ?>" class="btn btn-secondary btn-sm">
            <span>📋</span> Leave Review Queue
        </a>
        <button type="button" class="btn btn-primary btn-sm" onclick="document.getElementById('adjustmentCard').scrollIntoView({ behavior: 'smooth' });">
            <span>⚖️</span> Adjust Quota
        </button>
    </div>
</div>

<!-- Filters Bar -->
<div class="filter-bar">
    <form method="GET" action="<?= url('/leaves/balances') ?>" style="display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: flex-end; width: 100%;">
        <div style="flex: 1; min-width: 140px;">
            <label for="yearFilter" style="display: block; font-size: 0.75rem; font-weight: 600; color: var(--color-text-muted); margin-bottom: 0.25rem;">Calendar Year</label>
            <select name="year" id="yearFilter" class="form-select" style="padding: 4px 8px; font-size: 0.8125rem;">
                <?php 
                $curY = (int)date('Y');
                for ($y = $curY + 1; $y >= $curY - 3; $y--): ?>
                    <option value="<?= $y ?>" <?= (int)$selectedYear === $y ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
        </div>

        <div style="flex: 2; min-width: 200px;">
            <label for="deptFilter" style="display: block; font-size: 0.75rem; font-weight: 600; color: var(--color-text-muted); margin-bottom: 0.25rem;">Department</label>
            <select name="dept" id="deptFilter" class="form-select" style="padding: 4px 8px; font-size: 0.8125rem;">
                <option value="">All Organizational Units</option>
                <?php foreach ($departments ?? [] as $d): ?>
                    <option value="<?= (int)$d['id'] ?>" <?= (isset($selectedDept) && (int)$selectedDept === (int)$d['id']) ? 'selected' : '' ?>>
                        <?= e($d['department_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="display: flex; gap: 0.35rem;">
            <button type="submit" class="btn btn-primary btn-sm" style="padding: 5px 12px;">Filter</button>
            <a href="<?= url('/leaves/balances') ?>" class="btn btn-secondary btn-sm" style="padding: 5px 12px;">Reset</a>
        </div>
    </form>
</div>

<!-- Balances Table Card -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">
            <span>⚖️</span> Leave Balances Directory (Year <?= (int)$selectedYear ?>)
        </h2>
        <span style="font-size: 0.8125rem; color: var(--color-text-muted);">
            <?= count($balances) ?> Record Allocation<?= count($balances) === 1 ? '' : 's' ?>
        </span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover" aria-label="Workforce Leave Balances Table">
            <thead>
                <tr>
                    <th>Employee ID</th>
                    <th>Employee Name</th>
                    <th>Department</th>
                    <th>Leave Classification</th>
                    <th>Allocated Days</th>
                    <th>Days Used</th>
                    <th>Remaining Days</th>
                    <th>Utilization Meter</th>
                    <th class="text-right">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($balances)): ?>
                    <tr>
                        <td colspan="9" class="text-center" style="padding: 2.5rem; color: var(--color-text-muted);">
                            <div class="empty-state">
                                <div class="empty-state-icon" aria-hidden="true">⚖️</div>
                                <div class="empty-state-title">No Balance Allocations Found</div>
                                <div class="empty-state-text">No leave balance records match year <?= (int)$selectedYear ?>. You can initialize balances below.</div>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($balances as $b): 
                        $alloc = (int)$b['allocated_days'];
                        $used = (int)$b['used_days'];
                        $rem = (int)$b['remaining_days'];
                        $pct = $alloc > 0 ? min(100, round(($used / $alloc) * 100)) : 0;
                    ?>
                        <tr>
                            <td>
                                <strong style="font-family: var(--font-mono); font-size: 0.8125rem; color: var(--color-primary);">
                                    <?= e($b['employee_no']) ?>
                                </strong>
                            </td>
                            <td>
                                <strong><?= e($b['first_name'] . ' ' . $b['last_name']) ?></strong>
                            </td>
                            <td>
                                <span class="badge badge-neutral">
                                    <?= e($b['department_name'] ?? 'General') ?>
                                </span>
                            </td>
                            <td>
                                <strong><?= e($b['leave_name']) ?></strong>
                            </td>
                            <td>
                                <?= $alloc ?> days
                            </td>
                            <td>
                                <strong><?= $used ?></strong> days
                            </td>
                            <td>
                                <span class="badge <?= $rem > 3 ? 'badge-success' : ($rem > 0 ? 'badge-warning' : 'badge-danger') ?>">
                                    <?= $rem ?> days
                                </span>
                            </td>
                            <td style="min-width: 140px;">
                                <div class="balance-meter" role="progressbar" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100">
                                    <div class="balance-fill" style="width: <?= $pct ?>%; background-color: <?= $pct > 80 ? 'var(--color-error)' : ($pct > 50 ? 'var(--color-warning)' : 'var(--color-primary)') ?>;"></div>
                                </div>
                                <span style="font-size: 0.7rem; color: var(--color-text-muted);"><?= $pct ?>% utilized</span>
                            </td>
                            <td class="text-right">
                                <button type="button" 
                                        class="btn btn-secondary btn-sm" 
                                        onclick="prefillAdjustment(<?= (int)$b['employee_id'] ?>, <?= (int)$b['leave_type_id'] ?>, <?= (int)$b['allocated_days'] ?>, '<?= addslashes($b['first_name'] . ' ' . $b['last_name']) ?>', '<?= addslashes($b['leave_name']) ?>');">
                                    Adjust
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Authorized Leave Quota Adjustment Card -->
<div class="card" id="adjustmentCard" style="margin-top: 2rem;">
    <div class="card-header">
        <h2 class="card-title">
            <span>⚙️</span> Authorized Leave Quota Adjustment & Initialization
        </h2>
        <span style="font-size: 0.75rem; color: var(--color-text-muted);">
            Changes are permanently audited in System Activity Logs
        </span>
    </div>
    <div class="card-body">
        <form method="POST" action="<?= url('/hr/leaves/balances/adjust') ?>" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; align-items: end;">
            <?= csrf_field() ?>
            <input type="hidden" name="year" value="<?= (int)$selectedYear ?>">

            <div class="form-group" style="margin-bottom: 0;">
                <label for="adj_employee_id" class="form-label required">Employee Target</label>
                <select name="employee_id" id="adj_employee_id" class="form-select" required>
                    <option value="">-- Select Employee --</option>
                    <?php 
                    $seenEmp = [];
                    foreach ($balances as $b): 
                        if (isset($seenEmp[$b['employee_id']])) continue;
                        $seenEmp[$b['employee_id']] = true;
                    ?>
                        <option value="<?= (int)$b['employee_id'] ?>">
                            <?= e($b['first_name'] . ' ' . $b['last_name']) ?> (<?= e($b['employee_no']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" style="margin-bottom: 0;">
                <label for="adj_leave_type_id" class="form-label required">Leave Classification</label>
                <select name="leave_type_id" id="adj_leave_type_id" class="form-select" required>
                    <option value="">-- Select Classification --</option>
                    <?php foreach ($leaveTypes ?? [] as $lt): ?>
                        <option value="<?= (int)$lt['id'] ?>">
                            <?= e($lt['leave_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" style="margin-bottom: 0;">
                <label for="adj_allocated_days" class="form-label required">New Total Allocated Days</label>
                <input type="number" name="allocated_days" id="adj_allocated_days" class="form-control" min="0" max="365" required>
            </div>

            <div class="form-group" style="margin-bottom: 0; grid-column: span 2;">
                <label for="adj_reason" class="form-label required">Administrative Reason / Justification</label>
                <input type="text" name="reason" id="adj_reason" class="form-control" placeholder="e.g. Approved tenure service credit, Corporate tenure increment, Correction" required>
            </div>

            <div style="margin-bottom: 0;">
                <button type="submit" class="btn btn-primary" style="width: 100%;">
                    <span>💾</span> Save Adjustment
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function prefillAdjustment(empId, leaveTypeId, allocDays, empName, leaveName) {
    var empSelect = document.getElementById('adj_employee_id');
    var typeSelect = document.getElementById('adj_leave_type_id');
    var daysInput = document.getElementById('adj_allocated_days');
    
    if (empSelect) empSelect.value = empId;
    if (typeSelect) typeSelect.value = leaveTypeId;
    if (daysInput) daysInput.value = allocDays;

    document.getElementById('adjustmentCard').scrollIntoView({ behavior: 'smooth' });
}
</script>
