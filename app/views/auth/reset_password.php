<?php
/** @var bool|null $tokenValid */
/** @var string|null $token */
/** @var string|null $tokenError */
/** @var string|null $error */
/** @var array<int, string>|null $resetErrors */
/** @var array<int, array{key: string, label: string}>|null $passwordRules */

$backUrl = url('/forgot-password');
$backAriaLabel = 'Back to Forgot Password';
require __DIR__ . '/_public_support_header.php';

$isTokenValid = !empty($tokenValid);
$resetToken = (isset($token) && is_string($token)) ? $token : '';
$resolvedTokenError = (isset($tokenError) && is_string($tokenError) && $tokenError !== '')
    ? $tokenError
    : 'This password reset link is invalid, expired, or has already been used.';
$resolvedError = (isset($error) && is_string($error)) ? trim($error) : '';
$rulesList = (isset($passwordRules) && is_array($passwordRules)) ? $passwordRules : [];
$errorList = (isset($resetErrors) && is_array($resetErrors)) ? $resetErrors : [];
$defaultResetSummary = 'Please resolve the password requirements below.';
$resetReportHref = url('/auth/support/report?category=' . rawurlencode('Password Recovery Problem'));
?>
    <main id="mainPublicSupport" class="ps-shell ps-shell--narrow">
        <nav class="ps-breadcrumb" aria-label="Breadcrumb">
            <a href="<?= url('/login') ?>">Sign In</a>
            <span aria-hidden="true">/</span>
            <a href="<?= url('/forgot-password') ?>">Forgot Password</a>
            <span aria-hidden="true">/</span>
            <span>Reset Password</span>
        </nav>

        <section class="ps-card">
            <div class="ps-card-header">
                <h1 class="ps-card-title" style="font-size: 1.35rem;">Set a New Password</h1>
            </div>

            <?php if (!$isTokenValid) : ?>
                <div class="alert-banner alert-error" role="alert" style="margin-bottom: 1rem;">
                    <svg
                        class="alert-icon"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        aria-hidden="true">
                        <circle cx="12" cy="12" r="10" />
                        <path d="m15 9-6 6M9 9l6 6" />
                    </svg>
                    <div><?= e($resolvedTokenError) ?></div>
                </div>

                <p class="ps-option-desc" style="margin-bottom: 1.25rem;">
                    Password reset links remain active for 15 minutes and are invalidated as soon as used or
                    superseded by a newer request.
                </p>

                <div class="ps-actions-row">
                    <a href="<?= url('/forgot-password') ?>" class="ps-btn-primary">Request New Reset Link</a>
                    <a href="<?= e($resetReportHref) ?>" class="ps-btn-secondary">
                        Report Recovery Issue
                    </a>
                </div>
            <?php else : ?>
                <?php if ($resolvedError !== '' || !empty($errorList)) : ?>
                    <div class="alert-banner alert-error" role="alert" style="margin-bottom: 1rem;">
                        <svg
                            class="alert-icon"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            aria-hidden="true">
                            <circle cx="12" cy="12" r="10" />
                            <path d="m15 9-6 6M9 9l6 6" />
                        </svg>
                        <div>
                            <div><?= e($resolvedError !== '' ? $resolvedError : $defaultResetSummary) ?></div>
                            <?php if (count($errorList) > 1) : ?>
                                <ul style="margin: 0.45rem 0 0 1.15rem; padding: 0; font-size: 0.8125rem;">
                                    <?php foreach ($errorList as $item) : ?>
                                        <li><?= e((string)$item) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="ps-security-note" role="note" style="margin-bottom: 1.15rem;">
                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        aria-hidden="true">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                    </svg>
                    <span class="ps-text-justify">
                        Saving a new password will immediately sign out all active sessions, revoke all
                        remembered trusted browsers, and require Email OTP verification on your next sign-in.
                    </span>
                </div>

                <form
                    method="POST"
                    action="<?= url('/reset-password') ?>"
                    id="resetPasswordForm"
                    data-ps-validate
                    novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="token" value="<?= e($resetToken) ?>">

                    <div class="ps-field" style="margin-bottom: 1rem;">
                        <label for="resetNewPassword" class="ps-label">
                            New Password <span class="ps-required" aria-hidden="true">*</span>
                        </label>
                        <div class="ps-input-box">
                            <input
                                type="password"
                                id="resetNewPassword"
                                name="new_password"
                                class="ps-control ps-control--with-toggle"
                                required
                                minlength="8"
                                maxlength="72"
                                autocomplete="new-password"
                                aria-invalid="false"
                                aria-describedby="passwordRulesBox"
                                data-error-id="resetNewPwError"
                                placeholder="Create a strong password">
                            <button
                                type="button"
                                class="password-toggle-btn"
                                data-toggle-password="resetNewPassword"
                                data-visible="false"
                                aria-pressed="false"
                                aria-label="Show password">
                                <svg
                                    class="icon-eye-show"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    aria-hidden="true">
                                    <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z" />
                                    <circle cx="12" cy="12" r="3" />
                                </svg>
                                <svg
                                    class="icon-eye-hide"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    aria-hidden="true">
                                    <path d="M9.88 9.88a3 3 0 1 0 4.24 4.24" />
                                    <path
                                        d="M10.73 5.08A10.43 10.43 0 0 1 12 5
                                           c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68" />
                                    <path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61" />
                                    <line x1="2" x2="22" y1="2" y2="22" />
                                </svg>
                            </button>
                        </div>
                        <div id="resetNewPwError" class="ps-field-error" data-empty="true"></div>
                        <ul id="passwordRulesBox" class="ps-pw-rules" aria-label="Password requirements">
                            <?php foreach ($rulesList as $rule) : ?>
                                <li class="ps-pw-rule" data-rule="<?= e((string)($rule['key'] ?? '')) ?>">
                                    <span class="ps-pw-rule-dot" aria-hidden="true"></span>
                                    <span><?= e((string)($rule['label'] ?? '')) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>

                    <div class="ps-field">
                        <label for="resetConfirmPassword" class="ps-label">
                            Confirm New Password <span class="ps-required" aria-hidden="true">*</span>
                        </label>
                        <div class="ps-input-box">
                            <input
                                type="password"
                                id="resetConfirmPassword"
                                name="confirm_password"
                                class="ps-control ps-control--with-toggle"
                                required
                                minlength="8"
                                maxlength="72"
                                autocomplete="new-password"
                                aria-invalid="false"
                                data-error-id="resetConfirmPwError"
                                placeholder="Confirm your new password">
                            <button
                                type="button"
                                class="password-toggle-btn"
                                data-toggle-password="resetConfirmPassword"
                                data-visible="false"
                                aria-pressed="false"
                                aria-label="Show confirmation password">
                                <svg
                                    class="icon-eye-show"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    aria-hidden="true">
                                    <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z" />
                                    <circle cx="12" cy="12" r="3" />
                                </svg>
                                <svg
                                    class="icon-eye-hide"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    aria-hidden="true">
                                    <path d="M9.88 9.88a3 3 0 1 0 4.24 4.24" />
                                    <path
                                        d="M10.73 5.08A10.43 10.43 0 0 1 12 5
                                           c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68" />
                                    <path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61" />
                                    <line x1="2" x2="22" y1="2" y2="22" />
                                </svg>
                            </button>
                        </div>
                        <div id="resetConfirmPwError" class="ps-field-error" data-empty="true"></div>
                    </div>

                    <div class="ps-actions-row ps-form-actions">
                        <a href="<?= url('/login') ?>" class="ps-btn-secondary">Cancel</a>
                        <button type="submit" id="resetSubmitBtn" class="ps-btn-primary" data-submit-lock>
                            Save New Password
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </section>
    </main>
<?php require __DIR__ . '/_public_support_footer.php'; ?>
