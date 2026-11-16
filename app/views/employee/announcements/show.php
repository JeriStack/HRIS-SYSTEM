<?php

/**
 * Employee Announcement Details View (Phase 5.3 & Phase 6.1)
 * Employee HRIS & Leave Management System (NEXORA HR)
 */

$announcement = isset($announcement) && is_array($announcement) ? $announcement : [];
$aid = (int)($announcement['id'] ?? 0);
$prioStr = (string)($announcement['priority'] ?? 'Normal');
$isPinned = !empty($announcement['is_pinned']);
$attachments = isset($announcement['attachments']) && is_array($announcement['attachments']) ? $announcement['attachments'] : [];

$prioBadge = match ($prioStr) {
    'Urgent'    => 'badge-danger',
    'Important' => 'badge-warning',
    default     => 'badge-neutral',
};
?>
<div class="page-header">
    <div class="page-title-group">
        <div style="display: flex; align-items: center; gap: 0.45rem; flex-wrap: wrap; margin-bottom: 0.35rem;">
            <?php if ($isPinned): ?>
                <span class="badge badge-primary" style="display: inline-flex; align-items: center; gap: 0.25rem;">
                    <?= icon('pin', 12) ?> Pinned
                </span>
            <?php endif; ?>
            <span class="badge <?= $prioBadge ?>"><?= e($prioStr) ?> Priority</span>
            <span class="badge badge-neutral"><?= e((string)($announcement['category'] ?? '')) ?></span>
            <span class="badge badge-success" style="display: inline-flex; align-items: center; gap: 0.25rem;">
                <?= icon('check-circle', 12) ?> Read Receipt Recorded
            </span>
        </div>
        <h1><?= e((string)($announcement['title'] ?? 'Announcement')) ?></h1>
        <p class="tabular-nums">
            Published <?= !empty($announcement['published_at']) ? e(date('F j, Y \a\t g:i A', strtotime((string)$announcement['published_at']))) : '—' ?>
            &middot; By <strong><?= e((string)($announcement['author_display_name'] ?? 'Human Resources')) ?></strong>
            &middot; Audience: <strong><?= e((string)($announcement['audience_label'] ?? 'All Active Employees')) ?></strong>
        </p>
    </div>
    <div class="page-actions">
        <a href="<?= url('/employee/announcements') ?>" class="btn btn-secondary btn-sm">
            <?= icon('arrow-left', 15) ?>
            <span>All Announcements</span>
        </a>
    </div>
</div>

<article class="card">
    <div class="card-body" style="padding: 1.75rem;">
        <div style="font-size: 1rem; line-height: 1.75; color: var(--color-text-main); white-space: pre-line; max-width: 72ch;"><?= e((string)($announcement['content'] ?? '')) ?></div>

        <?php if (!empty($attachments)): ?>
            <section style="margin-top: 1.75rem; padding-top: 1.25rem; border-top: 1px solid var(--color-border);" aria-labelledby="empAnnAttachmentsHeading">
                <h2 id="empAnnAttachmentsHeading" style="font-size: 0.875rem; font-weight: 700; color: var(--color-text-main); margin: 0 0 0.75rem 0;">
                    Supporting Attachments (<?= count($attachments) ?>)
                </h2>
                <div style="display: flex; flex-direction: column; gap: 0.5rem; max-width: 42rem;">
                    <?php foreach ($attachments as $att): ?>
                        <div style="display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; padding: 0.65rem 0.9rem; border: 1px solid var(--color-border); border-radius: var(--radius-md); background: var(--color-bg-body);">
                            <div style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.875rem;">
                                <?= icon('paperclip', 15) ?>
                                <strong><?= e((string)$att['original_filename']) ?></strong>
                                <span class="tabular-nums" style="color: var(--color-text-muted); font-size: 0.75rem;">
                                    (<?= number_format(((int)$att['file_size']) / 1024, 1) ?> KB)
                                </span>
                            </div>
                            <a href="<?= url('/employee/announcements/' . $aid . '/attachments/' . (int)$att['id']) ?>" class="btn btn-secondary btn-sm">
                                Download
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <footer class="tabular-nums" style="margin-top: 1.75rem; padding-top: 1rem; border-top: 1px solid var(--color-border); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem; font-size: 0.75rem; color: var(--color-text-muted);">
            <span>
                <?php if (!empty($announcement['read_at'])): ?>
                    First read on <?= e(date('M j, Y g:i A', strtotime((string)$announcement['read_at']))) ?>
                <?php else: ?>
                    Read receipt recorded
                <?php endif; ?>
            </span>
            <?php if (!empty($announcement['expires_at'])): ?>
                <span>Notice valid through <?= e(date('M j, Y g:i A', strtotime((string)$announcement['expires_at']))) ?></span>
            <?php endif; ?>
        </footer>
    </div>
</article>
