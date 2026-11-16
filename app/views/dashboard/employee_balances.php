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
$consumedPaid = max(0, $usedPaid + $convPaid);
$usedPct = $allocPaid > 0 ? min(100, (int)round(($consumedPaid / $allocPaid) * 100)) : 0;
$isAvailExhausted = $allocPaid > 0 && $availPaid <= 0;
$isLowPaidBalance = $allocPaid > 0 && !$isAvailExhausted && $usedPct >= 75;
$availCaption = match (true) {
    $allocPaid <= 0 => 'No paid quota allocated',
    $availPaid <= 0 => 'No paid credits remaining',
    default => 'Ready for new requests',
};
$meterClass = match (true) {
    $isAvailExhausted => 'is-exhausted',
    $isLowPaidBalance => 'is-warning',
    default => '',
};
?>
<div class="page-header">
    <div class="page-title-group">
        <h1>My Leave Balances (<?= $year ?>)</h1>
        <p>Statutory and company leave benefits, quotas, and verification status.</p>
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
        <span class="leave-section-meta leave-overview-note">
            <?= icon('info', 14) ?>
            <span>Pending days are reserved separately from approved used credits</span>
        </span>
    </div>
    <div class="leave-overview-shell">
        <div class="leave-overview-strip">
            <div class="leave-overview-primary">
                <div class="leave-overview-cell-head">
                    <span class="leave-overview-label">Available Now</span>
                    <span class="leave-overview-icon <?= $isAvailExhausted ? 'is-exhausted' : ($allocPaid > 0 ? 'is-available' : '') ?>" aria-hidden="true">
                        <?= icon($isAvailExhausted ? 'alert-circle' : 'check-circle', 16) ?>
                    </span>
                </div>

                <div class="leave-overview-hero-block">
                    <div class="leave-overview-hero-row">
                        <span class="leave-overview-hero-val"><?= $availPaid ?></span>
                        <span class="leave-overview-hero-unit"><?= abs($availPaid) === 1 ? 'day' : 'days' ?> available</span>
                    </div>
                    <p class="leave-overview-hero-sub"><?= e($availCaption) ?></p>
                </div>

                <div class="leave-overview-meter-wrap">
                    <div class="leave-overview-meter" role="progressbar" aria-label="Paid annual leave utilization" aria-valuenow="<?= $usedPct ?>" aria-valuemin="0" aria-valuemax="100">
                        <div class="leave-overview-meter-fill <?= $meterClass ?>" style="width: <?= $usedPct ?>%;"></div>
                    </div>
                    <span class="leave-overview-meter-pct"><?= $usedPct ?>% used</span>
                </div>
            </div>

            <div class="leave-overview-grid">
                <div class="leave-overview-cell">
                    <div class="leave-overview-cell-head">
                        <span class="leave-overview-label">Allocated Paid</span>
                        <span class="leave-overview-icon" aria-hidden="true"><?= icon('calendar', 15) ?></span>
                    </div>
                    <div class="leave-overview-val"><?= $allocPaid ?>d</div>
                    <div class="leave-overview-sub">Annual paid credits</div>
                </div>

                <div class="leave-overview-cell">
                    <div class="leave-overview-cell-head">
                        <span class="leave-overview-label">Approved Used</span>
                        <span class="leave-overview-icon" aria-hidden="true"><?= icon('calendar-check', 15) ?></span>
                    </div>
                    <div class="leave-overview-val"><?= $usedPaid ?>d</div>
                    <div class="leave-overview-sub">Deducted after approval</div>
                </div>

                <div class="leave-overview-cell <?= $pendingPaid > 0 ? 'is-pending' : '' ?>">
                    <div class="leave-overview-cell-head">
                        <span class="leave-overview-label">Pending Approval</span>
                        <span class="leave-overview-icon <?= $pendingPaid > 0 ? 'is-pending' : '' ?>" aria-hidden="true"><?= icon('clock', 15) ?></span>
                    </div>
                    <div class="leave-overview-val"><?= $pendingPaid ?>d</div>
                    <div class="leave-overview-sub">Awaiting HR review</div>
                </div>

                <div class="leave-overview-cell">
                    <div class="leave-overview-cell-head">
                        <span class="leave-overview-label">Converted (SIL)</span>
                        <span class="leave-overview-icon <?= $convPaid > 0 ? 'is-info' : '' ?>" aria-hidden="true"><?= icon('peso', 15) ?></span>
                    </div>
                    <div class="leave-overview-val"><?= $convPaid ?>d</div>
                    <div class="leave-overview-sub">Recorded cash conversion</div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- 2. Statutory & Company Annual Leave Balances -->
<section class="leave-section" aria-labelledby="secAnnualHeading">
    <div class="leave-section-head">
        <h2 class="leave-section-title" id="secAnnualHeading">Statutory &amp; Company Annual Leave Balances</h2>
    </div>
    <?php if (empty($annualBenefits)): ?>
        <div class="card"><div class="card-body">No eligible annual leave categories available for your current profile.</div></div>
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
                    <?php
                        $reason = trim((string)($elig['reason'] ?? ''));
                        $showReason = $reason !== '' && $reason !== 'Active company leave policy.';
                        $law = trim((string)($tp['legal_reference'] ?? ''));
                        $usedPct = $alloc > 0 ? min(100, (int)round($used / $alloc * 100)) : 0;
                        $pendPct = $alloc > 0 ? min(100 - $usedPct, (int)round($pend / $alloc * 100)) : 0;
                        $usageParts = [$used . 'd used'];
                        if ($pend > 0) { $usageParts[] = $pend . 'd pending'; }
                        if ($conv > 0) { $usageParts[] = $conv . 'd converted'; }
                    ?>
                    <div class="leave-benefit-top">
                        <div class="leave-benefit-heading">
                            <h3 class="leave-benefit-name"><?= e($tp['leave_name'] ?? 'Leave Benefit') ?></h3>
                            <div class="leave-benefit-law"><?= e($classif === 'Statutory' && $law !== '' ? $classif . ' · ' . $law : $classif) ?></div>
                        </div>
                        <?php if ($statusLabel !== \App\Services\LeavePolicyEngine::STATUS_AVAILABLE): ?>
                            <div class="leave-benefit-badges">
                                <span class="badge <?= $statusBadgeClass ?>"><?= e($statusLabel) ?></span>
                            </div>
                        <?php endif; ?>
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
                        <div class="leave-benefit-balance">
                            <span class="leave-benefit-figure"><?= $rem ?></span>
                            <span class="leave-benefit-sub">of <?= $alloc ?> days left</span>
                        </div>
                        <div class="leave-benefit-meter" role="progressbar" aria-label="<?= e($tp['leave_name'] ?? 'Leave') ?> used" aria-valuenow="<?= $usedPct ?>" aria-valuemin="0" aria-valuemax="100">
                            <span class="leave-benefit-meter-used" style="width: <?= $usedPct ?>%;"></span>
                            <span class="leave-benefit-meter-pending" style="width: <?= $pendPct ?>%;"></span>
                        </div>
                        <?php if ($showReason): ?>
                            <p class="leave-benefit-note"><?= e($reason) ?></p>
                        <?php endif; ?>
                        <div class="leave-benefit-foot">
                            <span class="leave-benefit-sub"><?= e(implode(' · ', $usageParts)) ?></span>
                            <a href="<?= url('/leaves/apply?leave_type_id=' . (int)$tp['id']) ?>" class="btn btn-secondary btn-xs">Apply</a>
                        </div>
                    <?php endif; ?>
                </article>            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<!-- 3. Philippine Statutory Event-Based Benefits -->
<section class="leave-section" aria-labelledby="secStatEventsHeading">
    <div class="leave-section-head">
        <h2 class="leave-section-title" id="secStatEventsHeading">Philippine Statutory Event-Based Benefits</h2>
    </div>
    <?php
        $employeeGender = (string)($employee['gender'] ?? '');
        $isGenericReason = static fn (string $r): bool => $r === '' || $r === 'Active company leave policy.';

        // One-line description for company-provided benefits. Full rules live in the policy dialog.
        $eventBlurb = static function (array $tp): string {
            if (strtoupper(trim((string)($tp['leave_code'] ?? ''))) === 'BEREAVEMENT') {
                return 'Death of an immediate family member.';
            }
            $desc = trim((string)($tp['description'] ?? ''));
            return mb_strlen($desc) <= 100 ? $desc : '';
        };

        // Entitlement from the eligibility result (e.g. 7 transferred days for a male employee's Maternity row).
        $eventEntitlement = static function (array $tp, array $elig): array {
            $days = (int)($elig['entitled_days'] ?? 0);
            if ($days <= 0) {
                $days = (int)($tp['default_days'] ?? 0);
            }
            $unit = (string)($elig['entitlement_unit'] ?? ($tp['default_unit'] ?? 'working_days'));
            return [$days, $unit === 'calendar_days' ? 'calendar days' : 'working days'];
        };

        // Same confidentiality rule as LeavePolicyEngine / ConfidentialLeaveService.
        $isConfidentialType = static fn (array $tp): bool => !empty($tp['is_confidential'])
            || in_array(strtoupper(trim((string)($tp['leave_code'] ?? ''))), ['VAWC', 'SLW'], true);

        // Data attributes for the shared policy details dialog (see forms.js).
        $policyAttrs = static function (array $tp, array $elig, string $quotaText) use ($isGenericReason): string {
            $reason = trim((string)($elig['reason'] ?? ''));
            $attrs = [
                'data-policy-dialog-trigger' => '1',
                'data-policy-name'     => (string)($tp['leave_name'] ?? ''),
                'data-policy-code'     => (string)($tp['policy_code'] ?? ($tp['leave_code'] ?? 'STANDARD')),
                'data-policy-category' => (string)($tp['classification'] ?? 'Company') . ' / Event-Based',
                'data-policy-legal'    => (string)($tp['legal_reference'] ?? 'Internal Company Policy'),
                'data-policy-quota'    => $quotaText,
                'data-policy-service'  => (int)($tp['min_service_months'] ?? 0) . ' month(s) minimum service',
                'data-policy-docs'     => !empty($tp['requires_documentation'])
                    ? (string)($tp['document_requirements'] ?? 'Supporting documentation required')
                    : 'Optional unless requested by HR',
                'data-policy-notes'    => !$isGenericReason($reason) ? $reason : trim((string)($tp['description'] ?? '')),
            ];
            $out = [];
            foreach ($attrs as $k => $v) {
                $out[] = $k . '="' . e((string)$v) . '"';
            }
            return implode(' ', $out);
        };
    ?>
    <?php if (empty($statutoryEvents)): ?>
        <div class="card"><div class="card-body">No statutory event-based benefits apply to your profile.</div></div>
    <?php else: ?>
    <div class="card leave-list">
        <?php foreach ($statutoryEvents as $ev):
            $tp = $ev['type'];
            $elig = $ev['eligibility'] ?? [];
            $code = strtoupper(trim((string)($tp['leave_code'] ?? '')));
            [$entDays, $entUnit] = $eventEntitlement($tp, $elig);
            $isConf = $isConfidentialType($tp);
            $isPendingVerification = ($elig['status'] ?? '') === \App\Services\LeavePolicyEngine::STATUS_PENDING_VERIFICATION;
            $legalShort = trim((string)preg_replace('/\s*\(.*$/', '', (string)($tp['legal_reference'] ?? '')));
            $legalParts = $legalShort !== '' ? [$legalShort] : [];
            if ($code === 'MATERNITY' && $employeeGender === 'Male') {
                $legalParts[] = 'Transferred by the mother';
            }
            $maxDeliveries = (int)($tp['max_events_lifetime'] ?? 4);
            if ($code === 'PATERNITY' && isset($ev['paternity_deliveries_used']) && $maxDeliveries > 0) {
                $legalParts[] = (int)$ev['paternity_deliveries_used'] . ' of ' . $maxDeliveries . ' deliveries used';
            }
        ?>
            <div class="leave-list-row">
                <div class="leave-list-main">
                    <div class="leave-list-name">
                        <?= e((string)($tp['leave_name'] ?? '')) ?>
                        <?php if ($isPendingVerification): ?>
                            <span class="badge badge-warning">Pending HR Verification</span>
                        <?php endif; ?>
                    </div>
                    <div class="leave-list-sub">
                        <?= e(implode(' · ', $legalParts)) ?>
                        <?php if ($isConf): ?>
                            <span class="leave-list-lock" title="Details of this leave are kept confidential"><?= icon('lock', 12) ?> Confidential</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="leave-list-value">
                    <span class="leave-list-days"><?= $entDays ?></span>
                    <span class="leave-list-sub"><?= e($entUnit) ?></span>
                </div>
                <div class="leave-list-actions">
                    <button type="button" class="leave-link-btn" <?= $policyAttrs($tp, $elig, $entDays . ' ' . $entUnit) ?>>View Policy</button>
                    <a href="<?= url('/leaves/apply?leave_type_id=' . (int)$tp['id']) ?>" class="btn btn-secondary btn-xs"><?= $isConf ? 'Apply Confidentially' : 'Apply' ?></a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php if (!empty($qualifications) || !empty($eventEntitlements)): ?>
        <div class="grid grid-cols-2" style="gap: var(--space-4); margin-top: var(--space-4);">
            <div class="card">
                <div class="card-header"><h3 class="card-title" id="secQualificationsHeading">My Statutory Qualifications</h3></div>
                <div class="table-wrapper" tabindex="0" role="region" aria-labelledby="secQualificationsHeading">
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
                <div class="card-header"><h3 class="card-title" id="secEventEntitlementsHeading">Active Event Entitlement Records</h3></div>
                <div class="table-wrapper" tabindex="0" role="region" aria-labelledby="secEventEntitlementsHeading">
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

<!-- 4. Company-Provided Event-Based Benefits -->
<section class="leave-section" aria-labelledby="secCompEventHeading">
    <div class="leave-section-head">
        <h2 class="leave-section-title" id="secCompEventHeading">Company-Provided Event-Based Benefits</h2>
    </div>
    <?php if (empty($companyEvents)): ?>
        <div class="card"><div class="card-body">No company event-based leave policies are active.</div></div>
    <?php else: ?>
        <div class="card leave-list">
            <?php foreach ($companyEvents as $ce):
                $ctp = $ce['type'];
                $celig = $ce['eligibility'] ?? [];
                [$cDays, $cUnit] = $eventEntitlement($ctp, $celig);
                $cBlurb = $eventBlurb($ctp);
            ?>
                <div class="leave-list-row">
                    <div class="leave-list-main">
                        <div class="leave-list-name"><?= e((string)($ctp['leave_name'] ?? '')) ?></div>
                        <?php if ($cBlurb !== ''): ?>
                            <div class="leave-list-sub"><?= e($cBlurb) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="leave-list-value">
                        <span class="leave-list-days"><?= $cDays ?></span>
                        <span class="leave-list-sub"><?= e($cUnit) ?></span>
                    </div>
                    <div class="leave-list-actions">
                        <button type="button" class="leave-link-btn" <?= $policyAttrs($ctp, $celig, $cDays . ' ' . $cUnit) ?>>View Policy</button>
                        <a href="<?= url('/leaves/apply?leave_type_id=' . (int)$ctp['id']) ?>" class="btn btn-secondary btn-xs">Apply</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<!-- 5. Leave Without Pay -->
<?php
    $lwopApproved = (int)($unpaidSummary['approved_days'] ?? 0);
    $lwopPending = (int)($unpaidSummary['pending_days'] ?? 0);
    $lwopTypeId = (int)($unpaidBenefits[0]['type']['id'] ?? 0);
?>
<section class="leave-section" aria-labelledby="secLwopHeading">
    <div class="leave-section-head">
        <h2 class="leave-section-title" id="secLwopHeading">Leave Without Pay</h2>
    </div>
    <div class="card leave-list">
        <div class="leave-list-row">
            <div class="leave-list-main">
                <div class="leave-list-name">Unpaid leave, <?= (int)$year ?></div>
                <?php if ($lwopPending > 0): ?>
                    <div class="leave-list-sub"><?= $lwopPending ?> day<?= $lwopPending === 1 ? '' : 's' ?> pending approval</div>
                <?php endif; ?>
            </div>
            <div class="leave-list-value">
                <span class="leave-list-days"><?= $lwopApproved ?></span>
                <span class="leave-list-sub"><?= $lwopApproved === 1 ? 'day' : 'days' ?> approved</span>
            </div>
            <div class="leave-list-actions">
                <?php if ($lwopTypeId > 0): ?>
                    <a href="<?= url('/leaves/apply?leave_type_id=' . $lwopTypeId) ?>" class="btn btn-secondary btn-xs">Request LWOP</a>
                <?php endif; ?>
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
    <div class="table-wrapper" tabindex="0" role="region" aria-labelledby="secPendingHeading">
        <table class="table table-hover table-centered">
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

<!-- 7. Balance Activity (compact; full ledger is kept in the backend and available to HR) -->
<?php
    $ledgerLastUpdated = !empty($ledgerTransactions[0]['created_at']) ? strtotime((string)$ledgerTransactions[0]['created_at']) : false;
?>
<section class="leave-section card leave-activity" aria-labelledby="secLedgerHeading">
    <div class="leave-activity-bar">
        <div class="leave-activity-text">
            <h2 class="leave-activity-title" id="secLedgerHeading">Balance Activity</h2>
            <p class="leave-activity-desc">Your balances reflect allocations, approved deductions, and authorized adjustments. <span class="leave-activity-meta">
                Last updated:
                <?php if ($ledgerLastUpdated): ?>
                    <time datetime="<?= e(date('c', $ledgerLastUpdated)) ?>"><?= e(date('M d, Y H:i', $ledgerLastUpdated)) ?></time>
                <?php else: ?>
                    No activity recorded for <?= (int)$year ?>
                <?php endif; ?>
            </span></p>
        </div>
        <?php if (!empty($ledgerTransactions)): ?>
            <button type="button" class="btn btn-secondary btn-sm" id="btnToggleBalanceActivity" aria-expanded="false" aria-controls="balanceActivityPanel">View Balance Activity</button>
        <?php endif; ?>
    </div>
    <?php if (!empty($ledgerTransactions)): ?>
    <div class="leave-activity-panel" id="balanceActivityPanel" hidden>
        <div class="table-wrapper" tabindex="0" role="region" aria-labelledby="secLedgerHeading">
            <table class="table table-hover table-centered">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Leave Type</th>
                        <th>Activity</th>
                        <th>Change</th>
                        <th>Balance</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ledgerTransactions as $tx):
                        $delta = (float)($tx['days_delta'] ?? 0);
                        $deltaStr = ($delta > 0 ? '+' : '') . rtrim(rtrim(number_format($delta, 2, '.', ''), '0'), '.');
                    ?>
                        <tr>
                            <td style="white-space: nowrap;"><?= !empty($tx['created_at']) ? e(date('M d, Y H:i', strtotime((string)$tx['created_at']))) : '—' ?></td>
                            <td><?= e((string)($tx['leave_name'] ?? '')) ?></td>
                            <td><?= e(ucwords(str_replace('_', ' ', (string)($tx['transaction_type'] ?? '')))) ?></td>
                            <td class="<?= $delta >= 0 ? 'ledger-delta-pos' : 'ledger-delta-neg' ?>"><?= e($deltaStr) ?>d</td>
                            <td><?= (int)round((float)($tx['balance_before'] ?? 0)) ?>d &rarr; <?= (int)round((float)($tx['balance_after'] ?? 0)) ?>d</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <script>
    (function () {
        var btn = document.getElementById('btnToggleBalanceActivity');
        var panel = document.getElementById('balanceActivityPanel');
        if (!btn || !panel) return;
        btn.addEventListener('click', function () {
            var open = panel.hidden;
            panel.hidden = !open;
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
            btn.textContent = open ? 'Hide Balance Activity' : 'View Balance Activity';
        });
    })();
    </script>
    <?php endif; ?>
</section>

<!-- 8. Policy Reference & Eligibility Summary -->
<section class="leave-section card" aria-labelledby="secPolicyRefHeading">
    <div class="card-header">
        <h2 class="card-title" id="secPolicyRefHeading">Policy Reference &amp; Eligibility Rules</h2>
    </div>
    <div class="table-wrapper" tabindex="0" role="region" aria-labelledby="secPolicyRefHeading">
        <table class="table table-hover table-centered">
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
