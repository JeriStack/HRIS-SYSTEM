<?php
/**
 * Employee Dashboard View — "The Executive Ledger" Interactive Workbench
 * Self-service overview: welcome header, unified 4-cell summary strip with live quota meter,
 * leave entitlement ledger cards, filterable recent applications with inline pending cancellation,
 * and interactive alert triage.
 */
$user = (isset($user) && is_array($user)) ? $user : (currentUser() ?: []);
$userName = $user['full_name'] ?? $user['name'] ?? $user['username'] ?? 'Employee';
$empNo = $employee['employee_no'] ?? $user['employee_no'] ?? 'EMP';
$position = $employee['position_name'] ?? $employee['position'] ?? $user['position'] ?? 'Staff Member';
$department = $employee['department_name'] ?? $user['department'] ?? 'General Operations';

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

/** @var array<int, array<string, mixed>> $notifications */
$notifications = (isset($notifications) && is_array($notifications))
    ? $notifications
    : ((isset($recentNotifications) && is_array($recentNotifications)) ? $recentNotifications : []);
$unreadCount = (int)($unreadNotificationsCount ?? 0);

// Aggregate balance totals
$totalAllocated = 0;
$totalUsed = 0;
$totalRemaining = 0;
foreach ($balances as $bal) {
    $totalAllocated += (int)($bal['allocated_days'] ?? 0);
    $totalUsed += (int)($bal['used_days'] ?? 0);
    $totalRemaining += (int)($bal['remaining_days'] ?? 0);
}
$annualUtilizationPct = $totalAllocated > 0 ? min(100, (int)round(($totalUsed / $totalAllocated) * 100)) : 0;

// Detect next upcoming approved leave across all requests & slice recent workbench rows
$upcomingApprovedLeave = null;
$todayDate = date('Y-m-d');
$pendingCount = 0;

foreach ($requests as $req) {
    $reqStatus = $req['status'] ?? 'Pending';
    if ($reqStatus === 'Pending') {
        $pendingCount++;
    }
    if ($reqStatus === 'Approved' && !empty($req['start_date']) && $req['start_date'] >= $todayDate) {
        if ($upcomingApprovedLeave === null || $req['start_date'] < $upcomingApprovedLeave['start_date']) {
            $upcomingApprovedLeave = $req;
        }
    }
}

$recentRequestsSlice = array_slice($requests, 0, 6);
$statusCounts = [
    'all'       => count($recentRequestsSlice),
    'Pending'   => 0,
    'Approved'  => 0,
    'Rejected'  => 0,
    'Cancelled' => 0,
];
foreach ($recentRequestsSlice as $req) {
    $reqStatus = $req['status'] ?? 'Pending';
    if (isset($statusCounts[$reqStatus])) {
        $statusCounts[$reqStatus]++;
    }
}
?>

<!-- Welcome & Identity Header -->
<div class="emp-welcome-bar">
    <div class="emp-welcome-meta page-title-group">
        <h1>Welcome back, <?= e($userName) ?></h1>
        <p class="emp-welcome-sub">
            <span class="emp-id-chip role-badge emp"><?= e($empNo) ?></span>
            <span><?= e($position) ?></span>
            <span aria-hidden="true">&middot;</span>
            <span><?= e($department) ?></span>
            <span aria-hidden="true">&middot;</span>
            <span class="tabular-nums"><?= date('Y') ?> Leave Cycle</span>
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

<?php if ($upcomingApprovedLeave !== null) :
    $upStart = date('M j, Y', strtotime($upcomingApprovedLeave['start_date']));
    $upEnd = !empty($upcomingApprovedLeave['end_date']) ? date('M j, Y', strtotime($upcomingApprovedLeave['end_date'])) : $upStart;
    $upDays = (int)($upcomingApprovedLeave['number_of_days'] ?? $upcomingApprovedLeave['total_days'] ?? 1);
    $upName = $upcomingApprovedLeave['leave_name'] ?? $upcomingApprovedLeave['leave_type_name'] ?? 'Leave';
    ?>
    <div class="emp-upcoming-banner" role="status">
        <div class="emp-upcoming-banner-meta">
            <span class="emp-upcoming-icon" aria-hidden="true"><?= icon('calendar-check', 16) ?></span>
            <span class="emp-upcoming-label">Upcoming approved leave: <strong><?= e($upName) ?></strong></span>
            <span class="emp-meta-divider" aria-hidden="true">&middot;</span>
            <span class="emp-upcoming-dates tabular-nums">
                <?= e($upStart) ?><?= $upStart !== $upEnd ? ' &ndash; ' . e($upEnd) : '' ?> (<?= $upDays ?> working <?= $upDays === 1 ? 'day' : 'days' ?>)
            </span>
        </div>
        <a href="<?= url('/leaves/' . (int)$upcomingApprovedLeave['id']) ?>"
           class="emp-quiet-action"
           aria-label="Details for upcoming <?= e($upName) ?>">
            <span>Details</span>
            <?= icon('chevron-right', 14) ?>
        </a>
    </div>
<?php endif; ?>

<!-- Refined 4-Column Leave & Account KPI Overview -->
<section class="emp-kpi-grid" aria-label="Key Leave & Account Metrics">
    <!-- 1. Available Leave (Informational) -->
    <div class="emp-kpi-card">
        <div class="emp-kpi-header">
            <span class="emp-kpi-label">Available Leave</span>
            <span class="emp-kpi-icon" aria-hidden="true"><?= icon('calendar', 20) ?></span>
        </div>
        <div class="emp-kpi-value-row">
            <span class="emp-kpi-value"><?= $totalRemaining ?></span>
            <span class="emp-kpi-unit"><?= $totalRemaining === 1 ? 'day' : 'days' ?></span>
        </div>
        <div class="emp-kpi-footer">
            <span class="emp-kpi-meta tabular-nums">
                Of <strong><?= $totalAllocated ?></strong> allocated
                <?= $totalAllocated === 1 ? 'day' : 'days' ?> in <?= date('Y') ?>
            </span>
        </div>
    </div>

    <!-- 2. Days Used (Informational with Proportional Utilization Meter) -->
    <div class="emp-kpi-card">
        <div class="emp-kpi-header">
            <span class="emp-kpi-label">Days Used</span>
            <span class="emp-kpi-icon" aria-hidden="true"><?= icon('clock', 20) ?></span>
        </div>
        <div class="emp-kpi-value-row">
            <span class="emp-kpi-value"><?= $totalUsed ?></span>
            <span class="emp-kpi-unit"><?= $totalUsed === 1 ? 'day' : 'days' ?></span>
        </div>
        <div class="emp-kpi-footer">
            <div class="emp-kpi-meter-line">
                <div class="emp-kpi-meter"
                     role="progressbar"
                     aria-valuenow="<?= $annualUtilizationPct ?>"
                     aria-valuemin="0"
                     aria-valuemax="100"
                     aria-label="Annual leave utilization: <?= $annualUtilizationPct ?>% used">
                    <div class="emp-kpi-meter-fill <?= $annualUtilizationPct >= 85 ? 'is-warning' : '' ?>"
                         style="<?= e('width: ' . $annualUtilizationPct . '%;') ?>"></div>
                </div>
                <span class="emp-kpi-meter-pct"><?= $annualUtilizationPct ?>% used</span>
            </div>
        </div>
    </div>

    <!-- 3. Pending Requests (Interactive -> Links to My Leave Requests) -->
    <a href="<?= url('/employee/leave-requests') ?>"
       class="emp-kpi-card emp-kpi-interactive"
       aria-label="Pending Requests: <?= $pendingCount ?> <?= $pendingCount === 1 ? 'request' : 'requests' ?>">
        <div class="emp-kpi-header">
            <span class="emp-kpi-label">Pending Requests</span>
            <span class="emp-kpi-icon <?= $pendingCount > 0 ? 'is-pending' : '' ?>" aria-hidden="true">
                <?= icon('file-text', 20) ?>
            </span>
        </div>
        <div class="emp-kpi-value-row">
            <span class="emp-kpi-value"><?= $pendingCount ?></span>
            <span class="emp-kpi-unit"><?= $pendingCount === 1 ? 'request' : 'requests' ?></span>
        </div>
        <div class="emp-kpi-footer">
            <span class="emp-kpi-action <?= $pendingCount > 0 ? 'is-pending-text' : '' ?>">
                <span><?= $pendingCount > 0 ? 'Awaiting HR review' : 'View request history' ?></span>
                <span class="emp-kpi-arrow" aria-hidden="true">&rarr;</span>
            </span>
        </div>
    </a>

    <!-- 4. Unread Alerts (Interactive -> Links to Notifications Inbox) -->
    <a href="<?= url('/notifications') ?>"
       class="emp-kpi-card emp-kpi-interactive"
       aria-label="Unread Alerts: <?= $unreadCount ?> <?= $unreadCount === 1 ? 'alert' : 'alerts' ?>">
        <div class="emp-kpi-header">
            <span class="emp-kpi-label">Unread Alerts</span>
            <span class="emp-kpi-icon <?= $unreadCount > 0 ? 'is-alert' : '' ?>" aria-hidden="true">
                <?= icon('bell', 20) ?>
            </span>
        </div>
        <div class="emp-kpi-value-row">
            <span class="emp-kpi-value"><?= $unreadCount ?></span>
            <span class="emp-kpi-unit"><?= $unreadCount === 1 ? 'alert' : 'alerts' ?></span>
        </div>
        <div class="emp-kpi-footer">
            <span class="emp-kpi-action">
                <span><?= $unreadCount > 0 ? 'Open alert inbox' : 'All alerts read' ?></span>
                <span class="emp-kpi-arrow" aria-hidden="true">&rarr;</span>
            </span>
        </div>
    </a>
</section>

<!-- Leave Balances Section -->
<div class="card emp-balances-card mb-6">
    <div class="card-header">
        <div class="card-title-group">
            <h2 class="card-title">My Leave Balances</h2>
            <p class="card-subtitle">Available entitlements for the <?= date('Y') ?> leave cycle</p>
        </div>
        <a href="<?= url('/employee/leave-balances') ?>" class="emp-quiet-action">
            <span>Balance details</span>
            <?= icon('chevron-right', 16) ?>
        </a>
    </div>
    <div class="card-body">
        <?php if (!empty($balances)) : ?>
            <div class="emp-category-grid">
                <?php foreach ($balances as $bal) :
                    $rawAllocated = (int)($bal['allocated_days'] ?? 0);
                    $allocated = max(1, $rawAllocated);
                    $used = (int)($bal['used_days'] ?? 0);
                    $remaining = (int)($bal['remaining_days'] ?? 0);
                    $pctUsed = $rawAllocated > 0 ? min(100, (int)round(($used / $allocated) * 100)) : 0;
                    $leaveTypeId = (int)($bal['leave_type_id'] ?? 0);
                    $leaveName = $bal['leave_name'] ?? $bal['leave_type_name'] ?? 'Leave';

                    $barState = '';
                    if ($remaining === 0) {
                        $barState = 'is-exhausted';
                    } elseif ($pctUsed >= 75) {
                        $barState = 'is-warning';
                    }
                    ?>
                    <div class="emp-category-card balance-card <?= $remaining === 0 ? 'is-exhausted' : '' ?>">
                        <div class="emp-category-header">
                            <span class="emp-category-name balance-title"><strong><?= e($leaveName) ?></strong></span>
                            <?php if ($remaining > 0) : ?>
                                <a href="<?= url('/leaves/apply' . ($leaveTypeId > 0 ? '?type=' . $leaveTypeId : '')) ?>"
                                   class="emp-category-apply"
                                   aria-label="Apply for <?= e($leaveName) ?>">
                                    <span>Apply</span>
                                    <span class="emp-category-apply-arrow" aria-hidden="true">&rarr;</span>
                                </a>
                            <?php else : ?>
                                <span class="emp-category-exhausted"
                                      title="Annual quota exhausted for the <?= date('Y') ?> leave cycle">
                                    Exhausted
                                </span>
                            <?php endif; ?>
                        </div>

                        <div class="emp-category-balance-row">
                            <span class="emp-category-balance-val"><?= $remaining ?></span>
                            <span class="emp-category-balance-lbl">
                                <?= $remaining === 1 ? 'day available' : 'days available' ?>
                            </span>
                        </div>

                        <!-- Proportional Progress Meter -->
                        <div class="emp-category-meter"
                             role="progressbar"
                             aria-valuenow="<?= $pctUsed ?>"
                             aria-valuemin="0"
                             aria-valuemax="100"
                             aria-label="<?= e($leaveName) ?> utilization: <?= $used ?> of <?= $rawAllocated ?> days used (<?= $pctUsed ?>%)">
                            <div class="emp-category-meter-fill <?= $barState ?>"
                                 style="<?= e('width: ' . $pctUsed . '%;') ?>"></div>
                        </div>

                        <!-- Ledger Breakdown Figures -->
                        <div class="emp-category-ledger">
                            <span class="emp-ledger-stat">
                                <span class="emp-ledger-label">Allocated:</span>
                                <span class="emp-ledger-val">
                                    <?= $rawAllocated ?> <?= $rawAllocated === 1 ? 'day' : 'days' ?>
                                </span>
                            </span>
                            <span class="emp-ledger-stat">
                                <span class="emp-ledger-label">Used:</span>
                                <span class="emp-ledger-val">
                                    <?= $used ?> <?= $used === 1 ? 'day' : 'days' ?> (<?= $pctUsed ?>%)
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
                <p class="empty-state-desc">Your annual leave allocations have not been initialized yet. Please contact HR if you need assistance.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Main Two-Column Workbench Grid: Recent Requests & Notifications -->
<div class="emp-workspace-grid">
    <!-- Left Column: Interactive Recent Leave Applications Workbench -->
    <div class="card">
        <div class="card-header">
            <div class="card-title-group">
                <h2 class="card-title" id="recentRequestsHeading">Recent Leave Applications</h2>
                <p class="card-subtitle">Filter status or manage pending requests</p>
            </div>
            <a href="<?= url('/employee/leave-requests') ?>" class="emp-quiet-action">
                <span>View all</span>
                <?= icon('chevron-right', 14) ?>
            </a>
        </div>

        <?php if (!empty($recentRequestsSlice)) : ?>
            <div class="emp-filter-toolbar">
                <div class="emp-filter-pills" role="tablist" aria-label="Filter recent leave applications by status">
                    <button type="button" class="emp-filter-pill active" data-dash-req-filter="all" role="tab" aria-selected="true" aria-controls="dashRecentRequestsTable" tabindex="0">
                        <span>All</span>
                        <span class="emp-filter-count"><?= $statusCounts['all'] ?></span>
                    </button>
                    <button type="button" class="emp-filter-pill" data-dash-req-filter="Pending" role="tab" aria-selected="false" aria-controls="dashRecentRequestsTable" tabindex="-1">
                        <span>Pending</span>
                        <span class="emp-filter-count"><?= $statusCounts['Pending'] ?></span>
                    </button>
                    <button type="button" class="emp-filter-pill" data-dash-req-filter="Approved" role="tab" aria-selected="false" aria-controls="dashRecentRequestsTable" tabindex="-1">
                        <span>Approved</span>
                        <span class="emp-filter-count"><?= $statusCounts['Approved'] ?></span>
                    </button>
                    <button type="button" class="emp-filter-pill" data-dash-req-filter="Rejected" role="tab" aria-selected="false" aria-controls="dashRecentRequestsTable" tabindex="-1">
                        <span>Rejected</span>
                        <span class="emp-filter-count"><?= $statusCounts['Rejected'] ?></span>
                    </button>
                    <?php if ($statusCounts['Cancelled'] > 0) : ?>
                        <button type="button" class="emp-filter-pill" data-dash-req-filter="Cancelled" role="tab" aria-selected="false" aria-controls="dashRecentRequestsTable" tabindex="-1">
                            <span>Cancelled</span>
                            <span class="emp-filter-count"><?= $statusCounts['Cancelled'] ?></span>
                        </button>
                    <?php endif; ?>
                </div>
                <div id="dashRequestsFilterStatus" class="sr-only" aria-live="polite"></div>
            </div>
        <?php endif; ?>

        <div class="card-body p-0">
            <?php if (!empty($recentRequestsSlice)) : ?>
                <div class="table-responsive emp-requests-table-wrap" tabindex="0" role="region" aria-labelledby="recentRequestsHeading">
                    <table class="data-table emp-requests-table" id="dashRecentRequestsTable">
                        <caption class="sr-only">Recent leave applications</caption>
                        <thead>
                            <tr>
                                <th scope="col">Leave Type</th>
                                <th scope="col">Dates</th>
                                <th scope="col">Duration</th>
                                <th scope="col">Status</th>
                                <th scope="col" class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentRequestsSlice as $req) :
                                $reqId = (int)($req['id'] ?? 0);
                                $status = $req['status'] ?? 'Pending';
                                $badgeClass = match ($status) {
                                    'Approved'  => 'badge-success',
                                    'Rejected'  => 'badge-danger',
                                    'Cancelled' => 'badge-neutral',
                                    default     => 'badge-warning',
                                };
                                $startFormatted = !empty($req['start_date']) ? date('M j, Y', strtotime($req['start_date'])) : '—';
                                $endFormatted = !empty($req['end_date']) ? date('M j, Y', strtotime($req['end_date'])) : '—';
                                $days = (int)($req['number_of_days'] ?? $req['total_days'] ?? 1);
                                $reqTypeName = $req['leave_name'] ?? $req['leave_type_name'] ?? 'Leave';
                                $dateRangeLabel = $startFormatted . ($startFormatted !== $endFormatted ? ' – ' . $endFormatted : '');
                                ?>
                                <tr data-dash-req-status="<?= e($status) ?>">
                                    <td data-label="Leave Type">
                                        <div class="emp-req-type-cell">
                                            <div class="font-semibold text-main"><?= e($reqTypeName) ?></div>
                                            <?php if (!empty($req['rejection_reason']) && $status === 'Rejected') : ?>
                                                <div class="emp-rejection-note">Note: <?= e(mb_strimwidth($req['rejection_reason'], 0, 55, '...')) ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td data-label="Dates" class="text-sm tabular-nums whitespace-nowrap">
                                        <?= e($startFormatted) ?>
                                        <?php if ($startFormatted !== $endFormatted) : ?>
                                            <span class="text-muted">&ndash;</span> <?= e($endFormatted) ?>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Duration" class="tabular-nums whitespace-nowrap">
                                        <span class="font-semibold text-main"><?= $days ?></span>
                                        <span class="text-muted text-sm"><?= $days === 1 ? 'day' : 'days' ?></span>
                                    </td>
                                    <td data-label="Status">
                                        <span class="badge <?= $badgeClass ?> badge-dot"><?= e($status) ?></span>
                                    </td>
                                    <td data-label="Actions" class="text-right whitespace-nowrap">
                                        <div class="inline-flex items-center gap-2">
                                            <a href="<?= url('/leaves/' . $reqId) ?>"
                                               class="emp-quiet-action"
                                               aria-label="View <?= e($reqTypeName) ?> request (<?= e($dateRangeLabel) ?>)">
                                                View
                                            </a>
                                            <?php if ($status === 'Pending') : ?>
                                                <button
                                                    type="button"
                                                    class="emp-quiet-action emp-quiet-action-danger js-dash-cancel-leave"
                                                    data-leave-id="<?= $reqId ?>"
                                                    data-leave-type="<?= e($reqTypeName) ?>"
                                                    data-leave-dates="<?= e($dateRangeLabel) ?>"
                                                    aria-label="Cancel <?= e($reqTypeName) ?> request (<?= e($dateRangeLabel) ?>)"
                                                >
                                                    Cancel
                                                </button>
                                            <?php endif; ?>
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
                    <p class="empty-state-desc">No recent applications match the selected status filter.</p>
                </div>
            <?php else : ?>
                <div class="empty-state">
                    <div class="empty-state-icon"><?= icon('file-text', 36) ?></div>
                    <h3 class="empty-state-title">No Leave Applications Yet</h3>
                    <p class="empty-state-desc">When you submit a leave request, its approval progress and details will appear here.</p>
                    <a href="<?= url('/leaves/apply') ?>" class="btn btn-primary btn-sm">
                        <?= icon('plus', 15) ?>
                        <span>File Leave Request</span>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Right Column: Recent Updates & Alert Triage -->
    <div class="card">
        <div class="card-header">
            <div class="card-title-group">
                <h2 class="card-title">Recent Updates</h2>
                <p class="card-subtitle">Approvals &amp; account alerts</p>
            </div>
            <div class="inline-flex items-center gap-2">
                <?php if ($unreadCount > 0) : ?>
                    <form method="POST" action="<?= url('/notifications/mark-all-read') ?>">
                        <?= csrf_field() ?>
                        <button type="submit" class="emp-quiet-action" title="Mark all notifications as read">
                            <span>Mark all read</span>
                        </button>
                    </form>
                <?php endif; ?>
                <a href="<?= url('/notifications') ?>" class="emp-quiet-action">
                    <span>Inbox<?= $unreadCount > 0 ? ' (' . $unreadCount . ')' : '' ?></span>
                    <?= icon('chevron-right', 14) ?>
                </a>
            </div>
        </div>
        <div class="card-body p-0">
            <?php if (!empty($notifications)) : ?>
                <div class="emp-notif-list">
                    <?php foreach ($notifications as $notif) :
                        $notifId = (int)($notif['id'] ?? 0);
                        $isUnread = empty($notif['is_read']);
                        $type = strtolower($notif['type'] ?? 'info');
                        $iconName = match ($type) {
                            'success' => 'check-circle',
                            'warning' => 'alert-triangle',
                            'error', 'danger' => 'x-circle',
                            default   => 'bell',
                        };
                        $iconTone = match ($type) {
                            'success' => 'emp-notif-icon-success',
                            'warning' => 'emp-notif-icon-warning',
                            'error', 'danger' => 'emp-notif-icon-danger',
                            default   => 'emp-notif-icon-info',
                        };
                        $createdStr = !empty($notif['created_at']) ? date('M j, g:i A', strtotime($notif['created_at'])) : '';
                        $notifTitle = $notif['title'] ?? 'System Notification';
                        ?>
                        <div class="emp-notif-item <?= $isUnread ? 'is-unread' : '' ?>">
                            <div class="emp-notif-icon <?= $iconTone ?>" aria-hidden="true">
                                <?= icon($iconName, 16) ?>
                            </div>
                            <div class="emp-notif-content">
                                <div class="emp-notif-title-row">
                                    <span class="emp-notif-title">
                                        <?php if ($isUnread) : ?>
                                            <span class="emp-unread-dot" aria-hidden="true" title="Unread"></span>
                                            <span class="sr-only">Unread: </span>
                                        <?php endif; ?>
                                        <?= e($notifTitle) ?>
                                    </span>
                                    <span class="emp-notif-time"><?= e($createdStr) ?></span>
                                </div>
                                <p class="emp-notif-msg"><?= e($notif['message'] ?? '') ?></p>
                            </div>
                            <?php if ($isUnread && $notifId > 0) : ?>
                                <div class="emp-notif-actions">
                                    <form method="POST" action="<?= url('/notifications/' . $notifId . '/read') ?>">
                                        <?= csrf_field() ?>
                                        <button type="submit"
                                                class="emp-quiet-action"
                                                title="Mark notification as read"
                                                aria-label="Mark read: <?= e($notifTitle) ?>">
                                            Mark read
                                        </button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <div class="empty-state">
                    <div class="empty-state-icon"><?= icon('bell', 32) ?></div>
                    <h3 class="empty-state-title">You're All Caught Up</h3>
                    <p class="empty-state-desc">Notifications about your leave approvals and profile updates will appear here.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Accessible Pending Leave Cancellation Modal -->
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
                Are you sure you want to withdraw your pending <strong id="dashCancelLeaveType">Leave</strong> application for <strong id="dashCancelLeaveDates"></strong>?
            </p>
            <p class="text-xs text-muted">
                This request will be marked as Cancelled and removed from the HR review queue immediately.
            </p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" id="dismissDashCancelModalBtn">Keep Application</button>
            <form method="POST" action="" id="dashCancelLeaveForm">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-danger">Confirm Cancellation</button>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // 1. Inline Status Filter for Recent Leave Applications (with WAI-ARIA keyboard roving tabindex)
    const filterPills = Array.from(document.querySelectorAll('[data-dash-req-filter]'));
    const reqTable = document.getElementById('dashRecentRequestsTable');
    const emptyState = document.getElementById('dashRequestsFilterEmpty');
    const filterStatus = document.getElementById('dashRequestsFilterStatus');

    function activateFilterPill(pill, moveFocus) {
        if (!pill) return;
        const targetStatus = pill.getAttribute('data-dash-req-filter') || 'all';

        filterPills.forEach(function (p) {
            const isTarget = p === pill;
            p.classList.toggle('active', isTarget);
            p.setAttribute('aria-selected', isTarget ? 'true' : 'false');
            p.setAttribute('tabindex', isTarget ? '0' : '-1');
        });

        if (moveFocus) {
            pill.focus();
        }

        if (!reqTable) return;
        const rows = reqTable.querySelectorAll('tbody tr');
        let visibleRows = 0;

        rows.forEach(function (row) {
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
            filterStatus.textContent = 'Showing ' + visibleRows + ' ' + label + (visibleRows === 1 ? ' application.' : ' applications.');
        }
    }

    filterPills.forEach(function (pill, idx) {
        pill.addEventListener('click', function () {
            activateFilterPill(pill, false);
        });

        pill.addEventListener('keydown', function (e) {
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

    // 2. Accessible Pending Leave Cancellation Modal (with focus restoration & focus trap)
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
        if (lastTriggerBtn && typeof lastTriggerBtn.focus === 'function') {
            lastTriggerBtn.focus();
            lastTriggerBtn = null;
        }
    }

    document.querySelectorAll('.js-dash-cancel-leave').forEach(function (btn) {
        btn.addEventListener('click', function () {
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
                if (dismissBtn) {
                    dismissBtn.focus();
                }
            }
        });
    });

    if (cancelForm) {
        cancelForm.addEventListener('submit', function () {
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
        modal.addEventListener('click', function (e) {
            if (e.target === modal) closeDashModal();
        });
    }
    document.addEventListener('keydown', function (e) {
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
});
</script>
