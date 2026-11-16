<?php
/**
 * Edit Leave Classification & Manage Non-Retroactive Policy Versions (HR & Admin)
 */
$type = $type ?? [];
$tid = (int)($type['id'] ?? 0);
$currentModel = (string)($type['entitlement_model'] ?? 'annual');
$currentUnit = (string)($type['default_unit'] ?? 'working_days');
$currentClassif = (string)($type['classification'] ?? 'Company');
?>
<div class="page-header">
    <div class="page-title-group">
        <h1>Configure <?= e((string)($type['leave_name'] ?? 'Leave Classification')) ?></h1>
        <p>Update core classification attributes and publish non-retroactive versioned rules for future effective dates.</p>
    </div>
    <div class="page-actions">
        <a href="<?= url('/hr/leave-types') ?>" class="btn btn-secondary btn-sm">
            <?= icon('arrow-left', 15) ?>
            <span>Back to Classifications</span>
        </a>
    </div>
</div>

<div class="grid grid-cols-2" style="gap: var(--space-5); align-items: start; margin-bottom: var(--space-6);">
    <!-- Left: Classification Attributes Form -->
    <div class="card">
        <div class="card-header"><h2 class="card-title">Classification Attributes</h2></div>
        <div class="card-body">
            <form method="POST" action="<?= url('/hr/leave-types/' . $tid . '/edit') ?>">
                <?= csrf_field() ?>

                <div class="grid grid-cols-2" style="gap: var(--space-3);">
                    <div class="form-group">
                        <label for="leave_name" class="form-label">Classification Name</label>
                        <input type="text" id="leave_name" name="leave_name" class="form-input" required minlength="2" maxlength="100" value="<?= e((string)($type['leave_name'] ?? '')) ?>">
                    </div>
                    <div class="form-group">
                        <label for="leave_code" class="form-label">System Code</label>
                        <input type="text" id="leave_code" name="leave_code" class="form-input" maxlength="32" value="<?= e((string)($type['leave_code'] ?? '')) ?>">
                    </div>
                    <div class="form-group">
                        <label for="classification" class="form-label">Classification</label>
                        <select id="classification" name="classification" class="form-select">
                            <option value="Company" <?= $currentClassif === 'Company' ? 'selected' : '' ?>>Company</option>
                            <option value="Statutory" <?= $currentClassif === 'Statutory' ? 'selected' : '' ?>>Statutory</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="entitlement_model" class="form-label">Entitlement Model</label>
                        <select id="entitlement_model" name="entitlement_model" class="form-select">
                            <option value="annual" <?= $currentModel === 'annual' ? 'selected' : '' ?>>Annual Balance</option>
                            <option value="event_based" <?= $currentModel === 'event_based' ? 'selected' : '' ?>>Event-Based</option>
                            <option value="unpaid" <?= $currentModel === 'unpaid' ? 'selected' : '' ?>>Unpaid (LWOP)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="default_days" class="form-label">Default Quota (Days)</label>
                        <input type="number" id="default_days" name="default_days" class="form-input" min="0" max="365" value="<?= (int)($type['default_days'] ?? 0) ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="default_unit" class="form-label">Duration Unit</label>
                        <select id="default_unit" name="default_unit" class="form-select">
                            <option value="working_days" <?= $currentUnit === 'working_days' ? 'selected' : '' ?>>Working Days</option>
                            <option value="calendar_days" <?= $currentUnit === 'calendar_days' ? 'selected' : '' ?>>Calendar Days</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-2" style="gap: var(--space-3);">
                    <div class="form-group">
                        <label for="legal_reference" class="form-label">Legal / Policy Reference</label>
                        <input type="text" id="legal_reference" name="legal_reference" class="form-input" maxlength="160" value="<?= e((string)($type['legal_reference'] ?? '')) ?>">
                    </div>
                    <div class="form-group">
                        <label for="status" class="form-label">Status</label>
                        <select name="status" id="status" class="form-select" required>
                            <option value="Active" <?= ($type['status'] ?? '') === 'Active' ? 'selected' : '' ?>>Active</option>
                            <option value="Inactive" <?= ($type['status'] ?? '') === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="description" class="form-label">Description</label>
                    <textarea name="description" id="description" rows="2" class="form-textarea"><?= e((string)($type['description'] ?? '')) ?></textarea>
                </div>

                <div style="display: flex; flex-wrap: wrap; gap: 1rem; margin-bottom: var(--space-4);">
                    <label style="display: inline-flex; align-items: center; gap: 0.4rem; font-size: var(--font-size-sm);">
                        <input type="checkbox" name="is_paid" value="1" <?= !empty($type['is_paid']) ? 'checked' : '' ?>> Paid Leave
                    </label>
                    <label style="display: inline-flex; align-items: center; gap: 0.4rem; font-size: var(--font-size-sm);">
                        <input type="checkbox" name="is_confidential" value="1" <?= !empty($type['is_confidential']) ? 'checked' : '' ?>> Confidential Privacy Protection
                    </label>
                    <label style="display: inline-flex; align-items: center; gap: 0.4rem; font-size: var(--font-size-sm);">
                        <input type="checkbox" name="statutory_protected" value="1" <?= !empty($type['statutory_protected']) ? 'checked' : '' ?>> Statutory Protected
                    </label>
                </div>

                <button type="submit" class="btn btn-primary btn-sm">Save Classification Changes</button>
            </form>
        </div>
    </div>

    <!-- Right: Create New Non-Retroactive Policy Version -->
    <div class="card">
        <div class="card-header"><h2 class="card-title">Create Non-Retroactive Policy Version</h2></div>
        <div class="card-body">
            <form method="POST" action="<?= url('/hr/leave-types/' . $tid . '/policies') ?>">
                <?= csrf_field() ?>
                <div class="grid grid-cols-2" style="gap: var(--space-3);">
                    <div class="form-group">
                        <label for="polEffFrom" class="form-label">Effective From</label>
                        <input type="date" id="polEffFrom" name="effective_from" class="form-input" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="polApprovalStatus" class="form-label">Workflow Stage</label>
                        <select id="polApprovalStatus" name="approval_status" class="form-select">
                            <option value="Draft">Draft</option>
                            <option value="PendingReview">Pending Review</option>
                            <option value="Active">Publish Active Immediately</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="polDays" class="form-label">Entitlement Days</label>
                        <input type="number" id="polDays" name="entitlement_days" class="form-input" min="0" max="365" value="<?= (int)($type['entitlement_days'] ?? ($type['default_days'] ?? 0)) ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="polUnit" class="form-label">Duration Unit</label>
                        <select id="polUnit" name="entitlement_unit" class="form-select">
                            <option value="working_days" <?= ($type['entitlement_unit'] ?? $currentUnit) === 'working_days' ? 'selected' : '' ?>>Working Days</option>
                            <option value="calendar_days" <?= ($type['entitlement_unit'] ?? $currentUnit) === 'calendar_days' ? 'selected' : '' ?>>Calendar Days</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="polMinSvc" class="form-label">Min Service (Months)</label>
                        <input type="number" id="polMinSvc" name="min_service_months" class="form-input" min="0" max="120" value="<?= (int)($type['min_service_months'] ?? 0) ?>">
                    </div>
                    <div class="form-group">
                        <label for="polCashMax" class="form-label">Max Cash Convertible Days</label>
                        <input type="number" id="polCashMax" name="cash_conversion_max_days" class="form-input" min="0" max="60" value="<?= (int)round((float)($type['cash_conversion_max_days'] ?? 0)) ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="polDocReq" class="form-label">Document Requirements</label>
                    <input type="text" id="polDocReq" name="document_requirements" class="form-input" maxlength="255" value="<?= e((string)($type['document_requirements'] ?? '')) ?>">
                </div>

                <div class="form-group">
                    <label for="polNotes" class="form-label">Change Notes (Audit Rationale)</label>
                    <input type="text" id="polNotes" name="change_notes" class="form-input" maxlength="255" placeholder="Describe policy amendment (does not alter historical approved requests)" required>
                </div>

                <div style="display: flex; flex-wrap: wrap; gap: 1rem; margin-bottom: var(--space-4);">
                    <label style="display: inline-flex; align-items: center; gap: 0.4rem; font-size: var(--font-size-xs);">
                        <input type="checkbox" name="counts_as_sil_equivalent" value="1" <?= !empty($type['counts_as_sil_equivalent']) ? 'checked' : '' ?>> SIL Equivalent
                    </label>
                    <label style="display: inline-flex; align-items: center; gap: 0.4rem; font-size: var(--font-size-xs);">
                        <input type="checkbox" name="stack_with_equivalent" value="1" <?= !empty($type['stack_with_equivalent']) ? 'checked' : '' ?>> Allow SIL Stacking
                    </label>
                    <label style="display: inline-flex; align-items: center; gap: 0.4rem; font-size: var(--font-size-xs);">
                        <input type="checkbox" name="cash_convertible" value="1" <?= !empty($type['cash_convertible']) ? 'checked' : '' ?>> Cash Convertible
                    </label>
                    <label style="display: inline-flex; align-items: center; gap: 0.4rem; font-size: var(--font-size-xs);">
                        <input type="checkbox" name="requires_documentation" value="1" <?= !empty($type['requires_documentation']) ? 'checked' : '' ?>> Requires Docs
                    </label>
                    <label style="display: inline-flex; align-items: center; gap: 0.4rem; font-size: var(--font-size-xs);">
                        <input type="checkbox" name="requires_qualification" value="1" <?= !empty($type['requires_qualification']) ? 'checked' : '' ?>> Requires Qualification
                    </label>
                </div>

                <button type="submit" class="btn btn-secondary btn-sm">Create Policy Version</button>
            </form>
        </div>
    </div>
</div>

<!-- Version History Table -->
<div class="card">
    <div class="card-header"><h2 class="card-title">Policy Version History (Non-Retroactive Audit Trail)</h2></div>
    <div class="table-wrapper">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Version</th>
                    <th>Effective Window</th>
                    <th>Quota &amp; Unit</th>
                    <th>Rules</th>
                    <th>Status</th>
                    <th>Notes</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($policyVersions)): ?>
                    <tr><td colspan="7" class="text-center" style="color: var(--color-text-muted); padding: 1.5rem;">No versioned policy records created yet.</td></tr>
                <?php else: foreach ($policyVersions as $pv):
                    $pvSt = (string)($pv['approval_status'] ?? 'Draft');
                    $pvBadge = match ($pvSt) {
                        'Active' => 'badge-success',
                        'PendingReview' => 'badge-warning',
                        'Superseded', 'Archived' => 'badge-neutral',
                        default => 'badge-info',
                    };
                ?>
                    <tr>
                        <td><strong><?= e((string)($pv['policy_code'] ?? 'V' . (int)$pv['version_number'])) ?></strong> (v<?= (int)$pv['version_number'] ?>)</td>
                        <td style="font-size: var(--font-size-xs);">
                            <?= e((string)($pv['effective_from'] ?? '—')) ?>
                            <?= !empty($pv['effective_to']) ? ' &ndash; ' . e((string)$pv['effective_to']) : ' onward' ?>
                        </td>
                        <td><?= (int)round((float)($pv['entitlement_days'] ?? 0)) ?> <?= ($pv['entitlement_unit'] ?? '') === 'calendar_days' ? 'Cal.' : 'Work' ?> days</td>
                        <td style="font-size: var(--font-size-xs);">
                            Min <?= (int)($pv['min_service_months'] ?? 0) ?>m &middot;
                            <?= !empty($pv['is_paid']) ? 'Paid' : 'Unpaid' ?>
                            <?= !empty($pv['counts_as_sil_equivalent']) ? '&middot; SIL Equiv.' : '' ?>
                            <?= !empty($pv['is_confidential']) ? '&middot; Confidential' : '' ?>
                        </td>
                        <td><span class="badge <?= $pvBadge ?>"><?= e($pvSt) ?></span></td>
                        <td style="font-size: var(--font-size-xs); max-width: 240px;"><?= e((string)($pv['change_notes'] ?? '—')) ?></td>
                        <td>
                            <?php if (in_array($pvSt, ['Draft', 'PendingReview'], true)): ?>
                                <form method="POST" action="<?= url('/hr/leave-policies/' . (int)$pv['id'] . '/activate') ?>" style="display: inline;">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-success btn-xs">Approve &amp; Activate</button>
                                </form>
                            <?php else: ?>
                                <span style="font-size: var(--font-size-xs); color: var(--color-text-muted);">Locked</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
