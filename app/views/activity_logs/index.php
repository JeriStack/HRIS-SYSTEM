<?php
/**
 * System Audit & Activity Logs View (HR & Admin Access)
 * Employee HRIS & Leave Management System
 */
?>
<div class="page-header">
    <div class="page-title-group">
        <h1>Audit Activity Logs</h1>
        <p>Immutable system audit trail tracking authentication, employee records, roles, and administrative events.</p>
    </div>
    <div class="page-actions">
        <span style="font-size: 0.75rem; color: var(--color-text-muted); background: var(--color-bg-surface); padding: 0.4rem 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--color-border);">
            🔒 Read-Only Compliance Log
        </span>
    </div>
</div>

<?php if (!empty($latestActivity)): ?>
    <!-- LIFO Recent Activity Stack Inspection Banner (Phase 11/13) -->
    <div class="card" style="margin-bottom: var(--space-4); padding: 0.85rem 1.25rem; background: var(--color-bg-muted); border-left: 4px solid var(--color-primary); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
        <div>
            <div style="font-size: 0.7rem; font-weight: 700; color: var(--color-primary); text-transform: uppercase;">
                ▲ LIFO Stack Top Event (Most Recent Audit Entry &bull; <?= (int)($stackDepth ?? 0) ?> Items in Stack)
            </div>
            <div style="font-size: 0.875rem; color: var(--color-text-primary); font-weight: 600; margin-top: 0.2rem;">
                <span class="badge badge-info" style="font-size: 0.7rem;"><?= e($latestActivity['action'] ?? '') ?></span>
                <?= e($latestActivity['description'] ?? '') ?>
            </div>
        </div>
        <div style="font-size: 0.75rem; color: var(--color-text-muted); text-align: right;">
            <div>by <strong><?= e($latestActivity['username'] ?? 'System') ?></strong></div>
            <div><?= !empty($latestActivity['created_at']) ? date('M d, Y h:i A', strtotime($latestActivity['created_at'])) : '' ?></div>
        </div>
    </div>
<?php endif; ?>

<!-- Search & Filter Bar -->
<div class="filter-bar">
    <form method="GET" action="<?= url('/activity-logs') ?>" style="display: flex; gap: var(--space-3); width: 100%; flex-wrap: wrap; align-items: center;">
        <div class="search-input-wrap">
            <input type="text" 
                   name="q" 
                   class="form-control" 
                   placeholder="Search audit trail by description, username, or IP..." 
                   value="<?= e($searchQuery ?? '') ?>">
        </div>
        <div style="min-width: 200px;">
            <select name="action" class="form-select">
                <option value="">-- All Audited Actions --</option>
                <option value="USER_LOGIN" <?= ($selectedAction ?? '') === 'USER_LOGIN' ? 'selected' : '' ?>>USER_LOGIN</option>
                <option value="USER_LOGOUT" <?= ($selectedAction ?? '') === 'USER_LOGOUT' ? 'selected' : '' ?>>USER_LOGOUT</option>
                <option value="USER_UPDATED" <?= ($selectedAction ?? '') === 'USER_UPDATED' ? 'selected' : '' ?>>USER_UPDATED</option>
                <option value="EMPLOYEE_CREATED" <?= ($selectedAction ?? '') === 'EMPLOYEE_CREATED' ? 'selected' : '' ?>>EMPLOYEE_CREATED</option>
                <option value="EMPLOYEE_UPDATED" <?= ($selectedAction ?? '') === 'EMPLOYEE_UPDATED' ? 'selected' : '' ?>>EMPLOYEE_UPDATED</option>
                <option value="EMPLOYEE_DEACTIVATED" <?= ($selectedAction ?? '') === 'EMPLOYEE_DEACTIVATED' ? 'selected' : '' ?>>EMPLOYEE_DEACTIVATED</option>
                <option value="LEAVE_REQUESTED" <?= ($selectedAction ?? '') === 'LEAVE_REQUESTED' ? 'selected' : '' ?>>LEAVE_REQUESTED</option>
                <option value="LEAVE_APPROVED" <?= ($selectedAction ?? '') === 'LEAVE_APPROVED' ? 'selected' : '' ?>>LEAVE_APPROVED</option>
                <option value="LEAVE_REJECTED" <?= ($selectedAction ?? '') === 'LEAVE_REJECTED' ? 'selected' : '' ?>>LEAVE_REJECTED</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary btn-sm">
            <span>🔍</span> Filter
        </button>
        <?php if (!empty($searchQuery) || !empty($selectedAction)): ?>
            <a href="<?= url('/activity-logs') ?>" class="btn btn-secondary btn-sm">
                Clear Filters
            </a>
        <?php endif; ?>
    </form>
</div>

<!-- Audit Trail Table Card -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">
            <span>📜</span> Security & Operations Trail (<?= count($logs) ?> Entries)
        </h2>
    </div>
    <div class="table-responsive">
        <table class="table table-hover" aria-label="System Audit Activity Trail">
            <thead>
                <tr>
                    <th>Log ID</th>
                    <th>Operator</th>
                    <th>Action</th>
                    <th>Description</th>
                    <th>IP Address</th>
                    <th>Timestamp</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="6" class="text-center" style="padding: 2.5rem; color: var(--color-text-muted);">
                            No activity logs match the selected search criteria.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td style="font-family: var(--font-mono); font-size: 0.75rem; color: var(--color-text-muted);">
                                #<?= (int)$log['id'] ?>
                            </td>
                            <td>
                                <strong><?= e($log['username'] ?? 'System / Anonymous') ?></strong>
                                <?php if (!empty($log['role'])): ?>
                                    <div>
                                        <span class="role-badge <?= e(strtolower($log['role'])) ?>">
                                            <?= e(strtoupper($log['role'])) ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-neutral" style="font-family: var(--font-mono); font-size: 0.725rem;">
                                    <?= e($log['action']) ?>
                                </span>
                            </td>
                            <td style="font-size: 0.8125rem; color: var(--color-text-main); max-width: 320px;">
                                <?= e($log['description']) ?>
                            </td>
                            <td style="font-family: var(--font-mono); font-size: 0.75rem; color: var(--color-text-muted);">
                                <?= e($log['ip_address'] ?? '127.0.0.1') ?>
                            </td>
                            <td style="font-size: 0.75rem; color: var(--color-text-muted); white-space: nowrap;">
                                <?= date('Y-m-d H:i:s', strtotime($log['created_at'])) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
