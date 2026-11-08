<?php
/**
 * Skeleton List Partial
 *
 * @var int $count Number of list items to render (default 5)
 * @var bool $hasAvatar Whether each item includes an avatar (default true)
 * @var bool $hasBadge Whether each item includes a trailing badge (default true)
 */
$count = isset($count) ? max(1, (int)$count) : 5;
$hasAvatar = $hasAvatar ?? true;
$hasBadge = $hasBadge ?? true;
?>
<div class="skeleton-list" aria-hidden="true">
    <?php for ($i = 0; $i < $count; $i++): ?>
        <div class="skeleton-list-item">
            <?php if ($hasAvatar): ?>
                <div class="skeleton-avatar"></div>
            <?php endif; ?>
            <div class="skeleton-list-content">
                <div class="skeleton-heading-sm skeleton-w-50" style="margin-bottom: 0.35rem;"></div>
                <div class="skeleton-text skeleton-w-75" style="margin-bottom: 0;"></div>
            </div>
            <?php if ($hasBadge): ?>
                <div class="skeleton-badge"></div>
            <?php endif; ?>
        </div>
    <?php endfor; ?>
</div>

