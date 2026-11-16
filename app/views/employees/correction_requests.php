<?php

/**
 * HR Profile Correction Requests Queue
 * Allows HR and Administrators to inspect, filter, approve, or reject employee correction requests.
 */

use App\Models\ProfileCorrectionRequest;

$statuses = ['All', 'Pending', 'Approved', 'Rejected', 'Cancelled'];
$selected = (string)($selectedStatus ?? 'All');
$pending = (int)($pendingCount ?? 0);
?>

<div class="page-header">
    <div>
        <h1 class="page-title">
            Profile Correction Requests
            <?php if ($pending > 0): ?>
                <span class="badge badge-warning"><?= $pending ?> Pending</span>
            <?php endif; ?>
        </h1>
        <p class="page-subtitle">Review employee requests for HR-verified identity, work email, and organizational record changes.</p>
    </div>
    <div class="page-actions">
        <a href="<?= url('/hr/employees') ?>" class="btn btn-secondary">
            <?= icon('arrow-left', 16) ?>
            <span>Employees Directory</span>
        </a>
    </div>
</div>

<div class="card mb-5">
    <div class="card-body">
        <div class="emp-filter-pills" role="navigation" aria-label="Filter correction requests by status">
            <?php foreach ($statuses as $statusOption): ?>
                <?php
                $isActive = ($selected === $statusOption);
                $href = $statusOption === 'All'
                    ? url('/hr/correction-requests')
                    : url('/hr/correction-requests?status=' . urlencode($statusOption));
                ?>
                <a href="<?= $href ?>" class="emp-filter-pill<?= $isActive ? ' active' : '' ?>"<?= $isActive ? ' aria-current="page"' : '' ?>>
                    <?= e($statusOption) ?>
                    <?php if ($statusOption === 'Pending' && $pending > 0): ?>
                        (<?= $pending ?>)
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <?php if (empty($requests)): ?>
            <div class="profile-empty-state" style="margin: var(--space-5);">
                <strong>No <?= $selected !== 'All' ? e(strtolower($selected)) . ' ' : '' ?>profile correction requests found</strong>
                <p>Employee-submitted requests to update verified records such as legal names, civil status, work email, or department assignment will appear here.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Field</th>
                            <th>Current &rarr; Requested</th>
                            <th>Employee Reason</th>
                            <th>Status</th>
                            <th>Submitted</th>
                            <th>Review &amp; Decision</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requests as $req): ?>
                            <?php
                            $reqId = (int)$req['id'];
                            $empId = (int)$req['employee_id'];
                            $empName = trim((string)($req['first_name'] ?? '') . ' ' . (string)($req['last_name'] ?? ''));
                            $rStatus = (string)($req['status'] ?? ProfileCorrectionRequest::STATUS_PENDING);
                            $rBadge = match ($rStatus) {
                                ProfileCorrectionRequest::STATUS_APPROVED  => 'badge-success',
                                ProfileCorrectionRequest::STATUS_REJECTED  => 'badge-danger',
                                ProfileCorrectionRequest::STATUS_CANCELLED => 'badge-secondary',
                                default                                    => 'badge-warning',
                            };
                            $revName = trim(
                                (string)($req['reviewer_first_name'] ?? '')
                                . ' '
                                . (string)($req['reviewer_last_name'] ?? '')
                            ) ?: ($req['reviewer_username'] ?? 'HR Reviewer');
                            ?>
                            <tr>
                                <td>
                                    <a href="<?= url('/hr/employees/' . $empId) ?>" class="font-semibold text-primary">
                                        <?= e($empName ?: ($req['employee_no'] ?? "EMP #{$empId}")) ?>
                                    </a>
                                    <div class="text-xs text-muted"><?= e($req['employee_no'] ?? '') ?></div>
                                </td>
                                <td class="font-semibold"><?= e($req['field_label'] ?? $req['field_name']) ?></td>
                                <td>
                                    <div class="corr-diff-wrap">
                                        <span class="corr-val-old"><?= e($req['current_display'] ?? 'Unset') ?></span>
                                        <span aria-hidden="true">&rarr;</span>
                                        <span class="corr-val-new"><?= e($req['requested_display'] ?? $req['requested_value']) ?></span>
                                    </div>
                                </td>
                                <td><?= e($req['reason'] ?? '') ?></td>
                                <td><span class="badge <?= $rBadge ?>"><?= e($rStatus) ?></span></td>
                                <td class="whitespace-nowrap">
                                    <?= e(!empty($req['created_at']) ? date('M j, Y', strtotime((string)$req['created_at'])) : 'N/A') ?>
                                </td>
                                <td>
                                    <?php if ($rStatus === ProfileCorrectionRequest::STATUS_PENDING): ?>
                                        <div class="profile-card-actions">
                                            <form method="POST" action="<?= url('/hr/correction-requests/' . $reqId . '/approve') ?>" class="profile-card-actions" onsubmit="return confirm('Approve this change and apply it to <?= e($req['employee_no'] ?? 'the employee') ?>?');">
                                                <?= csrf_field() ?>
                                                <input type="text" name="decision_reason" class="form-control" style="max-width: 170px; padding: 0.3rem 0.55rem; font-size: var(--font-size-xs);" maxlength="1000" placeholder="Optional approval note" aria-label="Approval note for request #<?= $reqId ?>">
                                                <button type="submit" class="btn btn-primary btn-sm">Approve</button>
                                            </form>

                                            <details>
                                                <summary class="btn btn-outline-danger btn-sm" style="cursor: pointer; list-style: none;">Reject</summary>
                                                <form method="POST" action="<?= url('/hr/correction-requests/' . $reqId . '/reject') ?>" class="profile-form-stack mt-1" style="min-width: 220px;">
                                                    <?= csrf_field() ?>
                                                    <label for="rejectReason<?= $reqId ?>" class="text-xs font-semibold">Rejection Reason <span class="required">*</span></label>
                                                    <textarea id="rejectReason<?= $reqId ?>" name="decision_reason" rows="2" class="form-control" maxlength="1000" required placeholder="Explain why this request is rejected"></textarea>
                                                    <button type="submit" class="btn btn-danger btn-sm">Confirm Rejection</button>
                                                </form>
                                            </details>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-xs">
                                            <?php if (!empty($req['decision_reason'])): ?>
                                                <div><?= e($req['decision_reason']) ?></div>
                                            <?php endif; ?>
                                            <div class="text-muted">
                                                <?= e($revName) ?>
                                                <?php if (!empty($req['reviewed_at'])): ?>
                                                    &middot; <?= e(date('M j, Y', strtotime((string)$req['reviewed_at']))) ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
