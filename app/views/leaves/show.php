<?php
/**
 * Leave Request Detail View
 * Displays comprehensive breakdown of a single leave request and available actions.
 */
$user = $user ?? currentUser() ?? [];
$leave = $req ?? $leave ?? [];
$role = strtolower((string)($user['role'] ?? 'employee'));
$isHrOrAdmin = in_array($role, ['admin', 'hr'], true);
$backUrl = $isHrOrAdmin ? url('/leaves') : url('/employee/leave-requests');

$status = $leave['status'] ?? 'Pending';
$badgeClass = match ($status) {
    'Approved'  => 'badge-success',
    'Rejected'  => 'badge-danger',
    'Cancelled' => 'badge-neutral',
    default     => 'badge-warning',
};

$startFormatted = !empty($leave['start_date']) ? date('F j, Y', strtotime($leave['start_date'])) : '—';
$endFormatted = !empty($leave['end_date']) ? date('F j, Y', strtotime($leave['end_date'])) : '—';
$createdFormatted = !empty($leave['created_at']) ? date('M j, Y \a\t g:i A', strtotime($leave['created_at'])) : '—';
$days = (int)($leave['number_of_days'] ?? $leave['total_days'] ?? 1);
$leaveTypeName = $leave['leave_name'] ?? $leave['leave_type_name'] ?? 'Leave Application';
$employeeName = trim(($leave['first_name'] ?? '') . ' ' . ($leave['last_name'] ?? ''));
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Leave Request #<?= (int)($leave['id'] ?? 0) ?></h1>
        <p class="page-subtitle">Submitted on <?= e($createdFormatted) ?></p>
    </div>
    <div class="page-actions">
        <a href="<?= $backUrl ?>" class="btn btn-secondary">
            <?= icon('arrow-left', 16) ?>
            <span>Back to Requests</span>
        </a>
    </div>
</div>

<div class="card" style="max-width: 760px;">
    <div class="card-header">
        <div class="card-title-group">
            <h2 class="card-title"><?= e($leaveTypeName) ?></h2>
            <p class="card-subtitle"><?= e($employeeName) ?> (<?= e($leave['employee_no'] ?? 'EMP') ?>)</p>
        </div>
        <span class="badge <?= $badgeClass ?> badge-dot"><?= e($status) ?></span>
    </div>

    <div class="card-body">
        <div class="emp-dl-grid mb-6">
            <div class="emp-dl-row">
                <span class="emp-dl-label">Employee</span>
                <span class="emp-dl-value"><?= e($employeeName ?: '—') ?></span>
            </div>
            <div class="emp-dl-row">
                <span class="emp-dl-label">Employee ID</span>
                <span class="emp-dl-value tabular-nums"><?= e($leave['employee_no'] ?? '—') ?></span>
            </div>
            <div class="emp-dl-row">
                <span class="emp-dl-label">Leave Category</span>
                <span class="emp-dl-value"><?= e($leaveTypeName) ?></span>
            </div>
            <div class="emp-dl-row">
                <span class="emp-dl-label">Total Duration</span>
                <span class="emp-dl-value tabular-nums"><?= $days ?> working <?= $days === 1 ? 'day' : 'days' ?></span>
            </div>
            <div class="emp-dl-row">
                <span class="emp-dl-label">Start Date</span>
                <span class="emp-dl-value tabular-nums"><?= e($startFormatted) ?></span>
            </div>
            <div class="emp-dl-row">
                <span class="emp-dl-label">End Date</span>
                <span class="emp-dl-value tabular-nums"><?= e($endFormatted) ?></span>
            </div>
        </div>

        <div class="mb-4">
            <div class="emp-dl-label mb-2">Reason for Leave</div>
            <div class="p-4 rounded-lg border text-sm text-main" style="background: var(--color-surface-hover); border-color: var(--color-border-light); white-space: pre-wrap;"><?= e($leave['reason'] ?? 'No reason provided.') ?></div>
        </div>

        <?php if (!empty($leave['reviewer_username']) || !empty($leave['approver_first_name'])) : ?>
            <div class="emp-dl-row">
                <span class="emp-dl-label">Reviewed By</span>
                <span class="emp-dl-value">
                    <?= e(!empty($leave['approver_first_name']) ? ($leave['approver_first_name'] . ' ' . ($leave['approver_last_name'] ?? '')) : $leave['reviewer_username']) ?>
                </span>
            </div>
        <?php endif; ?>

        <?php if (!empty($leave['rejection_reason'])) : ?>
            <div class="alert alert-danger mt-4 mb-0">
                <span class="alert-icon"><?= icon('x-circle', 18) ?></span>
                <div class="alert-content">
                    <div class="alert-title">Rejection Reason</div>
                    <p class="alert-text"><?= e($leave['rejection_reason']) ?></p>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($status === 'Pending') : ?>
        <div class="card-footer flex justify-between items-center flex-wrap gap-3">
            <?php if ($isHrOrAdmin) : ?>
                <form method="POST" action="<?= url('/leaves/' . (int)$leave['id'] . '/approve') ?>" style="display:inline;">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-success">
                        <?= icon('check', 16) ?>
                        <span>Approve Request</span>
                    </button>
                </form>

                <form method="POST" action="<?= url('/leaves/' . (int)$leave['id'] . '/reject') ?>" class="inline-flex items-center gap-2">
                    <?= csrf_field() ?>
                    <input type="text" name="rejection_reason" placeholder="Reason for rejection..." class="form-control" style="width: 240px;" required>
                    <button type="submit" class="btn btn-danger">
                        <?= icon('x', 16) ?>
                        <span>Reject</span>
                    </button>
                </form>
            <?php endif; ?>

            <?php if ($role === 'employee') : ?>
                <span class="text-xs text-muted">Need to withdraw this application? You can cancel it while it awaits HR review.</span>
                <form method="POST" action="<?= url('/leaves/' . (int)$leave['id'] . '/cancel') ?>" style="display:inline;">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-outline-danger">
                        <?= icon('x', 15) ?>
                        <span>Cancel Request</span>
                    </button>
                </form>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
