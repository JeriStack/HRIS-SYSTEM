<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 Not Found - <?= e(config('config.app_name', 'NEXORA HR')) ?></title>
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
            background: #EFF6FF;
            color: #1D4ED8;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin-bottom: 1.25rem;
            border: 2px solid #BFDBFE;
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
        <div class="error-icon" aria-hidden="true">🔍</div>
        <div class="badge badge-info" style="margin-bottom: 1rem;">HTTP 404 &bull; RESOURCE NOT FOUND</div>
        <h1 class="error-title">Page Not Found</h1>
        <p class="error-desc">
            The requested page, record, or URL resource could not be found or has been relocated.
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
