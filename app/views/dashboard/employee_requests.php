<?php
/**
 * Employee Self-Service: My Leave Requests
 * Filterable table of leave applications with status badges, details view, and safe cancellation modal.
 */
$requests = $requests ?? $leaveRequests ?? [];

$counts = [
    'all'       => count($requests),
    'Pending'   => 0,
    'Approved'  => 0,
    'Rejected'  => 0,
    'Cancelled' => 0,
];
foreach ($requests as $r) {
    $st = $r['status'] ?? 'Pending';
    if (isset($counts[$st])) {
        $counts[$st]++;
    }
}
?>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h1 class="page-title">My Leave Requests</h1>
        <p class="page-subtitle">Track approval status, review notes, and history of your leave applications</p>
    </div>
    <div class="page-actions">
        <a href="<?= url('/employee/leave-balances') ?>" class="btn btn-secondary">
            <?= icon('calendar-check', 16) ?>
            <span>Leave Balances</span>
        </a>
        <a href="<?= url('/leaves/apply') ?>" class="btn btn-primary">
            <?= icon('plus', 16) ?>
            <span>File Leave Request</span>
        </a>
    </div>
</div>

<!-- Requests Table Card -->
<div class="card">
    <!-- Filter & Search Toolbar -->
    <div class="emp-filter-toolbar">
        <div class="emp-filter-pills" role="tablist" aria-label="Filter leave requests by status">
            <button type="button" class="emp-filter-pill active" data-status-filter="all" role="tab" aria-selected="true">
                <span>All</span>
                <span class="emp-filter-count"><?= $counts['all'] ?></span>
            </button>
            <button type="button" class="emp-filter-pill" data-status-filter="Pending" role="tab" aria-selected="false">
                <span>Pending</span>
                <span class="emp-filter-count"><?= $counts['Pending'] ?></span>
            </button>
            <button type="button" class="emp-filter-pill" data-status-filter="Approved" role="tab" aria-selected="false">
                <span>Approved</span>
                <span class="emp-filter-count"><?= $counts['Approved'] ?></span>
            </button>
            <button type="button" class="emp-filter-pill" data-status-filter="Rejected" role="tab" aria-selected="false">
                <span>Rejected</span>
                <span class="emp-filter-count"><?= $counts['Rejected'] ?></span>
            </button>
            <button type="button" class="emp-filter-pill" data-status-filter="Cancelled" role="tab" aria-selected="false">
                <span>Cancelled</span>
                <span class="emp-filter-count"><?= $counts['Cancelled'] ?></span>
            </button>
        </div>

        <div class="emp-search-box">
            <span class="emp-search-icon"><?= icon('search', 15) ?></span>
            <input
                type="search"
                id="leaveRequestSearch"
                class="form-control"
                placeholder="Search type, dates, or reason..."
                aria-label="Search leave requests"
            >
        </div>
    </div>

    <div class="card-body p-0">
        <?php if (!empty($requests)) : ?>
            <div class="table-responsive">
                <table class="data-table" id="employeeRequestsTable">
                    <thead>
                        <tr>
                            <th>Ref #</th>
                            <th>Leave Type</th>
                            <th>Dates</th>
                            <th>Duration</th>
                            <th>Reason &amp; Review Notes</th>
                            <th>Date Filed</th>
                            <th>Status</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requests as $req) :
                            $id = (int)$req['id'];
                            $status = $req['status'] ?? 'Pending';
                            $badgeClass = match ($status) {
                                'Approved'  => 'badge-success',
                                'Rejected'  => 'badge-danger',
                                'Cancelled' => 'badge-neutral',
                                default     => 'badge-warning',
                            };
                            $startStr = !empty($req['start_date']) ? date('M j, Y', strtotime($req['start_date'])) : '—';
                            $endStr = !empty($req['end_date']) ? date('M j, Y', strtotime($req['end_date'])) : '—';
                            $filedStr = !empty($req['created_at']) ? date('M j, Y', strtotime($req['created_at'])) : '—';
                            $days = (int)($req['number_of_days'] ?? $req['total_days'] ?? 1);
                            $leaveName = $req['leave_name'] ?? $req['leave_type_name'] ?? 'Leave';
                            ?>
                            <tr data-request-status="<?= e($status) ?>">
                                <td class="text-muted text-xs font-semibold tabular-nums">#<?= $id ?></td>
                                <td>
                                    <span class="font-semibold text-main"><?= e($leaveName) ?></span>
                                    <?php if (!empty($req['classification']) || !empty($req['is_confidential']) || (($req['entitlement_unit'] ?? 'working_days') === 'calendar_days')): ?>
                                        <div style="display: flex; flex-wrap: wrap; gap: 0.25rem; margin-top: 0.25rem;">
                                            <?php if (!empty($req['classification'])): ?>
                                                <span class="badge <?= $req['classification'] === 'Statutory' ? 'badge-statutory' : 'badge-company' ?>" style="font-size: 0.6875rem;">
                                                    <?= e((string)$req['classification']) ?>
                                                </span>
                                            <?php endif; ?>
                                            <?php if (($req['entitlement_unit'] ?? 'working_days') === 'calendar_days'): ?>
                                                <span class="badge badge-info" style="font-size: 0.6875rem;">Calendar Days</span>
                                            <?php endif; ?>
                                            <?php if (isset($req['is_paid']) && (int)$req['is_paid'] === 0): ?>
                                                <span class="badge badge-unpaid" style="font-size: 0.6875rem;">Unpaid</span>
                                            <?php endif; ?>
                                            <?php if (!empty($req['is_confidential'])): ?>
                                                <span class="badge badge-confidential" style="font-size: 0.6875rem;">Confidential</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-sm tabular-nums">
                                    <div><?= e($startStr) ?></div>
                                    <?php if ($startStr !== $endStr) : ?>
                                        <div class="text-xs text-muted">to <?= e($endStr) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="tabular-nums">
                                    <span class="font-semibold"><?= $days ?></span>
                                    <span class="text-xs text-muted"><?= $days === 1 ? 'day' : 'days' ?></span>
                                </td>
                                <td style="max-width: 280px;">
                                    <div class="text-sm text-main" style="word-break: break-word;">
                                        <?= e(mb_strimwidth($req['reason'] ?? '—', 0, 85, '...')) ?>
                                    </div>
                                    <?php if (!empty($req['rejection_reason']) && $status === 'Rejected') : ?>
                                        <div class="text-xs text-danger mt-1">
                                            <strong>HR Note:</strong> <?= e($req['rejection_reason']) ?>
                                        </div>
                                    <?php elseif (!empty($req['approver_name']) && $status === 'Approved') : ?>
                                        <div class="text-xs text-muted mt-1">
                                            Approved by <?= e($req['approver_name']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-xs text-muted tabular-nums"><?= e($filedStr) ?></td>
                                <td>
                                    <span class="badge <?= $badgeClass ?> badge-dot"><?= e($status) ?></span>
                                    <?php if (($req['info_request_status'] ?? 'None') === 'Requested'): ?>
                                        <div style="margin-top: 0.25rem;"><span class="badge badge-danger" style="font-size: 0.6875rem;">Clarification Needed</span></div>
                                    <?php elseif (($req['info_request_status'] ?? 'None') === 'Responded'): ?>
                                        <div style="margin-top: 0.25rem;"><span class="badge badge-info" style="font-size: 0.6875rem;">Clarification Sent</span></div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-right">
                                    <div class="inline-flex gap-2">
                                        <a href="<?= url('/leaves/' . $id) ?>" class="btn btn-secondary btn-sm">
                                            View
                                        </a>
                                        <?php if ($status === 'Pending') : ?>
                                            <button
                                                type="button"
                                                class="btn btn-outline-danger btn-sm js-cancel-leave-btn"
                                                data-leave-id="<?= $id ?>"
                                                data-leave-type="<?= e($leaveName) ?>"
                                                data-leave-dates="<?= e($startStr . ($startStr !== $endStr ? ' – ' . $endStr : '')) ?>"
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

            <!-- Filtered Empty State -->
            <div id="requestsFilterEmpty" class="empty-state" style="display: none;">
                <div class="empty-state-icon"><?= icon('search', 32) ?></div>
                <h3 class="empty-state-title">No Matching Leave Requests</h3>
                <p class="empty-state-desc">No applications match your selected status filter or search query.</p>
            </div>
        <?php else : ?>
            <div class="empty-state">
                <div class="empty-state-icon"><?= icon('file-text', 40) ?></div>
                <h3 class="empty-state-title">No Leave Requests Submitted</h3>
                <p class="empty-state-desc">You have not filed any leave applications yet. Click below to submit your first request.</p>
                <a href="<?= url('/leaves/apply') ?>" class="btn btn-primary">
                    <?= icon('plus', 16) ?>
                    <span>File Leave Request</span>
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Accessible Cancellation Confirmation Modal -->
<div class="modal-backdrop" id="cancelLeaveModal" role="dialog" aria-modal="true" aria-labelledby="cancelModalTitle" aria-hidden="true">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title" id="cancelModalTitle">Cancel Leave Request</h2>
            <button type="button" class="modal-close" id="closeCancelModalBtn" aria-label="Close dialog">
                <?= icon('x', 18) ?>
            </button>
        </div>
        <div class="modal-body">
            <p class="text-sm text-main mb-3">
                Are you sure you want to cancel your pending <strong id="cancelModalLeaveType">Leave</strong> request for <strong id="cancelModalLeaveDates"></strong>?
            </p>
            <p class="text-xs text-muted">
                This action will mark the request as Cancelled and remove it from the HR approval queue.
            </p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" id="dismissCancelModalBtn">Keep Request</button>
            <form method="POST" action="" id="cancelLeaveForm" style="display: inline;">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-danger">Confirm Cancellation</button>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const pills = document.querySelectorAll('[data-status-filter]');
    const searchInput = document.getElementById('leaveRequestSearch');
    const table = document.getElementById('employeeRequestsTable');
    const emptyNotice = document.getElementById('requestsFilterEmpty');
    const allowedStatuses = ['all', 'Pending', 'Approved', 'Rejected', 'Cancelled'];
    const initialFromServer = <?= json_encode($initialStatus ?? 'all') ?>;
    let activeStatus = allowedStatuses.includes(initialFromServer) ? initialFromServer : 'all';

    function syncFilterPills() {
        pills.forEach(function (p) {
            const isActive = (p.getAttribute('data-status-filter') || 'all') === activeStatus;
            p.classList.toggle('active', isActive);
            p.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });
    }

    function filterRows() {
        if (!table) return;
        const rows = table.querySelectorAll('tbody tr');
        const query = (searchInput ? searchInput.value : '').trim().toLowerCase();
        let visible = 0;

        rows.forEach(function (row) {
            const rowStatus = row.getAttribute('data-request-status') || '';
            const matchesStatus = (activeStatus === 'all') || (rowStatus === activeStatus);
            const matchesQuery = !query || row.textContent.toLowerCase().includes(query);

            if (matchesStatus && matchesQuery) {
                row.style.display = '';
                visible++;
            } else {
                row.style.display = 'none';
            }
        });

        if (emptyNotice) {
            emptyNotice.style.display = visible === 0 ? 'block' : 'none';
        }
    }

    pills.forEach(function (pill) {
        pill.addEventListener('click', function () {
            activeStatus = pill.getAttribute('data-status-filter') || 'all';
            syncFilterPills();
            filterRows();
        });
    });

    if (activeStatus !== 'all') {
        syncFilterPills();
        filterRows();
    }

    if (searchInput) {
        searchInput.addEventListener('input', filterRows);
    }

    // Cancellation Modal
    const modal = document.getElementById('cancelLeaveModal');
    const cancelForm = document.getElementById('cancelLeaveForm');
    const typeSpan = document.getElementById('cancelModalLeaveType');
    const datesSpan = document.getElementById('cancelModalLeaveDates');
    const closeBtn = document.getElementById('closeCancelModalBtn');
    const dismissBtn = document.getElementById('dismissCancelModalBtn');
    const baseCancelUrl = <?= json_encode(url('/leaves/')) ?>;

    function closeModal() {
        if (!modal) return;
        modal.classList.remove('show');
        modal.setAttribute('aria-hidden', 'true');
    }

    document.querySelectorAll('.js-cancel-leave-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const leaveId = btn.getAttribute('data-leave-id');
            const leaveType = btn.getAttribute('data-leave-type') || 'Leave';
            const leaveDates = btn.getAttribute('data-leave-dates') || '';

            if (cancelForm) {
                cancelForm.action = baseCancelUrl.replace(/\/$/, '') + '/' + encodeURIComponent(leaveId) + '/cancel';
            }
            if (typeSpan) typeSpan.textContent = leaveType;
            if (datesSpan) datesSpan.textContent = leaveDates;

            if (modal) {
                modal.classList.add('show');
                modal.setAttribute('aria-hidden', 'false');
            }
        });
    });

    if (closeBtn) closeBtn.addEventListener('click', closeModal);
    if (dismissBtn) dismissBtn.addEventListener('click', closeModal);
    if (modal) {
        modal.addEventListener('click', function (e) {
            if (e.target === modal) closeModal();
        });
    }
});
</script>
