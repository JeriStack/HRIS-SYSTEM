<?php
/**
 * Detailed Leave Request View
 * Employee HRIS & Leave Management System - Phase 8
 */

$status = $req['status'] ?? 'Pending';
$statusBadgeClass = match($status) {
    'Approved'  => 'badge-success',
    'Pending'   => 'badge-warning',
    'Rejected'  => 'badge-danger',
    'Cancelled' => 'badge-neutral',
    default     => 'badge-neutral',
};

$userRole = strtolower($user['role'] ?? 'employee');
$isReviewer = ($userRole === 'hr');
$isOwner = ((int)($user['employee_id'] ?? 0) === (int)$req['employee_id']);
?>
<div class="page-header">
    <div class="page-title-group">
        <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.35rem; flex-wrap: wrap;">
            <h1>Leave Application #LR-<?= str_pad((string)$req['id'], 3, '0', STR_PAD_LEFT) ?></h1>
            <span class="badge <?= $statusBadgeClass ?>" style="font-size: 0.8125rem; padding: 0.35rem 0.75rem;">
                <span class="badge-dot" aria-hidden="true"></span>
                <?= e($status) ?>
            </span>
        </div>
        <p>Submitted by <strong><?= e($req['first_name'] . ' ' . $req['last_name']) ?></strong> (<?= e($req['employee_no']) ?>) &bull; <?= e($req['department_name'] ?? 'General') ?></p>
    </div>
    <div class="page-actions">
        <?php if ($isReviewer): ?>
            <a href="<?= url('/leaves') ?>" class="btn btn-secondary btn-sm">
                <span>←</span> Back to Queue
            </a>
        <?php else: ?>
            <a href="<?= url('/employee/leave-requests') ?>" class="btn btn-secondary btn-sm">
                <span>←</span> Back to My Requests
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="grid grid-cols-3" style="align-items: start; gap: 1.5rem;">
    <!-- Main Request Details (Spans 2 columns) -->
    <div style="grid-column: span 2; display: flex; flex-direction: column; gap: 1.5rem;">
        <div class="card">
            <div class="card-header">
                <h2 class="card-title"><span>📋</span> Application Overview</h2>
                <span style="font-size: 0.8125rem; color: var(--color-text-muted);">
                    Filed on <?= date('M d, Y h:i A', strtotime($req['created_at'])) ?>
                </span>
            </div>
            <div class="card-body">
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.25rem; margin-bottom: 1.5rem;">
                    <div>
                        <div style="font-size: 0.75rem; font-weight: 700; color: var(--color-text-muted); text-transform: uppercase;">
                            Leave Classification
                        </div>
                        <div style="font-size: 1.05rem; font-weight: 600; color: var(--color-text-main); margin-top: 0.25rem;">
                            <?= e($req['leave_name']) ?>
                        </div>
                        <?php if (!empty($req['leave_description'])): ?>
                            <div style="font-size: 0.8125rem; color: var(--color-text-muted); margin-top: 0.15rem;">
                                <?= e($req['leave_description']) ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div>
                        <div style="font-size: 0.75rem; font-weight: 700; color: var(--color-text-muted); text-transform: uppercase;">
                            Authorized Duration
                        </div>
                        <div style="font-size: 1.05rem; font-weight: 700; color: var(--color-primary); margin-top: 0.25rem;">
                            <?= (int)$req['number_of_days'] ?> Working Day<?= (int)$req['number_of_days'] === 1 ? '' : 's' ?>
                        </div>
                        <div style="font-size: 0.8125rem; color: var(--color-text-muted); margin-top: 0.15rem;">
                            (Weekends excluded)
                        </div>
                    </div>

                    <div>
                        <div style="font-size: 0.75rem; font-weight: 700; color: var(--color-text-muted); text-transform: uppercase;">
                            Inclusive Schedule
                        </div>
                        <div style="font-size: 0.95rem; font-weight: 600; color: var(--color-text-main); margin-top: 0.25rem;">
                            <?= date('l, M d, Y', strtotime($req['start_date'])) ?> &rarr;<br>
                            <?= date('l, M d, Y', strtotime($req['end_date'])) ?>
                        </div>
                    </div>

                    <div>
                        <div style="font-size: 0.75rem; font-weight: 700; color: var(--color-text-muted); text-transform: uppercase;">
                            Requester Information
                        </div>
                        <div style="font-size: 0.95rem; font-weight: 600; color: var(--color-text-main); margin-top: 0.25rem;">
                            <?= e($req['first_name'] . ' ' . $req['last_name']) ?>
                        </div>
                        <div style="font-size: 0.8125rem; color: var(--color-text-muted);">
                            <?= e($req['email']) ?> &bull; <?= e($req['employee_no']) ?>
                        </div>
                    </div>
                </div>

                <!-- Reason Section -->
                <div style="border-top: 1px solid var(--color-border); padding-top: 1.25rem; margin-top: 1rem;">
                    <div style="font-size: 0.75rem; font-weight: 700; color: var(--color-text-muted); text-transform: uppercase; margin-bottom: 0.35rem;">
                        Employee Statement & Reason
                    </div>
                    <div style="background-color: var(--color-bg-muted); border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 1rem; font-size: 0.9rem; line-height: 1.6; color: var(--color-text-main);">
                        <?= nl2br(e($req['reason'])) ?>
                    </div>
                </div>

                <!-- Adjudication Review Audit Section -->
                <?php if ($status !== 'Pending'): ?>
                    <div style="border-top: 1px solid var(--color-border); padding-top: 1.25rem; margin-top: 1.25rem;">
                        <div style="font-size: 0.75rem; font-weight: 700; color: var(--color-text-muted); text-transform: uppercase; margin-bottom: 0.35rem;">
                            Adjudication Record
                        </div>
                        <div style="display: flex; gap: 2rem; font-size: 0.875rem; flex-wrap: wrap;">
                            <div>
                                <strong>Decision:</strong> <span class="badge <?= $statusBadgeClass ?>"><?= e($status) ?></span>
                            </div>
                            <?php if (!empty($req['reviewer_username'])): ?>
                                <div>
                                    <strong>Reviewed By:</strong> <?= e($req['reviewer_username']) ?>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($req['approved_at'])): ?>
                                <div>
                                    <strong>Date:</strong> <?= date('M d, Y h:i A', strtotime($req['approved_at'])) ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if ($status === 'Rejected' && !empty($req['rejection_reason'])): ?>
                            <div style="margin-top: 1rem; background-color: #FEF2F2; border: 1px solid #FECACA; border-radius: var(--radius-md); padding: 0.85rem 1rem;">
                                <strong style="color: #991B1B; font-size: 0.85rem;">HR Rejection Reason / Note:</strong>
                                <p style="margin: 0.35rem 0 0 0; color: #7F1D1D; font-size: 0.875rem;">
                                    <?= nl2br(e($req['rejection_reason'])) ?>
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Action Box for Pending Requests -->
        <?php if ($status === 'Pending'): ?>
            <div class="card" style="border: 1px solid var(--color-warning);">
                <div class="card-header" style="background-color: #FFFBEB;">
                    <h2 class="card-title" style="color: #92400E;">
                        <span>⚖️</span> Pending Actions
                    </h2>
                </div>
                <div class="card-body">
                    <?php if ($isReviewer): ?>
                        <div style="display: flex; gap: 1.5rem; flex-wrap: wrap;">
                            <!-- Approve Form -->
                            <div style="flex: 1; min-width: 260px;">
                                <h3 style="font-size: 0.95rem; font-weight: 600; margin-bottom: 0.5rem; color: var(--color-success);">
                                    Approve Application
                                </h3>
                                <p style="font-size: 0.8125rem; color: var(--color-text-muted); margin-bottom: 1rem;">
                                    Approving will authorize this absence and atomically deduct <strong><?= (int)$req['number_of_days'] ?> day(s)</strong> from the employee's available balance.
                                </p>
                                <form method="POST" action="<?= url('/leaves/' . $req['id'] . '/approve') ?>" onsubmit="return confirm('Confirm approval of leave request #<?= $req['id'] ?>?');">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-success" style="width: 100%;">
                                        <span>✓</span> Approve Leave Request
                                    </button>
                                </form>
                            </div>

                            <!-- Reject Form -->
                            <div style="flex: 1; min-width: 260px; border-left: 1px solid var(--color-border); padding-left: 1.5rem;">
                                <h3 style="font-size: 0.95rem; font-weight: 600; margin-bottom: 0.5rem; color: var(--color-danger);">
                                    Decline / Reject
                                </h3>
                                <form method="POST" action="<?= url('/leaves/' . $req['id'] . '/reject') ?>" onsubmit="return confirm('Confirm rejection of leave request #<?= $req['id'] ?>?');">
                                    <?= csrf_field() ?>
                                    <div class="form-group" style="margin-bottom: 0.75rem;">
                                        <label for="rejection_reason" class="form-label" style="font-size: 0.8125rem;">Rejection Justification</label>
                                        <textarea name="rejection_reason" id="rejection_reason" rows="2" class="form-textarea" placeholder="State reason for declining this request..."></textarea>
                                    </div>
                                    <button type="submit" class="btn btn-danger" style="width: 100%;">
                                        <span>✕</span> Reject Leave Request
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php elseif ($isOwner): ?>
                        <div>
                            <p style="font-size: 0.875rem; color: var(--color-text-muted); margin-bottom: 1rem;">
                                This request is currently awaiting HR review. If your schedule has changed, you may cancel this application.
                            </p>
                            <form method="POST" action="<?= url('/leaves/' . $req['id'] . '/cancel') ?>" onsubmit="return confirm('Are you sure you want to cancel this leave application?');">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-danger btn-sm">
                                    <span>✕</span> Cancel My Application
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Right Column: Employee Balances Card -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">
                <span>⚖️</span> Leave Quotas (<?= date('Y', strtotime($req['start_date'])) ?>)
            </h2>
        </div>
        <div class="card-body" style="padding: 1.25rem;">
            <?php if (empty($balances)): ?>
                <div style="font-size: 0.85rem; color: var(--color-text-muted);">
                    No balance allocation found for year <?= date('Y', strtotime($req['start_date'])) ?>.
                </div>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 1rem;">
                    <?php foreach ($balances as $b): 
                        $isCurrentType = ((int)$b['leave_type_id'] === (int)$req['leave_type_id']);
                        $rem = (int)($b['remaining_days'] ?? 0);
                    ?>
                        <div style="padding: 0.75rem; border-radius: var(--radius-md); <?= $isCurrentType ? 'background-color: var(--color-bg-muted); border: 1px solid var(--color-primary);' : 'border-bottom: 1px solid var(--color-border-subtle);' ?>">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.25rem;">
                                <strong style="font-size: 0.875rem;"><?= e($b['leave_name']) ?></strong>
                                <span class="badge <?= $rem > 2 ? 'badge-success' : ($rem > 0 ? 'badge-warning' : 'badge-danger') ?>">
                                    <?= $rem ?> Left
                                </span>
                            </div>
                            <div style="font-size: 0.75rem; color: var(--color-text-muted);">
                                <?= (int)$b['used_days'] ?> used / <?= (int)$b['allocated_days'] ?> total
                            </div>
                            <?php if ($isCurrentType): ?>
                                <div style="font-size: 0.7rem; font-weight: 700; color: var(--color-primary); margin-top: 0.25rem;">
                                    ★ Target Leave Type for this Request
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
