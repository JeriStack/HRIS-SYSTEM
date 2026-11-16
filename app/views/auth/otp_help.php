<?php
$backUrl = url('/auth/help');
$backAriaLabel = 'Back to Help & Support';
require __DIR__ . '/_public_support_header.php';
$otpReportHref = url('/auth/support/report?category=' . rawurlencode('OTP Delivery Problem'));
?>
    <main id="mainPublicSupport" class="ps-shell">
        <nav class="ps-breadcrumb" aria-label="Breadcrumb">
            <a href="<?= url('/login') ?>">Sign In</a>
            <span aria-hidden="true">/</span>
            <a href="<?= url('/auth/help') ?>">Help &amp; Support</a>
            <span aria-hidden="true">/</span>
            <span>OTP Verification Problems</span>
        </nav>

        <section class="ps-hero">
            <h1>Troubleshooting Email OTP Verification</h1>
            <p class="ps-hero-desc ps-text-justify">
                NEXORA HR requires a new 6-digit Email OTP code when you sign in from an untrusted browser, after
                device trust expires, or following a password reset or account security change.
            </p>
        </section>

        <section class="ps-diag-grid" aria-label="Common OTP troubleshooting steps">
            <article class="ps-diag-item">
                <h2>1. Verification Code Delayed or Missing</h2>
                <p class="ps-text-justify">
                    Wait up to 60 seconds and check Junk, Spam, or secondary inbox tabs for emails from NEXORA HR.
                    Confirm that you are checking the primary email address registered to your account.
                </p>
            </article>

            <article class="ps-diag-item">
                <h2>2. Code Rejected as Invalid or Already Used</h2>
                <p class="ps-text-justify">
                    Starting a new sign-in or clicking <strong>Resend Code</strong> immediately invalidates earlier
                    codes. Always enter the 6-digit code from the newest verification email rather than an earlier
                    thread.
                </p>
            </article>

            <article class="ps-diag-item">
                <h2>3. Code Expired After 5 Minutes</h2>
                <p class="ps-text-justify">
                    Each verification code expires automatically after 5 minutes. On the OTP verification screen,
                    click <strong>Resend Code</strong> (available once every 60 seconds) or sign in again to receive
                    a fresh code.
                </p>
            </article>

            <article class="ps-diag-item">
                <h2>4. Locked Out After Too Many Attempts</h2>
                <p class="ps-text-justify">
                    Entering 5 incorrect codes in a row invalidates the active code, and 10 failed attempts within 15
                    minutes trigger a temporary security lockout. Wait 15 minutes before retrying from Sign In.
                </p>
            </article>

            <article class="ps-diag-item">
                <h2>5. OTP Required Again on a Trusted Browser</h2>
                <p class="ps-text-justify">
                    Resetting your password, updating your email address, revoking trusted devices, or switching between
                    Admin and HR browser sessions clears saved browser trust and requires a fresh Email OTP check.
                </p>
            </article>

            <article class="ps-diag-item">
                <h2>6. Strict No-Bypass Security Policy</h2>
                <p class="ps-text-justify">
                    Support staff never see your OTP code and cannot bypass two-factor verification. If your registered
                    email address is unreachable or has changed, submit a support request below for identity
                    verification.
                </p>
            </article>
        </section>

        <section class="ps-card" aria-label="Next actions for OTP assistance">
            <div class="ps-card-header">
                <h2 class="ps-card-title">Still Unable to Complete OTP Verification?</h2>
            </div>
            <p class="ps-option-desc ps-text-justify" style="margin-bottom: 1rem;">
                If these steps do not resolve the issue, return to the OTP screen while a verification session is
                active, reset your password if your credentials have changed, or submit a delivery problem report to HR
                Support.
            </p>
            <div class="ps-actions-row">
                <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
                    <a href="<?= url('/verify-otp') ?>" class="ps-btn-secondary">Return to OTP Screen</a>
                    <a href="<?= url('/forgot-password') ?>" class="ps-btn-secondary">Reset Password</a>
                </div>
                <a href="<?= e($otpReportHref) ?>" class="ps-btn-primary">
                    Report OTP Delivery Problem
                </a>
            </div>
        </section>
    </main>
<?php require __DIR__ . '/_public_support_footer.php'; ?>
