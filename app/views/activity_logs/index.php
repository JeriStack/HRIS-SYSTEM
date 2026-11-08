<?php

declare(strict_types=1);

/**
 * Enterprise Audit Trail & Security Activity Monitoring Dashboard
 * Route: GET /activity-logs
 * Access: Administrator and HR Officer (Protected by RoleMiddleware(['admin', 'hr']))
 *
 * Implements real-time filtering, summary metrics, before/after diff inspection,
 * and formula-injection-safe CSV exports.
 */

$logs = $logs ?? [];
$stats = $stats ?? [];
$filters = $filters ?? [];
$activeFilters = $activeFilters ?? [];
$distinctActions = $distinctActions ?? [];
$categories = $categories ?? [];
$page = $page ?? 1;
$perPage = $perPage ?? 25;
$totalCount = $totalCount ?? count($logs);
$totalPages = $totalPages ?? 1;
$user = $user ?? currentUser();

$startEntry = $totalCount > 0 ? (($page - 1) * $perPage) + 1 : 0;
$endEntry = min($totalCount, $page * $perPage);

// Build query string for pagination and export links preserving active filters
$exportQueryParams = http_build_query($activeFilters);
$exportUrl = url('/activity-logs/export' . ($exportQueryParams !== '' ? '?' . $exportQueryParams : ''));
?>

<!-- Scoped Styling for Audit Trail Dashboard -->
<style>
.audit-status-dot {
    display: inline-block;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    margin-right: 0.375rem;
}
.audit-status-dot.success { background-color: var(--color-success, #10b981); }
.audit-status-dot.failure { background-color: var(--color-danger, #ef4444); }
.audit-status-dot.warning { background-color: var(--color-warning, #f59e0b); }

.diff-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.8125rem;
    margin-top: 0.5rem;
}
.diff-table th, .diff-table td {
    padding: 0.5rem 0.75rem;
    border: 1px solid var(--color-border, #e2e8f0);
    text-align: left;
}
.diff-table th {
    background: var(--color-bg-muted, #f8fafc);
    color: var(--color-text-muted, #64748b);
    font-weight: 600;
}
.diff-old {
    background-color: #fef2f2;
    color: #991b1b;
}
.diff-new {
    background-color: #ecfdf5;
    color: #065f46;
}
.user-avatar-initials {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: var(--color-bg-muted, #f1f5f9);
    border: 1px solid var(--color-border, #e2e8f0);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.75rem;
    color: var(--color-text-main, #0f172a);
    flex-shrink: 0;
}
</style>

<!-- =========================================================================
     TOP BREADCRUMBS & PAGE HEADER
     ========================================================================= -->
<div class="breadcrumb" style="margin-bottom: var(--space-3); font-size: 0.8125rem; color: var(--color-text-muted);">
    <a href="<?= url('/admin/dashboard') ?>" style="color: inherit; text-decoration: none;">Dashboard</a>
    <span style="margin: 0 0.5rem;">/</span>
    <span>Security &amp; Compliance</span>
    <span style="margin: 0 0.5rem;">/</span>
    <span style="color: var(--color-text-main); font-weight: 600;">Audit Trail</span>
</div>

<div class="page-header" style="margin-bottom: var(--space-6);">
    <div class="page-title-group">
        <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.25rem; flex-wrap: wrap;">
            <h1 style="margin: 0; font-size: 1.625rem; font-weight: 700; color: var(--color-text-main);">Audit Trail</h1>
            <span class="badge badge-primary">COMPLIANCE &amp; SECURITY</span>
        </div>
        <p style="margin: 0; font-size: 0.9375rem; color: var(--color-text-muted);">
            Monitor, investigate, and review important activities and state changes performed throughout NEXORA HR.
        </p>
    </div>
    <div class="page-actions" style="display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center;">
        <a href="<?= $exportUrl ?>" class="btn btn-outline btn-sm">
            <span>📥</span> Export CSV
        </a>
        <span style="font-size: 0.75rem; color: var(--color-text-muted); background: var(--color-bg-surface); padding: 0.4rem 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--color-border);">
            🔒 Immutable Compliance Log
        </span>
    </div>
</div>

<!-- =========================================================================
     SUMMARY METRIC CARDS
     ========================================================================= -->
<div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: var(--space-4); margin-bottom: var(--space-6);">
    <!-- Card 1: Total Events -->
    <div class="stat-card">
        <div class="stat-info">
            <span class="stat-label">Total Audit Events</span>
            <div class="stat-value" style="color: var(--color-text-main); font-size: 1.625rem;">
                <?= number_format((int)($stats['total_events'] ?? 0)) ?>
            </div>
            <div class="stat-subtext" style="font-size: 0.75rem; color: var(--color-text-muted);">
                Lifetime logged activities
            </div>
        </div>
        <div class="stat-icon-wrapper icon-blue" aria-hidden="true">📜</div>
    </div>

    <!-- Card 2: Successful Operations -->
    <div class="stat-card accent-green">
        <div class="stat-info">
            <span class="stat-label">Successful Operations</span>
            <div class="stat-value" style="color: var(--color-success, #10b981); font-size: 1.625rem;">
                <?= number_format((int)($stats['success_count'] ?? 0)) ?>
            </div>
            <div class="stat-subtext" style="font-size: 0.75rem; color: var(--color-text-muted);">
                Executed without error
            </div>
        </div>
        <div class="stat-icon-wrapper icon-teal" aria-hidden="true">✅</div>
    </div>

    <!-- Card 3: Failed / Blocked Operations -->
    <div class="stat-card accent-red">
        <div class="stat-info">
            <span class="stat-label">Failed / Blocked</span>
            <div class="stat-value" style="color: var(--color-danger, #ef4444); font-size: 1.625rem;">
                <?= number_format((int)($stats['failure_count'] ?? 0)) ?>
            </div>
            <div class="stat-subtext" style="font-size: 0.75rem; color: var(--color-text-muted);">
                Halted by security guards
            </div>
        </div>
        <div class="stat-icon-wrapper icon-red" aria-hidden="true">⚠️</div>
    </div>

    <!-- Card 4: Security Events -->
    <div class="stat-card accent-purple">
        <div class="stat-info">
            <span class="stat-label">Security &amp; Auth Events</span>
            <div class="stat-value" style="color: #8b5cf6; font-size: 1.625rem;">
                <?= number_format((int)($stats['security_count'] ?? 0)) ?>
            </div>
            <div class="stat-subtext" style="font-size: 0.75rem; color: var(--color-text-muted);">
                Logins, 2FA, and RBAC policies
            </div>
        </div>
        <div class="stat-icon-wrapper icon-purple" aria-hidden="true">🛡️</div>
    </div>

    <!-- Card 5: Recent Admin Changes -->
    <div class="stat-card accent-blue">
        <div class="stat-info">
            <span class="stat-label">Admin Actions (24h)</span>
            <div class="stat-value" style="color: var(--color-primary, #2563eb); font-size: 1.625rem;">
                <?= number_format((int)($stats['recent_admin_changes'] ?? 0)) ?>
            </div>
            <div class="stat-subtext" style="font-size: 0.75rem; color: var(--color-text-muted);">
                Privileged operations in 24h
            </div>
        </div>
        <div class="stat-icon-wrapper icon-blue" aria-hidden="true">⚡</div>
    </div>
</div>

<?php if (!empty($latestActivity)): ?>
    <!-- Fast-Inspection Banner for Most Recent Entry (DSA LIFO Stack) -->
    <div class="card" style="margin-bottom: var(--space-5); padding: 0.85rem 1.25rem; background: var(--color-bg-muted); border-left: 4px solid var(--color-primary); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
        <div>
            <div style="font-size: 0.7rem; font-weight: 700; color: var(--color-primary); text-transform: uppercase; letter-spacing: 0.05em;">
                🔔 Latest System Activity (DSA LIFO Stack Inspection)
            </div>
            <div style="font-size: 0.875rem; color: var(--color-text-main); font-weight: 600; margin-top: 0.2rem;">
                <span class="badge badge-neutral" style="font-size: 0.7rem; font-family: var(--font-mono);"><?= e($latestActivity['action'] ?? '') ?></span>
                <?= e($latestActivity['description'] ?? '') ?>
            </div>
        </div>
        <div style="font-size: 0.75rem; color: var(--color-text-muted); text-align: right;">
            <div>Actor: <strong><?= e($latestActivity['username'] ?? 'System') ?></strong></div>
            <div><?= !empty($latestActivity['created_at']) ? date('M d, Y h:i A', strtotime($latestActivity['created_at'])) : '' ?></div>
        </div>
    </div>
<?php endif; ?>

<!-- =========================================================================
     COMPREHENSIVE SEARCH & FILTER CONTROLS
     ========================================================================= -->
<div class="card" style="margin-bottom: var(--space-5);">
    <div class="card-body" style="padding: var(--space-4) var(--space-5);">
        <form method="GET" action="<?= url('/activity-logs') ?>" style="display: flex; flex-direction: column; gap: var(--space-3);">
            <div style="display: flex; gap: var(--space-3); flex-wrap: wrap; align-items: center;">
                <!-- Full Text Query Input -->
                <div style="position: relative; flex: 2; min-width: 260px;">
                    <span style="position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); color: var(--color-text-muted); font-size: 0.875rem;" aria-hidden="true">🔍</span>
                    <input type="text" 
                           name="q" 
                           class="form-control" 
                           placeholder="Search by actor, email, action, resource ID, or IP..." 
                           value="<?= e($filters['q'] ?? '') ?>"
                           style="padding-left: 2.25rem; font-size: 0.875rem;">
                </div>

                <!-- Category Filter -->
                <div style="flex: 1; min-width: 170px;">
                    <select name="category" class="form-control" style="font-size: 0.875rem;">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $catKey => $catLabel): ?>
                            <option value="<?= e($catKey) ?>" <?= ($filters['category'] ?? '') === $catKey ? 'selected' : '' ?>>
                                <?= e($catLabel) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Action Filter -->
                <div style="flex: 1; min-width: 180px;">
                    <select name="action" class="form-control" style="font-size: 0.875rem;">
                        <option value="">All Audited Actions (<?= count($distinctActions) ?>)</option>
                        <?php foreach ($distinctActions as $act): ?>
                            <option value="<?= e($act) ?>" <?= ($filters['action'] ?? '') === $act ? 'selected' : '' ?>>
                                <?= e($act) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Role Filter -->
                <div style="flex: 1; min-width: 140px;">
                    <select name="role" class="form-control" style="font-size: 0.875rem;">
                        <option value="">All Roles</option>
                        <option value="admin" <?= ($filters['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Administrator</option>
                        <option value="hr" <?= ($filters['role'] ?? '') === 'hr' ? 'selected' : '' ?>>Human Resources</option>
                        <option value="employee" <?= ($filters['role'] ?? '') === 'employee' ? 'selected' : '' ?>>Employee</option>
                    </select>
                </div>

                <!-- Status Filter -->
                <div style="flex: 1; min-width: 130px;">
                    <select name="status" class="form-control" style="font-size: 0.875rem;">
                        <option value="">All Statuses</option>
                        <option value="SUCCESS" <?= ($filters['status'] ?? '') === 'SUCCESS' ? 'selected' : '' ?>>Success</option>
                        <option value="FAILURE" <?= ($filters['status'] ?? '') === 'FAILURE' ? 'selected' : '' ?>>Failure / Blocked</option>
                        <option value="WARNING" <?= ($filters['status'] ?? '') === 'WARNING' ? 'selected' : '' ?>>Warning</option>
                    </select>
                </div>
            </div>

            <!-- Date Range Controls -->
            <div style="display: flex; gap: var(--space-3); flex-wrap: wrap; align-items: center; justify-content: space-between; padding-top: var(--space-2); border-top: 1px dashed var(--color-border);">
                <div style="display: flex; gap: var(--space-2); align-items: center; flex-wrap: wrap;">
                    <span style="font-size: 0.8125rem; color: var(--color-text-muted); font-weight: 600;">Date Range:</span>
                    <input type="date" name="from_date" class="form-control" style="font-size: 0.8125rem; width: 145px; padding: 0.25rem 0.5rem;" value="<?= e($filters['from_date'] ?? '') ?>" aria-label="From Date">
                    <span style="font-size: 0.8125rem; color: var(--color-text-muted);">to</span>
                    <input type="date" name="to_date" class="form-control" style="font-size: 0.8125rem; width: 145px; padding: 0.25rem 0.5rem;" value="<?= e($filters['to_date'] ?? '') ?>" aria-label="To Date">
                </div>

                <div style="display: flex; gap: 0.5rem;">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <span>🔍</span> Apply Filters
                    </button>
                    <?php if (!empty($activeFilters)): ?>
                        <a href="<?= url('/activity-logs') ?>" class="btn btn-outline btn-sm">
                            Clear Filters
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- =========================================================================
     AUDIT TRAIL DATA TABLE
     ========================================================================= -->
<div class="card" style="margin-bottom: var(--space-6);">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
        <div>
            <h2 class="card-title" style="margin: 0; font-size: 1rem; font-weight: 600;">
                <span>📜</span> Audit Activity Records
            </h2>
            <span style="font-size: 0.8125rem; color: var(--color-text-muted);">
                Showing entries <?= $startEntry ?> to <?= $endEntry ?> of <?= number_format($totalCount) ?> total recorded events
            </span>
        </div>
        <div>
            <span class="badge badge-neutral" style="font-size: 0.75rem;">Page <?= $page ?> of <?= $totalPages ?></span>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover" aria-label="Complete Audit Trail Directory">
            <thead>
                <tr>
                    <th style="width: 160px;">Timestamp</th>
                    <th style="width: 180px;">Actor Identity</th>
                    <th>Activity &amp; Operational Action</th>
                    <th style="width: 140px;">Category</th>
                    <th style="width: 140px;">Affected Resource</th>
                    <th style="width: 120px; text-align: center;">Outcome</th>
                    <th style="width: 130px;">IP Address</th>
                    <th style="width: 90px; text-align: right;">Inspection</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 3rem 1.5rem; color: var(--color-text-muted);">
                            <div style="font-size: 2rem; margin-bottom: 0.5rem;" aria-hidden="true">🔍</div>
                            <div style="font-weight: 600; color: var(--color-text-main); margin-bottom: 0.25rem;">No audit records found</div>
                            <div style="font-size: 0.875rem;">Try modifying your keyword, date range, or category filter.</div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                        <?php
                        $actorName = trim(($log['first_name'] ?? '') . ' ' . ($log['last_name'] ?? ''));
                        if ($actorName === '') {
                            $actorName = $log['username'] ?? 'System';
                        }
                        $initials = strtoupper(substr($log['username'] ?? 'SY', 0, 2));

                        $role = strtolower((string)($log['actor_role'] ?? $log['user_current_role'] ?? 'system'));
                        if ($role === 'admin') {
                            $roleBadge = 'badge-primary';
                            $roleLabel = 'Admin';
                        } elseif ($role === 'hr') {
                            $roleBadge = 'badge-info';
                            $roleLabel = 'HR';
                        } elseif ($role === 'employee') {
                            $roleBadge = 'badge-success';
                            $roleLabel = 'Employee';
                        } else {
                            $roleBadge = 'badge-neutral';
                            $roleLabel = 'System';
                        }

                        $status = strtoupper((string)($log['status'] ?? 'SUCCESS'));
                        if ($status === 'SUCCESS') {
                            $statusDot = 'success';
                            $statusBadge = 'badge-success';
                        } elseif ($status === 'FAILURE') {
                            $statusDot = 'failure';
                            $statusBadge = 'badge-danger';
                        } else {
                            $statusDot = 'warning';
                            $statusBadge = 'badge-warning';
                        }

                        $categoryName = $categories[$log['category'] ?? ''] ?? ucfirst($log['category'] ?? 'General');
                        ?>
                        <tr>
                            <!-- Timestamp -->
                            <td style="font-family: var(--font-mono); font-size: 0.8125rem; color: var(--color-text-muted); white-space: nowrap;">
                                <?= date('Y-m-d H:i:s', strtotime($log['created_at'])) ?>
                            </td>

                            <!-- Actor Identity -->
                            <td>
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <div class="user-avatar-initials" aria-hidden="true"><?= e($initials) ?></div>
                                    <div>
                                        <div style="font-weight: 600; color: var(--color-text-main); font-size: 0.8125rem; line-height: 1.2;">
                                            <?= e($actorName) ?>
                                        </div>
                                        <div style="display: flex; align-items: center; gap: 0.375rem; margin-top: 0.125rem;">
                                            <span class="badge <?= $roleBadge ?>" style="font-size: 0.6875rem; padding: 0.1rem 0.35rem;">
                                                <?= e($roleLabel) ?>
                                            </span>
                                            <span style="font-size: 0.6875rem; color: var(--color-text-muted);">
                                                <?= !empty($log['user_id']) ? '#' . (int)$log['user_id'] : 'System' ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <!-- Activity Description & Action Token -->
                            <td>
                                <div style="font-weight: 600; font-size: 0.8125rem; color: var(--color-text-main);">
                                    <?= e($log['description']) ?>
                                </div>
                                <code style="font-size: 0.75rem; color: var(--color-primary);"><?= e($log['action']) ?></code>
                            </td>

                            <!-- Category -->
                            <td>
                                <span class="badge badge-neutral" style="font-size: 0.75rem;">
                                    <?= e($categoryName) ?>
                                </span>
                            </td>

                            <!-- Affected Resource -->
                            <td>
                                <?php if (!empty($log['entity_type'])): ?>
                                    <div style="font-size: 0.8125rem; color: var(--color-text-main); font-weight: 500;">
                                        <?= e($log['entity_type']) ?>
                                    </div>
                                    <span style="font-size: 0.75rem; color: var(--color-text-muted); font-family: var(--font-mono);">
                                        ID #<?= e($log['entity_id'] ?? '—') ?>
                                    </span>
                                <?php else: ?>
                                    <span style="font-size: 0.75rem; color: var(--color-text-muted);">—</span>
                                <?php endif; ?>
                            </td>

                            <!-- Outcome Status -->
                            <td style="text-align: center;">
                                <span class="badge <?= $statusBadge ?>" style="font-size: 0.75rem;">
                                    <span class="audit-status-dot <?= $statusDot ?>" aria-hidden="true"></span>
                                    <?= e($status) ?>
                                </span>
                            </td>

                            <!-- IP Address -->
                            <td style="font-family: var(--font-mono); font-size: 0.75rem; color: var(--color-text-muted);">
                                <?= e($log['ip_address'] ?? '127.0.0.1') ?>
                            </td>

                            <!-- Action -->
                            <td style="text-align: right;">
                                <button type="button" class="btn btn-outline btn-xs" 
                                        onclick='openAuditDetailModal(<?= json_encode([
                                            "id" => $log["id"],
                                            "event_id" => $log["event_id"] ?? ("evt_" . $log["id"]),
                                            "timestamp" => $log["created_at"],
                                            "actor_name" => $actorName,
                                            "username" => $log["username"] ?? "System",
                                            "user_id" => $log["user_id"] ?? "System",
                                            "email" => $log["user_email"] ?? "—",
                                            "role" => strtoupper($role),
                                            "action" => $log["action"],
                                            "category" => $categoryName,
                                            "description" => $log["description"],
                                            "status" => $status,
                                            "entity_type" => $log["entity_type"] ?? "N/A",
                                            "entity_id" => $log["entity_id"] ?? "N/A",
                                            "old_values" => $log["old_values"] ?? null,
                                            "new_values" => $log["new_values"] ?? null,
                                            "ip_address" => $log["ip_address"] ?? "127.0.0.1",
                                            "user_agent" => $log["user_agent"] ?? "CLI / Internal",
                                            "request_id" => $log["request_id"] ?? "req_standard"
                                        ]) ?>)'
                                        aria-label="Inspect audit event #<?= (int)$log['id'] ?>">
                                    Inspect
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Server-Side Pagination Bar -->
    <?php if ($totalPages > 1): ?>
        <div class="card-footer" style="padding: var(--space-4) var(--space-6); background: var(--color-bg-muted); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
            <div style="font-size: 0.8125rem; color: var(--color-text-muted);">
                Showing <strong><?= $startEntry ?></strong> to <strong><?= $endEntry ?></strong> of <strong><?= number_format($totalCount) ?></strong> audit events
            </div>
            <div style="display: flex; gap: 0.375rem; align-items: center;">
                <?php
                $prevParams = array_merge($activeFilters, ['page' => max(1, $page - 1)]);
                $nextParams = array_merge($activeFilters, ['page' => min($totalPages, $page + 1)]);
                ?>
                <a href="<?= url('/activity-logs?' . http_build_query($prevParams)) ?>" 
                   class="btn btn-outline btn-sm <?= $page <= 1 ? 'disabled' : '' ?>"
                   style="<?= $page <= 1 ? 'pointer-events: none; opacity: 0.5;' : '' ?>">
                    &larr; Previous
                </a>

                <span style="font-size: 0.8125rem; font-weight: 600; padding: 0 0.5rem; color: var(--color-text-main);">
                    Page <?= $page ?> of <?= $totalPages ?>
                </span>

                <a href="<?= url('/activity-logs?' . http_build_query($nextParams)) ?>" 
                   class="btn btn-outline btn-sm <?= $page >= $totalPages ? 'disabled' : '' ?>"
                   style="<?= $page >= $totalPages ? 'pointer-events: none; opacity: 0.5;' : '' ?>">
                    Next &rarr;
                </a>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- =========================================================================
     AUDIT EVENT COMPREHENSIVE DETAIL MODAL
     ========================================================================= -->
<div class="modal-backdrop" id="auditDetailModal" role="dialog" aria-modal="true" aria-labelledby="auditModalTitle">
    <div class="modal" style="max-width: 680px;">
        <div class="modal-header">
            <h3 class="modal-title" id="auditModalTitle" style="display: flex; align-items: center; gap: 0.5rem;">
                <span>📜</span> Audit Event Details — #<span id="mId">—</span>
            </h3>
            <button type="button" class="modal-close" onclick="closeAuditDetailModal()" aria-label="Close dialog">&times;</button>
        </div>
        <div class="modal-body">
            <!-- Event & Actor Overview Grid -->
            <div style="background: var(--color-bg-muted); border-radius: var(--radius-md); padding: 1rem; margin-bottom: 1.25rem;">
                <div class="grid grid-cols-2" style="gap: 0.75rem; font-size: 0.8125rem;">
                    <div>
                        <span style="color: var(--color-text-muted); display: block;">Event Identifier:</span>
                        <code style="color: var(--color-primary); font-size: 0.75rem;" id="mEventId">—</code>
                    </div>
                    <div>
                        <span style="color: var(--color-text-muted); display: block;">Timestamp:</span>
                        <span style="font-family: var(--font-mono); color: var(--color-text-main);" id="mTimestamp">—</span>
                    </div>
                    <div>
                        <span style="color: var(--color-text-muted); display: block;">Actor User:</span>
                        <strong style="color: var(--color-text-main);" id="mActorName">—</strong>
                        <span style="color: var(--color-text-muted); font-size: 0.75rem;" id="mUsername">—</span>
                    </div>
                    <div>
                        <span style="color: var(--color-text-muted); display: block;">Actor Role:</span>
                        <span class="badge" id="mRoleBadge">—</span>
                    </div>
                    <div>
                        <span style="color: var(--color-text-muted); display: block;">Category:</span>
                        <span style="font-weight: 600; color: var(--color-text-main);" id="mCategory">—</span>
                    </div>
                    <div>
                        <span style="color: var(--color-text-muted); display: block;">Execution Outcome:</span>
                        <span class="badge" id="mStatusBadge">—</span>
                    </div>
                    <div style="grid-column: span 2;">
                        <span style="color: var(--color-text-muted); display: block;">Affected Resource:</span>
                        <span style="font-weight: 600; color: var(--color-text-main);" id="mResource">—</span>
                    </div>
                </div>
            </div>

            <!-- Human-Readable Operational Description -->
            <div style="margin-bottom: 1.25rem;">
                <span style="font-size: 0.8125rem; font-weight: 700; color: var(--color-text-muted); text-transform: uppercase; letter-spacing: 0.05em; display: block; margin-bottom: 0.25rem;">
                    Operational Description &amp; Action Code:
                </span>
                <div style="padding: 0.75rem 1rem; background: var(--color-bg-surface); border: 1px solid var(--color-border); border-radius: var(--radius-md);">
                    <div style="font-size: 0.875rem; font-weight: 600; color: var(--color-text-main); margin-bottom: 0.25rem;" id="mDescription">
                        —
                    </div>
                    <code style="font-size: 0.75rem; color: var(--color-primary);" id="mActionCode">—</code>
                </div>
            </div>

            <!-- State-Change Before & After Diff Inspection -->
            <div style="margin-bottom: 1.25rem;">
                <span style="font-size: 0.8125rem; font-weight: 700; color: var(--color-text-muted); text-transform: uppercase; letter-spacing: 0.05em; display: block; margin-bottom: 0.25rem;">
                    State-Change Mutation Details:
                </span>
                <div id="mDiffContainer" style="background: var(--color-bg-surface); border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 0.75rem; max-height: 200px; overflow-y: auto;">
                    <!-- Populated dynamically via JS -->
                </div>
            </div>

            <!-- Request & Origin Information -->
            <div style="background: var(--color-bg-muted); border-radius: var(--radius-md); padding: 0.75rem 1rem; font-size: 0.75rem; color: var(--color-text-muted);">
                <div style="margin-bottom: 0.25rem;">
                    <strong>Client IP:</strong> <code style="color: var(--color-text-main);" id="mIp">—</code> &bull; 
                    <strong>Request ID:</strong> <code style="color: var(--color-text-main);" id="mRequestId">—</code>
                </div>
                <div>
                    <strong>User Agent:</strong> <span id="mAgent" style="word-break: break-all;">—</span>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-primary btn-sm" onclick="closeAuditDetailModal()">Close</button>
        </div>
    </div>
</div>

<!-- =========================================================================
     CLIENT-SIDE JAVASCRIPT FOR DETAIL MODAL & DIFF RENDERING
     ========================================================================= -->
<script>
function openAuditDetailModal(data) {
    document.getElementById('mId').textContent = data.id;
    document.getElementById('mEventId').textContent = data.event_id;
    document.getElementById('mTimestamp').textContent = data.timestamp;
    document.getElementById('mActorName').textContent = data.actor_name;
    document.getElementById('mUsername').textContent = '(@' + data.username + ' • ID #' + data.user_id + ')';

    const roleBadge = document.getElementById('mRoleBadge');
    roleBadge.textContent = data.role;
    roleBadge.className = 'badge ' + (data.role === 'ADMIN' ? 'badge-primary' : (data.role === 'HR' ? 'badge-info' : 'badge-neutral'));

    document.getElementById('mCategory').textContent = data.category;

    const statusBadge = document.getElementById('mStatusBadge');
    statusBadge.textContent = data.status;
    statusBadge.className = 'badge ' + (data.status === 'SUCCESS' ? 'badge-success' : (data.status === 'FAILURE' ? 'badge-danger' : 'badge-warning'));

    document.getElementById('mResource').textContent = data.entity_type !== 'N/A' ? (data.entity_type + ' #' + data.entity_id) : 'General System Resource';
    document.getElementById('mDescription').textContent = data.description;
    document.getElementById('mActionCode').textContent = data.action;

    document.getElementById('mIp').textContent = data.ip_address;
    document.getElementById('mRequestId').textContent = data.request_id;
    document.getElementById('mAgent').textContent = data.user_agent;

    // Render Before/After Diff Table
    const diffContainer = document.getElementById('mDiffContainer');
    let oldVals = null;
    let newVals = null;

    try {
        if (data.old_values) {
            oldVals = typeof data.old_values === 'string' ? JSON.parse(data.old_values) : data.old_values;
        }
        if (data.new_values) {
            newVals = typeof data.new_values === 'string' ? JSON.parse(data.new_values) : data.new_values;
        }
    } catch (e) {
        oldVals = null;
        newVals = null;
    }

    if (!oldVals && !newVals) {
        diffContainer.innerHTML = '<span style="color: var(--color-text-muted); font-size: 0.8125rem;">No state-change payload was recorded for this event.</span>';
    } else {
        const allKeys = new Set([
            ...Object.keys(oldVals || {}),
            ...Object.keys(newVals || {})
        ]);

        let html = '<table class="diff-table"><thead><tr><th style="width: 30%;">Field Attribute</th><th style="width: 35%;">Previous Value (Old)</th><th style="width: 35%;">New Value</th></tr></thead><tbody>';

        allKeys.forEach(k => {
            const vOld = oldVals && oldVals[k] !== undefined ? JSON.stringify(oldVals[k]) : '<em>(empty)</em>';
            const vNew = newVals && newVals[k] !== undefined ? JSON.stringify(newVals[k]) : '<em>(empty)</em>';
            const isChanged = vOld !== vNew;

            html += `<tr>
                <td><strong>${escapeHtml(k)}</strong></td>
                <td class="${isChanged ? 'diff-old' : ''}">${vOld}</td>
                <td class="${isChanged ? 'diff-new' : ''}">${vNew}</td>
            </tr>`;
        });

        html += '</tbody></table>';
        diffContainer.innerHTML = html;
    }

    const modal = document.getElementById('auditDetailModal');
    if (modal) {
        modal.classList.add('is-open');
    }
}

function closeAuditDetailModal() {
    const modal = document.getElementById('auditDetailModal');
    if (modal) {
        modal.classList.remove('is-open');
    }
}

function escapeHtml(text) {
    if (!text) return '';
    return text.toString()
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeAuditDetailModal();
    }
});

document.addEventListener('click', function(event) {
    if (event.target.classList.contains('modal-backdrop')) {
        closeAuditDetailModal();
    }
});
</script>
