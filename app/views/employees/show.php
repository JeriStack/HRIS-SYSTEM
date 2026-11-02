<?php
$fullName = trim("{$employee['first_name']} " . ($employee['middle_name'] ? "{$employee['middle_name']} " : "") . "{$employee['last_name']}");
$initials = strtoupper(substr($employee['first_name'], 0, 1) . substr($employee['last_name'], 0, 1));

// Calculate Age and Tenure
$birthDate = new DateTime($employee['birth_date']);
$now = new DateTime();
$age = $birthDate->diff($now)->y;

$hiredDate = new DateTime($employee['date_hired']);
$tenure = $hiredDate->diff($now);
$tenureStr = "{$tenure->y} yr(s), {$tenure->m} mo(s)";
?>

<div style="margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
    <div>
        <a href="<?= url('/hr/employees') ?>" style="text-decoration: none; color: #4F46E5; font-size: 0.875rem; font-weight: 600; display: inline-flex; align-items: center; gap: 0.25rem;">
            ← Back to Employee Directory
        </a>
        <h1 style="font-size: 1.5rem; font-weight: 700; color: #1E293B; margin: 0.5rem 0 0.25rem 0;">Employee Profile</h1>
        <p style="color: #64748B; margin: 0; font-size: 0.875rem;">Comprehensive workforce record and identity details.</p>
    </div>
    <div class="action-btns">
        <a href="<?= url('/hr/employees/' . $employee['id'] . '/edit') ?>" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 0.4rem;">
            <span>✏️</span>
            <span>Edit Employee</span>
        </a>
        <?php if ($employee['employment_status'] === 'Active'): ?>
            <button type="button" 
                    class="btn btn-danger" 
                    onclick="openModal('deactivateModal')"
                    style="display: inline-flex; align-items: center; gap: 0.4rem;">
                <span>🛑</span>
                <span>Deactivate</span>
            </button>
        <?php else: ?>
            <button type="button"
                    class="btn btn-primary"
                    onclick="openModal('reactivateModal')"
                    style="display: inline-flex; align-items: center; gap: 0.4rem;">
                <span>✅</span>
                <span>Reactivate</span>
            </button>
        <?php endif; ?>
    </div>
</div>

<!-- Header Card -->
<div class="card" style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 0.75rem; padding: 1.75rem; margin-bottom: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1.25rem;">
    <div style="display: flex; align-items: center; gap: 1.25rem;">
        <div style="width: 64px; height: 64px; border-radius: 50%; background: #4F46E5; color: white; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; font-weight: bold; box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.3);">
            <?= e($initials) ?>
        </div>
        <div>
            <h2 style="font-size: 1.35rem; font-weight: 700; color: #1E293B; margin: 0 0 0.25rem 0;">
                <?= e($fullName) ?>
            </h2>
            <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
                <code style="font-weight: 600; background: #EEF2FF; color: #4F46E5; padding: 0.2rem 0.5rem; border-radius: 0.25rem; font-size: 0.8125rem;">
                    <?= e($employee['employee_no']) ?>
                </code>
                <span style="color: #64748B; font-size: 0.875rem;">•</span>
                <span style="font-weight: 500; color: #334155; font-size: 0.875rem;"><?= e($employee['position_name'] ?? 'Unassigned Role') ?></span>
                <span style="color: #64748B; font-size: 0.875rem;">•</span>
                <span style="color: #64748B; font-size: 0.875rem;"><?= e($employee['department_name'] ?? 'Unassigned Dept') ?></span>
            </div>
        </div>
    </div>
    <div>
        <?php if ($employee['employment_status'] === 'Active'): ?>
            <span class="badge badge-success" style="font-size: 0.875rem; padding: 0.4rem 0.85rem;">Active Employment</span>
        <?php else: ?>
            <span class="badge badge-danger" style="font-size: 0.875rem; padding: 0.4rem 0.85rem;">Inactive Employment</span>
        <?php endif; ?>
    </div>
</div>

<!-- Details Split Grid -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem;">
    <!-- Personal Information Card -->
    <div class="card" style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 0.75rem; padding: 1.75rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
        <h3 style="font-size: 1.1rem; font-weight: 600; color: #1E293B; margin: 0 0 1.25rem 0; padding-bottom: 0.75rem; border-bottom: 1px solid #F1F5F9; display: flex; align-items: center; gap: 0.5rem;">
            <span>👤</span>
            <span>Personal Information</span>
        </h3>

        <div style="display: flex; flex-direction: column; gap: 1rem; font-size: 0.875rem;">
            <div style="display: flex; justify-content: space-between; border-bottom: 1px solid #F8FAFC; padding-bottom: 0.5rem;">
                <span style="color: #64748B; font-weight: 500;">Email Address:</span>
                <span style="color: #0F172A; font-weight: 600;"><?= e($employee['email']) ?></span>
            </div>
            <div style="display: flex; justify-content: space-between; border-bottom: 1px solid #F8FAFC; padding-bottom: 0.5rem;">
                <span style="color: #64748B; font-weight: 500;">Phone Number:</span>
                <span style="color: #0F172A;"><?= e($employee['phone'] ?: '—') ?></span>
            </div>
            <div style="display: flex; justify-content: space-between; border-bottom: 1px solid #F8FAFC; padding-bottom: 0.5rem;">
                <span style="color: #64748B; font-weight: 500;">Gender:</span>
                <span style="color: #0F172A;"><?= e($employee['gender']) ?></span>
            </div>
            <div style="display: flex; justify-content: space-between; border-bottom: 1px solid #F8FAFC; padding-bottom: 0.5rem;">
                <span style="color: #64748B; font-weight: 500;">Date of Birth:</span>
                <span style="color: #0F172A;"><?= e($employee['birth_date']) ?> (Age <?= e((string)$age) ?>)</span>
            </div>
            <div style="border-bottom: 1px solid #F8FAFC; padding-bottom: 0.5rem;">
                <div style="color: #64748B; font-weight: 500; margin-bottom: 0.25rem;">Residential Address:</div>
                <div style="color: #0F172A; line-height: 1.4;"><?= nl2br(e($employee['address'] ?: 'No address specified on record.')) ?></div>
            </div>
        </div>
    </div>

    <!-- Employment & Organization Card -->
    <div class="card" style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 0.75rem; padding: 1.75rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
        <h3 style="font-size: 1.1rem; font-weight: 600; color: #1E293B; margin: 0 0 1.25rem 0; padding-bottom: 0.75rem; border-bottom: 1px solid #F1F5F9; display: flex; align-items: center; gap: 0.5rem;">
            <span>🏢</span>
            <span>Employment Details</span>
        </h3>

        <div style="display: flex; flex-direction: column; gap: 1rem; font-size: 0.875rem;">
            <div style="display: flex; justify-content: space-between; border-bottom: 1px solid #F8FAFC; padding-bottom: 0.5rem;">
                <span style="color: #64748B; font-weight: 500;">Department:</span>
                <span style="color: #0F172A; font-weight: 600;"><?= e($employee['department_name'] ?? '—') ?></span>
            </div>
            <div style="display: flex; justify-content: space-between; border-bottom: 1px solid #F8FAFC; padding-bottom: 0.5rem;">
                <span style="color: #64748B; font-weight: 500;">Job Position:</span>
                <span style="color: #0F172A; font-weight: 600;"><?= e($employee['position_name'] ?? '—') ?></span>
            </div>
            <div style="display: flex; justify-content: space-between; border-bottom: 1px solid #F8FAFC; padding-bottom: 0.5rem;">
                <span style="color: #64748B; font-weight: 500;">Date Hired:</span>
                <span style="color: #0F172A;"><?= e($employee['date_hired']) ?> (<?= e($tenureStr) ?>)</span>
            </div>
            <div style="display: flex; justify-content: space-between; border-bottom: 1px solid #F8FAFC; padding-bottom: 0.5rem;">
                <span style="color: #64748B; font-weight: 500;">Employment Status:</span>
                <span>
                    <?php if ($employee['employment_status'] === 'Active'): ?>
                        <span class="badge badge-success">Active</span>
                    <?php else: ?>
                        <span class="badge badge-danger">Inactive</span>
                    <?php endif; ?>
                </span>
            </div>
            <div style="display: flex; justify-content: space-between; border-bottom: 1px solid #F8FAFC; padding-bottom: 0.5rem;">
                <span style="color: #64748B; font-weight: 500;">System Portal Access:</span>
                <span>
                    <?php if (!empty($employee['user_account_id'])): ?>
                        <code style="background: #F1F5F9; color: #334155; padding: 0.15rem 0.4rem; border-radius: 0.25rem;">
                            <?= e($employee['email']) ?> (<?= e(strtoupper($employee['user_role'] ?? 'EMPLOYEE')) ?>)
                        </code>
                    <?php else: ?>
                        <a href="<?= url('/hr/users/create?employee_id=' . $employee['id']) ?>" class="btn btn-primary btn-sm" style="padding: 0.2rem 0.5rem; font-size: 0.75rem; text-decoration: none;">
                            + Provision Portal Account
                        </a>
                    <?php endif; ?>
                </span>
            </div>
            <div style="display: flex; justify-content: space-between; padding-bottom: 0.5rem;">
                <span style="color: #64748B; font-weight: 500;">Record Registered:</span>
                <span style="color: #94A3B8; font-size: 0.8125rem;"><?= e($employee['created_at']) ?></span>
            </div>
        </div>
    </div>
</div>

<!-- Deactivation Confirmation Modal -->
<?php if ($employee['employment_status'] === 'Active'): ?>
<div id="deactivateModal" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="deactivateTitle">
    <div class="modal-card">
        <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem;">
            <div style="width: 40px; height: 40px; border-radius: 50%; background: #FEE2E2; color: #DC2626; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">⚠️</div>
            <div>
                <h3 id="deactivateTitle" style="margin: 0; font-size: 1.15rem; color: #0F172A;">Confirm Deactivation</h3>
                <div style="font-size: 0.8125rem; color: #64748B;">Soft deactivation preserves historical leave logs.</div>
            </div>
        </div>

        <p style="font-size: 0.875rem; color: #475569; line-height: 1.5; margin-bottom: 1.5rem;">
            Are you sure you want to change the status of <strong><?= e($fullName) ?></strong> (<code><?= e($employee['employee_no']) ?></code>) to <strong>Inactive</strong>?
            <br><br>
            <span style="font-size: 0.8125rem; color: #94A3B8;">
                Historical leave records, balance allocations, and activity audit events will remain intact in the database.
            </span>
        </p>

        <form method="POST" action="<?= url('/hr/employees/' . $employee['id'] . '/deactivate') ?>" style="margin: 0;">
            <?= csrf_field() ?>
            <?php
            $stepUpFieldId = 'deactivate_current_password';
            $stepUpHelp = 'Deactivation is a sensitive action. Confirm your own HR account password to continue.';
            $stepUpOptional = false;
            require __DIR__ . '/../partials/step_up_field.php';
            ?>
            <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('deactivateModal')">Cancel</button>
                <button type="submit" class="btn btn-danger" style="font-weight: 600;">Confirm Deactivation</button>
            </div>
        </form>
    </div>
</div>
<?php else: ?>
<!-- Reactivation Confirmation Modal -->
<div id="reactivateModal" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="reactivateTitle">
    <div class="modal-card">
        <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem;">
            <div style="width: 40px; height: 40px; border-radius: 50%; background: #DCFCE7; color: #16A34A; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">✅</div>
            <div>
                <h3 id="reactivateTitle" style="margin: 0; font-size: 1.15rem; color: #0F172A;">Confirm Reactivation</h3>
                <div style="font-size: 0.8125rem; color: #64748B;">Restores employment status and the linked login account.</div>
            </div>
        </div>

        <p style="font-size: 0.875rem; color: #475569; line-height: 1.5; margin-bottom: 1.5rem;">
            Reactivate <strong><?= e($fullName) ?></strong> (<code><?= e($employee['employee_no']) ?></code>)?
            <br><br>
            <span style="font-size: 0.8125rem; color: #94A3B8;">
                Their employment status returns to Active and their login account is re-enabled. Signing in still requires email and password plus MFA.
            </span>
        </p>

        <form method="POST" action="<?= url('/hr/employees/' . $employee['id'] . '/reactivate') ?>" style="margin: 0;">
            <?= csrf_field() ?>
            <?php
            $stepUpFieldId = 'reactivate_current_password';
            $stepUpHelp = 'Reactivation restores system access. Confirm your own HR account password to continue.';
            $stepUpOptional = false;
            require __DIR__ . '/../partials/step_up_field.php';
            ?>
            <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('reactivateModal')">Cancel</button>
                <button type="submit" class="btn btn-primary" style="font-weight: 600;">Confirm Reactivation</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>
