<?php

/**
 * HR Help & Support Center View ("My Support Tickets")
 * Enables HR personnel to submit technical/operational tickets to Administrators,
 * track own ticket status/assignments, and filter submitted cases cleanly.
 */

require_once __DIR__ . '/../../partials/hr_support_helpers.php';

$tickets = $tickets ?? [];
$stats = $stats ?? ['total' => 0, 'open' => 0, 'waiting_for_hr' => 0, 'resolved' => 0];
$filters = $filters ?? ['search' => '', 'status' => '', 'category' => '', 'date_from' => '', 'date_to' => ''];
$categories = $categories ?? [];
$statuses = $statuses ?? [];
$canSubmit = !empty($canSubmit);
$reopenWindowDays = (int)($reopenWindowDays ?? 14);
?>
<div class="page-header">
    <div class="page-title-group">
        <h1>Help &amp; Support Center</h1>
        <p>Report technical errors, access blockers, or system issues directly to System Administrators.</p>
    </div>
    <div class="page-actions">
        <?php if ($canSubmit): ?>
            <a href="<?= url('/hr/help/create') ?>" class="btn btn-primary btn-sm">
                <?= icon('plus-circle', 15) ?>
                <span>Submit Support Ticket</span>
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- HR Ticket KPI Summary -->
<section class="support-kpi-grid" aria-label="My HR Support Metrics">
    <div class="stat-card">
        <div class="stat-info">
            <span class="stat-label">Total Submitted</span>
            <span class="stat-value tabular-nums"><?= (int)($stats['total'] ?? 0) ?></span>
            <span class="stat-subtext">Tickets filed by you</span>
        </div>
        <div class="stat-icon-wrapper icon-blue" aria-hidden="true"><?= icon('layers', 20) ?></div>
    </div>

    <div class="stat-card">
        <div class="stat-info">
            <span class="stat-label">Open Tickets</span>
            <span class="stat-value tabular-nums"><?= (int)($stats['open'] ?? 0) ?></span>
            <span class="stat-subtext">Active investigation queue</span>
        </div>
        <div class="stat-icon-wrapper icon-amber" aria-hidden="true"><?= icon('clock', 20) ?></div>
    </div>

    <div class="stat-card">
        <div class="stat-info">
            <span class="stat-label">Waiting for HR</span>
            <span class="stat-value tabular-nums"><?= (int)($stats['waiting_for_hr'] ?? 0) ?></span>
            <span class="stat-subtext">Admin requested details</span>
        </div>
        <div class="stat-icon-wrapper icon-purple" aria-hidden="true"><?= icon('mail', 20) ?></div>
    </div>

    <div class="stat-card">
        <div class="stat-info">
            <span class="stat-label">Resolved</span>
            <span class="stat-value tabular-nums"><?= (int)($stats['resolved'] ?? 0) ?></span>
            <span class="stat-subtext">Ready to verify &amp; close</span>
        </div>
        <div class="stat-icon-wrapper icon-teal" aria-hidden="true"><?= icon('check-circle', 20) ?></div>
    </div>
</section>

<div class="support-privacy-notice" role="note" style="margin-bottom: var(--space-5);">
    <span class="support-privacy-notice-icon" aria-hidden="true"><?= icon('info', 18) ?></span>
    <div class="text-sm">
        <strong>Ticket Tracking &amp; Closure Policy:</strong>
        Resolved tickets may be confirmed as Closed or reopened within <strong class="tabular-nums"><?= $reopenWindowDays ?></strong> days if the issue persists. Once closed, only an Administrator can reopen the ticket. Looking for Employee-submitted inquiries? Visit the <a href="<?= url('/hr/support') ?>">Employee Support Inbox</a>.
    </div>
</div>

<section class="card" aria-labelledby="myHrTicketsHeading">
    <div class="card-header">
        <div>
            <h2 id="myHrTicketsHeading" class="card-title">My Support Tickets</h2>
            <span class="card-subtitle">Only tickets submitted under your HR account appear in this view</span>
        </div>
        <span class="badge badge-neutral tabular-nums"><?= count($tickets) ?> shown</span>
    </div>

    <search>
        <form method="GET" action="<?= url('/hr/help') ?>" class="support-filter-bar" aria-label="Filter my HR support tickets">
            <div class="support-filter-field">
                <label for="hrHelpSearch">Search Reference or Subject</label>
                <input
                    type="search"
                    id="hrHelpSearch"
                    name="search"
                    class="form-control"
                    value="<?= e((string)($filters['search'] ?? '')) ?>"
                    placeholder="HR-SUP-... or keyword"
                >
            </div>
            <div class="support-filter-field">
                <label for="hrHelpStatus">Status</label>
                <select id="hrHelpStatus" name="status" class="form-select">
                    <option value="">All Statuses</option>
                    <?php foreach ($statuses as $st): ?>
                        <option value="<?= e($st) ?>"<?= ($filters['status'] ?? '') === $st ? ' selected' : '' ?>><?= e($st) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="support-filter-field">
                <label for="hrHelpCategory">Category</label>
                <select id="hrHelpCategory" name="category" class="form-select">
                    <option value="">All Categories</option>
                    <?php foreach (array_keys($categories) as $catName): ?>
                        <option value="<?= e($catName) ?>"<?= ($filters['category'] ?? '') === $catName ? ' selected' : '' ?>><?= e($catName) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="support-filter-field">
                <label for="hrHelpDateFrom">From Date</label>
                <input type="date" id="hrHelpDateFrom" name="date_from" class="form-control" value="<?= e((string)($filters['date_from'] ?? '')) ?>">
            </div>
            <div class="support-filter-field">
                <label for="hrHelpDateTo">To Date</label>
                <input type="date" id="hrHelpDateTo" name="date_to" class="form-control" value="<?= e((string)($filters['date_to'] ?? '')) ?>">
            </div>
            <div style="display: flex; gap: 0.5rem; align-items: end;">
                <button type="submit" class="btn btn-primary btn-sm">Apply Filters</button>
                <a href="<?= url('/hr/help') ?>" class="btn btn-secondary btn-sm">Reset</a>
            </div>
        </form>
    </search>

    <div class="support-table-region" role="region" aria-label="My HR Support Tickets Table" tabindex="0">
        <?php if (empty($tickets)): ?>
            <div class="empty-state" style="padding: var(--space-8) var(--space-4);">
                <div class="empty-state-icon" aria-hidden="true"><?= icon('shield', 28) ?></div>
                <h3>No HR support tickets found</h3>
                <p>No tickets match your filters, or you have not reported an issue to Administrators yet.</p>
                <?php if ($canSubmit): ?>
                    <a href="<?= url('/hr/help/create') ?>" class="btn btn-primary btn-sm" style="margin-top: var(--space-3);">
                        Submit Your First Ticket
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <table class="table table-hover support-responsive-table">
                <thead>
                    <tr>
                        <th scope="col">Reference</th>
                        <th scope="col">Subject</th>
                        <th scope="col">Category</th>
                        <th scope="col">Priority</th>
                        <th scope="col">Submitted</th>
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
                        $assigned = hr_support_format_person(
                            $t['assigned_first_name'] ?? null,
                            $t['assigned_last_name'] ?? null,
                            $t['assigned_admin_username'] ?? null,
                            'Pending Assignment'
                        );
                        $createdTs = !empty($t['created_at']) ? date('M j, Y', strtotime((string)$t['created_at'])) : '—';
                        $updatedRaw = (string)($t['last_activity_at'] ?? $t['updated_at'] ?? $t['created_at'] ?? '');
                        $updatedTs = $updatedRaw !== '' ? date('M j, Y H:i', strtotime($updatedRaw)) : '—';
                    ?>
                        <tr>
                            <td data-label="Reference" class="tabular-nums"><strong><?= e($ref) ?></strong></td>
                            <td data-label="Subject"><?= e((string)($t['subject'] ?? '')) ?></td>
                            <td data-label="Category"><?= e((string)($t['category'] ?? '')) ?></td>
                            <td data-label="Priority"><span class="badge <?= e(hr_support_priority_badge($prio)) ?>"><?= e($prio) ?></span></td>
                            <td data-label="Submitted" class="tabular-nums"><?= e($createdTs) ?></td>
                            <td data-label="Assigned Admin"><?= e($assigned) ?></td>
                            <td data-label="Status"><span class="badge <?= e(hr_support_status_badge($stat)) ?>"><?= e($stat) ?></span></td>
                            <td data-label="Last Updated" class="tabular-nums"><?= e($updatedTs) ?></td>
                            <td data-label="Action" class="text-right">
                                <a href="<?= url('/hr/help/tickets/' . rawurlencode($ref)) ?>" class="btn btn-secondary btn-sm">Open Thread</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</section>
