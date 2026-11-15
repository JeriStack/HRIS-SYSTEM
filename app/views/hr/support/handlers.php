<?php
/**
 * Confidential Complaint Handler Designation & Governance View
 */
$roster = $roster ?? [];
$escalationPolicySummary = (string)($escalationPolicySummary ?? '');
?>
<div class="page-header">
    <div class="page-title-group">
        <h1>Confidential Complaint Handlers</h1>
        <p>Authorize or revoke user-scoped confidential complaint clearance within existing HR and Administrator roles.</p>
    </div>
    <div class="page-actions">
        <a href="<?= url('/hr/support') ?>" class="btn btn-secondary btn-sm">
            <?= icon('arrow-left', 15) ?>
            <span>Back to HR Support Inbox</span>
        </a>
    </div>
</div>

<section class="support-disclosure-box" aria-labelledby="handlerGovernancePolicyHeading">
    <div class="inline-flex items-center gap-2" style="margin-bottom: 0.35rem;">
        <?= icon('shield', 17) ?>
        <h2 id="handlerGovernancePolicyHeading" style="font-size: var(--font-size-md); margin: 0;">Confidentiality &amp; COI Boundaries</h2>
    </div>
    <ul class="support-disclosure-list">
        <li>Standard HR accounts do <strong>not</strong> receive automatic access to confidential workplace complaints unless actively designated below.</li>
        <li>Conflict-of-interest exclusion still overrides clearance whenever an authorized handler is identified as the subject of a complaint.</li>
        <li><?= e($escalationPolicySummary) ?></li>
    </ul>
</section>

<section class="card" aria-labelledby="handlerRosterHeading">
    <div class="card-header">
        <h2 id="handlerRosterHeading" class="card-title">HR &amp; Administrator Handler Roster</h2>
        <span class="badge badge-neutral tabular-nums"><?= count($roster) ?> accounts</span>
    </div>
    <div class="support-table-region">
        <table class="table table-hover support-responsive-table">
            <caption class="support-table-caption">HR and Administrator users with confidential complaint handling clearance status</caption>
            <thead>
                <tr>
                    <th scope="col">Account</th>
                    <th scope="col">Role</th>
                    <th scope="col">Department</th>
                    <th scope="col">Clearance Status</th>
                    <th scope="col">Notes &amp; Granted By</th>
                    <th scope="col" class="text-right">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($roster as $row):
                    $uid = (int)$row['user_id'];
                    $isActiveAccount = ($row['account_status'] ?? '') === 'Active';
                    $isDesignated = !empty($row['is_designated_active']) && !empty($row['can_handle_confidential']);
                    $fullName = trim(((string)($row['first_name'] ?? '')) . ' ' . ((string)($row['last_name'] ?? '')));
                    if ($fullName === '') {
                        $fullName = (string)$row['username'];
                    }
                ?>
                    <tr>
                        <td data-label="Account">
                            <strong><?= e($fullName) ?></strong>
                            <div class="text-xs text-muted">
                                @<?= e((string)$row['username']) ?>
                                <?= !empty($row['employee_no']) ? ' &middot; ' . e((string)$row['employee_no']) : '' ?>
                                <?= !$isActiveAccount ? ' (Inactive Account)' : '' ?>
                            </div>
                        </td>
                        <td data-label="Role">
                            <span class="badge badge-neutral"><?= e(strtoupper((string)$row['role'])) ?></span>
                        </td>
                        <td data-label="Department" class="text-sm text-muted">
                            <?= e((string)($row['department_name'] ?? 'System / Unassigned')) ?>
                        </td>
                        <td data-label="Clearance Status">
                            <?php if ($isDesignated): ?>
                                <span class="badge badge-success">Designated Active</span>
                            <?php else: ?>
                                <span class="badge badge-neutral">Standard Queue Only</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Notes & Granted By" class="text-xs text-muted">
                            <?= !empty($row['designation_notes']) ? e((string)$row['designation_notes']) : '—' ?>
                            <?php if (!empty($row['granted_by_username'])): ?>
                                <div>Updated by @<?= e((string)$row['granted_by_username']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td data-label="Action" class="text-right">
                            <form method="POST" action="<?= url('/hr/support/handlers') ?>" style="display: inline-flex; gap: 0.4rem; flex-wrap: wrap; justify-content: flex-end;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="target_user_id" value="<?= $uid ?>">
                                <input
                                    type="text"
                                    name="designation_notes"
                                    class="form-control"
                                    style="width: 190px; font-size: 0.75rem; padding: 0.3rem 0.5rem;"
                                    placeholder="Authorization note..."
                                    maxlength="255"
                                    value="<?= e((string)($row['designation_notes'] ?? '')) ?>"
                                    aria-label="Designation note for <?= e((string)$row['username']) ?>"
                                >
                                <?php if ($isDesignated): ?>
                                    <input type="hidden" name="action" value="revoke">
                                    <button type="submit" class="btn btn-secondary btn-sm">Revoke</button>
                                <?php else: ?>
                                    <input type="hidden" name="action" value="grant">
                                    <button type="submit" class="btn btn-primary btn-sm"<?= !$isActiveAccount ? ' disabled' : '' ?>>Designate</button>
                                <?php endif; ?>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
