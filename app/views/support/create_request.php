<?php
/**
 * General Support Request Submission Form
 */
$categories = $categories ?? [];
$priorities = $priorities ?? ['Low', 'Medium', 'High', 'Urgent'];
$old = $old ?? [];
$selectedCategory = (string)($old['category'] ?? 'General Inquiry');
if (!isset($categories[$selectedCategory])) {
    $selectedCategory = 'General Inquiry';
}
$selectedPriority = (string)($old['priority'] ?? ($categories[$selectedCategory]['default_priority'] ?? 'Medium'));
$maxMb = number_format(((int)($maxBytes ?? 5242880)) / (1024 * 1024), 1);
$maxFiles = (int)($maxFiles ?? 3);
?>
<div class="page-header">
    <div class="page-title-group">
        <h1>Submit Support Request</h1>
        <p>Ask an HR question, report a technical problem, or request help with leave or profile records.</p>
    </div>
    <div class="page-actions">
        <a href="<?= url('/support') ?>" class="btn btn-secondary btn-sm">
            <?= icon('arrow-left', 15) ?>
            <span>Back to Support Center</span>
        </a>
        <a href="<?= url('/support/complaint') ?>" class="btn btn-secondary btn-sm">
            <?= icon('lock', 15) ?>
            <span>File Confidential Complaint Instead</span>
        </a>
    </div>
</div>

<div class="card" style="max-width: 820px;">
    <div class="card-header">
        <h2 class="card-title">Support Request Form</h2>
        <span class="badge badge-neutral">Server Reference Assigned Upon Submit</span>
    </div>
    <div class="card-body">
        <?php if (empty($hasLinkedEmployee)): ?>
            <div class="alert alert-warning" role="alert">
                <span class="alert-icon" aria-hidden="true"><?= icon('alert-triangle', 18) ?></span>
                <div class="alert-content">Your account must be linked to an employee profile before creating a ticket.</div>
            </div>
        <?php endif; ?>

        <form method="POST" action="<?= url('/support/request') ?>" enctype="multipart/form-data" data-support-form novalidate>
            <?= csrf_field() ?>
            <div class="sr-only" role="status" aria-live="polite" data-form-live-status></div>

            <div class="grid grid-cols-2" style="gap: var(--space-4); margin-bottom: var(--space-4);">
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="supportCategorySelect" class="form-label">Request Category <span aria-hidden="true">*</span></label>
                    <select
                        id="supportCategorySelect"
                        name="category"
                        class="form-select support-form-control"
                        data-error-id="category-error"
                        required
                    >
                        <?php foreach ($categories as $catKey => $catMeta): ?>
                            <option
                                value="<?= e((string)$catKey) ?>"
                                data-description="<?= e((string)($catMeta['description'] ?? '')) ?>"
                                data-default-priority="<?= e((string)($catMeta['default_priority'] ?? 'Medium')) ?>"
                                <?= $selectedCategory === $catKey ? 'selected' : '' ?>
                            >
                                <?= e((string)($catMeta['label'] ?? $catKey)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p id="categoryDescriptionText" class="text-xs text-muted" style="margin-top: 0.35rem; margin-bottom: 0;"></p>
                    <div id="category-error" class="support-field-error" hidden></div>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label for="supportPrioritySelect" class="form-label">Priority</label>
                    <select id="supportPrioritySelect" name="priority" class="form-select">
                        <?php foreach ($priorities as $p): ?>
                            <option value="<?= e((string)$p) ?>"<?= $selectedPriority === $p ? ' selected' : '' ?>>
                                <?= e((string)$p) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="text-xs text-muted" style="margin-top: 0.35rem; margin-bottom: 0;">
                        Submitted under your authenticated session identity.
                    </p>
                </div>
            </div>

            <div id="complaintSwitchCallout" class="support-privacy-notice" hidden>
                <span class="support-privacy-notice-icon" aria-hidden="true"><?= icon('lock', 18) ?></span>
                <div>
                    <strong>Workplace Complaint Selected:</strong> For formal workplace concerns requiring confidential complaint categorization, incident date tracking, and handler conflict-of-interest checks, we recommend using the dedicated complaint workflow.
                    <div style="margin-top: 0.5rem;">
                        <a href="<?= url('/support/complaint') ?>" class="btn btn-secondary btn-sm">Open Confidential Complaint Workflow</a>
                    </div>
                </div>
            </div>

            <!-- Category-specific optional supplementary fields -->
            <fieldset data-category-panel="Technical Issue" style="border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: var(--space-3); margin-bottom: var(--space-4);" hidden>
                <legend class="text-xs" style="font-weight: 700; padding: 0 0.4rem;">Technical Details (Optional)</legend>
                <div class="grid grid-cols-2" style="gap: var(--space-3);">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="affectedModuleInput" class="form-label">Affected Portal Page or Module</label>
                        <input type="text" id="affectedModuleInput" name="affected_module" class="form-control" maxlength="150" placeholder="e.g., Leave Filing, Profile Avatar" value="<?= e((string)($old['affected_module'] ?? '')) ?>">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="browserDeviceInput" class="form-label">Browser / Device</label>
                        <input type="text" id="browserDeviceInput" name="browser_device" class="form-control" maxlength="150" placeholder="e.g., Chrome on Windows, Mobile Safari" value="<?= e((string)($old['browser_device'] ?? '')) ?>">
                    </div>
                </div>
            </fieldset>

            <fieldset data-category-panel="Leave Concern" style="border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: var(--space-3); margin-bottom: var(--space-4);" hidden>
                <legend class="text-xs" style="font-weight: 700; padding: 0 0.4rem;">Leave Reference (Optional)</legend>
                <div class="grid grid-cols-2" style="gap: var(--space-3);">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="leaveRefInput" class="form-label">Related Leave Request #</label>
                        <input type="text" id="leaveRefInput" name="leave_ref" class="form-control" maxlength="150" placeholder="e.g., LR-104 or Vacation Leave" value="<?= e((string)($old['leave_ref'] ?? '')) ?>">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="leavePeriodInput" class="form-label">Leave Dates / Cycle Year</label>
                        <input type="text" id="leavePeriodInput" name="leave_period" class="form-control" maxlength="150" placeholder="e.g., Nov 18–20, 2026" value="<?= e((string)($old['leave_period'] ?? '')) ?>">
                    </div>
                </div>
            </fieldset>

            <fieldset data-category-panel="Employee Profile Correction" style="border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: var(--space-3); margin-bottom: var(--space-4);" hidden>
                <legend class="text-xs" style="font-weight: 700; padding: 0 0.4rem;">Profile Correction Context (Optional)</legend>
                <div class="grid grid-cols-2" style="gap: var(--space-3);">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="profileFieldInput" class="form-label">Record Field Needing Attention</label>
                        <input type="text" id="profileFieldInput" name="profile_field" class="form-control" maxlength="150" placeholder="e.g., Department, Civil Status, Contact Number" value="<?= e((string)($old['profile_field'] ?? '')) ?>">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="correctionRefInput" class="form-label">Correction Request Reference</label>
                        <input type="text" id="correctionRefInput" name="correction_ref" class="form-control" maxlength="150" placeholder="If previously requested" value="<?= e((string)($old['correction_ref'] ?? '')) ?>">
                    </div>
                </div>
            </fieldset>

            <fieldset data-category-panel="Policy Clarification" style="border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: var(--space-3); margin-bottom: var(--space-4);" hidden>
                <legend class="text-xs" style="font-weight: 700; padding: 0 0.4rem;">Policy Topic (Optional)</legend>
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="policyTopicInput" class="form-label">Handbook Section or Statutory Policy</label>
                    <input type="text" id="policyTopicInput" name="policy_topic" class="form-control" maxlength="150" placeholder="e.g., SIL Conversion, Solo Parent SPIC, Attendance" value="<?= e((string)($old['policy_topic'] ?? '')) ?>">
                </div>
            </fieldset>

            <div class="form-group">
                <label for="supportSubjectInput" class="form-label">Subject <span aria-hidden="true">*</span></label>
                <input
                    type="text"
                    id="supportSubjectInput"
                    name="subject"
                    class="form-control support-form-control"
                    required
                    minlength="5"
                    maxlength="200"
                    data-error-id="subject-error"
                    placeholder="Concise summary of your question or issue"
                    value="<?= e((string)($old['subject'] ?? '')) ?>"
                >
                <div id="subject-error" class="support-field-error" hidden></div>
            </div>

            <div class="form-group">
                <label for="supportDescriptionInput" class="form-label">Detailed Description <span aria-hidden="true">*</span></label>
                <textarea
                    id="supportDescriptionInput"
                    name="description"
                    class="form-control support-form-control"
                    rows="6"
                    required
                    minlength="15"
                    maxlength="5000"
                    data-error-id="description-error"
                    placeholder="Explain what happened or what assistance you need (at least 15 characters)..."
                ><?= e((string)($old['description'] ?? '')) ?></textarea>
                <div id="description-error" class="support-field-error" hidden></div>
            </div>

            <div class="form-group">
                <label for="supportAttachmentsInput" class="form-label">Supporting Attachments (Optional)</label>
                <input
                    type="file"
                    id="supportAttachmentsInput"
                    name="attachments[]"
                    class="form-control"
                    multiple
                    accept=".pdf,.jpg,.jpeg,.png,.webp,.txt"
                    data-support-files
                    data-max-files="<?= $maxFiles ?>"
                    data-max-bytes="<?= (int)($maxBytes ?? 5242880) ?>"
                    data-file-list-id="requestFileList"
                    data-file-status-id="requestFileStatus"
                    aria-describedby="requestAttachmentHelp"
                >
                <p id="requestAttachmentHelp" class="text-xs text-muted" style="margin-top: 0.35rem; margin-bottom: 0;">
                    Up to <?= $maxFiles ?> files (PDF, JPG, PNG, WEBP, or TXT), max <?= $maxMb ?> MB each. Stored privately outside web root.
                </p>
                <ul id="requestFileList" class="support-selected-files" hidden></ul>
                <div id="requestFileStatus" class="support-file-status" role="status" aria-live="polite" hidden></div>
            </div>

            <div class="support-form-actions" style="display: flex; flex-wrap: wrap; justify-content: flex-end; gap: var(--space-2); margin-top: var(--space-4);">
                <a href="<?= url('/support') ?>" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary"<?= empty($hasLinkedEmployee) ? ' disabled' : '' ?>>
                    <?= icon('send', 15) ?>
                    <span>Submit Support Request</span>
                </button>
            </div>
        </form>
    </div>
</div>
