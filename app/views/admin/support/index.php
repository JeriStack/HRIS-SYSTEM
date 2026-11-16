<?php

/**
 * Admin Support Management Inbox View
 * Live database support metrics, full search/filtering (status, priority, category, date, assigned admin),
 * and clean triage launch into HR-submitted support tickets.
 */

require_once __DIR__ . '/../../partials/hr_support_helpers.php';

$stats = $stats ?? [];
$tickets = $tickets ?? [];
$filters = $filters ?? [
    'search'            => '',
    'status'            => '',
    'priority'          => '',
    'category'          => '',
    'date_from'         => '',
    'date_to'           => '',
    'assigned_admin_id' => '',
];
$admins = $admins ?? [];
$categories = $categories ?? [];
$priorities = $priorities ?? ['Low', 'Medium', 'High', 'Critical'];
$statuses = $statuses ?? [];
?>
<div class="page-header">
    <div class="page-title-group">
        <h1>HR Support Management</h1>
        <p>Investigate HR technical reports, assign Administrators, respond to HR, and track resolutions.</p>
    </div>
    <div class="page-actions">
        <a href="<?= url('/admin/dashboard') ?>" class="btn btn-secondary btn-sm">
            <?= icon('arrow-left', 15) ?>
            <span>System Overview</span>
        </a>
    </div>
</div>

<!-- Live Support KPI Cards -->
<section class="support-kpi-grid" aria-label="HR Support Queue Metrics">
    <div class="stat-card">
        <div class="stat-info">
            <span class="stat-label">Total Open Tickets</span>
            <span class="stat-value tabular-nums"><?= (int)($stats['total_open'] ?? 0) ?></span>
            <span class="stat-subtext">Active unresolved tickets</span>
        </div>
        <div class="stat-icon-wrapper icon-blue" aria-hidden="true"><?= icon('layers', 20) ?></div>
    </div>

    <div class="stat-card">
        <div class="stat-info">
            <span class="stat-label">Newly Submitted</span>
            <span class="stat-value tabular-nums"><?= (int)($stats['newly_submitted'] ?? 0) ?></span>
            <span class="stat-subtext">Awaiting acknowledgment</span>
        </div>
        <div class="stat-icon-wrapper icon-amber" aria-hidden="true"><?= icon('file-plus', 20) ?></div>
    </div>

    <div class="stat-card">
        <div class="stat-info">
            <span class="stat-label">Critical &amp; High Priority</span>
            <span class="stat-value tabular-nums"><?= (int)($stats['critical_and_high'] ?? 0) ?></span>
            <span class="stat-subtext">Urgent active issues</span>
        </div>
        <div class="stat-icon-wrapper icon-amber" aria-hidden="true"><?= icon('alert-triangle', 20) ?></div>
    </div>

    <div class="stat-card">
        <div class="stat-info">
            <span class="stat-label">Awaiting HR Response</span>
            <span class="stat-value tabular-nums"><?= (int)($stats['awaiting_hr'] ?? 0) ?></span>
            <span class="stat-subtext">Waiting for HR</span>
        </div>
        <div class="stat-icon-wrapper icon-purple" aria-hidden="true"><?= icon('mail', 20) ?></div>
    </div>

    <div class="stat-card">
        <div class="stat-info">
            <span class="stat-label">In Progress</span>
            <span class="stat-value tabular-nums"><?= (int)($stats['in_progress'] ?? 0) ?></span>
            <span class="stat-subtext">Under active remediation</span>
        </div>
        <div class="stat-icon-wrapper icon-teal" aria-hidden="true"><?= icon('clock', 20) ?></div>
    </div>

    <div class="stat-card">
        <div class="stat-info">
            <span class="stat-label">Recently Resolved</span>
            <span class="stat-value tabular-nums"><?= (int)($stats['recently_resolved'] ?? 0) ?></span>
            <span class="stat-subtext">Last 7 days</span>
        </div>
        <div class="stat-icon-wrapper icon-teal" aria-hidden="true"><?= icon('check-circle', 20) ?></div>
    </div>
</section>

<section class="card" aria-labelledby="adminSupportInboxHeading">
    <div class="card-header">
        <div>
            <h2 id="adminSupportInboxHeading" class="card-title">HR Support Inbox</h2>
            <span class="card-subtitle">All technical, access, and operational tickets reported by HR personnel</span>
        </div>
        <span class="badge badge-neutral tabular-nums"><?= count($tickets) ?> ticket<?= count($tickets) === 1 ? '' : 's' ?></span>
    </div>

    <search>
        <form method="GET" action="<?= url('/admin/support') ?>" class="support-filter-bar" aria-label="Search and filter HR support tickets">
            <div class="support-filter-field">
                <label for="adminSupSearch">Search Reference, Subject, or Submitter</label>
                <input
                    type="search"
                    id="adminSupSearch"
                    name="search"
                    class="form-control"
                    value="<?= e((string)($filters['search'] ?? '')) ?>"
                    placeholder="HR-SUP-..., subject, or HR username"
                >
            </div>
            <div class="support-filter-field">
                <label for="adminSupStatus">Status</label>
                <select id="adminSupStatus" name="status" class="form-select">
                    <option value="">All Statuses</option>
                    <?php foreach ($statuses as $st): ?>
                        <option value="<?= e($st) ?>"<?= ($filters['status'] ?? '') === $st ? ' selected' : '' ?>><?= e($st) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="support-filter-field">
                <label for="adminSupPriority">Priority</label>
                <select id="adminSupPriority" name="priority" class="form-select">
                    <option value="">All Priorities</option>
                    <?php foreach ($priorities as $prio): ?>
                        <option value="<?= e($prio) ?>"<?= ($filters['priority'] ?? '') === $prio ? ' selected' : '' ?>><?= e($prio) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="support-filter-field">
                <label for="adminSupCategory">Category</label>
                <select id="adminSupCategory" name="category" class="form-select">
                    <option value="">All Categories</option>
                    <?php foreach (array_keys($categories) as $catName): ?>
                        <option value="<?= e($catName) ?>"<?= ($filters['category'] ?? '') === $catName ? ' selected' : '' ?>><?= e($catName) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="support-filter-field">
                <label for="adminSupAssigned">Assigned Admin</label>
                <select id="adminSupAssigned" name="assigned_admin_id" class="form-select">
                    <option value="">All Administrators</option>
                    <option value="unassigned"<?= ($filters['assigned_admin_id'] ?? '') === 'unassigned' ? ' selected' : '' ?>>Unassigned Only</option>
                    <option value="mine"<?= ($filters['assigned_admin_id'] ?? '') === 'mine' ? ' selected' : '' ?>>Assigned to Me</option>
                    <?php foreach ($admins as $adm):
                        $aid = (string)$adm['user_id'];
                        $admLabel = hr_support_format_person(
                            $adm['first_name'] ?? null,
                            $adm['last_name'] ?? null,
                            $adm['username'] ?? null,
                            'Admin #' . $aid
                        );
                    ?>
                        <option value="<?= e($aid) ?>"<?= ($filters['assigned_admin_id'] ?? '') === $aid ? ' selected' : '' ?>><?= e($admLabel) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="support-filter-field">
                <label for="adminSupDateFrom">Submitted From</label>
                <input type="date" id="adminSupDateFrom" name="date_from" class="form-control" value="<?= e((string)($filters['date_from'] ?? '')) ?>">
            </div>
            <div class="support-filter-field">
                <label for="adminSupDateTo">Submitted To</label>
                <input type="date" id="adminSupDateTo" name="date_to" class="form-control" value="<?= e((string)($filters['date_to'] ?? '')) ?>">
            </div>
            <div style="display: flex; gap: 0.5rem; align-items: end;">
                <button type="submit" class="btn btn-primary btn-sm">Apply Filters</button>
                <a href="<?= url('/admin/support') ?>" class="btn btn-secondary btn-sm">Reset</a>
            </div>
        </form>
    </search>

    <div class="support-table-region" role="region" aria-label="HR Support Inbox Table" tabindex="0">
        <?php if (empty($tickets)): ?>
            <div class="empty-state" style="padding: var(--space-8) var(--space-4);">
                <div class="empty-state-icon" aria-hidden="true"><?= icon('check-circle', 28) ?></div>
                <h3>No HR support tickets match</h3>
                <p>Adjust your search filters or check back later when HR submits technical or access reports.</p>
            </div>
        <?php else: ?>
            <table class="table table-hover support-responsive-table">
                <thead>
                    <tr>
                        <th scope="col">Reference</th>
                        <th scope="col">Subject</th>
                        <th scope="col">Submitted By</th>
                        <th scope="col">Category</th>
                        <th scope="col">Priority</th>
                        <th scope="col">Submitted Date</th>
                        <th scope="col">Assigned Admin</th>
                        <th scope="col">Status</th>
                        <th scope="col">Last Updated</th>
                        <th scope="col" class="text-right">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tickets as $t):
                        $ref = (string)($t['ticket_ref'] ?? '');
                        $prio = (string)($t['priority'] ?? 'Medium');
                        $stat = (string)($t['status'] ?? 'Submitted');
                        $submitter = hr_support_format_person(
                            $t['submitter_first_name'] ?? null,
                            $t['submitter_last_name'] ?? null,
                            $t['submitter_username'] ?? null,
                            'HR Officer'
                        );
                        $assigned = hr_support_format_person(
                            $t['assigned_first_name'] ?? null,
                            $t['assigned_last_name'] ?? null,
                            $t['assigned_admin_username'] ?? null,
                            'Unassigned'
                        );
                        $createdTs = !empty($t['created_at']) ? date('M j, Y', strtotime((string)$t['created_at'])) : '—';
                        $updatedRaw = (string)($t['last_activity_at'] ?? $t['updated_at'] ?? $t['created_at'] ?? '');
                        $updatedTs = $updatedRaw !== '' ? date('M j, Y H:i', strtotime($updatedRaw)) : '—';
                    ?>
                        <tr>
                            <td data-label="Reference" class="tabular-nums"><strong><?= e($ref) ?></strong></td>
                            <td data-label="Subject"><?= e((string)($t['subject'] ?? '')) ?></td>
                            <td data-label="Submitted By"><?= e($submitter) ?></td>
                            <td data-label="Category"><?= e((string)($t['category'] ?? '')) ?></td>
                            <td data-label="Priority"><span class="badge <?= e(hr_support_priority_badge($prio)) ?>"><?= e($prio) ?></span></td>
                            <td data-label="Submitted Date" class="tabular-nums"><?= e($createdTs) ?></td>
                            <td data-label="Assigned Admin"><?= e($assigned) ?></td>
                            <td data-label="Status"><span class="badge <?= e(hr_support_status_badge($stat)) ?>"><?= e($stat) ?></span></td>
                            <td data-label="Last Updated" class="tabular-nums"><?= e($updatedTs) ?></td>
                            <td data-label="Action" class="text-right">
                                <a href="<?= url('/admin/support/tickets/' . rawurlencode($ref)) ?>" class="btn btn-primary btn-sm">Manage</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</section>
