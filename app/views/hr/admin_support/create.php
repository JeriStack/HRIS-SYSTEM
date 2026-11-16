<?php

/**
 * Submit HR-to-Admin Support Ticket View
 * Structured technical/operational reporting form with inline accessible validation
 * and native pre-submission confirmation dialog.
 */

$categories = $categories ?? [];
$priorities = $priorities ?? ['Low', 'Medium', 'High', 'Critical'];
$maxFiles = (int)($maxFiles ?? 3);
$maxBytes = (int)($maxBytes ?? 5242880);
$maxMb = number_format($maxBytes / (1024 * 1024), 1);
$old = $old ?? [];

$selectedCategory = (string)($old['category'] ?? '');
$selectedPriority = (string)($old['priority'] ?? 'Medium');
?>
<div class="page-header">
    <div class="page-title-group">
        <h1>Submit Support Ticket to Admin</h1>
        <p>Report system errors, access issues, leave/employee module defects, or data discrepancies for Administrator review.</p>
    </div>
    <div class="page-actions">
        <a href="<?= url('/hr/help') ?>" class="btn btn-secondary btn-sm">
            <?= icon('arrow-left', 15) ?>
            <span>My Support Tickets</span>
        </a>
    </div>
</div>

<section class="card" aria-labelledby="submitHrTicketHeading" style="max-width: 920px;">
    <div class="card-header">
        <div>
            <h2 id="submitHrTicketHeading" class="card-title">Technical &amp; Operational Issue Report</h2>
            <span class="card-subtitle">Each ticket receives a unique tracking reference (HR-SUP-<?= e(date('Y')) ?>-XXXXXXXX)</span>
        </div>
        <span class="badge badge-info">HR &rarr; Admin Channel</span>
    </div>
    <div class="card-body">
        <div class="support-privacy-notice" role="note" style="margin-bottom: var(--space-5);">
            <span class="support-privacy-notice-icon" aria-hidden="true"><?= icon('lock', 18) ?></span>
            <div class="text-sm">
                <strong>Data Minimization Notice:</strong> Avoid pasting employee passwords, OTP codes, or sensitive medical/confidential complaint narratives unless strictly needed to reproduce a system error.
            </div>
        </div>

        <form
            id="createHrSupportForm"
            method="POST"
            action="<?= url('/hr/help/tickets') ?>"
            enctype="multipart/form-data"
            data-support-form
            data-confirm-dialog="confirmCreateTicketDialog"
            novalidate
        >
            <?= csrf_field() ?>
            <div class="sr-only" role="status" aria-live="polite" data-form-live-status></div>

            <div class="form-group">
                <label for="hrTicketSubject" class="form-label">Subject <span aria-hidden="true">*</span></label>
                <input
                    type="text"
                    id="hrTicketSubject"
                    name="subject"
                    class="form-control support-form-control"
                    required
                    minlength="5"
                    maxlength="200"
                    value="<?= e((string)($old['subject'] ?? '')) ?>"
                    data-error-id="hr-subject-error"
                    placeholder="Concise summary of the technical error or operational concern"
                >
                <div id="hr-subject-error" class="support-field-error" hidden></div>
            </div>

            <div class="grid grid-cols-2" style="gap: var(--space-4);">
                <div class="form-group">
                    <label for="supportCategorySelect" class="form-label">Issue Category <span aria-hidden="true">*</span></label>
                    <select
                        id="supportCategorySelect"
                        name="category"
                        class="form-select support-form-control"
                        required
                        data-error-id="hr-category-error"
                    >
                        <option value="">-- Select Issue Category --</option>
                        <?php foreach ($categories as $catKey => $catMeta):
                            $desc = (string)($catMeta['description'] ?? '');
                            $defPrio = (string)($catMeta['default_priority'] ?? 'Medium');
                        ?>
                            <option
                                value="<?= e($catKey) ?>"
                                data-description="<?= e($desc) ?>"
                                data-default-priority="<?= e($defPrio) ?>"
                                <?= $selectedCategory === $catKey ? ' selected' : '' ?>
                            >
                                <?= e((string)($catMeta['label'] ?? $catKey)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p id="categoryDescriptionText" class="text-xs text-muted" style="margin-top: 0.35rem; margin-bottom: 0;" hidden></p>
                    <div id="hr-category-error" class="support-field-error" hidden></div>
                </div>

                <div class="form-group">
                    <label for="supportPrioritySelect" class="form-label">Priority Level <span aria-hidden="true">*</span></label>
                    <select
                        id="supportPrioritySelect"
                        name="priority"
                        class="form-select support-form-control"
                        required
                        data-error-id="hr-priority-error"
                    >
                        <?php foreach ($priorities as $prio): ?>
                            <option value="<?= e($prio) ?>"<?= $selectedPriority === $prio ? ' selected' : '' ?>><?= e($prio) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="text-xs text-muted" style="margin-top: 0.35rem; margin-bottom: 0;">
                        Use <strong>Critical</strong> only for system-wide outages or blocking payroll/leave workflows.
                    </p>
                    <div id="hr-priority-error" class="support-field-error" hidden></div>
                </div>
            </div>

            <div class="form-group">
                <label for="hrTicketDescription" class="form-label">Detailed Description <span aria-hidden="true">*</span></label>
                <textarea
                    id="hrTicketDescription"
                    name="description"
                    class="form-control support-form-control"
                    rows="6"
                    required
                    minlength="15"
                    maxlength="5000"
                    data-error-id="hr-desc-error"
                    placeholder="Describe the steps taken, affected module or URL, expected behavior, and exact error banner or timestamp..."
                ><?= e((string)($old['description'] ?? '')) ?></textarea>
                <div id="hr-desc-error" class="support-field-error" hidden></div>
            </div>

            <div class="form-group">
                <label for="hrCreateAttachments" class="form-label">Supporting Screenshots or Logs (Optional)</label>
                <input
                    type="file"
                    id="hrCreateAttachments"
                    name="attachments[]"
                    class="form-control"
                    multiple
                    accept=".pdf,.jpg,.jpeg,.png,.webp,.txt"
                    data-support-files
                    data-max-files="<?= $maxFiles ?>"
                    data-max-bytes="<?= $maxBytes ?>"
                    data-file-list-id="hrCreateFileList"
                    data-file-status-id="hrCreateFileStatus"
                >
                <p class="text-xs text-muted" style="margin-top: 0.35rem; margin-bottom: 0;">
                    Allowed formats: PNG, JPG, WEBP, PDF, TXT (up to <?= $maxFiles ?> files, max <?= $maxMb ?> MB each). Stored outside public web roots.
                </p>
                <ul id="hrCreateFileList" class="support-selected-files" hidden></ul>
                <div id="hrCreateFileStatus" class="support-file-status" role="status" aria-live="polite" hidden></div>
            </div>

            <div class="support-form-actions" style="display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: var(--space-5);">
                <a href="<?= url('/hr/help') ?>" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">Review &amp; Submit Ticket</button>
            </div>
        </form>
    </div>
</section>

<dialog id="confirmCreateTicketDialog" class="support-dialog" aria-labelledby="confirmCreateTicketTitle">
    <div class="support-dialog-head">
        <h2 id="confirmCreateTicketTitle" style="font-size: var(--font-size-md); margin: 0;">Confirm Support Ticket Submission</h2>
        <button type="button" class="btn btn-secondary btn-sm" data-dialog-close aria-label="Close confirmation dialog"><?= icon('x', 14) ?></button>
    </div>
    <div class="support-dialog-body">
        <p class="text-sm" style="margin-bottom: var(--space-3);">
            Please verify the summary below before routing this ticket to System Administrators:
        </p>
        <dl style="display: grid; grid-template-columns: 110px 1fr; gap: 0.45rem 0.75rem; font-size: var(--font-size-xs); margin: 0;">
            <dt class="text-muted">Subject</dt>
            <dd id="confirmTicketSubject" style="margin: 0; font-weight: 600;"></dd>
            <dt class="text-muted">Category</dt>
            <dd id="confirmTicketCategory" style="margin: 0;"></dd>
            <dt class="text-muted">Priority</dt>
            <dd id="confirmTicketPriority" style="margin: 0; font-weight: 600;"></dd>
            <dt class="text-muted">Attachments</dt>
            <dd id="confirmTicketFiles" style="margin: 0;"></dd>
        </dl>
    </div>
    <div class="support-dialog-foot">
        <button type="button" class="btn btn-secondary btn-sm" data-dialog-close>Edit Ticket</button>
        <button type="button" class="btn btn-primary btn-sm" data-confirm-submit-action>Confirm &amp; Submit</button>
    </div>
</dialog>
