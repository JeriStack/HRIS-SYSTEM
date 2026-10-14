<?php
/**
 * Employee Personal Leave Balances View
 * Employee HRIS & Leave Management System
 */

$userName = $user['name'] ?? $user['username'] ?? 'Employee';
$empNo = $user['employee_no'] ?? 'EMP-Self';
$currentYear = $year ?? (int)date('Y');

$totalAllocated = 0;
$totalUsed = 0;
$totalRemaining = 0;

foreach ($balances as $b) {
    $totalAllocated += (int)($b['allocated_days'] ?? 0);
    $totalUsed += (int)($b['used_days'] ?? 0);
    $totalRemaining += (int)($b['remaining_days'] ?? 0);
}

$overallPct = $totalAllocated > 0 ? min(100, round(($totalUsed / $totalAllocated) * 100)) : 0;
?>

<div class="page-header">
    <div class="page-title-group">
        <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.35rem; flex-wrap: wrap;">
            <h1>My Leave Balances</h1>
            <span class="role-badge emp" style="font-weight: 700; letter-spacing: 0.05em;">EMPLOYEE SELF-SERVICE</span>
        </div>
        <p>Annual leave entitlements, quota usage tracking, and remaining balance for Year <strong><?= $currentYear ?></strong> &bull; Employee ID: <strong style="color: var(--color-primary); font-family: var(--font-mono);"><?= e($empNo) ?></strong></p>
    </div>
    <div class="page-actions">
        <a href="<?= url('/employee/leave-requests') ?>" class="btn btn-secondary btn-sm">
            <span>📋</span> My Leave Requests
        </a>
        <a href="<?= url('/leaves/apply') ?>" class="btn btn-primary btn-sm">
            <span>✍️</span> File Leave Request
        </a>
    </div>
</div>

<!-- High-Level Metric Summary Cards -->
<div class="grid grid-cols-4" style="margin-bottom: var(--space-6);">
    <div class="card stat-card">
        <div class="stat-label">Total Allocated</div>
        <div class="stat-value" style="color: var(--color-text-main);"><?= $totalAllocated ?> <span style="font-size: 0.875rem; font-weight: normal; color: var(--color-text-muted);">Days</span></div>
        <div class="stat-desc">Annual total across all leave types</div>
    </div>
    <div class="card stat-card">
        <div class="stat-label">Days Utilized</div>
        <div class="stat-value" style="color: var(--color-warning);"><?= $totalUsed ?> <span style="font-size: 0.875rem; font-weight: normal; color: var(--color-text-muted);">Days</span></div>
        <div class="stat-desc"><?= $overallPct ?>% of annual allocation consumed</div>
    </div>
    <div class="card stat-card">
        <div class="stat-label">Days Remaining</div>
        <div class="stat-value" style="color: var(--color-success);"><?= $totalRemaining ?> <span style="font-size: 0.875rem; font-weight: normal; color: var(--color-text-muted);">Days</span></div>
        <div class="stat-desc">Available for filing in <?= $currentYear ?></div>
    </div>
    <div class="card stat-card">
        <div class="stat-label">Overall Utilization</div>
        <div class="stat-value" style="color: var(--color-primary);"><?= $overallPct ?>%</div>
        <div class="balance-meter" style="margin-top: 0.5rem; height: 6px;">
            <div class="balance-fill" style="width: <?= $overallPct ?>%; background-color: var(--color-primary);"></div>
        </div>
    </div>
</div>

<!-- Detailed Entitlement Breakdown Cards -->
<div class="card" style="margin-bottom: var(--space-6);">
    <div class="card-header">
        <h2 class="card-title"><span>⚖️</span> Leave Entitlements Breakdown (<?= $currentYear ?>)</h2>
        <span style="font-size: 0.8125rem; color: var(--color-text-muted);">
            Authoritative balance figures derived from database records
        </span>
    </div>
    <div class="card-body">
        <?php if (empty($balances)): ?>
            <div class="empty-state" style="padding: 2.5rem;">
                <div class="empty-state-icon" aria-hidden="true">📅</div>
                <div class="empty-state-title">No Leave Entitlements Found</div>
                <div class="empty-state-text">Your annual leave balances have not yet been initialized for <?= $currentYear ?>. Please contact your Human Resources department.</div>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-3">
                <?php foreach ($balances as $b): 
                    $alloc = (int)($b['allocated_days'] ?? 0);
                    $used = (int)($b['used_days'] ?? 0);
                    $rem = (int)($b['remaining_days'] ?? 0);
                    $pct = $alloc > 0 ? min(100, round(($used / $alloc) * 100)) : 0;
                    $badgeClass = $rem > 3 ? 'badge-success' : ($rem > 0 ? 'badge-warning' : 'badge-danger');
                ?>
                    <div class="balance-card" style="background: #FFFFFF; border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: var(--space-5);">
                        <div class="balance-title" style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: var(--space-3);">
                            <div>
                                <h3 style="font-size: var(--font-size-base); font-weight: 700; color: var(--color-text-main); margin: 0 0 4px 0;">
                                    <?= e($b['leave_name']) ?>
                                </h3>
                                <p style="font-size: 0.75rem; color: var(--color-text-muted); margin: 0; line-height: 1.4;">
                                    <?= e($b['description'] ?? 'Standard leave allocation') ?>
                                </p>
                            </div>
                            <span class="badge <?= $badgeClass ?>" style="font-size: 0.75rem; white-space: nowrap;">
                                <?= $rem ?> Day<?= $rem === 1 ? '' : 's' ?> Left
                            </span>
                        </div>

                        <!-- Progress Meter -->
                        <div style="margin: var(--space-4) 0 var(--space-2) 0;">
                            <div style="display: flex; justify-content: space-between; font-size: 0.75rem; margin-bottom: 4px; color: var(--color-text-muted);">
                                <span>Used: <?= $used ?> of <?= $alloc ?> days</span>
                                <span><?= $pct ?>%</span>
                            </div>
                            <div class="balance-meter" role="progressbar" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100" style="height: 8px; border-radius: var(--radius-full); background: var(--color-slate-100); overflow: hidden;">
                                <div class="balance-fill" style="width: <?= $pct ?>%; height: 100%; border-radius: var(--radius-full); background-color: <?= $rem > 3 ? 'var(--color-primary)' : ($rem > 0 ? 'var(--color-warning)' : 'var(--color-error)') ?>;"></div>
                            </div>
                        </div>

                        <!-- Three Metrics -->
                        <div class="balance-stats" style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 8px; margin-top: var(--space-3); padding-top: var(--space-3); border-top: 1px solid var(--color-border); text-align: center; font-size: 0.8125rem;">
                            <div>
                                <span style="display: block; font-size: 0.7rem; color: var(--color-text-muted); text-transform: uppercase;">Allocated</span>
                                <strong style="font-size: 1.1rem; color: var(--color-text-main);"><?= $alloc ?></strong>
                            </div>
                            <div>
                                <span style="display: block; font-size: 0.7rem; color: var(--color-text-muted); text-transform: uppercase;">Used</span>
                                <strong style="font-size: 1.1rem; color: var(--color-warning);"><?= $used ?></strong>
                            </div>
                            <div>
                                <span style="display: block; font-size: 0.7rem; color: var(--color-text-muted); text-transform: uppercase;">Remaining</span>
                                <strong style="font-size: 1.1rem; color: var(--color-success);"><?= $rem ?></strong>
                            </div>
                        </div>

                        <div style="margin-top: var(--space-4); text-align: right;">
                            <a href="<?= url('/leaves/apply?type=' . $b['leave_type_id']) ?>" class="btn btn-outline btn-sm" style="font-size: 0.75rem; width: 100%; justify-content: center;">
                                Request <?= e($b['leave_name']) ?>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Policy & Business Rules Reference Card -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title"><span>ℹ️</span> Leave Filing Guidelines &amp; Policies</h2>
    </div>
    <div class="card-body" style="font-size: var(--font-size-sm); color: var(--color-text-body); line-height: 1.6;">
        <div class="grid grid-cols-2" style="gap: var(--space-6);">
            <div>
                <h4 style="font-size: var(--font-size-base); font-weight: 600; color: var(--color-text-main); margin-bottom: var(--space-2);">
                    📅 Working Day Computation
                </h4>
                <p>
                    Leave duration is calculated strictly on business days (Monday through Friday). Saturdays, Sundays, and official public holidays do not count against your available leave quota.
                </p>
                <h4 style="font-size: var(--font-size-base); font-weight: 600; color: var(--color-text-main); margin-bottom: var(--space-2); margin-top: var(--space-4);">
                    ⚖️ Balance Verification
                </h4>
                <p>
                    The system automatically validates that you possess sufficient remaining days before filing. Requests that exceed your remaining balance cannot be submitted.
                </p>
            </div>
            <div>
                <h4 style="font-size: var(--font-size-base); font-weight: 600; color: var(--color-text-main); margin-bottom: var(--space-2);">
                    ⏳ Review &amp; Approval Process
                </h4>
                <p>
                    All filed leave requests enter a <em>Pending</em> review queue. Authorized HR personnel and administrators review applications. You will receive an immediate in-system notification when a decision is recorded.
                </p>
                <h4 style="font-size: var(--font-size-base); font-weight: 600; color: var(--color-text-main); margin-bottom: var(--space-2); margin-top: var(--space-4);">
                    🚫 Cancellation Policy
                </h4>
                <p>
                    You may cancel your application at any time while its status remains <em>Pending</em>. Once approved or rejected, status changes require direct coordination with HR.
                </p>
            </div>
        </div>
    </div>
</div>
