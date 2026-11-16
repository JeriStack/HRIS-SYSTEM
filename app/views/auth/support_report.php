<?php
$backUrl = url('/auth/help');
$backAriaLabel = 'Back to Help & Support';
require __DIR__ . '/_public_support_header.php';

$old = $old ?? [];
$categories = $categories ?? [];
$priorities = $priorities ?? ['Low', 'Medium', 'High', 'Urgent'];
$maxFiles = (int)($maxFiles ?? 3);
$maxBytes = (int)($maxBytes ?? 5242880);
$maxMb = number_format($maxBytes / (1024 * 1024), 1);
$submissionResult = $submissionResult ?? null;
?>
    <main id="mainPublicSupport" class="ps-shell" role="main">
        <div class="ps-breadcrumb" aria-label="Breadcrumb">
            <a href="<?= url('/login') ?>">Sign In</a>
            <span aria-hidden="true">/</span>
            <a href="<?= url('/auth/help') ?>">Help &amp; Support</a>
            <span aria-hidden="true">/</span>
            <span>Contact &amp; Login Issue Support</span>
        </div>

        <section class="ps-hero">
            <h1>Report a Login Issue or Contact Support</h1>
            <p class="ps-hero-desc">
                Submit a pre-login support ticket directly to NEXORA HR &amp; IT Support. After submitting, you will receive an <strong>AUTH-YYYY-NNNNN</strong> reference and a private tracking link by email.
            </p>
        </section>

        <?php if (is_array($submissionResult) && !empty($submissionResult['ticket_ref'])): ?>
            <section class="ps-card" role="status" aria-live="polite" style="border-color: var(--success-border);">
                <div class="ps-card-header">
                    <h2 class="ps-card-title">Support Request Submitted</h2>
                    <span class="ps-badge ps-badge-success">Submitted</span>
                </div>
                <p style="margin: 0 0 1rem; font-size: 0.875rem; line-height: 1.55;">
                    Your request has been logged under reference
                    <strong class="tabular-nums" id="publicTicketRefDisplay"><?= e((string)$submissionResult['ticket_ref']) ?></strong>.
                    A private tracking link has been sent to <strong><?= e((string)($submissionResult['masked_email'] ?? 'your contact email')) ?></strong> so only you can view updates and HR responses.
                </p>
                <div class="ps-actions-row">
                    <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
                        <a href="<?= url('/auth/support/track') ?>" class="ps-btn-primary">Open Ticket Tracker</a>
                        <a href="<?= url('/auth/help') ?>" class="ps-btn-secondary">Back to Help Hub</a>
                    </div>
                    <a href="<?= url('/login') ?>" class="ps-btn-secondary">Return to Sign In</a>
                </div>
            </section>
        <?php endif; ?>

        <section class="ps-card" aria-labelledby="publicSupportFormTitle">
            <div class="ps-card-header">
                <h2 id="publicSupportFormTitle" class="ps-card-title">Public Authentication Assistance Form</h2>
                <span class="ps-badge ps-badge-info">Pre-Login Support</span>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert-banner alert-error" role="alert" style="margin-bottom: 1rem;">
                    <svg class="alert-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="12" cy="12" r="10" />
                        <path d="m15 9-6 6M9 9l6 6" />
                    </svg>
                    <div><?= e((string)$error) ?></div>
                </div>
            <?php endif; ?>

            <div class="ps-security-note" role="note" style="margin-bottom: 1.15rem;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z" />
                    <path d="M12 9v4M12 17h.01" />
                </svg>
                <span>
                    <strong>Security Policy:</strong> Never include your account password, OTP verification code, or private password reset links in the form below or in uploaded screenshots.
                </span>
            </div>

            <form method="POST" action="<?= url('/auth/support/report') ?>" enctype="multipart/form-data" id="publicSupportForm" data-ps-validate novalidate>
                <?= csrf_field() ?>

                <div class="hp-field-wrap" aria-hidden="true">
                    <label for="companyWebsite">Leave this field empty</label>
                    <input type="text" id="companyWebsite" name="company_website" value="" tabindex="-1" autocomplete="off">
                </div>

                <div class="ps-form-grid">
                    <div class="ps-field">
                        <label for="publicReqName" class="ps-label">Full Name <span aria-hidden="true">*</span></label>
                        <p id="publicReqNameHint" class="ps-hint">Enter your full name as listed on your employee profile (2-120 characters).</p>
                        <input
                            type="text"
                            id="publicReqName"
                            name="requester_name"
                            class="ps-control"
                            value="<?= e((string)($old['requester_name'] ?? '')) ?>"
                            required
                            minlength="2"
                            maxlength="120"
                            autocomplete="name"
                            aria-invalid="false"
                            aria-describedby="publicReqNameHint"
                            data-error-id="publicReqNameError"
                            placeholder="Juan Dela Cruz">
                        <div id="publicReqNameError" class="ps-field-error" data-empty="true"></div>
                    </div>

                    <div class="ps-field">
                        <label for="publicReqEmail" class="ps-label">Contact Email Address <span aria-hidden="true">*</span></label>
                        <p id="publicReqEmailHint" class="ps-hint">Your ticket confirmation and private tracking link will be sent here.</p>
                        <input
                            type="email"
                            id="publicReqEmail"
                            name="requester_email"
                            class="ps-control"
                            value="<?= e((string)($old['requester_email'] ?? '')) ?>"
                            required
                            maxlength="150"
                            autocomplete="email"
                            autocorrect="off"
                            autocapitalize="none"
                            spellcheck="false"
                            aria-invalid="false"
                            aria-describedby="publicReqEmailHint"
                            data-error-id="publicReqEmailError"
                            placeholder="you@company.local">
                        <div id="publicReqEmailError" class="ps-field-error" data-empty="true"></div>
                    </div>

                    <div class="ps-field">
                        <label for="publicCategory" class="ps-label">Issue Category <span aria-hidden="true">*</span></label>
                        <p id="publicCategoryHint" class="ps-hint">Select the category that matches your sign-in issue.</p>
                        <select
                            id="publicCategory"
                            name="category"
                            class="ps-control"
                            required
                            aria-invalid="false"
                            aria-describedby="publicCategoryHint"
                            data-error-id="publicCategoryError">
                            <option value="">Select a category...</option>
                            <?php foreach ($categories as $catKey => $catMeta):
                                $selectedCat = (string)($old['category'] ?? '') === (string)$catKey;
                            ?>
                                <option value="<?= e((string)$catKey) ?>"<?= $selectedCat ? ' selected' : '' ?>>
                                    <?= e((string)($catMeta['label'] ?? $catKey)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div id="publicCategoryError" class="ps-field-error" data-empty="true"></div>
                    </div>

                    <div class="ps-field">
                        <label for="publicPriority" class="ps-label">Urgency</label>
                        <p id="publicPriorityHint" class="ps-hint">Optional impact severity (defaults per category).</p>
                        <select id="publicPriority" name="priority" class="ps-control" aria-describedby="publicPriorityHint">
                            <option value="">Recommended default</option>
                            <?php foreach ($priorities as $prio):
                                $selectedPrio = (string)($old['priority'] ?? '') === (string)$prio;
                            ?>
                                <option value="<?= e((string)$prio) ?>"<?= $selectedPrio ? ' selected' : '' ?>>
                                    <?= e((string)$prio) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="ps-field-error" data-empty="true"></div>
                    </div>

                    <div class="ps-field">
                        <label for="publicIssueDatetime" class="ps-label">Approximate Date &amp; Time of Issue</label>
                        <p id="publicDatetimeHint" class="ps-hint">Optional (e.g. Nov 16, 9:15 AM PHT) to help check mail/auth logs.</p>
                        <input
                            type="text"
                            id="publicIssueDatetime"
                            name="issue_datetime"
                            class="ps-control"
                            value="<?= e((string)($old['issue_datetime'] ?? '')) ?>"
                            maxlength="100"
                            aria-describedby="publicDatetimeHint"
                            placeholder="Today at 9:15 AM PHT">
                        <div class="ps-field-error" data-empty="true"></div>
                    </div>

                    <div class="ps-field">
                        <label for="publicClientEnv" class="ps-label">Browser &amp; Device Context</label>
                        <p id="publicClientEnvHint" class="ps-hint">Auto-filled browser summary (editable before submission).</p>
                        <input
                            type="text"
                            id="publicClientEnv"
                            name="client_env"
                            class="ps-control"
                            maxlength="255"
                            aria-describedby="publicClientEnvHint"
                            placeholder="Chrome on Windows">
                        <div class="ps-field-error" data-empty="true"></div>
                    </div>

                    <div class="ps-field ps-field-full">
                        <label for="publicSubject" class="ps-label">Subject <span aria-hidden="true">*</span></label>
                        <p id="publicSubjectHint" class="ps-hint">Brief summary of your sign-in issue (5-200 characters).</p>
                        <input
                            type="text"
                            id="publicSubject"
                            name="subject"
                            class="ps-control"
                            value="<?= e((string)($old['subject'] ?? '')) ?>"
                            required
                            minlength="5"
                            maxlength="200"
                            aria-invalid="false"
                            aria-describedby="publicSubjectHint"
                            data-error-id="publicSubjectError"
                            placeholder="OTP verification email not arriving after password login">
                        <div id="publicSubjectError" class="ps-field-error" data-empty="true"></div>
                    </div>

                    <div class="ps-field ps-field-full">
                        <label for="publicDescription" class="ps-label">Detailed Description <span aria-hidden="true">*</span></label>
                        <p id="publicDescHint" class="ps-hint">Describe what happened and any exact on-screen message (15-5,000 characters). Never paste passwords or OTP codes.</p>
                        <textarea
                            id="publicDescription"
                            name="description"
                            class="ps-control"
                            rows="5"
                            required
                            minlength="15"
                            maxlength="5000"
                            aria-invalid="false"
                            aria-describedby="publicDescHint"
                            data-error-id="publicDescError"
                            placeholder="Include steps taken, selected portal (Admin/HR/Employee), and visible error banner text..."><?= e((string)($old['description'] ?? '')) ?></textarea>
                        <div id="publicDescError" class="ps-field-error" data-empty="true"></div>
                    </div>

                    <div class="ps-field ps-field-full">
                        <label for="publicAttachments" class="ps-label">Supporting Screenshots or Logs (Optional)</label>
                        <p id="publicFilesHint" class="ps-hint">
                            Allowed formats: PNG, JPG, WEBP, PDF, TXT (up to <?= $maxFiles ?> files, max <?= $maxMb ?> MB each). Crop out passwords or tokens before uploading.
                        </p>
                        <input
                            type="file"
                            id="publicAttachments"
                            name="attachments[]"
                            class="ps-control"
                            multiple
                            accept=".png,.jpg,.jpeg,.webp,.pdf,.txt"
                            aria-describedby="publicFilesHint"
                            data-ps-files
                            data-max-files="<?= $maxFiles ?>"
                            data-max-bytes="<?= $maxBytes ?>"
                            data-list-id="publicFileList"
                            data-status-id="publicFileStatus">
                        <ul id="publicFileList" class="ps-file-list" hidden></ul>
                        <div id="publicFileStatus" class="ps-field-error" data-empty="true" hidden></div>
                    </div>
                </div>

                <div class="ps-actions-row">
                    <a href="<?= url('/auth/help') ?>" class="ps-btn-secondary">Cancel</a>
                    <button type="submit" id="publicSupportSubmitBtn" class="ps-btn-primary" data-submit-lock>
                        Submit Support Request
                    </button>
                </div>
            </form>
        </section>
    </main>
<?php require __DIR__ . '/_public_support_footer.php'; ?>
