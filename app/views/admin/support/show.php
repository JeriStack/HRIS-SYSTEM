<?php

/**
 * Admin Support Ticket Investigation & Communication View
 * Separates HR-visible replies from strictly Admin-only internal investigation notes,
 * allows Administrator assignment, priority changes, controlled workflow transitions,
 * ticket resolution, closure, and reopening.
 */

require_once __DIR__ . '/../../partials/hr_support_helpers.php';

$ticket = $ticket ?? [];
$messages = $messages ?? [];
$attachments = $attachments ?? [];
$attachmentsByMessage = $attachmentsByMessage ?? [];
$history = $history ?? [];
$assignments = $assignments ?? [];
$assignableAdmins = $assignableAdmins ?? [];
$canManage = !empty($canManage);
$canInternalNotes = !empty($canInternalNotes);
$allowedTransitions = $allowedTransitions ?? [];
$priorities = $priorities ?? ['Low', 'Medium', 'High', 'Critical'];
$maxFiles = (int)($maxFiles ?? 3);
$maxBytes = (int)($maxBytes ?? 5242880);
$maxMb = number_format($maxBytes / (1024 * 1024), 1);

$ticketRef = (string)($ticket['ticket_ref'] ?? '');
$status = (string)($ticket['status'] ?? 'Submitted');
$priority = (string)($ticket['priority'] ?? 'Medium');
$submitterName = hr_support_format_person(
    $ticket['submitter_first_name'] ?? null,
    $ticket['submitter_last_name'] ?? null,
    $ticket['submitter_username'] ?? null,
    'HR User'
);
$assignedName = hr_support_format_person(
    $ticket['assigned_first_name'] ?? null,
    $ticket['assigned_last_name'] ?? null,
    $ticket['assigned_admin_username'] ?? null,
    'Unassigned'
);
?>
<div class="page-header">
    <div class="page-title-group">
        <div class="inline-flex items-center gap-2 flex-wrap">
            <h1 class="tabular-nums"><?= e($ticketRef) ?></h1>
            <span class="badge <?= e(hr_support_status_badge($status)) ?>"><?= e($status) ?></span>
            <span class="badge <?= e(hr_support_priority_badge($priority)) ?>"><?= e($priority) ?> Priority</span>
            <?php if ((int)($ticket['reopen_count'] ?? 0) > 0): ?>
                <span class="badge badge-warning tabular-nums">Reopened &times;<?= (int)$ticket['reopen_count'] ?></span>
            <?php endif; ?>
        </div>
        <p><?= e((string)($ticket['subject'] ?? '')) ?></p>
    </div>
    <div class="page-actions">
        <a href="<?= url('/admin/support') ?>" class="btn btn-secondary btn-sm">
            <?= icon('arrow-left', 15) ?>
            <span>Back to Support Inbox</span>
        </a>
    </div>
</div>

<div class="support-detail-layout">
    <div>
        <section class="card" style="margin-bottom: var(--space-4);" aria-labelledby="adminConversationHeading">
            <div class="card-header">
                <h2 id="adminConversationHeading" class="card-title">Conversation &amp; Internal Investigation Notes</h2>
                <span class="text-xs text-muted tabular-nums"><?= count($messages) ?> entr<?= count($messages) === 1 ? 'y' : 'ies' ?></span>
            </div>
            <div class="card-body">
                <?php if (empty($messages)): ?>
                    <p class="text-sm text-muted">No conversation entries logged yet.</p>
                <?php else: ?>
                    <div class="support-thread-list">
                        <?php foreach ($messages as $m):
                            $mid = (int)($m['id'] ?? 0);
                            $isInternal = !empty($m['is_internal_note']);
                            $mType = (string)($m['message_type'] ?? 'hr_reply');
                            $isAdminSender = (string)($m['sender_role'] ?? '') === 'admin';
                            $senderName = hr_support_format_person(
                                $m['sender_first_name'] ?? null,
                                $m['sender_last_name'] ?? null,
                                $m['sender_username'] ?? null,
                                $isAdminSender ? 'Administrator' : 'HR User'
                            );

                            $cardClass = 'support-message-card';
                            if ($isInternal) {
                                $cardClass .= ' is-internal-note';
                            } elseif ($mType === 'status_change') {
                                $cardClass .= ' is-status-event';
                            } elseif ($isAdminSender) {
                                $cardClass .= ' is-hr';
                            }

                            $typeLabel = match ($mType) {
                                'initial_submission'      => 'Initial HR Report',
                                'admin_response'          => 'HR-Visible Admin Response',
                                'info_request'            => 'Info Requested (HR Visible)',
                                'internal_note'           => 'Private Admin Note (Hidden from HR)',
                                'reopen_request'          => 'HR Reopen Request',
                                'resolution_confirmation' => 'HR Confirmed Resolution',
                                'status_change'           => 'Status Update',
                                default                   => 'HR Reply',
                            };
                            $ts = !empty($m['created_at']) ? date('M j, Y g:i A', strtotime((string)$m['created_at'])) : '';
                            $msgFiles = $attachmentsByMessage[$mid] ?? [];
                        ?>
                            <article class="<?= $cardClass ?>">
                                <div class="support-message-meta">
                                    <span class="support-message-sender">
                                        <span><?= e($senderName) ?></span>
                                        <span class="badge <?= $isInternal ? 'badge-warning' : ($mType === 'info_request' ? 'badge-info' : ($isAdminSender ? 'badge-primary' : 'badge-neutral')) ?>">
                                            <?= e($typeLabel) ?>
                                        </span>
                                    </span>
                                    <time class="tabular-nums" datetime="<?= e((string)($m['created_at'] ?? '')) ?>"><?= e($ts) ?></time>
                                </div>
                                <div class="support-message-body"><?= e((string)($m['body'] ?? '')) ?></div>
                                <?php if (!empty($msgFiles)): ?>
                                    <div style="margin-top: 0.6rem; display: flex; flex-wrap: wrap; gap: 0.45rem;">
                                        <?php foreach ($msgFiles as $mf): ?>
                                            <a href="<?= url('/admin/support/attachments/' . (int)$mf['id']) ?>" class="btn btn-secondary btn-sm">
                                                <?= icon(!empty($mf['is_internal_only']) ? 'lock' : 'file-text', 13) ?>
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

        <?php if ($canManage): ?>
            <section class="card" aria-labelledby="adminRespondHeading">
                <div class="card-header">
                    <h2 id="adminRespondHeading" class="card-title">Respond to HR or Add Internal Admin Note</h2>
                </div>
                <div class="card-body">
                    <form
                        method="POST"
                        action="<?= url('/admin/support/tickets/' . rawurlencode($ticketRef) . '/respond') ?>"
                        enctype="multipart/form-data"
                        data-support-form
                        novalidate
                    >
                        <?= csrf_field() ?>
                        <div class="sr-only" role="status" aria-live="polite" data-form-live-status></div>

                        <fieldset class="support-mode-selector">
                            <legend class="form-label" style="margin-bottom: 0.45rem;">Communication Mode</legend>
                            <label class="support-mode-option">
                                <input type="radio" name="response_mode" value="reply" data-admin-mode-radio checked>
                                <span>Reply to HR (HR-Visible)</span>
                            </label>
                            <label class="support-mode-option">
                                <input type="radio" name="response_mode" value="info_request" data-admin-mode-radio>
                                <span>Request Additional Info (Waiting for HR)</span>
                            </label>
                            <?php if ($canInternalNotes): ?>
                                <label class="support-mode-option">
                                    <input type="radio" name="response_mode" value="internal_note" data-admin-mode-radio>
                                    <span>Internal Admin Note (Private)</span>
                                </label>
                            <?php endif; ?>
                        </fieldset>

                        <div id="adminResponseModeNotice" class="support-mode-cue is-public" role="status" aria-live="polite"></div>

                        <div id="adminReplyTargetStatusGroup" class="form-group">
                            <label for="adminTargetStatusSelect" class="form-label">Update Status Alongside Reply (Optional)</label>
                            <select id="adminTargetStatusSelect" name="target_status" class="form-select">
                                <option value="">Keep current status (or auto-acknowledge Submitted)</option>
                                <?php foreach ($allowedTransitions as $nextSt): ?>
                                    <option value="<?= e($nextSt) ?>"><?= e($nextSt) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="adminResponseBody" class="form-label">Message / Note Content <span aria-hidden="true">*</span></label>
                            <textarea
                                id="adminResponseBody"
                                name="body"
                                class="form-control support-form-control"
                                rows="4"
                                required
                                minlength="2"
                                maxlength="5000"
                                data-error-id="admin-body-error"
                                placeholder="Write your response to HR or technical root-cause investigation note..."
                            ></textarea>
                            <div id="admin-body-error" class="support-field-error" hidden></div>
                        </div>

                        <div class="form-group">
                            <label for="adminReplyFiles" class="form-label">Attach Supporting Files (Optional)</label>
                            <input
                                type="file"
                                id="adminReplyFiles"
                                name="attachments[]"
                                class="form-control"
                                multiple
                                accept=".pdf,.jpg,.jpeg,.png,.webp,.txt"
                                data-support-files
                                data-max-files="<?= $maxFiles ?>"
                                data-max-bytes="<?= $maxBytes ?>"
                                data-file-list-id="adminFileList"
                                data-file-status-id="adminFileStatus"
                            >
                            <p class="text-xs text-muted" style="margin-top: 0.3rem; margin-bottom: 0;">
                                Files uploaded alongside an Internal Admin Note are flagged internal-only and blocked from HR download routes. Max <?= $maxMb ?> MB each.
                            </p>
                            <ul id="adminFileList" class="support-selected-files" hidden></ul>
                            <div id="adminFileStatus" class="support-file-status" role="status" aria-live="polite" hidden></div>
                        </div>

                        <div style="display: flex; justify-content: flex-end;">
                            <button type="submit" id="adminResponseSubmitBtn" class="btn btn-primary">Send Response to HR</button>
                        </div>
                    </form>
                </div>
            </section>
        <?php endif; ?>
    </div>

    <aside style="display: flex; flex-direction: column; gap: var(--space-4);" aria-label="Ticket triage and audit controls">
        <section class="card">
            <div class="card-header">
                <h2 class="card-title">Ticket Details</h2>
            </div>
            <div class="card-body" style="display: flex; flex-direction: column; gap: 0.65rem;">
                <div>
                    <div class="text-xs text-muted">Submitted By (HR)</div>
                    <div class="text-sm" style="font-weight: 600;">
                        <?= e($submitterName) ?>
                        <?php if (!empty($ticket['submitter_employee_no'])): ?>
                            <span class="text-xs text-muted">(<?= e((string)$ticket['submitter_employee_no']) ?>)</span>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($ticket['submitter_email'])): ?>
                        <div class="text-xs text-muted" style="word-break: break-word;"><?= e((string)$ticket['submitter_email']) ?></div>
                    <?php endif; ?>
                </div>
                <div>
                    <div class="text-xs text-muted">Category</div>
                    <div class="text-sm" style="font-weight: 600;"><?= e((string)($ticket['category'] ?? '')) ?></div>
                </div>
                <div>
                    <div class="text-xs text-muted">Assigned Administrator</div>
                    <div class="text-sm" style="font-weight: 600;"><?= e($assignedName) ?></div>
                </div>
                <div>
                    <div class="text-xs text-muted">Submitted At</div>
                    <div class="text-sm tabular-nums"><?= !empty($ticket['created_at']) ? e(date('M j, Y g:i A', strtotime((string)$ticket['created_at']))) : '—' ?></div>
                </div>
                <div>
                    <div class="text-xs text-muted">Last Activity</div>
                    <div class="text-sm tabular-nums"><?= !empty($ticket['last_activity_at']) ? e(date('M j, Y g:i A', strtotime((string)$ticket['last_activity_at']))) : '—' ?></div>
                </div>
            </div>
        </section>

        <?php if ($canManage): ?>
            <section class="card" aria-labelledby="assignAdminHeading">
                <div class="card-header">
                    <h2 id="assignAdminHeading" class="card-title">Assign Administrator</h2>
                </div>
                <div class="card-body">
                    <form method="POST" action="<?= url('/admin/support/tickets/' . rawurlencode($ticketRef) . '/assign') ?>">
                        <?= csrf_field() ?>
                        <div class="form-group">
                            <label for="assignedAdminSelect" class="form-label">Administrator</label>
                            <select id="assignedAdminSelect" name="assigned_admin_id" class="form-select">
                                <option value="">-- Unassigned Queue --</option>
                                <?php foreach ($assignableAdmins as $adm):
                                    $aid = (int)$adm['user_id'];
                                    $admLabel = hr_support_format_person(
                                        $adm['first_name'] ?? null,
                                        $adm['last_name'] ?? null,
                                        $adm['username'] ?? null,
                                        'Admin #' . $aid
                                    );
                                ?>
                                    <option value="<?= $aid ?>"<?= (int)($ticket['assigned_admin_id'] ?? 0) === $aid ? ' selected' : '' ?>>
                                        <?= e($admLabel) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="assignNotesInput" class="form-label">Handoff Note (Optional)</label>
                            <input type="text" id="assignNotesInput" name="notes" class="form-control" maxlength="255" placeholder="Routing note for audit trail">
                        </div>
                        <button type="submit" class="btn btn-secondary btn-sm" style="width: 100%;">Update Assignment</button>
                    </form>
                </div>
            </section>

            <section class="card" aria-labelledby="adminTriageHeading">
                <div class="card-header">
                    <h2 id="adminTriageHeading" class="card-title">Status, Priority &amp; Resolution</h2>
                </div>
                <div class="card-body">
                    <form method="POST" action="<?= url('/admin/support/tickets/' . rawurlencode($ticketRef) . '/triage') ?>">
                        <?= csrf_field() ?>
                        <div class="form-group">
                            <label for="triagePrioritySelect" class="form-label">Priority</label>
                            <select id="triagePrioritySelect" name="priority" class="form-select">
                                <?php foreach ($priorities as $prio): ?>
                                    <option value="<?= e($prio) ?>"<?= $priority === $prio ? ' selected' : '' ?>><?= e($prio) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="triageStatusSelect" class="form-label">Ticket Status</label>
                            <select id="triageStatusSelect" name="status" class="form-select">
                                <option value="<?= e($status) ?>" selected><?= e($status) ?> (Current)</option>
                                <?php foreach ($allowedTransitions as $nextSt): ?>
                                    <option value="<?= e($nextSt) ?>"><?= e($nextSt) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="triageResolutionInput" class="form-label">Resolution Summary (HR-Visible)</label>
                            <textarea id="triageResolutionInput" name="resolution_summary" class="form-control" rows="2" maxlength="3000" placeholder="Optional fix summary shown when resolved"><?= e((string)($ticket['resolution_summary'] ?? '')) ?></textarea>
                        </div>
                        <div class="form-group">
                            <label for="triageRemarksInput" class="form-label">Public Status Note (Optional)</label>
                            <input type="text" id="triageRemarksInput" name="remarks" class="form-control" maxlength="500" placeholder="Reason for status/priority update">
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm" style="width: 100%;">Save Workflow Changes</button>
                    </form>
                </div>
            </section>
        <?php endif; ?>

        <section class="card" aria-labelledby="adminAttachmentsHeading">
            <div class="card-header">
                <h2 id="adminAttachmentsHeading" class="card-title">All Attachments</h2>
                <span class="badge badge-neutral tabular-nums"><?= count($attachments) ?></span>
            </div>
            <div class="card-body">
                <?php if (empty($attachments)): ?>
                    <p class="text-xs text-muted" style="margin-bottom: 0;">No files attached.</p>
                <?php else: ?>
                    <ul class="support-attachment-list">
                        <?php foreach ($attachments as $att):
                            $kb = max(1, (int)round(((int)$att['file_size_bytes']) / 1024));
                            $isPriv = !empty($att['is_internal_only']);
                        ?>
                            <li class="support-attachment-row">
                                <div style="min-width: 0; flex: 1;">
                                    <div class="text-xs" style="font-weight: 600; word-break: break-all;">
                                        <?= e((string)$att['original_filename']) ?>
                                        <?php if ($isPriv): ?>
                                            <span class="badge badge-warning">Internal Only</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-xs text-muted tabular-nums"><?= $kb ?> KB &middot; <?= e((string)$att['mime_type']) ?></div>
                                </div>
                                <a href="<?= url('/admin/support/attachments/' . (int)$att['id']) ?>" class="btn btn-secondary btn-sm">Download</a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </section>

        <section class="card" aria-labelledby="adminHistoryHeading">
            <div class="card-header">
                <h2 id="adminHistoryHeading" class="card-title">Audit &amp; Transition Timeline</h2>
            </div>
            <div class="card-body">
                <?php if (empty($history)): ?>
                    <p class="text-xs text-muted" style="margin-bottom: 0;">No transition history recorded.</p>
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
