<?php
$backUrl = url('/auth/help');
$backAriaLabel = 'Back to Help & Support';
require __DIR__ . '/_public_support_header.php';
?>
    <main id="mainPublicSupport" class="ps-shell" role="main">
        <div class="ps-breadcrumb" aria-label="Breadcrumb">
            <a href="<?= url('/login') ?>">Sign In</a>
            <span aria-hidden="true">/</span>
            <a href="<?= url('/auth/help') ?>">Help &amp; Support</a>
            <span aria-hidden="true">/</span>
            <span>OTP Verification Problems</span>
        </div>

        <section class="ps-hero">
            <h1>Troubleshooting Email OTP Verification</h1>
            <p class="ps-hero-desc">
                NEXORA HR requires a fresh 6-digit Email OTP code whenever you sign in from a new browser, an expired trust window, or following a password reset or security state update.
            </p>
        </section>

        <section class="ps-diag-grid" aria-label="Common OTP troubleshooting steps">
            <article class="ps-diag-item">
                <h2>1. Verification Code Delayed or Missing</h2>
                <p>
                    Wait up to 60 seconds and check Junk, Spam, or Focused/Other inbox tabs for emails from NEXORA HR. Confirm you are checking the primary email registered on your HRIS employee record.
                </p>
            </article>

            <article class="ps-diag-item">
                <h2>2. Code Rejected as Invalid or Already Used</h2>
                <p>
                    Starting a new sign-in or pressing Resend Code immediately invalidates older 6-digit codes. Always enter the newest verification email rather than an earlier message thread.
                </p>
            </article>

            <article class="ps-diag-item">
                <h2>3. Code Expired After 5 Minutes</h2>
                <p>
                    Each OTP challenge expires automatically after 5 minutes for security. Click <strong>Resend Code</strong> on the OTP verification page (available once per 60-second cooldown) or sign in again for a fresh code.
                </p>
            </article>

            <article class="ps-diag-item">
                <h2>4. Locked Out After Too Many Attempts</h2>
                <p>
                    Five consecutive incorrect OTP entries invalidate the active challenge, and 10 failed verifications within 15 minutes trigger a temporary security lockout. Wait 15 minutes before retrying from the login page.
                </p>
            </article>

            <article class="ps-diag-item">
                <h2>5. OTP Required Again on a Trusted Browser</h2>
                <p>
                    Resetting your password, changing your email address, revoking trusted devices, or switching between Admin/HR browser contexts requires one fresh Email OTP check to re-establish device trust.
                </p>
            </article>

            <article class="ps-diag-item">
                <h2>6. Strict No-Bypass Security Policy</h2>
                <p>
                    Support personnel never receive your OTP code and cannot bypass two-factor verification. If your registered email address bounces or changed, submit a public assistance ticket below for identity verification.
                </p>
            </article>
        </section>

        <section class="ps-card" aria-label="Next actions for OTP assistance">
            <div class="ps-card-header">
                <h2 class="ps-card-title">Still Unable to Complete OTP Verification?</h2>
            </div>
            <p class="ps-option-desc" style="margin-bottom: 1rem;">
                If none of the checks above resolve your delivery or verification problem, return to the verification page if an active challenge is open, reset your password if credentials changed, or open a support ticket with HR Support.
            </p>
            <div class="ps-actions-row">
                <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
                    <a href="<?= url('/verify-otp') ?>" class="ps-btn-secondary">Return to OTP Screen</a>
                    <a href="<?= url('/forgot-password') ?>" class="ps-btn-secondary">Reset Password</a>
                </div>
                <a href="<?= url('/auth/support/report?category=' . rawurlencode('OTP Delivery Problem')) ?>" class="ps-btn-primary">
                    Report OTP Delivery Problem
                </a>
            </div>
        </section>
    </main>
<?php require __DIR__ . '/_public_support_footer.php'; ?>
