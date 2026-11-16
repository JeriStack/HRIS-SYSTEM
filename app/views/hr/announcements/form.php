<?php

/**
 * HR Create / Edit Announcement Form View (Phase 3.2 & Phase 12.1)
 * Sections:
 * 1. Announcement Information
 * 2. Content & Live Preview
 * 3. Audience Targeting
 * 4. Publication Settings
 * 5. Attachments
 */

$mode = isset($mode) && $mode === 'edit' ? 'edit' : 'create';
$announcement = isset($announcement) && is_array($announcement) ? $announcement : null;
$departments = isset($departments) && is_array($departments) ? $departments : [];
$categories = isset($categories) && is_array($categories) ? $categories : [];
$priorities = isset($priorities) && is_array($priorities) ? $priorities : ['Normal', 'Important', 'Urgent'];
$old = isset($old) && is_array($old) ? $old : [];
$maxFiles = isset($maxFiles) ? (int)$maxFiles : 3;
$maxBytes = isset($maxBytes) ? (int)$maxBytes : 5 * 1024 * 1024;
$maxMb = round($maxBytes / (1024 * 1024), 1);
$canPublish = !empty($canPublish);
$canSchedule = !empty($canSchedule);

$aid = $announcement !== null ? (int)($announcement['id'] ?? 0) : 0;
$formAction = $mode === 'edit' ? url('/hr/announcements/' . $aid . '/edit') : url('/hr/announcements');

$titleVal = (string)($old['title'] ?? $announcement['title'] ?? '');
$contentVal = (string)($old['content'] ?? $announcement['content'] ?? '');
$categoryVal = (string)($old['category'] ?? $announcement['category'] ?? 'General Announcement');
$priorityVal = (string)($old['priority'] ?? $announcement['priority'] ?? 'Normal');
$isPinnedVal = array_key_exists('is_pinned', $old)
    ? !empty($old['is_pinned'])
    : !empty($announcement['is_pinned']);

$targetTypeVal = (string)($old['target_type'] ?? $announcement['target_type'] ?? 'all');
$selectedDeptIds = [];
if (isset($old['department_ids']) && is_array($old['department_ids'])) {
    $selectedDeptIds = array_map('intval', $old['department_ids']);
} elseif (isset($announcement['target_department_ids']) && is_array($announcement['target_department_ids'])) {
    $selectedDeptIds = array_map('intval', $announcement['target_department_ids']);
}

$formatDateTimeLocal = static function (?string $raw): string {
    if ($raw === null || trim($raw) === '') {
        return '';
    }
    $ts = strtotime($raw);
    return $ts !== false ? date('Y-m-d\TH:i', $ts) : '';
};

$scheduledAtVal = $formatDateTimeLocal((string)($old['scheduled_at'] ?? $announcement['scheduled_at'] ?? ''));
$expiresAtVal = $formatDateTimeLocal((string)($old['expires_at'] ?? $announcement['expires_at'] ?? ''));

$currentStatus = (string)($announcement['status'] ?? 'Draft');
$defaultPublishMode = 'draft';
if (isset($old['publish_mode'])) {
    $defaultPublishMode = (string)$old['publish_mode'];
} elseif ($currentStatus === 'Scheduled') {
    $defaultPublishMode = 'schedule';
} elseif ($currentStatus === 'Published') {
    $defaultPublishMode = 'save';
}

$existingAttachments = isset($announcement['attachments']) && is_array($announcement['attachments'])
    ? $announcement['attachments']
    : [];
?>
<style>
.ann-form-section {
    padding: 1.25rem 0;
    border-bottom: 1px solid var(--color-border);
}
.ann-form-section:first-child {
    padding-top: 0;
}
.ann-form-section:last-of-type {
    border-bottom: none;
}
.ann-form-section-title {
    font-size: 1rem;
    font-weight: 700;
    color: var(--color-text-main);
    margin: 0 0 0.25rem 0;
}
.ann-form-section-desc {
    font-size: 0.8125rem;
    color: var(--color-text-muted);
    margin: 0 0 1rem 0;
}
.ann-dept-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 0.55rem;
    margin-top: 0.75rem;
}
.ann-dept-option {
    display: flex;
    align-items: center;
    gap: 0.55rem;
    padding: 0.55rem 0.75rem;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    background: var(--color-bg-surface);
    font-size: 0.8125rem;
    cursor: pointer;
}
.ann-preview-pane {
    padding: 1.25rem;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    background: var(--color-bg-body);
    margin-top: 0.75rem;
}
dialog.ann-native-dialog {
    border: 1px solid var(--color-border);
    border-radius: var(--radius-lg);
    background: var(--color-bg-surface);
    color: var(--color-text-main);
    padding: 0;
    max-width: 28rem;
    width: calc(100% - 2rem);
    box-shadow: 0 20px 40px rgba(15, 23, 42, 0.24);
}
dialog.ann-native-dialog[open] {
    display: block;
}
dialog.ann-native-dialog:modal {
    overscroll-behavior: contain;
}
dialog.ann-native-dialog::backdrop {
    background: rgba(15, 23, 42, 0.55);
    overflow: hidden;
    overscroll-behavior: contain;
}
</style>

<div class="page-header">
    <div class="page-title-group">
        <h1><?= $mode === 'edit' ? 'Edit Announcement' : 'Create Official Announcement' ?></h1>
        <p><?= $mode === 'edit' ? 'Update announcement content, target audience, or publication schedule.' : 'Compose and target an official announcement for active employees or specific departments.' ?></p>
    </div>
    <div class="page-actions">
        <a href="<?= $mode === 'edit' ? url('/hr/announcements/' . $aid) : url('/hr/announcements') ?>" class="btn btn-secondary btn-sm">
            <?= icon('arrow-left', 15) ?>
            <span>Back to Announcements</span>
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body" style="padding: 1.5rem;">
        <form method="POST" action="<?= $formAction ?>" enctype="multipart/form-data" id="annComposeForm">
            <?= csrf_field() ?>

            <!-- Section 1: Announcement Information -->
            <div class="ann-form-section">
                <h2 class="ann-form-section-title">Announcement Information</h2>
                <p class="ann-form-section-desc">Provide a clear title, classification category, and priority level.</p>

                <div class="form-group">
                    <label class="form-label" for="annTitle">Announcement Title <span class="text-danger">*</span></label>
                    <input type="text"
                        id="annTitle"
                        name="title"
                        class="form-control"
                        required
                        minlength="3"
                        maxlength="200"
                        value="<?= e($titleVal) ?>"
                        placeholder="e.g., 2026 Annual Benefits Open Enrollment & Policy Briefing">
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem;">
                    <div class="form-group" style="margin: 0;">
                        <label class="form-label" for="annCategory">Category <span class="text-danger">*</span></label>
                        <select id="annCategory" name="category" class="form-control" required>
                            <?php foreach ($categories as $catKey => $catMeta): ?>
                                <?php $catLabel = is_array($catMeta) ? (string)($catMeta['label'] ?? $catKey) : (string)$catMeta; ?>
                                <option value="<?= e($catLabel) ?>" <?= $categoryVal === $catLabel ? 'selected' : '' ?>><?= e($catLabel) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group" style="margin: 0;">
                        <label class="form-label" for="annPriority">Priority Level <span class="text-danger">*</span></label>
                        <select id="annPriority" name="priority" class="form-control" required>
                            <?php foreach ($priorities as $prio): ?>
                                <option value="<?= e((string)$prio) ?>" <?= $priorityVal === (string)$prio ? 'selected' : '' ?>><?= e((string)$prio) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group" style="margin: 0; display: flex; align-items: end; padding-bottom: 0.45rem;">
                        <label style="display: inline-flex; align-items: center; gap: 0.5rem; font-size: 0.875rem; font-weight: 600; cursor: pointer;">
                            <input type="checkbox" name="is_pinned" id="annIsPinned" value="1" <?= $isPinnedVal ? 'checked' : '' ?>>
                            <span>Pin announcement to top of Employee Feed</span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Section 2: Content & Live Preview -->
            <div class="ann-form-section">
                <div style="display: flex; align-items: center; justify-content: space-between; gap: 0.75rem;">
                    <div>
                        <h2 class="ann-form-section-title">Content</h2>
                        <p class="ann-form-section-desc">Write the full announcement message as it will appear to employees.</p>
                    </div>
                    <button type="button" class="btn btn-secondary btn-sm" id="toggleAnnPreviewBtn">
                        <?= icon('eye', 15) ?>
                        <span>Toggle Preview</span>
                    </button>
                </div>

                <div class="form-group" style="margin: 0;">
                    <label class="form-label sr-only" for="annContent">Announcement Content</label>
                    <textarea id="annContent"
                        name="content"
                        class="form-control"
                        rows="8"
                        required
                        minlength="5"
                        maxlength="20000"
                        placeholder="Enter the complete announcement details, effective dates, and instructions for employees..."><?= e($contentVal) ?></textarea>
                </div>

                <div class="ann-preview-pane" id="annLivePreviewPane" hidden aria-live="polite">
                    <div style="display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; margin-bottom: 0.5rem;">
                        <strong id="annPreviewTitle" style="font-size: 1rem; color: var(--color-text-main);"></strong>
                        <span class="badge badge-neutral" id="annPreviewBadge">Preview</span>
                    </div>
                    <div id="annPreviewBody" style="font-size: 0.875rem; line-height: 1.65; color: var(--color-text-main); white-space: pre-line; max-width: 70ch;"></div>
                </div>
            </div>

            <!-- Section 3: Audience Targeting -->
            <div class="ann-form-section">
                <h2 class="ann-form-section-title">Audience</h2>
                <p class="ann-form-section-desc">Choose whether this announcement is visible to all active employees or restricted to selected departments.</p>

                <div style="display: flex; gap: 1.5rem; flex-wrap: wrap;">
                    <label style="display: inline-flex; align-items: center; gap: 0.45rem; font-size: 0.875rem; font-weight: 600; cursor: pointer;">
                        <input type="radio" name="target_type" value="all" <?= $targetTypeVal !== 'department' ? 'checked' : '' ?>>
                        <span>All Active Employees</span>
                    </label>
                    <label style="display: inline-flex; align-items: center; gap: 0.45rem; font-size: 0.875rem; font-weight: 600; cursor: pointer;">
                        <input type="radio" name="target_type" value="department" <?= $targetTypeVal === 'department' ? 'checked' : '' ?>>
                        <span>Selected Departments Only</span>
                    </label>
                </div>

                <div id="annDepartmentsContainer" <?= $targetTypeVal === 'department' ? '' : 'hidden' ?>>
                    <div class="ann-dept-grid" role="group" aria-label="Target departments">
                        <?php foreach ($departments as $dept): ?>
                            <?php $did = (int)$dept['id']; ?>
                            <label class="ann-dept-option">
                                <input type="checkbox"
                                    name="department_ids[]"
                                    value="<?= $did ?>"
                                    <?= in_array($did, $selectedDeptIds, true) ? 'checked' : '' ?>>
                                <span><?= e((string)$dept['department_name']) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Section 4: Publication Settings -->
            <div class="ann-form-section">
                <h2 class="ann-form-section-title">Publication Settings</h2>
                <p class="ann-form-section-desc">Configure publication mode, optional scheduled release timestamp, and optional automatic expiration.</p>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem;">
                    <?php if ($currentStatus !== 'Published'): ?>
                        <div class="form-group" style="margin: 0;">
                            <label class="form-label" for="annPublishMode">Publication Action</label>
                            <select id="annPublishMode" name="publish_mode" class="form-control">
                                <option value="draft" <?= $defaultPublishMode === 'draft' ? 'selected' : '' ?>>Save as Draft (No Notifications)</option>
                                <?php if ($canPublish): ?>
                                    <option value="publish" <?= $defaultPublishMode === 'publish' ? 'selected' : '' ?>>Publish Immediately</option>
                                <?php endif; ?>
                                <?php if ($canSchedule): ?>
                                    <option value="schedule" <?= $defaultPublishMode === 'schedule' ? 'selected' : '' ?>>Schedule for Future Date &amp; Time</option>
                                <?php endif; ?>
                            </select>
                        </div>

                        <div class="form-group" id="annScheduledAtGroup" style="margin: 0;" <?= $defaultPublishMode === 'schedule' ? '' : 'hidden' ?>>
                            <label class="form-label" for="annScheduledAt">Scheduled Publication Date &amp; Time <span class="text-danger">*</span></label>
                            <input type="datetime-local"
                                id="annScheduledAt"
                                name="scheduled_at"
                                class="form-control"
                                value="<?= e($scheduledAtVal) ?>">
                        </div>
                    <?php else: ?>
                        <input type="hidden" name="publish_mode" value="save">
                    <?php endif; ?>

                    <div class="form-group" style="margin: 0;">
                        <label class="form-label" for="annExpiresAt">Expiration Date &amp; Time (Optional)</label>
                        <input type="datetime-local"
                            id="annExpiresAt"
                            name="expires_at"
                            class="form-control"
                            value="<?= e($expiresAtVal) ?>">
                    </div>
                </div>
            </div>

            <!-- Section 5: Attachments -->
            <div class="ann-form-section">
                <h2 class="ann-form-section-title">Attachments</h2>
                <p class="ann-form-section-desc">Attach up to <?= $maxFiles ?> supporting documents or images (PDF, JPG, PNG, WEBP, or TXT, max <?= $maxMb ?> MB each).</p>

                <?php if (!empty($existingAttachments)): ?>
                    <div style="margin-bottom: 0.85rem;">
                        <div style="font-size: 0.8125rem; font-weight: 600; margin-bottom: 0.4rem;">Existing Attachments (Check to remove upon saving):</div>
                        <?php foreach ($existingAttachments as $att): ?>
                            <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.8125rem; margin-bottom: 0.3rem;">
                                <input type="checkbox" name="remove_attachment_ids[]" value="<?= (int)$att['id'] ?>">
                                <span>Remove: <strong><?= e((string)$att['original_filename']) ?></strong> (<?= number_format(((int)$att['file_size']) / 1024, 1) ?> KB)</span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="form-group" style="margin: 0;">
                    <label class="form-label" for="annAttachments">Upload Attachment(s)</label>
                    <input type="file"
                        id="annAttachments"
                        name="attachments[]"
                        class="form-control"
                        multiple
                        accept=".pdf,.jpg,.jpeg,.png,.webp,.txt,application/pdf,image/jpeg,image/png,image/webp,text/plain">
                </div>
            </div>

            <div style="display: flex; align-items: center; justify-content: flex-end; gap: 0.65rem; padding-top: 1rem;">
                <a href="<?= $mode === 'edit' ? url('/hr/announcements/' . $aid) : url('/hr/announcements') ?>" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary" id="annSubmitBtn">
                    <?= icon('check', 16) ?>
                    <span id="annSubmitBtnLabel"><?= $mode === 'edit' ? 'Save Changes' : 'Save Announcement' ?></span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Native Confirmation Dialog for Publish / Schedule -->
<dialog id="annFormConfirmDialog" class="ann-native-dialog" aria-labelledby="annFormConfirmTitle">
    <div class="modal-header">
        <h2 class="modal-title" id="annFormConfirmTitle">Confirm Publication</h2>
        <button type="button" class="modal-close" id="annFormConfirmClose" aria-label="Close dialog">
            <?= icon('x', 18) ?>
        </button>
    </div>
    <div class="modal-body">
        <p id="annFormConfirmDesc" style="font-size: 0.875rem; color: var(--color-text-main); margin: 0;"></p>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-secondary" id="annFormConfirmCancel" autofocus>Review Again</button>
        <button type="button" class="btn btn-primary" id="annFormConfirmProceed">Confirm &amp; Proceed</button>
    </div>
</dialog>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('annComposeForm');
    var targetRadios = document.querySelectorAll('input[name="target_type"]');
    var deptContainer = document.getElementById('annDepartmentsContainer');
    var publishSelect = document.getElementById('annPublishMode');
    var schedGroup = document.getElementById('annScheduledAtGroup');
    var schedInput = document.getElementById('annScheduledAt');
    var previewBtn = document.getElementById('toggleAnnPreviewBtn');
    var previewPane = document.getElementById('annLivePreviewPane');
    var previewTitle = document.getElementById('annPreviewTitle');
    var previewBody = document.getElementById('annPreviewBody');
    var previewBadge = document.getElementById('annPreviewBadge');
    var titleInput = document.getElementById('annTitle');
    var contentInput = document.getElementById('annContent');
    var catInput = document.getElementById('annCategory');
    var prioInput = document.getElementById('annPriority');
    var dialog = document.getElementById('annFormConfirmDialog');
    var dialogTitle = document.getElementById('annFormConfirmTitle');
    var dialogDesc = document.getElementById('annFormConfirmDesc');
    var cancelBtn = document.getElementById('annFormConfirmCancel');
    var closeBtn = document.getElementById('annFormConfirmClose');
    var proceedBtn = document.getElementById('annFormConfirmProceed');
    var confirmedOnce = false;

    targetRadios.forEach(function (radio) {
        radio.addEventListener('change', function () {
            if (deptContainer) {
                deptContainer.hidden = (radio.value !== 'department' || !radio.checked);
            }
        });
    });

    if (publishSelect) {
        publishSelect.addEventListener('change', function () {
            var isSched = publishSelect.value === 'schedule';
            if (schedGroup) schedGroup.hidden = !isSched;
            if (schedInput) schedInput.required = isSched;
        });
    }

    function syncPreview() {
        if (!previewPane || previewPane.hidden) return;
        if (previewTitle) previewTitle.textContent = (titleInput && titleInput.value.trim()) ? titleInput.value.trim() : 'Untitled Announcement';
        if (previewBody) previewBody.textContent = (contentInput && contentInput.value.trim()) ? contentInput.value.trim() : 'No content entered yet.';
        if (previewBadge) {
            var cat = catInput ? catInput.value : '';
            var prio = prioInput ? prio.value : '';
            previewBadge.textContent = (cat ? cat + ' · ' : '') + (prioInput ? prioInput.value : 'Normal');
        }
    }

    if (previewBtn && previewPane) {
        previewBtn.addEventListener('click', function () {
            previewPane.hidden = !previewPane.hidden;
            syncPreview();
        });
    }
    if (titleInput) titleInput.addEventListener('input', syncPreview);
    if (contentInput) contentInput.addEventListener('input', syncPreview);
    if (catInput) catInput.addEventListener('change', syncPreview);
    if (prioInput) prioInput.addEventListener('change', syncPreview);

    if (form && dialog) {
        form.addEventListener('submit', function (e) {
            if (confirmedOnce || !publishSelect) return;
            var mode = publishSelect.value;
            if (mode === 'publish' || mode === 'schedule') {
                e.preventDefault();
                if (dialogTitle) {
                    dialogTitle.textContent = mode === 'publish' ? 'Confirm Immediate Publication' : 'Confirm Scheduled Publication';
                }
                if (dialogDesc) {
                    dialogDesc.textContent = mode === 'publish'
                        ? 'Publish this announcement now? Targeted active employees will be able to read it immediately and receive an in-system notification.'
                        : 'Schedule this announcement for automatic publication at the selected date and time?';
                }
                if (typeof dialog.showModal === 'function') {
                    dialog.showModal();
                } else {
                    confirmedOnce = true;
                    form.submit();
                }
            }
        });

        if (proceedBtn) {
            proceedBtn.addEventListener('click', function () {
                confirmedOnce = true;
                if (dialog.open) dialog.close();
                form.submit();
            });
        }
        if (cancelBtn) {
            cancelBtn.addEventListener('click', function () {
                if (dialog.open) dialog.close();
            });
        }
        if (closeBtn) {
            closeBtn.addEventListener('click', function () {
                if (dialog.open) dialog.close();
            });
        }
    }
});
</script>
