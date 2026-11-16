<?php
/**
 * HR Support Inbox & Overview View
 * Scopes confidential complaint metrics and rows strictly to designated non-conflicted handlers.
 */
$stats = $stats ?? [];
$tickets = $tickets ?? [];
$filters = $filters ?? ['search' => '', 'category' => '', 'status' => '', 'priority' => '', 'scope' => ''];
$canHandleConfidential = !empty($canHandleConfidential);
$canManageHandlers = !empty($canManageHandlers);
$showConfidentialCard = !empty($stats['show_confidential_metric']);
?>
<div class="page-header">
    <div class="page-title-group">
        <h1>HR Support Inbox</h1>
        <p>Manage employee support tickets, follow-up requests, assignments, and authorized case resolutions.</p>
    </div>
    <div class="page-actions">
        <?php if ($canManageHandlers): ?>
            <a href="<?= url('/hr/support/handlers') ?>" class="btn btn-secondary btn-sm">
                <?= icon('lock', 15) ?>
                <span>Manage Confidential Handlers</span>
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- Overview KPI Cards -->
<section class="support-kpi-grid" aria-label="Support Inbox Overview Metrics">
    <div class="stat-card">
        <div class="stat-info">
            <span class="stat-label">Total Open Requests</span>
            <span class="stat-value tabular-nums"><?= (int)($stats['total_open'] ?? 0) ?></span>
            <span class="stat-subtext">Active queue workload</span>
        </div>
        <div class="stat-icon-wrapper icon-blue" aria-hidden="true"><?= icon('layers', 20) ?></div>
    </div>

    <div class="stat-card">
        <div class="stat-info">
            <span class="stat-label">New Submissions</span>
            <span class="stat-value tabular-nums"><?= (int)($stats['new_submissions'] ?? 0) ?></span>
            <span class="stat-subtext">Awaiting initial triage</span>
        </div>
        <div class="stat-icon-wrapper icon-amber" aria-hidden="true"><?= icon('file-plus', 20) ?></div>
    </div>

    <div class="stat-card">
        <div class="stat-info">
            <span class="stat-label">Under Review</span>
            <span class="stat-value tabular-nums"><?= (int)($stats['under_review'] ?? 0) ?></span>
            <span class="stat-subtext">In progress with HR</span>
        </div>
        <div class="stat-icon-wrapper icon-teal" aria-hidden="true"><?= icon('clock', 20) ?></div>
    </div>

    <div class="stat-card">
        <div class="stat-info">
            <span class="stat-label">Waiting for Employee</span>
            <span class="stat-value tabular-nums"><?= (int)($stats['waiting_for_employee'] ?? 0) ?></span>
            <span class="stat-subtext">Pending employee reply</span>
        </div>
        <div class="stat-icon-wrapper icon-purple" aria-hidden="true"><?= icon('mail', 20) ?></div>
    </div>

    <div class="stat-card">
        <div class="stat-info">
            <span class="stat-label">Resolved Requests</span>
            <span class="stat-value tabular-nums"><?= (int)($stats['resolved_count'] ?? 0) ?></span>
            <span class="stat-subtext">Ready or awaiting confirm</span>
        </div>
        <div class="stat-icon-wrapper icon-teal" aria-hidden="true"><?= icon('check-circle', 20) ?></div>
    </div>

    <?php if ($showConfidentialCard): ?>
        <div class="stat-card">
            <div class="stat-info">
                <span class="stat-label">Confidential Attention</span>
                <span class="stat-value tabular-nums"><?= (int)($stats['confidential_attention'] ?? 0) ?></span>
                <span class="stat-subtext">Open authorized complaints</span>
            </div>
            <div class="stat-icon-wrapper icon-amber" aria-hidden="true"><?= icon('lock', 20) ?></div>
        </div>
    <?php endif; ?>
</section>

<section class="card" aria-labelledby="hrSupportInboxHeading">
    <div class="card-header">
        <div>
            <h2 id="hrSupportInboxHeading" class="card-title">Support Inbox</h2>
            <span class="card-subtitle">
                <?= $canHandleConfidential
                    ? 'Showing general support requests and confidential cases you are cleared to handle'
                    : 'Showing general support requests only (confidential workplace complaints require designated handler clearance)' ?>
            </span>
        </div>
        <span class="badge badge-neutral tabular-nums"><?= count($tickets) ?> ticket<?= count($tickets) === 1 ? '' : 's' ?></span>
    </div>

    <search>
        <form method="GET" action="<?= url('/hr/support') ?>" class="support-filter-bar" aria-label="Filter HR support inbox">
            <div class="support-filter-field">
                <label for="hrInboxSearch">Search</label>
                <input
                    type="search"
                    id="hrInboxSearch"
                    name="search"
                    class="form-control"
                    placeholder="Ticket ref, employee, subject"
                    value="<?= e((string)$filters['search']) ?>"
                >
            </div>
            <div class="support-filter-field">
                <label for="hrInboxCategory">Category</label>
                <select id="hrInboxCategory" name="category" class="form-select">
                    <option value="">All Categories</option>
                    <?php foreach ($categories ?? [] as $catKey => $catMeta): ?>
                        <?php if ($catKey === 'Workplace Complaint' && !$canHandleConfidential) { continue; } ?>
                        <option value="<?= e((string)$catKey) ?>"<?= $filters['category'] === $catKey ? ' selected' : '' ?>>
                            <?= e((string)($catMeta['label'] ?? $catKey)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="support-filter-field">
                <label for="hrInboxStatus">Status</label>
                <select id="hrInboxStatus" name="status" class="form-select">
                    <option value="">All Statuses</option>
                    <?php foreach ($statuses ?? [] as $st): ?>
                        <option value="<?= e((string)$st) ?>"<?= $filters['status'] === $st ? ' selected' : '' ?>>
                            <?= e((string)$st) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="support-filter-field">
                <label for="hrInboxPriority">Priority</label>
                <select id="hrInboxPriority" name="priority" class="form-select">
                    <option value="">All Priorities</option>
                    <?php foreach ($priorities ?? [] as $pr): ?>
                        <option value="<?= e((string)$pr) ?>"<?= $filters['priority'] === $pr ? ' selected' : '' ?>>
                            <?= e((string)$pr) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="support-filter-field">
                <label for="hrInboxScope">Queue Scope</label>
                <select id="hrInboxScope" name="scope" class="form-select">
                    <option value="">All Accessible</option>
                    <option value="mine"<?= $filters['scope'] === 'mine' ? ' selected' : '' ?>>Assigned to Me</option>
                    <option value="unassigned"<?= $filters['scope'] === 'unassigned' ? ' selected' : '' ?>>Unassigned Open</option>
                    <option value="general"<?= $filters['scope'] === 'general' ? ' selected' : '' ?>>General Support Only</option>
                    <option value="public_auth"<?= $filters['scope'] === 'public_auth' ? ' selected' : '' ?>>Public Login Assistance</option>
                    <?php if ($canHandleConfidential): ?>
                        <option value="confidential"<?= $filters['scope'] === 'confidential' ? ' selected' : '' ?>>Confidential Complaints</option>
                    <?php endif; ?>
                </select>
            </div>
            <div style="display: flex; gap: var(--space-2); align-items: center;">
                <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                <?php if ($filters['search'] !== '' || $filters['category'] !== '' || $filters['status'] !== '' || $filters['priority'] !== '' || $filters['scope'] !== ''): ?>
                    <a href="<?= url('/hr/support') ?>" class="btn btn-secondary btn-sm">Reset</a>
                <?php endif; ?>
            </div>
        </form>
    </search>

    <div class="support-table-region">
        <?php if (empty($tickets)): ?>
            <div style="padding: 2.5rem; text-align: center;" class="text-sm text-muted">
                No accessible support tickets match the selected filters.
            </div>
        <?php else: ?>
            <table class="table table-hover support-responsive-table">
                <caption class="support-table-caption">Accessible employee support requests and authorized confidential complaints</caption>
                <thead>
                    <tr>
                        <th scope="col">Ticket Reference</th>
                        <th scope="col">Employee</th>
                        <th scope="col">Category</th>
                        <th scope="col">Subject</th>
                        <th scope="col">Submitted</th>
                        <th scope="col">Priority</th>
                        <th scope="col">Status</th>
                        <th scope="col">Assigned Handler</th>
                        <th scope="col" class="text-right">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tickets as $t):
                        $status = (string)($t['status'] ?? 'Submitted');
                        $statusBadge = match ($status) {
                            'Resolved', 'Closed'   => 'badge-success',
                            'Waiting for Employee' => 'badge-warning',
                            'Under Review'         => 'badge-info',
                            default                => 'badge-primary',
                        };
                        $prio = (string)($t['priority'] ?? 'Medium');
                        $prioBadge = match ($prio) {
                            'Urgent' => 'badge-danger',
                            'High'   => 'badge-warning',
                            'Low'    => 'badge-neutral',
                            default  => 'badge-info',
                        };
                        $isConf = !empty($t['is_confidential']) || (($t['request_type'] ?? '') === 'complaint');
                        $isPublic = !empty($t['is_public_ticket']) || (($t['support_channel'] ?? '') === 'public_auth');
                        $empName = $isPublic
                            ? (trim((string)($t['requester_name'] ?? '')) ?: 'Public Requester')
                            : trim((string)($t['first_name'] ?? '') . ' ' . (string)($t['last_name'] ?? ''));
                        $empSub = $isPublic
                            ? trim((string)($t['requester_email'] ?? '')) . ' · Public Inquiry'
                            : (string)($t['employee_no'] ?? '');
                        $handlerName = trim((string)($t['assigned_first_name'] ?? '') . ' ' . (string)($t['assigned_last_name'] ?? ''));
                        if ($handlerName === '') {
                            $handlerName = (string)($t['assigned_to_username'] ?? 'Unassigned');
                        }
                    ?>
                        <tr>
                            <td data-label="Ticket Reference">
                                <a href="<?= url('/hr/support/' . (int)$t['id']) ?>" class="tabular-nums" style="font-weight: 700;">
                                    <?= e((string)$t['ticket_ref']) ?>
                                </a>
                            </td>
                            <td data-label="<?= $isPublic ? 'Requester' : 'Employee' ?>">
                                <div style="font-weight: 600;"><?= e($empName) ?></div>
                                <div class="text-xs text-muted"><?= e($empSub) ?></div>
                            </td>
                            <td data-label="Category">
                                <span><?= e((string)$t['category']) ?></span>
                                <?php if ($isPublic): ?>
                                    <span class="badge badge-info" style="margin-left: 0.35rem;">Public Login Issue</span>
                                <?php endif; ?>
                                <?php if ($isConf): ?>
                                    <span class="badge badge-warning" style="margin-left: 0.3rem;">Confidential</span>
                                <?php endif; ?>
                                <?php if (!empty($t['escalated_flag'])): ?>
                                    <span class="badge badge-danger" style="margin-left: 0.3rem;">Escalated</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Subject">
                                <strong><?= e((string)$t['subject']) ?></strong>
                                <?php if (!empty($t['complaint_category'])): ?>
                                    <div class="text-xs text-muted"><?= e((string)$t['complaint_category']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td data-label="Submitted" class="text-xs text-muted">
                                <?= !empty($t['created_at']) ? e(date('M j, Y', strtotime((string)$t['created_at']))) : '—' ?>
                            </td>
                            <td data-label="Priority">
                                <span class="badge <?= $prioBadge ?>"><?= e($prio) ?></span>
                            </td>
                            <td data-label="Status">
                                <span class="badge <?= $statusBadge ?>"><?= e($status) ?></span>
                            </td>
                            <td data-label="Assigned Handler" class="text-sm"><?= e($handlerName) ?></td>
                            <td data-label="Action" class="text-right">
                                <a href="<?= url('/hr/support/' . (int)$t['id']) ?>" class="btn btn-secondary btn-sm">Open Case</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</section>
