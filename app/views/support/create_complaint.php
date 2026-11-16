<?php
/**
 * Confidential Workplace Complaint Submission Workflow
 */
$complaintCategories = $complaintCategories ?? [];
$priorities = $priorities ?? ['Medium', 'High', 'Urgent'];
$subjectCandidates = $subjectCandidates ?? [];
$escalationPolicySummary = (string)($escalationPolicySummary ?? '');
$old = $old ?? [];
$selectedCategory = (string)($old['complaint_category'] ?? '');
$selectedPriority = (string)($old['priority'] ?? 'High');
$selectedSubjectEmp = (string)($old['subject_employee_id'] ?? '');
$maxMb = number_format(((int)($maxBytes ?? 5242880)) / (1024 * 1024), 1);
$maxFiles = (int)($maxFiles ?? 3);
?>
<div class="page-header">
    <div class="page-title-group">
        <h1>Submit Confidential Workplace Complaint</h1>
        <p>Report grievances, conduct concerns, or policy violations through restricted-access HR handling.</p>
    </div>
    <div class="page-actions">
        <a href="<?= url('/support') ?>" class="btn btn-secondary btn-sm">
            <?= icon('arrow-left', 15) ?>
            <span>Back to Support Center</span>
        </a>
    </div>
</div>

<div style="max-width: 860px;">
    <section class="support-disclosure-box" aria-labelledby="complaintPrivacyNoticeHeading">
        <div class="inline-flex items-center gap-2" style="margin-bottom: 0.35rem;">
            <?= icon('lock', 17) ?>
            <h2 id="complaintPrivacyNoticeHeading" style="font-size: var(--font-size-md); margin: 0;">How Confidentiality &amp; Access Controls Work</h2>
        </div>
        <ul class="support-disclosure-list">
            <li><strong>Not Anonymous:</strong> This submission records your authenticated employee identity so authorized officers can follow up safely and provide resolution updates.</li>
            <li><strong>Restricted Handler Scope:</strong> Not all HR users can view complaints. Only specifically designated, authorized HR confidential officers may open complaint details and supporting attachments.</li>
            <li><strong>Conflict-of-Interest Safeguard:</strong> Anyone identified as the subject of a complaint is automatically blocked from inbox visibility, notifications, assignment, and documents for this case.</li>
            <li><strong>Escalation Policy:</strong> <?= e($escalationPolicySummary) ?></li>
        </ul>
    </section>

    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Workplace Complaint Details</h2>
            <span class="badge badge-warning">Confidential Handling Enabled</span>
        </div>
        <div class="card-body">
            <?php if (empty($hasLinkedEmployee)): ?>
                <div class="alert alert-warning" role="alert">
                    <span class="alert-icon" aria-hidden="true"><?= icon('alert-triangle', 18) ?></span>
                    <div class="alert-content">Your account must be linked to an active employee profile to file a complaint.</div>
                </div>
            <?php endif; ?>

            <form method="POST" action="<?= url('/support/complaint') ?>" enctype="multipart/form-data" data-support-form novalidate>
                <?= csrf_field() ?>
                <div class="sr-only" role="status" aria-live="polite" data-form-live-status></div>

                <div class="grid grid-cols-2" style="gap: var(--space-4); margin-bottom: var(--space-4);">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="complaintCategorySelect" class="form-label">Complaint Category <span aria-hidden="true">*</span></label>
                        <select
                            id="complaintCategorySelect"
                            name="complaint_category"
                            class="form-select support-form-control"
                            data-error-id="complaint-cat-error"
                            required
                        >
                            <option value="">-- Select Complaint Category --</option>
                            <?php foreach ($complaintCategories as $key => $label): ?>
                                <option value="<?= e((string)$key) ?>"<?= $selectedCategory === $key ? ' selected' : '' ?>>
                                    <?= e((string)$label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div id="complaint-cat-error" class="support-field-error" hidden></div>
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="complaintIncidentDate" class="form-label">Relevant Incident Date (Optional)</label>
                        <input
                            type="date"
                            id="complaintIncidentDate"
                            name="incident_date"
                            class="form-control"
                            max="<?= date('Y-m-d') ?>"
                            value="<?= e((string)($old['incident_date'] ?? '')) ?>"
                        >
                    </div>
                </div>

                <div class="grid grid-cols-2" style="gap: var(--space-4); margin-bottom: var(--space-4);">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="complaintSubjectEmp" class="form-label">Subject Employee for COI Safeguard (Optional)</label>
                        <select id="complaintSubjectEmp" name="subject_employee_id" class="form-select">
                            <option value="">-- Not Applicable / Prefer to Describe Below --</option>
                            <?php foreach ($subjectCandidates as $cand):
                                $cid = (string)$cand['id'];
                                $cLabel = trim((string)$cand['employee_no'] . ' - ' . $cand['first_name'] . ' ' . $cand['last_name']);
                                if (!empty($cand['department_name'])) {
                                    $cLabel .= ' (' . $cand['department_name'] . ')';
                                }
                            ?>
                                <option value="<?= e($cid) ?>"<?= $selectedSubjectEmp === $cid ? ' selected' : '' ?>>
                                    <?= e($cLabel) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-xs text-muted" style="margin-top: 0.35rem; margin-bottom: 0;">
                            Selecting an involved staff member ensures the system automatically blocks them from accessing or handling this complaint.
                        </p>
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="complaintSubjectRef" class="form-label">Other Party / Department Context (Optional)</label>
                        <input
                            type="text"
                            id="complaintSubjectRef"
                            name="subject_reference"
                            class="form-control"
                            maxlength="150"
                            placeholder="e.g., Shift group, external vendor, or site unit"
                            value="<?= e((string)($old['subject_reference'] ?? '')) ?>"
                        >
                    </div>
                </div>

                <div class="grid grid-cols-2" style="gap: var(--space-4); margin-bottom: var(--space-4);">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="complaintPrioritySelect" class="form-label">Severity / Priority</label>
                        <select id="complaintPrioritySelect" name="priority" class="form-select">
                            <?php foreach ($priorities as $p): ?>
                                <?php if ($p === 'Low') { continue; } ?>
                                <option value="<?= e((string)$p) ?>"<?= $selectedPriority === $p ? ' selected' : '' ?>>
                                    <?= e((string)$p) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="complaintSubjectInput" class="form-label">Subject <span aria-hidden="true">*</span></label>
                        <input
                            type="text"
                            id="complaintSubjectInput"
                            name="subject"
                            class="form-control support-form-control"
                            required
                            minlength="5"
                            maxlength="200"
                            data-error-id="complaint-subject-error"
                            placeholder="Brief summary of the workplace concern"
                            value="<?= e((string)($old['subject'] ?? '')) ?>"
                        >
                        <div id="complaint-subject-error" class="support-field-error" hidden></div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="complaintDescriptionInput" class="form-label">Detailed Complaint Description <span aria-hidden="true">*</span></label>
                    <textarea
                        id="complaintDescriptionInput"
                        name="description"
                        class="form-control support-form-control"
                        rows="6"
                        required
                        minlength="15"
                        maxlength="5000"
                        data-error-id="complaint-desc-error"
                        placeholder="Provide factual details, timeline, and context to assist the authorized HR investigation team..."
                    ><?= e((string)($old['description'] ?? '')) ?></textarea>
                    <div id="complaint-desc-error" class="support-field-error" hidden></div>
                </div>

                <div class="form-group">
                    <label for="complaintAttachmentsInput" class="form-label">Optional Supporting Documents</label>
                    <input
                        type="file"
                        id="complaintAttachmentsInput"
                        name="attachments[]"
                        class="form-control"
                        multiple
                        accept=".pdf,.jpg,.jpeg,.png,.webp,.txt"
                        data-support-files
                        data-max-files="<?= $maxFiles ?>"
                        data-max-bytes="<?= (int)($maxBytes ?? 5242880) ?>"
                        data-file-list-id="complaintFileList"
                        data-file-status-id="complaintFileStatus"
                        aria-describedby="complaintAttachmentHelp"
                    >
                    <p id="complaintAttachmentHelp" class="text-xs text-muted" style="margin-top: 0.35rem; margin-bottom: 0;">
                        Up to <?= $maxFiles ?> files (PDF, JPG, PNG, WEBP, TXT), max <?= $maxMb ?> MB each. Files are stored outside the public directory and restricted strictly to authorized complaint handlers.
                    </p>
                    <ul id="complaintFileList" class="support-selected-files" hidden></ul>
                    <div id="complaintFileStatus" class="support-file-status" role="status" aria-live="polite" hidden></div>
                </div>

                <div class="form-group" style="margin-top: var(--space-4);">
                    <label style="display: flex; align-items: flex-start; gap: 0.6rem; cursor: pointer;">
                        <input
                            type="checkbox"
                            id="complaintAckCheckbox"
                            name="identity_acknowledgment"
                            value="1"
                            required
                            data-error-id="complaint-ack-error"
                            style="margin-top: 0.25rem;"
                        >
                        <span class="text-sm">
                            I understand this confidential workplace complaint is submitted under my authenticated employee account (not anonymous) and will be handled strictly by authorized confidential HR officers or escalated oversight in accordance with system access controls.
                        </span>
                    </label>
                    <div id="complaint-ack-error" class="support-field-error" hidden></div>
                </div>

                <div class="support-form-actions" style="display: flex; flex-wrap: wrap; justify-content: flex-end; gap: var(--space-2); margin-top: var(--space-4);">
                    <a href="<?= url('/support') ?>" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary"<?= empty($hasLinkedEmployee) ? ' disabled' : '' ?>>
                        <?= icon('lock', 15) ?>
                        <span>Submit Confidential Complaint</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
