<?php

/**
 * Detailed Leave Request View (Employee Self-Service & HR Review)
 * Includes Confidential Protection Redaction, Frozen Policy Snapshot, Protected Documents & Clarification Workflow
 */
$leave = $req ?? $leave ?? [];
$userRole = strtolower((string)($user['role'] ?? ''));
$isHrOrAdmin = in_array($userRole, ['admin', 'hr'], true);
$canReviewLeave = ($userRole === 'hr') && can('leave.approve');
$isOwner = !empty($user['employee_id']) && (int)$user['employee_id'] === (int)($leave['employee_id'] ?? 0);
$canViewUnmasked = $canViewUnmasked ?? true;
$isConfidential = $isConfidential ?? !empty($leave['is_confidential']);
$isRedacted = !empty($leave['is_redacted']) || !$canViewUnmasked;

$id = (int)($leave['id'] ?? 0);
$status = (string)($leave['status'] ?? 'Pending');
$infoStatus = (string)($leave['info_request_status'] ?? 'None');
$startFormatted = !empty($leave['start_date']) ? date('F j, Y', strtotime((string)$leave['start_date'])) : '—';
$endFormatted = !empty($leave['end_date']) ? date('F j, Y', strtotime((string)$leave['end_date'])) : '—';
$createdFormatted = !empty($leave['created_at']) ? date('M j, Y \a\t g:i A', strtotime((string)$leave['created_at'])) : '—';
$days = (int)($leave['number_of_days'] ?? $leave['total_days'] ?? 1);
$unitLabel = (($leave['entitlement_unit'] ?? 'working_days') === 'calendar_days') ? 'calendar' : 'working';
$leaveTypeName = (string)($leave['leave_name'] ?? $leave['leave_type_name'] ?? 'Leave Application');
$employeeName = trim((string)($leave['first_name'] ?? '') . ' ' . (string)($leave['last_name'] ?? ''));

$badgeClass = match ($status) {
    'Approved'  => 'badge-success',
    'Rejected'  => 'badge-danger',
    'Cancelled' => 'badge-neutral',
    default     => 'badge-warning',
};
$maxMb = number_format(((int)($maxDocBytes ?? 5242880)) / (1024 * 1024), 0);
?>
<div class="page-header">
    <div class="page-title-group">
        <h1>Leave Request #LR-<?= str_pad((string)$id, 3, '0', STR_PAD_LEFT) ?></h1>
        <p>Filed <?= e($createdFormatted) ?> &middot; <?= e($employeeName ?: 'Employee') ?> (<?= e((string)($leave['employee_no'] ?? '—')) ?>)</p>
    </div>
    <div class="page-actions">
        <a href="<?= url($isHrOrAdmin ? '/leaves' : '/employee/leave-requests') ?>" class="btn btn-secondary btn-sm">
            <?= icon('arrow-left', 15) ?>
            <span>Back to List</span>
        </a>
    </div>
</div>

<?php if ($isRedacted): ?>
    <div class="leave-confidential-banner" style="margin-bottom: var(--space-5);">
        <?= icon('lock', 18) ?>
        <div>
            <strong>Confidential Leave Protection Active:</strong> Detailed narrative reasons, category metadata, and protected medical/court attachments are restricted to users holding <code>leave_confidential.view</code> clearance (or the filing employee). This access attempt has been recorded in the audit log.
        </div>
    </div>
<?php elseif ($isConfidential): ?>
    <div class="leave-confidential-banner" style="margin-bottom: var(--space-5);">
        <?= icon('shield', 18) ?>
        <div>
            <strong>Statutory Privacy Notice:</strong> Authorized unmasked view for RA 9262 / RA 9710 confidential leave. Views and attachment downloads are audit-logged.
        </div>
    </div>
<?php endif; ?>

<div class="leave-split-layout">
    <!-- Left / Main Details -->
    <div style="display: grid; gap: var(--space-4);">
        <div class="card">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
                <div>
                    <h2 class="card-title"><?= e($leaveTypeName) ?></h2>
                    <p class="card-subtitle" style="margin: 2px 0 0; font-size: var(--font-size-xs); color: var(--color-text-muted);">
                        <?= e($employeeName) ?> &middot; <?= e((string)($leave['department_name'] ?? 'Department')) ?>
                    </p>
                </div>
                <div style="display: flex; gap: 0.4rem; flex-wrap: wrap;">
                    <?php if (!empty($leave['classification'])): ?>
                        <span class="badge <?= ($leave['classification'] === 'Statutory') ? 'badge-statutory' : 'badge-company' ?>">
                            <?= e((string)$leave['classification']) ?>
                        </span>
                    <?php endif; ?>
                    <?php if (isset($leave['is_paid']) && (int)$leave['is_paid'] === 0): ?>
                        <span class="badge badge-unpaid">Unpaid (LWOP)</span>
                    <?php endif; ?>
                    <span class="badge <?= $badgeClass ?>"><?= e($status) ?></span>
                    <?php if ($infoStatus === 'Requested'): ?>
                        <span class="badge badge-danger">Clarification Requested</span>
                    <?php elseif ($infoStatus === 'Responded'): ?>
                        <span class="badge badge-info">Employee Responded</span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card-body">
                <?php
                    $sameDay = $startFormatted === $endFormatted;
                    $reviewerName = !empty($leave['approver_first_name'])
                        ? trim($leave['approver_first_name'] . ' ' . ($leave['approver_last_name'] ?? ''))
                        : (string)($leave['reviewer_username'] ?? '');
                ?>
                <dl class="leave-facts">
                    <div>
                        <dt>Schedule</dt>
                        <dd><?= e($sameDay ? $startFormatted : $startFormatted . ' – ' . $endFormatted) ?></dd>
                    </div>
                    <div>
                        <dt>Duration</dt>
                        <dd><?= $days ?> <?= e($unitLabel) ?> day<?= $days === 1 ? '' : 's' ?></dd>
                    </div>
                    <div>
                        <dt>Reviewed by</dt>
                        <dd<?= $reviewerName === '' ? ' class="is-muted"' : '' ?>><?= e($reviewerName !== '' ? $reviewerName : 'Awaiting review') ?></dd>
                    </div>
                </dl>

                <div class="leave-detail-section">
                    <h3 class="leave-detail-label">Reason</h3>
                    <p class="leave-narrative"><?= e((string)($leave['reason'] ?? 'No reason provided.')) ?></p>
                </div>

                <?php if (!$isRedacted && !empty($requestMetadata)): ?>
                    <div style="margin-bottom: var(--space-4);">
                        <div style="font-size: var(--font-size-xs); color: var(--color-text-muted); margin-bottom: 0.35rem;">Statutory / Category Filing Details</div>
                        <div class="grid grid-cols-2" style="gap: 0.65rem; padding: 0.75rem 1rem; border: 1px solid var(--color-border); border-radius: var(--radius-md); background: var(--color-bg-subtle);">
                            <?php foreach ($requestMetadata as $mk => $mv):
                                if (str_starts_with((string)$mk, '_') || $mv === '' || $mv === null || $mv === 0) {
                                    continue;
                                }
                            ?>
                                <div>
                                    <span style="font-size: var(--font-size-xs); color: var(--color-text-muted);"><?= e(ucwords(str_replace('_', ' ', (string)$mk))) ?>:</span>
                                    <strong style="font-size: var(--font-size-sm);"><?= e(is_bool($mv) ? ($mv ? 'Yes' : 'No') : (string)$mv) ?></strong>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="leave-detail-section">
                    <h3 class="leave-detail-label">Supporting documents</h3>
                    <?php if ($isRedacted): ?>
                        <p class="leave-detail-empty">Attachments are protected behind confidential clearance.</p>
                    <?php elseif (empty($documents)): ?>
                        <p class="leave-detail-empty">None uploaded.</p>
                    <?php else: ?>
                        <div class="doc-attachment-list">
                            <?php foreach ($documents as $doc):
                                $kb = max(1, (int)round(((int)($doc['file_size_bytes'] ?? 0)) / 1024));
                            ?>
                                <div class="doc-attachment-item">
                                    <div>
                                        <strong><?= e((string)($doc['original_filename'] ?? 'attachment')) ?></strong>
                                        <div style="font-size: var(--font-size-xs); color: var(--color-text-muted);">
                                            <?= e(ucwords(str_replace('_', ' ', (string)($doc['document_category'] ?? 'supporting')))) ?>
                                            &middot; <?= e((string)($doc['mime_type'] ?? '')) ?> &middot; <?= $kb ?> KB
                                        </div>
                                    </div>
                                    <a href="<?= url('/leaves/documents/' . (int)$doc['id']) ?>" class="btn btn-secondary btn-xs" target="_blank" rel="noopener">
                                        <?= icon('eye', 14) ?>
                                        <span>Open Secure File</span>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if (!empty($leave['rejection_reason'])): ?>
                    <div class="leave-eligibility-banner is-blocked" style="margin-bottom: var(--space-4);">
                        <div>
                            <strong>HR Decision Note:</strong> <?= e((string)$leave['rejection_reason']) ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- HR Clarification / Additional Information Thread -->
                <?php if (!empty($leave['hr_info_request_note']) || !empty($leave['employee_info_response'])): ?>
                    <div class="card" style="padding: var(--space-4); margin-bottom: var(--space-4); background: var(--color-bg-subtle); border: 1px solid var(--color-border);">
                        <strong style="display: block; margin-bottom: 0.5rem;">HR Clarification &amp; Supporting Info Thread</strong>
                        <?php if (!empty($leave['hr_info_request_note'])): ?>
                            <div style="margin-bottom: 0.6rem;">
                                <span style="font-size: var(--font-size-xs); color: var(--color-warning); font-weight: 600;">HR Request (<?= !empty($leave['info_requested_at']) ? e(date('M d, Y H:i', strtotime((string)$leave['info_requested_at']))) : 'Recorded' ?>):</span>
                                <p style="margin: 0.2rem 0 0;"><?= e((string)$leave['hr_info_request_note']) ?></p>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($leave['employee_info_response'])): ?>
                            <div>
                                <span style="font-size: var(--font-size-xs); color: var(--color-info); font-weight: 600;">Employee Response (<?= !empty($leave['info_responded_at']) ? e(date('M d, Y H:i', strtotime((string)$leave['info_responded_at']))) : 'Recorded' ?>):</span>
                                <p style="margin: 0.2rem 0 0;"><?= e((string)$leave['employee_info_response']) ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- Employee Response Form for Pending Requests -->
                <?php if ($isOwner && $status === 'Pending'): ?>
                    <div class="card" style="padding: var(--space-4); margin-bottom: var(--space-4); border: 1px solid var(--color-primary-border);">
                        <h3 style="margin: 0 0 0.65rem; font-size: var(--font-size-base);">
                            <?= $infoStatus === 'Requested' ? 'Respond to HR Clarification Request' : 'Submit Additional Clarification or Proof' ?>
                        </h3>
                        <form method="POST" action="<?= url('/leaves/' . $id . '/respond-info') ?>" enctype="multipart/form-data">
                            <?= csrf_field() ?>
                            <div class="form-group">
                                <label for="empInfoResponse" class="form-label">Clarification Response</label>
                                <textarea id="empInfoResponse" name="employee_info_response" class="form-textarea" rows="2" required placeholder="Provide additional clarification or document details for HR."></textarea>
                            </div>
                            <div class="form-group">
                                <label for="clarificationFile" class="form-label">Attach Updated Proof (Optional, Max <?= e($maxMb) ?> MB)</label>
                                <input type="file" id="clarificationFile" name="supporting_document" class="form-input" accept=".pdf,.jpg,.jpeg,.png,.webp">
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm">Send Clarification to HR</button>
                        </form>
                    </div>
                <?php endif; ?>

                <!-- HR Actions: Request Info, Approve, Reject -->
                <?php if ($status === 'Pending' && ($canReviewLeave || $isOwner)): ?>
                    <div style="display: flex; flex-direction: column; gap: var(--space-4); padding-top: var(--space-3); border-top: 1px solid var(--color-border-subtle);">
                        <?php if ($canReviewLeave): ?>
                            <div style="display: flex; flex-wrap: wrap; gap: var(--space-3); align-items: center;">
                                <form method="POST" action="<?= url('/leaves/' . $id . '/approve') ?>" style="display:inline;">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-success btn-sm">
                                        <?= icon('check', 15) ?>
                                        <span>Approve Request</span>
                                    </button>
                                </form>

                                <form method="POST" action="<?= url('/leaves/' . $id . '/reject') ?>" style="display: inline-flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                                    <?= csrf_field() ?>
                                    <input type="text" name="rejection_reason" placeholder="Reason for rejection..." class="form-input" style="width: 240px;" required>
                                    <button type="submit" class="btn btn-danger btn-sm">Reject Request</button>
                                </form>
                            </div>

                            <form method="POST" action="<?= url('/leaves/' . $id . '/request-info') ?>" style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
                                <?= csrf_field() ?>
                                <input type="text" name="info_request_note" class="form-input" style="flex: 1; min-width: 240px;" placeholder="Request clarification or missing document from employee (keeps status Pending)..." required>
                                <button type="submit" class="btn btn-secondary btn-sm">Request Additional Info</button>
                            </form>
                        <?php endif; ?>

                        <?php if ($isOwner): ?>
                            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
                                <span style="font-size: var(--font-size-xs); color: var(--color-text-muted);">You may withdraw this application while it awaits HR decision.</span>
                                <form method="POST" action="<?= url('/leaves/' . $id . '/cancel') ?>" style="display:inline;">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-outline-danger btn-sm">Cancel Application</button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <!-- Right Sidebar: Frozen Policy Snapshot & Employee Balances -->
    <aside style="display: grid; gap: var(--space-4);">
        <?php if (!empty($policySnapshot)): ?>
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Applied Policy Snapshot</h2>
                </div>
                <div class="card-body">
                    <dl style="margin: 0;">
                        <div class="leave-dialog-kv">
                            <dt>Version</dt>
                            <?php $snapCode = (string)($policySnapshot['policy_code'] ?? 'V1'); $snapVer = (int)($policySnapshot['version_number'] ?? 1); ?>
                            <dd><?= e(preg_match('/V' . $snapVer . '$/i', $snapCode) ? $snapCode : $snapCode . ' (v' . $snapVer . ')') ?></dd>
                        </div>
                        <div class="leave-dialog-kv">
                            <dt>Effective From</dt>
                            <dd><?= !empty($policySnapshot['effective_from']) ? e(date('M j, Y', strtotime((string)$policySnapshot['effective_from']))) : '—' ?></dd>
                        </div>
                        <div class="leave-dialog-kv">
                            <dt>Legal Basis</dt>
                            <dd><?= e((string)($policySnapshot['legal_reference'] ?? 'Company Policy')) ?></dd>
                        </div>
                        <div class="leave-dialog-kv">
                            <dt>Pay Treatment</dt>
                            <dd><?= !empty($policySnapshot['is_paid']) ? 'Paid' : 'Unpaid (LWOP)' ?></dd>
                        </div>
                    </dl>
                </div>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Annual Balances</h2>
            </div>
            <div class="card-body" style="padding: 0;">
                <div class="table-wrapper">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Benefit</th>
                                <th>Remaining</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($balances)): ?>
                                <tr>
                                    <td colspan="2" class="text-center" style="color: var(--color-text-muted);">No balance rows recorded.</td>
                                </tr>
                                <?php else: foreach ($balances as $bal): ?>
                                    <tr>
                                        <td><?= e((string)($bal['leave_name'] ?? '')) ?></td>
                                        <td style="text-align: right;"><strong><?= (int)($bal['remaining_days'] ?? 0) ?>d</strong> <span style="color: var(--color-text-muted);">/ <?= (int)($bal['allocated_days'] ?? 0) ?>d</span></td>
                                    </tr>
                            <?php endforeach;
                            endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </aside>
</div>