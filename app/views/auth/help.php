<?php
$backUrl = url('/login');
$backAriaLabel = 'Back to Sign In';
require __DIR__ . '/_public_support_header.php';
?>
    <main id="mainPublicSupport" class="ps-shell" role="main">
        <div class="ps-breadcrumb" aria-label="Breadcrumb">
            <a href="<?= url('/') ?>">Home</a>
            <span aria-hidden="true">/</span>
            <a href="<?= url('/login') ?>">Sign In</a>
            <span aria-hidden="true">/</span>
            <span>Help &amp; Support</span>
        </div>

        <section class="ps-hero">
            <h1>Login &amp; Account Assistance</h1>
            <p class="ps-hero-desc">
                Resolve sign-in blockers before entering your portal workspace. Select one of the four assistance pathways below or check the live status of a previously submitted request.
            </p>
        </section>

        <?php if (!empty($error)): ?>
            <div class="alert-banner alert-error" role="alert">
                <svg class="alert-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="12" cy="12" r="10" />
                    <path d="m15 9-6 6M9 9l6 6" />
                </svg>
                <div><?= e((string)$error) ?></div>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert-banner alert-success" role="status">
                <svg class="alert-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                    <path d="m9 11 3 3L22 4" />
                </svg>
                <div><?= e((string)$success) ?></div>
            </div>
        <?php endif; ?>

        <section class="ps-options-grid" aria-label="Authentication assistance options">
            <!-- 1. Forgot Password -->
            <article class="ps-option-card">
                <div class="ps-option-head">
                    <div class="ps-option-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect width="18" height="11" x="3" y="11" rx="2" ry="2" />
                            <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="ps-option-title">Forgot Password</h2>
                        <p class="ps-option-desc">
                            Request a 15-minute single-use password reset link sent to your registered Admin, HR, or Employee email address.
                        </p>
                    </div>
                </div>
                <a href="<?= url('/forgot-password') ?>" id="hubForgotPasswordLink" class="ps-option-action">
                    <span>Reset Password</span>
                </a>
            </article>

            <!-- 2. OTP Verification Problems -->
            <article class="ps-option-card">
                <div class="ps-option-head">
                    <div class="ps-option-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                            <path d="M12 8v4" />
                            <path d="M12 16h.01" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="ps-option-title">OTP Verification Problems</h2>
                        <p class="ps-option-desc">
                            Troubleshoot delayed verification emails, expired 5-minute codes, attempt lockouts, or remembered browser prompts.
                        </p>
                    </div>
                </div>
                <a href="<?= url('/auth/help/otp') ?>" id="hubOtpHelpLink" class="ps-option-action">
                    <span>Open OTP Guide</span>
                </a>
            </article>

            <!-- 3. Unable to Sign In -->
            <article class="ps-option-card">
                <div class="ps-option-head">
                    <div class="ps-option-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10" />
                            <line x1="4.93" y1="4.93" x2="19.07" y2="19.07" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="ps-option-title">Unable to Sign In</h2>
                        <p class="ps-option-desc">
                            Report a locked account, disabled profile status, portal mismatch, or unexpected sign-in screen error.
                        </p>
                    </div>
                </div>
                <a href="<?= url('/auth/support/report?category=' . rawurlencode('Unable to Sign In')) ?>" id="hubUnableSignInLink" class="ps-option-action">
                    <span>Report Sign-In Issue</span>
                </a>
            </article>

            <!-- 4. Contact Support -->
            <article class="ps-option-card">
                <div class="ps-option-head">
                    <div class="ps-option-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="ps-option-title">Contact Support</h2>
                        <p class="ps-option-desc">
                            Send a general pre-login inquiry or attach error screenshots for direct review by HR &amp; Technical Support.
                        </p>
                    </div>
                </div>
                <a href="<?= url('/auth/support/report') ?>" id="hubContactSupportLink" class="ps-option-action ps-option-action--secondary">
                    <span>Contact HR Support</span>
                </a>
            </article>
        </section>

        <section class="ps-two-col ps-two-col--hub" aria-label="Request tracking and account verification guidance">
            <article class="ps-card">
                <div class="ps-card-header">
                    <h2 class="ps-card-title">Track an Existing Request</h2>
                </div>
                <form method="POST" action="<?= url('/auth/support/track') ?>" data-ps-validate novalidate>
                    <?= csrf_field() ?>
                    <div class="ps-field">
                        <label for="hubTrackToken" class="ps-label">Private Tracking Token or Link</label>
                        <p id="hubTrackHint" class="ps-hint">
                            Paste the tracking token or full URL from your AUTH-YYYY-NNNNN confirmation email.
                        </p>
                        <input
                            type="text"
                            id="hubTrackToken"
                            name="tracking_token"
                            class="ps-control tabular-nums"
                            required
                            autocomplete="off"
                            autocorrect="off"
                            autocapitalize="none"
                            spellcheck="false"
                            aria-invalid="false"
                            aria-describedby="hubTrackHint"
                            data-error-id="hubTrackError"
                            placeholder="Paste your tracking token">
                        <div id="hubTrackError" class="ps-field-error" data-empty="true"></div>
                    </div>
                    <div class="ps-actions-row ps-actions-row--center">
                        <button type="submit" class="ps-btn-primary" data-submit-lock>View Request Status</button>
                    </div>
                </form>
            </article>

            <article class="ps-card">
                <div class="ps-card-header">
                    <h2 class="ps-card-title">Lost Email Access or Device?</h2>
                </div>
                <p class="ps-option-desc" style="margin-bottom: 0.75rem;">
                    For security, NEXORA HR staff will never read, issue, or bypass Email OTP codes or passwords over email.
                </p>
                <p class="ps-option-desc" style="margin-bottom: 1rem;">
                    If you lost access to your registered company mailbox or recovery device, submit a support request with an alternative contact email and HR will coordinate in-person or official identity verification before updating your account record.
                </p>
                <div class="ps-actions-row ps-actions-row--center">
                    <a href="<?= url('/auth/support/report?category=' . rawurlencode('Account Locked or Disabled')) ?>" class="ps-btn-secondary">
                        Request Account Verification Guidance
                    </a>
                </div>
            </article>
        </section>
    </main>
<?php require __DIR__ . '/_public_support_footer.php'; ?>
