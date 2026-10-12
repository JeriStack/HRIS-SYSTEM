<?php
/**
 * Employee Personal Profile View (Self-Service)
 * Employee HRIS & Leave Management System
 */

$empNo = $profile['employee_no'] ?? 'N/A';
$fullName = ($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? '');
$status = $profile['employment_status'] ?? 'Active';
?>
<div class="page-header">
    <div class="page-title-group">
        <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.35rem;">
            <h1>My Employee Profile</h1>
            <span class="role-badge emp">EMPLOYEE RECORD</span>
        </div>
        <p>Personal employment record and organizational assignment details.</p>
    </div>
    <div class="page-actions">
        <a href="<?= url('/employee/dashboard') ?>" class="btn btn-secondary btn-sm">
            <span>←</span> Back to Dashboard
        </a>
        <a href="<?= url('/employee/leave-balances') ?>" class="btn btn-primary btn-sm">
            <span>⚖️</span> View Balances
        </a>
    </div>
</div>

<div class="grid grid-cols-3" style="align-items: start;">
    <!-- Profile Card (Left Column) -->
    <div class="card">
        <div class="card-body" style="text-align: center; padding: 2rem 1.5rem;">
            <div style="width: 72px; height: 72px; border-radius: var(--radius-full); background: #334155; color: white; display: inline-flex; align-items: center; justify-content: center; font-size: 1.75rem; font-weight: 700; margin-bottom: 1rem; border: 3px solid #BFDBFE;">
                <?= strtoupper(substr($profile['first_name'] ?? 'E', 0, 1) . substr($profile['last_name'] ?? 'P', 0, 1)) ?>
            </div>
            <h2 style="font-size: 1.25rem; margin-bottom: 0.25rem;"><?= e($fullName) ?></h2>
            <div style="font-size: 0.875rem; color: var(--color-text-muted); margin-bottom: 0.75rem;">
                <?= e($profile['position_name'] ?? 'Position Unassigned') ?>
            </div>
            <div style="margin-bottom: 1rem;">
                <span class="badge <?= $status === 'Active' ? 'badge-success' : 'badge-danger' ?>">
                    <span class="badge-dot" aria-hidden="true"></span>
                    <?= e($status) ?>
                </span>
            </div>
            <div style="font-family: var(--font-mono); font-size: 0.875rem; font-weight: 600; color: var(--color-primary); background: var(--color-bg-muted); padding: 0.35rem 0.75rem; border-radius: var(--radius-md); display: inline-block;">
                <?= e($empNo) ?>
            </div>
        </div>
        <div class="card-footer" style="flex-direction: column; align-items: stretch; gap: 0.5rem; text-align: center;">
            <span style="font-size: 0.75rem; color: var(--color-text-muted);">
                Hire Date: <strong><?= !empty($profile['date_hired']) ? date('F j, Y', strtotime($profile['date_hired'])) : 'Not recorded' ?></strong>
            </span>
        </div>
    </div>

    <!-- Detailed Information Panel (Spans 2 columns) -->
    <div class="card" style="grid-column: span 2;">
        <div class="card-header">
            <h2 class="card-title"><span>📋</span> Personal & Organizational Information</h2>
            <span style="font-size: 0.75rem; color: var(--color-text-muted);">Confidential Personal Record</span>
        </div>
        <div class="card-body">
            <div style="display: grid; grid-template-columns: 180px 1fr; gap: 1rem; padding: 0.75rem 0; border-bottom: 1px solid var(--color-border-subtle);">
                <div style="font-weight: 600; color: var(--color-text-muted); font-size: 0.8125rem;">Employee Number:</div>
                <div style="font-family: var(--font-mono); font-weight: 700; color: var(--color-primary);"><?= e($empNo) ?></div>
            </div>

            <div style="display: grid; grid-template-columns: 180px 1fr; gap: 1rem; padding: 0.75rem 0; border-bottom: 1px solid var(--color-border-subtle);">
                <div style="font-weight: 600; color: var(--color-text-muted); font-size: 0.8125rem;">Email Address:</div>
                <div><?= e($profile['email'] ?? 'Not recorded') ?></div>
            </div>

            <div style="display: grid; grid-template-columns: 180px 1fr; gap: 1rem; padding: 0.75rem 0; border-bottom: 1px solid var(--color-border-subtle);">
                <div style="font-weight: 600; color: var(--color-text-muted); font-size: 0.8125rem;">Department:</div>
                <div><strong><?= e($profile['department_name'] ?? 'Unassigned') ?></strong></div>
            </div>

            <div style="display: grid; grid-template-columns: 180px 1fr; gap: 1rem; padding: 0.75rem 0; border-bottom: 1px solid var(--color-border-subtle);">
                <div style="font-weight: 600; color: var(--color-text-muted); font-size: 0.8125rem;">Job Position:</div>
                <div><?= e($profile['position_name'] ?? 'Unassigned') ?></div>
            </div>

            <div style="display: grid; grid-template-columns: 180px 1fr; gap: 1rem; padding: 0.75rem 0; border-bottom: 1px solid var(--color-border-subtle);">
                <div style="font-weight: 600; color: var(--color-text-muted); font-size: 0.8125rem;">Employment Status:</div>
                <div>
                    <span class="badge <?= $status === 'Active' ? 'badge-success' : 'badge-danger' ?>">
                        <?= e($status) ?>
                    </span>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 180px 1fr; gap: 1rem; padding: 0.75rem 0; border-bottom: 1px solid var(--color-border-subtle);">
                <div style="font-weight: 600; color: var(--color-text-muted); font-size: 0.8125rem;">Contact Phone:</div>
                <div><?= e($profile['phone'] ?? '—') ?></div>
            </div>

            <div style="display: grid; grid-template-columns: 180px 1fr; gap: 1rem; padding: 0.75rem 0;">
                <div style="font-weight: 600; color: var(--color-text-muted); font-size: 0.8125rem;">Permanent Address:</div>
                <div><?= e($profile['address'] ?? '—') ?></div>
            </div>
        </div>
    </div>
</div>
