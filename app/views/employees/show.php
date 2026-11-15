<?php

use App\Models\EmploymentHistory;
use App\Models\ProfileCorrectionRequest;
use App\Services\EmployeeTenureCalculator;
use App\Services\ProfilePictureService;

$fullName = trim("{$employee['first_name']} " . ($employee['middle_name'] ? "{$employee['middle_name']} " : "") . "{$employee['last_name']}");
$initials = strtoupper(substr((string)$employee['first_name'], 0, 1) . substr((string)$employee['last_name'], 0, 1)) ?: 'E';

$birthDateFormatted = EmployeeTenureCalculator::formatDate($employee['birth_date'] ?? null);
$ageStr = '';
if (!empty($employee['birth_date'])) {
    try {
        $bd = new DateTimeImmutable((string)$employee['birth_date']);
        $ageStr = ' (Age ' . $bd->diff(new DateTimeImmutable('today'))->y . ')';
    } catch (\Throwable $e) {
        $ageStr = '';
    }
}

$tenureData = is_array($tenure ?? null)
    ? $tenure
    : EmployeeTenureCalculator::calculate($employee['date_hired'] ?? null);
$dateHiredFormatted = EmployeeTenureCalculator::formatDate($employee['date_hired'] ?? null);
$empStatus = (string)($employee['employment_status'] ?? 'Active');
$empType   = (string)($employee['employment_type'] ?? 'Regular');
$resolvedAvatarUrl = $avatarUrl ?? ProfilePictureService::avatarUrl((int)$employee['id'], $employee['profile_picture'] ?? null);
$hasAvatar = !empty($resolvedAvatarUrl);

$lastPromotionDate = is_array($lastPromotion ?? null) && !empty($lastPromotion['effective_date'])
    ? EmployeeTenureCalculator::formatDate((string)$lastPromotion['effective_date'])
    : 'No promotion recorded';
$primaryEmergency = !empty($emergencyContacts[0]) && is_array($emergencyContacts[0]) ? $emergencyContacts[0] : null;
$historyErrors = is_array($historyErrors ?? null) ? $historyErrors : [];
$historyOld = is_array($historyOld ?? null) ? $historyOld : [];
?>

<div class="page-header">
    <div>
        <a href="<?= url('/hr/employees') ?>" class="emp-quiet-action" style="padding-left: 0; margin-bottom: var(--space-1);">
            <?= icon('arrow-left', 15) ?>
            <span>Back to Employee Directory</span>
        </a>
        <h1 class="page-title">Employee Profile</h1>
        <p class="page-subtitle">Comprehensive workforce record, identity verification, and chronological employment history.</p>
    </div>
    <div class="page-actions">
        <a href="<?= url('/hr/employees/' . (int)$employee['id'] . '/edit') ?>" class="btn btn-primary">
            <span>Edit Employee</span>
        </a>
        <?php if ($empStatus === 'Active'): ?>
            <button type="button" class="btn btn-danger" onclick="openModal('deactivateModal')">
                <span>Deactivate</span>
            </button>
        <?php else: ?>
            <button type="button" class="btn btn-primary" onclick="openModal('reactivateModal')">
                <span>Reactivate</span>
            </button>
        <?php endif; ?>
    </div>
</div>

<div class="profile-page-stack">
    <!-- Hero Summary Card -->
    <section class="card profile-hero-card" aria-labelledby="hrEmpHeroName">
        <div class="profile-hero-top">
            <div class="profile-hero-lead">
                <div class="profile-avatar-frame<?= $hasAvatar ? ' has-photo' : '' ?>">
                    <?php if ($hasAvatar): ?>
                        <img
                            src="<?= e((string)$resolvedAvatarUrl) ?>"
                            alt="Profile picture of <?= e($fullName) ?>"
                            class="avatar-img-cover"
                            width="88"
                            height="88"
                            decoding="async"
                        >
                    <?php else: ?>
                        <span aria-hidden="true"><?= e($initials) ?></span>
                    <?php endif; ?>
                </div>
                <div class="profile-hero-identity">
                    <h2 class="profile-hero-title" id="hrEmpHeroName"><?= e($fullName) ?></h2>
                    <p class="profile-hero-subtitle">
                        <?= e($employee['position_name'] ?? 'Unassigned Role') ?>
                        &middot;
                        <?= e($employee['department_name'] ?? 'Unassigned Dept') ?>
                    </p>
                    <div class="profile-hero-meta">
                        <?php if ($empStatus === 'Active'): ?>
                            <span class="badge badge-success">Active Employment</span>
                        <?php else: ?>
                            <span class="badge badge-danger">Inactive Employment</span>
                        <?php endif; ?>
                        <span class="badge badge-info"><?= e($empType) ?></span>
                        <span class="profile-governance-tag">ID: <?= e($employee['employee_no']) ?></span>
                    </div>
                </div>
            </div>

            <div class="profile-photo-actions">
                <button type="button" class="btn btn-secondary btn-sm" data-open-dialog="addMilestoneDialog">
                    <?= icon('plus', 15) ?>
                    <span>Record Milestone</span>
                </button>
                <a href="<?= url('/hr/correction-requests') ?>" class="btn btn-secondary btn-sm">
                    Correction Queue
                </a>
            </div>
        </div>

        <div class="profile-hero-strip">
            <div class="profile-strip-item">
                <span class="profile-strip-label">Work Email</span>
                <span class="profile-strip-value"><?= e($employee['email']) ?></span>
            </div>
            <div class="profile-strip-item">
                <span class="profile-strip-label">Date Hired</span>
                <span class="profile-strip-value"><?= e($dateHiredFormatted) ?></span>
            </div>
            <div class="profile-strip-item">
                <span class="profile-strip-label">Years of Service</span>
                <span class="profile-strip-value"><?= e($tenureData['formatted']) ?></span>
            </div>
            <div class="profile-strip-item">
                <span class="profile-strip-label">Date of Last Promotion</span>
                <span class="profile-strip-value"><?= e($lastPromotionDate) ?></span>
            </div>
        </div>
    </section>

    <!-- Personal & Contact vs Employment -->
    <div class="profile-two-col">
        <section class="card profile-section-card">
            <div class="card-body">
                <div class="profile-card-head">
                    <div>
                        <h2 class="profile-card-title">
                            <?= icon('user', 18) ?>
                            <span>Personal &amp; Contact Information</span>
                        </h2>
                        <p class="profile-card-desc">Identity, personal contact, and residential details.</p>
                    </div>
                </div>

                <dl class="profile-dl-grid">
                    <div class="profile-dl-item">
                        <dt class="profile-dl-label">Work Email</dt>
                        <dd class="profile-dl-value"><?= e($employee['email']) ?></dd>
                    </div>
                    <div class="profile-dl-item">
                        <dt class="profile-dl-label">Personal Email</dt>
                        <dd class="profile-dl-value <?= empty($employee['personal_email']) ? 'is-muted' : '' ?>">
                            <?= e(!empty($employee['personal_email']) ? $employee['personal_email'] : 'Not specified') ?>
                        </dd>
                    </div>
                    <div class="profile-dl-item">
                        <dt class="profile-dl-label">Mobile / Phone</dt>
                        <dd class="profile-dl-value <?= empty($employee['phone']) ? 'is-muted' : '' ?>">
                            <?= e($employee['phone'] ?: 'Not specified') ?>
                        </dd>
                    </div>
                    <div class="profile-dl-item">
                        <dt class="profile-dl-label">Gender</dt>
                        <dd class="profile-dl-value"><?= e($employee['gender'] ?? 'Not specified') ?></dd>
                    </div>
                    <div class="profile-dl-item">
                        <dt class="profile-dl-label">Civil Status</dt>
                        <dd class="profile-dl-value <?= empty($employee['civil_status']) ? 'is-muted' : '' ?>">
                            <?= e(!empty($employee['civil_status']) ? $employee['civil_status'] : 'Not specified') ?>
                        </dd>
                    </div>
                    <div class="profile-dl-item">
                        <dt class="profile-dl-label">Date of Birth</dt>
                        <dd class="profile-dl-value"><?= e($birthDateFormatted . $ageStr) ?></dd>
                    </div>
                    <div class="profile-dl-item is-full">
                        <dt class="profile-dl-label">Residential Address</dt>
                        <dd class="profile-dl-value <?= empty($employee['address']) ? 'is-muted' : '' ?>">
                            <?= nl2br(e($employee['address'] ?: 'No address specified on record.')) ?>
                        </dd>
                    </div>
                </dl>
            </div>
        </section>

        <section class="card profile-section-card">
            <div class="card-body">
                <div class="profile-card-head">
                    <div>
                        <h2 class="profile-card-title">
                            <?= icon('briefcase', 18) ?>
                            <span>Employment &amp; Access</span>
                        </h2>
                        <p class="profile-card-desc">Organizational placement, portal account, and service dates.</p>
                    </div>
                </div>

                <dl class="profile-dl-grid">
                    <div class="profile-dl-item">
                        <dt class="profile-dl-label">Department</dt>
                        <dd class="profile-dl-value"><?= e($employee['department_name'] ?? 'Unassigned') ?></dd>
                    </div>
                    <div class="profile-dl-item">
                        <dt class="profile-dl-label">Job Position</dt>
                        <dd class="profile-dl-value"><?= e($employee['position_name'] ?? 'Unassigned') ?></dd>
                    </div>
                    <div class="profile-dl-item">
                        <dt class="profile-dl-label">Employment Type</dt>
                        <dd class="profile-dl-value"><?= e($empType) ?></dd>
                    </div>
                    <div class="profile-dl-item">
                        <dt class="profile-dl-label">Employment Status</dt>
                        <dd class="profile-dl-value">
                            <?php if ($empStatus === 'Active'): ?>
                                <span class="badge badge-success">Active</span>
                            <?php else: ?>
                                <span class="badge badge-danger">Inactive</span>
                            <?php endif; ?>
                        </dd>
                    </div>
                    <div class="profile-dl-item">
                        <dt class="profile-dl-label">Date Hired</dt>
                        <dd class="profile-dl-value"><?= e($dateHiredFormatted) ?></dd>
                    </div>
                    <div class="profile-dl-item">
                        <dt class="profile-dl-label">Years of Service</dt>
                        <dd class="profile-dl-value"><?= e($tenureData['formatted']) ?></dd>
                    </div>
                    <div class="profile-dl-item is-full">
                        <dt class="profile-dl-label">System Portal Access</dt>
                        <dd class="profile-dl-value">
                            <?php if (!empty($employee['user_account_id'])): ?>
                                <span class="profile-governance-tag">
                                    <?= e($employee['email']) ?> (<?= e(strtoupper($employee['user_role'] ?? 'EMPLOYEE')) ?>)
                                </span>
                            <?php else: ?>
                                <a href="<?= url('/hr/users/create?employee_id=' . (int)$employee['id']) ?>" class="btn btn-primary btn-sm">
                                    + Provision Portal Account
                                </a>
                            <?php endif; ?>
                        </dd>
                    </div>
                    <div class="profile-dl-item is-full">
                        <dt class="profile-dl-label">Record Registered</dt>
                        <dd class="profile-dl-value is-muted"><?= e($employee['created_at'] ?? 'N/A') ?></dd>
                    </div>
                </dl>
            </div>
        </section>
    </div>

    <!-- Emergency Contact & Recent Correction Requests -->
    <div class="profile-two-col">
        <section class="card profile-section-card">
            <div class="card-body">
                <div class="profile-card-head">
                    <div>
                        <h2 class="profile-card-title">
                            <?= icon('phone', 18) ?>
                            <span>Emergency Contact</span>
                        </h2>
                        <p class="profile-card-desc">Employee-designated emergency contact on file.</p>
                    </div>
                </div>

                <?php if ($primaryEmergency === null): ?>
                    <div class="profile-empty-state">
                        <strong>No emergency contact recorded</strong>
                        <p>The employee has not added an emergency contact in self-service yet.</p>
                    </div>
                <?php else: ?>
                    <dl class="profile-dl-grid">
                        <div class="profile-dl-item">
                            <dt class="profile-dl-label">Contact Name</dt>
                            <dd class="profile-dl-value"><?= e($primaryEmergency['full_name']) ?></dd>
                        </div>
                        <div class="profile-dl-item">
                            <dt class="profile-dl-label">Relationship</dt>
                            <dd class="profile-dl-value"><?= e($primaryEmergency['relationship']) ?></dd>
                        </div>
                        <div class="profile-dl-item">
                            <dt class="profile-dl-label">Primary Number</dt>
                            <dd class="profile-dl-value"><?= e($primaryEmergency['contact_number']) ?></dd>
                        </div>
                        <div class="profile-dl-item">
                            <dt class="profile-dl-label">Alternative Number</dt>
                            <dd class="profile-dl-value <?= empty($primaryEmergency['alternative_contact_number']) ? 'is-muted' : '' ?>">
                                <?= e(!empty($primaryEmergency['alternative_contact_number']) ? $primaryEmergency['alternative_contact_number'] : 'None provided') ?>
                            </dd>
                        </div>
                        <div class="profile-dl-item is-full">
                            <dt class="profile-dl-label">Address</dt>
                            <dd class="profile-dl-value <?= empty($primaryEmergency['address']) ? 'is-muted' : '' ?>">
                                <?= e(!empty($primaryEmergency['address']) ? $primaryEmergency['address'] : 'Not specified') ?>
                            </dd>
                        </div>
                    </dl>
                <?php endif; ?>
            </div>
        </section>

        <section class="card profile-section-card">
            <div class="card-body">
                <div class="profile-card-head">
                    <div>
                        <h2 class="profile-card-title">
                            <?= icon('check-circle', 18) ?>
                            <span>Recent Correction Requests</span>
                        </h2>
                        <p class="profile-card-desc">Corrections submitted by this employee.</p>
                    </div>
                    <a href="<?= url('/hr/correction-requests') ?>" class="btn btn-secondary btn-sm">Open Queue</a>
                </div>

                <?php if (empty($correctionRequests)): ?>
                    <div class="profile-empty-state">
                        <strong>No correction requests recorded</strong>
                        <p>Profile correction submissions and their HR review outcomes will appear here.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Field</th>
                                    <th>Change</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($correctionRequests, 0, 5) as $req): ?>
                                    <?php
                                    $rStatus = (string)($req['status'] ?? ProfileCorrectionRequest::STATUS_PENDING);
                                    $rBadge = match ($rStatus) {
                                        ProfileCorrectionRequest::STATUS_APPROVED  => 'badge-success',
                                        ProfileCorrectionRequest::STATUS_REJECTED  => 'badge-danger',
                                        ProfileCorrectionRequest::STATUS_CANCELLED => 'badge-secondary',
                                        default                                    => 'badge-warning',
                                    };
                                    ?>
                                    <tr>
                                        <td class="font-semibold"><?= e($req['field_label'] ?? $req['field_name']) ?></td>
                                        <td>
                                            <div class="corr-diff-wrap">
                                                <span class="corr-val-old"><?= e($req['current_display'] ?? 'Unset') ?></span>
                                                <span aria-hidden="true">&rarr;</span>
                                                <span class="corr-val-new"><?= e($req['requested_display'] ?? $req['requested_value']) ?></span>
                                            </div>
                                        </td>
                                        <td><span class="badge <?= $rBadge ?>"><?= e($rStatus) ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <!-- Chronological Employment History -->
    <section class="card" id="employment-history" aria-labelledby="hrHistoryHeading">
        <div class="card-body">
            <div class="profile-card-head">
                <div>
                    <h2 class="profile-card-title" id="hrHistoryHeading">
                        <?= icon('clock', 18) ?>
                        <span>Employment History Timeline</span>
                    </h2>
                    <p class="profile-card-desc">Immutable career events including hiring, promotions, transfers, and type changes.</p>
                </div>
                <button type="button" class="btn btn-primary btn-sm" data-open-dialog="addMilestoneDialog">
                    <?= icon('plus', 15) ?>
                    <span>Add Employment Milestone</span>
                </button>
            </div>

            <?php if (empty($employmentHistory)): ?>
                <div class="profile-empty-state">
                    <strong>No employment history milestones recorded</strong>
                    <p>Record an initial hiring entry, promotion, department transfer, or contract change to start building the chronological timeline.</p>
                    <button type="button" class="btn btn-secondary btn-sm" data-open-dialog="addMilestoneDialog">Record First Milestone</button>
                </div>
            <?php else: ?>
                <ol class="profile-timeline">
                    <?php foreach ($employmentHistory as $milestone): ?>
                        <?php
                        $eventType = (string)($milestone['event_type'] ?? 'Other');
                        $dotClass = match ($eventType) {
                            'Promotion'      => 'is-promotion',
                            'Initial Hiring' => 'is-hiring',
                            default          => '',
                        };
                        $badgeTone = match ($eventType) {
                            'Promotion'           => 'badge-success',
                            'Initial Hiring'      => 'badge-info',
                            'Department Transfer' => 'badge-primary',
                            default               => 'badge-secondary',
                        };
                        $prevDept = $milestone['previous_department_name'] ?? null;
                        $newDept  = $milestone['new_department_name'] ?? null;
                        $prevPos  = $milestone['previous_position_name'] ?? null;
                        $newPos   = $milestone['new_position_name'] ?? null;
                        $recorderName = trim(
                            (string)($milestone['recorded_by_first_name'] ?? '')
                            . ' '
                            . (string)($milestone['recorded_by_last_name'] ?? '')
                        ) ?: ($milestone['recorded_by_username'] ?? 'HR System');
                        ?>
                        <li class="profile-timeline-item">
                            <div class="profile-timeline-rail" aria-hidden="true">
                                <span class="profile-timeline-dot <?= $dotClass ?>"></span>
                            </div>
                            <div class="profile-timeline-card">
                                <div class="profile-timeline-head">
                                    <span class="badge <?= $badgeTone ?>"><?= e($eventType) ?></span>
                                    <time class="profile-timeline-date" datetime="<?= e((string)($milestone['effective_date'] ?? '')) ?>">
                                        Effective <?= e(EmployeeTenureCalculator::formatDate($milestone['effective_date'] ?? null)) ?>
                                    </time>
                                </div>
                                <?php if ($newPos || $newDept): ?>
                                    <div class="profile-timeline-transition">
                                        <?php if ($prevPos && $newPos && $prevPos !== $newPos): ?>
                                            <span><?= e($prevPos) ?> &rarr; <?= e($newPos) ?></span>
                                        <?php elseif ($newPos): ?>
                                            <span><?= e($newPos) ?></span>
                                        <?php endif; ?>
                                        <?php if ($prevDept && $newDept && $prevDept !== $newDept): ?>
                                            <span>(<?= e($prevDept) ?> &rarr; <?= e($newDept) ?>)</span>
                                        <?php elseif ($newDept): ?>
                                            <span class="text-muted">&middot; <?= e($newDept) ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($milestone['employment_type'])): ?>
                                            <span class="profile-governance-tag"><?= e($milestone['employment_type']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($milestone['description'])): ?>
                                    <p class="profile-timeline-note"><?= e($milestone['description']) ?></p>
                                <?php endif; ?>
                                <div class="profile-timeline-meta">Recorded by <?= e($recorderName) ?></div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        </div>
    </section>
</div>

<!-- Native Dialog: Record HR Employment Milestone -->
<dialog
    id="addMilestoneDialog"
    class="profile-dialog"
    aria-labelledby="addMilestoneTitle"
    data-auto-open="<?= !empty($historyErrors) ? 'true' : 'false' ?>"
>
    <div class="profile-dialog-head">
        <h2 class="profile-dialog-title" id="addMilestoneTitle">Record Employment Milestone</h2>
        <button type="button" class="profile-dialog-close" data-close-dialog aria-label="Close milestone dialog">
            <?= icon('x', 18) ?>
        </button>
    </div>

    <form method="POST" action="<?= url('/hr/employees/' . (int)$employee['id'] . '/history') ?>">
        <?= csrf_field() ?>
        <div class="profile-dialog-body profile-form-stack">
            <div class="profile-form-grid">
                <div class="form-group mb-0">
                    <label for="milestoneEventType" class="form-label">Event Type <span class="required">*</span></label>
                    <?php $selEvent = (string)($historyOld['event_type'] ?? EmploymentHistory::EVENT_PROMOTION); ?>
                    <select id="milestoneEventType" name="event_type" class="form-select" required>
                        <?php foreach (($eventTypes ?? EmploymentHistory::VALID_EVENT_TYPES) as $ev): ?>
                            <option value="<?= e($ev) ?>" <?= $selEvent === $ev ? 'selected' : '' ?>><?= e($ev) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (!empty($historyErrors['event_type'])): ?>
                        <span class="field-error-text"><?= e($historyErrors['event_type']) ?></span>
                    <?php endif; ?>
                </div>

                <div class="form-group mb-0">
                    <label for="milestoneEffectiveDate" class="form-label">Effective Date <span class="required">*</span></label>
                    <input type="date" id="milestoneEffectiveDate" name="effective_date" class="form-control" value="<?= e((string)($historyOld['effective_date'] ?? date('Y-m-d'))) ?>" required>
                    <?php if (!empty($historyErrors['effective_date'])): ?>
                        <span class="field-error-text"><?= e($historyErrors['effective_date']) ?></span>
                    <?php endif; ?>
                </div>

                <div class="form-group mb-0">
                    <label for="milestoneDeptSelect" class="form-label">New / Target Department</label>
                    <?php $selMDept = (string)($historyOld['new_department_id'] ?? ($employee['department_id'] ?? '')); ?>
                    <select id="milestoneDeptSelect" name="new_department_id" class="form-select">
                        <option value="">Keep unchanged</option>
                        <?php foreach (($departments ?? []) as $d): ?>
                            <option value="<?= (int)$d['id'] ?>" <?= $selMDept === (string)$d['id'] ? 'selected' : '' ?>>
                                <?= e($d['department_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group mb-0">
                    <label for="milestonePosSelect" class="form-label">New / Target Position</label>
                    <?php $selMPos = (string)($historyOld['new_position_id'] ?? ($employee['position_id'] ?? '')); ?>
                    <select id="milestonePosSelect" name="new_position_id" class="form-select">
                        <option value="">Keep unchanged</option>
                        <?php foreach (($positions ?? []) as $p): ?>
                            <option value="<?= (int)$p['id'] ?>" data-department-id="<?= (int)$p['department_id'] ?>" <?= $selMPos === (string)$p['id'] ? 'selected' : '' ?>>
                                <?= e($p['position_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (!empty($historyErrors['new_position_id'])): ?>
                        <span class="field-error-text"><?= e($historyErrors['new_position_id']) ?></span>
                    <?php endif; ?>
                </div>

                <div class="form-group is-full mb-0">
                    <label for="milestoneEmpType" class="form-label">Employment Type (Optional)</label>
                    <?php $selMType = (string)($historyOld['employment_type'] ?? ($employee['employment_type'] ?? 'Regular')); ?>
                    <select id="milestoneEmpType" name="employment_type" class="form-select">
                        <?php foreach (($employmentTypes ?? ['Regular', 'Probationary', 'Contractual', 'Project-Based', 'Part-Time']) as $et): ?>
                            <option value="<?= e($et) ?>" <?= $selMType === $et ? 'selected' : '' ?>><?= e($et) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group is-full mb-0">
                    <label for="milestoneNotes" class="form-label">Notes / Official Summary <span class="required">*</span></label>
                    <textarea id="milestoneNotes" name="description" rows="3" class="form-control" maxlength="1000" required placeholder="Document reason, approved memo reference, or transition details"><?= e((string)($historyOld['description'] ?? '')) ?></textarea>
                    <?php if (!empty($historyErrors['description'])): ?>
                        <span class="field-error-text"><?= e($historyErrors['description']) ?></span>
                    <?php endif; ?>
                </div>

                <div class="form-group is-full mb-0">
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="sync_current_assignment" value="1" checked>
                        <span>Also update the employee's current Department, Position, and Employment Type on their live profile</span>
                    </label>
                </div>
            </div>
        </div>

        <div class="profile-dialog-foot">
            <button type="button" class="btn btn-secondary" data-close-dialog>Cancel</button>
            <button type="submit" class="btn btn-primary">Save Milestone</button>
        </div>
    </form>
</dialog>

<!-- Deactivation Confirmation Modal -->
<?php if ($employee['employment_status'] === 'Active'): ?>
<div id="deactivateModal" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="deactivateTitle">
    <div class="modal-card">
        <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem;">
            <div style="width: 40px; height: 40px; border-radius: 50%; background: var(--color-danger-bg, #FEE2E2); color: var(--color-danger, #DC2626); display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">⚠️</div>
            <div>
                <h3 id="deactivateTitle" style="margin: 0; font-size: 1.15rem; color: var(--color-text-primary);">Confirm Deactivation</h3>
                <div style="font-size: 0.8125rem; color: var(--color-text-muted);">Soft deactivation preserves historical leave logs.</div>
            </div>
        </div>

        <p style="font-size: 0.875rem; color: var(--color-text-secondary); line-height: 1.5; margin-bottom: 1.5rem;">
            Are you sure you want to change the status of <strong><?= e($fullName) ?></strong> (<code><?= e($employee['employee_no']) ?></code>) to <strong>Inactive</strong>?
            <br><br>
            <span style="font-size: 0.8125rem; color: var(--color-text-muted);">
                Historical leave records, balance allocations, and activity audit events will remain intact in the database.
            </span>
        </p>

        <form method="POST" action="<?= url('/hr/employees/' . $employee['id'] . '/deactivate') ?>" style="margin: 0;">
            <?= csrf_field() ?>
            <?php
            $stepUpFieldId = 'deactivate_current_password';
            $stepUpHelp = 'Deactivation is a sensitive action. Confirm your own HR account password to continue.';
            $stepUpOptional = false;
            require __DIR__ . '/../partials/step_up_field.php';
            ?>
            <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('deactivateModal')">Cancel</button>
                <button type="submit" class="btn btn-danger" style="font-weight: 600;">Confirm Deactivation</button>
            </div>
        </form>
    </div>
</div>
<?php else: ?>
<!-- Reactivation Confirmation Modal -->
<div id="reactivateModal" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="reactivateTitle">
    <div class="modal-card">
        <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem;">
            <div style="width: 40px; height: 40px; border-radius: 50%; background: var(--color-success-bg, #DCFCE7); color: var(--color-success, #16A34A); display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">✅</div>
            <div>
                <h3 id="reactivateTitle" style="margin: 0; font-size: 1.15rem; color: var(--color-text-primary);">Confirm Reactivation</h3>
                <div style="font-size: 0.8125rem; color: var(--color-text-muted);">Restores employment status and the linked login account.</div>
            </div>
        </div>

        <p style="font-size: 0.875rem; color: var(--color-text-secondary); line-height: 1.5; margin-bottom: 1.5rem;">
            Reactivate <strong><?= e($fullName) ?></strong> (<code><?= e($employee['employee_no']) ?></code>)?
            <br><br>
            <span style="font-size: 0.8125rem; color: var(--color-text-muted);">
                Their employment status returns to Active and their login account is re-enabled. Signing in still requires email and password plus MFA.
            </span>
        </p>

        <form method="POST" action="<?= url('/hr/employees/' . $employee['id'] . '/reactivate') ?>" style="margin: 0;">
            <?= csrf_field() ?>
            <?php
            $stepUpFieldId = 'reactivate_current_password';
            $stepUpHelp = 'Reactivation restores system access. Confirm your own HR account password to continue.';
            $stepUpOptional = false;
            require __DIR__ . '/../partials/step_up_field.php';
            ?>
            <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('reactivateModal')">Cancel</button>
                <button type="submit" class="btn btn-primary" style="font-weight: 600;">Confirm Reactivation</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>
