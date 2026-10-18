<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'Sign In - Employee HRIS & Leave Management System') ?></title>
    <link rel="stylesheet" href="<?= asset('css/style.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/components.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/responsive.css') ?>">
    <style>
        .login-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            background: radial-gradient(circle at top right, #EEF2FF, #F8FAFC);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        }
        .login-card {
            background: #FFFFFF;
            border: 1px solid #E2E8F0;
            border-radius: 1rem;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            max-width: 440px;
            width: 100%;
            padding: 2.25rem;
        }
        .login-brand {
            text-align: center;
            margin-bottom: 2rem;
        }
        .login-brand-icon {
            width: 48px;
            height: 48px;
            background: #4F46E5;
            color: #FFFFFF;
            border-radius: 0.75rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 0.75rem;
            box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.3);
        }
        .login-brand h1 {
            font-size: 1.35rem;
            font-weight: 700;
            color: #0F172A;
            margin: 0 0 0.35rem 0;
        }
        .login-brand p {
            font-size: 0.8125rem;
            color: #64748B;
            margin: 0;
        }
        .alert-banner {
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1.25rem;
            font-size: 0.875rem;
            display: flex;
            align-items: flex-start;
            gap: 0.5rem;
            line-height: 1.4;
        }
        .alert-error {
            background-color: #FEF2F2;
            border: 1px solid #FCA5A5;
            color: #991B1B;
        }
        .alert-warning {
            background-color: #FFFBEB;
            border: 1px solid #FCD34D;
            color: #92400E;
        }
        .alert-success {
            background-color: #ECFDF5;
            border: 1px solid #6EE7B7;
            color: #065F46;
        }
        .form-group {
            margin-bottom: 1.25rem;
        }
        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 600;
            color: #1E293B;
            margin-bottom: 0.375rem;
        }
        .form-label.required::after {
            content: " *";
            color: #EF4444;
        }
        .form-input {
            width: 100%;
            padding: 0.625rem 0.875rem;
            border: 1px solid #CBD5E1;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            color: #0F172A;
            box-sizing: border-box;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }
        .form-input:focus {
            outline: none;
            border-color: #4F46E5;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.15);
        }
        .btn-submit {
            width: 100%;
            padding: 0.75rem;
            background: #4F46E5;
            color: #FFFFFF;
            border: none;
            border-radius: 0.5rem;
            font-size: 0.9375rem;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.15s ease, transform 0.05s ease;
            box-shadow: 0 2px 4px rgba(79, 70, 229, 0.2);
            margin-top: 0.5rem;
        }
        .btn-submit:hover {
            background: #4338CA;
        }
        .btn-submit:active {
            transform: scale(0.99);
        }
        .btn-submit:disabled {
            background: #94A3B8;
            cursor: not-allowed;
            box-shadow: none;
        }
        .login-footer {
            margin-top: 1.75rem;
            padding-top: 1.25rem;
            border-top: 1px solid #F1F5F9;
            text-align: center;
        }
        .back-link {
            font-size: 0.8125rem;
            color: #4F46E5;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            transition: color 0.15s ease;
        }
        .back-link:hover {
            color: #3730A3;
            text-decoration: underline;
        }
        .security-badge {
            margin-top: 0.875rem;
            font-size: 0.75rem;
            color: #94A3B8;
            line-height: 1.4;
        }
    </style>
</head>
<body class="login-page">

    <main class="login-card" role="main">
        <div class="login-brand">
            <div class="login-brand-icon" aria-hidden="true">H</div>
            <h1>HRIS & Leave System</h1>
            <p>Employee HRIS & Leave Management System</p>
        </div>

        <!-- Alert Notices -->
        <?php if (!empty($error)): ?>
            <div class="alert-banner alert-error" role="alert">
                <span aria-hidden="true">✕</span>
                <div><?= e($error) ?></div>
            </div>
        <?php endif; ?>

        <?php if (!empty($timeout)): ?>
            <div class="alert-banner alert-warning" role="alert">
                <span aria-hidden="true">⚠️</span>
                <div><?= e($timeout) ?></div>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert-banner alert-success" role="alert">
                <span aria-hidden="true">✓</span>
                <div><?= e($success) ?></div>
            </div>
        <?php endif; ?>

        <!-- Authentication Form -->
        <form method="POST" action="<?= url('/login') ?>" id="loginForm" autocomplete="on">
            <!-- CSRF Protection Field -->
            <?= csrf_field() ?>

            <div class="form-group">
                <label for="loginEmail" class="form-label required">Email Address</label>
                <input type="email" 
                       id="loginEmail" 
                       name="email" 
                       class="form-input" 
                       placeholder="e.g. name@company.local" 
                       value="<?= e($oldEmail ?? '') ?>"
                       required 
                       autofocus 
                       autocomplete="email">
            </div>

            <div class="form-group">
                <label for="loginPassword" class="form-label required">Password</label>
                <div style="position: relative;">
                    <input type="password" 
                           id="loginPassword" 
                           name="password" 
                           class="form-input" 
                           style="padding-right: 40px;" 
                           placeholder="Enter your password" 
                           required 
                           autocomplete="current-password">
                    <button type="button" 
                            id="togglePasswordBtn"
                            style="position: absolute; right: 8px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; padding: 4px; font-size: 1rem;" 
                            aria-label="Toggle password visibility">👁️</button>
                </div>
            </div>

            <button type="submit" id="loginSubmitBtn" class="btn-submit">
                Sign In
            </button>
        </form>

        <!-- Secure Form Footer -->
        <div class="login-footer">
            <div>
                <a href="<?= url('/') ?>" class="back-link">
                    ← Back to Public Landing Page
                </a>
            </div>
            <div class="security-badge">
                🔒 Protected by Two-Factor Email OTP Verification • Strict Role-Based Access
            </div>
        </div>
    </main>

    <script>
        // Password Visibility Toggle
        const toggleBtn = document.getElementById('togglePasswordBtn');
        const passInput = document.getElementById('loginPassword');
        if (toggleBtn && passInput) {
            toggleBtn.addEventListener('click', function() {
                const isPassword = passInput.getAttribute('type') === 'password';
                passInput.setAttribute('type', isPassword ? 'text' : 'password');
                toggleBtn.textContent = isPassword ? '🙈' : '👁️';
            });
        }

        // Form Submission UX
        const form = document.getElementById('loginForm');
        const submitBtn = document.getElementById('loginSubmitBtn');
        if (form && submitBtn) {
            form.addEventListener('submit', function() {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Authenticating...';
            });
        }
    </script>
</body>
</html>
