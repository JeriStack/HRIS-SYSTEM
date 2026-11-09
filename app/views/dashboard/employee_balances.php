<?php
/**
 * Employee Self-Service: My Leave Balances
 * Clear breakdown of annual entitlements, used days, and remaining days.
 */
$balances = $balances ?? $leaveBalances ?? [];

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

<!-- Page Header -->
<div class="page-header">
    <div>
        <h1 class="page-title">My Leave Balances</h1>
        <p class="page-subtitle">Entitlements and remaining days for the <?= date('Y') ?> calendar year</p>
    </div>
    <div class="page-actions">
        <a href="<?= url('/employee/leave-requests') ?>" class="btn btn-secondary">
            <?= icon('file-text', 16) ?>
            <span>Request History</span>
        </a>
        <a href="<?= url('/leaves/apply') ?>" class="btn btn-primary">
            <?= icon('plus', 16) ?>
            <span>File Leave Request</span>
        </a>
    </div>
</div>

<!-- Summary Strip -->
<section class="emp-summary-strip" aria-label="Annual Leave Totals">
    <div class="emp-summary-cell">
        <span class="emp-summary-label">Total Allocated</span>
        <div class="emp-summary-value-row">
            <span class="emp-summary-value"><?= $totalAllocated ?></span>
            <span class="emp-summary-unit">days</span>
        </div>
        <span class="emp-summary-meta">Across <?= count($balances) ?> leave <?= count($balances) === 1 ? 'category' : 'categories' ?></span>
    </div>

    <div class="emp-summary-cell">
        <span class="emp-summary-label">Days Used</span>
        <div class="emp-summary-value-row">
            <span class="emp-summary-value"><?= $totalUsed ?></span>
            <span class="emp-summary-unit">days approved</span>
        </div>
        <span class="emp-summary-meta">Deducted upon HR approval</span>
    </div>

    <div class="emp-summary-cell">
        <span class="emp-summary-label">Days Available</span>
        <div class="emp-summary-value-row">
            <span class="emp-summary-value"><?= $totalRemaining ?></span>
            <span class="emp-summary-unit">days remaining</span>
        </div>
        <span class="emp-summary-meta">Eligible for new leave filings</span>
    </div>

    <div class="emp-summary-cell">
        <span class="emp-summary-label">Annual Utilization</span>
        <div class="emp-summary-value-row">
            <span class="emp-summary-value"><?= $overallPct ?>%</span>
            <span class="emp-summary-unit">of annual quota</span>
        </div>
        <span class="emp-summary-meta"><?= date('Y') ?> leave period</span>
    </div>
</section>

<!-- Category Breakdown Cards -->
<?php if (!empty($balances)) : ?>
    <div class="emp-entitlement-grid mb-6">
        <?php foreach ($balances as $bal) :
            $allocated = max(1, (int)($bal['allocated_days'] ?? 1));
            $rawAllocated = (int)($bal['allocated_days'] ?? 0);
            $used = (int)($bal['used_days'] ?? 0);
            $remaining = (int)($bal['remaining_days'] ?? 0);
            $pct = min(100, round(($used / $allocated) * 100));
            $leaveTypeId = (int)($bal['leave_type_id'] ?? 0);
            $leaveName = $bal['leave_name'] ?? $bal['leave_type_name'] ?? 'Leave';

            $barClass = '';
            $statusBadgeClass = 'badge-success';
            $statusLabel = 'Available';
            if ($remaining === 0) {
                $barClass = 'progress-danger';
                $statusBadgeClass = 'badge-danger';
                $statusLabel = 'Exhausted';
            } elseif ($pct >= 75) {
                $barClass = 'progress-warning';
                $statusBadgeClass = 'badge-warning';
                $statusLabel = 'Low Balance';
            }
            ?>
            <div class="emp-entitlement-card">
                <div class="emp-entitlement-top">
                    <div>
                        <div class="emp-entitlement-name"><?= e($leaveName) ?></div>
                        <?php if (!empty($bal['description'])) : ?>
                            <p class="emp-entitlement-desc"><?= e($bal['description']) ?></p>
                        <?php endif; ?>
                    </div>
                    <span class="badge <?= $statusBadgeClass ?>"><?= $statusLabel ?></span>
                </div>

                <div class="emp-entitlement-main">
                    <div>
                        <span class="emp-entitlement-avail"><?= $remaining ?></span>
                        <span class="emp-entitlement-avail-label">days available</span>
                    </div>
                    <span class="text-xs text-muted tabular-nums"><?= $used ?> of <?= $rawAllocated ?> used (<?= $pct ?>%)</span>
                </div>

                <div class="progress-bar" role="progressbar" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100" aria-label="<?= e($leaveName) ?> usage">
                    <div class="progress-fill <?= $barClass ?>" style="width: <?= $pct ?>%;"></div>
                </div>

                <div class="emp-entitlement-meta">
                    <span class="text-xs text-muted">Annual Entitlement: <strong class="text-main tabular-nums"><?= $rawAllocated ?> days</strong></span>
                    <?php if ($remaining > 0) : ?>
                        <a href="<?= url('/leaves/apply' . ($leaveTypeId > 0 ? '?type=' . $leaveTypeId : '')) ?>" class="btn btn-secondary btn-sm">
                            <span>Request Leave</span>
                            <?= icon('arrow-right', 14) ?>
                        </a>
                    <?php else : ?>
                        <span class="text-xs text-muted">No days left</span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php else : ?>
    <div class="card mb-6">
        <div class="card-body">
            <div class="empty-state">
                <div class="empty-state-icon"><?= icon('calendar', 40) ?></div>
                <h3 class="empty-state-title">No Leave Entitlements Found</h3>
                <p class="empty-state-desc">Your leave balances for <?= date('Y') ?> have not been configured yet. Please contact HR Administration to initialize your entitlements.</p>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Policy Reference Card -->
<div class="card">
    <div class="card-header">
        <div class="card-title-group">
            <h2 class="card-title">Leave Calculation &amp; Policy Reference</h2>
            <p class="card-subtitle">How your balances are calculated and updated</p>
        </div>
    </div>
    <div class="card-body">
        <div class="emp-policy-grid">
            <div class="emp-policy-item">
                <span class="emp-policy-icon"><?= icon('calendar', 18) ?></span>
                <div>
                    <div class="emp-policy-title">Working Days Only</div>
                    <p class="emp-policy-text">Saturdays and Sundays are automatically excluded when calculating your leave duration.</p>
                </div>
            </div>
            <div class="emp-policy-item">
                <span class="emp-policy-icon"><?= icon('check-circle', 18) ?></span>
                <div>
                    <div class="emp-policy-title">Deduction on Approval</div>
                    <p class="emp-policy-text">Leave days are deducted from your balance once an HR Officer approves your request.</p>
                </div>
            </div>
            <div class="emp-policy-item">
                <span class="emp-policy-icon"><?= icon('clock', 18) ?></span>
                <div>
                    <div class="emp-policy-title">Pending Cancellations</div>
                    <p class="emp-policy-text">You may cancel any pending leave request at any time prior to HR adjudication.</p>
                </div>
            </div>
            <div class="emp-policy-item">
                <span class="emp-policy-icon"><?= icon('shield', 18) ?></span>
                <div>
                    <div class="emp-policy-title">Balance Adjustments</div>
                    <p class="emp-policy-text">If you notice a discrepancy in your allocated days, please reach out to HR Administration.</p>
                </div>
            </div>
        </div>
    </div>
</div>
