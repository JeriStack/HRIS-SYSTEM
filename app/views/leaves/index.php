<?php
/**
 * Leave Requests Review Queue View (HR & Admin Access)
 * Employee HRIS & Leave Management System
 */
?>
<div class="page-header">
    <div class="page-title-group">
        <h1>Leave Review Queue</h1>
        <p>Authorize, reject, or audit employee annual leave and medical absence applications.</p>
    </div>
    <div class="page-actions">
        <a href="<?= url('/leaves/balances') ?>" class="btn btn-secondary btn-sm">
            <span>⚖️</span> View Workforce Balances
        </a>
    </div>
</div>

<!-- Filter Hub -->
<div class="filter-bar">
    <span style="font-size: 0.8125rem; font-weight: 600; color: var(--color-text-muted); margin-right: 0.5rem;">
        Filter by Status:
    </span>
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
                    <th>Request ID</th>
                    <th>Requester Employee</th>
                    <th>Department</th>
                    <th>Leave Type</th>
                    <th>Inclusive Dates</th>
                    <th>Duration</th>
                    <th>Reason / Details</th>
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
                                    There are currently no leave applications matching the selected criteria.
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
                            default     => 'badge-neutral',
                        };
                    ?>
                        <tr>
                            <td>
                                <strong style="font-family: var(--font-mono); font-size: 0.8125rem; color: var(--color-primary);">
                                    #LR-<?= str_pad((string)$req['id'], 3, '0', STR_PAD_LEFT) ?>
                                </strong>
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
                                <?= date('M j, Y', strtotime($req['start_date'])) ?> &rarr;<br>
                                <?= date('M j, Y', strtotime($req['end_date'])) ?>
                            </td>
                            <td>
                                <strong><?= (int)$req['number_of_days'] ?></strong> Day<?= (int)$req['number_of_days'] === 1 ? '' : 's' ?>
                            </td>
                            <td style="font-size: 0.8125rem; color: var(--color-text-muted); max-width: 200px;">
                                <?= e($req['reason']) ?>
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
                                <?php if ($status === 'Pending'): ?>
                                    <div class="table-actions" style="justify-content: flex-end;">
                                        <!-- Approve Form -->
                                        <form method="POST" action="<?= url('/leaves/' . $req['id'] . '/approve') ?>" style="display: inline;" onsubmit="return confirm('Confirm approval of leave request #<?= $req['id'] ?> for <?= addslashes($req['first_name'] . ' ' . $req['last_name']) ?>?');">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-success btn-sm">
                                                <span>✓</span> Approve
                                            </button>
                                        </form>

                                        <!-- Reject Form -->
                                        <form method="POST" action="<?= url('/leaves/' . $req['id'] . '/reject') ?>" style="display: inline;" onsubmit="return confirm('Confirm rejection of leave request #<?= $req['id'] ?>?');">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-danger btn-sm">
                                                <span>✕</span> Reject
                                            </button>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <span style="font-size: 0.75rem; color: var(--color-text-muted);">
                                        Processed
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
