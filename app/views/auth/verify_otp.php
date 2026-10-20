<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'Two-Factor Verification - Employee HRIS & Leave Management System') ?></title>
    
    <!-- Google Fonts for Modern UI and Authentic Calligraphy Signature -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Caveat:wght@600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            min-height: 100vh;
            min-height: 100dvh;
            background-color: #F7FBFF;
            background-image: 
                radial-gradient(ellipse 65% 55% at 50% 50%, #FFFFFF 25%, rgba(255, 255, 255, 0.95) 50%, rgba(247, 251, 255, 0.6) 75%, transparent 100%),
                radial-gradient(circle at 10% 10%, #EAF4FF 0%, rgba(247, 251, 255, 0.5) 50%, transparent 75%),
                radial-gradient(circle at 90% 90%, #E3EFFF 0%, rgba(247, 251, 255, 0.5) 50%, transparent 75%),
                linear-gradient(135deg, #F0F6FD 0%, #FFFFFF 45%, #F7FBFF 75%, #EDF5FE 100%);
            background-attachment: fixed;
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            color: #0F172A;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow-x: hidden;
            padding: 2rem 1.5rem;
        }

        /* Ambient Wave Shapes (Graceful Corner Framing) */
        .bg-wave-tl {
            position: fixed;
            top: 0;
            left: 0;
            width: clamp(320px, 34vw, 520px);
            height: clamp(320px, 34vw, 520px);
            pointer-events: none;
            z-index: 0;
            filter: drop-shadow(0 10px 25px rgba(37, 99, 235, 0.05));
        }

        .bg-wave-br {
            position: fixed;
            bottom: 0;
            right: 0;
            width: clamp(360px, 38vw, 600px);
            height: clamp(360px, 38vw, 600px);
            pointer-events: none;
            z-index: 0;
            filter: drop-shadow(-8px -10px 30px rgba(36, 89, 211, 0.06));
        }

        /* 3-Column Page Shell */
        .page-container {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 1320px;
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            align-items: center;
            gap: 2.5rem;
        }

        /* Left Branding Column */
        .left-branding {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            justify-content: center;
            width: 100%;
            max-width: 380px;
            margin: 0 auto;
        }

        .brand-logo-img {
            max-width: 300px;
            width: 100%;
            height: auto;
            display: block;
            margin: 0 auto 1.25rem;
            filter: drop-shadow(0 4px 12px rgba(15, 46, 92, 0.08));
        }

        .brand-tagline {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            font-size: 0.9375rem;
            font-weight: 500;
            color: #4D7EB8;
            letter-spacing: 0.015em;
            white-space: nowrap;
            line-height: 1.4;
            text-align: center;
        }

        .brand-tagline .tagline-dot {
            color: #60A5FA;
            font-size: 0.875rem;
            line-height: 1;
            display: inline-block;
        }

        /* Center Verification Card */
        .center-card-wrapper {
            display: flex;
            justify-content: center;
        }

        .verification-card {
            background: #FFFFFF;
            border-radius: 28px;
            box-shadow: 0 20px 50px -12px rgba(36, 89, 211, 0.12), 0 0 35px rgba(255, 255, 255, 0.95), 0 0 0 1px rgba(207, 229, 255, 0.7);
            max-width: 480px;
            width: 100%;
            padding: 2.5rem 2.25rem 2rem;
            position: relative;
            overflow: hidden;
        }

        /* Subtle Corner Accent inside Card (Upper-Right only) */
        .card-corner-tr {
            position: absolute;
            top: 0;
            right: 0;
            width: 95px;
            height: 95px;
            pointer-events: none;
            z-index: 1;
            border-top-right-radius: 28px;
            overflow: hidden;
        }

        .card-inner {
            position: relative;
            z-index: 2;
        }

        /* Top Padlock Badge */
        .lock-badge {
            width: 58px;
            height: 58px;
            margin: 0 auto 1.15rem;
            border-radius: 18px;
            background: linear-gradient(145deg, #3B82F6 0%, #1D4ED8 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 22px -4px rgba(37, 99, 235, 0.45);
        }

        .card-title {
            font-size: 1.55rem;
            font-weight: 800;
            color: #0F172A;
            text-align: center;
            margin-bottom: 0.35rem;
            letter-spacing: -0.02em;
        }

        .card-subtitle {
            font-size: 0.875rem;
            color: #64748B;
            text-align: center;
            margin-bottom: 0.85rem;
            font-weight: 500;
        }

        /* Masked Email Chip */
        .email-chip-wrapper {
            text-align: center;
            margin-bottom: 1.25rem;
        }

        .email-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            background: #EFF6FF;
            border: 1px solid #DBEAFE;
            border-radius: 9999px;
            padding: 0.35rem 0.95rem;
            font-size: 0.8125rem;
            font-weight: 600;
            color: #1E40AF;
        }

        /* Feedback Alerts */
        .alert-banner {
            border-radius: 14px;
            padding: 0.8rem 1rem;
            margin-bottom: 1.35rem;
            font-size: 0.8125rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            line-height: 1.45;
        }

        .alert-success {
            background-color: #E6FBF0;
            border: 1px solid #A7F3D0;
            color: #065F46;
        }

        .alert-error {
            background-color: #FEF2F2;
            border: 1px solid #FECACA;
            color: #991B1B;
        }

        .status-circle-success {
            width: 22px;
            height: 22px;
            border-radius: 50%;
            background: #10B981;
            color: #FFFFFF;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 800;
            flex-shrink: 0;
        }

        .status-circle-error {
            width: 22px;
            height: 22px;
            border-radius: 50%;
            background: #EF4444;
            color: #FFFFFF;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 800;
            flex-shrink: 0;
        }

        /* OTP Code Form */
        .otp-section-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 700;
            color: #0F172A;
            text-align: center;
            margin-bottom: 0.85rem;
        }

        .otp-boxes-group {
            display: flex;
            justify-content: center;
            gap: 0.65rem;
            margin-bottom: 0.85rem;
        }

        .otp-box-digit {
            width: 52px;
            height: 58px;
            border: 1.5px solid #BFDBFE;
            border-radius: 12px;
            background: #FFFFFF;
            font-size: 1.65rem;
            font-weight: 800;
            color: #0F2E5C;
            text-align: center;
            font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
            transition: all 0.15s ease-in-out;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02);
            -webkit-appearance: none;
            margin: 0;
        }

        .otp-box-digit:focus {
            outline: none;
            border-color: #2563EB;
            background: #FFFFFF;
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.15);
            transform: translateY(-1px);
        }

        .otp-expiry-meta {
            text-align: center;
            font-size: 0.78125rem;
            color: #64748B;
            margin-bottom: 1.35rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.35rem;
        }

        .otp-expiry-meta strong {
            color: #2563EB;
            font-weight: 700;
        }

        /* Primary Verify Button */
        .btn-verify {
            width: 100%;
            padding: 0.875rem 1.25rem;
            background: linear-gradient(180deg, #1D63ED 0%, #0D4BC3 100%);
            color: #FFFFFF;
            border: none;
            border-radius: 12px;
            font-size: 0.9375rem;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.6rem;
            box-shadow: 0 8px 18px -2px rgba(13, 75, 195, 0.35);
            transition: all 0.2s ease;
        }

        .btn-verify:hover:not(:disabled) {
            background: linear-gradient(180deg, #256CF0 0%, #1052CE 100%);
            transform: translateY(-1px);
            box-shadow: 0 12px 24px -2px rgba(13, 75, 195, 0.45);
        }

        .btn-verify:disabled {
            background: #94A3B8;
            box-shadow: none;
            cursor: not-allowed;
            transform: none;
        }

        /* Secondary Actions Row */
        .secondary-actions-row {
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            align-items: center;
            margin-top: 1.25rem;
            padding-top: 0.85rem;
        }

        .secondary-action-divider {
            width: 1px;
            height: 22px;
            background: #E2E8F0;
        }

        .btn-secondary-action {
            background: none;
            border: none;
            color: #2563EB;
            font-size: 0.8125rem;
            font-weight: 600;
            cursor: pointer;
            padding: 0.35rem 0.5rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.35rem;
            text-decoration: none;
            transition: color 0.15s ease;
            font-family: inherit;
        }

        .btn-secondary-action:hover:not(:disabled) {
            color: #1D4ED8;
            text-decoration: underline;
        }

        .btn-secondary-action:disabled {
            color: #94A3B8;
            cursor: not-allowed;
            text-decoration: none;
        }

        /* Bottom Guard Strip inside Card */
        .card-footer-strip {
            background: #F8FAFC;
            border-top: 1px solid #F1F5F9;
            padding: 0.85rem 1.25rem;
            margin: 1.5rem -2.25rem -2rem;
            text-align: center;
            font-size: 0.6875rem;
            color: #64748B;
            line-height: 1.45;
        }

        /* Right Security Graphic & Handwritten Script */
        .right-security-art {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            position: relative;
            max-width: 320px;
            margin: 0 auto;
        }

        .orbital-container {
            position: relative;
            width: 280px;
            height: 280px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .security-cursive-badge {
            margin-top: 0.65rem;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            transform: rotate(-4deg);
        }

        .cursive-text {
            font-family: 'Caveat', cursive;
            font-size: 2.15rem;
            font-weight: 700;
            color: #2459D3;
            line-height: 1.05;
            letter-spacing: -0.01em;
            text-shadow: 0 2px 6px rgba(36, 89, 211, 0.08);
            text-align: center;
        }

        .cursive-underline {
            display: block;
            margin-top: 6px;
        }

        /* Responsive Breakpoints */
        @media (max-width: 1140px) {
            .page-container {
                gap: 1.5rem;
            }

            .left-branding {
                max-width: 300px;
            }

            .brand-logo-img {
                max-width: 240px;
            }

            .verification-card {
                max-width: 440px;
                padding: 2.25rem 1.85rem 1.85rem;
            }

            .orbital-container {
                width: 240px;
                height: 240px;
            }

            .cursive-text {
                font-size: 1.95rem;
            }
        }

        @media (max-width: 960px) {
            .page-container {
                grid-template-columns: 1fr;
                gap: 1.5rem;
                justify-items: center;
            }

            .left-branding {
                text-align: center;
                align-items: center;
                max-width: 380px;
                margin: 0 auto;
            }

            .brand-logo-img {
                max-width: 220px;
                margin-bottom: 0.75rem;
            }

            .brand-tagline {
                font-size: 0.875rem;
                gap: 0.5rem;
            }

            .bg-wave-tl {
                width: 260px;
                height: 260px;
            }

            .bg-wave-br {
                width: 300px;
                height: 300px;
            }

            .right-security-art {
                display: none;
            }
        }

        @media (max-width: 480px) {
            body {
                padding: 1rem 0.75rem;
            }

            .bg-wave-tl {
                width: 190px;
                height: 190px;
            }

            .bg-wave-br {
                width: 220px;
                height: 220px;
            }

            .card-corner-tr {
                width: 75px;
                height: 75px;
                border-top-right-radius: 20px;
            }

            .brand-tagline {
                font-size: 0.8125rem;
                gap: 0.45rem;
                flex-wrap: wrap;
            }

            .verification-card {
                padding: 1.75rem 1.15rem 1.5rem;
                border-radius: 20px;
            }

            .card-footer-strip {
                margin: 1.25rem -1.15rem -1.5rem;
                padding: 0.75rem 0.85rem;
            }

            .otp-boxes-group {
                gap: 0.35rem;
            }

            .otp-box-digit {
                width: 42px;
                height: 50px;
                font-size: 1.35rem;
                border-radius: 9px;
            }

            .card-title {
                font-size: 1.3rem;
            }
        }

        @media (max-width: 360px) {
            body {
                padding: 0.75rem 0.5rem;
            }

            .verification-card {
                padding: 1.5rem 0.85rem 1.25rem;
                border-radius: 18px;
            }

            .card-footer-strip {
                margin: 1rem -0.85rem -1.25rem;
                padding: 0.65rem 0.5rem;
                font-size: 0.625rem;
            }

            .otp-boxes-group {
                gap: 0.25rem;
            }

            .otp-box-digit {
                width: 38px;
                height: 46px;
                font-size: 1.2rem;
                border-radius: 8px;
            }

            .btn-secondary-action {
                font-size: 0.75rem;
                padding: 0.25rem 0.35rem;
            }
        }

        /* Animation utilities */
        .spinner {
            animation: spin 0.9s linear infinite;
        }

        @keyframes spin {
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>

    <!-- Ambient Waves in Viewport Corners -->
    <div class="bg-wave-tl" aria-hidden="true">
        <svg viewBox="0 0 550 550" width="100%" height="100%" fill="none" preserveAspectRatio="none">
            <!-- Layer 1: Soft broad translucent pale-blue wave -->
            <path d="M 0 0 H 520 C 440 180, 280 380, 0 460 Z" fill="url(#bgWaveGradTL1)" />
            <!-- Layer 2: Mid azure flowing wave -->
            <path d="M 0 0 H 380 C 310 140, 190 280, 0 350 Z" fill="url(#bgWaveGradTL2)" />
            <!-- Layer 3: Smooth primary corner curve -->
            <path d="M 0 0 H 250 C 200 90, 110 180, 0 230 Z" fill="url(#bgWaveGradTL3)" />
            <!-- Soft crest line highlight -->
            <path d="M 10 225 C 105 175, 195 85, 245 10" stroke="rgba(255, 255, 255, 0.55)" stroke-width="2" stroke-linecap="round" fill="none" />
            <defs>
                <linearGradient id="bgWaveGradTL1" x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%" stop-color="#CFE5FF" stop-opacity="0.45" />
                    <stop offset="60%" stop-color="#EAF4FF" stop-opacity="0.2" />
                    <stop offset="100%" stop-color="#F7FBFF" stop-opacity="0.02" />
                </linearGradient>
                <linearGradient id="bgWaveGradTL2" x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%" stop-color="#3B82F6" stop-opacity="0.4" />
                    <stop offset="50%" stop-color="#93C5FD" stop-opacity="0.22" />
                    <stop offset="100%" stop-color="#EAF4FF" stop-opacity="0.04" />
                </linearGradient>
                <linearGradient id="bgWaveGradTL3" x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%" stop-color="#2459D3" stop-opacity="0.75" />
                    <stop offset="45%" stop-color="#3B82F6" stop-opacity="0.5" />
                    <stop offset="90%" stop-color="#60A5FA" stop-opacity="0.2" />
                    <stop offset="100%" stop-color="#CFE5FF" stop-opacity="0.05" />
                </linearGradient>
            </defs>
        </svg>
    </div>

    <div class="bg-wave-br" aria-hidden="true">
        <svg viewBox="0 0 600 600" width="100%" height="100%" fill="none" preserveAspectRatio="none">
            <!-- Layer 1: Inner gentle pale-blue flowing curve -->
            <path d="M 600 600 V 220 C 510 320, 360 440, 180 600 Z" fill="url(#bgWaveGradBR1)" />
            <!-- Layer 2: Mid flowing azure curve -->
            <path d="M 600 600 V 330 C 520 400, 420 490, 290 600 Z" fill="url(#bgWaveGradBR2)" />
            <!-- Layer 3: Outer primary blue curve near corner -->
            <path d="M 600 600 V 440 C 540 480, 480 540, 390 600 Z" fill="url(#bgWaveGradBR3)" />
            <!-- Flowing subtle crest highlight -->
            <path d="M 395 595 C 482 535, 538 478, 595 435" stroke="rgba(255, 255, 255, 0.6)" stroke-width="2" stroke-linecap="round" fill="none" />
            <defs>
                <linearGradient id="bgWaveGradBR1" x1="100%" y1="100%" x2="0%" y2="0%">
                    <stop offset="0%" stop-color="#93C5FD" stop-opacity="0.32" />
                    <stop offset="60%" stop-color="#CFE5FF" stop-opacity="0.15" />
                    <stop offset="100%" stop-color="#EAF4FF" stop-opacity="0.02" />
                </linearGradient>
                <linearGradient id="bgWaveGradBR2" x1="100%" y1="100%" x2="0%" y2="0%">
                    <stop offset="0%" stop-color="#3B82F6" stop-opacity="0.45" />
                    <stop offset="50%" stop-color="#60A5FA" stop-opacity="0.25" />
                    <stop offset="100%" stop-color="#CFE5FF" stop-opacity="0.06" />
                </linearGradient>
                <linearGradient id="bgWaveGradBR3" x1="100%" y1="100%" x2="0%" y2="0%">
                    <stop offset="0%" stop-color="#2459D3" stop-opacity="0.75" />
                    <stop offset="40%" stop-color="#3B82F6" stop-opacity="0.55" />
                    <stop offset="85%" stop-color="#60A5FA" stop-opacity="0.25" />
                    <stop offset="100%" stop-color="#CFE5FF" stop-opacity="0.06" />
                </linearGradient>
            </defs>
        </svg>
    </div>

    <!-- 3-Column Page Shell -->
    <div class="page-container">

        <!-- Column 1: Left Branding -->
        <aside class="left-branding" aria-label="Brand Information">
            <img src="<?= asset('img/hris-logo.png') ?>" alt="HRIS - Human Resource Information System" class="brand-logo-img">
            <div class="brand-tagline">
                <span>Secure People</span>
                <span class="tagline-dot" aria-hidden="true">&bull;</span>
                <span>Smarter HR</span>
                <span class="tagline-dot" aria-hidden="true">&bull;</span>
                <span>Better Tomorrow</span>
            </div>
        </aside>

        <!-- Column 2: Center 2FA Verification Card -->
        <main class="center-card-wrapper" role="main">
            <div class="verification-card">
                
                <!-- Subtle Fluid Corner Accent inside the card (Upper-Right only) -->
                <div class="card-corner-tr" aria-hidden="true">
                    <svg viewBox="0 0 95 95" width="95" height="95" fill="none">
                        <!-- Translucent underlayer -->
                        <path d="M 95 0 C 50 16, 20 52, 10 95 H 95 V 0 Z" fill="url(#cornerGradTRSubtle)" />
                        <!-- Main gentle curve -->
                        <path d="M 95 0 C 65 10, 30 42, 20 95 H 95 V 0 Z" fill="url(#cornerGradTR)" />
                        <!-- Soft crest line highlight -->
                        <path d="M 21 95 C 31 43, 66 11, 95 0" stroke="rgba(255, 255, 255, 0.5)" stroke-width="1.2" fill="none" />
                        <defs>
                            <linearGradient id="cornerGradTR" x1="1" y1="0" x2="0" y2="1">
                                <stop offset="0%" stop-color="#3B82F6" stop-opacity="0.38" />
                                <stop offset="50%" stop-color="#60A5FA" stop-opacity="0.20" />
                                <stop offset="100%" stop-color="#CFE5FF" stop-opacity="0.04" />
                            </linearGradient>
                            <linearGradient id="cornerGradTRSubtle" x1="1" y1="0" x2="0" y2="1">
                                <stop offset="0%" stop-color="#BFDBFE" stop-opacity="0.25" />
                                <stop offset="70%" stop-color="#EAF4FF" stop-opacity="0.08" />
                                <stop offset="100%" stop-color="#F7FBFF" stop-opacity="0.02" />
                            </linearGradient>
                        </defs>
                    </svg>
                </div>

                <div class="card-inner">
                    <!-- Top Blue Lock Badge -->
                    <div class="lock-badge" aria-hidden="true">
                        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <rect x="4" y="10" width="16" height="12" rx="3" fill="#FFFFFF" />
                            <path d="M8 10V7a4 4 0 1 1 8 0v3" stroke="#FFFFFF" stroke-width="2.5" stroke-linecap="round" />
                            <circle cx="12" cy="15.5" r="1.5" fill="#1D4ED8" />
                            <path d="M12 17v2" stroke="#1D4ED8" stroke-width="1.5" stroke-linecap="round" />
                        </svg>
                    </div>

                    <!-- Title & Subtitle -->
                    <h1 class="card-title">Two-Factor Verification</h1>
                    <p class="card-subtitle">Enter the 6-digit verification code sent to your email</p>

                    <!-- Masked Email Chip -->
                    <?php if (!empty($maskedEmail)): ?>
                        <div class="email-chip-wrapper">
                            <div class="email-chip">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#2563EB" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="2" y="4" width="20" height="16" rx="2" />
                                    <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7" />
                                </svg>
                                <span><?= e($maskedEmail) ?></span>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Feedback Alert Banners -->
                    <?php if (!empty($error)): ?>
                        <div class="alert-banner alert-error" role="alert">
                            <span class="status-circle-error" aria-hidden="true">!</span>
                            <div><?= e($error) ?></div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($success)): ?>
                        <div class="alert-banner alert-success" role="alert">
                            <span class="status-circle-success" aria-hidden="true">&#10003;</span>
                            <div><?= e($success) ?></div>
                        </div>
                    <?php endif; ?>

                    <!-- OTP Verification Form -->
                    <form method="POST" action="<?= url('/verify-otp') ?>" id="otpVerifyForm" autocomplete="off">
                        <?= csrf_field() ?>

                        <label class="otp-section-label" for="otp_digit_0">Verification Code (OTP)</label>

                        <!-- 6 Separate Digit Boxes -->
                        <div class="otp-boxes-group" id="otpBoxesGroup">
                            <input type="text"
                                   id="otp_digit_0"
                                   class="otp-box-digit"
                                   maxlength="1"
                                   inputmode="numeric"
                                   pattern="[0-9]"
                                   autocomplete="one-time-code"
                                   data-index="0"
                                   aria-label="Digit 1 of verification code"
                                   autofocus>
                            <input type="text"
                                   id="otp_digit_1"
                                   class="otp-box-digit"
                                   maxlength="1"
                                   inputmode="numeric"
                                   pattern="[0-9]"
                                   data-index="1"
                                   aria-label="Digit 2 of verification code">
                            <input type="text"
                                   id="otp_digit_2"
                                   class="otp-box-digit"
                                   maxlength="1"
                                   inputmode="numeric"
                                   pattern="[0-9]"
                                   data-index="2"
                                   aria-label="Digit 3 of verification code">
                            <input type="text"
                                   id="otp_digit_3"
                                   class="otp-box-digit"
                                   maxlength="1"
                                   inputmode="numeric"
                                   pattern="[0-9]"
                                   data-index="3"
                                   aria-label="Digit 4 of verification code">
                            <input type="text"
                                   id="otp_digit_4"
                                   class="otp-box-digit"
                                   maxlength="1"
                                   inputmode="numeric"
                                   pattern="[0-9]"
                                   data-index="4"
                                   aria-label="Digit 5 of verification code">
                            <input type="text"
                                   id="otp_digit_5"
                                   class="otp-box-digit"
                                   maxlength="1"
                                   inputmode="numeric"
                                   pattern="[0-9]"
                                   data-index="5"
                                   aria-label="Digit 6 of verification code">
                        </div>

                        <!-- Hidden Canonical Field Submitted to POST /verify-otp -->
                        <input type="hidden" name="otp" id="otpFullValue" value="">

                        <!-- Expiry and Attempts Info -->
                        <div class="otp-expiry-meta">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#64748B" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10" />
                                <polyline points="12 6 12 12 16 14" />
                            </svg>
                            <span>Code expires in <strong>5 minutes</strong> &bull; 5 attempts maximum</span>
                        </div>

                        <!-- Primary Submit Button -->
                        <button type="submit" id="verifySubmitBtn" class="btn-verify">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#FFFFFF" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                                <path d="m9 12 2 2 4-4" />
                            </svg>
                            <span>Verify &amp; Sign In</span>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#FFFFFF" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
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
                            <button type="submit" id="resendBtn" class="btn-secondary-action" <?= ($resendCooldownRemaining ?? 0) > 0 ? 'disabled' : '' ?>>
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M21.5 2v6h-6M2.5 22v-6h6M2 11.5a10 10 0 0 1 18.8-4.3M22 12.5a10 10 0 0 1-18.8 4.2" />
                                </svg>
                                <span>Resend Code <span id="cooldownTimer"><?= ($resendCooldownRemaining ?? 0) > 0 ? '(' . (int)$resendCooldownRemaining . 's)' : '' ?></span></span>
                            </button>
                        </form>

                        <!-- Hairline Divider -->
                        <div class="secondary-action-divider" aria-hidden="true"></div>

                        <!-- Right: Back to Sign In Form -->
                        <form method="POST" action="<?= url('/verify-otp/cancel') ?>" id="cancelForm" style="margin: 0; text-align: center;">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn-secondary-action">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <line x1="19" y1="12" x2="5" y2="12" />
                                    <polyline points="12 19 5 12 12 5" />
                                </svg>
                                <span>Back to Sign In</span>
                            </button>
                        </form>
                    </div>

                    <!-- Card Bottom Guard Disclaimer -->
                    <div class="card-footer-strip">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="#2563EB" xmlns="http://www.w3.org/2000/svg" style="vertical-align: -2px; margin-right: 3px;">
                            <path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm-2 16l-4-4 1.41-1.41L10 14.17l6.59-6.59L18 9l-8 8z"/>
                        </svg>
                        HRIS Security Guard &bull; Passwords are protected via Email OTP<br>
                        Never share your one-time password with anyone.
                    </div>
                </div>
            </div>
        </main>

        <!-- Column 3: Right Security Graphic & Handwritten Script -->
        <aside class="right-security-art" aria-hidden="true">
            <div class="orbital-container">
                <svg width="280" height="280" viewBox="0 0 320 320" fill="none">
                    <!-- Concentric Dotted & Solid Orbital Rings centered at (160, 160) -->
                    <circle cx="160" cy="160" r="130" stroke="#93C5FD" stroke-width="1.5" stroke-dasharray="5 7" opacity="0.65" />
                    <circle cx="160" cy="160" r="90" stroke="#BFDBFE" stroke-width="1.5" opacity="0.7" />

                    <!-- Decorative Orbital Accent Dots -->
                    <circle cx="252" cy="68" r="3.5" fill="#3B82F6" opacity="0.75" />
                    <circle cx="47" cy="225" r="3" fill="#60A5FA" opacity="0.7" />
                    <circle cx="224" cy="224" r="3" fill="#2459D3" opacity="0.75" />

                    <!-- Centered Pale-Blue Translucent Security Shield (Centered at 160, 160) -->
                    <path d="M 160 111 L 125 126 V 164 C 125 192 160 211 160 211 C 160 211 195 192 195 164 V 126 Z" fill="url(#shieldTransGrad)" stroke="#93C5FD" stroke-width="2.5" stroke-linejoin="round" />

                    <!-- Centered Recognizable Blue Padlock Icon inside Shield -->
                    <rect x="146" y="152" width="28" height="22" rx="4.5" fill="#2459D3" />
                    <path d="M 152 152 V 142 C 152 136 156 132 160 132 C 164 132 168 136 168 142 V 152" stroke="#2459D3" stroke-width="3.5" stroke-linecap="round" fill="none" />
                    <circle cx="160" cy="161" r="2.2" fill="#FFFFFF" />
                    <path d="M 160 163.2 V 167.5" stroke="#FFFFFF" stroke-width="1.8" stroke-linecap="round" />

                    <defs>
                        <linearGradient id="shieldTransGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                            <stop offset="0%" stop-color="#EAF4FF" stop-opacity="0.8" />
                            <stop offset="100%" stop-color="#CFE5FF" stop-opacity="0.45" />
                        </linearGradient>
                    </defs>
                </svg>
            </div>

            <!-- "Your Security Matters" in Authentic Cursive Style -->
            <div class="security-cursive-badge">
                <div class="cursive-text">Your Security<br>Matters</div>
                <svg class="cursive-underline" width="130" height="14" viewBox="0 0 130 14" fill="none">
                    <path d="M 4 8 C 42 14, 88 2, 126 8" stroke="#3B82F6" stroke-width="2.5" stroke-linecap="round" />
                </svg>
            </div>
        </aside>

    </div>

    <!-- Interactive Script for 6-Box OTP UX & Cooldown Countdown -->
    <script>
        (function() {
            const otpDigits = document.querySelectorAll('.otp-box-digit');
            const otpHidden = document.getElementById('otpFullValue');
            const verifyForm = document.getElementById('otpVerifyForm');
            const verifySubmitBtn = document.getElementById('verifySubmitBtn');

            function syncOtpHidden() {
                let fullCode = '';
                otpDigits.forEach(input => {
                    fullCode += input.value;
                });
                if (otpHidden) {
                    otpHidden.value = fullCode;
                }
                return fullCode;
            }

            function triggerSubmit() {
                if (verifySubmitBtn && !verifySubmitBtn.disabled) {
                    verifySubmitBtn.disabled = true;
                    verifySubmitBtn.innerHTML = `
                        <svg class="spinner" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                            <circle cx="12" cy="12" r="10" stroke-opacity="0.25"/>
                            <path d="M12 2a10 10 0 0 1 10 10" />
                        </svg>
                        <span>Verifying...</span>
                    `;
                }
                if (verifyForm) {
                    if (typeof verifyForm.requestSubmit === 'function') {
                        verifyForm.requestSubmit();
                    } else {
                        verifyForm.submit();
                    }
                }
            }

            // Keyboard navigation, digit restriction, and auto-focus next
            otpDigits.forEach((input, index) => {
                input.addEventListener('input', function(e) {
                    const cleaned = this.value.replace(/[^0-9]/g, '');
                    this.value = cleaned ? cleaned[cleaned.length - 1] : '';

                    const fullCode = syncOtpHidden();

                    if (this.value && index < otpDigits.length - 1) {
                        otpDigits[index + 1].focus();
                    }

                    if (fullCode.length === 6) {
                        triggerSubmit();
                    }
                });

                input.addEventListener('keydown', function(e) {
                    if (e.key === 'Backspace') {
                        if (!this.value && index > 0) {
                            otpDigits[index - 1].focus();
                            otpDigits[index - 1].value = '';
                            syncOtpHidden();
                        }
                    } else if (e.key === 'ArrowLeft' && index > 0) {
                        e.preventDefault();
                        otpDigits[index - 1].focus();
                    } else if (e.key === 'ArrowRight' && index < otpDigits.length - 1) {
                        e.preventDefault();
                        otpDigits[index + 1].focus();
                    }
                });

                // Seamless paste support (handles copying 6 digits from email)
                input.addEventListener('paste', function(e) {
                    e.preventDefault();
                    const clipboardText = (e.clipboardData || window.clipboardData).getData('text');
                    const digits = clipboardText.replace(/[^0-9]/g, '').slice(0, 6);

                    if (!digits) return;

                    digits.split('').forEach((char, i) => {
                        if (otpDigits[i]) {
                            otpDigits[i].value = char;
                        }
                    });

                    const fullCode = syncOtpHidden();
                    const nextFocusIndex = Math.min(digits.length, otpDigits.length - 1);
                    otpDigits[nextFocusIndex].focus();

                    if (fullCode.length === 6) {
                        triggerSubmit();
                    }
                });
            });

            // Standard submit listener
            if (verifyForm) {
                verifyForm.addEventListener('submit', function(e) {
                    const fullCode = syncOtpHidden();
                    if (fullCode.length !== 6) {
                        e.preventDefault();
                        // Find first empty box and focus
                        for (let i = 0; i < otpDigits.length; i++) {
                            if (!otpDigits[i].value) {
                                otpDigits[i].focus();
                                break;
                            }
                        }
                        return false;
                    }
                    if (verifySubmitBtn) {
                        verifySubmitBtn.disabled = true;
                        verifySubmitBtn.innerHTML = `
                            <svg class="spinner" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                                <circle cx="12" cy="12" r="10" stroke-opacity="0.25"/>
                                <path d="M12 2a10 10 0 0 1 10 10" />
                            </svg>
                            <span>Verifying...</span>
                        `;
                    }
                });
            }

            // Resend Cooldown Countdown Timer
            let cooldownSeconds = <?= (int)($resendCooldownRemaining ?? 0) ?>;
            const resendBtn = document.getElementById('resendBtn');
            const timerSpan = document.getElementById('cooldownTimer');

            if (cooldownSeconds > 0 && resendBtn && timerSpan) {
                resendBtn.disabled = true;
                const interval = setInterval(function() {
                    cooldownSeconds--;
                    if (cooldownSeconds <= 0) {
                        clearInterval(interval);
                        resendBtn.disabled = false;
                        timerSpan.textContent = '';
                    } else {
                        timerSpan.textContent = '(' + cooldownSeconds + 's)';
                    }
                }, 1000);
            }
        })();
    </script>
</body>
</html>
