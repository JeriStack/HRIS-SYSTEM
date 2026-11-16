<?php
$backUrl = url('/login');
$backAriaLabel = 'Back to Sign In';
require __DIR__ . '/_public_support_header.php';
?>
    <main id="mainPublicSupport" class="ps-shell ps-shell--narrow" role="main">
        <div class="ps-breadcrumb" aria-label="Breadcrumb">
            <a href="<?= url('/login') ?>">Sign In</a>
            <span aria-hidden="true">/</span>
            <a href="<?= url('/auth/help') ?>">Help &amp; Support</a>
            <span aria-hidden="true">/</span>
            <span>Forgot Password</span>
        </div>

        <section class="ps-card">
            <div class="ps-card-header">
                <h1 class="ps-card-title" style="font-size: 1.35rem;">Forgot Your Password?</h1>
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

            <?php if (!empty($success)): ?>
                <div class="alert-banner alert-success" role="status" style="margin-bottom: 1rem;">
                    <svg class="alert-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                        <path d="m9 11 3 3L22 4" />
                    </svg>
                    <div class="ps-text-justify"><?= e((string)$success) ?></div>
                </div>
            <?php endif; ?>

            <p class="ps-option-desc ps-text-justify" style="margin-bottom: 1rem;">
                Enter the email address registered to your active Admin, HR, or Employee account. Recovery links expire after 15 minutes and can only be used once.
            </p>

            <form method="POST" action="<?= url('/forgot-password') ?>" id="forgotPasswordForm" data-ps-validate novalidate>
                <?= csrf_field() ?>

                <div class="ps-field">
                    <label for="forgotEmail" class="ps-label">Registered Email Address <span aria-hidden="true">*</span></label>
                    <p id="forgotEmailHint" class="ps-hint ps-text-justify">
                        Use your company or registered login email address (for example, name@company.com).
                    </p>
                    <input
                        type="email"
                        id="forgotEmail"
                        name="email"
                        class="ps-control"
                        value="<?= e((string)($oldEmail ?? '')) ?>"
                        required
                        maxlength="150"
                        autocomplete="email"
                        autocorrect="off"
                        autocapitalize="none"
                        spellcheck="false"
                        aria-invalid="false"
                        aria-describedby="forgotEmailHint"
                        data-error-id="forgotEmailError"
                        placeholder="you@company.local">
                    <div id="forgotEmailError" class="ps-field-error" data-empty="true"></div>
                </div>

                <div class="ps-actions-row" style="margin-top: 1rem;">
                    <button type="submit" id="forgotSubmitBtn" class="ps-btn-primary ps-btn-full" data-submit-lock>
                        Send Recovery Instructions
                    </button>
                </div>
            </form>
        </section>
    </main>
<?php require __DIR__ . '/_public_support_footer.php'; ?>
