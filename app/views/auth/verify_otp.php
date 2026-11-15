<?php
/** @var int|string|null $resendCooldownRemaining */
/** @var string|null $error */
/** @var string|null $success */
/** @var string|null $pageTitle */
/** @var string|null $maskedEmail */

$cooldownSeconds = isset($resendCooldownRemaining) ? max(0, (int)$resendCooldownRemaining) : 0;
$errorMessage = (isset($error) && is_string($error)) ? $error : '';
$successMessage = (isset($success) && is_string($success)) ? $success : '';
$maskedEmailDisplay = (isset($maskedEmail) && is_string($maskedEmail)) ? $maskedEmail : '';
$resolvedPageTitle = (isset($pageTitle) && is_string($pageTitle) && $pageTitle !== '')
    ? $pageTitle
    : 'Two-Factor Verification - NEXORA HR';

$hasServerError = ($errorMessage !== '');
$hasServerSuccess = ($successMessage !== '');

$errorHeading = 'Invalid verification code';
if ($hasServerError) {
    $lowerErr = strtolower($errorMessage);
    if (str_contains($lowerErr, 'expired')) {
        $errorHeading = 'Verification code expired';
    } elseif (str_contains($lowerErr, 'maximum') || str_contains($lowerErr, 'attempts') || str_contains($lowerErr, 'wait')) {
        $errorHeading = 'Verification limit notice';
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($resolvedPageTitle) ?></title>
    <link rel="icon" type="image/png" href="<?= asset('img/nexora-logo-icon.png') ?>">

    <!-- Prevent Theme Flash (FOUC) — Light Default, Persistent Dark Option -->
    <script>
        (function() {
            try {
                var savedTheme = localStorage.getItem('nexora_theme');
                var resolved = savedTheme === 'dark' ? 'dark' : 'light';
                document.documentElement.setAttribute('data-theme', resolved);
            } catch (err) {
                document.documentElement.setAttribute('data-theme', 'light');
            }
        })();
    </script>

    <link rel="stylesheet" href="<?= asset('css/otp.css') ?>">
</head>

<body class="otp-verification-page">
    <a href="#otpMainCard" class="skip-link">Skip to verification code form</a>

    <!-- Screen-Reader Polite Status Announcer -->
    <div id="otpStatusAnnouncer" class="sr-only" aria-live="polite" aria-atomic="true"></div>

    <!-- Top Utility Theme Toggle -->
    <div class="otp-top-controls">
        <button type="button"
            id="themeToggleBtn"
            class="theme-toggle-btn"
            aria-label="Switch to dark mode"
            title="Switch to dark mode">
            <svg class="sun-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="12" cy="12" r="4" />
                <path d="M12 2v2" />
                <path d="M12 20v2" />
                <path d="m4.93 4.93 1.41 1.41" />
                <path d="m17.66 17.66 1.41 1.41" />
                <path d="M2 12h2" />
                <path d="M20 12h2" />
                <path d="m6.34 17.66-1.41 1.41" />
                <path d="m19.07 4.93-1.41 1.41" />
            </svg>
            <svg class="moon-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z" />
            </svg>
        </button>
    </div>

    <!-- 3-Column Page Shell -->
    <div class="page-container">

        <!-- Column 1: Left Branding Panel -->
        <aside class="left-branding" aria-label="NEXORA HR Brand Information">
            <div class="brand-logo-shell" role="img" aria-label="NEXORA HR — People. Processes. Progress.">
                <img
                    src="<?= e(asset('img/nexora-logo-icon.png')) ?>"
                    alt=""
                    class="brand-logo-emblem"
                    width="62"
                    height="62">
                <div class="brand-logo-lockup" aria-hidden="true">
                    <div class="brand-logo-wordmark">
                        <span class="logo-word-nexora">NEXOR<span class="logo-letter-a">A<svg class="logo-delta-mark" viewBox="0 0 12 10" aria-hidden="true" focusable="false"><polygon points="6,1 11,10 1,10" fill="currentColor" /></svg></span></span>
                        <span class="logo-word-hr">HR</span>
                    </div>
                    <div class="brand-logo-tagline">
                        <span class="logo-tagline-rule"></span>
                        <span class="logo-tagline-text">People. Processes. Progress.</span>
                        <span class="logo-tagline-rule"></span>
                    </div>
                </div>
            </div>

            <div class="brand-official-tagline">
                <span class="brand-official-tagline-dot" aria-hidden="true"></span>
                <span>People. Processes. Progress.</span>
            </div>

            <div class="brand-tagline">
                <span>Secure People</span>
                <span class="tagline-dot" aria-hidden="true">&bull;</span>
                <span>Smarter HR</span>
                <span class="tagline-dot" aria-hidden="true">&bull;</span>
                <span>Better Tomorrow</span>
            </div>

            <p class="brand-support-desc">
                Your workforce information deserves a secure and reliable experience.
            </p>
        </aside>

        <!-- Column 2: Center 2FA Verification Card -->
        <main id="otpMainCard" class="center-card-wrapper">
            <div class="verification-card">
                <div class="card-inner">
                    <!-- Top Navy/Blue Lock Badge -->
                    <div class="lock-badge" aria-hidden="true">
                        <svg width="26" height="26" viewBox="0 0 24 24" fill="none">
                            <rect x="4" y="10" width="16" height="12" rx="3" fill="#FFFFFF" />
                            <path d="M8 10V7a4 4 0 1 1 8 0v3" stroke="#FFFFFF" stroke-width="2.4" stroke-linecap="round" />
                            <circle cx="12" cy="15.5" r="1.5" fill="#153D70" />
                            <path d="M12 17v2" stroke="#153D70" stroke-width="1.6" stroke-linecap="round" />
                        </svg>
                    </div>

                    <!-- Title & Subtitle -->
                    <h1 class="card-title">Two-Factor Verification</h1>
                    <p class="card-subtitle">Enter the 6-digit verification code sent to your email.</p>

                    <!-- Masked Email Chip -->
                    <?php if ($maskedEmailDisplay !== ''): ?>
                        <div class="email-chip-wrapper">
                            <div class="email-chip">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <rect x="2" y="4" width="20" height="16" rx="2" />
                                    <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7" />
                                </svg>
                                <span><?= e($maskedEmailDisplay) ?></span>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Server Feedback Alert Banners -->
                    <?php if ($hasServerError): ?>
                        <div class="alert-banner alert-error" role="alert">
                            <span class="status-circle-error" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M12 8v5" />
                                    <path d="M12 16h.01" />
                                </svg>
                            </span>
                            <div>
                                <strong><?= e($errorHeading) ?></strong>
                                <span><?= e($errorMessage) ?></span>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($hasServerSuccess): ?>
                        <div class="alert-banner alert-success" role="status">
                            <span class="status-circle-success" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M20 6 9 17l-5-5" />
                                </svg>
                            </span>
                            <div><?= e($successMessage) ?></div>
                        </div>
                    <?php elseif (!$hasServerError): ?>
                        <div class="alert-banner alert-info" role="status">
                            <span class="status-circle-info" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="10" />
                                    <path d="M12 16v-4" />
                                    <path d="M12 8h.01" />
                                </svg>
                            </span>
                            <div>Check your registered email inbox for your single-use 6-digit verification code.</div>
                        </div>
                    <?php endif; ?>

                    <!-- Client-Side Incomplete Submission Feedback Banner -->
                    <div id="clientFeedbackBanner" class="alert-banner alert-error client-feedback-banner" role="alert" hidden>
                        <span class="status-circle-error" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 8v5" />
                                <path d="M12 16h.01" />
                            </svg>
                        </span>
                        <div>
                            <strong id="clientFeedbackTitle">Incomplete verification code</strong>
                            <span id="clientFeedbackMessage">Please enter all 6 numeric digits sent to your email.</span>
                        </div>
                    </div>

                    <!-- OTP Verification Form -->
                    <form method="POST" action="<?= url('/verify-otp') ?>" id="otpVerifyForm" autocomplete="on">
                        <?= csrf_field() ?>

                        <label class="otp-section-label" for="otp_digit_0">Verification Code (OTP)</label>

                        <!-- 6 Separate Digit Boxes -->
                        <div class="otp-boxes-group" id="otpBoxesGroup" role="group" aria-label="6-digit verification code" aria-describedby="otpExpiryMeta">
                            <input type="text"
                                id="otp_digit_0"
                                class="otp-box-digit <?= $hasServerError ? 'is-invalid' : '' ?>"
                                maxlength="6"
                                inputmode="numeric"
                                pattern="[0-9]"
                                autocomplete="one-time-code"
                                autocorrect="off"
                                autocapitalize="none"
                                spellcheck="false"
                                enterkeyhint="next"
                                data-index="0"
                                aria-label="Digit 1 of verification code"
                                <?= $hasServerError ? 'aria-invalid="true"' : '' ?>
                                autofocus>
                            <input type="text"
                                id="otp_digit_1"
                                class="otp-box-digit <?= $hasServerError ? 'is-invalid' : '' ?>"
                                maxlength="6"
                                inputmode="numeric"
                                pattern="[0-9]"
                                autocorrect="off"
                                autocapitalize="none"
                                spellcheck="false"
                                enterkeyhint="next"
                                data-index="1"
                                aria-label="Digit 2 of verification code"
                                <?= $hasServerError ? 'aria-invalid="true"' : '' ?>>
                            <input type="text"
                                id="otp_digit_2"
                                class="otp-box-digit <?= $hasServerError ? 'is-invalid' : '' ?>"
                                maxlength="6"
                                inputmode="numeric"
                                pattern="[0-9]"
                                autocorrect="off"
                                autocapitalize="none"
                                spellcheck="false"
                                enterkeyhint="next"
                                data-index="2"
                                aria-label="Digit 3 of verification code"
                                <?= $hasServerError ? 'aria-invalid="true"' : '' ?>>
                            <input type="text"
                                id="otp_digit_3"
                                class="otp-box-digit <?= $hasServerError ? 'is-invalid' : '' ?>"
                                maxlength="6"
                                inputmode="numeric"
                                pattern="[0-9]"
                                autocorrect="off"
                                autocapitalize="none"
                                spellcheck="false"
                                enterkeyhint="next"
                                data-index="3"
                                aria-label="Digit 4 of verification code"
                                <?= $hasServerError ? 'aria-invalid="true"' : '' ?>>
                            <input type="text"
                                id="otp_digit_4"
                                class="otp-box-digit <?= $hasServerError ? 'is-invalid' : '' ?>"
                                maxlength="6"
                                inputmode="numeric"
                                pattern="[0-9]"
                                autocorrect="off"
                                autocapitalize="none"
                                spellcheck="false"
                                enterkeyhint="next"
                                data-index="4"
                                aria-label="Digit 5 of verification code"
                                <?= $hasServerError ? 'aria-invalid="true"' : '' ?>>
                            <input type="text"
                                id="otp_digit_5"
                                class="otp-box-digit <?= $hasServerError ? 'is-invalid' : '' ?>"
                                maxlength="6"
                                inputmode="numeric"
                                pattern="[0-9]"
                                autocorrect="off"
                                autocapitalize="none"
                                spellcheck="false"
                                enterkeyhint="done"
                                data-index="5"
                                aria-label="Digit 6 of verification code"
                                <?= $hasServerError ? 'aria-invalid="true"' : '' ?>>
                        </div>

                        <!-- Hidden Canonical Field Submitted to POST /verify-otp -->
                        <input type="hidden" name="otp" id="otpFullValue" value="">

                        <!-- Clipboard Autofill Action (Revealed via JS when Clipboard API is supported) -->
                        <div class="otp-autofill-row" id="otpAutofillRow" hidden>
                            <button type="button"
                                id="otpAutofillBtn"
                                class="btn-autofill-otp"
                                title="Autofill 6-digit verification code from clipboard">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <rect x="8" y="2" width="8" height="4" rx="1" ry="1" />
                                    <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2" />
                                    <path d="m9 14 2 2 4-4" />
                                </svg>
                                <span>Autofill from Clipboard</span>
                            </button>
                        </div>

                        <!-- Expiry and Attempts Info -->
                        <div class="otp-expiry-meta" id="otpExpiryMeta">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <circle cx="12" cy="12" r="10" />
                                <polyline points="12 6 12 12 16 14" />
                            </svg>
                            <span>Code expires in <strong>5 minutes</strong> &bull; 5 attempts maximum</span>
                        </div>

                        <!-- Primary Submit Button -->
                        <button type="submit" id="verifySubmitBtn" class="btn-verify">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#FFFFFF" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                                <path d="m9 12 2 2 4-4" />
                            </svg>
                            <span>Verify &amp; Sign In</span>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#FFFFFF" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <line x1="5" y1="12" x2="19" y2="12" />
                                <polyline points="12 5 19 12 12 19" />
                            </svg>
                        </button>
                    </form>

                    <!-- Secondary Actions: Resend & Cancel / Return -->
                    <div class="secondary-actions-row">
                        <!-- Left: Resend Code Form -->
                        <form method="POST" action="<?= url('/verify-otp/resend') ?>" id="resendForm" style="margin: 0; text-align: center;">
                            <?= csrf_field() ?>
                            <button type="submit"
                                id="resendBtn"
                                class="btn-secondary-action"
                                data-cooldown-seconds="<?= $cooldownSeconds ?>"
                                <?= $cooldownSeconds > 0 ? 'disabled' : '' ?>>
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M21.5 2v6h-6M2.5 22v-6h6M2 11.5a10 10 0 0 1 18.8-4.3M22 12.5a10 10 0 0 1-18.8 4.2" />
                                </svg>
                                <span>Resend Code <span id="cooldownTimer"><?= $cooldownSeconds > 0 ? '(' . $cooldownSeconds . 's)' : '' ?></span></span>
                            </button>
                        </form>

                        <!-- Hairline Divider -->
                        <div class="secondary-action-divider" aria-hidden="true"></div>

                        <!-- Right: Back to Sign In Form -->
                        <form method="POST" action="<?= url('/verify-otp/cancel') ?>" id="cancelForm" style="margin: 0; text-align: center;">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn-secondary-action">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <line x1="19" y1="12" x2="5" y2="12" />
                                    <polyline points="12 19 5 12 12 5" />
                                </svg>
                                <span>Back to Sign In</span>
                            </button>
                        </form>
                    </div>

                    <!-- Card Bottom Guard Disclaimer -->
                    <div class="card-footer-strip">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="var(--brand-blue)" style="vertical-align: -2px; margin-right: 3px;" aria-hidden="true">
                            <path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm-2 16l-4-4 1.41-1.41L10 14.17l6.59-6.59L18 9l-8 8z" />
                        </svg>
                        NEXORA HR Security Guard &bull; Protected via Two-Factor Email OTP<br>
                        Never share your one-time verification code with anyone.
                    </div>
                </div>
            </div>
        </main>

        <!-- Column 3: Right Security Illustration & Messaging -->
        <aside class="right-security-art" aria-label="Security assurance">
            <div class="orbital-container" aria-hidden="true">
                <svg width="260" height="260" viewBox="0 0 320 320" fill="none">
                    <defs>
                        <linearGradient id="shieldTransGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                            <stop offset="0%" stop-color="#EFF6FF" stop-opacity="0.92" />
                            <stop offset="100%" stop-color="#DBEAFE" stop-opacity="0.55" />
                        </linearGradient>
                    </defs>

                    <!-- Outer Rotating Orbit Group -->
                    <g class="orbit-ring-outer">
                        <circle cx="160" cy="160" r="130" stroke="#60A5FA" stroke-width="1.5" stroke-dasharray="5 7" opacity="0.65" />
                        <circle cx="252" cy="68" r="4" fill="#2563EB" opacity="0.85" />
                        <circle cx="47" cy="225" r="3.5" fill="#14B8A6" opacity="0.8" />
                        <circle cx="224" cy="224" r="3.5" fill="#153D70" opacity="0.75" />
                    </g>

                    <!-- Inner Orbit Ring -->
                    <circle cx="160" cy="160" r="90" stroke="#93C5FD" stroke-width="1.5" opacity="0.7" />

                    <!-- Centered Shield & Padlock Composition -->
                    <g class="shield-pulse-group">
                        <path d="M 160 108 L 122 124 V 164 C 122 194 160 214 160 214 C 160 214 198 194 198 164 V 124 Z" fill="url(#shieldTransGrad)" stroke="#3B82F6" stroke-width="2.2" stroke-linejoin="round" />

                        <rect x="145" y="151" width="30" height="23" rx="4.5" fill="#153D70" />
                        <path d="M 152 151 V 141 C 152 135 156 131 160 131 C 164 131 168 135 168 141 V 151" stroke="#2563EB" stroke-width="3.5" stroke-linecap="round" fill="none" />
                        <circle cx="160" cy="160.5" r="2.2" fill="#FFFFFF" />
                        <path d="M 160 162.7 V 167.2" stroke="#14B8A6" stroke-width="2" stroke-linecap="round" />
                    </g>
                </svg>
            </div>

            <div class="security-cursive-badge">
                <div class="cursive-text">Your Security Matters</div>
                <p class="security-art-caption">Single-use cryptographic verification protects your NEXORA HR workspace.</p>
                <svg class="cursive-underline" width="120" height="12" viewBox="0 0 130 14" fill="none" aria-hidden="true">
                    <path d="M 4 8 C 42 14, 88 2, 126 8" stroke="#14B8A6" stroke-width="2.5" stroke-linecap="round" />
                </svg>
            </div>
        </aside>

    </div>

    <script src="<?= asset('js/otp.js') ?>" defer></script>
</body>

</html>
