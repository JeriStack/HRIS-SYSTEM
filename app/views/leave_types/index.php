<?php
/**
 * HR Leave Classifications & Philippine Statutory Policy Console
 */
$selectedStatus = (string)($selectedStatus ?? '');
?>
<div class="page-header">
    <div class="page-title-group">
        <h1>Leave Classifications &amp; Policies</h1>
        <p>Configure statutory and company leave benefits, entitlement models, SIL offset behavior, and versioned rules.</p>
    </div>
    <div class="page-actions">
        <a href="<?= url('/hr/leave-qualifications') ?>" class="btn btn-secondary btn-sm">
            <?= icon('check-circle', 15) ?>
            <span>Leave Qualifications</span>
        </a>
        <a href="<?= url('/hr/leave-types/create') ?>" class="btn btn-primary btn-sm">
            <?= icon('plus', 15) ?>
            <span>Create Leave Classification</span>
        </a>
    </div>
</div>

<div class="card" style="margin-bottom: var(--space-5); padding: var(--space-4);">
    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
        <a href="<?= url('/hr/leave-types') ?>" class="btn btn-sm <?= $selectedStatus === '' ? 'btn-primary' : 'btn-secondary' ?>">All Classifications</a>
        <a href="<?= url('/hr/leave-types?status=Active') ?>" class="btn btn-sm <?= $selectedStatus === 'Active' ? 'btn-primary' : 'btn-secondary' ?>">Active Only</a>
        <a href="<?= url('/hr/leave-types?status=Inactive') ?>" class="btn btn-sm <?= $selectedStatus === 'Inactive' ? 'btn-primary' : 'btn-secondary' ?>">Inactive Only</a>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">Configured Leave Classifications (<?= count($leaveTypes ?? []) ?>)</h2>
    </div>
    <div class="table-wrapper">
        <table class="table table-hover" aria-label="Leave Types Table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Classification Name</th>
                    <th>Category &amp; Model</th>
                    <th>Default Quota &amp; Unit</th>
                    <th>Policy Flags &amp; Legal Ref</th>
                    <th>Usage</th>
                    <th>Status</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($leaveTypes)): ?>
                    <tr><td colspan="8" class="text-center" style="padding: 2rem; color: var(--color-text-muted);">No leave classifications found.</td></tr>
                <?php else: foreach ($leaveTypes as $lt):
                    $isActive = ($lt['status'] ?? 'Active') === 'Active';
                    $classif = (string)($lt['classification'] ?? 'Company');
                    $model = (string)($lt['entitlement_model'] ?? 'annual');
                    $unit = (($lt['default_unit'] ?? 'working_days') === 'calendar_days') ? 'Calendar Days' : 'Working Days';
                ?>
                    <tr>
                        <td style="font-family: var(--font-mono); font-size: var(--font-size-xs);">#<?= (int)$lt['id'] ?></td>
                        <td>
                            <strong><?= e((string)$lt['leave_name']) ?></strong>
                            <?php if (!empty($lt['leave_code'])): ?>
                                <div style="font-size: var(--font-size-xs); color: var(--color-text-muted); font-family: var(--font-mono);"><?= e((string)$lt['leave_code']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="display: flex; flex-wrap: wrap; gap: 0.25rem;">
                                <span class="badge <?= $classif === 'Statutory' ? 'badge-statutory' : 'badge-company' ?>"><?= e($classif) ?></span>
                                <span class="badge <?= $model === 'unpaid' ? 'badge-unpaid' : 'badge-neutral' ?>"><?= e($model === 'event_based' ? 'Event-Based' : ($model === 'unpaid' ? 'Unpaid' : 'Annual')) ?></span>
                            </div>
                        </td>
                        <td>
                            <?php if ($model === 'unpaid'): ?>
                                <span style="color: var(--color-text-muted);">Unpaid (0 quota)</span>
                            <?php else: ?>
                                <strong><?= (int)($lt['default_days'] ?? 0) ?></strong> <?= e($unit) ?>
                            <?php endif; ?>
                            <?php if (!empty($lt['policy_code'])): ?>
                                <div style="font-size: var(--font-size-xs); color: var(--color-text-muted);">Policy: <?= e((string)$lt['policy_code']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td style="max-width: 240px;">
                            <div style="font-size: var(--font-size-xs); color: var(--color-text-body);">
                                <?= e((string)($lt['legal_reference'] ?? ($lt['description'] ?? 'Company Policy'))) ?>
                            </div>
                            <div style="display: flex; gap: 0.25rem; flex-wrap: wrap; margin-top: 0.25rem;">
                                <?php if (!empty($lt['is_confidential'])): ?>
                                    <span class="badge badge-confidential" style="font-size: 0.6875rem;">Confidential</span>
                                <?php endif; ?>
                                <?php if (!empty($lt['counts_as_sil_equivalent'])): ?>
                                    <span class="badge badge-info" style="font-size: 0.6875rem;">SIL Equivalent</span>
                                <?php endif; ?>
                                <?php if (!empty($lt['cash_convertible'])): ?>
                                    <span class="badge badge-success" style="font-size: 0.6875rem;">Cash Convertible</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td style="font-size: var(--font-size-xs); color: var(--color-text-muted);">
                            <?= (int)($lt['request_count'] ?? 0) ?> reqs &middot; <?= (int)($lt['balance_count'] ?? 0) ?> bals
                        </td>
                        <td>
                            <span class="badge <?= $isActive ? 'badge-success' : 'badge-neutral' ?>"><?= e((string)($lt['status'] ?? 'Active')) ?></span>
                        </td>
                        <td class="text-right" style="white-space: nowrap;">
                            <a href="<?= url('/hr/leave-types/' . (int)$lt['id'] . '/edit') ?>" class="btn btn-secondary btn-xs">Edit &amp; Versions</a>
                            <form method="POST" action="<?= url('/hr/leave-types/' . $lt['id'] . '/toggle-status') ?>" style="display: inline;" onsubmit="return confirm('Confirm <?= $isActive ? 'deactivation' : 'activation' ?> of <?= addslashes((string)$lt['leave_name']) ?>? Historical records will be safely preserved.');">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-secondary btn-xs"><?= $isActive ? 'Deactivate' : 'Activate' ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
