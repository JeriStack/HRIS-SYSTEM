<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>500 Internal Error - <?= e(config('config.app_name', 'Employee HRIS')) ?></title>
    <link rel="stylesheet" href="<?= asset('css/style.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/components.css') ?>">
    <style>
        .error-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            background: var(--color-bg-page);
        }
        .error-card {
            background: var(--color-bg-surface);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-lg);
            max-width: 520px;
            width: 100%;
            padding: 2.5rem;
            text-align: center;
        }
        .error-icon {
            width: 64px;
            height: 64px;
            background: #FEF2F2;
            color: #B91C1C;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin-bottom: 1.25rem;
            border: 2px solid #FECACA;
        }
        .error-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--color-text-main);
            margin: 0 0 0.5rem 0;
        }
        .error-desc {
            font-size: 0.9375rem;
            color: var(--color-text-muted);
            margin: 0 0 1.5rem 0;
            line-height: 1.5;
        }
    </style>
</head>
<body class="error-page">
    <main class="error-card" role="main">
        <div class="error-icon" aria-hidden="true">⚠️</div>
        <div class="badge badge-danger" style="margin-bottom: 1rem;">HTTP 500 &bull; APPLICATION ERROR</div>
        <h1 class="error-title">System Error</h1>
        <p class="error-desc">
            An unexpected error occurred while processing your request. The technical details have been logged for administrative investigation.
        </p>
        <div style="display: flex; gap: 0.75rem; justify-content: center;">
            <a href="<?= url('/dashboard') ?>" class="btn btn-primary">
                Return to Dashboard
            </a>
            <a href="<?= url('/') ?>" class="btn btn-secondary">
                Public Home
            </a>
        </div>
    </main>
</body>
</html>
