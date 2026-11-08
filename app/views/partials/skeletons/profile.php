<?php
/**
 * Skeleton Profile Section Partial
 *
 * @var int $fields Number of detail fields in profile grid (default 6)
 */
$fields = isset($fields) ? max(2, (int)$fields) : 6;
?>
<div aria-hidden="true">
    <div class="skeleton-profile-header">
        <div class="skeleton-avatar skeleton-avatar-xl"></div>
        <div style="flex: 1; min-width: 0;">
            <div class="skeleton-heading" style="width: 220px; margin-bottom: 0.5rem;"></div>
            <div class="skeleton-text skeleton-w-40" style="margin-bottom: 0.5rem;"></div>
            <div style="display: flex; gap: 0.5rem;">
                <div class="skeleton-badge"></div>
                <div class="skeleton-badge" style="width: 90px;"></div>
            </div>
        </div>
        <div class="skeleton-button" style="width: 130px;"></div>
    </div>

    <div class="skeleton-card">
        <div class="skeleton-card-header">
            <div class="skeleton-heading" style="width: 180px; margin-bottom: 0;"></div>
        </div>
        <div class="skeleton-card-body">
            <div class="skeleton-profile-grid">
                <?php for ($i = 0; $i < $fields; $i++): ?>
                    <div>
                        <div class="skeleton-heading-sm skeleton-w-40" style="height: 0.75rem; margin-bottom: 0.35rem;"></div>
                        <div class="skeleton-text skeleton-w-75"></div>
                    </div>
                <?php endfor; ?>
            </div>
        </div>
    </div>
</div>

