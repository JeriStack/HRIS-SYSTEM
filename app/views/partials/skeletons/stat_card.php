<?php
/**
 * Skeleton Stat Cards Partial
 *
 * @var int $count Number of stat cards to render (default 4)
 * @var bool $standalone Whether to render without the outer grid wrapper
 */
$count = isset($count) ? (int)$count : 4;
$standalone = !empty($standalone);
?>
<?php if (!$standalone): ?>
<div class="skeleton-stat-grid" aria-hidden="true">
<?php endif; ?>

    <?php for ($i = 0; $i < $count; $i++): ?>
        <div class="skeleton-stat-card" aria-hidden="true">
            <div class="skeleton-stat-info">
                <div class="skeleton-heading-sm skeleton-w-50" style="margin-bottom: 0.5rem;"></div>
                <div class="skeleton-heading-lg skeleton-w-40" style="margin-bottom: 0.5rem;"></div>
                <div class="skeleton-text skeleton-w-70"></div>
            </div>
            <div class="skeleton-icon"></div>
        </div>
    <?php endfor; ?>

<?php if (!$standalone): ?>
</div>
<?php endif; ?>

