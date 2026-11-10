<?php
/**
 * File Leave Request View
 * Guided leave application form with real-time working day calculation and balance check.
 */
$preselectedType = isset($_GET['type']) ? (int)$_GET['type'] : 0;
$selectedType = (int)old('leave_type_id', $preselectedType ?: '');

// Build map of remaining balances by leave_type_id
$balanceMap = [];
if (!empty($balances)) {
    foreach ($balances as $b) {
        if (isset($b['leave_type_id'])) {
            $balanceMap[(int)$b['leave_type_id']] = $b;
        }
    }
}
?>

<!-- Page Header -->
<div class="page-header">
    <div class="page-title-group">
        <h1 class="page-title">File Leave Request</h1>
        <p class="page-subtitle">Submit a new leave application for HR review and approval</p>
    </div>
    <div class="page-actions">
        <a href="<?= url('/employee/leave-requests') ?>" class="btn btn-secondary">
            <?= icon('arrow-left', 16) ?>
            <span>My Leave Requests</span>
        </a>
    </div>
</div>

<div class="emp-apply-grid">
    <!-- Left Column: Leave Application Form -->
    <div class="card">
        <div class="card-header">
            <div class="card-title-group">
                <h2 class="card-title">Leave Application Details</h2>
                <p class="card-subtitle">Select your leave category, dates, and reason. Weekends are excluded automatically.</p>
            </div>
        </div>

        <form action="<?= url('/leaves/apply') ?>" method="POST" id="leaveApplicationForm" novalidate>
            <?= csrf_field() ?>

            <div class="card-body">
                <!-- Leave Type Selection -->
                <div class="form-group">
                    <div class="flex justify-between items-center mb-1">
                        <label for="leaveTypeSelect" class="form-label mb-0">
                            Leave Category <span class="required">*</span>
                        </label>
                        <span id="selectedTypeBalanceHint" class="badge badge-primary" style="display: none;" aria-live="polite"></span>
                    </div>
                    <select id="leaveTypeSelect" name="leave_type_id" class="form-control form-select" required>
                        <option value="" disabled <?= $selectedType === 0 ? 'selected' : '' ?>>Select leave category...</option>
                        <?php foreach ($leaveTypes as $type) :
                            $tId = (int)$type['id'];
                            $typeName = $type['leave_name'] ?? $type['name'] ?? 'Leave';
                            $balInfo = $balanceMap[$tId] ?? null;
                            $remDays = $balInfo ? (int)$balInfo['remaining_days'] : (int)($type['default_days'] ?? 0);
                            $allocDays = $balInfo ? (int)($balInfo['allocated_days'] ?? $type['default_days'] ?? 0) : (int)($type['default_days'] ?? 0);
                            ?>
                            <option
                                value="<?= $tId ?>"
                                data-remaining="<?= $remDays ?>"
                                data-balance="<?= $remDays ?>"
                                data-allocated="<?= $allocDays ?>"
                                <?= $selectedType === $tId ? 'selected' : '' ?>
                            >
                                <?= e($typeName) ?> (<?= $remDays ?> <?= $remDays === 1 ? 'day' : 'days' ?> available)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Start & End Dates -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="leaveStartDate" class="form-label">Start Date <span class="required">*</span></label>
                        <input
                            type="date"
                            id="leaveStartDate"
                            name="start_date"
                            class="form-control"
                            required
                            min="<?= date('Y-m-d') ?>"
                            value="<?= e((string)old('start_date')) ?>"
                        >
                        <span class="form-hint">First day of absence</span>
                    </div>

                    <div class="form-group">
                        <label for="leaveEndDate" class="form-label">End Date <span class="required">*</span></label>
                        <input
                            type="date"
                            id="leaveEndDate"
                            name="end_date"
                            class="form-control"
                            required
                            min="<?= date('Y-m-d') ?>"
                            value="<?= e((string)old('end_date')) ?>"
                        >
                        <span class="form-hint">Last day of absence (inclusive)</span>
                    </div>
                </div>

                <!-- Live Working Days Calculator Feedback -->
                <div id="leaveCalcBox" class="emp-calc-box mb-5" role="status" aria-live="polite">
                    <div>
                        <div class="text-xs font-semibold text-muted" style="text-transform: uppercase; letter-spacing: 0.04em;">Calculated Duration</div>
                        <div id="dateValidationError" class="form-error mt-1" role="alert" style="display: none;"></div>
                        <div class="text-xs text-muted mt-1">Excludes Saturdays and Sundays</div>
                    </div>
                    <div id="calculatedDaysDisplay" class="emp-calc-days">0 working days</div>
                </div>

                <!-- Reason for Leave -->
                <div class="form-group mb-0">
                    <label for="leaveReason" class="form-label">Reason for Leave <span class="required">*</span></label>
                    <textarea
                        id="leaveReason"
                        name="reason"
                        class="form-control"
                        rows="4"
                        required
                        placeholder="Provide brief context for your leave request (e.g., family vacation, medical appointment)..."
                    ><?= e((string)old('reason')) ?></textarea>
                </div>
            </div>

            <div class="card-footer flex justify-between items-center">
                <a href="<?= url('/employee/dashboard') ?>" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary" id="submitLeaveBtn">
                    <?= icon('send', 15) ?>
                    <span>Submit Application</span>
                </button>
            </div>
        </form>
    </div>

    <!-- Right Column: Balances & Guidelines -->
    <div class="flex flex-col gap-6">
        <div class="card">
            <div class="card-header">
                <div class="card-title-group">
                    <h2 class="card-title">Available Leave Balances</h2>
                    <p class="card-subtitle"><?= date('Y') ?> entitlement summary</p>
                </div>
            </div>
            <div class="card-body">
                <?php if (!empty($balances)) : ?>
                    <div class="flex flex-col gap-3">
                        <?php foreach ($balances as $bal) :
                            $allocated = max(1, (int)($bal['allocated_days'] ?? 1));
                            $rawAllocated = (int)($bal['allocated_days'] ?? 0);
                            $used = (int)($bal['used_days'] ?? 0);
                            $remaining = (int)($bal['remaining_days'] ?? 0);
                            $pct = min(100, round(($used / $allocated) * 100));
                            $bTypeId = (int)($bal['leave_type_id'] ?? 0);
                            $balName = $bal['leave_name'] ?? $bal['leave_type_name'] ?? 'Leave';
                            ?>
                            <div
                                class="p-3 rounded-lg border"
                                data-balance-type-id="<?= $bTypeId ?>"
                                style="border-color: var(--color-border-light); transition: border-color 0.15s ease, background-color 0.15s ease;"
                            >
                                <div class="flex justify-between items-center mb-1">
                                    <span class="font-semibold text-sm text-main"><?= e($balName) ?></span>
                                    <span class="font-bold text-sm text-primary tabular-nums"><?= $remaining ?> <?= $remaining === 1 ? 'day' : 'days' ?> left</span>
                                </div>
                                <div class="progress-bar mb-1">
                                    <div class="progress-fill <?= $remaining === 0 ? 'progress-danger' : ($pct >= 75 ? 'progress-warning' : '') ?>" style="width: <?= $pct ?>%;"></div>
                                </div>
                                <div class="text-xs text-muted tabular-nums"><?= $used ?> used of <?= $rawAllocated ?> allocated</div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else : ?>
                    <p class="text-sm text-muted">No leave balances initialized for <?= date('Y') ?>.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Filing Guidelines</h2>
            </div>
            <div class="card-body">
                <ul class="flex flex-col gap-3 text-sm text-muted" style="list-style: none; padding: 0; margin: 0;">
                    <li class="flex items-start gap-2">
                        <span class="text-primary mt-1"><?= icon('check-circle', 15) ?></span>
                        <span><strong>Advance Filing:</strong> Submit planned vacation leaves at least 3 working days in advance.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="text-primary mt-1"><?= icon('calendar', 15) ?></span>
                        <span><strong>Overlap Check:</strong> Overlapping pending or approved dates are automatically prevented.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="text-primary mt-1"><?= icon('clock', 15) ?></span>
                        <span><strong>Balance Deduction:</strong> Days are deducted only when your request is approved by HR.</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>
