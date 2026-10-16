<?php
/**
 * Leave Requests Review Queue View (HR & Admin Access)
 * Employee HRIS & Leave Management System - Phase 8
 */
?>
<div class="page-header">
    <div class="page-title-group">
        <h1>Leave Review Queue</h1>
        <p>Review, approve, or reject employee absence applications. Manage workforce availability.</p>
    </div>
    <div class="page-actions">
        <a href="<?= url('/leaves/balances') ?>" class="btn btn-secondary btn-sm">
            <span>⚖️</span> Workforce Balances
        </a>
        <a href="<?= url('/hr/leave-types') ?>" class="btn btn-secondary btn-sm">
            <span>🏷️</span> Leave Classifications
        </a>
    </div>
</div>

<!-- Filter Hub -->
<div class="filter-bar" style="display: flex; flex-direction: column; gap: 0.75rem;">
    <!-- Status Tabs -->
    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.5rem;">
        <div style="display: flex; gap: 0.4rem; flex-wrap: wrap;">
            <a href="<?= url('/leaves') ?>" class="btn btn-sm <?= empty($selectedStatus) ? 'btn-primary' : 'btn-secondary' ?>">
                All Requests
            </a>
            <a href="<?= url('/leaves') ?>?status=Pending" class="btn btn-sm <?= $selectedStatus === 'Pending' ? 'btn-primary' : 'btn-secondary' ?>">
                ⏳ Pending
            </a>
            <a href="<?= url('/leaves') ?>?status=Approved" class="btn btn-sm <?= $selectedStatus === 'Approved' ? 'btn-primary' : 'btn-secondary' ?>">
                ✓ Approved
            </a>
            <a href="<?= url('/leaves') ?>?status=Rejected" class="btn btn-sm <?= $selectedStatus === 'Rejected' ? 'btn-primary' : 'btn-secondary' ?>">
                ✕ Rejected
            </a>
            <a href="<?= url('/leaves') ?>?status=Cancelled" class="btn btn-sm <?= $selectedStatus === 'Cancelled' ? 'btn-primary' : 'btn-secondary' ?>">
                ⊘ Cancelled
            </a>
        </div>
    </div>

    <!-- Department & Date Filtering Form -->
    <form method="GET" action="<?= url('/leaves') ?>" style="display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: flex-end; background-color: var(--color-bg-muted); padding: 0.75rem 1rem; border-radius: var(--radius-md); border: 1px solid var(--color-border);">
        <?php if (!empty($selectedStatus)): ?>
            <input type="hidden" name="status" value="<?= e($selectedStatus) ?>">
        <?php endif; ?>

        <div style="flex: 1; min-width: 180px;">
            <label for="deptSelect" style="display: block; font-size: 0.75rem; font-weight: 600; color: var(--color-text-muted); margin-bottom: 0.25rem;">Department</label>
            <select name="dept_id" id="deptSelect" class="form-select" style="padding: 4px 8px; font-size: 0.8125rem;">
                <option value="">All Departments</option>
                <?php foreach ($departments ?? [] as $d): ?>
                    <option value="<?= (int)$d['id'] ?>" <?= (isset($selectedDept) && (int)$selectedDept === (int)$d['id']) ? 'selected' : '' ?>>
                        <?= e($d['department_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="flex: 1; min-width: 140px;">
            <label for="startDateFilter" style="display: block; font-size: 0.75rem; font-weight: 600; color: var(--color-text-muted); margin-bottom: 0.25rem;">From Date</label>
            <input type="date" name="start_date" id="startDateFilter" value="<?= e($startDate ?? '') ?>" class="form-control" style="padding: 4px 8px; font-size: 0.8125rem;">
        </div>

        <div style="flex: 1; min-width: 140px;">
            <label for="endDateFilter" style="display: block; font-size: 0.75rem; font-weight: 600; color: var(--color-text-muted); margin-bottom: 0.25rem;">To Date</label>
            <input type="date" name="end_date" id="endDateFilter" value="<?= e($endDate ?? '') ?>" class="form-control" style="padding: 4px 8px; font-size: 0.8125rem;">
        </div>

        <div style="display: flex; gap: 0.35rem;">
            <button type="submit" class="btn btn-primary btn-sm" style="padding: 5px 12px;">Filter</button>
            <a href="<?= url('/leaves') ?><?= !empty($selectedStatus) ? '?status=' . e($selectedStatus) : '' ?>" class="btn btn-secondary btn-sm" style="padding: 5px 12px;">Reset</a>
        </div>
    </form>
</div>

<!-- Leave Requests Table Card -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">
            <span>📋</span> Applications Queue (<?= count($requests) ?> Record<?= count($requests) === 1 ? '' : 's' ?>)
        </h2>
    </div>
    <div class="table-responsive">
        <table class="table table-hover" aria-label="Leave Applications Review Table">
            <thead>
                <tr>
                    <th>Ref #</th>
                    <th>Requester Employee</th>
                    <th>Department</th>
                    <th>Leave Classification</th>
                    <th>Schedule Range</th>
                    <th>Duration</th>
                    <th>Reason / Justification</th>
                    <th>Status</th>
                    <th class="text-right">Decision Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($requests)): ?>
                    <tr>
                        <td colspan="9" class="text-center" style="padding: 3rem;">
                            <div class="empty-state">
                                <div class="empty-state-icon" aria-hidden="true">🏖️</div>
                                <div class="empty-state-title">No Leave Requests Found</div>
                                <div class="empty-state-text">
                                    There are currently no leave applications matching the selected filter criteria.
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($requests as $req): 
                        $status = $req['status'] ?? 'Pending';
                        $statusBadgeClass = match($status) {
                            'Approved'  => 'badge-success',
                            'Pending'   => 'badge-warning',
                            'Rejected'  => 'badge-danger',
                            'Cancelled' => 'badge-neutral',
                            default     => 'badge-neutral',
                        };
                    ?>
                        <tr>
                            <td>
                                <a href="<?= url('/leaves/' . $req['id']) ?>" style="font-family: var(--font-mono); font-size: 0.8125rem; font-weight: 700; color: var(--color-primary); text-decoration: none;">
                                    #LR-<?= str_pad((string)$req['id'], 3, '0', STR_PAD_LEFT) ?>
                                </a>
                            </td>
                            <td>
                                <strong><?= e($req['first_name'] . ' ' . $req['last_name']) ?></strong>
                                <div style="font-size: 0.75rem; color: var(--color-text-muted); font-family: var(--font-mono);">
                                    <?= e($req['employee_no']) ?>
                                </div>
                            </td>
                            <td>
                                <span class="badge badge-neutral">
                                    <?= e($req['department_name'] ?? 'General') ?>
                                </span>
                            </td>
                            <td>
                                <strong><?= e($req['leave_name']) ?></strong>
                            </td>
                            <td style="font-size: 0.8125rem; white-space: nowrap;">
                                <?= date('M d, Y', strtotime($req['start_date'])) ?> &rarr;<br>
                                <?= date('M d, Y', strtotime($req['end_date'])) ?>
                            </td>
                            <td>
                                <strong><?= (int)$req['number_of_days'] ?></strong> Day<?= (int)$req['number_of_days'] === 1 ? '' : 's' ?>
                            </td>
                            <td style="font-size: 0.8125rem; color: var(--color-text-muted); max-width: 220px;">
                                <span style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;" title="<?= e($req['reason']) ?>">
                                    <?= e($req['reason']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge <?= $statusBadgeClass ?>">
                                    <span class="badge-dot" aria-hidden="true"></span>
                                    <?= e($status) ?>
                                </span>
                                <?php if (!empty($req['reviewer_username'])): ?>
                                    <div style="font-size: 0.7rem; color: var(--color-text-muted); margin-top: 2px;">
                                        By: <?= e($req['reviewer_username']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="text-right">
                                <div class="table-actions" style="justify-content: flex-end;">
                                    <a href="<?= url('/leaves/' . $req['id']) ?>" class="btn btn-secondary btn-sm" title="View Details">
                                        View
                                    </a>

                                    <?php if ($status === 'Pending'): ?>
                                        <!-- Approve Action -->
                                        <form method="POST" action="<?= url('/leaves/' . $req['id'] . '/approve') ?>" style="display: inline;" onsubmit="return confirm('Confirm approval of leave request #<?= $req['id'] ?> for <?= addslashes($req['first_name'] . ' ' . $req['last_name']) ?>?');">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-success btn-sm">
                                                <span>✓</span> Approve
                                            </button>
                                        </form>

                                        <!-- Reject Action with Prompt -->
                                        <form method="POST" action="<?= url('/leaves/' . $req['id'] . '/reject') ?>" style="display: inline;" onsubmit="return promptRejectionReason(this, <?= $req['id'] ?>);">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="rejection_reason" value="">
                                            <button type="submit" class="btn btn-danger btn-sm">
                                                <span>✕</span> Reject
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function promptRejectionReason(form, reqId) {
    var reason = prompt('Please specify the reason for rejecting leave application #LR-' + String(reqId).padStart(3, '0') + ':');
    if (reason === null) {
        return false; // User cancelled prompt
    }
    form.querySelector('input[name="rejection_reason"]').value = reason.trim();
    return true;
}
</script>
