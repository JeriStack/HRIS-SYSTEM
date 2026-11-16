<?php

/**
 * HR Announcements Management Index View (Phase 3.1 & Phase 12.1)
 * Employee HRIS & Leave Management System (NEXORA HR)
 */

$announcements = isset($announcements) && is_array($announcements) ? $announcements : [];
$stats = isset($stats) && is_array($stats) ? $stats : [
    'total' => 0, 'draft' => 0, 'scheduled' => 0, 'published' => 0, 'archived' => 0, 'expired' => 0,
];
$filters = isset($filters) && is_array($filters) ? $filters : [
    'search' => '', 'category' => '', 'priority' => '', 'status' => '', 'date_from' => '', 'date_to' => '', 'sort' => 'newest',
];
$pagination = isset($pagination) && is_array($pagination) ? $pagination : [
    'total' => 0, 'page' => 1, 'per_page' => 10, 'total_pages' => 1,
];
$categories = isset($categories) && is_array($categories) ? $categories : [];
$priorities = isset($priorities) && is_array($priorities) ? $priorities : ['Normal', 'Important', 'Urgent'];
$statuses = isset($statuses) && is_array($statuses) ? $statuses : ['Draft', 'Scheduled', 'Published', 'Archived', 'Expired'];
$canCreate = !empty($canCreate);
$canPublish = !empty($canPublish);
$canSchedule = !empty($canSchedule);
$canArchive = !empty($canArchive);
$canDelete = !empty($canDelete);

$annStatusBadge = static function (string $status): string {
    return match ($status) {
        'Published' => 'badge-success',
        'Scheduled' => 'badge-primary',
        'Draft'     => 'badge-warning',
        'Expired'   => 'badge-neutral',
        'Archived'  => 'badge-neutral',
        default     => 'badge-neutral',
    };
};

$annPriorityBadge = static function (string $priority): string {
    return match ($priority) {
        'Urgent'    => 'badge-danger',
        'Important' => 'badge-warning',
        default     => 'badge-neutral',
    };
};

$buildPageUrl = static function (int $targetPage) use ($filters): string {
    $q = array_filter([
        'search'    => $filters['search'] ?? '',
        'category'  => $filters['category'] ?? '',
        'priority'  => $filters['priority'] ?? '',
        'status'    => $filters['status'] ?? '',
        'date_from' => $filters['date_from'] ?? '',
        'date_to'   => $filters['date_to'] ?? '',
        'sort'      => ($filters['sort'] ?? 'newest') !== 'newest' ? $filters['sort'] : '',
        'page'      => $targetPage > 1 ? $targetPage : null,
    ], static fn ($v): bool => $v !== null && $v !== '');

    return url('/hr/announcements') . (!empty($q) ? '?' . http_build_query($q) : '');
};
?>
<style>
.ann-ledger-strip {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    background: var(--color-bg-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-lg);
    overflow: hidden;
    margin-bottom: 1.25rem;
}
.ann-ledger-cell {
    padding: 0.95rem 1.15rem;
    border-right: 1px solid var(--color-border);
    text-decoration: none;
    color: inherit;
    transition: background-color 0.15s ease;
}
.ann-ledger-cell:last-child {
    border-right: none;
}
.ann-ledger-cell:hover {
    background: var(--color-bg-hover);
}
.ann-ledger-label {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--color-text-muted);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
}
.ann-ledger-val {
    font-size: 1.5rem;
    font-weight: 800;
    color: var(--color-text-main);
    font-variant-numeric: tabular-nums;
    margin-top: 0.25rem;
    line-height: 1.2;
}
.ann-filter-grid {
    display: grid;
    grid-template-columns: 1.8fr 1.1fr 0.9fr 0.9fr 0.9fr 0.9fr 0.9fr auto;
    gap: 0.65rem;
    align-items: end;
}
.ann-read-meter {
    width: 100%;
    max-width: 120px;
    height: 6px;
    background: var(--color-border);
    border-radius: 999px;
    overflow: hidden;
    margin-top: 0.3rem;
}
.ann-read-meter-bar {
    height: 100%;
    background: var(--color-primary);
    border-radius: 999px;
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
@media (max-width: 1180px) {
    .ann-filter-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}
@media (max-width: 768px) {
    .ann-ledger-strip {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .ann-filter-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="page-header">
    <div class="page-title-group">
        <h1>Announcements Management</h1>
        <p>Create, schedule, publish, and track official company announcements and departmental advisories.</p>
    </div>
    <div class="page-actions">
        <?php if ($canCreate): ?>
            <a href="<?= url('/hr/announcements/create') ?>" class="btn btn-primary" id="createAnnouncementBtn">
                <?= icon('megaphone', 16) ?>
                <span>Create Announcement</span>
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- Lifecycle Summary Strip -->
<section class="ann-ledger-strip" aria-label="Announcement lifecycle summary">
    <a href="<?= url('/hr/announcements') ?>" class="ann-ledger-cell">
        <div class="ann-ledger-label">
            <span>All Announcements</span>
            <?= icon('megaphone', 15) ?>
        </div>
        <div class="ann-ledger-val"><?= (int)($stats['total'] ?? 0) ?></div>
    </a>
    <a href="<?= url('/hr/announcements?status=Published') ?>" class="ann-ledger-cell">
        <div class="ann-ledger-label">
            <span>Published (Active)</span>
            <?= icon('check-circle', 15) ?>
        </div>
        <div class="ann-ledger-val" style="color: var(--color-success);"><?= (int)($stats['published'] ?? 0) ?></div>
    </a>
    <a href="<?= url('/hr/announcements?status=Scheduled') ?>" class="ann-ledger-cell">
        <div class="ann-ledger-label">
            <span>Scheduled</span>
            <?= icon('clock', 15) ?>
        </div>
        <div class="ann-ledger-val" style="color: var(--color-primary);"><?= (int)($stats['scheduled'] ?? 0) ?></div>
    </a>
    <a href="<?= url('/hr/announcements?status=Draft') ?>" class="ann-ledger-cell">
        <div class="ann-ledger-label">
            <span>Drafts</span>
            <?= icon('file-text', 15) ?>
        </div>
        <div class="ann-ledger-val" style="color: var(--color-warning);"><?= (int)($stats['draft'] ?? 0) ?></div>
    </a>
    <a href="<?= url('/hr/announcements?status=Archived') ?>" class="ann-ledger-cell">
        <div class="ann-ledger-label">
            <span>Expired / Archived</span>
            <?= icon('archive', 15) ?>
        </div>
        <div class="ann-ledger-val"><?= (int)(($stats['expired'] ?? 0) + ($stats['archived'] ?? 0)) ?></div>
    </a>
</section>

<!-- Filter & Search Bar -->
<div class="card" style="margin-bottom: 1.25rem;">
    <div class="card-body" style="padding: 1rem 1.25rem;">
        <form method="GET" action="<?= url('/hr/announcements') ?>" class="ann-filter-grid" aria-label="Filter announcements">
            <div class="form-group" style="margin: 0;">
                <label class="form-label" for="annFilterSearch" style="font-size: 0.75rem;">Search</label>
                <input type="search" id="annFilterSearch" name="search" class="form-control" value="<?= e($filters['search'] ?? '') ?>" placeholder="Title, content, or category...">
            </div>
            <div class="form-group" style="margin: 0;">
                <label class="form-label" for="annFilterCategory" style="font-size: 0.75rem;">Category</label>
                <select id="annFilterCategory" name="category" class="form-control">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $catKey => $catMeta): ?>
                        <?php $catVal = is_array($catMeta) ? (string)($catMeta['label'] ?? $catKey) : (string)$catMeta; ?>
                        <option value="<?= e($catVal) ?>" <?= ($filters['category'] ?? '') === $catVal ? 'selected' : '' ?>><?= e($catVal) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin: 0;">
                <label class="form-label" for="annFilterPriority" style="font-size: 0.75rem;">Priority</label>
                <select id="annFilterPriority" name="priority" class="form-control">
                    <option value="">All Priorities</option>
                    <?php foreach ($priorities as $prio): ?>
                        <option value="<?= e((string)$prio) ?>" <?= ($filters['priority'] ?? '') === (string)$prio ? 'selected' : '' ?>><?= e((string)$prio) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin: 0;">
                <label class="form-label" for="annFilterStatus" style="font-size: 0.75rem;">Status</label>
                <select id="annFilterStatus" name="status" class="form-control">
                    <option value="">All Statuses</option>
                    <?php foreach ($statuses as $st): ?>
                        <option value="<?= e((string)$st) ?>" <?= ($filters['status'] ?? '') === (string)$st ? 'selected' : '' ?>><?= e((string)$st) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin: 0;">
                <label class="form-label" for="annFilterFrom" style="font-size: 0.75rem;">From Date</label>
                <input type="date" id="annFilterFrom" name="date_from" class="form-control" value="<?= e($filters['date_from'] ?? '') ?>">
            </div>
            <div class="form-group" style="margin: 0;">
                <label class="form-label" for="annFilterTo" style="font-size: 0.75rem;">To Date</label>
                <input type="date" id="annFilterTo" name="date_to" class="form-control" value="<?= e($filters['date_to'] ?? '') ?>">
            </div>
            <div class="form-group" style="margin: 0;">
                <label class="form-label" for="annFilterSort" style="font-size: 0.75rem;">Sort By</label>
                <select id="annFilterSort" name="sort" class="form-control">
                    <option value="newest" <?= ($filters['sort'] ?? 'newest') === 'newest' ? 'selected' : '' ?>>Newest First</option>
                    <option value="published_at" <?= ($filters['sort'] ?? '') === 'published_at' ? 'selected' : '' ?>>Publication Date</option>
                    <option value="priority" <?= ($filters['sort'] ?? '') === 'priority' ? 'selected' : '' ?>>Priority</option>
                    <option value="title" <?= ($filters['sort'] ?? '') === 'title' ? 'selected' : '' ?>>Title (A–Z)</option>
                    <option value="oldest" <?= ($filters['sort'] ?? '') === 'oldest' ? 'selected' : '' ?>>Oldest First</option>
                </select>
            </div>
            <div style="display: flex; gap: 0.45rem;">
                <button type="submit" class="btn btn-primary btn-sm">Apply</button>
                <a href="<?= url('/hr/announcements') ?>" class="btn btn-secondary btn-sm">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Announcements Table -->
<div class="card">
    <div class="card-header" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem;">
        <h2 class="card-title" id="hrAnnouncementsTableHeading">Company Announcements Registry</h2>
        <span class="badge badge-neutral tabular-nums">
            Showing <?= count($announcements) ?> of <?= (int)($pagination['total'] ?? 0) ?> record(s)
        </span>
    </div>

    <?php if (empty($announcements)): ?>
        <div class="card-body" style="padding: 3rem 1.5rem; text-align: center;">
            <div style="display: inline-flex; width: 48px; height: 48px; border-radius: 999px; align-items: center; justify-content: center; background: var(--color-bg-hover); color: var(--color-primary); margin-bottom: 0.75rem;">
                <?= icon('megaphone', 22) ?>
            </div>
            <h3 style="font-size: 1rem; font-weight: 700; color: var(--color-text-main); margin-bottom: 0.35rem;">No Announcements Found</h3>
            <p style="font-size: 0.875rem; color: var(--color-text-muted); max-width: 34rem; margin: 0 auto 1.25rem;">
                No announcements match your active filters, or no organizational announcements have been created yet.
            </p>
            <?php if ($canCreate): ?>
                <a href="<?= url('/hr/announcements/create') ?>" class="btn btn-primary btn-sm">
                    <?= icon('plus', 15) ?>
                    <span>Create First Announcement</span>
                </a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div tabindex="0" role="region" aria-labelledby="hrAnnouncementsTableHeading" class="table-responsive">
            <table class="table table-hover" aria-describedby="hrAnnouncementsTableHeading">
                <caption class="sr-only">HR Announcements Management List</caption>
                <thead>
                    <tr>
                        <th scope="col">Title &amp; Category</th>
                        <th scope="col">Priority</th>
                        <th scope="col">Audience</th>
                        <th scope="col">Author</th>
                        <th scope="col">Publication / Schedule</th>
                        <th scope="col">Expiration</th>
                        <th scope="col">Status</th>
                        <th scope="col">Read Statistics</th>
                        <th scope="col" class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($announcements as $ann): ?>
                        <?php
                        $aid = (int)$ann['id'];
                        $statusStr = (string)($ann['status'] ?? 'Draft');
                        $prioStr = (string)($ann['priority'] ?? 'Normal');
                        $isPinned = !empty($ann['is_pinned']);
                        $canModifyRow = !empty($ann['can_modify']);
                        $canStatsRow = !empty($ann['can_view_stats']);
                        $readCnt = (int)($ann['read_count'] ?? 0);
                        $unreadCnt = (int)($ann['unread_count'] ?? 0);
                        $targetCnt = (int)($ann['targeted_count'] ?? 0);
                        $readPct = (float)($ann['read_percentage'] ?? 0.0);
                        ?>
                        <tr>
                            <th scope="row" style="font-weight: 600; min-width: 220px;">
                                <div style="display: flex; align-items: center; gap: 0.45rem; flex-wrap: wrap;">
                                    <?php if ($isPinned): ?>
                                        <span class="badge badge-primary" title="Pinned announcement" style="display: inline-flex; align-items: center; gap: 0.25rem;">
                                            <?= icon('pin', 12) ?> Pinned
                                        </span>
                                    <?php endif; ?>
                                    <a href="<?= url('/hr/announcements/' . $aid) ?>" style="color: var(--color-text-main); text-decoration: none; font-weight: 700;">
                                        <?= e((string)$ann['title']) ?>
                                    </a>
                                </div>
                                <div style="font-size: 0.75rem; color: var(--color-text-muted); font-weight: 400; margin-top: 0.2rem;">
                                    <?= e((string)$ann['category']) ?>
                                    <?php if ((int)($ann['attachment_count'] ?? 0) > 0): ?>
                                        &middot; <span><?= (int)$ann['attachment_count'] ?> attachment(s)</span>
                                    <?php endif; ?>
                                </div>
                            </th>
                            <td>
                                <span class="badge <?= $annPriorityBadge($prioStr) ?>"><?= e($prioStr) ?></span>
                            </td>
                            <td style="font-size: 0.8125rem; max-width: 180px;">
                                <?= e((string)($ann['audience_label'] ?? 'All Active Employees')) ?>
                            </td>
                            <td style="font-size: 0.8125rem; color: var(--color-text-muted);">
                                <?= e((string)($ann['author_display_name'] ?? 'HR Officer')) ?>
                            </td>
                            <td class="tabular-nums" style="font-size: 0.8125rem; color: var(--color-text-muted);">
                                <?php if (!empty($ann['published_at'])): ?>
                                    <div><?= e(date('M j, Y g:i A', strtotime((string)$ann['published_at']))) ?></div>
                                <?php elseif (!empty($ann['scheduled_at'])): ?>
                                    <div style="color: var(--color-primary);">Sched: <?= e(date('M j, Y g:i A', strtotime((string)$ann['scheduled_at']))) ?></div>
                                <?php else: ?>
                                    <span>Unpublished</span>
                                <?php endif; ?>
                            </td>
                            <td class="tabular-nums" style="font-size: 0.8125rem; color: var(--color-text-muted);">
                                <?= !empty($ann['expires_at']) ? e(date('M j, Y g:i A', strtotime((string)$ann['expires_at']))) : 'No expiration' ?>
                            </td>
                            <td>
                                <span class="badge <?= $annStatusBadge($statusStr) ?>"><?= e($statusStr) ?></span>
                            </td>
                            <td class="tabular-nums" style="font-size: 0.75rem; min-width: 140px;">
                                <?php if ($canStatsRow): ?>
                                    <div>
                                        <strong><?= $readCnt ?></strong> read / <?= $unreadCnt ?> unread
                                        <span style="color: var(--color-text-muted);">(<?= number_format($readPct, 1) ?>%)</span>
                                    </div>
                                    <div class="ann-read-meter" aria-hidden="true">
                                        <div class="ann-read-meter-bar" style="width: <?= min(100, max(0, $readPct)) ?>%;"></div>
                                    </div>
                                <?php else: ?>
                                    <span style="color: var(--color-text-muted);">Author stats</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-right" style="white-space: nowrap;">
                                <div style="display: inline-flex; gap: 0.35rem; align-items: center;">
                                    <a href="<?= url('/hr/announcements/' . $aid) ?>" class="btn btn-secondary btn-sm">View</a>
                                    <?php if ($canModifyRow && $statusStr !== 'Archived'): ?>
                                        <a href="<?= url('/hr/announcements/' . $aid . '/edit') ?>" class="btn btn-secondary btn-sm">Edit</a>
                                    <?php endif; ?>
                                    <?php if ($canModifyRow && $canPublish && in_array($statusStr, ['Draft', 'Scheduled'], true)): ?>
                                        <button type="button"
                                            class="btn btn-primary btn-sm js-ann-confirm-action"
                                            data-action-url="<?= e(url('/hr/announcements/' . $aid . '/publish')) ?>"
                                            data-action-title="Publish Announcement Immediately"
                                            data-action-desc="<?= e('Publish "' . (string)$ann['title'] . '" now? Targeted employees will receive an in-system notification.') ?>"
                                            data-action-btn="Publish Now"
                                            data-action-variant="primary">
                                            Publish
                                        </button>
                                    <?php endif; ?>
                                    <?php if ($canModifyRow && $canArchive && $statusStr !== 'Archived'): ?>
                                        <button type="button"
                                            class="btn btn-secondary btn-sm js-ann-confirm-action"
                                            data-action-url="<?= e(url('/hr/announcements/' . $aid . '/archive')) ?>"
                                            data-action-title="Archive Announcement"
                                            data-action-desc="<?= e('Archive "' . (string)$ann['title'] . '"? It will be removed from the active employee feed while preserving audit history.') ?>"
                                            data-action-btn="Archive"
                                            data-action-variant="danger">
                                            Archive
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if (($pagination['total_pages'] ?? 1) > 1): ?>
            <div class="card-footer" style="display: flex; align-items: center; justify-content: space-between; padding: 0.85rem 1.25rem; border-top: 1px solid var(--color-border);">
                <span class="tabular-nums" style="font-size: 0.8125rem; color: var(--color-text-muted);">
                    Page <?= (int)$pagination['page'] ?> of <?= (int)$pagination['total_pages'] ?>
                </span>
                <div style="display: flex; gap: 0.4rem;">
                    <?php if ((int)$pagination['page'] > 1): ?>
                        <a href="<?= e($buildPageUrl((int)$pagination['page'] - 1)) ?>" class="btn btn-secondary btn-sm">Previous</a>
                    <?php endif; ?>
                    <?php if ((int)$pagination['page'] < (int)$pagination['total_pages']): ?>
                        <a href="<?= e($buildPageUrl((int)$pagination['page'] + 1)) ?>" class="btn btn-secondary btn-sm">Next</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Native Confirmation Dialog (Modern Web Guidance `dialog` compliant) -->
<dialog id="annLifecycleDialog" class="ann-native-dialog" aria-labelledby="annDialogTitle">
    <div class="modal-header">
        <h2 class="modal-title" id="annDialogTitle">Confirm Action</h2>
        <button type="button" class="modal-close" id="annDialogCloseBtn" aria-label="Close dialog">
            <?= icon('x', 18) ?>
        </button>
    </div>
    <div class="modal-body">
        <p id="annDialogDesc" style="font-size: 0.875rem; color: var(--color-text-main); margin: 0;"></p>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-secondary" id="annDialogCancelBtn" autofocus>Cancel</button>
        <form method="POST" action="" id="annDialogForm" style="margin: 0;">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-primary" id="annDialogSubmitBtn">Confirm</button>
        </form>
    </div>
</dialog>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var dialog = document.getElementById('annLifecycleDialog');
    var form = document.getElementById('annDialogForm');
    var titleEl = document.getElementById('annDialogTitle');
    var descEl = document.getElementById('annDialogDesc');
    var submitBtn = document.getElementById('annDialogSubmitBtn');
    var cancelBtn = document.getElementById('annDialogCancelBtn');
    var closeBtn = document.getElementById('annDialogCloseBtn');

    function closeDialog() {
        if (dialog && typeof dialog.close === 'function' && dialog.open) {
            dialog.close();
        }
    }

    document.querySelectorAll('.js-ann-confirm-action').forEach(function (btn) {
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
