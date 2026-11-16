<?php
$resolvedRole = in_array($oldPortalRole ?? '', ['admin', 'hr', 'employee'], true) ? $oldPortalRole : '';
$shouldOpenModal = !empty($error) || $resolvedRole !== '' || !empty($oldEmail);
$activeVisualRole = $resolvedRole !== '' ? $resolvedRole : 'hr';
$modalHeaderTitle = match ($activeVisualRole) {
    'admin'    => 'Admin Portal Login',
    'employee' => 'Employee Portal Login',
    default    => 'HR Portal Login',
};
$modalPillLabel = match ($activeVisualRole) {
    'admin'    => 'Admin Portal Login',
    'employee' => 'Employee Portal Login',
    default    => 'HR Portal Login',
};
$modalSubmitLabel = match ($activeVisualRole) {
    'admin'    => 'Sign In to Admin Portal',
    'employee' => 'Sign In to Employee Portal',
    default    => 'Sign In to HR Portal',
};
$emailPlaceholder = match ($activeVisualRole) {
    'admin'    => 'admin.name@company.local',
    'employee' => 'employee.name@company.local',
    default    => 'hr.name@company.local',
};
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <title><?= e($pageTitle ?? 'Sign In - NEXORA HR') ?></title>
    <link rel="icon" type="image/png" href="<?= asset('img/nexora-logo-icon.png') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Manrope:wght@600;700;800&display=swap" rel="stylesheet">
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
    <?php
    $authCssFile = __DIR__ . '/../../../public/assets/css/auth.css';
    $authCssVer = file_exists($authCssFile) ? (string)filemtime($authCssFile) : '2.1';
    $authJsFile = __DIR__ . '/../../../public/assets/js/auth.js';
    $authJsVer = file_exists($authJsFile) ? (string)filemtime($authJsFile) : '2.1';
    ?>
    <link rel="stylesheet" href="<?= asset('css/auth.css') ?>?v=<?= e($authCssVer) ?>">
</head>

<body class="auth-landing-page">
    <a href="#mainAuthContent" class="skip-link">Skip to authentication content</a>

    <!-- Top Utility Header -->
    <header class="auth-header">
        <div class="auth-header-inner">
            <a href="<?= url('/') ?>" class="back-link" aria-label="Back to Public Landing Page" title="Back to Public Landing Page">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="m15 18-6-6 6-6" />
                </svg>
            </a>

            <div class="auth-header-right">
                <button type="button" id="themeToggleBtn" class="theme-toggle-btn" aria-label="Switch to dark mode" title="Switch to dark mode">
                    <svg class="sun-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="12" cy="12" r="4" />
                        <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41" />
                    </svg>
                    <svg class="moon-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z" />
                    </svg>
                </button>
            </div>
        </div>
    </header>

    <!-- Main Authentication Landing View -->
    <main id="mainAuthContent" class="auth-main" role="main">
        <div class="login-brand">
            <h1>Welcome to NEXORA HR</h1>
            <p class="login-brand-desc">Your centralized workspace for organized human resource management.</p>
            <div class="login-tagline-pill">
                <span class="login-tagline-dot" aria-hidden="true"></span>
                <span>People. Processes. Progress.</span>
            </div>
        </div>

        <?php if (!empty($timeout) || !empty($success)): ?>
            <div class="page-alerts-container">
                <?php if (!empty($timeout)): ?>
                    <div class="alert-banner alert-warning" role="alert">
                        <svg class="alert-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z" />
                            <path d="M12 9v4M12 17h.01" />
                        </svg>
                        <div><?= e($timeout) ?></div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($success)): ?>
                    <div class="alert-banner alert-success" role="alert">
                        <svg class="alert-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                            <path d="m9 11 3 3L22 4" />
                        </svg>
                        <div><?= e($success) ?></div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Role Selection Cards (Admin, HR & Employee) -->
        <section class="role-selection-grid" aria-label="Select your application role">
            <!-- CARD 0: ADMIN LOGIN -->
            <article class="role-card role-card-admin <?= $activeVisualRole === 'admin' ? 'is-selected' : '' ?>" data-role-card="admin">
                <div class="role-card-top">
                    <div class="role-card-header-row" style="display:flex; justify-content:center; width:100%;">
                        <div class="role-card-icon" style="margin:0 auto;" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10" />
                                <path d="m9 12 2 2 4-4" />
                            </svg>
                        </div>
                    </div>

                    <h2 class="role-card-title">Admin Portal</h2>
                    <p class="role-card-desc">
                        Full administrative oversight, account governance, role assignment, and audit inspection.
                    </p>
                </div>

                <button type="button" id="continueAsAdminBtn" class="role-card-btn role-card-btn-admin" data-open-role-modal="admin">
                    <span>Continue as Admin</span>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M5 12h14" />
                        <path d="m12 5 7 7-7 7" />
                    </svg>
                </button>
            </article>

            <!-- CARD 1: HR LOGIN -->
            <article class="role-card role-card-hr <?= $activeVisualRole === 'hr' ? 'is-selected' : '' ?>" data-role-card="hr">
                <div class="role-card-top">
                    <div class="role-card-header-row" style="display:flex; justify-content:center; width:100%;">
                        <div class="role-card-icon" style="margin:0 auto;" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="2" y="7" width="20" height="14" rx="2" ry="2" />
                                <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16" />
                            </svg>
                        </div>
                    </div>

                    <h2 class="role-card-title">HR Portal</h2>
                    <p class="role-card-desc">
                        Access employee management, leave administration, and authorized HR operations.
                    </p>
                </div>

                <button type="button" id="continueAsHrBtn" class="role-card-btn role-card-btn-hr" data-open-role-modal="hr">
                    <span>Continue as HR</span>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M5 12h14" />
                        <path d="m12 5 7 7-7 7" />
                    </svg>
                </button>
            </article>

            <!-- CARD 2: EMPLOYEE LOGIN -->
            <article class="role-card role-card-employee <?= $activeVisualRole === 'employee' ? 'is-selected' : '' ?>" data-role-card="employee">
                <div class="role-card-top">
                    <div class="role-card-header-row" style="display:flex; justify-content:center; width:100%;">
                        <div class="role-card-icon" style="margin:0 auto;" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
                                <circle cx="9" cy="7" r="4" />
                                <path d="M22 21v-2a4 4 0 0 0-3-3.87" />
                                <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                            </svg>
                        </div>
                    </div>

                    <h2 class="role-card-title">Employee Portal</h2>
                    <p class="role-card-desc">
                        Access your employee information, submit leave requests, and review available leave information.
                    </p>
                </div>

                <button type="button" id="continueAsEmployeeBtn" class="role-card-btn role-card-btn-employee" data-open-role-modal="employee">
                    <span>Continue as Employee</span>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M5 12h14" />
                        <path d="m12 5 7 7-7 7" />
                    </svg>
                </button>
            </article>
        </section>

        <!-- Security Information Footer -->
        <footer class="auth-security-footer">
            <div class="security-badge">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
                    <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                </svg>
                <span>Protected by Two-Factor Email OTP Verification &bull; Strict Role-Based Access</span>
            </div>
            <nav class="auth-help-links" aria-label="Sign-In Help and Password Recovery">
                <a class="auth-help-link" href="<?= e(url('/auth/help')) ?>">Help &amp; Support</a>
                <span class="auth-help-sep" aria-hidden="true">&bull;</span>
                <a class="auth-help-link" href="<?= e(url('/forgot-password')) ?>">Forgot Password</a>
                <span class="auth-help-sep" aria-hidden="true">&bull;</span>
                <a class="auth-help-link" href="<?= e(url('/auth/help/otp')) ?>">OTP Help</a>
                <span class="auth-help-sep" aria-hidden="true">&bull;</span>
                <a class="auth-help-link" href="<?= e(url('/auth/support/track')) ?>">Track Request</a>
            </nav>
        </footer>
    </main>

    <!-- Pop-Up Login Modal Dialog -->
    <div id="loginModalBackdrop"
        class="login-modal-backdrop <?= $shouldOpenModal ? 'is-open' : '' ?>"
        data-initial-open="<?= $shouldOpenModal ? 'true' : 'false' ?>"
        aria-hidden="<?= $shouldOpenModal ? 'false' : 'true' ?>">
        <div id="loginModalDialog"
            class="login-modal-dialog"
            role="dialog"
            aria-modal="true"
            aria-labelledby="loginModalTitle"
            data-active-role="<?= e($activeVisualRole) ?>"
            data-initial-role="<?= e($resolvedRole) ?>">

            <div class="modal-top-bar">
                <div class="modal-role-pill">
                    <span class="modal-role-pill-dot" aria-hidden="true"></span>
                    <span id="loginModalTitle"><?= e($modalPillLabel) ?></span>
                </div>

                <button type="button"
                    id="closeLoginModalBtn"
                    class="modal-close-btn"
                    aria-label="Close login modal"
                    title="Close login modal">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M18 6 6 18" />
                        <path d="m6 6 12 12" />
                    </svg>
                </button>
            </div>

            <!-- Hero Role Icon (Centered) -->
            <div class="modal-hero-icon-row">
                <div class="modal-role-icon-box" aria-hidden="true">
                    <svg class="icon-role-admin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10" />
                        <path d="m9 12 2 2 4-4" />
                    </svg>
                    <svg class="icon-role-hr" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="2" y="7" width="20" height="14" rx="2" ry="2" />
                        <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16" />
                    </svg>
                    <svg class="icon-role-employee" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2" />
                        <circle cx="12" cy="7" r="4" />
                    </svg>
                </div>
            </div>

            <!-- Alert Notices Inside Modal -->
            <?php if (!empty($error)): ?>
                <div class="alert-banner alert-error" role="alert">
                    <svg class="alert-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="12" cy="12" r="10" />
                        <path d="m15 9-6 6M9 9l6 6" />
                    </svg>
                    <div><?= e($error) ?></div>
                </div>
            <?php endif; ?>

            <!-- Authentication Form -->
            <form method="POST" action="<?= url('/login') ?>" id="loginForm" autocomplete="on">
                <?= csrf_field() ?>
                <input type="hidden" name="portal_role" id="portalRoleInput" value="<?= e($resolvedRole) ?>">

                <div class="form-group">
                    <label for="loginEmail" class="form-label">Email Address</label>
                    <div class="input-wrapper">
                        <svg class="input-leading-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <rect width="20" height="16" x="2" y="4" rx="2" />
                            <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7" />
                        </svg>
                        <input type="email"
                            id="loginEmail"
                            name="email"
                            class="form-input"
                            placeholder="<?= e($emailPlaceholder) ?>"
                            value="<?= e($oldEmail ?? '') ?>"
                            required
                            autofocus
                            autocomplete="email">
                    </div>
                </div>

                <div class="form-group">
                    <div class="form-label-row">
                        <label for="loginPassword" class="form-label">Password</label>
                        <a class="form-inline-help-link" href="<?= e(url('/forgot-password')) ?>">Forgot Password?</a>
                    </div>
                    <div class="input-wrapper">
                        <svg class="input-leading-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <rect width="18" height="11" x="3" y="11" rx="2" ry="2" />
                            <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                        </svg>
                        <input type="password"
                            id="loginPassword"
                            name="password"
                            class="form-input input-password"
                            placeholder="Enter your password"
                            required
                            autocomplete="current-password">
                        <button type="button"
                            id="togglePasswordBtn"
                            class="password-toggle-btn"
                            data-visible="false"
                            aria-label="Show password"
                            title="Show password">
                            <svg class="icon-eye-show" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z" />
                                <circle cx="12" cy="12" r="3" />
                            </svg>
                            <svg class="icon-eye-hide" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M9.88 9.88a3 3 0 1 0 4.24 4.24" />
                                <path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68" />
                                <path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61" />
                                <line x1="2" x2="22" y1="2" y2="22" />
                            </svg>
                        </button>
                    </div>
                </div>

                <button type="submit" id="loginSubmitBtn" class="btn-submit">
                    <svg class="btn-spinner" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true">
                        <path d="M21 12a9 9 0 1 1-6.219-8.56" />
                    </svg>
                    <span id="loginSubmitBtnText"><?= e($modalSubmitLabel) ?></span>
                </button>
            </form>

            <div class="modal-help-strip">
                Need login help?
                <a href="<?= e(url('/auth/help')) ?>">Help &amp; Support</a>
                &middot;
                <a href="<?= e(url('/auth/help/otp')) ?>">OTP Issues</a>
                &middot;
                <a href="<?= e(url('/auth/support/report')) ?>">Report Login Issue</a>
            </div>

            <!-- Modal Footer -->
            <div class="modal-footer">
                <button type="button" id="backToRolesBtn" class="modal-back-btn">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="m15 18-6-6 6-6" />
                    </svg>
                    <span>Back to Role Selection</span>
                </button>

                <span class="modal-otp-note">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10" />
                        <path d="m9 12 2 2 4-4" />
                    </svg>
                    <span>2FA Email OTP Enabled</span>
                </span>
            </div>
        </div>
    </div>

    <script src="<?= asset('js/auth.js') ?>?v=<?= e($authJsVer) ?>" defer></script>
</body>

</html>