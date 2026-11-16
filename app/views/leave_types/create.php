<?php
/**
 * Create Leave Classification & Initial Policy Form (HR & Admin)
 */
?>
<div class="page-header">
    <div class="page-title-group">
        <h1>Create Leave Classification</h1>
        <p>Define a new statutory or company leave benefit together with its initial active policy rules.</p>
    </div>
    <div class="page-actions">
        <a href="<?= url('/hr/leave-types') ?>" class="btn btn-secondary btn-sm">
            <?= icon('arrow-left', 15) ?>
            <span>Back to Classifications</span>
        </a>
    </div>
</div>

<div class="card" style="max-width: 860px;">
    <div class="card-header"><h2 class="card-title">Leave Classification &amp; Policy Definition</h2></div>
    <div class="card-body">
        <form method="POST" action="<?= url('/hr/leave-types') ?>">
            <?= csrf_field() ?>

            <div class="grid grid-cols-2" style="gap: var(--space-4);">
                <div class="form-group">
                    <label for="leave_name" class="form-label">Classification Name</label>
                    <input type="text" id="leave_name" name="leave_name" class="form-input" required minlength="2" maxlength="100" placeholder="e.g. Magna Carta Study Leave">
                </div>
                <div class="form-group">
                    <label for="leave_code" class="form-label">System Policy Code</label>
                    <input type="text" id="leave_code" name="leave_code" class="form-input" maxlength="32" placeholder="Optional e.g. STUDY_LEAVE">
                </div>
                <div class="form-group">
                    <label for="classification" class="form-label">Legal Classification</label>
                    <select id="classification" name="classification" class="form-select">
                        <option value="Company">Company-Provided Benefit</option>
                        <option value="Statutory">Philippine Statutory Benefit</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="entitlement_model" class="form-label">Entitlement Model</label>
                    <select id="entitlement_model" name="entitlement_model" class="form-select">
                        <option value="annual">Annual Balance (Year-based credits)</option>
                        <option value="event_based">Event-Based Entitlement (Per qualifying event)</option>
                        <option value="unpaid">Unpaid Leave (Zero paid credit deduction)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="default_days" class="form-label">Default Days Quota (0 allowed for Unpaid)</label>
                    <input type="number" id="default_days" name="default_days" class="form-input" min="0" max="365" value="5" required>
                </div>
                <div class="form-group">
                    <label for="default_unit" class="form-label">Duration Unit</label>
                    <select id="default_unit" name="default_unit" class="form-select">
                        <option value="working_days">Working Days (Mon–Fri)</option>
                        <option value="calendar_days">Calendar Days (Inclusive)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="min_service_months" class="form-label">Minimum Service Tenure (Months)</label>
                    <input type="number" id="min_service_months" name="min_service_months" class="form-input" min="0" max="120" value="0">
                </div>
                <div class="form-group">
                    <label for="effective_from" class="form-label">Effective From Date</label>
                    <input type="date" id="effective_from" name="effective_from" class="form-input" value="<?= date('Y-m-d') ?>">
                </div>
            </div>

            <div class="grid grid-cols-2" style="gap: var(--space-4);">
                <div class="form-group">
                    <label for="legal_reference" class="form-label">Legal / Policy Reference</label>
                    <input type="text" id="legal_reference" name="legal_reference" class="form-input" maxlength="160" placeholder="e.g. Company Handbook Sec. 4 or RA reference">
                </div>
                <div class="form-group">
                    <label for="status" class="form-label">Operational Status</label>
                    <select name="status" id="status" class="form-select" required>
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="description" class="form-label">Description &amp; Eligibility Notes</label>
                <textarea name="description" id="description" rows="2" class="form-textarea" placeholder="Describe covered employees and usage rules."></textarea>
            </div>

            <div class="form-group">
                <label for="document_requirements" class="form-label">Required Supporting Documents (if applicable)</label>
                <input type="text" id="document_requirements" name="document_requirements" class="form-input" maxlength="255" placeholder="e.g. Medical certificate or official endorsement">
            </div>

            <div style="display: flex; flex-wrap: wrap; gap: 1.25rem; margin-bottom: var(--space-5); padding: var(--space-3); background: var(--color-bg-subtle); border-radius: var(--radius-md); border: 1px solid var(--color-border);">
                <label style="display: inline-flex; align-items: center; gap: 0.45rem; font-size: var(--font-size-sm); cursor: pointer;">
                    <input type="checkbox" name="is_paid" value="1" checked> Paid Leave Benefit
                </label>
                <label style="display: inline-flex; align-items: center; gap: 0.45rem; font-size: var(--font-size-sm); cursor: pointer;">
                    <input type="checkbox" name="counts_as_sil_equivalent" value="1"> Counts toward Art. 95 SIL (>=5d VL rule)
                </label>
                <label style="display: inline-flex; align-items: center; gap: 0.45rem; font-size: var(--font-size-sm); cursor: pointer;">
                    <input type="checkbox" name="cash_convertible" value="1"> Cash Convertible (tracked via HR memo)
                </label>
                <label style="display: inline-flex; align-items: center; gap: 0.45rem; font-size: var(--font-size-sm); cursor: pointer;">
                    <input type="checkbox" name="requires_documentation" value="1"> Requires Supporting Document
                </label>
                <label style="display: inline-flex; align-items: center; gap: 0.45rem; font-size: var(--font-size-sm); cursor: pointer;">
                    <input type="checkbox" name="is_confidential" value="1"> Strict Confidential Handling
                </label>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: var(--space-3);">
                <a href="<?= url('/hr/leave-types') ?>" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">Create Leave Classification</button>
            </div>
        </form>
    </div>
</div>
