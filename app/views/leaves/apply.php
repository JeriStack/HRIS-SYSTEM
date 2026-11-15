<?php
/**
 * Dynamic Philippine Statutory & Company Leave Application Form
 * Preserves core form IDs for JS & automated tests while exposing statutory category panels & document upload
 */
$preselectedTypeId = (int)($_GET['leave_type_id'] ?? 0);
$maxMb = number_format(((int)($maxDocBytes ?? 5242880)) / (1024 * 1024), 0);
$paternityUsed = 0;
foreach ($leaveTypes ?? [] as $ltCheck) {
    if (strtoupper((string)($ltCheck['leave_code'] ?? '')) === 'PATERNITY') {
        $paternityUsed = (int)($ltCheck['deliveries_used'] ?? 0);
        break;
    }
}
?>
<div class="page-header">
    <div class="page-title-group">
        <h1>File Leave Request</h1>
        <p>Select an eligible statutory or company benefit, attach required documents, and verify your duration before filing.</p>
    </div>
    <div class="page-actions">
        <a href="<?= url('/employee/leave-balances') ?>" class="btn btn-secondary btn-sm">
            <?= icon('arrow-left', 15) ?>
            <span>My Balances</span>
        </a>
    </div>
</div>

<div class="leave-apply-layout leave-split-layout">
    <div class="card">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
            <h2 class="card-title">Leave Application Form</h2>
            <span id="selectedTypeLegalRef" class="badge badge-statutory" hidden></span>
        </div>
        <div class="card-body">
            <form id="leaveApplicationForm" action="<?= url('/leaves/apply') ?>" method="POST" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" id="numberOfDaysInput" name="number_of_days" value="0">

                <div class="form-group">
                    <label for="leaveTypeSelect" class="form-label">Leave Classification</label>
                    <select id="leaveTypeSelect" name="leave_type_id" class="form-select" required>
                        <option value="">-- Select Leave Benefit --</option>
                        <?php foreach ($leaveTypes ?? [] as $lt):
                            $ltId = (int)$lt['id'];
                            $code = strtoupper(trim((string)($lt['leave_code'] ?? '')));
                            $model = (string)($lt['entitlement_model'] ?? 'annual');
                            $unit = (string)($lt['default_unit'] ?? 'working_days');
                            $elig = $lt['eligibility'] ?? ['eligible' => true, 'reason' => ''];
                            $rules = is_array($lt['rules_decoded'] ?? null) ? $lt['rules_decoded'] : [];
                            $allowsCrossYear = !empty($rules['allows_cross_year']) || $code === 'MATERNITY' || $model === 'event_based';
                            $balRem = $lt['balance_remaining'];
                            $balAlloc = $lt['balance_allocated'];
                            $isSelected = $preselectedTypeId > 0 && $preselectedTypeId === $ltId;
                        ?>
                            <option
                                value="<?= $ltId ?>"
                                data-name="<?= e((string)$lt['leave_name']) ?>"
                                data-code="<?= e($code) ?>"
                                data-unit="<?= e($unit) ?>"
                                data-model="<?= e($model) ?>"
                                data-cross-year="<?= $allowsCrossYear ? '1' : '0' ?>"
                                data-eligible="<?= !empty($elig['eligible']) ? '1' : '0' ?>"
                                data-eligible-reason="<?= e((string)($elig['reason'] ?? '')) ?>"
                                data-confidential="<?= !empty($lt['is_confidential']) ? '1' : '0' ?>"
                                data-requires-doc="<?= !empty($lt['requires_documentation']) ? '1' : '0' ?>"
                                data-doc-req="<?= e((string)($lt['document_requirements'] ?? '')) ?>"
                                data-legal="<?= e((string)($lt['legal_reference'] ?? '')) ?>"
                                data-balance="<?= $balRem !== null ? (int)$balRem : '' ?>"
                                data-allocated="<?= $balAlloc !== null ? (int)$balAlloc : '' ?>"
                                <?= $isSelected ? 'selected' : '' ?>
                            >
                                <?= e((string)$lt['leave_name']) ?>
                                (<?= e($lt['classification'] ?? 'Company') ?><?= $model === 'unpaid' ? ', Unpaid' : ($model === 'event_based' ? ', Event' : ($balRem !== null ? ', ' . (int)$balRem . 'd left' : '')) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span id="selectedTypeBalanceHint" class="badge badge-info" style="display: none; margin-top: 0.45rem;"></span>
                </div>

                <div id="eligibilityStatusBanner" class="leave-eligibility-banner" style="margin-bottom: var(--space-4);" hidden></div>

                <div id="confidentialPrivacyNotice" class="leave-confidential-banner" style="margin-bottom: var(--space-4);" hidden>
                    <?= icon('lock', 16) ?>
                    <div>
                        <strong>Protected Confidential Filing:</strong> Reason narratives and medical/protection attachments for this leave are encrypted from general staff views and restricted strictly to authorized clearance holders with access audit logging.
                    </div>
                </div>

                <div class="grid grid-cols-2" style="gap: var(--space-4);">
                    <div class="form-group">
                        <label for="leaveStartDate" class="form-label">Start Date</label>
                        <input type="date" id="leaveStartDate" name="start_date" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label for="leaveEndDate" class="form-label">End Date</label>
                        <input type="date" id="leaveEndDate" name="end_date" class="form-input" required>
                    </div>
                </div>

                <div id="leaveCalcBox" class="card" style="padding: 0.85rem 1rem; margin-bottom: var(--space-4); background: var(--color-bg-subtle); border: 1px solid var(--color-border);">
                    <div style="display: flex; justify-content: space-between; align-items: center; gap: 1rem;">
                        <span style="font-size: var(--font-size-sm); color: var(--color-text-muted);">Calculated Leave Duration</span>
                        <strong id="calculatedDaysDisplay" style="font-size: var(--font-size-md);">0 Working Days</strong>
                    </div>
                    <div id="dateValidationError" class="field-error-text" style="display: none;"></div>
                </div>

                <!-- Conditional Panel: Maternity Leave (RA 11210) -->
                <div class="leave-conditional-panel" data-for-code="MATERNITY" hidden>
                    <strong style="font-size: var(--font-size-sm);">Maternity Leave Scenario (RA 11210)</strong>
                    <div class="grid grid-cols-2" style="gap: var(--space-3);">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="maternityScenario" class="form-label">Maternity Classification</label>
                            <select id="maternityScenario" name="maternity_scenario" class="form-select">
                                <option value="live_childbirth">Live Childbirth (105 calendar days)</option>
                                <option value="solo_parent">Qualified Solo Parent (120 calendar days)</option>
                                <option value="miscarriage_etp">Miscarriage / Emergency Termination (60 calendar days)</option>
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="matEventDate" class="form-label">Expected / Actual Delivery Date</label>
                            <input type="date" id="matEventDate" name="event_date" class="form-input">
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="maternityUnpaidExt" class="form-label">Optional Unpaid Extension (0–30 days)</label>
                            <input type="number" id="maternityUnpaidExt" name="unpaid_extension_days" class="form-input" min="0" max="30" value="0">
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="maternityFatherTransfer" class="form-label">Transfer to Child's Father (0–7 days)</label>
                            <input type="number" id="maternityFatherTransfer" name="transfer_to_father_days" class="form-input" min="0" max="7" value="0">
                        </div>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="motherAllocationRef" class="form-label">Allocation / SSS MAT Notification Reference (Optional)</label>
                        <input type="text" id="motherAllocationRef" name="mother_allocation_ref" class="form-input" maxlength="120" placeholder="Reference code if applicable">
                    </div>
                </div>

                <!-- Conditional Panel: Paternity Leave (RA 8187) -->
                <div class="leave-conditional-panel" data-for-code="PATERNITY" hidden>
                    <strong style="font-size: var(--font-size-sm);">Paternity Leave Delivery Details (RA 8187 &middot; Used: <?= $paternityUsed ?> / 4)</strong>
                    <div class="grid grid-cols-3" style="gap: var(--space-3);">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="deliverySequence" class="form-label">Delivery Sequence (1–4)</label>
                            <input type="number" id="deliverySequence" name="delivery_sequence" class="form-input" min="1" max="4" value="<?= min(4, $paternityUsed + 1) ?>">
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="patEventDate" class="form-label">Delivery / Miscarriage Date</label>
                            <input type="date" id="patEventDate" name="event_date" class="form-input">
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="spouseName" class="form-label">Lawful Spouse Full Name</label>
                            <input type="text" id="spouseName" name="spouse_name" class="form-input" maxlength="120" placeholder="Lawful spouse name">
                        </div>
                    </div>
                </div>

                <!-- Conditional Panel: Special Leave for Women (RA 9710) -->
                <div class="leave-conditional-panel" data-for-code="SLW" hidden>
                    <strong style="font-size: var(--font-size-sm);">Gynecological Surgery Certification (RA 9710)</strong>
                    <div class="grid grid-cols-2" style="gap: var(--space-3);">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="physicianCertRef" class="form-label">Physician Medical Certificate Reference</label>
                            <input type="text" id="physicianCertRef" name="physician_cert_ref" class="form-input" maxlength="120" placeholder="Attending OB-GYN / Hospital Cert #">
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="slwEventDate" class="form-label">Scheduled / Actual Surgery Date</label>
                            <input type="date" id="slwEventDate" name="event_date" class="form-input">
                        </div>
                    </div>
                    <label style="display: inline-flex; align-items: center; gap: 0.5rem; font-size: var(--font-size-sm); cursor: pointer;">
                        <input type="checkbox" name="has_physician_cert" value="1">
                        <span>I confirm a competent physician certificate is attached or held on file.</span>
                    </label>
                </div>

                <!-- Conditional Panel: VAWC Leave (RA 9262) -->
                <div class="leave-conditional-panel" data-for-code="VAWC" hidden>
                    <strong style="font-size: var(--font-size-sm);">Protection Order / Certification Reference (RA 9262)</strong>
                    <div class="grid grid-cols-3" style="gap: var(--space-3);">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="protectionOrderRef" class="form-label">BPO / TPO / PPO Reference</label>
                            <input type="text" id="protectionOrderRef" name="protection_order_ref" class="form-input" maxlength="120" placeholder="Barangay or Court Order #">
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="certificationRef" class="form-label">Prosecutor / Medical Cert Ref</label>
                            <input type="text" id="certificationRef" name="certification_ref" class="form-input" maxlength="120" placeholder="Alternative certification ref">
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="vawcExtensionDays" class="form-label">Order-Authorized Extension Days</label>
                            <input type="number" id="vawcExtensionDays" name="vawc_extension_days" class="form-input" min="0" max="60" value="0">
                        </div>
                    </div>
                </div>

                <!-- Conditional Panel: Bereavement Leave -->
                <div class="leave-conditional-panel" data-for-code="BEREAVEMENT" hidden>
                    <strong style="font-size: var(--font-size-sm);">Immediate Family Bereavement Verification</strong>
                    <div class="grid grid-cols-3" style="gap: var(--space-3);">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="relationshipType" class="form-label">Relationship</label>
                            <select id="relationshipType" name="relationship_type" class="form-select">
                                <option value="">-- Select Relationship --</option>
                                <option value="spouse">Spouse</option>
                                <option value="child">Child</option>
                                <option value="parent">Parent</option>
                                <option value="sibling">Sibling</option>
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="deceasedName" class="form-label">Deceased Family Member</label>
                            <input type="text" id="deceasedName" name="deceased_name" class="form-input" maxlength="120">
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="bereavEventDate" class="form-label">Date of Passing</label>
                            <input type="date" id="bereavEventDate" name="event_date" class="form-input">
                        </div>
                    </div>
                </div>

                <div class="form-group" style="margin-top: var(--space-4);">
                    <label for="leaveReason" class="form-label">Reason / Purpose of Absence</label>
                    <textarea id="leaveReason" name="reason" rows="3" class="form-textarea" required minlength="5" maxlength="1000" placeholder="Provide concise context for HR review (kept strictly confidential for RA 9262 / RA 9710 filings)."></textarea>
                </div>

                <div class="card" style="padding: var(--space-4); margin-bottom: var(--space-4); background: var(--color-bg-subtle); border: 1px solid var(--color-border);">
                    <div class="grid grid-cols-2" style="gap: var(--space-3);">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="documentCategory" class="form-label">Supporting Document Category</label>
                            <select id="documentCategory" name="document_category" class="form-select">
                                <option value="supporting_document">General Supporting Document</option>
                                <option value="medical_certificate">Medical / Physician Certificate</option>
                                <option value="protection_order">Protection Order / Legal Certification</option>
                                <option value="solo_parent_id">Solo Parent Identification Card (SPIC)</option>
                                <option value="marriage_certificate">Marriage Certificate / Delivery Proof</option>
                                <option value="death_certificate">Death Certificate / Obituary Proof</option>
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="supportingDocumentInput" class="form-label">Attachment (PDF, JPEG, PNG, WebP &middot; Max <?= e($maxMb) ?> MB)</label>
                            <input type="file" id="supportingDocumentInput" name="supporting_document" class="form-input" accept=".pdf,.jpg,.jpeg,.png,.webp">
                        </div>
                    </div>
                    <span id="docRequirementHint" class="form-hint-text">Optional supporting proof (PDF, JPEG, PNG, or WebP, max 5 MB).</span>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: var(--space-3); flex-wrap: wrap;">
                    <button type="button" id="previewLeaveBtn" class="btn btn-secondary">
                        <?= icon('eye', 16) ?>
                        <span>Preview Summary</span>
                    </button>
                    <button type="submit" id="submitLeaveBtn" class="btn btn-primary">
                        <?= icon('send', 16) ?>
                        <span>Submit Leave Application</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <aside class="card">
        <div class="card-header"><h2 class="card-title">Benefit Snapshot (<?= (int)date('Y') ?>)</h2></div>
        <div class="card-body" style="padding: 0;">
            <div class="table-wrapper">
                <table class="table">
                    <thead><tr><th>Benefit</th><th>Quota / Status</th></tr></thead>
                    <tbody>
                        <?php foreach ($leaveTypes ?? [] as $lt):
                            $el = $lt['eligibility'] ?? [];
                            $mdl = (string)($lt['entitlement_model'] ?? 'annual');
                        ?>
                            <tr data-balance-type-id="<?= (int)$lt['id'] ?>">
                                <td>
                                    <div style="font-weight: 600; font-size: var(--font-size-sm);"><?= e((string)$lt['leave_name']) ?></div>
                                    <div style="font-size: var(--font-size-xs); color: var(--color-text-muted);"><?= e((string)($lt['classification'] ?? 'Company')) ?></div>
                                </td>
                                <td style="text-align: right; white-space: nowrap;">
                                    <?php if (empty($el['eligible'])): ?>
                                        <span class="badge badge-neutral">Ineligible</span>
                                    <?php elseif ($mdl === 'unpaid'): ?>
                                        <span class="badge badge-unpaid">Unpaid</span>
                                    <?php elseif ($mdl === 'event_based'): ?>
                                        <span class="badge badge-info"><?= (int)($lt['default_days'] ?? 0) ?>d / event</span>
                                    <?php else: ?>
                                        <strong><?= (int)($lt['balance_remaining'] ?? 0) ?>d</strong> / <?= (int)($lt['balance_allocated'] ?? 0) ?>d
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </aside>
</div>

<dialog id="leavePreviewDialog" class="leave-dialog" data-close-on-backdrop="1" aria-labelledby="leavePrevTitle">
    <div class="leave-dialog-head">
        <h3 id="leavePrevTitle" class="leave-dialog-title">Confirm Leave Application</h3>
        <button type="button" class="profile-dialog-close" data-dialog-close aria-label="Close preview"><?= icon('x', 18) ?></button>
    </div>
    <div class="leave-dialog-body">
        <dl style="margin: 0;">
            <div class="leave-dialog-kv"><dt>Leave Benefit</dt><dd id="prevTypeName">—</dd></div>
            <div class="leave-dialog-kv"><dt>Start Date</dt><dd id="prevStartDate">—</dd></div>
            <div class="leave-dialog-kv"><dt>End Date</dt><dd id="prevEndDate">—</dd></div>
            <div class="leave-dialog-kv"><dt>Calculated Duration</dt><dd id="prevDuration">—</dd></div>
            <div class="leave-dialog-kv"><dt>Reason</dt><dd id="prevReason">—</dd></div>
            <div class="leave-dialog-kv"><dt>Attachment</dt><dd id="prevDocument">None attached</dd></div>
        </dl>
    </div>
    <div class="leave-dialog-foot">
        <button type="button" class="btn btn-secondary btn-sm" data-dialog-close>Edit Application</button>
        <button type="button" id="confirmSubmitLeaveBtn" class="btn btn-primary btn-sm">Confirm &amp; Submit</button>
    </div>
</dialog>
