<?php
/**
 * Skeleton Table Partial
 *
 * @var int $columns Number of columns per row (default 5)
 * @var int $rows Number of rows to render (default 5)
 * @var bool $hasToolbar Whether to render a search/filter toolbar above table (default false)
 * @var bool $standalone Whether to render without the outer wrapper card (default false)
 */
$columns = isset($columns) ? max(1, (int)$columns) : 5;
$rows = isset($rows) ? max(1, (int)$rows) : 5;
$hasToolbar = !empty($hasToolbar);
$standalone = !empty($standalone);

$widths = ['skeleton-w-40', 'skeleton-w-75', 'skeleton-w-60', 'skeleton-w-50', 'skeleton-w-30', 'skeleton-w-70'];
?>
<?php if (!$standalone): ?>
<div class="skeleton-table-wrapper" aria-hidden="true">
    <?php if ($hasToolbar): ?>
        <div class="skeleton-table-toolbar">
            <div class="skeleton-input" style="max-width: 280px; height: 36px;"></div>
            <div style="display: flex; gap: 0.5rem;">
                <div class="skeleton-button" style="width: 100px; height: 36px;"></div>
                <div class="skeleton-button" style="width: 80px; height: 36px;"></div>
            </div>
        </div>
    <?php endif; ?>
    <div style="overflow-x: auto;">
<?php endif; ?>

<table class="skeleton-table" aria-hidden="true">
    <thead>
        <tr>
            <?php for ($c = 0; $c < $columns; $c++): ?>
                <th>
                    <div class="skeleton-text <?= $widths[$c % count($widths)] ?>" style="height: 0.8125rem; margin-bottom: 0;"></div>
                </th>
            <?php endfor; ?>
        </tr>
    </thead>
    <tbody>
        <?php for ($r = 0; $r < $rows; $r++): ?>
            <tr>
                <?php for ($c = 0; $c < $columns; $c++): ?>
                    <td>
                        <div class="skeleton-text <?= $widths[($c + $r) % count($widths)] ?>" style="margin-bottom: 0;"></div>
                    </td>
                <?php endfor; ?>
            </tr>
        <?php endfor; ?>
    </tbody>
</table>

<?php if (!$standalone): ?>
    </div>
</div>
<?php endif; ?>

