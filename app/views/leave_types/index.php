<?php
/**
 * Leave Types & Policy Management View (HR & Admin Access)
 * Employee HRIS & Leave Management System - Phase 8
 */
?>
<div class="page-header">
    <div class="page-title-group">
        <h1>Leave Classifications & Policies</h1>
        <p>Define standard leave categories, default annual quotas, and activate or deactivate policies.</p>
    </div>
    <div class="page-actions">
        <a href="<?= url('/leaves') ?>" class="btn btn-secondary btn-sm">
            <span>📋</span> Leave Review Queue
        </a>
        <a href="<?= url('/hr/leave-types/create') ?>" class="btn btn-primary btn-sm">
            <span>➕</span> New Classification
        </a>
    </div>
</div>

<!-- Filter Bar -->
<div class="filter-bar">
    <span style="font-size: 0.8125rem; font-weight: 600; color: var(--color-text-muted); margin-right: 0.5rem;">
        Filter Status:
    </span>
    <div style="display: flex; gap: 0.4rem; flex-wrap: wrap;">
        <a href="<?= url('/hr/leave-types') ?>" class="btn btn-sm <?= empty($selectedStatus) ? 'btn-primary' : 'btn-secondary' ?>">
            All (<?= count($leaveTypes) ?>)
        </a>
        <a href="<?= url('/hr/leave-types') ?>?status=Active" class="btn btn-sm <?= $selectedStatus === 'Active' ? 'btn-primary' : 'btn-secondary' ?>">
            ✓ Active
        </a>
        <a href="<?= url('/hr/leave-types') ?>?status=Inactive" class="btn btn-sm <?= $selectedStatus === 'Inactive' ? 'btn-primary' : 'btn-secondary' ?>">
            ⊘ Inactive
        </a>
    </div>
</div>

<!-- Classifications Table Card -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">
            <span>🏷️</span> Configured Leave Types
        </h2>
        <span style="font-size: 0.8125rem; color: var(--color-text-muted);">
            <?= count($leaveTypes) ?> Record<?= count($leaveTypes) === 1 ? '' : 's' ?>
        </span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover" aria-label="Leave Types Table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Classification Name</th>
                    <th>Default Annual Quota</th>
                    <th>Description / Eligibility</th>
                    <th>Usage References</th>
                    <th>Status</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($leaveTypes)): ?>
                    <tr>
                        <td colspan="7" class="text-center" style="padding: 3rem;">
                            <div class="empty-state">
                                <div class="empty-state-icon" aria-hidden="true">🏷️</div>
                                <div class="empty-state-title">No Leave Classifications Found</div>
                                <div class="empty-state-text">Click "New Classification" above to configure your organization's first leave type.</div>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($leaveTypes as $lt): 
                        $isActive = ($lt['status'] === 'Active');
                        $badgeClass = $isActive ? 'badge-success' : 'badge-neutral';
                        $reqCount = (int)($lt['request_count'] ?? 0);
                        $balCount = (int)($lt['balance_count'] ?? 0);
                    ?>
                        <tr>
                            <td>
                                <strong style="font-family: var(--font-mono); font-size: 0.8125rem; color: var(--color-primary);">
                                    #<?= (int)$lt['id'] ?>
                                </strong>
                            </td>
                            <td>
                                <strong><?= e($lt['leave_name']) ?></strong>
                            </td>
                            <td>
                                <strong><?= (int)$lt['default_days'] ?></strong> Days / Year
                            </td>
                            <td style="font-size: 0.8125rem; color: var(--color-text-muted); max-width: 280px;">
                                <?= !empty($lt['description']) ? e($lt['description']) : '<span style="font-style: italic;">No description provided</span>' ?>
                            </td>
                            <td>
                                <span style="font-size: 0.75rem; font-family: var(--font-mono);">
                                    <?= $reqCount ?> request<?= $reqCount === 1 ? '' : 's' ?> &bull; <?= $balCount ?> balance<?= $balCount === 1 ? '' : 's' ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge <?= $badgeClass ?>">
                                    <span class="badge-dot" aria-hidden="true"></span>
                                    <?= e($lt['status']) ?>
                                </span>
                            </td>
                            <td class="text-right">
                                <div class="table-actions" style="justify-content: flex-end;">
                                    <a href="<?= url('/hr/leave-types/' . $lt['id'] . '/edit') ?>" class="btn btn-secondary btn-sm" title="Edit Classification">
                                        <span>✏️</span> Edit
                                    </a>

                                    <form method="POST" action="<?= url('/hr/leave-types/' . $lt['id'] . '/toggle-status') ?>" style="display: inline;" onsubmit="return confirm('Confirm <?= $isActive ? 'deactivation' : 'activation' ?> of <?= addslashes($lt['leave_name']) ?>? Historical records will be safely preserved.');">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn <?= $isActive ? 'btn-danger' : 'btn-success' ?> btn-sm">
                                            <?= $isActive ? 'Deactivate' : 'Activate' ?>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
