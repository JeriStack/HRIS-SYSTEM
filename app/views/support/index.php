<?php
/**
 * Employee Help & Support Center View
 * Provides navigation to submit requests/complaints, support guidelines, filters, and ticket listing.
 */
$tickets = $tickets ?? [];
$filters = $filters ?? ['search' => '', 'category' => '', 'status' => '', 'request_type' => ''];
$openCount = (int)($openCount ?? 0);
$reopenWindowDays = (int)($reopenWindowDays ?? 14);
$hasLinkedEmployee = !empty($hasLinkedEmployee);
?>
<div class="page-header">
    <div class="page-title-group">
        <h1>Help &amp; Support Center</h1>
        <p>Ask HR questions, report system issues, file confidential workplace complaints, and track responses.</p>
    </div>
    <div class="page-actions">
        <a href="<?= url('/support/request') ?>" class="btn btn-primary btn-sm">
            <?= icon('plus-circle', 15) ?>
            <span>Submit Support Request</span>
        </a>
        <a href="<?= url('/support/complaint') ?>" class="btn btn-secondary btn-sm">
            <?= icon('lock', 15) ?>
            <span>Submit Workplace Complaint</span>
        </a>
    </div>
</div>

<?php if (!$hasLinkedEmployee): ?>
    <div class="alert alert-warning" role="alert">
        <span class="alert-icon" aria-hidden="true"><?= icon('alert-triangle', 18) ?></span>
        <div class="alert-content">
            <strong>Employee Profile Required:</strong> Your user account is not currently linked to an active employee record. Please contact Human Resources to link your employee profile before submitting a ticket.
        </div>
    </div>
<?php endif; ?>

<section class="support-center-actions" aria-label="Support options and guidelines">
    <article class="support-action-tile">
        <div>
            <div class="support-action-tile-head">
                <span class="support-action-icon" aria-hidden="true"><?= icon('file-plus', 18) ?></span>
                <div>
                    <h2 style="font-size: var(--font-size-md); margin-bottom: 0.25rem;">Submit a Support Request</h2>
                    <p class="text-sm text-muted" style="margin-bottom: 0;">
                        General HR inquiries, portal or technical bugs, leave concerns, profile correction follow-ups, or policy questions.
                    </p>
                </div>
            </div>
        </div>
        <div>
            <a href="<?= url('/support/request') ?>" class="btn btn-primary btn-sm">Open Support Form</a>
        </div>
    </article>

    <article class="support-action-tile">
        <div>
            <div class="support-action-tile-head">
                <span class="support-action-icon is-confidential" aria-hidden="true"><?= icon('lock', 18) ?></span>
                <div>
                    <h2 style="font-size: var(--font-size-md); margin-bottom: 0.25rem;">Submit a Workplace Complaint</h2>
                    <p class="text-sm text-muted" style="margin-bottom: 0;">
                        Formal workplace conduct, employee relations, harassment, or policy concerns handled by specifically authorized HR officers.
                    </p>
                </div>
            </div>
        </div>
        <div>
            <a href="<?= url('/support/complaint') ?>" class="btn btn-secondary btn-sm">File Confidential Complaint</a>
        </div>
    </article>

    <article class="support-action-tile">
        <div>
            <div class="support-action-tile-head">
                <span class="support-action-icon" aria-hidden="true"><?= icon('info', 18) ?></span>
                <div>
                    <h2 style="font-size: var(--font-size-md); margin-bottom: 0.25rem;">Support Guidelines</h2>
                    <ul class="support-disclosure-list" style="margin-top: 0.35rem;">
                        <li>Submissions are linked to your authenticated employee account (not anonymous).</li>
                        <li>Confidential complaints are hidden from general HR users and any conflicted party.</li>
                        <li>You may reopen Resolved tickets within <?= $reopenWindowDays ?> days or confirm final closure.</li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="text-xs text-muted">
            Currently Open: <strong class="tabular-nums"><?= $openCount ?></strong>
        </div>
    </article>
</section>

<section class="card" aria-labelledby="mySupportRequestsHeading">
    <div class="card-header">
        <div>
            <h2 id="mySupportRequestsHeading" class="card-title">My Support Requests &amp; Complaints</h2>
            <span class="card-subtitle">Only requests submitted under your authenticated employee account appear here</span>
        </div>
        <span class="badge badge-neutral tabular-nums"><?= count($tickets) ?> shown</span>
    </div>

    <search>
        <form method="GET" action="<?= url('/support') ?>" class="support-filter-bar" aria-label="Filter my support requests">
            <div class="support-filter-field">
                <label for="filterSupportSearch">Search</label>
                <input
                    type="search"
                    id="filterSupportSearch"
                    name="search"
                    class="form-control"
                    placeholder="Ticket ref (SUP-...), subject, category"
                    value="<?= e((string)$filters['search']) ?>"
                >
            </div>
            <div class="support-filter-field">
                <label for="filterSupportCategory">Category</label>
                <select id="filterSupportCategory" name="category" class="form-select">
                    <option value="">All Categories</option>
                    <?php foreach ($categories ?? [] as $catKey => $catMeta): ?>
                        <option value="<?= e((string)$catKey) ?>"<?= $filters['category'] === $catKey ? ' selected' : '' ?>>
                            <?= e((string)($catMeta['label'] ?? $catKey)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="support-filter-field">
                <label for="filterSupportStatus">Status</label>
                <select id="filterSupportStatus" name="status" class="form-select">
                    <option value="">All Statuses</option>
                    <?php foreach ($statuses ?? [] as $st): ?>
                        <option value="<?= e((string)$st) ?>"<?= $filters['status'] === $st ? ' selected' : '' ?>>
                            <?= e((string)$st) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="display: flex; gap: var(--space-2); align-items: center;">
                <button type="submit" class="btn btn-primary btn-sm">Apply Filters</button>
                <?php if ($filters['search'] !== '' || $filters['category'] !== '' || $filters['status'] !== '' || $filters['request_type'] !== ''): ?>
                    <a href="<?= url('/support') ?>" class="btn btn-secondary btn-sm">Reset</a>
                <?php endif; ?>
            </div>
        </form>
    </search>

    <div class="support-table-region">
        <?php if (empty($tickets)): ?>
            <div style="padding: 2.5rem; text-align: center;">
                <p class="text-sm text-muted" style="margin-bottom: var(--space-3);">
                    No support requests or workplace complaints match your current filter.
                </p>
                <a href="<?= url('/support/request') ?>" class="btn btn-primary btn-sm">Submit Your First Request</a>
            </div>
        <?php else: ?>
            <table class="table table-hover support-responsive-table">
                <caption class="support-table-caption">Employee support requests and confidential workplace complaints</caption>
                <thead>
                    <tr>
                        <th scope="col">Ticket Reference</th>
                        <th scope="col">Category</th>
                        <th scope="col">Subject</th>
                        <th scope="col">Submitted Date</th>
                        <th scope="col">Assigned Handler</th>
                        <th scope="col">Status</th>
                        <th scope="col">Last Updated</th>
                        <th scope="col" class="text-right">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tickets as $t):
                        $status = (string)($t['status'] ?? 'Submitted');
                        $badgeClass = match ($status) {
                            'Resolved', 'Closed'   => 'badge-success',
                            'Waiting for Employee' => 'badge-warning',
                            'Under Review'         => 'badge-info',
                            default                => 'badge-primary',
                        };
                        $isConf = !empty($t['is_confidential']) || (($t['request_type'] ?? '') === 'complaint');
                        $submittedAt = !empty($t['created_at']) ? date('M j, Y', strtotime((string)$t['created_at'])) : '—';
                        $updatedAt = !empty($t['last_activity_at']) ? date('M j, Y g:i A', strtotime((string)$t['last_activity_at'])) : '—';
                    ?>
                        <tr>
                            <td data-label="Ticket Reference">
                                <a href="<?= url('/support/' . (int)$t['id']) ?>" class="tabular-nums" style="font-weight: 700;">
                                    <?= e((string)$t['ticket_ref']) ?>
                                </a>
                            </td>
                            <td data-label="Category">
                                <span><?= e((string)$t['category']) ?></span>
                                <?php if ($isConf): ?>
                                    <span class="badge badge-warning" style="margin-left: 0.35rem;">Confidential</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Subject">
                                <strong><?= e((string)$t['subject']) ?></strong>
                                <?php if (!empty($t['complaint_category'])): ?>
                                    <div class="text-xs text-muted"><?= e((string)$t['complaint_category']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td data-label="Submitted Date" class="text-sm text-muted"><?= e($submittedAt) ?></td>
                            <td data-label="Assigned Handler" class="text-sm"><?= e((string)($t['assigned_handler_display'] ?? 'Pending Assignment')) ?></td>
                            <td data-label="Status">
                                <span class="badge <?= $badgeClass ?>"><?= e($status) ?></span>
                            </td>
                            <td data-label="Last Updated" class="text-xs text-muted"><?= e($updatedAt) ?></td>
                            <td data-label="Action" class="text-right">
                                <a href="<?= url('/support/' . (int)$t['id']) ?>" class="btn btn-secondary btn-sm">
                                    <?= icon('eye', 14) ?>
                                    <span>View</span>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</section>
