<?php
/**
 * Skeleton Form Partial
 *
 * @var int $fields Number of form fields to render (default 4)
 * @var int $columns Number of grid columns for fields (default 1)
 * @var bool $hasButton Whether to include submit button placeholder (default true)
 */
$fields = isset($fields) ? max(1, (int)$fields) : 4;
$columns = isset($columns) ? max(1, (int)$columns) : 1;
$hasButton = $hasButton ?? true;
?>
<div class="skeleton-card" aria-hidden="true">
    <div class="skeleton-card-body">
        <div style="display: grid; grid-template-columns: repeat(<?= $columns ?>, 1fr); gap: 1.25rem;">
            <?php for ($i = 0; $i < $fields; $i++): ?>
                <div class="skeleton-form-group">
                    <div class="skeleton-form-label"></div>
                    <div class="skeleton-input"></div>
                </div>
            <?php endfor; ?>
        </div>

        <?php if ($hasButton): ?>
            <div style="display: flex; gap: 0.75rem; margin-top: 1.5rem; justify-content: flex-end;">
                <div class="skeleton-button" style="width: 90px;"></div>
                <div class="skeleton-button" style="width: 130px;"></div>
            </div>
        <?php endif; ?>
    </div>
</div>

