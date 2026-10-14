<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($title) ? e($title) . ' - ' : '' ?><?= e(config('config.app_name', 'HRIS Portal')) ?></title>
    <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/components.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/responsive.css')) ?>">
</head>
<body>
    <div class="app-shell">
        <main class="app-main" style="padding: 2rem;">
            <?= $content ?? '' ?>
        </main>
    </div>
</body>
</html>
