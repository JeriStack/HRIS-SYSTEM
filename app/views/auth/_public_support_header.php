<?php
/**
 * Shared standalone layout header for unauthenticated Help & Support,
 * Password Recovery, and Pre-Login Support Tracker views.
 */
$authCssFile = __DIR__ . '/../../../public/assets/css/auth.css';
$authCssVer = file_exists($authCssFile) ? (string)filemtime($authCssFile) : '2.1';
$psCssFile = __DIR__ . '/../../../public/assets/css/public-support.css';
$psCssVer = file_exists($psCssFile) ? (string)filemtime($psCssFile) : '1.0';
$backTarget = $backUrl ?? url('/auth/help');
$backLabel = $backAriaLabel ?? 'Back to Help & Support';
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <title><?= e($pageTitle ?? 'Help & Support - NEXORA HR') ?></title>
    <link rel="icon" type="image/png" href="<?= asset('img/nexora-logo-icon.png') ?>">
    <script>
        (function () {
            try {
                var savedTheme = localStorage.getItem('nexora_theme');
                var resolved = savedTheme === 'dark' ? 'dark' : 'light';
                document.documentElement.setAttribute('data-theme', resolved);
            } catch (_err) {
                document.documentElement.setAttribute('data-theme', 'light');
            }
        })();
    </script>
    <link rel="stylesheet" href="<?= asset('css/auth.css') ?>?v=<?= e($authCssVer) ?>">
    <link rel="stylesheet" href="<?= asset('css/public-support.css') ?>?v=<?= e($psCssVer) ?>">
</head>

<body class="auth-landing-page">
    <a href="#mainPublicSupport" class="skip-link">Skip to main content</a>

    <header class="auth-header">
        <div class="auth-header-inner">
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <a href="<?= e($backTarget) ?>" class="back-link" aria-label="<?= e($backLabel) ?>" title="<?= e($backLabel) ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="m15 18-6-6 6-6" />
                    </svg>
                </a>
                <a href="<?= url('/auth/help') ?>" class="ps-header-brand">
                    <img src="<?= asset('img/nexora-logo-icon.png') ?>" alt="NEXORA HR" width="30" height="30">
                    <span>NEXORA HR</span>
                </a>
            </div>

            <div class="auth-header-right">
                <nav class="ps-header-nav-links" aria-label="Support quick navigation">
                    <a href="<?= url('/auth/help') ?>" class="ps-nav-chip">Help Hub</a>
                    <a href="<?= url('/auth/support/track') ?>" class="ps-nav-chip">Track Ticket</a>
                    <a href="<?= url('/login') ?>" class="ps-nav-chip">Sign In</a>
                </nav>
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

    <div id="formLiveAnnouncer" class="sr-only" aria-live="polite" aria-atomic="true"></div>
