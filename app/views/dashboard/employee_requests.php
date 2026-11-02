<?php
/**
 * Employee Personal Leave Applications History View
 * Employee HRIS & Leave Management System - Phase 8
 */

$userName = $user['name'] ?? $user['username'] ?? 'Employee';
$empNo = $user['employee_no'] ?? 'EMP-Self';
?>

<div class="page-header">
    <div class="page-title-group">
        <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.35rem; flex-wrap: wrap;">
            <h1>My Leave Requests</h1>
            <span class="role-badge emp" style="font-weight: 700; letter-spacing: 0.05em;">EMPLOYEE SELF-SERVICE</span>
        </div>
        <p>Complete history of your filed leave applications, current status, and adjudication notes &bull; Employee ID: <strong style="color: var(--color-primary); font-family: var(--font-mono);"><?= e($empNo) ?></strong></p>
    </div>
    <div class="page-actions">
        <a href="<?= url('/employee/leave-balances') ?>" class="btn btn-secondary btn-sm">
            <span>⚖️</span> My Leave Balances
        </a>
        <a href="<?= url('/leaves/apply') ?>" class="btn btn-primary btn-sm">
            <span>✍️</span> File New Leave Request
        </a>
    </div>
</div>

<?php if (!empty($historyArray)): ?>
    <!-- Chronological Leave Timeline Display -->
    <div class="card" style="margin-bottom: var(--space-4); padding: 0.85rem 1.25rem; background: var(--color-bg-muted);">
        <div style="font-size: 0.725rem; font-weight: 700; color: var(--color-text-muted); text-transform: uppercase; margin-bottom: 0.5rem;">
            📅 Chronological Leave Timeline (<?= count($historyArray) ?> Record<?= count($historyArray) === 1 ? '' : 's' ?>)
        </div>
        <div style="display: flex; gap: 0.5rem; align-items: center; overflow-x: auto; padding-bottom: 0.25rem;">
            <?php foreach ($historyArray as $idx => $node): ?>
                <div style="background: var(--color-bg-surface); border: 1px solid var(--color-border); padding: 0.35rem 0.65rem; border-radius: var(--radius-md); font-size: 0.8125rem; white-space: nowrap;">
                    <strong style="color: var(--color-primary);">#LR-<?= str_pad((string)$node['id'], 3, '0', STR_PAD_LEFT) ?></strong>
                    <span style="color: var(--color-text-muted); margin-left: 0.25rem;"><?= e($node['leave_name'] ?? 'Leave') ?> (<?= (int)$node['number_of_days'] ?>d)</span>
                </div>
                <?php if ($idx < count($historyArray) - 1): ?>
                    <span style="color: var(--color-text-muted); font-weight: 700;">&rarr;</span>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<!-- Requests Table Card -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">
            <span>📋</span> My Leave Applications History
        </h2>
        <span style="font-size: 0.8125rem; color: var(--color-text-muted);">
            <?= count($requests) ?> Request<?= count($requests) === 1 ? '' : 's' ?> Recorded
        </span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover" aria-label="My Personal Leave Requests">
            <thead>
                <tr>
                    <th>Ref #</th>
                    <th>Leave Classification</th>
                    <th>Start Date</th>
                    <th>End Date</th>
                    <th>Duration</th>
                    <th>Reason / Notes</th>
                    <th>Date Filed</th>
                    <th>Status</th>
                    <th style="text-align: right;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($requests)): ?>
                    <tr>
                        <td colspan="9" class="text-center" style="padding: 3rem; color: var(--color-text-muted);">
                            <div style="font-size: 2.5rem; margin-bottom: var(--space-2);" aria-hidden="true">🏖️</div>
                            <h3 style="font-size: var(--font-size-base); font-weight: 600; color: var(--color-text-main); margin-bottom: var(--space-1);">No Leave Applications Found</h3>
                            <p style="margin: 0 0 var(--space-4) 0; font-size: var(--font-size-sm);">You haven't filed any leave requests yet.</p>
                            <a href="<?= url('/leaves/apply') ?>" class="btn btn-primary btn-sm">File Your First Leave Request</a>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($requests as $r): 
                        $status = $r['status'] ?? 'Pending';
                        $badgeClass = match($status) {
                            'Approved'  => 'badge-success',
                            'Pending'   => 'badge-warning',
                            'Rejected'  => 'badge-danger',
                            'Cancelled' => 'badge-neutral',
                            default     => 'badge-neutral',
                        };
                    ?>
                        <tr>
                            <td>
                                <a href="<?= url('/leaves/' . $r['id']) ?>" style="font-family: var(--font-mono); font-size: 0.8125rem; font-weight: 700; color: var(--color-primary); text-decoration: none;">
                                    #LR-<?= str_pad((string)$r['id'], 3, '0', STR_PAD_LEFT) ?>
                                </a>
                            </td>
                            <td>
                                <strong><?= e($r['leave_name']) ?></strong>
                            </td>
                            <td>
                                <?= date('M d, Y', strtotime($r['start_date'])) ?>
                            </td>
                            <td>
                                <?= date('M d, Y', strtotime($r['end_date'])) ?>
                            </td>
                            <td>
                                <strong><?= (int)$r['number_of_days'] ?></strong> working day<?= (int)$r['number_of_days'] === 1 ? '' : 's' ?>
                            </td>
                            <td style="max-width: 250px;">
                                <span title="<?= e($r['reason']) ?>" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; font-size: 0.8125rem;">
                                    <?= e($r['reason']) ?>
                                </span>
                                <?php if ($status === 'Rejected' && !empty($r['rejection_reason'])): ?>
                                    <div style="font-size: 0.725rem; color: var(--color-danger); margin-top: 3px; font-style: italic;">
                                        Reason: <?= e($r['rejection_reason']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td style="font-size: 0.8125rem; color: var(--color-text-muted);">
                                <?= date('M d, Y', strtotime($r['created_at'])) ?>
                            </td>
                            <td>
                                <span class="badge <?= $badgeClass ?>">
                                    <span class="badge-dot" aria-hidden="true"></span>
                                    <?= e($status) ?>
                                </span>
                            </td>
                            <td style="text-align: right;">
                                <div class="table-actions" style="justify-content: flex-end;">
                                    <a href="<?= url('/leaves/' . $r['id']) ?>" class="btn btn-secondary btn-sm" style="font-size: 0.75rem; padding: 3px 8px;">
                                        View
                                    </a>

                                    <?php if ($status === 'Pending'): ?>
                                        <form action="<?= url('/leaves/' . $r['id'] . '/cancel') ?>" method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to cancel this pending leave request?');">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-danger btn-sm" style="font-size: 0.75rem; padding: 3px 8px;">
                                                Cancel
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
