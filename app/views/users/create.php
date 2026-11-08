<div class="user-create-view" style="max-width: 700px; margin: 0 auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h1 style="font-size: 1.5rem; font-weight: 700; color: #1E293B; margin: 0 0 0.25rem 0;">Provision User Account</h1>
            <p style="color: #64748B; font-size: 0.875rem; margin: 0;">Create secure portal credentials and assign an operational role for an active employee.</p>
        </div>
        <a href="<?= url('/admin/users') ?>" class="btn btn-secondary btn-sm">← Back to Users Directory</a>
    </div>

    <!-- Security Information Notice -->
    <div style="background: #EFF6FF; border: 1px solid #BFDBFE; border-radius: 0.5rem; padding: 1rem; margin-bottom: 1.5rem; color: #1E40AF; font-size: 0.875rem; line-height: 1.5;">
        <strong>🛡️ Account Onboarding Policy (NFR-SEC-002):</strong>
        <ul style="margin: 0.5rem 0 0 1.25rem; padding: 0;">
            <li>User accounts are strictly bound to registered employee profiles with unique, verified email addresses.</li>
            <li>Allowed roles are strictly limited to <strong>Administrator (admin)</strong>, <strong>HR Officer (hr)</strong>, and <strong>Employee (employee)</strong>.</li>
            <li>Initial passwords must be at least 8 characters long and are hashed using bcrypt.</li>
            <li>Users will complete mandatory Email OTP verification upon their first and every subsequent sign-in.</li>
        </ul>
    </div>

    <?php if (empty($unlinkedEmployees) && empty($selectedEmpId)): ?>
        <div style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 0.75rem; padding: 2.5rem; text-align: center; color: #64748B;">
            <div style="font-size: 2.5rem; margin-bottom: 0.75rem;">✅</div>
            <h3 style="font-size: 1.15rem; color: #1E293B; margin-bottom: 0.5rem;">All Active Employees Are Provisioned</h3>
            <p style="margin-bottom: 1.5rem; font-size: 0.875rem;">Every currently active employee record already has an associated user portal account.</p>
            <div style="display: flex; gap: 0.75rem; justify-content: center;">
                <a href="<?= url('/hr/employees/create') ?>" class="btn btn-primary btn-sm">+ Add New Employee</a>
                <a href="<?= url('/admin/users') ?>" class="btn btn-secondary btn-sm">View Accounts Directory</a>
            </div>
        </div>
    <?php else: ?>
        <div style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 0.75rem; padding: 1.75rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
            <form method="POST" action="<?= url('/admin/users/create') ?>" id="provisionUserForm">
                <?= csrf_field() ?>

                <!-- Select Employee -->
                <div style="margin-bottom: 1.25rem;">
                    <label for="employee_id" style="display: block; font-size: 0.8125rem; font-weight: 600; color: #1E293B; margin-bottom: 0.35rem;">
                        Select Active Employee <span style="color: #DC2626;">*</span>
                    </label>
                    <select id="employee_id" name="employee_id" required style="width: 100%; padding: 0.55rem 0.75rem; border: 1px solid #CBD5E1; border-radius: 0.375rem; font-size: 0.875rem; background: #FFFFFF;">
                        <option value="">-- Choose an employee to provision --</option>
                        <?php foreach ($unlinkedEmployees as $emp): ?>
                            <?php $selected = ($emp['id'] === (int)($old['employee_id'] ?? $selectedEmpId)) ? 'selected' : ''; ?>
                            <option value="<?= e((string)$emp['id']) ?>" 
                                    data-email="<?= e($emp['email']) ?>" 
                                    data-empno="<?= e($emp['employee_no']) ?>"
                                    data-dept="<?= e($emp['department_name'] ?? 'Unassigned') ?>"
                                    data-pos="<?= e($emp['position_name'] ?? 'Unassigned') ?>"
                                    <?= $selected ?>>
                                <?= e($emp['last_name'] . ', ' . $emp['first_name'] . ' (' . $emp['employee_no'] . ') — ' . $emp['email']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (!empty($errors['employee_id'])): ?>
                        <div style="color: #DC2626; font-size: 0.75rem; margin-top: 0.35rem;"><?= e($errors['employee_id']) ?></div>
                    <?php endif; ?>
                </div>

                <!-- Preview Info Card -->
                <div id="employeePreviewCard" style="display: none; background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 0.5rem; padding: 1rem; margin-bottom: 1.25rem;">
                    <div style="font-size: 0.75rem; font-weight: 600; color: #64748B; text-transform: uppercase; margin-bottom: 0.5rem;">Associated Profile Details</div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; font-size: 0.8125rem;">
                        <div>
                            <span style="color: #64748B;">Registered Email:</span>
                            <strong id="previewEmail" style="color: #0F172A; display: block;">—</strong>
                        </div>
                        <div>
                            <span style="color: #64748B;">Employee No:</span>
                            <strong id="previewEmpNo" style="color: #0F172A; display: block;">—</strong>
                        </div>
                        <div>
                            <span style="color: #64748B;">Department:</span>
                            <span id="previewDept" style="color: #334155;">—</span>
                        </div>
                        <div>
                            <span style="color: #64748B;">Job Position:</span>
                            <span id="previewPos" style="color: #334155;">—</span>
                        </div>
                    </div>
                </div>

                <!-- Assigned Role -->
                <div style="margin-bottom: 1.25rem;">
                    <label for="role" style="display: block; font-size: 0.8125rem; font-weight: 600; color: #1E293B; margin-bottom: 0.35rem;">
                        Assigned Application Role <span style="color: #DC2626;">*</span>
                    </label>
                    <select id="role" name="role" required style="width: 100%; padding: 0.55rem 0.75rem; border: 1px solid #CBD5E1; border-radius: 0.375rem; font-size: 0.875rem; background: #FFFFFF;">
                        <option value="employee" <?= (($old['role'] ?? 'employee') === 'employee') ? 'selected' : '' ?>>
                            Employee (employee) — Self-service portal, view balance, apply for leave
                        </option>
                        <option value="hr" <?= (($old['role'] ?? '') === 'hr') ? 'selected' : '' ?>>
                            HR Officer (hr) — Manage workforce, leave approvals, users, and organization
                        </option>
                    </select>
                    <?php if (!empty($errors['role'])): ?>
                        <div style="color: #DC2626; font-size: 0.75rem; margin-top: 0.35rem;"><?= e($errors['role']) ?></div>
                    <?php endif; ?>
                </div>

                <!-- Initial Password -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
                    <div>
                        <label for="password" style="display: block; font-size: 0.8125rem; font-weight: 600; color: #1E293B; margin-bottom: 0.35rem;">
                            Initial Password <span style="color: #DC2626;">*</span>
                        </label>
                        <input type="password" 
                               id="password" 
                               name="password" 
                               required 
                               minlength="8" 
                               autocomplete="new-password"
                               placeholder="Minimum 8 characters"
                               style="width: 100%; padding: 0.55rem 0.75rem; border: 1px solid #CBD5E1; border-radius: 0.375rem; font-size: 0.875rem; box-sizing: border-box;">
                        <?php if (!empty($errors['password'])): ?>
                            <div style="color: #DC2626; font-size: 0.75rem; margin-top: 0.35rem;"><?= e($errors['password']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div>
                        <label for="confirm_password" style="display: block; font-size: 0.8125rem; font-weight: 600; color: #1E293B; margin-bottom: 0.35rem;">
                            Confirm Password <span style="color: #DC2626;">*</span>
                        </label>
                        <input type="password" 
                               id="confirm_password" 
                               name="confirm_password" 
                               required 
                               minlength="8" 
                               autocomplete="new-password"
                               placeholder="Repeat initial password"
                               style="width: 100%; padding: 0.55rem 0.75rem; border: 1px solid #CBD5E1; border-radius: 0.375rem; font-size: 0.875rem; box-sizing: border-box;">
                        <?php if (!empty($errors['confirm_password'])): ?>
                            <div style="color: #DC2626; font-size: 0.75rem; margin-top: 0.35rem;"><?= e($errors['confirm_password']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!empty($stepUpRequired) || !empty($errors['current_password'])): ?>
                    <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 0.5rem; padding: 1rem; margin-bottom: 1.5rem;">
                        <label for="current_password" style="display: block; font-size: 0.8125rem; font-weight: 600; color: #1E293B; margin-bottom: 0.35rem;">
                            Confirm Your Current Password <span style="color: #DC2626;">*</span>
                        </label>
                        <p style="color: #64748B; font-size: 0.75rem; margin-top: 0; margin-bottom: 0.5rem;">
                            Step-up security check: To provision user accounts, please confirm your own active HR password.
                        </p>
                        <input type="password" 
                               id="current_password" 
                               name="current_password" 
                               autocomplete="current-password"
                               placeholder="Enter your current password"
                               style="width: 100%; padding: 0.55rem 0.75rem; border: 1px solid #CBD5E1; border-radius: 0.375rem; font-size: 0.875rem; box-sizing: border-box;">
                        <?php if (!empty($errors['current_password'])): ?>
                            <div style="color: #DC2626; font-size: 0.75rem; margin-top: 0.35rem;"><?= e($errors['current_password']) ?></div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- Form Actions -->
                <div style="display: flex; gap: 0.75rem; justify-content: flex-end; padding-top: 1rem; border-top: 1px solid #F1F5F9;">
                    <a href="<?= url('/admin/users') ?>" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary" id="submitProvisionBtn">Provision User Account</button>
                </div>
            </form>
        </div>

        <script>
            const empSelect = document.getElementById('employee_id');
            const previewCard = document.getElementById('employeePreviewCard');
            const previewEmail = document.getElementById('previewEmail');
            const previewEmpNo = document.getElementById('previewEmpNo');
            const previewDept = document.getElementById('previewDept');
            const previewPos = document.getElementById('previewPos');

            function updatePreview() {
                const opt = empSelect.options[empSelect.selectedIndex];
                if (opt && opt.value) {
                    previewEmail.textContent = opt.getAttribute('data-email') || '—';
                    previewEmpNo.textContent = opt.getAttribute('data-empno') || '—';
                    previewDept.textContent = opt.getAttribute('data-dept') || '—';
                    previewPos.textContent = opt.getAttribute('data-pos') || '—';
                    previewCard.style.display = 'block';
                } else {
                    previewCard.style.display = 'none';
                }
            }

            if (empSelect) {
                empSelect.addEventListener('change', updatePreview);
                updatePreview();
            }
        </script>
    <?php endif; ?>
</div>
