<?php
/**
 * Employee Self-Service Portal Dashboard View
 * Employee HRIS & Leave Management System
 */

$userName = $user['name'] ?? $user['username'] ?? 'Employee';
$empNo = $user['employee_no'] ?? 'EMP-Self';
?>
<div class="page-header">
    <div class="page-title-group">
        <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.35rem;">
            <h1>Welcome, <?= e($userName) ?></h1>
            <span class="role-badge emp" style="font-weight: 700; letter-spacing: 0.05em;">EMPLOYEE SELF-SERVICE</span>
        </div>
        <p>Employee Self-Service Portal &bull; Employee ID: <strong style="color: var(--color-primary); font-family: var(--font-mono);"><?= e($empNo) ?></strong></p>
    </div>
    <div class="page-actions">
        <a href="<?= url('/employee/profile') ?>" class="btn btn-secondary btn-sm">
            <span>👤</span> View My Profile
        </a>
        <a href="<?= url('/leaves/apply') ?>" class="btn btn-primary btn-sm">
            <span>✍️</span> File Leave Request
        </a>
    </div>
</div>

<!-- Personal Leave Balances Grid -->
<div class="card" style="margin-bottom: var(--space-6);">
    <div class="card-header">
        <h2 class="card-title"><span>⚖️</span> My Leave Balances (<?= date('Y') ?> Allocation)</h2>
        <a href="<?= url('/employee/leave-balances') ?>" class="btn btn-secondary btn-sm">JSON API View</a>
    </div>
    <div class="card-body">
        <?php if (empty($balances)): ?>
            <div class="empty-state" style="padding: 1.5rem;">
                <div class="empty-state-icon" aria-hidden="true">📅</div>
                <div class="empty-state-title">No Leave Balances Configured</div>
                <div class="empty-state-text">Your annual leave balances have not yet been initialized by HR for the current year.</div>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-3">
                <?php foreach ($balances as $b): 
                    $allocated = (int)($b['allocated_days'] ?? 0);
                    $used = (int)($b['used_days'] ?? 0);
                    $remaining = (int)($b['remaining_days'] ?? 0);
                    $pct = $allocated > 0 ? min(100, round(($used / $allocated) * 100)) : 0;
                ?>
                    <div class="balance-card">
                        <div class="balance-title">
                            <strong><?= e($b['leave_name']) ?></strong>
                            <span class="badge <?= $remaining > 3 ? 'badge-success' : ($remaining > 0 ? 'badge-warning' : 'badge-danger') ?>">
                                <?= $remaining ?> Day<?= $remaining === 1 ? '' : 's' ?> Left
                            </span>
                        </div>
                        <div class="balance-meter" role="progressbar" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100" aria-label="<?= e($b['leave_name']) ?> usage">
                            <div class="balance-fill" style="width: <?= $pct ?>%; background-color: <?= $remaining > 3 ? 'var(--color-primary)' : 'var(--color-warning)' ?>;"></div>
                        </div>
                        <div class="balance-stats">
                            <span>Allocated: <strong><?= $allocated ?></strong></span>
                            <span>Used: <strong><?= $used ?></strong></span>
                            <span>Remaining: <strong><?= $remaining ?></strong></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Recent Personal Leave Requests -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title"><span>📋</span> My Leave Applications & Status</h2>
        <a href="<?= url('/leaves/apply') ?>" class="btn btn-primary btn-sm">
            <span>➕</span> New Request
        </a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover" aria-label="My Recent Leave Applications">
            <thead>
                <tr>
                    <th>Leave Type</th>
                    <th>Start Date</th>
                    <th>End Date</th>
                    <th>Duration</th>
                    <th>Reason / Notes</th>
                    <th>Approval Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($pendingLeaves)): ?>
                    <tr>
                        <td colspan="6" class="text-center" style="padding: 2.5rem; color: var(--color-text-muted);">
                            <div class="empty-state-icon" aria-hidden="true" style="font-size: 2rem; margin-bottom: 0.5rem;">🏖️</div>
                            <div>You have no recent leave applications filed.</div>
                            <div style="margin-top: 0.5rem;">
                                <a href="<?= url('/leaves/apply') ?>" class="btn btn-outline btn-sm">Submit a Leave Request</a>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($pendingLeaves as $req): 
                        $status = $req['status'] ?? 'Pending';
                        $statusBadgeClass = match($status) {
                            'Approved' => 'badge-success',
                            'Pending'  => 'badge-warning',
                            'Rejected' => 'badge-danger',
                            default    => 'badge-neutral',
                        };
                    ?>
                        <tr>
                            <td>
                                <strong><?= e($req['leave_name']) ?></strong>
                            </td>
                            <td style="font-size: 0.8125rem;">
                                <?= date('M j, Y', strtotime($req['start_date'])) ?>
                            </td>
                            <td style="font-size: 0.8125rem;">
                                <?= date('M j, Y', strtotime($req['end_date'])) ?>
                            </td>
                            <td>
                                <strong><?= (int)$req['number_of_days'] ?></strong> Day<?= (int)$req['number_of_days'] === 1 ? '' : 's' ?>
                            </td>
                            <td style="font-size: 0.8125rem; color: var(--color-text-muted); max-width: 250px;">
                                <?= e($req['reason']) ?>
                            </td>
                            <td>
                                <span class="badge <?= $statusBadgeClass ?>">
                                    <span class="badge-dot" aria-hidden="true"></span>
                                    <?= e($status) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>