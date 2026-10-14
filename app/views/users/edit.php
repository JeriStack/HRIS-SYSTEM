<div class="user-edit-view" style="max-width: 700px; margin: 0 auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h1 style="font-size: 1.5rem; font-weight: 700; color: #1E293B; margin: 0 0 0.25rem 0;">Edit User Account</h1>
            <p style="color: #64748B; font-size: 0.875rem; margin: 0;">Configure assigned role and account activation status.</p>
        </div>
        <a href="<?= url('/hr/users') ?>" class="btn btn-secondary btn-sm">← Back to Users List</a>
    </div>

    <!-- Security Warning & Lockout Notice -->
    <div style="background: #FFFBEB; border: 1px solid #FCD34D; border-radius: 0.5rem; padding: 1rem; margin-bottom: 1.5rem; color: #92400E; font-size: 0.875rem; line-height: 1.5;">
        <strong>⚠️ Administrative Security Notice:</strong>
        <ul style="margin: 0.5rem 0 0 1.25rem; padding: 0;">
            <li>Role assignments take effect immediately upon authentication.</li>
            <li>You cannot demote or deactivate your own active session account.</li>
            <li>The system prevents deactivating or demoting the last active HR Officer account.</li>
        </ul>
    </div>

    <div style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 0.75rem; padding: 1.75rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
        <form method="POST" action="<?= url('/hr/users/' . $targetUser['id'] . '/edit') ?>">
            <?= csrf_field() ?>

            <!-- Read-only Account Identity Details -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.25rem;">
                <div>
                    <label style="display: block; font-size: 0.8125rem; font-weight: 600; color: #475569; margin-bottom: 0.35rem;">Username</label>
                    <input type="text" value="<?= e($targetUser['username']) ?>" disabled style="width: 100%; padding: 0.5rem 0.75rem; border: 1px solid #E2E8F0; border-radius: 0.375rem; background: #F8FAFC; color: #64748B; font-size: 0.875rem;">
                </div>
                <div>
                    <label style="display: block; font-size: 0.8125rem; font-weight: 600; color: #475569; margin-bottom: 0.35rem;">Associated Employee</label>
                    <input type="text" value="<?= e(!empty($targetUser['first_name']) ? ($targetUser['first_name'] . ' ' . $targetUser['last_name'] . ' (' . $targetUser['employee_no'] . ')') : 'None (System Account)') ?>" disabled style="width: 100%; padding: 0.5rem 0.75rem; border: 1px solid #E2E8F0; border-radius: 0.375rem; background: #F8FAFC; color: #64748B; font-size: 0.875rem;">
                </div>
            </div>

            <!-- Role Assignment -->
            <div style="margin-bottom: 1.25rem;">
                <label for="role" style="display: block; font-size: 0.8125rem; font-weight: 600; color: #1E293B; margin-bottom: 0.35rem;">
                    Assigned Application Role <span style="color: #DC2626;">*</span>
                </label>
                <select id="role" name="role" required style="width: 100%; padding: 0.55rem 0.75rem; border: 1px solid #CBD5E1; border-radius: 0.375rem; font-size: 0.875rem; background: #FFFFFF;">
                    <option value="hr" <?= $targetUser['role'] === 'hr' ? 'selected' : '' ?>>
                        HR Officer (hr) — Full workforce management, user accounts, employees, departments, positions, leaves
                    </option>
                    <option value="employee" <?= $targetUser['role'] === 'employee' ? 'selected' : '' ?>>
                        Employee (employee) — Self-service portal, personal profile, own leave requests
                    </option>
                </select>
                <small style="display: block; color: #64748B; font-size: 0.75rem; margin-top: 0.35rem;">
                    Allowed role values are strictly constrained to <code>hr</code> and <code>employee</code>.
                </small>
            </div>

            <!-- Account Status -->
            <div style="margin-bottom: 1.5rem;">
                <label for="status" style="display: block; font-size: 0.8125rem; font-weight: 600; color: #1E293B; margin-bottom: 0.35rem;">
                    Account Status <span style="color: #DC2626;">*</span>
                </label>
                <select id="status" name="status" required style="width: 100%; padding: 0.55rem 0.75rem; border: 1px solid #CBD5E1; border-radius: 0.375rem; font-size: 0.875rem; background: #FFFFFF;">
                    <option value="Active" <?= $targetUser['status'] === 'Active' ? 'selected' : '' ?>>
                        Active — Account is authorized to authenticate
                    </option>
                    <option value="Inactive" <?= $targetUser['status'] === 'Inactive' ? 'selected' : '' ?>>
                        Inactive — Authentication blocked at login boundary
                    </option>
                </select>
                <small style="display: block; color: #64748B; font-size: 0.75rem; margin-top: 0.35rem;">
                    Inactive accounts are rejected automatically during login verification.
                </small>
            </div>

            <!-- Form Actions -->
            <div style="display: flex; gap: 0.75rem; justify-content: flex-end; padding-top: 1rem; border-top: 1px solid #F1F5F9;">
                <a href="<?= url('/hr/users') ?>" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>
