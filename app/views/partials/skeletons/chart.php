<?php
/**
 * Skeleton Chart Placeholder Partial
 *
 * @var int $bars Number of chart bar columns (default 7)
 * @var bool $hasHeader Whether to include title header (default true)
 * @var int $height Height of chart area in pixels (default 220)
 */
$bars = isset($bars) ? max(3, (int)$bars) : 7;
$hasHeader = $hasHeader ?? true;
$height = isset($height) ? max(120, (int)$height) : 220;

$heightPercentages = [45, 75, 30, 85, 60, 90, 50, 68, 40, 80, 55, 70];
?>
<div class="skeleton-chart-container" aria-hidden="true">
    <?php if ($hasHeader): ?>
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
            <div>
                <div class="skeleton-heading" style="width: 160px; margin-bottom: 0.35rem;"></div>
                <div class="skeleton-text skeleton-w-75" style="width: 220px; height: 0.75rem; margin-bottom: 0;"></div>
            </div>
            <div style="display: flex; gap: 0.5rem;">
                <div class="skeleton-badge" style="width: 60px;"></div>
                <div class="skeleton-badge" style="width: 60px;"></div>
            </div>
        </div>
    <?php endif; ?>

    <div class="skeleton-chart-bars" style="height: <?= (int)$height ?>px;">
        <?php for ($i = 0; $i < $bars; $i++): ?>
            <?php $barHeight = $heightPercentages[$i % count($heightPercentages)]; ?>
            <div class="skeleton-chart-bar-wrap">
                <div class="skeleton-bar" style="height: <?= (int)$barHeight ?>%;"></div>
                <div class="skeleton-text" style="width: 28px; height: 0.65rem; margin-bottom: 0;"></div>
            </div>
        <?php endfor; ?>
    </div>
</div>

