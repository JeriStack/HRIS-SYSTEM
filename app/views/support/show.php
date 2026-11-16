<?php
/**
 * Employee Support Ticket / Confidential Complaint Detail View
 * Strictly renders public conversation messages and non-internal attachments only.
 */
$ticket = $ticket ?? [];
$isConfidential = !empty($isConfidential);
$messages = $messages ?? [];
$attachments = $attachments ?? [];
$statusHistory = $statusHistory ?? [];
$canReply = !empty($canReply);
$canReopen = !empty($canReopen);
$canConfirmClose = !empty($canConfirmClose);
$reopenWindowDays = (int)($reopenWindowDays ?? 14);
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
        <div class="inline-flex items-center gap-2 flex-wrap">
            <h1 class="tabular-nums"><?= e((string)$ticket['ticket_ref']) ?></h1>
            <span class="badge <?= $statusBadge ?>"><?= e($status) ?></span>
            <?php if ($isConfidential): ?>
                <span class="badge badge-warning">Confidential Complaint</span>
            <?php endif; ?>
            <?php if (!empty($ticket['escalated_flag'])): ?>
                <span class="badge badge-danger">Escalated Oversight</span>
            <?php endif; ?>
        </div>
        <p><?= e((string)$ticket['subject']) ?></p>
    </div>
    <div class="page-actions">
        <a href="<?= url('/support') ?>" class="btn btn-secondary btn-sm">
            <?= icon('arrow-left', 15) ?>
            <span>My Support Requests</span>
        </a>
        <?php if ($canConfirmClose): ?>
            <button type="button" class="btn btn-primary btn-sm" data-dialog-open="confirmCloseDialog">
                <?= icon('check-circle', 15) ?>
                <span>Confirm Closure</span>
            </button>
        <?php endif; ?>
        <?php if ($canReopen): ?>
            <button type="button" class="btn btn-secondary btn-sm" data-dialog-open="reopenTicketDialog">
                <span>Reopen Request</span>
            </button>
        <?php endif; ?>
    </div>
</div>

<?php if ($isConfidential): ?>
    <div class="support-privacy-notice" role="note">
        <span class="support-privacy-notice-icon" aria-hidden="true"><?= icon('lock', 18) ?></span>
        <div class="text-sm">
            <strong>Restricted Confidential Case:</strong> Access to this complaint and its attachments is restricted strictly to you and specifically authorized confidential handlers (excluding any conflicted party).
            <?php if (!empty($ticket['escalated_flag'])): ?>
                <div style="margin-top: 0.35rem;"><?= e((string)($escalationPolicySummary ?? '')) ?></div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php if ($status === 'Waiting for Employee'): ?>
    <div class="alert alert-warning" role="alert">
        <span class="alert-icon" aria-hidden="true"><?= icon('alert-triangle', 18) ?></span>
        <div class="alert-content">
            <strong>Action Required:</strong> The HR handler requested additional information. Please post a reply below so review can resume.
        </div>
    </div>
<?php endif; ?>

<div class="support-detail-layout">
    <div>
        <?php if (!empty($ticket['resolution_remarks']) && in_array($status, ['Resolved', 'Closed'], true)): ?>
            <section class="card" style="margin-bottom: var(--space-4);" aria-labelledby="resolutionRemarksHeading">
                <div class="card-header">
                    <h2 id="resolutionRemarksHeading" class="card-title">Resolution Summary</h2>
                    <span class="badge badge-success"><?= e($status) ?></span>
                </div>
                <div class="card-body">
                    <p style="margin-bottom: 0; white-space: pre-wrap;"><?= e((string)$ticket['resolution_remarks']) ?></p>
                    <?php if ($canConfirmClose): ?>
                        <p class="text-xs text-muted" style="margin-top: var(--space-3); margin-bottom: 0;">
                            You may confirm final closure above or reopen within <?= $reopenWindowDays ?> days if the issue persists.
                        </p>
                    <?php endif; ?>
                </div>
            </section>
        <?php endif; ?>

        <section class="card" style="margin-bottom: var(--space-4);" aria-labelledby="conversationThreadHeading">
            <div class="card-header">
                <h2 id="conversationThreadHeading" class="card-title">Conversation History</h2>
                <span class="text-xs text-muted tabular-nums"><?= count($messages) ?> entr<?= count($messages) === 1 ? 'y' : 'ies' ?></span>
            </div>
            <div class="card-body">
                <?php if (empty($messages)): ?>
                    <p class="text-sm text-muted">No conversation entries recorded yet.</p>
                <?php else: ?>
                    <div class="support-thread-list">
                        <?php foreach ($messages as $m):
                            $mType = (string)($m['message_type'] ?? 'employee_reply');
                            $isHrSender = in_array((string)($m['sender_role'] ?? ''), ['hr', 'admin'], true);
                            $cardClass = 'support-message-card';
                            if ($mType === 'status_change') {
                                $cardClass .= ' is-status-event';
                            } elseif ($isHrSender) {
                                $cardClass .= ' is-hr';
                            }

                            $senderName = trim(((string)($m['sender_first_name'] ?? '')) . ' ' . ((string)($m['sender_last_name'] ?? '')));
                            if ($senderName === '') {
                                $senderName = (string)($m['sender_username'] ?? ucfirst((string)($m['sender_role'] ?? 'System')));
                            }
                            if ($isHrSender && $isConfidential) {
                                $senderName = 'Authorized HR Handler';
                            }

                            $typeLabel = match ($mType) {
                                'initial_submission' => 'Initial Submission',
                                'hr_response'        => 'HR Response',
                                'info_request'       => 'Additional Information Requested',
                                'status_change'      => 'Status Update',
                                default              => 'Employee Reply',
                            };
                            $ts = !empty($m['created_at']) ? date('M j, Y g:i A', strtotime((string)$m['created_at'])) : '';
                        ?>
                            <article class="<?= $cardClass ?>">
                                <div class="support-message-meta">
                                    <span class="support-message-sender">
                                        <span><?= e($senderName) ?></span>
                                        <span class="badge <?= $mType === 'info_request' ? 'badge-warning' : 'badge-neutral' ?>"><?= e($typeLabel) ?></span>
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

        <?php if ($canReply): ?>
            <section class="card" aria-labelledby="replyFormHeading">
                <div class="card-header">
                    <h2 id="replyFormHeading" class="card-title">
                        <?= $status === 'Waiting for Employee' ? 'Submit Requested Information / Reply' : 'Post Follow-Up Reply' ?>
                    </h2>
                </div>
                <div class="card-body">
                    <form method="POST" action="<?= url('/support/' . (int)$ticket['id'] . '/reply') ?>" enctype="multipart/form-data" data-support-form novalidate>
                        <?= csrf_field() ?>
                        <div class="sr-only" role="status" aria-live="polite" data-form-live-status></div>

                        <div class="form-group">
                            <label for="employeeReplyBody" class="form-label">Message <span aria-hidden="true">*</span></label>
                            <textarea
                                id="employeeReplyBody"
                                name="body"
                                class="form-control support-form-control"
                                rows="4"
                                required
                                minlength="2"
                                maxlength="5000"
                                data-error-id="reply-body-error"
                                placeholder="Provide your update or response to HR..."
                            ></textarea>
                            <div id="reply-body-error" class="support-field-error" hidden></div>
                        </div>

                        <div class="form-group">
                            <label for="replyAttachmentsInput" class="form-label">Attach Supporting Files (Optional)</label>
                            <input
                                type="file"
                                id="replyAttachmentsInput"
                                name="attachments[]"
                                class="form-control"
                                multiple
                                accept=".pdf,.jpg,.jpeg,.png,.webp,.txt"
                                data-support-files
                                data-max-files="<?= $maxFiles ?>"
                                data-max-bytes="<?= $maxBytes ?>"
                                data-file-list-id="replyFileList"
                                data-file-status-id="replyFileStatus"
                            >
                            <p class="text-xs text-muted" style="margin-top: 0.3rem; margin-bottom: 0;">
                                Up to <?= $maxFiles ?> files (PDF, JPG, PNG, WEBP, TXT), max <?= $maxMb ?> MB each.
                            </p>
                            <ul id="replyFileList" class="support-selected-files" hidden></ul>
                            <div id="replyFileStatus" class="support-file-status" role="status" aria-live="polite" hidden></div>
                        </div>

                        <div style="display: flex; justify-content: flex-end;">
                            <button type="submit" class="btn btn-primary">
                                <?= icon('send', 15) ?>
                                <span>Send Reply</span>
                            </button>
                        </div>
                    </form>
                </div>
            </section>
        <?php else: ?>
            <div class="support-disclosure-box">
                <p class="text-sm text-muted" style="margin-bottom: 0;">
                    This request is currently <strong><?= e($status) ?></strong>. Direct replies are closed<?= $canReopen ? '; use Reopen Request above if additional follow-up is required.' : '.' ?>
                </p>
            </div>
        <?php endif; ?>
    </div>

    <aside style="display: flex; flex-direction: column; gap: var(--space-4);" aria-label="Ticket summary and history">
        <section class="card">
            <div class="card-header">
                <h2 class="card-title">Ticket Summary</h2>
            </div>
            <div class="card-body" style="display: flex; flex-direction: column; gap: 0.65rem;">
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
                    <div class="text-xs text-muted">Priority</div>
                    <div class="text-sm" style="font-weight: 600;"><?= e((string)($ticket['priority'] ?? 'Medium')) ?></div>
                </div>
                <div>
                    <div class="text-xs text-muted">Assigned Handler</div>
                    <div class="text-sm" style="font-weight: 600;"><?= e((string)($assignedHandlerDisplay ?? 'Pending Assignment')) ?></div>
                </div>
                <?php if (!empty($ticket['incident_date'])): ?>
                    <div>
                        <div class="text-xs text-muted">Incident Date</div>
                        <div class="text-sm"><?= e(date('M j, Y', strtotime((string)$ticket['incident_date']))) ?></div>
                    </div>
                <?php endif; ?>
                <div>
                    <div class="text-xs text-muted">Submitted At</div>
                    <div class="text-sm"><?= !empty($ticket['created_at']) ? e(date('M j, Y g:i A', strtotime((string)$ticket['created_at']))) : '—' ?></div>
                </div>
                <?php if ($metadata !== []): ?>
                    <div style="border-top: 1px solid var(--color-border); padding-top: 0.65rem;">
                        <div class="text-xs text-muted" style="margin-bottom: 0.35rem;">Additional Context</div>
                        <?php foreach ($metadata as $k => $v): ?>
                            <div class="text-xs" style="margin-bottom: 0.25rem;">
                                <strong><?= e(ucwords(str_replace('_', ' ', (string)$k))) ?>:</strong> <?= e((string)$v) ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="card" aria-labelledby="attachmentsPanelHeading">
            <div class="card-header">
                <h2 id="attachmentsPanelHeading" class="card-title">Attachments</h2>
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

        <section class="card" aria-labelledby="statusTimelineHeading">
            <div class="card-header">
                <h2 id="statusTimelineHeading" class="card-title">Status Timeline</h2>
            </div>
            <div class="card-body">
                <?php if (empty($statusHistory)): ?>
                    <p class="text-xs text-muted" style="margin-bottom: 0;">No status transitions yet.</p>
                <?php else: ?>
                    <ol class="support-timeline">
                        <?php foreach ($statusHistory as $h): ?>
                            <li class="support-timeline-item">
                                <div style="display: flex; justify-content: space-between; gap: 0.5rem; font-size: var(--font-size-xs);">
                                    <strong><?= e((string)$h['to_status']) ?></strong>
                                    <span class="text-muted"><?= !empty($h['created_at']) ? e(date('M j, Y g:i A', strtotime((string)$h['created_at']))) : '' ?></span>
                                </div>
                                <?php if (!empty($h['remarks'])): ?>
                                    <div class="text-xs text-muted"><?= e((string)$h['remarks']) ?></div>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>
            </div>
        </section>
    </aside>
</div>

<?php if ($canConfirmClose): ?>
    <dialog id="confirmCloseDialog" class="support-dialog" aria-labelledby="confirmCloseTitle">
        <form method="POST" action="<?= url('/support/' . (int)$ticket['id'] . '/close') ?>">
            <?= csrf_field() ?>
            <div class="support-dialog-head">
                <h2 id="confirmCloseTitle" style="font-size: var(--font-size-md); margin: 0;">Confirm Request Closure</h2>
                <button type="button" class="btn btn-secondary btn-sm" data-dialog-close aria-label="Close modal"><?= icon('x', 14) ?></button>
            </div>
            <div class="support-dialog-body">
                <p class="text-sm" style="margin-bottom: var(--space-3);">
                    Confirming closure changes <strong><?= e((string)$ticket['ticket_ref']) ?></strong> from Resolved to Closed.
                </p>
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="closeRemarksInput" class="form-label">Closing Note (Optional)</label>
                    <input type="text" id="closeRemarksInput" name="remarks" class="form-control" maxlength="500" placeholder="Optional confirmation comment">
                </div>
            </div>
            <div class="support-dialog-foot">
                <button type="button" class="btn btn-secondary btn-sm" data-dialog-close>Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm">Confirm &amp; Close</button>
            </div>
        </form>
    </dialog>
<?php endif; ?>

<?php if ($canReopen): ?>
    <dialog id="reopenTicketDialog" class="support-dialog" aria-labelledby="reopenDialogTitle">
        <form method="POST" action="<?= url('/support/' . (int)$ticket['id'] . '/reopen') ?>" data-support-form>
            <?= csrf_field() ?>
            <div class="support-dialog-head">
                <h2 id="reopenDialogTitle" style="font-size: var(--font-size-md); margin: 0;">Reopen Resolved Request</h2>
                <button type="button" class="btn btn-secondary btn-sm" data-dialog-close aria-label="Close modal"><?= icon('x', 14) ?></button>
            </div>
            <div class="support-dialog-body">
                <p class="text-sm" style="margin-bottom: var(--space-3);">
                    Reopening returns <strong><?= e((string)$ticket['ticket_ref']) ?></strong> to Under Review for further HR attention.
                </p>
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="reopenReasonInput" class="form-label">Reason for Reopening <span aria-hidden="true">*</span></label>
                    <textarea id="reopenReasonInput" name="reason" class="form-control support-form-control" rows="3" required minlength="5" maxlength="2000" data-error-id="reopen-reason-error" placeholder="Explain what remains unresolved..."></textarea>
                    <div id="reopen-reason-error" class="support-field-error" hidden></div>
                </div>
            </div>
            <div class="support-dialog-foot">
                <button type="button" class="btn btn-secondary btn-sm" data-dialog-close>Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm">Reopen Request</button>
            </div>
        </form>
    </dialog>
<?php endif; ?>
