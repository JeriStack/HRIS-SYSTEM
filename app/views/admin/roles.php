<?php
/**
 * Three-Role Permissions Governance Matrix View
 * Route: GET /admin/roles
 * Access: Administrator Only
 */
?>
<div class="page-header" style="margin-bottom: var(--space-6);">
    <div class="page-title-group">
        <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.25rem;">
            <h1 style="margin: 0; font-size: 1.625rem; font-weight: 700;">Roles &amp; Permissions</h1>
            <span class="badge badge-primary">ADR-012 RBAC GOVERNANCE</span>
        </div>
        <p style="margin: 0; font-size: 0.9375rem; color: var(--color-text-muted);">
            Inspection of application permissions, capability allowlists, and role boundaries across Admin, HR, and Employee.
        </p>
    </div>
    <div class="page-actions">
        <a href="<?= url('/admin/users') ?>" class="btn btn-primary btn-sm">
            <span>👥</span> Manage User Role Assignments
        </a>
    </div>
</div>

<div class="card" style="margin-bottom: var(--space-6);">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h2 class="card-title" style="margin: 0; font-size: 1rem; font-weight: 600;">System-Wide Capability Matrix</h2>
            <span style="font-size: 0.8125rem; color: var(--color-text-muted);">Defined in <code>Core\Permission</code> and enforced server-side via <code>RoleMiddleware</code>.</span>
        </div>
        <span class="badge badge-neutral" style="font-family: var(--font-mono);">STRICT 3-ROLE MODEL</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover" aria-label="Role Permission Matrix">
            <thead>
                <tr>
                    <th style="width: 250px;">Functional Domain</th>
                    <th>Capability Name</th>
                    <th style="width: 140px; text-align: center;">Administrator</th>
                    <th style="width: 140px; text-align: center;">HR Officer</th>
                    <th style="width: 140px; text-align: center;">Employee</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($categorized as $category => $caps): ?>
                    <tr style="background: var(--color-bg-muted);">
                        <td colspan="5" style="font-weight: 700; font-size: 0.8125rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--color-text-muted);">
                            <?= e($category) ?>
                        </td>
                    </tr>
                    <?php foreach ($caps as $cap): ?>
                        <tr>
                            <td style="color: var(--color-text-muted); font-size: 0.8125rem;">
                                <?= e($category) ?>
                            </td>
                            <td>
                                <code style="font-size: 0.8125rem; font-weight: 600; color: var(--color-primary);"><?= e($cap) ?></code>
                            </td>
                            <td style="text-align: center;">
                                <?php if (\Core\Permission::can('admin', $cap)): ?>
                                    <span style="color: var(--color-success); font-weight: 700; font-size: 1.1rem;">✓</span>
                                <?php else: ?>
                                    <span style="color: var(--color-text-muted); font-size: 1.1rem;">—</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: center;">
                                <?php if (\Core\Permission::can('hr', $cap)): ?>
                                    <span style="color: var(--color-success); font-weight: 700; font-size: 1.1rem;">✓</span>
                                <?php else: ?>
                                    <span style="color: var(--color-text-muted); font-size: 1.1rem;">—</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: center;">
                                <?php if (\Core\Permission::can('employee', $cap)): ?>
                                    <span style="color: var(--color-success); font-weight: 700; font-size: 1.1rem;">✓</span>
                                <?php else: ?>
                                    <span style="color: var(--color-text-muted); font-size: 1.1rem;">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

