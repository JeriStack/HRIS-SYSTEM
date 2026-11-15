<?php

/**
 * Employee Dashboard View — "The Executive Ledger" Self-Service Workbench
 * Sections (in hierarchy order):
 * 1. Welcome & Identity Header
 * 2. Employee Summary Cards (Available Leave, Days Used, Pending Requests, Unread Alerts)
 * 3. My Leave Balances
 * 4. Recent Leave Requests (max 3, with submission date & Pending-only cancellation)
 * 5. Upcoming Approved Leave (max 3, sorted by nearest start date)
 * 6. Recent Notifications (max 3, with unread indicator & inline mark-read)
 */
$user = (isset($user) && is_array($user)) ? $user : (currentUser() ?: []);
$userName = (string)($user['full_name'] ?? $user['name'] ?? $user['username'] ?? 'Employee');
$empNo = (string)($employee['employee_no'] ?? $user['employee_no'] ?? 'EMP');
$position = (string)($employee['position_name'] ?? $employee['position'] ?? $user['position'] ?? 'Staff Member');
$department = (string)($employee['department_name'] ?? $user['department'] ?? 'General Operations');
$leaveCycleYear = isset($leaveCycleYear) ? (int)$leaveCycleYear : (int)date('Y');
$todayDate = date('Y-m-d');

$defaultErrors = [
    'profile'       => null,
    'balances'      => null,
    'requests'      => null,
    'upcoming'      => null,
    'notifications' => null,
];
$sectionErrors = (isset($sectionErrors) && is_array($sectionErrors))
    ? array_merge($defaultErrors, $sectionErrors)
    : $defaultErrors;

/** @var array<int, array<string, mixed>> $balances */
$balances = (isset($balances) && is_array($balances))
    ? $balances
    : ((isset($leaveBalances) && is_array($leaveBalances)) ? $leaveBalances : []);

/** @var array<int, array<string, mixed>> $requests */
$requests = (isset($requests) && is_array($requests))
    ? $requests
    : ((isset($pendingLeaves) && is_array($pendingLeaves))
        ? $pendingLeaves
        : ((isset($recentRequests) && is_array($recentRequests)) ? $recentRequests : []));

/** @var array<int, array<string, mixed>> $upcomingApprovedLeaves */
if (isset($upcomingApprovedLeaves) && is_array($upcomingApprovedLeaves)) {
    $upcomingApprovedList = $upcomingApprovedLeaves;
} else {
    $upcomingApprovedList = array_values(array_filter(
        $requests,
        static fn(array $req): bool => ($req['status'] ?? '') === 'Approved'
            && !empty($req['start_date'])
            && (string)$req['start_date'] >= $todayDate
    ));
    usort(
        $upcomingApprovedList,
        static fn(array $a, array $b): int => strcmp((string)($a['start_date'] ?? ''), (string)($b['start_date'] ?? ''))
    );
}
$upcomingApprovedSlice = array_slice($upcomingApprovedList, 0, 3);

/** @var array<int, array<string, mixed>> $recentNotifications */
$recentNotifications = (isset($recentNotifications) && is_array($recentNotifications)) ? $recentNotifications : [];
$recentNotificationsSlice = array_slice($recentNotifications, 0, 3);

$unreadCount = max(0, (int)($unreadNotificationsCount ?? 0));

// Combined leave totals strictly from database balance records
$totalAllocated = 0;
$totalUsed = 0;
$totalRemaining = 0;
$balanceCount = count($balances);
foreach ($balances as $bal) {
    $totalAllocated += (int)($bal['allocated_days'] ?? 0);
    $totalUsed += (int)($bal['used_days'] ?? 0);
    $totalRemaining += (int)($bal['remaining_days'] ?? 0);
}

if (isset($pendingRequestsCount)) {
    $pendingCount = max(0, (int)$pendingRequestsCount);
} else {
    $pendingCount = 0;
    foreach ($requests as $req) {
        if (($req['status'] ?? 'Pending') === 'Pending') {
            $pendingCount++;
        }
    }
}

$recentRequestsSlice = array_slice($requests, 0, 3);
$statusCounts = [
    'all'       => count($recentRequestsSlice),
    'Pending'   => 0,
    'Approved'  => 0,
    'Rejected'  => 0,
    'Cancelled' => 0,
];
foreach ($recentRequestsSlice as $req) {
    $reqStatus = (string)($req['status'] ?? 'Pending');
    if (isset($statusCounts[$reqStatus])) {
        $statusCounts[$reqStatus]++;
    }
}

$balancesUnavailable = $sectionErrors['balances'] !== null;
$requestsUnavailable = $sectionErrors['requests'] !== null;
$upcomingUnavailable = $sectionErrors['upcoming'] !== null;
$notificationsUnavailable = $sectionErrors['notifications'] !== null;

$todayImmutable = DateTimeImmutable::createFromFormat('Y-m-d', $todayDate) ?: new DateTimeImmutable('today');
?>

<!-- Section 1: Employee Welcome & Identity Header -->
<div class="emp-welcome-bar">
    <div class="emp-welcome-meta page-title-group">
        <h1>Welcome back, <?= e($userName) ?></h1>
        <p class="emp-welcome-sub">
            <span class="emp-id-chip role-badge emp"><?= e($empNo) ?></span>
            <span><?= e($position) ?></span>
            <span aria-hidden="true">&middot;</span>
            <span><?= e($department) ?></span>
            <span aria-hidden="true">&middot;</span>
            <span class="tabular-nums"><?= $leaveCycleYear ?> Leave Cycle</span>
        </p>
    </div>
    <div class="emp-welcome-actions">
        <a href="<?= url('/employee/profile') ?>" class="btn btn-secondary">
            <?= icon('user', 16) ?>
            <span>My Profile</span>
        </a>
        <a href="<?= url('/leaves/apply') ?>" class="btn btn-primary">
            <?= icon('plus', 16) ?>
            <span>File Leave Request</span>
        </a>
    </div>
</div>

<?php if ($sectionErrors['profile'] !== null) : ?>
    <div class="emp-section-error mb-4" role="status">
        <div class="emp-section-error-body">
            <span class="emp-section-error-icon" aria-hidden="true"><?= icon('alert-triangle', 18) ?></span>
            <div>
                <p class="emp-section-error-title">Profile Refresh Notice</p>
                <p class="emp-section-error-desc"><?= e($sectionErrors['profile']) ?></p>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Section 2: Employee Summary Cards -->
<section class="emp-kpi-grid" aria-label="Employee Leave and Notification Summary">
    <!-- 1. Available Leave -->
    <a href="<?= url('/employee/leave-balances') ?>" class="emp-kpi-card emp-kpi-interactive">
        <div class="emp-kpi-header">
            <span class="emp-kpi-label">Available Leave</span>
            <span class="emp-kpi-icon" aria-hidden="true"><?= icon('calendar', 20) ?></span>
        </div>
        <div class="emp-kpi-value-row">
            <?php if ($balancesUnavailable) : ?>
                <span class="emp-kpi-value">&mdash;</span>
                <span class="emp-kpi-unit">unavailable</span>
            <?php else : ?>
                <span class="emp-kpi-value"><?= $totalRemaining ?></span>
                <span class="emp-kpi-unit"><?= abs($totalRemaining) === 1 ? 'day' : 'days' ?></span>
            <?php endif; ?>
        </div>
    </a>

    <!-- 2. Days Used -->
    <a href="<?= url('/employee/leave-balances') ?>" class="emp-kpi-card emp-kpi-interactive">
        <div class="emp-kpi-header">
            <span class="emp-kpi-label">Days Used</span>
            <span class="emp-kpi-icon" aria-hidden="true"><?= icon('clock', 20) ?></span>
        </div>
        <div class="emp-kpi-value-row">
            <?php if ($balancesUnavailable) : ?>
                <span class="emp-kpi-value">&mdash;</span>
                <span class="emp-kpi-unit">unavailable</span>
            <?php else : ?>
                <span class="emp-kpi-value"><?= $totalUsed ?></span>
                <span class="emp-kpi-unit"><?= abs($totalUsed) === 1 ? 'day' : 'days' ?></span>
            <?php endif; ?>
        </div>
    </a>

    <!-- 3. Pending Requests -->
    <a href="<?= url('/employee/leave-requests?status=Pending') ?>" class="emp-kpi-card emp-kpi-interactive">
        <div class="emp-kpi-header">
            <span class="emp-kpi-label">Pending Requests</span>
            <span class="emp-kpi-icon <?= $pendingCount > 0 ? 'is-pending' : '' ?>" aria-hidden="true">
                <?= icon('file-text', 20) ?>
            </span>
        </div>
        <div class="emp-kpi-value-row">
            <?php if ($requestsUnavailable && !isset($pendingRequestsCount)) : ?>
                <span class="emp-kpi-value">&mdash;</span>
                <span class="emp-kpi-unit">unavailable</span>
            <?php else : ?>
                <span class="emp-kpi-value"><?= $pendingCount ?></span>
                <span class="emp-kpi-unit"><?= $pendingCount === 1 ? 'request' : 'requests' ?></span>
            <?php endif; ?>
        </div>
    </a>

    <!-- 4. Unread Alerts -->
    <a href="<?= url('/notifications') ?>" class="emp-kpi-card emp-kpi-interactive">
        <div class="emp-kpi-header">
            <span class="emp-kpi-label">Unread Alerts</span>
            <span class="emp-kpi-icon <?= $unreadCount > 0 ? 'is-alert' : '' ?>" id="dashKpiUnreadIcon" aria-hidden="true">
                <?= icon('bell', 20) ?>
            </span>
        </div>
        <div class="emp-kpi-value-row">
            <?php if ($notificationsUnavailable) : ?>
                <span class="emp-kpi-value" id="dashKpiUnreadVal">&mdash;</span>
                <span class="emp-kpi-unit" id="dashKpiUnreadUnit">unavailable</span>
            <?php else : ?>
                <span class="emp-kpi-value" id="dashKpiUnreadVal"><?= $unreadCount ?></span>
                <span class="emp-kpi-unit" id="dashKpiUnreadUnit"><?= $unreadCount === 1 ? 'alert' : 'alerts' ?></span>
            <?php endif; ?>
        </div>
    </a>
</section>

<!-- Section 3: My Leave Balances -->
<section class="card emp-balances-card mb-6" aria-labelledby="balancesHeading">
    <div class="card-header">
        <div class="card-title-group">
            <h2 class="card-title" id="balancesHeading">My Leave Balances</h2>
            <p class="card-subtitle">Individual leave-type entitlements for the <?= $leaveCycleYear ?> cycle</p>
        </div>
        <a href="<?= url('/employee/leave-balances') ?>" class="emp-quiet-action">
            <span>Balance details</span>
            <?= icon('chevron-right', 16) ?>
        </a>
    </div>
    <div class="card-body">
        <?php if ($balancesUnavailable) : ?>
            <div class="emp-section-error" role="alert">
                <div class="emp-section-error-body">
                    <span class="emp-section-error-icon" aria-hidden="true"><?= icon('alert-triangle', 18) ?></span>
                    <div>
                        <h3 class="emp-section-error-title">Leave Balances Temporarily Unavailable</h3>
                        <p class="emp-section-error-desc"><?= e($sectionErrors['balances']) ?></p>
                    </div>
                </div>
                <a href="<?= url('/employee/leave-balances') ?>" class="btn btn-secondary btn-sm">Open Balances Page</a>
            </div>
        <?php elseif (!empty($balances)) : ?>
            <div class="emp-balances-summary" role="note" aria-label="Combined leave entitlement summary">
                <span class="emp-balances-summary-total tabular-nums">
                    Combined remaining: <strong><?= $totalRemaining ?> <?= abs($totalRemaining) === 1 ? 'day' : 'days' ?></strong> across <?= $balanceCount ?> leave <?= $balanceCount === 1 ? 'category' : 'categories' ?> (<?= $totalUsed ?> of <?= $totalAllocated ?> allocated used)
                </span>
                <span class="emp-balances-summary-note">
                    Each leave category is tracked separately and does not transfer across types.
                </span>
            </div>

            <div class="emp-category-grid">
                <?php foreach ($balances as $bal) :
                    $rawAllocated = (int)($bal['allocated_days'] ?? 0);
                    $rawUsed = (int)($bal['used_days'] ?? 0);
                    $remaining = array_key_exists('remaining_days', $bal)
                        ? (int)$bal['remaining_days']
                        : ($rawAllocated - $rawUsed);
                    $pctUsed = $rawAllocated > 0
                        ? max(0, min(100, (int)round(($rawUsed / $rawAllocated) * 100)))
                        : 0;
                    $leaveTypeId = (int)($bal['leave_type_id'] ?? 0);
                    $leaveName = (string)($bal['leave_name'] ?? $bal['leave_type_name'] ?? 'Leave');

                    $barState = '';
                    if ($remaining <= 0 && $rawAllocated > 0) {
                        $barState = 'is-exhausted';
                    } elseif ($pctUsed >= 75 && $rawAllocated > 0) {
                        $barState = 'is-warning';
                    }
                ?>
                    <div class="emp-category-card balance-card <?= ($remaining <= 0 && $rawAllocated > 0) ? 'is-exhausted' : '' ?>">
                        <div class="emp-category-header">
                            <span class="emp-category-name balance-title"><strong><?= e($leaveName) ?></strong></span>
                            <?php if ($remaining > 0) : ?>
                                <a href="<?= url('/leaves/apply' . ($leaveTypeId > 0 ? '?type=' . $leaveTypeId : '')) ?>"
                                    class="emp-category-apply"
                                    aria-label="Apply for <?= e($leaveName) ?>">
                                    <span>Apply</span>
                                    <span class="emp-category-apply-arrow" aria-hidden="true">&rarr;</span>
                                </a>
                            <?php elseif ($rawAllocated <= 0) :
                                $eligStatus = (string)($bal['eligibility_status'] ?? 'Available');
                                $zeroBadgeText = match (true) {
                                    $eligStatus === 'CreditedViaVL' || !empty($bal['sil_credited_from_vl']) => 'Via VL',
                                    $eligStatus === 'PendingVerification' => 'Pending SPIC',
                                    $eligStatus === 'Ineligible' => 'Ineligible',
                                    ($bal['entitlement_model'] ?? '') === 'unpaid' => 'Unpaid',
                                    default => 'Unallocated',
                                };
                                $zeroBadgeTitle = match ($zeroBadgeText) {
                                    'Via VL' => 'Article 95 SIL satisfied via equivalent paid Vacation Leave',
                                    'Pending SPIC' => 'Awaiting HR qualification verification before allocation',
                                    'Ineligible' => 'Employee does not meet eligibility criteria for this benefit',
                                    'Unpaid' => 'Unpaid absence category tracked without paid leave credits',
                                    default => 'No annual quota assigned for ' . $leaveCycleYear,
                                };
                            ?>
                                <span class="emp-category-unallocated"
                                    title="<?= e($zeroBadgeTitle) ?>">
                                    <?= e($zeroBadgeText) ?>
                                </span>
                            <?php elseif ($remaining < 0) : ?>
                                <span class="emp-category-exhausted"
                                    title="Used days exceed the <?= $leaveCycleYear ?> annual allocation">
                                    Overdrawn
                                </span>
                            <?php else : ?>
                                <span class="emp-category-exhausted"
                                    title="Annual quota exhausted for the <?= $leaveCycleYear ?> leave cycle">
                                    Exhausted
                                </span>
                            <?php endif; ?>
                        </div>

                        <div class="emp-category-balance-row <?= $remaining < 0 ? 'is-deficit' : '' ?>">
                            <span class="emp-category-balance-val"><?= $remaining ?></span>
                            <span class="emp-category-balance-lbl">
                                <?= abs($remaining) === 1 ? 'day remaining' : 'days remaining' ?>
                            </span>
                        </div>

                        <div class="emp-category-meter"
                            role="progressbar"
                            aria-valuenow="<?= $pctUsed ?>"
                            aria-valuemin="0"
                            aria-valuemax="100"
                            aria-label="<?= e($leaveName) ?> utilization: <?= $rawUsed ?> of <?= $rawAllocated ?> days used (<?= $pctUsed ?>%)">
                            <div class="emp-category-meter-fill <?= $barState ?>"
                                style="<?= e('width: ' . $pctUsed . '%;') ?>"></div>
                        </div>

                        <div class="emp-category-ledger">
                            <span class="emp-ledger-stat">
                                <span class="emp-ledger-label">Allocated:</span>
                                <span class="emp-ledger-val">
                                    <?= $rawAllocated ?> <?= abs($rawAllocated) === 1 ? 'day' : 'days' ?>
                                </span>
                            </span>
                            <span class="emp-ledger-stat">
                                <span class="emp-ledger-label">Used:</span>
                                <span class="emp-ledger-val">
                                    <?= $rawUsed ?> <?= abs($rawUsed) === 1 ? 'day' : 'days' ?> (<?= $pctUsed ?>%)
                                </span>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else : ?>
            <div class="empty-state">
                <div class="empty-state-icon"><?= icon('calendar', 36) ?></div>
                <h3 class="empty-state-title">No Leave Entitlements Assigned</h3>
                <p class="empty-state-desc">Your annual leave allocations for <?= $leaveCycleYear ?> have not been initialized yet. Please contact HR if you need assistance.</p>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- Section 4: Recent Leave Requests -->
<section class="card emp-requests-card mb-6" aria-labelledby="recentRequestsHeading">
    <div class="card-header">
        <div class="card-title-group">
            <h2 class="card-title" id="recentRequestsHeading">Recent Leave Requests</h2>
            <p class="card-subtitle">Your latest leave submissions and approval statuses</p>
        </div>
        <a href="<?= url('/employee/leave-requests') ?>" class="emp-quiet-action">
            <span>View all</span>
            <?= icon('chevron-right', 16) ?>
        </a>
    </div>

    <?php if (!$requestsUnavailable && !empty($recentRequestsSlice)) : ?>
        <div class="emp-filter-toolbar">
            <div class="emp-filter-pills" role="tablist" aria-label="Filter recent leave requests by status">
                <button type="button" class="emp-filter-pill active" data-dash-req-filter="all" role="tab" aria-selected="true" tabindex="0">
                    <span>All</span>
                    <span class="emp-filter-count"><?= $statusCounts['all'] ?></span>
                </button>
                <button type="button" class="emp-filter-pill" data-dash-req-filter="Pending" role="tab" aria-selected="false" tabindex="-1">
                    <span>Pending</span>
                    <span class="emp-filter-count"><?= $statusCounts['Pending'] ?></span>
                </button>
                <button type="button" class="emp-filter-pill" data-dash-req-filter="Approved" role="tab" aria-selected="false" tabindex="-1">
                    <span>Approved</span>
                    <span class="emp-filter-count"><?= $statusCounts['Approved'] ?></span>
                </button>
                <button type="button" class="emp-filter-pill" data-dash-req-filter="Rejected" role="tab" aria-selected="false" tabindex="-1">
                    <span>Rejected</span>
                    <span class="emp-filter-count"><?= $statusCounts['Rejected'] ?></span>
                </button>
                <?php if ($statusCounts['Cancelled'] > 0) : ?>
                    <button type="button" class="emp-filter-pill" data-dash-req-filter="Cancelled" role="tab" aria-selected="false" tabindex="-1">
                        <span>Cancelled</span>
                        <span class="emp-filter-count"><?= $statusCounts['Cancelled'] ?></span>
                    </button>
                <?php endif; ?>
            </div>
            <div id="dashRequestsFilterStatus" class="sr-only" aria-live="polite"></div>
        </div>
    <?php endif; ?>

    <div class="card-body <?= (!$requestsUnavailable && !empty($recentRequestsSlice)) ? 'p-0' : '' ?>">
        <?php if ($requestsUnavailable) : ?>
            <div class="emp-section-error" role="alert">
                <div class="emp-section-error-body">
                    <span class="emp-section-error-icon" aria-hidden="true"><?= icon('alert-triangle', 18) ?></span>
                    <div>
                        <h3 class="emp-section-error-title">Recent Leave Requests Unavailable</h3>
                        <p class="emp-section-error-desc"><?= e($sectionErrors['requests']) ?></p>
                    </div>
                </div>
                <a href="<?= url('/employee/leave-requests') ?>" class="btn btn-secondary btn-sm">Open Requests History</a>
            </div>
        <?php elseif (!empty($recentRequestsSlice)) : ?>
            <div class="table-responsive emp-requests-table-wrap" tabindex="0" role="region" aria-labelledby="recentRequestsHeading">
                <table class="data-table emp-requests-table" id="dashRecentRequestsTable">
                    <caption class="sr-only">Recent leave requests</caption>
                    <thead>
                        <tr>
                            <th scope="col">Leave Type</th>
                            <th scope="col">Dates</th>
                            <th scope="col">Duration</th>
                            <th scope="col">Submitted</th>
                            <th scope="col">Status</th>
                            <th scope="col" class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentRequestsSlice as $req) :
                            $reqId = (int)($req['id'] ?? 0);
                            $status = (string)($req['status'] ?? 'Pending');
                            $badgeClass = match ($status) {
                                'Approved'  => 'badge-success',
                                'Rejected'  => 'badge-danger',
                                'Cancelled' => 'badge-neutral',
                                default     => 'badge-warning',
                            };
                            $startRaw = (string)($req['start_date'] ?? '');
                            $endRaw = (string)($req['end_date'] ?? '');
                            $startFormatted = $startRaw !== '' ? date('M j, Y', strtotime($startRaw)) : '—';
                            $endFormatted = $endRaw !== '' ? date('M j, Y', strtotime($endRaw)) : $startFormatted;
                            $submittedFormatted = !empty($req['created_at'])
                                ? date('M j, Y', strtotime((string)$req['created_at']))
                                : '—';
                            $days = (int)($req['number_of_days'] ?? $req['total_days'] ?? 1);
                            $reqTypeName = (string)($req['leave_name'] ?? $req['leave_type_name'] ?? 'Leave');
                            $dateRangeLabel = $startFormatted . ($startFormatted !== $endFormatted ? ' – ' . $endFormatted : '');
                        ?>
                            <tr data-dash-req-status="<?= e($status) ?>">
                                <td data-label="Leave Type">
                                    <div class="emp-req-type-cell">
                                        <div class="emp-req-type-name"><?= e($reqTypeName) ?></div>
                                        <?php if (!empty($req['rejection_reason']) && $status === 'Rejected') : ?>
                                            <div class="emp-rejection-note">Note: <?= e(mb_strimwidth((string)$req['rejection_reason'], 0, 55, '...')) ?></div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td data-label="Dates" class="emp-req-dates tabular-nums whitespace-nowrap">
                                    <?= e($startFormatted) ?>
                                    <?php if ($startFormatted !== $endFormatted) : ?>
                                        <span class="text-muted">&ndash;</span> <?= e($endFormatted) ?>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Duration" class="emp-req-duration tabular-nums whitespace-nowrap">
                                    <?php $reqIsCalendar = ($req['entitlement_unit'] ?? 'working_days') === 'calendar_days'; ?>
                                    <span class="emp-req-days-val"><?= $days ?></span>
                                    <span class="emp-req-days-unit"><?= $reqIsCalendar ? ($days === 1 ? 'calendar day' : 'calendar days') : ($days === 1 ? 'day' : 'days') ?></span>
                                </td>
                                <td data-label="Submitted" class="emp-req-submitted tabular-nums whitespace-nowrap">
                                    <?= e($submittedFormatted) ?>
                                </td>
                                <td data-label="Status">
                                    <span class="badge <?= $badgeClass ?> badge-dot"><?= e($status) ?></span>
                                </td>
                                <td data-label="Actions" class="text-right whitespace-nowrap">
                                    <div class="emp-req-actions">
                                        <?php if ($status === 'Pending') : ?>
                                            <button
                                                type="button"
                                                class="emp-quiet-action emp-quiet-action-danger js-dash-cancel-leave"
                                                data-leave-id="<?= $reqId ?>"
                                                data-leave-type="<?= e($reqTypeName) ?>"
                                                data-leave-dates="<?= e($dateRangeLabel) ?>"
                                                aria-label="Cancel <?= e($reqTypeName) ?> request (<?= e($dateRangeLabel) ?>)">
                                                Cancel
                                            </button>
                                        <?php endif; ?>
                                        <a href="<?= url('/leaves/' . $reqId) ?>"
                                            class="emp-quiet-action"
                                            aria-label="View <?= e($reqTypeName) ?> request (<?= e($dateRangeLabel) ?>)">
                                            View
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div id="dashRequestsFilterEmpty" class="empty-state" style="display: none;">
                <div class="empty-state-icon"><?= icon('search', 32) ?></div>
                <h3 class="empty-state-title">No Matching Requests</h3>
                <p class="empty-state-desc">No recent requests match the selected status filter.</p>
            </div>
        <?php else : ?>
            <div class="empty-state">
                <div class="empty-state-icon"><?= icon('file-text', 36) ?></div>
                <h3 class="empty-state-title">No Leave Requests Yet</h3>
                <p class="empty-state-desc">When you submit a leave request, its status and details will appear here.</p>
                <a href="<?= url('/leaves/apply') ?>" class="btn btn-primary btn-sm">
                    <?= icon('plus', 15) ?>
                    <span>File Leave Request</span>
                </a>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- Section 5: Upcoming Approved Leave -->
<section class="card emp-upcoming-card mb-6" aria-labelledby="upcomingApprovedHeading">
    <div class="card-header">
        <div class="card-title-group">
            <h2 class="card-title" id="upcomingApprovedHeading">Upcoming Approved Leave</h2>
            <p class="card-subtitle">Scheduled approved time off sorted by nearest start date</p>
        </div>
        <a href="<?= url('/employee/leave-requests?status=Approved') ?>" class="emp-quiet-action">
            <span>View schedule</span>
            <?= icon('chevron-right', 16) ?>
        </a>
    </div>
    <div class="card-body <?= (!$upcomingUnavailable && !empty($upcomingApprovedSlice)) ? 'p-0' : '' ?>">
        <?php if ($upcomingUnavailable) : ?>
            <div class="emp-section-error" role="alert">
                <div class="emp-section-error-body">
                    <span class="emp-section-error-icon" aria-hidden="true"><?= icon('alert-triangle', 18) ?></span>
                    <div>
                        <h3 class="emp-section-error-title">Upcoming Leave Schedule Unavailable</h3>
                        <p class="emp-section-error-desc"><?= e($sectionErrors['upcoming']) ?></p>
                    </div>
                </div>
                <a href="<?= url('/employee/leave-requests?status=Approved') ?>" class="btn btn-secondary btn-sm">View Approved Requests</a>
            </div>
        <?php elseif (!empty($upcomingApprovedSlice)) : ?>
            <div class="emp-upcoming-list">
                <?php foreach ($upcomingApprovedSlice as $up) :
                    $upId = (int)($up['id'] ?? 0);
                    $upType = (string)($up['leave_name'] ?? $up['leave_type_name'] ?? 'Leave');
                    $upStartRaw = (string)($up['start_date'] ?? '');
                    $upEndRaw = (string)($up['end_date'] ?? '');
                    $upStart = $upStartRaw !== '' ? date('M j, Y', strtotime($upStartRaw)) : '—';
                    $upEnd = $upEndRaw !== '' ? date('M j, Y', strtotime($upEndRaw)) : $upStart;
                    $upDays = (int)($up['number_of_days'] ?? $up['total_days'] ?? 1);
                    $timingLabel = '';
                    $startObj = $upStartRaw !== '' ? DateTimeImmutable::createFromFormat('Y-m-d', $upStartRaw) : false;
                    if ($startObj instanceof DateTimeImmutable) {
                        $daysUntil = (int)$todayImmutable->diff($startObj)->format('%r%a');
                        if ($daysUntil === 0) {
                            $timingLabel = 'Starts today';
                        } elseif ($daysUntil === 1) {
                            $timingLabel = 'Starts tomorrow';
                        } elseif ($daysUntil > 1) {
                            $timingLabel = 'In ' . $daysUntil . ' days';
                        }
                    }
                ?>
                    <div class="emp-upcoming-item">
                        <div class="emp-upcoming-item-main">
                            <div class="emp-upcoming-item-icon" aria-hidden="true">
                                <?= icon('calendar-check', 18) ?>
                            </div>
                            <div class="emp-upcoming-item-details">
                                <div class="emp-upcoming-item-title-row">
                                    <span class="emp-upcoming-item-type"><?= e($upType) ?></span>
                                    <span class="badge badge-success badge-dot">Approved</span>
                                    <?php if ($timingLabel !== '') : ?>
                                        <span class="emp-upcoming-item-timing tabular-nums"><?= e($timingLabel) ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="emp-upcoming-item-meta tabular-nums">
                                    <?php $upUnitText = ($up['entitlement_unit'] ?? 'working_days') === 'calendar_days' ? 'calendar' : 'working'; ?>
                                    <span><?= e($upStart) ?><?= $upStart !== $upEnd ? ' &ndash; ' . e($upEnd) : '' ?></span>
                                    <span aria-hidden="true">&middot;</span>
                                    <span><?= $upDays ?> <?= $upUnitText ?> <?= $upDays === 1 ? 'day' : 'days' ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="emp-upcoming-item-actions">
                            <a href="<?= url('/leaves/' . $upId) ?>"
                                class="emp-quiet-action"
                                aria-label="View approved <?= e($upType) ?> leave starting <?= e($upStart) ?>">
                                <span>Details</span>
                                <?= icon('chevron-right', 14) ?>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else : ?>
            <div class="empty-state">
                <div class="empty-state-icon"><?= icon('calendar-check', 36) ?></div>
                <h3 class="empty-state-title">No Upcoming Approved Leave</h3>
                <p class="empty-state-desc">You have no approved time off scheduled for upcoming dates. Pending applications remain under Pending until approved.</p>
                <a href="<?= url('/leaves/apply') ?>" class="btn btn-secondary btn-sm">
                    <?= icon('plus', 15) ?>
                    <span>Plan Time Off</span>
                </a>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- Section 5B: Help & Support Compact Widget -->
<section class="card support-dashboard-widget" aria-labelledby="helpSupportWidgetHeading">
    <div class="card-header">
        <div class="card-header-title">
            <h2 id="helpSupportWidgetHeading" class="card-title">Help &amp; Support</h2>
            <span class="card-subtitle">Submit HR inquiries, technical requests, or confidential workplace complaints</span>
        </div>
        <a href="<?= url('/support') ?>" class="btn btn-secondary btn-sm">
            <span>View My Requests</span>
            <?= icon('arrow-right', 14) ?>
        </a>
    </div>
    <div class="card-body">
        <?php
        $supportSummary = $supportSummary ?? ['open_count' => 0, 'recent_tickets' => []];
        $openSupportCount = (int)($supportSummary['open_count'] ?? 0);
        $recentSupportTickets = is_array($supportSummary['recent_tickets'] ?? null) ? $supportSummary['recent_tickets'] : [];
        ?>
        <div class="support-widget-summary">
            <div class="inline-flex items-center gap-2 flex-wrap">
                <span class="support-widget-count-pill">
                    <?= icon('shield', 14) ?>
                    <span><?= $openSupportCount ?> Open Request<?= $openSupportCount === 1 ? '' : 's' ?></span>
                </span>
                <span class="text-xs text-muted">Need HR help, leave guidance, or confidential grievance support?</span>
            </div>
            <div class="support-widget-actions">
                <a href="<?= url('/support/request') ?>" class="btn btn-primary btn-sm">
                    <?= icon('plus-circle', 14) ?>
                    <span>Submit Request</span>
                </a>
                <a href="<?= url('/support/complaint') ?>" class="btn btn-secondary btn-sm">
                    <?= icon('lock', 14) ?>
                    <span>Submit Complaint</span>
                </a>
            </div>
        </div>

        <?php if (!empty($sectionErrors['support'])): ?>
            <p class="text-sm text-muted"><?= e((string)$sectionErrors['support']) ?></p>
        <?php elseif (empty($recentSupportTickets)): ?>
            <p class="text-sm text-muted" style="margin-bottom: 0;">You have no recent support requests or complaints.</p>
        <?php else: ?>
            <div class="support-recent-list" aria-label="Recent support requests">
                <?php foreach (array_slice($recentSupportTickets, 0, 3) as $stRow):
                    $stStatus = (string)($stRow['status'] ?? 'Submitted');
                    $stBadge = match ($stStatus) {
                        'Resolved', 'Closed'   => 'badge-success',
                        'Waiting for Employee' => 'badge-warning',
                        'Under Review'         => 'badge-info',
                        default                => 'badge-primary',
                    };
                ?>
                    <a href="<?= url('/support/' . (int)$stRow['id']) ?>" class="support-recent-row">
                        <div class="support-recent-main">
                            <div class="support-recent-ref"><?= e((string)$stRow['ticket_ref']) ?> &middot; <?= e((string)$stRow['category']) ?></div>
                            <div class="support-recent-subject"><?= e((string)$stRow['display_subject']) ?></div>
                        </div>
                        <span class="badge <?= $stBadge ?>"><?= e($stStatus) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- Section 6: Recent Notifications -->
<section class="card emp-notifications-card" aria-labelledby="recentNotificationsHeading">
    <div class="card-header">
        <div class="card-title-group">
            <h2 class="card-title" id="recentNotificationsHeading">
                <span>Recent Notifications</span>
                <span class="badge badge-primary emp-notif-unread-badge" id="dashNotifHeaderBadge" <?= $unreadCount > 0 ? '' : ' hidden' ?>>
                    <?= $unreadCount ?> unread
                </span>
            </h2>
            <p class="card-subtitle">Important leave decisions, HR notices, and account updates</p>
        </div>
        <a href="<?= url('/notifications') ?>" class="emp-quiet-action">
            <span>View all</span>
            <?= icon('chevron-right', 16) ?>
        </a>
    </div>
    <div class="card-body <?= (!$notificationsUnavailable && !empty($recentNotificationsSlice)) ? 'p-0' : '' ?>">
        <div id="dashNotifLiveRegion" class="sr-only" aria-live="polite"></div>

        <?php if ($notificationsUnavailable) : ?>
            <div class="emp-section-error" role="alert">
                <div class="emp-section-error-body">
                    <span class="emp-section-error-icon" aria-hidden="true"><?= icon('alert-triangle', 18) ?></span>
                    <div>
                        <h3 class="emp-section-error-title">Notifications Temporarily Unavailable</h3>
                        <p class="emp-section-error-desc"><?= e($sectionErrors['notifications']) ?></p>
                    </div>
                </div>
                <a href="<?= url('/notifications') ?>" class="btn btn-secondary btn-sm">Open Notifications</a>
            </div>
        <?php elseif (!empty($recentNotificationsSlice)) : ?>
            <div class="emp-notif-list" id="dashNotifList">
                <?php foreach ($recentNotificationsSlice as $notif) :
                    $notifId = (int)($notif['id'] ?? 0);
                    $isUnread = empty($notif['is_read']);
                    $notifType = strtolower((string)($notif['type'] ?? 'info'));
                    $iconName = match ($notifType) {
                        'success' => 'check-circle',
                        'warning' => 'alert-triangle',
                        'error', 'danger' => 'x-circle',
                        default   => 'bell',
                    };
                    $iconTone = match ($notifType) {
                        'success' => 'emp-notif-icon-success',
                        'warning' => 'emp-notif-icon-warning',
                        'error', 'danger' => 'emp-notif-icon-danger',
                        default   => 'emp-notif-icon-info',
                    };
                    $createdFormatted = !empty($notif['created_at'])
                        ? date('M j, Y \a\t g:i A', strtotime((string)$notif['created_at']))
                        : '';
                    $notifTitle = (string)($notif['title'] ?? 'Notification');
                    $notifMessage = mb_strimwidth((string)($notif['message'] ?? ''), 0, 180, '...');
                    $destPath = !empty($notif['destination_path']) ? (string)$notif['destination_path'] : null;
                    $destLabel = !empty($notif['destination_label']) ? (string)$notif['destination_label'] : 'Open';
                ?>
                    <div
                        class="emp-notif-item <?= $isUnread ? 'is-unread' : '' ?>"
                        data-notif-id="<?= $notifId ?>"
                        data-read-state="<?= $isUnread ? 'unread' : 'read' ?>">
                        <div class="emp-notif-icon <?= $iconTone ?>" aria-hidden="true">
                            <?= icon($iconName, 18) ?>
                        </div>

                        <div class="emp-notif-content">
                            <div class="emp-notif-title-row">
                                <span class="emp-notif-title">
                                    <?php if ($isUnread) : ?>
                                        <span class="emp-unread-dot" aria-hidden="true" title="Unread notification"></span>
                                        <span class="sr-only">Unread: </span>
                                    <?php endif; ?>
                                    <?= e($notifTitle) ?>
                                </span>
                                <?php if ($createdFormatted !== '') : ?>
                                    <span class="emp-notif-time tabular-nums"><?= e($createdFormatted) ?></span>
                                <?php endif; ?>
                            </div>
                            <p class="emp-notif-msg"><?= e($notifMessage) ?></p>
                        </div>

                        <div class="emp-notif-actions">
                            <?php if ($destPath !== null) : ?>
                                <a href="<?= url($destPath) ?>"
                                    class="emp-quiet-action"
                                    aria-label="<?= e($destLabel . ' for ' . $notifTitle) ?>">
                                    <span><?= e($destLabel) ?></span>
                                    <?= icon('chevron-right', 14) ?>
                                </a>
                            <?php endif; ?>

                            <?php if ($isUnread && $notifId > 0) : ?>
                                <form method="POST"
                                    action="<?= url('/notifications/' . $notifId . '/read') ?>"
                                    class="js-dash-mark-read-form"
                                    data-notif-id="<?= $notifId ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="redirect_to" value="/employee/dashboard">
                                    <button type="submit"
                                        class="btn btn-secondary btn-sm"
                                        title="Mark notification as read"
                                        aria-label="Mark <?= e($notifTitle) ?> as read">
                                        Mark Read
                                    </button>
                                </form>
                            <?php else : ?>
                                <span class="emp-notif-read-pill">Read</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else : ?>
            <div class="empty-state">
                <div class="empty-state-icon"><?= icon('bell', 36) ?></div>
                <h3 class="empty-state-title">No Notifications Yet</h3>
                <p class="empty-state-desc">You are all caught up. Updates regarding your leave requests, approvals, and HR notices will appear here.</p>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- Accessible Pending Leave Cancellation Confirmation Dialog -->
<div class="modal-backdrop" id="dashCancelLeaveModal" role="dialog" aria-modal="true" aria-labelledby="dashCancelModalTitle" aria-hidden="true">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title" id="dashCancelModalTitle">Cancel Pending Leave Request</h2>
            <button type="button" class="modal-close" id="closeDashCancelModalBtn" aria-label="Close dialog">
                <?= icon('x', 18) ?>
            </button>
        </div>
        <div class="modal-body">
            <p class="text-sm text-main mb-3">
                Are you sure you want to withdraw your pending <strong id="dashCancelLeaveType">Leave</strong> request for <strong id="dashCancelLeaveDates"></strong>?
            </p>
            <p class="text-xs text-muted">
                This request will be marked as Cancelled and removed from the HR review queue immediately.
            </p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" id="dismissDashCancelModalBtn">Keep Request</button>
            <form method="POST" action="" id="dashCancelLeaveForm">
                <?= csrf_field() ?>
                <input type="hidden" name="redirect_to" value="/employee/dashboard">
                <button type="submit" class="btn btn-danger">Confirm Cancellation</button>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // 1. Inline Status Filter for Recent Leave Requests (WAI-ARIA roving tabindex)
        const filterPills = Array.from(document.querySelectorAll('[data-dash-req-filter]'));
        const reqTable = document.getElementById('dashRecentRequestsTable');
        const emptyState = document.getElementById('dashRequestsFilterEmpty');
        const filterStatus = document.getElementById('dashRequestsFilterStatus');

        function activateFilterPill(pill, moveFocus) {
            if (!pill) return;
            const targetStatus = pill.getAttribute('data-dash-req-filter') || 'all';

            filterPills.forEach(function(p) {
                const isTarget = p === pill;
                p.classList.toggle('active', isTarget);
                p.setAttribute('aria-selected', isTarget ? 'true' : 'false');
                p.setAttribute('tabindex', isTarget ? '0' : '-1');
            });

            if (moveFocus) {
                pill.focus();
            }
            if (typeof pill.scrollIntoView === 'function') {
                pill.scrollIntoView({
                    block: 'nearest',
                    inline: 'nearest'
                });
            }

            if (!reqTable) return;
            const rows = reqTable.querySelectorAll('tbody tr');
            let visibleRows = 0;

            rows.forEach(function(row) {
                const rowStatus = row.getAttribute('data-dash-req-status') || '';
                if (targetStatus === 'all' || rowStatus === targetStatus) {
                    row.style.display = '';
                    visibleRows++;
                } else {
                    row.style.display = 'none';
                }
            });

            reqTable.hidden = visibleRows === 0;
            if (emptyState) {
                emptyState.style.display = visibleRows === 0 ? 'block' : 'none';
            }
            if (filterStatus) {
                const label = targetStatus === 'all' ? 'all' : targetStatus.toLowerCase();
                filterStatus.textContent = 'Showing ' + visibleRows + ' ' + label + (visibleRows === 1 ? ' request.' : ' requests.');
            }
        }

        filterPills.forEach(function(pill, idx) {
            pill.addEventListener('click', function() {
                activateFilterPill(pill, false);
            });

            pill.addEventListener('keydown', function(e) {
                if (filterPills.length === 0) return;
                let nextIndex = null;
                if (e.key === 'ArrowRight') {
                    nextIndex = (idx + 1) % filterPills.length;
                } else if (e.key === 'ArrowLeft') {
                    nextIndex = (idx - 1 + filterPills.length) % filterPills.length;
                } else if (e.key === 'Home') {
                    nextIndex = 0;
                } else if (e.key === 'End') {
                    nextIndex = filterPills.length - 1;
                }
                if (nextIndex !== null) {
                    e.preventDefault();
                    activateFilterPill(filterPills[nextIndex], true);
                }
            });
        });

        // 2. Accessible Pending Leave Cancellation Confirmation Dialog
        const modal = document.getElementById('dashCancelLeaveModal');
        const cancelForm = document.getElementById('dashCancelLeaveForm');
        const typeLabel = document.getElementById('dashCancelLeaveType');
        const datesLabel = document.getElementById('dashCancelLeaveDates');
        const closeBtn = document.getElementById('closeDashCancelModalBtn');
        const dismissBtn = document.getElementById('dismissDashCancelModalBtn');
        const baseCancelUrl = <?= json_encode(url('/leaves/')) ?>;
        let lastTriggerBtn = null;

        function closeDashModal() {
            if (!modal) return;
            modal.classList.remove('show');
            modal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
            if (lastTriggerBtn && typeof lastTriggerBtn.focus === 'function') {
                lastTriggerBtn.focus();
                lastTriggerBtn = null;
            }
        }

        document.querySelectorAll('.js-dash-cancel-leave').forEach(function(btn) {
            btn.addEventListener('click', function() {
                lastTriggerBtn = btn;
                const leaveId = btn.getAttribute('data-leave-id');
                const leaveType = btn.getAttribute('data-leave-type') || 'Leave';
                const leaveDates = btn.getAttribute('data-leave-dates') || '';

                if (cancelForm && leaveId) {
                    cancelForm.action = baseCancelUrl.replace(/\/$/, '') + '/' + encodeURIComponent(leaveId) + '/cancel';
                }
                if (typeLabel) typeLabel.textContent = leaveType;
                if (datesLabel) datesLabel.textContent = leaveDates;

                if (modal) {
                    modal.classList.add('show');
                    modal.setAttribute('aria-hidden', 'false');
                    document.body.style.overflow = 'hidden';
                    if (dismissBtn) {
                        dismissBtn.focus();
                    }
                }
            });
        });

        if (cancelForm) {
            cancelForm.addEventListener('submit', function() {
                const submitBtn = cancelForm.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.classList.add('is-loading');
                    submitBtn.textContent = 'Cancelling...';
                }
            });
        }

        if (closeBtn) closeBtn.addEventListener('click', closeDashModal);
        if (dismissBtn) dismissBtn.addEventListener('click', closeDashModal);
        if (modal) {
            modal.addEventListener('click', function(e) {
                if (e.target === modal) closeDashModal();
            });
        }
        document.addEventListener('keydown', function(e) {
            if (!modal || !modal.classList.contains('show')) return;
            if (e.key === 'Escape') {
                e.preventDefault();
                closeDashModal();
            } else if (e.key === 'Tab') {
                const focusable = modal.querySelectorAll('button:not([disabled]), [href], input:not([disabled])');
                if (focusable.length === 0) return;
                const first = focusable[0];
                const last = focusable[focusable.length - 1];
                if (e.shiftKey && document.activeElement === first) {
                    e.preventDefault();
                    last.focus();
                } else if (!e.shiftKey && document.activeElement === last) {
                    e.preventDefault();
                    first.focus();
                }
            }
        });

        // 3. Progressive-Enhancement Inline Notification Mark-Read Sync
        function syncUnreadCounts(nextUnread) {
            const count = Math.max(0, Number(nextUnread) || 0);

            const kpiVal = document.getElementById('dashKpiUnreadVal');
            const kpiUnit = document.getElementById('dashKpiUnreadUnit');
            const kpiIcon = document.getElementById('dashKpiUnreadIcon');
            if (kpiVal) kpiVal.textContent = String(count);
            if (kpiUnit) kpiUnit.textContent = count === 1 ? 'alert' : 'alerts';
            if (kpiIcon) kpiIcon.classList.toggle('is-alert', count > 0);

            const headerBadge = document.getElementById('dashNotifHeaderBadge');
            if (headerBadge) {
                headerBadge.textContent = count + ' unread';
                headerBadge.hidden = count === 0;
            }

            const sidebarBadge = document.getElementById('sidebarUnreadBadge');
            if (sidebarBadge) {
                sidebarBadge.textContent = count > 99 ? '99+' : String(count);
                sidebarBadge.hidden = count === 0;
                sidebarBadge.setAttribute('aria-label', count + ' unread notifications');
            }

            const rtCount = document.getElementById('rtCount');
            const rtNotify = document.getElementById('rtNotify');
            const rtBell = document.getElementById('rtBell');
            if (rtCount) {
                rtCount.textContent = count > 99 ? '99+' : String(count);
                rtCount.hidden = count === 0;
            }
            if (rtNotify) {
                rtNotify.setAttribute('data-unread', String(count));
            }
            if (rtBell) {
                rtBell.setAttribute('aria-label', count > 0 ? 'Notifications, ' + count + ' unread' : 'Notifications');
            }
        }

        const notifLiveRegion = document.getElementById('dashNotifLiveRegion');
        document.querySelectorAll('.js-dash-mark-read-form').forEach(function(form) {
            form.addEventListener('submit', function(e) {
                if (typeof window.fetch !== 'function' || typeof window.FormData !== 'function') {
                    return;
                }
                e.preventDefault();

                const submitBtn = form.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.disabled = true;
                }

                fetch(form.action, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: new FormData(form)
                    })
                    .then(function(res) {
                        if (!res.ok) {
                            throw new Error('Request failed');
                        }
                        return res.json();
                    })
                    .then(function(payload) {
                        if (!payload || payload.status !== 'success') {
                            throw new Error('Unexpected response');
                        }

                        const item = form.closest('.emp-notif-item');
                        if (item) {
                            item.classList.remove('is-unread');
                            item.setAttribute('data-read-state', 'read');
                            const dot = item.querySelector('.emp-unread-dot');
                            if (dot) dot.remove();
                            const srUnread = item.querySelector('.emp-notif-title .sr-only');
                            if (srUnread) srUnread.remove();

                            const readBadge = document.createElement('span');
                            readBadge.className = 'emp-notif-read-pill';
                            readBadge.textContent = 'Read';
                            form.replaceWith(readBadge);
                        }

                        const remainingUnread = payload.data && typeof payload.data.unread_count === 'number' ?
                            payload.data.unread_count :
                            0;
                        syncUnreadCounts(remainingUnread);

                        if (notifLiveRegion) {
                            notifLiveRegion.textContent = 'Notification marked as read. ' + remainingUnread + ' unread remaining.';
                        }
                    })
                    .catch(function() {
                        if (submitBtn) {
                            submitBtn.disabled = false;
                        }
                        form.submit();
                    });
            });
        });
    });
</script>