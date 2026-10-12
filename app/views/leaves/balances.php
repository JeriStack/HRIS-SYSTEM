<?php
/**
 * Workforce Leave Balances View (HR & Admin Access)
 * Employee HRIS & Leave Management System
 */
?>
<div class="page-header">
    <div class="page-title-group">
        <h1>Workforce Leave Balances</h1>
        <p>Annual leave entitlements, usage tracking, and remaining quotas across organizational units.</p>
    </div>
    <div class="page-actions">
        <a href="<?= url('/leaves') ?>" class="btn btn-secondary btn-sm">
            <span>📋</span> Leave Review Queue
        </a>
    </div>
</div>

<!-- Balances Table Card -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">
            <span>⚖️</span> Leave Balances Directory (Year <?= (int)$selectedYear ?>)
        </h2>
        <span style="font-size: 0.8125rem; color: var(--color-text-muted);">
            <?= count($balances) ?> Record Allocation<?= count($balances) === 1 ? '' : 's' ?>
        </span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover" aria-label="Workforce Leave Balances Table">
            <thead>
                <tr>
                    <th>Employee ID</th>
                    <th>Employee Name</th>
                    <th>Department</th>
                    <th>Leave Classification</th>
                    <th>Allocated Days</th>
                    <th>Days Used</th>
                    <th>Remaining Days</th>
                    <th>Utilization Meter</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($balances)): ?>
                    <tr>
                        <td colspan="8" class="text-center" style="padding: 2.5rem; color: var(--color-text-muted);">
                            No leave balance records found for <?= (int)$selectedYear ?>.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($balances as $b): 
                        $alloc = (int)$b['allocated_days'];
                        $used = (int)$b['used_days'];
                        $rem = (int)$b['remaining_days'];
                        $pct = $alloc > 0 ? min(100, round(($used / $alloc) * 100)) : 0;
                    ?>
                        <tr>
                            <td>
                                <strong style="font-family: var(--font-mono); font-size: 0.8125rem; color: var(--color-primary);">
                                    <?= e($b['employee_no']) ?>
                                </strong>
                            </td>
                            <td>
                                <strong><?= e($b['first_name'] . ' ' . $b['last_name']) ?></strong>
                            </td>
                            <td>
                                <span class="badge badge-neutral">
                                    <?= e($b['department_name'] ?? 'General') ?>
                                </span>
                            </td>
                            <td>
                                <?= e($b['leave_name']) ?>
                            </td>
                            <td>
                                <?= $alloc ?> days
                            </td>
                            <td>
                                <strong><?= $used ?></strong> days
                            </td>
                            <td>
                                <span class="badge <?= $rem > 3 ? 'badge-success' : ($rem > 0 ? 'badge-warning' : 'badge-danger') ?>">
                                    <?= $rem ?> days
                                </span>
                            </td>
                            <td style="min-width: 140px;">
                                <div class="balance-meter" role="progressbar" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100">
                                    <div class="balance-fill" style="width: <?= $pct ?>%; background-color: <?= $pct > 80 ? 'var(--color-error)' : ($pct > 50 ? 'var(--color-warning)' : 'var(--color-primary)') ?>;"></div>
                                </div>
                                <span style="font-size: 0.7rem; color: var(--color-text-muted);"><?= $pct ?>% utilized</span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
