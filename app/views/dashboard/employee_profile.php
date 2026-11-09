<?php
/**
 * Employee Self-Service: My Profile
 * Structured employment record and personal contact details with zero field duplication.
 */
$user = $user ?? currentUser() ?? [];
$emp = $profile ?? $employee ?? null;

$fullName = $emp
    ? trim(($emp['first_name'] ?? '') . ' ' . ($emp['last_name'] ?? ''))
    : ($user['full_name'] ?? $user['name'] ?? $user['username'] ?? 'Employee');

$initials = 'EM';
if ($emp && !empty($emp['first_name']) && !empty($emp['last_name'])) {
    $initials = strtoupper(substr($emp['first_name'], 0, 1) . substr($emp['last_name'], 0, 1));
} elseif (!empty($fullName)) {
    $initialsParts = explode(' ', $fullName);
    $initials = strtoupper(substr($initialsParts[0], 0, 1) . (isset($initialsParts[1]) ? substr($initialsParts[1], 0, 1) : ''));
}

$status = $emp['employment_status'] ?? $emp['status'] ?? 'Active';
$positionTitle = $emp['position_name'] ?? $emp['position'] ?? 'Staff Member';
$statusBadge = $status === 'Active' ? 'badge-success' : 'badge-neutral';
$hireDateFormatted = (!empty($emp['hire_date'])) ? date('F j, Y', strtotime($emp['hire_date'])) : 'Not specified';
?>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h1 class="page-title">My Profile</h1>
        <p class="page-subtitle">Your official employment record and contact information on file</p>
    </div>
    <div class="page-actions">
        <a href="<?= url('/employee/dashboard') ?>" class="btn btn-secondary">
            <?= icon('arrow-left', 16) ?>
            <span>Back to Dashboard</span>
        </a>
    </div>
</div>

<?php if ($emp) : ?>
    <div class="emp-profile-grid">
        <!-- Left Identity Card -->
        <div class="card emp-identity-card">
            <div class="emp-avatar-lg" aria-hidden="true"><?= e($initials) ?></div>
            <h2 class="emp-identity-name"><?= e($fullName) ?></h2>
            <p class="emp-identity-role"><?= e($positionTitle) ?></p>

            <div class="inline-flex items-center gap-2">
                <span class="badge <?= $statusBadge ?> badge-dot"><?= e($status) ?></span>
                <span class="emp-id-chip"><?= e($emp['employee_no'] ?? 'EMP') ?></span>
            </div>

            <div class="emp-identity-Quicklinks">
                <a href="<?= url('/employee/leave-balances') ?>" class="btn btn-secondary btn-sm w-full justify-between">
                    <span>View Leave Balances</span>
                    <?= icon('chevron-right', 14) ?>
                </a>
                <a href="<?= url('/employee/leave-requests') ?>" class="btn btn-secondary btn-sm w-full justify-between">
                    <span>My Leave Requests</span>
                    <?= icon('chevron-right', 14) ?>
                </a>
            </div>
        </div>

        <!-- Right Details Stack -->
        <div class="flex flex-col gap-6">
            <!-- Employment & Organization Details -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title-group">
                        <h3 class="card-title">Employment &amp; Organization</h3>
                        <p class="card-subtitle">Role classification, department assignment, and tenure</p>
                    </div>
                </div>
                <div class="card-body">
                    <div class="emp-dl-grid">
                        <div class="emp-dl-row">
                            <span class="emp-dl-label">Employee ID</span>
                            <span class="emp-dl-value tabular-nums"><?= e($emp['employee_no'] ?? '—') ?></span>
                        </div>
                        <div class="emp-dl-row">
                            <span class="emp-dl-label">Employment Status</span>
                            <span class="emp-dl-value"><?= e($status) ?></span>
                        </div>
                        <div class="emp-dl-row">
                            <span class="emp-dl-label">Department</span>
                            <span class="emp-dl-value"><?= e($emp['department_name'] ?? 'Unassigned') ?></span>
                        </div>
                        <div class="emp-dl-row">
                            <span class="emp-dl-label">Job Position</span>
                            <span class="emp-dl-value"><?= e($positionTitle) ?></span>
                        </div>
                        <div class="emp-dl-row">
                            <span class="emp-dl-label">Date Hired</span>
                            <span class="emp-dl-value"><?= e($hireDateFormatted) ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Contact Information -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title-group">
                        <h3 class="card-title">Contact Information</h3>
                        <p class="card-subtitle">Primary communication channels and registered address</p>
                    </div>
                </div>
                <div class="card-body">
                    <div class="emp-dl-grid">
                        <div class="emp-dl-row">
                            <span class="emp-dl-label">Work Email</span>
                            <span class="emp-dl-value"><?= e($emp['email'] ?? '—') ?></span>
                        </div>
                        <div class="emp-dl-row">
                            <span class="emp-dl-label">Phone Number</span>
                            <span class="emp-dl-value tabular-nums"><?= e(!empty($emp['phone']) ? $emp['phone'] : 'Not provided') ?></span>
                        </div>
                        <div class="emp-dl-row" style="grid-column: 1 / -1;">
                            <span class="emp-dl-label">Registered Address</span>
                            <span class="emp-dl-value"><?= e(!empty($emp['address']) ? $emp['address'] : 'Not provided') ?></span>
                        </div>
                    </div>
                </div>
                <div class="card-footer flex items-center gap-2 text-xs text-muted">
                    <?= icon('info', 15) ?>
                    <span>Employment and personal records are maintained by HR Administration. Contact your HR representative to request updates to your official record.</span>
                </div>
            </div>
        </div>
    </div>
<?php else : ?>
    <div class="card">
        <div class="card-body">
            <div class="empty-state">
                <div class="empty-state-icon"><?= icon('user', 40) ?></div>
                <h3 class="empty-state-title">Employee Record Not Linked</h3>
                <p class="empty-state-desc">Your user account does not have an active employee profile linked yet. Please contact HR Administration for assistance.</p>
            </div>
        </div>
    </div>
<?php endif; ?>
