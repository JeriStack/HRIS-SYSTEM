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
            background-color: #F0F6FD;
            background-image: 
                radial-gradient(ellipse 70% 60% at 50% 48%, #FFFFFF 15%, rgba(255, 255, 255, 0.85) 45%, rgba(240, 246, 253, 0.4) 75%, transparent 100%),
                radial-gradient(circle at 8% 12%, #DCEBFC 0%, #EBF4FE 35%, transparent 65%),
                radial-gradient(circle at 92% 88%, #CFE4FB 0%, #E2F0FE 30%, transparent 65%),
                linear-gradient(135deg, #E6F0FA 0%, #F4F9FD 50%, #E8F2FB 100%);
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

        /* Ambient Wave Shapes */
        .bg-wave-tl {
            position: fixed;
            top: 0;
            left: 0;
            width: clamp(420px, 45vw, 680px);
            height: clamp(420px, 45vw, 680px);
            pointer-events: none;
            z-index: 0;
            filter: drop-shadow(0 15px 35px rgba(37, 99, 235, 0.08));
        }

        .bg-wave-br {
            position: fixed;
            bottom: 0;
            right: 0;
            width: clamp(480px, 50vw, 820px);
            height: clamp(480px, 50vw, 820px);
            pointer-events: none;
            z-index: 0;
            filter: drop-shadow(-10px -15px 40px rgba(29, 78, 216, 0.12));
        }

        .bg-wave-bl {
            position: fixed;
            bottom: 0;
            left: 0;
            width: clamp(340px, 32vw, 520px);
            height: clamp(260px, 26vw, 420px);
            pointer-events: none;
            z-index: 0;
            opacity: 0.85;
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
            box-shadow: 0 25px 60px -15px rgba(23, 76, 155, 0.16), 0 0 1px 1px rgba(219, 234, 254, 0.8);
            max-width: 480px;
            width: 100%;
            padding: 2.5rem 2.25rem 2rem;
            position: relative;
            overflow: hidden;
        }

        /* Decorative Fluid Corner Accents inside the Card */
        .card-corner-tr {
            position: absolute;
            top: 0;
            right: 0;
            width: 155px;
            height: 155px;
            pointer-events: none;
            z-index: 0;
            filter: drop-shadow(-2px 4px 10px rgba(29, 78, 216, 0.15));
        }

        .card-corner-bl {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 140px;
            height: 140px;
            pointer-events: none;
            z-index: 0;
            filter: drop-shadow(2px -4px 10px rgba(30, 58, 138, 0.12));
        }

        .card-inner {
            position: relative;
            z-index: 1;
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
            align-items: flex-start;
            padding-left: 1rem;
            position: relative;
        }

        .orbital-container {
            position: relative;
            width: 320px;
            height: 320px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .security-cursive-badge {
            margin-top: -20px;
            margin-left: 60px;
            transform: rotate(-7deg);
            display: inline-block;
        }

        .cursive-text {
            font-family: 'Caveat', cursive;
            font-size: 2.15rem;
            font-weight: 700;
            color: #2563EB;
            line-height: 0.95;
            letter-spacing: -0.01em;
            text-shadow: 0 2px 8px rgba(37, 99, 235, 0.12);
        }

        .cursive-underline {
            display: block;
            margin-top: 2px;
        }

        /* Responsive Breakpoints */
        @media (max-width: 1080px) {
            .page-container {
                grid-template-columns: 1fr;
                gap: 1.5rem;
                justify-items: center;
            }

            .left-branding {
                text-align: center;
                align-items: center;
                padding-right: 0;
            }

            .brand-logo-img {
                max-width: 240px;
                margin-bottom: 0.85rem;
            }

            .brand-tagline {
                font-size: 0.875rem;
                gap: 0.5rem;
            }

            .bg-wave-tl {
                width: 320px;
                height: 320px;
            }

            .bg-wave-br {
                width: 360px;
                height: 360px;
            }

            .bg-wave-bl {
                width: 260px;
                height: 200px;
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
                width: 220px;
                height: 220px;
            }

            .bg-wave-br {
                width: 260px;
                height: 260px;
            }

            .bg-wave-bl {
                display: none;
            }

            .card-corner-tr {
                width: 115px;
                height: 115px;
            }

            .card-corner-bl {
                width: 100px;
                height: 100px;
            }

            .brand-tagline {
                font-size: 0.8125rem;
                gap: 0.45rem;
                flex-wrap: wrap;
            }

            .verification-card {
                padding: 1.75rem 1.25rem 1.5rem;
                border-radius: 22px;
            }

            .card-footer-strip {
                margin: 1.25rem -1.25rem -1.5rem;
                padding: 0.75rem 1rem;
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
        <svg viewBox="0 0 650 650" width="100%" height="100%" fill="none" preserveAspectRatio="none">
            <!-- Layer 1: Soft broad translucent ice-blue wave -->
            <path d="M0 0 H 600 C 510 220, 330 460, 0 570 Z" fill="url(#bgWaveGradTL1)" />
            <!-- Layer 2: Mid azure flowing wave -->
            <path d="M0 0 H 450 C 380 170, 240 360, 0 440 Z" fill="url(#bgWaveGradTL2)" />
            <!-- Layer 3: Vibrant enterprise cobalt wave -->
            <path d="M0 0 H 310 C 260 115, 145 245, 0 300 Z" fill="url(#bgWaveGradTL3)" />
            <!-- Layer 4: Flowing geometric accent curve -->
            <path d="M 12 295 C 150 240, 260 110, 305 12" stroke="url(#bgWaveTLStroke)" stroke-width="2.5" stroke-linecap="round" fill="none" opacity="0.45" />
            <defs>
                <linearGradient id="bgWaveGradTL1" x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%" stop-color="#93C5FD" stop-opacity="0.4" />
                    <stop offset="60%" stop-color="#BFDBFE" stop-opacity="0.2" />
                    <stop offset="100%" stop-color="#DBEAFE" stop-opacity="0.04" />
                </linearGradient>
                <linearGradient id="bgWaveGradTL2" x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%" stop-color="#60A5FA" stop-opacity="0.65" />
                    <stop offset="50%" stop-color="#93C5FD" stop-opacity="0.35" />
                    <stop offset="100%" stop-color="#DBEAFE" stop-opacity="0.08" />
                </linearGradient>
                <linearGradient id="bgWaveGradTL3" x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%" stop-color="#1D4ED8" stop-opacity="0.88" />
                    <stop offset="45%" stop-color="#2563EB" stop-opacity="0.75" />
                    <stop offset="90%" stop-color="#60A5FA" stop-opacity="0.45" />
                    <stop offset="100%" stop-color="#93C5FD" stop-opacity="0.15" />
                </linearGradient>
                <linearGradient id="bgWaveTLStroke" x1="0%" y1="100%" x2="100%" y2="0%">
                    <stop offset="0%" stop-color="#FFFFFF" stop-opacity="0.1" />
                    <stop offset="50%" stop-color="#FFFFFF" stop-opacity="0.75" />
                    <stop offset="100%" stop-color="#FFFFFF" stop-opacity="0.2" />
                </linearGradient>
            </defs>
        </svg>
    </div>

    <div class="bg-wave-br" aria-hidden="true">
        <svg viewBox="0 0 800 800" width="100%" height="100%" fill="none" preserveAspectRatio="none">
            <!-- Layer 1: Oversized ambient sweeping wave reaching towards center -->
            <path d="M 800 800 V 160 C 660 290, 390 490, 90 800 Z" fill="url(#bgWaveGradBR1)" />
            <!-- Layer 2: Secondary flowing azure curve -->
            <path d="M 800 800 V 290 C 680 400, 490 550, 230 800 Z" fill="url(#bgWaveGradBR2)" />
            <!-- Layer 3: Primary deep enterprise cobalt wave -->
            <path d="M 800 800 V 430 C 715 500, 570 630, 370 800 Z" fill="url(#bgWaveGradBR3)" />
            <!-- Layer 4: Flowing geometric accent line -->
            <path d="M 385 790 C 575 620, 720 490, 792 422" stroke="url(#bgWaveBRStroke)" stroke-width="2.5" stroke-linecap="round" fill="none" opacity="0.4" />
            <defs>
                <linearGradient id="bgWaveGradBR1" x1="100%" y1="100%" x2="0%" y2="0%">
                    <stop offset="0%" stop-color="#3B82F6" stop-opacity="0.45" />
                    <stop offset="60%" stop-color="#93C5FD" stop-opacity="0.18" />
                    <stop offset="100%" stop-color="#DBEAFE" stop-opacity="0.02" />
                </linearGradient>
                <linearGradient id="bgWaveGradBR2" x1="100%" y1="100%" x2="0%" y2="0%">
                    <stop offset="0%" stop-color="#1D4ED8" stop-opacity="0.78" />
                    <stop offset="50%" stop-color="#2563EB" stop-opacity="0.55" />
                    <stop offset="100%" stop-color="#60A5FA" stop-opacity="0.18" />
                </linearGradient>
                <linearGradient id="bgWaveGradBR3" x1="100%" y1="100%" x2="0%" y2="0%">
                    <stop offset="0%" stop-color="#1E3A8A" stop-opacity="0.95" />
                    <stop offset="40%" stop-color="#1D4ED8" stop-opacity="0.9" />
                    <stop offset="80%" stop-color="#2563EB" stop-opacity="0.7" />
                    <stop offset="100%" stop-color="#60A5FA" stop-opacity="0.3" />
                </linearGradient>
                <linearGradient id="bgWaveBRStroke" x1="0%" y1="100%" x2="100%" y2="0%">
                    <stop offset="0%" stop-color="#93C5FD" stop-opacity="0.1" />
                    <stop offset="50%" stop-color="#FFFFFF" stop-opacity="0.7" />
                    <stop offset="100%" stop-color="#93C5FD" stop-opacity="0.2" />
                </linearGradient>
            </defs>
        </svg>
    </div>

    <div class="bg-wave-bl" aria-hidden="true">
        <svg viewBox="0 0 520 400" width="100%" height="100%" fill="none" preserveAspectRatio="none">
            <path d="M 0 400 V 150 C 140 190, 330 280, 500 400 Z" fill="url(#bgWaveGradBL1)" />
            <path d="M 0 400 V 230 C 110 250, 250 320, 380 400 Z" fill="url(#bgWaveGradBL2)" />
            <defs>
                <linearGradient id="bgWaveGradBL1" x1="0%" y1="100%" x2="100%" y2="0%">
                    <stop offset="0%" stop-color="#60A5FA" stop-opacity="0.4" />
                    <stop offset="60%" stop-color="#93C5FD" stop-opacity="0.18" />
                    <stop offset="100%" stop-color="#DBEAFE" stop-opacity="0.03" />
                </linearGradient>
                <linearGradient id="bgWaveGradBL2" x1="0%" y1="100%" x2="100%" y2="0%">
                    <stop offset="0%" stop-color="#1D4ED8" stop-opacity="0.82" />
                    <stop offset="50%" stop-color="#2563EB" stop-opacity="0.6" />
                    <stop offset="100%" stop-color="#60A5FA" stop-opacity="0.15" />
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
                
                <!-- Fluid Corner Overlays inside the card -->
                <div class="card-corner-tr" aria-hidden="true">
                    <svg viewBox="0 0 155 155" width="155" height="155" fill="none">
                        <!-- Subtle translucent underlayer for depth -->
                        <path d="M 155 0 C 95 24, 42 80, 32 155 H 155 V 0 Z" fill="url(#cornerGradTRSubtle)" />
                        <!-- Main vibrant corner wave -->
                        <path d="M 155 0 C 112 16, 62 58, 52 155 H 155 V 0 Z" fill="url(#cornerGradTR)" />
                        <!-- Soft crest line highlight -->
                        <path d="M 54 155 C 64 60, 114 18, 155 0" stroke="rgba(255, 255, 255, 0.45)" stroke-width="1.5" fill="none" />
                        <defs>
                            <linearGradient id="cornerGradTR" x1="1" y1="0" x2="0" y2="1">
                                <stop offset="0%" stop-color="#1D4ED8" stop-opacity="0.95" />
                                <stop offset="35%" stop-color="#2563EB" stop-opacity="0.88" />
                                <stop offset="75%" stop-color="#60A5FA" stop-opacity="0.65" />
                                <stop offset="100%" stop-color="#93C5FD" stop-opacity="0.25" />
                            </linearGradient>
                            <linearGradient id="cornerGradTRSubtle" x1="1" y1="0" x2="0" y2="1">
                                <stop offset="0%" stop-color="#60A5FA" stop-opacity="0.45" />
                                <stop offset="60%" stop-color="#93C5FD" stop-opacity="0.2" />
                                <stop offset="100%" stop-color="#DBEAFE" stop-opacity="0.05" />
                            </linearGradient>
                        </defs>
                    </svg>
                </div>

                <div class="card-corner-bl" aria-hidden="true">
                    <svg viewBox="0 0 140 140" width="140" height="140" fill="none">
                        <!-- Subtle underlayer -->
                        <path d="M 0 140 C 50 88, 96 38, 108 0 H 0 V 140 Z" fill="url(#cornerGradBLSubtle)" />
                        <!-- Main vibrant wave -->
                        <path d="M 0 140 C 36 98, 76 52, 86 0 H 0 V 140 Z" fill="url(#cornerGradBL)" />
                        <defs>
                            <linearGradient id="cornerGradBL" x1="0" y1="1" x2="1" y2="0">
                                <stop offset="0%" stop-color="#1E3A8A" stop-opacity="0.95" />
                                <stop offset="40%" stop-color="#1D4ED8" stop-opacity="0.85" />
                                <stop offset="80%" stop-color="#3B82F6" stop-opacity="0.6" />
                                <stop offset="100%" stop-color="#93C5FD" stop-opacity="0.2" />
                            </linearGradient>
                            <linearGradient id="cornerGradBLSubtle" x1="0" y1="1" x2="1" y2="0">
                                <stop offset="0%" stop-color="#3B82F6" stop-opacity="0.4" />
                                <stop offset="70%" stop-color="#93C5FD" stop-opacity="0.15" />
                                <stop offset="100%" stop-color="#DBEAFE" stop-opacity="0.05" />
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
                <svg width="320" height="320" viewBox="0 0 400 400" fill="none">
                    <!-- Concentric Dotted Orbital Rings -->
                    <circle cx="200" cy="200" r="165" stroke="#93C5FD" stroke-width="1.2" stroke-dasharray="4 6" opacity="0.65" />
                    <circle cx="45" cy="160" r="4" fill="#3B82F6" opacity="0.75" />
                    <circle cx="345" cy="140" r="3.5" fill="#60A5FA" opacity="0.7" />
                    
                    <circle cx="200" cy="200" r="115" stroke="#BFDBFE" stroke-width="1.2" opacity="0.6" />
                    <circle cx="295" cy="245" r="3" fill="#2563EB" opacity="0.8" />
                    <circle cx="100" cy="240" r="3" fill="#3B82F6" opacity="0.7" />

                    <!-- Translucent Security Shield -->
                    <g transform="translate(142, 115)">
                        <path d="M58 5 L12 25 V78 C12 118 58 145 58 145 C58 145 104 118 104 78 V25 Z" fill="url(#shieldTransGrad)" stroke="#93C5FD" stroke-width="3" />
                        <!-- Padlock Silhouette inside Shield -->
                        <rect x="42" y="66" width="32" height="26" rx="5" fill="#93C5FD" opacity="0.85" />
                        <path d="M49 66 V53 a9 9 0 0 1 18 0 v13" stroke="#93C5FD" stroke-width="4.5" stroke-linecap="round" fill="none" opacity="0.85" />
                        <circle cx="58" cy="78" r="2.8" fill="#FFFFFF" />
                        <path d="M58 80.8 V85.5" stroke="#FFFFFF" stroke-width="2.2" stroke-linecap="round" />
                    </g>

                    <defs>
                        <linearGradient id="shieldTransGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                            <stop offset="0%" stop-color="#DBEAFE" stop-opacity="0.6" />
                            <stop offset="100%" stop-color="#BFDBFE" stop-opacity="0.25" />
                        </linearGradient>
                    </defs>
                </svg>
            </div>

            <!-- "Your Security Matters" in Authentic Cursive Style -->
            <div class="security-cursive-badge">
                <div class="cursive-text">Your Security<br>Matters</div>
                <svg class="cursive-underline" width="135" height="18" viewBox="0 0 135 18" fill="none">
                    <path d="M3 13 C 40 2, 85 16, 130 5" stroke="#2563EB" stroke-width="2.8" stroke-linecap="round" />
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
