<div class="users-management-view">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h1 style="font-size: 1.5rem; font-weight: 700; color: var(--color-text-main, #1E293B); margin: 0 0 0.25rem 0;">User Account & Role Management</h1>
            <p style="color: var(--color-text-muted, #64748B); font-size: 0.875rem; margin: 0;">Administrator console for maintaining authentication accounts, roles, and status.</p>
        </div>
        <div style="display: flex; gap: 0.5rem; align-items: center;">
            <a href="<?= url('/admin/dashboard') ?>" class="btn btn-secondary btn-sm">← Back to Dashboard</a>
            <a href="<?= url('/admin/users/create') ?>" class="btn btn-primary btn-sm">+ Provision User Account</a>
        </div>
    </div>

    <!-- Security Information Alert -->
    <div style="background: var(--color-primary-light, #EFF6FF); border: 1px solid var(--color-primary-border, #BFDBFE); border-radius: 0.5rem; padding: 1rem; margin-bottom: 1.5rem; color: var(--color-primary, #1E40AF); font-size: 0.875rem; line-height: 1.5;">
        <strong>🛡️ Access & Role Policy (NFR-SEC-002):</strong> Only Administrators can modify roles and account status. Changes to roles are validated against the strict allowlist (<code>admin</code>, <code>hr</code>, <code>employee</code>). Last-active-Administrator lockout protection is actively enforced.
    </div>

    <!-- User Metrics Grid -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
        <div style="background: var(--color-bg-surface, #FFFFFF); border: 1px solid var(--color-border, #E2E8F0); border-radius: 0.5rem; padding: 1rem;">
            <div style="font-size: 0.75rem; font-weight: 600; color: var(--color-text-muted, #64748B); text-transform: uppercase;">Total Users</div>
            <div style="font-size: 1.5rem; font-weight: 700; color: var(--color-text-main, #1E293B); margin-top: 0.25rem;"><?= e((string)($stats['total'] ?? 0)) ?></div>
        </div>
        <div style="background: var(--color-bg-surface, #FFFFFF); border: 1px solid var(--color-border, #E2E8F0); border-radius: 0.5rem; padding: 1rem;">
            <div style="font-size: 0.75rem; font-weight: 600; color: var(--color-success, #15803D); text-transform: uppercase;">Active Accounts</div>
            <div style="font-size: 1.5rem; font-weight: 700; color: var(--color-success, #15803D); margin-top: 0.25rem;"><?= e((string)($stats['active'] ?? 0)) ?></div>
        </div>
        <div style="background: var(--color-bg-surface, #FFFFFF); border: 1px solid var(--color-border, #E2E8F0); border-radius: 0.5rem; padding: 1rem;">
            <div style="font-size: 0.75rem; font-weight: 600; color: var(--color-error, #B91C1C); text-transform: uppercase;">Inactive Accounts</div>
            <div style="font-size: 1.5rem; font-weight: 700; color: var(--color-error, #B91C1C); margin-top: 0.25rem;"><?= e((string)($stats['inactive'] ?? 0)) ?></div>
        </div>
        <div style="background: var(--color-bg-surface, #FFFFFF); border: 1px solid var(--color-border, #E2E8F0); border-radius: 0.5rem; padding: 1rem;">
            <div style="font-size: 0.75rem; font-weight: 600; color: #6366F1; text-transform: uppercase;">Administrators</div>
            <div style="font-size: 1.5rem; font-weight: 700; color: #4F46E5; margin-top: 0.25rem;"><?= e((string)($stats['admins'] ?? 0)) ?></div>
        </div>
        <div style="background: var(--color-bg-surface, #FFFFFF); border: 1px solid var(--color-border, #E2E8F0); border-radius: 0.5rem; padding: 1rem;">
            <div style="font-size: 0.75rem; font-weight: 600; color: var(--color-warning, #B45309); text-transform: uppercase;">HR Officers</div>
            <div style="font-size: 1.5rem; font-weight: 700; color: var(--color-warning, #B45309); margin-top: 0.25rem;"><?= e((string)($stats['hrs'] ?? 0)) ?></div>
        </div>
        <div style="background: var(--color-bg-surface, #FFFFFF); border: 1px solid var(--color-border, #E2E8F0); border-radius: 0.5rem; padding: 1rem;">
            <div style="font-size: 0.75rem; font-weight: 600; color: #047857; text-transform: uppercase;">Employees</div>
            <div style="font-size: 1.5rem; font-weight: 700; color: #047857; margin-top: 0.25rem;"><?= e((string)($stats['employees'] ?? 0)) ?></div>
        </div>
    </div>

    <!-- User Accounts Table Card -->
    <div style="background: var(--color-bg-surface, #FFFFFF); border: 1px solid var(--color-border, #E2E8F0); border-radius: 0.75rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05); overflow: hidden;">
        <div style="padding: 1rem 1.25rem; border-bottom: 1px solid var(--color-border, #E2E8F0); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
            <h3 style="font-size: 1.1rem; font-weight: 600; color: var(--color-text-main, #1E293B); margin: 0;">Registered Accounts Directory</h3>
            <input type="text" id="userFilterInput" placeholder="Filter by username or employee..." style="padding: 0.4rem 0.75rem; border: 1px solid var(--color-border-dark, #CBD5E1); border-radius: 0.375rem; font-size: 0.875rem; width: 260px;">
        </div>

        <div class="table-responsive">
            <table style="width: 100%; border-collapse: collapse; font-size: 0.875rem;" id="usersTable">
                <thead>
                    <tr style="background: var(--color-bg-subtle, #F8FAFC); border-bottom: 1px solid var(--color-border, #E2E8F0); text-align: left;">
                        <th style="padding: 0.75rem 1rem; color: var(--color-text-body, #475569); font-weight: 600;">ID</th>
                        <th style="padding: 0.75rem 1rem; color: var(--color-text-body, #475569); font-weight: 600;">Username</th>
                        <th style="padding: 0.75rem 1rem; color: var(--color-text-body, #475569); font-weight: 600;">Assigned Role</th>
                        <th style="padding: 0.75rem 1rem; color: var(--color-text-body, #475569); font-weight: 600;">Status</th>
                        <th style="padding: 0.75rem 1rem; color: var(--color-text-body, #475569); font-weight: 600;">Linked Employee</th>
                        <th style="padding: 0.75rem 1rem; color: var(--color-text-body, #475569); font-weight: 600;">Department</th>
                        <th style="padding: 0.75rem 1rem; color: var(--color-text-body, #475569); font-weight: 600;">Created</th>
                        <th style="padding: 0.75rem 1rem; color: var(--color-text-body, #475569); font-weight: 600; text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($users)): ?>
                        <?php foreach ($users as $u): ?>
                            <tr style="border-bottom: 1px solid var(--color-border-subtle, #F1F5F9);" class="user-row">
                                <td style="padding: 0.75rem 1rem; color: var(--color-text-muted, #64748B);">#<?= e((string)$u['id']) ?></td>
                                <td style="padding: 0.75rem 1rem;">
                                    <strong><?= e($u['username']) ?></strong>
                                    <?php if ($u['id'] === currentUserId()): ?>
                                        <span style="font-size: 0.7rem; background: #E0E7FF; color: #3730A3; padding: 0.15rem 0.4rem; border-radius: 0.25rem; font-weight: 600; margin-left: 0.25rem;">YOU</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 0.75rem 1rem;">
                                    <?php
                                        $roleBadgeStyle = match($u['role']) {
                                            'hr'       => 'background: var(--color-warning-bg, #FEF3C7); color: var(--color-warning, #B45309); border: 1px solid var(--color-warning-border, #FDE68A);',
                                            'employee' => 'background: var(--color-success-bg, #ECFDF5); color: #047857; border: 1px solid var(--color-success-border, #A7F3D0);',
                                            default    => 'background: var(--color-bg-muted, #F1F5F9); color: var(--color-text-body, #475569);'
                                        };
                                    ?>
                                    <span style="display: inline-block; padding: 0.2rem 0.55rem; border-radius: 0.25rem; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; <?= $roleBadgeStyle ?>">
                                        <?= e($u['role']) ?>
                                    </span>
                                </td>
                                <td style="padding: 0.75rem 1rem;">
                                    <?php if ($u['status'] === 'Active'): ?>
                                        <span style="display: inline-block; padding: 0.2rem 0.55rem; border-radius: 0.25rem; font-size: 0.75rem; font-weight: 600; background: #DCFCE7; color: var(--color-success, #15803D); border: 1px solid var(--color-success-border, #BBF7D0);">Active</span>
                                    <?php else: ?>
                                        <span style="display: inline-block; padding: 0.2rem 0.55rem; border-radius: 0.25rem; font-size: 0.75rem; font-weight: 600; background: var(--color-error-bg, #FEE2E2); color: var(--color-error, #B91C1C); border: 1px solid var(--color-error-border, #FECACA);">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 0.75rem 1rem;">
                                    <?php if (!empty($u['employee_no'])): ?>
                                        <?= e($u['first_name'] . ' ' . $u['last_name']) ?>
                                        <div style="font-size: 0.75rem; color: var(--color-text-muted, #64748B);"><code><?= e($u['employee_no']) ?></code> • <?= e($u['email']) ?></div>
                                    <?php else: ?>
                                        <span style="color: var(--color-text-muted, #94A3B8); font-style: italic;">No Employee Linked</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 0.75rem 1rem; color: var(--color-text-body, #475569);">
                                    <?= e($u['department_name'] ?? '—') ?>
                                </td>
                                <td style="padding: 0.75rem 1rem; color: var(--color-text-muted, #64748B); font-size: 0.8rem;">
                                    <?= e(date('M d, Y', strtotime($u['created_at']))) ?>
                                </td>
                                <td style="padding: 0.75rem 1rem; text-align: right;">
                                    <a href="<?= url('/admin/users/' . $u['id'] . '/edit') ?>" class="btn btn-secondary btn-sm" style="padding: 0.3rem 0.65rem; font-size: 0.8rem; font-weight: 600;">
                                        Edit Role
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" style="padding: 2rem; text-align: center; color: var(--color-text-muted, #94A3B8);">No user accounts found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    // Live client search filtering
    document.getElementById('userFilterInput')?.addEventListener('input', function() {
        const query = this.value.toLowerCase();
        const rows = document.querySelectorAll('.user-row');
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(query) ? '' : 'none';
        });
    });
</script>
