<?php

/**
 * HR-to-Admin Support Ticket Detail & Conversation View
 * Displays only HR-visible messages, attachments, and transition history.
 * Private Admin internal notes are excluded on the backend and never passed here.
 */

require_once __DIR__ . '/../../partials/hr_support_helpers.php';

$ticket = $ticket ?? [];
$messages = $messages ?? [];
$attachments = $attachments ?? [];
$attachmentsByMessage = $attachmentsByMessage ?? [];
$history = $history ?? [];
$canReply = !empty($canReply);
$canConfirmResolution = !empty($canConfirmResolution);
$canReopen = !empty($canReopen);
$reopenWindowDays = (int)($reopenWindowDays ?? 14);
$reopenPolicySummary = (string)($reopenPolicySummary ?? '');
$maxFiles = (int)($maxFiles ?? 3);
$maxBytes = (int)($maxBytes ?? 5242880);
$maxMb = number_format($maxBytes / (1024 * 1024), 1);

$ticketRef = (string)($ticket['ticket_ref'] ?? '');
$status = (string)($ticket['status'] ?? 'Submitted');
$priority = (string)($ticket['priority'] ?? 'Medium');
$assignedAdmin = hr_support_format_person(
    $ticket['assigned_first_name'] ?? null,
    $ticket['assigned_last_name'] ?? null,
    $ticket['assigned_admin_username'] ?? null,
    'Pending Assignment'
);
?>
<div class="page-header">
    <div class="page-title-group">
        <div class="inline-flex items-center gap-2 flex-wrap">
            <h1 class="tabular-nums"><?= e($ticketRef) ?></h1>
            <span class="badge <?= e(hr_support_status_badge($status)) ?>"><?= e($status) ?></span>
            <span class="badge <?= e(hr_support_priority_badge($priority)) ?>"><?= e($priority) ?> Priority</span>
        </div>
        <p><?= e((string)($ticket['subject'] ?? '')) ?></p>
    </div>
    <div class="page-actions">
        <a href="<?= url('/hr/help') ?>" class="btn btn-secondary btn-sm">
            <?= icon('arrow-left', 15) ?>
            <span>My Support Tickets</span>
        </a>
        <?php if ($canConfirmResolution): ?>
            <button type="button" class="btn btn-primary btn-sm" data-dialog-open="confirmResolutionDialog">
                <?= icon('check-circle', 15) ?>
                <span>Confirm Resolution</span>
            </button>
        <?php endif; ?>
        <?php if ($canReopen): ?>
            <button type="button" class="btn btn-secondary btn-sm" data-dialog-open="reopenHrTicketDialog">
                <span>Request Reopen</span>
            </button>
        <?php endif; ?>
    </div>
</div>

<?php if ($status === 'Waiting for HR'): ?>
    <div class="alert alert-warning" role="alert">
        <span class="alert-icon" aria-hidden="true"><?= icon('alert-triangle', 18) ?></span>
        <div class="alert-content">
            <strong>Action Requested:</strong> An Administrator asked for additional details on this ticket. Post your follow-up reply below so investigation can continue.
        </div>
    </div>
<?php elseif ($status === 'Resolved'): ?>
    <div class="alert alert-success" role="status">
        <span class="alert-icon" aria-hidden="true"><?= icon('check-circle', 18) ?></span>
        <div class="alert-content">
            <strong>Ticket Marked Resolved:</strong> <?= e($reopenPolicySummary) ?>
        </div>
    </div>
<?php elseif ($status === 'Closed'): ?>
    <div class="support-privacy-notice" role="note" style="margin-bottom: var(--space-4);">
        <span class="support-privacy-notice-icon" aria-hidden="true"><?= icon('lock', 18) ?></span>
        <div class="text-sm">
            <strong>Ticket Closed:</strong> <?= e($reopenPolicySummary) ?> Submit a new ticket if a new incident occurred.
        </div>
    </div>
<?php endif; ?>

<div class="support-detail-layout">
    <div>
        <section class="card" style="margin-bottom: var(--space-4);" aria-labelledby="hrPublicThreadHeading">
            <div class="card-header">
                <h2 id="hrPublicThreadHeading" class="card-title">Ticket Conversation</h2>
                <span class="text-xs text-muted tabular-nums"><?= count($messages) ?> entr<?= count($messages) === 1 ? 'y' : 'ies' ?></span>
            </div>
            <div class="card-body">
                <?php if (empty($messages)): ?>
                    <p class="text-sm text-muted">No conversation entries recorded yet.</p>
                <?php else: ?>
                    <div class="support-thread-list">
                        <?php foreach ($messages as $m):
                            $mid = (int)($m['id'] ?? 0);
                            $mType = (string)($m['message_type'] ?? 'hr_reply');
                            $isAdminSender = (string)($m['sender_role'] ?? '') === 'admin';
                            $senderName = hr_support_format_person(
                                $m['sender_first_name'] ?? null,
                                $m['sender_last_name'] ?? null,
                                $m['sender_username'] ?? null,
                                $isAdminSender ? 'Administrator' : 'HR User'
                            );
                            $typeLabel = match ($mType) {
                                'initial_submission'      => 'Initial Report',
                                'admin_response'          => 'Administrator Response',
                                'info_request'            => 'Information Requested',
                                'reopen_request'          => 'Reopen Requested',
                                'resolution_confirmation' => 'Resolution Confirmed',
                                'status_change'           => 'Status Update',
                                default                   => 'HR Follow-Up',
                            };
                            $cardClass = 'support-message-card';
                            if ($mType === 'status_change') {
                                $cardClass .= ' is-status-event';
                            } elseif ($isAdminSender) {
                                $cardClass .= ' is-hr';
                            }
                            $ts = !empty($m['created_at']) ? date('M j, Y g:i A', strtotime((string)$m['created_at'])) : '';
                            $msgFiles = $attachmentsByMessage[$mid] ?? [];
                        ?>
                            <article class="<?= $cardClass ?>">
                                <div class="support-message-meta">
                                    <span class="support-message-sender">
                                        <span><?= e($senderName) ?></span>
                                        <span class="badge <?= $mType === 'info_request' ? 'badge-warning' : ($isAdminSender ? 'badge-primary' : 'badge-neutral') ?>">
                                            <?= e($typeLabel) ?>
                                        </span>
                                    </span>
                                    <time class="tabular-nums" datetime="<?= e((string)($m['created_at'] ?? '')) ?>"><?= e($ts) ?></time>
                                </div>
                                <div class="support-message-body"><?= e((string)($m['body'] ?? '')) ?></div>
                                <?php if (!empty($msgFiles)): ?>
                                    <div style="margin-top: 0.6rem; display: flex; flex-wrap: wrap; gap: 0.45rem;">
                                        <?php foreach ($msgFiles as $mf): ?>
                                            <a href="<?= url('/hr/help/attachments/' . (int)$mf['id']) ?>" class="btn btn-secondary btn-sm">
                                                <?= icon('file-text', 13) ?>
                                                <span><?= e((string)$mf['original_filename']) ?></span>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <?php if ($canReply): ?>
            <section class="card" aria-labelledby="hrReplyHeading">
                <div class="card-header">
                    <h2 id="hrReplyHeading" class="card-title">Send Additional Information</h2>
                </div>
                <div class="card-body">
                    <form
                        method="POST"
                        action="<?= url('/hr/help/tickets/' . rawurlencode($ticketRef) . '/reply') ?>"
                        enctype="multipart/form-data"
                        data-support-form
                        novalidate
                    >
                        <?= csrf_field() ?>
                        <div class="sr-only" role="status" aria-live="polite" data-form-live-status></div>

                        <div class="form-group">
                            <label for="hrReplyBody" class="form-label">Follow-Up Message <span aria-hidden="true">*</span></label>
                            <textarea
                                id="hrReplyBody"
                                name="body"
                                class="form-control support-form-control"
                                rows="4"
                                required
                                minlength="2"
                                maxlength="5000"
                                data-error-id="hr-reply-error"
                                placeholder="Provide additional reproduction steps, timestamps, or requested verification notes..."
                            ></textarea>
                            <div id="hr-reply-error" class="support-field-error" hidden></div>
                        </div>

                        <div class="form-group">
                            <label for="hrReplyFiles" class="form-label">Attach Supporting Files (Optional)</label>
                            <input
                                type="file"
                                id="hrReplyFiles"
                                name="attachments[]"
                                class="form-control"
                                multiple
                                accept=".pdf,.jpg,.jpeg,.png,.webp,.txt"
                                data-support-files
                                data-max-files="<?= $maxFiles ?>"
                                data-max-bytes="<?= $maxBytes ?>"
                                data-file-list-id="hrReplyFileList"
                                data-file-status-id="hrReplyFileStatus"
                            >
                            <p class="text-xs text-muted" style="margin-top: 0.3rem; margin-bottom: 0;">
                                Allowed formats: PNG, JPG, WEBP, PDF, TXT (up to <?= $maxFiles ?> files, max <?= $maxMb ?> MB each).
                            </p>
                            <ul id="hrReplyFileList" class="support-selected-files" hidden></ul>
                            <div id="hrReplyFileStatus" class="support-file-status" role="status" aria-live="polite" hidden></div>
                        </div>

                        <div style="display: flex; justify-content: flex-end;">
                            <button type="submit" class="btn btn-primary">Send Reply</button>
                        </div>
                    </form>
                </div>
            </section>
        <?php endif; ?>
    </div>

    <aside style="display: flex; flex-direction: column; gap: var(--space-4);" aria-label="Ticket metadata and history">
        <section class="card">
            <div class="card-header">
                <h2 class="card-title">Ticket Details</h2>
            </div>
            <div class="card-body" style="display: flex; flex-direction: column; gap: 0.65rem;">
                <div>
                    <div class="text-xs text-muted">Category</div>
                    <div class="text-sm" style="font-weight: 600;"><?= e((string)($ticket['category'] ?? '')) ?></div>
                </div>
                <div>
                    <div class="text-xs text-muted">Priority</div>
                    <div class="text-sm" style="font-weight: 600;"><?= e($priority) ?></div>
                </div>
                <div>
                    <div class="text-xs text-muted">Assigned Administrator</div>
                    <div class="text-sm" style="font-weight: 600;"><?= e($assignedAdmin) ?></div>
                </div>
                <div>
                    <div class="text-xs text-muted">Submitted At</div>
                    <div class="text-sm tabular-nums"><?= !empty($ticket['created_at']) ? e(date('M j, Y g:i A', strtotime((string)$ticket['created_at']))) : '—' ?></div>
                </div>
                <div>
                    <div class="text-xs text-muted">Last Activity</div>
                    <div class="text-sm tabular-nums"><?= !empty($ticket['last_activity_at']) ? e(date('M j, Y g:i A', strtotime((string)$ticket['last_activity_at']))) : '—' ?></div>
                </div>
                <?php if (!empty($ticket['resolved_at'])): ?>
                    <div>
                        <div class="text-xs text-muted">Resolved At</div>
                        <div class="text-sm tabular-nums"><?= e(date('M j, Y g:i A', strtotime((string)$ticket['resolved_at']))) ?></div>
                    </div>
                <?php endif; ?>
                <?php if (!empty($ticket['closed_at'])): ?>
                    <div>
                        <div class="text-xs text-muted">Closed At</div>
                        <div class="text-sm tabular-nums"><?= e(date('M j, Y g:i A', strtotime((string)$ticket['closed_at']))) ?></div>
                    </div>
                <?php endif; ?>
                <?php if (!empty($ticket['resolution_summary'])): ?>
                    <div style="border-top: 1px solid var(--color-border); padding-top: 0.65rem;">
                        <div class="text-xs text-muted" style="margin-bottom: 0.25rem;">Resolution Summary</div>
                        <div class="text-xs"><?= e((string)$ticket['resolution_summary']) ?></div>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="card" aria-labelledby="hrAttachmentsHeading">
            <div class="card-header">
                <h2 id="hrAttachmentsHeading" class="card-title">Attachments</h2>
                <span class="badge badge-neutral tabular-nums"><?= count($attachments) ?></span>
            </div>
            <div class="card-body">
                <?php if (empty($attachments)): ?>
                    <p class="text-xs text-muted" style="margin-bottom: 0;">No supporting attachments uploaded.</p>
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
                                    <div class="text-xs text-muted tabular-nums"><?= $kb ?> KB &middot; <?= e((string)$att['mime_type']) ?></div>
                                </div>
                                <a href="<?= url('/hr/help/attachments/' . (int)$att['id']) ?>" class="btn btn-secondary btn-sm">Download</a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </section>

        <section class="card" aria-labelledby="hrHistoryHeading">
            <div class="card-header">
                <h2 id="hrHistoryHeading" class="card-title">Status History</h2>
            </div>
            <div class="card-body">
                <?php if (empty($history)): ?>
                    <p class="text-xs text-muted" style="margin-bottom: 0;">No status transitions recorded.</p>
                <?php else: ?>
                    <ol class="support-timeline">
                        <?php foreach ($history as $h):
                            $headline = !empty($h['to_status'])
                                ? (string)$h['to_status']
                                : (!empty($h['to_priority']) ? 'Priority: ' . $h['to_priority'] : ucwords(str_replace('_', ' ', (string)($h['event_kind'] ?? 'update'))));
                        ?>
                            <li class="support-timeline-item">
                                <div style="display: flex; justify-content: space-between; gap: 0.5rem; font-size: var(--font-size-xs);">
                                    <strong><?= e($headline) ?></strong>
                                    <span class="text-muted tabular-nums"><?= !empty($h['created_at']) ? e(date('M j, Y g:i', strtotime((string)$h['created_at']))) : '' ?></span>
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

<?php if ($canConfirmResolution): ?>
    <dialog id="confirmResolutionDialog" class="support-dialog" aria-labelledby="confirmResolutionTitle">
        <form method="POST" action="<?= url('/hr/help/tickets/' . rawurlencode($ticketRef) . '/confirm-resolution') ?>">
            <?= csrf_field() ?>
            <div class="support-dialog-head">
                <h2 id="confirmResolutionTitle" style="font-size: var(--font-size-md); margin: 0;">Confirm Resolution &amp; Close Ticket</h2>
                <button type="button" class="btn btn-secondary btn-sm" data-dialog-close aria-label="Close modal"><?= icon('x', 14) ?></button>
            </div>
            <div class="support-dialog-body">
                <p class="text-sm" style="margin-bottom: var(--space-3);">
                    Confirming transitions <strong class="tabular-nums"><?= e($ticketRef) ?></strong> from Resolved to Closed. Once closed, only an Administrator can reopen it.
                </p>
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="hrConfirmNote" class="form-label">Confirmation Note (Optional)</label>
                    <input type="text" id="hrConfirmNote" name="note" class="form-control" maxlength="500" placeholder="Verified fix in production/module">
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
    <dialog id="reopenHrTicketDialog" class="support-dialog" aria-labelledby="reopenHrTicketTitle">
        <form method="POST" action="<?= url('/hr/help/tickets/' . rawurlencode($ticketRef) . '/reopen') ?>" data-support-form>
            <?= csrf_field() ?>
            <div class="support-dialog-head">
                <h2 id="reopenHrTicketTitle" style="font-size: var(--font-size-md); margin: 0;">Request Ticket Reopening</h2>
                <button type="button" class="btn btn-secondary btn-sm" data-dialog-close aria-label="Close modal"><?= icon('x', 14) ?></button>
            </div>
            <div class="support-dialog-body">
                <p class="text-sm" style="margin-bottom: var(--space-3);">
                    Reopening returns <strong class="tabular-nums"><?= e($ticketRef) ?></strong> to Under Review for further Administrator investigation.
                </p>
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="hrReopenReason" class="form-label">What remains unresolved? <span aria-hidden="true">*</span></label>
                    <textarea id="hrReopenReason" name="reason" class="form-control support-form-control" rows="3" required minlength="10" maxlength="2000" data-error-id="hr-reopen-error" placeholder="Explain what still fails or needs verification..."></textarea>
                    <div id="hr-reopen-error" class="support-field-error" hidden></div>
                </div>
            </div>
            <div class="support-dialog-foot">
                <button type="button" class="btn btn-secondary btn-sm" data-dialog-close>Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm">Reopen Ticket</button>
            </div>
        </form>
    </dialog>
<?php endif; ?>
