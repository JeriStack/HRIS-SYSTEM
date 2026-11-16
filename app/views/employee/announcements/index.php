<?php

/**
 * Employee Announcement Feed View (Phase 5.1 & Phase 12.2)
 * Employee HRIS & Leave Management System (NEXORA HR)
 */

$announcements = isset($announcements) && is_array($announcements) ? $announcements : [];
$unreadCount = isset($unreadCount) ? max(0, (int)$unreadCount) : 0;
$filters = isset($filters) && is_array($filters) ? $filters : [
    'search' => '', 'category' => '', 'priority' => '', 'unread_only' => false, 'pinned_only' => false,
];
$pagination = isset($pagination) && is_array($pagination) ? $pagination : [
    'total' => 0, 'page' => 1, 'per_page' => 10, 'total_pages' => 1,
];
$categories = isset($categories) && is_array($categories) ? $categories : [];
$priorities = isset($priorities) && is_array($priorities) ? $priorities : ['Normal', 'Important', 'Urgent'];

$prioBadge = static function (string $priority): string {
    return match ($priority) {
        'Urgent'    => 'badge-danger',
        'Important' => 'badge-warning',
        default     => 'badge-neutral',
    };
};

$buildFeedUrl = static function (int $targetPage) use ($filters): string {
    $q = array_filter([
        'search'      => $filters['search'] ?? '',
        'category'    => $filters['category'] ?? '',
        'priority'    => $filters['priority'] ?? '',
        'unread_only' => !empty($filters['unread_only']) ? '1' : '',
        'pinned_only' => !empty($filters['pinned_only']) ? '1' : '',
        'page'        => $targetPage > 1 ? $targetPage : null,
    ], static fn ($v): bool => $v !== null && $v !== '');

    return url('/employee/announcements') . (!empty($q) ? '?' . http_build_query($q) : '');
};
?>
<style>
.emp-ann-feed-list {
    display: flex;
    flex-direction: column;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-lg);
    background: var(--color-bg-surface);
    overflow: hidden;
}
.emp-ann-feed-item {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1.25rem;
    padding: 1.15rem 1.35rem;
    border-bottom: 1px solid var(--color-border);
    text-decoration: none;
    color: inherit;
    transition: background-color 0.15s ease;
}
.emp-ann-feed-item:last-child {
    border-bottom: none;
}
.emp-ann-feed-item:hover {
    background: var(--color-bg-hover);
}
.emp-ann-feed-item.is-unread {
    background: rgba(29, 78, 216, 0.035);
}
.emp-ann-unread-dot {
    width: 8px;
    height: 8px;
    border-radius: 999px;
    background: var(--color-primary);
    display: inline-block;
    flex-shrink: 0;
}
.emp-ann-filter-row {
    display: grid;
    grid-template-columns: 1.8fr 1.1fr 0.9fr auto auto;
    gap: 0.75rem;
    align-items: end;
}
@media (max-width: 960px) {
    .emp-ann-filter-row {
        grid-template-columns: 1fr 1fr;
    }
    .emp-ann-feed-item {
        flex-direction: column;
        gap: 0.75rem;
    }
}
@media (max-width: 640px) {
    .emp-ann-filter-row {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="page-header">
    <div class="page-title-group">
        <h1>Company Announcements</h1>
        <p>Official organizational updates, policy notices, and departmental advisories addressed to you.</p>
    </div>
    <div class="page-actions" style="display: flex; align-items: center; gap: 0.6rem;">
        <span class="badge <?= $unreadCount > 0 ? 'badge-primary' : 'badge-neutral' ?> tabular-nums" id="empAnnUnreadSummaryBadge">
            <?= $unreadCount ?> unread
        </span>
        <a href="<?= url('/employee/dashboard') ?>" class="btn btn-secondary btn-sm">
            <?= icon('arrow-left', 15) ?>
            <span>Dashboard</span>
        </a>
    </div>
</div>

<!-- Search & Filter Bar -->
<div class="card" style="margin-bottom: 1.25rem;">
    <div class="card-body" style="padding: 1rem 1.25rem;">
        <form method="GET" action="<?= url('/employee/announcements') ?>" class="emp-ann-filter-row" aria-label="Filter announcements">
            <div class="form-group" style="margin: 0;">
                <label class="form-label" for="empAnnSearch" style="font-size: 0.75rem;">Search</label>
                <input type="search" id="empAnnSearch" name="search" class="form-control" value="<?= e($filters['search'] ?? '') ?>" placeholder="Search announcements...">
            </div>
            <div class="form-group" style="margin: 0;">
                <label class="form-label" for="empAnnCategory" style="font-size: 0.75rem;">Category</label>
                <select id="empAnnCategory" name="category" class="form-control">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $catKey => $catMeta): ?>
                        <?php $catVal = is_array($catMeta) ? (string)($catMeta['label'] ?? $catKey) : (string)$catMeta; ?>
                        <option value="<?= e($catVal) ?>" <?= ($filters['category'] ?? '') === $catVal ? 'selected' : '' ?>><?= e($catVal) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin: 0;">
                <label class="form-label" for="empAnnPriority" style="font-size: 0.75rem;">Priority</label>
                <select id="empAnnPriority" name="priority" class="form-control">
                    <option value="">All Priorities</option>
                    <?php foreach ($priorities as $prio): ?>
                        <option value="<?= e((string)$prio) ?>" <?= ($filters['priority'] ?? '') === (string)$prio ? 'selected' : '' ?>><?= e((string)$prio) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="display: flex; align-items: center; gap: 0.85rem; padding-bottom: 0.45rem;">
                <label style="display: inline-flex; align-items: center; gap: 0.4rem; font-size: 0.8125rem; font-weight: 600; cursor: pointer;">
                    <input type="checkbox" name="unread_only" value="1" <?= !empty($filters['unread_only']) ? 'checked' : '' ?>>
                    <span>Unread Only</span>
                </label>
                <label style="display: inline-flex; align-items: center; gap: 0.4rem; font-size: 0.8125rem; font-weight: 600; cursor: pointer;">
                    <input type="checkbox" name="pinned_only" value="1" <?= !empty($filters['pinned_only']) ? 'checked' : '' ?>>
                    <span>Pinned</span>
                </label>
            </div>
            <div style="display: flex; gap: 0.45rem;">
                <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                <a href="<?= url('/employee/announcements') ?>" class="btn btn-secondary btn-sm">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Feed List -->
<?php if (empty($announcements)): ?>
    <div class="card">
        <div class="card-body" style="padding: 3rem 1.5rem; text-align: center;">
            <div style="display: inline-flex; width: 48px; height: 48px; border-radius: 999px; align-items: center; justify-content: center; background: var(--color-bg-hover); color: var(--color-primary); margin-bottom: 0.75rem;">
                <?= icon('megaphone', 22) ?>
            </div>
            <h2 style="font-size: 1rem; font-weight: 700; color: var(--color-text-main); margin: 0 0 0.35rem 0;">No Announcements Found</h2>
            <p style="font-size: 0.875rem; color: var(--color-text-muted); max-width: 32rem; margin: 0 auto;">
                There are currently no active announcements matching your filter criteria.
            </p>
        </div>
    </div>
<?php else: ?>
    <section class="emp-ann-feed-list" aria-label="Employee announcements feed">
        <?php foreach ($announcements as $ann): ?>
            <?php
            $aid = (int)$ann['id'];
            $isRead = !empty($ann['is_read']);
            $isPinned = !empty($ann['is_pinned']);
            $prioStr = (string)($ann['priority'] ?? 'Normal');
            $attCount = (int)($ann['attachment_count'] ?? 0);
            ?>
            <a href="<?= url('/employee/announcements/' . $aid) ?>" class="emp-ann-feed-item <?= $isRead ? '' : 'is-unread' ?>">
                <div style="flex: 1; min-width: 0;">
                    <div style="display: flex; align-items: center; gap: 0.45rem; flex-wrap: wrap; margin-bottom: 0.3rem;">
                        <?php if (!$isRead): ?>
                            <span class="emp-ann-unread-dot" aria-hidden="true"></span>
                            <span class="badge badge-primary" style="font-size: 0.75rem;">Unread</span>
                        <?php else: ?>
                            <span class="badge badge-neutral" style="font-size: 0.75rem;">Read</span>
                        <?php endif; ?>
                        <?php if ($isPinned): ?>
                            <span class="badge badge-primary" style="font-size: 0.75rem; display: inline-flex; align-items: center; gap: 0.2rem;">
                                <?= icon('pin', 11) ?> Pinned
                            </span>
                        <?php endif; ?>
                        <span class="badge <?= $prioBadge($prioStr) ?>" style="font-size: 0.75rem;"><?= e($prioStr) ?></span>
                        <span class="badge badge-neutral" style="font-size: 0.75rem;"><?= e((string)$ann['category']) ?></span>
                        <?php if ($attCount > 0): ?>
                            <span style="display: inline-flex; align-items: center; gap: 0.25rem; font-size: 0.75rem; color: var(--color-text-muted);">
                                <?= icon('paperclip', 13) ?>
                                <span class="tabular-nums"><?= $attCount ?></span>
                            </span>
                        <?php endif; ?>
                    </div>

                    <h2 style="font-size: 1rem; font-weight: <?= $isRead ? '600' : '700' ?>; color: var(--color-text-main); margin: 0 0 0.3rem 0;">
                        <?= e((string)$ann['title']) ?>
                    </h2>
                    <p style="font-size: 0.875rem; color: var(--color-text-muted); margin: 0 0 0.45rem 0; line-height: 1.55; max-width: 72ch;">
                        <?= e((string)($ann['content_preview'] ?? '')) ?>
                    </p>
                    <div class="tabular-nums" style="font-size: 0.75rem; color: var(--color-text-muted);">
                        Published <?= !empty($ann['published_at']) ? e(date('M j, Y g:i A', strtotime((string)$ann['published_at']))) : '—' ?>
                        &middot; By <?= e((string)($ann['author_display_name'] ?? 'Human Resources')) ?>
                        &middot; Audience: <?= e((string)($ann['audience_label'] ?? 'All Active Employees')) ?>
                        <?php if (!empty($ann['expires_at'])): ?>
                            &middot; Valid until <?= e(date('M j, Y', strtotime((string)$ann['expires_at']))) ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div style="display: inline-flex; align-items: center; gap: 0.3rem; font-size: 0.8125rem; font-weight: 600; color: var(--color-primary); flex-shrink: 0; align-self: center;">
                    <span>Read Full Notice</span>
                    <?= icon('chevron-right', 16) ?>
                </div>
            </a>
        <?php endforeach; ?>
    </section>

    <?php if (($pagination['total_pages'] ?? 1) > 1): ?>
        <div style="display: flex; align-items: center; justify-content: space-between; margin-top: 1rem;">
            <span class="tabular-nums" style="font-size: 0.8125rem; color: var(--color-text-muted);">
                Page <?= (int)$pagination['page'] ?> of <?= (int)$pagination['total_pages'] ?>
            </span>
            <div style="display: flex; gap: 0.4rem;">
                <?php if ((int)$pagination['page'] > 1): ?>
                    <a href="<?= e($buildFeedUrl((int)$pagination['page'] - 1)) ?>" class="btn btn-secondary btn-sm">Previous</a>
                <?php endif; ?>
                <?php if ((int)$pagination['page'] < (int)$pagination['total_pages']): ?>
                    <a href="<?= e($buildFeedUrl((int)$pagination['page'] + 1)) ?>" class="btn btn-secondary btn-sm">Next</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>
