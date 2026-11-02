<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'Two-Factor Authenticator - NEXORA HR') ?></title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

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
                linear-gradient(135deg, #F0F6FD 0%, #FFFFFF 45%, #F7FBFF 75%, #EDF5FE 100%);
            background-attachment: fixed;
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            color: #0F172A;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1.5rem;
        }

        .auth-card {
            background: #FFFFFF;
            border: 1px solid #E2E8F0;
            border-radius: 1.25rem;
            box-shadow: 0 20px 40px -15px rgba(15, 23, 42, 0.08), 0 0 1px 1px rgba(15, 23, 42, 0.02);
            width: 100%;
            max-width: 440px;
            padding: 2.5rem 2rem;
            position: relative;
            z-index: 10;
        }

        .brand-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 3.25rem;
            height: 3.25rem;
            border-radius: 0.875rem;
            background: linear-gradient(135deg, #2563EB 0%, #1D4ED8 100%);
            color: #FFFFFF;
            margin-bottom: 1.25rem;
            box-shadow: 0 8px 16px -4px rgba(37, 99, 235, 0.35);
        }

        .title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #0F172A;
            letter-spacing: -0.02em;
            margin-bottom: 0.5rem;
        }

        .subtitle {
            font-size: 0.875rem;
            color: #64748B;
            line-height: 1.5;
            margin-bottom: 1.5rem;
        }

        .alert {
            padding: 0.75rem 1rem;
            border-radius: 0.625rem;
            font-size: 0.875rem;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: flex-start;
            gap: 0.5rem;
        }

        .alert-error {
            background-color: #FEF2F2;
            border: 1px solid #FEE2E2;
            color: #991B1B;
        }

        .alert-success {
            background-color: #F0FDF4;
            border: 1px solid #DCFCE7;
            color: #166534;
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 600;
            color: #334155;
            margin-bottom: 0.5rem;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1.5px solid #CBD5E1;
            border-radius: 0.625rem;
            font-size: 1.125rem;
            font-family: inherit;
            color: #0F172A;
            letter-spacing: 0.15em;
            text-align: center;
            font-weight: 700;
            transition: all 0.2s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: #2563EB;
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.12);
        }

        .btn-primary {
            width: 100%;
            padding: 0.875rem 1.25rem;
            border: none;
            border-radius: 0.625rem;
            background: linear-gradient(135deg, #2563EB 0%, #1D4ED8 100%);
            color: #FFFFFF;
            font-size: 0.9375rem;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);
            transition: all 0.2s ease;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #1D4ED8 0%, #1E40AF 100%);
            box-shadow: 0 6px 16px rgba(37, 99, 235, 0.35);
            transform: translateY(-1px);
        }

        .footer-links {
            margin-top: 1.5rem;
            padding-top: 1.25rem;
            border-top: 1px solid #F1F5F9;
            text-align: center;
            font-size: 0.8125rem;
            color: #64748B;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .footer-links a {
            color: #2563EB;
            text-decoration: none;
            font-weight: 600;
        }

        .footer-links a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <main class="auth-card" role="main">
        <div class="brand-badge">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
            </svg>
        </div>

        <h1 class="title">Two-Factor Authentication</h1>
        <p class="subtitle">Enter the 6-digit verification code from your authenticator application (Google Authenticator, Microsoft Authenticator) or an 8-character recovery code.</p>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error" role="alert">
                <span><?= e($error) ?></span>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success" role="status">
                <span><?= e($success) ?></span>
            </div>
        <?php endif; ?>

        <form action="<?= url('/verify-totp') ?>" method="POST" autocomplete="off">
            <input type="hidden" name="_csrf_token" value="<?= e($csrfToken) ?>">

            <div class="form-group">
                <label for="code" class="form-label">Authenticator or Recovery Code</label>
                <input 
                    type="text" 
                    id="code" 
                    name="code" 
                    class="form-control" 
                    placeholder="000000" 
                    maxlength="16" 
                    required 
                    autofocus
                    autocomplete="one-time-code"
                >
            </div>

            <button type="submit" class="btn-primary">Verify &amp; Continue</button>
        </form>

        <div class="footer-links">
            <form action="<?= url('/verify-totp/email-fallback') ?>" method="POST" style="margin: 0;">
                <input type="hidden" name="_csrf_token" value="<?= e($csrfToken) ?>">
                <span>Don't have access to your authenticator app?</span>
                <button type="submit" style="background: none; border: none; color: #2563EB; font-weight: 600; cursor: pointer; text-decoration: underline; font-family: inherit; font-size: inherit;">
                    Send code via registered email instead
                </button>
            </form>
            <div>
                <a href="<?= url('/login') ?>">Cancel and return to sign in</a>
            </div>
        </div>
    </main>
</body>
</html>

