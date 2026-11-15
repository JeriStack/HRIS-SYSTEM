<?php
/**
 * Employee Leave Balances, Philippine Statutory Benefits & Immutable Ledger
 * 8-Section Executive Ledger Self-Service View
 */
$year = (int)($year ?? date('Y'));
$metrics = $overviewMetrics ?? [];
$allocPaid = (int)($metrics['allocated_paid'] ?? 0);
$usedPaid = (int)($metrics['used_paid'] ?? 0);
$pendingPaid = (int)($metrics['pending_paid'] ?? 0);
$availPaid = (int)($metrics['available_paid'] ?? 0);
$convPaid = (int)($metrics['converted_paid'] ?? 0);
?>
<div class="page-header">
    <div class="page-title-group">
        <h1>My Leave Balances (<?= $year ?>)</h1>
        <p>Philippine statutory leave benefits, company quotas, verification status, and audit ledger.</p>
    </div>
    <div class="page-actions">
        <a href="<?= url('/leaves/apply') ?>" class="btn btn-primary btn-sm">
            <?= icon('file-plus', 15) ?>
            <span>Apply for Leave</span>
        </a>
        <a href="<?= url('/employee/leave-requests') ?>" class="btn btn-secondary btn-sm">
            <?= icon('file-text', 15) ?>
            <span>All Requests</span>
        </a>
    </div>
</div>

<!-- 1. Overview Metrics Strip -->
<section class="leave-section" aria-labelledby="secOverviewHeading">
    <div class="leave-section-head">
        <h2 class="leave-section-title" id="secOverviewHeading">Paid Annual Leave Overview</h2>
        <span class="leave-section-meta">Pending days are reserved separately from approved used credits</span>
    </div>
    <div class="grid grid-cols-5" style="gap: var(--space-4);">
        <div class="stat-card">
            <div class="stat-info">
                <span class="stat-label">Allocated Paid</span>
                <span class="stat-value"><?= $allocPaid ?>d</span>
                <span class="stat-subtext">Annual paid credits</span>
            </div>
            <div class="stat-icon-wrapper icon-blue" aria-hidden="true"><?= icon('calendar', 20) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-info">
                <span class="stat-label">Available Now</span>
                <span class="stat-value" style="color: var(--color-primary);"><?= $availPaid ?>d</span>
                <span class="stat-subtext">Ready for new requests</span>
            </div>
            <div class="stat-icon-wrapper icon-green" aria-hidden="true"><?= icon('check-circle', 20) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-info">
                <span class="stat-label">Approved Used</span>
                <span class="stat-value"><?= $usedPaid ?>d</span>
                <span class="stat-subtext">Deducted after approval</span>
            </div>
            <div class="stat-icon-wrapper icon-teal" aria-hidden="true"><?= icon('calendar-check', 20) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-info">
                <span class="stat-label">Pending Approval</span>
                <span class="stat-value" style="color: var(--color-warning);"><?= $pendingPaid ?>d</span>
                <span class="stat-subtext">Awaiting HR review</span>
            </div>
            <div class="stat-icon-wrapper icon-amber" aria-hidden="true"><?= icon('clock', 20) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-info">
                <span class="stat-label">Converted (SIL)</span>
                <span class="stat-value"><?= $convPaid ?>d</span>
                <span class="stat-subtext">Recorded cash conversion</span>
            </div>
            <div class="stat-icon-wrapper icon-purple" aria-hidden="true"><?= icon('balances', 20) ?></div>
        </div>
    </div>
</section>

<!-- 2. Statutory & Company Annual Leave Balances -->
<section class="leave-section" aria-labelledby="secAnnualHeading">
    <div class="leave-section-head">
        <h2 class="leave-section-title" id="secAnnualHeading">Statutory &amp; Company Annual Leave Balances</h2>
        <span class="leave-section-meta">SIL offset and Solo Parent SPIC verification rules apply</span>
    </div>
    <?php if (empty($annualBenefits)): ?>
        <div class="card"><div class="card-body">No annual leave categories configured.</div></div>
    <?php else: ?>
        <div class="leave-benefit-grid">
            <?php foreach ($annualBenefits as $entry):
                $tp = $entry['type'];
                $elig = $entry['eligibility'] ?? [];
                $isEligible = !empty($elig['eligible']);
                $isCreditedViaVl = !empty($elig['credited_via_vl']);
                $statusLabel = (string)($entry['status_label'] ?? 'Available');
                $alloc = (int)($entry['allocated_days'] ?? 0);
                $used = (int)($entry['used_days'] ?? 0);
                $pend = (int)($entry['pending_days'] ?? 0);
                $conv = (int)($entry['converted_days'] ?? 0);
                $rem = (int)($entry['remaining_days'] ?? 0);
                $classif = (string)($tp['classification'] ?? 'Company');
                $statusBadgeClass = match (true) {
                    $isCreditedViaVl => 'badge-info',
                    !$isEligible => 'badge-neutral',
                    $rem <= 0 && $alloc > 0 => 'badge-danger',
                    $statusLabel === 'Low Balance' => 'badge-warning',
                    default => 'badge-success',
                };
            ?>
                <article class="leave-benefit-card" data-balance-type-id="<?= (int)$tp['id'] ?>">
                    <div class="leave-benefit-top">
                        <div>
                            <h3 class="leave-benefit-name"><?= e($tp['leave_name'] ?? 'Leave Benefit') ?></h3>
                            <?php if (!empty($tp['legal_reference'])): ?>
                                <div class="leave-benefit-law"><?= e($tp['legal_reference']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="leave-benefit-badges">
                            <span class="badge <?= $classif === 'Statutory' ? 'badge-statutory' : 'badge-company' ?>">
                                <?= e($classif) ?>
                            </span>
                            <span class="badge <?= $statusBadgeClass ?>"><?= e($statusLabel) ?></span>
                        </div>
                    </div>

                    <?php if ($isCreditedViaVl): ?>
                        <div class="leave-eligibility-banner is-caution">
                            <span><?= e((string)($elig['reason'] ?? 'Satisfied via qualifying Vacation Leave under Article 95.')) ?></span>
                        </div>
                    <?php elseif (!$isEligible): ?>
                        <div class="leave-eligibility-banner is-blocked">
                            <span><?= e((string)($elig['reason'] ?? 'Not currently eligible under active policy rules.')) ?></span>
                        </div>
                    <?php else: ?>
                        <div class="leave-benefit-stats">
                            <div>
                                <span class="leave-benefit-figure"><?= $rem ?></span>
                                <span class="leave-benefit-sub">of <?= $alloc ?> available</span>
                            </div>
                            <div class="leave-benefit-sub">
                                Used: <?= $used ?>d &middot; Pending: <?= $pend ?>d<?= $conv > 0 ? ' &middot; Converted: ' . $conv . 'd' : '' ?>
                            </div>
                        </div>
                        <p class="leave-benefit-sub" style="margin: 0;">
                            <?= e((string)($elig['reason'] ?? '')) ?>
                        </p>
                        <div style="margin-top: auto;">
                            <a href="<?= url('/leaves/apply?leave_type_id=' . (int)$tp['id']) ?>" class="btn btn-secondary btn-xs">Apply</a>
                        </div>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<!-- 3. Philippine Statutory Event-Based Benefits -->
<section class="leave-section" aria-labelledby="secStatEventsHeading">
    <div class="leave-section-head">
        <h2 class="leave-section-title" id="secStatEventsHeading">Philippine Statutory Event-Based Benefits</h2>
        <span class="leave-section-meta">Granted per qualifying life/medical/protective event (not ordinary annual balances)</span>
    </div>
    <div class="leave-benefit-grid">
        <?php foreach ($statutoryEvents ?? [] as $ev):
            $tp = $ev['type'];
            $elig = $ev['eligibility'] ?? [];
            $isElig = !empty($elig['eligible']);
            $code = strtoupper((string)($tp['leave_code'] ?? ''));
            $unitLabel = (($tp['default_unit'] ?? 'working_days') === 'calendar_days') ? 'calendar days' : 'working days';
            $isConf = !empty($tp['is_confidential']);
        ?>
            <article class="leave-benefit-card">
                <div class="leave-benefit-top">
                    <div>
                        <h3 class="leave-benefit-name"><?= e($tp['leave_name'] ?? '') ?></h3>
                        <?php if (!empty($tp['legal_reference'])): ?>
                            <div class="leave-benefit-law"><?= e($tp['legal_reference']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="leave-benefit-badges">
                        <span class="badge badge-statutory">Statutory Event</span>
                        <?php if ($isConf): ?>
                            <span class="badge badge-confidential">Confidential</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="leave-benefit-stats">
                    <div>
                        <span class="leave-benefit-figure"><?= (int)($tp['default_days'] ?? 0) ?></span>
                        <span class="leave-benefit-sub"><?= e($unitLabel) ?> / event</span>
                    </div>
                    <span class="badge <?= $isElig ? 'badge-success' : 'badge-neutral' ?>">
                        <?= $isElig ? 'Potentially Eligible' : 'Restricted' ?>
                    </span>
                </div>
                <?php if ($code === 'PATERNITY' && isset($ev['paternity_deliveries_used'])): ?>
                    <div class="leave-benefit-sub">
                        Lawful Spouse Deliveries Used: <strong><?= (int)$ev['paternity_deliveries_used'] ?> / 4</strong>
                    </div>
                <?php endif; ?>
                <p class="leave-benefit-sub" style="margin: 0;"><?= e((string)($elig['reason'] ?? '')) ?></p>
                <?php if ($isElig): ?>
                    <div style="margin-top: auto;">
                        <a href="<?= url('/leaves/apply?leave_type_id=' . (int)$tp['id']) ?>" class="btn btn-secondary btn-xs">File Request</a>
                    </div>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>

    <?php if (!empty($qualifications) || !empty($eventEntitlements)): ?>
        <div class="grid grid-cols-2" style="gap: var(--space-4); margin-top: var(--space-4);">
            <div class="card">
                <div class="card-header"><h3 class="card-title">My Statutory Qualifications</h3></div>
                <div class="table-wrapper">
                    <table class="table table-hover">
                        <thead><tr><th>Qualification</th><th>Reference</th><th>Status</th><th>Validity</th></tr></thead>
                        <tbody>
                            <?php if (empty($qualifications)): ?>
                                <tr><td colspan="4" class="text-center" style="color: var(--color-text-muted);">No qualifications on file. Contact HR to verify SPIC or marriage records.</td></tr>
                            <?php else: foreach ($qualifications as $q):
                                $qSt = (string)($q['verification_status'] ?? 'PendingVerification');
                                $qBadge = match ($qSt) {
                                    'Verified' => 'badge-success',
                                    'Expired', 'Revoked', 'Rejected' => 'badge-danger',
                                    default => 'badge-warning',
                                };
                            ?>
                                <tr>
                                    <td><?= e(strtoupper(str_replace('_', ' ', (string)($q['qualification_type'] ?? '')))) ?></td>
                                    <td><?= e((string)($q['reference_no'] ?? '—')) ?></td>
                                    <td><span class="badge <?= $qBadge ?>"><?= e($qSt) ?></span></td>
                                    <td><?= !empty($q['valid_to']) ? e(date('M d, Y', strtotime((string)$q['valid_to']))) : 'Ongoing' ?></td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h3 class="card-title">Active Event Entitlement Records</h3></div>
                <div class="table-wrapper">
                    <table class="table table-hover">
                        <thead><tr><th>Benefit</th><th>Event Date</th><th>Granted</th><th>Used / Pending</th><th>Status</th></tr></thead>
                        <tbody>
                            <?php if (empty($eventEntitlements)): ?>
                                <tr><td colspan="5" class="text-center" style="color: var(--color-text-muted);">No event-based leave claims recorded yet.</td></tr>
                            <?php else: foreach ($eventEntitlements as $ee): ?>
                                <tr>
                                    <td><?= e((string)($ee['leave_name'] ?? '')) ?></td>
                                    <td><?= !empty($ee['event_date']) ? e(date('M d, Y', strtotime((string)$ee['event_date']))) : '—' ?></td>
                                    <td><?= (int)($ee['entitled_days'] ?? 0) ?>d</td>
                                    <td><?= (int)($ee['used_days'] ?? 0) ?>d / <?= (int)($ee['pending_days'] ?? 0) ?>d</td>
                                    <td><span class="badge badge-info"><?= e((string)($ee['event_status'] ?? 'Open')) ?></span></td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</section>

<!-- 4. Company Event-Based Benefits & 5. Unpaid Leave Tracking -->
<section class="leave-section grid grid-cols-2" style="gap: var(--space-4);" aria-labelledby="secCompEventHeading">
    <div class="card">
        <div class="card-header"><h2 class="card-title" id="secCompEventHeading">Company Event-Based Benefits</h2></div>
        <div class="card-body">
            <?php if (empty($companyEvents)): ?>
                <p style="color: var(--color-text-muted); margin: 0;">No company event-based leave policies active.</p>
            <?php else: foreach ($companyEvents as $ce):
                $ctp = $ce['type'];
            ?>
                <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; padding: 0.5rem 0;">
                    <div>
                        <strong><?= e((string)($ctp['leave_name'] ?? '')) ?></strong>
                        <div class="leave-benefit-sub"><?= e((string)($ce['eligibility']['reason'] ?? '')) ?></div>
                    </div>
                    <div style="text-align: right; white-space: nowrap;">
                        <span class="badge badge-company"><?= (int)($ctp['default_days'] ?? 0) ?> working days / event</span>
                        <div style="margin-top: 0.35rem;">
                            <a href="<?= url('/leaves/apply?leave_type_id=' . (int)$ctp['id']) ?>" class="btn btn-secondary btn-xs">Apply</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h2 class="card-title">Unpaid Leave (LWOP) Tracking — <?= $year ?></h2></div>
        <div class="card-body">
            <div style="display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap;">
                <div>
                    <div class="leave-benefit-figure"><?= (int)($unpaidSummary['approved_days'] ?? 0) ?> days</div>
                    <div class="leave-benefit-sub">Approved unpaid absences (zero paid credit deduction)</div>
                </div>
                <div style="text-align: right;">
                    <span class="badge badge-unpaid">Pending: <?= (int)($unpaidSummary['pending_days'] ?? 0) ?>d</span>
                    <?php if (!empty($unpaidBenefits[0]['type']['id'])): ?>
                        <div style="margin-top: 0.45rem;">
                            <a href="<?= url('/leaves/apply?leave_type_id=' . (int)$unpaidBenefits[0]['type']['id']) ?>" class="btn btn-secondary btn-xs">File LWOP</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- 6. Pending Leave Requests Preview (Max 3) -->
<section class="leave-section card" aria-labelledby="secPendingHeading">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; gap: 1rem;">
        <h2 class="card-title" id="secPendingHeading">Pending Leave Requests (<?= (int)($pendingRequestsTotal ?? 0) ?>)</h2>
        <a href="<?= url('/employee/leave-requests') ?>" class="btn btn-secondary btn-xs">
            View All Requests (<?= (int)($pendingRequestsTotal ?? 0) ?>)
        </a>
    </div>
    <div class="table-wrapper">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Reference</th>
                    <th>Classification</th>
                    <th>Dates</th>
                    <th>Duration</th>
                    <th>Review State</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($pendingRequestsPreview)): ?>
                    <tr><td colspan="6" class="text-center" style="color: var(--color-text-muted);">No pending leave requests awaiting review.</td></tr>
                <?php else: foreach ($pendingRequestsPreview as $pr):
                    $infoSt = (string)($pr['info_request_status'] ?? 'None');
                ?>
                    <tr>
                        <td><strong>#LR-<?= str_pad((string)$pr['id'], 3, '0', STR_PAD_LEFT) ?></strong></td>
                        <td><?= e((string)($pr['leave_name'] ?? '')) ?></td>
                        <td><?= e(date('M d, Y', strtotime((string)$pr['start_date']))) ?> &ndash; <?= e(date('M d, Y', strtotime((string)$pr['end_date']))) ?></td>
                        <td><?= (int)($pr['number_of_days'] ?? 0) ?> <?= (($pr['entitlement_unit'] ?? 'working_days') === 'calendar_days') ? 'cal. days' : 'work days' ?></td>
                        <td>
                            <span class="badge badge-warning">Pending</span>
                            <?php if ($infoSt === 'Requested'): ?>
                                <span class="badge badge-danger">Clarification Needed</span>
                            <?php elseif ($infoSt === 'Responded'): ?>
                                <span class="badge badge-info">Clarification Sent</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="<?= url('/leaves/' . (int)$pr['id']) ?>" class="btn btn-secondary btn-xs">
                                <?= $infoSt === 'Requested' ? 'Respond' : 'View' ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</section>

<!-- 7. Balance & Entitlement Transaction Ledger -->
<section class="leave-section card" aria-labelledby="secLedgerHeading">
    <div class="card-header">
        <h2 class="card-title" id="secLedgerHeading">Balance &amp; Entitlement Transaction Ledger (<?= $year ?>)</h2>
    </div>
    <div class="table-wrapper">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Timestamp</th>
                    <th>Leave Type</th>
                    <th>Transaction</th>
                    <th>Delta</th>
                    <th>Before &rarr; After</th>
                    <th>Reference</th>
                    <th>Remarks</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($ledgerTransactions)): ?>
                    <tr><td colspan="7" class="text-center" style="color: var(--color-text-muted);">No ledger entries recorded for <?= $year ?> yet.</td></tr>
                <?php else: foreach ($ledgerTransactions as $tx):
                    $delta = (float)($tx['days_delta'] ?? 0);
                    $deltaStr = ($delta > 0 ? '+' : '') . rtrim(rtrim(number_format($delta, 2, '.', ''), '0'), '.');
                ?>
                    <tr>
                        <td style="white-space: nowrap;"><?= !empty($tx['created_at']) ? e(date('M d, Y H:i', strtotime((string)$tx['created_at']))) : '—' ?></td>
                        <td><?= e((string)($tx['leave_name'] ?? '')) ?></td>
                        <td><span class="badge badge-neutral"><?= e((string)($tx['transaction_type'] ?? '')) ?></span></td>
                        <td class="<?= $delta >= 0 ? 'ledger-delta-pos' : 'ledger-delta-neg' ?>"><?= e($deltaStr) ?>d</td>
                        <td><?= (int)round((float)($tx['balance_before'] ?? 0)) ?>d &rarr; <?= (int)round((float)($tx['balance_after'] ?? 0)) ?>d</td>
                        <td><?= e((string)($tx['conversion_reference'] ?? (!empty($tx['leave_request_id']) ? '#LR-' . $tx['leave_request_id'] : '—'))) ?></td>
                        <td><?= e((string)($tx['remarks'] ?? '')) ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</section>

<!-- 8. Policy Reference & Eligibility Summary -->
<section class="leave-section card" aria-labelledby="secPolicyRefHeading">
    <div class="card-header">
        <h2 class="card-title" id="secPolicyRefHeading">Policy Reference &amp; Eligibility Rules</h2>
    </div>
    <div class="table-wrapper">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Leave Benefit</th>
                    <th>Classification</th>
                    <th>Entitlement Model</th>
                    <th>Standard Quota</th>
                    <th>Min Service</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($allTypePolicies ?? [] as $tp):
                    $modelLabel = match ($tp['entitlement_model'] ?? 'annual') {
                        'event_based' => 'Event-Based',
                        'unpaid' => 'Unpaid (LWOP)',
                        default => 'Annual Balance',
                    };
                    $unitText = (($tp['default_unit'] ?? 'working_days') === 'calendar_days') ? 'calendar days' : 'working days';
                    $quotaText = ($tp['entitlement_model'] ?? '') === 'unpaid'
                        ? 'As needed (unpaid)'
                        : ((int)($tp['default_days'] ?? 0) . ' ' . $unitText);
                ?>
                    <tr>
                        <td><strong><?= e((string)($tp['leave_name'] ?? '')) ?></strong></td>
                        <td>
                            <span class="badge <?= ($tp['classification'] ?? '') === 'Statutory' ? 'badge-statutory' : 'badge-company' ?>">
                                <?= e((string)($tp['classification'] ?? 'Company')) ?>
                            </span>
                        </td>
                        <td><?= e($modelLabel) ?></td>
                        <td><?= e($quotaText) ?></td>
                        <td><?= (int)($tp['min_service_months'] ?? 0) ?> mos.</td>
                        <td>
                            <button
                                type="button"
                                class="btn btn-secondary btn-xs"
                                data-policy-dialog-trigger="1"
                                data-policy-name="<?= e((string)($tp['leave_name'] ?? '')) ?>"
                                data-policy-code="<?= e((string)($tp['policy_code'] ?? ($tp['leave_code'] ?? 'STANDARD'))) ?>"
                                data-policy-category="<?= e(($tp['classification'] ?? 'Company') . ' / ' . $modelLabel) ?>"
                                data-policy-legal="<?= e((string)($tp['legal_reference'] ?? 'Internal Company Policy')) ?>"
                                data-policy-quota="<?= e($quotaText) ?>"
                                data-policy-service="<?= (int)($tp['min_service_months'] ?? 0) ?> month(s) minimum service"
                                data-policy-docs="<?= !empty($tp['requires_documentation']) ? e((string)($tp['document_requirements'] ?? 'Supporting documentation required')) : 'Optional unless requested by HR' ?>"
                                data-policy-notes="<?= e((string)($tp['description'] ?? '')) ?>"
                            >Policy Details</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<dialog id="policyDetailsDialog" class="leave-dialog" data-close-on-backdrop="1" aria-labelledby="policyDlgTitle">
    <div class="leave-dialog-head">
        <h3 id="policyDlgTitle" class="leave-dialog-title">Policy Reference</h3>
        <button type="button" class="profile-dialog-close" data-dialog-close aria-label="Close policy dialog"><?= icon('x', 18) ?></button>
    </div>
    <div class="leave-dialog-body">
        <dl style="margin: 0;">
            <div class="leave-dialog-kv"><dt>Policy Code</dt><dd id="policyDlgCode">—</dd></div>
            <div class="leave-dialog-kv"><dt>Classification</dt><dd id="policyDlgCategory">—</dd></div>
            <div class="leave-dialog-kv"><dt>Legal Reference</dt><dd id="policyDlgLegal">—</dd></div>
            <div class="leave-dialog-kv"><dt>Quota / Unit</dt><dd id="policyDlgQuota">—</dd></div>
            <div class="leave-dialog-kv"><dt>Tenure Requirement</dt><dd id="policyDlgService">—</dd></div>
            <div class="leave-dialog-kv"><dt>Documentation</dt><dd id="policyDlgDocs">—</dd></div>
            <div class="leave-dialog-kv"><dt>Summary</dt><dd id="policyDlgNotes">—</dd></div>
        </dl>
    </div>
    <div class="leave-dialog-foot">
        <button type="button" class="btn btn-primary btn-sm" data-dialog-close>Close</button>
    </div>
</dialog>
