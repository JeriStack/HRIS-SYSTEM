<?php
/**
 * HR Support Case Management Detail View
 * Displays public messages, clearly isolated internal HR notes, handler assignment,
 * status transitions, COI/escalation metadata, and secure attachment access.
 */
$ticket = $ticket ?? [];
$isConfidential = !empty($isConfidential);
$messages = $messages ?? [];
$attachments = $attachments ?? [];
$statusHistory = $statusHistory ?? [];
$assignments = $assignments ?? [];
$eligibleHandlers = $eligibleHandlers ?? [];
$allowedNextStatuses = $allowedNextStatuses ?? [];
$priorities = $priorities ?? ['Low', 'Medium', 'High', 'Urgent'];
$canRespond = !empty($canRespond);
$canAssign = !empty($canAssign);
$canManageStatus = !empty($canManageStatus);
$maxFiles = (int)($maxFiles ?? 3);
$maxBytes = (int)($maxBytes ?? 5242880);
$maxMb = number_format($maxBytes / (1024 * 1024), 1);

$status = (string)($ticket['status'] ?? 'Submitted');
$statusBadge = match ($status) {
    'Resolved', 'Closed'   => 'badge-success',
    'Waiting for Employee' => 'badge-warning',
    'Under Review'         => 'badge-info',
    default                => 'badge-primary',
};

$empName = trim(((string)($ticket['first_name'] ?? '')) . ' ' . ((string)($ticket['last_name'] ?? '')));
$assignedName = trim(((string)($ticket['assigned_first_name'] ?? '')) . ' ' . ((string)($ticket['assigned_last_name'] ?? '')));
if ($assignedName === '') {
    $assignedName = (string)($ticket['assigned_to_username'] ?? 'Unassigned');
}

$metadata = [];
if (!empty($ticket['category_metadata_json'])) {
    $decoded = json_decode((string)$ticket['category_metadata_json'], true);
    if (is_array($decoded)) {
        $metadata = $decoded;
    }
}
?>
<div class="page-header">
    <div class="page-title-group">
<?php $isPublic = !empty($ticket['is_public_ticket']) || (($ticket['support_channel'] ?? '') === 'public_auth'); ?>
        <div class="inline-flex items-center gap-2 flex-wrap">
            <h1 class="tabular-nums"><?= e((string)$ticket['ticket_ref']) ?></h1>
            <span class="badge <?= $statusBadge ?>"><?= e($status) ?></span>
            <span class="badge badge-neutral"><?= e((string)($ticket['priority'] ?? 'Medium')) ?> Priority</span>
            <?php if ($isConfidential): ?>
                <span class="badge badge-warning">Confidential Case</span>
            <?php endif; ?>
            <?php if ($isPublic): ?>
                <span class="badge badge-info">Public Login Assistance</span>
            <?php endif; ?>
            <?php if (!empty($ticket['escalated_flag'])): ?>
                <span class="badge badge-danger">Escalated Policy Oversight</span>
            <?php endif; ?>
        </div>
        <p><?= e((string)$ticket['subject']) ?></p>
    </div>
    <div class="page-actions">
        <a href="<?= url('/hr/support') ?>" class="btn btn-secondary btn-sm">
            <?= icon('arrow-left', 15) ?>
            <span>Back to Support Inbox</span>
        </a>
    </div>
</div>

<?php if ($isConfidential): ?>
    <div class="support-privacy-notice" role="note">
        <span class="support-privacy-notice-icon" aria-hidden="true"><?= icon('lock', 18) ?></span>
        <div class="text-sm">
            <strong>Authorized Confidential Complaint Access Logged:</strong> Your view of this record is auditable. Do not disclose complaint contents to non-authorized personnel.
            <?php if (!empty($ticket['subject_employee_id'])): ?>
                <div style="margin-top: 0.35rem;">
                    <strong>Conflict-of-Interest Exclusion Active:</strong> Employee #<?= e((string)($ticket['subject_employee_no'] ?? $ticket['subject_employee_id'])) ?> (<?= e(trim(((string)($ticket['subject_first_name'] ?? '')) . ' ' . ((string)($ticket['subject_last_name'] ?? '')))) ?>) is automatically blocked from viewing or handling this case.
                </div>
            <?php endif; ?>
            <?php if (!empty($ticket['escalated_flag'])): ?>
                <div style="margin-top: 0.35rem;">
                    <strong>Escalation Notice:</strong> <?= e((string)($ticket['escalation_reason'] ?? $escalationPolicySummary ?? '')) ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<div class="support-detail-layout">
    <div>
        <section class="card" style="margin-bottom: var(--space-4);" aria-labelledby="hrThreadHeading">
            <div class="card-header">
                <h2 id="hrThreadHeading" class="card-title">Conversation &amp; Internal HR Notes</h2>
                <span class="text-xs text-muted tabular-nums"><?= count($messages) ?> entr<?= count($messages) === 1 ? 'y' : 'ies' ?></span>
            </div>
            <div class="card-body">
                <?php if (empty($messages)): ?>
                    <p class="text-sm text-muted">No messages logged yet.</p>
                <?php else: ?>
                    <div class="support-thread-list">
                        <?php foreach ($messages as $m):
                            $isInternal = !empty($m['is_internal_note']);
                            $mType = (string)($m['message_type'] ?? 'employee_reply');
                            $isHrSender = in_array((string)($m['sender_role'] ?? ''), ['hr', 'admin'], true);

                            $cardClass = 'support-message-card';
                            if ($isInternal) {
                                $cardClass .= ' is-internal-note';
                            } elseif ($mType === 'status_change') {
                                $cardClass .= ' is-status-event';
                            } elseif ($isHrSender) {
                                $cardClass .= ' is-hr';
                            }

                            $senderRole = (string)($m['sender_role'] ?? '');
                            if ($senderRole === 'public_requester') {
                                $senderName = trim((string)($ticket['requester_name'] ?? '')) ?: 'Public Requester';
                            } else {
                                $senderName = trim(((string)($m['sender_first_name'] ?? '')) . ' ' . ((string)($m['sender_last_name'] ?? '')));
                                if ($senderName === '') {
                                    $senderName = (string)($m['sender_username'] ?? ucfirst($senderRole !== '' ? $senderRole : 'System'));
                                }
                            }

                            $typeLabel = match ($mType) {
                                'initial_submission' => 'Initial Submission',
                                'hr_response'        => 'Employee-Visible HR Response',
                                'info_request'       => 'Information Requested',
                                'internal_note'      => 'Internal HR Note (Private)',
                                'status_change'      => 'Status Change',
                                default              => 'Employee Reply',
                            };
                            $ts = !empty($m['created_at']) ? date('M j, Y g:i A', strtotime((string)$m['created_at'])) : '';
                        ?>
                            <article class="<?= $cardClass ?>">
                                <div class="support-message-meta">
                                    <span class="support-message-sender">
                                        <span><?= e($senderName) ?></span>
                                        <span class="badge <?= $isInternal ? 'badge-warning' : ($mType === 'info_request' ? 'badge-info' : 'badge-neutral') ?>">
                                            <?= e($typeLabel) ?>
                                        </span>
                                    </span>
                                    <time datetime="<?= e((string)($m['created_at'] ?? '')) ?>"><?= e($ts) ?></time>
                                </div>
                                <div class="support-message-body"><?= e((string)($m['body'] ?? '')) ?></div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <?php if ($canRespond): ?>
            <section class="card" aria-labelledby="hrRespondHeading">
                <div class="card-header">
                    <h2 id="hrRespondHeading" class="card-title">Respond or Record Internal Note</h2>
                </div>
                <div class="card-body">
                    <form method="POST" action="<?= url('/hr/support/' . (int)$ticket['id'] . '/respond') ?>" enctype="multipart/form-data" data-support-form novalidate>
                        <?= csrf_field() ?>
                        <div class="sr-only" role="status" aria-live="polite" data-form-live-status></div>

                        <fieldset class="support-mode-selector">
                            <legend class="form-label" style="margin-bottom: 0.45rem;">Action Type</legend>
                            <label class="support-mode-option">
                                <input type="radio" name="response_mode" value="public_reply" data-hr-mode-radio checked>
                                <span>Public Response to Employee</span>
                            </label>
                            <label class="support-mode-option">
                                <input type="radio" name="response_mode" value="request_info" data-hr-mode-radio>
                                <span>Request Additional Information</span>
                            </label>
                            <label class="support-mode-option">
                                <input type="radio" name="response_mode" value="internal_note" data-hr-mode-radio>
                                <span>Internal HR Note (Private)</span>
                            </label>
                        </fieldset>

                        <div id="hrResponseModeNotice" class="support-mode-cue is-public" role="status" aria-live="polite"></div>

                        <div class="form-group">
                            <label for="hrResponseBody" class="form-label">Message / Note Content <span aria-hidden="true">*</span></label>
                            <textarea
                                id="hrResponseBody"
                                name="body"
                                class="form-control support-form-control"
                                rows="4"
                                required
                                minlength="2"
                                maxlength="5000"
                                data-error-id="hr-body-error"
                                placeholder="Write your response or internal case note..."
                            ></textarea>
                            <div id="hr-body-error" class="support-field-error" hidden></div>
                        </div>

                        <div class="form-group">
                            <label for="hrAttachmentsInput" class="form-label">Attach Supporting Files (Optional)</label>
                            <input
                                type="file"
                                id="hrAttachmentsInput"
                                name="attachments[]"
                                class="form-control"
                                multiple
                                accept=".pdf,.jpg,.jpeg,.png,.webp,.txt"
                                data-support-files
                                data-max-files="<?= $maxFiles ?>"
                                data-max-bytes="<?= $maxBytes ?>"
                                data-file-list-id="hrFileList"
                                data-file-status-id="hrFileStatus"
                            >
                            <p class="text-xs text-muted" style="margin-top: 0.3rem; margin-bottom: 0;">
                                Files attached with an Internal HR Note are marked internal-only and never visible to employees. Max <?= $maxMb ?> MB each.
                            </p>
                            <ul id="hrFileList" class="support-selected-files" hidden></ul>
                            <div id="hrFileStatus" class="support-file-status" role="status" aria-live="polite" hidden></div>
                        </div>

                        <div style="display: flex; justify-content: flex-end;">
                            <button type="submit" id="hrResponseSubmitBtn" class="btn btn-primary">Send Response to Employee</button>
                        </div>
                    </form>
                </div>
            </section>
        <?php endif; ?>
    </div>

    <aside style="display: flex; flex-direction: column; gap: var(--space-4);" aria-label="Case controls and metadata">
        <section class="card">
            <div class="card-header">
                <h2 class="card-title">Case Details</h2>
            </div>
            <div class="card-body" style="display: flex; flex-direction: column; gap: 0.65rem;">
                <div>
                    <?php if ($isPublic): ?>
                        <div class="text-xs text-muted">Public Requester</div>
                        <div class="text-sm" style="font-weight: 600;">
                            <?= e((string)($ticket['requester_name'] ?? 'Public Requester')) ?>
                        </div>
                        <div class="text-xs text-muted" style="word-break: break-word;">
                            <?= e((string)($ticket['requester_email'] ?? '')) ?>
                        </div>
                        <?php if (!empty($ticket['public_issue_datetime'])): ?>
                            <div class="text-xs text-muted" style="margin-top: 0.35rem;">
                                Issue time: <?= e((string)$ticket['public_issue_datetime']) ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($ticket['public_client_env'])): ?>
                            <div class="text-xs text-muted">
                                Environment: <?= e((string)$ticket['public_client_env']) ?>
                            </div>
                        <?php endif; ?>
                        <div class="text-xs text-warning" style="margin-top: 0.5rem;">
                            Security notice: never share passwords, reset links, or OTP codes inside ticket replies.
                        </div>
                    <?php else: ?>
                        <div class="text-xs text-muted">Submitted By</div>
                        <div class="text-sm" style="font-weight: 600;">
                            <?= e($empName) ?> (<?= e((string)($ticket['employee_no'] ?? '')) ?>)
                        </div>
                        <?php if (!empty($ticket['department_name'])): ?>
                            <div class="text-xs text-muted"><?= e((string)$ticket['department_name']) ?></div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                <div>
                    <div class="text-xs text-muted">Category</div>
                    <div class="text-sm" style="font-weight: 600;">
                        <?= e((string)$ticket['category']) ?>
                        <?php if (!empty($ticket['complaint_category'])): ?>
                            <div class="text-xs text-muted" style="font-weight: 400;"><?= e((string)$ticket['complaint_category']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <div>
                    <div class="text-xs text-muted">Current Assigned Handler</div>
                    <div class="text-sm" style="font-weight: 600;"><?= e($assignedName) ?></div>
                </div>
                <?php if (!empty($ticket['incident_date'])): ?>
                    <div>
                        <div class="text-xs text-muted">Incident Date</div>
                        <div class="text-sm"><?= e(date('M j, Y', strtotime((string)$ticket['incident_date']))) ?></div>
                    </div>
                <?php endif; ?>
                <?php if (!empty($ticket['subject_reference'])): ?>
                    <div>
                        <div class="text-xs text-muted">Other Party / Unit Context</div>
                        <div class="text-sm"><?= e((string)$ticket['subject_reference']) ?></div>
                    </div>
                <?php endif; ?>
                <?php if ($metadata !== []): ?>
                    <div style="border-top: 1px solid var(--color-border); padding-top: 0.65rem;">
                        <?php foreach ($metadata as $k => $v): ?>
                            <div class="text-xs" style="margin-bottom: 0.25rem;">
                                <strong><?= e(ucwords(str_replace('_', ' ', (string)$k))) ?>:</strong> <?= e((string)$v) ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <?php if ($canAssign): ?>
            <section class="card" aria-labelledby="assignHandlerHeading">
                <div class="card-header">
                    <h2 id="assignHandlerHeading" class="card-title">Assign / Reassign Handler</h2>
                </div>
                <div class="card-body">
                    <form method="POST" action="<?= url('/hr/support/' . (int)$ticket['id'] . '/assign') ?>">
                        <?= csrf_field() ?>
                        <div class="form-group">
                            <label for="assignedToSelect" class="form-label">Eligible Handler</label>
                            <select id="assignedToSelect" name="assigned_to_user_id" class="form-select">
                                <option value="">-- Unassigned --</option>
                                <?php foreach ($eligibleHandlers as $h):
                                    $uid = (int)$h['user_id'];
                                    $hName = trim(((string)($h['first_name'] ?? '')) . ' ' . ((string)($h['last_name'] ?? '')));
                                    if ($hName === '') {
                                        $hName = (string)$h['username'];
                                    }
                                    $hLabel = $hName . ' (' . strtoupper((string)$h['role']) . ')';
                                ?>
                                    <option value="<?= $uid ?>"<?= (int)($ticket['assigned_to_user_id'] ?? 0) === $uid ? ' selected' : '' ?>>
                                        <?= e($hLabel) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($isConfidential && $eligibleHandlers === []): ?>
                                <p class="text-xs text-muted" style="margin-top: 0.35rem; margin-bottom: 0;">
                                    No non-conflicted designated confidential HR handler is currently eligible.
                                </p>
                            <?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label for="assignNotesInput" class="form-label">Assignment Note (Optional)</label>
                            <input type="text" id="assignNotesInput" name="notes" class="form-control" maxlength="255" placeholder="Hand-off reason or instructions">
                        </div>
                        <button type="submit" class="btn btn-secondary btn-sm" style="width: 100%;">Update Handler Assignment</button>
                    </form>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($canManageStatus): ?>
            <section class="card" aria-labelledby="workflowStatusHeading">
                <div class="card-header">
                    <h2 id="workflowStatusHeading" class="card-title">Status &amp; Priority</h2>
                </div>
                <div class="card-body">
                    <form method="POST" action="<?= url('/hr/support/' . (int)$ticket['id'] . '/status') ?>">
                        <?= csrf_field() ?>
                        <div class="form-group">
                            <label for="hrStatusSelect" class="form-label">Workflow Status</label>
                            <select id="hrStatusSelect" name="status" class="form-select">
                                <option value="<?= e($status) ?>" selected><?= e($status) ?> (Current)</option>
                                <?php foreach ($allowedNextStatuses as $nextSt): ?>
                                    <option value="<?= e((string)$nextSt) ?>"><?= e((string)$nextSt) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="hrPrioritySelect" class="form-label">Priority</label>
                            <select id="hrPrioritySelect" name="priority" class="form-select">
                                <?php foreach ($priorities as $p): ?>
                                    <option value="<?= e((string)$p) ?>"<?= ((string)($ticket['priority'] ?? 'Medium')) === $p ? ' selected' : '' ?>>
                                        <?= e((string)$p) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="hrResolutionRemarks" class="form-label">Status / Resolution Remarks</label>
                            <textarea id="hrResolutionRemarks" name="resolution_remarks" class="form-control" rows="3" maxlength="5000" placeholder="Required when resolving or closing a ticket..."><?= e((string)($ticket['resolution_remarks'] ?? '')) ?></textarea>
                        </div>

                        <button type="submit" id="hrStatusSubmitBtn" class="btn btn-primary btn-sm" style="width: 100%;">Save Status &amp; Priority</button>
                    </form>
                </div>
            </section>
        <?php endif; ?>

        <section class="card" aria-labelledby="hrAttachmentsHeading">
            <div class="card-header">
                <h2 id="hrAttachmentsHeading" class="card-title">Case Attachments</h2>
                <span class="badge badge-neutral tabular-nums"><?= count($attachments) ?></span>
            </div>
            <div class="card-body">
                <?php if (empty($attachments)): ?>
                    <p class="text-xs text-muted" style="margin-bottom: 0;">No files attached.</p>
                <?php else: ?>
                    <ul class="support-attachment-list">
                        <?php foreach ($attachments as $att):
                            $kb = max(1, (int)round(((int)$att['file_size_bytes']) / 1024));
                        ?>
                            <li class="support-attachment-row">
                                <div style="min-width: 0; flex: 1;">
                                    <div class="text-xs" style="font-weight: 600; word-break: break-all;">
                                        <?= e((string)$att['original_filename']) ?>
                                        <?php if (!empty($att['is_internal_only'])): ?>
                                            <span class="badge badge-warning">Internal Only</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-xs text-muted"><?= $kb ?> KB &middot; <?= e((string)$att['mime_type']) ?></div>
                                </div>
                                <a href="<?= url('/support/attachments/' . (int)$att['id']) ?>" class="btn btn-secondary btn-sm">Download</a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </section>

        <section class="card" aria-labelledby="hrAuditTrailHeading">
            <div class="card-header">
                <h2 id="hrAuditTrailHeading" class="card-title">Status &amp; Assignment History</h2>
            </div>
            <div class="card-body">
                <ol class="support-timeline">
                    <?php foreach ($statusHistory as $h): ?>
                        <li class="support-timeline-item">
                            <div style="display: flex; justify-content: space-between; gap: 0.5rem; font-size: var(--font-size-xs);">
                                <strong><?= e((string)$h['to_status']) ?></strong>
                                <span class="text-muted"><?= !empty($h['created_at']) ? e(date('M j, Y g:i A', strtotime((string)$h['created_at']))) : '' ?></span>
                            </div>
                            <div class="text-xs text-muted">
                                By <?= e((string)($h['actor_username'] ?? 'System')) ?><?= !empty($h['remarks']) ? ' — ' . e((string)$h['remarks']) : '' ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                    <?php foreach ($assignments as $asg):
                        $targetName = (string)($asg['assignee_username'] ?? 'Unassigned');
                    ?>
                        <li class="support-timeline-item">
                            <div style="display: flex; justify-content: space-between; gap: 0.5rem; font-size: var(--font-size-xs);">
                                <strong><?= e(ucfirst((string)$asg['action'])) ?>: <?= e($targetName) ?></strong>
                                <span class="text-muted"><?= !empty($asg['created_at']) ? e(date('M j, Y g:i A', strtotime((string)$asg['created_at']))) : '' ?></span>
                            </div>
                            <div class="text-xs text-muted">
                                By <?= e((string)($asg['actor_username'] ?? 'System')) ?><?= !empty($asg['notes']) ? ' — ' . e((string)$asg['notes']) : '' ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </div>
        </section>
    </aside>
</div>
