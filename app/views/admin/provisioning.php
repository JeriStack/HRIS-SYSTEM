<?php
/**
 * Controlled Administrator Provisioning View
 * Route: GET /admin/provisioning
 * Access: Administrator Only
 */
?>
<div class="page-header" style="margin-bottom: var(--space-6);">
    <div class="page-title-group">
        <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.25rem;">
            <h1 style="margin: 0; font-size: 1.625rem; font-weight: 700;">Admin Provisioning &amp; Governance</h1>
            <span class="badge badge-success">CONTROLLED PROVISIONING ACTIVE</span>
        </div>
        <p style="margin: 0; font-size: 0.9375rem; color: var(--color-text-muted);">
            Protected initial setup, active Administrator inventory, and Last Active Admin Lockout Protection.
        </p>
    </div>
    <div class="page-actions">
        <a href="<?= url('/admin/users') ?>" class="btn btn-secondary btn-sm">
            <span>👥</span> Manage All Users
        </a>
    </div>
</div>

<!-- Lockout Protection & Security Rules Alert -->
<div class="alert alert-info" style="margin-bottom: var(--space-6); display: flex; align-items: flex-start; gap: 0.75rem;">
    <span style="font-size: 1.25rem;">🛡️</span>
    <div style="font-size: 0.875rem; line-height: 1.5;">
        <strong>Last Active Administrator Lockout Protection is Active.</strong>
        The system enforces transactional safeguards preventing deactivation, demotion, or deletion of the last remaining active Administrator. Public web registration of Admin accounts is permanently disabled.
    </div>
</div>

<!-- Active Administrators Table -->
<div class="card" style="margin-bottom: var(--space-6);">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h2 class="card-title" style="margin: 0; font-size: 1rem; font-weight: 600;">Active Administrators Inventory</h2>
            <span style="font-size: 0.8125rem; color: var(--color-text-muted);"><?= (int)$adminCount ?> total administrator account(s) registered in the system.</span>
        </div>
        <span class="badge badge-primary"><?= (int)$adminCount ?> Active Admin(s)</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover" aria-label="Administrator Accounts">
            <thead>
                <tr>
                    <th style="width: 80px;">User ID</th>
                    <th>Username / Identity</th>
                    <th>Email Address</th>
                    <th>Status</th>
                    <th>Provisioned Date</th>
                    <th style="width: 140px; text-align: right;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($activeAdmins as $admin): ?>
                    <tr>
                        <td style="font-family: var(--font-mono); font-weight: 600;">#<?= (int)$admin['id'] ?></td>
                        <td>
                            <strong><?= e($admin['username']) ?></strong>
                            <?php if (!empty($admin['first_name'])): ?>
                                <div style="font-size: 0.75rem; color: var(--color-text-muted);">
                                    <?= e($admin['first_name'] . ' ' . $admin['last_name']) ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td style="font-family: var(--font-mono); font-size: 0.8125rem;">
                            <?= e($admin['email']) ?>
                        </td>
                        <td>
                            <span class="badge badge-success">
                                <span class="badge-dot" aria-hidden="true"></span>
                                <?= e($admin['status'] ?? 'Active') ?>
                            </span>
                        </td>
                        <td style="font-size: 0.8125rem; color: var(--color-text-muted);">
                            <?= date('M j, Y H:i', strtotime($admin['created_at'])) ?>
                        </td>
                        <td style="text-align: right;">
                            <a href="<?= url('/admin/users/' . (int)$admin['id'] . '/edit') ?>" class="btn btn-secondary btn-sm" style="padding: 0.25rem 0.6rem; font-size: 0.75rem;">
                                Edit Credentials
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Controlled Setup CLI Instructions -->
<div class="card" style="margin-bottom: var(--space-6);">
    <div class="card-header">
        <h2 class="card-title" style="margin: 0; font-size: 1rem; font-weight: 600;"><span>⚡</span> Controlled CLI Administrator Provisioning</h2>
        <span style="font-size: 0.8125rem; color: var(--color-text-muted);">Protected command-line onboarding utility</span>
    </div>
    <div class="card-body" style="padding: 1.25rem;">
        <p style="font-size: 0.875rem; color: var(--color-text-body); margin-bottom: 0.75rem;">
            New system administrators must be bootstrapped exclusively through the dedicated CLI provisioning script to ensure password complexity validation, Bcrypt (cost 12) hashing, transaction safety, and immutable audit logging.
        </p>
        <div style="background: var(--color-bg-page); border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 0.85rem 1rem; font-family: var(--font-mono); font-size: 0.8125rem; color: var(--color-text-main); overflow-x: auto;">
            php bin/create_admin_user.php --username=admin.new --email=admin.new@nexora.local --password="YourSecurePassword123!" --first-name="System" --last-name="Administrator"
        </div>
    </div>
</div>

<!-- Admin Provisioning Audit Trail -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title" style="margin: 0; font-size: 1rem; font-weight: 600;"><span>📜</span> Provisioning &amp; Role Modification Audit Trail</h2>
    </div>
    <div class="table-responsive">
        <table class="table table-hover" aria-label="Provisioning Audit Logs">
            <thead>
                <tr>
                    <th>Actor</th>
                    <th>Action</th>
                    <th>Description</th>
                    <th>IP Address</th>
                    <th>Timestamp</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($adminAuditLogs)): ?>
                    <tr>
                        <td colspan="5" class="text-center" style="padding: 2rem; color: var(--color-text-muted);">
                            No administrative provisioning events recorded.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($adminAuditLogs as $log): ?>
                        <tr>
                            <td><strong><?= e($log['username'] ?? 'CLI / System') ?></strong></td>
                            <td>
                                <span class="badge badge-neutral" style="font-family: var(--font-mono); font-size: 0.7rem;">
                                    <?= e($log['action']) ?>
                                </span>
                            </td>
                            <td style="font-size: 0.8125rem;"><?= e($log['description']) ?></td>
                            <td style="font-family: var(--font-mono); font-size: 0.75rem; color: var(--color-text-muted);"><?= e($log['ip_address'] ?? 'CLI') ?></td>
                            <td style="font-size: 0.775rem; color: var(--color-text-muted);"><?= date('M j, Y H:i', strtotime($log['created_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

