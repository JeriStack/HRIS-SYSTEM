<?php
/**
 * HR Statutory Leave Qualifications & Verification Console
 * Covers Solo Parent ID (SPIC), Paternity Marriage Proof, and SIL Statutory Exclusions
 */
$selectedStatus = (string)($selectedStatus ?? '');
$selectedType = (string)($selectedType ?? '');
?>
<div class="page-header">
    <div class="page-title-group">
        <h1>Statutory Leave Qualifications</h1>
        <p>Verify Solo Parent IDs (RA 11861), Paternity lawful spouse marriage proof (RA 8187), and Labor Code Art. 95 SIL exclusions.</p>
    </div>
    <div class="page-actions">
        <a href="<?= url('/leaves/balances') ?>" class="btn btn-secondary btn-sm">
            <?= icon('balances', 15) ?>
            <span>Leave Balances</span>
        </a>
        <a href="<?= url('/hr/leave-types') ?>" class="btn btn-secondary btn-sm">
            <?= icon('layers', 15) ?>
            <span>Leave Policies</span>
        </a>
    </div>
</div>

<div class="leave-split-layout">
    <!-- Left / Main: Existing Qualification Records Table -->
    <div>
        <div class="card" style="margin-bottom: var(--space-4); padding: var(--space-4);">
            <form method="GET" action="<?= url('/hr/leave-qualifications') ?>" style="display: flex; gap: var(--space-3); align-items: flex-end; flex-wrap: wrap;">
                <div style="min-width: 180px;">
                    <label for="qualTypeFilter" class="form-label" style="font-size: var(--font-size-xs);">Qualification Type</label>
                    <select id="qualTypeFilter" name="type" class="form-select">
                        <option value="">All Types</option>
                        <option value="solo_parent_spic" <?= $selectedType === 'solo_parent_spic' ? 'selected' : '' ?>>Solo Parent SPIC (RA 11861)</option>
                        <option value="paternity_marriage" <?= $selectedType === 'paternity_marriage' ? 'selected' : '' ?>>Paternity Marriage Proof (RA 8187)</option>
                        <option value="sil_exclusion" <?= $selectedType === 'sil_exclusion' ? 'selected' : '' ?>>SIL Exclusion Override (Art. 95)</option>
                        <option value="maternity_eligibility" <?= $selectedType === 'maternity_eligibility' ? 'selected' : '' ?>>Maternity Record</option>
                        <option value="slw_eligibility" <?= $selectedType === 'slw_eligibility' ? 'selected' : '' ?>>SLW Qualification</option>
                        <option value="vawc_protection" <?= $selectedType === 'vawc_protection' ? 'selected' : '' ?>>VAWC Protection Order</option>
                    </select>
                </div>
                <div style="min-width: 160px;">
                    <label for="qualStatusFilter" class="form-label" style="font-size: var(--font-size-xs);">Verification Status</label>
                    <select id="qualStatusFilter" name="status" class="form-select">
                        <option value="">All Statuses</option>
                        <option value="PendingVerification" <?= $selectedStatus === 'PendingVerification' ? 'selected' : '' ?>>Pending Verification</option>
                        <option value="Verified" <?= $selectedStatus === 'Verified' ? 'selected' : '' ?>>Verified</option>
                        <option value="Expired" <?= $selectedStatus === 'Expired' ? 'selected' : '' ?>>Expired</option>
                        <option value="Revoked" <?= $selectedStatus === 'Revoked' ? 'selected' : '' ?>>Revoked</option>
                    </select>
                </div>
                <div style="display: flex; gap: 0.5rem;">
                    <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                    <a href="<?= url('/hr/leave-qualifications') ?>" class="btn btn-secondary btn-sm">Reset</a>
                </div>
            </form>
        </div>

        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Employee Qualification Directory (<?= count($qualifications ?? []) ?>)</h2>
            </div>
            <div class="table-wrapper">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Qualification</th>
                            <th>Reference #</th>
                            <th>Validity Window</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($qualifications)): ?>
                            <tr>
                                <td colspan="6" class="text-center" style="color: var(--color-text-muted); padding: 2rem;">
                                    No statutory qualification records match your filters.
                                </td>
                            </tr>
                        <?php else: foreach ($qualifications as $q):
                            $st = (string)($q['verification_status'] ?? 'PendingVerification');
                            $badgeCls = match ($st) {
                                'Verified' => 'badge-success',
                                'Expired', 'Revoked', 'Rejected' => 'badge-danger',
                                default => 'badge-warning',
                            };
                            $empLabel = trim(($q['first_name'] ?? '') . ' ' . ($q['last_name'] ?? ''));
                        ?>
                            <tr>
                                <td>
                                    <strong><?= e($empLabel) ?></strong>
                                    <div style="font-size: var(--font-size-xs); color: var(--color-text-muted);">
                                        <?= e((string)($q['employee_no'] ?? '')) ?> &middot; <?= e((string)($q['department_name'] ?? 'Unassigned')) ?>
                                    </div>
                                </td>
                                <td>
                                    <span style="font-weight: 500;"><?= e(ucwords(str_replace('_', ' ', (string)($q['qualification_type'] ?? '')))) ?></span>
                                    <?php if (!empty($q['sil_exempt_override'])): ?>
                                        <div style="font-size: var(--font-size-xs); color: var(--color-warning);">SIL Exempt: <?= e((string)($q['exclusion_reason'] ?? 'Excluded')) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><?= e((string)($q['reference_no'] ?? '—')) ?></td>
                                <td style="font-size: var(--font-size-xs);">
                                    <?= !empty($q['valid_from']) ? e(date('M d, Y', strtotime((string)$q['valid_from']))) : '—' ?>
                                    &ndash;
                                    <?= !empty($q['valid_to']) ? e(date('M d, Y', strtotime((string)$q['valid_to']))) : 'Ongoing' ?>
                                </td>
                                <td><span class="badge <?= $badgeCls ?>"><?= e($st) ?></span></td>
                                <td>
                                    <?php if ($st !== 'Verified'): ?>
                                        <form method="POST" action="<?= url('/hr/leave-qualifications/verify') ?>" style="display: inline;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= (int)$q['id'] ?>">
                                            <input type="hidden" name="employee_id" value="<?= (int)$q['employee_id'] ?>">
                                            <input type="hidden" name="qualification_type" value="<?= e((string)$q['qualification_type']) ?>">
                                            <input type="hidden" name="verification_status" value="Verified">
                                            <input type="hidden" name="reference_no" value="<?= e((string)($q['reference_no'] ?? '')) ?>">
                                            <input type="hidden" name="valid_from" value="<?= e((string)($q['valid_from'] ?? '')) ?>">
                                            <input type="hidden" name="valid_to" value="<?= e((string)($q['valid_to'] ?? '')) ?>">
                                            <input type="hidden" name="sil_exempt_override" value="<?= !empty($q['sil_exempt_override']) ? '1' : '0' ?>">
                                            <input type="hidden" name="exclusion_reason" value="<?= e((string)($q['exclusion_reason'] ?? '')) ?>">
                                            <button type="submit" class="btn btn-success btn-xs">Verify</button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($st === 'Verified'): ?>
                                        <form method="POST" action="<?= url('/hr/leave-qualifications/verify') ?>" style="display: inline;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= (int)$q['id'] ?>">
                                            <input type="hidden" name="employee_id" value="<?= (int)$q['employee_id'] ?>">
                                            <input type="hidden" name="qualification_type" value="<?= e((string)$q['qualification_type']) ?>">
                                            <input type="hidden" name="verification_status" value="Revoked">
                                            <input type="hidden" name="reference_no" value="<?= e((string)($q['reference_no'] ?? '')) ?>">
                                            <input type="hidden" name="valid_from" value="<?= e((string)($q['valid_from'] ?? '')) ?>">
                                            <input type="hidden" name="valid_to" value="<?= e((string)($q['valid_to'] ?? '')) ?>">
                                            <button type="submit" class="btn btn-secondary btn-xs">Revoke</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Right: Record / Update Statutory Qualification Form -->
    <aside class="card">
        <div class="card-header"><h2 class="card-title">Record / Verify Qualification</h2></div>
        <div class="card-body">
            <form method="POST" action="<?= url('/hr/leave-qualifications/verify') ?>">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label for="qualEmployeeId" class="form-label">Employee</label>
                    <select id="qualEmployeeId" name="employee_id" class="form-select" required>
                        <option value="">-- Select Employee --</option>
                        <?php foreach ($employees ?? [] as $emp): ?>
                            <option value="<?= (int)$emp['id'] ?>">
                                <?= e(($emp['employee_no'] ?? '') . ' — ' . ($emp['first_name'] ?? '') . ' ' . ($emp['last_name'] ?? '')) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="qualType" class="form-label">Qualification Category</label>
                    <select id="qualType" name="qualification_type" class="form-select" required>
                        <option value="solo_parent_spic">Solo Parent ID (SPIC - RA 11861)</option>
                        <option value="paternity_marriage">Paternity Marriage Proof (RA 8187)</option>
                        <option value="sil_exclusion">SIL Exclusion Override (Labor Code Art. 95)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="qualStatus" class="form-label">Verification Status</label>
                    <select id="qualStatus" name="verification_status" class="form-select" required>
                        <option value="Verified">Verified</option>
                        <option value="PendingVerification">Pending Verification</option>
                        <option value="Expired">Expired</option>
                        <option value="Revoked">Revoked</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="qualRefNo" class="form-label">Document / SPIC / Registry Reference #</label>
                    <input type="text" id="qualRefNo" name="reference_no" class="form-input" maxlength="100" placeholder="e.g. SPIC-2026-0041 or PSA-MC-991">
                </div>

                <div class="grid grid-cols-2" style="gap: var(--space-3);">
                    <div class="form-group">
                        <label for="qualValidFrom" class="form-label">Valid From</label>
                        <input type="date" id="qualValidFrom" name="valid_from" class="form-input">
                    </div>
                    <div class="form-group">
                        <label for="qualValidTo" class="form-label">Valid Until (SPIC Expiry)</label>
                        <input type="date" id="qualValidTo" name="valid_to" class="form-input">
                    </div>
                </div>

                <div class="form-group">
                    <label style="display: inline-flex; align-items: center; gap: 0.5rem; font-size: var(--font-size-sm); cursor: pointer;">
                        <input type="checkbox" name="sil_exempt_override" value="1">
                        <span>Apply Article 95 SIL statutory exclusion</span>
                    </label>
                </div>

                <div class="form-group">
                    <label for="qualExclusionReason" class="form-label">Exclusion Reason (if SIL Exempt)</label>
                    <input type="text" id="qualExclusionReason" name="exclusion_reason" class="form-input" maxlength="150" placeholder="e.g. Field personnel / establishment <10">
                </div>

                <div class="form-group">
                    <label for="qualNotes" class="form-label">Verification Notes</label>
                    <textarea id="qualNotes" name="supporting_notes" class="form-textarea" rows="2" placeholder="HR verification remarks"></textarea>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%;">Save Qualification Record</button>
            </form>
        </div>
    </aside>
</div>
