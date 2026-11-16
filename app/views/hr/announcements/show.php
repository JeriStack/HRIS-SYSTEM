<?php

/**
 * HR Announcement Details, Preview, Lifecycle Controls & Read Statistics View (Phase 3.3, 3.4, 6.2)
 * Employee HRIS & Leave Management System (NEXORA HR)
 */

$announcement = isset($announcement) && is_array($announcement) ? $announcement : [];
$aid = (int)($announcement['id'] ?? 0);
$statusStr = (string)($announcement['status'] ?? 'Draft');
$prioStr = (string)($announcement['priority'] ?? 'Normal');
$isPinned = !empty($announcement['is_pinned']);
$attachments = isset($announcement['attachments']) && is_array($announcement['attachments']) ? $announcement['attachments'] : [];

$canModify = !empty($canModify);
$canPublish = !empty($canPublish);
$canSchedule = !empty($canSchedule);
$canArchive = !empty($canArchive);
$canDelete = !empty($canDelete);
$canViewStats = !empty($canViewStats);

$readStats = isset($readStats) && is_array($readStats) ? $readStats : null;
$readReceipts = isset($readReceipts) && is_array($readReceipts) ? $readReceipts : null;
$receiptFilter = isset($receiptFilter) ? (string)$receiptFilter : 'all';

$statusBadge = match ($statusStr) {
    'Published' => 'badge-success',
    'Scheduled' => 'badge-primary',
    'Draft'     => 'badge-warning',
    default     => 'badge-neutral',
};
$prioBadge = match ($prioStr) {
    'Urgent'    => 'badge-danger',
    'Important' => 'badge-warning',
    default     => 'badge-neutral',
};
?>
<style>
.ann-detail-layout {
    display: grid;
    grid-template-columns: 1.6fr 1fr;
    gap: 1.25rem;
    align-items: start;
}
.ann-stats-strip {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    background: var(--color-bg-body);
    overflow: hidden;
    margin-bottom: 1rem;
}
.ann-stats-cell {
    padding: 0.75rem 0.9rem;
    border-right: 1px solid var(--color-border);
}
.ann-stats-cell:last-child {
    border-right: none;
}
.ann-stats-label {
    font-size: 0.75rem;
    color: var(--color-text-muted);
    font-weight: 600;
}
.ann-stats-val {
    font-size: 1.25rem;
    font-weight: 800;
    color: var(--color-text-main);
    font-variant-numeric: tabular-nums;
    margin-top: 0.15rem;
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
@media (max-width: 1024px) {
    .ann-detail-layout {
        grid-template-columns: 1fr;
    }
    .ann-stats-strip {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
</style>

<div class="page-header">
    <div class="page-title-group">
        <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 0.25rem;">
            <span class="badge <?= $statusBadge ?>"><?= e($statusStr) ?></span>
            <span class="badge <?= $prioBadge ?>"><?= e($prioStr) ?> Priority</span>
            <span class="badge badge-neutral"><?= e((string)($announcement['category'] ?? '')) ?></span>
            <?php if ($isPinned): ?>
                <span class="badge badge-primary"><?= icon('pin', 12) ?> Pinned</span>
            <?php endif; ?>
        </div>
        <h1><?= e((string)($announcement['title'] ?? 'Announcement')) ?></h1>
        <p class="tabular-nums">
            Authored by <?= e((string)($announcement['author_display_name'] ?? 'HR Officer')) ?>
            &middot; Audience: <strong><?= e((string)($announcement['audience_label'] ?? 'All Active Employees')) ?></strong>
        </p>
    </div>
    <div class="page-actions" style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
        <a href="<?= url('/hr/announcements') ?>" class="btn btn-secondary btn-sm">
            <?= icon('arrow-left', 15) ?>
            <span>All Announcements</span>
        </a>
        <?php if ($canModify && $statusStr !== 'Archived'): ?>
            <a href="<?= url('/hr/announcements/' . $aid . '/edit') ?>" class="btn btn-secondary btn-sm">
                <?= icon('edit', 15) ?>
                <span>Edit</span>
            </a>
        <?php endif; ?>
        <?php if ($canPublish && in_array($statusStr, ['Draft', 'Scheduled', 'Expired'], true)): ?>
            <button type="button"
                class="btn btn-primary btn-sm js-ann-show-confirm"
                data-action-url="<?= e(url('/hr/announcements/' . $aid . '/publish')) ?>"
                data-action-title="Publish Announcement Immediately"
                data-action-desc="Publish this announcement now? Targeted active employees will be notified immediately."
                data-action-btn="Publish Now"
                data-action-variant="primary">
                <?= icon('send', 15) ?>
                <span>Publish Now</span>
            </button>
        <?php endif; ?>
        <?php if ($canSchedule && $statusStr === 'Scheduled'): ?>
            <button type="button"
                class="btn btn-secondary btn-sm js-ann-show-confirm"
                data-action-url="<?= e(url('/hr/announcements/' . $aid . '/cancel-schedule')) ?>"
                data-action-title="Cancel Scheduled Publication"
                data-action-desc="Cancel the scheduled publication time and return this announcement to Draft?"
                data-action-btn="Revert to Draft"
                data-action-variant="primary">
                <span>Cancel Schedule</span>
            </button>
        <?php endif; ?>
        <?php if ($canArchive && $statusStr !== 'Archived'): ?>
            <button type="button"
                class="btn btn-secondary btn-sm js-ann-show-confirm"
                data-action-url="<?= e(url('/hr/announcements/' . $aid . '/archive')) ?>"
                data-action-title="Archive Announcement"
                data-action-desc="Archive this announcement? It will be removed from the active employee feed while preserving audit history."
                data-action-btn="Archive"
                data-action-variant="danger">
                <?= icon('archive', 15) ?>
                <span>Archive</span>
            </button>
        <?php endif; ?>
        <?php if ($canDelete && $statusStr === 'Draft'): ?>
            <button type="button"
                class="btn btn-danger btn-sm js-ann-show-confirm"
                data-action-url="<?= e(url('/hr/announcements/' . $aid . '/delete')) ?>"
                data-action-title="Delete Draft Announcement"
                data-action-desc="Permanently delete this unpublished draft announcement? This action cannot be undone."
                data-action-btn="Delete Draft"
                data-action-variant="danger">
                <?= icon('trash', 15) ?>
                <span>Delete Draft</span>
            </button>
        <?php endif; ?>
    </div>
</div>

<div class="ann-detail-layout">
    <!-- Left Column: Announcement Content Preview & Attachments -->
    <div>
        <article class="card" style="margin-bottom: 1.25rem;">
            <div class="card-header">
                <h2 class="card-title">Announcement Content Preview</h2>
                <span style="font-size: 0.75rem; color: var(--color-text-muted);">Employee-facing view</span>
            </div>
            <div class="card-body" style="padding: 1.5rem;">
                <div style="font-size: 1rem; line-height: 1.7; color: var(--color-text-main); white-space: pre-line; max-width: 72ch;"><?= e((string)($announcement['content'] ?? '')) ?></div>

                <?php if (!empty($attachments)): ?>
                    <div style="margin-top: 1.5rem; padding-top: 1.15rem; border-top: 1px solid var(--color-border);">
                        <h3 style="font-size: 0.875rem; font-weight: 700; color: var(--color-text-main); margin: 0 0 0.65rem 0;">
                            Attached Documents (<?= count($attachments) ?>)
                        </h3>
                        <div style="display: flex; flex-direction: column; gap: 0.45rem;">
                            <?php foreach ($attachments as $att): ?>
                                <div style="display: flex; align-items: center; justify-content: space-between; padding: 0.55rem 0.85rem; border: 1px solid var(--color-border); border-radius: var(--radius-md); background: var(--color-bg-body);">
                                    <div style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.8125rem;">
                                        <?= icon('paperclip', 15) ?>
                                        <strong><?= e((string)$att['original_filename']) ?></strong>
                                        <span class="tabular-nums" style="color: var(--color-text-muted);">
                                            (<?= number_format(((int)$att['file_size']) / 1024, 1) ?> KB)
                                        </span>
                                    </div>
                                    <a href="<?= url('/hr/announcements/' . $aid . '/attachments/' . (int)$att['id']) ?>" class="btn btn-secondary btn-sm">
                                        Download
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </article>

        <!-- Read Receipts Table (Phase 6.2) -->
        <?php if ($canViewStats && $readStats !== null && $readReceipts !== null): ?>
            <section class="card" aria-labelledby="annReadStatsHeading">
                <div class="card-header" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem;">
                    <div>
                        <h2 class="card-title" id="annReadStatsHeading">Audience Engagement &amp; Read Receipts</h2>
                        <p class="card-subtitle" style="font-size: 0.75rem; color: var(--color-text-muted); margin: 0.15rem 0 0 0;">
                            Only employees included in the announcement's target audience are listed.
                        </p>
                    </div>
                    <div style="display: flex; gap: 0.35rem;">
                        <a href="<?= url('/hr/announcements/' . $aid . '?read_filter=all') ?>" class="btn <?= $receiptFilter === 'all' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">All (<?= (int)$readStats['total_targeted'] ?>)</a>
                        <a href="<?= url('/hr/announcements/' . $aid . '?read_filter=read') ?>" class="btn <?= $receiptFilter === 'read' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">Read (<?= (int)$readStats['total_read'] ?>)</a>
                        <a href="<?= url('/hr/announcements/' . $aid . '?read_filter=unread') ?>" class="btn <?= $receiptFilter === 'unread' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">Unread (<?= (int)$readStats['total_unread'] ?>)</a>
                    </div>
                </div>
                <div class="card-body" style="padding: 1.25rem;">
                    <div class="ann-stats-strip">
                        <div class="ann-stats-cell">
                            <div class="ann-stats-label">Targeted Employees</div>
                            <div class="ann-stats-val"><?= (int)$readStats['total_targeted'] ?></div>
                        </div>
                        <div class="ann-stats-cell">
                            <div class="ann-stats-label">Read</div>
                            <div class="ann-stats-val" style="color: var(--color-success);"><?= (int)$readStats['total_read'] ?></div>
                        </div>
                        <div class="ann-stats-cell">
                            <div class="ann-stats-label">Unread</div>
                            <div class="ann-stats-val" style="color: var(--color-warning);"><?= (int)$readStats['total_unread'] ?></div>
                        </div>
                        <div class="ann-stats-cell">
                            <div class="ann-stats-label">Read Rate</div>
                            <div class="ann-stats-val"><?= number_format((float)$readStats['read_percentage'], 1) ?>%</div>
                        </div>
                    </div>

                    <?php if (empty($readReceipts['items'])): ?>
                        <p style="font-size: 0.875rem; color: var(--color-text-muted); text-align: center; padding: 1.5rem 0; margin: 0;">
                            No targeted employee records match the selected read filter.
                        </p>
                    <?php else: ?>
                        <div tabindex="0" role="region" aria-labelledby="annReadStatsHeading" class="table-responsive">
                            <table class="table table-hover">
                                <caption class="sr-only">Targeted Employee Read Receipts</caption>
                                <thead>
                                    <tr>
                                        <th scope="col">Employee</th>
                                        <th scope="col">Department</th>
                                        <th scope="col">Status</th>
                                        <th scope="col">First Read Timestamp</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($readReceipts['items'] as $rec): ?>
                                        <tr>
                                            <th scope="row" style="font-weight: 600;">
                                                <span style="font-family: var(--font-mono); font-size: 0.75rem; color: var(--color-primary);">
                                                    <?= e((string)$rec['employee_no']) ?>
                                                </span>
                                                &middot; <?= e((string)$rec['full_name']) ?>
                                            </th>
                                            <td style="font-size: 0.8125rem;"><?= e((string)$rec['department_name']) ?></td>
                                            <td>
                                                <?php if (!empty($rec['is_read'])): ?>
                                                    <span class="badge badge-success">Read</span>
                                                <?php else: ?>
                                                    <span class="badge badge-neutral">Unread</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="tabular-nums" style="font-size: 0.8125rem; color: var(--color-text-muted);">
                                                <?= !empty($rec['read_at']) ? e(date('M j, Y g:i A', strtotime((string)$rec['read_at']))) : '—' ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        <?php endif; ?>
    </div>

    <!-- Right Column: Lifecycle & Schedule Metadata -->
    <div>
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Publication &amp; Lifecycle Details</h2>
            </div>
            <div class="card-body" style="padding: 1.25rem; font-size: 0.875rem;">
                <dl style="margin: 0; display: grid; grid-template-columns: 1fr; gap: 0.75rem;">
                    <div>
                        <dt style="color: var(--color-text-muted); font-size: 0.75rem; font-weight: 600;">Lifecycle Status</dt>
                        <dd style="margin: 0.2rem 0 0 0; font-weight: 700;"><?= e($statusStr) ?></dd>
                    </div>
                    <div>
                        <dt style="color: var(--color-text-muted); font-size: 0.75rem; font-weight: 600;">Target Audience</dt>
                        <dd style="margin: 0.2rem 0 0 0; font-weight: 600;"><?= e((string)($announcement['audience_label'] ?? 'All Active Employees')) ?></dd>
                    </div>
                    <div>
                        <dt style="color: var(--color-text-muted); font-size: 0.75rem; font-weight: 600;">Published Timestamp</dt>
                        <dd class="tabular-nums" style="margin: 0.2rem 0 0 0;">
                            <?= !empty($announcement['published_at']) ? e(date('M j, Y g:i A', strtotime((string)$announcement['published_at']))) : 'Not published yet' ?>
                        </dd>
                    </div>
                    <div>
                        <dt style="color: var(--color-text-muted); font-size: 0.75rem; font-weight: 600;">Scheduled Timestamp</dt>
                        <dd class="tabular-nums" style="margin: 0.2rem 0 0 0;">
                            <?= !empty($announcement['scheduled_at']) ? e(date('M j, Y g:i A', strtotime((string)$announcement['scheduled_at']))) : 'None' ?>
                        </dd>
                    </div>
                    <div>
                        <dt style="color: var(--color-text-muted); font-size: 0.75rem; font-weight: 600;">Expiration Timestamp</dt>
                        <dd class="tabular-nums" style="margin: 0.2rem 0 0 0;">
                            <?= !empty($announcement['expires_at']) ? e(date('M j, Y g:i A', strtotime((string)$announcement['expires_at']))) : 'Does not expire' ?>
                        </dd>
                    </div>
                    <div>
                        <dt style="color: var(--color-text-muted); font-size: 0.75rem; font-weight: 600;">Created At</dt>
                        <dd class="tabular-nums" style="margin: 0.2rem 0 0 0;">
                            <?= !empty($announcement['created_at']) ? e(date('M j, Y g:i A', strtotime((string)$announcement['created_at']))) : '—' ?>
                        </dd>
                    </div>
                </dl>
            </div>
        </div>
    </div>
</div>

<!-- Native Confirmation Dialog -->
<dialog id="annShowActionDialog" class="ann-native-dialog" aria-labelledby="annShowDialogTitle">
    <div class="modal-header">
        <h2 class="modal-title" id="annShowDialogTitle">Confirm Action</h2>
        <button type="button" class="modal-close" id="annShowDialogClose" aria-label="Close dialog">
            <?= icon('x', 18) ?>
        </button>
    </div>
    <div class="modal-body">
        <p id="annShowDialogDesc" style="font-size: 0.875rem; color: var(--color-text-main); margin: 0;"></p>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-secondary" id="annShowDialogCancel" autofocus>Cancel</button>
        <form method="POST" action="" id="annShowDialogForm" style="margin: 0;">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-primary" id="annShowDialogSubmit">Confirm</button>
        </form>
    </div>
</dialog>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var dialog = document.getElementById('annShowActionDialog');
    var form = document.getElementById('annShowDialogForm');
    var titleEl = document.getElementById('annShowDialogTitle');
    var descEl = document.getElementById('annShowDialogDesc');
    var submitBtn = document.getElementById('annShowDialogSubmit');
    var cancelBtn = document.getElementById('annShowDialogCancel');
    var closeBtn = document.getElementById('annShowDialogClose');

    function closeDialog() {
        if (dialog && dialog.open) dialog.close();
    }

    document.querySelectorAll('.js-ann-show-confirm').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (!dialog || !form) return;
            form.action = btn.getAttribute('data-action-url') || '';
            if (titleEl) titleEl.textContent = btn.getAttribute('data-action-title') || 'Confirm Action';
            if (descEl) descEl.textContent = btn.getAttribute('data-action-desc') || '';
            if (submitBtn) {
                submitBtn.textContent = btn.getAttribute('data-action-btn') || 'Confirm';
                var variant = btn.getAttribute('data-action-variant') || 'primary';
                submitBtn.className = 'btn ' + (variant === 'danger' ? 'btn-danger' : 'btn-primary');
            }
            if (typeof dialog.showModal === 'function') {
                dialog.showModal();
            }
        });
    });

    if (cancelBtn) cancelBtn.addEventListener('click', closeDialog);
    if (closeBtn) closeBtn.addEventListener('click', closeDialog);
});
</script>
