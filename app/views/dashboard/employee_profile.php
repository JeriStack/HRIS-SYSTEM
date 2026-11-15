<?php

/**
 * Employee Self-Service Profile Page
 * Complete profile picture management, verified identity records, self-service contact &
 * emergency contact management, dynamic tenure, employment history timeline, and HR correction requests.
 */

use App\Models\ProfileCorrectionRequest;
use App\Services\EmployeeTenureCalculator;

$emp = is_array($profile ?? null) ? $profile : [];
$fullName = trim(implode(' ', array_filter([
    $emp['first_name'] ?? '',
    $emp['middle_name'] ?? '',
    $emp['last_name'] ?? '',
]))) ?: ($user['name'] ?? 'Employee');

$shortName = trim(($emp['first_name'] ?? '') . ' ' . ($emp['last_name'] ?? '')) ?: $fullName;
$initials = strtoupper(
    substr((string)($emp['first_name'] ?? $shortName), 0, 1)
    . substr((string)($emp['last_name'] ?? ''), 0, 1)
) ?: 'E';

$empStatus = (string)($emp['employment_status'] ?? 'Active');
$empType   = (string)($emp['employment_type'] ?? 'Regular');
$hasAvatar = !empty($avatarUrl);
$maxMbText = number_format(((int)($avatarMaxBytes ?? 5 * 1024 * 1024)) / (1024 * 1024), 1);

$tenureData = is_array($tenure ?? null) ? $tenure : EmployeeTenureCalculator::calculate($emp['date_hired'] ?? null);
$dateHiredFormatted = EmployeeTenureCalculator::formatDate($emp['date_hired'] ?? null);
$birthDateFormatted = EmployeeTenureCalculator::formatDate($emp['birth_date'] ?? null);
$lastPromotionDate  = is_array($lastPromotion ?? null) && !empty($lastPromotion['effective_date'])
    ? EmployeeTenureCalculator::formatDate((string)$lastPromotion['effective_date'])
    : 'No promotion recorded';

$primaryEmergency = !empty($emergencyContacts[0]) && is_array($emergencyContacts[0]) ? $emergencyContacts[0] : null;
$formErrors = is_array($formErrors ?? null) ? $formErrors : [];
$formOld    = is_array($formOld ?? null) ? $formOld : [];
$activeForm = (string)($activeForm ?? '');

$valContact = static function (string $key, mixed $default = '') use ($activeForm, $formOld, $emp): string {
    if ($activeForm === 'contact' && array_key_exists($key, $formOld)) {
        return (string)$formOld[$key];
    }
    return (string)($emp[$key] ?? $default);
};

$valEmergency = static function (string $key, mixed $default = '') use ($activeForm, $formOld, $primaryEmergency): string {
    if ($activeForm === 'emergency' && array_key_exists($key, $formOld)) {
        return (string)$formOld[$key];
    }
    return (string)($primaryEmergency[$key] ?? $default);
};

$currentCorrectionSnapshot = [
    'first_name'      => $emp['first_name'] ?? '',
    'middle_name'     => $emp['middle_name'] ?? '',
    'last_name'       => $emp['last_name'] ?? '',
    'birth_date'      => $emp['birth_date'] ?? '',
    'gender'          => $emp['gender'] ?? '',
    'civil_status'    => $emp['civil_status'] ?? '',
    'email'           => $emp['email'] ?? '',
    'department_id'   => $emp['department_id'] ?? '',
    'position_id'     => $emp['position_id'] ?? '',
    'employment_type' => $emp['employment_type'] ?? 'Regular',
    'date_hired'      => $emp['date_hired'] ?? '',
];

$selectedCorrField = ($activeForm === 'correction' && !empty($formOld['field_name']))
    ? (string)$formOld['field_name']
    : 'civil_status';
$selectedCorrOldVal = ($activeForm === 'correction') ? (string)($formOld['requested_value'] ?? '') : '';
$selectedCorrReason = ($activeForm === 'correction') ? (string)($formOld['reason'] ?? '') : '';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">My Profile</h1>
        <p class="page-subtitle">Manage your profile photo, personal contact details, emergency contact, and career records.</p>
    </div>
    <div class="page-actions">
        <button type="button" class="btn btn-secondary" data-open-dialog="correctionRequestModal">
            <?= icon('file-text', 16) ?>
            <span>Request Correction</span>
        </button>
        <a href="<?= url('/employee/dashboard') ?>" class="btn btn-secondary">
            <?= icon('arrow-left', 16) ?>
            <span>Back to Dashboard</span>
        </a>
    </div>
</div>

<div class="profile-page-stack">
    <!-- 1. Profile Header & Photo Management -->
    <section class="card profile-hero-card" id="profile-photo" aria-labelledby="profileHeroName">
        <div class="profile-hero-top">
            <div class="profile-hero-lead">
                <div class="profile-avatar-frame<?= $hasAvatar ? ' has-photo' : '' ?>">
                    <?php if ($hasAvatar): ?>
                        <img
                            src="<?= e((string)$avatarUrl) ?>"
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
                    <h2 class="profile-hero-title" id="profileHeroName"><?= e($fullName) ?></h2>
                    <p class="profile-hero-subtitle">
                        <span class="profile-hero-role"><?= e($emp['position_name'] ?? 'Position Unassigned') ?></span>
                        <span class="profile-hero-sep" aria-hidden="true">&middot;</span>
                        <span><?= e($emp['department_name'] ?? 'Department Unassigned') ?></span>
                    </p>
                    <div class="profile-hero-meta">
                        <span class="badge badge-dot <?= $empStatus === 'Active' ? 'badge-success' : 'badge-danger' ?>">
                            <?= e($empStatus) ?>
                        </span>
                        <span class="badge badge-info"><?= e($empType) ?></span>
                    </div>
                </div>
            </div>

            <div class="profile-photo-panel">
                <div class="profile-photo-actions">
                    <button type="button" class="btn btn-primary btn-sm" data-open-dialog="avatarCropModal">
                        <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                        <span><?= $hasAvatar ? 'Change Photo' : 'Upload Photo' ?></span>
                    </button>
                    <?php if ($hasAvatar): ?>
                        <form method="POST" action="<?= url('/employee/profile/avatar/delete') ?>" onsubmit="return confirm('Remove your current profile picture and restore your default initials avatar?');">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-outline-danger btn-sm">
                                <svg aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                <span>Remove Photo</span>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
                <p class="profile-photo-hint">JPEG, PNG or WebP &middot; Max <?= e($maxMbText) ?> MB</p>
            </div>
        </div>

        <div class="profile-hero-strip" aria-label="Employment Highlights">
            <div class="profile-strip-item">
                <span class="profile-strip-label">Employee ID</span>
                <span class="profile-strip-value is-mono"><?= e($emp['employee_no'] ?? 'Not assigned') ?></span>
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
                <span class="profile-strip-value<?= (!is_array($lastPromotion ?? null) || empty($lastPromotion['effective_date'])) ? ' is-muted' : '' ?>">
                    <?= e($lastPromotionDate) ?>
                </span>
            </div>
        </div>
    </section>

    <!-- 2 & 3. Personal Information & Contact Information -->
    <div class="profile-two-col">
        <!-- Personal Information (HR Verified) -->
        <section class="card profile-section-card" id="personal-info" aria-labelledby="personalInfoHeading">
            <div class="card-body">
                <div class="profile-card-head">
                    <div class="profile-card-head-main">
                        <h2 class="profile-card-title" id="personalInfoHeading">
                            <?= icon('user', 18) ?>
                            <span>Personal Information</span>
                        </h2>
                        <p class="profile-card-desc">Official employee civil and identity records maintained with HR.</p>
                    </div>
                    <span class="profile-governance-tag">HR Verified</span>
                </div>

                <dl class="profile-dl-grid">
                    <div class="profile-dl-item is-full">
                        <dt class="profile-dl-label">Full Legal Name</dt>
                        <dd class="profile-dl-value"><?= e($fullName) ?></dd>
                    </div>
                    <div class="profile-dl-item">
                        <dt class="profile-dl-label">Employee ID</dt>
                        <dd class="profile-dl-value is-mono"><?= e($emp['employee_no'] ?? 'Not assigned') ?></dd>
                    </div>
                    <div class="profile-dl-item">
                        <dt class="profile-dl-label">Date of Birth</dt>
                        <dd class="profile-dl-value"><?= e($birthDateFormatted) ?></dd>
                    </div>
                    <div class="profile-dl-item">
                        <dt class="profile-dl-label">Gender</dt>
                        <dd class="profile-dl-value"><?= e($emp['gender'] ?? 'Not specified') ?></dd>
                    </div>
                    <div class="profile-dl-item">
                        <dt class="profile-dl-label">Civil Status</dt>
                        <dd class="profile-dl-value <?= empty($emp['civil_status']) ? 'is-muted' : '' ?>">
                            <?= e(!empty($emp['civil_status']) ? (string)$emp['civil_status'] : 'Not specified') ?>
                        </dd>
                    </div>
                </dl>

                <div class="profile-card-footer">
                    <span class="profile-footer-note">
                        <?= icon('lock', 14, 'profile-footer-note-icon') ?>
                        <span>Verified identity records require HR review to change.</span>
                    </span>
                    <button type="button" class="btn btn-secondary btn-sm" data-open-dialog="correctionRequestModal" data-correction-field="civil_status">
                        Request Correction
                    </button>
                </div>
            </div>
        </section>

        <!-- Contact Information (Self-Service for Personal Email, Mobile, Address; Read-only Work Email) -->
        <section class="card profile-section-card" id="contact-info" aria-labelledby="contactInfoHeading">
            <div class="card-body">
                <div class="profile-card-head">
                    <div class="profile-card-head-main">
                        <h2 class="profile-card-title" id="contactInfoHeading">
                            <?= icon('mail', 18) ?>
                            <span>Contact Information</span>
                        </h2>
                        <p class="profile-card-desc">Update your personal contact details.</p>
                    </div>
                    <span class="profile-governance-tag is-self">Self-Service</span>
                </div>

                <form method="POST" action="<?= url('/employee/profile/contact') ?>" class="profile-form-stack" novalidate>
                    <?= csrf_field() ?>

                    <div class="form-group mb-0">
                        <span class="form-label profile-label-row">
                            <span>Work Email Address</span>
                            <span class="profile-verified-pill">HR Controlled</span>
                        </span>
                        <div class="profile-readonly-box">
                            <span class="profile-readonly-value">
                                <?= icon('lock', 14, 'profile-readonly-icon') ?>
                                <span><?= e($emp['email'] ?? 'Not assigned') ?></span>
                            </span>
                            <button type="button" class="profile-inline-action" data-open-dialog="correctionRequestModal" data-correction-field="email">
                                Request change
                            </button>
                        </div>
                    </div>

                    <div class="profile-form-grid">
                        <?php
                        $hasPersonalEmailError = $activeForm === 'contact' && isset($formErrors['personal_email']);
                        $hasPhoneError         = $activeForm === 'contact' && isset($formErrors['phone']);
                        $hasAddressError       = $activeForm === 'contact' && isset($formErrors['address']);
                        ?>
                        <div class="form-group mb-0">
                            <label for="personalEmailInput" class="form-label">Personal Email</label>
                            <input
                                type="email"
                                id="personalEmailInput"
                                name="personal_email"
                                class="form-control<?= $hasPersonalEmailError ? ' is-invalid' : '' ?>"
                                value="<?= e($valContact('personal_email')) ?>"
                                autocomplete="email"
                                autocorrect="off"
                                autocapitalize="none"
                                spellcheck="false"
                                inputmode="email"
                                maxlength="100"
                                placeholder="name@example.com"
                                <?= $hasPersonalEmailError ? 'aria-invalid="true" aria-describedby="personalEmailError"' : '' ?>
                            >
                            <?php if ($hasPersonalEmailError): ?>
                                <span id="personalEmailError" class="field-error-text" role="alert"><?= e($formErrors['personal_email']) ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="form-group mb-0">
                            <label for="contactPhoneInput" class="form-label">Mobile / Contact Number</label>
                            <input
                                type="tel"
                                id="contactPhoneInput"
                                name="phone"
                                class="form-control<?= $hasPhoneError ? ' is-invalid' : '' ?>"
                                value="<?= e($valContact('phone')) ?>"
                                autocomplete="tel"
                                inputmode="tel"
                                maxlength="25"
                                placeholder="+63 917 000 0000"
                                <?= $hasPhoneError ? 'aria-invalid="true" aria-describedby="contactPhoneError"' : '' ?>
                            >
                            <?php if ($hasPhoneError): ?>
                                <span id="contactPhoneError" class="field-error-text" role="alert"><?= e($formErrors['phone']) ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="form-group is-full mb-0">
                            <label for="contactAddressInput" class="form-label">Residential Address</label>
                            <textarea
                                id="contactAddressInput"
                                name="address"
                                rows="2"
                                class="form-control<?= $hasAddressError ? ' is-invalid' : '' ?>"
                                autocomplete="street-address"
                                maxlength="500"
                                placeholder="House/Unit, Street, Barangay, City/Province"
                                <?= $hasAddressError ? 'aria-invalid="true" aria-describedby="contactAddressError"' : '' ?>
                            ><?= e($valContact('address')) ?></textarea>
                            <?php if ($hasAddressError): ?>
                                <span id="contactAddressError" class="field-error-text" role="alert"><?= e($formErrors['address']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="profile-form-footer">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <?= icon('check', 16) ?>
                            <span>Save Contact Details</span>
                        </button>
                    </div>
                </form>
            </div>
        </section>
    </div>

    <!-- 4 & 5. Emergency Contact & Employment Organization Details -->
    <div class="profile-two-col">
        <!-- Emergency Contact Information -->
        <section class="card profile-section-card" id="emergency-contact" aria-labelledby="emergencyHeading">
            <div class="card-body">
                <div class="profile-card-head">
                    <div>
                        <h2 class="profile-card-title" id="emergencyHeading">
                            <?= icon('phone', 18) ?>
                            <span>Emergency Contact Information</span>
                        </h2>
                        <p class="profile-card-desc">Primary person HR should reach in case of workplace emergency.</p>
                    </div>
                    <span class="profile-governance-tag is-self">Self-Service</span>
                </div>

                <?php if ($primaryEmergency === null && $activeForm !== 'emergency'): ?>
                    <div class="profile-empty-state mb-4">
                        <strong>No emergency contact on file yet</strong>
                        <p>Add a trusted family member or guardian contact below so HR can reach someone promptly when needed.</p>
                    </div>
                <?php elseif ($primaryEmergency !== null): ?>
                    <dl class="profile-dl-grid mb-4">
                        <div class="profile-dl-item">
                            <dt class="profile-dl-label">Primary Contact</dt>
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

                <form method="POST" action="<?= url('/employee/profile/emergency-contact') ?>" class="profile-form-stack" novalidate>
                    <?= csrf_field() ?>

                    <div class="profile-form-grid">
                        <div class="form-group mb-0">
                            <label for="emergencyFullName" class="form-label">Full Name <span class="required">*</span></label>
                            <input
                                type="text"
                                id="emergencyFullName"
                                name="full_name"
                                class="form-control<?= ($activeForm === 'emergency' && isset($formErrors['full_name'])) ? ' is-invalid' : '' ?>"
                                value="<?= e($valEmergency('full_name')) ?>"
                                maxlength="120"
                                required
                                placeholder="e.g. Maria Clara Santos"
                            >
                            <?php if ($activeForm === 'emergency' && isset($formErrors['full_name'])): ?>
                                <span class="field-error-text" role="alert"><?= e($formErrors['full_name']) ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="form-group mb-0">
                            <label for="emergencyRelationship" class="form-label">Relationship <span class="required">*</span></label>
                            <?php $currentRel = $valEmergency('relationship'); ?>
                            <select
                                id="emergencyRelationship"
                                name="relationship"
                                class="form-select<?= ($activeForm === 'emergency' && isset($formErrors['relationship'])) ? ' is-invalid' : '' ?>"
                                required
                            >
                                <option value="">-- Select Relationship --</option>
                                <?php foreach (($relationships ?? []) as $rel): ?>
                                    <option value="<?= e($rel) ?>" <?= $currentRel === $rel ? 'selected' : '' ?>><?= e($rel) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($activeForm === 'emergency' && isset($formErrors['relationship'])): ?>
                                <span class="field-error-text" role="alert"><?= e($formErrors['relationship']) ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="form-group mb-0">
                            <label for="emergencyPhone" class="form-label">Primary Contact Number <span class="required">*</span></label>
                            <input
                                type="tel"
                                id="emergencyPhone"
                                name="contact_number"
                                class="form-control<?= ($activeForm === 'emergency' && isset($formErrors['contact_number'])) ? ' is-invalid' : '' ?>"
                                value="<?= e($valEmergency('contact_number')) ?>"
                                maxlength="25"
                                required
                                placeholder="+63 918 555 0199"
                            >
                            <?php if ($activeForm === 'emergency' && isset($formErrors['contact_number'])): ?>
                                <span class="field-error-text" role="alert"><?= e($formErrors['contact_number']) ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="form-group mb-0">
                            <label for="emergencyAltPhone" class="form-label">Alternative Number</label>
                            <input
                                type="tel"
                                id="emergencyAltPhone"
                                name="alternative_contact_number"
                                class="form-control<?= ($activeForm === 'emergency' && isset($formErrors['alternative_contact_number'])) ? ' is-invalid' : '' ?>"
                                value="<?= e($valEmergency('alternative_contact_number')) ?>"
                                maxlength="25"
                                placeholder="Optional secondary number"
                            >
                            <?php if ($activeForm === 'emergency' && isset($formErrors['alternative_contact_number'])): ?>
                                <span class="field-error-text" role="alert"><?= e($formErrors['alternative_contact_number']) ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="form-group is-full mb-0">
                            <label for="emergencyAddress" class="form-label">Contact Address</label>
                            <input
                                type="text"
                                id="emergencyAddress"
                                name="address"
                                class="form-control<?= ($activeForm === 'emergency' && isset($formErrors['address'])) ? ' is-invalid' : '' ?>"
                                value="<?= e($valEmergency('address')) ?>"
                                maxlength="500"
                                placeholder="Optional residential address of emergency contact"
                            >
                            <?php if ($activeForm === 'emergency' && isset($formErrors['address'])): ?>
                                <span class="field-error-text" role="alert"><?= e($formErrors['address']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="profile-form-footer">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <?= icon('check', 16) ?>
                            <span><?= $primaryEmergency ? 'Update Emergency Contact' : 'Add Emergency Contact' ?></span>
                        </button>
                    </div>
                </form>

                <?php if ($primaryEmergency !== null): ?>
                    <form method="POST" action="<?= url('/employee/profile/emergency-contact/' . (int)$primaryEmergency['id'] . '/delete') ?>" class="mt-1" onsubmit="return confirm('Delete your saved emergency contact record?');">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-outline-danger btn-sm">Remove Emergency Contact</button>
                    </form>
                <?php endif; ?>
            </div>
        </section>

        <!-- Employment & Organization Details (HR Verified) -->
        <section class="card profile-section-card" id="employment-info" aria-labelledby="employmentInfoHeading">
            <div class="card-body">
                <div class="profile-card-head">
                    <div class="profile-card-head-main">
                        <h2 class="profile-card-title" id="employmentInfoHeading">
                            <?= icon('briefcase', 18) ?>
                            <span>Employment &amp; Organization</span>
                        </h2>
                        <p class="profile-card-desc">Official department assignment, role classification, and calculated service tenure.</p>
                    </div>
                    <span class="profile-governance-tag">HR Verified</span>
                </div>

                <dl class="profile-dl-grid">
                    <div class="profile-dl-item">
                        <dt class="profile-dl-label">Department</dt>
                        <dd class="profile-dl-value"><?= e($emp['department_name'] ?? 'Unassigned') ?></dd>
                    </div>
                    <div class="profile-dl-item">
                        <dt class="profile-dl-label">Current Position</dt>
                        <dd class="profile-dl-value"><?= e($emp['position_name'] ?? 'Unassigned') ?></dd>
                    </div>
                    <div class="profile-dl-item">
                        <dt class="profile-dl-label">Employment Type</dt>
                        <dd class="profile-dl-value"><?= e($empType) ?></dd>
                    </div>
                    <div class="profile-dl-item">
                        <dt class="profile-dl-label">Employment Status</dt>
                        <dd class="profile-dl-value">
                            <span class="badge badge-dot <?= $empStatus === 'Active' ? 'badge-success' : 'badge-danger' ?>">
                                <?= e($empStatus) ?>
                            </span>
                        </dd>
                    </div>
                    <div class="profile-dl-item">
                        <dt class="profile-dl-label">Date Hired</dt>
                        <dd class="profile-dl-value"><?= e($dateHiredFormatted) ?></dd>
                    </div>
                    <div class="profile-dl-item">
                        <dt class="profile-dl-label">Years of Service</dt>
                        <dd class="profile-dl-value">
                            <?= e($tenureData['formatted']) ?>
                            <?php if (!empty($tenureData['is_future'])): ?>
                                <span class="badge badge-info">Starts in future</span>
                            <?php endif; ?>
                        </dd>
                    </div>
                    <div class="profile-dl-item is-full">
                        <dt class="profile-dl-label">Date of Last Promotion</dt>
                        <dd class="profile-dl-value <?= empty($lastPromotion) ? 'is-muted' : '' ?>">
                            <?php if (is_array($lastPromotion ?? null) && !empty($lastPromotion['effective_date'])): ?>
                                <?= e(EmployeeTenureCalculator::formatDate((string)$lastPromotion['effective_date'])) ?>
                                <?php if (!empty($lastPromotion['new_position_name'])): ?>
                                    — <?= e((string)$lastPromotion['new_position_name']) ?>
                                <?php endif; ?>
                            <?php else: ?>
                                No promotion recorded
                            <?php endif; ?>
                        </dd>
                    </div>
                </dl>

                <div class="profile-card-footer">
                    <span class="profile-footer-note">
                        <?= icon('lock', 14, 'profile-footer-note-icon') ?>
                        <span>Organizational assignments are managed by HR.</span>
                    </span>
                    <button type="button" class="btn btn-secondary btn-sm" data-open-dialog="correctionRequestModal" data-correction-field="department_id">
                        Request Correction
                    </button>
                </div>
            </div>
        </section>
    </div>

    <!-- 6. Employment History Timeline -->
    <section class="card" id="employment-history" aria-labelledby="historyHeading">
        <div class="card-body">
            <div class="profile-card-head">
                <div>
                    <h2 class="profile-card-title" id="historyHeading">
                        <?= icon('clock', 18) ?>
                        <span>Employment History Timeline</span>
                    </h2>
                    <p class="profile-card-desc">Official chronological career milestones recorded by Human Resources.</p>
                </div>
                <span class="profile-governance-tag">Chronological Official Record</span>
            </div>

            <?php if (empty($employmentHistory)): ?>
                <div class="profile-empty-state">
                    <strong>No career transitions recorded yet</strong>
                    <p>When HR records your onboarding milestone, promotions, department transfers, or employment type updates, they will appear here chronologically.</p>
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

                                <div class="profile-timeline-meta">
                                    Recorded by <?= e($recorderName) ?>
                                </div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        </div>
    </section>

    <!-- 7. Profile Correction Requests -->
    <section class="card" id="correction-requests" aria-labelledby="correctionsHeading">
        <div class="card-body">
            <div class="profile-card-head">
                <div>
                    <h2 class="profile-card-title" id="correctionsHeading">
                        <?= icon('check-circle', 18) ?>
                        <span>Profile Correction Requests</span>
                    </h2>
                    <p class="profile-card-desc">Request HR verification to update official personal, email, or organizational records.</p>
                </div>
                <button type="button" class="btn btn-primary btn-sm" data-open-dialog="correctionRequestModal">
                    <?= icon('plus', 16) ?>
                    <span>New Correction Request</span>
                </button>
            </div>

            <?php if (empty($correctionRequests)): ?>
                <div class="profile-empty-state">
                    <strong>No profile correction requests submitted</strong>
                    <p>All HR-verified personal and organizational details look accurate? If anything needs updating, submit a correction request for HR approval.</p>
                    <button type="button" class="btn btn-secondary btn-sm" data-open-dialog="correctionRequestModal">
                        Submit Correction Request
                    </button>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Field</th>
                                <th>Requested Change</th>
                                <th>Reason</th>
                                <th>Status</th>
                                <th>Submitted</th>
                                <th>HR Decision</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($correctionRequests as $req): ?>
                                <?php
                                $rStatus = (string)($req['status'] ?? ProfileCorrectionRequest::STATUS_PENDING);
                                $rBadge = match ($rStatus) {
                                    ProfileCorrectionRequest::STATUS_APPROVED  => 'badge-success',
                                    ProfileCorrectionRequest::STATUS_REJECTED  => 'badge-danger',
                                    ProfileCorrectionRequest::STATUS_CANCELLED => 'badge-secondary',
                                    default                                    => 'badge-warning',
                                };
                                $revName = trim(
                                    (string)($req['reviewer_first_name'] ?? '')
                                    . ' '
                                    . (string)($req['reviewer_last_name'] ?? '')
                                ) ?: ($req['reviewer_username'] ?? '');
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
                                    <td><?= e($req['reason'] ?? '') ?></td>
                                    <td><span class="badge <?= $rBadge ?>"><?= e($rStatus) ?></span></td>
                                    <td class="whitespace-nowrap">
                                        <?= e(!empty($req['created_at']) ? date('M j, Y', strtotime((string)$req['created_at'])) : 'N/A') ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($req['decision_reason'])): ?>
                                            <span><?= e($req['decision_reason']) ?></span>
                                        <?php elseif ($rStatus !== ProfileCorrectionRequest::STATUS_PENDING && $revName !== ''): ?>
                                            <span class="text-muted">Reviewed by <?= e($revName) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">Awaiting HR review</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($rStatus === ProfileCorrectionRequest::STATUS_PENDING): ?>
                                            <form method="POST" action="<?= url('/employee/profile/correction-requests/' . (int)$req['id'] . '/cancel') ?>" onsubmit="return confirm('Cancel this pending profile correction request?');">
                                                <?= csrf_field() ?>
                                                <button type="submit" class="btn btn-secondary btn-sm">Cancel</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-muted text-xs">Closed</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>

<!-- Native Accessible Dialog: Profile Picture Upload & Interactive Square Cropper -->
<dialog
    id="avatarCropModal"
    class="profile-dialog"
    aria-labelledby="avatarCropModalTitle"
    data-auto-open="<?= $activeForm === 'avatar' ? 'true' : 'false' ?>"
>
    <div class="profile-dialog-head">
        <h2 class="profile-dialog-title" id="avatarCropModalTitle">Upload &amp; Crop Profile Picture</h2>
        <button type="button" class="profile-dialog-close" data-close-dialog aria-label="Close photo dialog">
            <?= icon('x', 18) ?>
        </button>
    </div>

    <form
        id="avatarUploadForm"
        method="POST"
        action="<?= url('/employee/profile/avatar') ?>"
        enctype="multipart/form-data"
        data-max-bytes="<?= (int)($avatarMaxBytes ?? 5 * 1024 * 1024) ?>"
    >
        <?= csrf_field() ?>
        <input type="hidden" id="cropXInput" name="crop_x" value="">
        <input type="hidden" id="cropYInput" name="crop_y" value="">
        <input type="hidden" id="cropSizeInput" name="crop_size" value="">

        <div class="profile-dialog-body">
            <div class="form-group mb-0">
                <label for="avatarFileInput" class="form-label">Select Image File</label>
                <input
                    type="file"
                    id="avatarFileInput"
                    name="profile_picture"
                    class="form-control"
                    accept="image/jpeg,image/png,image/webp"
                    aria-describedby="avatarUploadHelp"
                    required
                >
                <span id="avatarUploadHelp" class="form-hint-text">
                    Accepted formats: JPEG, PNG, or WebP. Maximum file size: <?= e($maxMbText) ?> MB (minimum 64&times;64 px).
                </span>
                <div id="avatarClientError" class="field-error-text" role="alert" hidden></div>
            </div>

            <div id="avatarCropWorkspace" class="cropper-workspace" hidden>
                <div class="cropper-stage-box">
                    <canvas id="avatarCropCanvas" class="cropper-canvas" width="320" height="320" aria-label="Interactive square photo crop area"></canvas>
                    <div class="cropper-range-row">
                        <label for="avatarZoomInput">Zoom</label>
                        <input type="range" id="avatarZoomInput" min="1" max="3" step="0.05" value="1">
                        <button type="button" id="avatarResetCropBtn" class="btn btn-secondary btn-sm">Reset</button>
                    </div>
                    <span class="form-hint-text">Drag the image to reposition within the circle guide.</span>
                </div>

                <div class="cropper-preview-col">
                    <span class="text-xs font-semibold text-muted">Avatar Preview</span>
                    <div class="cropper-preview-ring">
                        <canvas id="avatarPreviewCanvas" width="96" height="96" aria-label="Circular avatar preview"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="profile-dialog-foot">
            <button type="button" class="btn btn-secondary" data-close-dialog>Cancel</button>
            <button type="submit" id="avatarSaveBtn" class="btn btn-primary" disabled>Save Profile Picture</button>
        </div>
    </form>
</dialog>

<!-- Native Accessible Dialog: Submit HR Profile Correction Request -->
<dialog
    id="correctionRequestModal"
    class="profile-dialog"
    aria-labelledby="correctionModalTitle"
    data-auto-open="<?= $activeForm === 'correction' ? 'true' : 'false' ?>"
>
    <div class="profile-dialog-head">
        <h2 class="profile-dialog-title" id="correctionModalTitle">Request Profile Correction</h2>
        <button type="button" class="profile-dialog-close" data-close-dialog aria-label="Close correction request dialog">
            <?= icon('x', 18) ?>
        </button>
    </div>

    <form
        id="correctionRequestForm"
        method="POST"
        action="<?= url('/employee/profile/correction-requests') ?>"
        data-fields="<?= e((string)json_encode($allowedCorrectionFields ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)) ?>"
        data-current="<?= e((string)json_encode($currentCorrectionSnapshot, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)) ?>"
        data-departments="<?= e((string)json_encode($departments ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)) ?>"
        data-positions="<?= e((string)json_encode($positions ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)) ?>"
        data-old-value="<?= e($selectedCorrOldVal) ?>"
    >
        <?= csrf_field() ?>

        <div class="profile-dialog-body profile-form-stack">
            <div class="form-group mb-0">
                <label for="correctionFieldSelect" class="form-label">Profile Field to Correct <span class="required">*</span></label>
                <select id="correctionFieldSelect" name="field_name" class="form-select<?= ($activeForm === 'correction' && isset($formErrors['field_name'])) ? ' is-invalid' : '' ?>" data-correction-select required>
                    <?php foreach (($allowedCorrectionFields ?? []) as $fieldKey => $meta): ?>
                        <option value="<?= e((string)$fieldKey) ?>" <?= $selectedCorrField === $fieldKey ? 'selected' : '' ?>>
                            <?= e((string)($meta['label'] ?? $fieldKey)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($activeForm === 'correction' && isset($formErrors['field_name'])): ?>
                    <span class="field-error-text" role="alert"><?= e($formErrors['field_name']) ?></span>
                <?php endif; ?>
            </div>

            <div class="profile-readonly-box">
                <span class="text-xs font-semibold text-muted">Current Value on File</span>
                <span id="correctionCurrentPreview" class="font-semibold text-main">Loading...</span>
            </div>

            <div class="form-group mb-0">
                <label for="correctionRequestedValue" class="form-label">Requested Corrected Value <span class="required">*</span></label>
                <div id="correctionValueContainer"></div>
                <?php if ($activeForm === 'correction' && isset($formErrors['requested_value'])): ?>
                    <span class="field-error-text" role="alert"><?= e($formErrors['requested_value']) ?></span>
                <?php endif; ?>
            </div>

            <div class="form-group mb-0">
                <label for="correctionReasonInput" class="form-label">Reason / Supporting Details <span class="required">*</span></label>
                <textarea
                    id="correctionReasonInput"
                    name="reason"
                    rows="3"
                    class="form-control<?= ($activeForm === 'correction' && isset($formErrors['reason'])) ? ' is-invalid' : '' ?>"
                    minlength="5"
                    maxlength="1000"
                    required
                    placeholder="Briefly explain why this record should be corrected (minimum 5 characters)."
                ><?= e($selectedCorrReason) ?></textarea>
                <?php if ($activeForm === 'correction' && isset($formErrors['reason'])): ?>
                    <span class="field-error-text" role="alert"><?= e($formErrors['reason']) ?></span>
                <?php endif; ?>
            </div>
        </div>

        <div class="profile-dialog-foot">
            <button type="button" class="btn btn-secondary" data-close-dialog>Cancel</button>
            <button type="submit" class="btn btn-primary">Submit Request to HR</button>
        </div>
    </form>
</dialog>
