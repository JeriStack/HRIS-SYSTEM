<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? '403 Forbidden - Access Denied') ?></title>
    <meta name="color-scheme" content="light dark">
    <script src="<?= asset('js/theme-init.js') ?>"></script>
    <link rel="stylesheet" href="<?= asset('css/style.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/components.css') ?>">
    <style>
        .error-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            background: var(--color-bg-subtle, #F8FAFC);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        }
        .error-card {
            background: var(--color-bg-surface, #FFFFFF);
            border: 1px solid var(--color-border, #E2E8F0);
            border-radius: 1rem;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            max-width: 520px;
            width: 100%;
            padding: 2.5rem;
            text-align: center;
        }
        .error-icon {
            width: 64px;
            height: 64px;
            background: var(--color-error-bg, #FEF2F2);
            color: var(--color-error, #DC2626);
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin-bottom: 1.25rem;
            border: 2px solid var(--color-error-border, #FCA5A5);
        }
        .error-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--color-text-main, #0F172A);
            margin: 0 0 0.5rem 0;
        }
        .error-desc {
            font-size: 0.9375rem;
            color: var(--color-text-muted, #64748B);
            margin: 0 0 1.5rem 0;
            line-height: 1.5;
        }
        .error-badge {
            display: inline-block;
            background: var(--color-bg-muted, #F1F5F9);
            color: var(--color-text-body, #475569);
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            letter-spacing: 0.05em;
        }
        .btn-return {
            display: inline-block;
            padding: 0.625rem 1.25rem;
            background: #4F46E5;
            color: #FFFFFF;
            border-radius: 0.5rem;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.875rem;
            transition: background-color 0.15s ease;
        }
        .btn-return:hover {
            background: #4338CA;
        }
    </style>
</head>
<body class="error-page">
    <main class="error-card" role="main">
        <div class="error-icon" aria-hidden="true">🛡️</div>
        <div class="error-badge">HTTP 403 • FORBIDDEN</div>
        <h1 class="error-title">Access Denied</h1>
        <p class="error-desc">
            <?= e($message ?? 'You do not have the required permissions or role clearance to access this protected area.') ?>
        </p>
        <div>
            <a href="<?= url('/dashboard') ?>" class="btn-return">Return to Dashboard</a>
        </div>
    </main>
</body>
</html>
