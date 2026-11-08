<?php
/**
 * Skeleton Card Partial
 *
 * @var int $lines Number of text lines in card body (default 3)
 * @var bool $hasHeader Whether to include card header (default true)
 * @var bool $hasFooter Whether to include card footer (default false)
 * @var bool $hasAvatar Whether header includes an avatar (default false)
 */
$lines = isset($lines) ? max(1, (int)$lines) : 3;
$hasHeader = $hasHeader ?? true;
$hasFooter = !empty($hasFooter);
$hasAvatar = !empty($hasAvatar);

$bodyWidths = ['skeleton-w-full', 'skeleton-w-85', 'skeleton-w-70', 'skeleton-w-60', 'skeleton-w-40'];
?>
<div class="skeleton-card" aria-hidden="true">
    <?php if ($hasHeader): ?>
        <div class="skeleton-card-header">
            <div style="display: flex; align-items: center; gap: 0.75rem; flex: 1;">
                <?php if ($hasAvatar): ?>
                    <div class="skeleton-avatar"></div>
                <?php endif; ?>
                <div class="skeleton-heading" style="width: 45%; margin-bottom: 0;"></div>
            </div>
            <div class="skeleton-badge"></div>
        </div>
    <?php endif; ?>

    <div class="skeleton-card-body">
        <?php for ($i = 0; $i < $lines; $i++): ?>
            <div class="skeleton-text <?= $bodyWidths[$i % count($bodyWidths)] ?>"></div>
        <?php endfor; ?>
    </div>

    <?php if ($hasFooter): ?>
        <div class="skeleton-card-footer">
            <div class="skeleton-button" style="width: 90px; height: 34px;"></div>
            <div class="skeleton-button" style="width: 110px; height: 34px;"></div>
        </div>
    <?php endif; ?>
</div>

